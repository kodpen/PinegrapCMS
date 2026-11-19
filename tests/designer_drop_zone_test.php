<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the zone a drop on the visual designer's canvas takes once the
 * drag rules are asked (_sdCanvasDropZone()): the middle band of an element
 * that takes no children falls back to the nearer edge beside it, a
 * before/after band its parent refuses falls back to inside, and with
 * neither the element answers nothing so an ancestor may take the drag. The
 * function is cut out of assets/js/style_designer.src.js and run under Node;
 * on a machine without Node the test is skipped.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Runs each case [zone, y, h, inside, sibling] through _sdCanvasDropZone()
// and returns its answers; null without Node.
function designer_drop_zone_test_run($cases)
{
	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);

	if ($code !== 0) {
		return null;
	}

	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	$from = strpos($source, 'function _sdCanvasDropZone(');
	$to = strpos($source, 'function setupDropEvents(', (int) $from);
	pg_assert_true(($from !== false) && ($to !== false), 'the designer has _sdCanvasDropZone()');

	$script = substr($source, $from, $to - $from)
		. 'var cases = ' . json_encode($cases) . ';'
		. 'console.log(JSON.stringify(cases.map(function (c) {'
		. ' return _sdCanvasDropZone(c[0], c[1], c[2], function () { return c[3]; }, function () { return c[4]; });'
		. '})));';

	$file = tempnam(sys_get_temp_dir(), 'pgdropzone');
	file_put_contents($file, $script);
	$out = array();
	exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	unlink($file);

	pg_assert_same(0, $code, implode("\n", $out));

	return json_decode(implode("\n", $out), true);
}

// The middle band of an element that takes no children lands beside it, on
// the nearer edge, when its parent takes the drag; it no longer bubbles to an
// ancestor that measures the pointer against its own, far larger box.
function test_designer_drop_zone_middle_of_closed_element_lands_beside_it()
{
	$answer = designer_drop_zone_test_run(array(
		array('inside', 40, 119, false, true),
		array('inside', 59, 119, false, true),
		array('inside', 60, 119, false, true),
		array('inside', 80, 119, false, true),
	));

	if ($answer === null) {
		return;
	}

	pg_assert_same(array('before', 'before', 'after', 'after'), $answer, 'upper half of the middle band before the element, lower half after it');
}

// What an element that takes the drag, or a parent that does, already did
// stays as it was.
function test_designer_drop_zone_existing_bands_unchanged()
{
	$answer = designer_drop_zone_test_run(array(
		array('inside', 60, 119, true, true),
		array('inside', 60, 119, true, false),
		array('before', 10, 119, false, true),
		array('after', 110, 119, true, true),
		array('before', 10, 119, true, false),
		array('after', 110, 119, true, false),
		array('inside-start', 60, 119, true, true),
		array('inside-end', 60, 119, true, false),
	));

	if ($answer === null) {
		return;
	}

	pg_assert_same(array('inside', 'inside', 'before', 'after', 'inside', 'inside', 'inside-start', 'inside'), $answer, 'inside stays inside; a sibling band the parent takes stays; one it refuses falls back to inside');
}

// Neither the element nor its parent takes the drag: no zone, so the
// dragover bubbles on and an ancestor may take it.
function test_designer_drop_zone_nothing_takes_it()
{
	$answer = designer_drop_zone_test_run(array(
		array('inside', 60, 119, false, false),
		array('before', 10, 119, false, false),
		array('after', 110, 119, false, false),
	));

	if ($answer === null) {
		return;
	}

	pg_assert_same(array(null, null, null), $answer, 'no zone when nothing here takes the drag');
}
