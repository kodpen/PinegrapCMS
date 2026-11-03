<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The Team pulse card: the people the reader may plan for (everyone with
 * the board right, the members of the departments they lead otherwise, as
 * widget 30 and the planning board decide), each with their presence, how
 * many of them are online, their open and late tasks, and who works on
 * what: a running task timer first, else a task in progress, else where
 * they are when they are out.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_DASHBOARD_V2')) {
    exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/dashboard/v2/team.php');

/**
 * The line under a person in "Who works on what".
 *
 * @param array $item  task, since (timer start or 0), away (text or '')
 * @param array $person pg_dashboard_v2_person()
 * @param int   $now
 * @return string
 */
function pg_dashboard_v2_team_note($item, $person, $now)
{
    $first = trim((string) strtok($person['name'], ' '));
    $name = ($first !== '') ? $first : $person['name'];

    if ($item['away'] !== '') {
        return $name . ' · ' . $item['away'];
    }

    if ($item['since'] > 0) {
        $minutes = (int) floor(max(0, $now - $item['since']) / 60);

        return $name . ' · ' . (($minutes < 60)
            ? lang(array('string' => 'working for {var:1} min', 'vars' => pg_format_number($minutes, 0)))
            : lang(array('string' => 'working for {var:1} h', 'vars' => pg_format_number(floor($minutes / 60), 0))));
    }

    if ($person['presence'] === 'away') {
        return $name . ' · ' . lang('away');
    }

    return $name . ' · ' . (($person['presence'] === 'online') ? lang('online') : lang('offline'));
}

function pg_dashboard_v2_card_team($user, $period, $compare)
{
    require_once(PG_FUNCTIONS_DIR . '/includes/dashboard/widgets.php');

    $rights = pg_dashboard_workspace_rights($user);

    if (($rights === null) || (!$rights['board'] && empty(ws_led_people($rights['id'])))) {
        return array('status' => 'denied');
    }

    $me = (int) $rights['id'];
    $now = time();
    $board = ws_board($rights, date('Y-m-d', $now), 1);

    $people = array();
    $away = array();

    foreach ($board['rows'] as $row) {
        $person = $row['person'];
        $person_id = (int) ($person['id'] ?? 0);

        if (($person_id <= 0) || ($person_id === $me) || !empty($person['gone'])) {
            continue;
        }

        $people[$person_id] = pg_dashboard_v2_person($person);

        // Out of the office today: on leave, or on a visit.
        $cell = $row['cells'][0] ?? null;

        if ($cell !== null) {
            if ($cell['leave']) {
                $away[$person_id] = lang('On leave');
            } else {
                foreach ($cell['events'] as $event) {
                    if ($event['kind'] === 'visit') {
                        $away[$person_id] = lang(array('string' => 'Visit: {var:1}', 'vars' => $event['title']));
                        break;
                    }
                }
            }
        }
    }

    if (empty($people)) {
        return array('status' => 'success', 'data' => array('empty' => lang('Nobody on your team yet.')));
    }

    $ids = implode(',', array_map('intval', array_keys($people)));

    // Open and late tasks of these people, each task once however many of
    // them it is assigned to.
    $counts = db_item("SELECT COUNT(DISTINCT t.id) AS open_tasks,
            COUNT(DISTINCT CASE WHEN t.due_date IS NOT NULL AND t.due_date > '0000-00-00' AND t.due_date < '" . e(date('Y-m-d', $now)) . "' THEN t.id END) AS late
        FROM ws_tasks t INNER JOIN ws_task_assignees a ON a.task_id = t.id
        WHERE a.user_id IN (" . $ids . ") AND t.status IN ('todo', 'doing', 'waiting')");

    // What each one works on: a running timer, else their newest task in
    // progress. A task the reader may not see is said, not named.
    $work = array();

    if (ws_task_time_ready()) {
        foreach ((array) db_items("SELECT user_id, task_id, started_at FROM ws_task_time
            WHERE minutes = 0 AND started_at > 0 AND user_id IN (" . $ids . ")
            ORDER BY started_at DESC") as $item) {
            if (!isset($work[(int) $item['user_id']])) {
                $work[(int) $item['user_id']] = array('task_id' => (int) $item['task_id'], 'since' => (int) $item['started_at']);
            }
        }
    }

    foreach ((array) db_items("SELECT a.user_id, t.id AS task_id FROM ws_tasks t
        INNER JOIN ws_task_assignees a ON a.task_id = t.id
        WHERE a.user_id IN (" . $ids . ") AND t.status = 'doing'
        ORDER BY t.updated_at DESC LIMIT 60") as $item) {
        if (!isset($work[(int) $item['user_id']])) {
            $work[(int) $item['user_id']] = array('task_id' => (int) $item['task_id'], 'since' => 0);
        }
    }

    $rows = array();

    foreach ($people as $person_id => $person) {
        if (count($rows) >= 4) {
            break;
        }

        $item = array('task' => null, 'since' => 0, 'away' => $away[$person_id] ?? '');

        if (isset($work[$person_id]) && ($item['away'] === '')) {
            $task = ws_task($work[$person_id]['task_id']);

            if ($task) {
                $item['task'] = $task;
                $item['since'] = $work[$person_id]['since'];
            }
        } elseif ($item['away'] === '') {
            continue;
        }

        $visible = $item['task'] && ws_can_see_task($rights, $item['task']);

        $rows[] = array(
            'person' => $person,
            'text'   => $item['task'] ? ($visible ? (string) $item['task']['title'] : lang('A task')) : lang('Out of office'),
            'note'   => pg_dashboard_v2_team_note($item, $person, $now),
            'url'    => ($item['task'] && $visible) ? 'workspace_tasks.php?task=' . (int) $item['task']['id'] : 'workspace_board.php',
        );
    }

    $online = 0;

    foreach ($people as $person) {
        if ($person['presence'] === 'online') {
            $online++;
        }
    }

    $late = (int) ($counts['late'] ?? 0);

    return array('status' => 'success', 'data' => array(
        'people'     => array_values(array_slice($people, 0, 8, true)),
        'more'       => max(0, count($people) - 8),
        'presence'   => array('online' => lang('online'), 'away' => lang('away'), 'offline' => lang('offline')),
        'summary'    => array(
            array('text' => lang(array('string' => '{var:1} online', 'vars' => pg_format_number($online, 0))), 'tone' => ''),
            array('text' => lang(array('string' => '{var:1} open task{suffix:1}', 'vars' => pg_format_number((int) ($counts['open_tasks'] ?? 0), 0))), 'tone' => ''),
            array('text' => lang(array('string' => '{var:1} late', 'vars' => pg_format_number($late, 0))), 'tone' => ($late > 0) ? 'bad' : ''),
        ),
        'rows_title' => lang('Who works on what'),
        'rows'       => $rows,
        'none'       => lang('Nobody has a task in progress.'),
        'url'        => 'workspace_board.php',
        'url_text'   => lang('Planning board'),
    ));
}
