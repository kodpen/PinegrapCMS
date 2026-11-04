<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Appointment bookings (2026.11.0, 11.0.157): the model of the three tables
 * booking_services, booking_staff and bookings, and the slot engine.
 *
 * Times are unix seconds (UTC) in the tables; a staff member's hours are
 * wall-clock times of the site's zone, turned into real time day by day, so a
 * DST change day has one hour less or more to offer. A slot is a start time:
 * the service's length must fit inside the day's hours, and the slot with its
 * buffers before and after must not meet a busy range (another booking, a
 * Workspace calendar event). Buffers keep bookings apart; they do not shorten
 * the hours.
 *
 * A row in bookings takes its slot through the unique key (staff_id,
 * slot_at); slot_at is a copy of start_at on every row but a cancelled one,
 * where it is NULL, so a cancelled booking gives its slot back and keeps its
 * real time (a cancel or a reschedule writes slot_at with the status or the
 * times in one UPDATE). A visitor's hold is a 'held' row that lives until hold_until,
 * confirming turns it into 'requested' or 'confirmed', and a second row for
 * the same staff member and start is refused by the key, which is the "just
 * taken" answer. A slot of another length that only overlaps is refused
 * under a per-staff lock before the INSERT.
 *
 * The manage link's token is an HMAC of the booking and its end under the
 * site's ENCRYPTION_KEY, so a later mail draws the same link from the row;
 * only its sha256 is stored in bookings.manage_token.
 *
 * Every reader asks pg_booking_ready() first: files land before the upgrade
 * creates the tables.
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
 * Whether the upgrade has created the three tables.
 *
 * @return bool
 */
function pg_booking_ready()
{
    return function_exists('pg_schema_has')
        && pg_schema_has('booking_services', 'length_minutes')
        && pg_schema_has('booking_staff', 'hours')
        && pg_schema_has('bookings', 'hold_until');
}

/**
 * The values of bookings.status, in the order of the ENUM.
 *
 * @return string[]
 */
function pg_booking_statuses()
{
    return array('held', 'requested', 'confirmed', 'cancelled', 'no_show', 'done');
}

/**
 * Whether a booking may move from one status to another. A hold becomes a
 * request, a confirmed booking or nothing (cancelled); a request is confirmed
 * or cancelled; a confirmed booking ends as done, no-show or cancelled. The
 * last three are final, and nothing goes back to a hold.
 *
 * @param string $from
 * @param string $to
 * @return bool
 */
function pg_booking_can_move($from, $to)
{
    $moves = array(
        'held'      => array('requested', 'confirmed', 'cancelled'),
        'requested' => array('confirmed', 'cancelled'),
        'confirmed' => array('cancelled', 'no_show', 'done'),
    );

    return isset($moves[$from]) && in_array($to, $moves[$from], true);
}

/**
 * A wall-clock time "H:MM" or "HH:MM" as minutes of the day, 0-1440 ("24:00"
 * is the end of the day); null for anything else.
 *
 * @param mixed $value
 * @return int|null
 */
function pg_booking_minutes($value)
{
    if (!is_string($value) || (preg_match('/^(\d{1,2}):(\d{2})$/', trim($value), $match) !== 1)) {
        return null;
    }

    $hour = (int) $match[1];
    $minute = (int) $match[2];

    if (($minute > 59) || ($hour > 24) || (($hour === 24) && ($minute !== 0))) {
        return null;
    }

    return ($hour * 60) + $minute;
}

/**
 * A list of [start, end] wall-clock ranges cleaned: unreadable or empty
 * ranges dropped, the rest sorted, and overlapping or touching ones merged.
 *
 * @param mixed $ranges
 * @return array list of array('HH:MM', 'HH:MM')
 */
function pg_booking_ranges_clean($ranges)
{
    if (!is_array($ranges)) {
        return array();
    }

    $spans = array();

    foreach ($ranges as $range) {

        if (!is_array($range) || (count($range) !== 2)) {
            continue;
        }

        $range = array_values($range);
        $start = pg_booking_minutes($range[0]);
        $end = pg_booking_minutes($range[1]);

        if (($start !== null) && ($end !== null) && ($start < $end)) {
            $spans[] = array($start, $end);
        }

    }

    sort($spans);

    $merged = array();

    foreach ($spans as $span) {

        $last = count($merged) - 1;

        if (($last >= 0) && ($span[0] <= $merged[$last][1])) {
            $merged[$last][1] = max($merged[$last][1], $span[1]);
        } else {
            $merged[] = $span;
        }

    }

    $clean = array();

    foreach ($merged as $span) {
        $clean[] = array(sprintf('%02d:%02d', intdiv($span[0], 60), $span[0] % 60), sprintf('%02d:%02d', intdiv($span[1], 60), $span[1] % 60));
    }

    return $clean;
}

/**
 * A JSON text or an array as decoded data; an empty array for anything else.
 *
 * @param mixed $value
 * @return array
 */
function pg_booking_json($value)
{
    if (is_string($value)) {
        $value = json_decode($value, true);
    }

    return is_array($value) ? $value : array();
}

/**
 * A staff member's weekly hours (booking_staff.hours) cleaned: ISO weekday
 * 1 (Monday) to 7 (Sunday) => ranges as pg_booking_ranges_clean(). A day
 * without a range is left out, which is a day off.
 *
 * @param mixed $hours JSON text or array
 * @return array
 */
function pg_booking_hours_clean($hours)
{
    $clean = array();

    foreach (pg_booking_json($hours) as $day => $ranges) {

        $day = (string) $day;

        if (preg_match('/^[1-7]$/', $day) !== 1) {
            continue;
        }

        $ranges = pg_booking_ranges_clean($ranges);

        if ($ranges) {
            $clean[(int) $day] = $ranges;
        }

    }

    ksort($clean);

    return $clean;
}

/**
 * A staff member's dated exceptions (booking_staff.exceptions) cleaned:
 * 'YYYY-MM-DD' => the hours of that day in place of the weekly ones; an empty
 * list is a day off. Impossible dates are dropped.
 *
 * @param mixed $exceptions JSON text or array
 * @return array
 */
function pg_booking_exceptions_clean($exceptions)
{
    $clean = array();

    foreach (pg_booking_json($exceptions) as $date => $ranges) {

        $date = (string) $date;

        if (!pg_booking_date_valid($date)) {
            continue;
        }

        $clean[$date] = pg_booking_ranges_clean($ranges);

    }

    ksort($clean);

    return $clean;
}

/**
 * Whether a text is a real calendar day 'YYYY-MM-DD'.
 *
 * @param mixed $date
 * @return bool
 */
function pg_booking_date_valid($date)
{
    return is_string($date)
        && (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $match) === 1)
        && checkdate((int) $match[2], (int) $match[3], (int) $match[1]);
}

/**
 * The free slot starts of one day for one service and staff member.
 *
 * Pure: everything it needs comes in. The day's hours are its dated
 * exception when there is one, else the weekly hours of its weekday; a
 * holiday closes the day unless an exception sets hours for it. Each range
 * is walked in real time from its start in steps of step_minutes (the
 * service's length by default); a start is free when the service fits before
 * the range's end, the start is no earlier than now plus the lead time and
 * no later than now plus the horizon, and the slot with its buffers meets no
 * busy range.
 *
 * @param array  $service array with length_minutes, buffer_before, buffer_after
 * @param array  $staff   array with hours and exceptions (JSON text or arrays)
 * @param string $day     'YYYY-MM-DD' in the site's zone
 * @param array  $busy    list of array(start, end) unix ranges that are taken
 * @param int    $now     unix time
 * @param array  $policy  zone (default: the PHP zone), lead_minutes (0),
 *                        horizon_days (0: none), step_minutes, holidays (list
 *                        of 'YYYY-MM-DD')
 * @return int[] unix start times, ascending
 */
function pg_booking_slots($service, $staff, $day, $busy, $now, $policy)
{
    $length = isset($service['length_minutes']) ? (int) $service['length_minutes'] : 0;

    if (($length <= 0) || !pg_booking_date_valid($day)) {
        return array();
    }

    $step = !empty($policy['step_minutes']) ? (int) $policy['step_minutes'] : $length;
    $step = max(5, $step);

    try {
        $zone = new DateTimeZone(!empty($policy['zone']) ? (string) $policy['zone'] : date_default_timezone_get());
        $midnight = new DateTime($day . ' 00:00:00', $zone);
    } catch (Exception $exception) {
        return array();
    }

    $exceptions = pg_booking_exceptions_clean(isset($staff['exceptions']) ? $staff['exceptions'] : array());

    if (isset($exceptions[$day])) {

        $ranges = $exceptions[$day];

    } else {

        $holidays = (isset($policy['holidays']) && is_array($policy['holidays'])) ? $policy['holidays'] : array();

        if (in_array($day, $holidays, true)) {
            return array();
        }

        $hours = pg_booking_hours_clean(isset($staff['hours']) ? $staff['hours'] : array());
        $weekday = (int) $midnight->format('N');
        $ranges = isset($hours[$weekday]) ? $hours[$weekday] : array();

    }

    $now = (int) $now;
    $earliest = $now + (isset($policy['lead_minutes']) ? max(0, (int) $policy['lead_minutes']) * 60 : 0);
    $latest = !empty($policy['horizon_days']) ? $now + ((int) $policy['horizon_days'] * 86400) : null;
    $seconds = $length * 60;
    $slots = array();

    foreach ($ranges as $range) {

        $from = pg_booking_wall_time($day, $range[0], $zone);
        $until = pg_booking_wall_time($day, $range[1], $zone);

        for ($start = $from; ($start + $seconds) <= $until; $start += $step * 60) {

            if (($start < $earliest) || (($latest !== null) && ($start > $latest))) {
                continue;
            }

            if (!pg_booking_overlaps($start, $service, $busy)) {
                $slots[] = $start;
            }

        }

    }

    sort($slots);

    return $slots;
}

/**
 * The unix time of a wall-clock "HH:MM" on a day in a zone; "24:00" is the
 * next day's midnight. A time the spring change skips moves forward, as
 * DateTime does.
 *
 * @param string       $day  'YYYY-MM-DD'
 * @param string       $time 'HH:MM'
 * @param DateTimeZone $zone
 * @return int
 */
function pg_booking_wall_time($day, $time, $zone)
{
    if ($time === '24:00') {
        $moment = new DateTime($day . ' 00:00:00', $zone);
        $moment->modify('+1 day');
    } else {
        $moment = new DateTime($day . ' ' . $time . ':00', $zone);
    }

    return $moment->getTimestamp();
}

/**
 * Whether a slot, widened by the service's buffers, meets a busy range.
 * Ranges are half-open: one that ends where the buffer starts is free.
 *
 * @param int   $start   unix start of the slot
 * @param array $service array with length_minutes, buffer_before, buffer_after
 * @param array $busy    list of array(start, end)
 * @return bool
 */
function pg_booking_overlaps($start, $service, $busy)
{
    $from = (int) $start - (isset($service['buffer_before']) ? (int) $service['buffer_before'] * 60 : 0);
    $until = (int) $start + ((int) $service['length_minutes'] * 60) + (isset($service['buffer_after']) ? (int) $service['buffer_after'] * 60 : 0);

    foreach ((array) $busy as $range) {

        if (is_array($range) && (count($range) >= 2)) {

            $range = array_values($range);

            if (($from < (int) $range[1]) && ($until > (int) $range[0])) {
                return true;
            }

        }

    }

    return false;
}

/**
 * The busy ranges of booking rows: a cancelled booking and a hold past its
 * hold_until take no time.
 *
 * @param array $rows rows with start_at, end_at, status, hold_until
 * @param int   $now
 * @return array list of array(start, end)
 */
function pg_booking_busy_from_rows($rows, $now)
{
    $busy = array();

    foreach ((array) $rows as $row) {

        if (pg_booking_slot_holder($row, $now) === 'taken') {
            $busy[] = array((int) $row['start_at'], (int) $row['end_at']);
        }

    }

    return $busy;
}

/**
 * What the row that sits on a slot means for it: 'free' without a row,
 * 'expired' for a hold past hold_until (the slot may be taken again once the
 * row is gone), 'cancelled' for a cancelled booking, else 'taken'.
 *
 * @param array|null $row with status and hold_until
 * @param int        $now
 * @return string
 */
function pg_booking_slot_holder($row, $now)
{
    if (!is_array($row)) {
        return 'free';
    }

    $status = isset($row['status']) ? (string) $row['status'] : '';

    if ($status === 'cancelled') {
        return 'cancelled';
    }

    if (($status === 'held') && ((int) $row['hold_until'] < (int) $now)) {
        return 'expired';
    }

    return 'taken';
}

/**
 * The answer of an INSERT into bookings: 'ok', 'taken' when the unique key
 * (staff_id, slot_at) refused it, else 'error'.
 *
 * @param mixed $result what db_q() returned
 * @param int   $errno  mysqli_errno() after it
 * @return string
 */
function pg_booking_insert_answer($result, $errno)
{
    if ($result) {
        return 'ok';
    }

    return ((int) $errno === 1062) ? 'taken' : 'error';
}

/**
 * The raw token of a booking's manage link: an HMAC of the booking and its
 * end under the site's key, so it can be drawn again from the row and a
 * moved booking gets a new link.
 *
 * @param int    $booking_id
 * @param int    $end_at     bookings.end_at
 * @param string $key        the site's ENCRYPTION_KEY
 * @return string 64 hex characters, '' without a booking or a key
 */
function pg_booking_manage_token($booking_id, $end_at, $key)
{
    $booking_id = (int) $booking_id;
    $key = (string) $key;

    if (($booking_id <= 0) || ($key === '')) {
        return '';
    }

    return hash_hmac('sha256', 'booking-manage:' . $booking_id . ':' . (int) $end_at, $key);
}

/**
 * Whether a manage link's token matches the stored hash and the booking has
 * not ended yet.
 *
 * @param string $token  from the link
 * @param string $stored bookings.manage_token (sha256 of the token)
 * @param int    $end_at bookings.end_at
 * @param int    $now
 * @return bool
 */
function pg_booking_manage_token_valid($token, $stored, $end_at, $now)
{
    $token = (string) $token;
    $stored = (string) $stored;

    if ((preg_match('/^[0-9a-f]{64}$/', $token) !== 1) || (strlen($stored) !== 64)) {
        return false;
    }

    return hash_equals($stored, hash('sha256', $token)) && ((int) $now <= (int) $end_at);
}

/**
 * The site's key for the manage token, '' when none is set.
 *
 * @return string
 */
function pg_booking_key()
{
    return defined('ENCRYPTION_KEY') ? (string) ENCRYPTION_KEY : '';
}

/**
 * The name of the lock a staff member's bookings are written under. GET_LOCK
 * names belong to the whole database server, so the name carries the
 * database.
 *
 * @param int $staff_id
 * @return string
 */
function pg_booking_lock_name($staff_id)
{
    $database = defined('DB_DATABASE') ? DB_DATABASE . '@' . (defined('DB_HOST') ? DB_HOST : '') : (string) db_value("SELECT DATABASE()");

    return 'pg_booking_' . md5((string) $database) . '_' . (int) $staff_id;
}

/**
 * The customer columns of a booking from the caller's data, cut to their
 * column widths.
 *
 * @param array $booking
 * @return array
 */
function pg_booking_customer($booking)
{
    $text = function ($name, $width) use ($booking) {
        $value = isset($booking[$name]) ? trim((string) $booking[$name]) : '';
        return function_exists('mb_substr') ? mb_substr($value, 0, $width, 'UTF-8') : substr($value, 0, $width);
    };

    return array(
        'user_id' => isset($booking['user_id']) ? max(0, (int) $booking['user_id']) : 0,
        'name'    => $text('name', 100),
        'email'   => $text('email', 191),
        'phone'   => $text('phone', 40),
        'notes'   => isset($booking['notes']) ? (string) $booking['notes'] : '',
    );
}

/**
 * Frees a start from a hold that ran out, under the staff member's lock. A
 * hold with a deposit order is cancelled, not removed: a payment that still
 * arrives for the order finds its booking (pg_booking_deposit_mark_paid()).
 *
 * @param int $staff_id
 * @param int $start
 * @param int $now
 */
function pg_booking_clear_expired_hold($staff_id, $start, $now)
{
    if (function_exists('pg_booking_deposit_ready') && pg_booking_deposit_ready()) {
        db_q("UPDATE bookings SET status = 'cancelled', slot_at = NULL, hold_until = 0, updated_at = ? WHERE staff_id = ? AND start_at = ? AND status = 'held' AND hold_until < ? AND order_id > 0",
            array((int) $now, (int) $staff_id, (int) $start, (int) $now));
    }

    db_q("DELETE FROM bookings WHERE staff_id = ? AND start_at = ? AND status = 'held' AND hold_until < ?", array((int) $staff_id, (int) $start, (int) $now));
}

/**
 * Writes one booking row under the staff member's lock: an expired hold on
 * the same start is removed first, a live booking that overlaps the slot with
 * the service's buffers refuses it, and the INSERT itself is refused by the
 * unique key (staff_id, slot_at) when another live row sits on the start.
 *
 * @param array  $booking service (array, as in pg_booking_slots()), service_id,
 *                        staff_id, start_at and the customer columns
 * @param string $status  'held', 'requested' or 'confirmed'
 * @param int    $hold_until
 * @param string $source  bookings.source
 * @param int    $now
 * @return array answer 'ok' / 'taken' / 'invalid' / 'error', id
 */
function pg_booking_insert($booking, $status, $hold_until, $source, $now)
{
    $service = isset($booking['service']) && is_array($booking['service']) ? $booking['service'] : array();
    $staff_id = isset($booking['staff_id']) ? (int) $booking['staff_id'] : 0;
    $start = isset($booking['start_at']) ? (int) $booking['start_at'] : 0;
    $length = isset($service['length_minutes']) ? (int) $service['length_minutes'] : 0;

    if (($staff_id <= 0) || ($start <= 0) || ($length <= 0) || empty($booking['service_id'])) {
        return array('answer' => 'invalid', 'id' => 0);
    }

    $end = $start + ($length * 60);
    $lock = pg_booking_lock_name($staff_id);

    if ((int) db_value("SELECT GET_LOCK('" . e($lock) . "', 10)") !== 1) {
        return array('answer' => 'error', 'id' => 0);
    }

    pg_booking_clear_expired_hold($staff_id, $start, $now);

    $before = isset($service['buffer_before']) ? (int) $service['buffer_before'] * 60 : 0;
    $after = isset($service['buffer_after']) ? (int) $service['buffer_after'] * 60 : 0;

    $rows = db_items(
        "SELECT start_at, end_at, status, hold_until FROM bookings
        WHERE
            staff_id = '" . $staff_id . "'
            AND start_at < '" . ($end + $after) . "'
            AND end_at > '" . ($start - $before) . "'
            AND status <> 'cancelled'");

    if (pg_booking_overlaps($start, $service, pg_booking_busy_from_rows($rows, $now))) {
        db_value("SELECT RELEASE_LOCK('" . e($lock) . "')");
        return array('answer' => 'taken', 'id' => 0);
    }

    $customer = pg_booking_customer($booking);

    $result = db_q(
        "INSERT INTO bookings
            (service_id, staff_id, user_id, name, email, phone, start_at, end_at, slot_at, status, source, manage_token, hold_until, notes, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, '', ?, ?, ?, ?)",
        array((int) $booking['service_id'], $staff_id, $customer['user_id'], $customer['name'], $customer['email'], $customer['phone'],
            $start, $end, $start, (string) $status, substr((string) $source, 0, 20), (int) $hold_until, $customer['notes'], (int) $now, (int) $now));

    $answer = pg_booking_insert_answer($result, mysqli_errno(db::$con));
    $id = ($answer === 'ok') ? (int) mysqli_insert_id(db::$con) : 0;

    db_value("SELECT RELEASE_LOCK('" . e($lock) . "')");

    return array('answer' => $answer, 'id' => $id);
}

/**
 * Holds a slot while the visitor fills the form: a 'held' row that lives
 * until now plus the hold minutes. 'taken' when another row has the slot.
 *
 * @param array $booking      as in pg_booking_insert()
 * @param int   $hold_minutes
 * @param int   $now
 * @param string $source
 * @return array answer, id
 */
function pg_booking_hold($booking, $hold_minutes, $now, $source = 'site')
{
    return pg_booking_insert($booking, 'held', (int) $now + (max(1, (int) $hold_minutes) * 60), $source, $now);
}

/**
 * Confirms a booking. With an id it turns that hold into a request or a
 * confirmed booking while the hold lives ('expired' once it has run out or
 * is gone); without an id it books the slot at once (a staff-made booking)
 * through the same INSERT, 'taken' when another row has it. The manage
 * token's hash is stored and the raw token returned for the link.
 *
 * @param array  $booking as in pg_booking_insert(), id for a hold
 * @param string $status  'requested' or 'confirmed'
 * @param int    $now
 * @param string $source
 * @return array answer 'ok' / 'taken' / 'expired' / 'invalid' / 'error', id, token
 */
function pg_booking_confirm($booking, $status, $now, $source = 'site')
{
    if (!in_array($status, array('requested', 'confirmed'), true)) {
        return array('answer' => 'invalid', 'id' => 0, 'token' => '');
    }

    $id = isset($booking['id']) ? (int) $booking['id'] : 0;
    $customer = pg_booking_customer($booking);

    if ($id > 0) {

        $result = db_q(
            "UPDATE bookings
            SET status = ?, hold_until = 0, user_id = ?, name = ?, email = ?, phone = ?, notes = ?, updated_at = ?
            WHERE id = ? AND status = 'held' AND hold_until >= ?",
            array($status, $customer['user_id'], $customer['name'], $customer['email'], $customer['phone'], $customer['notes'], (int) $now, $id, (int) $now));

        if (!$result) {
            return array('answer' => 'error', 'id' => 0, 'token' => '');
        }

        if (mysqli_affected_rows(db::$con) !== 1) {
            return array('answer' => 'expired', 'id' => 0, 'token' => '');
        }

    } else {

        $written = pg_booking_insert($booking, $status, 0, $source, $now);

        if ($written['answer'] !== 'ok') {
            return $written + array('token' => '');
        }

        $id = $written['id'];

    }

    $end = (int) db_value("SELECT end_at FROM bookings WHERE id = '" . e($id) . "'");
    $token = pg_booking_manage_token($id, $end, pg_booking_key());

    if ($token !== '') {
        db_q("UPDATE bookings SET manage_token = ? WHERE id = ?", array(hash('sha256', $token), $id));
    }

    // booking.created (and booking.confirmed with its mail and text when it
    // is confirmed at once), after the row is complete.
    if (function_exists('pg_booking_created')) {
        pg_booking_created($id, $status);
    }

    return array('answer' => 'ok', 'id' => $id, 'token' => $token);
}
