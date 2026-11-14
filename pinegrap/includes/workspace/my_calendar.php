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
    }

    // A plan with a roadmap carries how far it has come.
    if (function_exists('ws_personal_plans_progress_place')) {
        $days = ws_personal_plans_progress_place($days, $viewer);
    }

    return array(
        'view'     => $range['view'],
        'label'    => ws_my_calendar_label($range),
        'anchor'   => $range['anchor'],
        'from'     => $from,
        'to'       => $to,
        'prev'     => $range['prev'],
        'next'     => $range['next'],
        'today'    => $today,
        'days'     => array_values($days),
        'spans'    => $spans,
        'weekdays' => array_values(ws_weekday_short_names()),
        'layers'   => array_values(array_filter(array_keys($layers), 'strlen')),
        'plans'    => function_exists('ws_personal_plans_ready') && ws_personal_plans_ready(),
    );
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
        'own_calendar'  => lang('Your own calendar'),
    );
}
