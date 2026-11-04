<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source pins for Edit Featured & New Items: the rows that hold the form
 * fields are a plain table (never a paged, searched DataTable, which would
 * drop the rows it does not show from the post), each Featured Order box is
 * named after its item and a disabled item says so in words.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function featured_new_items_source()
{
	return (string) file_get_contents(dirname(__DIR__) . '/pinegrap/edit_featured_and_new_items.php');
}

function test_featured_new_items_table_is_not_a_datatable()
{
	$source = featured_new_items_source();

	pg_assert_false(strpos($source, 'class="chart') !== false, 'no table.chart: DataTables would page, search and hide the form fields');
	pg_assert_false(strpos($source, 'chart-buttons') !== false, 'no DataTables button bar');
	pg_assert_contains('<table class="table table-hover">', $source);

	// The truncation guard must stay the form's last field.
	pg_assert_contains('<input type="hidden" name="max_input_vars_test" value="true">' . "\n" . '                </form>', $source);
}

function test_featured_new_items_order_box_named()
{
	$source = featured_new_items_source();

	pg_assert_contains("'aria-label' => lang(array('string' => 'Featured Order of {var:1}', 'vars' => \$item_label))", $source);
}

function test_featured_new_items_disabled_in_words()
{
	$source = featured_new_items_source();

	pg_assert_contains('<span class="visually-hidden">\' . h(lang(\'Disabled\')) . \'</span>', $source);
}

function test_featured_new_items_sort_order_value()
{
	require_once(PG_FUNCTIONS_DIR . '/includes/fn/ecommerce.php');

	pg_assert_same('', pg_featured_sort_order_value(''), 'an empty box means not featured');
	pg_assert_same('', pg_featured_sort_order_value('  '), 'spaces mean not featured');
	pg_assert_same(0, pg_featured_sort_order_value('0'), 'zero is an order');
	pg_assert_same(7, pg_featured_sort_order_value(' 7 '), 'spaces around a number are dropped');
	pg_assert_same(65535, pg_featured_sort_order_value('65535'), 'the column maximum is accepted');

	foreach (array('65536', 'abc', '-5', '12,50', '12.5', '1e3', '0x1') as $invalid) {
		pg_assert_same(false, pg_featured_sort_order_value($invalid), $invalid . ' is refused');
	}
}

function test_featured_new_items_form_checks_and_layout()
{
	$source = featured_new_items_source();

	pg_assert_contains('pg_featured_sort_order_value(', $source, 'the save checks the order');
	pg_assert_contains("'data-inputmask-alias'=>'integer'", $source, 'the order box takes whole numbers');
	pg_assert_contains("'New on Date of {var:1}'", $source, 'the date box is named');
	pg_assert_contains('<div class="table-responsive">', $source, 'the table scrolls in its own box');
	pg_assert_contains('text-break', $source, 'long names break');
	pg_assert_contains('$prefill = !$liveform->check_form_errors()', $source, 'after a save the stored values are shown');
}

function test_featured_new_items_summary_counts()
{
	require_once(PG_FUNCTIONS_DIR . '/includes/fn/ecommerce.php');

	$items = array(
		array('type' => 'product group', 'id' => 1, 'parent_product_group_id' => 0, 'enabled' => 1, 'featured' => 1, 'new_date' => '2026-01-01'),
		array('type' => 'product group', 'id' => 2, 'parent_product_group_id' => 1, 'enabled' => 1, 'featured' => 1, 'new_date' => '0000-00-00'),
		array('type' => 'product group', 'id' => 3, 'parent_product_group_id' => 1, 'enabled' => 0, 'featured' => 0, 'new_date' => '2026-11-01'),
		array('type' => 'product', 'id' => 10, 'parent_product_group_id' => 2, 'enabled' => 1, 'featured' => 1, 'new_date' => '2026-10-01'),
		array('type' => 'product', 'id' => 10, 'parent_product_group_id' => 3, 'enabled' => 1, 'featured' => 0, 'new_date' => '0000-00-00'),
		array('type' => 'product', 'id' => 11, 'parent_product_group_id' => 2, 'enabled' => 0, 'featured' => 0, 'new_date' => ''),
	);

	pg_assert_same(
		array('product_groups' => 2, 'products' => 2, 'featured' => 2, 'new' => 2, 'disabled' => 2),
		pg_featured_new_summary_counts($items),
		'the root group is skipped, a product in two groups is one product, featured and new count rows'
	);
	pg_assert_same(
		array('product_groups' => 0, 'products' => 0, 'featured' => 0, 'new' => 0, 'disabled' => 0),
		pg_featured_new_summary_counts(array()),
		'no items, all zero'
	);
}

function test_featured_new_items_wide_layout()
{
	$source = featured_new_items_source();

	pg_assert_contains('<div class="col-12 lg:col-8 2xl:col-9 order-1">', $source, 'the table sits in the form column');
	pg_assert_contains('<div class="col-12 lg:col-4 2xl:col-3 order-2">', $source, 'the status column follows it');
	pg_assert_contains('pg_featured_new_summary_counts($items)', $source, 'the Summary reads the listed rows');
	pg_assert_contains('<div class="col-12 order-3 position-sticky" style="bottom:0; z-index:3;">', $source, 'the save bar sticks');
}
