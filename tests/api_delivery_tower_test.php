<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for GET /reports/delivery (includes/api/resources/reports.php) and
 * GET /shipments/problems (includes/api/resources/shipments.php): the
 * scorecard's figures with "too few parcels", a month and twelve months, the
 * arrival check; a problem's fields, its province and never an address or a
 * name, and how a closed one closed.
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

require_once(PG_FUNCTIONS_DIR . '/includes/api/resources/reports.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/resources/shipments.php');

// The presenter's helpers live in includes/api/response.php, which loads only
// behind the API's own entry. A stand-in of the same shape.
if (!function_exists('api_time')) {
	function api_time($unix)
	{
		$unix = (int) $unix;

		return ($unix <= 0) ? null : gmdate('Y-m-d\TH:i:s\Z', $unix);
	}
}

// $count parcels of one carrier and province shipped on $day (a local
// date), delivered $days later, with an estimate $estimate_days after the
// ship day.
function _api_delivery_test_rows($carrier, $state, $day, $count, $days, $estimate_days, array $more = array())
{
	$shipped = strtotime($day . ' 10:00:00');
	$rows = array();

	for ($i = 0; $i < $count; $i++) {
		$rows[] = array_merge(array(
			'carrier' => $carrier,
			'state' => $state,
			'city' => '',
			'shipped_at' => $shipped,
			'delivered_at' => $shipped + ($days * 86400),
			'delivery_date' => date('Y-m-d', $shipped + ($estimate_days * 86400)),
			'status' => 'delivered',
			'has_problem' => 0,
		), $more);
	}

	return $rows;
}

function test_api_delivery_figures_hide_too_few_parcels()
{
	$out = api_delivery_figures_present(array('parcels' => 7, 'enough' => false, 'on_time' => 50.0, 'median' => 2.0, 'problem' => 0.0, 'returned' => 0.0));
	pg_assert_same(array('parcels' => 7, 'enough' => false, 'on_time' => null, 'median' => null, 'problem' => null, 'returned' => null), $out, 'under the minimum only the parcels are given');

	$out = api_delivery_figures_present(array('parcels' => 25, 'enough' => true, 'on_time' => 96.04, 'median' => 2.5, 'problem' => 4.0, 'returned' => 0.0));
	pg_assert_same(96.0, $out['on_time'], 'a share with one decimal');
	pg_assert_same(2.5, $out['median']);
	pg_assert_same(0.0, $out['returned'], 'zero is a figure, not null');

	$out = api_delivery_figures_present(array('parcels' => 25, 'enough' => true, 'on_time' => null, 'median' => null, 'problem' => 0.0, 'returned' => 0.0));
	pg_assert_same(null, $out['on_time'], 'nothing judged stays null');
}

function test_api_delivery_report_month()
{
	$state = array();
	pg_delivery_scorecard_add($state, array_merge(
		_api_delivery_test_rows('yurtici', 'Ankara', '2026-10-05', 20, 2, 3),
		_api_delivery_test_rows('yurtici', 'İzmir', '2026-10-06', 5, 4, 3),
		_api_delivery_test_rows('aras', 'Ankara', '2026-10-07', 3, 1, 3)
	));
	$view = pg_delivery_scorecard_month_view($state, '2026-10');
	$arrival = array(array('id' => 4, 'name' => 'Standard delivery', 'check' => array('measured' => 2.6, 'set' => 1)));

	$out = api_delivery_report_present($view, false, array('2026-10'), array('yurtici' => 'Yurtiçi Kargo', 'aras' => 'Aras Kargo'), $arrival, true, gmmktime(1, 0, 0, 11, 20, 2026));

	pg_assert_same('month', $out['period']);
	pg_assert_same('2026-10', $out['from_month']);
	pg_assert_same('2026-10', $out['to_month']);
	pg_assert_same(20, $out['min_parcels']);
	pg_assert_same(28, $out['total']['parcels'], 'every parcel counts in the total');
	pg_assert_same(2, count($out['carriers']), 'a carrier a row');
	pg_assert_same('yurtici', $out['carriers'][0]['carrier'], 'most parcels first');
	pg_assert_same('Yurtiçi Kargo', $out['carriers'][0]['name']);
	pg_assert_same(true, $out['carriers'][0]['figures']['enough']);
	pg_assert_same(80.0, $out['carriers'][0]['figures']['on_time'], 'the 5 late parcels of 25');
	pg_assert_same(2, count($out['carriers'][0]['provinces']), 'its provinces under it');
	pg_assert_same('Ankara', $out['carriers'][0]['provinces'][0]['name']);
	pg_assert_same(100.0, $out['carriers'][0]['provinces'][0]['figures']['on_time']);
	pg_assert_same(null, $out['carriers'][0]['provinces'][1]['figures']['on_time'], 'a province under 20 parcels has no figures');
	pg_assert_same(array(), $out['carriers'][0]['months'], 'no months in a month view');
	pg_assert_same(false, $out['carriers'][1]['figures']['enough'], 'a carrier under 20 parcels in all');
	pg_assert_same(3, $out['carriers'][1]['figures']['parcels']);
	pg_assert_same(array(array('method_id' => 4, 'name' => 'Standard delivery', 'measured' => 2.6, 'set' => 1)), $out['arrival'], 'the arrival check');
	pg_assert_same('2026-11-20T01:00:00Z', $out['measured_at']);
}

function test_api_delivery_report_year_and_empty()
{
	$months = pg_delivery_scorecard_months('2026-10');
	$state = array();
	pg_delivery_scorecard_add($state, _api_delivery_test_rows('', 'Ankara', '2026-10-05', 2, 2, 3), false);
	$out = api_delivery_report_present(pg_delivery_scorecard_year_view($state, $months), true, $months, array('' => ''), array(), true, 0);

	pg_assert_same('year', $out['period']);
	pg_assert_same('2025-11', $out['from_month']);
	pg_assert_same('2026-10', $out['to_month']);
	pg_assert_same(null, $out['carriers'][0]['carrier'], 'parcels with no known carrier');
	pg_assert_same(12, count($out['carriers'][0]['months']), 'twelve months a carrier');
	pg_assert_same('2026-10', $out['carriers'][0]['months'][11]['month'], 'oldest first');
	pg_assert_same(2, $out['carriers'][0]['months'][11]['figures']['parcels']);
	pg_assert_same(array(), $out['carriers'][0]['provinces'], 'no provinces over twelve months');
	pg_assert_same(null, $out['measured_at'], 'no summary yet');

	$out = api_delivery_report_present(pg_delivery_scorecard_month_view(array(), '2026-10'), false, array('2026-10'), array(), array(), false, 0);
	pg_assert_same(false, $out['shipping']);
	pg_assert_same(0, $out['total']['parcels']);
	pg_assert_same(null, $out['total']['on_time'], 'no parcels, no figures');
	pg_assert_same(array(), $out['carriers']);
}

function _api_delivery_test_problem(array $more = array())
{
	return array_merge(array(
		'id' => 55, 'kind' => 1, 'found_at' => gmmktime(9, 0, 0, 11, 18, 2026), 'closed_at' => 0, 'close_reason' => 0, 'assigned_to' => 3, 'assignee' => 'mehmet', 'note' => 'Called the branch',
		'order_id' => 1042, 'order_number' => '100318', 'order_type' => 'online', 'order_status' => 'complete',
		'billing_first_name' => 'Ayşe', 'billing_last_name' => 'Demir',
		'parcel_id' => 931, 'shipment_id' => 931, 'number' => '7350012345', 'carrier' => 'yurtici', 'status' => 'in_transit', 'tracking_url' => '',
		'shipped_at' => gmmktime(9, 0, 0, 11, 10, 2026), 'delivered_at' => 0, 'created_at' => gmmktime(8, 0, 0, 11, 10, 2026), 'status_at' => 0,
		'state' => ' Ankara ', 'city' => 'Çankaya', 'delivery_date' => '2026-11-13', 'shipping_method_code' => '',
	), $more);
}

function test_api_shipment_problem_present()
{
	$figures = array('yurtici' => array('country' => array('count' => 40, 'median' => 3.0, 'p90' => 5.0, 'median_working' => 2.0, 'p90_working' => 4.0), 'provinces' => array('ankara' => array('count' => 25, 'median' => 2.46, 'p90' => 4.0, 'median_working' => 2.0, 'p90_working' => 3.0))));
	$out = api_shipment_problem_present(_api_delivery_test_problem(), $figures, gmmktime(12, 0, 0, 11, 19, 2026));

	pg_assert_same('stuck', $out['kind']);
	pg_assert_same(true, $out['open']);
	pg_assert_same(null, $out['closed_at']);
	pg_assert_same(null, $out['close_reason'], 'an open problem has no reason');
	pg_assert_same(3, $out['assigned_to']);
	pg_assert_same('mehmet', $out['assignee']);
	pg_assert_same(931, $out['shipment_id']);
	pg_assert_same('Ankara', $out['province'], 'the state, trimmed');
	pg_assert_same(2.5, $out['usual_days'], 'the province\'s median');
	pg_assert_same(9, $out['days_in_transit']);
	pg_assert_same('2026-11-13', $out['estimated_date']);
	pg_assert_same('2026-11-10T09:00:00Z', $out['shipped_at']);

	$text = json_encode($out, JSON_UNESCAPED_UNICODE);
	foreach (array('Ayşe', 'Demir', 'Çankaya') as $private) {
		pg_assert_false(strpos($text, $private) !== false, 'no name and nothing of the address beyond the province: ' . $private);
	}
	pg_assert_same(array_keys(api_shipment_problem_schema()), array_keys($out), 'every field declared, in order');

	$gone = api_shipment_problem_present(_api_delivery_test_problem(array('parcel_id' => null, 'number' => null, 'carrier' => null, 'status' => null, 'state' => '', 'city' => '', 'delivery_date' => '0000-00-00', 'assigned_to' => 0)), $figures, gmmktime(12, 0, 0, 11, 19, 2026));
	pg_assert_same(null, $gone['shipment_id'], 'a removed parcel');
	pg_assert_same(null, $gone['carrier']);
	pg_assert_same(null, $gone['days_in_transit']);
	pg_assert_same(null, $gone['province']);
	pg_assert_same(null, $gone['estimated_date'], 'no estimate given');
	pg_assert_same(null, $gone['assignee'], 'nobody');
}

function test_api_shipment_problem_close_reason()
{
	$closed = array('closed_at' => gmmktime(9, 0, 0, 11, 19, 2026));

	pg_assert_same('refunded', api_shipment_problem_close_reason(_api_delivery_test_problem($closed + array('close_reason' => 3))), 'closed by staff');
	pg_assert_same('other', api_shipment_problem_close_reason(_api_delivery_test_problem($closed + array('close_reason' => 5))));
	pg_assert_same('delivered', api_shipment_problem_close_reason(_api_delivery_test_problem($closed + array('close_reason' => 1, 'status' => 'delivered', 'delivered_at' => 5))), 'settled by a delivery');
	pg_assert_same('scanned', api_shipment_problem_close_reason(_api_delivery_test_problem($closed + array('close_reason' => 1, 'kind' => 2, 'status' => 'in_transit'))), 'a label not handed over was scanned');
	pg_assert_same('status_changed', api_shipment_problem_close_reason(_api_delivery_test_problem($closed + array('close_reason' => 1, 'kind' => 3, 'status' => 'in_transit'))));
	pg_assert_same('order_cancelled', api_shipment_problem_close_reason(_api_delivery_test_problem($closed + array('close_reason' => 1, 'order_status' => 'cancelled'))));
	pg_assert_same('parcel_removed', api_shipment_problem_close_reason(_api_delivery_test_problem($closed + array('close_reason' => 1, 'parcel_id' => null))));
}

function test_api_delivery_routes()
{
	$routes = array();
	foreach (api_schema() as $route) {
		$routes[$route['id']] = $route;
	}

	foreach (array('reports.delivery' => '/reports/delivery', 'shipments.problems' => '/shipments/problems') as $id => $path) {
		pg_assert_true(isset($routes[$id]), $id . ' is declared');
		pg_assert_same($path, $routes[$id]['path']);
		pg_assert_same('GET', $routes[$id]['method']);
		pg_assert_same('orders:read', $routes[$id]['scope'], 'the orders right');
		pg_assert_false(isset($routes[$id]['mcp']) && ($routes[$id]['mcp'] === false), 'offered as an MCP tool by the route rule');
	}
}
