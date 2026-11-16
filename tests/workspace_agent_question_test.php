<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the agent question (agent_question.php): the flag the server
 * sets, its settings, who may answer, settling by the first answer, the
 * answer as a line, the wake's brief and the memory index's decision lines.
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

require_once(PG_FUNCTIONS_DIR . '/includes/api/scopes.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/api.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/poll_parts.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/poll_again.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/agent_question.php');

function ws_agent_question_test_source($function)
{
	$reflection = new ReflectionFunction($function);
	$lines = file($reflection->getFileName());

	return implode('', array_slice($lines, $reflection->getStartLine() - 1, $reflection->getEndLine() - $reflection->getStartLine() + 1));
}

// Only an application asking makes an agent question; a person never does.
function test_ws_agent_question_flag()
{
	pg_assert_same(1, ws_agent_question_flag(12), 'an application');
	pg_assert_same(0, ws_agent_question_flag(0), 'a person');
	pg_assert_same(0, ws_agent_question_flag(-3));
	pg_assert_true(ws_agent_question_is(array('agent_question' => '1')));
	pg_assert_false(ws_agent_question_is(array('agent_question' => 0)));
	pg_assert_false(ws_agent_question_is(array()), 'a poll made before the step');
}

// The flag comes from the asking application, never from the request.
function test_ws_poll_create_sets_the_flag_from_the_app()
{
	$source = ws_agent_question_test_source('ws_poll_create');

	pg_assert_contains('ws_agent_question_flag($app_id)', $source);
	pg_assert_false(strpos($source, "\$data['agent_question']") !== false, 'the request never sets it');
}

// An agent question takes no anonymous vote and no closing time.
function test_ws_agent_question_settings()
{
	$sent = array('multiple' => 1, 'anonymous' => 1, 'closes_at' => 1900000000);
	$kept = ws_agent_question_settings($sent, true);

	pg_assert_same(0, $kept['anonymous']);
	pg_assert_same(0, $kept['closes_at']);
	pg_assert_same(1, $kept['multiple'], 'several answers stay possible');
	pg_assert_same($sent, ws_agent_question_settings($sent, false), 'a person\'s poll keeps what it was given');
}

// Only the people asked answer; a guest never.
function test_ws_agent_question_answer_refusal()
{
	pg_assert_same('', ws_agent_question_answer_refusal(4, false, array(4, 7)));
	pg_assert_same('Only the people asked can answer this question.', ws_agent_question_answer_refusal(5, false, array(4, 7)));
	pg_assert_same('Only the people asked can answer this question.', ws_agent_question_answer_refusal(0, false, array(0)));
	pg_assert_same('A guest cannot answer an agent question.', ws_agent_question_answer_refusal(4, true, array(4)));
}

// The answer cannot be taken back, so an empty one is refused.
function test_ws_agent_question_choice_refusal()
{
	pg_assert_same('Choose an answer first.', ws_agent_question_choice_refusal(array()));
	pg_assert_same('', ws_agent_question_choice_refusal(array(3)));
}

// An open agent question is not closed by hand; a person's poll is.
function test_ws_agent_question_close_refusal()
{
	pg_assert_same('An agent question closes itself when it is answered.', ws_agent_question_close_refusal(array('agent_question' => 1, 'closed_at' => 0)));
	pg_assert_same('', ws_agent_question_close_refusal(array('agent_question' => 1, 'closed_at' => 100)), 'already answered');
	pg_assert_same('', ws_agent_question_close_refusal(array('agent_question' => 0, 'closed_at' => 0)));
	pg_assert_contains('ws_agent_question_close_refusal($poll)', ws_agent_question_test_source('ws_poll_close'));
	pg_assert_contains('ws_agent_question_close_refusal($poll)', ws_agent_question_test_source('ws_api_polls_close'));
}

// The first answer settles it: closed before the votes are written.
function test_ws_poll_vote_settles_an_agent_question()
{
	$source = ws_agent_question_test_source('ws_poll_vote');
	$settle = strpos($source, 'ws_agent_question_settle(');
	$write = strpos($source, 'INSERT IGNORE INTO ws_poll_votes');

	pg_assert_true(($settle !== false) && ($write !== false) && ($settle < $write), 'settled before the answer is written');
	pg_assert_contains('ws_agent_question_wake($viewer, $poll)', $source);
	pg_assert_contains('AND closed_at = 0', ws_agent_question_test_source('ws_agent_question_settle'));
	pg_assert_contains('ws_agent_question_answer_check(', ws_agent_question_test_source('ws_poll_vote_check'));
	pg_assert_contains('ws_agent_question_is($poll)', ws_agent_question_test_source('ws_guest_vote'));
}

// The answer: the labels in the options' order, then the "Other" words.
function test_ws_agent_question_answer()
{
	$options = array(
		array('id' => 1, 'label' => 'Bar', 'other' => 0),
		array('id' => 2, 'label' => 'Boxes', 'other' => 0),
		array('id' => 3, 'label' => 'Other', 'other' => 1),
	);
	$votes = array(
		array('option_id' => 3, 'user_id' => 9, 'other_text' => 'Both, by week', 'created_at' => 50),
		array('option_id' => 2, 'user_id' => 9, 'other_text' => '', 'created_at' => 50),
	);
	$answer = ws_agent_question_answer($options, $votes);

	pg_assert_same(array('Boxes'), $answer['labels']);
	pg_assert_same('Both, by week', $answer['other_text']);
	pg_assert_same(9, $answer['by']);
	pg_assert_same(50, $answer['at']);
	pg_assert_same('Boxes, "Both, by week"', ws_agent_question_answer_text($answer));
	pg_assert_same(0, ws_agent_question_answer($options, array())['by'], 'unanswered');
	pg_assert_same('', ws_agent_question_answer_text(ws_agent_question_answer($options, array())));
}

// The memory index line: question — answer (who), newest first with the decisions.
function test_ws_agent_question_decision_lines()
{
	pg_assert_same('How is a plan drawn? — Bar (Erdal Güral)', ws_agent_question_decision_line(' How is a plan drawn? ', 'Bar', 'Erdal Güral'));
	pg_assert_same('Q — A', ws_agent_question_decision_line('Q', 'A', ''));

	$merged = ws_agent_question_decisions_merge(
		array(array('body' => 'd1', 'marked_at' => 30), array('body' => 'd2', 'marked_at' => 10)),
		array(array('body' => 'q1', 'marked_at' => 20)),
		2
	);

	pg_assert_same(array('d1', 'q1'), array_column($merged, 'body'));
	pg_assert_contains('ws_agent_question_decisions(', ws_agent_question_test_source('ws_memory_brief'));
}

// The Decisions tab: answered agent questions' messages among the decision
// messages by the time they were answered, each row kept whole.
function test_ws_channel_decisions_lists_answered_agent_questions()
{
	$merged = ws_agent_question_decisions_merge(
		array(array('id' => 5, 'kind' => 'decision', 'marked_at' => 300), array('id' => 2, 'kind' => 'note', 'marked_at' => 100)),
		array(array('id' => 9, 'kind' => 'message', 'marked_at' => 200)),
		300
	);

	pg_assert_same(array(5, 9, 2), array_column($merged, 'id'));
	pg_assert_same('message', $merged[1]['kind'], 'the question message stays as it is');
	pg_assert_contains('ws_agent_question_decision_messages(', ws_agent_question_test_source('ws_channel_decisions'));

	$source = ws_agent_question_test_source('ws_agent_question_decision_messages');

	pg_assert_contains('p.agent_question = 1 AND p.closed_at > 0', $source);
	pg_assert_contains('p.withdrawn_at = 0', $source);
	pg_assert_contains("\$row['marked_at'] = (int) \$row['answered_at']", $source);
}

// The request's state carries the question and the answer to the brief.
function test_ws_agent_question_request_state_and_brief()
{
	$state = ws_agent_question_request_state(array('poll_id' => 5, 'message_id' => 80, 'question' => 'Which design?', 'answer' => 'Two columns', 'by' => 'Selin K.'));
	$row = array('ai_state' => $state);
	$question = ws_agent_question_request_of($row);

	pg_assert_same(5, $question['poll_id']);
	pg_assert_same('Two columns', $question['answer']);
	pg_assert_same(null, ws_agent_question_request_of(array('ai_state' => '{"approval":{"id":1}}')));
	pg_assert_same(null, ws_agent_question_request_of(array()));

	$brief = implode("\n", ws_agent_question_brief_lines($question));

	pg_assert_contains('poll 5, message 80', $brief);
	pg_assert_contains('Selin K.', $brief);
	pg_assert_contains('Which design?', $brief);
	pg_assert_contains('Two columns', $brief);
	pg_assert_contains('ws_agent_question_brief_lines(', ws_agent_question_test_source('ws_agent_brief'));
	pg_assert_contains('ws_agent_question_brief_lines(', ws_agent_question_test_source('ws_ai_briefing'));
}

// The answer wakes the agent although the poll is its own message.
function test_ws_agent_after_send_answer_wakes_its_own_question()
{
	$source = ws_agent_question_test_source('ws_agent_after_send');

	pg_assert_contains("!\$answer && (\$own_app > 0)", $source);
	pg_assert_contains("\$answer ? 0 : ws_agent_chain_depth(\$chain)", $source);
	pg_assert_contains("'answer' => true", ws_agent_question_test_source('ws_agent_question_wake'));
}

// The API answers the agent question's fields, in its schema too.
function test_ws_api_poll_agent_question_fields()
{
	$present = ws_api_poll_present(array(
		'id' => 3, 'closes_at' => 0, 'voters' => 1, 'options' => array(), 'result_id' => 0,
		'agent_question' => true, 'asked' => array(array('id' => 4, 'name' => 'Erdal')), 'can_answer' => false,
		'answered_by' => array('id' => 4, 'name' => 'Erdal'), 'answered_at' => 0, 'answer' => 'Bar',
	));
	$schema = ws_api_poll_schema();

	pg_assert_true($present['agent_question']);
	pg_assert_same(array(array('user_id' => 4, 'name' => 'Erdal')), $present['asked']);
	pg_assert_same(array('user_id' => 4, 'name' => 'Erdal'), $present['answered_by']);
	pg_assert_same('Bar', $present['answer']);

	foreach (array('agent_question', 'asked', 'can_answer', 'answered_by', 'answered_at', 'answer') as $key) {
		pg_assert_true(array_key_exists($key, $present), $key . ' presented');
		pg_assert_true(array_key_exists($key, $schema), $key . ' in the schema');
	}

	pg_assert_same(null, ws_api_poll_present(array('id' => 1, 'closes_at' => 0, 'voters' => 0, 'options' => array(), 'result_id' => 0))['answered_by'], 'a person\'s poll');
}

// Step 11.0.214 adds only the flag, idempotently.
function test_upgrade_ws_poll_agent_question()
{
	$file = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/migrations/2026.11.0.php');
	$at = strpos($file, 'function upgrade_2026_11_0_ws_poll_agent_question()');
	$source = ($at === false) ? '' : substr($file, $at, 600);

	pg_assert_contains("install_add_column('ws_polls', 'agent_question', \"TINYINT UNSIGNED NOT NULL DEFAULT 0\")", $source);
	pg_assert_same(1, substr_count($source, 'install_add_column('), 'no other column');
	pg_assert_contains('upgrade_2026_11_0_ws_poll_agent_question();  // 11.0.214', $file);
}

// An answer to Claude's or Pinegrap AI's own question is not a new request
// of the person who answers, so their per-person limit does not apply.
function test_ws_assistant_per_person_limit_applies()
{
	pg_assert_true(ws_assistant_per_person_limit_applies(false), 'a request of the person');
	pg_assert_false(ws_assistant_per_person_limit_applies(true), 'an answer to the assistant\'s question');
}

// The wake passes the answer flag to both assistants and says so in the
// channel when the assistant still cannot be told.
function test_ws_agent_question_wake_answers_assistants()
{
	$source = ws_agent_question_test_source('ws_agent_question_wake');

	pg_assert_contains('ws_ai_after_send($viewer, $channel, (int) $poll[\'message_id\'], $body, true, true)', $source);
	pg_assert_contains('ws_claude_after_send($viewer, $channel, (int) $poll[\'message_id\'], $body, true, true)', $source);
	pg_assert_contains('ws_message_system(', $source);
	pg_assert_contains('ws_assistant_per_person_limit_applies($answer)', ws_agent_question_test_source('ws_claude_after_send'));
	pg_assert_contains('ws_assistant_per_person_limit_applies($answer)', ws_agent_question_test_source('ws_ai_after_send'));
}
