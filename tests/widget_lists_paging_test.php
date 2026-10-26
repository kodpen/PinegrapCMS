<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the page links of the form list, catalog listing and search
 * results widgets as a list (source paging.pages): the source, its rows
 * (pg_sw_list_paging_rows()), the kinds that offer it, a list drawn in the
 * widget's surround by the finish pass (in the catalog's surround with its
 * paging flags too), the renderers leaving their own pagination out, and
 * the editor's twin.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function _wlp_numbers($rows)
{
	return array_map(function ($row) {
		return ($row['__pager_is_gap'] === '1') ? '…' : $row['__pager_number'] . ($row['__pager_is_current'] === '1' ? '*' : '');
	}, $rows);
}

function test_widget_lists_paging_source()
{
	$sources = pg_sw_list_sources();
	pg_assert_true(isset($sources['paging.pages']), 'a list source');
	pg_assert_same('paging', $sources['paging.pages']['family']);
	pg_assert_same('__page_number', $sources['paging.pages']['key']);
	pg_assert_same(array('form_record.values', 'form_record.split', 'form_record.files', 'paging.pages'), array_keys(pg_sw_kind_lists('form_list_view')));
	pg_assert_false(isset(pg_sw_kind_lists('form_item_view')['paging.pages']), 'not in a record view');
	pg_assert_true(isset(pg_sw_kind_lists('catalog_listing')['paging.pages']), 'in the catalog listing');
	pg_assert_same(array('paging.pages'), array_keys(pg_sw_kind_lists('search_results')), 'the search results offer only it');
	pg_assert_false(isset(pg_sw_kind_lists('catalog_item_view')['paging.pages']), 'not in a product view');
}

// The same links as the built-in pagination, keyed by the page shown.
function test_widget_lists_paging_rows()
{
	$saved_get = $_GET;
	$_GET = array('author' => 'Jane', 'p2' => '7');

	pg_assert_same(array('paging.pages' => array()), pg_sw_list_paging_rows('page_number', 1, 1));
	pg_assert_same(array('paging.pages' => array()), pg_sw_list_paging_rows('page_number', 1, 0));

	$rows = pg_sw_list_paging_rows('p2', 7, 20);
	pg_assert_same(array(7), array_keys($rows['paging.pages']), 'keyed by the page shown');
	pg_assert_same(array('1', '…', '5', '6', '7*', '8', '9', '…', '20'), _wlp_numbers($rows['paging.pages'][7]));
	pg_assert_same('?author=Jane&p2=5', $rows['paging.pages'][7][2]['__pager_url'], 'raw, the other values kept');
	pg_assert_same('', $rows['paging.pages'][7][1]['__pager_url'], 'a gap has no address');

	$sources = pg_sw_list_sources();
	$tokens  = array_map(function ($pair) { return $pair[0]; }, $sources['paging.pages']['tokens']);
	foreach ($rows['paging.pages'][7] as $row) pg_assert_same($tokens, array_keys($row), 'row keys');

	pg_assert_same(array('1*', '2', '3', '…', '9'), _wlp_numbers(pg_sw_list_paging_rows('p2', 1, 9)['paging.pages'][1]));
	pg_assert_same(array('1', '2', '3*', '4', '5'), _wlp_numbers(pg_sw_list_paging_rows('p2', 3, 5)['paging.pages'][3]));
	pg_assert_same(array('1', '…', '7', '8', '9*'), _wlp_numbers(pg_sw_list_paging_rows('p2', 9, 9)['paging.pages'][9]));

	// The links match the built-in pagination's numbers.
	preg_match_all('#<li class="page-item[^"]*"[^>]*><(?:a|span) class="page-link"[^>]*>([^<]*)<#', pg_sw_pagination_html('p2', 7, 20), $m);
	$html_numbers = array_values(array_filter($m[1], function ($t) { return $t !== '&laquo;' && $t !== '&raquo;'; }));
	pg_assert_same(array('1', '…', '5', '6', '7', '8', '9', '…', '20'), $html_numbers);
	$_GET = $saved_get;
}

// A list of the page links in the widget's surround: the surround's fill
// gives its key, the finish pass draws the rows, escaped once.
function test_widget_lists_paging_drawn_in_the_surround()
{
	$saved_get = $_GET;
	$_GET = array('tag' => 'a&b');
	$link = array('type' => 'semantic', 'props' => array('tag' => 'a', 'cssClass' => 'pl', 'text' => 'x', 'href' => '#',
		'_bindings' => array('href' => '__pager_url', 'text' => '__pager_number')), 'children' => array());
	$tree = array('type' => 'root', 'props' => array(), 'children' => array(
		array('type' => 'loop_area', 'props' => array(), 'children' => array(
			array('type' => 'semantic', 'props' => array('tag' => 'p', 'cssClass' => 'rec', 'text' => 'r'), 'children' => array()),
		)),
		array('type' => 'semantic', 'props' => array('tag' => 'nav', 'cssClass' => 'pager'), 'children' => array(
			array('type' => 'loop_area', 'props' => array('source' => 'paging.pages'), 'children' => array($link)),
		)),
	));

	$scope = pg_sw_finish_open(array('sid' => 92, 'kind' => 'form_list_view', 'email' => false));
	try {
		$split  = _split_widget_tree($tree, true);
		$static = trim(_render_tree_node($split['static_tree'], 0, 0));
		pg_assert_same(array('paging.pages' => true), pg_sw_list_wanted('paging'), 'the widget drew a page list');
		pg_assert_same(array(), pg_sw_list_wanted('form_record'), 'and no record list');
		pg_sw_list_publish('paging', pg_sw_list_paging_rows('page_number', 2, 3));
		$paging = pg_sw_list_paging('page_number', 2, 3);
		$html   = pg_sw_sweep_tokens(_pg_sw_fill_tokens($static, $paging['tokens']));
		$html   = str_replace(array('<!--pg-loop-before-->', '<!--pg-loop-slot-->', '<!--pg-loop-after-->'), '', $html);
		$html   = pg_sw_finish_widget_html($html, $scope);
	} finally {
		pg_sw_finish_close();
	}
	$_GET = $saved_get;

	preg_match_all('#<a class="pl" href="([^"]*)"[^>]*>([^<]*)<#', $html, $m);
	pg_assert_same(array('1', '2', '3'), $m[2], 'one link per page');
	pg_assert_same('?tag=a%26b&amp;page_number=3', $m[1][2], 'escaped once');
	pg_assert_false(strpos($html, '<!--pg-') !== false, 'no marker left');
	pg_assert_false(strpos($html, '^^') !== false, 'no token left');
}

// The renderer publishes the rows and leaves its own pagination out only
// when the design holds a page list.
function test_widget_lists_paging_renderer_wiring()
{
	$widgets = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/widgets.php');
	$start = strpos($widgets, 'function _render_system_widget_form_list(');
	$body = substr($widgets, (int) $start, (int) strpos($widgets, "\nfunction ", (int) $start + 10) - (int) $start);
	pg_assert_contains("if (pg_sw_list_wanted('paging')) {
        pg_sw_list_publish('paging', pg_sw_list_paging_rows(\$page_param, \$current_page, \$total_pages));
        \$pagination_html = '';
    } else {
        \$pagination_html = pg_sw_pagination_html(\$page_param, \$current_page, \$total_pages);
    }", $body);
}

// The editor offers the list in the form list, anywhere in the widget.
function test_widget_lists_paging_editor()
{
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.js');
	pg_assert_contains("extra: { listSource: 'paging.pages' }", $js);
	pg_assert_contains("'paging.pages': {", $js);
	pg_assert_contains("kinds: ['form_list_view', 'catalog_listing', 'search_results'],", $js);
	pg_assert_contains("function _sdListNeedsMainLoop(kind, source) {", $js);
	pg_assert_true((bool) preg_match("/'paging.pages':\s+_sdT\('One row for every page link/", $js), 'the panel explains the list');
}

// In the catalog listing's surround: its paging flags are marked before the
// render and resolved after the count (_render_system_widget_catalog_listing()),
// so the starter's previous / next items and the list drawn by the finish
// pass come out together.
function test_widget_lists_paging_in_the_catalog_surround()
{
	$saved_get = $_GET;
	$_GET = array('shop_page' => '1');
	$step = function ($flag, $token) {
		return array('type' => 'semantic', 'props' => array('tag' => 'a', 'cssClass' => $flag, 'text' => 's', 'href' => '#',
			'_bindings' => array('eo_visible_if' => $flag, 'href' => $token)), 'children' => array());
	};
	$link = array('type' => 'semantic', 'props' => array('tag' => 'a', 'cssClass' => 'pl', 'text' => 'x', 'href' => '#',
		'_bindings' => array('href' => '__pager_url', 'text' => '__pager_number')), 'children' => array());
	$tree = array('type' => 'root', 'props' => array(), 'children' => array(
		array('type' => 'loop_area', 'props' => array(), 'children' => array(
			array('type' => 'semantic', 'props' => array('tag' => 'p', 'cssClass' => 'card', 'text' => 'c'), 'children' => array()),
		)),
		array('type' => 'semantic', 'props' => array('tag' => 'nav', 'cssClass' => 'pager'), 'children' => array(
			$step('has_prev_page', '__prev_page_url'),
			array('type' => 'loop_area', 'props' => array('source' => 'paging.pages'), 'children' => array($link)),
			$step('has_next_page', '__next_page_url'),
		)),
	));

	$scope = pg_sw_finish_open(array('sid' => 93, 'kind' => 'catalog_listing', 'email' => false));
	try {
		$split = _split_widget_tree($tree, true);
		_pg_sw_mark_visibility($split['static_tree'], array('has_prev_page', 'has_next_page', 'has_more_pages'));
		$static = trim(_render_tree_node($split['static_tree'], 0, 0));
		pg_assert_same(array('paging.pages' => true), pg_sw_list_wanted('paging'), 'the listing drew a page list');
		pg_sw_list_publish('paging', pg_sw_list_paging_rows('shop_page', 1, 2));
		$paging = pg_sw_list_paging('shop_page', 1, 2);
		$html   = _pg_sw_resolve_visibility($static, $paging['flags']);
		$html   = pg_sw_sweep_tokens(_pg_catalog_fill_page_tokens($html, $paging['tokens']));
		$html   = str_replace(array('<!--pg-loop-before-->', '<!--pg-loop-slot-->', '<!--pg-loop-after-->'), '', $html);
		$html   = pg_sw_finish_widget_html($html, $scope);
	} finally {
		pg_sw_finish_close();
	}
	$_GET = $saved_get;

	pg_assert_false(strpos($html, 'class="has_prev_page"') !== false, 'no previous page on the first');
	pg_assert_contains('class="has_next_page" href="?shop_page=2"', $html);
	preg_match_all('#<a class="pl" href="([^"]*)"[^>]*>([^<]*)<#', $html, $m);
	pg_assert_same(array('1', '2'), $m[2], 'one link per page');
	pg_assert_same(array('', '?shop_page=2'), $m[1], 'the page shown is not linked');
	pg_assert_false(strpos($html, '<!--pg-') !== false, 'no marker left');
	pg_assert_false(strpos($html, '^^') !== false, 'no token left');
}

// The catalog listing and the search results publish the rows and leave
// their own pagination out only when the design holds a page list.
function test_widget_lists_paging_catalog_and_search_wiring()
{
	$catalog = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/widgets_catalog.php');
	$start = strpos($catalog, 'function _render_system_widget_catalog_listing(');
	$body = substr($catalog, (int) $start, (int) strpos($catalog, "\nfunction ", (int) $start + 10) - (int) $start);
	pg_assert_contains("\$pagination_html = '';
    if (pg_sw_list_wanted('paging')) {
        pg_sw_list_publish('paging', pg_sw_list_paging_rows(\$page_param, \$current_page, \$total_pages));
    } elseif (\$total_pages > 1) {", $body);

	$widgets = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/widgets.php');
	$start = strpos($widgets, 'function _render_system_widget_search_results(');
	$body = substr($widgets, (int) $start, (int) strpos($widgets, "\nfunction ", (int) $start + 10) - (int) $start);
	pg_assert_contains("'pagination' => (\$total_pages > 1) ? '<!--pg-sr-pagination-->' : '',", $body);
	pg_assert_contains("if (pg_sw_list_wanted('paging')) {
        pg_sw_list_publish('paging', pg_sw_list_paging_rows(\$page_param, \$current_page, \$total_pages));
        \$pagination_html = '';
    } else {
        \$pagination_html = pg_sw_pagination_html(\$page_param, \$current_page, \$total_pages);
    }", $body);
	// The list registers itself while the tree is drawn: the question is
	// asked after the render, and the placeholder filled in both halves.
	pg_assert_true(strpos($body, "_render_tree_node(\$split['static_tree'], 0, 0)") < strpos($body, "pg_sw_list_wanted('paging')"), 'asked after the render');
	pg_assert_contains("\$static_html   = str_replace('<!--pg-sr-pagination-->', \$pagination_html, \$static_html);", $body);
	pg_assert_contains("\$loop_template = str_replace('<!--pg-sr-pagination-->', \$pagination_html, \$loop_template);", $body);
}
