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
