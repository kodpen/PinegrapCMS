<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - the plan: how much of each person's day is already spoken for,
 * and what a new piece of work would do to it.
 *
 * A task's estimate is spread evenly over the working days between its start
 * and its due date (the due day alone when it has no start). A task with no
 * estimate counts the site's default. Leave takes the day away; a meeting or a
 * visit takes its hours. Holidays - company-wide, or for one department - are
 * not working days at all.
 *
 * The board never refuses anything by itself. It says what an assignment
 * would do - "Ayşe is at 140% on Thursday" - and leaves the choice to the
 * person handing out the work. Only a clash with somebody's leave is a hard
 * stop, and even that is one a board holder may override.
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
 * The days from one date to another, inclusive, as Y-m-d.
 *
 * @param string $from
 * @param string $to
 * @return string[]
 */
function ws_days($from, $to)
{
    $days = array();
    $cursor = strtotime($from . ' 12:00:00');
    $end = strtotime($to . ' 12:00:00');

    if (($cursor === false) || ($end === false)) {
        return $days;
    }

    // DST-safe: stepping by a day from noon never lands on the wrong date.
    for ($guard = 0; ($cursor <= $end) && ($guard < 400); $guard++) {
        $days[] = date('Y-m-d', $cursor);
        $cursor = strtotime('+1 day', $cursor);
    }

    return $days;
}

/**
 * Does a person work on this weekday?
 *
 * @param string $date
 * @param int    $mask Monday = bit 0
 * @return bool
 */
function ws_is_workday($date, $mask)
{
    $weekday = (int) date('N', strtotime($date . ' 12:00:00'));

    return (bool) ($mask & (1 << ($weekday - 1)));
}

/**
 * A moment moved by whole years, on the same day and at the same time; a
 * 29 February lands on the 28th in a year without one.
 *
 * @param int $time
 * @param int $years
 * @return int
 */
function ws_event_in_year($time, $years)
{
    $year = (int) date('Y', $time) + (int) $years;
    $month = (int) date('n', $time);
    $day = min((int) date('j', $time), (int) date('t', mktime(12, 0, 0, $month, 1, $year)));

    return mktime((int) date('G', $time), (int) date('i', $time), (int) date('s', $time), $month, $day, $year);
}

/**
 * The events that touch a range of days, and who each one is for.
 *
 * An item marked yearly (a holiday on the same day every year) is brought
 * into every later year of the range too, with the same id: opening it opens
 * the item itself.
 *
 * @param string $from
 * @param string $to
 * @return array[] with people (ids) filled in for scope = people
 */
function ws_events_between($from, $to)
{
    $start = strtotime($from . ' 00:00:00');
    $end = strtotime($to . ' 23:59:59');

    // A day read from a calendar and left out is not a day off anywhere.
    $counted = (function_exists('ws_holiday_skip_ready') && ws_holiday_skip_ready()) ? ' AND skipped = 0' : '';

    $rows = (array) db_items("SELECT * FROM ws_events
        WHERE starts_at <= '" . (int) $end . "' AND ends_at >= '" . (int) $start . "'" . $counted . "
        ORDER BY starts_at");

    if (function_exists('ws_workdays_ready') && ws_workdays_ready()) {
        $first_year = (int) date('Y', $start);
        $last_year = (int) date('Y', $end);

        foreach ((array) db_items("SELECT * FROM ws_events WHERE yearly = 1 AND starts_at <= '" . (int) $end . "'" . $counted) as $row) {
            $base_year = (int) date('Y', (int) $row['starts_at']);

            for ($year = max($first_year - 1, $base_year + 1); $year <= $last_year; $year++) {
                $copy = $row;
                $copy['starts_at'] = ws_event_in_year((int) $row['starts_at'], $year - $base_year);
                $copy['ends_at'] = ws_event_in_year((int) $row['ends_at'], $year - $base_year);

                if (($copy['starts_at'] <= $end) && ($copy['ends_at'] >= $start)) {
                    $rows[] = $copy;
                }
            }
        }

        usort($rows, function ($a, $b) {
            return ((int) $a['starts_at'] - (int) $b['starts_at']) ?: ((int) $a['id'] - (int) $b['id']);
        });
    }

    if (empty($rows)) {
        return array();
    }

    $ids = array();

    foreach ($rows as $row) {
        $ids[] = (int) $row['id'];
    }

    $people = array();

    foreach ((array) db_items("SELECT event_id, user_id FROM ws_event_people WHERE event_id IN (" . implode(',', $ids) . ")") as $row) {
        $people[(int) $row['event_id']][] = (int) $row['user_id'];
    }

    foreach ($rows as $index => $row) {
        $rows[$index]['people'] = $people[(int) $row['id']] ?? array();
    }

    return $rows;
}

/**
 * Is this event about this person?
 *
 * @param array $event
 * @param int   $user_id
 * @param int[] $departments the person's departments
 * @return bool
 */
function ws_event_applies($event, $user_id, $departments)
{
    if ($event['scope'] === 'company') {
        return true;
    }

    if ($event['scope'] === 'department') {
        return in_array((int) $event['department_id'], $departments, true);
    }

    return in_array((int) $user_id, $event['people'], true);
}

/**
 * Minutes of an event that fall on one day, bounded by the working day.
 *
 * @param array  $event
 * @param string $date
 * @param int    $day_minutes
 * @return int
 */
function ws_event_minutes_on($event, $date, $day_minutes)
{
    $day_start = strtotime($date . ' 00:00:00');
    $day_end = strtotime($date . ' 00:00:00 +1 day');

    // An all-day item fills every day it touches, and only those.
    if ((int) $event['all_day'] === 1) {
        return (((int) $event['starts_at'] < $day_end) && ((int) $event['ends_at'] >= $day_start)) ? (int) $day_minutes : 0;
    }

    $from = max($day_start, (int) $event['starts_at']);
    $to = min($day_end, (int) $event['ends_at']);

    return ($to > $from) ? min($day_minutes, (int) round(($to - $from) / 60)) : 0;
}

/**
 * The window a task sits in: [start, due], the due day alone when there is no
 * start, the start day alone when there is no due date. Null when the task
 * has no date at all - it is not on the board.
 *
 * @param array $task start_date, due_date
 * @return array|null [start, end]
 */
function ws_task_window($task)
{
    $start = (string) ($task['start_date'] ?? '');
    $due = (string) ($task['due_date'] ?? '');

    $start = (($start === '') || ($start === '0000-00-00')) ? '' : $start;
    $due = (($due === '') || ($due === '0000-00-00')) ? '' : $due;

    if (($start === '') && ($due === '')) {
        return null;
    }

    if ($due === '') {
        return array($start, $start);
    }

    if (($start === '') || ($start > $due)) {
        return array($due, $due);
    }

    return array($start, $due);
}

/**
 * The plan for a set of people over a range of days.
 *
 * For each person and day: the minutes planned (tasks and timed events), the
 * capacity, whether it is a working day, whether they are on leave, and the
 * tasks and events that make up the day.
 *
 * @param int[]  $user_ids
 * @param string $from
 * @param string $to
 * @param array  $extra   a task that does not exist yet (or a changed one), as
 *                        [task row, int[] people], to see what it would do
 * @param int    $exclude_task_id leave this task out (it is being changed)
 * @param bool   $upcoming also the coming copies of repeating tasks, which
 *                         weigh on the days they will run (the planning board)
 * @return array user id => [days => [date => cell], unscheduled => int]
 */
function ws_plan_grid($user_ids, $from, $to, $extra = null, $exclude_task_id = 0, $upcoming = false)
{
    $user_ids = array_values(array_unique(array_map('intval', (array) $user_ids)));
    $days = ws_days($from, $to);
    $grid = array();

    if (empty($user_ids) || empty($days)) {
        return $grid;
    }

    $events = ws_events_between($from, $to);
    $departments = array();

    foreach ((array) db_items("SELECT user_id, department_id FROM ws_department_members
        WHERE user_id IN (" . implode(',', $user_ids) . ")") as $row) {
        $departments[(int) $row['user_id']][] = (int) $row['department_id'];
    }

    // Open tasks of these people whose window touches the range: a task that
    // started before the range and is due inside it still weighs on the days
    // in view.
    $rows = (array) db_items("SELECT t.*, a.user_id AS assignee_id
        FROM ws_tasks t
        INNER JOIN ws_task_assignees a ON a.task_id = t.id
        WHERE a.user_id IN (" . implode(',', $user_ids) . ")
        AND t.status IN ('todo', 'doing', 'waiting')
        AND t.id <> '" . (int) $exclude_task_id . "'
        AND (t.due_date IS NOT NULL OR t.start_date IS NOT NULL)
        AND COALESCE(t.start_date, t.due_date) <= '" . e($to) . "'
        AND COALESCE(t.due_date, t.start_date) >= '" . e($from) . "'");

    $tasks_by_person = array();

    foreach ($rows as $row) {
        $tasks_by_person[(int) $row['assignee_id']][] = $row;
    }

    // The newest copies of running series: dragging one of them on the board
    // may take the copies after it along (ws_recurrence_moved()).
    $newest = array();

    if ($upcoming && function_exists('ws_recurrence_ready') && ws_recurrence_ready()) {
        $series_ids = array();

        foreach ($rows as $row) {
            if ((int) ($row['recurrence_id'] ?? 0) > 0) {
                $series_ids[(int) $row['recurrence_id']] = true;
            }
        }

        if (!empty($series_ids)) {
            foreach ((array) db_items("SELECT last_task_id FROM ws_task_recurrences
                WHERE status = 'active' AND id IN (" . implode(',', array_keys($series_ids)) . ")") as $series) {
                $newest[(int) $series['last_task_id']] = true;
            }
        }
    }

    // A coming copy runs like the task it repeats: from its start to its due
    // date, with the same estimate. While the newest copy is overdue it waits
    // for that copy, and says so.
    if ($upcoming && function_exists('ws_recurrence_projections')) {
        foreach (ws_recurrence_projections($from, $to) as $item) {
            foreach (array_intersect($item['people'], $user_ids) as $user_id) {
                foreach ($item['copies'] as $copy) {
                    $tasks_by_person[(int) $user_id][] = array(
                        'start_date'  => ($copy['start'] !== $copy['due']) ? $copy['start'] : null,
                        'due_date'    => $copy['due'],
                        'status'      => 'todo',
                        'assignee_id' => (int) $user_id,
                        '_upcoming'   => true,
                        '_repeat'     => ws_recurrence_label($item['series']),
                        '_moved'      => !empty($copy['moved']) ? $copy['date'] : '',
                        '_waits'      => !empty($item['late']) ? ws_task_number($item['task']['id']) : '',
                    ) + $item['task'];
                }
            }
        }
    }

    if (is_array($extra) && isset($extra[0])) {
        foreach ((array) $extra[1] as $user_id) {
            $tasks_by_person[(int) $user_id][] = $extra[0] + array('assignee_id' => (int) $user_id, '_new' => true);
        }
    }

    $unscheduled = array();

    foreach ((array) db_items("SELECT a.user_id, COUNT(*) AS n
        FROM ws_tasks t INNER JOIN ws_task_assignees a ON a.task_id = t.id
        WHERE a.user_id IN (" . implode(',', $user_ids) . ")
        AND t.status IN ('todo', 'doing', 'waiting')
        AND t.due_date IS NULL AND t.start_date IS NULL
        GROUP BY a.user_id") as $row) {
        $unscheduled[(int) $row['user_id']] = (int) $row['n'];
    }

    foreach ($user_ids as $user_id) {
        $capacity = ws_capacity($user_id);
        $person_departments = $departments[$user_id] ?? array();
        $cells = array();

        foreach ($days as $date) {
            $cells[$date] = array(
                'date'     => $date,
                'workday'  => ws_is_workday($date, $capacity['workdays']),
                'holiday'  => false,
                'leave'    => false,
                'capacity' => 0,
                'minutes'  => 0,
                'tasks'    => array(),
                'events'   => array(),
            );
        }

        foreach ($events as $event) {
            if (!ws_event_applies($event, $user_id, $person_departments)) {
                continue;
            }

            foreach ($days as $date) {
                $minutes = ws_event_minutes_on($event, $date, $capacity['day_minutes']);

                if ($minutes <= 0) {
                    continue;
                }

                if ($event['kind'] === 'holiday') {
                    $cells[$date]['holiday'] = true;
                } elseif ($event['kind'] === 'leave') {
                    $cells[$date]['leave'] = true;
                } else {
                    $cells[$date]['minutes'] += $minutes;
                }

                $cells[$date]['events'][] = array(
                    'id'    => (int) $event['id'],
                    'kind'  => (string) $event['kind'],
                    'title' => (string) $event['title'],
                    'time'  => ((int) $event['all_day'] === 1) ? '' : date('H:i', max((int) $event['starts_at'], strtotime($date . ' 00:00:00'))),
                    'minutes' => $minutes,
                );
            }
        }

        foreach ($cells as $date => $cell) {
            $available = $cell['workday'] && !$cell['holiday'] && !$cell['leave'];
            $cells[$date]['capacity'] = $available ? $capacity['day_minutes'] : 0;
        }

        foreach ($tasks_by_person[$user_id] ?? array() as $task) {
            $window = ws_task_window($task);

            if ($window === null) {
                continue;
            }

            // The working days of the task's whole window, not only the ones
            // in view: a two-week task seen through one week is half on it.
            $window_days = array();

            foreach (ws_days($window[0], $window[1]) as $date) {
                $leave = isset($cells[$date]) ? ($cells[$date]['leave'] || $cells[$date]['holiday']) : false;

                if (ws_is_workday($date, $capacity['workdays']) && !$leave) {
                    $window_days[] = $date;
                }
            }

            // Due on a day off: the work still has to be done by then, so it
            // lands on the due day rather than disappearing.
            if (empty($window_days)) {
                $window_days = array($window[1]);
            }

            $total = ((int) $task['estimate_minutes'] > 0) ? (int) $task['estimate_minutes'] : (int) WS_DEFAULT_TASK_MINUTES;
            $share = (int) ceil($total / count($window_days));

            foreach ($window_days as $date) {
                if (!isset($cells[$date])) {
                    continue;
                }

                $cells[$date]['minutes'] += $share;
                $cells[$date]['tasks'][] = array(
                    'id'       => (int) ($task['id'] ?? 0),
                    'title'    => (string) $task['title'],
                    'minutes'  => $share,
                    'status'   => (string) ($task['status'] ?? 'todo'),
                    'priority' => (string) ($task['priority'] ?? 'normal'),
                    'due'      => ($date === $window[1]),
                    'new'      => !empty($task['_new']),
                    'estimated' => ((int) $task['estimate_minutes'] > 0),
                    'due_date' => (string) ($task['due_date'] ?? ''),
                    'progress' => empty($task['_upcoming']) ? ws_task_progress($task) : null,
                    'upcoming' => !empty($task['_upcoming']),
                    'repeat'   => (string) ($task['_repeat'] ?? ''),
                    'moved_from' => (string) ($task['_moved'] ?? ''),
                    'waits_for' => (string) ($task['_waits'] ?? ''),
                    'series_latest' => empty($task['_upcoming']) && isset($newest[(int) ($task['id'] ?? 0)]),
                );
            }
        }

        foreach ($cells as $date => $cell) {
            $cells[$date]['load'] = ($cell['capacity'] > 0)
                ? (int) round(($cell['minutes'] * 100) / $cell['capacity'])
                : (($cell['minutes'] > 0) ? 999 : 0);
        }

        $grid[$user_id] = array('days' => $cells, 'unscheduled' => $unscheduled[$user_id] ?? 0);
    }

    return $grid;
}

/**
 * What handing a piece of work to these people would do to their days.
 *
 * @param array $viewer
 * @param array $task        the task as it would be: title, start_date, due_date,
 *                           estimate_minutes, department_id (id when it exists)
 * @param int[] $user_ids
 * @return array hard (bool), items (warnings), suggestions (lighter people)
 */
function ws_assignment_check($viewer, $task, $user_ids)
{
    $user_ids = array_values(array_unique(array_filter(array_map('intval', (array) $user_ids))));
    $out = array('hard' => false, 'items' => array(), 'suggestions' => array());
    $window = ws_task_window($task);
    $department_id = (int) ($task['department_id'] ?? 0);

    if (empty($user_ids)) {
        return $out;
    }

    $names = ws_people($user_ids);

    if ($department_id > 0) {
        $department = ws_department($department_id);
        $members = ws_department_member_ids($department_id);

        foreach ($user_ids as $user_id) {
            if (!in_array($user_id, $members, true)) {
                $out['items'][] = array(
                    'user_id' => $user_id,
                    'type'    => 'department',
                    'level'   => 'info',
                    'message' => lang(array('string' => '{var:1} is not in the {var:2} department.', 'vars' => array($names[$user_id]['name'] ?? '', $department['name'] ?? ''))),
                );
            }
        }
    }

    if ($window === null) {
        return $out;
    }

    $grid = ws_plan_grid($user_ids, $window[0], $window[1],
        array($task + array('status' => 'todo', 'priority' => 'normal'), $user_ids),
        (int) ($task['id'] ?? 0));

    $today = date('Y-m-d');

    if ($window[1] < $today) {
        $out['items'][] = array(
            'user_id' => 0,
            'type'    => 'past',
            'level'   => 'info',
            'message' => lang('The due date has already passed.'),
        );
    }

    foreach ($user_ids as $user_id) {
        $name = $names[$user_id]['name'] ?? '';
        $over = array();
        $leave = array();
        $free = 0;

        foreach ($grid[$user_id]['days'] ?? array() as $date => $cell) {
            if ($cell['leave'] || $cell['holiday']) {
                $leave[] = ws_day_label($date);
                continue;
            }

            if ($cell['load'] > 100) {
                $over[] = ws_day_label($date) . ' ' . lang(array('string' => '{var:1}%', 'vars' => $cell['load']));
            }

            $free += max(0, $cell['capacity'] - $cell['minutes']);
        }

        if (!empty($leave)) {
            $out['hard'] = true;
            $out['items'][] = array(
                'user_id' => $user_id,
                'type'    => 'leave',
                'level'   => 'hard',
                'message' => lang(array('string' => '{var:1} is away on {var:2}.', 'vars' => array($name, implode(', ', $leave)))),
            );
        }

        if (!empty($over)) {
            $out['items'][] = array(
                'user_id' => $user_id,
                'type'    => 'capacity',
                'level'   => 'warning',
                'message' => lang(array('string' => '{var:1} would be over their day: {var:2}.', 'vars' => array($name, implode(', ', $over)))),
            );
        }
    }

    // Somebody lighter, for the button beside the warning: the people who
    // share the task's department - or the assignees' own departments - with
    // the most free time across the window.
    if (!empty($out['items'])) {
        $pool = ($department_id > 0) ? ws_department_member_ids($department_id) : array();

        if (empty($pool)) {
            foreach ($user_ids as $user_id) {
                foreach (ws_user_department_ids($user_id) as $id) {
                    $pool = array_merge($pool, ws_department_member_ids($id));
                }
            }
        }

        $pool = array_values(array_diff(array_unique(array_intersect($pool, ws_team_ids())), $user_ids));

        if (!empty($pool) && ws_can_assign_to($viewer, $pool)) {
            $candidates = ws_plan_grid($pool, $window[0], $window[1]);
            $ranked = array();

            foreach ($candidates as $user_id => $row) {
                $capacity = 0;
                $minutes = 0;
                $blocked = false;

                foreach ($row['days'] as $cell) {
                    $capacity += $cell['capacity'];
                    $minutes += $cell['minutes'];
                    $blocked = $blocked || $cell['leave'];
                }

                if (!$blocked && ($capacity > 0)) {
                    $ranked[$user_id] = (int) round(($minutes * 100) / $capacity);
                }
            }

            asort($ranked);

            $people = ws_people(array_keys($ranked));

            foreach (array_slice($ranked, 0, 3, true) as $user_id => $load) {
                $out['suggestions'][] = array(
                    'user_id' => (int) $user_id,
                    'name'    => $people[$user_id]['name'] ?? '',
                    'load'    => (int) $load,
                    'label'   => lang(array('string' => '{var:1} ({var:2}% busy)', 'vars' => array($people[$user_id]['name'] ?? '', (int) $load))),
                );
            }
        }
    }

    return $out;
}

/**
 * Everyone who is over their day, or has work while away, in a range - the
 * list a lead reads on Monday morning.
 *
 * @param array  $viewer
 * @param string $from
 * @param string $to
 * @return array[]
 */
function ws_conflicts($viewer, $from, $to)
{
    $scope = ws_board_scope($viewer);
    $user_ids = ($scope === true) ? ws_team_ids() : $scope;
    $grid = ws_plan_grid($user_ids, $from, $to);
    $people = ws_people($user_ids);
    $out = array();

    foreach ($grid as $user_id => $row) {
        foreach ($row['days'] as $date => $cell) {
            if (($cell['leave'] || $cell['holiday']) && !empty($cell['tasks'])) {
                $out[] = array(
                    'user_id' => $user_id,
                    'name'    => $people[$user_id]['name'] ?? '',
                    'date'    => $date,
                    'day'     => ws_day_label($date),
                    'type'    => 'leave',
                    'load'    => $cell['load'],
                    'tasks'   => $cell['tasks'],
                );
            } elseif ($cell['load'] > 100) {
                $out[] = array(
                    'user_id' => $user_id,
                    'name'    => $people[$user_id]['name'] ?? '',
                    'date'    => $date,
                    'day'     => ws_day_label($date),
                    'type'    => 'capacity',
                    'load'    => $cell['load'],
                    'tasks'   => $cell['tasks'],
                );
            }
        }
    }

    usort($out, function ($a, $b) {
        return strcmp($a['date'] . $a['name'], $b['date'] . $b['name']);
    });

    return $out;
}

/**
 * Writes a plan item: a meeting, a visit, leave or a holiday.
 *
 * @param array $viewer
 * @param array $data id, kind, scope, department_id, people, title, note,
 *                    start (Y-m-d), end (Y-m-d), start_time, end_time, all_day
 * @return array ok, error, field, event_id
 */
function ws_event_save($viewer, $data)
{
    if (!$viewer['member']) {
        return array('ok' => false, 'error' => lang('Access denied.'), 'field' => '', 'event_id' => 0);
    }

    $kinds = array('meeting', 'visit', 'leave', 'holiday', 'other');
    $kind = in_array(($data['kind'] ?? ''), $kinds, true) ? $data['kind'] : 'meeting';
    $scope = in_array(($data['scope'] ?? ''), array('people', 'department', 'company'), true) ? $data['scope'] : 'people';

    // Holidays and company-wide items are the settings holder's to write; a
    // department's are its lead's too.
    if (($scope === 'company') && !$viewer['settings']) {
        return array('ok' => false, 'error' => lang('Only somebody who manages the workspace settings can plan for the whole company.'), 'field' => 'scope', 'event_id' => 0);
    }

    $department_id = (int) ($data['department_id'] ?? 0);

    if (($scope === 'department') && !$viewer['settings'] && !in_array($department_id, ws_lead_departments($viewer['id']), true)) {
        return array('ok' => false, 'error' => lang('Only the department\'s lead can plan for the whole department.'), 'field' => 'scope', 'event_id' => 0);
    }

    $people = array();

    if ($scope === 'people') {
        foreach ((array) ($data['people'] ?? array()) as $user_id) {
            if (ws_is_team_member($user_id)) {
                $people[(int) $user_id] = (int) $user_id;
            }
        }

        if (empty($people)) {
            $people[(int) $viewer['id']] = (int) $viewer['id'];
        }

        if (!ws_can_assign_to($viewer, array_values($people))) {
            return array('ok' => false, 'error' => lang('You may only plan for yourself or for the people in the departments you lead.'), 'field' => 'people', 'event_id' => 0);
        }
    }

    $title = mb_substr(trim((string) ($data['title'] ?? '')), 0, 255);

    if ($title === '') {
        $labels = ws_event_kinds();
        $title = $labels[$kind];
    }

    $start_day = ws_date_or_null($data['start'] ?? '');
    $end_day = ws_date_or_null(($data['end'] ?? '') !== '' ? $data['end'] : ($data['start'] ?? ''));

    if (!$start_day || !$end_day) {
        return array('ok' => false, 'error' => lang('Write the date as day.month.year.'), 'field' => 'start', 'event_id' => 0);
    }

    $all_day = !empty($data['all_day']) || in_array($kind, array('leave', 'holiday'), true);

    if ($all_day) {
        $starts_at = strtotime($start_day . ' 00:00:00');
        $ends_at = strtotime($end_day . ' 23:59:59');
    } else {
        $start_time = preg_match('/^[0-9]{1,2}:[0-9]{2}$/', (string) ($data['start_time'] ?? '')) ? $data['start_time'] : '09:00';
        $end_time = preg_match('/^[0-9]{1,2}:[0-9]{2}$/', (string) ($data['end_time'] ?? '')) ? $data['end_time'] : '10:00';
        $starts_at = strtotime($start_day . ' ' . $start_time . ':00');
        $ends_at = strtotime($end_day . ' ' . $end_time . ':00');
    }

    if (($starts_at === false) || ($ends_at === false) || ($ends_at <= $starts_at)) {
        return array('ok' => false, 'error' => lang('The end has to come after the start.'), 'field' => 'end', 'event_id' => 0);
    }

    $event_id = (int) ($data['id'] ?? 0);

    if ($event_id > 0) {
        $current = db_item("SELECT * FROM ws_events WHERE id = '" . $event_id . "'");

        if (!is_array($current) || !ws_can_edit_event($viewer, $current)) {
            return array('ok' => false, 'error' => lang('You cannot change this item.'), 'field' => '', 'event_id' => 0);
        }

        db("UPDATE ws_events SET
                kind = '" . $kind . "', scope = '" . $scope . "', department_id = '" . (($scope === 'department') ? $department_id : 0) . "',
                title = '" . e($title) . "', note = '" . e(mb_substr((string) ($data['note'] ?? ''), 0, 500)) . "',
                starts_at = '" . (int) $starts_at . "', ends_at = '" . (int) $ends_at . "', all_day = '" . ($all_day ? 1 : 0) . "'
            WHERE id = '" . $event_id . "'");

        db("DELETE FROM ws_event_people WHERE event_id = '" . $event_id . "'");
    } else {
        db("INSERT INTO ws_events (kind, scope, department_id, title, note, starts_at, ends_at, all_day, created_by, created_at)
            VALUES ('" . $kind . "', '" . $scope . "', '" . (($scope === 'department') ? $department_id : 0) . "',
                '" . e($title) . "', '" . e(mb_substr((string) ($data['note'] ?? ''), 0, 500)) . "',
                '" . (int) $starts_at . "', '" . (int) $ends_at . "', '" . ($all_day ? 1 : 0) . "',
                '" . (int) $viewer['id'] . "', '" . time() . "')");

        $event_id = (int) mysqli_insert_id(db::$con);
    }

    foreach ($people as $user_id) {
        db("INSERT IGNORE INTO ws_event_people (event_id, user_id) VALUES ('" . $event_id . "', '" . (int) $user_id . "')");
    }

    return array('ok' => true, 'error' => '', 'field' => '', 'event_id' => $event_id);
}

/**
 * May this person change or remove a plan item?
 *
 * @param array $viewer
 * @param array $event
 * @return bool
 */
function ws_can_edit_event($viewer, $event)
{
    if ((int) $event['created_by'] === (int) $viewer['id']) {
        return true;
    }

    if ($viewer['settings']) {
        return true;
    }

    return ($event['scope'] === 'department') && in_array((int) $event['department_id'], ws_lead_departments($viewer['id']), true);
}

/**
 * Removes a plan item.
 *
 * @param array $viewer
 * @param int   $event_id
 * @return array ok, error
 */
function ws_event_delete($viewer, $event_id)
{
    $event = db_item("SELECT * FROM ws_events WHERE id = '" . (int) $event_id . "'");

    if (!is_array($event) || !ws_can_edit_event($viewer, $event)) {
        return array('ok' => false, 'error' => lang('You cannot change this item.'));
    }

    db("DELETE FROM ws_event_people WHERE event_id = '" . (int) $event_id . "'");
    db("DELETE FROM ws_events WHERE id = '" . (int) $event_id . "'");

    return array('ok' => true, 'error' => '');
}

/**
 * @return array kind => label
 */
function ws_event_kinds()
{
    return array(
        'meeting' => lang('Meeting'),
        'visit'   => lang('Customer visit'),
        'leave'   => lang('Leave'),
        'holiday' => lang('Holiday'),
        'other'   => lang('Other'),
    );
}

/**
 * The board: people as rows, days as columns, for the people this viewer may
 * see and, when asked, one department.
 *
 * @param array  $viewer
 * @param string $from
 * @param int    $days
 * @param int    $department_id
 * @param bool   $only_me
 * @param int    $person_id one person's row (someone the viewer may see)
 * @return array days, people, rows
 */
function ws_board($viewer, $from, $days = 7, $department_id = 0, $only_me = false, $person_id = 0)
{
    $days = max(1, min(31, (int) $days));
    $from = ws_date_or_null($from) ?: date('Y-m-d', strtotime('monday this week'));
    $to = date('Y-m-d', strtotime($from . ' 12:00:00 +' . ($days - 1) . ' days'));

    $scope = ws_board_scope($viewer);
    $user_ids = ($scope === true) ? ws_team_ids() : $scope;

    if ($only_me) {
        $user_ids = array((int) $viewer['id']);
    } elseif (((int) $person_id > 0) && in_array((int) $person_id, $user_ids, true)) {
        $user_ids = array((int) $person_id);
    } elseif ((int) $department_id > 0) {
        $user_ids = array_values(array_intersect($user_ids, ws_department_member_ids($department_id)));
    }

    $grid = ws_plan_grid($user_ids, $from, $to, null, 0, true);
    $people = ws_people($user_ids);
    $day_list = array();

    foreach (ws_days($from, $to) as $date) {
        $day_list[] = array(
            'date'    => $date,
            'label'   => ws_day_label($date),
            'today'   => ($date === date('Y-m-d')),
            'weekend' => ((int) date('N', strtotime($date . ' 12:00:00')) >= 6),
        );
    }

    $rows = array();

    foreach ($user_ids as $user_id) {
        if (!isset($grid[$user_id])) {
            continue;
        }

        $cells = array();

        foreach ($grid[$user_id]['days'] as $cell) {
            $cells[] = $cell;
        }

        $rows[] = array(
            'person'      => $people[$user_id] ?? array('id' => $user_id, 'name' => ''),
            'capacity'    => ws_capacity($user_id),
            'unscheduled' => $grid[$user_id]['unscheduled'],
            'cells'       => $cells,
            'departments' => ws_user_department_ids($user_id),
        );
    }

    return array(
        'from'  => $from,
        'to'    => $to,
        'days'  => $day_list,
        'rows'  => $rows,
        'scope' => ($scope === true) ? 'all' : 'limited',
    );
}
