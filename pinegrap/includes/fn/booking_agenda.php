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

    $email = trim((string) ($fields['email'] ?? ''));
    $phone = trim((string) ($fields['phone'] ?? ''));

    if (($email !== '') && (filter_var($email, FILTER_VALIDATE_EMAIL) === false)) {
        $errors['email'] = lang('Enter a valid e-mail address.');
    } elseif (($email === '') && ($phone === '')) {
        $errors['email'] = lang('Enter an e-mail address or a phone number.');
    }

    return $errors;
}
