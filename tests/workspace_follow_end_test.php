<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for followEnd() of workspace.js, run under node: whether a
 * Workspace message pane (a channel's or a discussion's) follows its newest
 * line, and whether it goes down to it, after each event.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Runs followEnd() under node over a list of events, each fed the state the
// one before returned; the list of results, null when node is missing.
function workspace_follow_end_test_run($state, $events)
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace.js');

	pg_assert_true((bool) preg_match('/^    function followEnd\(state, event\) \{.*?^    \}$/ms', $source, $match), 'followEnd() is found in workspace.js');

	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);

	if ($code !== 0) {
		return null;
	}

	$file = @tempnam(sys_get_temp_dir(), 'pgwsend');

	if ($file === false) {
		return null;
	}

	file_put_contents($file, $match[0] . "\n"
		. 'var state = ' . json_encode($state) . ';' . "\n"
		. 'var out = ' . json_encode($events) . '.map(function (event) { state = followEnd(state, event); return state; });' . "\n"
		. 'console.log(JSON.stringify(out));');
	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	pg_assert_same(0, $code, 'node ran: ' . implode("\n", $out));

	return json_decode(implode("\n", $out), true);
}

// The "scroll" values of a run, one per event.
function workspace_follow_end_test_scrolls($run)
{
	return array_map(function ($step) { return $step['scroll']; }, $run);
}

// The "following" values of a run, one per event.
function workspace_follow_end_test_following($run)
{
	return array_map(function ($step) { return $step['following']; }, $run);
}

function test_workspace_follow_end_picture_before_load_keeps_the_end()
{
	// At the end; a picture takes its size (the pane's scroll event comes
	// first, 250 px taller, far from the end by geometry), a second message
	// arrives, the picture's load.
	$run = workspace_follow_end_test_run(array('following' => true, 'height' => 1000), array(
		array('type' => 'scroll', 'height' => 1250, 'near' => false),
		array('type' => 'grow', 'height' => 1330, 'near' => false),
		array('type' => 'scroll', 'height' => 1330, 'near' => true),
		array('type' => 'grow', 'height' => 1330, 'near' => true),
	));

	if ($run === null) {
		return;
	}

	pg_assert_same(array(true, true, true, true), workspace_follow_end_test_following($run), 'growth never drops the end');
	pg_assert_same(array(true, true, false, true), workspace_follow_end_test_scrolls($run), 'every growth goes down again');
	pg_assert_same(1330, $run[3]['height'], 'the height of the last stick is kept');
}

function test_workspace_follow_end_reader_above_stays_put()
{
	// Scrolled 300 px up (same height: the reader moved), then messages and
	// pictures arrive below.
	$run = workspace_follow_end_test_run(array('following' => true, 'height' => 1000), array(
		array('type' => 'scroll', 'height' => 1000, 'near' => false),
		array('type' => 'grow', 'height' => 1080, 'near' => false),
		array('type' => 'scroll', 'height' => 1080, 'near' => false),
		array('type' => 'grow', 'height' => 1300, 'near' => false),
	));

	if ($run === null) {
		return;
	}

	pg_assert_same(array(false, false, false, false), workspace_follow_end_test_following($run), 'the reader who scrolled away is not followed');
	pg_assert_same(array(false, false, false, false), workspace_follow_end_test_scrolls($run), 'the pane does not move');
}

function test_workspace_follow_end_back_down_follows_again()
{
	$run = workspace_follow_end_test_run(array('following' => false, 'height' => 1000), array(
		array('type' => 'scroll', 'height' => 1400, 'near' => true),
		array('type' => 'grow', 'height' => 1600, 'near' => false),
	));

	if ($run === null) {
		return;
	}

	pg_assert_same(array(true, true), workspace_follow_end_test_following($run), 'scrolled back to the end: followed again');
	pg_assert_same(array(false, true), workspace_follow_end_test_scrolls($run), 'the next growth goes down');
	pg_assert_same(1600, $run[1]['height']);
}

function test_workspace_follow_end_stick_and_leave()
{
	// The arrow or sending sticks; a jump to a message or older lines leave,
	// and growth after a leave does not pull the pane down.
	$run = workspace_follow_end_test_run(array('following' => false, 'height' => 0), array(
		array('type' => 'stick', 'height' => 900, 'near' => false),
		array('type' => 'leave', 'height' => 900, 'near' => true),
		array('type' => 'grow', 'height' => 1500, 'near' => false),
		array('type' => 'scroll', 'height' => 1500, 'near' => false),
	));

	if ($run === null) {
		return;
	}

	pg_assert_same(array(true, false, false, false), workspace_follow_end_test_following($run));
	pg_assert_same(array(true, false, false, false), workspace_follow_end_test_scrolls($run), 'a jump to a message wins over following');
	pg_assert_same(900, $run[1]['height']);
}

function test_workspace_follow_end_empty_state()
{
	// A pane never stuck yet (state without a height) follows from its
	// opening: the first growth goes down.
	$run = workspace_follow_end_test_run(array('following' => true), array(
		array('type' => 'grow', 'height' => 500, 'near' => false),
	));

	if ($run === null) {
		return;
	}

	pg_assert_same(array(array('following' => true, 'height' => 500, 'scroll' => true)), $run);
}
