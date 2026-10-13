<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the external API's product attributes in
 * includes/api/resources/product_attributes.php: the options a request sends,
 * what a change to an attribute's options comes to, the option object and
 * the route table.
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

require_once(PG_FUNCTIONS_DIR . '/includes/api/resources/product_attributes.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/schema.php');

function test_api_product_attributes_options_are_checked_and_tidied()
{
	$read = api_product_attribute_options_from(array(
		array('label' => ' Small '),
		array('label' => 'Medium', 'no_value' => false),
		array('label' => 'No thanks', 'no_value' => 'true'),
	), false);

	pg_assert_same(null, $read['problem']);
	pg_assert_same(array(
		array('id' => 0, 'label' => 'Small'),
		array('id' => 0, 'label' => 'Medium', 'no_value' => false),
		array('id' => 0, 'label' => 'No thanks', 'no_value' => true),
	), $read['options']);
}

function test_api_product_attributes_options_refuse_what_cannot_be_an_option()
{
	$problem = function ($raw, $with_ids) {
		$read = api_product_attribute_options_from($raw, $with_ids);
		return ($read['problem'] === null) ? null : $read['problem']['kind'];
	};

	pg_assert_same('shape', $problem(array('Small'), false), 'a bare string');
	pg_assert_same('shape', $problem(array(array('id' => 3, 'label' => 'Small')), false), 'an id when creating');
	pg_assert_same('shape', $problem(array(array('label' => 'Small', 'price' => 5)), true), 'an unknown key');
	pg_assert_same('shape', $problem(array(array('id' => '2.5', 'label' => 'Small')), true), 'an id that is not a whole number');
	pg_assert_same('label', $problem(array(array('label' => '  ')), false), 'no label');
	pg_assert_same('long', $problem(array(array('label' => str_repeat('a', 256))), false), 'too long');
	pg_assert_same('twice', $problem(array(array('label' => 'Red'), array('label' => 'red')), false), 'the same label in another case');
	pg_assert_same(null, $problem(array(array('id' => 4, 'label' => 'Red'), array('id' => null, 'label' => 'Blue')), true), 'an id, and none');

	$read = api_product_attribute_options_from(array(array('label' => 'Red'), array('label' => 'RED')), false);

	pg_assert_same(1, $read['problem']['index'], 'the second one is the repeat');
	pg_assert_same('RED', $read['problem']['label']);
}

// Size: 11 S, 12 M, 13 L, where M is the "no thanks" choice.
function api_product_attributes_test_current()
{
	return array(
		array('id' => '11', 'label' => 'S', 'no_value' => '0'),
		array('id' => '12', 'label' => 'M', 'no_value' => '1'),
		array('id' => '13', 'label' => 'L', 'no_value' => '0'),
	);
}

function test_api_product_attributes_a_new_option_is_added_and_the_others_stay()
{
	$plan = api_product_attribute_option_plan(api_product_attributes_test_current(), array(array('id' => 0, 'label' => 'XL')), array());

	pg_assert_same(null, $plan['problem']);
	pg_assert_same(array(array('label' => 'XL', 'no_value' => false)), $plan['insert']);
	pg_assert_same(array(), $plan['update'], 'nothing else is rewritten');
	pg_assert_same(array(), $plan['remove'], 'nothing is removed by being left out');
}

function test_api_product_attributes_a_renamed_option_keeps_its_no_value_unless_sent()
{
	$plan = api_product_attribute_option_plan(api_product_attributes_test_current(), array(array('id' => 12, 'label' => 'Medium')), array());

	pg_assert_same(array(array('id' => 12, 'label' => 'Medium', 'no_value' => true)), $plan['update']);

	$plan = api_product_attribute_option_plan(api_product_attributes_test_current(), array(array('id' => 12, 'label' => 'M', 'no_value' => false)), array());

	pg_assert_same(array(array('id' => 12, 'label' => 'M', 'no_value' => false)), $plan['update']);
}

function test_api_product_attributes_options_go_only_when_named()
{
	$plan = api_product_attribute_option_plan(api_product_attributes_test_current(), array(), array(13, 13));

	pg_assert_same(null, $plan['problem']);
	pg_assert_same(array(13), $plan['remove'], 'once');

	// The label of an option being removed is free for another one.
	$plan = api_product_attribute_option_plan(api_product_attributes_test_current(), array(array('id' => 11, 'label' => 'L')), array(13));

	pg_assert_same(null, $plan['problem'], 'L is free once 13 goes');
}

function test_api_product_attributes_option_changes_that_cannot_be_made()
{
	$kind = function ($sent, $remove) {
		$plan = api_product_attribute_option_plan(api_product_attributes_test_current(), $sent, $remove);
		return ($plan['problem'] === null) ? null : $plan['problem']['kind'];
	};

	pg_assert_same('foreign', $kind(array(), array(99)), 'removing another attribute\'s option');
	pg_assert_same('foreign', $kind(array(array('id' => 99, 'label' => 'XL')), array()), 'changing another attribute\'s option');
	pg_assert_same('removed', $kind(array(array('id' => 13, 'label' => 'Large')), array(13)), 'changed and removed');
	pg_assert_same('empty', $kind(array(), array(11, 12, 13)), 'no option left');
	pg_assert_same(null, $kind(array(array('id' => 0, 'label' => 'XL')), array(11, 12, 13)), 'all replaced by a new one');
	pg_assert_same('twice', $kind(array(array('id' => 0, 'label' => 's')), array()), 'a new option reading like one already there');
	pg_assert_same('twice', $kind(array(array('id' => 11, 'label' => 'M')), array()), 'renamed to another option\'s label');
}

function test_api_product_attributes_option_object()
{
	$option = api_product_attribute_option_present(array('id' => '12', 'label' => 'M', 'no_value' => '1'));

	pg_assert_same(array('id' => 12, 'label' => 'M', 'no_value' => true), $option);
	pg_assert_same(array_keys(api_product_attribute_option_schema()), array_keys($option), 'every field declared, in order');
}

function test_api_product_attributes_routes_reach_a_handler_under_the_products_scope()
{
	$found = 0;

	foreach (api_schema() as $route) {
		if (strpos($route['id'], 'product_attributes.') !== 0) {
			continue;
		}

		$found++;

		pg_assert_true(function_exists($route['handler']), $route['id'] . ': ' . $route['handler'] . '() exists');

		$write = ($route['method'] !== 'GET');

		pg_assert_same($write ? 'products:write' : 'products:read', $route['scope'], $route['id'] . ' scope');

		if ($write) {
			pg_assert_true(!empty($route['dry_run']), $route['id'] . ' takes X-Dry-Run');
		}
	}

	pg_assert_same(6, $found, 'list, get, create, update, delete and its POST address');
}
