<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the drop mark on the visual designer's canvas (_sdSetDropMark()):
 * one element shows a mark at a time, so an ancestor that marked itself a
 * moment earlier loses its line when a child takes the drag, and a mark is
 * written only when it changes. The marks and the function are cut out of
 * assets/js/style_designer.src.js and run under Node with stand-in elements;
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

// Runs $steps (JavaScript statements on the stand-in elements `outer` and
// `inner`) and returns the value of $result; null without Node.
function designer_drop_mark_test_run($steps, $result)
{
	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);

	if ($code !== 0) {
		return null;
	}

	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	$from = strpos($source, 'var _SD_INSERT_MARKS = ');
	$to = strpos($source, 'function setupDropEvents(', (int) $from);
	pg_assert_true(($from !== false) && ($to !== false), 'the designer has the drop marks and _sdSetDropMark()');

	$script = substr($source, $from, $to - $from)
		. 'var writes = 0;'
		. 'function El() { var c = []; return { _sdDropZone: null, classList: {'
		. ' contains: function (m) { return c.indexOf(m) !== -1; },'
		. ' add: function (m) { writes++; c.push(m); },'
		. ' remove: function (m) { writes++; c.splice(c.indexOf(m), 1); },'
		. ' list: function () { return c.slice(); } } }; }'
		. 'var outer = El(); var inner = El();'
		. $steps
		. 'console.log(JSON.stringify(' . $result . '));';

	$file = tempnam(sys_get_temp_dir(), 'pgdropmark');
	file_put_contents($file, $script);
	$out = array();
	exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	unlink($file);

	pg_assert_same(0, $code, implode("\n", $out));

	return json_decode(implode("\n", $out), true);
}

// An ancestor marked "before" a moment earlier loses its line, and its
// remembered zone, when a child inside it takes the drag.
function test_designer_drop_mark_one_line_at_a_time()
{
	$answer = designer_drop_mark_test_run(
		'_sdSetDropMark(outer, "sd-insert-before"); outer._sdDropZone = "before";'
		. '_sdSetDropMark(inner, "sd-insert-after"); inner._sdDropZone = "after";',
		'[outer.classList.list(), outer._sdDropZone, inner.classList.list(), inner._sdDropZone]'
	);

	if ($answer === null) {
		return;
	}

	pg_assert_same(array(array(), null, array('sd-insert-after'), 'after'), $answer, 'only the child that took the drag keeps a mark');
}

// The same mark again writes nothing; a new mark on the same element
// replaces the old one; clearing leaves no mark and no zone.
function test_designer_drop_mark_written_on_change_only()
{
	$answer = designer_drop_mark_test_run(
		'_sdSetDropMark(inner, "sd-insert-before"); var a = writes;'
		. '_sdSetDropMark(inner, "sd-insert-before"); var b = writes;'
		. '_sdSetDropMark(inner, "sd-drop-hl"); var c = inner.classList.list();'
		. 'inner._sdDropZone = "inside"; _sdSetDropMark(inner, null);'
		. '_sdSetDropMark(outer, "sd-insert-after");',
		'[a, b, c, inner.classList.list(), inner._sdDropZone, outer.classList.list()]'
	);

	if ($answer === null) {
		return;
	}

	pg_assert_same(array(1, 1, array('sd-drop-hl'), array(), null, array('sd-insert-after')), $answer, 'one write per change, replaced on the same element, cleared with its zone');
}
