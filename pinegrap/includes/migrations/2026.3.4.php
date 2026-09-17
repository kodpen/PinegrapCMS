<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// Schema step for version 2026.3.4. Loaded by includes/migrations/runner.php when an
// installation is behind this version; never requested on its own. The function
// must be safe to run twice: every ADD / CREATE asks first and skips what exists.
if (!defined('INSTALL_OR_UPDATE')) {
	exit;
}

function upgrade_to_2026_3_4() {
    // ── Visitor reporting: aggregate at write time ───────────────────────
    //
    // Two separate faults, one root cause.
    //
    // 1. update_visitor_page_data() only ever wrote landing_page_name, and
    //    only on a visitor's first page. Everything after that incremented a
    //    counter and was otherwise discarded. Worse, the name reaching it had
    //    already had its slug stripped (get_page.php:92 for catalog detail,
    //    :122 for form item view), so every product recorded as 'urun-detay'
    //    and every article as 'blog-gorunum'. The question "which article was
    //    read at 3pm" had no answer anywhere in the database.
    //
    // 2. Every report counted raw visitor rows with HOUR(FROM_UNIXTIME(...))
    //    groupings. At 100,000-200,000 visits a day that is millions of rows
    //    per month, scanned and sorted into a temporary table on each load.
    //
    // Both are fixed by counting when the view happens rather than
    // reconstructing it later. This is the shape waf_log took in 2026.2.6: a
    // bucket key plus INSERT ... ON DUPLICATE KEY UPDATE, so a repeated event
    // increments a counter instead of adding a row.
    //
    // No visitor data is removed or altered. The `visitors` table keeps every
    // column and every row, and view_visitor_report.php's advanced filters
    // continue to read it directly.

    // Read the ceiling BEFORE the tables exist.
    //
    // pg_visitor_rollup_ready() starts returning true the moment
    // visitor_content_hourly appears, and live counting begins from that
    // instant. Rows at or below this id were therefore written while nothing
    // was counting, and are the backfill's job; rows above it are counted
    // live. Reading the ceiling first means the two ranges cannot overlap.
    // The handful of page views that land between this read and the CREATE
    // are missed rather than double-counted, which is the right way round to
    // be wrong.
    $max_visitor_id = (int) db_value("SELECT MAX(id) FROM visitors");

    // Site-wide totals: 24 rows per day, 8,760 a year. This is what the
    // dashboard's three traffic panels read instead of the visitors table.
    db("CREATE TABLE IF NOT EXISTS visitor_stats_hourly (
            stat_date    DATE NOT NULL,
            stat_hour    TINYINT UNSIGNED NOT NULL,
            new_visitors INT UNSIGNED NOT NULL DEFAULT 0,
            page_views   INT UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY (stat_date, stat_hour)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Per-content totals: one row per (hour, page, item).
    //
    // item_type/item_id hold the primary key of what was actually shown, not
    // its title. A renamed product then renames throughout the report history
    // instead of leaving stale copies of the old title in old rows.
    //
    // The unique key is a sha1 of the bucket rather than the columns
    // themselves. A composite key over page_name would run to roughly 780
    // bytes in utf8mb4, past the 767-byte per-column index limit on MySQL 5.6
    // with COMPACT row format. waf_log's event_key solves the same problem
    // the same way.
    db("CREATE TABLE IF NOT EXISTS visitor_content_hourly (
            id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
            bucket_key CHAR(40) NOT NULL,
            stat_date  DATE NOT NULL,
            stat_hour  TINYINT UNSIGNED NOT NULL,
            page_id    INT UNSIGNED NOT NULL DEFAULT 0,
            page_name  VARCHAR(100) NOT NULL DEFAULT '',
            item_type  VARCHAR(20) NOT NULL DEFAULT '',
            item_id    INT UNSIGNED NOT NULL DEFAULT 0,
            views      INT UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_bucket (bucket_key),
            KEY idx_date_hour (stat_date, stat_hour),
            KEY idx_date_views (stat_date, views),
            KEY idx_item (item_type, item_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Backfill bookkeeping. Kept in config so the work survives a killed
    // request: this software runs on dozens of server types and the ones with
    // a hard FastCGI or proxy timeout will cut a long upgrade off mid-flight
    // no matter what ini_set('max_execution_time') says.
    if (!db_item("SHOW COLUMNS FROM config LIKE 'visitor_rollup_max_id'")) {
        db("ALTER TABLE config ADD visitor_rollup_max_id INT UNSIGNED NOT NULL DEFAULT 0");
    }
    if (!db_item("SHOW COLUMNS FROM config LIKE 'visitor_rollup_cursor'")) {
        db("ALTER TABLE config ADD visitor_rollup_cursor INT UNSIGNED NOT NULL DEFAULT 0");
    }
    if (!db_item("SHOW COLUMNS FROM config LIKE 'visitor_rollup_done'")) {
        db("ALTER TABLE config ADD visitor_rollup_done TINYINT(1) NOT NULL DEFAULT 0");
    }

    db("UPDATE config SET
            visitor_rollup_max_id = '" . $max_visitor_id . "',
            visitor_rollup_cursor = 0,
            visitor_rollup_done   = '" . ($max_visitor_id > 0 ? 0 : 1) . "'");
}
