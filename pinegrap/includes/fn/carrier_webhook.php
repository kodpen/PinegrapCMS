<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Carrier status webhook: the messages a carrier label service posts when a
 * parcel it opened moves (carrier_webhook.php?driver=<key>&key=<secret>),
 * written onto the parcel the moment they arrive instead of at the next
 * hourly run of the shipment tracking job, which stays as the safety net.
 *
 * A driver takes part with four optional functions beside its contract
 * (includes/fn/carrier_labels.php):
 *
 *   pg_label_<key>_webhook_read($body)              list of reference,
 *                                                   is_return, answer (a
 *                                                   track() answer); false
 *                                                   for a body it cannot read
 *   pg_label_<key>_webhook_verify($raw, $headers, $account)
 *                                                   whether a signed message
 *                                                   is the service's (headers
 *                                                   by lower-case name)
 *   pg_label_<key>_webhook_register($url, $account) success, id, error
 *   pg_label_<key>_webhook_remove($id, $account)    success, error
 *
 * The secret key in the address is the credential of every driver; a
 * service that signs its messages (Sendcloud) is checked by its verify
 * function as well, and one that does not (Geliver, Shippo) by the key
 * alone. The key and the service's webhook id are kept with the driver's
 * sealed secrets (connect_apps label_<key>, webhook_key and webhook_id).
 * Saving the driver switched on registers the address at the service;
 * switching it off removes it there and drops the key, so the old address
 * is refused. A driver without register shows no webhook on the card.
 *
 * A message reaches the parcel only through the tracking write-back:
 * pg_shipment_tracking_decide() and pg_shipment_tracking_apply()
 * (includes/fn/shipment_tracking.php), so an arrival is recorded once with
 * order.delivered, the e-mail and the text message, whoever learnt of it
 * first. Return labels never record an arrival.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/shipment_tracking.php');

// Messages one address may post a minute. A service's servers send one per
// parcel movement; a bulk shipping day stays well under this.
define('PG_CARRIER_WEBHOOK_PER_MINUTE', 300);

// The largest body read; a message carries one shipment.
define('PG_CARRIER_WEBHOOK_MAX_BODY', 1048576);

// Unknown shipments remembered, so each is logged once.
define('PG_CARRIER_WEBHOOK_UNKNOWN_KEPT', 100);

/**
 * Whether the key presented in the address is the stored one, compared in
 * constant time. No key stored refuses every message.
 *
 * @param string $stored
 * @param mixed  $presented
 * @return bool
 */
function pg_carrier_webhook_key_matches($stored, $presented)
{
    return is_string($stored) && ($stored !== '') && is_string($presented) && hash_equals($stored, $presented);
}

/**
 * @return string 32 hex characters
 */
function pg_carrier_webhook_new_key()
{
    return bin2hex(random_bytes(16));
}

/**
 * The address a driver's service posts its messages to.
 *
 * @param string $key   the driver
 * @param string $token the secret key
 * @return string
 */
function pg_carrier_webhook_url($key, $token)
{
    // init.php defines the site's address; without it (a unit test run) the
    // address is relative.
    $base = defined('URL_SCHEME') ? URL_SCHEME . HOSTNAME_SETTING . PATH . SOFTWARE_DIRECTORY : '';

    return $base . '/carrier_webhook.php?driver=' . rawurlencode((string) $key) . '&key=' . rawurlencode((string) $token);
}

/**
 * A service's words with the secret key masked: a refusal may echo the
 * address it was given. Pure.
 *
 * @param string $text
 * @param string $token
 * @return string
 */
function pg_carrier_webhook_mask($text, $token)
{
    return ((string) $token === '') ? (string) $text : str_replace((string) $token, '****', (string) $text);
}

/**
 * The name of a driver's webhook function, '' when it has none.
 *
 * @param string $key
 * @param string $name read, register or remove
 * @return string
 */
function pg_carrier_webhook_function($key, $name)
{
    if (!pg_label_key_valid($key) || (pg_label_driver($key) === null) || !pg_label_load($key)) {
        return '';
    }

    $function = 'pg_label_' . $key . '_webhook_' . $name;

    return function_exists($function) ? $function : '';
}

/**
 * The request headers by lower-case, dash-separated name, from $_SERVER.
 * Pure.
 *
 * @param array $server
 * @return array
 */
function pg_carrier_webhook_headers($server)
{
    $headers = array();

    foreach ((array) $server as $name => $value) {
        if (!is_string($value)) {
            continue;
        }

        if (strncmp((string) $name, 'HTTP_', 5) === 0) {
            $headers[strtolower(str_replace('_', '-', substr($name, 5)))] = $value;
        } elseif (in_array($name, array('CONTENT_TYPE', 'CONTENT_LENGTH'), true)) {
            $headers[strtolower(str_replace('_', '-', $name))] = $value;
        }
    }

    return $headers;
}

/**
 * Whether a message passes the driver's signature check; true for a
 * driver that has none, as the key in the address already matched.
 *
 * @param string $driver
 * @param string $raw     the body as it came
 * @param array  $headers pg_carrier_webhook_headers()
 * @param array  $account pg_label_account()
 * @return bool
 */
function pg_carrier_webhook_verified($driver, $raw, $headers, $account)
{
    $verify = pg_carrier_webhook_function($driver, 'verify');

    if ($verify === '') {
        return true;
    }

    try {
        return call_user_func($verify, (string) $raw, $headers, $account) === true;
    } catch (\Throwable $exception) {
        return false;
    }
}

/**
 * Counts a refused credential as a firewall offence.
 */
function pg_carrier_webhook_offence()
{
    if (function_exists('waf_register_offence') && function_exists('waf_client_ip') && function_exists('waf_mode')) {
        waf_register_offence(waf_client_ip(), 5, (waf_mode() === 'block'));
    }
}

/**
 * The label a service's shipment id belongs to, with its parcel row (number
 * null when the row is gone, or for a return label, which has none).
 *
 * @param string $driver
 * @param string $reference
 * @return array|null
 */
function pg_carrier_webhook_label($driver, $reference)
{
    return db_item(
        "SELECT
            shipment_labels.id,
            shipment_labels.order_id,
            orders.order_number,
            orders.status AS order_status,
            shipment_labels.ship_to_id,
            shipment_labels.driver,
            shipment_labels.reference,
            shipment_labels.state,
            shipment_labels.is_return,
            shipping_tracking_numbers.number,
            shipping_tracking_numbers.carrier,
            shipping_tracking_numbers.tracking_url,
            shipping_tracking_numbers.status,
            shipping_tracking_numbers.status_at,
            shipping_tracking_numbers.delivered_at
        FROM shipment_labels
        INNER JOIN orders ON orders.id = shipment_labels.order_id
        LEFT JOIN shipping_tracking_numbers ON
            (shipment_labels.is_return = '0')
            AND (shipping_tracking_numbers.ship_to_id = shipment_labels.ship_to_id)
            AND (shipping_tracking_numbers.number = shipment_labels.number)
        WHERE
            (shipment_labels.driver = '" . e((string) $driver) . "')
            AND (shipment_labels.reference = '" . e((string) $reference) . "')
        ORDER BY (shipment_labels.state = 'active') DESC, shipment_labels.id DESC
        LIMIT 1");
}

/**
 * What one message changes on its parcel. Pure.
 *
 * Unknown: no label of this site has the shipment. Ignored: a return label
 * (its arrival is the goods coming back, never order.delivered), a label no
 * longer active, a parcel row gone, an order cancelled, a parcel that has
 * arrived or gone back (a repeated delivery message records nothing), an
 * answer without a status, a status older than the one the row holds (a
 * message that came out of order), or nothing new. Apply: the tracking
 * decision for pg_shipment_tracking_apply(). An arrival is never too old.
 *
 * @param array|null $row   pg_carrier_webhook_label()
 * @param array      $event one of the driver's webhook_read() list
 * @param int        $now
 * @return array action (unknown, ignored, apply), reason, decision
 */
function pg_carrier_webhook_decide($row, $event, $now)
{
    $out = array('action' => 'ignored', 'reason' => '', 'decision' => null);

    if (!is_array($row)) {
        $out['action'] = 'unknown';

        return $out;
    }

    if (!empty($row['is_return']) || !empty($event['is_return'])) {
        $out['reason'] = 'return';

        return $out;
    }

    if (($row['state'] !== 'active') || ($row['number'] === null) || in_array((string) $row['order_status'], array('incomplete', 'cancelled'), true)) {
        $out['reason'] = 'inactive';

        return $out;
    }

    if (((int) $row['delivered_at'] > 0) || in_array((string) $row['status'], array('delivered', 'returned'), true)) {
        $out['reason'] = 'closed';

        return $out;
    }

    $decision = pg_shipment_tracking_decide($row, isset($event['answer']) ? $event['answer'] : null, $now);

    if (!$decision['ok']) {
        $out['reason'] = 'failed';
    } elseif (!$decision['delivered'] && ($decision['at'] < (int) $row['status_at'])) {
        $out['reason'] = 'stale';
    } elseif (!$decision['write'] && !$decision['delivered']) {
        $out['reason'] = 'same';
    } else {
        $out['action'] = 'apply';
        $out['decision'] = $decision;
    }

    return $out;
}

/**
 * Whether an unknown shipment is logged: once, while it is among the last
 * PG_CARRIER_WEBHOOK_UNKNOWN_KEPT seen. Pure.
 *
 * @param array  $seen list of driver:reference
 * @param string $driver
 * @param string $reference
 * @return array log (bool), seen (the list to keep)
 */
function pg_carrier_webhook_unknown_note($seen, $driver, $reference)
{
    $seen = is_array($seen) ? array_values(array_filter($seen, 'is_string')) : array();
    $name = $driver . ':' . $reference;

    if (in_array($name, $seen, true)) {
        return array('log' => false, 'seen' => $seen);
    }

    $seen[] = $name;

    return array('log' => true, 'seen' => array_slice($seen, -PG_CARRIER_WEBHOOK_UNKNOWN_KEPT));
}

/**
 * @return string
 */
function pg_carrier_webhook_unknown_path()
{
    return PG_FUNCTIONS_DIR . '/data/temp/carrier_webhook.json';
}

/**
 * Logs a message about a shipment no label has, once.
 *
 * @param string $driver
 * @param string $reference
 */
function pg_carrier_webhook_unknown($driver, $reference)
{
    $path = pg_carrier_webhook_unknown_path();
    $seen = is_file($path) ? json_decode((string) @file_get_contents($path), true) : array();
    $note = pg_carrier_webhook_unknown_note($seen, $driver, $reference);

    if (!$note['log']) {
        return;
    }

    @file_put_contents($path, json_encode($note['seen']), LOCK_EX);

    $definition = pg_label_driver($driver);

    log_activity('Shipment Tracking: ' . $definition['label'] . ' sent a status of shipment ' . $reference . ', which no label of this site has.', 'SYSTEM');
}

/**
 * Whether the calling address is over its allowance. Counted here, as the
 * firewall's own limits are off in monitor mode.
 *
 * @return bool
 */
function pg_carrier_webhook_throttled()
{
    if (!function_exists('waf_rate_exceeded') || !function_exists('waf_client_ip') || !function_exists('waf_ip_subject') || !function_exists('waf_ip_is_counted')) {
        return false;
    }

    $ip = (string) waf_client_ip();

    if (($ip === '') || !waf_ip_is_counted($ip)) {
        return false;
    }

    $exceeded = waf_rate_exceeded(waf_ip_subject($ip), 'carrier_webhook', PG_CARRIER_WEBHOOK_PER_MINUTE, 60);

    if (function_exists('waf_rate_sweep') && (mt_rand(1, 50) === 1)) {
        waf_rate_sweep();
    }

    if (!$exceeded) {
        return false;
    }

    if (function_exists('waf_log_event') && isset(db::$con) && db::$con) {
        waf_log_event('rate', 'rate-carrier-webhook', 'rate', 5, 'carrier_webhook.php', PG_CARRIER_WEBHOOK_PER_MINUTE . '/1min');
    }

    if (function_exists('waf_text_log')) {
        waf_text_log('DENY', $ip, array('status' => 429, 'rule' => 'rate-carrier-webhook'));
    }

    return true;
}

/**
 * Sends the answer and ends the request.
 *
 * @param int   $http
 * @param array $data
 * @return void
 */
function pg_carrier_webhook_answer($http, $data)
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
 * Answers one message: carrier_webhook.php?driver=<key>&key=<secret>.
 *
 * 405 not POST; 404 no such driver or one without webhooks; 429 over the
 * allowance; 503 the database not upgraded; 403 e-commerce off, the driver
 * off or no key stored; 401 a wrong key (an offence for the firewall); 413
 * too large; 401 a signature the driver refuses (an offence too); 400 not
 * a message; else 200, unknown shipments included, so
 * the service does not send them again.
 *
 * @return void
 */
function pg_carrier_webhook_receive()
{
    $method = isset($_SERVER['REQUEST_METHOD']) ? strtoupper((string) $_SERVER['REQUEST_METHOD']) : '';
    $driver = (isset($_GET['driver']) && is_string($_GET['driver'])) ? $_GET['driver'] : '';

    if ($method !== 'POST') {
        pg_carrier_webhook_answer(405, array('status' => 'method_not_allowed'));
    }

    $read = pg_carrier_webhook_function($driver, 'read');

    if ($read === '') {
        pg_carrier_webhook_answer(404, array('status' => 'unknown_driver'));
    }

    if (pg_carrier_webhook_throttled()) {
        pg_carrier_webhook_answer(429, array('status' => 'rate_limited'));
    }

    if (!pg_shipment_labels_ready() || !pg_shipment_rows_ready()) {
        pg_carrier_webhook_answer(503, array('status' => 'not_ready'));
    }

    $settings = pg_label_settings($driver);
    $stored = isset($settings['secrets']['webhook_key']) ? $settings['secrets']['webhook_key'] : '';

    if (!defined('ECOMMERCE') || (ECOMMERCE !== true) || !$settings['enabled'] || ($stored === '')) {
        pg_carrier_webhook_answer(403, array('status' => 'off'));
    }

    if (!pg_carrier_webhook_key_matches($stored, isset($_GET['key']) ? $_GET['key'] : null)) {
        pg_carrier_webhook_offence();
        pg_carrier_webhook_answer(401, array('status' => 'wrong_key'));
    }

    $length = isset($_SERVER['CONTENT_LENGTH']) ? (int) $_SERVER['CONTENT_LENGTH'] : 0;

    // One byte more than the limit is read, so a body sent without a length
    // is caught at the limit rather than cut there.
    $body = ($length > PG_CARRIER_WEBHOOK_MAX_BODY) ? false : @file_get_contents('php://input', false, null, 0, PG_CARRIER_WEBHOOK_MAX_BODY + 1);

    if (($body === false) || (strlen($body) > PG_CARRIER_WEBHOOK_MAX_BODY)) {
        pg_carrier_webhook_answer(413, array('status' => 'too_large'));
    }

    if (!pg_carrier_webhook_verified($driver, $body, pg_carrier_webhook_headers($_SERVER), pg_label_account(pg_label_fields($driver), $settings))) {
        pg_carrier_webhook_offence();
        pg_carrier_webhook_answer(401, array('status' => 'wrong_signature'));
    }

    try {
        $events = call_user_func($read, (string) $body);
    } catch (\Throwable $exception) {
        $events = false;
    }

    if (!is_array($events)) {
        pg_carrier_webhook_answer(400, array('status' => 'unreadable'));
    }

    $counts = array('applied' => 0, 'ignored' => 0, 'unknown' => 0);
    $now = time();

    foreach ($events as $event) {
        $reference = (is_array($event) && isset($event['reference'])) ? (string) $event['reference'] : '';

        if ($reference === '') {
            $counts['ignored']++;
            continue;
        }

        $event['answer'] = pg_label_answer('track', isset($event['answer']) ? $event['answer'] : null);
        $row = pg_carrier_webhook_label($driver, $reference);
        $decided = pg_carrier_webhook_decide($row, $event, $now);

        if ($decided['action'] === 'unknown') {
            pg_carrier_webhook_unknown($driver, $reference);
            $counts['unknown']++;
        } elseif (($decided['action'] === 'apply') && pg_shipment_tracking_apply($row, $decided['decision'])) {
            $counts['applied']++;
        } else {
            $counts['ignored']++;
        }
    }

    pg_carrier_webhook_answer(200, array('ok' => true) + $counts);
}

/**
 * Changes the webhook values among a driver's sealed secrets: a value sets,
 * null removes.
 *
 * @param string $key
 * @param array  $changes name => text|null
 * @return bool
 */
function pg_carrier_webhook_secrets_put($key, $changes)
{
    if (!pg_connect_schema_ready() || !pg_connect_can_seal()) {
        return false;
    }

    $provider = pg_label_settings_key($key);
    $row = db_item("SELECT secret FROM connect_apps WHERE provider = '" . e($provider) . "'");

    if (!is_array($row)) {
        return false;
    }

    $secrets = pg_connect_unseal($row['secret']);

    foreach ($changes as $name => $value) {
        if ($value === null) {
            unset($secrets[$name]);
        } else {
            $secrets[$name] = (string) $value;
        }
    }

    db("UPDATE connect_apps SET secret = '" . e(pg_connect_seal($secrets)) . "' WHERE provider = '" . e($provider) . "'");

    return true;
}

/**
 * Registers the driver's webhook at its service after a save of the Shipping
 * card that leaves it on, or removes it when the save switched it off. A
 * webhook stays registered across saves; newly typed keys (another account,
 * perhaps) register it again. A failed registration keeps the key, so the
 * address the card shows can be added in the service's panel by hand.
 *
 * @param string   $key
 * @param array    $decision pg_label_settings_decide()
 * @param liveform $liveform
 */
function pg_carrier_webhook_sync($key, $decision, $liveform)
{
    $register = pg_carrier_webhook_function($key, 'register');
    $remove = pg_carrier_webhook_function($key, 'remove');

    if (($register === '') || !pg_connect_can_seal()) {
        return;
    }

    $settings = pg_label_settings($key);

    if (!$settings['exists'] || !$settings['readable']) {
        return;
    }

    $definition = pg_label_driver($key);
    $account = pg_label_account(pg_label_fields($key), $settings);
    $id = isset($settings['secrets']['webhook_id']) ? $settings['secrets']['webhook_id'] : '';
    $token = isset($settings['secrets']['webhook_key']) ? $settings['secrets']['webhook_key'] : '';

    $call = function ($function, $first) use ($account) {
        try {
            $answer = call_user_func($function, $first, $account);
        } catch (\Throwable $exception) {
            $answer = array('success' => false, 'error' => $exception->getMessage());
        }

        return is_array($answer) ? $answer + array('success' => false, 'id' => '', 'error' => '') : array('success' => false, 'id' => '', 'error' => '');
    };

    if (empty($decision['enabled'])) {
        if (($id === '') && ($token === '')) {
            return;
        }

        if (($id !== '') && ($remove !== '')) {
            $answer = $call($remove, $id);

            if (!$answer['success']) {
                $liveform->add_notice(lang(array(
                    'string' => '{var:1} could not remove the status webhook: {var:2}. Remove it in its panel.',
                    'vars'   => array(h($definition['label']), h(pg_carrier_webhook_mask($answer['error'], $token))))));
            }
        }

        // The key goes too, so the old address is refused from now on.
        pg_carrier_webhook_secrets_put($key, array('webhook_id' => null, 'webhook_key' => null));

        return;
    }

    if (!pg_label_ready($key) || (($id !== '') && empty($decision['secrets']))) {
        return;
    }

    if (($id !== '') && ($remove !== '')) {
        $call($remove, $id);
    }

    if ($token === '') {
        $token = pg_carrier_webhook_new_key();
    }

    $answer = $call($register, pg_carrier_webhook_url($key, $token));
    $registered = $answer['success'] && is_string($answer['id']) && ($answer['id'] !== '');

    pg_carrier_webhook_secrets_put($key, array('webhook_id' => $registered ? $answer['id'] : null, 'webhook_key' => $token));

    if ($registered) {
        $liveform->add_notice(lang(array(
            'string' => '{var:1} will send parcel status changes to this site as they happen.',
            'vars'   => array(h($definition['label'])))));
    } else {
        $liveform->add_notice(lang(array(
            'string' => '{var:1} did not register the status webhook: {var:2}. Statuses are still checked every hour; the card shows the address to add in its panel.',
            'vars'   => array(h($definition['label']), h(pg_carrier_webhook_mask($answer['error'], $token))))));
    }
}

/**
 * The webhook's line on the driver's part of the Shipping card: nothing for
 * a driver without webhooks; registered; the address to add by hand after a
 * failed registration; or what a save does.
 *
 * @param string $key
 * @param array  $settings pg_label_settings()
 * @return string
 */
function pg_carrier_webhook_card_row($key, $settings)
{
    if (pg_carrier_webhook_function($key, 'register') === '') {
        return '';
    }

    $definition = pg_label_driver($key);
    $id = isset($settings['secrets']['webhook_id']) ? $settings['secrets']['webhook_id'] : '';
    $token = isset($settings['secrets']['webhook_key']) ? $settings['secrets']['webhook_key'] : '';

    if ($settings['enabled'] && ($id !== '')) {
        $text = lang(array('string' => '{var:1} sends parcel status changes to this site as they happen.', 'vars' => array(h($definition['label']))));
    } elseif ($settings['enabled'] && ($token !== '')) {
        $input = pg_label_input_name($key, 'webhook_url');

        return '
                                                                                        <div class="col-12">
                                                                                            <label class="form-label" for="' . h($input) . '">' . lang('Status Webhook Address') . '</label>
                                                                                            <input class="form-control" type="text" id="' . h($input) . '" value="' . h(pg_carrier_webhook_url($key, $token)) . '" readonly="readonly"/>
                                                                                            <div class="form-text">' . lang(array('string' => 'The webhook could not be registered. Add this address in the {var:1} panel as its parcel status webhook, or save again to retry. Keep it private.', 'vars' => array(h($definition['label'])))) . '</div>
                                                                                        </div>';
    } else {
        $text = lang(array('string' => 'Saving with {var:1} switched on registers a webhook there, so parcel statuses arrive as they change.', 'vars' => array(h($definition['label']))));
    }

    return '
                                                                                        <div class="col-12 form-text">' . $text . '</div>';
}
