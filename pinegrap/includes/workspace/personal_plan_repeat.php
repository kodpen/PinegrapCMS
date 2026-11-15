<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - repeating personal plans: every day, week, month or year,
 * until a day or for a number of times. The rule sits on the plan's own row
 * (repeat_frequency, repeat_interval, repeat_until, repeat_count); each time
 * is worked out on every read from the plan's first time, moved by whole
 * calendar days so a time of day survives a clock change. A time deleted or
 * changed alone ("This one") leaves its day in ws_personal_plan_skips; a
 * changed one becomes a plan of its own naming its series (repeat_of).
 *
 * The times are read only from rows the plan's gate let through
 * (ws_personal_plan_can_see()): a time is the plan itself, on another day.
 * A roadmap does not repeat.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/** The most times a reader is given at once. */
const WS_PERSONAL_PLAN_TIMES_MAX = 1000;

/** The most times "for N times" may ask for. */
const WS_PERSONAL_PLAN_REPEAT_COUNT_MAX = 999;

/** The longest step between two times, in units. */
const WS_PERSONAL_PLAN_REPEAT_INTERVAL_MAX = 99;

/**
 * Whether the database has the repeat columns and the skipped days
 * (2026.11.0, 11.0.209).
 *
 * @return bool
 */
function ws_personal_plan_repeat_ready()
{
    static $ready = null;

    if ($ready === null) {
        $ready = function_exists('ws_personal_plans_ready') && ws_personal_plans_ready()
            && pg_schema_has('ws_personal_plans', 'repeat_frequency') && pg_schema_has('ws_personal_plan_skips');
    }

    return $ready;
}

/**
 * The frequencies a plan may repeat by.
 *
 * @return string[]
 */
function ws_personal_plan_repeat_frequencies()
{
    return array('daily', 'weekly', 'monthly', 'yearly');
}

/**
 * Whether a plan row repeats. Pure.
 *
 * @param array $plan
 * @return bool
 */
function ws_personal_plan_repeats($plan)
{
    return is_array($plan) && in_array((string) ($plan['repeat_frequency'] ?? ''), ws_personal_plan_repeat_frequencies(), true);
}

/**
 * The shortest and the longest step of a rule, in days. Pure.
 *
 * @param string $frequency
 * @param int    $interval
 * @return int[] array(shortest, longest)
 */
function ws_personal_plan_repeat_step_days($frequency, $interval)
{
    $interval = max(1, (int) $interval);
    $units = array('daily' => array(1, 1), 'weekly' => array(7, 7), 'monthly' => array(28, 31), 'yearly' => array(365, 366));
    $unit = $units[$frequency] ?? array(1, 1);

    return array($unit[0] * $interval, $unit[1] * $interval);
}

/**
 * The day of the k-th time (k = 0 is the first). A month or a year with no
 * such day takes its last day, as task series do. Pure.
 *
 * @param string $first     Y-m-d
 * @param string $frequency
 * @param int    $interval
 * @param int    $k
 * @return string Y-m-d
 */
function ws_personal_plan_repeat_day($first, $frequency, $interval, $k)
{
    list($y, $m, $d) = array_map('intval', explode('-', $first));
    $step = max(1, (int) $interval) * max(0, (int) $k);

    if (($frequency === 'daily') || ($frequency === 'weekly')) {
        return gmdate('Y-m-d', gmmktime(0, 0, 0, $m, $d, $y) + $step * (($frequency === 'weekly') ? 7 : 1) * 86400);
    }

    if ($frequency === 'monthly') {
        $months = ($y * 12 + ($m - 1)) + $step;
        $y = intdiv($months, 12);
        $m = ($months % 12) + 1;
    } else {
        $y += $step;
    }

    $last = (int) gmdate('t', gmmktime(0, 0, 0, $m, 1, $y));

    return sprintf('%04d-%02d-%02d', $y, $m, min($d, $last));
}

/**
 * The k-th time of a plan: its start and end moved by as many calendar days
 * as its day lies after the first. Pure.
 *
 * @param array $plan ws_personal_plans row with a rule
 * @param int   $k
 * @return array k, day (Y-m-d), starts_at, ends_at
 */
function ws_personal_plan_time($plan, $k)
{
    $first = date('Y-m-d', (int) $plan['starts_at']);
    $day = ws_personal_plan_repeat_day($first, (string) $plan['repeat_frequency'], (int) $plan['repeat_interval'], $k);
    $days = (int) round((gmmktime(0, 0, 0, (int) substr($day, 5, 2), (int) substr($day, 8, 2), (int) substr($day, 0, 4))
        - gmmktime(0, 0, 0, (int) substr($first, 5, 2), (int) substr($first, 8, 2), (int) substr($first, 0, 4))) / 86400);
    $move = function ($ts) use ($days) {
        return ($days === 0) ? (int) $ts : (int) strtotime(date('Y-m-d H:i:s', (int) $ts) . ' +' . $days . ' days');
    };

    return array('k' => (int) $k, 'day' => $day, 'starts_at' => $move($plan['starts_at']), 'ends_at' => $move($plan['ends_at']));
}

/**
 * The times of a plan touching a span (end exclusive), skipped days left
 * out. A plan that does not repeat has its one time. Pure.
 *
 * @param array    $plan
 * @param int      $from_ts
 * @param int      $to_ts
 * @param string[] $skips days (Y-m-d) taken out of the series
 * @param int      $limit
 * @return array[] ws_personal_plan_time() rows
 */
function ws_personal_plan_times($plan, $from_ts, $to_ts, $skips = array(), $limit = WS_PERSONAL_PLAN_TIMES_MAX)
{
    if (!ws_personal_plan_repeats($plan)) {
        return (((int) $plan['starts_at'] < $to_ts) && ((int) $plan['ends_at'] > $from_ts))
            ? array(array('k' => 0, 'day' => date('Y-m-d', (int) $plan['starts_at']), 'starts_at' => (int) $plan['starts_at'], 'ends_at' => (int) $plan['ends_at']))
            : array();
    }

    $until = (int) ($plan['repeat_until'] ?? 0);
    $length = max(0, (int) $plan['ends_at'] - (int) $plan['starts_at']);
    $longest = ws_personal_plan_repeat_step_days((string) $plan['repeat_frequency'], (int) $plan['repeat_interval'])[1];
    // The first time that may touch the span: never later than the real one.
    $k = max(0, (int) floor(($from_ts - $length - (int) $plan['starts_at']) / ($longest * 86400)) - 1);
    $skipped = array_flip($skips);
    $out = array();

    for ($guard = 0; $guard < 100000; $guard++, $k++) {
        $time = ws_personal_plan_time($plan, $k);

        if (($time['starts_at'] >= $to_ts) || (($until > 0) && ($time['starts_at'] >= $until))) {
            break;
        }

        if (($time['ends_at'] > $from_ts) && !isset($skipped[$time['day']])) {
            $out[] = $time;

            if (count($out) >= $limit) {
                break;
            }
        }
    }

    return $out;
}

/**
 * The time of a plan that starts on a day, or null. Pure.
 *
 * @param array    $plan
 * @param string   $day   Y-m-d
 * @param string[] $skips
 * @return array|null
 */
function ws_personal_plan_time_on($plan, $day, $skips = array())
{
    $ts = strtotime($day . ' 00:00:00');

    foreach (ws_personal_plan_times($plan, $ts, $ts + 86400 * 2, $skips, 5) as $time) {
        if ($time['day'] === $day) {
            return $time;
        }
    }

    return null;
}

/**
 * Where a series ends (repeat_until): the end of its last time, 0 for no
 * end. Worked out on save from a last day or a number of times, so readers
 * never count. Pure.
 *
 * @param array  $plan  starts_at, ends_at, repeat_frequency, repeat_interval
 * @param string $end   never, until or count
 * @param string $day   the last day a time may start on (until)
 * @param int    $count the number of times (count)
 * @return int|array the moment, or array('error' => text)
 */
function ws_personal_plan_repeat_until($plan, $end, $day, $count)
{
    if ($end === 'count') {
        return ws_personal_plan_time($plan, max(1, (int) $count) - 1)['ends_at'];
    }

    if ($end !== 'until') {
        return 0;
    }

    $first = date('Y-m-d', (int) $plan['starts_at']);

    if ($day < $first) {
        return array('error' => lang('The repeat cannot end before the plan starts.'));
    }

    $shortest = ws_personal_plan_repeat_step_days((string) $plan['repeat_frequency'], (int) $plan['repeat_interval'])[0];
    $span = (int) round((strtotime($day . ' 12:00:00') - strtotime($first . ' 12:00:00')) / 86400);
    $k = (int) floor($span / $shortest) + 1;

    // From the highest k that may still start on or before the day, back.
    for (; $k > 0; $k--) {
        $time = ws_personal_plan_time($plan, $k);

        if ($time['day'] <= $day) {
            return $time['ends_at'];
        }
    }

    return ws_personal_plan_time($plan, 0)['ends_at'];
}

/**
 * A plan's length on the wall clock, in seconds: the local date and time of
 * its end less those of its start, so an all-day plan on a day with a clock
 * change lasts 86400. Pure.
 *
 * @param array $plan starts_at, ends_at
 * @return int
 */
function ws_personal_plan_wall_length($plan)
{
    $wall = function ($ts) {
        return gmmktime((int) date('G', $ts), (int) date('i', $ts), (int) date('s', $ts), (int) date('n', $ts), (int) date('j', $ts), (int) date('Y', $ts));
    };

    return $wall((int) $plan['ends_at']) - $wall((int) $plan['starts_at']);
}

/**
 * Reads and checks the rule the drawer sends, for a plan already cleaned by
 * ws_personal_plan_clean(). Pure.
 *
 * @param array $clean the clean plan (starts_at, ends_at)
 * @param array $data  repeat ('' none, daily, weekly, monthly, yearly),
 *                     repeat_every, repeat_end (never, until, count),
 *                     repeat_until (Y-m-d), repeat_count
 * @return array repeat_frequency, repeat_interval, repeat_until,
 *               repeat_count; or error
 */
function ws_personal_plan_repeat_clean($clean, $data)
{
    $text = function ($value) {
        return is_scalar($value) ? trim((string) $value) : '';
    };
    $frequency = $text($data['repeat'] ?? '');
    $none = array('repeat_frequency' => '', 'repeat_interval' => 1, 'repeat_until' => 0, 'repeat_count' => 0);

    if ($frequency === '') {
        return $none;
    }

    if (!in_array($frequency, ws_personal_plan_repeat_frequencies(), true)) {
        return array('error' => lang('Choose how the plan repeats.'));
    }

    $every = $text($data['repeat_every'] ?? '1');
    $every = ($every === '') ? '1' : $every;

    if (!ctype_digit($every) || ((int) $every < 1) || ((int) $every > WS_PERSONAL_PLAN_REPEAT_INTERVAL_MAX)) {
        return array('error' => lang('Choose how often the plan repeats.'));
    }

    $rule = array('starts_at' => $clean['starts_at'], 'ends_at' => $clean['ends_at'], 'repeat_frequency' => $frequency, 'repeat_interval' => (int) $every);

    // Two times never overlap: the plan ends before its next time starts.
    // Times move by calendar days, so the length is read on the wall clock:
    // a day holding a clock change is still one day.
    if (ws_personal_plan_wall_length($clean) > ws_personal_plan_repeat_step_days($frequency, (int) $every)[0] * 86400) {
        return array('error' => lang('A repeating plan has to end before its next time starts.'));
    }

    $end = $text($data['repeat_end'] ?? 'never');
    $count = 0;
    $day = '';

    if ($end === 'count') {
        $count = $text($data['repeat_count'] ?? '');

        if (!ctype_digit($count) || ((int) $count < 1) || ((int) $count > WS_PERSONAL_PLAN_REPEAT_COUNT_MAX)) {
            return array('error' => lang('Choose how many times the plan repeats.'));
        }

        $count = (int) $count;
    } elseif ($end === 'until') {
        $day = $text($data['repeat_until'] ?? '');

        if (!preg_match('/^([0-9]{4})-([0-9]{2})-([0-9]{2})$/', $day, $m) || !checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            return array('error' => lang('Choose the last day of the repeat.'));
        }
    } else {
        $end = 'never';
    }

    $until = ws_personal_plan_repeat_until($rule, $end, $day, $count);

    if (is_array($until)) {
        return $until;
    }

    return array('repeat_frequency' => $frequency, 'repeat_interval' => (int) $every, 'repeat_until' => (int) $until, 'repeat_count' => $count);
}

/**
 * The rule as the drawer reads it. Pure.
 *
 * @param array $plan
 * @return array repeat, repeat_every, repeat_end, repeat_until (the day the
 *               last time starts on), repeat_count
 */
function ws_personal_plan_repeat_present($plan)
{
    if (!ws_personal_plan_repeats($plan)) {
        return array('repeat' => '', 'repeat_every' => 1, 'repeat_end' => 'never', 'repeat_until' => '', 'repeat_count' => '');
    }

    $until = (int) ($plan['repeat_until'] ?? 0);
    $count = (int) ($plan['repeat_count'] ?? 0);
    $last = '';

    if ($until > 0) {
        // The last time is the one ending at the series' end.
        $last = date('Y-m-d', $until - max(1, (int) $plan['ends_at'] - (int) $plan['starts_at']));
    }

    return array(
        'repeat'       => (string) $plan['repeat_frequency'],
        'repeat_every' => max(1, (int) $plan['repeat_interval']),
        'repeat_end'   => ($count > 0) ? 'count' : (($until > 0) ? 'until' : 'never'),
        'repeat_until' => $last,
        'repeat_count' => ($count > 0) ? (string) $count : '',
    );
}

/**
 * The moment a time reminds: its start, or 09:00 of its first day when it
 * lasts all day, less the lead. Pure.
 *
 * @param array $plan
 * @param array $time ws_personal_plan_time()
 * @return int
 */
function ws_personal_plan_time_remind_at($plan, $time)
{
    $at = ((int) $plan['all_day'] === 1) ? strtotime($time['day'] . ' 09:00:00') : (int) $time['starts_at'];

    return max(1, $at - (int) ($plan['remind_minutes'] ?? 0) * 60);
}

/**
 * The next reminder of a repeating plan after a moment: the first time not
 * skipped whose reminder comes later. 0 when the series has no time left.
 * Pure.
 *
 * @param array    $plan  with remind_minutes
 * @param int      $after
 * @param string[] $skips
 * @return int
 */
function ws_personal_plan_next_remind($plan, $after, $skips = array())
{
    $lead = (int) ($plan['remind_minutes'] ?? 0) * 60;

    foreach (ws_personal_plan_times($plan, $after + $lead - 86400, PHP_INT_MAX, $skips, 50) as $time) {
        $at = ws_personal_plan_time_remind_at($plan, $time);

        if ($at > $after) {
            return $at;
        }
    }

    return 0;
}

/**
 * The time a reminder moment belongs to, its day: the lead added back. Pure.
 *
 * @param array $plan
 * @param int   $remind_at
 * @return string Y-m-d
 */
function ws_personal_plan_remind_day($plan, $remind_at)
{
    return date('Y-m-d', (int) $remind_at + (int) ($plan['remind_minutes'] ?? 0) * 60);
}

/**
 * The skipped days of plans, plan id => days.
 *
 * @param int[] $plan_ids
 * @return array
 */
function ws_personal_plan_skips($plan_ids)
{
    $plan_ids = array_values(array_filter(array_map('intval', (array) $plan_ids)));
    $out = array();

    if (empty($plan_ids) || !ws_personal_plan_repeat_ready()) {
        return $out;
    }

    foreach ((array) db_items("SELECT plan_id, day FROM ws_personal_plan_skips WHERE plan_id IN (" . implode(',', $plan_ids) . ")") as $row) {
        $out[(int) $row['plan_id']][] = (string) $row['day'];
    }

    return $out;
}

/**
 * Takes a day out of a series.
 *
 * @param int    $plan_id
 * @param string $day
 */
function ws_personal_plan_skip($plan_id, $day)
{
    db("INSERT IGNORE INTO ws_personal_plan_skips (plan_id, day) VALUES ('" . (int) $plan_id . "', '" . e($day) . "')");
}

/**
 * Plan rows laid out as their times in a span: each time is a copy of its
 * row with the time's start and end and its day (time_day). Rows already
 * passed the gate. Pure but for the skipped days it reads.
 *
 * @param array[] $plans
 * @param int     $from_ts
 * @param int     $to_ts
 * @return array[]
 */
function ws_personal_plans_as_times($plans, $from_ts, $to_ts)
{
    $repeating = array();

    foreach ($plans as $plan) {
        if (ws_personal_plan_repeats($plan)) {
            $repeating[] = (int) $plan['id'];
        }
    }

    $skips = ws_personal_plan_skips($repeating);
    $out = array();

    foreach ($plans as $plan) {
        foreach (ws_personal_plan_times($plan, $from_ts, $to_ts, $skips[(int) $plan['id']] ?? array()) as $time) {
            $out[] = array_merge($plan, array(
                'starts_at' => $time['starts_at'],
                'ends_at'   => $time['ends_at'],
                'time_day'  => ws_personal_plan_repeats($plan) ? $time['day'] : '',
            ));
        }
    }

    usort($out, function ($a, $b) {
        return ((int) $a['starts_at'] <=> (int) $b['starts_at']) ?: ((int) $a['id'] <=> (int) $b['id']);
    });

    return array_slice($out, 0, WS_PERSONAL_PLAN_TIMES_MAX);
}

/**
 * A plan as it stands on one of its times: the row with that time's start
 * and end, its day (time_day), and the times left counted from it. Null
 * when no time starts on the day. Pure but for the skipped days it reads.
 *
 * @param array  $plan a row the gate let through
 * @param string $day  Y-m-d
 * @return array|null
 */
function ws_personal_plan_at_time($plan, $day)
{
    if (!ws_personal_plan_repeats($plan) || !preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/', (string) $day)) {
        return null;
    }

    $time = ws_personal_plan_time_on($plan, $day, ws_personal_plan_skips(array((int) $plan['id']))[(int) $plan['id']] ?? array());

    if (!$time) {
        return null;
    }

    $count = (int) ($plan['repeat_count'] ?? 0);

    return array_merge($plan, array(
        'starts_at'    => $time['starts_at'],
        'ends_at'      => $time['ends_at'],
        'time_day'     => $time['day'],
        'time_k'       => $time['k'],
        'repeat_count' => ($count > 0) ? max(1, $count - $time['k']) : 0,
    ));
}

/**
 * Where a series cut before one of its times ends (repeat_until): the end
 * of the time before it, as ws_personal_plan_repeat_until() writes it, so
 * the drawer reads its last day back and a save keeps the cut. Pure.
 *
 * @param array $plan
 * @param array $time ws_personal_plan_time(), never the first (k >= 1)
 * @return int
 */
function ws_personal_plan_cut_until($plan, $time)
{
    return (int) ws_personal_plan_time($plan, max(1, (int) $time['k']) - 1)['ends_at'];
}

/**
 * Ends a series before one of its times: the times changed out of it from
 * then on and the days skipped from then on are handed to $to (0 deletes
 * them).
 *
 * @param array $plan
 * @param array $time ws_personal_plan_time()
 * @param int   $to   the series that goes on from the time, or 0
 */
function ws_personal_plan_series_cut($plan, $time, $to)
{
    $plan_id = (int) $plan['id'];
    $owner = (int) $plan['user_id'];

    db("UPDATE ws_personal_plans SET repeat_until = '" . ws_personal_plan_cut_until($plan, $time) . "', repeat_count = 0, updated_at = '" . time() . "'
        WHERE id = '" . $plan_id . "' AND user_id = '" . $owner . "'");

    $later = array_map('intval', (array) db_values("SELECT id FROM ws_personal_plans
        WHERE repeat_of = '" . $plan_id . "' AND user_id = '" . $owner . "' AND starts_at >= '" . (int) $time['starts_at'] . "'"));

    if ($to > 0) {
        if (!empty($later)) {
            db("UPDATE ws_personal_plans SET repeat_of = '" . (int) $to . "' WHERE id IN (" . implode(',', $later) . ")");
        }

        db("UPDATE IGNORE ws_personal_plan_skips SET plan_id = '" . (int) $to . "' WHERE plan_id = '" . $plan_id . "' AND day >= '" . e($time['day']) . "'");
    } else {
        ws_personal_plans_remove($later);
    }

    db("DELETE FROM ws_personal_plan_skips WHERE plan_id = '" . $plan_id . "' AND day >= '" . e($time['day']) . "'");
    ws_personal_plan_rearm($plan_id);
}

/**
 * Sets a series' reminder on its next time again after its times changed.
 *
 * @param int $plan_id
 */
function ws_personal_plan_rearm($plan_id)
{
    $plan = db_item("SELECT * FROM ws_personal_plans WHERE id = '" . (int) $plan_id . "'");

    if (!ws_personal_plan_repeats($plan) || ((int) $plan['remind_at'] <= 0)) {
        return;
    }

    $next = ws_personal_plan_next_remind($plan, time(), ws_personal_plan_skips(array((int) $plan_id))[(int) $plan_id] ?? array());

    if ($next > 0) {
        db("UPDATE ws_personal_plans SET remind_at = '" . $next . "', reminded_at = 0 WHERE id = '" . (int) $plan_id . "'");
    } else {
        db("UPDATE ws_personal_plans SET reminded_at = '" . time() . "' WHERE id = '" . (int) $plan_id . "' AND reminded_at = 0");
    }
}

/**
 * Saves a change of one time of a series ("This one"), or of it and the ones
 * after it ("This and the following"). Null when the change is the whole
 * series' (the first time and the following), for the plain save.
 *
 * @param array $viewer
 * @param array $plan   the series, its owner's
 * @param array $data   the drawer's plan, mode and time_day
 * @return array|null ok + plan, or error
 */
function ws_personal_plan_save_time($viewer, $plan, $data)
{
    $mode = (string) $data['mode'];
    $time = ws_personal_plan_time_on($plan, is_scalar($data['time_day'] ?? '') ? (string) ($data['time_day'] ?? '') : '',
        ws_personal_plan_skips(array((int) $plan['id']))[(int) $plan['id']] ?? array());

    if (!$time) {
        return array('error' => lang('This time of the plan cannot be found.'));
    }

    if (($mode === 'following') && ($time['k'] === 0)) {
        return null;
    }

    $new = $data;
    unset($new['mode'], $new['time_day'], $new['mail_item']);

    if ($mode === 'one') {
        // A time of its own: no rule, naming its series.
        $new['repeat'] = '';
        $new['repeat_of'] = (int) $plan['id'];
    }

    $saved = ws_personal_plan_save($viewer, $new, 0);

    if (isset($saved['error'])) {
        return $saved;
    }

    $new_id = (int) $saved['plan']['id'];
    // The customer goes with it; the drawer sends a changed one after.
    db("UPDATE ws_personal_plans SET contact_id = '" . (int) ($plan['contact_id'] ?? 0) . "' WHERE id = '" . $new_id . "'");

    if ($mode === 'one') {
        ws_personal_plan_skip((int) $plan['id'], $time['day']);
        ws_personal_plan_rearm((int) $plan['id']);
    } else {
        ws_personal_plan_series_cut($plan, $time, $new_id);
    }

    return array('ok' => true, 'plan' => ws_personal_plan_for_reader((int) $viewer['id'], ws_personal_plan((int) $viewer['id'], $new_id), $viewer));
}

/**
 * Deletes one time of a series ("This one"), or it and the ones after it
 * ("This and the following"). Null when that is the whole series.
 *
 * @param array  $plan the series, its owner's
 * @param string $mode one or following
 * @param string $day  the time's day
 * @return array|null ok or error
 */
function ws_personal_plan_delete_time($plan, $mode, $day)
{
    $time = ws_personal_plan_time_on($plan, $day, ws_personal_plan_skips(array((int) $plan['id']))[(int) $plan['id']] ?? array());

    if (!$time) {
        return array('error' => lang('This time of the plan cannot be found.'));
    }

    if (($mode === 'following') && ($time['k'] === 0)) {
        return null;
    }

    if ($mode === 'one') {
        ws_personal_plan_skip((int) $plan['id'], $time['day']);
        ws_personal_plan_rearm((int) $plan['id']);
    } else {
        ws_personal_plan_series_cut($plan, $time, 0);
    }

    return array('ok' => true);
}
