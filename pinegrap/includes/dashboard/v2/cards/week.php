<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The My week card: the seven days of this week (Monday first) with a dot
 * per item, today marked, then the coming items as dated rows. Items are
 * the Work Calendar events about the reader (the whole company, one of
 * their departments, or them by name) and their open tasks due that week,
 * read as widget 29 reads them.
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

function pg_dashboard_v2_card_week($user, $period, $compare)
{
    require_once(PG_FUNCTIONS_DIR . '/includes/dashboard/widgets.php');

    $rights = pg_dashboard_workspace_rights($user);

    if ($rights === null) {
        return array('status' => 'denied');
    }

    $me = (int) $rights['id'];
    $now = time();
    $monday = pg_dashboard_v2_week_start($now);
    $from = date('Y-m-d', $monday);
    $to = date('Y-m-d', mktime(0, 0, 0, (int) date('n', $monday), (int) date('j', $monday) + 6, (int) date('Y', $monday)));
    $today = date('Y-m-d', $now);

    $counts = array();
    $items = array();
    $departments = ws_user_department_ids($me);
    $kinds = ws_event_kinds();

    foreach (ws_events_between($from, $to) as $event) {
        if (!ws_event_applies($event, $me, $departments)) {
            continue;
        }

        $start = (int) $event['starts_at'];
        $day = max($from, date('Y-m-d', $start));
        $counts[$day] = ($counts[$day] ?? 0) + 1;

        // The rows are what is still to come.
        if ((int) $event['ends_at'] < $now) {
            continue;
        }

        $all_day = ((int) $event['all_day'] === 1) || ($day !== date('Y-m-d', $start));

        $items[] = array(
            'sort' => max($start, strtotime($day . ' 00:00:00')),
            'date' => pg_dashboard_v2_short_date(strtotime($day . ' 12:00:00')),
            'text' => (string) $event['title'],
            'note' => (string) ($kinds[$event['kind']] ?? $kinds['other']) . ' · ' . ($all_day
                ? lang('All day')
                : get_absolute_time(array('timestamp' => $start, 'type' => 'time', 'timezone_type' => 'site', 'format' => 'plain_text'))),
            'url'  => 'workspace_calendar.php',
        );
    }

    foreach (ws_tasks_list($rights, array('scope' => 'mine', 'status' => 'open', 'from' => $from, 'to' => $to, 'limit' => 40)) as $task) {
        $day = (string) $task['due_date'];
        $counts[$day] = ($counts[$day] ?? 0) + 1;

        if ($day < $today) {
            continue;
        }

        $items[] = array(
            // After the day's events: a task has no hour.
            'sort' => strtotime($day . ' 23:59:59'),
            'date' => pg_dashboard_v2_short_date(strtotime($day . ' 12:00:00')),
            'text' => (string) $task['title'],
            'note' => lang('Task due'),
            'url'  => (string) $task['url'],
        );
    }

    usort($items, function ($a, $b) {
        return $a['sort'] - $b['sort'];
    });

    foreach ($items as $key => $item) {
        unset($items[$key]['sort']);
    }

    return array('status' => 'success', 'data' => array(
        'days'       => pg_dashboard_v2_days($monday, 7, $counts, $now),
        'days_label' => lang('This week'),
        'items'      => array_slice($items, 0, 4),
        'none'       => lang('Nothing on your calendar this week.'),
        'url'        => 'workspace_calendar.php',
        'url_text'   => lang('Work Calendar'),
    ));
}
