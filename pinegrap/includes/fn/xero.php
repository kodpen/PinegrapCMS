<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The site's Xero organisation: the connection made on Xero's page of
 * Connections (connections.php?service=xero) through the site's own Xero
 * application (includes/connect/providers/xero.php), the organisation it
 * reaches and the three accounts the site's sales will be booked to.
 *
 * After the sign-in, GET https://api.xero.com/connections lists the
 * organisations the application may reach; the one just allowed is told
 * apart by the authentication_event_id of the access token
 * (xero_after_connect()). Xero answers every accounting call for the
 * organisation named in the xero-tenant-id header.
 *
 * Nothing has a table or a column of its own: the organisation, the list
 * of organisations and the chosen accounts are one sealed value in
 * connect_accounts as the service entry "xero" (pg_connect_service_get/
 * put()), typed by xero_state_parse(). Without the encryption key nothing
 * can be connected, so nothing is stored either.
 *
 * Xero is called after the sign-in, for the chart of accounts the page
 * offers, and when the chosen accounts are saved. The pure parts are tested
 * in tests/xero_test.php.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

if (!defined('PG_XERO_CONNECTIONS_URL')) {
    define('PG_XERO_CONNECTIONS_URL', 'https://api.xero.com/connections');
}

if (!defined('PG_XERO_API')) {
    define('PG_XERO_API', 'https://api.xero.com/api.xro/2.0');
}

// The most organisations one sealed entry keeps; Xero's Starter tier
// allows five connections.
if (!defined('PG_XERO_TENANTS_KEPT')) {
    define('PG_XERO_TENANTS_KEPT', 50);
}

/* ---------------------------------------------------------------------------
   Pure parts
   --------------------------------------------------------------------------- */

/**
 * Whether a value is a Xero identifier: a UUID as Xero writes them. Pure.
 *
 * @param mixed $id
 * @return bool
 */
function xero_uuid_valid($id)
{
    return is_string($id) && (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/iD', $id) === 1);
}

/**
 * The authentication_event_id an access token carries: the sign-in it came
 * from, which the organisations allowed in that sign-in share as their
 * authEventId. Read without checking the signature, as the token came
 * straight from Xero's token endpoint. Pure.
 *
 * @param string $jwt the access token
 * @return string the identifier, '' when the token names none
 */
function xero_token_auth_event($jwt)
{
    $claims = pg_google_jwt_payload((string) $jwt);
    $event = (is_array($claims) && isset($claims['authentication_event_id']) && is_string($claims['authentication_event_id']))
        ? strtolower($claims['authentication_event_id']) : '';

    return xero_uuid_valid($event) ? $event : '';
}

/**
 * The organisations of a GET /connections answer. A practice (Xero HQ) has
 * no books and is left out. Pure.
 *
 * @param mixed  $data  the decoded answer: a list of connections
 * @param string $event the sign-in's authentication_event_id, '' if unknown
 * @return array tenant id => ['name' => string, 'fresh' => bool, allowed in
 *               that sign-in]
 */
function xero_tenants_from_api($data, $event)
{
    $tenants = array();

    foreach (is_array($data) ? $data : array() as $connection) {
        if (!is_array($connection) || !isset($connection['tenantId']) || !xero_uuid_valid($connection['tenantId'])) {
            continue;
        }

        if (!isset($connection['tenantType']) || (strtoupper((string) $connection['tenantType']) !== 'ORGANISATION')) {
            continue;
        }

        $id = strtolower($connection['tenantId']);
        $name = isset($connection['tenantName']) && is_scalar($connection['tenantName']) ? pg_connect_clip((string) $connection['tenantName']) : '';

        $tenants[$id] = array(
            'name'  => ($name !== '') ? $name : $id,
            'fresh' => ($event !== '') && isset($connection['authEventId']) && is_string($connection['authEventId'])
                && (strtolower($connection['authEventId']) === $event),
        );

        if (count($tenants) >= PG_XERO_TENANTS_KEPT) {
            break;
        }
    }

    return $tenants;
}

/**
 * The organisation the site uses after a sign-in: the one chosen before
 * while the application still reaches it, so the books never change
 * without being asked; else the only one allowed in this sign-in; else the
 * only one there is; else none, and the page asks. Pure.
 *
 * @param array  $tenants xero_tenants_from_api()
 * @param string $current the tenant id chosen before, '' for none
 * @return string tenant id, '' when one must be chosen
 */
function xero_tenant_pick($tenants, $current)
{
    $tenants = is_array($tenants) ? $tenants : array();

    if (($current !== '') && isset($tenants[$current])) {
        return $current;
    }

    $fresh = array_keys(array_filter($tenants, function ($tenant) {
        return !empty($tenant['fresh']);
    }));

    if (count($fresh) === 1) {
        return $fresh[0];
    }

    return (count($tenants) === 1) ? (string) key($tenants) : '';
}

/**
 * The accounts of a GET /Accounts answer, typed. Pure.
 *
 * @param mixed $data the decoded answer
 * @return array AccountID => id, code, name, type, class, status, payments
 *               (EnablePaymentsToAccount), in Xero's order
 */
function xero_accounts_from_api($data)
{
    $accounts = array();
    $list = (is_array($data) && isset($data['Accounts']) && is_array($data['Accounts'])) ? $data['Accounts'] : array();

    foreach ($list as $account) {
        if (!is_array($account) || !isset($account['AccountID']) || !xero_uuid_valid($account['AccountID'])) {
            continue;
        }

        $id = strtolower($account['AccountID']);
        $text = function ($key) use ($account) {
            return isset($account[$key]) && is_scalar($account[$key]) ? trim((string) $account[$key]) : '';
        };

        $accounts[$id] = array(
            'id'       => $id,
            'code'     => mb_substr($text('Code'), 0, 10),
            'name'     => mb_substr($text('Name'), 0, 150),
            'type'     => strtoupper($text('Type')),
            'class'    => strtoupper($text('Class')),
            'status'   => strtoupper($text('Status')),
            'payments' => !empty($account['EnablePaymentsToAccount']) && ($account['EnablePaymentsToAccount'] !== 'false'),
        );
    }

    return $accounts;
}

/**
 * The roles an account is chosen for: income (the revenue account sales
 * lines are booked to), clearing (where payments taken online wait for the
 * payout) and bank (where payments by transfer, cash or on delivery go).
 * Pure.
 *
 * @return array role keys
 */
function xero_account_roles()
{
    return array('income', 'clearing', 'bank');
}

/**
 * Whether an account may hold a role. Only an active account: income in a
 * revenue account; a payment in a bank account or one that takes payments
 * (EnablePaymentsToAccount), as POST /Payments asks. Pure.
 *
 * @param string $role    income, clearing or bank
 * @param array  $account xero_accounts_from_api()'s item
 * @return bool
 */
function xero_account_fits($role, $account)
{
    if (!is_array($account) || (($account['status'] ?? '') !== 'ACTIVE')) {
        return false;
    }

    if ($role === 'income') {
        return (($account['class'] ?? '') === 'REVENUE') || in_array($account['type'] ?? '', array('REVENUE', 'SALES', 'OTHERINCOME'), true);
    }

    if ($role === 'clearing') {
        return (($account['type'] ?? '') === 'BANK') || !empty($account['payments']);
    }

    if ($role === 'bank') {
        return ($account['type'] ?? '') === 'BANK';
    }

    return false;
}

/**
 * The accounts that may hold a role, by their label. Pure.
 *
 * @param array  $accounts xero_accounts_from_api()
 * @param string $role
 * @return array AccountID => label
 */
function xero_accounts_for_role($accounts, $role)
{
    $offered = array();

    foreach (is_array($accounts) ? $accounts : array() as $id => $account) {
        if (xero_account_fits($role, $account)) {
            $offered[$id] = xero_account_label($account);
        }
    }

    asort($offered, SORT_NATURAL | SORT_FLAG_CASE);

    return $offered;
}

/**
 * An account as the page names it: "200 — Sales", or the name alone when it
 * has no code (a bank account may have none). Pure.
 *
 * @param array $account id, code, name
 * @return string
 */
function xero_account_label($account)
{
    $code = is_array($account) && isset($account['code']) ? trim((string) $account['code']) : '';
    $name = is_array($account) && isset($account['name']) ? trim((string) $account['name']) : '';

    if ($name === '') {
        $name = is_array($account) && isset($account['id']) ? (string) $account['id'] : '';
    }

    return ($code !== '') ? $code . ' — ' . $name : $name;
}

/**
 * Xero's own words for a refused call: the accounting API's Message and
 * validation errors, or the problem details' Detail and Title. Pure.
 *
 * @param int    $status   the HTTP status
 * @param mixed  $data     the decoded answer
 * @param string $fallback what the transport said, used when Xero said
 *                         nothing
 * @return string at most 250 characters
 */
function xero_error_message($status, $data, $fallback)
{
    $words = array();

    if (is_array($data)) {
        foreach (array('Message', 'Detail', 'Title', 'message', 'error_description', 'error') as $key) {
            if (isset($data[$key]) && is_string($data[$key]) && (trim($data[$key]) !== '')) {
                $words[] = trim($data[$key]);
                break;
            }
        }

        foreach ((isset($data['Elements']) && is_array($data['Elements'])) ? $data['Elements'] : array() as $element) {
            foreach ((is_array($element) && isset($element['ValidationErrors']) && is_array($element['ValidationErrors'])) ? $element['ValidationErrors'] : array() as $error) {
                if (is_array($error) && isset($error['Message']) && is_string($error['Message']) && !in_array(trim($error['Message']), $words, true)) {
                    $words[] = trim($error['Message']);
                }
            }
        }
    }

    if (!$words) {
        $words[] = ((string) $fallback !== '') ? (string) $fallback : 'HTTP ' . (int) $status;
    } elseif ((int) $status > 0) {
        array_unshift($words, 'HTTP ' . (int) $status . ':');
    }

    return pg_connect_clip(implode(' ', $words));
}

/**
 * A chosen account as it is kept: its id, code and name, or empty strings
 * when none is chosen. Pure.
 *
 * @param mixed $stored
 * @return array id, code, name
 */
function xero_account_kept($stored)
{
    $kept = array('id' => '', 'code' => '', 'name' => '');

    if (is_array($stored) && isset($stored['id']) && xero_uuid_valid($stored['id'])) {
        $kept['id'] = strtolower($stored['id']);

        foreach (array('code' => 10, 'name' => 150) as $key => $length) {
            $kept[$key] = isset($stored[$key]) && is_scalar($stored[$key]) ? mb_substr(trim((string) $stored[$key]), 0, $length) : '';
        }
    }

    return $kept;
}

/**
 * The stored state, every key present and typed. Pure.
 *
 * @param mixed $stored
 * @return array connection_id, tenant_id, tenant_name, tenants (id =>
 *               name), accounts (income, clearing, bank: id, code, name),
 *               connected_at
 */
function xero_state_parse($stored)
{
    $state = array(
        'connection_id' => 0,
        'tenant_id'     => '',
        'tenant_name'   => '',
        'tenants'       => array(),
        'accounts'      => array(),
        'connected_at'  => 0,
    );

    $stored = is_array($stored) ? $stored : array();

    foreach (array('connection_id', 'connected_at') as $key) {
        if (isset($stored[$key]) && is_scalar($stored[$key])) {
            $state[$key] = max(0, (int) $stored[$key]);
        }
    }

    foreach ((isset($stored['tenants']) && is_array($stored['tenants'])) ? $stored['tenants'] : array() as $id => $name) {
        if (xero_uuid_valid((string) $id) && is_scalar($name) && (count($state['tenants']) < PG_XERO_TENANTS_KEPT)) {
            $state['tenants'][strtolower((string) $id)] = pg_connect_clip((string) $name);
        }
    }

    $tenant = isset($stored['tenant_id']) && xero_uuid_valid($stored['tenant_id']) ? strtolower($stored['tenant_id']) : '';

    if ($tenant !== '') {
        $state['tenant_id'] = $tenant;
        $state['tenant_name'] = isset($stored['tenant_name']) && is_scalar($stored['tenant_name']) ? pg_connect_clip((string) $stored['tenant_name']) : '';
    }

    foreach (xero_account_roles() as $role) {
        // An account belongs to an organisation: none is kept without one.
        $state['accounts'][$role] = xero_account_kept(($tenant !== '') && isset($stored['accounts'][$role]) ? $stored['accounts'][$role] : null);
    }

    return $state;
}

/* ---------------------------------------------------------------------------
   State
   --------------------------------------------------------------------------- */

// Connections can be stored and sealed: the tables and the encryption key.
function xero_ready()
{
    return function_exists('pg_connect_schema_ready') && pg_connect_schema_ready() && pg_connect_can_seal();
}

function xero_state()
{
    return xero_state_parse(xero_ready() ? pg_connect_service_get('xero') : null);
}

/**
 * @param array $state stored typed (xero_state_parse())
 * @return bool false without the encryption key or the tables
 */
function xero_state_save($state)
{
    return pg_connect_service_put('xero', 'Xero', xero_state_parse($state));
}

// The connection is gone: what was read through it goes too.
function xero_forget()
{
    pg_connect_service_delete('xero');
}

/**
 * The site's Xero connection: the one the state names while it is still the
 * site's, else the site's account connected for the purpose.
 *
 * @param array|null $state xero_state()
 * @return array|null pg_connect_account()
 */
function xero_connection($state = null)
{
    if (!xero_ready()) {
        return null;
    }

    $state = is_array($state) ? $state : xero_state();

    if ($state['connection_id'] > 0) {
        $account = pg_connect_account($state['connection_id']);

        if (is_array($account) && ($account['user_id'] === 0) && ($account['provider'] === 'xero')) {
            return $account;
        }
    }

    $accounts = pg_connect_accounts(array('provider' => 'xero', 'user_id' => 0, 'purpose' => 'xero'));

    return $accounts ? $accounts[0] : null;
}

/* ---------------------------------------------------------------------------
   Calls
   --------------------------------------------------------------------------- */

/**
 * @param int    $connection_id
 * @param string $tenant_id the organisation an accounting call is for; ''
 *                          for the identity service
 * @param string $method
 * @param string $url
 * @return array ok, status, data, error (Xero's words, clipped)
 */
function xero_call($connection_id, $tenant_id, $method, $url)
{
    $options = array(
        'headers'  => array('Accept: application/json'),
        'timeout'  => 30,
        'max_body' => 4194304,
    );

    if ($tenant_id !== '') {
        $options['headers'][] = 'xero-tenant-id: ' . $tenant_id;
    }

    $result = pg_connect_api((int) $connection_id, $method, $url, $options);

    return array(
        'ok'     => !empty($result['ok']),
        'status' => (int) $result['status'],
        'data'   => $result['data'],
        'error'  => !empty($result['ok']) ? '' : xero_error_message((int) $result['status'], $result['data'], (string) $result['error']),
    );
}

/**
 * The organisation's chart of accounts.
 *
 * @param int    $connection_id
 * @param string $tenant_id
 * @return array ok, accounts (xero_accounts_from_api()), error
 */
function xero_accounts_read($connection_id, $tenant_id)
{
    if (!xero_uuid_valid($tenant_id)) {
        return array('ok' => false, 'accounts' => array(), 'error' => lang('No Xero organisation is chosen.'));
    }

    $call = xero_call($connection_id, $tenant_id, 'GET', PG_XERO_API . '/Accounts?order=Name');

    return array(
        'ok'       => $call['ok'],
        'accounts' => $call['ok'] ? xero_accounts_from_api($call['data']) : array(),
        'error'    => $call['error'],
    );
}

/**
 * Once the browser is back from Xero's sign-in: the organisations the
 * application reaches are read and kept, and the one the site uses is
 * picked (xero_tenant_pick()). A new organisation leaves no account chosen.
 *
 * @param int $connection_id
 * @return array notices, warnings (Xero's words among them, unescaped), log
 */
function xero_after_connect($connection_id)
{
    $answer = array('notices' => array(), 'warnings' => array(), 'log' => '');

    $token = pg_connect_access_token((int) $connection_id);

    if (empty($token['ok'])) {
        $answer['warnings'][] = lang(array('string' => 'Xero has to be connected again: {var:1}', 'vars' => (string) $token['error']));

        return $answer;
    }

    $call = xero_call($connection_id, '', 'GET', PG_XERO_CONNECTIONS_URL);

    if (!$call['ok']) {
        $answer['warnings'][] = lang(array('string' => 'Xero gave a token but would not list its organisations: {var:1}', 'vars' => $call['error']));

        return $answer;
    }

    $tenants = xero_tenants_from_api($call['data'], xero_token_auth_event((string) $token['token']));
    $state = xero_state();
    $pick = xero_tenant_pick($tenants, $state['tenant_id']);

    if ($pick !== $state['tenant_id']) {
        $state['accounts'] = array();
    }

    $state['connection_id'] = (int) $connection_id;
    $state['tenants'] = array_map(function ($tenant) {
        return $tenant['name'];
    }, $tenants);
    $state['tenant_id'] = $pick;
    $state['tenant_name'] = ($pick !== '') ? $tenants[$pick]['name'] : '';
    $state['connected_at'] = time();

    xero_state_save($state);

    if (!$tenants) {
        $answer['warnings'][] = lang('Xero was connected, but no organisation was allowed. Connect it again and choose the organisation the site\'s sales go to.');
    } elseif ($pick === '') {
        $answer['warnings'][] = lang('Xero was connected to several organisations. Choose the one the site\'s sales go to under Connection details.');
    } else {
        $answer['notices'][] = lang(array('string' => 'The site uses the Xero organisation {var:1}.', 'vars' => $state['tenant_name']));
        $answer['log'] = lang(array('string' => 'connected the Xero organisation {var:1}', 'vars' => $state['tenant_name']));
    }

    return $answer;
}
