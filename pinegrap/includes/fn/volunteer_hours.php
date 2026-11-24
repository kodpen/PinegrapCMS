<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Check-in and hours of volunteer shifts: the signed code of a place's QR
 * poster, which shift a scan checks in or out, "Did not come" 30 minutes
 * after the start, the coordinator's list of the day, the hours waiting for
 * approval and their approval, and hours a volunteer worked outside a
 * shift. The pure rules come first, the store after them.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/volunteer_shifts.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/volunteer_board.php');

if (!defined('PG_VOLUNTEER_CHECK_IN_EARLY')) {
    // Minutes before a shift's start from which its poster checks in.
    define('PG_VOLUNTEER_CHECK_IN_EARLY', 60);
}

if (!defined('PG_VOLUNTEER_CHECK_OUT_LATE')) {
    // Minutes after a shift's end until which its poster still checks out.
    define('PG_VOLUNTEER_CHECK_OUT_LATE', 240);
}

if (!defined('PG_VOLUNTEER_NO_SHOW_MINUTES')) {
    // Minutes after the start with no check-in that make "Did not come".
    define('PG_VOLUNTEER_NO_SHOW_MINUTES', 30);
}

if (!defined('PG_VOLUNTEER_OUTSIDE_DAYS')) {
    // How far back a volunteer may enter hours worked outside a shift.
    define('PG_VOLUNTEER_OUTSIDE_DAYS', 90);
}

/**
 * A wall-clock time moved by some minutes, as written, with no time zone
 * (a clock change never moves it). Pure.
 *
 * @param string $time    'YYYY-MM-DD HH:MM[:SS]'
 * @param int    $minutes
 * @return string 'YYYY-MM-DD HH:MM:SS', '' for an unreadable time
 */
function pg_volunteer_time_add($time, $minutes)
{
    if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}(:\d{2})?$/', (string) $time)) {
        return '';
    }

    $stamp = strtotime($time . ' UTC');

    return ($stamp === false) ? '' : gmdate('Y-m-d H:i:s', $stamp + ((int) $minutes * 60));
}

/**
 * The code a place's poster carries: HMAC-SHA256 of the programme and the
 * place under ENCRYPTION_KEY, cut to 20 hex letters; nothing is stored. Pure.
 *
 * @param int    $programme_id
 * @param string $place
 * @param string $key
 * @return string
 */
function pg_volunteer_poster_code($programme_id, $place, $key)
{
    return substr(hash_hmac('sha256', 'volunteer-poster:' . (int) $programme_id . ':' . (string) $place, (string) $key), 0, 20);
}

/**
 * Whether a scanned poster code belongs to the programme and place. Pure.
 *
 * @param int    $programme_id
 * @param string $place
 * @param mixed  $code
 * @param string $key
 * @return bool
 */
function pg_volunteer_poster_code_valid($programme_id, $place, $code, $key)
{
    return ((int) $programme_id > 0) && ((string) $key !== '') && is_string($code) && (strlen($code) === 20)
        && hash_equals(pg_volunteer_poster_code($programme_id, $place, $key), $code);
}

/**
 * What a scan of the poster does to one sign-up. Pure.
 *
 * @param array  $signup state, check_in, check_out
 * @param array  $shift  start, end
 * @param string $now
 * @return string check_in, check_out, early (before the hour before the
 *                start), done (checked out, or the check-out time passed),
 *                over (ended without a check-in), none (not signed)
 */
function pg_volunteer_scan_step(array $signup, array $shift, $now)
{
    if ((string) ($signup['state'] ?? '') !== 'signed') {
        return 'none';
    }

    if ((string) ($signup['check_out'] ?? '') !== '') {
        return 'done';
    }

    $end = (string) $shift['end'];

    if ((string) ($signup['check_in'] ?? '') !== '') {
        return (strcmp((string) $now, pg_volunteer_time_add($end, PG_VOLUNTEER_CHECK_OUT_LATE)) <= 0) ? 'check_out' : 'done';
    }

    if (strcmp((string) $now, pg_volunteer_time_add((string) $shift['start'], -PG_VOLUNTEER_CHECK_IN_EARLY)) < 0) {
        return 'early';
    }

    return (strcmp((string) $now, $end) < 0) ? 'check_in' : 'over';
}

/**
 * The sign-up a scan acts on among a volunteer's rows at one place: an open
 * check-out first, then a check-in, then the next shift too early to start.
 * Pure.
 *
 * @param array  $rows sign-ups, each with 'shift' (start, end)
 * @param string $now
 * @return array|null the row with 'step', null when none fits
 */
function pg_volunteer_scan_pick(array $rows, $now)
{
    $best = null;
    $rank = array('check_out' => 0, 'check_in' => 1, 'early' => 2, 'done' => 3);

    foreach ($rows as $row) {
        $step = pg_volunteer_scan_step($row, $row['shift'], $now);

        if (!isset($rank[$step])) {
            continue;
        }

        if (($best === null) || ($rank[$step] < $rank[$best['step']])
            || (($rank[$step] === $rank[$best['step']]) && (strcmp((string) $row['shift']['start'], (string) $best['shift']['start']) < 0))) {
            $best = $row;
            $best['step'] = $step;
        }
    }

    return $best;
}

/**
 * Whether a sign-up turns into "Did not come": signed, no check-in, no
 * outcome yet, 30 minutes after the start. Pure.
 *
 * @param array  $signup state, check_in, outcome
 * @param string $start
 * @param string $now
 * @return bool
 */
function pg_volunteer_no_show_due(array $signup, $start, $now)
{
    $limit = pg_volunteer_time_add((string) $start, PG_VOLUNTEER_NO_SHOW_MINUTES);

    return ((string) ($signup['state'] ?? '') === 'signed') && ((string) ($signup['check_in'] ?? '') === '')
        && ((string) ($signup['outcome'] ?? '') === '') && ($limit !== '') && (strcmp((string) $now, $limit) >= 0);
}

/**
 * Whether an hours row waits for a coordinator's approval: not approved, a
 * place kept; a shift's row once checked in and either checked out or the
 * shift ended; a row outside a shift at once. Pure.
 *
 * @param array      $signup
 * @param array|null $shift  start, end; null outside a shift
 * @param string     $now
 * @return bool
 */
function pg_volunteer_hours_waiting(array $signup, $shift, $now)
{
    if (((int) ($signup['approved_at'] ?? 0) > 0) || ((string) ($signup['state'] ?? '') !== 'signed')) {
        return false;
    }

    if (!is_array($shift)) {
        return (int) ($signup['minutes'] ?? 0) > 0;
    }

    return ((string) ($signup['check_in'] ?? '') !== '')
        && (((string) ($signup['check_out'] ?? '') !== '') || (strcmp((string) $now, (string) $shift['end']) >= 0));
}

/**
 * Hours worked outside a shift as the volunteer typed them: a day in the
 * last 90 days and not after today, hours from a quarter to 24 (a comma or
 * a point), a note. Pure.
 *
 * @param array  $post  day, hours, note
 * @param string $today 'YYYY-MM-DD'
 * @return array row (worked_on, minutes, note) or error ('day', 'hours')
 */
function pg_volunteer_outside_input(array $post, $today)
{
    $day = is_string($post['day'] ?? null) ? trim($post['day']) : '';
    $hours = is_string($post['hours'] ?? null) ? str_replace(',', '.', trim($post['hours'])) : '';
    $earliest = pg_volunteer_add_days((string) $today, -PG_VOLUNTEER_OUTSIDE_DAYS);

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $day) || !checkdate((int) substr($day, 5, 2), (int) substr($day, 8, 2), (int) substr($day, 0, 4))
        || (strcmp($day, (string) $today) > 0) || (strcmp($day, $earliest) < 0)) {
        return array('error' => 'day');
    }

    $minutes = preg_match('/^\d{1,2}(\.\d{1,2})?$/', $hours) ? (int) round(((float) $hours) * 60) : 0;

    if (($minutes < 15) || ($minutes > 1440)) {
        return array('error' => 'hours');
    }

    $note = is_string($post['note'] ?? null) ? trim(preg_replace('/\s+/u', ' ', $post['note'])) : '';

    return array('row' => array('worked_on' => $day, 'minutes' => $minutes, 'note' => mb_substr($note, 0, 255)));
}

/**
 * Minutes as hours and minutes for a list. Pure.
 *
 * @param int $minutes
 * @return string
 */
function pg_volunteer_duration_text($minutes)
{
    $minutes = max(0, (int) $minutes);

    return lang(array('string' => '{var:1} h {var:2} min', 'vars' => array(intdiv($minutes, 60), str_pad((string) ($minutes % 60), 2, '0', STR_PAD_LEFT))));
}

/**
 * The address a place's poster opens on a phone.
 *
 * @param int    $programme_id
 * @param string $place
 * @return string
 */
function pg_volunteer_poster_url($programme_id, $place)
{
    return URL_SCHEME . HOSTNAME_SETTING . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/volunteer_check_in.php?pr=' . (int) $programme_id
        . '&pl=' . rawurlencode((string) $place) . '&c=' . pg_volunteer_poster_code($programme_id, $place, (string) ENCRYPTION_KEY);
}

/**
 * A programme's places, from its shifts.
 *
 * @param int $programme_id
 * @return array place names
 */
function pg_volunteer_programme_places($programme_id)
{
    return array_values(array_filter(array_map('strval', (array) db_values("SELECT DISTINCT place FROM volunteer_shifts WHERE programme_id = '" . (int) $programme_id . "' ORDER BY place")), 'strlen'));
}

/**
 * The sign-ups a scan may act on: the volunteers' signed places on the
 * programme's shifts at the place, near the time.
 *
 * @param array  $volunteer_ids profiles this browser holds
 * @param int    $programme_id
 * @param string $place
 * @param string $now
 * @return array rows with 'shift'
 */
function pg_volunteer_scan_rows(array $volunteer_ids, $programme_id, $place, $now)
{
    $ids = array_filter(array_map('intval', $volunteer_ids));

    if (!$ids) {
        return array();
    }

    $rows = array();

    foreach ((array) db_items("SELECT volunteer_signups.id, volunteer_signups.volunteer_id, volunteer_signups.role, volunteer_signups.state, volunteer_signups.check_in, volunteer_signups.check_out, volunteer_shifts.start_time, volunteer_shifts.end_time, volunteer_shifts.place
        FROM volunteer_signups INNER JOIN volunteer_shifts ON volunteer_shifts.id = volunteer_signups.shift_id
        INNER JOIN volunteers ON volunteers.id = volunteer_signups.volunteer_id AND volunteers.state = 'active'
        WHERE volunteer_signups.volunteer_id IN (" . implode(', ', $ids) . ") AND volunteer_signups.state = 'signed'
            AND volunteer_shifts.programme_id = '" . (int) $programme_id . "' AND volunteer_shifts.place = '" . escape((string) $place) . "'
            AND volunteer_shifts.start_time <= '" . escape(pg_volunteer_time_add($now, 720)) . "'
            AND volunteer_shifts.end_time >= '" . escape(pg_volunteer_time_add($now, -PG_VOLUNTEER_CHECK_OUT_LATE)) . "'
        ORDER BY volunteer_shifts.start_time, volunteer_signups.id") as $row) {
        $row['shift'] = array('start' => (string) $row['start_time'], 'end' => (string) $row['end_time'], 'place' => (string) $row['place']);
        $rows[] = $row;
    }

    return $rows;
}

/**
 * Writes a check-in or check-out once: a second press, or a second phone,
 * changes nothing. A check-in makes the outcome "came", also after "Did not
 * come".
 *
 * @param int    $signup_id
 * @param string $step    check_in, check_out
 * @param int    $user_id the coordinator's, 0 for the volunteer's scan
 * @param string $now
 * @return bool written
 */
function pg_volunteer_check_write($signup_id, $step, $user_id, $now)
{
    if ($step === 'check_in') {
        $ok = db_q("UPDATE volunteer_signups SET check_in = ?, outcome = 'came', checked_by = ?, updated_at = UNIX_TIMESTAMP() WHERE id = ? AND state = 'signed' AND check_in IS NULL AND approved_at = 0", array((string) $now, (int) $user_id, (int) $signup_id));
    } elseif ($step === 'check_out') {
        $ok = db_q("UPDATE volunteer_signups SET check_out = ?, updated_at = UNIX_TIMESTAMP() WHERE id = ? AND state = 'signed' AND check_in IS NOT NULL AND check_in < ? AND check_out IS NULL AND approved_at = 0", array((string) $now, (int) $signup_id, (string) $now));
    } else {
        return false;
    }

    return $ok && (mysqli_affected_rows(db::$con) === 1);
}

/**
 * Marks "Did not come" by hand: a signed place with no check-in.
 *
 * @param int $programme_id
 * @param int $signup_id
 * @return bool
 */
function pg_volunteer_no_show_write($programme_id, $signup_id)
{
    $ok = db_q("UPDATE volunteer_signups SET outcome = 'no_show', updated_at = UNIX_TIMESTAMP() WHERE id = ? AND programme_id = ? AND state = 'signed' AND check_in IS NULL AND shift_id IS NOT NULL", array((int) $signup_id, (int) $programme_id));

    return $ok && (mysqli_affected_rows(db::$con) === 1);
}

/**
 * Turns every signed place of the programme with no check-in 30 minutes
 * after its start into "Did not come" (pg_volunteer_no_show_due()). Run when
 * the Hours tab opens; one conditional write.
 *
 * @param int    $programme_id
 * @param string $now
 * @return int rows marked
 */
function pg_volunteer_no_show_pass($programme_id, $now)
{
    $limit = pg_volunteer_time_add($now, -PG_VOLUNTEER_NO_SHOW_MINUTES);
    $ok = ($limit !== '') && db_q("UPDATE volunteer_signups INNER JOIN volunteer_shifts ON volunteer_shifts.id = volunteer_signups.shift_id
        SET volunteer_signups.outcome = 'no_show', volunteer_signups.updated_at = UNIX_TIMESTAMP()
        WHERE volunteer_signups.programme_id = ? AND volunteer_signups.state = 'signed' AND volunteer_signups.check_in IS NULL
            AND volunteer_signups.outcome = '' AND volunteer_shifts.start_time <= ?", array((int) $programme_id, $limit));

    return $ok ? max(0, (int) mysqli_affected_rows(db::$con)) : 0;
}

/**
 * The volunteer's name on a list row.
 *
 * @param array $row first_name, last_name, email_address
 * @return string
 */
function _pg_volunteer_row_name(array $row)
{
    $name = trim((string) $row['first_name'] . ' ' . (string) $row['last_name']);

    return ($name !== '') ? $name : (string) $row['email_address'];
}

/**
 * The coordinator's list of a day: every signed place on the programme's
 * shifts starting that day, by start and name.
 *
 * @param int    $programme_id
 * @param string $day 'YYYY-MM-DD'
 * @return array rows with name and 'shift'
 */
function pg_volunteer_day_rows($programme_id, $day)
{
    $rows = array();

    foreach ((array) db_items("SELECT volunteer_signups.id, volunteer_signups.volunteer_id, volunteer_signups.role, volunteer_signups.state, volunteer_signups.outcome, volunteer_signups.check_in, volunteer_signups.check_out, volunteer_signups.approved_at,
            volunteer_shifts.id AS shift_id, volunteer_shifts.place, volunteer_shifts.start_time, volunteer_shifts.end_time, contacts.first_name, contacts.last_name, contacts.email_address
        FROM volunteer_signups INNER JOIN volunteer_shifts ON volunteer_shifts.id = volunteer_signups.shift_id
        INNER JOIN volunteers ON volunteers.id = volunteer_signups.volunteer_id LEFT JOIN contacts ON contacts.id = volunteers.contact_id
        WHERE volunteer_shifts.programme_id = '" . (int) $programme_id . "' AND volunteer_signups.state = 'signed'
            AND volunteer_shifts.start_time >= '" . escape($day) . " 00:00:00' AND volunteer_shifts.start_time < '" . escape(pg_volunteer_add_days($day, 1)) . " 00:00:00'
        ORDER BY volunteer_shifts.start_time, volunteer_shifts.id, contacts.first_name, contacts.last_name") as $row) {
        $row['name'] = _pg_volunteer_row_name($row);
        $row['shift'] = array('id' => (int) $row['shift_id'], 'start' => (string) $row['start_time'], 'end' => (string) $row['end_time'], 'place' => (string) $row['place']);
        $rows[] = $row;
    }

    return $rows;
}

/**
 * The hours rows of a programme waiting for approval, with their minutes
 * (pg_volunteer_signup_minutes()).
 *
 * @param int    $programme_id
 * @param string $now
 * @return array id => row with name, 'shift' (null outside a shift), minutes_worked
 */
function pg_volunteer_hours_queue($programme_id, $now)
{
    $rows = array();

    foreach ((array) db_items("SELECT volunteer_signups.*, volunteer_shifts.place, volunteer_shifts.start_time, volunteer_shifts.end_time, contacts.first_name, contacts.last_name, contacts.email_address
        FROM volunteer_signups LEFT JOIN volunteer_shifts ON volunteer_shifts.id = volunteer_signups.shift_id
        INNER JOIN volunteers ON volunteers.id = volunteer_signups.volunteer_id LEFT JOIN contacts ON contacts.id = volunteers.contact_id
        WHERE volunteer_signups.programme_id = '" . (int) $programme_id . "' AND volunteer_signups.approved_at = 0 AND volunteer_signups.state = 'signed'
            AND (volunteer_signups.shift_id IS NULL OR volunteer_signups.check_in IS NOT NULL)
        ORDER BY COALESCE(volunteer_shifts.start_time, volunteer_signups.worked_on), volunteer_signups.id LIMIT 500") as $row) {
        $shift = ($row['shift_id'] !== null) ? array('start' => (string) $row['start_time'], 'end' => (string) $row['end_time'], 'place' => (string) $row['place']) : null;

        if (!pg_volunteer_hours_waiting($row, $shift, $now)) {
            continue;
        }

        $row['name'] = _pg_volunteer_row_name($row);
        $row['shift'] = $shift;
        $row['minutes_worked'] = min(65535, pg_volunteer_signup_minutes($row, $shift));
        $rows[(int) $row['id']] = $row;
    }

    return $rows;
}

/**
 * Approves hours rows of the queue: stamps who and when and keeps the
 * minutes counted then; a row approved meanwhile is not written twice.
 *
 * @param int   $programme_id
 * @param array $ids
 * @param array $queue   pg_volunteer_hours_queue()
 * @param int   $user_id
 * @return int rows approved
 */
function pg_volunteer_hours_approve($programme_id, array $ids, array $queue, $user_id)
{
    $done = 0;

    foreach (array_unique(array_map('intval', $ids)) as $id) {
        if (!isset($queue[$id])) {
            continue;
        }

        $ok = db_q("UPDATE volunteer_signups SET approved_at = UNIX_TIMESTAMP(), approved_by = ?, minutes = ?, updated_at = UNIX_TIMESTAMP() WHERE id = ? AND programme_id = ? AND approved_at = 0 AND state = 'signed'",
            array((int) $user_id, (int) $queue[$id]['minutes_worked'], $id, (int) $programme_id));

        if ($ok && (mysqli_affected_rows(db::$con) === 1)) {
            $done++;
        }
    }

    return $done;
}

/**
 * Adds hours a volunteer worked outside a shift: a row with no shift that
 * waits for approval. Active volunteers only.
 *
 * @param int   $volunteer_id
 * @param array $post day, hours, note
 * @return string hours_added, or the refusal (day, hours, closed)
 */
function pg_volunteer_outside_add($volunteer_id, array $post)
{
    $input = pg_volunteer_outside_input($post, date('Y-m-d'));

    if (isset($input['error'])) {
        return $input['error'];
    }

    $volunteer = db_item("SELECT id, programme_id FROM volunteers WHERE id = '" . (int) $volunteer_id . "' AND state = 'active'");

    if (!$volunteer) {
        return 'closed';
    }

    $ok = db_q("INSERT INTO volunteer_signups (programme_id, shift_id, volunteer_id, role, state, worked_on, minutes, note, created_at, updated_at) VALUES (?, NULL, ?, '', 'signed', ?, ?, ?, UNIX_TIMESTAMP(), UNIX_TIMESTAMP())",
        array((int) $volunteer['programme_id'], (int) $volunteer['id'], $input['row']['worked_on'], $input['row']['minutes'], $input['row']['note']));

    return $ok ? 'hours_added' : 'closed';
}
