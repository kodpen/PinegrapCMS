<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for two spots of the order submit that broke on PHP 8. The affiliate
 * commission was added up on top of the order record default '', and PHP 8
 * throws on '' + float and on round(''), so every order placed with a valid
 * affiliate code ended in a 500 and stayed incomplete. A product that grants
 * private access read the user's access row before it existed on a first
 * purchase.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The commission is a number before the product loop adds to it.
function test_order_submit_affiliate_commission_starts_at_zero()
{
	// The commission sum of the order (pg_order_commission()).
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/order_completion.php');

	$reset = strpos($source, "\$commission = 0;");
	$add = strpos($source, "\$commission += \$product_commission;");
	$round = strpos($source, "return round(\$commission);");

	pg_assert_true($reset !== false, 'commission reset');
	pg_assert_true(($add !== false) && ($round !== false), 'commission sum');
	pg_assert_true(($reset !== false) && ($reset < $add), 'reset comes before the sum');

	// No other assignment to the commission between the reset and the sum.
	if (($reset !== false) && ($add !== false)) {
		pg_assert_false((bool) preg_match('/\$commission = /', substr($source, $reset + 1, $add - $reset - 1)), 'no assignment in between');
	}

	// PHP itself: the old default is refused, the new one adds up.
	$refused = false;

	try {
		$commission = '';
		$commission += 1.5;
	} catch (TypeError $e) {
		// PHP 8.0 and later.
		$refused = true;
	} catch (ErrorException $e) {
		// PHP 7.1 to 7.4 warn "A non-numeric value encountered"; the runner
		// throws the warning as this exception.
		$refused = true;
	}

	pg_assert_true($refused, "'' + float is refused");

	$commission = 0;
	$commission += 448.5;
	pg_assert_same(449.0, round($commission), 'zero start');
}

// A first purchase finds no access row; the rights and the expiration date
// read as empty instead of an offset on null.
function test_order_submit_affiliate_private_access_first_purchase()
{
	// The private folder access of an order line (pg_order_complete_line_folder_access()).
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/order_completion.php');

	pg_assert_contains("\$rights = \$row['rights'] ?? '';", $source, 'rights default');
	pg_assert_contains("\$expiration_date = \$row['expiration_date'] ?? '';", $source, 'expiration date default');
}
