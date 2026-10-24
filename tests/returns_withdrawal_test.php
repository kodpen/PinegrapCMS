<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the withdrawal timing of a return (includes/fn/returns.php):
 * pg_return_timing(), the one reading of "did the request come in time" the
 * panel and the external API share, and pg_return_customer_rules(), which
 * holds a request to the customer's timing rules (a customer's own, or one
 * recorded through the API with customer_rules), and where both are used.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function returns_withdrawal_test_source($relative)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $relative);
}

// The source of one top-level function, up to the next one.
function returns_withdrawal_test_body($source, $name)
{
	$start = strpos($source, "\nfunction " . $name . '(');

	if ($start === false) {
		return '';
	}

	$next = strpos($source, "\nfunction ", $start + 1);

	return ($next === false) ? substr($source, $start) : substr($source, $start, $next - $start);
}

// A known delivery: asked before the deadline, on it, and the day after.
function test_returns_withdrawal_timing_known_delivery()
{
	$window = pg_return_window(array('delivered' => '2026-10-01'), '2026-10-24');

	pg_assert_same(array('delivered' => '2026-10-01', 'estimated' => false, 'deadline' => '2026-10-15', 'in_time' => true),
		pg_return_timing($window, '2026-10-10'), 'asked within the period');
	pg_assert_true(pg_return_timing($window, '2026-10-15')['in_time'], 'the deadline itself counts as in time');
	pg_assert_false(pg_return_timing($window, '2026-10-16')['in_time'], 'the day after is late');
	pg_assert_same('2026-10-15', pg_return_timing($window, '2026-10-16')['deadline'], 'the deadline stays the delivery\'s');
}

// An estimated delivery is said to be one.
function test_returns_withdrawal_timing_estimated_delivery()
{
	$window = pg_return_window(array('ship_date' => '2026-10-01', 'delivery_date' => '0000-00-00'), '2026-10-24');
	$timing = pg_return_timing($window, '2026-10-12');

	pg_assert_same('2026-10-04', $timing['delivered'], 'ship date and three days');
	pg_assert_true($timing['estimated']);
	pg_assert_same('2026-10-18', $timing['deadline']);
	pg_assert_true($timing['in_time']);
}

// No date at all, or nothing readable: no timing.
function test_returns_withdrawal_timing_without_a_window()
{
	pg_assert_same(null, pg_return_timing(null, '2026-10-12'), 'no window');
	pg_assert_same(null, pg_return_timing(pg_return_window(array('ship_date' => '0000-00-00'), '2026-10-24'), '2026-10-12'), 'a delivery with no date');
	pg_assert_same(null, pg_return_timing(array('estimated' => true), '2026-10-12'), 'a window without its dates');
	pg_assert_false(pg_return_timing(pg_return_window(array('delivered' => '2026-10-01'), '2026-10-24'), '')['in_time'], 'no request day: not in time');
}

// The customer's timing rules hold a customer's own request, and one
// recorded through the API with customer_rules; staff and the API by
// default skip them.
function test_returns_withdrawal_customer_rules()
{
	pg_assert_true(pg_return_customer_rules('customer', array()), 'the customer');
	pg_assert_true(pg_return_customer_rules('customer', array('customer_rules' => false)), 'the customer always');
	pg_assert_true(pg_return_customer_rules('api', array('customer_rules' => true)), 'an app acting for the customer');
	pg_assert_false(pg_return_customer_rules('api', array()), 'an app, by default');
	pg_assert_false(pg_return_customer_rules('api', array('customer_rules' => 'yes')), 'only true counts');
	pg_assert_false(pg_return_customer_rules('panel', array()), 'staff');
	pg_assert_false(pg_return_customer_rules('panel', array('customer_rules' => true)), 'staff are never held to them');
}

// The check asks the same helper; the repeat lookup keeps the request's own
// source.
function test_returns_withdrawal_check_uses_the_rule()
{
	$check = returns_withdrawal_test_body(returns_withdrawal_test_source('includes/fn/returns.php'), 'pg_return_create_check');

	pg_assert_contains("if (pg_return_customer_rules(\$result['source'], \$options)) {", $check, 'the timing block is the helper\'s');
	pg_assert_false(strpos($check, "if (\$result['source'] === 'customer') {") !== false, 'no second reading of who is held to them');
	pg_assert_contains("pg_return_find_repeat(\$order_id, \$ship_to_id, \$result['source']", $check, 'the repeat is looked for by the request\'s source');
}

// The panel reads the timing through the helper.
function test_returns_withdrawal_panel_uses_the_helper()
{
	$panel = returns_withdrawal_test_source('view_return.php');

	pg_assert_contains('pg_return_timing($window, $requested_day)', $panel);
	pg_assert_false(strpos($panel, "strcmp(\$requested_day, \$window['deadline'])") !== false, 'no second comparison of its own');
}
