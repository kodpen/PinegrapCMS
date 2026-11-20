<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for calls between two people on the Workspace channel screen: when
 * a call rings the other person and may be declined (calls.php), the
 * browser side's pure parts run under node (assets/js/workspace_calls.js:
 * where the buttons show, who offers, the members against the connections,
 * which rings show and go, the clock, the devices asked), and the wiring of
 * the look, the actions, the bundle and the Permissions-Policy.
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

function workspace_calls_screen_test_call($started_by = 3, $started_at = 1000, $state = 'live')
{
	return array('id' => 11, 'channel_id' => 7, 'started_by' => $started_by, 'started_at' => $started_at, 'state' => $state, 'kind' => 'voice');
}

function test_workspace_calls_screen_a_call_rings_the_other_of_two()
{
	$call = workspace_calls_screen_test_call();

	pg_assert_true(ws_call_rings($call, 4, array(3), 2, 1010), 'the other person of a conversation of two is rung while the caller waits alone');
	pg_assert_false(ws_call_rings($call, 3, array(3), 2, 1010), 'the caller is not rung by their own call');
	pg_assert_false(ws_call_rings($call, 4, array(3, 4), 2, 1010), 'once the other one is in, it no longer rings');
	pg_assert_false(ws_call_rings($call, 4, array(3), 3, 1010), 'a channel of three rings nobody (groups come later)');
	pg_assert_false(ws_call_rings($call, 4, array(3), 2, 1000 + WS_CALL_RING), 'it rings WS_CALL_RING seconds at most');
	pg_assert_true(ws_call_rings($call, 4, array(3), 2, 999 + WS_CALL_RING), 'and up to then');
	pg_assert_false(ws_call_rings(workspace_calls_screen_test_call(3, 1000, 'ended'), 4, array(3), 2, 1010), 'an ended call rings nobody');
	pg_assert_false(ws_call_rings($call, 4, array(), 2, 1010), 'a call its caller already left rings nobody');
}

function test_workspace_calls_screen_decline()
{
	$call = workspace_calls_screen_test_call();
	$other = array('kind' => 'user', 'id' => 4, 'member' => true);

	pg_assert_true(ws_call_may_decline($other, $call, array(3), 2), 'the person rung may decline');
	pg_assert_true(ws_call_may_decline($other, workspace_calls_screen_test_call(3, 1), array(3), 2), 'also after the ring\'s time, while the caller still waits');
	pg_assert_false(ws_call_may_decline(array('kind' => 'user', 'id' => 4, 'member' => false), $call, array(3), 2), 'someone outside the channel may not');
	pg_assert_false(ws_call_may_decline(array('kind' => 'user', 'id' => 3, 'member' => true), $call, array(3), 2), 'the caller hangs up, never declines');
	pg_assert_false(ws_call_may_decline($other, $call, array(3, 4), 2), 'a call already answered is not declined');
	pg_assert_false(ws_call_may_decline(array('kind' => 'guest', 'id' => 4), $call, array(3), 2), 'a guest declines nothing');
	pg_assert_false(ws_call_may_decline($other, $call, array(3), 3), 'nobody ends a call of a larger channel by declining');
	pg_assert_same(array(3, 5), ws_call_live_users(array(array('user_id' => 3, 'guest_id' => 0), array('user_id' => 0, 'guest_id' => 9), array('user_id' => 5, 'guest_id' => 0))), 'a call\'s people leave its guests out');
}

function test_workspace_calls_screen_public_route_and_texts()
{
	$servers = ws_call_ice_servers();

	pg_assert_true(count($servers) >= 1, 'a STUN address is given by default');

	foreach ($servers as $server) {
		foreach ((array) $server['urls'] as $url) {
			pg_assert_same(0, strpos($url, 'stun:'), 'the default is a STUN address, never a relay with a secret');
		}
	}

	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace_calls.js');
	preg_match_all("/t\\('(call_[a-z_]+)'/", $source, $used);
	preg_match_all("/'(call_[a-z_]+)'/", $source, $named);
	$strings = ws_call_js_strings();

	foreach (array_unique(array_merge($used[1], array('call_voice', 'call_video', 'call_ringing', 'call_ringing_video', 'call_ended', 'call_unanswered', 'call_lost'))) as $key) {
		pg_assert_true(isset($strings[$key]), 'the text ' . $key . ' is given to the screen');
	}

	pg_assert_true(count($named[1]) > 0, 'the module names its texts');
}

// Runs the script after workspace_calls.js under node and returns what it
// printed as JSON; false when node is missing.
function workspace_calls_screen_test_run($script)
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

function test_workspace_calls_screen_browser_parts()
{
	$got = workspace_calls_screen_test_run(<<<'JS'
var two = { joined: true, kind: 'private', members: [{ id: 3 }, { id: 4 }] };
var view = { calls: true, era: false, thread: false };
function copy(o, change) { var c = {}; Object.keys(o).forEach(function (k) { c[k] = o[k]; }); Object.keys(change).forEach(function (k) { c[k] = change[k]; }); return c; }
console.log(JSON.stringify({
	two: C.callable(two, view),
	three: C.callable(copy(two, { members: [{ id: 3 }, { id: 4 }, { id: 5 }] }), view),
	not_joined: C.callable(copy(two, { joined: false }), view),
	archived: C.callable(copy(two, { archived: true }), view),
	mailbox: C.callable(copy(two, { kind: 'mailbox' }), view),
	guest_room: C.callable(copy(two, { kind: 'guest' }), view),
	era: C.callable(two, copy(view, { era: true })),
	thread: C.callable(two, copy(view, { thread: true })),
	calls_off: C.callable(two, copy(view, { calls: false })),
	later_offers: C.offers(9, 8),
	earlier_waits: C.offers(8, 9),
	diff: C.peersDiff([{ member_id: 8 }, { member_id: 9 }, { member_id: 10 }], 8, [9, 12]),
	show: C.ringsToShow([{ call_id: 1 }, { call_id: 2 }, { call_id: 3 }, { call_id: 4 }], { 1: {} }, { 2: true }, 3),
	gone: C.ringsGone([{ call_id: 2 }], { 1: {}, 2: {} }),
	clocks: [C.clock(0), C.clock(65.7), C.clock(3725)],
	voice: C.mediaAsk('voice', null),
	video_chosen: C.mediaAsk('video', { audio: 'mic2', video: 'cam1' }),
	unsupported: C.supported()
}));
JS
	);

	if ($got === false) {
		return;
	}

	pg_assert_true(is_array($got), 'workspace_calls.js runs under node');
	pg_assert_true($got['two'], 'a conversation of two people carries the call buttons');
	pg_assert_false($got['three'], 'a channel of three does not, yet');
	pg_assert_false($got['not_joined'], 'nor one the viewer only reads');
	pg_assert_false($got['archived'], 'nor an archived one');
	pg_assert_false($got['mailbox'], 'nor a mailbox room');
	pg_assert_false($got['guest_room'], 'nor a guest room (guests come later)');
	pg_assert_false($got['era'], 'nor an earlier version of a conversation');
	pg_assert_false($got['thread'], 'nor a discussion');
	pg_assert_false($got['calls_off'], 'nor a site whose database has no calls');
	pg_assert_true($got['later_offers'], 'the later joiner makes the offer');
	pg_assert_false($got['earlier_waits'], 'the earlier one answers it');
	pg_assert_same(array('add' => array(10), 'remove' => array(12)), $got['diff'], 'members come and go against the connections held, the viewer left out');
	pg_assert_same(array(array('call_id' => 4)), $got['show'], 'a ring shows once, never after an answer here, never for the call this screen is in');
	pg_assert_same(array(1), $got['gone'], 'a ring no longer in the look goes');
	pg_assert_same(array('0:00', '1:05', '1:02:05'), $got['clocks'], 'the call\'s length reads m:ss, then h:mm:ss');
	pg_assert_false($got['voice']['video'], 'a voice call asks for no camera');
	pg_assert_true($got['voice']['audio']['echoCancellation'], 'the microphone cancels its echo');
	pg_assert_same(array('exact' => 'mic2'), $got['video_chosen']['audio']['deviceId'], 'the chosen microphone is asked for');
	pg_assert_same(array('exact' => 'cam1'), $got['video_chosen']['video']['deviceId'], 'the chosen camera is asked for');
	pg_assert_false($got['unsupported'], 'without WebRTC there is no call');
}

function test_workspace_calls_screen_wiring()
{
	$actions = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/actions.php');
	$panel = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/panel/actions.php');
	$screen = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace.js');

	pg_assert_contains('ws_call_rings_for($viewer)', $actions, 'the channel screen\'s look carries the rings');
	pg_assert_contains("case 'ws_call_sync':", $actions, 'a browser in a call reads it without the channel\'s look');
	pg_assert_contains("case 'ws_call_decline':", $actions, 'the person rung may decline');
	pg_assert_contains("'ws_call_decline', 'ws_call_sync'", $panel, 'both are panel actions of the team');
	pg_assert_contains('window.PGWsCalls.rings(data.rings', $screen, 'the screen hands the rings to the module');
	pg_assert_contains('window.PGWsCalls.headButtons(channel', $screen, 'the head draws the call buttons');

	$bundles = pg_js_bundles();
	$parts = $bundles['assets/js/workspace.min.js'];
	pg_assert_true(in_array('assets/js/workspace_calls.js', $parts, true), 'the module is in the Workspace bundle');
	pg_assert_true(array_search('assets/js/workspace_calls.js', $parts, true) < array_search('assets/js/workspace.js', $parts, true), 'before the screen that calls it');

	$waf = (string) file_get_contents(PG_FUNCTIONS_DIR . '/waf.php');
	pg_assert_true((bool) preg_match('/Permissions-Policy: [^\']*camera=\(self\)/', $waf), 'the camera is let through on the site\'s own pages, the Workspace and the guest page among them');
	pg_assert_true((bool) preg_match('/Permissions-Policy: [^\']*microphone=\(self\)/', $waf), 'so is the microphone');
	pg_assert_false(strpos((string) file_get_contents(PG_FUNCTIONS_DIR . '/workspace_guest.php'), 'Permissions-Policy') !== false, 'the guest page sends no narrower policy of its own');
}
