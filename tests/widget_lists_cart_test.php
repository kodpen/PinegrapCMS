<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the cart list (includes/fn/widgets_lists_cart.php): the
 * visitor's cart lines as a list in the cart link (a mini cart) and beside
 * the cart widget's own rows, drawn by the finish pass from the rows the
 * renderers publish.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Lines in the cart widget's item shape: two to recipient 5, one to none.
function _widget_cart_items()
{
	$item = function ($id, $short, $ship_to, $url = '') {
		return array('item_id' => $id, 'name' => 'SKU-' . $id, 'short_description' => $short, 'qty' => 2,
			'unit_price' => '₺5,00', 'line_total' => '₺10,00', 'image' => '', 'url' => $url,
			'remove_url' => '/software/remove_item_from_cart.php?order_item_id=' . $id . '&token=t', 'ship_to_id' => $ship_to);
	};
	return array($item(1, 'Mug & cup', 5, '/p/mug'), $item(2, '', 5), $item(3, 'Tea', 0));
}

function test_widget_lists_cart_sources_and_rows()
{
	$sources = pg_sw_list_cart_sources();
	pg_assert_same(array('cart.items', 'cart.item_gift_cards', 'cart.item_schedule', 'cart.item_forms', 'cart.item_form_fields', 'cart.item_form_field_options'), array_keys($sources));
	pg_assert_same('__ship_to_key', $sources['cart.items']['key']);
	pg_assert_same(array('cart.items'), array_keys(pg_sw_kind_lists('cart_link')), 'a gift card list is the cart widget\'s');
	pg_assert_same(array('cart.items', 'cart.item_gift_cards', 'cart.item_schedule', 'cart.item_forms', 'cart.item_form_fields', 'cart.item_form_field_options'), array_keys(pg_sw_kind_lists('shopping_cart')));
	pg_assert_same(array('cart.items', 'checkout.offers', 'checkout.suggestions', 'checkout.gift_cards', 'checkout.recipients'), array_keys(pg_sw_kind_lists('express_order')), 'nor the express order\'s');

	$rows = pg_sw_list_cart_rows(_widget_cart_items());
	$tokens = array();
	foreach ($sources['cart.items']['tokens'] as $pair) $tokens[] = $pair[0];
	foreach ($rows['cart.items'][0] as $row) pg_assert_same($tokens, array_keys($row), 'row keys');
	pg_assert_same(array('1', '2', '3'), array_column($rows['cart.items'][0], '__item_id'));
	pg_assert_same(array('1', '2'), array_column($rows['cart.items'][5], '__item_id'), 'a recipient\'s lines');
	pg_assert_same('SKU-2', $rows['cart.items'][0][1]['__item_image_alt'], 'the alt falls back to the name');
	pg_assert_same('#', $rows['cart.items'][0][1]['__item_url'], 'no detail page: #');
	pg_assert_same(array('cart.items' => array(), 'cart.item_gift_cards' => array(), 'cart.item_schedule' => array(), 'cart.item_forms' => array(), 'cart.item_form_fields' => array(), 'cart.item_form_field_options' => array()), pg_sw_list_cart_rows(array()));
}

// A mini cart in the cart link: the lines escaped once, the remove link
// kept whole, no list in an empty cart.
function test_widget_lists_cart_link_end_to_end()
{
	$n = function ($type, $props, $kids = array()) { return array('type' => $type, 'props' => $props, 'children' => $kids); };
	$tree = $n('root', array(), array(
		$n('semantic', array('tag' => 'span', 'cssClass' => 'count', 'text' => '0', '_bindings' => array('text' => '__cart_count'))),
		$n('semantic', array('tag' => 'ul', 'cssClass' => 'mini'), array($n('loop_area', array('source' => 'cart.items'), array(
			$n('semantic', array('tag' => 'li', 'cssClass' => 'line'), array(
				$n('semantic', array('tag' => 'a', 'cssClass' => 'name', 'href' => '#', 'text' => 'x', '_bindings' => array('href' => '__item_url', 'text' => '__item_short_description'))),
				$n('semantic', array('tag' => 'a', 'cssClass' => 'rm', 'href' => '#', 'text' => '×', '_bindings' => array('href' => '__item_remove_url'))),
			)),
		)))),
	));
	$draw = function ($items) use ($tree) {
		$scope = pg_sw_finish_open(array('sid' => 51, 'kind' => 'cart_link', 'email' => false));
		try {
			$html = trim(_render_tree_node($tree, 0, 0));
			pg_sw_list_publish('cart', pg_sw_list_cart_rows($items));
			$html = _pg_member_apply_tokens($html, array('__cart_count' => '3', '__ship_to_key' => '0'));
		} finally {
			pg_sw_finish_close();
		}
		return pg_sw_finish_widget_html($html, $scope);
	};
	$out = $draw(_widget_cart_items());
	pg_assert_same(false, strpos($out, '^^'));
	pg_assert_same(3, substr_count($out, 'class="line"'));
	pg_assert_true(strpos($out, '>Mug &amp; cup</a>') !== false, 'the name escaped once');
	pg_assert_true(strpos($out, 'href="/p/mug"') !== false, 'the product link');
	pg_assert_true(strpos($out, 'href="/software/remove_item_from_cart.php?order_item_id=3&amp;token=t"') !== false, 'the remove link');
	$out = $draw(array());
	pg_assert_same(0, substr_count($out, 'class="line"'), 'an empty cart lists nothing');
}
