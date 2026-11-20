<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the @herkes hint of a discussion and the channel's agents added
 * to a discussion in one go (includes/workspace/everyone_hint.php): when the
 * line under a person's message is written, what it names, which agents the
 * one-go list offers and what an add of several answers.
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

function ws_everyone_hint_test_rows()
{
	return array(
		array('id' => 1, 'kind' => 'app', 'handle' => 'own', 'reach_from' => 0),
		array('id' => 2, 'kind' => 'app', 'handle' => 'coder', 'reach_from' => 4),
		array('id' => 3, 'kind' => 'model', 'handle' => 'writer', 'reach_from' => 4),
		array('id' => 5, 'kind' => 'app', 'handle' => 'tester', 'reach_from' => 4),
	);
}

function test_workspace_everyone_hint_by_name()
{
	pg_assert_same(array('coder', 'tester'), ws_everyone_hint_by_name(ws_everyone_hint_test_rows()), 'the channel\'s application agents asked by name; a model agent and an own agent are not named');
	pg_assert_same(array(), ws_everyone_hint_by_name(array()), 'no rows, no names');
}

function test_workspace_everyone_hint_tag_woke()
{
	$rows = ws_everyone_hint_test_rows();

	pg_assert_true(ws_everyone_hint_tag_woke($rows, array('agent:1')), 'an own application agent woken: the tag woke it');
	pg_assert_false(ws_everyone_hint_tag_woke($rows, array('agent:2')), 'a channel\'s agent woken by its name is not the tag\'s');
	pg_assert_false(ws_everyone_hint_tag_woke($rows, array('model:3')), 'nor a model agent');
	pg_assert_false(ws_everyone_hint_tag_woke($rows, array()), 'nobody woken');
}

function test_workspace_everyone_hint_due()
{
	$names = array('coder');

	pg_assert_true(ws_everyone_hint_due(true, true, '@herkes look', false, $names), 'H1: a person\'s @herkes in a discussion that woke nobody, with a channel agent asked by name');
	pg_assert_true(ws_everyone_hint_due(true, true, 'Please @everyone.', false, $names), 'the English word too');
	pg_assert_false(ws_everyone_hint_due(true, true, '@herkes look', true, $names), 'H2: the tag woke an agent');
	pg_assert_false(ws_everyone_hint_due(false, true, '@herkes look', false, $names), 'H2: a channel that is no discussion');
	pg_assert_false(ws_everyone_hint_due(true, false, '@herkes look', false, $names), 'H2: an application\'s message');
	pg_assert_false(ws_everyone_hint_due(true, true, '@herkes look', false, array()), 'H2: the channel has no agent asked by name');
	pg_assert_false(ws_everyone_hint_due(true, true, 'look at this', false, $names), 'no tag, no line');
	pg_assert_false(ws_everyone_hint_due(true, true, 'ali@herkes.com', false, $names), 'an address is no tag');
}

function test_workspace_everyone_hint_line()
{
	$line = ws_everyone_hint_line(array('coder', 'tester'));

	pg_assert_contains('@coder, @tester', $line, 'the line names the agents asked by name');
	pg_assert_contains('Agents in this discussion', $line, 'and says where they are added');
	pg_assert_true(strpos($line, '<@') === false, 'the line carries no tag that could ring or wake');
}

function test_workspace_everyone_hint_from_channel()
{
	$addable = array(
		array('agent_id' => 2, 'handle' => 'coder'),
		array('agent_id' => 7, 'handle' => 'other'),
		array('agent_id' => 5, 'handle' => 'tester'),
	);

	pg_assert_same(array(2, 5), array_column(ws_channel_agents_from_channel($addable, array(5, 2, 9)), 'agent_id'), 'only the channel\'s agents that may still be added, in the list\'s order');
	pg_assert_same(array(), ws_channel_agents_from_channel($addable, array()), 'a channel with no agent offers none');
}

function test_workspace_everyone_hint_add_many()
{
	$saved = array();
	$try = function ($id) use (&$saved) {
		if ($id === 3) {
			return array('name' => 'Writer', 'error' => 'Only the manager of the channel adds this agent.');
		}

		$saved[] = $id;

		return array('name' => 'Agent ' . $id, 'error' => '');
	};

	$out = ws_channel_agents_add_many(array('2', 3, 5, 5, 0, -1), $try);

	pg_assert_same(array(2, 5), $saved, 'each picked agent is tried once; a refused one is not saved');
	pg_assert_same(array(2, 5), $out['added'], 'two are added');
	pg_assert_same(array('Writer: Only the manager of the channel adds this agent.'), $out['refused'], 'and the refused one is named with its reason');

	$out = ws_channel_agents_add_many(array(), $try);
	pg_assert_same(array(), $out['added'], 'nothing picked, nothing added');
}

function test_workspace_everyone_hint_wired()
{
	$messages = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/messages.php');
	pg_assert_contains('ws_everyone_hint_after(', $messages, 'a send and an edit write the hint');
	pg_assert_true(substr_count($messages, 'ws_everyone_hint_after(') >= 2, 'both the send and the edit');

	pg_assert_true(in_array('ws_channel_agents_add_many', ws_channel_agents_actions(), true), 'the panel answers the add of several');
	pg_assert_true(function_exists('ws_agent_wake_matches'), 'the wake rule stays');
}
