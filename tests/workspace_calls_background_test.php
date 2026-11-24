<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a call's background effects: the pinned files
 * (includes/workspace/calls_background.php), the Calls card's "Background
 * effects" switch, the remembered choice, and the browser's pure parts
 * (assets/js/workspace_call_background.js under node): which buttons are
 * drawn, which way a mode runs, the mask's alpha.
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

// Runs the script after the given files under node and returns what it
// printed as JSON; false when node is missing.
function workspace_calls_background_test_run($files, $script)
{
	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);

	if ($code !== 0) {
		return false;
	}

	$file = @tempnam(sys_get_temp_dir(), 'pgwsbg');

	if ($file === false) {
		return false;
	}

	$body = "var window = { addEventListener: function () {} }; var navigator = {}; var document = {};\n";

	foreach ($files as $one) {
		$body .= file_get_contents(PG_FUNCTIONS_DIR . '/' . $one) . "\n";
	}

	file_put_contents($file, $body . $script);
	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	return json_decode(implode("\n", $out), true);
}

function test_workspace_calls_background_pinned_files()
{
	$files = ws_call_background_files();

	pg_assert_same(array('bundle', 'loader', 'wasm', 'model'), array_keys($files), 'four files: the module, the WASM loader and binary, the model');

	foreach ($files as $name => $file) {
		pg_assert_true((bool) preg_match('/^[0-9a-f]{64}$/', $file['sha256']), $name . ': a SHA-256 digest is pinned');
		pg_assert_true(is_int($file['size']) && ($file['size'] > 0), $name . ': its size is pinned');
		pg_assert_true((bool) preg_match('#^https://(cdn\.jsdelivr\.net/npm/@mediapipe/tasks-vision@' . preg_quote(WS_CALL_BACKGROUND_RUNTIME, '#') . '/|storage\.googleapis\.com/mediapipe-models/)#', $file['url']), $name . ': fetched from its fixed https source at the pinned version');
		pg_assert_same($file['file'], basename($file['file']), $name . ': a plain file name');
	}

	pg_assert_true(strpos($files['model']['url'], '/latest/') === false, 'the model is fetched from a numbered version, not latest');
	pg_assert_same('application/wasm', $files['wasm']['type'], 'the WASM is served as such, so the browser compiles it streaming');
	pg_assert_same(0, strpos($files['bundle']['type'], 'text/javascript'), 'the module is served as JavaScript');
	pg_assert_same(0, strpos(ws_call_background_dir(), PG_FUNCTIONS_DIR . '/data/'), 'kept under data/, outside the file-integrity check');
}

function test_workspace_calls_background_manifest()
{
	$files = ws_call_background_files();
	$manifest = ws_call_background_manifest($files);
	$changed = $files;
	$changed['model']['sha256'] = str_repeat('0', 64);

	pg_assert_true(ws_call_background_manifest_ok($manifest, $files), 'a fetch for these pins reads as ready');
	pg_assert_false(ws_call_background_manifest_ok($manifest, $changed), 'a new pin (an update) reads the folder as stale');
	pg_assert_false(ws_call_background_manifest_ok('', $files), 'no manifest: not ready');
	pg_assert_false(ws_call_background_manifest_ok('{broken', $files), 'a broken manifest: not ready');

	$urls = ws_call_background_urls($files, '/pg/workspace_call_background.php');

	pg_assert_same('/pg/workspace_call_background.php?f=wasm&v=' . substr($files['wasm']['sha256'], 0, 12), $urls['wasm'], 'each address names its file and its digest, so a new pin is a new address');
}

function test_workspace_calls_background_switch()
{
	pg_assert_same(false, ws_calls_settings_parse('')['background'], 'nothing stored: background effects off');
	pg_assert_same(true, ws_calls_settings_parse('{"on":true,"background":true}')['background'], 'turned on');

	$stored = ws_calls_settings_parse('{"on":true}') + array('secret_set' => false);
	$on = ws_calls_settings_from_post($stored, array('ws_calls_on' => '1', 'ws_calls_background' => '1'), false);
	$same = ws_calls_settings_from_post($stored, array('ws_calls_on' => '1'), false);

	pg_assert_true($on['settings']['background'] && $on['changed'], 'the switch is saved and counts as a change');
	pg_assert_false($same['settings']['background'] || $same['changed'], 'left off: nothing changed');

	$html = ws_calls_settings_card_html(ws_calls_settings_parse('{"on":true,"background":true}') + array('secret_set' => false, 'background_ready' => false), false, '/api.php');

	pg_assert_true(strpos($html, 'name="ws_calls_background" value="1" id="ws_calls_background" aria-describedby="ws_calls_background-help" checked') !== false, 'the card draws the switch on');
	pg_assert_true(strpos($html, h(lang('The files are not there for this version: Save fetches them again.'))) !== false, 'on without the files for these pins: the card says Save fetches them');
	pg_assert_true(ws_call_background_config(array('background' => false)) === null, 'switch off: the call window gets no files');
}

function test_workspace_calls_background_remembered()
{
	$result = workspace_calls_background_test_run(array('assets/js/workspace_calls.js'), <<<'JS'
var C = window.PGWsCalls;
console.log(JSON.stringify({
	kept: C.choicesRead('{"background":"remove"}').background,
	wrong: C.choicesRead('{"background":"sepia"}').background,
	changed: C.choicesWith({ video: 'cam1' }, { background: 'blur' }),
	refused: C.choicesWith({ background: 'blur' }, { background: 'x' }).background
}));
JS
	);

	if ($result === false) {
		pg_assert_true(true, 'node is not installed: the browser parts are not run');
		return;
	}

	pg_assert_same('remove', $result['kept'], 'the effect of the last call is read back');
	pg_assert_same('', $result['wrong'], 'an unknown effect reads as none');
	pg_assert_same('blur', $result['changed']['background'], 'a change is kept');
	pg_assert_same('cam1', $result['changed']['video'], 'with the other choices');
	pg_assert_same('', $result['refused'], 'a changed value is checked as a stored one is');
}

function test_workspace_calls_background_offer_and_route()
{
	$result = workspace_calls_background_test_run(array('assets/js/workspace_call_background.js'), <<<'JS'
var B = window.PGWsCallBackground;
var all = { files: true, simd: true, canvas: true, filter: true };
console.log(JSON.stringify({
	nothing: B.offer({}),
	native_only: B.offer({ native: true }),
	model: B.offer(all),
	no_simd: B.offer({ files: true, simd: false, canvas: true, filter: true }),
	no_filter: B.offer({ files: true, simd: true, canvas: true, filter: false }),
	switch_off: B.offer({ native: false, files: false, simd: true, canvas: true, filter: true }),
	blur_native: B.route('blur', { native: true, files: true, simd: true, canvas: true, filter: true }),
	blur_model: B.route('blur', all),
	remove_native_only: B.route('remove', { native: true }),
	remove_model: B.route('remove', all),
	unknown: B.route('sepia', all),
	mode: [B.modeRead('blur'), B.modeRead('remove'), B.modeRead(''), B.modeRead(3)],
	native_caps: [B.nativeFrom({ backgroundBlur: [false, true] }), B.nativeFrom({ backgroundBlur: [false] }), B.nativeFrom({}), B.nativeFrom(null)],
	alpha: Array.prototype.slice.call(B.alphaFill(new Array(12), [0, 0.5, 1.2]))
}));
JS
	);

	if ($result === false) {
		pg_assert_true(true, 'node is not installed: the browser parts are not run');
		return;
	}

	pg_assert_same(array('blur' => false, 'remove' => false), $result['nothing'], 'nothing can run: no button');
	pg_assert_same(array('blur' => true, 'remove' => false), $result['native_only'], 'the camera blurs by itself: Blur only, with no files');
	pg_assert_same(array('blur' => true, 'remove' => true), $result['model'], 'the files and a capable browser: both');
	pg_assert_same(array('blur' => false, 'remove' => false), $result['no_simd'], 'no WASM SIMD: no button');
	pg_assert_same(array('blur' => false, 'remove' => true), $result['no_filter'], 'a canvas that cannot blur: Remove only');
	pg_assert_same(array('blur' => false, 'remove' => false), $result['switch_off'], 'switch off and no native blur: no button');
	pg_assert_same('native', $result['blur_native'], 'Blur uses the camera\'s own blur first');
	pg_assert_same('model', $result['blur_model'], 'else the model');
	pg_assert_same('', $result['remove_native_only'], 'Remove always needs the model');
	pg_assert_same('model', $result['remove_model'], 'Remove with the model');
	pg_assert_same('', $result['unknown'], 'an unknown mode does not run');
	pg_assert_same(array('blur', 'remove', '', ''), $result['mode'], 'only the two modes are read');
	pg_assert_same(array(true, false, false, false), $result['native_caps'], 'the camera\'s own blur is read from its capabilities');
	pg_assert_same(array(255, 255, 255, 0, 255, 255, 255, 128, 255, 255, 255, 255), $result['alpha'], 'the mask is white with the confidence as alpha, bounded');
}

function test_workspace_calls_background_wiring()
{
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace_calls.js');
	$strings = ws_call_js_strings();
	$bundles = pg_js_bundles();

	foreach (array('call_background', 'call_background_blur', 'call_background_remove', 'call_background_failed') as $key) {
		pg_assert_true(isset($strings[$key]), $key . ' is sent to the window');
	}

	foreach (array('assets/js/workspace.min.js', 'assets/js/workspace_guest.min.js') as $bundle) {
		$list = $bundles[$bundle];

		pg_assert_true(array_search('assets/js/workspace_call_background.js', $list, true) < array_search('assets/js/workspace_calls.js', $list, true), $bundle . ': the effects come before the call window');
	}

	pg_assert_true(strpos($js, "backgroundSet(mine, chosen.background, true)") !== false, 'the remembered effect is started quietly with the call');
	pg_assert_true(strpos($js, 'backgroundDraw(mine, panel)') !== false, 'the buttons are drawn in the gear panel');
	pg_assert_true(strpos((string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/screen.php'), "'background' => ws_call_background_config(") !== false, 'the panel hands the file addresses to the window');
	pg_assert_true(strpos((string) file_get_contents(PG_FUNCTIONS_DIR . '/workspace_guest.php'), "'background' => ws_call_background_config(") !== false, 'and the guest page');
	pg_assert_true(strpos((string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/calls_settings.php'), 'ws_call_background_remove();') !== false, 'switching off deletes the files');
}
