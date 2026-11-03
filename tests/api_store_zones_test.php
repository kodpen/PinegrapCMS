<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the shipping zone and tax zone writes: the link id cleaning of
 * includes/fn/store_zones.php, the partial update of the external API
 * (includes/api/resources/store.php), the route rows, the store:write scope
 * and the webhook events, and the four zone screens writing through the
 * shared functions.
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

require_once(PG_FUNCTIONS_DIR . '/includes/api/resources/store.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/schema.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/scopes.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/outbound/webhooks.php');

function test_store_zone_link_ids_keep_distinct_positive_ids()
{
	pg_assert_same(array(4, 7), pg_store_zone_link_ids(array('4', '', '0', 7, '4')), 'blank, zero and repeated ids are dropped');
	pg_assert_same(array(2), pg_store_zone_link_ids(array(array('1'), '2', '-3', '1.5', 'x')), 'a nested, negative, fractional or text value is skipped');
	pg_assert_same(array(), pg_store_zone_link_ids(null), 'nothing given');
	pg_assert_same(array(), pg_store_zone_link_ids('5'), 'not a list');
}

function test_store_zone_tables_name_the_link_tables()
{
	pg_assert_same('zones_states_xref', pg_store_zone_tables('zone')['states'], 'a shipping zone');
	pg_assert_same('tax_zone_id', pg_store_zone_tables('tax_zone')['key'], 'a tax zone');
}

function test_api_store_zone_save_data_lays_the_sent_fields_over_the_row()
{
	$row = array('id' => '3', 'name' => 'Europe', 'base_rate' => '500', 'primary_weight_rate' => '0', 'secondary_weight_rate' => '0', 'item_rate' => '150');

	$data = api_store_zone_save_data($row, array('item_rate' => 200));
	pg_assert_same('Europe', $data['name'], 'an unsent name stays');
	pg_assert_same(500, $data['base_rate'], 'an unsent rate stays');
	pg_assert_same(200, $data['item_rate'], 'a sent rate changes');

	$renamed = api_store_zone_save_data($row, array('name' => '  EU  ', 'base_rate' => 0));
	pg_assert_same('EU', $renamed['name'], 'a sent name is trimmed');
	pg_assert_same(0, $renamed['base_rate'], 'a sent zero is a change, not an unsent field');
}

function test_api_store_zone_routes_reach_a_handler_under_their_scopes()
{
	$found = array();

	foreach (api_schema() as $route) {
		if ((strpos($route['id'], 'store.zones') !== 0) && (strpos($route['id'], 'store.tax_zones') !== 0)) {
			continue;
		}

		$found[] = $route['id'];

		pg_assert_true(function_exists($route['handler']), $route['id'] . ': ' . $route['handler'] . '() exists');

		$write = ($route['method'] !== 'GET');

		pg_assert_same($write ? 'store:write' : 'orders:read', $route['scope'], $route['id'] . ' scope');

		if ($write) {
			pg_assert_true(!empty($route['dry_run']), $route['id'] . ' takes X-Dry-Run');
		}

		foreach ($route['params'] as $param) {
			if ($param['name'] === 'name') {
				pg_assert_same(50, $param['max_length'], $route['id'] . ': the name fits varchar(50)');
			}
		}
	}

	pg_assert_same(12, count($found), 'list, get, create, update, delete and its POST address, for both kinds');
}

function test_api_store_write_scope_is_granted_and_ceilinged()
{
	$groups = api_scope_groups();
	pg_assert_same('store:write', $groups['store']['write'], 'the group writes store:write');
	pg_assert_same('', $groups['store']['read'], 'reading stays under orders');

	if (defined('ECOMMERCE') && ECOMMERCE === true) {
		$manager = api_owner_scopes(array('id' => 7, 'username' => 'm', 'role' => 2, 'manage_ecommerce' => false, 'manage_contacts' => false, 'manage_forms' => false, 'manage_emails' => 'no'));
		pg_assert_true(in_array('store:write', $manager, true), 'a manager may grant it');

		$user = api_owner_scopes(array('id' => 8, 'username' => 'u', 'role' => 3, 'manage_ecommerce' => false, 'manage_contacts' => false, 'manage_forms' => false, 'manage_emails' => 'no'));
		pg_assert_false(in_array('store:write', $user, true), 'a user without the commerce right may not');
	}
}

function test_store_zone_events_are_in_the_catalogue()
{
	$events = api_webhook_events();

	foreach (array('zone.created', 'zone.updated', 'zone.deleted', 'tax_zone.created', 'tax_zone.updated', 'tax_zone.deleted') as $event) {
		pg_assert_true(isset($events[$event]), $event . ' is listed, so pg_announce() does not drop it');
	}
}

function test_store_zone_screens_write_through_the_shared_functions()
{
	$expect = array(
		'add_zone.php'      => array('pg_store_zone_save('),
		'edit_zone.php'     => array('pg_store_zone_save(', 'pg_store_zone_delete('),
		'add_tax_zone.php'  => array('pg_store_tax_zone_save('),
		'edit_tax_zone.php' => array('pg_store_tax_zone_save(', 'pg_store_tax_zone_delete('),
	);

	foreach ($expect as $file => $calls) {
		$source = file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);

		foreach ($calls as $call) {
			pg_assert_contains($call, $source, $file . ' calls ' . $call . ')');
		}

		pg_assert_false(strpos($source, 'INSERT INTO zones') !== false || strpos($source, 'INSERT INTO tax_zones') !== false, $file . ' writes no zone row itself');
	}
}
