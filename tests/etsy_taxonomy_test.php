<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for Etsy's seller taxonomy (includes/fn/etsy.php and the marketplace
 * connector includes/api/outbound/connectors/etsy.php): the nested nodes
 * flattened into the category cache and a node's properties as the
 * attribute rows of the listings screen.
 *
 * Etsy is never called. The answers in tests/fixtures/etsy/ follow the
 * response shapes of Etsy's Open API v3 documentation (November 2026) and
 * have not been recorded from a live shop.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_INIT_LOADED')) {
	define('PG_INIT_LOADED', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/api/outbound/connectors/base.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/outbound/connectors/etsy.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/etsy.php');

function etsy_taxonomy_test_fixture($name)
{
	return json_decode((string) file_get_contents(dirname(__FILE__) . '/fixtures/etsy/' . $name), true);
}

// A call answering each "METHOD path" from $routes (=> array(status,
// fixture)), writing each one and its options into $calls; an unknown one
// is 404.
function etsy_taxonomy_test_call($routes, &$calls)
{
	return function ($method, $path, $options = array()) use ($routes, &$calls) {
		$key = $method . ' ' . $path;
		$calls[] = array($key, $options);

		if (!isset($routes[$key])) {
			return array('ok' => false, 'status' => 404, 'data' => array(), 'error' => '');
		}

		$status = $routes[$key][0];

		return array('ok' => ($status >= 200 && $status < 300), 'status' => $status,
			'data' => etsy_taxonomy_test_fixture($routes[$key][1]), 'error' => '');
	};
}

function etsy_taxonomy_test_account()
{
	return array('id' => 9, 'provider' => 'etsy', 'credentials' => array('shop' => '87654321'));
}

/* ------------------------------------------------------------- the tree */

function test_etsy_taxonomy_rows_flatten_the_nested_nodes_in_order()
{
	$rows = pg_etsy_taxonomy_rows(etsy_taxonomy_test_fixture('taxonomy-nodes.json'));

	pg_assert_same(array('1', '2', '3', '4', '69'), array_column($rows, 'remote_id'), 'depth first in Etsy\'s order, the broken id left out');
	pg_assert_same(array('', '1', '2', '2', ''), array_column($rows, 'parent_id'));
	pg_assert_same('Accessories > Belts & Suspenders > Belts', $rows[3]['path']);
	pg_assert_same(array(0, 0, 1, 1, 1), array_column($rows, 'is_leaf'), 'a node without children is a leaf');
}

function test_etsy_taxonomy_rows_of_an_answer_that_is_not_a_tree_are_empty()
{
	pg_assert_same(array(), pg_etsy_taxonomy_rows(null));
	pg_assert_same(array(), pg_etsy_taxonomy_rows(array('error' => 'x')));
	pg_assert_same(array(), pg_etsy_taxonomy_rows(array('results' => 'x')));
}

function test_etsy_category_tree_reads_the_seller_taxonomy()
{
	$calls = array();
	$call = etsy_taxonomy_test_call(array('GET /seller-taxonomy/nodes' => array(200, 'taxonomy-nodes.json')), $calls);

	$result = mp_etsy_category_tree(etsy_taxonomy_test_account(), $call);

	pg_assert_true($result['ok']);
	pg_assert_same(5, count($result['data']['categories']));
	pg_assert_same(1, count($calls));
	pg_assert_true($calls[0][1]['max_body'] > 1048576, 'the whole tree is kept, not the default megabyte');
}

function test_etsy_category_tree_refuses_an_empty_tree_and_passes_an_error_on()
{
	$calls = array();
	$call = etsy_taxonomy_test_call(array('GET /seller-taxonomy/nodes' => array(200, 'shipping-profiles-empty.json')), $calls);

	pg_assert_false(mp_etsy_category_tree(etsy_taxonomy_test_account(), $call)['ok'], 'an empty tree never empties the cache');

	$call = etsy_taxonomy_test_call(array('GET /seller-taxonomy/nodes' => array(403, 'error-forbidden.json')), $calls);
	$result = mp_etsy_category_tree(etsy_taxonomy_test_account(), $call);

	pg_assert_false($result['ok']);
	pg_assert_contains('Etsy: ', $result['error']);
}

/* ----------------------------------------------------------- properties */

function test_etsy_property_rows_become_attribute_rows()
{
	$rows = pg_etsy_property_rows(etsy_taxonomy_test_fixture('taxonomy-properties.json'));

	pg_assert_same(array('200', '100', '46803063641'), array_column($rows, 'attribute_id'), 'neither attribute nor variation, and a broken id, are left out');
	pg_assert_same(array(0, 1, 2), array_column($rows, 'sort_order'));

	pg_assert_same('Primary color', $rows[0]['name']);
	pg_assert_same(1, $rows[0]['mandatory']);
	pg_assert_same(1, $rows[0]['is_variant']);
	pg_assert_same(0, $rows[0]['custom_allowed'], 'a property with values is a closed choice');
	pg_assert_same(array(array('id' => '1', 'value' => 'Beige'), array('id' => '2', 'value' => 'Black')), $rows[0]['values'], 'a repeated value once');

	pg_assert_same(0, $rows[1]['mandatory']);
	pg_assert_same(array('8 (US)', '8 (UK)'), array_column($rows[1]['values'], 'value'), 'two scales are told apart');

	pg_assert_same('Holiday', $rows[2]['name'], 'the name stands in for an empty display name');
	pg_assert_same(1, $rows[2]['custom_allowed'], 'no values: the shop\'s own text');
	pg_assert_same(0, $rows[2]['is_variant']);
}

function test_etsy_category_attributes_ask_for_the_node_s_properties()
{
	$calls = array();
	$call = etsy_taxonomy_test_call(array('GET /seller-taxonomy/nodes/4/properties' => array(200, 'taxonomy-properties.json')), $calls);

	$result = mp_etsy_category_attributes(etsy_taxonomy_test_account(), ' 4 ', $call);

	pg_assert_true($result['ok']);
	pg_assert_same(3, count($result['data']['attributes']));
	pg_assert_same('GET /seller-taxonomy/nodes/4/properties', $calls[0][0]);
}

function test_etsy_category_attributes_never_send_an_id_that_is_not_a_number()
{
	$calls = array();
	$call = etsy_taxonomy_test_call(array(), $calls);

	pg_assert_false(mp_etsy_category_attributes(etsy_taxonomy_test_account(), '4/../x', $call)['ok']);
	pg_assert_false(mp_etsy_category_attributes(etsy_taxonomy_test_account(), '', $call)['ok']);
	pg_assert_same(array(), $calls);
}

function test_etsy_connector_offers_the_taxonomy_under_the_provider_s_key()
{
	pg_assert_true(mp_can('etsy', 'category_tree'));
	pg_assert_true(mp_can('etsy', 'category_attributes'));
	pg_assert_same('etsy', mp_category_key(etsy_taxonomy_test_account()), 'one taxonomy for every shop');
}
