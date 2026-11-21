<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for shelf addresses (includes/fn/product_bins.php): the address as
 * stored, the stock card's "Shelf" boxes, the product screen's post, the
 * import's shelf columns, Modify Selected's "Shelf address", and source pins
 * on the product screen, the dialog, the import and the inventory API.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_API_PANEL')) {
	define('PG_API_PANEL', true);
}

if (!defined('PG_INIT_LOADED')) {
	define('PG_INIT_LOADED', true);
}

require_once(PG_FUNCTIONS_DIR . '/view_folder_and_files_f.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/resources/inventory.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/schema.php');

function product_bins_test_locations()
{
	return array(
		array('id' => 7, 'name' => 'Warehouse', 'enabled' => 1, 'is_default' => 1),
		array('id' => 3, 'name' => 'Shop', 'enabled' => 1, 'is_default' => 0),
	);
}

function product_bins_test_source($path)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $path);
}

function product_bins_test_function_body($source, $name)
{
	$start = strpos($source, 'function ' . $name . '(');
	pg_assert_true($start !== false, $name . ' exists');
	$next = strpos($source, "\nfunction ", $start + 10);

	return substr($source, $start, ($next === false) ? null : ($next - $start));
}

function test_product_bins_clean()
{
	pg_assert_same('A-03-2', pg_bin_clean('  A-03-2 '), 'trimmed');
	pg_assert_same('A 03 2', pg_bin_clean("A \t03\n\n2"), 'runs of spaces and breaks are one space');
	pg_assert_same('', pg_bin_clean(''), 'empty clears');
	pg_assert_same('', pg_bin_clean(null), 'null clears');
	pg_assert_same('12', pg_bin_clean(12), 'a number is text');
	pg_assert_same(str_repeat('Ş', 40), pg_bin_clean(str_repeat('Ş', 40)), '40 characters fit, counted as characters');
	pg_assert_same(false, pg_bin_clean(str_repeat('a', 41)), '41 characters are too long');
	pg_assert_same(false, pg_bin_clean(array('A')), 'a list is no address');
	pg_assert_same(false, pg_bin_clean(true), 'a boolean is no address');
	pg_assert_same(false, pg_bin_clean("A\xC3"), 'text that is not UTF-8');
}

function test_product_bins_screen_values()
{
	$locations = product_bins_test_locations();

	pg_assert_same(array(7 => 'A-1', 3 => 'B-2'), pg_bins_screen_values($locations, array(7 => 'A-1', 3 => 'B-2')), 'each location shows its own');
	pg_assert_same(array(7 => 'OLD', 3 => ''), pg_bins_screen_values($locations, array(0 => 'OLD')), 'the default shows the address typed before locations');
	pg_assert_same(array(7 => 'A-1', 3 => ''), pg_bins_screen_values($locations, array(0 => 'OLD', 7 => 'A-1')), 'its own address wins');
}

function test_product_bins_posted()
{
	pg_assert_same(array('ok' => true, 'bins' => array(7 => 'A-1', 3 => '')),
		pg_bins_posted(array(7, 3), array(7 => ' A-1 ', 3 => '', 99 => 'X')), 'drawn locations read, an emptied box clears, others ignored');
	pg_assert_same(array('ok' => true, 'bins' => array(0 => 'A-1')),
		pg_bins_posted(array(0), array(0 => 'A-1')), 'without locations');
	pg_assert_same(array('ok' => true, 'bins' => array()),
		pg_bins_posted(array(0), 'A-1'), 'not a list');
	pg_assert_same(false, pg_bins_posted(array(7), array(7 => str_repeat('a', 41)))['ok'], 'too long refuses the save');
}

// Screen check: with locations one "Shelf" box in each location's row,
// beside its count; without, one box.
function test_product_bins_stock_card_rows()
{
	$locations = product_bins_test_locations();
	$html = pg_bin_location_rows_html($locations, array(7 => 5, 3 => 0), array(7 => 'A-1', 3 => ''));

	pg_assert_same(2, substr_count($html, 'name="product_bin['), 'one Shelf box a location');
	pg_assert_same(2, substr_count($html, 'name="stock_location['), 'one count a location');
	pg_assert_same(2, substr_count($html, '<div class="row g-5">'), 'one row a location');

	foreach (explode('<div class="col-12"><div class="row g-5">', $html) as $index => $row) {
		if ($index === 0) {
			continue;
		}

		pg_assert_same(1, substr_count($row, 'name="stock_location['), 'a row holds one count');
		pg_assert_same(1, substr_count($row, 'name="product_bin['), 'and its Shelf box beside it');
	}

	pg_assert_contains('name="stock_location[7]"', $html, 'the count keeps its name');
	pg_assert_contains('name="stock_location_was[7]" value="5"', $html, 'and its opened figure');
	pg_assert_contains('name="product_bin[7]" id="product_bin_7"', $html, 'the box is the location\'s');
	pg_assert_contains('value="A-1"', $html, 'the address shown');
	pg_assert_contains('maxlength="40"', $html, 'held to the column');

	$before = pg_bin_location_rows_html($locations, array(7 => 5), null);
	pg_assert_same(0, substr_count($before, 'product_bin'), 'no box before the upgrade');
	pg_assert_same(2, substr_count($before, 'name="stock_location['), 'the counts stay');

	$one = pg_bin_field_html(0, 'A"1', 'col-12 pg-field-span-3', '<div class="form-text">help</div>');
	pg_assert_same(1, substr_count($one, '<input '), 'one box without locations');
	pg_assert_contains('name="product_bin[0]"', $one, 'at location 0');
	pg_assert_contains('value="A&quot;1"', $one, 'escaped');
	pg_assert_contains('>' . lang('Shelf') . '</label>', $one, 'labelled Shelf');
}

function test_product_bins_stock_card_source()
{
	$body = product_bins_test_function_body(product_bins_test_source('product_builder.php'), 'pg_pb_render_stock_quantity');

	pg_assert_contains("(\$mode === 'edit') && !\$is_bundle && pg_bins_ready()", $body, 'edit screen of a product that is not a bundle, after the upgrade');
	pg_assert_contains("pg_bin_field_html(0,", $body, 'one box under the count without locations');
	pg_assert_contains('pg_bin_location_rows_html(', $body, 'a box beside each count with locations');
	pg_assert_same(1, substr_count($body, 'Shelf: where it sits in the store room'), 'one line of help');

	$save = product_bins_test_function_body(product_bins_test_source('product_builder.php'), 'pg_pb_update_product');
	pg_assert_contains('pg_bins_write($product_id, pg_bins_posted(pg_pb_bin_location_ids()', $save, 'the save writes the boxes drawn');
	pg_assert_contains("!pg_bundle_ids_among(array((int) \$product_id))", $save, 'never for a bundle');

	$check = product_bins_test_function_body(product_bins_test_source('product_builder.php'), 'pg_pb_validate_product_post');
	pg_assert_contains("pg_bins_posted(pg_pb_bin_location_ids(), \$_POST['product_bin'])['ok']", $check, 'too long refused before any write');
}

function test_product_bins_import_columns()
{
	$locations = product_bins_test_locations();

	pg_assert_same(array('positions' => array(1 => 0), 'unknown' => array('shelf:Shop')),
		pg_bin_import_columns(array('name', 'Shelf', 'shelf:Shop'), array(), 0), 'without locations: shelf, and shelf:<name> is unknown');
	pg_assert_same(array('positions' => array(1 => 7, 2 => 3), 'unknown' => array()),
		pg_bin_import_columns(array('name', 'shelf', ' SHELF: shop '), $locations, 7), 'with locations: shelf is the default, shelf:<name> without case');
	pg_assert_same(array('positions' => array(1 => 7), 'unknown' => array('shelf', 'shelf:Nowhere')),
		pg_bin_import_columns(array('name', 'shelf:Warehouse', 'shelf', 'shelf:Nowhere'), $locations, 7), 'a second column for a location and an unknown name');
	pg_assert_true(pg_bin_is_column_heading(' Shelf '), 'shelf');
	pg_assert_true(pg_bin_is_column_heading('shelf:Shop'), 'shelf:<name>');
	pg_assert_false(pg_bin_is_column_heading('shelves'), 'another heading');
}

function test_product_bins_import_row()
{
	pg_assert_same(array(7 => 'A-1', 3 => ''), pg_bin_import_row(array('p1', ' A-1', ''), array(1 => 7, 2 => 3)), 'an empty cell clears');
	pg_assert_same(array(0 => ''), pg_bin_import_row(array('p1'), array(3 => 0)), 'a short row reads empty');
	pg_assert_same(false, pg_bin_import_row(array('p1', str_repeat('a', 41)), array(1 => 0)), 'too long leaves the row out');
	pg_assert_same(array(), pg_bin_import_row(array('p1'), array()), 'no shelf column');

	$source = product_bins_test_source('import_products.php');
	pg_assert_same(2, substr_count($source, 'pg_bins_write($product_id, $row_bins);'), 'updated and added products both get their addresses');
	pg_assert_contains('$rows_shelf_too_long[] = $row_label;', $source, 'a too long cell is named');
	pg_assert_contains('pg_bin_is_column_heading($heading)', $source, 'shelf headings leave the unknown list');
}

// Screen check: Modify Selected has one entry "Shelf address".
function test_product_bins_mass_edit()
{
	pg_assert_same(null, pg_products_mass_shelf_value(array()), 'nothing asked');
	pg_assert_same(null, pg_products_mass_shelf_value(array('edit_shelf' => 'A-1')), 'a value without the choice');
	pg_assert_same('A-1', pg_products_mass_shelf_value(array('edit_shelf_method' => 'set', 'edit_shelf' => ' A-1 ')), 'set');
	pg_assert_same('', pg_products_mass_shelf_value(array('edit_shelf_method' => 'clear', 'edit_shelf' => 'A-1')), 'clear');
	pg_assert_same(false, pg_products_mass_shelf_value(array('edit_shelf_method' => 'set', 'edit_shelf' => ' ')), 'set with none');

	pg_assert_same('shelf_missing', pg_products_mass_edit_problem(array('edit_shelf_method' => 'set', 'edit_shelf' => '')), 'missing');
	pg_assert_same('shelf_too_long', pg_products_mass_edit_problem(array('edit_shelf_method' => 'set', 'edit_shelf' => str_repeat('a', 41))), 'too long');
	pg_assert_same('', pg_products_mass_edit_problem(array('edit_shelf_method' => 'clear')), 'clear');
	pg_assert_true(pg_products_mass_change_besides_price(array('edit_shelf_method' => 'clear'), null), 'a change besides the price');

	$template = product_bins_test_source('includes/templates/edit_products.php');
	pg_assert_same(1, substr_count($template, 'name="edit_shelf_method"'), 'one entry');
	pg_assert_same(1, substr_count($template, "lang('Shelf address')"), 'labelled Shelf address');
	pg_assert_same(1, substr_count($template, 'name="edit_shelf"'), 'one address box');
	pg_assert_same(1, substr_count($template, 'name="edit_shelf_location"'), 'one location choice');

	$handler = product_bins_test_source('edit_products.php');
	pg_assert_contains('pg_bins_write($product_id, array($shelf_location => $shelf_value));', $handler, 'written per product');
	pg_assert_contains("'shelf_missing' =>", $handler, 'refusal named');
}

function test_product_bins_inventory_api()
{
	$shelf = null;

	foreach (api_schema() as $route) {
		if ($route['id'] === 'inventory.write') {
			foreach ($route['params'] as $param) {
				if ($param['name'] === 'shelf') {
					$shelf = $param;
				}
			}
		}
	}

	pg_assert_true(is_array($shelf), 'inventory.write takes shelf');
	pg_assert_same(array('body', 'string', PG_BIN_MAX, true), array($shelf['in'], $shelf['type'], $shelf['max_length'], $shelf['nullable']), 'a nullable body string held to the column');
	pg_assert_same('string?', api_inventory_schema()['shelf'], 'the answer carries it');

	$body = product_bins_test_function_body(product_bins_test_source('includes/api/resources/inventory.php'), 'api_inventory_write');
	pg_assert_true(strpos($body, "api_fail(422, 'shelf'") < strpos($body, 'api_inventory_apply('), 'refused before the stock moves');
	pg_assert_true(strpos($body, 'api_dry_run_stop(') < strpos($body, 'pg_bins_write('), 'a dry run writes nothing');
}
