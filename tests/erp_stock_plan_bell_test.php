<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The stock plan's bell and API (includes/erp/alerts.php,
 * includes/erp/api_stock_plan.php): a planned product is announced with its
 * days left and order-by day, a product without history keeps the minimum
 * notice, and GET /erp/stock-plan filters and presents the plan rows.
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

require_once(PG_FUNCTIONS_DIR . '/includes/erp/api.php');
require_once(PG_FUNCTIONS_DIR . '/includes/erp/alerts.php');
require_once(PG_FUNCTIONS_DIR . '/includes/notifications.php');

function test_erp_stock_plan_bell_notice_carries_days_and_order_by()
{
	$rows = array(
		array('product_id' => 12, 'days_left' => 3, 'order_by_day' => '2026-11-18'),
		array('product_id' => 7, 'days_left' => 9, 'order_by_day' => '2026-11-22'),
	);

	$notice = erp_alert_plan_notice($rows);

	pg_assert_same('erp_low_stock', $notice['action'], 'the plan notice is the low stock notice, on its switch');
	pg_assert_same('2', $notice['title'], 'title counts the products');
	pg_assert_same(12, $notice['product_id'], 'the most urgent product is the first');
	pg_assert_same('3|2026-11-18', $notice['order_total'], 'days left and order-by day of the first');
}

function test_erp_stock_plan_bell_notice_without_lead_time()
{
	$notice = erp_alert_plan_notice(array(array('product_id' => 5, 'days_left' => 4, 'order_by_day' => null)));

	pg_assert_same('4|', $notice['order_total'], 'no order-by day when the lead time is unknown');
	pg_assert_same(array(), erp_alert_plan_notice(array()), 'no rows, no notice');
}

function test_erp_stock_plan_bell_announces_order_now_and_runs_out()
{
	pg_assert_same(array('runs_out', 'order_now'), erp_alert_plan_states(), 'only the states that need an order now');
}

function test_erp_stock_plan_bell_minimum_notice_left_to_unplanned_products()
{
	$planned = implode(' AND ', erp_alert_low_stock_minimum_where(true));
	$before = implode(' AND ', erp_alert_low_stock_minimum_where(false));

	pg_assert_true(strpos($planned, "sp.state <> 'no_history'") !== false, 'a product with a plan is left to the plan notice');
	pg_assert_true(strpos($planned, 'p.inventory_quantity <= m.min_quantity') !== false, 'the minimum rule stays');
	pg_assert_false(strpos($before, 'erp_stock_plan') !== false, 'without the plan table every product keeps the minimum notice');
}

function test_erp_stock_plan_bell_sentence()
{
	$one = pg_notification_stock_plan_sentence(1, 'Tea <b>', 3, '2026-11-18');
	$many = pg_notification_stock_plan_sentence(3, 'Tea', 3, '');
	$gone = pg_notification_stock_plan_sentence(2, '', 3, '2026-11-18');

	pg_assert_true(strpos($one, 'runs out in 3 day(s), order by') !== false, 'days left and order-by day: ' . $one);
	pg_assert_false(strpos($one, '<b>') !== false, 'the name is escaped');
	pg_assert_false(strpos($many, 'order by') !== false, 'no order-by day without a lead time');
	pg_assert_true(strpos($many, '2 more product(s)') !== false, 'the others are counted: ' . $many);
	pg_assert_true(strpos($gone, '2 product(s) should be ordered') !== false, 'a product gone: the count only');
}

function test_erp_stock_plan_bell_display_points_to_the_plan()
{
	$plan = pg_notification_display(array('action' => 'erp_low_stock', 'title' => '2', 'product_id' => 0, 'order_total' => '3|2026-11-18'));
	$minimum = pg_notification_display(array('action' => 'erp_low_stock', 'title' => '2', 'product_id' => 0, 'order_total' => ''));

	pg_assert_true(strpos($plan['url'], 'erp_stock_plan.php') !== false, 'the plan notice opens Stock Planning');
	pg_assert_true(strpos($minimum['url'], 'erp_stock_minimums.php?show=low') !== false, 'the minimum notice keeps its screen');
	pg_assert_true(strpos($minimum['description'], 'minimum stock') !== false, 'the minimum sentence stays');
}

function test_erp_stock_plan_api_filters()
{
	$all = erp_api_stock_plan_where(array());
	$some = erp_api_stock_plan_where(array('state' => 'order_now', 'supplier_id' => 4, 'product_id' => 9));
	$odd = erp_api_stock_plan_where(array('state' => "x' OR 1=1"));

	pg_assert_same(array("p.inventory = '1'"), $all, 'tracked products only');
	pg_assert_true(in_array("sp.state = 'order_now'", $some, true), 'state filter');
	pg_assert_true(in_array("sp.supplier_id = '4'", $some, true), 'supplier filter');
	pg_assert_true(in_array("sp.product_id = '9'", $some, true), 'product filter');
	pg_assert_same($all, $odd, 'an unknown state adds nothing');
}

function test_erp_stock_plan_api_route()
{
	$found = null;

	foreach (erp_api_routes() as $route) {
		if ($route['id'] === 'erp.stock_plan.list') {
			$found = $route;
		}
	}

	pg_assert_true(is_array($found), 'the route is declared');
	pg_assert_same('GET', $found['method'], 'a read');
	pg_assert_same('/erp/stock-plan', $found['path'], 'its path');
	pg_assert_same('erp:read', $found['scope'], 'its scope');
	pg_assert_true(function_exists($found['handler']), 'its handler exists');
	pg_assert_true(isset(erp_openapi_objects()['ErpStockPlanRow']), 'its object is declared');
}

function test_erp_stock_plan_api_present()
{
	$ctx = array('today' => '2026-11-20', 'history_from' => '2025-09-16', 'dead_days' => 90);
	$row = array(
		'product_id' => 9, 'product_sku' => 'T-1', 'product_name' => 'Tea', 'inventory_quantity' => 6,
		'units_day' => '0.000', 'season' => '1.00', 'supplier_id' => 0, 'supplier_title' => null,
		'lead_days' => null, 'lead_source' => 'none', 'days_left' => null, 'runout_day' => null,
		'order_by_day' => null, 'quantity' => 0, 'state' => 'no_history', 'last_sale' => '2026-01-10', 'avg_cost' => 250,
	);

	$out = erp_api_stock_plan_present($row, $ctx);

	pg_assert_same(array_keys(erp_api_stock_plan_schema()), array_keys($out), 'every field of the schema, in its order');
	pg_assert_same(null, $out['supplier_id'], 'no supplier yet');
	pg_assert_same(null, $out['order_by_date'], 'no order-by day');
	pg_assert_true($out['dead'], 'in stock and not sold for 90 days');
	pg_assert_same(1500, $out['dead_value'], 'stock at average cost');

	$row['supplier_id'] = 4;
	$row['supplier_title'] = 'Acme';
	$row['avg_cost'] = null;
	$row['last_sale'] = '2026-11-01';
	$out = erp_api_stock_plan_present($row, $ctx);

	pg_assert_same(4, $out['supplier_id'], 'the supplier');
	pg_assert_same('Acme', $out['supplier_title'], 'its name');
	pg_assert_false($out['dead'], 'sold lately');
	pg_assert_same(null, $out['dead_value'], 'no value when not dead');
}
