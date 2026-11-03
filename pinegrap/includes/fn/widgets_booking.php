<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Visual Page Editor - the Booking system widget (kind 'booking'): the
 * visitor picks a service, a staff member or "anyone", a day of a month
 * calendar that shows only the days with a free slot, then a time, and the
 * slot is held for them while they fill in their details.
 *
 * The page draws the services (one loop row each) and carries the staff of
 * each service in the widget's data-pg-booking settings, the same for every
 * visitor, so the page can come from the page cache. The days and times come
 * from booking_slots.php, which reads the bookings and the staff members'
 * Workspace calendars on every request; assets/js/pg_booking.js draws them
 * on pg_datetime.js's calendar.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/* ---------------------------------------------------------------------------
   Pure helpers
   --------------------------------------------------------------------------- */

/**
 * The booking policy: how far ahead and how soon a slot may be booked, how
 * long a hold lives. The defaults until Settings › Bookings stores its own;
 * the zone is the site's.
 *
 * @return array zone, lead_minutes, horizon_days, hold_minutes, step_minutes
 *               (0: the service's length)
 */
function pg_booking_policy()
{
    return array(
        'zone'         => date_default_timezone_get(),
        'lead_minutes' => 60,
        'horizon_days' => 60,
        'hold_minutes' => 10,
        'step_minutes' => 0,
    );
}

/**
 * The service ids a staff member takes, from booking_staff.service_ids (a
 * JSON list or an array).
 *
 * @param mixed $value
 * @return int[]
 */
function pg_booking_service_ids($value)
{
    if (is_string($value)) {
        $value = json_decode($value, true);
    }

    $ids = array();

    foreach (is_array($value) ? $value : array() as $id) {
        if (is_numeric($id) && ((int) $id > 0)) {
            $ids[(int) $id] = (int) $id;
        }
    }

    return array_values($ids);
}

/**
 * The staff rows that take a service, in the order given.
 *
 * @param array $staff_rows rows with id, service_ids, enabled
 * @param int   $service_id
 * @return array
 */
function pg_booking_staff_for($staff_rows, $service_id)
{
    $found = array();

    foreach ((array) $staff_rows as $row) {
        if (is_array($row) && !empty($row['enabled']) && in_array((int) $service_id, pg_booking_service_ids(isset($row['service_ids']) ? $row['service_ids'] : ''), true)) {
            $found[] = $row;
        }
    }

    return $found;
}

/**
 * Whether a month is written 'YYYY-MM'.
 *
 * @param mixed $month
 * @return bool
 */
function pg_booking_month_valid($month)
{
    return is_string($month) && (preg_match('/^([0-9]{4})-(0[1-9]|1[0-2])$/', $month) === 1);
}

/**
 * The days of a month, 'YYYY-MM-DD'.
 *
 * @param string $month 'YYYY-MM'
 * @return string[]
 */
function pg_booking_month_dates($month)
{
    if (!pg_booking_month_valid($month)) {
        return array();
    }

    list($year, $number) = array_map('intval', explode('-', $month));
    $days = (int) date('t', mktime(12, 0, 0, $number, 1, $year));
    $dates = array();

    for ($day = 1; $day <= $days; $day++) {
        $dates[] = sprintf('%04d-%02d-%02d', $year, $number, $day);
    }

    return $dates;
}

/**
 * The free times of a day for a service over several staff members: each
 * start once, with the staff member who takes it. When more than one is
 * free at a start, the one with the fewest busy minutes that day takes it
 * ("anyone" picks the freest), the lowest id on a tie.
 *
 * @param array  $service as in pg_booking_slots()
 * @param array  $staff   rows with id, hours, exceptions
 * @param string $day     'YYYY-MM-DD'
 * @param array  $busy    staff id => list of array(start, end)
 * @param int    $now
 * @param array  $policy  as in pg_booking_slots()
 * @return array list of array('start' => unix, 'staff_id' => id), ascending
 */
function pg_booking_day_times($service, $staff, $day, $busy, $now, $policy)
{
    $by_start = array();
    $load = array();

    try {
        $zone = new DateTimeZone(!empty($policy['zone']) ? (string) $policy['zone'] : date_default_timezone_get());
        $from = (new DateTime($day . ' 00:00:00', $zone))->getTimestamp();
        $until = (new DateTime($day . ' 00:00:00', $zone))->modify('+1 day')->getTimestamp();
    } catch (Exception $exception) {
        return array();
    }

    foreach ((array) $staff as $row) {
        $id = (int) $row['id'];
        $ranges = isset($busy[$id]) ? (array) $busy[$id] : array();
        $minutes = 0;

        foreach ($ranges as $range) {
            $range = array_values((array) $range);
            if (count($range) >= 2) {
                $minutes += max(0, min((int) $range[1], $until) - max((int) $range[0], $from));
            }
        }

        $load[$id] = $minutes;

        foreach (pg_booking_slots($service, $row, $day, $ranges, $now, $policy) as $start) {
            $by_start[$start][] = $id;
        }
    }

    ksort($by_start);
    $times = array();

    foreach ($by_start as $start => $ids) {
        usort($ids, function ($a, $b) use ($load) {
            return ($load[$a] - $load[$b]) ?: ($a - $b);
        });
        $times[] = array('start' => (int) $start, 'staff_id' => $ids[0]);
    }

    return $times;
}

/**
 * The days of a month with at least one free time, for a service over
 * several staff members. Days before today or past the horizon are not
 * walked.
 *
 * @param array  $service
 * @param array  $staff
 * @param string $month  'YYYY-MM'
 * @param array  $busy   staff id => ranges
 * @param int    $now
 * @param array  $policy
 * @return string[] 'YYYY-MM-DD'
 */
function pg_booking_month_days($service, $staff, $month, $busy, $now, $policy)
{
    try {
        $zone = new DateTimeZone(!empty($policy['zone']) ? (string) $policy['zone'] : date_default_timezone_get());
        $today = (new DateTime('@' . (int) $now))->setTimezone($zone)->format('Y-m-d');
        $last = !empty($policy['horizon_days'])
            ? (new DateTime('@' . ((int) $now + ((int) $policy['horizon_days'] * 86400))))->setTimezone($zone)->format('Y-m-d')
            : null;
    } catch (Exception $exception) {
        return array();
    }

    $days = array();

    foreach (pg_booking_month_dates($month) as $date) {
        if (($date < $today) || (($last !== null) && ($date > $last))) {
            continue;
        }

        foreach ((array) $staff as $row) {
            $id = (int) $row['id'];
            if (pg_booking_slots($service, $row, $date, isset($busy[$id]) ? $busy[$id] : array(), $now, $policy)) {
                $days[] = $date;
                break;
            }
        }
    }

    return $days;
}

/**
 * The busy ranges of Workspace calendar events, per staff member. An
 * all-day event, stored up to 23:59:59 of its last day, takes that whole
 * second too.
 *
 * @param array $events     rows with starts_at, ends_at, all_day, scope,
 *                          department_id, people (user ids)
 * @param array $staff      rows with id, user_id
 * @param array $departments user id => department ids
 * @return array staff id => list of array(start, end)
 */
function pg_booking_ws_busy($events, $staff, $departments)
{
    $busy = array();

    foreach ((array) $staff as $row) {
        $user_id = (int) $row['user_id'];
        $own = isset($departments[$user_id]) ? array_map('intval', (array) $departments[$user_id]) : array();

        foreach ((array) $events as $event) {
            $scope = isset($event['scope']) ? (string) $event['scope'] : 'people';
            $people = isset($event['people']) ? array_map('intval', (array) $event['people']) : array();

            if (($scope === 'company')
                || (($scope === 'department') && in_array((int) $event['department_id'], $own, true))
                || (($scope === 'people') && in_array($user_id, $people, true))) {
                $end = (int) $event['ends_at'] + (!empty($event['all_day']) ? 1 : 0);
                $busy[(int) $row['id']][] = array((int) $event['starts_at'], $end);
            }
        }
    }

    return $busy;
}

/**
 * The staff of every service as the widget's script reads them: service id
 * => list of array(id, name), and whether "anyone" is offered (more than
 * one staff member).
 *
 * @param array $services rows with id
 * @param array $staff    rows with id, name, service_ids, enabled
 * @return array
 */
function pg_booking_widget_staff_map($services, $staff)
{
    $map = array();

    foreach ((array) $services as $service) {
        $list = array();

        foreach (pg_booking_staff_for($staff, (int) $service['id']) as $row) {
            $list[] = array('id' => (int) $row['id'], 'name' => (string) $row['name']);
        }

        $map[(string) (int) $service['id']] = $list;
    }

    return $map;
}

/**
 * The parts of the widget the script works with, by their binding: a
 * container's section binding names the part, a button's action binding
 * what it does. Each becomes a data-pg-booking-* attribute; a service
 * button carries its row's service id, and a button gets type="button" so
 * it never submits a form around the widget.
 *
 * @param array $node tree node, changed in place
 * @return void
 */
function pg_booking_widget_mark(&$node)
{
    if (!is_array($node)) {
        return;
    }

    $bindings = (isset($node['props']['_bindings']) && is_array($node['props']['_bindings'])) ? $node['props']['_bindings'] : array();
    $attrs = array();

    if (isset($bindings['section']) && in_array($bindings['section'], pg_booking_widget_parts(), true)) {
        $attrs[] = array('name' => 'data-pg-booking-part', 'value' => substr((string) $bindings['section'], 8));
    }

    if (isset($bindings['action']) && in_array($bindings['action'], array('booking_service', 'booking_back'), true)) {
        $attrs[] = array('name' => 'data-pg-booking-action', 'value' => substr((string) $bindings['action'], 8));

        if ($bindings['action'] === 'booking_service') {
            $attrs[] = array('name' => 'data-pg-booking-service', 'value' => '^^__service_id^^');
        }

        if (isset($node['props']['tag']) && (strtolower((string) $node['props']['tag']) === 'button')) {
            $attrs[] = array('name' => 'type', 'value' => 'button');
        } elseif (isset($node['type']) && ($node['type'] === 'component')) {
            $node['props']['btnElement'] = 'button';
            $node['props']['btnType'] = 'button';
            $node['props']['href'] = '';
        }
    }

    if ($attrs) {
        $managed = array();
        foreach ($attrs as $attr) {
            $managed[$attr['name']] = true;
        }

        $kept = array();
        foreach ((isset($node['props']['_attrs']) && is_array($node['props']['_attrs'])) ? $node['props']['_attrs'] : array() as $attr) {
            if (is_array($attr) && isset($attr['name']) && !isset($managed[strtolower((string) $attr['name'])])) {
                $kept[] = $attr;
            }
        }

        $node['props']['_attrs'] = array_merge($kept, $attrs);
    }

    if (!empty($node['children']) && is_array($node['children'])) {
        foreach ($node['children'] as &$child) {
            pg_booking_widget_mark($child);
        }
        unset($child);
    }
}

/**
 * The section bindings of the widget's parts.
 *
 * @return string[]
 */
function pg_booking_widget_parts()
{
    return array('booking_step_service', 'booking_step_staff', 'booking_step_when', 'booking_step_details',
        'booking_staff', 'booking_calendar', 'booking_times', 'booking_summary', 'booking_status');
}

/**
 * One loop row of tokens per service.
 *
 * @param array $services booking_services rows
 * @return array
 */
function pg_booking_widget_rows($services)
{
    $rows = array();

    foreach ((array) $services as $service) {
        $price = (int) $service['price'];
        $rows[] = array(
            '__service_id'          => (string) (int) $service['id'],
            '__service_name'        => (string) $service['name'],
            '__service_description' => (string) $service['description'],
            '__service_length'      => lang('{var:1} min', (int) $service['length_minutes']),
            '__service_price'       => (($price > 0) && function_exists('prepare_price_for_output')) ? strip_tags(prepare_price_for_output($price, false, 0, 'plain_text')) : '',
        );
    }

    return $rows;
}

/* ---------------------------------------------------------------------------
   Reading
   --------------------------------------------------------------------------- */

/**
 * The services a visitor may book: switched on, with a length and at least
 * one staff member who takes them.
 *
 * @return array list of array(services rows, staff rows with name)
 */
function pg_booking_widget_data()
{
    if (!pg_booking_ready()) {
        return array(array(), array());
    }

    $staff = (array) db_items(
        "SELECT booking_staff.*, user.user_username, contacts.first_name, contacts.last_name
        FROM booking_staff
        LEFT JOIN user ON user.user_id = booking_staff.user_id
        LEFT JOIN contacts ON contacts.id = user.user_contact
        WHERE booking_staff.enabled = 1 AND user.user_id IS NOT NULL
        ORDER BY booking_staff.id");

    foreach ($staff as $index => $row) {
        $staff[$index]['name'] = pg_user_display_name($row['first_name'], $row['last_name'], $row['user_username']);
    }

    $services = array();

    foreach ((array) db_items("SELECT * FROM booking_services WHERE enabled = 1 AND length_minutes > 0 ORDER BY sort_order, name, id") as $service) {
        if (pg_booking_staff_for($staff, (int) $service['id'])) {
            $services[] = $service;
        }
    }

    return array($services, $staff);
}

/**
 * The busy ranges of staff members between two times: their bookings (a
 * hold past its time and a cancelled booking take none) and the events of
 * their Workspace calendars, synced ones included.
 *
 * @param array $staff rows with id, user_id
 * @param int   $from
 * @param int   $until
 * @param int   $now
 * @return array staff id => list of array(start, end)
 */
function pg_booking_busy_between($staff, $from, $until, $now)
{
    $ids = array();
    $user_ids = array();

    foreach ((array) $staff as $row) {
        $ids[] = (int) $row['id'];
        $user_ids[] = (int) $row['user_id'];
    }

    if (!$ids) {
        return array();
    }

    $busy = array();

    foreach ((array) db_items(
        "SELECT staff_id, start_at, end_at, status, hold_until FROM bookings
        WHERE staff_id IN (" . implode(',', $ids) . ")
            AND start_at < '" . (int) $until . "' AND end_at > '" . (int) $from . "'
            AND status <> 'cancelled'") as $row) {
        foreach (pg_booking_busy_from_rows(array($row), $now) as $range) {
            $busy[(int) $row['staff_id']][] = $range;
        }
    }

    $events = (array) db_items(
        "SELECT id, scope, department_id, starts_at, ends_at, all_day FROM ws_events
        WHERE starts_at < '" . (int) $until . "' AND ends_at >= '" . (int) $from . "'");

    if ($events) {
        $event_ids = array();
        foreach ($events as $event) {
            $event_ids[] = (int) $event['id'];
        }

        $people = array();
        foreach ((array) db_items("SELECT event_id, user_id FROM ws_event_people WHERE event_id IN (" . implode(',', $event_ids) . ")") as $row) {
            $people[(int) $row['event_id']][] = (int) $row['user_id'];
        }

        foreach ($events as $index => $event) {
            $events[$index]['people'] = isset($people[(int) $event['id']]) ? $people[(int) $event['id']] : array();
        }

        $departments = array();
        foreach ((array) db_items("SELECT user_id, department_id FROM ws_department_members WHERE user_id IN (" . implode(',', $user_ids) . ")") as $row) {
            $departments[(int) $row['user_id']][] = (int) $row['department_id'];
        }

        foreach (pg_booking_ws_busy($events, $staff, $departments) as $staff_id => $ranges) {
            $busy[$staff_id] = array_merge(isset($busy[$staff_id]) ? $busy[$staff_id] : array(), $ranges);
        }
    }

    return $busy;
}

/* ---------------------------------------------------------------------------
   Drawing
   --------------------------------------------------------------------------- */

/**
 * The settings the widget's script reads from its data-pg-booking
 * attribute: the endpoint, the staff of each service, the policy's hold
 * minutes and the words it may show. The same for every visitor.
 *
 * @param array $services
 * @param array $staff
 * @return array
 */
function pg_booking_widget_settings($services, $staff)
{
    $policy = pg_booking_policy();

    return array(
        'url'   => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/booking_slots.php',
        'staff' => pg_booking_widget_staff_map($services, $staff),
        'zone'  => (string) $policy['zone'],
        'hold'  => (int) $policy['hold_minutes'],
        'words' => array(
            'anyone'   => lang('Anyone available'),
            'noTimes'  => lang('No time is free on this day. Please pick another day.'),
            'noDays'   => lang('No day is free in this month.'),
            'loading'  => lang('Loading…'),
            'taken'    => lang('This time was just taken. Please pick another one.'),
            'held'     => lang('We are keeping {time} for you for {minutes} minutes.'),
            'failed'   => lang('Something went wrong. Please try again.'),
            'times'    => lang('Free times on {day}'),
            'zoneNote' => lang('Times are in {zone}.'),
        ),
    );
}

// Render a 'booking' system widget.
//
// loop_area: one row per service a visitor may book (pg_booking_widget_rows()).
// Row tokens: ^^__service_id^^, ^^__service_name^^, ^^__service_description^^,
// ^^__service_length^^ ("60 min"), ^^__service_price^^ ('' when free).
// Flags: has_services, no_services; row: service_has_price,
// service_has_description.
// Parts by section binding (pg_booking_widget_parts()) and buttons by action
// binding (booking_service, booking_back), drawn by assets/js/pg_booking.js.
// $data: for the tests, array(services, staff) in place of the tables.
function _render_system_widget_booking($tree_json, $widget_id, $cfg = array(), $mode = 'preview', $data = null)
{
    static $placement = 0;
    $widget_id = (int) $widget_id;
    if ($tree_json === '' || $tree_json === null) return '';
    $tree = json_decode($tree_json, true);
    if (!is_array($tree)) return '';

    list($services, $staff) = is_array($data) ? $data : pg_booking_widget_data();
    $rows = pg_booking_widget_rows($services);

    _eo_apply_visibility_bindings($tree, array(
        'has_services'            => (bool) $rows,
        'no_services'             => !$rows,
        'service_has_price'       => true,
        'service_has_description' => true,
    ));
    _pg_inject_messages_node($tree, 'booking');
    pg_booking_widget_mark($tree);

    $placed = ++$placement;
    $again  = pg_sw_widget_drawn_before($widget_id) ? $placed : 0;
    $split = _split_widget_tree($tree);
    $loop_rendered = '';

    if ($split['loop_children'] !== null) {
        $row_tree = array('type' => 'root', 'props' => array(), 'children' => $split['loop_children']);
        foreach ($rows as $i => $row) {
            $t = $row_tree;
            _eo_apply_visibility_bindings($t, array(
                'service_has_price'       => $row['__service_price'] !== '',
                'service_has_description' => trim($row['__service_description']) !== '',
            ));
            $html = '';
            foreach ($t['children'] as $child) $html .= _render_tree_node($child, 0, 0);
            $html = pg_sw_sweep_tokens(_pg_sw_fill_tokens(trim($html), pg_sw_escape_token_values($row)));
            $loop_rendered .= pg_sw_uniquify_loop_row_ids($html, $widget_id, $i + 1, $again);
        }
    }

    $static = trim(_render_tree_node($split['static_tree'], 0, 0));
    $static = pg_sw_uniquify_row_ids(pg_sw_sweep_tokens(_pg_sw_fill_tokens($static, pg_sw_escape_token_values(array(
        '__service_total' => (string) count($rows),
    )))), $widget_id, 1000 + $placed);
    $html = str_replace('<!--pg-loop-slot-->', $loop_rendered, $static);

    if (!$rows) {
        return $html;
    }

    $settings = json_encode(pg_booking_widget_settings($services, $staff), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    return '<div data-pg-booking="' . h($settings) . '">' . $html . '</div>';
}

/**
 * The booking widget's script (assets/js/pg_booking.js), for a page whose
 * HTML holds a widget with services (its data-pg-booking settings). Asked
 * once every shared component and system widget is drawn in
 * (get_page_content.php). The calendar comes with the site's date control
 * (pg_site_datetime_inject(), which the same attribute brings).
 *
 * @param string      $content page HTML
 * @param string|null $src     the script's address; null builds it
 * @return string
 */
function pg_booking_widget_inject($content, $src = null)
{
    if (!preg_match('/\sdata-pg-booking="/', $content) || (strpos($content, '/pg_booking.js') !== false)) {
        return $content;
    }

    if ($src === null) {
        $src = OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/js/pg_booking.js?v='
            . @filemtime(PG_FUNCTIONS_DIR . '/assets/js/pg_booking.js');
    }

    $tag = '<script src="' . h($src) . '" defer></script>' . "\n";
    $end = strripos($content, '</body>');

    return ($end === false) ? $content . $tag : substr($content, 0, $end) . $tag . substr($content, $end);
}

/* ---------------------------------------------------------------------------
   The endpoint (booking_slots.php)
   --------------------------------------------------------------------------- */

/**
 * Whether one address has asked too often: reads (days, times) and holds
 * are counted apart, with the firewall's counters, which work with the
 * firewall off. A bot holding every slot meets the hold limit first.
 *
 * @param string $kind 'read' or 'hold'
 * @return int 0, or the window in seconds to wait
 */
function pg_booking_throttled($kind)
{
    if (!function_exists('waf_rate_exceeded') || !function_exists('waf_client_ip') || !function_exists('waf_ip_subject')) {
        return 0;
    }

    $ip = (string) waf_client_ip();

    if (($ip === '') || (function_exists('waf_ip_is_counted') && !waf_ip_is_counted($ip))) {
        return 0;
    }

    $limit = ($kind === 'hold') ? 20 : 120;
    $window = ($kind === 'hold') ? 600 : 300;

    if (!waf_rate_exceeded(waf_ip_subject($ip), 'booking_' . $kind, $limit, $window)) {
        return 0;
    }

    if (function_exists('waf_log_event') && isset(db::$con) && db::$con) {
        waf_log_event('rate', 'rate-booking-' . $kind, 'rate', 5, 'booking_slots.php', $limit . '/' . (int) round($window / 60) . 'min');
    }

    if (function_exists('waf_text_log')) {
        waf_text_log('DENY', $ip, array('status' => 429, 'rule' => 'rate-booking-' . $kind));
    }

    return $window;
}

/**
 * Gives back the slot this session holds, if it still is a hold.
 *
 * @param int $now
 * @return void
 */
function pg_booking_release_session_hold($now)
{
    $id = isset($_SESSION['software']['booking_hold']) ? (int) $_SESSION['software']['booking_hold'] : 0;
    unset($_SESSION['software']['booking_hold']);

    if (($id > 0) && pg_booking_ready()) {
        db_q("DELETE FROM bookings WHERE id = ? AND status = 'held'", array($id));
    }
}

/**
 * A time as the site's clock writes it, in the site's zone.
 *
 * @param int    $time
 * @param string $zone
 * @return string
 */
function pg_booking_time_label($time, $zone)
{
    try {
        $moment = new DateTime('@' . (int) $time);
        $moment->setTimezone(new DateTimeZone($zone));
    } catch (Exception $exception) {
        return '';
    }

    return $moment->format(function_exists('pg_time_format_code') ? pg_time_format_code() : 'H:i');
}

/**
 * Answers a month, day or hold request of the widget.
 *
 * @param array $request decoded body
 * @param int   $now
 * @return array|null null for a request that names no bookable service,
 *                    staff member, month or day
 */
function pg_booking_slots_request($request, $now)
{
    list($services, $staff) = pg_booking_widget_data();
    $service_id = isset($request['service']) ? (int) $request['service'] : 0;
    $service = null;

    foreach ($services as $row) {
        if ((int) $row['id'] === $service_id) {
            $service = $row;
        }
    }

    if ($service === null) {
        return null;
    }

    $staff = pg_booking_staff_for($staff, $service_id);
    $staff_id = isset($request['staff']) ? (int) $request['staff'] : 0;

    if ($staff_id > 0) {
        $staff = array_values(array_filter($staff, function ($row) use ($staff_id) {
            return (int) $row['id'] === $staff_id;
        }));

        if (!$staff) {
            return null;
        }
    }

    $policy = pg_booking_policy();
    $zone = new DateTimeZone($policy['zone']);
    $action = (string) $request['action'];

    if ($action === 'month') {
        $month = isset($request['month']) ? $request['month'] : '';

        if (!pg_booking_month_valid($month)) {
            return null;
        }

        $first = new DateTime($month . '-01 00:00:00', $zone);
        $from = $first->getTimestamp() - 86400;
        $until = $first->modify('+1 month')->getTimestamp() + 86400;

        return array('ok' => true, 'days' => pg_booking_month_days($service, $staff, $month, pg_booking_busy_between($staff, $from, $until, $now), $now, $policy));
    }

    $day = ($action === 'day') ? (isset($request['day']) ? $request['day'] : '') : '';

    if ($action === 'hold') {
        $start = isset($request['start']) ? (int) $request['start'] : 0;

        if (($staff_id <= 0) || ($start <= 0)) {
            return null;
        }

        $day = (new DateTime('@' . $start))->setTimezone($zone)->format('Y-m-d');

        // The slot this session held before is free again, the same time too.
        pg_booking_release_session_hold($now);
    }

    if (!pg_booking_date_valid($day)) {
        return null;
    }

    $midnight = new DateTime($day . ' 00:00:00', $zone);
    $from = $midnight->getTimestamp() - 86400;
    $until = $midnight->modify('+1 day')->getTimestamp() + 86400;
    $times = pg_booking_day_times($service, $staff, $day, pg_booking_busy_between($staff, $from, $until, $now), $now, $policy);

    if ($action === 'day') {
        $list = array();
        foreach ($times as $time) {
            $list[] = array('start' => $time['start'], 'staff' => $time['staff_id'], 'label' => pg_booking_time_label($time['start'], $policy['zone']));
        }

        return array('ok' => true, 'times' => $list);
    }

    // A hold only on a time the visitor could have been offered.
    $offered = false;
    foreach ($times as $time) {
        $offered = $offered || ($time['start'] === $start);
    }

    if (!$offered) {
        return array('ok' => false, 'error' => 'taken', 'message' => lang('This time was just taken. Please pick another one.'));
    }

    $held = pg_booking_hold(array(
        'service'    => $service,
        'service_id' => $service_id,
        'staff_id'   => $staff_id,
        'start_at'   => $start,
    ), $policy['hold_minutes'], $now);

    if ($held['answer'] === 'taken') {
        return array('ok' => false, 'error' => 'taken', 'message' => lang('This time was just taken. Please pick another one.'));
    }

    if ($held['answer'] !== 'ok') {
        return array('ok' => false, 'error' => 'failed', 'message' => lang('Something went wrong. Please try again.'));
    }

    $_SESSION['software']['booking_hold'] = (int) $held['id'];

    return array('ok' => true, 'hold' => array('until' => $now + ((int) $policy['hold_minutes'] * 60), 'label' => pg_booking_time_label($start, $policy['zone'])));
}
