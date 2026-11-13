<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The designer's Assistant panel (assets/js/designer_ai.js) when the site's
 * answer cannot be read: an answer that is not JSON (a PHP message, a
 * server's error page, an empty body), a JSON answer that says nothing, or no
 * answer at all. The panel says so once, in its own translated words, never
 * the browser's parse text, and an Apply that failed leaves the proposal
 * open to try again. The panel runs in Node against a small stand-in for the
 * editor and the page; skipped where Node cannot run.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The panel's words, as markers the results are read by.
function designer_ai_unreadable_test_text()
{
	return array(
		'apply'            => 'APPLY',
		'reapply'          => 'APPLY AGAIN',
		'applied'          => 'APPLIED',
		'failed'           => 'NOT DONE',
		'unreadable'       => 'UNREADABLE',
		'unreached'        => 'UNREACHED',
		'apply_unreadable' => 'APPLY UNREADABLE',
		'apply_failed'     => 'APPLY FAILED',
	);
}

// Runs the panel with the editor's post answering each sub-action through
// the JavaScript of `$answers` (sub-action => an expression giving a
// promise), opens it, presses Apply on proposal 7 when `$click` and returns
// what was shown; null when Node cannot run.
function designer_ai_unreadable_test_run($answers, $click)
{
	if (!function_exists('exec')) {
		return null;
	}

	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);

	if ($code !== 0) {
		return null;
	}

	$routes = '';

	foreach ($answers as $sub => $js) {
		$routes .= '    ' . json_encode($sub) . ': function () { return ' . $js . '; },' . "\n";
	}

	$script = '"use strict";' . "\n"
		. '// Interval timers would keep Node running; the panel needs none here.' . "\n"
		. 'var setInterval = function () { return 0; };' . "\n"
		. 'var clearInterval = function () {};' . "\n"
		. 'function node(tag) {' . "\n"
		. '    var parts = {};' . "\n"
		. '    var e = { tagName: tag, attrs: {}, children: [], listeners: {}, html: "", disabled: false, hidden: false, value: "", textContent: "",' . "\n"
		. '        classList: { add: function () {}, remove: function () {}, toggle: function () {}, contains: function () { return false; } },' . "\n"
		. '        setAttribute: function (n, v) { this.attrs[n] = String(v); },' . "\n"
		. '        getAttribute: function (n) { return this.attrs.hasOwnProperty(n) ? this.attrs[n] : null; },' . "\n"
		. '        addEventListener: function (type, fn) { (this.listeners[type] = this.listeners[type] || []).push(fn); },' . "\n"
		. '        appendChild: function (c) { this.children.push(c); return c; },' . "\n"
		. '        focus: function () {},' . "\n"
		. '        querySelector: function (s) { return parts[s] || (parts[s] = node(s)); } };' . "\n"
		. '    Object.defineProperty(e, "innerHTML", { get: function () { return this.html; }, set: function (v) { this.html = String(v); } });' . "\n"
		. '    return e;' . "\n"
		. '}' . "\n"
		. 'var document = { readyState: "complete", body: node("body"), createElement: node,' . "\n"
		. '    getElementById: function () { return null; }, querySelector: function () { return null; }, addEventListener: function () {} };' . "\n"
		. 'var shown = { toasts: [], calls: [], applied: 0, warned: 0 };' . "\n"
		. 'var routes = {' . "\n" . $routes . '};' . "\n"
		. 'var window = { sdDesign: { ai: { enabled: true, proposal: 0, text: ' . json_encode(designer_ai_unreadable_test_text()) . ' } },' . "\n"
		. '    console: { warn: function () { shown.warned++; } } };' . "\n"
		. 'var bridge = {' . "\n"
		. '    tree: function () { return {}; }, treeJSON: function () { return "{}"; }, selected: function () { return null; },' . "\n"
		. '    page: function () { return { key: "p0", page_id: 5, page_name: "Home" }; },' . "\n"
		. '    mayEdit: function () { return true; }, full: function () { return true; }, label: function () { return ""; }, select: function () {},' . "\n"
		. '    apply: function () { shown.applied++; },' . "\n"
		. '    post: function (sub) { shown.calls.push(sub); return routes[sub] ? routes[sub]() : Promise.resolve({ status: "success" }); },' . "\n"
		. '    toast: function (html, kind) { shown.toasts.push({ html: html, kind: kind }); }' . "\n"
		. '};' . "\n"
		. 'var StyleDesigner = { aiBridge: function () { return bridge; } };' . "\n"
		. file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/designer_ai.js') . "\n"
		. 'window.PgDesignerAI.open();' . "\n"
		. 'setTimeout(function () {' . "\n"
		. '    var list = document.body.children[0].querySelector(".sd-ai-list");' . "\n"
		. '    var button = { disabled: false, getAttribute: function (n) { return n === "data-act" ? "apply" : null; },' . "\n"
		. '        closest: function (s) { return s === "[data-proposal]" ? { getAttribute: function () { return "7"; } } : null; } };' . "\n"
		. '    if (' . ($click ? 'true' : 'false') . ') {' . "\n"
		. '        list.listeners.click[0]({ target: { closest: function () { return button; } } });' . "\n"
		. '    }' . "\n"
		. '    setTimeout(function () {' . "\n"
		. '        shown.list = list.innerHTML;' . "\n"
		. '        shown.disabled = button.disabled;' . "\n"
		. '        console.log(JSON.stringify(shown));' . "\n"
		. '        process.exit(0);' . "\n"
		. '    }, 40);' . "\n"
		. '}, 40);' . "\n";

	$file = @tempnam(sys_get_temp_dir(), 'pgdai');

	if ($file === false) {
		return null;
	}

	file_put_contents($file, $script);
	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	pg_assert_same(0, $code, 'the Node script runs: ' . implode("\n", array_slice($out, 0, 8)));

	return json_decode((string) end($out), true);
}

// The panel's state answer with one request and its pending proposal 7.
function designer_ai_unreadable_test_state()
{
	return 'Promise.resolve(' . json_encode(array(
		'status'   => 'success',
		'page_ok'  => true,
		'agents'   => array(array('agent' => 'claude', 'label' => 'Claude', 'ready' => true, 'problem' => '')),
		'requests' => array(array(
			'id'         => 3,
			'agent'      => 'claude',
			'status'     => 'answered',
			'waiting'    => false,
			'prompt'     => 'Make this section bolder.',
			'answer'     => 'A darker background and a bigger heading.',
			'created_at' => 0,
			'proposals'  => array(array('id' => 7, 'page_id' => 5, 'node_id' => '', 'status' => 'pending', 'ops_count' => 6)),
		)),
	)) . ')';
}

// The answer of #8970: PHP's warning printed in front of the JSON, so the
// editor's fetch fails with the browser's own parse text.
function designer_ai_unreadable_test_parse_error()
{
	return 'Promise.reject(new SyntaxError("Unexpected token \'<\', \"<br /> <b>\"... is not valid JSON"))';
}

function designer_ai_unreadable_test_one_toast($shown, $html, $label)
{
	pg_assert_same(1, count($shown['toasts']), $label . ': one message for one click');
	pg_assert_same(array('html' => $html, 'kind' => 'error'), $shown['toasts'][0], $label . ': the panel\'s own words');
	pg_assert_false(strpos(json_encode($shown), 'Unexpected token') !== false, $label . ': never the browser\'s parse text');
}

function designer_ai_unreadable_test_still_open($shown, $label)
{
	pg_assert_same(0, $shown['applied'], $label . ': nothing put on the canvas');
	pg_assert_true(strpos($shown['list'], 'data-act="apply"') !== false, $label . ': the proposal keeps its Apply button');
	pg_assert_false(strpos($shown['list'], 'APPLIED') !== false, $label . ': not drawn as applied');
	pg_assert_same(false, $shown['disabled'], $label . ': Apply can be pressed again');
}

// An Apply whose answer is not JSON says that the change could not be
// applied because the server answered with an error, once, and leaves the
// proposal open.
function test_designer_ai_apply_answer_not_json_says_so_once_and_keeps_the_proposal()
{
	$shown = designer_ai_unreadable_test_run(array(
		'ai_state' => designer_ai_unreadable_test_state(),
		'ai_apply' => designer_ai_unreadable_test_parse_error(),
	), true);

	if ($shown === null) {
		return;
	}

	pg_assert_same(array('ai_state', 'ai_apply'), $shown['calls'], 'one apply call');
	designer_ai_unreadable_test_one_toast($shown, 'APPLY UNREADABLE', 'not JSON');
	designer_ai_unreadable_test_still_open($shown, 'not JSON');
	pg_assert_same(1, $shown['warned'], 'the browser\'s text is kept in the console');
}

// A JSON answer without a status or a message, and a success whose tree is
// not JSON, are unreadable answers too.
function test_designer_ai_apply_answer_without_words_or_tree_is_unreadable()
{
	$cases = array(
		'an empty object' => 'Promise.resolve({})',
		'no answer body'  => 'Promise.resolve(null)',
		'a bare string'   => 'Promise.resolve("<br />")',
		'a tree not JSON' => 'Promise.resolve({ status: "success", tree: "<br /><b>Warning</b>" })',
	);

	foreach ($cases as $label => $answer) {
		$shown = designer_ai_unreadable_test_run(array(
			'ai_state' => designer_ai_unreadable_test_state(),
			'ai_apply' => $answer,
		), true);

		if ($shown === null) {
			return;
		}

		designer_ai_unreadable_test_one_toast($shown, 'APPLY UNREADABLE', $label);
		designer_ai_unreadable_test_still_open($shown, $label);
	}
}

// No answer at all (the site cannot be reached) is said in other words than
// a server error, still once and with the proposal kept.
function test_designer_ai_apply_without_an_answer_says_the_site_was_not_reached()
{
	$shown = designer_ai_unreadable_test_run(array(
		'ai_state' => designer_ai_unreadable_test_state(),
		'ai_apply' => 'Promise.reject(new TypeError("Failed to fetch"))',
	), true);

	if ($shown === null) {
		return;
	}

	designer_ai_unreadable_test_one_toast($shown, 'UNREACHED', 'no answer');
	designer_ai_unreadable_test_still_open($shown, 'no answer');
}

// The server's own sentence in a JSON error is shown as it is, as before.
function test_designer_ai_apply_json_error_keeps_the_servers_sentence()
{
	$shown = designer_ai_unreadable_test_run(array(
		'ai_state' => designer_ai_unreadable_test_state(),
		'ai_apply' => 'Promise.resolve({ status: "error", code: "server_error", message: "The server ran into an error, so this was not done." })',
	), true);

	if ($shown === null) {
		return;
	}

	designer_ai_unreadable_test_one_toast($shown, 'The server ran into an error, so this was not done.', 'JSON error');
	designer_ai_unreadable_test_still_open($shown, 'JSON error');
}

// The panel's first read failing the same way puts the panel's words in its
// problem line, not the parse text.
function test_designer_ai_state_answer_not_json_shows_the_panels_words()
{
	$shown = designer_ai_unreadable_test_run(array(
		'ai_state' => designer_ai_unreadable_test_parse_error(),
	), false);

	if ($shown === null) {
		return;
	}

	pg_assert_true(strpos($shown['list'], 'UNREADABLE') !== false, 'the problem line has the panel\'s words: ' . $shown['list']);
	pg_assert_false(strpos($shown['list'], 'Unexpected token') !== false, 'no parse text in the panel');
	pg_assert_same(array(), $shown['toasts'], 'the problem line is the one message');
}

// Every word the panel looks up is in the configuration the editor hands
// it, under lang(), so none shows as its bare key.
function test_designer_ai_panel_words_are_all_configured()
{
	preg_match_all('/\bt\(\'([a-z_]+)\'/', file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/designer_ai.js'), $used);
	$php = file_get_contents(PG_FUNCTIONS_DIR . '/includes/designer_ai.php');

	foreach (array_unique($used[1]) as $key) {
		pg_assert_true((bool) preg_match('/\'' . preg_quote($key, '/') . '\'\s*=>\s*lang\(/', $php), 'the panel\'s word "' . $key . '" is configured');
	}
}
