<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for Right or Wrong on an agent's reply
 * (includes/workspace/agent_feedback.php) that need no database: who may
 * mark and who is refused, the reason's limit, the 7- and 30-day counts, the
 * route kept to the agent's own key, the person's mark from a team devices
 * session and on the API's message, the migration step and the
 * instructions line.
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
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/agent_feedback.php');

function ws_agent_feedback_test_records()
{
	return array(
		'request' => array('id' => 7, 'agent' => 'agent:3', 'status' => 'answered', 'requested_by' => 10, 'reply_message_id' => 50),
		'message' => array('id' => 50, 'channel_id' => 2, 'deleted_at' => 0),
	);
}

// The person who asked and a manager may mark; anybody else in the channel
// is refused, and nobody outside it learns the answer is there.
function test_workspace_agent_feedback_who_marks()
{
	$r = ws_agent_feedback_test_records();
	$asker = array('id' => 10, 'member' => true);
	$other = array('id' => 11, 'member' => true);

	pg_assert_true(ws_agent_feedback_check($asker, $r['request'], $r['message'], true, false, 'right', '')['ok'], 'the asker');
	pg_assert_true(ws_agent_feedback_check($other, $r['request'], $r['message'], true, true, 'wrong', 'no')['ok'], 'a manager of the channel');

	$said = ws_agent_feedback_check($other, $r['request'], $r['message'], true, false, 'right', '');
	pg_assert_same(array(false, 403), array($said['ok'], $said['status']), 'another member');

	pg_assert_same(404, ws_agent_feedback_check($asker, $r['request'], $r['message'], false, false, 'right', '')['status'], 'the asker who no longer reads the channel');
	pg_assert_same(404, ws_agent_feedback_check(array('id' => 10, 'member' => false), $r['request'], $r['message'], true, false, 'right', '')['status'], 'no longer a member');
	pg_assert_true(ws_agent_feedback_check($asker, $r['request'], $r['message'], true, false, null, '')['ok'], 'the asker takes the mark back');
	pg_assert_same(403, ws_agent_feedback_check($other, $r['request'], $r['message'], true, false, null, '')['status'], 'another member cannot take it back');
}

// Only an answered registry agent's reply, still there, is marked.
function test_workspace_agent_feedback_what_is_marked()
{
	$r = ws_agent_feedback_test_records();
	$asker = array('id' => 10, 'member' => true);

	pg_assert_true(ws_agent_feedback_check($asker, array('agent' => 'model:4') + $r['request'], $r['message'], true, false, 'right', '')['ok'], 'a model agent');

	foreach (array('claude', 'ai', 'mcp', 'app:3', 'agent:0') as $agent) {
		pg_assert_same(404, ws_agent_feedback_check($asker, array('agent' => $agent) + $r['request'], $r['message'], true, false, 'right', '')['status'], $agent);
	}

	pg_assert_same(404, ws_agent_feedback_check($asker, array('status' => 'running') + $r['request'], $r['message'], true, false, 'right', '')['status'], 'not answered yet');
	pg_assert_same(404, ws_agent_feedback_check($asker, $r['request'], array('id' => 51) + $r['message'], true, false, 'right', '')['status'], 'not the reply of that request');
	pg_assert_same(404, ws_agent_feedback_check($asker, $r['request'], array('deleted_at' => 5) + $r['message'], true, false, 'right', '')['status'], 'a deleted reply');
	pg_assert_same(404, ws_agent_feedback_check($asker, null, $r['message'], true, false, 'right', '')['status'], 'no request');
}

function test_workspace_agent_feedback_reason()
{
	$r = ws_agent_feedback_test_records();
	$asker = array('id' => 10, 'member' => true);

	pg_assert_same('it missed', ws_agent_feedback_check($asker, $r['request'], $r['message'], true, false, 'wrong', " it \n missed ")['reason'], 'spaces fold');
	pg_assert_true(ws_agent_feedback_check($asker, $r['request'], $r['message'], true, false, 'wrong', str_repeat('ğ', 300))['ok'], '300 characters');

	$long = ws_agent_feedback_check($asker, $r['request'], $r['message'], true, false, 'wrong', str_repeat('ğ', 301));
	pg_assert_same(array(false, 422, 'reason'), array($long['ok'], $long['status'], $long['field']), '301 characters');

	$bad = ws_agent_feedback_check($asker, $r['request'], $r['message'], true, false, 'maybe', '');
	pg_assert_same(array(422, 'verdict'), array($bad['status'], $bad['field']), 'right or wrong only');
}

// Counted by the time of the mark: inside 7 days in both, between 7 and 30
// in the 30 only, older in neither.
function test_workspace_agent_feedback_tally()
{
	$now = 1800000000;
	$day = 86400;
	$rows = array(
		array('verdict' => 'right', 'marked_at' => $now - 60),
		array('verdict' => 'wrong', 'marked_at' => $now - 6 * $day),
		array('verdict' => 'right', 'marked_at' => $now - 7 * $day),
		array('verdict' => 'wrong', 'marked_at' => $now - 20 * $day),
		array('verdict' => 'right', 'marked_at' => $now - 30 * $day),
		array('verdict' => 'right', 'marked_at' => $now - 40 * $day),
	);

	pg_assert_same(array(
		'days_7'  => array('right' => 1, 'wrong' => 1),
		'days_30' => array('right' => 2, 'wrong' => 2),
	), ws_agent_feedback_tally($rows, $now));
	pg_assert_same(array('days_7' => array('right' => 0, 'wrong' => 0), 'days_30' => array('right' => 0, 'wrong' => 0)), ws_agent_feedback_tally(array(), $now));
}

function test_workspace_agent_feedback_route()
{
	$found = null;

	foreach (ws_agent_request_api_routes() as $route) {
		if ($route['id'] === 'workspace.agent_feedback.get') {
			$found = $route;
		}
	}

	pg_assert_true(is_array($found), 'the route is listed');
	pg_assert_same(array('GET', '/workspace/agent-feedback', 'workspace:read', false), array($found['method'], $found['path'], $found['scope'], $found['mcp']), 'off MCP');

	// The handler takes the caller through the agent-only gate first.
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/api_agent_requests.php');
	$start = strpos($source, 'function ws_api_agent_feedback_get(');
	pg_assert_true($start !== false);
	pg_assert_contains('$agent = ws_api_agent_caller();', substr($source, $start, 200), 'any key but the agent\'s own is refused');

	pg_assert_contains('GET /workspace/agent-feedback', implode("\n", ws_agent_instructions_request_lines()), 'the instructions name the route');
}

function test_workspace_agent_feedback_schema_step()
{
	$migration = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/migrations/2026.11.0.php');

	pg_assert_contains('upgrade_2026_11_0_ws_agent_feedback();', $migration, 'the step is called');
	pg_assert_contains("install_create_table('ws_agent_feedback'", $migration, 'created with the idempotent helper');
	pg_assert_contains("'ws_agent_feedback',", (string) file_get_contents(PG_FUNCTIONS_DIR . '/install/index.php'), 'listed in get_tables()');
}

// Under a reply: only the person who asked and the channel's managers get
// the marks; anybody else, and any other request, nothing.
function test_workspace_agent_feedback_offer()
{
	$r = ws_agent_feedback_test_records();
	$mark = array('verdict' => 'wrong', 'reason' => 'missed it');

	pg_assert_same(array('verdict' => '', 'reason' => ''), ws_agent_feedback_offer(10, false, $r['request'], null), 'the asker, unmarked');
	pg_assert_same(array('verdict' => 'wrong', 'reason' => 'missed it'), ws_agent_feedback_offer(11, true, $r['request'], $mark), 'a manager sees the mark');
	pg_assert_same(null, ws_agent_feedback_offer(11, false, $r['request'], $mark), 'another member sees nothing');
	pg_assert_same(null, ws_agent_feedback_offer(10, false, array('agent' => 'claude') + $r['request'], null), 'not a registry agent');
	pg_assert_same(null, ws_agent_feedback_offer(10, false, array('status' => 'failed') + $r['request'], null), 'not answered');
}

// The panel action: the one writer and its refusals, '' takes the mark back.
function test_workspace_agent_feedback_action()
{
	$actions = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/actions.php');
	$start = strpos($actions, "case 'ws_agent_feedback':");

	pg_assert_true($start !== false, 'the action is handled');

	$case = substr($actions, $start, 700);
	pg_assert_contains('ws_agent_feedback_mark($viewer,', $case, 'through the one writer and its check');
	pg_assert_contains("(\$verdict === '') ? null : \$verdict", $case, 'an empty verdict takes the mark back');
	pg_assert_contains('ws_action_error($marked[\'error\'])', $case, 'the writer\'s refusal is the answer');
	pg_assert_contains("'ws_agent_feedback'", (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/panel/actions.php'), 'a listed panel action (CSRF and session checked there)');

	// The refusals the action answers with, from the writer's check.
	$r = ws_agent_feedback_test_records();
	pg_assert_same(403, ws_agent_feedback_check(array('id' => 11, 'member' => true), $r['request'], $r['message'], true, false, 'right', '')['status'], 'neither the asker nor a manager');
	pg_assert_same(404, ws_agent_feedback_check(array('id' => 10, 'member' => true), array('agent' => 'ai') + $r['request'], $r['message'], true, false, 'right', '')['status'], 'not an agent\'s reply');
	pg_assert_same(422, ws_agent_feedback_check(array('id' => 10, 'member' => true), $r['request'], $r['message'], true, false, 'wrong', str_repeat('a', 301))['status'], 'over 300 characters');

	pg_assert_contains("'feedback'", (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/messages.php'), 'the message carries the mark');
	pg_assert_contains('feedbackNode: function (message)', (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace.js'), 'drawn under the reply');
}

function test_workspace_agent_feedback_counts_text()
{
	$rows = array(
		array('agent' => 'agent:3', 'verdict' => 'right', 'd7' => '2', 'd30' => '5'),
		array('agent' => 'agent:3', 'verdict' => 'wrong', 'd7' => '0', 'd30' => '1'),
		array('agent' => 'model:4', 'verdict' => 'wrong', 'd7' => '1', 'd30' => '1'),
		array('agent' => 'agent:9', 'verdict' => 'maybe', 'd7' => '1', 'd30' => '1'),
	);
	$tally = ws_agent_feedback_tally_rows($rows);

	pg_assert_same(array('days_7' => array('right' => 2, 'wrong' => 0), 'days_30' => array('right' => 5, 'wrong' => 1)), $tally['agent:3']);
	pg_assert_same(array('days_7' => array('right' => 0, 'wrong' => 1), 'days_30' => array('right' => 0, 'wrong' => 1)), $tally['model:4']);
	pg_assert_false(isset($tally['agent:9']), 'no other verdict');

	$text = ws_agent_feedback_counts_text($tally['agent:3']);
	pg_assert_same(lang(array('string' => '7 days: {var:1} right · {var:2} wrong; 30 days: {var:3} right · {var:4} wrong', 'vars' => array(2, 0, 5, 1))), $text);
	pg_assert_same('', ws_agent_feedback_counts_text(null), 'nothing marked: left out');
	pg_assert_same(array(
		lang(array('string' => '{var:1} days: {var:2} right · {var:3} wrong', 'vars' => array(7, 2, 0))),
		lang(array('string' => '{var:1} days: {var:2} right · {var:3} wrong', 'vars' => array(30, 5, 1))),
	), ws_agent_feedback_counts_lines($tally['agent:3']), 'the agent card\'s two lines');
	pg_assert_same(array(), ws_agent_feedback_counts_lines(null));
	pg_assert_same('', ws_agent_feedback_counts_text(array('days_7' => array('right' => 0, 'wrong' => 0), 'days_30' => array('right' => 0, 'wrong' => 0))));

	pg_assert_contains('ws_agent_feedback_counts_text(', (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/agent_activity.php'), 'the Agents card row');
	pg_assert_contains("'feedback'     =>", (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/agent_card.php'), 'the agent card');
}

// The person's route: a team devices session only, no MCP tool, the panel's
// check before the rehearsal and the one writer after it.
function test_workspace_agent_feedback_person_route()
{
	$found = null;

	foreach (ws_api_routes() as $route) {
		if ($route['id'] === 'workspace.messages.agent_feedback') {
			$found = $route;
		}
	}

	pg_assert_true(is_array($found), 'the route is listed');
	pg_assert_same(array('POST', '/workspace/messages/{id}/agent-feedback', 'workspace:write', false, true), array($found['method'], $found['path'], $found['scope'], $found['mcp'], $found['dry_run']));

	$params = array();

	foreach ($found['params'] as $param) {
		$params[$param['name']] = $param;
	}

	pg_assert_true(!empty($params['verdict']['nullable']), 'null takes the mark back');
	pg_assert_same(array('right', 'wrong'), $params['verdict']['values']);
	pg_assert_same(WS_AGENT_FEEDBACK_REASON_MAX, $params['reason']['max_length']);

	$function = new ReflectionFunction('ws_api_messages_agent_feedback');
	$lines = file($function->getFileName());
	$source = implode('', array_slice($lines, $function->getStartLine() - 1, $function->getEndLine() - $function->getStartLine() + 1));
	$at = -1;

	foreach (array('ws_api_own_words_only(', 'ws_agent_feedback_ready()', 'ws_agent_feedback_check(', 'api_dry_run_stop(', 'ws_agent_feedback_mark(') as $call) {
		$next = strpos($source, $call);
		pg_assert_true(($next !== false) && ($next > $at), $call . ' in its order');
		$at = $next;
	}

	pg_assert_contains("\$verdict = (\$verdict === '') ? null : \$verdict;", $source, 'an empty verdict takes the mark back');
}

// The refusals keep the writer's status.
function test_workspace_agent_feedback_person_route_refusals()
{
	$function = new ReflectionFunction('ws_api_agent_feedback_refusal');
	$lines = file($function->getFileName());
	$source = implode('', array_slice($lines, $function->getStartLine() - 1, $function->getEndLine() - $function->getStartLine() + 1));

	pg_assert_contains('api_fail_not_found(', $source, '404');
	pg_assert_contains("api_fail(403, 'forbidden'", $source, '403');
	pg_assert_contains("api_fail(503, 'service_unavailable'", $source, '503');
	pg_assert_contains("api_fail_validation((string) \$result['error'], \$result['field'] ?? null)", $source, '422 with its field');
}

// The API's message carries the mark as the panel's payload does, read once
// for a page.
function test_workspace_agent_feedback_on_the_api_message()
{
	pg_assert_same('WorkspaceMessageFeedback?', ws_api_message_schema()['feedback']);
	pg_assert_same(array('verdict' => 'string', 'reason' => 'string'), ws_api_message_feedback_schema());

	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/api_resources.php');
	pg_assert_contains("'feedback'       => \$deleted ? null : (\$feedback[(int) \$row['id']] ?? null),", $source, 'null on a deleted message and for other readers');
	pg_assert_contains('$feedback = function_exists(\'ws_agent_feedback_map\') ? ws_agent_feedback_map($viewer, $ids) : array();', $source, 'one read for a page');
}
