<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// Schema step for version 2026.1.6. Loaded by includes/migrations/runner.php when an
// installation is behind this version; never requested on its own. The function
// must be safe to run twice: every ADD / CREATE asks first and skips what exists.
if (!defined('INSTALL_OR_UPDATE')) {
	exit;
}

function upgrade_to_2026_1_6() {
	// Expand log_ip from varchar(15) to varchar(45) to support IPv6 addresses.
	// IPv4 max: 15 chars (e.g. 123.123.123.123)
	// IPv6 max: 39 chars (e.g. 2001:0db8:85a3:0000:0000:8a2e:0370:7334)
	// varchar(45) also covers IPv4-mapped IPv6 (e.g. ::ffff:192.168.1.1 = 45 chars max)
	install_modify_column('log', 'log_ip', "varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL");
	install_add_column('config', 'ecommerce_show_product_images', "TINYINT DEFAULT 1");
}
