<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// Schema step for version 2026.4.1. Loaded by includes/migrations/runner.php when an
// installation is behind this version; never requested on its own. The function
// must be safe to run twice: every ADD / CREATE asks first and skips what exists.
if (!defined('INSTALL_OR_UPDATE')) {
	exit;
}

function upgrade_to_2026_4_1() {

    // Daily rollup for article views.
    //
    // submitted_form_views held one row per view. On a site serving 200,000
    // views a day it had reached eight million rows and 1.1 GB -- 73% of the
    // whole database, 946 MB of it index. Being MyISAM, every article view took
    // an exclusive lock on the entire table while four B-trees were updated,
    // and every other request touching it queued behind that lock. Measured on
    // the affected site, requests spent 84% of their wall clock waiting rather
    // than computing, at every hour of the day.
    //
    // Two of those four indexes could never be used at all: `submitted_form_id`
    // repeated the leading column of the composite index, and `page_id` had a
    // cardinality of six across eight million rows.
    //
    // All of it existed to answer one question on one administrator screen --
    // how many views each article drew in the last N days. The counter readers
    // see comes from submitted_form_info.number_of_views and is untouched.
    //
    // No secondary index here, deliberately. The retention sweep and the
    // delete-by-page paths scan, but they scan a few thousand rows; paying for
    // an index on every write to save that is the trade that produced the table
    // this one replaces.
    db("CREATE TABLE IF NOT EXISTS submitted_form_view_stats (
        submitted_form_id INT UNSIGNED NOT NULL DEFAULT 0,
        page_id           INT UNSIGNED NOT NULL DEFAULT 0,
        view_date         DATE         NOT NULL DEFAULT '0000-00-00',
        views             INT UNSIGNED NOT NULL DEFAULT 0,
        PRIMARY KEY (submitted_form_id, page_id, view_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Bookkeeping for an interruptible backfill.
    $config_columns = array(
        'sfv_rollup_cutover' => "ALTER TABLE config ADD sfv_rollup_cutover INT UNSIGNED NOT NULL DEFAULT 0",
        'sfv_rollup_cursor'  => "ALTER TABLE config ADD sfv_rollup_cursor INT UNSIGNED NOT NULL DEFAULT 0",
        'sfv_rollup_done'    => "ALTER TABLE config ADD sfv_rollup_done TINYINT(1) NOT NULL DEFAULT 0",
    );

    foreach ($config_columns as $column => $sql) {
        if (!db_item("SHOW COLUMNS FROM config LIKE '" . $column . "'")) {
            db($sql);
        }
    }

    // install/index.php never loads init.php, so the session time zone MySQL
    // would otherwise use is the server default. The live writer buckets with
    // CURDATE() and the backfill with DATE(FROM_UNIXTIME(...)); if the two
    // clocks disagree, history and new traffic land on different days and meet
    // at a seam the width of the offset.
    if (function_exists('pg_sync_mysql_timezone')) {
        pg_sync_mysql_timezone();
    }

    // The ceiling is fixed before anything is written, so the live writer and
    // the backfill can never cover the same second twice. Views recorded from
    // this moment go to the rollup; the backfill owns everything before it.
    $cutover = time();

    $oldest = 0;
    if (db_item("SHOW TABLES LIKE 'submitted_form_views'")) {
        // Served by the `timestamp` index, so this is a lookup, not a scan of
        // eight million rows.
        $oldest = (int) db_value("SELECT MIN(timestamp) FROM submitted_form_views WHERE timestamp > 0");
    }

    if ($oldest > 0) {
        // Start on a day boundary so every chunk maps to exactly one bucket.
        $cursor = strtotime(date('Y-m-d', $oldest));

        db("UPDATE config SET
                sfv_rollup_cutover = '" . (int) $cutover . "',
                sfv_rollup_cursor  = '" . (int) $cursor . "',
                sfv_rollup_done    = 0");
    } else {
        // Nothing to summarise: a fresh install, or a site whose legacy table
        // was already retired.
        db("UPDATE config SET
                sfv_rollup_cutover = '" . (int) $cutover . "',
                sfv_rollup_cursor  = '" . (int) $cutover . "',
                sfv_rollup_done    = 1");
    }

    // Spend a bounded slice here; the form view directory screen carries the
    // rest a few seconds at a time. Split across page loads rather than run to
    // completion because IIS FastCGI and nginx end a long request on their own
    // schedule, and the version number is written only after this returns -- an
    // upgrade killed midway starts over.
    //
    // $recheck bypasses the readiness probe's static cache: the table was
    // created moments ago in this same request, and a "no" cached before that
    // would make the backfill skip itself and report success.
    if (function_exists('pg_sfv_backfill_step')) {
        pg_sfv_backfill_step(20, true);
    }
}
