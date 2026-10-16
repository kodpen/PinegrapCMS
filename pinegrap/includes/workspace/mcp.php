<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - other services' MCP servers, each under an alias.
 *
 * An administrator adds an MCP server - a code host's, a documentation
 * site's, another Pinegrap site's - under an alias of its own (@github,
 * @docs). The site speaks to it as a client (includes/mcp/client.php), with
 * one connection for the whole site: the credential the administrator
 * entered. The server's tools are listed on the settings screen and each one
 * is off until the administrator turns it on.
 *
 * Because the connection is shared, whoever writes to the alias acts with the
 * administrator's account on the other service. So on a shared connection a
 * tool that may change something there is never turned on: only a tool the
 * server says reads only, or one that the administrator declares reads only
 * when the server says nothing either way. A tool the server marks as
 * destructive stays off. Tools that write are for servers people sign in to
 * themselves, below.
 *
 * The credential is kept encrypted with the site's key and is never shown
 * again; the screen offers an empty field that keeps it when left empty.
 *
 * A server that signs in with OAuth (auth_kind 'oauth') has no credential
 * here: the site is a client of the service's authorization server, found by
 * discovery when the server is saved (pg_mcp_client_discover_auth()), and an
 * administrator connects the site's account with the card's Connect button.
 * The tokens are a connected account (includes/fn/connect.php) under the
 * provider key mcp_<server id>, which ws_mcp_connect_provider() builds from
 * what discovery found; every request carries the account's access token,
 * renewed there.
 *
 * On such a server each person may also connect an account of their own
 * (owner 'person'). A tool that reads is called with the asker's own account
 * when they have one, else with the site's. A tool that writes can be turned
 * on there too, as one that asks first ('approve'): calling it runs nothing,
 * the call waits under the answer as a proposal (mcp_ask.php) and runs only
 * when the person who asked applies it, with their own account and never
 * with the site's, so nobody acts on the other service in somebody else's
 * name.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/mcp/client.php');

/**
 * The most servers a site may add.
 */
define('WS_MCP_MAX_SERVERS', 20);

/**
 * How long one request to a server may take on the settings screen, in
 * seconds.
 */
define('WS_MCP_SCREEN_TIMEOUT', 20);

/**
 * How long an access token is taken to last when the service's answer does
 * not say (a GitHub token does not end), in seconds. The server's 401 says
 * when it stopped working.
 */
define('WS_MCP_TOKEN_TTL', 157680000);

// The sign-in of a server that uses OAuth is a connected account whose
// provider is built from the server (includes/fn/connect.php).
if (function_exists('pg_connect_provider_builders')) {
    pg_connect_provider_builders('mcp_', 'ws_mcp_connect_provider');
}

/**
 * Has the schema step that brought MCP servers in (2026.4.10, 10.82) run?
 *
 * @return bool
 */
function ws_mcp_ready()
{
    static $ready = null;

    if ($ready === null) {
        $ready = function_exists('waf_table_has_column')
            && waf_table_has_column('ws_mcp_servers', 'tool_rules')
            && waf_table_has_column('ws_mcp_calls', 'status')
            && waf_table_has_column('ws_ai_requests', 'mcp_server_id');
    }

    return $ready;
}

/**
 * The ways a server can be told who the site is.
 *
 * @return array kind => label
 */
function ws_mcp_auth_kinds()
{
    return array(
        'none'   => lang('No credential'),
        'bearer' => lang('Access token (Bearer)'),
        'header' => lang('A header of the service\'s own'),
        'basic'  => lang('User name and password'),
        'oauth'  => lang('Sign in to the service (OAuth)'),
    );
}

/**
 * Aliases nobody's server may take: the assistants' own and the words that
 * mean everybody.
 *
 * @return string[]
 */
function ws_mcp_reserved_aliases()
{
    return array('ai', 'claude', 'pinegrap', 'mcp', 'all', 'everyone', 'here', 'channel', 'herkes', 'kanal', 'burada', 'system', 'admin');
}

/**
 * An alias as it is kept: lower case, without the @ it is written with.
 *
 * @param string $alias
 * @return string
 */
function ws_mcp_alias_clean($alias)
{
    return strtolower(ltrim(trim((string) $alias), '@'));
}

/**
 * What is wrong with an alias, by its shape alone: '' when nothing is. It
 * starts with a letter, holds letters, digits, - and _, and is 2 to 32
 * characters long, so it reads the same in every language and in an
 * address.
 *
 * @param string $alias as ws_mcp_alias_clean() keeps it
 * @return string
 */
function ws_mcp_alias_shape_problem($alias)
{
    if (!preg_match('/^[a-z][a-z0-9_-]{1,31}$/', (string) $alias)) {
        return lang('An alias starts with a letter and holds 2 to 32 letters, digits, - or _ (no spaces, no accented letters).');
    }

    if (in_array($alias, ws_mcp_reserved_aliases(), true)) {
        return lang('That alias is kept for the Workspace itself. Choose another.');
    }

    return '';
}

/**
 * What is wrong with an alias, its shape and whether it is free: '' when
 * nothing is. A person's user name is not free either, or "@ali" would mean
 * two things.
 *
 * @param string $alias
 * @param int    $server_id the server it is for (0 for a new one)
 * @return string
 */
function ws_mcp_alias_problem($alias, $server_id = 0)
{
    $problem = ws_mcp_alias_shape_problem($alias);

    if ($problem !== '') {
        return $problem;
    }

    if ((int) db_value("SELECT COUNT(*) FROM ws_mcp_servers WHERE alias = '" . e($alias) . "' AND id <> '" . (int) $server_id . "'") > 0) {
        return lang('Another MCP server already has that alias.');
    }

    if ((int) db_value("SELECT COUNT(*) FROM user WHERE LOWER(user_username) = '" . e($alias) . "'") > 0) {
        return lang('Somebody\'s user name is the same as that alias. Choose another, so that @ means one thing.');
    }

    return '';
}

/**
 * What is wrong with a header name for a credential: '' when nothing is.
 * The headers the transport sets itself cannot be replaced by one.
 *
 * @param string $name
 * @return string
 */
function ws_mcp_header_name_problem($name)
{
    if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9-]{0,63}$/', (string) $name)) {
        return lang('A header name holds only letters, digits and -.');
    }

    $lower = strtolower($name);

    if (in_array($lower, array('host', 'content-type', 'content-length', 'accept', 'connection', 'transfer-encoding', 'cookie', 'user-agent'), true)
        || (strpos($lower, 'mcp-') === 0)) {
        return lang('That header is set by the connection itself and cannot carry the credential.');
    }

    return '';
}

/**
 * A credential as it is stored: JSON encrypted with the site's key.
 *
 * @param array $credential
 * @return string "<ciphertext>:<iv>", '' for none
 */
function ws_mcp_secret_encode($credential)
{
    if (empty($credential)) {
        return '';
    }

    list($cipher, $iv) = encrypt_string_with_iv((string) json_encode($credential, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

    return $cipher . ':' . $iv;
}

/**
 * A stored credential, read back.
 *
 * @param string $stored
 * @return array empty when there is none or it cannot be read (the site's
 *               key changed)
 */
function ws_mcp_secret_decode($stored)
{
    $stored = (string) $stored;

    if (($stored === '') || (strpos($stored, ':') === false)) {
        return array();
    }

    list($cipher, $iv) = explode(':', $stored, 2);

    $values = json_decode(decode_ssl_keys($cipher, $iv), true);

    return is_array($values) ? $values : array();
}

/**
 * The headers that carry a server's credential.
 *
 * @param string $kind   ws_mcp_servers.auth_kind
 * @param array  $secret ws_mcp_secret_decode()
 * @return string[]
 */
function ws_mcp_auth_headers($kind, $secret)
{
    switch ($kind) {
        case 'bearer':
            return (($secret['token'] ?? '') !== '') ? array('Authorization: Bearer ' . $secret['token']) : array();

        case 'header':
            return ((($secret['name'] ?? '') !== '') && (($secret['value'] ?? '') !== '')) ? array($secret['name'] . ': ' . $secret['value']) : array();

        case 'basic':
            return (($secret['user'] ?? '') !== '') ? array('Authorization: Basic ' . base64_encode($secret['user'] . ':' . ($secret['password'] ?? ''))) : array();
    }

    return array();
}

/**
 * Every server, by id, the turned-off ones included.
 *
 * @param bool $fresh read them again
 * @return array[]
 */
function ws_mcp_servers($fresh = false)
{
    static $servers = null;

    if (!ws_mcp_ready()) {
        return array();
    }

    if (($servers === null) || $fresh) {
        $servers = array();

        foreach ((array) db_items("SELECT * FROM ws_mcp_servers ORDER BY alias") as $row) {
            $servers[(int) $row['id']] = $row;
        }
    }

    return $servers;
}

/**
 * One server.
 *
 * @param int $id
 * @return array|null
 */
function ws_mcp_server($id)
{
    $servers = ws_mcp_servers();

    return $servers[(int) $id] ?? null;
}

/**
 * A server's tools, as the client keeps them (pg_mcp_client_tool_normalise()).
 *
 * @param array $server
 * @return array[] by name
 */
function ws_mcp_tools($server)
{
    $tools = array();

    foreach ((array) json_decode((string) ($server['tools'] ?? ''), true) as $tool) {
        if (is_array($tool) && isset($tool['name'])) {
            $tools[(string) $tool['name']] = $tool;
        }
    }

    return $tools;
}

/**
 * The administrator's word on each tool, by name: auto (the server says it
 * reads only), auto_declared (the administrator says it reads only), approve
 * (each call waits for the approval of the person who asked).
 *
 * @param array $server
 * @return array name => rule
 */
function ws_mcp_tool_rules($server)
{
    $rules = array();

    foreach ((array) json_decode((string) ($server['tool_rules'] ?? ''), true) as $name => $rule) {
        if (in_array($rule, array('auto', 'auto_declared', 'approve'), true)) {
            $rules[(string) $name] = $rule;
        }
    }

    return $rules;
}

/**
 * Can a server's tools that write be turned on, each call approved by the
 * person who asked? Only where people sign in with their own accounts: the
 * call runs with the account of the one who approves it.
 *
 * @param array $server
 * @return bool
 */
function ws_mcp_can_approve($server)
{
    return ((string) ($server['auth_kind'] ?? '') === 'oauth');
}

/**
 * How a tool may be turned on with one connection for the whole site:
 * 'read' when the server says it reads only, 'declare' when the server says
 * nothing and the administrator may declare it, '' when it may not be turned
 * on at all (the server says it writes, or that it destroys).
 *
 * @param array $tool
 * @return string
 */
function ws_mcp_tool_can_run($tool)
{
    $hints = (isset($tool['hints']) && is_array($tool['hints'])) ? $tool['hints'] : array();

    if (($hints['readOnlyHint'] ?? null) === true) {
        return 'read';
    }

    // The other hints mean something only for a tool that writes: a server
    // that gives one of them, or says readOnlyHint false, says the tool
    // writes. Only a tool with none of them is the administrator's to judge.
    foreach (array('readOnlyHint', 'destructiveHint', 'idempotentHint') as $hint) {
        if (array_key_exists($hint, $hints)) {
            return '';
        }
    }

    return 'declare';
}

/**
 * The tools that are turned on and may still run: a rule for a tool the
 * server no longer lists, or now marks as writing, counts for nothing. A tool
 * whose calls wait for approval carries 'approve' => true; that rule counts
 * only while the server signs in with OAuth.
 *
 * @param array $server
 * @return array[] by name
 */
function ws_mcp_tools_on($server)
{
    $tools = ws_mcp_tools($server);
    $on = array();

    foreach (ws_mcp_tool_rules($server) as $name => $rule) {
        if (!isset($tools[$name])) {
            continue;
        }

        if ($rule === 'approve') {
            if (ws_mcp_can_approve($server)) {
                $on[$name] = $tools[$name] + array('approve' => true);
            }

            continue;
        }

        $can = ws_mcp_tool_can_run($tools[$name]);

        if (($can === 'read') || (($can === 'declare') && ($rule === 'auto_declared'))) {
            $on[$name] = $tools[$name];
        }
    }

    return $on;
}

/**
 * The rule a tool gets from the settings form: what the switch or the list
 * asked for, as far as the tool and the server allow it.
 *
 * @param array  $server
 * @param array  $tool
 * @param string $wanted 1 (the switch), auto_declared or approve (the list)
 * @return string the rule, '' for off
 */
function ws_mcp_tool_rule_for($server, $tool, $wanted)
{
    $wanted = (string) $wanted;

    if (($wanted === '') || ($wanted === '0')) {
        return '';
    }

    $can = ws_mcp_tool_can_run($tool);

    if ($can === 'read') {
        return 'auto';
    }

    if (!ws_mcp_can_approve($server)) {
        return ($can === 'declare') ? 'auto_declared' : '';
    }

    // On a server people sign in to: a tool that writes asks first, and one
    // the server says nothing about is what the administrator chose.
    if ($can === 'declare') {
        return ($wanted === 'approve') ? 'approve' : 'auto_declared';
    }

    return 'approve';
}

/**
 * The connection to a server, as the client takes it. On a server that signs
 * in with OAuth it carries the account it was opened with (account_id, and
 * person when that is somebody's own), so a 401 renews that account's token.
 *
 * @param array $server
 * @param int   $timeout     seconds one request may take
 * @param int   $user_id     whose own account comes first (0 for the site's)
 * @param bool  $person_only only that person's own account will do
 * @return array
 */
function ws_mcp_connection($server, $timeout = WS_MCP_SCREEN_TIMEOUT, $user_id = 0, $person_only = false)
{
    $oauth = ((string) $server['auth_kind'] === 'oauth');
    $account = $oauth ? ws_mcp_oauth_account($server, $user_id, $person_only) : null;
    $person = $account && ((int) $account['user_id'] > 0);

    // A person's own account starts a conversation of its own: the session
    // the server's row keeps was opened with the site's token.
    $conn = pg_mcp_client_connection(
        (string) $server['url'],
        $oauth
            ? ws_mcp_account_headers($account)
            : ws_mcp_auth_headers((string) $server['auth_kind'], ws_mcp_secret_decode($server['secret'] ?? '')),
        $person ? '' : (string) $server['protocol'],
        $person ? '' : (string) $server['session_id'],
        $timeout
    );

    $conn['account_id'] = $account ? (int) $account['id'] : 0;
    $conn['person'] = $person;

    return $conn;
}

/* ---------------------------------------------------------------------------
   Signing in with OAuth
   --------------------------------------------------------------------------- */

/**
 * The connected-accounts key of a server's sign-in (includes/fn/connect.php).
 *
 * @param array $server
 * @return string mcp_<server id>
 */
function ws_mcp_connect_key($server)
{
    return 'mcp_' . (int) $server['id'];
}

/**
 * The provider of a server that signs in with OAuth, for
 * includes/fn/connect.php: built from the server's row and from what its
 * discovery found, kept with its application (connect_apps.options).
 *
 * @param string $key mcp_<server id>
 * @return array|null
 */
function ws_mcp_connect_provider($key)
{
    if (!preg_match('/^mcp_([1-9][0-9]{0,9})$/', (string) $key, $match)) {
        return null;
    }

    $server = ws_mcp_server((int) $match[1]);

    if (!$server || ($server['auth_kind'] !== 'oauth')) {
        return null;
    }

    $app = pg_connect_app($key);

    return ws_mcp_connect_definition($server, is_array($app) ? $app['options'] : array());
}

/**
 * A provider definition from a server and what its discovery found.
 *
 * The service names no account, may hand out no refresh token and may give
 * tokens that do not end; every authorization and token request names the
 * server as the resource the token is for (RFC 8707), so a token for this
 * server is not good for another.
 *
 * @param array $server
 * @param array $options connect_apps.options: authorize_url, token_url,
 *                       revoke_url, issuer, iss_param, resource, scopes,
 *                       client_auth
 * @return array|null null when discovery has not found the endpoints
 */
function ws_mcp_connect_definition($server, $options)
{
    if (empty($options['authorize_url']) || empty($options['token_url'])) {
        return null;
    }

    $resource = (string) ($options['resource'] ?? '');
    $params = ($resource !== '') ? array('resource' => $resource) : array();
    $issuer = (string) ($options['issuer'] ?? '');

    return array(
        'label'              => '@' . $server['alias'],
        'account_label'      => '@' . $server['alias'],
        'authorize_url'      => (string) $options['authorize_url'],
        'token_url'          => (string) $options['token_url'],
        'revoke_url'         => (string) ($options['revoke_url'] ?? ''),
        'issuers'            => ($issuer !== '') ? array($issuer) : array(),
        'iss_required'       => !empty($options['iss_param']),
        'identity'           => false,
        'identity_scopes'    => pg_connect_scope_list(isset($options['scopes']) && is_array($options['scopes']) ? $options['scopes'] : array()),
        'refresh_optional'   => true,
        'default_ttl'        => WS_MCP_TOKEN_TTL,
        'nonce'              => false,
        'authorize_params'   => $params,
        'token_params'       => $params,
        'client_auth'        => (($options['client_auth'] ?? '') === 'basic') ? 'basic' : 'post',
        'revoke_client_auth' => true,
    );
}

/**
 * How the site becomes a client of a server's authorization server: the
 * administrator's own application when a client ID was entered, else the
 * site's client ID metadata document when the server reads those (and the
 * site is on https, so the server can fetch it), else a dynamic registration.
 *
 * @param array  $found     pg_mcp_client_discover_auth()
 * @param string $client_id entered by the administrator, '' for none
 * @param bool   $https     the site's own address is https
 * @return string manual | cimd | dcr | '' when there is no way
 */
function ws_mcp_oauth_registration($found, $client_id, $https)
{
    if (trim((string) $client_id) !== '') {
        return 'manual';
    }

    if (!empty($found['cimd']) && $https) {
        return 'cimd';
    }

    return ((string) ($found['registration_url'] ?? '') !== '') ? 'dcr' : '';
}

/**
 * Finds out how to sign in to a server and makes the site a client there
 * (ws_mcp_oauth_registration()). What was found is kept with the application
 * (connect_apps, provider mcp_<id>). A different client than before ends the
 * connections made with the old one.
 *
 * @param array       $server
 * @param string      $client_id     entered by the administrator, '' for none
 * @param string|null $client_secret entered, null keeps the stored one
 * @param int         $user_id
 * @return array ok, error
 */
function ws_mcp_oauth_setup($server, $client_id, $client_secret, $user_id)
{
    $fail = function ($error) {
        return array('ok' => false, 'error' => $error);
    };

    if (!pg_connect_schema_ready()) {
        return $fail(lang('The database has not been upgraded yet.'));
    }

    if (!pg_connect_can_seal()) {
        return $fail(lang('This site has no encryption key, so it cannot keep the permissions of a connected account.'));
    }

    $found = pg_mcp_client_discover_auth((string) $server['url'], null, WS_MCP_SCREEN_TIMEOUT);

    if (!$found['ok']) {
        return $fail($found['error']);
    }

    $key = ws_mcp_connect_key($server);
    $app = pg_connect_app($key);
    $client_id = trim((string) $client_id);
    $mode = ws_mcp_oauth_registration($found, $client_id, (URL_SCHEME === 'https://'));
    $secret = '';
    $auth = in_array('client_secret_post', $found['auth_methods'], true) ? 'post' : 'basic';

    switch ($mode) {
        case 'manual':
            $kept = is_array($app) && ($app['client_id'] === $client_id) && (($app['options']['registered'] ?? '') === 'manual');
            $secret = (($client_secret !== null) && ((string) $client_secret !== '')) ? (string) $client_secret : ($kept ? $app['client_secret'] : '');
            break;

        case 'cimd':
            $client_id = pg_connect_client_metadata_url();
            break;

        case 'dcr':
            // A registration this server already made is kept: registering
            // again on every save would leave clients behind there.
            if (is_array($app) && (($app['options']['registered'] ?? '') === 'dcr') && (($app['options']['token_url'] ?? '') === $found['token_url'])) {
                $client_id = $app['client_id'];
                $secret = $app['client_secret'];
                $auth = (($app['options']['client_auth'] ?? '') === 'basic') ? 'basic' : 'post';
                break;
            }

            $registered = pg_mcp_client_register(
                $found['registration_url'],
                pg_mcp_client_client_metadata(pg_connect_redirect_uri(), URL_SCHEME . HOSTNAME_SETTING . PATH),
                $found['scopes']
            );

            if (!$registered['ok']) {
                return $fail($registered['error']);
            }

            $client_id = $registered['client_id'];
            $secret = $registered['client_secret'];
            $auth = ($registered['auth_method'] === 'client_secret_post') ? 'post' : 'basic';
            break;

        default:
            return $fail(lang(array(
                'string' => 'The service does not register the site on its own. Register an application in its developer settings with the redirect address {var:1}, then enter its client ID here.',
                'vars'   => pg_connect_redirect_uri())));
    }

    if (strlen($client_id) > 255) {
        return $fail(lang('The client ID is too long.'));
    }

    // A new client cannot renew what the old one was given.
    if (is_array($app) && ($app['client_id'] !== $client_id)) {
        ws_mcp_oauth_forget($server);
    }

    $saved = pg_connect_app_save($key, $client_id, ($secret !== '') ? $secret : null, array(
        'public'        => ($secret === ''),
        'registered'    => $mode,
        'authorize_url' => $found['authorize_url'],
        'token_url'     => $found['token_url'],
        'revoke_url'    => $found['revoke_url'],
        'issuer'        => $found['issuer'],
        'iss_param'     => $found['iss_param'],
        'resource'      => $found['resource'],
        'scopes'        => $found['scopes'],
        'client_auth'   => $auth,
    ), true, (int) $user_id);

    return $saved ? array('ok' => true, 'error' => '') : $fail(lang('This site has no encryption key, so it cannot keep the permissions of a connected account.'));
}

/**
 * Ends a server's sign-in: its connections (the service is told where it can
 * be) and its application. Called while the row still says oauth, so the
 * provider can still be built for the revocation.
 *
 * @param array $server
 */
function ws_mcp_oauth_forget($server)
{
    if (!pg_connect_schema_ready()) {
        return;
    }

    $key = ws_mcp_connect_key($server);

    foreach (pg_connect_accounts(array('provider' => $key)) as $account) {
        pg_connect_disconnect($account['id']);
    }

    db("DELETE FROM connect_apps WHERE provider = '" . e($key) . "'");
}

/**
 * The site's connection to a server that signs in with OAuth.
 *
 * @param array $server
 * @return array|null pg_connect_account_public(), null when there is none
 */
function ws_mcp_site_account($server)
{
    if ((($server['auth_kind'] ?? '') !== 'oauth') || !pg_connect_schema_ready()) {
        return null;
    }

    $accounts = pg_connect_accounts(array('provider' => ws_mcp_connect_key($server), 'user_id' => 0));

    return $accounts ? $accounts[0] : null;
}

/**
 * A person's own connection to a server that signs in with OAuth.
 *
 * @param array $server
 * @param int   $user_id
 * @return array|null pg_connect_account_public(), null when there is none
 */
function ws_mcp_person_account($server, $user_id)
{
    if (((int) $user_id <= 0) || (($server['auth_kind'] ?? '') !== 'oauth') || !pg_connect_schema_ready()) {
        return null;
    }

    $accounts = pg_connect_accounts(array('provider' => ws_mcp_connect_key($server), 'user_id' => (int) $user_id));

    return $accounts ? $accounts[0] : null;
}

/**
 * The account a request to a server that signs in with OAuth goes with: the
 * person's own when it works, else the site's (never the site's for a call
 * only the person's own account may make).
 *
 * @param array $server
 * @param int   $user_id     0 for the site's account only
 * @param bool  $person_only
 * @return array|null an active account, null when there is none
 */
function ws_mcp_oauth_account($server, $user_id = 0, $person_only = false)
{
    $mine = ws_mcp_person_account($server, $user_id);

    if ($mine && ($mine['status'] === 'active')) {
        return $mine;
    }

    if ($person_only) {
        return null;
    }

    $site = ws_mcp_site_account($server);

    return ($site && ($site['status'] === 'active')) ? $site : null;
}

/**
 * The Authorization header of a connected account: its access token.
 *
 * @param array|null $account
 * @param bool       $force renew the token even when it looks valid
 * @return string[] empty when no token could be had
 */
function ws_mcp_account_headers($account, $force = false)
{
    if (!$account || ($account['status'] !== 'active')) {
        return array();
    }

    $token = pg_connect_access_token($account['id'], array(), $force);

    return $token['ok'] ? array('Authorization: Bearer ' . $token['token']) : array();
}

/**
 * The Authorization header of a server that signs in with OAuth
 * (ws_mcp_oauth_account()).
 *
 * @param array $server
 * @param bool  $force       renew the token even when it looks valid
 * @param int   $user_id     whose own account comes first (0 for the site's)
 * @param bool  $person_only
 * @return string[] empty when nobody is connected or no token could be had
 */
function ws_mcp_oauth_headers($server, $force = false, $user_id = 0, $person_only = false)
{
    return ws_mcp_account_headers(ws_mcp_oauth_account($server, $user_id, $person_only), $force);
}

/**
 * After a 401 from a server that signs in with OAuth: the token of the
 * account the connection was opened with is renewed once and the connection
 * carries the new one. A service that gave no refresh token marks the
 * connection to be connected again (pg_connect_refresh()).
 *
 * @param array $conn   the connection, changed in place
 * @param array $server
 * @param int   $status the HTTP status the request ended with
 * @return bool whether to send the request again
 */
function ws_mcp_reauthorize(&$conn, $server, $status)
{
    if (((int) $status !== 401) || ((string) $server['auth_kind'] !== 'oauth') || empty($conn['account_id'])) {
        return false;
    }

    $token = pg_connect_access_token((int) $conn['account_id'], array(), true);

    if (!$token['ok']) {
        return false;
    }

    $conn['headers'] = array('Authorization: Bearer ' . $token['token']);

    return true;
}

/**
 * What a failed request says: for a server that signs in with OAuth and
 * refused the token, that the account has to be connected again - the site's
 * by an administrator, a person's own by that person.
 *
 * @param array  $server
 * @param int    $status
 * @param string $error
 * @param bool   $person the request went with a person's own account
 * @return string
 */
function ws_mcp_error_text($server, $status, $error, $person = false)
{
    if (((string) ($server['auth_kind'] ?? '') === 'oauth') && ((int) $status === 401)) {
        return $person
            ? lang(array('string' => 'Your own connection to @{var:1} ended. Connect your account again.', 'vars' => $server['alias']))
            : lang(array('string' => '@{var:1} is not connected, or its connection ended. An administrator connects it again under Workspace settings › MCP servers.', 'vars' => $server['alias']));
    }

    return (string) $error;
}

/**
 * The address that offers a person to connect their own account to a server,
 * from a channel: the channel opens and asks first (assets/js/workspace.js).
 *
 * @param array $server
 * @param int   $channel_id
 * @return string
 */
function ws_mcp_connect_link($server, $channel_id)
{
    return URL_SCHEME . HOSTNAME_SETTING . PATH . SOFTWARE_DIRECTORY . '/workspace.php?channel=' . (int) $channel_id . '&mcp_connect=' . (int) $server['id'];
}

/**
 * Sends a person to the service to connect their own account. The sign-in
 * must be set up already: setting it up registers the site with the service,
 * which is an administrator's to do.
 *
 * @param array  $viewer
 * @param array  $server
 * @param string $return the screen to come back to (pg_connect_return_path())
 * @return array ok, url, error
 */
function ws_mcp_person_connect_start($viewer, $server, $return)
{
    $fail = function ($error) {
        return array('ok' => false, 'url' => '', 'error' => $error);
    };

    if (($server['auth_kind'] !== 'oauth') || ($server['status'] !== 'active')) {
        return $fail(lang('This kind of connection is not available.'));
    }

    if (!pg_connect_schema_ready()) {
        return $fail(lang('The database has not been upgraded yet.'));
    }

    $key = ws_mcp_connect_key($server);

    if (!pg_connect_app_ready(pg_connect_app($key)) || !ws_mcp_connect_provider($key)) {
        return $fail(lang(array('string' => 'The sign-in to @{var:1} is not set up yet. An administrator saves the server again under Workspace settings › MCP servers.', 'vars' => $server['alias'])));
    }

    $mine = ws_mcp_person_account($server, (int) $viewer['id']);

    return pg_connect_start($key, 'mcp', 'person', $viewer, $return, $mine ? (int) $mine['id'] : 0);
}

/**
 * Keeps what a connection learnt (the version, the session) and the last
 * error, when they changed.
 *
 * @param array  $server
 * @param array  $conn
 * @param string $error '' after a request that went through
 */
function ws_mcp_remember($server, $conn, $error)
{
    // What a person's own account met is not the server's state: the row
    // keeps the site's conversation, and the card its errors.
    if (!empty($conn['person'])) {
        return;
    }

    $error = mb_substr((string) $error, 0, 250);

    if (($conn['protocol'] === (string) $server['protocol']) && ($conn['session'] === (string) $server['session_id']) && ($error === (string) $server['last_error'])) {
        return;
    }

    db("UPDATE ws_mcp_servers SET
            protocol = '" . e(mb_substr($conn['protocol'], 0, 20)) . "',
            session_id = '" . e($conn['session']) . "',
            last_error = '" . e($error) . "'
        WHERE id = '" . (int) $server['id'] . "'");

    ws_mcp_servers(true);
}

/**
 * Asks a server for its tools again and keeps the list, with the name it
 * gives itself and its instructions. Rules for tools it no longer lists are
 * dropped. On a server that signs in with OAuth the asker's own account is
 * used when they have one, else the site's.
 *
 * @param array $server
 * @param int   $user_id who asks (0: the site's account only)
 * @return array ok, error, count
 */
function ws_mcp_refresh_tools($server, $user_id = 0)
{
    if (!function_exists('curl_init')) {
        return array('ok' => false, 'error' => lang('This server cannot make outgoing HTTPS requests (cURL is missing).'), 'count' => 0);
    }

    $conn = ws_mcp_connection($server, WS_MCP_SCREEN_TIMEOUT, $user_id);

    if (((string) $server['auth_kind'] === 'oauth') && empty($conn['headers'])) {
        return array('ok' => false, 'error' => lang(array('string' => 'Connect @{var:1} first: the site signs in to the service before its tools can be read.', 'vars' => $server['alias'])), 'count' => 0);
    }

    $conn['protocol'] = '';
    $conn['session'] = '';

    $open = pg_mcp_client_negotiate($conn);

    if (!$open['ok'] && ws_mcp_reauthorize($conn, $server, $open['status'])) {
        $open = pg_mcp_client_negotiate($conn);
    }

    if (!$open['ok']) {
        $error = ws_mcp_error_text($server, $open['status'], $open['error'], $conn['person']);
        ws_mcp_remember($server, $conn, $error);

        return array('ok' => false, 'error' => $error, 'count' => 0);
    }

    $list = pg_mcp_client_list_tools($conn);

    if (!$list['ok'] && ws_mcp_reauthorize($conn, $server, $list['status'])) {
        $list = pg_mcp_client_list_tools($conn);
    }

    if (!$list['ok']) {
        $error = ws_mcp_error_text($server, $list['status'], $list['error'], $conn['person']);
        ws_mcp_remember($server, $conn, $error);

        return array('ok' => false, 'error' => $error, 'count' => 0);
    }

    $tools = array();

    foreach ($list['tools'] as $tool) {
        $tools[] = $tool;
    }

    $rules = array();
    $listed = array_column($tools, null, 'name');

    foreach (ws_mcp_tool_rules($server) as $name => $rule) {
        if (isset($listed[$name])) {
            $rules[$name] = $rule;
        }
    }

    $info = trim(mb_substr(trim((string) ($open['server']['title'] ?? ($open['server']['name'] ?? ''))) . ' ' . trim((string) ($open['server']['version'] ?? '')), 0, 200));

    // A session opened with a person's own account is not the site's.
    db("UPDATE ws_mcp_servers SET
            protocol = '" . e(mb_substr($conn['protocol'], 0, 20)) . "',
            session_id = '" . e($conn['person'] ? '' : $conn['session']) . "',
            server_info = '" . e($info) . "',
            instructions = '" . e(mb_substr((string) $open['instructions'], 0, 4000)) . "',
            tools = '" . e((string) json_encode($tools, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) . "',
            tools_at = '" . time() . "',
            tools_ttl = '" . (int) $list['ttl'] . "',
            tool_rules = '" . e((string) json_encode((object) $rules)) . "',
            last_error = ''
        WHERE id = '" . (int) $server['id'] . "'");

    ws_mcp_servers(true);

    return array('ok' => true, 'error' => '', 'count' => count($tools));
}

/**
 * The host of a server's address, for a line on a screen.
 *
 * @param string $url
 * @return string
 */
function ws_mcp_host($url)
{
    $host = (string) parse_url((string) $url, PHP_URL_HOST);

    return ($host !== '') ? $host : (string) $url;
}

/* ---------------------------------------------------------------------------
   The settings screen
   --------------------------------------------------------------------------- */

/**
 * A form on the settings screen: a server added, changed, asked for its tools
 * again, turned off or on, removed, its tools turned on and off, the site's
 * sign-in to it connected or ended, or the viewer's own account connected or
 * ended (anybody who may open the screen).
 *
 * @param array    $viewer
 * @param string   $action mcp_save | mcp_refresh | mcp_toggle | mcp_delete |
 *                         mcp_tools | mcp_connect | mcp_disconnect |
 *                         mcp_connect_me | mcp_disconnect_me
 * @param liveform $liveform
 * @return string the anchor to go back to
 */
function ws_mcp_settings_post($viewer, $action, $liveform)
{
    if (!ws_mcp_ready()) {
        $liveform->add_error(lang('The database has not been upgraded yet.'));
        return '#ws-mcp';
    }

    // A person's own account is theirs to connect and end.
    if (($action === 'mcp_connect_me') || ($action === 'mcp_disconnect_me')) {
        $server = ws_mcp_server((int) ($_POST['mcp_id'] ?? 0));

        if (!$server || ($server['auth_kind'] !== 'oauth')) {
            $liveform->add_error(lang('That MCP server could not be found.'));
            return '#ws-mcp';
        }

        $anchor = '#ws-mcp-' . (int) $server['id'];

        if ($action === 'mcp_connect_me') {
            $start = ws_mcp_person_connect_start($viewer, $server, 'workspace_settings.php?mcp_connected=' . (int) $server['id'] . $anchor);

            if (!$start['ok']) {
                $liveform->add_error($start['error']);
                return $anchor;
            }

            header('Location: ' . $start['url']);
            exit();
        }

        if (ws_mcp_person_disconnect($viewer, $server)) {
            $liveform->add_notice(lang(array('string' => 'Your own account is disconnected from @{var:1}.', 'vars' => $server['alias'])));
        }

        return $anchor;
    }

    if ((int) $viewer['role'] !== 0) {
        $liveform->add_error(lang('Only an administrator can add MCP servers.'));
        return '#ws-mcp';
    }

    $username = (string) ($_SESSION['sessionusername'] ?? '');
    $id = (int) ($_POST['mcp_id'] ?? 0);
    $server = ($id > 0) ? ws_mcp_server($id) : null;

    if (($id > 0) && !$server) {
        $liveform->add_error(lang('That MCP server could not be found.'));
        return '#ws-mcp';
    }

    $anchor = $server ? '#ws-mcp-' . (int) $server['id'] : '#ws-mcp';

    if (function_exists('set_time_limit')) {
        @set_time_limit(WS_MCP_SCREEN_TIMEOUT * (PG_MCP_CLIENT_MAX_PAGES + 3));
    }

    switch ($action) {
        case 'mcp_save':
            return ws_mcp_settings_save($viewer, $server, $liveform, $username);

        case 'mcp_refresh':
            if (!$server) {
                break;
            }

            $refresh = ws_mcp_refresh_tools($server, (int) $viewer['id']);

            if ($refresh['ok']) {
                $liveform->add_notice(lang(array('string' => '@{var:1} answered with {var:2} tools.', 'vars' => array($server['alias'], $refresh['count']))));
            } else {
                $liveform->add_error(lang(array('string' => '@{var:1} could not be reached: {var:2}', 'vars' => array($server['alias'], $refresh['error']))));
            }

            return $anchor;

        case 'mcp_toggle':
            if (!$server) {
                break;
            }

            $status = ($server['status'] === 'active') ? 'disabled' : 'active';

            db("UPDATE ws_mcp_servers SET status = '" . $status . "', updated_at = '" . time() . "' WHERE id = '" . (int) $server['id'] . "'");
            ws_mcp_servers(true);

            log_activity(lang(array('string' => ($status === 'active') ? 'the MCP server @{var:1} was turned on' : 'the MCP server @{var:1} was turned off', 'vars' => $server['alias'])), $username);
            $liveform->add_notice(($status === 'active')
                ? lang(array('string' => '@{var:1} can be asked again.', 'vars' => $server['alias']))
                : lang(array('string' => '@{var:1} is turned off. Nothing is sent to it until it is turned on again.', 'vars' => $server['alias'])));

            return $anchor;

        case 'mcp_delete':
            if (!$server) {
                break;
            }

            ws_mcp_oauth_forget($server);
            db("DELETE FROM ws_mcp_servers WHERE id = '" . (int) $server['id'] . "'");
            ws_mcp_servers(true);

            // The tasks handed to its alias are with nobody now
            // (delegation.php); what it proposed on them stays to be decided.
            if (function_exists('ws_delegation_ready') && ws_delegation_ready()) {
                db("UPDATE ws_tasks SET delegate = '', delegated_by = 0, delegated_at = 0, updated_at = '" . time() . "'
                    WHERE delegate = 'mcp:" . (int) $server['id'] . "'");
            }

            log_activity(lang(array('string' => 'the MCP server @{var:1} was removed', 'vars' => $server['alias'])), $username);
            $liveform->add_notice(lang(array('string' => '@{var:1} was removed, with its credential.', 'vars' => $server['alias'])));

            return '#ws-mcp';

        case 'mcp_tools':
            if (!$server) {
                break;
            }

            $tools = ws_mcp_tools($server);
            $wanted = (isset($_POST['mcp_tool']) && is_array($_POST['mcp_tool'])) ? $_POST['mcp_tool'] : array();
            $rules = array();

            foreach ($wanted as $name => $on) {
                $name = (string) $name;

                if (!isset($tools[$name]) || !is_scalar($on)) {
                    continue;
                }

                $rule = ws_mcp_tool_rule_for($server, $tools[$name], (string) $on);

                if ($rule !== '') {
                    $rules[$name] = $rule;
                }
            }

            db("UPDATE ws_mcp_servers SET tool_rules = '" . e((string) json_encode((object) $rules)) . "', updated_at = '" . time() . "' WHERE id = '" . (int) $server['id'] . "'");
            ws_mcp_servers(true);

            log_activity(lang(array('string' => 'the tools of the MCP server @{var:1} were changed', 'vars' => $server['alias'])), $username);
            $liveform->add_notice(lang(array('string' => '{var:1} tools of @{var:2} can be used.', 'vars' => array(count($rules), $server['alias']))));

            // The tool that takes the messages of a server that is an agent
            // itself (mcp_agent.php), by the rules just saved.
            if (function_exists('ws_mcp_agent_settings_save')) {
                ws_mcp_agent_settings_save($server, $rules, $liveform);
            }

            return $anchor;

        case 'mcp_connect':
            if (!$server || ($server['auth_kind'] !== 'oauth')) {
                break;
            }

            ws_mcp_settings_connect($viewer, $server, $liveform);

            return $anchor;

        case 'mcp_disconnect':
            $account = $server ? ws_mcp_site_account($server) : null;

            if (!$account) {
                break;
            }

            pg_connect_disconnect($account['id']);

            log_activity(lang(array('string' => 'the site\'s connection to the MCP server @{var:1} was ended', 'vars' => $server['alias'])), $username);
            $liveform->add_notice(lang(array('string' => '@{var:1} is disconnected. Nothing is sent to it until it is connected again.', 'vars' => $server['alias'])));

            return $anchor;
    }

    return $anchor;
}

/**
 * A server added or changed. A new server, or one whose address or
 * credential changed, is asked for its tools at once.
 *
 * @param array      $viewer
 * @param array|null $server
 * @param liveform   $liveform
 * @param string     $username
 * @return string the anchor to go back to
 */
function ws_mcp_settings_save($viewer, $server, $liveform, $username)
{
    $alias = ws_mcp_alias_clean($_POST['mcp_alias'] ?? '');
    $name = trim(mb_substr(preg_replace('/\s+/u', ' ', (string) ($_POST['mcp_name'] ?? '')), 0, 100));
    $url = trim((string) ($_POST['mcp_url'] ?? ''));
    $kind = (string) ($_POST['mcp_auth'] ?? 'none');
    $anchor = $server ? '#ws-mcp-' . (int) $server['id'] : '#ws-mcp';

    if (!$server && (count(ws_mcp_servers()) >= WS_MCP_MAX_SERVERS)) {
        $liveform->add_error(lang(array('string' => 'A site can have at most {var:1} MCP servers.', 'vars' => WS_MCP_MAX_SERVERS)));
        return $anchor;
    }

    $problem = ws_mcp_alias_problem($alias, $server ? (int) $server['id'] : 0);

    if ($problem === '' && !pg_mcp_client_url_is_valid($url)) {
        $problem = lang('The address must be an https address, without a user name, a password or a # part.');
    }

    if ($problem === '' && !isset(ws_mcp_auth_kinds()[$kind])) {
        $problem = lang('Choose how the site signs in to the server.');
    }

    // The credential: what was typed, or what is kept when the field was
    // left empty and the kind did not change.
    $kept = ($server && ($server['auth_kind'] === $kind)) ? ws_mcp_secret_decode($server['secret'] ?? '') : array();
    $secret = array();
    $oauth_client_id = '';
    $oauth_client_secret = '';

    if ($problem === '') {
        switch ($kind) {
            case 'bearer':
                $token = trim((string) ($_POST['mcp_token'] ?? ''));
                $secret = array('token' => ($token !== '') ? $token : (string) ($kept['token'] ?? ''));

                if ($secret['token'] === '') {
                    $problem = lang('Enter the access token.');
                }
                break;

            case 'header':
                $header = trim((string) ($_POST['mcp_header_name'] ?? ''));
                $value = trim((string) ($_POST['mcp_header_value'] ?? ''));
                $secret = array('name' => $header, 'value' => ($value !== '') ? $value : (string) ($kept['value'] ?? ''));
                $problem = ws_mcp_header_name_problem($header);

                if (($problem === '') && ($secret['value'] === '')) {
                    $problem = lang('Enter the value of the header.');
                }
                break;

            case 'basic':
                $user = trim((string) ($_POST['mcp_user'] ?? ''));
                $password = (string) ($_POST['mcp_password'] ?? '');
                $secret = array('user' => $user, 'password' => ($password !== '') ? $password : (string) ($kept['password'] ?? ''));

                if (($user === '') || (strpos($user, ':') !== false)) {
                    $problem = lang('Enter the user name (without a colon).');
                }
                break;

            // Nothing is kept on the row: the application goes to connect_apps
            // once the server is saved (ws_mcp_oauth_setup()).
            case 'oauth':
                $oauth_client_id = trim((string) ($_POST['mcp_client_id'] ?? ''));
                $oauth_client_secret = trim((string) ($_POST['mcp_client_secret'] ?? ''));

                if (preg_match('/[\r\n]/', $oauth_client_id . $oauth_client_secret)) {
                    $problem = lang('A credential cannot hold a line break.');
                } elseif ((strlen($oauth_client_id) > 255) || (strlen($oauth_client_secret) > 1000)) {
                    $problem = lang('The client ID is too long.');
                } elseif (!function_exists('pg_connect_schema_ready') || !pg_connect_schema_ready()) {
                    $problem = lang('The database has not been upgraded yet.');
                }
                break;
        }
    }

    if (preg_match('/[\r\n]/', implode('', array_map('strval', $secret)))) {
        $problem = lang('A credential cannot hold a line break.');
    }

    if ($problem !== '') {
        $liveform->add_error($problem);
        return $anchor;
    }

    $now = time();
    $stored = ws_mcp_secret_encode($secret);
    $changed = !$server || ($server['url'] !== $url) || ($server['auth_kind'] !== $kind) || ($stored !== '' && ws_mcp_secret_decode($server['secret'] ?? '') !== $secret);

    // A sign-in made for another address, or no longer wanted, ends first,
    // while the row still says how to tell the service.
    if ($server && ($server['auth_kind'] === 'oauth') && (($kind !== 'oauth') || ($server['url'] !== $url))) {
        ws_mcp_oauth_forget($server);
    }

    if (!$server) {
        db("INSERT INTO ws_mcp_servers (alias, name, url, auth_kind, secret, status, tool_rules, created_by, created_at, updated_at)
            VALUES ('" . e($alias) . "', '" . e($name) . "', '" . e($url) . "', '" . e($kind) . "', '" . e($stored) . "', 'active', '{}',
                '" . (int) $viewer['id'] . "', '" . $now . "', '" . $now . "')");

        $id = (int) mysqli_insert_id(db::$con);
        log_activity(lang(array('string' => 'the MCP server @{var:1} was added', 'vars' => $alias)), $username);
    } else {
        $id = (int) $server['id'];

        // A new address or credential is a new conversation: what the old one
        // agreed on is forgotten.
        db("UPDATE ws_mcp_servers SET
                alias = '" . e($alias) . "',
                name = '" . e($name) . "',
                url = '" . e($url) . "',
                auth_kind = '" . e($kind) . "',
                secret = '" . e($stored) . "',
                " . ($changed ? "protocol = '', session_id = ''," : '') . "
                updated_at = '" . $now . "'
            WHERE id = '" . $id . "'");

        log_activity(lang(array('string' => 'the MCP server @{var:1} was changed', 'vars' => $alias)), $username);
    }

    ws_mcp_servers(true);
    $saved = ws_mcp_server($id);

    if (!$saved) {
        $liveform->add_error(lang('The MCP server could not be saved.'));
        return '#ws-mcp';
    }

    if ($kind === 'oauth') {
        $app = pg_connect_app(ws_mcp_connect_key($saved));
        $manual = (is_array($app) && (($app['options']['registered'] ?? '') === 'manual')) ? $app['client_id'] : '';

        if ($changed || !pg_connect_app_ready($app) || ($oauth_client_id !== $manual) || ($oauth_client_secret !== '')) {
            $setup = ws_mcp_oauth_setup($saved, $oauth_client_id, ($oauth_client_secret !== '') ? $oauth_client_secret : null, (int) $viewer['id']);

            if (!$setup['ok']) {
                $liveform->add_error(lang(array('string' => '@{var:1} was saved, but the sign-in to it could not be set up: {var:2}', 'vars' => array($alias, $setup['error']))));
                return '#ws-mcp-' . $id;
            }
        }

        if (!ws_mcp_oauth_headers($saved, false, (int) $viewer['id'])) {
            $liveform->add_notice(lang(array('string' => '@{var:1} was saved. Connect the site to it with the Connect button; its tools are read once it is connected.', 'vars' => $alias)));
            return '#ws-mcp-' . $id;
        }
    }

    if (!$changed) {
        $liveform->add_notice(lang(array('string' => '@{var:1} was saved.', 'vars' => $alias)));
        return '#ws-mcp-' . $id;
    }

    $refresh = ws_mcp_refresh_tools($saved, (int) $viewer['id']);

    if ($refresh['ok']) {
        $liveform->add_notice(lang(array('string' => '@{var:1} was saved and answered with {var:2} tools. Turn on the ones that may be used.', 'vars' => array($alias, $refresh['count']))));
    } else {
        $liveform->add_error(lang(array('string' => '@{var:1} was saved but could not be reached: {var:2}', 'vars' => array($alias, $refresh['error']))));
    }

    return '#ws-mcp-' . $id;
}

/**
 * Sends the administrator to the service to connect the site's account: the
 * sign-in is set up again first when it is not ready (the service could not
 * be reached when the server was saved). Leaves the page when it can; a
 * problem is left on the form.
 *
 * @param array    $viewer
 * @param array    $server
 * @param liveform $liveform
 */
function ws_mcp_settings_connect($viewer, $server, $liveform)
{
    $key = ws_mcp_connect_key($server);
    $app = pg_connect_app($key);

    if (!pg_connect_app_ready($app) || !ws_mcp_connect_provider($key)) {
        $manual = (is_array($app) && (($app['options']['registered'] ?? '') === 'manual')) ? $app['client_id'] : '';
        $setup = ws_mcp_oauth_setup($server, $manual, null, (int) $viewer['id']);

        if (!$setup['ok']) {
            $liveform->add_error(lang(array('string' => 'The sign-in to @{var:1} could not be set up: {var:2}', 'vars' => array($server['alias'], $setup['error']))));
            return;
        }
    }

    $account = ws_mcp_site_account($server);
    $start = pg_connect_start($key, 'mcp', 'site', $viewer,
        'workspace_settings.php?mcp_connected=' . (int) $server['id'] . '#ws-mcp-' . (int) $server['id'],
        $account ? (int) $account['id'] : 0);

    if (!$start['ok']) {
        $liveform->add_error($start['error']);
        return;
    }

    header('Location: ' . $start['url']);
    exit();
}

/**
 * Ends a person's own connection to a server (the service is told where it
 * can be).
 *
 * @param array $viewer
 * @param array $server
 * @return bool whether there was one
 */
function ws_mcp_person_disconnect($viewer, $server)
{
    $mine = ws_mcp_person_account($server, (int) $viewer['id']);

    if (!$mine) {
        return false;
    }

    pg_connect_disconnect($mine['id']);

    log_activity(lang(array('string' => 'ended their own connection to the MCP server @{var:1}', 'vars' => $server['alias'])), (string) ($_SESSION['sessionusername'] ?? ''));

    // The cards of the calls that waited for this account are drawn again.
    if (function_exists('ws_message_touch') && function_exists('ws_changes_ready') && ws_changes_ready()) {
        foreach ((array) db_values("SELECT DISTINCT c.message_id FROM ws_ai_changes c
            INNER JOIN ws_mcp_calls m ON m.id = c.record_id
            WHERE c.record_type = 'mcp_call' AND c.status = 'pending' AND c.requested_by = '" . (int) $viewer['id'] . "'
                AND m.server_id = '" . (int) $server['id'] . "'") as $message_id) {
            ws_message_touch((int) $message_id);
        }
    }

    return true;
}

/**
 * Back on the settings screen from the service (connect.php): its message,
 * and the server's tools read at once when an administrator is now connected.
 *
 * @param array    $viewer
 * @param liveform $liveform
 */
function ws_mcp_settings_returned($viewer, $liveform)
{
    $id = (int) ($_GET['mcp_connected'] ?? 0);
    $flash = ($id > 0) ? pg_connect_flash_take() : null;

    if (!$flash) {
        return;
    }

    if ($flash['kind'] === 'error') {
        $liveform->add_error($flash['text']);
        return;
    }

    $liveform->add_notice($flash['text']);

    $server = ws_mcp_server($id);

    if (!$server || ((int) $viewer['role'] !== 0) || ($server['auth_kind'] !== 'oauth')) {
        return;
    }

    $refresh = ws_mcp_refresh_tools($server, (int) $viewer['id']);

    if ($refresh['ok']) {
        $liveform->add_notice(lang(array('string' => '@{var:1} answered with {var:2} tools. Turn on the ones that may be used.', 'vars' => array($server['alias'], $refresh['count']))));
    } else {
        $liveform->add_error(lang(array('string' => '@{var:1} could not be reached: {var:2}', 'vars' => array($server['alias'], $refresh['error']))));
    }
}

/**
 * The form a server is added or changed with.
 *
 * @param string     $self_url
 * @param array|null $server
 * @param bool       $admin
 * @return string
 */
function ws_mcp_settings_form($self_url, $server, $admin)
{
    $id = $server ? (int) $server['id'] : 0;
    $prefix = 'ws_mcp_' . ($id ?: 'new') . '_';
    $kind = $server ? (string) $server['auth_kind'] : 'none';
    $secret = $server ? ws_mcp_secret_decode($server['secret'] ?? '') : array();
    $disabled = $admin ? '' : ' disabled';
    $kept = $server ? ' placeholder="' . h(lang('Kept: leave empty to keep it')) . '"' : '';
    $app = ($server && ($kind === 'oauth') && function_exists('pg_connect_app')) ? pg_connect_app(ws_mcp_connect_key($server)) : null;
    $manual = is_array($app) && (($app['options']['registered'] ?? '') === 'manual');

    $options = '';

    foreach (ws_mcp_auth_kinds() as $key => $label) {
        $options .= '<option value="' . h($key) . '"' . (($key === $kind) ? ' selected' : '') . '>' . h($label) . '</option>';
    }

    return '
        <form method="post" action="' . h($self_url) . '" class="ws-dept-form" data-ws-mcp-form>
            ' . get_token_field() . '
            <input type="hidden" name="ws_action" value="mcp_save">
            <input type="hidden" name="mcp_id" value="' . $id . '">
            <div class="row g-2">
                <div class="col-sm-4">
                    <label class="form-label small" for="' . $prefix . 'alias">' . h(lang('Alias')) . '</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text">@</span>
                        <input class="form-control" type="text" name="mcp_alias" id="' . $prefix . 'alias" value="' . h($server ? $server['alias'] : '') . '" maxlength="32" pattern="[A-Za-z][A-Za-z0-9_\-]{1,31}" required autocomplete="off"' . $disabled . '>
                    </div>
                </div>
                <div class="col-sm-8">
                    <label class="form-label small" for="' . $prefix . 'name">' . h(lang('Name')) . '</label>
                    <input class="form-control form-control-sm" type="text" name="mcp_name" id="' . $prefix . 'name" value="' . h($server ? $server['name'] : '') . '" maxlength="100" placeholder="' . h(lang('GitHub, the documentation, …')) . '"' . $disabled . '>
                </div>
                <div class="col-12">
                    <label class="form-label small" for="' . $prefix . 'url">' . h(lang('Server address')) . '</label>
                    <input class="form-control form-control-sm" type="url" name="mcp_url" id="' . $prefix . 'url" value="' . h($server ? $server['url'] : '') . '" maxlength="500" placeholder="https://" required autocomplete="off"' . $disabled . '>
                    <div class="form-text">' . h(lang('The address of the service\'s remote MCP server (Streamable HTTP), as its documentation gives it. Servers that run on a computer (npx, stdio) cannot be reached from a website.')) . '</div>
                </div>
                <div class="col-sm-5">
                    <label class="form-label small" for="' . $prefix . 'auth">' . h(lang('How the site signs in')) . '</label>
                    <select class="form-select form-select-sm" name="mcp_auth" id="' . $prefix . 'auth" data-ws-mcp-auth' . $disabled . '>' . $options . '</select>
                </div>
                <div class="col-sm-7' . (($kind === 'bearer') ? '' : ' d-none') . '" data-ws-mcp-auth-for="bearer">
                    <label class="form-label small" for="' . $prefix . 'token">' . h(lang('Access token')) . '</label>
                    <input class="form-control form-control-sm" type="password" name="mcp_token" id="' . $prefix . 'token" autocomplete="new-password"' . (($kind === 'bearer') ? $kept : '') . $disabled . '>
                </div>
                <div class="col-sm-7' . (($kind === 'header') ? '' : ' d-none') . '" data-ws-mcp-auth-for="header">
                    <div class="row g-2">
                        <div class="col-5">
                            <label class="form-label small" for="' . $prefix . 'header_name">' . h(lang('Header')) . '</label>
                            <input class="form-control form-control-sm" type="text" name="mcp_header_name" id="' . $prefix . 'header_name" value="' . h((string) ($secret['name'] ?? '')) . '" maxlength="64" placeholder="X-API-Key" autocomplete="off"' . $disabled . '>
                        </div>
                        <div class="col-7">
                            <label class="form-label small" for="' . $prefix . 'header_value">' . h(lang('Value')) . '</label>
                            <input class="form-control form-control-sm" type="password" name="mcp_header_value" id="' . $prefix . 'header_value" autocomplete="new-password"' . (($kind === 'header') ? $kept : '') . $disabled . '>
                        </div>
                    </div>
                </div>
                <div class="col-sm-7' . (($kind === 'basic') ? '' : ' d-none') . '" data-ws-mcp-auth-for="basic">
                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label small" for="' . $prefix . 'user">' . h(lang('User name')) . '</label>
                            <input class="form-control form-control-sm" type="text" name="mcp_user" id="' . $prefix . 'user" value="' . h((string) ($secret['user'] ?? '')) . '" autocomplete="off"' . $disabled . '>
                        </div>
                        <div class="col-6">
                            <label class="form-label small" for="' . $prefix . 'password">' . h(lang('Password')) . '</label>
                            <input class="form-control form-control-sm" type="password" name="mcp_password" id="' . $prefix . 'password" autocomplete="new-password"' . (($kind === 'basic') ? $kept : '') . $disabled . '>
                        </div>
                    </div>
                </div>
                <div class="col-sm-7' . (($kind === 'oauth') ? '' : ' d-none') . '" data-ws-mcp-auth-for="oauth">
                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label small" for="' . $prefix . 'client_id">' . h(lang('Client ID (if the service needs one)')) . '</label>
                            <input class="form-control form-control-sm" type="text" name="mcp_client_id" id="' . $prefix . 'client_id" value="' . h($manual ? $app['client_id'] : '') . '" maxlength="255" autocomplete="off"' . $disabled . '>
                        </div>
                        <div class="col-6">
                            <label class="form-label small" for="' . $prefix . 'client_secret">' . h(lang('Client secret')) . '</label>
                            <input class="form-control form-control-sm" type="password" name="mcp_client_secret" id="' . $prefix . 'client_secret" autocomplete="new-password"' . (($manual && $app['secret_stored']) ? $kept : '') . $disabled . '>
                        </div>
                    </div>
                    <div class="form-text">' . h(lang(array('string' => 'Most services register the site on their own when it connects. A service that wants an application of its own: register one in its developer settings with the redirect address {var:1} and enter its client ID here, and its secret if it gave one.', 'vars' => function_exists('pg_connect_redirect_uri') ? pg_connect_redirect_uri() : ''))) . '</div>
                </div>
                <div class="col-12">
                    <div class="form-text">' . h(lang('The credential is kept encrypted with the site\'s key and is never shown again. Everybody who may ask the alias works with this one account on the other service, so give it the least it needs, read-only where the service offers that.')) . '</div>
                </div>
            </div>
            ' . ($admin ? '<button type="submit" class="btn btn-sm btn-primary rounded-pill px-3 mt-3"><i class="bi bi-check2 me-1" aria-hidden="true"></i>' . h($server ? lang('Save') : lang('Add and connect')) . '</button>' : '') . '
        </form>';
}

/**
 * A server's tools, each with its switch.
 *
 * @param string $self_url
 * @param array  $server
 * @param bool   $admin
 * @return string
 */
function ws_mcp_settings_tools($self_url, $server, $admin)
{
    $tools = ws_mcp_tools($server);

    if (empty($tools)) {
        return '<div class="ws-empty py-3"><i class="bi bi-tools" aria-hidden="true"></i>' . h((int) $server['tools_at'] > 0
            ? lang('The server lists no tools.')
            : lang('The tools have not been read yet. Ask the server for them with the button above.')) . '</div>';
    }

    $rules = ws_mcp_tool_rules($server);
    $on = ws_mcp_tools_on($server);
    $approve = ws_mcp_can_approve($server);
    $rows = '';

    foreach ($tools as $name => $tool) {
        $can = ws_mcp_tool_can_run($tool);
        $field = 'ws_mcp_' . (int) $server['id'] . '_tool_' . substr(md5($name), 0, 10);
        $value = '1';
        $choice = false;

        if ($can === 'read') {
            $badge = '<span class="badge text-bg-success-subtle text-success-emphasis">' . h(lang('Reads only')) . '</span>';
            $hint = '';
        } elseif ($can === 'declare') {
            $badge = '<span class="badge text-bg-warning-subtle text-warning-emphasis">' . h(lang('Not said')) . '</span>';
            $hint = $approve
                ? lang('The server does not say whether this tool changes anything. Choose "Reads only" only if you know it does; otherwise each call waits for the approval of the person who asked.')
                : lang('The server does not say whether this tool changes anything. Turn it on only if you know it only reads.');
            $choice = $approve;
        } else {
            $badge = '<span class="badge text-bg-danger-subtle text-danger-emphasis">' . h((($tool['hints']['destructiveHint'] ?? null) === true) ? lang('Can delete') : lang('Writes')) . '</span>';
            $hint = $approve
                ? lang('It changes things on the other service. Each call waits for the approval of the person who asked and runs with their own account.')
                : lang('It changes things on the other service. With one connection for everybody it stays off.');
            $value = 'approve';
        }

        $description = (string) $tool['description'];

        // A tool the server says nothing about, on a server people sign in
        // to: off, reads only, or asks first.
        if ($choice) {
            $current = isset($on[$name]) ? (string) ($rules[$name] ?? '') : '';
            $control = '
                <select class="form-select form-select-sm w-auto mt-1" name="mcp_tool[' . h($name) . ']" id="' . $field . '" aria-label="' . h($name) . '"' . ($admin ? '' : ' disabled') . '>
                    <option value="">' . h(lang('Off')) . '</option>
                    <option value="auto_declared"' . (($current === 'auto_declared') ? ' selected' : '') . '>' . h(lang('Reads only')) . '</option>
                    <option value="approve"' . (($current === 'approve') ? ' selected' : '') . '>' . h(lang('Ask before each call')) . '</option>
                </select>';
        } else {
            $control = '
                <div class="form-check form-switch mt-1">
                    <input class="form-check-input" type="checkbox" name="mcp_tool[' . h($name) . ']" value="' . $value . '" id="' . $field . '"'
                        . (isset($on[$name]) ? ' checked' : '') . (((($can === '') && !$approve) || !$admin) ? ' disabled' : '') . '>
                </div>';
        }

        $rows .= '
            <li class="ws-dept-row align-items-start" data-ws-mcp-tool="' . h(mb_strtolower($name . ' ' . $tool['title'] . ' ' . $description)) . '"' . (($can === 'read') ? ' data-ws-mcp-reads' : '') . '>
                ' . $control . '
                <label class="ws-grow" for="' . $field . '">
                    <span class="fw-semibold"><code>' . h($name) . '</code></span>
                    ' . (($tool['title'] !== '') ? '<span class="text-body-secondary">· ' . h($tool['title']) . '</span>' : '') . '
                    ' . $badge . '
                    ' . (!empty($on[$name]['approve']) ? '<span class="badge text-bg-primary-subtle text-primary-emphasis">' . h(lang('Asks first')) . '</span>' : '') . '
                    ' . (($description !== '') ? '<span class="d-block small text-body-secondary">' . h(mb_strimwidth($description, 0, 240, '…')) . '</span>' : '') . '
                    ' . (($hint !== '') ? '<span class="d-block small text-body-secondary fst-italic">' . h($hint) . '</span>' : '') . '
                </label>
            </li>';
    }

    return '
        <form method="post" action="' . h($self_url) . '">
            ' . get_token_field() . '
            <input type="hidden" name="ws_action" value="mcp_tools">
            <input type="hidden" name="mcp_id" value="' . (int) $server['id'] . '">
            ' . (function_exists('ws_mcp_agent_settings_field') ? ws_mcp_agent_settings_field($server, $admin) : '') . '
            <div class="d-flex align-items-center gap-2 mb-2">
                <div class="small fw-semibold ws-grow">' . h(lang(array('string' => 'Tools ({var:1} of {var:2} turned on)', 'vars' => array(count($on), count($tools)))))
                    . ((defined('WS_MCP_ASK_MAX_TOOLS') && (count($on) > WS_MCP_ASK_MAX_TOOLS)) ? '<span class="d-block fw-normal text-warning-emphasis">' . h(lang(array('string' => 'Pinegrap AI is offered the first {var:1} of them, in alphabetical order.', 'vars' => WS_MCP_ASK_MAX_TOOLS))) . '</span>' : '') . '</div>
                ' . ($admin ? '<button type="submit" class="btn btn-sm btn-primary rounded-pill px-3"><i class="bi bi-check2 me-1" aria-hidden="true"></i>' . h(lang('Save')) . '</button>' : '') . '
            </div>
            <div class="d-flex align-items-center gap-2 mb-2">
                <input class="form-control form-control-sm ws-grow" type="search" placeholder="' . h(lang('Filter the tools')) . '" aria-label="' . h(lang('Filter the tools')) . '" data-ws-mcp-filter>
                ' . ($admin ? '<button type="button" class="btn btn-sm btn-outline-secondary text-nowrap" data-ws-mcp-all-read><i class="bi bi-check2-all me-1" aria-hidden="true"></i>' . h(lang('Every tool that reads only')) . '</button>' : '') . '
            </div>
            <ul class="list-unstyled ws-mcp-tools mb-0">' . $rows . '</ul>
        </form>';
}

/**
 * The card on the settings screen.
 *
 * @param string $self_url
 * @param array  $viewer
 * @return string
 */
function ws_mcp_settings_card($self_url, $viewer)
{
    if (!ws_mcp_ready()) {
        return '';
    }

    $admin = ((int) $viewer['role'] === 0);
    $servers = ws_mcp_servers(true);
    $list = '';
    $agents = false;

    foreach ($servers as $server) {
        $id = (int) $server['id'];
        $on = ws_mcp_tools_on($server);
        $tools = ws_mcp_tools($server);
        $oauth = ($server['auth_kind'] === 'oauth');
        $account = $oauth ? ws_mcp_site_account($server) : null;
        $connected = $account && ($account['status'] === 'active');
        $mine = $oauth ? ws_mcp_person_account($server, (int) $viewer['id']) : null;
        $people = 0;

        foreach ($oauth ? pg_connect_accounts(array('provider' => ws_mcp_connect_key($server))) : array() as $connection) {
            if (((int) $connection['user_id'] > 0) && ($connection['status'] === 'active')) {
                $people++;
            }
        }

        if ($server['status'] !== 'active') {
            $state = '<span class="badge text-bg-secondary">' . h(lang('Off')) . '</span>';
        } elseif ($oauth && $account && !$connected) {
            $state = '<span class="badge text-bg-danger">' . h(lang('Connect again')) . '</span>';
        } elseif ($oauth && !$account) {
            $state = '<span class="badge text-bg-warning">' . h(lang('Not connected')) . '</span>';
        } elseif ($server['last_error'] !== '') {
            $state = '<span class="badge text-bg-danger">' . h(lang('Cannot be reached')) . '</span>';
        } elseif (empty($on)) {
            $state = '<span class="badge text-bg-warning">' . h(lang('No tool on')) . '</span>';
        } else {
            $state = '<span class="badge text-bg-success">' . h(lang('Ready')) . '</span>';
        }

        $details = array(ws_mcp_host($server['url']));

        if ($server['server_info'] !== '') {
            $details[] = $server['server_info'];
        }

        if ($connected) {
            $details[] = lang(array('string' => 'connected {var:1}', 'vars' => ws_time_label($account['refreshed_timestamp'] ?: $account['created_timestamp'])));
        }

        if ($people > 0) {
            $details[] = lang(array('string' => '{var:1} people with their own account', 'vars' => $people));
        }

        $details[] = lang(array('string' => '{var:1} of {var:2} tools on', 'vars' => array(count($on), count($tools))));

        // A server that is an agent itself answers its alias on its own.
        // Each message of one whose tool writes waits for an approval first.
        if (function_exists('ws_mcp_agent_mode') && ws_mcp_agent_ready() && ws_mcp_agent_mode($server)) {
            $asks = !empty($on[(string) $server['agent_tool']]['approve']);
            $details[] = lang(array('string' => $asks ? 'answers on its own through {var:1}, each message approved first' : 'answers on its own through {var:1}', 'vars' => (string) $server['agent_tool']));
            $agents = true;
        }

        // The site's sign-in: Connect while it has none or it stopped working,
        // Disconnect while it works.
        $sign_in = '';

        if ($admin && $oauth && !$connected) {
            $sign_in = '
                <form method="post" action="' . h($self_url) . '" class="d-inline">
                    ' . get_token_field() . '
                    <input type="hidden" name="ws_action" value="mcp_connect">
                    <input type="hidden" name="mcp_id" value="' . $id . '">
                    <button type="submit" class="btn btn-sm btn-primary rounded-pill px-3"><i class="bi bi-box-arrow-in-right me-1" aria-hidden="true"></i>' . h($account ? lang('Connect again') : lang('Connect')) . '</button>
                </form>';
        } elseif ($admin && $account) {
            $sign_in = '
                <form method="post" action="' . h($self_url) . '" class="d-inline" onsubmit="return confirm(' . h(json_encode(lang('End the site\'s connection to this server? Its tools cannot be used until it is connected again.'))) . ');">
                    ' . get_token_field() . '
                    <input type="hidden" name="ws_action" value="mcp_disconnect">
                    <input type="hidden" name="mcp_id" value="' . $id . '">
                    <button type="submit" class="btn btn-sm btn-ghost" title="' . h(lang('Disconnect')) . '"><i class="bi bi-x-circle me-1" aria-hidden="true"></i>' . h(lang('Disconnect')) . '</button>
                </form>';
        }

        if ((int) $server['tools_at'] > 0) {
            $details[] = lang(array('string' => 'read {var:1}', 'vars' => ws_time_label($server['tools_at'])));
        }

        // The viewer's own account: what the tools that ask first run with,
        // and what the tools that read use before the site's.
        $own = '';

        if ($oauth && ($server['status'] === 'active')) {
            $own_works = $mine && ($mine['status'] === 'active');
            $own = '
                <div class="small d-flex flex-wrap align-items-center gap-2 mt-1">
                    <span class="text-body-secondary">' . h($own_works
                        ? lang(array('string' => 'Your own account is connected ({var:1}).', 'vars' => ws_time_label($mine['refreshed_timestamp'] ?: $mine['created_timestamp'])))
                        : ($mine ? lang('Your own connection ended.') : lang('Your own account is not connected.'))) . '</span>
                    <form method="post" action="' . h($self_url) . '" class="d-inline">
                        ' . get_token_field() . '
                        <input type="hidden" name="ws_action" value="' . ($own_works ? 'mcp_disconnect_me' : 'mcp_connect_me') . '">
                        <input type="hidden" name="mcp_id" value="' . $id . '">
                        <button type="submit" class="btn btn-link btn-sm p-0 align-baseline">' . h($own_works ? lang('Disconnect my account') : ($mine ? lang('Connect my account again') : lang('Connect my account'))) . '</button>
                    </form>
                </div>';
        }

        $list .= '
            <div class="ws-dept-row' . (($server['status'] !== 'active') ? ' opacity-75' : '') . '" id="ws-mcp-' . $id . '">
                <i class="bi bi-plug" aria-hidden="true"></i>
                <div class="ws-grow">
                    <div class="fw-semibold">@' . h($server['alias']) . (($server['name'] !== '') ? ' <span class="fw-normal text-body-secondary">· ' . h($server['name']) . '</span>' : '') . ' ' . $state . '</div>
                    <div class="small text-body-secondary">' . h(implode(' · ', $details)) . '</div>
                    ' . (($server['last_error'] !== '') ? '<div class="small text-danger">' . h($server['last_error']) . '</div>' : '') . '
                    ' . $own . '
                </div>
                ' . $sign_in . '
                <button type="button" class="btn btn-sm btn-ghost" data-bs-toggle="collapse" data-bs-target="#ws-mcp-edit-' . $id . '" aria-expanded="false"><i class="bi bi-sliders me-1" aria-hidden="true"></i>' . h(lang('Tools and settings')) . '</button>
                ' . ($admin ? '
                <form method="post" action="' . h($self_url) . '" class="d-inline">
                    ' . get_token_field() . '
                    <input type="hidden" name="ws_action" value="mcp_refresh">
                    <input type="hidden" name="mcp_id" value="' . $id . '">
                    <button type="submit" class="btn btn-sm btn-ghost" title="' . h(lang('Ask the server for its tools again')) . '"><i class="bi bi-arrow-clockwise" aria-hidden="true"></i></button>
                </form>
                <form method="post" action="' . h($self_url) . '" class="d-inline">
                    ' . get_token_field() . '
                    <input type="hidden" name="ws_action" value="mcp_toggle">
                    <input type="hidden" name="mcp_id" value="' . $id . '">
                    <button type="submit" class="btn btn-sm btn-ghost" title="' . h(($server['status'] === 'active') ? lang('Turn off') : lang('Turn on')) . '"><i class="bi ' . (($server['status'] === 'active') ? 'bi-pause-circle' : 'bi-play-circle') . '" aria-hidden="true"></i></button>
                </form>
                <form method="post" action="' . h($self_url) . '" class="d-inline" onsubmit="return confirm(' . h(json_encode(lang('Remove this MCP server and its credential?'))) . ');">
                    ' . get_token_field() . '
                    <input type="hidden" name="ws_action" value="mcp_delete">
                    <input type="hidden" name="mcp_id" value="' . $id . '">
                    <button type="submit" class="btn btn-sm btn-ghost" title="' . h(lang('Remove')) . '"><i class="bi bi-trash" aria-hidden="true"></i></button>
                </form>' : '') . '
            </div>
            <div class="collapse" id="ws-mcp-edit-' . $id . '">
                <div class="row g-3 py-2">
                    <div class="col-lg-6">' . ws_mcp_settings_form($self_url, $server, $admin) . '</div>
                    <div class="col-lg-6">' . ws_mcp_settings_tools($self_url, $server, $admin) . '</div>
                </div>
            </div>';
    }

    if ($list === '') {
        $list = '<div class="ws-empty py-3"><i class="bi bi-plug" aria-hidden="true"></i>' . h(lang('No MCP server yet.')) . '</div>';
    }

    return '
        <div class="card mt-4" id="ws-mcp">
            <div class="card-header d-flex align-items-center gap-2">
                <h2 class="h6 mb-0 ws-grow"><i class="bi bi-plug me-1" aria-hidden="true"></i>' . h(lang('MCP servers')) . '</h2>
                <a class="btn btn-sm btn-ghost" href="workspace_mcp.php" title="' . h(lang('Your own accounts in one place, and the tools that read run with a form')) . '"><i class="bi bi-person-gear me-1" aria-hidden="true"></i>' . h(lang('My MCP connections')) . '</a>
                ' . ($admin ? '<button type="button" class="btn btn-sm btn-primary rounded-pill px-3" data-bs-toggle="collapse" data-bs-target="#ws-mcp-new"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>' . h(lang('Add a server')) . '</button>' : '') . '
            </div>
            <div class="card-body">
                <p class="small text-body-secondary">' . h(lang('Connect the tools of other services - a code host, a project tracker, a documentation site - under an alias such as @github. Each tool stays off until you turn it on here; only tools that read can be turned on.'))
                    . ' ' . h(lang('Written in a channel where Pinegrap AI may be asked, the alias is answered by Pinegrap AI with the server\'s tools.'))
                    . ((function_exists('ws_mcp_agent_ready') && ws_mcp_agent_ready()) ? ' ' . h(lang('A server that is an agent itself can answer on its own, without Pinegrap AI: choose the tool that takes the messages under Tools and settings.')) : '')
                    . ' ' . h(lang('On a server people sign in to (OAuth), everybody may connect their own account, and tools that write can be turned on too: each call waits under the answer until the person who asked approves it, and runs with their own account.')) . '</p>
                ' . ((function_exists('ws_ai_ready') && !ws_ai_ready()) ? '<div class="alert alert-warning small py-2">' . h($agents
                    ? lang('Pinegrap AI is not connected yet, so only the aliases of servers that answer on their own can be asked. Connect it on the Pinegrap AI card.')
                    : lang('Pinegrap AI is not connected yet, so the aliases cannot be asked. Connect it on the Pinegrap AI card.')) . '</div>' : '') . '
                ' . ($admin ? '<div class="collapse mb-3" id="ws-mcp-new">' . ws_mcp_settings_form($self_url, null, true) . '</div>' : '<div class="form-text mb-2">' . h(lang('Only an administrator can add MCP servers.')) . '</div>') . '
                ' . $list . '
            </div>
        </div>
        <script>
        document.addEventListener(\'input\', function (event) {
            var field = event.target;

            if (!field.matches || !field.matches(\'[data-ws-mcp-filter]\')) {
                return;
            }

            var words = field.value.toLocaleLowerCase().trim();

            field.closest(\'form\').querySelectorAll(\'[data-ws-mcp-tool]\').forEach(function (row) {
                row.classList.toggle(\'d-none\', words !== \'\' && row.getAttribute(\'data-ws-mcp-tool\').indexOf(words) === -1);
            });
        });

        document.addEventListener(\'click\', function (event) {
            var button = event.target.closest ? event.target.closest(\'[data-ws-mcp-all-read]\') : null;

            if (!button) {
                return;
            }

            button.closest(\'form\').querySelectorAll(\'[data-ws-mcp-reads] input[type=checkbox]:not([disabled])\').forEach(function (box) {
                box.checked = true;
            });
        });

        document.addEventListener(\'change\', function (event) {
            var select = event.target;

            if (!select.matches || !select.matches(\'[data-ws-mcp-auth]\')) {
                return;
            }

            select.closest(\'form\').querySelectorAll(\'[data-ws-mcp-auth-for]\').forEach(function (part) {
                part.classList.toggle(\'d-none\', part.getAttribute(\'data-ws-mcp-auth-for\') !== select.value);
            });
        });
        </script>';
}
