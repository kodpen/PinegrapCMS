<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a poll over the Workspace API: closed_at on the poll, can_vote
 * false past closes_at and in an earlier version of the conversation, and
 * the field the vote, change and close refusals name.
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

// A ws_polls row as the vote finds it.
function ws_api_polls_test_row($closes_at = 0, $closed_at = 0)
{
	return array('id' => 14, 'message_id' => 5601, 'channel_id' => 3, 'closes_at' => $closes_at, 'closed_at' => $closed_at, 'multiple' => 0, 'anonymous' => 0);
}

function ws_api_polls_test_source($function)
{
	$reflection = new ReflectionFunction($function);
	$lines = file($reflection->getFileName());

	return implode('', array_slice($lines, $reflection->getStartLine() - 1, $reflection->getEndLine() - $reflection->getStartLine() + 1));
}

// An open poll in the current conversation takes a vote.
function test_ws_poll_vote_state_open()
{
	$state = ws_poll_vote_state(ws_api_polls_test_row(time() + 3600), false);

	pg_assert_true($state['ok'], 'open until an hour from now');
	pg_assert_true(ws_poll_vote_state(ws_api_polls_test_row(0), false)['ok'], 'open until somebody closes it');
}

// Past closes_at but not yet closed: no vote, said on closes_at, and the
// vote closes it.
function test_ws_poll_vote_state_past_closes_at()
{
	$state = ws_poll_vote_state(ws_api_polls_test_row(time() - 60), false);

	pg_assert_false($state['ok'], 'its time ran out');
	pg_assert_same('closes_at', $state['field']);
	pg_assert_true($state['expired'], 'the vote closes it');
	pg_assert_same(lang('The poll is closed.'), $state['error']);
}

// Closed: no vote, said on closes_at, nothing left to close.
function test_ws_poll_vote_state_closed()
{
	$state = ws_poll_vote_state(ws_api_polls_test_row(0, time() - 600), false);

	pg_assert_false($state['ok']);
	pg_assert_same('closes_at', $state['field']);
	pg_assert_false($state['expired'], 'already closed');
}

// In an earlier version of the conversation: no vote, said on message_id,
// and the poll is left as it was even past its time.
function test_ws_poll_vote_state_earlier_version()
{
	$state = ws_poll_vote_state(ws_api_polls_test_row(time() + 3600), true);

	pg_assert_false($state['ok'], 'kept as it was');
	pg_assert_same('message_id', $state['field']);
	pg_assert_same(lang('This message is in an earlier version of the conversation, which is kept as it was.'), $state['error']);
	pg_assert_false(ws_poll_vote_state(ws_api_polls_test_row(time() - 60), true)['expired'], 'not closed by a refused vote');
}

// The card's can_vote is the vote's own state, so the panel and the API agree.
function test_ws_polls_map_can_vote_asks_the_vote_state()
{
	$source = ws_api_polls_test_source('ws_polls_map');

	pg_assert_contains('ws_poll_vote_state(', $source, 'can_vote from the vote state');
	pg_assert_contains("'closed_at'", $source, 'the closing time is carried');
	pg_assert_contains('ws_poll_vote_state(', ws_api_polls_test_source('ws_poll_vote_check'), 'the vote meets the same state');
}

// closed_at on the poll: null while open, the closing time once closed.
function test_ws_api_poll_present_closed_at()
{
	$closed = 1793091600;
	$present = ws_api_poll_present(array('id' => 14, 'closed' => true, 'closed_at' => $closed, 'closes_at' => 0, 'voters' => 0, 'options' => array(), 'result_id' => 0));

	pg_assert_same(api_time($closed), $present['closed_at']);
	pg_assert_same(null, ws_api_poll_present(array('id' => 14, 'closed_at' => 0, 'closes_at' => 0, 'voters' => 0, 'options' => array(), 'result_id' => 0))['closed_at'], 'open');
	pg_assert_same('string?', ws_api_poll_schema()['closed_at'] ?? null, 'declared');
	pg_assert_same(array_keys(ws_api_poll_schema()), array_keys($present), 'the answer has the declared fields in order');
}

// The refusals name their field: message_id for an earlier version, closes_at
// for a closed or expired poll, on the vote, the change and the close alike;
// the vote refuses through the same function before a dry run stops.
function test_ws_api_poll_refusals_name_their_field()
{
	pg_assert_contains("ws_api_poll_refused(lang('This message is in an earlier version of the conversation, which is kept as it was.'), 'message_id')", ws_api_polls_test_source('ws_api_poll_or_404'), 'an earlier version');

	$refused = ws_api_polls_test_source('ws_api_poll_refused');
	pg_assert_contains("lang('The poll is closed.')", $refused, 'a closed poll');
	pg_assert_contains("'closes_at'", $refused, 'named on closes_at');

	$vote = ws_api_polls_test_source('ws_api_polls_vote');
	pg_assert_contains("ws_api_poll_refused(\$checked['error'], \$checked['field'])", $vote, 'the vote refusal');
	pg_assert_true(strpos($vote, "ws_api_poll_refused(\$checked['error']") < strpos($vote, 'api_dry_run_stop('), 'a dry run meets it');
	pg_assert_contains('ws_api_poll_or_404(', ws_api_polls_test_source('ws_api_polls_update'), 'the change');
	pg_assert_contains('ws_api_poll_or_404(', ws_api_polls_test_source('ws_api_polls_close'), 'the close');
}
