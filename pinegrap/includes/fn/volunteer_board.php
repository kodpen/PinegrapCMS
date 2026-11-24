<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The shift board of a volunteer programme (the Shifts tab of
 * edit_volunteer_programme.php): the week's Monday, the Add shift form read
 * and cleaned, the weekly series, "Copy last week", a role's colour by its
 * places filled, the board's days and places; then the store: the week's
 * shifts with their places filled, one shift with its sign-ups, adding,
 * copying, removing (telling who had signed up) and calling volunteers for
 * a short shift by e-mail. Loaded by that screen only.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// The most weeks one "Repeat weekly" adds: half a year.
if (!defined('PG_VOLUNTEER_SERIES_WEEKS')) {
    define('PG_VOLUNTEER_SERIES_WEEKS', 26);
}

// The most places one role of a shift asks for.
if (!defined('PG_VOLUNTEER_ROLE_MAX')) {
    define('PG_VOLUNTEER_ROLE_MAX', 99);
}

/**
 * A date some days later, counted on the calendar in UTC so no clock change
 * moves the wall-clock day. Pure.
 *
 * @param string $date 'YYYY-MM-DD'
 * @param int    $days
 * @return string 'YYYY-MM-DD'
 */
function pg_volunteer_add_days($date, $days)
{
    $parts = array_map('intval', explode('-', substr((string) $date, 0, 10)) + array(0, 0, 0));

    return gmdate('Y-m-d', gmmktime(0, 0, 0, $parts[1], $parts[2] + (int) $days, $parts[0]));
}

/**
 * The Monday of a date's week; null for a date that is not one. Pure.
 *
 * @param mixed $date 'YYYY-MM-DD'
 * @return string|null
 */
function pg_volunteer_week_monday($date)
{
    if (!is_string($date) || !preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $match) || !checkdate((int) $match[2], (int) $match[3], (int) $match[1])) {
        return null;
    }

    $weekday = (int) gmdate('N', gmmktime(0, 0, 0, (int) $match[2], (int) $match[3], (int) $match[1]));

    return pg_volunteer_add_days($date, 1 - $weekday);
}

/**
 * Moves a 'YYYY-MM-DD HH:MM:SS' time some days on, its clock kept. Pure.
 *
 * @param string $time
 * @param int    $days
 * @return string
 */
function _pg_volunteer_time_add_days($time, $days)
{
    return pg_volunteer_add_days(substr($time, 0, 10), $days) . substr($time, 10);
}

/**
 * The Add shift drawer's fields read and cleaned: the place, the day, the
 * start and end (an end at or before the start is the next day, the same
 * clock is refused), the places each of the programme's roles asks for
 * (0 to PG_VOLUNTEER_ROLE_MAX, a zero or an unknown role dropped, at least
 * one needed), a note, and how many weeks "Repeat weekly" adds. Pure.
 *
 * @param array $post
 * @param array $roles the programme's roles, key => role
 * @return array array('shift' => place, start, end, roles, note; 'weeks'; 'errors')
 */
function pg_volunteer_shift_input(array $post, array $roles)
{
    $errors = array();
    $place = pg_waiting_text($post['place'] ?? '', 100);
    $date = (string) ($post['date'] ?? '');
    $clock = function ($value) {
        return (is_string($value) && preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', $value)) ? $value . ':00' : null;
    };
    $start_at = $clock($post['start_at'] ?? '');
    $end_at = $clock($post['end_at'] ?? '');
    $start = '';
    $end = '';

    if ($place === '') {
        $errors[] = lang('Give the shift a place.');
    }

    if ((pg_volunteer_week_monday($date) === null) || ($start_at === null) || ($end_at === null)) {
        $errors[] = lang('Give the shift a day, a start and an end.');
    } elseif ($start_at === $end_at) {
        $errors[] = lang('The shift ends when it starts: give it a length.');
    } else {
        $start = $date . ' ' . $start_at;
        $end = (($end_at < $start_at) ? pg_volunteer_add_days($date, 1) : $date) . ' ' . $end_at;
    }

    $counts = array();

    foreach ((array) ($post['count'] ?? array()) as $key => $count) {
        $count = min(PG_VOLUNTEER_ROLE_MAX, max(0, (int) $count));

        if (isset($roles[$key]) && ($count > 0)) {
            $counts[(string) $key] = $count;
        }
    }

    if (!$counts) {
        $errors[] = lang('Ask for at least one place in a role.');
    }

    return array(
        'shift'  => array('place' => $place, 'start' => $start, 'end' => $end, 'roles' => $counts, 'note' => pg_waiting_text($post['note'] ?? '', 500)),
        'weeks'  => empty($post['repeat']) ? 1 : min(PG_VOLUNTEER_SERIES_WEEKS, max(1, (int) ($post['weeks'] ?? 1))),
        'errors' => $errors,
    );
}

/**
 * A weekly series' start and end times, the first as given and each next a
 * week later. Pure.
 *
 * @param string $start
 * @param string $end
 * @param int    $weeks at least one
 * @return array list of array(start, end)
 */
function pg_volunteer_series_spans($start, $end, $weeks)
{
    $spans = array();

    for ($week = 0; $week < max(1, (int) $weeks); $week++) {
        $spans[] = array(_pg_volunteer_time_add_days($start, 7 * $week), _pg_volunteer_time_add_days($end, 7 * $week));
    }

    return $spans;
}

/**
 * "Copy last week": last week's shifts a week later, with their roles and
 * note, outside any series; a shift this week with the same place, start
 * and end is not copied again, so a second press adds nothing. Pure.
 *
 * @param array $last_week shifts: place, start, end, roles, note
 * @param array $this_week shifts: place, start, end
 * @return array shifts to add: place, start, end, roles, note
 */
function pg_volunteer_week_copy(array $last_week, array $this_week)
{
    $there = array();

    foreach ($this_week as $shift) {
        $there[$shift['place'] . '|' . $shift['start'] . '|' . $shift['end']] = true;
    }

    $copies = array();

    foreach ($last_week as $shift) {
        $copy = array(
            'place' => (string) $shift['place'],
            'start' => _pg_volunteer_time_add_days((string) $shift['start'], 7),
            'end'   => _pg_volunteer_time_add_days((string) $shift['end'], 7),
            'roles' => (array) $shift['roles'],
            'note'  => (string) ($shift['note'] ?? ''),
        );
        $key = $copy['place'] . '|' . $copy['start'] . '|' . $copy['end'];

        if (!isset($there[$key])) {
            $there[$key] = true;
            $copies[] = $copy;
        }
    }

    return $copies;
}

/**
 * A role's colour on the board: 'full' (teal) when its places are taken,
 * 'near' (rose) when it is short and the shift starts within the call days,
 * else 'short' (amber). Pure.
 *
 * @param int      $filled signed places
 * @param int      $count  places asked
 * @param array    $shift  start, end
 * @param string   $now
 * @param int|null $days   the programme's call days; null or 0 for the default
 * @return string
 */
function pg_volunteer_role_tone($filled, $count, array $shift, $now, $days = null)
{
    if ((int) $filled >= (int) $count) {
        return 'full';
    }

    return pg_volunteer_call_due($shift, $now, ((int) $days > 0) ? (int) $days : null) ? 'near' : 'short';
}

/**
 * The board's seven days from a Monday, each with its shifts by start, then
 * place; a place filter keeps that place's shifts only. Pure.
 *
 * @param array  $shifts with place, start
 * @param string $monday 'YYYY-MM-DD'
 * @param string $place  '' for every place
 * @return array 'YYYY-MM-DD' => list of shifts
 */
function pg_volunteer_board_days(array $shifts, $monday, $place = '')
{
    $days = array();

    for ($day = 0; $day < 7; $day++) {
        $days[pg_volunteer_add_days($monday, $day)] = array();
    }

    usort($shifts, function ($a, $b) {
        return strcmp($a['start'] . '|' . $a['place'], $b['start'] . '|' . $b['place']);
    });

    foreach ($shifts as $shift) {
        $date = substr((string) $shift['start'], 0, 10);

        if (isset($days[$date]) && (($place === '') || ($shift['place'] === $place))) {
            $days[$date][] = $shift;
        }
    }

    return $days;
}

/**
 * The places of some shifts, each once, in order. Pure.
 *
 * @param array $shifts
 * @return array
 */
function pg_volunteer_board_places(array $shifts)
{
    $places = array_values(array_unique(array_filter(array_map('strval', array_column($shifts, 'place')), 'strlen')));
    sort($places);

    return $places;
}

/**
 * Who is told when a shift is removed: the volunteers signed up or on its
 * waitlist, each once. Pure.
 *
 * @param array $signups volunteer_id, state
 * @return array volunteer ids
 */
function pg_volunteer_shift_people_told(array $signups)
{
    $ids = array();

    foreach ($signups as $signup) {
        if (in_array($signup['state'], array('signed', 'waiting'), true)) {
            $ids[] = (int) $signup['volunteer_id'];
        }
    }

    return array_values(array_unique($ids));
}

/**
 * A shift row with its JSON decoded.
 *
 * @param array $row volunteer_shifts row
 * @return array
 */
function pg_volunteer_shift_decode(array $row)
{
    $roles = json_decode((string) $row['roles'], true);
    $called = json_decode((string) $row['called'], true);

    return array(
        'id'           => (int) $row['id'],
        'programme_id' => (int) $row['programme_id'],
        'place'        => (string) $row['place'],
        'start'        => (string) $row['start_time'],
        'end'          => (string) $row['end_time'],
        'roles'        => is_array($roles) ? array_map('intval', $roles) : array(),
        'note'         => (string) $row['note'],
        'series_id'    => (int) $row['series_id'],
        'called'       => is_array($called) ? array_map('intval', $called) : array(),
        'filled'       => array(),
        'waiting'      => array(),
    );
}

/**
 * A programme's shifts starting in a time range, each with its signed and
 * waiting places per role.
 *
 * @param int    $programme_id
 * @param string $from 'YYYY-MM-DD'
 * @param string $to   'YYYY-MM-DD', not included
 * @return array id => shift
 */
function pg_volunteer_board_shifts($programme_id, $from, $to)
{
    $shifts = array();

    foreach ((array) db_items("SELECT * FROM volunteer_shifts WHERE programme_id = '" . (int) $programme_id . "' AND start_time >= '" . escape($from) . "' AND start_time < '" . escape($to) . "' ORDER BY start_time, id") as $row) {
        $shifts[(int) $row['id']] = pg_volunteer_shift_decode($row);
    }

    if ($shifts) {
        foreach ((array) db_items("SELECT shift_id, role, state, COUNT(*) AS n FROM volunteer_signups WHERE shift_id IN (" . implode(', ', array_keys($shifts)) . ") AND state IN ('signed', 'waiting') GROUP BY shift_id, role, state") as $row) {
            $shifts[(int) $row['shift_id']][($row['state'] === 'signed') ? 'filled' : 'waiting'][(string) $row['role']] = (int) $row['n'];
        }
    }

    return $shifts;
}

/**
 * One shift of a programme with its sign-ups and the volunteers' names;
 * null when the programme has no such shift.
 *
 * @param int $programme_id
 * @param int $shift_id
 * @return array|null
 */
function pg_volunteer_shift_get($programme_id, $shift_id)
{
    $row = db_item("SELECT * FROM volunteer_shifts WHERE id = '" . (int) $shift_id . "' AND programme_id = '" . (int) $programme_id . "'");

    if (!$row) {
        return null;
    }

    $shift = pg_volunteer_shift_decode($row);
    $shift['signups'] = array();

    foreach ((array) db_items("SELECT volunteer_signups.id, volunteer_signups.volunteer_id, volunteer_signups.role, volunteer_signups.state, volunteer_signups.offered_at, volunteer_signups.swap_from, volunteer_signups.check_in, volunteer_signups.approved_at, volunteer_signups.created_at, contacts.first_name, contacts.last_name, contacts.email_address FROM volunteer_signups
        INNER JOIN volunteers ON volunteers.id = volunteer_signups.volunteer_id
        LEFT JOIN contacts ON contacts.id = volunteers.contact_id
        WHERE volunteer_signups.shift_id = '" . $shift['id'] . "' ORDER BY volunteer_signups.created_at, volunteer_signups.id") as $signup) {
        $name = trim($signup['first_name'] . ' ' . $signup['last_name']);
        $signup['name'] = ($name !== '') ? $name : (string) $signup['email_address'];
        $shift['signups'][] = $signup;

        if (in_array($signup['state'], array('signed', 'waiting'), true)) {
            $bucket = ($signup['state'] === 'signed') ? 'filled' : 'waiting';
            $shift[$bucket][$signup['role']] = ($shift[$bucket][$signup['role']] ?? 0) + 1;
        }
    }

    return $shift;
}

/**
 * Writes one shift.
 *
 * @param int   $programme_id
 * @param array $shift     place, start, end, roles, note
 * @param int   $series_id
 * @param int   $user_id
 * @return int the new id, 0 when the write failed
 */
function _pg_volunteer_shift_insert($programme_id, array $shift, $series_id, $user_id)
{
    $ok = db_q("INSERT INTO volunteer_shifts (programme_id, place, start_time, end_time, roles, note, series_id, called, created_by, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, '[]', ?, UNIX_TIMESTAMP(), UNIX_TIMESTAMP())",
        array((int) $programme_id, $shift['place'], $shift['start'], $shift['end'], json_encode((object) $shift['roles']), $shift['note'], (int) $series_id, (int) $user_id));

    return $ok ? (int) mysqli_insert_id(db::$con) : 0;
}

/**
 * Adds a shift, or a weekly series of it: the series' shifts carry the
 * first one's id as their series_id.
 *
 * @param int   $programme_id
 * @param array $shift   pg_volunteer_shift_input()'s shift
 * @param int   $weeks
 * @param int   $user_id
 * @return int shifts added
 */
function pg_volunteer_shift_add($programme_id, array $shift, $weeks, $user_id)
{
    $series_id = 0;
    $added = 0;

    foreach (pg_volunteer_series_spans($shift['start'], $shift['end'], $weeks) as $span) {
        $id = _pg_volunteer_shift_insert($programme_id, array('start' => $span[0], 'end' => $span[1]) + $shift, $series_id, $user_id);

        if ($id === 0) {
            break;
        }

        if (($weeks > 1) && ($series_id === 0)) {
            $series_id = $id;
            db_q("UPDATE volunteer_shifts SET series_id = ? WHERE id = ?", array($id, $id));
        }

        $added++;
    }

    return $added;
}

/**
 * "Copy last week" into the week from a Monday, under a lock so two presses
 * at once copy once.
 *
 * @param int    $programme_id
 * @param string $monday
 * @param int    $user_id
 * @return int shifts added
 */
function pg_volunteer_week_copy_store($programme_id, $monday, $user_id)
{
    $lock = 'pg_volunteer_copy_' . (int) $programme_id;

    if ((int) db_value("SELECT GET_LOCK('" . escape($lock) . "', 5)") !== 1) {
        return 0;
    }

    $copies = pg_volunteer_week_copy(
        array_values(pg_volunteer_board_shifts($programme_id, pg_volunteer_add_days($monday, -7), $monday)),
        array_values(pg_volunteer_board_shifts($programme_id, $monday, pg_volunteer_add_days($monday, 7)))
    );
    $added = 0;

    foreach ($copies as $copy) {
        $added += (_pg_volunteer_shift_insert($programme_id, $copy, 0, $user_id) > 0) ? 1 : 0;
    }

    db_value("SELECT RELEASE_LOCK('" . escape($lock) . "')");

    return $added;
}

/**
 * Sends one plain-text e-mail to a volunteer through the queue.
 *
 * @param string $to
 * @param array  $message subject, body
 */
function _pg_volunteer_mail($to, array $message)
{
    email(array(
        'to'                 => $to,
        'from_email_address' => EMAIL_ADDRESS,
        'subject'            => $message['subject'],
        'format'             => 'plain_text',
        'body'               => $message['body'],
        'type'               => 'system',
        'queue'              => true,
    ));
}

/**
 * A shift's day and times as words for a message or a confirm.
 *
 * @param array $shift
 * @return string
 */
function pg_volunteer_shift_when(array $shift)
{
    $start = strtotime($shift['start']);

    return get_absolute_time(array('timestamp' => $start, 'type' => 'date', 'format' => 'plain_text')) . ' ' . date('H:i', $start) . '–' . date('H:i', strtotime($shift['end'])) . ' · ' . $shift['place'];
}

/**
 * Removes a shift with its sign-ups and tells by e-mail the volunteers who
 * had signed up or were waiting. A shift with a check-in or approved hours
 * on it stays: those hours belong to the volunteers.
 *
 * @param array $programme
 * @param array $shift pg_volunteer_shift_get()
 * @return int|false volunteers told, false when the shift stays
 */
function pg_volunteer_shift_remove(array $programme, array $shift)
{
    foreach ($shift['signups'] as $signup) {
        if (($signup['check_in'] !== null) || ((int) $signup['approved_at'] > 0)) {
            return false;
        }
    }

    if (!db_q("DELETE FROM volunteer_shifts WHERE id = ? AND programme_id = ?", array($shift['id'], $programme['id'])) || (mysqli_affected_rows(db::$con) < 1)) {
        return false;
    }

    db_q("DELETE FROM volunteer_signups WHERE shift_id = ?", array($shift['id']));
    $told = pg_volunteer_shift_people_told($shift['signups']);

    foreach ($shift['signups'] as $signup) {
        if (in_array((int) $signup['volunteer_id'], $told, true) && validate_email_address((string) $signup['email_address'])) {
            $told = array_diff($told, array((int) $signup['volunteer_id']));
            _pg_volunteer_mail((string) $signup['email_address'], array(
                'subject' => strtr(lang('{programme}: a shift was cancelled'), array('{programme}' => $programme['name'])),
                'body'    => strtr(lang('Hello {name},') . "\n\n" . lang('The shift of {programme} on {when} was cancelled. You do not need to come.'), array('{name}' => (string) $signup['first_name'], '{programme}' => $programme['name'], '{when}' => pg_volunteer_shift_when($shift))),
            ));
        }
    }

    return count(pg_volunteer_shift_people_told($shift['signups']));
}

/**
 * The programme's active volunteers as pg_volunteer_fit_reasons() reads
 * them: skills, completed courses (by the site account of their contact),
 * documents, free hours, their other signed shifts near this one, calls
 * and whether this shift called them already; with name and e-mail.
 *
 * @param array $programme
 * @param array $shift
 * @return array id => volunteer
 */
function pg_volunteer_call_people(array $programme, array $shift)
{
    $people = array();

    foreach ((array) db_items("SELECT volunteers.id, volunteers.contact_id, volunteers.state, volunteers.skills, volunteers.hours, volunteers.calls, contacts.first_name, contacts.email_address FROM volunteers
        LEFT JOIN contacts ON contacts.id = volunteers.contact_id
        WHERE volunteers.programme_id = '" . (int) $programme['id'] . "' AND volunteers.state = 'active'") as $row) {
        $row['skills'] = pg_volunteer_list_text((array) json_decode((string) $row['skills'], true));
        $row['hours'] = pg_booking_hours_clean($row['hours']);
        $row['calls'] = ((int) $row['calls'] === 1);
        $row['called'] = in_array((int) $row['id'], $shift['called'], true);
        $row['trainings'] = array();
        $row['documents'] = array();
        $row['shifts'] = array();
        $people[(int) $row['id']] = $row;
    }

    if (!$people) {
        return array();
    }

    $ids = implode(', ', array_keys($people));

    foreach ((array) db_items("SELECT volunteer_id, kind, valid_until FROM volunteer_documents WHERE volunteer_id IN (" . $ids . ")") as $row) {
        $people[(int) $row['volunteer_id']]['documents'][] = $row;
    }

    $day_before = pg_volunteer_add_days(substr($shift['start'], 0, 10), -1);
    $day_after = pg_volunteer_add_days(substr($shift['end'], 0, 10), 2);

    foreach ((array) db_items("SELECT volunteer_signups.volunteer_id, volunteer_shifts.id, volunteer_shifts.start_time, volunteer_shifts.end_time FROM volunteer_signups
        INNER JOIN volunteer_shifts ON volunteer_shifts.id = volunteer_signups.shift_id
        WHERE volunteer_signups.volunteer_id IN (" . $ids . ") AND volunteer_signups.state = 'signed' AND volunteer_shifts.start_time >= '" . $day_before . "' AND volunteer_shifts.start_time < '" . $day_after . "'") as $row) {
        $people[(int) $row['volunteer_id']]['shifts'][] = array('id' => (int) $row['id'], 'start' => $row['start_time'], 'end' => $row['end_time']);
    }

    if (pg_course_ready()) {
        $by_contact = array();

        foreach ($people as $id => $person) {
            $by_contact[(int) $person['contact_id']][] = $id;
        }

        foreach ((array) db_items("SELECT user.user_contact AS contact_id, course_progress.course_id FROM course_progress INNER JOIN user ON user.user_id = course_progress.user_id
            WHERE course_progress.kind = '" . (int) pg_course_kind('complete') . "' AND user.user_contact IN (" . implode(', ', array_keys($by_contact)) . ")") as $row) {
            foreach ($by_contact[(int) $row['contact_id']] ?? array() as $id) {
                $people[$id]['trainings'][] = (int) $row['course_id'];
            }
        }
    }

    return $people;
}

/**
 * Whom "Call volunteers" asks for a shift: for each short role, the
 * volunteers pg_volunteer_call_pick() gives, each once.
 *
 * @param array  $programme
 * @param array  $shift pg_volunteer_shift_get()
 * @param array  $people pg_volunteer_call_people()
 * @param string $now
 * @return array volunteer ids
 */
function pg_volunteer_shift_call_ids(array $programme, array $shift, array $people, $now)
{
    $taken = array_column($shift['signups'], 'volunteer_id');
    $ids = array();

    foreach ($shift['roles'] as $key => $count) {
        $role = ($programme['roles'][$key] ?? array()) + array('count' => $count);
        $days = ($programme['call_days'] > 0) ? $programme['call_days'] : null;
        $ids = array_merge($ids, pg_volunteer_call_pick($shift, $role, $shift['filled'][$key] ?? 0, $taken, array_values($people), $now, $days));
    }

    return array_values(array_unique($ids));
}

/**
 * Sends the programme's Call message by e-mail to the volunteers a short
 * shift asks and records them in the shift's 'called', so nobody is asked
 * twice for one shift.
 *
 * @param array $programme
 * @param array $shift pg_volunteer_shift_get()
 * @return int volunteers asked
 */
function pg_volunteer_shift_call(array $programme, array $shift)
{
    $people = pg_volunteer_call_people($programme, $shift);
    $ids = pg_volunteer_shift_call_ids($programme, $shift, $people, date('Y-m-d H:i:s'));

    if (!$ids) {
        return 0;
    }

    db_q("UPDATE volunteer_shifts SET called = ?, updated_at = UNIX_TIMESTAMP() WHERE id = ?", array(json_encode(array_values(array_unique(array_merge($shift['called'], $ids)))), $shift['id']));

    foreach ($ids as $id) {
        if (validate_email_address((string) $people[$id]['email_address'])) {
            $message = pg_volunteer_message($programme['texts'], 'call', array('name' => (string) $people[$id]['first_name'], 'programme' => $programme['name']));
            _pg_volunteer_mail((string) $people[$id]['email_address'], array('subject' => $message['subject'], 'body' => $message['body'] . "\n\n" . pg_volunteer_shift_when($shift)));
        }
    }

    return count($ids);
}
