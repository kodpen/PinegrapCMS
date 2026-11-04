<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * What a customer does with their own booking: the manage page
 * (booking_manage.php), opened from the signed link of the booking's mails
 * without an account, and the bookings section of the my_account widget,
 * whose items open the same page. The customer may cancel, move the booking
 * to another free time of the same service and staff member, and download
 * it as an .ics file.
 *
 * The policy of Settings › Bookings decides: a requested or confirmed
 * booking may be cancelled until cancel_hours before its start, and moved
 * in the same window while reschedule is on. A new time is one the slot
 * engine offers a visitor now (lead time, horizon, step), with the booking
 * itself left out of the busy times; the move is written under the staff
 * member's lock and the unique key refuses a time taken meanwhile.
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
 * What the customer may do with a booking now (pure): cancel and move a
 * requested or confirmed booking until cancel_hours before its start; move
 * only while the policy allows it.
 *
 * @param array $booking with status, start_at
 * @param array $policy  cancel_hours, reschedule (pg_booking_policy())
 * @param int   $now
 * @return array cancel (bool), reschedule (bool), until (the last moment, unix)
 */
function pg_booking_manage_rights($booking, $policy, $now)
{
    $until = (int) ($booking['start_at'] ?? 0) - ((int) ($policy['cancel_hours'] ?? 0) * 3600);
    $open = in_array((string) ($booking['status'] ?? ''), array('requested', 'confirmed'), true)
        && ((int) $now < (int) ($booking['start_at'] ?? 0))
        && ((int) $now <= $until);

    return array(
        'cancel'     => $open,
        'reschedule' => $open && !empty($policy['reschedule']),
        'until'      => $until,
    );
}

/**
 * A customer's bookings split for the account (pure): upcoming ones (a
 * request or a confirmed booking that has not ended), soonest first, and the
 * rest, latest first. Holds are left out.
 *
 * @param array $rows bookings rows
 * @param int   $now
 * @return array upcoming, past
 */
function pg_booking_customer_split($rows, $now)
{
    $upcoming = array();
    $past = array();

    foreach ((array) $rows as $row) {
        $status = (string) ($row['status'] ?? '');

        if ($status === 'held') {
            continue;
        }

        if (in_array($status, array('requested', 'confirmed'), true) && ((int) $row['end_at'] > (int) $now)) {
            $upcoming[] = $row;
        } else {
            $past[] = $row;
        }
    }

    usort($upcoming, function ($a, $b) {
        return ((int) $a['start_at'] - (int) $b['start_at']) ?: ((int) $a['id'] - (int) $b['id']);
    });
    usort($past, function ($a, $b) {
        return ((int) $b['start_at'] - (int) $a['start_at']) ?: ((int) $b['id'] - (int) $a['id']);
    });

    return array('upcoming' => $upcoming, 'past' => $past);
}

/**
 * The months the reschedule calendar may show (pure): from this month to the
 * month of the horizon's last day, in the site's zone.
 *
 * @param int    $now
 * @param int    $horizon_days
 * @param string $zone
 * @return string[] 'YYYY-MM', ascending
 */
function pg_booking_manage_months($now, $horizon_days, $zone)
{
    try {
        $tz = new DateTimeZone((string) $zone);
    } catch (Exception $exception) {
        $tz = new DateTimeZone(date_default_timezone_get());
    }

    $month = (new DateTime('@' . (int) $now))->setTimezone($tz)->format('Y-m');
    $last = (new DateTime('@' . ((int) $now + (max(1, (int) $horizon_days) * 86400))))->setTimezone($tz)->format('Y-m');
    $months = array();

    while (($month <= $last) && (count($months) < 25)) {
        $months[] = $month;
        $month = (new DateTime($month . '-01 00:00:00', $tz))->modify('+1 month')->format('Y-m');
    }

    return $months;
}

/**
 * The words a customer reads for a booking's status.
 *
 * @param string $status
 * @return string
 */
function pg_booking_customer_status_label($status)
{
    $labels = array(
        'requested' => lang('Requested'),
        'confirmed' => lang('Confirmed'),
        'cancelled' => lang('Cancelled'),
        'no_show'   => lang('No-show'),
        'done'      => lang('Done'),
    );

    return isset($labels[$status]) ? $labels[$status] : '';
}

/**
 * The booking a manage link opens: its row with the service's and staff
 * member's names while the token matches and the booking has not ended.
 * Null for anything else, a wrong token and an unknown id alike.
 *
 * @param mixed $id
 * @param mixed $token
 * @param int   $now
 * @return array|null
 */
function pg_booking_manage_find($id, $token, $now)
{
    if (!is_scalar($id) || !is_scalar($token) || (preg_match('/^\d{1,10}$/', (string) $id) !== 1)) {
        return null;
    }

    $row = pg_booking_message_row((int) $id);

    if (!$row || ((string) $row['status'] === 'held')
        || !pg_booking_manage_token_valid((string) $token, (string) $row['manage_token'], (int) $row['end_at'], $now)) {
        return null;
    }

    return $row;
}

/**
 * The service and staff member a booking may be moved with: the same ones,
 * while the service is still offered and the staff member still gives it.
 *
 * @param array $row
 * @return array|null array(service row, staff row)
 */
function pg_booking_manage_engine($row)
{
    list($services, $staff) = pg_booking_widget_data();

    foreach ($services as $service) {
        if ((int) $service['id'] !== (int) $row['service_id']) {
            continue;
        }

        foreach (pg_booking_staff_for($staff, (int) $service['id']) as $member) {
            if ((int) $member['id'] === (int) $row['staff_id']) {
                return array($service, $member);
            }
        }
    }

    return null;
}

/**
 * The staff member's busy ranges around a booking with the booking itself
 * taken out: pg_booking_busy_between() lists the bookings' ranges first, so
 * the first range equal to the booking's own times is its own.
 *
 * @param array $row
 * @param array $staff booking_staff row
 * @param int   $from
 * @param int   $until
 * @param int   $now
 * @return array list of array(start, end)
 */
function pg_booking_manage_busy($row, $staff, $from, $until, $now)
{
    $busy = pg_booking_busy_between(array($staff), $from, $until, $now);
    $ranges = isset($busy[(int) $staff['id']]) ? array_values($busy[(int) $staff['id']]) : array();

    return pg_booking_without_range($ranges, (int) $row['start_at'], (int) $row['end_at']);
}

/**
 * A list of busy ranges without the first one equal to start and end (pure).
 *
 * @param array $ranges list of array(start, end)
 * @param int   $start
 * @param int   $end
 * @return array
 */
function pg_booking_without_range($ranges, $start, $end)
{
    foreach ((array) $ranges as $index => $range) {
        $range = array_values((array) $range);
        if ((count($range) >= 2) && ((int) $range[0] === (int) $start) && ((int) $range[1] === (int) $end)) {
            unset($ranges[$index]);
            break;
        }
    }

    return array_values((array) $ranges);
}

/**
 * The days of a month a booking could move to.
 *
 * @param array  $row
 * @param array  $engine pg_booking_manage_engine()
 * @param string $month  'YYYY-MM'
 * @param int    $now
 * @return string[] 'YYYY-MM-DD'
 */
function pg_booking_manage_days($row, $engine, $month, $now)
{
    if (!pg_booking_month_valid($month)) {
        return array();
    }

    $policy = pg_booking_widget_policy();
    $first = new DateTime($month . '-01 00:00:00', new DateTimeZone($policy['zone']));
    $from = $first->getTimestamp() - 86400;
    $until = $first->modify('+1 month')->getTimestamp() + 86400;
    $busy = array((int) $engine[1]['id'] => pg_booking_manage_busy($row, $engine[1], $from, $until, $now));

    return pg_booking_month_days($engine[0], array($engine[1]), $month, $busy, $now, $policy);
}

/**
 * The times of a day a booking could move to.
 *
 * @param array  $row
 * @param array  $engine pg_booking_manage_engine()
 * @param string $day    'YYYY-MM-DD'
 * @param int    $now
 * @return int[] unix starts
 */
function pg_booking_manage_times($row, $engine, $day, $now)
{
    if (!pg_booking_date_valid($day)) {
        return array();
    }

    $policy = pg_booking_widget_policy();
    $midnight = new DateTime($day . ' 00:00:00', new DateTimeZone($policy['zone']));
    $from = $midnight->getTimestamp() - 86400;
    $until = $midnight->modify('+1 day')->getTimestamp() + 86400;
    return pg_booking_slots($engine[0], $engine[1], $day, pg_booking_manage_busy($row, $engine[1], $from, $until, $now), $now, $policy);
}

/**
 * Cancels a booking for its customer: one UPDATE from the status read, the
 * slot freed in it, then booking.cancelled with its mail and text.
 *
 * @param array $row
 * @param int   $now
 * @return bool false when the row was changed meanwhile or the query failed
 */
function pg_booking_manage_cancel($row, $now)
{
    $result = db_q(
        "UPDATE bookings SET status = 'cancelled', slot_at = NULL, hold_until = 0, updated_at = ?
        WHERE id = ? AND status = ?",
        array((int) $now, (int) $row['id'], (string) $row['status']));

    if (!$result || (mysqli_affected_rows(db::$con) !== 1)) {
        return false;
    }

    pg_booking_changed((int) $row['id'], 'cancelled');

    return true;
}

/**
 * Moves a booking to a new start under the staff member's lock: another
 * live booking overlapping the new time with the service's buffers refuses
 * it, then one UPDATE moves start, end and slot from the times read and
 * stores the new link's hash (the link follows end_at). 1062 on the unique
 * key is "just taken".
 *
 * @param array $row
 * @param array $service booking_services row
 * @param int   $start
 * @param int   $now
 * @return array answer 'ok' / 'taken' / 'changed' / 'error', token
 */
function pg_booking_manage_move($row, $service, $start, $now)
{
    $id = (int) $row['id'];
    $staff_id = (int) $row['staff_id'];
    $start = (int) $start;
    $end = $start + ((int) $service['length_minutes'] * 60);
    $lock = pg_booking_lock_name($staff_id);

    if ((int) db_value("SELECT GET_LOCK('" . e($lock) . "', 10)") !== 1) {
        return array('answer' => 'error', 'token' => '');
    }

    pg_booking_clear_expired_hold($staff_id, $start, $now);

    $before = (int) $service['buffer_before'] * 60;
    $after = (int) $service['buffer_after'] * 60;

    $rows = db_items(
        "SELECT start_at, end_at, status, hold_until FROM bookings
        WHERE
            staff_id = '" . $staff_id . "'
            AND id <> '" . $id . "'
            AND start_at < '" . ($end + $after) . "'
            AND end_at > '" . ($start - $before) . "'
            AND status <> 'cancelled'");

    if (pg_booking_overlaps($start, $service, pg_booking_busy_from_rows($rows, $now))) {
        db_value("SELECT RELEASE_LOCK('" . e($lock) . "')");
        return array('answer' => 'taken', 'token' => '');
    }

    $token = pg_booking_manage_token($id, $end, pg_booking_key());

    $result = db_q(
        "UPDATE bookings SET start_at = ?, end_at = ?, slot_at = ?, manage_token = ?, updated_at = ?
        WHERE id = ? AND status = ? AND start_at = ?",
        array($start, $end, $start, ($token !== '') ? hash('sha256', $token) : '', (int) $now, $id, (string) $row['status'], (int) $row['start_at']));

    $answer = pg_booking_insert_answer($result, mysqli_errno(db::$con));

    if (($answer === 'ok') && (mysqli_affected_rows(db::$con) !== 1)) {
        $answer = 'changed';
    }

    db_value("SELECT RELEASE_LOCK('" . e($lock) . "')");

    if ($answer !== 'ok') {
        return array('answer' => ($answer === 'taken') ? 'taken' : (($answer === 'changed') ? 'changed' : 'error'), 'token' => '');
    }

    pg_booking_changed($id, 'rescheduled');

    return array('answer' => 'ok', 'token' => $token);
}

/**
 * A signed-in member's bookings with the service's and staff member's
 * names, the latest 50.
 *
 * @param int $user_id
 * @return array
 */
function pg_booking_customer_rows($user_id)
{
    if (((int) $user_id <= 0) || !pg_booking_ready()) {
        return array();
    }

    $rows = (array) db_items(
        "SELECT
            bookings.*,
            booking_services.name AS service_name,
            user.user_username AS staff_username,
            contacts.first_name AS staff_first_name,
            contacts.last_name AS staff_last_name
        FROM bookings
        LEFT JOIN booking_services ON booking_services.id = bookings.service_id
        LEFT JOIN booking_staff ON booking_staff.id = bookings.staff_id
        LEFT JOIN user ON user.user_id = booking_staff.user_id
        LEFT JOIN contacts ON contacts.id = user.user_contact
        WHERE bookings.user_id = '" . (int) $user_id . "' AND bookings.status <> 'held'
        ORDER BY bookings.start_at DESC
        LIMIT 50");

    foreach ($rows as $index => $row) {
        // The contact's name only: a login name never reaches the site.
        $name = pg_contact_display_name($row['staff_first_name'], $row['staff_last_name']);
        $rows[$index]['staff_name'] = $name;
    }

    return $rows;
}

/**
 * The bookings section of the my_account widget: upcoming bookings with a
 * link to their manage page (cancel, move, .ics), then past ones. Site
 * output, Bootstrap 5.3.8. '' when the member has none.
 *
 * @param array $rows pg_booking_customer_rows()
 * @param int   $now
 * @return string
 */
function pg_booking_customer_section_html($rows, $now)
{
    $split = pg_booking_customer_split($rows, $now);

    if (!$split['upcoming'] && !$split['past']) {
        return '';
    }

    $context = pg_booking_message_context(array('id' => 0, 'end_at' => 0, 'status' => 'cancelled'));
    $policy = pg_booking_policy();
    $badges = array('requested' => 'text-bg-warning', 'confirmed' => 'text-bg-success');
    $html = '';

    foreach (array('upcoming' => lang('Upcoming'), 'past' => lang('Past')) as $part => $heading) {
        if (!$split[$part]) {
            continue;
        }

        $html .= '<h6 class="mt-3 mb-2">' . h($heading) . '</h6><div class="list-group pg-sw-bookings">';

        foreach ($split[$part] as $row) {
            $times = pg_booking_message_times((int) $row['start_at'], (int) $row['end_at'], $context['zone'], $context['date_code'], $context['time_code']);
            $status = (string) $row['status'];
            $deposit = pg_booking_deposit_label(pg_booking_deposit_view($row));
            $html .= '<div class="list-group-item">'
                . '<div class="d-flex flex-wrap justify-content-between align-items-center gap-2">'
                . '<span class="fw-semibold">' . h((string) $row['service_name']) . '</span>'
                . '<span class="badge ' . (isset($badges[$status]) ? $badges[$status] : 'text-bg-secondary') . '">' . h(pg_booking_customer_status_label($status)) . '</span>'
                . '</div>'
                . '<div class="small text-body-secondary">' . h($times['date'] . ' ' . $times['time'])
                . (((string) $row['staff_name'] !== '') ? ' · ' . h((string) $row['staff_name']) : '')
                . (($deposit !== '') ? ' · ' . h($deposit) : '') . '</div>';

            $url = ($part === 'upcoming') ? pg_booking_manage_url((int) $row['id'], (int) $row['end_at']) : '';

            if (($url !== '') && ((string) $row['manage_token'] !== '')) {
                $rights = pg_booking_manage_rights($row, $policy, $now);
                $html .= '<div class="d-flex flex-wrap gap-2 mt-2">'
                    . '<a href="' . h($url) . '" class="btn btn-sm btn-outline-primary" rel="nofollow">'
                    . h(($rights['cancel'] || $rights['reschedule']) ? lang('Change or cancel') : lang('View booking')) . '</a>'
                    . '<a href="' . h($url . '&ics=1') . '" class="btn btn-sm btn-outline-secondary" rel="nofollow">' . h(lang('Add to calendar')) . '</a>'
                    . '</div>';
            }

            $html .= '</div>';
        }

        $html .= '</div>';
    }

    return $html;
}
