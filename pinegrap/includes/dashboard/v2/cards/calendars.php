<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The Calendars card: five weeks from the Monday of this week with the days
 * that have events marked, the next three events, and how many calendars
 * the reader may see. Events are those of the calendars widget 20 shows
 * (validate_calendar_access()), expanded with their recurrences by
 * get_calendar() a month at a time, as the calendar system widget does.
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

function pg_dashboard_v2_card_calendars($user, $period, $compare)
{
    if (!defined('CALENDARS') || (CALENDARS !== true) || (validate_calendars_access($user, true) == false)) {
        return array('status' => 'denied');
    }

    $calendars = array();

    foreach ((array) db_items("SELECT id, name FROM calendars ORDER BY name") as $row) {
        if (validate_calendar_access($row['id']) == true) {
            $calendars[] = $row;
        }
    }

    if (empty($calendars)) {
        return array('status' => 'success', 'data' => array(
            'empty' => lang('There are no calendars, so no calendar events could be displayed.'),
        ));
    }

    $now = time();
    $first = pg_dashboard_v2_week_start($now);
    $last = mktime(0, 0, 0, (int) date('n', $first), (int) date('j', $first) + 34, (int) date('Y', $first));
    $from = date('Y-m-d', $first);
    $to = date('Y-m-d', $last);

    // get_calendar() expands recurrences month by month; five weeks span two
    // or three months.
    $events = array();
    $month = mktime(0, 0, 0, (int) date('n', $first), 1, (int) date('Y', $first));

    while ($month <= $last) {
        foreach ((array) get_calendar('', $calendars, 'monthly', '', $user, date('m-01-Y', $month), '', '', 'array') as $event) {
            if (($event['event_start_date'] >= $from) && ($event['event_start_date'] <= $to)) {
                $events[] = $event;
            }
        }

        $month = mktime(0, 0, 0, (int) date('n', $month) + 1, 1, (int) date('Y', $month));
    }

    $counts = array();
    $coming = array();
    $today = date('Y-m-d', $now);

    foreach ($events as $event) {
        $counts[$event['event_start_date']] = ($counts[$event['event_start_date']] ?? 0) + 1;

        $all_day = !empty($event['all_day']);
        $start = strtotime($event['event_start_date'] . ' ' . ($all_day ? '00:00:00' : $event['event_start_time']));

        // Still to come: today's all-day events, the timed ones not started.
        if (($event['event_start_date'] < $today) || (!$all_day && ($start < $now))) {
            continue;
        }

        $coming[] = array(
            'sort' => $start,
            'date' => pg_dashboard_v2_short_date($start),
            'text' => (string) $event['name'],
            'note' => $all_day
                ? lang('All day')
                : get_absolute_time(array('timestamp' => $start, 'type' => 'time', 'format' => 'plain_text')),
            'url'  => 'edit_calendar_event.php?id=' . (int) $event['id'],
        );
    }

    usort($coming, function ($a, $b) {
        return $a['sort'] - $b['sort'];
    });

    $coming = array_slice($coming, 0, 3);

    foreach ($coming as $key => $item) {
        unset($coming[$key]['sort']);
    }

    $range = date('j', $first) . ' ' . lang(date('F', $first)) . ' – ' . date('j', $last) . ' ' . lang(date('F', $last));

    return array('status' => 'success', 'data' => array(
        'range'    => $range,
        'count'    => (count($calendars) === 1)
            ? lang('One calendar')
            : lang(array('string' => '{var:1} calendars', 'vars' => pg_format_number(count($calendars), 0))),
        'weekdays' => array_map(function ($day) {
            return $day['name'];
        }, array_slice(pg_dashboard_v2_days($first, 7, array(), $now), 0, 7)),
        'days'     => pg_dashboard_v2_days($first, 35, $counts, $now),
        'items'    => $coming,
        'none'     => lang('No events coming up.'),
        'url'      => 'calendars.php',
        'url_text' => lang('Go to calendars'),
    ));
}
