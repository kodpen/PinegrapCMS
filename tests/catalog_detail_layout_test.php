<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the catalog detail widget drawing a classic catalog detail
 * layout converted into its design elements (pg_layout_convert('catalog
 * detail', …)): the flags its conditions became (pg_civ_layout_flags()),
 * a product's line, a group's product lines and pick list, and the
 * converted product column through the widget's binding pass.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/layout_convert.php');

function test_civ_layout_flags_plain_product()
{
	$f = pg_civ_layout_flags(true, 0, 1, false, '');
	pg_assert_same(array('has_products', 'no_variant_choice', 'no_product_choice', 'one_product'), array_keys(array_filter($f)));
	$f = pg_civ_layout_flags(false, 0, 1, true, '/shop');
	pg_assert_same(array('no_products', 'no_variant_choice', 'no_product_choice', 'one_product', 'has_back_button'), array_keys(array_filter($f)), 'out of stock: no recipient to pick');
	pg_assert_true(pg_civ_layout_flags(true, 0, 1, true, '')['has_recipient_choice'], 'several recipients');
}

function test_civ_layout_flags_group()
{
	$f = pg_civ_layout_flags(true, 2, 4, false, '');
	pg_assert_same(array('has_products', 'no_product_choice', 'many_products'), array_keys(array_filter($f)), 'attributes: the variant picker');
	$f = pg_civ_layout_flags(true, 0, 3, false, '');
	pg_assert_same(array('has_products', 'no_variant_choice', 'has_product_choice', 'many_products'), array_keys(array_filter($f)), 'no attributes: a pick list');
	$f = pg_civ_layout_flags(false, 0, 3, false, '');
	pg_assert_same(array('no_products', 'no_variant_choice', 'no_product_choice', 'many_products'), array_keys(array_filter($f)), 'none can be ordered: the lines');
}

function test_civ_product_line()
{
	pg_assert_same('Tom &amp; Co (<span>$4.00</span>)', pg_civ_product_line('Tom & Co', '<span>$4.00</span>'));
	$long = '<p>' . str_repeat('a', 60) . '</p>';
	pg_assert_same('Shirt ($4.00) - ' . str_repeat('a', 50) . '...', pg_civ_product_line('Shirt', '$4.00', $long), 'the message cut at 50');
	pg_assert_same('Shirt ($4.00) - Back &lt;soon&gt;', pg_civ_product_line('Shirt', '$4.00', '<p>Back &lt;soon&gt;</p>'));
}

function test_civ_product_lines_and_choice()
{
	pg_assert_same('', pg_civ_product_lines_html(array()));
	pg_assert_same('<ul><li>A</li><li>B</li></ul>', pg_civ_product_lines_html(array('A', 'B')));
	pg_assert_same('<option value="3">A &amp; B</option><option value="4" selected>C</option>', pg_civ_product_choice_options(array(3 => 'A & B', 4 => 'C'), 4));
}

function _test_cdl_html($id, $flags)
{
	$r = pg_layout_convert('catalog detail', (string)file_get_contents(PG_FUNCTIONS_DIR . '/data/backups/english_default/layouts/' . $id . '.php'), array());
	$tree = json_decode(json_encode($r['tree']), true);
	_apply_catalog_item_view_bindings($tree, array('multi_recipient_active' => true, 'recipient_options' => array('Myself' => 'Myself'), 'min_quantity' => 1, 'default_quantity' => 1, 'max_quantity' => 0));
	_pg_sw_mark_visibility($tree, array_keys($flags));
	return _pg_sw_resolve_visibility(trim(_render_tree_node($tree, 0, 0)), $flags);
}

// A plain product: its line, the quantity and the add button, no pick list,
// no lines of a group, no back link.
function test_catalog_detail_converted_plain_product()
{
	$html = _test_cdl_html(1002, pg_civ_layout_flags(true, 0, 1, true, ''));
	pg_assert_contains('<strong>Item:</strong> ^^__product_line^^', $html, 'the product row');
	pg_assert_false(strpos($html, 'Items:') !== false || strpos($html, '^^__product_lines_html^^') !== false, 'not a group\'s lines');
	pg_assert_false(strpos($html, 'name="product_id"') !== false, 'no pick list');
	pg_assert_true((bool)preg_match('/<input[^>]*name="quantity"[^>]*min="1"/', $html), 'the quantity the widget fills');
	pg_assert_true(strpos($html, 'name="ship_to"') !== false && strpos($html, 'name="add_name"') !== false, 'the recipient and the new name');
	pg_assert_true(strpos($html, '^^__add_button_label^^') !== false && strpos($html, 'type="submit"') !== false, 'the add button');
	pg_assert_false(strpos($html, '^^__back_url^^') !== false, 'no back link');
	pg_assert_true(strpos($html, '^^__variant_picker^^') !== false, 'the variant picker\'s place');
	pg_assert_false(strpos($html, '<form') !== false || strpos($html, 'pg-vis') !== false, 'no form, no marker');
}

// A group with no attributes: the pick list while one can be ordered, the
// lines and the back link while none can.
function test_catalog_detail_converted_group()
{
	$html = _test_cdl_html(1002, pg_civ_layout_flags(true, 0, 3, false, ''));
	pg_assert_true((bool)preg_match('/<select name="product_id" required id="product_id">\^\^__product_choice_options\^\^<\/select>/', $html), 'the pick list');
	pg_assert_false(strpos($html, '^^__product_line^^') !== false, 'not the one product\'s row');
	pg_assert_false(strpos($html, 'name="ship_to"') !== false, 'one recipient');
	$html = _test_cdl_html(1002, pg_civ_layout_flags(false, 0, 3, false, '/shop'));
	pg_assert_contains('<strong>Items:</strong>', $html);
	pg_assert_contains('^^__product_lines_html^^', $html, 'the group\'s lines');
	pg_assert_false(strpos($html, 'name="quantity"') !== false || strpos($html, '^^__add_button_label^^') !== false, 'nothing to add');
	pg_assert_true(substr_count($html, '^^__back_url^^') === 2, 'the back links at the top and in place of the form');
}

function test_civ_keywords_html()
{
	pg_assert_same('', pg_civ_keywords_html(' , ', '/shop?query='));
	pg_assert_same("<a href=\"/shop?query=linen%20shirt\">linen shirt</a>\n<a href=\"/shop?query=a%26b\">a&amp;b</a>", pg_civ_keywords_html('linen shirt, a&b', '/shop?query='));
	pg_assert_same("linen\nwool", pg_civ_keywords_html('linen,wool', ''), 'no catalog page: the words alone');
}

// The sidebar of a converted text: the search form a <div role="search">
// whose box and button post the form after the widget, the categories and
// keywords the widget's, the currency select kept for its picker.
function test_catalog_detail_converted_sidebar()
{
	$r = pg_layout_convert('catalog detail', (string)file_get_contents(PG_FUNCTIONS_DIR . '/data/backups/english_default/layouts/986.php'), array());
	$tree = json_decode(json_encode($r['tree']), true);
	$ctx = array('min_quantity' => 1, 'default_quantity' => 1, 'max_quantity' => 0, 'search_form_id' => 'pg-civ-search-5', 'search_param' => 'query');
	_apply_catalog_item_view_bindings($tree, $ctx);
	$html = trim(_render_tree_node($tree, 0, 0));
	pg_assert_false(strpos($html, '<form') !== false, 'no nested form');
	pg_assert_true((bool)preg_match('/<div[^>]*role="search"/', $html), 'the search box\'s wrapper');
	pg_assert_true((bool)preg_match('/<input[^>]*type="search"[^>]*name="query"[^>]*form="pg-civ-search-5"/', $html), 'the box posts the form after the widget');
	pg_assert_true((bool)preg_match('/<button[^>]*form="pg-civ-search-5"/', $html), 'its button too');
	pg_assert_true(strpos($html, '^^__group_tree_inner_html^^') !== false && strpos($html, '^^__keywords_html^^') !== false, 'the categories and keywords');
	pg_assert_true(strpos($html, 'name="currency_id"') !== false, 'the currency select for the widget\'s picker');
	pg_assert_true(strpos($html, 'Browse Catalog') !== false && strpos($html, 'Search Catalog') !== false, 'the theme\'s headings stay');
	pg_assert_true(strpos($html, '^^__cross_sell_html^^') !== false, 'the cross-sell part\'s place');
	// No catalog page to search: the search form is left out.
	$tree = json_decode(json_encode($r['tree']), true);
	_apply_catalog_item_view_bindings($tree, array('search_form_id' => '') + $ctx);
	pg_assert_false(strpos(_render_tree_node($tree, 0, 0), 'role="search"') !== false, 'no search without a catalog page');
}
