<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for agents asked to approve (includes/workspace/approval_agents.php):
 * only a request a person makes by hand may ask an agent, never a flow's, a
 * scheduled action's or an application's; an agent never answers a request
 * its own application wrote; the rule counts an agent as a person; what the
 * agent's request carries and tells it.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Function definitions only: nothing in these files runs a query when loaded.
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/approvals.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/approval_agents.php');

function test_approval_agents_allowed_only_by_hand_and_by_a_person()
{
	pg_assert_true(ws_approval_agents_allowed(true, 0), 'the form, a person');
	pg_assert_false(ws_approval_agents_allowed(false, 0), 'a flow or a scheduled action');
	pg_assert_false(ws_approval_agents_allowed(true, 7), 'an application, even marked by hand');
	pg_assert_false(ws_approval_agents_allowed(false, 7), 'an application');
	pg_assert_false(ws_approval_agents_allowed('1', 0), 'only a real true counts');
}

function test_approval_agents_clean_keeps_request_values_once()
{
	pg_assert_same(
		array('agent:5', 'model:2', 'claude'),
		ws_approval_agents_clean(array(' Agent:5 ', 'model:2', 'agent:5', 'app:5', 'agent:0', 'agent:x', '', 'claude', 'agent:5:1', 'claude:1'))
	);
	pg_assert_same(array(), ws_approval_agents_clean(null));
}

function test_approval_agent_never_answers_its_own_applications_request()
{
	pg_assert_false(ws_approval_agent_may_answer(12, 12), 'its own application wrote it');
	pg_assert_true(ws_approval_agent_may_answer(12, 0), 'a person wrote it');
	pg_assert_true(ws_approval_agent_may_answer(12, 9), 'another application wrote it');
}

function test_approval_outcome_counts_an_agent_as_a_person()
{
	$now = 1000;

	pg_assert_same('open', ws_approval_outcome('all', array('approved', 'pending'), $now, 0), 'all: the agent still to answer');
	pg_assert_same('approved', ws_approval_outcome('all', array('approved', 'approved'), $now, 0), 'all: person and agent approved');
	pg_assert_same('approved', ws_approval_outcome('any', array('pending', 'approved'), $now, 0), 'any: the agent approved first');
	pg_assert_same('rejected', ws_approval_outcome('any', array('approved', 'rejected'), $now, 0), 'a refusal settles it');
	pg_assert_same('expired', ws_approval_outcome('all', array('approved', 'pending'), $now, 900), 'an agent that never answered lets it expire');
}

function test_approval_agent_request_state_round_trip()
{
	$state = ws_approval_agent_request_state(array('id' => 41, 'title' => 'Publish the price list', 'rule' => 'all', 'closes_at' => 1700000000, 'extra' => 'x'));

	pg_assert_same(
		array('id' => 41, 'title' => 'Publish the price list', 'rule' => 'all', 'closes_at' => 1700000000),
		ws_approval_agent_request_of(array('ai_state' => $state))
	);
	pg_assert_same(null, ws_approval_agent_request_of(array('ai_state' => '{"item":{"kind":"note"}}')), 'a tag in a note is no approval');
	pg_assert_same(null, ws_approval_agent_request_of(array('ai_state' => '')));
}

function test_approval_agent_brief_names_the_request_and_the_route()
{
	$lines = ws_approval_agent_brief_lines(array('id' => 41, 'title' => 'Publish the price list', 'rule' => 'any', 'closes_at' => 0));
	$text = implode("\n", $lines);

	pg_assert_contains('"Publish the price list" (approval request 41)', $text);
	pg_assert_contains('POST /workspace/approvals/41/decide', $text);
	pg_assert_contains('The first approval settles it', $text);
	pg_assert_false(strpos($text, 'closes on') !== false, 'no deadline, no line');

	$all = implode("\n", ws_approval_agent_brief_lines(array('id' => 2, 'title' => 'T', 'rule' => 'all', 'closes_at' => 1700000000)));

	pg_assert_contains('Everybody asked has to approve it', $all);
	pg_assert_contains('It closes on 2023-11-14 22:13 UTC.', $all);
}

// The bound in the code: only the panel's own form passes may_ask_agents; a flow's
// approval box, a tool that asks first, a member's change and a scheduled
// action ask people only.
function test_approval_flow_callers_never_ask_by_hand()
{
	$root = PG_FUNCTIONS_DIR . '/includes/workspace/';

	foreach (array('scheduled.php', 'steps.php', 'approval_guests.php') as $file) {
		$source = (string) file_get_contents($root . $file);

		pg_assert_true(strpos($source, 'ws_approval_create(') !== false, $file . ' asks for approval');
		pg_assert_false(strpos($source, "'may_ask_agents'") !== false, $file . ' never asks agents');
	}

	pg_assert_contains("'may_ask_agents' => true", (string) file_get_contents($root . 'actions.php'), 'the panel form asks by hand');
}
