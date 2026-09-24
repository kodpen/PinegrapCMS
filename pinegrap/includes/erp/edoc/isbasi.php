<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * ERP e-document driver: Logo İşbaşı.
 *
 * Logo İşbaşı offers a REST API to its subscribers (developers.isbasi.com,
 * behind an İşbaşı sign-in; the Swagger file it links is the source of every
 * path here and docs/_isbasi_api_notlari.md is the reading of it). The API
 * key is issued by Logo support on request, and calls carry it together
 * with the İşbaşı administrator's e-mail and password.
 *
 * How a request is made (single-account model, no SSO):
 *
 *   1. POST {entry}/api/v1.0/user/getplatform  -> data.baseUrl, the API host
 *   2. POST {base}/api/v1.0/user/integrationLogin -> access token, tenant id
 *   3. every call: apiKey, Authorization: Bearer, tenantId, UserName,
 *      UserEmail, Lang, DeviceType headers
 *
 * The token lives a day; it is kept encrypted on the provider row
 * (erp_edoc_session_save) so a store does not log in for every document -
 * the monthly quota is 3000 reads and 7000 writes. A 401 logs in once more
 * and retries.
 *
 * What the documentation does not say is not guessed. The login answer's
 * field names are read flexibly and, when none fits, listed by name in the
 * error so the first real test teaches the shape; the integer code lists
 * (e-Archive payment type, e-government type, e-invoice profile) are not in
 * the public documentation, so the driver leaves them out and returns a
 * plain error for the document kinds that need them (see the notes file).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_ERP_ENTRY')) {
    exit;
}

// Where a call starts. Live is the host the Swagger file names (the platform
// call then answers with the real API base for the account); test is the
// environment Logo hands out with a test API key - there the address Logo
// gives IS the base, so nothing the platform call says can move a test
// setup onto the live system. The settings card can override either.
define('ERP_EDOC_ISBASI_ENTRY_URL', 'https://isbasimw.isbasi.com');
define('ERP_EDOC_ISBASI_TEST_URL', 'https://soho-isbasi-mwv2-test.logo-paas.com');
define('ERP_EDOC_ISBASI_TEST_PANEL_URL', 'https://soho-isbasi-uiv2-test.logo-paas.com/');

// One day per the documentation; renewed an hour early.
define('ERP_EDOC_ISBASI_TOKEN_LIFETIME', 23 * 3600);

function erp_edoc_isbasi_info()
{
    return array(
        'label' => 'Logo İşbaşı',
        'description' => lang('Cloud pre-accounting by Logo. e-Invoice and e-Archive through the İşbaşı API; the store needs an İşbaşı subscription and an API key issued by Logo support.'),
        'docs_url' => 'https://developers.isbasi.com/',
        'capabilities' => array('einvoice', 'earchive', 'inbox', 'accounts'),
        'settings_note' => lang('Ask Logo support (destek@logo.com.tr) for the API key of your İşbaşı account; the user name and password are the ones you sign in to İşbaşı with.'),
    );
}

function erp_edoc_isbasi_fields()
{
    return array(
        array('name' => 'api_key', 'label' => lang('API key'), 'type' => 'password', 'required' => true,
            'help' => lang('Issued by Logo support for your account.')),
        array('name' => 'username', 'label' => lang('İşbaşı user name'), 'type' => 'text', 'required' => true,
            'help' => lang('The e-mail address you sign in to İşbaşı with.')),
        array('name' => 'password', 'label' => lang('İşbaşı password'), 'type' => 'password', 'required' => true, 'help' => ''),
        array('name' => 'tenant_id', 'label' => lang('Company (tenant id)'), 'type' => 'text', 'required' => false,
            'help' => lang('One İşbaşı user can have several companies (Firma Değiştir in the İşbaşı panel). Leave it empty to work in the company İşbaşı signs you in to - the connection test names it; paste an id to pin one.')),
        array('name' => 'environment', 'label' => lang('Environment'), 'type' => 'select', 'required' => false,
            'options' => array(
                'live' => lang('Live (real documents go to the tax authority)'),
                'test' => lang('Test environment (Logo test account)'),
            ),
            'help' => lang(array('string' => 'The test environment needs the test API key and user Logo issues; its own panel is at {var:1}.', 'vars' => ERP_EDOC_ISBASI_TEST_PANEL_URL))),
        array('name' => 'entry_url', 'label' => lang('API address'), 'type' => 'text', 'required' => false,
            'help' => lang(array('string' => 'Leave it empty unless Logo gave you an address of your own: {var:1} for live, {var:2} for test. This is the API address, not the panel you sign in to — a panel address pasted here is turned into its API address.', 'vars' => array(ERP_EDOC_ISBASI_ENTRY_URL, ERP_EDOC_ISBASI_TEST_URL)))),
    );
}

/* ---------------------------------------------------------------------------
   Session
   --------------------------------------------------------------------------- */

/**
 * The address the API is spoken to, out of whatever was pasted in the box.
 *
 * Logo's welcome mail lists three addresses together - the API endpoint, the
 * panel to sign in to, and the key - so the panel address lands in this box
 * often enough to design for rather than to punish. Only the scheme, host
 * and port survive (the driver appends its own paths), and İşbaşı's panel
 * host becomes its API host: the two names differ by one syllable,
 * soho-isbasi-**uiv2**-test and soho-isbasi-**mwv2**-test.
 *
 * A host that is not İşbaşı's is left alone; Logo does hand out special
 * addresses, and guessing at those would be worse than passing them on.
 *
 * @param string $url       What the operator typed, if anything
 * @param string $fallback  The address the chosen environment uses
 * @return string  Scheme and host, no trailing slash
 */
function erp_edoc_isbasi_base_url($url, $fallback)
{
    $url = trim((string) $url);

    if ($url === '') {
        return rtrim((string) $fallback, '/');
    }

    if (!preg_match('~^https?://~i', $url)) {
        $url = 'https://' . ltrim($url, '/');
    }

    $parts = parse_url($url);
    $host = strtolower(trim((string) ($parts['host'] ?? '')));

    if ($host === '') {
        return rtrim((string) $fallback, '/');
    }

    if (strpos($host, 'uiv2') !== false) {
        $host = str_replace('uiv2', 'mwv2', $host);
    }

    $scheme = strtolower((string) ($parts['scheme'] ?? 'https'));
    $port = isset($parts['port']) ? (':' . (int) $parts['port']) : '';

    return $scheme . '://' . $host . $port;
}

/**
 * The credentials with the entry address filled in.
 *
 * @param array|null $credentials  Stored ones when null
 * @return array
 */
function erp_edoc_isbasi_credentials($credentials = null)
{
    $credentials = is_array($credentials) ? $credentials : erp_edoc_credentials('isbasi');

    foreach (array('api_key', 'username', 'password', 'tenant_id', 'entry_url', 'environment') as $name) {
        $credentials[$name] = trim((string) ($credentials[$name] ?? ''));
    }

    // Live unless the store says test: a setup whose environment was never
    // answered is a live setup, never a silent test one.
    $credentials['environment'] = ($credentials['environment'] === 'test') ? 'test' : 'live';

    $credentials['entry_url'] = erp_edoc_isbasi_base_url($credentials['entry_url'],
        ($credentials['environment'] === 'test') ? ERP_EDOC_ISBASI_TEST_URL : ERP_EDOC_ISBASI_ENTRY_URL);

    return $credentials;
}

/**
 * Test or live, for the screens: an invoice sent from a test setup is not
 * a document anybody can hand to a customer, and the card says so.
 *
 * @return array ['is_test' => bool, 'label' => string]
 */
function erp_edoc_isbasi_environment()
{
    $credentials = erp_edoc_isbasi_credentials();
    $is_test = ($credentials['environment'] === 'test');

    return array('is_test' => $is_test, 'label' => $is_test ? lang('Test environment') : '');
}

/**
 * Which of the three required credentials is missing, by label.
 *
 * @param array $credentials
 * @return array
 */
function erp_edoc_isbasi_missing($credentials)
{
    $missing = array();

    foreach (erp_edoc_isbasi_fields() as $field) {
        if (!empty($field['required']) && ((string) ($credentials[$field['name']] ?? '') === '')) {
            $missing[] = $field['label'];
        }
    }

    return $missing;
}

/**
 * Looks a value up under any of several key spellings, one level down as
 * well ("data.tenantId" and "data.user.tenantId" both count). Case does not
 * matter: the documentation itself writes tenantId, TenantId and tenantID.
 *
 * @param array $haystack
 * @param array $keys
 * @return string
 */
function erp_edoc_isbasi_pick($haystack, $keys)
{
    if (!is_array($haystack)) {
        return '';
    }

    $wanted = array_map('strtolower', $keys);

    foreach ($haystack as $key => $value) {
        if (in_array(strtolower((string) $key), $wanted, true) && is_scalar($value) && (trim((string) $value) !== '')) {
            return trim((string) $value);
        }
    }

    foreach ($haystack as $value) {
        if (is_array($value)) {
            $found = erp_edoc_isbasi_pick($value, $keys);

            if ($found !== '') {
                return $found;
            }
        }
    }

    return '';
}

/**
 * The names of the keys in an answer, for an error message that teaches
 * the shape without printing a value.
 *
 * @param mixed $data
 * @return string
 */
function erp_edoc_isbasi_key_names($data)
{
    if (!is_array($data)) {
        return gettype($data);
    }

    $names = array();

    foreach ($data as $key => $value) {
        $names[] = (string) $key . (is_array($value) ? '{' . implode(',', array_slice(array_keys($value), 0, 12)) . '}' : '');
    }

    return implode(', ', array_slice($names, 0, 25));
}

/**
 * The message İşbaşı put in an answer, or the HTTP situation when there is none.
 *
 * @param array $result  From erp_edoc_http()
 * @return string
 */
function erp_edoc_isbasi_error_text($result)
{
    if ((string) ($result['error'] ?? '') !== '') {
        return lang(array('string' => 'İşbaşı could not be reached: {var:1}', 'vars' => (string) $result['error']));
    }

    $body = $result['body'];
    $message = is_array($body) ? trim((string) ($body['message'] ?? '')) : '';

    if ($message !== '') {
        return $message;
    }

    $code = (int) ($result['http_code'] ?? 0);

    if ($code === 401) {
        return lang('İşbaşı did not accept the credentials (401).');
    }

    if ($code === 429) {
        return lang('İşbaşı says too many requests (429); the monthly quota or the rate limit is reached.');
    }

    if ($code === 0) {
        return lang('İşbaşı gave no answer.');
    }

    // The login address answering "method not allowed" or "not here" is the
    // address being wrong, not the credentials: most often the panel
    // address in the API address box.
    if (($code === 404) || ($code === 405)) {
        return lang(array('string' => 'There is no İşbaşı API at this address (HTTP {var:1}). Check the API address on the e-Document card — the panel address you sign in to is a different one.', 'vars' => (string) $code));
    }

    // 502/503/504 come back as a plain HTML page from the load balancer, not
    // as İşbaşı's own envelope: the service itself is down, not the request
    // wrong. Worth saying, because the operator's next move is to wait.
    if (($code === 502) || ($code === 503) || ($code === 504)) {
        return lang(array('string' => 'İşbaşı is not answering at the moment (HTTP {var:1}). Try again in a little while.', 'vars' => (string) $code));
    }

    return lang(array('string' => 'İşbaşı answered HTTP {var:1}.', 'vars' => (string) $code));
}

/**
 * Was the answer a success by İşbaşı's own envelope: HTTP 2xx, isError
 * false, code 0 or 200.
 *
 * @param array $result
 * @return bool
 */
function erp_edoc_isbasi_ok($result)
{
    $http = (int) ($result['http_code'] ?? 0);

    if (($http < 200) || ($http > 299) || !is_array($result['body'])) {
        return false;
    }

    $body = $result['body'];

    if (array_key_exists('isError', $body) && !empty($body['isError'])) {
        return false;
    }

    if (isset($body['code']) && !in_array((int) $body['code'], array(0, 200), true)) {
        return false;
    }

    return true;
}

/**
 * Logs in: platform query for the base URL, then integrationLogin for the
 * token and tenant. Returns the session, or an error.
 *
 * @param array $credentials  From erp_edoc_isbasi_credentials()
 * @param array $context      doc_type, doc_id: the document this login is for, for the log
 * @return array ['success' => bool, 'session' => array, 'error' => string]
 */
function erp_edoc_isbasi_login($credentials, $context = array())
{
    $log_type = (string) ($context['doc_type'] ?? '');
    $log_id = (int) ($context['doc_id'] ?? 0);

    $missing = erp_edoc_isbasi_missing($credentials);

    if (!empty($missing)) {
        return array('success' => false, 'session' => array(), 'error' => lang(array('string' => 'Missing: {var:1}.', 'vars' => implode(', ', $missing))));
    }

    $json_headers = array(
        'apiKey: ' . $credentials['api_key'],
        'Lang: tr-TR',
        'Content-Type: application/json; charset=utf-8',
        'Accept: application/json',
    );

    // The address Logo gives is the base. The documentation also describes
    // user/getplatform as the way to learn it, and this driver did call it
    // first - but an integration API key is refused there ("API
    // yetkilendirilmedi", 403, in the test environment and with a live key
    // alike), so it was one wasted call and one alarming log line per login.
    // A store Logo gives another address to puts it in the entry box.
    $base_url = $credentials['entry_url'];

    // The login. What comes back (2026-09-21, test environment):
    // data { moduleToken, accessToken, tokenType, tenantId, userId,
    //        licenses[ {code, name, period, expireDate, closureDate,
    //        packageMessage, isFree, isExpired} ], ... }
    $login_url = $base_url . '/api/v1.0/user/integrationLogin';
    $login_body = array('username' => $credentials['username'], 'password' => $credentials['password']);

    // The company to work in, when the store pinned one. IntegrationLoginInfo
    // documents tenantId; without it İşbaşı opens the user's default company,
    // which on the test account was the one its panel had selected
    // (4719c95b-..., 2026-09-23).
    if ((string) ($credentials['tenant_id'] ?? '') !== '') {
        $login_body['tenantId'] = (string) $credentials['tenant_id'];
    }
    $login = erp_edoc_http('POST', $login_url, $json_headers, $login_body);
    erp_edoc_log('isbasi', $log_type, $log_id, 'POST', $login_url, $login, $login_body);

    if (!erp_edoc_isbasi_ok($login)) {
        return array('success' => false, 'session' => array(), 'error' => erp_edoc_isbasi_error_text($login));
    }

    $data = is_array($login['body']['data'] ?? null) ? $login['body']['data'] : $login['body'];
    $token = erp_edoc_isbasi_pick($data, array('accessToken', 'access_token', 'token', 'Token', 'bearerToken', 'jwt'));
    $tenant = erp_edoc_isbasi_pick($data, array('tenantId', 'tenantID', 'TenantId', 'tenant_id', 'tenant'));

    // Pinned to one company but signed in to another: İşbaşı did not take
    // the id, and the documents would land in the wrong books.
    if (((string) ($credentials['tenant_id'] ?? '') !== '') && ($tenant !== '')
        && (strcasecmp($tenant, (string) $credentials['tenant_id']) !== 0)) {
        return array('success' => false, 'session' => array(), 'error' => lang(array(
            'string' => 'İşbaşı signed in to company {var:1}, not to {var:2} in the company box. Check the id (Firma Değiştir in the İşbaşı panel).',
            'vars' => array($tenant, (string) $credentials['tenant_id']),
        )));
    }

    if ($token === '') {
        return array('success' => false, 'session' => array(), 'error' => lang(array(
            'string' => 'İşbaşı signed in but the answer carries no access token under a known name (fields: {var:1}). Send this line to Logo support or to the developer.',
            'vars' => erp_edoc_isbasi_key_names($data),
        )));
    }

    $session = array(
        'base_url' => $base_url,
        'environment' => $credentials['environment'],
        'access_token' => $token,
        'tenant_id' => $tenant,
        'user_id' => erp_edoc_isbasi_pick($data, array('userId', 'UserId', 'user_id', 'id')),
        'user_email' => erp_edoc_isbasi_pick($data, array('email', 'userEmail', 'UserEmail', 'emailAddress')),
        'platform' => erp_edoc_isbasi_pick($data, array('Platform', 'platform')),
        'is_test_user' => !empty($data['IsTestUser']) || !empty($data['isTestUser']),
        'is_canary' => !empty($data['isCanary']),
        // The flags the documentation mentions after login, kept by name so
        // the invoice builder can ask for the portal e-Archive one.
        'flags' => array_filter(is_array($data) ? $data : array(), function ($value, $key) {
            return is_bool($value) && preg_match('/responsible|einvoice|earchive|eportal/i', (string) $key);
        }, ARRAY_FILTER_USE_BOTH),
        'fingerprint' => erp_edoc_isbasi_fingerprint($credentials),
        'expires_at' => time() + ERP_EDOC_ISBASI_TOKEN_LIFETIME,
        'logged_in_at' => time(),
    );

    if ($session['user_email'] === '') {
        $session['user_email'] = $credentials['username'];
    }

    return array('success' => true, 'session' => $session, 'error' => '');
}

/**
 * Which credentials a session was opened with, so a changed password does
 * not keep riding the old token.
 *
 * @param array $credentials
 * @return string
 */
function erp_edoc_isbasi_fingerprint($credentials)
{
    return substr(md5($credentials['api_key'] . '|' . $credentials['username'] . '|' . $credentials['password']
        . '|' . $credentials['entry_url'] . '|' . $credentials['environment']
        . ((string) ($credentials['tenant_id'] ?? '') !== '' ? '|' . $credentials['tenant_id'] : '')), 0, 16);
}

/**
 * A live session: the cached one when it fits the stored credentials and
 * has not expired, a fresh login otherwise.
 *
 * @param bool  $fresh    true forces a new login
 * @param array $context  doc_type, doc_id for the log
 * @return array ['success' => bool, 'session' => array, 'error' => string]
 */
function erp_edoc_isbasi_session($fresh = false, $context = array())
{
    $credentials = erp_edoc_isbasi_credentials();

    if (!$fresh) {
        $cached = erp_edoc_session('isbasi');

        if (!empty($cached['access_token']) && ((string) ($cached['fingerprint'] ?? '') === erp_edoc_isbasi_fingerprint($credentials))) {
            return array('success' => true, 'session' => $cached, 'error' => '');
        }
    }

    $login = erp_edoc_isbasi_login($credentials, $context);

    if ($login['success']) {
        erp_edoc_session_save('isbasi', $login['session']);
    }

    return $login;
}

/**
 * The headers every authenticated call carries. Some endpoints list only a
 * few of these as required; sending them all is harmless and saves a
 * second reading of the documentation per call.
 *
 * @param array  $session
 * @param string $content_type
 * @param string $accept
 * @return array
 */
function erp_edoc_isbasi_headers($session, $content_type = 'application/json; charset=utf-8', $accept = 'application/json')
{
    $credentials = erp_edoc_isbasi_credentials();

    $headers = array(
        'apiKey: ' . $credentials['api_key'],
        'Authorization: Bearer ' . (string) $session['access_token'],
        'Lang: tr-TR',
        'DeviceType: WEB',
        'DeviceName: Pinegrap',
        'UserName: ' . $credentials['username'],
        'UserEmail: ' . (string) (($session['user_email'] ?? '') !== '' ? $session['user_email'] : $credentials['username']),
        'Accept: ' . $accept,
        'Content-Type: ' . $content_type,
    );

    if ((string) ($session['tenant_id'] ?? '') !== '') {
        $headers[] = 'tenantId: ' . $session['tenant_id'];
    }

    if ((string) ($session['user_id'] ?? '') !== '') {
        $headers[] = 'UserId: ' . $session['user_id'];
    }

    return $headers;
}

/**
 * An authenticated call, logged, with one re-login on 401.
 *
 * @param string     $method
 * @param string     $path      '/api/v1.0/...', query string included
 * @param array|null $body
 * @param array      $context   doc_type, doc_id for the log; content_type, accept overrides
 * @return array  erp_edoc_http() result plus 'session'
 */
function erp_edoc_isbasi_request($method, $path, $body = null, $context = array())
{
    $session = erp_edoc_isbasi_session(false, $context);

    if (!$session['success']) {
        return array('http_code' => 0, 'body' => null, 'raw' => '', 'error' => '', 'duration_ms' => 0, 'login_error' => $session['error']);
    }

    $attempt = function ($session_data) use ($method, $path, $body, $context) {
        $url = rtrim((string) $session_data['base_url'], '/') . $path;
        $headers = erp_edoc_isbasi_headers($session_data,
            (string) ($context['content_type'] ?? 'application/json; charset=utf-8'),
            (string) ($context['accept'] ?? 'application/json'));
        $result = erp_edoc_http($method, $url, $headers, $body);
        erp_edoc_log('isbasi', (string) ($context['doc_type'] ?? ''), (int) ($context['doc_id'] ?? 0), $method, $url, $result, $body);

        return $result;
    };

    $result = $attempt($session['session']);

    if ((int) $result['http_code'] === 401) {
        $session = erp_edoc_isbasi_session(true, $context);

        if (!$session['success']) {
            return $result + array('login_error' => $session['error']);
        }

        $result = $attempt($session['session']);
    }

    return $result + array('login_error' => '');
}

/* ---------------------------------------------------------------------------
   Contract
   --------------------------------------------------------------------------- */

/**
 * Signs in with the given credentials (typed ones over stored ones, as the
 * settings card sends them) and says what İşbaşı answered. A success here
 * also refreshes the cached session when the credentials are the stored ones.
 *
 * @param array $credentials
 * @return array ['success' => bool, 'message' => string, 'details' => array]
 */
function erp_edoc_isbasi_ping($credentials)
{
    $credentials = erp_edoc_isbasi_credentials($credentials);
    $login = erp_edoc_isbasi_login($credentials);

    if (!$login['success']) {
        return array('success' => false, 'message' => $login['error'], 'details' => array());
    }

    $session = $login['session'];

    if ($session['fingerprint'] === erp_edoc_isbasi_fingerprint(erp_edoc_isbasi_credentials())) {
        erp_edoc_session_save('isbasi', $session);
    }

    $notes = array();

    if ($credentials['environment'] === 'test') {
        $notes[] = lang('test environment');
    }

    if ($session['platform'] !== '') {
        $notes[] = lang(array('string' => 'platform {var:1}', 'vars' => $session['platform']));
    }

    // In full: it is what the company box takes, and a store with several
    // companies needs to see which one the documents will go to.
    if ($session['tenant_id'] !== '') {
        $notes[] = lang(array('string' => 'company {var:1}', 'vars' => $session['tenant_id']))
            . ((string) ($credentials['tenant_id'] ?? '') === '' ? ' ' . lang('(İşbaşı\'s default)') : '');
    }


    if ($session['is_test_user']) {
        $notes[] = lang('test user');
    }

    if ($session['is_canary']) {
        $notes[] = lang('canary environment');
    }

    if (!empty($session['flags'])) {
        $on = array_keys(array_filter($session['flags']));

        if (!empty($on)) {
            $notes[] = implode(', ', $on);
        }
    }

    return array(
        'success' => true,
        'message' => lang(array('string' => 'İşbaşı signed in{var:1}.', 'vars' => empty($notes) ? '' : ' (' . implode('; ', $notes) . ')')),
        'details' => array('base_url' => $session['base_url']),
    );
}

/**
 * Is a tax number registered at GİB for e-Invoice (and e-Delivery note).
 *
 * @param string $vkn  VKN or TCKN
 * @return array ['success' => bool, 'is_einvoice_user' => bool, 'aliases' => array,
 *                'is_edispatch_user' => bool, 'title' => string, 'error' => string]
 */
function erp_edoc_isbasi_check_taxpayer($vkn)
{
    $vkn = preg_replace('/\D/', '', (string) $vkn);
    $none = array('success' => false, 'is_einvoice_user' => false, 'aliases' => array(), 'is_edispatch_user' => false, 'title' => '', 'error' => '');

    if ((strlen($vkn) !== 10) && (strlen($vkn) !== 11)) {
        return array_merge($none, array('error' => lang('A VKN has 10 digits and a TCKN 11.')));
    }

    $result = erp_edoc_isbasi_request('GET', '/api/v1.0/user/gibUser?tcknVkn=' . rawurlencode($vkn), null, array('doc_type' => 'taxpayer', 'accept' => 'text/plain, application/json'));

    if ((string) ($result['login_error'] ?? '') !== '') {
        return array_merge($none, array('error' => $result['login_error']));
    }

    // 404 is the documented answer for "not registered".
    if ((int) $result['http_code'] === 404) {
        return array_merge($none, array('success' => true));
    }

    if (!erp_edoc_isbasi_ok($result)) {
        return array_merge($none, array('error' => erp_edoc_isbasi_error_text($result)));
    }

    $data = is_array($result['body']['data'] ?? null) ? $result['body']['data'] : array();
    $collect = function ($list) {
        $aliases = array();
        $title = '';

        foreach ((array) $list as $item) {
            $alias = trim((string) ($item['alias'] ?? ''));

            if (($alias !== '') && !in_array($alias, $aliases, true)) {
                $aliases[] = $alias;
            }

            if (($title === '') && (trim((string) ($item['title'] ?? '')) !== '')) {
                $title = trim((string) $item['title']);
            }
        }

        return array($aliases, $title);
    };

    // PK is the receiving side: the alias an invoice to this taxpayer is
    // addressed to. GB (sender) aliases count towards "is a user" only.
    list($pk_aliases, $pk_title) = $collect($data['pkList'] ?? array());
    list($gb_aliases, $gb_title) = $collect($data['gbList'] ?? array());
    list($dispatch_pk, ) = $collect($data['dispatchPkList'] ?? array());
    list($dispatch_gb, ) = $collect($data['dispatchGbList'] ?? array());

    return array(
        'success' => true,
        'is_einvoice_user' => !empty($pk_aliases) || !empty($gb_aliases),
        'aliases' => !empty($pk_aliases) ? $pk_aliases : $gb_aliases,
        'is_edispatch_user' => !empty($dispatch_pk) || !empty($dispatch_gb),
        'title' => ($pk_title !== '') ? $pk_title : $gb_title,
        'error' => '',
    );
}

/**
 * Registers a sales invoice at İşbaşı, which issues it as e-Invoice or
 * e-Archive by the counterparty's tax number. What comes back is İşbaşı's
 * invoice id; the GİB number and ETTN arrive with poll().
 *
 * @param array $invoice  An erp_invoices row
 * @param array $lines    erp_invoice_items rows
 * What comes back (2026-09-21, test environment):
 *   data { invoiceId: 1966, uuid: "A7E0F552-...", no: "REE2026000000172" }
 * so the ETTN and the document number are known at once - the outgoing
 * e-document list does not carry the invoice until GİB has taken it.
 *
 * What comes back is a DRAFT. Logo support put it plainly (2026-09-21):
 * "Fatura kaydedildiğinde taslak olarak oluşur resmileşmesi için gibe
 * gönderilmesi gereklidir." So this answers 'created', and
 * erp_edoc_isbasi_submit_invoice() is what makes it a document.
 *
 * @param array $options  'party' (erp_edoc_invoice_party() output) when the caller has it
 * @return array ['success' => bool, 'external_id' => string, 'gib_uuid' => string,
 *                'gib_number' => string, 'status' => 'created', 'error' => string]
 */
function erp_edoc_isbasi_send_invoice($invoice, $lines, $options = array())
{
    $fail = function ($error) {
        return array('success' => false, 'external_id' => '', 'gib_uuid' => '', 'gib_number' => '', 'status' => 'error', 'error' => $error);
    };

    if ((string) ($invoice['direction'] ?? 'sales') !== 'sales') {
        return $fail(lang('Only sales invoices go to İşbaşı.'));
    }

    if ((string) ($invoice['doc_type'] ?? 'invoice') === 'return') {
        return $fail(lang('İşbaşı takes a sales return only on its own screen (Satış İade Faturası, opened from the invoice); the integration endpoint has no field for the invoice being returned.'));
    }

    if ((string) ($invoice['doc_type'] ?? 'invoice') !== 'invoice') {
        return $fail(lang('Only invoices go to İşbaşı; a proforma is not an e-document.'));
    }

    if (erp_edoc_isbasi_government_type((string) ($invoice['invoice_type'] ?? 'SATIS')) === null) {
        return $fail(erp_edoc_isbasi_government_type_refusal((string) $invoice['invoice_type']));
    }

    if (empty($lines)) {
        return $fail(lang('The invoice has no lines.'));
    }

    $payload = erp_edoc_isbasi_invoice_payload($invoice, $lines, $options);

    if (isset($payload['error'])) {
        return $fail($payload['error']);
    }

    $result = erp_edoc_isbasi_request('POST', '/api/v1.0/invoices/integrationInvoices', $payload, array('doc_type' => 'invoice', 'doc_id' => (int) $invoice['id']));

    if ((string) ($result['login_error'] ?? '') !== '') {
        return $fail($result['login_error']);
    }

    if (!erp_edoc_isbasi_ok($result)) {
        return $fail(erp_edoc_isbasi_error_text($result));
    }

    // Documented as data.invoiceId; a bare scalar in data is read too.
    $data = $result['body']['data'] ?? null;
    $external_id = is_array($data) ? erp_edoc_isbasi_pick($data, array('invoiceId', 'id')) : trim((string) $data);

    if ($external_id === '') {
        return $fail(lang(array('string' => 'İşbaşı accepted the invoice but returned no invoice id (fields: {var:1}).', 'vars' => erp_edoc_isbasi_key_names($result['body']))));
    }

    return array(
        'success' => true,
        'external_id' => $external_id,
        'gib_uuid' => is_array($data) ? erp_edoc_isbasi_pick($data, array('uuid', 'uuId', 'ettn')) : '',
        'gib_number' => is_array($data) ? erp_edoc_isbasi_pick($data, array('no', 'invoiceNumber', 'number')) : '',
        'status' => 'created',
        'error' => '',
    );
}

/**
 * Hands a draft İşbaşı is holding to the tax authority. This is the step
 * that turns a saved invoice into a legal document; without it the record
 * sits at eStatus 0 ("GİB'E GÖNDERİLECEK" / "İMZAYA GÖNDERİLMEDİ") for good.
 *
 * Logo support named the endpoint on 2026-09-21. It carries no body; the
 * document is named in the query string, and the İşbaşı id - not the ETTN -
 * is what identifies it.
 *
 * Calling it twice for one document is the provider's to refuse; the service
 * layer stops offering the button once the document has gone.
 *
 * @param array  $invoice      An erp_invoices row
 * @param string $external_id  İşbaşı's invoice id; the row's own when omitted
 * @return array ['success' => bool, 'status' => string, 'message' => string, 'error' => string]
 */
function erp_edoc_isbasi_submit_invoice($invoice, $external_id = '')
{
    $fail = function ($error) {
        return array('success' => false, 'status' => '', 'message' => '', 'error' => $error);
    };

    $external_id = trim((string) (($external_id !== '') ? $external_id : ($invoice['edoc_external_id'] ?? '')));

    if ($external_id === '') {
        return $fail(lang('The invoice has no İşbaşı id.'));
    }

    $result = erp_edoc_isbasi_request('POST', '/api/v1.0/einvoices/eInvoiceWithJson?invoiceId=' . rawurlencode($external_id), null,
        array('doc_type' => 'invoice', 'doc_id' => (int) ($invoice['id'] ?? 0)));

    if ((string) ($result['login_error'] ?? '') !== '') {
        return $fail($result['login_error']);
    }

    // İşbaşı answers a hand-over that WORKED with HTTP 400, isError true and
    // a one-word message - "İşlendi" on one document, "İmzalandı" on the
    // next (test tenant, 2026-09-21). Both times the document moved on that
    // very call, from "İMZAYA GÖNDERİLMEDİ" / "GİB'E GÖNDERİLECEK" to
    // "HENÜZ İŞLENMEDİ" / "İşlem Bekliyor.". Keeping a list of İşbaşı's
    // words for success would break on the next one it invents, so the
    // document itself is asked: if it is no longer an unsent draft, the
    // call did its work whatever the envelope said. The extra read happens
    // only on this path, never on a clean answer. (Logo has been asked why
    // the code is 400.)
    if (!erp_edoc_isbasi_ok($result)) {
        $after = erp_edoc_isbasi_poll($invoice, $external_id);

        if (!empty($after['success']) && ((string) ($after['status'] ?? 'created') !== 'created')) {
            $said = trim((string) ($result['body']['message'] ?? ''));
            $seen = trim((string) ($after['message'] ?? ''));

            return array(
                'success' => true,
                'status' => (string) $after['status'],
                'message' => ($seen !== '') ? $seen : (($said !== '') ? $said : lang('İşbaşı took the document for GİB.')),
                'error' => '',
            );
        }

        return $fail(erp_edoc_isbasi_error_text($result));
    }

    $data = $result['body']['data'] ?? null;
    $message = is_array($data)
        ? erp_edoc_isbasi_pick($data, array('description', 'message', 'statusText', 'eStatusText'))
        : trim((string) $data);

    if ($message === '') {
        $message = trim((string) ($result['body']['message'] ?? ''));
    }

    return array(
        'success' => true,
        'status' => 'sent',
        'message' => ($message !== '') ? $message : lang('İşbaşı took the document for GİB.'),
        'error' => '',
    );
}

/**
 * What İşbaşı would refuse this buyer for, by field label.
 *
 * Asked here rather than there: a rejected write is a write off the month's
 * quota and an error in the provider's words, when the fix is a field on the
 * account card. The list is what the test environment answered, one refusal
 * at a time (2026-09-21): "customer.address alanı boş gönderilemez",
 * "customer.tcknvkn alanı boş gönderilemez", "Firma tanımında adres, posta
 * kodu, şehir ve ilçe alanları dolu olmalıdır".
 *
 * The same function answers at issue time, before a number is spent, and at
 * send time - so the two can never drift apart.
 *
 * @param array $party    erp_edoc_invoice_party() output
 * @param array $invoice  The row, when there is one: the walk-in account is
 *                        recognised from it
 * @return array  field name => label, empty when the buyer is complete.
 *                Named, not just labelled, so the screen that reports the
 *                gap can offer the boxes to close it.
 */
function erp_edoc_isbasi_party_missing($party, $invoice = array())
{
    $tax_number = preg_replace('/\D/', '', (string) ($party['tax_number'] ?? ''));
    $is_person = !empty($party['is_person']) || (strlen($tax_number) === 11);
    $is_walk_in = defined('ERP_WALKIN_ACCOUNT_ID') && ((int) ERP_WALKIN_ACCOUNT_ID > 0)
        && ((int) ($invoice['account_id'] ?? 0) === (int) ERP_WALKIN_ACCOUNT_ID);

    // The buyer with no identity number of their own is the final consumer,
    // and GİB has a number for exactly that; so this is not a gap.
    if (($tax_number === '') && ($is_walk_in || $is_person)) {
        $tax_number = erp_edoc_final_consumer_tckn();
    }

    $missing = array();

    if (trim((string) ($party['title'] ?? '')) === '') {
        $missing['title'] = lang('Title');
    }

    if (($tax_number === '') || ((strlen($tax_number) !== 10) && (strlen($tax_number) !== 11))) {
        $missing['tax_number'] = lang('VKN / TCKN');
    } elseif (!erp_edoc_tax_number_valid($tax_number, true)) {
        // The right length but the wrong check digits: GİB refuses it, so
        // does every integrator in front of it.
        $missing['tax_number'] = lang('VKN / TCKN (the check digits do not match; the number is mistyped)');
    }

    // An individual is carried as first name and surname, and İşbaşı
    // refuses one without a surname ("Bireysel firma için Ad ve Soyad
    // gereklidir", 2026-09-22). A person's card with a one-word title has
    // no surname to send.
    $person_named = ($tax_number === '') ? $is_person : ($is_person || (strlen($tax_number) === 11));
    if ($person_named && !isset($missing['title']) && (erp_edoc_person_name((string) $party['title']) === null)) {
        $missing['title'] = lang('Name and surname (a person needs both; or mark the card as a company)');
    }

    foreach (array('address' => lang('Address'), 'postcode' => lang('Zip Code'), 'city' => lang('City'), 'district' => lang('District')) as $field => $label) {
        if (trim((string) ($party[$field] ?? '')) === '') {
            $missing[$field] = $label;
        }
    }

    if (!isset($missing['postcode']) && !erp_edoc_postcode_valid((string) $party['postcode'], (string) ($party['country_code'] ?? 'TR'))) {
        $missing['postcode'] = lang('Zip Code (five digits)');
    }

    return $missing;
}

/**
 * What the document itself is missing, as opposed to its buyer.
 *
 * Only the internet sale has any: VUK 509 wants the carrier named on the
 * document the goods travel with, and İşbaşı enforces it. These live on the
 * invoice, not on the account card - a different carrier next week does not
 * make last week's document wrong.
 *
 * @param array $invoice  An erp_invoices row
 * @return array  field name => label, empty when nothing is missing
 */
function erp_edoc_isbasi_document_missing($invoice)
{
    $missing = array();

    if ((int) ($invoice['is_internet_sale'] ?? 0) !== 1) {
        return $missing;
    }

    if (trim((string) ($invoice['carrier_title'] ?? '')) === '') {
        $missing['carrier_title'] = lang('Carrier');
    }

    $carrier_vkn = preg_replace('/\D/', '', (string) ($invoice['carrier_vkn'] ?? ''));

    if (($carrier_vkn === '') || ((strlen($carrier_vkn) !== 10) && (strlen($carrier_vkn) !== 11))) {
        $missing['carrier_vkn'] = lang('Carrier VKN / TCKN');
    } elseif (!erp_edoc_tax_number_valid($carrier_vkn)) {
        // The final-consumer number is no carrier's number either.
        $missing['carrier_vkn'] = lang('Carrier VKN / TCKN (not a valid number)');
    }

    // A carrier with a TCKN is an individual (firmType 0), and İşbaşı wants
    // the first name and the surname of one.
    if ((strlen($carrier_vkn) === 11) && !isset($missing['carrier_title'])
        && (erp_edoc_person_name((string) $invoice['carrier_title']) === null)) {
        $missing['carrier_title'] = lang('An 11-digit number makes the carrier a person: write the first name and surname, or enter the cargo company\'s 10-digit VKN.');
    }

    return $missing;
}

/**
 * The SimpleInv body for an invoice, field for field from the documentation.
 *
 * @param array $invoice
 * @param array $lines
 * @param array $options
 * @return array  The payload, or ['error' => string]
 */
function erp_edoc_isbasi_invoice_payload($invoice, $lines, $options = array())
{
    $party = (isset($options['party']) && is_array($options['party'])) ? $options['party'] : erp_edoc_invoice_party($invoice);
    $tax_number = preg_replace('/\D/', '', (string) $party['tax_number']);
    $is_person = !empty($party['is_person']) || (strlen($tax_number) === 11);

    // A buyer with no identity number on the card. İşbaşı will not take an
    // invoice without customer.tcknVkn, and a counter sale genuinely has no
    // number to give, so the final-consumer eleven ones go instead - the
    // e-Arşiv convention (Erdal's decision, 2026-09-21). Only where that is
    // what the blank means: the walk-in account the till bills to, or a
    // person's card. A company card with no VKN is a card somebody has not
    // finished filling in, and that is worth stopping for.
    $is_walk_in = defined('ERP_WALKIN_ACCOUNT_ID') && ((int) ERP_WALKIN_ACCOUNT_ID > 0)
        && ((int) ($invoice['account_id'] ?? 0) === (int) ERP_WALKIN_ACCOUNT_ID);

    if (($tax_number === '') && ($is_walk_in || $is_person)) {
        $tax_number = erp_edoc_final_consumer_tckn();
        $is_person = true;
    }

    $missing = erp_edoc_isbasi_party_missing($party, $invoice);

    if (!empty($missing)) {
        return array('error' => lang(array(
            'string' => 'İşbaşı will not take the invoice until these are on the account card: {var:1}. A document already issued keeps the copy it was written with, so the invoice has to be raised again after the card is filled in.',
            'vars' => implode(', ', $missing),
        )));
    }

    // The code of the İşbaşı card the account is linked to, when it is:
    // İşbaşı then puts the invoice on that card instead of looking one up by
    // name and number (or opening another).
    $customer = array(
        'code' => mb_substr(trim((string) ($party['provider_code'] ?? '')), 0, 50),
        'name' => (string) $party['title'],
        'email' => (string) $party['email'],
        'tcknVkn' => $tax_number,
        'taxOffice' => (string) $party['tax_office'],
        'country' => erp_edoc_isbasi_country_name((string) $party['country_code']),
        'city' => (string) $party['city'],
        'district' => (string) $party['district'],
        'address' => trim((string) $party['address']),
        // Not in the published SimpleInvCustomer model, but İşbaşı refuses a
        // customer without one ("Firma tanımında adres, posta kodu, şehir ve
        // ilçe alanları dolu olmalıdır", 2026-09-21). The document's own copy
        // of the address already carries the postcode inside the line; the
        // field is taken from the account card.
        'postalCode' => (string) $party['postcode'],
        'isPerson' => $is_person,
    );

    if ($is_person) {
        // İşbaşı matches a person by first and last name; the title is one
        // string here, so the last word is the surname. A one-word title
        // never gets this far (erp_edoc_isbasi_party_missing()).
        $person = erp_edoc_person_name((string) $party['title']);
        $customer['firstName'] = ($person !== null) ? $person['first'] : trim((string) $party['title']);
        $customer['lastName'] = ($person !== null) ? $person['last'] : '';
    }

    // Money: Pinegrap keeps kurus, İşbaşı wants decimals. Unit prices are
    // VAT-exclusive on the invoice, so vatIncluded is false.
    $details = array();

    foreach ($lines as $line) {
        $quantity = (float) $line['quantity'];
        $unit_price = ((int) $line['unit_price']) / 100;
        $discount_rate = (float) $line['discount_rate'];
        $discount_value = ((int) $line['discount_amount']) / 100;
        // products.code is the SKU field (free text); İşbaşı matches its
        // own product card by itemCode, so the SKU when there is one.
        $item_code = ((int) ($line['product_id'] ?? 0) > 0) ? trim((string) db_value("SELECT code FROM products WHERE id = '" . (int) $line['product_id'] . "'")) : '';
        $item_code = mb_substr($item_code, 0, 50);

        $detail = array(
            'quantity' => $quantity,
            'taxRate' => (float) $line['tax_rate'],
            'name' => (string) $line['description'],
            'price' => $unit_price,
            'discountRate' => $discount_rate,
            'discountValue' => ($discount_rate > 0) ? 0 : $discount_value,
            'description' => '',
            'productDetail' => array(
                'itemCode' => ($item_code !== '') ? $item_code : ((int) ($line['product_id'] ?? 0) > 0 ? 'PG-' . (int) $line['product_id'] : ''),
                'itemType' => ((int) ($line['product_id'] ?? 0) > 0) ? 1 : 2,
                'name' => (string) $line['description'],
                'vat' => (int) round((float) $line['tax_rate']),
                'unit' => erp_edoc_isbasi_unit_name((string) $line['unit_code']),
            ),
        );

        if ((string) $line['vat_exemption_code'] !== '') {
            $detail['vatExemptionCode'] = (string) $line['vat_exemption_code'];
        }

        if (((float) $line['withholding_rate'] > 0) && ((string) $line['withholding_code'] !== '')) {
            $detail['productDetail']['withholding'] = array(
                'code' => (string) $line['withholding_code'],
                'rateText' => erp_edoc_isbasi_rate_text((float) $line['withholding_rate']),
            );
        }

        $details[] = $detail;
    }

    // Shipping, surcharges and gift cards are lines of their own on the
    // Pinegrap invoice (the bridge writes them so); nothing to add here.
    $currency = strtoupper(trim((string) $invoice['currency']));
    $payload = array(
        'invoiceId' => 0,
        'customer' => $customer,
        'invoiceDate' => date('Y-m-d H:i:s', strtotime((string) $invoice['issue_date'] . ' 12:00:00')),
        'currency' => ($currency === 'TRY') ? 'TL' : $currency,
        'exchangeRate' => ($currency === 'TRY') ? 1 : (float) $invoice['exchange_rate'],
        'description' => trim((string) $invoice['full_number'] . (((int) ($invoice['order_id'] ?? 0) > 0) ? ' / ' . lang(array('string' => 'Order #{var:1}', 'vars' => (int) $invoice['order_id'])) : '')),
        'vatIncluded' => false,
        'salesInvoiceDetails' => $details,
    );

    // The e-Archive internet sale: type 3, the web address, the payment
    // date and the carrier are what the documentation lists as mandatory.
    // The integer payment-type code is not published; it is sent only when
    // the store has been given the list (settings: isbasi payment_type_codes).
    if ((int) ($invoice['is_internet_sale'] ?? 0) === 1) {
        $shipment_date = ((string) $invoice['shipment_date'] !== '0000-00-00') ? (string) $invoice['shipment_date'] : (string) $invoice['issue_date'];
        $payment_date = ((string) $invoice['payment_date'] !== '0000-00-00') ? (string) $invoice['payment_date'] : (string) $invoice['issue_date'];
        $carrier_vkn = preg_replace('/\D/', '', (string) $invoice['carrier_vkn']);

        $carrier_missing = erp_edoc_isbasi_document_missing($invoice);

        if (!empty($carrier_missing)) {
            return array('error' => lang(array(
                'string' => 'An internet-sale e-Archive invoice needs these on the invoice before İşbaşı will take it: {var:1}.',
                'vars' => implode(', ', $carrier_missing),
            )));
        }

        $egov = array(
            'invoiceTypeForEinvoice' => 3,
            // ISO 8601, the documentation's example. Open question to Logo:
            // İşbaşı answers "Ödeme tarihi ... zorunludur" to this field in
            // every form tried (Y-m-d H:i:s, ISO 8601, ISO 8601 UTC with
            // milliseconds, d.m.Y; also with the date repeated on the invoice
            // itself), 2026-09-23.
            'eArchivePaymentDate' => date('Y-m-d\TH:i:s', strtotime($payment_date . ' 12:00:00')),
            'eArchivePaymentAgent' => erp_edoc_isbasi_payment_label((string) $invoice['payment_method']),
            'website' => (string) $invoice['web_address'],
        );

        $payment_type = erp_edoc_isbasi_payment_type((string) $invoice['payment_method']);

        if ($payment_type !== null) {
            $egov['eArchivePaymentType'] = $payment_type;
        }

        $payload['eGovernmentInvoice'] = $egov;
        $payload['sendingDate'] = date('Y-m-d H:i:s', strtotime($shipment_date . ' 12:00:00'));

        // An individual carrier travels as first name and surname; a company
        // as its title alone.
        $carrier_person = (strlen($carrier_vkn) === 11) ? erp_edoc_person_name((string) $invoice['carrier_title']) : null;

        $payload['shipmentAgentItem'] = array(
            'name' => ($carrier_person !== null) ? $carrier_person['first'] : (string) $invoice['carrier_title'],
            'surName' => ($carrier_person !== null) ? $carrier_person['last'] : '',
            'identifier' => $carrier_vkn,
            'firmType' => (strlen($carrier_vkn) === 11) ? 0 : 1,
        );
    }

    // A type other than a plain sale travels as its İşbaşı number; a plain
    // sale is left to İşbaşı, as it has been since the first invoice went.
    $government_type = erp_edoc_isbasi_government_type((string) ($invoice['invoice_type'] ?? 'SATIS'));

    if ($government_type > 0) {
        $payload['eGovernmentInvoice'] = (isset($payload['eGovernmentInvoice']) ? $payload['eGovernmentInvoice'] : array())
            + array('eGovernmentType' => $government_type);
    }

    return $payload;
}

/**
 * İşbaşı's number for a GİB invoice type, or null for one it cannot be
 * sent as from here.
 *
 * The numbers are İşbaşı's own list (Giden E-Faturalar → type filter, read
 * 2026-09-23): 0 Satış, 1 Özel Matrah, 2 İstisna, 4 Tevkifat, 6 İade,
 * 11 Konaklama Vergisi, 14 Teknoloji Destek, 15/16/18 YTB. Özel matrah needs
 * a special base per line and a return needs the invoice it returns, and
 * neither travels through the integration endpoint; export is not on the
 * list at all.
 *
 * @param string $invoice_type  erp_invoices.invoice_type
 * @return int|null
 */
function erp_edoc_isbasi_government_type($invoice_type)
{
    $types = array('SATIS' => 0, 'ISTISNA' => 2, 'TEVKIFAT' => 4);

    return array_key_exists($invoice_type, $types) ? $types[$invoice_type] : null;
}

/**
 * Why an invoice type is not sent, in the operator's words.
 *
 * @param string $invoice_type
 * @return string
 */
function erp_edoc_isbasi_government_type_refusal($invoice_type)
{
    switch ($invoice_type) {
        case 'OZELMATRAH':
            return lang('A special-base (özel matrah) invoice needs a base amount per line, which the İşbaşı integration endpoint does not take; issue it on the İşbaşı screen.');
        case 'IADE':
            return lang('A return is issued as a return invoice against the original, not as an invoice of type İADE.');
        case 'IHRACAT':
            return lang('İşbaşı does not list export among the invoice types its integration endpoint takes.');
    }

    return lang(array('string' => 'İşbaşı has no code for a {var:1} invoice.', 'vars' => $invoice_type));
}

/**
 * İşbaşı's number for how an internet sale was paid.
 *
 * İşbaşı's own form lists them (read 2026-09-23): 0 credit or debit card,
 * 1 EFT/transfer, 2 cash on delivery, 3 payment intermediary, 4 other - the
 * GİB codes erp_invoices.payment_method keeps, in the same order. A list in
 * the driver settings (payment_type_codes) still wins, should Logo number a
 * store differently.
 *
 * @param string $method  erp_invoices.payment_method (GİB code)
 * @return int|null
 */
function erp_edoc_isbasi_payment_type($method)
{
    $settings = erp_edoc_settings('isbasi');
    $codes = (is_array($settings['payment_type_codes'] ?? null) ? $settings['payment_type_codes'] : array()) + array(
        'KREDIKARTI/BANKAKARTI' => 0,
        'EFT/HAVALE' => 1,
        'KAPIDAODEME' => 2,
        'ODEMEARACISI' => 3,
        'DIGER' => 4,
    );

    return isset($codes[$method]) ? (int) $codes[$method] : null;
}

/**
 * Can a document at İşbaşı be taken back, from the row alone.
 *
 * What İşbaşı allows (read from the test tenant, 2026-09-23): a draft is
 * deleted through the API; an e-Archive invoice that has gone reports
 * isCancellable and is cancelled on İşbaşı's own screen - the API documents
 * no call for it - but only once İşbaşı has processed it: its screen refuses
 * one that is still "to be created", "created", "sent to the server,
 * awaiting processing" or "in error" (tried on REC2026000000218); an
 * e-Invoice that has gone reports isCancellable false: the buyer answers it
 * with a return invoice (or a rejection) instead.
 *
 * @param array $invoice  An erp_invoices row
 * @return array ['ok' => bool, 'manual' => bool, 'reason' => string, 'url' => string]
 */
function erp_edoc_isbasi_cancellable($invoice)
{
    $status = (string) ($invoice['edoc_status'] ?? 'none');
    $external_id = trim((string) ($invoice['edoc_external_id'] ?? ''));
    $panel = erp_edoc_isbasi_panel_url($external_id);

    if (($external_id === '') || ($status === 'created')) {
        return array('ok' => true, 'manual' => false, 'url' => '',
            'reason' => ($external_id === '') ? '' : lang('The draft is deleted at İşbaşı in the same click.'));
    }

    if (in_array($status, array('error', 'rejected'), true)) {
        return array('ok' => true, 'manual' => false, 'url' => $panel,
            'reason' => lang('İşbaşı is asked what it holds first: a draft is deleted there, anything further is left for its own screen.'));
    }

    if ((string) ($invoice['edoc_kind'] ?? '') === 'earchive') {
        return array('ok' => true, 'manual' => true, 'url' => $panel,
            'reason' => lang('An e-Archive invoice can be cancelled at İşbaşı, but only on its own screen: open the invoice there and choose Cancel (İptal Et). Then confirm here; İşbaşı is asked before anything changes.')
                . (($status !== 'accepted') ? ' ' . lang('İşbaşı refuses it until the invoice has been processed; ask after its status first.') : ''));
    }

    if ((string) ($invoice['edoc_kind'] ?? '') === 'einvoice') {
        return array('ok' => false, 'manual' => false, 'url' => '',
            'reason' => lang('An e-Invoice that has gone to GİB cannot be cancelled: the buyer answers it with their own return invoice (or rejects it), and that return is recorded here.'));
    }

    return array('ok' => true, 'manual' => true, 'url' => $panel,
        'reason' => lang('Whether İşbaşı lets it be cancelled depends on what it became; İşbaşı is asked before anything changes.'));
}

/**
 * Takes a document back at İşbaşı: a draft is deleted, a cancelled
 * e-Archive invoice is confirmed. What İşbaşı holds is read first, so the
 * decision rests on its answer and not on a row that may be out of date.
 *
 * @param array  $invoice
 * @param string $reason
 * @return array ['success' => bool, 'error' => string, 'message' => string, 'outcome' => 'deleted'|'cancelled'|'']
 */
function erp_edoc_isbasi_cancel_invoice($invoice, $reason = '')
{
    $external_id = trim((string) ($invoice['edoc_external_id'] ?? ''));
    $context = array('doc_type' => 'invoice', 'doc_id' => (int) $invoice['id']);
    $fail = function ($error) {
        return array('success' => false, 'error' => $error, 'message' => '', 'outcome' => '');
    };

    if ($external_id === '') {
        return array('success' => true, 'error' => '', 'message' => '', 'outcome' => 'deleted');
    }

    $detail = erp_edoc_isbasi_request('GET', '/api/v1.0/invoices/' . rawurlencode($external_id), null, $context);

    if ((string) ($detail['login_error'] ?? '') !== '') {
        return $fail($detail['login_error']);
    }

    // Gone already: deleted on İşbaşı's screen, or never kept.
    if (!erp_edoc_isbasi_ok($detail)) {
        $text = erp_edoc_isbasi_error_text($detail);
        if (((int) $detail['http_code'] === 404) || (mb_stripos($text, 'bulunamad', 0, 'UTF-8') !== false)) {
            return array('success' => true, 'error' => '', 'message' => lang('İşbaşı no longer has the document.'), 'outcome' => 'deleted');
        }
        return $fail($text);
    }

    $data = is_array($detail['body']['data'] ?? null) ? $detail['body']['data'] : array();
    $egov = is_array($data['eGovernmentInvoice'] ?? null) ? $data['eGovernmentInvoice'] : array();
    $type = (int) ($egov['invoiceTypeForEinvoice'] ?? 0);

    if (!empty($data['isCancelled'])) {
        return array('success' => true, 'error' => '', 'message' => lang('İşbaşı has it as cancelled.'), 'outcome' => 'cancelled');
    }

    // Not handed to GİB yet: a draft, deleted through the API. The route is
    // the one the documentation's path names (DELETE /invoices?Ids=), not
    // the /deleteInvoice of its example, which answers 404 (2026-09-23).
    if (((int) ($egov['eStatus'] ?? 0) === 0) && empty($egov['isSended'])) {
        $deleted = erp_edoc_isbasi_request('DELETE', '/api/v1.0/invoices?Ids=' . rawurlencode($external_id), null, $context);

        if ((string) ($deleted['login_error'] ?? '') !== '') {
            return $fail($deleted['login_error']);
        }

        if (!erp_edoc_isbasi_ok($deleted)) {
            return $fail(lang(array('string' => 'İşbaşı would not delete the draft: {var:1}', 'vars' => erp_edoc_isbasi_error_text($deleted))));
        }

        return array('success' => true, 'error' => '', 'message' => lang('The draft has been deleted at İşbaşı.'), 'outcome' => 'deleted');
    }

    if (($type === 2) || ($type === 3)) {
        $state = trim((string) ($egov['description'] ?? ''));
        return $fail(lang('İşbaşı still has the e-Archive invoice as valid. Cancel it on the İşbaşı screen first (open the invoice, İptal Et), then confirm here again.')
            . (($state !== '') ? ' ' . lang(array('string' => 'Its state there: {var:1}. İşbaşı cancels an e-Archive invoice only once it has been processed.', 'vars' => $state)) : ''));
    }

    return $fail(lang('The invoice has gone to GİB as an e-Invoice and cannot be cancelled; the buyer answers it with a return invoice.'));
}

/**
 * The invoice on İşbaşı's own screen, for the operator.
 *
 * @param string $external_id
 * @return string  '' when the panel address cannot be told
 */
function erp_edoc_isbasi_panel_url($external_id)
{
    $credentials = erp_edoc_isbasi_credentials();
    $base = (string) $credentials['entry_url'];

    // The panel lives next to the API on Logo's hosts (mwv2 -> uiv2, the
    // reverse of erp_edoc_isbasi_base_url()). The live panel's address is
    // not published, so there no link is offered rather than a guessed one.
    if (($external_id === '') || (strpos($base, '-mwv2-') === false)) {
        return '';
    }

    return rtrim(str_replace('-mwv2-', '-uiv2-', $base), '/') . '/Invoice/Sales/Edit?id=' . rawurlencode((string) $external_id) . '&type=1';
}

/**
 * Asks after an invoice that was sent: its İşbaşı number, and from the
 * outgoing e-document list its GİB status, ETTN and any rejection note.
 *
 * @param array  $invoice  An erp_invoices row
 * @param string $job_id   The İşbaşı invoice id send_invoice() returned
 * @return array ['success' => bool, 'status' => 'sent'|'accepted'|'rejected'|'error',
 *                'gib_uuid' => string, 'gib_number' => string, 'kind' => 'einvoice'|'earchive'|'',
 *                'message' => string, 'error' => string,
 *                'party' => ['external_id', 'code', 'title'] - the card İşbaşı put the invoice on]
 */
function erp_edoc_isbasi_poll($invoice, $job_id)
{
    $job_id = trim((string) $job_id);
    $out = array('success' => false, 'status' => 'sent', 'gib_uuid' => '', 'gib_number' => '', 'kind' => '', 'message' => '', 'error' => '', 'party' => array());

    if ($job_id === '') {
        $out['error'] = lang('The invoice has no İşbaşı id to ask after.');
        return $out;
    }

    // 1. The invoice itself. What matters is under data.eGovernmentInvoice
    // (2026-09-21, read from the test environment):
    //   { eGovernmentType, invoiceTypeForEinvoice (1 e-Invoice, 2 e-Archive,
    //     3 e-Archive internet sale), eInvoiceProfile, description
    //     ("GİB'E GÖNDERİLECEK"), eStatus, sendMode, isSended, gibCode,
    //     rejectNote, ... }
    $detail = erp_edoc_isbasi_request('GET', '/api/v1.0/invoices/' . rawurlencode($job_id), null, array('doc_type' => 'invoice', 'doc_id' => (int) $invoice['id']));

    if ((string) ($detail['login_error'] ?? '') !== '') {
        $out['error'] = $detail['login_error'];
        return $out;
    }

    if (erp_edoc_isbasi_ok($detail)) {
        $data = is_array($detail['body']['data'] ?? null) ? $detail['body']['data'] : array();
        $egov = is_array($data['eGovernmentInvoice'] ?? null) ? $data['eGovernmentInvoice'] : array();

        $out['gib_number'] = erp_edoc_isbasi_pick($data, array('invoiceNumber', 'gibNumber'));

        // 1 e-Invoice; 2 and 3 are e-Archive (3 the internet sale).
        $type = (int) ($egov['invoiceTypeForEinvoice'] ?? 0);
        $out['kind'] = ($type === 1) ? 'einvoice' : ((($type === 2) || ($type === 3)) ? 'earchive' : '');
        $out['gib_uuid'] = erp_edoc_isbasi_pick($data, array('uuId', 'uuid', 'ettn', 'gibUuid'));

        // The customer card the invoice landed on: found by code, by name
        // and number, or opened for it (data.firm {id, code, name}).
        $firm = is_array($data['firm'] ?? null) ? $data['firm'] : array();

        if (trim((string) ($firm['id'] ?? '')) !== '') {
            $out['party'] = array(
                'external_id' => trim((string) $firm['id']),
                'code' => trim((string) ($firm['code'] ?? '')),
                'title' => trim((string) ($firm['name'] ?? '')),
            );
        }

        $description = trim((string) ($egov['description'] ?? ''));
        $reject = trim((string) ($egov['rejectNote'] ?? ''));
        $cancel = trim((string) ($data['cancelText'] ?? ''));
        $e_status = (int) ($egov['eStatus'] ?? 0);
        $out['message'] = erp_edoc_isbasi_join(array($description, $reject, $cancel));

        // The numbers are İşbaşı's own: the integration report documents 1, 4
        // and 8 as still running and 10 as "GİB işlemi başarıyla tamamlandı",
        // and 0 is what a freshly written invoice carries ("GİB'E
        // GÖNDERİLECEK"). Anything else is read from the words.
        if (!empty($data['isCancelled'])) {
            $out['status'] = 'cancelled';
        } elseif ($cancel !== '') {
            $out['status'] = 'rejected';
        } elseif ($reject !== '') {
            $out['status'] = 'rejected';
        } elseif ($e_status === 10) {
            $out['status'] = 'accepted';
        } elseif (preg_match('/hata|error|başarısız|fail/iu', $description)) {
            $out['status'] = 'error';
        } elseif (($e_status === 0) && empty($egov['isSended'])) {
            // Saved at İşbaşı, not yet handed to GİB. Reading this as "sent"
            // hid the one step the operator still had to take.
            $out['status'] = 'created';
        } else {
            $out['status'] = 'sent';
        }

        $out['success'] = true;
    } elseif ((int) $detail['http_code'] === 404) {
        $out['status'] = 'error';
        $out['error'] = lang('İşbaşı no longer has an invoice with this id.');
        return $out;
    }

    // 2. Only when the ETTN is still unknown: the outgoing e-documents around
    // the invoice date, matched by the sales invoice id. The send answer
    // carries the ETTN, so this is for a document sent before that was read,
    // and the list stays empty until GİB has the document anyway.
    if ($out['gib_uuid'] !== '') {
        return $out;
    }

    $issue = strtotime((string) $invoice['issue_date']);
    $from = date('Y-m-d\TH:i:s', $issue - 86400);
    $to = date('Y-m-d\TH:i:s', min(time(), $issue + (40 * 86400)));
    $list_body = array(
        'filters' => array(
            array('columnName' => 'issueDate', 'operator' => 5, 'value' => $from),
            array('columnName' => 'issueDate', 'operator' => 2, 'value' => $to),
        ),
        'sorting' => array('issueDate' => -1),
        'paging' => array('currentPage' => 1, 'pageSize' => 100),
        'count' => false,
        'excel' => array('export' => false, 'allowedColumns' => null, 'lucaExport' => false),
    );
    $list = erp_edoc_isbasi_request('POST', '/api/v1.0/einvoices/GetOutgoingInvoiceDataList', $list_body,
        array('doc_type' => 'invoice', 'doc_id' => (int) $invoice['id'], 'content_type' => 'application/json-patch+json', 'accept' => 'text/plain, application/json'));

    if (erp_edoc_isbasi_ok($list)) {
        $rows = is_array($list['body']['data']['data'] ?? null) ? $list['body']['data']['data'] : array();

        foreach ($rows as $row) {
            // The list names a document by its GİB number, in a field called
            // 'invoiceId' (observed 2026-09-21: invoiceId REE2026000000172).
            // The numeric id given at creation appears as 'salesInvoiceId'
            // when it appears at all, so either one identifies the row.
            $row_id = trim((string) ($row['salesInvoiceId'] ?? ''));
            $row_no = trim((string) ($row['invoiceId'] ?? ''));
            $want_no = ($out['gib_number'] !== '') ? $out['gib_number'] : trim((string) ($invoice['gib_number'] ?? ''));

            if (!((($row_id !== '') && ($row_id === $job_id))
                || (($row_no !== '') && ($want_no !== '') && (strcasecmp($row_no, $want_no) === 0)))) {
                continue;
            }

            if (trim((string) ($row['uuId'] ?? '')) !== '') {
                $out['gib_uuid'] = trim((string) $row['uuId']);
            }

            $status_text = trim((string) ($row['status'] ?? ''));
            $reject = trim((string) ($row['rejectNot'] ?? ''));
            $out['message'] = erp_edoc_isbasi_join(array($status_text, $reject, (string) ($row['message'] ?? '')));

            // İşbaşı's own words decide. An ETTN alone does not: a document
            // handed over a minute ago carries one and still reads "HENÜZ
            // İŞLENMEDİ", so promoting it to "accepted" would tell the
            // operator GİB had taken a document GİB has not seen.
            if (preg_match('/red|reject|iptal|cancel/iu', $status_text . ' ' . $reject)) {
                $out['status'] = 'rejected';
            } elseif (preg_match('/hata|error|başarısız|fail/iu', $status_text)) {
                $out['status'] = 'error';
            } elseif (preg_match('/başar|tamamlan|kabul|onayl/iu', $status_text)) {
                $out['status'] = 'accepted';
            }

            break;
        }
    } elseif (!erp_edoc_isbasi_ok($detail)) {
        $out['error'] = erp_edoc_isbasi_error_text($list);
        return $out;
    }

    $out['success'] = true;

    return $out;
}

/**
 * The rendered document (by ETTN) or the UBL file (by İşbaşı invoice id)
 * of a sent invoice.
 *
 * What actually comes back is not always what the format asks for, so the
 * answer is taken at its word and the bytes are looked at (2026-09-21, test
 * environment): fileFormat=PDF gave type "HTML" and an HTML rendering for a
 * document GİB had not taken yet, and the UBL came as type "zip" named
 * 20260921101032.zip - an archive around the XML, not the XML.
 *
 * @param array  $invoice  An erp_invoices row; gib_uuid and edoc_external_id are read
 * @param string $format   'pdf' | 'xml'
 * @return array ['success' => bool, 'content' => string (binary), 'filename' => string,
 *                'mime' => string, 'error' => string]
 */
function erp_edoc_isbasi_fetch_document($invoice, $format = 'pdf')
{
    $format = strtolower((string) $format);
    $none = array('success' => false, 'content' => '', 'filename' => '', 'mime' => '', 'error' => '');
    $external_id = trim((string) ($invoice['edoc_external_id'] ?? ''));
    $uuid = trim((string) ($invoice['gib_uuid'] ?? ''));

    if ($format === 'xml') {
        if ($external_id === '') {
            return array_merge($none, array('error' => lang('The invoice has no İşbaşı id.')));
        }

        $path = '/api/v1.0/einvoices/DocumentUblData?invoiceId=' . rawurlencode($external_id) . '&type=1';
    } else {
        if ($uuid === '') {
            return array_merge($none, array('error' => lang('The invoice has no ETTN yet; ask after its status first.')));
        }

        $path = '/api/v1.0/einvoices/DocumentDatawithuuid?uuid=' . rawurlencode($uuid) . '&fileFormat=PDF';
    }

    $result = erp_edoc_isbasi_request('GET', $path, null,
        array('doc_type' => 'invoice', 'doc_id' => (int) $invoice['id'], 'content_type' => 'application/json-patch+json', 'accept' => 'text/plain, application/json'));

    // Before GİB has the document the ETTN address answers with the HTML
    // preview rather than a PDF. The e-Archive portal has its own printer
    // (Logo support, 2026-09-21), keyed by the İşbaşı id; it is tried when
    // the first answer is not a PDF, so the operator gets the real thing
    // whenever one exists. POST with a literal null body, as documented.
    if (($format !== 'xml') && ($external_id !== '') && !erp_edoc_isbasi_pdf_answer($result)) {
        $portal = erp_edoc_isbasi_request('POST', '/api/v1.0/einvoices/print-eaportal-invoice-pdf?invoiceId=' . rawurlencode($external_id), 'null',
            array('doc_type' => 'invoice', 'doc_id' => (int) $invoice['id'], 'accept' => 'text/plain, application/json'));

        if (erp_edoc_isbasi_pdf_answer($portal)) {
            $result = $portal;
        }
    }

    if ((string) ($result['login_error'] ?? '') !== '') {
        return array_merge($none, array('error' => $result['login_error']));
    }

    if (!erp_edoc_isbasi_ok($result)) {
        return array_merge($none, array('error' => erp_edoc_isbasi_error_text($result)));
    }

    $data = is_array($result['body']['data'] ?? null) ? $result['body']['data'] : array();
    $content = base64_decode((string) ($data['content'] ?? ''), true);

    if (($content === false) || ($content === '')) {
        return array_merge($none, array('error' => lang('İşbaşı returned no document content.')));
    }

    $name = trim((string) ($data['name'] ?? ''));
    $kind = erp_edoc_isbasi_document_kind($content, $name, (string) ($data['type'] ?? ''));

    return array(
        'success' => true,
        'content' => $content,
        'filename' => ($name !== '') ? $name : ((string) $invoice['full_number'] . '.' . $kind['extension']),
        'mime' => $kind['mime'],
        'error' => '',
    );
}

/**
 * Joins what İşbaşı says about one document without repeating itself: the
 * same sentence often arrives in two fields at once, and "İşlem Bekliyor. —
 * İşlem Bekliyor." on a status line helps nobody.
 *
 * @param array $parts
 * @return string
 */
function erp_edoc_isbasi_join($parts)
{
    $said = array();
    $seen = array();

    foreach ((array) $parts as $part) {
        $part = trim((string) $part);
        $key = mb_strtolower($part, 'UTF-8');

        if (($part === '') || in_array($key, $seen, true)) {
            continue;
        }

        $seen[] = $key;
        $said[] = $part;
    }

    return implode(' — ', $said);
}

/**
 * Whether a document answer actually carries a PDF. İşbaşı hands back the
 * same envelope for its HTML preview and for the signed document, so the
 * bytes are what decides - not the 'type' it puts beside them.
 *
 * @param array $result  An erp_edoc_http() result
 * @return bool
 */
function erp_edoc_isbasi_pdf_answer($result)
{
    if (!erp_edoc_isbasi_ok($result)) {
        return false;
    }

    $data = is_array($result['body']['data'] ?? null) ? $result['body']['data'] : array();
    $content = base64_decode((string) ($data['content'] ?? ''), true);

    return (is_string($content) && (strncmp($content, '%PDF', 4) === 0));
}

/**
 * What a returned document actually is. The bytes decide; the name and the
 * provider's own word for it only fill in what the bytes leave open.
 *
 * @param string $content  The decoded file
 * @param string $name     The name the provider gave it, if any
 * @param string $type     The provider's word for it ("HTML", "zip", "PDF")
 * @return array ['mime' => string, 'extension' => string]
 */
function erp_edoc_isbasi_document_kind($content, $name = '', $type = '')
{
    $head = ltrim(mb_substr((string) $content, 0, 400));
    $extension = strtolower((string) pathinfo((string) $name, PATHINFO_EXTENSION));
    $type = strtolower(trim((string) $type));

    if (strncmp((string) $content, '%PDF', 4) === 0) {
        return array('mime' => 'application/pdf', 'extension' => 'pdf');
    }

    if (strncmp((string) $content, 'PK', 2) === 0) {
        return array('mime' => 'application/zip', 'extension' => 'zip');
    }

    if ((stripos($head, '<!doctype html') === 0) || (stripos($head, '<html') === 0) || ($type === 'html')) {
        return array('mime' => 'text/html; charset=utf-8', 'extension' => 'html');
    }

    if ((strncmp($head, '<?xml', 5) === 0) || ($type === 'xml') || ($extension === 'xml')) {
        return array('mime' => 'application/xml', 'extension' => 'xml');
    }

    return array('mime' => 'application/octet-stream', 'extension' => ($extension !== '') ? $extension : 'bin');
}

/**
 * Whether an answer was GİB's date order, and the latest date İşbaşı named.
 *
 * The refusal reads (2026-09-23, test environment): "Girilen tarihten sonra
 * aynı tipte fatura kesilmiş. En son fatura Tarihi :21-09-2026 10:00" - the
 * latest document of the same type (e-Fatura and e-Arşiv are counted apart),
 * day-month-year and a time. That date is the one the invoice has to be at
 * or after; the store's own record cannot know it when the other document
 * was issued on the İşbaşı screen.
 *
 * @param string $message  The provider's answer as the invoice keeps it
 * @return array ['refused' => bool, 'latest' => 'Y-m-d' or '', 'latest_time' => 'H:i' or '']
 */
function erp_edoc_isbasi_date_refusal($message)
{
    $message = (string) $message;
    $out = array('refused' => false, 'latest' => '', 'latest_time' => '');

    // Matched from the second word on, in lower case: PCRE does not fold the
    // dotted capital İ, and the sentence starts with a capital G anyway.
    if (!preg_match('/tarihten sonra ayn\x{0131} tipte/u', $message)) {
        return $out;
    }

    $out['refused'] = true;

    if (preg_match('/(\d{2})[-.\/](\d{2})[-.\/](\d{4})(?:\s+(\d{2}):(\d{2}))?/', $message, $found)
        && checkdate((int) $found[2], (int) $found[1], (int) $found[3])) {
        $out['latest'] = $found[3] . '-' . $found[2] . '-' . $found[1];
        $out['latest_time'] = isset($found[4]) ? ($found[4] . ':' . $found[5]) : '';
    }

    return $out;
}

/* ---------------------------------------------------------------------------
   Incoming e-invoices
   --------------------------------------------------------------------------- */

/**
 * The e-government type of an incoming invoice as its UBL code. The list
 * carries a number (eGovermentType) that came back 0 for every row in the
 * test environment, and beside it the type in words (EGovermentTypeDesc:
 * "Satış", "Tevkifat", "İade", "İstisna", "Özel Matrah"), which is what is
 * read here; a row of type 3 ("Satış İade Faturası") is a return whatever
 * the words say. The document's UBL is what finally says, and replaces this
 * once the document is read.
 *
 * @param array $row  A myInvoicesList row
 * @return string  A UBL InvoiceTypeCode, or ''
 */
function erp_edoc_isbasi_inbox_type($row)
{
    if ((int) ($row['type'] ?? 0) === 3) {
        return 'IADE';
    }

    $words = mb_strtoupper(trim((string) ($row['EGovermentTypeDesc'] ?? ($row['eGovermentTypeDesc'] ?? ''))), 'UTF-8');
    $words = preg_replace('/[^A-Z]/', '', strtr($words, array('Ş' => 'S', 'İ' => 'I', 'Ö' => 'O', 'Ü' => 'U', 'Ç' => 'C', 'Ğ' => 'G')));

    $known = array('SATIS', 'IADE', 'TEVKIFAT', 'ISTISNA', 'OZELMATRAH', 'IHRACKAYITLI', 'KONAKLAMAVERGISI',
        'TEKNOLOJIDESTEK', 'YTBSATIS', 'YTBISTISNA', 'YTBTEVKIFAT', 'SGK');

    return in_array($words, $known, true) ? $words : '';
}

/**
 * One page of the invoices suppliers sent the store, by issue date.
 *
 * POST einvoices/myInvoicesList (2026-09-23, test environment): the answer
 * is data {count, data[]}; a row names the document by its GİB number in
 * invoiceId, carries the ETTN in uuId, the payable amount in amount and the
 * VAT base in totalVatBase, the currency as "TL" for lira, and the status
 * of the document at GİB in status ("Kabul").
 *
 * @param string $from  Y-m-d
 * @param string $to    Y-m-d
 * @param int    $page  From 1
 * @return array ['success' => bool, 'error' => string, 'items' => array, 'total' => int, 'pages' => int]
 */
function erp_edoc_isbasi_inbox($from, $to, $page = 1)
{
    $none = array('success' => false, 'error' => '', 'items' => array(), 'total' => 0, 'pages' => 0);
    $page = max(1, (int) $page);
    // The largest page İşbaşı offers (15, 30, 60, 100).
    $size = 100;

    $body = array(
        'filters' => array(
            array('columnName' => 'issueDate', 'operator' => 5, 'value' => $from . 'T00:00:00'),
            array('columnName' => 'issueDate', 'operator' => 2, 'value' => $to . 'T23:59:59.999'),
        ),
        'sorting' => array('issueDate' => 1),
        'paging' => array('currentPage' => $page, 'pageSize' => $size),
        'columnNames' => null,
        'count' => true,
        'excel' => array('export' => false, 'allowedColumns' => null, 'lucaExport' => false),
    );

    $result = erp_edoc_isbasi_request('POST', '/api/v1.0/einvoices/myInvoicesList', $body, array('doc_type' => 'inbox'));

    if ((string) ($result['login_error'] ?? '') !== '') {
        return array_merge($none, array('error' => $result['login_error']));
    }

    if (!erp_edoc_isbasi_ok($result)) {
        return array_merge($none, array('error' => erp_edoc_isbasi_error_text($result)));
    }

    $data = is_array($result['body']['data'] ?? null) ? $result['body']['data'] : array();
    $rows = is_array($data['data'] ?? null) ? $data['data'] : array();
    $total = (int) ($data['count'] ?? count($rows));
    $items = array();

    foreach ($rows as $row) {
        $currency = strtoupper(trim((string) ($row['currency'] ?? '')));

        $items[] = array(
            'external_id' => (string) ($row['invoiceId'] ?? ''),
            'gib_uuid' => trim((string) ($row['uuId'] ?? '')),
            'gib_number' => (string) ($row['invoiceId'] ?? ''),
            'invoice_type' => erp_edoc_isbasi_inbox_type($row),
            'profile' => (string) ($row['invoiceType'] ?? ''),
            'issue_date' => substr((string) ($row['issueDate'] ?? ''), 0, 10),
            'supplier_title' => (string) ($row['supplier'] ?? ''),
            'supplier_tax_number' => (string) ($row['supplierTcknVkn'] ?? ''),
            'currency' => (($currency === 'TL') || ($currency === '')) ? 'TRY' : $currency,
            'tax_base' => (int) round(((float) ($row['totalVatBase'] ?? 0)) * 100),
            'total' => (int) round(((float) ($row['amount'] ?? 0)) * 100),
            'provider_status' => (string) ($row['status'] ?? ''),
        );
    }

    return array(
        'success' => true,
        'error' => '',
        'items' => $items,
        'total' => $total,
        'pages' => max(1, (int) ceil($total / $size)),
    );
}

/**
 * An incoming invoice's UBL or PDF, by its ETTN.
 *
 * The UBL of an incoming invoice has its own address -
 * einvoices/DocumentUblDatawithuuid - and comes as a zip around the XML,
 * like the outgoing one; the PDF is the same ETTN address the outgoing
 * documents use and gives a real PDF for a received invoice (2026-09-23).
 * A document İşbaşı has no UBL for answers 400 "UBL bulunamadı!", which is
 * passed on as it is.
 *
 * @param array  $item    An erp_edoc_inbox row (gib_uuid, id)
 * @param string $format  'ubl' | 'pdf'
 * @return array ['success' => bool, 'content' => string, 'filename' => string, 'mime' => string, 'error' => string]
 */
function erp_edoc_isbasi_inbox_document($item, $format = 'ubl')
{
    $none = array('success' => false, 'content' => '', 'filename' => '', 'mime' => '', 'error' => '');
    $uuid = trim((string) ($item['gib_uuid'] ?? ''));

    if ($uuid === '') {
        return array_merge($none, array('error' => lang('The document has no ETTN.')));
    }

    $path = ((string) $format === 'pdf')
        ? '/api/v1.0/einvoices/DocumentDatawithuuid?uuid=' . rawurlencode($uuid) . '&fileFormat=PDF'
        : '/api/v1.0/einvoices/DocumentUblDatawithuuid?uuid=' . rawurlencode($uuid) . '&type=1';

    $result = erp_edoc_isbasi_request('GET', $path, null,
        array('doc_type' => 'inbox', 'doc_id' => (int) ($item['id'] ?? 0), 'content_type' => 'application/json-patch+json', 'accept' => 'text/plain, application/json'));

    if ((string) ($result['login_error'] ?? '') !== '') {
        return array_merge($none, array('error' => $result['login_error']));
    }

    if (!erp_edoc_isbasi_ok($result)) {
        return array_merge($none, array('error' => erp_edoc_isbasi_error_text($result)));
    }

    $data = is_array($result['body']['data'] ?? null) ? $result['body']['data'] : array();
    $content = base64_decode((string) ($data['content'] ?? ''), true);

    if (($content === false) || ($content === '')) {
        return array_merge($none, array('error' => lang('İşbaşı returned no document content.')));
    }

    $name = trim((string) ($data['name'] ?? ''));
    $kind = erp_edoc_isbasi_document_kind($content, $name, (string) ($data['type'] ?? ''));

    return array(
        'success' => true,
        'content' => $content,
        'filename' => ($name !== '') ? $name : ($uuid . '.' . $kind['extension']),
        'mime' => $kind['mime'],
        'error' => '',
    );
}

/* ---------------------------------------------------------------------------
   Customer and supplier cards (Müşteri & Tedarikçi)
   --------------------------------------------------------------------------- */

/**
 * İşbaşı's firm type as the ERP's account kind. The numbers are the panel's
 * own filter (Müşteri & Tedarikçi list, 2026-09-23): 1 customer, 2 supplier,
 * 3 both.
 *
 * @param mixed $firm_type
 * @return string  'customer' | 'supplier' | 'both'
 */
function erp_edoc_isbasi_kind($firm_type)
{
    $firm_type = (int) $firm_type;

    return ($firm_type === 2) ? 'supplier' : (($firm_type === 3) ? 'both' : 'customer');
}

/**
 * The account kind as İşbaşı's firm type.
 *
 * @param string $kind
 * @return int
 */
function erp_edoc_isbasi_firm_type($kind)
{
    return ((string) $kind === 'supplier') ? 2 : (((string) $kind === 'both') ? 3 : 1);
}

/**
 * The ISO code of a country İşbaşı names. The list carries the name only
 * ("Türkiye"); a card read whole carries the code as well, and that wins.
 *
 * @param string $name
 * @param string $code
 * @return string  Two letters, or '' when the name is not known
 */
function erp_edoc_isbasi_country_code($name, $code = '')
{
    $code = strtoupper(trim((string) $code));

    if (preg_match('/^[A-Z]{2}$/', $code)) {
        return $code;
    }

    $name = trim((string) $name);

    if ($name === '') {
        return '';
    }

    $folded = mb_strtolower(strtr($name, array('İ' => 'i', 'I' => 'ı')), 'UTF-8');

    if (in_array($folded, array('türkiye', 'turkiye', 'turkey', 'türkiye cumhuriyeti'), true)) {
        return 'TR';
    }

    $found = db_value("SELECT code FROM countries WHERE LOWER(name) = LOWER('" . escape($name) . "') LIMIT 1");

    return preg_match('/^[A-Z]{2}$/', strtoupper((string) $found)) ? strtoupper((string) $found) : '';
}

/**
 * A firm as the card shape includes/erp/edoc/account_sync.php works with.
 *
 * The list and the detail name the same things differently (2026-09-23,
 * test environment): the list row carries vknTckn, town and a name that is
 * already "first last" for a person, while the detail carries
 * taxOrPersonalId, district, countryCode and the name parts separately.
 * The list's firstname/lastname columns do not mean that for a company
 * (they hold the display name), so the list's name is the title.
 *
 * @param array $firm
 * @param bool  $detail  true for a GET firms/{id} answer
 * @return array
 */
function erp_edoc_isbasi_card($firm, $detail = false)
{
    $is_person = !empty($firm['isPersonalCompany']);
    $name = trim((string) ($firm['name'] ?? ''));

    if ($detail && $is_person) {
        $parts = trim(trim((string) ($firm['firstName'] ?? '')) . ' ' . trim((string) ($firm['lastName'] ?? '')));
        $title = ($parts !== '') ? $parts : trim((string) ($firm['fullName'] ?? ''));
    } elseif (!$detail && $is_person && ($name === '')) {
        $title = trim(trim((string) ($firm['firstname'] ?? '')) . ' ' . trim((string) ($firm['lastname'] ?? '')));
    } else {
        $title = $name;
    }

    if ($title === '') {
        $title = ($name !== '') ? $name : trim((string) ($firm['fullName'] ?? ($firm['displayName'] ?? '')));
    }

    $tax_number = $detail ? ($firm['taxOrPersonalId'] ?? '') : ($firm['vknTckn'] ?? ($firm['taxOrPersonalId'] ?? ''));
    $district = $detail ? ($firm['district'] ?? '') : ($firm['town'] ?? ($firm['district'] ?? ''));
    $updated = strtotime((string) ($firm['modificationDate'] ?? ''));

    return array(
        'external_id' => trim((string) ($firm['id'] ?? '')),
        'code' => trim((string) ($firm['code'] ?? '')),
        'title' => preg_replace('/\s+/u', ' ', $title),
        'is_person' => $is_person,
        // Letters are kept so that a mistyped number stays mistyped: stripped
        // to its digits, "1234567890abc" would pass for a real VKN.
        'tax_number' => strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $tax_number)),
        'tax_office' => trim((string) ($firm['taxOffice'] ?? '')),
        'email' => trim((string) ($firm['emailAddress'] ?? '')),
        'phone' => trim((string) ($firm['phone'] ?? '')),
        'address' => trim((string) ($firm['address'] ?? '')),
        'district' => trim((string) $district),
        'city' => trim((string) ($firm['city'] ?? '')),
        'country_code' => erp_edoc_isbasi_country_code((string) ($firm['country'] ?? ''), (string) ($firm['countryCode'] ?? '')),
        'postcode' => trim((string) ($firm['postalCode'] ?? '')),
        'kind' => erp_edoc_isbasi_kind($firm['firmType'] ?? 1),
        'is_active' => !array_key_exists('isActive', $firm) || !empty($firm['isActive']),
        'updated_at' => ($updated !== false) ? (int) $updated : 0,
    );
}

/**
 * A phone number in the form most İşbaşı cards carry it: a Turkish number
 * as 0 and its ten digits (+90, 0090 and a bare ten digits all become that);
 * anything else as it was typed. The comparison reads the forms as one line
 * (erp_edoc_account_norm()), so the card does not look different for it.
 *
 * @param string $phone
 * @return string
 */
function erp_edoc_isbasi_phone($phone)
{
    $phone = trim((string) $phone);
    $digits = preg_replace('/\D/', '', $phone);

    if (((strlen($digits) === 14) && (strpos($digits, '0090') === 0))
        || ((strlen($digits) === 12) && (strpos($digits, '90') === 0))
        || ((strlen($digits) === 11) && ($digits[0] === '0'))
        || ((strlen($digits) === 10) && ($digits[0] !== '0'))) {
        return '0' . substr($digits, -10);
    }

    return $phone;
}

/**
 * One page of the store's customer and supplier cards at İşbaşı.
 *
 * POST firms/firms (2026-09-23, test environment) answers data {count,
 * data[]} like the other lists. Sorted by id so that the pages do not shift
 * under a read: many cards share a name, and a sort on the name repeats or
 * skips cards at the page boundaries.
 *
 * @param int $page  From 1
 * @return array ['success' => bool, 'error' => string, 'items' => cards, 'total' => int, 'pages' => int]
 */
function erp_edoc_isbasi_accounts($page = 1)
{
    $none = array('success' => false, 'error' => '', 'items' => array(), 'total' => 0, 'pages' => 0);
    $page = max(1, (int) $page);
    // The largest page the panel offers (15, 30, 60, 100).
    $size = 100;

    $body = array(
        'filters' => array(),
        'sorting' => array('id' => 'Asc'),
        'paging' => array('currentPage' => $page, 'pageSize' => $size),
        'count' => true,
        'excel' => array('export' => false),
    );

    $result = erp_edoc_isbasi_request('POST', '/api/v1.0/firms/firms', $body, array('doc_type' => 'account'));

    if ((string) ($result['login_error'] ?? '') !== '') {
        return array_merge($none, array('error' => $result['login_error']));
    }

    if (!erp_edoc_isbasi_ok($result)) {
        return array_merge($none, array('error' => erp_edoc_isbasi_error_text($result)));
    }

    $data = is_array($result['body']['data'] ?? null) ? $result['body']['data'] : array();
    $rows = is_array($data['data'] ?? null) ? $data['data'] : array();
    $total = (int) ($data['count'] ?? count($rows));
    $items = array();

    foreach ($rows as $row) {
        if (is_array($row)) {
            $items[] = erp_edoc_isbasi_card($row, false);
        }
    }

    return array(
        'success' => true,
        'error' => '',
        'items' => $items,
        'total' => $total,
        'pages' => max(1, (int) ceil($total / $size)),
    );
}

/**
 * One card, read whole (GET firms/{id}). 'firm' is İşbaşı's own object, the
 * base account_save() writes back.
 *
 * @param string $external_id
 * @return array ['success' => bool, 'error' => string, 'card' => array, 'firm' => array]
 */
function erp_edoc_isbasi_account($external_id)
{
    $none = array('success' => false, 'error' => '', 'card' => array(), 'firm' => array());
    $external_id = trim((string) $external_id);

    if (!preg_match('/^[0-9A-Za-z\-]{1,64}$/', $external_id)) {
        return array_merge($none, array('error' => lang('The card has no İşbaşı id.')));
    }

    $result = erp_edoc_isbasi_request('GET', '/api/v1.0/firms/' . rawurlencode($external_id), null, array('doc_type' => 'account'));

    if ((string) ($result['login_error'] ?? '') !== '') {
        return array_merge($none, array('error' => $result['login_error']));
    }

    if (!erp_edoc_isbasi_ok($result) || !is_array($result['body']['data'] ?? null)) {
        return array_merge($none, array('error' => erp_edoc_isbasi_error_text($result)));
    }

    $firm = $result['body']['data'];

    return array('success' => true, 'error' => '', 'card' => erp_edoc_isbasi_card($firm, true), 'firm' => $firm);
}

/**
 * Creates a card at İşbaşı ($external_id '') or changes one.
 *
 * PUT firms takes the whole FirmItemDetails object, and its required fields
 * include the opening balance and the balance. A change is therefore made
 * on the card as İşbaşı keeps it: read it (GET firms/{id}), change only the
 * fields named in $card, and put the rest back as it came - nothing of the
 * card's money, bank or e-document settings passes through Pinegrap.
 *
 * The name fields move together: İşbaşı keeps name, fullName and
 * displayName (and a person's first and last name), and a field that
 * mirrored the old title follows the new one; one that held something else
 * (a long legal title beside a short name) is left as it was.
 *
 * City and district travel as names. Their codes (cityCode, districtCode)
 * are emptied when the name changes, so an old code cannot contradict it.
 *
 * @param array  $card         Card fields to write (title, is_person, tax_number,
 *                             tax_office, email, phone, address, district, city,
 *                             country_code, postcode, kind, is_active; code on create)
 * @param string $external_id  '' to create
 * @return array ['success' => bool, 'error' => string, 'external_id' => string]
 */
function erp_edoc_isbasi_account_save($card, $external_id = '')
{
    $none = array('success' => false, 'error' => '', 'external_id' => '');
    $external_id = trim((string) $external_id);
    $card = (array) $card;

    if ($external_id !== '') {
        $read = erp_edoc_isbasi_account($external_id);

        if (!$read['success']) {
            return array_merge($none, array('error' => $read['error']));
        }

        $firm = $read['firm'];
        $old_title = (string) $read['card']['title'];
    } else {
        $firm = array(
            'id' => 0,
            'code' => '',
            'isActive' => true,
            'isPersonalCompany' => false,
            'isForeign' => false,
            'name' => '',
            'firstName' => null,
            'lastName' => null,
            'fullName' => '',
            'displayName' => '',
            'taxOrPersonalId' => '',
            'taxOffice' => '',
            'country' => 'Türkiye',
            'countryCode' => 'TR',
            'city' => '',
            'cityCode' => '',
            'district' => '',
            'districtCode' => '',
            'state' => '',
            'stateCode' => '',
            'stateTINCode' => '',
            'postalCode' => '',
            'address' => '',
            'phone' => '',
            'webAddress' => '',
            'tags' => array(),
            'category' => null,
            'phoneNumbers' => array(),
            'emailAddress' => '',
            'employees' => array(),
            'shippingAddresses' => array(),
            'banks' => array(),
            'eInvoiceResponsible' => false,
            'eInvoiceProfile' => 0,
            'eInvoiceSenderLabel' => '',
            'eDispatchResponsible' => false,
            'eDispatchSenderLabel' => '',
            'genericCustomer' => false,
            'notApplyVat' => false,
            'notApplyWithHolding' => false,
            'notApplyAdditionalTax' => false,
            'notApplyGST' => true,
            'mersisNo' => null,
            'tradeRegisterNumber' => null,
            'predefinedDescription' => null,
            'allowDuplicate' => false,
            'beginningBalance' => 0,
            'balance' => 0,
            'currency' => 'TL',
            'currencyBalance' => 0,
            'firmType' => 1,
        );
        $old_title = '';

        if (trim((string) ($card['code'] ?? '')) !== '') {
            $firm['code'] = mb_substr(trim((string) $card['code']), 0, 50);
        }

        // What the ERP learnt from GİB about the buyer, when it asked.
        if (array_key_exists('einvoice_user', $card)) {
            $firm['eInvoiceResponsible'] = !empty($card['einvoice_user']);

            if (!empty($card['einvoice_user']) && (trim((string) ($card['einvoice_alias'] ?? '')) !== '')) {
                $firm['eInvoicePostLabel'] = trim((string) $card['einvoice_alias']);
            }
        }
    }

    if (array_key_exists('is_person', $card)) {
        $firm['isPersonalCompany'] = !empty($card['is_person']);
    }

    if (array_key_exists('title', $card)) {
        $title = trim((string) $card['title']);

        if ($title === '') {
            return array_merge($none, array('error' => lang('Enter a name.')));
        }

        $mirrors = function ($value) use ($old_title) {
            $value = trim((string) $value);

            return ($value === '') || ($value === $old_title);
        };

        if (!empty($firm['isPersonalCompany'])) {
            $person = erp_edoc_person_name($title);
            $old_first = trim((string) ($firm['firstName'] ?? ''));
            $first = ($person !== null) ? $person['first'] : $title;
            $last = ($person !== null) ? $person['last'] : '';

            foreach (array('name', 'displayName') as $key) {
                $value = trim((string) ($firm[$key] ?? ''));

                if (($value !== '') && ($value === $old_first)) {
                    $firm[$key] = $first;
                } elseif ($mirrors($value)) {
                    $firm[$key] = $title;
                }
            }

            if ($mirrors($firm['fullName'] ?? '')) {
                $firm['fullName'] = $title;
            }

            $firm['firstName'] = $first;
            $firm['lastName'] = $last;
        } else {
            foreach (array('fullName', 'displayName') as $key) {
                if ($mirrors($firm[$key] ?? '')) {
                    $firm[$key] = $title;
                }
            }

            $firm['name'] = $title;
        }
    }

    $plain = array(
        'tax_number' => 'taxOrPersonalId',
        'tax_office' => 'taxOffice',
        'email' => 'emailAddress',
        'phone' => 'phone',
        'address' => 'address',
        'postcode' => 'postalCode',
    );

    foreach ($plain as $field => $key) {
        if (array_key_exists($field, $card)) {
            if ($field === 'tax_number') {
                $firm[$key] = preg_replace('/\D/', '', (string) $card[$field]);
            } elseif ($field === 'phone') {
                // PUT firms keeps the number from phoneNumbers[0]; phone on
                // its own was answered with success and not kept
                // (2026-09-23, test environment). GET carries it in both.
                $firm[$key] = erp_edoc_isbasi_phone((string) $card[$field]);
                $numbers = is_array($firm['phoneNumbers'] ?? null) ? array_values($firm['phoneNumbers']) : array();
                $numbers[0] = $firm[$key];
                $firm['phoneNumbers'] = $numbers;
            } else {
                $firm[$key] = trim((string) $card[$field]);
            }
        }
    }

    foreach (array('city' => 'cityCode', 'district' => 'districtCode') as $field => $code_key) {
        if (array_key_exists($field, $card)) {
            $value = trim((string) $card[$field]);

            if ($value !== trim((string) ($firm[$field] ?? ''))) {
                $firm[$code_key] = '';
            }

            $firm[$field] = $value;
        }
    }

    if (array_key_exists('country_code', $card)) {
        $code = strtoupper(trim((string) $card['country_code']));
        $code = preg_match('/^[A-Z]{2}$/', $code) ? $code : 'TR';

        if ($code !== strtoupper(trim((string) ($firm['countryCode'] ?? '')))) {
            $firm['countryCode'] = $code;
            $firm['country'] = erp_edoc_isbasi_country_name($code);
        }

        $firm['isForeign'] = ($code !== 'TR');
    }

    if (array_key_exists('kind', $card)) {
        $firm['firmType'] = erp_edoc_isbasi_firm_type((string) $card['kind']);
    }

    if (array_key_exists('is_active', $card)) {
        $firm['isActive'] = !empty($card['is_active']);
    }

    $result = erp_edoc_isbasi_request('PUT', '/api/v1.0/firms', $firm, array('doc_type' => 'account'));

    if ((string) ($result['login_error'] ?? '') !== '') {
        return array_merge($none, array('error' => $result['login_error']));
    }

    if (!erp_edoc_isbasi_ok($result)) {
        return array_merge($none, array('error' => erp_edoc_isbasi_error_text($result)));
    }

    // data is the card's id (documented as "Güncellenen firma ID"); a
    // change keeps the id it was asked for when the answer carries none.
    $data = $result['body']['data'] ?? null;
    $id = is_scalar($data) ? trim((string) $data) : erp_edoc_isbasi_pick(is_array($data) ? $data : array(), array('id', 'firmId'));

    if (($id === '') || ($id === '0')) {
        $id = $external_id;
    }

    if ($id === '') {
        return array_merge($none, array('error' => lang(array(
            'string' => 'İşbaşı took the card but did not say its id. Read the list again to find it. (Answer fields: {var:1})',
            'vars' => erp_edoc_isbasi_key_names($result['body']),
        ))));
    }

    return array('success' => true, 'error' => '', 'external_id' => $id);
}

/* ---------------------------------------------------------------------------
   Small maps
   --------------------------------------------------------------------------- */

/**
 * UN/ECE unit codes as İşbaşı's unit names (Turkish, the way the İşbaşı
 * screens spell them).
 *
 * @param string $code
 * @return string
 */
function erp_edoc_isbasi_unit_name($code)
{
    $map = array(
        'C62' => 'Adet', 'KGM' => 'Kg', 'GRM' => 'Gr', 'MTR' => 'Metre', 'MTK' => 'm2', 'LTR' => 'Litre',
        'HUR' => 'Saat', 'DAY' => 'Gün', 'MON' => 'Ay', 'SET' => 'Set', 'PA' => 'Paket', 'BX' => 'Kutu',
    );

    return $map[strtoupper((string) $code)] ?? 'Adet';
}

/**
 * "7/10" style withholding rate text from a percentage.
 *
 * @param float $rate  e.g. 70 for 7/10
 * @return string
 */
function erp_edoc_isbasi_rate_text($rate)
{
    $tenths = (int) round(((float) $rate) / 10);

    return max(1, min(10, $tenths)) . '/10';
}

/**
 * ISO country code to the name İşbaşı prints.
 *
 * @param string $code
 * @return string
 */
function erp_edoc_isbasi_country_name($code)
{
    $code = strtoupper(trim((string) $code));

    if (($code === '') || ($code === 'TR')) {
        return 'Türkiye';
    }

    $name = db_value("SELECT name FROM countries WHERE code = '" . escape($code) . "' LIMIT 1");

    return ((string) $name !== '') ? (string) $name : $code;
}

/**
 * The payment agent text for an internet sale: the method's label.
 *
 * @param string $method  A Pinegrap payment method code
 * @return string
 */
function erp_edoc_isbasi_payment_label($method)
{
    if (function_exists('erp_payment_method_labels')) {
        $labels = erp_payment_method_labels();

        if (is_array($labels) && isset($labels[$method])) {
            return (string) $labels[$method];
        }
    }

    return (string) $method;
}
