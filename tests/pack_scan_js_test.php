<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Pack screen's scan field (assets/js/pack.js), run under
 * Node: a product held in front of the phone's camera counts one unit
 * however long it stays, and a code read while the previous one is being
 * answered waits its turn instead of being lost in the field.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function pack_scan_js_test_source()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pack.js');
}

// One "function name(" of pack.js up to the closing brace at its own indent.
function pack_scan_js_test_function($name)
{
	$source = pack_scan_js_test_source();
	$start = strpos($source, "\n    function " . $name . '(');

	if ($start === false) {
		return '';
	}

	$end = strpos($source, "\n    }\n", (int) $start);

	return (string) substr($source, (int) $start, (int) $end - (int) $start) . "\n    }\n";
}

// Runs a script under Node and returns what it printed as JSON; null when
// Node is not installed.
function pack_scan_js_test_run($script)
{
	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);

	if ($code !== 0) {
		return null;
	}

	$file = tempnam(sys_get_temp_dir(), 'pgpackjs');
	file_put_contents($file, "'use strict';\n" . $script);
	$out = array();
	exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	unlink($file);

	$printed = implode("\n", $out);
	pg_assert_same(0, $code, $printed);
	$answer = json_decode($printed, true);
	pg_assert_true(is_array($answer), 'Node printed JSON: ' . $printed);

	return $answer;
}

// Frames come about every 250 ms. A code held in view is read once; the
// same code counts again only after it left the view for half a second; a
// frame or two missed while it is held is no new unit.
function test_pack_camera_reads_a_held_code_once()
{
	$text = pack_scan_js_test_function('cameraRead');
	pg_assert_true($text !== '', 'cameraRead is found');

	$frames = array(
		array('A', 0), array('A', 250), array('A', 1600), array('A', 3200),
		array('', 3450), array('A', 3700),
		array('', 3950), array('', 4200), array('', 4450), array('A', 4700),
		array('B', 4950), array('A', 5200),
	);
	$answer = pack_scan_js_test_run($text
		. "\nvar state = { code: '', goneAt: 0 };\n"
		. "var frames = " . json_encode($frames) . ";\n"
		. "console.log(JSON.stringify(frames.map(function (f) { return cameraRead(state, f[0], f[1]); })));\n");

	if ($answer === null) {
		return;
	}

	pg_assert_same(
		array(true, false, false, false, false, false, false, false, false, true, true, true),
		$answer,
		'held: once; a missed frame: no unit; out of view half a second: again; another code: at once'
	);
}

// After an answer the codes read meanwhile are sent in turn, unless the
// screen moves on: an order found, a pack closed, a reload.
function test_pack_codes_read_meanwhile_wait_their_turn()
{
	$text = pack_scan_js_test_function('afterAnswer');
	pg_assert_true($text !== '', 'afterAnswer is found');

	$cases = array(
		array(array('ok' => true, 'good' => true), true, array('B', 'C')),
		array(array('ok' => true, 'good' => false), true, array('B', 'C')),
		array(array('ok' => false, 'message' => 'x'), true, array('B', 'C')),
		array(array('ok' => true, 'done' => array(array('text' => 'x'))), true, array()),
		array(array('ok' => false, 'reload' => true), true, array()),
		array(array('ok' => true, 'url' => 'pack.php?ship_to=1'), false, array()),
		array(null, true, array('B', 'C')),
	);
	$answer = pack_scan_js_test_run($text
		. "\nvar cases = " . json_encode($cases) . ";\n"
		. "console.log(JSON.stringify(cases.map(function (one) { return afterAnswer(['B', 'C'], one[0], one[1]); })));\n");

	if ($answer === null) {
		return;
	}

	foreach ($cases as $index => $case) {
		pg_assert_same($case[2], $answer[$index], 'case ' . $index);
	}
}

// read() empties the field for every code and keeps a code read while
// busy; the camera loop asks cameraRead().
function test_pack_scan_wiring()
{
	$read = pack_scan_js_test_function('read');

	pg_assert_true(preg_match('/input\.value = \'\';\s*if \(!code\)/', $read) === 1, 'the field is emptied before anything else');
	pg_assert_true(strpos($read, 'waiting.push(code)') !== false, 'a code read while busy waits');
	pg_assert_true(strpos($read, 'afterAnswer(waiting') !== false, 'the answer decides what is sent next');
	pg_assert_true(strpos(pack_scan_js_test_function('readFrame'), 'cameraRead(seen') !== false, 'the camera asks cameraRead');
}
