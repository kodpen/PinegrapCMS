<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// Schema step for version 2026.3.1. Loaded by includes/migrations/runner.php when an
// installation is behind this version; never requested on its own. The function
// must be safe to run twice: every ADD / CREATE asks first and skips what exists.
if (!defined('INSTALL_OR_UPDATE')) {
	exit;
}

function upgrade_to_2026_3_1() {
    // ── Firewall: store the reference shown to the blocked visitor ────────
    //
    // The block page printed a code and told the visitor to quote it to the
    // site owner. Nothing wrote that code anywhere, so the owner could not
    // look it up — the page's entire purpose was defeated.
    install_add_column('waf_log', 'reference', "VARCHAR(16) NOT NULL DEFAULT '' AFTER user_id");
    install_add_index('waf_log', 'idx_reference', "INDEX idx_reference (reference)");

    // ── Performance log: drop indexes nothing reads ──────────────────────
    //
    // Every request pays to maintain each index on this table. Checked
    // against the report's own queries:
    //
    //   idx_peak_memory  the memory report orders a derived table, never the
    //                    base column                              -> unused
    //   idx_script       the grouping key is a CASE expression, not the bare
    //                    column                                   -> unused
    //   idx_duration     the percentile query is already filtered by
    //                    log_timestamp, so the optimiser takes that index
    //                                                             -> redundant
    //
    // idx_timestamp and idx_area_timestamp carry every real query and stay.
    // Removing three of five secondary indexes takes roughly half the write
    // cost off a table that is written on every single page view.
    install_drop_index('perf_log', 'idx_peak_memory');
    install_drop_index('perf_log', 'idx_script');
    install_drop_index('perf_log', 'idx_duration');
}
