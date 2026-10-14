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
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/submit_order.php');

	$reset = strpos($source, "\$commission = 0;");
	$add = strpos($source, "\$commission += \$product_commission;");
	$round = strpos($source, "\$commission = round(\$commission);");

	pg_assert_true($reset !== false, 'commission reset');
	pg_assert_true(($add !== false) && ($round !== false), 'commission sum');
	pg_assert_true(($reset !== false) && ($reset < $add), 'reset comes before the sum');

	// No other assignment to the commission between the reset and the sum.
	if (($reset !== false) && ($add !== false)) {
		pg_assert_false((bool) preg_match('/\$commission = /', substr($source, $reset + 1, $add - $reset - 1)), 'no assignment in between');
	}

	// PHP itself: the old default throws, the new one adds up.
	$threw = false;

	try {
		$commission = '';
		$commission += 1.5;
	} catch (TypeError $e) {
		$threw = true;
	}

	pg_assert_true($threw || (PHP_MAJOR_VERSION < 8), "'' + float throws on PHP 8");

	$commission = 0;
	$commission += 448.5;
	pg_assert_same(449.0, round($commission), 'zero start');
}

// A first purchase finds no access row; the rights and the expiration date
// read as empty instead of an offset on null.
function test_order_submit_affiliate_private_access_first_purchase()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/submit_order.php');

	pg_assert_contains("\$rights = \$row['rights'] ?? '';", $source, 'rights default');
	pg_assert_contains("\$expiration_date = \$row['expiration_date'] ?? '';", $source, 'expiration date default');
}
