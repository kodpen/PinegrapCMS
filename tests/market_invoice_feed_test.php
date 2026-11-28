<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for an order charged in a market's currency reaching the ERP and the
 * product feed: the invoice's currency and rate (erp_order_invoice_currency()
 * and the writer that uses it), and a feed priced in a market
 * (pg_product_feed_market_for(), pg_product_feed_market_prices(), the setup's
 * market_id and the item it gives).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The body of the order invoice writer, up to its closing brace.
function market_invoice_feed_test_writer()
{
	$source = str_replace("\r\n", "\n", (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/erp/order_bridge.php'));
	$from = strpos($source, 'function erp_invoice_from_order_locked(');

	if ($from === false) {
		return '';
	}

	$to = strpos($source, "\n}\n", $from);

	return substr($source, $from, (($to === false) ? strlen($source) : $to) - $from);
}

// An order in the home currency, or stored before the columns, is home at 1;
// another is in its charged currency at its own rate, dated its day.
function test_market_invoice_currency_of_an_order()
{
	$home = erp_order_invoice_currency(array('charged_currency' => '', 'charged_rate' => '0'), 'TRY');
	pg_assert_same('TRY', $home['currency'], 'no charged currency is home');
	pg_assert_same(1.0, $home['rate'], 'at 1');
	pg_assert_same('base', $home['source'], 'source base');
	pg_assert_same('', $home['rate_date'], 'dated the issue day');
	pg_assert_same('', $home['error']);

	$old = erp_order_invoice_currency(array('total' => 100), 'TRY');
	pg_assert_same('TRY', $old['currency'], 'an order read before the columns is home');

	$same = erp_order_invoice_currency(array('charged_currency' => 'try', 'charged_rate' => '0'), 'TRY');
	pg_assert_same('TRY', $same['currency'], 'the home code is home');
	pg_assert_same('', $same['error']);

	$placed = mktime(12, 0, 0, 11, 20, 2026);
	$usd = erp_order_invoice_currency(array('charged_currency' => 'USD', 'charged_rate' => '34.12345678', 'order_date' => (string) $placed), 'TRY');
	pg_assert_same('USD', $usd['currency'], 'the charged currency');
	pg_assert_same(34.123457, $usd['rate'], 'the order\'s rate, rounded to the six places the ERP keeps');
	pg_assert_same('2026-11-20', $usd['rate_date'], 'dated the order\'s day');
	pg_assert_same('order', $usd['source']);
	pg_assert_same('', $usd['error']);

	$unknown = erp_order_invoice_currency(array('charged_currency' => 'EUR', 'charged_rate' => '0.00000000', 'order_date' => (string) $placed), 'TRY');
	pg_assert_same('EUR', $unknown['currency']);
	pg_assert_same('no_rate', $unknown['error'], 'a charged currency without a rate is refused, never priced at today\'s rate');

	$tiny = erp_order_invoice_currency(array('charged_currency' => 'IDR', 'charged_rate' => '0.00000040'), 'TRY');
	pg_assert_same('no_rate', $tiny['error'], 'a rate the six places cannot hold is no rate');
}

// The writer stores the order's currency and rate on the invoice, its base
// total at that rate, and posts the ledger and the gift card alike.
function test_market_invoice_writer_uses_the_order_currency()
{
	$body = market_invoice_feed_test_writer();
	pg_assert_true($body !== '', 'the writer is there');

	pg_assert_contains("erp_order_invoice_currency(\$order, erp_base_currency())", $body, 'the order\'s currency is read');
	pg_assert_contains("currency = '\" . escape(\$money['currency']) . \"'", $body, 'the invoice row\'s currency');
	pg_assert_contains("number_format(\$money['rate'], 6, '.', '')", $body, 'its rate');
	pg_assert_contains("exchange_rate_source = '\" . escape(\$money['source']) . \"'", $body, 'its rate source');
	pg_assert_contains("erp_to_base((int) \$totals['grand_total'], \$money['rate'])", $body, 'the base total at the order\'s rate');
	pg_assert_true(substr_count($body, "'exchange_rate' => \$money['rate']") === 2, 'the ledger debit and the gift card credit convert alike');
	pg_assert_true(strpos($body, "currency = '\" . escape(erp_base_currency()) . \"'") === false, 'no longer always the home currency');

	$refusal = strpos($body, "\$money['error'] === 'no_rate'");
	$begin = strpos($body, 'erp_tx_begin(');
	pg_assert_true(($refusal !== false) && ($begin !== false) && ($refusal < $begin), 'an unknown rate is refused before the transaction');
}

// The gift card credit takes the invoice's currency and its reversal the
// credit's own.
function test_market_invoice_gift_card_currency()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/erp/settlement.php');

	pg_assert_contains("\$amount_base = \$foreign ? erp_to_base(\$amount, \$exchange_rate) : \$amount;", $source, 'the credit converts at the given rate');
	pg_assert_contains("'amount_base' => (int) \$credit['amount_base'],", $source, 'the reversal takes back the same base value');
	pg_assert_contains("'currency' => (string) \$credit['currency'],", $source, 'in the same currency');
}

// The market a feed is priced in: none, one that cannot price, one that can.
function test_market_feed_market_for()
{
	$markets = array(
		3 => array('id' => 3, 'currency_code' => 'EUR', 'rounding' => 'end99'),
		4 => array('id' => 4, 'currency_code' => 'TRY', 'rounding' => 'whole'),
		5 => array('id' => 5, 'currency_code' => 'GBP', 'rounding' => 'none'),
	);
	$rates = array('EUR' => '0.02700000', 'GBP' => '0');

	pg_assert_same(null, pg_product_feed_market_for(0, $markets, $rates, 'TRY'), '0 is the base currency');
	pg_assert_same(false, pg_product_feed_market_for(9, $markets, $rates, 'TRY'), 'a market that is not enabled fails the build');
	pg_assert_same(false, pg_product_feed_market_for(5, $markets, $rates, 'TRY'), 'a currency without a rate fails the build');

	pg_assert_same(array('id' => 3, 'currency' => 'EUR', 'rate' => 0.027, 'rounding' => 'end99'), pg_product_feed_market_for(3, $markets, $rates, 'TRY'));
	pg_assert_same(array('id' => 4, 'currency' => 'TRY', 'rate' => 1.0, 'rounding' => 'whole'), pg_product_feed_market_for('4', $markets, $rates, 'try'), 'a market in the base currency still rounds');
}

// A price and a sale price as the market charges them.
function test_market_feed_prices()
{
	$eur = array('id' => 3, 'currency' => 'EUR', 'rate' => 0.027, 'rounding' => 'end99');

	pg_assert_same(array('price' => 149900, 'sale_price' => 119900), pg_product_feed_market_prices(149900, 119900, null, null), 'no market: as they are');
	pg_assert_same(array('price' => 149900, 'sale_price' => null), pg_product_feed_market_prices('149900', null, null, null));

	// 1,499.00 TRY at 0.027 is 40.47 EUR, rounded up to 40.99.
	pg_assert_same(array('price' => 4099, 'sale_price' => null), pg_product_feed_market_prices(149900, null, null, $eur), 'converted and rounded by the rule');
	pg_assert_same(array('price' => 3500, 'sale_price' => null), pg_product_feed_market_prices(149900, null, 3500, $eur), 'the fixed price, never rounded');

	// A saving of 300.00 TRY is 8.10 EUR.
	pg_assert_same(array('price' => 4099, 'sale_price' => 3289), pg_product_feed_market_prices(149900, 119900, null, $eur), 'the saving converted off the market price');
	pg_assert_same(array('price' => 3500, 'sale_price' => 2690), pg_product_feed_market_prices(149900, 119900, 3500, $eur), 'off the fixed price too');
	pg_assert_same(array('price' => 500, 'sale_price' => null), pg_product_feed_market_prices(149900, 119900, 500, $eur), 'a saving that leaves nothing is no sale price');
}

// The item a product becomes in a market's feed.
function test_market_feed_item()
{
	$product = array(
		'id' => '12', 'name' => 'CHR-01', 'title' => '', 'short_description' => 'Chair',
		'full_description' => '', 'meta_description' => '', 'price' => '149900',
		'image_name' => 'chair.jpg', 'address_name' => 'chair', 'selection_type' => 'quantity',
		'recurring' => '0', 'inventory' => '0', 'inventory_quantity' => '0', 'backorder' => '0',
		'gtin' => '', 'brand' => 'Kodpen', 'mpn' => '', 'google_product_category' => '',
	);
	$site = array('base_url' => 'https://shop.example/', 'detail_page' => 'product', 'currency' => 'EUR', 'default_brand' => '',
		'market' => array('id' => 3, 'currency' => 'EUR', 'rate' => 0.027, 'rounding' => 'end99'));

	$item = pg_product_feed_item($product, array('sale_price' => 119900, 'fixed_price' => 3500), $site);
	pg_assert_same(3500, $item['price'], 'the fixed price');
	pg_assert_same(2690, $item['sale_price']);
	pg_assert_same('EUR', $item['currency']);
	pg_assert_same('35.00 EUR', pg_product_feed_money($item['price'], $item['currency']));

	unset($site['market']);
	$site['currency'] = 'TRY';
	$base = pg_product_feed_item($product, array('sale_price' => 119900), $site);
	pg_assert_same(149900, $base['price'], 'no market: the base price, as before');
	pg_assert_same(119900, $base['sale_price']);
}

// The setup keeps market_id, a save takes only a market the card offered,
// and a build with a market that cannot price says why.
function test_market_feed_setup()
{
	pg_assert_same(0, pg_product_feed_settings_parse('')['market_id'], 'none stored is the base currency');
	pg_assert_same(3, pg_product_feed_settings_parse('{"market_id":"3"}')['market_id']);
	pg_assert_same(0, pg_product_feed_settings_parse('{"market_id":-2}')['market_id'], 'never below 0');
	pg_assert_same(3, pg_product_feed_settings_options(pg_product_feed_settings_parse('{"market_id":3}'))['market_id'], 'passed to the build');

	$choices = array(3 => 'Europe (EUR)');
	pg_assert_same(3, pg_product_feed_posted_market('3', $choices));
	pg_assert_same(0, pg_product_feed_posted_market('7', $choices), 'a market not offered');
	pg_assert_same(0, pg_product_feed_posted_market('0', $choices));
	pg_assert_same(0, pg_product_feed_posted_market(array('3'), $choices), 'not a scalar');
	pg_assert_same(0, pg_product_feed_posted_market('3x', $choices));

	pg_assert_true(pg_product_feed_error_text('no_market') !== pg_product_feed_error_text('unknown_code'), 'no_market has its own text');

	$save = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/settings/commerce.save.php');
	pg_assert_contains("pg_product_feed_posted_market(post_value('product_feed_market_id'), pg_product_feed_market_choices())", $save, 'the save checks the posted market');
}
