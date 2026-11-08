<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests that what the route table documents about each parameter passes the
 * router's own validator: the declared default, every value of an enum, and
 * one example request per route built from its declared types and bounds
 * with every parameter filled in. The OpenAPI document and the MCP tool
 * schemas print these declarations, so a default outside its own bounds, an
 * enum default that is not one of its values, a max below its min or a list
 * that takes no item would hand a client a request the API answers with 422.
 *
 * The checks run in a process of their own (helpers/api_route_examples_run.php):
 * the validator needs the real includes/api/response.php, and other test
 * files declare stand-ins for some of its functions.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The helper's answer, read once for every test below.
function api_route_examples_result()
{
	static $result = null;

	if ($result !== null) {
		return $result;
	}

	$output = '';

	if (function_exists('shell_exec')) {
		$command = escapeshellarg(PHP_BINARY) . ' '
			. escapeshellarg(__DIR__ . '/helpers/api_route_examples_run.php') . ' 2>&1';

		$output = (string) shell_exec($command);
	}

	$marker = strrpos($output, '@@RESULT@@');
	$decoded = ($marker === false) ? null : json_decode(substr($output, $marker + 10), true);

	$result = is_array($decoded) ? $decoded : array('error' => substr($output, 0, 2000));

	return $result;
}

function test_api_route_examples_helper_reads_the_route_table()
{
	$result = api_route_examples_result();

	pg_assert_false(isset($result['error']), 'the helper answered: ' . (isset($result['error']) ? $result['error'] : ''));
	pg_assert_true(isset($result['routes']) && ($result['routes'] > 300), 'every route table is read');
}

function test_api_route_examples_harness_catches_a_wrong_value()
{
	$result = api_route_examples_result();

	pg_assert_contains('validation_failed limit', isset($result['harness']['too_big']) ? (string) $result['harness']['too_big'] : '', 'a limit above its max is refused');
	pg_assert_true(array_key_exists('inside', isset($result['harness']) ? $result['harness'] : array()) && ($result['harness']['inside'] === null), 'a limit inside its bounds passes');
}

function test_api_route_examples_every_default_passes_its_validator()
{
	$result = api_route_examples_result();

	pg_assert_same(array(), isset($result['defaults']) ? $result['defaults'] : null, 'declared defaults the validator refuses');
}

function test_api_route_examples_every_enum_value_passes_its_validator()
{
	$result = api_route_examples_result();

	pg_assert_same(array(), isset($result['enums']) ? $result['enums'] : null, 'enum values the validator refuses');
}

function test_api_route_examples_every_route_takes_its_documented_request()
{
	$result = api_route_examples_result();

	pg_assert_same(array(), isset($result['requests']) ? $result['requests'] : null, 'routes that refuse their own documented request');
}
