<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * How a walk-in queue number arrives beyond a plain walk-in: a priority
 * group given at the kiosk or by staff, and changed at the desk; a booking
 * folded in ("I have a booking" finds today's booking with one of the
 * place's Bookings staff by the phone or e-mail it was made with, and the
 * number then stands at the booked time within the grace window); and a
 * person who took a number from the site saying "I'm on my way". Bookings
 * keep their rows and moves: the arrival is only a queue row naming the
 * booking. The matching rules are pure; the reads need pg_waiting_ready()
 * and pg_booking_ready().
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/** Booking states a person may arrive with: the others are over or not booked. */
const PG_WAITING_BOOKING_STATES = array('confirmed', 'requested');

/**
 * A priority group's key when the place has that group, '' otherwise (no
 * priority). Pure.
 *
 * @param mixed $key
 * @param array $groups the place's settings priority list
 * @return string
 */
function pg_waiting_priority_clean($key, $groups)
{
    if (!is_scalar($key) || ((string) $key === '')) {
        return '';
    }

    foreach ((array) $groups as $group) {
        if (is_array($group) && (($group['key'] ?? '') === (string) $key)) {
            return (string) $key;
        }
    }

    return '';
}

/**
 * Whether a place folds bookings in: it names Bookings staff and the
 * bookings are installed.
 *
 * @param array $place
 * @return bool
 */
function pg_waiting_takes_bookings($place)
{
    return !empty($place['settings']['booking_staff']) && function_exists('pg_booking_ready') && pg_booking_ready();
}

/**
 * Whether a booking row was made with this phone or e-mail: the phones
 * compared as pg_sms_number() writes them, the e-mails without case. Pure
 * but for pg_sms_number().
 *
 * @param array  $booking phone, email
 * @param string $phone   as pg_sms_number() gives it, '' for none
 * @param string $email   '' for none
 * @return bool
 */
function pg_waiting_booking_matches($booking, $phone, $email)
{
    $email = strtolower(trim((string) $email));

    if (($email !== '') && (strtolower(trim((string) ($booking['email'] ?? ''))) === $email)) {
        return true;
    }

    if ((string) $phone === '') {
        return false;
    }

    $booked = trim((string) ($booking['phone'] ?? ''));

    return ($booked !== '') && (pg_sms_number($booked) === (string) $phone);
}

/**
 * The booking a person arrives with among the day's matching ones: a live
 * one (confirmed or requested) not already in the queue, the nearest to
 * now. Pure.
 *
 * @param array $bookings rows with id, start_at, status
 * @param int[] $in_queue booking ids that already have a number
 * @param int   $now
 * @return array|null
 */
function pg_waiting_booking_pick($bookings, $in_queue, $now)
{
    $in_queue = array_map('intval', (array) $in_queue);
    $best = null;

    foreach ((array) $bookings as $booking) {

        if (!is_array($booking) || !in_array((string) ($booking['status'] ?? ''), PG_WAITING_BOOKING_STATES, true)
            || in_array((int) ($booking['id'] ?? 0), $in_queue, true)) {
            continue;
        }

        $distance = abs((int) $booking['start_at'] - (int) $now);

        if (($best === null) || ($distance < $best[0]) || (($distance === $best[0]) && ((int) $booking['id'] < (int) $best[1]['id']))) {
            $best = array($distance, $booking);
        }

    }

    return ($best === null) ? null : $best[1];
}

/**
 * The start and end (exclusive) of a day in the site's zone, unix UTC.
 *
 * @param int $now
 * @return int[] array(start, end)
 */
function pg_waiting_day_bounds($now)
{
    $day = new DateTime('@' . (int) $now);
    $day->setTimezone(new DateTimeZone(date_default_timezone_get()));
    $day->setTime(0, 0, 0);
    $start = $day->getTimestamp();
    $day->modify('+1 day');

    return array($start, $day->getTimestamp());
}

/**
 * Today's booking with one of the place's Bookings staff made with this
 * phone or e-mail and not yet in the queue, or null.
 *
 * @param array    $place
 * @param string   $phone as pg_sms_number() gives it
 * @param string   $email
 * @param int|null $now
 * @return array|null id, start_at, status
 */
function pg_waiting_booking_find($place, $phone, $email, $now = null)
{
    $now = ($now === null) ? time() : (int) $now;

    if (!pg_waiting_takes_bookings($place) || (((string) $phone === '') && ((string) $email === ''))) {
        return null;
    }

    list($start, $end) = pg_waiting_day_bounds($now);
    $staff = implode(',', array_map('intval', $place['settings']['booking_staff']));
    $rows = (array) db_items("SELECT id, start_at, status, phone, email FROM bookings WHERE staff_id IN (" . $staff . ") AND start_at >= '" . $start . "' AND start_at < '" . $end . "' AND status IN ('confirmed', 'requested')");
    $rows = array_values(array_filter($rows, function ($row) use ($phone, $email) {
        return pg_waiting_booking_matches($row, $phone, $email);
    }));

    if (!$rows) {
        return null;
    }

    $in_queue = array_column((array) db_items("SELECT booking_id FROM waiting_tickets WHERE booking_id IN (" . implode(',', array_map('intval', array_column($rows, 'id'))) . ")"), 'booking_id');

    return pg_waiting_booking_pick($rows, $in_queue, $now);
}

/**
 * Whether the person's page offers "I'm on my way": a number taken from
 * the site (not at the place), still waiting, not said yet. Pure.
 *
 * @param array $ticket source, state, on_way_at
 * @return bool
 */
function pg_waiting_on_way_offered($ticket)
{
    return (($ticket['source'] ?? '') === 'site') && (($ticket['state'] ?? '') === 'waiting') && ((int) ($ticket['on_way_at'] ?? 0) === 0);
}

/**
 * "I'm on my way": written once, while the number waits. The desk sees it
 * as the On the way pill.
 *
 * @param array    $ticket
 * @param int|null $now
 * @return bool
 */
function pg_waiting_on_way($ticket, $now = null)
{
    if (!pg_waiting_on_way_offered($ticket)) {
        return false;
    }

    $done = db_q("UPDATE waiting_tickets SET on_way_at = ? WHERE id = ? AND state = 'waiting' AND on_way_at = 0", array(($now === null) ? time() : (int) $now, (int) $ticket['id']));

    return ($done !== false) && (mysqli_affected_rows(db::$con) > 0);
}

/**
 * The desk gives a waiting number a priority group or takes it away ('').
 *
 * @param array  $place
 * @param int    $ticket_id
 * @param string $key       a group's key, '' for none
 * @return bool
 */
function pg_waiting_set_priority($place, $ticket_id, $key)
{
    $key = pg_waiting_priority_clean($key, $place['settings']['priority']);
    $done = db_q("UPDATE waiting_tickets SET priority = ? WHERE id = ? AND place_id = ? AND state = 'waiting'", array($key, (int) $ticket_id, (int) $place['id']));

    return ($done !== false) && (mysqli_affected_rows(db::$con) > 0);
}
