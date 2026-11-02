<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the speech service of voice messages
 * (includes/workspace/voice.php) that need no database: the lists a model
 * and a voice are chosen from, the text an answer is spoken from (cut at a
 * sentence end), the request a service gets, what its answer means, and the
 * name a spoken answer is kept under.
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

// The lists the card offers, with their defaults in them.
function test_ws_voice_speech_lists()
{
	pg_assert_true(in_array(ws_voice_speech_default_model(), ws_voice_speech_models(), true), 'the default model is offered');
	pg_assert_true(in_array(ws_voice_speech_default_voice(), ws_voice_speech_voices(), true), 'the default voice is offered');

	foreach (array_merge(ws_voice_speech_models(), ws_voice_speech_voices()) as $name) {
		pg_assert_true(ws_voice_model_valid($name), 'a name the card offers passes the shape: ' . $name);
	}

	pg_assert_true(ws_voice_speech_voice_valid('alloy'), 'a voice');
	pg_assert_true(ws_voice_speech_voice_valid('Fritz-PlayAI'), 'another service\'s voice');
	pg_assert_false(ws_voice_speech_voice_valid(''), 'no voice');
	pg_assert_false(ws_voice_speech_voice_valid('a voice'), 'a space');
}

// An answer longer than the limit is cut where a sentence ends; a short one
// stays as it is, white space round it gone.
function test_ws_voice_speech_cut()
{
	pg_assert_same('Merhaba. Yarın görüşelim.', ws_voice_speech_cut("  Merhaba. Yarın görüşelim.\n"), 'short, trimmed');
	pg_assert_same('', ws_voice_speech_cut("  \n "), 'nothing to say');

	$sentence = 'Bu bir cümledir. ';
	$long = str_repeat($sentence, 100);
	$cut = ws_voice_speech_cut($long);

	pg_assert_true(mb_strlen($cut) <= WS_VOICE_SPEECH_MAX_CHARS, 'within the limit');
	pg_assert_same('.', mb_substr($cut, -1), 'ends at a sentence end');
	pg_assert_same(mb_strlen($sentence) - 1, mb_strlen($cut) % mb_strlen($sentence), 'whole sentences only');
	pg_assert_same($cut, mb_substr($long, 0, mb_strlen($cut)), 'the text as it was');

	pg_assert_same('First one! Second one?', ws_voice_speech_cut('First one! Second one? Third one that goes over', 22), 'a ! or ? ends a sentence');
	pg_assert_same("First line\nSecond line", ws_voice_speech_cut("First line\nSecond line\nThird line", 23), 'a line end ends a sentence');
	pg_assert_same('no sentence end at', ws_voice_speech_cut('no sentence end at all in this text', 20), 'a word end when no sentence ends');
	pg_assert_same('abcdefghij', ws_voice_speech_cut('abcdefghijklmnop', 10), 'the limit itself when nothing else');
	pg_assert_same('Merhaba dünya.', ws_voice_speech_cut('Merhaba dünya. Yarın görüşürüz.', 16), 'multibyte letters counted as characters');
}

// What a service is sent: the speech endpoint under the base, a JSON body
// with the model, the voice, the text and the format, the key in a header
// the caller adds. Only a model that takes instructions gets the language.
function test_ws_voice_speech_request()
{
	$request = ws_voice_speech_request('Merhaba.', 'tr', 'https://api.openai.com/v1', 'gpt-4o-mini-tts', 'coral');

	pg_assert_same('https://api.openai.com/v1/audio/speech', $request['url'], 'the endpoint');
	pg_assert_same('mp3', $request['format'], 'the sound format');

	$body = json_decode($request['body'], true);

	pg_assert_same('gpt-4o-mini-tts', $body['model'], 'the model');
	pg_assert_same('coral', $body['voice'], 'the voice');
	pg_assert_same('Merhaba.', $body['input'], 'the text');
	pg_assert_same('mp3', $body['response_format'], 'the format');
	pg_assert_contains('Turkish', (string) $body['instructions'], 'the language, for a model that takes instructions');
	pg_assert_true(in_array('Content-Type: application/json', $request['headers'], true), 'JSON');
	pg_assert_true(in_array('Accept: audio/mpeg', $request['headers'], true), 'a sound back');
	pg_assert_false((bool) preg_grep('/^Authorization:/', $request['headers']), 'no key in the pure part');

	$plain = json_decode(ws_voice_speech_request('Hello.', 'en', 'https://api.openai.com/v1', 'tts-1', 'alloy')['body'], true);
	pg_assert_false(array_key_exists('instructions', $plain), 'tts-1 takes no instructions');

	$unknown = json_decode(ws_voice_speech_request('Hello.', 'xx', 'https://api.openai.com/v1', 'gpt-4o-mini-tts', 'alloy')['body'], true);
	pg_assert_false(array_key_exists('instructions', $unknown), 'a language without a name asks nothing');

	pg_assert_same('https://tts.example.com/v1/audio/speech', ws_voice_speech_request('x', 'en', 'https://tts.example.com/v1/', 'm', 'v')['url'], 'a slash at the base\'s end');
}

// The language a request names and the row keeps: the code's language name
// where one is known, the code itself kept short.
function test_ws_voice_speech_language()
{
	pg_assert_same('Turkish', ws_voice_speech_language_name('tr'), 'tr');
	pg_assert_same('Turkish', ws_voice_speech_language_name('tr_TR'), 'a locale');
	pg_assert_same('English', ws_voice_speech_language_name('en-US'), 'a tag');
	pg_assert_same('', ws_voice_speech_language_name('xx'), 'unknown');
	pg_assert_same('', ws_voice_speech_language_name(''), 'none');
	pg_assert_same('tr', ws_voice_speech_language_code('tr_TR'), 'the code');
	pg_assert_same('', ws_voice_speech_language_code('1'), 'not a code');
}

// The service's answer: a sound when 200 with bytes that are not JSON, the
// service's own words otherwise, and whether the key or account refused.
function test_ws_voice_speech_answer()
{
	$ok = ws_voice_speech_answer(200, "\xFF\xFBbytes", 'audio/mpeg');
	pg_assert_true($ok['ok'], '200 with bytes');
	pg_assert_same('', $ok['error'], 'no error');
	pg_assert_false($ok['refused'], 'not refused');

	pg_assert_false(ws_voice_speech_answer(200, '', 'audio/mpeg')['ok'], '200 with nothing');
	pg_assert_false(ws_voice_speech_answer(200, '{"error":{"message":"x"}}', 'application/json')['ok'], '200 with JSON is no sound');

	$refused = ws_voice_speech_answer(401, '{"error":{"message":"Incorrect API key"}}', 'application/json');
	pg_assert_false($refused['ok'], 'refused');
	pg_assert_true($refused['refused'], 'the key');
	pg_assert_contains('Incorrect API key', $refused['error'], 'its own words');

	pg_assert_contains('HTTP 503', ws_voice_speech_answer(503, '', '')['error'], 'the status when it says nothing');
	pg_assert_contains('timeout', ws_voice_speech_answer(0, '', '', 'timeout')['error'], 'the client\'s error when no answer came');
	pg_assert_false(ws_voice_speech_answer(429, '', '')['refused'], 'a limit is not the key');
}

// A spoken answer is kept under the recorder's name shape, so that it is
// drawn, searched, quoted and deleted like any voice message.
function test_ws_voice_speech_file_name()
{
	$name = ws_voice_speech_file_name('mp3', 1793000000);

	pg_assert_true(ws_voice_is_recording($name), 'a recording by name: ' . $name);
	pg_assert_same('voice-20261026-073320.mp3', $name, 'the time in the name');
	pg_assert_same('audio/mpeg', ws_file_type($name)['mime'], 'served as a sound');
}

// The card, the settings and the keep are wired.
function test_ws_voice_speech_is_wired()
{
	$voice = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/voice.php');

	pg_assert_contains("pg_config_text_save('ws_voice_speech_url'", $voice, 'the address is a config_text row');
	pg_assert_contains("pg_config_text_save('ws_voice_speech_key'", $voice, 'the key is a config_text row');
	pg_assert_contains("pg_config_text_save('ws_voice_speech_model'", $voice, 'the model is a config_text row');
	pg_assert_contains("pg_config_text_save('ws_voice_speech_voice'", $voice, 'the voice is a config_text row');
	pg_assert_contains('id="ws_voice_speech_model"', $voice, 'the card has the model');
	pg_assert_contains('id="ws_voice_speech_voice"', $voice, 'the card has the voice');
	pg_assert_contains("INSERT INTO ws_voice", $voice, 'the keep writes the row');
	pg_assert_contains("'done'", $voice, 'as done');
	pg_assert_false((bool) preg_match('/install_add_column\\(/', $voice), 'no schema change');
}
