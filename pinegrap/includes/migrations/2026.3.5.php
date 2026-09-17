<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// Schema step for version 2026.3.5. Loaded by includes/migrations/runner.php when an
// installation is behind this version; never requested on its own. The function
// must be safe to run twice: every ADD / CREATE asks first and skips what exists.
if (!defined('INSTALL_OR_UPDATE')) {
	exit;
}

function upgrade_to_2026_3_5() {
    // ── Backfill the rollups from existing visitor rows ──────────────────
    //
    // Read-only with respect to `visitors`: this reads rows and writes
    // summaries elsewhere. Nothing in the source table is modified.
    //
    // Runs against a time budget and stores its position, because it cannot
    // assume it will be allowed to finish. Whatever is left over is picked up
    // a slice at a time when an administrator opens the dashboard, so the
    // work completes without anyone having to babysit a long-running page.
    // Third argument forces the table probe to run again: 2026.3.4 created
    // these tables a moment ago, in this same request.
    if (function_exists('pg_visitor_backfill_step')) {
        pg_visitor_backfill_step(20, 20000, true);
    }
}
