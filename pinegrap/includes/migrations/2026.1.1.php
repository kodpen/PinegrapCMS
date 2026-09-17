<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// Schema step for version 2026.1.1. Loaded by includes/migrations/runner.php when an
// installation is behind this version; never requested on its own. The function
// must be safe to run twice: every ADD / CREATE asks first and skips what exists.
if (!defined('INSTALL_OR_UPDATE')) {
	exit;
}

function upgrade_to_2026_1_1() {
	// Bot filtering: allowed bots list + block unknown bots toggle.
	$default_bots = "googlebot\nbingbot\nslurp\nduckduckbot\nbaiduspider\nyandexbot\nsogou\nexabot\nfacebot\nia_archiver";
	install_add_column('config', 'allowed_bots', "TEXT DEFAULT NULL");
	install_add_column('config', 'block_unknown_bots', "TINYINT(1) NOT NULL DEFAULT 0");
	// The default list only fills an empty column, so a second run cannot overwrite a list
	// the operator has edited since.
	db("UPDATE config SET allowed_bots = '" . escape($default_bots) . "' WHERE allowed_bots IS NULL");
}
