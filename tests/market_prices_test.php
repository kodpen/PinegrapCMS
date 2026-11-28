<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a product's fixed price in a market
 * (includes/fn/market_prices.php): what a write changes, a typed price, the
 * product form's posted prices, the export's columns and the import's.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// A new or changed price is set, none or 0 takes a stored one off, a
// market not named and an unchanged price are left alone.
function test_market_prices_changes()
{
	$changes = pg_market_prices_changes(
		array(1 => 1000, 2 => 2000, 3 => 3000),
		array(1 => 1000, 2 => 2500, 3 => null, 4 => 4000, 5 => null, 6 => 0, 0 => 99)
	);

	pg_assert_same(array(2 => 2500, 4 => 4000), $changes['set']);
	pg_assert_same(array(3), $changes['remove']);

	$changes = pg_market_prices_changes(array(7 => 500), array(7 => 0));
	pg_assert_same(array(), $changes['set']);
	pg_assert_same(array(7), $changes['remove'], '0 takes the price off');
}

// '' is none, an amount is minor units rounded, a minus sign or a word is
// refused.
function test_market_prices_cell()
{
	pg_assert_same(null, pg_market_price_cell(''));
	pg_assert_same(null, pg_market_price_cell('   '));
	pg_assert_same(1250, pg_market_price_cell('12.50'));
	pg_assert_same(1250, pg_market_price_cell('12,50'));
	pg_assert_same(123456, pg_market_price_cell('1.234,56'));
	pg_assert_same(1999, pg_market_price_cell('€ 19.99'));
	pg_assert_same(0, pg_market_price_cell('0'));
	pg_assert_same(false, pg_market_price_cell('-5'));
	pg_assert_same(false, pg_market_price_cell('abc'));
}

// Only the markets the card drew are read; a price that is not an amount
// names its market.
function test_market_prices_posted()
{
	$markets = array(
		3 => array('name' => 'Europe'),
		5 => array('name' => 'United States'),
		8 => array('name' => 'Japan'),
	);

	$read = pg_market_prices_posted(array(
		'market_price_drawn_3' => '1', 'market_price_3' => '9,99',
		'market_price_drawn_5' => '1', 'market_price_5' => '',
		'market_price_8' => '100',
	), $markets);

	pg_assert_same(array(3 => 999, 5 => null), $read['prices']);
	pg_assert_same(array(), $read['refused']);

	$read = pg_market_prices_posted(array(
		'market_price_drawn_3' => '1', 'market_price_3' => 'ten',
		'market_price_drawn_5' => '1', 'market_price_5' => array('1'),
	), $markets);

	pg_assert_same(array(5 => null), $read['prices'], 'a list is no value');
	pg_assert_same(array('Europe'), $read['refused']);
}

// One price:<name> column per market, a decimal or an empty cell.
function test_market_prices_export()
{
	$markets = array(3 => array('name' => 'Europe'), 5 => array('name' => 'United States'));

	pg_assert_same(array('price:Europe', 'price:United States'), pg_market_price_export_headings($markets));
	pg_assert_same(array('12.50', ''), pg_market_price_export_cells($markets, array(3 => 1250)));
	pg_assert_same(array('', '0.99'), pg_market_price_export_cells($markets, array(5 => 99)));
}

// A price heading is matched to its market by name, case and spaces aside;
// one naming no market, or a market twice, is unknown.
function test_market_prices_import_columns()
{
	$markets = array(3 => array('name' => 'Europe'), 5 => array('name' => 'United States'));
	$read = pg_market_price_import_columns(
		array('name', 'price', 'Price: united states ', 'price:Europe', 'price:Mars', 'PRICE:europe', 'stock:Main'),
		$markets
	);

	pg_assert_same(array(2 => 5, 3 => 3), $read['positions']);
	pg_assert_same(array('price:Mars', 'PRICE:europe'), $read['unknown']);
	pg_assert_true(pg_market_price_is_column_heading(' Price:Europe'));
	pg_assert_true(!pg_market_price_is_column_heading('price'));
}

// An empty cell leaves the market alone, 0 takes the price off, a cell that
// is not an amount refuses the row.
function test_market_prices_import_row()
{
	$positions = array(2 => 5, 3 => 3);

	pg_assert_same(array(5 => 1999, 3 => 0), pg_market_price_import_row(array('a', 'b', '19.99', '0'), $positions));
	pg_assert_same(array(3 => 1000), pg_market_price_import_row(array('a', 'b', '', '10'), $positions));
	pg_assert_same(array(), pg_market_price_import_row(array('a', 'b'), $positions), 'a short row');
	pg_assert_same(false, pg_market_price_import_row(array('a', 'b', 'x', '10'), $positions));
}

// The card names each market with its currency, a stored price as a
// decimal, the symbol raw (it is stored HTML), and a switched-off market.
function test_market_prices_card_html()
{
	$html = pg_market_prices_card_html(
		array(
			3 => array('name' => 'Europe <EU>', 'currency_code' => 'eur', 'enabled' => 1),
			5 => array('name' => 'United States', 'currency_code' => 'USD', 'enabled' => 0),
		),
		array(3 => 1250),
		array('EUR' => '&euro;')
	);

	pg_assert_true(strpos($html, 'Europe &lt;EU&gt;') !== false, 'the name is escaped');
	pg_assert_true(strpos($html, 'name="market_price_drawn_3"') !== false);
	pg_assert_true(strpos($html, 'value="12.50"') !== false);
	pg_assert_true(strpos($html, '&euro;</span>') !== false, 'the symbol raw');
	pg_assert_true(strpos($html, '>USD</span>') !== false, 'the code without a symbol');
	pg_assert_true(substr_count($html, 'pg-tone-gray') === 1, 'one market is off');
}

// The cart, the product form, the export and the import call the module;
// a deleted market or product takes its prices along.
function test_market_prices_wiring()
{
	$cart = file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/market_cart.php');
	$ecommerce = file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/ecommerce.php');
	$builder = file_get_contents(PG_FUNCTIONS_DIR . '/product_builder.php');
	$markets = file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/markets.php');

	pg_assert_true(strpos($cart, 'pg_market_fixed_price($state[\'market_id\'], $product_id)') !== false);
	pg_assert_true(strpos($ecommerce, "pg_market_cart_price(\$product['price'], \$product['id'])") !== false);
	pg_assert_true(strpos($ecommerce, "pg_market_cart_price(\$order_items[\$key]['product_price'], \$order_items[\$key]['product_id'])") !== false);
	pg_assert_true(strpos($builder, 'pg_market_prices_card($pg_product_id)') !== false);
	pg_assert_true(strpos($builder, 'pg_market_prices_save_posted($product_id, $_POST)') !== false);
	pg_assert_true(strpos($builder, 'pg_market_prices_delete_product($product_id)') !== false);
	pg_assert_true(strpos($markets, 'pg_market_prices_delete_market($market_id)') !== false);
	pg_assert_true(strpos(file_get_contents(PG_FUNCTIONS_DIR . '/export_products_f.php'), 'pg_market_price_export_cells(') !== false);
	pg_assert_true(strpos(file_get_contents(PG_FUNCTIONS_DIR . '/import_products.php'), 'pg_market_prices_save($product_id, $row_market_prices)') !== false);
}
