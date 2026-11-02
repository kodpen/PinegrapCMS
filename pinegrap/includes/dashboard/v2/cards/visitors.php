<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The Visitor summaries card: visits, page views and pages per visit for
 * the period, a line of visits (hours for today, days for 7 and 30 days)
 * with the previous period or last year dashed beside it, and the most read
 * content. Read from the hourly rollups widget 5 reads
 * (pg_visitor_stats_range(), pg_visitor_top_content()), never from the raw
 * visitors table.
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
 * The dates a period covers and the dates it is compared with.
 *
 * @param int $period  0 today, 1 last 7 days, 2 last 30 days
 * @param int $compare 0 none, 1 the period before, 2 the same dates a year ago
 * @param int $now
 * @return array dates (Y-m-d, oldest first), compare (same length, or empty),
 *               before (the period before, for the change figure), hourly
 */
function pg_dashboard_v2_visitor_dates($period, $compare, $now)
{
    $days = array(0 => 1, 1 => 7, 2 => 30);
    $count = $days[$period] ?? 1;

    $dates = array();
    $before = array();
    $last_year = array();

    // Days are stepped with strtotime(): across a clock change a day is not
    // 86,400 seconds.
    for ($i = $count - 1; $i >= 0; $i--) {
        $day = strtotime('-' . $i . ' days', $now);
        $dates[] = date('Y-m-d', $day);
        $before[] = date('Y-m-d', strtotime('-' . ($i + $count) . ' days', $now));
        $last_year[] = date('Y-m-d', strtotime('-1 year', $day));
    }

    return array(
        'dates'   => $dates,
        'before'  => $before,
        'compare' => ($compare === 1) ? $before : (($compare === 2) ? $last_year : array()),
        'hourly'  => ($count === 1),
    );
}

/**
 * The plotted series from the rollup rows: one point an hour for a single
 * day (up to $upto_hour), else one point a day.
 *
 * @param array $stats     date => hour => array(visitors, page_views)
 * @param array $dates
 * @param bool  $hourly
 * @param int   $upto_hour the last hour drawn, 23 for a whole day
 * @return array visits (points), views (total)
 */
function pg_dashboard_v2_visitor_series($stats, $dates, $hourly, $upto_hour = 23)
{
    $points = array();
    $views = 0;

    if ($hourly) {
        $date = (string) reset($dates);
        $points = array_fill(0, (int) $upto_hour + 1, 0);

        foreach ((array) ($stats[$date] ?? array()) as $hour => $values) {
            $hour = (int) $hour;

            if ($hour <= $upto_hour) {
                $points[$hour] = (int) $values['visitors'];
                $views += (int) $values['page_views'];
            }
        }

        return array('visits' => $points, 'views' => $views);
    }

    foreach ($dates as $date) {
        $sum = 0;

        foreach ((array) ($stats[$date] ?? array()) as $values) {
            $sum += (int) $values['visitors'];
            $views += (int) $values['page_views'];
        }

        $points[] = $sum;
    }

    return array('visits' => $points, 'views' => $views);
}

/**
 * The rollup rows of a set of dates (one range read per set).
 *
 * @param array $dates oldest first
 * @return array
 */
function pg_dashboard_v2_visitor_stats($dates)
{
    if (empty($dates)) {
        return array();
    }

    return pg_visitor_stats_range(reset($dates), end($dates));
}

function pg_dashboard_v2_card_visitors($user, $period, $compare)
{
    if (!pg_dashboard_v2_gate('visitors', $user)) {
        return array('status' => 'denied');
    }

    $now = time();
    $range = pg_dashboard_v2_visitor_dates($period, $compare, $now);
    $hour = (int) date('G', $now);
    $upto = $range['hourly'] ? $hour : 23;

    $current = pg_dashboard_v2_visitor_series(pg_dashboard_v2_visitor_stats($range['dates']), $range['dates'], $range['hourly'], $upto);

    // The change compares like with like: today up to this hour with the
    // day before up to the same hour.
    $before = pg_dashboard_v2_visitor_series(pg_dashboard_v2_visitor_stats($range['before']), $range['before'], $range['hourly'], $upto);

    $compare_line = null;

    if (!empty($range['compare'])) {
        $compare_line = pg_dashboard_v2_visitor_series(pg_dashboard_v2_visitor_stats($range['compare']), $range['compare'], $range['hourly'], 23)['visits'];
    }

    $visits = array_sum($current['visits']);
    $change = pg_dashboard_v2_change($visits, array_sum($before['visits']));
    $per_visit = ($visits > 0) ? ($current['views'] / $visits) : 0;

    $labels = array();

    if ($range['hourly']) {
        for ($h = 0; $h < 24; $h++) {
            $labels[] = str_pad((string) $h, 2, '0', STR_PAD_LEFT) . ':00';
        }
    } else {
        foreach ($range['dates'] as $date) {
            $labels[] = get_absolute_time(array('timestamp' => strtotime($date), 'type' => 'date', 'year' => false, 'format' => 'plain_text', 'timezone_type' => 'site'));
        }
    }

    $chart_labels = array(
        0 => lang('Visits per hour today'),
        1 => lang('Visits per day, last 7 days'),
        2 => lang('Visits per day, last 30 days'),
    );

    $compare_names = array(
        1 => ($period === 0) ? lang('Yesterday') : lang('Previous period'),
        2 => lang('Last year'),
    );

    $rows = array();
    $top = pg_visitor_top_content(reset($range['dates']), end($range['dates']), 5);
    $most = empty($top) ? 0 : (int) $top[0]['views'];

    foreach ($top as $row) {
        $rows[] = array(
            'text'  => (string) $row['label'],
            'value' => (int) $row['views'],
            'share' => ($most > 0) ? round(((int) $row['views']) / $most, 3) : 0,
            'note'  => pg_format_number((int) $row['views'], 0),
        );
    }

    return array('status' => 'success', 'data' => array(
        'tiles' => array(
            array('label' => lang('Visits'), 'text' => pg_format_number($visits, 0), 'note' => ($change === null) ? '' : $change['text'], 'tone' => ($change === null) ? '' : $change['tone']),
            array('label' => lang('Page views'), 'text' => pg_format_number($current['views'], 0), 'note' => '', 'tone' => ''),
            array('label' => lang('Pages per visit'), 'text' => pg_format_number($per_visit, 1), 'note' => '', 'tone' => ''),
        ),
        'points'        => $current['visits'],
        'compare'       => $compare_line,
        'compare_label' => ($compare_line === null) ? '' : $compare_names[$compare],
        'labels'        => $labels,
        'chart_label'   => $chart_labels[$period] ?? $chart_labels[0],
        'rows_title'    => lang('Most read'),
        'rows'          => $rows,
        'empty'         => (($visits === 0) && (array_sum((array) $compare_line) === 0)) ? lang('No visits in this period yet.') : '',
        'url'           => 'view_visitor_reports.php',
    ));
}
