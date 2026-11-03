<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the local order completion shared by the counter screen and the
 * external API (includes/fn/local_order.php): the line refusals, the way of
 * paying, the checks a completion and a dry run make before any write, and
 * source pins that the screen hands its session cart to the shared function.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(dirname(__DIR__) . '/pinegrap/includes/fn/local_order.php');

function local_order_test_line($id, $product_id, $quantity, $extra = array())
{
	return array_merge(array(
		'id' => $id,
		'product_id' => $product_id,
		'quantity' => $quantity,
		'enabled' => 1,
		'inventory' => 0,
		'inventory_quantity' => 0,
		'rate' => 20.0,
		'net' => 1000 * $quantity,
		'tax' => 200 * $quantity,
		'gross' => 1200 * $quantity,
	), $extra);
}

function test_local_order_line_errors_none()
{
	$lines = array(
		local_order_test_line(1, 10, 3),
		local_order_test_line(2, 11, 2, array('inventory' => 1, 'inventory_quantity' => 2)),
	);

	pg_assert_same(array(), pg_local_order_line_errors($lines), 'untracked and within the stock');
}

function test_local_order_line_errors_switched_off()
{
	$errors = pg_local_order_line_errors(array(local_order_test_line(5, 10, 1, array('enabled' => 0))));

	pg_assert_same(array(5), array_keys($errors), 'the line is named');
	pg_assert_contains('switched off', $errors[5]);
}

// One product on two lines counts its units together: 2 + 3 of a stock of 4
// names both lines; a switched-off line is not counted against the stock.
function test_local_order_line_errors_units_counted_together()
{
	$stock = array('inventory' => 1, 'inventory_quantity' => 4);
	$errors = pg_local_order_line_errors(array(
		local_order_test_line(1, 10, 2, $stock),
		local_order_test_line(2, 10, 3, $stock),
		local_order_test_line(3, 11, 1),
	));

	pg_assert_same(array(1, 2), array_keys($errors), 'both lines of the product');
	pg_assert_contains('4', $errors[1], 'the stock is named');

	$sold_out = pg_local_order_line_errors(array(local_order_test_line(7, 12, 1, array('inventory' => 1, 'inventory_quantity' => 0))));
	pg_assert_contains('out of stock', $sold_out[7]);
}

function test_local_order_pay_method()
{
	foreach (array('cash', 'card', 'transfer', 'cheque', 'other', 'later', 'split') as $method) {
		pg_assert_same($method, pg_local_order_pay_method($method), $method);
	}

	foreach (array('', 'CASH', 'bitcoin', null, array('cash'), 1) as $raw) {
		pg_assert_same('later', pg_local_order_pay_method($raw), var_export($raw, true));
	}
}

function test_local_order_check_empty()
{
	$user = array('id' => 1, 'role' => 0);

	foreach (array(0, 9) as $order_id) {
		$res = pg_local_order_check($user, $order_id, array(), array('pay_method' => 'card'));
		pg_assert_false($res['ok'], 'refused');
		pg_assert_same('warning', $res['level']);
	}

	// Lines checked before they are written (the API) need no order yet.
	pg_assert_true(pg_local_order_check($user, 0, array(local_order_test_line(1, 10, 1)), array('pay_method' => 'card'))['ok'], 'unwritten lines');
}

function test_local_order_check_line_errors()
{
	$res = pg_local_order_check(array('id' => 1, 'role' => 0), 9, array(local_order_test_line(4, 10, 1, array('enabled' => 0))), array('pay_method' => 'card'));

	pg_assert_false($res['ok'], 'refused');
	pg_assert_same(array(4), array_keys($res['line_errors']), 'per line');
}

function test_local_order_check_passes()
{
	$lines = array(local_order_test_line(1, 10, 2), local_order_test_line(2, 11, 1));

	foreach (array('card', 'later', 'cash', '') as $method) {
		$res = pg_local_order_check(array('id' => 1, 'role' => 0), 9, $lines, array('pay_method' => $method, 'received' => 0));
		pg_assert_true($res['ok'], $method);
		pg_assert_same(null, $res['split'], $method . ': no split');
	}

	// Cash covering the total, or more (change due), passes.
	$res = pg_local_order_check(array('id' => 1, 'role' => 0), 9, $lines, array('pay_method' => 'cash', 'received' => 5000));
	pg_assert_true($res['ok'], 'change due');
}

// A split payment is recorded in the ERP's tills: refused before any write
// while the ERP is off.
function test_local_order_check_split_needs_the_erp()
{
	if (local_sale_erp_on()) {
		return;
	}

	$res = pg_local_order_check(array('id' => 1, 'role' => 0), 9, array(local_order_test_line(1, 10, 1)), array('pay_method' => 'split', 'pay_parts' => '[]'));

	pg_assert_false($res['ok'], 'refused');
	pg_assert_contains('ERP cash right', $res['message']);
}

function test_local_order_screen_hands_over_its_cart()
{
	$sale = (string) file_get_contents(dirname(__DIR__) . '/pinegrap/includes/local_sale.php');
	$order = (string) file_get_contents(dirname(__DIR__) . '/pinegrap/includes/fn/local_order.php');

	pg_assert_contains("require_once(PG_FUNCTIONS_DIR . '/includes/fn/local_order.php');", $sale, 'the screen loads the module');
	pg_assert_contains('pg_local_order_complete($user, local_sale_order_id(), local_sale_customer(), $input)', $sale, 'the session cart and customer are handed over');
	pg_assert_contains("if (\$res['code'] === 'completed') {", $sale, 'a cart completed meanwhile is let go');
	pg_assert_contains("\$_SESSION['ecommerce']['local_sale_payment'] = array(", $sale, 'the till and the way of paying are remembered');
	pg_assert_same(0, substr_count($order, '$_SESSION'), 'the shared completion reads no session');

	// The claim comes before every write and the check before the claim.
	$check = strpos($order, '$checked = pg_local_order_check(');
	$claim = strpos($order, "UPDATE orders SET status = 'complete', type = 'local'");
	$number = strpos($order, '$order_number = local_sale_next_order_number();');
	pg_assert_true(($check !== false) && ($claim !== false) && ($check < $claim), 'checked, then claimed');
	pg_assert_true(($number !== false) && ($claim < $number), 'claimed, then numbered');
	pg_assert_contains("pg_announce('order.created', array('id' => \$order_id, 'order_number' => (int) \$order_number));", $order, 'order.created');
}
