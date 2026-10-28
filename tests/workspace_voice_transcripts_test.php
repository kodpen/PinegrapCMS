<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the connection of voice message transcripts
 * (includes/workspace/voice.php) that need no database: the service's
 * address as it is entered and checked, the model and key shapes, the name
 * the consent line gives the service, where recordings may be sent, and the
 * schema step that brings the table and the channel column in.
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

// A pasted endpoint becomes its base; a slash at the end goes.
function test_ws_voice_url_clean()
{
	pg_assert_same('', ws_voice_url_clean('  '), 'empty stays empty');
	pg_assert_same('https://api.openai.com/v1', ws_voice_url_clean(' https://api.openai.com/v1/ '), 'spaces and the last slash');
	pg_assert_same('https://api.openai.com/v1', ws_voice_url_clean('https://api.openai.com/v1/audio/transcriptions'), 'the whole endpoint pasted');
	pg_assert_same('https://api.groq.com/openai/v1', ws_voice_url_clean('https://api.groq.com/openai/v1/Audio/Transcriptions/'), 'any case, a slash after it');
}

// Only HTTPS, with a host, and nothing that would carry the key or the call
// elsewhere: no name and password, no query, no fragment.
function test_ws_voice_url_valid()
{
	pg_assert_true(ws_voice_url_valid('https://api.openai.com/v1'), 'OpenAI');
	pg_assert_true(ws_voice_url_valid('https://example.openai.azure.com/openai/deployments/whisper'), 'a path');
	pg_assert_true(ws_voice_url_valid('https://stt.example.com:8443/v1'), 'a port');

	foreach (array('', 'http://api.openai.com/v1', 'ftp://api.openai.com', 'https:///v1', 'https://user:pass@api.openai.com/v1',
		'https://api.openai.com/v1?key=1', 'https://api.openai.com/v1#x', 'https://api.openai.com/v 1', 'api.openai.com/v1',
		'https://' . str_repeat('a', 260) . '.com') as $url) {
		pg_assert_false(ws_voice_url_valid($url), 'refused: ' . substr($url, 0, 60));
	}
}

function test_ws_voice_model_and_key_shapes()
{
	foreach (array('whisper-1', 'gpt-4o-mini-transcribe', 'whisper-large-v3', 'openai/whisper-1', 'model:v2.1') as $model) {
		pg_assert_true(ws_voice_model_valid($model), 'model ' . $model);
	}

	foreach (array('', '-x', 'a b', 'a"b', str_repeat('a', 101)) as $model) {
		pg_assert_false(ws_voice_model_valid($model), 'model refused: ' . $model);
	}

	pg_assert_true(ws_voice_key_shape_ok('sk-proj-' . str_repeat('A', 40)), 'an OpenAI key');
	pg_assert_false(ws_voice_key_shape_ok('short'), 'too short');
	pg_assert_false(ws_voice_key_shape_ok('sk-abc def ghi'), 'a space');
	pg_assert_false(ws_voice_key_shape_ok(str_repeat('a', 513)), 'too long');
}

// The consent line names the company for the services people know and the
// host for any other; no address is OpenAI.
function test_ws_voice_provider_name()
{
	pg_assert_same('OpenAI', ws_voice_provider_name(''), 'the default');
	pg_assert_same('OpenAI', ws_voice_provider_name('https://api.openai.com/v1'), 'OpenAI');
	pg_assert_same('Azure OpenAI', ws_voice_provider_name('https://acme.openai.azure.com/openai'), 'Azure');
	pg_assert_same('Groq', ws_voice_provider_name('https://api.groq.com/openai/v1'), 'Groq');
	pg_assert_same('stt.example.com', ws_voice_provider_name('https://STT.example.com/v1'), 'any other host');
	pg_assert_same('evilopenai.com.example.net', ws_voice_provider_name('https://evilopenai.com.example.net/v1'), 'a look-alike is its host');
}

// Public channels yes, private ones only when a manager allowed it, a
// manager's no wins, never a guest's room.
function test_ws_voice_channel_rule()
{
	pg_assert_true(ws_voice_channel_rule('public', 0), 'public');
	pg_assert_false(ws_voice_channel_rule('private', 0), 'private');
	pg_assert_true(ws_voice_channel_rule('private', 1), 'private, allowed');
	pg_assert_false(ws_voice_channel_rule('public', 2), 'public, closed');
	pg_assert_false(ws_voice_channel_rule('guest', 1), 'a guest\'s room');
	pg_assert_false(ws_voice_channel_rule('direct', 0), 'a direct conversation');
}

// The step makes the table, the channel column in the ai_access shape and
// two short config columns; the texts go to config_text, never config.
function test_ws_voice_schema_step()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/migrations/2026.11.0.php');

	pg_assert_contains('upgrade_2026_11_0_workspace_voice();', $source, 'the step is called');

	$from = strpos($source, 'function upgrade_2026_11_0_workspace_voice()');
	pg_assert_true($from !== false, 'the step is there');

	$step = substr($source, $from, strpos($source, "\n}\n", $from) - $from);

	pg_assert_contains("install_create_table('ws_voice'", $step, 'the table');
	pg_assert_contains("install_add_column('ws_channels', 'voice_access', \"TINYINT UNSIGNED NOT NULL DEFAULT 0\")", $step, 'the channel column');
	pg_assert_false((bool) preg_match("/install_add_column\\('config', '[a-z_]+', \"(VARCHAR|TEXT)/", $step), 'no long column on config');
	pg_assert_contains("'ws_voice',", (string) file_get_contents(PG_FUNCTIONS_DIR . '/install/index.php'), 'the table is in get_tables()');
}
