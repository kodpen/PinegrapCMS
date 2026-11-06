<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The panel's order screen (view_order.php) escapes the order's special offer
 * code and reference code. The offer code is any text of up to 50 characters
 * that a user with the commerce right gives an offer, and an order stores the
 * code its customer entered; it was printed raw into the screen every
 * administrator opens, so markup in a code ran there.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_view_order_offer_code_escape()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/view_order.php');

	foreach (array('special_offer_code', 'reference_code') as $name) {
		pg_assert_same(false, strpos($source, "'<span class=\"text-end text-break\">' . \$" . $name . " . '</span>"), $name . ' printed raw');
		pg_assert_same(false, strpos($source, "<span class=\"text-end text-break\">' . \$" . $name . " . '</span>"), $name . ' printed raw');
		pg_assert_contains("<span class=\"text-end text-break\">' . h(\$" . $name . ") . '</span>", $source, $name . ' escaped');
	}
}
