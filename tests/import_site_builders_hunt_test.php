<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for faults found in the Squarespace, Webflow and Wix readers of the
 * content import (includes/import/webflow.php, wix.php, collection.php and
 * the writers they feed): price cells read as the product import reads
 * them, and the cells each fault turned on.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/importers.php');
require_once(PG_FUNCTIONS_DIR . '/includes/import/wix.php');
require_once(PG_FUNCTIONS_DIR . '/includes/import/write_product.php');

function test_import_site_builders_price_cells_read_as_the_product_import()
{
	// A thousands comma was read as a decimal one: 1,299 became 1.30.
	pg_assert_same(129900, pg_import_webflow_money(array('variant price' => '1,299'))['price'], 'Webflow grouped price');
	pg_assert_same(129900, pg_import_webflow_money(array('variant price' => '1,299.00'))['price'], 'Webflow grouped price with cents');
	pg_assert_same(1999, pg_import_webflow_money(array('variant price' => '19.99 USD'))['price'], 'Webflow price with its currency');
	pg_assert_same(1250, pg_import_webflow_money(array('variant price' => '12,50'))['price'], 'Webflow decimal comma kept');
	pg_assert_same(129900, pg_import_wix_cents('1,299'), 'Wix grouped price');
	pg_assert_same(1999, pg_import_wix_cents('$19.99'), 'Wix price with its symbol');
	pg_assert_same(1250, pg_import_wix_cents('12,50'), 'Wix decimal comma kept');
	pg_assert_same(null, pg_import_wix_cents(''), 'empty');
	pg_assert_same(null, pg_import_wix_cents('soon'), 'words');
	pg_assert_contains('soon', pg_import_webflow_money(array('variant price' => 'soon'))['refused'], 'a word is still refused');
}

function test_import_site_builders_date_field_keeps_the_day_written()
{
	require_once(PG_FUNCTIONS_DIR . '/includes/import/write_record.php');

	$zone = date_default_timezone_get();
	date_default_timezone_set('America/New_York');

	$date = function ($cell, $type = 'date') {
		$rows = pg_import_record_column_rows($cell, array('type' => $type, 'name' => 'Day'), array())['rows'];

		return $rows ? $rows[0]['data'] : '';
	};

	// Midnight UTC is the evening before in New York: a date field took
	// that day.
	pg_assert_same('2024-03-15', $date('Fri Mar 15 2024 00:00:00 GMT+0000 (Coordinated Universal Time)'), 'Webflow date');
	pg_assert_same('2024-03-15', $date('2024-03-15T00:00:00Z'), 'Wix date');
	pg_assert_same('2024-03-15', $date('2024-03-15'), 'a date without a zone');
	pg_assert_same('2024-03-14 20:00', $date('2024-03-15T00:00:00Z', 'date and time'), 'a date and time is still shown in the site zone');
	pg_assert_same('', $date('not a date'), 'unreadable');
	pg_assert_same('', pg_import_collection_date('(Coordinated Universal Time)'), 'only a zone name');

	date_default_timezone_set($zone);
}

function test_import_site_builders_wix_surcharge_below_the_product_price()
{
	$product = array('price' => '40', 'discountmode' => '', 'discountvalue' => '');

	// A variant cheaper than the product carries a negative surcharge: the
	// variant was refused as "not an amount".
	$money = pg_import_wix_money($product, array('price' => '', 'surcharge' => '-5'), true);
	pg_assert_same('', $money['refused'], 'not refused');
	pg_assert_same(3500, $money['price'], 'the product price less five');
	pg_assert_same(null, $money['compare_at'], 'no struck-through price');

	pg_assert_same(4500, pg_import_wix_money($product, array('price' => '', 'surcharge' => '5'), true)['price'], 'a surcharge still adds');
	pg_assert_same(0, pg_import_wix_money($product, array('price' => '', 'surcharge' => '-50'), true)['price'], 'never below zero');
	pg_assert_contains('-x', pg_import_wix_money($product, array('price' => '', 'surcharge' => '-x'), true)['refused'], 'a word is still refused');
}

function test_import_site_builders_wix_inventory_figures()
{
	// The cell lost its dot and minus sign before it was read as a number.
	pg_assert_same(2, pg_import_wix_stock('2.0'), 'a count a spreadsheet wrote with a decimal');
	pg_assert_same(0, pg_import_wix_stock('-3'), 'below zero is none left');
	pg_assert_same(1000, pg_import_wix_stock('1,000'), 'grouped thousands');
	pg_assert_same(12, pg_import_wix_stock(' 12 '), 'a count');
	pg_assert_same(0, pg_import_wix_stock('OUT_OF_STOCK'), 'out of stock');
	pg_assert_same(0, pg_import_wix_stock('OutOfStock'), 'out of stock, previous layout');
	pg_assert_same(null, pg_import_wix_stock('InStock'), 'in stock is not counted');
	pg_assert_same(null, pg_import_wix_stock(''), 'empty');
}

function test_import_site_builders_webflow_redirect_headers_detected()
{
	// The reader takes these headers; the upload refused the file first.
	foreach (array("old path,new path\n/a,/b\n", "from,to\n/a,/b\n", "Redirect From,Redirect To\n/a,/b\n", "source,target\n/a,/b\n", "\"Source\";\"Target\"\n/a;/b\n") as $file) {
		$first = strtok($file, "\n");
		pg_assert_same('webflow', pg_import_detect_kind($file), $first);
		pg_assert_same('redirects', pg_import_webflow_file_kind(str_getcsv($first, (strpos($first, ';') !== false) ? ';' : ',', '"', '\\')), 'reader: ' . $first);
	}

	pg_assert_same('', pg_import_detect_kind("from,subject\na,b\n"), 'other two-cell files stay unknown');
}
