<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the refund amounts on the dashboard 'To refund' lane and the
 * order screen. A web order keeps total, gift_card_discount and
 * refunded_amount in the store's base currency (every payment driver
 * charges BASE_CURRENCY_CODE), while orders.currency_code is only the
 * currency the visitor was shown prices in. The amounts were labelled with
 * that code, so a 1.200 TL order whose buyer browsed in EUR read
 * '1.200,00 EUR'. The screens run queries, so they are read from source.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

/**
 * A file's code with its comments left out.
 *
 * @param string $relative
 * @return string
 */
function pg_test_refund_currency_code($relative)
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/' . $relative);

	return implode('', array_map('pg_test_source_text', pg_test_source_tokens(substr($source, 5))));
}

/**
 * The code between two markers of a file, comments left out.
 *
 * @param string $code
 * @param string $from
 * @param string $to
 * @return string
 */
function pg_test_refund_currency_slice($code, $from, $to)
{
	$start = strpos($code, $from);
	pg_assert_true($start !== false, 'marker found: ' . $from);

	$end = strpos($code, $to, (int) $start + strlen($from));
	pg_assert_true($end !== false, 'marker found: ' . $to);

	return substr($code, (int) $start, (int) $end - (int) $start);
}

// The lane counts and sums every waiting refund as one base-currency figure
// and prints it with the store's symbol, as the 'To ship' lane does.
function test_refund_amounts_dashboard_lane_in_base_currency()
{
	$code = pg_test_refund_currency_code('includes/dashboard/widgets/widget_9.php');
	$lane = pg_test_refund_currency_slice($code, 'function pg_dashboard_ops_refunds()', "'label'  => lang('To refund')");

	pg_assert_true(strpos($lane, 'GROUP BY currency_code') === false, 'the waiting refunds are not split by the visitor\'s currency');
	pg_assert_true(strpos($lane, 'currency_code') === false, 'the visitor\'s currency code is not read or printed');
	pg_assert_true(strpos($lane, 'In several currencies') === false, 'no "In several currencies" sub-line');
	pg_assert_same(2, substr_count($lane, 'BASE_CURRENCY_SYMBOL)'), 'the row and the lane sum print the store\'s symbol');
}

// The Refund Pending banner, its dialog, the gift card sentence, the cancel
// dialog's refunded / remaining note and the notice after cancelling print
// base amounts with the store's symbol, not orders.currency_code.
function test_refund_amounts_order_screen_in_base_currency()
{
	$code = pg_test_refund_currency_code('view_order.php');

	$refund = pg_test_refund_currency_slice($code, '$refund_payment_left_cents = ', 'if (in_array($refund_state');
	pg_assert_true(strpos($refund, 'currency_code') === false, 'the refund texts do not use the visitor\'s currency');
	pg_assert_same(2, substr_count($refund, 'BASE_CURRENCY_SYMBOL)'), 'the refund amount and the gift card part print the store\'s symbol');

	$cancel = pg_test_refund_currency_slice($code, 'of the payment was refunded already.', '} elseif ($cancel_payment_plan[\'remaining\'] > 0) {');
	pg_assert_true(strpos($cancel, 'currency_code') === false, 'the cancel note does not use the visitor\'s currency');
	pg_assert_same(2, substr_count($cancel, 'BASE_CURRENCY_SYMBOL)'), 'refunded and remaining print the store\'s symbol');

	$notice = pg_test_refund_currency_slice($code, 'if ($cancelled_gift_card_cents > 0) {', 'go(URL_SCHEME');
	pg_assert_true(strpos($notice, 'currency_code') === false, 'the notice after cancelling does not use the visitor\'s currency');
	pg_assert_same(1, substr_count($notice, 'BASE_CURRENCY_SYMBOL)'), 'the notice prints the store\'s symbol');

	// The Currency row still names the currency the visitor was shown.
	pg_assert_contains('get_currency_name_from_code($currency_code)', $code, 'the Currency row is kept');
}
