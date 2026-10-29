<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for "Convert to text" under a voice message (assets/js/pg_voice_text.js,
 * includes/fn/voice_text.php): the control shows only where the browser's
 * own model hears audio, writes the recording out piece by piece, keeps the
 * text in the page, and the Workspace player and the chat window carry it
 * with their texts.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/voice_text.php');

function voice_text_test_source($path)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $path);
}

// Runs the script under Node with a small stub page and the given stubs,
// then the given probe; answers the JSON it prints, or null without Node.
function voice_text_test_run($stubs, $probe)
{
	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);

	if ($code !== 0) {
		return null;
	}

	$page = 'function Node(tag) { this.tag = tag; this.children = []; this.hidden = false; this.textContent = ""; this.style = {}; this.attrs = {}; this.on = {}; }' . "\n"
		. 'Node.prototype.appendChild = function (c) { this.children.push(c); return c; };' . "\n"
		. 'Node.prototype.setAttribute = function (k, v) { this.attrs[k] = v; };' . "\n"
		. 'Node.prototype.addEventListener = function (k, f) { this.on[k] = f; };' . "\n"
		. 'global.document = { createElement: function (tag) { return new Node(tag); } };' . "\n"
		. 'global.window = global; var posted = [];' . "\n"
		. 'function settle() { return new Promise(function (r) { setTimeout(r, 20); }); }' . "\n";

	$file = tempnam(sys_get_temp_dir(), 'pgvtext') . '.js';
	file_put_contents($file, $page . $stubs . "\n" . voice_text_test_source('assets/js/pg_voice_text.js') . "\n" . $probe);
	$lines = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $lines, $code);
	@unlink($file);

	pg_assert_same(0, $code, implode("\n", $lines));

	return json_decode(implode("\n", $lines), true);
}

// The browser's model with audio input: availability per output language,
// a session whose copies answer one piece each; a recording of 70 seconds.
function voice_text_test_model($answers)
{
	return 'var asked = []; var created = []; var prompts = 0; var destroyed = 0;' . "\n"
		. 'window.LanguageModel = { availability: function (o) { asked.push(o.expectedOutputs[0].languages[0]); return Promise.resolve(' . $answers . '[o.expectedOutputs[0].languages[0]] || "unavailable"); },' . "\n"
		. ' create: function (o) { created.push(o); return Promise.resolve({ destroy: function () { destroyed++; }, clone: function () { return Promise.resolve({ destroy: function () {}, prompt: function (m) { prompts++; if (window.failAt === prompts) { return Promise.reject(new Error("x")); } return Promise.resolve(" piece " + prompts + " "); } }); } }); } };' . "\n"
		. 'window.fetch = function (url, o) { posted.push([url, o.method || "GET"]); return Promise.resolve({ ok: true, arrayBuffer: function () { return Promise.resolve(new ArrayBuffer(8)); } }); };' . "\n"
		. 'window.AudioContext = function () { this.close = function () {}; this.decodeAudioData = function (b, ok) { ok({ sampleRate: 100, length: 7000, getChannelData: function () { return new Float32Array(7000); } }); }; };' . "\n"
		. 'window.AudioBuffer = function (o) { this.length = o.length; this.copyToChannel = function () {}; };' . "\n"
		. 'window.AbortController = function () { this.signal = {}; this.abort = function () {}; };';
}

// A browser without the model shows nothing.
function test_voice_text_hidden_without_the_model()
{
	$result = voice_text_test_run('', 'var box = PGVoiceText.control("/f.weba", function (k) { return k; }, "tr");' . "\n"
		. 'settle().then(function () { console.log(JSON.stringify({ hidden: box.hidden })); });');

	if ($result !== null) {
		pg_assert_true($result['hidden'], 'no LanguageModel, no control');
	}

	$result = voice_text_test_run(voice_text_test_model('{}'), 'var box = PGVoiceText.control("/f.weba", function (k) { return k; }, "tr");' . "\n"
		. 'settle().then(function () { console.log(JSON.stringify({ hidden: box.hidden, asked: asked })); });');

	if ($result !== null) {
		pg_assert_true($result['hidden'], 'a model that hears no audio, no control');
		pg_assert_same(array('tr', 'en'), $result['asked'], 'the screen\'s language first, then English');
	}
}

// The recording is written out in 30-second pieces, the text joined under
// the player with its mark; the file is only read, nothing is sent.
function test_voice_text_writes_out_the_recording()
{
	$probe = 'var box = PGVoiceText.control("/f.weba", function (k) { return "[" + k + "]"; }, "tr");' . "\n"
		. 'settle().then(function () { var shown = !box.hidden; box.children[0].on.click(); return settle().then(function () {' . "\n"
		. ' console.log(JSON.stringify({ shown: shown, text: box.children[3].textContent, by: !box.children[4].hidden, button: box.children[0].hidden, status: box.children[1].hidden,'
		. ' prompts: prompts, posted: posted, output: created[0].expectedOutputs[0].languages[0], audio: created[0].expectedInputs[1].type, destroyed: destroyed })); }); });';
	$result = voice_text_test_run(voice_text_test_model('{ tr: "unavailable", en: "downloadable" }'), $probe);

	if ($result === null) {
		return;
	}

	pg_assert_true($result['shown'], 'shown where the model can download');
	pg_assert_same(3, $result['prompts'], '70 seconds in three pieces');
	pg_assert_same('piece 1 piece 2 piece 3', $result['text']);
	pg_assert_true($result['by'], 'marked as the browser\'s text');
	pg_assert_true($result['button'], 'the button is gone once written');
	pg_assert_true($result['status'], 'no status left');
	pg_assert_same(array(array('/f.weba', 'GET')), $result['posted'], 'the file is read once and nothing is sent');
	pg_assert_same('en', $result['output']);
	pg_assert_same('audio', $result['audio']);
	pg_assert_same(1, $result['destroyed'], 'the session is let go');
}

// A failed piece says so and offers the button again.
function test_voice_text_failure_offers_it_again()
{
	$probe = 'window.failAt = 2; var box = PGVoiceText.control("/f.weba", function (k) { return k; }, "en");' . "\n"
		. 'settle().then(function () { box.children[0].on.click(); return settle().then(function () {' . "\n"
		. ' console.log(JSON.stringify({ status: box.children[1].textContent, button: box.children[0].hidden, by: box.children[4].hidden, destroyed: destroyed })); }); });';
	$result = voice_text_test_run(voice_text_test_model('{ en: "available" }'), $probe);

	if ($result === null) {
		return;
	}

	pg_assert_same('voice_text_failed', $result['status']);
	pg_assert_false($result['button'], 'the button is back');
	pg_assert_true($result['by'], 'no mark without a text');
	pg_assert_same(1, $result['destroyed']);
}

// The Workspace player offers it only without a server transcript; the
// chat window draws it under its audio; both carry the texts and the file.
function test_voice_text_wiring()
{
	$voice = voice_text_test_source('assets/js/workspace_voice.js');
	$chat = voice_text_test_source('assets/js/chat_backend.src.js');
	$bundles = pg_js_bundles();

	pg_assert_contains("var under = voice ? transcript(voice, h) : null;\n\n        if (!under && window.PGVoiceText) {", $voice, 'a server transcript wins');
	pg_assert_contains('window.PGVoiceText.control(attachment.url', $chat);
	pg_assert_contains("\$output .= pg_js_bundle_tags('assets/js/pg_voice_text.min.js');\n\n    \$output .= pg_js_bundle_tags('assets/js/workspace.min.js')", voice_text_test_source('includes/workspace/screen.php'), 'loaded before the player');
	pg_assert_contains("return \$output . pg_js_bundle_tags('assets/js/pg_voice_text.min.js')\n            . ws_record_loader(", voice_text_test_source('includes/workspace/screen.php'), 'a record screen loads it too');
	pg_assert_same(array('assets/js/pg_voice_text.js'), $bundles['assets/js/pg_voice_text.min.js']);
	pg_assert_contains("pg_js_bundle_tags('assets/js/pg_voice_text.min.js')", voice_text_test_source('chat.php'));
	pg_assert_contains('+ pg_voice_text_strings()', voice_text_test_source('includes/workspace/voice.php'));

	foreach (array('voice_text_convert', 'voice_text_working', 'voice_text_download', 'voice_text_by', 'voice_text_failed') as $key) {
		pg_assert_true(array_key_exists($key, pg_voice_text_strings()), $key);
		pg_assert_contains("'" . $key . "'", voice_text_test_source('assets/js/pg_voice_text.js'), $key . ' is asked');
	}
}
