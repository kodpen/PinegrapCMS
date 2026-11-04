<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a flow Claude drafts in a channel: the instructions the site
 * serves tell it to read the kinds of box and to put the flow into its
 * answer's changes as a draft; the channel answer takes a flow draft, which
 * applications already could propose; and boxes sent without places are
 * laid out. Before, Claude answered that none of its tools could build a
 * flow and wrote the design out in words.
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

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/api.php');
require_once(__DIR__ . '/helpers/source_reader.php');

function workspace_claude_flow_drafts_test_code($function)
{
	$code = '';

	foreach (pg_test_source_tokens(pg_test_function_source($function)) as $token) {
		$code .= pg_test_source_text($token);
	}

	return $code;
}

// The instructions name the kinds of box, the draft's shape and what the
// person does with it.
function test_workspace_claude_flow_drafts_instructions()
{
	$text = ws_claude_routine_prompt('https://shop.example/pinegrap/integration.php');

	pg_assert_contains('GET /workspace/flow-kinds', $text, 'the kinds of box');
	pg_assert_contains('{"type": "flow", "action": "draft", "data": {"name": "...", "nodes": [', $text, 'the draft in changes');
	pg_assert_contains('exactly one box that starts it', $text, 'one start');
	pg_assert_contains('question put to people is an approval box', $text, 'the question box');
	pg_assert_contains('leave x and y out', $text, 'no places');
	pg_assert_contains('a value box (kind value) whose', $text, 'the value box');
	pg_assert_contains('rule reads two values (' . implode(', ', array('join', 'compare', 'add', 'subtract', 'add_days')) . ') needs both', $text, 'its rules with two values');
	pg_assert_same(array(), array_diff(ws_flow_derived_two_inputs(), array('join', 'compare', 'add', 'subtract', 'add_days')), 'every rule with two values named');
	pg_assert_contains('join writes a, a space, then b, so split a text between them', $text, 'a text split between a and b');
	pg_assert_contains('opens it on the Flows canvas and publishes it', $text, 'the person publishes');
}

// The channel answer takes a flow draft as an application's proposal does;
// a run on a task still does not.
function test_workspace_claude_flow_drafts_answer_route()
{
	$answer = workspace_claude_flow_drafts_test_code('ws_api_claude_answer');
	pg_assert_contains("ws_changes_input(\$params['changes'] ?? array(), (int) \$row['requested_by'], (int) \$row['channel_id'], 0, false, true)", $answer, 'the channel answer takes drafts');
	pg_assert_contains("ws_changes_input(\$params['changes'] ?? array(), (int) \$row['requested_by'], 0, (int) \$row['task_id'])", $answer, 'a task run does not');

	$input = workspace_claude_flow_drafts_test_code('ws_changes_input');
	pg_assert_contains("(\$type === 'flow') && (\$for_proposal || \$drafts)", $input, 'a flow draft with either flag');
}

// Boxes sent all in one place (none, or the same x and y) are laid out from
// the start; boxes with places of their own keep them.
function test_workspace_claude_flow_drafts_layout()
{
	$sent = array('type' => 'flow', 'action' => 'draft', 'data' => array(
		'name'  => 'Kampanya',
		'nodes' => array(
			array('id' => 'start', 'kind' => 'manual'),
			array('id' => 'ask', 'kind' => 'approval', 'config' => array('title' => 'Başlasın mı?', 'channel_id' => 3, 'approvers' => array(7))),
			array('id' => 'go', 'kind' => 'post', 'config' => array('body' => 'Başladı', 'channel_id' => 3)),
		),
		'edges' => array(array('from' => 'start', 'to' => 'ask', 'on' => 'next'), array('from' => 'ask', 'to' => 'go', 'on' => 'approved')),
	));
	$read = ws_flow_draft_proposal_input($sent);
	$at = array();

	foreach ($read['data']['graph']['nodes'] as $node) {
		$at[$node['id']] = array($node['x'], $node['y']);
	}

	pg_assert_true($read['ok'], 'read');
	pg_assert_same(array(WS_FLOW_LAYOUT_ORIGIN, WS_FLOW_LAYOUT_ORIGIN), $at['start'], 'the start first');
	pg_assert_same(array(WS_FLOW_LAYOUT_ORIGIN + 2 * WS_FLOW_LAYOUT_STEP_X, WS_FLOW_LAYOUT_ORIGIN), $at['go'], 'a column per step');

	foreach ($sent['data']['nodes'] as $index => $node) {
		$sent['data']['nodes'][$index] += array('x' => 0, 'y' => 0);
	}

	pg_assert_same($read['data']['graph']['nodes'], ws_flow_draft_proposal_input($sent)['data']['graph']['nodes'], 'all at 0, 0 is no place either');

	$sent['data']['nodes'][0]['x'] = 400;
	$placed = ws_flow_draft_proposal_input($sent)['data']['graph']['nodes'];
	pg_assert_same(400, $placed[0]['x'], 'places of their own are kept');
	pg_assert_same(0, $placed[2]['x'], 'and the others too');
}
