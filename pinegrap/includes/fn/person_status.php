<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * A person's own status: a kind (available, busy, in a meeting, out,
 * sleeping, on leave), an optional one-line note and an optional end. Only
 * the person sets it; it informs the team and the channels' agents and holds
 * no notification back (quiet hours stay the only thing that does). It is
 * kept as JSON in ws_profiles.status (kind, note, until, at); an empty value
 * is no status, and a status past its end reads as none, with no job that
 * clears it.
 *
 * Everything here is pure: the caller reads the stored value, the clock, the
 * person's time zone and their quiet hours.
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
 * The longest a note is, in characters.
 */
define('PG_PERSON_STATUS_NOTE_MAX', 80);

/**
 * The furthest a chosen end may lie ahead, in days.
 */
define('PG_PERSON_STATUS_UNTIL_MAX_DAYS', 366);

/**
 * The kinds of status in the order they are offered, with their labels.
 *
 * @return array kind => label
 */
function pg_person_status_kinds()
{
    return array(
        'available' => lang('Available to talk'),
        'busy'      => lang('Busy'),
        'meeting'   => lang('In a meeting'),
        'out'       => lang('Out of office'),
        'sleeping'  => lang('Sleeping'),
        'leave'     => lang('On leave'),
    );
}

/**
 * The ends a person can choose: none, 30 minutes, 1 hour, 4 hours, the end of
 * today, a chosen time (with until), and for sleeping the end of their quiet
 * hours or else 08:00 the next morning.
 *
 * @return string[]
 */
function pg_person_status_ends()
{
    return array('none', '30m', '1h', '4h', 'today', 'morning', 'at');
}

/**
 * The end each kind offers until the person chooses another. Pure.
 *
 * @param string $kind
 * @return string one of pg_person_status_ends(), never 'at'
 */
function pg_person_status_default_end($kind)
{
    $ends = array(
        'busy'     => '1h',
        'meeting'  => '1h',
        'out'      => '4h',
        'sleeping' => 'morning',
        'leave'    => 'today',
    );

    return $ends[(string) $kind] ?? 'none';
}

/**
 * A time zone by name; an unknown name counts as UTC. Pure.
 *
 * @param string|DateTimeZone $zone
 * @return DateTimeZone
 */
function pg_person_status_zone($zone)
{
    if ($zone instanceof DateTimeZone) {
        return $zone;
    }

    try {
        return new DateTimeZone((string) $zone);
    } catch (Exception $error) {
        return new DateTimeZone('UTC');
    }
}

/**
 * The moment an end means, counted from now in the person's own zone: 0 for
 * none, -1 for an end that is not known ('at' is read by the caller from
 * until). 'today' is the coming midnight; 'morning' the next time the clock
 * reads the end of the person's quiet hours, or 08:00 without them. Pure.
 *
 * @param string      $end        one of pg_person_status_ends()
 * @param int         $now
 * @param string      $zone       the person's time zone
 * @param string|null $quiet_end  the end of their quiet hours, HH:MM, or null
 * @return int
 */
function pg_person_status_end_time($end, $now, $zone, $quiet_end = null)
{
    $now = (int) $now;

    switch ((string) $end) {
        case 'none':
            return 0;
        case '30m':
            return $now + 1800;
        case '1h':
            return $now + 3600;
        case '4h':
            return $now + 4 * 3600;
    }

    $local = new DateTime('@' . $now);
    $local->setTimezone(pg_person_status_zone($zone));

    if ($end === 'today') {
        $local->modify('+1 day');
        $local->setTime(0, 0);

        return $local->getTimestamp();
    }

    if ($end === 'morning') {
        $clock = (is_string($quiet_end) && preg_match('/^([01][0-9]|2[0-3]):([0-5][0-9])$/', $quiet_end, $match)) ? array((int) $match[1], (int) $match[2]) : array(8, 0);
        $local->setTime($clock[0], $clock[1]);

        if ($local->getTimestamp() <= $now) {
            $local->modify('+1 day');
            $local->setTime($clock[0], $clock[1]);
        }

        return $local->getTimestamp();
    }

    return -1;
}

/**
 * A chosen end as a moment: a Unix time, or a date and time (ISO 8601; with
 * no offset it is read in the person's zone). 0 when it is none of these.
 * Pure.
 *
 * @param mixed  $value
 * @param string $zone
 * @return int
 */
function pg_person_status_until_read($value, $zone)
{
    if (is_int($value) || (is_string($value) && preg_match('/^[0-9]{9,11}$/', trim($value)))) {
        return max(0, (int) $value);
    }

    if (!is_string($value) || !preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}[T ][0-9]{2}:[0-9]{2}/', trim($value))) {
        return 0;
    }

    try {
        $date = new DateTime(trim($value), pg_person_status_zone($zone));
    } catch (Exception $error) {
        return 0;
    }

    return max(0, $date->getTimestamp());
}

/**
 * The stored status, or null when there is none: an empty or broken value,
 * an unknown kind, or an end that has passed. Pure.
 *
 * @param string|array|null $value the column's JSON, or its decoded array
 * @param int               $now
 * @return array|null kind, note, until (0 for no end), at (when it was set)
 */
function pg_person_status_decode($value, $now)
{
    $data = is_array($value) ? $value : json_decode((string) $value, true);

    if (!is_array($data)) {
        return null;
    }

    $kind = is_string($data['kind'] ?? null) ? $data['kind'] : '';

    if (!array_key_exists($kind, pg_person_status_kinds())) {
        return null;
    }

    $until = max(0, (int) ($data['until'] ?? 0));

    if (($until > 0) && ($until <= (int) $now)) {
        return null;
    }

    return array(
        'kind'  => $kind,
        'note'  => pg_person_status_note_clean($data['note'] ?? ''),
        'until' => $until,
        'at'    => max(0, (int) ($data['at'] ?? 0)),
    );
}

/**
 * A note as one line: line breaks and control characters become spaces,
 * runs of spaces one, and the ends are trimmed. Its length is not cut here.
 * Pure.
 *
 * @param mixed $note
 * @return string
 */
function pg_person_status_note_clean($note)
{
    if (!is_scalar($note)) {
        return '';
    }

    $note = (string) $note;

    if (function_exists('mb_check_encoding') && !mb_check_encoding($note, 'UTF-8')) {
        return '';
    }

    return trim((string) preg_replace('/[\s\x00-\x1F\x7F]+/u', ' ', $note));
}

/**
 * Checks a status a person sends: kind (empty clears the status), note, end
 * and, for the end 'at', until. No end given takes the kind's default; an
 * end of 'none' keeps the status until it is changed. Pure.
 *
 * @param array       $request   kind, note, end, until
 * @param int         $now
 * @param string      $zone      the person's time zone
 * @param string|null $quiet_end the end of their quiet hours, HH:MM, or null
 * @return array ok, value (kind, note, until, at, or null to clear), error, field
 */
function pg_person_status_input($request, $now, $zone, $quiet_end = null)
{
    $request = is_array($request) ? $request : array();
    $now = (int) $now;
    $kind = is_scalar($request['kind'] ?? null) ? trim((string) $request['kind']) : '';

    if ($kind === '') {
        return array('ok' => true, 'value' => null, 'error' => '', 'field' => '');
    }

    if (!array_key_exists($kind, pg_person_status_kinds())) {
        return array('ok' => false, 'value' => null, 'error' => lang('Choose one of the statuses offered.'), 'field' => 'kind');
    }

    $note = pg_person_status_note_clean($request['note'] ?? '');
    $length = function_exists('mb_strlen') ? mb_strlen($note, 'UTF-8') : strlen($note);

    if ($length > PG_PERSON_STATUS_NOTE_MAX) {
        return array('ok' => false, 'value' => null, 'error' => lang(array('string' => 'A note is at most {var:1} characters.', 'vars' => array(PG_PERSON_STATUS_NOTE_MAX))), 'field' => 'note');
    }

    $end = is_scalar($request['end'] ?? null) ? trim((string) $request['end']) : '';
    $end = ($end === '') ? pg_person_status_default_end($kind) : $end;

    if (!in_array($end, pg_person_status_ends(), true)) {
        return array('ok' => false, 'value' => null, 'error' => lang('Choose when the status clears.'), 'field' => 'end');
    }

    if ($end === 'at') {
        $until = pg_person_status_until_read($request['until'] ?? null, $zone);

        if (($until <= $now) || ($until > $now + PG_PERSON_STATUS_UNTIL_MAX_DAYS * 86400)) {
            return array('ok' => false, 'value' => null, 'error' => lang('Choose a time in the coming year.'), 'field' => 'until');
        }
    } else {
        $until = pg_person_status_end_time($end, $now, $zone, $quiet_end);
    }

    return array('ok' => true, 'value' => array('kind' => $kind, 'note' => $note, 'until' => $until, 'at' => $now), 'error' => '', 'field' => '');
}

/**
 * Checks a save of a status before it is written: only a team member, and
 * only their own (a request naming another person is refused, never written
 * for them). Pure.
 *
 * @param array       $viewer    ws_viewer(): id, member
 * @param array       $request   kind, note, end, until, and an optional user_id
 * @param int         $now
 * @param string      $zone      the person's time zone
 * @param string|null $quiet_end the end of their quiet hours, HH:MM, or null
 * @return array ok, user_id, value, error, field
 */
function pg_person_status_save_check($viewer, $request, $now, $zone, $quiet_end = null)
{
    $user_id = (int) ($viewer['id'] ?? 0);
    $request = is_array($request) ? $request : array();

    if (empty($viewer['member']) || ($user_id <= 0)) {
        return array('ok' => false, 'user_id' => 0, 'value' => null, 'error' => lang('Only team members have a status.'), 'field' => '');
    }

    if (isset($request['user_id']) && ((int) $request['user_id'] !== $user_id)) {
        return array('ok' => false, 'user_id' => 0, 'value' => null, 'error' => lang('You can only set your own status.'), 'field' => '');
    }

    $input = pg_person_status_input($request, $now, $zone, $quiet_end);

    return array('ok' => $input['ok'], 'user_id' => $input['ok'] ? $user_id : 0, 'value' => $input['value'], 'error' => $input['error'], 'field' => $input['field']);
}

/**
 * The words of an end in the person's own zone: "until 18:00 today", "until
 * tomorrow 08:00", "until Friday 18:00" within the week, "until 2026-12-24
 * 18:00" further on; '' for no end. Pure.
 *
 * @param int    $until
 * @param int    $now
 * @param string $zone
 * @return string
 */
function pg_person_status_until_words($until, $now, $zone)
{
    $until = (int) $until;

    if ($until <= 0) {
        return '';
    }

    $zone = pg_person_status_zone($zone);
    $end = new DateTime('@' . $until);
    $end->setTimezone($zone);
    $today = new DateTime('@' . (int) $now);
    $today->setTimezone($zone);
    $today->setTime(0, 0);
    $day = clone $end;
    $day->setTime(0, 0);
    $days = (int) round(($day->getTimestamp() - $today->getTimestamp()) / 86400);
    $clock = $end->format('H:i');

    // The coming midnight reads as the end of today.
    if (($clock === '00:00') && ($days === 1)) {
        return lang('until the end of today');
    }

    if ($days <= 0) {
        return lang(array('string' => 'until {var:1} today', 'vars' => array($clock)));
    }

    if ($days === 1) {
        return lang(array('string' => 'until tomorrow {var:1}', 'vars' => array($clock)));
    }

    if ($days < 7) {
        $names = array(lang('Monday'), lang('Tuesday'), lang('Wednesday'), lang('Thursday'), lang('Friday'), lang('Saturday'), lang('Sunday'));

        return lang(array('string' => 'until {var:1} {var:2}', 'vars' => array($names[(int) $end->format('N') - 1], $clock)));
    }

    return lang(array('string' => 'until {var:1} {var:2}', 'vars' => array($end->format('Y-m-d'), $clock)));
}

/**
 * A status as the screens and the API give it, with its label and the words
 * of its end in the person's zone; null for none. Pure.
 *
 * @param array|null $status pg_person_status_decode()
 * @param int        $now
 * @param string     $zone
 * @return array|null kind, label, note, until, until_words, at
 */
function pg_person_status_present($status, $now, $zone)
{
    if (!is_array($status) || !isset($status['kind'])) {
        return null;
    }

    $kinds = pg_person_status_kinds();

    return array(
        'kind'        => (string) $status['kind'],
        'label'       => (string) ($kinds[$status['kind']] ?? $status['kind']),
        'note'        => (string) ($status['note'] ?? ''),
        'until'       => (int) ($status['until'] ?? 0),
        'until_words' => pg_person_status_until_words((int) ($status['until'] ?? 0), $now, $zone),
        'at'          => (int) ($status['at'] ?? 0),
    );
}
