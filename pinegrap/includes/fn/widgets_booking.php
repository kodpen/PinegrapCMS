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
 * The site's booking policy (Settings › Bookings, pg_booking_policy()) with
 * the zone the slot engine works in, the site's.
 *
 * @return array as pg_booking_policy(), plus zone
 */
function pg_booking_widget_policy()
{
    return array('zone' => date_default_timezone_get()) + pg_booking_policy();
}

/**
 * The words for a time another visitor took first: the site's own from
 * Settings › Bookings, else the widget's.
 *
 * @return string
 */
function pg_booking_widget_taken_text()
{
    $texts = function_exists('pg_booking_texts') ? pg_booking_texts() : array();

    return isset($texts['taken']) ? (string) $texts['taken'] : lang('This time was just taken. Please pick another one.');
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
    return array('booking_step_service', 'booking_step_staff', 'booking_step_when', 'booking_step_details', 'booking_step_done',
        'booking_staff', 'booking_calendar', 'booking_times', 'booking_summary', 'booking_form', 'booking_done', 'booking_status');
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
        // A currency symbol stored as an entity (&#8378;) becomes its
        // character here: the renderer escapes every value.
        $rows[] = array(
            '__service_id'          => (string) (int) $service['id'],
            '__service_name'        => (string) $service['name'],
            '__service_description' => (string) $service['description'],
            '__service_length'      => lang('{var:1} min', (int) $service['length_minutes']),
            '__service_price'       => (($price > 0) && function_exists('prepare_price_for_output')) ? html_entity_decode(prepare_price_for_output($price, false, 0, 'plain_text'), ENT_QUOTES | ENT_HTML5, 'UTF-8') : '',
        );
    }

    return $rows;
}

/**
 * The visitor's details from the widget's form, cleaned and checked: name
 * and e-mail are needed, the phone may hold digits, spaces, + ( ) . - only,
 * the notes keep their line ends. A consent value is checked only when the
 * form sent one (the page has the box): then it must be true. Each value is
 * cut to its column.
 *
 * @param array $input name, email, phone, notes, consent
 * @return array values (name, email, phone, notes), errors (field => sentence)
 */
function pg_booking_widget_details_clean($input)
{
    $input = is_array($input) ? $input : array();
    $text = function ($key) use ($input) {
        return (isset($input[$key]) && is_scalar($input[$key])) ? (string) $input[$key] : '';
    };
    $cut = function ($value, $width) {
        return mb_substr($value, 0, $width, 'UTF-8');
    };

    $name = $cut(trim((string) preg_replace('/\s+/u', ' ', strip_tags($text('name')))), 100);
    $email = trim($text('email'));
    $phone = trim((string) preg_replace('/\s+/u', ' ', $text('phone')));
    $notes = str_replace(array("\r\n", "\r"), "\n", $text('notes'));
    $notes = $cut(trim((string) preg_replace('/[\x00-\x09\x0B-\x1F\x7F]/u', '', strip_tags($notes))), 1000);
    $errors = array();

    if ($name === '') {
        $errors['name'] = lang('Please enter your name.');
    }

    if (($email === '') || (strlen($email) > 191) || (filter_var($email, FILTER_VALIDATE_EMAIL) === false)) {
        $errors['email'] = lang('Please enter a valid e-mail address.');
    }

    if (($phone !== '') && ((strlen($phone) > 40) || (preg_match('/^\+?[0-9 ().\-]+$/', $phone) !== 1) || (preg_match_all('/[0-9]/', $phone) < 5))) {
        $errors['phone'] = lang('Please enter a valid phone number.');
    }

    if (array_key_exists('consent', $input) && ($input['consent'] !== true)) {
        $errors['consent'] = lang('Please agree to how your details are kept.');
    }

    return array(
        'values' => array('name' => $name, 'email' => $email, 'phone' => $phone, 'notes' => $notes),
        'errors' => $errors,
    );
}

/**
 * The words that end a booking: the site's own from Settings › Bookings, else
 * the widget's for a confirmed booking or a request that waits for staff.
 *
 * @param string $status confirmed or requested
 * @param array  $texts  as pg_booking_texts()
 * @return string
 */
function pg_booking_widget_done_text($status, $texts)
{
    if (isset($texts['done']) && ((string) $texts['done'] !== '')) {
        return (string) $texts['done'];
    }

    return ($status === 'confirmed')
        ? lang('Your appointment is booked. We have sent the details to your e-mail address.')
        : lang('Thank you. We have your request and will confirm it by e-mail.');
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
        "SELECT booking_staff.*, contacts.first_name, contacts.last_name
        FROM booking_staff
        LEFT JOIN user ON user.user_id = booking_staff.user_id
        LEFT JOIN contacts ON contacts.id = user.user_contact
        WHERE booking_staff.enabled = 1 AND user.user_id IS NOT NULL
        ORDER BY booking_staff.id");

    foreach ($staff as $index => $row) {
        // The contact's name only: a login name never reaches the public page.
        $name = pg_contact_display_name($row['first_name'], $row['last_name']);
        $staff[$index]['name'] = ($name !== '') ? $name : lang('Staff member');
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

    $reach = pg_booking_buffer_reach();

    foreach ((array) db_items(
        pg_booking_busy_sql() . "
        WHERE bookings.staff_id IN (" . implode(',', $ids) . ")
            AND bookings.start_at < '" . ((int) $until + $reach) . "' AND bookings.end_at > '" . ((int) $from - $reach) . "'
            AND bookings.status <> 'cancelled'") as $row) {
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

/**
 * The details of the signed-in visitor for the widget's form: the contact's
 * name and phone and the account's e-mail. Empty values for a guest.
 *
 * @return array name, email, phone, user_id
 */
function pg_booking_widget_visitor()
{
    $visitor = array('name' => '', 'email' => '', 'phone' => '', 'user_id' => 0);

    if (!defined('USER_LOGGED_IN') || !USER_LOGGED_IN || !defined('USER_ID')) {
        return $visitor;
    }

    $visitor['user_id'] = (int) USER_ID;
    $visitor['email'] = defined('USER_EMAIL_ADDRESS') ? (string) USER_EMAIL_ADDRESS : '';
    $contact = (defined('USER_CONTACT_ID') && USER_CONTACT_ID)
        ? db_item("SELECT first_name, last_name, mobile_phone, home_phone, business_phone FROM contacts WHERE id = '" . (int) USER_CONTACT_ID . "'")
        : null;

    if (is_array($contact)) {
        $visitor['name'] = pg_contact_display_name($contact['first_name'], $contact['last_name']);

        foreach (array('mobile_phone', 'home_phone', 'business_phone') as $column) {
            if (($visitor['phone'] === '') && (trim((string) $contact[$column]) !== '')) {
                $visitor['phone'] = trim((string) $contact[$column]);
            }
        }
    }

    return $visitor;
}

/**
 * The address of the site's privacy notice for the consent box: the page
 * the design templates install for it ("privacy-policy" in the site's
 * language, or in English). '' when the site has none.
 *
 * @return string
 */
function pg_booking_widget_privacy_url()
{
    static $url = null;

    if ($url === null) {
        $names = array_unique(array(lang('privacy-policy'), 'privacy-policy'));
        $name = (string) db_value(
            "SELECT page_name FROM page WHERE page_name IN ('" . implode("', '", array_map('e', $names)) . "') ORDER BY page_name = '" . e($names[0]) . "' DESC LIMIT 1");
        $url = ($name !== '') ? OUTPUT_PATH . encode_url_path($name) : '';
    }

    return $url;
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
    $policy = pg_booking_widget_policy();

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
            'taken'    => pg_booking_widget_taken_text(),
            'held'     => lang('We are keeping {time} for you for {minutes} minutes.'),
            'failed'   => lang('Something went wrong. Please try again.'),
            'times'    => lang('Free times on {day}'),
            'zoneNote' => lang('Times are in {zone}.'),
            'sending'  => lang('Booking your appointment…'),
            'check'    => lang('Please check the marked fields.'),
        ),
    );
}

// Render a 'booking' system widget.
//
// loop_area: one row per service a visitor may book (pg_booking_widget_rows()).
// Row tokens: ^^__service_id^^, ^^__service_name^^, ^^__service_description^^,
// ^^__service_length^^ ("60 min"), ^^__service_price^^ ('' when free).
// Static: ^^__service_total^^, ^^__privacy_url^^ (the privacy notice page,
// pg_booking_widget_privacy_url()).
// Flags: has_services, no_services, has_privacy_page; row: service_has_price,
// service_has_description.
// Parts by section binding (pg_booking_widget_parts()) and buttons by action
// binding (booking_service, booking_back), drawn by assets/js/pg_booking.js.
// $data: for the tests, array(services, staff, privacy url) in place of the
// tables.
function _render_system_widget_booking($tree_json, $widget_id, $cfg = array(), $mode = 'preview', $data = null)
{
    static $placement = 0;
    $widget_id = (int) $widget_id;
    if ($tree_json === '' || $tree_json === null) return '';
    $tree = json_decode($tree_json, true);
    if (!is_array($tree)) return '';

    list($services, $staff) = is_array($data) ? $data : pg_booking_widget_data();
    $rows = pg_booking_widget_rows($services);
    $privacy = isset($data[2]) ? (string) $data[2] : (is_array($data) ? '' : pg_booking_widget_privacy_url());

    _eo_apply_visibility_bindings($tree, array(
        'has_services'            => (bool) $rows,
        'no_services'             => !$rows,
        'service_has_price'       => true,
        'service_has_description' => true,
        'has_privacy_page'        => $privacy !== '',
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
        '__privacy_url'   => $privacy,
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
    unset($_SESSION['software']['booking_hold'], $_SESSION['software']['booking_hold_slot']);

    if (($id <= 0) || !pg_booking_ready()) {
        return;
    }

    // A hold with a deposit order is cancelled, not removed: a payment that
    // still arrives for the order finds its booking (pg_booking_deposit_mark_paid()).
    if (pg_booking_deposit_ready()) {
        db_q("UPDATE bookings SET status = 'cancelled', slot_at = NULL, hold_until = 0, updated_at = ? WHERE id = ? AND status = 'held' AND order_id > 0", array((int) $now, $id));
        pg_booking_pay_pending_forget();
    }

    db_q("DELETE FROM bookings WHERE id = ? AND status = 'held'", array($id));
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

    $policy = pg_booking_widget_policy();
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
        return array('ok' => false, 'error' => 'taken', 'message' => pg_booking_widget_taken_text());
    }

    $held = pg_booking_hold(array(
        'service'    => $service,
        'service_id' => $service_id,
        'staff_id'   => $staff_id,
        'start_at'   => $start,
    ), $policy['hold_minutes'], $now);

    if ($held['answer'] === 'taken') {
        return array('ok' => false, 'error' => 'taken', 'message' => pg_booking_widget_taken_text());
    }

    if ($held['answer'] !== 'ok') {
        return array('ok' => false, 'error' => 'failed', 'message' => lang('Something went wrong. Please try again.'));
    }

    $_SESSION['software']['booking_hold'] = (int) $held['id'];
    $_SESSION['software']['booking_hold_slot'] = array($service_id, $staff_id, $start);
    $visitor = pg_booking_widget_visitor();
    unset($visitor['user_id']);

    // The signed-in visitor's details fill the form: the page itself is the
    // same for everyone and may come from the page cache.
    return array('ok' => true, 'hold' => array('until' => $now + ((int) $policy['hold_minutes'] * 60), 'label' => pg_booking_time_label($start, $policy['zone'])), 'visitor' => $visitor);
}

/**
 * Whether the day of a start still offers it to a visitor (a hold that ran
 * out is booked or kept again only then).
 *
 * @param array $service booking_services row
 * @param array $staff   the staff rows of the start (one)
 * @param int   $start
 * @param int   $now
 * @param array $policy  pg_booking_widget_policy()
 * @return bool
 */
function pg_booking_widget_offered($service, $staff, $start, $now, $policy)
{
    $day = (new DateTime('@' . (int) $start))->setTimezone(new DateTimeZone($policy['zone']))->format('Y-m-d');
    $midnight = new DateTime($day . ' 00:00:00', new DateTimeZone($policy['zone']));
    $from = $midnight->getTimestamp() - 86400;
    $until = $midnight->modify('+1 day')->getTimestamp() + 86400;

    foreach (pg_booking_day_times($service, $staff, $day, pg_booking_busy_between($staff, $from, $until, $now), $now, $policy) as $time) {
        if ($time['start'] === (int) $start) {
            return true;
        }
    }

    return false;
}

/**
 * pg_booking_widget_offered() by ids: the service bookable and the staff
 * member still taking it.
 *
 * @param int   $service_id
 * @param int   $staff_id
 * @param int   $start
 * @param int   $now
 * @param array $policy
 * @return bool
 */
function pg_booking_widget_still_offered($service_id, $staff_id, $start, $now, $policy)
{
    list($services, $staff) = pg_booking_widget_data();
    $service = null;

    foreach ($services as $row) {
        if ((int) $row['id'] === (int) $service_id) {
            $service = $row;
        }
    }

    $staff = array_values(array_filter(pg_booking_staff_for($staff, (int) $service_id), function ($row) use ($staff_id) {
        return (int) $row['id'] === (int) $staff_id;
    }));

    return ($service !== null) && $staff && pg_booking_widget_offered($service, $staff, $start, $now, $policy);
}

/**
 * Books the slot this session holds with the visitor's details. A hold that
 * ran out is booked anyway while its time is still free; otherwise the
 * answer sends the visitor back to the times ("expired" or "taken").
 *
 * @param array $request decoded body: name, email, phone, notes, consent
 * @param int   $now
 * @return array
 */
function pg_booking_confirm_request($request, $now)
{
    $details = pg_booking_widget_details_clean($request);

    if ($details['errors']) {
        return array('ok' => false, 'error' => 'invalid', 'fields' => $details['errors'], 'message' => lang('Please check the marked fields.'));
    }

    $expired = array('ok' => false, 'error' => 'expired', 'message' => lang('The time we kept for you ran out and is no longer free. Please pick another one.'));
    $id = isset($_SESSION['software']['booking_hold']) ? (int) $_SESSION['software']['booking_hold'] : 0;
    $slot = (isset($_SESSION['software']['booking_hold_slot']) && is_array($_SESSION['software']['booking_hold_slot'])) ? $_SESSION['software']['booking_hold_slot'] : array();

    if (($id <= 0) || (count($slot) !== 3)) {
        return $expired;
    }

    list($service_id, $staff_id, $start) = array_map('intval', array_values($slot));
    list($services, $staff) = pg_booking_widget_data();
    $service = null;

    foreach ($services as $row) {
        if ((int) $row['id'] === $service_id) {
            $service = $row;
        }
    }

    $staff = array_values(array_filter(pg_booking_staff_for($staff, $service_id), function ($row) use ($staff_id) {
        return (int) $row['id'] === $staff_id;
    }));

    if (($service === null) || !$staff) {
        return $expired;
    }

    $policy = pg_booking_widget_policy();
    $visitor = pg_booking_widget_visitor();
    $booking = $details['values'] + array(
        'id'         => $id,
        'user_id'    => $visitor['user_id'],
        'service'    => $service,
        'service_id' => $service_id,
        'staff_id'   => $staff_id,
        'start_at'   => $start,
    );
    // A deposit is paid before the booking is made: the hold is kept for
    // the payment (includes/fn/booking_deposit_pay.php).
    if (pg_booking_widget_deposit($service) !== null) {
        if (!pg_booking_pay_keep_hold($id, $now)) {
            pg_booking_release_session_hold($now);
            return $expired;
        }

        return pg_booking_pay_start($id, pg_booking_customer($booking), $request['page'] ?? '', $now);
    }

    $status = !empty($policy['auto_confirm']) ? 'confirmed' : 'requested';
    $done = pg_booking_confirm($booking, $status, $now);

    if ($done['answer'] === 'expired') {
        // The hold ran out: book the time at once when the day still offers it.
        if (!pg_booking_widget_offered($service, $staff, $start, $now, $policy)) {
            pg_booking_release_session_hold($now);
            return $expired;
        }

        unset($booking['id']);
        $done = pg_booking_confirm($booking, $status, $now);
    }

    if ($done['answer'] === 'taken') {
        pg_booking_release_session_hold($now);
        return array('ok' => false, 'error' => 'taken', 'message' => pg_booking_widget_taken_text());
    }

    if ($done['answer'] !== 'ok') {
        return array('ok' => false, 'error' => 'failed', 'message' => lang('Something went wrong. Please try again.'));
    }

    // The row is a booking now: forget it, so a later release leaves it alone.
    unset($_SESSION['software']['booking_hold'], $_SESSION['software']['booking_hold_slot']);

    return array('ok' => true, 'status' => $status, 'message' => pg_booking_widget_done_text($status, pg_booking_texts()));
}
