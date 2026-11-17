<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for what the OpenAPI document says about itself beyond the route
 * table: one sentence for each tag, a description generated for every id
 * parameter that has none, and one example body for every route that takes
 * a body, built by the same function the route examples test sends through
 * the router's validator. None of it reaches the MCP tool list.
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

require_once(PG_FUNCTIONS_DIR . '/includes/api/schema.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/openapi.php');
require_once(PG_FUNCTIONS_DIR . '/includes/erp/api.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/api.php');
require_once(PG_FUNCTIONS_DIR . '/includes/translate/api.php');
require_once(PG_FUNCTIONS_DIR . '/includes/mcp/tools.php');

function api_openapi_explains_test_routes()
{
	return array_merge(api_schema(), erp_api_routes(), ws_api_routes(), translate_api_routes());
}

function test_api_openapi_explains_example_values_follow_the_declaration()
{
	pg_assert_same(5, api_openapi_example_value(array('type' => 'int', 'min' => 5)));
	pg_assert_same(1, api_openapi_example_value(array('type' => 'int')));
	pg_assert_same(0, api_openapi_example_value(array('type' => 'money', 'max' => 0)));
	pg_assert_same(true, api_openapi_example_value(array('type' => 'bool')));
	pg_assert_same('draft', api_openapi_example_value(array('type' => 'enum', 'values' => array('draft', 'live'))));
	pg_assert_same('2026-11-07', api_openapi_example_value(array('type' => 'datetime')));
	pg_assert_same(array(1), api_openapi_example_value(array('type' => 'list', 'of' => 'integer')));
	pg_assert_same(array('a'), api_openapi_example_value(array('type' => 'list', 'of' => 'string')));
	pg_assert_same('a', api_openapi_example_value(array('name' => 'title')));
}

function test_api_openapi_explains_example_body_holds_the_body_fields_only()
{
	$route = array('params' => array(
		array('name' => 'id', 'in' => 'path', 'type' => 'int'),
		array('name' => 'limit', 'in' => 'query', 'type' => 'int'),
		array('name' => 'name', 'in' => 'body', 'type' => 'string', 'required' => true),
		array('name' => 'price', 'in' => 'body', 'type' => 'money', 'min' => 0),
	));

	pg_assert_same(array('name' => 'a', 'price' => 0), api_openapi_example_body($route));
	pg_assert_same(null, api_openapi_example_body(array('params' => array(array('name' => 'id', 'in' => 'path')))));
}

function test_api_openapi_explains_id_descriptions()
{
	$route = array('path' => '/store/zones/{id}');

	pg_assert_same('Id of the record at /store/zones/{id}.', api_openapi_id_description($route, array('name' => 'id', 'in' => 'path')));
	pg_assert_same('Id of the account.', api_openapi_id_description($route, array('name' => 'account_id')));
	pg_assert_same('Id of the product group.', api_openapi_id_description($route, array('name' => 'product_group_id')));
	pg_assert_same('Ids of the calendars, as a list.', api_openapi_id_description($route, array('name' => 'calendar_ids')));
	pg_assert_same(null, api_openapi_id_description($route, array('name' => 'account_id', 'description' => 'Given.')));
	pg_assert_same(null, api_openapi_id_description($route, array('name' => 'name')));
	pg_assert_same(null, api_openapi_id_description($route, array('name' => 'paid')));
}

// Every id parameter of every route has a description in the document,
// written or generated.
function test_api_openapi_explains_every_id_parameter_is_described()
{
	$bare = array();

	foreach (api_openapi_explains_test_routes() as $route) {
		foreach ($route['params'] as $param) {
			if (!preg_match('/(^id|_ids?)$/', $param['name'])) {
				continue;
			}
			if (!isset($param['description']) && (api_openapi_id_description($route, $param) === null)) {
				$bare[] = $route['id'] . ' ' . $param['name'];
			}
		}
	}

	pg_assert_same(array(), $bare);
}

// One sentence for each group a route is listed under, and none for a group
// with no route.
function test_api_openapi_explains_every_tag_has_a_sentence()
{
	$tags = array();

	foreach (api_openapi_explains_test_routes() as $route) {
		$tags[api_openapi_tag($route['id'])] = true;
	}

	$sentences = api_openapi_tag_sentences();

	pg_assert_same(array(), array_values(array_diff(array_keys($tags), array_keys($sentences))), 'every group has a sentence');
	pg_assert_same(array(), array_values(array_diff(array_keys($sentences), array_keys($tags))), 'no sentence for a group without routes');

	foreach ($sentences as $tag => $sentence) {
		pg_assert_true(substr($sentence, -1) === '.', $tag . ' is a sentence');
		pg_assert_true(strlen($sentence) <= 200, $tag . ' is one sentence');
	}

	$listed = api_openapi_tags(array_keys($tags));

	pg_assert_same(count($tags), count($listed));
	pg_assert_same(array('name', 'description'), array_keys($listed[0]));
}

// The tool list an MCP client reads is built from the route table alone: the
// generated texts and examples never reach it.
function test_api_openapi_explains_mcp_tools_do_not_carry_them()
{
	$json = json_encode(pg_mcp_tools_list(api_openapi_explains_test_routes()));

	pg_assert_false(strpos($json, 'Id of the record at') !== false, 'no generated id description');
	pg_assert_false(strpos($json, '"example"') !== false, 'no example body');
}

// Nothing this file adds closes an answer's shape: no example or text says an
// answer (an error above all, which is gaining an errors list) has no other
// fields, so each stays true when a field is added.
function test_api_openapi_explains_nothing_says_there_are_no_other_fields()
{
	$said = array(json_encode(api_openapi_tag_sentences()));

	foreach (api_openapi_explains_test_routes() as $route) {
		$said[] = json_encode(api_openapi_example_body($route));
		foreach ($route['params'] as $param) {
			$said[] = (string)api_openapi_id_description($route, $param);
		}
	}

	$said = implode("\n", $said);

	foreach (array('additionalProperties', 'only these fields', 'no other field', 'nothing else') as $closed) {
		pg_assert_false(stripos($said, $closed) !== false, 'does not say ' . $closed);
	}

	pg_assert_false(strpos(json_encode(api_openapi_tags(array_keys(api_openapi_tag_sentences()))), 'additionalProperties') !== false, 'the tags list is open');
}
