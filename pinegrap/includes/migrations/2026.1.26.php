<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// Schema step for version 2026.1.26. Loaded by includes/migrations/runner.php when an
// installation is behind this version; never requested on its own. The function
// must be safe to run twice: every ADD / CREATE asks first and skips what exists.
if (!defined('INSTALL_OR_UPDATE')) {
	exit;
}

function upgrade_to_2026_1_26() {
    // Customer self-service cancellation: 'cancelled' status + audit columns.
    //
    // The enum is redefined only while it still lacks 'cancelled'.  On a database that is
    // already past this version the MODIFY would be a no-op at best, and repeating it after
    // a later version has widened the enum again would silently throw values away.
    $status = install_column_info('orders', 'status');
    if (($status !== false) && (stripos((string) $status['Type'], "'cancelled'") === false)) {
        install_modify_column('orders', 'status', "enum('incomplete','complete','exported','cancelled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'incomplete'");
    } else {
        install_note(lang(array('string' => '{var:1} already carries a cancellation value', 'vars' => 'orders.status')));
    }
    install_add_column('orders', 'cancelled_at', "INT(10) UNSIGNED DEFAULT NULL");
    install_add_column('orders', 'cancelled_by', "INT(10) UNSIGNED NOT NULL DEFAULT 0");
    install_add_column('orders', 'cancellation_reason', "VARCHAR(500) NOT NULL DEFAULT ''");
    install_add_index('orders', 'idx_cancelled_at', "INDEX idx_cancelled_at (cancelled_at)");
}
