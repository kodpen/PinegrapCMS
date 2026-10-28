<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for voice message transcripts as the assistants and recaps read
 * them (includes/workspace/voice.php) that need no database: how a
 * transcript is joined to the person's own words, what is taken out of it,
 * and the readers that carry it.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/voice.php');

// The transcript follows the person's words, marked as machine text; with
// none the words come back as they were.
function test_ws_voice_with_transcript()
{
	pg_assert_same('Look at this', ws_voice_with_transcript('Look at this', ''), 'no transcript');
	pg_assert_same('Look at this', ws_voice_with_transcript('Look at this', "  \n "), 'an empty transcript');
	pg_assert_same('[voice message, machine transcript: Ship it on Friday.]', ws_voice_with_transcript('', 'Ship it on Friday.'), 'a recording alone');
	pg_assert_same('@ai [voice message, machine transcript: Ship it on Friday.]', ws_voice_with_transcript('@ai', 'Ship it on Friday.'), 'typed words first');
	pg_assert_same('[voice message, machine transcript: abcde…]', ws_voice_with_transcript('', 'abcdefgh', 5), 'cut at the limit');
}

// One line, and no tag the speaking voice could not have chosen.
function test_ws_voice_transcript_clean()
{
	pg_assert_same('Call Ayşe tomorrow', ws_voice_transcript_clean("Call <@user:4> Ayşe\n\ttomorrow "), 'tags and line breaks out');
	pg_assert_same('Order today', ws_voice_transcript_clean('Order <#order:12> today'), 'record tags out');
	pg_assert_same('ğüşiöç…', ws_voice_transcript_clean('ğüşiöçİ', 6), 'cut by characters');
	pg_assert_same('a < b', ws_voice_transcript_clean('a < b'), 'other text kept');
}

// Rows without a recording ask nothing of the database.
function test_ws_voice_transcripts_without_files()
{
	pg_assert_same(array(), ws_voice_transcripts(array()), 'no rows');
	pg_assert_same(array(), ws_voice_transcripts(array(array('file_id' => 0), array('file_id' => 7, 'deleted_at' => 5))), 'no file, a deleted message');
	pg_assert_same('', ws_voice_transcript_of(null), 'no row');
	pg_assert_same('', ws_voice_transcript_of(array('file_id' => 0)), 'no file');
}

// Pinegrap AI, the MCP ask, Claude's reads and recaps carry the transcript.
function test_ws_voice_assistants_are_wired()
{
	$read = function ($path) {
		return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $path);
	};

	$ai = $read('includes/workspace/ai.php');
	pg_assert_contains('$heard = function_exists(\'ws_voice_transcripts\') ? ws_voice_transcripts($rows) : array();', $ai, 'the channel context');
	pg_assert_contains('$request_text = ws_ai_heard_text($asker, $message, 4000);', $ai, 'the request');
	pg_assert_contains('$request_text = ws_ai_heard_text($asker, $message, 4000);', $read('includes/workspace/mcp_ask.php'), 'the MCP ask');

	$claude = $read('includes/workspace/api_claude.php');
	pg_assert_contains("'transcript'          => (\$message && (\$body === (string) \$message['body'])) ? ws_api_voice_transcript(\$message) : '',", $claude, 'the request Claude claims');
	pg_assert_contains("'transcript'  => \$transcript(\$row),", $claude, 'the channel context messages');
	pg_assert_contains("'transcript' => 'string?'", $claude, 'declared in the schema');

	pg_assert_contains('$text = ws_voice_with_transcript($text, $heard[(int) $row[\'file_id\']], 400);', $read('includes/workspace/briefing.php'), 'recaps');
}
