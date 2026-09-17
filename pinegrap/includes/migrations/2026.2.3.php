<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// Schema step for version 2026.2.3. Loaded by includes/migrations/runner.php when an
// installation is behind this version; never requested on its own. The function
// must be safe to run twice: every ADD / CREATE asks first and skips what exists.
if (!defined('INSTALL_OR_UPDATE')) {
	exit;
}

function upgrade_to_2026_2_3() {
    // ── Repair the 'canceled' / 'cancelled' status split ─────────────────
    //
    // History of the bug this fixes:
    //   * An earlier upgrade added 'canceled' (one L) to the orders.status
    //     enum. The iyzico cancel flow on view_order.php wrote that value.
    //   * upgrade_to_2026_1_26() then redefined the enum as
    //     ('incomplete','complete','exported','cancelled') — two L's — and in
    //     doing so REMOVED 'canceled' from the allowed set.
    //
    // Two consequences on installs that had already used the old flow:
    //   1. MODIFY COLUMN coerced every existing 'canceled' row to '' (MySQL's
    //      out-of-range enum fallback) — those orders lost their status.
    //   2. Every later "cancel" from view_order.php kept writing 'canceled',
    //      which is now invalid, so it also landed as ''. In strict mode the
    //      UPDATE failed outright. Either way the order never got cancelled.
    //
    // The PHP side is fixed (all cancellation now runs through
    // process_order_cancellation(), which writes 'cancelled'). This migration
    // repairs the rows that were already damaged.
    //
    // Recovery rule — cancelled_at is the only surviving evidence:
    //   * cancelled_at set   → the row really was cancelled  → 'cancelled'
    //   * cancelled_at empty → status was clobbered by the ALTER, and we
    //                          cannot tell whether it had been exported, so
    //                          we restore the safe, reversible value
    //                          'complete'. An operator can re-export or
    //                          re-cancel from the admin screens.
    //
    // Guarded by a column probe because cancelled_at only exists once
    // upgrade_to_2026_1_26() has run.
    $has_cancelled_at = db_item("SHOW COLUMNS FROM orders LIKE 'cancelled_at'");

    if ($has_cancelled_at) {
        db("UPDATE orders
            SET status = 'cancelled'
            WHERE status = ''
              AND cancelled_at IS NOT NULL
              AND cancelled_at > 0");
    }

    // Anything still blank predates the cancellation columns entirely.
    db("UPDATE orders SET status = 'complete' WHERE status = ''");
}
