<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * An order line's product form answers on the external API's Order
 * (includes/api/resources/orders.php): one per unit and field, a check box
 * group's values joined, a file as id, name, size, type and its address, and
 * the schema that declares them.
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

require_once(PG_FUNCTIONS_DIR . '/includes/api/resources/orders.php');

function test_api_order_item_answers_group_by_line_unit_and_field()
{
	$rows = array(
		array('order_item_id' => '7', 'quantity_number' => '1', 'form_field_id' => '3', 'name' => 'photo', 'label' => 'Your photo', 'data' => 'mug.jpg', 'file_id' => '42', 'file_name' => 'mug1.jpg', 'file_size' => '2048', 'file_type' => 'jpg'),
		array('order_item_id' => '7', 'quantity_number' => '1', 'form_field_id' => '4', 'name' => 'extras', 'label' => 'Extras', 'data' => 'Gift box', 'file_id' => '0', 'file_name' => null, 'file_size' => null, 'file_type' => null),
		array('order_item_id' => '7', 'quantity_number' => '1', 'form_field_id' => '4', 'name' => 'extras', 'label' => 'Extras', 'data' => 'Card', 'file_id' => '0', 'file_name' => null, 'file_size' => null, 'file_type' => null),
		array('order_item_id' => '7', 'quantity_number' => '2', 'form_field_id' => '3', 'name' => 'photo', 'label' => null, 'data' => 'gone.jpg', 'file_id' => '50', 'file_name' => null, 'file_size' => null, 'file_type' => null),
		array('order_item_id' => '8', 'quantity_number' => '1', 'form_field_id' => '9', 'name' => 'text', 'label' => 'Text', 'data' => 'Happy birthday', 'file_id' => '0', 'file_name' => null, 'file_size' => null, 'file_type' => null),
	);

	$answers = api_order_item_answers_present($rows, 'https://shop.example/');

	pg_assert_same(array(7, 8), array_keys($answers), 'the answers were not grouped by line');
	pg_assert_same(3, count($answers[7]), 'a check box group was not one answer');

	pg_assert_same(array(
		'unit'     => 1,
		'field_id' => 3,
		'name'     => 'photo',
		'label'    => 'Your photo',
		'value'    => 'mug1.jpg',
		'file'     => array(
			'id'   => 42,
			'name' => 'mug1.jpg',
			'size' => 2048,
			'type' => 'jpg',
			'url'  => 'https://shop.example/mug1.jpg'
		)
	), $answers[7][0], 'a file answer was not presented');

	pg_assert_same('Gift box, Card', $answers[7][1]['value'], 'a check box group\'s values were not joined');
	pg_assert_same(null, $answers[7][1]['file'], 'an answer without a file carried one');

	// The file was deleted from Files since: the answer keeps its text, no file.
	pg_assert_same(2, $answers[7][2]['unit'], 'the second unit lost its number');
	pg_assert_same('gone.jpg', $answers[7][2]['value'], 'a deleted file emptied the answer');
	pg_assert_same(null, $answers[7][2]['file'], 'a deleted file was still presented');
	pg_assert_same(null, $answers[7][2]['label'], 'a deleted field\'s label was not null');

	pg_assert_same(array(), api_order_item_answers_present(array(), 'https://shop.example/'), 'no answers gave something');
}

function test_api_order_item_answers_file_name_is_url_encoded()
{
	$rows = array(
		array('order_item_id' => '1', 'quantity_number' => '1', 'form_field_id' => '2', 'name' => 'f', 'label' => 'F', 'data' => 'a b.pdf', 'file_id' => '5', 'file_name' => 'a b.pdf', 'file_size' => '10', 'file_type' => 'pdf'),
	);

	$answers = api_order_item_answers_present($rows, 'https://shop.example/');

	pg_assert_same('https://shop.example/a%20b.pdf', $answers[1][0]['file']['url'], 'a space in the name was not encoded');
}

function test_api_order_item_answers_are_declared()
{
	$schema = api_order_schema();

	pg_assert_true(isset($schema['items'][0]['answers']), 'items carry no answers in the schema');
	pg_assert_same('OrderItemFile?', $schema['items'][0]['answers'][0]['file'], 'the answer\'s file is not a nullable object');
	pg_assert_same(array('id', 'name', 'size', 'type', 'url'), array_keys(api_order_item_file_schema()), 'the file object is not id, name, size, type and url');
}
