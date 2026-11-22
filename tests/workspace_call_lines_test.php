<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the lines a Workspace call writes into its channel
 * (includes/workspace/call_lines.php): the length, who took part, which
 * line an ended call writes, the words, and that the lines are system
 * lines written once, by the request that ended the call.
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

// The body of one function of a workspace file, or ''.
function workspace_call_lines_test_function($file, $name)
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/' . $file);

	if (!preg_match('/\nfunction ' . preg_quote($name, '/') . '\([^)]*\)\n\{\n(.*?)\n\}\n/s', $source, $match)) {
		return '';
	}

	return $match[1];
}

function test_workspace_call_lines_length()
{
	pg_assert_same('0:00', ws_call_line_length(-5), 'a length below zero reads as none');
	pg_assert_same('0:42', ws_call_line_length(42), 'seconds');
	pg_assert_same('12:05', ws_call_line_length(725), 'minutes and seconds');
	pg_assert_same('1:02:09', ws_call_line_length(3729), 'hours, minutes and seconds');
}

function test_workspace_call_lines_parties_once_in_join_order()
{
	$members = array(
		array('id' => 3, 'user_id' => 5, 'guest_id' => 0, 'joined_at' => 120),
		array('id' => 1, 'user_id' => 2, 'guest_id' => 0, 'joined_at' => 100),
		array('id' => 2, 'user_id' => 0, 'guest_id' => 9, 'joined_at' => 110),
		array('id' => 4, 'user_id' => 5, 'guest_id' => 0, 'joined_at' => 130),
	);

	pg_assert_same(array('user:2', 'guest:9', 'user:5'), ws_call_line_parties($members), 'each party once, by their first join');
	pg_assert_same(array(), ws_call_line_parties(array()), 'nobody');
}

function test_workspace_call_lines_party_words()
{
	pg_assert_same('<@user:7>', ws_call_line_party_word('user:7', array()), 'a person is the mention tag the channel draws as their name');
	pg_assert_same('Ada script', ws_call_line_party_word('guest:4', array('guest:4' => '<Ada> *script*')), 'a guest by its name, without markup');
	pg_assert_true(ws_call_line_party_word('guest:5', array()) !== '', 'a guest without a name is still named');
}

function test_workspace_call_lines_which_line_an_ended_call_writes()
{
	$call = array('started_by' => 2);

	pg_assert_same('missed', ws_call_line_end_kind(array('user:2'), $call, 2, 'private', 0), 'a conversation of two nobody answered is a missed call');
	pg_assert_same('declined', ws_call_line_end_kind(array('user:2'), $call, 2, 'private', 5), 'the other person declined it');
	pg_assert_same('ended', ws_call_line_end_kind(array('user:2', 'user:5'), $call, 2, 'private', 0), 'answered: the call ended');
	pg_assert_same('ended', ws_call_line_end_kind(array('user:2'), $call, 4, 'public', 0), 'a group call nobody joined ended, nobody is named as missing it');
	pg_assert_same('ended', ws_call_line_end_kind(array('user:2'), $call, 2, 'guest', 0), 'a guest room rings nobody, so nothing is missed');
	pg_assert_same('ended', ws_call_line_end_kind(array('user:2', 'guest:9'), $call, 1, 'guest', 0), 'a guest took part');
}

function test_workspace_call_lines_words()
{
	pg_assert_same('<@user:2> started a voice call.', ws_call_line_started_text('voice', '<@user:2>'), 'a voice call started');
	pg_assert_same('<@user:2> started a video call.', ws_call_line_started_text('video', '<@user:2>'), 'a video call started');
	pg_assert_same('Voice call ended · 12:05 · <@user:2>, Ada, <@user:5>', ws_call_line_ended_text('ended', 'voice', 725, array('<@user:2>', 'Ada', '<@user:5>'), ''), 'its length and who took part, the starter first');
	pg_assert_same('Video call ended · 0:42 · <@user:2>', ws_call_line_ended_text('ended', 'video', 42, array('<@user:2>'), ''), 'a video call');
	pg_assert_same('<@user:5> missed a voice call from <@user:2>.', ws_call_line_ended_text('missed', 'voice', 40, array('<@user:2>'), '<@user:5>'), 'a missed call names the one who did not answer');
	pg_assert_same('<@user:5> declined a video call from <@user:2>.', ws_call_line_ended_text('declined', 'video', 10, array('<@user:2>'), '<@user:5>'), 'a declined call');
}

function test_workspace_call_lines_are_system_lines_written_once()
{
	$end = workspace_call_lines_test_function('calls.php', 'ws_call_end_row');
	$start = workspace_call_lines_test_function('calls.php', 'ws_call_start');
	$decline = workspace_call_lines_test_function('calls.php', 'ws_call_decline');
	$lines = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/call_lines.php');

	pg_assert_true(strpos($end, 'mysqli_affected_rows(db::$con) > 0') !== false && strpos($end, 'if ($ended_here)') !== false, 'only the request that ended the call writes its line, so a race writes it once');
	pg_assert_true(strpos($start, 'ws_call_line_post_started(') !== false, 'a new call writes its started line');
	pg_assert_true(strpos($start, 'return ws_call_join(') < strpos($start, 'ws_call_line_post_started('), 'joining a call already on writes no started line');
	pg_assert_true(strpos($decline, 'ws_call_end_row($call, (int) $party[\'id\'])') !== false, 'a decline names who declined');
	pg_assert_true(strpos($lines, 'ws_message_system(') !== false, 'the lines are system lines');
	pg_assert_true(strpos($lines, 'ws_message_send(') === false, 'never a person\'s message, so no agent is woken or asked');
}
