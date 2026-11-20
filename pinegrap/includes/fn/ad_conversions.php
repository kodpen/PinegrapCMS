<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Ad conversions: purchases sent from the server to the ad services the shop
 * connects (Meta Conversions API, GA4 Measurement Protocol, TikTok Events
 * API). Kept in ad_conversion_events (2026.11.0, 11.0.254), which is the send
 * queue, the event log and the place of the buyer's ids until the event is
 * sent: one row per event and service, unique on (service, event_id).
 *
 * The flow of a purchase:
 * - The payment step (pg_ad_conversion_payment_step(), the visitor's own
 *   request in submit_order.php): for each connected service that takes the
 *   purchase, a buyer who consented gets a status 0 row with that service's
 *   ids (cookies, address, browser name); without consent no id is kept, and
 *   a row kept earlier loses its ids (consent taken back).
 * - Completion (pg_ad_conversion_order_completed(), from pg_order_complete()
 *   after its lock, so a second completion never reaches it): the status 0
 *   rows become due (1) with what the order says (amount, currency, lines);
 *   no cookie is read, because a payment service's own message completes an
 *   order without the visitor.
 * - The general job (pg_ad_conversion_dispatch()) builds each due row's event
 *   at send time (pg_ad_conversion_event(): e-mail and phone normalised and
 *   hashed then, never kept in a row) and hands it to the service's sender,
 *   pg_ad_conversion_send_<service>(), when one exists. Sent (2) or refused
 *   or given up (3) rows drop their ids. Rows over 30 days are swept.
 *
 * Consent is read by pg_ad_conversion_consent_by() from three inputs: the
 * cookie notice on, the stored answer and the service's "Send personal data
 * while the cookie notice is off" box; never pg_consent_allows(), which
 * answers yes while the notice is off.
 *
 * A service's settings live in config_text row ad_conversions_<service>, a
 * JSON object: connected, events (the ticked event names), person_notice_off
 * (the box), without_person (the events sent without the person when there is
 * no consent).
 *
 * No network call is made in a request: an event is a queue row. Money stays
 * in minor units until the event is built.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// Rows older than this are swept, whatever their status.
define('PG_AD_CONVERSION_KEEP_DAYS', 30);

// The sweep deletes in batches of this many rows, at most this many batches.
define('PG_AD_CONVERSION_SWEEP_ROWS', 500);
define('PG_AD_CONVERSION_SWEEP_BATCHES', 20);

// The most due rows one pass of the general job reads.
define('PG_AD_CONVERSION_PASS_ROWS', 50);

/**
 * The services an event can go to, in the order they are worked.
 *
 * @return string[]
 */
function pg_ad_conversion_services()
{
    return array('meta_capi', 'ga4_mp', 'tiktok_events');
}

/**
 * Whether the table exists (the 11.0.254 step has run).
 *
 * @return bool
 */
function pg_ad_conversions_ready()
{
    return pg_schema_has('ad_conversion_events', 'event_id');
}

/**
 * A service's settings from its config_text value, every key present. Pure.
 *
 * @param mixed $raw the stored JSON
 * @return array connected (bool), events (string[]), person_notice_off
 *               (bool), without_person (string[])
 */
function pg_ad_conversion_settings_parse($raw)
{
    $data = is_string($raw) ? json_decode($raw, true) : null;
    $data = is_array($data) ? $data : array();

    $list = function ($value) {
        return is_array($value) ? array_values(array_filter($value, 'is_string')) : array();
    };

    return array(
        'connected' => !empty($data['connected']),
        'events' => array_key_exists('events', $data) ? $list($data['events']) : array('purchase'),
        'person_notice_off' => !empty($data['person_notice_off']),
        'without_person' => $list($data['without_person'] ?? array()),
    );
}

/**
 * Each service's settings, keyed by service.
 *
 * @return array service => pg_ad_conversion_settings_parse()
 */
function pg_ad_conversion_settings_all()
{
    $settings = array();

    foreach (pg_ad_conversion_services() as $service) {
        $settings[$service] = pg_ad_conversion_settings_parse(pg_config_text('ad_conversions_' . $service));
    }

    return $settings;
}

/**
 * Whether personal data may go to a service for this visitor. With the
 * cookie notice on only a stored yes to marketing counts; with it off only
 * the service's box, ticked at the shop's own risk, and the stored answer
 * is not read. Pure.
 *
 * @param bool  $notice_on pg_consent_enabled()
 * @param array $choices   category => bool (pg_consent_choices())
 * @param bool  $box       the service's "Send personal data while the
 *                         cookie notice is off"
 * @return bool
 */
function pg_ad_conversion_consent_by($notice_on, array $choices, $box)
{
    if (!$notice_on) {
        return (bool) $box;
    }

    return isset($choices['marketing']) && ($choices['marketing'] === true);
}

/**
 * Whether an order of this type is ever sent: never a marketplace order (the
 * marketplace's buyer, not the shop's), never a counter sale (no setting for
 * it yet). Pure.
 *
 * @param mixed $type orders.type; '' or null on a site without the column
 * @return bool
 */
function pg_ad_conversion_order_sendable($type)
{
    return !in_array((string) $type, array('marketplace', 'local'), true);
}

/**
 * A cookie's value when it is a plain printable string of a sane length,
 * else ''. Pure.
 *
 * @param mixed $value
 * @return string
 */
function pg_ad_conversion_cookie_value($value)
{
    if (!is_string($value) || ($value === '') || (strlen($value) > 255)) {
        return '';
    }

    return preg_match('/^[\x21-\x7e]+$/D', $value) ? $value : '';
}

/**
 * The client id GA4 takes, from the _ga cookie (GA1.1.<random>.<time> gives
 * <random>.<time>), '' when it cannot be read. Pure.
 *
 * @param mixed $cookie
 * @return string
 */
function pg_ad_conversion_ga_client_id($cookie)
{
    $value = pg_ad_conversion_cookie_value($cookie);

    if (!preg_match('/^GA\d+\.\d+\.(\d+\.\d+)$/D', $value, $match)) {
        return '';
    }

    return $match[1];
}

/**
 * The ids a service is given for a consenting buyer: its own browser ids
 * from the cookies, the address and the browser's name. An id that cannot be
 * read is left out. Pure.
 *
 * @param string $service
 * @param array  $cookies    $_COOKIE
 * @param string $ip
 * @param string $user_agent
 * @return array
 */
function pg_ad_conversion_ids_for($service, array $cookies, $ip, $user_agent)
{
    $ids = array();

    if ($service === 'meta_capi') {
        $ids['fbp'] = pg_ad_conversion_cookie_value($cookies['_fbp'] ?? '');
        $ids['fbc'] = pg_ad_conversion_cookie_value($cookies['_fbc'] ?? '');
    } elseif ($service === 'ga4_mp') {
        $ids['client_id'] = pg_ad_conversion_ga_client_id($cookies['_ga'] ?? '');
    } elseif ($service === 'tiktok_events') {
        $ids['ttp'] = pg_ad_conversion_cookie_value($cookies['_ttp'] ?? '');
    }

    $ids = array_filter($ids, 'strlen');

    $ids['ip'] = substr((string) $ip, 0, 45);
    $ids['user_agent'] = substr((string) $user_agent, 0, 500);

    return $ids;
}

/**
 * What the payment step does for each connected service that takes the
 * purchase: keep (write or renew the status 0 row with the ids) or withdraw
 * (no consent: a row kept earlier loses its ids). Pure.
 *
 * @param bool  $notice_on
 * @param array $choices
 * @param array $settings service => pg_ad_conversion_settings_parse()
 * @return array service => keep | withdraw
 */
function pg_ad_conversion_payment_step_plan($notice_on, array $choices, array $settings)
{
    $plan = array();

    foreach ($settings as $service => $one) {
        if (empty($one['connected']) || !in_array('purchase', $one['events'], true)) {
            continue;
        }

        $plan[$service] = pg_ad_conversion_consent_by($notice_on, $choices, !empty($one['person_notice_off'])) ? 'keep' : 'withdraw';
    }

    return $plan;
}

/**
 * What completion does for each service, from the order's purchase rows as
 * the payment step left them; reads nothing about the visitor. Pure.
 * - a status 0 row with the person: due
 * - a status 0 row without the person (consent taken back): due without the
 *   person when the service sends the purchase without the person, else
 *   given up
 * - no row: a row without the person when the service sends the purchase
 *   without the person
 * - a row already due, sent or given up: nothing (a second completion)
 *
 * @param array $rows     service => the purchase row (status, person)
 * @param array $settings service => pg_ad_conversion_settings_parse()
 * @return array service => due | give_up | insert
 */
function pg_ad_conversion_completion_plan(array $rows, array $settings)
{
    $plan = array();

    foreach ($settings as $service => $one) {
        if (empty($one['connected']) || !in_array('purchase', $one['events'], true)) {
            continue;
        }

        $without_person = in_array('purchase', $one['without_person'], true);

        if (!isset($rows[$service])) {
            if ($without_person) {
                $plan[$service] = 'insert';
            }

            continue;
        }

        if ((int) $rows[$service]['status'] !== 0) {
            continue;
        }

        $plan[$service] = (((int) $rows[$service]['person'] === 1) || $without_person) ? 'due' : 'give_up';
    }

    return $plan;
}

/**
 * The values a row takes when consent is taken back. Pure.
 *
 * @return array
 */
function pg_ad_conversion_withdrawn_values()
{
    return array('ids' => null, 'person' => 0);
}

/**
 * Whether a cookie notice answer refused marketing. Pure.
 *
 * @param array[]|null $changes pg_consent_answer_changes()
 * @return bool
 */
function pg_ad_conversion_marketing_refused($changes)
{
    if (!is_array($changes)) {
        return false;
    }

    foreach ($changes as $change) {
        if ((($change['purpose'] ?? '') === 'marketing') && (($change['status'] ?? '') === 'refused')) {
            return true;
        }
    }

    return false;
}

/**
 * The statement that takes the ids and the person off an order's rows not
 * sent yet; one placeholder, the order id.
 *
 * @return string
 */
function pg_ad_conversion_withdraw_sql()
{
    return "UPDATE ad_conversion_events SET ids = NULL, person = 0 WHERE order_id = ? AND status IN (0, 1)";
}

/**
 * The statement that writes a new row, or leaves the one already there:
 * service, event, order_id, event_id, ids, detail, person, status,
 * next_attempt_at, created_at.
 *
 * @return string
 */
function pg_ad_conversion_insert_sql()
{
    return "INSERT IGNORE INTO ad_conversion_events
        (service, event, order_id, event_id, ids, detail, person, status, attempts, next_attempt_at, last_status_code, last_error, created_at, sent_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, ?, 0, '', ?, 0)";
}

/**
 * The statement that deletes one batch of rows past the keeping time. Pure.
 *
 * @param int $now
 * @param int $limit
 * @return string
 */
function pg_ad_conversion_sweep_sql($now, $limit)
{
    return "DELETE FROM ad_conversion_events WHERE created_at < " . ((int) $now - PG_AD_CONVERSION_KEEP_DAYS * 86400) . " LIMIT " . (int) $limit;
}

/**
 * A purchase's event id: the order's reference, which the receipt page can
 * hand a browser pixel so both send the same id. Pure.
 *
 * @param string $reference_code
 * @return string
 */
function pg_ad_conversion_purchase_event_id($reference_code)
{
    return substr((string) $reference_code, 0, 64);
}

/**
 * What a purchase says that is not about a person, kept in the row's detail
 * at completion. Amounts in minor units. Pure.
 *
 * @param int    $total        the order's total
 * @param string $currency
 * @param string $order_number
 * @param int    $time         the moment of completion
 * @param array  $lines        product_name (the SKU), quantity, price
 * @return array
 */
function pg_ad_conversion_purchase_detail($total, $currency, $order_number, $time, array $lines)
{
    $contents = array();

    foreach ($lines as $line) {
        $contents[] = array(
            'id' => (string) ($line['product_name'] ?? ''),
            'quantity' => (int) ($line['quantity'] ?? 0),
            'price' => (int) round((float) ($line['price'] ?? 0)),
        );
    }

    return array(
        'value' => (int) round((float) $total),
        'currency' => (string) $currency,
        'order_number' => (string) $order_number,
        'time' => (int) $time,
        'contents' => $contents,
    );
}

/**
 * An amount in minor units as the decimal a service's body takes. Pure.
 *
 * @param int $minor
 * @return string
 */
function pg_ad_conversion_decimal($minor)
{
    $minor = (int) $minor;
    $sign = ($minor < 0) ? '-' : '';
    $minor = abs($minor);

    return $sign . intdiv($minor, 100) . '.' . str_pad((string) ($minor % 100), 2, '0', STR_PAD_LEFT);
}

/**
 * An e-mail address trimmed, lower-cased and hashed with SHA-256; '' for a
 * value that is not an address. Pure.
 *
 * @param mixed $email
 * @return string
 */
function pg_ad_conversion_hash_email($email)
{
    $email = strtolower(trim((string) $email));

    return (strpos($email, '@') > 0) && (strpos($email, ' ') === false) ? hash('sha256', $email) : '';
}

/**
 * A phone number as its digits with the country code (a leading + or 00
 * dropped), hashed with SHA-256; '' for fewer than 7 digits. Pure.
 *
 * @param mixed $phone
 * @return string
 */
function pg_ad_conversion_hash_phone($phone)
{
    $digits = preg_replace('/\D+/', '', (string) $phone);

    if (strpos($digits, '00') === 0) {
        $digits = substr($digits, 2);
    }

    return (strlen($digits) >= 7) ? hash('sha256', $digits) : '';
}

/**
 * The event a sender turns into its service's body, built at send time from
 * the row and the order: amounts as decimals, the e-mail and phone hashed,
 * the ids only when the row carries the person. Pure.
 *
 * @param array $row   event, event_id, person, ids, detail
 * @param array $order billing_email_address, billing_phone_number
 * @return array event, event_id, time, value, currency, order_number,
 *               contents (id, quantity, price), user
 */
function pg_ad_conversion_event(array $row, array $order)
{
    $detail = json_decode((string) ($row['detail'] ?? ''), true);
    $detail = is_array($detail) ? $detail : array();

    $contents = array();

    foreach ((array) ($detail['contents'] ?? array()) as $line) {
        $contents[] = array(
            'id' => (string) ($line['id'] ?? ''),
            'quantity' => (int) ($line['quantity'] ?? 0),
            'price' => pg_ad_conversion_decimal($line['price'] ?? 0),
        );
    }

    $user = array();

    if ((int) ($row['person'] ?? 0) === 1) {
        $ids = json_decode((string) ($row['ids'] ?? ''), true);

        $user = array_filter(array(
            'email_sha256' => pg_ad_conversion_hash_email($order['billing_email_address'] ?? ''),
            'phone_sha256' => pg_ad_conversion_hash_phone($order['billing_phone_number'] ?? ''),
        ), 'strlen') + (is_array($ids) ? $ids : array());
    }

    return array(
        'event' => (string) ($row['event'] ?? ''),
        'event_id' => (string) ($row['event_id'] ?? ''),
        'time' => (int) ($detail['time'] ?? 0),
        'value' => pg_ad_conversion_decimal($detail['value'] ?? 0),
        'currency' => (string) ($detail['currency'] ?? ''),
        'order_number' => (string) ($detail['order_number'] ?? ''),
        'contents' => $contents,
        'user' => $user,
    );
}

/**
 * The columns a send's outcome writes: sent (2) drops the ids; a refusal
 * the service meant, or the last of api_webhook_max_attempts(), gives up (3)
 * and drops them; any other failure is tried again after
 * api_webhook_backoff(). Pure.
 *
 * @param array $row     attempts
 * @param array $outcome ok, status, error (as api_http_post() answers)
 * @param int   $now
 * @return array column => value
 */
function pg_ad_conversion_after_attempt(array $row, array $outcome, $now)
{
    $attempts = (int) ($row['attempts'] ?? 0) + 1;
    $status = (int) ($outcome['status'] ?? 0);
    $values = array(
        'attempts' => min(255, $attempts),
        'last_status_code' => max(0, min(65535, $status)),
        'last_error' => substr((string) ($outcome['error'] ?? ''), 0, 500),
    );

    if (!empty($outcome['ok'])) {
        return $values + array('status' => 2, 'ids' => null, 'sent_at' => (int) $now, 'last_error' => '');
    }

    $wait = (($attempts < api_webhook_max_attempts()) && api_http_should_retry(array('ok' => false, 'status' => $status)))
        ? api_webhook_backoff($attempts)
        : 0;

    if ($wait <= 0) {
        return $values + array('status' => 3, 'ids' => null);
    }

    return $values + array('status' => 1, 'next_attempt_at' => (int) $now + $wait);
}

/**
 * The order's type and reference, null for no order.
 *
 * @param int $order_id
 * @return array|null
 */
function pg_ad_conversion_order_row($order_id)
{
    $result = db_q("SELECT reference_code" . (pg_schema_has('orders', 'type') ? ", type" : "") . ", billing_email_address, billing_phone_number
        FROM orders WHERE id = ?", array((int) $order_id));

    $row = $result ? mysqli_fetch_assoc($result) : null;

    return is_array($row) ? $row : null;
}

/**
 * The payment step, in the visitor's own request: writes or renews the
 * order's status 0 purchase rows with each service's ids for a consenting
 * buyer, and takes the ids off them without consent. Nothing is sent.
 *
 * @param int $order_id
 * @return void
 */
function pg_ad_conversion_payment_step($order_id)
{
    $order_id = (int) $order_id;

    if (($order_id <= 0) || !pg_ad_conversions_ready()) {
        return;
    }

    $plan = pg_ad_conversion_payment_step_plan(pg_consent_enabled(), pg_consent_choices(), pg_ad_conversion_settings_all());

    if ($plan === array()) {
        return;
    }

    $order = pg_ad_conversion_order_row($order_id);

    if (($order === null) || !pg_ad_conversion_order_sendable($order['type'] ?? '')) {
        return;
    }

    $event_id = pg_ad_conversion_purchase_event_id($order['reference_code']);
    $ip = function_exists('waf_client_ip') ? waf_client_ip() : (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    $user_agent = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
    $now = time();

    foreach ($plan as $service => $action) {
        if ($action === 'withdraw') {
            $withdrawn = pg_ad_conversion_withdrawn_values();

            db_q("UPDATE ad_conversion_events SET ids = ?, person = ? WHERE service = ? AND event_id = ? AND status IN (0, 1)",
                array($withdrawn['ids'], $withdrawn['person'], $service, $event_id));

            continue;
        }

        $ids = json_encode(pg_ad_conversion_ids_for($service, $_COOKIE, $ip, $user_agent) + pg_ad_click_ids_held_for($service));

        db_q(pg_ad_conversion_insert_sql(), array($service, 'purchase', $order_id, $event_id, $ids, null, 1, 0, 0, $now));

        // A row already there is renewed while it still waits for the order.
        db_q("UPDATE ad_conversion_events SET ids = ?, person = 1 WHERE service = ? AND event_id = ? AND status = 0",
            array($ids, $service, $event_id));
    }
}

/**
 * Completion: queues the order's purchase for each service (see
 * pg_ad_conversion_completion_plan()). Called by pg_order_complete() only
 * in the request that completed the order. Reads no visitor data: a payment
 * service's own message completes an order without the visitor.
 *
 * @param int    $order_id
 * @param int    $total        the order's total, minor units
 * @param string $order_number
 * @return void
 */
function pg_ad_conversion_order_completed($order_id, $total, $order_number)
{
    $order_id = (int) $order_id;

    if (($order_id <= 0) || !pg_ad_conversions_ready()) {
        return;
    }

    $settings = pg_ad_conversion_settings_all();
    $rows = array();
    $result = db_q("SELECT service, status, person FROM ad_conversion_events WHERE order_id = ? AND event = 'purchase'", array($order_id));

    while ($result && ($row = mysqli_fetch_assoc($result))) {
        $rows[$row['service']] = $row;
    }

    $plan = pg_ad_conversion_completion_plan($rows, $settings);

    if ($plan === array()) {
        return;
    }

    $order = pg_ad_conversion_order_row($order_id);

    if (($order === null) || !pg_ad_conversion_order_sendable($order['type'] ?? '')) {
        return;
    }

    $lines = array();
    $result = db_q("SELECT product_name, quantity, price FROM order_items WHERE order_id = ? ORDER BY id", array($order_id));

    while ($result && ($line = mysqli_fetch_assoc($result))) {
        $lines[] = $line;
    }

    $now = time();
    $event_id = pg_ad_conversion_purchase_event_id($order['reference_code']);
    $detail = json_encode(pg_ad_conversion_purchase_detail($total, defined('BASE_CURRENCY_CODE') ? BASE_CURRENCY_CODE : '', $order_number, $now, $lines));

    foreach ($plan as $service => $action) {
        if ($action === 'insert') {
            db_q(pg_ad_conversion_insert_sql(), array($service, 'purchase', $order_id, $event_id, null, $detail, 0, 1, $now, $now));
        } elseif ($action === 'due') {
            db_q("UPDATE ad_conversion_events SET status = 1, detail = ?, next_attempt_at = ? WHERE service = ? AND event_id = ? AND status = 0",
                array($detail, $now, $service, $event_id));
        } else {
            db_q("UPDATE ad_conversion_events SET status = 3, ids = NULL, last_error = ? WHERE service = ? AND event_id = ? AND status = 0",
                array('No consent', $service, $event_id));
        }
    }
}

/**
 * A cookie notice answer that refused marketing takes the ids off the rows
 * of this visitor's order not sent yet: the cart's and the one just
 * completed.
 *
 * @param array[]|null $changes pg_consent_answer_changes()
 * @return void
 */
function pg_ad_conversion_consent_changed($changes)
{
    if (!pg_ad_conversion_marketing_refused($changes) || !pg_ad_conversions_ready()) {
        return;
    }

    $order_ids = array_unique(array_filter(array(
        (int) ($_SESSION['ecommerce']['order_id'] ?? 0),
        (int) ($_SESSION['ecommerce']['completed_order_id'] ?? 0),
    )));

    foreach ($order_ids as $order_id) {
        db_q(pg_ad_conversion_withdraw_sql(), array($order_id));
    }
}

/**
 * The sender of a service, '' while its step has not added one.
 *
 * @param string $service
 * @return string
 */
function pg_ad_conversion_sender($service)
{
    $name = 'pg_ad_conversion_send_' . $service;

    return function_exists($name) ? $name : '';
}

/**
 * The sweep, once a day: rows past the keeping time, in batches.
 *
 * @return int rows deleted
 */
function pg_ad_conversion_sweep_daily()
{
    $path = PG_FUNCTIONS_DIR . '/data/temp/ad_conversions_swept.txt';
    $last = is_file($path) ? (int) @file_get_contents($path) : 0;
    $now = time();

    if (($now - $last) < 86400) {
        return 0;
    }

    @file_put_contents($path, (string) $now, LOCK_EX);

    $removed = 0;

    for ($batch = 0; $batch < PG_AD_CONVERSION_SWEEP_BATCHES; $batch++) {
        db(pg_ad_conversion_sweep_sql($now, PG_AD_CONVERSION_SWEEP_ROWS));
        $affected = max(0, (int) mysqli_affected_rows(db::$con));
        $removed += $affected;

        if ($affected < PG_AD_CONVERSION_SWEEP_ROWS) {
            break;
        }
    }

    return $removed;
}

/**
 * The general job's pass: the sweep, then the due rows within the time
 * budget. A row is claimed before its call, so two jobs never send it twice.
 * A service that did not answer has its other rows left for the next pass
 * without a call; a row whose service is no longer connected is given up; a
 * row whose service has no sender yet waits.
 *
 * @param int $budget seconds
 * @return int rows sent
 */
function pg_ad_conversion_dispatch($budget)
{
    if (!pg_ad_conversions_ready()) {
        return 0;
    }

    pg_ad_conversion_sweep_daily();

    require_once(PG_FUNCTIONS_DIR . '/includes/api/outbound/webhooks.php');

    $started = microtime(true);
    $now = time();
    $settings = pg_ad_conversion_settings_all();
    $held = array();
    $sent = 0;
    $rows = array();
    $result = db_q("SELECT * FROM ad_conversion_events WHERE status = 1 AND next_attempt_at <= ? ORDER BY next_attempt_at, id LIMIT " . PG_AD_CONVERSION_PASS_ROWS, array($now));

    while ($result && ($row = mysqli_fetch_assoc($result))) {
        $rows[] = $row;
    }

    foreach ($rows as $row) {
        if ((microtime(true) - $started) >= $budget) {
            break;
        }

        $service = (string) $row['service'];

        if (isset($held[$service])) {
            continue;
        }

        if (empty($settings[$service]['connected'])) {
            db_q("UPDATE ad_conversion_events SET status = 3, ids = NULL, last_error = ? WHERE id = ? AND status = 1",
                array('The service is not connected.', (int) $row['id']));
            continue;
        }

        $sender = pg_ad_conversion_sender($service);

        if ($sender === '') {
            continue;
        }

        // The claim: a row another pass took is left to it.
        db_q("UPDATE ad_conversion_events SET next_attempt_at = ? WHERE id = ? AND status = 1 AND next_attempt_at = ?",
            array($now + 300, (int) $row['id'], (int) $row['next_attempt_at']));

        if (mysqli_affected_rows(db::$con) < 1) {
            continue;
        }

        $order = ((int) $row['order_id'] > 0) ? pg_ad_conversion_order_row((int) $row['order_id']) : null;
        $outcome = call_user_func($sender, pg_ad_conversion_event($row, is_array($order) ? $order : array()), $settings[$service]);
        $outcome = is_array($outcome) ? $outcome : array('ok' => false, 'status' => 0, 'error' => 'No answer.');
        $values = pg_ad_conversion_after_attempt($row, $outcome, time());

        $set = array();
        $params = array();

        foreach ($values as $column => $value) {
            $set[] = $column . ' = ?';
            $params[] = $value;
        }

        $params[] = (int) $row['id'];
        db_q("UPDATE ad_conversion_events SET " . implode(', ', $set) . " WHERE id = ?", $params);

        if (!empty($outcome['ok'])) {
            $sent++;
        } elseif ((int) ($outcome['status'] ?? 0) === 0) {
            $held[$service] = true;
        }
    }

    return $sent;
}
