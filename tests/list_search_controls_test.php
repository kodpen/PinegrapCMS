<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The search, sort and price forms the form list and catalog listing
 * widgets print above their items: every control has an accessible name,
 * the price filter's words are translated, and the router's page name on a
 * pretty address is not carried into the forms or the Clear link.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function _list_search_controls_box($uri, $get, $query)
{
	$saved_get = $_GET;
	$saved_uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : null;
	$_GET = $get;
	$_SERVER['REQUEST_URI'] = $uri;

	$html = _pg_sw_search_box_html('query', $query, 'Search the blog...', 'w-100', 'page_number');

	$_GET = $saved_get;
	if ($saved_uri === null) unset($_SERVER['REQUEST_URI']); else $_SERVER['REQUEST_URI'] = $saved_uri;
	return $html;
}

function test_search_box_controls_are_named()
{
	$html = _list_search_controls_box('/blog?query=coffee&lang=en', array('page' => 'blog', 'query' => 'coffee', 'lang' => 'en'), 'coffee');

	pg_assert_contains('placeholder="Search the blog..." aria-label="Search the blog..."', $html);
	pg_assert_contains('<button type="submit" class="btn btn-outline-secondary" aria-label="' . h(lang('Search')) . '">', $html);
	pg_assert_contains('aria-label="' . h(lang('Clear search')) . '"><span class="bi bi-x-lg" aria-hidden="true"></span></a>', $html);
	pg_assert_contains('<input type="hidden" name="lang" value="en">', $html);
	pg_assert_contains('href="?lang=en"', $html);
	pg_assert_false(strpos($html, 'name="page"') !== false, 'no router page name in the form');
	pg_assert_false(strpos($html, 'page=blog') !== false, 'no router page name in the Clear link');
	pg_assert_false(strpos($html, 'type="hidden" name="query"') !== false, 'the query is not repeated as a hidden input');
}

// On /index.php?page=… the page name is the address and stays.
function test_search_box_keeps_page_on_index_php()
{
	$html = _list_search_controls_box('/index.php?page=blog', array('page' => 'blog'), '');

	pg_assert_contains('<input type="hidden" name="page" value="blog">', $html);
	pg_assert_false(strpos($html, 'bi-x-lg') !== false, 'no Clear link without a query');
}

function _list_search_controls_fn($file, $name)
{
	$src = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/' . $file);
	$start = strpos($src, 'function ' . $name . '(');
	pg_assert_true($start !== false, $name . ' is found');
	$end = strpos($src, "\nfunction ", (int) $start + 10);
	return substr($src, (int) $start, ($end === false ? strlen($src) : (int) $end) - (int) $start);
}

// Both widgets print the shared search box; the catalog's sort label points
// at its select, the price boxes are named and its words go through lang().
function test_list_widgets_use_the_named_controls()
{
	$catalog = _list_search_controls_fn('widgets_catalog.php', '_render_system_widget_catalog_listing');
	pg_assert_contains('_pg_sw_search_box_html($query_param, $search_query, $search_label, $search_width_cls, $page_param)', $catalog);
	pg_assert_contains("_pg_sw_hidden_query_inputs(array('sort', (string)\$page_param))", $catalog);
	pg_assert_contains("_pg_sw_hidden_query_inputs(array('pmin', 'pmax', (string)\$page_param))", $catalog);
	pg_assert_contains('for="\' . $sort_id . \'"', $catalog);
	pg_assert_contains('<select name="sort" id="\' . $sort_id . \'"', $catalog);
	pg_assert_contains("aria-label=\"' . h(lang('Minimum price')) . '\"", $catalog);
	pg_assert_contains("aria-label=\"' . h(lang('Maximum price')) . '\"", $catalog);
	pg_assert_contains("h(lang('Price')) . ':", $catalog);
	pg_assert_false(strpos($catalog, 'Fiyat') !== false || strpos($catalog, 'Uygula') !== false, 'no fixed Turkish text');

	$src = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/widgets.php');
	pg_assert_true(substr_count($src, '_pg_sw_search_box_html($query_param, $search_query, $search_label, $search_width_cls, $page_param)') === 1, 'form list prints the shared box');
}
