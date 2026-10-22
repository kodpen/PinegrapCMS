<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the status moves of the external API's order update
 * (includes/api/resources/orders.php, api_orders_status_move()): what each
 * move does, the ones refused, and the statuses the update route accepts.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_API_PANEL')) {
	define('PG_API_PANEL', true);
}

// orders.php pulls in the outbound webhook module, whose gate asks for
// init.php; behind the gate both files only declare functions.
if (!defined('PG_INIT_LOADED')) {
	define('PG_INIT_LOADED', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/api/resources/orders.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/schema.php');

function test_api_order_status_move_between_complete_and_exported()
{
	pg_assert_same('set', api_orders_status_move('complete', 'exported'), 'complete to exported is a plain write');
	pg_assert_same('set', api_orders_status_move('exported', 'complete'), 'exported back to complete is a plain write');
	pg_assert_same('none', api_orders_status_move('complete', 'complete'), 'the same status moves nothing');
	pg_assert_same('none', api_orders_status_move('cancelled', 'cancelled'), 'a cancelled order asked to cancel stays as it is');
}

function test_api_order_status_move_cancel()
{
	pg_assert_same('cancel', api_orders_status_move('complete', 'cancelled'), 'a completed order is cancelled through the cancellation path');
	pg_assert_same('cancel', api_orders_status_move('exported', 'cancelled'), 'an exported order too');
}

function test_api_order_status_move_out_of_cancelled_restores()
{
	// The panel's Restore: bookkeeping cleared, gift cards given their date
	// back. A plain write left the cancellation and its refund flag behind.
	pg_assert_same('restore', api_orders_status_move('cancelled', 'complete'), 'cancelled to complete is a restore');
	pg_assert_same('restore', api_orders_status_move('cancelled', 'exported'), 'cancelled to exported is a restore too');
}

function test_api_order_status_move_refuses_the_cart()
{
	// incomplete is the cart's status: a paid order is never turned back into
	// one, and a cart is not made an order without the checkout.
	pg_assert_same('refuse', api_orders_status_move('complete', 'incomplete'), 'an order is not turned into a cart');
	pg_assert_same('refuse', api_orders_status_move('cancelled', 'incomplete'), 'a cancelled order is not turned into a cart');
	pg_assert_same('refuse', api_orders_status_move('incomplete', 'complete'), 'a cart is not completed without the checkout');
	pg_assert_same('refuse', api_orders_status_move('incomplete', 'exported'), 'a cart is not exported');
	pg_assert_same('refuse', api_orders_status_move('incomplete', 'cancelled'), 'a cart is not cancelled (the order screen offers no cancel either)');
	pg_assert_same('none', api_orders_status_move('incomplete', 'incomplete'), 'a cart left as it is');
}

function test_api_order_status_update_route_drops_incomplete()
{
	$values = array();

	foreach (api_schema() as $route) {
		if ($route['id'] === 'orders.update' || $route['id'] === 'orders.list') {
			foreach ($route['params'] as $param) {
				if ($param['name'] === 'status') {
					$values[$route['id']] = $param['values'];
				}
			}
		}
	}

	pg_assert_same(array('complete', 'exported', 'cancelled'), $values['orders.update'], 'the update takes no cart status');
	pg_assert_true(in_array('incomplete', $values['orders.list'], true), 'the list still filters abandoned carts');
}
