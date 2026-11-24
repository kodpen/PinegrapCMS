<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a raised hand and the connection marks in a Workspace call
 * (includes/workspace/calls.php and assets/js/workspace_calls.js): the hand
 * and unhand marks, whose hands the call part names, the keep-alive that
 * renews them, the weak-connection count and "You are muted".
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
function workspace_calls_hand_test_function($name)
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/calls.php');

	if (!preg_match('/\nfunction ' . preg_quote($name, '/') . '\([^)]*\)\n\{\n(.*?)\n\}\n/s', $source, $match)) {
		return '';
	}

	return $match[1];
}

function test_workspace_calls_hand_marks_are_addressed_to_nobody()
{
	$from = array('id' => 10, 'call_id' => 1, 'left_at' => 0);

	pg_assert_true(in_array('hand', ws_call_signal_kinds(), true) && in_array('unhand', ws_call_signal_kinds(), true), 'hand and unhand are signal kinds');
	pg_assert_same('', ws_call_signal_check($from, null, 'hand', ''), 'a hand needs no addressee');
	pg_assert_same('', ws_call_signal_check($from, null, 'unhand', ''), 'nor does an unhand');
	pg_assert_true(ws_call_signal_to_nobody('hand') && ws_call_signal_to_nobody('unhand'), 'the hand marks go to nobody');
	pg_assert_true(strlen('unhand') <= 16, 'the kind fits ws_call_signals.kind (VARCHAR 16): no schema change');
}

function test_workspace_calls_hand_whose_hands_are_up()
{
	$now = 1700000000;
	$live = array(10, 11, 12);
	$mark = function ($member, $kind, $ago) use ($now) {
		return array('from_member_id' => $member, 'kind' => $kind, 'created_at' => $now - $ago);
	};

	pg_assert_same(array(), ws_call_hands_from(array(), $live, $now), 'no mark: no hand');
	pg_assert_same(array(11), ws_call_hands_from(array($mark(11, 'hand', 5)), $live, $now), 'a member\'s hand is up');
	pg_assert_same(array(), ws_call_hands_from(array($mark(11, 'unhand', 3), $mark(11, 'hand', 5)), $live, $now), 'its newest mark decides: lowered');
	pg_assert_same(array(11), ws_call_hands_from(array($mark(11, 'hand', 3), $mark(11, 'unhand', 5)), $live, $now), 'raised again');
	pg_assert_same(array(10, 12), ws_call_hands_from(array($mark(12, 'hand', 1), $mark(11, 'unhand', 2), $mark(10, 'hand', 4)), $live, $now), 'each member\'s hand is its own, in member order');
	pg_assert_same(array(), ws_call_hands_from(array($mark(13, 'hand', 1)), $live, $now), 'a hand of one no longer in the call is down');
	pg_assert_same(array(), ws_call_hands_from(array($mark(11, 'hand', WS_CALL_HAND_LIFE)), $live, $now), 'a hand not renewed in time is down (a closed tab)');
	pg_assert_true(WS_CALL_HAND_LIFE > 2 * 30, 'the browser renews its hand every 30 seconds: two renewals may be late before it lapses');
}

function test_workspace_calls_hand_the_server_reads_the_marks()
{
	$hands = workspace_calls_hand_test_function('ws_call_hands');
	$part = workspace_calls_hand_test_function('ws_call_live_part');
	$send = workspace_calls_hand_test_function('ws_call_signal_send');

	pg_assert_true(strpos($hands, "AND to_member_id = 0 AND kind IN ('hand', 'unhand')") !== false && strpos($hands, 'body') === false, 'the hands are read from the marks, never from a body');
	pg_assert_true(strpos($hands, "WHERE call_id = '\" . (int) \$call_id . \"' AND created_at > '\" . (\$now - WS_CALL_HAND_LIFE) . \"'") !== false, 'only the marks still alive are read, on the call\'s index');
	pg_assert_true(strpos($part, 'ws_call_hands((int) $call[\'id\'], $members)') !== false, 'the live read names the raised hands');
	pg_assert_true(strpos($send, 'ws_call_over_limit($sent, WS_CALL_SIGNALS_A_MINUTE)') !== false, 'hand marks count in the member\'s signals a minute');
	pg_assert_true(strpos($send, "if ((\$kind === 'share') || (\$kind === 'unshare')) {") !== false, 'a hand is no share: it goes through the plain writer, so another\'s hand never holds it');
}

function test_workspace_calls_hand_present_names_the_hands()
{
	$call = array('id' => 5, 'channel_id' => 7, 'kind' => 'voice', 'started_by' => 3, 'started_at' => 1700000000, 'state' => 'live');
	$members = array(array('id' => 10, 'user_id' => 3, 'guest_id' => 0, 'joined_at' => 1700000000));

	pg_assert_same(array(10), ws_call_present($call, $members, array(), 0, array('10'))['hands'], 'the raised hands\' member ids');
	pg_assert_same(array(), ws_call_present($call, $members, array())['hands'], 'none: an empty list');

	require_once(PG_FUNCTIONS_DIR . '/includes/workspace/api_calls.php');
	pg_assert_false(isset(ws_api_call_present(ws_call_present($call, $members, array(), 0, array(10)))['call']['hands']), 'the API\'s call route does not name the hands');
}

function test_workspace_calls_hand_texts()
{
	$strings = ws_call_js_strings();

	foreach (array('call_hand_raise' => 'Raise hand', 'call_hand_lower' => 'Lower hand', 'call_hand_up' => 'Hand raised', 'call_weak' => 'Weak connection', 'call_muted_talk' => 'You are muted') as $key => $text) {
		pg_assert_same(lang($text), $strings[$key] ?? null, $key . ' is the translated text');
	}
}

// Runs one script under node with the browser part loaded; '' without node.
function workspace_calls_hand_test_node($script)
{
	$node = trim((string) @shell_exec('command -v node 2>/dev/null'));

	if ($node === '') {
		return '';
	}

	$file = tempnam(sys_get_temp_dir(), 'pghand');
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace_calls.js');

	file_put_contents($file, "var window = { addEventListener: function () {} };\nvar navigator = {};\n" . $source . "\nvar C = window.PGWsCalls;\n" . $script);
	$out = (string) shell_exec(escapeshellarg($node) . ' ' . escapeshellarg($file) . ' 2>&1');
	@unlink($file);

	return trim($out);
}

function test_workspace_calls_hand_browser_parts()
{
	$out = workspace_calls_hand_test_node(implode("\n", array(
		"console.log(JSON.stringify([",
		"  C.keepAlive(false, false), C.keepAlive(true, false), C.keepAlive(false, true), C.keepAlive(true, true),",
		"  C.handState(false).label, C.handState(true).label, C.handState(true).pressed,",
		"  C.inbound([{ type: 'inbound-rtp', packetsReceived: 90, packetsLost: 2 }, { type: 'inbound-rtp', packetsReceived: 10, packetsLost: 0 }, { type: 'outbound-rtp', packetsSent: 50 }, null]),",
		"  C.lossRatio(null, { received: 10, lost: 0 }),",
		"  C.lossRatio({ received: 100, lost: 2 }, { received: 190, lost: 12 }),",
		"  C.lossRatio({ received: 100, lost: 5 }, { received: 100, lost: 4 }),",
		"  C.weak('connected', 0), C.weak('connected', 0.1), C.weak('connected', 0.01), C.weak('disconnected', 0), C.weak('failed', 0),",
		"  C.mutedHint(false, 1000, 1500), C.mutedHint(true, 1000, 1500), C.mutedHint(true, 1000, 5000), C.mutedHint(true, 0, 10)",
		"]));",
	)));

	if ($out === '') {
		pg_assert_true(true, 'node is not installed: the browser parts are checked in CI');
		return;
	}

	pg_assert_same(
		'[["ping"],["share"],["hand"],["share","hand"],"call_hand_raise","call_hand_lower","true",{"received":100,"lost":2},0,0.1,0,false,true,false,true,true,false,true,false,false]',
		$out,
		'the keep-alive renews a share and a hand; the button\'s label follows the hand; packets lost since the last count make a weak connection, as a broken one does; "You are muted" only while muted and heard a moment ago'
	);
}

function test_workspace_calls_hand_browser_wiring()
{
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace_calls.js');

	pg_assert_true(strpos($js, "makeButton('pg-icon-button', '', 'bi-hand-index', t('call_hand_raise'))") !== false, 'Raise hand is an icon button in the window\'s tools (look A)');
	pg_assert_true(strpos($js, "mine.handButton.classList.toggle('is-active', on);") !== false && strpos($js, "mine.handButton.setAttribute('aria-pressed', state.pressed);") !== false, 'a raised hand presses the button');
	pg_assert_true(strpos($js, "send(0, on ? 'hand' : 'unhand', '');") !== false, 'the hand goes as a mark to nobody');
	pg_assert_true(strpos($js, 'mine.hands = part.call.hands || [];') !== false, 'the others\' hands come from the call part');
	pg_assert_true(strpos($js, 'held.pc.getStats().then(') !== false && strpos($js, 'clearInterval(mine.stats);') !== false, 'the connections are counted with getStats, and no longer once the call ends');
	pg_assert_true(strpos($js, "mark('bi-hand-index-fill', t('call_hand_up'))") !== false && strpos($js, "mark('bi-reception-1', t('call_weak'))") !== false, 'each tile carries the hand and weak-connection marks');
	pg_assert_true(strpos($js, 'track.clone()') !== false && strpos($js, 'mine.hearCopy.stop();') !== false, 'the muted microphone is heard from a copy that is stopped again');
	pg_assert_true(strpos($js, "notice.appendChild(el('span', '', t('call_muted_talk')));") !== false, '"You are muted" is said in the window');

	foreach (array('backend.src.css', 'workspace_guest.css') as $sheet) {
		$css = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/css/' . $sheet);

		pg_assert_true(strpos($css, '.ws-call-tile-marks {') !== false && strpos($css, '.ws-call-tile-mark[hidden] {') !== false, $sheet . ' draws the tile\'s marks');
	}
}
