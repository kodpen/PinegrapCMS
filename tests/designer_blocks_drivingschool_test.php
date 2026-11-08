<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Driving School block family
 * (assets/js/style_designer_blocks_drivingschool.js): the core's
 * registration call and helper kit are cut out of style_designer.src.js and
 * run under Node with the family file (skipped on a machine without Node).
 * Every block draws a section with one h2 and no skipped heading level, no
 * fixed colour or template class, numbers filled into their {var:n} slots,
 * an entrance the interaction normalizer keeps, and no fixed year; the
 * licence cards carry a plate and their facts, the road signs stay
 * decorative, the test sheet is a table with header scopes and a status
 * said with an icon and a word.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function designer_blocks_drivingschool_test_file()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer_blocks_drivingschool.js');
}

// The core between $from and the first $to after it, asserted found.
function designer_blocks_drivingschool_test_cut($from, $to)
{
	static $js = null;
	if ($js === null) {
		$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	}
	$start = strpos($js, $from);
	$end = $start === false ? false : strpos($js, $to, $start + strlen($from));
	pg_assert_true($start !== false && $end !== false, 'the core holds ' . trim($from));
	return ($start === false || $end === false) ? '' : substr($js, $start, $end - $start);
}

// Every block's tree, drawn under Node, flattened to the facts the tests
// read; null without Node.
function designer_blocks_drivingschool_test_run()
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
		. designer_blocks_drivingschool_test_cut('    function _sdT(key, vars) {', "\n    }\n") . "\n    }\n"
		. "var _ids = 0;\n"
		. "function createNode(type, props, children) { return { id: 'n' + (++_ids), type: type, props: props || {}, children: children || [] }; }\n"
		. designer_blocks_drivingschool_test_cut('    // ── Block families in their own files', '    // ── Block stylesheet')
		. designer_blocks_drivingschool_test_cut('    function _sdFxSpec() {', '    // The data-pg-fx-* attributes')
		. "var _SD_BLOCK_CSS = {}, _SD_BLOCK_CSS_NEEDS = {};\n"
		. "var StyleDesigner = { registerBlockFamily: _sdRegisterBlockFamily };\n"
		. designer_blocks_drivingschool_test_file() . "\n"
		. "var fam = _sdBlockFamilies()[0];\n"
		. "var report = { prefix: fam.prefix, group: fam.group.title, blocks: {} };\n"
		. "fam.items.forEach(function (it) {\n"
		. "  var ct = it.extra.componentType, root = fam.build(ct);\n"
		. "  var b = { root: [root.type, root.props.tag], attrs: root.props._attrs || [], classes: [], headings: [], texts: [], fx: [], badges: [], hidden: 0, scopes: [] };\n"
		. "  (function walk(n) {\n"
		. "    var p = n.props;\n"
		. "    String(p.cssClass || '').split(/\\s+/).forEach(function (c) { if (c) b.classes.push(c); });\n"
		. "    if (p.text) b.texts.push(p.text);\n"
		. "    if (p.alt) b.texts.push(p.alt);\n"
		. "    var tag = p.tag || '';\n"
		. "    if (/^h[1-6]$/.test(tag)) b.headings.push(tag);\n"
		. "    (p._attrs || []).forEach(function (a) {\n"
		. "      if (a.name === 'aria-hidden' && a.value === 'true') b.hidden++;\n"
		. "      if (a.name === 'scope') b.scopes.push(a.value);\n"
		. "    });\n"
		. "    if (p._fx) b.fx.push(_sdFxNormalize(p._fx) ? 'ok' : 'dropped');\n"
		. "    if (/(^| )badge( |$)/.test(p.cssClass || '') && n.children.length) {\n"
		. "      b.badges.push(n.children.map(function (k) { return k.props.contentType || k.props.tag; }).join('+'));\n"
		. "    }\n"
		. "    n.children.forEach(walk);\n"
		. "  })(root);\n"
		. "  report.blocks[ct] = b;\n"
		. "});\n"
		. "report.unknown = fam.build('drivingschool-none');\n"
		. "console.log(JSON.stringify(report));\n";

	$file = tempnam(sys_get_temp_dir(), 'pgdrv');
	file_put_contents($file, $script);
	$out = array();
	exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	unlink($file);
	pg_assert_same(0, $code, implode("\n", $out));
	return $got = json_decode((string) end($out), true);
}

function test_designer_blocks_drivingschool_family_registers()
{
	$got = designer_blocks_drivingschool_test_run();
	if ($got === null) {
		pg_assert_true(true, 'Node is not installed: the run is skipped');
		return;
	}
	pg_assert_same('drivingschool-', $got['prefix'], 'the family\'s componentType prefix');
	pg_assert_same('Driving School', $got['group'], 'the family has its own palette group');
	pg_assert_same(array('drivingschool-licences', 'drivingschool-signs', 'drivingschool-test-sheet'), array_keys($got['blocks']), 'the three blocks');
	pg_assert_same(null, $got['unknown'], 'an unknown type draws nothing');
}

function test_designer_blocks_drivingschool_blocks_draw_sound_trees()
{
	$got = designer_blocks_drivingschool_test_run();
	if ($got === null) {
		pg_assert_true(true, 'Node is not installed: the run is skipped');
		return;
	}
	$fixed = array('bg-white', 'bg-light', 'bg-dark', 'text-white', 'text-white-50', 'text-light', 'text-dark', 'text-muted', 'text-bg-light', 'text-bg-dark', 'btn-light', 'btn-dark', 'btn-outline-light', 'link-light', 'navbar-dark');
	foreach ($got['blocks'] as $ctype => $b) {
		pg_assert_same(array('semantic', 'section'), $b['root'], $ctype . ' is a section');
		pg_assert_same(1, count(array_keys($b['headings'], 'h2', true)), $ctype . ' has one h2');
		$levels = array_values(array_unique(array_map(function ($h) { return (int) substr($h, 1); }, $b['headings'])));
		sort($levels);
		pg_assert_same(range(2, max($levels)), $levels, $ctype . ' skips no heading level');
		pg_assert_same(array(), array_values(array_intersect($b['classes'], $fixed)), $ctype . ' writes no fixed colour class');
		pg_assert_same(array(), array_values(preg_grep('/^(pdr|drv)-/', $b['classes'])), $ctype . ' writes no template class');
		pg_assert_same(array(), array_values(preg_grep('/\\{var/', $b['texts'])), $ctype . ' fills every {var} slot');
		pg_assert_same(array(), array_values(preg_grep('/\\b(19|20)\\d\\d\\b/', $b['texts'])), $ctype . ' names no year');
		pg_assert_same(array('ok'), $b['fx'], $ctype . ' has one entrance the normalizer keeps');
	}

	$licences = $got['blocks']['drivingschool-licences'];
	pg_assert_same(4, count(array_keys($licences['classes'], 'pg-drv-plate', true)), 'a number plate per licence class');
	pg_assert_same(4, count(array_keys($licences['headings'], 'h3', true)), 'four licence cards');
	foreach (array('B', 'A2', 'C1', 'BE', '18 years old', 'From 20 lessons', 'Manual or automatic', 'Holds class B') as $text) {
		pg_assert_true(in_array($text, $licences['texts'], true), 'a licence card says ' . $text);
	}

	$signs = $got['blocks']['drivingschool-signs'];
	pg_assert_same(6, count(array_keys($signs['classes'], 'pg-drv-sign', true)), 'a road sign per theory topic');
	pg_assert_same(6, $signs['hidden'], 'every road sign is hidden from screen readers');
	pg_assert_same(array(), array_values(array_diff(array('pg-drv-sign-warn', 'pg-drv-sign-ban', 'text-bg-primary'), $signs['classes'])), 'warning, ban and information signs');
	pg_assert_true(in_array('43 of 50', $signs['texts'], true), 'the pass mark fills both numbers');

	$sheet = $got['blocks']['drivingschool-test-sheet'];
	pg_assert_same(array('col', 'col', 'col', 'col', 'row', 'row', 'row', 'row', 'row', 'row'), $sheet['scopes'], 'the fault table names its column and row heads');
	pg_assert_same(array('icon+heading'), $sheet['badges'], 'the result says it with an icon and a word');
	pg_assert_true(in_array('This sheet: 4 driving faults of 15 allowed, none serious or dangerous.', $sheet['texts'], true), 'two numbers fill their own slots');
	pg_assert_true(in_array('pg-drv-sheet', $sheet['classes'], true), 'the sheet keeps tabular figures');
}

// The rules the blocks need are read from the family file by the PHP
// stylesheet reader, so a template can ship them.
function test_designer_blocks_drivingschool_rules_reach_the_stylesheet()
{
	$part = _pg_designer_block_css_parse(designer_blocks_drivingschool_test_file());
	pg_assert_same(array('pg-drv-plate', 'pg-drv-sign', 'pg-drv-sign-warn', 'pg-drv-sign-ban', 'pg-drv-sheet'), array_keys($part['rules']), 'the family\'s rules');
	pg_assert_same(array('pg-drv-sign', 'pg-drv-sign-warn', 'pg-drv-sign-ban'), $part['needs']['drivingschool-signs'], 'the theory topics need the sign shapes');
	$source = _pg_designer_block_css_source();
	pg_assert_true(isset($source['rules']['pg-drv-plate']), 'the block stylesheet holds the family\'s rules');
	foreach (array('pg-drv-plate', 'pg-drv-sign-warn', 'pg-drv-sign-ban') as $key) {
		pg_assert_true((bool) preg_match('/var\\(--bs-[a-z-]+\\)/', $part['rules'][$key]), 'a coloured rule uses a theme variable: ' . $key);
	}
}
