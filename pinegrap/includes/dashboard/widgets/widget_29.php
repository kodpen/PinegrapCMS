<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Dashboard widget 29 - Agenda: the reader's next seven days from the
 * workspace calendar - meetings, visits, leave and holidays - with the tasks
 * that fall due on each day.
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

function pg_dashboard_widget_29($request, $user)
{
    $rights = pg_dashboard_workspace_rights($user);

    if ($rights === null) {
        return array('status' => 'error', 'message' => 'Access denied');
    }

    $me = (int) $rights['id'];
    $now = time();
    $from = date('Y-m-d', $now);
    $to = date('Y-m-d', strtotime('+6 days', $now));

    $days = array();

    foreach (ws_days($from, $to) as $date) {
        $days[$date] = array();
    }

    // Events about this person, the way the calendar decides it: the whole
    // company, one of their departments, or them by name. One that has ended
    // already is gone; one that runs over several days is listed once, on the
    // first day in view, with the day it ends.
    $departments = ws_user_department_ids($me);
    $kinds = ws_event_kinds();

    foreach (ws_events_between($from, $to) as $event) {
        if (((int) $event['ends_at'] < $now) || !ws_event_applies($event, $me, $departments)) {
            continue;
        }

        $first = max($from, date('Y-m-d', (int) $event['starts_at']));
        $last = date('Y-m-d', max((int) $event['starts_at'], (int) $event['ends_at'] - 1));
        $all_day = ((int) $event['all_day'] === 1) || ($first !== date('Y-m-d', (int) $event['starts_at']));

        if (!isset($days[$first])) {
            continue;
        }

        $days[$first][] = array(
            'sort' => $all_day ? 0 : ((int) $event['starts_at'] - strtotime($first . ' 00:00:00')),
            'row'  => pg_dashboard_agenda_event_row($event, $kinds, $all_day, $first, $last),
        );
    }

    // The reader's open tasks due in the same week.
    foreach (ws_tasks_list($rights, array('scope' => 'mine', 'status' => 'open', 'from' => $from, 'to' => $to, 'limit' => 40)) as $task) {
        if (!isset($days[(string) $task['due_date']])) {
            continue;
        }

        $days[$task['due_date']][] = array(
            'sort' => 86400 + (($task['priority'] === 'urgent') ? 0 : (($task['priority'] === 'high') ? 1 : 2)),
            'row'  => pg_widget_row(array(
                'small' => true,
                'href'  => h($task['url']),
                'badge' => '<i class="bi ' . (($task['priority'] === 'urgent') ? 'bi-exclamation-octagon' : 'bi-check2-square') . '"></i>',
                'color' => ($task['priority'] === 'urgent') ? '#ef4444' : '',
                'name'  => h($task['title']),
                'aside' => h(lang('Task due')),
            )),
        );
    }

    $output_rows = '';
    $shown = 0;

    foreach ($days as $date => $items) {
        if (empty($items) || ($shown >= 24)) {
            continue;
        }

        usort($items, function ($a, $b) {
            return $a['sort'] - $b['sort'];
        });

        if ($date === $from) {
            $label = lang('Today');
        } elseif ($date === date('Y-m-d', strtotime('+1 day', $now))) {
            $label = lang('Tomorrow');
        } else {
            $label = ws_day_label($date);
        }

        $output_rows .= pg_widget_row_heading(h($label), pg_format_number(count($items), 0));

        foreach ($items as $item) {
            $output_rows .= $item['row'];
            $shown++;
        }
    }

    $output_data = ($output_rows !== '')
        ? '<div class="pg-list">' . $output_rows . '</div>'
        : pg_widget_empty('bi-calendar2-check', lang('Nothing on your calendar this week.'), 'good', lang('Work Calendar'), 'workspace_calendar.php');

    return array(
        'status' => 'success',
        'message' => lang('Data Received successfully.'),
        'data' => '<div class="card-body p-0">' . $output_data . '</div>',
    );
}

/**
 * One calendar event as an agenda row.
 *
 * The tile is coloured by kind only where the kind changes what the day is:
 * leave and holidays are days off, and a visit takes someone out of the
 * office. A meeting wears the card's own colour.
 *
 * @param array  $event   a ws_events row
 * @param array  $kinds   ws_event_kinds()
 * @param bool   $all_day no start time on the day it is listed
 * @param string $first   the day it is listed on, Y-m-d
 * @param string $last    the day it ends, Y-m-d
 * @return string
 */
function pg_dashboard_agenda_event_row($event, $kinds, $all_day, $first, $last)
{
    $looks = array(
        'meeting' => array('bi-people', ''),
        'visit'   => array('bi-geo-alt', '#f59e0b'),
        'leave'   => array('bi-airplane', '#64748b'),
        'holiday' => array('bi-flag', '#10b981'),
        'other'   => array('bi-calendar-event', ''),
    );

    $look = $looks[$event['kind']] ?? $looks['other'];
    $meta = (string) ($kinds[$event['kind']] ?? $kinds['other']);

    if ($last > $first) {
        $meta .= ' · ' . lang(array('string' => 'until {var:1}', 'vars' => ws_day_label($last)));
    }

    return pg_widget_row(array(
        'small' => true,
        'href'  => 'workspace_calendar.php',
        'badge' => '<i class="bi ' . $look[0] . '"></i>',
        'color' => $look[1],
        'name'  => h($event['title']),
        'aside' => h($all_day
            ? lang('All day')
            : get_absolute_time(array('timestamp' => (int) $event['starts_at'], 'type' => 'time', 'timezone_type' => 'site', 'format' => 'plain_text'))),
        'meta'  => h($meta),
    ));
}
