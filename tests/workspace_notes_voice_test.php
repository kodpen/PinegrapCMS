<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a recording made in a Workspace note (assets/js/workspace.js,
 * startNotes()): the recorder's files are taken as a note's file, the
 * recordings a note links to are found once each in their order, and the
 * microphone, the recorder's row and the players are wired to the note.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/files.php');

function ws_notes_voice_test_screen()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace.js');
}

// A recording is kept as a note's file (ws_note_file_store_bytes() refuses
// what a channel refuses) and drawn as a sound.
function test_ws_notes_voice_extensions_are_kept()
{
	foreach (array('weba', 'ogg', 'm4a') as $extension) {
		$name = 'voice-20261028-101500.' . $extension;

		pg_assert_false(ws_upload_refused($name), $name . ' is not refused');
		pg_assert_same('audio', ws_file_kind($name), $name . ' is a sound');
	}
}

// The links a note's text holds to recordings: voice- names only, each
// address once, in their order. Run under Node; skipped without it.
function test_ws_notes_voice_links()
{
	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);

	if ($code !== 0) {
		return;
	}

	$screen = ws_notes_voice_test_screen();
	$from = strpos($screen, '        function voiceLinks(');
	$to = ($from !== false) ? strpos($screen, "\n        // Under the text", $from) : false;

	pg_assert_true(($from !== false) && ($to !== false), 'voiceLinks() is found');

	$text = "Meeting\n[voice-20261028-101500.weba](https://example.com/voice-20261028-101500.weba) and "
		. "[report.pdf](https://example.com/report.pdf)\n[voice-20261028-101500.weba](https://example.com/voice-20261028-101500.weba)\n"
		. "[Voice-20261028-111500.ogg](http://example.com/files/voice-2.ogg) [voice-x](javascript:alert(1))";
	$file = tempnam(sys_get_temp_dir(), 'pgnv') . '.js';

	file_put_contents($file, 'global.window = { PGWsVoice: { isVoice: function (name) { return /^voice-/i.test(String(name || "")); } } };' . "\n"
		. substr($screen, $from, $to - $from) . "\n"
		. 'console.log(JSON.stringify(voiceLinks(' . json_encode($text) . ')));');

	$lines = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $lines, $code);
	@unlink($file);

	pg_assert_same(0, $code, implode("\n", $lines));
	pg_assert_same(array(
		array('name' => 'voice-20261028-101500.weba', 'url' => 'https://example.com/voice-20261028-101500.weba', 'kind' => 'audio'),
		array('name' => 'Voice-20261028-111500.ogg', 'url' => 'http://example.com/files/voice-2.ogg', 'kind' => 'audio'),
	), json_decode(implode("\n", $lines), true));
}

// The microphone records into the note's own row and the file goes through
// the note's attach(); the players follow the text in both views.
function test_ws_notes_voice_wiring()
{
	$screen = ws_notes_voice_test_screen();

	pg_assert_contains("files.push(tool('bi-mic', t('notes_voice'), recordVoice));", $screen);
	pg_assert_contains('send: function (file) { attach([file]); },', $screen);
	pg_assert_contains("voiceRow = el('div', 'ws-nb-voice d-none');", $screen);
	pg_assert_contains("drawVoices(current.body || '');", $screen);
	pg_assert_contains('drawVoices(field.value());', $screen);
	pg_assert_contains('voicesLater();', $screen);
	pg_assert_contains("'notes_voice'", (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/notes.php'));
	pg_assert_contains("'notes_voices'", (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/notes.php'));
}
