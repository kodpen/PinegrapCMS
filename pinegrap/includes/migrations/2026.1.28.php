<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// Schema step for version 2026.1.28. Loaded by includes/migrations/runner.php when an
// installation is behind this version; never requested on its own. The function
// must be safe to run twice: every ADD / CREATE asks first and skips what exists.
if (!defined('INSTALL_OR_UPDATE')) {
	exit;
}

function upgrade_to_2026_1_28() {
    // ── Cart "Save for later" infrastructure ────────────────────────────
    // Adds a saved_for_later flag on order_items so a cart row can be
    // hidden from the active cart without being lost. Feature is opt-in
    // via ECOMMERCE_SAVE_FOR_LATER config define — the column is created
    // unconditionally so the migration is reversible-safe and the runtime
    // gate stays in PHP (no per-install schema branching).
    //
    // saved_at — unix timestamp; lets future UI sort "Recently saved" or
    //            prune stale wishlist rows on a cron.
    install_add_column('order_items', 'saved_for_later', "TINYINT(1) NOT NULL DEFAULT 0");
    install_add_column('order_items', 'saved_at', "INT(10) UNSIGNED DEFAULT NULL");
    install_add_index('order_items', 'idx_saved_for_later', "INDEX idx_saved_for_later (saved_for_later)");
}
