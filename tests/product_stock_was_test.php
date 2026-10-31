<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Edit Product stock count guard (product_builder.php): the
 * screen posts inventory_quantity_was, the count it opened with, and a save
 * writes the count only when it was changed there, and then only over the
 * count the screen opened with. A sale made while the screen was open is
 * no longer undone by the save.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/product_builder.php');

function pg_stock_was_function_body($name)
{
	$function = new ReflectionFunction($name);

	return implode('', array_slice(
		file($function->getFileName()),
		$function->getStartLine() - 1,
		$function->getEndLine() - $function->getStartLine() + 1));
}

function test_stock_was_untouched_count_is_kept()
{
	// Opened at 10, two orders took 3, the operator fixed the description.
	pg_assert_same('keep', pg_pb_stock_save_action('10', '10', 7), 'an unchanged count keeps the stored 7');
	pg_assert_same('keep', pg_pb_stock_save_action(' 10 ', '10', 10), 'spaces do not count as a change');
	pg_assert_same('keep', pg_pb_stock_save_action('', '0', 0), 'an empty box over 0 is no change');
}

function test_stock_was_changed_count_is_written_when_nothing_moved()
{
	pg_assert_same('write', pg_pb_stock_save_action('25', '10', 10), 'a typed count over an unmoved stock is written');
	pg_assert_same('write', pg_pb_stock_save_action('0', '4', '4'), 'a typed 0 is written');
}

function test_stock_was_changed_count_over_a_moved_stock_is_a_conflict()
{
	pg_assert_same('conflict', pg_pb_stock_save_action('25', '10', 7), 'a sale meanwhile keeps the stored 7');
	pg_assert_same('conflict', pg_pb_stock_save_action('7', '10', 8), 'a different typed count is not written over 8');
}

function test_stock_was_typed_count_equal_to_the_stored_one_is_no_conflict()
{
	pg_assert_same('keep', pg_pb_stock_save_action('7', '10', 7), 'the typed count already stored needs no write');
}

function test_stock_was_form_without_the_field_writes_as_before()
{
	pg_assert_same('write', pg_pb_stock_save_action('25', NULL, 7), 'no was value: written as before');
	pg_assert_same('write', pg_pb_stock_save_action('25', '', 7), 'an empty was value: written as before');
	pg_assert_same('write', pg_pb_stock_save_action('25', 'x', 7), 'a non-numeric was value: written as before');
}

function test_stock_was_hidden_field_is_drawn_in_edit_mode()
{
	$body = pg_stock_was_function_body('pg_pb_render_product_screen');

	pg_assert_true(strpos($body, 'name="inventory_quantity_was"') !== FALSE, 'the screen draws inventory_quantity_was');
}

function test_stock_was_update_reads_the_was_value_and_guards_the_write()
{
	$body = pg_stock_was_function_body('pg_pb_update_product');

	pg_assert_true(strpos($body, "\$_POST['inventory_quantity_was']") !== FALSE, 'the save reads inventory_quantity_was');
	pg_assert_true(strpos($body, 'pg_pb_stock_save_action(') !== FALSE, 'the save decides through pg_pb_stock_save_action()');
	pg_assert_true(strpos($body, 'AND inventory_quantity = ') !== FALSE, 'a changed count is written only over the opened count');
	pg_assert_true(strpos($body, "'stock_kept'") !== FALSE, 'a kept count is reported back to the screen');
}

function test_stock_was_edit_screen_says_the_count_was_kept()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/edit_product.php');

	pg_assert_true(strpos($source, "\$result['stock_kept']") !== FALSE, 'edit_product.php reads stock_kept');
	pg_assert_true(strpos($source, 'The stock of this product changed while the screen was open') !== FALSE, 'edit_product.php says so');
}
