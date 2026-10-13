<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Connected accounts: the site as an OAuth client.
 *
 * An operator (or a person, for their own work) connects an outside account -
 * Google, Microsoft, ... - once, in the browser. The tokens are kept here,
 * encrypted with the site's key, and renewed here; a feature that calls the
 * provider asks pg_connect_access_token() or pg_connect_api() and never sees
 * a refresh token. The reverse direction - outside applications connecting to
 * this site - is includes/oauth/.
 *
 * Two kinds of definition, one file each, read from a folder:
 *
 *   includes/connect/providers/<key>.php  where to send the person, where to
 *                                         trade the code, how the provider
 *                                         names itself and its quirks;
 *   includes/connect/purposes/<key>.php   what a feature needs from an
 *                                         account: the scopes per provider
 *                                         and who may own the connection.
 *
 * Adding a provider or a purpose is adding a file: nothing here changes and
 * no column is added.
 *
 * Every provider is the site's own application (registered by the operator
 * in the provider's console, credentials in connect_apps), so the consent
 * screen shows the site's name. The redirect address registered there is
 * pg_connect_redirect_uri(), one address for every provider and purpose.
 *
 * Tables (2026.4.10, 10.72): connect_apps (one row per provider) and
 * connect_accounts (one row per connected account; provider 'service' rows
 * hold tokens a server-to-server integration fetched for itself).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}


/* ---------------------------------------------------------------------------
   Definitions
   --------------------------------------------------------------------------- */

/**
 * Every definition of one kind, read from its folder, by key.
 *
 * The key is the file name. A file that does not return an array, or whose
 * name is not lower-case letters, digits and underscores, is skipped.
 *
 * @param string $kind 'providers' or 'purposes'
 * @return array<string,array>
 */
function pg_connect_definitions($kind)
{
    static $cache = array();

    if (!in_array($kind, array('providers', 'purposes'), true)) {
        return array();
    }

    if (isset($cache[$kind])) {
        return $cache[$kind];
    }

    $definitions = array();

    $files = glob(PG_FUNCTIONS_DIR . '/includes/connect/' . $kind . '/*.php');

    if (is_array($files)) {
        sort($files);

        foreach ($files as $file) {
            $key = basename($file, '.php');

            if (!preg_match('/^[a-z0-9_]{1,40}$/', $key)) {
                continue;
            }

            $definition = include($file);

            if (is_array($definition)) {
                $definition['key'] = $key;
                $definitions[$key] = $definition;
            }
        }
    }

    $cache[$kind] = $definitions;

    return $definitions;
}

function pg_connect_providers()
{
    return pg_connect_definitions('providers');
}

function pg_connect_provider($key)
{
    $providers = pg_connect_providers();

    return isset($providers[$key]) ? $providers[$key] : null;
}

function pg_connect_purposes()
{
    return pg_connect_definitions('purposes');
}

function pg_connect_purpose($key)
{
    $purposes = pg_connect_purposes();

    return isset($purposes[$key]) ? $purposes[$key] : null;
}

/**
 * Whether a purpose can be served by a provider, and by whom.
 *
 * @param array  $purpose
 * @param string $provider_key
 * @param string $owner 'site' or 'person'
 * @return bool
 */
function pg_connect_purpose_allows($purpose, $provider_key, $owner)
{
    if (!is_array($purpose) || !isset($purpose['scopes']) || !is_array($purpose['scopes'])) {
        return false;
    }

    if (!array_key_exists($provider_key, $purpose['scopes'])) {
        return false;
    }

    $allowed = isset($purpose['owner']) ? (string) $purpose['owner'] : 'any';

    return ($allowed === 'any') || ($allowed === $owner);
}


/* ---------------------------------------------------------------------------
   Pure helpers (tests/connect_test.php)
   --------------------------------------------------------------------------- */

function pg_connect_base64url($binary)
{
    return rtrim(strtr(base64_encode($binary), '+/', '-_'), '=');
}

/**
 * A PKCE verifier and its S256 challenge (RFC 7636).
 *
 * @param string|null $verifier given by a test; a fresh one otherwise
 * @return array array('verifier' => string, 'challenge' => string)
 */
function pg_connect_pkce($verifier = null)
{
    if ($verifier === null) {
        $verifier = pg_connect_base64url(random_bytes(48));
    }

    return array(
        'verifier'  => $verifier,
        'challenge' => pg_connect_base64url(hash('sha256', $verifier, true)),
    );
}

/**
 * A scope string or list as a list, with duplicates and empties removed and
 * the order kept.
 *
 * @param string|array $scopes
 * @return array
 */
function pg_connect_scope_list($scopes)
{
    if (!is_array($scopes)) {
        $scopes = preg_split('/\s+/', trim((string) $scopes));
    }

    $list = array();

    foreach ($scopes as $scope) {
        $scope = trim((string) $scope);

        if (($scope !== '') && !in_array($scope, $list, true)) {
            $list[] = $scope;
        }
    }

    return $list;
}

/**
 * The scopes to ask for when a purpose is connected: the provider's identity
 * scopes (who the account is, and lasting access) and the purpose's own.
 *
 * @param array $provider
 * @param array $purpose
 * @return array
 */
function pg_connect_request_scopes($provider, $purpose)
{
    $scopes = isset($provider['identity_scopes']) ? $provider['identity_scopes'] : array();

    if (is_array($purpose) && isset($purpose['scopes'][$provider['key']]) && is_array($purpose['scopes'][$provider['key']])) {
        $scopes = array_merge($scopes, $purpose['scopes'][$provider['key']]);
    }

    return pg_connect_scope_list($scopes);
}

/**
 * The scopes an access token is fetched for.
 *
 * Most providers hand out one access token for everything the account
 * allowed. Microsoft hands out one per resource (Graph, Outlook, ...), so a
 * feature names the scopes it is about to use and the token for them is
 * fetched and cached separately; with none named, the provider's defaults.
 *
 * @param array $provider
 * @param array $scopes
 * @return array
 */
function pg_connect_token_scopes($provider, $scopes)
{
    if (empty($provider['token_per_scope'])) {
        return array();
    }

    $scopes = pg_connect_scope_list($scopes);

    if (!$scopes) {
        $scopes = isset($provider['default_scopes']) ? pg_connect_scope_list($provider['default_scopes']) : array();
    }

    return $scopes;
}

/**
 * Where an access token is cached inside a connection's sealed data.
 *
 * @param array $provider
 * @param array $scopes what pg_connect_token_scopes() returned
 * @return string
 */
function pg_connect_access_key($provider, $scopes)
{
    if (empty($provider['token_per_scope']) || !$scopes) {
        return 'default';
    }

    $scopes = pg_connect_scope_list($scopes);
    sort($scopes);

    return substr(sha1(implode(' ', $scopes)), 0, 16);
}

/**
 * An endpoint of a provider, with {tenant} filled in from the application's
 * options (Microsoft). A tenant that is not a word, a GUID or a domain falls
 * back to the provider's default.
 *
 * @param array  $provider
 * @param array  $app      what pg_connect_app() returned
 * @param string $name     'authorize_url', 'token_url', ...
 * @return string '' when the provider has no such endpoint
 */
function pg_connect_endpoint($provider, $app, $name)
{
    $url = isset($provider[$name]) ? (string) $provider[$name] : '';

    if (($url === '') || (strpos($url, '{tenant}') === false)) {
        return $url;
    }

    $tenant = (is_array($app) && isset($app['options']['tenant'])) ? trim((string) $app['options']['tenant']) : '';

    if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9.-]{0,99}$/', $tenant)) {
        $tenant = isset($provider['default_tenant']) ? (string) $provider['default_tenant'] : 'common';
    }

    return str_replace('{tenant}', rawurlencode($tenant), $url);
}

/**
 * The address the person is sent to on the provider.
 *
 * @param array $provider
 * @param array $app
 * @param array $request redirect_uri, scopes, state, challenge, nonce, login_hint
 * @return string
 */
function pg_connect_authorize_url($provider, $app, $request)
{
    $params = array(
        'response_type'         => 'code',
        'client_id'             => (string) $app['client_id'],
        'redirect_uri'          => (string) $request['redirect_uri'],
        'scope'                 => implode(' ', pg_connect_scope_list($request['scopes'])),
        'state'                 => (string) $request['state'],
        'code_challenge'        => (string) $request['challenge'],
        'code_challenge_method' => 'S256',
    );

    if (isset($request['nonce']) && ((string) $request['nonce'] !== '')) {
        $params['nonce'] = (string) $request['nonce'];
    }

    if (isset($request['login_hint']) && ((string) $request['login_hint'] !== '')) {
        $params['login_hint'] = (string) $request['login_hint'];
    }

    if (isset($provider['authorize_params']) && is_array($provider['authorize_params'])) {
        foreach ($provider['authorize_params'] as $name => $value) {
            if (!isset($params[$name])) {
                $params[$name] = (string) $value;
            }
        }
    }

    $url = pg_connect_endpoint($provider, $app, 'authorize_url');

    return $url . ((strpos($url, '?') === false) ? '?' : '&') . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
}

/**
 * A token endpoint answer, in the shape the rest of this file reads.
 *
 * @param array $data the decoded JSON
 * @param int   $now
 * @return array
 */
function pg_connect_token_response($data, $now)
{
    $expires_in = isset($data['expires_in']) ? (int) $data['expires_in'] : 0;

    if ($expires_in <= 0) {
        $expires_in = 3600;
    }

    return array(
        'access_token'  => isset($data['access_token']) ? (string) $data['access_token'] : '',
        'refresh_token' => isset($data['refresh_token']) ? (string) $data['refresh_token'] : '',
        'expires_at'    => (int) $now + $expires_in,
        'scopes'        => pg_connect_scope_list(isset($data['scope']) ? $data['scope'] : ''),
        'id_token'      => isset($data['id_token']) ? (string) $data['id_token'] : '',
    );
}

/**
 * Whether a provider's own name for itself is the one expected: the iss of an
 * id_token, or the iss parameter of the redirect (RFC 9207).
 *
 * @param array  $provider
 * @param string $issuer
 * @return bool
 */
function pg_connect_issuer_matches($provider, $issuer)
{
    $issuer = (string) $issuer;

    if ($issuer === '') {
        return false;
    }

    if (isset($provider['issuers']) && is_array($provider['issuers']) && in_array($issuer, $provider['issuers'], true)) {
        return true;
    }

    return isset($provider['issuer_pattern']) && (preg_match($provider['issuer_pattern'], $issuer) === 1);
}

/**
 * What is wrong with an id_token's claims, or '' when nothing is.
 *
 * The token itself comes straight from the provider's token endpoint over a
 * verified TLS connection, in answer to a request that carried the client
 * secret, so its signature is not checked (OpenID Connect Core 3.1.3.7); the
 * claims still have to name this provider, this application and this
 * request.
 *
 * @param array  $provider
 * @param array  $claims
 * @param string $client_id
 * @param string $nonce the one sent with the request
 * @param int    $now
 * @return string
 */
function pg_connect_claims_problem($provider, $claims, $client_id, $nonce, $now)
{
    if (!is_array($claims)) {
        return lang('The identity token could not be read.');
    }

    if (!pg_connect_issuer_matches($provider, isset($claims['iss']) ? $claims['iss'] : '')) {
        return lang('The identity token was issued by someone else.');
    }

    $audience = isset($claims['aud']) ? $claims['aud'] : '';
    $audiences = is_array($audience) ? $audience : array($audience);

    if (!in_array((string) $client_id, array_map('strval', $audiences), true)) {
        return lang('The identity token was issued to another application.');
    }

    if (isset($claims['exp']) && ((int) $claims['exp'] < ((int) $now - 300))) {
        return lang('The identity token has expired.');
    }

    if (((string) $nonce !== '') && (!isset($claims['nonce']) || !hash_equals((string) $nonce, (string) $claims['nonce']))) {
        return lang('The identity token does not belong to this request.');
    }

    if (!isset($claims['sub']) || ((string) $claims['sub'] === '')) {
        return lang('The identity token does not name the account.');
    }

    return '';
}

/**
 * Who the account is, from id_token claims or a userinfo answer.
 *
 * @param array $claims
 * @return array array('id' => string, 'label' => string)
 */
function pg_connect_identity($claims)
{
    $label = '';

    foreach (array('email', 'preferred_username', 'name') as $field) {
        if (isset($claims[$field]) && (trim((string) $claims[$field]) !== '')) {
            $label = trim((string) $claims[$field]);
            break;
        }
    }

    return array(
        'id'    => isset($claims['sub']) ? (string) $claims['sub'] : '',
        'label' => function_exists('mb_substr') ? mb_substr($label, 0, 255) : substr($label, 0, 255),
    );
}

/**
 * The screen a finished connection returns to: a script in the software
 * directory, with an optional query and fragment. Anything else is the
 * Connected accounts screen, so the address cannot leave the panel.
 *
 * @param string $return
 * @return string
 */
function pg_connect_return_path($return)
{
    $return = is_string($return) ? $return : '';

    if (preg_match('/^[a-z0-9_]{1,60}\.php(\?[A-Za-z0-9_=&%.\-]{0,200})?(#[A-Za-z0-9_\-]{0,60})?$/', $return)) {
        return $return;
    }

    return 'connections.php';
}

/**
 * The access token cached under a key, or '' when there is none or it ends
 * within the margin.
 *
 * @param array  $secret the connection's unsealed data
 * @param string $key
 * @param int    $now
 * @param int    $margin seconds
 * @return string
 */
function pg_connect_cached_access($secret, $key, $now, $margin = 120)
{
    if (!isset($secret['access'][$key]) || !is_array($secret['access'][$key])) {
        return '';
    }

    $entry = $secret['access'][$key];

    if (!isset($entry['token'], $entry['expires']) || ((int) $entry['expires'] <= (int) $now + $margin)) {
        return '';
    }

    return (string) $entry['token'];
}

/**
 * The sealed data with one access token stored and the expired ones dropped.
 *
 * @param array  $secret
 * @param string $key
 * @param string $token
 * @param int    $expires
 * @param int    $now
 * @return array
 */
function pg_connect_store_access($secret, $key, $token, $expires, $now)
{
    $access = (isset($secret['access']) && is_array($secret['access'])) ? $secret['access'] : array();

    foreach ($access as $name => $entry) {
        if (!is_array($entry) || !isset($entry['expires']) || ((int) $entry['expires'] <= (int) $now)) {
            unset($access[$name]);
        }
    }

    $access[$key] = array('token' => (string) $token, 'expires' => (int) $expires);

    $secret['access'] = $access;

    return $secret;
}

/**
 * An error text cut to what fits the last_error column, on one line.
 *
 * @param string $text
 * @return string
 */
function pg_connect_clip($text)
{
    $text = trim(preg_replace('/\s+/', ' ', strip_tags((string) $text)));

    return function_exists('mb_substr') ? mb_substr($text, 0, 250) : substr($text, 0, 250);
}


/* ---------------------------------------------------------------------------
   Sealing
   --------------------------------------------------------------------------- */

/**
 * Whether the tables are there: code can arrive before the database upgrade
 * (10.72) has run, and every feature asking about connections then hears
 * "none" instead of failing on a missing table.
 *
 * @return bool
 */
function pg_connect_schema_ready()
{
    return pg_schema_has('connect_accounts') && pg_schema_has('connect_apps');
}

/**
 * Whether tokens can be kept: the site has an encryption key.
 *
 * @return bool
 */
function pg_connect_can_seal()
{
    return defined('ENCRYPTION_KEY') && ((string) ENCRYPTION_KEY !== '') && function_exists('openssl_encrypt');
}

/**
 * Data encrypted with the site's key, as "<ciphertext>:<iv>".
 *
 * @param array $data
 * @return string '' when the site has no key
 */
function pg_connect_seal($data)
{
    if (!pg_connect_can_seal()) {
        return '';
    }

    $json = json_encode($data);

    if ($json === false) {
        return '';
    }

    $sealed = encrypt_string_with_iv($json);

    return $sealed[0] . ':' . $sealed[1];
}

/**
 * @param string $stored
 * @return array
 */
function pg_connect_unseal($stored)
{
    $stored = (string) $stored;

    if (($stored === '') || !pg_connect_can_seal()) {
        return array();
    }

    $parts = explode(':', $stored, 2);

    if (count($parts) !== 2) {
        return array();
    }

    $data = json_decode(decode_ssl_keys($parts[0], $parts[1]), true);

    return is_array($data) ? $data : array();
}


/* ---------------------------------------------------------------------------
   Applications (connect_apps)
   --------------------------------------------------------------------------- */

/**
 * The address to register in every provider's console.
 *
 * @return string
 */
function pg_connect_redirect_uri()
{
    return URL_SCHEME . HOSTNAME_SETTING . PATH . SOFTWARE_DIRECTORY . '/connect.php';
}

/**
 * A provider's application on this site, or null when none is set up.
 *
 * Google falls back to the "Sign in with Google" application of the security
 * settings, so a site that has one does not enter it twice.
 *
 * @param string $provider_key
 * @return array|null client_id, client_secret, options, enabled, source
 */
function pg_connect_app($provider_key)
{
    $row = !pg_connect_schema_ready() ? null : db_item(
        "SELECT client_id, secret, options, enabled
        FROM connect_apps
        WHERE provider = '" . e($provider_key) . "'");

    if (is_array($row)) {
        $secret = pg_connect_unseal($row['secret']);
        $options = json_decode((string) $row['options'], true);

        return array(
            'client_id'     => (string) $row['client_id'],
            'client_secret' => isset($secret['client_secret']) ? (string) $secret['client_secret'] : '',
            'secret_stored' => ((string) $row['secret'] !== ''),
            'options'       => is_array($options) ? $options : array(),
            'enabled'       => ((int) $row['enabled'] === 1),
            'source'        => 'connect',
        );
    }

    if (($provider_key === 'google') && defined('OAUTH_GOOGLE_CLIENT_ID') && (OAUTH_GOOGLE_CLIENT_ID !== '')) {
        $secret_row = db_item("SELECT oauth_google_client_secret, oauth_google_secret_iv FROM config");

        $client_secret = is_array($secret_row)
            ? decode_ssl_keys((string) $secret_row['oauth_google_client_secret'], (string) $secret_row['oauth_google_secret_iv'])
            : '';

        return array(
            'client_id'     => (string) OAUTH_GOOGLE_CLIENT_ID,
            'client_secret' => (string) $client_secret,
            'secret_stored' => ((string) $client_secret !== ''),
            'options'       => array(),
            'enabled'       => true,
            'source'        => 'signin',
        );
    }

    return null;
}

/**
 * Whether connections can be started and renewed through a provider.
 *
 * @param array|null $app
 * @return bool
 */
function pg_connect_app_ready($app)
{
    return is_array($app) && $app['enabled'] && ($app['client_id'] !== '') && ($app['client_secret'] !== '');
}

/**
 * Saves a provider's application. A secret of null or '' keeps the stored one.
 *
 * @param string      $provider_key
 * @param string      $client_id
 * @param string|null $client_secret
 * @param array       $options non-secret settings (Microsoft: tenant)
 * @param bool        $enabled
 * @param int         $user_id
 * @return bool false when the site has no encryption key
 */
function pg_connect_app_save($provider_key, $client_id, $client_secret, $options, $enabled, $user_id)
{
    if (!pg_connect_can_seal()) {
        return false;
    }

    $existing = db_item("SELECT secret FROM connect_apps WHERE provider = '" . e($provider_key) . "'");

    $secret = is_array($existing) ? pg_connect_unseal($existing['secret']) : array();

    if (($client_secret !== null) && ((string) $client_secret !== '')) {
        $secret['client_secret'] = (string) $client_secret;
    }

    $sealed = $secret ? pg_connect_seal($secret) : '';

    $columns = "client_id = '" . e(trim((string) $client_id)) . "',
        secret = '" . e($sealed) . "',
        options = '" . e(json_encode(is_array($options) ? $options : array())) . "',
        enabled = '" . ($enabled ? 1 : 0) . "',
        updated_user_id = '" . (int) $user_id . "',
        updated_timestamp = UNIX_TIMESTAMP()";

    if (is_array($existing)) {
        db("UPDATE connect_apps SET " . $columns . " WHERE provider = '" . e($provider_key) . "'");
    } else {
        db("INSERT INTO connect_apps SET provider = '" . e($provider_key) . "', " . $columns);
    }

    return true;
}


/* ---------------------------------------------------------------------------
   Connections (connect_accounts)
   --------------------------------------------------------------------------- */

/**
 * A connection row as the screens read it: no sealed data.
 *
 * @param array $row
 * @return array
 */
function pg_connect_account_public($row)
{
    return array(
        'id'                  => (int) $row['id'],
        'provider'            => (string) $row['provider'],
        'user_id'             => (int) $row['user_id'],
        'external_id'         => (string) $row['external_id'],
        'label'               => (string) $row['label'],
        'purposes'            => pg_connect_scope_list(str_replace(',', ' ', (string) $row['purposes'])),
        'scopes'              => pg_connect_scope_list((string) $row['scopes']),
        'status'              => (string) $row['status'],
        'last_error'          => (string) $row['last_error'],
        'created_user_id'     => (int) $row['created_user_id'],
        'created_timestamp'   => (int) $row['created_timestamp'],
        'refreshed_timestamp' => (int) $row['refreshed_timestamp'],
        'last_used_timestamp' => (int) $row['last_used_timestamp'],
    );
}

/**
 * Connected accounts, newest first. Service tokens are not listed.
 *
 * @param array $filter optional 'provider', 'user_id' (0 for the site's own),
 *                      'purpose'
 * @return array
 */
function pg_connect_accounts($filter = array())
{
    if (!pg_connect_schema_ready()) {
        return array();
    }

    $where = array("provider <> 'service'");

    if (isset($filter['provider'])) {
        $where[] = "provider = '" . e($filter['provider']) . "'";
    }

    if (isset($filter['user_id'])) {
        $where[] = "user_id = '" . (int) $filter['user_id'] . "'";
    }

    if (isset($filter['purpose'])) {
        $where[] = "FIND_IN_SET('" . e($filter['purpose']) . "', purposes) > 0";
    }

    $rows = db_items(
        "SELECT id, provider, user_id, external_id, label, purposes, scopes, status, last_error,
            created_user_id, created_timestamp, refreshed_timestamp, last_used_timestamp
        FROM connect_accounts
        WHERE " . implode(' AND ', $where) . "
        ORDER BY id DESC");

    $accounts = array();

    foreach ($rows as $row) {
        $accounts[] = pg_connect_account_public($row);
    }

    return $accounts;
}

/**
 * One connected account, or null.
 *
 * @param int $id
 * @return array|null
 */
function pg_connect_account($id)
{
    if (!pg_connect_schema_ready()) {
        return null;
    }

    $row = db_item(
        "SELECT id, provider, user_id, external_id, label, purposes, scopes, status, last_error,
            created_user_id, created_timestamp, refreshed_timestamp, last_used_timestamp
        FROM connect_accounts
        WHERE id = '" . (int) $id . "' AND provider <> 'service'");

    return is_array($row) ? pg_connect_account_public($row) : null;
}

/**
 * Marks a connection as needing to be connected again and says so in the log.
 *
 * @param array  $row
 * @param string $reason
 */
function pg_connect_mark_reauth($row, $reason)
{
    db(
        "UPDATE connect_accounts
        SET status = 'reauth', last_error = '" . e(pg_connect_clip($reason)) . "'
        WHERE id = '" . (int) $row['id'] . "'");

    if (function_exists('log_activity')) {
        log_activity(lang(array(
            'string' => 'the connected account {var:1} ({var:2}) stopped working and has to be connected again: {var:3}',
            'vars'   => array((string) $row['label'], (string) $row['provider'], pg_connect_clip($reason)))), '');
    }
}

/**
 * Ends a connection: the provider is told when it can be (best effort), then
 * the row is deleted.
 *
 * @param int $id
 * @return array|null the connection that was ended
 */
function pg_connect_disconnect($id)
{
    $row = db_item("SELECT * FROM connect_accounts WHERE id = '" . (int) $id . "' AND provider <> 'service'");

    if (!is_array($row)) {
        return null;
    }

    $provider = pg_connect_provider($row['provider']);
    $app = pg_connect_app($row['provider']);
    $secret = pg_connect_unseal($row['secret']);

    $token = isset($secret['refresh_token']) ? (string) $secret['refresh_token'] : '';

    if (is_array($provider) && ($token !== '') && (pg_connect_endpoint($provider, $app, 'revoke_url') !== '')) {
        require_once(PG_FUNCTIONS_DIR . '/includes/api/outbound/http.php');

        api_http_request('POST', pg_connect_endpoint($provider, $app, 'revoke_url'), array(
            'body'    => http_build_query(array('token' => $token), '', '&'),
            'headers' => array('Content-Type: application/x-www-form-urlencoded'),
            'timeout' => 10,
            'agent'   => 'connect',
        ));
    }

    db("DELETE FROM connect_accounts WHERE id = '" . (int) $row['id'] . "'");

    return pg_connect_account_public($row);
}


/* ---------------------------------------------------------------------------
   Tokens
   --------------------------------------------------------------------------- */

/**
 * A request to a provider's token endpoint.
 *
 * @param array $provider
 * @param array $app
 * @param array $params grant_type and the rest; client authentication is added
 * @return array ok, data, error_code, error
 */
function pg_connect_token_request($provider, $app, $params)
{
    require_once(PG_FUNCTIONS_DIR . '/includes/api/outbound/http.php');

    $headers = array('Content-Type: application/x-www-form-urlencoded', 'Accept: application/json');

    $params['client_id'] = $app['client_id'];

    if (isset($provider['client_auth']) && ($provider['client_auth'] === 'basic')) {
        $headers[] = 'Authorization: Basic ' . base64_encode(rawurlencode($app['client_id']) . ':' . rawurlencode($app['client_secret']));
    } else {
        $params['client_secret'] = $app['client_secret'];
    }

    $result = api_http_request('POST', pg_connect_endpoint($provider, $app, 'token_url'), array(
        'body'     => http_build_query($params, '', '&'),
        'headers'  => $headers,
        'timeout'  => 20,
        'max_body' => 65536,
        'agent'    => 'connect',
    ));

    $data = json_decode((string) $result['body'], true);

    if ($result['ok'] && is_array($data) && !empty($data['access_token'])) {
        return array('ok' => true, 'data' => $data, 'error_code' => '', 'error' => '');
    }

    $code = (is_array($data) && isset($data['error']) && is_string($data['error'])) ? $data['error'] : '';

    $description = (is_array($data) && isset($data['error_description']) && is_string($data['error_description']))
        ? $data['error_description']
        : (($result['error'] !== '') ? $result['error'] : ('HTTP ' . (int) $result['status']));

    return array(
        'ok'         => false,
        'data'       => array(),
        'error_code' => $code,
        'error'      => pg_connect_clip((($code !== '') ? $code . ': ' : '') . $description),
    );
}

/**
 * A valid access token for a connection, renewed when it is about to end.
 *
 * Renewal runs under a named database lock and reads the row again inside it:
 * Microsoft and Dropbox replace the refresh token on every use, so two
 * requests renewing at once would leave one of them holding a token the
 * provider already retired - and the connection broken.
 *
 * @param int   $connection_id
 * @param array $scopes Microsoft: the scopes the token is for; others ignore it
 * @param bool  $force renew even when the cached token is still valid
 * @return array ok, token, error
 */
function pg_connect_access_token($connection_id, $scopes = array(), $force = false)
{
    $fail = function ($error) {
        return array('ok' => false, 'token' => '', 'error' => $error);
    };

    if (!pg_connect_schema_ready()) {
        return $fail(lang('The connected account was not found.'));
    }

    $read = function () use ($connection_id) {
        return db_item("SELECT * FROM connect_accounts WHERE id = '" . (int) $connection_id . "' AND provider <> 'service'");
    };

    $row = $read();

    if (!is_array($row)) {
        return $fail(lang('The connected account was not found.'));
    }

    $provider = pg_connect_provider($row['provider']);

    if (!is_array($provider)) {
        return $fail(lang('The provider of this connection is not known to this version.'));
    }

    if ($row['status'] !== 'active') {
        return $fail(($row['last_error'] !== '') ? $row['last_error'] : lang('The account has to be connected again.'));
    }

    $scopes = pg_connect_token_scopes($provider, $scopes);
    $key = pg_connect_access_key($provider, $scopes);
    $now = time();

    $secret = pg_connect_unseal($row['secret']);
    $seen = pg_connect_cached_access($secret, $key, $now);

    if (!$force && ($seen !== '')) {
        pg_connect_touch($row);

        return array('ok' => true, 'token' => $seen, 'error' => '');
    }

    $lock = 'pg_connect_' . (int) $row['id'];
    $locked = ((int) db_value("SELECT GET_LOCK('" . e($lock) . "', 15)") === 1);

    // Another request may have renewed it while this one waited.
    $row = $read();
    $secret = is_array($row) ? pg_connect_unseal($row['secret']) : array();
    $cached = pg_connect_cached_access($secret, $key, $now);

    if (is_array($row) && ($cached !== '') && (!$force || ($cached !== $seen))) {
        if ($locked) {
            db("SELECT RELEASE_LOCK('" . e($lock) . "')");
        }

        pg_connect_touch($row);

        return array('ok' => true, 'token' => $cached, 'error' => '');
    }

    $result = is_array($row)
        ? pg_connect_refresh($row, $provider, $secret, $scopes, $key)
        : $fail(lang('The connected account was not found.'));

    if ($locked) {
        db("SELECT RELEASE_LOCK('" . e($lock) . "')");
    }

    return $result;
}

/**
 * Renews a connection's access token with its refresh token. Called under
 * the lock pg_connect_access_token() holds.
 *
 * @return array ok, token, error
 */
function pg_connect_refresh($row, $provider, $secret, $scopes, $key)
{
    $refresh_token = isset($secret['refresh_token']) ? (string) $secret['refresh_token'] : '';

    if ($refresh_token === '') {
        pg_connect_mark_reauth($row, lang('The provider gave no lasting permission.'));

        return array('ok' => false, 'token' => '', 'error' => lang('The account has to be connected again.'));
    }

    $app = pg_connect_app($row['provider']);

    if (!pg_connect_app_ready($app)) {
        $error = lang('The application for this provider is not set up on Connected accounts.');

        db("UPDATE connect_accounts SET last_error = '" . e($error) . "' WHERE id = '" . (int) $row['id'] . "'");

        return array('ok' => false, 'token' => '', 'error' => $error);
    }

    $params = array('grant_type' => 'refresh_token', 'refresh_token' => $refresh_token);

    if ($scopes) {
        $params['scope'] = implode(' ', pg_connect_scope_list(array_merge($scopes, array('offline_access'))));
    }

    $response = pg_connect_token_request($provider, $app, $params);

    if (!$response['ok']) {
        // invalid_grant is the provider saying the permission itself is gone:
        // withdrawn by the person, expired, or the password changed. Anything
        // else (a timeout, a 500) is a bad moment and the next call retries.
        if ($response['error_code'] === 'invalid_grant') {
            pg_connect_mark_reauth($row, $response['error']);
        } else {
            db("UPDATE connect_accounts SET last_error = '" . e($response['error']) . "' WHERE id = '" . (int) $row['id'] . "'");
        }

        return array('ok' => false, 'token' => '', 'error' => $response['error']);
    }

    $now = time();
    $parsed = pg_connect_token_response($response['data'], $now);

    $secret = pg_connect_store_access($secret, $key, $parsed['access_token'], $parsed['expires_at'], $now);

    if ($parsed['refresh_token'] !== '') {
        $secret['refresh_token'] = $parsed['refresh_token'];
    }

    db(
        "UPDATE connect_accounts
        SET
            secret = '" . e(pg_connect_seal($secret)) . "',
            last_error = '',
            refreshed_timestamp = '" . $now . "',
            last_used_timestamp = '" . $now . "'
        WHERE id = '" . (int) $row['id'] . "'");

    return array('ok' => true, 'token' => $parsed['access_token'], 'error' => '');
}

/**
 * Notes that a connection was used, at most once a minute.
 *
 * @param array $row
 */
function pg_connect_touch($row)
{
    if ((int) $row['last_used_timestamp'] < (time() - 60)) {
        db("UPDATE connect_accounts SET last_used_timestamp = UNIX_TIMESTAMP() WHERE id = '" . (int) $row['id'] . "'");
    }
}

/**
 * A call to a provider's API with a connection's access token. A 401 renews
 * the token once and repeats the call.
 *
 * @param int    $connection_id
 * @param string $method
 * @param string $url     https only (api_http_request())
 * @param array  $options api_http_request() options; max_body defaults to 1 MB
 * @param array  $scopes  Microsoft: the scopes the call needs
 * @return array ok, status, body, data (decoded JSON or null), error
 */
function pg_connect_api($connection_id, $method, $url, $options = array(), $scopes = array())
{
    require_once(PG_FUNCTIONS_DIR . '/includes/api/outbound/http.php');

    if (!isset($options['max_body'])) {
        $options['max_body'] = 1048576;
    }

    if (!isset($options['agent'])) {
        $options['agent'] = 'connect';
    }

    $headers = isset($options['headers']) ? $options['headers'] : array();

    $result = array('ok' => false, 'status' => 0, 'body' => '', 'data' => null, 'error' => '');

    for ($attempt = 0; $attempt < 2; $attempt++) {
        $token = pg_connect_access_token($connection_id, $scopes, $attempt > 0);

        if (!$token['ok']) {
            $result['error'] = $token['error'];

            return $result;
        }

        $options['headers'] = array_merge($headers, array('Authorization: Bearer ' . $token['token']));

        $result = api_http_request($method, $url, $options);

        if ((int) $result['status'] !== 401) {
            break;
        }
    }

    $result['data'] = json_decode((string) $result['body'], true);

    return $result;
}


/* ---------------------------------------------------------------------------
   Connecting
   --------------------------------------------------------------------------- */

/**
 * Begins connecting an account: the address to send the browser to.
 *
 * The state, the PKCE verifier and the nonce stay in the person's session;
 * the callback (connect.php) must come back in the same session.
 *
 * @param string $provider_key
 * @param string $purpose_key
 * @param string $owner         'site' or 'person'
 * @param array  $user          the signed-in person
 * @param string $return        the screen to come back to
 * @param int    $connection_id an existing connection to renew, or 0
 * @return array ok, url, error
 */
function pg_connect_start($provider_key, $purpose_key, $owner, $user, $return, $connection_id = 0)
{
    $fail = function ($error) {
        return array('ok' => false, 'url' => '', 'error' => $error);
    };

    if (!pg_connect_can_seal()) {
        return $fail(lang('This site has no encryption key, so it cannot keep the permissions of a connected account.'));
    }

    $provider = pg_connect_provider($provider_key);
    $purpose = pg_connect_purpose($purpose_key);

    if (!is_array($provider) || !pg_connect_purpose_allows($purpose, $provider_key, $owner)) {
        return $fail(lang('This kind of connection is not available.'));
    }

    $app = pg_connect_app($provider_key);

    if (!pg_connect_app_ready($app)) {
        return $fail(lang(array(
            'string' => 'The {var:1} application is not set up yet. Enter it on Connected accounts first.',
            'vars'   => $provider['label'])));
    }

    $login_hint = '';
    $purpose_keys = array($purpose_key);
    $scopes = pg_connect_request_scopes($provider, $purpose);

    // Connecting an account again asks for everything it already serves, so
    // a renewed permission is not narrower than the one it replaces.
    if ($connection_id > 0) {
        $existing = pg_connect_account($connection_id);

        if (is_array($existing) && ($existing['provider'] === $provider_key)) {
            $login_hint = $existing['label'];

            foreach ($existing['purposes'] as $existing_purpose_key) {
                $existing_purpose = pg_connect_purpose($existing_purpose_key);

                if (pg_connect_purpose_allows($existing_purpose, $provider_key, $owner)) {
                    $purpose_keys[] = $existing_purpose_key;
                    $scopes = array_merge($scopes, pg_connect_request_scopes($provider, $existing_purpose));
                }
            }
        }
    }

    $state = bin2hex(random_bytes(16));
    $pkce = pg_connect_pkce();
    $nonce = bin2hex(random_bytes(16));

    $pending = (isset($_SESSION['software']['connect']) && is_array($_SESSION['software']['connect']))
        ? $_SESSION['software']['connect']
        : array();

    // A handful at most: a person who opens several tabs gets each of them
    // back, and abandoned attempts do not pile up in the session.
    while (count($pending) >= 5) {
        array_shift($pending);
    }

    $pending[$state] = array(
        'provider' => $provider_key,
        'purpose'  => $purpose_key,
        'purposes' => pg_connect_scope_list($purpose_keys),
        'owner'    => $owner,
        'user_id'  => (int) $user['id'],
        'return'   => pg_connect_return_path($return),
        'verifier' => $pkce['verifier'],
        'nonce'    => $nonce,
        'started'  => time(),
    );

    $_SESSION['software']['connect'] = $pending;

    return array(
        'ok'    => true,
        'url'   => pg_connect_authorize_url($provider, $app, array(
            'redirect_uri' => pg_connect_redirect_uri(),
            'scopes'       => pg_connect_scope_list($scopes),
            'state'        => $state,
            'challenge'    => $pkce['challenge'],
            'nonce'        => $nonce,
            'login_hint'   => $login_hint,
        )),
        'error' => '',
    );
}

/**
 * Finishes connecting an account, from the provider's redirect.
 *
 * @param array $query the callback's query parameters
 * @param array $user  the signed-in person
 * @return array ok, error, return, connection_id, label
 */
function pg_connect_finish($query, $user)
{
    $state = (isset($query['state']) && is_string($query['state'])) ? $query['state'] : '';

    $pending = null;

    if (($state !== '') && isset($_SESSION['software']['connect'][$state])) {
        $pending = $_SESSION['software']['connect'][$state];
        unset($_SESSION['software']['connect'][$state]);
    }

    $fail = function ($error, $return = 'connections.php') {
        return array('ok' => false, 'error' => $error, 'return' => $return, 'connection_id' => 0, 'label' => '');
    };

    if (!is_array($pending) || ((int) $pending['user_id'] !== (int) $user['id'])) {
        return $fail(lang('The connection could not be verified. Please start it again.'));
    }

    $return = $pending['return'];

    if ((int) $pending['started'] < (time() - 900)) {
        return $fail(lang('The connection took too long. Please start it again.'), $return);
    }

    $provider = pg_connect_provider($pending['provider']);
    $purpose = pg_connect_purpose($pending['purpose']);
    $app = pg_connect_app($pending['provider']);

    if (!is_array($provider) || !is_array($purpose) || !pg_connect_app_ready($app)) {
        return $fail(lang('This kind of connection is not available.'), $return);
    }

    if (isset($query['error'])) {
        $error = is_string($query['error']) ? $query['error'] : '';

        return $fail(($error === 'access_denied')
            ? lang('The permission was not given, so nothing was connected.')
            : lang(array('string' => '{var:1} did not connect the account: {var:2}', 'vars' => array(
                $provider['label'],
                pg_connect_clip($error . ((isset($query['error_description']) && is_string($query['error_description'])) ? ' - ' . $query['error_description'] : '')))))
            , $return);
    }

    // RFC 9207: when the provider names itself on the redirect, it has to be
    // the provider this attempt was sent to. One redirect address serves
    // every provider, so this is what tells them apart.
    if (isset($query['iss']) && !pg_connect_issuer_matches($provider, is_string($query['iss']) ? $query['iss'] : '')) {
        return $fail(lang('The connection could not be verified. Please start it again.'), $return);
    }

    $code = (isset($query['code']) && is_string($query['code'])) ? $query['code'] : '';

    if ($code === '') {
        return $fail(lang('The connection could not be verified. Please start it again.'), $return);
    }

    $response = pg_connect_token_request($provider, $app, array(
        'grant_type'    => 'authorization_code',
        'code'          => $code,
        'redirect_uri'  => pg_connect_redirect_uri(),
        'code_verifier' => $pending['verifier'],
    ));

    if (!$response['ok']) {
        if (function_exists('log_activity')) {
            log_activity(lang(array(
                'string' => 'connecting a {var:1} account failed ({var:2})',
                'vars'   => array($provider['label'], $response['error']))), '');
        }

        return $fail(lang(array(
            'string' => '{var:1} did not connect the account: {var:2}',
            'vars'   => array($provider['label'], $response['error']))), $return);
    }

    $now = time();
    $tokens = pg_connect_token_response($response['data'], $now);

    $claims = ($tokens['id_token'] !== '') ? pg_google_jwt_payload($tokens['id_token']) : null;

    if (is_array($claims)) {
        $problem = pg_connect_claims_problem($provider, $claims, $app['client_id'], $pending['nonce'], $now);

        if ($problem !== '') {
            return $fail(lang(array(
                'string' => '{var:1} did not connect the account: {var:2}',
                'vars'   => array($provider['label'], $problem))), $return);
        }
    } elseif (pg_connect_endpoint($provider, $app, 'userinfo_url') !== '') {
        $info = api_http_request('GET', pg_connect_endpoint($provider, $app, 'userinfo_url'), array(
            'headers'  => array('Authorization: Bearer ' . $tokens['access_token'], 'Accept: application/json'),
            'timeout'  => 15,
            'max_body' => 65536,
            'agent'    => 'connect',
        ));

        $claims = $info['ok'] ? json_decode((string) $info['body'], true) : null;
    }

    $identity = pg_connect_identity(is_array($claims) ? $claims : array());

    if ($identity['id'] === '') {
        return $fail(lang(array(
            'string' => '{var:1} did not say which account was connected. Please try again.',
            'vars'   => $provider['label'])), $return);
    }

    $owner_id = ($pending['owner'] === 'person') ? (int) $user['id'] : 0;

    $existing = db_item(
        "SELECT * FROM connect_accounts
        WHERE
            provider = '" . e($provider['key']) . "'
            AND user_id = '" . $owner_id . "'
            AND external_id = '" . e($identity['id']) . "'");

    $secret = is_array($existing) ? pg_connect_unseal($existing['secret']) : array();

    if ($tokens['refresh_token'] !== '') {
        $secret['refresh_token'] = $tokens['refresh_token'];
    }

    if (!isset($secret['refresh_token']) || ($secret['refresh_token'] === '')) {
        return $fail(lang(array(
            'string' => '{var:1} gave no lasting permission, so the account could not be kept connected. Remove this site from the account\'s connected apps and try again.',
            'vars'   => $provider['label'])), $return);
    }

    // A provider that issues one token per resource answers the code with a
    // token for whichever resource it picked; the first call fetches the one
    // it needs instead of trusting a cache entry that may name the wrong one.
    if (empty($provider['token_per_scope'])) {
        $secret = pg_connect_store_access($secret, 'default', $tokens['access_token'], $tokens['expires_at'], $now);
    }

    // What the account now allows. A provider with one token per resource
    // lists only that token's resource in its answer, though the person
    // agreed to everything asked for on one screen, so the request counts.
    $requested = pg_connect_request_scopes($provider, $purpose);

    if (!$tokens['scopes']) {
        $granted = $requested;
    } elseif (!empty($provider['token_per_scope'])) {
        $granted = pg_connect_scope_list(array_merge($requested, $tokens['scopes']));
    } else {
        $granted = $tokens['scopes'];
    }

    $purposes = (isset($pending['purposes']) && is_array($pending['purposes'])) ? $pending['purposes'] : array($purpose['key']);
    $scopes = $granted;

    if (is_array($existing)) {
        $purposes = pg_connect_scope_list(array_merge(pg_connect_scope_list(str_replace(',', ' ', (string) $existing['purposes'])), $purposes));
        $scopes = pg_connect_scope_list(array_merge(pg_connect_scope_list((string) $existing['scopes']), $granted));
    }

    $columns = "label = '" . e($identity['label']) . "',
        purposes = '" . e(implode(',', $purposes)) . "',
        scopes = '" . e(implode(' ', $scopes)) . "',
        secret = '" . e(pg_connect_seal($secret)) . "',
        status = 'active',
        last_error = '',
        refreshed_timestamp = '" . $now . "'";

    if (is_array($existing)) {
        db("UPDATE connect_accounts SET " . $columns . " WHERE id = '" . (int) $existing['id'] . "'");

        $connection_id = (int) $existing['id'];
    } else {
        db(
            "INSERT INTO connect_accounts SET
                provider = '" . e($provider['key']) . "',
                user_id = '" . $owner_id . "',
                external_id = '" . e($identity['id']) . "',
                created_user_id = '" . (int) $user['id'] . "',
                created_timestamp = '" . $now . "',
                " . $columns);

        $connection_id = (int) mysqli_insert_id(db::$con);
    }

    if (function_exists('log_activity')) {
        log_activity(lang(array(
            'string' => 'connected the {var:1} account {var:2} ({var:3})',
            'vars'   => array($provider['label'], $identity['label'], $purpose['label']))), $_SESSION['sessionusername'] ?? '');
    }

    return array('ok' => true, 'error' => '', 'return' => $return, 'connection_id' => $connection_id, 'label' => $identity['label']);
}

/**
 * A message for the screen a connection returns to.
 *
 * @param string $kind 'notice' or 'error'
 * @param string $text
 */
function pg_connect_flash($kind, $text)
{
    $_SESSION['software']['connect_flash'] = array('kind' => ($kind === 'error') ? 'error' : 'notice', 'text' => (string) $text);
}

/**
 * The message pg_connect_flash() left, once.
 *
 * @return array|null kind, text
 */
function pg_connect_flash_take()
{
    if (!isset($_SESSION['software']['connect_flash']) || !is_array($_SESSION['software']['connect_flash'])) {
        return null;
    }

    $flash = $_SESSION['software']['connect_flash'];
    unset($_SESSION['software']['connect_flash']);

    return $flash;
}

/**
 * Hidden fields of the form that starts a connection (POST to connect.php).
 * The form is the caller's, outside any other form.
 *
 * @param string $provider_key
 * @param string $purpose_key
 * @param string $return
 * @param string $owner 'site' or 'person'
 * @param int    $connection_id an existing connection to renew, or 0
 * @return string
 */
function pg_connect_start_fields($provider_key, $purpose_key, $return, $owner = 'site', $connection_id = 0)
{
    return get_token_field()
        . '<input type="hidden" name="provider" value="' . h($provider_key) . '">'
        . '<input type="hidden" name="purpose" value="' . h($purpose_key) . '">'
        . '<input type="hidden" name="owner" value="' . h(($owner === 'person') ? 'person' : 'site') . '">'
        . '<input type="hidden" name="return" value="' . h(pg_connect_return_path($return)) . '">'
        . '<input type="hidden" name="connection_id" value="' . (int) $connection_id . '">';
}


/* ---------------------------------------------------------------------------
   Service tokens
   --------------------------------------------------------------------------- */

/**
 * Tokens a server-to-server integration fetched for itself (a password or
 * client_credentials grant), kept sealed in connect_accounts under provider
 * 'service' rather than in a file of its own.
 *
 * @param string $key the integration's name for it, e.g. 'parasut'
 * @return array the data put there, or array()
 */
function pg_connect_service_get($key)
{
    if (!pg_connect_schema_ready()) {
        return array();
    }

    $stored = db_value(
        "SELECT secret FROM connect_accounts
        WHERE provider = 'service' AND user_id = '0' AND external_id = '" . e($key) . "'");

    return ($stored === null) ? array() : pg_connect_unseal($stored);
}

/**
 * @param string $key
 * @param string $label what the screens may show about it
 * @param array  $data
 * @return bool false when the site has no encryption key or the tables are
 *              not there yet
 */
function pg_connect_service_put($key, $label, $data)
{
    $sealed = pg_connect_schema_ready() ? pg_connect_seal($data) : '';

    if ($sealed === '') {
        return false;
    }

    $columns = "label = '" . e(pg_connect_clip($label)) . "',
        secret = '" . e($sealed) . "',
        status = 'active',
        refreshed_timestamp = UNIX_TIMESTAMP(),
        last_used_timestamp = UNIX_TIMESTAMP()";

    $id = db_value(
        "SELECT id FROM connect_accounts
        WHERE provider = 'service' AND user_id = '0' AND external_id = '" . e($key) . "'");

    if ($id !== null) {
        db("UPDATE connect_accounts SET " . $columns . " WHERE id = '" . (int) $id . "'");
    } else {
        db(
            "INSERT INTO connect_accounts SET
                provider = 'service',
                user_id = '0',
                external_id = '" . e($key) . "',
                created_timestamp = UNIX_TIMESTAMP(),
                " . $columns);
    }

    return true;
}

/**
 * @param string $key
 */
function pg_connect_service_delete($key)
{
    if (!pg_connect_schema_ready()) {
        return;
    }

    db("DELETE FROM connect_accounts WHERE provider = 'service' AND user_id = '0' AND external_id = '" . e($key) . "'");
}
