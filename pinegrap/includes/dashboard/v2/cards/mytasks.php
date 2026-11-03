<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The My tasks card: the reader's open Workspace tasks in the task list's
 * order (due date, undated last, the pressing first), each with a box that
 * ticks it done through the Workspace's own ws_task_status action, as the
 * Today page does; and how many of today's tasks are done. The same read
 * as widget 27 (ws_tasks_list(), scope mine).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_DASHBOARD_V2')) {
    exit;
}

/**
 * A task's tone on the card: late is bad, due today or urgent is a warning.
 *
 * @param array  $task  ws_tasks_list() brief
 * @param string $today Y-m-d
 * @return string bad, warn or ''
 */
function pg_dashboard_v2_task_tone($task, $today)
{
    if (!empty($task['overdue'])) {
        return 'bad';
    }

    if ((($task['due_date'] ?? '') === $today) || (($task['priority'] ?? '') === 'urgent')) {
        return 'warn';
    }

    return '';
}

function pg_dashboard_v2_card_mytasks($user, $period, $compare)
{
    require_once(PG_FUNCTIONS_DIR . '/includes/dashboard/widgets.php');

    $rights = pg_dashboard_workspace_rights($user);

    if ($rights === null) {
        return array('status' => 'denied');
    }

    $me = (int) $rights['id'];
    $now = time();
    $today = date('Y-m-d', $now);
    $tasks = ws_tasks_list($rights, array('scope' => 'mine', 'status' => 'open', 'limit' => 60));

    $rows = array();
    $due_today = 0;

    foreach ($tasks as $task) {
        if ($task['due_date'] === $today) {
            $due_today++;
        }

        if (count($rows) >= 5) {
            continue;
        }

        $priorities = array('urgent' => lang('urgent'), 'high' => lang('high'));
        $note = (string) $task['due_label'];

        if (isset($priorities[$task['priority']])) {
            $note .= ' · ' . $priorities[$task['priority']];
        }

        $rows[] = array(
            'id'     => (int) $task['id'],
            'text'   => (string) $task['title'],
            'note'   => $note,
            'tone'   => pg_dashboard_v2_task_tone($task, $today),
            'status' => (string) $task['status'],
            'url'    => (string) $task['url'],
        );
    }

    // Done today among the reader's tasks due today, for "Today 1/4 done".
    $done_today = (int) db_value("SELECT COUNT(DISTINCT t.id) FROM ws_tasks t
        INNER JOIN ws_task_assignees a ON a.task_id = t.id AND a.user_id = '" . $me . "'
        WHERE t.status = 'done' AND t.due_date = '" . e($today) . "'");

    return array('status' => 'success', 'data' => array(
        'summary'  => lang(array('string' => 'Today {var:1}/{var:2} done', 'vars' => array(pg_format_number($done_today, 0), pg_format_number($done_today + $due_today, 0)))),
        'rows'     => $rows,
        'empty'    => empty($rows) ? lang('No open tasks.') : '',
        'labels'   => array(
            'tick'   => lang('Mark {var:1} done'),
            'done'   => lang('Done'),
            'failed' => lang('The task could not be changed.'),
        ),
        'url'      => 'workspace_tasks.php',
        'url_text' => lang('All tasks'),
    ));
}
