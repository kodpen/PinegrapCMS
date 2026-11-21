<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The walk-in queue's rules, pure: they read no table and take plain arrays,
 * so the store, the desk, the person's page and the API share one answer.
 *
 * - The order: within a service the oldest number first; a priority person
 *   before everyone else; a booking that arrived within the grace window
 *   goes in at its booked time, after it as a walk-in at its arrival.
 * - A number is its service's letter and the day's count: A-012.
 * - No new number once the daily cap is reached, outside the hours, or when
 *   the estimate passes the closing time.
 * - The estimate: the median handling time of the handled numbers (from 20;
 *   before that the minutes the place gives), times the people ahead,
 *   divided by the open desks serving the service; shown rounded to five
 *   minutes. Without a handling time only "N ahead" is shown.
 * - "Not here" is offered M minutes after the call; the first time a person
 *   may be moved back (a setting, off by default), the second time they
 *   leave.
 * - A number's page opens by a signed link: an HMAC of the number under the
 *   site's ENCRYPTION_KEY (pg_booking_key()), only its sha256 stored, the
 *   way a booking's manage link is.
 *
 * Times are unix UTC; a day and its hours are in the site's zone.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/** Handled numbers a service needs before its own median is used. */
const PG_WAITING_MEDIAN_FROM = 20;

/** The estimate is shown rounded to this many minutes. */
const PG_WAITING_ROUND_MINUTES = 5;

/**
 * The moment a number takes its place in the order. A walk-in stands at the
 * time it joined; a booking that arrived no later than its time plus the
 * grace window stands at its booked time (never earlier, even when it came
 * early), a later one at its arrival as a walk-in.
 *
 * @param array $ticket        joined_at, booking_at (0 without a booking)
 * @param int   $grace_seconds the place's grace window
 * @return int
 */
function pg_waiting_order_time($ticket, $grace_seconds)
{
    $joined = isset($ticket['joined_at']) ? (int) $ticket['joined_at'] : 0;
    $booked = isset($ticket['booking_at']) ? (int) $ticket['booking_at'] : 0;

    if (($booked > 0) && ($joined <= $booked + max(0, (int) $grace_seconds))) {
        return $booked;
    }

    return $joined;
}

/**
 * Whether a number belongs to a priority person: a non-empty reason.
 *
 * @param array $ticket
 * @return bool
 */
function pg_waiting_is_priority($ticket)
{
    return isset($ticket['priority']) && (trim((string) $ticket['priority']) !== '');
}

/**
 * Waiting numbers in the order they are called: priority people first, then
 * by pg_waiting_order_time(), then by id, so two numbers never tie.
 *
 * @param array $tickets       list of array(id, service, joined_at, priority, booking_at)
 * @param int   $grace_seconds
 * @return array the same rows, ordered, re-indexed
 */
function pg_waiting_order($tickets, $grace_seconds)
{
    $keyed = array();

    foreach ((array) $tickets as $ticket) {

        if (!is_array($ticket)) {
            continue;
        }

        $keyed[] = array(
            pg_waiting_is_priority($ticket) ? 0 : 1,
            pg_waiting_order_time($ticket, $grace_seconds),
            isset($ticket['id']) ? (int) $ticket['id'] : 0,
            $ticket,
        );

    }

    usort($keyed, function ($a, $b) {
        for ($i = 0; $i < 3; $i++) {
            if ($a[$i] !== $b[$i]) {
                return ($a[$i] < $b[$i]) ? -1 : 1;
            }
        }
        return 0;
    });

    return array_column($keyed, 3);
}

/**
 * The number a desk calls next among the waiting ones, null when none of
 * the desk's services has anyone waiting.
 *
 * @param array $tickets       waiting numbers as pg_waiting_order()
 * @param array $services      the service keys the desk serves
 * @param int   $grace_seconds
 * @return array|null
 */
function pg_waiting_next($tickets, $services, $grace_seconds)
{
    $services = array_map('strval', (array) $services);

    foreach (pg_waiting_order($tickets, $grace_seconds) as $ticket) {
        if (isset($ticket['service']) && in_array((string) $ticket['service'], $services, true)) {
            return $ticket;
        }
    }

    return null;
}

/**
 * How many people of the same service stand before a number; null when the
 * number is not among the waiting ones.
 *
 * @param array $tickets       waiting numbers as pg_waiting_order()
 * @param int   $ticket_id
 * @param int   $grace_seconds
 * @return int|null
 */
function pg_waiting_ahead($tickets, $ticket_id, $grace_seconds)
{
    $ordered = pg_waiting_order($tickets, $grace_seconds);
    $service = null;

    foreach ($ordered as $ticket) {
        if ((int) $ticket['id'] === (int) $ticket_id) {
            $service = isset($ticket['service']) ? (string) $ticket['service'] : '';
            break;
        }
    }

    if ($service === null) {
        return null;
    }

    $ahead = 0;

    foreach ($ordered as $ticket) {

        if ((int) $ticket['id'] === (int) $ticket_id) {
            break;
        }

        if (isset($ticket['service']) && ((string) $ticket['service'] === $service)) {
            $ahead++;
        }

    }

    return $ahead;
}

/**
 * A service's letter cleaned: one Latin capital A-Z, '' for anything else.
 *
 * @param mixed $letter
 * @return string
 */
function pg_waiting_letter_clean($letter)
{
    $letter = strtoupper(trim((string) $letter));

    return (preg_match('/^[A-Z]$/', $letter) === 1) ? $letter : '';
}

/**
 * The number shown for a service's letter and the day's count: 'A-012'.
 * The count is padded to three digits and keeps going past 999.
 *
 * @param string $letter
 * @param int    $count  1 for the day's first number
 * @return string '' without a letter or a count
 */
function pg_waiting_number($letter, $count)
{
    $letter = pg_waiting_letter_clean($letter);
    $count = (int) $count;

    if (($letter === '') || ($count <= 0)) {
        return '';
    }

    return $letter . '-' . sprintf('%03d', $count);
}

/**
 * The count of a service's next number from the day's last one (0 when the
 * day has none yet).
 *
 * @param int $last
 * @return int
 */
function pg_waiting_next_count($last)
{
    return max(0, (int) $last) + 1;
}

/**
 * Why a new number is refused, '' when it may be given.
 *
 * - 'closed': outside the hours ($closes_at is 0 or already passed).
 * - 'cap': the day's numbers have reached the daily cap (0 is no cap).
 * - 'closing': the estimate for the new number passes the closing time.
 *   Without an estimate only the hours count.
 *
 * @param int      $taken_today      numbers given today at the place
 * @param int      $cap              the place's daily cap, 0 for none
 * @param int|null $estimate_seconds the new number's estimate, null for none
 * @param int      $now
 * @param int      $closes_at        end of the open range now is in, 0 when closed
 * @return string
 */
function pg_waiting_join_refusal($taken_today, $cap, $estimate_seconds, $now, $closes_at)
{
    $now = (int) $now;
    $closes_at = (int) $closes_at;

    if (($closes_at <= 0) || ($now >= $closes_at)) {
        return 'closed';
    }

    if (((int) $cap > 0) && ((int) $taken_today >= (int) $cap)) {
        return 'cap';
    }

    if (($estimate_seconds !== null) && ($now + (int) $estimate_seconds > $closes_at)) {
        return 'closing';
    }

    return '';
}

/**
 * The end of the open range a moment falls in, 0 when the place is closed
 * then. Hours and dated exceptions are in the bookings' shapes
 * (pg_booking_hours_clean(), pg_booking_exceptions_clean()); a dated entry
 * replaces the weekday's hours and an empty one is a day off.
 *
 * @param mixed        $hours      JSON text or array
 * @param mixed        $exceptions JSON text or array
 * @param int          $now
 * @param DateTimeZone $zone       the site's zone
 * @return int
 */
function pg_waiting_closes_at($hours, $exceptions, $now, $zone)
{
    $moment = (new DateTime('@' . (int) $now))->setTimezone($zone);
    $day = $moment->format('Y-m-d');
    $dated = pg_booking_exceptions_clean($exceptions);

    if (array_key_exists($day, $dated)) {
        $ranges = $dated[$day];
    } else {
        $weekly = pg_booking_hours_clean($hours);
        $weekday = (int) $moment->format('N');
        $ranges = isset($weekly[$weekday]) ? $weekly[$weekday] : array();
    }

    foreach ($ranges as $range) {

        $start = pg_booking_wall_time($day, $range[0], $zone);
        $end = pg_booking_wall_time($day, $range[1], $zone);

        if (((int) $now >= $start) && ((int) $now < $end)) {
            return $end;
        }

    }

    return 0;
}

/**
 * The median of a list of numbers, null for an empty list; the mean of the
 * middle two for an even count, rounded to a whole second.
 *
 * @param array $values
 * @return int|null
 */
function pg_waiting_median($values)
{
    $values = array_map('intval', array_values((array) $values));
    $count = count($values);

    if ($count === 0) {
        return null;
    }

    sort($values);
    $middle = intdiv($count, 2);

    if (($count % 2) === 1) {
        return $values[$middle];
    }

    return (int) round(($values[$middle - 1] + $values[$middle]) / 2);
}

/**
 * A service's handling time in seconds: the median of its handled numbers
 * once there are PG_WAITING_MEDIAN_FROM of them, before that the minutes
 * the place gives, null when neither is there.
 *
 * @param array $durations     seconds each handled number took (the caller's window, 14 days)
 * @param int   $start_minutes the place's length for the service, 0 for none
 * @return int|null
 */
function pg_waiting_handling_seconds($durations, $start_minutes)
{
    $durations = (array) $durations;

    if (count($durations) >= PG_WAITING_MEDIAN_FROM) {
        return pg_waiting_median($durations);
    }

    return ((int) $start_minutes > 0) ? ((int) $start_minutes * 60) : null;
}

/**
 * How many open desks serve a service. A paused or closed desk is not
 * counted.
 *
 * @param array  $desks   list of array(state, services)
 * @param string $service
 * @return int
 */
function pg_waiting_open_desks($desks, $service)
{
    $count = 0;

    foreach ((array) $desks as $desk) {

        if (!is_array($desk) || !isset($desk['state']) || ($desk['state'] !== 'open')) {
            continue;
        }

        $services = isset($desk['services']) ? array_map('strval', (array) $desk['services']) : array();

        if (in_array((string) $service, $services, true)) {
            $count++;
        }

    }

    return $count;
}

/**
 * The wait before a number in seconds: the people ahead times the handling
 * time, divided by the open desks. Null without a handling time or an open
 * desk.
 *
 * @param int      $ahead
 * @param int|null $handling_seconds
 * @param int      $open_desks
 * @return int|null
 */
function pg_waiting_estimate_seconds($ahead, $handling_seconds, $open_desks)
{
    if (($handling_seconds === null) || ((int) $open_desks <= 0)) {
        return null;
    }

    return (int) round(max(0, (int) $ahead) * (int) $handling_seconds / (int) $open_desks);
}

/**
 * The estimate as shown, "about" so many minutes: to the nearest five, and
 * never below five while there is a wait. Null stays null.
 *
 * @param int|null $seconds
 * @return int|null
 */
function pg_waiting_estimate_minutes($seconds)
{
    if ($seconds === null) {
        return null;
    }

    $seconds = (int) $seconds;

    if ($seconds <= 0) {
        return 0;
    }

    $step = PG_WAITING_ROUND_MINUTES * 60;

    return max(1, (int) round($seconds / $step)) * PG_WAITING_ROUND_MINUTES;
}

/**
 * Whether "Not here" may be pressed: M minutes have passed since the call.
 *
 * @param int $called_at 0 when the number has not been called
 * @param int $now
 * @param int $minutes   the place's setting
 * @return bool
 */
function pg_waiting_not_here_allowed($called_at, $now, $minutes)
{
    if ((int) $called_at <= 0) {
        return false;
    }

    return (int) $now - (int) $called_at >= max(0, (int) $minutes) * 60;
}

/**
 * What "Not here" does to a number: 'moved_back' the first time when the
 * place moves people back, otherwise 'left'.
 *
 * @param int  $moved_back how many times the number was moved back already
 * @param bool $move_back  the place's setting, off by default
 * @return string
 */
function pg_waiting_not_here_outcome($moved_back, $move_back)
{
    return ($move_back && ((int) $moved_back < 1)) ? 'moved_back' : 'left';
}

/**
 * The raw token of a number's page link: an HMAC of the number and the time
 * it joined under the site's key. Only its sha256 is stored.
 *
 * @param int    $ticket_id
 * @param int    $joined_at
 * @param string $key       the site's ENCRYPTION_KEY (pg_booking_key())
 * @return string 64 hex characters, '' without a number or a key
 */
function pg_waiting_ticket_token($ticket_id, $joined_at, $key)
{
    $ticket_id = (int) $ticket_id;
    $key = (string) $key;

    if (($ticket_id <= 0) || ($key === '')) {
        return '';
    }

    return hash_hmac('sha256', 'waiting-ticket:' . $ticket_id . ':' . (int) $joined_at, $key);
}

/**
 * Whether a link's token matches a number's stored hash. A number whose
 * hash was cleared opens nothing.
 *
 * @param string $token  from the link
 * @param string $stored sha256 of the token
 * @return bool
 */
function pg_waiting_ticket_token_valid($token, $stored)
{
    $token = (string) $token;
    $stored = (string) $stored;

    if ((preg_match('/^[0-9a-f]{64}$/', $token) !== 1) || (strlen($stored) !== 64)) {
        return false;
    }

    return hash_equals($stored, hash('sha256', $token));
}
