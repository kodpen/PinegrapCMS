<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for every wrong field named at once (includes/api/router.php):
 * api_validate_collect() gathers each refusal of the router's own checks as a
 * row of error.errors, the first row stays error.field and error.message, at
 * most api_field_errors_max() rows; a JSON list as body is told apart.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_API_ENTRY')) {
	define('PG_API_ENTRY', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/api/router.php');

// A write route with a few typed body fields.
function api_field_errors_test_route()
{
	return array(
		'id'     => 'things.create',
		'method' => 'POST',
		'path'   => '/things',
		'params' => array(
			array('name' => 'name', 'in' => 'body', 'type' => 'string', 'required' => true, 'max_length' => 5),
			array('name' => 'quantity', 'in' => 'body', 'type' => 'int', 'min' => 0),
			array('name' => 'price', 'in' => 'body', 'type' => 'money'),
			array('name' => 'state', 'in' => 'body', 'type' => 'enum', 'values' => array('on', 'off')),
			array('name' => 'tags', 'in' => 'body', 'type' => 'list', 'of' => 'string'),
		),
	);
}

// The fields of each row, in order.
function api_field_errors_test_pairs($errors)
{
	$pairs = array();

	foreach ($errors as $row) {
		$pairs[] = $row['field'] . ':' . $row['code'];
	}

	return $pairs;
}

// Every wrong field is named, in declaration order, unknown keys last.
function test_api_field_errors_names_every_wrong_field()
{
	$input = array('quantity' => '-2', 'price' => '19.99', 'state' => 'maybe', 'tags' => 'x', 'stock' => 4, 'colour' => 'red');

	$checked = api_validate_collect(api_field_errors_test_route(), $input, array_keys($input));

	pg_assert_same(array(
		'name:required',
		'quantity:below_min',
		'price:not_minor_units',
		'state:not_allowed',
		'tags:not_list',
		'stock:unknown_field',
		'colour:unknown_field',
	), api_field_errors_test_pairs($checked['errors']), 'rows');

	foreach ($checked['errors'] as $row) {
		pg_assert_same(array('field', 'code', 'message'), array_keys($row), 'row shape');
		pg_assert_true($row['message'] !== '', 'a sentence for ' . $row['field']);
	}
}

// The first row is the refusal a single-error answer gave before.
function test_api_field_errors_first_row_is_the_old_refusal()
{
	$checked = api_validate_collect(api_field_errors_test_route(), array('name' => 'toolong'), array('name'));

	pg_assert_same(array('name:too_long'), api_field_errors_test_pairs($checked['errors']), 'one row');
	pg_assert_same('name is longer than the 5 characters accepted.', $checked['errors'][0]['message'], 'same sentence');
}

// A right call has no rows and its typed values.
function test_api_field_errors_none_for_a_right_call()
{
	$input = array('name' => ' Pen ', 'quantity' => '3', 'price' => 1999, 'state' => 'on', 'tags' => array('a'));

	$checked = api_validate_collect(api_field_errors_test_route(), $input, array_keys($input));

	pg_assert_same(array(), $checked['errors'], 'no rows');
	pg_assert_same(array('name' => 'Pen', 'quantity' => 3, 'price' => 1999, 'state' => 'on', 'tags' => array('a')), $checked['values'], 'values');
}

// A wrong value leaves the right ones typed.
function test_api_field_errors_keeps_the_right_values()
{
	$input = array('name' => 'Pen', 'quantity' => 'many');

	$checked = api_validate_collect(api_field_errors_test_route(), $input, array_keys($input));

	pg_assert_same(array('quantity:not_integer'), api_field_errors_test_pairs($checked['errors']), 'one row');
	pg_assert_same('Pen', $checked['values']['name'], 'name kept');
}

// At most twenty rows travel.
function test_api_field_errors_cap()
{
	pg_assert_same(20, api_field_errors_max(), 'twenty');

	$input = array('name' => 'Pen');

	for ($i = 0; $i < 30; $i++) {
		$input['extra_' . $i] = 1;
	}

	$checked = api_validate_collect(api_field_errors_test_route(), $input, array_keys($input));

	pg_assert_same(30, count($checked['errors']), 'all collected');
	pg_assert_same(20, count(array_slice($checked['errors'], 0, api_field_errors_max())), 'twenty sent');
}

// A JSON list with items is not an object; [] stays an empty body.
function test_api_field_errors_json_list_body()
{
	pg_assert_true(api_json_body_is_list('[{"name":"Pen"}]', json_decode('[{"name":"Pen"}]', true)), 'list of objects');
	pg_assert_true(api_json_body_is_list(" \n[1,2]", array(1, 2)), 'list after spaces');
	pg_assert_false(api_json_body_is_list('[]', array()), 'empty list');
	pg_assert_false(api_json_body_is_list('{"0":"a"}', array(0 => 'a')), 'object with a numeric key');
	pg_assert_false(api_json_body_is_list('{"name":"Pen"}', array('name' => 'Pen')), 'object');
}
