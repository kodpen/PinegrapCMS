<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The site's QuickBooks Online company: the connection made on QuickBooks'
 * page of Connections (connections.php?service=quickbooks) through the
 * site's own Intuit application (includes/connect/providers/quickbooks.php),
 * the company it reaches and the three accounts the site's sales will be
 * booked to.
 *
 * Intuit names the company only on the return address of the sign-in, as
 * its realmId parameter, which pg_connect_finish() keeps for this provider
 * ('return_params'); quickbooks_after_connect() reads the company with it.
 * Every accounting call goes to /v3/company/<realmId>/ on the host of the
 * application's environment: development keys reach only sandbox
 * companies, production keys come after Intuit's App Assessment
 * Questionnaire.
 *
 * Intuit asks that the sign-in addresses be read from its discovery
 * document; they are read while the page is drawn, at most once a day, and
 * the documented ones stand in until then (quickbooks_sign_in()).
 *
 * Nothing has a table or a column of its own: the environment, the
 * addresses, the company and the chosen accounts are one sealed value in
 * connect_accounts as the service entry "quickbooks" (pg_connect_service_
 * get/put()), typed by quickbooks_state_parse(). The pure parts are tested
 * in tests/quickbooks_test.php.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// Requests below minor version 75 are answered as 75 since 2025-08-01.
if (!defined('PG_QUICKBOOKS_MINOR_VERSION')) {
    define('PG_QUICKBOOKS_MINOR_VERSION', 75);
}

// How long the discovery document's addresses are kept before reading it
// again, in seconds.
if (!defined('PG_QUICKBOOKS_DISCOVERY_TTL')) {
    define('PG_QUICKBOOKS_DISCOVERY_TTL', 86400);
}

/* ---------------------------------------------------------------------------
   Pure parts
   --------------------------------------------------------------------------- */

/**
 * Whether a value is a QuickBooks company id (realmId): digits. Pure.
 *
 * @param mixed $realm
 * @return bool
 */
function quickbooks_realm_valid($realm)
{
    return is_string($realm) && (preg_match('/^[0-9]{1,32}$/D', $realm) === 1);
}

/**
 * The application's environment as it is kept. Pure.
 *
 * @param mixed $value
 * @return string 'production' | 'sandbox'
 */
function quickbooks_environment_value($value)
{
    return ($value === 'sandbox') ? 'sandbox' : 'production';
}

/**
 * Where the accounting calls of an environment go. Pure.
 *
 * @param string $environment
 * @return string
 */
function quickbooks_api_base($environment)
{
    return (quickbooks_environment_value($environment) === 'sandbox')
        ? 'https://sandbox-quickbooks.api.intuit.com/v3/company/'
        : 'https://quickbooks.api.intuit.com/v3/company/';
}

/**
 * Intuit's discovery document of an environment. Pure.
 *
 * @param string $environment
 * @return string
 */
function quickbooks_discovery_url($environment)
{
    return (quickbooks_environment_value($environment) === 'sandbox')
        ? 'https://developer.api.intuit.com/.well-known/openid_sandbox_configuration'
        : 'https://developer.api.intuit.com/.well-known/openid_configuration';
}

/**
 * The sign-in addresses Intuit documents, used until the discovery document
 * is read. Pure.
 *
 * @return array authorize, token, revoke, issuer
 */
function quickbooks_sign_in_documented()
{
    return array(
        'authorize' => 'https://appcenter.intuit.com/connect/oauth2',
        'token'     => 'https://oauth.platform.intuit.com/oauth2/v1/tokens/bearer',
        'revoke'    => 'https://developer.api.intuit.com/v2/oauth2/tokens/revoke',
        'issuer'    => 'https://oauth.platform.intuit.com/op/v1',
    );
}

/**
 * Whether an address may stand for one of Intuit's: HTTPS on intuit.com or
 * one of its subdomains, so a stored or answered value never sends the
 * client secret elsewhere. Pure.
 *
 * @param mixed $url
 * @return bool
 */
function quickbooks_intuit_url($url)
{
    if (!is_string($url) || (strlen($url) > 300)) {
        return false;
    }

    $parts = parse_url($url);

    if (!is_array($parts) || (strtolower($parts['scheme'] ?? '') !== 'https') || isset($parts['user']) || isset($parts['port'])) {
        return false;
    }

    $host = strtolower((string) ($parts['host'] ?? ''));

    return ($host === 'intuit.com') || (substr($host, -11) === '.intuit.com');
}

/**
 * The sign-in addresses of a discovery document answer, or of what was
 * kept from one. A missing or foreign address is the documented one. Pure.
 *
 * @param mixed $data the decoded discovery document, or quickbooks_sign_in()'s
 *                    shape
 * @return array authorize, token, revoke, issuer
 */
function quickbooks_sign_in($data)
{
    $addresses = quickbooks_sign_in_documented();
    $keys = array(
        'authorize' => array('authorization_endpoint', 'authorize'),
        'token'     => array('token_endpoint', 'token'),
        'revoke'    => array('revocation_endpoint', 'revoke'),
        'issuer'    => array('issuer', 'issuer'),
    );

    foreach ($keys as $key => $names) {
        foreach ($names as $name) {
            if (is_array($data) && isset($data[$name]) && quickbooks_intuit_url($data[$name])) {
                $addresses[$key] = $data[$name];
                break;
            }
        }
    }

    return $addresses;
}

/**
 * Whether the kept addresses are due to be read again. Pure.
 *
 * @param int $read_at when they were read, 0 for never
 * @param int $now
 * @return bool
 */
function quickbooks_discovery_due($read_at, $now)
{
    return ((int) $read_at <= 0) || (((int) $now - (int) $read_at) >= PG_QUICKBOOKS_DISCOVERY_TTL) || ((int) $read_at > (int) $now);
}

/**
 * The company of a CompanyInfo answer. Pure.
 *
 * @param mixed $data the decoded answer
 * @return array name, country (two letters upper-case, '' when not said)
 */
function quickbooks_company_from_api($data)
{
    $info = (is_array($data) && isset($data['CompanyInfo']) && is_array($data['CompanyInfo'])) ? $data['CompanyInfo'] : array();
    $name = '';

    foreach (array('CompanyName', 'LegalName') as $key) {
        if (isset($info[$key]) && is_scalar($info[$key]) && (trim((string) $info[$key]) !== '')) {
            $name = pg_connect_clip((string) $info[$key]);
            break;
        }
    }

    $country = isset($info['Country']) && is_string($info['Country']) ? $info['Country']
        : ((isset($info['CompanyAddr']['Country']) && is_string($info['CompanyAddr']['Country'])) ? $info['CompanyAddr']['Country'] : '');
    $country = strtoupper(trim($country));

    // QuickBooks writes "US", and an older company may write "USA".
    if ($country === 'USA') {
        $country = 'US';
    }

    return array('name' => $name, 'country' => (preg_match('/^[A-Z]{2}$/', $country) === 1) ? $country : '');
}

/**
 * The rows of a query answer (GET …/query?query=select …). Pure.
 *
 * @param mixed  $data   the decoded answer
 * @param string $entity Account, TaxCode
 * @return array
 */
function quickbooks_query_rows($data, $entity)
{
    return (is_array($data) && isset($data['QueryResponse'][$entity]) && is_array($data['QueryResponse'][$entity]))
        ? $data['QueryResponse'][$entity] : array();
}

/**
 * The accounts of a query answer, typed. Pure.
 *
 * @param mixed $data the decoded answer
 * @return array Id => id, number (AcctNum), name, type (AccountType),
 *               subtype (AccountSubType), active, in QuickBooks' order
 */
function quickbooks_accounts_from_api($data)
{
    $accounts = array();

    foreach (quickbooks_query_rows($data, 'Account') as $account) {
        $id = (is_array($account) && isset($account['Id']) && is_scalar($account['Id'])) ? (string) $account['Id'] : '';

        if (preg_match('/^[0-9]{1,20}$/', $id) !== 1) {
            continue;
        }

        $text = function ($key, $length) use ($account) {
            return isset($account[$key]) && is_scalar($account[$key]) ? mb_substr(trim((string) $account[$key]), 0, $length) : '';
        };

        $accounts[$id] = array(
            'id'      => $id,
            'number'  => $text('AcctNum', 20),
            'name'    => $text('Name', 150),
            'type'    => $text('AccountType', 50),
            'subtype' => $text('AccountSubType', 50),
            'active'  => !isset($account['Active']) || (($account['Active'] !== false) && ($account['Active'] !== 'false')),
        );
    }

    return $accounts;
}

/**
 * The roles an account is chosen for: income (the account of the items the
 * sales lines name), clearing (where a sale paid online is deposited until
 * the payout, Undeposited Funds as a rule) and bank (where payments by
 * transfer, cash or on delivery go). Pure.
 *
 * @return array role keys
 */
function quickbooks_account_roles()
{
    return array('income', 'clearing', 'bank');
}

/**
 * Whether an account may hold a role: an active income account for
 * income; a bank or other current asset account, which a SalesReceipt's
 * DepositToAccountRef takes, for clearing; a bank account for bank. Pure.
 *
 * @param string $role
 * @param array  $account quickbooks_accounts_from_api()'s item
 * @return bool
 */
function quickbooks_account_fits($role, $account)
{
    if (!is_array($account) || empty($account['active'])) {
        return false;
    }

    $type = (string) ($account['type'] ?? '');

    if ($role === 'income') {
        return in_array($type, array('Income', 'Other Income'), true);
    }

    if ($role === 'clearing') {
        return in_array($type, array('Bank', 'Other Current Asset'), true);
    }

    if ($role === 'bank') {
        return $type === 'Bank';
    }

    return false;
}

/**
 * An account as the page names it: "4000 — Sales", or the name alone. Pure.
 *
 * @param array $account id, number, name
 * @return string
 */
function quickbooks_account_label($account)
{
    $number = is_array($account) && isset($account['number']) ? trim((string) $account['number']) : '';
    $name = is_array($account) && isset($account['name']) ? trim((string) $account['name']) : '';

    if ($name === '') {
        $name = is_array($account) && isset($account['id']) ? (string) $account['id'] : '';
    }

    return ($number !== '') ? $number . ' — ' . $name : $name;
}

/**
 * The accounts that may hold a role, by their label. Pure.
 *
 * @param array  $accounts quickbooks_accounts_from_api()
 * @param string $role
 * @return array Id => label
 */
function quickbooks_accounts_for_role($accounts, $role)
{
    $offered = array();

    foreach (is_array($accounts) ? $accounts : array() as $id => $account) {
        if (quickbooks_account_fits($role, $account)) {
            $offered[(string) $id] = quickbooks_account_label($account);
        }
    }

    asort($offered, SORT_NATURAL | SORT_FLAG_CASE);

    return $offered;
}

/**
 * The tax codes a sale may carry, from a query answer: active codes with a
 * sales rate, by their label. TAX and NON are the US companies' own and are
 * not matched (quickbooks_tax_us()). Pure.
 *
 * @param mixed $data the decoded answer
 * @return array Id => label ("Name — Description" or the name)
 */
function quickbooks_tax_codes_for_sales($data)
{
    $offered = array();

    foreach (quickbooks_query_rows($data, 'TaxCode') as $code) {
        $id = (is_array($code) && isset($code['Id']) && is_scalar($code['Id'])) ? (string) $code['Id'] : '';

        if ((preg_match('/^[0-9]{1,20}$/', $id) !== 1) || (isset($code['Active']) && (($code['Active'] === false) || ($code['Active'] === 'false')))) {
            continue;
        }

        $rates = isset($code['SalesTaxRateList']['TaxRateDetail']) && is_array($code['SalesTaxRateList']['TaxRateDetail'])
            ? $code['SalesTaxRateList']['TaxRateDetail'] : array();

        if (!$rates) {
            continue;
        }

        $name = isset($code['Name']) && is_scalar($code['Name']) ? mb_substr(trim((string) $code['Name']), 0, 100) : '';
        $about = isset($code['Description']) && is_scalar($code['Description']) ? mb_substr(trim((string) $code['Description']), 0, 100) : '';

        if ($name === '') {
            $name = $id;
        }

        $offered[$id] = (($about !== '') && ($about !== $name)) ? $name . ' — ' . $about : $name;
    }

    asort($offered, SORT_NATURAL | SORT_FLAG_CASE);

    return $offered;
}

/**
 * Whether the company is a US one: its sales carry TAX or NON with the
 * site's total, and no rate is matched. Pure.
 *
 * @param array $state quickbooks_state_parse()
 * @return bool
 */
function quickbooks_tax_us($state)
{
    return is_array($state) && (($state['country'] ?? '') === 'US');
}

/**
 * QuickBooks' own words for a refused call: the Fault's errors (Message,
 * Detail, code) or the sign-in's error. Pure.
 *
 * @param int    $status
 * @param mixed  $data     the decoded answer
 * @param string $fallback what the transport said
 * @return string at most 250 characters
 */
function quickbooks_error_message($status, $data, $fallback)
{
    $words = array();
    $fault = null;

    if (is_array($data)) {
        $fault = isset($data['Fault']) ? $data['Fault'] : (isset($data['fault']) ? $data['fault'] : null);
    }

    $errors = is_array($fault) ? (isset($fault['Error']) ? $fault['Error'] : (isset($fault['error']) ? $fault['error'] : array())) : array();

    foreach (is_array($errors) ? $errors : array() as $error) {
        if (!is_array($error)) {
            continue;
        }

        $text = trim((isset($error['Message']) && is_string($error['Message'])) ? $error['Message'] : ((isset($error['message']) && is_string($error['message'])) ? $error['message'] : ''));
        $detail = trim((isset($error['Detail']) && is_string($error['Detail'])) ? $error['Detail'] : ((isset($error['detail']) && is_string($error['detail'])) ? $error['detail'] : ''));
        $code = isset($error['code']) && is_scalar($error['code']) ? trim((string) $error['code']) : '';

        $line = trim($text . ((($detail !== '') && ($detail !== $text)) ? ': ' . $detail : '') . (($code !== '') ? ' (' . $code . ')' : ''));

        if (($line !== '') && !in_array($line, $words, true)) {
            $words[] = $line;
        }
    }

    if (!$words && is_array($data)) {
        foreach (array('error_description', 'error') as $key) {
            if (isset($data[$key]) && is_string($data[$key]) && (trim($data[$key]) !== '')) {
                $words[] = trim($data[$key]);
                break;
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
 * The stored state, every key present and typed. Pure.
 *
 * @param mixed $stored
 * @return array environment, sign_in (quickbooks_sign_in()), sign_in_read_at,
 *               connection_id, realm_id, company_name, country, accounts
 *               (income, clearing, bank: id, number, name), connected_at
 */
function quickbooks_state_parse($stored)
{
    $stored = is_array($stored) ? $stored : array();

    $state = array(
        'environment'     => quickbooks_environment_value($stored['environment'] ?? ''),
        'sign_in'         => quickbooks_sign_in($stored['sign_in'] ?? null),
        'sign_in_read_at' => 0,
        'connection_id'   => 0,
        'realm_id'        => '',
        'company_name'    => '',
        'country'         => '',
        'accounts'        => array(),
        'connected_at'    => 0,
    );

    foreach (array('sign_in_read_at', 'connection_id', 'connected_at') as $key) {
        if (isset($stored[$key]) && is_scalar($stored[$key])) {
            $state[$key] = max(0, (int) $stored[$key]);
        }
    }

    $realm = (isset($stored['realm_id']) && quickbooks_realm_valid((string) $stored['realm_id'])) ? (string) $stored['realm_id'] : '';

    if ($realm !== '') {
        $state['realm_id'] = $realm;
        $state['company_name'] = isset($stored['company_name']) && is_scalar($stored['company_name']) ? pg_connect_clip((string) $stored['company_name']) : '';
        $country = isset($stored['country']) && is_string($stored['country']) ? strtoupper($stored['country']) : '';
        $state['country'] = (preg_match('/^[A-Z]{2}$/', $country) === 1) ? $country : '';
    }

    foreach (quickbooks_account_roles() as $role) {
        $kept = array('id' => '', 'number' => '', 'name' => '');
        $account = (($realm !== '') && isset($stored['accounts'][$role]) && is_array($stored['accounts'][$role])) ? $stored['accounts'][$role] : array();

        // An account belongs to a company: none is kept without one.
        if (isset($account['id']) && is_scalar($account['id']) && (preg_match('/^[0-9]{1,20}$/', (string) $account['id']) === 1)) {
            $kept['id'] = (string) $account['id'];

            foreach (array('number' => 20, 'name' => 150) as $key => $length) {
                $kept[$key] = isset($account[$key]) && is_scalar($account[$key]) ? mb_substr(trim((string) $account[$key]), 0, $length) : '';
            }
        }

        $state['accounts'][$role] = $kept;
    }

    return $state;
}

/**
 * What stays when the connection goes: the environment and the addresses,
 * which belong to the application. Pure.
 *
 * @param array $state quickbooks_state_parse()
 * @return array
 */
function quickbooks_state_forgotten($state)
{
    $state = quickbooks_state_parse($state);

    return quickbooks_state_parse(array(
        'environment'     => $state['environment'],
        'sign_in'         => $state['sign_in'],
        'sign_in_read_at' => $state['sign_in_read_at'],
    ));
}

/* ---------------------------------------------------------------------------
   State
   --------------------------------------------------------------------------- */

// Connections can be stored and sealed: the tables and the encryption key.
function quickbooks_ready()
{
    return function_exists('pg_connect_schema_ready') && function_exists('pg_schema_has') && pg_connect_schema_ready() && pg_connect_can_seal();
}

/**
 * The stored state, read once a request unless $fresh.
 *
 * @param bool $fresh read it again
 * @return array quickbooks_state_parse()
 */
function quickbooks_state($fresh = false)
{
    static $state = null;

    if (($state === null) || $fresh) {
        $state = quickbooks_state_parse(quickbooks_ready() ? pg_connect_service_get('quickbooks') : null);
    }

    return $state;
}

/**
 * @param array $state
 * @return bool false without the encryption key or the tables
 */
function quickbooks_state_save($state)
{
    $saved = pg_connect_service_put('quickbooks', 'QuickBooks', quickbooks_state_parse($state));
    quickbooks_state(true);

    return $saved;
}

// The connection is gone: the company and the accounts go with it.
function quickbooks_forget()
{
    if (quickbooks_ready()) {
        quickbooks_state_save(quickbooks_state_forgotten(quickbooks_state(true)));
    }
}

/**
 * The site's QuickBooks connection.
 *
 * @param array|null $state quickbooks_state()
 * @return array|null pg_connect_account()
 */
function quickbooks_connection($state = null)
{
    if (!quickbooks_ready()) {
        return null;
    }

    $state = is_array($state) ? $state : quickbooks_state();

    if ($state['connection_id'] > 0) {
        $account = pg_connect_account($state['connection_id']);

        if (is_array($account) && ($account['user_id'] === 0) && ($account['provider'] === 'quickbooks')) {
            return $account;
        }
    }

    $accounts = pg_connect_accounts(array('provider' => 'quickbooks', 'user_id' => 0, 'purpose' => 'quickbooks'));

    return $accounts ? $accounts[0] : null;
}

/* ---------------------------------------------------------------------------
   Calls
   --------------------------------------------------------------------------- */

/**
 * Reads the discovery document again when the kept addresses are a day old.
 * A refusal keeps what was there, so the documented addresses stand in.
 *
 * @return array quickbooks_state()
 */
function quickbooks_discovery_refresh()
{
    $state = quickbooks_state();

    if (!quickbooks_ready() || !quickbooks_discovery_due($state['sign_in_read_at'], time())) {
        return $state;
    }

    require_once(PG_FUNCTIONS_DIR . '/includes/api/outbound/http.php');

    $answer = api_http_request('GET', quickbooks_discovery_url($state['environment']), array(
        'headers'  => array('Accept: application/json'),
        'timeout'  => 10,
        'max_body' => 65536,
        'agent'    => 'connect',
    ));

    $data = !empty($answer['ok']) ? json_decode((string) $answer['body'], true) : null;

    // Read or not, it is not asked again for a day.
    if (is_array($data)) {
        $state['sign_in'] = quickbooks_sign_in($data);
    }

    $state['sign_in_read_at'] = time();
    quickbooks_state_save($state);

    return quickbooks_state();
}

/**
 * A GET of the company's accounting API.
 *
 * @param int    $connection_id
 * @param array  $state quickbooks_state()
 * @param string $path  after /v3/company/<realmId>/, with its own query
 * @return array ok, status, data, error (QuickBooks' words, clipped)
 */
function quickbooks_get($connection_id, $state, $path)
{
    if (!quickbooks_realm_valid($state['realm_id'])) {
        return array('ok' => false, 'status' => 0, 'data' => null, 'error' => lang('No QuickBooks company is connected.'));
    }

    $url = quickbooks_api_base($state['environment']) . $state['realm_id'] . '/' . $path
        . ((strpos($path, '?') === false) ? '?' : '&') . 'minorversion=' . PG_QUICKBOOKS_MINOR_VERSION;

    $result = pg_connect_api((int) $connection_id, 'GET', $url, array(
        'headers'  => array('Accept: application/json'),
        'timeout'  => 30,
        'max_body' => 4194304,
    ));

    return array(
        'ok'     => !empty($result['ok']),
        'status' => (int) $result['status'],
        'data'   => $result['data'],
        'error'  => !empty($result['ok']) ? '' : quickbooks_error_message((int) $result['status'], $result['data'], (string) $result['error']),
    );
}

/**
 * A query of the company (select … from <entity>), up to 1,000 rows.
 *
 * @param int    $connection_id
 * @param array  $state
 * @param string $query
 * @return array as quickbooks_get()
 */
function quickbooks_query($connection_id, $state, $query)
{
    return quickbooks_get($connection_id, $state, 'query?query=' . rawurlencode($query));
}

/**
 * The company's active accounts.
 *
 * @param int   $connection_id
 * @param array $state
 * @return array ok, accounts (quickbooks_accounts_from_api()), error
 */
function quickbooks_accounts_read($connection_id, $state)
{
    $call = quickbooks_query($connection_id, $state, 'select * from Account where Active = true maxresults 1000');

    return array('ok' => $call['ok'], 'accounts' => $call['ok'] ? quickbooks_accounts_from_api($call['data']) : array(), 'error' => $call['error']);
}

/**
 * The company's sales tax codes.
 *
 * @param int   $connection_id
 * @param array $state
 * @return array ok, codes (quickbooks_tax_codes_for_sales()), error
 */
function quickbooks_tax_codes_read($connection_id, $state)
{
    $call = quickbooks_query($connection_id, $state, 'select * from TaxCode where Active = true maxresults 1000');

    return array('ok' => $call['ok'], 'codes' => $call['ok'] ? quickbooks_tax_codes_for_sales($call['data']) : array(), 'error' => $call['error']);
}

/**
 * Once the browser is back from Intuit's sign-in: the company the return
 * named is read and kept. Another company leaves no account chosen. While
 * Xero holds the site's books, nothing is kept: one accounting service at a
 * time.
 *
 * @param int    $connection_id
 * @param string $realm the return's realmId
 * @return array notices, warnings (QuickBooks' words among them,
 *               unescaped), log
 */
function quickbooks_after_connect($connection_id, $realm)
{
    $answer = array('notices' => array(), 'warnings' => array(), 'log' => '');
    $realm = (string) $realm;

    require_once(PG_FUNCTIONS_DIR . '/includes/fn/xero.php');

    if (xero_ready() && (xero_state()['tenant_id'] !== '')) {
        $answer['warnings'][] = lang('Xero keeps the site\'s books. Disconnect Xero first, then connect QuickBooks again.');

        return $answer;
    }

    $state = quickbooks_state(true);

    if (!quickbooks_realm_valid($realm)) {
        $answer['warnings'][] = lang('QuickBooks did not say which company was connected. Connect it again and choose the company the site\'s sales go to.');

        return $answer;
    }

    if ($realm !== $state['realm_id']) {
        $state['accounts'] = array();
    }

    $state['connection_id'] = (int) $connection_id;
    $state['realm_id'] = $realm;
    $state['connected_at'] = time();

    $call = quickbooks_get($connection_id, $state, 'companyinfo/' . $realm);
    $company = $call['ok'] ? quickbooks_company_from_api($call['data']) : array('name' => '', 'country' => '');

    $state['company_name'] = ($company['name'] !== '') ? $company['name'] : $realm;
    $state['country'] = $company['country'];

    quickbooks_state_save($state);

    if (!$call['ok']) {
        $answer['warnings'][] = lang(array('string' => 'QuickBooks was connected, but the company could not be read: {var:1}', 'vars' => $call['error']));

        return $answer;
    }

    $answer['notices'][] = lang(array('string' => 'The site uses the QuickBooks company {var:1}.', 'vars' => $state['company_name']));
    $answer['log'] = lang(array('string' => 'connected the QuickBooks company {var:1}', 'vars' => $state['company_name']));

    return $answer;
}
