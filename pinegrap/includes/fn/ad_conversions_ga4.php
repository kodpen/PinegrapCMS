<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * GA4 Measurement Protocol: the sender of ad_conversion_events rows for the
 * service ga4_mp, the body it posts, how Google's answers are read, the
 * refund rows written from order_refunds, the settings and sealed API secret
 * typed on connections.php?service=ga4_mp, and that page's "Connection
 * details" card.
 *
 * The Measurement ID is not typed here: it is the Google tag ID set on the
 * Google Analytics page (GOOGLE_ANALYTICS_WEB_PROPERTY_ID), used when it is a
 * GA4 stream's G- id. The settings share config_text row
 * ad_conversions_ga4_mp with what ad_conversions.php reads (connected, events,
 * person_notice_off, without_person) and add secret, the API secret sealed
 * with pg_connect_seal(). "Send a test event" posts to the validation
 * endpoint, which checks the body but not the secret, and shows nothing in
 * the reports.
 *
 * The request's facts (addresses, body, client id, consent, limits, answers)
 * are read from Google's own documents and kept in
 * docs/memory/api/ga4-mp-facts.md.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// Google backdates an event up to 72 hours and moves an older one to 72
// hours ago; a row older than this is given up without a call instead.
define('PG_AD_CONVERSION_GA4_MAX_AGE', 72 * 3600 - 600);

// The config_text row of the service.
define('PG_AD_CONVERSION_GA4_ROW', 'ad_conversions_ga4_mp');

// The most refund rows one pass of the job writes.
define('PG_AD_CONVERSION_GA4_REFUND_ROWS', 100);

/**
 * The events the page can tick, our name => GA4's recommended event name.
 * Pure.
 *
 * @return array
 */
function pg_ad_conversion_ga4_events()
{
    return array(
        'purchase' => 'purchase',
        'refund' => 'refund',
        'add_to_cart' => 'add_to_cart',
        'begin_checkout' => 'begin_checkout',
        'complete_registration' => 'sign_up',
        'lead' => 'generate_lead',
    );
}

/**
 * GA4's name of one of our events, '' for one it has none for. Pure.
 *
 * @param string $event
 * @return string
 */
function pg_ad_conversion_ga4_event_name($event)
{
    $names = pg_ad_conversion_ga4_events();

    return isset($names[$event]) ? $names[$event] : '';
}

/**
 * Whether a Measurement ID is a GA4 web stream's: G- and letters and digits.
 * Pure.
 *
 * @param mixed $id
 * @return bool
 */
function pg_ad_conversion_ga4_measurement_id_valid($id)
{
    return is_string($id) && (bool) preg_match('/^G-[A-Z0-9]{4,20}$/D', $id);
}

/**
 * Whether an API secret has the shape Google gives: 10 to 100 letters,
 * digits, dashes and underscores. Pure.
 *
 * @param mixed $secret
 * @return bool
 */
function pg_ad_conversion_ga4_secret_valid($secret)
{
    return is_string($secret) && (bool) preg_match('/^[A-Za-z0-9_\-]{10,100}$/D', $secret);
}

/**
 * The Measurement ID: the Google Analytics page's tag ID when it is a G- id,
 * else ''.
 *
 * @return string
 */
function pg_ad_conversion_ga4_measurement_id()
{
    $tag = defined('GOOGLE_ANALYTICS_WEB_PROPERTY_ID') ? strtoupper(trim((string) GOOGLE_ANALYTICS_WEB_PROPERTY_ID)) : '';

    return pg_ad_conversion_ga4_measurement_id_valid($tag) ? $tag : '';
}

/**
 * The stored settings row as an array, every key present. Pure.
 *
 * @param mixed $raw the stored JSON
 * @return array the keys of pg_ad_conversion_settings_parse() and secret
 *               (sealed)
 */
function pg_ad_conversion_ga4_stored($raw)
{
    $data = is_string($raw) ? json_decode($raw, true) : null;
    $data = is_array($data) ? $data : array();

    return pg_ad_conversion_settings_parse($raw) + array(
        'secret' => (string) ($data['secret'] ?? ''),
    );
}

/**
 * The settings the page's form writes. An empty secret field keeps the
 * stored secret; the choices are taken as posted
 * (pg_ad_conversion_choices_merge()). "Send events without the person" is
 * the purchase's only: a refund never carries the person (see
 * pg_ad_conversion_ga4_refund_rows()). Pure.
 *
 * @param array  $stored pg_ad_conversion_ga4_stored()
 * @param array  $post   the choices of pg_ad_conversion_choices_merge()
 * @param string $sealed the newly sealed secret, '' to keep the stored one
 * @return array what is saved as JSON
 */
function pg_ad_conversion_ga4_settings_merge(array $stored, array $post, $sealed)
{
    $secret = ((string) $sealed !== '') ? (string) $sealed : (string) ($stored['secret'] ?? '');

    return array(
        'connected' => ($secret !== ''),
        'secret' => $secret,
    ) + pg_ad_conversion_choices_merge(array_keys(pg_ad_conversion_ga4_events()), $post);
}

/**
 * A refund's event id: the purchase's id and the order_refunds row's, so
 * each refund of an order is its own row. Pure.
 *
 * @param string $purchase_event_id
 * @param int    $refund_id
 * @return string
 */
function pg_ad_conversion_ga4_refund_event_id($purchase_event_id, $refund_id)
{
    return substr((string) $purchase_event_id, 0, 40) . '-refund-' . (int) $refund_id;
}

/**
 * The transaction id GA4 joins a purchase and its refunds by: the order's
 * reference, the purchase's event id. Pure.
 *
 * @param array $event pg_ad_conversion_event()
 * @return string
 */
function pg_ad_conversion_ga4_transaction_id(array $event)
{
    $id = (string) ($event['event_id'] ?? '');

    if ((($event['event'] ?? '') === 'refund') && preg_match('/^(.+)-refund-\d+$/D', $id, $match)) {
        return $match[1];
    }

    return $id;
}

/**
 * The client id of the body. With the person, the visitor's own from the
 * _ga cookie. Without it, one made from the transaction id, in the shape
 * Google asks (two positive numbers joined by a period): the same for an
 * order's purchase and its refunds, and nothing of the visitor. Pure.
 *
 * @param array $event pg_ad_conversion_event()
 * @return string
 */
function pg_ad_conversion_ga4_client_id(array $event)
{
    $own = (string) ($event['user']['client_id'] ?? '');

    if (preg_match('/^\d+\.\d+$/D', $own)) {
        return $own;
    }

    $hash = hash('sha256', 'pg-ga4-client:' . pg_ad_conversion_ga4_transaction_id($event));

    return (hexdec(substr($hash, 0, 7)) + 1) . '.' . (hexdec(substr($hash, 7, 7)) + 1);
}

/**
 * One event as the Measurement Protocol's body: the client id, the time in
 * microseconds, the consent the row stands for, the address and browser
 * name only with the person, and one event with the ecommerce parameters.
 * No user_data: GA4 normalises e-mail and phone otherwise than the event
 * carries them, and asks a user_id with them. Pure.
 *
 * @param array $event pg_ad_conversion_event()
 * @return array
 */
function pg_ad_conversion_ga4_body(array $event)
{
    $person = (array) ($event['user'] ?? array());
    $with_person = preg_match('/^\d+\.\d+$/D', (string) ($person['client_id'] ?? '')) === 1;
    $state = $with_person ? 'GRANTED' : 'DENIED';

    $params = array();

    // Only a purchase and a refund are joined by a transaction id; a Lead or
    // a sign-up has no amount, so no value and no currency.
    if (in_array((string) ($event['event'] ?? ''), array('purchase', 'refund'), true)) {
        $params['transaction_id'] = pg_ad_conversion_ga4_transaction_id($event);
    }

    if ((string) ($event['currency'] ?? '') !== '') {
        $params['currency'] = strtoupper((string) $event['currency']);
        $params['value'] = (float) ($event['value'] ?? 0);
    }

    $items = array();

    foreach ((array) ($event['contents'] ?? array()) as $line) {
        $items[] = array(
            'item_id' => (string) ($line['id'] ?? ''),
            'quantity' => (int) ($line['quantity'] ?? 0),
            'price' => (float) ($line['price'] ?? 0),
        );
    }

    if ($items) {
        $params['items'] = $items;
    }

    $body = array(
        'client_id' => pg_ad_conversion_ga4_client_id($event),
        'timestamp_micros' => (int) ($event['time'] ?? 0) * 1000000,
        'consent' => array('ad_user_data' => $state, 'ad_personalization' => $state),
    );

    if ($with_person) {
        foreach (array('ip' => 'ip_override', 'user_agent' => 'user_agent') as $ours => $theirs) {
            if (isset($person[$ours]) && is_string($person[$ours]) && ($person[$ours] !== '')) {
                $body[$theirs] = $person[$ours];
            }
        }
    }

    $body['events'] = array(array(
        'name' => pg_ad_conversion_ga4_event_name((string) ($event['event'] ?? '')),
        'params' => $params ? $params : new stdClass(),
    ));

    return $body;
}

/**
 * The address events are posted to, the validation endpoint's for a test.
 * The secret goes in the query string, the only place Google takes it.
 * Pure.
 *
 * @param string $measurement_id
 * @param string $secret
 * @param bool   $validate
 * @return string
 */
function pg_ad_conversion_ga4_url($measurement_id, $secret, $validate = false)
{
    return 'https://www.google-analytics.com/' . ($validate ? 'debug/mp/collect' : 'mp/collect')
        . '?measurement_id=' . rawurlencode((string) $measurement_id) . '&api_secret=' . rawurlencode((string) $secret);
}

/**
 * The collection endpoint's answer as the queue reads a send. Google answers
 * 2xx for every request it received, even with a body it will not use; any
 * other status is a refusal not to be sent again as it was, but for a
 * server failure or a throttle, which api_http_should_retry() retries. Pure.
 *
 * @param array $http api_http_post()'s answer (ok, status, body, error)
 * @return array ok, status, error
 */
function pg_ad_conversion_ga4_answer(array $http)
{
    $status = (int) ($http['status'] ?? 0);

    if ($status === 0) {
        return array('ok' => false, 'status' => 0, 'error' => (string) (($http['error'] ?? '') !== '' ? $http['error'] : 'Google Analytics did not answer.'));
    }

    if (($status >= 200) && ($status < 300)) {
        return array('ok' => true, 'status' => $status, 'error' => '');
    }

    return array('ok' => false, 'status' => $status, 'error' => 'HTTP ' . $status);
}

/**
 * The validation endpoint's answer: valid when validationMessages is empty;
 * else each message's field, description and code. Pure.
 *
 * @param array $http api_http_post()'s answer
 * @return array ok, status, error
 */
function pg_ad_conversion_ga4_validation_answer(array $http)
{
    $outcome = pg_ad_conversion_ga4_answer($http);

    if (!$outcome['ok']) {
        return $outcome;
    }

    $data = json_decode((string) ($http['body'] ?? ''), true);

    if (!is_array($data) || !isset($data['validationMessages']) || !is_array($data['validationMessages'])) {
        return array('ok' => false, 'status' => (int) $outcome['status'], 'error' => 'Google Analytics gave no validation answer.');
    }

    $lines = array();

    foreach ($data['validationMessages'] as $message) {
        $lines[] = trim((string) ($message['fieldPath'] ?? '') . ': ' . (string) ($message['description'] ?? ''), ': ')
            . ((string) ($message['validationCode'] ?? '') !== '' ? ' (' . (string) $message['validationCode'] . ')' : '');
    }

    if ($lines === array()) {
        return $outcome;
    }

    return array('ok' => false, 'status' => 400, 'error' => substr(implode(' ', $lines), 0, 500));
}

/**
 * Why an event cannot go to GA4, '' when it can: keys missing, an event GA4
 * has no name for, an event older than GA4 keeps. Pure.
 *
 * @param array $event pg_ad_conversion_event()
 * @param array $keys  measurement_id, secret
 * @param int   $now
 * @return string
 */
function pg_ad_conversion_ga4_refusal(array $event, array $keys, $now)
{
    if (($keys['measurement_id'] ?? '') === '' || ($keys['secret'] ?? '') === '') {
        return 'The Measurement ID or the API secret is missing.';
    }

    if (pg_ad_conversion_ga4_event_name((string) ($event['event'] ?? '')) === '') {
        return 'Google Analytics has no event for this.';
    }

    if (((int) ($event['time'] ?? 0) <= 0) || (((int) $now - (int) $event['time']) > PG_AD_CONVERSION_GA4_MAX_AGE)) {
        return 'Google Analytics takes events up to 72 hours old.';
    }

    return '';
}

/**
 * A refund's detail, from its purchase's: the refunded amount in minor
 * units, the purchase's currency and order number, the refund's time, no
 * lines (a refund by amount). Pure.
 *
 * @param mixed $purchase_detail the purchase row's detail JSON
 * @param int   $amount          minor units
 * @param int   $time
 * @return array
 */
function pg_ad_conversion_ga4_refund_detail($purchase_detail, $amount, $time)
{
    $purchase = json_decode((string) $purchase_detail, true);
    $purchase = is_array($purchase) ? $purchase : array();

    return array(
        'value' => (int) $amount,
        'currency' => (string) ($purchase['currency'] ?? ''),
        'order_number' => (string) ($purchase['order_number'] ?? ''),
        'time' => (int) $time,
        'contents' => array(),
    );
}

/**
 * The statement that finds the refunds to send: order_refunds rows of the
 * last 72 hours whose order's purchase GA4 received (status 2), so a refund
 * of an order GA4 never saw is not sent. Read from the purchase rows, a
 * small table, then order_refunds by its order_id index.
 *
 * @return string
 */
function pg_ad_conversion_ga4_refunds_sql()
{
    return "SELECT r.id AS refund_id, r.order_id, r.amount_cents, UNIX_TIMESTAMP(r.created_at) AS refunded_at, e.event_id, e.detail
        FROM ad_conversion_events e
        INNER JOIN order_refunds r ON r.order_id = e.order_id
        WHERE e.service = 'ga4_mp' AND e.event = 'purchase' AND e.status = 2
            AND r.created_at >= DATE_SUB(NOW(), INTERVAL 72 HOUR)
        ORDER BY r.id
        LIMIT " . PG_AD_CONVERSION_GA4_REFUND_ROWS;
}

/**
 * The rows pg_ad_conversion_insert_sql() writes for the refunds found: due
 * at once, without the person (a refund follows the purchase by its
 * transaction id, and the ids were dropped when the purchase was sent). A
 * refund of nothing is left out. Pure.
 *
 * @param array $refunds pg_ad_conversion_ga4_refunds_sql() rows
 * @param int   $now
 * @return array[] the insert's parameters
 */
function pg_ad_conversion_ga4_refund_rows(array $refunds, $now)
{
    $rows = array();

    foreach ($refunds as $refund) {
        if ((int) ($refund['amount_cents'] ?? 0) <= 0) {
            continue;
        }

        $detail = pg_ad_conversion_ga4_refund_detail($refund['detail'] ?? '', (int) $refund['amount_cents'], (int) ($refund['refunded_at'] ?? 0));

        $rows[] = array(
            'ga4_mp',
            'refund',
            (int) $refund['order_id'],
            pg_ad_conversion_ga4_refund_event_id((string) $refund['event_id'], (int) $refund['refund_id']),
            null,
            json_encode($detail),
            0,
            1,
            (int) $now,
            (int) $now,
        );
    }

    return $rows;
}

/**
 * The general job's refund pass: when GA4 is connected with the refund
 * ticked, a due row for each recent refund of an order GA4 received. The
 * unique key (service, event_id) keeps a refund to one row however often the
 * pass reads it. Every writer of order_refunds (a refund on the order page,
 * a return's refund, a refund as store credit) is covered without a call of
 * its own.
 *
 * @return int rows written
 */
function pg_ad_conversion_ga4_queue_refunds()
{
    if (!pg_ad_conversions_ready()) {
        return 0;
    }

    $settings = pg_ad_conversion_settings_parse(pg_config_text(PG_AD_CONVERSION_GA4_ROW));

    if (!$settings['connected'] || !in_array('refund', $settings['events'], true)) {
        return 0;
    }

    $refunds = array();
    $result = db(pg_ad_conversion_ga4_refunds_sql());

    while ($result && ($row = mysqli_fetch_assoc($result))) {
        $refunds[] = $row;
    }

    $written = 0;

    foreach (pg_ad_conversion_ga4_refund_rows($refunds, time()) as $params) {
        db_q(pg_ad_conversion_insert_sql(), $params);
        $written += max(0, (int) mysqli_affected_rows(db::$con));
    }

    return $written;
}

/**
 * The Measurement ID and the opened API secret, '' each when not set or the
 * secret does not open.
 *
 * @return array measurement_id, secret
 */
function pg_ad_conversion_ga4_keys()
{
    $stored = pg_ad_conversion_ga4_stored(pg_config_text(PG_AD_CONVERSION_GA4_ROW));
    $open = ($stored['secret'] !== '') ? pg_connect_unseal($stored['secret']) : array();

    return array(
        'measurement_id' => pg_ad_conversion_ga4_measurement_id(),
        'secret' => (string) ($open['secret'] ?? ''),
    );
}

/**
 * Whether GA4 is connected (a secret is stored and the Google Analytics page
 * has a G- Measurement ID): the registry's state reader.
 *
 * @return bool
 */
function pg_ad_conversion_ga4_connected()
{
    $stored = pg_ad_conversion_ga4_stored(pg_config_text(PG_AD_CONVERSION_GA4_ROW));

    return $stored['connected'] && ($stored['secret'] !== '') && (pg_ad_conversion_ga4_measurement_id() !== '');
}

/**
 * One post to GA4, to the validation endpoint for a test.
 *
 * @param array $keys     measurement_id, secret
 * @param array $body     pg_ad_conversion_ga4_body()
 * @param bool  $validate
 * @return array ok, status, error
 */
function pg_ad_conversion_ga4_post(array $keys, array $body, $validate = false)
{
    require_once(PG_FUNCTIONS_DIR . '/includes/api/outbound/http.php');

    $http = api_http_post(pg_ad_conversion_ga4_url($keys['measurement_id'], $keys['secret'], $validate), json_encode($body), array('Content-Type: application/json'), 10);
    $http = is_array($http) ? $http : array();

    return $validate ? pg_ad_conversion_ga4_validation_answer($http) : pg_ad_conversion_ga4_answer($http);
}

/**
 * The sender pg_ad_conversion_dispatch() calls for a due ga4_mp row. A row
 * GA4 could not take is answered as a refusal (400) without a call, so it
 * is given up.
 *
 * @param array $event    pg_ad_conversion_event()
 * @param array $settings pg_ad_conversion_settings_parse()
 * @return array ok, status, error
 */
function pg_ad_conversion_send_ga4_mp(array $event, array $settings)
{
    $keys = pg_ad_conversion_ga4_keys();
    $refusal = pg_ad_conversion_ga4_refusal($event, $keys, time());

    if ($refusal !== '') {
        return array('ok' => false, 'status' => 400, 'error' => $refusal);
    }

    return pg_ad_conversion_ga4_post($keys, pg_ad_conversion_ga4_body($event));
}

/**
 * The test event "Send a test event" posts: a purchase of 1.00 in the site's
 * currency without the person, with an id of its own. Pure.
 *
 * @param int    $now
 * @param string $currency
 * @return array as pg_ad_conversion_event()
 */
function pg_ad_conversion_ga4_test_event($now, $currency)
{
    return array(
        'event' => 'purchase',
        'event_id' => 'pg-test-' . (int) $now,
        'time' => (int) $now,
        'value' => '1.00',
        'currency' => (string) $currency,
        'order_number' => '',
        'contents' => array(array('id' => 'pg-test', 'quantity' => 1, 'price' => '1.00')),
        'user' => array(),
    );
}

/**
 * "Send a test event": the test purchase to the validation endpoint, which
 * reads the body and shows nothing in the reports. It does not check the
 * secret.
 *
 * @return array ok, status, error
 */
function pg_ad_conversion_ga4_send_test()
{
    $keys = pg_ad_conversion_ga4_keys();
    $now = time();
    $event = pg_ad_conversion_ga4_test_event($now, defined('BASE_CURRENCY_CODE') ? BASE_CURRENCY_CODE : 'USD');
    $refusal = pg_ad_conversion_ga4_refusal($event, $keys, $now);

    if ($refusal !== '') {
        return array('ok' => false, 'status' => 400, 'error' => $refusal);
    }

    return pg_ad_conversion_ga4_post($keys, pg_ad_conversion_ga4_body($event), true);
}

/**
 * The page's write: save (and send a test), or disconnect. Errors and
 * notices go to the live form; ticking or clearing the notice-off box writes
 * an activity log line.
 *
 * @param string $action   ga4_mp_save, ga4_mp_remove
 * @param array  $post     $_POST
 * @param object $liveform
 * @return void
 */
function pg_ad_conversion_ga4_post_page($action, array $post, $liveform)
{
    $name = 'GA4 Measurement Protocol';
    $stored = pg_ad_conversion_ga4_stored(pg_config_text(PG_AD_CONVERSION_GA4_ROW));

    if ($action === 'ga4_mp_remove') {
        pg_config_text_save(PG_AD_CONVERSION_GA4_ROW, '');
        $liveform->add_notice(lang(array('string' => '{var:1} was disconnected.', 'vars' => $name)));
        log_activity(lang(array('string' => 'removed the {var:1} connection', 'vars' => $name)), $_SESSION['sessionusername']);

        return;
    }

    if ($action !== 'ga4_mp_save') {
        return;
    }

    $secret = trim((string) ($post['api_secret'] ?? ''));

    if (($secret === '') && ($stored['secret'] === '')) {
        $liveform->add_error(lang('Paste the API secret.'));

        return;
    }

    if (($secret !== '') && !pg_ad_conversion_ga4_secret_valid($secret)) {
        $liveform->add_error(lang('The API secret is 10 to 100 letters and digits without spaces.'));

        return;
    }

    $sealed = '';

    if ($secret !== '') {
        $sealed = pg_connect_seal(array('secret' => $secret));

        if ($sealed === '') {
            $liveform->add_error(lang('This site has no encryption key, so it cannot keep the key.'));

            return;
        }
    }

    $saved = pg_ad_conversion_ga4_settings_merge($stored, array(
        'events' => $post['ad_events'] ?? array(),
        'person_notice_off' => !empty($post['person_notice_off']),
        'without_person' => $post['ad_without_person'] ?? array(),
        'lead_forms' => $post['ad_lead_forms'] ?? array(),
        'counter_sales' => !empty($post['counter_sales']),
    ), $sealed);

    pg_config_text_save(PG_AD_CONVERSION_GA4_ROW, json_encode($saved));
    $liveform->add_notice(lang(array('string' => 'The {var:1} settings were saved.', 'vars' => $name)));
    log_activity(lang(array('string' => 'changed the {var:1} connection', 'vars' => $name)), $_SESSION['sessionusername']);

    if ($saved['person_notice_off'] !== $stored['person_notice_off']) {
        log_activity(lang(array(
            'string' => $saved['person_notice_off']
                ? 'turned on sending personal data to {var:1} while the cookie notice is off'
                : 'turned off sending personal data to {var:1} while the cookie notice is off',
            'vars' => $name,
        )), $_SESSION['sessionusername']);
    }

    if (empty($post['submit_try'])) {
        return;
    }

    $try = pg_ad_conversion_ga4_send_test();

    if ($try['ok']) {
        $liveform->add_notice(lang('Google Analytics found the test event valid. The validation endpoint does not check the API secret, and the event does not show in the reports.'));
    } else {
        // Google's own words, escaped whole.
        $liveform->add_error(h((string) $try['error']));
    }
}

/**
 * The "Connection details" card of GA4's page, and Disconnect in Status.
 *
 * @param array $page       the page's parts (connections.php)
 * @param bool  $may_manage who may open Visitors and privacy
 * @param bool  $can_seal
 * @return array the page's parts
 */
function pg_ad_conversion_ga4_page(array $page, $may_manage, $can_seal)
{
    $stored = pg_ad_conversion_ga4_stored(pg_config_text(PG_AD_CONVERSION_GA4_ROW));
    $notice_on = function_exists('pg_consent_enabled') && pg_consent_enabled();
    $measurement_id = pg_ad_conversion_ga4_measurement_id();
    $page['here'] = true;

    if ($may_manage && !in_array('details', $page['cards'], true)) {
        array_splice($page['cards'], max(0, (int) array_search('uses', $page['cards'], true)), 0, array('details'));
    }

    if ($may_manage) {
        $measurement_line = ($measurement_id !== '')
            ? '<input type="text" class="pg-input font-monospace" id="ga4_mp_measurement_id" value="' . h($measurement_id) . '" readonly>
					<div class="pg-field-help">' . h(lang('The Google tag ID of the Google Analytics page.')) . '</div>'
            : '<div class="alert theme-warning small py-3 mb-0">' . h(lang('Set a Measurement ID that starts with G- on the Google Analytics page first.')) . '
					<a href="connections.php?service=google_analytics">' . h(lang('Google Analytics')) . '</a></div>';

        $page['details'] = '
		<section class="pg-card p-5 mb-6" id="connections-details" aria-labelledby="connections-page-details">
			<h2 class="pg-title-section mb-1" id="connections-page-details">' . h(lang('Connection details')) . '</h2>
			<p class="pg-card-sub mb-4">' . h(lang('Events are sent from the server to Google Analytics, after the visitor accepted marketing cookies.')) . '</p>
			<form method="post" action="connections.php" class="connections-app-form">
				' . get_token_field() . '
				<input type="hidden" name="connections_action" value="ga4_mp_save">
				<input type="hidden" name="connections_return" value="ga4_mp">
				<div>
					<label class="pg-field-label" for="ga4_mp_measurement_id">' . h(lang('Measurement ID')) . '</label>
					' . $measurement_line . '
				</div>
				<div>
					<label class="pg-field-label" for="ga4_mp_api_secret">' . h(lang('API secret')) . '</label>
					<input type="password" class="pg-input font-monospace" id="ga4_mp_api_secret" name="api_secret" value="" maxlength="100" autocomplete="new-password" spellcheck="false"
						placeholder="' . h(($stored['secret'] !== '') ? lang('Stored. Leave empty to keep it.') : '') . '">
					<div class="pg-field-help">' . h(lang('Google Analytics › Admin › Data streams › your stream › Measurement Protocol API secrets › Create. It is stored encrypted and never shown again.')) . '</div>
				</div>
				' . pg_ad_conversion_choices_fields('ga4_mp', pg_ad_conversion_ga4_events(), $stored, $notice_on) . '
				<div class="d-flex flex-wrap align-items-center gap-3">
					<button type="submit" name="submit_save" value="1" class="pg-button pg-button-sm pg-button-fill"' . ($can_seal ? '' : ' disabled') . '>' . h(lang('Save')) . '</button>
					<button type="submit" name="submit_try" value="1" class="pg-button pg-button-sm pg-button-quiet"' . ($can_seal ? '' : ' disabled') . '>' . h(lang('Send a test event')) . '</button>
				</div>
			</form>
		</section>';

        if ($stored['secret'] !== '') {
            $page['status_extra'] = '
			<div class="mt-4">
				<button type="button" class="pg-button pg-button-sm pg-button-danger connections-action" data-action="ga4_mp_remove" data-id="0"
					data-confirm="' . h(lang('GA4 Measurement Protocol is disconnected: no event goes to Google Analytics, and the events waiting are given up.')) . '">
					<i class="bi bi-x-circle me-1" aria-hidden="true"></i>' . h(lang('Disconnect')) . '</button>
			</div>';
        }
    }

    $page['uses'] = '
				<div class="pg-card-inner-row connections-row">
					<span class="connections-row-main">
						<span class="connections-row-name">' . h(lang('Purchases and refunds sent from the server')) . '</span>
						<span class="pg-text-meta d-block">' . h(lang('On this page')) . '</span>
					</span>
					<span class="connections-row-side">' . connections_state_pill(pg_ad_conversion_ga4_connected() ? 'connected' : 'not_connected') . '</span>
				</div>';

    return $page;
}
