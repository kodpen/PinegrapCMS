<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Dashboard widget 30 - Team Pulse: the people the reader looks after, today
 * - who is out (on leave or on a visit), who has more planned than the day
 * holds, and who has a task timer running right now.
 *
 * Loaded and called by includes/dashboard/widgets.php; see that file for the
 * contract every widget follows.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_DASHBOARD_WIDGETS')) {
    exit;
}

function pg_dashboard_widget_30($request, $user)
{
    $rights = pg_dashboard_workspace_rights($user);

    // The planning board decides who may be seen: everyone for those with
    // the board right, the members of the departments a person leads
    // otherwise. Somebody who leads nobody has no team to take the pulse of.
    if (($rights === null) || (!$rights['board'] && empty(ws_led_people($rights['id'])))) {
        return array('status' => 'error', 'message' => 'Access denied');
    }

    $me = (int) $rights['id'];
    $now = time();
    $board = ws_board($rights, date('Y-m-d', $now), 1);

    $out = array();
    $busy = array();
    $people = array();

    foreach ($board['rows'] as $row) {
        $person = $row['person'];
        $person_id = (int) ($person['id'] ?? 0);

        if (($person_id === $me) || !empty($person['gone'])) {
            continue;
        }

        $people[$person_id] = $person;
        $cell = $row['cells'][0] ?? null;

        if ($cell === null) {
            continue;
        }

        // Out: on leave, or on a visit that has not ended. A holiday is a day
        // off for everyone and says nothing about one person.
        $away = '';

        if ($cell['leave']) {
            $away = lang('On leave');
        } else {
            foreach ($cell['events'] as $event) {
                if ($event['kind'] === 'visit') {
                    $away = lang(array('string' => 'Visit: {var:1}', 'vars' => $event['title']))
                        . (($event['time'] !== '') ? ' · ' . $event['time'] : '');
                    break;
                }
            }
        }

        if ($away !== '') {
            $out[] = pg_widget_row(array(
                'href'  => 'workspace_board.php',
                'badge' => pg_dashboard_person_badge($person),
                'name'  => h($person['name']),
                'meta'  => h($away),
            ));
            continue;
        }

        // Overloaded: more planned for today than the day holds.
        $capacity = (int) $cell['capacity'];
        $load = ($capacity > 0) ? (int) round(((int) $cell['minutes'] / $capacity) * 100) : 0;

        if (($capacity > 0) && ($load > 100)) {
            $busy[] = array(
                'load' => $load,
                'row'  => pg_widget_row(array(
                    'href'  => 'workspace_board.php',
                    'badge' => pg_dashboard_person_badge($person),
                    'name'  => h($person['name']),
                    'aside' => h(lang(array('string' => '{var:1}%', 'vars' => $load))),
                    'meta'  => h(lang(array(
                        'string' => '{var:1} task{suffix:1} planned today',
                        'vars'   => pg_format_number(count($cell['tasks']), 0),
                        'suffix' => array((count($cell['tasks']) == 1) ? '' : 's'),
                    ))),
                    'class' => 'is-over',
                )),
            );
        }
    }

    usort($busy, function ($a, $b) {
        return $b['load'] - $a['load'];
    });

    // Timers running now, for the same people. A task the reader may not see
    // is still counted, but not named.
    $timers = array();

    if (!empty($people) && ws_task_time_ready()) {

        $running = (array) db_items("SELECT user_id, task_id, started_at FROM ws_task_time
            WHERE minutes = 0 AND started_at > 0
            AND user_id IN (" . implode(',', array_map('intval', array_keys($people))) . ")
            ORDER BY started_at");

        $task_ids = array();

        foreach ($running as $item) {
            $task_ids[] = (int) $item['task_id'];
        }

        $assignees = empty($task_ids) ? array() : ws_task_assignees_map($task_ids);

        foreach ($running as $item) {
            $task = ws_task((int) $item['task_id']);
            $title = ($task && ws_can_see_task($rights, $task, $assignees[(int) $item['task_id']] ?? array()))
                ? (string) $task['title']
                : lang('A task');
            $elapsed = max(0, $now - (int) $item['started_at']);

            $timers[] = pg_widget_row(array(
                'href'  => $task ? 'workspace_tasks.php?task=' . (int) $task['id'] : 'workspace_board.php',
                'badge' => pg_dashboard_person_badge($people[(int) $item['user_id']]),
                'name'  => h($people[(int) $item['user_id']]['name']),
                // The page script moves this on every minute
                // (data-pg-elapsed), the same way as the timer under the
                // greeting.
                'aside' => '<span data-pg-elapsed="' . (int) $item['started_at'] . '">'
                    . sprintf('%d:%02d', floor($elapsed / 3600), floor(($elapsed % 3600) / 60)) . '</span>',
                'meta'  => h($title),
            ));
        }
    }

    $lanes = array(
        'out' => array(
            'label' => lang('Out of office'),
            'icon'  => 'bi-door-open',
            'color' => '#64748b',
            'count' => count($out),
            'sub'   => h(lang('On leave or on a visit')),
            'body'  => !empty($out)
                ? '<div class="pg-list">' . implode('', $out) . '</div>'
                : pg_widget_empty('bi-people', lang('Everyone is in today.'), 'good'),
        ),
        'busy' => array(
            'label'  => lang('Overloaded'),
            'icon'   => 'bi-speedometer2',
            'color'  => '#ef4444',
            'count'  => count($busy),
            'urgent' => true,
            'sub'    => h(lang('More planned than the day holds')),
            'body'   => !empty($busy)
                ? '<div class="pg-list">' . implode('', array_column($busy, 'row')) . '</div>'
                : pg_widget_empty('bi-check2-circle', lang('Nobody has more than a day of work today.'), 'good'),
        ),
        'timer' => array(
            'label' => lang('Working'),
            'icon'  => 'bi-stopwatch',
            'color' => '#10b981',
            'count' => count($timers),
            'sub'   => h(lang('A task timer is running')),
            'body'  => !empty($timers)
                ? '<div class="pg-list">' . implode('', $timers) . '</div>'
                : pg_widget_empty('bi-stopwatch', lang('No task timer is running.')),
        ),
    );

    return array(
        'status' => 'success',
        'message' => lang('Data Received successfully.'),
        'data' => pg_widget_lanes('ws-team', lang('Team Pulse'), $lanes),
    );
}

/**
 * A person's tile: their avatar where they have one, otherwise the first
 * letter of their name.
 *
 * @param array $person ws_people() entry
 * @return string
 */
function pg_dashboard_person_badge($person)
{
    if (!empty($person['avatar'])) {
        return '<img src="' . h($person['avatar']) . '" alt="">';
    }

    return h(mb_strtoupper(mb_substr((string) ($person['name'] ?? '?'), 0, 1, 'UTF-8'), 'UTF-8'));
}
