<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the flows screen as a role-3 member builds their own flows
 * (includes/workspace/flows.php, assets/js/workspace_flow.js): the Flows
 * entry shown to a member, the kinds of box their palette offers taken from
 * ws_flow_member_kinds(), a refused box on their flow marked with the save
 * check's words, no home channel, shares, templates, Pinegrap AI's draft or
 * a report's e-mail addresses for them, and staff unchanged throughout.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/templates.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/flows.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/flow_member.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/flow_runs.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/steps.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/flow_conditions.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/flow_values.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/flow_loops.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/flow_anchors.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/scheduled.php');

// A source file of the product, read once.
function ws_flows_member_canvas_test_source($path)
{
	static $read = array();

	if (!isset($read[$path])) {
		$read[$path] = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $path);
	}

	return $read[$path];
}

// The body of a PHP function of a source file, from its name to the next
// function.
function ws_flows_member_canvas_test_function($path, $name)
{
	$source = ws_flows_member_canvas_test_source($path);
	$at = strpos($source, 'function ' . $name . '(');

	pg_assert_true($at !== false, $name . ' is in ' . $path);

	$next = strpos($source, "\nfunction ", $at + 10);

	return substr($source, $at, (($next === false) ? strlen($source) : $next) - $at);
}

// A box of a graph.
function ws_flows_member_canvas_test_node($id, $kind, $config = array())
{
	return array('id' => $id, 'kind' => $kind, 'label' => '', 'x' => 0, 'y' => 0, 'config' => $config);
}

function test_workspace_flows_member_canvas_member_gets_the_member_kinds()
{
	$member = ws_flow_js_member('member');

	pg_assert_true($member['member']);
	pg_assert_same(ws_flow_member_kinds(), $member['member_kinds'], 'the palette follows the server list');
}

function test_workspace_flows_member_canvas_staff_unchanged()
{
	foreach (array('staff', '') as $builder) {
		pg_assert_same(array('member' => false, 'member_kinds' => null), ws_flow_js_member($builder), $builder);
		pg_assert_same(array(), ws_flow_member_problems($builder, array('nodes' => array(ws_flows_member_canvas_test_node('a', 'webhook')), 'edges' => array())), $builder);
	}
}

function test_workspace_flows_member_canvas_refused_boxes_are_marked()
{
	$graph = array(
		'nodes' => array(
			ws_flows_member_canvas_test_node('start', 'manual'),
			ws_flows_member_canvas_test_node('hook', 'webhook', array('url' => 'https://example.com/')),
			ws_flows_member_canvas_test_node('sum', 'report', array('title' => 'Week', 'metrics' => array(), 'channel_id' => 0, 'to' => 'a@example.com')),
			ws_flows_member_canvas_test_node('task', 'task', array('title' => 'Call')),
		),
		'edges' => array(),
	);
	$problems = ws_flow_member_problems('member', $graph);

	pg_assert_same(2, count($problems));
	pg_assert_same('hook', $problems[0]['node']);
	pg_assert_same('member_kind', $problems[0]['code']);
	pg_assert_contains(lang('This kind of box is not open to your own flows.'), $problems[0]['text']);
	pg_assert_same('sum', $problems[1]['node']);
	pg_assert_contains(lang('In your own flows a summary of counts goes to a channel, not to e-mail addresses.'), $problems[1]['text']);
}

function test_workspace_flows_member_canvas_entry_and_detail_ask_the_member()
{
	$nav = ws_flows_member_canvas_test_function('includes/workspace/flows.php', 'ws_flows_nav_visible');

	pg_assert_contains('ws_can_schedule($viewer) || ws_flow_member($viewer)', $nav, 'Flows is shown to a member who builds');

	$detail = ws_flows_member_canvas_test_function('includes/workspace/flows.php', 'ws_flow_detail');

	pg_assert_contains('ws_flow_member_problems(', $detail, 'a refused box is a problem on the canvas');
}

function test_workspace_flows_member_canvas_config_keeps_staff_extras_from_members()
{
	$config = ws_flows_member_canvas_test_function('includes/workspace/flows.php', 'ws_flow_js_config');

	pg_assert_contains('ws_flow_js_member(ws_flow_builder_kind($viewer))', $config);
	pg_assert_contains('ws_scheduled_js_config($viewer, true)', $config);
	pg_assert_contains("!\$member['member'] && function_exists('ws_mcp_active_servers')", $config, 'no MCP servers for a member');

	foreach (array('$staff_build && is_array', "'template_save' => \$staff_build", "'ai_sketch' => \$staff_build") as $gate) {
		pg_assert_contains($gate, $config, 'templates and the AI draft stay staff-only');
	}

	$scheduled = ws_flows_member_canvas_test_function('includes/workspace/scheduled.php', 'ws_scheduled_js_config');

	pg_assert_contains('$flows && function_exists(\'ws_flow_member\') && ws_flow_member($viewer)', $scheduled, 'only the canvas asks it for a member');
}

// The script takes the kinds from the configuration and names none of its
// own; the controls the server refuses a member are not drawn for them.
function test_workspace_flows_member_canvas_script_follows_the_server()
{
	$script = ws_flows_member_canvas_test_source('assets/js/workspace_flow.js');

	pg_assert_contains('FL.member_kinds', $script, 'the palette reads the server list');
	pg_assert_true(strpos($script, "'row_add', 'row_set'") === false, 'the member list is not copied into the script');

	foreach (array('function memberKind(', 'function homeField(', 'function shareOffered(', 'function reportToField(') as $piece) {
		pg_assert_contains($piece, $script);
	}

	pg_assert_contains('memberKind(item[0])', $script, 'the palette leaves refused kinds out');
}
