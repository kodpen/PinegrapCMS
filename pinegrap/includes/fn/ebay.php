<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * eBay: the two providers of connected accounts (production and the
 * sandbox, includes/connect/providers/ebay.php and ebay_sandbox.php), the
 * sites a marketplace account sells on, the choices its connector reads
 * from the seller's account (includes/api/outbound/connectors/ebay.php),
 * and the account deletion notices eBay sends to ebay_account_deletion.php.
 *
 * eBay withholds a production keyset until the application names an
 * address for those notices: the address answers eBay's challenge with the
 * hash of the challenge code, its verification token and its own URL, and
 * checks each notice's ECDSA signature against eBay's public key (fetched
 * with an application token and kept an hour). eBay sends a notice for
 * every member who closes an account, so only one about a buyer of this
 * shop is recorded: a privacy request of the Privacy centre naming the
 * orders, which the shop answers with the erasure tool it already has.
 *
 * The pure parts are tested in tests/ebay_test.php.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/**
 * The environments, production first: a notice's key is looked for in this
 * order.
 *
 * @return array
 */
function pg_ebay_environments()
{
    return array('production', 'sandbox');
}

/**
 * The provider of connected accounts an environment signs in through. Pure.
 *
 * @param string $environment
 * @return string
 */
function pg_ebay_provider_key($environment)
{
    return ($environment === 'sandbox') ? 'ebay_sandbox' : 'ebay';
}

/**
 * The sign-in and API hosts of an environment. Pure.
 *
 * @param string $environment
 * @return array auth, api
 */
function pg_ebay_hosts($environment)
{
    if ($environment === 'sandbox') {
        return array('auth' => 'https://auth.sandbox.ebay.com', 'api' => 'https://api.sandbox.ebay.com');
    }

    return array('auth' => 'https://auth.ebay.com', 'api' => 'https://api.ebay.com');
}

/**
 * What the seller allows: listings and their inventory, orders and their
 * shipment, and the account's business policies and locations.
 *
 * @return array
 */
function pg_ebay_scopes()
{
    return array(
        'https://api.ebay.com/oauth/api_scope/sell.inventory',
        'https://api.ebay.com/oauth/api_scope/sell.fulfillment',
        'https://api.ebay.com/oauth/api_scope/sell.account',
    );
}

/**
 * The eBay sites the Sell APIs serve: marketplace id => domain, currency
 * (ISO 4217) and the language of its listings. One marketplace account
 * sells on one site.
 *
 * @return array
 */
function pg_ebay_sites()
{
    return array(
        'EBAY_US' => array('domain' => 'ebay.com', 'currency' => 'USD', 'locale' => 'en-US'),
        'EBAY_GB' => array('domain' => 'ebay.co.uk', 'currency' => 'GBP', 'locale' => 'en-GB'),
        'EBAY_DE' => array('domain' => 'ebay.de', 'currency' => 'EUR', 'locale' => 'de-DE'),
        'EBAY_AU' => array('domain' => 'ebay.com.au', 'currency' => 'AUD', 'locale' => 'en-AU'),
        'EBAY_CA' => array('domain' => 'ebay.ca', 'currency' => 'CAD', 'locale' => 'en-CA'),
        'EBAY_FR' => array('domain' => 'ebay.fr', 'currency' => 'EUR', 'locale' => 'fr-FR'),
        'EBAY_IT' => array('domain' => 'ebay.it', 'currency' => 'EUR', 'locale' => 'it-IT'),
        'EBAY_ES' => array('domain' => 'ebay.es', 'currency' => 'EUR', 'locale' => 'es-ES'),
        'EBAY_NL' => array('domain' => 'ebay.nl', 'currency' => 'EUR', 'locale' => 'nl-NL'),
        'EBAY_BE' => array('domain' => 'ebay.be', 'currency' => 'EUR', 'locale' => 'nl-BE'),
        'EBAY_AT' => array('domain' => 'ebay.at', 'currency' => 'EUR', 'locale' => 'de-AT'),
        'EBAY_CH' => array('domain' => 'ebay.ch', 'currency' => 'CHF', 'locale' => 'de-CH'),
        'EBAY_IE' => array('domain' => 'ebay.ie', 'currency' => 'EUR', 'locale' => 'en-IE'),
        'EBAY_PL' => array('domain' => 'ebay.pl', 'currency' => 'PLN', 'locale' => 'pl-PL'),
        'EBAY_HK' => array('domain' => 'ebay.com.hk', 'currency' => 'HKD', 'locale' => 'zh-HK'),
        'EBAY_SG' => array('domain' => 'ebay.com.sg', 'currency' => 'SGD', 'locale' => 'en-US'),
        'EBAY_MY' => array('domain' => 'ebay.com.my', 'currency' => 'MYR', 'locale' => 'en-US'),
        'EBAY_PH' => array('domain' => 'ebay.ph', 'currency' => 'PHP', 'locale' => 'en-PH'),
        'EBAY_TW' => array('domain' => 'tw.ebay.com', 'currency' => 'TWD', 'locale' => 'zh-TW'),
    );
}

/**
 * The sites as a select's options, named by their domain and currency,
 * which read the same in every language. Pure.
 *
 * @return array marketplace id => label
 */
function pg_ebay_site_options()
{
    $options = array();

    foreach (pg_ebay_sites() as $id => $site) {
        $options[$id] = $site['domain'] . ' · ' . $site['currency'];
    }

    return $options;
}

/**
 * A provider definition of connected accounts for one environment. The
 * application is a keyset of eBay's developer program: its App ID is the
 * client ID, its Cert ID the secret, and its RuName names the redirect
 * address eBay keeps for it (pg_connect_provider_redirect()). The seller
 * signs in once; one connection serves every site's account.
 *
 * @param string $environment 'production' or 'sandbox'
 * @return array
 */
function pg_ebay_provider($environment)
{
    $hosts = pg_ebay_hosts($environment);
    $sandbox = ($environment === 'sandbox');

    return array(
        'label'            => $sandbox ? lang('eBay (sandbox)') : 'eBay',
        'icon'             => 'bi-bag',
        'authorize_url'    => $hosts['auth'] . '/oauth2/authorize',
        'token_url'        => $hosts['api'] . '/identity/v1/oauth2/token',
        'check_url'        => $hosts['api'] . '/sell/account/v1/privilege',
        'issuers'          => array(),
        'identity_scopes'  => array(),
        'identity'         => false,
        'account_label'    => $sandbox ? lang('eBay (sandbox)') : 'eBay',
        'nonce'            => false,
        'authorize_params' => array(),
        'client_auth'      => 'basic',
        'token_per_scope'  => false,
        'default_ttl'      => 7200,
        'connect_purpose'  => 'ebay',
        'redirect_option'  => 'runame',
        'console_url'      => 'https://developer.ebay.com/my/keys',
        'options'          => array(
            'runame' => array(
                'label'   => lang('RuName'),
                'help'    => lang('The name eBay gives the redirect address under User Tokens › Get a Token from eBay via Your Application, for example Your_Name-YourApp-PRD-abc123.'),
                'default' => '',
                'pattern' => '/^[A-Za-z0-9_.-]{1,100}$/',
                'invalid' => lang('A RuName holds letters, digits, dots, dashes and underscores.'),
            ),
        ),
        'redirect_help'    => lang('Add a redirect address under User Tokens in eBay\'s developer program and enter this address as its auth accepted URL; eBay names it with a RuName.'),
        'copy_values'      => $sandbox ? array() : array(
            'deletion' => array(
                'label' => lang('Account deletion notification endpoint'),
                'value' => pg_ebay_deletion_address(),
                'help'  => lang('eBay sends a production keyset only once this address is entered under Alerts & Notifications › Marketplace account deletion. It must be an https address.'),
            ),
            'token'    => array(
                'label' => lang('Verification token'),
                'value' => pg_ebay_deletion_token(),
                'help'  => lang('Enter it beside the address: eBay proves the address with it.'),
            ),
        ),
        'setup'            => array(
            lang('Make a keyset for your own application in eBay\'s developer program.'),
            lang('Add a redirect address under User Tokens and enter the redirect address below as its auth accepted URL.'),
            lang('Copy the App ID as the Client ID, the Cert ID as the client secret and the RuName here, save, then press Connect.'),
        ),
    );
}


/* ---------------------------------------------------------------------------
   Account deletion notices
   --------------------------------------------------------------------------- */

/**
 * The verification token entered beside the deletion address: derived from
 * the site's encryption key, so it is stored nowhere and stays the same;
 * '' on a site with no key.
 *
 * @return string 64 hexadecimal characters
 */
function pg_ebay_deletion_token()
{
    if (!defined('ENCRYPTION_KEY') || ((string) ENCRYPTION_KEY === '')) {
        return '';
    }

    return hash_hmac('sha256', 'ebay-account-deletion', (string) ENCRYPTION_KEY);
}

/**
 * The address eBay sends account deletion notices to.
 *
 * @return string
 */
function pg_ebay_deletion_address()
{
    if (!defined('URL_SCHEME') || !defined('HOSTNAME_SETTING') || !defined('PATH') || !defined('SOFTWARE_DIRECTORY')) {
        return '';
    }

    return URL_SCHEME . HOSTNAME_SETTING . PATH . SOFTWARE_DIRECTORY . '/ebay_account_deletion.php';
}

/**
 * The answer to eBay's challenge: the hexadecimal SHA-256 of the challenge
 * code, the verification token and the endpoint, in that order. Pure.
 *
 * @param string $code
 * @param string $token
 * @param string $endpoint
 * @return string
 */
function pg_ebay_challenge_response($code, $token, $endpoint)
{
    return hash('sha256', (string) $code . (string) $token . (string) $endpoint);
}

/**
 * The X-EBAY-SIGNATURE header read: base64 of a JSON object naming the
 * algorithm, the public key's id, the signature and the digest. Pure.
 *
 * @param string $header
 * @return array|null alg, kid, signature (binary), digest
 */
function pg_ebay_signature_header($header)
{
    $json = base64_decode(trim((string) $header), true);
    $data = is_string($json) ? json_decode($json, true) : null;

    if (!is_array($data)) {
        return null;
    }

    foreach (array('kid', 'signature') as $name) {
        if (!isset($data[$name]) || !is_string($data[$name]) || ($data[$name] === '')) {
            return null;
        }
    }

    $signature = base64_decode($data['signature'], true);

    if (!is_string($signature) || ($signature === '') || !preg_match('/^[A-Za-z0-9_.:-]{1,100}$/', $data['kid'])) {
        return null;
    }

    return array(
        'alg'       => isset($data['alg']) ? strtoupper((string) $data['alg']) : 'ECDSA',
        'kid'       => $data['kid'],
        'signature' => $signature,
        'digest'    => isset($data['digest']) ? strtoupper((string) $data['digest']) : 'SHA1',
    );
}

/**
 * A public key as eBay answers it (the PEM markers and the key on one line)
 * re-wrapped the way OpenSSL reads it; '' for anything else. Pure.
 *
 * @param string $key
 * @return string
 */
function pg_ebay_pem($key)
{
    $body = str_replace(array('-----BEGIN PUBLIC KEY-----', '-----END PUBLIC KEY-----'), '', (string) $key);
    $body = preg_replace('/\s+/', '', $body);

    if (($body === '') || !preg_match('~^[A-Za-z0-9+/]+={0,2}$~', $body) || (base64_decode($body, true) === false)) {
        return '';
    }

    return "-----BEGIN PUBLIC KEY-----\n" . chunk_split($body, 64, "\n") . "-----END PUBLIC KEY-----\n";
}

/**
 * Whether a notice's signature is the key's over its body. eBay's own
 * libraries verify either the body as sent or its JSON written again
 * without spaces, so both are tried. Pure.
 *
 * @param string $body      the request body as received
 * @param string $signature binary DER signature
 * @param string $pem       pg_ebay_pem()
 * @param string $digest    'SHA1' or 'SHA256'
 * @return bool
 */
function pg_ebay_signature_valid($body, $signature, $pem, $digest)
{
    $algorithms = array('SHA1' => OPENSSL_ALGO_SHA1, 'SHA256' => OPENSSL_ALGO_SHA256);
    $digest = strtoupper((string) $digest);

    if (!function_exists('openssl_verify') || !isset($algorithms[$digest]) || ($pem === '') || ($signature === '')) {
        return false;
    }

    $key = openssl_pkey_get_public($pem);

    if ($key === false) {
        return false;
    }

    $texts = array((string) $body);
    $decoded = json_decode((string) $body, true);

    if (is_array($decoded)) {
        $texts[] = (string) json_encode($decoded, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    foreach (array_unique($texts) as $text) {
        if (openssl_verify($text, (string) $signature, $key, $algorithms[$digest]) === 1) {
            return true;
        }
    }

    return false;
}

/**
 * A notice read: its id and the member it is about; null for anything but
 * a MARKETPLACE_ACCOUNT_DELETION notice naming a member. Pure.
 *
 * @param string $body
 * @return array|null notification_id, username, user_id
 */
function pg_ebay_deletion_payload($body)
{
    $data = json_decode((string) $body, true);

    if (!is_array($data) || !isset($data['metadata']['topic'], $data['notification']['data']) || ($data['metadata']['topic'] !== 'MARKETPLACE_ACCOUNT_DELETION')) {
        return null;
    }

    $member = $data['notification']['data'];
    $text = function ($value) {
        return is_scalar($value) ? trim((string) $value) : '';
    };

    $username = $text(isset($member['username']) ? $member['username'] : '');

    if (($username === '') || (mb_strlen($username) > 64)) {
        return null;
    }

    return array(
        'notification_id' => mb_substr($text(isset($data['notification']['notificationId']) ? $data['notification']['notificationId'] : ''), 0, 128),
        'username'        => $username,
        'user_id'         => mb_substr($text(isset($member['userId']) ? $member['userId'] : ''), 0, 64),
    );
}

/**
 * Whether the deletion address answers: an eBay application is saved for
 * either environment and the site can make the verification token.
 *
 * @return bool
 */
function pg_ebay_deletion_open()
{
    if (pg_ebay_deletion_token() === '') {
        return false;
    }

    foreach (pg_ebay_environments() as $environment) {
        $app = pg_connect_app(pg_ebay_provider_key($environment));

        if (is_array($app) && ($app['client_id'] !== '')) {
            return true;
        }
    }

    return false;
}

/**
 * An application token of an environment (the client credentials grant),
 * for the notification key; '' when its application is not ready or eBay
 * refused.
 *
 * @param string $environment
 * @return string
 */
function pg_ebay_app_token($environment)
{
    $key = pg_ebay_provider_key($environment);
    $provider = pg_connect_provider($key);
    $app = pg_connect_app($key);

    if (!is_array($provider) || !pg_connect_app_ready($app)) {
        return '';
    }

    $answer = pg_connect_token_request($provider, $app, array(
        'grant_type' => 'client_credentials',
        'scope'      => 'https://api.ebay.com/oauth/api_scope',
    ));

    return $answer['ok'] ? (string) $answer['data']['access_token'] : '';
}

/**
 * The public key a notice names, from the hour-long cache or from eBay's
 * Notification API: production first, then the sandbox.
 *
 * @param string $kid
 * @return array ok, pem, digest, unknown (eBay said no such key)
 */
function pg_ebay_public_key($kid)
{
    require_once(PG_FUNCTIONS_DIR . '/includes/api/outbound/http.php');

    $now = time();
    $cache = pg_connect_service_get('ebay_notification_keys');
    $answer = array('ok' => false, 'pem' => '', 'digest' => '', 'unknown' => false);

    if (isset($cache[$kid]['pem'], $cache[$kid]['at']) && (($now - (int) $cache[$kid]['at']) < 3600)) {
        return array('ok' => true, 'pem' => (string) $cache[$kid]['pem'], 'digest' => (string) $cache[$kid]['digest'], 'unknown' => false);
    }

    foreach (pg_ebay_environments() as $environment) {
        $token = pg_ebay_app_token($environment);

        if ($token === '') {
            continue;
        }

        $hosts = pg_ebay_hosts($environment);
        $result = api_http_request('GET', $hosts['api'] . '/commerce/notification/v1/public_key/' . rawurlencode($kid), array(
            'headers'  => array('Authorization: Bearer ' . $token, 'Accept: application/json'),
            'timeout'  => 10,
            'max_body' => 16384,
            'agent'    => 'connect',
        ));
        $data = json_decode((string) $result['body'], true);
        $pem = (is_array($data) && isset($data['key'])) ? pg_ebay_pem($data['key']) : '';

        if ($result['ok'] && ($pem !== '')) {
            foreach ($cache as $cached_kid => $entry) {
                if (!is_array($entry) || !isset($entry['at']) || (($now - (int) $entry['at']) > 86400)) {
                    unset($cache[$cached_kid]);
                }
            }

            $digest = isset($data['digest']) ? strtoupper((string) $data['digest']) : 'SHA1';
            $cache[$kid] = array('pem' => $pem, 'digest' => $digest, 'at' => $now);
            pg_connect_service_put('ebay_notification_keys', 'eBay notification keys', array_slice($cache, -10, 10, true));

            return array('ok' => true, 'pem' => $pem, 'digest' => $digest, 'unknown' => false);
        }

        $answer['unknown'] = $answer['unknown'] || ((int) $result['status'] === 404);
    }

    return $answer;
}

/**
 * The orders a member bought here through eBay, newest first: an eBay
 * order's contact carries the buyer's eBay username as its nickname.
 *
 * @param string $username
 * @return array id, order_number, email
 */
function pg_ebay_buyer_orders($username)
{
    $rows = db_items(
        "SELECT orders.id, orders.order_number, orders.billing_email_address, contacts.email_address
        FROM orders
        INNER JOIN marketplace_order_map ON (marketplace_order_map.order_id = orders.id) AND (marketplace_order_map.status = 'imported')
        INNER JOIN marketplace_accounts ON (marketplace_accounts.id = marketplace_order_map.account_id) AND (marketplace_accounts.provider = 'ebay')
        INNER JOIN contacts ON contacts.id = orders.contact_id
        WHERE contacts.nickname = '" . e(mb_substr((string) $username, 0, 50)) . "'
        ORDER BY orders.id DESC
        LIMIT 50");

    $orders = array();

    foreach ((array) $rows as $row) {
        $email = trim((string) $row['billing_email_address']);

        $orders[] = array(
            'id'           => (int) $row['id'],
            'order_number' => (string) $row['order_number'],
            'email'        => ($email !== '') ? $email : trim((string) $row['email_address']),
        );
    }

    return $orders;
}

/**
 * The privacy request a notice about one of the shop's buyers becomes: an
 * erasure request whose address is the one on the buyer's latest order and
 * whose message names the orders. Pure.
 *
 * @param array $payload pg_ebay_deletion_payload()
 * @param array $orders  pg_ebay_buyer_orders()
 * @return array|null kind, email, name, message; null with no order or no address
 */
function pg_ebay_deletion_request($payload, $orders)
{
    $email = '';
    $numbers = array();

    foreach ($orders as $order) {
        if (($email === '') && ($order['email'] !== '')) {
            $email = $order['email'];
        }

        $numbers[] = ($order['order_number'] !== '') ? $order['order_number'] : '#' . $order['id'];
    }

    if (!$numbers || ($email === '')) {
        return null;
    }

    return array(
        'kind'    => 'erasure',
        'email'   => $email,
        'name'    => mb_substr($payload['username'], 0, 100),
        'message' => lang(array(
            'string' => 'eBay reports that its member {var:1} closed their eBay account and asks for their data to be erased. Their orders here: {var:2}.',
            'vars'   => array($payload['username'], implode(', ', $numbers)),
        )),
    );
}

/**
 * Records a notice: a privacy request and a line in the bell when the
 * member bought here and no request for them is on file yet.
 *
 * @param array $payload pg_ebay_deletion_payload()
 * @return int the new request's id, or 0
 */
function pg_ebay_record_deletion($payload)
{
    require_once(PG_FUNCTIONS_DIR . '/includes/fn/privacy_requests.php');

    if (!pg_privacy_requests_ready()) {
        return 0;
    }

    $input = pg_ebay_deletion_request($payload, pg_ebay_buyer_orders($payload['username']));

    if ($input === null) {
        return 0;
    }

    $known = db_value("SELECT id FROM privacy_requests WHERE source = 'marketplace' AND name = '" . e($input['name']) . "' LIMIT 1");

    if ($known !== null) {
        return 0;
    }

    $added = pg_privacy_request_add($input, array('id' => 0), 'marketplace');

    if ($added['id'] > 0) {
        create_notification(array('action' => 'privacy_request', 'type' => 'warning', 'title' => (string) $added['id'], 'order_total' => 'marketplace', 'user' => 'system'));
    }

    return (int) $added['id'];
}

/**
 * Answers a request to ebay_account_deletion.php and ends it: eBay's
 * challenge (GET with challenge_code) with the hash it expects, a notice
 * (POST) with 204 once its signature is eBay's. A signature that is not
 * eBay's is answered 412, as eBay's guide asks, and counted as an offence
 * by the WAF; a key eBay could not be asked for is 500, so eBay sends the
 * notice again.
 */
function pg_ebay_deletion_receive()
{
    $method = isset($_SERVER['REQUEST_METHOD']) ? strtoupper((string) $_SERVER['REQUEST_METHOD']) : '';

    if (!pg_ebay_deletion_open()) {
        pg_ebay_deletion_answer(404);
    }

    if ($method === 'GET') {
        $code = (isset($_GET['challenge_code']) && is_string($_GET['challenge_code'])) ? $_GET['challenge_code'] : '';

        if (($code === '') || (strlen($code) > 512)) {
            pg_ebay_deletion_answer(400);
        }

        pg_ebay_deletion_answer(200, array(
            'challengeResponse' => pg_ebay_challenge_response($code, pg_ebay_deletion_token(), pg_ebay_deletion_address()),
        ));
    }

    if ($method !== 'POST') {
        pg_ebay_deletion_answer(405);
    }

    $limit = 16384;

    if (isset($_SERVER['CONTENT_LENGTH']) && ((int) $_SERVER['CONTENT_LENGTH'] > $limit)) {
        pg_ebay_deletion_answer(413);
    }

    // One byte more than the limit is read, so a body sent without a length
    // is still caught at the limit rather than cut there.
    $body = (string) @file_get_contents('php://input', false, null, 0, $limit + 1);

    if (strlen($body) > $limit) {
        pg_ebay_deletion_answer(413);
    }

    $signature = pg_ebay_signature_header(isset($_SERVER['HTTP_X_EBAY_SIGNATURE']) ? $_SERVER['HTTP_X_EBAY_SIGNATURE'] : '');

    if ($signature === null) {
        pg_ebay_deletion_offence();
        pg_ebay_deletion_answer(412);
    }

    $key = pg_ebay_public_key($signature['kid']);

    if (!$key['ok']) {
        if ($key['unknown']) {
            pg_ebay_deletion_offence();
            pg_ebay_deletion_answer(412);
        }

        pg_ebay_deletion_answer(500);
    }

    if (!pg_ebay_signature_valid($body, $signature['signature'], $key['pem'], $key['digest'])) {
        pg_ebay_deletion_offence();
        pg_ebay_deletion_answer(412);
    }

    $payload = pg_ebay_deletion_payload($body);

    if ($payload !== null) {
        pg_ebay_record_deletion($payload);
    }

    pg_ebay_deletion_answer(204);
}

/**
 * A refused notice counted by the WAF, which blocks an address that keeps
 * sending them when it is in block mode.
 */
function pg_ebay_deletion_offence()
{
    if (function_exists('waf_register_offence') && function_exists('waf_client_ip') && function_exists('waf_mode')) {
        waf_register_offence(waf_client_ip(), 5, (waf_mode() === 'block'));
    }
}

/**
 * Sends the answer and ends the request; what the shared code printed on
 * the way is dropped.
 *
 * @param int        $status
 * @param array|null $body JSON, or none
 */
function pg_ebay_deletion_answer($status, $body = null)
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    http_response_code((int) $status);
    header('Cache-Control: no-store');
    header('X-Robots-Tag: noindex, nofollow');

    if ((int) $status === 405) {
        header('Allow: GET, POST');
    }

    if ($body !== null) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($body);
    }

    exit;
}


/* ---------------------------------------------------------------------------
   The seller's choices (connectors/ebay.php)
   --------------------------------------------------------------------------- */

/**
 * The inventory locations of a getInventoryLocations answer as a select's
 * choices: key => name (the key when it has none). Pure.
 *
 * @param mixed $data
 * @return array
 */
function pg_ebay_location_choices($data)
{
    $choices = array();
    $list = (is_array($data) && isset($data['locations']) && is_array($data['locations'])) ? $data['locations'] : array();

    foreach ($list as $location) {
        $key = isset($location['merchantLocationKey']) && is_scalar($location['merchantLocationKey']) ? trim((string) $location['merchantLocationKey']) : '';

        if ($key === '') {
            continue;
        }

        if (isset($location['merchantLocationStatus']) && ($location['merchantLocationStatus'] === 'DISABLED')) {
            continue;
        }

        $name = isset($location['name']) && is_scalar($location['name']) ? trim((string) $location['name']) : '';
        $choices[$key] = ($name !== '') ? $name : $key;
    }

    return $choices;
}

/**
 * The business policies of one kind in a getFulfillmentPolicies,
 * getPaymentPolicies or getReturnPolicies answer as a select's choices:
 * id => name. Pure.
 *
 * @param mixed  $data
 * @param string $kind 'fulfillment', 'payment' or 'return'
 * @return array
 */
function pg_ebay_policy_choices($data, $kind)
{
    $choices = array();
    $list_key = $kind . 'Policies';
    $id_key = $kind . 'PolicyId';
    $list = (is_array($data) && isset($data[$list_key]) && is_array($data[$list_key])) ? $data[$list_key] : array();

    foreach ($list as $policy) {
        $id = isset($policy[$id_key]) && is_scalar($policy[$id_key]) ? trim((string) $policy[$id_key]) : '';

        if ($id === '') {
            continue;
        }

        $name = isset($policy['name']) && is_scalar($policy['name']) ? trim((string) $policy['name']) : '';
        $choices[$id] = ($name !== '') ? $name : $id;
    }

    return $choices;
}

/**
 * The key a site's categories and item specifics are cached under
 * (marketplace_categories.provider): eBay keeps a category tree a site, and
 * the sandbox's trees are its own. At most 27 characters, inside the
 * column's 30. Pure.
 *
 * @param string $environment 'production' or 'sandbox'
 * @param string $site        marketplace id, pg_ebay_sites()
 * @return string
 */
function pg_ebay_category_key($environment, $site)
{
    return pg_ebay_provider_key($environment) . ':' . $site;
}

/**
 * A getCategoryTree answer flattened into the rows of the category cache:
 * remote_id, parent_id ('' at the top), name, path ("A > B > C") and
 * is_leaf, parents before their children. eBay's root node ("Root", id 0)
 * is not a category and is left out, as is a node without an id. Pure.
 *
 * @param mixed $data
 * @return array
 */
function pg_ebay_category_rows($data)
{
    $rows = array();

    if (!is_array($data) || !isset($data['rootCategoryNode']) || !is_array($data['rootCategoryNode'])) {
        return $rows;
    }

    $root = $data['rootCategoryNode'];
    $stack = array();

    // A stack rather than recursion, the top level pushed in reverse so the
    // rows come out in eBay's order.
    $children = (isset($root['childCategoryTreeNodes']) && is_array($root['childCategoryTreeNodes'])) ? $root['childCategoryTreeNodes'] : array();

    foreach (array_reverse($children) as $child) {
        $stack[] = array($child, '', '');
    }

    while ($stack) {
        list($node, $parent_id, $parent_path) = array_pop($stack);

        $id = (is_array($node) && isset($node['category']['categoryId']) && is_scalar($node['category']['categoryId'])) ? trim((string) $node['category']['categoryId']) : '';

        if (($id === '') || (preg_match('/^[0-9]{1,20}$/', $id) !== 1)) {
            continue;
        }

        $name = (isset($node['category']['categoryName']) && is_scalar($node['category']['categoryName'])) ? trim((string) $node['category']['categoryName']) : '';
        $path = ($parent_path === '') ? $name : ($parent_path . ' > ' . $name);
        $children = (isset($node['childCategoryTreeNodes']) && is_array($node['childCategoryTreeNodes'])) ? $node['childCategoryTreeNodes'] : array();

        $rows[] = array(
            'remote_id' => $id,
            'parent_id' => $parent_id,
            'name'      => $name,
            'path'      => $path,
            'is_leaf'   => (!empty($node['leafCategoryTreeNode']) || !$children) ? 1 : 0,
        );

        foreach (array_reverse($children) as $child) {
            $stack[] = array($child, $id, $path);
        }
    }

    return $rows;
}

/**
 * A getItemAspectsForCategory answer as the attribute rows of the category
 * cache. eBay's item specifics have no ids: the aspect's name is its id and
 * each value its own id, and a listing sends them by name. Required is
 * aspectRequired; free text (aspectMode FREE_TEXT) allows a value of the
 * shop's own; an aspect enabled for variations is answered by each product's
 * own option, the article's answer being its fallback. A name longer than
 * the cache's 64 characters is left out rather than cut, since a cut name
 * is another aspect. Pure.
 *
 * @param mixed $data
 * @return array attribute_id, name, mandatory, is_variant, custom_allowed, sort_order, values
 */
function pg_ebay_aspect_rows($data)
{
    $rows = array();
    $list = (is_array($data) && isset($data['aspects']) && is_array($data['aspects'])) ? $data['aspects'] : array();
    $seen = array();

    foreach ($list as $aspect) {
        $name = (is_array($aspect) && isset($aspect['localizedAspectName']) && is_scalar($aspect['localizedAspectName'])) ? trim((string) $aspect['localizedAspectName']) : '';

        if (($name === '') || (mb_strlen($name) > 64) || isset($seen[$name])) {
            continue;
        }

        $seen[$name] = true;
        $constraint = (isset($aspect['aspectConstraint']) && is_array($aspect['aspectConstraint'])) ? $aspect['aspectConstraint'] : array();
        $values = array();
        $known = array();

        foreach ((isset($aspect['aspectValues']) && is_array($aspect['aspectValues'])) ? $aspect['aspectValues'] : array() as $value) {
            $text = (is_array($value) && isset($value['localizedValue']) && is_scalar($value['localizedValue'])) ? trim((string) $value['localizedValue']) : '';

            if (($text === '') || isset($known[$text])) {
                continue;
            }

            $known[$text] = true;
            $values[] = array('id' => $text, 'value' => $text);
        }

        $rows[] = array(
            'attribute_id'   => $name,
            'name'           => $name,
            'mandatory'      => !empty($constraint['aspectRequired']) ? 1 : 0,
            'is_variant'     => !empty($constraint['aspectEnabledForVariations']) ? 1 : 0,
            'custom_allowed' => (isset($constraint['aspectMode']) && ($constraint['aspectMode'] === 'SELECTION_ONLY')) ? 0 : 1,
            'sort_order'     => count($rows),
            'values'         => $values,
        );
    }

    return $rows;
}


/* ---------------------------------------------------------------------------
   Listings (connectors/ebay.php): inventory item, group, offer and publish
   --------------------------------------------------------------------------- */

/**
 * The longest SKU and inventory item group key eBay takes, the longest
 * title, the most SKUs one bulkUpdatePriceQuantity call takes and the most
 * orders one getOrders page answers. Pure.
 *
 * @return array sku, group_key, title, pictures, product_description, bulk, orders
 */
function pg_ebay_listing_limits()
{
    return array('sku' => 50, 'group_key' => 50, 'title' => 80, 'pictures' => 24, 'product_description' => 4000, 'bulk' => 25, 'orders' => 200);
}

/**
 * The words a site takes for a product identifier the product does not have
 * ("Does not apply" on the English sites), from eBay's product identifier
 * text page. Pure.
 *
 * @param string $site marketplace id
 * @return string
 */
function pg_ebay_does_not_apply($site)
{
    $texts = array(
        'EBAY_DE' => 'Nicht zutreffend',
        'EBAY_AT' => 'Nicht zutreffend',
        'EBAY_CH' => 'Nicht zutreffend',
        'EBAY_FR' => 'Non applicable',
        'EBAY_IT' => 'Non applicabile',
        'EBAY_ES' => 'No aplicable',
        'EBAY_NL' => 'Niet van toepassing',
        'EBAY_BE' => 'Niet van toepassing',
        'EBAY_PL' => 'Nie dotyczy',
    );

    return isset($texts[$site]) ? $texts[$site] : 'Does not apply';
}

/**
 * The product identifier of an inventory item from the product's barcode:
 * twelve digits are a UPC, eight, thirteen or fourteen an EAN (GTIN-14 is
 * sent as one); anything else, or none, is the site's "does not apply" text,
 * under UPC on the American sites and EAN elsewhere. Pure.
 *
 * @param string $barcode
 * @param string $site
 * @return array 'upc' or 'ean' => array(value)
 */
function pg_ebay_identifier($barcode, $site)
{
    $barcode = trim((string) $barcode);

    if (preg_match('/^[0-9]{12}$/', $barcode) === 1) {
        return array('upc' => array($barcode));
    }

    if (preg_match('/^([0-9]{8}|[0-9]{13}|[0-9]{14})$/', $barcode) === 1) {
        return array('ean' => array($barcode));
    }

    $field = in_array($site, array('EBAY_US', 'EBAY_CA'), true) ? 'upc' : 'ean';

    return array($field => array(pg_ebay_does_not_apply($site)));
}

/**
 * A listing item's attributes (mp_listing_attributes()) as eBay's aspects:
 * name => array(value). eBay's item specifics have no ids, so the value id
 * is the value's own text; a free answer is its custom value. Pure.
 *
 * @param array $attributes id, value_id, custom_value
 * @return array
 */
function pg_ebay_aspects($attributes)
{
    $aspects = array();

    foreach ((array) $attributes as $attribute) {
        $name = isset($attribute['id']) ? trim((string) $attribute['id']) : '';
        $value = (isset($attribute['value_id']) && ((string) $attribute['value_id'] !== ''))
            ? trim((string) $attribute['value_id'])
            : (isset($attribute['custom_value']) ? trim((string) $attribute['custom_value']) : '');

        if (($name === '') || ($value === '')) {
            continue;
        }

        $aspects[$name] = array($value);
    }

    return $aspects;
}

/**
 * The price an offer is sent at, in the site's currency: the shop's price
 * (price_major) times the rate from the shop's currency to the site's,
 * rounded half away from zero to the cent. Every eBay site's currency has
 * two decimals. '' when there is no price or no rate. Pure.
 *
 * @param float      $price_major
 * @param float|null $rate
 * @return string "12.34"
 */
function pg_ebay_offer_price($price_major, $rate)
{
    if (!is_numeric($price_major) || !is_numeric($rate) || ((float) $rate <= 0)) {
        return '';
    }

    // The shop's price in hundredths first, so a float like 19.989999 is
    // the 1999 it stands for before the rate is applied.
    $cents = (int) round(round((float) $price_major * 100, 6) * (float) $rate);

    if ($cents <= 0) {
        return '';
    }

    return number_format($cents / 100, 2, '.', '');
}

/**
 * The key an article's variants are grouped under on eBay: the product
 * group's name when it is a key eBay takes, else a stable one made from it.
 * Pure.
 *
 * @param string $group_code
 * @return string
 */
function pg_ebay_group_key($group_code)
{
    $group_code = trim((string) $group_code);
    $limits = pg_ebay_listing_limits();

    if (preg_match('/^[A-Za-z0-9._-]+$/', $group_code) === 1 && (strlen($group_code) <= $limits['group_key'])) {
        return $group_code;
    }

    return 'pg-' . substr(md5($group_code), 0, 24);
}

/**
 * The plain text of a description, for the inventory item's product
 * description, which takes 4,000 characters: tags left out and the text cut
 * at a word, which cannot break any markup. The offer carries the full HTML.
 * Pure.
 *
 * @param string $html
 * @return string
 */
function pg_ebay_plain_description($html)
{
    $text = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags(str_replace(array('<br', '</p>', '</li>'), array(' <br', ' </p>', ' </li>'), (string) $html)), ENT_QUOTES, 'UTF-8')));
    $limits = pg_ebay_listing_limits();

    if (mb_strlen($text) <= $limits['product_description']) {
        return $text;
    }

    $cut = mb_substr($text, 0, $limits['product_description']);
    $space = mb_strrpos($cut, ' ');

    return ($space !== false && $space > 0) ? mb_substr($cut, 0, $space) : $cut;
}

/**
 * Whether one listing item can go to eBay as it is: '' or the reason it
 * cannot. Never cut: a cut title or SKU is another listing. Pure.
 *
 * @param array $item mp_listing_items()
 * @return string
 */
function pg_ebay_item_problem($item)
{
    $limits = pg_ebay_listing_limits();
    $sku = isset($item['code']) ? (string) $item['code'] : '';
    $title = isset($item['title']) ? trim((string) $item['title']) : '';

    if (($sku === '') || (mb_strlen($sku) > $limits['sku'])) {
        return lang(array('string' => 'eBay takes a product code of 1 to {var:1} characters; {var:2} is not one.', 'vars' => array($limits['sku'], $sku)));
    }

    if ($title === '') {
        return lang(array('string' => '{var:1} has no title.', 'vars' => array($sku)));
    }

    if (mb_strlen($title) > $limits['title']) {
        return lang(array('string' => 'The title of {var:1} is longer than the {var:2} characters eBay takes.', 'vars' => array($sku, $limits['title'])));
    }

    foreach ((array) (isset($item['images']) ? $item['images'] : array()) as $url) {
        if (stripos((string) $url, 'https://') !== 0) {
            return lang(array('string' => 'eBay only takes pictures on https addresses; {var:1} has one that is not.', 'vars' => array($sku)));
        }
    }

    return '';
}

/**
 * The createOrReplaceInventoryItem body of one listing item: a new product,
 * its quantity, title, plain description, aspects, up to 24 pictures and its
 * identifier (pg_ebay_identifier()). Pure.
 *
 * @param array  $item mp_listing_items()
 * @param string $site
 * @return array
 */
function pg_ebay_inventory_item($item, $site)
{
    $limits = pg_ebay_listing_limits();
    $product = array(
        'title'       => trim((string) $item['title']),
        'description' => pg_ebay_plain_description(isset($item['description']) ? $item['description'] : ''),
        'aspects'     => (object) pg_ebay_aspects(isset($item['attributes']) ? $item['attributes'] : array()),
        'imageUrls'   => array_slice(array_values((array) $item['images']), 0, $limits['pictures']),
    );

    $product += pg_ebay_identifier(isset($item['barcode']) ? $item['barcode'] : '', $site);

    return array(
        'availability' => array('shipToLocationAvailability' => array('quantity' => max(0, (int) $item['quantity']))),
        'condition'    => 'NEW',
        'product'      => $product,
    );
}

/**
 * The createOrReplaceInventoryItemGroup body of an article's variants: the
 * aspects all of them share, the ones they differ by with every value
 * (variesBy), the article's title and pictures, and their SKUs. Answers
 * array(ok, body, error): variants that differ by nothing cannot be one
 * listing. Pure.
 *
 * @param array $items mp_listing_items() of one article, two or more
 * @return array
 */
function pg_ebay_inventory_group($items)
{
    $items = array_values((array) $items);
    $limits = pg_ebay_listing_limits();
    $aspects = array();
    $names = array();

    foreach ($items as $index => $item) {
        $aspects[$index] = pg_ebay_aspects(isset($item['attributes']) ? $item['attributes'] : array());
        $names += array_fill_keys(array_keys($aspects[$index]), true);
    }

    $common = array();
    $varies = array();

    foreach (array_keys($names) as $name) {
        $values = array();

        foreach ($aspects as $own) {
            $values[] = isset($own[$name]) ? $own[$name][0] : '';
        }

        if (count(array_unique($values)) === 1) {
            $common[$name] = array($values[0]);
        } elseif (!in_array('', $values, true)) {
            $varies[] = array('name' => (string) $name, 'values' => array_values(array_unique($values)));
        }
    }

    if (!$varies) {
        return array('ok' => false, 'body' => array(), 'error' => lang('The variants of this article differ by no item specific, so eBay cannot list them as one listing. Give each product its own option.'));
    }

    $title = isset($items[0]['group_title']) && (trim((string) $items[0]['group_title']) !== '') ? trim((string) $items[0]['group_title']) : trim((string) $items[0]['title']);

    if (mb_strlen($title) > $limits['title']) {
        return array('ok' => false, 'body' => array(), 'error' => lang(array('string' => 'The title of this article is longer than the {var:1} characters eBay takes.', 'vars' => array($limits['title']))));
    }

    $pictures = array();

    foreach ($items as $item) {
        foreach ((array) $item['images'] as $url) {
            $pictures[(string) $url] = true;
        }
    }

    return array('ok' => true, 'error' => '', 'body' => array(
        'title'       => $title,
        'description' => pg_ebay_plain_description(isset($items[0]['description']) ? $items[0]['description'] : ''),
        'aspects'     => (object) $common,
        'imageUrls'   => array_slice(array_keys($pictures), 0, $limits['pictures']),
        'variantSKUs' => array_values(array_map(function ($item) {
            return (string) $item['code'];
        }, $items)),
        'variesBy'    => array('aspectsImageVariesBy' => array(), 'specifications' => $varies),
    ));
}

/**
 * The createOffer (or updateOffer) body of one listing item on a site: a
 * fixed price offer in the account's category, at the account's location,
 * under its three business policies, at $price in the site's currency. Pure.
 *
 * @param array  $item     mp_listing_items()
 * @param string $site
 * @param array  $choices  location, fulfillment_policy, payment_policy, return_policy
 * @param string $price    pg_ebay_offer_price()
 * @return array
 */
function pg_ebay_offer_body($item, $site, $choices, $price)
{
    $sites = pg_ebay_sites();

    return array(
        'sku'                 => (string) $item['code'],
        'marketplaceId'       => $site,
        'format'              => 'FIXED_PRICE',
        'availableQuantity'   => max(0, (int) $item['quantity']),
        'categoryId'          => (string) $item['category_id'],
        'listingDescription'  => (string) $item['description'],
        'merchantLocationKey' => (string) $choices['location'],
        'listingPolicies'     => array(
            'fulfillmentPolicyId' => (string) $choices['fulfillment_policy'],
            'paymentPolicyId'     => (string) $choices['payment_policy'],
            'returnPolicyId'      => (string) $choices['return_policy'],
        ),
        'pricingSummary'      => array('price' => array('value' => (string) $price, 'currency' => $sites[$site]['currency'])),
    );
}

/**
 * The offer of a getOffers answer for one site: array(offer_id, status,
 * listing_id, listing_status), or null when the SKU has none there. Pure.
 *
 * @param mixed  $data
 * @param string $site
 * @return array|null
 */
function pg_ebay_offer_of($data, $site)
{
    $list = (is_array($data) && isset($data['offers']) && is_array($data['offers'])) ? $data['offers'] : array();

    foreach ($list as $offer) {
        if (!is_array($offer) || !isset($offer['offerId']) || !is_scalar($offer['offerId']) || ((string) $offer['offerId'] === '')) {
            continue;
        }

        if (isset($offer['marketplaceId']) && ($offer['marketplaceId'] !== $site)) {
            continue;
        }

        $listing = (isset($offer['listing']) && is_array($offer['listing'])) ? $offer['listing'] : array();

        return array(
            'offer_id'       => (string) $offer['offerId'],
            'status'         => isset($offer['status']) && is_string($offer['status']) ? $offer['status'] : '',
            'listing_id'     => isset($listing['listingId']) && is_scalar($listing['listingId']) ? (string) $listing['listingId'] : '',
            'listing_status' => isset($listing['listingStatus']) && is_string($listing['listingStatus']) ? $listing['listingStatus'] : '',
        );
    }

    return null;
}

/**
 * Where one SKU's listing stands, as the marketplace layer's listing state
 * (mp_sync_listing_row_update()): a published offer with a listing id is
 * 'approved' with the listing id as its content id, and an ended listing is
 * noted as archived; anything else is 'unknown', since eBay reviews
 * nothing and a refused publish was written when it was sent. Pure.
 *
 * @param array|null $offer pg_ebay_offer_of()
 * @return array
 */
function pg_ebay_listing_state($offer)
{
    if (!is_array($offer) || ($offer['status'] !== 'PUBLISHED') || ($offer['listing_id'] === '')) {
        return array('state' => 'unknown');
    }

    return array(
        'state'      => 'approved',
        'content_id' => $offer['listing_id'],
        'archived'   => in_array($offer['listing_status'], array('ENDED', 'EBAY_ENDED'), true),
    );
}

/**
 * Every message of an eBay error answer, joined, at most three. Pure.
 *
 * @param mixed $data
 * @return string
 */
function pg_ebay_error_messages($data)
{
    $messages = array();

    foreach ((is_array($data) && isset($data['errors']) && is_array($data['errors'])) ? $data['errors'] : array() as $error) {
        $text = (is_array($error) && isset($error['message']) && is_string($error['message'])) ? trim($error['message']) : '';

        if (($text !== '') && !in_array($text, $messages, true)) {
            $messages[] = $text;
        }
    }

    return implode(' ', array_slice($messages, 0, 3));
}

/* ---------------------------------------------------------------------------
   Stock and price (connectors/ebay.php): bulkUpdatePriceQuantity
   --------------------------------------------------------------------------- */

/**
 * One SKU's request of a bulkUpdatePriceQuantity call: the quantity on the
 * inventory item and on the offer when stock is sent, the offer's price in
 * the site's currency when a price is given. A product that does not track
 * stock sends no quantity: zero would take the listing off sale. null when
 * there is nothing to say about the SKU. Pure.
 *
 * @param array  $row       mp_product_row(): remote_code, quantity, tracked
 * @param string $offer_id
 * @param bool   $with_stock
 * @param string $price     pg_ebay_offer_price(), '' to send no price
 * @param string $currency  the site's currency
 * @return array|null
 */
function pg_ebay_bulk_request($row, $offer_id, $with_stock, $price, $currency)
{
    $offer = array('offerId' => (string) $offer_id);
    $request = array('sku' => trim((string) $row['remote_code']));

    if ($with_stock && !empty($row['tracked'])) {
        $quantity = max(0, (int) $row['quantity']);
        $request['shipToLocationAvailability'] = array('quantity' => $quantity);
        $offer['availableQuantity'] = $quantity;
    }

    if ((string) $price !== '') {
        $offer['price'] = array('value' => (string) $price, 'currency' => (string) $currency);
    }

    if (count($offer) === 1) {
        return null;
    }

    $request['offers'] = array($offer);

    return $request;
}

/**
 * Each SKU's verdict in a bulkUpdatePriceQuantity answer (200 or 207): SKU
 * => '' when every response of it is 200, else eBay's messages. A SKU the
 * answer does not name is not in the result. Pure.
 *
 * @param mixed $data
 * @return array
 */
function pg_ebay_bulk_verdicts($data)
{
    $verdicts = array();
    $list = (is_array($data) && isset($data['responses']) && is_array($data['responses'])) ? $data['responses'] : array();

    foreach ($list as $response) {
        if (!is_array($response) || !isset($response['sku']) || !is_scalar($response['sku'])) {
            continue;
        }

        $sku = (string) $response['sku'];
        $status = isset($response['statusCode']) ? (int) $response['statusCode'] : 0;

        if (!isset($verdicts[$sku])) {
            $verdicts[$sku] = '';
        }

        if ($status === 200) {
            continue;
        }

        $messages = pg_ebay_error_messages($response);
        $text = ($messages !== '') ? $messages : ('HTTP ' . $status);

        $verdicts[$sku] = ($verdicts[$sku] === '') ? $text : $verdicts[$sku] . ' ' . $text;
    }

    return $verdicts;
}

/* ---------------------------------------------------------------------------
   Orders (connectors/ebay.php): getOrders and createShippingFulfillment
   --------------------------------------------------------------------------- */

/**
 * An eBay amount ({value, currency}) in minor units, rounded half away
 * from zero. Every eBay site's currency has two decimals. A discount eBay
 * gives as a negative amount counts as its size. 0 when there is none. Pure.
 *
 * @param mixed $amount
 * @return int
 */
function pg_ebay_minor($amount)
{
    $value = (is_array($amount) && isset($amount['value'])) ? $amount['value'] : null;

    if (!is_numeric($value)) {
        return 0;
    }

    return (int) abs(round(round((float) $value * 100, 6)));
}

/**
 * A moment as eBay's filters write it: UTC with milliseconds. Pure.
 *
 * @param int $seconds
 * @return string
 */
function pg_ebay_iso_time($seconds)
{
    return gmdate('Y-m-d\TH:i:s', (int) $seconds) . '.000Z';
}

/**
 * Unix seconds of an eBay timestamp, 0 when it does not read. Pure.
 *
 * @param mixed $text
 * @return int
 */
function pg_ebay_seconds($text)
{
    $seconds = is_string($text) ? strtotime($text) : false;

    return ($seconds === false) ? 0 : (int) $seconds;
}

/**
 * The window of last modification one pull asks for: from five minutes
 * before the cursor (the importer skips what it has seen), never older than
 * the ninety days getOrders keeps (an hour inside them), to now or a week
 * past the cursor, whichever is first, so a backlog is read a week a pass
 * and the cursor still moves. A first pull reads the last week. Pure.
 *
 * @param int $since the cursor, 0 before the first pull
 * @param int $now
 * @return array array(start, end) in unix seconds
 */
function pg_ebay_order_window($since, $now)
{
    $since = (int) $since;
    $now = (int) $now;

    if ($since <= 0) {
        $since = $now - 604800;
    }

    $oldest = $now - 7776000 + 3600;

    return array(max($since - 300, $oldest), min($now, max($since, $oldest) + 604800));
}

/**
 * One page of getOrders over a window of last modification. Pure.
 *
 * @param int $start
 * @param int $end
 * @param int $offset
 * @return string
 */
function pg_ebay_orders_path($start, $end, $offset)
{
    $filter = 'lastmodifieddate:[' . pg_ebay_iso_time($start) . '..' . pg_ebay_iso_time($end) . ']';

    return '/sell/fulfillment/v1/order?filter=' . rawurlencode($filter)
        . '&limit=' . pg_ebay_listing_limits()['orders'] . '&offset=' . (int) $offset;
}

/**
 * The one word an order's state is kept under (marketplace_order_map's
 * remote_status): CANCELED once eBay granted a cancellation, else its
 * fulfilment status (NOT_STARTED, IN_PROGRESS for part shipped, FULFILLED).
 * A cancellation only asked for leaves the order as it is. Pure.
 *
 * @param array $order
 * @return string
 */
function pg_ebay_order_status($order)
{
    $cancel = (isset($order['cancelStatus']['cancelState']) && is_string($order['cancelStatus']['cancelState']))
        ? $order['cancelStatus']['cancelState'] : '';

    if ($cancel === 'CANCELED') {
        return 'CANCELED';
    }

    return (isset($order['orderFulfillmentStatus']) && is_string($order['orderFulfillmentStatus']))
        ? mb_substr($order['orderFulfillmentStatus'], 0, 40) : '';
}

/**
 * A text field of an eBay answer, trimmed; '' when it is not text. Pure.
 *
 * @param mixed  $row
 * @param string $key
 * @return string
 */
function pg_ebay_text($row, $key)
{
    return (is_array($row) && isset($row[$key]) && is_scalar($row[$key])) ? trim((string) $row[$key]) : '';
}

/**
 * The delivery address of an order (its first shipping step's shipTo) in
 * the importer's shape, or an empty array (a collected or digital order).
 * Pure.
 *
 * @param array $order
 * @return array
 */
function pg_ebay_order_address($order)
{
    $ship_to = (isset($order['fulfillmentStartInstructions'][0]['shippingStep']['shipTo'])
        && is_array($order['fulfillmentStartInstructions'][0]['shippingStep']['shipTo']))
        ? $order['fulfillmentStartInstructions'][0]['shippingStep']['shipTo'] : array();

    if (!$ship_to) {
        return array();
    }

    $address = (isset($ship_to['contactAddress']) && is_array($ship_to['contactAddress'])) ? $ship_to['contactAddress'] : array();

    return array(
        'name'      => pg_ebay_text($ship_to, 'fullName'),
        'company'   => pg_ebay_text($ship_to, 'companyName'),
        'address_1' => pg_ebay_text($address, 'addressLine1'),
        'address_2' => pg_ebay_text($address, 'addressLine2'),
        'city'      => pg_ebay_text($address, 'city'),
        'state'     => pg_ebay_text($address, 'stateOrProvince'),
        'district'  => pg_ebay_text($address, 'county'),
        'zip'       => pg_ebay_text($address, 'postalCode'),
        'country'   => pg_ebay_text($address, 'countryCode'),
        'phone'     => isset($ship_to['primaryPhone']) ? pg_ebay_text($ship_to['primaryPhone'], 'phoneNumber') : '',
        'email'     => pg_ebay_text($ship_to, 'email'),
    );
}

/**
 * One eBay order as the package mp_import_order() takes, or null: no order
 * id, or not paid yet (PENDING, FAILED), which comes back once paid since
 * paying changes it. The order is the package: getOrders has no smaller
 * unit that ships.
 *
 * Amounts are in the order's own currency (pricingSummary's), in minor
 * units: a line's price per unit is its lineItemCost (unit price times
 * quantity) over the quantity, its discount the seller's promotions on the
 * whole line, the shipping the delivery cost less its discount. The price
 * is what the buyer was shown, tax included where the site's prices
 * include it; the taxes eBay collects and remits itself
 * (ebayCollectAndRemitTaxes) are not the seller's money and are left out.
 * Only what delivering the order needs is kept of the buyer: the delivery
 * address, which stands in as the billing address, its name, telephone
 * and e-mail. Pure.
 *
 * @param array $order
 * @return array|null
 */
function pg_ebay_order_package($order)
{
    $order_id = pg_ebay_text($order, 'orderId');

    if ($order_id === '') {
        return null;
    }

    if (in_array(pg_ebay_text($order, 'orderPaymentStatus'), array('PENDING', 'FAILED'), true)) {
        return null;
    }

    $lines = array();
    $currency = '';

    foreach ((isset($order['lineItems']) && is_array($order['lineItems'])) ? $order['lineItems'] : array() as $item) {
        if (!is_array($item)) {
            continue;
        }

        $quantity = isset($item['quantity']) ? max(1, (int) $item['quantity']) : 1;
        $discount = 0;

        foreach ((isset($item['appliedPromotions']) && is_array($item['appliedPromotions'])) ? $item['appliedPromotions'] : array() as $promotion) {
            $discount += pg_ebay_minor(is_array($promotion) && isset($promotion['discountAmount']) ? $promotion['discountAmount'] : null);
        }

        $tax = 0;

        foreach ((isset($item['taxes']) && is_array($item['taxes'])) ? $item['taxes'] : array() as $entry) {
            $tax += pg_ebay_minor(is_array($entry) && isset($entry['amount']) ? $entry['amount'] : null);
        }

        if (($currency === '') && isset($item['lineItemCost']['currency'])) {
            $currency = pg_ebay_text($item['lineItemCost'], 'currency');
        }

        $sku = pg_ebay_text($item, 'sku');

        $lines[] = array(
            'remote_line_id' => pg_ebay_text($item, 'lineItemId'),
            'code'           => $sku,
            'sku'            => $sku,
            'name'           => pg_ebay_text($item, 'title'),
            'quantity'       => $quantity,
            'price'          => (int) round(pg_ebay_minor(isset($item['lineItemCost']) ? $item['lineItemCost'] : null) / $quantity),
            'tax'            => (int) round($tax / $quantity),
            'discount'       => (int) round($discount / $quantity),
            'discount_total' => $discount,
            'status'         => pg_ebay_text($item, 'lineItemFulfillmentStatus'),
        );
    }

    $summary = (isset($order['pricingSummary']) && is_array($order['pricingSummary'])) ? $order['pricingSummary'] : array();

    if (isset($summary['total']['currency'])) {
        $currency = pg_ebay_text($summary['total'], 'currency');
    }

    $shipping = max(0, pg_ebay_minor(isset($summary['deliveryCost']) ? $summary['deliveryCost'] : null)
        - pg_ebay_minor(isset($summary['deliveryDiscount']) ? $summary['deliveryDiscount'] : null));

    $address = pg_ebay_order_address($order);
    $billing = $address ? $address : array_fill_keys(array('name', 'company', 'address_1', 'address_2', 'city', 'state', 'district', 'zip', 'country', 'phone', 'email'), '');
    $name = isset($address['name']) ? $address['name'] : '';
    $space = ($name !== '') ? strrpos($name, ' ') : false;

    return array(
        'remote_order_id'   => $order_id,
        'remote_package_id' => $order_id,
        'remote_status'     => pg_ebay_order_status($order),
        // The modification time, on the scale the feed is asked on: "Try
        // again" moves the pull cursor back to it.
        'placed_at'         => pg_ebay_seconds(isset($order['lastModifiedDate']) ? $order['lastModifiedDate'] : null),
        'currency'          => strtoupper($currency),
        'customer'          => array(
            'first_name' => ($space === false) ? $name : trim(substr($name, 0, $space)),
            'last_name'  => ($space === false) ? '' : trim(substr($name, $space + 1)),
            'email'      => isset($address['email']) ? $address['email'] : '',
            'phone'      => isset($address['phone']) ? $address['phone'] : '',
            'company'    => isset($address['company']) ? $address['company'] : '',
        ),
        'billing'           => $billing,
        'shipping_address'  => $address,
        'shipping_cost'     => $shipping,
        'lines'             => $lines,
    );
}

/**
 * The shippingCarrierCode a parcel is reported with: eBay's code for a
 * carrier of the shop's list it knows, else the carrier's name as the shop
 * has it. '' when the parcel names none. Pure.
 *
 * @param string $carrier the carrier's key in pg_shipping_carriers(), or ''
 * @param string $name    the carrier's name as the order screen shows it
 * @return string
 */
function pg_ebay_carrier_code($carrier, $name)
{
    $codes = array(
        'ups'          => 'UPS',
        'usps'         => 'USPS',
        'fedex'        => 'FedEx',
        'dhl'          => 'DHL',
        'deutschepost' => 'DeutschePost',
        'dpd'          => 'DPD',
        'royalmail'    => 'RoyalMail',
    );

    $carrier = trim((string) $carrier);

    if (isset($codes[$carrier])) {
        return $codes[$carrier];
    }

    return mb_substr(trim((string) $name), 0, 50);
}

/**
 * The body of a createShippingFulfillment call: every line of the package
 * in its quantity, shipped with that carrier under that number. null when
 * there is no number, no carrier or no line. Pure.
 *
 * @param array  $package     the imported package (mp_sync_package_from_row())
 * @param string $number
 * @param string $carrier_code pg_ebay_carrier_code()
 * @param int    $shipped_at  unix seconds
 * @return array|null
 */
function pg_ebay_fulfillment_body($package, $number, $carrier_code, $shipped_at)
{
    $number = trim((string) $number);
    $carrier_code = trim((string) $carrier_code);

    if (($number === '') || ($carrier_code === '')) {
        return null;
    }

    $items = array();

    foreach ((isset($package['lines']) && is_array($package['lines'])) ? $package['lines'] : array() as $line) {
        $id = is_array($line) ? pg_ebay_text($line, 'remote_line_id') : '';

        if ($id !== '') {
            $items[] = array('lineItemId' => $id, 'quantity' => isset($line['quantity']) ? max(1, (int) $line['quantity']) : 1);
        }
    }

    if (!$items) {
        return null;
    }

    return array(
        'lineItems'           => $items,
        'shippedDate'         => pg_ebay_iso_time($shipped_at),
        'shippingCarrierCode' => $carrier_code,
        'trackingNumber'      => $number,
    );
}
