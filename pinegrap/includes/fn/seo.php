<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// A module of functions.php: Structured data, Open Graph, robots rules, sitemap ping, tag-cloud keywords, visitor and form-view rollups, and the performance monitor.
//
// Loaded by functions.php through require_once, never on its own. The
// functions were moved here from functions.php as they were; PG_FUNCTIONS_DIR
// is the software directory (what dirname(__FILE__) was in functions.php),
// see the note at the top of functions.php.
if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}
/**
 * Register (or read back) the content item that this request displayed.
 *
 * Visitor tracking records the host page name, and by the time it runs the
 * item slug has already been stripped off the request (get_page.php:92 for
 * catalog detail, :122 for form item view). Every product therefore recorded
 * as 'urun-detay' and every article as 'blog-gorunum', so the hourly figures
 * could not name what was actually read.
 *
 * Render code that has already resolved an item calls this to register it;
 * the rollup writer calls it with no arguments to read it back. Only the
 * type and the primary key are kept — titles are resolved when a report is
 * read so a renamed product renames everywhere, rather than leaving old
 * copies of the title scattered across historical rows.
 *
 * @param string $type '', 'product', 'product_group' or 'form'
 * @param int    $id   Primary key in the matching table
 */
function pg_track_content($type = null, $id = null)
{
    static $item = array('type' => '', 'id' => 0);

    // Reader: called with no arguments.
    if ($type === null) {
        return $item;
    }

    $id = (int) $id;

    if ($id <= 0 || $type === '') {
        return $item;
    }

    // First registration wins. A page may embed several system widgets, and
    // the first one to resolve an item is the one the URL addressed; a
    // related-products widget further down must not overwrite it.
    if ($item['id'] === 0) {
        $item = array('type' => (string) $type, 'id' => $id);
    }

    return $item;
}

/**
 * The style properties the render currently in progress is using.
 *
 * These were constants, which is right for a request - one request renders one
 * page - and wrong for a process that renders many. define() latches the first
 * value, so in the search indexer and the SEO structure pass every page after
 * the first was rendered with the first page's collection and the first page's
 * layout override. get_layout_type() and render_layout() take the override as
 * the page's own layout type, so the wrong template file was used and nobody
 * could see it in the output.
 *
 * A function can be set again. The COLLECTION and STYLE_LAYOUT_TYPE constants
 * are still defined for anything outside the software that reads them; nothing
 * inside it does any more.
 *
 * Called with no argument to read, with a value to set.
 */
function pg_render_collection($collection = null)
{
    static $current = '';

    if ($collection !== null) {
        $current = (string) $collection;
    }

    return $current;
}

/**
 * The layout type the style of the render in progress overrides pages with.
 *
 * Empty when the style does not override anything, which is the normal case.
 * See pg_render_collection() for why this is not a constant.
 */
function pg_render_layout_type($layout_type = null)
{
    static $current = '';

    if ($layout_type !== null) {
        $current = (string) $layout_type;
    }

    return $current;
}

/**
 * Remember that this request produced the page a visitor would see.
 *
 * The performance monitor only measures a render that is representative of
 * what the public gets. Three things are not: a PDF, an RSS feed and an
 * iCalendar file, all served from the same URL as the page; and the same page
 * rendered with the editing toolbar, which carries the toolbar itself and the
 * edit wrappers and is measurably heavier.
 *
 * Registered once by get_page.php when it has decided which of those it is
 * producing. Read by pg_measured_entity(), so the gate covers the catalogue
 * item as well as the page - pg_track_content() is called from deep inside the
 * render and has no idea who is looking, so gating only the page registration
 * would leave an operator's edit-mode reloads landing in the product's own
 * measurements.
 */
function pg_measurable_render($measurable = null)
{
    static $state = false;

    if ($measurable !== null) {
        $state = (bool) $measurable;
    }

    return $state;
}

/**
 * Remember which page this request rendered.
 *
 * pg_track_content() above answers a different question: which catalogue item
 * did the URL address. A plain page addresses none, so on its own it cannot
 * tell the performance monitor whose measurement it is holding.
 *
 * Registered from get_page.php once the page has been sent, and deliberately
 * not from update_visitor_page_data(): that call is behind the VISITOR_TRACKING
 * switch, and an operator turning visitor statistics off should not silently
 * turn the speed half of the SEO score off with it.
 *
 * First registration wins, same as pg_track_content(). A page that renders
 * another page's content through a widget is still the page that was asked for.
 */
function pg_rendered_page($page_id = null)
{
    static $rendered_page_id = 0;

    if (($page_id !== null) && ($rendered_page_id === 0) && ((int) $page_id > 0)) {
        $rendered_page_id = (int) $page_id;
    }

    return $rendered_page_id;
}

/**
 * The record a performance measurement belongs to, as (type, id).
 *
 * A product or a product group carries an SEO score of its own, so a request
 * that resolved to one is measuring that record. Everything else - a plain
 * page, a catalogue listing, a form item view - is measuring the page that
 * rendered it. Form submissions are not scored records, so they fall through
 * to their page rather than being recorded under a type nothing can join to.
 *
 * Returns an empty type when the request resolved to nothing worth scoring,
 * which is the normal answer for a back-end screen or a 404.
 */
function pg_measured_entity()
{
    if (!pg_measurable_render()) {
        return array('type' => '', 'id' => 0);
    }

    $item = pg_track_content();

    if ((($item['type'] === 'product') || ($item['type'] === 'product_group')) && ((int) $item['id'] > 0)) {
        return array('type' => $item['type'], 'id' => (int) $item['id']);
    }

    $page_id = pg_rendered_page();

    if ($page_id > 0) {
        return array('type' => 'page', 'id' => $page_id);
    }

    return array('type' => '', 'id' => 0);
}

/**
 * True when the 2026.4.2 upgrade has added the identity columns to perf_stats.
 *
 * Probed rather than assumed because the monitor writes on every request. An
 * installation that took the code update without running the database upgrade
 * would otherwise have its one INSERT rejected for naming columns that do not
 * exist, and would lose all performance data instead of just the identity.
 * Mirrors pg_visitor_rollup_ready() and pg_sfv_stats_ready().
 *
 * Both columns are required, not just one. The upgrade adds them as two
 * separate ALTERs and bumps the version only after the whole function returns,
 * so a copy that times out between the two - which is what an ALTER on a table
 * of a couple of hundred thousand rows does on an older MySQL - leaves exactly
 * the half-applied state where probing one column says yes and the INSERT then
 * names a column that is not there. Every request would silently record
 * nothing, which is worse than recording no identity.
 */
function pg_perf_stats_has_entity()
{
    static $cached = null;

    if ($cached !== null) {
        return $cached;
    }

    $cached = false;

    if (!isset(db::$con) || !db::$con) {
        return false;
    }

    $cached = (pg_schema_has('perf_stats', 'entity_type') && pg_schema_has('perf_stats', 'entity_id'));

    return $cached;
}

/**
 * True when the 2026.4.8 upgrade (8.15) has added the query counts to
 * perf_stats. Both columns, for the reason given above.
 */
function pg_perf_stats_has_queries()
{
    static $cached = null;

    if ($cached === null) {
        $cached = (pg_schema_has('perf_stats', 'total_queries') && pg_schema_has('perf_stats', 'max_queries'));
    }

    return $cached;
}

/**
 * How many queries this request sent to the database.
 *
 * The connection is asked first: its Questions counter (session scope on
 * MySQL and MariaDB alike) counts every statement this connection has sent,
 * the plain mysqli_query() calls included, which pg_db_stats() cannot see.
 * Over a persistent connection ("p:" in DB_HOST) the session outlives the
 * request and the counter would carry earlier requests, so there, and when
 * the status cannot be read, the count of pg_db_run() is used instead.
 *
 * Anything outside 0 - 1,000,000 is reported as 0, for the reason the
 * duration has its sanity gate in perf_monitor_shutdown(): a summary row keeps
 * what it is given.
 *
 * @return int
 */
function pg_perf_request_queries()
{
    $queries = -1;

    $persistent = (defined('DB_HOST') && (strncasecmp((string) DB_HOST, 'p:', 2) === 0));

    if (!$persistent) {
        $status = @mysqli_query(db::$con, "SHOW SESSION STATUS LIKE 'Questions'");

        if ($status) {
            $row = @mysqli_fetch_row($status);

            if (is_array($row) && isset($row[1]) && is_numeric($row[1])) {
                $queries = (int) $row[1];
            }
        }
    }

    if ($queries < 0) {
        $stats = pg_db_stats();
        $queries = (int) $stats['count'];
    }

    if (($queries < 0) || ($queries > 1000000)) {
        $queries = 0;
    }

    return $queries;
}

/**
 * Make MySQL agree with PHP about what time it is.
 *
 * A timestamp column is absolute, but the moment a query asks
 * HOUR(FROM_UNIXTIME(...)) the answer depends on the connection's time_zone.
 * If that disagrees with PHP, every hourly figure is skewed by the difference.
 *
 * init.php sets this on every normal request, so pages served through it are
 * already consistent. install/index.php is the exception: it loads config.php
 * and functions.php directly and never touches init.php, so an upgrade runs
 * with whatever time zone the MySQL server defaults to. The backfill buckets
 * history with SQL date functions while live traffic is bucketed with PHP's
 * date(), and without this call those two would land in different hours —
 * leaving a seam at the offset between them, exactly where old and new data
 * meet.
 *
 * Offsets rather than zone names on purpose: named zones need the MySQL
 * timezone tables populated, which many shared hosts leave empty.
 *
 * Cheap and idempotent; safe to call before anything that buckets by time.
 */
function pg_sync_mysql_timezone()
{
    static $done = false;

    if ($done || !isset(db::$con) || !db::$con) {
        return;
    }

    $done = true;

    @mysqli_query(db::$con, "SET time_zone = '" . e(date('P')) . "'");
}

/**
 * True when the visitor rollup tables exist.
 *
 * Installs that take a code update without running the database upgrade must
 * keep serving pages, so every rollup write is gated on this. Asked of
 * pg_schema_has(), which answers most requests from its cache file.
 */
function pg_visitor_rollup_ready($recheck = false)
{
    static $cached = null;

    // The installer creates these tables part-way through a request that has
    // already loaded this file. Without a way to re-probe, a "no" cached from
    // earlier in the same request would make the backfill skip itself and
    // report success, which is the sort of failure nobody finds for months.
    // pg_schema_has() keeps a "no" for the request as well, so a recheck
    // makes it forget the table.
    if ($cached !== null && !$recheck) {
        return $cached;
    }

    $cached = false;

    if (!isset(db::$con) || !db::$con) {
        return false;
    }

    if ($recheck) {
        pg_schema_forget('visitor_content_hourly');
    }

    $cached = pg_schema_has('visitor_content_hourly');

    return $cached;
}

/**
 * Add one page view to the hourly rollups.
 *
 * Written as INSERT ... ON DUPLICATE KEY UPDATE against a bucket key, the same
 * shape waf_log uses since 2026.2.6: a repeated view does not add a row, it
 * increments a counter. A site serving 200,000 views a day adds one row per
 * (hour, page, item) rather than 200,000 rows, so the reporting queries read
 * thousands of rows where they used to read tens of millions.
 *
 * Failures are swallowed on purpose. Counting a visit must never be the reason
 * a visitor cannot see the page.
 */
function pg_record_visitor_page_view($page_id, $page_name)
{
    if (!pg_visitor_rollup_ready()) {
        return;
    }

    // Leave sessions the backfill has not reached yet alone.
    //
    // visitors.page_views is a running total that keeps climbing after the
    // rollup goes live, and the backfill reads whatever it says when it gets
    // there. A session that started before the cutover and is still browsing
    // would therefore have the same views counted twice: once here, and again
    // when the backfill sums its final page_views. That is what made an hour
    // report 29 views when 25 had happened.
    //
    // Once the cursor is past this session's row the backfill will never look
    // at it again, so counting live from that point is correct and nothing is
    // lost.
    if (pg_visitor_view_awaits_backfill()) {
        return;
    }

    $item      = pg_track_content();
    $item_type = $item['type'];
    $item_id   = (int) $item['id'];

    // Prefer the address the visitor actually used.
    //
    // For a pretty form URL, get_page.php:122 swaps the page name for the form
    // item view page that renders it — a template such as
    // 'blog-cards-no-sidebar'. Recording that would name a page which shows
    // nothing on its own, and would merge every list page sharing the template
    // into one entry. PRETTY_URL_PATH still holds '/blog/<slug>', the address
    // that was requested, so that is what gets recorded.
    if (defined('PRETTY_URL_PATH') && PRETTY_URL_PATH !== '') {
        $page_name = PRETTY_URL_PATH;
    }

    // Bucket with PHP's clock. The backfill synchronises MySQL to the same
    // offset before it runs, so historical and live rows agree on which hour
    // a view belongs to.
    $date = date('Y-m-d');
    $hour = (int) date('G');

    // The path is deliberately not part of the key. Query strings and
    // pagination produce endless variants of the same content, and keying on
    // them would defeat the aggregation exactly when it matters most.
    $bucket_key = sha1($date . '|' . $hour . '|' . (int) $page_id . '|' . $item_type . '|' . $item_id);

    @mysqli_query(
        db::$con,
        "INSERT INTO visitor_content_hourly
            (bucket_key, stat_date, stat_hour, page_id, page_name, item_type, item_id, views)
         VALUES
            ('" . e($bucket_key) . "', '" . e($date) . "', $hour, " . (int) $page_id . ",
             '" . e(mb_substr((string) $page_name, 0, 100)) . "', '" . e($item_type) . "', $item_id, 1)
         ON DUPLICATE KEY UPDATE views = views + 1"
    );

    @mysqli_query(
        db::$con,
        "INSERT INTO visitor_stats_hourly (stat_date, stat_hour, new_visitors, page_views)
         VALUES ('" . e($date) . "', $hour, 0, 1)
         ON DUPLICATE KEY UPDATE page_views = page_views + 1"
    );
}

/**
 * Add one new visitor session to the hourly rollup.
 *
 * Called where a row is inserted into `visitors`, so the counter reproduces
 * exactly what the old widget query measured: rows whose start_timestamp falls
 * in this hour.
 */
function pg_record_visitor_session()
{
    if (!pg_visitor_rollup_ready()) {
        return;
    }

    @mysqli_query(
        db::$con,
        "INSERT INTO visitor_stats_hourly (stat_date, stat_hour, new_visitors, page_views)
         VALUES ('" . e(date('Y-m-d')) . "', " . (int) date('G') . ", 1, 0)
         ON DUPLICATE KEY UPDATE new_visitors = new_visitors + 1"
    );
}

/**
 * True when this visitor's row is still waiting to be backfilled.
 *
 * The backfill walks `visitors` by id and reads each session's page_views
 * total. Any view counted live for a session the cursor has not passed yet
 * would be counted a second time when the backfill arrives, because it reads
 * the column's value at that moment, not its value at the cutover.
 *
 * Costs one row read from a single-row table, and only while a backfill is in
 * progress — the usual state is 'done', which returns immediately.
 */
function pg_visitor_view_awaits_backfill()
{
    static $state = null;

    if (empty($_SESSION['software']['visitor_id'])) {
        return false;
    }

    if ($state === null) {
        $state = pg_visitor_backfill_state();
    }

    if ($state === false || !empty($state['done']) || $state['max_id'] <= 0) {
        return false;
    }

    $visitor_id = (int) $_SESSION['software']['visitor_id'];

    return ($visitor_id > (int) $state['cursor'] && $visitor_id <= (int) $state['max_id']);
}

/**
 * Read the backfill's bookkeeping, or an empty state when unavailable.
 */
function pg_visitor_backfill_state($recheck = false)
{
    if (!pg_visitor_rollup_ready($recheck)) {
        return false;
    }

    $row = db_item("SELECT visitor_rollup_max_id, visitor_rollup_cursor, visitor_rollup_done FROM config");

    if (!is_array($row)) {
        return false;
    }

    return array(
        'max_id' => (int) $row['visitor_rollup_max_id'],
        'cursor' => (int) $row['visitor_rollup_cursor'],
        'done'   => ((int) $row['visitor_rollup_done'] === 1),
    );
}

/**
 * Summarise a slice of historical visitor rows into the rollup tables.
 *
 * Reads from `visitors` and writes only to the rollups. Not one row or column
 * of the source table is touched, so every advanced filter in
 * view_visitor_report.php keeps working against the full raw history.
 *
 * Written to be interrupted. This software runs on many server types, and the
 * ones sitting behind IIS FastCGI or an nginx proxy will terminate a long
 * request on their own schedule regardless of PHP's max_execution_time. The
 * position is stored in config after each chunk, so a killed request costs at
 * most one chunk and the next call resumes where this one stopped.
 *
 * Two limits, whichever is reached first:
 *   $budget_seconds  wall clock, so the caller can bound the delay it adds
 *   $chunk           rows per statement, so no single statement runs long
 *
 * @param  int $budget_seconds Time to spend before returning
 * @param  int $chunk          Visitor rows to summarise per statement
 * @return array|false         Progress state, or false when unavailable
 */
function pg_visitor_backfill_step($budget_seconds = 5, $chunk = 20000, $recheck = false)
{
    $state = pg_visitor_backfill_state($recheck);

    if ($state === false || $state['done'] || $state['max_id'] <= 0) {
        return $state;
    }

    // The live writer buckets with PHP's date(); these statements bucket with
    // MySQL's FROM_UNIXTIME(). Both clocks have to read the same, or history
    // and new traffic land in different hours and the join between them shows
    // a seam at the offset between the two.
    pg_sync_mysql_timezone();

    $budget_seconds = max(1, (int) $budget_seconds);
    $chunk          = max(1000, (int) $chunk);
    $deadline       = microtime(true) + $budget_seconds;

    $cursor = $state['cursor'];
    $max_id = $state['max_id'];

    // The site root and the page that serves it are one page recorded under
    // several names: '', '/', 'index.php', and a legacy 'example.com/'. Left
    // alone they become separate rows that split the home page's traffic
    // between them, and one of them renders as "Homepage" while the other
    // shows the real page name — the same page listed twice.
    //
    // Live tracking is not affected: get_page.php resolves the home page
    // before tracking runs, so it always records the actual page name. This
    // is only needed for history.
    $home = db_item("SELECT page_id, page_name FROM page WHERE page_home = 'yes' ORDER BY page_id LIMIT 1");

    $home_aliases    = "'', '/', 'index.php', 'example.com/'";
    $sql_home_page_id   = '0';
    $sql_home_page_name = "''";

    if (is_array($home) && !empty($home['page_id'])) {
        $home_aliases      .= ", '" . e(trim($home['page_name'])) . "'";
        $sql_home_page_id   = (int) $home['page_id'];
        $sql_home_page_name = "'" . e(trim($home['page_name'])) . "'";
    }

    // Resolved once, used by both the id and the name so a bucket's key and
    // its label can never disagree.
    $sql_page_id   = "CASE WHEN v.landing_page_name IN ($home_aliases) THEN $sql_home_page_id ELSE COALESCE(p.page_id, 0) END";
    $sql_page_name = "CASE WHEN v.landing_page_name IN ($home_aliases) THEN $sql_home_page_name ELSE LEFT(v.landing_page_name, 100) END";

    while ($cursor < $max_id && microtime(true) < $deadline) {

        $upper = min($max_id, $cursor + $chunk);

        // Site-wide hourly totals.
        //
        // page_views is attributed to the hour the session started, because
        // that is the only timestamp a historical row carries — the per-view
        // times were never recorded. Sessions from here on are counted view
        // by view at the moment each view happens, so this approximation
        // applies to history only.
        @mysqli_query(
            db::$con,
            "INSERT INTO visitor_stats_hourly (stat_date, stat_hour, new_visitors, page_views)
             SELECT
                 DATE(FROM_UNIXTIME(start_timestamp)) AS d,
                 HOUR(FROM_UNIXTIME(start_timestamp)) AS h,
                 COUNT(*),
                 COALESCE(SUM(page_views), 0)
             FROM visitors
             WHERE id > " . (int) $cursor . " AND id <= " . (int) $upper . "
               AND start_timestamp > 0
             GROUP BY d, h
             ON DUPLICATE KEY UPDATE
                 new_visitors = new_visitors + VALUES(new_visitors),
                 page_views   = page_views   + VALUES(page_views)"
        );

        // Per-content hourly totals.
        //
        // Historical rows resolve to page granularity only. landing_page_name
        // is all that was ever stored, and it holds the host page's name with
        // the item slug already removed, so which product or article was seen
        // is not recoverable from it — that information was never written
        // down. item_type stays empty for these rows and reports fall back to
        // the page name. Rows recorded from now on carry the item.
        //
        // SUM(page_views), not COUNT(*). Both rollup tables have to count the
        // same thing or the dashboard contradicts itself: the tooltip reads
        // the hour's page views from visitor_stats_hourly and the busiest item
        // from this table, and when one counted views while the other counted
        // sessions it printed "29 page views" directly above "home-1 · 1".
        //
        // The whole session's views land on the page it entered through. That
        // over-credits landing pages, but a historical row records no other
        // page, so the alternative is to discard the views entirely. Sessions
        // recorded from here on are counted view by view against the page
        // actually being viewed.
        //
        // The join to `page` is a LEFT JOIN so a landing page that has since
        // been deleted still contributes its traffic, under page_id 0.
        // Grouped in a derived table, then keyed on the outside.
        //
        // Building the bucket key in the same SELECT as the GROUP BY would
        // ask MySQL to prove a SHA1 over four expressions is functionally
        // dependent on those same expressions. Whether it manages that varies
        // by version and by whether ONLY_FULL_GROUP_BY is set. Grouping first
        // and hashing after removes the question: the outer query has no
        // GROUP BY at all.
        @mysqli_query(
            db::$con,
            "INSERT INTO visitor_content_hourly
                 (bucket_key, stat_date, stat_hour, page_id, page_name, item_type, item_id, views)
             SELECT
                 SHA1(CONCAT_WS('|', x.d, x.h, x.pid, '', 0)),
                 x.d, x.h, x.pid, x.pname, '', 0, x.cnt
             FROM (
                 SELECT
                     DATE(FROM_UNIXTIME(v.start_timestamp)) AS d,
                     HOUR(FROM_UNIXTIME(v.start_timestamp)) AS h,
                     $sql_page_id AS pid,
                     $sql_page_name AS pname,
                     COALESCE(SUM(v.page_views), 0) AS cnt
                 FROM visitors v
                 LEFT JOIN page p ON p.page_name = v.landing_page_name
                 WHERE v.id > " . (int) $cursor . " AND v.id <= " . (int) $upper . "
                   AND v.start_timestamp > 0
                 GROUP BY d, h, pid, pname
             ) x
             ON DUPLICATE KEY UPDATE views = views + VALUES(views)"
        );

        $cursor = $upper;

        // Record the position after every chunk, not at the end. If this
        // request dies on the next statement the work already done stands.
        db("UPDATE config SET visitor_rollup_cursor = '" . (int) $cursor . "'");
    }

    if ($cursor >= $max_id) {
        db("UPDATE config SET visitor_rollup_done = 1");
    }

    return array(
        'max_id' => $max_id,
        'cursor' => $cursor,
        'done'   => ($cursor >= $max_id),
    );
}

/**
 * True when the article view rollup table exists.
 *
 * Installs that take a code update without running the database upgrade keep
 * writing to the legacy per-view table, so the feature works either way.
 * Mirrors pg_visitor_rollup_ready().
 */
function pg_sfv_stats_ready($recheck = false)
{
    static $cached = null;

    // The installer creates the table part-way through a request that has
    // already loaded this file. Without a way to re-probe, a "no" cached
    // earlier in the same request would make the backfill skip itself and
    // report success.
    if ($cached !== null && !$recheck) {
        return $cached;
    }

    $cached = false;

    if (!isset(db::$con) || !db::$con) {
        return false;
    }

    if ($recheck) {
        pg_schema_forget('submitted_form_view_stats');
    }

    $cached = pg_schema_has('submitted_form_view_stats');

    return $cached;
}

// Where a screen without a folder picker should file the images and documents it receives.  A file
// row with folder zero is in no folder at all: it never shows up in the file manager or on the files
// screen again, which is what used to happen to everything that arrived through the chat.  A site
// picks the folder in the settings; until then the top folder is used, because that is where people
// look for it.
function pg_default_upload_folder($setting)
{
    static $cached = array();

    // Only the settings we know about; the name goes into a query below.
    $allowed = array('chat_upload_folder_id', 'product_upload_folder_id', 'api_upload_folder_id');

    if (in_array($setting, $allowed, true) == false) {
        return 0;
    }

    if (isset($cached[$setting])) {
        return $cached[$setting];
    }

    $folder_id = 0;

    // the column only exists after the 2026.4.4 upgrade
    if (pg_schema_has('config', $setting)) {
        $folder_id = (int) db_value("SELECT " . $setting . " FROM config");
    }

    // the folder may have been deleted since somebody picked it
    if ($folder_id > 0) {
        if ((int) db_value("SELECT COUNT(*) FROM folder WHERE folder_id = '" . e($folder_id) . "'") == 0) {
            $folder_id = 0;
        }
    }

    if ($folder_id == 0) {
        $folder_id = (int) db_value("SELECT folder_id FROM folder WHERE folder_parent = '0' ORDER BY folder_id LIMIT 1");
    }

    $cached[$setting] = $folder_id;

    return $folder_id;
}

/**
 * The Recycle Bin folder's id, or 0 when there is no bin.
 *
 * The bin is an ordinary folder row (that is what keeps clean_up.php from
 * deleting the files inside it), which means every folder picker on the site
 * would list it and offer to file new content there or grant a user rights
 * over it. It is not a place anybody chooses; it is where deleted things wait.
 * So the pickers hide it - select_folder() and get_acl_folder_tree() drop the
 * row, and dropping it also hides everything below it, because a binned
 * subtree is only reachable through the bin as its parent.
 *
 * The File Manager is the exception and reads the id through its own helper:
 * showing the bin, and what is in it, is the whole point of that screen.
 *
 * Guarded per column: before the 2026.4.4 upgrade there is no bin at all.
 */
function pg_recycle_bin_folder_id($recheck = false)
{
    static $cached = null;

    if (($cached !== null) && !$recheck) {
        return $cached;
    }

    $cached = 0;

    if (!isset(db::$con) || !db::$con) {
        return 0;
    }

    if ($recheck) {
        pg_schema_forget('config');
    }

    if (!pg_schema_has('config', 'recycle_folder_id')) {
        return 0;
    }

    $id = (int) db_value("SELECT recycle_folder_id FROM config");

    // A row that no longer exists is the same as no bin: the folder can have
    // been deleted by hand, and a stale id would hide an unrelated folder the
    // day that id is handed to something else.
    if (($id > 0) && db_value("SELECT COUNT(*) FROM folder WHERE folder_id = '" . escape($id) . "'")) {
        $cached = $id;
    }

    return $cached;
}

/**
 * True when the page table carries the search engine indexing columns.
 *
 * An installation that takes a code update without running the database
 * upgrade keeps the old behaviour: every page is indexable and robots.txt is
 * built the way it always was. Mirrors pg_sfv_stats_ready().
 *
 * Both columns are probed. They are added by one upgrade step, but the step
 * issues two statements, and a half-applied ALTER would otherwise leave the
 * readiness check saying yes to a query that selects a column that is not
 * there.
 */
function pg_page_noindex_ready($recheck = false)
{
    static $cached = null;

    if ($cached !== null && !$recheck) {
        return $cached;
    }

    $cached = false;

    if (!isset(db::$con) || !db::$con) {
        return false;
    }

    if ($recheck) {
        pg_schema_forget('page');
    }

    $cached = pg_schema_has('page', 'noindex') && pg_schema_has('page', 'nofollow');

    return $cached;
}

/**
 * Has the multi-page designer schema (2026.4.4, upgrade_2026_4_4_multi_page_design)
 * been applied?
 *
 * Probes BOTH halves of the swap — the page-side tree columns and the
 * style-side asset columns — because the upgrade runs five separate ALTERs
 * and a half-applied state must read as "not ready". Same shape as
 * pg_page_noindex_ready(): asked once per request, cached.
 *
 * Code that takes the new files before the upgrade has run keeps working on
 * the old layout (tree on the style, assets on the page); nothing below
 * assumes the new columns without asking here first.
 */
function pg_multi_page_design_ready($recheck = false)
{
    static $cached = null;

    if ($cached !== null && !$recheck) {
        return $cached;
    }

    $cached = false;

    if (!isset(db::$con) || !db::$con) {
        return false;
    }

    if ($recheck) {
        pg_schema_forget('page');
        pg_schema_forget('style');
    }

    $cached = pg_schema_has('page', 'page_tree_json')
           && pg_schema_has('page', 'page_tree_code')
           && pg_schema_has('style', 'style_custom_css')
           && pg_schema_has('style', 'style_custom_js')
           && pg_schema_has('style', 'style_custom_fonts');

    return $cached;
}

/**
 * SQL expression for "the layout tree that belongs to this page".
 *
 * For use inside a query that already joins `page` and `style` under those
 * exact aliases. On a migrated database the page's own tree wins and the
 * style's is the fallback (pages saved before the swap, or attached to a
 * style but never opened in the new editor); on an un-migrated database the
 * only tree there is lives on the style.
 *
 * Centralised because six readers scan this text with LIKE for
 * `"sharedId":N` — each of them spelling out the fallback by hand is how
 * one of them ends up scanning the wrong column.
 */
function pg_page_tree_sql_expr()
{
    if (pg_multi_page_design_ready()) {
        return "COALESCE(NULLIF(page.page_tree_json, ''), style.style_tree_json)";
    }
    return "style.style_tree_json";
}

/**
 * The layout tree JSON for a page, with the same fallback as
 * pg_page_tree_sql_expr(). '' when the page has no tree at all.
 */
function pg_page_tree_json($page_id)
{
    $page_id = (int)$page_id;
    if ($page_id <= 0) return '';
    $tree = db_value(
        "SELECT " . pg_page_tree_sql_expr() . "
         FROM page
         INNER JOIN style ON page.page_style = style.style_id
         WHERE page.page_id = '" . $page_id . "' LIMIT 1"
    );
    return ($tree === null || $tree === false) ? '' : (string)$tree;
}

/**
 * True when the page table carries the custom JSON-LD column (2026.4.4).
 * Mirrors pg_page_noindex_ready(): code without the upgrade keeps behaving
 * exactly as before, with no screen field and no query that selects it.
 */
function pg_page_custom_jsonld_ready($recheck = false)
{
    static $cached = null;

    if ($cached !== null && !$recheck) {
        return $cached;
    }

    $cached = false;

    if (!isset(db::$con) || !db::$con) {
        return false;
    }

    if ($recheck) {
        pg_schema_forget('page');
    }

    $cached = pg_schema_has('page', 'custom_jsonld');

    return $cached;
}

/**
 * True when the page table carries the canonical address and the two robots
 * directives (page.canonical_url, page.noarchive, page.max_snippet; 2026.11.0,
 * 11.0.22). All three are probed by name: the step adds them together, and a
 * database that has only some of them shows no field and writes none.
 */
function pg_page_seo_extras_ready($recheck = false)
{
    static $cached = null;

    if ($cached !== null && !$recheck) {
        return $cached;
    }

    $cached = false;

    if (!isset(db::$con) || !db::$con) {
        return false;
    }

    if ($recheck) {
        pg_schema_forget('page');
    }

    $cached = pg_schema_has('page', 'canonical_url')
           && pg_schema_has('page', 'noarchive')
           && pg_schema_has('page', 'max_snippet');

    return $cached;
}

/**
 * The og:locale value for the site's interface language.
 */
function pg_og_locale()
{
    $language = defined('SOFTWARE_LANGUAGE') ? SOFTWARE_LANGUAGE : 'en';

    switch ($language) {
        case 'tr':
            return 'tr_TR';

        default:
            return 'en_US';
    }
}

/**
 * File extensions that can stand as a social share image.
 */
function pg_og_image_extensions()
{
    return array('jpg', 'jpeg', 'png', 'gif', 'webp', 'avif');
}

/**
 * Collapse accidental duplicate slashes in a URL's path.
 *
 * Addresses arrive from stored content - a rich-text src, a media field, a
 * settings value - and often carry the editor era they were written in:
 * "http://host//cover.jpg" was measured in the wild as a blog share image.
 * The server happens to forgive it (IIS canonicalizes the request), but the
 * markup we EMIT is an identity - og:image and JSON-LD addresses should have
 * exactly one spelling.
 *
 * Only the path is touched: the scheme's own "//" is kept, and a query
 * string may legitimately carry slashes that mean something to whoever
 * reads it.
 */
function pg_normalize_url_slashes($url)
{
    $url = (string) $url;

    if ($url === '') {
        return '';
    }

    $split = preg_split('/(?=[?#])/', $url, 2);
    $split[0] = preg_replace('#(?<!:)/{2,}#', '/', $split[0]);

    return implode('', $split);
}

/**
 * Turn a relative URL from operator content into an absolute one.
 */
function pg_absolutize_site_url($url)
{
    $url = trim((string) $url);

    if ($url === '') {
        return '';
    }

    if (preg_match('#^https?://#i', $url)) {
        return pg_normalize_url_slashes($url);
    }

    // Scheme-relative: keep the host, borrow the scheme.
    if (mb_substr($url, 0, 2) === '//') {
        return rtrim(URL_SCHEME, ':/') . ':' . $url;
    }

    if (mb_substr($url, 0, 1) === '/') {
        return pg_normalize_url_slashes(URL_SCHEME . HOSTNAME_SETTING . $url);
    }

    return pg_normalize_url_slashes(URL_SCHEME . HOSTNAME_SETTING . (defined('PATH') ? PATH : '/') . $url);
}

/**
 * Build the share-image description for a row from the files table.
 *
 * Returns array(url, width, height, alt). Width and height are 0 when the
 * dimension columns have not been measured (or, on an installation that has
 * not run the 2026.4.4 upgrade, do not exist).
 */
function pg_og_image_from_file_row($file)
{
    if (!is_array($file) || (($file['name'] ?? '') === '')) {
        return null;
    }

    return array(
        'url'    => pg_normalize_url_slashes(URL_SCHEME . HOSTNAME_SETTING . (defined('PATH') ? PATH : '/') . encode_url_path($file['name'])),
        'width'  => (int) ($file['image_width'] ?? 0),
        'height' => (int) ($file['image_height'] ?? 0),
        'alt'    => trim((string) ($file['description'] ?? '')),
    );
}

/**
 * Share image from a file NAME (products, product groups, settings values).
 *
 * SELECT * rather than a column list on purpose: image_width/image_height were
 * added by a later upgrade, and naming them would error on a database that has
 * not run it. The lookup is by the indexed name column. A name that is not an
 * image type returns nothing - a PDF can be a product attachment but not a
 * share picture. The URL is still built when the files row is missing, because
 * product images resolve through the same public path either way.
 */
function pg_og_image_from_file_name($file_name)
{
    $file_name = trim((string) $file_name);

    if ($file_name === '') {
        return null;
    }

    $extension = mb_strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

    if (!in_array($extension, pg_og_image_extensions())) {
        return null;
    }

    $file = db_item("SELECT * FROM files WHERE name = '" . e($file_name) . "' LIMIT 1");

    if (is_array($file) && (($file['name'] ?? '') !== '')) {
        return pg_og_image_from_file_row($file);
    }

    return array(
        'url'    => pg_normalize_url_slashes(URL_SCHEME . HOSTNAME_SETTING . (defined('PATH') ? PATH : '/') . encode_url_path($file_name)),
        'width'  => 0,
        'height' => 0,
        'alt'    => '',
    );
}

/**
 * Share image from a files.id (the media field of a submitted form).
 */
function pg_og_image_from_file_id($file_id)
{
    $file_id = (int) $file_id;

    if ($file_id <= 0) {
        return null;
    }

    $file = db_item("SELECT * FROM files WHERE id = '" . $file_id . "' LIMIT 1");

    if (!is_array($file) || (($file['name'] ?? '') === '')) {
        return null;
    }

    $extension = mb_strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (!in_array($extension, pg_og_image_extensions())) {
        return null;
    }

    return pg_og_image_from_file_row($file);
}

/**
 * First <img> address out of a rich-text value, absolutized.
 */
function pg_first_image_url_from_html($html)
{
    $html = html_entity_decode((string) $html, ENT_QUOTES, 'UTF-8');

    if (preg_match('/<img[^>]+src\s*=\s*["\']([^"\']+)/i', $html, $matches)) {
        return pg_absolutize_site_url($matches[1]);
    }

    return '';
}

/**
 * Resolve the RSS media field of a submitted form into a share image.
 *
 * The media field is a loose binding: operators point it at a file upload, a
 * plain URL, a rich-text editor or a video field, and only the operator knows
 * which. So the VALUE decides, not the field type:
 *
 *   file upload            -> the file, if its type is an image
 *   http(s) image address  -> used as is
 *   video address          -> nothing (og:image must not carry a video URL)
 *   rich text              -> its first <img>, if it has one
 *
 * Returns array(url, width, height, alt) or null.
 */
function pg_resolve_submitted_form_og_image($custom_form_page_id, $submitted_form_id)
{
    $custom_form_page_id = (int) $custom_form_page_id;
    $submitted_form_id = (int) $submitted_form_id;

    if (($custom_form_page_id <= 0) || ($submitted_form_id <= 0)) {
        return null;
    }

    $media_field_id = db_value(
        "SELECT id FROM form_fields
        WHERE (page_id = '" . $custom_form_page_id . "') AND (rss_field = 'media')
        LIMIT 1");

    if (!$media_field_id) {
        return null;
    }

    $media = db_item(
        "SELECT data, file_id FROM form_data
        WHERE (form_id = '" . $submitted_form_id . "') AND (form_field_id = '" . (int) $media_field_id . "')
        LIMIT 1");

    if (!is_array($media)) {
        return null;
    }

    if ((int) ($media['file_id'] ?? 0) > 0) {
        return pg_og_image_from_file_id($media['file_id']);
    }

    $data = trim((string) ($media['data'] ?? ''));

    if ($data === '') {
        return null;
    }

    // A bare address: image extension is a picture; a video address is not
    // ours to use, og:image must carry a picture.
    if (preg_match('#^https?://\S+$#i', $data)) {
        $clean_path = (string) parse_url($data, PHP_URL_PATH);
        $extension = mb_strtolower(pathinfo($clean_path, PATHINFO_EXTENSION));

        if (in_array($extension, pg_og_image_extensions())) {
            return array('url' => pg_normalize_url_slashes($data), 'width' => 0, 'height' => 0, 'alt' => '');
        }

        return null;
    }

    // Rich text: the first embedded picture represents the post.
    $first_image = pg_first_image_url_from_html($data);

    if ($first_image !== '') {
        return array('url' => $first_image, 'width' => 0, 'height' => 0, 'alt' => '');
    }

    return null;
}

/**
 * Resolve a settings value that names an image: either a file name from the
 * Files screen or a full address. Used by the default share image and the
 * organization logo.
 */
function pg_resolve_config_image($value)
{
    $value = trim((string) $value);

    if ($value === '') {
        return null;
    }

    if (preg_match('#^https?://#i', $value)) {
        return array('url' => pg_normalize_url_slashes($value), 'width' => 0, 'height' => 0, 'alt' => '');
    }

    return pg_og_image_from_file_name($value);
}

/**
 * The site-wide fallback share image from Settings.
 */
function pg_og_default_image()
{
    if (!defined('OG_DEFAULT_IMAGE')) {
        return null;
    }

    return pg_resolve_config_image(OG_DEFAULT_IMAGE);
}

/**
 * Who wrote a submitted form, for the BlogPosting author.
 *
 * Only the linked contact's name is used (pg_contact_display_name(),
 * includes/fn/contacts.php). The account's login username is never printed:
 * it is half of a credential, and the account that signs a template's sample
 * records (the setup administrator) has no contact. An anonymous submission,
 * or one whose contact has no name, returns nothing, and the caller falls
 * back to naming the site.
 */
function pg_submitted_form_author_name($submitted_form_id)
{
    $form = db_item(
        "SELECT contact_id FROM forms
        WHERE id = '" . (int) $submitted_form_id . "'
        LIMIT 1");

    if (!is_array($form)) {
        return '';
    }

    if ((int) ($form['contact_id'] ?? 0) > 0) {
        $contact = db_item(
            "SELECT first_name, last_name, nickname FROM contacts
            WHERE id = '" . (int) $form['contact_id'] . "'
            LIMIT 1");

        if (is_array($contact)) {
            $name = pg_contact_display_name($contact['first_name'] ?? '', $contact['last_name'] ?? '', $contact['nickname'] ?? '');

            if ($name !== '') {
                return $name;
            }
        }
    }

    return '';
}

/**
 * True when the operator has configured the merchant facts at all.
 */
function pg_merchant_jsonld_configured()
{
    return defined('MERCHANT_COUNTRY')
        && (MERCHANT_COUNTRY !== '')
        && ((defined('MERCHANT_SHIPPING_RATE') && MERCHANT_SHIPPING_RATE >= 0)
            || (defined('MERCHANT_RETURN_DAYS') && MERCHANT_RETURN_DAYS >= 0));
}

/**
 * The offer-level blocks Google's merchant listing reads: shipping cost and
 * delivery time, and the return policy.
 *
 * Both only for shippable products. A digital product has no shipping and its
 * download is not returned by mail - emitting either block on it would be one
 * more piece of markup that says something untrue, which is exactly the class
 * of problem 2026.4.4 removes.
 *
 * Site-wide values rather than per product: the software's shipping engine
 * prices per order (weight, destination, method), and the markup format wants
 * one representative figure. The operator picks it in Settings; until then
 * nothing is emitted.
 */
function pg_product_merchant_jsonld_parts($shippable)
{
    $parts = array();

    if (((int) $shippable) != 1 || !defined('MERCHANT_COUNTRY') || MERCHANT_COUNTRY === '') {
        return $parts;
    }

    if (defined('MERCHANT_SHIPPING_RATE') && MERCHANT_SHIPPING_RATE >= 0) {
        $parts['shippingDetails'] = array(
            '@type' => 'OfferShippingDetails',
            'shippingRate' => array(
                '@type'    => 'MonetaryAmount',
                'value'    => sprintf('%01.2f', MERCHANT_SHIPPING_RATE / 100),
                'currency' => defined('BASE_CURRENCY_CODE') ? BASE_CURRENCY_CODE : '',
            ),
            'shippingDestination' => array(
                '@type'          => 'DefinedRegion',
                'addressCountry' => MERCHANT_COUNTRY,
            ),
            'deliveryTime' => array(
                '@type' => 'ShippingDeliveryTime',
                'transitTime' => array(
                    '@type'    => 'QuantitativeValue',
                    'minValue' => defined('MERCHANT_TRANSIT_DAYS_MIN') ? MERCHANT_TRANSIT_DAYS_MIN : 1,
                    'maxValue' => defined('MERCHANT_TRANSIT_DAYS_MAX') ? MERCHANT_TRANSIT_DAYS_MAX : 3,
                    'unitCode' => 'DAY',
                ),
            ),
        );
    }

    if (defined('MERCHANT_RETURN_DAYS') && MERCHANT_RETURN_DAYS >= 0) {
        $return_policy = array(
            '@type'             => 'MerchantReturnPolicy',
            'applicableCountry' => MERCHANT_COUNTRY,
        );

        // Zero is an answer, not an omission: returns are not accepted, and
        // saying so is valid markup.
        if (MERCHANT_RETURN_DAYS == 0) {
            $return_policy['returnPolicyCategory'] = 'https://schema.org/MerchantReturnNotPermitted';
        } else {
            $return_policy['returnPolicyCategory'] = 'https://schema.org/MerchantReturnFiniteReturnWindow';
            $return_policy['merchantReturnDays'] = (int) MERCHANT_RETURN_DAYS;
            $return_policy['returnMethod'] = 'https://schema.org/ReturnByMail';
            $return_policy['returnFees'] = (defined('MERCHANT_RETURN_FEES') && MERCHANT_RETURN_FEES == 1)
                ? 'https://schema.org/ReturnShippingFees'
                : 'https://schema.org/FreeReturn';
        }

        $parts['hasMerchantReturnPolicy'] = $return_policy;
    }

    return $parts;
}

/**
 * schema.org BlogPosting for a submitted form shown on a form item view.
 *
 * properties: headline, description, url, image (array from the og resolvers
 * or null), published (unix), modified (unix), author (string, may be blank).
 */
function pg_build_blogposting_jsonld($properties)
{
    $data = array(
        '@context' => 'https://schema.org',
        '@type'    => 'BlogPosting',
        'headline' => (string) ($properties['headline'] ?? ''),
        'url'      => (string) ($properties['url'] ?? ''),
        'mainEntityOfPage' => array(
            '@type' => 'WebPage',
            '@id'   => (string) ($properties['url'] ?? ''),
        ),
    );

    if (trim((string) ($properties['description'] ?? '')) !== '') {
        $data['description'] = trim((string) $properties['description']);
    }

    if (!empty($properties['image']['url'])) {
        $data['image'] = $properties['image']['url'];
    }

    if ((int) ($properties['published'] ?? 0) > 0) {
        $data['datePublished'] = date('c', (int) $properties['published']);
    }

    if ((int) ($properties['modified'] ?? 0) > 0) {
        $data['dateModified'] = date('c', (int) $properties['modified']);
    }

    // A person when we know one; the site otherwise. Google recommends the
    // property, and an anonymous submission still has a responsible publisher.
    if (trim((string) ($properties['author'] ?? '')) !== '') {
        $data['author'] = array('@type' => 'Person', 'name' => trim((string) $properties['author']));
    } elseif (defined('TITLE')) {
        $data['author'] = array('@type' => 'Organization', 'name' => TITLE);
    }

    if (defined('TITLE')) {
        $publisher = array('@type' => 'Organization', 'name' => TITLE);
        $logo = defined('ORGANIZATION_LOGO') ? pg_resolve_config_image(ORGANIZATION_LOGO) : null;

        if (!empty($logo['url'])) {
            $publisher['logo'] = array('@type' => 'ImageObject', 'url' => $logo['url']);
        }

        $data['publisher'] = $publisher;
    }

    return $data;
}

/**
 * schema.org Organization, emitted once on the home page.
 */
function pg_build_organization_jsonld()
{
    $data = array(
        '@context' => 'https://schema.org',
        '@type'    => 'Organization',
        'name'     => defined('TITLE') ? TITLE : HOSTNAME_SETTING,
        'url'      => URL_SCHEME . HOSTNAME_SETTING . (defined('PATH') ? PATH : '/'),
    );

    $logo = defined('ORGANIZATION_LOGO') ? pg_resolve_config_image(ORGANIZATION_LOGO) : null;

    if (!empty($logo['url'])) {
        $data['logo'] = $logo['url'];
    }

    return $data;
}

/**
 * schema.org BreadcrumbList from (name, url) pairs, in order.
 */
function pg_build_breadcrumb_jsonld($items)
{
    $elements = array();
    $position = 1;

    foreach ($items as $item) {
        if (trim((string) ($item['name'] ?? '')) === '') {
            continue;
        }

        $elements[] = array(
            '@type'    => 'ListItem',
            'position' => $position++,
            'name'     => trim((string) $item['name']),
            'item'     => (string) ($item['url'] ?? ''),
        );
    }

    return array(
        '@context'        => 'https://schema.org',
        '@type'           => 'BreadcrumbList',
        'itemListElement' => $elements,
    );
}

/**
 * Wrap operator-authored JSON-LD for output.
 *
 * The stored text is decoded and RE-ENCODED rather than echoed: json_encode
 * escapes the forward slash, so no value the operator typed can form the
 * "</script" sequence and break out of the tag. Text that does not decode -
 * saved before validation existed, or edited outside the software - produces
 * nothing rather than a broken block; the save-time check is where the
 * operator hears about it.
 */
function pg_custom_jsonld_block($raw)
{
    $raw = trim((string) $raw);

    if ($raw === '') {
        return '';
    }

    $decoded = json_decode($raw);

    if ($decoded === null) {
        return '';
    }

    return "\n<!-- Start Custom Structured Data -->\n<script type=\"application/ld+json\">"
        . json_encode($decoded, JSON_UNESCAPED_UNICODE)
        . "</script>\n<!-- End Custom Structured Data -->";
}

/**
 * Check a page's own JSON-LD before it is stored (page.custom_jsonld).
 *
 * The text is kept as the operator wrote it, trimmed: the output re-encodes
 * it anyway (pg_custom_jsonld_block()) and the editor shows it back with its
 * own line breaks. It has to decode, be an object or a list of objects, and
 * every one of them has to say what it describes: an @type, or an @graph of
 * items that do. Anything else is refused with a reason the screen can
 * name, instead of being stored and then left out of the page.
 *
 * Returns array('ok' => bool, 'value' => string, 'error' => '' | 'size' |
 * 'json' | 'shape' | 'type').
 */
function pg_page_custom_jsonld_check($raw)
{
    $raw = trim((string) $raw);

    if ($raw === '') {
        return array('ok' => true, 'value' => '', 'error' => '');
    }

    $refuse = function ($error) {
        return array('ok' => false, 'value' => '', 'error' => $error);
    };

    if (strlen($raw) > 65535) {
        return $refuse('size');
    }

    $decoded = json_decode($raw);

    if (json_last_error() !== JSON_ERROR_NONE) {
        return $refuse('json');
    }

    if (is_object($decoded)) {
        $items = array($decoded);
    } elseif (is_array($decoded)) {
        $items = $decoded;
    } else {
        return $refuse('shape');
    }

    if (!$items) {
        return $refuse('type');
    }

    foreach ($items as $item) {
        if (!is_object($item)) {
            return $refuse('shape');
        }

        if (!_pg_jsonld_item_typed($item, true)) {
            return $refuse('type');
        }
    }

    return array('ok' => true, 'value' => $raw, 'error' => '');
}

/**
 * Whether one JSON-LD item names its type: a non-empty @type (a word or a
 * list of words), or, at the top only, an @graph of items that do.
 */
function _pg_jsonld_item_typed($item, $top)
{
    $type = isset($item->{'@type'}) ? $item->{'@type'} : null;

    if (is_string($type) && trim($type) !== '') {
        return true;
    }

    if (is_array($type) && $type) {
        foreach ($type as $word) {
            if (!is_string($word) || (trim($word) === '')) {
                return false;
            }
        }

        return true;
    }

    if ($top && isset($item->{'@graph'}) && is_array($item->{'@graph'}) && $item->{'@graph'}) {
        foreach ($item->{'@graph'} as $node) {
            if (!is_object($node) || !_pg_jsonld_item_typed($node, false)) {
                return false;
            }
        }

        return true;
    }

    return false;
}

/**
 * The sentence for a refused page JSON-LD (pg_page_custom_jsonld_check()),
 * naming the page: one save covers every page of a design.
 */
function pg_page_custom_jsonld_message($error, $page_name)
{
    $page_name = ((string) $page_name !== '') ? (string) $page_name : lang('New Page');

    switch ($error) {
        case 'size':
            return lang(array('string' => 'The structured data of {var:1} is too long; it can be at most 64 KB.', 'vars' => array($page_name)));
        case 'shape':
            return lang(array('string' => 'The structured data of {var:1} must be a JSON object or a list of objects.', 'vars' => array($page_name)));
        case 'type':
            return lang(array('string' => 'Every item in the structured data of {var:1} needs an @type.', 'vars' => array($page_name)));
        default:
            return lang(array('string' => 'The structured data of {var:1} is not valid JSON.', 'vars' => array($page_name)));
    }
}

/**
 * Check a page's own canonical address before it is stored (page.canonical_url).
 *
 * Two forms are kept, trimmed, as typed: a full http or https address (on
 * this site or another one, for a page first published elsewhere), or a path
 * on this site starting with a single "/", which keeps pointing at this site
 * after a change of host name. Anything a search engine could read as a
 * relative or scheme-less address is refused, and so are white space, control
 * characters and the characters no address carries unescaped.
 *
 * Returns array('ok' => bool, 'value' => string, 'error' => '' | 'size' |
 * 'chars' | 'scheme').
 */
function pg_page_canonical_url_check($raw)
{
    $refuse = function ($error) {
        return array('ok' => false, 'value' => '', 'error' => $error);
    };

    // An address pasted from a document or a web page brings Unicode white
    // space and invisible characters along (a no-break space at the end, a
    // byte order mark at the start): trimmed at both ends like ASCII space,
    // where trim() alone kept them and the page printed ...%C2%A0. The same
    // set as the designer's live check (_sdCanonicalCheck(), JS \s plus the
    // zero-width ones). Not UTF-8 is not an address.
    $raw = preg_replace('~^[\x00\x09-\x0D\p{Z}\x{FEFF}\x{200B}-\x{200D}\x{2060}]+|[\x00\x09-\x0D\p{Z}\x{FEFF}\x{200B}-\x{200D}\x{2060}]+$~u', '', (string) $raw);

    if ($raw === null) {
        return $refuse('chars');
    }

    if ($raw === '') {
        return array('ok' => true, 'value' => '', 'error' => '');
    }

    if (strlen($raw) > 2000) {
        return $refuse('size');
    }

    // Inside: ASCII space and control characters, the characters no address
    // carries unescaped, and every Unicode separator or format character
    // (no-break and ideographic spaces, line separators, zero-width and
    // direction marks), none of which a visitor can see in the address.
    if (preg_match('~[\x00-\x20\x7F"<>\\\\]|[\p{Z}\p{Cf}]~u', $raw)) {
        return $refuse('chars');
    }

    if ($raw[0] === '/') {
        // "//host/path" is an address on another host without a scheme.
        if ((strlen($raw) > 1) && ($raw[1] === '/')) {
            return $refuse('scheme');
        }

        return array('ok' => true, 'value' => $raw, 'error' => '');
    }

    if (!preg_match('~^https?://~i', $raw)) {
        return $refuse('scheme');
    }

    $host = parse_url($raw, PHP_URL_HOST);

    if (!is_string($host) || ($host === '')) {
        return $refuse('scheme');
    }

    return array('ok' => true, 'value' => $raw, 'error' => '');
}

/**
 * The sentence for a refused canonical address (pg_page_canonical_url_check()),
 * naming the page: one save of the visual designer covers every page of a
 * design.
 */
function pg_page_canonical_url_message($error, $page_name)
{
    $page_name = ((string) $page_name !== '') ? (string) $page_name : lang('New Page');

    switch ($error) {
        case 'size':
            return lang(array('string' => 'The canonical address of {var:1} is too long; it can be at most 2000 characters.', 'vars' => array($page_name)));
        case 'chars':
            return lang(array('string' => 'The canonical address of {var:1} cannot contain spaces, quotation marks or angle brackets.', 'vars' => array($page_name)));
        default:
            return lang(array('string' => 'The canonical address of {var:1} must be a full address starting with http:// or https://, or a path on this site starting with /.', 'vars' => array($page_name)));
    }
}

/**
 * The longest text snippet, in characters, a page may ask for with
 * max-snippet. Search engines show far less; a larger number would only be
 * a roundabout way of writing -1.
 */
function pg_page_max_snippet_limit()
{
    return 10000;
}

/**
 * Check a page's max-snippet value before it is stored (page.max_snippet).
 *
 * Empty means no directive (null): the search engine decides. Otherwise a
 * whole number: -1 for no limit, 0 for no text snippet, or the most
 * characters to show, up to pg_page_max_snippet_limit(). A stored number
 * read back as a string passes the same way.
 *
 * Returns array('ok' => bool, 'value' => int|null, 'error' => '' | 'number').
 */
function pg_page_max_snippet_check($raw)
{
    if (is_int($raw)) {
        $raw = (string) $raw;
    }

    $raw = is_string($raw) ? trim($raw) : (($raw === null) ? '' : false);

    if ($raw === '') {
        return array('ok' => true, 'value' => null, 'error' => '');
    }

    if (($raw === false) || !preg_match('~^(-1|0|[1-9][0-9]{0,4})$~', $raw) || ((int) $raw > pg_page_max_snippet_limit())) {
        return array('ok' => false, 'value' => null, 'error' => 'number');
    }

    return array('ok' => true, 'value' => (int) $raw, 'error' => '');
}

/**
 * The sentence for a refused max-snippet value, naming the page.
 */
function pg_page_max_snippet_message($page_name)
{
    $page_name = ((string) $page_name !== '') ? (string) $page_name : lang('New Page');

    return lang(array('string' => 'The snippet length of {var:1} must be a whole number: -1 for no limit, 0 for no text snippet, or at most {var:2} characters.', 'vars' => array($page_name, pg_page_max_snippet_limit())));
}

/**
 * The address a page's head names as canonical in place of the automatic
 * one, from the stored page.canonical_url: a path on this site is put after
 * $base (scheme and host, URL_SCHEME . HOSTNAME_SETTING), and letters outside
 * ASCII after the host are percent-encoded, the form the automatic address
 * has. '' when the page names none, or when what is stored no longer passes
 * pg_page_canonical_url_check() (written outside the software): the page
 * then keeps the automatic address rather than naming a broken one.
 *
 * @param string|null $stored
 * @param string      $base
 * @return string unescaped; the caller escapes it for HTML
 */
function pg_page_canonical_href($stored, $base)
{
    $check = pg_page_canonical_url_check($stored);

    if (!$check['ok'] || ($check['value'] === '')) {
        return '';
    }

    $value = $check['value'];

    if ($value[0] === '/') {
        $host_part = rtrim((string) $base, '/');
        $rest = $value;
    } else {
        // Scheme and host are left as typed: an internationalised host name
        // is not percent-encoded.
        preg_match('~^(https?://[^/?#]*)(.*)$~is', $value, $parts);
        $host_part = $parts[1];
        $rest = $parts[2];
    }

    $rest = preg_replace_callback('~[\x80-\xFF]+~', function ($match) {
        return rawurlencode($match[0]);
    }, $rest);

    return $host_part . $rest;
}

/**
 * The content of a page's robots meta tag, '' for none.
 *
 * A page closed to search engines says only that (noindex, with follow or
 * nofollow): the other directives describe how an indexed page is shown,
 * and a page out of the index shows nothing. Otherwise noarchive and
 * max-snippet are written when the page asks for them; nofollow alone is
 * never written (page-noindex-switch).
 *
 * @param bool            $noindex
 * @param bool            $nofollow
 * @param bool            $noarchive
 * @param int|string|null $max_snippet as stored: null or '' for none
 * @return string
 */
function pg_page_robots_content($noindex, $nofollow, $noarchive, $max_snippet)
{
    if ($noindex) {
        return 'noindex, ' . ($nofollow ? 'nofollow' : 'follow');
    }

    $directives = array();

    if ($noarchive) {
        $directives[] = 'noarchive';
    }

    $snippet = pg_page_max_snippet_check($max_snippet);

    if ($snippet['ok'] && ($snippet['value'] !== null)) {
        $directives[] = 'max-snippet:' . $snippet['value'];
    }

    return implode(', ', $directives);
}

/**
 * The page's own address, kept while its head names another address as
 * canonical (page.canonical_url): get_page_content() sets it for the page it
 * draws, '' when the canonical link is the page's own. The language pass
 * (pg_tr_render_finalize()) reads the canonical link to find the page it
 * draws, for its alternates and its index verdict, and asks here first.
 *
 * @param string|null $address the address to keep; null only reads it
 * @return string
 */
function pg_page_own_url($address = null)
{
    static $own = '';

    if ($address !== null) {
        $own = (string) $address;
    }

    return $own;
}

/**
 * Disallow lines that block crawling of the pages marked noindex.
 *
 * Returns one "Disallow: ..." line per entry and no User-agent line of its own:
 * a rule belongs to whichever group it is written into, and choosing that group
 * is the caller's job. Empty when the feature has nothing to say, so a site with
 * no noindex page gets exactly the robots.txt it got before this existed.
 */
function pg_build_robots_disallow_rules()
{
    // PATH is defined by both entry points that can reach robots.txt, but this
    // function builds absolute paths and has nothing to fall back on without it.
    if (!defined('PATH') || !pg_page_noindex_ready()) {
        return array();
    }

    $pages = db_items(
        "SELECT
            page_name,
            page_folder,
            page_home
        FROM page
        WHERE noindex = '1'
        ORDER BY page_name ASC");

    $rules = array();

    foreach ($pages as $page) {
        // A page behind a login is already out of reach for a crawler, and
        // robots.txt is world readable: naming it here would publish the
        // address of something the operator is keeping private.
        if (get_access_control_type($page['page_folder']) != 'public') {
            continue;
        }

        // The home page answers on the site root as well as under its own name.
        if ($page['page_home'] == 'yes') {
            $rules[] = 'Disallow: ' . PATH . '$';
        }

        $page_name = trim($page['page_name']);

        if ($page_name == '') {
            continue;
        }

        $path = PATH . encode_url_path($page_name);

        // Three anchored rules rather than one prefix rule. A bare
        // "Disallow: /search" also matches /search-engine-optimisation, which is
        // a different page nobody asked to hide. '$' ends the match, '?' pins
        // the query string form the site search page answers on, and the
        // trailing slash covers the item addresses a list view builds below its
        // own name. Both characters are part of the robots.txt grammar in
        // RFC 9309, so a conforming crawler has to honour them.
        $rules[] = 'Disallow: ' . $path . '$';
        $rules[] = 'Disallow: ' . $path . '?';
        $rules[] = 'Disallow: ' . $path . '/';
    }

    return array_values(array_unique($rules));
}

/**
 * Write the rules into the operator's own catch-all group.
 *
 * Returns the rewritten robots.txt text, or FALSE when the operator's text has
 * no "User-agent: *" group to write into.
 *
 * Opening a second "User-agent: *" group instead would be correct by the
 * specification - RFC 9309 says a crawler MUST combine the groups that match
 * the same token, and Google documents the same behaviour - but a parser that
 * stops at the first matching group would then read ours and miss every rule
 * the operator wrote. On a site whose additional content is "Disallow: /" that
 * is the difference between closed and open.
 */
function pg_merge_robots_rules_into_catch_all($additional_robots_content, $rules)
{
    if (($additional_robots_content == '') || (count($rules) == 0)) {
        return FALSE;
    }

    $lines = preg_split('/\r\n|\r|\n/', $additional_robots_content);

    $catch_all_line = -1;

    // Find the operator's catch-all group.
    foreach ($lines as $index => $line) {
        if (preg_match('/^\s*user-agent\s*:\s*\*\s*(#.*)?$/i', $line)) {
            $catch_all_line = $index;
            break;
        }
    }

    if ($catch_all_line == -1) {
        return FALSE;
    }

    // One group can be introduced by several User-agent lines at once, and
    // neither a blank line nor a comment ends it. The rules go after the last of
    // those lines: written in between, they would sit in the middle of the
    // group's own declaration.
    $insert_after = $catch_all_line;

    for ($index = $catch_all_line + 1; $index < count($lines); $index++) {
        $line = trim($lines[$index]);

        if (($line == '') || (mb_substr($line, 0, 1) == '#')) {
            continue;
        }

        if (preg_match('/^user-agent\s*:/i', $line)) {
            $insert_after = $index;
            continue;
        }

        break;
    }

    array_splice($lines, $insert_after + 1, 0, $rules);

    return implode("\r\n", $lines);
}

/**
 * True while the legacy per-view table is still present.
 *
 * It is renamed rather than dropped when the backfill finishes, so this stays
 * false-safe on installs that have already retired it.
 */
function pg_sfv_legacy_table_exists($recheck = false)
{
    static $cached = null;

    if ($cached !== null && !$recheck) {
        return $cached;
    }

    $cached = false;

    if (!isset(db::$con) || !db::$con) {
        return false;
    }

    if ($recheck) {
        pg_schema_forget('submitted_form_views');
    }

    $cached = pg_schema_has('submitted_form_views');

    return $cached;
}

/**
 * Add one article view to the daily rollup.
 *
 * The table this replaces held one row per view. On a site serving 200,000
 * views a day it had reached eight million rows and 1.1 GB -- 73% of the entire
 * database, 946 MB of it index. It was MyISAM, so every article view took an
 * exclusive lock on the whole table while four B-trees were updated, none of
 * which fit in key_buffer_size, and every other request touching the table
 * queued behind it.
 *
 * Rows here are keyed by (article, page, day), so a piece read 50,000 times in
 * a day adds one row and increments a counter. The table grows with the
 * calendar, not with traffic.
 *
 * This is not the counter readers see. submitted_form_info.number_of_views is a
 * running total maintained separately by the caller and is left alone.
 *
 * @return bool True when the rollup handled it, false to fall back to legacy
 */
function pg_sfv_record_view($submitted_form_id, $page_id)
{
    if (!pg_sfv_stats_ready()) {
        return false;
    }

    // CURDATE() reads MySQL's clock. init.php sets the session time zone from
    // PHP on every request, so the live writer and the backfill -- which
    // buckets with DATE(FROM_UNIXTIME(...)) -- agree on where a day starts.
    @mysqli_query(
        db::$con,
        "INSERT INTO submitted_form_view_stats (submitted_form_id, page_id, view_date, views)
         VALUES (" . (int) $submitted_form_id . ", " . (int) $page_id . ", CURDATE(), 1)
         ON DUPLICATE KEY UPDATE views = views + 1"
    );

    return true;
}

/**
 * Count one view of a record shown on a page built in the visual editor.
 *
 * The designed record page's twin of the counting block in
 * get_form_item_view.php: the running total in
 * submitted_form_info.number_of_views and the day's row in
 * submitted_form_view_stats, both per (record, page). get_page.php calls it
 * once the page has been sent, for the record the form_item_view widget
 * registered (pg_sw_record_view_seen()).
 *
 * submitted_form_info has no unique key, so two first views arriving at once
 * could each find no row and insert one. The first-insert path therefore takes
 * a named lock, updates again under it and inserts only if that still matched
 * nothing. A lock that times out ('0': another request is inserting) drops this
 * one view rather than risk a second row. A server without a lock service
 * (the query fails or returns NULL, as on Galera/PXC) inserts unlocked, like
 * the legacy page: a rare duplicate row is read correctly by the MAX()
 * readers, while skipping would leave the record without a counter for good.
 *
 * Every statement goes through @mysqli_query, never db(): db() turns a failed
 * query into an error screen, and a lock wait or deadlock on a busy record's
 * counter must not replace the page a visitor has already received.
 */
function pg_sfv_count_view($submitted_form_id, $page_id)
{
    $submitted_form_id = (int) $submitted_form_id;
    $page_id           = (int) $page_id;

    if ($submitted_form_id <= 0 || $page_id <= 0 || !isset(db::$con) || !db::$con) {
        return;
    }

    pg_sfv_record_view($submitted_form_id, $page_id);

    $update = "UPDATE submitted_form_info
               SET number_of_views = number_of_views + 1
               WHERE submitted_form_id = '" . $submitted_form_id . "'
                 AND page_id = '" . $page_id . "'";

    if (!@mysqli_query(db::$con, $update)) {
        return;
    }

    if (mysqli_affected_rows(db::$con) === 0) {
        // Under 64 characters whatever the ids; no database prefix, which
        // could push it over the limit and make every call fail.
        $lock = "'pg_sfi_" . $submitted_form_id . "_" . $page_id . "'";
        $insert = "INSERT INTO submitted_form_info (submitted_form_id, page_id, number_of_views)
                   VALUES ('" . $submitted_form_id . "', '" . $page_id . "', 1)";
        $result = @mysqli_query(db::$con, "SELECT GET_LOCK($lock, 1)");
        $row = ($result instanceof mysqli_result) ? mysqli_fetch_row($result) : null;
        $lock_state = ($row && $row[0] !== null) ? (string) $row[0] : null;

        if ($lock_state === '1') {
            if (@mysqli_query(db::$con, $update) && mysqli_affected_rows(db::$con) === 0) {
                @mysqli_query(db::$con, $insert);
            }

            @mysqli_query(db::$con, "SELECT RELEASE_LOCK($lock)");
        } elseif ($lock_state === null) {
            // No lock service: insert unlocked (legacy behaviour).
            @mysqli_query(db::$con, $insert);
        }
        // '0': the lock timed out while another request holds it; drop this view.
    }

    pg_sfv_sweep_sampled();
}

/**
 * Now and then, drop daily view rows older than six months.
 *
 * The same rule and sample as the legacy record page, so a site whose record
 * pages are all designed keeps the table bounded too. Only the rollup table:
 * the old per-view table is read by the backfill and is never swept here.
 */
function pg_sfv_sweep_sampled()
{
    if (mt_rand(1, 500) !== 1 || !pg_sfv_stats_ready()) {
        return;
    }

    @mysqli_query(db::$con, "DELETE FROM submitted_form_view_stats WHERE view_date < DATE_SUB(CURDATE(), INTERVAL 6 MONTH)");
}

/**
 * Remove view history for a deleted article or page.
 *
 * Clears both tables while the legacy one is still around. Clearing only the
 * rollup would let the backfill resurrect a deleted article's counts the next
 * time it passed over those days.
 *
 * @param string $column Either 'submitted_form_id' or 'page_id'
 * @param int    $id     Row to clear
 */
function pg_sfv_delete_views($column, $id)
{
    $column = ($column === 'page_id') ? 'page_id' : 'submitted_form_id';
    $id     = (int) $id;

    if (pg_sfv_stats_ready()) {
        db("DELETE FROM submitted_form_view_stats WHERE $column = '$id'");
    }

    if (pg_sfv_legacy_table_exists()) {
        db("DELETE FROM submitted_form_views WHERE $column = '$id'");
    }
}

/**
 * Read the article view backfill's bookkeeping, or false when unavailable.
 */
function pg_sfv_backfill_state($recheck = false)
{
    if (!pg_sfv_stats_ready($recheck)) {
        return false;
    }

    $row = db_item("SELECT sfv_rollup_cutover, sfv_rollup_cursor, sfv_rollup_done FROM config");

    if (!is_array($row)) {
        return false;
    }

    return array(
        'cutover' => (int) $row['sfv_rollup_cutover'],
        'cursor'  => (int) $row['sfv_rollup_cursor'],
        'done'    => ((int) $row['sfv_rollup_done'] === 1),
    );
}

/**
 * Summarise a slice of legacy per-view rows into the daily rollup.
 *
 * Reads from submitted_form_views and writes only to the rollup. The source is
 * left untouched so it can be inspected, and renamed rather than dropped, until
 * the results have been trusted for a release.
 *
 * Written to be interrupted, for the same reason pg_visitor_backfill_step() is:
 * servers behind IIS FastCGI or an nginx proxy end a long request on their own
 * schedule regardless of PHP's max_execution_time, and install/index.php writes
 * the version number only after the upgrade function returns.
 *
 * @param  int  $budget_seconds Wall clock to spend before returning
 * @return array|false          Progress state, or false when unavailable
 */
function pg_sfv_backfill_step($budget_seconds = 3, $recheck = false)
{
    $state = pg_sfv_backfill_state($recheck);

    if ($state === false || $state['done'] || $state['cutover'] <= 0) {
        return $state;
    }

    // Nothing left to read once the legacy table has been retired.
    if (!pg_sfv_legacy_table_exists($recheck)) {
        db("UPDATE config SET sfv_rollup_done = 1");
        $state['done'] = true;

        return $state;
    }

    // The live writer buckets with CURDATE(), this one with
    // DATE(FROM_UNIXTIME(...)). Both clocks have to read the same, or history
    // and new traffic land on different days and the two meet at a seam.
    pg_sync_mysql_timezone();

    $budget_seconds = max(1, (int) $budget_seconds);
    $deadline       = microtime(true) + $budget_seconds;

    $cursor  = $state['cursor'];
    $cutover = $state['cutover'];

    while ($cursor < $cutover && microtime(true) < $deadline) {

        // One calendar day per statement.
        //
        // The legacy table has no primary key -- all four of its indexes are
        // non-unique -- so there is no id to walk the way the visitor backfill
        // does. `timestamp` is indexed, and a day is both a natural bound and
        // exactly the bucket being written, so a chunk maps to one row per
        // (article, page).
        //
        // strtotime() rather than +86400: a day is not always 86,400 seconds
        // long, and a DST boundary would otherwise split one date across two
        // chunks.
        $upper = min($cutover, strtotime('+1 day', $cursor));

        if ($upper <= $cursor) {
            break;
        }

        // Grouped in a derived table, then inserted. Computing DATE() in a
        // GROUP BY SELECT asks MySQL to prove the expression is functionally
        // dependent on its own inputs, which it does inconsistently across
        // versions under ONLY_FULL_GROUP_BY.
        //
        // += rather than = because the final chunk is a partial day that the
        // live writer has already written rows for. Adding is correct there and
        // harmless everywhere else: the cursor guarantees no chunk runs twice.
        @mysqli_query(
            db::$con,
            "INSERT INTO submitted_form_view_stats (submitted_form_id, page_id, view_date, views)
             SELECT sid, pid, d, c FROM (
                 SELECT
                     submitted_form_id              AS sid,
                     page_id                        AS pid,
                     DATE(FROM_UNIXTIME(timestamp)) AS d,
                     COUNT(*)                       AS c
                 FROM submitted_form_views
                 WHERE timestamp >= " . (int) $cursor . " AND timestamp < " . (int) $upper . "
                 GROUP BY sid, pid, d
             ) x
             ON DUPLICATE KEY UPDATE views = views + VALUES(views)"
        );

        $cursor = $upper;

        // Recorded after every chunk, not at the end. A request killed by a
        // server timeout costs one day of work, not the whole run.
        db("UPDATE config SET sfv_rollup_cursor = '" . (int) $cursor . "'");
    }

    if ($cursor >= $cutover) {
        db("UPDATE config SET sfv_rollup_done = 1");
    }

    return array(
        'cutover' => $cutover,
        'cursor'  => $cursor,
        'done'    => ($cursor >= $cutover),
    );
}

/**
 * Turn rollup rows into display labels and links.
 *
 * Rows store what was viewed as a type and a primary key, so the title is
 * looked up here rather than copied into every historical row. Resolution is
 * batched: one query per item type for the whole result set, not one per row.
 *
 * A row whose item no longer exists keeps its page name, so deleting a product
 * does not erase the traffic it once had.
 *
 * @param  array $rows Rows carrying item_type, item_id and page_name
 * @return array       Same rows with 'label' and 'url' filled in
 */
function pg_visitor_label_content_rows($rows)
{
    if (empty($rows) || !is_array($rows)) {
        return array();
    }

    // Collect the ids to look up, grouped by type.
    $wanted = array();
    foreach ($rows as $row) {
        $type = isset($row['item_type']) ? (string) $row['item_type'] : '';
        $id   = isset($row['item_id']) ? (int) $row['item_id'] : 0;
        if ($type !== '' && $id > 0) {
            $wanted[$type][$id] = $id;
        }
    }

    // One query per type. `name` is a product's primary label and
    // short_description is its fallback, matching how add_product.php derives
    // an address name when one is not given.
    //
    // address_name / reference_code come along because the link needs them:
    // the recorded page name is the template that displayed the item, so on
    // its own it points at a page that cannot show anything without the
    // item's own segment.
    $sources = array(
        'product'       => "SELECT id, CASE WHEN name <> '' THEN name ELSE short_description END AS label, address_name, '' AS reference_code, 0 AS form_page_id FROM products WHERE id IN (%s)",
        'product_group' => "SELECT id, CASE WHEN name <> '' THEN name ELSE short_description END AS label, address_name, '' AS reference_code, 0 AS form_page_id FROM product_groups WHERE id IN (%s)",
        'form'          => "SELECT id, address_name AS label, address_name, reference_code, page_id AS form_page_id FROM forms WHERE id IN (%s)",
    );

    $items          = array();
    $form_page_ids  = array();

    foreach ($wanted as $type => $ids) {
        if (!isset($sources[$type]) || empty($ids)) {
            continue;
        }

        $id_list = implode(',', array_map('intval', $ids));
        $result  = @mysqli_query(db::$con, sprintf($sources[$type], $id_list));

        if (!$result) {
            continue;
        }

        while ($r = @mysqli_fetch_assoc($result)) {
            $items[$type . ':' . (int) $r['id']] = array(
                'label'          => trim((string) $r['label']),
                'address_name'   => trim((string) $r['address_name']),
                'reference_code' => trim((string) $r['reference_code']),
                'form_page_id'   => (int) $r['form_page_id'],
            );

            if ($type === 'form' && (int) $r['form_page_id'] > 0) {
                $form_page_ids[(int) $r['form_page_id']] = (int) $r['form_page_id'];
            }
        }
    }

    // A submitted form has two possible addresses and the custom form's own
    // settings decide which one works. With pretty URLs on it is reached
    // through the LIST view page plus its address name (/blog/some-article);
    // with them off, through the ITEM view page plus a reference code
    // (/blog-post?r=P8TDKZMZGV). Building only the second shape, or neither,
    // produces a link to a bare item view page that has no article to show.
    //
    // Both branches mirror get_form_list_view.php:1406-1411.
    $form_routes = array();

    if (!empty($form_page_ids)) {

        $page_list = implode(',', array_map('intval', $form_page_ids));

        // Pretty URLs need the setting AND a title field, because the address
        // name is derived from that field. Same two conditions
        // check_if_pretty_urls_are_enabled() applies, asked once for all
        // forms in the result rather than once per row.
        $result = @mysqli_query(
            db::$con,
            "SELECT
                 cfp.page_id,
                 cfp.pretty_urls,
                 (SELECT COUNT(*) FROM form_fields ff
                   WHERE ff.page_id = cfp.page_id AND ff.rss_field = 'title') AS title_fields
             FROM custom_form_pages cfp
             WHERE cfp.page_id IN ($page_list)"
        );

        if ($result) {
            while ($r = @mysqli_fetch_assoc($result)) {
                $form_routes[(int) $r['page_id']] = array(
                    'pretty'    => ((int) $r['pretty_urls'] === 1 && (int) $r['title_fields'] > 0),
                    'list_page' => '',
                );
            }
        }

        // The list view page that publishes this custom form. Collection 'a'
        // is the default the router itself assumes (get_page.php:114).
        $result = @mysqli_query(
            db::$con,
            "SELECT flv.custom_form_page_id, p.page_name
             FROM form_list_view_pages flv
             LEFT JOIN page p ON p.page_id = flv.page_id
             WHERE flv.custom_form_page_id IN ($page_list)
               AND flv.collection = 'a'"
        );

        if ($result) {
            while ($r = @mysqli_fetch_assoc($result)) {
                $pid = (int) $r['custom_form_page_id'];
                if (!isset($form_routes[$pid])) {
                    $form_routes[$pid] = array('pretty' => false, 'list_page' => '');
                }
                $form_routes[$pid]['list_page'] = trim((string) $r['page_name']);
            }
        }
    }

    $base = (defined('URL_SCHEME') ? URL_SCHEME : '')
        . (defined('HOSTNAME_SETTING') ? HOSTNAME_SETTING : '')
        . (defined('PATH') ? PATH : '/');

    foreach ($rows as $key => $row) {
        $type      = isset($row['item_type']) ? (string) $row['item_type'] : '';
        $id        = isset($row['item_id']) ? (int) $row['item_id'] : 0;
        $page_name = isset($row['page_name']) ? trim((string) $row['page_name']) : '';
        $item      = ($type !== '' && $id > 0 && isset($items[$type . ':' . $id])) ? $items[$type . ':' . $id] : null;

        $label = ($item && $item['label'] !== '') ? $item['label'] : '';

        // Fall back to the page. Historical rows have no item because the item
        // was never recorded, and a deleted product leaves the same gap.
        if ($label === '') {
            $label = ($page_name === '' || $page_name === '/') ? lang('Homepage') : $page_name;
        }

        // Default: the page itself. Correct for anything that is not an item
        // — listings, the home page, ordinary content pages.
        $url = $base . encode_url_path($page_name);

        if ($item) {
            if ($type === 'form') {
                $route = isset($form_routes[$item['form_page_id']]) ? $form_routes[$item['form_page_id']] : null;

                if ($route && $route['pretty'] && $route['list_page'] !== '' && $item['address_name'] !== '') {
                    $url = $base . encode_url_path($route['list_page']) . '/' . encode_url_path($item['address_name']);
                } elseif ($item['reference_code'] !== '') {
                    // The recorded page name is already the item view page,
                    // which is exactly what this form of the link needs.
                    $url = $base . encode_url_path($page_name) . '?r=' . rawurlencode($item['reference_code']);
                }

            } elseif ($item['address_name'] !== '') {
                // Catalog items hang off whichever page displayed them, and
                // that is the page recorded on the row.
                $url = $base . encode_url_path($page_name) . '/' . encode_url_path($item['address_name']);
            }
        }

        $rows[$key]['label'] = $label;
        $rows[$key]['url']   = $url;
    }

    return $rows;
}

/**
 * Most viewed content over a date range.
 *
 * Reads the hourly rollup, so the cost is set by how much distinct content the
 * range covers, not by how many people visited. This is the query that used to
 * group several million raw visitor rows on a computed expression.
 */
function pg_visitor_top_content($start_date, $end_date, $limit = 5)
{
    if (!pg_visitor_rollup_ready()) {
        return array();
    }

    $limit  = max(1, (int) $limit);
    $result = @mysqli_query(
        db::$con,
        "SELECT page_id, page_name, item_type, item_id, SUM(views) AS views
         FROM visitor_content_hourly
         WHERE stat_date >= '" . e($start_date) . "' AND stat_date <= '" . e($end_date) . "'
         GROUP BY page_id, item_type, item_id, page_name
         ORDER BY views DESC, page_id ASC, item_type ASC, item_id ASC, page_name ASC
         LIMIT " . $limit
    );

    $rows = array();

    if ($result) {
        while ($r = @mysqli_fetch_assoc($result)) {
            $r['views'] = (int) $r['views'];
            $rows[]     = $r;
        }
    }

    return pg_visitor_label_content_rows($rows);
}

/**
 * Busiest content in each hour of a single day, indexed 0-23.
 *
 * Written as a join against a grouped maximum rather than a window function,
 * which MySQL only gained in 8.0 and this software still supports 5.5 upward.
 * The inner aggregate is answered from idx_date_hour; the outer select returns
 * at most 24 rows.
 */
function pg_visitor_top_content_by_hour($date)
{
    $hours = array_fill(0, 24, null);

    if (!pg_visitor_rollup_ready()) {
        return $hours;
    }

    $date = e($date);

    // Ties are broken in PHP rather than with a GROUP BY on the outer query.
    // Selecting ungrouped columns alongside a GROUP BY is rejected outright
    // under ONLY_FULL_GROUP_BY, which MySQL 5.7 turned on by default, and the
    // widget would fail on any reasonably current server. The inner aggregate
    // is unaffected: it groups and aggregates nothing else.
    $result = @mysqli_query(
        db::$con,
        "SELECT c.stat_hour, c.page_id, c.page_name, c.item_type, c.item_id, c.views
         FROM visitor_content_hourly c
         INNER JOIN (
             SELECT stat_hour, MAX(views) AS top_views
             FROM visitor_content_hourly
             WHERE stat_date = '$date'
             GROUP BY stat_hour
         ) m ON m.stat_hour = c.stat_hour AND m.top_views = c.views
         WHERE c.stat_date = '$date'
         ORDER BY c.stat_hour, c.id"
    );

    if (!$result) {
        return $hours;
    }

    $rows = array();

    while ($r = @mysqli_fetch_assoc($result)) {
        $hour = (int) $r['stat_hour'];

        // First row wins; the rest of a tied hour is discarded.
        if (isset($rows[$hour])) {
            continue;
        }

        $rows[$hour] = array(
            'page_id'   => $r['page_id'],
            'page_name' => $r['page_name'],
            'item_type' => $r['item_type'],
            'item_id'   => $r['item_id'],
            'views'     => (int) $r['views'],
        );
    }

    foreach (pg_visitor_label_content_rows($rows) as $hour => $row) {
        $hours[(int) $hour] = array('name' => $row['label'], 'cnt' => $row['views']);
    }

    return $hours;
}

/**
 * Visitor and page-view totals for a date range, keyed by date then hour.
 *
 * Replaces GROUP BY HOUR(FROM_UNIXTIME(start_timestamp)) over the raw table.
 * A year of traffic is 8,760 rows here whatever the traffic was.
 */
function pg_visitor_stats_range($start_date, $end_date)
{
    $out = array();

    if (!pg_visitor_rollup_ready()) {
        return $out;
    }

    $result = @mysqli_query(
        db::$con,
        "SELECT stat_date, stat_hour, new_visitors, page_views
         FROM visitor_stats_hourly
         WHERE stat_date >= '" . e($start_date) . "' AND stat_date <= '" . e($end_date) . "'"
    );

    if (!$result) {
        return $out;
    }

    while ($r = @mysqli_fetch_assoc($result)) {
        $out[$r['stat_date']][(int) $r['stat_hour']] = array(
            'visitors'   => (int) $r['new_visitors'],
            'page_views' => (int) $r['page_views'],
        );
    }

    return $out;
}

/**
 * True when the request comes from an automated client rather than a person.
 *
 * The "bots are not recorded" rule, shared by the visitor row
 * (init_tracking()) and the record view counters: a bot the firewall marked
 * (IS_BOT); without that mark (firewall off, allow-listed address, excluded
 * path) any user agent class other than human; and other Pinegrap
 * installations, which the firewall treats as ordinary clients so they are
 * never blocked (not through IS_BOT: that constant also exempts a request
 * from rate limiting, which a claimed user agent must not buy). Not cached:
 * called once per counted page.
 */
function pg_request_is_automated()
{
    if (defined('IS_BOT') && IS_BOT) {
        return true;
    }

    if (!defined('IS_BOT') && function_exists('waf_classify_bot')) {
        $class = waf_classify_bot(isset($_SERVER['HTTP_USER_AGENT']) ? (string) $_SERVER['HTTP_USER_AGENT'] : '');

        if ($class['class'] !== 'human') {
            return true;
        }
    }

    return function_exists('waf_is_pinegrap_client') && waf_is_pinegrap_client();
}

// The visitor's row in visitors for this request: opened or found again, with
// the tracking, affiliate and campaign codes and the referring site. Called
// by get_page.php for a page it draws and by page_view.php for a page the
// page cache answered (includes/fn/page_cache.php).
function init_tracking()
{
	// No visitor row for crawlers, scripts, uptime monitors or other Pinegrap
	// installations: the rule the record view counters share (seo.php).
	if (pg_request_is_automated()) {
		return;
	}

	$referring_search_engine = '';
	// if there is a tracking code in the query string, then store tracking code in session and cookie (overwrite existing cookie, if there is one)
	if (isset($_GET['t']) && $_GET['t'])
	{
		$tracking_code = trim($_GET['t']);
		$_SESSION['software']['tracking_code'] = $tracking_code;
		// If we have not already retrieved the tracking code duration from the db, then get it.
		// Tracking code duration is the number of days that tracking code should be stored.
		if (!defined('TRACKING_CODE_DURATION'))
		{
			define('TRACKING_CODE_DURATION', db("SELECT tracking_code_duration FROM config"));
		}
		// Set tracking code cookie.
		pg_consent_setcookie('analytics', 'software[tracking_code]', $tracking_code, time() + (86400 * TRACKING_CODE_DURATION));
		// else there is not a tracking code in the query string, so get tracking code
		
	}
	else
	{
		$tracking_code = get_tracking_code();
	}
	// if there is an affiliate code in the query string, then store affiliate code in session and cookie (overwrite existing cookie, if there is one)
	if (isset($_GET['a']) && $_GET['a'])
	{
		$affiliate_code = trim($_GET['a']);
		$_SESSION['software']['affiliate_code'] = $affiliate_code;
		// set affiliate code cookie for 10 years
		pg_consent_setcookie('marketing', 'software[affiliate_code]', $affiliate_code, time() + 315360000);
		// else there is not an affiliate code in the query string, so get affiliate code
		
	}
	else
	{
		$affiliate_code = get_affiliate_code();
	}
	// if there is an http referer and the http referer has not already been tracked
	if (isset($_SERVER['HTTP_REFERER']) && $_SERVER['HTTP_REFERER'] && !isset($_SESSION['software']['http_referer']))
	{
		// get the http referer host and remove www. from it. A referer
		// without a host ("foo", "/path", "https:///") has an empty one.
		$http_referer_host = str_replace('www.', '', (string) parse_url($_SERVER['HTTP_REFERER'], PHP_URL_HOST));
		// remove www. and the port from http host. The referer host above
		// never carries the port, so a site served on one (127.0.0.1:8000)
		// counted its own pages as a referring site.
		$http_host = str_replace('www.', '', preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? ''));
		// if the http referer is not from the same host as the http host, then store http referer in session
		if ($http_referer_host != $http_host)
		{
			$_SESSION['software']['http_referer'] = $_SERVER['HTTP_REFERER'];
		}
	}
	$referer = '';
	if(isset($_SESSION['software']['http_referer'])){
		$referer = $_SESSION['software']['http_referer'];
	}
	

	$referring_host_name = '';
	$referring_search_terms = '';
	
	// if visitor id is not already set in the session, then this is a new visitor
	// Record the visitor's REAL address. Behind a CDN or reverse proxy,
	// REMOTE_ADDR is the edge server, so every visitor previously collapsed
	// into a handful of addresses. waf_client_ip() returns REMOTE_ADDR
	// unchanged unless the operator has declared a trusted proxy, so this is
	// identical behaviour on plain hosting.
	$visitor_ip_address = function_exists('waf_client_ip')
		? waf_client_ip()
		: (isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '');

	$visitor_user_agent = isset($_SERVER['HTTP_USER_AGENT'])
		? mb_substr($_SERVER['HTTP_USER_AGENT'], 0, 255)
		: '';

	if (empty($_SESSION['software']['visitor_id']) || !$_SESSION['software']['visitor_id'])
	{
		// Legacy fallback: ignore host-tracker.com monitoring bot.
		if (mb_strpos($_SERVER['HTTP_USER_AGENT'] ?? '', 'host-tracker.com') !== false)
		{
			return;
		}
		// check to see if visitor should be connected to an existing visitor record
		// (e.g. if the visitor is a robot that just recently visited)
		// if all of the data is the same (e.g. ip address) and the visitor visited in the last 30 minutes,
		// then we will connect the visitor to an existing record
		$thirty_minutes_ago = time() - 1800;

		// http_referer was deliberately REMOVED from this key.
		//
		// Only a client that ignores cookies ever reaches this branch, and
		// that is almost exclusively automated traffic. Such a client sends a
		// different referer on every page it walks, so requiring the referer
		// to match meant the lookup never matched — and a brand new visitor
		// row was inserted for each individual request. One crawler reading a
		// blog therefore registered as tens of thousands of "visitors", which
		// is why the site counter ran far ahead of Google Analytics (which is
		// JavaScript-based and counts none of them).
		//
		// Campaign attribution does not depend on the referer here:
		// tracking_code and affiliate_code are still part of the key, so a
		// visitor arriving under a different campaign still opens a new record.
		//
		// ORDER BY + LIMIT make the choice deterministic now that more than
		// one row can match; the most recently active record wins.
		$query = "SELECT id

                 FROM visitors

                 WHERE

                    (ip_address = IFNULL(INET_ATON('" . escape($visitor_ip_address) . "'), 0))

                    AND (stop_timestamp > '$thirty_minutes_ago')

                    AND (tracking_code = '" . escape($tracking_code) . "')

                    AND (affiliate_code = '" . escape($affiliate_code) . "')

                 ORDER BY stop_timestamp DESC

                 LIMIT 1";
		$result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
		// if a matching visitor was found, connect visitor to existing record
		if (mysqli_num_rows($result) > 0)
		{
			$row = mysqli_fetch_assoc($result);
			$_SESSION['software']['visitor_id'] = $row['id'];
			// else a matching visitor was not found, so create new visitor record
			
		}
		else
		{
			// if number of visits cookie is set
			if (!empty($_COOKIE['software']['number_of_visits']))
			{
				$first_visit = 0;
				pg_consent_setcookie('analytics', 'software[number_of_visits]', $_COOKIE['software']['number_of_visits'] + 1, time() + 315360000);
				// else number of visits cookie is not set
				
			}
			else
			{
				$first_visit = 1;
				pg_consent_setcookie('analytics', 'software[number_of_visits]', '1', time() + 315360000);
			}
			// if there is an http referer
			if ($referer)
			{
				// Parse the stored referer rather than this request's. It is
				// stored on the first page of the visit; if that page ended
				// before the visitor record was created, this request may
				// carry another referer or none.
				$referer_parts = parse_url($referer);
				if (!is_array($referer_parts))
				{
					$referer_parts = array();
				}
				$referring_host_name = $referer_parts['host'] ?? '';
				// check to see if the referring host is a search engine and find search query delimiter.
				// The host and not the whole address: flask.com, or another
				// site's address that merely contains "www.google", is no search engine.
				$delimiter = '';
				if (preg_match('/www\.google.*/i', $referring_host_name))
				{
					$referring_search_engine = 'Google';
					$delimiter = 'q';
				}
				elseif (preg_match('/search\.msn.*/i', $referring_host_name))
				{
					$referring_search_engine = 'MSN';
					$delimiter = 'q';
				}
				elseif (preg_match('/search\.yahoo.*/i', $referring_host_name))
				{
					$referring_search_engine = 'Yahoo!';
					$delimiter = 'p';
				}
				elseif (preg_match('/www\.bing\.com/i', $referring_host_name))
				{
					$referring_search_engine = 'Bing';
					$delimiter = 'q';
				}
				elseif (preg_match('/(search\.aol\.com)|(aolsearch\.aol\.com)/i', $referring_host_name))
				{
					$referring_search_engine = 'AOL';
					$delimiter = 'query';
				}
				elseif (preg_match('/(^|\.)ask\.com$/i', $referring_host_name))
				{
					$referring_search_engine = 'Ask.com';
					$delimiter = 'q';
				}
				elseif (preg_match('/www\.ask\.co\.uk/i', $referring_host_name))
				{
					$referring_search_engine = 'Ask.com';
					$delimiter = 'ask';
				}
				elseif (preg_match('/search\.earthlink\.net/i', $referring_host_name))
				{
					$referring_search_engine = 'EarthLink';
					$delimiter = 'q';
				}
				elseif (preg_match('/www\.netscape\.com/i', $referring_host_name))
				{
					$referring_search_engine = 'Netscape';
					$delimiter = 's';
				}
				elseif (preg_match('/(search\.netscape\.com)|(searcht\.netscape\.com)/i', $referring_host_name))
				{
					$referring_search_engine = 'Netscape';
					$delimiter = 'query';
				}
				elseif (preg_match('/www\.alltheweb\.com/i', $referring_host_name))
				{
					$referring_search_engine = 'AlltheWeb';
					$delimiter = 'q';
				}
				elseif (preg_match('/msxml\.excite\.com/i', $referring_host_name))
				{
					$referring_search_engine = 'Excite';
					$delimiter = 'qkw';
				}
				elseif (preg_match('/www\.metacrawler\.com/i', $referring_host_name))
				{
					$referring_search_engine = 'MetaCrawler';
					$delimiter = 'qkw';
				}
				elseif (preg_match('/dpxml\.webcrawler\.com/i', $referring_host_name))
				{
					$referring_search_engine = 'WebCrawler';
					$delimiter = 'qkw';
				}
				elseif (preg_match('/search\.lycos\.com/i', $referring_host_name))
				{
					$referring_search_engine = 'Lycos';
					$delimiter = 'query';
				}
				elseif (preg_match('/www\.hotbot\.com/i', $referring_host_name))
				{
					$referring_search_engine = 'HotBot';
					$delimiter = 'query';
				}
				elseif (preg_match('/search\.mamma\.com/i', $referring_host_name))
				{
					$referring_search_engine = 'Mamma.com';
					$delimiter = 'query';
				}
				elseif (preg_match('/search\.cometsystems\.com/i', $referring_host_name))
				{
					$referring_search_engine = 'Comet Web Search';
					$delimiter = 'qry';
				}
				elseif (preg_match('/www\.overture\.com/i', $referring_host_name))
				{
					$referring_search_engine = 'Overture';
					$delimiter = 'Keywords';
				}
				elseif (preg_match('/www\.looksmart\.com/i', $referring_host_name))
				{
					$referring_search_engine = 'LookSmart';
					$delimiter = 'key';
				}
				elseif (preg_match('/search\.looksmart\.com/i', $referring_host_name))
				{
					$referring_search_engine = 'LookSmart';
					$delimiter = 'qt';
				}
				elseif (preg_match('/search\.viewpoint\.com/i', $referring_host_name))
				{
					$referring_search_engine = 'Viewpoint';
					$delimiter = 'k';
				}
				elseif (preg_match('/search\.dmoz\.org/i', $referring_host_name))
				{
					$referring_search_engine = 'Open Directory Project';
					$delimiter = 'search';
				}
				// if a delimiter was found, get search terms. Search engines
				// mostly send their bare address now (https://www.google.com/),
				// so there is often no query string at all.
				if ($delimiter && isset($referer_parts['query']))
				{
					// get query string items
					parse_str($referer_parts['query'], $query_string_items);
					// get referring search terms from query string. parse_str()
					// has already decoded them: decoding again turned "c%2B%2B"
					// into "c  ". A repeated parameter (q[]=x) arrives as an
					// array, which trim() refused with an error page.
					$search_terms = $query_string_items[$delimiter] ?? '';
					$referring_search_terms = is_string($search_terms) ? trim($search_terms) : '';
				}
			}
			
			$visitor_currency_code = '';
			if(defined('VISITOR_CURRENCY_CODE') && VISITOR_CURRENCY_CODE != ''){
			    $visitor_currency_code = VISITOR_CURRENCY_CODE;
			}
			// user_agent arrived in 2026.2.5. Probed rather than assumed so an
			// install that has taken the code update but not the database
			// upgrade keeps tracking visitors instead of failing every page
			// view with an unknown-column error.
			$sql_user_agent_column = '';
			$sql_user_agent_value = '';

			if (function_exists('waf_table_has_column')
				&& waf_table_has_column('visitors', 'user_agent')
			) {
				$sql_user_agent_column = 'user_agent,';
				$sql_user_agent_value = "'" . escape($visitor_user_agent) . "',";
			}

			// Since 2026.4.10 (10.6) the referring address and the user agent
			// are kept once each in visitor_texts and the row names them by
			// number (includes/fn/visitor_texts.php). Where the list cannot be
			// written the texts go into the row as before.
			$sql_text_columns = '';
			$sql_text_values = '';
			$sql_referer = $referer;

			if (function_exists('pg_visitor_texts_ready') && pg_visitor_texts_ready()) {

				$referer_text_id = pg_visitor_text_id(PG_VISITOR_TEXT_REFERER, $referer);
				$agent_text_id = pg_visitor_text_id(PG_VISITOR_TEXT_AGENT, $visitor_user_agent);

				if (($referer_text_id !== false) && ($agent_text_id !== false)) {
					$sql_text_columns = 'http_referer_id, user_agent_id,';
					$sql_text_values = (int) $referer_text_id . ', ' . (int) $agent_text_id . ',';
					$sql_referer = '';

					if ($sql_user_agent_column !== '') {
						$sql_user_agent_value = "'',";
					}
				}
			}

			$query = "INSERT INTO visitors (

                        $sql_user_agent_column

                        $sql_text_columns

                        ip_address,

                        http_referer,

                        referring_host_name,

                        referring_search_engine,

                        referring_search_terms,

                        first_visit,

                        tracking_code,

                        currency_code,

                        affiliate_code,

                        start_timestamp,

                        stop_timestamp)

                     VALUES (

                        $sql_user_agent_value

                        $sql_text_values

                        IFNULL(INET_ATON('" . escape($visitor_ip_address) . "'), 0),

                        '" . escape($sql_referer) . "',

                        '" . escape($referring_host_name) . "',

                        '" . escape($referring_search_engine) . "',

                        '" . escape($referring_search_terms) . "',

                        '$first_visit',

                        '" . escape($tracking_code) . "',

                        '" . escape($visitor_currency_code) . "',

                        '" . escape($affiliate_code) . "',

                        UNIX_TIMESTAMP(),

                        UNIX_TIMESTAMP())";
			$result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
			$_SESSION['software']['visitor_id'] = mysqli_insert_id(db::$con);

			// Count the new session in the hourly rollup. Placed here rather
			// than derived later so the counter measures exactly what the old
			// reports measured: a visitors row whose start_timestamp is now.
			pg_record_visitor_session();
		}
	}
	// Assume that we don't need to update visitor or order utm tags, until we find out otherwise.
	$update_utm = false;
	// If utm tags appear in the URL, then store them in session, cookie and visitor record.
	// A link often carries utm_source alone; the tags it leaves out are stored
	// empty, as before, without a warning for each.
	if (isset($_GET['utm_source']) && $_GET['utm_source'])
	{
		$_SESSION['software']['utm_source'] = trim($_GET['utm_source']);
		$_SESSION['software']['utm_medium'] = trim($_GET['utm_medium'] ?? '');
		$_SESSION['software']['utm_campaign'] = trim($_GET['utm_campaign'] ?? '');
		$_SESSION['software']['utm_term'] = trim($_GET['utm_term'] ?? '');
		$_SESSION['software']['utm_content'] = trim($_GET['utm_content'] ?? '');
		$cookie_id = 0;
		if (!empty($_SESSION['software']['cookie_id']))
		{
			$cookie_id = db("SELECT id FROM cookies WHERE id = '" . e($_SESSION['software']['cookie_id']) . "'");
		}
		else if (!empty($_COOKIE['lsid']))
		{
			$cookie_id = db("SELECT id FROM cookies WHERE lsid = '" . e($_COOKIE['lsid']) . "'");
		}
		// If a cookie was found in the db, then update it.
		if ($cookie_id)
		{
			db("UPDATE cookies SET

                    utm_source = '" . e($_SESSION['software']['utm_source']) . "',

                    utm_medium = '" . e($_SESSION['software']['utm_medium']) . "',

                    utm_campaign = '" . e($_SESSION['software']['utm_campaign']) . "',

                    utm_term = '" . e($_SESSION['software']['utm_term']) . "',

                    utm_content = '" . e($_SESSION['software']['utm_content']) . "',

                    modified_timestamp = UNIX_TIMESTAMP()

                WHERE id = '" . e($cookie_id) . "'");
			// Otherwise a cookie was not found, so create one.  We store the UTM data in the db instead
			// of the actual cookie for performance reasons, so that the visitor's browser does not
			// have to send all of that UTM data in a cookie for every request.  We just store a UUID
			// value in the actual cookie, which connects to a db record.
			
		}
		else
		{
			$lsid = db("SELECT UUID()");
			db("INSERT INTO cookies (

                    lsid,

                    utm_source,

                    utm_medium,

                    utm_campaign,

                    utm_term,

                    utm_content,

                    created_timestamp,

                    modified_timestamp)

                VALUES (

                    '" . e($lsid) . "',

                    '" . e($_SESSION['software']['utm_source']) . "',

                    '" . e($_SESSION['software']['utm_medium']) . "',

                    '" . e($_SESSION['software']['utm_campaign']) . "',

                    '" . e($_SESSION['software']['utm_term']) . "',

                    '" . e($_SESSION['software']['utm_content']) . "',

                    UNIX_TIMESTAMP(),

                    UNIX_TIMESTAMP())");
			$cookie_id = mysqli_insert_id(db::$con);
			// Set cookie in visitor's browser for 10 years.  Even though we only care about the
			// UTM data for 30 days, we store the cookie for 10 years, because we might eventually
			// use this same cookie to store data for other features.
			pg_consent_setcookie('analytics', 'lsid', $lsid, time() + 315360000);
			// For 1 in 100 chance, delete cookies that have not been modified in a year.
			// The 1 in 100 chance is just added for performance reasons.
			if (rand(1, 100) == 1)
			{
				db("DELETE FROM cookies WHERE modified_timestamp < " . (time() - 31536000));
			}
		}
		$_SESSION['software']['cookie_id'] = $cookie_id;
		$update_utm = true;
		// Otherwise, there are no utm tags in the URL, so if we have not looked for utm tags in a
		// cookie yet for this session and there is a cookie, then do that, so they will be available
		// in the session.  We do this once per session.
		
	}
	else if (!isset($_SESSION['software']['utm_source']) and isset($_COOKIE['lsid']) && $_COOKIE['lsid'])
	{
		// If we have not already retrieved the tracking code duration from the db, then get it.
		// Tracking code duration is the number of days that tracking code should be stored.
		if (!defined('TRACKING_CODE_DURATION'))
		{
			define('TRACKING_CODE_DURATION', db("SELECT tracking_code_duration FROM config"));
		}
		// Try to find utm tag info in cookie that is less than the tracking code duration, from
		// the site settings.
		$cookie = db_item("SELECT

                id,

                utm_source,

                utm_medium,

                utm_campaign,

                utm_term,

                utm_content

            FROM cookies

            WHERE

                (lsid = '" . e($_COOKIE['lsid']) . "')

                AND (modified_timestamp > '" . (time() - (86400 * TRACKING_CODE_DURATION)) . "')");
		// If a cookie was found, then update session.
		if ($cookie)
		{
			$_SESSION['software']['cookie_id'] = $cookie['id'];
			$_SESSION['software']['utm_source'] = $cookie['utm_source'];
			$_SESSION['software']['utm_medium'] = $cookie['utm_medium'];
			$_SESSION['software']['utm_campaign'] = $cookie['utm_campaign'];
			$_SESSION['software']['utm_term'] = $cookie['utm_term'];
			$_SESSION['software']['utm_content'] = $cookie['utm_content'];
			$update_utm = true;
			// Otherwise a cookie could not be found, so remember that we have already done this check
			// by setting utm_source to blank.
			
		}
		else
		{
			$_SESSION['software']['utm_source'] = '';
		}
	}
	// If the utm tags for the visitor or order should be updated, then do that.
	if ($update_utm)
	{
		if ($_SESSION['software']['visitor_id'])
		{
			db("UPDATE visitors

                SET

                    utm_source = '" . e($_SESSION['software']['utm_source']) . "',

                    utm_medium = '" . e($_SESSION['software']['utm_medium']) . "',

                    utm_campaign = '" . e($_SESSION['software']['utm_campaign']) . "',

                    utm_term = '" . e($_SESSION['software']['utm_term']) . "',

                    utm_content = '" . e($_SESSION['software']['utm_content']) . "',

                    stop_timestamp = UNIX_TIMESTAMP()

                WHERE id = '" . e($_SESSION['software']['visitor_id']) . "'");
		}
		if (($_SESSION['ecommerce']['order_id'] ?? ''))
		{
			db("UPDATE orders

                SET

                    utm_source = '" . e($_SESSION['software']['utm_source']) . "',

                    utm_medium = '" . e($_SESSION['software']['utm_medium']) . "',

                    utm_campaign = '" . e($_SESSION['software']['utm_campaign']) . "',

                    utm_term = '" . e($_SESSION['software']['utm_term']) . "',

                    utm_content = '" . e($_SESSION['software']['utm_content']) . "'

                WHERE id = '" . e(($_SESSION['ecommerce']['order_id'] ?? '')) . "'");
		}
	}
}

function update_visitor_page_data($page_name, $page_id = 0)
{
    pg_record_visitor_page_view($page_id, $page_name);

    // init_tracking() opens no visitor record for a crawler, another Pinegrap
    // installation or an uptime monitor, so there is no row to update.
    $visitor_id = (int) ($_SESSION['software']['visitor_id'] ?? 0);

    if ($visitor_id <= 0) {
        return;
    }

    // check to see if landing page name has already been set
    $query = "SELECT landing_page_name

             FROM visitors

             WHERE id = '" . $visitor_id . "'";
    $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
    $row = mysqli_fetch_assoc($result);
    // The session can name a row that is gone: a restored backup, a table
    // emptied by hand.
    if (!is_array($row)) {
        return;
    }
    $existing_landing_page_name = $row['landing_page_name'];
    // if there is not an existing landing page name, prepare sql for landing page name update
    if (!$existing_landing_page_name) {
        $sql_landing_page_name = "landing_page_name = '" . escape($page_name) . "',";
    } else {
        $sql_landing_page_name = '';
    }
    // update visitor record
    $query = "UPDATE visitors

             SET

                $sql_landing_page_name

                page_views = page_views + 1,

                stop_timestamp = UNIX_TIMESTAMP()

             WHERE id = '" . $visitor_id . "'";
    $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
}
// this function lowercases a keyword and folds the four forms of the letter i onto one
// another. turkish treats the dotless "ı" and the dotted "i" as two different letters,
// and unicode's default lowercasing of "İ" leaves a combining dot behind that then
// matches nothing, so "İstanbul" and "istanbul" would count as two separate keywords.
// english is unaffected, "I" and "i" already fold together there.
//
// this is the conservative fold, used when deciding that two keywords are the same one
// written twice. accents are left alone here on purpose: dropping one of them would
// throw away a term the operator typed, and in turkish "kar" and "kâr" really are two
// different words.
function fold_keyword_for_duplicates($keyword)
{
    $keyword = str_replace(
        array('I', 'İ', 'ı'),
        array('i', 'i', 'i'),
        (string) $keyword);
    return mb_strtolower(trim($keyword), 'UTF-8');
}
// this function folds a keyword for matching a visitor's search query against it. it goes
// further than the fold above and removes accents as well, because that is what the site
// search did before the comparison moved into php: both keyword columns are
// utf8mb4_unicode_ci, so the sql equality test this replaced already ignored accents and
// a visitor typing "bahce" reached a page tagged "Bahçe". comparing in php without this
// would quietly take that away.
function fold_keyword_for_matching($keyword)
{
    static $accented_letters = array(
        'á','à','â','ä','ã','å','ā','ă','ą',  'æ',
        'ç','ć','č',  'ď','đ',
        'é','è','ê','ë','ē','ĕ','ė','ę','ě',
        'ğ','ĝ','ġ','ģ',  'ĥ',
        'í','ì','î','ï','ĩ','ī','ĭ','į',  'ĵ',  'ķ',
        'ĺ','ľ','ł',  'ñ','ń','ň','ņ',
        'ó','ò','ô','ö','õ','ø','ō','ŏ','ő',  'œ',
        'ŕ','ř',  'ś','ŝ','ş','š',  'ß',
        'ţ','ť','ŧ',
        'ú','ù','û','ü','ũ','ū','ŭ','ů','ű','ų',
        'ŵ',  'ý','ÿ','ŷ',  'ź','ż','ž');
    static $plain_letters = array(
        'a','a','a','a','a','a','a','a','a',  'ae',
        'c','c','c',  'd','d',
        'e','e','e','e','e','e','e','e','e',
        'g','g','g','g',  'h',
        'i','i','i','i','i','i','i','i',  'j',  'k',
        'l','l','l',  'n','n','n','n',
        'o','o','o','o','o','o','o','o','o',  'oe',
        'r','r',  's','s','s','s',  'ss',
        't','t','t',
        'u','u','u','u','u','u','u','u','u','u',
        'w',  'y','y','y',  'z','z','z');
    return str_replace($accented_letters, $plain_letters, fold_keyword_for_duplicates($keyword));
}
// this function merges two comma separated keyword lists into one, keeping the order of
// the first list and dropping blank and repeated terms. it is used wherever a page arrives
// carrying the retired meta keywords field alongside its promote on keyword list.
//
// there is no length limit because there is no column limit: page_search_keywords and
// page_meta_keywords are both longtext.
function merge_keyword_lists($first_list, $second_list)
{
    $terms = array();
    $found_keywords = array();
    $candidates = array_merge(
        explode(',', (string) $first_list),
        explode(',', (string) $second_list));
    foreach ($candidates as $keyword) {
        $keyword = trim($keyword);
        if ($keyword == '') {
            continue;
        }
        $found_keyword = fold_keyword_for_duplicates($keyword);
        if (isset($found_keywords[$found_keyword])) {
            continue;
        }
        $found_keywords[$found_keyword] = true;
        $terms[] = $keyword;
    }
    return implode(', ', $terms);
}
// this function returns the ids of the pages that the site search should promote for a
// search query, which are the pages whose promote on keyword list contains that query as
// one of its terms.
//
// the comparison happens here instead of in the query for two reasons. the list is comma
// separated, so an equality test in sql only ever matched a page that had exactly one
// keyword in it and silently ignored every page with a real list. and the turkish forms
// of the letter i need folding that no sql collation gives us.
function get_page_ids_promoted_on_keyword($query)
{
    $page_ids = array();
    $query_keyword = fold_keyword_for_matching($query);
    if ($query_keyword == '') {
        return $page_ids;
    }
    $pages = db_items(
        "SELECT page_id, page_search_keywords
        FROM page
        WHERE (page_search = '1') AND (page_search_keywords != '')");
    // loop through the searchable pages and keep the ones that list this keyword
    foreach ($pages as $page) {
        foreach (explode(',', (string) $page['page_search_keywords']) as $keyword) {
            if (fold_keyword_for_matching($keyword) === $query_keyword) {
                $page_ids[] = (int) $page['page_id'];
                break;
            }
        }
    }
    return $page_ids;
}
// this function turns a list of page ids into a value that can be used with sql IN and
// NOT IN. every id is cast to an integer here, so the result is safe to embed whatever
// the caller passed in. a zero is returned for an empty list, because IN () is a syntax
// error and no page has an id of zero, so IN (0) matches nothing and NOT IN (0) matches
// everything.
function get_page_id_list_for_sql($page_ids)
{
    $integer_ids = array();
    foreach ($page_ids as $page_id) {
        $integer_ids[] = (int) $page_id;
    }
    if (count($integer_ids) == 0) {
        return '0';
    }
    return implode(', ', $integer_ids);
}
// this function will update the keywords in the tag cloud keywords table for a page.
// the keywords come from the promote on keyword field (page_search_keywords), which is
// the same list the site search promotes the page on.
function update_tag_cloud_keywords_for_page($page_id, $new_page_search, $new_search_keywords, $original_page_search, $original_search_keywords)
{
    // if the new include in site search in on, and if there is data in the new promote on keyword field, then update the tag cloud table
    if (($new_page_search == 1) && ($new_search_keywords != '')) {
        // break the keywords into an array
        $new_keywords = explode(',', $new_search_keywords);
        // loop through the keywords to tag to only add keywords that are not blank and remove any extra spaces before and after the keyword
        foreach ($new_keywords as $key => $new_keyword) {
            if ($new_keyword != '') {
                $new_keywords[$key] = trim($new_keyword);
            }
        }
        // remove duplicate entries from the array
        $new_keywords = array_unique($new_keywords);
        // if the original page search is on and if there are original keywords, then there is going to be records in the database,
        // so remove the original keywords from the new keywords array and remove the orignal keywords from the database if they need to be removed
        if (($original_page_search == 1) && ($original_search_keywords != '')) {
            // break the keywords into an array
            $original_keywords = explode(',', $original_search_keywords);
            // loop through the keywords to tag to only add keywords that are not blank and remove any extra spaces before and after the keyword
            foreach ($original_keywords as $key => $original_keyword) {
                if ($original_keyword != '') {
                    $original_keywords[$key] = trim($original_keyword);
                }
            }
            // remove duplicate entries from the array
            $original_keywords = array_unique($original_keywords);
            // loop through the old and new keywords arrays to remove any keywords that are in both, and to remove old keywords that are not in the new keywords array
            foreach ($original_keywords as $original_keyword) {
                $found_keyword = false;
                foreach ($new_keywords as $key => $new_keyword) {
                    // if the original keyword matches the new keyword, then remove it from the new keywords array and indicate that a keyword was found
                    if ($original_keyword == $new_keyword) {
                        unset($new_keywords[$key]);
                        $found_keyword = true;
                    }
                }
                // if a keyword was not found, then remove it from the database
                if ($found_keyword == false) {
                    $query = "DELETE FROM tag_cloud_keywords WHERE ((keyword = '" . escape($original_keyword) . "') AND (item_id = '" . escape($page_id) . "') AND (item_type = 'page'))";
                    $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
                }
            }
        }
        // loop through the new keywords and add them to the database
        foreach ($new_keywords as $key => $new_keyword) {
            // if the new keyword is not blank, then insert the keyword
            if ($new_keyword != '') {
                $query = "INSERT INTO tag_cloud_keywords 

                    (

                        keyword, 

                        item_id, 

                        item_type

                    ) VALUES (

                        '" . escape($new_keyword) . "',

                        '" . escape($page_id) . "',

                        'page'

                    )";
                $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
            }
        }
        // else if the original page search is on and the original keywords are not blank, then remove any keywords for this page from the database
    } elseif (($original_page_search == 1) && ($original_search_keywords != '')) {
        $query = "DELETE FROM tag_cloud_keywords WHERE item_id = '" . escape($page_id) . "' AND item_type = 'page'";
        $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
    }
}
// this function gets all product groups within the parent product group that was passed into this function
function get_product_groups_in_product_group_tree($parent_product_group_id = 0, $all_product_groups = array())
{
    $product_groups = array();
    // if this is the first time this function has run, then get all product groups and put them in an array,
    // and add the first parent product group to the array
    if (count($all_product_groups) == 0) {
        // get all product groups
        $query = "SELECT

                id,

                parent_id,

                display_type

            FROM product_groups

            ORDER BY sort_order, name";
        $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
        // add each product group to an array
        while ($row = mysqli_fetch_assoc($result)) {
            $all_product_groups[] = $row;
        }
        // loop through all product groups to add the parent product group to the array
        foreach ($all_product_groups as $product_group) {
            if ($product_group['id'] == $parent_product_group_id) {
                $product_groups[] = $product_group;
            }
        }
    }
    $child_product_groups = array();
    // loop through all product groups to get child product groups
    foreach ($all_product_groups as $product_group) {
        // if this product group's parent id is equal to the parent product group id then it's a child,
        // so add it to the product groups array, and then get it's children if it has any
        if ($product_group['parent_id'] == $parent_product_group_id) {
            $product_groups[] = $product_group;
            // if this product group is a browse product group, then get it's child product groups
            if ($product_group['display_type'] == 'browse') {
                $child_product_groups = get_product_groups_in_product_group_tree($product_group['id'], $all_product_groups);
            }
            // add the children to the product groups array
            $product_groups = array_merge($product_groups, $child_product_groups);
        }
    }
    return $product_groups;
}
// this function updates the tag cloud keywords that are in products and product groups whenever a search results page type is modified
function update_tag_cloud_keywords_for_search_results_page_type($page_id, $new_search_results_search_catalog_items, $new_search_results_product_group_id, $extra = '')
{
    // get search catalog items and product group id for this page
    $query = "SELECT search_catalog_items, product_group_id FROM search_results_pages WHERE page_id = '" . escape($page_id) . "'";
    $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
    $row = mysqli_fetch_assoc($result);
    $original_search_catalog_items = $row['search_catalog_items'];
    $original_product_group_id = $row['product_group_id'];
    if ($original_search_catalog_items == '') {
        $original_search_catalog_items = 0;
    }
    if ($new_search_results_product_group_id == '') {
        $new_search_results_product_group_id = 0;
    }
    // if "search products" has been enabled, or if "search products" is enabled and if the search results product group id is different than the current search results product group id value,
    // then update the tag cloud tables accordingly
    if ((($original_search_catalog_items == 0) && ($new_search_results_search_catalog_items == 1)) || (($new_search_results_search_catalog_items == 1) && ($new_search_results_product_group_id != $original_product_group_id))) {
        // if the search catalog items was already on, and if the selected product group has changed, then remove all of the xref records and keywords from the tag cloud tables for the items inside of the original product group
        if (($original_search_catalog_items == 1) && ($new_search_results_product_group_id != $original_product_group_id)) {
            delete_tag_cloud_keywords_for_search_results_page($page_id);
        }
        // call function that is responsible for updating the tag cloud keywords for the search results page's product group
        update_tag_cloud_keywords_for_search_results_page_product_group($page_id, $new_search_results_product_group_id);
        // else if "search products" is being turned off, or if the records need to be deleted,
        // then remove all of the xref records and keywords from the tag cloud tables for the items inside of the original product group
    } elseif (($original_search_catalog_items == 1) && ($new_search_results_search_catalog_items == 0)) {
        delete_tag_cloud_keywords_for_search_results_page($page_id);
    }
}
function delete_tag_cloud_keywords_for_search_results_page($page_id)
{
    $all_items = array();
    // get items from xref table
    $query = "SELECT 

            search_results_page_id,

            item_id,

            item_type

        FROM tag_cloud_keywords_xref";
    $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
    while ($row = mysqli_fetch_assoc($result)) {
        $all_items[] = $row;
    }
    $items_to_delete = array();
    // loop through items to find the ones we want to delete
    foreach ($all_items as $key => $item) {
        // if the item's page id is equal to this page's id, then add it to the item to delete array and remove it from the items array
        if ($item['search_results_page_id'] == $page_id) {
            $items_to_delete[] = $item;
            unset($all_items[$key]);
        }
    }
    // loop through the items to remove their xref records and keywords
    foreach ($items_to_delete as $item_to_delete) {
        $delete_keywords = true;
        // loop through all the items to see if this item has an xref record for a different search result page
        foreach ($all_items as $item) {
            // if the item to delete's id and type is equal to the id and type of the current item, then this item is being used for a different search results page,
            // so indicate that it's keywords shouldn't be deleted
            if (($item_to_delete['item_id'] == $item['item_id']) && ($item_to_delete['item_type'] == $item['item_type'])) {
                $delete_keywords = false;
            }
        }
        // if the keywords can be deleted, then delete them
        if ($delete_keywords == true) {
            $query = "DELETE FROM tag_cloud_keywords WHERE (item_id = '" . escape($item_to_delete['item_id']) . "') AND (item_type = '" . escape($item_to_delete['item_type']) . "')";
            $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
        }
        // delete the xref record for this item
        $query = "DELETE FROM tag_cloud_keywords_xref WHERE ((search_results_page_id = '" . escape($page_id) . "') AND (item_id = '" . escape($item_to_delete['item_id']) . "') AND (item_type = '" . escape($item_to_delete['item_type']) . "'))";
        $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
    }
}
function update_tag_cloud_keywords_for_search_results_page_product_group($page_id, $new_search_results_product_group_id)
{
    $product_groups = array();
    // get all product groups within the scope of the product group id
    $product_groups = get_product_groups_in_product_group_tree($new_search_results_product_group_id);
    $where = '';
    // loop through product groups to build sql where statment
    foreach ($product_groups as $product_group) {
        // if where isn't blank, then add or
        if ($where != '') {
            $where .= ' OR ';
        }
        $where .= "(id = '" . escape($product_group['id']) . "')";
    }
    if ($where != '') {
        $where = '(' . $where . ') AND ';
    }
    $items = array();
    // get product group data from the database
    $query = "SELECT 

            product_groups.id,

            product_groups.name,

            product_groups.keywords,

            product_groups.display_type

        FROM product_groups

        WHERE $where(display_type = 'select')";
    $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
    while ($row = mysqli_fetch_assoc($result)) {
        $items[] = array(
            'type' => 'product_group',
            'display_type' => 'display_type',
            'id' => $row['id'],
            'name' => $row['name'],
            'keywords' => $row['keywords']
        );
    }
    $where = '';
    // loop through product groups to build sql where statment
    foreach ($product_groups as $product_group) {
        // if the product group's display type is set to browse, then add this product group id to the where statement
        if ($product_group['display_type'] == 'browse') {
            // if where isn't blank, then add or
            if ($where != '') {
                $where .= ' OR ';
            }
            $where .= "(products_groups_xref.product_group = '" . escape($product_group['id']) . "')";
        }
    }
    if ($where != '') {
        $where = 'WHERE (' . $where . ')';
    }
    // get product data from the database
    $query = "SELECT 

            products.id,

            products.name,

            products.keywords 

        FROM products_groups_xref

        LEFT JOIN products ON products.id = products_groups_xref.product

        $where";
    $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
    while ($row = mysqli_fetch_assoc($result)) {
        $items[] = array(
            'type' => 'product',
            'id' => $row['id'],
            'name' => $row['name'],
            'keywords' => $row['keywords']
        );
    }
    $tag_cloud_keywords = array();
    // get data from tag cloud keywords table
    $query = "SELECT 

            keyword,

            item_id,

            item_type

        FROM tag_cloud_keywords

        WHERE (item_type = 'product') OR (item_type = 'product_group')";
    $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
    while ($row = mysqli_fetch_assoc($result)) {
        $tag_cloud_keywords[] = $row;
    }
    $inserted_items = array();
    $inserted_keywords = array();
    // loop through each item to add it's keywords to the tag cloud
    foreach ($items as $item) {
        $item_keywords = explode(',', $item['keywords']);
        // trim the keywords and remove any blank entries
        foreach ($item_keywords as $key => $item_keyword) {
            // if the keyword is not blank, then trim it
            if ($item_keyword != '') {
                $item_keywords[$key] = trim($item_keyword);
                // otherwise remove it from the array
            } else {
                unset($item_keywords[$key]);
            }
        }
        // remove duplicates from the array
        $item_keywords = array_unique($item_keywords);
        // loop through the keywords and add them to the tag cloud if able
        foreach ($item_keywords as $item_keyword) {
            $record_exits = false;
            // loop through tag cloud keywords to see if there is already a record for this keyword
            foreach ($tag_cloud_keywords as $tag_cloud_keyword) {
                // if the item's keyword, id, and type matches the tag cloud keywords then a record for this keyword already exists
                if (($item_keyword == $tag_cloud_keyword['keyword']) && ($item['id'] == $tag_cloud_keyword['item_id']) && ($item['type'] == $tag_cloud_keyword['item_type'])) {
                    $record_exits = true;
                }
            }
            // if a record doesn't exist in the database, then loop through the inserted keywords to see if this keyword has been added to the database
            if ($record_exits == false) {
                // loop through the inserted keywords to see if there is already a record for this keyword
                foreach ($inserted_keywords as $inserted_keyword) {
                    // if the item's keyword, id, and type matches the inseted keywords then a record for this keyword already exists
                    if (($item_keyword == $inserted_keyword['keyword']) && ($item['id'] == $inserted_keyword['item_id']) && ($item['type'] == $inserted_keyword['item_type'])) {
                        $record_exits = true;
                    }
                }
            }
            // if there is not a record for this item, then add one
            if ($record_exits == false) {
                // if the new keyword is not blank, then insert the keyword
                if ($item_keyword != '') {
                    $query = "INSERT INTO tag_cloud_keywords 

                        (

                            keyword, 

                            item_id, 

                            item_type

                        ) VALUES (

                            '" . escape($item_keyword) . "',

                            '" . escape($item['id']) . "',

                            '" . escape($item['type']) . "'

                        )";
                    $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
                    // add keyword to the inserted keywords array so that it wont be added again
                    $inserted_keywords[] = array(
                        'keyword' => $item_keyword,
                        'item_id' => $item['id'],
                        'item_type' => $item['type']
                    );
                }
            }
        }
        $record_exits = false;
        // loop through inserted items to see if there is already a record for this item
        foreach ($inserted_items as $inserted_item) {
            // if the item's id and type matches the inserted items then a xref for this item already exists
            if (($item['id'] == $inserted_item['id']) && ($item['type'] == $inserted_item['type'])) {
                $record_exits = true;
            }
        }
        // if there is not a record for this item, then add one
        if ($record_exits == false) {
            $query = "INSERT INTO tag_cloud_keywords_xref 

                (

                    search_results_page_id, 

                    item_id, 

                    item_type

                ) VALUES (

                    '" . escape($page_id) . "',

                    '" . escape($item['id']) . "',

                    '" . escape($item['type']) . "'

                )";
            $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
            // add item to array so that it is not inserted again
            $inserted_items[] = $item;
        }
    }
}

/**
 * Checks if the sitemap content has changed and securely pings IndexNow if necessary.
 * This unifies the sitemap check logic safely on the backend.
 *
 * @return bool True if sitemap hashed content changed and ping fired/attempted, false otherwise.
 */
function update_sitemap_and_ping()
{
    // Record that a check is being performed now. (We do it securely at the start to block recursion on failures).
    $query = "UPDATE config SET last_sitemap_check_timestamp = UNIX_TIMESTAMP()";
    mysqli_query(db::$con, $query);

    require_once(PG_FUNCTIONS_DIR . '/get_sitemap_info.php');
    $sitemap_info = get_sitemap_info();

    if ($sitemap_info['urls_exist'] === true) {

        $sitemap_check_hash = md5($sitemap_info['content']);

        if ($sitemap_check_hash != LAST_SITEMAP_CHECK_HASH) {

            $sitemap_url = URL_SCHEME . HOSTNAME_SETTING . PATH . 'sitemap.xml';

            if (defined('INDEXNOW_KEY') && !empty(INDEXNOW_KEY)) {
                $indexnow_url = 'https://api.indexnow.org/indexnow';
                $post_data = json_encode([
                    "host" => HOSTNAME_SETTING,
                    "key" => INDEXNOW_KEY,
                    "urlList" => [$sitemap_url]
                ]);

                $ch = curl_init($indexnow_url);
                // Identify this installation on outgoing requests. Sent with no
                // User-Agent, a request looks like an anonymous client to the receiving
                // server's firewall and gets rejected — which is how Pinegrap ended up
                // blocking its own licence and update checks.
                curl_setopt($ch, CURLOPT_USERAGENT, function_exists('pinegrap_user_agent') ? pinegrap_user_agent() : 'Pinegrap');
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 8);
                curl_setopt($ch, CURLOPT_TIMEOUT, 10);

                $response = curl_exec($ch);

                if ($response === false) {
                    log_activity(lang('IndexNow ping failed: ') . curl_error($ch), '');
                } else {
                    log_activity(lang('IndexNow ping sent successfully.'), '');
                }

                curl_close($ch);
            } else {
                log_activity(lang('IndexNow skipped: INDEXNOW_KEY not defined or empty.'), '');
            }

            log_activity(lang('daily sitemap.xml check detected updated sitemap.xml'), '');

            $query = "UPDATE config SET last_sitemap_check_hash = '" . escape($sitemap_check_hash) . "'";
            mysqli_query(db::$con, $query);

            return true;
        }
    }

    return false;
}

function perf_monitor_init()
{
    if (defined('PERF_MONITOR') && PERF_MONITOR === false) {
        return;
    }

    // Skip CLI / cron contexts: no useful URL, and they distort percentile views.
    if (PHP_SAPI === 'cli') {
        return;
    }

    $sample_rate = defined('PERF_MONITOR_SAMPLE_RATE') ? (int) PERF_MONITOR_SAMPLE_RATE : 100;
    if ($sample_rate < 1) {
        return;
    }
    if ($sample_rate < 100 && mt_rand(1, 100) > $sample_rate) {
        return;
    }

    // Prefer REQUEST_TIME_FLOAT — PHP populates it before our code runs, so it captures
    // the true entry timestamp rather than "now, after init has already partially executed".
    // The value has to be positive to be usable. Some SAPIs expose the key
    // with a zero or empty value rather than omitting it, and isset() happily
    // returns true for that — the duration then comes out as the current unix
    // time in milliseconds, roughly 1.7 trillion, which MySQL silently clamps
    // to the column ceiling and which then poisons the hourly average for
    // good, because a summary row keeps what it was given.
    $start_time = (isset($_SERVER['REQUEST_TIME_FLOAT']) && (float) $_SERVER['REQUEST_TIME_FLOAT'] > 0)
        ? (float) $_SERVER['REQUEST_TIME_FLOAT']
        : microtime(true);

    $start_rusage = function_exists('getrusage') ? @getrusage() : null;

    $GLOBALS['perf_monitor'] = array(
        'start_time'   => $start_time,
        'start_rusage' => $start_rusage,
    );

    register_shutdown_function('perf_monitor_shutdown');
}

function perf_monitor_shutdown()
{
    if (empty($GLOBALS['perf_monitor'])) {
        return;
    }

    // Flush the response before doing DB work, so logging never sits on the
    // user-visible path.
    //
    // fastcgi_finish_request() is PHP-FPM only. Under mod_php, CGI or IIS it
    // does not exist, and everything below runs BEFORE the response is
    // finished — the monitor then costs the visitor real milliseconds. Track
    // which case we are in so the expensive housekeeping can be skipped when
    // the visitor is still waiting.
    $flushed = false;

    if (function_exists('fastcgi_finish_request')) {
        $flushed = (bool) @fastcgi_finish_request();
    }

    if (!isset(db::$con) || !db::$con) {
        return;
    }

    // Site Settings switch. Checked here rather than in perf_monitor_init(),
    // which runs before the config row is read. Everything expensive is below
    // this line, so turning it off leaves only a few microseconds of timers.
    if (defined('PERF_MONITOR_ENABLED') && !PERF_MONITOR_ENABLED) {
        return;
    }

    $state = $GLOBALS['perf_monitor'];
    $now = microtime(true);
    $duration_ms = (int) round(($now - $state['start_time']) * 1000);

    // Sanity gate.
    //
    // A summary table cannot forget. One nonsensical duration is added into
    // total_ms and pushed into max_ms, and every average computed from that
    // bucket is wrong until the bucket ages out — which is exactly what
    // happened on a live install: a single bad measurement produced an
    // average of 595,400,352,033 ms and a peak of 4,294,967,295 ms, the
    // unsigned 32-bit ceiling.
    //
    // MySQL will not object. Strict mode is deliberately off in this codebase,
    // so an out-of-range value is clamped and a negative one is reinterpreted
    // as huge, both without an error. The check therefore has to happen here.
    //
    // An hour is far beyond any real request: PHP's own max_execution_time is
    // measured in seconds. Anything past it is a broken clock or a broken
    // start time, and the right answer is to record nothing rather than
    // something false.
    if ($duration_ms < 0 || $duration_ms > 3600000) {
        return;
    }

    $script_name = isset($_SERVER['SCRIPT_NAME']) ? basename($_SERVER['SCRIPT_NAME']) : '';

    // Scripts the operator does not want measured. api.php is excluded by
    // default: it is the internal AJAX endpoint, so every dashboard widget and
    // every autocomplete keystroke is one of its requests. Grouped under a
    // single name it sat at the top of every report while telling nobody
    // anything about which page is slow.
    $ignored = defined('PERF_MONITOR_IGNORE_SCRIPTS')
        ? PERF_MONITOR_IGNORE_SCRIPTS
        : 'api.php';

    if ($ignored !== '' && $script_name !== '') {
        foreach (preg_split('/[\s,;]+/', $ignored) as $ignored_script) {
            if ($ignored_script !== '' && strcasecmp($ignored_script, $script_name) === 0) {
                return;
            }
        }
    }

    // Same reasoning, cheaper to state: clamp rather than discard, because a
    // memory reading being odd is no reason to lose a good duration.
    $peak_memory_kb = (int) round(memory_get_peak_usage(true) / 1024);

    if ($peak_memory_kb < 0 || $peak_memory_kb > 16777216) {
        $peak_memory_kb = 0;
    }

    $cpu_user_ms = 0;
    $cpu_system_ms = 0;
    if (is_array($state['start_rusage']) && function_exists('getrusage')) {
        $end_rusage = @getrusage();
        if (is_array($end_rusage)) {
            $u = (($end_rusage['ru_utime.tv_sec'] - $state['start_rusage']['ru_utime.tv_sec']) * 1000)
               + (($end_rusage['ru_utime.tv_usec'] - $state['start_rusage']['ru_utime.tv_usec']) / 1000);
            $s = (($end_rusage['ru_stime.tv_sec'] - $state['start_rusage']['ru_stime.tv_sec']) * 1000)
               + (($end_rusage['ru_stime.tv_usec'] - $state['start_rusage']['ru_stime.tv_usec']) / 1000);
            if ($u >= 0 && $u <= 3600000) { $cpu_user_ms = (int) round($u); }
            if ($s >= 0 && $s <= 3600000) { $cpu_system_ms = (int) round($s); }
        }
    }

    // Backend vs frontend: router.php sets PHP_SETTINGS_UPDATED before including init.php,
    // so its presence means the request was routed (i.e. a public-facing page or asset).
    $area = defined('PHP_SETTINGS_UPDATED') ? 'frontend' : 'backend';

    $request_url = '';
    if (defined('REQUEST_URL')) {
        $request_url = REQUEST_URL;
    } elseif (isset($_SERVER['REQUEST_URI'])) {
        $request_url = $_SERVER['REQUEST_URI'];
    }

    $query_string = '';
    $qpos = strpos($request_url, '?');
    if ($qpos !== false) {
        $query_string = substr($request_url, $qpos + 1, 500);
        $request_url = substr($request_url, 0, $qpos);
    }
    if (strlen($request_url) > 510) {
        $request_url = substr($request_url, 0, 510);
    }

    $http_status = function_exists('http_response_code') ? (int) http_response_code() : 200;

    // Grouping label: the URL identifies a front-end page, the script name a
    // back-end screen.
    $label = ($area === 'frontend')
        ? ($request_url === '' || $request_url === '/' ? '[home]' : $request_url)
        : $script_name;

    // Anything that failed is grouped under its status code instead of its URL.
    //
    // Two reasons, and the second is the important one.
    //
    // A missing file is not a page. The rewrite rule sends any request whose
    // file does not exist to the router, so a stale asset reference or a
    // scanner probing /install/api.php runs the whole PHP stack and lands in
    // the report next to real pages, where it means nothing.
    //
    // More seriously, those URLs are chosen by whoever is making the request.
    // perf_stats keeps one row per label per hour, so a scanner walking a
    // hundred thousand paths would create a hundred thousand buckets an hour —
    // letting a stranger decide how large this table gets, which is precisely
    // what the summary design exists to prevent. Folding them into one label
    // keeps the signal (how many failures, and how slow they are to produce)
    // and caps the cost at a single row.
    //
    // The individual slow rows below still record the real URL, so a 404 that
    // is somehow taking seconds can still be traced.
    if ($http_status >= 400) {
        $label = '[' . $http_status . ']';
    }

    if (strlen($label) > 250) {
        $label = substr($label, 0, 250);
    }

    $slow_ms = defined('PERF_MONITOR_SLOW_MS') ? (int) PERF_MONITOR_SLOW_MS : 1000;
    $is_slow = ($slow_ms > 0 && $duration_ms >= $slow_ms) ? 1 : 0;

    // Read before anything below sends a statement of its own, so the count
    // is the request's and not the monitor's.
    $query_count = pg_perf_request_queries();

    // ── Every request: fold into an hourly bucket ────────────────────────
    //
    // One statement, and the table stops growing with traffic — the same
    // request an hour from now updates a different row, the same request a
    // minute from now updates this one. This is what replaces a million rows
    // of ordinary traffic that nobody was ever going to read individually.
    $hour_start = $now - ((int) $now % 3600);

    // Which record this measurement belongs to, resolved while the process
    // that rendered it is still alive. The SEO score joins on this; without
    // it the report would have to turn a URL back into a record, and
    // '/katalog/urun-adi' turns into the catalog detail template rather than
    // into the product, which would file every product under one page.
    //
    // Only for front-end requests that succeeded. A back-end screen is not a
    // scored record, and a failure has already been folded into a '[404]'
    // label that no longer identifies anything.
    $entity_type = '';
    $entity_id = 0;

    // Probed once, and only where the answer can change anything: a back-end
    // screen is not a scored record and a failure has already been folded into
    // a '[404]' label that identifies nothing. Under mod_php, CGI and IIS
    // there is no fastcgi_finish_request(), so everything here still runs while
    // the visitor waits - a metadata round trip on every request would be new
    // cost on the public path for no answer.
    $has_entity_columns = false;

    if (($area === 'frontend') && ($http_status < 400)) {

        $has_entity_columns = pg_perf_stats_has_entity();
        $entity = pg_measured_entity();
        $entity_type = $entity['type'];
        $entity_id = (int) $entity['id'];
    }

    // The record is part of the bucket key, not just a column on it.
    //
    // One label does not always mean one record. '[home]' is shared by every
    // page carrying page_home, and get_page.php picks between them at random
    // by design. A catalogue reached as '/urun-detay?pid=5' has its query
    // string stripped, so every product behind it shares one label. Storing
    // the identity as a plain column would hand the whole bucket to whichever
    // record happened to be rendered first that hour and quietly credit one
    // page with another page's timings.
    //
    // This does not widen the cost problem the summary design exists to solve.
    // A label already carries whatever cardinality it had: a '[404]' never
    // carries a record at all, and the page types that accept a trailing path
    // segment (system layouts, catalog and catalog detail) were already
    // producing one label per requested path before this change, all of them
    // resolving to the same record. What the record adds is one bucket per
    // record behind a shared label, which is bounded by the site's own content
    // - the same bound visitor_content_hourly already accepts.
    // PERF_MONITOR_MAX_ROWS remains the backstop for the paths that were
    // unbounded to begin with.
    // Only split the bucket when the identity can actually be written. Before
    // the upgrade has run there is nowhere to record which record a bucket
    // belongs to, so splitting would produce indistinguishable rows and spend
    // the row cap several times faster for no benefit at all.
    $bucket_key = $has_entity_columns
        ? sha1($area . '|' . $label . '|' . $entity_type . '|' . $entity_id)
        : sha1($area . '|' . $label);

    $sql_entity_columns = '';
    $sql_entity_values = '';

    if ($has_entity_columns) {
        $sql_entity_columns = ', entity_type, entity_id';
        $sql_entity_values = ", '" . escape($entity_type) . "', " . (int) $entity_id;
    }

    // The query counts, once the upgrade has added their columns; until then
    // the INSERT stays as it was.
    $sql_query_columns = '';
    $sql_query_values = '';
    $sql_query_update = '';

    if (pg_perf_stats_has_queries()) {
        $sql_query_columns = ', total_queries, max_queries';
        $sql_query_values = ', ' . (int) $query_count . ', ' . (int) $query_count;
        $sql_query_update = ',
            total_queries = total_queries + ' . (int) $query_count . ',
            max_queries  = GREATEST(max_queries, ' . (int) $query_count . ')';
    }

    @mysqli_query(
        db::$con,
        "INSERT INTO perf_stats
            (bucket_key, hour_start, label, area, hits, slow_hits,
             total_ms, min_ms, max_ms, total_kb, max_kb, total_cpu_ms" . $sql_entity_columns . $sql_query_columns . ")
         VALUES
            ('" . escape($bucket_key) . "', " . (int) $hour_start . ",
             '" . escape($label) . "', '" . escape($area) . "', 1, " . $is_slow . ",
             " . $duration_ms . ", " . $duration_ms . ", " . $duration_ms . ",
             " . $peak_memory_kb . ", " . $peak_memory_kb . ",
             " . ($cpu_user_ms + $cpu_system_ms) . $sql_entity_values . $sql_query_values . ")
         ON DUPLICATE KEY UPDATE
            hits         = hits + 1,
            slow_hits    = slow_hits + " . $is_slow . ",
            total_ms     = total_ms + " . $duration_ms . ",
            min_ms       = LEAST(min_ms, " . $duration_ms . "),
            max_ms       = GREATEST(max_ms, " . $duration_ms . "),
            total_kb     = total_kb + " . $peak_memory_kb . ",
            max_kb       = GREATEST(max_kb, " . $peak_memory_kb . "),
            total_cpu_ms = total_cpu_ms + " . ($cpu_user_ms + $cpu_system_ms) . $sql_query_update
    );

    // ── Slow requests only: keep the full detail ─────────────────────────
    //
    // A summary tells you a page is slow; it cannot tell you which request
    // took 101 seconds or who made it. These rows are rare by construction —
    // on a site averaging 48 ms, almost nothing crosses the threshold — so
    // they cost little and carry the parameters, address and client that make
    // the outlier investigable.
    if ($is_slow) {
        $method = isset($_SERVER['REQUEST_METHOD']) ? substr($_SERVER['REQUEST_METHOD'], 0, 8) : 'GET';
        $is_ajax = (isset($_SERVER['HTTP_X_REQUESTED_WITH'])
            && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') ? 1 : 0;
        $user_id = (defined('USER_ID') && USER_ID !== '') ? (int) USER_ID : 0;

        $ip_address = function_exists('waf_client_ip')
            ? waf_client_ip()
            : (isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '');

        $user_agent = isset($_SERVER['HTTP_USER_AGENT'])
            ? substr($_SERVER['HTTP_USER_AGENT'], 0, 250)
            : '';

        $has_query_count = pg_schema_has('perf_log', 'query_count');

        @mysqli_query(
            db::$con,
            "INSERT INTO perf_log
                (request_url, query_string, script_name, area, method, http_status,
                 duration_ms, peak_memory_kb, cpu_user_ms, cpu_system_ms,
                 ip_address, user_agent, user_id, is_ajax, log_timestamp" . ($has_query_count ? ", query_count" : "") . ")
             VALUES (
                '" . escape($request_url) . "',
                '" . escape($query_string) . "',
                '" . escape($script_name) . "',
                '" . escape($area) . "',
                '" . escape($method) . "',
                " . (int) $http_status . ",
                " . (int) $duration_ms . ",
                " . (int) $peak_memory_kb . ",
                " . (int) $cpu_user_ms . ",
                " . (int) $cpu_system_ms . ",
                '" . escape($ip_address) . "',
                '" . escape($user_agent) . "',
                " . (int) $user_id . ",
                " . (int) $is_ajax . ",
                UNIX_TIMESTAMP()" . ($has_query_count ? ",
                " . (int) $query_count : "") . "
            )"
        );
    }

    // ── Housekeeping ─────────────────────────────────────────────────────
    //
    // Only when the response has already been flushed. A DELETE is the most
    // expensive thing this function can do, and on a server where we could not
    // flush first it would land squarely on the visitor's wait.
    if (!$flushed) {
        return;
    }

    if (mt_rand(1, 200) !== 1) {
        return;
    }

    $retention = defined('PERF_MONITOR_RETENTION_DAYS') ? (int) PERF_MONITOR_RETENTION_DAYS : 30;

    if ($retention > 0) {
        $cutoff = time() - ($retention * 86400);
        @mysqli_query(db::$con, "DELETE FROM perf_stats WHERE hour_start < " . (int) $cutoff . " LIMIT 2000");
        @mysqli_query(db::$con, "DELETE FROM perf_log WHERE log_timestamp < " . (int) $cutoff . " LIMIT 1000");
    }

    // Row cap as a backstop. Both tables are bounded by design now, but a site
    // with very high URL cardinality can still accumulate buckets faster than
    // retention removes them.
    $max_rows = defined('PERF_MONITOR_MAX_ROWS') ? (int) PERF_MONITOR_MAX_ROWS : 200000;

    if ($max_rows > 0) {
        $oldest = @mysqli_query(db::$con,
            "SELECT hour_start FROM perf_stats ORDER BY hour_start DESC LIMIT 1 OFFSET " . (int) $max_rows);

        if ($oldest && @mysqli_num_rows($oldest)) {
            $row = @mysqli_fetch_assoc($oldest);
            @mysqli_query(db::$con,
                "DELETE FROM perf_stats WHERE hour_start <= " . (int) $row['hour_start'] . " LIMIT 5000");
        }
    }
}

/**
 * Plain text for a <title> or a meta description made from a catalog field:
 * tags and entities gone, white space folded, and when $max is given cut at
 * a word before $max characters with an ellipsis.
 *
 * @param string $html
 * @param int    $max  0 for no limit
 * @return string
 */
function pg_sw_item_text($html, $max = 0)
{
    $text = preg_replace('~<(script|style)\b[^>]*>.*?</\1\s*>~is', ' ', (string) $html);
    $text = html_entity_decode(strip_tags(preg_replace('~<\s*(br|/p|/div|/li|/h[1-6])\b[^>]*>~i', ' $0', (string) $text)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = trim(preg_replace('~[\s\x{00A0}]+~u', ' ', (string) $text));

    $max = (int) $max;

    if (($max > 1) && (mb_strlen($text, 'UTF-8') > $max)) {
        $cut = mb_substr($text, 0, $max - 1, 'UTF-8');

        // Back to the last whole word, unless the cut already ends one or
        // that would throw away more than half of the text.
        if (mb_substr($text, $max - 1, 1, 'UTF-8') !== ' ') {
            $space = mb_strrpos($cut, ' ', 0, 'UTF-8');

            if (($space !== false) && ($space >= ($max * 0.5))) {
                $cut = mb_substr($cut, 0, $space, 'UTF-8');
            }
        }

        $text = rtrim($cut, " ,.;:-") . "\u{2026}";
    }

    return $text;
}

/**
 * The item a page built in the visual editor shows through a system widget
 * when its address names one, so that the page is that item's page: a
 * product or a select-type product group on a Catalog Detail widget
 * (/<page>/<address>, or ?pid= for a product), a product group a Catalog
 * widget drills down to (/<page>/<address>, or ?cat=<address> on the home
 * page), a record on a Form Item View widget (?r=, or /<page>/<name> when
 * its form has pretty URLs on, pg_sw_route_record_address()). The item is
 * resolved the way the widgets resolve it. null when the page shows no item of its own:
 * the bare page, an address that names nothing, the widget's own root group,
 * a record the visitor may not see.
 *
 * @param int    $page_id
 * @param string $page_name
 * @param bool   $home      the page is the site's home page
 * @return array|null type (product | product_group | record), path (from
 *                    PATH, encoded), query ('' or '?…', encoded), title,
 *                    description (plain text, '' when the item has none),
 *                    image_name
 */
function pg_sw_page_item($page_id, $page_name, $home = false)
{
    static $cache = array();

    $page_id = (int) $page_id;

    if (($page_id <= 0) || !function_exists('_pg_find_system_widget_on_page')) {
        return null;
    }

    // Keyed by the request's item address as well: the search index draws
    // one record after another on the same page in one process.
    $cache_key = $page_id;

    foreach (array('page', 'r', 'cat', 'pid') as $cache_name) {
        $cache_key .= '|' . ((isset($_GET[$cache_name]) && is_scalar($_GET[$cache_name])) ? (string) $_GET[$cache_name] : '');
    }

    if (array_key_exists($cache_key, $cache)) {
        return $cache[$cache_key];
    }

    if (count($cache) >= 256) {
        $cache = array();
    }

    $cache[$cache_key] = null;

    // The segment after the page name, decoded the way the widgets decode it.
    $full = isset($_GET['page']) ? (string) $_GET['page'] : '';
    $slash = mb_strpos($full, '/', 0, 'UTF-8');
    $segment = '';

    if ($slash !== false) {
        $segment = mb_substr($full, $slash + 1, null, 'UTF-8');
        $next = mb_strpos($segment, '/', 0, 'UTF-8');

        if ($next !== false) {
            $segment = mb_substr($segment, 0, $next, 'UTF-8');
        }

        $segment = urldecode(trim($segment));
    }

    if (strlen($segment) > 200) {
        $segment = '';
    }

    $base = PATH . encode_url_path($page_name);
    $group_columns = "id, address_name, short_description, full_description, name, title, meta_description, image_name";

    $group_item = function ($group, $path, $query) {
        $title = trim((string) $group['title']);

        if ($title === '') {
            $title = pg_sw_item_text($group['short_description']);
        }

        if ($title === '') {
            $title = trim((string) $group['name']);
        }

        $description = trim((string) $group['meta_description']);

        if ($description === '') {
            $description = pg_sw_item_text($group['full_description'], 160);
        }

        return array(
            'type'        => 'product_group',
            'path'        => $path,
            'query'       => $query,
            'title'       => $title,
            'description' => $description,
            'image_name'  => (string) $group['image_name'],
        );
    };

    // The listing widget takes the address before a detail widget on the
    // same page, as the page's feed and structured data do.
    $listing = _pg_find_system_widget_on_page($page_id, 'catalog_listing');

    if ($listing) {
        // The home page is asked for a group with ?cat= at the site root,
        // like the widget; under its own name it takes the segment too.
        $address = (($segment === '') && $home && isset($_GET['cat']) && is_scalar($_GET['cat'])) ? trim((string) $_GET['cat']) : $segment;

        if (($address === '') || (strlen($address) > 200)) {
            return null;
        }

        $group = db_item("SELECT $group_columns FROM product_groups WHERE address_name = '" . e($address) . "' AND enabled = 1 LIMIT 1");

        // The widget's own group is what the bare page already shows.
        if (!is_array($group) || ((int) $group['id'] === (int) $listing['product_group_id'])) {
            return null;
        }

        return $cache[$cache_key] = $home
            ? $group_item($group, PATH, '?cat=' . rawurlencode((string) $group['address_name']))
            : $group_item($group, $base . '/' . encode_url_path((string) $group['address_name']), '');
    }

    $detail = $home ? null : _pg_find_system_widget_on_page($page_id, 'catalog_item_view');

    if ($detail) {
        $by_pid = ($segment === '') && isset($_GET['pid']) && ((int) $_GET['pid'] > 0);

        if (($segment === '') && !$by_pid) {
            return null;
        }

        $product = _pg_catalog_item_resolve_product($detail['product_group_id']);

        if (!is_array($product)) {
            return null;
        }

        // An address that is not the product's own is a select-type group:
        // the widget shows that group's first product for it.
        if (($segment !== '') && (mb_strtolower((string) $product['address_name'], 'UTF-8') !== mb_strtolower($segment, 'UTF-8'))) {
            $group = db_item("SELECT $group_columns FROM product_groups
                              WHERE address_name = '" . e($segment) . "' AND enabled = 1 AND display_type = 'select' LIMIT 1");

            return $cache[$cache_key] = is_array($group)
                ? $group_item($group, $base . '/' . encode_url_path((string) $group['address_name']), '')
                : null;
        }

        $title = trim((string) $product['title']);

        if ($title === '') {
            $title = pg_sw_item_text($product['short_description']);
        }

        $description = trim((string) $product['meta_description']);

        if ($description === '') {
            $description = pg_sw_item_text($product['full_description'], 160);
        }

        // A product without an address is reached by its id alone.
        $address = (string) $product['address_name'];

        return $cache[$cache_key] = array(
            'type'        => 'product',
            'path'        => ($address !== '') ? $base . '/' . encode_url_path($address) : $base,
            'query'       => ($address !== '') ? '' : '?pid=' . (int) $product['id'],
            'title'       => $title,
            'description' => $description,
            'image_name'  => (string) $product['image_name'],
        );
    }

    if (!$home && function_exists('pg_sw_record_comment_context') && isset($_GET['r']) && is_scalar($_GET['r']) && (trim((string) $_GET['r']) !== '')) {
        $context = pg_sw_record_comment_context($page_id, 0);

        if (is_array($context) && !empty($context['visible']) && is_array($context['form'])) {
            // A record of a form with pretty URLs on is canonical under its
            // name (/<page>/<name>), however it was asked for.
            $address = (function_exists('pg_sw_form_pretty_urls') && pg_sw_form_pretty_urls($context['custom_form_page_id']))
                ? trim((string) $context['form']['address_name']) : '';

            return $cache[$cache_key] = array(
                'type'        => 'record',
                'path'        => ($address !== '') ? $base . '/' . encode_url_path($address) : $base,
                'query'       => ($address !== '') ? '' : '?r=' . rawurlencode((string) $context['form']['reference_code']),
                'title'       => '',
                'description' => '',
                'image_name'  => '',
            );
        }
    }

    return null;
}

/**
 * sitemap.xml with the stylesheet a browser draws it with: an xml-stylesheet
 * instruction right after the XML declaration. A browser shows the XML tree
 * of a sitemap only while no element in it is an XHTML one; the hreflang
 * alternates (xhtml:link) turn the tree into run-together text. CSS rather
 * than XSL, because browsers are taking XSLT out (Chrome stops running it in
 * version 158) while CSS on XML stays. Search engines skip the instruction.
 *
 * @param string $xml  the sitemap
 * @param string $href the stylesheet's address, unescaped
 * @return string
 */
function pg_sitemap_add_stylesheet($xml, $href)
{
    $xml = (string) $xml;

    if (strpos($xml, '<?xml-stylesheet') !== false) {
        return $xml;
    }

    $instruction = '<?xml-stylesheet type="text/css" href="' . htmlspecialchars((string) $href, ENT_QUOTES | ENT_XML1, 'UTF-8') . '"?>';

    // Nothing may come before the declaration, so the instruction follows it.
    if (preg_match('/^<\?xml\s[^?]*\?>(\r?\n)?/', $xml, $match)) {
        return rtrim($match[0], "\r\n") . "\n" . $instruction . "\n" . substr($xml, strlen($match[0]));
    }

    return $instruction . "\n" . $xml;
}

/**
 * A text as a CSS string literal, quotes included.
 *
 * @param string $text
 * @return string
 */
function pg_sitemap_css_string($text)
{
    return '"' . str_replace(array('\\', '"', "\r", "\n"), array('\\\\', '\\"', '', '\\A '), (string) $text) . '"';
}

/**
 * The stylesheet pg_sitemap_add_stylesheet() names: the addresses as a
 * numbered list, each with its date, priority and language alternates, in
 * light and dark. CSS can only add words through content, so the labels come
 * in translated.
 *
 * @param array $labels title, note, count, lastmod, priority
 * @return string
 */
function pg_sitemap_stylesheet_css($labels)
{
    $label = function ($key) use ($labels) {
        return pg_sitemap_css_string(isset($labels[$key]) ? $labels[$key] : '');
    };

    return '@namespace url(http://www.sitemaps.org/schemas/sitemap/0.9);
@namespace xhtml url(http://www.w3.org/1999/xhtml);

/* The document element is the urlset: it is the page. */
urlset {
    display: block;
    margin: 0;
    padding: 32px 16px 40px;
    color-scheme: light dark;
    background: #f6f8fa;
    color: #1f2328;
    font: 14px/1.5 system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
    counter-reset: pg-sitemap-url;
}

urlset::before {
    content: ' . $label('title') . ';
    display: block;
    max-width: 960px;
    margin: 0 auto 16px;
    font-size: 22px;
    font-weight: 600;
}

/* After the last address the counter holds the total. */
urlset::after {
    content: ' . $label('count') . ' " " counter(pg-sitemap-url) "\A" ' . $label('note') . ';
    display: block;
    max-width: 960px;
    margin: 16px auto 0;
    white-space: pre-wrap;
    font-size: 12px;
    color: #59636e;
}

url {
    display: block;
    position: relative;
    box-sizing: border-box;
    max-width: 960px;
    margin: 0 auto;
    padding: 10px 16px 10px 56px;
    background: #ffffff;
    border: 1px solid #d1d9e0;
    border-top-width: 0;
    counter-increment: pg-sitemap-url;
}

url:first-of-type {
    border-top-width: 1px;
    border-radius: 8px 8px 0 0;
}

url:last-of-type {
    border-radius: 0 0 8px 8px;
}

url:only-of-type {
    border-radius: 8px;
}

url::before {
    content: counter(pg-sitemap-url);
    position: absolute;
    top: 10px;
    left: 12px;
    width: 32px;
    text-align: right;
    color: #818b98;
    font-variant-numeric: tabular-nums;
}

loc {
    display: block;
    color: #0969da;
    font-weight: 600;
    overflow-wrap: anywhere;
}

lastmod,
priority {
    display: inline-block;
    margin: 2px 16px 0 0;
    font-size: 12px;
    color: #59636e;
}

lastmod::before {
    content: ' . $label('lastmod') . ' " ";
}

priority::before {
    content: ' . $label('priority') . ' " ";
}

/* The alternates are HTML link elements, hidden by the browser until told
   otherwise: one line each, the language code and the address. */
xhtml|link {
    display: block;
    margin-top: 4px;
    font-size: 12px;
    color: #59636e;
    overflow-wrap: anywhere;
}

xhtml|link::before {
    content: attr(hreflang);
    display: inline-block;
    min-width: 2.5em;
    margin-right: 8px;
    padding: 0 6px;
    border-radius: 4px;
    background: #ddf4ff;
    color: #0550ae;
    font-weight: 600;
    text-align: center;
}

xhtml|link::after {
    content: attr(href);
}

@media (prefers-color-scheme: dark) {
    urlset {
        background: #0d1117;
        color: #f0f6fc;
    }

    urlset::after,
    lastmod,
    priority,
    xhtml|link {
        color: #9198a1;
    }

    url {
        background: #151b23;
        border-color: #3d444d;
    }

    url::before {
        color: #656c76;
    }

    loc {
        color: #4493f8;
    }

    xhtml|link::before {
        background: #0c2d6b;
        color: #79c0ff;
    }
}
';
}
