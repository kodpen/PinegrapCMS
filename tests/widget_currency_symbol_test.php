<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The currencies table keeps a symbol as HTML (&#8378;, &euro;). A system
 * widget value that is escaped afterwards - a token of a map
 * pg_sw_escape_token_values() escapes, a list row, h() - carries money as
 * text (pg_visitor_money_text()), so the visitor reads "₺12,50", not
 * "&#8378;12,50".
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(__DIR__ . '/helpers/source_reader.php');

// The text twin of pg_visitor_money(): the symbol's entity becomes its
// character; a plain symbol reads the same as the markup; a symbol typed as
// markup stays text for h().
function test_widget_currency_symbol_visitor_money_text()
{
	$figure = pg_format_number(1234.5, 2);

	pg_assert_same('₺' . $figure, pg_visitor_money_text(1234.5, '&#8378;'), 'a numeric entity');
	pg_assert_same('€' . $figure, pg_visitor_money_text(1234.5, '&euro;'), 'a named entity');
	pg_assert_same('-₺' . pg_format_number(3, 2), pg_visitor_money_text(-3, '&#8378;'), 'the minus sign before the symbol');
	pg_assert_same(pg_visitor_money(1234.5, '$'), pg_visitor_money_text(1234.5, '$'), 'a plain symbol');
	pg_assert_same('&lt;b&gt;$&lt;/b&gt;' . $figure, h(pg_visitor_money_text(1234.5, '<b>$</b>')), 'markup is text once escaped');
}

// The catalog card's and detail's tokens escaped with the rest of the map:
// a bundle's saving, the reference price, the price before an offer and the
// amount saved.
function test_widget_currency_symbol_catalog_tokens()
{
	$contents = array(array('price' => 1000, 'quantity' => 2), array('price' => 500, 'quantity' => 1));
	$bundle = pg_sw_escape_token_values(_pg_catalog_bundle_values(true, $contents, 2000, '&#8378;'));
	pg_assert_same('₺' . pg_format_number(5, 2), $bundle['__bundle_saving_formatted'], 'a bundle\'s saving');

	$reference = pg_sw_escape_token_values(pg_price_reference_tokens(array('price' => 1500, 'kind' => 'manual', 'days' => 0), '&euro;'));
	pg_assert_same('€' . pg_format_number(15, 2), $reference['__reference_price'], 'the reference price');
	pg_assert_same(lang('Regular price'), $reference['__reference_label']);

	// The price before an offer and the amount saved are written in the
	// widgets' own maps: the product card, the group card and the detail.
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/widgets_catalog.php');
	$code = implode('', array_map('pg_test_source_text', pg_test_source_tokens($source)));
	preg_match_all("/'__(?:original_price|discount_amount)_formatted'\s*=>\s*([^\n]*)/", $code, $matches);
	$written = array_values(array_filter($matches[1], function ($value) { return trim($value) !== "'',"; }));
	pg_assert_same(5, count($written), 'every map that fills them');
	foreach ($written as $value) {
		pg_assert_contains('pg_visitor_money_text(', $value, 'text money');
	}

	// The variant script writes its payload's prices with innerHTML: the
	// reference price goes to it escaped, as markup like the others.
	pg_assert_contains("\$_vp_ref3['reference_price'] = h(\$_vp_ref_tokens['__reference_price']);", $code, 'the variant payload');
}

// A cart line in the cart widget's shape, its money as the widget writes it.
function _wcs_cart_item()
{
	return array('item_id' => 7, 'name' => 'SKU-7', 'short_description' => 'Tea', 'qty' => 2,
		'unit_price' => '&#8378;5,00', 'line_total' => '&#8378;10,00', 'image' => '', 'url' => '',
		'remove_url' => '/software/remove_item_from_cart.php?order_item_id=7&token=t', 'ship_to_id' => 0);
}

// The list rows the finish pass escapes once: the cart lines (cart widget,
// express order, mini cart) and the express order's gift cards.
function test_widget_currency_symbol_list_rows()
{
	$rows = pg_sw_list_cart_rows(array(_wcs_cart_item()));
	pg_assert_same('₺5,00', $rows['cart.items'][0][0]['__item_price'], 'the unit price');
	pg_assert_same('₺10,00', $rows['cart.items'][0][0]['__item_total'], 'the line total');

	$checkout = pg_sw_list_checkout_rows(array(
		'gift_cards' => pg_sw_list_checkout_redeem(array(array('id' => 4, 'code' => 'ABCDEFGH1234', 'old_balance' => 3000, 'givex' => 0)), 1000),
		'fmt'        => function ($cents) { return pg_visitor_money($cents / 100, '&#8378;'); },
	));
	pg_assert_same('₺' . pg_format_number(10, 2), $checkout['checkout.gift_cards'][0][0]['__gift_card_amount'], 'the amount taken');
	pg_assert_same('₺' . pg_format_number(20, 2), $checkout['checkout.gift_cards'][0][0]['__gift_card_balance'], 'the balance left');
}

// A mini cart drawn by the finish pass shows the symbol once.
function test_widget_currency_symbol_mini_cart_end_to_end()
{
	$n = function ($type, $props, $kids = array()) { return array('type' => $type, 'props' => $props, 'children' => $kids); };
	$tree = $n('root', array(), array(
		$n('semantic', array('tag' => 'ul', 'cssClass' => 'mini'), array($n('loop_area', array('source' => 'cart.items'), array(
			$n('semantic', array('tag' => 'li', 'cssClass' => 'line'), array(
				$n('semantic', array('tag' => 'span', 'cssClass' => 'price', 'text' => 'x', '_bindings' => array('text' => '__item_price'))),
				$n('semantic', array('tag' => 'span', 'cssClass' => 'total', 'text' => 'x', '_bindings' => array('text' => '__item_total'))),
			)),
		)))),
	));
	$scope = pg_sw_finish_open(array('sid' => 52, 'kind' => 'cart_link', 'email' => false));
	try {
		$html = trim(_render_tree_node($tree, 0, 0));
		pg_sw_list_publish('cart', pg_sw_list_cart_rows(array(_wcs_cart_item())));
		$html = _pg_member_apply_tokens($html, array('__ship_to_key' => '0'));
	} finally {
		pg_sw_finish_close();
	}
	$out = pg_sw_finish_widget_html($html, $scope);

	pg_assert_contains('>₺5,00</span>', $out, 'the unit price');
	pg_assert_contains('>₺10,00</span>', $out, 'the line total');
	pg_assert_same(false, strpos($out, '&amp;#8378;'), 'never escaped twice');
}
