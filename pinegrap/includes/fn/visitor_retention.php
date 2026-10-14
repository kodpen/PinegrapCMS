<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Keeping the visitors table to a size. One row is written for every visit
 * and nothing ever removed them, so a busy site carried millions of rows and
 * close to a gigabyte in that one table: every backup copied it, every
 * OPTIMIZE or engine change rewrote it, and the queries on it slowed down
 * with it.
 *
 * Here: a retention period for the raw rows (config.visitor_retention_days,
 * 0 keeps them all), a daily summary written before a day's rows go
 * (config.visitor_summary, table visitor_stats_daily), the removal itself in
 * short chunks the general job and the Database Engine screen run, the move
 * of a table still on MyISAM to InnoDB once it is down to its period, and the
 * figures that screen and System Status show about the table.
 *
 * The hourly rollups (visitor_stats_hourly, visitor_content_hourly) are not
 * touched: they are what the dashboard and the traffic report read, and they
 * never depended on the raw rows once a visit was counted. The one exception
 * is the backfill that fills them from history after an upgrade; rows it has
 * not reached yet are kept until it has.
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
 * Values of one dimension kept per day; the rest are added up into one row.
 *
 * A dimension such as the referring host or the city can have thousands of
 * values on a busy day, most of them seen once. Keeping each would make the
 * summary grow nearly as fast as the table it replaces.
 */
define('PG_VISITOR_SUMMARY_TOP', 100);

/**
 * Days a retention period may be set to, other than 0 (keep every row).
 *
 * The lower bound keeps a visit that is still going on, and the order or form
 * it may lead to in the next hours, away from the removal.
 */
define('PG_VISITOR_RETENTION_MIN_DAYS', 7);
define('PG_VISITOR_RETENTION_MAX_DAYS', 3650);

/**
 * True when the columns and the table this module works with are there.
 */
function pg_visitor_retention_ready()
{
    return pg_schema_has('config', 'visitor_retention_days')
        && pg_schema_has('config', 'visitor_summary')
        && pg_schema_has('visitor_stats_daily');
}

/**
 * The two settings, or false before the upgrade that adds them.
 *
 * @return array('days' => int, 'summary' => bool)|false
 */
function pg_visitor_retention_settings()
{
    if (!pg_visitor_retention_ready()) {
        return false;
    }

    $row = db_item("SELECT visitor_retention_days, visitor_summary FROM config");

    if (!is_array($row)) {
        return false;
    }

    return array(
        'days' => (int) $row['visitor_retention_days'],
        'summary' => ((int) $row['visitor_summary'] === 1),
    );
}

/**
 * A retention period as typed into the settings, or null when it is not one.
 *
 * Empty and 0 both mean "keep every row". Anything else has to be a whole
 * number of days within the bounds above.
 *
 * @param mixed $value
 * @return int|null
 */
function pg_visitor_retention_days_from_input($value)
{
    $value = trim((string) $value);

    if (($value === '') || ($value === '0')) {
        return 0;
    }

    if (preg_match('/^[0-9]{1,5}$/', $value) !== 1) {
        return null;
    }

    $days = (int) $value;

    if (($days < PG_VISITOR_RETENTION_MIN_DAYS) || ($days > PG_VISITOR_RETENTION_MAX_DAYS)) {
        return null;
    }

    return $days;
}

/**
 * The first moment that is kept: midnight, by the site's clock, of the day
 * $days days before the day of $now. Rows that started earlier are removed.
 *
 * Whole days, so a day is either summarized and removed whole or left whole,
 * and the summary never holds half a day. Counted with strtotime() rather
 * than 86400-second steps so a daylight saving change does not move the
 * boundary off midnight.
 *
 * @param int $days 0 for no limit
 * @param int $now
 * @return int Unix time, 0 when nothing is to be removed
 */
function pg_visitor_retention_cutoff($days, $now)
{
    $days = (int) $days;

    if ($days <= 0) {
        return 0;
    }

    $midnight = strtotime(date('Y-m-d', (int) $now) . ' 00:00:00');

    return (int) strtotime('-' . $days . ' days', $midnight);
}

/**
 * The device a user agent belongs to, as SQL over the named column:
 * 'desktop', 'mobile', 'tablet', or '' when there is no user agent.
 *
 * Coarse on purpose. Tablets are told apart first because an iPad and an
 * Android tablet would otherwise read as mobile; an Android phone carries
 * "Mobile" in its user agent and a tablet does not.
 *
 * @param string $column
 * @return string
 */
function pg_visitor_device_sql($column)
{
    $c = (string) $column;

    return "CASE"
        . " WHEN ($c = '') THEN ''"
        . " WHEN ($c LIKE '%iPad%' OR $c LIKE '%Tablet%' OR ($c LIKE '%Android%' AND $c NOT LIKE '%Mobile%')) THEN 'tablet'"
        . " WHEN ($c LIKE '%Mobi%' OR $c LIKE '%iPhone%' OR $c LIKE '%Android%') THEN 'mobile'"
        . " ELSE 'desktop' END";
}

/**
 * The paid / organic channel of a visit as SQL: 'pay_per_click' when the
 * tracking code carries the pay-per-click flag of the SEO settings,
 * 'organic' when a search engine referred it, otherwise ''.
 *
 * With no flag set nothing counts as paid: an empty pattern would match
 * every visit (the API counts it the same way).
 *
 * @param string $prefix Table name or alias with its dot, or ''
 * @return string
 */
function pg_visitor_channel_sql($prefix = '')
{
    $flag = defined('PAY_PER_CLICK_FLAG') ? trim((string) PAY_PER_CLICK_FLAG) : '';

    return "CASE"
        . (($flag !== '') ? " WHEN (" . $prefix . "tracking_code LIKE '%" . e(escape_like($flag)) . "%') THEN 'pay_per_click'" : '')
        . " WHEN (" . $prefix . "referring_search_engine <> '') THEN 'organic' ELSE '' END";
}

/**
 * What a day's visits are counted by in the summary: name => SQL over a
 * `visitors` row.
 *
 * The names are the visitor report's own summarize-by keys, so that report
 * can ask the summary for exactly what it groups by. The full referrer
 * address and the postcode are left out: nearly every visit has a value of
 * its own there, and a day's worth of them would be as large as the rows.
 *
 * @return array<string,string>
 */
function pg_visitor_summary_dimensions()
{
    $has_user_agent = pg_schema_has('visitors', 'user_agent');

    return array(
        'referring_host_name' => 'referring_host_name',
        'referring_search_engine' => 'referring_search_engine',
        'referring_search_terms' => 'referring_search_terms',
        'pay_per_click_organic' => pg_visitor_channel_sql(),
        'landing_page_name' => 'landing_page_name',
        'first_visit' => 'first_visit',
        'tracking_code' => 'tracking_code',
        'affiliate_code' => 'affiliate_code',
        'utm_source' => 'utm_source',
        'utm_medium' => 'utm_medium',
        'utm_campaign' => 'utm_campaign',
        'utm_term' => 'utm_term',
        'utm_content' => 'utm_content',
        'custom_form_name' => 'custom_form_name',
        'country' => 'country',
        'state' => 'state',
        'city' => 'city',
        'site_search_terms' => 'site_search_terms',
        'currency_code' => 'currency_code',
        'device' => $has_user_agent ? pg_visitor_device_sql('user_agent') : "''",
    );
}

/**
 * The figures a summary row carries, as SQL over `visitors` rows. The same
 * sums the visitor report screen and the API make: the order and form
 * columns are 0 or 1 on a visit, so their sum is a count.
 *
 * @return string
 */
function pg_visitor_summary_sums_sql()
{
    return "COUNT(*) AS visits,
        COALESCE(SUM(page_views), 0) AS page_views,
        COALESCE(SUM(custom_form_submitted), 0) AS forms_submitted,
        COALESCE(SUM(order_created), 0) AS orders_created,
        COALESCE(SUM(order_retrieved), 0) AS orders_retrieved,
        COALESCE(SUM(order_checked_out), 0) AS orders_checked_out,
        COALESCE(SUM(order_completed), 0) AS orders_completed,
        COALESCE(SUM(order_total), 0) AS order_total";
}

/**
 * The figure columns of visitor_stats_daily, in the order the summary writes
 * them.
 *
 * @return array
 */
function pg_visitor_summary_figures()
{
    return array('visits', 'page_views', 'forms_submitted', 'orders_created', 'orders_retrieved', 'orders_checked_out', 'orders_completed', 'order_total');
}

/**
 * The top values of one dimension and the row the remaining values add up
 * to.
 *
 * $rows is what the grouped query returned, largest first and already cut
 * to the top; $total is the day's totals. Every visit has exactly one value
 * of every dimension (an empty one included), so what the top values do not
 * cover is what the rest row carries. No rest row when nothing is left.
 *
 * @param array $rows  list of array('value' => string, figure => int, ...)
 * @param array $total figure => int
 * @return array list of array('value', 'rest' => 0|1, figure => int, ...)
 */
function pg_visitor_summary_with_rest($rows, $total)
{
    $figures = pg_visitor_summary_figures();

    $out = array();
    $covered = array_fill_keys($figures, 0);

    foreach ((array) $rows as $row) {
        $line = array('value' => (string) $row['value'], 'rest' => 0);

        foreach ($figures as $figure) {
            $line[$figure] = isset($row[$figure]) ? (int) $row[$figure] : 0;
            $covered[$figure] += $line[$figure];
        }

        $out[] = $line;
    }

    $rest_visits = (isset($total['visits']) ? (int) $total['visits'] : 0) - $covered['visits'];

    if ($rest_visits > 0) {
        $line = array('value' => '', 'rest' => 1);

        foreach ($figures as $figure) {
            $line[$figure] = (isset($total[$figure]) ? (int) $total[$figure] : 0) - $covered[$figure];
        }

        $out[] = $line;
    }

    return $out;
}

/**
 * The last day the summary holds, as Y-m-d, or '' when it holds none.
 *
 * The day's totals row (dimension '') is written last, after every other row
 * of the day, so a day whose writing was cut off is not counted here and is
 * written again from the start on the next run.
 *
 * @return string
 */
function pg_visitor_summary_through()
{
    if (!pg_visitor_retention_ready()) {
        return '';
    }

    $through = db_value("SELECT MAX(stat_date) FROM visitor_stats_daily WHERE dimension = ''");

    return (is_string($through) && ($through !== '') && ($through !== '0000-00-00')) ? $through : '';
}

/**
 * Where the raw rows take over from the summary: midnight of the day after
 * the last summarized day, or 0 when nothing is summarized.
 *
 * A report adds the summary for the days before this moment to the rows from
 * this moment on, and must leave out any raw row before it: the summary
 * already counted that day whole, and a summarized day's rows can still be
 * there for the minutes it takes to remove them.
 *
 * @return int
 */
function pg_visitor_summary_raw_from()
{
    $through = pg_visitor_summary_through();

    if ($through === '') {
        return 0;
    }

    return (int) strtotime('+1 day', strtotime($through . ' 00:00:00'));
}

/**
 * The summarized days of a visitor report window, grouped the way the visitor
 * report groups the raw rows (view_visitor_report.php).
 *
 * The summary holds each day once per dimension, so it can answer a report
 * grouped by year, month and day plus at most one dimension, with no filters.
 * Values outside a day's top values come back as one "(other values)" group.
 *
 * @param int   $from      First moment of the window
 * @param int   $to        Last moment of the window
 * @param array $groupings The report's summarize-by keys, in order
 * @return array|false Rows keyed sb1, sb2, ... plus row_count, page_views,
 *                     custom_form_submitted, order_created, order_retrieved,
 *                     order_checked_out, order_completed and order_total;
 *                     false when the summary cannot answer the grouping
 */
function pg_visitor_summary_report_rows($from, $to, $groupings)
{
    $through = pg_visitor_summary_through();

    if ($through === '') {
        return array();
    }

    $dates = array('year' => '%Y', 'month' => '%m', 'day' => '%d');
    $dimensions = pg_visitor_summary_dimensions();

    $dimension = '';
    $select = array();
    $aliases = array();

    foreach (array_values($groupings) as $index => $grouping) {

        $alias = 'sb' . ($index + 1);
        $aliases[] = $alias;

        if (isset($dates[$grouping])) {

            $select[] = "DATE_FORMAT(stat_date, '" . $dates[$grouping] . "') AS " . $alias;

        } elseif (isset($dimensions[$grouping]) && ($dimension === '')) {

            $dimension = $grouping;

            // The report shows the channel by these two names.
            $value = ($grouping === 'pay_per_click_organic')
                ? "CASE dim_value WHEN 'pay_per_click' THEN 'Pay Per Click' WHEN 'organic' THEN 'Organic' ELSE '' END"
                : "dim_value";

            $select[] = "IF(is_rest = 1, '" . e(lang('(other values)')) . "', " . $value . ") AS " . $alias;

        } else {
            return false;
        }
    }

    $first = date('Y-m-d', (int) $from);
    $last = min(date('Y-m-d', (int) $to), $through);

    if ($first > $last) {
        return array();
    }

    $rows = db_items(
        "SELECT
            " . ((count($select) > 0) ? implode(', ', $select) . ', ' : '') . "
            SUM(visits) AS row_count,
            SUM(page_views) AS page_views,
            SUM(forms_submitted) AS custom_form_submitted,
            SUM(orders_created) AS order_created,
            SUM(orders_retrieved) AS order_retrieved,
            SUM(orders_checked_out) AS order_checked_out,
            SUM(orders_completed) AS order_completed,
            SUM(order_total) AS order_total
        FROM visitor_stats_daily
        WHERE
            (dimension = '" . e($dimension) . "')
            AND (stat_date >= '" . e($first) . "')
            AND (stat_date <= '" . e($last) . "')"
        . ((count($aliases) > 0) ? "
        GROUP BY " . implode(', ', $aliases) : ''));

    $out = array();

    foreach ((array) $rows as $row) {
        // The sums of no rows at all: nothing summarized in the window.
        if ($row['row_count'] === null) {
            continue;
        }
        $out[] = $row;
    }

    return $out;
}

/**
 * Write one day of the summary from the raw rows of that day.
 *
 * The day's rows are removed first, so a day can be written again after a
 * run that died halfway through it. The totals row goes last: its presence
 * is what marks the day as done (pg_visitor_summary_through()).
 *
 * @param string $date Y-m-d by the site's clock
 * @return int Visits the day held
 */
function pg_visitor_summarize_day($date)
{
    $from = strtotime($date . ' 00:00:00');
    $to = strtotime('+1 day', $from);

    $range = "(start_timestamp >= " . (int) $from . ") AND (start_timestamp < " . (int) $to . ")";

    $sums = pg_visitor_summary_sums_sql();

    db("DELETE FROM visitor_stats_daily WHERE stat_date = '" . e($date) . "'");

    // Ids grow with time, so a day's rows are one stretch of the primary key.
    // Reading that stretch in key order is about twice as fast as visiting
    // each row through the start_timestamp index, once per dimension. A day
    // whose stretch is mostly other days' rows (rows written out of order, an
    // import) is read through the index instead.
    $span = db_item("SELECT COUNT(*) AS n, MIN(id) AS lo, MAX(id) AS hi FROM visitors WHERE " . $range);

    if (!is_array($span) || ((int) $span['n'] === 0)) {
        return 0;
    }

    $source = "visitors";

    if (((int) $span['hi'] - (int) $span['lo'] + 1) <= max(2 * (int) $span['n'], (int) $span['n'] + 10000)) {
        $source = "visitors FORCE INDEX (PRIMARY)";
        $range = "(id BETWEEN " . (int) $span['lo'] . " AND " . (int) $span['hi'] . ") AND " . $range;
    }

    $total = db_item("SELECT " . $sums . " FROM " . $source . " WHERE " . $range);

    if (!is_array($total) || ((int) $total['visits'] === 0)) {
        return 0;
    }

    $figures = pg_visitor_summary_figures();

    $insert = "INSERT INTO visitor_stats_daily (stat_date, dimension, dim_value, is_rest, " . implode(', ', $figures) . ") VALUES ";

    $values = function ($dimension, $line) use ($date, $figures) {
        $sql = "('" . e($date) . "', '" . e($dimension) . "', '" . e(mb_substr((string) $line['value'], 0, 255)) . "', " . (int) $line['rest'];
        foreach ($figures as $figure) {
            $sql .= ", " . (int) $line[$figure];
        }
        return $sql . ")";
    };

    foreach (pg_visitor_summary_dimensions() as $dimension => $expression) {

        // Grouped by the value, largest first. A secondary order on the value
        // makes the cut at the top deterministic when counts tie.
        $rows = db_items(
            "SELECT " . $expression . " AS value, " . $sums . "
            FROM " . $source . "
            WHERE " . $range . "
            GROUP BY value
            ORDER BY visits DESC, value
            LIMIT " . (int) PG_VISITOR_SUMMARY_TOP);

        $lines = pg_visitor_summary_with_rest($rows, $total);

        if (count($lines) == 0) {
            continue;
        }

        $sql = array();

        foreach ($lines as $line) {
            $sql[] = $values($dimension, $line);
        }

        db($insert . implode(', ', $sql));
    }

    db($insert . $values('', array('value' => '', 'rest' => 0) + array_map('intval', $total)));

    return (int) $total['visits'];
}

/**
 * The lock that keeps two runs of the cleanup apart: the general job and the
 * button on the Database Engine screen can start one at the same moment.
 *
 * A named lock of the database server, scoped by the database's name because
 * the name is shared by every database on the server.
 *
 * @return string SQL expression for the name
 */
function pg_visitor_cleanup_lock_sql()
{
    return "LEFT(CONCAT('pg_visitor_cleanup.', DATABASE()), 64)";
}

/**
 * The file the general job reads to decide whether a pass is due.
 *
 * A file rather than a column: losing it (the cleanup of data/temp) costs
 * one extra look, nothing more.
 *
 * @return string
 */
function pg_visitor_cleanup_marker()
{
    return PG_FUNCTIONS_DIR . '/data/temp/visitor_cleanup.json';
}

/**
 * What the last run left behind, from the marker file.
 *
 * @return array('checked_at' => int, 'backlog' => bool, 'deleted' => int,
 *               'summarized' => int, 'seconds' => float)
 */
function pg_visitor_cleanup_last()
{
    $last = @json_decode((string) @file_get_contents(pg_visitor_cleanup_marker()), true);

    if (!is_array($last)) {
        $last = array();
    }

    return array(
        'checked_at' => isset($last['checked_at']) ? (int) $last['checked_at'] : 0,
        'backlog' => !empty($last['backlog']),
        'deleted' => isset($last['deleted']) ? (int) $last['deleted'] : 0,
        'summarized' => isset($last['summarized']) ? (int) $last['summarized'] : 0,
        'seconds' => isset($last['seconds']) ? (float) $last['seconds'] : 0.0,
    );
}

/**
 * Forget when the cleanup last looked, so the next tick of the general job
 * runs it: the settings changed, or a run was cut short.
 */
function pg_visitor_cleanup_reset()
{
    $marker = pg_visitor_cleanup_marker();

    if (file_exists($marker)) {
        @unlink($marker);
    }
}

/**
 * Whether the general job should run a pass now.
 *
 * Every tick while rows are waiting (a run ran out of time with work left),
 * otherwise once an hour: once the table is down to its period, all an hour
 * adds is the rows that crossed the boundary since.
 *
 * @return bool
 */
function pg_visitor_cleanup_due()
{
    $settings = pg_visitor_retention_settings();

    if (($settings === false) || ($settings['days'] <= 0)) {
        return false;
    }

    $last = pg_visitor_cleanup_last();

    return $last['backlog'] || ($last['checked_at'] < (time() - 3600));
}

/**
 * Remove the visitor rows older than the retention period, summarizing each
 * day first when the summary is on.
 *
 * Written to be stopped at any point and picked up again:
 *
 * - The summary goes a day at a time and a day is marked done only by its
 *   last row, so a run cut off in the middle writes that day again.
 * - Rows are removed in chunks of $chunk by primary key, each chunk its own
 *   short statement and its own transaction. A visit being written meanwhile
 *   is a row at the far end of the key and is never waited on; nothing locks
 *   the table as a whole the way one large DELETE would.
 * - With the summary on, only days already summarized are removed.
 * - Rows the hourly rollup backfill has not counted yet are kept until it
 *   has; the backfill gets a share of this run first.
 *
 * The budget is wall clock: the run returns after the statement that crosses
 * it, so the caller's wait is about $budget_seconds plus one chunk.
 *
 * @param int $budget_seconds
 * @param int $chunk Rows per DELETE
 * @return array('state' => 'unavailable'|'off'|'busy'|'done'|'more',
 *               'days' => int, 'summary' => bool, 'cutoff' => int,
 *               'summarized' => int, 'deleted' => int, 'seconds' => float)
 */
function pg_visitor_cleanup_run($budget_seconds = 15, $chunk = 5000)
{
    $started = microtime(true);

    $result = array(
        'state' => 'unavailable',
        'days' => 0,
        'summary' => false,
        'cutoff' => 0,
        'summarized' => 0,
        'deleted' => 0,
        'seconds' => 0.0,
    );

    $settings = pg_visitor_retention_settings();

    if ($settings === false) {
        return $result;
    }

    $result['days'] = $settings['days'];
    $result['summary'] = $settings['summary'];

    if ($settings['days'] <= 0) {
        $result['state'] = 'off';
        return $result;
    }

    if ((int) db_value("SELECT GET_LOCK(" . pg_visitor_cleanup_lock_sql() . ", 0)") !== 1) {
        $result['state'] = 'busy';
        return $result;
    }

    // The request that runs this may be the general job under a crontab, or
    // the panel; neither should stop halfway through a statement because the
    // browser went away.
    if (function_exists('ignore_user_abort')) {
        @ignore_user_abort(true);
    }

    $budget_seconds = max(1, (int) $budget_seconds);
    $chunk = max(500, min(50000, (int) $chunk));
    $deadline = $started + $budget_seconds;

    // InnoDB locks only the rows a chunk removes, so new visits are written
    // while it runs. MyISAM locks the whole table for each statement, and
    // every page view waits for the chunk in front of it: there the chunks
    // are smaller and the table is left free for at least as long as the
    // last chunk held it.
    $row_locks = (strtolower((string) db_value(
        "SELECT ENGINE FROM information_schema.TABLES WHERE (TABLE_SCHEMA = DATABASE()) AND (TABLE_NAME = 'visitors')")) === 'innodb');

    if (!$row_locks) {
        $chunk = min($chunk, 1000);
    }

    // The summary takes at most three fifths of the run, so that a long
    // backlog of days still sees rows removed on every run rather than only
    // once the summary has caught up.
    $summary_deadline = $started + max(1, $budget_seconds * 0.6);

    $cutoff = pg_visitor_retention_cutoff($settings['days'], time());
    $result['cutoff'] = $cutoff;

    // -- The hourly rollup backfill ------------------------------------------
    //
    // After an upgrade the rollups are filled from history by id. A row the
    // backfill has not reached is the only record of that visit for the
    // dashboard, so rows above its cursor are left alone until it passes.
    $id_ceiling = null;

    if (function_exists('pg_visitor_backfill_state')) {
        $backfill = pg_visitor_backfill_state();

        if (is_array($backfill) && !$backfill['done'] && ($backfill['max_id'] > 0)) {
            $backfill = pg_visitor_backfill_step(max(1, min(5, (int) floor($deadline - microtime(true)))));

            if (is_array($backfill) && !$backfill['done']) {
                $id_ceiling = (int) $backfill['cursor'];
            }
        }
    }

    // -- The summary ---------------------------------------------------------
    $delete_before = $cutoff;
    $summary_complete = true;

    if ($settings['summary']) {

        $through = pg_visitor_summary_through();
        $summary_complete = false;

        while (microtime(true) < $summary_deadline) {

            $from = ($through !== '') ? (int) strtotime('+1 day', strtotime($through . ' 00:00:00')) : 1;

            // The next day that has rows, so a gap of empty days costs one
            // read and not one per day.
            $next = db_value(
                "SELECT MIN(start_timestamp)
                FROM visitors
                WHERE (start_timestamp >= " . (int) $from . ") AND (start_timestamp < " . (int) $cutoff . ")");

            if (($next === null) || ($next === '') || ((int) $next <= 0)) {
                $summary_complete = true;
                break;
            }

            $day = date('Y-m-d', (int) $next);

            pg_visitor_summarize_day($day);

            $through = $day;
            $result['summarized']++;
        }

        // Only what the summary already holds goes. When it has caught up to
        // the boundary, that is everything before it.
        if (!$summary_complete) {
            $delete_before = ($through !== '') ? min($cutoff, (int) strtotime('+1 day', strtotime($through . ' 00:00:00'))) : 0;
        }
    }

    // -- The removal ---------------------------------------------------------
    $delete_done = ($delete_before <= 0);

    if (!$delete_done) {

        // The last row to go, found once through the start_timestamp index:
        // the latest start before the boundary, then the highest id of that
        // second. Ordering by the time alone lets both engines read the
        // index from its end; adding the id to the order made MyISAM sort
        // every row before the boundary.
        $last_start = (int) db_value(
            "SELECT start_timestamp
            FROM visitors
            WHERE start_timestamp < " . (int) $delete_before . "
            ORDER BY start_timestamp DESC
            LIMIT 1");

        $last_id = ($last_start > 0)
            ? (int) db_value("SELECT MAX(id) FROM visitors WHERE start_timestamp = " . $last_start)
            : 0;

        if ($id_ceiling !== null) {
            $last_id = min($last_id, $id_ceiling);
        }

        if ($last_id <= 0) {
            $delete_done = true;
        }

        // The chunks walk the primary key from its low end, where the oldest
        // rows are. Each one is a window of ids read from the key alone,
        // then removed by that range: the server cannot pick another index
        // for it, and every chunk takes the same short time however large
        // the table is. A row in the window that started after the boundary
        // (written out of order) stays.
        $cursor = 0;

        while (!$delete_done && (microtime(true) < $deadline)) {

            $upper = (int) db_value(
                "SELECT MAX(id)
                FROM (
                    SELECT id
                    FROM visitors
                    WHERE (id > " . $cursor . ") AND (id <= " . $last_id . ")
                    ORDER BY id
                    LIMIT " . $chunk . "
                ) AS visitor_chunk");

            if ($upper <= $cursor) {
                $delete_done = true;
                break;
            }

            $chunk_started = microtime(true);

            $deleted = @mysqli_query(db::$con,
                "DELETE FROM visitors
                WHERE (id > " . $cursor . ") AND (id <= " . $upper . ") AND (start_timestamp < " . (int) $delete_before . ")");

            if (!$deleted) {
                // A lock wait timeout or a deadlock with a live write: give
                // the site the moment and leave the rest for the next run.
                break;
            }

            $result['deleted'] += (int) mysqli_affected_rows(db::$con);

            $cursor = $upper;

            if ($cursor >= $last_id) {
                $delete_done = true;
                break;
            }

            // A short breath between chunks, so other queries waiting on the
            // same pages and the replication of a busy server keep up.
            usleep($row_locks ? 20000 : max(20000, (int) ((microtime(true) - $chunk_started) * 1000000)));
        }
    }

    db("SELECT RELEASE_LOCK(" . pg_visitor_cleanup_lock_sql() . ")");

    $backlog = !$delete_done || !$summary_complete || ($id_ceiling !== null);

    $result['state'] = $backlog ? 'more' : 'done';
    $result['seconds'] = round(microtime(true) - $started, 2);

    @file_put_contents(pg_visitor_cleanup_marker(), json_encode(array(
        'checked_at' => time(),
        'backlog' => $backlog,
        'deleted' => $result['deleted'],
        'summarized' => $result['summarized'],
        'seconds' => $result['seconds'],
    )));

    return $result;
}

/**
 * What a cleanup would remove now: the rows before the boundary and about how
 * much room they take, from the table's own average row size.
 *
 * @param array|null $catalog_row pg_db_table_catalog()['visitors'], when the
 *                                caller has read it already
 * @return array('days', 'summary', 'cutoff', 'rows', 'bytes', 'oldest')|false
 */
function pg_visitor_cleanup_preview($catalog_row = null)
{
    $settings = pg_visitor_retention_settings();

    if ($settings === false) {
        return false;
    }

    $cutoff = pg_visitor_retention_cutoff($settings['days'], time());

    // Through the start_timestamp index, which holds nothing but the time and
    // the key: a few seconds' read even on a table of millions.
    $rows = ($cutoff > 0)
        ? (int) db_value("SELECT COUNT(*) FROM visitors WHERE start_timestamp < " . (int) $cutoff)
        : 0;

    $oldest = (int) db_value("SELECT MIN(start_timestamp) FROM visitors WHERE start_timestamp > 0");

    if ($catalog_row === null) {
        $catalog = pg_db_table_catalog();
        $catalog_row = isset($catalog['visitors']) ? $catalog['visitors'] : null;
    }

    $bytes = 0.0;

    if (is_array($catalog_row) && ($catalog_row['rows'] > 0)) {
        $bytes = $rows * ((float) $catalog_row['bytes'] / (float) $catalog_row['rows']);
    }

    return array(
        'days' => $settings['days'],
        'summary' => $settings['summary'],
        'cutoff' => $cutoff,
        'rows' => $rows,
        'bytes' => $bytes,
        'oldest' => $oldest,
    );
}

/**
 * When the visitors table is large enough to be worth a word in the panel.
 *
 * Rows from the server's statistics (an estimate on InnoDB), bytes data plus
 * indexes.
 *
 * @return array('rows' => int, 'bytes' => float)
 */
function pg_visitor_health_thresholds()
{
    return array(
        'rows' => 2000000,
        'bytes' => 536870912.0,
    );
}

/**
 * Whether the figures of the visitors table cross either threshold.
 *
 * @param array $catalog_row pg_db_table_catalog()['visitors']
 * @return bool
 */
function pg_visitor_table_is_large($catalog_row)
{
    if (!is_array($catalog_row)) {
        return false;
    }

    $limits = pg_visitor_health_thresholds();

    return ((int) $catalog_row['rows'] >= $limits['rows']) || ((float) $catalog_row['bytes'] >= $limits['bytes']);
}

/**
 * One sentence on a run of pg_visitor_cleanup_run(), for the activity log and
 * the notice on the Database Engine screen.
 *
 * @param array $run
 * @return string
 */
function pg_visitor_cleanup_result_text($run)
{
    switch ($run['state']) {

        case 'unavailable':
            return lang('Visitor records: the settings for keeping them are not in the database yet. Run the upgrade steps again.');

        case 'off':
            return lang('Visitor records are kept without a limit, so nothing was removed. The period is set under Settings › SEO › Visitor Tracking.');

        case 'busy':
            return lang('Another visitor record cleanup is running (the general job, or another tab). Try again in a minute.');
    }

    $text = lang(array(
        'string' => '{var:1} visitor record(s) older than {var:2} days removed, {var:3} day(s) added to the daily summary, in {var:4} s.',
        'vars' => array(
            pg_format_number($run['deleted'], 0),
            $run['days'],
            pg_format_number($run['summarized'], 0),
            pg_format_number($run['seconds'], 1),
        ),
    ));

    return $text . ' ' . (($run['state'] === 'more')
        ? lang('Older records are still left; the general job carries on with them.')
        : lang('No older records are left.'));
}

// -- Moving a MyISAM visitors table to InnoDB ---------------------------------
//
// 2026.3.6 moved `visitors` to InnoDB with one ALTER TABLE; on a site where
// that did not happen (the ALTER was refused, or the table came back from an
// old backup) it is still on MyISAM. There every write to it waits for every
// statement on it, and once the cleanup has removed rows the holes they leave
// switch off MyISAM's concurrent inserts as well. The file also keeps the
// size of the rows that are gone.
//
// ALTER TABLE ... ENGINE=InnoDB fixes both, but it copies the whole table
// while every write to it waits: the site stops for as long as the copy takes.
// Here the general job builds the InnoDB table next to it instead, a chunk of
// rows per statement, copies again the visits that changed meanwhile, and
// then puts it in the old table's place with one RENAME TABLE, which takes a
// moment. The old table is checked against the new one and removed.

/**
 * The InnoDB table built next to a MyISAM `visitors`, and the name the MyISAM
 * table goes under between the swap and its removal.
 */
define('PG_VISITOR_MOVE_COPY', 'visitors_innodb');
define('PG_VISITOR_MOVE_OLD', 'visitors_myisam');

/**
 * The file that remembers what the last look at the move found and, during a
 * move, when the copy began.
 *
 * Where the copy stands is read from the tables themselves (the highest id in
 * the copy), so losing the file during the copy costs a longer last pass.
 * Lost between the swap and the removal, it leaves the old table for the
 * operator: only a table this file says the swap put there is dropped.
 *
 * @return string
 */
function pg_visitor_move_marker()
{
    return PG_FUNCTIONS_DIR . '/data/temp/visitor_move.json';
}

/**
 * What the marker file holds, every key present.
 *
 * state: '' never looked, 'none' the table is not on MyISAM, 'waiting' on
 * MyISAM and not started ('reason' says why), 'copying', 'swapped' (the copy
 * is in place, the old table not removed yet), 'done', 'failed' ('error').
 *
 * @return array
 */
function pg_visitor_move_last()
{
    $last = @json_decode((string) @file_get_contents(pg_visitor_move_marker()), true);

    if (!is_array($last)) {
        $last = array();
    }

    return array(
        'state' => isset($last['state']) ? (string) $last['state'] : '',
        'reason' => isset($last['reason']) ? (string) $last['reason'] : '',
        'error' => isset($last['error']) ? (string) $last['error'] : '',
        'requested' => !empty($last['requested']),
        'checked_at' => isset($last['checked_at']) ? (int) $last['checked_at'] : 0,
        'started' => isset($last['started']) ? (int) $last['started'] : 0,
        'synced_at' => isset($last['synced_at']) ? (int) $last['synced_at'] : 0,
        'copied' => isset($last['copied']) ? (int) $last['copied'] : 0,
        'last_id' => isset($last['last_id']) ? (int) $last['last_id'] : 0,
        'needed' => isset($last['needed']) ? (float) $last['needed'] : 0.0,
        'rows_before' => isset($last['rows_before']) ? (int) $last['rows_before'] : 0,
        'bytes_before' => isset($last['bytes_before']) ? (float) $last['bytes_before'] : 0.0,
        'bytes_after' => isset($last['bytes_after']) ? (float) $last['bytes_after'] : 0.0,
        'finished_at' => isset($last['finished_at']) ? (int) $last['finished_at'] : 0,
        'failed_at' => isset($last['failed_at']) ? (int) $last['failed_at'] : 0,
    );
}

/**
 * Write the marker file.
 *
 * @param array $last pg_visitor_move_last() with the changes
 */
function pg_visitor_move_save($last)
{
    $marker = pg_visitor_move_marker();

    if (!is_dir(dirname($marker))) {
        @mkdir(dirname($marker), 0755, true);
    }

    @file_put_contents($marker, json_encode($last), LOCK_EX);
}

/**
 * Whether the general job should look at the move now: on every tick while
 * one is under way or the operator asked for one, otherwise once an hour
 * (once a day when the table is not on MyISAM at all).
 *
 * @return bool
 */
function pg_visitor_move_due()
{
    $last = pg_visitor_move_last();

    if ($last['requested'] || in_array($last['state'], array('copying', 'swapped'), true)) {
        return true;
    }

    $every = in_array($last['state'], array('none', 'done'), true) ? 86400 : 3600;

    return $last['checked_at'] < (time() - $every);
}

/**
 * Whether the database server runs on this machine, from the host the site
 * connects to, so that the free space of its data directory can be read here.
 *
 * No database access.
 *
 * @param string $host DB_HOST: a name or address, with a port, or mysqli's
 *                     "p:" prefix for a persistent connection
 * @return bool
 */
function pg_visitor_move_db_is_local($host)
{
    $host = strtolower(trim((string) $host));

    if (strpos($host, 'p:') === 0) {
        $host = substr($host, 2);
    }

    if (preg_match('/^\[([^\]]*)\](?::\d+)?$/', $host, $match) === 1) {
        $host = $match[1];
    } elseif (preg_match('/^([^:]*):\d+$/', $host, $match) === 1) {
        $host = $match[1];
    }

    return in_array($host, array('', 'localhost', '127.0.0.1', '::1', '.'), true);
}

/**
 * Free disk space the move asks for before the general job starts it on its
 * own, from the figures of the table now.
 *
 * The copy holds the rows that are left (the data less the room of removed
 * rows, and the indexes); InnoDB keeps the same rows in up to about one and
 * a half times the room, and the server's logs grow with the copy as well.
 * Three times that, and a gigabyte left over for the rest of the server.
 *
 * No database access.
 *
 * @param array $catalog_row pg_db_table_catalog()['visitors']
 * @return float bytes
 */
function pg_visitor_move_disk_needed($catalog_row)
{
    $data = isset($catalog_row['data']) ? (float) $catalog_row['data'] : 0.0;
    $free = isset($catalog_row['free']) ? (float) $catalog_row['free'] : 0.0;
    $index = isset($catalog_row['index']) ? (float) $catalog_row['index'] : 0.0;

    return (3 * (max(0.0, $data - $free) + $index)) + 1073741824.0;
}

/**
 * Free bytes where the database server keeps its files, or null when that
 * cannot be read from here: the server is on another machine, the host has
 * switched disk_free_space() off, or open_basedir keeps the directory out of
 * reach.
 *
 * @return float|null
 */
function pg_visitor_move_disk_free()
{
    if (!defined('DB_HOST') || !pg_visitor_move_db_is_local(DB_HOST) || !function_exists('disk_free_space')) {
        return null;
    }

    $result = @mysqli_query(db::$con, "SELECT @@datadir");

    $row = $result ? mysqli_fetch_row($result) : null;

    $directory = is_array($row) ? (string) $row[0] : '';

    if ($directory === '') {
        return null;
    }

    $free = @disk_free_space($directory);

    return ($free === false) ? null : (float) $free;
}

/**
 * Why the general job does not start moving the table now, or '' when it may.
 *
 * On its own it starts only once the cleanup has brought the table down to
 * its period, so that only the rows that are kept are copied, only when it
 * can see the free disk the copy needs, and not again for a week after a
 * move that stopped on an error. The operator's request skips these: whoever
 * asked has seen the space it needs on the screen.
 *
 * No database access.
 *
 * @param array $facts engine (lower case), capable (bool), fits (bool|null),
 *                     old_exists (bool), requested (bool), failed (bool: a
 *                     move stopped on an error in the last week),
 *                     days (int), cleaned (bool: the cleanup has run),
 *                     backlog (bool), disk_free (float|null), needed (float)
 * @return string '', 'engine', 'unsupported', 'too_wide', 'name_taken',
 *                'failed', 'no_period', 'backlog', 'disk_unknown', 'disk_low'
 */
function pg_visitor_move_hold_reason($facts)
{
    if ($facts['engine'] !== 'myisam') {
        return 'engine';
    }

    if (!$facts['capable']) {
        return 'unsupported';
    }

    if ($facts['fits'] === false) {
        return 'too_wide';
    }

    if ($facts['old_exists']) {
        return 'name_taken';
    }

    if ($facts['requested']) {
        return '';
    }

    if ($facts['failed']) {
        return 'failed';
    }

    if ((int) $facts['days'] <= 0) {
        return 'no_period';
    }

    if (!$facts['cleaned'] || $facts['backlog']) {
        return 'backlog';
    }

    if ($facts['disk_free'] === null) {
        return 'disk_unknown';
    }

    if ((float) $facts['disk_free'] < (float) $facts['needed']) {
        return 'disk_low';
    }

    return '';
}

/**
 * One query of the move, through mysqli rather than db(): db() ends the
 * request on a failed query, and a statement of the move that fails is one
 * of its answers.
 *
 * @param string $sql
 * @return mysqli_result|bool
 */
function pg_visitor_move_query($sql)
{
    return @mysqli_query(db::$con, $sql);
}

/**
 * Engine of `visitors`, of the copy and of the old table, lower case, '' for
 * a table that is not there.
 *
 * @return array name => engine
 */
function pg_visitor_move_engines()
{
    $engines = array('visitors' => '', PG_VISITOR_MOVE_COPY => '', PG_VISITOR_MOVE_OLD => '');

    $result = pg_visitor_move_query(
        "SELECT TABLE_NAME, ENGINE
        FROM information_schema.TABLES
        WHERE
            (TABLE_SCHEMA = DATABASE())
            AND (TABLE_NAME IN ('visitors', '" . PG_VISITOR_MOVE_COPY . "', '" . PG_VISITOR_MOVE_OLD . "'))");

    while ($result && ($row = mysqli_fetch_row($result))) {
        $engines[(string) $row[0]] = strtolower((string) $row[1]);
    }

    return $engines;
}

/**
 * Whether the copy has the columns of `visitors`, in the same order and of
 * the same types: the copy statements read SELECT *. An upgrade step that
 * changed `visitors` after the copy began means beginning again.
 *
 * @return bool
 */
function pg_visitor_move_same_columns()
{
    $columns = array('visitors' => array(), PG_VISITOR_MOVE_COPY => array());

    $result = pg_visitor_move_query(
        "SELECT TABLE_NAME, COLUMN_NAME, COLUMN_TYPE
        FROM information_schema.COLUMNS
        WHERE
            (TABLE_SCHEMA = DATABASE())
            AND (TABLE_NAME IN ('visitors', '" . PG_VISITOR_MOVE_COPY . "'))
        ORDER BY TABLE_NAME, ORDINAL_POSITION");

    while ($result && ($row = mysqli_fetch_row($result))) {
        $columns[(string) $row[0]][] = $row[1] . ' ' . $row[2];
    }

    return (count($columns['visitors']) > 0) && ($columns['visitors'] === $columns[PG_VISITOR_MOVE_COPY]);
}

/**
 * The ids a SELECT returns, as integers.
 *
 * @param string $sql
 * @return array|false
 */
function pg_visitor_move_ids($sql)
{
    $result = pg_visitor_move_query($sql);

    if (!$result) {
        return false;
    }

    $ids = array();

    while ($row = mysqli_fetch_row($result)) {
        $ids[] = (int) $row[0];
    }

    return $ids;
}

/**
 * A single number a SELECT returns, or 0.
 *
 * @param string $sql
 * @return int
 */
function pg_visitor_move_number($sql)
{
    $result = pg_visitor_move_query($sql);

    $row = $result ? mysqli_fetch_row($result) : null;

    return is_array($row) ? (int) $row[0] : 0;
}

/**
 * Build the empty InnoDB copy: the table's own definition, on InnoDB, without
 * the two indexes the 2026.4.10 upgrade takes off an InnoDB `visitors`
 * (ip_address, which idx_dedup begins with, and idx_user_agent, which no
 * query reads). The table it replaces would otherwise keep them for good, as
 * that upgrade leaves a MyISAM table alone.
 *
 * @return string '' when built, otherwise the server's error
 */
function pg_visitor_move_create_copy()
{
    $copy = PG_VISITOR_MOVE_COPY;

    if (!pg_visitor_move_query("CREATE TABLE `" . $copy . "` LIKE `visitors`")) {
        return (string) mysqli_error(db::$con);
    }

    $indexes = array();

    $result = pg_visitor_move_query(
        "SELECT DISTINCT INDEX_NAME
        FROM information_schema.STATISTICS
        WHERE (TABLE_SCHEMA = DATABASE()) AND (TABLE_NAME = '" . $copy . "')");

    while ($result && ($row = mysqli_fetch_row($result))) {
        $indexes[(string) $row[0]] = true;
    }

    $changes = array('ENGINE=InnoDB', 'ROW_FORMAT=DYNAMIC');

    if (isset($indexes['idx_dedup']) && isset($indexes['ip_address'])) {
        $changes[] = 'DROP INDEX `ip_address`';
    }

    if (isset($indexes['idx_user_agent'])) {
        $changes[] = 'DROP INDEX `idx_user_agent`';
    }

    // The table is empty: this is a change of its definition, not a copy.
    if (!pg_visitor_move_query("ALTER TABLE `" . $copy . "` " . implode(', ', $changes))) {
        $error = (string) mysqli_error(db::$con);
        pg_visitor_move_query("DROP TABLE IF EXISTS `" . $copy . "`");
        return $error;
    }

    return '';
}

/**
 * Move a MyISAM `visitors` to InnoDB a step at a time, or look whether one
 * should be started.
 *
 * Each run takes the cleanup's lock, so a move and a cleanup never run at
 * the same time, and goes on from where the tables stand:
 *
 * - Copy: rows go into the copy in windows of the primary key, $chunk rows a
 *   statement. On MyISAM the statement holds the table for as long as it
 *   reads its window, and the run then leaves it free at least as long.
 * - Last passes, once the copy has caught up with new visits: the visits
 *   that changed after they were copied (each change to a visit comes with a
 *   page view, and a page view moves its stop_timestamp to the moment) are
 *   copied again in chunks, then the visits of those seconds once more.
 * - Swap: RENAME TABLE puts the copy in place of `visitors` and the old table
 *   under PG_VISITOR_MOVE_OLD in one statement. A visit written to the old
 *   table between the last pass and the swap is copied right after it; the
 *   copy's AUTO_INCREMENT was moved past them first, so no new visit takes
 *   their ids.
 * - Check and removal: every row of the old table within the retention
 *   period must be in the new one (one that is not is copied); then the old
 *   table is dropped.
 *
 * @param int  $budget_seconds
 * @param int  $chunk Rows per copy statement
 * @return array('state' => 'busy'|'none'|'waiting'|'copying'|'swapped'|'done'|'failed',
 *               'reason' => string, 'copied' => int, 'text' => string, 'seconds' => float)
 */
function pg_visitor_move_run($budget_seconds = 15, $chunk = 2000)
{
    $started = microtime(true);

    $result = array('state' => 'none', 'reason' => '', 'copied' => 0, 'text' => '', 'seconds' => 0.0);

    if (!class_exists('db') || empty(db::$con)) {
        return $result;
    }

    if (pg_visitor_move_number("SELECT GET_LOCK(" . pg_visitor_cleanup_lock_sql() . ", 0)") !== 1) {
        $result['state'] = 'busy';
        return $result;
    }

    if (function_exists('ignore_user_abort')) {
        @ignore_user_abort(true);
    }

    $deadline = $started + max(1, (int) $budget_seconds);
    $chunk = max(500, min(20000, (int) $chunk));

    $last = pg_visitor_move_last();
    $last['checked_at'] = time();

    $requested_before = $last['requested'];

    // The copy re-inserts every row under the session's sql_mode, the same
    // reason pg_innodb_convert_table() sets it; put back afterwards.
    $previous = null;
    $previous_result = pg_visitor_move_query("SELECT @@SESSION.sql_mode, @@SESSION.lock_wait_timeout");

    if ($previous_result) {
        $previous = mysqli_fetch_row($previous_result);
    }

    pg_visitor_move_query("SET SESSION sql_mode = 'NO_ENGINE_SUBSTITUTION'");

    $result = pg_visitor_move_step($last, $deadline, $chunk, $result);

    if (is_array($previous)) {
        pg_visitor_move_query("SET SESSION sql_mode = '" . mysqli_real_escape_string(db::$con, (string) $previous[0]) . "'");
        pg_visitor_move_query("SET SESSION lock_wait_timeout = " . (int) $previous[1]);
    }

    // A request the screen wrote while this run was under way is kept for
    // the next one, unless this run has already moved the table on.
    $on_disk = pg_visitor_move_last();

    if ($on_disk['requested'] && !$requested_before && !in_array($last['state'], array('copying', 'swapped', 'done'), true)) {
        $last['requested'] = true;
    }

    pg_visitor_move_save($last);

    pg_visitor_move_query("SELECT RELEASE_LOCK(" . pg_visitor_cleanup_lock_sql() . ")");

    $result['seconds'] = round(microtime(true) - $started, 2);

    return $result;
}

/**
 * The work of one pg_visitor_move_run(), under its lock.
 *
 * @param array $last   The marker, updated in place
 * @param float $deadline
 * @param int   $chunk
 * @param array $result
 * @return array $result
 */
function pg_visitor_move_step(&$last, $deadline, $chunk, $result)
{
    $copy = PG_VISITOR_MOVE_COPY;
    $old = PG_VISITOR_MOVE_OLD;

    $engines = pg_visitor_move_engines();

    // The copy is in place; what is left is the old table. Only a table this
    // marker says the swap put there is checked and dropped; any other table
    // of that name is left for the operator to look at.
    if (($engines['visitors'] === 'innodb') && ($engines[$old] !== '') && ($last['state'] === 'swapped')) {
        return pg_visitor_move_finish($last, $result);
    }

    if ($engines['visitors'] !== 'myisam') {

        // A copy with no MyISAM table to go with it: the table was converted
        // another way meanwhile, or a backup brought the copy back.
        if ($engines[$copy] !== '') {
            pg_visitor_move_query("DROP TABLE `" . $copy . "`");
        }

        if (!in_array($last['state'], array('done', 'failed'), true)) {
            $last['state'] = 'none';
        }

        $last['requested'] = false;
        $last['reason'] = '';
        $result['state'] = $last['state'];

        return $result;
    }

    if ($engines[$copy] === '') {

        $catalog = pg_db_table_catalog();
        $catalog_row = isset($catalog['visitors']) ? $catalog['visitors'] : array();

        $settings = pg_visitor_retention_settings();
        $cleanup = pg_visitor_cleanup_last();
        $capability = pg_innodb_capability();
        $estimate = $capability['ok'] ? pg_innodb_row_estimate('visitors') : null;

        $last['needed'] = pg_visitor_move_disk_needed($catalog_row);

        $reason = pg_visitor_move_hold_reason(array(
            'engine' => 'myisam',
            'capable' => $capability['ok'],
            'fits' => is_array($estimate) ? $estimate['fits'] : null,
            'old_exists' => ($engines[$old] !== ''),
            'requested' => $last['requested'],
            'failed' => (($last['state'] === 'failed') && ($last['failed_at'] > (time() - 7 * 86400))),
            'days' => is_array($settings) ? $settings['days'] : 0,
            'cleaned' => ($cleanup['checked_at'] > 0),
            'backlog' => $cleanup['backlog'],
            'disk_free' => pg_visitor_move_disk_free(),
            'needed' => $last['needed'],
        ));

        if ($reason !== '') {

            if ($last['state'] !== 'failed') {
                $last['state'] = 'waiting';
                $last['error'] = ($reason === 'unsupported') ? $capability['reason'] : '';
            }

            $last['requested'] = false;
            $last['reason'] = $reason;

            $result['state'] = $last['state'];
            $result['reason'] = $reason;

            return $result;
        }

        $error = pg_visitor_move_create_copy();

        if ($error !== '') {
            return pg_visitor_move_fail($last, $result, $error);
        }

        $last['state'] = 'copying';
        $last['reason'] = '';
        $last['error'] = '';
        $last['requested'] = false;
        $last['started'] = time();
        $last['synced_at'] = 0;
        $last['copied'] = 0;
        $last['rows_before'] = isset($catalog_row['rows']) ? (int) $catalog_row['rows'] : 0;
        $last['bytes_before'] = isset($catalog_row['bytes']) ? (float) $catalog_row['bytes'] : 0.0;
        $last['finished_at'] = 0;

        // Written now: a run cut off from here on must find when it began.
        pg_visitor_move_save($last);

    } elseif (($engines[$copy] !== 'innodb') || !pg_visitor_move_same_columns()) {

        // Not a copy this function made, or `visitors` changed under it.
        pg_visitor_move_query("DROP TABLE `" . $copy . "`");

        $last['state'] = 'waiting';
        $last['reason'] = 'restart';
        $result['state'] = 'waiting';
        $result['reason'] = 'restart';

        return $result;
    }

    // The marker went missing during a copy: when the copy table was made
    // is the next best start. Without either, a week back, which only makes
    // the last pass look at more visits.
    if ($last['started'] <= 0) {
        $created = pg_visitor_move_query(
            "SELECT UNIX_TIMESTAMP(CREATE_TIME)
            FROM information_schema.TABLES
            WHERE (TABLE_SCHEMA = DATABASE()) AND (TABLE_NAME = '" . $copy . "')");
        $created_row = $created ? mysqli_fetch_row($created) : null;
        $last['started'] = (is_array($created_row) && ((int) $created_row[0] > 0)) ? (int) $created_row[0] : (time() - 7 * 86400);
    }

    $last['state'] = 'copying';

    // -- Copy ----------------------------------------------------------------
    $cursor = pg_visitor_move_number("SELECT COALESCE(MAX(id), 0) FROM `" . $copy . "`");
    $caught_up = false;

    while (microtime(true) < $deadline) {

        $chunk_started = microtime(true);

        // The window's upper id is read from the key first and the rows then
        // copied by that range, the way the cleanup removes them: a plain
        // range is the same statement on every server and safe for
        // statement-based replication, which INSERT ... SELECT ... LIMIT is
        // not.
        $upper = pg_visitor_move_number(
            "SELECT COALESCE(MAX(id), 0)
            FROM (
                SELECT id
                FROM visitors
                WHERE id > " . $cursor . "
                ORDER BY id
                LIMIT " . $chunk . "
            ) AS visitor_chunk");

        if ($upper <= $cursor) {
            $caught_up = true;
            break;
        }

        if (!pg_visitor_move_query(
            "INSERT INTO `" . $copy . "`
            SELECT * FROM visitors
            WHERE (id > " . $cursor . ") AND (id <= " . $upper . ")")) {
            return pg_visitor_move_fail($last, $result, (string) mysqli_error(db::$con));
        }

        $copied = (int) mysqli_affected_rows(db::$con);

        $result['copied'] += $copied;
        $last['copied'] += $copied;

        $cursor = $upper;

        // The last window of the table: what is left is the visits that
        // arrive meanwhile, for the last passes.
        if ($copied < $chunk) {
            $caught_up = true;
            break;
        }

        usleep(max(20000, (int) ((microtime(true) - $chunk_started) * 1000000)));
    }

    // The last passes and the swap want a few seconds to themselves.
    if (!$caught_up || (($deadline - microtime(true)) < 3)) {
        $result['state'] = 'copying';
        return $result;
    }

    return pg_visitor_move_swap($last, $deadline, $chunk, $result);
}

/**
 * Copy rows of `visitors` into the copy again, by id, a chunk at a time,
 * replacing what the copy holds for them.
 *
 * A DELETE and an INSERT rather than REPLACE ... SELECT, which statement-based
 * replication counts as unsafe. Nothing but this function writes to the copy,
 * so nothing sees it between the two.
 *
 * @param array $ids
 * @param int   $chunk
 * @param float $deadline 0 for no limit
 * @return bool|null true when done, null when the deadline came first, false
 *                   on an error
 */
function pg_visitor_move_recopy($ids, $chunk, $deadline)
{
    foreach (array_chunk($ids, $chunk) as $part) {

        if (($deadline > 0) && (microtime(true) >= $deadline)) {
            return null;
        }

        $part_started = microtime(true);

        $list = implode(',', $part);

        if (!pg_visitor_move_query("DELETE FROM `" . PG_VISITOR_MOVE_COPY . "` WHERE id IN (" . $list . ")")
            || !pg_visitor_move_query("INSERT INTO `" . PG_VISITOR_MOVE_COPY . "` SELECT * FROM visitors WHERE id IN (" . $list . ")")) {
            return false;
        }

        if ($deadline > 0) {
            usleep(max(20000, (int) ((microtime(true) - $part_started) * 1000000)));
        }
    }

    return true;
}

/**
 * The last passes and the swap of pg_visitor_move_run().
 *
 * @param array $last
 * @param float $deadline
 * @param int   $chunk
 * @param array $result
 * @return array $result
 */
function pg_visitor_move_swap(&$last, $deadline, $chunk, $result)
{
    $copy = PG_VISITOR_MOVE_COPY;
    $old = PG_VISITOR_MOVE_OLD;

    // The swap needs the name; a table that took it during the copy is not
    // this function's to remove.
    $engines = pg_visitor_move_engines();

    if ($engines[$old] !== '') {
        return pg_visitor_move_fail($last, $result, lang(array('string' => 'a table named {var:1} is in the way', 'vars' => array($old))));
    }

    // -- Visits that changed after they were copied --------------------------
    //
    // Read through the stop_timestamp index: the visits of the last minutes
    // or hours, not the table. A run that runs out of time leaves synced_at
    // as it was and the next one starts this pass again.
    $since = ($last['synced_at'] > 0) ? ($last['synced_at'] - 2) : ($last['started'] - 60);
    $pass_started = time();

    $cursor = pg_visitor_move_number("SELECT COALESCE(MAX(id), 0) FROM `" . $copy . "`");

    $changed = pg_visitor_move_ids(
        "SELECT id FROM visitors
        WHERE (stop_timestamp >= " . (int) $since . ") AND (id <= " . $cursor . ")");

    if ($changed === false) {
        return pg_visitor_move_fail($last, $result, (string) mysqli_error(db::$con));
    }

    $recopied = pg_visitor_move_recopy($changed, $chunk, $deadline - 2);

    if ($recopied === false) {
        return pg_visitor_move_fail($last, $result, (string) mysqli_error(db::$con));
    }

    $result['state'] = 'copying';

    if ($recopied === null) {
        return $result;
    }

    $last['synced_at'] = $pass_started;

    // -- The visits of the last seconds, then the swap, back to back ---------
    //
    // AUTO_INCREMENT first: the copy hands out ids from past what the old
    // table may still hand out until the swap, so a visit written there in
    // that moment keeps its id when it is copied over afterwards.
    $next_id = pg_visitor_move_number("SELECT COALESCE(MAX(id), 0) FROM visitors") + 1000;

    if (!pg_visitor_move_query("ALTER TABLE `" . $copy . "` AUTO_INCREMENT = " . $next_id)) {
        return pg_visitor_move_fail($last, $result, (string) mysqli_error(db::$con));
    }

    if (!pg_visitor_move_query("INSERT INTO `" . $copy . "` SELECT * FROM visitors WHERE id > " . $cursor)) {
        return pg_visitor_move_fail($last, $result, (string) mysqli_error(db::$con));
    }

    $changed = pg_visitor_move_ids(
        "SELECT id FROM visitors
        WHERE (stop_timestamp >= " . ($pass_started - 2) . ") AND (id <= " . $cursor . ")");

    if (($changed === false) || (pg_visitor_move_recopy($changed, $chunk, 0) === false)) {
        return pg_visitor_move_fail($last, $result, (string) mysqli_error(db::$con));
    }

    $last_id = pg_visitor_move_number("SELECT COALESCE(MAX(id), 0) FROM `" . $copy . "`");

    // RENAME waits for the statements running on either table, then takes a
    // moment. A short wait: on a busy table the next run tries again rather
    // than hold the queue that forms behind the waiting RENAME.
    pg_visitor_move_query("SET SESSION lock_wait_timeout = 5");

    $renamed = pg_visitor_move_query("RENAME TABLE visitors TO `" . $old . "`, `" . $copy . "` TO visitors");

    if (!$renamed) {
        $last['error'] = (string) mysqli_error(db::$con);
        return $result;
    }

    $last['state'] = 'swapped';
    $last['last_id'] = $last_id;
    $last['error'] = '';

    pg_visitor_move_save($last);

    // Visits written to the old table between the last pass and the swap.
    // Their ids are below the copy's AUTO_INCREMENT, so none is taken yet;
    // should this fail all the same, the check below finds them.
    pg_visitor_move_query("INSERT INTO visitors SELECT * FROM `" . $old . "` WHERE id > " . $last_id);

    return pg_visitor_move_finish($last, $result);
}

/**
 * After the swap: check the old table against the new one, then drop it.
 *
 * A row of the old table that started before the retention boundary may
 * rightly be missing from the new one (a cleanup ran since the swap), so only
 * the rows within the period are looked for. One that is missing is copied
 * over; when that does not make the two agree, the old table stays and the
 * move is marked failed.
 *
 * @param array $last
 * @param array $result
 * @return array $result
 */
function pg_visitor_move_finish(&$last, $result)
{
    $old = PG_VISITOR_MOVE_OLD;

    $settings = pg_visitor_retention_settings();
    $cutoff = is_array($settings) ? pg_visitor_retention_cutoff($settings['days'], time()) : 0;

    // A plain SELECT reads the InnoDB table without locking its rows.
    $missing_sql =
        "SELECT o.id
        FROM `" . $old . "` AS o
        LEFT JOIN visitors AS n ON (n.id = o.id)
        WHERE (n.id IS NULL) AND (o.start_timestamp >= " . (int) $cutoff . ")";

    $missing = pg_visitor_move_ids($missing_sql);

    if (is_array($missing) && (count($missing) > 0)) {

        foreach (array_chunk($missing, 1000) as $part) {
            pg_visitor_move_query(
                "INSERT INTO visitors
                SELECT * FROM `" . $old . "`
                WHERE id IN (" . implode(',', $part) . ")");
        }

        $missing = pg_visitor_move_ids($missing_sql);
    }

    if (!is_array($missing) || (count($missing) > 0)) {

        $last['state'] = 'failed';
        $last['failed_at'] = time();
        $last['error'] = is_array($missing)
            ? lang(array('string' => '{var:1} record(s) of the old table could not be copied to the new one', 'vars' => array(pg_format_number(count($missing), 0))))
            : (string) mysqli_error(db::$con);

        $result['state'] = 'failed';
        $result['text'] = pg_visitor_move_result_text($last);

        return $result;
    }

    pg_visitor_move_query("DROP TABLE `" . $old . "`");

    $catalog = pg_db_table_catalog();

    $last['state'] = 'done';
    $last['error'] = '';
    $last['reason'] = '';
    $last['requested'] = false;
    $last['finished_at'] = time();
    $last['bytes_after'] = isset($catalog['visitors']) ? (float) $catalog['visitors']['bytes'] : 0.0;

    $result['state'] = 'done';
    $result['text'] = pg_visitor_move_result_text($last);

    return $result;
}

/**
 * A move that stopped on an error: the copy is dropped, which gives its disk
 * space back, and the table stays on MyISAM, where the site works as before.
 * Not started again on its own; the screen offers to.
 *
 * Only ever called before the swap; after it nothing is dropped.
 *
 * @param array  $last
 * @param array  $result
 * @param string $error
 * @return array $result
 */
function pg_visitor_move_fail(&$last, $result, $error)
{
    pg_visitor_move_query("DROP TABLE IF EXISTS `" . PG_VISITOR_MOVE_COPY . "`");

    $last['state'] = 'failed';
    $last['error'] = ($error !== '') ? $error : lang('unknown error');
    $last['requested'] = false;
    $last['failed_at'] = time();

    $result['state'] = 'failed';
    $result['text'] = pg_visitor_move_result_text($last);

    return $result;
}

/**
 * One sentence on how a move ended, for the activity log and the screen.
 *
 * @param array $last pg_visitor_move_last()
 * @return string
 */
function pg_visitor_move_result_text($last)
{
    if ($last['state'] === 'done') {
        return lang(array(
            'string' => 'The visitors table was moved to InnoDB: {var:1} before, {var:2} now.',
            'vars' => array(pg_innodb_size_label($last['bytes_before']), pg_innodb_size_label($last['bytes_after'])),
        ));
    }

    if ($last['state'] === 'failed') {
        return lang(array(
            'string' => 'Moving the visitors table to InnoDB stopped: {var:1}. It stays on MyISAM and works as before.',
            'vars' => array($last['error']),
        ));
    }

    return '';
}

/**
 * Why a MyISAM visitors table is not being moved, in a sentence for the
 * screen.
 *
 * @param array $last pg_visitor_move_last()
 * @return string
 */
function pg_visitor_move_reason_text($last)
{
    switch ($last['reason']) {

        case 'unsupported':
            return lang(array('string' => 'It cannot be moved to InnoDB on this server: {var:1}.', 'vars' => array($last['error'])));

        case 'too_wide':
            return lang('It cannot be moved to InnoDB as it is: its rows are too wide for an InnoDB page.');

        case 'name_taken':
            return lang(array('string' => 'A table named {var:1} is in the way of the move. Once you have looked at it and removed it, the move can start.', 'vars' => array(PG_VISITOR_MOVE_OLD)));

        case 'no_period':
            return lang('The general job moves it to InnoDB on its own once a period is set for keeping visitor records and the older ones are gone.');

        case 'backlog':
            return lang('Once the old records are gone, the general job moves it to InnoDB on its own, a few thousand records at a time while the site keeps running. That also makes the file smaller.');

        case 'disk_unknown':
            return lang(array('string' => 'The general job does not start moving it to InnoDB on its own, because the free disk space of the database server cannot be read from here. The move needs about {var:1} free until the old table is removed.', 'vars' => array(pg_innodb_size_label($last['needed']))));

        case 'disk_low':
            return lang(array('string' => 'The general job does not start moving it to InnoDB on its own, because the database server has less free disk space than the move needs, about {var:1}.', 'vars' => array(pg_innodb_size_label($last['needed']))));
    }

    return '';
}
