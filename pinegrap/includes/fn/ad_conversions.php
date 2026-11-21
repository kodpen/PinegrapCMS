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
 * The visitor's other events (Lead, CompleteRegistration, add to cart,
 * begin checkout) are written due at once, only with consent, by the
 * functions after pg_ad_conversion_dispatch().
 *
 * Consent is read by pg_ad_conversion_consent_by() from three inputs: the
 * cookie notice on, the stored answer and the service's "Send personal data
 * while the cookie notice is off" box; never pg_consent_allows(), which
 * answers yes while the notice is off.
 *
 * A service's settings live in config_text row ad_conversions_<service>, a
 * JSON object: connected, events (the ticked event names), person_notice_off
 * (the box), without_person (the events sent without the person when there is
 * no consent), lead_forms (the form pages sent as Lead), counter_sales (a
 * counter sale's purchase sent, never with the person).
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

// The most add to cart events one cart sends, per service.
define('PG_AD_CONVERSION_CART_ADDS', 20);

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
 *               (bool), without_person (string[]), lead_forms (int[], the
 *               form pages whose records are a Lead), counter_sales (bool)
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
        'lead_forms' => pg_ad_conversion_page_ids($data['lead_forms'] ?? array()),
        'counter_sales' => !empty($data['counter_sales']),
    );
}

/**
 * Every event a service page can offer, in the order the pages list them.
 * Pure.
 *
 * @return string[]
 */
function pg_ad_conversion_all_events()
{
    return array('purchase', 'refund', 'add_to_cart', 'begin_checkout', 'complete_registration', 'lead');
}

/**
 * The choices a service page's form writes, shared by the three pages: the
 * events ticked among those the service offers, the notice-off box, "Send
 * events without the person" (the purchase only), the forms sent as Lead
 * (kept only while the service offers Lead) and "Send counter sales". Pure.
 *
 * @param string[] $offered the events the service offers
 * @param array    $post    events (string[]), person_notice_off,
 *                          without_person (string[]), lead_forms (page ids),
 *                          counter_sales
 * @return array events, person_notice_off, without_person, lead_forms,
 *               counter_sales
 */
function pg_ad_conversion_choices_merge(array $offered, array $post)
{
    $posted = is_array($post['events'] ?? null) ? $post['events'] : array();
    $without = is_array($post['without_person'] ?? null) ? $post['without_person'] : array();
    $events = array_values(array_intersect(pg_ad_conversion_all_events(), $offered, $posted));

    return array(
        'events' => $events,
        'person_notice_off' => !empty($post['person_notice_off']),
        'without_person' => in_array('purchase', $offered, true) && in_array('purchase', $without, true) ? array('purchase') : array(),
        'lead_forms' => in_array('lead', $offered, true) ? pg_ad_conversion_page_ids($post['lead_forms'] ?? array()) : array(),
        'counter_sales' => !empty($post['counter_sales']),
    );
}

/**
 * A list of page ids as positive integers, in order, each once. Pure.
 *
 * @param mixed $value
 * @return int[]
 */
function pg_ad_conversion_page_ids($value)
{
    $ids = array();

    foreach (is_array($value) ? $value : array() as $id) {
        if ((is_int($id) || (is_string($id) && ctype_digit($id))) && ((int) $id > 0)) {
            $ids[] = (int) $id;
        }
    }

    return array_values(array_unique($ids));
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
 * Whether an order of this type is sent from the site's checkout: never a
 * marketplace order (the marketplace's buyer, not the shop's), never a
 * counter sale, whose purchase goes only through
 * pg_ad_conversion_counter_sale_completed(), without the person. Pure.
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
 *               contents (id, quantity, price), user, counter_sale (bool)
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
    $counter_sale = !empty($detail['counter_sale']);

    // A counter sale never carries the person, whatever the row says.
    if (((int) ($row['person'] ?? 0) === 1) && !$counter_sale) {
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
        'counter_sale' => $counter_sale,
    );
}

/**
 * The services a counter sale's purchase goes to: connected, the purchase
 * ticked and "Send counter sales" on. Pure.
 *
 * @param array $settings service => pg_ad_conversion_settings_parse()
 * @return string[]
 */
function pg_ad_conversion_counter_sale_plan(array $settings)
{
    $services = array();

    foreach ($settings as $service => $one) {
        if (!empty($one['connected']) && !empty($one['counter_sales']) && in_array('purchase', (array) ($one['events'] ?? array()), true)) {
            $services[] = $service;
        }
    }

    return $services;
}

/**
 * The insert parameters of a counter sale's purchase row for each service:
 * due at once, no ids, person 0, the detail marked as a counter sale. Pure.
 *
 * @param string[] $services pg_ad_conversion_counter_sale_plan()
 * @param int      $order_id
 * @param string   $event_id pg_ad_conversion_purchase_event_id()
 * @param array    $detail   pg_ad_conversion_purchase_detail()
 * @param int      $now
 * @return array[] pg_ad_conversion_insert_sql()'s parameters
 */
function pg_ad_conversion_counter_sale_rows(array $services, $order_id, $event_id, array $detail, $now)
{
    $rows = array();

    if (((int) $order_id <= 0) || ((string) $event_id === '')) {
        return $rows;
    }

    $detail['counter_sale'] = true;
    $detail = json_encode($detail);

    foreach ($services as $service) {
        $rows[] = array((string) $service, 'purchase', (int) $order_id, (string) $event_id, null, $detail, 0, 1, (int) $now, (int) $now);
    }

    return $rows;
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

    pg_ad_conversion_begin_checkout($order_id);

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
 * A counter sale completed at the till (pg_local_order_complete(), after its
 * claim, so a second completion never reaches it): its purchase is queued,
 * without the person, for each service with "Send counter sales" on. Nothing
 * of the buyer is read: no e-mail, phone, browser or click id.
 *
 * @param int    $order_id
 * @param int    $total        the order's total, minor units
 * @param string $order_number
 * @return void
 */
function pg_ad_conversion_counter_sale_completed($order_id, $total, $order_number)
{
    $order_id = (int) $order_id;

    if (($order_id <= 0) || !pg_ad_conversions_ready()) {
        return;
    }

    $services = pg_ad_conversion_counter_sale_plan(pg_ad_conversion_settings_all());

    if ($services === array()) {
        return;
    }

    $order = pg_ad_conversion_order_row($order_id);

    if (($order === null) || ((string) ($order['type'] ?? '') !== 'local')) {
        return;
    }

    $lines = array();
    $result = db_q("SELECT product_name, quantity, price FROM order_items WHERE order_id = ? ORDER BY id", array($order_id));

    while ($result && ($line = mysqli_fetch_assoc($result))) {
        $lines[] = $line;
    }

    $now = time();
    $detail = pg_ad_conversion_purchase_detail($total, defined('BASE_CURRENCY_CODE') ? BASE_CURRENCY_CODE : '', $order_number, $now, $lines);

    foreach (pg_ad_conversion_counter_sale_rows($services, $order_id, pg_ad_conversion_purchase_event_id($order['reference_code']), $detail, $now) as $params) {
        db_q(pg_ad_conversion_insert_sql(), $params);
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

// The visitor's events: Lead (a record of a form the shop picked),
// CompleteRegistration (an account opened), add to cart and begin checkout.
// Each goes only to a service that has it ticked, and only with the visitor's
// consent: no row is written without it. The row is due at once; the
// general job sends it as it sends a purchase.

/**
 * The visitor's events a service can tick besides the purchase. Pure.
 *
 * @return string[]
 */
function pg_ad_conversion_visitor_events()
{
    return array('lead', 'complete_registration', 'add_to_cart', 'begin_checkout');
}

/**
 * The services a visitor's event goes to: connected, the event ticked, the
 * visitor's consent for that service (pg_ad_conversion_consent_by()); a Lead
 * only for a form the service lists. Pure.
 *
 * @param string $event
 * @param bool   $notice_on
 * @param array  $choices
 * @param array  $settings     service => pg_ad_conversion_settings_parse()
 * @param int    $form_page_id the form's page, for a Lead
 * @return string[]
 */
function pg_ad_conversion_visitor_plan($event, $notice_on, array $choices, array $settings, $form_page_id = 0)
{
    $services = array();

    if (!in_array($event, pg_ad_conversion_visitor_events(), true)) {
        return $services;
    }

    foreach ($settings as $service => $one) {
        if (empty($one['connected']) || !in_array($event, (array) ($one['events'] ?? array()), true)) {
            continue;
        }

        if (($event === 'lead') && !in_array((int) $form_page_id, (array) ($one['lead_forms'] ?? array()), true)) {
            continue;
        }

        if (pg_ad_conversion_consent_by($notice_on, $choices, !empty($one['person_notice_off']))) {
            $services[] = $service;
        }
    }

    return $services;
}

/**
 * A visitor's event id: lead-<form record reference>, reg-<user id>,
 * checkout-<order reference> (once per order), cart-<order reference>-<n>
 * (the cart's n-th add); at most 64 characters, '' without a key. Pure.
 *
 * @param string     $event
 * @param string|int $key
 * @param int        $count the add's number in the cart, for add to cart
 * @return string
 */
function pg_ad_conversion_visitor_event_id($event, $key, $count = 0)
{
    $key = (string) $key;
    $prefixes = array('lead' => 'lead-', 'complete_registration' => 'reg-', 'begin_checkout' => 'checkout-', 'add_to_cart' => 'cart-');

    if (($key === '') || !isset($prefixes[$event])) {
        return '';
    }

    $suffix = ($event === 'add_to_cart') ? '-' . (int) $count : '';

    return substr($prefixes[$event] . substr($key, 0, 64 - strlen($prefixes[$event]) - strlen($suffix)) . $suffix, 0, 64);
}

/**
 * Whether a cart that sent this many add to cart events to a service may
 * send one more. Pure.
 *
 * @param int $count
 * @return bool
 */
function pg_ad_conversion_cart_add_allowed($count)
{
    return (int) $count < PG_AD_CONVERSION_CART_ADDS;
}

/**
 * What an add to cart says that is not about a person: the line, its value
 * in minor units. Pure.
 *
 * @param string $sku        products.name, as the purchase's lines carry it
 * @param int    $quantity
 * @param int    $unit_price minor units
 * @param string $currency
 * @param int    $time
 * @return array
 */
function pg_ad_conversion_cart_add_detail($sku, $quantity, $unit_price, $currency, $time)
{
    $quantity = max(1, (int) $quantity);
    $unit_price = (int) round((float) $unit_price);

    return array(
        'value' => $unit_price * $quantity,
        'currency' => (string) $currency,
        'order_number' => '',
        'time' => (int) $time,
        'contents' => array(array('id' => (string) $sku, 'quantity' => $quantity, 'price' => $unit_price)),
    );
}

/**
 * The ids of a visitor's event, with the e-mail hashed (a Lead and a
 * registration have no order to read it from at send time); the plain
 * address is never kept. Pure.
 *
 * @param array  $ids   pg_ad_conversion_ids_for()
 * @param string $email '' for none
 * @return array
 */
function pg_ad_conversion_visitor_ids(array $ids, $email)
{
    $hash = pg_ad_conversion_hash_email($email);

    if ($hash !== '') {
        $ids['email_sha256'] = $hash;
    }

    return $ids;
}

/**
 * The receipt's mark for a browser pixel pasted on the site: the purchase's
 * event id in data-pg-event-id, so the pixel sends the id the server sends
 * and the service counts the purchase once. Pure.
 *
 * @param string $reference_code the order's
 * @return string
 */
function pg_ad_conversion_receipt_marker($reference_code)
{
    $event_id = pg_ad_conversion_purchase_event_id($reference_code);

    return ($event_id === '') ? '' : '<span hidden data-pg-event-id="' . htmlspecialchars($event_id, ENT_QUOTES, 'UTF-8') . '"></span>';
}

/**
 * The services a visitor's event goes to in this request, read from the
 * settings and the visitor's consent; empty at once when none takes it.
 *
 * @param string $event
 * @param int    $form_page_id
 * @return string[]
 */
function pg_ad_conversion_visitor_services($event, $form_page_id = 0)
{
    if (!pg_ad_conversions_ready()) {
        return array();
    }

    $settings = pg_ad_conversion_settings_all();
    $ticked = false;

    foreach ($settings as $one) {
        $ticked = $ticked || (!empty($one['connected']) && in_array($event, $one['events'], true));
    }

    if (!$ticked) {
        return array();
    }

    return pg_ad_conversion_visitor_plan($event, pg_consent_enabled(), pg_consent_choices(), $settings, $form_page_id);
}

/**
 * Writes a visitor's event for each service, due at once, with that
 * service's ids (cookies, address, browser name) and the
 * hashed e-mail when one is given. A row already there for the event id is
 * left as it is.
 *
 * @param string   $event
 * @param string[] $event_ids service => event id
 * @param int      $order_id  0 without an order
 * @param array    $detail
 * @param string   $email
 * @return void
 */
function pg_ad_conversion_visitor_write($event, array $event_ids, $order_id, array $detail, $email = '')
{
    $ip = function_exists('waf_client_ip') ? waf_client_ip() : (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    $user_agent = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
    $detail = json_encode($detail);
    $now = time();

    foreach ($event_ids as $service => $event_id) {
        if ($event_id === '') {
            continue;
        }

        $ids = json_encode(pg_ad_conversion_visitor_ids(pg_ad_conversion_ids_for($service, $_COOKIE, $ip, $user_agent), $email));

        db_q(pg_ad_conversion_insert_sql(), array($service, $event, (int) $order_id, $event_id, $ids, $detail, 1, 1, $now, $now));
    }
}

/**
 * A Lead: a record of a form the shop picked on a service's page.
 *
 * @param int    $form_page_id   the form's page
 * @param string $reference_code the record's
 * @param string $email          the submitter's address, '' for none
 * @return void
 */
function pg_ad_conversion_lead($form_page_id, $reference_code, $email)
{
    $services = pg_ad_conversion_visitor_services('lead', (int) $form_page_id);
    $event_id = pg_ad_conversion_visitor_event_id('lead', $reference_code);

    if (($services === array()) || ($event_id === '')) {
        return;
    }

    $detail = array('value' => 0, 'currency' => '', 'order_number' => '', 'time' => time(), 'contents' => array());

    pg_ad_conversion_visitor_write('lead', array_fill_keys($services, $event_id), 0, $detail, $email);
}

/**
 * CompleteRegistration: an account the visitor opened.
 *
 * @param int    $user_id
 * @param string $email
 * @return void
 */
function pg_ad_conversion_registration($user_id, $email)
{
    if ((int) $user_id <= 0) {
        return;
    }

    $services = pg_ad_conversion_visitor_services('complete_registration');

    if ($services === array()) {
        return;
    }

    $detail = array('value' => 0, 'currency' => '', 'order_number' => '', 'time' => time(), 'contents' => array());

    pg_ad_conversion_visitor_write('complete_registration', array_fill_keys($services, pg_ad_conversion_visitor_event_id('complete_registration', (int) $user_id)), 0, $detail, $email);
}

/**
 * Add to cart, from cart_action.php: returns at once unless a service has
 * the event ticked and the visitor consented; at most
 * PG_AD_CONVERSION_CART_ADDS a cart and service.
 *
 * @param int    $order_id   the cart
 * @param string $sku        products.name
 * @param int    $quantity
 * @param int    $unit_price minor units
 * @return void
 */
function pg_ad_conversion_add_to_cart($order_id, $sku, $quantity, $unit_price)
{
    $services = ((int) $order_id > 0) ? pg_ad_conversion_visitor_services('add_to_cart') : array();

    if ($services === array()) {
        return;
    }

    $order = pg_ad_conversion_order_row((int) $order_id);

    if (($order === null) || !pg_ad_conversion_order_sendable($order['type'] ?? '')) {
        return;
    }

    $event_ids = array();

    foreach ($services as $service) {
        $result = db_q("SELECT COUNT(*) FROM ad_conversion_events WHERE order_id = ? AND service = ? AND event = 'add_to_cart'", array((int) $order_id, $service));
        $row = $result ? mysqli_fetch_row($result) : null;
        $count = (int) ($row[0] ?? 0);

        if (pg_ad_conversion_cart_add_allowed($count)) {
            $event_ids[$service] = pg_ad_conversion_visitor_event_id('add_to_cart', $order['reference_code'], $count + 1);
        }
    }

    $detail = pg_ad_conversion_cart_add_detail($sku, $quantity, $unit_price, defined('BASE_CURRENCY_CODE') ? BASE_CURRENCY_CODE : '', time());

    pg_ad_conversion_visitor_write('add_to_cart', $event_ids, (int) $order_id, $detail);
}

/**
 * Begin checkout, at the payment step: once per order (the event id is the
 * order's), with the cart's lines and total.
 *
 * @param int $order_id
 * @return void
 */
function pg_ad_conversion_begin_checkout($order_id)
{
    $services = pg_ad_conversion_visitor_services('begin_checkout');

    if ($services === array()) {
        return;
    }

    $order = pg_ad_conversion_order_row((int) $order_id);

    if (($order === null) || !pg_ad_conversion_order_sendable($order['type'] ?? '')) {
        return;
    }

    $lines = array();
    $result = db_q("SELECT product_name, quantity, price FROM order_items WHERE order_id = ? ORDER BY id", array((int) $order_id));

    while ($result && ($line = mysqli_fetch_assoc($result))) {
        $lines[] = $line;
    }

    $result = db_q("SELECT total FROM orders WHERE id = ?", array((int) $order_id));
    $row = $result ? mysqli_fetch_row($result) : null;
    $total = (int) round((float) ($row[0] ?? 0));
    $detail = pg_ad_conversion_purchase_detail($total, defined('BASE_CURRENCY_CODE') ? BASE_CURRENCY_CODE : '', '', time(), $lines);

    pg_ad_conversion_visitor_write('begin_checkout', array_fill_keys($services, pg_ad_conversion_visitor_event_id('begin_checkout', $order['reference_code'])), (int) $order_id, $detail);
}

/**
 * One switch row of a service page: the title, a line under it and the
 * switch.
 *
 * @param string $id
 * @param string $name    the field's name
 * @param string $value
 * @param bool   $checked
 * @param string $title
 * @param string $meta
 * @return string
 */
function pg_ad_conversion_switch_row($id, $name, $value, $checked, $title, $meta)
{
    return '
					<div class="pg-card-inner-row">
						<div class="flex-grow-1">
							<label class="pg-list-row-title d-block" for="' . h($id) . '">' . h($title) . '</label>
							<div class="pg-text-meta">' . h($meta) . '</div>
						</div>
						<input type="checkbox" class="pg-switch" role="switch" name="' . h($name) . '" value="' . h($value) . '" id="' . h($id) . '"' . ($checked ? ' checked' : '') . '>
					</div>';
}

/**
 * The choices part of a service page's "Connection details" form, the same
 * on the three pages: "Events sent" (a switch for each event the service
 * offers), "Forms sent as Lead" when it offers Lead, "Without consent",
 * "Counter sales" and "Send personal data while the cookie notice is off"
 * with its warning.
 *
 * @param string $service
 * @param array  $names     our event => the service's own name, in the
 *                          order of pg_ad_conversion_all_events()
 * @param array  $stored    pg_ad_conversion_settings_parse()
 * @param bool   $notice_on pg_consent_enabled()
 * @return string
 */
function pg_ad_conversion_choices_fields($service, array $names, array $stored, $notice_on)
{
    $events = '';
    $consent_only = array('add_to_cart', 'begin_checkout', 'complete_registration', 'lead');

    foreach ($names as $event => $their_name) {
        $meta = $their_name;

        if ($event === 'refund') {
            $meta .= ' · ' . lang('A refund goes for an order whose purchase Google Analytics received, within 72 hours of the refund.');
        } elseif ($event === 'lead') {
            $meta .= ' · ' . lang('A record of a form picked below.');
        } elseif (in_array($event, $consent_only, true)) {
            $meta .= ' · ' . lang('Only after the visitor accepted marketing cookies.');
        }

        $events .= pg_ad_conversion_switch_row($service . '_event_' . $event, 'ad_events[]', $event, in_array($event, $stored['events'], true), pg_ad_conversion_log_event_label($event), $meta);
    }

    $forms = '';

    if (isset($names['lead'])) {
        foreach ((array) db_items("SELECT page_id, form_name FROM custom_form_pages ORDER BY form_name, page_id") as $form) {
            $form_id = (int) $form['page_id'];
            $forms .= '
					<div class="d-flex align-items-center gap-2">
						<input type="checkbox" class="pg-checkbox" name="ad_lead_forms[]" value="' . $form_id . '" id="' . h($service) . '_lead_form_' . $form_id . '"' . (in_array($form_id, $stored['lead_forms'], true) ? ' checked' : '') . '>
						<label class="pg-text-2" for="' . h($service) . '_lead_form_' . $form_id . '">' . h((string) $form['form_name']) . '</label>
					</div>';
        }

        $forms = '
				<div>
					<div class="pg-field-label">' . h(lang('Forms sent as Lead')) . '</div>' . (($forms !== '') ? $forms : '
					<div class="pg-text-meta">' . h(lang('No form yet.')) . '</div>') . '
					<div class="pg-field-help">' . h(lang('A record of a picked form is sent as Lead, when Lead is on.')) . '</div>
				</div>';
    }

    $notice_line = '';

    if (!$notice_on && !$stored['person_notice_off']) {
        $notice_line = '
				<div class="alert theme-warning small py-3 mb-0">' . h(lang('The cookie notice is off: events go without the person')) . '.
					<a href="' . h(pg_settings_link('privacy', 'pgset-cookies')) . '">' . h(lang('Cookie Consent')) . '</a></div>';
    }

    return '
				<div>
					<div class="pg-field-label">' . h(lang('Events sent')) . '</div>
					<div class="pg-card-inner">' . $events . '
					</div>
				</div>' . $forms . '
				<div>
					<div class="pg-field-label">' . h(lang('Without consent')) . '</div>
					<div class="pg-card-inner">' . pg_ad_conversion_switch_row($service . '_without_purchase', 'ad_without_person[]', 'purchase', in_array('purchase', $stored['without_person'], true),
                        lang('Send events without the person'), lang('Purchase') . ' · ' . lang('Only the amount and the products, when the buyer did not accept marketing cookies.')) . '
					</div>
				</div>
				<div>
					<div class="pg-field-label">' . h(lang('Counter sales')) . '</div>
					<div class="pg-card-inner">' . pg_ad_conversion_switch_row($service . '_counter_sales', 'counter_sales', '1', !empty($stored['counter_sales']),
                        lang('Send counter sales'), lang('Purchase') . ' · ' . lang('A sale at the till goes with the amount and the products only: no e-mail, phone, browser or click id.')) . '
					</div>
				</div>
				<div>
					<div class="pg-card-inner">' . pg_ad_conversion_switch_row($service . '_person_notice_off', 'person_notice_off', '1', $stored['person_notice_off'],
                        lang('Send personal data while the cookie notice is off'), lang('The buyer was not asked, and the risk is the shop\'s. It acts only while the cookie notice is off.')) . '
					</div>
				</div>' . $notice_line;
}

// The services' senders.
require_once(PG_FUNCTIONS_DIR . '/includes/fn/ad_conversions_meta.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/ad_conversions_tiktok.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/ad_conversions_ga4.php');
