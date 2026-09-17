<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// Schema step for version 2026.1.5. Loaded by includes/migrations/runner.php when an
// installation is behind this version; never requested on its own. The function
// must be safe to run twice: every ADD / CREATE asks first and skips what exists.
if (!defined('INSTALL_OR_UPDATE')) {
	exit;
}

function upgrade_to_2026_1_5() {
	// local_sale_history and local_sale_history_items are no longer used.
	// Local sales are now tracked via orders.type = 'local'.
	db("DROP TABLE IF EXISTS local_sale_history_items");
	db("DROP TABLE IF EXISTS local_sale_history");
}
