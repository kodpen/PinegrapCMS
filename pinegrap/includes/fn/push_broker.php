<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Native push for team devices through the Kodpen push broker: a device
 * gives the site its FCM or APNs token, the site trades it with the broker,
 * server to server, for an https endpoint, and keeps that endpoint as a
 * push_subscriptions row (provider 'fcm' or 'apns'). The site never stores
 * the token; the sender, the clean-up and the device revoke treat the row as
 * they treat a browser's.
 *
 * Every call to the broker goes through pg_push_broker_call(). The broker's
 * answer is read by the pure pg_push_broker_answer(); quotas and who may use
 * the broker are the broker's own settings, so a refusal is passed on to the
 * device as the broker gave it.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// The broker's address. Empty until the broker is live: the device routes then
// answer 503 and GET /meta features.native_push is false. data/config.php may
// define it first to point a test site at a test broker.
if (!defined('PG_PUSH_BROKER_URL')) {
    define('PG_PUSH_BROKER_URL', '');
}

/**
 * The providers a device may register with.
 *
 * @return string[]
 */
function pg_push_broker_providers()
{
    return array('fcm', 'apns');
}

/**
 * The broker's base address: an https URL with a host and an optional path,
 * no user, query or fragment, without the trailing slash; '' for anything
 * else, so a mistyped value calls nothing. Pure.
 *
 * @param string $value
 * @return string
 */
function pg_push_broker_base($value)
{
    $value = trim((string) $value);

    if (($value === '') || preg_match('/[\s\x00-\x1F\x7F]/', $value)) {
        return '';
    }

    $parts = parse_url($value);

    if (!is_array($parts) || (strtolower((string) ($parts['scheme'] ?? '')) !== 'https') || ((string) ($parts['host'] ?? '') === '')
        || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) {
        return '';
    }

    return rtrim($value, '/');
}

/**
 * Whether push_subscriptions has the provider column (step 11.0.215); files
 * land before the schema does.
 *
 * @return bool
 */
function pg_push_broker_has_provider()
{
    return pg_push_schema_available() && pg_push_has_provider();
}

/**
 * Whether a device can register here: a broker address, a VAPID key to sign
 * with and the provider column. Needs includes/push.php.
 *
 * @return bool
 */
function pg_push_broker_ready()
{
    return (pg_push_broker_base(PG_PUSH_BROKER_URL) !== '') && (pg_push_vapid_public_key() !== '') && pg_push_broker_has_provider();
}

/**
 * The licence key as the licence check sends it, '' when none is set or it
 * cannot be a licence key.
 *
 * @return string
 */
function pg_push_broker_license_key()
{
    if (!defined('SUBSCRIPTION_KEY') || !function_exists('pg_license_key_normalize')) {
        return '';
    }

    $key = pg_license_key_normalize((string) SUBSCRIPTION_KEY);

    return pg_license_key_valid($key) ? $key : '';
}

/**
 * The headers of a broker call. Authorization carries the VAPID token, as the
 * sender's does; the licence key cannot share that header, so it goes in
 * X-Pinegrap-License, and only when there is one. Pure.
 *
 * @param string $token       the VAPID token for the broker's origin
 * @param string $public_key  the site's VAPID public key
 * @param string $license_key a normalised valid licence key, or ''
 * @param string $site        the site name as the Kodpen API client sends it, or ''
 * @return string[]
 */
function pg_push_broker_headers($token, $public_key, $license_key, $site)
{
    $headers = array(
        'Authorization: vapid t=' . $token . ', k=' . $public_key,
        'Content-Type: application/json',
        'Accept: application/json, application/problem+json',
    );

    if ((string) $license_key !== '') {
        $headers[] = 'X-Pinegrap-License: ' . $license_key;
    }

    if ((string) $site !== '') {
        $headers[] = 'X-Pinegrap-Site: ' . $site;
    }

    return $headers;
}

/**
 * The broker's own words on a refusal (error.message, detail or title of its
 * JSON body), as plain text of at most 300 characters; '' when it gave none.
 * Pure.
 *
 * @param mixed $body the decoded body
 * @return string
 */
function pg_push_broker_detail($body)
{
    $text = '';

    if (is_array($body)) {
        if (isset($body['error']['message']) && is_string($body['error']['message'])) {
            $text = $body['error']['message'];
        } elseif (isset($body['detail']) && is_string($body['detail'])) {
            $text = $body['detail'];
        } elseif (isset($body['title']) && is_string($body['title'])) {
            $text = $body['title'];
        }
    }

    // Not UTF-8: nothing of it is shown.
    if (@preg_match('//u', $text) !== 1) {
        return '';
    }

    $text = trim((string) preg_replace('/\s+/u', ' ', (string) preg_replace('/[\x00-\x1F\x7F]/', ' ', strip_tags($text))));

    return (mb_strlen($text) > 300) ? rtrim(mb_substr($text, 0, 299)) . '…' : $text;
}

/**
 * What one broker answer means. $result is what the outbound client gives
 * (status, body, error, headers by lower-case name). Pure.
 *
 * register: ok with the endpoint (an https URL of at most 500 characters) on
 * 200 or 201. forget: ok on 2xx, and on 404 and 410, which say the endpoint
 * is already gone. A refusal gives reason (unreachable, unreadable, busy,
 * token, refused), the status and code the device is answered with, the
 * field, retry_after in seconds for busy, and the broker's detail.
 *
 * @param string $action register or forget
 * @param array  $result
 * @return array
 */
function pg_push_broker_answer($action, $result)
{
    $status = (int) ($result['status'] ?? 0);
    $body = json_decode((string) ($result['body'] ?? ''), true);
    $out = array('ok' => false, 'endpoint' => '', 'broker_status' => $status, 'reason' => '', 'status' => 503, 'code' => 'service_unavailable', 'field' => null, 'retry_after' => 0, 'detail' => '');

    if ($action === 'forget') {
        $out['ok'] = (($status >= 200) && ($status < 300)) || ($status === 404) || ($status === 410);
        $out['reason'] = $out['ok'] ? '' : 'unreachable';
        return $out;
    }

    if (($status === 200) || ($status === 201)) {
        $endpoint = (is_array($body) && isset($body['endpoint']) && is_string($body['endpoint'])) ? trim($body['endpoint']) : '';

        if (($endpoint !== '') && (strlen($endpoint) <= 500) && (stripos($endpoint, 'https://') === 0) && (filter_var($endpoint, FILTER_VALIDATE_URL) !== false)) {
            $out['ok'] = true;
            $out['endpoint'] = $endpoint;
            return $out;
        }

        $out['reason'] = 'unreadable';
        return $out;
    }

    $out['detail'] = pg_push_broker_detail($body);

    if ($status === 429) {
        $wait = isset($result['headers']['retry-after']) ? trim((string) $result['headers']['retry-after']) : '';
        $out['reason'] = 'busy';
        $out['status'] = 429;
        $out['code'] = 'rate_limited';
        $out['retry_after'] = ctype_digit($wait) ? max(1, min(3600, (int) $wait)) : 60;
    } elseif (in_array($status, array(400, 404, 410, 422), true)) {
        $out['reason'] = 'token';
        $out['status'] = 422;
        $out['code'] = 'validation_failed';
        $out['field'] = 'token';
    } elseif (($status >= 401) && ($status < 500)) {
        $out['reason'] = 'refused';
        $out['status'] = 403;
        $out['code'] = 'forbidden';
    } else {
        $out['reason'] = 'unreachable';
        $out['detail'] = '';
    }

    return $out;
}

/**
 * The one call to the broker. register sends provider, token and bundle_id
 * to POST {broker}/v1/endpoints; forget sends endpoint to
 * POST {broker}/v1/endpoints/delete. Signed with the site's VAPID key for the
 * broker's origin, as the sender signs a push. Needs includes/push.php.
 *
 * @param string        $action  register or forget
 * @param array         $payload
 * @param callable|null $send    function ($url, $body, $headers) returning the
 *                               outbound client's result; tests pass a stub
 * @return array pg_push_broker_answer()
 */
function pg_push_broker_call($action, array $payload, $send = null)
{
    $base = pg_push_broker_base(PG_PUSH_BROKER_URL);
    $token = ($base !== '') ? pg_push_vapid_token($base) : '';

    if ($token === '') {
        return pg_push_broker_answer($action, array('status' => 0));
    }

    $url = $base . (($action === 'forget') ? '/v1/endpoints/delete' : '/v1/endpoints');
    $site = (function_exists('pg_vendor_api_site_value') && defined('HOSTNAME_SETTING')) ? (string) pg_vendor_api_site_value((string) HOSTNAME_SETTING) : '';
    $headers = pg_push_broker_headers($token, pg_push_vapid_public_key(), pg_push_broker_license_key(), $site);
    $body = json_encode($payload, JSON_UNESCAPED_SLASHES);

    if ($send === null) {
        $send = function ($url, $body, $headers) use ($action) {
            include_once(PG_FUNCTIONS_DIR . '/includes/api/outbound/http.php');

            return api_http_request('POST', $url, array(
                'body'             => $body,
                'headers'          => $headers,
                // A forget runs inside a sign-out; it does not hold it long.
                'timeout'          => ($action === 'forget') ? 5 : 10,
                'agent'            => 'push',
                'response_headers' => true,
            ));
        };
    }

    $result = call_user_func($send, $url, $body, $headers);

    return pg_push_broker_answer($action, is_array($result) ? $result : array('status' => 0));
}

/**
 * Has the broker forget native push endpoints whose rows the site is about to
 * delete: a device signed out or revoked, its registration deleted, a row an
 * operator stopped. Asks nothing while no broker is set; a broker that does
 * not answer is not asked again, and the endpoint, never used again, expires
 * there. The caller deletes the rows itself. Needs includes/push.php.
 *
 * @param string[] $endpoints
 * @return void
 */
function pg_push_broker_forget_endpoints($endpoints)
{
    if (pg_push_broker_base(PG_PUSH_BROKER_URL) === '') {
        return;
    }

    foreach ((array) $endpoints as $endpoint) {
        $answer = pg_push_broker_call('forget', array('endpoint' => (string) $endpoint));

        if ($answer['reason'] === 'unreachable') {
            return;
        }
    }
}
