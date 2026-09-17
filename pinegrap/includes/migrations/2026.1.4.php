<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// Schema step for version 2026.1.4. Loaded by includes/migrations/runner.php when an
// installation is behind this version; never requested on its own. The function
// must be safe to run twice: every ADD / CREATE asks first and skips what exists.
if (!defined('INSTALL_OR_UPDATE')) {
	exit;
}

function upgrade_to_2026_1_4() {
	// Add type column to orders to distinguish online vs. local (in-store) orders.
	// Default is 'online' so all existing orders are automatically classified correctly.
	install_add_column('orders', 'type', "VARCHAR(20) NOT NULL DEFAULT 'online'");
	install_add_index('orders', 'idx_type', "INDEX idx_type (type)");
}
