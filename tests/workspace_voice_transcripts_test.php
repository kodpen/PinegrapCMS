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
	pg_assert_same('acme.openai.azure.com', ws_voice_provider_name('https://acme.openai.azure.com/openai'), 'Azure is its host: its address needs a query the check refuses');
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

// The recorder's names are recordings; an attached sound file is not, and a
// .webm (drawn as a video) never is.
function test_ws_voice_is_recording()
{
	foreach (array('voice-20261028-121500.weba', 'voice-20261028-121500.ogg', 'voice-20261028-121500.M4A') as $name) {
		pg_assert_true(ws_voice_is_recording($name), $name);
	}

	foreach (array('', 'song.mp3', 'voice.weba', 'voice-20261028-121500.webm', 'voice-x.weba.php', 'my voice-1.ogg', 'voice-../x.ogg') as $name) {
		pg_assert_false(ws_voice_is_recording($name), 'not a recording: ' . $name);
	}
}

// The body the service reads: the fields, then the file under its name, the
// boundary closed; quotes and line breaks never reach a header.
function test_ws_voice_multipart()
{
	$body = ws_voice_multipart('B', array('model' => 'whisper-1', 'response_format' => 'json'), 'file', "voice-1.ogg", 'audio/ogg', 'BYTES');

	pg_assert_same(
		"--B\r\nContent-Disposition: form-data; name=\"model\"\r\n\r\nwhisper-1\r\n"
		. "--B\r\nContent-Disposition: form-data; name=\"response_format\"\r\n\r\njson\r\n"
		. "--B\r\nContent-Disposition: form-data; name=\"file\"; filename=\"voice-1.ogg\"\r\nContent-Type: audio/ogg\r\n\r\nBYTES\r\n--B--\r\n",
		$body,
		'the body'
	);

	pg_assert_contains('filename="ab.ogg"', ws_voice_multipart('B', array(), 'file', "a\"\r\nb.ogg", 'audio/ogg', ''), 'a name cannot break the header');
}

// The answer decides the recording's fate: a text, another try later, the
// whole queue held (the key), or this one given up.
function test_ws_voice_answer()
{
	$done = ws_voice_answer(200, '{"text":"  Merhaba, yarın görüşelim. ","language":"turkish"}');
	pg_assert_same('done', $done['outcome'], '200 with a text');
	pg_assert_same('Merhaba, yarın görüşelim.', $done['text'], 'trimmed text');
	pg_assert_same('turkish', $done['language'], 'language when given');

	pg_assert_same('', ws_voice_answer(200, '{"text":"ok"}')['language'], 'no language');
	pg_assert_same('fail', ws_voice_answer(200, 'not json')['outcome'], '200 without a text');

	foreach (array(401, 402, 403) as $status) {
		pg_assert_same('hold', ws_voice_answer($status, '{"error":{"message":"Incorrect API key"}}')['outcome'], 'held on ' . $status);
	}

	foreach (array(0, 408, 429, 500, 503) as $status) {
		pg_assert_same('retry', ws_voice_answer($status, '', 'timeout')['outcome'], 'retried on ' . $status);
	}

	$bad = ws_voice_answer(400, '{"error":{"message":"Invalid file format."}}');
	pg_assert_same('fail', $bad['outcome'], 'a file the service cannot read');
	pg_assert_contains('Invalid file format.', $bad['error'], 'the service\'s own words');
	pg_assert_contains('HTTP 413', ws_voice_answer(413, '')['error'], 'the status when it says nothing');
}

// Both uploads queue a recording, the delete takes it away, the hourly job
// carries the queue on, and the API shows the transcript in the file.
function test_ws_voice_is_wired()
{
	$read = function ($path) {
		return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $path);
	};

	pg_assert_contains('ws_voice_queue($channel, $result[\'message_id\'], $stored[\'file_id\'], $stored[\'name\'], (array) ($options[\'voice\'] ?? array()))', $read('includes/workspace/files.php'), 'the panel upload, with what the recorder measured');
	pg_assert_contains('ws_voice_queue($channel, $result[\'message_id\'], $stored[\'file_id\'], $stored[\'name\'])', $read('includes/workspace/api_resources.php'), 'files.create');
	pg_assert_contains('ws_voice_message_deleted($message)', $read('includes/workspace/messages.php'), 'delete for everyone');
	pg_assert_contains('ws_voice_job()', $read('workspace_recurring_job.php'), 'the hourly job');
	pg_assert_contains("'voice' => 'WorkspaceMessageVoice?'", $read('includes/workspace/api_resources.php'), 'the file schema');
	pg_assert_contains("ws_voice_panel_present((int) \$row['file_id'])", $read('includes/workspace/messages.php'), 'the panel\'s file part');
	pg_assert_contains("\$brief['voice'] = function_exists('ws_voice_channel_state')", $read('includes/workspace/channels.php'), 'the channel screen');
	pg_assert_contains("ws_voice_announce(\$message, \$channel, \$row, \$outcome", $read('includes/workspace/voice.php'), 'announced once written out');
	pg_assert_contains("'voice'     => array('duration_ms' => \$request['voice_duration_ms'] ?? 0, 'peaks' => \$request['voice_peaks'] ?? '')", $read('includes/workspace/actions.php'), 'ws_attach hands the measure on');
	pg_assert_contains("case 'ws_channel_voice':", $read('includes/workspace/actions.php'), 'the channel switch');
	pg_assert_contains("(string) ws_voice_kick(1)", $read('includes/workspace/actions.php'), 'the panel kick, one call at most');
	pg_assert_contains("'ws_channel_voice', 'ws_voice_kick'", $read('includes/panel/actions.php'), 'both actions routed');
	pg_assert_contains("api('ws_channel_voice'", $read('assets/js/workspace.js'), 'the menu item');
	pg_assert_contains("api('ws_voice_kick')", $read('assets/js/workspace.js'), 'the screen carries the queue on');
	pg_assert_contains("options.send(file, { duration_ms: length, peaks: peaks })", $read('assets/js/workspace_voice.js'), 'the recorder sends what it measured');
	pg_assert_contains('var PEAKS = ' . WS_VOICE_PEAKS . ';', $read('assets/js/workspace_voice.js'), 'the recorder keeps no more levels than the server');
}

// What the recorder measured is kept only in its own shape: a length within
// the cap, the levels as digits, no more of them than the column is given.
function test_ws_voice_measure_clean()
{
	pg_assert_same(array('duration_ms' => 4200, 'peaks' => '0139'), ws_voice_measure_clean(4200, '0139'), 'as sent');
	pg_assert_same(array('duration_ms' => 4201, 'peaks' => ''), ws_voice_measure_clean('4200.6', ''), 'a fraction rounded, no waveform');
	pg_assert_same(array('duration_ms' => 0, 'peaks' => ''), ws_voice_measure_clean(-5, array('1')), 'below zero, not a string');
	pg_assert_same(array('duration_ms' => 0, 'peaks' => ''), ws_voice_measure_clean('abc', '12a'), 'not a number, not digits');
	pg_assert_same((WS_VOICE_MAX_SECONDS + 1) * 1000, ws_voice_measure_clean(PHP_INT_MAX, '')['duration_ms'], 'held to the cap');
	pg_assert_same(str_repeat('5', WS_VOICE_PEAKS), ws_voice_measure_clean(1000, str_repeat('5', WS_VOICE_PEAKS))['peaks'], 'as many levels as kept');
	pg_assert_same('', ws_voice_measure_clean(1000, str_repeat('5', WS_VOICE_PEAKS + 1))['peaks'], 'one more is refused whole');
	pg_assert_true(WS_VOICE_PEAKS <= 255, 'the levels fit ws_voice.peaks');
}

// The event is in the catalogue an application subscribes from, carries the
// channel a narrowed subscription is matched by, and leaves private channels
// and discussions out.
function test_ws_voice_transcribed_event()
{
	require_once(PG_FUNCTIONS_DIR . '/includes/workspace/api.php');

	pg_assert_true(isset(ws_webhook_events()['workspace.message.transcribed']), 'in the catalogue');

	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/voice.php');
	$start = strpos($source, 'function ws_voice_announce(');
	$body = substr($source, $start, strpos($source, "\n}\n", $start) - $start);

	pg_assert_contains("((string) (\$channel['kind'] ?? '') !== 'public')", $body, 'public channels only');
	pg_assert_contains("'channel_id'  => (int) \$channel['id']", $body, 'with its channel');
}
