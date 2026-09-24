<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - the month calendar: the work falling due on each day, the plan
 * items, the channels opened and how much was talked about, with the month's
 * figures beside it (who carries the most work, who the least, what is late).
 *
 * The planning board answers "who has what this week"; this answers "what did
 * this month hold". Everything is filtered for the reader: a task they may not
 * see is not counted, a channel they may not read neither.
 *
 * The coming copies of repeating tasks are drawn on their due days as well,
 * marked as coming; they are not counted in the month's figures, which are
 * about work that exists.
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
 * The month a request names ("2026-09"), or this month.
 *
 * @param string $month
 * @return string Y-m
 */
function ws_calendar_month($month)
{
    $month = (string) $month;

    if (preg_match('/^([0-9]{4})-([0-9]{2})$/', $month, $match) && checkdate((int) $match[2], 1, (int) $match[1])) {
        return $month;
    }

    return date('Y-m');
}

/**
 * The month's name as a person reads it: "Eylül 2026".
 *
 * @param string $month Y-m
 * @return string
 */
function ws_calendar_month_label($month)
{
    $names = array(
        1 => lang('January'), 2 => lang('February'), 3 => lang('March'), 4 => lang('April'),
        5 => lang('May'), 6 => lang('June'), 7 => lang('July'), 8 => lang('August'),
        9 => lang('September'), 10 => lang('October'), 11 => lang('November'), 12 => lang('December'),
    );

    $time = strtotime($month . '-01 12:00:00');

    return $names[(int) date('n', $time)] . ' ' . date('Y', $time);
}

/**
 * Everything the month screen draws.
 *
 * @param array  $viewer
 * @param string $month         Y-m
 * @param int    $person_id     only this person's work (0: everyone in view)
 * @param int    $department_id only this department's (0: all)
 * @return array
 */
function ws_calendar($viewer, $month, $person_id = 0, $department_id = 0)
{
    $month = ws_calendar_month($month);
    $first = $month . '-01';
    $last = date('Y-m-t', strtotime($first . ' 12:00:00'));

    // Whole weeks, Monday first, so the grid has no ragged edges.
    $from = date('Y-m-d', strtotime($first . ' 12:00:00 -' . ((int) date('N', strtotime($first . ' 12:00:00')) - 1) . ' days'));
    $to = date('Y-m-d', strtotime($last . ' 12:00:00 +' . (7 - (int) date('N', strtotime($last . ' 12:00:00'))) . ' days'));
    $from_ts = strtotime($from . ' 00:00:00');
    $to_ts = strtotime($to . ' 23:59:59');
    $month_from_ts = strtotime($first . ' 00:00:00');
    $month_to_ts = strtotime($last . ' 23:59:59');
    $today = date('Y-m-d');

    // The people whose figures this reader may see, narrowed by the filters.
    $scope = ws_board_scope($viewer);
    $people_ids = ($scope === true) ? ws_team_ids() : $scope;

    if ((int) $department_id > 0) {
        $people_ids = array_values(array_intersect($people_ids, ws_department_member_ids($department_id)));
    }

    $person_id = (int) $person_id;

    if (($person_id > 0) && !in_array($person_id, $people_ids, true) && ($person_id !== (int) $viewer['id'])) {
        $person_id = 0;
    }

    $days = array();

    foreach (ws_days($from, $to) as $date) {
        $days[$date] = array(
            'date'     => $date,
            'day'      => (int) date('j', strtotime($date . ' 12:00:00')),
            'in_month' => (substr($date, 0, 7) === $month),
            'today'    => ($date === $today),
            'weekend'  => ((int) date('N', strtotime($date . ' 12:00:00')) >= 6),
            'label'    => ws_day_label($date),
            'tasks'    => array(),
            'events'   => array(),
            'channels' => array(),
            'messages' => 0,
            'talk'     => array(),
        );
    }

    // ── Tasks: on their due day, or their start day when they have none ──

    $rows = (array) db_items("SELECT * FROM ws_tasks
        WHERE (due_date BETWEEN '" . e($from) . "' AND '" . e($to) . "')
        OR (due_date IS NULL AND start_date BETWEEN '" . e($from) . "' AND '" . e($to) . "')
        OR (due_date IS NULL AND start_date IS NULL AND created_at BETWEEN '" . (int) $from_ts . "' AND '" . (int) $to_ts . "')
        ORDER BY FIELD(priority, 'urgent', 'high', 'normal', 'low'), id
        LIMIT 3000");

    $ids = array();

    foreach ($rows as $row) {
        $ids[] = (int) $row['id'];
    }

    $assignees = ws_task_assignees_map($ids);
    $department_people = ((int) $department_id > 0) ? ws_department_member_ids($department_id) : array();
    $month_tasks = array();

    foreach ($rows as $row) {
        $task_id = (int) $row['id'];
        $people = $assignees[$task_id] ?? array();

        if (!ws_can_see_task($viewer, $row, $people)) {
            continue;
        }

        if (($person_id > 0) && !in_array($person_id, $people, true)) {
            continue;
        }

        if (((int) $department_id > 0) && ((int) $row['department_id'] !== (int) $department_id) && empty(array_intersect($people, $department_people))) {
            continue;
        }

        $due = (string) ($row['due_date'] ?? '');
        $start = (string) ($row['start_date'] ?? '');
        $date = ($due !== '' && $due !== '0000-00-00') ? $due
            : (($start !== '' && $start !== '0000-00-00') ? $start : date('Y-m-d', (int) $row['created_at']));

        if (!isset($days[$date])) {
            continue;
        }

        $open = ws_task_is_open($row['status']);

        $days[$date]['tasks'][] = array(
            'id'       => $task_id,
            'number'   => ws_task_number($task_id),
            'title'    => (string) $row['title'],
            'status'   => (string) $row['status'],
            'priority' => (string) $row['priority'],
            'open'     => $open,
            'overdue'  => $open && ($date < $today) && ($due !== ''),
            'dated'    => ($due !== '' && $due !== '0000-00-00'),
            'due_date' => ($due !== '' && $due !== '0000-00-00') ? $due : '',
            'people'   => array_values($people),
        );

        if (substr($date, 0, 7) === $month) {
            $month_tasks[] = array('row' => $row, 'people' => $people, 'date' => $date, 'open' => $open);
        }
    }

    // ── Coming copies of repeating tasks, on their due days ──

    $upcoming = 0;

    foreach ((function_exists('ws_recurrence_projections') ? ws_recurrence_projections($from, $to) : array()) as $item) {
        $row = $item['task'];
        $people = $item['people'];

        if (!ws_can_see_task($viewer, $row, $people)) {
            continue;
        }

        if (($person_id > 0) && !in_array($person_id, $people, true)) {
            continue;
        }

        if (((int) $department_id > 0) && ((int) $row['department_id'] !== (int) $department_id) && empty(array_intersect($people, $department_people))) {
            continue;
        }

        foreach ($item['copies'] as $copy) {
            if (!isset($days[$copy['due']])) {
                continue;
            }

            $days[$copy['due']]['tasks'][] = array(
                'id'         => (int) $row['id'],
                'number'     => ws_task_number($row['id']),
                'title'      => (string) $row['title'],
                'status'     => 'todo',
                'priority'   => (string) $row['priority'],
                'open'       => true,
                'overdue'    => false,
                'dated'      => true,
                'due_date'   => $copy['due'],
                'start_date' => ($copy['start'] !== $copy['due']) ? $copy['start'] : '',
                'people'     => $people,
                'upcoming'   => true,
                'repeat'     => ws_recurrence_label($item['series']),
                'moved_from' => !empty($copy['moved']) ? $copy['date'] : '',
                // The newest copy is overdue: this one comes only if that one
                // is done before it starts.
                'waits_for'  => !empty($item['late']) ? ws_task_number($row['id']) : '',
            );

            if (substr($copy['due'], 0, 7) === $month) {
                $upcoming++;
            }
        }
    }

    // ── Plan items ──

    foreach (ws_events_between($from, $to) as $event) {
        if (($person_id > 0) && ($event['scope'] === 'people') && !in_array($person_id, $event['people'], true)) {
            continue;
        }

        foreach (array_keys($days) as $date) {
            $day_start = strtotime($date . ' 00:00:00');
            $day_end = strtotime($date . ' 00:00:00 +1 day');

            if (((int) $event['starts_at'] >= $day_end) || ((int) $event['ends_at'] < $day_start)) {
                continue;
            }

            $days[$date]['events'][] = array(
                'id'     => (int) $event['id'],
                'kind'   => (string) $event['kind'],
                'title'  => (string) $event['title'],
                'time'   => ((int) $event['all_day'] === 1) ? '' : date('H:i', max((int) $event['starts_at'], $day_start)),
                'scope'  => (string) $event['scope'],
                'people' => array_values($event['people']),
            );
        }
    }

    // ── Conversations: channels opened, and how much was said each day ──

    $readable = array();

    foreach (ws_channels_for($viewer) as $channel) {
        $readable[$channel['id']] = $channel;
    }

    foreach (ws_channels_for($viewer, true) as $channel) {
        $readable[$channel['id']] = $channel;
    }

    $channel_filter = function ($channel) use ($department_id) {
        return ((int) $department_id <= 0) || ((int) $channel['department_id'] === (int) $department_id);
    };

    foreach ((array) db_items("SELECT id, name, kind, contact_id, department_id, created_at, created_by FROM ws_channels
        WHERE created_at BETWEEN '" . (int) $from_ts . "' AND '" . (int) $to_ts . "'
        ORDER BY created_at") as $channel) {

        if (!isset($readable[(int) $channel['id']]) || !$channel_filter($channel)) {
            continue;
        }

        $date = date('Y-m-d', (int) $channel['created_at']);

        if (isset($days[$date])) {
            $days[$date]['channels'][] = array(
                'id'       => (int) $channel['id'],
                'name'     => (string) $channel['name'],
                'private'  => ($channel['kind'] === 'private'),
                'customer' => ((int) $channel['contact_id'] > 0),
                'by'       => ws_person_name($channel['created_by']),
            );
        }
    }

    // Messages per channel per hour, read grouped; the hour is turned into a
    // day here, in the site's own time zone, not the database's.
    $talk = array();
    $channel_totals = array();
    $sender_filter = ($person_id > 0) ? " AND sender_kind = 'user' AND sender_id = '" . $person_id . "'" : '';

    foreach ((array) db_items("SELECT channel_id, FLOOR(created_at / 3600) AS hour_bucket, COUNT(*) AS n
        FROM ws_messages
        WHERE created_at BETWEEN '" . (int) $from_ts . "' AND '" . (int) $to_ts . "'
        AND deleted_at = 0 AND kind <> 'system'" . $sender_filter . "
        GROUP BY channel_id, hour_bucket") as $row) {

        $channel_id = (int) $row['channel_id'];

        if (!isset($readable[$channel_id]) || !$channel_filter($readable[$channel_id])) {
            continue;
        }

        $date = date('Y-m-d', ((int) $row['hour_bucket']) * 3600 + 1800);

        if (!isset($days[$date])) {
            continue;
        }

        $talk[$date][$channel_id] = ($talk[$date][$channel_id] ?? 0) + (int) $row['n'];

        if (substr($date, 0, 7) === $month) {
            $channel_totals[$channel_id] = ($channel_totals[$channel_id] ?? 0) + (int) $row['n'];
        }
    }

    foreach ($talk as $date => $channels) {
        arsort($channels);

        foreach ($channels as $channel_id => $count) {
            $days[$date]['messages'] += $count;
            $days[$date]['talk'][] = array(
                'id'      => $channel_id,
                'name'    => $readable[$channel_id]['name'],
                'private' => ($readable[$channel_id]['kind'] === 'private'),
                'count'   => $count,
            );
        }
    }

    // ── The month's figures ──

    $per_person = array();

    foreach ($people_ids as $user_id) {
        $per_person[(int) $user_id] = array('total' => 0, 'open' => 0, 'done' => 0, 'overdue' => 0, 'minutes' => 0);
    }

    $totals = array('tasks' => 0, 'open' => 0, 'done' => 0, 'overdue' => 0, 'unassigned' => 0, 'channels' => 0, 'messages' => 0, 'upcoming' => $upcoming);

    foreach ($month_tasks as $item) {
        $row = $item['row'];
        $totals['tasks']++;

        if ($item['open']) {
            $totals['open']++;

            if (($item['date'] < $today) && ((string) ($row['due_date'] ?? '') !== '')) {
                $totals['overdue']++;
            }
        } elseif ($row['status'] === 'done') {
            $totals['done']++;
        }

        if (empty($item['people'])) {
            $totals['unassigned']++;
        }

        foreach ($item['people'] as $user_id) {
            if (!isset($per_person[$user_id])) {
                continue;
            }

            $per_person[$user_id]['total']++;
            $per_person[$user_id]['minutes'] += ((int) $row['estimate_minutes'] > 0) ? (int) $row['estimate_minutes'] : (int) WS_DEFAULT_TASK_MINUTES;

            if ($item['open']) {
                $per_person[$user_id]['open']++;

                if (($item['date'] < $today) && ((string) ($row['due_date'] ?? '') !== '')) {
                    $per_person[$user_id]['overdue']++;
                }
            } elseif ($row['status'] === 'done') {
                $per_person[$user_id]['done']++;
            }
        }
    }

    $briefs = ws_people(array_keys($per_person));
    $people = array();

    foreach ($per_person as $user_id => $figures) {
        if (!isset($briefs[$user_id])) {
            continue;
        }

        $people[] = array('person' => $briefs[$user_id]) + $figures + array('minutes_label' => ws_minutes_label($figures['minutes']));
    }

    usort($people, function ($a, $b) {
        return ($b['total'] - $a['total']) ?: strcmp($a['person']['name'], $b['person']['name']);
    });

    $busiest = null;

    foreach ($days as $date => $day) {
        if (!$day['in_month']) {
            continue;
        }

        $totals['channels'] += count($day['channels']);
        $totals['messages'] += $day['messages'];

        $existing = count(array_filter($day['tasks'], function ($task) {
            return empty($task['upcoming']);
        }));
        $weight = $existing + $day['messages'];

        if (($weight > 0) && (($busiest === null) || ($weight > $busiest['weight']))) {
            $busiest = array('date' => $date, 'label' => $day['label'], 'weight' => $weight, 'tasks' => $existing, 'messages' => $day['messages']);
        }
    }

    arsort($channel_totals);
    $top_channels = array();

    foreach (array_slice($channel_totals, 0, 5, true) as $channel_id => $count) {
        $top_channels[] = array(
            'id'      => (int) $channel_id,
            'name'    => $readable[$channel_id]['name'],
            'private' => ($readable[$channel_id]['kind'] === 'private'),
            'count'   => (int) $count,
        );
    }

    // Most and least loaded, among the people who are in view; the least is
    // looked for among everyone, those with nothing at all included - they are
    // the first place to hand the next piece of work.
    $most = empty($people) ? null : $people[0];
    $least = empty($people) ? null : $people[count($people) - 1];

    if ($most && $least && ($most['person']['id'] === $least['person']['id'])) {
        $least = null;
    }

    $weeks = array_chunk(array_values($days), 7);

    return array(
        'month'        => $month,
        'label'        => ws_calendar_month_label($month),
        'prev'         => date('Y-m', strtotime($first . ' 12:00:00 -1 month')),
        'next'         => date('Y-m', strtotime($first . ' 12:00:00 +1 month')),
        'this_month'   => date('Y-m'),
        'weeks'        => $weeks,
        'weekdays'     => array_values(ws_weekday_short_names()),
        'people'       => $people,
        'most'         => $most,
        'least'        => $least,
        'totals'       => $totals,
        'busiest'      => $busiest,
        'top_channels' => $top_channels,
        'person_id'    => $person_id,
        'scope'        => ($scope === true) ? 'all' : 'limited',
    );
}
