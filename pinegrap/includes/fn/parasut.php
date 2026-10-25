<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// A module of functions.php: Parasut accounting integration.
//
// Loaded by functions.php through require_once, never on its own. The
// functions were moved here from functions.php as they were; PG_FUNCTIONS_DIR
// is the software directory (what dirname(__FILE__) was in functions.php),
// see the note at the top of functions.php.
if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// NOTE (2026.1.29): the legacy cancel_order() helper was removed. It wrote the
// misspelled enum value 'canceled', which the 2026.1.26 schema change dropped
// from orders.status — every call silently stored an empty status. All
// cancellation now goes through process_order_cancellation() (see below),
// which owns the gateway void, the gift-card expiry and the cancellation
// bookkeeping columns in one place.

// ---------------------------------------------------------------------------
// Parasut API V4 helpers
// ---------------------------------------------------------------------------

// The out-of-band redirect: Paraşüt shows the authorization code on its own
// page instead of sending the browser anywhere, and the token endpoint wants
// the same value back with every grant.
if (!defined('PARASUT_REDIRECT_URI')) {
    define('PARASUT_REDIRECT_URI', 'urn:ietf:wg:oauth:2.0:oob');
}

// The API takes 10 requests in any 10 seconds (documentation, "Genel
// Bilgiler"); one invoice with new products and a buyer to open can pass that.
if (!defined('PARASUT_RATE_LIMIT')) {
    define('PARASUT_RATE_LIMIT', 10);
}

if (!defined('PARASUT_RATE_WINDOW')) {
    define('PARASUT_RATE_WINDOW', 10.0);
}

/**
 * How long to wait before the next request so that no window of
 * $window seconds holds more than $limit of them.
 *
 * @param array $stamps  microtime(true) of the requests made so far
 * @param float $now
 * @param int   $limit
 * @param float $window
 * @return float  Seconds; 0 when the request may go now
 */
function parasut_throttle_wait($stamps, $now, $limit = PARASUT_RATE_LIMIT, $window = PARASUT_RATE_WINDOW)
{
    $recent = array();

    foreach ((array) $stamps as $stamp) {
        if (($now - (float) $stamp) < $window) {
            $recent[] = (float) $stamp;
        }
    }

    if (count($recent) < $limit) {
        return 0.0;
    }

    sort($recent);

    // The oldest of the last $limit requests has to leave the window.
    return max(0.0, round($recent[count($recent) - $limit] + $window - $now, 3));
}

/**
 * Holds the request back until the rate limit allows it, then counts it.
 * Per PHP request: a cron run and a page writing at the same moment are not
 * counted together, which the 429 retry in _parasut_request() (and in the
 * e-document driver's request) covers.
 *
 * @return void
 */
function parasut_throttle()
{
    static $stamps = array();

    $wait = parasut_throttle_wait($stamps, microtime(true));

    if ($wait > 0) {
        usleep((int) ceil($wait * 1000000));
    }

    $now = microtime(true);
    $stamps[] = $now;
    $stamps = array_values(array_filter($stamps, function ($stamp) use ($now) {
        return ($now - $stamp) < PARASUT_RATE_WINDOW;
    }));
}

/**
 * Make a cURL request to the Parasut API.
 *
 * A 429 (over the rate limit, another process calling too) is sent once
 * more after a full rate window, as the e-document driver does
 * (erp_edoc_parasut_request()): Paraşüt did not process the request, so
 * repeating it cannot create a second record.
 *
 * @param string $method     GET | POST | PUT | PATCH | DELETE
 * @param string $url        Full URL
 * @param array  $body       Associative array (will be JSON-encoded) or null
 * @param string $token      Bearer token (omit for OAuth token endpoint)
 * @param bool   $retry_429  false for the repeated request, so a second 429 is returned
 * @return array ['http_code' => int, 'body' => mixed]
 */
function _parasut_request($method, $url, $body = null, $token = null, $retry_429 = true)
{
    parasut_throttle();

    $ch = curl_init($url);
    // Identify this installation on outgoing requests. Sent with no
    // User-Agent, a request looks like an anonymous client to the receiving
    // server's firewall and gets rejected — which is how Pinegrap ended up
    // blocking its own licence and update checks.
    curl_setopt($ch, CURLOPT_USERAGENT, function_exists('pinegrap_user_agent') ? pinegrap_user_agent() : 'Pinegrap');
    $headers = ['Accept: application/json'];

    if ($token) {
        $headers[] = 'Authorization: Bearer ' . $token;
    }

    if ($body !== null) {
        $json = json_encode($body);
        $headers[] = 'Content-Type: application/json';
        $headers[] = 'Content-Length: ' . strlen($json);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
    }

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);

    // The certificate is checked against the bundle the rest of the software
    // uses (CURL_CA_BUNDLE), the way the update channel and the e-document
    // layer do; without it a host with a stale CA store fails every call
    // with a bare "cURL error 60".
    if (function_exists('pg_curl_tls')) {
        pg_curl_tls($ch);
    }

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    $curl_errno = curl_errno($ch);
    curl_close($ch);

    if ($curl_error) {
        if (function_exists('pg_curl_tls_hint')) {
            $curl_error .= pg_curl_tls_hint($curl_errno);
        }
        return ['http_code' => 0, 'body' => null, 'raw' => '', 'curl_error' => $curl_error];
    }

    if (($http_code === 429) && $retry_429) {
        sleep((int) ceil(PARASUT_RATE_WINDOW));

        return _parasut_request($method, $url, $body, $token, false);
    }

    return ['http_code' => $http_code, 'body' => json_decode($response, true), 'raw' => (string) $response];
}

/**
 * Return the Parasut API base URL (production or sandbox).
 */
function _parasut_base()
{
    // Production only. The setting that used to switch this pointed at
    // api.heroku-staging.parasut.com, Parasut's own staging host rather than a
    // sandbox offered to customers: it does not answer to a merchant's
    // credentials, and because the OAuth call goes through here too, turning the
    // switch on stopped the integration working at all.
    return defined('PARASUT_API_BASE') ? PARASUT_API_BASE : 'https://api.parasut.com';
}

/**
 * Return the company-scoped API prefix.
 */
function _parasut_prefix()
{
    return _parasut_base() . '/v4/' . PARASUT_COMPANY_ID;
}

/**
 * Extract a readable error message from a Parasut API response.
 */
function _parasut_extract_error($result)
{
    if (isset($result['curl_error'])) {
        return $result['curl_error'];
    }
    $body = $result['body'] ?? [];
    if (!empty($body['errors'])) {
        $msgs = [];
        foreach ((array) $body['errors'] as $err) {
            // Paraşüt often repeats the detail as the title ("X: X").
            $title = is_array($err) ? trim((string) ($err['title'] ?? '')) : '';
            $detail = is_array($err) ? trim((string) ($err['detail'] ?? '')) : trim((string) $err);
            $msgs[] = (($title !== '') && ($detail !== '') && ($title !== $detail)) ? $title . ': ' . $detail : (($detail !== '') ? $detail : $title);
        }
        return implode(' | ', $msgs);
    }
    if (!empty($body['error_description'])) {
        return $body['error_description'];
    }
    return 'HTTP ' . ($result['http_code'] ?? '?');
}

/**
 * Read the buyer's tax or identity number for an order.
 *
 * Which field holds it is an operator setting (PARASUT_TC_IN_FIELD). Two of the
 * three choices are columns on orders; the third, tax_number, is not - it lives
 * on the linked contact, which is where the settings screen sends the operator
 * and what view_order.php checks before offering the button. Reading it off the
 * order row returned nothing, so contacts created from those orders carried no
 * identity number at all and could never be billed by e-invoice.
 *
 * @param array $order  Order row
 * @return string  Empty when not configured or not filled in
 */
function _parasut_order_identity($order)
{
    if (!defined('PARASUT_TC_IN_FIELD') || PARASUT_TC_IN_FIELD === 'do not use') {
        return '';
    }

    $field = PARASUT_TC_IN_FIELD;

    if ($field === 'tax_number') {
        $contact_id = (int) ($order['contact_id'] ?? 0);
        if ($contact_id <= 0) {
            return '';
        }
        return trim((string) db_value("SELECT tax_number FROM contacts WHERE id = '" . escape($contact_id) . "' LIMIT 1"));
    }

    return trim((string) ($order[$field] ?? ''));
}

/**
 * Say why an order cannot get an e-shipment for want of the buyer's VKN/TCKN.
 *
 * Under a custom-field setting (custom_field_1/2) the number is the order's
 * field, which the shipment's buyer card is opened with; a number on the
 * contact card never reaches Paraşüt then, so it does not count. The refusal
 * names the field as the order screen labels it ("Custom Field #1"). Under
 * "do not use" (the default) and tax_number the linked contact's tax_number
 * is checked.
 *
 * @param string $field               PARASUT_TC_IN_FIELD
 * @param array  $order               Order row (custom_field_1, custom_field_2)
 * @param string $contact_tax_number  The linked contact's tax_number
 * @return string  Empty when the number is there, otherwise the refusal
 */
function _parasut_shipment_identity_refusal($field, $order, $contact_tax_number)
{
    if (($field === 'custom_field_1') || ($field === 'custom_field_2')) {
        if (trim((string) ($order[$field] ?? '')) !== '') {
            return '';
        }

        return lang(array(
            'string' => 'The buyer\'s VKN/TCKN is read from the order\'s "{var:1}" field (Paraşüt card of the commerce settings), and that field is empty on this order.',
            'vars' => array(lang('Custom Field') . ' #' . (($field === 'custom_field_1') ? '1' : '2')),
        ));
    }

    if (trim((string) $contact_tax_number) !== '') {
        return '';
    }

    return lang('VKN/TCKN is missing from the linked contact. Please update the contact record before creating a document.');
}

/**
 * Decide whether an order is billed by e-invoice or by e-archive.
 *
 * The rule is the tax authority's, not ours: a buyer registered for e-invoice
 * must be sent one, and everyone else gets an e-archive. Sending the wrong kind
 * produces a document that is not valid. Parasut answers the question through
 * e_invoice_inboxes, and the answer comes with the buyer's GIB address, which
 * the e-invoice has to carry as `to`.
 *
 * Asked at the moment of issue and not cached: the registered-user list has no
 * published update interval, and a stale "not registered" is the answer that
 * produces an invalid document.
 *
 * A buyer with no identity number cannot be registered, so that case skips the
 * lookup. A lookup that fails is not treated as "not registered" - the caller
 * is told, and no document is raised on a guess.
 *
 * @param string $identity  VKN or TCKN
 * @param string $token     Parasut access token
 * @return array ['success' => bool, 'kind' => 'einvoice'|'earchive', 'alias' => string, 'error' => string]
 */
function _parasut_resolve_edoc_kind($identity, $token)
{
    if ($identity === '') {
        return ['success' => true, 'kind' => 'earchive', 'alias' => '', 'error' => ''];
    }

    $url = _parasut_prefix() . '/e_invoice_inboxes?filter[vkn]=' . urlencode($identity);
    $result = _parasut_request('GET', $url, null, $token);

    if ($result['http_code'] !== 200) {
        return [
            'success' => false,
            'kind' => '',
            'alias' => '',
            'error' => lang(array('string' => 'Could not check whether the buyer is registered for e-invoice: {var:1}', 'vars' => array(_parasut_extract_error($result)))),
        ];
    }

    $items = $result['body']['data'] ?? [];
    if (empty($items)) {
        return ['success' => true, 'kind' => 'earchive', 'alias' => '', 'error' => ''];
    }

    // A buyer can publish more than one box. Nothing here can tell them apart,
    // so the first is used; picking per contact is a job for the ERP screens.
    $alias = '';
    foreach ($items as $item) {
        $candidate = trim((string) ($item['attributes']['e_invoice_address'] ?? ''));
        if ($candidate !== '') {
            $alias = $candidate;
            break;
        }
    }

    if ($alias === '') {
        return [
            'success' => false,
            'kind' => '',
            'alias' => '',
            'error' => lang('The buyer is registered for e-invoice but Parasut returned no address to send it to.'),
        ];
    }

    return ['success' => true, 'kind' => 'einvoice', 'alias' => $alias, 'error' => ''];
}

/**
 * Wait, briefly, for an e-document conversion to finish.
 *
 * Converting a sales invoice into an e-invoice or e-archive is asynchronous:
 * Parasut answers with a trackable job, and an accepted POST says only that the
 * work was queued. The code used to read that as "sent" and tell the operator
 * the document had been filed.
 *
 * The job id is only valid for fifteen minutes and there is no queue here yet to
 * come back to it, so this polls for a few seconds while the operator is still
 * on the request. Most conversions land inside that window; one that does not is
 * reported as still running rather than as done.
 *
 * @param string $job_id
 * @param string $token
 * @return array ['state' => 'done'|'pending'|'failed', 'error' => string]
 */
function _parasut_track_job($job_id, $token)
{
    if ($job_id === '') {
        return ['state' => 'pending', 'error' => ''];
    }

    // Growing waits, about six seconds in total. Long enough for the common case,
    // short enough that the page does not appear to hang.
    $waits = [1, 2, 3];

    foreach ($waits as $wait) {
        sleep($wait);

        $result = _parasut_request('GET', _parasut_prefix() . '/trackable_jobs/' . urlencode($job_id), null, $token);

        if ($result['http_code'] !== 200) {
            continue;
        }

        $attributes = $result['body']['data']['attributes'] ?? [];
        $status = strtolower((string) ($attributes['status'] ?? ''));

        if (($status === 'done') || ($status === 'completed') || ($status === 'success')) {
            return ['state' => 'done', 'error' => ''];
        }

        if (($status === 'error') || ($status === 'failed') || ($status === 'aborted')) {
            $errors = $attributes['errors'] ?? [];
            $messages = [];
            foreach ((array) $errors as $error) {
                $messages[] = is_array($error) ? implode(' ', array_map('strval', $error)) : (string) $error;
            }
            return ['state' => 'failed', 'error' => $messages ? implode(' | ', $messages) : lang('The conversion was rejected.')];
        }
    }

    return ['state' => 'pending', 'error' => ''];
}

/**
 * Decode the stored Parasut credentials.
 *
 * Held as one encrypted JSON blob in config.parasut_credentials_enc, in the
 * "<ciphertext>:<iv>" shape encrypt_string_with_iv() answers. Decoding fails
 * softly to empty strings: a site restored with a different ENCRYPTION_KEY
 * cannot recover these values, and the caller should report "not connected"
 * rather than stop on a fatal error.
 *
 * @param bool $fresh  Read the row again instead of what this request already
 *                     decoded (after _parasut_credentials_store())
 * @return array ['client_secret' => string, 'password' => string]
 */
function _parasut_credentials($fresh = false)
{
    static $credentials = null;

    if (($credentials !== null) && !$fresh) {
        return $credentials;
    }

    $credentials = ['client_secret' => '', 'password' => ''];

    $stored = $fresh
        ? (string) db_value("SELECT parasut_credentials_enc FROM config LIMIT 1")
        : (defined('PARASUT_CREDENTIALS_ENC') ? (string) PARASUT_CREDENTIALS_ENC : '');
    if ($stored === '' || strpos($stored, ':') === false) {
        return $credentials;
    }

    list($cipher, $iv) = explode(':', $stored, 2);
    $json = decode_ssl_keys($cipher, $iv);
    $values = ($json === '') ? null : json_decode($json, true);

    if (is_array($values)) {
        $credentials['client_secret'] = (string) ($values['client_secret'] ?? '');
        $credentials['password'] = (string) ($values['password'] ?? '');
    }

    return $credentials;
}

/**
 * Write one or both secrets into the encrypted blob, keeping the other as it
 * is. The same merge the settings save does (pg_parasut_credentials_for_save())
 * for the connect endpoint, which has only the secret to write.
 *
 * @param array $values  'client_secret' and/or 'password'; a key left out is kept
 * @return bool
 */
function _parasut_credentials_store($values)
{
    $current = _parasut_credentials(true);

    foreach (['client_secret', 'password'] as $key) {
        if (array_key_exists($key, (array) $values)) {
            $current[$key] = trim((string) $values[$key]);
        }
    }

    if (($current['client_secret'] === '') && ($current['password'] === '')) {
        $encoded = '';
    } else {
        list($cipher, $iv) = encrypt_string_with_iv(json_encode($current));
        $encoded = $cipher . ':' . $iv;
    }

    $ok = (db("UPDATE config SET parasut_credentials_enc = '" . escape($encoded) . "'") !== false);

    // What this request decoded is stale now.
    _parasut_credentials(true);

    return $ok;
}

/**
 * Which Parasut account a cached token belongs to. A token kept from another
 * company, client or user is not offered after the settings change.
 *
 * The connect endpoint computes the owner for the values it has just saved,
 * which the constants of the running request do not know yet.
 *
 * @param string|null $company_id  PARASUT_COMPANY_ID when null
 * @param string|null $client_id   PARASUT_CLIENT_ID when null
 * @param string|null $username    PARASUT_USERNAME when null
 * @return string
 */
function _parasut_token_owner($company_id = null, $client_id = null, $username = null)
{
    $company_id = ($company_id === null) ? (defined('PARASUT_COMPANY_ID') ? PARASUT_COMPANY_ID : '') : $company_id;
    $client_id = ($client_id === null) ? (defined('PARASUT_CLIENT_ID') ? PARASUT_CLIENT_ID : '') : $client_id;
    $username = ($username === null) ? (defined('PARASUT_USERNAME') ? PARASUT_USERNAME : '') : $username;

    return md5((string) $company_id . '|' . (string) $client_id . '|' . (string) $username);
}

/**
 * Read the cached token.
 *
 * The token and its refresh token are kept sealed with the site's key among
 * the connected accounts' service tokens (pg_connect_service_get()). Until
 * 2026.4.10 they were a plain JSON file, data/parasut_token.json; a file left
 * from then is moved in on the first read and deleted. Before the database
 * upgrade has run there is nowhere to move it, and the file is read as it was.
 *
 * @param string|null $owner  The owner stamp to match; _parasut_token_owner() of
 *                            the running request when null. The connect endpoint
 *                            names the one it has just written with, which the
 *                            constants of its own request do not know yet.
 * @return array  Cache contents, or an empty array
 */
function _parasut_token_cache_read($owner = null)
{
    $cache = pg_connect_service_get('parasut');

    if (file_exists(PARASUT_TOKEN_CACHE)) {
        if ($cache) {
            // The sealed copy is the one in use; the file is a leftover.
            @unlink(PARASUT_TOKEN_CACHE);
        } else {
            $raw = @file_get_contents(PARASUT_TOKEN_CACHE);
            $legacy = ($raw === false) ? null : json_decode($raw, true);
            $legacy = is_array($legacy) ? $legacy : [];

            // The file was written for the settings of its day, which are the
            // settings of today on every site that has not changed them since.
            $legacy['owner'] = _parasut_token_owner();

            if (pg_connect_service_put('parasut', 'Paraşüt', $legacy)) {
                @unlink(PARASUT_TOKEN_CACHE);
            }

            $cache = $legacy;
        }
    }

    $owner = ($owner === null) ? _parasut_token_owner() : (string) $owner;

    if (!isset($cache['owner']) || ($cache['owner'] !== $owner)) {
        return [];
    }

    return $cache;
}

/**
 * Write the token cache.
 *
 * Sealed among the service tokens, so the refresh token - which renews
 * access without the account password - is not readable from the disk or a
 * backup of it. Before the database upgrade has run, the file it replaces is
 * written as before: written to a temporary file and renamed into place, so a
 * second request reads either the old file or the new one, never a half
 * one.
 *
 * @param array       $cache
 * @param string|null $owner  _parasut_token_owner() of the running request when null
 * @return void
 */
function _parasut_token_cache_write($cache, $owner = null)
{
    $cache['owner'] = ($owner === null) ? _parasut_token_owner() : (string) $owner;

    if (pg_connect_service_put('parasut', 'Paraşüt', $cache)) {
        return;
    }

    if (pg_connect_schema_ready()) {
        // No encryption key: the credentials could not have been read either,
        // so there is nothing to keep.
        return;
    }

    $temporary = PARASUT_TOKEN_CACHE . '.' . getmypid() . '.tmp';

    if (file_put_contents($temporary, json_encode($cache), LOCK_EX) === false) {
        return;
    }

    if (!@rename($temporary, PARASUT_TOKEN_CACHE)) {
        @unlink($temporary);
    }
}

/**
 * Exchange the stored refresh token for a new access token.
 *
 * The refresh token was already being saved and never used, so an expired token
 * meant sending the account password again on every renewal. Refreshing keeps
 * the password out of all but the first exchange, which is what makes it
 * possible to stop storing it later.
 *
 * @param string $refresh_token
 * @param array  $credentials
 * @return array ['success' => bool, 'data' => array|null]
 */
function _parasut_token_refresh($refresh_token, $credentials)
{
    if ($refresh_token === '') {
        return ['success' => false, 'data' => null];
    }

    $data = _parasut_token_exchange([
        'grant_type' => 'refresh_token',
        'refresh_token' => $refresh_token,
    ], $credentials);

    return ['success' => !empty($data['access_token']), 'data' => $data];
}

/**
 * POST to the OAuth token endpoint.
 *
 * Kept on plain cURL rather than api_http_request(): the token endpoint wants a
 * form-encoded body and HTTP Basic auth, neither of which that helper sends.
 * The client is named both ways - Basic auth and client_id / client_secret in
 * the body - because the documentation's example uses the body and the
 * server accepts either; a client id typed on the settings card but not
 * saved yet travels in $credentials.
 *
 * @param array $body         Grant-specific fields
 * @param array $credentials  From _parasut_credentials(); 'client_id' overrides the saved one
 * @return array|null  Decoded response, or null on a transport error
 */
function _parasut_token_exchange($body, $credentials)
{
    $client_id = trim((string) (($credentials['client_id'] ?? '') !== '' ? $credentials['client_id'] : (defined('PARASUT_CLIENT_ID') ? PARASUT_CLIENT_ID : '')));
    $client_secret = (string) ($credentials['client_secret'] ?? '');
    $body = ['client_id' => $client_id, 'client_secret' => $client_secret] + (array) $body;

    parasut_throttle();

    $ch = curl_init(_parasut_base() . '/oauth/token');
    // Identify this installation on outgoing requests. Sent with no
    // User-Agent, a request looks like an anonymous client to the receiving
    // server's firewall and gets rejected — which is how Pinegrap ended up
    // blocking its own licence and update checks.
    curl_setopt($ch, CURLOPT_USERAGENT, function_exists('pinegrap_user_agent') ? pinegrap_user_agent() : 'Pinegrap');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($body),
        CURLOPT_USERPWD => $client_id . ':' . $client_secret,
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded', 'Accept: application/json'],
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);

    if (function_exists('pg_curl_tls')) {
        pg_curl_tls($ch);
    }

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if ($curl_error) {
        return ['error_description' => 'cURL error: ' . $curl_error];
    }

    $data = json_decode($response, true);

    if (!is_array($data)) {
        return ['error_description' => 'HTTP ' . $http_code];
    }

    if ($http_code !== 200) {
        $data['error_description'] = $data['error_description'] ?? ('HTTP ' . $http_code);
        unset($data['access_token']);
    }

    return $data;
}

/**
 * Get a valid OAuth2 access token, cached between requests to avoid repeated
 * logins (_parasut_token_cache_read()).
 *
 * Renewal is taken by one request at a time. Without that, every page that found
 * the token expired asked Parasut for a new one at the same moment, and each
 * answer invalidated the one before it, so the requests knocked each other out.
 * A request that cannot take the lock waits for whoever holds it and then reads
 * the cache that request wrote.
 *
 * @return array ['success' => bool, 'token' => string|null, 'error' => string]
 */
function parasut_get_token()
{
    // Keep a 60-second buffer: a token that expires mid-request is no use.
    $cache = _parasut_token_cache_read();
    if (!empty($cache['access_token']) && !empty($cache['expires_at']) && (time() < ($cache['expires_at'] - 60))) {
        return ['success' => true, 'token' => $cache['access_token'], 'error' => ''];
    }

    $credentials = _parasut_credentials();

    // Two ways in. The authorization code (the Connect button on the settings
    // card) leaves a refresh token and no password; the password grant needs
    // the account's user name and password. Without either there is nothing
    // to renew with.
    $can_refresh = (!empty($cache['refresh_token']) && ($credentials['client_secret'] !== ''));
    $can_sign_in = (($credentials['client_secret'] !== '') && ($credentials['password'] !== '')
        && (defined('PARASUT_USERNAME') && (trim((string) PARASUT_USERNAME) !== '')));

    if (!$can_refresh && !$can_sign_in) {
        return [
            'success' => false,
            'token' => null,
            'error' => ($credentials['client_secret'] === '')
                ? lang('Parasut credentials are not set. Enter them in Settings, E-commerce, Invoicing.')
                : lang('Pinegrap is not connected to Paraşüt. On the Paraşüt card of the commerce settings, authorize it with the code Paraşüt shows (Connect), or enter the Paraşüt user name and password.'),
        ];
    }

    $lock_name = 'pinegrap_parasut_token_' . substr(md5(PARASUT_COMPANY_ID . '|' . PARASUT_CLIENT_ID), 0, 20);
    $have_lock = (db_value("SELECT GET_LOCK('" . escape($lock_name) . "', 10)") == '1');

    // Whoever we waited for may have renewed it already - also when the wait
    // ran out without the lock.
    $cache = _parasut_token_cache_read();
    if (!empty($cache['access_token']) && !empty($cache['expires_at']) && (time() < ($cache['expires_at'] - 60))) {
        if ($have_lock) {
            db_value("SELECT RELEASE_LOCK('" . escape($lock_name) . "')");
        }
        return ['success' => true, 'token' => $cache['access_token'], 'error' => ''];
    }

    $refresh = $can_refresh ? _parasut_token_refresh((string) ($cache['refresh_token'] ?? ''), $credentials) : ['success' => false, 'data' => null];

    // Every refresh hands out a new refresh token and retires the one sent
    // (API documentation). A request that renewed in parallel leaves this one
    // holding a retired token: invalid_grant here then only means the cache
    // has moved on, so it is read once more before the connection is called
    // expired.
    if (!$refresh['success'] && ((string) ($refresh['data']['error'] ?? '') === 'invalid_grant')) {
        $moved = _parasut_token_cache_read();

        if (!empty($moved['access_token']) && !empty($moved['expires_at']) && (time() < ($moved['expires_at'] - 60))
            && ((string) ($moved['refresh_token'] ?? '') !== (string) ($cache['refresh_token'] ?? ''))) {
            if ($have_lock) {
                db_value("SELECT RELEASE_LOCK('" . escape($lock_name) . "')");
            }
            return ['success' => true, 'token' => $moved['access_token'], 'error' => ''];
        }
    }

    if ($refresh['success']) {
        $data = $refresh['data'];
    } elseif ($can_sign_in) {
        $data = _parasut_token_exchange([
            'grant_type' => 'password',
            'username' => PARASUT_USERNAME,
            'password' => $credentials['password'],
            'redirect_uri' => PARASUT_REDIRECT_URI,
        ], $credentials);
        $data['grant'] = 'password';
    } else {
        // The refresh token has been revoked or has expired (Paraşüt keeps
        // one for a limited time), and there is no password to fall back on:
        // the operator has to authorize once more.
        $data = (array) $refresh['data'];

        // invalid_grant is the server's word for a refresh token it no longer
        // honours; anything else (a transport error, a 5xx) is passed on as it
        // is, because authorizing again would not cure it.
        if ((string) ($data['error'] ?? '') === 'invalid_grant') {
            $data['error_description'] = lang(array(
                'string' => 'The Paraşüt connection has expired ({var:1}). Authorize Pinegrap once more on the Paraşüt card of the commerce settings.',
                'vars' => (string) ($data['error_description'] ?? 'invalid_grant'),
            ));
        }

        unset($data['access_token']);
    }

    if (empty($data['access_token'])) {
        if ($have_lock) {
            db_value("SELECT RELEASE_LOCK('" . escape($lock_name) . "')");
        }
        $message = $data['error_description'] ?? 'no access token in the response';
        return ['success' => false, 'token' => null, 'error' => lang(array('string' => 'Parasut token error: {var:1}', 'vars' => array($message)))];
    }

    // What the connect endpoint recorded about the account (grant, user,
    // company name) rides along; a renewal is not a new connection.
    _parasut_token_cache_write(array_merge(array_diff_key((array) $cache, ['owner' => 1]), [
        'access_token' => $data['access_token'],
        // Parasut does not always return a new refresh token; keep the old one
        // rather than losing the ability to renew without the password.
        'refresh_token' => $data['refresh_token'] ?? ($cache['refresh_token'] ?? ''),
        'expires_at' => time() + (int) ($data['expires_in'] ?? 7200),
        'grant' => (string) ($data['grant'] ?? ($cache['grant'] ?? 'password')),
    ]));

    if ($have_lock) {
        db_value("SELECT RELEASE_LOCK('" . escape($lock_name) . "')");
    }

    return ['success' => true, 'token' => $data['access_token'], 'error' => ''];
}

/**
 * Drops the cached access token so the next parasut_get_token() renews it
 * (with the refresh token, or the password). For the answer that says the
 * token is no longer good - a 401 - when its own clock still says it is.
 *
 * @return void
 */
function parasut_token_forget()
{
    $cache = _parasut_token_cache_read();

    if (empty($cache)) {
        return;
    }

    unset($cache['access_token'], $cache['owner']);
    $cache['expires_at'] = 0;

    _parasut_token_cache_write($cache);
}

/**
 * The page the operator opens to let this installation into their Paraşüt
 * account. Paraşüt asks them to sign in and to allow the application, then
 * prints an authorization code (the out-of-band redirect) that is pasted
 * on the settings card and exchanged by parasut_connect_with_code().
 *
 * @param string|null $client_id  The saved one when null
 * @return string  '' without a client id
 */
function parasut_authorize_url($client_id = null)
{
    $client_id = trim((string) (($client_id === null) ? (defined('PARASUT_CLIENT_ID') ? PARASUT_CLIENT_ID : '') : $client_id));

    if ($client_id === '') {
        return '';
    }

    return _parasut_base() . '/oauth/authorize?' . http_build_query([
        'client_id' => $client_id,
        'redirect_uri' => PARASUT_REDIRECT_URI,
        'response_type' => 'code',
    ]);
}

/**
 * Who the token belongs to and which companies they can work in
 * (GET /v4/me, with the companies included). The company list is what fills
 * an empty Company ID box on connect.
 *
 * @param string $token
 * @return array ['success' => bool, 'email' => string, 'name' => string,
 *                'companies' => array id => name, 'error' => string]
 */
function parasut_me($token)
{
    $result = _parasut_request('GET', _parasut_base() . '/v4/me?include=companies', null, $token);

    if (($result['http_code'] !== 200) || empty($result['body']['data'])) {
        return ['success' => false, 'email' => '', 'name' => '', 'companies' => [], 'error' => _parasut_extract_error($result)];
    }

    return _parasut_me_parse($result['body']) + ['success' => true, 'error' => ''];
}

/**
 * Reads the /v4/me answer: the user's e-mail and name, and the companies
 * (relationship ids, named from the included records when they were sent).
 *
 * @param array $body  The decoded JSON:API document
 * @return array ['email' => string, 'name' => string, 'companies' => array id => name]
 */
function _parasut_me_parse($body)
{
    $body = (array) $body;
    $attributes = (array) ($body['data']['attributes'] ?? []);
    $companies = [];

    foreach ((array) ($body['data']['relationships']['companies']['data'] ?? []) as $reference) {
        if (is_array($reference) && isset($reference['id'])) {
            $companies[(string) $reference['id']] = '';
        }
    }

    foreach ((array) ($body['included'] ?? []) as $item) {
        if (is_array($item) && ((string) ($item['type'] ?? '') === 'companies') && isset($item['id'])) {
            $companies[(string) $item['id']] = trim((string) ($item['attributes']['name'] ?? ''));
        }
    }

    return [
        'email' => trim((string) ($attributes['email'] ?? '')),
        'name' => trim((string) ($attributes['name'] ?? '')),
        'companies' => $companies,
    ];
}

/**
 * Turns the authorization code Paraşüt showed into a connection: the code is
 * exchanged for a token pair, the account is asked who it is and which
 * companies it can see, and what the next request needs is written down -
 * client id and secret in the config row, the company id when the box was
 * empty and the account has exactly one, and the sealed token with its
 * refresh token. No password is involved, so none is stored.
 *
 * A code is good for one exchange and a few minutes; a second click with
 * the same code is refused by Paraşüt, which is what the error says.
 *
 * @param string $code           What Paraşüt printed after the sign-in
 * @param string $client_id      As typed on the card (the saved one when empty)
 * @param string $client_secret  As typed (the saved one when empty)
 * @param string $company_id     As typed; '' lets the account's one company fill it
 * @return array ['success' => bool, 'company_id' => string, 'company_name' => string,
 *                'email' => string, 'owner' => string (the token's owner stamp), 'error' => string]
 */
function parasut_connect_with_code($code, $client_id = '', $client_secret = '', $company_id = '')
{
    $fail = function ($error) {
        return ['success' => false, 'company_id' => '', 'company_name' => '', 'email' => '', 'owner' => '', 'error' => $error];
    };

    $code = trim((string) $code);
    $client_id = trim((string) $client_id);
    $client_secret = trim((string) $client_secret);
    $company_id = trim((string) $company_id);

    if ($client_id === '') {
        $client_id = defined('PARASUT_CLIENT_ID') ? trim((string) PARASUT_CLIENT_ID) : '';
    }

    if ($client_secret === '') {
        $stored = _parasut_credentials();
        $client_secret = $stored['client_secret'];
    }

    if ($code === '') {
        return $fail(lang('Paste the authorization code Paraşüt showed after the sign-in.'));
    }

    if (($client_id === '') || ($client_secret === '')) {
        return $fail(lang('The client id and the client secret are needed before Pinegrap can be authorized.'));
    }

    $data = _parasut_token_exchange([
        'grant_type' => 'authorization_code',
        'code' => $code,
        'redirect_uri' => PARASUT_REDIRECT_URI,
    ], ['client_id' => $client_id, 'client_secret' => $client_secret]);

    if (empty($data['access_token'])) {
        return $fail(lang(array(
            'string' => 'Paraşüt did not accept the code: {var:1}. A code works once and only for a few minutes; open the authorization page again for a new one.',
            'vars' => (string) ($data['error_description'] ?? 'no access token in the response'),
        )));
    }

    $me = parasut_me($data['access_token']);

    if (!$me['success']) {
        return $fail(lang(array('string' => 'Paraşüt gave a token but would not say whose it is: {var:1}', 'vars' => $me['error'])));
    }

    $companies = $me['companies'];

    if ($company_id === '') {
        if (count($companies) === 1) {
            $company_id = (string) key($companies);
        } elseif (count($companies) === 0) {
            return $fail(lang('This Paraşüt user has no company to work in.'));
        } else {
            $named = [];
            foreach ($companies as $id => $name) {
                $named[] = ($name !== '') ? $name . ' (' . $id . ')' : (string) $id;
            }
            return $fail(lang(array(
                'string' => 'This Paraşüt user works in more than one company. Write the id of the one to use in the Company ID box and connect again: {var:1}',
                'vars' => implode(', ', $named),
            )));
        }
    } elseif (!empty($companies) && !isset($companies[$company_id])) {
        $named = [];
        foreach ($companies as $id => $name) {
            $named[] = ($name !== '') ? $name . ' (' . $id . ')' : (string) $id;
        }
        return $fail(lang(array(
            'string' => 'The Paraşüt user cannot see company {var:1}. Its companies: {var:2}',
            'vars' => array($company_id, implode(', ', $named)),
        )));
    }

    $company_name = (string) ($companies[$company_id] ?? '');

    // Kept in this order: the config row first, so the owner of the sealed
    // token matches what the next request reads from the constants.
    db("UPDATE config SET
        parasut_client_id = '" . escape($client_id) . "',
        parasut_company_id = '" . escape($company_id) . "'");
    _parasut_credentials_store(['client_secret' => $client_secret]);

    $owner = _parasut_token_owner($company_id, $client_id, null);

    _parasut_token_cache_write([
        'access_token' => (string) $data['access_token'],
        'refresh_token' => (string) ($data['refresh_token'] ?? ''),
        'expires_at' => time() + (int) ($data['expires_in'] ?? 7200),
        'grant' => 'code',
        'email' => $me['email'],
        'company_name' => $company_name,
        'connected_at' => time(),
    ], $owner);

    return [
        'success' => true,
        'company_id' => $company_id,
        'company_name' => $company_name,
        'email' => $me['email'],
        'owner' => $owner,
        'error' => '',
    ];
}

/**
 * What the settings card says about the connection, read from the sealed
 * token alone - nothing is asked of Paraşüt while a settings page is drawn.
 *
 * @param string|null $owner  The token's owner stamp; the saved settings' when
 *                            null. Connect passes the one it wrote with: the
 *                            company and client it has just saved are not in
 *                            its request's constants.
 * @return array ['connected' => bool, 'grant' => 'code'|'password'|'', 'email' => string,
 *                'company_name' => string, 'expires_at' => int, 'connected_at' => int,
 *                'renewable' => bool]
 */
function parasut_connection_status($owner = null)
{
    $cache = _parasut_token_cache_read($owner);
    $credentials = _parasut_credentials();

    $renewable = (!empty($cache['refresh_token']) && ($credentials['client_secret'] !== ''))
        || (($credentials['client_secret'] !== '') && ($credentials['password'] !== '')
            && defined('PARASUT_USERNAME') && (trim((string) PARASUT_USERNAME) !== ''));

    return [
        'connected' => (!empty($cache['access_token']) && (((int) ($cache['expires_at'] ?? 0) > time()) || $renewable)),
        'grant' => (string) ($cache['grant'] ?? (!empty($cache['access_token']) ? 'password' : '')),
        'email' => (string) ($cache['email'] ?? ''),
        'company_name' => (string) ($cache['company_name'] ?? ''),
        'expires_at' => (int) ($cache['expires_at'] ?? 0),
        'connected_at' => (int) ($cache['connected_at'] ?? 0),
        'renewable' => $renewable,
    ];
}

/**
 * The settings card's line for a connection state, the same after a page
 * draw and after Connect answers.
 *
 * @param array $status  parasut_connection_status() output
 * @return array ['text' => string, 'tone' => 'success'|'secondary']
 */
function parasut_connection_line($status)
{
    $status = (array) $status;

    if (empty($status['connected'])) {
        return ['text' => lang('Not connected to Paraşüt yet.'), 'tone' => 'secondary'];
    }

    $parts = [((string) ($status['grant'] ?? '') === 'code')
        ? lang('Connected with an authorization code')
        : lang('Connected with the user name and password')];

    foreach (['email', 'company_name'] as $key) {
        if (trim((string) ($status[$key] ?? '')) !== '') {
            $parts[] = trim((string) $status[$key]);
        }
    }

    if ((int) ($status['connected_at'] ?? 0) > 0) {
        $parts[] = date('d.m.Y H:i', (int) $status['connected_at']);
    }

    return ['text' => implode(' · ', $parts), 'tone' => 'success'];
}

/**
 * The company number the settings save writes. An empty box keeps the saved
 * number: Connect writes the number it finds while the open page still holds
 * the empty box, and saving that page used to blank the number, which
 * orphaned the token (its owner stamp names the company) and disconnected
 * the store. Another number typed in the box replaces it.
 *
 * @param mixed  $posted  The box as posted
 * @param string $stored  The saved number
 * @return string
 */
function parasut_company_id_for_save($posted, $stored)
{
    $posted = is_string($posted) ? trim($posted) : '';

    return ($posted !== '') ? $posted : trim((string) $stored);
}

/**
 * Whether an order's documents belong to the ERP rather than to the order
 * screen's own Paraşüt buttons (Create Invoice, E-Shipment). With the ERP on
 * and an e-document provider switched on, the ERP issues the order's invoice
 * and the provider takes it to GİB; the older buttons send the same sale to
 * Paraşüt as a second invoice, outside the ERP's books.
 *
 * Without the ERP, or with the ERP but no provider (its invoices stay on
 * paper), the buttons are still the store's way to Paraşüt.
 *
 * @return bool
 */
function parasut_order_documents_in_erp()
{
    static $in_erp = null;

    if ($in_erp === null) {
        // erp_edoc_providers (4.61) says which provider is on; before it the
        // ERP's own Paraşüt switch did.
        $table = function_exists('waf_table_has_column') && waf_table_has_column('erp_edoc_providers', 'is_active');

        $in_erp = _parasut_order_documents_in_erp(
            defined('ERP_ENABLED') && ERP_ENABLED,
            $table ? ((int) db_value("SELECT COUNT(*) FROM erp_edoc_providers WHERE is_active = 1") > 0)
                : (defined('ERP_PARASUT_ENABLED') && ((int) ERP_PARASUT_ENABLED === 1))
        );
    }

    return $in_erp;
}

/**
 * The rule behind parasut_order_documents_in_erp().
 *
 * @param bool $erp_enabled  The ERP module is on
 * @param bool $provider_on  An e-document provider is switched on for it
 * @return bool
 */
function _parasut_order_documents_in_erp($erp_enabled, $provider_on)
{
    return (bool) $erp_enabled && (bool) $provider_on;
}

/**
 * Whether the store sends its orders to Paraşüt from the order screen's own
 * buttons (Create Invoice, E-Shipment): the integration is switched on and
 * its company and client are set. With the ERP and an e-document provider
 * on, the buttons give way to the ERP's card (parasut_order_documents_in_erp()).
 *
 * @return bool
 */
function parasut_order_buttons_on()
{
    return _parasut_order_buttons_on(
        defined('ENABLE_PARASUT') ? ENABLE_PARASUT : 0,
        defined('PARASUT_COMPANY_ID') ? PARASUT_COMPANY_ID : '',
        defined('PARASUT_CLIENT_ID') ? PARASUT_CLIENT_ID : ''
    );
}

/**
 * The rule behind parasut_order_buttons_on().
 *
 * @param mixed  $enabled     config.enable_parasut
 * @param string $company_id  The Paraşüt company
 * @param string $client_id   The Paraşüt application's client id
 * @return bool
 */
function _parasut_order_buttons_on($enabled, $company_id, $client_id)
{
    return (bool) $enabled && ((string) $company_id !== '') && ((string) $client_id !== '');
}

/**
 * Return the company's first Parasut stock location ID.
 * Fetches from the API once and caches it in config.parasut_default_warehouse_id.
 *
 * $skip is a warehouse Paraşüt has just answered as not found and whose id
 * was cleared from the setting: PARASUT_DEFAULT_WAREHOUSE_ID was read at
 * init and still holds it, so it is passed over and the first warehouse
 * fetched again (parasut_kept_warehouse_id()).
 *
 * @param string $token  Valid Parasut OAuth token
 * @param string $skip   A warehouse id not to use
 * @return array ['success' => bool, 'warehouse_id' => string|null, 'error' => string,
 *                'kept' => bool (the id is the setting's, used without asking Paraşüt)]
 */
function parasut_ensure_default_warehouse($token, $skip = '')
{
    $kept = parasut_kept_warehouse_id(defined('PARASUT_DEFAULT_WAREHOUSE_ID') ? PARASUT_DEFAULT_WAREHOUSE_ID : '', $skip);

    if ($kept !== '') {
        return ['success' => true, 'warehouse_id' => $kept, 'error' => '', 'kept' => true];
    }

    $result = _parasut_request('GET', _parasut_prefix() . '/warehouses?page[size]=1', null, $token);

    if ($result['http_code'] !== 200 || empty($result['body']['data'])) {
        return ['success' => false, 'warehouse_id' => null, 'error' => lang(array('string' => 'Could not fetch stock locations: {var:1}', 'vars' => array(_parasut_extract_error($result)))), 'kept' => false];
    }

    $warehouse_id = (string) $result['body']['data'][0]['id'];
    db("UPDATE config SET parasut_default_warehouse_id = '" . escape($warehouse_id) . "'");

    return ['success' => true, 'warehouse_id' => $warehouse_id, 'error' => '', 'kept' => false];
}

/**
 * The warehouse id the Paraşüt settings keep (config.parasut_default_warehouse_id,
 * typed on the settings card or filled by the first lookup), '' when the
 * first warehouse has to be fetched: none is kept, or the one kept is $skip.
 *
 * @param string|null $kept  PARASUT_DEFAULT_WAREHOUSE_ID
 * @param string      $skip  A warehouse Paraşüt answered as not found
 * @return string
 */
function parasut_kept_warehouse_id($kept, $skip = '')
{
    $kept = trim((string) $kept);

    return (($kept !== '') && ($kept !== trim((string) $skip))) ? $kept : '';
}

/**
 * Paraşüt's code for a currency, or '' for one Paraşüt does not take. Its
 * API definition lists TRL, USD, EUR and GBP for every document's currency;
 * TRL is its own code for the lira (the ISO code is TRY). An empty code
 * reads as the lira.
 *
 * @param string $currency  ISO code (TRY, EUR, ...)
 * @return string  TRL | USD | EUR | GBP | ''
 */
function parasut_currency_code($currency)
{
    $currency = strtoupper(trim((string) $currency));

    if (in_array($currency, array('', 'TRY', 'TL', 'TRL'), true)) {
        return 'TRL';
    }

    return in_array($currency, array('USD', 'EUR', 'GBP'), true) ? $currency : '';
}

/**
 * The attributes of the Paraşüt product card opened for a Pinegrap product.
 *
 * The price goes as list_price, the card's sales price: Paraşüt answers a
 * create that names unit_price with 201 and keeps no price on the card. v4
 * products have no product_type; inventory_tracking alone says whether the
 * card keeps stock, and initial_stock_count gives a tracked card its opening
 * stock in the create itself.
 *
 * products.price is held in the store's base currency, so the price is
 * labelled with that currency in Paraşüt's code (parasut_currency_code()).
 * For a currency Paraşüt does not take, the card opens without a sales
 * price: a price labelled in another currency would be wrong, and invoice
 * lines carry their own prices.
 *
 * The opening stock is the stock before the document the card is opened
 * for: Pinegrap's count plus what the sale behind that document already
 * took out of it ($taken_out). The document then takes those goods out at
 * Paraşüt, so they are counted out once; opened at the count alone, the
 * same goods were counted out twice. Only that document's units are added:
 * another order of the product placed before the card opened and documented
 * after it is still taken out at Paraşüt from a card that never held it, so
 * the card ends below Pinegrap's count by that order's units. Which earlier
 * orders will ever be documented at Paraşüt cannot be told here.
 *
 * @param array       $product    A products row: name, short_description, price
 *                                (minor units), inventory, inventory_quantity, tax_rate
 * @param int         $taken_out  Units of the product the documented sale took out of
 *                                inventory_quantity (parasut_stock_taken_units()); 0 for none
 * @param string|null $currency   The store's base currency (ISO code); null reads
 *                                BASE_CURRENCY_CODE, and with none defined the lira
 * @return array
 */
function parasut_product_attributes($product, $taken_out = 0, $currency = null)
{
    $track_stock = !empty($product['inventory']);

    if ($currency === null) {
        $currency = defined('BASE_CURRENCY_CODE') ? (string) BASE_CURRENCY_CODE : '';
    }

    $currency_code = parasut_currency_code($currency);

    $attributes = [
        'name' => $product['short_description'] ?: ($product['name'] ?: lang('No Product Name')),
        'list_price' => round($product['price'] / 100, 2),
        'currency' => $currency_code,
        // Default rate on the Parasut product card. Invoice lines carry their own
        // rate and override this, so it only matters for documents raised inside
        // Parasut. A null products.tax_rate means "the zone decides", which has no
        // equivalent on a product card - 0 is the safe default there.
        'vat_rate' => ($product['tax_rate'] === null || $product['tax_rate'] === '') ? 0 : (float) $product['tax_rate'],
        'inventory_tracking' => $track_stock,
    ];

    if ($currency_code === '') {
        unset($attributes['list_price'], $attributes['currency']);
    }

    if ($track_stock) {
        $attributes['initial_stock_count'] = max(0, (int) $product['inventory_quantity']) + max(0, (int) $taken_out);
    }

    return $attributes;
}

/**
 * Whether an order's sale took its stock-tracked lines out of Pinegrap's
 * stock count (products.inventory_quantity). A checkout (submit_order.php),
 * a counter sale (local_sale_complete()) and a marketplace import
 * (mp_order_take_stock()) each do when the order is placed; a sale through
 * the stock leaves 0, and a card opened at 0 plus the quantity ends at 0
 * after the document too. A cart (incomplete) took nothing.
 *
 * A cancel through process_order_cancellation() puts nothing back, so a
 * cancelled order still took its stock. The one exception is a marketplace
 * order cancelled on the marketplace: mp_order_cancel() cancels it with no
 * user (cancelled_by 0) and then puts its stock back
 * (mp_order_return_stock()). The shop's own cancel of a marketplace order
 * (order screen, order list, API, workspace) names the user and puts
 * nothing back. A restored order has cancelled_by 0 again and reads as
 * having taken its stock; one the marketplace cancelled was not taken again.
 *
 * @param array $order  orders row: status, type, cancelled_by
 * @return bool
 */
function parasut_order_took_stock($order)
{
    $status = (string) ($order['status'] ?? '');

    if (($status === '') || ($status === 'incomplete')) {
        return false;
    }

    if (($status === 'cancelled') && ((string) ($order['type'] ?? '') === 'marketplace')) {
        return (int) ($order['cancelled_by'] ?? 0) > 0;
    }

    return true;
}

/**
 * The units of each Pinegrap product the rows come to: a product on two
 * lines once. A stock count moves by whole units (erp_stock_whole()), so a
 * row without a product, or whose quantity is not whole or not above zero,
 * counts for nothing.
 *
 * @param array $rows  Order lines, invoice lines or stock moves: product_id, quantity
 * @return array [product id => units]
 */
function parasut_stock_taken_units($rows)
{
    $units = array();

    foreach ((array) $rows as $row) {
        $product_id = is_array($row) ? (int) ($row['product_id'] ?? 0) : 0;
        $quantity = is_array($row) ? (float) ($row['quantity'] ?? 0) : 0.0;

        if (($product_id <= 0) || ($quantity <= 0) || (abs($quantity - round($quantity)) >= 0.00005)) {
            continue;
        }

        $units[$product_id] = ($units[$product_id] ?? 0) + (int) round($quantity);
    }

    return $units;
}

/**
 * What the order screen's Create Invoice takes out at Paraşüt that the
 * order already took out of Pinegrap's count, per product. The sales
 * invoice goes with the shipment included (Paraşüt's default) and takes
 * the goods out itself, unless the order has its E-Shipment document: it
 * then goes without shipment and moves no stock
 * (parasut_order_invoice_with_shipment()).
 *
 * @param array $order  orders row: status, type, parasut_shipment_id
 * @param array $lines  The invoice's lines (parasut_order_invoice_lines()): product_id, quantity
 * @return array [product id => units]
 */
function parasut_order_stock_taken($order, $lines)
{
    if (trim((string) ($order['parasut_shipment_id'] ?? '')) !== '') {
        return array();
    }

    return parasut_order_took_stock($order) ? parasut_stock_taken_units($lines) : array();
}

/**
 * What an invoice the ERP driver writes at Paraşüt takes out there that its
 * sale already took out of Pinegrap's count, per product: a product card the
 * invoice opens starts from the stock before it (parasut_product_attributes()).
 *
 * - The caller's own answer ('stock_taken'), as the order screen's Create
 *   Invoice gives it (parasut_order_stock_taken()).
 * - An order's invoice: its lines, when the order took them
 *   (parasut_order_took_stock()) and the invoice goes with the shipment.
 *   One naming the order's shipment document goes without shipment and
 *   moves no stock (erp_edoc_parasut_order_shipment_id()). The invoice
 *   itself changes no count (erp_stock_post_invoice()).
 * - A typed invoice: what its own sale moves took out, wanted and applied
 *   to the count (erp_stock_apply_pending()). A move not applied yet has
 *   not lowered the count; applied later, it brings Pinegrap to where the
 *   invoice brings Paraşüt.
 *
 * Nothing is read without a line naming a product.
 *
 * @param array         $invoice  erp_invoices row (id, order_id, order_shipment_id), or 'stock_taken'
 * @param array         $lines    The invoice's lines: product_id, quantity
 * @param callable|null $read     function (string $what, int $id): 'order' => the orders row (status,
 *                                type, cancelled_by) or null, 'moves' => the invoice's applied sale moves
 *                                (product_id, quantity); null reads the database
 * @return array [product id => units]
 */
function parasut_invoice_stock_taken($invoice, $lines, $read = null)
{
    if (isset($invoice['stock_taken']) && is_array($invoice['stock_taken'])) {
        return $invoice['stock_taken'];
    }

    $units = parasut_stock_taken_units($lines);

    if ($units === array()) {
        return array();
    }

    if ($read === null) {
        $read = '_parasut_stock_taken_read';
    }

    $order_id = (int) ($invoice['order_id'] ?? 0);

    if ($order_id > 0) {
        if (erp_edoc_parasut_order_shipment_id($invoice) !== '') {
            return array();
        }

        $order = $read('order', $order_id);

        return (is_array($order) && parasut_order_took_stock($order)) ? $units : array();
    }

    return parasut_stock_taken_units((array) $read('moves', (int) ($invoice['id'] ?? 0)));
}

/**
 * The rows parasut_invoice_stock_taken() reads: an order's status, type and
 * cancelled_by, or the sale moves of a typed invoice that changed the count.
 * No stock tables (before 2026.4.4), no moves.
 *
 * @param string $what  'order' | 'moves'
 * @param int    $id    orders.id | erp_invoices.id
 * @return array|null
 */
function _parasut_stock_taken_read($what, $id)
{
    if ($what === 'order') {
        return db_item("SELECT status, type, cancelled_by FROM orders WHERE id = '" . (int) $id . "' LIMIT 1");
    }

    if (($id <= 0) || !function_exists('erp_stock_ready') || !erp_stock_ready()) {
        return array();
    }

    return (array) db_items("SELECT product_id, quantity FROM erp_stock_moves
        WHERE invoice_id = '" . (int) $id . "' AND kind = 'sale' AND direction = 'out'
          AND stock_wanted = 1 AND stock_applied = 1");
}

/**
 * Ensure a Pinegrap product exists in Parasut and return its Parasut ID.
 * Creates the product in Parasut on first call and persists the ID to the DB.
 *
 * A tracked product's opening stock is set by the create's
 * initial_stock_count: the stock before the document the card is opened
 * for, which is the count plus $taken_out (parasut_product_attributes()).
 * No /stock_updates request follows: Paraşüt answers it
 * 422 "Güncellenen stok miktarı bulunamadı." for a card opened so, and it
 * cannot seed stock from zero either (parasut_ensure_product_stock_tracking()).
 *
 * @param int    $product_id  Pinegrap products.id
 * @param string $token       Valid Parasut OAuth token
 * @param int    $taken_out   Units the sale behind the document already took out of
 *                            inventory_quantity and the document takes out at Paraşüt;
 *                            used only when the card is opened
 * @return array ['success' => bool, 'parasut_product_id' => string|null, 'track_stock' => bool, 'error' => string];
 *               'missing' => true when the products row no longer exists (no request was sent)
 */
function parasut_sync_product($product_id, $token, $taken_out = 0)
{
    // Fetch product details (needed for both new creation and track_stock flag).
    $result = mysqli_query(db::$con, "SELECT name, short_description, price, inventory, inventory_quantity, tax_rate, parasut_product_id FROM products WHERE id = '" . escape($product_id) . "' LIMIT 1");
    if ($result === false) {
        // A read that failed (a column the upgrade has not added yet, say)
        // tells nothing about the row: not 'missing', so the line is refused
        // rather than put on the default product/service.
        return ['success' => false, 'parasut_product_id' => null, 'track_stock' => false, 'error' => lang(array('string' => 'Product {var:1} could not be read from the database: {var:2}', 'vars' => array($product_id, mysqli_error(db::$con))))];
    }
    $product = mysqli_fetch_assoc($result);
    if (!$product) {
        // The product was deleted from the catalogue: its line has no
        // product of its own any more (erp_edoc_parasut_line_product_pick()).
        return ['success' => false, 'parasut_product_id' => null, 'track_stock' => false, 'error' => lang(array('string' => 'Product not found: {var:1}', 'vars' => array($product_id))), 'missing' => true];
    }

    $track_stock = !empty($product['inventory']);

    // Already synced — return cached ID. A card Paraşüt no longer holds is
    // forgotten by the press its create was refused for (_parasut_forget_kept_id()).
    if ($product['parasut_product_id']) {
        return ['success' => true, 'parasut_product_id' => $product['parasut_product_id'], 'track_stock' => $track_stock, 'error' => ''];
    }

    $body = [
        'data' => [
            'type' => 'products',
            'attributes' => parasut_product_attributes($product, $taken_out),
        ],
    ];

    $api_result = _parasut_request('POST', _parasut_prefix() . '/products', $body, $token);

    if (!in_array($api_result['http_code'], [200, 201]) || empty($api_result['body']['data']['id'])) {
        return ['success' => false, 'parasut_product_id' => null, 'track_stock' => $track_stock, 'error' => _parasut_extract_error($api_result)];
    }

    $parasut_product_id = (string) $api_result['body']['data']['id'];
    db("UPDATE products SET parasut_product_id = '" . escape($parasut_product_id) . "' WHERE id = '" . escape($product_id) . "'");

    return ['success' => true, 'parasut_product_id' => $parasut_product_id, 'track_stock' => $track_stock, 'error' => ''];
}

/**
 * One line of a shipment document: a stock movement of the product in the
 * warehouse. Paraşüt takes the lines as relationships.stock_movements and
 * refuses shipment_document_details lines with 422 "Stok kalemleri
 * doldurulmalı". The document's inflow attribute gives the direction.
 *
 * @param string|int $product_id    Paraşüt product id
 * @param string|int $warehouse_id  Paraşüt warehouse id
 * @param int        $quantity
 * @return array
 */
function parasut_shipment_stock_movement($product_id, $warehouse_id, $quantity)
{
    return [
        'type' => 'stock_movements',
        'attributes' => ['quantity' => $quantity],
        'relationships' => [
            'product' => ['data' => ['type' => 'products', 'id' => (string) $product_id]],
            'warehouse' => ['data' => ['type' => 'warehouses', 'id' => (string) $warehouse_id]],
        ],
    ];
}

/**
 * Whether E-Shipment may write the order's outgoing shipment document, and
 * which sales invoice it names, judged by the invoice Create Invoice left on
 * the order (orders.parasut_invoice_id). Asked before anything is written.
 *
 * A sales invoice that Paraşüt keeps with shipment_included true (its
 * default, and what Create Invoice sends for an order not yet shipped) took
 * its goods out of stock itself, and Paraşüt refuses to link a shipment
 * document to it (422 "İrsaliyeli faturalar ile irsaliye
 * ilişkilendirilemez."); an unlinked document would take the goods out a
 * second time. One kept with shipment_included false moved no stock: the
 * shipment document names it in relationships.invoices and takes the goods
 * out once. An invoice deleted on Paraşüt's screen since (a 404 naming it
 * as not found, parasut_stored_record_state()) is no invoice: the document
 * goes unlinked, and 'gone' names the id for the caller to clear. Any other
 * 404 ("No route matches.", say) is no answer. The unsure mark
 * (parasut_order_invoice_unsure()) is not a record whose answer can be
 * read, and a read that fails is no answer either: both refuse rather than
 * guess.
 *
 * @param string   $invoice_id  orders.parasut_invoice_id
 * @param callable $get         function (string $path): the _parasut_request() answer of
 *                              GET _parasut_prefix() . $path
 * @return array ['invoice_id' => string ('' for no invoice to name), 'error' => string ('' when it may go)],
 *               and 'gone' => the stored id when Paraşüt no longer holds that invoice
 */
function parasut_shipment_invoice_link($invoice_id, $get)
{
    $invoice_id = trim((string) $invoice_id);

    if ($invoice_id === '') {
        return array('invoice_id' => '', 'error' => '');
    }

    if (parasut_order_invoice_unsure($invoice_id)) {
        return array('invoice_id' => '', 'error' => lang('The last try to invoice this order got no answer from Paraşüt (Check Paraşüt), so it is not known whether its sales invoice carries the shipment. Press Create Invoice again to settle the invoice first; no shipment document was created.'));
    }

    $read = $get('/sales_invoices/' . rawurlencode($invoice_id));

    if (parasut_stored_record_state((array) $read, 'sales_invoices') === 'gone') {
        return array('invoice_id' => '', 'error' => '', 'gone' => $invoice_id);
    }

    $attributes = (((int) ($read['http_code'] ?? 0) === 200) && is_array($read['body']['data']['attributes'] ?? null))
        ? $read['body']['data']['attributes']
        : array();
    $included = array_key_exists('shipment_included', $attributes) ? $attributes['shipment_included'] : null;

    if (!is_bool($included)) {
        return array('invoice_id' => '', 'error' => lang(array(
            'string' => 'Paraşüt could not be asked whether this order\'s sales invoice (ID: {var:1}) carries the shipment: {var:2}. No shipment document was created; try again in a minute.',
            'vars' => array($invoice_id, parasut_refusal_words(_parasut_extract_error((array) $read))),
        )));
    }

    if ($included) {
        return array('invoice_id' => '', 'error' => lang(array(
            'string' => 'This order\'s sales invoice in Paraşüt (ID: {var:1}) carries the shipment: its goods left stock with it, so a shipment document would count them out a second time. A separate e-waybill needs the invoice issued without shipment. No shipment document was created.',
            'vars' => array($invoice_id),
        )));
    }

    return array('invoice_id' => $invoice_id, 'error' => '');
}

/**
 * The order's outgoing shipment_documents body naming its sales invoice
 * (relationships.invoices), when parasut_shipment_invoice_link() gave one.
 *
 * @param array  $body        The shipment_documents body
 * @param string $invoice_id  Paraşüt sales invoice id, '' for none
 * @return array
 */
function parasut_shipment_with_invoice($body, $invoice_id)
{
    $invoice_id = trim((string) $invoice_id);

    if ($invoice_id !== '') {
        $body['data']['relationships']['invoices'] = array('data' => array(array('type' => 'sales_invoices', 'id' => $invoice_id)));
    }

    return $body;
}

/**
 * Whether a Paraşüt product keeps stock. v4 products carry inventory_tracking
 * and no product_type (a GET answers null for it).
 *
 * @param array|null $attributes  data.attributes of GET /products/{id}
 * @return bool
 */
function parasut_product_stock_tracked($attributes)
{
    return !empty($attributes['inventory_tracking']);
}

/**
 * Ensure an existing Parasut product has inventory tracking enabled (inventory_tracking=true)
 * AND has at least $needed_qty available stock at the given warehouse.
 *
 * Parasut's documented way to add stock to an existing product via API is to create an inflow
 * shipment_document (a "Yeni Gelen İrsaliye" / incoming stock entry). The `/stock_updates` endpoint
 * only works on products that already have stock movements — it cannot seed stock from zero, and
 * returns "Güncellenen stok miktarı bulunamadı" ("no updated stock amount found") when there is
 * nothing to update.
 *
 * $needed_qty is the whole quantity the outgoing document takes of the
 * product: a product on two lines of an order is asked about once, for
 * both (parasut_shipment_stock_needs()).
 *
 * @param string        $parasut_product_id
 * @param int           $needed_qty       Minimum stock that must be present for the upcoming outflow.
 * @param string|null   $seed_contact_id  Parasut contact id to attribute the inflow document to.
 * @param string|null   $warehouse_id     Warehouse to credit the stock to.
 * @param string        $token
 * @param callable|null $request          function (string $method, string $path, array|null $body):
 *                                        the _parasut_request() answer of $method _parasut_prefix() . $path;
 *                                        null sends it with $token
 * @return array ['success' => bool, 'current_stock' => float, 'error' => string], and on a
 *               failed request 'answer' => its _parasut_request() answer, which says whether
 *               Paraşüt no longer holds the product, warehouse or contact (parasut_missing_record())
 */
function parasut_ensure_product_stock_tracking($parasut_product_id, $needed_qty, $seed_contact_id, $warehouse_id, $token, $request = null)
{
    if ($request === null) {
        $request = function ($method, $path, $body = null) use ($token) {
            return _parasut_request($method, _parasut_prefix() . $path, $body, $token);
        };
    }

    // 1) Fetch current product state.
    $get = $request('GET', '/products/' . urlencode($parasut_product_id), null);
    if ($get['http_code'] !== 200 || empty($get['body']['data'])) {
        return ['success' => false, 'current_stock' => 0, 'error' => lang(array('string' => 'Product fetch failed: {var:1}', 'vars' => array(_parasut_extract_error($get)))), 'answer' => $get];
    }
    $attrs = $get['body']['data']['attributes'] ?? [];
    $is_tracked = parasut_product_stock_tracked($attrs);
    $current_stock = (float) ($attrs['stock_count'] ?? 0);

    // 2) Enable inventory tracking if it's off (the product was created without stock).
    if (!$is_tracked) {
        $patch_body = [
            'data' => [
                'id' => (string) $parasut_product_id,
                'type' => 'products',
                'attributes' => ['inventory_tracking' => true],
            ],
        ];
        $patch = $request('PUT', '/products/' . urlencode($parasut_product_id), $patch_body);
        if (!in_array($patch['http_code'], [200, 201])) {
            return ['success' => false, 'current_stock' => 0, 'error' => lang(array('string' => 'Product patch failed: {var:1}', 'vars' => array(_parasut_extract_error($patch)))), 'answer' => $patch];
        }
        $current_stock = 0; // freshly enabled — no stock yet.
    }

    // If the product already has stock movements (e.g. from sales_invoices), Parasut allows
    // negative stock outflow — no seeding needed.
    $has_movements = !empty($attrs['has_stock_movements']);
    if ($has_movements) {
        return ['success' => true, 'current_stock' => $current_stock, 'error' => ''];
    }

    // 3) If stock is insufficient for the outgoing shipment, seed it via an inflow shipment_document.
    if ($current_stock < $needed_qty && $seed_contact_id && $warehouse_id) {
        $seed_qty = max(1, (int) ceil($needed_qty - $current_stock));
        $seed_body = [
            'data' => [
                'type' => 'shipment_documents',
                'attributes' => [
                    'description' => lang('Opening stock entry (Pinegrap sync)'),
                    'issue_date' => date('Y-m-d'),
                    // inflow: true = incoming shipment / stock entry.
                    'inflow' => true,
                ],
                'relationships' => [
                    'contact' => [
                        'data' => ['type' => 'contacts', 'id' => (string) $seed_contact_id],
                    ],
                    'stock_movements' => [
                        'data' => [parasut_shipment_stock_movement($parasut_product_id, $warehouse_id, $seed_qty)],
                    ],
                ],
            ],
        ];
        $seed = $request('POST', '/shipment_documents', $seed_body);
        if (!in_array($seed['http_code'], [200, 201])) {
            return [
                'success' => false,
                'current_stock' => $current_stock,
                'error' => lang(array('string' => 'Stock seeding (inflow) failed: {var:1}', 'vars' => array(_parasut_extract_error($seed)))),
                'answer' => $seed,
            ];
        }
        $current_stock += $seed_qty;
    }

    return ['success' => true, 'current_stock' => $current_stock, 'error' => ''];
}

/**
 * What E-Shipment's document takes of each Paraşüt product: the quantities
 * of its lines added up per product, in the order the products first come,
 * each named by its first line. The stock step asks once per product for
 * the total (parasut_ensure_product_stock_tracking()). Asked line by line,
 * a product on two lines (two recipients) passed a stock that covered each
 * line but not both, and a seed brought by the first line left the second
 * unchecked.
 *
 * @param array $lines  ['parasut_product_id' => string, 'quantity' => int, 'name' => string] per stock-tracked line
 * @return array  [['parasut_product_id' => string, 'quantity' => int, 'name' => string], ...]
 */
function parasut_shipment_stock_needs($lines)
{
    $needs = array();
    $at = array();

    foreach ((array) $lines as $line) {
        $id = (string) ($line['parasut_product_id'] ?? '');

        if (!isset($at[$id])) {
            $at[$id] = count($needs);
            $needs[] = array('parasut_product_id' => $id, 'quantity' => 0, 'name' => (string) ($line['name'] ?? ''));
        }

        $needs[$at[$id]]['quantity'] += (int) ($line['quantity'] ?? 0);
    }

    return $needs;
}

/**
 * Why E-Shipment cannot write the order's document, or '': lines naming a
 * product deleted from the catalogue since the order (order_items keeps the
 * product_id, the products row is gone). Whether such a product kept stock
 * and which Paraşüt product it was cannot be told any more, and left out it
 * would be missing from the waybill without a word. A line with no product
 * at all is not one of them.
 *
 * @param array $items  order_items rows: product_id, product_name, product_row_id
 *                      (products.id through the LEFT JOIN, null when the row is gone)
 * @return string
 */
function parasut_shipment_deleted_products_refusal($items)
{
    $names = array();

    foreach ((array) $items as $item) {
        $product_id = (int) ($item['product_id'] ?? 0);

        if (($product_id <= 0) || ((int) ($item['product_row_id'] ?? 0) > 0)) {
            continue;
        }

        $name = trim((string) ($item['product_name'] ?? ''));
        $names[$product_id] = ($name !== '') ? '"' . $name . '"' : '#' . $product_id;
    }

    if ($names === array()) {
        return '';
    }

    return lang(array(
        'string' => 'Lines of this order name products deleted from the catalogue since the order was placed: {var:1}. Pinegrap cannot tell whether they kept stock or which Paraşüt product they were, so no shipment document was created. Write this shipment in Paraşüt by hand.',
        'vars' => implode(', ', $names),
    ));
}

/**
 * The attributes the buyer card of an order is opened with at Paraşüt.
 *
 * The identity number goes in tax_number, the one field Paraşüt keeps for
 * a company's VKN and a person's TCKN; contact_type says which. The buyer
 * is a company when the order names a billing company, a person otherwise,
 * and a person whenever the number has 11 digits (a TCKN, also that of a
 * sole proprietor trading under a name), as the ERP's Paraşüt driver opens
 * a card (erp_edoc_parasut_contact_attributes()).
 *
 * A Turkish billing address goes in Paraşüt's own fields, mapped as the
 * ERP's driver writes a card (erp_address_from_checkout()): the street
 * lines in address, the checkout's state field (the province) in city, its
 * city field (the district) in district, the postcode in postal_code. An
 * e-Archive or e-Invoice reads the city and the district off the card. The
 * address is Turkish when its billing country is TR or, with none given,
 * when the store's is (erp_account_country(), the way
 * erp_address_from_checkout() reads an empty country). Any other address
 * goes as one line in address (street, city, state, postcode, country)
 * with no city, district or postal_code. The ERP's address code
 * (includes/erp/accounts.php) must be loaded for a Turkish address;
 * parasut_sync_contact() loads it.
 *
 * @param array       $order      Order row (billing_* fields)
 * @param string      $id_number  The buyer's VKN or TCKN (_parasut_order_identity()), '' for none
 * @param string|null $country    The country the address is read by; null for the billing
 *                                country, else the store's (erp_account_country(), which asks
 *                                the database when the order names none)
 * @return array
 */
function parasut_order_contact_attributes($order, $id_number, $country = null)
{
    $id_number = trim((string) $id_number);
    $billing_company = trim($order['billing_company'] ?? '');

    if (($billing_company !== '') && (strlen($id_number) !== 11)) {
        $contact_type = 'company';
    } else {
        $contact_type = 'person';
    }

    $name = ($billing_company !== '')
        ? $billing_company
        : trim(trim($order['billing_first_name'] ?? '') . ' ' . trim($order['billing_last_name'] ?? ''));

    $attributes = [
        'account_type' => 'customer',
        'contact_type' => $contact_type,
        'name' => $name,
        'email' => $order['billing_email_address'] ?? '',
        'phone' => $order['billing_phone_number'] ?? '',
    ];

    if ($id_number !== '') {
        $attributes['tax_number'] = $id_number;
    }

    if ($country === null) {
        $country = erp_account_country((string) ($order['billing_country'] ?? ''));
    }

    if (strtoupper(trim((string) $country)) === 'TR') {
        $place = erp_address_from_checkout((string) ($order['billing_city'] ?? ''), (string) ($order['billing_state'] ?? ''), 'TR');
        $fields = [
            // The street lines joined as the ERP account takes them.
            'address' => trim(trim((string) ($order['billing_address_1'] ?? '')) . ' ' . trim((string) ($order['billing_address_2'] ?? ''))),
            'city' => $place['city'],
            'district' => $place['district'],
            'postal_code' => trim((string) ($order['billing_zip_code'] ?? '')),
        ];

        // An empty field is left off, as the folded address was.
        foreach ($fields as $key => $value) {
            if ($value !== '') {
                $attributes[$key] = $value;
            }
        }

        return $attributes;
    }

    // Build address string.
    $addr_parts = array_filter([
        $order['billing_address_1'] ?? '',
        $order['billing_address_2'] ?? '',
        $order['billing_city'] ?? '',
        $order['billing_state'] ?? '',
        $order['billing_zip_code'] ?? '',
        $order['billing_country'] ?? '',
    ]);
    if ($addr_parts) {
        $attributes['address'] = implode(', ', $addr_parts);
    }

    return $attributes;
}

/**
 * The number an order's buyer card is looked up by at Paraşüt before one is
 * opened: the order's VKN/TCKN (_parasut_order_identity()) as digits, when
 * it passes the tax authority's check digits (erp_edoc_tax_number_valid()).
 * '' for a buyer without one, for the final consumer's 11111111111, which
 * many buyers share, and for a number that fails the check. A real VKN/TCKN
 * always passes. Buyers who typed a placeholder that fails it (00000000000,
 * 2222222222, 12345678901) each keep a card of their own; a placeholder that
 * passes it (1234567890, 4444444444) is looked up like a real number, and a
 * card under it is taken only when it is the buyer's own
 * (parasut_order_contact_card_is_buyers()). Needs the ERP's e-document
 * registry (_parasut_order_invoice_load()).
 *
 * @param string $identity  _parasut_order_identity()
 * @return string  Digits, or ''
 */
function parasut_order_contact_search_number($identity)
{
    $digits = preg_replace('/\D/', '', (string) $identity);

    return erp_edoc_tax_number_valid($digits) ? $digits : '';
}

/**
 * Whether a card found under the buyer's number is this buyer's: the same
 * name and e-mail as the card the order would open
 * (parasut_order_contact_attributes()), case and outer spaces aside, as the
 * ERP's driver matches a final consumer's card
 * (erp_edoc_parasut_final_consumer_card_matches()). A placeholder that
 * passes the check digits is typed by strangers too, and a card is taken as
 * it is: the invoice carries no buyer details of its own, so another buyer's
 * card would put the e-Arşiv in their name and mail it to them.
 *
 * @param mixed $card        attributes of a listed card
 * @param array $attributes  parasut_order_contact_attributes()
 * @return bool
 */
function parasut_order_contact_card_is_buyers($card, $attributes)
{
    if (!is_array($card)) {
        return false;
    }

    foreach (array('name', 'email') as $field) {
        $have = mb_strtolower(trim((string) ($card[$field] ?? '')), 'UTF-8');
        $want = mb_strtolower(trim((string) ($attributes[$field] ?? '')), 'UTF-8');

        if ($have !== $want) {
            return false;
        }
    }

    return true;
}

/**
 * The Paraşüt card an order of the order screen's buttons goes on.
 *
 * A buyer with a VKN/TCKN (parasut_order_contact_search_number()) keeps one
 * card over all orders. The cards under the number are read the way the
 * ERP's driver reads them before it opens one (erp_edoc_parasut_contact_for()):
 * GET contacts?filter[tax_number], newest first, page by page up to
 * ERP_EDOC_PARASUT_FIND_PAGES pages, and the first card that is not
 * archived, carries exactly the number and is this buyer's
 * (parasut_order_contact_card_is_buyers()) is taken as it is. A page that
 * answers neither a list nor 404 (no card under the number) refuses: no card
 * is opened while one may be unread. The driver's own function is not called
 * because it opens a missing card with the ERP account's details and logs
 * every request as an ERP e-document call.
 *
 * Without such a card, and for a buyer without a number or with the final
 * consumer's, a card is opened with $attributes. Needs the driver loaded
 * (_parasut_order_invoice_load()).
 *
 * @param string   $identity    _parasut_order_identity()
 * @param array    $attributes  parasut_order_contact_attributes(), for a new card
 * @param callable $request     function (string $method, string $path, array|null $body):
 *                              the _parasut_request() answer of $method _parasut_prefix() . $path
 * @return array ['id' => string, 'reused' => bool (an existing card), 'error' => string]
 */
function parasut_order_contact_for($identity, $attributes, $request)
{
    $number = parasut_order_contact_search_number($identity);
    $page = 0;

    while (($number !== '') && ($page < (int) ERP_EDOC_PARASUT_FIND_PAGES)) {
        $page++;

        // Paraşüt takes ten requests in any ten seconds, so a long list is
        // mostly a wait, which PHP's time limit counts on Windows.
        if ($page === 2) {
            erp_edoc_parasut_allow_time();
        }

        // Newest first: the list's own order is by balance, which an invoice
        // written meanwhile moves.
        $found = $request('GET', '/contacts?filter[tax_number]=' . rawurlencode($number)
            . '&sort=-id&page[number]=' . $page . '&page[size]=' . ERP_EDOC_PARASUT_PAGE_SIZE, null);

        // 404: no card under the number.
        if ((int) ($found['http_code'] ?? 0) === 404) {
            break;
        }

        if (!erp_edoc_parasut_ok($found) || !is_array($found['body'] ?? null)) {
            return array('id' => '', 'reused' => false, 'error' => lang(array(
                'string' => 'Paraşüt would not search its contacts: {var:1}',
                'vars' => array(_parasut_extract_error($found)),
            )));
        }

        foreach ((array) ($found['body']['data'] ?? array()) as $item) {
            $id = is_array($item) ? trim((string) ($item['id'] ?? '')) : '';
            $card = (is_array($item) && is_array($item['attributes'] ?? null)) ? $item['attributes'] : array();

            // The filter is the server's; a card whose number only begins
            // the same way is another buyer's, and so is one under the same
            // number with another name or e-mail.
            if (($id !== '') && empty($card['archived'])
                && (preg_replace('/\D/', '', (string) ($card['tax_number'] ?? '')) === $number)
                && parasut_order_contact_card_is_buyers($card, $attributes)) {
                return array('id' => $id, 'reused' => true, 'error' => '');
            }
        }

        if ($page >= max(1, (int) ($found['body']['meta']['total_pages'] ?? 1))) {
            break;
        }
    }

    $created = $request('POST', '/contacts', array('data' => array('type' => 'contacts', 'attributes' => $attributes)));
    $id = trim((string) ($created['body']['data']['id'] ?? ''));

    if (!in_array((int) ($created['http_code'] ?? 0), array(200, 201), true) || ($id === '')) {
        return array('id' => '', 'reused' => false, 'error' => lang(array(
            'string' => 'Contact create failed: {var:1}',
            'vars' => array(_parasut_extract_error($created)),
        )));
    }

    return array('id' => $id, 'reused' => false, 'error' => '');
}

/**
 * The Parasut contact an order goes on: the one the order already holds, else
 * the card Paraşüt keeps for the buyer's VKN/TCKN, else a new one
 * (parasut_order_contact_for()). Stores its id in orders.parasut_contact_id.
 *
 * @param array $order Full order row from DB (billing_* fields, contact_id, id)
 * @return array ['success' => bool, 'parasut_contact_id' => string|null, 'error' => string]
 */
function parasut_sync_contact($order)
{
    $token_result = parasut_get_token();
    if (!$token_result['success']) {
        return ['success' => false, 'parasut_contact_id' => null, 'error' => $token_result['error']];
    }
    $token = $token_result['token'];

    // If we already linked a Parasut contact to this order, reuse it.
    if (!empty($order['parasut_contact_id'])) {
        return ['success' => true, 'parasut_contact_id' => $order['parasut_contact_id'], 'error' => ''];
    }

    // The address is read by the ERP's accounts code (erp_account_country(),
    // erp_address_from_checkout()) and the search by the driver's; E-Shipment
    // does not load them itself.
    _parasut_order_invoice_load();

    $identity = _parasut_order_identity($order);

    // A repeat buyer with a VKN/TCKN goes on the card opened for an earlier
    // order; a new card carries the order's own details.
    $contact = parasut_order_contact_for($identity, parasut_order_contact_attributes($order, $identity), function ($method, $path, $body) use ($token) {
        return _parasut_request($method, _parasut_prefix() . $path, $body, $token);
    });

    if ($contact['error'] !== '') {
        return ['success' => false, 'parasut_contact_id' => null, 'error' => $contact['error']];
    }

    $parasut_contact_id = $contact['id'];

    // Persist the Parasut contact ID on the order.
    db("UPDATE orders SET parasut_contact_id = '" . escape($parasut_contact_id) . "' WHERE id = '" . escape($order['id']) . "'");

    return ['success' => true, 'parasut_contact_id' => $parasut_contact_id, 'error' => ''];
}

/**
 * Load what an order's Paraşüt invoice is built with: the ERP's order lines
 * (includes/erp/order_bridge.php) and the Paraşüt driver's VAT shift,
 * refusals and sales invoice body (includes/erp/edoc/parasut.php), and the
 * address mapping the buyer card is opened with (includes/erp/accounts.php).
 * They are loaded whether the ERP is switched on or not; its tables are
 * installed either way, and the order's lines are the ones its ERP invoice
 * carries.
 *
 * @return void
 */
function _parasut_order_invoice_load()
{
    require_once(PG_FUNCTIONS_DIR . '/includes/erp/bootstrap.php');
    require_once(PG_FUNCTIONS_DIR . '/includes/erp/edoc/parasut.php');
}

/**
 * The lines an order's Paraşüt invoice carries, as the ERP puts them on the
 * order's invoice (erp_order_lines()): the lines the checkout charged, the
 * order discount spread over them and the VAT taken again per rate on what
 * it leaves, the shipping, the surcharge and the instalment charge each with
 * its VAT inside, at the rate of the goods. What the invoice must come to is
 * what the customer was charged with the gift card added back: a gift card
 * is a means of payment, not a reduction of the sale. A charge that left a
 * rate's total out of reach (an order placed before the checkout settled
 * its charges, Pay with iyzico's instalment charge) goes the kurus lower
 * that reaches it, and the invoice comes to that much less (erp_order_lines()).
 *
 * @param array     $order          orders row
 * @param array     $items          order_items rows joined with the product (short_description,
 *                                  product_tax_rate, product_start), saved_for_later left out
 * @param bool|null $charges_taxed  The charges hold VAT; null asks the store (erp_shipping_taxed())
 * @param bool|null $clearcommerce  The gateway charges recurring lines itself; null reads the store's
 * @return array ['lines' => array, 'totals' => array, 'expected' => int kurus, 'charged' => int
 *                lines the checkout charged]
 */
function parasut_order_invoice_lines($order, $items, $charges_taxed = null, $clearcommerce = null)
{
    $charged = erp_order_charged_items($order, (array) $items, $clearcommerce);
    $built = !empty($charged)
        ? erp_order_lines($order, $charged, $charges_taxed, true)
        : array('lines' => array(), 'totals' => array('grand_total' => 0, 'charges_left' => 0));

    return array(
        'lines' => $built['lines'],
        'totals' => $built['totals'],
        'expected' => (int) ($order['total'] ?? 0) + (int) ($order['gift_card_discount'] ?? 0) - (int) $built['totals']['charges_left'],
        'charged' => count($charged),
    );
}

/**
 * Why an order's lines (parasut_order_invoice_lines()) cannot go to Paraşüt,
 * or '' when they can: a rate Paraşüt does not take, a line below zero
 * (erp_edoc_parasut_negative_line_refusal()), a total its VAT cannot come to
 * (erp_edoc_parasut_total_refusal()), a line with no product to go on. Asked
 * before anything is sent, so that no buyer card is opened at Paraşüt for an
 * invoice that cannot follow.
 *
 * @param array       $lines     erp_order_lines() lines
 * @param string      $currency  The order's currency, for the amounts in the reason
 * @param string|null $default   The default product/service id; null reads the setting
 * @return string
 */
function parasut_order_invoice_refusal($lines, $currency, $default = null)
{
    $taken = erp_edoc_parasut_vat_rates();
    $refused = array();

    foreach ((array) $lines as $line) {
        $rate = round((float) ($line['tax_rate'] ?? 0), 3);

        if (!in_array($rate, $taken, true) && !in_array($rate, $refused, true)) {
            $refused[] = $rate;
        }
    }

    if (!empty($refused)) {
        return lang(array(
            'string' => 'Paraşüt takes VAT at {var:1} only; this order was charged {var:2}, so it cannot be invoiced there.',
            'vars' => array(
                implode(', ', array_map('erp_percent_text', $taken)),
                implode(', ', array_map('erp_percent_text', $refused)),
            ),
        ));
    }

    $refusal = erp_edoc_parasut_negative_line_refusal($lines);

    if ($refusal !== '') {
        return $refusal;
    }

    $refusal = erp_edoc_parasut_total_refusal($lines, $currency);

    if ($refusal !== '') {
        return $refusal;
    }

    return erp_edoc_parasut_product_refusal($lines, $default);
}

/**
 * The attributes of an order's e-document, as the ERP's Paraşüt driver
 * writes them for the order's invoice (erp_edoc_parasut_edoc_attributes()):
 * the exemption behind a zero-rated line, the scenario and the buyer's
 * mailbox of an e-Invoice, and for an internet sale's e-Archive the web
 * address, the payment and the carrier (VUK 509). What such an e-Archive
 * cannot go without is refused here, before anything is sent.
 *
 * @param array  $sale        erp_order_sale_fields()
 * @param array  $lines       The order's invoice lines, each with its vat_exemption_code
 * @param string $kind        'einvoice' | 'earchive'
 * @param string $alias       The buyer's GİB mailbox (e-Invoice)
 * @param string $issue_date  'Y-m-d': the sales invoice's day, which a payment or
 *                            shipment date the order does not give falls back to
 * @return array ['attributes' => array, 'error' => string]
 */
function parasut_order_edoc_attributes($sale, $lines, $kind, $alias, $issue_date)
{
    // These documents have always gone as commercial e-Invoices, and an order
    // carries no invoice note to name an "other" exemption in words.
    $invoice = array_merge((array) $sale, array(
        'issue_date' => (string) $issue_date,
        'edoc_scenario' => 'commercial',
        'notes' => '',
    ));

    $missing = erp_edoc_parasut_document_missing($invoice, $kind);

    if (!empty($missing)) {
        return array('attributes' => array(), 'error' => lang(array(
            'string' => 'An internet-sale e-Archive invoice names the web address and the carrier, and this order does not give these: {var:1}. The carrier is set on the order\'s shipping method (Carrier Title, Carrier VKN). Nothing was sent to Paraşüt.',
            'vars' => implode(', ', $missing),
        )));
    }

    $attributes = erp_edoc_parasut_edoc_attributes($invoice, $lines, $kind, $alias);

    if (isset($attributes['error'])) {
        return array('attributes' => array(), 'error' => (string) $attributes['error']);
    }

    return array('attributes' => $attributes, 'error' => '');
}

/**
 * What orders.parasut_invoice_id keeps after the order's POST sales_invoices:
 * the record's id when Paraşüt names one, '' when it refused the request (a
 * 4xx wrote nothing), and the unsure mark (ERP_EDOC_PARASUT_UNSURE) when no
 * answer, a 5xx or a success naming no record leaves open that it wrote one
 * (erp_edoc_parasut_answer_unsure()). Needs the driver loaded
 * (_parasut_order_invoice_load()).
 *
 * @param array $result  _parasut_request() answer
 * @return string
 */
function parasut_order_invoice_id_after_create($result)
{
    $id = trim((string) ($result['body']['data']['id'] ?? ''));

    if (in_array((int) ($result['http_code'] ?? 0), array(200, 201), true) && ($id !== '')) {
        return $id;
    }

    return erp_edoc_parasut_answer_unsure($result) ? ERP_EDOC_PARASUT_UNSURE : '';
}

/**
 * Whether orders.parasut_invoice_id holds the unsure mark rather than a
 * record's id: the order's last POST sales_invoices got no answer that
 * settled it (parasut_order_invoice_id_after_create()), so Paraşüt may or
 * may not hold the record. The mark is the driver's ERP_EDOC_PARASUT_UNSURE;
 * a record's id is digits. Readable without the driver loaded, for the
 * order list and the order screen.
 *
 * @param string|null $value  orders.parasut_invoice_id
 * @return bool
 */
function parasut_order_invoice_unsure($value)
{
    $mark = defined('ERP_EDOC_PARASUT_UNSURE') ? ERP_EDOC_PARASUT_UNSURE : '?';

    return strpos(trim((string) $value), $mark) === 0;
}

/**
 * The order screen's badge for the order's Paraşüt invoice, beside Create
 * Invoice ($in_button) or in the Order Information card's status row:
 * "Sent" with the record's id, "Not Sent" in the row, and for the unsure
 * mark a warning that the last create got no answer, so Paraşüt is to be
 * checked. The mark is never shown as "Sent" or as an id.
 *
 * @param string|null $value      orders.parasut_invoice_id
 * @param bool        $in_button
 * @return string HTML
 */
function parasut_order_invoice_status_badge($value, $in_button = false)
{
    $value = trim((string) $value);

    if (parasut_order_invoice_unsure($value)) {
        return ($in_button ? ' ' : '')
            . '<span class="badge bg-warning text-dark' . ($in_button ? ' ms-1' : '') . '" title="' . h(lang('The last try to invoice this order got no answer from Paraşüt, which may hold its sales invoice.')) . '">'
            . h(lang('Check Paraşüt')) . '</span>';
    }

    if ($value !== '') {
        return $in_button
            ? ' <span class="badge bg-success ms-1" title="' . lang('Parasut ID') . ': ' . h($value) . '">' . lang('Sent') . '</span>'
            : '<span class="badge bg-success">' . lang('Sent') . '</span>'
                . ' <small class="text-muted">' . lang('Parasut ID') . ': ' . h($value) . '</small>';
    }

    return $in_button ? '' : '<span class="badge bg-secondary">' . lang('Not Sent') . '</span>';
}

/**
 * The sales invoices in a list answer that are the order's: the driver's
 * match (erp_edoc_parasut_written_matches(): the same description, issue
 * date and contact, not cancelled), with a record that is already an
 * e-document kept in. The driver leaves those out because its own issued
 * record of a rejected invoice is not an unanswered try's; an order has one
 * sales invoice only, and one converted on Paraşüt's screen after the
 * unanswered press is still the order's. Needs the driver loaded.
 *
 * @param array  $body        The decoded answer of GET sales_invoices (include=active_e_document)
 * @param array  $payload     The sales_invoices body the order goes with
 * @param string $contact_id  The order's Paraşüt contact
 * @return array id => ['attributes' => array, 'edoc_id' => string ('' while a draft)]
 */
function parasut_order_invoice_matches($body, $payload, $contact_id)
{
    $body = is_array($body) ? $body : array();
    $issued = array();

    if (is_array($body['data'] ?? null)) {
        foreach ($body['data'] as $index => $item) {
            $edoc_id = is_array($item) ? trim((string) ($item['relationships']['active_e_document']['data']['id'] ?? '')) : '';

            if ($edoc_id !== '') {
                $issued[trim((string) ($item['id'] ?? ''))] = $edoc_id;
                unset($body['data'][$index]['relationships']['active_e_document']);
            }
        }
    }

    $matches = array();

    foreach (erp_edoc_parasut_written_matches($body, $payload, $contact_id) as $id => $attributes) {
        $matches[$id] = array('attributes' => (array) $attributes, 'edoc_id' => (string) ($issued[(string) $id] ?? ''));
    }

    return $matches;
}

/**
 * Looks at Paraşüt for the sales invoice an unanswered Create Invoice may
 * have written, the way the ERP's driver looks for its own
 * (erp_edoc_parasut_find_written()): the contact's sales invoices of the
 * issue date, newest first, up to ERP_EDOC_PARASUT_FIND_PAGES pages,
 * matched by parasut_order_invoice_matches(). A page that is not answered,
 * or nothing matched with pages left unread
 * (erp_edoc_parasut_search_incomplete()), is an error: the order is then
 * not taken for never invoiced. Needs the driver loaded.
 *
 * @param array    $payload     The sales_invoices body the order goes with (description, issue date)
 * @param string   $contact_id  The order's Paraşüt contact
 * @param callable $get         function (string $path): the _parasut_request() answer of
 *                              GET _parasut_prefix() . $path
 * @return array ['records' => id => ['attributes' => array, 'edoc_id' => string], 'error' => string]
 */
function parasut_order_invoice_find($payload, $contact_id, $get)
{
    $records = array();
    $date = (string) ($payload['data']['attributes']['issue_date'] ?? '');
    $page = 0;
    $total_pages = 1;

    while ($page < (int) ERP_EDOC_PARASUT_FIND_PAGES) {
        $page++;
        $list = $get('/sales_invoices?filter[contact_id]=' . rawurlencode((string) $contact_id)
            . '&filter[issue_date]=' . rawurlencode($date) . '&sort=-id&include=active_e_document'
            . '&page[number]=' . $page . '&page[size]=' . ERP_EDOC_PARASUT_PAGE_SIZE);

        if (!erp_edoc_parasut_ok($list) || !is_array($list['body'] ?? null)) {
            return array('records' => array(), 'error' => lang(array(
                'string' => 'The last try got no answer from Paraşüt, and Paraşüt could not be asked whether it kept the invoice: {var:1}. Nothing was sent; try again in a minute.',
                'vars' => array(_parasut_extract_error($list)),
            )));
        }

        $records += parasut_order_invoice_matches($list['body'], $payload, $contact_id);
        $total_pages = max(1, (int) ($list['body']['meta']['total_pages'] ?? 1));

        if ($page >= $total_pages) {
            break;
        }
    }

    if (erp_edoc_parasut_search_incomplete($records, $page, $total_pages)) {
        return array('records' => array(), 'error' => lang('The last try got no answer from Paraşüt, and the buyer has more invoices on that day than can be looked through. Nothing was sent; look for the record on Paraşüt\'s screen.'));
    }

    return array('records' => $records, 'error' => '');
}

/**
 * The order's sales_invoices body for an order whose outgoing shipment
 * document E-Shipment already wrote (orders.parasut_shipment_id): sent
 * without shipment (shipment_included false) and naming that document
 * (relationships.shipment_documents). A sales invoice Paraşüt keeps with
 * the shipment included, its default, takes its goods out of stock itself,
 * so beside the shipment document it would count them out a second time.
 * Without a shipment document the body stays as it is.
 *
 * @param array  $body         The sales_invoices body (erp_edoc_parasut_invoice_payload())
 * @param string $shipment_id  orders.parasut_shipment_id
 * @return array
 */
function parasut_order_invoice_with_shipment($body, $shipment_id)
{
    $shipment_id = trim((string) $shipment_id);

    if ($shipment_id !== '') {
        $body['data']['attributes']['shipment_included'] = false;
        $body['data']['relationships']['shipment_documents'] = array('data' => array(array('type' => 'shipment_documents', 'id' => $shipment_id)));
    }

    return $body;
}

/**
 * What a read of a record whose id an order keeps (orders.parasut_invoice_id,
 * parasut_shipment_id) says about that record: 'kept' when Paraşüt answers
 * it (200); 'gone' when it answers 404 naming that record as not found
 * (title "Record was not found", detail SalesInvoice-<id> or
 * ShipmentDocument-<id>), as it does for a record deleted on Paraşüt's
 * screen; 'unknown' for any other answer, on which nothing is guessed.
 *
 * A 404 that does not name the record says nothing about it: Paraşüt
 * answers "No route matches." for a path it does not know. A company number
 * the token's user cannot reach, which the settings box saves as typed,
 * answers 401 "No access to company: <n>". No connection, a timeout, a 5xx,
 * 401/403 and 429 are no answer either; the id is cleared on none of them.
 *
 * What this cannot tell apart: a company number that points at another
 * company the user can reach (one of their companies mistyped, or a switch
 * from the demo company to the real one) answers "Record was not found" for
 * this company's ids too, so a press clears the stored id and writes a new
 * record at that company. A switch wants that; once a wrong number is put
 * right, the order holds the new id while the first record is still at the
 * first company.
 *
 * @param array  $read  The _parasut_request() answer of GET sales_invoices/{id} or shipment_documents/{id}
 * @param string $type  'sales_invoices' or 'shipment_documents': the kind of record read
 * @return string 'kept' | 'gone' | 'unknown'
 */
function parasut_stored_record_state($read, $type)
{
    $read = (array) $read;

    if (trim((string) ($read['curl_error'] ?? '')) !== '') {
        return 'unknown';
    }

    $code = (int) ($read['http_code'] ?? 0);

    if ($code === 200) {
        return 'kept';
    }

    // The model name Paraşüt writes before the id in a not-found detail.
    $models = array('sales_invoices' => 'SalesInvoice-', 'shipment_documents' => 'ShipmentDocument-');
    $model = $models[(string) $type] ?? '';
    $errors = $read['body']['errors'] ?? null;

    if (($code !== 404) || ($model === '') || !is_array($errors)) {
        return 'unknown';
    }

    foreach ($errors as $error) {
        $title = is_string($error['title'] ?? null) ? trim($error['title']) : '';
        $detail = is_string($error['detail'] ?? null) ? trim($error['detail']) : '';

        if (($title === 'Record was not found') && (strpos($detail, $model) === 0)) {
            return 'gone';
        }
    }

    return 'unknown';
}

/**
 * Reads the Paraşüt record whose id an order keeps, before a press of the
 * order screen's buttons relies on it. A record deleted on Paraşüt's screen
 * leaves its id on the order and no panel screen clears it, so the press
 * asks: one Paraşüt no longer holds is left out by the caller, its id
 * cleared (_parasut_order_forget_record()) and the screen told ('notice');
 * one that is there goes on as before; a read that does not say refuses as
 * before. The unsure mark of an unanswered create is the caller's
 * (parasut_order_invoice_unsure()): it is not a record to read.
 *
 * @param string   $type  'sales_invoices' or 'shipment_documents'
 * @param string   $id    The stored id; '' reads nothing
 * @param callable $get   function (string $path): the _parasut_request() answer of
 *                        GET _parasut_prefix() . $path
 * @return array ['state' => 'none' (no id) | 'kept' | 'gone' | 'unknown',
 *                'notice' => string ('' unless gone), 'error' => string (Paraşüt's words when unknown)]
 */
function parasut_stored_record_check($type, $id, $get)
{
    $id = trim((string) $id);

    if ($id === '') {
        return array('state' => 'none', 'notice' => '', 'error' => '');
    }

    $read = (array) $get('/' . $type . '/' . rawurlencode($id));
    $state = parasut_stored_record_state($read, $type);

    return array(
        'state' => $state,
        'notice' => ($state === 'gone') ? parasut_stored_record_gone_notice($type, $id) : '',
        'error' => ($state === 'unknown') ? _parasut_extract_error($read) : '',
    );
}

/**
 * The order screen's notice for a record whose id the order kept and that
 * Paraşüt no longer holds, which the press left out.
 *
 * @param string $type  'sales_invoices' or 'shipment_documents'
 * @param string $id
 * @return string
 */
function parasut_stored_record_gone_notice($type, $id)
{
    if ($type === 'shipment_documents') {
        return lang(array(
            'string' => 'This order\'s earlier shipment document (ID: {var:1}) is no longer at Paraşüt, so it was left out.',
            'vars' => array((string) $id),
        ));
    }

    return lang(array(
        'string' => 'This order\'s earlier sales invoice (ID: {var:1}) is no longer at Paraşüt, so it was left out.',
        'vars' => array((string) $id),
    ));
}

/**
 * Paraşüt's words, or a token error, as they go inside a refusal sentence
 * before its own period ("...: {var:2}. ..."): one closing period and the
 * spaces around the text are dropped, so "No route matches." does not
 * print "..".
 *
 * @param string $text
 * @return string
 */
function parasut_refusal_words($text)
{
    $text = trim((string) $text);

    if (substr($text, -1) === '.') {
        $text = rtrim((string) substr($text, 0, -1));
    }

    return $text;
}

/**
 * parasut_stored_record_check() against Paraşüt with the stored token. No
 * token is no answer ('unknown').
 *
 * @param string $type  'sales_invoices' or 'shipment_documents'
 * @param string $id
 * @return array As parasut_stored_record_check()
 */
function _parasut_stored_record_check($type, $id)
{
    $token_result = parasut_get_token();

    if (empty($token_result['success'])) {
        return array('state' => 'unknown', 'notice' => '', 'error' => (string) ($token_result['error'] ?? ''));
    }

    $token = (string) $token_result['token'];

    return parasut_stored_record_check($type, $id, function ($path) use ($token) {
        return _parasut_request('GET', _parasut_prefix() . $path, null, $token);
    });
}

/**
 * Clears an order's Paraşüt id column for a record Paraşüt no longer holds,
 * only while the column still holds that id: a press running beside this
 * one may have written a new record's id meanwhile.
 *
 * @param int    $order_id  orders.id
 * @param string $column    'parasut_invoice_id' or 'parasut_shipment_id'
 * @param string $id        The id that was read
 * @return void
 */
function _parasut_order_forget_record($order_id, $column, $id)
{
    if (!in_array($column, array('parasut_invoice_id', 'parasut_shipment_id'), true)) {
        return;
    }

    db("UPDATE orders SET " . $column . " = '' WHERE id = '" . escape($order_id) . "' AND TRIM(" . $column . ") = '" . escape(trim((string) $id)) . "'");
}

/**
 * The record Paraşüt's answer names as one it does not hold: a 404 with an
 * error titled "Record was not found" whose detail is <Model>-<id>
 * (Product-1079203184, Warehouse-…, Contact-…, ShipmentDocument-…), the
 * reading parasut_stored_record_state() makes of a read. A create naming a
 * record deleted at Paraşüt, or one of another Paraşüt company, is answered
 * so and writes nothing. Every other answer names none: a 404 without the
 * record (a path Paraşüt has no route for: "No route matches."), the 401
 * "No access to company: <n>" of a company the token's user cannot reach,
 * a transport error whatever came with it, any other code.
 *
 * @param array|null $result  _parasut_request() or erp_edoc_parasut_request() answer
 * @return array|null ['type' => 'Product', 'id' => '1079203184']
 */
function parasut_missing_record($result)
{
    $result = (array) $result;

    foreach (array('curl_error', 'error', 'login_error') as $transport) {
        if (trim((string) ($result[$transport] ?? '')) !== '') {
            return null;
        }
    }

    $errors = $result['body']['errors'] ?? null;

    if (((int) ($result['http_code'] ?? 0) !== 404) || !is_array($errors)) {
        return null;
    }

    foreach ($errors as $error) {
        if (!is_array($error)) {
            continue;
        }

        $title = is_string($error['title'] ?? null) ? trim($error['title']) : '';
        $detail = is_string($error['detail'] ?? null) ? trim($error['detail']) : '';

        if (($title === 'Record was not found') && preg_match('/^([A-Za-z]+)-([0-9]+)$/', $detail, $named)) {
            return array('type' => $named[1], 'id' => $named[2]);
        }
    }

    return null;
}

/**
 * What a press does about the record its create was answered as not found
 * (parasut_missing_record()). 'forget' when it is an id the press took from
 * what Pinegrap keeps rather than asked Paraşüt for ($kept), on the first
 * answer only: the id is forgotten and the create sent once more. A record
 * deleted at Paraşüt, or a shop moved to another Paraşüt company, leaves
 * such ids behind and no panel screen clears them. 'default_product' when
 * it is the default product/service, a setting the operator typed, which is
 * never forgotten. '' for anything else: the press refuses as before.
 *
 * @param array|null $missing          parasut_missing_record()
 * @param array      $kept             'Product', 'Contact', 'Warehouse', 'ShipmentDocument' => the ids
 *                                     the press took from what is kept
 * @param string     $default_product  The default product/service id (PARASUT_DEFAULT_PRODUCT_ID)
 * @param bool       $resent           The answer is the resend's
 * @return string 'forget' | 'default_product' | ''
 */
function parasut_stale_kept_action($missing, $kept, $default_product, $resent)
{
    if (!is_array($missing) || !isset($missing['type'], $missing['id'])) {
        return '';
    }

    $type = (string) $missing['type'];
    $id = trim((string) $missing['id']);

    if (($type === 'Product') && ($id !== '') && ($id === trim((string) $default_product))) {
        return 'default_product';
    }

    if ($resent || ($id === '') || !isset($kept[$type])) {
        return '';
    }

    foreach ((array) $kept[$type] as $kept_id) {
        if (trim((string) $kept_id) === $id) {
            return 'forget';
        }
    }

    return '';
}

/**
 * A create at Paraşüt that is sent once more when Paraşüt answers that it
 * does not hold a record whose id the press took from what Pinegrap keeps
 * (parasut_stale_kept_action()). The id is forgotten ($forget readies the
 * create again: the id cleared, the card or the body made again), the
 * screen told (parasut_stale_kept_notice()), and the create sent once
 * more; what that try answers is the outcome, a second such 404 included.
 * Paraşüt writes nothing on that 404, so the record is not written twice.
 * When $forget cannot ready the create, nothing goes again: the notice
 * says so, and $forget's reason is the refusal. The resend starts PHP's
 * time limit afresh (erp_edoc_parasut_allow_time()): it repeats the try's
 * requests under Paraşüt's rate limit, and on Windows the limit counts
 * that wait. A create that names the default product/service refuses in
 * words naming the setting, which is not forgotten.
 *
 * @param callable $send             function (): array with 'answer' => the answer of the request
 *                                   that decides the try (null when none went) and 'kept' => what
 *                                   the try took from what is kept (parasut_stale_kept_action());
 *                                   the rest is the caller's
 * @param string   $default_product  The default product/service id
 * @param callable $forget           function (string $type, string $id): forgets the id and readies
 *                                   the create again; '' or why it cannot go again
 * @return array ['sent' => what the last $send() returned, 'notices' => array of sentences,
 *                'refusal' => string ('' unless the default product was named or $forget refused)]
 */
function parasut_send_once_more($send, $default_product, $forget)
{
    $notices = array();
    $sent = (array) call_user_func($send);

    for ($resent = false; ; $resent = true) {
        $missing = parasut_missing_record($sent['answer'] ?? null);
        $action = parasut_stale_kept_action($missing, (array) ($sent['kept'] ?? array()), $default_product, $resent);

        if ($action === 'default_product') {
            return array('sent' => $sent, 'notices' => $notices, 'refusal' => parasut_default_product_gone_refusal($missing['id']));
        }

        if ($action !== 'forget') {
            return array('sent' => $sent, 'notices' => $notices, 'refusal' => '');
        }

        $refusal = (string) call_user_func($forget, (string) $missing['type'], (string) $missing['id']);
        $notices[] = parasut_stale_kept_notice($missing['type'], $missing['id'], $refusal === '');

        if ($refusal !== '') {
            return array('sent' => $sent, 'notices' => $notices, 'refusal' => $refusal);
        }

        // The helper is the driver's; E-Shipment loads the driver only when
        // it looks a buyer card up.
        if (!function_exists('erp_edoc_parasut_allow_time')) {
            _parasut_order_invoice_load();
        }

        erp_edoc_parasut_allow_time();

        $sent = (array) call_user_func($send);
    }
}

/**
 * What the order screen and the ERP say about a kept id Paraşüt no longer
 * holds: that it was forgotten, and whether the create was tried once more
 * without it; not how that try went (the outcome says so). When the create
 * could not be readied again, the refusal beside the notice says why.
 *
 * @param string $type   'Product', 'Warehouse', 'Contact' or 'ShipmentDocument'
 * @param string $id
 * @param bool   $tried  The create was sent once more without the id
 * @return string  '' for another type
 */
function parasut_stale_kept_notice($type, $id, $tried = true)
{
    $vars = array((string) $id);

    if ($type === 'Product') {
        return $tried
            ? lang(array('string' => 'Paraşüt no longer holds product card {var:1}, which Pinegrap kept for a product: the id was forgotten and Pinegrap tried once more without it.', 'vars' => $vars))
            : lang(array('string' => 'Paraşüt no longer holds product card {var:1}, which Pinegrap kept for a product: the id was forgotten, but the document could not be sent once more without it.', 'vars' => $vars));
    }

    if ($type === 'Warehouse') {
        return $tried
            ? lang(array('string' => 'Paraşüt no longer holds warehouse {var:1}, the default warehouse kept in the Paraşüt settings: the id was forgotten and Pinegrap tried once more without it.', 'vars' => $vars))
            : lang(array('string' => 'Paraşüt no longer holds warehouse {var:1}, the default warehouse kept in the Paraşüt settings: the id was forgotten, but the document could not be sent once more without it.', 'vars' => $vars));
    }

    if ($type === 'Contact') {
        return $tried
            ? lang(array('string' => 'Paraşüt no longer holds buyer card {var:1}, which this order kept: the id was forgotten and Pinegrap tried once more without it.', 'vars' => $vars))
            : lang(array('string' => 'Paraşüt no longer holds buyer card {var:1}, which this order kept: the id was forgotten, but the document could not be sent once more without it.', 'vars' => $vars));
    }

    if ($type === 'ShipmentDocument') {
        return $tried
            ? lang(array('string' => 'Paraşüt no longer holds shipment document {var:1}, which E-Shipment on the order screen wrote for this order: the id was forgotten and Pinegrap tried once more without it, as an ordinary invoice of the order with the shipment included.', 'vars' => $vars))
            : lang(array('string' => 'Paraşüt no longer holds shipment document {var:1}, which E-Shipment on the order screen wrote for this order: the id was forgotten, but the invoice could not be sent once more without it.', 'vars' => $vars));
    }

    return '';
}

/**
 * The refusal of a create Paraşüt answered as not holding the default
 * product/service, which the operator typed on the settings card and
 * which is never cleared. An invoice names it for a line without a card of
 * its own; E-Shipment names it only through a product whose own card is
 * that id, which goes on a new card once the setting holds another id.
 *
 * @param string $id
 * @return string
 */
function parasut_default_product_gone_refusal($id)
{
    return lang(array(
        'string' => 'Paraşüt no longer holds the default product/service {var:1} entered on the Paraşüt card of the commerce settings, which invoice lines without a product card of their own go on. Enter there the id of a product or service Paraşüt holds; the document was not written.',
        'vars' => array((string) $id),
    ));
}

/**
 * The statement that forgets a kept id Paraşüt no longer holds, only where
 * it is still held: every product keeping that card (the next
 * parasut_sync_product() opens a new one), the default warehouse setting,
 * the order's buyer card. '' for another type, for an id that is not a
 * Paraşüt record id (digits, as parasut_missing_record() reads it) or, for
 * a buyer card, no order. An order's shipment document is cleared by
 * _parasut_order_forget_record().
 *
 * @param string $type      'Product', 'Warehouse' or 'Contact'
 * @param string $id
 * @param int    $order_id  orders.id (Contact)
 * @return string
 */
function parasut_forget_kept_id_sql($type, $id, $order_id = 0)
{
    $id = trim((string) $id);

    if (!preg_match('/^[0-9]+$/', $id)) {
        return '';
    }

    if ($type === 'Product') {
        return "UPDATE products SET parasut_product_id = '' WHERE TRIM(parasut_product_id) = '" . $id . "'";
    }

    if ($type === 'Warehouse') {
        return "UPDATE config SET parasut_default_warehouse_id = '' WHERE TRIM(parasut_default_warehouse_id) = '" . $id . "'";
    }

    if (($type === 'Contact') && ((int) $order_id > 0)) {
        return "UPDATE orders SET parasut_contact_id = '' WHERE id = '" . (int) $order_id . "' AND TRIM(parasut_contact_id) = '" . $id . "'";
    }

    return '';
}

/**
 * Forgets a kept id Paraşüt answered as not found
 * (parasut_forget_kept_id_sql(), _parasut_order_forget_record()).
 *
 * @param string $type      'Product', 'Warehouse', 'Contact' or 'ShipmentDocument'
 * @param string $id
 * @param int    $order_id  orders.id (Contact, ShipmentDocument)
 * @return void
 */
function _parasut_forget_kept_id($type, $id, $order_id = 0)
{
    if ($type === 'ShipmentDocument') {
        if ((int) $order_id > 0) {
            _parasut_order_forget_record($order_id, 'parasut_shipment_id', $id);
        }

        return;
    }

    $sql = parasut_forget_kept_id_sql($type, $id, $order_id);

    if ($sql !== '') {
        db($sql);
    }
}

/**
 * The Paraşüt product cards Pinegrap keeps for these products
 * (products.parasut_product_id), which parasut_sync_product() hands back
 * without asking Paraşüt. Read before a body is built: a card the build
 * opens is not a kept one.
 *
 * @param array $product_ids  products.id
 * @return array  Card ids
 */
function parasut_kept_product_cards($product_ids)
{
    $ids = array();

    foreach ((array) $product_ids as $product_id) {
        if ((int) $product_id > 0) {
            $ids[(int) $product_id] = (int) $product_id;
        }
    }

    if (empty($ids)) {
        return array();
    }

    $cards = array();

    foreach ((array) db_items("SELECT parasut_product_id FROM products WHERE id IN (" . implode(', ', $ids) . ")") as $row) {
        $card = trim((string) ($row['parasut_product_id'] ?? ''));

        if ($card !== '') {
            $cards[$card] = $card;
        }
    }

    return array_values($cards);
}

/**
 * Create a Paraşüt sales invoice for an order and turn it into its
 * e-document: an e-invoice for a buyer registered with GİB, an e-archive
 * invoice otherwise (_parasut_resolve_edoc_kind()).
 *
 * The lines are the ones the order's ERP invoice carries
 * (parasut_order_invoice_lines()), written the way the ERP's Paraşüt driver
 * writes them (erp_edoc_parasut_invoice_payload()). Paraşüt works the VAT
 * out itself, once per rate on the whole amount of its lines; where that is
 * not the order's VAT the rate's amount there moves by a kurus or so, so
 * that the invoice still comes to what the customer paid
 * (erp_edoc_parasut_vat_shift()). The e-document carries what the driver
 * gives the order's invoice too (parasut_order_edoc_attributes()): a
 * zero-rated line's exemption, an internet sale's address, payment and
 * carrier. Whatever can be refused from the order alone is refused before
 * anything is sent, and a sales invoice Paraşüt totals differently is
 * deleted before it becomes a document. After E-Shipment the invoice goes
 * without shipment, naming the shipment document
 * (parasut_order_invoice_with_shipment()). An invoice or shipment document
 * the order keeps the id of is read first (parasut_stored_record_check()):
 * one deleted on Paraşüt's screen since is left out and its id cleared. The
 * buyer card and product cards Pinegrap kept are sent without a read; one
 * Paraşüt answers the create it no longer holds is forgotten and the
 * invoice sent once more (parasut_send_once_more()).
 *
 * @param int   $order_id  orders.id
 * @param array $notices   Receives what the order screen shows beside the outcome:
 *                         each record the press left out or forgot because Paraşüt no longer holds it
 * @return array ['success' => bool, 'parasut_invoice_id' => string|null, 'edoc_kind' => string,
 *                'edoc_state' => string, 'error' => string]
 */
function parasut_create_invoice($order_id, &$notices = array())
{
    $notices = array();

    // Fetch full order.
    $query = "SELECT
                orders.*,
                contacts.company AS contact_company
              FROM orders
              LEFT JOIN contacts ON orders.contact_id = contacts.id
              WHERE orders.id = '" . escape($order_id) . "'
              LIMIT 1";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
    $order = mysqli_fetch_assoc($result);

    if (!$order) {
        return ['success' => false, 'parasut_invoice_id' => null, 'error' => lang('Order not found.')];
    }

    _parasut_order_invoice_load();

    // One sales invoice per order. The id is kept as soon as the sales invoice
    // exists, also when its e-document failed or is still being converted; a
    // second press would raise another sales invoice and, while the first
    // conversion runs, a second document for the same sale at GİB. Whether
    // that conversion finished cannot be told from here (its job id is not
    // kept), so the rest is finished on the record in Paraşüt. A create that
    // got no answer leaves the unsure mark instead of an id
    // (parasut_order_invoice_id_after_create()): that press goes on, and
    // looks at Paraşüt for the record before it writes one.
    $existing_invoice_id = trim((string) ($order['parasut_invoice_id'] ?? ''));
    $unsure = parasut_order_invoice_unsure($existing_invoice_id);

    // A kept id is read first: an invoice deleted on Paraşüt's screen since
    // leaves its id on the order, which no screen clears. One Paraşüt names
    // as not found is forgotten and the order invoiced as one never sent;
    // one still there refuses as before, and a read that does not say (a
    // 401 for a company number the token cannot reach among them) refuses
    // with Paraşüt's words.
    if (($existing_invoice_id !== '') && !$unsure) {
        $stored = _parasut_stored_record_check('sales_invoices', $existing_invoice_id);

        if ($stored['state'] === 'unknown') {
            return [
                'success' => false,
                'parasut_invoice_id' => $existing_invoice_id,
                'error' => lang(array(
                    'string' => 'This order already has a sales invoice in Paraşüt (ID: {var:1}), and Paraşüt could not be asked whether it is still there: {var:2}. A second one was not created; try again in a minute.',
                    'vars' => array($existing_invoice_id, parasut_refusal_words($stored['error'])),
                )),
            ];
        }

        if ($stored['state'] !== 'gone') {
            return [
                'success' => false,
                'parasut_invoice_id' => $existing_invoice_id,
                'error' => lang(array(
                    'string' => 'This order already has a sales invoice in Paraşüt (ID: {var:1}), so a second one was not created. If its e-document did not go through, issue it from that invoice in Paraşüt.',
                    'vars' => array($existing_invoice_id),
                )),
            ];
        }

        _parasut_order_forget_record($order_id, 'parasut_invoice_id', $existing_invoice_id);
        $notices[] = $stored['notice'];
        $order['parasut_invoice_id'] = '';
        $existing_invoice_id = '';
    }

    // The lines, read the way the ERP reads them for the order's invoice.
    $items = (array) db_items("SELECT order_items.*, products.short_description,
            products.tax_rate AS product_tax_rate, products.start AS product_start
        FROM order_items
        LEFT JOIN products ON order_items.product_id = products.id
        WHERE order_items.order_id = '" . (int) $order['id'] . "' AND order_items.saved_for_later = 0
        ORDER BY order_items.id ASC");

    if (empty($items)) {
        return ['success' => false, 'parasut_invoice_id' => null, 'error' => lang('This order has no items.')];
    }

    $built = parasut_order_invoice_lines($order, $items);

    if ($built['charged'] === 0) {
        return ['success' => false, 'parasut_invoice_id' => null, 'error' => lang('None of the lines of this order was charged when it was placed, so there is nothing to invoice yet.')];
    }

    // A line below zero first: its order's VAT can be one the checkout held
    // at zero, and the total check below would then name the totals, not
    // the line Paraşüt refuses.
    $refusal = erp_edoc_parasut_negative_line_refusal($built['lines']);

    if ($refusal !== '') {
        return ['success' => false, 'parasut_invoice_id' => null, 'error' => $refusal];
    }

    // The lines must come to what the customer was charged. Only rounding is
    // absorbed on the way (erp_order_lines()); a wider gap means the order's
    // own figures disagree, and a legal document is not the place to find out.
    if ($built['totals']['grand_total'] !== $built['expected']) {
        return ['success' => false, 'parasut_invoice_id' => null, 'error' => lang(array(
            'string' => 'The invoice total ({var:1}) does not match the order total ({var:2}). The invoice was not created.',
            'vars' => array(erp_money_out($built['totals']['grand_total']), erp_money_out($built['expected'])),
        ))];
    }

    // The order's amounts are in the store's base currency; orders.currency_code
    // is only the one the visitor was shown prices in.
    $currency = erp_base_currency();
    $refusal = parasut_order_invoice_refusal($built['lines'], $currency);

    if ($refusal !== '') {
        return ['success' => false, 'parasut_invoice_id' => null, 'error' => $refusal];
    }

    $token_result = parasut_get_token();
    if (!$token_result['success']) {
        return ['success' => false, 'parasut_invoice_id' => null, 'error' => $token_result['error']];
    }
    $token = $token_result['token'];

    // The shipment document E-Shipment left on the order is read before the
    // invoice names it: Paraşüt answers a sales invoice naming a deleted
    // document with 404 and writes nothing. One it no longer holds is left
    // out (the invoice goes as for an order never shipped) and its id
    // forgotten; a read that does not say refuses, nothing guessed.
    $shipment_check = parasut_stored_record_check('shipment_documents', (string) ($order['parasut_shipment_id'] ?? ''), function ($path) use ($token) {
        return _parasut_request('GET', _parasut_prefix() . $path, null, $token);
    });

    if ($shipment_check['state'] === 'gone') {
        _parasut_order_forget_record($order_id, 'parasut_shipment_id', trim((string) $order['parasut_shipment_id']));
        $notices[] = $shipment_check['notice'];
        $order['parasut_shipment_id'] = '';
    } elseif ($shipment_check['state'] === 'unknown') {
        return ['success' => false, 'parasut_invoice_id' => null, 'error' => lang(array(
            'string' => 'Paraşüt could not be asked whether this order\'s shipment document (ID: {var:1}) is still there: {var:2}. No invoice was created; try again in a minute.',
            'vars' => array(trim((string) $order['parasut_shipment_id']), parasut_refusal_words($shipment_check['error'])),
        ))];
    }

    // Which document this has to be is settled before anything is created: a
    // sales invoice raised here and then found to need the other kind cannot be
    // taken back.
    $kind_result = _parasut_resolve_edoc_kind(_parasut_order_identity($order), $token);
    if (!$kind_result['success']) {
        return ['success' => false, 'parasut_invoice_id' => null, 'error' => $kind_result['error']];
    }
    $edoc_kind = $kind_result['kind'];
    $edoc_alias = $kind_result['alias'];

    // orders.order_date is a UNIX timestamp; fall back to today when it is missing or zero.
    $ts = (int) ($order['order_date'] ?? 0);
    if ($ts <= 0) {
        $ts = time();
    }
    $issue_date = date('Y-m-d', $ts);
    $order_number = trim((string) ($order['order_number'] ?? ''));

    // What the e-document says besides the lines, as the order's ERP invoice
    // would carry it: each zero-rated line's exemption, an internet sale's
    // address, payment and carrier. Settled here too, so that an e-Archive
    // that cannot go leaves no buyer card or sales invoice at Paraşüt.
    foreach ($built['lines'] as $index => $line) {
        $built['lines'][$index]['vat_exemption_code'] = erp_vat_line_exemption_code($line);
    }
    $edoc = parasut_order_edoc_attributes(erp_order_sale_fields($order), $built['lines'], $edoc_kind, $edoc_alias, $issue_date);
    if ($edoc['error'] !== '') {
        return ['success' => false, 'parasut_invoice_id' => null, 'error' => $edoc['error']];
    }

    // The buyer card the order kept and the product cards its lines'
    // products kept are sent without asking Paraşüt. They are noted before
    // anything is built, so that a card the build opens is not taken for a
    // kept one (parasut_stale_kept_action()).
    $kept_contact = trim((string) ($order['parasut_contact_id'] ?? ''));
    $kept = array(
        'Contact' => ($kept_contact !== '') ? array($kept_contact) : array(),
        'Product' => parasut_kept_product_cards(array_column($built['lines'], 'product_id')),
    );

    // Sync (or retrieve) the Parasut contact.
    $contact_result = parasut_sync_contact($order);
    if (!$contact_result['success']) {
        return ['success' => false, 'parasut_invoice_id' => null, 'error' => $contact_result['error']];
    }
    $parasut_contact_id = $contact_result['parasut_contact_id'];

    // The body the ERP's driver writes for an invoice of the order: its lines
    // with their products, the kurus each rate moves, the order's number and
    // day. The description stays the one these documents always carried.
    // Built again, on the same buyer card or a new one, when the create is
    // sent once more. A product card the build opens starts from the stock
    // before the order when the invoice takes the order's goods out at
    // Paraşüt (parasut_order_stock_taken()).
    $build_invoice_body = function ($contact_id) use ($order, $built, $order_number, $issue_date, $currency) {
        $invoice_body = erp_edoc_parasut_invoice_payload(array(
            'order_id' => (int) $order['id'],
            'order_number' => $order_number,
            'order_date' => (((int) ($order['order_date'] ?? 0)) > 0) ? $issue_date : '',
            'full_number' => '',
            'issue_date' => $issue_date,
            'due_date' => $issue_date,
            'currency' => $currency,
            'exchange_rate' => 0,
            'notes' => '',
            'stock_taken' => parasut_order_stock_taken($order, $built['lines']),
        ), $built['lines'], $contact_id);

        if (isset($invoice_body['error'])) {
            return $invoice_body;
        }

        $invoice_body['data']['attributes']['description'] = lang('Order') . ' #' . $order_number;

        // An order E-Shipment already sent took its goods out of Paraşüt's stock
        // with the shipment document: the invoice goes without shipment and
        // names that document, so the goods are not counted out twice.
        $invoice_body = parasut_order_invoice_with_shipment($invoice_body, (string) ($order['parasut_shipment_id'] ?? ''));

        return $invoice_body;
    };
    $invoice_body = $build_invoice_body($parasut_contact_id);

    if (isset($invoice_body['error'])) {
        return ['success' => false, 'parasut_invoice_id' => null, 'error' => $invoice_body['error']];
    }

    $sales_invoice_id = '';
    $attributes = array();

    // After a create that got no answer the record may be at Paraşüt all the
    // same. It is looked for with the description, issue date and contact
    // this request carries, which are the unanswered one's (the order holds
    // its date and its contact). One found is the order's sales invoice and
    // goes on as if just created; none found, the invoice is written now; a
    // search that fails or finds several keeps the mark, and nothing is
    // written beside what may be there.
    if ($unsure) {
        $found = parasut_order_invoice_find($invoice_body, $parasut_contact_id, function ($path) use ($token) {
            return _parasut_request('GET', _parasut_prefix() . $path, null, $token);
        });

        if ($found['error'] !== '') {
            return ['success' => false, 'parasut_invoice_id' => null, 'error' => $found['error']];
        }

        if (count($found['records']) > 1) {
            return ['success' => false, 'parasut_invoice_id' => null, 'error' => lang(array(
                'string' => 'Paraşüt holds {var:1} records of this invoice from earlier tries (ids {var:2}). Delete all but one of them on Paraşüt\'s screen, then send again; the one left is taken over.',
                'vars' => array(count($found['records']), implode(', ', array_keys($found['records']))),
            ))];
        }

        foreach ($found['records'] as $found_id => $record) {
            $sales_invoice_id = (string) $found_id;
            $attributes = (array) $record['attributes'];

            db("UPDATE orders SET parasut_invoice_id = '" . escape($sales_invoice_id) . "' WHERE id = '" . escape($order_id) . "'");

            // Already an e-document (issued on Paraşüt's screen): the order
            // keeps the record and nothing is converted again.
            if ((string) $record['edoc_id'] !== '') {
                return ['success' => false, 'parasut_invoice_id' => $sales_invoice_id, 'error' => lang(array(
                    'string' => 'Paraşüt already holds this order\'s sales invoice (ID: {var:1}), issued there as an e-document. The order keeps it now, and nothing was sent again.',
                    'vars' => array($sales_invoice_id),
                ))];
            }
        }
    }

    if ($sales_invoice_id === '') {
        // Marked before the request goes: a request whose answer never comes
        // (a timeout, a dropped connection, a 5xx) may still have written the
        // record, and so may one whose process dies waiting. The order row
        // already holds the date and the buyer card the request carries, so
        // the mark is the bare one.
        $inv_result = array();
        $create = function () use ($order_id, $token, $kept, &$invoice_body, &$inv_result, &$sales_invoice_id) {
            db("UPDATE orders SET parasut_invoice_id = '" . escape(ERP_EDOC_PARASUT_UNSURE) . "' WHERE id = '" . escape($order_id) . "'");

            $inv_result = _parasut_request('POST', _parasut_prefix() . '/sales_invoices', $invoice_body, $token);
            $sales_invoice_id = parasut_order_invoice_id_after_create($inv_result);

            // A definite answer replaces the mark: the record's id, kept from
            // here on whatever the later steps do, or nothing after a refusal.
            if ($sales_invoice_id !== ERP_EDOC_PARASUT_UNSURE) {
                db("UPDATE orders SET parasut_invoice_id = '" . escape($sales_invoice_id) . "' WHERE id = '" . escape($order_id) . "'");
            }

            return array('answer' => $inv_result, 'kept' => $kept);
        };

        // A kept buyer card or product card that Paraşüt answers it no longer
        // holds (404, nothing written: deleted there, or the shop moved to
        // another Paraşüt company) is forgotten, the card made again and the
        // invoice sent once more. The default product/service is a setting:
        // it refuses in words that name it.
        $default_product = defined('PARASUT_DEFAULT_PRODUCT_ID') ? (string) PARASUT_DEFAULT_PRODUCT_ID : '';
        $sent = parasut_send_once_more($create, $default_product, function ($type, $id) use ($order_id, $build_invoice_body, &$order, &$parasut_contact_id, &$invoice_body) {
            _parasut_forget_kept_id($type, $id, $order_id);

            if ($type === 'Contact') {
                $order['parasut_contact_id'] = '';
                $contact_result = parasut_sync_contact($order);

                if (!$contact_result['success']) {
                    return (string) $contact_result['error'];
                }

                $parasut_contact_id = $contact_result['parasut_contact_id'];
            }

            $invoice_body = $build_invoice_body($parasut_contact_id);

            return isset($invoice_body['error']) ? (string) $invoice_body['error'] : '';
        });

        foreach ($sent['notices'] as $notice) {
            $notices[] = $notice;
        }

        if ($sent['refusal'] !== '') {
            return ['success' => false, 'parasut_invoice_id' => null, 'error' => $sent['refusal']];
        }

        if ($sales_invoice_id === '') {
            $msg = _parasut_extract_error($inv_result);
            return ['success' => false, 'parasut_invoice_id' => null, 'error' => lang(array('string' => 'Invoice create failed: {var:1}', 'vars' => array($msg)))];
        }

        if ($sales_invoice_id === ERP_EDOC_PARASUT_UNSURE) {
            return ['success' => false, 'parasut_invoice_id' => null, 'error' => lang(array(
                'string' => 'Paraşüt did not say whether it took the invoice ({var:1}). The next send looks for it at Paraşüt first, so it is not written twice.',
                'vars' => array(_parasut_extract_error($inv_result)),
            ))];
        }

        $attributes = (array) ($inv_result['body']['data']['attributes'] ?? array());
    }

    // What Paraşüt made of the lines against what the customer was charged.
    // A record that differs is deleted again before it becomes a document;
    // one that cannot be deleted stays on the order, so the button does not
    // raise a second record beside it.
    $check = erp_edoc_parasut_total_check($attributes, array('grand_total' => $built['expected']));

    if (!$check['ok']) {
        $deleted = _parasut_request('DELETE', _parasut_prefix() . '/sales_invoices/' . rawurlencode($sales_invoice_id), null, $token);
        $deleted_code = (int) $deleted['http_code'];
        $gone = (($deleted_code >= 200) && ($deleted_code < 300)) || ($deleted_code === 404);

        if ($gone) {
            db("UPDATE orders SET parasut_invoice_id = '' WHERE id = '" . escape($order_id) . "'");
            $message = lang(array(
                'string' => 'Paraşüt computed {var:1} for the invoice where the order comes to {var:2}; the record was deleted there and nothing was issued.',
                'vars' => array(erp_money_out_currency($check['total'], $currency), erp_money_out_currency($built['expected'], $currency)),
            ));
        } else {
            $message = lang(array(
                'string' => 'Paraşüt computed {var:1} for the invoice where the order comes to {var:2}, and the record could not be deleted there ({var:3}): it is still at Paraşüt as id {var:4}, not issued. Delete it on Paraşüt\'s screen.',
                'vars' => array(erp_money_out_currency($check['total'], $currency), erp_money_out_currency($built['expected'], $currency), _parasut_extract_error($deleted), $sales_invoice_id),
            ));
        }

        // How Paraşüt got there, so the gap can be traced to the VAT or to the
        // amounts before it once the record is gone.
        $totals = erp_edoc_parasut_totals($attributes);

        if ($totals !== array()) {
            $message .= ' ' . lang(array(
                'string' => 'Paraşüt has it at {var:1} before VAT, {var:2} VAT, {var:3} withheld, {var:4} in all.',
                'vars' => array(
                    erp_money_out_currency($totals['before_taxes'], $currency),
                    erp_money_out_currency($totals['vat'], $currency),
                    erp_money_out_currency($totals['vat_withholding'], $currency),
                    erp_money_out_currency($totals['net'], $currency),
                ),
            ));
        }

        return ['success' => false, 'parasut_invoice_id' => $gone ? null : $sales_invoice_id, 'error' => $message];
    }

    // Now issue the e-invoice or e-archive document. An e-invoice's attributes
    // carry the buyer's GİB mailbox from the registration lookup (one with
    // nowhere to go is rejected); the two requests name the sales invoice
    // under different relationship keys.
    if ($edoc_kind === 'einvoice') {
        $doc_body = [
            'data' => [
                'type' => 'e_invoices',
                'attributes' => $edoc['attributes'],
                'relationships' => [
                    'invoice' => ['data' => ['type' => 'sales_invoices', 'id' => $sales_invoice_id]],
                ],
            ],
        ];
        $doc_result = _parasut_request('POST', _parasut_prefix() . '/e_invoices', $doc_body, $token);
    } else {
        $doc_body = [
            'data' => [
                'type' => 'e_archives',
                'attributes' => $edoc['attributes'],
                'relationships' => [
                    'sales_invoice' => ['data' => ['type' => 'sales_invoices', 'id' => $sales_invoice_id]],
                ],
            ],
        ];
        $doc_result = _parasut_request('POST', _parasut_prefix() . '/e_archives', $doc_body, $token);
    }

    // Even if the e-invoice/e-archive step fails, the order keeps the sales
    // invoice ID (stored when the record was created or found).
    if (!in_array($doc_result['http_code'], [200, 201])) {
        $msg = _parasut_extract_error($doc_result);
        // The order is not flagged as exported: the flag drives the badge in
        // the order list, and marking it here would show a document as filed
        // that never reached the tax authority.
        return ['success' => false, 'parasut_invoice_id' => $sales_invoice_id, 'edoc_kind' => $edoc_kind, 'error' => lang(array('string' => 'Sales invoice created (ID: {var:1}) but e-document failed: {var:2}', 'vars' => array($sales_invoice_id, $msg)))];
    }

    // The POST was accepted, which means the conversion was queued. Find out
    // whether it actually ran before telling the operator the document is filed.
    $job_id = (string) ($doc_result['body']['data']['id'] ?? '');
    $job = _parasut_track_job($job_id, $token);

    if ($job['state'] === 'failed') {
        return [
            'success' => false,
            'parasut_invoice_id' => $sales_invoice_id,
            'edoc_kind' => $edoc_kind,
            'error' => lang(array('string' => 'Sales invoice created (ID: {var:1}) but the e-document was rejected: {var:2}', 'vars' => array($sales_invoice_id, $job['error']))),
        ];
    }

    // Only a conversion that finished counts as exported.
    if ($job['state'] === 'done') {
        db("UPDATE orders SET parasut_exported = 1 WHERE id = '" . escape($order_id) . "'");
    }

    return [
        'success' => true,
        'parasut_invoice_id' => $sales_invoice_id,
        'edoc_kind' => $edoc_kind,
        'edoc_state' => $job['state'],
        'error' => '',
    ];
}

/**
 * Create a Parasut shipment document (e-irsaliye) for the given order.
 *
 * After Create Invoice it goes only for a sales invoice issued without
 * shipment, which the document names; one that carries the shipment, the
 * unsure mark or an invoice that cannot be read refuses it before anything
 * is written (parasut_shipment_invoice_link()). A shipment document or
 * invoice the order keeps the id of and that was deleted on Paraşüt's
 * screen since is left out and its id cleared (parasut_stored_record_check()).
 * The buyer card, default warehouse and product cards Pinegrap kept are
 * sent without a read; one Paraşüt answers it no longer holds is forgotten
 * and the try made once more (parasut_send_once_more()).
 *
 * @param int   $order_id  orders.id
 * @param array $notices   Receives what the order screen shows beside the outcome:
 *                         each record the press left out or forgot because Paraşüt no longer holds it
 * @return array ['success' => bool, 'parasut_shipment_id' => string|null, 'error' => string]
 */
function parasut_create_shipment($order_id, &$notices = array())
{
    $notices = array();

    // Fetch full order.
    $query = "SELECT orders.*
              FROM orders
              WHERE orders.id = '" . escape($order_id) . "'
              LIMIT 1";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
    $order = mysqli_fetch_assoc($result);

    if (!$order) {
        return ['success' => false, 'parasut_shipment_id' => null, 'error' => lang('Order not found.')];
    }

    // One outgoing shipment document per order, refused before anything is
    // written. Every create takes the stock-tracked lines out of Paraşüt's
    // stock again and shipment_documents has no idempotency key, so a second
    // press would leave a second waybill for the same sale and overwrite the
    // stored id of the first. The kept id is read first: a document deleted
    // on Paraşüt's screen since leaves its id on the order, which no screen
    // clears. One Paraşüt names as not found is forgotten and the order
    // shipped as one never shipped; one still there refuses as before, and a
    // read that does not say (a 401 for a company number the token cannot
    // reach among them) refuses with Paraşüt's words.
    $existing_shipment_id = trim((string) ($order['parasut_shipment_id'] ?? ''));

    if ($existing_shipment_id !== '') {
        $stored = _parasut_stored_record_check('shipment_documents', $existing_shipment_id);

        if ($stored['state'] === 'unknown') {
            return [
                'success' => false,
                'parasut_shipment_id' => $existing_shipment_id,
                'error' => lang(array(
                    'string' => 'This order already has a shipment document in Paraşüt (ID: {var:1}), and Paraşüt could not be asked whether it is still there: {var:2}. A second one was not created; try again in a minute.',
                    'vars' => array($existing_shipment_id, parasut_refusal_words($stored['error'])),
                )),
            ];
        }

        if ($stored['state'] !== 'gone') {
            return [
                'success' => false,
                'parasut_shipment_id' => $existing_shipment_id,
                'error' => lang(array(
                    'string' => 'This order already has a shipment document in Paraşüt (ID: {var:1}), so a second one was not created.',
                    'vars' => array($existing_shipment_id),
                )),
            ];
        }

        _parasut_order_forget_record($order_id, 'parasut_shipment_id', $existing_shipment_id);
        $notices[] = $stored['notice'];
        $order['parasut_shipment_id'] = '';

        // The order screen asks for the buyer's VKN/TCKN only on an order
        // with no shipment id, so this press skipped it there.
        $identity_refusal = _parasut_shipment_identity_refusal(
            defined('PARASUT_TC_IN_FIELD') ? PARASUT_TC_IN_FIELD : 'do not use',
            $order,
            (string) db_value("SELECT tax_number FROM contacts WHERE id = '" . escape((int) ($order['contact_id'] ?? 0)) . "'")
        );

        if ($identity_refusal !== '') {
            return ['success' => false, 'parasut_shipment_id' => null, 'error' => $identity_refusal];
        }
    }

    // A line whose product was deleted from the catalogue since the order is
    // refused here: read as an untracked line, it was left off the waybill
    // without a word. Only the guard above may have been to Paraşüt by now
    // (it reads a kept shipment document and forgets one no longer there);
    // E-Shipment's own token and every other request come after this.
    $deleted_refusal = parasut_shipment_deleted_products_refusal(db_items("SELECT
            order_items.product_id,
            order_items.product_name,
            products.id AS product_row_id
        FROM order_items
        LEFT JOIN products ON order_items.product_id = products.id
        WHERE order_items.order_id = '" . escape($order_id) . "'
          AND order_items.saved_for_later = 0"));

    if ($deleted_refusal !== '') {
        return ['success' => false, 'parasut_shipment_id' => null, 'error' => $deleted_refusal];
    }

    $token_result = parasut_get_token();
    if (!$token_result['success']) {
        return ['success' => false, 'parasut_shipment_id' => null, 'error' => $token_result['error']];
    }
    $token = $token_result['token'];

    // The order's sales invoice from Create Invoice, judged before anything
    // is written: one that carries the shipment already took the goods out
    // of stock, one issued without shipment is named by the document.
    $invoice_link = parasut_shipment_invoice_link((string) ($order['parasut_invoice_id'] ?? ''), function ($path) use ($token) {
        return _parasut_request('GET', _parasut_prefix() . $path, null, $token);
    });

    if ($invoice_link['error'] !== '') {
        return ['success' => false, 'parasut_shipment_id' => null, 'error' => $invoice_link['error']];
    }

    // An invoice deleted on Paraşüt's screen since: the document goes
    // unlinked, as for an order never invoiced, and the order forgets it.
    if ((string) ($invoice_link['gone'] ?? '') !== '') {
        _parasut_order_forget_record($order_id, 'parasut_invoice_id', $invoice_link['gone']);
        $notices[] = parasut_stored_record_gone_notice('sales_invoices', $invoice_link['gone']);
    }

    // The buyer card, the warehouse, the product cards and the document. An
    // id Pinegrap kept for the order's buyer card, the default warehouse or
    // a product's card is sent without asking Paraşüt. When Paraşüt answers
    // that it no longer holds one (404, nothing written: deleted there, or
    // the shop moved to another Paraşüt company), the id is forgotten and
    // the try made once more (parasut_send_once_more()): the card opened
    // again, the first warehouse fetched again. The stock step's product
    // read and inflow seed count too: they come before the shipment
    // document. An inflow an earlier line seeded in the first try is not
    // seeded again: that product now has stock movements, and
    // parasut_ensure_product_stock_tracking() returns before seeding.
    $skip_warehouse = '';
    $send = function () use ($order_id, $token, $invoice_link, &$order, &$skip_warehouse) {
        // What this try takes from what Pinegrap keeps rather than asks
        // Paraşüt for (parasut_stale_kept_action()).
        $kept = array('Contact' => array(), 'Warehouse' => array(), 'Product' => array());

        if (trim((string) ($order['parasut_contact_id'] ?? '')) !== '') {
            $kept['Contact'][] = trim((string) $order['parasut_contact_id']);
        }

        // Sync contact.
        $contact_result = parasut_sync_contact($order);
        if (!$contact_result['success']) {
            return ['success' => false, 'parasut_shipment_id' => null, 'error' => $contact_result['error']];
        }
        $parasut_contact_id = $contact_result['parasut_contact_id'];

        // A card opened here is the order's from now on (parasut_sync_contact()
        // stored it): a try made once more goes on it rather than opening a
        // second one.
        $order['parasut_contact_id'] = $parasut_contact_id;

        // Fetch order items (join products to get display name and Parasut product ID).
        $items_query = "SELECT
                            order_items.product_id,
                            order_items.quantity,
                            order_items.product_name,
                            products.short_description,
                            products.parasut_product_id,
                            products.inventory
                        FROM order_items
                        LEFT JOIN products ON order_items.product_id = products.id
                        WHERE order_items.order_id = '" . escape($order_id) . "'
                          AND order_items.saved_for_later = 0";
        $items_result = mysqli_query(db::$con, $items_query) or output_error('Query failed.');

        $default_parasut_product_id = defined('PARASUT_DEFAULT_PRODUCT_ID') ? PARASUT_DEFAULT_PRODUCT_ID : '';

        // Warehouse is required on every stock movement AND to seed stock for newly
        // tracked products — fetch it once up front.
        $warehouse_result = parasut_ensure_default_warehouse($token, $skip_warehouse);
        $default_warehouse_id = $warehouse_result['success'] ? $warehouse_result['warehouse_id'] : '';
        if (!$default_warehouse_id) {
            return ['success' => false, 'parasut_shipment_id' => null, 'error' => lang(array('string' => 'No Parasut warehouse available: {var:1}', 'vars' => array(($warehouse_result['error'] ?? ''))))];
        }

        if (!empty($warehouse_result['kept'])) {
            $kept['Warehouse'][] = (string) $default_warehouse_id;
        }

        $items = array();
        while ($item = mysqli_fetch_assoc($items_result)) {
            $items[] = $item;
        }

        // The order took its stock-tracked lines out of Pinegrap's count when
        // it was placed, and the document takes them out at Paraşüt: a card
        // opened here starts from the stock before the order, the count plus
        // the order's whole quantity of the product (parasut_product_attributes()).
        $taken = parasut_order_took_stock($order) ? parasut_stock_taken_units($items) : array();

        $stock_lines = array();
        $skipped_count = 0;
        foreach ($items as $item) {
            // Skip non-shippable items — Parasut shipment_documents can only contain
            // stock-tracked (inventory_tracking) products. Non-inventory items
            // (services, digital goods, etc.) are silently excluded from the e-irsaliye
            // (the e-waybill). A line of a deleted product was refused above.
            if (empty($item['inventory'])) {
                $skipped_count++;
                continue;
            }

            $item_name = $item['short_description'] ?: ($item['product_name'] ?: lang('No Product Name'));
            $item_qty = max(1, (int) $item['quantity']);

            $item_parasut_product_id = (string) $item['parasut_product_id'];
            $sync_error = '';
            if ($item_parasut_product_id !== '') {
                $kept['Product'][] = $item_parasut_product_id;
            } elseif ($item['product_id']) {
                $sync = parasut_sync_product($item['product_id'], $token, $taken[(int) $item['product_id']] ?? 0);
                $item_parasut_product_id = $sync['success'] ? (string) $sync['parasut_product_id'] : '';
                $sync_error = (string) $sync['error'];
            }

            // A stock movement names its product, so a line Paraşüt has no product
            // for cannot go on the document.
            if ($item_parasut_product_id === '') {
                return [
                    'success' => false,
                    'parasut_shipment_id' => null,
                    'error' => lang(array('string' => 'Stock preparation failed for {var:1}: {var:2}', 'vars' => array($item_name, $sync_error))),
                ];
            }

            $stock_lines[] = array('parasut_product_id' => $item_parasut_product_id, 'quantity' => $item_qty, 'name' => $item_name);
        }

        // Guarantee each Parasut product has inventory_tracking=true AND enough
        // stock for the whole quantity the document takes of it, the lines of
        // one product added up. If stock is short, seed it via an inflow
        // shipment_document (the documented Parasut way to add stock to a
        // product via API). Every line's card is open by now, so a failure
        // here leaves no seed written ahead of a card that could not open.
        foreach (parasut_shipment_stock_needs($stock_lines) as $need) {
            $ensure = parasut_ensure_product_stock_tracking(
                $need['parasut_product_id'],
                $need['quantity'],
                $parasut_contact_id,
                $default_warehouse_id,
                $token
            );
            if (!$ensure['success']) {
                return [
                    'success' => false,
                    'parasut_shipment_id' => null,
                    'error' => lang(array('string' => 'Stock preparation failed for {var:1}: {var:2}', 'vars' => array($need['name'], $ensure['error']))),
                    'answer' => $ensure['answer'] ?? null,
                    'kept' => $kept,
                ];
            }
        }

        // One stock movement per order line; the step above made each
        // product ready for the sum of its lines.
        $stock_movements = [];
        foreach ($stock_lines as $stock_line) {
            $stock_movements[] = parasut_shipment_stock_movement($stock_line['parasut_product_id'], $default_warehouse_id, $stock_line['quantity']);
        }

        if (empty($stock_movements)) {
            return [
                'success' => false,
                'parasut_shipment_id' => null,
                'error' => lang('This order has no shippable (stock-tracked) items. The e-waybill cannot be created.'),
            ];
        }

        // orders.order_date is a UNIX timestamp; fall back to today when it is missing or zero.
        $ts = (int) ($order['order_date'] ?? 0);
        if ($ts <= 0) {
            $ts = time();
        }
        $issue_date = date('Y-m-d', $ts);

        $body = [
            'data' => [
                'type' => 'shipment_documents',
                'attributes' => [
                    'description' => lang('Order') . ' #' . $order['order_number'],
                    'issue_date' => $issue_date,
                    // inflow: false = outgoing (giden irsaliye, sale/shipment to customer)
                    //         true  = incoming (gelen irsaliye, purchase from supplier)
                    'inflow' => false,
                ],
                'relationships' => [
                    'contact' => [
                        'data' => ['type' => 'contacts', 'id' => $parasut_contact_id],
                    ],
                    'stock_movements' => ['data' => $stock_movements],
                ],
            ],
        ];
        $body = parasut_shipment_with_invoice($body, $invoice_link['invoice_id']);

        $result = _parasut_request('POST', _parasut_prefix() . '/shipment_documents', $body, $token);

        // One request, one outcome. A failed create is reported as it stands and is
        // never retried here with a reshaped body: shipment_documents has no
        // idempotency key, so a retry that succeeds leaves a second stock document
        // in Parasut that this code cannot see and the operator has to delete by hand.
        // The one exception is a 404 naming a kept id as not found, which wrote
        // nothing: the answer goes back, and the try is made once more without it.
        if (!in_array($result['http_code'], [200, 201]) || empty($result['body']['data']['id'])) {
            $msg = _parasut_extract_error($result);
            return [
                'success' => false,
                'parasut_shipment_id' => null,
                'error' => lang(array('string' => 'Shipment create failed: {var:1}', 'vars' => array($msg))),
                'answer' => $result,
                'kept' => $kept,
            ];
        }

        $shipment_id = (string) $result['body']['data']['id'];

        db("UPDATE orders SET parasut_shipment_id = '" . escape($shipment_id) . "' WHERE id = '" . escape($order_id) . "'");

        return ['success' => true, 'parasut_shipment_id' => $shipment_id, 'error' => ''];
    };

    // The default product/service is a setting, never forgotten: a product
    // card that is that id refuses in words naming it, as in Create Invoice
    // and the ERP driver. No shipment line goes on the default; once the
    // setting holds another id, that product's card is forgotten and a new
    // one opened like any other kept card.
    $default_product = defined('PARASUT_DEFAULT_PRODUCT_ID') ? (string) PARASUT_DEFAULT_PRODUCT_ID : '';
    $sent = parasut_send_once_more($send, $default_product, function ($type, $id) use ($order_id, &$order, &$skip_warehouse) {
        _parasut_forget_kept_id($type, $id, $order_id);

        if ($type === 'Contact') {
            $order['parasut_contact_id'] = '';
        } elseif ($type === 'Warehouse') {
            $skip_warehouse = $id;
        }

        return '';
    });

    foreach ($sent['notices'] as $notice) {
        $notices[] = $notice;
    }

    if ($sent['refusal'] !== '') {
        return ['success' => false, 'parasut_shipment_id' => null, 'error' => $sent['refusal']];
    }

    return [
        'success' => !empty($sent['sent']['success']),
        'parasut_shipment_id' => $sent['sent']['parasut_shipment_id'] ?? null,
        'error' => (string) ($sent['sent']['error'] ?? ''),
    ];
}

/**
 * Check if a VKN/TCKN is registered for e-invoice in the GİB system.
 *
 * @param string $vkn  Tax/identity number to look up
 * @return array ['success' => bool, 'data' => array|null, 'error' => string]
 */
function parasut_check_einvoice_address($vkn)
{
    $token_result = parasut_get_token();
    if (!$token_result['success']) {
        return ['success' => false, 'data' => null, 'error' => $token_result['error']];
    }
    $token = $token_result['token'];

    $url = _parasut_prefix() . '/e_invoice_inboxes?filter[vkn]=' . urlencode($vkn);
    $result = _parasut_request('GET', $url, null, $token);

    if ($result['http_code'] !== 200) {
        return ['success' => false, 'data' => null, 'error' => _parasut_extract_error($result)];
    }

    $items = $result['body']['data'] ?? [];
    return ['success' => true, 'data' => $items, 'error' => ''];
}

/**
 * The query of a list read of the e-invoice inbox (purchase_bills,
 * sales_invoices): one page, newest first, the related contact included,
 * and the filters given.
 *
 * Paraşüt's range operators are eq, lt, gt, gteq, lteq and not_eq, so the
 * date range goes as filter[issue_date][gteq] and [lteq]; gte and lte are
 * answered 400 "'gte' is not a valid operator". A date that is not a
 * calendar day written Y-m-d is left out, as Paraşüt answers 400
 * "'issue_date' is not a date" for anything else.
 *
 * @param array  $filters         start_date, end_date (Y-m-d), contact_id, item_type
 * @param int    $page
 * @param int    $per_page
 * @param string $include         The related record listed with each item ('supplier', 'contact')
 * @param string $contact_filter  The filter that names the contact ('supplier_id', 'contact_id')
 * @return array  Query parameters for http_build_query()
 */
function parasut_list_params($filters, $page, $per_page, $include, $contact_filter)
{
    $params = [
        'page[number]' => $page,
        'page[size]' => $per_page,
        'sort' => '-issue_date',
        'include' => $include,
    ];

    foreach (['start_date' => 'gteq', 'end_date' => 'lteq'] as $key => $operator) {
        $date = is_string($filters[$key] ?? null) ? trim($filters[$key]) : '';

        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $parts) && checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1])) {
            $params['filter[issue_date][' . $operator . ']'] = $date;
        }
    }

    if (!empty($filters['contact_id'])) {
        $params['filter[' . $contact_filter . ']'] = $filters['contact_id'];
    }
    if (!empty($filters['item_type'])) {
        $params['filter[item_type]'] = $filters['item_type'];
    }

    return $params;
}

/**
 * List purchase bills (incoming invoices) from Parasut.
 *
 * @param array $filters  Optional: ['start_date' => 'Y-m-d', 'end_date' => 'Y-m-d', 'contact_id' => '...', 'item_type' => '...']
 * @param int   $page
 * @param int   $per_page
 * @return array ['success' => bool, 'data' => array, 'included' => array, 'meta' => array, 'error' => string]
 */
function parasut_list_purchase_bills($filters = [], $page = 1, $per_page = 25)
{
    $token_result = parasut_get_token();
    if (!$token_result['success']) {
        return ['success' => false, 'data' => [], 'included' => [], 'meta' => [], 'error' => $token_result['error']];
    }
    $token = $token_result['token'];

    $params = parasut_list_params($filters, $page, $per_page, 'supplier', 'supplier_id');

    $url = _parasut_prefix() . '/purchase_bills?' . http_build_query($params);
    $result = _parasut_request('GET', $url, null, $token);

    if ($result['http_code'] !== 200) {
        return ['success' => false, 'data' => [], 'included' => [], 'meta' => [], 'error' => _parasut_extract_error($result)];
    }

    // Build a lookup map for included contacts (id => name).
    $included_contacts = [];
    foreach (($result['body']['included'] ?? []) as $inc) {
        if ($inc['type'] === 'contacts') {
            $included_contacts[$inc['id']] = $inc['attributes']['name'] ?? '';
        }
    }

    return [
        'success' => true,
        'data' => $result['body']['data'] ?? [],
        'contacts' => $included_contacts,
        'meta' => $result['body']['meta'] ?? [],
        'error' => '',
    ];
}

/**
 * List sales invoices from Parasut.
 *
 * @param array $filters  Optional: ['start_date', 'end_date', 'contact_id', 'item_type']
 * @param int   $page
 * @param int   $per_page
 * @return array ['success' => bool, 'data' => array, 'contacts' => array, 'meta' => array, 'error' => string]
 */
function parasut_list_sales_invoices($filters = [], $page = 1, $per_page = 25)
{
    $token_result = parasut_get_token();
    if (!$token_result['success']) {
        return ['success' => false, 'data' => [], 'contacts' => [], 'meta' => [], 'error' => $token_result['error']];
    }
    $token = $token_result['token'];

    $params = parasut_list_params($filters, $page, $per_page, 'contact', 'contact_id');

    $url = _parasut_prefix() . '/sales_invoices?' . http_build_query($params);
    $result = _parasut_request('GET', $url, null, $token);

    if ($result['http_code'] !== 200) {
        return ['success' => false, 'data' => [], 'contacts' => [], 'meta' => [], 'error' => _parasut_extract_error($result)];
    }

    $contacts = [];
    foreach (($result['body']['included'] ?? []) as $inc) {
        if ($inc['type'] === 'contacts') {
            $contacts[$inc['id']] = $inc['attributes']['name'] ?? '';
        }
    }

    return [
        'success' => true,
        'data' => $result['body']['data'] ?? [],
        'contacts' => $contacts,
        'meta' => $result['body']['meta'] ?? [],
        'error' => '',
    ];
}
