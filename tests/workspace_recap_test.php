<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for "while you were away" (includes/workspace/briefing.php): the
 * facts of a channel said as phrases and as one sentence, and the text the
 * model reads and writes kept free of tags. The functions tested here read
 * nothing from the database and call no model.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The module file holds functions and constants only; loading it runs nothing.
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/briefing.php');

// ws_recap_channel()'s answer for a channel where nothing happened, with the
// given facts laid over it.
function workspace_recap_facts($changes = array())
{
	return array_merge(array(
		'messages'  => 0,
		'from'      => '',
		'guests'    => array(),
		'decisions' => 0,
		'tasks'     => 0,
		'done'      => 0,
		'approvals' => 0,
		'since'     => 0,
	), $changes);
}

// Each fact is one phrase, in the order the sentence reads them: the team's
// messages, decisions, what concerns the reader, what was finished, the
// customers.
function test_workspace_recap_parts_in_order()
{
	$parts = ws_recap_parts(workspace_recap_facts(array(
		'messages'  => 12,
		'from'      => 'Ayşe',
		'decisions' => 2,
		'tasks'     => 1,
		'approvals' => 3,
		'done'      => 1,
		'guests'    => array('Mehmet' => 3, 'Zeynep' => 1, 'Ali' => 1),
	)));

	pg_assert_same(array(
		'7 new messages (most from Ayşe)',
		'2 decisions written down',
		'a task handed to you',
		'3 approvals waiting for you',
		'a task finished',
		'Mehmet wrote 3 messages',
		'Zeynep wrote',
	), $parts, 'the phrases, two customers at most');
}

// One of each is said in words, and a message nobody is named for has no
// name in brackets.
function test_workspace_recap_parts_singular()
{
	pg_assert_same(array('a new message (Ayşe)', 'a decision written down', 'an approval waiting for you'), ws_recap_parts(workspace_recap_facts(array(
		'messages' => 1, 'from' => 'Ayşe', 'decisions' => 1, 'approvals' => 1,
	))), 'one of each');

	pg_assert_same(array('a new message'), ws_recap_parts(workspace_recap_facts(array('messages' => 1))), 'from an app');
	pg_assert_same(array('Mehmet wrote 2 messages'), ws_recap_parts(workspace_recap_facts(array('messages' => 2, 'guests' => array('Mehmet' => 2)))), 'the customer only, said once');
	pg_assert_same(array('4 new messages'), ws_recap_parts(workspace_recap_facts(array('messages' => 4))), 'no name');
}

// The e-mail says the tasks and approvals once for all channels, so a
// channel's line leaves them out.
function test_workspace_recap_parts_not_for_me()
{
	pg_assert_same(array('5 new messages (most from Ali)'), ws_recap_parts(workspace_recap_facts(array(
		'messages' => 5, 'from' => 'Ali', 'tasks' => 2, 'approvals' => 1,
	)), false), 'tasks and approvals left out');
}

function test_workspace_recap_sentence()
{
	pg_assert_same('While you were away: 3 new messages (most from Ali), 2 decisions written down, a task handed to you.', ws_recap_sentence(workspace_recap_facts(array(
		'messages' => 3, 'from' => 'Ali', 'decisions' => 2, 'tasks' => 1,
	))), 'the sentence');

	pg_assert_same('', ws_recap_sentence(workspace_recap_facts()), 'nothing happened, nothing said');
}

// The greeting says the bare count of messages in its own words; the recap
// line takes its place only when it has more to say.
function test_workspace_recap_says_more()
{
	pg_assert_false(ws_recap_says_more(workspace_recap_facts(array('messages' => 9, 'from' => 'Ali'))), 'messages only');
	pg_assert_true(ws_recap_says_more(workspace_recap_facts(array('messages' => 1, 'guests' => array('Mehmet' => 1)))), 'a customer wrote');
	pg_assert_true(ws_recap_says_more(workspace_recap_facts(array('done' => 1))), 'a task finished');
}

// Tags out of what goes to the model and of what comes back.
function test_workspace_recap_untag()
{
	pg_assert_same('Ayşe asked about order 1045, see #design.', ws_recap_untag('Ayşe <@user:4> asked about order 1045 <#order:1045>, see #design.'), 'tags dropped, words kept');
	pg_assert_same('no tags here', ws_recap_untag('  no tags here '), 'plain text trimmed');
}

function test_workspace_recap_facts_text()
{
	pg_assert_same('7 new messages, 1 decisions written down, 2 tasks handed to the reader, the guest Mehmet wrote 3 messages', ws_recap_facts_text(workspace_recap_facts(array(
		'messages' => 7, 'decisions' => 1, 'tasks' => 2, 'guests' => array('Mehmet' => 3),
	))), 'the facts for the model');
}
