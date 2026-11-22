<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for Workspace calls on a phone and through a changing network:
 * a member whose window went silent leaves the call on the server, one
 * person joins a call once (a second window takes the place over, never a
 * second seat), a leave or a signal of a window that lost its place changes
 * nothing, the browser's ICE restart and its patience with failed reads
 * (assets/js/workspace_calls.js, under node), and the phone's layout.
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

// The body of one function of calls.php, or ''.
function workspace_calls_endurance_test_function($name)
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/calls.php');

	if (!preg_match('/\nfunction ' . preg_quote($name, '/') . '\([^)]*\)\n\{\n(.*?)\n\}\n/s', $source, $match)) {
		return '';
	}

	return $match[1];
}

// Runs the script after workspace_calls.js under node and returns what it
// printed as JSON; false when node is missing.
function workspace_calls_endurance_test_run($script)
{
	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);

	if ($code !== 0) {
		return false;
	}

	$file = @tempnam(sys_get_temp_dir(), 'pgwscall');

	if ($file === false) {
		return false;
	}

	$stub = "var window = { addEventListener: function () {} }; var navigator = {}; var document = {};\n";
	file_put_contents($file, $stub . file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace_calls.js') . "\nvar C = window.PGWsCalls;\n" . $script);
	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	return json_decode(implode("\n", $out), true);
}

function test_workspace_calls_endurance_a_silent_member_is_gone()
{
	$member = array('id' => 5, 'joined_at' => 1000, 'left_at' => 0);

	pg_assert_false(ws_call_member_is_gone($member, 0, 1000 + WS_CALL_MEMBER_SILENCE), 'a member who just joined is not gone before its first keep-alive is due');
	pg_assert_true(ws_call_member_is_gone($member, 0, 1001 + WS_CALL_MEMBER_SILENCE), 'nothing heard from it since joining: gone');
	pg_assert_false(ws_call_member_is_gone($member, 1500, 1500 + WS_CALL_MEMBER_SILENCE), 'its own last signal keeps it in');
	pg_assert_true(ws_call_member_is_gone($member, 1500, 1501 + WS_CALL_MEMBER_SILENCE), 'silent too long after its last signal: gone');
	pg_assert_true(WS_CALL_MEMBER_SILENCE >= 75 && WS_CALL_MEMBER_SILENCE < WS_CALL_SILENCE, 'more than two keep-alives (30 s) are missed first, and a member goes before its whole call would');
	pg_assert_true(WS_CALL_MEMBER_SILENCE < WS_CALL_SIGNAL_LIFE, 'its last signal is still kept when the clean-up asks');
}

function test_workspace_calls_endurance_one_place_a_person()
{
	pg_assert_same('new', ws_call_join_place(null, false), 'not in the call: a new place');
	pg_assert_same('new', ws_call_join_place(null, true), 'Join here without a place elsewhere: a new place too');
	pg_assert_same('elsewhere', ws_call_join_place(array('id' => 5), false), 'already in the call in another window: refused, never a second seat');
	pg_assert_same('replace', ws_call_join_place(array('id' => 5), true), 'Join here: the other window\'s place is taken over');

	$join = workspace_calls_endurance_test_function('ws_call_join');

	pg_assert_true(strpos($join, "'code' => 'call_elsewhere'") !== false, 'the refusal carries its code');
	pg_assert_true(strpos($join, 'ws_call_member_out(') !== false, 'a take-over leaves the old place first (its signals and share go)');
	pg_assert_true(strpos(workspace_calls_endurance_test_function('ws_call_start'), 'ws_call_join($party, $channel, (int) $live[\'id\'])') !== false, 'a start where the person is already in the live call is the same refused join');
}

function test_workspace_calls_endurance_a_window_without_its_place()
{
	pg_assert_true(ws_call_member_matches(array('id' => 5), 0), 'no member id given (an older page): the party\'s place');
	pg_assert_true(ws_call_member_matches(array('id' => 5), 5), 'its own place');
	pg_assert_false(ws_call_member_matches(array('id' => 6), 5), 'a window whose place was taken over does not leave or speak for the new one');
	pg_assert_false(ws_call_member_matches(null, 5), 'no place at all');

	pg_assert_true(strpos(workspace_calls_endurance_test_function('ws_call_leave'), 'ws_call_member_matches($mine, $member_id)') !== false, 'a leave names its place');
	pg_assert_true(strpos(workspace_calls_endurance_test_function('ws_call_signal_send'), 'ws_call_member_matches($from, $member_id)') !== false, 'a signal names its place');

	$sweep = workspace_calls_endurance_test_function('ws_calls_sweep');

	pg_assert_true(strpos($sweep, 'ws_call_member_is_gone(') !== false, 'the clean-up takes silent members out');
	pg_assert_true(strpos($sweep, 's.from_member_id = m.id') !== false, 'by their own newest signal (idx_from)');

	$actions = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/actions.php');
	$guests = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/guests.php');

	pg_assert_true(strpos($actions, "!empty(\$request['again'])") !== false && strpos($guests, "!empty(\$request['again'])") !== false, 'the panel and the guest page pass Join here');
	pg_assert_true(strpos($actions, "(int) (\$request['member_id'] ?? 0)") !== false && strpos($guests, "(int) (\$request['member_id'] ?? 0)") !== false, 'and the window\'s place');
}

function test_workspace_calls_endurance_browser_parts()
{
	$got = workspace_calls_endurance_test_run(<<<'JS'
var live = { call: { id: 11, kind: 'video', members: [{ member_id: 1 }, { member_id: 2 }] }, member_id: 0 };
var mine = { call: live.call, member_id: 2 };
console.log(JSON.stringify({
	join: C.joinState(live, 0, 4),
	elsewhere: C.joinState(mine, 0, 4),
	in_it: C.joinState(mine, 11, 4),
	restart_failed: C.restartDue('failed', true, 1000, 0, 1000),
	restart_answerer: C.restartDue('failed', false, 1000, 0, 1000),
	restart_disconnected_early: C.restartDue('disconnected', true, 1000, 0, 2000),
	restart_disconnected: C.restartDue('disconnected', true, 1000, 0, 3000),
	restart_again_soon: C.restartDue('failed', true, 1000, 4000, 6000),
	restart_again: C.restartDue('failed', true, 1000, 4000, 9000),
	restart_connected: C.restartDue('connected', true, 0, 0, 9000),
	restart_network: C.restartDue('connected', true, 0, 0, 9000, true),
	place_ok: C.placeOutcome({ call: { id: 11 }, member_id: 2 }, 11, 2, true),
	place_moved: C.placeOutcome({ call: { id: 11 }, member_id: 3 }, 11, 2, true),
	place_gone: C.placeOutcome({ call: { id: 11 }, member_id: 0 }, 11, 2, true),
	call_over: C.placeOutcome({ call: null, member_id: 0 }, 11, 2, true),
	never_answered: C.placeOutcome(null, 11, 2, false),
	keep_reading: C.readGiveUp({ name: 'TypeError' }, 1000, 1000 + 20000),
	give_up_reading: C.readGiveUp({ name: 'TypeError' }, 1000, 1000 + 46000),
	refused: C.readGiveUp(new Error('This call has ended.'), 1000, 1500)
}));
JS
	);

	if ($got === false) {
		pg_assert_true(true, 'node is not installed: the browser parts are checked in CI');
		return;
	}

	pg_assert_true(is_array($got), 'workspace_calls.js runs under node');
	pg_assert_true($got['join']['show'] && !$got['join']['here'], 'not in the call: Join');
	pg_assert_true($got['elsewhere']['show'] && $got['elsewhere']['here'], 'in it in another window: Join here, never a second Join');
	pg_assert_false($got['in_it']['show'], 'the window in the call shows nothing');
	pg_assert_true($got['restart_failed'], 'a failed connection is restarted by its offerer');
	pg_assert_false($got['restart_answerer'], 'never by its answerer: one offer a pair');
	pg_assert_false($got['restart_disconnected_early'], 'a short break is waited out');
	pg_assert_true($got['restart_disconnected'], 'a break of two seconds is restarted');
	pg_assert_false($got['restart_again_soon'], 'restarts are spaced');
	pg_assert_true($got['restart_again'], 'and repeated while it stays broken');
	pg_assert_false($got['restart_connected'], 'a working connection is left alone');
	pg_assert_true($got['restart_network'], 'a changed network restarts even a connection still called connected');
	pg_assert_same('', $got['place_ok'], 'its own place: the call goes on');
	pg_assert_same('call_moved', $got['place_moved'], 'its place taken by another window: the call goes on there');
	pg_assert_same('call_lost', $got['place_gone'], 'taken out while the call goes on (it was silent): lost');
	pg_assert_same('call_ended', $got['call_over'], 'the call is over');
	pg_assert_same('call_unanswered', $got['never_answered'], 'nobody ever came');
	pg_assert_false($got['keep_reading'], 'a read lost to the network is tried again');
	pg_assert_true($got['give_up_reading'], 'not for ever');
	pg_assert_true($got['refused'], 'a refused read (the call ended) ends it here at once');
}

function test_workspace_calls_endurance_the_browser_wiring()
{
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace_calls.js');

	pg_assert_true(strpos($js, 'createOffer({ iceRestart: true })') !== false, 'the route is found again with an ICE restart');
	pg_assert_true(strpos($js, "window.addEventListener('online'") !== false, 'when the device comes back online');
	pg_assert_true(strpos($js, "navigator.connection.addEventListener('change'") !== false, 'and when its network changes');
	pg_assert_true(strpos($js, "h().api('ws_call_leave', { call_id: mine.id, member_id: mine.memberId }, { keepalive: true })") !== false, 'a closed tab leaves its own place, sent as the page goes');
	pg_assert_true(strpos($js, "ask.again = 1;") !== false, 'Join here takes the place over');
	pg_assert_false(strpos($js, "'ws-call-head ws-head-wide'"), 'the call buttons stay in a phone\'s narrow head');
}

function test_workspace_calls_endurance_the_phone_layout()
{
	$css = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/css/backend.src.css');

	pg_assert_true(preg_match('/@media \(max-width: 575\.98px\) \{\s*\.ws-call:not\(\.is-stage\) \{[^}]*left: \.5rem;/', $css) === 1, 'on a phone the window spans the width');
	pg_assert_true(strpos($css, 'max-height: calc(100dvh - ') !== false, 'and never runs off a short screen (landscape)');
	pg_assert_true(preg_match('/@media \(orientation: landscape\) and \(max-height: 500px\)/', $css) === 1, 'a phone on its side gets its own layout');
}
