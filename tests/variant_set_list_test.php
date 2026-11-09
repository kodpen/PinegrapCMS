<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The Variant Sets list (includes/fn/variant_set_list.php) on the list table
 * driver, printed whole: its columns, the cells of one set, the screen in
 * product_builder.php without DataTables markup.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/list_table.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/variant_set_list.php');

foreach (array('PATH' => '/', 'OUTPUT_PATH' => '/', 'OUTPUT_SOFTWARE_DIRECTORY' => 'pinegrap', 'DATE_FORMAT' => 'day_month', 'TIME_FORMAT' => 'twenty_four_hours') as $variant_set_test_name => $variant_set_test_value) {
	if (!defined($variant_set_test_name)) {
		define($variant_set_test_name, $variant_set_test_value);
	}
}

function pg_test_variant_set_row(array $over = array())
{
	return array_merge(array(
		'id' => '12', 'name' => 'Shirt <cotton>', 'enabled' => '1', 'short_description' => 'Soft & light',
		'image_name' => '', 'timestamp' => '1700000000', 'parent_name' => '', 'username' => 'admin',
		'variant_count' => '3', 'min_price' => '1250', 'max_price' => '1990',
	), $over);
}

function test_variant_set_list_columns()
{
	$columns = pg_variant_set_list_columns();

	pg_assert_same('name', key($columns), 'the name leads the row');
	pg_assert_same(array('name', 'short_description', 'variants', 'price', 'parent', 'enabled', 'last_modified'),
		array_keys(pg_list_table_printed_sort_columns($columns)), 'the image, attributes and buttons do not sort');
	pg_assert_true(in_array(PG_VARIANT_SET_LIST, pg_list_table_printed_lists(), true), 'the list keeps a heading sort');
}

function test_variant_set_list_cells()
{
	$cells = pg_variant_set_list_cells(pg_test_variant_set_row(), array('Size', 'Colour'));

	pg_assert_same(array_keys(pg_variant_set_list_columns()), array_keys($cells), 'one cell per column');
	pg_assert_contains('href="edit_product_group.php?id=12" data-pg-open>Shirt &lt;cotton&gt;</a>', $cells['name']['html'], 'the escaped name opens the set');
	pg_assert_contains('Soft &amp; light', $cells['short_description']['html'], 'the description is escaped');
	pg_assert_same('3', $cells['variants']['attributes']['data-pg-sort-value'], 'variants sort by their count');
	pg_assert_contains('>Colour</span>', $cells['attributes']['html'], 'each attribute is a badge');
	pg_assert_contains(' &ndash; ', $cells['price']['html'], 'differing prices show a span');
	pg_assert_same('1250', $cells['price']['attributes']['data-pg-sort-value'], 'price sorts by the lowest, in minor units');
	pg_assert_same('1', $cells['enabled']['attributes']['data-pg-sort-value'], 'enabled sorts first or last');
	pg_assert_same('1700000000', $cells['last_modified']['attributes']['data-pg-sort-value'], 'the date sorts by its time');
	pg_assert_contains('duplicate_product_group.php?id=12', $cells['actions']['html'], 'the row duplicates the set');
	pg_assert_contains('bi-image', $cells['image']['html'], 'no image: a placeholder, not a broken tile');

	$empty = pg_variant_set_list_cells(pg_test_variant_set_row(array('variant_count' => '0', 'enabled' => '0', 'image_name' => 'a b.jpg')), array());

	pg_assert_contains('theme-warning', $empty['variants']['html'], 'a set with no products is a warning');
	pg_assert_same('-1', $empty['price']['attributes']['data-pg-sort-value'], 'no price sorts below every price');
	pg_assert_contains('&mdash;', $empty['attributes']['html'], 'no attributes: a dash');
	pg_assert_same('0', $empty['enabled']['attributes']['data-pg-sort-value'], 'disabled sorts as 0');
	pg_assert_contains('src="/a b.jpg"', $empty['image']['html'], 'the image is shown');
}

function test_variant_set_list_screen()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/product_builder.php');
	$start = strpos($source, 'function pg_pb_variant_sets_screen()');
	$screen = substr($source, $start, strpos($source, "\nfunction ", $start + 10) - $start);

	pg_assert_false(strpos($screen, 'class="chart') !== false, 'no DataTables table left');
	pg_assert_false(strpos($screen, 'select_all') !== false, 'the driver selects the rows');
	pg_assert_contains("'ids_field' => 'group_ids[]'", $screen, 'the selected ids post as variant_set_action.php reads them');
	pg_assert_contains('pg_list_table_search_box(PG_VARIANT_SET_LIST)', $screen, 'search box');
	pg_assert_contains("pg_js_bundle_tags('assets/js/pg_list_table.min.js')", $screen, 'driver loaded');
}
