<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - repeating tasks: a task handed out again on a schedule, to the
 * same people, with the same department, channel, estimate and records.
 *
 * A series (ws_task_recurrences) holds the rule and the date the next copy is
 * due; every copy is an ordinary task carrying the series id. The copies are
 * made on the calendar, one at a time. A copy is not handed out within the
 * days of the one before (its start to its due date): a task that runs longer
 * than the step between copies passes over the dates that fall inside it,
 * even when it was finished early. Nor while the one before is still open:
 * it stays open, overdue, and the next copy comes on the first date after it
 * is done. Copies never pile up on anybody. The scheduled job
 * (workspace_recurring_job.php) makes them.
 *
 * A series ends three ways: somebody completes the task for good (the copy in
 * hand is marked done and no more are made), the end date passes, or the
 * repeat is switched off. An ended series keeps its row, so every copy can
 * still say where it came from.
 *
 * A copy's date is its due date. The first copy's date is the anchor the
 * rule counts from: monthly and yearly steps are counted from it rather than
 * from the copy before, so a series that starts on the 31st comes back to the
 * 31st after a short month instead of drifting to the 28th for good.
 *
 * A copy opens on its start day: a task that starts on Monday and is due on
 * Friday comes back every Monday, due that Friday (lead_days is the gap). A
 * task with only a due date opens on its due date.
 *
 * The rule is read off the due date: "every month" is every month on the day
 * the task is due, "every week" on its weekday. Moving the newest copy's due
 * date in its drawer moves the series with it. Dragging the newest copy on
 * the board asks which: that copy only, or the copies after it as well
 * (ws_recurrence_moved()). An earlier copy only ever moves itself.
 *
 * A copy whose day falls on a weekend or a holiday is due on the next working
 * day instead, and a start that falls on one moves the same way
 * (includes/workspace/workdays.php, the company's calendar and the task's
 * department's). The series keeps counting from its own days: a monthly task
 * on the 16th whose 16th is a Saturday is due on the Monday that month and on
 * the 16th the next. The day a copy stands for is kept on it
 * (ws_tasks.recurrence_date), so opening a moved copy still shows the rule.
 *
 * The copies not handed out yet are shown ahead of time on the planning board
 * and the work calendar (ws_recurrence_projections()), marked as coming, under
 * the same rule: each copy counted as done on its due date, a date that falls
 * within the days of the one before is not shown. While the newest copy is
 * overdue they are still shown, marked as waiting for it: each comes only if
 * that copy is done before its start (ws_recurrence_late()).
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
 * The most copies one run of the job makes, so a large backlog is worked off
 * over several runs rather than in one long request.
 */
define('WS_RECURRENCE_BATCH', 200);

/**
 * Whether the upgrade that holds the series has run.
 *
 * @return bool
 */
function ws_recurrence_ready()
{
    static $ready = null;

    if ($ready === null) {
        $ready = function_exists('waf_table_has_column')
            && waf_table_has_column('ws_task_recurrences', 'next_date')
            && waf_table_has_column('ws_tasks', 'recurrence_id');
    }

    return $ready;
}

/**
 * Whether the series know their lead time (the start-to-due gap); without
 * it a copy opens on its due date.
 *
 * @return bool
 */
function ws_recurrence_has_lead()
{
    static $ready = null;

    if ($ready === null) {
        $ready = ws_recurrence_ready() && waf_table_has_column('ws_task_recurrences', 'lead_days');
    }

    return $ready;
}

/**
 * The days between a task's start and its due date, 0 without a start.
 *
 * @param array $task
 * @return int
 */
function ws_recurrence_lead($task)
{
    $start = (string) ($task['start_date'] ?? '');
    $due = (string) ($task['due_date'] ?? '');

    if (($start === '') || ($start === '0000-00-00') || ($due === '') || ($due === '0000-00-00')) {
        return 0;
    }

    return max(0, min(3650, (int) round((strtotime($due . ' 12:00:00') - strtotime($start . ' 12:00:00')) / 86400)));
}

/**
 * The day the copy due on a date opens.
 *
 * @param string $date Y-m-d
 * @param int    $lead
 * @return string
 */
function ws_recurrence_opens($date, $lead)
{
    return ($lead > 0) ? date('Y-m-d', strtotime($date . ' 12:00:00 -' . (int) $lead . ' days')) : $date;
}

/**
 * A copy's days for the date it stands for: its due date and its start,
 * each moved off a weekend or a holiday to the next working day of the
 * task's department (the start never after the due date).
 *
 * @param string $date          Y-m-d, the day of the rule
 * @param int    $lead
 * @param int    $department_id
 * @return array due, start, date (the day of the rule), moved (bool)
 */
function ws_recurrence_copy_dates($date, $lead, $department_id)
{
    $start = ws_recurrence_opens($date, $lead);
    $due = $date;

    if (function_exists('ws_workdays_ready') && ws_workdays_ready()) {
        $due = ws_next_workday($date, $department_id);
        $start = min(ws_next_workday($start, $department_id), $due);
    }

    return array('due' => $due, 'start' => $start, 'date' => $date, 'moved' => ($due !== $date));
}

/**
 * The day a task stands for in its series: the day of the rule it was made
 * for, which is its due date unless it was moved off a day off.
 *
 * @param array $task
 * @return string Y-m-d
 */
function ws_recurrence_base_date($task)
{
    $date = (string) ($task['recurrence_date'] ?? '');

    return (($date !== '') && ($date !== '0000-00-00')) ? $date : ws_task_occurrence_date($task);
}

/**
 * Whether a copy was moved off a day off and still sits where it was moved.
 *
 * @param array $task
 * @return bool
 */
function ws_recurrence_is_moved($task)
{
    $date = (string) ($task['recurrence_date'] ?? '');

    return ($date !== '') && ($date !== '0000-00-00') && ($date !== ws_task_occurrence_date($task));
}

/**
 * @return array frequency => label
 */
function ws_recurrence_frequencies()
{
    return array(
        'daily'   => lang('Daily'),
        'weekly'  => lang('Weekly'),
        'monthly' => lang('Monthly'),
        'yearly'  => lang('Yearly'),
    );
}

/**
 * @return array ISO day => full weekday name
 */
function ws_recurrence_weekday_names()
{
    return array(
        1 => lang('Monday'),
        2 => lang('Tuesday'),
        3 => lang('Wednesday'),
        4 => lang('Thursday'),
        5 => lang('Friday'),
        6 => lang('Saturday'),
        7 => lang('Sunday'),
    );
}

/**
 * @return array month number => name
 */
function ws_recurrence_month_names()
{
    return array(
        1 => lang('January'), 2 => lang('February'), 3 => lang('March'), 4 => lang('April'),
        5 => lang('May'), 6 => lang('June'), 7 => lang('July'), 8 => lang('August'),
        9 => lang('September'), 10 => lang('October'), 11 => lang('November'), 12 => lang('December'),
    );
}

/**
 * One series.
 *
 * @param int $series_id
 * @return array|null
 */
function ws_recurrence($series_id)
{
    if (!ws_recurrence_ready() || ((int) $series_id <= 0)) {
        return null;
    }

    $row = db_item("SELECT * FROM ws_task_recurrences WHERE id = '" . (int) $series_id . "'");

    return is_array($row) ? $row : null;
}

/**
 * The date a task stands for in its series: its due date, else its start
 * date, else the day it was made.
 *
 * @param array $task
 * @return string Y-m-d
 */
function ws_task_occurrence_date($task)
{
    foreach (array('due_date', 'start_date') as $field) {
        $date = (string) ($task[$field] ?? '');

        if (($date !== '') && ($date !== '0000-00-00')) {
            return $date;
        }
    }

    return date('Y-m-d', ((int) ($task['created_at'] ?? 0) > 0) ? (int) $task['created_at'] : time());
}

/**
 * Weekdays as a bit mask: Monday is bit 0, Sunday bit 6 (ISO day 1..7).
 *
 * @param int[] $days
 * @return int
 */
function ws_recurrence_days_mask($days)
{
    $mask = 0;

    foreach ((array) $days as $day) {
        $day = (int) $day;

        if (($day >= 1) && ($day <= 7)) {
            $mask |= (1 << ($day - 1));
        }
    }

    return $mask;
}

/**
 * @param int $mask
 * @return int[] ISO days, Monday first
 */
function ws_recurrence_mask_days($mask)
{
    $days = array();

    for ($day = 1; $day <= 7; $day++) {
        if (((int) $mask) & (1 << ($day - 1))) {
            $days[] = $day;
        }
    }

    return $days;
}

/**
 * The k-th date of a rule counted from its anchor (k = 0 is the anchor), for
 * the rules that step by a whole unit. A month or a year that has no such day
 * takes its last day.
 *
 * @param array $rule frequency, interval_count, anchor_date
 * @param int   $k
 * @return string Y-m-d
 */
function ws_recurrence_nth($rule, $k)
{
    $anchor = (string) $rule['anchor_date'];
    $step = max(1, (int) $rule['interval_count']) * max(0, (int) $k);
    list($year, $month, $day) = array_map('intval', explode('-', $anchor));

    switch ((string) $rule['frequency']) {
        case 'daily':
            return date('Y-m-d', strtotime($anchor . ' 12:00:00 +' . $step . ' days'));

        case 'weekly':
            return date('Y-m-d', strtotime($anchor . ' 12:00:00 +' . ($step * 7) . ' days'));

        case 'yearly':
            $year += $step;
            break;

        default:
            $months = ($month - 1) + $step;
            $year += intdiv($months, 12);
            $month = ($months % 12) + 1;
    }

    $last = (int) date('t', mktime(12, 0, 0, $month, 1, $year));

    return sprintf('%04d-%02d-%02d', $year, $month, min($day, $last));
}

/**
 * The first date of a rule after a given date.
 *
 * A weekly rule with chosen days walks the days, taking the chosen weekdays
 * of every n-th week counted from the anchor's week. The other rules count
 * copies from the anchor ($k is the index of the date returned).
 *
 * @param array  $rule  frequency, interval_count, weekdays, anchor_date
 * @param string $after Y-m-d
 * @param int    $k     in: the index to start looking from; out: the index found
 * @return string Y-m-d
 */
function ws_recurrence_next($rule, $after, &$k)
{
    $interval = max(1, (int) $rule['interval_count']);

    if (((string) $rule['frequency'] === 'weekly') && ((int) $rule['weekdays'] > 0)) {
        $anchor_week = strtotime('monday this week', strtotime($rule['anchor_date'] . ' 12:00:00'));
        $cursor = strtotime($after . ' 12:00:00');

        for ($i = 0; $i < (7 * $interval * 2) + 7; $i++) {
            $cursor = strtotime('+1 day', $cursor);
            $weeks = (int) floor((strtotime('monday this week', $cursor) - $anchor_week) / 604800 + 0.5);

            if (($weeks >= 0) && (($weeks % $interval) === 0) && (((int) $rule['weekdays']) & (1 << ((int) date('N', $cursor) - 1)))) {
                $k++;

                return date('Y-m-d', $cursor);
            }
        }

        // No chosen day was reachable, which only a broken mask gives.
        $k++;

        return date('Y-m-d', strtotime($after . ' 12:00:00 +' . (7 * $interval) . ' days'));
    }

    $k = max(0, (int) $k);

    for ($guard = 0; $guard < 10000; $guard++) {
        $date = ws_recurrence_nth($rule, $k);

        if ($date > $after) {
            return $date;
        }

        $k++;
    }

    return ws_recurrence_nth($rule, $k);
}

/**
 * The rule as people say it, with the day it falls on: "Every month on day
 * 16", "Every 2 weeks on Mon, Thu", "Every year on 16 September", followed by
 * the end date when there is one.
 *
 * @param array $series
 * @return string
 */
function ws_recurrence_label($series)
{
    $interval = max(1, (int) $series['interval_count']);
    $anchor = (string) ($series['anchor_date'] ?? '');
    $time = (($anchor !== '') && ($anchor !== '0000-00-00')) ? strtotime($anchor . ' 12:00:00') : time();

    $say = function ($one, $many, $detail) use ($interval) {
        return ($interval === 1)
            ? lang(array('string' => $one, 'vars' => $detail))
            : lang(array('string' => $many, 'vars' => array($interval, $detail)));
    };

    switch ((string) $series['frequency']) {
        case 'daily':
            $label = ($interval === 1) ? lang('Every day') : lang(array('string' => 'Every {var:1} days', 'vars' => $interval));
            break;

        case 'weekly':
            $days = ws_recurrence_mask_days($series['weekdays']);

            if (($interval === 1) && ($days === array(1, 2, 3, 4, 5))) {
                $label = lang('Every weekday (Mon–Fri)');
                break;
            }

            // No days chosen: the weekday of the due date. One day is named
            // in full, several are listed short.
            if (empty($days) || (count($days) === 1)) {
                $detail = ws_recurrence_weekday_names()[empty($days) ? (int) date('N', $time) : $days[0]];
            } else {
                $names = ws_weekday_short_names();
                $detail = implode(', ', array_map(function ($day) use ($names) {
                    return $names[$day];
                }, $days));
            }

            $label = $say('Every week on {var:1}', 'Every {var:1} weeks on {var:2}', $detail);
            break;

        case 'yearly':
            $detail = (int) date('j', $time) . ' ' . ws_recurrence_month_names()[(int) date('n', $time)];
            $label = $say('Every year on {var:1}', 'Every {var:1} years on {var:2}', $detail);
            break;

        default:
            $label = $say('Every month on day {var:1}', 'Every {var:1} months on day {var:2}', (int) date('j', $time));
    }

    $end = (string) ($series['end_date'] ?? '');

    if (($end !== '') && ($end !== '0000-00-00')) {
        $label .= ' · ' . lang(array('string' => 'until {var:1}', 'vars' => date('d.m.Y', strtotime($end . ' 12:00:00'))));
    }

    return $label;
}

/**
 * Why a series ended, in words.
 *
 * @param string $reason
 * @return string
 */
function ws_recurrence_reason_label($reason)
{
    switch ((string) $reason) {
        case 'completed':
            return lang('The task was completed for good.');
        case 'end_date':
            return lang('The repeat reached its end date.');
        case 'failed':
            return lang('The next copy could not be handed out, so the repeat stopped.');
    }

    return lang('The repeat was switched off.');
}

/**
 * What a task's drawer and the API show of its series, or null.
 *
 * @param array $task
 * @return array|null
 */
function ws_recurrence_detail($task)
{
    if (!ws_recurrence_ready() || ((int) ($task['recurrence_id'] ?? 0) <= 0)) {
        return null;
    }

    $series = ws_recurrence($task['recurrence_id']);

    if (!$series) {
        return null;
    }

    // Only the newest copy changes the rule: the ones before it are done
    // with, or being worked off.
    $shape = ws_recurrence_shape($series);
    $shape['latest'] = ((int) $series['last_task_id'] === (int) $task['id']);
    $shape['latest_number'] = ws_task_number($series['last_task_id']);

    // The day of the rule this copy stands for, and whether it was moved off
    // a day off to where it is due now.
    $shape['base_date'] = ws_recurrence_base_date($task);
    $shape['moved_from'] = ws_recurrence_is_moved($task) ? $shape['base_date'] : null;

    return $shape;
}

/**
 * @param array $series
 * @return array
 */
function ws_recurrence_shape($series)
{
    $date = function ($value) {
        $value = (string) $value;

        return (($value !== '') && ($value !== '0000-00-00')) ? $value : null;
    };

    $active = ((string) $series['status'] === 'active');
    $newest = $active ? ws_task($series['last_task_id']) : null;

    return array(
        'id'           => (int) $series['id'],
        'first_task_id' => (int) $series['first_task_id'],
        'last_task_id' => (int) $series['last_task_id'],
        'frequency'    => (string) $series['frequency'],
        'interval'     => max(1, (int) $series['interval_count']),
        'weekdays'     => ws_recurrence_mask_days($series['weekdays']),
        'anchor_date'  => $date($series['anchor_date']),
        'next_date'    => $active ? $date($series['next_date']) : null,
        'opens_date'   => ($active && $date($series['next_date'])) ? ws_recurrence_opens($series['next_date'], (int) ($series['lead_days'] ?? 0)) : null,
        'end_date'     => $date($series['end_date']),
        'occurrences'  => (int) $series['occurrences'],
        'status'       => (string) $series['status'],
        'active'       => $active,
        'ended_reason' => (string) $series['ended_reason'],
        'ended_label'  => $active ? '' : ws_recurrence_reason_label($series['ended_reason']),
        'label'        => ws_recurrence_label($series),
        'lead_days'    => (int) ($series['lead_days'] ?? 0),
        'upcoming'     => $active ? ws_recurrence_upcoming($series, date('Y-m-d'), '9999-12-31', 3, ws_recurrence_held_until($newest), (int) ($newest['department_id'] ?? 0)) : array(),
        'late'         => $active && ws_recurrence_late($newest),
    );
}

/**
 * Until when the newest copy of a series holds the next ones back: to the
 * end of its days, its due date, whether it is open or already done. A copy
 * still open after that holds them back as well (ws_recurrence_run()).
 *
 * @param array|null $task the series' newest copy
 * @return string Y-m-d, or '' when there is none
 */
function ws_recurrence_held_until($task)
{
    return $task ? ws_task_occurrence_date($task) : '';
}

/**
 * Whether a series' newest copy is overdue: past its date and still open.
 * The coming copies wait for it: none is handed out while it is open, so
 * each comes only if it is done before that copy's start.
 *
 * @param array|null $task the series' newest copy
 * @return bool
 */
function ws_recurrence_late($task)
{
    return $task && ws_task_is_open($task['status']) && (ws_task_occurrence_date($task) < date('Y-m-d'));
}

/**
 * The copies a series has still to hand out whose days touch a range,
 * soonest first. A copy runs from its start (lead_days before it is due) to
 * its due date.
 *
 * One copy at a time: each copy is counted as done on its due date, and a
 * copy whose start falls within the days of the one before is passed over,
 * the way ws_recurrence_run() passes it over.
 *
 * The days are the copies' own: moved off weekends and holidays the way they
 * will be handed out (ws_recurrence_copy_dates()).
 *
 * @param array  $series
 * @param string $from          Y-m-d
 * @param string $to            Y-m-d
 * @param int    $limit
 * @param string $held_until    Y-m-d the newest copy's days end on (ws_recurrence_held_until())
 * @param int    $department_id the task's department, whose days off count too
 * @return array[] due, start, date (the day of the rule), moved
 */
function ws_recurrence_upcoming($series, $from, $to, $limit = 62, $held_until = '', $department_id = 0)
{
    $out = array();
    $date = (string) ($series['next_date'] ?? '');

    if (((string) ($series['status'] ?? '') !== 'active') || ($date === '') || ($date === '0000-00-00')) {
        return $out;
    }

    $end = (string) ($series['end_date'] ?? '');
    $end = (($end !== '') && ($end !== '0000-00-00')) ? $end : null;
    $lead = (int) ($series['lead_days'] ?? 0);
    $k = (int) $series['occurrences'];
    $running = (string) $held_until;

    for ($guard = 0; ($guard < 1000) && (count($out) < $limit); $guard++) {
        if (($end !== null) && ($date > $end)) {
            break;
        }

        // A move only ever goes later, so a copy whose own start is past the
        // range is past it moved as well.
        if (ws_recurrence_opens($date, $lead) > $to) {
            break;
        }

        $copy = ws_recurrence_copy_dates($date, $lead, $department_id);

        if (($running === '') || ($copy['start'] > $running)) {
            if (($copy['due'] >= $from) && ($copy['start'] <= $to)) {
                $out[] = $copy;
            }

            $running = $copy['due'];
        }

        $date = ws_recurrence_next($series, $date, $k);
    }

    return $out;
}

/**
 * The coming copies of every repeating task between two dates, for the
 * screens that show them ahead of time. Each series comes with its newest
 * copy, which the coming ones repeat, and that copy's people.
 *
 * @param string $from Y-m-d
 * @param string $to   Y-m-d
 * @return array[] series, task, people (user ids), copies (see ws_recurrence_upcoming()),
 *                 late (the newest copy is overdue and the copies wait for it)
 */
function ws_recurrence_projections($from, $to)
{
    if (!ws_recurrence_ready()) {
        return array();
    }

    $opens = ws_recurrence_has_lead() ? 'DATE_SUB(next_date, INTERVAL lead_days DAY)' : 'next_date';
    $series_rows = (array) db_items("SELECT * FROM ws_task_recurrences
        WHERE status = 'active' AND next_date IS NOT NULL
        AND " . $opens . " <= '" . e($to) . "'
        AND (end_date IS NULL OR end_date >= '" . e($from) . "')
        ORDER BY id
        LIMIT 500");

    if (empty($series_rows)) {
        return array();
    }

    $task_ids = array();

    foreach ($series_rows as $series) {
        $task_ids[] = (int) $series['last_task_id'];
    }

    $tasks = array();

    foreach ((array) db_items("SELECT * FROM ws_tasks WHERE id IN (" . implode(',', array_unique($task_ids)) . ")") as $task) {
        $tasks[(int) $task['id']] = $task;
    }

    $people = ws_task_assignees_map(array_keys($tasks));
    $out = array();

    foreach ($series_rows as $series) {
        $task = $tasks[(int) $series['last_task_id']] ?? null;

        if (!$task) {
            continue;
        }

        $copies = ws_recurrence_upcoming($series, $from, $to, 62, ws_recurrence_held_until($task), (int) $task['department_id']);

        if (empty($copies)) {
            continue;
        }

        $out[] = array(
            'series' => $series,
            'task'   => $task,
            'people' => array_values($people[(int) $task['id']] ?? array()),
            'copies' => $copies,
            'late'   => ws_recurrence_late($task),
        );
    }

    return $out;
}

/**
 * Checks a rule sent by a screen or the API.
 *
 * @param mixed $data frequency (daily | weekly | monthly | yearly | none),
 *                    interval, weekdays (ISO days), end_date
 * @return array ok, error, field, rule (frequency 'none' for no repeat)
 */
function ws_recurrence_validate($data)
{
    $fail = function ($message, $field) {
        return array('ok' => false, 'error' => $message, 'field' => $field, 'rule' => array());
    };

    $data = is_array($data) ? $data : array();
    $frequency = (string) ($data['frequency'] ?? 'none');

    if ($frequency === 'none' || $frequency === '') {
        return array('ok' => true, 'error' => '', 'field' => '', 'rule' => array('frequency' => 'none'));
    }

    if (!isset(ws_recurrence_frequencies()[$frequency])) {
        return $fail(lang('That is not a way a task can repeat.'), 'recurrence');
    }

    $interval = (int) ($data['interval'] ?? 1);

    if (($interval < 1) || ($interval > 99)) {
        return $fail(lang('A task repeats every 1 to 99 days, weeks, months or years.'), 'recurrence');
    }

    $end = ws_date_or_null($data['end_date'] ?? '');

    if ($end === false) {
        return $fail(lang('Write the date as day.month.year.'), 'recurrence');
    }

    return array('ok' => true, 'error' => '', 'field' => '', 'rule' => array(
        'frequency'      => $frequency,
        'interval_count' => $interval,
        'weekdays'       => ($frequency === 'weekly') ? ws_recurrence_days_mask($data['weekdays'] ?? array()) : 0,
        'end_date'       => $end,
    ));
}

/**
 * Sets, changes or switches off the repeat of a task.
 *
 * Setting it on a task that does not repeat starts a series with this task
 * as its first copy. Changing it keeps the series and counts the new rule
 * from the latest copy, so no copy is made twice and none is skipped by the
 * change. A task with no date takes today as its due date: a copy is due on
 * a day, and the rule counts from it.
 *
 * The same rule sent again keeps the schedule, unless the newest copy's date
 * was moved in the same save: then the series counts from the new date, the
 * way the drawer says it will ("every month on day 18" once the 16th became
 * the 18th), and the copy stands for that date from then on.
 *
 * @param array      $viewer
 * @param array      $task   the task as saved
 * @param mixed      $data   see ws_recurrence_validate()
 * @param array|null $before the task as it was before the save
 * @return array ok, error, field, changed
 */
function ws_recurrence_save($viewer, $task, $data, $before = null)
{
    if (!ws_recurrence_ready()) {
        return array('ok' => false, 'error' => lang('The workspace is not installed yet: the database has to be updated first.'), 'field' => '', 'changed' => false);
    }

    if (!ws_can_edit_task($viewer, $task)) {
        return array('ok' => false, 'error' => lang('You cannot change this task.'), 'field' => '', 'changed' => false);
    }

    $check = ws_recurrence_validate($data);

    if (!$check['ok']) {
        return $check + array('changed' => false);
    }

    $rule = $check['rule'];
    $series = ws_recurrence($task['recurrence_id'] ?? 0);
    $active = $series && ((string) $series['status'] === 'active');
    $now = time();

    if ($rule['frequency'] === 'none') {
        if ($active) {
            ws_recurrence_close($series, 'stopped', (int) $viewer['id']);

            return array('ok' => true, 'error' => '', 'field' => '', 'changed' => true);
        }

        return array('ok' => true, 'error' => '', 'field' => '', 'changed' => false);
    }

    $latest_moved = $active && is_array($before)
        && ((int) $series['last_task_id'] === (int) $task['id'])
        && (ws_task_occurrence_date($before) !== ws_task_occurrence_date($task));

    // The dates typed in this save say how long a copy runs. A copy the
    // calendar moved off a day off, saved as it is, says nothing: its gap is
    // the move's, not the task's.
    $dates_typed = is_array($before)
        && (((string) ($before['start_date'] ?? '') !== (string) ($task['start_date'] ?? ''))
            || ((string) ($before['due_date'] ?? '') !== (string) ($task['due_date'] ?? '')));
    $lead_of = function ($copy) use ($series, $dates_typed) {
        return ($series && !$dates_typed && ws_recurrence_is_moved($copy))
            ? (int) ($series['lead_days'] ?? 0)
            : ws_recurrence_lead($copy);
    };

    // The same rule sent again (a task saved without touching its repeat)
    // leaves the schedule as it is.
    if ($active && !$latest_moved
        && ((string) $series['frequency'] === $rule['frequency'])
        && ((int) $series['interval_count'] === (int) $rule['interval_count'])
        && ((int) $series['weekdays'] === (int) $rule['weekdays'])
        && ((string) ($series['end_date'] ?? '') === (string) ($rule['end_date'] ?? ''))) {
        // The newest copy's dates may have moved: the next copy opens as far
        // ahead of its due date as this one does.
        if (ws_recurrence_has_lead() && ((int) $series['last_task_id'] === (int) $task['id'])) {
            db("UPDATE ws_task_recurrences SET lead_days = '" . (int) $lead_of($task) . "' WHERE id = '" . (int) $series['id'] . "'");
        }

        return array('ok' => true, 'error' => '', 'field' => '', 'changed' => false);
    }

    // A copy is due on a day.
    if (((string) ($task['due_date'] ?? '') === '') && ((string) ($task['start_date'] ?? '') === '')) {
        db("UPDATE ws_tasks SET due_date = '" . e(date('Y-m-d')) . "', updated_at = '" . $now . "' WHERE id = '" . (int) $task['id'] . "'");
        $task = ws_task($task['id']);
    }

    // The rule counts from the latest copy: this task when it is the latest,
    // otherwise the series' newest one.
    $latest = $task;

    if ($active && ((int) $series['last_task_id'] > 0) && ((int) $series['last_task_id'] !== (int) $task['id'])) {
        $newest = ws_task($series['last_task_id']);

        if ($newest && (ws_task_occurrence_date($newest) > ws_task_occurrence_date($task))) {
            $latest = $newest;
        }
    }

    // Counted from the day of the rule the latest copy stands for, unless its
    // date was changed in this save: then from the day chosen.
    $own_date = ((int) $latest['id'] === (int) $task['id']) && $dates_typed;
    $anchor = $own_date ? ws_task_occurrence_date($latest) : ws_recurrence_base_date($latest);
    $rule['anchor_date'] = $anchor;
    $lead_sql = ws_recurrence_has_lead() ? ", lead_days = '" . (int) $lead_of($latest) . "'" : '';
    $k = 0;
    $next = ws_recurrence_next($rule, $anchor, $k);
    $ended = (($rule['end_date'] ?? null) !== null) && ($next > $rule['end_date']);
    $end_sql = ($rule['end_date'] === null) ? 'NULL' : "'" . e($rule['end_date']) . "'";

    if ($active) {
        db("UPDATE ws_task_recurrences SET
                frequency = '" . e($rule['frequency']) . "',
                interval_count = '" . (int) $rule['interval_count'] . "',
                weekdays = '" . (int) $rule['weekdays'] . "',
                anchor_date = '" . e($anchor) . "',
                next_date = '" . e($next) . "',
                end_date = " . $end_sql . ",
                occurrences = '" . (int) $k . "',
                updated_at = '" . $now . "'" . $lead_sql . "
            WHERE id = '" . (int) $series['id'] . "'");

        $series_id = (int) $series['id'];
    } else {
        db("INSERT INTO ws_task_recurrences
                (first_task_id, last_task_id, frequency, interval_count, weekdays, anchor_date, next_date, end_date,
                 occurrences, status, created_by, created_at, updated_at)
            VALUES (
                '" . (int) $task['id'] . "',
                '" . (int) $task['id'] . "',
                '" . e($rule['frequency']) . "',
                '" . (int) $rule['interval_count'] . "',
                '" . (int) $rule['weekdays'] . "',
                '" . e($anchor) . "',
                '" . e($next) . "',
                " . $end_sql . ",
                '" . (int) $k . "',
                'active',
                '" . (int) $viewer['id'] . "',
                '" . $now . "',
                '" . $now . "')");

        $series_id = (int) mysqli_insert_id(db::$con);

        if ($series_id <= 0) {
            return array('ok' => false, 'error' => lang('The repeat could not be saved.'), 'field' => 'recurrence', 'changed' => false);
        }

        db("UPDATE ws_tasks SET recurrence_id = '" . $series_id . "', updated_at = '" . $now . "' WHERE id = '" . (int) $task['id'] . "'");

        if ($lead_sql !== '') {
            db("UPDATE ws_task_recurrences SET " . ltrim($lead_sql, ', ') . " WHERE id = '" . $series_id . "'");
        }
    }

    // The series counts from this task's own date now: a day of the rule it
    // stood for before (a copy moved off a day off) no longer holds.
    if ($own_date && function_exists('ws_workdays_ready') && ws_workdays_ready()) {
        db("UPDATE ws_tasks SET recurrence_date = NULL WHERE id = '" . (int) $task['id'] . "'");
    }

    // An end date before the next copy leaves nothing more to hand out.
    if ($ended) {
        ws_recurrence_close(ws_recurrence($series_id), 'end_date', 0);
    }

    return array('ok' => true, 'error' => '', 'field' => '', 'changed' => true);
}

/**
 * After a copy of a repeating task was moved to another day on the board:
 * either the copies after it move with it ($following), the way changing the
 * newest copy's due date in its drawer moves them, or only this copy moved
 * and it keeps standing for its day of the rule.
 *
 * Only the newest copy of a running series takes the ones after it along; an
 * earlier copy only ever moves itself. A weekly series kept on the weekday of
 * its date follows the new date's weekday, as "Every week on …" does in the
 * drawer. The series keeps its lead: a copy the calendar moved off a day off
 * may run longer than a copy does.
 *
 * @param array $viewer
 * @param array $before    the task before the move
 * @param array $task      the task after the move
 * @param bool  $following the copies after it move too
 * @return bool whether the series moved
 */
function ws_recurrence_moved($viewer, $before, $task, $following)
{
    if (!ws_recurrence_ready() || ((int) ($task['recurrence_id'] ?? 0) <= 0)
        || (ws_task_occurrence_date($before) === ws_task_occurrence_date($task))) {
        return false;
    }

    $series = ws_recurrence($task['recurrence_id']);
    $latest = $series && ((string) $series['status'] === 'active') && ((int) $series['last_task_id'] === (int) $task['id']);

    if (!$following || !$latest) {
        // The copy still stands for the day it was due on, so the series and
        // the copy's drawer keep reading the rule from that day.
        $kept = (string) ($before['recurrence_date'] ?? '');

        if ((($kept === '') || ($kept === '0000-00-00')) && function_exists('ws_workdays_ready') && ws_workdays_ready()) {
            db("UPDATE ws_tasks SET recurrence_date = '" . e(ws_task_occurrence_date($before)) . "' WHERE id = '" . (int) $task['id'] . "'");
        }

        return false;
    }

    $days = ws_recurrence_mask_days($series['weekdays']);

    if (((string) $series['frequency'] === 'weekly') && (count($days) === 1)
        && ($days[0] === (int) date('N', strtotime(ws_recurrence_base_date($before) . ' 12:00:00')))) {
        $days = array();
    }

    $end = (string) ($series['end_date'] ?? '');
    $result = ws_recurrence_save($viewer, $task, array(
        'frequency' => (string) $series['frequency'],
        'interval'  => max(1, (int) $series['interval_count']),
        'weekdays'  => $days,
        'end_date'  => (($end !== '') && ($end !== '0000-00-00')) ? $end : '',
    ), $before);

    if (!$result['ok']) {
        return false;
    }

    if (ws_recurrence_has_lead()) {
        db("UPDATE ws_task_recurrences SET lead_days = '" . (int) ($series['lead_days'] ?? 0) . "' WHERE id = '" . (int) $series['id'] . "'");
    }

    return true;
}

/**
 * Ends a series. The copies already handed out are left as they are.
 *
 * @param array  $series
 * @param string $reason completed | end_date | stopped | failed
 * @param int    $user_id who ended it, 0 for the schedule
 * @return void
 */
function ws_recurrence_close($series, $reason, $user_id)
{
    if (!$series || ((string) $series['status'] !== 'active')) {
        return;
    }

    db("UPDATE ws_task_recurrences SET
            status = 'ended',
            ended_reason = '" . e($reason) . "',
            ended_by = '" . (int) $user_id . "',
            ended_at = '" . time() . "',
            updated_at = '" . time() . "'
        WHERE id = '" . (int) $series['id'] . "' AND status = 'active'");
}

/**
 * Ends the repeat of a task by hand. 'complete' is completing the task for
 * good: the copy in hand is marked done as well, and no more are made.
 * 'stop' only stops the copies.
 *
 * @param array  $viewer
 * @param array  $task
 * @param string $mode complete | stop
 * @return array ok, error
 */
function ws_recurrence_end($viewer, $task, $mode)
{
    $series = ws_recurrence($task['recurrence_id'] ?? 0);

    if (!$series) {
        return array('ok' => false, 'error' => lang('This task does not repeat.'));
    }

    if (!ws_can_edit_task($viewer, $task)) {
        return array('ok' => false, 'error' => lang('You cannot change this task.'));
    }

    if ($mode === 'complete') {
        if (ws_task_is_open($task['status'])) {
            $result = ws_task_set_status($viewer, $task, 'done');

            if (!$result['ok']) {
                return $result;
            }
        }

        ws_recurrence_close($series, 'completed', (int) $viewer['id']);
    } else {
        ws_recurrence_close($series, 'stopped', (int) $viewer['id']);
    }

    return array('ok' => true, 'error' => '');
}

/**
 * The account a series acts as when the schedule hands out a copy: the
 * person who set the repeat, with their rights as they are today.
 *
 * @param array $series
 * @return array|null
 */
function ws_recurrence_actor($series)
{
    $row = function_exists('pg_load_user_row') ? pg_load_user_row((int) $series['created_by']) : null;

    if (!is_array($row) || ((int) ($row['id'] ?? 0) <= 0)) {
        return null;
    }

    $viewer = ws_viewer($row);

    return $viewer['member'] ? $viewer : null;
}

/**
 * Hands out one copy of a series for a date.
 *
 * The newest copy is the template, so a title or a person changed on it
 * carries on. The copy's start keeps the template's lead time before its due
 * date.
 *
 * @param array  $series
 * @param string $date Y-m-d, the copy's due date
 * @return int the new task's id, 0 when it could not be made
 */
function ws_recurrence_spawn($series, $date)
{
    $template = ws_task($series['last_task_id']) ?: ws_task($series['first_task_id']);
    $actor = ws_recurrence_actor($series);

    if (!$template || !$actor) {
        return 0;
    }

    // The series' lead, not the template's own gap: a template moved off a
    // weekend runs longer than the task does.
    $lead = ws_recurrence_has_lead() ? (int) ($series['lead_days'] ?? 0) : ws_recurrence_lead($template);
    $copy = ws_recurrence_copy_dates($date, $lead, (int) $template['department_id']);
    $start = ((string) ($template['start_date'] ?? '') !== '') ? $copy['start'] : null;

    $refs = array();

    foreach ((array) db_items("SELECT ref_type, ref_id FROM ws_refs WHERE source_type = 'task' AND source_id = '" . (int) $template['id'] . "'") as $ref) {
        if (!in_array($ref['ref_type'], array('user', 'dept'), true)) {
            $refs[] = '<#' . $ref['ref_type'] . ':' . (int) $ref['ref_id'] . '>';
        }
    }

    // A channel the person who set the repeat can no longer write in: the
    // copy is handed out without it rather than not at all.
    $channel_id = (int) $template['channel_id'];

    if (($channel_id > 0) && !ws_can_post_channel($actor, ws_channel($channel_id))) {
        $channel_id = 0;
    }

    $result = ws_task_create($actor, array(
        'title'            => (string) $template['title'],
        'description'      => ws_recurrence_description($template),
        'priority'         => (string) $template['priority'],
        'status'           => 'todo',
        'start_date'       => $start,
        'due_date'         => $copy['due'],
        'estimate_minutes' => (int) $template['estimate_minutes'],
        'department_id'    => (int) $template['department_id'],
        'channel_id'       => $channel_id,
        'assignees'        => ws_task_assignee_ids($template['id']),
        'refs'             => $refs,
    ));

    if (!$result['ok']) {
        return 0;
    }

    $task_id = (int) $result['task_id'];

    db("UPDATE ws_tasks SET recurrence_id = '" . (int) $series['id'] . "'"
        . ((function_exists('ws_workdays_ready') && ws_workdays_ready()) ? ", recurrence_date = '" . e($date) . "'" : '') . "
        WHERE id = '" . $task_id . "'");

    // The channel the task belongs to gets its card, the way a task made
    // there by hand does.
    if ($channel_id > 0) {
        $channel = ws_channel($channel_id);

        if ($channel && ws_can_post_channel($actor, $channel)) {
            $sent = ws_message_send($actor, $channel, '', array('kind' => 'task', 'task_id' => $task_id));

            if (!empty($sent['ok'])) {
                db("UPDATE ws_tasks SET source_message_id = '" . (int) $sent['message_id'] . "' WHERE id = '" . $task_id . "'");
            }
        }
    }

    return $task_id;
}

/**
 * The text a copy starts with. The "- [ ]" lines of the task's own text come
 * back unticked: ticks are kept per task, so every copy starts its list
 * fresh. A checklist the task carried from a channel message stays with that
 * message and its one set of ticks; the copy gets its lines, unticked, under
 * its own text instead.
 *
 * @param array $template
 * @return string
 */
function ws_recurrence_description($template)
{
    // A line written as done ("- [x]") starts the copy undone as well; lines
    // inside a block of code are code and stay as written.
    $lines = preg_split('/\r\n|\r|\n/', (string) $template['description']);
    $code = false;

    foreach ($lines as $index => $line) {
        if ($code) {
            $code = !ws_code_fence_close($line);
            continue;
        }

        if (ws_code_fence_open($line) !== null) {
            $code = true;
            continue;
        }

        $lines[$index] = (string) preg_replace('/^(\s*[-*]\s)\[[xX]\](\s+)/u', '$1[ ]$2', $line);
    }

    $description = implode("\n", $lines);

    if (!function_exists('ws_task_checklist_message') || !function_exists('ws_checklist_items')) {
        return $description;
    }

    $message = ws_task_checklist_message($template);

    if (!$message) {
        return $description;
    }

    $lines = array();

    foreach (ws_checklist_items($message['body'] ?? '') as $item) {
        $lines[] = '- [ ] ' . $item['text'];
    }

    if (empty($lines)) {
        return $description;
    }

    return trim($description . "\n\n" . implode("\n", $lines));
}

/**
 * The scheduled run: hands out every copy that has come due.
 *
 * A series that fell behind (the job did not run for a few days) gets one
 * copy, for the latest date that has come, not one for every date it
 * missed: a pile of identical overdue tasks helps nobody. For the same
 * reason no copy is handed out within the days of the newest one (a copy
 * that starts on or before its due date), nor while it is still open: the
 * date is passed over, and an open copy stays open, overdue, until somebody
 * deals with it. Each series is claimed by moving its next date with a
 * guarded update before its copy is made, so two runs that overlap cannot
 * hand out the same copy twice.
 *
 * @param string|null $today Y-m-d, today by default
 * @return array made, ended, failed, passed (dates passed over for the copy before)
 */
function ws_recurrence_run($today = null)
{
    $summary = array('made' => 0, 'ended' => 0, 'failed' => 0, 'passed' => 0);

    if (!ws_recurrence_ready()) {
        return $summary;
    }

    $today = ($today !== null) ? (string) $today : date('Y-m-d');

    // A copy opens on its start day, lead_days before it is due.
    $opens = ws_recurrence_has_lead() ? "DATE_SUB(next_date, INTERVAL lead_days DAY)" : 'next_date';

    $due = (array) db_items("SELECT * FROM ws_task_recurrences
        WHERE status = 'active' AND next_date IS NOT NULL AND " . $opens . " <= '" . e($today) . "'
        ORDER BY next_date, id
        LIMIT " . (int) WS_RECURRENCE_BATCH);

    foreach ($due as $series) {
        $end = (string) ($series['end_date'] ?? '');
        $end = (($end !== '') && ($end !== '0000-00-00')) ? $end : null;
        $date = (string) $series['next_date'];

        if (($end !== null) && ($date > $end)) {
            ws_recurrence_close($series, 'end_date', 0);
            $summary['ended']++;
            continue;
        }

        // The latest copy that has opened, the ones before it passed over.
        $lead = (int) ($series['lead_days'] ?? 0);
        $k = (int) $series['occurrences'];
        $after = ws_recurrence_next($series, $date, $k);

        while ((ws_recurrence_opens($after, $lead) <= $today) && (($end === null) || ($after <= $end))) {
            $date = $after;
            $after = ws_recurrence_next($series, $date, $k);
        }

        db("UPDATE ws_task_recurrences SET
                next_date = '" . e($after) . "',
                occurrences = '" . (int) $k . "',
                updated_at = '" . time() . "'
            WHERE id = '" . (int) $series['id'] . "' AND status = 'active' AND next_date = '" . e($series['next_date']) . "'");

        if (mysqli_affected_rows(db::$con) !== 1) {
            continue;
        }

        // One copy at a time: a date that falls within the newest copy's days,
        // or comes while it is still open, is passed over rather than handed
        // out on top of it.
        $newest = ws_task($series['last_task_id']);
        $held = ws_recurrence_held_until($newest);

        $copy = ws_recurrence_copy_dates($date, $lead, (int) ($newest['department_id'] ?? 0));

        if (($newest && ws_task_is_open($newest['status'])) || (($held !== '') && ($copy['start'] <= $held))) {
            $summary['passed']++;

            if (($end !== null) && ($after > $end)) {
                ws_recurrence_close(ws_recurrence($series['id']), 'end_date', 0);
                $summary['ended']++;
            }

            continue;
        }

        $task_id = ws_recurrence_spawn($series, $date);

        if ($task_id <= 0) {
            ws_recurrence_close($series, 'failed', 0);
            $summary['failed']++;

            if (function_exists('log_activity')) {
                log_activity(lang(array('string' => 'repeating workspace task ({var:1}) was stopped, its next copy could not be made', 'vars' => ws_task_number($series['last_task_id']))), 'SYSTEM');
            }

            continue;
        }

        // The newest copy is the template of the next one. The lead stays the
        // series': the copy may have been moved off a day off.
        db("UPDATE ws_task_recurrences SET last_task_id = '" . $task_id . "' WHERE id = '" . (int) $series['id'] . "'");
        $summary['made']++;

        // The copy just made was the last one the end date allows.
        if (($end !== null) && ($after > $end)) {
            ws_recurrence_close(ws_recurrence($series['id']), 'end_date', 0);
            $summary['ended']++;
        }
    }

    return $summary;
}

/**
 * The run without a scheduler: a workspace screen that opens calls this, and
 * it runs ws_recurrence_run() when the last run is more than a quarter of an
 * hour old. A site that has not set up its scheduled jobs still gets its
 * copies the first time somebody opens the workspace that day; one that has
 * pays one indexed read. Two screens opening at once cannot hand out a copy
 * twice: every copy is claimed by a guarded update.
 *
 * @return void
 */
function ws_recurrence_tick()
{
    if (!ws_recurrence_ready()) {
        return;
    }

    $last = (int) db_value("SELECT last_run_at FROM cron_runs WHERE job_name = 'workspace_recurring_job'");

    if ($last > (time() - 900)) {
        return;
    }

    ws_recurrence_run();

    if (function_exists('pg_cron_ran')) {
        pg_cron_ran('workspace_recurring_job');
    }
}

/**
 * The texts the repeat field in the task drawer shows.
 *
 * @return array key => text
 */
function ws_recurrence_js_strings()
{
    return array(
        'repeat'                => lang('Repeat'),
        'repeat_none'           => lang('Does not repeat'),
        'repeat_every'          => lang('Every'),
        'repeat_unit_daily'     => lang('day(s)'),
        'repeat_unit_weekly'    => lang('week(s)'),
        'repeat_unit_monthly'   => lang('month(s)'),
        'repeat_unit_yearly'    => lang('year(s)'),
        'repeat_on_days'        => lang('On these days'),
        'repeat_until'          => lang('Repeat until'),
        'repeat_when'           => lang('Dates and repeat'),
        'repeat_daily'          => lang('Every day'),
        'repeat_weekdays'       => lang('Every weekday (Mon–Fri)'),
        'repeat_weekly_on'      => ws_js_template('Every week on {var:1}', 1),
        'repeat_monthly_on'     => ws_js_template('Every month on day {var:1}', 1),
        'repeat_yearly_on'      => ws_js_template('Every year on {var:1}', 1),
        'repeat_n_days'         => ws_js_template('Every {var:1} days', 1),
        'repeat_n_weeks_on'     => ws_js_template('Every {var:1} weeks on {var:2}', 2),
        'repeat_n_months_on'    => ws_js_template('Every {var:1} months on day {var:2}', 2),
        'repeat_n_years_on'     => ws_js_template('Every {var:1} years on {var:2}', 2),
        'repeat_custom'         => lang('Custom…'),
        'repeat_ends'           => lang('Repeat ends'),
        'repeat_ends_never'     => lang('Keeps repeating'),
        'repeat_ends_on'        => lang('On a date'),
        'repeat_until_date'     => ws_js_template('until {var:1}', 1),
        'repeat_opens_start'    => ws_js_template('Each copy opens on its start day and runs {var:1} days, like this one.', 1),
        'repeat_opens_due'      => lang('Each copy opens on its due day.'),
        'repeat_upcoming'       => lang('Next copies:'),
        'repeat_overlap'        => ws_js_template('This task runs {var:1} days, longer than the time between copies. No copy is handed out within the days of the one before, so the dates in between are passed over. To repeat for a period instead, put its last day in Repeat ends.', 1),
        'repeat_due_filled'     => ws_js_template('A repeat counts from the due date, so it was set to {var:1}.', 1),
        'repeat_follows_date'   => lang('Moving the due date moves the copies after it as well.'),
        'repeat_older_copy'     => ws_js_template('This is an earlier copy. The repeat is changed on the newest one, {var:1}.', 1),
        'repeat_open_latest'    => ws_js_template('Open {var:1}', 1),
        'repeat_late'           => lang('This copy is overdue. The next copies wait for it: none is handed out while it is open, so a date that comes before it is done is passed over.'),
        'repeat_moved_hand'     => ws_js_template('This copy stands for {var:1} in the repeat and was moved by hand; the copies after it keep their days.', 1),
        'upcoming_waits'        => ws_js_template('{var:1} is overdue: this copy comes only if it is done before this copy starts.', 1),
        'move_repeat_ask'       => lang('This task repeats. Move only this copy, or the copies after it as well?'),
        'move_repeat_one'       => lang('Only this copy'),
        'move_repeat_following' => lang('This and the following copies'),
        'move_repeat_help'      => lang('The following copies then count from the new day, as when the due date is changed in the task.'),
        'move_repeat_ok'        => lang('Move'),
        'upcoming_copy'         => ws_js_template('Coming copy of a repeating task, due {var:1}', 1),
        'upcoming_hint'         => lang('Not handed out yet: it opens on its day. Clicking it opens the task it repeats.'),
        'upcoming_count'        => ws_js_template('{var:1} more copies of repeating tasks are coming this month.', 1),
        'repeat_help'           => lang('A copy goes to the same people on every date. No new copy is handed out within the days of the one before or while it is open: an unfinished copy stays open, overdue, and the next one comes on the first date after it is done. "Complete for good" stops the copies, and so does the day the repeat ends.'),
        'repeat_next'           => ws_js_template('Next copy: {var:1}', 1),
        'repeat_complete'       => lang('Complete for good'),
        'repeat_complete_ask'   => lang('Mark this task done and stop the copies? The copies already handed out stay as they are.'),
        'repeat_completed'      => lang('The task is done and will not come back.'),
        'repeat_ended'          => lang('This repeat has ended.'),
        'repeating'             => lang('Repeating task'),
    ) + (function_exists('ws_workdays_js_strings') ? ws_workdays_js_strings() : array());
}

/**
 * What the repeat field needs besides its texts.
 *
 * @return array ready, frequencies, weekdays, weekday_names, months, today,
 *               calendar (the working week and the days off, or null)
 */
function ws_recurrence_js_config()
{
    return array(
        'ready'         => ws_recurrence_ready(),
        'frequencies'   => ws_recurrence_frequencies(),
        'weekdays'      => ws_weekday_short_names(),
        'weekday_names' => ws_recurrence_weekday_names(),
        'months'        => ws_recurrence_month_names(),
        'today'         => date('Y-m-d'),
        'calendar'      => (function_exists('ws_workdays_ready') && ws_workdays_ready()) ? ws_workdays_js_config() : null,
    );
}
