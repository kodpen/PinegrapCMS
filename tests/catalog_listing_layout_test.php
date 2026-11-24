<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the catalog listing widget drawing a classic catalog layout
 * converted into its design elements (pg_layout_convert('catalog', …)):
 * a row's own flags (pg_catalog_row_flags()) decide what its card shows,
 * the surround's flags (pg_catalog_listing_flags()) whether the list or the
 * layout's words for no result show.
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

function _test_cll_tree($site, $id)
{
	$r = pg_layout_convert('catalog', (string)file_get_contents(PG_FUNCTIONS_DIR . '/data/backups/' . $site . '/layouts/' . $id . '.php'), array());
	return $r['tree'];
}

function test_catalog_row_flags()
{
	pg_assert_same(array(
		'catalog_item_has_image' => true,
		'catalog_item_has_short_description' => false,
		'catalog_item_has_price' => true,
	), pg_catalog_row_flags(array('__image_url' => '/a.jpg', '__short_description' => '', '__price_formatted' => '$4.00')));
	pg_assert_same(array(false, false, false), array_values(pg_catalog_row_flags(array())), 'a group with nothing');
}

function test_catalog_listing_flags()
{
	$f = pg_catalog_listing_flags(0, false, array());
	pg_assert_same(array(false, true, false, true, false, false, false, true), array_values($f), 'nothing, browsing, one currency');
	pg_assert_same(array('has_items', 'no_items', 'has_query', 'no_query', 'many_results', 'one_result', 'has_currency_choice', 'no_currency_choice'), array_keys($f));
	$f = pg_catalog_listing_flags(1, true, array(array('id' => 1), array('id' => 2)));
	pg_assert_same(array(true, false, true, false, false, true, true, false), array_values($f), 'one found, two currencies');
	pg_assert_true(pg_catalog_listing_flags(2, true, array())['many_results']);
}

// A converted card: a product with no short description shows its picture
// and price, a group with no price its picture and words; the card's
// markers are gone.
function test_catalog_converted_card()
{
	$tree = _test_cll_tree('english_default', 985);
	$split = _split_widget_tree(json_decode(json_encode($tree), true));
	$cards = _pg_catalog_card_templates($split['loop_children']);
	pg_assert_same(true, $cards['row_flagged']);
	$product = array('__image_url' => '/shirt.jpg', '__short_description' => '', '__price_formatted' => '$4.00', '__detail_url' => '/p/shirt');
	$html = pg_sw_fill_longest(_pg_catalog_card_for($cards, false, array('is_bundle' => false, 'has_bundle_saving' => false), pg_catalog_row_flags($product)), pg_sw_escape_token_values($product));
	pg_assert_true(strpos($html, 'src="/shirt.jpg"') !== false && strpos($html, 'href="/p/shirt"') !== false, 'the picture and the address');
	pg_assert_true(strpos($html, '$4.00') !== false && strpos($html, 'mb0') === false, 'the price, no words');
	pg_assert_false(strpos($html, 'pg-vis') !== false, 'no marker');
	$group = array('__image_url' => '/g.jpg', '__short_description' => 'Shirts', '__price_formatted' => '');
	$html = _pg_catalog_card_for($cards, false, array('no_rating' => false, 'is_bundle' => false, 'has_bundle_saving' => false), pg_catalog_row_flags($group));
	pg_assert_true(strpos($html, 'mb0') !== false && strpos($html, 'mb16') === false, 'the group\'s words, no price');
}

// The surround: with results, the list's place and the count of a search;
// with none, the layout's words for browsing and no list's place.
function test_catalog_converted_surround()
{
	$tree = _test_cll_tree('english_default', 985);
	$split = _split_widget_tree(json_decode(json_encode($tree), true));
	_pg_sw_mark_visibility($split['static_tree'], array_keys(pg_catalog_listing_flags(0, false, array())));
	$static = trim(_render_tree_node($split['static_tree'], 0, 0));
	$found = _pg_sw_resolve_visibility($static, pg_catalog_listing_flags(3, true, array()));
	pg_assert_true(strpos($found, '<!--pg-loop-slot-->') !== false, 'the list\'s place');
	pg_assert_contains('<h5>Found ^^__result_count^^ products containing <strong>^^__search_query^^</strong>.</h5>', $found, 'the count and its plural');
	pg_assert_false(strpos($found, ' product containing') !== false, 'not the singular');
	$one = _pg_sw_resolve_visibility($static, pg_catalog_listing_flags(1, true, array()));
	pg_assert_contains('<h5>Found ^^__result_count^^ product containing <strong>^^__search_query^^</strong>.</h5>', $one, 'one result');
	pg_assert_false(strpos($found, 'Sorry') !== false, 'no words for no result');
	$none = _pg_sw_resolve_visibility($static, pg_catalog_listing_flags(0, false, array()));
	pg_assert_false(strpos($none, '<!--pg-loop-slot-->') !== false, 'no list\'s place');
	pg_assert_true(strpos($none, 'no products were found in this product proup') !== false, 'the words for an empty group');
	pg_assert_false(strpos($none, 'containing') !== false, 'not those of a search');
	pg_assert_false(strpos($none, 'currency_id') !== false, 'no currency choice');
}
