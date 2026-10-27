<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the express order's lists (includes/fn/widgets_lists_checkout.php):
 * the sources and the kind that offers them, the rows of the applied
 * offers, the suggestions, the gift cards and the recipients, the gift card
 * redemption, the cart lines in the cart list's shape, and the renderer
 * publishing them.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_widget_lists_checkout_sources()
{
	$sources = pg_sw_list_checkout_sources();
	pg_assert_same(array('checkout.offers', 'checkout.suggestions', 'checkout.gift_cards', 'checkout.recipients'), array_keys($sources));
	foreach ($sources as $name => $d) {
		pg_assert_same('checkout', $d['family'], $name);
		pg_assert_same('__ship_to_key', $d['key'], $name);
	}
	pg_assert_same(array(), pg_sw_kind_lists('order_view') ? array_intersect(array_keys(pg_sw_kind_lists('order_view')), array_keys($sources)) : array(), 'only the express order');
}

// Every row carries exactly its source's tokens.
function test_widget_lists_checkout_rows()
{
	$fmt = function ($c) { return '₺' . number_format($c / 100, 2, ',', '.'); };
	$rows = pg_sw_list_checkout_rows(array(
		'offers'      => array(array('id' => 1, 'code' => 'WELCOME', 'description' => '10% off'), array('id' => 2, 'code' => 'SHIP', 'description' => ''),
		                       array('id' => 3, 'code' => '', 'description' => '')),
		'suggestions' => array(array('upsell_message' => 'Add one more <b>', 'upsell_action_page_id' => 7, 'upsell_action_button_label' => ''),
		                       array('description' => 'Free gift', 'upsell_action_page_id' => 0), array('description' => ''), 'x'),
		'gift_cards'  => pg_sw_list_checkout_redeem(array(
			array('id' => 4, 'code' => 'ABCDEFGH1234', 'old_balance' => 3000, 'givex' => 0),
			array('id' => 5, 'code' => '60362812345678901', 'old_balance' => 5000, 'givex' => 1),
		), 4000),
		'recipients'  => array(array('id' => 9, 'ship_to_name' => 'Home', 'first_name' => 'Ada', 'city' => 'İzmir'),
		                       array('id' => 10, 'ship_to_name' => '', 'first_name' => '', 'last_name' => '')),
		'items'       => array(array('ship_to_id' => 9), array('ship_to_id' => 9), array('ship_to_id' => 0)),
		'fmt'         => $fmt,
		'remove_base' => '/software/remove_gift_card_from_order.php?applied_gift_card_id=',
		'remove_tail' => '&send_to=%2Fcheckout&token=t',
		'page_url'    => function ($id) { return '/page-' . $id; },
	));
	$sources = pg_sw_list_checkout_sources();
	foreach ($rows as $source => $by_key) {
		pg_assert_same(array(0), array_keys($by_key), $source . ': the whole order only');
		$names = array_map(function ($t) { return $t[0]; }, $sources[$source]['tokens']);
		foreach ($by_key[0] as $row) {
			pg_assert_same($names, array_keys($row), $source);
		}
	}
	pg_assert_same(array('10% off', 'SHIP'), array_column($rows['checkout.offers'][0], '__offer_text'), 'the code stands in for a missing message');
	pg_assert_same(array(
		array('__offer_text' => 'Add one more <b>', '__offer_action_url' => '/page-7', '__offer_action_label' => 'More Info'),
		array('__offer_text' => 'Free gift', '__offer_action_url' => '', '__offer_action_label' => ''),
	), $rows['checkout.suggestions'][0], 'raw values, a page only when one is named');
	pg_assert_same(array(
		array('__gift_card_code' => '************1234', '__gift_card_amount' => '₺30,00', '__gift_card_balance' => '₺0,00',
			'__gift_card_remove_url' => '/software/remove_gift_card_from_order.php?applied_gift_card_id=4&send_to=%2Fcheckout&token=t'),
		array('__gift_card_code' => '603628*****67890*', '__gift_card_amount' => '₺10,00', '__gift_card_balance' => '₺40,00',
			'__gift_card_remove_url' => '/software/remove_gift_card_from_order.php?applied_gift_card_id=5&send_to=%2Fcheckout&token=t'),
	), $rows['checkout.gift_cards'][0]);
	pg_assert_same(array('9', '1', 'Home', 'İzmir', '2'), array($rows['checkout.recipients'][0][0]['__ship_to_key'], $rows['checkout.recipients'][0][0]['__recipient_index'],
		$rows['checkout.recipients'][0][0]['__recipient_name'], $rows['checkout.recipients'][0][0]['__recipient_address_city'], $rows['checkout.recipients'][0][0]['__ship_to_item_count']));
	pg_assert_same('Recipient 2', $rows['checkout.recipients'][0][1]['__recipient_name'], 'a recipient without a name');
	pg_assert_same('0', $rows['checkout.recipients'][0][1]['__ship_to_item_count']);
}

// The cards take the gift card discount in the order applied, each up to
// its balance.
function test_widget_lists_checkout_redeem()
{
	$cards = array(array('id' => 1, 'code' => 'a', 'old_balance' => 1000, 'givex' => 0), array('id' => 2, 'code' => 'b', 'old_balance' => 1000, 'givex' => 0));
	pg_assert_same(array(array(1000, 0), array(500, 500)), array_map(function ($c) { return array($c['amount'], $c['left']); }, pg_sw_list_checkout_redeem($cards, 1500)));
	pg_assert_same(array(array(0, 1000), array(0, 1000)), array_map(function ($c) { return array($c['amount'], $c['left']); }, pg_sw_list_checkout_redeem($cards, 0)));
	pg_assert_same(array(array(1000, 0), array(1000, 0)), array_map(function ($c) { return array($c['amount'], $c['left']); }, pg_sw_list_checkout_redeem($cards, 9999)));
	pg_assert_same(array(), pg_sw_list_checkout_gift_cards(0, 100), 'no order, no query');
	pg_assert_same(array(), pg_sw_list_checkout_applied_offers(0), 'no order, no query');
}

// The express order's lines in the cart list's shape.
function test_widget_lists_checkout_cart_items()
{
	$fmt = function ($c) { return (string)$c; };
	$items = pg_sw_list_checkout_cart_items(array(
		array('item_id' => 3, 'product_name' => 'SKU', 'short_description' => 'Tea', 'quantity' => 2, 'price' => 250, 'image_name' => '', 'address_name' => '', 'ship_to_id' => 9),
	), $fmt, function ($id) { return '/rm?' . $id; });
	pg_assert_same('500', $items[0]['line_total']);
	pg_assert_same('/rm?3', $items[0]['remove_url']);
	pg_assert_same(9, $items[0]['ship_to_id']);
	pg_assert_same('', $items[0]['url'], 'no address, no link');
	$rows = pg_sw_list_cart_rows($items);
	pg_assert_same(array(0, 9), array_keys($rows['cart.items']), 'filed under the order and the recipient');
}

// The express order renderer publishes the lists it drew and fills the key.
function test_widget_lists_checkout_renderer_wiring()
{
	$src = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/widgets_cart.php');
	pg_assert_contains("pg_sw_list_publish('cart', pg_sw_list_cart_rows(pg_sw_list_checkout_cart_items(", $src);
	pg_assert_contains("pg_sw_list_publish('checkout', pg_sw_list_checkout_rows(", $src);
	pg_assert_contains("\$static_vars['^^__ship_to_key^^'] = '0';", $src);
	$eo = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/widgets_express_order.php');
	pg_assert_contains("'^^__ship_to_key^^'                      => (string)\$rid,", $eo);
	pg_assert_contains('$offers = pg_sw_list_checkout_applied_offers($oid);', $eo);
}
