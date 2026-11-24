<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the pick and pack routes (includes/api/resources/pick_waves.php):
 * GET /pick-waves and /pick-waves/{id}, GET /orders/{id}/packs and
 * POST /products/{id}/shelf: their route rows, a wave's fields and status
 * words, its pick list with the ticked lines last, an address's pack from
 * waiting to packed, and the shelf answer.
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

require_once(PG_FUNCTIONS_DIR . '/includes/api/scopes.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/schema.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/resources/pick_waves.php');

// The presenter's helpers live in includes/api/response.php, which loads only
// behind the API's own entry. A stand-in of the same shape.
if (!function_exists('api_time')) {
	function api_time($unix)
	{
		$unix = (int) $unix;

		return ($unix <= 0) ? null : gmdate('Y-m-d\TH:i:s\Z', $unix);
	}
}

function _api_pick_route($id)
{
	foreach (api_schema() as $route) {
		if ($route['id'] === $id) {
			return $route;
		}
	}

	return null;
}

function test_api_pick_routes_are_declared()
{
	$list = _api_pick_route('pick_waves.list');
	pg_assert_same('GET', $list['method']);
	pg_assert_same('/pick-waves', $list['path']);
	pg_assert_same('orders:read', $list['scope']);
	pg_assert_same(array('list' => 'PickWave'), $list['returns']);

	$get = _api_pick_route('pick_waves.get');
	pg_assert_same('/pick-waves/{id}', $get['path']);
	pg_assert_same('PickWaveDetail', $get['returns']);

	$packs = _api_pick_route('orders.packs');
	pg_assert_same('/orders/{id}/packs', $packs['path']);
	pg_assert_same('orders:read', $packs['scope']);

	$shelf = _api_pick_route('products.shelf');
	pg_assert_same('POST', $shelf['method'], 'IIS refuses PUT on some hosts: POST is the method');
	pg_assert_same(array('PUT'), $shelf['also_accepts']);
	pg_assert_same('inventory:write', $shelf['scope'], 'the scope that writes a shelf with stock');
	pg_assert_true(!empty($shelf['dry_run']), 'the shelf write can be rehearsed');

	foreach (array($list, $get, $packs) as $route) {
		pg_assert_true(strlen($route['summary'] . ' ' . $route['description']) <= 600, $route['id'] . ': the MCP description fits its budget');
	}
}

function test_api_pick_wave_present()
{
	$row = array(
		'id' => 12, 'name' => '', 'status' => PG_WAVE_PICKED, 'location_id' => 0, 'single_item' => 1,
		'created_by' => 3, 'created_by_name' => 'ayse', 'created_at' => 1764000000, 'closed_at' => 0,
		'addresses' => '4', 'units' => '9', 'packed' => '1',
	);
	$out = api_pick_wave_present($row);

	pg_assert_same(12, $out['id']);
	pg_assert_same(null, $out['name'], 'a wave with no name of its own');
	pg_assert_same('picked', $out['status']);
	pg_assert_same(null, $out['location_id'], 'location 0 is any');
	pg_assert_same(true, $out['single_item']);
	pg_assert_same(null, $out['closed_at']);
	pg_assert_same(4, $out['addresses']);
	pg_assert_same(9, $out['units']);
	pg_assert_same(array_keys(api_pick_wave_schema()), array_keys($out), 'every field declared, in order');

	$row['status'] = PG_WAVE_CLOSED;
	$row['name'] = 'Morning';
	$out = api_pick_wave_present($row);
	pg_assert_same('closed', $out['status']);
	pg_assert_same('Morning', $out['name']);
}

function test_api_pick_wave_detail_lines_and_orders()
{
	$lines = pg_pick_list_totals(array(
		array('product_id' => 5, 'location_id' => 0, 'ship_to_id' => 1, 'quantity' => 2, 'bin' => 'A-10', 'code' => 'TS-M', 'name' => 'T-shirt', 'options' => array('Size: M')),
		array('product_id' => 6, 'location_id' => 0, 'ship_to_id' => 2, 'quantity' => 1, 'bin' => 'A-3', 'code' => 'CAP', 'name' => 'Cap', 'options' => array()),
		array('product_id' => 5, 'location_id' => 0, 'ship_to_id' => 2, 'quantity' => 1, 'bin' => 'A-10', 'code' => 'TS-M', 'name' => 'T-shirt', 'options' => array('Size: M')),
	));
	$ticked = pg_pick_list_ticked($lines, array('6:0'));
	$addresses = array(
		array('ship_to_id' => 1, 'order_id' => 40, 'order_number' => '1040', 'ship_by' => mktime(0, 0, 0, 11, 24, 2026), 'removed_at' => 0, 'units' => 2, 'pack_status' => -1),
		array('ship_to_id' => 2, 'order_id' => 41, 'order_number' => '1041', 'ship_by' => 0, 'removed_at' => 0, 'units' => 2, 'pack_status' => PG_PACK_PACKED),
		array('ship_to_id' => 3, 'order_id' => 42, 'order_number' => '', 'ship_by' => 0, 'removed_at' => 1764000000, 'units' => 1, 'pack_status' => PG_PACK_OPEN),
	);
	$row = array('id' => 1, 'name' => '', 'status' => PG_WAVE_OPEN, 'location_id' => 0, 'single_item' => 0,
		'created_by' => 0, 'created_by_name' => '', 'created_at' => 1764000000, 'closed_at' => 0, 'addresses' => 2, 'units' => 4, 'packed' => 1);

	$out = api_pick_wave_detail_present($row, $ticked, $addresses);

	pg_assert_same(array_keys(api_pick_wave_detail_schema()), array_keys($out), 'every field declared, in order');
	pg_assert_same(1, $out['picked_lines']);
	pg_assert_same(2, $out['total_lines']);
	pg_assert_same(5, $out['lines'][0]['product_id'], 'the line still to pick comes first');
	pg_assert_same('A-10', $out['lines'][0]['shelf']);
	pg_assert_same(3, $out['lines'][0]['units']);
	pg_assert_same(2, $out['lines'][0]['addresses']);
	pg_assert_same(array('Size: M'), $out['lines'][0]['options']);
	pg_assert_same(false, $out['lines'][0]['picked']);
	pg_assert_same(true, $out['lines'][1]['picked'], 'a ticked line sinks');
	pg_assert_same(array_keys(api_pick_list_line_schema()), array_keys($out['lines'][0]));

	pg_assert_same('2026-11-24', $out['orders'][0]['ship_by']);
	pg_assert_same('waiting', $out['orders'][0]['pack_status'], 'no pack row yet');
	pg_assert_same('packed', $out['orders'][1]['pack_status']);
	pg_assert_same(null, $out['orders'][1]['ship_by']);
	pg_assert_same(null, $out['orders'][2]['order_number']);
	pg_assert_true($out['orders'][2]['removed_at'] !== null, 'a cancelled order keeps its row with removed_at');
	pg_assert_same(array_keys(api_pick_wave_order_schema()), array_keys($out['orders'][0]));
}

function test_api_order_pack_present()
{
	$lines = pg_pack_lines_build(array(
		array('product_id' => 5, 'quantity' => 2, 'name' => 'T-shirt', 'code' => 'TS-M', 'options' => array('Size: M')),
		array('product_id' => 0, 'quantity' => 1, 'name' => 'Gone', 'code' => 'Gone'),
	), array(array('product_id' => 5, 'quantity' => 2, 'scanned' => 1)));

	$waiting = array('id' => 0, 'order_id' => 40, 'ship_to_id' => 7, 'wave_id' => 3, 'status' => PG_PACK_OPEN, 'packer_id' => 0,
		'started_at' => 0, 'packed_at' => 0, 'units' => 3, 'scanned' => 1, 'refused' => 0, 'lines' => $lines);
	$out = api_order_pack_present($waiting);

	pg_assert_same(array_keys(api_order_pack_schema()), array_keys($out), 'every field declared, in order');
	pg_assert_same('waiting', $out['status'], 'no row: no scan has started it');
	pg_assert_same(3, $out['wave_id']);
	pg_assert_same(null, $out['packer_id']);
	pg_assert_same(null, $out['short_at'], 'a pack not started has no short_at');
	pg_assert_same(1, $out['lines'][0]['scanned']);
	pg_assert_same(2, $out['lines'][0]['units']);
	pg_assert_same(null, $out['lines'][1]['product_id'], 'a deleted product');
	pg_assert_same(array_keys(api_order_pack_line_schema()), array_keys($out['lines'][0]));
	pg_assert_same(array(array('product_id' => null, 'units' => 1), array('product_id' => 5, 'units' => 1)), $out['missing']);

	$packed = array_merge($waiting, array('id' => 9, 'status' => PG_PACK_PACKED, 'packer_id' => 4, 'started_at' => 1764000000, 'packed_at' => 1764000600, 'short_at' => 0, 'wave_id' => 0));
	$out = api_order_pack_present($packed);
	pg_assert_same('packed', $out['status']);
	pg_assert_same(4, $out['packer_id']);
	pg_assert_same(null, $out['wave_id'], 'wave 0 is none');

	pg_assert_same('short', api_pick_pack_status_name(PG_PACK_SHORT));
	pg_assert_same('open', api_pick_pack_status_name(PG_PACK_OPEN));
	pg_assert_same('waiting', api_pick_pack_status_name(null));
}

function test_api_product_shelf_present()
{
	pg_assert_same(array('product_id' => 5, 'location' => 2, 'shelf' => 'A-03-2'), api_product_shelf_present(5, 2, 'A-03-2'));
	pg_assert_same(null, api_product_shelf_present(5, 0, '')['shelf'], 'a removed address reads null');
	pg_assert_same(array_keys(api_product_shelf_schema()), array_keys(api_product_shelf_present(1, 0, 'X')));
}
