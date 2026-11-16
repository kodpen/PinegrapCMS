<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the catalog listing's closed blocks: the category tree and the
 * filters offcanvas (price, stock and attribute filters) are built by pure
 * builders in includes/fn/widgets_catalog.php, and the designer canvas draws
 * their twins from assets/js/style_designer.src.js. The twins are cut out and
 * run under Node (skipped on a machine without Node) and compared with the
 * builders byte for byte, for a catalog with sub groups and filterable
 * attributes.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function catalog_listing_blocks_test_source()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
}

// The body of the named function, up to the next function at the same indentation.
function catalog_listing_blocks_test_function($source, $name)
{
	$from = strpos($source, '    function ' . $name . '(');
	pg_assert_true($from !== false, 'the designer has ' . $name . '()');
	$to = strpos($source, "\n    function ", $from + 10);
	pg_assert_true($to !== false, $name . '() has an end');

	return substr($source, $from, $to - $from);
}

// Runs a JavaScript expression over the cut twins; null without Node.
function catalog_listing_blocks_test_run($expr)
{
	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);

	if ($code !== 0) {
		return null;
	}

	$source = catalog_listing_blocks_test_source();
	$script = "function esc(s) { if (!s && s !== 0) return ''; return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/\"/g, '&quot;'); }\n"
		. "function _sdT(s) { return s; }\n"
		. "function query(p, v) { return (v === '' || v == null) ? '' : p + '=' + v; }\n";
	foreach (array('_sdCatalogGroupTreeHtml', '_sdCatalogPriceFilterHtml', '_sdCatalogStockFilterHtml',
		'_sdCatalogAttrFiltersHtml', '_sdCatalogFiltersInnerHtml') as $fn) {
		$script .= catalog_listing_blocks_test_function($source, $fn) . "\n";
	}
	$script .= 'console.log(JSON.stringify(' . $expr . '));';
	$file = tempnam(sys_get_temp_dir(), 'pgcatlist');
	file_put_contents($file, $script);
	$out = array();
	exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	unlink($file);
	pg_assert_same(0, $code, implode("\n", $out));

	return json_decode(implode("\n", $out), true);
}

// The query string of a filter link, as the test's twin builds it.
function catalog_listing_blocks_test_query($param, $value)
{
	return ((string)$value === '') ? '' : $param . '=' . $value;
}

// The canvas draws the category tree the site prints: the levels, the
// visitor's group in bold, browse groups on the listing page.
function test_catalog_listing_blocks_group_tree()
{
	$rows = array(
		0  => array(
			array('id' => 12, 'name' => 'Shirts', 'address_name' => 'shirts', 'display_type' => 'browse'),
			array('id' => 13, 'name' => 'Trousers', 'address_name' => 'trousers', 'display_type' => 'browse'),
			array('id' => 14, 'name' => 'Gift sets', 'address_name' => 'gift-sets', 'display_type' => 'select'),
		),
		12 => array(
			array('id' => 121, 'name' => 'Linen', 'address_name' => 'linen', 'display_type' => 'browse'),
			array('id' => 122, 'name' => 'Oxford', 'address_name' => 'oxford', 'display_type' => 'browse'),
		),
	);
	$level = function ($parent, $depth) use (&$level, $rows) {
		if (empty($rows[$parent])) return '';
		return pg_catalog_group_tree_level($rows[$parent], '/shop/', 121, '/product/', $depth, $level);
	};
	$site = $level(0, 0);
	pg_assert_contains('<li class="pg-sw-cl-tree-item active"><a href="/shop/linen" class="text-decoration-none fw-bold">Linen</a></li>', $site);
	pg_assert_contains('href="/product/gift-sets"', $site, 'a select group opens on the detail page');

	$drawn = catalog_listing_blocks_test_run('_sdCatalogGroupTreeHtml([{ id: 12, name: "Shirts", url: "/shop/shirts", children: ['
		. '{ id: 121, name: "Linen", url: "/shop/linen" }, { id: 122, name: "Oxford", url: "/shop/oxford" }] }, '
		. '{ id: 13, name: "Trousers", url: "/shop/trousers" }, { id: 14, name: "Gift sets", url: "/product/gift-sets" }], 121, 0)');

	if ($drawn !== null) {
		pg_assert_same($site, $drawn, 'the canvas category tree is the site\'s');
	}
}

// The canvas draws each filter as the site prints it.
function test_catalog_listing_blocks_filters_match_the_site()
{
	$attrs = array(
		1 => array('attr_label' => 'Colour', 'options' => array(array('option_id' => 11, 'option_label' => 'Natural'), array('option_id' => 12, 'option_label' => 'Navy'))),
		2 => array('attr_label' => 'Size', 'options' => array(array('option_id' => 21, 'option_label' => 'S'), array('option_id' => 22, 'option_label' => 'M'))),
	);
	$js_attrs = '{ 1: { attr_label: "Colour", options: [{ option_id: 11, option_label: "Natural" }, { option_id: 12, option_label: "Navy" }] }, '
		. '2: { attr_label: "Size", options: [{ option_id: 21, option_label: "S" }, { option_id: 22, option_label: "M" }] } }';
	$q = 'catalog_listing_blocks_test_query';
	$site = array(
		pg_catalog_price_filter_html('', '', '', 5),
		pg_catalog_price_filter_html('', '10', '90', 1),
		pg_catalog_stock_filter_html(7, true, ''),
		pg_catalog_stock_filter_html(7, false, 'stock=1'),
		pg_catalog_attribute_filters_html($attrs, array(1 => 11), 'checkboxes', $q, 7),
		pg_catalog_attribute_filters_html($attrs, array(2 => 22), 'buttons', $q, 7),
		pg_catalog_filters_offcanvas_inner_html('pgFilters7', '', 'P', 'S', 'A'),
		pg_catalog_filters_offcanvas_inner_html('pgFilters7', 'C', 'P', '', ''),
	);
	$drawn = catalog_listing_blocks_test_run('['
		. '_sdCatalogPriceFilterHtml("", "", "", 5), _sdCatalogPriceFilterHtml("", "10", "90", 1), '
		. '_sdCatalogStockFilterHtml(7, true, ""), _sdCatalogStockFilterHtml(7, false, "stock=1"), '
		. '_sdCatalogAttrFiltersHtml(' . $js_attrs . ', { 1: 11 }, "checkboxes", query, 7), '
		. '_sdCatalogAttrFiltersHtml(' . $js_attrs . ', { 2: 22 }, "buttons", query, 7), '
		. '_sdCatalogFiltersInnerHtml("pgFilters7", "", "P", "S", "A"), _sdCatalogFiltersInnerHtml("pgFilters7", "C", "P", "", "")]');

	if ($drawn === null) {
		return;
	}

	foreach ($site as $i => $html) {
		pg_assert_same($html, $drawn[$i], 'the canvas filter part ' . $i . ' is the site\'s');
	}
}

// The listing renders through the builders; the canvas draws a section's
// sample only where the settings make the site print something.
function test_catalog_listing_blocks_wiring()
{
	$php = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/widgets_catalog.php');
	pg_assert_contains('return pg_catalog_group_tree_level($rows, $listing_path_base, $active_group_id, $detail_url_prefix, $depth,', $php);
	pg_assert_contains('$filters_offcanvas_inner_html = pg_catalog_filters_offcanvas_inner_html($_pg_off_id, $filter_chips_html, $price_filter_html, $stock_filter_html, $attribute_filters_html);', $php);
	pg_assert_contains('$stock_filter_html = pg_catalog_stock_filter_html($widget_id, $stock_filter_active, $stock_link_query);', $php);

	$js = catalog_listing_blocks_test_source();
	pg_assert_contains("var _catSample = _sdCatalogSectionSample(_sec, doc, _sysWidgetRenderCfg);", $js);
	$section = catalog_listing_blocks_test_function($js, '_sdCatalogSectionHtml');
	pg_assert_contains("(c.group_navigation === 'none') ? ''", $section);
	pg_assert_contains("if (!c.price_filter_enabled && !c.stock_filter_enabled && !c.show_attribute_filters) return '';", $section);
}
