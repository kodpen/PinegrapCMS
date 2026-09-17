<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// Schema step for version 2026.3.2. Loaded by includes/migrations/runner.php when an
// installation is behind this version; never requested on its own. The function
// must be safe to run twice: every ADD / CREATE asks first and skips what exists.
if (!defined('INSTALL_OR_UPDATE')) {
	exit;
}

function upgrade_to_2026_3_2() {
    // ── Performance monitor: summarise instead of hoarding ───────────────
    //
    // One site reached 1,612,330 rows in perf_log. At that size the report
    // page became the slowest thing on the whole site — 40 seconds to open —
    // because the percentile query walked 1.5 million rows every time it was
    // viewed. Meanwhile every visitor request was inserting into that same
    // table, and the retention sweep was locking rows in it.
    //
    // The fix follows what the numbers actually showed: average 48 ms, p95
    // 241 ms, worst case 101 seconds. The middle of that distribution is
    // healthy and carries no information. Only the tail is worth storing at
    // full detail.
    //
    //   perf_stats  every request, folded into an hourly bucket per page.
    //               One INSERT ... ON DUPLICATE KEY UPDATE, and the table
    //               stops growing with traffic.
    //   perf_log    only requests slower than the threshold, now carrying the
    //               address, user agent and query string so a 101-second
    //               request can actually be investigated.
    db("CREATE TABLE IF NOT EXISTS perf_stats (
        bucket_key   CHAR(40) NOT NULL,
        hour_start   INT UNSIGNED NOT NULL,
        label        VARCHAR(255) NOT NULL DEFAULT '',
        area         VARCHAR(16) NOT NULL DEFAULT 'frontend',
        hits         INT UNSIGNED NOT NULL DEFAULT 0,
        slow_hits    INT UNSIGNED NOT NULL DEFAULT 0,
        total_ms     BIGINT UNSIGNED NOT NULL DEFAULT 0,
        min_ms       INT UNSIGNED NOT NULL DEFAULT 0,
        max_ms       INT UNSIGNED NOT NULL DEFAULT 0,
        total_kb     BIGINT UNSIGNED NOT NULL DEFAULT 0,
        max_kb       INT UNSIGNED NOT NULL DEFAULT 0,
        total_cpu_ms BIGINT UNSIGNED NOT NULL DEFAULT 0,
        PRIMARY KEY (bucket_key, hour_start),
        INDEX idx_hour (hour_start),
        INDEX idx_area_hour (area, hour_start)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Context for the slow rows. Without these a 101-second entry says only
    // that something was slow, not whether it was a scraper hammering a
    // filtered catalogue or a real customer on a product page.
    $perf_exists = db_item("SHOW TABLES LIKE 'perf_log'");

    if ($perf_exists) {
        if (!db_item("SHOW COLUMNS FROM perf_log LIKE 'ip_address'")) {
            db("ALTER TABLE perf_log ADD ip_address VARCHAR(45) NOT NULL DEFAULT ''");
        }

        if (!db_item("SHOW COLUMNS FROM perf_log LIKE 'user_agent'")) {
            db("ALTER TABLE perf_log ADD user_agent VARCHAR(255) NOT NULL DEFAULT ''");
        }

        // The query string was deliberately stripped before, to keep grouping
        // cardinality down. That reasoning no longer applies: grouping happens
        // in perf_stats now, and on a slow row the parameters are usually the
        // whole explanation.
        if (!db_item("SHOW COLUMNS FROM perf_log LIKE 'query_string'")) {
            db("ALTER TABLE perf_log ADD query_string VARCHAR(512) NOT NULL DEFAULT ''");
        }

        // The existing rows are one-per-request records of ordinary traffic —
        // the exact data this change stops collecting. Keeping them would
        // leave the table huge and the new slow-request list meaningless,
        // since every fast request would still be in it.
        db("TRUNCATE perf_log");
    }
}
