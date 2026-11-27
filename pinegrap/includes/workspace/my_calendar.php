<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - My calendar (workspace_my_calendar.php): the Work Calendar
 * opened on the reader alone. Their own tasks, the plan items they are in,
 * their confirmed bookings and their own iCal layer, by month, by week or as
 * a list of days. Nothing of anybody else is read: no person filter is
 * taken from the request, the reader is always the person.
 *
 * The Work Calendar (calendar.php), the planning board and capacity are not
 * changed by this screen; it reuses their readers and day placement.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/** How many days the list view shows from its first day. */
const WS_MY_CALENDAR_LIST_DAYS = 30;

/**
 * The shortest stretch, in minutes, an item takes on the week's hour grid:
 * a task with a due time is a mark this long ending at that time, and a
 * shorter event is drawn (and shares the width) as if it lasted this long.
 */
const WS_MY_CALENDAR_HOUR_SHORTEST = 30;

/**
 * The view a request names, or month.
 *
 * @param string $view
 * @return string month | week | list
 */
function ws_my_calendar_view($view)
{
    return (is_string($view) && in_array($view, array('month', 'week', 'list'), true)) ? $view : 'month';
}

/**
 * The days a view covers around a day it is anchored on, and where the
 * previous and next buttons go. A month is drawn in whole weeks, Monday
 * first, like the Work Calendar; a week runs Monday to Sunday; the list runs
 * WS_MY_CALENDAR_LIST_DAYS days from the anchor. Pure.
 *
 * @param string $view   ws_my_calendar_view()
 * @param string $anchor Y-m-d ('' or a bad date: $today)
 * @param string $today  Y-m-d
 * @return array view, anchor, from, to (Y-m-d, both included), prev, next
 *               (anchors), month (Y-m, the month view's month)
 */
function ws_my_calendar_range($view, $anchor, $today)
{
    $view = ws_my_calendar_view($view);
    $anchor = is_string($anchor) ? $anchor : '';

    if (!preg_match('/^([0-9]{4})-([0-9]{2})-([0-9]{2})$/', $anchor, $match) || !checkdate((int) $match[2], (int) $match[3], (int) $match[1])) {
        $anchor = $today;
    }

    $noon = strtotime($anchor . ' 12:00:00');
    $shift = function ($date, $modify) {
        return date('Y-m-d', strtotime($date . ' 12:00:00 ' . $modify));
    };
    $monday = function ($date) use ($shift) {
        return $shift($date, '-' . ((int) date('N', strtotime($date . ' 12:00:00')) - 1) . ' days');
    };

    if ($view === 'week') {
        $from = $monday($anchor);
        $to = $shift($from, '+6 days');

        return array('view' => $view, 'anchor' => $anchor, 'from' => $from, 'to' => $to,
            'prev' => $shift($from, '-7 days'), 'next' => $shift($from, '+7 days'), 'month' => substr($anchor, 0, 7));
    }

    if ($view === 'list') {
        $to = $shift($anchor, '+' . (WS_MY_CALENDAR_LIST_DAYS - 1) . ' days');

        return array('view' => $view, 'anchor' => $anchor, 'from' => $anchor, 'to' => $to,
            'prev' => $shift($anchor, '-' . WS_MY_CALENDAR_LIST_DAYS . ' days'), 'next' => $shift($anchor, '+' . WS_MY_CALENDAR_LIST_DAYS . ' days'), 'month' => substr($anchor, 0, 7));
    }

    $first = date('Y-m-01', $noon);
    $last = date('Y-m-t', $noon);
    $from = $monday($first);
    $to = $shift($last, '+' . (7 - (int) date('N', strtotime($last . ' 12:00:00'))) . ' days');

    return array('view' => $view, 'anchor' => $first, 'from' => $from, 'to' => $to,
        'prev' => $shift($first, '-1 month'), 'next' => $shift($first, '+1 month'), 'month' => substr($first, 0, 7));
}

/**
 * The days a task covers on My calendar, the way the Work Calendar lays it:
 * its planned window (ws_task_window(), else its creation day); an open task
 * whose window is behind it runs on to today; a closed one ends on the day
 * it was closed. Pure.
 *
 * @param array  $row   ws_tasks row
 * @param string $today Y-m-d
 * @return string[] first and last day (Y-m-d)
 */
function ws_my_calendar_task_days($row, $today)
{
    $created = date('Y-m-d', (int) ($row['created_at'] ?? 0));
    $window = ws_task_window($row);
    $first = $window ? $window[0] : $created;
    $last = $window ? $window[1] : $first;

    if (ws_task_is_open((string) ($row['status'] ?? ''))) {
        if (($last < $today) && ($first <= $today)) {
            $last = $today;
        }
    } elseif ((int) ($row['completed_at'] ?? 0) > 0) {
        $last = max($first, date('Y-m-d', (int) $row['completed_at']));
    }

    return array($first, $last);
}

/**
 * The calendar item a task is drawn as. Pure.
 *
 * @param array  $row    ws_tasks row
 * @param int[]  $people its assignees
 * @param string $today  Y-m-d
 * @return array
 */
function ws_my_calendar_task_item($row, $people, $today)
{
    $due = (string) ($row['due_date'] ?? '');
    $due = ($due === '0000-00-00') ? '' : $due;
    $open = ws_task_is_open((string) $row['status']);

    return array(
        'id'       => (int) $row['id'],
        'number'   => ws_task_number((int) $row['id']),
        'title'    => (string) $row['title'],
        'status'   => (string) $row['status'],
        'priority' => (string) $row['priority'],
        'open'     => $open,
        'overdue'  => $open && ($due !== '') && ($due < $today),
        'due_date' => $due,
        'time'     => substr((string) ($row['due_time'] ?? ''), 0, 5),
        'people'   => array_values(array_map('intval', $people)),
    );
}

/**
 * Everything My calendar draws for the reader.
 *
 * @param array  $viewer ws_viewer()
 * @param string $view   month | week | list
 * @param string $anchor Y-m-d, a day of the range ('' for today)
 * @return array view, label, from, to, prev, next, today, days (in order,
 *               each date, day, label, today, weekend, in_range, tasks,
 *               events), spans (tasks over several days), weekdays, layers
 */
function ws_my_calendar($viewer, $view, $anchor = '')
{
    $me = (int) $viewer['id'];
    $today = date('Y-m-d');
    $range = ws_my_calendar_range($view, $anchor, $today);
    $from = $range['from'];
    $to = $range['to'];
    $from_ts = strtotime($from . ' 00:00:00');
    $to_ts = strtotime($to . ' 23:59:59');

    $days = array();

    foreach (ws_days($from, $to) as $date) {
        $days[$date] = array(
            'date'     => $date,
            'day'      => (int) date('j', strtotime($date . ' 12:00:00')),
            'label'    => ws_day_label($date),
            'today'    => ($date === $today),
            'weekend'  => ((int) date('N', strtotime($date . ' 12:00:00')) >= 6),
            'in_range' => ($range['view'] !== 'month') || (substr($date, 0, 7) === $range['month']),
            'tasks'    => array(),
            'events'   => array(),
        );
    }

    // ── The reader's own tasks: those they are assigned to ──
    //
    // Read wide (anything that may touch the range), then laid exactly by
    // ws_my_calendar_task_days().

    $rows = (array) db_items("SELECT t.* FROM ws_tasks t
        INNER JOIN ws_task_assignees a ON a.task_id = t.id AND a.user_id = '" . $me . "'
        WHERE (COALESCE(t.start_date, t.due_date) <= '" . e($to) . "' OR (t.start_date IS NULL AND t.due_date IS NULL AND t.created_at <= '" . (int) $to_ts . "'))
        AND (t.status IN ('todo', 'doing', 'waiting') OR t.completed_at >= '" . (int) $from_ts . "'
            OR t.due_date >= '" . e($from) . "' OR t.start_date >= '" . e($from) . "' OR t.created_at >= '" . (int) $from_ts . "')
        ORDER BY FIELD(t.priority, 'urgent', 'high', 'normal', 'low'), t.id
        LIMIT 1000");

    $ids = array();

    foreach ($rows as $row) {
        $ids[] = (int) $row['id'];
    }

    $assignees = ws_task_assignees_map($ids);
    $spans = array();

    foreach ($rows as $row) {
        $people = $assignees[(int) $row['id']] ?? array();

        if (!ws_can_see_task($viewer, $row, $people)) {
            continue;
        }

        list($first, $last) = ws_my_calendar_task_days($row, $today);

        if (($first > $to) || ($last < $from)) {
            continue;
        }

        $item = ws_my_calendar_task_item($row, $people, $today);
        // One the reader may change, with a due day: moved by a drag or
        // "Move to…" (ws_task_move, as the Planning Board moves it).
        $item['can_move'] = ($item['due_date'] !== '') && ws_can_edit_task($viewer, $row, $people);

        if ($first === $last) {
            $days[$first]['tasks'][] = $item;
        } else {
            $spans[] = $item + array('span_start' => $first, 'span_end' => $last);
        }
    }

    // ── Coming copies of the reader's repeating tasks ──

    foreach ((function_exists('ws_recurrence_projections') ? ws_recurrence_projections($from, $to) : array()) as $projection) {
        $row = $projection['task'];
        $people = array_map('intval', (array) $projection['people']);

        if (!in_array($me, $people, true) || !ws_can_see_task($viewer, $row, $people)) {
            continue;
        }

        foreach ($projection['copies'] as $copy) {
            if (!isset($days[$copy['due']])) {
                continue;
            }

            $days[$copy['due']]['tasks'][] = array(
                'id'       => (int) $row['id'],
                'number'   => ws_task_number((int) $row['id']),
                'title'    => (string) $row['title'],
                'status'   => 'todo',
                'priority' => (string) $row['priority'],
                'open'     => true,
                'overdue'  => false,
                'due_date' => $copy['due'],
                'time'     => '',
                'people'   => $people,
                'upcoming' => true,
                'repeat'   => ws_recurrence_label($projection['series']),
            );
        }
    }

    // ── The plan items that apply to the reader ──

    $departments = ws_user_department_ids($me);

    foreach (ws_events_between($from, $to) as $event) {
        if (ws_event_applies($event, $me, array_map('intval', (array) $departments))) {
            $days = ws_calendar_plan_place($days, $event);
        }
    }

    // ── Their confirmed bookings ──

    require_once(PG_FUNCTIONS_DIR . '/includes/fn/booking_agenda.php');
    $days = ws_booking_calendar_place($days, ws_booking_calendar_events(pg_booking_calendar_items($me, $from_ts, $to_ts + 1)));

    // ── Their own iCal calendars, read-only ──

    $layers = array();

    if (function_exists('ws_personal_ical_layer')) {
        $layer = array_filter(ws_personal_ical_layer($me), function ($event) use ($from_ts, $to_ts) {
            return ((int) $event['starts_at'] <= $to_ts) && ((int) $event['ends_at'] >= $from_ts);
        });

        $days = ws_personal_ical_place($days, $layer);

        foreach ($layer as $event) {
            $layers[(string) ($event['layer'] ?? '')] = true;
        }
    }

    // ── Their own plans, through the plans' gate ──

    if (function_exists('ws_personal_plans_between')) {
        $plans = ws_personal_plans_between($me, $from_ts, $to_ts + 1);
        $names = array();

        foreach (ws_people(array_unique(array_map('intval', array_column($plans, 'user_id')))) as $id => $brief) {
            $names[(int) $id] = (string) $brief['name'];
        }

        $days = ws_personal_plans_place($days, $plans, $me, $names);
    }

    foreach ($days as $date => $day) {
        $days[$date]['events'] = ws_my_calendar_sort_events($day['events']);

        // The week's hour grid: where each item with a time stands.
        if ($range['view'] === 'week') {
            $days[$date]['hours'] = ws_my_calendar_day_hours($days[$date]);
        }
    }

    // A plan with a roadmap carries how far it has come.
    if (function_exists('ws_personal_plans_progress_place')) {
        $days = ws_personal_plans_progress_place($days, $viewer);
    }

    return array(
        'view'     => $range['view'],
        'label'    => ws_my_calendar_label($range),
        // The same range in short words, for the bar on a phone.
        'label_short' => ws_my_calendar_label_short($range),
        'anchor'   => $range['anchor'],
        'from'     => $from,
        'to'       => $to,
        'prev'     => $range['prev'],
        'next'     => $range['next'],
        'today'    => $today,
        // The minute of the day now, for the week's line of now.
        'now'      => ws_my_calendar_minutes(date('H:i')),
        'days'     => array_values($days),
        'spans'    => $spans,
        'weekdays' => array_values(ws_weekday_short_names()),
        'layers'   => array_values(array_filter(array_keys($layers), 'strlen')),
        'plans'    => function_exists('ws_personal_plans_ready') && ws_personal_plans_ready(),
    );
}

/**
 * The minutes from midnight of an "H:i" time ("24:00" is 1440), or -1 for
 * no time. Pure.
 *
 * @param string $time
 * @return int
 */
function ws_my_calendar_minutes($time)
{
    if (!is_string($time) || !preg_match('/^([01][0-9]|2[0-4]):([0-5][0-9])$/', $time, $match)) {
        return -1;
    }

    $minutes = (int) $match[1] * 60 + (int) $match[2];

    return ($minutes > 1440) ? -1 : $minutes;
}

/**
 * Where a day's items with a time stand on the week's hour grid. An event
 * stands from its time to its end on that day; a task due at a time is a
 * mark of WS_MY_CALENDAR_HOUR_SHORTEST minutes ending there. What has no
 * hour (whole-day events, tasks without a due time, coming copies of a
 * repeating task, a task over several days) and an event that fills the
 * whole day (the middle of a long one) are left to the strip above the
 * grid. Pure.
 *
 * @param array $day a day of ws_my_calendar(): date, events, tasks
 * @return array[] type (event | task), index (in the day's events or
 *                 tasks), start, end (minutes), column, columns
 */
function ws_my_calendar_day_hours($day)
{
    $items = array();

    foreach ((array) ($day['events'] ?? array()) as $index => $event) {
        $start = ws_my_calendar_minutes((string) ($event['time'] ?? ''));
        $end = ws_my_calendar_minutes((string) ($event['until'] ?? ''));

        if ($start < 0 || ($start === 0 && $end === 1440)) {
            continue;
        }

        $items[] = array('type' => 'event', 'index' => $index, 'start' => $start, 'end' => max($start, $end));
    }

    foreach ((array) ($day['tasks'] ?? array()) as $index => $task) {
        $due = ws_my_calendar_minutes((string) ($task['time'] ?? ''));

        if ($due < 0 || !empty($task['upcoming']) || (string) ($task['due_date'] ?? '') !== (string) ($day['date'] ?? '')) {
            continue;
        }

        $start = max(0, $due - WS_MY_CALENDAR_HOUR_SHORTEST);
        $items[] = array('type' => 'task', 'index' => $index, 'start' => $start, 'end' => $start + WS_MY_CALENDAR_HOUR_SHORTEST);
    }

    foreach (ws_my_calendar_hours_place($items) as $key => $place) {
        $items[$key] = $place + $items[$key];
    }

    return $items;
}

/**
 * Lays items with a start and an end (minutes) side by side where they
 * overlap: items that overlap, directly or through others, form a group
 * sharing the day's width in as many columns as the group needs at once;
 * each item takes the first column free at its start. An item shorter than
 * WS_MY_CALENDAR_HOUR_SHORTEST is laid (and drawn) as that long, never past
 * midnight. Pure.
 *
 * @param array[] $items each start, end
 * @return array[] in the order given: start, end (as drawn), column (from
 *                 0), columns
 */
function ws_my_calendar_hours_place($items)
{
    $out = array();

    foreach ($items as $key => $item) {
        $start = max(0, min(1440, (int) $item['start']));
        $end = min(1440, max((int) $item['end'], $start + WS_MY_CALENDAR_HOUR_SHORTEST));
        $start = min($start, $end - WS_MY_CALENDAR_HOUR_SHORTEST);
        $out[$key] = array('start' => $start, 'end' => $end, 'column' => 0, 'columns' => 1);
    }

    $order = array_keys($out);

    // By start; the longer first, then as given.
    usort($order, function ($a, $b) use ($out, $order) {
        return ($out[$a]['start'] - $out[$b]['start'])
            ?: ($out[$b]['end'] - $out[$a]['end'])
            ?: (array_search($a, $order, true) - array_search($b, $order, true));
    });

    $group = array();
    $columns = array();
    $group_end = -1;

    $close = function () use (&$out, &$group, &$columns) {
        foreach ($group as $key) {
            $out[$key]['columns'] = count($columns);
        }

        $group = array();
        $columns = array();
    };

    foreach ($order as $key) {
        if ($out[$key]['start'] >= $group_end) {
            $close();
        }

        $column = 0;

        while (isset($columns[$column]) && $columns[$column] > $out[$key]['start']) {
            $column++;
        }

        $columns[$column] = $out[$key]['end'];
        $out[$key]['column'] = $column;
        $group[] = $key;
        $group_end = max($group_end, $out[$key]['end']);
    }

    $close();

    return $out;
}

/**
 * A day's events in the order of the day: whole-day ones first, then by
 * time. Pure.
 *
 * @param array[] $events
 * @return array[]
 */
function ws_my_calendar_sort_events($events)
{
    $order = array_keys($events);

    usort($order, function ($a, $b) use ($events) {
        $ta = (string) ($events[$a]['time'] ?? '');
        $tb = (string) ($events[$b]['time'] ?? '');

        return strcmp($ta, $tb) ?: ($a - $b);
    });

    return array_map(function ($key) use ($events) {
        return $events[$key];
    }, $order);
}

/**
 * The range's title: "November 2026" for a month, "9 – 15 November 2026"
 * for a week or a list.
 *
 * @param array $range ws_my_calendar_range()
 * @return string
 */
function ws_my_calendar_label($range)
{
    if ($range['view'] === 'month') {
        return ws_calendar_month_label($range['month']);
    }

    $from = strtotime($range['from'] . ' 12:00:00');
    $to = strtotime($range['to'] . ' 12:00:00');
    $to_label = date('j', $to) . ' ' . ws_calendar_month_label(date('Y-m', $to));

    if (date('Y-m', $from) === date('Y-m', $to)) {
        return date('j', $from) . ' – ' . $to_label;
    }

    return date('j', $from) . ' ' . ws_calendar_month_label(date('Y-m', $from)) . ' – ' . $to_label;
}

/**
 * The range's title short enough for a phone's bar: "Nov 2026" for a month,
 * "9 – 15 Nov" or "30 Nov – 6 Dec" for a week (no year), "14 Nov" for the
 * list, the day it runs on from. Pure.
 *
 * @param array $range ws_my_calendar_range()
 * @return string
 */
function ws_my_calendar_label_short($range)
{
    $names = array(
        1 => lang('Jan'), 2 => lang('Feb'), 3 => lang('Mar'), 4 => lang('Apr'),
        5 => lang('May'), 6 => lang('Jun'), 7 => lang('Jul'), 8 => lang('Aug'),
        9 => lang('Sep'), 10 => lang('Oct'), 11 => lang('Nov'), 12 => lang('Dec'),
    );

    if ($range['view'] === 'month') {
        $time = strtotime($range['month'] . '-01 12:00:00');

        return $names[(int) date('n', $time)] . ' ' . date('Y', $time);
    }

    $from = strtotime($range['from'] . ' 12:00:00');

    // The list runs on from its day: that day alone names it.
    if ($range['view'] === 'list') {
        return date('j', $from) . ' ' . $names[(int) date('n', $from)];
    }

    $to = strtotime($range['to'] . ' 12:00:00');
    $to_label = date('j', $to) . ' ' . $names[(int) date('n', $to)];

    if (date('Y-m', $from) === date('Y-m', $to)) {
        return date('j', $from) . ' – ' . $to_label;
    }

    return date('j', $from) . ' ' . $names[(int) date('n', $from)] . ' – ' . $to_label;
}

/**
 * The texts the screen's script reads (assets/js/workspace_my_calendar.js)
 * beside the Workspace's own (all_day, tasks, plan_items, more_count, today).
 *
 * @return array
 */
function ws_my_calendar_js_strings()
{
    return array(
        'month'         => lang('Month'),
        'week'          => lang('Week'),
        'list'          => lang('List'),
        'view'          => lang('View'),
        'previous'      => lang('Previous'),
        'next'          => lang('Next'),
        'work_calendar' => lang('Work Calendar'),
        'nothing'       => lang('Nothing on your calendar in these days.'),
        'nothing_day'   => lang('Nothing on your calendar this day.'),
        'own_calendar'  => lang('Your own calendar'),
        // A plan or task moved by a drag or "Move to…" (workspace_my_calendar_move.js).
        'open'          => lang('Open it'),
        'move_to'       => lang('Move to...'),
        'move_to_q'     => lang('Move to which day?'),
        'move_day'      => lang('Day'),
        'move'          => lang('Move'),
        'undo'          => lang('Undo'),
        'move_undone'   => lang('The move is undone.'),
    );
}
