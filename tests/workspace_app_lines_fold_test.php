<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the fold of an application's one-line messages in a Workspace
 * channel: appLine() and appLineRuns() of workspace.js run under node, and
 * the payload flags ws_app_line_flags() of messages.php.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/messages.php');

// Runs appLineRuns() of workspace.js under node over the messages; false
// when node is missing, null when the functions are not found.
function workspace_app_lines_fold_test_runs($messages, $pin_id = 0, $days = false)
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace.js');

	if (!preg_match('/^    function appLine\(message, pinId\) \{.*?^    \}$/ms', $source, $line)
		|| !preg_match('/^    function appLineRuns\(messages, pinId, dayOf\) \{.*?^    \}$/ms', $source, $runs)) {
		return null;
	}

	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);

	if ($code !== 0) {
		return false;
	}

	$file = @tempnam(sys_get_temp_dir(), 'pgwsapl');

	if ($file === false) {
		return false;
	}

	file_put_contents($file, $line[0] . "\n" . $runs[0] . "\n"
		. 'var messages = ' . json_encode($messages) . ';' . "\n"
		. 'var dayOf = ' . ($days ? 'function (m) { return m.day || ""; }' : 'null') . ';' . "\n"
		. 'console.log(JSON.stringify(appLineRuns(messages, ' . (int) $pin_id . ', dayOf).map(function (r) { return r.ids; })));');
	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	return json_decode(implode("\n", $out), true);
}

// A plain line of application $app.
function workspace_app_lines_fold_test_line($id, $app = 7, $extra = array())
{
	return $extra + array(
		'id'          => $id,
		'kind'        => 'message',
		'sender_kind' => 'app',
		'sender'      => array('app_id' => $app, 'app' => true),
		'one_line'    => true,
		'mentions_me' => false,
		'parent_id'   => 0,
		'task_id'     => 0,
		'file'        => null,
		'deleted'     => false,
	);
}

function test_workspace_app_lines_fold_runs()
{
	$person = array('id' => 5, 'kind' => 'message', 'sender_kind' => 'user', 'sender' => array('id' => 3), 'deleted' => false);

	$runs = workspace_app_lines_fold_test_runs(array(
		workspace_app_lines_fold_test_line(1),
		workspace_app_lines_fold_test_line(2),
		workspace_app_lines_fold_test_line(3, 7, array('deleted' => true)),
		workspace_app_lines_fold_test_line(4),
		$person,
		workspace_app_lines_fold_test_line(6),
		workspace_app_lines_fold_test_line(7, 8),
		workspace_app_lines_fold_test_line(8, 8),
	));

	pg_assert_true($runs !== null, 'workspace.js defines appLine() and appLineRuns()');

	if ($runs === false) {
		return;
	}

	pg_assert_same(array(array(1, 2, 4), array(6), array(7, 8)), $runs,
		'a deleted line breaks nothing; a person or another application ends the run');
}

function test_workspace_app_lines_fold_what_stays_out()
{
	$out = array(
		array('one_line' => false),
		array('mentions_me' => true),
		array('file' => array('name' => 'a.png')),
		array('poll' => array('id' => 1)),
		array('approval' => array('id' => 1)),
		array('ack' => array('asked' => 1)),
		array('has_list' => true),
		array('kind' => 'decision'),
		array('kind' => 'note'),
		array('thread' => array('replies' => 2)),
		array('task_id' => 4),
		array('sender' => array('app_id' => 7, 'claude' => true)),
		array('sender' => array('app_id' => 7, 'ai' => true)),
		array('sender' => array('app_id' => 7, 'mcp' => true)),
		array('sender' => array('app_id' => 7, 'agent' => true)),
	);
	$messages = array();
	$id = 0;

	foreach ($out as $extra) {
		$messages[] = workspace_app_lines_fold_test_line(++$id);
		$messages[] = workspace_app_lines_fold_test_line(++$id, 7, $extra);
	}

	$messages[] = workspace_app_lines_fold_test_line(++$id);
	$runs = workspace_app_lines_fold_test_runs($messages);

	if (!is_array($runs)) {
		return;
	}

	$expected = array();

	for ($i = 1; $i <= $id; $i += 2) {
		$expected[] = array($i);
	}

	pg_assert_same($expected, $runs, 'each message with something on it, or from an agent, stays out and ends the run');

	pg_assert_same(array(array(1), array(3)), workspace_app_lines_fold_test_runs(array(
		workspace_app_lines_fold_test_line(1),
		workspace_app_lines_fold_test_line(2),
		workspace_app_lines_fold_test_line(3),
	), 2), 'the pinned message stays out');

	pg_assert_same(array(array(1, 2), array(3)), workspace_app_lines_fold_test_runs(array(
		workspace_app_lines_fold_test_line(1, 7, array('day' => 'a')),
		workspace_app_lines_fold_test_line(2, 7, array('day' => 'a')),
		workspace_app_lines_fold_test_line(3, 7, array('day' => 'b')),
	), 0, true), 'a new day starts a new run');
}

function test_workspace_app_lines_fold_payload_flags()
{
	pg_assert_same(array('one_line' => true, 'mentions_me' => false), ws_app_line_flags("CI passed on #8441\r\n", 5, array()), 'a trailing line break is still one line');
	pg_assert_same(array('one_line' => false, 'mentions_me' => false), ws_app_line_flags("Build failed\nsee the log", 5, array()), 'two lines');
	pg_assert_same(array('one_line' => false, 'mentions_me' => false), ws_app_line_flags('   ', 5, array()), 'an empty body is no line');
	pg_assert_same(true, ws_app_line_flags('Deploy waits for <@user:5>', 5, array())['mentions_me'], 'a mention of the reader');
	pg_assert_same(false, ws_app_line_flags('Deploy waits for <@user:51>', 5, array())['mentions_me'], 'another person');
	pg_assert_same(true, ws_app_line_flags('Ping <@dept:2>', 5, array(1, 2))['mentions_me'], 'a department of the reader');
	pg_assert_same(false, ws_app_line_flags('Ping <@dept:3>', 5, array(1, 2))['mentions_me'], 'another department');
}

// The payload carries the flags and the sender the agent mark; the screen
// folds after each drawing and keeps the choice per channel.
function test_workspace_app_lines_fold_wiring()
{
	$php = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/messages.php');
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace.js');

	pg_assert_contains("'agent'    => ws_app_is_agent(\$app_id),", $php, 'an application sender says whether it is a registered agent');
	pg_assert_contains("'one_line'    => false,", $php, 'the payload carries one_line');
	pg_assert_contains("'mentions_me' => false,", $php, 'the payload carries mentions_me');
	pg_assert_contains("'ws-app-lines:' + this.channel.id", $js, 'the choice is kept per channel in the browser');
	pg_assert_true(substr_count($js, 'foldAppLines();') >= 6, 'the fold is drawn again after drawing, arrival, change, removal and a new choice');
	pg_assert_contains('this.revealAppLine(messageId);', $js, 'a jump opens the run');
	pg_assert_contains("pane.querySelectorAll('.ws-msg:not(.ws-msg-folded)')", $js, 'the arrow keys skip folded lines');
}
