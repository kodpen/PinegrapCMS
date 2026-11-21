<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Ad conversions: what happened to the events (ad_conversion_events). Each
 * service's Connections page shows its last 50 events and, per day, how many
 * were sent, how many failed and the share sent with the person; the general
 * job's operations alerts tell when a service refused every event of a day;
 * GET /reports/ad-conversions answers the same counts for 30 days with the
 * last errors.
 *
 * Read only: nothing here sends or changes a row. A row never holds a plain
 * e-mail or phone, and its ids (browser and click ids) are never read here,
 * so the log shows no personal data, only whether the event carried the
 * person.
 *
 * Days are the site's days: a row's created_at moved by the site zone's
 * offset at the time of reading, so a day next to a clock change may count an
 * hour's events on its neighbour.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// The events a service's page lists.
define('PG_AD_CONVERSION_LOG_ROWS', 50);

// The days of counts a service's page shows, and the report answers.
define('PG_AD_CONVERSION_LOG_DAYS', 7);
define('PG_AD_CONVERSION_REPORT_DAYS', 30);

// The last errors the report answers per service.
define('PG_AD_CONVERSION_REPORT_ERRORS', 10);

/**
 * A service's name, as the services write it.
 *
 * @param string $service
 * @return string
 */
function pg_ad_conversion_service_name($service)
{
    $names = array(
        'meta_capi' => 'Meta Conversions API',
        'ga4_mp' => 'GA4 Measurement Protocol',
        'tiktok_events' => 'TikTok Events API',
    );

    return isset($names[$service]) ? $names[$service] : (string) $service;
}

/**
 * An event's name in the log, in the panel's language.
 *
 * @param string $event
 * @return string
 */
function pg_ad_conversion_log_event_label($event)
{
    switch ((string) $event) {
        case 'purchase':
            return lang('Purchase');
        case 'refund':
            return lang('Refund');
        case 'lead':
            return lang('Lead from a form');
        case 'complete_registration':
            return lang('Customer account opened');
        case 'add_to_cart':
            return lang('Add to cart');
        case 'begin_checkout':
            return lang('Begin checkout');
        default:
            return (string) $event;
    }
}

/**
 * An event's icon in the log.
 *
 * @param string $event
 * @return string a Bootstrap Icons class
 */
function pg_ad_conversion_log_event_icon($event)
{
    $icons = array(
        'purchase' => 'bi-bag',
        'refund' => 'bi-arrow-counterclockwise',
        'lead' => 'bi-ui-checks',
        'complete_registration' => 'bi-person-plus',
        'add_to_cart' => 'bi-cart-plus',
        'begin_checkout' => 'bi-credit-card',
    );

    return isset($icons[$event]) ? $icons[$event] : 'bi-send';
}

/**
 * A row's state: its words and its pill's tone. Pure but for lang().
 *
 * Waiting for the order and due are neutral (gray): nobody has to act. Sent
 * is teal, refused or given up rose.
 *
 * @param int $status
 * @param int $attempts
 * @return array state (waiting, due, retrying, sent, refused), label, tone
 */
function pg_ad_conversion_log_state($status, $attempts)
{
    switch ((int) $status) {
        case 0:
            return array('state' => 'waiting', 'label' => lang('Waiting for the order'), 'tone' => 'gray');
        case 1:
            return ((int) $attempts > 0)
                ? array('state' => 'retrying', 'label' => lang('Tried again later'), 'tone' => 'gray')
                : array('state' => 'due', 'label' => lang('Waiting to be sent'), 'tone' => 'gray');
        case 2:
            return array('state' => 'sent', 'label' => lang('Sent'), 'tone' => 'teal');
        default:
            return array('state' => 'refused', 'label' => lang('Refused'), 'tone' => 'rose');
    }
}

/**
 * The order number a row's detail names, '' when none. Pure.
 *
 * @param mixed $detail the row's detail JSON
 * @return string
 */
function pg_ad_conversion_log_order_number($detail)
{
    $data = is_string($detail) ? json_decode($detail, true) : null;

    if (!is_array($data) || !isset($data['order_number']) || !is_scalar($data['order_number'])) {
        return '';
    }

    return (string) $data['order_number'];
}

/**
 * A row as the log shows it: no ids, no event body. Pure but for lang().
 *
 * @param array $row a row of ad_conversion_events
 * @return array id, event, event_label, order_id, order_number, person
 *               (bool), state, label, tone, reason, at
 */
function pg_ad_conversion_log_item(array $row)
{
    $status = (int) ($row['status'] ?? 0);
    $attempts = (int) ($row['attempts'] ?? 0);
    $state = pg_ad_conversion_log_state($status, $attempts);
    $error = trim((string) ($row['last_error'] ?? ''));

    // The reason is told for a refusal, and for a try that failed and waits
    // to be tried again; a sent row keeps none.
    $reason = (($status === 3) || (($status === 1) && ($attempts > 0))) ? $error : '';

    if (($reason === '') && ($status === 3)) {
        $code = (int) ($row['last_status_code'] ?? 0);
        $reason = ($code > 0) ? 'HTTP ' . $code : '';
    }

    $sent_at = (int) ($row['sent_at'] ?? 0);

    return array(
        'id' => (int) ($row['id'] ?? 0),
        'event' => (string) ($row['event'] ?? ''),
        'event_label' => pg_ad_conversion_log_event_label($row['event'] ?? ''),
        'order_id' => (int) ($row['order_id'] ?? 0),
        'order_number' => pg_ad_conversion_log_order_number($row['detail'] ?? null),
        'person' => !empty($row['person']),
        'state' => $state['state'],
        'label' => $state['label'],
        'tone' => $state['tone'],
        'reason' => $reason,
        'at' => (($status === 2) && ($sent_at > 0)) ? $sent_at : (int) ($row['created_at'] ?? 0),
    );
}

/**
 * The day number (days since 1970-01-01 in the site's zone) of a time. Pure.
 *
 * @param int $time
 * @param int $offset the site zone's offset from UTC, in seconds
 * @return int
 */
function pg_ad_conversion_log_day($time, $offset)
{
    return (int) floor(((int) $time + (int) $offset) / 86400);
}

/**
 * The daily counts from grouped rows, newest day first, every day of the
 * window present. Pure.
 *
 * @param array $groups rows of day (day number), status (2 sent, 3 refused),
 *                      person (0 or 1), n (rows)
 * @param int   $last   the newest day number of the window
 * @param int   $days   how many days the window holds
 * @return array list of day (Y-m-d), sent, failed, sent_with_person, share
 *               (percent of the sent events that carried the person, null
 *               with none sent)
 */
function pg_ad_conversion_daily_counts(array $groups, $last, $days)
{
    $last = (int) $last;
    $first = $last - max(1, (int) $days) + 1;
    $counts = array();

    for ($day = $last; $day >= $first; $day--) {
        $counts[$day] = array('day' => gmdate('Y-m-d', $day * 86400), 'sent' => 0, 'failed' => 0, 'sent_with_person' => 0, 'share' => null);
    }

    foreach ($groups as $group) {
        $day = (int) ($group['day'] ?? 0);
        $n = (int) ($group['n'] ?? 0);

        if (!isset($counts[$day]) || ($n <= 0)) {
            continue;
        }

        $status = (int) ($group['status'] ?? 0);

        if ($status === 2) {
            $counts[$day]['sent'] += $n;

            if (!empty($group['person'])) {
                $counts[$day]['sent_with_person'] += $n;
            }
        } elseif ($status === 3) {
            $counts[$day]['failed'] += $n;
        }
    }

    foreach ($counts as $day => $count) {
        if ($count['sent'] > 0) {
            $counts[$day]['share'] = (int) round(100 * $count['sent_with_person'] / $count['sent']);
        }
    }

    return array_values($counts);
}

/**
 * The counts of a window added up. Pure.
 *
 * @param array $counts pg_ad_conversion_daily_counts()
 * @return array sent, failed, sent_with_person, share
 */
function pg_ad_conversion_counts_total(array $counts)
{
    $total = array('sent' => 0, 'failed' => 0, 'sent_with_person' => 0, 'share' => null);

    foreach ($counts as $count) {
        $total['sent'] += (int) $count['sent'];
        $total['failed'] += (int) $count['failed'];
        $total['sent_with_person'] += (int) $count['sent_with_person'];
    }

    if ($total['sent'] > 0) {
        $total['share'] = (int) round(100 * $total['sent_with_person'] / $total['sent']);
    }

    return $total;
}

/**
 * Whether a day's counts are a service that refused every event. Pure.
 *
 * @param array $count one day of pg_ad_conversion_daily_counts()
 * @return bool
 */
function pg_ad_conversion_refused_all($count)
{
    return is_array($count) && ((int) ($count['failed'] ?? 0) > 0) && ((int) ($count['sent'] ?? 0) === 0);
}

/**
 * The newest rows of a service, as the log shows them.
 *
 * @param string $service
 * @param int    $limit
 * @return array list of pg_ad_conversion_log_item()
 */
function pg_ad_conversion_log_read($service, $limit = PG_AD_CONVERSION_LOG_ROWS)
{
    $items = array();

    if (!pg_ad_conversions_ready()) {
        return $items;
    }

    $result = db_q("SELECT id, event, order_id, detail, person, status, attempts, last_status_code, last_error, created_at, sent_at
        FROM ad_conversion_events WHERE service = ? ORDER BY created_at DESC, id DESC LIMIT " . max(1, (int) $limit), array((string) $service));

    while ($result && ($row = mysqli_fetch_assoc($result))) {
        $items[] = pg_ad_conversion_log_item($row);
    }

    return $items;
}

/**
 * The grouped rows of the last days, per service: sent and refused rows by
 * day and person.
 *
 * @param int $now
 * @param int $days
 * @return array service => rows of day, status, person, n; and the window's
 *               newest day under the key '' (an int)
 */
function pg_ad_conversion_groups_read($now, $days)
{
    $offset = (int) date('Z', (int) $now);
    $last = pg_ad_conversion_log_day($now, $offset);
    $since = (($last - max(1, (int) $days) + 1) * 86400) - $offset;
    $groups = array('' => $last);

    if (!pg_ad_conversions_ready()) {
        return $groups;
    }

    // The (service, created_at) key reads the window; the offset is a whole
    // number written into the expression, never a value from outside.
    $result = db_q("SELECT service, FLOOR((created_at + " . $offset . ") / 86400) AS day, status, person, COUNT(*) AS n
        FROM ad_conversion_events WHERE created_at >= ? AND status IN (2, 3)
        GROUP BY service, day, status, person", array($since));

    while ($result && ($row = mysqli_fetch_assoc($result))) {
        $groups[(string) $row['service']][] = $row;
    }

    return $groups;
}

/**
 * The newest refusals of a service: when, which event, the service's words.
 *
 * @param string $service
 * @param int    $limit
 * @return array list of at, event, status_code, error
 */
function pg_ad_conversion_errors_read($service, $limit = PG_AD_CONVERSION_REPORT_ERRORS)
{
    $errors = array();

    if (!pg_ad_conversions_ready()) {
        return $errors;
    }

    $result = db_q("SELECT event, last_status_code, last_error, created_at FROM ad_conversion_events
        WHERE service = ? AND status = 3 ORDER BY created_at DESC, id DESC LIMIT " . max(1, (int) $limit), array((string) $service));

    while ($result && ($row = mysqli_fetch_assoc($result))) {
        $errors[] = array(
            'at' => (int) $row['created_at'],
            'event' => (string) $row['event'],
            'status_code' => (int) $row['last_status_code'],
            'error' => (string) $row['last_error'],
        );
    }

    return $errors;
}

// ----------------------------------------------------------------------------
// The operations alert
// ----------------------------------------------------------------------------

/**
 * The alert's sentence. Pure but for lang().
 *
 * @param string $service
 * @param array  $count   the day's counts
 * @param string $reason  the last refusal's words
 * @return string
 */
function pg_ad_conversion_refused_sentence($service, array $count, $reason)
{
    $sentence = lang(array(
        'string' => '{var:1} refused all {var:2} events of {var:3}.',
        'vars' => array(pg_ad_conversion_service_name($service), (int) $count['failed'], (string) $count['day']),
    ));

    $reason = trim((string) $reason);

    if ($reason !== '') {
        $sentence .= ' ' . lang(array('string' => 'Last reason: {var:1}', 'vars' => mb_substr($reason, 0, 200)));
    }

    return $sentence;
}

/**
 * The operations alerts' source (pg_ops_alert_sources()): once a day, for
 * yesterday, each connected service that refused every event it tried.
 *
 * The day is claimed in the alert state file first, so the read runs once a
 * day; the alert itself is told once a day per service (its subject).
 *
 * @param int $now
 * @return string[] the codes told
 */
function pg_ad_conversion_check_refused($now)
{
    $found = array();

    if (!function_exists('pg_ad_conversions_ready') || !pg_ad_conversions_ready()) {
        return $found;
    }

    $now = (int) $now;
    $day = pg_ad_conversion_log_day($now, (int) date('Z', $now)) - 1;
    $state = pg_ops_alert_state();

    if (is_array($state) && isset($state['ad_conversions']) && ((int) $state['ad_conversions'] >= $day)) {
        return $found;
    }

    $claimed = true;

    $state = pg_ops_alert_state(function ($state) use ($day, &$claimed) {

        if (isset($state['ad_conversions']) && ((int) $state['ad_conversions'] >= $day)) {
            $claimed = false;
            return $state;
        }

        $state['ad_conversions'] = $day;

        return $state;
    });

    if (($state !== null) && !$claimed) {
        return $found;
    }

    $settings = pg_ad_conversion_settings_all();

    // Yesterday alone: the window of two days ending today, its older day.
    $groups = pg_ad_conversion_groups_read($now, 2);

    foreach (pg_ad_conversion_services() as $service) {

        if (empty($settings[$service]['connected']) || empty($groups[$service])) {
            continue;
        }

        $counts = pg_ad_conversion_daily_counts($groups[$service], $groups[''], 2);
        $yesterday = $counts[1];

        if (!pg_ad_conversion_refused_all($yesterday)) {
            continue;
        }

        $errors = pg_ad_conversion_errors_read($service, 1);
        $reason = $errors ? $errors[0]['error'] : '';

        pg_ops_alert('ad_events_refused', pg_ad_conversion_refused_sentence($service, $yesterday, $reason), $service);
        $found[] = 'ad_events_refused';
    }

    return $found;
}

// ----------------------------------------------------------------------------
// The service's page
// ----------------------------------------------------------------------------

/**
 * The "Daily counts" rows. Pure but for lang() and h().
 *
 * @param array $counts pg_ad_conversion_daily_counts()
 * @return string
 */
function pg_ad_conversion_counts_html(array $counts)
{
    $rows = '';

    foreach ($counts as $count) {
        $line = lang(array('string' => '{var:1} sent, {var:2} failed', 'vars' => array((int) $count['sent'], (int) $count['failed'])));

        if ($count['share'] !== null) {
            $line .= ' · ' . lang(array('string' => '{var:1}% with the person', 'vars' => (int) $count['share']));
        }

        $pill = '';

        if (pg_ad_conversion_refused_all($count)) {
            $pill = '<span class="pg-pill pg-tone-rose">' . h(lang('All refused')) . '</span>';
        }

        $rows .= '
				<div class="pg-card-inner-row connections-row">
					<span class="connections-row-main">
						<span class="connections-row-name">' . h(get_absolute_time(array('timestamp' => strtotime($count['day'] . ' 12:00:00'), 'type' => 'date', 'format' => 'plain_text', 'size' => 'short'))) . '</span>
						<span class="pg-text-meta d-block">' . h($line) . '</span>
					</span>
					<span class="connections-row-side">' . $pill . '</span>
				</div>';
    }

    return $rows;
}

/**
 * The log's rows. Pure but for lang() and h().
 *
 * @param array $items list of pg_ad_conversion_log_item()
 * @return string
 */
function pg_ad_conversion_log_rows_html(array $items)
{
    $rows = '';

    foreach ($items as $item) {
        $order = '';

        if ($item['order_id'] > 0) {
            $order = ' · <a href="view_order.php?id=' . (int) $item['order_id'] . '">'
                . h(($item['order_number'] !== '') ? lang(array('string' => 'Order {var:1}', 'vars' => $item['order_number'])) : lang('Order')) . '</a>';
        }

        $reason = ($item['reason'] !== '')
            ? '<div class="pg-list-row-text text-break">' . h(mb_substr($item['reason'], 0, 300)) . '</div>'
            : '';

        $rows .= '
				<div class="pg-list-row is-read">
					<span class="pg-icon-tile pg-tone-gray" aria-hidden="true"><span class="bi ' . h(pg_ad_conversion_log_event_icon($item['event'])) . '"></span></span>
					<div class="pg-list-row-main">
						<div class="pg-list-row-head">
							<span class="pg-list-row-title">' . h($item['event_label']) . '</span>
							<span class="pg-list-row-time">' . h(get_relative_time(array('timestamp' => $item['at'], 'format' => 'plain_text'))) . '</span>
						</div>' . $reason . '
						<div class="pg-list-row-actions"><span class="pg-pill pg-tone-' . h($item['tone']) . '">' . h($item['label']) . '</span>
							<span class="pg-list-row-place">' . h($item['person'] ? lang('With the person') : lang('Without the person')) . $order . '</span></div>
					</div>
					<div class="pg-list-row-side"></div>
				</div>';
    }

    return $rows;
}

/**
 * The "Events" card of a service's Connections page: the daily counts of the
 * last week and the last 50 events.
 *
 * @param string $service
 * @return string
 */
function pg_ad_conversion_log_card($service)
{
    $now = time();
    $groups = pg_ad_conversion_groups_read($now, PG_AD_CONVERSION_LOG_DAYS);
    $counts = pg_ad_conversion_daily_counts(isset($groups[$service]) ? $groups[$service] : array(), $groups[''], PG_AD_CONVERSION_LOG_DAYS);
    $items = pg_ad_conversion_log_read($service);

    if ($items === array()) {
        $body = '
			<div class="pg-card-inner"><div class="ws-empty py-5"><i class="bi bi-send" aria-hidden="true"></i>' . h(lang('No event yet.')) . '</div></div>';
    } else {
        $body = '
			<h3 class="pg-field-label mb-2">' . h(lang('Daily counts')) . '</h3>
			<div class="pg-card-inner mb-5">' . pg_ad_conversion_counts_html($counts) . '
			</div>
			<h3 class="pg-field-label mb-2">' . h(lang('Last events')) . '</h3>
			<div class="pg-card-inner">' . pg_ad_conversion_log_rows_html($items) . '
			</div>';
    }

    return '
		<section class="pg-card p-5 mb-6" id="connections-events" aria-labelledby="connections-page-events">
			<h2 class="pg-title-section mb-1" id="connections-page-events">' . h(lang('Events')) . '</h2>
			<p class="pg-card-sub mb-4">' . h(lang('What was sent in the last week, and the last 50 events. No personal data is shown.')) . '</p>' . $body . '
		</section>';
}

// ----------------------------------------------------------------------------
// The report
// ----------------------------------------------------------------------------

/**
 * GET /reports/ad-conversions: per service its state, the counts of the
 * last 30 days and the last refusals.
 *
 * @param int $now
 * @return array services: list of service, name, connected, events, waiting,
 *               totals, days, errors
 */
function pg_ad_conversion_report($now)
{
    $settings = pg_ad_conversion_settings_all();
    $groups = pg_ad_conversion_groups_read($now, PG_AD_CONVERSION_REPORT_DAYS);
    $waiting = array();

    if (pg_ad_conversions_ready()) {
        $result = db_q("SELECT service, COUNT(*) AS n FROM ad_conversion_events WHERE status = 1 GROUP BY service", array());

        while ($result && ($row = mysqli_fetch_assoc($result))) {
            $waiting[(string) $row['service']] = (int) $row['n'];
        }
    }

    $services = array();

    foreach (pg_ad_conversion_services() as $service) {
        $counts = pg_ad_conversion_daily_counts(isset($groups[$service]) ? $groups[$service] : array(), $groups[''], PG_AD_CONVERSION_REPORT_DAYS);

        $services[] = array(
            'service' => $service,
            'name' => pg_ad_conversion_service_name($service),
            'connected' => !empty($settings[$service]['connected']),
            'events' => isset($settings[$service]['events']) ? array_values((array) $settings[$service]['events']) : array(),
            'waiting' => isset($waiting[$service]) ? $waiting[$service] : 0,
            'totals' => pg_ad_conversion_counts_total($counts),
            'days' => $counts,
            'errors' => pg_ad_conversion_errors_read($service),
        );
    }

    return array('services' => $services);
}
