<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the special offer code box of the classic Shopping Cart and
 * Express Order handlers (shopping_cart.php, express_order.php). A typed
 * code that was not found, or whose offers did not run, emptied the box and
 * was written to the order as an empty code, so a typo took off the code
 * that was already applied and its discount with it. A refused code now
 * leaves the order's code as it was and puts it back in the box, the way
 * the designer cart (cart_action.php) does.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Both refusals (not found, not running) mark the code refused instead of
// emptying it.
function test_cart_offer_code_refusal_keeps_the_code()
{
	foreach (array('shopping_cart.php', 'express_order.php') as $file) {
		$source = file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);

		pg_assert_same(2, substr_count($source, '$special_offer_code_refused = true;'), $file . ': both refusals marked');
		pg_assert_true(strpos($source, "\$liveform->set('special_offer_code', '');") === false, $file . ': box no longer emptied');
		pg_assert_true(strpos($source, "\$special_offer_code = '';") === false, $file . ': code no longer emptied');
	}
}

// The order's code is written only for a code that is kept; a refused one
// reads the stored code back into the box, and only a kept code can set the
// affiliate.
function test_cart_offer_code_written_only_when_kept()
{
	foreach (array('shopping_cart.php', 'express_order.php') as $file) {
		$source = file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);

		$guard  = strpos($source, 'if ($special_offer_code_refused) {');
		$read   = strpos($source, 'SELECT special_offer_code FROM orders WHERE id = ?');
		$else   = ($guard !== false) ? strpos($source, '} else {', $guard) : false;
		$update = strpos($source, "special_offer_code = ?\n");

		pg_assert_true($guard !== false, $file . ': refusal guard present');
		pg_assert_true(($read !== false) && ($read > $guard) && ($read < $else), $file . ': stored code read back on refusal');
		pg_assert_true(($update !== false) && ($update > $else), $file . ': code written only in the kept branch');
		pg_assert_contains("\$liveform->set('special_offer_code', \$stored_code_row ? (string) \$stored_code_row['special_offer_code'] : '');", $source, $file . ': stored code back in the box');
		pg_assert_contains('if ((AFFILIATE_PROGRAM == TRUE) && !$special_offer_code_refused) {', $source, $file . ': affiliate only for a kept code');
	}
}
