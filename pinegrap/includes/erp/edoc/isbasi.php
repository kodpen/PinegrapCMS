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

// The host the Swagger file names; the platform call answers with the real
// API base for the account. The settings card lets a store override it.
define('ERP_EDOC_ISBASI_ENTRY_URL', 'https://isbasimw.isbasi.com');

// One day per the documentation; renewed an hour early.
define('ERP_EDOC_ISBASI_TOKEN_LIFETIME', 23 * 3600);

function erp_edoc_isbasi_info()
{
    return array(
        'label' => 'Logo İşbaşı',
        'description' => lang('Cloud pre-accounting by Logo. e-Invoice and e-Archive through the İşbaşı API; the store needs an İşbaşı subscription and an API key issued by Logo support.'),
        'docs_url' => 'https://developers.isbasi.com/',
        'capabilities' => array('einvoice', 'earchive'),
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
        array('name' => 'entry_url', 'label' => lang('Entry address'), 'type' => 'text', 'required' => false,
            'help' => lang(array('string' => 'Where the platform query goes; the API address itself comes back from that query. Leave empty for {var:1}.', 'vars' => ERP_EDOC_ISBASI_ENTRY_URL))),
    );
}

/* ---------------------------------------------------------------------------
   Session
   --------------------------------------------------------------------------- */

/**
 * The credentials with the entry address filled in.
 *
 * @param array|null $credentials  Stored ones when null
 * @return array
 */
function erp_edoc_isbasi_credentials($credentials = null)
{
    $credentials = is_array($credentials) ? $credentials : erp_edoc_credentials('isbasi');

    foreach (array('api_key', 'username', 'password', 'entry_url') as $name) {
        $credentials[$name] = trim((string) ($credentials[$name] ?? ''));
    }

    if ($credentials['entry_url'] === '') {
        $credentials['entry_url'] = ERP_EDOC_ISBASI_ENTRY_URL;
    }

    $credentials['entry_url'] = rtrim($credentials['entry_url'], '/');

    return $credentials;
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

    // 1. Which platform, which base URL.
    $platform_url = $credentials['entry_url'] . '/api/v1.0/user/getplatform';
    $platform_body = array('isonedayvalidtoken' => false, 'username' => $credentials['username'], 'password' => $credentials['password']);
    $platform = erp_edoc_http('POST', $platform_url, $json_headers, $platform_body);
    erp_edoc_log('isbasi', $log_type, $log_id, 'POST', $platform_url, $platform, $platform_body);

    $base_url = '';
    $platform_data = array();

    if (erp_edoc_isbasi_ok($platform)) {
        $platform_data = is_array($platform['body']['data'] ?? null) ? $platform['body']['data'] : array();
        $base_url = rtrim(erp_edoc_isbasi_pick($platform_data, array('baseUrl', 'baseURL', 'base_url')), '/');
    } elseif (in_array((int) $platform['http_code'], array(401, 403), true)) {
        // A refused key or password; the login proper would only say it again.
        return array('success' => false, 'session' => array(), 'error' => erp_edoc_isbasi_error_text($platform));
    }

    // The documentation says the base URL comes from this step; when the
    // platform call does not give one, the entry address is tried as the
    // base so a single-host account still works.
    if ($base_url === '') {
        $base_url = $credentials['entry_url'];
    }

    // 2. The login proper.
    $login_url = $base_url . '/api/v1.0/user/integrationLogin';
    $login_body = array('username' => $credentials['username'], 'password' => $credentials['password']);
    $login = erp_edoc_http('POST', $login_url, $json_headers, $login_body);
    erp_edoc_log('isbasi', $log_type, $log_id, 'POST', $login_url, $login, $login_body);

    if (!erp_edoc_isbasi_ok($login)) {
        return array('success' => false, 'session' => array(), 'error' => erp_edoc_isbasi_error_text($login));
    }

    $data = is_array($login['body']['data'] ?? null) ? $login['body']['data'] : $login['body'];
    $token = erp_edoc_isbasi_pick($data, array('accessToken', 'access_token', 'token', 'Token', 'bearerToken', 'jwt'));
    $tenant = erp_edoc_isbasi_pick($data, array('tenantId', 'tenantID', 'TenantId', 'tenant_id', 'tenant'));

    if ($token === '') {
        return array('success' => false, 'session' => array(), 'error' => lang(array(
            'string' => 'İşbaşı signed in but the answer carries no access token under a known name (fields: {var:1}). Send this line to Logo support or to the developer.',
            'vars' => erp_edoc_isbasi_key_names($data),
        )));
    }

    $session = array(
        'base_url' => $base_url,
        'access_token' => $token,
        'tenant_id' => $tenant,
        'user_id' => erp_edoc_isbasi_pick($data, array('userId', 'UserId', 'user_id', 'id')),
        'user_email' => erp_edoc_isbasi_pick($data, array('email', 'userEmail', 'UserEmail', 'emailAddress')),
        'platform' => erp_edoc_isbasi_pick($platform_data, array('Platform', 'platform')),
        'is_test_user' => !empty($platform_data['IsTestUser']) || !empty($platform_data['isTestUser']),
        'is_canary' => !empty($platform_data['isCanary']),
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
    return substr(md5($credentials['api_key'] . '|' . $credentials['username'] . '|' . $credentials['password'] . '|' . $credentials['entry_url']), 0, 16);
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

    if ($session['platform'] !== '') {
        $notes[] = lang(array('string' => 'platform {var:1}', 'vars' => $session['platform']));
    }

    if ($session['tenant_id'] !== '') {
        $notes[] = lang(array('string' => 'tenant …{var:1}', 'vars' => substr($session['tenant_id'], -4)));
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
 * @param array $options  'party' (erp_edoc_invoice_party() output) when the caller has it
 * @return array ['success' => bool, 'external_id' => string, 'status' => 'sent', 'error' => string]
 */
function erp_edoc_isbasi_send_invoice($invoice, $lines, $options = array())
{
    $fail = function ($error) {
        return array('success' => false, 'external_id' => '', 'status' => 'error', 'error' => $error);
    };

    if ((string) ($invoice['direction'] ?? 'sales') !== 'sales') {
        return $fail(lang('Only sales invoices go to İşbaşı.'));
    }

    if ((string) ($invoice['doc_type'] ?? 'invoice') !== 'invoice') {
        return $fail(lang('Return and proforma documents cannot be sent yet: the İşbaşı code for them is not in the public documentation.'));
    }

    if ((string) ($invoice['invoice_type'] ?? 'SATIS') !== 'SATIS') {
        return $fail(lang(array('string' => 'A {var:1} invoice cannot be sent yet: the İşbaşı e-government type code for it is not in the public documentation.', 'vars' => (string) $invoice['invoice_type'])));
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

    return array('success' => true, 'external_id' => $external_id, 'status' => 'sent', 'error' => '');
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

    if (trim((string) $party['title']) === '') {
        return array('error' => lang('The invoice has no counterparty name.'));
    }

    $customer = array(
        'code' => '',
        'name' => (string) $party['title'],
        'email' => (string) $party['email'],
        'tcknVkn' => $tax_number,
        'taxOffice' => (string) $party['tax_office'],
        'country' => erp_edoc_isbasi_country_name((string) $party['country_code']),
        'city' => (string) $party['city'],
        'district' => (string) $party['district'],
        'address' => trim((string) $party['address'] . ((string) $party['postcode'] !== '' ? ' ' . $party['postcode'] : '')),
        'isPerson' => $is_person,
    );

    if ($is_person) {
        // İşbaşı matches a person by first and last name; the title is one
        // string here, so the last word is the surname.
        $parts = preg_split('/\s+/', trim((string) $party['title']));
        $customer['lastName'] = (count($parts) > 1) ? array_pop($parts) : '';
        $customer['firstName'] = implode(' ', $parts);
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

        if (trim((string) $invoice['carrier_title']) === '') {
            return array('error' => lang('An internet-sale e-Archive invoice needs the carrier (title and VKN) on the invoice; İşbaşı requires it.'));
        }

        $egov = array(
            'invoiceTypeForEinvoice' => 3,
            'eArchivePaymentDate' => date('Y-m-d H:i:s', strtotime($payment_date . ' 12:00:00')),
            'eArchivePaymentAgent' => erp_edoc_isbasi_payment_label((string) $invoice['payment_method']),
            'website' => (string) $invoice['web_address'],
        );

        $codes = erp_edoc_settings('isbasi');
        $codes = is_array($codes['payment_type_codes'] ?? null) ? $codes['payment_type_codes'] : array();

        if (isset($codes[(string) $invoice['payment_method']])) {
            $egov['eArchivePaymentType'] = (int) $codes[(string) $invoice['payment_method']];
        }

        $payload['eGovernmentInvoice'] = $egov;
        $payload['sendingDate'] = date('Y-m-d H:i:s', strtotime($shipment_date . ' 12:00:00'));
        $payload['shipmentAgentItem'] = array(
            'name' => (string) $invoice['carrier_title'],
            'surName' => '',
            'identifier' => $carrier_vkn,
            'firmType' => (strlen($carrier_vkn) === 11) ? 0 : 1,
        );
    }

    return $payload;
}

/**
 * Asks after an invoice that was sent: its İşbaşı number, and from the
 * outgoing e-document list its GİB status, ETTN and any rejection note.
 *
 * @param array  $invoice  An erp_invoices row
 * @param string $job_id   The İşbaşı invoice id send_invoice() returned
 * @return array ['success' => bool, 'status' => 'sent'|'accepted'|'rejected'|'error',
 *                'gib_uuid' => string, 'gib_number' => string, 'message' => string, 'error' => string]
 */
function erp_edoc_isbasi_poll($invoice, $job_id)
{
    $job_id = trim((string) $job_id);
    $out = array('success' => false, 'status' => 'sent', 'gib_uuid' => '', 'gib_number' => '', 'message' => '', 'error' => '');

    if ($job_id === '') {
        $out['error'] = lang('The invoice has no İşbaşı id to ask after.');
        return $out;
    }

    // 1. The invoice itself: number and totals.
    $detail = erp_edoc_isbasi_request('GET', '/api/v1.0/invoices/' . rawurlencode($job_id), null, array('doc_type' => 'invoice', 'doc_id' => (int) $invoice['id']));

    if ((string) ($detail['login_error'] ?? '') !== '') {
        $out['error'] = $detail['login_error'];
        return $out;
    }

    if (erp_edoc_isbasi_ok($detail)) {
        $data = is_array($detail['body']['data'] ?? null) ? $detail['body']['data'] : array();
        $out['gib_number'] = erp_edoc_isbasi_pick($data, array('invoiceNumber', 'gibNumber', 'documentNumber'));
        $out['gib_uuid'] = erp_edoc_isbasi_pick($data, array('uuId', 'uuid', 'ettn', 'gibUuid'));
        $e_status = erp_edoc_isbasi_pick($data, array('eStatusDescription', 'eReplayText', 'eReplyDescription'));

        if ($e_status !== '') {
            $out['message'] = $e_status;
        }
    } elseif ((int) $detail['http_code'] === 404) {
        $out['status'] = 'error';
        $out['error'] = lang('İşbaşı no longer has an invoice with this id.');
        return $out;
    }

    // 2. The outgoing e-documents around the invoice date, matched by the
    // sales invoice id; the list is what carries the GİB status and ETTN.
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
            if ((string) ($row['salesInvoiceId'] ?? '') !== $job_id) {
                continue;
            }

            if (trim((string) ($row['uuId'] ?? '')) !== '') {
                $out['gib_uuid'] = trim((string) $row['uuId']);
            }

            $status_text = trim((string) ($row['status'] ?? ''));
            $reject = trim((string) ($row['rejectNot'] ?? ''));
            $out['message'] = trim($status_text . (($reject !== '') ? ' — ' . $reject : '') . ((trim((string) ($row['message'] ?? '')) !== '') ? ' — ' . trim((string) $row['message']) : ''));

            // The status vocabulary is İşbaşı's own text; the words for
            // "rejected" and "error" are read, everything else with an ETTN
            // counts as accepted by GİB, without one as still on its way.
            if (preg_match('/red|reject|iptal|cancel/i', $status_text . ' ' . $reject)) {
                $out['status'] = 'rejected';
            } elseif (preg_match('/hata|error|başarısız|fail/i', $status_text)) {
                $out['status'] = 'error';
            } elseif ($out['gib_uuid'] !== '') {
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
 * The PDF (by ETTN) or the UBL XML (by İşbaşı invoice id) of a sent invoice.
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
        $mime = 'application/xml';
    } else {
        if ($uuid === '') {
            return array_merge($none, array('error' => lang('The invoice has no ETTN yet; ask after its status first.')));
        }

        $path = '/api/v1.0/einvoices/DocumentDatawithuuid?uuid=' . rawurlencode($uuid) . '&fileFormat=PDF';
        $mime = 'application/pdf';
    }

    $result = erp_edoc_isbasi_request('GET', $path, null,
        array('doc_type' => 'invoice', 'doc_id' => (int) $invoice['id'], 'content_type' => 'application/json-patch+json', 'accept' => 'text/plain, application/json'));

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

    return array(
        'success' => true,
        'content' => $content,
        'filename' => ($name !== '') ? $name : ((string) $invoice['full_number'] . '.' . (($format === 'xml') ? 'xml' : 'pdf')),
        'mime' => $mime,
        'error' => '',
    );
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
