<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the form_data row of a product form answer the designer cart
 * widget saves (pg_product_form_answer_row(), cart_action.php) and for the
 * name a reader shows for an answer saved with an empty name
 * (pg_form_data_name_sql()). The row carries the field's name, as the
 * classic cart and the express order write it: View Order, Print order, the
 * orders CSV export and order completion's ^^variables^^ read the label
 * from form_data.name.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The answer row carries the field's name and the ids as integers.
function test_product_form_answer_row_keeps_field_name()
{
	$row = pg_product_form_answer_row(array('id' => '12', 'name' => 'name_on_the_mug'), '7', '33', '2', 'Ayşe', 'standard');

	pg_assert_same(array(
		'form_id' => 0,
		'form_field_id' => 12,
		'data' => 'Ayşe',
		'file_id' => 0,
		'order_id' => 7,
		'order_item_id' => 33,
		'quantity_number' => 2,
		'name' => 'name_on_the_mug',
		'type' => 'standard',
		'ship_to_id' => 0,
	), $row, 'answer row');
}

// A field row without a name gives an empty name, never a notice.
function test_product_form_answer_row_without_name()
{
	$row = pg_product_form_answer_row(array('id' => 5), 1, 2, 1, '1764633600', 'date');
	pg_assert_same('', $row['name'], 'no name');
	pg_assert_same('date', $row['type'], 'type kept');
}

// A reader takes the field's name where the stored name is empty.
function test_form_data_name_sql_falls_back_to_field_name()
{
	$sql = pg_form_data_name_sql();
	pg_assert_contains("form_data.name = ''", $sql, 'empty name is detected');
	pg_assert_contains('form_fields.id = form_data.form_field_id', $sql, 'field row by id');
	pg_assert_contains("COALESCE(", $sql, 'a deleted field gives an empty name');
	pg_assert_same(false, strpos($sql, ' AS '), 'the caller aliases it');
}
