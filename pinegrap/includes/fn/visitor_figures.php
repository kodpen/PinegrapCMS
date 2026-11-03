<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The visitor figures of a window: totals, a breakdown by one dimension,
 * the traffic series and the most viewed content, for the Visitors screen,
 * the external API and the dashboard cards.
 *
 * Visits and page views by hour come from visitor_stats_hourly and content
 * from visitor_content_hourly. The figures a visit carries (forms, orders,
 * where it came from) come from the daily summary (visitor_stats_daily) for
 * the days it holds and from the raw visitors rows for the rest: today, the
 * days after the summarized run and the days before it. A day is read from
 * one source only, so a summarized day whose rows are still there is never
 * counted twice.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/**
 * The run of summarized days: first and last day of the contiguous days
 * ending at the most recent one. Pure.
 *
 * @param array $dates Y-m-d days with a totals row, in any order
 * @return array|null array('first' => Y-m-d, 'last' => Y-m-d), null for none
 */
function pg_visitor_summary_run($dates)
{
    $days = array();

    foreach ((array) $dates as $date) {
        $date = (string) $date;

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) && (date('Y-m-d', strtotime($date . ' 12:00:00')) === $date)) {
            $days[$date] = true;
        }
    }

    if (count($days) === 0) {
        return null;
    }

    krsort($days);
    $days = array_keys($days);

    $last = $days[0];
    $first = $last;

    // Noon steps keep a clock change from skipping or repeating a day.
    foreach ($days as $index => $date) {
        if ($index === 0) {
            continue;
        }

        if (date('Y-m-d', strtotime($first . ' 12:00:00 -1 day')) !== $date) {
            break;
        }

        $first = $date;
    }

    return array('first' => $first, 'last' => $last);
}

/**
 * The run of summarized days the summary holds now.
 *
 * A day counts when its totals row (dimension '') is there: it is written
 * last, so a day whose writing was cut off does not count.
 *
 * @return array|null array('first' => Y-m-d, 'last' => Y-m-d), null for none
 */
function pg_visitor_summary_range()
{
    if (!function_exists('pg_visitor_retention_ready') || !pg_visitor_retention_ready()) {
        return null;
    }

    $dates = db_values("SELECT DISTINCT stat_date FROM visitor_stats_daily WHERE dimension = '' ORDER BY stat_date DESC");

    return pg_visitor_summary_run(is_array($dates) ? $dates : array());
}

/**
 * First and last moment of a window given as days (Y-m-d, whole days) or
 * as Unix timestamps (the last one counted). Pure.
 *
 * @param string|int $from
 * @param string|int $to
 * @return array|null array(first moment, last moment), null for bad input
 */
function pg_visitor_window_bounds($from, $to)
{
    $bound = function ($value, $end) {
        if (is_int($value) || (is_string($value) && ctype_digit($value))) {
            return (int) $value;
        }

        $value = (string) $value;

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        $start = strtotime($value . ' 00:00:00');

        if (($start === false) || (date('Y-m-d', $start) !== $value)) {
            return null;
        }

        return $end ? ((int) strtotime('+1 day', $start) - 1) : (int) $start;
    };

    $first = $bound($from, false);
    $last = $bound($to, true);

    if (($first === null) || ($last === null)) {
        return null;
    }

    return array($first, $last);
}

/**
 * A window cut into the days the summary answers and the moments the raw
 * rows answer. Pure.
 *
 * A day comes from the summary when it is whole in the window, inside the
 * run and before today; every other day, or the part of it in the window,
 * from the rows. Neighbouring days of one source are one part.
 *
 * @param int        $from  First moment
 * @param int        $to    Last moment counted
 * @param array|null $run   pg_visitor_summary_run()
 * @param string     $today Y-m-d by the site's clock
 * @return array list of array('source' => summary|raw, 'first', 'last'
 *               (Y-m-d), 'from', 'to' (moments), 'days' => int)
 */
function pg_visitor_day_split($from, $to, $run, $today)
{
    $from = (int) $from;
    $to = (int) $to;

    if ($from > $to) {
        return array();
    }

    $parts = array();
    $date = date('Y-m-d', $from);
    $last = date('Y-m-d', $to);

    while ($date <= $last) {
        $day_start = (int) strtotime($date . ' 00:00:00');
        $day_end = (int) strtotime('+1 day', $day_start) - 1;

        $whole = ($from <= $day_start) && ($to >= $day_end);

        $source = ($whole && is_array($run) && ($date >= $run['first']) && ($date <= $run['last']) && ($date < (string) $today))
            ? 'summary'
            : 'raw';

        $moment_from = max($from, $day_start);
        $moment_to = min($to, $day_end);

        $count = count($parts);

        if (($count > 0) && ($parts[$count - 1]['source'] === $source)) {
            $parts[$count - 1]['last'] = $date;
            $parts[$count - 1]['to'] = $moment_to;
            $parts[$count - 1]['days']++;
        } else {
            $parts[] = array(
                'source' => $source,
                'first' => $date,
                'last' => $date,
                'from' => $moment_from,
                'to' => $moment_to,
                'days' => 1,
            );
        }

        $date = date('Y-m-d', strtotime($date . ' 12:00:00 +1 day'));
    }

    return $parts;
}

/**
 * Days of each source in a split. Pure.
 *
 * @param array $parts pg_visitor_day_split()
 * @return array array('summary' => int, 'raw' => int)
 */
function pg_visitor_day_counts($parts)
{
    $counts = array('summary' => 0, 'raw' => 0);

    foreach ((array) $parts as $part) {
        $counts[$part['source']] += (int) $part['days'];
    }

    return $counts;
}

/**
 * The window cut into summary and raw parts as the summary stands now.
 *
 * @param string|int $from Y-m-d or first moment
 * @param string|int $to   Y-m-d or last moment counted
 * @return array pg_visitor_day_split(), empty for bad input
 */
function pg_visitor_day_sources($from, $to)
{
    $bounds = pg_visitor_window_bounds($from, $to);

    if ($bounds === null) {
        return array();
    }

    return pg_visitor_day_split($bounds[0], $bounds[1], pg_visitor_summary_range(), date('Y-m-d'));
}

/**
 * The WHERE condition of the summary parts and of the raw parts.
 *
 * @param array $parts pg_visitor_day_split()
 * @return array array('summary' => SQL or '', 'raw' => SQL or '')
 */
function pg_visitor_parts_sql($parts)
{
    $summary = array();
    $raw = array();

    foreach ((array) $parts as $part) {
        if ($part['source'] === 'summary') {
            $summary[] = "((stat_date >= '" . e($part['first']) . "') AND (stat_date <= '" . e($part['last']) . "'))";
        } else {
            $raw[] = "((start_timestamp >= " . (int) $part['from'] . ") AND (start_timestamp <= " . (int) $part['to'] . "))";
        }
    }

    return array(
        'summary' => (count($summary) > 0) ? '(' . implode(' OR ', $summary) . ')' : '',
        'raw' => (count($raw) > 0) ? '(' . implode(' OR ', $raw) . ')' : '',
    );
}

/**
 * The summary's figure sums, as SQL over visitor_stats_daily rows.
 *
 * @return string
 */
function pg_visitor_summary_table_sums_sql()
{
    $sums = array();

    foreach (pg_visitor_summary_figures() as $figure) {
        $sums[] = "COALESCE(SUM(" . $figure . "), 0) AS " . $figure;
    }

    return implode(', ', $sums);
}

/**
 * Every figure of a row as a whole number; a missing one is 0. Pure.
 *
 * @param array|false|null $row
 * @return array figure => int
 */
function pg_visitor_figures_of($row)
{
    $out = array();

    foreach (pg_visitor_summary_figures() as $figure) {
        $out[$figure] = (is_array($row) && isset($row[$figure])) ? (int) $row[$figure] : 0;
    }

    return $out;
}

/**
 * The lists of a dimension from the summary and from the rows added up by
 * value, as the database groups them: without regard to case, the first
 * spelling kept. A summary rest row (is_rest) is never listed; it counts in
 * the totals only. Largest first, then by value. Pure.
 *
 * @param array $lists list of row lists, each row array('value', figure => int)
 * @param int   $limit
 * @return array list of array('value' => string, figure => int, ...)
 */
function pg_visitor_merge_rows($lists, $limit)
{
    $merged = array();

    foreach ((array) $lists as $rows) {
        foreach ((array) $rows as $row) {
            if (!empty($row['is_rest'])) {
                continue;
            }

            $value = (string) $row['value'];
            $key = function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);

            if (!isset($merged[$key])) {
                $merged[$key] = array('value' => $value) + pg_visitor_figures_of(null);
            }

            foreach (pg_visitor_figures_of($row) as $figure => $number) {
                $merged[$key][$figure] += $number;
            }
        }
    }

    $merged = array_values($merged);

    usort($merged, function ($a, $b) {
        if ($a['visits'] !== $b['visits']) {
            return ($a['visits'] > $b['visits']) ? -1 : 1;
        }

        return strcmp($a['value'], $b['value']);
    });

    return array_slice($merged, 0, max(1, (int) $limit));
}

/**
 * What a figures answer says about its sources.
 *
 * @param array $parts pg_visitor_day_split()
 * @param int   $summary_visits Visits the summary parts held
 * @return array array('summary_days', 'raw_days', 'summary_to' => Y-m-d|null)
 */
function pg_visitor_sources_note($parts, $summary_visits)
{
    $counts = pg_visitor_day_counts($parts);
    $summary_to = null;

    if ($summary_visits > 0) {
        foreach ($parts as $part) {
            if ($part['source'] === 'summary') {
                $summary_to = $part['last'];
            }
        }
    }

    return array('summary_days' => $counts['summary'], 'raw_days' => $counts['raw'], 'summary_to' => $summary_to);
}

/**
 * Visits, page views, forms and orders of a window.
 *
 * @param string|int $from Y-m-d or first moment
 * @param string|int $to   Y-m-d or last moment counted
 * @return array figure => int, plus summary_days, raw_days, summary_to
 */
function pg_visitor_totals($from, $to)
{
    $parts = pg_visitor_day_sources($from, $to);

    return pg_visitor_totals_of_parts($parts);
}

/**
 * pg_visitor_totals() over a split already made.
 *
 * @param array $parts pg_visitor_day_split()
 * @return array
 */
function pg_visitor_totals_of_parts($parts)
{
    $where = pg_visitor_parts_sql($parts);

    $summary = pg_visitor_figures_of(null);
    $raw = pg_visitor_figures_of(null);

    if ($where['summary'] !== '') {
        $summary = pg_visitor_figures_of(db_item("SELECT " . pg_visitor_summary_table_sums_sql() . "
            FROM visitor_stats_daily
            WHERE (dimension = '') AND " . $where['summary']));
    }

    if ($where['raw'] !== '') {
        $raw = pg_visitor_figures_of(db_item("SELECT " . pg_visitor_summary_sums_sql() . "
            FROM visitors
            WHERE " . $where['raw']));
    }

    $total = array();

    foreach ($summary as $figure => $number) {
        $total[$figure] = $number + $raw[$figure];
    }

    return $total + pg_visitor_sources_note($parts, $summary['visits']);
}

/**
 * Visits of a window counted by one dimension, largest first, with the
 * window's totals.
 *
 * $dimension is a key of pg_visitor_summary_dimensions(): its raw SQL counts
 * the rows, its name finds the summary rows. An empty value is a visit that
 * had none and is listed as ''.
 *
 * @param string     $dimension
 * @param string|int $from  Y-m-d or first moment
 * @param string|int $to    Y-m-d or last moment counted
 * @param int        $limit
 * @return array|false array('totals' => figures, 'rows' => list of
 *                     array('value', figure => int), 'summary_days',
 *                     'raw_days', 'summary_to'); false for an unknown
 *                     dimension
 */
function pg_visitor_breakdown($dimension, $from, $to, $limit = 50)
{
    $dimensions = pg_visitor_summary_dimensions();

    if (!isset($dimensions[$dimension])) {
        return false;
    }

    $limit = max(1, (int) $limit);
    $parts = pg_visitor_day_sources($from, $to);
    $where = pg_visitor_parts_sql($parts);

    $totals = pg_visitor_totals_of_parts($parts);
    $note = array(
        'summary_days' => $totals['summary_days'],
        'raw_days' => $totals['raw_days'],
        'summary_to' => $totals['summary_to'],
    );
    unset($totals['summary_days'], $totals['raw_days'], $totals['summary_to']);

    // With both sources a value can be outside the top of one and inside
    // the top of the sum: each list is read deeper than the limit.
    $both = ($where['summary'] !== '') && ($where['raw'] !== '');
    $depth = $both ? max(500, $limit * 4) : $limit;

    $lists = array();

    if ($where['summary'] !== '') {
        $lists[] = db_items("SELECT dim_value AS value, " . pg_visitor_summary_table_sums_sql() . "
            FROM visitor_stats_daily
            WHERE (dimension = '" . e($dimension) . "') AND (is_rest = 0) AND " . $where['summary'] . "
            GROUP BY value
            ORDER BY visits DESC, value ASC
            LIMIT " . (int) $depth);
    }

    if ($where['raw'] !== '') {
        $expression = $dimensions[$dimension];

        $lists[] = db_items("SELECT " . $expression . " AS value, " . pg_visitor_summary_sums_sql() . "
            FROM visitors" . pg_visitor_text_joins($expression) . "
            WHERE " . $where['raw'] . "
            GROUP BY value
            ORDER BY visits DESC, value ASC
            LIMIT " . (int) $depth);
    }

    foreach ($lists as $index => $list) {
        $lists[$index] = is_array($list) ? $list : array();
    }

    return array(
        'totals' => $totals,
        'rows' => pg_visitor_merge_rows($lists, $limit),
    ) + $note;
}

/**
 * Visits and page views of the hourly rollup in buckets of a grain, empty
 * buckets included, in order. Pure.
 *
 * Keys: 'Y-m-d H:00' for hour, 'Y-m-d' for day, the Monday's Y-m-d for
 * week, 'Y-m' for month; another grain is day.
 *
 * @param array  $stats pg_visitor_stats_range(): date => hour => figures
 * @param string $first Y-m-d
 * @param string $last  Y-m-d
 * @param string $grain hour, day, week or month
 * @return array key => array('visits' => int, 'page_views' => int)
 */
function pg_visitor_series($stats, $first, $last, $grain)
{
    if (!in_array($grain, array('hour', 'day', 'week', 'month'), true)) {
        $grain = 'day';
    }

    $out = array();
    $date = (string) $first;

    while (($date !== '') && ($date <= (string) $last)) {
        $noon = strtotime($date . ' 12:00:00');

        if ($noon === false) {
            break;
        }

        $hours = isset($stats[$date]) ? (array) $stats[$date] : array();

        for ($hour = 0; $hour < 24; $hour++) {
            if ($grain === 'hour') {
                $key = $date . ' ' . sprintf('%02d', $hour) . ':00';
            } elseif ($grain === 'week') {
                $key = date('Y-m-d', strtotime('-' . ((int) date('N', $noon) - 1) . ' days', $noon));
            } elseif ($grain === 'month') {
                $key = substr($date, 0, 7);
            } else {
                $key = $date;
            }

            if (!isset($out[$key])) {
                $out[$key] = array('visits' => 0, 'page_views' => 0);
            }

            if (isset($hours[$hour])) {
                $out[$key]['visits'] += (int) $hours[$hour]['visitors'];
                $out[$key]['page_views'] += (int) $hours[$hour]['page_views'];
            }
        }

        $date = date('Y-m-d', strtotime('+1 day', $noon));
    }

    return $out;
}

/**
 * The traffic series of a window from the hourly rollup.
 *
 * @param string $first Y-m-d
 * @param string $last  Y-m-d
 * @param string $grain hour, day, week or month
 * @return array pg_visitor_series()
 */
function pg_visitor_traffic($first, $last, $grain)
{
    return pg_visitor_series(pg_visitor_stats_range($first, $last), $first, $last, $grain);
}

/**
 * The most viewed pages and items of a window from the content rollup,
 * labelled (pg_visitor_label_content_rows()).
 *
 * @param string $first Y-m-d
 * @param string $last  Y-m-d
 * @param int    $limit
 * @return array
 */
function pg_visitor_content($first, $last, $limit = 10)
{
    return pg_visitor_top_content($first, $last, $limit);
}
