<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for eBay's categories and item specifics (includes/fn/ebay.php and
 * the marketplace connector includes/api/outbound/connectors/ebay.php): the
 * cache key a site's tree is kept under, the category tree read from the
 * Taxonomy API and a category's aspects as the attribute rows of the
 * listings screen.
 *
 * eBay is never called. The answers in tests/fixtures/ebay/ follow the
 * response shapes of eBay's public Taxonomy API documentation (November
 * 2026) and have not been recorded from a live account.
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
require_once(PG_FUNCTIONS_DIR . '/includes/api/outbound/connectors/ebay.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/ebay.php');

function ebay_categories_test_fixture($name)
{
	return json_decode((string) file_get_contents(dirname(__FILE__) . '/fixtures/ebay/' . $name), true);
}

// A call answering each path from $routes (path => array(status, fixture))
// and writing each path it was given into $calls; an unknown path is 404.
function ebay_categories_test_call($routes, &$calls)
{
	return function ($path) use ($routes, &$calls) {
		$calls[] = $path;

		if (!isset($routes[$path])) {
			return array('ok' => false, 'status' => 404, 'data' => array(), 'error' => '');
		}

		$status = $routes[$path][0];

		return array('ok' => ($status >= 200 && $status < 300), 'status' => $status,
			'data' => ebay_categories_test_fixture($routes[$path][1]), 'error' => '');
	};
}

function ebay_categories_test_account($site = 'EBAY_DE', $environment = 'production')
{
	return array('id' => 7, 'provider' => 'ebay', 'credentials' => array('environment' => $environment, 'site' => $site));
}

function ebay_categories_test_routes()
{
	return array(
		'/commerce/taxonomy/v1/get_default_category_tree_id?marketplace_id=EBAY_DE' => array(200, 'default-category-tree-id.json'),
		'/commerce/taxonomy/v1/category_tree/77' => array(200, 'category-tree.json'),
		'/commerce/taxonomy/v1/category_tree/77/get_item_aspects_for_category?category_id=63861' => array(200, 'item-aspects.json'),
	);
}

/* ------------------------------------------------------------- cache key */

function test_ebay_category_key_carries_the_environment_and_the_site()
{
	pg_assert_same('ebay:EBAY_DE', pg_ebay_category_key('production', 'EBAY_DE'));
	pg_assert_same('ebay_sandbox:EBAY_DE', pg_ebay_category_key('sandbox', 'EBAY_DE'));

	foreach (array_keys(pg_ebay_sites()) as $site) {
		pg_assert_true(strlen(pg_ebay_category_key('sandbox', $site)) <= 30, 'fits the cache\'s provider column: ' . $site);
	}
}

function test_ebay_account_keys_its_categories_by_its_site()
{
	pg_assert_same('ebay:EBAY_DE', mp_category_key(ebay_categories_test_account('EBAY_DE')));
	pg_assert_same('ebay_sandbox:EBAY_GB', mp_category_key(ebay_categories_test_account('EBAY_GB', 'sandbox')));
	pg_assert_same('ebay:EBAY_US', mp_category_key(ebay_categories_test_account('nowhere')), 'an unknown site is the US one');
	pg_assert_true(mp_can('ebay', 'category_tree'));
	pg_assert_true(mp_can('ebay', 'category_attributes'));
}

function test_other_marketplaces_keep_the_provider_as_their_key()
{
	pg_assert_same('trendyol', mp_category_key(array('id' => 1, 'provider' => 'trendyol', 'credentials' => array())));
	pg_assert_same('n11', mp_category_key(array('id' => 2, 'provider' => 'n11')));
}

/* ------------------------------------------------------------- the tree */

function test_ebay_category_tree_is_flattened_without_its_root()
{
	$rows = pg_ebay_category_rows(ebay_categories_test_fixture('category-tree.json'));

	pg_assert_same(array('11450', '15724', '63861', '53159', '293', '31388'), array_column($rows, 'remote_id'), 'parents before children, the node without an id left out');
	pg_assert_same(array('remote_id' => '11450', 'parent_id' => '', 'name' => 'Kleidung & Accessoires', 'path' => 'Kleidung & Accessoires', 'is_leaf' => 0), $rows[0]);
	pg_assert_same(array('remote_id' => '63861', 'parent_id' => '15724', 'name' => 'Kleider', 'path' => 'Kleidung & Accessoires > Damen > Kleider', 'is_leaf' => 1), $rows[2]);
	pg_assert_same('293', $rows[5]['parent_id']);
	pg_assert_same(array(), pg_ebay_category_rows(null));
	pg_assert_same(array(), pg_ebay_category_rows(array('rootCategoryNode' => 'x')));
}

function test_ebay_category_tree_is_read_for_the_accounts_site()
{
	$calls = array();

	$result = mp_ebay_category_tree(ebay_categories_test_account(), ebay_categories_test_call(ebay_categories_test_routes(), $calls));

	pg_assert_true($result['ok'], 'read: ' . (isset($result['error']) ? $result['error'] : ''));
	pg_assert_same(6, count($result['data']['categories']));
	pg_assert_same(array(
		'/commerce/taxonomy/v1/get_default_category_tree_id?marketplace_id=EBAY_DE',
		'/commerce/taxonomy/v1/category_tree/77',
	), $calls);
}

function test_ebay_category_tree_passes_on_a_refusal()
{
	$calls = array();
	$routes = ebay_categories_test_routes();
	$routes['/commerce/taxonomy/v1/get_default_category_tree_id?marketplace_id=EBAY_DE'] = array(403, 'error-not-authorized.json');

	$result = mp_ebay_category_tree(ebay_categories_test_account(), ebay_categories_test_call($routes, $calls));

	pg_assert_false($result['ok']);
	pg_assert_same('eBay: Access denied', $result['error']);
	pg_assert_same(1, count($calls), 'the tree is not asked for');

	$calls = array();
	$routes = ebay_categories_test_routes();
	$routes['/commerce/taxonomy/v1/category_tree/77'] = array(200, 'default-category-tree-id.json');

	$result = mp_ebay_category_tree(ebay_categories_test_account(), ebay_categories_test_call($routes, $calls));

	pg_assert_false($result['ok'], 'a tree without categories is an error, never an empty cache');
}

/* ------------------------------------------------------------- aspects */

function test_ebay_aspects_become_attribute_rows()
{
	$rows = pg_ebay_aspect_rows(ebay_categories_test_fixture('item-aspects.json'));

	pg_assert_same(array('Marke', 'Größe', 'Abteilung', 'Material'), array_column($rows, 'attribute_id'), 'the aspect\'s name is its id; a nameless one is left out');
	pg_assert_same(array(
		'attribute_id'   => 'Marke',
		'name'           => 'Marke',
		'mandatory'      => 1,
		'is_variant'     => 0,
		'custom_allowed' => 1,
		'sort_order'     => 0,
		'values'         => array(array('id' => 'Esprit', 'value' => 'Esprit'), array('id' => 'Mango', 'value' => 'Mango')),
	), $rows[0]);
	pg_assert_same(1, $rows[1]['is_variant'], 'an aspect a variation may differ by is answered by the products');
	pg_assert_same(0, $rows[2]['custom_allowed'], 'selection only');
	pg_assert_same(0, $rows[3]['mandatory']);
	pg_assert_same(array(), $rows[3]['values']);
	pg_assert_same(3, $rows[3]['sort_order']);
	pg_assert_same(array(), pg_ebay_aspect_rows(array()));
}

function test_ebay_category_attributes_are_read_from_the_sites_tree()
{
	$calls = array();

	$result = mp_ebay_category_attributes(ebay_categories_test_account(), '63861', ebay_categories_test_call(ebay_categories_test_routes(), $calls));

	pg_assert_true($result['ok'], 'read: ' . (isset($result['error']) ? $result['error'] : ''));
	pg_assert_same(4, count($result['data']['attributes']));
	pg_assert_same('/commerce/taxonomy/v1/category_tree/77/get_item_aspects_for_category?category_id=63861', end($calls));

	$calls = array();

	$result = mp_ebay_category_attributes(ebay_categories_test_account(), '63861 OR 1', ebay_categories_test_call(ebay_categories_test_routes(), $calls));

	pg_assert_false($result['ok']);
	pg_assert_same(array(), $calls, 'a category id that is not a number is never sent');
}
