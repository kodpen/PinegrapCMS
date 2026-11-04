<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the external API's refusal of a JSON list or object in a field
 * that takes one value: (string) on an array gave "Array" and a PHP warning,
 * so a text field was stored as "Array" with 200 and an enum, boolean or date
 * field printed the warning before its 422.
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

function test_api_single_value_refuses_a_list_or_an_object()
{
	foreach (array('string', 'enum', 'bool', 'datetime', 'int', 'money', 'decimal') as $type) {

		$param = array('name' => 'name', 'type' => $type);

		pg_assert_false(api_value_is_single($param, array('Europe')), $type . ': list');
		pg_assert_false(api_value_is_single($param, array('a' => 'b')), $type . ': object');
		pg_assert_false(api_value_is_single($param, array()), $type . ': empty list');
		pg_assert_false(api_value_is_single($param, new stdClass()), $type . ': stdClass');

	}

	pg_assert_false(api_value_is_single(array('name' => 'name'), array('a' => 'b')), 'no type is text');
}

function test_api_single_value_takes_one_value()
{
	foreach (array('Europe', '', 0, 12, 1.5, true, false, null) as $raw) {
		pg_assert_true(api_value_is_single(array('name' => 'name'), $raw), var_export($raw, true));
	}
}

function test_api_single_value_leaves_lists_and_objects_to_their_types()
{
	pg_assert_true(api_value_is_single(array('name' => 'items', 'type' => 'list'), array(1, 2)), 'list');
	pg_assert_true(api_value_is_single(array('name' => 'meta', 'type' => 'object'), array('a' => 'b')), 'object');
}

// The validator asks before any branch casts the value.
function test_api_single_value_is_checked_before_the_switch()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/api/router.php');
	$start = strpos($source, 'function api_validate_value(');
	$check = strpos($source, 'api_value_is_single($param, $raw)', (int) $start);
	$switch = strpos($source, 'switch ($type)', (int) $start);

	pg_assert_true(($start !== false) && ($check !== false) && ($switch !== false) && ($check < $switch), 'checked above the switch');
}
