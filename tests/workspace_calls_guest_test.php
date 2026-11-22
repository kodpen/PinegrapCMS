<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a guest in a Workspace call: the guest page's bar that joins a
 * room's running call and never starts one (workspace_guest.php,
 * assets/js/workspace_guest.js), the Join button of the call module under
 * node (assets/js/workspace_calls.js), the guest's actions and texts
 * (includes/workspace/guests.php), and a guest room's call on the staff's
 * side: its head's buttons, no ring, no decline (calls.php).
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

function workspace_calls_guest_test_source($path)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $path);
}

// Runs the script after workspace_calls.js under node, with a browser that
// has WebRTC and helpers that build plain objects; false without node.
function workspace_calls_guest_test_run($script)
{
	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);

	if ($code !== 0) {
		return false;
	}

	$file = @tempnam(sys_get_temp_dir(), 'pgwsguest');

	if ($file === false) {
		return false;
	}

	$stub = <<<'JS'
var window = { addEventListener: function () {}, RTCPeerConnection: function () {} };
var navigator = { mediaDevices: { getUserMedia: function () {} } };
var document = {};
function node(tag, className) {
	return { tag: tag, className: className || '', children: [], hidden: false, disabled: false, isConnected: true, title: '', text: '', clicks: [], attrs: {},
		lastChild: null,
		classList: { toggle: function (name, on) { this.on = this.on || {}; this.on[name] = !!on; } },
		appendChild: function (child) { this.children.push(child); this.lastChild = child; return child; },
		setAttribute: function (k, v) { this.attrs[k] = v; },
		addEventListener: function (kind, fn) { this.clicks.push(fn); } };
}
var helpers = {
	t: function (key) { var rest = Array.prototype.slice.call(arguments, 1); return key + (rest.length ? ':' + rest.join('/') : ''); },
	el: node,
	icon: function (name) { return node('i', name); },
	button: function (className, label) { var b = node('button', className); b.lastChild = { nodeType: 3, nodeValue: label }; return b; },
	api: function () { return Promise.reject(new Error('no')); },
	toast: function () {},
	clear: function () {}
};
var hooks = { helpers: helpers, me: 0, ice: [], max: 4, soundOn: function () { return false; } };
JS;

	file_put_contents($file, $stub . "\n" . workspace_calls_guest_test_source('assets/js/workspace_calls.js') . "\nvar C = window.PGWsCalls;\n" . $script);
	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	return json_decode(implode("\n", $out), true);
}

function test_workspace_calls_guest_join_button_only()
{
	$got = workspace_calls_guest_test_run(<<<'JS'
var members = function (n) { var list = []; for (var i = 1; i <= n; i++) { list.push({ member_id: 20 + i, name: 'P' + i }); } return list; };
var part = function (n, mine) { return { call: { id: 7, kind: 'voice', members: members(n) }, member_id: mine || 0, signals: [] }; };
var box = C.joinButton(0, 'Room', hooks);
var join = box.children[0];
var seen = {};
C.channelCall(0, null, hooks);
seen.none = { hidden: join.hidden };
C.channelCall(0, part(2), hooks);
seen.two = { hidden: join.hidden, disabled: join.disabled, label: join.lastChild.nodeValue };
C.channelCall(0, part(4), hooks);
seen.full = { hidden: join.hidden, disabled: join.disabled, label: join.lastChild.nodeValue };
C.channelCall(0, part(3, 22), hooks);
seen.in_it = { hidden: join.hidden, label: join.lastChild.nodeValue };
var room = { joined: true, kind: 'guest', members: [{ id: 3 }] };
var view = { calls: true, era: false, thread: false };
console.log(JSON.stringify({
	buttons: box.children.length,
	join_class: join.className,
	seen: seen,
	room_one: C.callable(room, view),
	room_none: C.callable({ joined: true, kind: 'guest', members: [] }, view),
	room_group: C.isGroup(room),
	two_group: C.isGroup({ kind: 'private', members: [{}, {}] })
}));
JS
	);

	if ($got === false) {
		return;
	}

	pg_assert_true(is_array($got), 'workspace_calls.js runs under node');
	pg_assert_same(1, $got['buttons'], 'the guest page\'s box holds the Join button alone: no Voice call, no Video call');
	pg_assert_contains('ws-call-join', $got['join_class'], 'it is the Join button');
	pg_assert_true($got['seen']['none']['hidden'], 'hidden while the room holds no call');
	pg_assert_false($got['seen']['two']['hidden'], 'shown while one runs');
	pg_assert_same('call_join_count:2/4', $got['seen']['two']['label'], 'with how many are in it');
	pg_assert_true($got['seen']['full']['disabled'], 'disabled at four');
	pg_assert_same('call_full', $got['seen']['full']['label'], 'reading that the call is full');
	pg_assert_false($got['seen']['in_it']['hidden'], 'once the guest is in it in another window, the button offers to take that place over');
	pg_assert_same('call_join_here', $got['seen']['in_it']['label'], 'as Join here');
	pg_assert_true($got['room_one'], 'a guest room with one of the team carries the call buttons on the staff\'s side');
	pg_assert_false($got['room_none'], 'an empty one does not');
	pg_assert_true($got['room_group'], 'a guest room\'s call is a group\'s: nobody is rung, one waits alone');
	pg_assert_false($got['two_group'], 'a conversation of two stays a call of two');
}

function test_workspace_calls_guest_page_wiring()
{
	$page = workspace_calls_guest_test_source('workspace_guest.php');
	$script = workspace_calls_guest_test_source('assets/js/workspace_guest.js');
	$guests = workspace_calls_guest_test_source('includes/workspace/guests.php');

	pg_assert_contains('<div class="pg-guest-call d-none" id="pg-guest-call" role="status">', $page, 'the bar under the head is drawn hidden');
	pg_assert_true(strpos($page, 'id="pg-guest-call"') < strpos($page, 'id="pg-guest-log"'), 'between the head and the conversation');
	pg_assert_contains("\$calls = function_exists('ws_calls_on') && ws_calls_on();", $page, 'the page carries calls only while they are on');
	pg_assert_contains("'calls'    => \$calls ? array('ice' => ws_call_ice_servers(), 'max' => WS_CALL_MAX_MEMBERS) : null,", $page, 'STUN only in the page; the relay comes with a granted join');

	preg_match('/var CALL_ACTIONS = \{([^}]*)\}/', $script, $map);
	pg_assert_true(!empty($map[1]), 'the guest page names the call actions it maps');
	pg_assert_same(4, substr_count($map[1], ':'), 'four: join, signal, sync, leave');
	pg_assert_false(strpos($map[1], 'start') !== false, 'never a start: a guest never starts a call');
	pg_assert_contains('Calls.joinButton(', $script, 'the bar holds the module\'s Join button');
	pg_assert_false(strpos($script, 'headButtons') !== false, 'never the head\'s start buttons');
	pg_assert_contains('if (!CONF.calls || !Calls || data.read_only || !call.bar)', $script, 'no bar for a guest who only reads, or while calls are off');

	pg_assert_contains("case 'call_sync':", $guests, 'a guest in the call reads it every second');
	pg_assert_false(strpos($guests, "case 'call_start'") !== false, 'the guest page has no start');

	$strings = ws_guest_call_strings();
	pg_assert_same(lang('A call is on in this conversation.'), $strings['call_on'], 'the bar\'s words');
	pg_assert_same(lang('A video call is on in this conversation.'), $strings['call_on_video'], 'and for a video call');

	foreach (array_keys(ws_call_js_strings()) as $key) {
		pg_assert_true(isset($strings[$key]), 'the call window\'s text ' . $key . ' reaches the guest page');
	}

	$bundles = pg_js_bundles();
	$parts = $bundles['assets/js/workspace_guest.min.js'];
	pg_assert_same(array('assets/js/workspace_calls.js', 'assets/js/workspace_guest.js'), $parts, 'the guest bundle carries the call module before the page');
}

function test_workspace_calls_guest_room_rings_nobody()
{
	$calls = workspace_calls_guest_test_source('includes/workspace/calls.php');

	pg_assert_contains("INNER JOIN ws_channels ch ON ch.id = c.channel_id AND ch.kind <> 'guest'", $calls, 'a guest room\'s call rings nobody of the team');
	pg_assert_contains("if (ws_channel_is_guest(ws_channel((int) \$call['channel_id'])) || !ws_call_may_decline(", $calls, 'and is never declined, which would end it with the guest in it');
}

function test_workspace_calls_guest_window_name()
{
	$module = workspace_calls_guest_test_source('assets/js/workspace_calls.js');

	pg_assert_contains("bar.appendChild(el('span', 'ws-call-name', mine.name));", $module, 'without a way to open a channel the window\'s name is the name only');
}
