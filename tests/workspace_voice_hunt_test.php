<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for faults of the Workspace voice messages (includes/workspace/voice.php):
 * a transcript arriving for a recording deleted meanwhile, a recording
 * posted through an OAuth connection asking the assistants once written
 * out, bytes that are no sound sent to the transcription service, an empty
 * transcript kept as done, and an error page kept as a spoken answer.
 *
 * ws_voice_run() and ws_voice_ask() run in a PHP process of their own with
 * a fake database and fake neighbours; the rest are pure.
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
require_once(__DIR__ . '/helpers/source_reader.php');

// Runs $script after the sources of $functions with $setup defined first;
// the script echoes JSON. Null when PHP cannot be started again.
function workspace_voice_hunt_test_run(array $functions, $setup, $script)
{
	if (!function_exists('exec') || !defined('PHP_BINARY') || (PHP_BINARY === '')) {
		return null;
	}

	$file = @tempnam(sys_get_temp_dir(), 'pgvoicehunt');

	if ($file === false) {
		return null;
	}

	$sources = '';

	foreach ($functions as $function) {
		$sources .= pg_test_function_source($function) . "\n";
	}

	file_put_contents($file, '<?php' . "\n" . $setup . "\n"
		. 'eval(' . var_export($sources, true) . ");\n"
		. $script . "\n");

	$lines = array();
	$code = 1;
	@exec(escapeshellarg(PHP_BINARY) . ' -d display_errors=stderr -d error_reporting=-1 ' . escapeshellarg($file) . ' 2>&1', $lines, $code);
	@unlink($file);

	$result = json_decode(implode("\n", $lines), true);

	if (($code !== 0) || !is_array($result)) {
		return array('error' => implode("\n", $lines));
	}

	return $result;
}

// ws_voice_run() with the service answering a transcript. $changed: does
// the done UPDATE find the row still running (false: deleted for everyone
// during the call). $deleted: the message as read again after the call.
function workspace_voice_hunt_test_run_job($changed, $deleted)
{
	$sound = sys_get_temp_dir() . '/pgvoicehunt-' . getmypid() . '.ogg';
	file_put_contents($sound, 'OggS' . str_repeat("\0", 60));

	$setup = <<<'PHP'
define('WS_VOICE_MAX_BYTES', 6291456);
define('WS_VOICE_MAX_SECONDS', 300);
define('WS_VOICE_ATTEMPTS', 3);
$GLOBALS['calls'] = array();
$GLOBALS['reads'] = 0;
function e($value) { return addslashes((string) $value); }
function lang($text) { return is_array($text) ? $text['string'] : $text; }
function ws_message($id)
{
	$GLOBALS['reads']++;

	// Read once before the call, once after it.
	return array('id' => $id, 'channel_id' => 5, 'file_id' => 7, 'file_name' => 'voice-20261126-101010.ogg', 'sender_kind' => 'user', 'sender_id' => 3,
		'deleted_at' => (($GLOBALS['reads'] > 1) && $GLOBALS['deleted']) ? 1700000000 : 0);
}
function ws_channel($id) { return array('id' => $id, 'kind' => 'public', 'name' => 'general'); }
function ws_voice_channel_allowed($channel) { return true; }
function db_value($sql) { return 'sound.ogg'; }
function pg_file_reader_path($name) { return $GLOBALS['sound']; }
function ws_voice_config() { return array('model' => '', 'provider' => 'OpenAI', 'error' => ''); }
function ws_voice_default_model() { return 'whisper-1'; }
function ws_voice_call($bytes, $name, $model) { $GLOBALS['calls'][] = 'call'; return array('status' => 200, 'body' => '{"text":"Hello there"}', 'error' => ''); }
function ws_voice_answer($status, $body, $error = '') { return array('outcome' => 'done', 'text' => 'Hello there', 'language' => 'en', 'error' => ''); }
function ws_voice_finish($row, $status, $error) { $GLOBALS['calls'][] = 'finish:' . $status; return $status; }
function db($sql) { return true; }
function ws_voice_changed() { return $GLOBALS['changed']; }
function ws_message_touch($id) { $GLOBALS['calls'][] = 'touch'; }
function ws_voice_announce($message, $channel, $row, $outcome, $provider, $model) { $GLOBALS['calls'][] = 'announce'; }
function ws_voice_ask($id) { $GLOBALS['calls'][] = 'ask'; }
function pg_config_text_save($name, $value) {}
PHP;

	$setup .= "\n" . '$GLOBALS[\'sound\'] = ' . var_export($sound, true) . ";\n"
		. '$GLOBALS[\'changed\'] = ' . var_export((bool) $changed, true) . ";\n"
		. '$GLOBALS[\'deleted\'] = ' . var_export((bool) $deleted, true) . ";\n";

	$result = workspace_voice_hunt_test_run(array('ws_voice_run', 'ws_voice_sound_rule'), $setup,
		'$status = ws_voice_run(array("id" => 1, "message_id" => 11, "file_id" => 7, "attempts" => 0, "duration_ms" => 0));'
		. ' echo json_encode(array("status" => $status, "calls" => $GLOBALS["calls"]));');

	@unlink($sound);

	return $result;
}

// Deleted for everyone while the service wrote it out: the transcript of a
// recording nobody can read any more is not announced to the webhooks, and
// nobody is asked.
function test_ws_voice_hunt_run_says_nothing_for_a_recording_deleted_during_the_call()
{
	$result = workspace_voice_hunt_test_run_job(false, true);

	if ($result === null) {
		return;
	}

	pg_assert_true(!isset($result['error']), 'the job ran: ' . (isset($result['error']) ? $result['error'] : ''));

	if (isset($result['error'])) {
		return;
	}

	pg_assert_same('skipped', $result['status'], 'the row was gone');
	pg_assert_same(array('call'), $result['calls'], 'no announce, no ask, no touch');
}

// The row moved to a copy and the message read again is deleted: kept, but
// not announced as the deleted message.
function test_ws_voice_hunt_run_announces_no_deleted_message()
{
	$result = workspace_voice_hunt_test_run_job(true, true);

	if (($result === null) || isset($result['error'])) {
		pg_assert_true($result === null, 'the job ran: ' . (isset($result['error']) ? $result['error'] : ''));

		return;
	}

	pg_assert_same('done', $result['status'], 'kept');
	pg_assert_same(array('call'), $result['calls'], 'no announce for a deleted message');
}

// The ordinary case still announces and asks once.
function test_ws_voice_hunt_run_announces_a_recording_still_there()
{
	$result = workspace_voice_hunt_test_run_job(true, false);

	if (($result === null) || isset($result['error'])) {
		pg_assert_true($result === null, 'the job ran: ' . (isset($result['error']) ? $result['error'] : ''));

		return;
	}

	pg_assert_same('done', $result['status'], 'done');
	pg_assert_same(array('call', 'touch', 'announce', 'ask'), $result['calls'], 'touched, announced, asked once');
}

// ws_voice_ask() for a message of the person ($via 0) or one posted through
// an OAuth connection ($via > 0): whether the assistants are asked.
function workspace_voice_hunt_test_ask($via)
{
	$setup = <<<'PHP'
$GLOBALS['asked'] = 0;
function ws_message($id) { return array('id' => $id, 'channel_id' => 5, 'sender_kind' => 'user', 'sender_id' => 3, 'deleted_at' => 0, 'body' => '<@app:2>', 'via_client_id' => $GLOBALS['via']); }
function ws_message_ask_assistants($viewer, $channel, $id, $body) { $GLOBALS['asked']++; return array('claude' => false); }
function ws_channel($id) { return array('id' => $id, 'kind' => 'public'); }
function ws_ai_viewer($id) { return array('id' => $id); }
function ws_can_post_channel($viewer, $channel) { return true; }
PHP;

	return workspace_voice_hunt_test_run(array('ws_voice_ask'), $setup . "\n" . '$GLOBALS[\'via\'] = ' . (int) $via . ";\n",
		'ws_voice_ask(11); echo json_encode(array("asked" => $GLOBALS["asked"]));');
}

// An OAuth connection's recording asked nobody when it was posted
// (ws_api_message_asks_assistants()); its transcript asks nobody either.
function test_ws_voice_hunt_ask_skips_a_recording_posted_through_an_oauth_connection()
{
	$oauth = workspace_voice_hunt_test_ask(9);
	$person = workspace_voice_hunt_test_ask(0);

	if (($oauth === null) || ($person === null)) {
		return;
	}

	pg_assert_true(!isset($oauth['error']) && !isset($person['error']), 'ran: ' . ($oauth['error'] ?? '') . ($person['error'] ?? ''));
	pg_assert_same(0, $oauth['asked'] ?? null, 'through an OAuth connection: nobody asked');
	pg_assert_same(1, $person['asked'] ?? null, 'the person\'s own recording: asked once');
}

// Only bytes that start as a sound are sent to the transcription service.
function test_ws_voice_hunt_sound_rule()
{
	pg_assert_true(ws_voice_sound_rule("\x1A\x45\xDF\xA3" . str_repeat("\0", 20)), 'WebM');
	pg_assert_true(ws_voice_sound_rule('OggS' . str_repeat("\0", 20)), 'Ogg');
	pg_assert_true(ws_voice_sound_rule("\0\0\0\x20ftypM4A " . str_repeat("\0", 20)), 'M4A');
	pg_assert_true(ws_voice_sound_rule('RIFF' . "\x24\0\0\0" . 'WAVEfmt '), 'WAV');
	pg_assert_true(ws_voice_sound_rule('ID3' . "\x04\0\0\0\0\0\0"), 'MP3 with a tag');
	pg_assert_true(ws_voice_sound_rule("\xFF\xFB\x90\x00"), 'MP3 frame');
	pg_assert_true(ws_voice_sound_rule("\xFF\xF1\x50\x80"), 'AAC ADTS');
	pg_assert_false(ws_voice_sound_rule('%PDF-1.7 a contract'), 'a PDF named voice-….mp3');
	pg_assert_false(ws_voice_sound_rule('Dear team, the figures are'), 'text');
	pg_assert_false(ws_voice_sound_rule("\xFF"), 'too short');
}

// The job reads the bytes and asks the rule before the call.
function test_ws_voice_hunt_run_asks_the_sound_rule_before_the_call()
{
	$source = pg_test_function_source('ws_voice_run');
	$rule = strpos($source, 'ws_voice_sound_rule(');
	$call = strpos($source, 'ws_voice_call(');

	pg_assert_true(($rule !== false) && ($call !== false) && ($rule < $call), 'the rule before the call');
}

// An empty text is no transcript: failed with words, not done with nothing.
function test_ws_voice_hunt_empty_transcript_is_not_done()
{
	$empty = ws_voice_answer(200, '{"text":"   "}');

	pg_assert_same('fail', $empty['outcome'], 'not done');
	pg_assert_true($empty['error'] !== '', 'says why');

	$said = ws_voice_answer(200, '{"text":"Merhaba","language":"turkish"}');

	pg_assert_same('done', $said['outcome'], 'a text is done');
	pg_assert_same('Merhaba', $said['text'], 'its text');
}

// A 200 error page from a proxy or a service is no sound.
function test_ws_voice_hunt_speech_answer_refuses_a_text_page()
{
	$page = ws_voice_speech_answer(200, '<html><body>Service unavailable</body></html>', 'text/html; charset=utf-8');

	pg_assert_false($page['ok'], 'an HTML page is no sound');
	pg_assert_true($page['error'] !== '', 'says why');
	pg_assert_false(ws_voice_speech_answer(200, 'Bad gateway', 'text/plain')['ok'], 'plain text is no sound');
	pg_assert_true(ws_voice_speech_answer(200, "ID3\x04\0\0", 'audio/mpeg')['ok'], 'an mp3 is a sound');
	pg_assert_true(ws_voice_speech_answer(200, "\xFF\xFB\x90", '')['ok'], 'no type still taken');
}
