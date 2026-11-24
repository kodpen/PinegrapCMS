<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The walk-in queue's report (a place's Report tab, queue_report.php): wait
 * and handling time (the median and the slowest tenth), people who left and
 * who were not here, per desk, staff member and service, arrivals by
 * weekday and hour, and the staffing line: the desks that would have kept
 * the mean wait under a target in each hour, by the Erlang C formula over
 * the period's arrivals and the median handling time. The numbers are read
 * one row at a time and folded into an accumulator, so a busy place's three
 * months stay small in memory. Excel through pg_xlsx_write_book().
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/** The periods the report offers, in days ending today. */
const PG_WAITING_REPORT_PERIODS = array(7, 30, 90);

/** The period shown first. */
const PG_WAITING_REPORT_DAYS = 30;

/** The wait target of the staffing line, in minutes, and its bounds. */
const PG_WAITING_REPORT_TARGET = 10;
const PG_WAITING_REPORT_TARGET_MAX = 120;

/** The most desks the staffing line asks for in one hour. */
const PG_WAITING_REPORT_DESKS_MAX = 99;

/**
 * The period asked for, one of PG_WAITING_REPORT_PERIODS. Pure.
 *
 * @param mixed $value
 * @return int days
 */
function pg_waiting_report_period($value)
{
    $days = is_numeric($value) ? (int) $value : 0;

    return in_array($days, PG_WAITING_REPORT_PERIODS, true) ? $days : PG_WAITING_REPORT_DAYS;
}

/**
 * The wait target asked for, in minutes, 1 to 120. Pure.
 *
 * @param mixed $value
 * @return int
 */
function pg_waiting_report_target($value)
{
    $minutes = is_numeric($value) ? (int) $value : 0;

    return ($minutes >= 1) ? min(PG_WAITING_REPORT_TARGET_MAX, $minutes) : PG_WAITING_REPORT_TARGET;
}

/**
 * The days of a period ending on $now's day, in the site's zone: the first
 * and last as YYYYMMDD (the numbers' day column) and how many times each
 * weekday (ISO, 1 Monday) falls in it. Pure but for the zone.
 *
 * @param int $days
 * @param int $now
 * @return array from, to, weekdays (1-7 => count)
 */
function pg_waiting_report_range($days, $now)
{
    $days = max(1, (int) $days);
    $weekdays = array_fill(1, 7, 0);
    $last = (new DateTimeImmutable('@' . (int) $now))->setTimezone(new DateTimeZone(date_default_timezone_get()))->setTime(12, 0);
    $first = $last->modify('-' . ($days - 1) . ' days');

    for ($day = $first, $i = 0; $i < $days; $i++, $day = $day->modify('+1 day')) {
        $weekdays[(int) $day->format('N')]++;
    }

    return array('from' => (int) $first->format('Ymd'), 'to' => (int) $last->format('Ymd'), 'weekdays' => $weekdays);
}

/**
 * An empty accumulator: the counts, the waits and handling times, the same
 * per desk, staff member and service, arrivals per weekday and hour, and
 * which desks called in each weekday and hour. Pure.
 *
 * @return array
 */
function pg_waiting_report_new()
{
    return array(
        'total'   => pg_waiting_report_group_new(),
        'desk'    => array(),
        'staff'   => array(),
        'service' => array(),
        'arrive'  => array(),
        'worked'  => array(),
    );
}

/**
 * One line of the report: the numbers taken, served, left and not here, and
 * the waits and handling times in seconds. Pure.
 *
 * @return array
 */
function pg_waiting_report_group_new()
{
    return array('taken' => 0, 'served' => 0, 'left' => 0, 'no_show' => 0, 'wait' => array(), 'handle' => array());
}

/**
 * When a number began to wait: its arrival, or its booked time for a
 * booking that came early or within the grace window (the order rule,
 * without a move back, which the wait includes). Pure.
 *
 * @param array $ticket joined_at, booking_at
 * @param int   $grace_seconds
 * @return int
 */
function pg_waiting_report_wait_start($ticket, $grace_seconds)
{
    $ticket['moved_back'] = 0;

    return pg_waiting_order_time($ticket, $grace_seconds);
}

/**
 * A number folded into the accumulator. Its hour and weekday are read in
 * the site's zone. Pure but for the zone.
 *
 * @param array $acc
 * @param array $ticket service, state, booking_at, desk_id, staff_user_id, joined_at, called_at, done_at, day
 * @param int   $grace_seconds
 * @return void
 */
function pg_waiting_report_add(array &$acc, $ticket, $grace_seconds)
{
    $state = (string) $ticket['state'];
    $joined = (int) $ticket['joined_at'];
    $called = (int) $ticket['called_at'];
    $done = (int) $ticket['done_at'];
    $desk = (int) $ticket['desk_id'];
    $staff = (int) $ticket['staff_user_id'];
    $keys = array('total' => 0, 'service' => (string) $ticket['service']);

    if ($desk > 0) {
        $keys['desk'] = $desk;
    }

    if ($staff > 0) {
        $keys['staff'] = $staff;
    }

    $wait = null;
    $handle = null;

    if (($called > 0) && in_array($state, array('called', 'served', 'no_show'), true)) {
        $wait = max(0, $called - pg_waiting_report_wait_start($ticket, $grace_seconds));
    }

    if (($state === 'served') && ($done >= $called) && ($called > 0)) {
        $handle = $done - $called;
    }

    foreach ($keys as $kind => $key) {
        if ($kind === 'total') {
            $line = &$acc['total'];
        } else {
            if (!isset($acc[$kind][$key])) {
                $acc[$kind][$key] = pg_waiting_report_group_new();
            }

            $line = &$acc[$kind][$key];
        }

        $line['taken']++;

        if (isset($line[$state])) {
            $line[$state]++;
        }

        if ($wait !== null) {
            $line['wait'][] = $wait;
        }

        if ($handle !== null) {
            $line['handle'][] = $handle;
        }

        unset($line);
    }

    if ($joined > 0) {
        $weekday = (int) date('N', $joined);
        $hour = (int) date('G', $joined);
        $acc['arrive'][$weekday][$hour] = ($acc['arrive'][$weekday][$hour] ?? 0) + 1;
    }

    if (($called > 0) && ($desk > 0)) {
        $acc['worked'][(int) date('N', $called)][(int) date('G', $called)][(int) $ticket['day'] . ':' . $desk] = true;
    }
}

/**
 * The slowest tenth's threshold: the 90th percentile by nearest rank, the
 * value a tenth of the numbers took longer than. Pure.
 *
 * @param int[] $values
 * @return int|null
 */
function pg_waiting_report_slowest_tenth($values)
{
    $values = array_map('intval', array_values((array) $values));
    $count = count($values);

    if ($count === 0) {
        return null;
    }

    sort($values);

    return $values[max(0, (int) ceil(0.9 * $count) - 1)];
}

/**
 * A line's figures: the counts, and the median and the slowest tenth of its
 * waits and handling times in seconds (null with none). Pure.
 *
 * @param array $line pg_waiting_report_group_new()
 * @return array
 */
function pg_waiting_report_figures($line)
{
    return array(
        'taken'       => (int) $line['taken'],
        'served'      => (int) $line['served'],
        'left'        => (int) $line['left'],
        'no_show'     => (int) $line['no_show'],
        'wait'        => pg_waiting_median($line['wait']),
        'wait_slow'   => pg_waiting_report_slowest_tenth($line['wait']),
        'handle'      => pg_waiting_median($line['handle']),
        'handle_slow' => pg_waiting_report_slowest_tenth($line['handle']),
    );
}

/**
 * The mean wait in seconds with $desks desks, $traffic the work arriving
 * in Erlangs (arrivals an hour times handling hours) and $handling the
 * mean handling time: the Erlang C queue. Null when the desks cannot keep
 * up (as many desks as the traffic or fewer). Pure.
 *
 * @param int   $desks
 * @param float $traffic
 * @param int   $handling seconds
 * @return float|null
 */
function pg_waiting_report_erlang_wait($desks, $traffic, $handling)
{
    $desks = (int) $desks;
    $traffic = (float) $traffic;

    if ($traffic <= 0) {
        return 0.0;
    }

    if ($desks <= $traffic) {
        return null;
    }

    // Erlang B by its recursion, then C from it.
    $blocked = 1.0;

    for ($k = 1; $k <= $desks; $k++) {
        $blocked = ($traffic * $blocked) / ($k + $traffic * $blocked);
    }

    $queued = ($desks * $blocked) / ($desks - $traffic * (1 - $blocked));

    return $queued * (int) $handling / ($desks - $traffic);
}

/**
 * The fewest desks that keep the mean wait at or under the target, for
 * $arrivals numbers an hour each taking $handling seconds; 0 with no
 * arrival, PG_WAITING_REPORT_DESKS_MAX at most. Pure.
 *
 * @param float $arrivals an hour
 * @param int   $handling seconds
 * @param int   $target_seconds
 * @return int
 */
function pg_waiting_report_desks_needed($arrivals, $handling, $target_seconds)
{
    $traffic = (float) $arrivals * max(0, (int) $handling) / 3600;

    if ($traffic <= 0) {
        return 0;
    }

    for ($desks = max(1, (int) floor($traffic) + 1); $desks < PG_WAITING_REPORT_DESKS_MAX; $desks++) {
        $wait = pg_waiting_report_erlang_wait($desks, $traffic, $handling);

        if (($wait !== null) && ($wait <= (int) $target_seconds)) {
            return $desks;
        }
    }

    return PG_WAITING_REPORT_DESKS_MAX;
}

/**
 * The hours the grids show: from the first to the last hour anything
 * arrived or was called in, an empty list with nothing. Pure.
 *
 * @param array $acc
 * @return int[]
 */
function pg_waiting_report_hours($acc)
{
    $hours = array();

    foreach (array('arrive', 'worked') as $part) {
        foreach ($acc[$part] as $by_hour) {
            $hours = array_merge($hours, array_keys($by_hour));
        }
    }

    return $hours ? range(min($hours), max($hours)) : array();
}

/**
 * The staffing line: for each weekday and hour, the mean arrivals on such a
 * day, the desks needed to keep the wait under the target and the desks
 * that called on such a day on average. Pure.
 *
 * @param array    $acc
 * @param int[]    $weekdays pg_waiting_report_range()['weekdays']
 * @param int|null $handling seconds; null gives no line
 * @param int      $target_minutes
 * @return array weekday => hour => array(arrivals, needed, worked)
 */
function pg_waiting_report_staffing($acc, $weekdays, $handling, $target_minutes)
{
    $line = array();

    if ($handling === null) {
        return $line;
    }

    $hours = pg_waiting_report_hours($acc);

    for ($weekday = 1; $weekday <= 7; $weekday++) {
        $days = max(1, (int) ($weekdays[$weekday] ?? 0));

        foreach ($hours as $hour) {
            $arrivals = ($acc['arrive'][$weekday][$hour] ?? 0) / $days;
            $worked = count($acc['worked'][$weekday][$hour] ?? array()) / $days;
            $line[$weekday][$hour] = array(
                'arrivals' => round($arrivals, 1),
                'needed'   => pg_waiting_report_desks_needed($arrivals, $handling, (int) $target_minutes * 60),
                'worked'   => round($worked, 1),
            );
        }
    }

    return $line;
}

/**
 * The handling time the staffing line uses: the median of the period's
 * handled numbers from 20, else the mean of the services' starting minutes
 * (the estimate's rule). Pure.
 *
 * @param array $acc
 * @param array $services the place's services
 * @return int|null seconds
 */
function pg_waiting_report_handling($acc, $services)
{
    $minutes = array();

    foreach ((array) $services as $service) {
        if (!empty($service['minutes'])) {
            $minutes[] = (int) $service['minutes'];
        }
    }

    return pg_waiting_handling_seconds($acc['total']['handle'], $minutes ? (int) round(array_sum($minutes) / count($minutes)) : 0);
}

/**
 * Seconds as minutes with one decimal, '' for none. Pure.
 *
 * @param int|null $seconds
 * @return float|string
 */
function pg_waiting_report_minutes($seconds)
{
    return ($seconds === null) ? '' : round($seconds / 60, 1);
}

/**
 * A place's numbers of a period folded, read one row at a time.
 *
 * @param int $place_id
 * @param int $from YYYYMMDD
 * @param int $to   YYYYMMDD
 * @param int $grace_seconds
 * @return array pg_waiting_report_new()
 */
function pg_waiting_report_read($place_id, $from, $to, $grace_seconds)
{
    $acc = pg_waiting_report_new();
    $result = db_q("SELECT service, state, booking_at, desk_id, staff_user_id, joined_at, called_at, done_at, day FROM waiting_tickets WHERE place_id = ? AND day BETWEEN ? AND ?", array((int) $place_id, (int) $from, (int) $to));

    if (!($result instanceof mysqli_result)) {
        return $acc;
    }

    while ($row = mysqli_fetch_assoc($result)) {
        pg_waiting_report_add($acc, $row, $grace_seconds);
    }

    mysqli_free_result($result);

    return $acc;
}

/**
 * The names of the staff members who called, by user id.
 *
 * @param int[] $ids
 * @return array id => name
 */
function pg_waiting_report_staff_names($ids)
{
    $ids = array_values(array_filter(array_map('intval', (array) $ids)));
    $names = array();

    if (!$ids) {
        return $names;
    }

    foreach (db_items("SELECT user.user_id, user.user_username, contacts.first_name, contacts.last_name FROM user LEFT JOIN contacts ON contacts.id = user.user_contact WHERE user.user_id IN (" . implode(', ', $ids) . ")") as $row) {
        $names[(int) $row['user_id']] = pg_user_display_name($row['first_name'], $row['last_name'], $row['user_username']);
    }

    return $names;
}

/**
 * The lines of one grouping, named and sorted by numbers taken, as the
 * screen and Excel show them. Pure.
 *
 * @param array $groups key => pg_waiting_report_group_new()
 * @param array $names  key => name
 * @return array list of array(name, figures)
 */
function pg_waiting_report_lines($groups, $names)
{
    $lines = array();

    foreach ($groups as $key => $line) {
        $name = isset($names[$key]) ? (string) $names[$key] : '';
        $lines[] = array('name' => ($name !== '') ? $name : '#' . $key, 'figures' => pg_waiting_report_figures($line));
    }

    usort($lines, function ($a, $b) {
        return ($b['figures']['taken'] - $a['figures']['taken']) ?: strcmp($a['name'], $b['name']);
    });

    return $lines;
}

/**
 * The cells of a grouping's table: name, taken, served, left, not here and
 * the four times in minutes. Pure.
 *
 * @param array $line pg_waiting_report_lines() line
 * @return array
 */
function pg_waiting_report_line_cells($line)
{
    $f = $line['figures'];

    return array($line['name'], $f['taken'], $f['served'], $f['left'], $f['no_show'], pg_waiting_report_minutes($f['wait']), pg_waiting_report_minutes($f['wait_slow']), pg_waiting_report_minutes($f['handle']), pg_waiting_report_minutes($f['handle_slow']));
}

/**
 * A head and rows as the typed rows of pg_xlsx_write_book(): a number a
 * numeric cell, '' or null a gap, anything else text (never a formula).
 * Pure.
 *
 * @param array $head
 * @param array $rows
 * @return array row number => cells
 */
function pg_waiting_report_sheet($head, $rows)
{
    $sheet = array();

    foreach (array_merge(array($head), array_values($rows)) as $index => $row) {
        $cells = array();

        foreach (array_values($row) as $value) {
            if (($value === null) || ($value === '')) {
                $cells[] = null;
            } elseif (is_int($value) || is_float($value)) {
                $cells[] = array('t' => 'n', 'v' => (string) $value);
            } else {
                $cells[] = array('t' => 's', 'v' => (string) $value);
            }
        }

        $sheet[$index + 1] = $cells;
    }

    return $sheet;
}

/**
 * A weekday × hour grid as rows: the head with the hours, a weekday a row,
 * a cell from $cell(weekday, hour). Pure.
 *
 * @param int[]    $hours
 * @param string[] $weekday_names 1-7 => name
 * @param callable $cell
 * @return array array(head, rows)
 */
function pg_waiting_report_grid($hours, $weekday_names, $cell)
{
    $head = array('');

    foreach ($hours as $hour) {
        $head[] = sprintf('%02d', $hour);
    }

    $rows = array();

    for ($weekday = 1; $weekday <= 7; $weekday++) {
        $row = array($weekday_names[$weekday]);

        foreach ($hours as $hour) {
            $row[] = $cell($weekday, $hour);
        }

        $rows[] = $row;
    }

    return array($head, $rows);
}
