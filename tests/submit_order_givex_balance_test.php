<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The Givex balance submit_order.php checks before charging. Givex replies
 * in major units ("19.99"); the order works in integer minor units. The
 * product was kept as a float: 19.99 * 100 is 1998.9999999999998, so a gift
 * card holding exactly the order total left a total a fraction above zero,
 * the order still asked for a card payment, and the card was redeemed for
 * less than the total. The balance is rounded to whole minor units now.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_submit_order_givex_balance_float_trap()
{
	// The trap the rounding guards against.
	pg_assert_true((19.99 * 100) != 1999, 'a plain product is not a whole number');
	pg_assert_same(1999, (int) round('19.99' * 100), 'rounded, it is the minor-unit amount');
	pg_assert_same(0, 1999 - (int) round('19.99' * 100), 'a card holding the total leaves nothing to charge');
}

function test_submit_order_givex_balance_rounded()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/submit_order.php');

	pg_assert_contains("\$balance = pg_givex_minor_units(\$result['balance']);", $source, 'the Givex balance is rounded to minor units');
	pg_assert_same(0, substr_count($source, "\$balance = \$result['balance'] * 100;"), 'no float balance is kept');
}
