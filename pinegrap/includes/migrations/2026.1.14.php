<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// Schema step for version 2026.1.14. Loaded by includes/migrations/runner.php when an
// installation is behind this version; never requested on its own. The function
// must be safe to run twice: every ADD / CREATE asks first and skips what exists.
if (!defined('INSTALL_OR_UPDATE')) {
	exit;
}

function upgrade_to_2026_1_14() {
	// Add 'canceled' status to orders, track refunded amounts, and create refund log table.
	//
	// The enum is only widened on a column that has never carried a cancellation value.
	// 2026.1.26 redefines it with 'cancelled' (two L), and MODIFY back to this shape on a
	// database that is already past that point would coerce every 'cancelled' row to '' -
	// the very damage 2026.2.3 repairs.  A step that runs again must never do that.
	$status = install_column_info('orders', 'status');
	if (($status !== false) && (stripos((string) $status['Type'], 'cancel') === false)) {
		install_modify_column('orders', 'status', "ENUM('incomplete','complete','exported','canceled') NOT NULL DEFAULT 'incomplete'");
	} else {
		install_note(lang(array('string' => '{var:1} already carries a cancellation value', 'vars' => 'orders.status')));
	}
	install_add_column('orders', 'refunded_amount', "INT NOT NULL DEFAULT 0");
	install_create_table('order_refunds', "CREATE TABLE order_refunds (
		id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
		order_id INT UNSIGNED NOT NULL,
		amount_cents INT NOT NULL,
		refund_type VARCHAR(20) NOT NULL DEFAULT 'refund',
		transaction_id VARCHAR(255) DEFAULT NULL,
		notes TEXT DEFAULT NULL,
		created_at DATETIME NOT NULL,
		INDEX idx_order_refunds_order_id (order_id)
	)" . ENGINE);

}
