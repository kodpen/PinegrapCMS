<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the express order submit (express_order.php): a payment period
 * the customer sets for a recurring line is stored only when it is one of
 * get_payment_period_options(), as the cart (shopping_cart.php,
 * cart_action.php) already does; another value is dropped and marked.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The customer's schedule block of the express order submit.
function _eorp_block()
{
	$src = (string) file_get_contents(PG_FUNCTIONS_DIR . '/express_order.php');
	$from = strpos($src, "\$recurring_payment_period = (string) \$liveform->get_field_value('recurring_payment_period_'");
	pg_assert_true($from !== false, 'period read found');
	$to = strpos($src, "UPDATE order_items", (int) $from);
	return (string) substr($src, (int) $from, (int) $to - (int) $from);
}

// The posted period is checked against the fixed list before it is stored.
function test_express_order_period_checked_against_options()
{
	$block = _eorp_block();
	pg_assert_true(strpos($block, 'get_payment_period_options()') !== false, 'period checked against the option list');
	pg_assert_true(strpos($block, "\$recurring_payment_period = ''") !== false, 'an unknown period is dropped');
}

// The list the check uses: the empty answer and the eight periods.
function test_express_order_period_options_list()
{
	$periods = array_values(get_payment_period_options());
	pg_assert_true(in_array('Monthly', $periods, true), 'Monthly listed');
	pg_assert_true(in_array('', $periods, true), 'empty answer listed');
	pg_assert_true(!in_array('Hourly', $periods, true), 'an invented period is not listed');
}
