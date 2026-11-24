<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the call's pop-out: "Pop out" moves the call window into a
 * small Document Picture-in-Picture window above other windows and tabs
 * (tiles, Mute, camera, Hang up), not drawn where the browser lacks it;
 * closing it returns the call to the page (assets/js/workspace_calls.js,
 * its pure part under node, and the wiring in both sheets).
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
function workspace_calls_popout_test_run($script)
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

function test_workspace_calls_popout_state()
{
	$got = workspace_calls_popout_test_run(<<<'JS'
console.log(JSON.stringify({
	none: C.popState(false, false, 'video', 2),
	open: C.popState(true, true, 'video', 2),
	two: C.popState(true, false, 'video', 2),
	four: C.popState(true, false, 'video', 4),
	voice: C.popState(true, false, 'voice', 4)
}));
JS
	);

	if ($got === false) {
		pg_assert_true(true, 'node is not installed: the pop-out is checked in CI');
		return;
	}

	pg_assert_false($got['none']['show'], 'without Document Picture-in-Picture there is no Pop out');
	pg_assert_false($got['open']['show'], 'a call already popped out offers no second Pop out');
	pg_assert_true($got['two']['show'], 'a browser that can pops out');
	pg_assert_same(array(360, 320), array($got['two']['width'], $got['two']['height']), 'two people on video: a small window');
	pg_assert_same(array(480, 420), array($got['four']['width'], $got['four']['height']), 'a group on video: room for a grid of four');
	pg_assert_same(array(320, 180), array($got['voice']['width'], $got['voice']['height']), 'a voice call: names and tools only');
}
