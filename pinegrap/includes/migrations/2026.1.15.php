<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// Schema step for version 2026.1.15. Loaded by includes/migrations/runner.php when an
// installation is behind this version; never requested on its own. The function
// must be safe to run twice: every ADD / CREATE asks first and skips what exists.
if (!defined('INSTALL_OR_UPDATE')) {
	exit;
}

function upgrade_to_2026_1_15() {
	// Store the full base total (subtotal + tax + shipping, before installment) in 3DS state.
	// Without this, the total restored on 3DS return was missing tax/shipping.
	install_add_column('iyzipay_3ds_state', 'base_total_cents', "INT NOT NULL DEFAULT 0");
}
