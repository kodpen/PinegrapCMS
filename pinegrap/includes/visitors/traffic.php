<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The traffic section of the Visitors overview (visitors.php): the headline
 * tiles (visits, page views, pages a visit, people on the site now), each
 * against the earlier period, and the line of visits and page views by the
 * period's grain with the earlier period dashed. The tooltip names the most
 * viewed content of the hour or the day. Totals come from the figures module
 * (includes/fn/visitor_figures.php), the line from the hourly rollup.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_VISITORS_SCREEN')) {
    exit;
}

// Above this many buckets the line's tooltip names no content: one rollup
// query a bucket would cost more than the note is worth.
if (!defined('PG_VISITORS_TRAFFIC_NOTE_BUCKETS')) {
    define('PG_VISITORS_TRAFFIC_NOTE_BUCKETS', 62);
}

/**
 * The traffic section.
 *
 * @param array $period pg_visitor_period()
 * @param array $user   validate_user()
 * @return string HTML
 */
function pg_visitors_section_traffic(array $period, array $user)
{
    $now = pg_visitor_totals((int) $period['from_ts'], (int) $period['to_ts'] - 1);
    $before = $period['compare']
        ? pg_visitor_totals((int) $period['previous_from_ts'], (int) $period['previous_to_ts'] - 1)
        : null;

    $series = pg_visitor_traffic($period['from'], $period['to'], $period['grain']);
    $previous_series = $period['compare']
        ? pg_visitor_traffic($period['previous_from'], $period['previous_to'], $period['grain'])
        : array();

    $state = pg_visitor_backfill_state();

    return pg_visitors_traffic_render($period, array(
        'now'             => $now,
        'before'          => $before,
        'online'          => (int) db_value("SELECT COUNT(*) FROM visitors WHERE stop_timestamp >= " . (int) (time() - 1200)),
        'series'          => $series,
        'previous_series' => $previous_series,
        'notes'           => pg_visitors_traffic_notes($period, array_keys($series)),
        'backfill'        => (is_array($state) && empty($state['done']) && ($state['max_id'] > 0)),
    ));
}

/**
 * The section's HTML from figures already read. Pure but for lang().
 *
 *   now, before      pg_visitor_totals() of the period and the earlier one
 *                    (before null with comparing off)
 *   online           people on the site in the last 20 minutes
 *   series           pg_visitor_traffic() of the period
 *   previous_series  the same of the earlier period, array() for none
 *   notes            the tooltip line of each bucket, in order
 *   backfill         true while the rollup backfill is still running
 *
 * @param array $period  pg_visitor_period()
 * @param array $figures
 * @return string HTML
 */
function pg_visitors_traffic_render(array $period, array $figures)
{
    $now = $figures['now'];
    $before = $figures['before'];

    $visits = (int) $now['visits'];
    $views = (int) $now['page_views'];
    $ratio = pg_visitors_traffic_ratio($views, $visits);

    $change = function ($current, $figure, $previous = null) use ($before) {
        if ($before === null) {
            return '';
        }

        return pg_visitors_change($current, ($previous !== null) ? $previous : (int) $before[$figure]);
    };

    $before_ratio = ($before !== null) ? pg_visitors_traffic_ratio((int) $before['page_views'], (int) $before['visits']) : null;

    $tiles = '<div class="pg-visitors-tiles pg-visitors-traffic-tiles">'
        . pg_visitors_tile(lang('Visits'), pg_format_number($visits, 0), $change($visits, 'visits'))
        . pg_visitors_tile(lang('Page views'), pg_format_number($views, 0), $change($views, 'page_views'))
        . pg_visitors_tile(
            lang('Pages a visit'),
            ($ratio === null) ? '–' : pg_format_number($ratio, 1),
            ($before === null) ? '' : pg_visitors_change($ratio, $before_ratio)
        )
        . pg_visitors_tile(lang('On the site now'), pg_format_number((int) $figures['online'], 0), '', lang('In the last 20 minutes'))
        . '</div>';

    $series = $figures['series'];
    $has_line = false;

    foreach ($series as $bucket) {
        if (((int) $bucket['visits'] > 0) || ((int) $bucket['page_views'] > 0)) {
            $has_line = true;
            break;
        }
    }

    if (($visits === 0) && ($views === 0) && !$has_line) {
        $line = pg_visitors_empty($figures['backfill']
            ? lang('The figures are still being built from the visit history; this section fills in as that finishes.')
            : '');
    } else {
        $line = pg_visitors_traffic_chart($period, $figures);
    }

    $aside = $figures['backfill']
        ? '<span class="small fg-secondary">' . h(lang('The visit history is still being added to the line.')) . '</span>'
        : '';

    return pg_visitors_section('traffic', lang('Traffic'), $tiles . '<div class="pg-visitors-traffic-line">' . $line . '</div>', $aside);
}

/**
 * The line of the section: visits and page views, and with comparing on the
 * earlier period's visits dashed, set bucket against bucket.
 *
 * @param array $period  pg_visitor_period()
 * @param array $figures see pg_visitors_traffic_render()
 * @return string HTML
 */
function pg_visitors_traffic_chart(array $period, array $figures)
{
    $keys = array_keys($figures['series']);
    $count = count($keys);
    $cut = pg_visitors_traffic_cut($period);

    $chart = array(
        'labels'  => pg_visitors_traffic_labels($keys, $period['grain']),
        'series'  => array(
            array('label' => lang('Visits'), 'data' => pg_visitors_traffic_values($figures['series'], 'visits', $count, $cut)),
            array('label' => lang('Page views'), 'data' => pg_visitors_traffic_values($figures['series'], 'page_views', $count, $cut)),
        ),
        'notes'   => $figures['notes'],
        'caption' => lang('Visits and page views'),
    );

    if (!empty($figures['previous_series'])) {
        $chart['series'][] = array(
            'label'  => lang('Visits, earlier period'),
            'data'   => pg_visitors_traffic_values($figures['previous_series'], 'visits', $count, null),
            'dashed' => true,
        );
    }

    return pg_visitors_chart('pg_visitors_traffic_chart', $chart);
}

/**
 * Page views a visit, null without a visit. Pure.
 *
 * @param int $views
 * @param int $visits
 * @return float|null
 */
function pg_visitors_traffic_ratio($views, $visits)
{
    if ((int) $visits <= 0) {
        return null;
    }

    return round((int) $views / (int) $visits, 1);
}

/**
 * The last hour bucket that has begun when an hour line ends now: the hours
 * after it are not drawn, not drawn as 0. Null for every other period. Pure.
 *
 * @param array $period pg_visitor_period()
 * @return int|null 0-23
 */
function pg_visitors_traffic_cut(array $period)
{
    if ($period['grain'] !== 'hour') {
        return null;
    }

    $end = (int) $period['to_ts'];

    // A whole day ends at the next midnight; a day cut at the present ends
    // inside it.
    if ($end >= strtotime($period['to'] . ' 00:00:00 +1 day')) {
        return null;
    }

    return (int) date('G', max((int) $period['from_ts'], $end - 1));
}

/**
 * One figure of a series as a list of a given length: missing buckets and
 * the hours after $cut are null. Pure.
 *
 * @param array    $series pg_visitor_series()
 * @param string   $figure visits or page_views
 * @param int      $length
 * @param int|null $cut    pg_visitors_traffic_cut()
 * @return array
 */
function pg_visitors_traffic_values(array $series, $figure, $length, $cut)
{
    $values = array();

    foreach (array_values($series) as $index => $bucket) {
        if ($index >= $length) {
            break;
        }

        $values[] = (($cut !== null) && ($index > $cut)) ? null : (int) $bucket[$figure];
    }

    while (count($values) < $length) {
        $values[] = null;
    }

    return $values;
}

/**
 * The x-axis labels of series keys: the hour, the day, the Monday of a week,
 * or the month and year.
 *
 * @param array  $keys  keys of pg_visitor_series()
 * @param string $grain hour, day, week or month
 * @return array
 */
function pg_visitors_traffic_labels(array $keys, $grain)
{
    $labels = array();

    foreach ($keys as $key) {
        $key = (string) $key;

        if ($grain === 'hour') {
            $labels[] = substr($key, 11, 5);
        } elseif ($grain === 'month') {
            $labels[] = get_month_name_from_number(substr($key, 5, 2)) . ' ' . substr($key, 0, 4);
        } else {
            $labels[] = prepare_form_data_for_output($key, 'date', false);
        }
    }

    return $labels;
}

/**
 * The first and last day of each bucket, cut to the period. Pure.
 *
 * @param array $period pg_visitor_period()
 * @param array $keys   keys of pg_visitor_series() for a day, week or month grain
 * @return array list of array(first Y-m-d, last Y-m-d)
 */
function pg_visitors_traffic_ranges(array $period, array $keys)
{
    $ranges = array();

    foreach ($keys as $key) {
        $key = (string) $key;

        if ($period['grain'] === 'month') {
            $first = $key . '-01';
            $last = date('Y-m-t', strtotime($first . ' 12:00:00'));
        } elseif ($period['grain'] === 'week') {
            $first = $key;
            $last = pg_visitor_period_shift($key, 6);
        } else {
            $first = $last = $key;
        }

        $ranges[] = array(max($first, $period['from']), min($last, $period['to']));
    }

    return $ranges;
}

/**
 * The tooltip line of a bucket's most viewed content, '' for none.
 *
 * @param string $name  the content's label
 * @param int    $views
 * @return string
 */
function pg_visitors_traffic_note($name, $views)
{
    if (((string) $name === '') || ((int) $views <= 0)) {
        return '';
    }

    return lang(array(
        'string' => 'Most viewed: {var:1} ({var:2})',
        'vars'   => array((string) $name, pg_format_number((int) $views, 0)),
    ));
}

/**
 * The tooltip line of every bucket: the busiest content of each hour of a
 * one-day period, of each day, week or month otherwise; none past
 * PG_VISITORS_TRAFFIC_NOTE_BUCKETS buckets.
 *
 * @param array $period pg_visitor_period()
 * @param array $keys   keys of the period's pg_visitor_series()
 * @return array list of text
 */
function pg_visitors_traffic_notes(array $period, array $keys)
{
    if ($period['grain'] === 'hour') {
        $notes = array();

        foreach (pg_visitor_top_content_by_hour($period['from']) as $top) {
            $notes[] = is_array($top) ? pg_visitors_traffic_note($top['name'], $top['cnt']) : '';
        }

        return $notes;
    }

    if (count($keys) > PG_VISITORS_TRAFFIC_NOTE_BUCKETS) {
        return array();
    }

    $notes = array();

    foreach (pg_visitors_traffic_ranges($period, $keys) as $range) {
        $top = pg_visitor_top_content($range[0], $range[1], 1);
        $notes[] = isset($top[0]) ? pg_visitors_traffic_note($top[0]['label'], $top[0]['views']) : '';
    }

    return $notes;
}
