<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the hints on refusals (includes/fn/agent_guide_hints.php): every
 * hint names a guide section that exists and tools that are routes, stays
 * within its length, reaches the error object through api_fail()'s helper,
 * and the designer's refusals of design.proposals.create carry codes of
 * their own.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_MCP_ENTRY')) {
	define('PG_MCP_ENTRY', true);
}

if (!defined('PG_API_PANEL')) {
	define('PG_API_PANEL', true);
}

if (!defined('PG_INIT_LOADED')) {
	define('PG_INIT_LOADED', true);
}

if (!defined('PG_API_ENTRY')) {
	define('PG_API_ENTRY', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/agent_guide.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/agent_guide_hints.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/agent_guide_designer.php');
require_once(PG_FUNCTIONS_DIR . '/includes/oauth/server.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/openapi.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/dry_run.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/scopes.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/schema.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/response.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/resources/design.php');

// Every hint's guide is topic#section of a topic and section that exist.
function test_agent_guide_hints_name_sections_that_exist()
{
	$registry = pg_agent_guide_registry();

	foreach (pg_agent_guide_hints() as $code => $entry) {
		pg_assert_same(1, preg_match('/^([a-z_]+)#([a-z_]+)$/', $entry['guide'], $found), $code . ': topic#section');

		$sections = pg_agent_guide_sections($registry, $found[1]);

		pg_assert_true(is_array($sections), $code . ': topic ' . $found[1] . ' has its file');

		$ids = array();

		foreach ((array)$sections as $section) {
			$ids[] = $section['id'];
		}

		pg_assert_true(in_array($found[2], $ids, true), $code . ': section ' . $entry['guide'] . ' exists');
	}
}

// A hint is one sentence of at most 200 characters once its tools are named,
// and every tool it names is a route.
function test_agent_guide_hints_length_and_tools()
{
	$routes = array();

	foreach (api_schema() as $route) {
		$routes[$route['id']] = true;
	}

	foreach (pg_agent_guide_hints() as $code => $entry) {
		$hint = pg_agent_guide_hint($code);

		pg_assert_true(mb_strlen($hint['hint']) <= pg_agent_guide_hint_max(), $code . ': at most 200 characters (' . mb_strlen($hint['hint']) . ')');
		pg_assert_same(false, strpos($hint['hint'], '{tool:'), $code . ': tools named');
		pg_assert_same(1, preg_match('/^[A-Z<].*\.$/', $hint['hint']), $code . ': a sentence');

		foreach (pg_agent_guide_named_routes(array(array('lines' => array($entry['hint'])))) as $route_id) {
			pg_assert_true(isset($routes[$route_id]), $code . ': ' . $route_id . ' is a route');
		}
	}
}

// api_fail() adds hint and guide for a code the registry holds, nothing else.
function test_agent_guide_hints_reach_the_error_object()
{
	$error = api_error_with_hint(array('code' => 'design_node_unknown', 'message' => 'x'), 'design_node_unknown');

	pg_assert_same('designer#page', $error['guide'], 'guide');
	pg_assert_contains('design_pages_get', $error['hint'], 'the tool by its name');
	pg_assert_same('x', $error['message'], 'message kept');

	$plain = api_error_with_hint(array('code' => 'not_found', 'message' => 'y'), 'not_found');

	pg_assert_same(array('code' => 'not_found', 'message' => 'y'), $plain, 'no hint for a code without one');
	pg_assert_same(null, pg_agent_guide_hint('no_such_code'), 'unknown code');
}

// Every 422 code a hint is written for is explained by the error catalogue.
function test_agent_guide_hints_codes_are_catalogued()
{
	$catalogue = api_error_catalogue();

	foreach (array_keys(pg_agent_guide_hints()) as $code) {
		if ($code === 'design_page_changed') {
			continue;
		}

		pg_assert_true(isset($catalogue[$code]), $code . ': in api_error_catalogue()');
	}
}

// The designer's refusals: an unknown id, the inside of a <pg-keep> part,
// a wrong operation, each with its own code.
function test_agent_guide_hints_designer_refusal_codes()
{
	$tree = pg_agent_guide_designer_example_tree();
	$cases = array(
		'design_node_unknown' => array(array('op' => 'update', 'node' => 'sd_99', 'text' => 'Hi')),
		'design_keep_inside'  => array(array('op' => 'insert', 'parent' => 'sd_5', 'html' => '<p>Hi</p>')),
		'design_op_invalid'   => array(array('op' => 'update', 'node' => 'sd_3')),
	);

	foreach ($cases as $code => $ops) {
		$result = pg_design_ai_apply_ops($tree, $ops);

		pg_assert_same(false, $result['ok'], $code . ': refused');
		pg_assert_same($code, $result['code'], $code . ': code');
		pg_assert_same(0, $result['op'], $code . ': index');
		pg_assert_same($code, api_design_proposal_code($result['code']), $code . ': answered as is');
	}

	$before = pg_design_ai_apply_ops($tree, array(array('op' => 'insert', 'before' => 'sd_404', 'html' => '<p>Hi</p>')));

	pg_assert_same('design_node_unknown', $before['code'], 'before: a missing sibling');

	$empty = pg_design_ai_apply_ops($tree, array());

	pg_assert_same('validation_failed', $empty['code'], 'no operations');
	pg_assert_same('validation_failed', api_design_proposal_code(''), 'no code');
	pg_assert_same('validation_failed', api_design_proposal_code('something_else'), 'a code not the designer\'s');
}
