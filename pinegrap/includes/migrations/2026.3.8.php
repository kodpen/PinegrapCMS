<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// Schema step for version 2026.3.8. Loaded by includes/migrations/runner.php when an
// installation is behind this version; never requested on its own. The function
// must be safe to run twice: every ADD / CREATE asks first and skips what exists.
if (!defined('INSTALL_OR_UPDATE')) {
	exit;
}

function upgrade_to_2026_3_8() {
    // ── Rebuild the visitor rollups from `visitors` ──────────────────────
    //
    // The first cut of the backfill had two faults that only show up once
    // real traffic runs through it.
    //
    // 1. The two summary tables counted different things. Site totals summed
    //    visitors.page_views while the per-content table used COUNT(*), which
    //    counts sessions. The dashboard reads the hour's total from one and
    //    the busiest item from the other, so it printed "29 page views" with
    //    "home-1 · 1" underneath.
    //
    // 2. visitors.page_views keeps climbing after the rollup starts counting
    //    live. A session already open at the cutover had its views recorded
    //    live AND summed again when the backfill reached its row, inflating
    //    every hour that straddled the upgrade.
    //
    // Both are fixed in the writer, but the numbers already stored were
    // produced by the old one and cannot be corrected in place — a bucket
    // holds a single total with no record of which half came from where. So
    // the derived tables are dropped and rebuilt from `visitors`, which has
    // been the authority all along and is not touched here.
    //
    // Cost of the rebuild: item-level detail collected since the upgrade is
    // re-derived from landing_page_name, so it returns to page level. Only
    // the summaries lose that; the raw table never had it to begin with, and
    // page views recorded from now on carry their item as normal.
    if (!db_item("SHOW TABLES LIKE 'visitor_content_hourly'")) {
        return;
    }

    db("TRUNCATE visitor_stats_hourly");
    db("TRUNCATE visitor_content_hourly");

    // Re-read the ceiling. Everything up to this id now comes from `visitors`
    // in one consistent pass, so there is no boundary for the two counting
    // methods to disagree across.
    $max_visitor_id = (int) db_value("SELECT MAX(id) FROM visitors");

    db("UPDATE config SET
            visitor_rollup_max_id = '" . $max_visitor_id . "',
            visitor_rollup_cursor = 0,
            visitor_rollup_done   = '" . ($max_visitor_id > 0 ? 0 : 1) . "'");

    if (function_exists('pg_visitor_backfill_step')) {
        pg_visitor_backfill_step(20, 20000, true);
    }
}
