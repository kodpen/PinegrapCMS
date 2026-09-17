<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// Schema step for version 2026.1.27. Loaded by includes/migrations/runner.php when an
// installation is behind this version; never requested on its own. The function
// must be safe to run twice: every ADD / CREATE asks first and skips what exists.
if (!defined('INSTALL_OR_UPDATE')) {
	exit;
}

function upgrade_to_2026_1_27() {
    // ── Refund-status tracking on orders ────────────────────────────────
    // Companion to 2026.1.26 (cancel feature). When the operator opts in
    // to automated Iyzipay refunds via ECOMMERCE_ORDER_CANCEL_AUTO_REFUND,
    // we record the outcome here so reports and the order_view widget can
    // distinguish "still owed to the customer" from "already refunded" or
    // "needs manual intervention".
    //
    // Enum values:
    //   ''                  — default / N/A (no cancellation has touched
    //                          this order yet).
    //   'pending'           — cancellation completed, refund attempt
    //                          queued but not yet acknowledged.
    //   'refunded'          — gateway confirmed the refund succeeded.
    //   'failed'            — gateway rejected the refund.
    //   'manual_required'   — auto-refund disabled, or payment method
    //                          isn't a refundable gateway, or auto-refund
    //                          threw an exception — operator must process
    //                          the refund in the gateway dashboard.
    install_add_column('orders', 'refund_status', "ENUM('','pending','refunded','failed','manual_required') NOT NULL DEFAULT ''");
    install_add_column('orders', 'refunded_at', "INT(10) UNSIGNED DEFAULT NULL");
    install_add_column('orders', 'refund_reference', "VARCHAR(255) NOT NULL DEFAULT ''");
    install_add_index('orders', 'idx_refund_status', "INDEX idx_refund_status (refund_status)");
}
