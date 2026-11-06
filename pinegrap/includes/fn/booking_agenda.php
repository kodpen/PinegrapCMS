<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The staff agenda of appointment bookings (view_bookings.php,
 * edit_booking.php): the views and their periods, the status chips, who
 * sees which staff member, the edit form's checks, and the readers of the
 * three booking tables the screens, the list action and the panel search
 * share. The model and the slot engine are in includes/fn/bookings.php.
 *
 * Times are unix seconds (UTC) in the tables and are shown in the site's
 * zone; a day of the agenda is a wall-clock day of that zone, so a day
 * across a clock change is 23 or 25 hours long.
 *
 * Who sees what: roles 0-2 every staff member; a role-3 user who is a staff
 * member (an enabled booking_staff row) their own bookings only; anyone else
 * none.
 *
 * Loaded where it is needed (require_once), not by functions.php.
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
 * The agenda view asked for: 'day', 'week' or 'list'; anything else is the
 * week.
 *
 * @param mixed $value
 * @return string
 */
function pg_booking_agenda_view($value)
{
    return (is_string($value) && in_array($value, array('day', 'week', 'list'), true)) ? $value : 'week';
}

/**
 * The period a view shows around a date, in the site's zone: one day, the
 * week the date falls in (from the week's first day), or the date's month
 * for the list. The navigator's previous and next dates step one period.
 *
 * @param string $view      pg_booking_agenda_view()'s answer
 * @param mixed  $date      'YYYY-MM-DD'; anything else is today
 * @param int    $now       unix time
 * @param int    $first_day the week's first day as date('w') counts it
 * @param string $zone      the site's zone
 * @return array date, today, days (list of 'YYYY-MM-DD'), start, end (unix,
 *               end exclusive), previous, next ('YYYY-MM-DD')
 */
function pg_booking_agenda_period($view, $date, $now, $first_day, $zone)
{
    $zone = new DateTimeZone((string) $zone);
    $today = new DateTime('@' . (int) $now);
    $today->setTimezone($zone);
    $today = $today->format('Y-m-d');

    if (!is_string($date) || !pg_booking_date_valid($date)) {
        $date = $today;
    }

    $day = new DateTime($date . ' 00:00:00', $zone);

    if ($view === 'day') {
        $first = clone $day;
        $count = 1;
        $step = '1 day';
    } elseif ($view === 'list') {
        $first = new DateTime($day->format('Y-m-01') . ' 00:00:00', $zone);
        $count = (int) $first->format('t');
        $step = '1 month';
    } else {
        $back = ((int) $day->format('w') - (int) $first_day + 7) % 7;
        $first = clone $day;
        $first->modify('-' . $back . ' days');
        $count = 7;
        $step = '7 days';
    }

    $days = array();
    $cursor = clone $first;

    for ($index = 0; $index < $count; $index++) {
        $days[] = $cursor->format('Y-m-d');
        $cursor->modify('+1 day');
    }

    // The end is the midnight after the last day, built from its wall-clock
    // date so a clock change inside the period moves only the real length.
    $end = new DateTime($cursor->format('Y-m-d') . ' 00:00:00', $zone);

    $previous = clone $first;
    $previous->modify('-' . $step);
    $next = clone $first;
    $next->modify('+' . $step);

    return array(
        'date'     => $date,
        'today'    => $today,
        'days'     => $days,
        'start'    => $first->getTimestamp(),
        'end'      => $end->getTimestamp(),
        'previous' => $previous->format('Y-m-d'),
        'next'     => $next->format('Y-m-d'),
    );
}

/**
 * Booking rows laid out by the day they start on in the site's zone, each
 * day in start order; rows outside the days are left out.
 *
 * @param array  $rows rows with start_at
 * @param array  $days list of 'YYYY-MM-DD'
 * @param string $zone
 * @return array 'YYYY-MM-DD' => list of rows
 */
function pg_booking_agenda_by_day($rows, $days, $zone)
{
    $zone = new DateTimeZone((string) $zone);
    $by_day = array_fill_keys($days, array());

    foreach ((array) $rows as $row) {
        $moment = new DateTime('@' . (int) $row['start_at']);
        $moment->setTimezone($zone);
        $key = $moment->format('Y-m-d');

        if (isset($by_day[$key])) {
            $by_day[$key][] = $row;
        }
    }

    foreach ($by_day as $key => $list) {
        usort($list, function ($a, $b) {
            return ((int) $a['start_at'] <=> (int) $b['start_at']) ?: ((int) ($a['id'] ?? 0) <=> (int) ($b['id'] ?? 0));
        });
        $by_day[$key] = $list;
    }

    return $by_day;
}

/**
 * The statuses the chips select, in the ENUM's order. Without a choice: the
 * bookings people made and kept or ended (requested, confirmed, no-show,
 * done); a cancelled booking and a visitor's hold show when chosen.
 *
 * @param mixed $value a status or a list of them
 * @return string[]
 */
function pg_booking_agenda_status_filter($value)
{
    $chosen = is_array($value) ? $value : (is_string($value) && ($value !== '') ? array($value) : array());
    $statuses = array();

    foreach (pg_booking_statuses() as $status) {
        if (in_array($status, $chosen, true)) {
            $statuses[] = $status;
        }
    }

    return $statuses ? $statuses : array('requested', 'confirmed', 'no_show', 'done');
}

/**
 * The status label and badge class of each status.
 *
 * @return array status => array(label, class)
 */
function pg_booking_agenda_status_badges()
{
    return array(
        'held'      => array(lang('On hold'), 'theme-secondary'),
        'requested' => array(lang('Requested'), 'theme-warning'),
        'confirmed' => array(lang('Confirmed'), 'theme-success'),
        'cancelled' => array(lang('Cancelled'), 'theme-danger'),
        'no_show'   => array(lang('No-show'), 'theme-inverse'),
        'done'      => array(lang('Done'), 'theme-info'),
    );
}

/**
 * The statuses a booking may move to from the edit screen, in the order
 * they are offered.
 *
 * @param string $from
 * @return string[]
 */
function pg_booking_agenda_moves($from)
{
    $moves = array();

    foreach (array('confirmed', 'cancelled', 'no_show', 'done') as $to) {
        if (pg_booking_can_move((string) $from, $to)) {
            $moves[] = $to;
        }
    }

    return $moves;
}

/**
 * Which staff member's bookings a user sees: null for every one (roles
 * 0-2), the user's own staff id for a role-3 staff member, false for none.
 *
 * @param int $role     users.role
 * @param int $staff_id the user's enabled booking_staff id, 0 without one
 * @return int|null|false
 */
function pg_booking_agenda_scope($role, $staff_id)
{
    if ((int) $role <= 2) {
        return null;
    }

    if (((int) $role === 3) && ((int) $staff_id > 0)) {
        return (int) $staff_id;
    }

    return false;
}

/**
 * Whether a scope (pg_booking_agenda_scope()) reaches a booking of a staff
 * member: every booking for null, the staff member's own for an id, none
 * for false. Pure: the Workspace's tags and event rules ask it per row.
 *
 * @param int|null|false $scope
 * @param int            $staff_id bookings.staff_id
 * @return bool
 */
function pg_booking_agenda_scope_allows($scope, $staff_id)
{
    if ($scope === null) {
        return true;
    }

    return ($scope !== false) && ((int) $scope > 0) && ((int) $scope === (int) $staff_id);
}

/**
 * The staff member the agenda is narrowed to: the one asked for when the
 * viewer may see them, the viewer's own when they see only themselves, else
 * 0 (every staff member).
 *
 * @param mixed    $value     the filter's value
 * @param int|null $scope     pg_booking_agenda_scope()'s answer (not false)
 * @param int[]    $staff_ids the staff ids that exist
 * @return int
 */
function pg_booking_agenda_staff_filter($value, $scope, $staff_ids)
{
    if ($scope !== null) {
        return (int) $scope;
    }

    $id = (is_scalar($value) && ctype_digit((string) $value)) ? (int) $value : 0;

    return in_array($id, array_map('intval', (array) $staff_ids), true) ? $id : 0;
}

/**
 * The notes of a cancelled booking: the reason added as its own paragraph
 * after the notes there were; no reason leaves them as they were.
 *
 * @param string $notes  bookings.notes
 * @param string $reason the reason typed
 * @param string $label  the translated "Cancelled" word
 * @return string
 */
function pg_booking_cancel_notes($notes, $reason, $label)
{
    $notes = rtrim((string) $notes);
    $reason = trim((string) $reason);

    if ($reason === '') {
        return $notes;
    }

    $line = $label . ': ' . $reason;

    return ($notes === '') ? $line : $notes . "\n\n" . $line;
}

/**
 * The errors of a staff-made booking's fields: the staff member exists and
 * gives the service, a start is chosen, a name, and an e-mail address or a
 * phone to reach the customer (a typed address must be valid).
 *
 * @param array $fields   service_id, staff_id, start_at, name, email, phone
 * @param array $services id => service row
 * @param array $staff    id => array('service_ids' => int[])
 * @return array field => message
 */
function pg_booking_agenda_check($fields, $services, $staff)
{
    $errors = array();
    $service_id = (int) ($fields['service_id'] ?? 0);
    $staff_id = (int) ($fields['staff_id'] ?? 0);

    if (!isset($staff[$staff_id])) {
        $errors['staff_id'] = lang('Choose a staff member.');
    } elseif (!isset($services[$service_id]) || !in_array($service_id, array_map('intval', (array) $staff[$staff_id]['service_ids']), true)) {
        $errors['service_id'] = lang('Choose a service this staff member gives.');
    }

    if ((int) ($fields['start_at'] ?? 0) <= 0) {
        $errors['start_at'] = lang('Choose a time.');
    }

    if (trim((string) ($fields['name'] ?? '')) === '') {
        $errors['name'] = lang('Enter the customer\'s name.');
    }

    $contact_error = pg_booking_agenda_contact_error($fields['email'] ?? '', $fields['phone'] ?? '');

    if ($contact_error !== '') {
        $errors['email'] = $contact_error;
    }

    return $errors;
}

/**
 * The error of a booking's contact fields, '' when they are fine: a typed
 * e-mail address must be valid, and an address or a phone must be there,
 * or no confirmation, reminder or cancellation can reach the customer.
 * Shared by a new booking and a change to one.
 *
 * @param string $email
 * @param string $phone
 * @return string the message for the e-mail field, or ''
 */
function pg_booking_agenda_contact_error($email, $phone)
{
    $email = trim((string) $email);
    $phone = trim((string) $phone);

    if (($email !== '') && (filter_var($email, FILTER_VALIDATE_EMAIL) === false)) {
        return lang('Enter a valid e-mail address.');
    }

    if (($email === '') && ($phone === '')) {
        return lang('Enter an e-mail address or a phone number.');
    }

    return '';
}

/**
 * The label of where a booking was made (bookings.source): the site's
 * booking widget, the panel's agenda or the external API; an unknown value
 * shows as stored.
 *
 * @param string $source
 * @return string
 */
function pg_booking_agenda_source_label($source)
{
    $source = (string) $source;
    $labels = array(
        'site'  => lang('Online booking'),
        'panel' => lang('Panel'),
        'api'   => lang('API'),
    );

    return $labels[$source] ?? $source;
}

/**
 * The fields of a booking an audit trail line compares: what the booking is
 * and where it stands. The customer's name, address, phone and the notes
 * stay out of the trail (only the notes' length shows a change), so a
 * person's erasure need not reach the trail for a booking.
 *
 * @param array $row a bookings row
 * @return array
 */
function pg_booking_agenda_audit_fields($row)
{
    if (!$row) {
        return array();
    }

    $notes = (string) ($row['notes'] ?? '');

    return array(
        'service_id'   => (int) $row['service_id'],
        'staff_id'     => (int) $row['staff_id'],
        'start_at'     => (int) $row['start_at'],
        'end_at'       => (int) $row['end_at'],
        'status'       => (string) $row['status'],
        'source'       => (string) $row['source'],
        'notes_length' => function_exists('mb_strlen') ? mb_strlen($notes, 'UTF-8') : strlen($notes),
    );
}

/**
 * Writes the audit trail line of a booking change.
 *
 * @param string $action create, update or cancel
 * @param int    $id
 * @param array  $before the row before (empty for a create)
 * @param array  $after  the row after
 * @return void
 */
function pg_booking_agenda_audit($action, $id, $before, $after)
{
    if (function_exists('pg_audit')) {
        pg_audit($action, 'booking', (int) $id, pg_booking_agenda_audit_fields($before), pg_booking_agenda_audit_fields($after), '#' . (int) $id);
    }
}

/**
 * The staff members, by id, with the panel user's name and the services
 * they give. Disabled staff members come too (their old bookings still
 * show); new bookings offer the enabled ones only.
 *
 * @return array id => array(id, user_id, name, service_ids, hours,
 *               exceptions, enabled)
 */
function pg_booking_agenda_staff()
{
    $staff = array();

    $rows = db_items(
        "SELECT
            booking_staff.id, booking_staff.user_id, booking_staff.service_ids, booking_staff.hours,
            booking_staff.exceptions, booking_staff.enabled,
            user.user_username, contacts.first_name, contacts.last_name
        FROM booking_staff
        LEFT JOIN user ON user.user_id = booking_staff.user_id
        LEFT JOIN contacts ON contacts.id = user.user_contact
        ORDER BY booking_staff.id");

    foreach ((array) $rows as $row) {
        $ids = json_decode((string) $row['service_ids'], true);

        $staff[(int) $row['id']] = array(
            'id'          => (int) $row['id'],
            'user_id'     => (int) $row['user_id'],
            'name'        => pg_user_display_name($row['first_name'], $row['last_name'], $row['user_username']),
            'service_ids' => is_array($ids) ? array_values(array_filter(array_map('intval', $ids))) : array(),
            'hours'       => (string) $row['hours'],
            'exceptions'  => (string) $row['exceptions'],
            'enabled'     => (int) $row['enabled'] === 1,
        );
    }

    uasort($staff, function ($a, $b) {
        return strcasecmp($a['name'], $b['name']) ?: ($a['id'] <=> $b['id']);
    });

    return $staff;
}

/**
 * The services, by id, in their order.
 *
 * @return array id => row
 */
function pg_booking_agenda_services()
{
    $services = array();

    foreach ((array) db_items("SELECT id, name, length_minutes, buffer_before, buffer_after, price, enabled FROM booking_services ORDER BY sort_order, name, id") as $row) {
        $services[(int) $row['id']] = $row;
    }

    return $services;
}

/**
 * The enabled staff row of a panel user, 0 when the user is no staff
 * member.
 *
 * @param int $user_id
 * @return int
 */
function pg_booking_agenda_user_staff_id($user_id)
{
    return (int) db_value("SELECT id FROM booking_staff WHERE user_id = '" . (int) $user_id . "' AND enabled = 1");
}

/**
 * Which staff member's bookings the signed-in user sees
 * (pg_booking_agenda_scope()).
 *
 * @param array $user validate_user()'s answer
 * @return int|null|false
 */
function pg_booking_agenda_user_scope($user)
{
    $role = (int) $user['role'];

    return pg_booking_agenda_scope($role, ($role === 3) ? pg_booking_agenda_user_staff_id($user['id']) : 0);
}

/**
 * The SQL condition that keeps bookings to a scope, for a query on the
 * bookings table.
 *
 * @param int|null $scope pg_booking_agenda_scope()'s answer (not false)
 * @return string
 */
function pg_booking_agenda_scope_sql($scope)
{
    return ($scope === null) ? '' : " AND (bookings.staff_id = '" . (int) $scope . "')";
}

/**
 * The bookings that start in a period, with their service's name, kept to
 * a staff member and to statuses.
 *
 * @param int      $start    unix, inclusive
 * @param int      $end      unix, exclusive
 * @param int      $staff_id 0 for every staff member
 * @param string[] $statuses
 * @return array
 */
function pg_booking_agenda_rows($start, $end, $staff_id, $statuses)
{
    $statuses = array_values(array_intersect((array) $statuses, pg_booking_statuses()));

    if (!$statuses) {
        return array();
    }

    $result = db_q(
        "SELECT bookings.id, bookings.service_id, bookings.staff_id, bookings.name, bookings.email, bookings.phone,
            bookings.start_at, bookings.end_at, bookings.status, bookings.hold_until, bookings.source,"
            . (pg_booking_deposit_ready() ? ' bookings.order_id, bookings.paid_at,' : '') . "
            booking_services.name AS service_name
        FROM bookings
        LEFT JOIN booking_services ON booking_services.id = bookings.service_id
        WHERE bookings.start_at >= ? AND bookings.start_at < ?"
        . (((int) $staff_id > 0) ? " AND bookings.staff_id = '" . (int) $staff_id . "'" : '') . "
            AND bookings.status IN ('" . implode("', '", $statuses) . "')
        ORDER BY bookings.start_at, bookings.id",
        array((int) $start, (int) $end));

    $rows = array();

    while ($result && ($row = mysqli_fetch_assoc($result))) {
        $rows[] = $row;
    }

    return $rows;
}

/**
 * The busy ranges of a staff member's Workspace calendar between two
 * moments: their own events, their departments' and the company's.
 *
 * @param int $user_id
 * @param int $from
 * @param int $until
 * @return array list of array(start, end)
 */
function pg_booking_agenda_ws_busy($user_id, $from, $until)
{
    if (!function_exists('pg_schema_has') || !pg_schema_has('ws_events', 'scope') || ((int) $user_id <= 0)) {
        return array();
    }

    $departments = array_map('intval', (array) db_values("SELECT department_id FROM ws_department_members WHERE user_id = '" . (int) $user_id . "'"));

    $rows = db_items(
        "SELECT ws_events.starts_at, ws_events.ends_at, ws_events.all_day
        FROM ws_events
        WHERE
            ws_events.starts_at < '" . (int) $until . "'
            AND ws_events.ends_at >= '" . (int) $from . "'
            AND (
                ws_events.scope = 'company'
                " . ($departments ? "OR (ws_events.scope = 'department' AND ws_events.department_id IN (" . implode(', ', $departments) . "))" : '') . "
                OR (ws_events.scope = 'people' AND EXISTS (SELECT 1 FROM ws_event_people WHERE ws_event_people.event_id = ws_events.id AND ws_event_people.user_id = '" . (int) $user_id . "'))
            )");

    $busy = array();

    // An all-day event is stored up to 23:59:59 of its last day; it takes
    // that whole second too.
    foreach ((array) $rows as $row) {
        $busy[] = array((int) $row['starts_at'], (int) $row['ends_at'] + (!empty($row['all_day']) ? 1 : 0));
    }

    return $busy;
}

/**
 * The free starts of a day for a staff-made booking: the slot engine over
 * the staff member's hours, their other bookings and their Workspace
 * calendar. Staff are not held to the visitors' lead time and horizon; a
 * start already past is not offered.
 *
 * @param array  $service a booking_services row
 * @param array  $staff   pg_booking_agenda_staff()'s row
 * @param string $day     'YYYY-MM-DD'
 * @param int    $now
 * @return int[]
 */
function pg_booking_agenda_free_slots($service, $staff, $day, $now)
{
    $zone = date_default_timezone_get();

    try {
        $from = (new DateTime($day . ' 00:00:00', new DateTimeZone($zone)))->getTimestamp();
    } catch (Exception $exception) {
        return array();
    }

    // A day and its neighbours' edges: buffers reach past midnight.
    $window_from = $from - 86400;
    $window_until = $from + (2 * 86400);

    $rows = db_items(
        "SELECT start_at, end_at, status, hold_until FROM bookings
        WHERE staff_id = '" . (int) $staff['id'] . "'
            AND start_at < '" . $window_until . "' AND end_at > '" . $window_from . "'
            AND status <> 'cancelled'");

    $busy = array_merge(pg_booking_busy_from_rows($rows, $now), pg_booking_agenda_ws_busy($staff['user_id'], $window_from, $window_until));

    return pg_booking_slots($service, $staff, $day, $busy, $now, array('zone' => $zone));
}

/**
 * A moment as the agenda writes it: the time alone ('14:30' or '2:30 PM')
 * or with the date.
 *
 * @param int  $timestamp
 * @param bool $with_date
 * @return string
 */
function pg_booking_agenda_time($timestamp, $with_date = false)
{
    $time = date(pg_time_format_code(), (int) $timestamp);

    return $with_date ? prepare_form_data_for_output(date('Y-m-d', (int) $timestamp), 'date') . ' ' . $time : $time;
}

/**
 * A status badge.
 *
 * @param string $status
 * @return string HTML
 */
function pg_booking_agenda_badge($status)
{
    $badges = pg_booking_agenda_status_badges();
    $badge = isset($badges[$status]) ? $badges[$status] : array((string) $status, 'theme-secondary');

    return '<span class="badge badge-subtle ' . h($badge[1]) . '">' . h($badge[0]) . '</span>';
}

/**
 * The list view's columns, as pg_list_table_printed() takes them: the
 * month's bookings are printed whole, so the browser sorts and searches
 * them. Pure.
 *
 * @return array key => spec
 */
function pg_booking_agenda_list_columns()
{
    return array(
        'time'     => array('heading' => lang('Time'), 'kind' => 'link'),
        'status'   => array('heading' => lang('Status'), 'kind' => 'badge'),
        'customer' => array('heading' => lang('Customer')),
        'contact'  => array('heading' => lang('Contact')),
        'service'  => array('heading' => lang('Service')),
        'staff'    => array('heading' => lang('Staff')),
    );
}

/**
 * One booking of the list view, as pg_list_table_printed() takes a row:
 * the time opens the booking and sorts by its Unix time, the status by its
 * label.
 *
 * @param array  $row     pg_booking_agenda_rows()'s booking
 * @param array  $staff   pg_booking_agenda_staff()'s answer
 * @param string $deposit the booking's deposit badge, HTML ('' for none)
 * @return array array('id', 'cells')
 */
function pg_booking_agenda_list_row(array $row, array $staff, $deposit = '')
{
    $id = (int) $row['id'];
    $who = isset($staff[(int) $row['staff_id']]) ? $staff[(int) $row['staff_id']]['name'] : '';
    $email = (string) $row['email'];
    $phone = (string) $row['phone'];
    $contact = h($email) . ((($email !== '') && ($phone !== '')) ? '<br>' : '') . h($phone);
    $badges = pg_booking_agenda_status_badges();
    $status = isset($badges[$row['status']]) ? $badges[$row['status']][0] : (string) $row['status'];

    return array('id' => $id, 'cells' => array(
        'time'     => pg_list_cell('<a href="edit_booking.php?id=' . $id . '" class="pg-link-body-emphasis" data-pg-open>' . h(pg_booking_agenda_time($row['start_at'], true)) . '</a>', 'align-middle text-nowrap', array('data-pg-sort-value' => (string) (int) $row['start_at'])),
        'status'   => pg_list_cell(pg_booking_agenda_badge($row['status']) . $deposit, 'align-middle', array('data-pg-sort-value' => $status)),
        'customer' => pg_list_cell(h($row['name']), 'align-middle text-wrap text-break'),
        'contact'  => pg_list_cell($contact, 'align-middle small text-wrap text-break'),
        'service'  => pg_list_cell(h((string) $row['service_name']), 'align-middle'),
        'staff'    => pg_list_cell(h($who), 'align-middle'),
    ));
}

/**
 * The customer card of a booking: the contact with the booking's e-mail
 * address, else the linked account's contact; 0 when there is none.
 *
 * @param array $booking
 * @return int contact id
 */
function pg_booking_agenda_contact_id($booking)
{
    $email = trim((string) ($booking['email'] ?? ''));

    if ($email !== '') {
        $id = (int) db_value("SELECT id FROM contacts WHERE email_address = '" . e($email) . "' ORDER BY id LIMIT 1");

        if ($id > 0) {
            return $id;
        }
    }

    if ((int) ($booking['user_id'] ?? 0) > 0) {
        return (int) db_value("SELECT user_contact FROM user WHERE user_id = '" . (int) $booking['user_id'] . "'");
    }

    return 0;
}

/**
 * The weekday name of a day, 'Monday' to 'Sunday' in the panel language.
 *
 * @param string $day 'YYYY-MM-DD'
 * @return string
 */
function pg_booking_agenda_weekday($day)
{
    $names = array(lang('Sunday'), lang('Monday'), lang('Tuesday'), lang('Wednesday'), lang('Thursday'), lang('Friday'), lang('Saturday'));

    return $names[(int) date('w', strtotime($day . ' 12:00:00'))];
}

/**
 * The address of the agenda with its view, date, staff member and status
 * chips, any of them replaced.
 *
 * @param array $state   view, date, staff, status
 * @param array $changes
 * @return string
 */
function pg_booking_agenda_link($state, $changes = array())
{
    $query = array_merge($state, $changes);
    $query = array_filter($query, function ($value) {
        return ($value !== '') && ($value !== 0) && ($value !== array()) && ($value !== null);
    });

    return 'view_bookings.php' . ($query ? '?' . http_build_query($query) : '');
}

/**
 * Writes a booking's status, customer details and notes as one UPDATE that
 * names the status it moves from, so a booking changed meanwhile (by another
 * screen or the API) is not overwritten. A cancel frees the slot in the same
 * UPDATE (slot_at NULL). A hold becoming a request or a confirmed booking
 * goes through pg_booking_confirm() instead, which stores the manage token.
 *
 * @param array  $booking  the bookings row read before
 * @param string $status   the status it moves to (or keeps)
 * @param array  $customer pg_booking_customer()'s answer
 * @param int    $now
 * @return bool false when the query failed or the row was changed meanwhile
 */
function pg_booking_agenda_status_write($booking, $status, $customer, $now)
{
    $cancel = ($status === 'cancelled') && ((string) $booking['status'] !== 'cancelled');

    $result = db_q(
        "UPDATE bookings
        SET status = ?, name = ?, email = ?, phone = ?, notes = ?, updated_at = ?" . ($cancel ? ", slot_at = NULL, hold_until = 0" : "") . "
        WHERE id = ? AND status = ?",
        array((string) $status, $customer['name'], $customer['email'], $customer['phone'], $customer['notes'], (int) $now, (int) $booking['id'], (string) $booking['status']));

    return $result && (mysqli_affected_rows(db::$con) === 1);
}

/**
 * The statuses a booking may move to now: pg_booking_agenda_moves(), but a
 * visitor's hold that has run out can only be cancelled (its slot is no
 * longer held for anyone).
 *
 * @param array $booking with status and hold_until
 * @param int   $now
 * @return string[]
 */
function pg_booking_agenda_moves_now($booking, $now)
{
    $moves = pg_booking_agenda_moves((string) $booking['status']);

    if (pg_booking_slot_holder($booking, $now) === 'expired') {
        return array_values(array_intersect($moves, array('cancelled')));
    }

    return $moves;
}

/**
 * A staff member's confirmed bookings between two moments, as their
 * Workspace calendar shows them: the Work Calendar, Today and the calendar
 * written to their Google or Outlook account all read them here. The
 * booking row is the one source of a booking's time; no copy is written to
 * ws_events.
 *
 * @param int $user_id the panel user the staff row belongs to
 * @param int $from    unix
 * @param int $until   unix
 * @return array[] pg_booking_calendar_item_rows()'s rows
 */
function pg_booking_calendar_items($user_id, $from, $until)
{
    if (((int) $user_id <= 0) || !pg_booking_ready()) {
        return array();
    }

    $rows = db_items(
        "SELECT bookings.id, bookings.name, bookings.start_at, bookings.end_at, booking_services.name AS service_name
        FROM bookings
        INNER JOIN booking_staff ON booking_staff.id = bookings.staff_id
        LEFT JOIN booking_services ON booking_services.id = bookings.service_id
        WHERE booking_staff.user_id = '" . (int) $user_id . "'
            AND bookings.status = 'confirmed'
            AND bookings.start_at < '" . (int) $until . "'
            AND bookings.end_at > '" . (int) $from . "'
        ORDER BY bookings.start_at, bookings.id
        LIMIT 500");

    return pg_booking_calendar_item_rows((array) $rows);
}

/**
 * The calendar's shape of booking rows: the title is the service and the
 * customer, the address opens the booking on the agenda.
 *
 * @param array[] $rows id, name, start_at, end_at, service_name
 * @return array[] id, starts_at, ends_at, title, service, customer, url
 */
function pg_booking_calendar_item_rows($rows)
{
    $items = array();

    foreach ($rows as $row) {
        $start = (int) $row['start_at'];
        $end = max($start, (int) $row['end_at']);
        $service = trim((string) ($row['service_name'] ?? ''));
        $customer = trim((string) ($row['name'] ?? ''));

        $items[] = array(
            'id'        => (int) $row['id'],
            'starts_at' => $start,
            'ends_at'   => $end,
            'title'     => implode(' · ', array_filter(array($service, $customer), 'strlen')) ?: lang('Booking'),
            'service'   => $service,
            'customer'  => $customer,
            'url'       => 'edit_booking.php?id=' . (int) $row['id'],
        );
    }

    return $items;
}

/**
 * Tells a staff member that the move of a booking in their own Google or
 * Outlook calendar was not kept: a booking's time changes only on the
 * agenda and the manage page, and the event is written back. One bell line
 * aimed at the person, naming the booking by its id.
 *
 * @param int   $user_id
 * @param array $item pg_booking_calendar_item_rows()'s row
 */
function pg_booking_calendar_refused($user_id, $item)
{
    include_once(PG_FUNCTIONS_DIR . '/includes/notifications.php');

    if (((int) $user_id <= 0) || !pg_notification_targets_available()) {
        return;
    }

    $title = lang(array('string' => 'The move of booking #{var:1} in your calendar was not kept', 'vars' => (int) $item['id']));

    db_q("INSERT INTO notifications (action, type, title, target_user_id, reference_id, user, readed, timestamp)
        VALUES ('booking_refused', 'info', ?, ?, ?, '', '0', UNIX_TIMESTAMP())",
        array(mb_substr($title, 0, 250), (int) $user_id, (int) $item['id']));
}
