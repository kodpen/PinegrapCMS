<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for what the delivery tower tells (includes/fn/delivery_tower_tell.php):
 * the shipping alert codes and their sentence, the counts a walk gathers
 * across ticks, the payload of shipment.problem and
 * shipment.problem_resolved, and the places the two events are named in
 * (webhook catalogue, Workspace, flow start box).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/api/outbound/webhooks.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/outbound/webhook_payloads.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/app_access.php');

function test_delivery_tower_tell_alert_codes()
{
	$codes = pg_ops_alert_codes();
	foreach (array('parcel_stuck', 'parcel_not_handed_over', 'parcel_problem') as $code) {
		pg_assert_true(isset($codes[$code]), $code . ' is an alert code');
		pg_assert_same('shipping', $codes[$code]['group'], $code . ' is a shipping alert');
	}

	pg_assert_same(array(
		'stuck' => 'parcel_stuck',
		'not_handed_over' => 'parcel_not_handed_over',
		'problem' => 'parcel_problem',
		'returned' => 'parcel_problem',
	), pg_delivery_tower_alert_codes(), 'a returned parcel is told with the delivery problems');
}

function test_delivery_tower_tell_counts_across_ticks()
{
	$told = array();
	pg_delivery_tower_told_add($told, 'stuck', '1001');
	pg_delivery_tower_told_add($told, 'stuck', '1002');
	pg_delivery_tower_told_add($told, 'returned', '1003');
	pg_delivery_tower_told_add($told, 'problem', '1003');

	// Kept as JSON in the pass record between ticks.
	$told = json_decode(json_encode($told), true);
	for ($i = 4; $i <= 9; $i++) {
		pg_delivery_tower_told_add($told, 'stuck', '100' . $i);
	}

	pg_assert_same(8, $told['parcel_stuck']['count'], 'every stuck parcel counted');
	pg_assert_same(PG_DELIVERY_TOWER_ALERT_NAMED, count($told['parcel_stuck']['orders']), 'only the first few orders kept');
	pg_assert_same(2, $told['parcel_problem']['count'], 'a problem and a return');
	pg_assert_same(array('1003'), $told['parcel_problem']['orders'], 'an order is named once');
}

function test_delivery_tower_tell_alert_detail()
{
	$detail = pg_delivery_tower_alert_detail('parcel_stuck', array('count' => 7, 'orders' => array('1001', '1002', '1003', '1004', '1005')));
	pg_assert_contains('7', $detail, 'the count');
	pg_assert_contains('1001, 1002, 1003, 1004, 1005', $detail, 'the first orders');
	pg_assert_contains('2', $detail, 'and the rest counted');

	$detail = pg_delivery_tower_alert_detail('parcel_problem', array('count' => 1, 'orders' => array('1001')));
	pg_assert_contains('1001', $detail);
	pg_assert_true(strpos($detail, 'more') === false, 'nothing more to count');
}

function _dt_tell_test_parcel()
{
	return array(
		'id' => 31, 'order_id' => 1042, 'order_number' => '100318', 'order_type' => 'marketplace', 'ship_to_id' => 1290,
		'number' => '7350012345', 'carrier' => 'yurtici', 'state' => 'Ankara', 'city' => 'Çankaya', 'status' => 'in_transit',
		'shipped_at' => 1763370000, 'delivered_at' => 0,
		// Never in a payload.
		'address_1' => 'Atatürk Bulvarı 1',
	);
}

function test_delivery_tower_tell_payload()
{
	$figures = array('yurtici' => array('country' => array('count' => 40, 'median' => 3, 'p90' => 5, 'median_working' => 2, 'p90_working' => 4), 'provinces' => array(
		pg_delivery_tower_province_key('Ankara', '') => array('count' => 20, 'median' => 2, 'p90' => 3, 'median_working' => 2, 'p90_working' => 3),
	)));
	$now = 1763370000 + 9 * 86400;

	$payload = pg_delivery_tower_event_payload(_dt_tell_test_parcel(), 'stuck', 55, $figures, $now, 'shipment.problem');
	pg_assert_same(1042, $payload['id'], 'the order, as every order event names it');
	pg_assert_same('100318', $payload['order_number']);
	pg_assert_same('marketplace', $payload['order_type'], 'a flow can leave a marketplace buyer out');
	pg_assert_same(55, $payload['problem_id']);
	pg_assert_same(31, $payload['shipment_id']);
	pg_assert_same('7350012345', $payload['number']);
	pg_assert_same('stuck', $payload['kind']);
	pg_assert_same('Ankara', $payload['province'], 'the province, never the street');
	pg_assert_same(2.0, $payload['usual_days'], 'the province figure\'s median');
	pg_assert_true($payload['days_in_transit'] >= 9, 'days since it was shipped');
	pg_assert_true(!isset($payload['reason']), 'a new problem has no reason');
	pg_assert_true(strpos(json_encode($payload), 'Atat') === false, 'no street address');

	$resolved = pg_delivery_tower_event_payload(_dt_tell_test_parcel(), 'stuck', 55, $figures, $now, 'shipment.problem_resolved', 'delivered');
	pg_assert_same('delivered', $resolved['reason']);

	// No figure for the carrier, never shipped, no province.
	$parcel = array_merge(_dt_tell_test_parcel(), array('carrier' => 'aras', 'shipped_at' => 0, 'state' => '', 'city' => '', 'order_type' => 'web'));
	$payload = pg_delivery_tower_event_payload($parcel, 'not_handed_over', 56, $figures, $now, 'shipment.problem');
	pg_assert_same(null, $payload['usual_days']);
	pg_assert_same(null, $payload['days_in_transit']);
	pg_assert_same(null, $payload['province']);
	pg_assert_same('web', $payload['order_type']);
}

function test_delivery_tower_tell_events_are_named()
{
	$events = api_webhook_core_events();
	$payloads = api_webhook_event_payloads();
	foreach (array('shipment.problem', 'shipment.problem_resolved') as $event) {
		pg_assert_true(isset($events[$event]), $event . ' in the webhook catalogue');
		pg_assert_true(isset($payloads[$event]), $event . ' has its fields and sample');
		pg_assert_true(in_array($event, pg_event_workspace_events(), true), $event . ' kept for the Workspace');
		pg_assert_same(lang('Orders'), api_app_event_area($event), $event . ' under Orders');
	}
	pg_assert_contains('not announced', $events['shipment.problem_resolved'], 'says what is left out');
}

function test_delivery_tower_tell_flow_start()
{
	require_once(PG_FUNCTIONS_DIR . '/includes/workspace/watch.php');
	require_once(PG_FUNCTIONS_DIR . '/includes/workspace/flow_anchors.php');

	pg_assert_same(array('order', 1042), ws_events_record_of('shipment.problem', array('id' => 1042)));
	pg_assert_same(array('order', 1042), ws_events_record_of('shipment.problem_resolved', array('id' => 1042)));
	pg_assert_same('returned', ws_events_filter_value('shipment.problem', array('id' => 1, 'kind' => 'returned')), 'the kind filter');
	pg_assert_same(array_keys(pg_delivery_tower_kinds()), array_keys(ws_events_problem_kinds()), 'one value per kind');

	$outputs = ws_flow_event_outputs();
	pg_assert_true(isset($outputs['shipment.problem'], $outputs['shipment.problem_resolved']), 'the start box gives its values');
}
