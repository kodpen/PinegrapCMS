<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for taking a product spreadsheet in (import_products_f.php):
 * pg_products_import_xlsx_date(), which turns a day count into the importer's
 * date text, and pg_products_import_to_csv() over a small .ods written here,
 * which goes through PHPExcel's OpenDocument reader. Also the price and row
 * cell readers of an import file in includes/fn/ecommerce.php
 * (pg_products_import_price_cents() needs product_builder.php), the reason a
 * refused price or stock cell is given, and the import loop's handling of a
 * product in the Recycle Bin.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/import_products_f.php');
require_once(PG_FUNCTIONS_DIR . '/product_builder.php');

// A day count names a calendar day, so the site's time zone must not move it.
function test_products_import_xlsx_date_ignores_the_time_zone()
{
	$zone = date_default_timezone_get();

	try {

		foreach (array('UTC', 'Europe/Istanbul', 'America/New_York') as $name) {
			date_default_timezone_set($name);

			pg_assert_same('2026-10-01', pg_products_import_xlsx_date('46296'), $name . ' day');
			pg_assert_same('2026-10-01 13:45:00', pg_products_import_xlsx_date('46296.572916666664'), $name . ' day and time');
		}

	} finally {
		date_default_timezone_set($zone);
	}
}

// An .ods is read without a fatal error on PHP 8, and its dates and times come
// out as written, whatever the time zone and whatever the time of year.
function test_products_import_ods_to_csv()
{
	if (!class_exists('ZipArchive')) {
		return;
	}

	$dir = sys_get_temp_dir() . '/pg_products_import_test_' . bin2hex(random_bytes(4));
	mkdir($dir);
	$path = $dir . '/products.ods';

	$zip = new ZipArchive();
	pg_assert_true($zip->open($path, ZipArchive::CREATE) === true, 'zip written');

	$zip->addFromString('mimetype', 'application/vnd.oasis.opendocument.spreadsheet');
	$zip->addFromString('META-INF/manifest.xml', '<?xml version="1.0" encoding="UTF-8"?><manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" manifest:version="1.2"><manifest:file-entry manifest:full-path="/" manifest:media-type="application/vnd.oasis.opendocument.spreadsheet"/><manifest:file-entry manifest:full-path="content.xml" manifest:media-type="text/xml"/><manifest:file-entry manifest:full-path="meta.xml" manifest:media-type="text/xml"/></manifest:manifest>');
	$zip->addFromString('meta.xml', '<?xml version="1.0" encoding="UTF-8"?><office:document-meta xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0" office:version="1.2"><office:meta/></office:document-meta>');
	$zip->addFromString('content.xml', '<?xml version="1.0" encoding="UTF-8"?><office:document-content xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0" xmlns:table="urn:oasis:names:tc:opendocument:xmlns:table:1.0" xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0" office:version="1.2"><office:body><office:spreadsheet><table:table table:name="Products">'
		. '<table:table-row>'
		. '<table:table-cell office:value-type="string"><text:p>name</text:p></table:table-cell>'
		. '<table:table-cell office:value-type="string"><text:p>full_description</text:p></table:table-cell>'
		. '<table:table-cell office:value-type="string"><text:p>price</text:p></table:table-cell>'
		. '<table:table-cell office:value-type="string"><text:p>enabled</text:p></table:table-cell>'
		. '<table:table-cell office:value-type="string"><text:p>summer</text:p></table:table-cell>'
		. '<table:table-cell office:value-type="string"><text:p>winter</text:p></table:table-cell>'
		. '<table:table-cell office:value-type="string"><text:p>opens</text:p></table:table-cell>'
		. '</table:table-row>'
		. '<table:table-row>'
		. '<table:table-cell office:value-type="string"><text:p>Çay bardağı</text:p></table:table-cell>'
		. '<table:table-cell office:value-type="string"><text:p>First line</text:p><text:p><text:span>Second </text:span><text:span>line</text:span></text:p></table:table-cell>'
		. '<table:table-cell office:value-type="float" office:value="19.99"><text:p>19.99</text:p></table:table-cell>'
		. '<table:table-cell office:value-type="boolean" office:boolean-value="true"><text:p>TRUE</text:p></table:table-cell>'
		. '<table:table-cell office:value-type="date" office:date-value="2026-07-15"><text:p>2026-07-15</text:p></table:table-cell>'
		. '<table:table-cell office:value-type="date" office:date-value="2026-01-02T13:45:00"><text:p>2026-01-02 13:45</text:p></table:table-cell>'
		. '<table:table-cell office:value-type="time" office:time-value="PT08H05M30S"><text:p>08:05:30</text:p></table:table-cell>'
		. '</table:table-row>'
		. '</table:table></office:spreadsheet></office:body></office:document-content>');
	$zip->close();

	$zone = date_default_timezone_get();

	// PHPExcel predates PHP 8.2 and raises deprecations of its own while it
	// loads; they are not what this test is about.
	$reporting = error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);

	try {

		foreach (array('UTC', 'Europe/Istanbul', 'America/New_York') as $name) {
			date_default_timezone_set($name);

			$error = '';
			$csv_path = pg_products_import_to_csv($path, 'Products.ODS', $error);

			pg_assert_same('', $error, $name . ' read');

			if ($csv_path === '') {
				continue;
			}

			$handle = fopen($csv_path, 'r');
			$rows = array();

			while (($row = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
				$rows[] = $row;
			}

			fclose($handle);
			unlink($csv_path);

			pg_assert_same(array(
				array('name', 'full_description', 'price', 'enabled', 'summer', 'winter', 'opens'),
				array('Çay bardağı', "First line\nSecond line", '19.99', '1', '2026-07-15', '2026-01-02 13:45:00', '1970-01-01 08:05:30'),
			), $rows, $name . ' rows');
		}

		// A worksheet's destructor creates PHPExcel's calculation engine, which
		// raises the same deprecations; collected here rather than at shutdown.
		gc_collect_cycles();

	} finally {
		error_reporting($reporting);
		date_default_timezone_set($zone);
		unlink($path);
		rmdir($dir);
	}
}

// A price cell becomes minor units one way whatever wrote it: a number a
// spreadsheet stored (a dot, any number of decimals) is read as a number, and
// a typed figure with a comma, a symbol or grouping is read as the product
// screen reads it. Anything else is not an amount.
function test_products_import_price_cents()
{
	pg_assert_same(1250, pg_products_import_price_cents('12,50'), 'a Turkish decimal comma');
	pg_assert_same(1500, pg_products_import_price_cents('₺15,00'), 'a currency symbol in front');
	pg_assert_same(1500, pg_products_import_price_cents('15,00 TL'), 'a currency code after it');
	pg_assert_same(123456, pg_products_import_price_cents('1.234,56'), 'Turkish grouping');
	pg_assert_same(123456, pg_products_import_price_cents('1,234.56'), 'English grouping');
	pg_assert_same(123456, pg_products_import_price_cents('1 234,56'), 'a space groups thousands');
	pg_assert_same(123400, pg_products_import_price_cents('1,234'), 'a comma before three digits groups');
	pg_assert_same(149999, pg_products_import_price_cents('1499.99'), 'a stored number');
	pg_assert_same(179999, pg_products_import_price_cents('1799.988'), 'a stored number with three decimals is rounded, not grouped');
	pg_assert_same(179999, pg_products_import_price_cents('1799.9879999999998'), 'a formula result with seventeen digits');
	pg_assert_same(1500, pg_products_import_price_cents('15'), 'a whole number');
	pg_assert_same(100000, pg_products_import_price_cents('1E+3'), 'a stored number in exponent form');
	pg_assert_same(0, pg_products_import_price_cents('0'), 'zero');
	pg_assert_same(1005, pg_products_import_price_cents(' 10.05 '), 'spaces around');
	pg_assert_same(null, pg_products_import_price_cents('abc'), 'words');
	pg_assert_same(null, pg_products_import_price_cents('12/50'), 'a slash');
	pg_assert_same(null, pg_products_import_price_cents('-5'), 'a negative amount');
	pg_assert_same(null, pg_products_import_price_cents(''), 'nothing');
	pg_assert_same(null, pg_products_import_price_cents('₺'), 'a symbol alone');
	pg_assert_same(null, pg_products_import_price_cents('99999999999'), 'more than the price column holds');
	pg_assert_same(null, pg_products_import_price_cents('1234567890123456789,00'), 'too many digits to read exactly');
	pg_assert_same(123400, pg_products_import_price_cents('1.234 TL'), 'with a currency code a dot before three digits groups');
	pg_assert_same(2147483647, pg_products_import_price_cents('21.474.836,47'), 'the price column top, typed');
	pg_assert_same(null, pg_products_import_price_cents('21.474.836,48'), 'one cent above the price column top, typed');
	pg_assert_same(null, pg_products_import_price_cents('25.000.000,00'), 'a typed amount above the top is not wrapped by the int cast');
}

// The money cells of one row, read before the database is touched. The header
// of an export: name, price, extra_shipping_cost, compare_at_price.
function test_products_import_row_prices()
{
	$keys = array('price' => 1, 'extra_shipping_cost' => 2, 'compare_at_price' => 3);

	pg_assert_same(
		array('prices' => array('price' => 1250, 'extra_shipping_cost' => 0), 'refused' => false, 'too_large' => false),
		pg_products_import_row_prices(array('Kalem', '12,50', '0.00', ''), $keys),
		'an empty compare-at cell leaves the price read'
	);
	pg_assert_same(
		array('prices' => array(), 'refused' => true, 'too_large' => false),
		pg_products_import_row_prices(array('Kalem', 'abc', '0.00', ''), $keys),
		'a price that is not an amount refuses the row'
	);
	pg_assert_same(
		array('prices' => array(), 'refused' => true, 'too_large' => true),
		pg_products_import_row_prices(array('Kalem', '30.000.000,00', '0.00', ''), $keys),
		'a price above the column top refuses the row as too large'
	);
	pg_assert_same(
		array('prices' => array(), 'refused' => true, 'too_large' => true),
		pg_products_import_row_prices(array('Kalem', '10,00', '', '25.000.000,00'), $keys),
		'so does a compare-at price above the top'
	);
	pg_assert_same(
		array('prices' => array('price' => 1250, 'extra_shipping_cost' => 0), 'refused' => false, 'too_large' => false),
		pg_products_import_row_prices(array('Kalem', '12,50', '0.00', '-5'), $keys),
		'a compare-at price starting with a minus sign is left out without refusing the row'
	);
	pg_assert_same(
		array('prices' => array('price' => 1250, 'compare_at_price' => 1500), 'refused' => false, 'too_large' => false),
		pg_products_import_row_prices(array('Kalem', '12,50', ' ', '15'), $keys),
		'a cell of spaces alone is none'
	);
	pg_assert_same(
		array('prices' => array('price' => 1250), 'refused' => false, 'too_large' => false),
		pg_products_import_row_prices(array('Kalem', '12,50'), $keys),
		'a row shorter than the header'
	);
	pg_assert_same(
		array('prices' => array(), 'refused' => false, 'too_large' => false),
		pg_products_import_row_prices(array('Kalem'), array()),
		'a file without money columns'
	);
}

// A column the file does not have, or a row shorter than the header, reads empty.
function test_products_import_row_value()
{
	pg_assert_same('a', pg_products_import_row_value(array('a', 'b'), 0), 'a cell');
	pg_assert_same('', pg_products_import_row_value(array('a'), 3), 'past the end of the row');
	pg_assert_same('', pg_products_import_row_value(array('a'), null), 'no such column');
	pg_assert_same('', pg_products_import_row_value(array('a'), false), 'array_search() found none');
}

// The cell readers are functions of the ecommerce module, which functions.php
// loads, not of the screen's root helper file.
function test_products_import_cell_readers_live_in_the_ecommerce_module()
{
	foreach (array('pg_products_import_price_cents', 'pg_products_import_row_prices', 'pg_products_import_row_value') as $name) {
		$function = new ReflectionFunction($name);
		pg_assert_same(realpath(PG_FUNCTIONS_DIR . '/includes/fn/ecommerce.php'), realpath((string) $function->getFileName()), $name);
	}
}

// A refused price cell says why: not an amount, or an amount the price
// columns cannot hold (more than 21.474.836,47, or more than fifteen digits,
// as on the product screen).
function test_products_import_price_read()
{
	pg_assert_same(array('cents' => 1250, 'too_large' => false), pg_products_import_price_read('12,50'), 'an amount');
	pg_assert_same(array('cents' => 2147483647, 'too_large' => false), pg_products_import_price_read('21.474.836,47'), 'the top, typed');
	pg_assert_same(array('cents' => null, 'too_large' => true), pg_products_import_price_read('30.000.000,00'), 'above the top, typed');
	pg_assert_same(array('cents' => null, 'too_large' => true), pg_products_import_price_read('30000000'), 'above the top, a spreadsheet number');
	pg_assert_same(array('cents' => null, 'too_large' => true), pg_products_import_price_read('1E+30'), 'above the top, an exponent');
	pg_assert_same(array('cents' => null, 'too_large' => true), pg_products_import_price_read('1234567890123456789,00'), 'more than fifteen digits');
	pg_assert_same(array('cents' => null, 'too_large' => false), pg_products_import_price_read('abc'), 'words are not an amount');
	pg_assert_same(array('cents' => null, 'too_large' => false), pg_products_import_price_read('-5'), 'a minus sign is not an amount');
	pg_assert_same(array('cents' => null, 'too_large' => false), pg_products_import_price_read('12/50'), 'other characters are not an amount');
	pg_assert_same(pg_products_import_price_read('1.234,56')['cents'], pg_products_import_price_cents('1.234,56'), 'the cents reader reads the same');
}

// A stock cell that is not a whole count of zero or more says why: a number
// with a fraction or a minus sign is not whole; anything else is not a number.
function test_products_import_quantity_refusal()
{
	pg_assert_same('not_whole', pg_products_import_quantity_refusal('1,5'), 'a fraction');
	pg_assert_same('not_whole', pg_products_import_quantity_refusal('-3'), 'a minus sign');
	pg_assert_same('not_whole', pg_products_import_quantity_refusal(' 2.25 '), 'a fraction with a dot');
	pg_assert_same('not_number', pg_products_import_quantity_refusal('ten'), 'words');
	pg_assert_same('not_number', pg_products_import_quantity_refusal('5 pcs'), 'a unit');
}

// Each refusal has its own list and message, so the operator reads the real
// reason: too large, not whole, or not a number / amount.
function test_products_import_names_the_reason_a_row_is_left_out()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/import_products.php');

	pg_assert_contains("if (\$row_read['too_large']) {\n                    \$rows_price_too_large[] = ", $source, 'a price above the top has its own list');
	pg_assert_contains('is too large (at most {var:1}): {var:2}.', $source, 'and says the top');
	pg_assert_contains('pg_money_text(PG_PRODUCT_PRICE_MAX / 100', $source, 'the top in the base currency');
	pg_assert_contains("\$decimals_problem = pg_products_import_row_decimals_problem(\$row, \$decimal_positions);", $source, 'the decimal cells say why');
	pg_assert_contains("if (\$decimals_problem === 'too_large') {", $source, 'a weight or size above the top has its own list');
	pg_assert_contains('a weight, length, width or height in them is too large: {var:1}.', $source, 'and its own message');
	pg_assert_contains("if (pg_products_import_quantity_refusal(\$quantity_cell) === 'not_whole') {", $source, 'a stock cell says why');
	pg_assert_contains('their stock quantity is not a whole number of zero or more: {var:1}.', $source, 'a fraction or minus sign has its own message');
}

// A row naming a product in the Recycle Bin is not written, whether Update
// Existing Products is on or off, and is listed; an update finds its product
// by id, not by name.
function test_products_import_leaves_a_binned_product_alone()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/import_products.php');

	pg_assert_contains("\$bin_ready = pg_catalog_bin_ready();", $source, 'the bin column is read only where it exists');
	pg_assert_contains("(\$bin_ready ? ', recycled' : '')", $source, 'the product lookup reads it');

	$binned = strpos($source, '$rows_binned[] = ');
	$update = strpos($source, "if (\$update_existing_products == '1') {");
	pg_assert_true(($binned !== false) && ($update !== false) && ($binned < $update), 'checked before Update Existing Products');
	pg_assert_contains('because their product is in the Recycle Bin: {var:1}.', $source, 'the rows are named');

	pg_assert_contains("WHERE id = '\" . (int) \$product_id . \"'\";", $source, 'the update finds the product by id');
	pg_assert_same(0, substr_count($source, "WHERE name = '\" . escape(\$row[\$name_key]) . \"'"), 'not by name');
}
