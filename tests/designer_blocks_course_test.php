<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the course block family (assets/js/style_designer_blocks_
 * course.js), run under Node (skipped on a machine without Node): its own
 * Courses group and four blocks; each printed by the site's renderer as a
 * hidden section marked data-pg-course="<kind>" that pg_course_blocks_inject()
 * finds, with the marks pg_course.js fills, a real button, a list whose
 * first item is the row the script copies, and no script, handler or style
 * of its own; the canvas drawing the hidden roots; the Preview's twin row.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(__DIR__ . '/helpers/designer_family_run.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/course_blocks.php');

// Every block's tree, from Node; null without Node.
function _test_course_block_trees()
{
	static $got = false;
	if ($got !== false) {
		return $got;
	}
	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);
	if ($code !== 0) {
		return $got = null;
	}
	$script = "'use strict';\n"
		. "function _sdT(key) { return key; }\n"
		. "var _n = 0; function createNode(type, props, children) { return { _id: 'n' + (++_n), type: type, props: props || {}, children: children || [] }; }\n"
		. pg_test_designer_family_cut('    // ── Block families in their own files', '    // ── Block stylesheet')
		. "var _SD_BLOCK_CSS = {}, _SD_BLOCK_CSS_NEEDS = {};\n"
		. "var StyleDesigner = { registerBlockFamily: _sdRegisterBlockFamily };\n"
		. pg_test_designer_family_file('course') . "\n"
		. "var fam = _sdBlockFamilies()[0];\n"
		. "var trees = {}; fam.items.forEach(function (it) { trees[it.extra.componentType] = fam.build(it.extra.componentType); });\n"
		. "console.log(JSON.stringify({ prefix: fam.prefix, group: fam.group.title, labels: fam.items.map(function (it) { return it.label; }), trees: trees }));\n";
	$file = tempnam(sys_get_temp_dir(), 'pgcourseblock');
	file_put_contents($file, $script);
	$out = array();
	exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	unlink($file);
	pg_assert_same(0, $code, implode("\n", $out));
	return $got = ($code === 0 ? json_decode((string) end($out), true) : null);
}

function test_designer_blocks_course_palette()
{
	$got = _test_course_block_trees();
	if ($got === null) {
		pg_assert_true(true, 'Node is not installed: the run is skipped');
		return;
	}
	pg_assert_same('course-', $got['prefix']);
	pg_assert_same('Courses', $got['group'], 'its own palette group');
	pg_assert_same(array('course-done', 'course-progress', 'course-lessons', 'course-mine'), array_keys($got['trees']));
}

// (Z8, Z11) Printed by the site's renderer: the mark and the parts the
// script fills.
function test_designer_blocks_course_printed()
{
	$got = _test_course_block_trees();
	if ($got === null) {
		return;
	}
	$want = array(
		'course-done'     => array('done', array('data-pg-course-press', 'data-pg-course-status')),
		'course-progress' => array('progress', array('data-pg-course-name', 'data-pg-course-bar', 'data-pg-course-fill', 'data-pg-course-count')),
		'course-lessons'  => array('lessons', array('data-pg-course-name', 'data-pg-course-list', 'data-pg-course-item', 'data-pg-course-link', 'data-pg-course-state', 'data-pg-course-next')),
		'course-mine'     => array('mine', array('data-pg-course-list', 'data-pg-course-item', 'data-pg-course-link', 'data-pg-course-state', 'data-pg-course-empty')),
	);
	foreach ($want as $ctype => $w) {
		$html = _render_tree_node($got['trees'][$ctype]);
		pg_assert_true((bool) preg_match('/^<section[^>]*\sdata-pg-course="' . $w[0] . '"[^>]*\shidden(?=[\s=>])/', $html), $ctype . ' is a hidden section with its mark');
		pg_assert_true(pg_course_blocks_on_page('<body>' . $html . '</body>'), $ctype . ': pg_course_blocks_inject() finds it');
		foreach ($w[1] as $mark) {
			pg_assert_true((bool) preg_match('/\s' . $mark . '(?=[\s=>])/', $html), $ctype . ' carries ' . $mark);
		}
		foreach (array('<script', ' onclick', ' style=') as $never) {
			pg_assert_false(stripos($html, $never) !== false, $ctype . ': no ' . trim($never));
		}
		pg_assert_false((bool) preg_match('/<section[^>]*class="[^"]*\bd-(flex|block|grid)\b/', $html), $ctype . ': no display utility on the hidden root');
	}
	$done = _render_tree_node($got['trees']['course-done']);
	pg_assert_true((bool) preg_match('/<button\b[^>]*type="button"[^>]*>Mark as done</', $done), 'a real button');
	pg_assert_contains('role="status"', $done, 'the press is spoken');
	$progress = _render_tree_node($got['trees']['course-progress']);
	pg_assert_contains('role="progressbar"', $progress);
	pg_assert_contains('aria-valuemax="100"', $progress);
	pg_assert_true((bool) preg_match('/<ol\b[^>]*data-pg-course-list/', _render_tree_node($got['trees']['course-lessons'])), 'the lessons in their order');
}

// The canvas draws the hidden roots; the Preview loads the script.
function test_designer_blocks_course_canvas()
{
	$core = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	pg_assert_contains("'[data-pg-course][hidden] { display:block !important; }'", $core, 'the canvas shows the hidden root');
	pg_assert_contains("{ test: /\\sdata-pg-course(?=[\\s=>\\/])/i, file: 'assets/js/pg_course.js' }", $core, 'the Preview\'s twin row');
	pg_assert_same('/\sdata-pg-course(?=[\s=>\/])/i', pg_course_blocks_pattern(), 'the same expression on the server');
	foreach (array('fetch(', 'addEventListener', 'onclick') as $never) {
		pg_assert_false(strpos(pg_test_designer_family_file('course'), $never) !== false, 'the block file holds no ' . $never);
	}
}

// The script's pure helpers.
function test_course_script_texts()
{
	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);
	if ($code !== 0) {
		pg_assert_true(true, 'Node is not installed: the run is skipped');
		return;
	}
	$file = PG_FUNCTIONS_DIR . '/assets/js/pg_course.js';
	$js = "var c = require(" . json_encode($file) . ");\n"
		. "var labels = { 'Progress': '%2 dersten %1 tanesi', 'Opens on': '%1 tarihinde' };\n"
		. "console.log(JSON.stringify([c.countText(labels, { done: 4, total: 12 }), c.countText({}, { complete: 1 }), c.percent({ done: 1, total: 3 }), c.percent({ done: 0, total: 0 }),\n"
		. "  c.stateText(labels, { state: 'opens', opens_on: '2026-11-17' }, 'en-US'), c.stateText({}, { state: 'locked' }), c.day('bad', 'en')]));\n";
	$tmp = tempnam(sys_get_temp_dir(), 'pgcoursejs');
	file_put_contents($tmp, $js);
	$out = array();
	exec('node ' . escapeshellarg($tmp) . ' 2>&1', $out, $code);
	unlink($tmp);
	pg_assert_same(0, $code, implode("\n", $out));
	pg_assert_same(array('12 dersten 4 tanesi', 'Complete', 33, 0, 'November 17, 2026 tarihinde', 'Locked', 'bad'), json_decode((string) end($out), true));
}
