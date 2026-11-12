<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a live page's data bridge: the site's check of a call
 * (includes/workspace/page_bridge.php), the shell's part
 * (pg_guarded_frame_bridge_script()) run under Node against stand-in
 * windows, and what the page card keeps to. What a browser enforces is
 * proven by tests/js/guarded_frame_proofs.js.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/page_bridge.php');

function test_workspace_page_bridge_request_check()
{
	$ok = ws_page_bridge_request('who', '{}');
	pg_assert_true($ok['ok'], 'who with no arguments');
	pg_assert_same(array(), $ok['args']);
	pg_assert_true(ws_page_bridge_request('who', '')['ok'], 'no arguments at all are an empty object');
	pg_assert_true(ws_page_bridge_request('who', ' {"a":1}')['ok']);

	foreach (array(null, '', 'Who', '1who', 'who-am-i', str_repeat('a', 25), array('who')) as $call) {
		pg_assert_same('invalid', ws_page_bridge_request($call, '{}')['error']['code'], var_export($call, true) . ' is no call name');
	}

	foreach (array('[]', '[1]', '"x"', '1', 'null', '{', '{"a":}') as $args) {
		pg_assert_same('invalid', ws_page_bridge_request('who', $args)['error']['code'], $args . ' is no object');
	}

	$large = '{"a":"' . str_repeat('x', PG_GUARDED_FRAME_BRIDGE_MAX_ARGS) . '"}';
	pg_assert_same('limit', ws_page_bridge_request('who', $large)['error']['code'], 'arguments over the bound');
	pg_assert_same('unknown_call', ws_page_bridge_request('rows', '{}')['error']['code'], 'a call not built yet');
	pg_assert_same(array('who'), ws_page_bridge_calls());
}

function test_workspace_page_bridge_answer_refuses_what_is_no_page()
{
	$viewer = array('id' => 5, 'member' => false);
	$answer = ws_page_bridge_answer($viewer, null, 'who', '{}');
	pg_assert_same('not_found', $answer['error']['code'], 'no message');

	$answer = ws_page_bridge_answer($viewer, array('id' => 1, 'deleted_at' => 3, 'file_id' => 2, 'channel_id' => 1), 'who', '{}');
	pg_assert_same('not_found', $answer['error']['code'], 'a deleted message');

	$answer = ws_page_bridge_answer($viewer, array('id' => 1, 'deleted_at' => 0, 'file_id' => 0, 'channel_id' => 1), 'who', '{}');
	pg_assert_same('not_found', $answer['error']['code'], 'a message with no file');
}

function test_workspace_page_bridge_shell_only_in_script_mode()
{
	pg_assert_true(strpos(pg_guarded_frame_shell_script('script'), 'canvas-data') !== false, 'a live page has the bridge');
	pg_assert_false(strpos(pg_guarded_frame_shell_script('static'), 'canvas-data') !== false, 'a static document has none');
	pg_assert_false(strpos(pg_guarded_frame_shell_script('mail'), 'canvas-data') !== false, 'a mail has none');
	pg_assert_same(120, PG_GUARDED_FRAME_BRIDGE_PER_MINUTE);
	pg_assert_same(8, PG_GUARDED_FRAME_BRIDGE_PENDING);
}

/**
 * The shell's script run under Node with stand-in windows: the page (the
 * document frame's window), the parent and a stranger. Returns what each
 * received, or null when Node is not there.
 */
function ws_test_page_bridge_node()
{
	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);

	if ($code !== 0) {
		return null;
	}

	$file = @tempnam(sys_get_temp_dir(), 'pgbridge');

	if ($file === false) {
		return null;
	}

	$harness = <<<'JS'
var clock = 1000, handler = null;
function win(name) { return { name: name, got: [], postMessage: function (m) { this.got.push(JSON.parse(JSON.stringify(m))); } }; }
var page = win('page'), parentWin = win('parent'), stranger = win('stranger');
var frame = { contentWindow: page, removed: false, remove: function () { this.removed = true; this.contentWindow = null; }, addEventListener: function () {} };
global.Date = { now: function () { return clock; } };
global.document = { getElementById: function () { return frame; } };
global.window = { parent: parentWin, addEventListener: function (type, fn) { if (type === 'message') { handler = fn; } } };
SHELL
function send(source, data) { handler({ source: source, data: data }); }
var out = {};
send(page, { pg: 'canvas-data', id: 'a', call: 'who', args: {}, canvas: 99, message_id: 99 });
out.passed = parentWin.got.slice();
send(stranger, { pg: 'canvas-data', id: 'b', call: 'who' });
send(parentWin, { pg: 'canvas-data', id: 'nope', ok: true, data: 1 });
send(stranger, { pg: 'canvas-data', id: 'a', ok: true, data: 'forged' });
out.dropped = [parentWin.got.length, page.got.length];
send(parentWin, { pg: 'canvas-data', id: 'a', ok: true, data: { name: 'Ayşe' }, extra: 1 });
send(parentWin, { pg: 'canvas-data', id: 'a', ok: true, data: { name: 'twice' } });
out.answered = page.got.slice();
page.got = [];
send(page, { pg: 'canvas-data', id: 'c', call: 'Who!' });
send(page, { pg: 'canvas-data', id: 'd', call: 'who', args: [1] });
send(page, { pg: 'canvas-data', id: {}, call: 'who' });
send(page, { pg: 'canvas-data', id: 'e', call: 'who', args: { a: new Array(20000).join('x') } });
for (var i = 0; i < 9; i++) { send(page, { pg: 'canvas-data', id: 'w' + i, call: 'who' }); }
out.refused = page.got.map(function (m) { return m.id + ':' + m.error.code; });
send(parentWin, { pg: 'canvas-data', id: 'w0', ok: false, error: { code: 'forbidden', text: 'No.' } });
out.error = page.got[page.got.length - 1];
for (var j = 0; j < 130; j++) { send(page, { pg: 'canvas-data', id: 'f' + j, call: 'who' }); }
out.stopped = { removed: frame.removed, last: parentWin.got[parentWin.got.length - 1] };
console.log(JSON.stringify(out));
JS;

	file_put_contents($file, str_replace('SHELL', pg_guarded_frame_shell_script('script'), $harness));
	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	pg_assert_same(0, $code, implode("\n", $out));

	return ($code === 0) ? json_decode(implode("\n", $out), true) : null;
}

function test_workspace_page_bridge_shell_under_node()
{
	$r = ws_test_page_bridge_node();

	if ($r === null) {
		return;
	}

	pg_assert_same(array(array('pg' => 'canvas-data', 'id' => 'a', 'call' => 'who', 'args' => array())), $r['passed'],
		'a request passes with no canvas the page named');
	pg_assert_same(array(1, 0), $r['dropped'], 'a stranger\'s request, an answer to nothing and a forged answer reach no one');
	pg_assert_same(array(array('pg' => 'canvas-data', 'id' => 'a', 'ok' => true, 'data' => array('name' => 'Ayşe'))), $r['answered'],
		'the answer reaches the page once, as the shell writes it');
	pg_assert_same(array('c:invalid', 'd:invalid', 'e:limit', 'w8:limit'), $r['refused'],
		'bad call, list arguments, too large, too many waiting; no id: no answer');
	pg_assert_same(array('pg' => 'canvas-data', 'id' => 'w0', 'ok' => false, 'error' => array('code' => 'forbidden', 'text' => 'No.')), $r['error']);
	pg_assert_true($r['stopped']['removed'], 'past the minute\'s bound the page is removed');
	pg_assert_same(array('pg' => 'canvas-data', 'stopped' => 'limit'), $r['stopped']['last'], 'and the panel is told');
}

function test_workspace_page_bridge_card_script()
{
	$js = file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace_page_card.js');

	pg_assert_true(strpos($js, "message_id: entry.message.id") !== false, 'the canvas is the card\'s, never the page\'s');
	pg_assert_true(strpos($js, "h.api('ws_page_call'") !== false);

	$strings = ws_page_bridge_js_strings();
	pg_assert_true(isset($strings['page_stopped'], $strings['page_open_again']));
}
