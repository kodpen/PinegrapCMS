<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for where an agent question reaches the person it asks: the ring,
 * Today's count line and "Waiting for you" rows, the dashboard's Assistant
 * card, the channel export and tools/ws_poll.php's ask, again and withdraw.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/channel_export.php');
require_once(PG_FUNCTIONS_DIR . '/includes/dashboard/v2/cards/ai.php');
require_once(dirname(PG_FUNCTIONS_DIR) . '/tools/ws_poll.php');

function ws_agent_question_reach_test_source($function)
{
	$reflection = new ReflectionFunction($function);
	$lines = file($reflection->getFileName());

	return implode('', array_slice($lines, $reflection->getStartLine() - 1, $reflection->getEndLine() - $reflection->getStartLine() + 1));
}

// The asked are rung whatever their notify level: the ring and the answer
// read the same people.
function test_ws_question_ring_reads_the_asked()
{
	pg_assert_contains('ws_question_asked_ids($channel, $tokens)', ws_agent_question_reach_test_source('ws_question_ring'));
	pg_assert_contains("'question_asked'", ws_agent_question_reach_test_source('ws_question_ring'));
	pg_assert_false(strpos(ws_agent_question_reach_test_source('ws_question_ring'), 'notify_level') !== false, 'rung whatever the notify level');
	pg_assert_true(pg_notification_asks('workspace', 'question_asked'), 'the bell counts it as asked of the reader');
}

// Waiting for the reader: an open agent question that asks them.
function test_ws_agent_question_waits_for()
{
	$open = array('agent_question' => 1, 'closed_at' => 0, 'withdrawn_at' => 0);

	pg_assert_true(ws_agent_question_waits_for($open, array(4, 7), 7));
	pg_assert_false(ws_agent_question_waits_for($open, array(4), 7), 'not asked');
	pg_assert_false(ws_agent_question_waits_for($open, array(0), 0), 'nobody');
	pg_assert_false(ws_agent_question_waits_for(array('closed_at' => 50) + $open, array(7), 7), 'answered');
	pg_assert_false(ws_agent_question_waits_for(array('withdrawn_at' => 50) + $open, array(7), 7), 'taken back');
	pg_assert_false(ws_agent_question_waits_for(array('agent_question' => 0) + $open, array(7), 7), 'a person\'s poll');
}

// Today's count line names the questions waiting, between the read receipts and the tasks.
function test_ws_home_lead_questions()
{
	pg_assert_same(array('1 agent question for you'), ws_home_lead(array('questions' => 1)));
	pg_assert_same(array('1 approval request', '2 agent questions for you', '1 task due today or overdue'),
		ws_home_lead(array('approvals' => 1, 'questions' => 2, 'due' => 1)));
	pg_assert_contains("'questions'     => array_slice(\$questions, 0, 5)", ws_agent_question_reach_test_source('ws_home'));
}

// The Assistant card's question chips: the asker named, the newest first.
function test_pg_dashboard_v2_ai_question_chips()
{
	$chips = pg_dashboard_v2_ai_question_chips(array(
		array('question' => 'Which design?', 'asker' => array('name' => 'Elçin'), 'url' => 'workspace.php?channel=2&message=9', 'created_at' => 10),
		array('question' => 'Ship today?', 'asker' => null, 'url' => 'workspace.php?channel=2&message=12', 'created_at' => 30),
	));

	pg_assert_same('question', $chips[0]['kind']);
	pg_assert_same('Question: Elçin · Which design?', $chips[0]['text']);
	pg_assert_same('Question: Ship today?', $chips[1]['text'], 'no asker name');
	pg_assert_same(array(30, 10), array_column(pg_dashboard_v2_ai_chips_order($chips), 'time'));
	pg_assert_same(array(), pg_dashboard_v2_ai_question_chips(array()));
}

// The export: an agent question with its answer, or whom it waits for; a
// taken-back poll; the words of "Other".
function test_ws_channel_export_poll_line_agent_question()
{
	$asked = array('agent_question' => true, 'closed' => false, 'withdrawn' => false, 'asked' => array(array('id' => 4, 'name' => 'Erdal')), 'options' => array());

	pg_assert_same('Agent question: Which bar? (waiting for Erdal)', ws_channel_export_poll_line(' Which bar? ', $asked));
	pg_assert_same('Agent question: Which bar? — Weekly, "both" (Erdal)', ws_channel_export_poll_line('Which bar?',
		array('closed' => true, 'answer' => 'Weekly, "both"', 'answered_by' => array('id' => 4, 'name' => 'Erdal')) + $asked));
	pg_assert_same('Agent question (taken back): Which bar?', ws_channel_export_poll_line('Which bar?', array('withdrawn' => true) + $asked));

	$poll = array('closed' => false, 'withdrawn' => false, 'options' => array(
		array('label' => 'A', 'count' => 2, 'voters' => array(array('text' => ''), array('text' => ''))),
		array('label' => 'Other', 'count' => 1, 'other' => true, 'voters' => array(array('text' => 'C instead'))),
	));

	pg_assert_same('Poll: Q — A 2 · Other 1 (C instead)', ws_channel_export_poll_line('Q', $poll));
	pg_assert_same('Poll (taken back): Q — A 2 · Other 1 (C instead)', ws_channel_export_poll_line('Q', array('withdrawn' => true) + $poll));
	pg_assert_same('Poll (closed): Q — A 2 · Other 1', ws_channel_export_poll_line('Q', array('closed' => true, 'options' => array($poll['options'][0], array('voters' => array()) + $poll['options'][1])) + $poll), 'anonymous: no words');
}

// ws_poll.php asks with a description, the Recommended mark, "Other" and several answers.
function test_ws_poll_ask_body_parts()
{
	$body = ws_poll_ask_body('Q', array('Bar :: One bar a week', 'Boxes', 'Dots'), array('recommend' => true, 'other' => true, 'multiple' => true))['body'];

	pg_assert_same(array('label' => 'Bar', 'description' => 'One bar a week', 'recommended' => true), $body['options'][0]);
	pg_assert_same('Boxes', $body['options'][1], 'a plain option stays a text');
	pg_assert_same(array('label' => 'Other', 'other' => true), $body['options'][3]);
	pg_assert_true($body['multiple']);
	pg_assert_same(array('label' => 'Something else', 'other' => true), ws_poll_ask_body('Q', array('A', 'B'), array('other' => 'Something else'))['body']['options'][2]);
	pg_assert_same(array('question' => 'Q', 'options' => array('A', 'B')), ws_poll_ask_body('Q', array('A', 'B'), ws_poll_flags(array()))['body'], 'no flags: as before');
	pg_assert_same(null, ws_poll_ask_body('Q', array('A :: x', 'A :: y'))['body'], 'a repeated label is one option');
	pg_assert_same(null, ws_poll_ask_body('Q', range(1, 10), array('other' => true))['body'], '"Other" counts among the ten');
	pg_assert_same(null, ws_poll_ask_body('Q', array('A :: ' . str_repeat('ş', 501), 'B'))['body']);
	pg_assert_same(array('recommend' => true, 'other' => 'Else', 'multiple' => false), ws_poll_flags(array('recommend' => true, 'other' => 'Else')));
}

// withdraw and again take a poll id; again also a question and options.
function test_ws_poll_command_withdraw_and_again()
{
	pg_assert_same('withdraw', ws_poll_command(array('withdraw', '5'))['command']);
	pg_assert_true(ws_poll_command(array('withdraw'))['error'] !== '');
	pg_assert_same(array('5', 'Q', 'A', 'B'), ws_poll_command(array('again', '5', 'Q', 'A', 'B'))['args']);
	pg_assert_true(ws_poll_command(array('again', '5'))['error'] !== '', 'again without a question');
	pg_assert_same(array(), ws_poll_settings('again', array(), array('PG_WS_SITE' => 'https://a.b/x', 'PG_WS_KEY' => 'k', 'PG_WS_SECRET' => 's', 'PG_WS_CHANNEL' => ''))['missing'], 'again needs no channel');
}
