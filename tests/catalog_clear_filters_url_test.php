<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the catalog listing's "clear filters" address
 * (_pg_catalog_clear_filters_query(), includes/fn/widgets_catalog.php): it
 * keeps the visitor's other query parameters, drops the filters (the
 * attribute filters' pg_attr_<id> included) and the page number, and does not carry the router's page name on a pretty address.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Runs $fn with $_GET and the request address set, then puts both back.
function _catalog_clear_filters_with_request($uri, $get, $fn)
{
	$saved_get = $_GET;
	$saved_uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : null;
	$_GET = $get;
	$_SERVER['REQUEST_URI'] = $uri;
	try {
		return $fn();
	} finally {
		$_GET = $saved_get;
		if ($saved_uri === null) unset($_SERVER['REQUEST_URI']); else $_SERVER['REQUEST_URI'] = $saved_uri;
	}
}

// Filters and the page number go; the search text and the language stay.
function test_catalog_clear_filters_query_drops_filters_and_page_number()
{
	pg_assert_same(
		'query=chair&lang=en',
		_pg_catalog_clear_filters_query(
			array('pmin' => '10', 'query' => 'chair', 'pmax' => '90', 'stock' => '1', 'sort' => 'price', 'page_number' => '3', 'lang' => 'en'),
			'page_number'
		),
		'filters and page number dropped');

	pg_assert_same(
		'q=a%26b',
		_pg_catalog_clear_filters_query(array('q' => 'a&b', 'attr' => array('3' => '7'), 'p2' => '2'), 'p2'),
		'values encoded, array parameters dropped, own page parameter dropped');

	pg_assert_same('', _pg_catalog_clear_filters_query(array(), 'page_number'), 'nothing left');
}

// An attribute filter is a pg_attr_<id> parameter: it is a filter and goes.
function test_catalog_clear_filters_query_drops_attribute_filters()
{
	pg_assert_same(
		'query=chair',
		_pg_catalog_clear_filters_query(array('pg_attr_3' => '7', 'query' => 'chair', 'pg_attr_12' => '40'), 'page_number'),
		'attribute filters dropped');
}

// A key is encoded too: it is written into the address as it came.
function test_catalog_clear_filters_query_encodes_keys()
{
	pg_assert_same('a%3Cb=1', _pg_catalog_clear_filters_query(array('a<b' => '1'), 'page_number'), 'key encoded');
}

// On a pretty address the router writes the page's name into $_GET['page'];
// the clear link must not repeat it. On /index.php?page=… it is the address.
function test_catalog_clear_filters_query_skips_router_page_name()
{
	$pretty = _catalog_clear_filters_with_request(
		'/urunler/ayakkabi?pmin=10&query=x',
		array('page' => 'urunler/ayakkabi', 'pmin' => '10', 'query' => 'x'),
		function () { return _pg_catalog_clear_filters_query(_pg_sw_query_params(), 'page_number'); }
	);
	pg_assert_same('query=x', $pretty, 'pretty address: page name not carried');

	$classic = _catalog_clear_filters_with_request(
		'/index.php?page=urunler&pmin=10',
		array('page' => 'urunler', 'pmin' => '10'),
		function () { return _pg_catalog_clear_filters_query(_pg_sw_query_params(), 'page_number'); }
	);
	pg_assert_same('page=urunler', $classic, 'index.php keeps page');
}
