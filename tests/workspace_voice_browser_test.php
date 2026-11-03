<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a recording written out in the sender's browser before it is
 * posted (includes/workspace/voice.php, assets/js/pg_voice_text.js,
 * workspace.js): where the browser route applies, which text is kept as
 * the machine transcript, and the screen's wiring.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(__DIR__ . '/helpers/language_map.php');

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/voice.php');

function ws_voice_browser_test_source($path)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $path);
}

// The browser writes a recording out only where no service does (the
// site's transcripts off) and the channel lets its recordings be written
// out; never before the schema step.
function test_ws_voice_browser_rule()
{
	pg_assert_true(ws_voice_browser_rule(true, false, true), 'no service, an open channel');
	pg_assert_false(ws_voice_browser_rule(true, true, true), 'the site\'s service writes it out');
	pg_assert_false(ws_voice_browser_rule(true, false, false), 'the channel keeps its recordings');
	pg_assert_false(ws_voice_browser_rule(false, false, true), 'no schema step');
}

// The text kept: trimmed, control characters out, line breaks as spaces,
// cut at the service's own limit; nothing for an empty text or a file the
// recorder did not name.
function test_ws_voice_browser_transcript()
{
	pg_assert_same('Merhaba dünya', ws_voice_browser_transcript("  Merhaba\x00 \n dünya ", 'voice-20261103-101500.weba'));
	pg_assert_same('', ws_voice_browser_transcript('   ', 'voice-20261103-101500.weba'), 'nothing heard');
	pg_assert_same('', ws_voice_browser_transcript(array('x'), 'voice-20261103-101500.weba'), 'not a string');
	pg_assert_same('', ws_voice_browser_transcript('Hello', 'song.mp3'), 'a sound somebody attached');
	pg_assert_same(WS_VOICE_BROWSER_MAX_CHARS, mb_strlen(ws_voice_browser_transcript(str_repeat('ş', WS_VOICE_BROWSER_MAX_CHARS + 50), 'voice-1.ogg')), 'cut at the limit');
}

// pg_voice_text.js under Node: transcribe() writes a Blob out piece by
// piece with progress and fetches nothing; downloaded() is true only for a
// model already on the device. The stubs are the "Convert to text" tests'.
function test_ws_voice_browser_transcribe_and_downloaded()
{
	require_once(__DIR__ . '/voice_text_test.php');

	$blob = 'var blob = { arrayBuffer: function () { return Promise.resolve(new ArrayBuffer(8)); } }; var steps = [];';
	$probe = $blob . "\n"
		. 'PGVoiceText.transcribe(blob, "tr", function (s) { steps.push(s.done); }).then(function (text) { return PGVoiceText.downloaded("tr").then(function (here) {' . "\n"
		. ' console.log(JSON.stringify({ text: text, steps: steps, posted: posted, destroyed: destroyed, here: here })); }); });';
	$result = voice_text_test_run(voice_text_test_model('{ en: "available" }'), $probe);

	if ($result !== null) {
		pg_assert_same('piece 1 piece 2 piece 3', $result['text']);
		pg_assert_same(array(0, 1 / 3, 2 / 3, 1), $result['steps'], 'progress after each piece');
		pg_assert_same(array(), $result['posted'], 'a recording in the page is not fetched');
		pg_assert_same(1, $result['destroyed'], 'the session is let go');
		pg_assert_true($result['here'], 'the model is on the device');
	}

	$probe = 'PGVoiceText.downloaded("en").then(function (here) { console.log(JSON.stringify({ here: here, created: created.length })); });';
	$result = voice_text_test_run(voice_text_test_model('{ en: "downloadable" }'), $probe);

	if ($result !== null) {
		pg_assert_false($result['here'], 'a model still to download is not used for sending');
		pg_assert_same(0, $result['created'], 'asking downloads nothing');
	}

	$probe = 'PGVoiceText.transcribe({}, "en").then(function () { console.log("{\"failed\":false}"); }, function () { console.log("{\"failed\":true}"); });';
	$result = voice_text_test_run('', $probe);

	if ($result !== null) {
		pg_assert_true($result['failed'], 'no model, a refusal');
	}
}

// The screen sends the browser's text with the recording only where the
// channel says so; the upload hands it to ws_voice_queue(), which keeps it
// done with provider 'browser'; the player names the sender's browser.
function test_ws_voice_browser_wiring()
{
	$screen = ws_voice_browser_test_source('assets/js/workspace.js');
	$voice = ws_voice_browser_test_source('includes/workspace/voice.php');

	pg_assert_contains("return !!(this.channel && this.channel.voice_browser) && voiceModelHere();", $screen);
	pg_assert_contains("window.PGVoiceText.transcribe(file, CFG.locale || '', function (step) {", $screen);
	pg_assert_contains("voice_transcript: settings.transcript || ''", $screen);
	pg_assert_contains("'transcript'  => \$request['voice_transcript'] ?? '',", ws_voice_browser_test_source('includes/workspace/actions.php'));
	pg_assert_contains("\$heard = ws_voice_browser_rule(true, \$enabled, \$allowed) ? ws_voice_browser_transcript(\$measure['transcript'] ?? '', \$name) : '';", $voice);
	pg_assert_contains("'done', '\" . e(\$heard) . \"', 'browser'", $voice);
	pg_assert_contains("\$brief['voice_browser'] = function_exists('ws_voice_browser_channel') && ws_voice_browser_channel(\$channel);", ws_voice_browser_test_source('includes/workspace/channels.php'));
	pg_assert_contains("(voice.provider === 'browser') ? t('voice_machine_browser')", ws_voice_browser_test_source('assets/js/workspace_voice.js'));

	foreach (array('voice_browser_hint', 'voice_browser_working', 'voice_machine_browser') as $key) {
		pg_assert_true(array_key_exists($key, ws_voice_js_strings()), $key);
	}
}
