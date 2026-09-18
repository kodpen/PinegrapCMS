<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// Schema step for version 2026.1. Loaded by includes/migrations/runner.php when an
// installation is behind this version; never requested on its own. The function
// must be safe to run twice: every ADD / CREATE asks first and skips what exists.
if (!defined('INSTALL_OR_UPDATE')) {
	exit;
}

function upgrade_to_2026_1() {
	install_add_column('short_links', 'file_id', "VARCHAR(100) DEFAULT ''");
	install_modify_column('short_links', 'destination_type', "ENUM('page', 'product_group', 'product', 'url', 'file') NOT NULL DEFAULT 'page'");
	install_add_column('config', 'indexnow_key', "VARCHAR(256) NOT NULL DEFAULT ''");
	install_create_table('iyzipay_3ds_state', "CREATE TABLE iyzipay_3ds_state (
    	id INT AUTO_INCREMENT PRIMARY KEY,
    	conversation_id VARCHAR(64) NOT NULL,
    	payment_id VARCHAR(64) DEFAULT NULL,
    	order_id INT NOT NULL,
    	subtotal_cents INT NOT NULL,
    	discount_cents INT DEFAULT 0,
    	installment_charge_cents INT DEFAULT 0,
    	created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    	INDEX (conversation_id),
    	INDEX (payment_id),
    	INDEX (order_id)
	)" . ENGINE);
	install_add_column('config', 'ecommerce_troy', "TINYINT(4) NOT NULL DEFAULT 1");
	install_modify_column('user', 'user_devpasspin', "VARCHAR(255) NOT NULL DEFAULT ''");
	// LONGTEXT rather than JSON: the JSON type needs MySQL 5.7.8 / MariaDB 10.2,
	// and a failed ADD here stopped the whole upgrade chain on older servers.
	// MariaDB itself stores JSON as LONGTEXT, nothing reads the column by JSON
	// functions, and 2026.4.4 drops the table.
	install_add_column('custom_apps', 'permissions', "LONGTEXT NOT NULL");
}
