<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// Schema step for version 2026.1.12. Loaded by includes/migrations/runner.php when an
// installation is behind this version; never requested on its own. The function
// must be safe to run twice: every ADD / CREATE asks first and skips what exists.
if (!defined('INSTALL_OR_UPDATE')) {
	exit;
}

function upgrade_to_2026_1_12() {
	// Add new social networking service columns.
	// Replaces defunct AddThis (closed 2023) and Google+1 (closed 2019)
	// with modern URL-based share services.
	install_add_column('config', 'social_networking_whatsapp', "TINYINT UNSIGNED NOT NULL DEFAULT 0");
	install_add_column('config', 'social_networking_telegram', "TINYINT UNSIGNED NOT NULL DEFAULT 0");
	install_add_column('config', 'social_networking_pinterest', "TINYINT UNSIGNED NOT NULL DEFAULT 0");
	install_add_column('config', 'social_networking_reddit', "TINYINT UNSIGNED NOT NULL DEFAULT 0");
	install_add_column('config', 'social_networking_email', "TINYINT UNSIGNED NOT NULL DEFAULT 0");
}
