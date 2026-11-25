<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the "Who wakes here" block of an application agent's brief
 * (ws_agent_brief_who_wakes_lines()) and the instructions' lines on naming,
 * claiming first and progress: each wake rule as an application's words
 * meet it, a discussion's followed agent, a paused agent and the reader
 * left out, the cut at twelve and a person's request. No database.
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

// A reach row as ws_agent_reach_rows() gives it.
function workspace_agent_who_wakes_test_row($id, $handle, $rule = 'mention', $prefix = '', $extra = array())
{
	return array_merge(array(
		'id'          => $id,
		'kind'        => 'app',
		'status'      => 'active',
		'handle'      => $handle,
		'name'        => ucfirst($handle) . ' agent',
		'app_id'      => 100 + $id,
		'wake_rule'   => $rule,
		'wake_prefix' => $prefix,
	), $extra);
}

function workspace_agent_who_wakes_test_reader()
{
	return array('id' => 1, 'handle' => 'mimar', 'name' => 'Architect');
}

function workspace_agent_who_wakes_test_text($rows, $follow = null, $assistants = array(), $person = false)
{
	return implode("\n", ws_agent_brief_who_wakes_lines($rows, workspace_agent_who_wakes_test_reader(), $follow, $assistants, $person));
}

// Each rule as the reader's words meet it: a name always; replies also by an
// answer; every message and a prefix only by a name.
function test_workspace_agent_who_wakes_each_rule()
{
	$text = workspace_agent_who_wakes_test_text(array(
		workspace_agent_who_wakes_test_row(2, 'ci', 'all'),
		workspace_agent_who_wakes_test_row(3, 'ops', 'prefix', 'deploy:'),
		workspace_agent_who_wakes_test_row(4, 'review', 'replies'),
		workspace_agent_who_wakes_test_row(5, 'docs', 'mention'),
	));

	pg_assert_contains('Who wakes here', $text);
	pg_assert_contains('@ci (Ci agent): wakes when you name it.', $text);
	pg_assert_contains('@ops (Ops agent): wakes when you name it.', $text);
	pg_assert_contains('@docs (Docs agent): wakes when you name it.', $text);
	pg_assert_contains('@review (Review agent): wakes when you name it or answer one of its messages.', $text);
	pg_assert_true(strpos($text, 'deploy:') === false, 'a prefix never matches an application, so it is not offered');
	pg_assert_true(mb_strlen($text) <= 1000, 'at most 1,000 characters');
}

// An agent with no handle is named by its application's tag.
function test_workspace_agent_who_wakes_no_handle()
{
	$text = workspace_agent_who_wakes_test_text(array(workspace_agent_who_wakes_test_row(6, '', 'mention', '', array('name' => 'Bot'))));

	pg_assert_contains('<@app:106> (Bot): wakes when you name it.', $text);
}

// A paused or not allowed agent and the reader itself are left out; with
// nobody left the block says the reader's words wake nobody.
function test_workspace_agent_who_wakes_left_out()
{
	$rows = array(
		workspace_agent_who_wakes_test_row(1, 'mimar', 'all'),
		workspace_agent_who_wakes_test_row(2, 'paused', 'mention', '', array('status' => 'paused')),
		workspace_agent_who_wakes_test_row(3, 'barred', 'mention', '', array('allowed' => 0)),
	);
	$text = workspace_agent_who_wakes_test_text($rows);

	pg_assert_true(strpos($text, '@mimar') === false, 'the reader is left out');
	pg_assert_true(strpos($text, '@paused') === false, 'a paused agent is left out');
	pg_assert_true(strpos($text, '@barred') === false, 'a not allowed agent is left out');
	pg_assert_contains('No other agent is reached here', $text);
}

// Over twelve agents: the first twelve and a count.
function test_workspace_agent_who_wakes_cut_at_twelve()
{
	$rows = array();

	for ($i = 2; $i <= 16; $i++) {
		$rows[] = workspace_agent_who_wakes_test_row($i, 'agent' . $i);
	}

	$text = workspace_agent_who_wakes_test_text($rows);

	pg_assert_contains('@agent13 ', $text);
	pg_assert_true(strpos($text, '@agent14 ') === false, 'the thirteenth is cut');
	pg_assert_contains('3 more agents are reached here', $text);
	pg_assert_true(mb_strlen($text) <= 1000, 'at most 1,000 characters');
}

// Long names never take the block past 1,000 characters; what does not fit
// is counted.
function test_workspace_agent_who_wakes_long_names()
{
	$rows = array();

	for ($i = 2; $i <= 13; $i++) {
		$rows[] = workspace_agent_who_wakes_test_row($i, 'agent' . $i, 'replies', '', array('name' => str_repeat("Long\nname ", 30)));
	}

	$text = workspace_agent_who_wakes_test_text($rows, array('handle' => '@agent2', 'registry' => true), array('Claude', 'Pinegrap AI'), true);

	pg_assert_true(mb_strlen($text) <= 1000, 'at most 1,000 characters, got ' . mb_strlen($text));
	pg_assert_contains('more agents are reached here', $text);
	pg_assert_true(strpos($text, "Long\nname") === false, 'a name stays on one line');
}

// A discussion following an agent: a person's message reaches it with no
// name, the reader's words still only by a name.
function test_workspace_agent_who_wakes_followed()
{
	$text = workspace_agent_who_wakes_test_text(array(workspace_agent_who_wakes_test_row(4, 'review', 'mention')), array('handle' => '@review', 'registry' => true));

	pg_assert_contains('This discussion follows @review: a person\'s message here reaches it with no name; your own words still wake it only as said above.', $text);

	$self = workspace_agent_who_wakes_test_text(array(), array('handle' => '@mimar', 'registry' => true));
	pg_assert_contains('This discussion follows you: a person\'s message here reaches you with no name.', $self);

	$claude = workspace_agent_who_wakes_test_text(array(), array('handle' => '@Claude', 'registry' => false));
	pg_assert_contains('This discussion follows @Claude: a person\'s message here reaches it with no name; your words never ask it.', $claude);
}

// Claude and Pinegrap AI, where they can be asked: an application's words
// never ask them.
function test_workspace_agent_who_wakes_assistants()
{
	$text = workspace_agent_who_wakes_test_text(array(), null, array('Claude', 'Pinegrap AI'));
	pg_assert_contains('Claude and Pinegrap AI are asked here by a person only: your words never ask them.', $text);

	$one = workspace_agent_who_wakes_test_text(array(), null, array('Claude'));
	pg_assert_contains('Claude is asked here by a person only: your words never ask it.', $one);

	$none = workspace_agent_who_wakes_test_text(array());
	pg_assert_true(strpos($none, 'by a person only') === false, 'no line where neither can be asked');
}

// A person's request: the answer tells the person, who is not named again.
function test_workspace_agent_who_wakes_person_request()
{
	$text = workspace_agent_who_wakes_test_text(array(), null, array(), true);
	pg_assert_contains('A person asked: your answer is written under their message and tells them itself, so do not name them again.', $text);

	$app = workspace_agent_who_wakes_test_text(array());
	pg_assert_true(strpos($app, 'A person asked') === false, 'no such line for an application\'s request');
}

// The block asks ws_agent_wake_matches() itself: a row whose rule the
// function would never meet by a name is not said to wake.
function test_workspace_agent_who_wakes_agrees_with_matcher()
{
	foreach (array('mention', 'all', 'prefix', 'replies') as $rule) {
		$row = workspace_agent_who_wakes_test_row(9, 'x' . $rule, $rule, 'go:');
		$text = workspace_agent_who_wakes_test_text(array($row));
		$replies = ws_agent_wake_matches($row, 'thanks', true, true);

		pg_assert_true(ws_agent_wake_matches($row, '@x' . $rule . ' hi', false, true), 'a name wakes ' . $rule);
		pg_assert_true(ws_agent_wake_matches($row, 'go: hi', false, true) === false || $rule === 'replies', 'a prefix alone never wakes ' . $rule);
		pg_assert_true((strpos($text, 'or answer one of its messages') !== false) === $replies, 'the line agrees for ' . $rule);
	}
}

// The instructions: when to name an agent, why the claim comes first, and
// progress as a rule for long work.
function test_workspace_agent_who_wakes_instruction_lines()
{
	$writing = implode("\n", ws_agent_instructions_writing_lines());
	pg_assert_contains('Name an agent with @ only in a question or an order to it, or in an answer it must read', $writing);
	pg_assert_contains('A note that asks nothing names nobody with the mark', $writing);
	pg_assert_contains('An answer under a message wakes nobody by itself', $writing);
	pg_assert_contains('"Who wakes here"', $writing);

	$request = implode("\n", ws_agent_instructions_request_lines());
	pg_assert_contains('the channel shows you as working only from your claim to the close', $request);
	pg_assert_contains('start no work on a request before its claim', $request);
	pg_assert_true(strpos($request, 'you may say where you are') === false, 'progress is no longer optional');
	pg_assert_contains('When a request takes longer than a few minutes, say where you are', $request);
}
