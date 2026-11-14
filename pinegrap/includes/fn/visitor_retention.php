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
 * of a table still on MyISAM to InnoDB once it is down to its period, the
 * online rebuild that gives the room of removed rows back to the disk, and the
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
 * The settings, or false before the upgrade that adds them. ip_days is the
 * period after which a visit's IP address is taken out of its record
 * (includes/fn/visitor_ip.php), 0 before its own step (10.8) as well.
 *
 * @return array('days' => int, 'summary' => bool, 'ip_days' => int)|false
 */
function pg_visitor_retention_settings()
{
    if (!pg_visitor_retention_ready()) {
        return false;
    }

    $ip_ready = pg_visitor_ip_ready();

    $row = db_item("SELECT visitor_retention_days, visitor_summary" . ($ip_ready ? ", visitor_ip_days" : "") . " FROM config");

    if (!is_array($row)) {
        return false;
    }

    return array(
        'days' => (int) $row['visitor_retention_days'],
        'summary' => ((int) $row['visitor_summary'] === 1),
        'ip_days' => $ip_ready ? (int) $row['visitor_ip_days'] : 0,
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
    return pg_visitor_days_from_input($value, PG_VISITOR_RETENTION_MIN_DAYS, PG_VISITOR_RETENTION_MAX_DAYS);
}

/**
 * A number of days as typed into the settings, within $min and $max, 0 for
 * empty or 0, null for anything else (a list included).
 *
 * @param mixed $value
 * @param int   $min
 * @param int   $max
 * @return int|null
 */
function pg_visitor_days_from_input($value, $min, $max)
{
    if (is_array($value) || is_object($value)) {
        return null;
    }

    $value = trim((string) $value);

    if (($value === '') || ($value === '0')) {
        return 0;
    }

    if (preg_match('/^[0-9]{1,5}$/', $value) !== 1) {
        return null;
    }

    $days = (int) $value;

    if (($days < (int) $min) || ($days > (int) $max)) {
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
 * The paid / organic split of a visit the saved visitor reports group by
 * (pay_per_click_organic) as SQL: 'pay_per_click' when the tracking code
 * carries the pay-per-click flag of the SEO settings, 'organic' when a
 * search engine referred it, otherwise ''.
 *
 * With no flag set nothing counts as paid: an empty pattern would match
 * every visit.
 *
 * @param string $prefix Table name or alias with its dot, or ''
 * @return string
 */
function pg_visitor_paid_organic_sql($prefix = '')
{
    $flag = defined('PAY_PER_CLICK_FLAG') ? trim((string) PAY_PER_CLICK_FLAG) : '';

    return "CASE"
        . (($flag !== '') ? " WHEN (" . $prefix . "tracking_code LIKE '%" . e(escape_like($flag)) . "%') THEN 'pay_per_click'" : '')
        . " WHEN (" . $prefix . "referring_search_engine <> '') THEN 'organic' ELSE '' END";
}

/**
 * The social networks a referring host is counted as social by: domains
 * matched on the host's last two labels (m.facebook.com, l.instagram.com).
 * Pure.
 *
 * @return array
 */
function pg_visitor_social_hosts()
{
    return array(
        'facebook.com', 'fb.com', 'fb.me',
        'instagram.com',
        'x.com', 'twitter.com', 't.co',
        'linkedin.com', 'lnkd.in',
        'youtube.com', 'youtu.be',
        'tiktok.com',
        'pinterest.com', 'pin.it',
        'reddit.com',
        'whatsapp.com', 'wa.me',
        'telegram.org', 't.me',
    );
}

/**
 * The utm_medium values of each channel the medium decides. Pure.
 *
 * @return array channel => list of lower-case mediums
 */
function pg_visitor_channel_mediums()
{
    return array(
        'paid' => array('cpc', 'ppc', 'paid', 'paid_social'),
        'email' => array('email', 'newsletter'),
        'social' => array('social'),
    );
}

/**
 * The channel names in the order the rule tries them. Pure.
 *
 * @return array
 */
function pg_visitor_channels()
{
    return array('paid', 'email', 'ai', 'organic', 'social', 'referral', 'direct');
}

/**
 * The channel of one visit, the rule pg_visitor_channel_sql() runs in the
 * database: the first that matches of paid, email, ai, organic, social,
 * referral, direct. Pure.
 *
 * @param array  $visit tracking_code, utm_medium, referring_host_name, referring_search_engine
 * @param string $flag  The pay-per-click flag of the SEO settings
 * @return string
 */
function pg_visitor_channel($visit, $flag = '')
{
    $flag = trim((string) $flag);
    $code = isset($visit['tracking_code']) ? (string) $visit['tracking_code'] : '';
    $medium = isset($visit['utm_medium']) ? strtolower(trim((string) $visit['utm_medium'])) : '';
    $host = isset($visit['referring_host_name']) ? strtolower(trim((string) $visit['referring_host_name'])) : '';
    $engine = isset($visit['referring_search_engine']) ? (string) $visit['referring_search_engine'] : '';
    $mediums = pg_visitor_channel_mediums();

    if ((($flag !== '') && (stripos($code, $flag) !== false)) || in_array($medium, $mediums['paid'], true)) {
        return 'paid';
    }

    if (in_array($medium, $mediums['email'], true)) {
        return 'email';
    }

    if (($host !== '') && in_array($host, pg_visitor_channel_ai_hosts(), true)) {
        return 'ai';
    }

    if ($engine !== '') {
        return 'organic';
    }

    $labels = explode('.', $host);
    $last_two = implode('.', array_slice($labels, -2));

    if (in_array($medium, $mediums['social'], true) || (($host !== '') && in_array($last_two, pg_visitor_social_hosts(), true))) {
        return 'social';
    }

    return ($host !== '') ? 'referral' : 'direct';
}

/**
 * The AI assistants' hosts with their www. forms (ai_visibility.php), empty
 * when that module is not loaded.
 *
 * @return array
 */
function pg_visitor_channel_ai_hosts()
{
    return function_exists('pg_visitor_ai_host_list') ? pg_visitor_ai_host_list() : array();
}

/**
 * A list of fixed strings (host names, mediums written in this file, never
 * request input) as a quoted SQL IN () list. Quoted without the connection,
 * so the SQL can be built before one is open. Pure.
 *
 * @param array $values
 * @return string
 */
function pg_visitor_sql_list($values)
{
    $out = array();

    foreach ((array) $values as $value) {
        $out[] = "'" . str_replace(array('\\', "'"), array('\\\\', "''"), (string) $value) . "'";
    }

    return implode(', ', $out);
}

/**
 * The channel of a visit as SQL over a `visitors` row, the rule
 * pg_visitor_channel() applies: paid (the tracking code carries the
 * pay-per-click flag, or a paid utm_medium), email, ai (an AI assistant's
 * host), organic (a search engine referred it), social (utm_medium social
 * or a social network's host), referral (any other host), direct.
 *
 * With no flag set only the medium makes a visit paid: an empty pattern
 * would match every visit. The host is stored as the referrer gave it, www.
 * included: the AI list carries both forms, the social match reads the last
 * two labels.
 *
 * @param string $prefix Table name or alias with its dot, or ''
 * @return string
 */
function pg_visitor_channel_sql($prefix = '')
{
    $flag = defined('PAY_PER_CLICK_FLAG') ? trim((string) PAY_PER_CLICK_FLAG) : '';
    $mediums = pg_visitor_channel_mediums();
    $medium = "LOWER(TRIM(" . $prefix . "utm_medium))";
    $host = "LOWER(" . $prefix . "referring_host_name)";

    $paid = $medium . " IN (" . pg_visitor_sql_list($mediums['paid']) . ")";

    if ($flag !== '') {
        $paid = "(" . $prefix . "tracking_code LIKE '%" . e(escape_like($flag)) . "%') OR " . $paid;
    }

    $ai_hosts = pg_visitor_channel_ai_hosts();
    $ai = (count($ai_hosts) > 0) ? " WHEN (" . $host . " IN (" . pg_visitor_sql_list($ai_hosts) . ")) THEN 'ai'" : '';

    return "CASE"
        . " WHEN (" . $paid . ") THEN 'paid'"
        . " WHEN (" . $medium . " IN (" . pg_visitor_sql_list($mediums['email']) . ")) THEN 'email'"
        . $ai
        . " WHEN (" . $prefix . "referring_search_engine <> '') THEN 'organic'"
        . " WHEN (" . $medium . " IN (" . pg_visitor_sql_list($mediums['social']) . ")"
        . " OR SUBSTRING_INDEX(" . $host . ", '.', -2) IN (" . pg_visitor_sql_list(pg_visitor_social_hosts()) . ")) THEN 'social'"
        . " WHEN (" . $prefix . "referring_host_name <> '') THEN 'referral'"
        . " ELSE 'direct' END";
}

/**
 * What a day's visits are counted by in the summary: name => SQL over a
 * `visitors` row.
 *
 * The names are the visitor report's own summarize-by keys, so that report
 * can ask the summary for exactly what it groups by. The full referrer
 * address and the postcode are left out: nearly every visit has a value of
 * its own there, and a day's worth of them would be as large as the rows.
 * The device reads the user agent through the list of visitor texts
 * (pg_visitor_text_sql()); the query takes pg_visitor_text_joins() for it.
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
        'pay_per_click_organic' => pg_visitor_paid_organic_sql(),
        'channel' => pg_visitor_channel_sql(),
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
        'device' => $has_user_agent ? pg_visitor_device_sql(pg_visitor_text_sql('user_agent')) : "''",
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
 * Where the raw rows take over from the summary: pg_visitor_rows_from().
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
    return pg_visitor_rows_from();
}

/**
 * The first moment from which the raw visitor rows are complete: the
 * retention boundary, or midnight of an earlier day while the summary has
 * not yet reached the days before the boundary (those rows are still there,
 * since only summarized days are removed). 0 with records kept without a
 * limit.
 *
 * The summary is written for every day, so it also covers days after this
 * moment; a screen that filters or lists single visits reads it only before.
 *
 * @return int
 */
function pg_visitor_rows_from()
{
    $settings = pg_visitor_retention_settings();

    if (($settings === false) || ($settings['days'] <= 0)) {
        return 0;
    }

    $cutoff = pg_visitor_retention_cutoff($settings['days'], time());

    if (!$settings['summary']) {
        return $cutoff;
    }

    $cutoff_day = date('Y-m-d', $cutoff);
    $oldest_day = pg_visitor_oldest_day();

    $summarized = ($oldest_day !== '') ? pg_visitor_summarized_days($oldest_day, $cutoff_day) : array();

    return min($cutoff, (int) strtotime(pg_visitor_summary_covered_until($summarized, $oldest_day, $cutoff_day) . ' 00:00:00'));
}

/**
 * The day of the oldest visitor row, Y-m-d by the site's clock, '' when the
 * table is empty.
 *
 * @return string
 */
function pg_visitor_oldest_day()
{
    $oldest = db_value("SELECT MIN(start_timestamp) FROM visitors");

    return (($oldest === null) || ($oldest === '') || ((int) $oldest <= 0)) ? '' : date('Y-m-d', (int) $oldest);
}

/**
 * The days from $from to $to (both Y-m-d, both included) whose summary is
 * complete, that is whose totals row is there.
 *
 * @param string $from
 * @param string $to
 * @return array Y-m-d strings
 */
function pg_visitor_summarized_days($from, $to)
{
    $days = db_values(
        "SELECT stat_date FROM visitor_stats_daily
        WHERE (dimension = '') AND (stat_date >= '" . e($from) . "') AND (stat_date <= '" . e($to) . "')");

    return array_map('strval', (array) $days);
}

/**
 * A calendar day $offset days from $day, both Y-m-d. Counted on UTC noon so
 * that a daylight saving change in the site's zone never skips or repeats a
 * day.
 *
 * @param string $day
 * @param int    $offset
 * @return string
 */
function pg_visitor_day_add($day, $offset)
{
    return gmdate('Y-m-d', (int) strtotime($day . ' 12:00:00 UTC') + ((int) $offset * 86400));
}

/**
 * Days the summary may be behind before System Status reports it: the job
 * writes yesterday on its first run of the day, so one day back is normal
 * and two is a run that has not come yet.
 */
if (!defined('PG_VISITOR_SUMMARY_LATE_DAYS')) {
    define('PG_VISITOR_SUMMARY_LATE_DAYS', 2);
}

/**
 * How many days back the summary's last day is from $today.
 *
 * With nothing summarized it counts from the day before the oldest visit,
 * so a job that never ran shows as late once there are days to write; with
 * no visit there is nothing to write and the answer is 0.
 *
 * @param string $last       Last summarized day (Y-m-d), '' for none
 * @param string $oldest_day Day of the oldest visitor row, '' for none
 * @param string $today      Y-m-d
 * @return int
 */
function pg_visitor_summary_lag($last, $oldest_day, $today)
{
    if ($last === '') {
        if ($oldest_day === '') {
            return 0;
        }

        $last = pg_visitor_day_add($oldest_day, -1);
    }

    // Noon UTC on both sides keeps a clock change out of the count.
    $days = (int) round(((int) strtotime($today . ' 12:00:00 UTC') - (int) strtotime($last . ' 12:00:00 UTC')) / 86400);

    return max(0, $days);
}

/**
 * The days a run of the summary writes, in the order it writes them.
 *
 * Yesterday and the day before come first: written again when $rewrite_recent
 * (an order completed after midnight on a visit begun before it changes
 * them), otherwise only if missing. Then the days the removal waits for,
 * oldest first: the days before the retention boundary not summarized yet.
 * Then every other day not summarized, backwards from yesterday to the
 * oldest visit. Days before the oldest visit have no rows to count.
 *
 * @param array  $summarized     Y-m-d of the days already summarized
 * @param string $oldest_day     Day of the oldest visitor row, '' for none
 * @param string $yesterday      Y-m-d
 * @param string $cutoff_day     Day of the retention boundary, '' for none
 * @param bool   $rewrite_recent Whether the last two days are written again
 * @return array Y-m-d strings
 */
function pg_visitor_summary_days_due($summarized, $oldest_day, $yesterday, $cutoff_day, $rewrite_recent)
{
    $out = array();

    if (($oldest_day === '') || ($oldest_day > $yesterday)) {
        return $out;
    }

    $done = array_flip(array_map('strval', (array) $summarized));

    foreach (array($yesterday, pg_visitor_day_add($yesterday, -1)) as $day) {
        if (($day >= $oldest_day) && ($rewrite_recent || !isset($done[$day]))) {
            $out[] = $day;
        }
    }

    $recent_end = pg_visitor_day_add($yesterday, -2);

    if ($cutoff_day !== '') {
        for ($day = $oldest_day; ($day < $cutoff_day) && ($day <= $recent_end); $day = pg_visitor_day_add($day, 1)) {
            if (!isset($done[$day])) {
                $out[] = $day;
            }
        }
    }

    $floor = (($cutoff_day !== '') && ($cutoff_day > $oldest_day)) ? $cutoff_day : $oldest_day;

    for ($day = $recent_end; $day >= $floor; $day = pg_visitor_day_add($day, -1)) {
        if (!isset($done[$day])) {
            $out[] = $day;
        }
    }

    return $out;
}

/**
 * The first day from the oldest visit on that is not summarized, at most the
 * day of the retention boundary: every row before its midnight is counted in
 * the summary, so the removal may go up to there.
 *
 * @param array  $summarized Y-m-d of the days summarized
 * @param string $oldest_day Day of the oldest visitor row, '' for none
 * @param string $cutoff_day Day of the retention boundary
 * @return string Y-m-d
 */
function pg_visitor_summary_covered_until($summarized, $oldest_day, $cutoff_day)
{
    if (($oldest_day === '') || ($oldest_day >= $cutoff_day)) {
        return $cutoff_day;
    }

    $done = array_flip(array_map('strval', (array) $summarized));

    for ($day = $oldest_day; $day < $cutoff_day; $day = pg_visitor_day_add($day, 1)) {
        if (!isset($done[$day])) {
            return $day;
        }
    }

    return $cutoff_day;
}

/**
 * Whether a run writes yesterday and the day before again: on the first run
 * of a day, then at most once an hour.
 *
 * @param array  $last      pg_visitor_cleanup_last()
 * @param string $yesterday Y-m-d
 * @param int    $now
 * @return bool
 */
function pg_visitor_summary_recent_due($last, $yesterday, $now)
{
    return !isset($last['recent_day']) || ((string) $last['recent_day'] !== $yesterday)
        || !isset($last['recent_at']) || ((int) $last['recent_at'] <= ((int) $now - 3600));
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
    $rows_from = pg_visitor_rows_from();

    if ($rows_from <= 0) {
        return array();
    }

    $through = date('Y-m-d', $rows_from - 1);

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

    $figures = pg_visitor_summary_figures();

    $insert = "INSERT INTO visitor_stats_daily (stat_date, dimension, dim_value, is_rest, " . implode(', ', $figures) . ") VALUES ";

    if (!is_array($span)) {
        return 0;
    }

    // A day without visits still gets its totals row, all zeros, so that it
    // counts as summarized and is not looked at again.
    if ((int) $span['n'] === 0) {
        db($insert . "('" . e($date) . "', '', '', 0" . str_repeat(', 0', count($figures)) . ")");
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
            FROM " . $source . pg_visitor_text_joins($expression) . "
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
 *               'summarized' => int, 'seconds' => float,
 *               'recent_day' => string, 'recent_at' => int)
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
        'recent_day' => isset($last['recent_day']) ? (string) $last['recent_day'] : '',
        'recent_at' => isset($last['recent_at']) ? (int) $last['recent_at'] : 0,
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
 * adds is the rows that crossed the boundary since. Also with records kept
 * without a limit, as long as the daily summary is on or their IP addresses
 * go after a period.
 *
 * @return bool
 */
function pg_visitor_cleanup_due()
{
    $settings = pg_visitor_retention_settings();

    if (($settings === false) || (($settings['days'] <= 0) && ($settings['ip_days'] <= 0) && !$settings['summary'])) {
        return false;
    }

    $last = pg_visitor_cleanup_last();

    return $last['backlog'] || ($last['checked_at'] < (time() - 3600));
}

/**
 * Write the daily summary of every day through yesterday when it is on, and
 * remove the visitor rows older than the retention period.
 *
 * Written to be stopped at any point and picked up again:
 *
 * - The summary goes a day at a time and a day is marked done only by its
 *   last row, so a run cut off in the middle writes that day again. Its
 *   order is pg_visitor_summary_days_due(): yesterday and the day before
 *   (written again on the first run of a day and then hourly), the days the
 *   removal waits for, then backwards to the oldest visit.
 * - Rows are removed in chunks of $chunk by primary key, each chunk its own
 *   short statement and its own transaction. A visit being written meanwhile
 *   is a row at the far end of the key and is never waited on; nothing locks
 *   the table as a whole the way one large DELETE would.
 * - With the summary on, only days already summarized are removed.
 * - Rows the hourly rollup backfill has not counted yet are kept until it
 *   has; the backfill gets a share of this run first.
 * - Then, with the time left, the IP address is taken out of the visits
 *   older than its own period (pg_visitor_ip_clear_run()); that also runs
 *   when the records themselves are kept without a limit.
 *
 * The budget is wall clock: the run returns after the statement that crosses
 * it, so the caller's wait is about $budget_seconds plus one chunk.
 *
 * @param int $budget_seconds
 * @param int $chunk Rows per DELETE
 * On a table split into months (includes/fn/visitor_partitions.php) the
 * months that lie wholly before the boundary go first, each in a moment with
 * DROP PARTITION; the chunks then remove what is left of the month the
 * boundary falls in. Not while the rollup backfill is behind: a dropped month
 * cannot leave the rows above its cursor in place.
 *
 * @return array('state' => 'unavailable'|'off'|'busy'|'done'|'more',
 *               'days' => int, 'summary' => bool, 'cutoff' => int,
 *               'summarized' => int, 'deleted' => int, 'months' => int,
 *               'ip_days' => int, 'ip_cleared' => int, 'seconds' => float)
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
        'months' => 0,
        'ip_days' => 0,
        'ip_cleared' => 0,
        'seconds' => 0.0,
    );

    $settings = pg_visitor_retention_settings();

    if ($settings === false) {
        return $result;
    }

    $result['days'] = $settings['days'];
    $result['summary'] = $settings['summary'];
    $result['ip_days'] = $settings['ip_days'];

    if (($settings['days'] <= 0) && ($settings['ip_days'] <= 0) && !$settings['summary']) {
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

    if (($cutoff > 0) && function_exists('pg_visitor_backfill_state')) {
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
    $last = pg_visitor_cleanup_last();
    $recent_day = $last['recent_day'];
    $recent_at = $last['recent_at'];

    if ($settings['summary']) {

        $today = date('Y-m-d');
        $yesterday = pg_visitor_day_add($today, -1);
        $cutoff_day = ($cutoff > 0) ? date('Y-m-d', $cutoff) : '';
        $oldest_day = pg_visitor_oldest_day();

        $summarized = ($oldest_day !== '') ? pg_visitor_summarized_days($oldest_day, $yesterday) : array();
        $rewrite_recent = pg_visitor_summary_recent_due($last, $yesterday, time());

        $due = pg_visitor_summary_days_due($summarized, $oldest_day, $yesterday, $cutoff_day, $rewrite_recent);
        $written = 0;
        $recent_count = count(array_intersect(array_slice($due, 0, 2), array($yesterday, pg_visitor_day_add($yesterday, -1))));

        foreach ($due as $day) {

            if (microtime(true) >= $summary_deadline) {
                break;
            }

            pg_visitor_summarize_day($day);

            $summarized[] = $day;
            $written++;
            $result['summarized']++;

            // Both recent days written: the next rewrite is an hour away.
            if ($rewrite_recent && ($written === $recent_count)) {
                $recent_day = $yesterday;
                $recent_at = time();
            }
        }

        $summary_complete = ($written === count($due));

        // Only what the summary already holds goes.
        if ($cutoff > 0) {
            $delete_before = min($cutoff, (int) strtotime(pg_visitor_summary_covered_until($summarized, $oldest_day, $cutoff_day) . ' 00:00:00'));
        }
    }

    // -- The removal ---------------------------------------------------------
    $delete_done = ($delete_before <= 0);

    // Whole months first, where the table is split into them.
    if (!$delete_done && ($id_ceiling === null)) {

        $dropped = pg_visitor_partition_drop_run($delete_before, $deadline);

        $result['deleted'] += $dropped['rows'];
        $result['months'] = $dropped['months'];
    }

    if (!$delete_done) {

        // The last row to go, found once through the start_timestamp index.
        $last_id = pg_visitor_last_id_before($delete_before);

        if ($id_ceiling !== null) {
            $last_id = min($last_id, $id_ceiling);
        }

        if ($last_id <= 0) {
            $delete_done = true;
        }

        // The chunks walk the primary key from its low end, where the oldest
        // rows are (pg_visitor_id_window_end()). A row in the window that
        // started after the boundary (written out of order) stays.
        $cursor = 0;

        while (!$delete_done && (microtime(true) < $deadline)) {

            $upper = pg_visitor_id_window_end($cursor, $last_id, $chunk);

            if ($upper <= $cursor) {
                $delete_done = true;
                break;
            }

            $chunk_started = microtime(true);

            $deleted = db_q(
                "DELETE FROM visitors
                WHERE (id > ?) AND (id <= ?) AND (start_timestamp < ?)", array((int) $cursor, (int) $upper, (int) $delete_before));

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

    // The IP addresses of the visits older than their own period
    // (includes/fn/visitor_ip.php), with the time the run has left. After the
    // removal, so a row about to go is not written first.
    $ip_done = true;

    if ($settings['ip_days'] > 0) {

        $ip = pg_visitor_ip_clear_run($settings['ip_days'], $deadline, $chunk, $row_locks);

        $result['ip_cleared'] = $ip['cleared'];
        $ip_done = ($ip['state'] !== 'more');
    }

    // The referring addresses and browsers no remaining visit names any more
    // (includes/fn/visitor_texts.php), with the time the run has left. It
    // goes by the oldest visit still there, so it is safe however far the
    // removal got.
    if (microtime(true) < $deadline) {
        pg_visitor_texts_prune($deadline);
    }

    db("SELECT RELEASE_LOCK(" . pg_visitor_cleanup_lock_sql() . ")");

    $backlog = !$delete_done || !$summary_complete || ($id_ceiling !== null) || !$ip_done;

    $result['state'] = $backlog ? 'more' : 'done';
    $result['seconds'] = round(microtime(true) - $started, 2);

    @file_put_contents(pg_visitor_cleanup_marker(), json_encode(array(
        'checked_at' => time(),
        'backlog' => $backlog,
        'deleted' => $result['deleted'],
        'summarized' => $result['summarized'],
        'months' => $result['months'],
        'ip_cleared' => $result['ip_cleared'],
        'seconds' => $result['seconds'],
        'recent_day' => $recent_day,
        'recent_at' => $recent_at,
    )));

    return $result;
}

/**
 * The highest id among the visits that started last before $before, found
 * through the start_timestamp index: the latest start before it, then the
 * highest id of that second. Ordering by the time alone lets both engines
 * read the index from its end; adding the id to the order made MyISAM sort
 * every row before the boundary. 0 when no visit started before it.
 *
 * @param int $before
 * @return int
 */
function pg_visitor_last_id_before($before)
{
    $last_start = (int) db_value(
        "SELECT start_timestamp
        FROM visitors
        WHERE start_timestamp < " . (int) $before . "
        ORDER BY start_timestamp DESC
        LIMIT 1");

    return ($last_start > 0)
        ? (int) db_value("SELECT MAX(id) FROM visitors WHERE start_timestamp = " . $last_start)
        : 0;
}

/**
 * The last id of the next window of up to $chunk visits after $cursor, no
 * further than $last_id; $cursor or less when there is none.
 *
 * The cleanup's chunks walk the primary key from its low end, where the
 * oldest rows are. Each one is a window of ids read from the key alone, then
 * changed by that range: the server cannot pick another index for it, and
 * every chunk takes the same short time however large the table is. ORDER BY
 * id LIMIT on the statement itself was tried first and was slow: the server
 * took the start_timestamp index.
 *
 * @param int $cursor
 * @param int $last_id
 * @param int $chunk
 * @return int
 */
function pg_visitor_id_window_end($cursor, $last_id, $chunk)
{
    return (int) db_value(
        "SELECT MAX(id)
        FROM (
            SELECT id
            FROM visitors
            WHERE (id > " . (int) $cursor . ") AND (id <= " . (int) $last_id . ")
            ORDER BY id
            LIMIT " . (int) $chunk . "
        ) AS visitor_chunk");
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
 * Rows and bytes as pg_visitor_table_figures() gives them: the records
 * counted where they are counted, the size of the table's file.
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
 * No database access.
 *
 * @param array $figures pg_visitor_table_figures()
 * @return bool
 */
function pg_visitor_table_is_large($figures)
{
    if (!is_array($figures)) {
        return false;
    }

    $limits = pg_visitor_health_thresholds();

    $rows = isset($figures['rows']) ? (int) $figures['rows'] : 0;
    $file = isset($figures['file']) ? (float) $figures['file'] : 0.0;

    return ($rows >= $limits['rows']) || ($file >= $limits['bytes']);
}

// -- How large the table is ---------------------------------------------------
//
// information_schema.TABLES is a guess on InnoDB: TABLE_ROWS and the page
// counts behind DATA_LENGTH and INDEX_LENGTH come from a sample of pages,
// refreshed once a tenth of the rows has changed, so after a large cleanup or
// a rebuild they can be far off for a while. The figures below are read from
// where the truth is when it can be reached from here, and say where each one
// came from; the statistics stand in for the rest.

/**
 * Above this many records by the server's estimate an InnoDB visitors table
 * is not counted for the screen. COUNT(*) reads the smallest index from end to
 * end, about a second for a million rows that are not in memory; at that size
 * the estimate is near enough, and is marked as one.
 */
define('PG_VISITOR_COUNT_MAX_ROWS', 1000000);

/**
 * A database or table name as MySQL and MariaDB spell it in file names:
 * letters, digits and the underscore as they are, every other ASCII
 * character '@' and its code in four hex digits ('-' is '@002d'). Characters
 * beyond ASCII have a table of their own, not repeated here: such a name
 * gives null, and the size is read from elsewhere.
 *
 * No database access.
 *
 * @param string $name
 * @return string|null
 */
function pg_visitor_db_file_name($name)
{
    $name = (string) $name;

    if (($name === '') || (preg_match('/[^\x00-\x7f]/', $name) === 1)) {
        return null;
    }

    return preg_replace_callback('/[^A-Za-z0-9_]/', function ($match) {
        return sprintf('@%04x', ord($match[0]));
    }, $name);
}

/**
 * The files a table keeps under the database server's data directory, as
 * sets to try one after the other: InnoDB's own .ibd file, MyISAM's .MYD and
 * .MYI together. The database's folder is tried as it is spelled and in lower
 * case, which is how a server with lower_case_table_names = 1 (the default on
 * Windows) names it.
 *
 * No database access.
 *
 * @param string $datadir  @@datadir
 * @param string $database DATABASE()
 * @param string $table
 * @param string $engine   lower case
 * @return array of arrays of paths; empty when there is nothing to look for
 */
function pg_visitor_table_file_paths($datadir, $database, $table, $engine)
{
    $datadir = rtrim((string) $datadir, '/\\');
    $folder = pg_visitor_db_file_name($database);
    $file = pg_visitor_db_file_name($table);

    if ($engine === 'innodb') {
        $extensions = array('.ibd');
    } elseif ($engine === 'myisam') {
        $extensions = array('.MYD', '.MYI');
    } else {
        return array();
    }

    if (($datadir === '') || ($folder === null) || ($file === null)) {
        return array();
    }

    $sets = array();

    foreach (array_unique(array($folder, strtolower($folder))) as $spelling) {

        $paths = array();

        foreach ($extensions as $extension) {
            $paths[] = $datadir . '/' . $spelling . '/' . $file . $extension;
        }

        $sets[] = $paths;
    }

    return $sets;
}

/**
 * Where the files of a table split into partitions are: one .ibd per
 * partition, named <table>#p#<partition> by MySQL 8 and <table>#P#<partition>
 * by MySQL 5.7 and MariaDB, as glob() patterns, the database's folder as it
 * is spelled and in lower case.
 *
 * No database access.
 *
 * @param string $datadir  @@datadir
 * @param string $database DATABASE()
 * @param string $table
 * @return array of patterns; empty when there is nothing to look for
 */
function pg_visitor_partition_file_patterns($datadir, $database, $table)
{
    $datadir = rtrim((string) $datadir, '/\\');
    $folder = pg_visitor_db_file_name($database);
    $file = pg_visitor_db_file_name($table);

    if (($datadir === '') || ($folder === null) || ($file === null)) {
        return array();
    }

    $patterns = array();

    foreach (array_unique(array($folder, strtolower($folder))) as $spelling) {
        $patterns[] = $datadir . '/' . $spelling . '/' . $file . '#[pP]#*.ibd';
    }

    return $patterns;
}

/**
 * The size of the visitors table's file on disk, and where the figure came
 * from:
 *
 * 'file'     read from the file itself, when the database server runs on
 *            this machine and the web server may look into its data
 *            directory (many servers keep that directory to themselves);
 * 'server'   the file size the database server gives: on InnoDB from its
 *            list of tablespaces (INNODB_TABLESPACES on MySQL 8,
 *            INNODB_SYS_TABLESPACES on MySQL 5.7 and MariaDB, both behind
 *            the PROCESS privilege), on MyISAM from the statistics, which
 *            are the sizes of its two files;
 * 'estimate' the rows, the indexes and the free space of InnoDB's statistics
 *            added up;
 * 'shared'   the same estimate, for a table kept in InnoDB's shared file
 *            (innodb_file_per_table off), which has no file of its own.
 *
 * A table split into months has a file per month; their sizes are added up,
 * from the files or from the server's list.
 *
 * @param array|null $catalog_row pg_db_table_catalog()['visitors']
 * @return array('bytes' => float, 'source' => string)
 */
function pg_visitor_table_file_size($catalog_row)
{
    $engine = isset($catalog_row['engine']) ? (string) $catalog_row['engine'] : '';
    $data = isset($catalog_row['data']) ? (float) $catalog_row['data'] : 0.0;
    $index = isset($catalog_row['index']) ? (float) $catalog_row['index'] : 0.0;
    $free = isset($catalog_row['free']) ? (float) $catalog_row['free'] : 0.0;

    // MyISAM counts the room of removed rows inside DATA_LENGTH; InnoDB
    // reports the free extents of the file apart from it.
    $statistics = ($engine === 'myisam')
        ? array('bytes' => $data + $index, 'source' => 'server')
        : array('bytes' => $data + $index + $free, 'source' => 'estimate');

    if (!in_array($engine, array('innodb', 'myisam'), true) || !class_exists('db') || empty(db::$con)) {
        return $statistics;
    }

    $result = pg_visitor_move_query("SELECT @@datadir, DATABASE(), @@innodb_file_per_table");
    $server = $result ? mysqli_fetch_row($result) : null;

    if (!is_array($server)) {
        return $statistics;
    }

    $partitioned = ($engine === 'innodb') && in_array(pg_visitor_partition_read('visitors')['state'], array('monthly', 'other'), true);

    // A data directory of a server on another machine is a path there, which
    // may name a file of another database here.
    if (defined('DB_HOST') && pg_visitor_move_db_is_local(DB_HOST) && $partitioned) {

        foreach (pg_visitor_partition_file_patterns($server[0], $server[1], 'visitors') as $pattern) {

            $files = @glob($pattern);

            if (!is_array($files) || (count($files) === 0)) {
                continue;
            }

            $bytes = 0.0;

            foreach ($files as $path) {

                clearstatcache(true, $path);

                $size = @filesize($path);

                if (($size === false) || ($size < 0)) {
                    continue 2;
                }

                $bytes += (float) $size;
            }

            return array('bytes' => $bytes, 'source' => 'file');
        }

    } elseif (defined('DB_HOST') && pg_visitor_move_db_is_local(DB_HOST)) {

        foreach (pg_visitor_table_file_paths($server[0], $server[1], 'visitors', $engine) as $paths) {

            $bytes = 0.0;

            foreach ($paths as $path) {

                // PHP keeps what it learned of a file for the rest of the
                // request; the rebuild reads the size before and after.
                clearstatcache(true, $path);

                $size = @filesize($path);

                if (($size === false) || ($size < 0)) {
                    continue 2;
                }

                $bytes += (float) $size;
            }

            return array('bytes' => $bytes, 'source' => 'file');
        }
    }

    if ($engine === 'myisam') {
        return $statistics;
    }

    $folder = pg_visitor_db_file_name($server[1]);

    if ($folder !== null) {

        foreach (array('INNODB_TABLESPACES', 'INNODB_SYS_TABLESPACES') as $view) {

            // A partition's tablespace is <folder>/visitors#p#<partition>
            // (#P# before MySQL 8); LIKE escapes the underscores of the
            // folder's name.
            $result = pg_visitor_move_query($partitioned
                ? "SELECT SUM(FILE_SIZE)
                    FROM information_schema." . $view . "
                    WHERE LOWER(NAME) LIKE '" . e(escape_like(strtolower($folder . '/visitors#p#'))) . "%'"
                : "SELECT FILE_SIZE
                    FROM information_schema." . $view . "
                    WHERE NAME = '" . e($folder . '/visitors') . "'");

            $row = $result ? mysqli_fetch_row($result) : null;

            if (is_array($row) && ((float) $row[0] > 0)) {
                return array('bytes' => (float) $row[0], 'source' => 'server');
            }
        }
    }

    if (($server[2] !== null) && in_array(strtoupper(trim((string) $server[2])), array('0', 'OFF'), true)) {
        $statistics['source'] = 'shared';
    }

    return $statistics;
}

/**
 * The figures of the visitors table the Database Engine card and the System
 * Status line show, each with where it came from.
 *
 * rows: MyISAM keeps the exact count, which is what its statistics give; an
 * InnoDB table is counted up to PG_VISITOR_COUNT_MAX_ROWS by the estimate,
 * and above it the estimate stands ('estimate'). file:
 * pg_visitor_table_file_size(). free: the free space inside the file as the
 * server reports it (on InnoDB the wholly free extents, which a rebuild gives
 * back to the disk).
 *
 * @param array $catalog_row pg_db_table_catalog()['visitors']
 * @return array('rows' => int, 'rows_source' => 'counted'|'estimate',
 *               'file' => float, 'file_source' => string, 'free' => float)
 */
function pg_visitor_table_figures($catalog_row)
{
    $engine = isset($catalog_row['engine']) ? (string) $catalog_row['engine'] : '';
    $rows = isset($catalog_row['rows']) ? (int) $catalog_row['rows'] : 0;
    $rows_source = ($engine === 'myisam') ? 'counted' : 'estimate';

    if (($engine === 'innodb') && ($rows <= PG_VISITOR_COUNT_MAX_ROWS) && class_exists('db') && !empty(db::$con)) {

        $result = pg_visitor_move_query("SELECT COUNT(*) FROM visitors");
        $row = $result ? mysqli_fetch_row($result) : null;

        if (is_array($row)) {
            $rows = (int) $row[0];
            $rows_source = 'counted';
        }
    }

    $file = pg_visitor_table_file_size($catalog_row);

    return array(
        'rows' => $rows,
        'rows_source' => $rows_source,
        'file' => $file['bytes'],
        'file_source' => $file['source'],
        'free' => isset($catalog_row['free']) ? (float) $catalog_row['free'] : 0.0,
    );
}

/**
 * A figure for the screen, with "about" in front when it is an estimate.
 *
 * @param string $text   the number or the size, formatted
 * @param string $source as pg_visitor_table_figures() gives it
 * @return string
 */
function pg_visitor_figure_text($text, $source)
{
    if (in_array($source, array('estimate', 'shared'), true)) {
        return lang(array('string' => 'about {var:1}', 'vars' => array($text)));
    }

    return (string) $text;
}

/**
 * Where a figure came from, in a few words for the screen.
 *
 * @param string $source 'counted', 'file', 'server', 'estimate' or 'shared'
 * @return string
 */
function pg_visitor_figure_source_text($source)
{
    switch ($source) {

        case 'counted':
            return lang('counted');

        case 'file':
            return lang('read from the file');

        case 'server':
            return lang('reported by the database server');

        case 'shared':
            return lang('estimate; the table is in the shared InnoDB file');
    }

    return lang('estimate from the server statistics');
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
            return lang('Visitor records are kept without a limit, so nothing was removed. The period is set under Site Settings › Visitors and privacy › Visitor Tracking.');

        case 'busy':
            return lang('Another visitor record cleanup is running (the general job, or another tab). Try again in a minute.');
    }

    $ip_text = !empty($run['ip_cleared'])
        ? ' ' . lang(array(
            'string' => 'The IP address was taken out of {var:1} record(s) older than {var:2} days.',
            'vars' => array(pg_format_number($run['ip_cleared'], 0), $run['ip_days']),
        ))
        : '';

    // Records kept without a limit: the run only took IP addresses out.
    if ($run['days'] <= 0) {
        return lang('Visitor records are kept without a limit, so nothing was removed. The period is set under Site Settings › Visitors and privacy › Visitor Tracking.') . $ip_text;
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

    if (!empty($run['months'])) {
        $text .= ' ' . lang(array(
            'string' => '{var:1} whole month(s) of them went at once.',
            'vars' => array(pg_format_number($run['months'], 0)),
        ));
    }

    $text .= $ip_text;

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
//
// Where the server can partition the table, the copy is split into months
// (includes/fn/visitor_partitions.php), so a MyISAM table comes out of the
// move on InnoDB and split at once. An InnoDB table that is not split, too
// large for the upgrade to split it in place, goes through the same copy:
// that move is the 'split' kind, the one from MyISAM the 'engine' kind.

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
 * state: '' never looked, 'none' there is nothing to move (the table is on
 * InnoDB and split into months, or this server cannot split it), 'waiting'
 * not started ('reason' says why), 'copying', 'swapped' (the copy is in
 * place, the old table not removed yet), 'done', 'failed' ('error'). kind:
 * 'engine' a move from MyISAM, 'split' an InnoDB table split into months.
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
        'kind' => (isset($last['kind']) && ($last['kind'] === 'split')) ? 'split' : 'engine',
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
        'bytes_source' => isset($last['bytes_source']) ? (string) $last['bytes_source'] : '',
        'finished_at' => isset($last['finished_at']) ? (int) $last['finished_at'] : 0,
        'failed_at' => isset($last['failed_at']) ? (int) $last['failed_at'] : 0,
        'blind' => !empty($last['blind']),
        'blind_failed' => !empty($last['blind_failed']),
    );
}

/**
 * Write the marker file.
 *
 * @param array $last pg_visitor_move_last() with the changes
 */
function pg_visitor_move_save($last)
{
    $last['blind_failed'] = pg_visitor_blind_failed_after(!empty($last['blind_failed']), !empty($last['blind']), isset($last['state']) ? (string) $last['state'] : '');

    $marker = pg_visitor_move_marker();

    if (!is_dir(dirname($marker))) {
        @mkdir(dirname($marker), 0755, true);
    }

    @file_put_contents($marker, json_encode($last), LOCK_EX);
}

/**
 * Whether the general job should look at the move now: on every tick while
 * one is under way or the operator asked for one, otherwise once an hour
 * (once a day when there is nothing to move).
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
 * Room on the disk left over for the rest of the server once a move or a
 * rebuild has taken what it needs: the server's logs, temporary tables and
 * the site's other writes while it runs.
 */
define('PG_VISITOR_DISK_MARGIN', 268435456);

/**
 * The most records left with which the general job moves or rebuilds the
 * table without reading the free disk space (pg_visitor_blind_ok()).
 */
define('PG_VISITOR_BLIND_MAX_ROWS', 5000000);

/**
 * The most room those records may take, data and indexes, for the same.
 */
define('PG_VISITOR_BLIND_MAX_BYTES', 2147483648);

/**
 * Room one visit takes in a fresh copy of the table, data and indexes, at
 * most: the figure a record count is turned into room with.
 */
define('PG_VISITOR_ROW_BYTES', 384);

/**
 * The room the rows that are left take, data and indexes: what a copy of the
 * table holds.
 *
 * MyISAM counts the room of removed rows inside its data figure and reports
 * it as free; InnoDB keeps that room outside the data figure.
 *
 * No database access.
 *
 * @param array $catalog_row pg_db_table_catalog()['visitors']
 * @return float bytes
 */
function pg_visitor_rows_room($catalog_row)
{
    $data = isset($catalog_row['data']) ? (float) $catalog_row['data'] : 0.0;
    $free = isset($catalog_row['free']) ? (float) $catalog_row['free'] : 0.0;
    $index = isset($catalog_row['index']) ? (float) $catalog_row['index'] : 0.0;

    if (isset($catalog_row['engine']) && ($catalog_row['engine'] === 'myisam')) {
        $data = max(0.0, $data - $free);
    }

    return $data + $index;
}

/**
 * The room the rows that are left would take in a fresh copy, for deciding
 * whether they are small against the file: the smaller of the server's
 * statistics and PG_VISITOR_ROW_BYTES for each record.
 *
 * After a large delete InnoDB keeps the emptied pages reserved for the table
 * and counts them in its data figure: with half of 10 million visits removed
 * the statistics still said 2.9 GB for the 5 million left in a 3.5 GB file.
 * The record count follows the delete.
 *
 * No database access.
 *
 * @param array $catalog_row pg_db_table_catalog()['visitors']
 * @return float bytes
 */
function pg_visitor_rows_room_estimate($catalog_row)
{
    $room = pg_visitor_rows_room($catalog_row);
    $rows = isset($catalog_row['rows']) ? max(0, (int) $catalog_row['rows']) : 0;

    if ($rows <= 0) {
        return $room;
    }

    return min($room, (float) $rows * PG_VISITOR_ROW_BYTES);
}

/**
 * Free disk space the move asks for before the general job starts it on its
 * own, from the figures of the table now.
 *
 * The copy holds the rows that are left (pg_visitor_rows_room_estimate());
 * InnoDB keeps rows in up to about one
 * and a half times the room of MyISAM, and a table split into months reserves
 * a few megabytes in each month's file. Half again on top of the rows, and
 * PG_VISITOR_DISK_MARGIN for the rest of the server. The old table goes once
 * the copy is in place.
 *
 * No database access.
 *
 * @param array $catalog_row pg_db_table_catalog()['visitors']
 * @return float bytes
 */
function pg_visitor_move_disk_needed($catalog_row)
{
    return (1.5 * pg_visitor_rows_room_estimate($catalog_row)) + (float) PG_VISITOR_DISK_MARGIN;
}

/**
 * Whether a copy of the rows that are left is small enough to be made without
 * the free disk space read (the database server on another host, or PHP not
 * allowed to look at its data directory).
 *
 * Only when the rows take at most half the table's file, number at most
 * PG_VISITOR_BLIND_MAX_ROWS and take at most PG_VISITOR_BLIND_MAX_BYTES: the
 * copy then needs only their room, and once it is in place the disk gets the
 * whole old file back, at least twice what the copy took. Should the disk be
 * full all the same, the server refuses the copy's writes, the copy is
 * dropped and the table stays as it was; the general job then does not try
 * again this way (pg_visitor_blind_failed_after()).
 *
 * No database access.
 *
 * @param int   $rows      records left
 * @param float $rows_room pg_visitor_rows_room()
 * @param float $file      the table's file size (pg_visitor_table_file_size())
 * @return bool
 */
function pg_visitor_blind_ok($rows, $rows_room, $file)
{
    $rows = (int) $rows;
    $rows_room = (float) $rows_room;
    $file = (float) $file;

    if (($rows_room <= 0) || ($file <= 0)) {
        return false;
    }

    return ($rows <= PG_VISITOR_BLIND_MAX_ROWS)
        && ($rows_room <= (float) PG_VISITOR_BLIND_MAX_BYTES)
        && (($rows_room * 2) <= $file);
}

/**
 * Whether the marker should say that a move or a rebuild the general job
 * started without the free disk space read has failed, which keeps it from
 * starting another that way. A move or rebuild that finishes, by the general
 * job or on the operator's request, clears it.
 *
 * No database access.
 *
 * @param bool   $blind_failed what the marker says now
 * @param bool   $blind        the last try started without the disk read
 * @param string $state        the marker's state
 * @return bool
 */
function pg_visitor_blind_failed_after($blind_failed, $blind, $state)
{
    if ($state === 'done') {
        return false;
    }

    if (($state === 'failed') && $blind) {
        return true;
    }

    return (bool) $blind_failed;
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
 * Which move the table needs: 'engine' from MyISAM to InnoDB (split into
 * months on the way where the server can), 'split' an InnoDB table that is
 * not split yet, '' none.
 *
 * No database access.
 *
 * @param string $engine    of `visitors`, lower case
 * @param string $partition pg_visitor_partition_read()['state']
 * @param bool   $splittable the server can partition the table
 * @return string
 */
function pg_visitor_move_kind($engine, $partition, $splittable)
{
    if ($engine === 'myisam') {
        return 'engine';
    }

    if (($engine === 'innodb') && ($partition === 'none') && $splittable) {
        return 'split';
    }

    return '';
}

/**
 * The hold reason of a move or rebuild whose free disk space cannot be read,
 * or '' when it may go ahead without it.
 *
 * No database access.
 *
 * @param array $facts blind_ok (bool), blind_failed (bool)
 * @return string '', 'disk_unknown', 'blind_failed'
 */
function pg_visitor_blind_hold($facts)
{
    if (empty($facts['blind_ok'])) {
        return 'disk_unknown';
    }

    return empty($facts['blind_failed']) ? '' : 'blind_failed';
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
 * Where the free disk space cannot be read, a copy small against the file
 * (pg_visitor_blind_ok()) still starts, until one started so has failed.
 *
 * @param array $facts kind (pg_visitor_move_kind()), capable (bool), fits
 *                     (bool|null), old_exists (bool), requested (bool), failed (bool: a
 *                     move stopped on an error in the last week),
 *                     days (int), cleaned (bool: the cleanup has run),
 *                     backlog (bool), disk_free (float|null), needed (float),
 *                     blind_ok (bool: pg_visitor_blind_ok()), blind_failed
 *                     (bool: a move started without the disk read failed)
 * @return string '', 'engine', 'unsupported', 'too_wide', 'name_taken',
 *                'failed', 'no_period', 'backlog', 'disk_unknown',
 *                'blind_failed', 'disk_low'
 */
function pg_visitor_move_hold_reason($facts)
{
    if ($facts['kind'] === '') {
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
        return pg_visitor_blind_hold($facts);
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
 * With $split the copy is split into months as well, from the month of the
 * oldest visit on (pg_visitor_partition_plan()). A move from MyISAM whose copy
 * the server will not split builds it unsplit, as before; a split that cannot
 * be made is an error.
 *
 * @param string $kind  pg_visitor_move_kind()
 * @param bool   $split the server can partition the table
 * @return string '' when built, otherwise the server's error
 */
function pg_visitor_move_create_copy($kind = 'engine', $split = false)
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

    // The months, from the oldest visit to the months ahead. The copy comes
    // with the key of `visitors`, which is (id) unless an earlier split made
    // it (id, start_timestamp).
    $split_sql = ($split || ($kind === 'split'))
        ? pg_visitor_partition_alter_sql(pg_visitor_partition_primary($copy), pg_visitor_partition_plan_for('visitors'), $changes)
        : null;

    if (($kind === 'split') && ($split_sql === null)) {
        pg_visitor_move_query("DROP TABLE IF EXISTS `" . $copy . "`");
        return lang('the primary key of the table is not the one it can be split by');
    }

    // The table is empty: this is a change of its definition, not a copy.
    $done = ($split_sql !== null) && pg_visitor_move_query("ALTER TABLE `" . $copy . "` " . $split_sql);

    if (!$done && ($kind !== 'split')) {
        $done = pg_visitor_move_query("ALTER TABLE `" . $copy . "` " . implode(', ', $changes));
    }

    if (!$done) {
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

    $support = pg_visitor_partition_support();
    $kind = pg_visitor_move_kind($engines['visitors'], pg_visitor_partition_read('visitors')['state'], $support['ok']);

    if ($kind === '') {

        // A copy with nothing to move it for: the table was converted or
        // split another way meanwhile, or a backup brought the copy back.
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

    $last['kind'] = $kind;

    if ($engines[$copy] === '') {

        $catalog = pg_db_table_catalog();
        $catalog_row = isset($catalog['visitors']) ? $catalog['visitors'] : array();

        $settings = pg_visitor_retention_settings();
        $cleanup = pg_visitor_cleanup_last();

        // An InnoDB table that is only split needs nothing of InnoDB it does
        // not have already.
        $capability = ($kind === 'engine') ? pg_innodb_capability() : array('ok' => true, 'reason' => '');
        $estimate = (($kind === 'engine') && $capability['ok']) ? pg_innodb_row_estimate('visitors') : null;

        $last['needed'] = pg_visitor_move_disk_needed($catalog_row);

        $disk_free = pg_visitor_move_disk_free();
        $blind_ok = false;

        if ($disk_free === null) {
            $file_now = pg_visitor_table_file_size($catalog_row);
            $blind_ok = pg_visitor_blind_ok(isset($catalog_row['rows']) ? (int) $catalog_row['rows'] : 0, pg_visitor_rows_room_estimate($catalog_row), $file_now['bytes']);
        }

        $reason = pg_visitor_move_hold_reason(array(
            'kind' => $kind,
            'capable' => $capability['ok'],
            'fits' => is_array($estimate) ? $estimate['fits'] : null,
            'old_exists' => ($engines[$old] !== ''),
            'requested' => $last['requested'],
            'failed' => (($last['state'] === 'failed') && ($last['failed_at'] > (time() - 7 * 86400))),
            'days' => is_array($settings) ? $settings['days'] : 0,
            'cleaned' => ($cleanup['checked_at'] > 0),
            'backlog' => $cleanup['backlog'],
            'disk_free' => $disk_free,
            'needed' => $last['needed'],
            'blind_ok' => $blind_ok,
            'blind_failed' => $last['blind_failed'],
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

        $error = pg_visitor_move_create_copy($kind, $support['ok']);

        if ($error !== '') {
            return pg_visitor_move_fail($last, $result, $error);
        }

        // A copy the general job starts without the disk read: should it
        // fail, the next is not started that way.
        $last['blind'] = (($disk_free === null) && !$last['requested']);

        $last['state'] = 'copying';
        $last['reason'] = '';
        $last['error'] = '';
        $last['requested'] = false;
        $last['started'] = time();
        $last['synced_at'] = 0;
        $last['copied'] = 0;
        $last['rows_before'] = isset($catalog_row['rows']) ? (int) $catalog_row['rows'] : 0;
        $file_before = pg_visitor_table_file_size($catalog_row);
        $last['bytes_before'] = $file_before['bytes'];
        $last['finished_at'] = 0;

        // Written now: a run cut off from here on must find when it began.
        pg_visitor_move_save($last);

    } elseif (($engines[$copy] !== 'innodb') || !pg_visitor_move_same_columns()
        || (($kind === 'split') && (pg_visitor_partition_read($copy)['state'] !== 'monthly'))) {

        // Not a copy this function made, `visitors` changed under it, or a
        // copy built unsplit for a table that now is to be split.
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

    // A plain SELECT reads the InnoDB table without locking its rows. The
    // start of a visit never changes, and with it in the join a table split
    // into months looks each row up in its own month only.
    $missing_sql =
        "SELECT o.id
        FROM `" . $old . "` AS o
        LEFT JOIN visitors AS n ON ((n.id = o.id) AND (n.start_timestamp = o.start_timestamp))
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
    $file_after = isset($catalog['visitors']) ? pg_visitor_table_file_size($catalog['visitors']) : array('bytes' => 0.0, 'source' => '');

    $last['state'] = 'done';
    $last['error'] = '';
    $last['reason'] = '';
    $last['requested'] = false;
    $last['finished_at'] = time();
    $last['bytes_after'] = $file_after['bytes'];
    $last['bytes_source'] = $file_after['source'];

    $result['state'] = 'done';
    $result['text'] = pg_visitor_move_result_text($last);

    return $result;
}

/**
 * A move that stopped on an error: the copy is dropped, which gives its disk
 * space back, and the table stays as it was, where the site works as before.
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
    if (($last['kind'] === 'split') && ($last['state'] === 'done')) {
        return lang(array(
            'string' => 'The visitors table was split into one partition per month: {var:1} before, {var:2} now.',
            'vars' => array(pg_innodb_size_label($last['bytes_before']), pg_visitor_figure_text(pg_innodb_size_label($last['bytes_after']), $last['bytes_source'])),
        ));
    }

    if (($last['kind'] === 'split') && ($last['state'] === 'failed')) {
        return lang(array(
            'string' => 'Splitting the visitors table into months stopped: {var:1}. It stays as it was and works as before.',
            'vars' => array($last['error']),
        ));
    }

    if ($last['state'] === 'done') {
        return lang(array(
            'string' => 'The visitors table was moved to InnoDB: {var:1} before, {var:2} now.',
            'vars' => array(pg_innodb_size_label($last['bytes_before']), pg_visitor_figure_text(pg_innodb_size_label($last['bytes_after']), $last['bytes_source'])),
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
 * Why a MyISAM visitors table is not being moved, or an InnoDB one not split
 * into months, in a sentence for the screen.
 *
 * @param array $last pg_visitor_move_last()
 * @return string
 */
function pg_visitor_move_reason_text($last)
{
    if (isset($last['kind']) && ($last['kind'] === 'split')) {

        switch ($last['reason']) {

            case 'name_taken':
                return lang(array('string' => 'A table named {var:1} is in the way of the split. Once you have looked at it and removed it, the split can start.', 'vars' => array(PG_VISITOR_MOVE_OLD)));

            case 'no_period':
                return lang('The general job splits it into months on its own once a period is set for keeping visitor records and the older ones are gone.');

            case 'backlog':
                return lang('Once the old records are gone, the general job splits it into one partition per month on its own, a few thousand records at a time while the site keeps running. From then on a month older than the period goes in a moment.');

            case 'disk_unknown':
                return lang(array('string' => 'The general job does not start splitting it into months on its own, because the free disk space of the database server cannot be read from here. The split needs about {var:1} free until the old table is removed.', 'vars' => array(pg_innodb_size_label($last['needed']))));

            case 'disk_low':
                return lang(array('string' => 'The general job does not start splitting it into months on its own, because the database server has less free disk space than the split needs, about {var:1}.', 'vars' => array(pg_innodb_size_label($last['needed']))));

            case 'blind_failed':
                return lang('The general job no longer splits it into months on its own: the free disk space of the database server cannot be read from here, and the split it last started without it stopped. Split into months now still starts it.');
        }

        return '';
    }

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

        case 'blind_failed':
            return lang('The general job no longer moves it to InnoDB on its own: the free disk space of the database server cannot be read from here, and the move it last started without it stopped. Move to InnoDB now still starts it.');
    }

    return '';
}

// -- Giving the room of removed rows back to the disk -------------------------
//
// Rows removed from an InnoDB table leave their pages free inside the table's
// file: new visits reuse them, but the file keeps its size. Once the cleanup
// has worked off the backlog of a busy site, most of the file can be free
// space. Rebuilding the table writes a new file that holds only the rows that
// are left and drops the old one.
//
// ALTER TABLE ... FORCE, ALGORITHM=INPLACE, LOCK=NONE is that rebuild done
// online (MySQL 5.6.17+, MariaDB 10.0+): visits are written and read while it
// runs, and the server holds the table to itself only for a moment at its
// start and at its end. Naming the algorithm and the lock makes a server that
// cannot rebuild it online refuse the statement rather than fall back to a
// copy during which every write waits. OPTIMIZE TABLE would fall back
// silently, and on MariaDB with innodb_defragment on it defragments the pages
// without making the file smaller.

/**
 * Free space inside the table's file from which a rebuild is worth its while:
 * 128 MB, and at least half the size of the rows and indexes that are left
 * (a third of the file).
 */
define('PG_VISITOR_SHRINK_MIN_FREE', 134217728);

/**
 * The file that remembers the last look at the rebuild and how the last
 * rebuild ended.
 *
 * @return string
 */
function pg_visitor_shrink_marker()
{
    return PG_FUNCTIONS_DIR . '/data/temp/visitor_shrink.json';
}

/**
 * What the marker file holds, every key present.
 *
 * state: '' never looked, 'waiting' not rebuilt now ('reason' says why),
 * 'running' a rebuild started and its request has not come back yet,
 * 'done', 'failed' ('error').
 *
 * @return array
 */
function pg_visitor_shrink_last()
{
    $last = @json_decode((string) @file_get_contents(pg_visitor_shrink_marker()), true);

    if (!is_array($last)) {
        $last = array();
    }

    return array(
        'state' => isset($last['state']) ? (string) $last['state'] : '',
        'reason' => isset($last['reason']) ? (string) $last['reason'] : '',
        'error' => isset($last['error']) ? (string) $last['error'] : '',
        'checked_at' => isset($last['checked_at']) ? (int) $last['checked_at'] : 0,
        'started' => isset($last['started']) ? (int) $last['started'] : 0,
        'finished_at' => isset($last['finished_at']) ? (int) $last['finished_at'] : 0,
        'failed_at' => isset($last['failed_at']) ? (int) $last['failed_at'] : 0,
        'seconds' => isset($last['seconds']) ? (float) $last['seconds'] : 0.0,
        'file_before' => isset($last['file_before']) ? (float) $last['file_before'] : 0.0,
        'file_after' => isset($last['file_after']) ? (float) $last['file_after'] : 0.0,
        'file_source' => isset($last['file_source']) ? (string) $last['file_source'] : '',
        'needed' => isset($last['needed']) ? (float) $last['needed'] : 0.0,
        'quiet_hour' => isset($last['quiet_hour']) ? (int) $last['quiet_hour'] : 3,
        'blind' => !empty($last['blind']),
        'blind_failed' => !empty($last['blind_failed']),
    );
}

/**
 * Write the marker file.
 *
 * @param array $last pg_visitor_shrink_last() with the changes
 */
function pg_visitor_shrink_save($last)
{
    $last['blind_failed'] = pg_visitor_blind_failed_after(!empty($last['blind_failed']), !empty($last['blind']), isset($last['state']) ? (string) $last['state'] : '');

    $marker = pg_visitor_shrink_marker();

    if (!is_dir(dirname($marker))) {
        @mkdir(dirname($marker), 0755, true);
    }

    @file_put_contents($marker, json_encode($last), LOCK_EX);
}

/**
 * Whether the general job should look at the rebuild now: once in every hour
 * of the clock, so that the quietest one is never skipped, and on every tick
 * while a rebuild's request has not come back.
 *
 * @return bool
 */
function pg_visitor_shrink_due()
{
    $last = pg_visitor_shrink_last();

    if ($last['state'] === 'running') {
        return true;
    }

    return date('YmdH', $last['checked_at']) !== date('YmdH');
}

/**
 * Whether the free space inside the table's file is worth a rebuild.
 *
 * No database access.
 *
 * @param array $catalog_row pg_db_table_catalog()['visitors']
 * @return bool
 */
function pg_visitor_shrink_worth($catalog_row)
{
    $free = isset($catalog_row['free']) ? (float) $catalog_row['free'] : 0.0;
    $bytes = isset($catalog_row['bytes']) ? (float) $catalog_row['bytes'] : 0.0;

    return ($free >= PG_VISITOR_SHRINK_MIN_FREE) && ($free >= ($bytes / 2));
}

/**
 * Whether a rebuild is worth its while by the table's file: the rows that are
 * left would take at most half of it, and it would give at least
 * PG_VISITOR_SHRINK_MIN_FREE back. Reaches what pg_visitor_shrink_worth()
 * misses after a large delete, when InnoDB still counts the emptied pages in
 * its data figure and not as free.
 *
 * No database access.
 *
 * @param float $rows_room pg_visitor_rows_room_estimate()
 * @param float $file      the table's file size (pg_visitor_table_file_size())
 * @return bool
 */
function pg_visitor_shrink_worth_by_file($rows_room, $file)
{
    $rows_room = (float) $rows_room;
    $file = (float) $file;

    return ($rows_room > 0) && ($file > 0) && (($rows_room * 2) <= $file)
        && (($file - $rows_room) >= PG_VISITOR_SHRINK_MIN_FREE);
}

/**
 * Free disk space a rebuild asks for before the general job starts one on its
 * own.
 *
 * The new file holds the rows that are left (pg_visitor_rows_room_estimate()),
 * without the free space of the old one; the sort files for the indexes take
 * about the indexes' room, never more than the rows' own, and
 * the log of the writes made during the rebuild comes out of
 * PG_VISITOR_DISK_MARGIN with the rest of the server.
 *
 * No database access.
 *
 * @param array $catalog_row pg_db_table_catalog()['visitors']
 * @return float bytes
 */
function pg_visitor_shrink_disk_needed($catalog_row)
{
    $index = isset($catalog_row['index']) ? (float) $catalog_row['index'] : 0.0;

    $rows_room = pg_visitor_rows_room_estimate($catalog_row);

    return $rows_room + min($index, $rows_room) + (float) PG_VISITOR_DISK_MARGIN;
}

/**
 * The hour of the day with the fewest page views, the one the general job
 * rebuilds the table in.
 *
 * An hour with no row had no page view. Of hours as quiet as each other, the
 * first from 03:00 on; with no figures at all, 03:00.
 *
 * No database access.
 *
 * @param array $views hour (0-23) => page views over the same days
 * @return int 0-23
 */
function pg_visitor_shrink_quiet_hour($views)
{
    if (array_sum(array_map('intval', (array) $views)) <= 0) {
        return 3;
    }

    $quiet = 3;
    $fewest = null;

    for ($step = 0; $step < 24; $step++) {

        $hour = ($step + 3) % 24;
        $count = isset($views[$hour]) ? (int) $views[$hour] : 0;

        if (($fewest === null) || ($count < $fewest)) {
            $quiet = $hour;
            $fewest = $count;
        }
    }

    return $quiet;
}

/**
 * Page views per hour of the day over the last two weeks, from the hourly
 * rollup the dashboard reads (bucketed on PHP's clock, the clock the general
 * job compares with).
 *
 * @return array hour => page views; empty when the rollup is not there
 */
function pg_visitor_shrink_hour_views()
{
    if (!function_exists('pg_visitor_rollup_ready') || !pg_visitor_rollup_ready()) {
        return array();
    }

    $result = db_q(
        "SELECT stat_hour, SUM(page_views)
        FROM visitor_stats_hourly
        WHERE stat_date >= ?
        GROUP BY stat_hour", array(date('Y-m-d', strtotime('-14 days'))));

    $views = array();

    while ($result && ($row = mysqli_fetch_row($result))) {
        $views[(int) $row[0]] = (int) $row[1];
    }

    return $views;
}

/**
 * What the decision to rebuild rests on, read now.
 *
 * @param array $catalog_row pg_db_table_catalog()['visitors']
 * @param array $last        pg_visitor_shrink_last()
 * @param bool  $requested   the operator asked for it
 * @return array the facts of pg_visitor_shrink_hold_reason(), and gain
 *               (pg_visitor_shrink_gain()) for the card
 */
function pg_visitor_shrink_facts($catalog_row, $last, $requested)
{
    $move = pg_visitor_move_last();
    $settings = pg_visitor_retention_settings();
    $cleanup = pg_visitor_cleanup_last();

    $per_table = null;
    $per_table_result = @mysqli_query(db::$con, "SELECT @@innodb_file_per_table");
    $per_table_row = $per_table_result ? mysqli_fetch_row($per_table_result) : null;

    if (is_array($per_table_row) && ($per_table_row[0] !== null)) {
        $per_table = !in_array(strtoupper(trim((string) $per_table_row[0])), array('0', 'OFF'), true);
    }

    $days = is_array($settings) ? (int) $settings['days'] : 0;

    $disk_free = pg_visitor_move_disk_free();
    $file_now = pg_visitor_table_file_size($catalog_row);
    $rows_room = pg_visitor_rows_room_estimate($catalog_row);
    $rows = isset($catalog_row['rows']) ? (int) $catalog_row['rows'] : 0;

    return array(
        'engine' => isset($catalog_row['engine']) ? (string) $catalog_row['engine'] : '',
        'moving' => in_array($move['state'], array('copying', 'swapped'), true),
        'partitioned' => (pg_visitor_partition_read('visitors')['state'] === 'monthly'),
        'per_table' => $per_table,
        'requested' => (bool) $requested,
        'worth' => pg_visitor_shrink_worth($catalog_row) || pg_visitor_shrink_worth_by_file($rows_room, $file_now['bytes']),
        'failed' => (($last['state'] === 'failed') && ($last['failed_at'] > (time() - 7 * 86400))),
        'recent' => (($last['state'] === 'done') && ($last['finished_at'] > (time() - 7 * 86400))),
        'backlog' => ($days > 0) && (($cleanup['checked_at'] <= 0) || $cleanup['backlog']),
        'disk_free' => $disk_free,
        'needed' => pg_visitor_shrink_disk_needed($catalog_row),
        'hour' => (int) date('G'),
        'quiet_hour' => pg_visitor_shrink_quiet_hour(pg_visitor_shrink_hour_views()),
        'gain' => pg_visitor_shrink_gain($rows_room, $file_now['bytes'], isset($catalog_row['free']) ? (float) $catalog_row['free'] : 0.0),
        'blind_ok' => ($disk_free === null) && pg_visitor_blind_ok($rows, $rows_room, $file_now['bytes']),
        'blind_failed' => !empty($last['blind_failed']),
    );
}

/**
 * Why the table is not rebuilt now, or '' when it may be.
 *
 * On its own the general job rebuilds it only when much of the file is free,
 * once the cleanup has removed what it is going to, when it can see the free
 * disk the rebuild needs, in the quietest hour of the day, and not again for
 * a week after a rebuild that ended either way. The operator's request skips
 * those: whoever asked has seen the space it needs on the screen.
 *
 * No database access.
 *
 * A table split into months is not rebuilt on its own: the cleanup drops a
 * month once all of it is older than the period, and the month's file goes
 * with it.
 *
 * @param array $facts engine (lower case), moving (bool: the move to InnoDB
 *                     is under way), partitioned (bool: split into months),
 *                     per_table (bool|null:
 *                     innodb_file_per_table), requested (bool), worth (bool),
 *                     failed (bool: a rebuild failed in the last week),
 *                     recent (bool: a rebuild ended in the last week),
 *                     backlog (bool), disk_free (float|null), needed (float),
 *                     hour (int, now), quiet_hour (int), blind_ok (bool:
 *                     pg_visitor_blind_ok()), blind_failed (bool: a rebuild
 *                     started without the disk read failed)
 * @return string '', 'engine', 'moving', 'shared_tablespace', 'partitioned',
 *                'small', 'failed', 'recent', 'backlog', 'disk_unknown',
 *                'blind_failed', 'disk_low', 'hour'
 */
function pg_visitor_shrink_hold_reason($facts)
{
    // A MyISAM table gets its file made smaller by the move to InnoDB.
    if ($facts['engine'] !== 'innodb') {
        return 'engine';
    }

    if ($facts['moving']) {
        return 'moving';
    }

    // With innodb_file_per_table off the table is rebuilt into the shared
    // tablespace, whose file never gets smaller.
    if ($facts['per_table'] === false) {
        return 'shared_tablespace';
    }

    if ($facts['requested']) {
        return '';
    }

    if (!empty($facts['partitioned'])) {
        return 'partitioned';
    }

    if (!$facts['worth']) {
        return 'small';
    }

    if ($facts['failed']) {
        return 'failed';
    }

    if ($facts['recent']) {
        return 'recent';
    }

    if ($facts['backlog']) {
        return 'backlog';
    }

    if ($facts['disk_free'] === null) {

        $blind = pg_visitor_blind_hold($facts);

        if ($blind !== '') {
            return $blind;
        }
    }

    if (($facts['disk_free'] !== null) && ((float) $facts['disk_free'] < (float) $facts['needed'])) {
        return 'disk_low';
    }

    if ((int) $facts['hour'] !== (int) $facts['quiet_hour']) {
        return 'hour';
    }

    return '';
}

/**
 * Rebuild the visitors table to make its file smaller, or look whether it
 * should be.
 *
 * Takes the cleanup's lock, so that the cleanup, the move and the rebuild
 * never run at the same time. The rebuild is one statement: a request that
 * ends under it (a web server's time limit) leaves the server to finish it,
 * holding the lock until it has, and the marker saying 'running'; the next
 * run that gets the lock reads from the table's size how it ended.
 *
 * The rebuild needs the table to itself for a moment at its start and at its
 * end, and every query on the table queues behind it while it waits for that
 * moment. A statement of this database that has been running for seconds (a
 * visitor report over a long period, a backup) would hold it up: the rebuild
 * does not start then. Otherwise it waits 2 s at most, the wait a transaction
 * left open with the table read in it can cause, gives up rather than keep
 * the visits queuing, and is tried again the next day.
 *
 * @param bool $requested the operator asked for it: skips the size, the
 *                        hour, the disk, the cleanup and the week's wait
 * @return array('state' => 'busy'|'waiting'|'done'|'failed', 'reason' =>
 *               string, 'text' => string, 'seconds' => float)
 */
function pg_visitor_shrink_run($requested = false)
{
    $result = array('state' => 'busy', 'reason' => '', 'text' => '', 'seconds' => 0.0);

    if (!class_exists('db') || empty(db::$con)) {
        $result['state'] = 'waiting';
        return $result;
    }

    // Said only to the operator: the general job finds the lock taken on
    // every tick of a long rebuild, which is nothing to log.
    if (pg_visitor_move_number("SELECT GET_LOCK(" . pg_visitor_cleanup_lock_sql() . ", 0)") !== 1) {
        if ($requested) {
            $result['text'] = lang('Another visitor record job is running (the cleanup, the move to InnoDB or a rebuild). Try again in a minute.');
        }
        return $result;
    }

    if (function_exists('ignore_user_abort')) {
        @ignore_user_abort(true);
    }

    // The statement can run for minutes; on Windows PHP's time limit counts
    // the time spent waiting for it.
    if (function_exists('set_time_limit')) {
        @set_time_limit(0);
    }

    $last = pg_visitor_shrink_last();
    $last['checked_at'] = time();

    // A rebuild whose request never came back. Holding the lock means the
    // server is through with it: the file is a good deal smaller when it
    // went through.
    if ($last['state'] === 'running') {

        $catalog = pg_db_table_catalog();
        $file_now = isset($catalog['visitors']) ? pg_visitor_table_file_size($catalog['visitors']) : array('bytes' => 0.0, 'source' => '');

        // How long it ran is not known.
        $last['seconds'] = 0.0;

        if (($file_now['bytes'] > 0) && ($last['file_before'] > 0) && ($file_now['bytes'] < 0.9 * $last['file_before'])) {
            $last['state'] = 'done';
            $last['finished_at'] = time();
            $last['file_after'] = $file_now['bytes'];
            $last['file_source'] = $file_now['source'];
            $last['error'] = '';
            $result['text'] = pg_visitor_shrink_result_text($last);
        } else {
            $last['state'] = 'failed';
            $last['failed_at'] = time();
            $last['error'] = lang('the rebuild did not finish');
            $result['text'] = pg_visitor_shrink_result_text($last);
        }
    }

    // Outside the quietest hour the general job's look stops here, before the
    // table catalog is read.
    $views = pg_visitor_shrink_hour_views();
    $last['quiet_hour'] = pg_visitor_shrink_quiet_hour($views);

    if (!$requested && ((int) date('G') !== $last['quiet_hour'])) {

        if (!in_array($last['state'], array('done', 'failed'), true)) {
            $last['state'] = 'waiting';
        }

        $last['reason'] = 'hour';

        pg_visitor_shrink_save($last);
        pg_visitor_move_query("SELECT RELEASE_LOCK(" . pg_visitor_cleanup_lock_sql() . ")");

        $result['state'] = 'waiting';
        $result['reason'] = 'hour';

        return $result;
    }

    $catalog = pg_db_table_catalog();
    $catalog_row = isset($catalog['visitors']) ? $catalog['visitors'] : array();

    $facts = pg_visitor_shrink_facts($catalog_row, $last, $requested);
    $last['needed'] = $facts['needed'];

    $reason = pg_visitor_shrink_hold_reason($facts);

    if ($reason !== '') {

        if (!in_array($last['state'], array('done', 'failed'), true)) {
            $last['state'] = 'waiting';
        }

        $last['reason'] = $reason;

        pg_visitor_shrink_save($last);
        pg_visitor_move_query("SELECT RELEASE_LOCK(" . pg_visitor_cleanup_lock_sql() . ")");

        $result['state'] = 'waiting';
        $result['reason'] = $reason;

        if ($requested) {
            $result['text'] = pg_visitor_shrink_reason_text($reason, $last);
        }

        return $result;
    }

    if (pg_visitor_shrink_long_statement() > 0) {

        if (!in_array($last['state'], array('done', 'failed'), true)) {
            $last['state'] = 'waiting';
        }

        $last['reason'] = 'in_use';

        pg_visitor_shrink_save($last);
        pg_visitor_move_query("SELECT RELEASE_LOCK(" . pg_visitor_cleanup_lock_sql() . ")");

        $result['state'] = 'waiting';
        $result['reason'] = 'in_use';
        if ($requested) {
            $result['text'] = pg_visitor_shrink_reason_text('in_use', $last);
        }

        return $result;
    }

    // Written before the statement: a request that ends under it leaves this
    // for the next run to find. A rebuild that does not get the table puts
    // back what was there.
    $before_attempt = $last;

    $last['state'] = 'running';
    $last['reason'] = '';
    $last['error'] = '';
    $last['started'] = time();
    $last['blind'] = (($facts['disk_free'] === null) && !$requested);
    $file_before = pg_visitor_table_file_size($catalog_row);

    $last['file_before'] = $file_before['bytes'];
    $last['file_after'] = 0.0;
    $last['file_source'] = $file_before['source'];

    pg_visitor_shrink_save($last);

    $rebuild = pg_db_run_table_statement('visitors', "ALTER TABLE {table} FORCE, ALGORITHM=INPLACE, LOCK=NONE", array(
        'state' => 'failed',
        'table' => 'visitors',
        'seconds' => 0.0,
        'messages' => array(),
        'errno' => 0,
        'error' => '',
        'running_seconds' => 0,
    ), '', 2);

    $last['seconds'] = (float) $rebuild['seconds'];

    if ($rebuild['state'] === 'ran') {

        $after = pg_db_table_catalog();
        $file_after = isset($after['visitors']) ? pg_visitor_table_file_size($after['visitors']) : array('bytes' => 0.0, 'source' => '');

        $last['state'] = 'done';
        $last['finished_at'] = time();
        $last['file_after'] = $file_after['bytes'];
        $last['file_source'] = $file_after['source'];

        $result['state'] = 'done';
        $result['text'] = pg_visitor_shrink_result_text($last);

    } elseif (in_array($rebuild['state'], array('busy', 'running'), true)) {

        // The table was in use: nothing changed and nothing failed.
        $last = $before_attempt;

        if (!in_array($last['state'], array('done', 'failed'), true)) {
            $last['state'] = 'waiting';
        }

        $last['reason'] = 'in_use';

        $result['state'] = 'waiting';
        $result['reason'] = 'in_use';
        if ($requested) {
            $result['text'] = pg_visitor_shrink_reason_text('in_use', $last);
        }

    } else {

        $last['state'] = 'failed';
        $last['failed_at'] = time();
        $last['error'] = ($rebuild['error'] !== '')
            ? (($rebuild['errno'] > 0) ? 'MySQL ' . (int) $rebuild['errno'] . ': ' . $rebuild['error'] : $rebuild['error'])
            : lang('unknown error');

        $result['state'] = 'failed';
        $result['text'] = pg_visitor_shrink_result_text($last);
    }

    pg_visitor_shrink_save($last);

    pg_visitor_move_query("SELECT RELEASE_LOCK(" . pg_visitor_cleanup_lock_sql() . ")");

    $result['seconds'] = (float) $rebuild['seconds'];

    return $result;
}

/**
 * How long, in seconds, the longest statement another connection is running
 * on this database has been at it, counting only those of 2 s and more; 0
 * when there is none or the list cannot be read.
 *
 * Without the PROCESS privilege the list holds the connections of the same
 * database user, which are the site's own.
 *
 * @return int
 */
function pg_visitor_shrink_long_statement()
{
    return pg_visitor_move_number(
        "SELECT COALESCE(MAX(TIME), 0)
        FROM information_schema.PROCESSLIST
        WHERE
            (ID <> CONNECTION_ID())
            AND (DB = DATABASE())
            AND (INFO IS NOT NULL)
            AND (COMMAND NOT IN ('Sleep', 'Daemon', 'Binlog Dump'))
            AND (TIME >= 2)");
}

/**
 * Room a rebuild has to give back before the card offers "Shrink now".
 */
define('PG_VISITOR_SHRINK_OFFER_MIN', 33554432);

/**
 * Whether a rebuild made the file smaller: by at least 1 MB and 1 %. Either
 * size unknown (0) says it did not.
 *
 * No database access.
 *
 * @param float $before file size before the rebuild
 * @param float $after  file size after it
 * @return bool
 */
function pg_visitor_shrink_gave_room($before, $after)
{
    $before = (float) $before;
    $after = (float) $after;

    if (($before <= 0) || ($after <= 0)) {
        return false;
    }

    return ($before - $after) >= max(1048576.0, $before / 100);
}

/**
 * The room a rebuild would give back to the disk: the larger of the free
 * space the server reports and the file less the rows' room.
 *
 * No database access.
 *
 * @param float $rows_room pg_visitor_rows_room_estimate()
 * @param float $file      the table's file size
 * @param float $free      DATA_FREE
 * @return float bytes
 */
function pg_visitor_shrink_gain($rows_room, $file, $free)
{
    $by_file = ((float) $file > 0) ? max(0.0, (float) $file - (float) $rows_room) : 0.0;

    return max(max(0.0, (float) $free), $by_file);
}

/**
 * Whether the card offers "Shrink now": only where a rebuild has room to give
 * back.
 *
 * No database access.
 *
 * @param float $gain pg_visitor_shrink_gain()
 * @return bool
 */
function pg_visitor_shrink_offered($gain)
{
    return (float) $gain >= PG_VISITOR_SHRINK_OFFER_MIN;
}

/**
 * Why "Clean up visitor records now" cannot be pressed, or '' when it can:
 * 'busy' another database job runs on the Database engine screen,
 * 'no_period' records are kept without a limit, 'inside_period' no record is
 * older than the period.
 *
 * No database access.
 *
 * @param bool $tools no other database job is running
 * @param int  $days  the period
 * @param int  $rows  records older than the period
 * @return string
 */
function pg_visitor_cleanup_disabled_reason($tools, $days, $rows)
{
    if (!$tools) {
        return 'busy';
    }

    if ((int) $days <= 0) {
        return 'no_period';
    }

    return ((int) $rows > 0) ? '' : 'inside_period';
}

/**
 * The card's sentence when every record is inside the period: there is
 * nothing to clean, and what a shorter period would do with this table.
 *
 * @param int    $days        the period
 * @param bool   $partitioned the table is split into months
 * @param string $engine      of `visitors`, lower case
 * @return string
 */
function pg_visitor_inside_period_text($days, $partitioned, $engine)
{
    $inside = lang(array(
        'string' => 'Every record is from the last {var:1} days, so there is nothing to clean up now and the table stays this size.',
        'vars' => array((int) $days),
    ));

    if ($partitioned) {
        return $inside . ' ' . lang('A shorter period under Visitor Tracking removes the older records: each month then wholly older than the period goes at once and its file goes back to the disk, the rest a chunk at a time.');
    }

    if ($engine === 'myisam') {
        return $inside . ' ' . lang('A shorter period under Visitor Tracking removes the older records a chunk at a time; their room goes back to the disk when the table is moved to InnoDB.');
    }

    return $inside . ' ' . lang('A shorter period under Visitor Tracking removes the older records a chunk at a time; their room goes back to the disk when the table is rebuilt.');
}

/**
 * One sentence on how a rebuild ended, for the activity log and the screen.
 *
 * @param array $last pg_visitor_shrink_last()
 * @return string
 */
function pg_visitor_shrink_result_text($last)
{
    if (($last['state'] === 'done') && !pg_visitor_shrink_gave_room($last['file_before'], $last['file_after'])) {
        return ($last['file_after'] > 0)
            ? lang(array(
                'string' => 'The visitors table was rebuilt; its file stayed about the same size, {var:1}: there was little free space in it to give back.',
                'vars' => array(pg_visitor_figure_text(pg_innodb_size_label($last['file_after']), $last['file_source'])),
            ))
            : lang('The visitors table was rebuilt; the size of its file afterwards could not be read.');
    }

    if (($last['state'] === 'done') && ($last['seconds'] <= 0)) {
        return lang(array(
            'string' => 'The visitors table was rebuilt and its file made smaller: {var:1} before, {var:2} now.',
            'vars' => array(
                pg_visitor_figure_text(pg_innodb_size_label($last['file_before']), $last['file_source']),
                pg_visitor_figure_text(pg_innodb_size_label($last['file_after']), $last['file_source']),
            ),
        ));
    }

    if ($last['state'] === 'done') {
        return lang(array(
            'string' => 'The visitors table was rebuilt in {var:1} s and its file made smaller: {var:2} before, {var:3} now.',
            'vars' => array(
                pg_format_number($last['seconds'], 1),
                pg_visitor_figure_text(pg_innodb_size_label($last['file_before']), $last['file_source']),
                pg_visitor_figure_text(pg_innodb_size_label($last['file_after']), $last['file_source']),
            ),
        ));
    }

    if ($last['state'] === 'failed') {
        return lang(array(
            'string' => 'Rebuilding the visitors table to make its file smaller stopped: {var:1}. The table is as it was and works as before.',
            'vars' => array($last['error']),
        ));
    }

    return '';
}

/**
 * Why the table is not rebuilt, in a sentence for the screen; '' when there
 * is nothing to say.
 *
 * @param string $reason pg_visitor_shrink_hold_reason(), or 'in_use'
 * @param array  $last   pg_visitor_shrink_last()
 * @return string
 */
function pg_visitor_shrink_reason_text($reason, $last)
{
    switch ($reason) {

        case 'engine':
            return lang('Only a table on InnoDB is rebuilt this way; a table on MyISAM gets a smaller file when it is moved to InnoDB.');

        case 'moving':
            return (pg_visitor_move_last()['kind'] === 'split')
                ? lang('The table is being split into one partition per month, which gives the room back to the disk as well.')
                : lang('The table is being moved to InnoDB, which gives the room back to the disk as well.');

        case 'shared_tablespace':
            return lang('The database server keeps InnoDB tables in one shared file (innodb_file_per_table is off), which does not get smaller when a table is rebuilt.');

        case 'partitioned':
            return lang('The table is split into one partition per month: once all of a month is older than the period, the general job drops it and its file goes back to the disk, so the table is not rebuilt on its own.');

        case 'small':
            return lang('Too little of the file is free for a rebuild to be worth it.');

        case 'failed':
            return pg_visitor_shrink_result_text($last) . ' ' . (!empty($last['blind_failed'])
                ? lang('The free disk space of the database server cannot be read from here, so the general job does not rebuild it on its own again; Shrink now tries now.')
                : lang('The general job tries again a week later; Shrink now tries now.'));

        case 'recent':
            return lang('The table was rebuilt in the last week; the general job looks at it again after that.');

        case 'backlog':
            return lang('Once the old records are gone, the general job gives this room back to the disk on its own, at the quietest hour of the day.');

        case 'disk_unknown':
            return lang(array('string' => 'The general job does not give it back on its own, because the free disk space of the database server cannot be read from here. Rebuilding the table needs about {var:1} free while it runs.', 'vars' => array(pg_innodb_size_label($last['needed']))));

        case 'disk_low':
            return lang(array('string' => 'The general job does not give it back on its own, because the database server has less free disk space than rebuilding the table needs, about {var:1}.', 'vars' => array(pg_innodb_size_label($last['needed']))));

        case 'blind_failed':
            return lang('The general job no longer gives it back on its own: the free disk space of the database server cannot be read from here, and the rebuild it last started without it stopped. Shrink now still rebuilds it.');

        case 'hour':
        case '':
            return lang(array('string' => 'The general job gives it back to the disk on its own at {var:1}, the quietest hour of the day by the page views of the last two weeks: it rebuilds the table while the site keeps running.', 'vars' => array(sprintf('%02d:00', (int) $last['quiet_hour']))));

        case 'in_use':
            return lang('The table was in use (a long query or a backup was running), so it was left as it is. The general job tries again at the quietest hour of the day.');
    }

    return '';
}
