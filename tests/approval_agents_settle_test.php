<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests that a settled approval request (approved, rejected, expired or
 * taken back) closes the open requests of the agents it asked: no agent is
 * left to answer a closed request, and the request carries why it closed.
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

function approval_agents_settle_test_source($function)
{
	$reflection = new ReflectionFunction($function);
	$lines = file($reflection->getFileName());

	return implode('', array_slice($lines, $reflection->getStartLine() - 1, $reflection->getEndLine() - $reflection->getStartLine() + 1));
}

// Only the requests of agents still to answer are closed.
function test_approval_agents_settle_open_requests()
{
	$rows = array(
		array('agent' => 'agent:5', 'request_id' => 31, 'decision' => 'pending'),
		array('agent' => 'model:2', 'request_id' => 32, 'decision' => 'pending'),
		array('agent' => 'ai', 'request_id' => 33, 'decision' => 'approved'),
		array('agent' => 'claude', 'request_id' => 0, 'decision' => 'pending'),
	);

	pg_assert_same(array(31 => 'agent:5', 32 => 'model:2'), ws_approval_agent_open_requests($rows));
	pg_assert_same(array(), ws_approval_agent_open_requests(array()));
}

// The request is told why it closed, in a sentence of its own.
function test_approval_agents_settle_reason()
{
	pg_assert_same('The approval request was settled before this agent answered.', ws_approval_agent_settled_reason());
}

// The settle closes the agents' requests on every outcome, a taken-back
// request included, before the result is written; the requests are
// cancelled through the shared helper, with the reason, and a waiting
// flow box hears of it.
function test_approval_agents_settle_closes_requests()
{
	$settle = approval_agents_settle_test_source('ws_approval_settle');
	$close = strpos($settle, 'ws_approval_agent_requests_close(');

	pg_assert_true($close !== false, 'the settle closes the agents\' requests');
	pg_assert_true($close > strpos($settle, 'mysqli_affected_rows'), 'only once the approval itself closed');
	pg_assert_true($close < strpos($settle, "if (\$outcome === 'approved')"), 'before any outcome branch: withdrawn and expired too');

	$closer = approval_agents_settle_test_source('ws_approval_agent_requests_close');

	pg_assert_contains('ws_agent_request_cancel(', $closer);
	pg_assert_contains('ws_approval_agent_settled_reason()', $closer);
	pg_assert_contains('ws_approval_agent_eye_off(', $closer);

	$cancel = approval_agents_settle_test_source('ws_agent_request_cancel');

	pg_assert_contains("status = 'cancelled'", $cancel);
	pg_assert_contains("status IN ('queued', 'sent', 'running')", $cancel);
	pg_assert_contains('answered_at', $cancel);
	pg_assert_contains('ws_step_agent_request_closed(', $cancel);

	$eye = approval_agents_settle_test_source('ws_approval_agent_eye_off');

	pg_assert_contains('ws_agent_react(', $eye, 'an application agent\'s eye');
	pg_assert_contains('ws_ai_react(', $eye, 'Pinegrap AI and model assistants');
	pg_assert_contains('ws_claude_react(', $eye, 'Claude');
}

// The site's run ends at once on a closed approval: no failure is written
// under it and no more steps are spent.
function test_approval_agents_settle_run_ends()
{
	$run = approval_agents_settle_test_source('ws_approval_agent_run_decide');

	pg_assert_contains("closed_at", $run);
	pg_assert_contains('ws_agent_request_cancel(', $run);
}
