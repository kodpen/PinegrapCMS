<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Dashboard widget 27 - My Tasks: the reader's open workspace tasks, as three
 * lanes - overdue, due today, everything open.
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

function pg_dashboard_widget_27($request, $user)
{
    $rights = pg_dashboard_workspace_rights($user);

    if ($rights === null) {
        return array('status' => 'error', 'message' => 'Access denied');
    }

    // The tasks assigned to the reader, the way the task list orders them:
    // by due date, undated last, the pressing first within a day. One read
    // fills all three lanes.
    $tasks = ws_tasks_list($rights, array('scope' => 'mine', 'status' => 'open', 'limit' => 60));
    $today = date('Y-m-d');

    $late = array();
    $due_today = array();

    foreach ($tasks as $task) {
        if ($task['overdue']) {
            $late[] = $task;
        } elseif ($task['due_date'] === $today) {
            $due_today[] = $task;
        }
    }

    // Done since Monday, for the line under the open lane: the list is what
    // is left, and this is what was cleared.
    $done_week = (int) db_value("SELECT COUNT(DISTINCT t.id) FROM ws_tasks t
        INNER JOIN ws_task_assignees a ON a.task_id = t.id AND a.user_id = '" . (int) $rights['id'] . "'
        WHERE t.status = 'done' AND t.completed_at >= '" . (int) strtotime('monday this week') . "'");

    $lanes = array(
        'late' => array(
            'label'  => lang('Overdue'),
            'icon'   => 'bi-alarm',
            'color'  => '#ef4444',
            'count'  => count($late),
            'urgent' => true,
            'sub'    => h(lang('Past their due date')),
            'body'   => pg_dashboard_tasks_list($late, pg_widget_empty('bi-check2-circle', lang('Nothing is overdue.'), 'good')),
        ),
        'today' => array(
            'label' => lang('Today'),
            'icon'  => 'bi-calendar-check',
            'color' => '#f59e0b',
            'count' => count($due_today),
            'sub'   => h(lang('Due today')),
            'body'  => pg_dashboard_tasks_list($due_today, pg_widget_empty('bi-cup-hot', lang('Nothing is due today.'), 'good')),
        ),
        'open' => array(
            'label' => lang('Open'),
            'icon'  => 'bi-list-check',
            'color' => '#6366f1',
            'count' => count($tasks),
            'sub'   => h(lang(array('string' => '{var:1} done this week', 'vars' => pg_format_number($done_week, 0)))),
            'body'  => pg_dashboard_tasks_list(array_slice($tasks, 0, 12), pg_widget_empty('bi-inbox', lang('No open tasks.'), 'good', lang('Open tasks'), 'workspace_tasks.php'))
                . ((count($tasks) > 12)
                    ? '<a class="pg-filter-more" href="workspace_tasks.php">' . h(lang(array('string' => '{var:1} more open', 'vars' => pg_format_number(count($tasks) - 12, 0)))) . ' <i class="bi bi-arrow-right-short"></i></a>'
                    : ''),
        ),
    );

    // Overdue first when there is any, then today, then the whole list.
    return array(
        'status' => 'success',
        'message' => lang('Data Received successfully.'),
        'data' => pg_widget_lanes('ws-tasks', lang('My Tasks'), $lanes),
    );
}

/**
 * Task rows for one lane.
 *
 * The tile carries the priority: red for urgent and for anything overdue,
 * amber for high, the card's own colour otherwise. The second line is the due
 * date and the status; the aside is how far the checklist has got.
 *
 * @param array  $tasks ws_tasks_list() briefs
 * @param string $empty what the lane says when it has none
 * @return string
 */
function pg_dashboard_tasks_list($tasks, $empty)
{
    if (empty($tasks)) {
        return $empty;
    }

    $output_rows = '';

    foreach ($tasks as $task) {

        if ($task['overdue'] || ($task['priority'] === 'urgent')) {
            $color = '#ef4444';
            $icon = $task['overdue'] ? 'bi-alarm' : 'bi-exclamation-octagon';
        } elseif ($task['priority'] === 'high') {
            $color = '#f59e0b';
            $icon = 'bi-arrow-up-circle';
        } else {
            $color = '';
            $icon = ($task['status'] === 'doing') ? 'bi-play-circle' : (($task['status'] === 'waiting') ? 'bi-pause-circle' : 'bi-check2-square');
        }

        $output_rows .= pg_widget_row(array(
            'href'  => h($task['url']),
            'badge' => '<i class="bi ' . $icon . '"></i>',
            'color' => $color,
            'name'  => h($task['title']),
            'aside' => ($task['progress'] !== null) ? h(lang(array('string' => '{var:1}%', 'vars' => (int) $task['progress']['percent']))) : '',
            'meta'  => h($task['due_label']) . ' &middot; ' . h($task['status_label']),
        ));
    }

    return '<div class="pg-list">' . $output_rows . '</div>';
}
