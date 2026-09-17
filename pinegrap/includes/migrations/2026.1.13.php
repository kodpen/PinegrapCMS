<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// Schema step for version 2026.1.13. Loaded by includes/migrations/runner.php when an
// installation is behind this version; never requested on its own. The function
// must be safe to run twice: every ADD / CREATE asks first and skips what exists.
if (!defined('INSTALL_OR_UPDATE')) {
	exit;
}

function upgrade_to_2026_1_13() {
	// Add Pay with Iyzico express checkout toggle.
	// Enables the "İyzico ile Öde" button on the order form,
	// redirecting customers to Iyzico for payment (similar to 3DS flow).
	install_add_column('config', 'ecommerce_pay_with_iyzico', "TINYINT UNSIGNED NOT NULL DEFAULT 0");
}
