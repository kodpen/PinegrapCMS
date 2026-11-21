<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// A module of functions.php: the rules of volunteer shifts, with no database:
// who fits a role on a shift (skills, trainings, a document valid then, free
// hours, no other shift at that time), the waitlist pass, the cancel cut-off,
// the unfilled call's pick, the show-up rate, hours sums, the document
// notices and the signed link's token. Everything takes plain arrays; the
// store, the pages and the jobs call these, nothing here reads a table.
//
// Times are the site's wall-clock 'YYYY-MM-DD HH:MM[:SS]' strings (DATETIME
// columns) and are compared as written, so no time zone is involved.
if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/** A programme's cancel cut-off when it sets none, in hours before the start. */
if (!defined('PG_VOLUNTEER_CUTOFF_HOURS')) {
    define('PG_VOLUNTEER_CUTOFF_HOURS', 24);
}

/** How many days before a short shift the call goes out when none is set. */
if (!defined('PG_VOLUNTEER_CALL_DAYS')) {
    define('PG_VOLUNTEER_CALL_DAYS', 3);
}

/** The show-up rate counts this many of the volunteer's last shifts. */
if (!defined('PG_VOLUNTEER_SHOW_UP_SHIFTS')) {
    define('PG_VOLUNTEER_SHOW_UP_SHIFTS', 10);
}

/**
 * A wall-clock time as whole minutes since 1970-01-01 00:00, read as written
 * (no time zone). Null for anything that is not a real date and time. Pure.
 *
 * @param mixed $value 'YYYY-MM-DD HH:MM' or 'YYYY-MM-DD HH:MM:SS'
 * @return int|null
 */
function pg_volunteer_minutes($value)
{
    if (!is_string($value) || (preg_match('/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})(?::(\d{2}))?$/D', trim($value), $m) !== 1)) {
        return null;
    }

    if (!checkdate((int) $m[2], (int) $m[3], (int) $m[1]) || ((int) $m[4] > 23) || ((int) $m[5] > 59)) {
        return null;
    }

    return intdiv(gmmktime((int) $m[4], (int) $m[5], 0, (int) $m[2], (int) $m[3], (int) $m[1]), 60);
}

/**
 * A 'YYYY-MM-DD' date as whole days since 1970-01-01; null when it is no
 * real date. Pure.
 *
 * @param mixed $value
 * @return int|null
 */
function pg_volunteer_day_number($value)
{
    if (!is_string($value) || (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/D', trim($value), $m) !== 1)
        || !checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
        return null;
    }

    return intdiv(gmmktime(0, 0, 0, (int) $m[2], (int) $m[3], (int) $m[1]), 86400);
}

/**
 * Days from one date to another (negative when the second is earlier); null
 * when either is no date. Pure.
 *
 * @param string $from 'YYYY-MM-DD'
 * @param string $to   'YYYY-MM-DD'
 * @return int|null
 */
function pg_volunteer_days_between($from, $to)
{
    $a = pg_volunteer_day_number($from);
    $b = pg_volunteer_day_number($to);

    return (($a === null) || ($b === null)) ? null : $b - $a;
}

/**
 * A shift's start and end in minutes, or null when either is missing or the
 * end is not after the start. Pure.
 *
 * @param array $shift with 'start' and 'end'
 * @return array|null array(start, end)
 */
function pg_volunteer_shift_span($shift)
{
    $start = (is_array($shift) && isset($shift['start'])) ? pg_volunteer_minutes($shift['start']) : null;
    $end = (is_array($shift) && isset($shift['end'])) ? pg_volunteer_minutes($shift['end']) : null;

    return (($start === null) || ($end === null) || ($end <= $start)) ? null : array($start, $end);
}

/**
 * Whether two shifts share any minute; one ending when the other starts does
 * not overlap. A shift with unreadable times overlaps nothing. Pure.
 *
 * @param array $a
 * @param array $b
 * @return bool
 */
function pg_volunteer_shifts_overlap($a, $b)
{
    $x = pg_volunteer_shift_span($a);
    $y = pg_volunteer_shift_span($b);

    return ($x !== null) && ($y !== null) && ($x[0] < $y[1]) && ($y[0] < $x[1]);
}

/**
 * Whether a document is valid for the whole shift: its valid-until date is
 * the shift's last day or later. A document with no readable date counts as
 * not valid. Pure.
 *
 * @param array $document with 'valid_until' ('YYYY-MM-DD')
 * @param array $shift    with 'start' and 'end'
 * @return bool
 */
function pg_volunteer_document_valid($document, $shift)
{
    $until = (is_array($document) && isset($document['valid_until'])) ? pg_volunteer_day_number($document['valid_until']) : null;
    $span = pg_volunteer_shift_span($shift);

    if (($until === null) || ($span === null)) {
        return false;
    }

    // The last minute worked decides the last day: an end at 00:00 belongs to
    // the day before.
    return $until >= intdiv($span[1] - 1, 1440);
}

/**
 * Whether the shift sits inside the volunteer's weekly free hours (the
 * bookings' hours shape, pg_booking_hours_clean()). A shift past midnight
 * must be free on both days. No free hours given means no limit. Pure.
 *
 * @param mixed $hours JSON text or array, 1 (Monday) to 7 => list of
 *                     array('HH:MM', 'HH:MM')
 * @param array $shift
 * @return bool
 */
function pg_volunteer_free_during($hours, $shift)
{
    $span = pg_volunteer_shift_span($shift);

    if ($span === null) {
        return false;
    }

    $hours = pg_booking_hours_clean($hours);

    if (!$hours) {
        return true;
    }

    for ($day = intdiv($span[0], 1440); $day * 1440 < $span[1]; $day++) {

        $from = max($span[0], $day * 1440) - $day * 1440;
        $to = min($span[1], ($day + 1) * 1440) - $day * 1440;
        // 1970-01-01 was a Thursday (4).
        $weekday = (($day + 3) % 7) + 1;
        $covered = false;

        foreach (isset($hours[$weekday]) ? $hours[$weekday] : array() as $range) {

            if ((pg_booking_minutes($range[0]) <= $from) && (pg_booking_minutes($range[1]) >= $to)) {
                $covered = true;
                break;
            }

        }

        if (!$covered) {
            return false;
        }

    }

    return true;
}

/**
 * A list of values as strings, for comparing ids and keys of either type.
 *
 * @param mixed $list
 * @return array
 */
function _pg_volunteer_strings($list)
{
    $out = array();

    foreach (is_array($list) ? $list : array() as $value) {
        if (is_scalar($value) && (trim((string) $value) !== '')) {
            $out[] = trim((string) $value);
        }
    }

    return $out;
}

/**
 * Why a volunteer cannot take a role on a shift, as codes in a fixed order:
 * 'state' (not Active), 'skill', 'training', 'document' (a needed kind with
 * no copy valid on the shift's days), 'hours' (outside the free hours),
 * 'busy' (another of their shifts overlaps). Empty when they fit. Pure.
 *
 * @param array $volunteer state, skills, trainings (course ids), documents
 *                         (kind, valid_until), hours, shifts (their other
 *                         signed-up shifts: id, start, end)
 * @param array $role      skills, trainings, documents (kinds)
 * @param array $shift     id, start, end
 * @return array
 */
function pg_volunteer_fit_reasons($volunteer, $role, $shift)
{
    $volunteer = is_array($volunteer) ? $volunteer : array();
    $role = is_array($role) ? $role : array();
    $reasons = array();

    if (!isset($volunteer['state']) || (strtolower((string) $volunteer['state']) !== 'active')) {
        $reasons[] = 'state';
    }

    $has = _pg_volunteer_strings(isset($volunteer['skills']) ? $volunteer['skills'] : array());

    if (array_diff(_pg_volunteer_strings(isset($role['skills']) ? $role['skills'] : array()), $has)) {
        $reasons[] = 'skill';
    }

    $done = _pg_volunteer_strings(isset($volunteer['trainings']) ? $volunteer['trainings'] : array());

    if (array_diff(_pg_volunteer_strings(isset($role['trainings']) ? $role['trainings'] : array()), $done)) {
        $reasons[] = 'training';
    }

    $valid_kinds = array();

    foreach ((isset($volunteer['documents']) && is_array($volunteer['documents'])) ? $volunteer['documents'] : array() as $document) {
        if (is_array($document) && isset($document['kind']) && pg_volunteer_document_valid($document, $shift)) {
            $valid_kinds[] = trim((string) $document['kind']);
        }
    }

    if (array_diff(_pg_volunteer_strings(isset($role['documents']) ? $role['documents'] : array()), $valid_kinds)) {
        $reasons[] = 'document';
    }

    if (!pg_volunteer_free_during(isset($volunteer['hours']) ? $volunteer['hours'] : array(), $shift)) {
        $reasons[] = 'hours';
    }

    $shift_id = isset($shift['id']) ? (string) $shift['id'] : '';

    foreach ((isset($volunteer['shifts']) && is_array($volunteer['shifts'])) ? $volunteer['shifts'] : array() as $other) {

        if (($shift_id !== '') && isset($other['id']) && ((string) $other['id'] === $shift_id)) {
            continue;
        }

        if (pg_volunteer_shifts_overlap($shift, $other)) {
            $reasons[] = 'busy';
            break;
        }

    }

    return $reasons;
}

/**
 * Whether a volunteer fits a role on a shift (pg_volunteer_fit_reasons()
 * gives none). Pure.
 *
 * @param array $volunteer
 * @param array $role
 * @param array $shift
 * @return bool
 */
function pg_volunteer_fits($volunteer, $role, $shift)
{
    return pg_volunteer_fit_reasons($volunteer, $role, $shift) === array();
}

/**
 * Whether a sign-up can still be cancelled: at least the cut-off before the
 * shift's start (exactly the cut-off still can). Past it the volunteer offers
 * their place instead. Pure.
 *
 * @param string   $start        the shift's start
 * @param string   $now
 * @param int|null $cutoff_hours the programme's cut-off; null for the default
 * @return bool
 */
function pg_volunteer_can_cancel($start, $now, $cutoff_hours = null)
{
    $start = pg_volunteer_minutes($start);
    $now = pg_volunteer_minutes($now);
    $cutoff = ($cutoff_hours === null) ? PG_VOLUNTEER_CUTOFF_HOURS : max(0, (int) $cutoff_hours);

    return ($start !== null) && ($now !== null) && ($now < $start) && ($start - $now >= $cutoff * 60);
}

/**
 * The waiting sign-ups that take the role's free places, first come first:
 * by 'created', then by id. A row with 'fits' false (no longer fits the
 * role) is passed over. Pure; the caller writes the moves under the shift's
 * lock and tells each one.
 *
 * @param int   $count   the role's places
 * @param array $signups the role's sign-ups on the shift: id, state
 *                       ('signed', 'waiting', 'cancelled' ...), created,
 *                       optional fits
 * @return array ids moved up, in turn
 */
function pg_volunteer_waitlist_pass($count, $signups)
{
    $signed = 0;
    $waiting = array();

    foreach (is_array($signups) ? $signups : array() as $row) {

        $state = isset($row['state']) ? (string) $row['state'] : '';

        if ($state === 'signed') {
            $signed++;
        } elseif (($state === 'waiting') && (!isset($row['fits']) || $row['fits'])) {
            $waiting[] = $row;
        }

    }

    usort($waiting, function ($a, $b) {
        $x = pg_volunteer_minutes(isset($a['created']) ? $a['created'] : '');
        $y = pg_volunteer_minutes(isset($b['created']) ? $b['created'] : '');

        if ($x !== $y) {
            return ($x === null) ? 1 : (($y === null) ? -1 : (($x < $y) ? -1 : 1));
        }

        return (int) $a['id'] - (int) $b['id'];
    });

    $moved = array();

    foreach (array_slice($waiting, 0, max(0, (int) $count - $signed)) as $row) {
        $moved[] = (int) $row['id'];
    }

    return $moved;
}

/**
 * Whether the unfilled call is due: the shift has not started and starts
 * within the set days. Pure.
 *
 * @param array    $shift
 * @param string   $now
 * @param int|null $days null for the default
 * @return bool
 */
function pg_volunteer_call_due($shift, $now, $days = null)
{
    $span = pg_volunteer_shift_span($shift);
    $now = pg_volunteer_minutes($now);
    $days = ($days === null) ? PG_VOLUNTEER_CALL_DAYS : max(0, (int) $days);

    return ($span !== null) && ($now !== null) && ($now < $span[0]) && ($span[0] - $now <= $days * 1440);
}

/**
 * The volunteers the unfilled call goes to for one role of a shift: only
 * while the role is short and the call is due; each fits the role on that
 * shift, has calls on ('calls', true by default), was not called for this
 * shift yet ('called') and has no sign-up on it. When the call goes out at
 * a given hour is the job's matter. Pure.
 *
 * @param array    $shift
 * @param array    $role       with 'count'
 * @param int      $filled     the role's signed places
 * @param array    $taken_ids  volunteer ids with any sign-up on the shift
 * @param array    $volunteers as pg_volunteer_fit_reasons() reads them, with
 *                             id, calls, called
 * @param string   $now
 * @param int|null $days
 * @return array volunteer ids, in the order given
 */
function pg_volunteer_call_pick($shift, $role, $filled, $taken_ids, $volunteers, $now, $days = null)
{
    $count = isset($role['count']) ? (int) $role['count'] : 0;

    if (((int) $filled >= $count) || !pg_volunteer_call_due($shift, $now, $days)) {
        return array();
    }

    $taken = _pg_volunteer_strings($taken_ids);
    $picked = array();

    foreach (is_array($volunteers) ? $volunteers : array() as $volunteer) {

        if (!isset($volunteer['id']) || in_array((string) $volunteer['id'], $taken, true)) {
            continue;
        }

        if ((isset($volunteer['calls']) && !$volunteer['calls']) || !empty($volunteer['called'])) {
            continue;
        }

        if (pg_volunteer_fits($volunteer, $role, $shift)) {
            $picked[] = (int) $volunteer['id'];
        }

    }

    return $picked;
}

/**
 * The show-up rate, a whole percent of the last shifts that came of those
 * that came or did not come; cancels are not counted. Null with nothing to
 * count. Shown to coordinators only, never used alone to keep anyone out.
 * Pure.
 *
 * @param array $rows start, outcome ('came', 'no_show', anything else skipped)
 * @return int|null
 */
function pg_volunteer_show_up_rate($rows)
{
    $counted = array();

    foreach (is_array($rows) ? $rows : array() as $row) {

        $outcome = isset($row['outcome']) ? (string) $row['outcome'] : '';
        $start = pg_volunteer_minutes(isset($row['start']) ? $row['start'] : '');

        if ((($outcome === 'came') || ($outcome === 'no_show')) && ($start !== null)) {
            $counted[] = array($start, $outcome === 'came');
        }

    }

    if (!$counted) {
        return null;
    }

    usort($counted, function ($a, $b) {
        return $b[0] - $a[0];
    });

    $counted = array_slice($counted, 0, PG_VOLUNTEER_SHOW_UP_SHIFTS);
    $came = 0;

    foreach ($counted as $item) {
        $came += $item[1] ? 1 : 0;
    }

    return (int) round(100 * $came / count($counted));
}

/**
 * A sign-up's minutes: the check-in to the check-out when both are there,
 * else the shift's length; a row with no shift (hours worked outside a
 * shift) carries its own 'minutes'. Pure.
 *
 * @param array      $signup check_in, check_out or minutes
 * @param array|null $shift
 * @return int
 */
function pg_volunteer_signup_minutes($signup, $shift)
{
    $in = isset($signup['check_in']) ? pg_volunteer_minutes($signup['check_in']) : null;
    $out = isset($signup['check_out']) ? pg_volunteer_minutes($signup['check_out']) : null;

    if (($in !== null) && ($out !== null) && ($out > $in)) {
        return $out - $in;
    }

    $span = is_array($shift) ? pg_volunteer_shift_span($shift) : null;

    if ($span !== null) {
        return $span[1] - $span[0];
    }

    return isset($signup['minutes']) ? max(0, (int) $signup['minutes']) : 0;
}

/**
 * Whether an hours row counts in a sum: approved and inside the period
 * (both ends included; an empty end is open). Pure.
 *
 * @param array  $row
 * @param string $from
 * @param string $to
 * @return bool
 */
function _pg_volunteer_hours_counts($row, $from, $to)
{
    if (empty($row['approved'])) {
        return false;
    }

    $day = isset($row['day']) ? (string) $row['day'] : '';

    return ((string) $from === '' || strcmp($day, (string) $from) >= 0) && ((string) $to === '' || strcmp($day, (string) $to) <= 0);
}

/**
 * Approved minutes in a period. Hours count only once approved. Pure.
 *
 * @param array  $rows day ('YYYY-MM-DD'), minutes, approved
 * @param string $from '' for no start
 * @param string $to   '' for no end
 * @return int
 */
function pg_volunteer_hours_total($rows, $from = '', $to = '')
{
    $total = 0;

    foreach (is_array($rows) ? $rows : array() as $row) {
        if (_pg_volunteer_hours_counts($row, $from, $to)) {
            $total += max(0, (int) $row['minutes']);
        }
    }

    return $total;
}

/**
 * Approved minutes in a period by one field (programme_id, role,
 * volunteer_id ...), in the order the values first appear. Pure.
 *
 * @param array  $rows
 * @param string $field
 * @param string $from
 * @param string $to
 * @return array value => minutes
 */
function pg_volunteer_hours_by($rows, $field, $from = '', $to = '')
{
    $sums = array();

    foreach (is_array($rows) ? $rows : array() as $row) {

        if (!_pg_volunteer_hours_counts($row, $from, $to) || !isset($row[$field]) || !is_scalar($row[$field])) {
            continue;
        }

        $value = $row[$field];
        $sums[$value] = (isset($sums[$value]) ? $sums[$value] : 0) + max(0, (int) $row['minutes']);
    }

    return $sums;
}

/**
 * Which notice a document's date calls for today: 30 from 30 days before,
 * 7 from 7 days before, each once ($sent lists the ones already sent); in
 * the last 7 days only the 7-day notice. 0 for none, and after the date.
 * A missed day still sends the notice it missed. Pure.
 *
 * @param string $valid_until 'YYYY-MM-DD'
 * @param string $today       'YYYY-MM-DD'
 * @param array  $sent        notices sent, 30 and/or 7
 * @return int 30, 7 or 0
 */
function pg_volunteer_document_notice($valid_until, $today, $sent = array())
{
    $left = pg_volunteer_days_between($today, $valid_until);
    $sent = array_map('intval', is_array($sent) ? $sent : array());

    if (($left === null) || ($left < 0) || ($left > 30)) {
        return 0;
    }

    $notice = ($left <= 7) ? 7 : 30;

    return in_array($notice, $sent, true) ? 0 : $notice;
}

/**
 * The raw token of a volunteer's signed link: a per-feature HMAC of the
 * volunteer and the moment it was issued under the site's key (the pattern
 * of pg_booking_manage_token()), so a new issue withdraws the old link. Only
 * its hash (pg_volunteer_link_token_hash()) is stored.
 *
 * @param int    $volunteer_id
 * @param int    $issued_at
 * @param string $key the site's ENCRYPTION_KEY
 * @return string 64 hex characters, '' without a volunteer or a key
 */
function pg_volunteer_link_token($volunteer_id, $issued_at, $key)
{
    $volunteer_id = (int) $volunteer_id;
    $key = (string) $key;

    if (($volunteer_id <= 0) || ($key === '')) {
        return '';
    }

    return hash_hmac('sha256', 'volunteer-link:' . $volunteer_id . ':' . (int) $issued_at, $key);
}

/**
 * The hash of a link token, the only form stored. Pure.
 *
 * @param string $token
 * @return string
 */
function pg_volunteer_link_token_hash($token)
{
    return hash('sha256', (string) $token);
}

/**
 * Whether a link's token matches the stored hash; an empty hash (withdrawn)
 * matches nothing. Pure.
 *
 * @param mixed  $token  from the link
 * @param string $stored
 * @return bool
 */
function pg_volunteer_link_token_valid($token, $stored)
{
    $stored = (string) $stored;

    if (!is_string($token) || (preg_match('/^[0-9a-f]{64}$/D', $token) !== 1) || (strlen($stored) !== 64)) {
        return false;
    }

    return hash_equals($stored, pg_volunteer_link_token_hash($token));
}

/**
 * Who opens a volunteer document's file, whatever its folder allows: a
 * signed-in user of roles 0 to 2, a coordinator of the volunteer's programme
 * (a user id in its coordinators list), the volunteer signed in (their
 * account's contact is the profile's) and the volunteer whose signed link
 * opened this session. Pure, and PHP only, for get_file.php.
 *
 * @param array $holders the documents holding the file: volunteer_id,
 *                       contact_id (the profile's), coordinators (the
 *                       programme's JSON list of user ids)
 * @param array $viewer  user_id (0: not signed in), role, contact_id (the
 *                       account's contact), link_volunteer_ids (volunteers
 *                       whose signed link this session opened)
 * @return bool
 */
function pg_volunteer_document_access_verdict($holders, $viewer)
{
    $viewer = is_array($viewer) ? $viewer : array();
    $user_id = isset($viewer['user_id']) ? (int) $viewer['user_id'] : 0;
    $contact_id = ($user_id > 0) && isset($viewer['contact_id']) ? (int) $viewer['contact_id'] : 0;
    $linked = array_map('intval', (isset($viewer['link_volunteer_ids']) && is_array($viewer['link_volunteer_ids'])) ? $viewer['link_volunteer_ids'] : array());

    if (($user_id > 0) && isset($viewer['role']) && is_numeric($viewer['role']) && ((int) $viewer['role'] < 3)) {
        return true;
    }

    foreach (is_array($holders) ? $holders : array() as $holder) {

        if (!is_array($holder)) {
            continue;
        }

        $volunteer_id = isset($holder['volunteer_id']) ? (int) $holder['volunteer_id'] : 0;

        if (($volunteer_id > 0) && in_array($volunteer_id, $linked, true)) {
            return true;
        }

        if (($contact_id > 0) && isset($holder['contact_id']) && ((int) $holder['contact_id'] === $contact_id)) {
            return true;
        }

        $coordinators = isset($holder['coordinators']) && is_string($holder['coordinators']) ? json_decode($holder['coordinators'], true) : null;

        if (($user_id > 0) && is_array($coordinators) && in_array((string) $user_id, _pg_volunteer_strings($coordinators), true)) {
            return true;
        }

    }

    return false;
}
