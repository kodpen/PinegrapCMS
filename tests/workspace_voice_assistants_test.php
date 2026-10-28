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

// A bell row or banner: words as they are for any other message, a
// recording's transcript after its words once written out, the label before.
function test_ws_voice_excerpt_text()
{
	pg_assert_same('Hello', ws_voice_excerpt_text('Hello', false, '', 140, 'Voice message'), 'not a recording');
	pg_assert_same('Voice message', ws_voice_excerpt_text('', true, '', 140, 'Voice message'), 'a recording not written out yet');
	pg_assert_same('@Ayşe', ws_voice_excerpt_text(' @Ayşe ', true, '', 140, 'Voice message'), 'its words before the transcript');
	pg_assert_same('Voice message: Ship it on Friday.', ws_voice_excerpt_text('', true, 'Ship it on Friday.', 140, 'Voice message'), 'the transcript');
	pg_assert_same('@Ayşe · Voice message: Ship it', ws_voice_excerpt_text('@Ayşe', true, 'Ship it', 140, 'Voice message'), 'words, then the transcript');
	pg_assert_same('Voice message: abc…', ws_voice_excerpt_text('', true, 'abcdefgh', 19, 'Voice message'), 'cut at the length');
}

// The search answer says it was the transcript that matched.
function test_ws_voice_found_in_transcript()
{
	pg_assert_same('Ship it on Friday', ws_voice_found_in_transcript('', 'Ship it on Friday', 'friday'), 'found in the transcript alone');
	pg_assert_same('', ws_voice_found_in_transcript('Friday it is', 'Ship it on Friday', 'friday'), 'the words matched as well');
	pg_assert_same('', ws_voice_found_in_transcript('', 'Ship it on Friday', 'monday'), 'not in the transcript');
	pg_assert_same('', ws_voice_found_in_transcript('', '', 'friday'), 'no transcript');
	pg_assert_same('', ws_voice_found_in_transcript('', 'Ship it', '  '), 'no search words');
}

// Bells, banners and both searches carry the transcript.
function test_ws_voice_push_and_search_are_wired()
{
	$read = function ($path) {
		return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $path);
	};

	pg_assert_same(2, substr_count($read('includes/workspace/notify.php'), 'ws_voice_excerpt($viewer, $message, 140)'), 'the mention and the read-receipt reminder');
	pg_assert_contains("(body LIKE '%\" . \$like . \"%'\" . ws_voice_search_or(\$like) . \")\"", $read('includes/workspace/messages.php'), 'the message search');
	pg_assert_contains("body LIKE '%\" . \$like . \"%'\" . ws_voice_search_or(\$like) . \")\"", $read('includes/workspace/palette.php'), 'the files group');
	pg_assert_contains('ws_voice_found_in_transcript($text, $heard[(int) $row[\'file_id\']], $query)', $read('includes/workspace/palette.php'), 'the answer shows the transcript');
}

// What an assistant reads of a message: the transcript once written out, a
// note for a recording without one, the words alone for any other message.
function test_ws_voice_heard()
{
	pg_assert_same('@ai [voice message, machine transcript: Ship it]', ws_voice_heard('@ai', 'Ship it', 'voice-1.weba'), 'written out');
	pg_assert_same('@ai [voice message, not transcribed]', ws_voice_heard('@ai', '', 'voice-1.weba'), 'not written out');
	pg_assert_same('[voice message, not transcribed]', ws_voice_heard('', ' ', 'voice-1.ogg'), 'no words, an empty transcript');
	pg_assert_same('@ai look', ws_voice_heard('@ai look', '', 'report.pdf'), 'another file');
	pg_assert_same('@ai look', ws_voice_heard('@ai look', '', ''), 'no file');
}

// A recording that will be written out waits for its transcript before it
// asks; any other file never asks. Neither needs the database.
function test_ws_voice_posted_waits()
{
	$none = array('claude' => false, 'agent' => false, 'ai' => false);
	$viewer = array('id' => 4);
	$channel = array('id' => 9, 'kind' => 'public');

	pg_assert_same($none, ws_voice_posted($viewer, $channel, 12, '@ai', 'voice-1.weba', true), 'waits for its transcript');
	pg_assert_same($none, ws_voice_posted($viewer, $channel, 12, '@ai', 'report.pdf', false), 'another file asks nobody');
}

// The asks once written out or given up, the uploads, and the recorder that
// keeps the microphone beside tags.
function test_ws_voice_asks_are_wired()
{
	$read = function ($path) {
		return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $path);
	};

	$voice = $read('includes/workspace/voice.php');
	pg_assert_contains("ws_voice_announce(\$message, \$channel, \$row, \$outcome, \$config['provider'], \$model);\n            ws_voice_ask((int) \$row['message_id']);", $voice, 'once written out');
	pg_assert_contains("WHERE id = '\" . (int) \$row['id'] . \"'\");\n\n    // Asked without a transcript: the assistant is told it was not heard.\n    ws_voice_ask((int) \$row['message_id']);", $voice, 'skipped or failed');
	pg_assert_contains('ws_voice_ask((int) $message_id);', $voice, 'given up by the watchdog');
	pg_assert_true(strpos($voice, 'ws_voice_watchdog();') < strpos($voice, "if (!ws_voice_config()['enabled']) {\n        return 'idle';"), 'the watchdog runs while transcripts are off');

	pg_assert_contains("ws_voice_posted(\$viewer, \$channel, \$result['message_id'], \$body, \$stored['name'], \$queued)", $read('includes/workspace/files.php'), 'the panel upload');
	pg_assert_contains("ws_api_assistants_carry_on(ws_voice_posted(\$viewer, \$channel, \$result['message_id'], (string) (\$params['text'] ?? ''), \$stored['name'], \$queued));", $read('includes/workspace/api_resources.php'), 'files.create');
	pg_assert_contains("return ws_voice_heard(\$text, ws_voice_transcript_of(\$row), (string) (\$row['file_name'] ?? ''), \$limit);", $read('includes/workspace/ai.php'), 'Pinegrap AI reads the note');

	$js = $read('assets/js/workspace.js');
	pg_assert_contains('(this.inputEmpty() || (this.voiceTags() !== \'\'))', $js, 'the microphone beside tags alone');
	pg_assert_contains('body: tags,', $js, 'the tags go with the recording');
	pg_assert_contains("voiceAnswers: function (message) {\n            var sender = (message && message.sender) || {};\n\n            return !sender.mcp;", $js, 'a recording answers an assistant');
}
