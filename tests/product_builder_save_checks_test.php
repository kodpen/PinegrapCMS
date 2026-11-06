<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Checks the product screen makes before a save: count boxes read
 * strictly, the quantity range (pg_pb_whole_number_from_input(),
 * pg_pb_number_fields_problem()) and a variant set member's combination
 * (pg_pb_variant_set_clash()), and when an edit post unticked every option
 * (pg_pb_attributes_cleared()).
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


function test_product_builder_whole_number_reads_digits_and_groups()
{
	pg_assert_same('', pg_pb_whole_number_from_input('', '.'), 'empty stays empty');
	pg_assert_same('', pg_pb_whole_number_from_input('  ', '.'), 'blank stays empty');
	pg_assert_same(0, pg_pb_whole_number_from_input('0', '.'), 'zero');
	pg_assert_same(12, pg_pb_whole_number_from_input(' 12 ', '.'), 'digits');
	pg_assert_same(7, pg_pb_whole_number_from_input('007', '.'), 'leading zeros');
	pg_assert_same(1200, pg_pb_whole_number_from_input('1.200', '.'), 'Turkish grouping');
	pg_assert_same(1200000, pg_pb_whole_number_from_input('1.200.000', '.'), 'two groups');
	pg_assert_same(1200, pg_pb_whole_number_from_input('1 200', '.'), 'space grouping');
	pg_assert_same(1200, pg_pb_whole_number_from_input('1,200', ','), 'English grouping');
	pg_assert_same(5, pg_pb_whole_number_from_input(5, ','), 'an int');
}

function test_product_builder_whole_number_refuses_signs_fractions_letters()
{
	foreach (array('-2', '-0', '+3', '12,5', '3,5', '12.5', 'abc', '1e3', '1.20', '1,200', '12a') as $value) {
		pg_assert_same(false, pg_pb_whole_number_from_input($value, '.'), 'refused with a dot separator: ' . $value);
	}

	pg_assert_same(false, pg_pb_whole_number_from_input('1.200', ','), 'a dot is no group with a comma separator');
	pg_assert_same(false, pg_pb_whole_number_from_input(array('1'), '.'), 'an array');
}

function test_product_builder_number_fields_name_the_bad_field()
{
	pg_assert_same('', pg_pb_number_fields_problem(array(), '.'), 'nothing posted');
	pg_assert_same('', pg_pb_number_fields_problem(array('number_of_payments' => '', 'reward_points' => '1.200', 'form_label_column_width' => '', 'price' => '12,50', 'weight' => '0'), '.'), 'valid values');

	pg_assert_contains(lang('Number of Payments'), pg_pb_number_fields_problem(array('number_of_payments' => '-2'), '.'), 'negative payments');
	pg_assert_contains(lang('Reward Points'), pg_pb_number_fields_problem(array('reward_points' => '12,5'), '.'), 'fractional points');
	pg_assert_contains(lang('Start (days)'), pg_pb_number_fields_problem(array('start' => 'abc'), '.'), 'letters in start');
	pg_assert_contains(lang('Access duration'), pg_pb_number_fields_problem(array('private_days' => '3,5'), '.'), 'fractional days');
}

function test_product_builder_label_width_and_signs()
{
	pg_assert_same('', pg_pb_number_fields_problem(array('form_label_column_width' => '35'), '.'), 'a width');
	pg_assert_same('', pg_pb_number_fields_problem(array('form_label_column_width' => '100'), '.'), 'the top width');
	pg_assert_same('', pg_pb_number_fields_problem(array('form_label_column_width' => '0'), '.'), 'a stored 0 reads as none');

	foreach (array('abc', '101', '-5', '35%') as $value) {
		pg_assert_contains(lang('Label Column Width'), pg_pb_number_fields_problem(array('form_label_column_width' => $value), '.'), 'width refused: ' . $value);
	}

	pg_assert_contains(lang('Unit Price'), pg_pb_number_fields_problem(array('price' => '-5'), '.'), 'negative price');
	pg_assert_contains(lang('Weight'), pg_pb_number_fields_problem(array('weight' => ' -3'), '.'), 'negative weight');
	pg_assert_same('', pg_pb_number_fields_problem(array('price' => '-0,00'), '.'), 'minus zero is zero');
}

function test_product_builder_quantity_range()
{
	pg_assert_same('', pg_pb_number_fields_problem(array('default_quantity' => '1', 'minimum_quantity' => '', 'maximum_quantity' => ''), '.'), 'no limits');
	pg_assert_same('', pg_pb_number_fields_problem(array('default_quantity' => '2', 'minimum_quantity' => '2', 'maximum_quantity' => '5'), '.'), 'inside the range');
	pg_assert_same('', pg_pb_number_fields_problem(array('default_quantity' => '10', 'minimum_quantity' => '10', 'maximum_quantity' => '0'), '.'), 'a zero maximum is no limit');

	$problem = pg_pb_number_fields_problem(array('default_quantity' => '1', 'minimum_quantity' => '10', 'maximum_quantity' => '2'), '.');
	pg_assert_contains(lang('Min. Quantity'), $problem, 'minimum above maximum');
	pg_assert_contains(lang('Max. Quantity'), $problem, 'names the maximum');

	pg_assert_contains(lang('Default Quantity'), pg_pb_number_fields_problem(array('default_quantity' => '1', 'minimum_quantity' => '3'), '.'), 'default below minimum');
	pg_assert_contains(lang('Default Quantity'), pg_pb_number_fields_problem(array('default_quantity' => '9', 'maximum_quantity' => '5'), '.'), 'default above maximum');
}

function test_product_builder_variant_set_clash()
{
	$set = array(1, 2);
	$siblings = array(202 => array(1 => 38, 2 => 10), 204 => array(1 => 39, 2 => 10), 205 => array(2 => 11, 1 => 39));
	$pair = function ($size, $colour) {
		return array(array('attribute_id' => 1, 'option_id' => $size), array('attribute_id' => 2, 'option_id' => $colour));
	};

	pg_assert_same(array(), pg_pb_variant_set_clash($pair(38, 11), $set, $siblings), 'a combination of its own');
	pg_assert_same(array('clash' => 202), pg_pb_variant_set_clash($pair(38, 10), $set, $siblings), 'another member holds it');
	pg_assert_same(array('clash' => 205), pg_pb_variant_set_clash($pair(39, 11), $set, $siblings), 'order of the pairs does not matter');
	pg_assert_same(array('outside' => 3), pg_pb_variant_set_clash(array(array('attribute_id' => 3, 'option_id' => 5)), $set, $siblings), 'an attribute outside the set');
	pg_assert_same(array(), pg_pb_variant_set_clash(array(array('attribute_id' => 3, 'option_id' => 5)), array(), array()), 'a set naming no attributes');
	pg_assert_same(array(), pg_pb_variant_set_clash(array(array('attribute_id' => 1, 'option_id' => 38)), $set, $siblings), 'a part of a combination is not a clash');
	pg_assert_same(array(), pg_pb_variant_set_clash(array(), $set, $siblings), 'nothing chosen');
}

function test_product_builder_weight_and_dimensions_read_as_numbers()
{
	pg_assert_same('', pg_pb_number_fields_problem(array('weight' => '0,125', 'length' => '12.5', 'width' => ' 1.234,5 ', 'height' => ''), '.'), 'decimals, grouping and an empty box');
	pg_assert_same('', pg_pb_number_fields_problem(array('weight' => '99999999.9999'), '.'), 'the column top');
	pg_assert_same('', pg_pb_number_fields_problem(array('weight' => '-0'), '.'), 'minus zero');

	foreach (array('abc', '1e5', '12 kg', '5x', '.', ',') as $value) {
		pg_assert_contains(lang('Length'), pg_pb_number_fields_problem(array('length' => $value), '.'), 'letters refused: ' . $value);
	}

	pg_assert_contains(lang('Weight'), pg_pb_number_fields_problem(array('weight' => '123456789012'), '.'), 'above the column');
	pg_assert_contains(lang('Weight'), pg_pb_number_fields_problem(array('weight' => array('1')), '.'), 'an array');
	// Kilograms are stored as pounds: 50,000,000 kg is above the column once converted.
	pg_assert_same('', pg_pb_number_fields_problem(array('weight' => '50000000'), '.'), 'fits in pounds');
	pg_assert_contains(lang('Weight'), pg_pb_number_fields_problem(array('weight' => '50000000', 'convert_to_metric_system' => '1'), '.'), 'too large once converted');
	pg_assert_same('', pg_pb_number_fields_problem(array('height' => '99999999', 'convert_to_metric_system' => '1'), '.'), 'centimetres shrink to inches');
}

function test_product_builder_attributes_cleared_needs_the_mark()
{
	pg_assert_same(true, pg_pb_attributes_cleared(array('pg_pb_attributes_posted' => '1', 'variants_json' => '[]')), 'every option unticked');
	pg_assert_same(true, pg_pb_attributes_cleared(array('pg_pb_attributes_posted' => '1', 'variants_json' => ' [] ')), 'spaces around the list');
	pg_assert_same(false, pg_pb_attributes_cleared(array('variants_json' => '[]')), 'no mark: no script read the ticks');
	pg_assert_same(false, pg_pb_attributes_cleared(array('pg_pb_attributes_posted' => '1', 'variants_json' => '')), 'an empty field is not an empty list');
	pg_assert_same(false, pg_pb_attributes_cleared(array('pg_pb_attributes_posted' => '1', 'variants_json' => '[{"name":"A"}]')), 'an option ticked');
	pg_assert_same(false, pg_pb_attributes_cleared(array('pg_pb_attributes_posted' => '1', 'variants_json' => array('[]'))), 'an array');
	pg_assert_same(false, pg_pb_attributes_cleared(array('pg_pb_attributes_posted' => '1')), 'nothing posted');
}

function test_product_builder_shipping_counts_and_stock_read_as_whole_numbers()
{
	pg_assert_same('', pg_pb_number_fields_problem(array('primary_weight_points' => '3', 'secondary_weight_points' => '', 'preparation_time' => '1.200', 'inventory_quantity' => '0'), '.'), 'whole counts and an empty box');

	pg_assert_contains(lang('Primary Weight Points'), pg_pb_number_fields_problem(array('primary_weight_points' => '-4'), '.'), 'negative points');
	pg_assert_contains(lang('Secondary Weight Points'), pg_pb_number_fields_problem(array('secondary_weight_points' => 'abc'), '.'), 'letters in points');
	pg_assert_contains(lang('Preparation Time'), pg_pb_number_fields_problem(array('preparation_time' => '2,5'), '.'), 'fractional days');
	pg_assert_contains(lang('Inventory Quantity'), pg_pb_number_fields_problem(array('inventory_quantity' => '-5'), '.'), 'negative stock');
	pg_assert_contains(lang('Inventory Quantity'), pg_pb_number_fields_problem(array('inventory_quantity' => array('1')), '.'), 'an array');
}

function test_product_builder_counts_fit_their_columns()
{
	pg_assert_same('', pg_pb_number_fields_problem(array('maximum_quantity' => '4294967295'), '.'), 'the column top');
	pg_assert_contains(lang('Max. Quantity'), pg_pb_number_fields_problem(array('maximum_quantity' => '4294967296'), '.'), 'above the column');
	pg_assert_contains(lang('Reward Points'), pg_pb_number_fields_problem(array('reward_points' => '99999999999999999999'), '.'), 'far above the column');
}

function test_product_builder_posted_stock_quantity_reads_groups()
{
	$saved = $_POST;

	$_POST = array();
	pg_assert_same('', pg_pb_posted_stock_quantity(), 'absent');

	$_POST = array('inventory_quantity' => ' 12 ');
	pg_assert_same('12', pg_pb_posted_stock_quantity(), 'spaces trimmed');

	$_POST = array('inventory_quantity' => '1 200');
	pg_assert_same('1200', pg_pb_posted_stock_quantity(), 'grouped by a space');

	$_POST = $saved;
}
