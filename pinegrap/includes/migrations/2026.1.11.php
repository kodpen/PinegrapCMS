<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// Schema step for version 2026.1.11. Loaded by includes/migrations/runner.php when an
// installation is behind this version; never requested on its own. The function
// must be safe to run twice: every ADD / CREATE asks first and skips what exists.
if (!defined('INSTALL_OR_UPDATE')) {
	exit;
}

function upgrade_to_2026_1_11() {
	// Add class column to menu_items to allow custom CSS classes on individual menu items.
	install_add_column('menu_items', 'class', "VARCHAR(255) DEFAULT NULL");

	// Add active_item_class column to menus to allow configurable active item class per menu.
	install_add_column('menus', 'active_item_class', "VARCHAR(255) DEFAULT NULL");
}
