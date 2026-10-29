<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the catalog branch of the site search widget
 * (_pg_sw_search_collect() in includes/fn/widgets.php): a catalog item is a
 * result only when the site has a page that opens it. Without one the item
 * was listed and counted with a '#' link, so a site with products but no
 * product detail page printed "1 result found" for an item nobody could
 * open. The editor names the gap under the "Product detail page" setting.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The source of one function of includes/fn/widgets.php, up to the next one.
function _search_results_catalog_link_fn($name)
{
	$src = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/widgets.php');
	$start = strpos($src, 'function ' . $name . '(');
	pg_assert_true($start !== false, $name . ' is found');
	$end = strpos($src, "\nfunction ", (int) $start + 10);
	return substr($src, (int) $start, (int) $end - (int) $start);
}

// The detail page is looked up before the catalog is searched, and the
// catalog is searched only once a page is found: no row without an address,
// so the count, the heading and the empty state read what can be opened.
function test_search_collect_catalog_needs_detail_page()
{
	$body = _search_results_catalog_link_fn('_pg_sw_search_collect');
	$lookup = strpos($body, '$detail_name = ');
	$guard = strpos($body, "if (\$want_catalog && \$detail_name !== '') {");
	$search = strpos($body, 'get_catalog_search_results(');
	pg_assert_true($lookup !== false, 'the detail page name is looked up');
	pg_assert_true($guard !== false, 'the catalog branch asks for a detail page');
	pg_assert_true($search !== false, 'the catalog is searched');
	pg_assert_true($lookup < $guard && $guard < $search, 'lookup, then the guard, then the search');
	pg_assert_same(1, substr_count($body, 'get_catalog_search_results('), 'the catalog is searched in one place');
	pg_assert_false(strpos($body, "\$url = '';") !== false, 'no catalog row is built without an address');
	pg_assert_false(strpos($body, "'full_url' => (\$url !== '')") !== false, 'no catalog row is built without a full address');
}

// The "Product detail page" setting of the editor's search results panel
// says when neither the site nor the design has a page to open a product.
function test_search_results_editor_names_missing_detail_page()
{
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	$start = strpos($js, '// ── search_results settings');
	$end = ($start !== false) ? strpos($js, '// ── shopping_cart settings', (int) $start) : false;
	pg_assert_true($start !== false && $end !== false, 'the search results settings are found');
	$panel = substr($js, (int) $start, (int) $end - (int) $start);
	pg_assert_contains('data-sw-cfg="catalog_detail_page_id"', $panel);
	pg_assert_contains("_swTabHasWidget(p, ['catalog_item_view'])", $panel);
	pg_assert_contains("_sdT('No product detail page exists yet: products stay out of the search results until the site has one.')", $panel);
	pg_assert_true(strpos($panel, 'var _srNoDetail = ') < strpos($panel, 'systemSection += sect('), 'the gap is known before the panel is drawn');
}
