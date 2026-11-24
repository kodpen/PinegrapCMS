<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the call window's sizes and one's own picture: Make larger /
 * Make smaller and Full screen in the window's head at all times, the size
 * remembered with the call choices, a tile pinned large, and Hide my
 * picture in the window's menu (assets/js/workspace_calls.js, its pure
 * parts under node).
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
function workspace_calls_window_test_run($script)
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

function test_workspace_calls_window_size_is_remembered()
{
	$got = workspace_calls_window_test_run(<<<'JS'
console.log(JSON.stringify({
	large: C.choicesRead('{"size":"large"}').size,
	small: C.choicesRead('{"size":"small"}').size,
	other: C.choicesRead('{"size":"huge"}').size,
	wrong: C.choicesRead('{"size":3}').size,
	changed: C.choicesWith({ audio: 'mic2', muted: true }, { size: 'large' }),
	bad: C.choicesWith({ size: 'small' }, { size: 'huge' }).size
}));
JS
	);

	if ($got === false) {
		pg_assert_true(true, 'node is not installed: the window is checked in CI');
		return;
	}

	pg_assert_same('large', $got['large'], 'a window made larger is read back');
	pg_assert_same('small', $got['small'], 'a window made smaller is read back');
	pg_assert_same('', $got['other'], 'an unknown size reads as none');
	pg_assert_same('', $got['wrong'], 'a size of the wrong type reads as none');
	pg_assert_same(array('audio' => 'mic2', 'video' => '', 'speaker' => '', 'muted' => true, 'cameraOff' => false, 'size' => 'large'), $got['changed'], 'the size is kept beside the other choices');
	pg_assert_same('', $got['bad'], 'an unknown size is never stored');
}

function test_workspace_calls_window_sizes_and_pin()
{
	$got = workspace_calls_window_test_run(<<<'JS'
var tiles = { tiles: [11, 12] };
function pin(id, more) { var v = { pin: id, tiles: [11, 12] }; Object.keys(more || {}).forEach(function (k) { v[k] = more[k]; }); return v; }
console.log(JSON.stringify({
	card: C.stageState([], 10, '', true, false, 0, tiles),
	larger: C.stageState([], 10, 'large', true, false, 0, tiles),
	full: C.stageState([], 10, '', true, true, 0, tiles),
	share_small: C.stageState([11], 10, 'small', true, false, 0, tiles),
	pin_other: C.stageState([], 10, '', true, false, 0, pin(12)),
	pin_self: C.stageState([], 10, '', true, false, 0, pin(0)),
	pin_small: C.stageState([], 10, 'small', true, false, 0, pin(12)),
	pin_gone: C.stageState([], 10, '', true, false, 0, pin(13)),
	pin_hidden: C.stageState([], 10, '', true, false, 0, pin(0, { selfHidden: true })),
	pin_and_shares: C.stageState([11, 12], 10, '', true, false, 12, pin(11)),
	no_view: C.stageState([11], 10, '', true, false, 0)
}));
JS
	);

	if ($got === false) {
		pg_assert_true(true, 'node is not installed: the window is checked in CI');
		return;
	}

	pg_assert_same(array('is-showing' => false, 'is-stage' => false, 'is-pinned' => false, 'is-self-sharing' => false), $got['card']['classes'], 'nothing on the stage, no size chosen: the card');
	pg_assert_same(array('label' => 'call_larger', 'icon' => 'bi-arrows-angle-expand', 'next' => 'large'), $got['card']['size'], 'the card can be made larger with nothing shared');
	pg_assert_true($got['larger']['classes']['is-stage'] && !$got['larger']['classes']['is-showing'], 'made larger: the grid fills the large window');
	pg_assert_same('small', $got['larger']['size']['next'], 'a larger window can be made smaller again');
	pg_assert_true($got['full']['classes']['is-stage'], 'full screen is always large');
	pg_assert_same('call_full_exit', $got['full']['full']['label'], 'in full screen the head\'s button leaves it');
	pg_assert_true(!$got['share_small']['classes']['is-stage'] && $got['share_small']['classes']['is-showing'], 'a window kept smaller shows a share at card size');
	pg_assert_same(array(12, 0, array()), array($got['pin_other']['pin'], $got['pin_other']['main'], $got['pin_other']['rail']), 'a pinned member\'s tile is on the stage');
	pg_assert_same(array('is-showing' => true, 'is-stage' => true, 'is-pinned' => true, 'is-self-sharing' => false), $got['pin_other']['classes'], 'a pin grows the window as a share does');
	pg_assert_same(0, $got['pin_self']['pin'], 'one\'s own tile can be pinned');
	pg_assert_true($got['pin_self']['classes']['is-pinned'], 'one\'s own pinned tile is on the stage');
	pg_assert_true($got['pin_small']['classes']['is-showing'] && !$got['pin_small']['classes']['is-stage'], 'a window kept smaller shows the pinned tile at card size');
	pg_assert_same(-1, $got['pin_gone']['pin'], 'a pinned member who left: nothing pinned');
	pg_assert_false($got['pin_gone']['classes']['is-showing'], 'and the window goes back to its grid');
	pg_assert_same(-1, $got['pin_hidden']['pin'], 'one\'s own hidden picture is never pinned');
	pg_assert_same(array(11, 0, array(11, 12)), array($got['pin_and_shares']['pin'], $got['pin_and_shares']['main'], $got['pin_and_shares']['rail']), 'a pinned tile puts every share on the rail');
	pg_assert_same(array(-1, 11), array($got['no_view']['pin'], $got['no_view']['main']), 'without a view nothing is pinned');
}

function test_workspace_calls_window_wiring()
{
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace_calls.js');
	$strings = ws_call_js_strings();

	foreach (array('call_pin', 'call_unpin', 'call_more', 'call_self_hide', 'call_self_show', 'call_larger', 'call_smaller', 'call_full_screen') as $key) {
		pg_assert_true(isset($strings[$key]) && ($strings[$key] !== ''), 'the text ' . $key . ' is given to the window');
	}

	pg_assert_contains("choicesSave({ size: mine.size });", $js, 'the size is remembered with the call choices');
	pg_assert_contains('size: chosen.size, pin: -1, selfHidden: false', $js, 'a call starts at the remembered size, nothing pinned, one\'s own picture shown');
	pg_assert_contains('win.appendChild(headDraw(mine, win));', $js, 'the head comes first in the window');
	pg_assert_contains("el('ul', 'menu ws-call-menu')", $js, 'the window\'s menu');
	pg_assert_contains('mine.selfTile.node.hidden = mine.selfHidden;', $js, 'Hide my picture hides one\'s own tile on this screen only');
	pg_assert_contains("el('button', 'ws-call-tile-pin')", $js, 'every member tile is a button that pins it');
	pg_assert_contains('mine.pin = -1;', $js, 'a share chosen on the rail or Unpin lets the pin go');
	pg_assert_true(substr_count($js, "document.removeEventListener('click', mine.onMenuAway);") === 1, 'the menu\'s listener goes with the window');

	foreach (array('assets/css/backend.src.css', 'assets/css/workspace_guest.css') as $sheet) {
		$css = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $sheet);
		pg_assert_contains('.ws-call-tile.is-pinned', $css, $sheet . ': the pinned tile leaves the rail');
		pg_assert_contains('.ws-call.is-stage:not(.is-showing) .ws-call-body', $css, $sheet . ': a larger window with nothing shared');
	}
}
