<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the operator's side of stock per location
 * (includes/fn/stock_moves.php): the history's filters and their condition,
 * the transfer check, the save plan of the Stock locations card and the
 * places the screens are wired in.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/list_table.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/stock_moves.php');

function stock_moves_test_locations()
{
	return array(
		array('id' => 7, 'name' => 'Warehouse', 'address' => 'Dock 1', 'enabled' => 1, 'is_default' => 1, 'sort_order' => 1),
		array('id' => 3, 'name' => 'Shop', 'address' => '', 'enabled' => 1, 'is_default' => 0, 'sort_order' => 2),
		array('id' => 9, 'name' => 'Old shop', 'address' => '', 'enabled' => 0, 'is_default' => 0, 'sort_order' => 3),
	);
}

function stock_moves_test_posted(array $changes = array())
{
	return array_replace(array(
		'name' => array(7 => 'Warehouse', 3 => 'Shop', 9 => 'Old shop'),
		'address' => array(7 => 'Dock 1', 3 => '', 9 => ''),
		'order' => array(7 => '1', 3 => '2', 9 => '3'),
		'enabled' => array(7 => '1', 3 => '1'),
		'default' => '7',
		'new_name' => '',
		'new_address' => '',
	), $changes);
}

function test_stock_moves_filters_drop_what_is_not_known()
{
	$filters = pg_stock_move_filters(array(
		'product_id' => '12',
		'location_id' => '5',
		'reason' => 'stolen',
		'from' => '2026-02-30',
		'to' => "2026-11-01' OR 1=1",
	), array(7, 3));

	pg_assert_same(array('product_id' => 12, 'location_id' => 0, 'reason' => '', 'from' => '', 'to' => ''), $filters, 'an unknown location, reason and bad dates filter nothing');

	$filters = pg_stock_move_filters(array('product_id' => '-4', 'location_id' => '3', 'reason' => 'transfer', 'from' => '2026-11-03', 'to' => '2026-11-01'), array(7, 3));

	pg_assert_same(0, $filters['product_id'], 'a negative product id is no filter');
	pg_assert_same(3, $filters['location_id'], 'a known location is kept');
	pg_assert_same('transfer', $filters['reason'], 'a known reason is kept');
	pg_assert_same(array('2026-11-01', '2026-11-03'), array($filters['from'], $filters['to']), 'a period given backwards is turned round');
}

function test_stock_moves_condition_binds_every_value()
{
	$zone = date_default_timezone_get();
	date_default_timezone_set('UTC');

	$condition = pg_stock_move_condition(array('product_id' => 12, 'location_id' => 3, 'reason' => 'transfer', 'from' => '2026-11-01', 'to' => '2026-11-01'));

	date_default_timezone_set($zone);

	pg_assert_same('stock_moves.product_id = ? AND stock_moves.location_id = ? AND stock_moves.reason = ? AND stock_moves.created_at >= ? AND stock_moves.created_at < ?', $condition['sql'], 'one placeholder per filter');
	pg_assert_same(array(12, 3, 'transfer', 1793491200, 1793577600), $condition['params'], 'the period covers the whole of the last day');

	$condition = pg_stock_move_condition(pg_stock_move_filters(array(), array()));

	pg_assert_same(array('sql' => '1 = 1', 'params' => array()), $condition, 'no filter lists every move');
}

function test_stock_moves_product_reference_reads_sku_or_id()
{
	pg_assert_same(array('id' => 42), pg_stock_product_reference(' #42 '), '#id names a product by id');
	pg_assert_same(array('sku' => 'TSHIRT-RED'), pg_stock_product_reference('TSHIRT-RED'), 'anything else is the SKU');
	pg_assert_same(array('sku' => '42'), pg_stock_product_reference('42'), 'a SKU of digits stays a SKU');
	pg_assert_same(array(), pg_stock_product_reference('  '), 'blank names nothing');
	pg_assert_same(array(), pg_stock_product_reference(array('x')), 'a list names nothing');
}

function test_stock_moves_transfer_check()
{
	$locations = stock_moves_test_locations();

	pg_assert_true(pg_stock_transfer_check('7', '3', '5', $locations)['ok'], 'warehouse to shop');

	$cases = array(
		array(7, 3, 0, 'quantity'),
		array(7, 3, '1.5', 'quantity'),
		array(7, 3, -2, 'quantity'),
		array(4, 3, 1, 'from'),
		array(7, 4, 1, 'to'),
		array(7, 7, 1, 'same'),
		array(7, 9, 1, 'to_disabled'),
	);

	foreach ($cases as $case) {
		$check = pg_stock_transfer_check($case[0], $case[1], $case[2], $locations);
		pg_assert_false($check['ok'], 'refused: ' . $case[3]);
		pg_assert_same($case[3], $check['error'], 'the reason of ' . json_encode($case));
	}

	pg_assert_true(pg_stock_transfer_check(9, 7, 2, $locations)['ok'], 'units may leave a location that is turned off');
}

function test_stock_moves_transfer_ref_pairs_both_moves()
{
	$ref = pg_stock_transfer_ref(12, 7, 3, 1762041600);

	pg_assert_same('T1762041600-12-7-3', $ref, 'time, product, from, to');
	pg_assert_true(strlen(pg_stock_transfer_ref(PHP_INT_MAX, PHP_INT_MAX, PHP_INT_MAX, PHP_INT_MAX)) <= PG_STOCK_REF_LENGTH, 'never longer than the column');
}

function test_stock_moves_save_plan_writes_only_changes()
{
	$locations = stock_moves_test_locations();
	$plan = pg_stock_locations_save_plan(stock_moves_test_posted(), $locations);

	pg_assert_same(array('errors' => array(), 'updates' => array(), 'default' => 0, 'add' => null), $plan, 'an unchanged card changes nothing');

	$plan = pg_stock_locations_save_plan(stock_moves_test_posted(array(
		'name' => array(7 => ' Main warehouse ', 3 => 'Shop', 9 => 'Old shop'),
		'enabled' => array(7 => '1', 3 => '1', 9 => '1'),
		'order' => array(7 => '1', 3 => 'x', 9 => '3'),
		'default' => '3',
		'new_name' => 'Consignment',
		'new_address' => ' Partner street 4 ',
	)), $locations);

	pg_assert_same(array(), $plan['errors'], 'nothing refused');
	pg_assert_same(array(
		7 => array('name' => 'Main warehouse', 'address' => 'Dock 1', 'enabled' => 1, 'sort_order' => 1),
		9 => array('name' => 'Old shop', 'address' => '', 'enabled' => 1, 'sort_order' => 3),
	), $plan['updates'], 'the renamed and the switched-on location; a bad order is kept as it was');
	pg_assert_same(3, $plan['default'], 'the shop becomes the default');
	pg_assert_same(array('name' => 'Consignment', 'address' => 'Partner street 4'), $plan['add'], 'the new location');
}

function test_stock_moves_save_plan_refuses()
{
	$locations = stock_moves_test_locations();

	$plan = pg_stock_locations_save_plan(stock_moves_test_posted(array('enabled' => array(3 => '1'))), $locations);
	pg_assert_same(array('stock_location_enabled[7]' => 'default_disabled'), $plan['errors'], 'the default stays on');

	$plan = pg_stock_locations_save_plan(stock_moves_test_posted(array('enabled' => array(7 => '1'), 'default' => '3')), $locations);
	pg_assert_same(array('stock_location_enabled[3]' => 'default_disabled'), $plan['errors'], 'a new default cannot be turned off in the same save');

	$plan = pg_stock_locations_save_plan(stock_moves_test_posted(array(
		'name' => array(7 => '', 3 => str_repeat('a', PG_STOCK_NAME_LENGTH + 1), 9 => 'Old shop'),
		'new_name' => str_repeat('b', PG_STOCK_NAME_LENGTH + 1),
	)), $locations);
	pg_assert_same(array('stock_location_name[7]', 'stock_location_name[3]', 'stock_location_new_name'), array_keys($plan['errors']), 'empty and long names are refused');

	$plan = pg_stock_locations_save_plan(stock_moves_test_posted(array('default' => '99', 'name' => array(7 => 'Warehouse', 55 => 'Forged'))), $locations);
	pg_assert_same(0, $plan['default'], 'an unknown default keeps the current one');
	pg_assert_same(array(), $plan['updates'], 'an unknown id is not written, a row not posted is left alone');
}

function test_stock_moves_reason_labels_cover_every_reason()
{
	pg_assert_same(pg_stock_reasons(), array_keys(pg_stock_move_reason_labels()), 'one label per reason, in order');
}

function test_stock_moves_wired_into_the_screens()
{
	$definition = pg_list_table_definition('view_stock_moves');

	pg_assert_same(array('file' => 'includes/fn/stock_moves.php', 'source' => 'pg_stock_move_list_source'), $definition, 'the rows action knows the list');
	pg_assert_same(null, pg_stock_move_list_source(array('role' => 3, 'manage_ecommerce' => 0), array()), 'a user without e-commerce rights is refused');

	$registry = file_get_contents(PG_FUNCTIONS_DIR . '/includes/settings/registry.php');
	$card = file_get_contents(PG_FUNCTIONS_DIR . '/includes/settings/commerce.php');

	pg_assert_true(strpos($registry, "'pgset-stock-locations' => lang('Stock locations')") !== false, 'the card is a section of Ecommerce');
	pg_assert_true(strpos($card, 'id="pgset-stock-locations"') !== false, 'the card is drawn');
	pg_assert_same(substr_count($card, '<div'), substr_count($card, '</div>'), 'the Ecommerce cards close every div');

	$screen = file_get_contents(PG_FUNCTIONS_DIR . '/view_stock_moves.php');

	pg_assert_true(strpos($screen, 'validate_token_field()') !== false, 'the transfer checks its token');
	pg_assert_true(strpos($screen, 'get_token_field()') !== false, 'the transfer form carries its token');
}
