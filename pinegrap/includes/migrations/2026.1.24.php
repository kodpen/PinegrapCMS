<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// Schema step for version 2026.1.24. Loaded by includes/migrations/runner.php when an
// installation is behind this version; never requested on its own. The function
// must be safe to run twice: every ADD / CREATE asks first and skips what exists.
if (!defined('INSTALL_OR_UPDATE')) {
	exit;
}

function upgrade_to_2026_1_24() {
    // Per-page custom CSS, JS, and font imports for system-layout pages (Visual Pinegrap Editor).
    // CSS and JS are stored inline (MEDIUMTEXT) and injected into the rendered page.
    // Fonts stores a JSON array of Google Fonts / @import rules.
    install_add_column('page', 'page_custom_css', "MEDIUMTEXT DEFAULT NULL");
    install_add_column('page', 'page_custom_js', "MEDIUMTEXT DEFAULT NULL");
    install_add_column('page', 'page_custom_fonts', "TEXT DEFAULT NULL");
}
