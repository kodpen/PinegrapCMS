<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Mail transport webhooks: the bounce, complaint and delivery reports a
 * sending service posts to mail_webhook.php?t=<key>&key=<secret>, written to
 * the bounce record (pg_mail_bounce_record(), includes/fn/mail_bounces.php)
 * the moment they arrive.
 *
 * A driver (includes/mail/<key>.php) takes part with two functions beside
 * its contract:
 *
 *   pg_mail_<key>_webhook($raw, $headers)         pure: list of id, kind
 *                                                 (hard, soft, complaint,
 *                                                 delivered, confirm or ''),
 *                                                 address, message_id (url
 *                                                 for confirm); false for a
 *                                                 body it cannot read
 *   pg_mail_<key>_webhook_verify($raw, $headers, $signing, $now)
 *                                                 optional, pure, for a
 *                                                 driver whose capabilities
 *                                                 say webhook_signed: ok,
 *                                                 stale or bad
 *
 * The secret key in the address is every driver's credential; a service
 * that signs its messages (Mailgun, Resend) is checked against the signing
 * key typed on the card as well, when one is stored. Both live in the
 * transport's sealed secrets (connect_apps mail_<key>: webhook_key,
 * webhook_signing). The key is made when the transport is saved and kept
 * when another one is chosen: a message sent yesterday may bounce today.
 *
 * A report already seen (its event id among the last PG_MAIL_WEBHOOK_SEEN_KEPT)
 * or a signed one older than PG_MAIL_WEBHOOK_MAX_AGE is a replay; it and a
 * report without an address the bounce record can key answer 200 and
 * record nothing, so the service does not send it again.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// A signed report older than this (seconds) is a replay.
define('PG_MAIL_WEBHOOK_MAX_AGE', 300);

// Reports one address may post a minute; a campaign's bounces arrive in bursts.
define('PG_MAIL_WEBHOOK_PER_MINUTE', 600);

// The largest body read; a report names one message.
define('PG_MAIL_WEBHOOK_MAX_BODY', 262144);

// Event ids remembered to tell a replay.
define('PG_MAIL_WEBHOOK_SEEN_KEPT', 500);

/**
 * The address a transport's service posts its reports to.
 *
 * @param string $key   the transport
 * @param string $token the secret key
 * @return string
 */
function pg_mail_webhook_url($key, $token)
{
    // init.php defines the site's address; without it (a unit test run) the
    // address is relative.
    $base = defined('URL_SCHEME') ? URL_SCHEME . HOSTNAME_SETTING . PATH . SOFTWARE_DIRECTORY : '';

    return $base . '/mail_webhook.php?t=' . rawurlencode((string) $key) . '&key=' . rawurlencode((string) $token);
}

/**
 * Whether a transport reads webhook reports, and whether it signs them.
 *
 * @param string $key
 * @return array webhook (bool), signed (bool)
 */
function pg_mail_webhook_support($key)
{
    if (!pg_mail_transport_driver($key) || !function_exists('pg_mail_' . $key . '_webhook')) {
        return array('webhook' => false, 'signed' => false);
    }

    $capabilities = call_user_func('pg_mail_' . $key . '_capabilities');

    return array(
        'webhook' => !empty($capabilities['webhook']),
        'signed'  => !empty($capabilities['webhook_signed']) && function_exists('pg_mail_' . $key . '_webhook_verify'),
    );
}

/**
 * A transport's webhook secrets.
 *
 * @param string $key
 * @return array webhook_key, webhook_signing ('' when none)
 */
function pg_mail_webhook_secrets($key)
{
    $row = pg_connect_schema_ready() ? db_item("SELECT secret FROM connect_apps WHERE provider = '" . e(pg_mail_transport_app_key($key)) . "'") : null;
    $secrets = is_array($row) ? pg_connect_unseal($row['secret']) : array();
    $out = array();

    foreach (array('webhook_key', 'webhook_signing') as $name) {
        $out[$name] = (is_array($secrets) && isset($secrets[$name]) && is_string($secrets[$name])) ? $secrets[$name] : '';
    }

    return $out;
}

/**
 * Changes webhook values among a transport's sealed secrets; the account
 * row must exist.
 *
 * @param string $key
 * @param array  $changes name => text
 * @return bool
 */
function pg_mail_webhook_secrets_put($key, $changes)
{
    if (!$changes || !pg_connect_schema_ready() || !pg_connect_can_seal()) {
        return false;
    }

    $provider = pg_mail_transport_app_key($key);
    $row = db_item("SELECT secret FROM connect_apps WHERE provider = '" . e($provider) . "'");

    if (!is_array($row)) {
        return false;
    }

    $secrets = pg_connect_unseal($row['secret']);
    $secrets = is_array($secrets) ? $secrets : array();

    foreach ($changes as $name => $value) {
        $secrets[$name] = (string) $value;
    }

    db("UPDATE connect_apps SET secret = '" . e(pg_connect_seal($secrets)) . "' WHERE provider = '" . e($provider) . "'");

    return true;
}

/**
 * What a save of the card writes among the chosen transport's webhook
 * secrets: a key when it has none, the signing key when one was typed (an
 * empty box keeps the stored one). Pure.
 *
 * @param array  $support pg_mail_webhook_support()
 * @param array  $stored  pg_mail_webhook_secrets()
 * @param string $signing the posted signing key
 * @param string $new_key a fresh key, used when none is stored
 * @return array name => value, empty for nothing
 */
function pg_mail_webhook_settings_decide($support, $stored, $signing, $new_key)
{
    $changes = array();

    if (!$support['webhook']) {
        return $changes;
    }

    if ($stored['webhook_key'] === '') {
        $changes['webhook_key'] = $new_key;
    }

    if ($support['signed'] && ($signing !== '') && ($signing !== $stored['webhook_signing'])) {
        $changes['webhook_signing'] = $signing;
    }

    return $changes;
}

/**
 * Writes the chosen transport's webhook secrets after the card's save.
 *
 * @param array  $post
 * @param string $chosen a transport key or 'smtp'
 */
function pg_mail_webhook_settings_save($post, $chosen)
{
    if (($chosen === 'smtp') || !pg_mail_transport_driver($chosen)) {
        return;
    }

    pg_mail_webhook_secrets_put($chosen, pg_mail_webhook_settings_decide(
        pg_mail_webhook_support($chosen),
        pg_mail_webhook_secrets($chosen),
        pg_mail_transport_post($post, 'mail_' . $chosen . '_webhook_signing'),
        bin2hex(random_bytes(16))));
}

/**
 * The webhook lines of a transport's pane on the card.
 *
 * @param string $key
 * @return string
 */
function pg_mail_webhook_settings_fields($key)
{
    $support = pg_mail_webhook_support($key);

    if (!$support['webhook']) {
        return '';
    }

    $secrets = pg_mail_webhook_secrets($key);
    $id = 'mail_' . $key . '_webhook_url';

    if ($secrets['webhook_key'] === '') {
        $address = '<div class="form-text">' . h(lang('Save the service to get the address its bounce and complaint reports are posted to.')) . '</div>';
    } else {
        $address = '<input type="text" id="' . h($id) . '" class="form-control" value="' . h(pg_mail_webhook_url($key, $secrets['webhook_key'])) . '" readonly>
                    <div class="form-text">' . h(lang('Add this address to the service as a webhook for bounces, complaints and deliveries.')) . '</div>';
    }

    $out = '
                <div class="col-12 my-3">
                    <label for="' . h($id) . '" class="form-label">' . h(lang('Webhook address')) . '</label>
                    ' . $address . '
                </div>';

    if ($support['signed']) {
        $name = 'mail_' . $key . '_webhook_signing';
        $placeholder = ($secrets['webhook_signing'] !== '') ? ' placeholder="' . h(lang('Stored. Leave empty to keep it.')) . '"' : '';
        $out .= '
                <div class="col-12 md:col-6 my-3">
                    <label for="' . h($name) . '" class="form-label">' . h(lang('Webhook signing key')) . '</label>
                    <input type="password" name="' . h($name) . '" id="' . h($name) . '" class="form-control" value="" autocomplete="new-password"' . $placeholder . '>
                    <div class="form-text">' . h(lang('Optional. With it, a report that the service did not sign is refused.')) . '</div>
                </div>';
    }

    return $out;
}

/**
 * Whether an event was seen, and the list to keep. Pure.
 *
 * @param mixed  $seen list of event ids
 * @param string $id
 * @return array seen (bool), list
 */
function pg_mail_webhook_seen_note($seen, $id)
{
    $list = is_array($seen) ? array_values(array_filter($seen, 'is_string')) : array();

    if (in_array($id, $list, true)) {
        return array('seen' => true, 'list' => $list);
    }

    $list[] = $id;

    return array('seen' => false, 'list' => array_slice($list, -PG_MAIL_WEBHOOK_SEEN_KEPT));
}

/**
 * Whether an event was handled before; remembers it when not.
 *
 * @param string $id
 * @return bool
 */
function pg_mail_webhook_seen($id)
{
    $path = PG_FUNCTIONS_DIR . '/data/temp/mail_webhook.json';
    $handle = @fopen($path, 'c+');

    if (!$handle) {
        return false;
    }

    flock($handle, LOCK_EX);
    $note = pg_mail_webhook_seen_note(json_decode((string) stream_get_contents($handle), true), $id);

    if (!$note['seen']) {
        ftruncate($handle, 0);
        rewind($handle);
        fwrite($handle, json_encode($note['list']));
    }

    flock($handle, LOCK_UN);
    fclose($handle);

    return $note['seen'];
}

/**
 * The request headers by lower-case, dash-separated name. Pure.
 *
 * @param array $server $_SERVER
 * @return array
 */
function pg_mail_webhook_headers($server)
{
    $headers = array();

    foreach ((array) $server as $name => $value) {
        if (is_string($value) && (strncmp((string) $name, 'HTTP_', 5) === 0)) {
            $headers[strtolower(str_replace('_', '-', substr($name, 5)))] = $value;
        }
    }

    return $headers;
}

/**
 * What one event does. Pure.
 *
 * @param array $event a driver's webhook event
 * @return array action (record, confirm, delivered, ignored), kind, address
 */
function pg_mail_webhook_decide($event)
{
    $kind = (is_array($event) && isset($event['kind'])) ? (string) $event['kind'] : '';
    $address = (is_array($event) && isset($event['address'])) ? pg_mail_bounce_address($event['address']) : '';

    if (($kind === 'confirm') && !empty($event['url'])) {
        return array('action' => 'confirm', 'kind' => $kind, 'address' => '');
    }

    if (in_array($kind, array('hard', 'soft', 'complaint'), true) && ($address !== '')) {
        return array('action' => 'record', 'kind' => $kind, 'address' => $address);
    }

    return array('action' => ($kind === 'delivered') ? 'delivered' : 'ignored', 'kind' => $kind, 'address' => $address);
}

/**
 * Sends the answer and ends the request.
 *
 * @param int   $http
 * @param array $data
 * @return void
 */
function pg_mail_webhook_answer($http, $data)
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    http_response_code((int) $http);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');

    echo json_encode($data);

    exit;
}

/**
 * Counts a refused credential as a firewall offence.
 */
function pg_mail_webhook_offence()
{
    if (function_exists('waf_register_offence') && function_exists('waf_client_ip') && function_exists('waf_mode')) {
        waf_register_offence(waf_client_ip(), 5, (waf_mode() === 'block'));
    }
}

/**
 * Whether the calling address is over its allowance. Counted here, as the
 * firewall's own limits are off in monitor mode.
 *
 * @return bool
 */
function pg_mail_webhook_throttled()
{
    if (!function_exists('waf_rate_exceeded') || !function_exists('waf_client_ip') || !function_exists('waf_ip_subject') || !function_exists('waf_ip_is_counted')) {
        return false;
    }

    $ip = (string) waf_client_ip();

    if (($ip === '') || !waf_ip_is_counted($ip) || !waf_rate_exceeded(waf_ip_subject($ip), 'mail_webhook', PG_MAIL_WEBHOOK_PER_MINUTE, 60)) {
        return false;
    }

    if (function_exists('waf_log_event') && isset(db::$con) && db::$con) {
        waf_log_event('rate', 'rate-mail-webhook', 'rate', 5, 'mail_webhook.php', PG_MAIL_WEBHOOK_PER_MINUTE . '/1min');
    }

    if (function_exists('waf_text_log')) {
        waf_text_log('DENY', $ip, array('status' => 429, 'rule' => 'rate-mail-webhook'));
    }

    return true;
}

/**
 * Answers one report: mail_webhook.php?t=<key>&key=<secret>.
 *
 * 405 not POST; 404 no such transport or one without webhooks; 429 over the
 * allowance; 503 the bounce record not upgraded; 403 no key stored; 401 a
 * wrong key or a refused signature (offences for the firewall); 413 too
 * large; 400 not a report; else 200, replays and unknown addresses
 * included.
 *
 * @return void
 */
function pg_mail_webhook_receive()
{
    $method = isset($_SERVER['REQUEST_METHOD']) ? strtoupper((string) $_SERVER['REQUEST_METHOD']) : '';
    $key = (isset($_GET['t']) && is_string($_GET['t'])) ? $_GET['t'] : '';

    if ($method !== 'POST') {
        pg_mail_webhook_answer(405, array('status' => 'method_not_allowed'));
    }

    $support = pg_mail_webhook_support($key);

    if (!$support['webhook']) {
        pg_mail_webhook_answer(404, array('status' => 'unknown_transport'));
    }

    if (pg_mail_webhook_throttled()) {
        pg_mail_webhook_answer(429, array('status' => 'rate_limited'));
    }

    if (!pg_mail_bounces_ready()) {
        pg_mail_webhook_answer(503, array('status' => 'not_ready'));
    }

    $secrets = pg_mail_webhook_secrets($key);

    if ($secrets['webhook_key'] === '') {
        pg_mail_webhook_answer(403, array('status' => 'off'));
    }

    $presented = isset($_GET['key']) ? $_GET['key'] : null;

    if (!is_string($presented) || !hash_equals($secrets['webhook_key'], $presented)) {
        pg_mail_webhook_offence();
        pg_mail_webhook_answer(401, array('status' => 'wrong_key'));
    }

    $length = isset($_SERVER['CONTENT_LENGTH']) ? (int) $_SERVER['CONTENT_LENGTH'] : 0;

    // One byte more than the limit is read, so a body sent without a length
    // is caught at the limit rather than cut there.
    $body = ($length > PG_MAIL_WEBHOOK_MAX_BODY) ? false : @file_get_contents('php://input', false, null, 0, PG_MAIL_WEBHOOK_MAX_BODY + 1);

    if (($body === false) || (strlen($body) > PG_MAIL_WEBHOOK_MAX_BODY)) {
        pg_mail_webhook_answer(413, array('status' => 'too_large'));
    }

    $headers = pg_mail_webhook_headers($_SERVER);

    if ($support['signed'] && ($secrets['webhook_signing'] !== '')) {
        $verified = call_user_func('pg_mail_' . $key . '_webhook_verify', $body, $headers, $secrets['webhook_signing'], time());

        if ($verified === 'stale') {
            pg_mail_webhook_answer(200, array('ok' => true, 'replay' => true));
        }

        if ($verified !== 'ok') {
            pg_mail_webhook_offence();
            pg_mail_webhook_answer(401, array('status' => 'wrong_signature'));
        }
    }

    $events = call_user_func('pg_mail_' . $key . '_webhook', $body, $headers);

    if (!is_array($events)) {
        pg_mail_webhook_answer(400, array('status' => 'unreadable'));
    }

    $counts = array('recorded' => 0, 'delivered' => 0, 'ignored' => 0, 'replay' => 0);

    foreach ($events as $event) {
        $decided = pg_mail_webhook_decide($event);

        if (($decided['action'] === 'ignored') || ($decided['action'] === 'delivered')) {
            $counts[$decided['action']]++;
        } elseif (pg_mail_webhook_seen((string) $event['id'])) {
            $counts['replay']++;
        } elseif ($decided['action'] === 'confirm') {
            // SNS waits for a visit to its address before it posts reports.
            pg_curl_run('GET', (string) $event['url'], array('timeout' => 15));
            $counts['ignored']++;
        } else {
            pg_mail_bounce_record($decided['address'], $decided['kind'], $key, array('message_id' => isset($event['message_id']) ? $event['message_id'] : ''));
            $counts['recorded']++;
        }
    }

    pg_mail_webhook_answer(200, array('ok' => true) + $counts);
}
