<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * A person's quiet hours: a daily range (start and end as HH:MM, over
 * midnight when the end comes first) and, when off_days is set, the whole of
 * the days they do not work. Inside it no Workspace push wakes their devices:
 * the push waits for the range's end (includes/workspace/notify.php). The
 * setting is kept as JSON in ws_profiles.quiet_hours; an empty value, or a
 * start equal to the end, is no quiet range at all.
 *
 * Everything here is pure: the caller reads the setting, the person's time
 * zone and working weekdays.
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
 * The longest a quiet range is looked ahead for its end, in days: a week of
 * days off and the range after them.
 */
define('PG_QUIET_HOURS_LOOKAHEAD_DAYS', 8);

/**
 * A clock time "HH:MM" as minutes after midnight, or -1 when it is none. Pure.
 *
 * @param mixed $value
 * @return int
 */
function pg_quiet_hours_minutes($value)
{
    if (!is_string($value) || !preg_match('/^([01][0-9]|2[0-3]):([0-5][0-9])$/', $value, $match)) {
        return -1;
    }

    return (int) $match[1] * 60 + (int) $match[2];
}

/**
 * The stored setting, or null when there is no quiet range: an empty or
 * broken value, a time that is no clock time, a start equal to the end. Pure.
 *
 * @param string|array|null $value the column's JSON, or its decoded array
 * @return array|null start, end (HH:MM), off_days (0 or 1)
 */
function pg_quiet_hours_decode($value)
{
    $data = is_array($value) ? $value : json_decode((string) $value, true);

    if (!is_array($data)) {
        return null;
    }

    $start = pg_quiet_hours_minutes($data['start'] ?? null);
    $end = pg_quiet_hours_minutes($data['end'] ?? null);

    if (($start < 0) || ($end < 0) || ($start === $end)) {
        return null;
    }

    return array('start' => (string) $data['start'], 'end' => (string) $data['end'], 'off_days' => empty($data['off_days']) ? 0 : 1);
}

/**
 * Is this moment inside the quiet range? Pure.
 *
 * @param array        $setting  pg_quiet_hours_decode()
 * @param DateTimeZone $zone
 * @param int          $workdays the working weekdays, bit 0 Monday
 * @param int          $moment
 * @return bool
 */
function pg_quiet_hours_at($setting, $zone, $workdays, $moment)
{
    $local = new DateTime('@' . (int) $moment);
    $local->setTimezone($zone);

    // A day off is quiet all day; with no working day known, none is off.
    $workdays = (int) $workdays & 127;

    if (!empty($setting['off_days']) && ($workdays > 0) && !($workdays & (1 << ((int) $local->format('N') - 1)))) {
        return true;
    }

    $minute = (int) $local->format('G') * 60 + (int) $local->format('i');
    $start = pg_quiet_hours_minutes($setting['start']);
    $end = pg_quiet_hours_minutes($setting['end']);

    return ($start < $end) ? (($minute >= $start) && ($minute < $end)) : (($minute >= $start) || ($minute < $end));
}

/**
 * How many seconds of quiet are left at this moment: 0 outside the range,
 * else the time to the first moment that is no longer quiet (the range's
 * end, or the end of the days off). Counted in the person's own time zone,
 * so a range keeps its clock times on a day the clock changes. Pure.
 *
 * @param array|string|null $setting  the stored value or pg_quiet_hours_decode()
 * @param string            $zone     the person's time zone; an unknown name
 *                                    counts as UTC
 * @param int               $workdays the working weekdays, bit 0 Monday
 * @param int               $now
 * @return int
 */
function pg_quiet_hours_left($setting, $zone, $workdays, $now)
{
    $setting = pg_quiet_hours_decode($setting);

    if ($setting === null) {
        return 0;
    }

    try {
        $zone = new DateTimeZone((string) $zone);
    } catch (Exception $error) {
        $zone = new DateTimeZone('UTC');
    }

    $now = (int) $now;

    if (!pg_quiet_hours_at($setting, $zone, $workdays, $now)) {
        return 0;
    }

    // The moments quiet can end at: each day's midnight (a day off ends
    // there) and each day's range end, in the person's zone.
    $end = pg_quiet_hours_minutes($setting['end']);
    $day = new DateTime('@' . $now);
    $day->setTimezone($zone);
    $day->setTime(0, 0);
    $candidates = array();

    for ($i = 0; $i <= PG_QUIET_HOURS_LOOKAHEAD_DAYS; $i++) {
        $date = clone $day;
        $date->modify('+' . $i . ' day');
        $date->setTime(0, 0);
        $candidates[] = $date->getTimestamp();
        $date->setTime(intdiv($end, 60), $end % 60);
        $candidates[] = $date->getTimestamp();
    }

    sort($candidates);

    foreach ($candidates as $moment) {
        if (($moment > $now) && !pg_quiet_hours_at($setting, $zone, $workdays, $moment)) {
            return $moment - $now;
        }
    }

    // Never quiet for longer than the look ahead: the push goes then.
    return 0;
}

/**
 * Checks the setting a person sends from the "Quiet hours" part: From and To
 * as HH:MM and the "Also quiet on the days I do not work" switch. Both times
 * empty, or equal, is no quiet range (the value null clears it). Pure.
 *
 * @param mixed $start
 * @param mixed $end
 * @param mixed $off_days
 * @return array ok, value (start, end, off_days, or null), error, field
 */
function pg_quiet_hours_input($start, $end, $off_days)
{
    $start = is_scalar($start) ? trim((string) $start) : '';
    $end = is_scalar($end) ? trim((string) $end) : '';

    if (($start === '') && ($end === '')) {
        return array('ok' => true, 'value' => null, 'error' => '', 'field' => '');
    }

    foreach (array('start' => $start, 'end' => $end) as $field => $time) {
        if (pg_quiet_hours_minutes($time) < 0) {
            return array('ok' => false, 'value' => null, 'error' => lang('Enter a time as hours and minutes, such as 22:00.'), 'field' => $field);
        }
    }

    // The rule pg_quiet_hours_decode() reads: a start equal to the end is off.
    $value = pg_quiet_hours_decode(array('start' => $start, 'end' => $end, 'off_days' => $off_days));

    return array('ok' => true, 'value' => $value, 'error' => '', 'field' => '');
}

/**
 * Checks a save of quiet hours before it is written: only a team member, and
 * only for their own profile (a request naming another person is refused,
 * never written for them). Pure.
 *
 * @param array $viewer  ws_viewer(): id, member
 * @param array $request start, end, off_days, and an optional user_id
 * @return array ok, user_id, value, error, field
 */
function pg_quiet_hours_save_check($viewer, $request)
{
    $user_id = (int) ($viewer['id'] ?? 0);

    if (empty($viewer['member']) || ($user_id <= 0)) {
        return array('ok' => false, 'user_id' => 0, 'value' => null, 'error' => lang('Only team members have quiet hours.'), 'field' => '');
    }

    if (isset($request['user_id']) && ((int) $request['user_id'] !== $user_id)) {
        return array('ok' => false, 'user_id' => 0, 'value' => null, 'error' => lang('You can only set your own quiet hours.'), 'field' => '');
    }

    $input = pg_quiet_hours_input($request['start'] ?? '', $request['end'] ?? '', $request['off_days'] ?? 0);

    return array('ok' => $input['ok'], 'user_id' => $input['ok'] ? $user_id : 0, 'value' => $input['value'], 'error' => $input['error'], 'field' => $input['field']);
}
