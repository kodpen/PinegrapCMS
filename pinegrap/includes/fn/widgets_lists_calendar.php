<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// A module of functions.php: the calendar view's month and week grid as
// lists (widgets_lists.php), so a designer builds the grid from elements of
// their own instead of the server-built month_grid section:
//
//   calendar.weekdays  the seven column heads, in the widget's week order;
//   calendar.weeks     the weeks the grid shows (a month: four to six);
//   calendar.days      the days of one week, or of the whole grid;
//   calendar.events    the events of one day, an event lasting several days
//                      on each of them.
//
// A grid nests them: weeks > days > events. The first three read
// ^^__week_key^^: 0 outside a weeks list (the static value: the whole
// grid), the week's number in the grid inside one, so a days list there
// lists that week's days; an events list reads its day's ^^__day_key^^
// (Ymd). The renderer
// publishes the rows (_render_system_widget_calendar_view()); the monthly
// and weekly views fill them, the upcoming view has none. Days of the
// neighbouring months pad the first and last week of a month and hold no
// events, as in the classic grid.
//
// Loaded by functions.php through require_once, never on its own.

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// The calendar sources, in pg_sw_list_sources()'s shape. The events list
// carries the calendar's own event tokens, so an element bound in the event
// loop binds the same way in the grid.
function pg_sw_list_calendar_sources()
{
    return array(
        'calendar.weekdays' => array(
            'family'       => 'calendar',
            'key'          => '__week_key',
            'label'        => lang('Days of the week'),
            'arg'          => '',
            'arg_required' => false,
            'tokens'       => array(
                array('__weekday_name', lang('Day name')),
                array('__weekday_short', lang('Short day name')),
            ),
        ),
        'calendar.weeks' => array(
            'family'       => 'calendar',
            'key'          => '__week_key',
            'label'        => lang('Weeks of the calendar'),
            'arg'          => '',
            'arg_required' => false,
            'tokens'       => array(
                array('__week_key', lang('Week in the calendar')),
                array('__week_number', lang('Week number')),
            ),
        ),
        'calendar.days' => array(
            'family'       => 'calendar',
            'key'          => '__week_key',
            'label'        => lang('Days of the calendar'),
            'arg'          => '',
            'arg_required' => false,
            'tokens'       => array(
                array('__day_key', lang('Day key')),
                array('__day_number', lang('Day number')),
                array('__day_date', lang('Date')),
                array('__day_weekday', lang('Weekday')),
                array('__day_is_today', lang('Today (1 or empty)')),
                array('__day_is_outside', lang('Day of another month (1 or empty)')),
                array('__day_event_count', lang('Number of events')),
            ),
        ),
        'calendar.events' => array(
            'family'       => 'calendar',
            'key'          => '__day_key',
            'label'        => lang('Events of the day'),
            'arg'          => '',
            'arg_required' => false,
            'tokens'       => array(
                array('__event_title', lang('Event title')),
                array('__event_url', lang('Event detail URL')),
                array('__event_excerpt', lang('Event summary')),
                array('__event_date', lang('Event date')),
                array('__event_day', lang('Day of the month')),
                array('__event_month', lang('Month name')),
                array('__event_weekday', lang('Day of the week')),
                array('__event_time', lang('Start time')),
                array('__event_end_time', lang('End time')),
                array('__event_time_range', lang('Time range ("All day" for all-day events)')),
                array('__event_date_range', lang('Full date and time range')),
                array('__event_location', lang('Location')),
                array('__event_calendar_names', lang('Calendars')),
                array('__event_is_all_day', lang('All-day event (1 or empty)')),
                array('__event_ical_url', lang('Add to my calendar — .ics file (href)')),
            ),
        ),
    );
}

// The English day names from the first day of the week ('monday' or
// 'sunday').
function _pg_sw_list_calendar_day_names($week_start)
{
    return ($week_start === 'sunday')
        ? array('Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday')
        : array('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday');
}

// The rows of the calendar lists, as pg_sw_list_publish('calendar') files
// them: source => key => rows (raw values; the pass escapes them once).
// $grid_start and $grid_end are midnights of the first and last day drawn,
// whole weeks; $focus_month (1-12) marks the days of other months, null
// (a week) marks none. $events_by_day: Y-m-d => the event values of
// _pg_sw_calendar_event_values() for each event that day. $today: Y-m-d.
// A grid over more than six weeks is cut at six, as no month needs more.
function pg_sw_list_calendar_rows($grid_start, $grid_end, $focus_month, $events_by_day, $week_start, $date_format, $today)
{
    $names = _pg_sw_list_calendar_day_names($week_start);
    $weekdays = array();
    foreach ($names as $name) {
        // The dictionary's own short names (Pzt, Cmt …), as the classic
        // grid's heads: three letters of the long name would make Pazartesi
        // and Pazar the same.
        $weekdays[] = array('__weekday_name' => lang($name), '__weekday_short' => lang(substr($name, 0, 3)));
    }

    $weeks  = array();
    $days   = array(0 => array());
    $events = array();
    $start  = (int)$grid_start;
    $end    = (int)$grid_end;
    for ($ts = $start, $i = 0; $ts <= $end && $i < 42; $ts = strtotime('+1 day', $ts), $i++) {
        $week = intdiv($i, 7) + 1;
        if ($i % 7 === 0) {
            // The ISO week of the week's Monday: a Sunday-first week's
            // Sunday still belongs to the week before.
            $monday  = ($week_start === 'sunday') ? strtotime('+1 day', $ts) : $ts;
            $weeks[] = array('__week_key' => (string)$week, '__week_number' => (string)(int)date('W', $monday));
            $days[$week] = array();
        }
        $key     = date('Y-m-d', $ts);
        $day_key = (int)date('Ymd', $ts);
        $outside = ($focus_month !== null && (int)date('n', $ts) !== (int)$focus_month);
        $list    = array();
        if (!$outside && isset($events_by_day[$key]) && is_array($events_by_day[$key])) {
            foreach ($events_by_day[$key] as $vals) {
                // As the event loop draws it: an event without a page links
                // nowhere rather than to this page.
                if (isset($vals['__event_url']) && $vals['__event_url'] === '') $vals['__event_url'] = '#';
                $list[] = $vals;
            }
        }
        if ($list) $events[$day_key] = $list;
        $row = array(
            '__day_key'         => (string)$day_key,
            '__day_number'      => date('j', $ts),
            '__day_date'        => _pg_sw_short_date($ts, $date_format),
            '__day_weekday'     => lang(date('l', $ts)),
            '__day_is_today'    => ($key === $today) ? '1' : '',
            '__day_is_outside'  => $outside ? '1' : '',
            '__day_event_count' => (string)count($list),
        );
        $days[$week][] = $row;
        $days[0][]     = $row;
    }

    return array(
        'calendar.weekdays' => array(0 => $weekdays),
        'calendar.weeks'    => array(0 => $weeks),
        'calendar.days'     => $days,
        'calendar.events'   => $events,
    );
}
