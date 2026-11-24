<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the remembered call choices: the microphone, camera and speaker
 * and whether the person was muted or had the camera off, kept in the
 * browser and used at the next start, join and accept
 * (assets/js/workspace_calls.js, its pure parts under node), and the wiring
 * of the Speaker or headphones select.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php');

// Runs the script after workspace_calls.js under node and returns what it
// printed as JSON; false when node is missing.
function workspace_calls_choices_test_run($script)
{
	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);

	if ($code !== 0) {
		return false;
	}

	$file = @tempnam(sys_get_temp_dir(), 'pgwscall');

	if ($file === false) {
		return false;
	}

	$stub = "var window = { addEventListener: function () {} }; var navigator = {}; var document = {};\n";
	file_put_contents($file, $stub . file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace_calls.js') . "\nvar C = window.PGWsCalls;\n" . $script);
	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	return json_decode(implode("\n", $out), true);
}

function test_workspace_calls_choices_read_and_change()
{
	$result = workspace_calls_choices_test_run(<<<'JS'
console.log(JSON.stringify({
	none: C.choicesRead(null),
	broken: C.choicesRead('{not json'),
	list: C.choicesRead('[1,2]'),
	kept: C.choicesRead('{"audio":"mic2","video":"cam1","speaker":"out3","muted":true,"cameraOff":true}'),
	wrong: C.choicesRead('{"audio":5,"video":null,"muted":"yes","cameraOff":1,"other":"x"}'),
	changed: C.choicesWith({ audio: 'mic2', muted: true }, { video: 'cam9', muted: false, other: 'x' }),
	fresh: C.choicesWith(null, { speaker: 'out1', cameraOff: true })
}));
JS
	);

	if ($result === false) {
		pg_assert_true(true, 'node is not installed: the browser parts are not run');
		return;
	}

	$empty = array('audio' => '', 'video' => '', 'speaker' => '', 'muted' => false, 'cameraOff' => false, 'background' => '');

	pg_assert_same($empty, $result['none'], 'nothing kept: no choice, not muted, camera on');
	pg_assert_same($empty, $result['broken'], 'broken text: no choice');
	pg_assert_same($empty, $result['list'], 'not an object: no choice');
	pg_assert_same(array('audio' => 'mic2', 'video' => 'cam1', 'speaker' => 'out3', 'muted' => true, 'cameraOff' => true, 'background' => ''), $result['kept'], 'the kept choices are read back');
	pg_assert_same($empty, $result['wrong'], 'values of the wrong type read as no choice, unknown names are dropped');
	pg_assert_same(array('audio' => 'mic2', 'video' => 'cam9', 'speaker' => '', 'muted' => false, 'cameraOff' => false, 'background' => ''), $result['changed'], 'one change keeps the other choices');
	pg_assert_same(array('audio' => '', 'video' => '', 'speaker' => 'out1', 'muted' => false, 'cameraOff' => true, 'background' => ''), $result['fresh'], 'a first change starts from no choice');
}

function test_workspace_calls_choices_fall_back_to_the_default()
{
	$result = workspace_calls_choices_test_run(<<<'JS'
var outputs = [{ kind: 'audiooutput', deviceId: 'default' }, { kind: 'audiooutput', deviceId: 'out3' }];
console.log(JSON.stringify({
	remembered: C.mediaAsk('video', { audio: 'mic2', video: 'cam1' }, true),
	picked: C.mediaAsk('video', { audio: 'mic2', video: 'cam1' }),
	none: C.mediaAsk('voice', { audio: '', video: '' }, true),
	speaker_there: C.speakerOf(outputs, 'out3'),
	speaker_gone: C.speakerOf(outputs, 'out9'),
	speaker_none: C.speakerOf(outputs, ''),
	speaker_no_list: C.speakerOf(null, 'out3')
}));
JS
	);

	if ($result === false) {
		pg_assert_true(true, 'node is not installed: the browser parts are not run');
		return;
	}

	pg_assert_same(array('ideal' => 'mic2'), $result['remembered']['audio']['deviceId'], 'a remembered microphone is only preferred, so a missing one gives way to the default');
	pg_assert_same(array('ideal' => 'cam1'), $result['remembered']['video']['deviceId'], 'a remembered camera is only preferred');
	pg_assert_same(array('exact' => 'mic2'), $result['picked']['audio']['deviceId'], 'a device picked in the window is asked for exactly');
	pg_assert_same(array('exact' => 'cam1'), $result['picked']['video']['deviceId'], 'a camera picked in the window is asked for exactly');
	pg_assert_true($result['remembered']['audio']['noiseSuppression'] === true, 'the microphone asks for noise suppression');
	pg_assert_true($result['remembered']['audio']['echoCancellation'] === true, 'and echo cancellation');
	pg_assert_false(isset($result['none']['audio']['deviceId']), 'no remembered microphone: the browser\'s default');
	pg_assert_false($result['none']['video'], 'a voice call asks for no camera');
	pg_assert_same('out3', $result['speaker_there'], 'the remembered speaker while it is there');
	pg_assert_same('', $result['speaker_gone'], 'a speaker no longer there: the browser\'s default');
	pg_assert_same('', $result['speaker_none'], 'no remembered speaker: the default');
	pg_assert_same('', $result['speaker_no_list'], 'no outputs listed: the default');
}

function test_workspace_calls_choices_wiring()
{
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace_calls.js');
	$strings = ws_call_js_strings();

	pg_assert_true(isset($strings['call_speaker']) && ($strings['call_speaker'] !== ''), 'the Speaker or headphones select has its text');
	pg_assert_contains("var CHOICES_KEY = 'pg_ws_call_choices';", $js, 'one key holds the choices');
	pg_assert_true(substr_count($js, 'window.localStorage') === 2, 'the storage is touched in two places only (read and write)');
	pg_assert_true((bool) preg_match('/function choicesLoad\(\) \{\s*try \{/', $js), 'reading the storage is wrapped in try/catch');
	pg_assert_true((bool) preg_match('/function choicesSave\(change\) \{\s*try \{/', $js), 'writing the storage is wrapped in try/catch');
	pg_assert_contains('getUserMedia(mediaAsk(kind, mine.devices, true))', $js, 'start, join and accept ask for the remembered devices, softly');
	pg_assert_contains('choicesSave({ muted: mine.muted });', $js, 'Mute is remembered');
	pg_assert_contains('choicesSave({ cameraOff: mine.cameraOff });', $js, 'the camera off is remembered');
	pg_assert_contains("choicesSave(kind === 'audio' ? { audio: deviceId } : { video: deviceId });", $js, 'a microphone or camera picked is remembered');
	pg_assert_contains('choicesSave({ speaker: deviceId });', $js, 'a speaker picked is remembered');
	pg_assert_contains("('setSinkId' in HTMLMediaElement.prototype)", $js, 'the Speaker select only where the browser can choose a speaker');
}
