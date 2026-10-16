<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for pg_catalog_search_term() (includes/fn/content.php), the term a
 * catalog page searches for. The catalog and the page's RSS link read it
 * separately before: after the Clear button, with an empty page query beside
 * ?query=x, and with ?query=x on a custom layout (which does not read it) the
 * catalog showed its product group but the RSS link was left out.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Runs the helper with $_GET and $_REQUEST set to the given query string.
function catalog_search_term_for($page_id, $layout_type, $query_string)
{
	$saved_get = $_GET;
	$saved_request = $_REQUEST;

	parse_str($query_string, $parameters);
	$_GET = $parameters;
	$_REQUEST = $parameters;

	$term = pg_catalog_search_term($page_id, $layout_type);

	$_GET = $saved_get;
	$_REQUEST = $saved_request;

	return $term;
}

// System layout: the simple search's Clear button, then the page's own query,
// then the general query only when the page's own is not given.
function test_catalog_search_term_system_layout()
{
	$cases = array(
		'' => '',
		'985_query=kalem' => 'kalem',
		'985_query=%20kalem%20' => 'kalem',
		'985_query=' => '',
		'985_query=%20' => '',
		'985_query=kalem&985_simple_clear=' => '',
		'985_simple_clear=' => '',
		'query=kalem' => 'kalem',
		'query=' => '',
		'985_query=&query=kalem' => '',
		'985_query=defter&query=kalem' => 'defter',
		'985_query[]=x' => '',
		'985_query[]=x&query=kalem' => 'kalem',
		'query[]=x' => '',
		'985_clear=&985_query=kalem' => 'kalem',
		'1000_query=kalem' => '',
	);

	foreach ($cases as $query_string => $expected) {
		pg_assert_same($expected, catalog_search_term_for('985', 'system', $query_string), 'system: ?' . $query_string);
	}
}

// Custom layout: its form's Clear, then the page's own query; the general
// query is not read.
function test_catalog_search_term_custom_layout()
{
	$cases = array(
		'' => '',
		'1000_query=kalem' => 'kalem',
		'1000_query=%20kalem%20' => 'kalem',
		'1000_query=' => '',
		'1000_query=kalem&1000_clear=' => '',
		'1000_clear=' => '',
		'query=kalem' => '',
		'1000_query=&query=kalem' => '',
		'1000_query[]=x' => '',
		'1000_simple_clear=&1000_query=kalem' => 'kalem',
		'985_query=kalem' => '',
	);

	foreach ($cases as $query_string => $expected) {
		pg_assert_same($expected, catalog_search_term_for('1000', 'custom', $query_string), 'custom: ?' . $query_string);
	}
}

// The catalog searches for the helper's term in both layouts, and the RSS link
// asks the same helper with the page's layout.
function test_catalog_search_term_catalog_and_rss_share_it()
{
	$catalog = file_get_contents(PG_FUNCTIONS_DIR . '/get_catalog.php');

	pg_assert_true(strpos($catalog, "\$search_query = pg_catalog_search_term(\$page_id, 'system');") !== false, 'system layout does not read the helper');
	pg_assert_true(strpos($catalog, "\$query = pg_catalog_search_term(\$page_id, 'custom');") !== false, 'custom layout does not read the helper');
	pg_assert_false(strpos($catalog, "\$search_query = trim(\$_GET") !== false, 'system layout reads the query itself');
	pg_assert_false(strpos($catalog, "\$query = \$form->get(\$page_id . '_query');") !== false, 'custom layout reads the query itself');

	$page_content = file_get_contents(PG_FUNCTIONS_DIR . '/get_page_content.php');

	$case = strpos($page_content, "case 'catalog':");
	$rss = strpos($page_content, "if (pg_catalog_search_term(\$system_region_properties['page_id'], get_layout_type(\$system_region_properties['page_id'])) == '') {", (int) $case);

	pg_assert_true(($case !== false) && ($rss !== false), 'catalog RSS link does not ask the helper');
	pg_assert_true(($rss - $case) < 1000, 'catalog RSS condition is not the first in its case');
}
