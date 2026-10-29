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
