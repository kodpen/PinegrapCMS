<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Pharmacy block family
 * (assets/js/style_designer_blocks_pharmacy.js): the core's registration
 * call and helper kit are cut out of style_designer.src.js and run under
 * Node with the family file (skipped on a machine without Node). Every
 * block draws a section with one h2 and no skipped heading level, no fixed
 * colour class, every {var} slot filled, no year and no motion (a quiet
 * sector); a reading's meaning always carries an icon and a word, and every
 * table head cell names its scope.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function designer_blocks_pharmacy_test_file()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer_blocks_pharmacy.js');
}

// The core between $from and the first $to after it, asserted found.
function designer_blocks_pharmacy_test_cut($from, $to)
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
function designer_blocks_pharmacy_test_run()
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
		. designer_blocks_pharmacy_test_cut('    function _sdT(key, vars) {', "\n    }\n") . "\n    }\n"
		. "var _ids = 0;\n"
		. "function createNode(type, props, children) { return { id: 'n' + (++_ids), type: type, props: props || {}, children: children || [] }; }\n"
		. designer_blocks_pharmacy_test_cut('    // ── Block families in their own files', '    // ── Block stylesheet')
		. designer_blocks_pharmacy_test_cut('    function _sdFxSpec() {', '    // The data-pg-fx-* attributes')
		. "var _SD_BLOCK_CSS = {}, _SD_BLOCK_CSS_NEEDS = {};\n"
		. "var StyleDesigner = { registerBlockFamily: _sdRegisterBlockFamily };\n"
		. designer_blocks_pharmacy_test_file() . "\n"
		. "var fam = _sdBlockFamilies()[0];\n"
		. "var report = { prefix: fam.prefix, group: fam.group.title, blocks: {} };\n"
		. "fam.items.forEach(function (it) {\n"
		. "  var ct = it.extra.componentType, root = fam.build(ct);\n"
		. "  var b = { root: [root.type, root.props.tag], attrs: root.props._attrs || [], classes: [], headings: [], texts: [], fx: [], badges: [], scopes: [], times: [], cells: 0 };\n"
		. "  (function walk(n) {\n"
		. "    var p = n.props;\n"
		. "    String(p.cssClass || '').split(/\\s+/).forEach(function (c) { if (c) b.classes.push(c); });\n"
		. "    if (p.text) b.texts.push(p.text);\n"
		. "    if (p.alt) b.texts.push(p.alt);\n"
		. "    var tag = p.tag || '';\n"
		. "    if (/^h[1-6]$/.test(tag)) b.headings.push(tag);\n"
		. "    (p._attrs || []).forEach(function (a) { if (a.name === 'scope') b.scopes.push(tag + ':' + a.value); if (a.name === 'datetime') b.times.push(a.value + '=' + p.text); });\n"
		. "    if (tag === 'th') b.cells++;\n"
		. "    if (p._fx) b.fx.push(_sdFxNormalize(p._fx) ? 'ok' : 'dropped');\n"
		. "    if (/(^| )badge( |$)/.test(p.cssClass || '') && n.children.length) {\n"
		. "      b.badges.push(n.children.map(function (k) { return k.props.contentType || k.props.tag; }).join('+'));\n"
		. "    }\n"
		. "    n.children.forEach(walk);\n"
		. "  })(root);\n"
		. "  report.blocks[ct] = b;\n"
		. "});\n"
		. "report.unknown = fam.build('pharmacy-none');\n"
		. "console.log(JSON.stringify(report));\n";

	$file = tempnam(sys_get_temp_dir(), 'pgrx');
	file_put_contents($file, $script);
	$out = array();
	exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	unlink($file);
	pg_assert_same(0, $code, implode("\n", $out));
	return $got = json_decode((string) end($out), true);
}

function test_designer_blocks_pharmacy_family_registers()
{
	$got = designer_blocks_pharmacy_test_run();
	if ($got === null) {
		pg_assert_true(true, 'Node is not installed: the run is skipped');
		return;
	}
	pg_assert_same('pharmacy-', $got['prefix'], 'the family\'s componentType prefix');
	pg_assert_same('Pharmacy', $got['group'], 'the family has its own palette group');
	pg_assert_same(array('pharmacy-duty', 'pharmacy-readings', 'pharmacy-vaccines', 'pharmacy-schedule'), array_keys($got['blocks']), 'the four blocks');
	pg_assert_same(null, $got['unknown'], 'an unknown type draws nothing');
}

function test_designer_blocks_pharmacy_blocks_draw_sound_trees()
{
	$got = designer_blocks_pharmacy_test_run();
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
		pg_assert_same(array(), array_values(preg_grep('/^(rx|prx)-/', $b['classes'])), $ctype . ' writes no template class');
		pg_assert_same(array(), array_values(preg_grep('/\\{var/', $b['texts'])), $ctype . ' fills every {var} slot');
		pg_assert_same(array(), array_values(preg_grep('/\\b(19|20)\\d\\d\\b/', $b['texts'])), $ctype . ' names no year');
		pg_assert_same(array(), $b['fx'], $ctype . ' stays still');
		pg_assert_same($b['cells'], count($b['scopes']), $ctype . ': every head cell names its scope');
	}

	$duty = $got['blocks']['pharmacy-duty'];
	pg_assert_same(3, count(array_keys($duty['headings'], 'h3', true)) - 1, 'three answers beside the night window');
	pg_assert_true(in_array('alert-danger', $duty['classes'], true) && in_array('Call 112', $duty['texts'], true), 'the emergency line calls 112');

	$readings = $got['blocks']['pharmacy-readings'];
	pg_assert_same(6, count($readings['badges']), 'a meaning per band');
	pg_assert_same(array(), array_values(array_diff($readings['badges'], array('icon+heading'))), 'every meaning says it with an icon and a word');
	pg_assert_same(6, count(preg_grep('/^th:row$/', $readings['scopes'])), 'each band heads its row');
	pg_assert_true(in_array('120 / 80 to 139 / 89', $readings['texts'], true), 'a range fills both slots');

	$vaccines = $got['blocks']['pharmacy-vaccines'];
	pg_assert_same(4, count(preg_grep('/^th:row$/', $vaccines['scopes'])), 'a row per vaccine');
	pg_assert_same(4, count(preg_grep('/^th:col$/', $vaccines['scopes'])), 'vaccine, for whom, doses, when');

	$schedule = $got['blocks']['pharmacy-schedule'];
	pg_assert_same(5, count(preg_grep('/^th:col$/', $schedule['scopes'])), 'the medicine and four times of day');
	pg_assert_true(in_array('No dose', $schedule['texts'], true), 'no dose says so in words');
}

// The rule the blocks need is read from the family file by the PHP
// stylesheet reader, so a template can ship it.
function test_designer_blocks_pharmacy_rules_reach_the_stylesheet()
{
	$part = _pg_designer_block_css_parse(designer_blocks_pharmacy_test_file());
	pg_assert_same(array('pg-pharmacy-figures'), array_keys($part['rules']), 'the family\'s rule');
	pg_assert_same(array('pharmacy-readings', 'pharmacy-vaccines', 'pharmacy-schedule'), array_keys($part['needs']), 'the tables need it');
	$source = _pg_designer_block_css_source();
	pg_assert_true(isset($source['rules']['pg-pharmacy-figures']), 'the block stylesheet holds the family\'s rule');
}
