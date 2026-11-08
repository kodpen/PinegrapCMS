<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Ferry block family (assets/js/style_designer_blocks_ferry.js):
 * the core's registration call and helper kit are cut out of
 * style_designer.src.js and run under Node with the family file (skipped on
 * a machine without Node). Every block draws a section with one h2 and no
 * skipped heading level, no fixed colour class, every {var} slot filled, an
 * entrance the interaction normalizer keeps and no fixed year; a sailing's
 * status always carries an icon and a word, every table head cell names its
 * scope, and a time carries its machine-readable value.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function designer_blocks_ferry_test_file()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer_blocks_ferry.js');
}

// The core between $from and the first $to after it, asserted found.
function designer_blocks_ferry_test_cut($from, $to)
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
function designer_blocks_ferry_test_run()
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
		. designer_blocks_ferry_test_cut('    function _sdT(key, vars) {', "\n    }\n") . "\n    }\n"
		. "var _ids = 0;\n"
		. "function createNode(type, props, children) { return { id: 'n' + (++_ids), type: type, props: props || {}, children: children || [] }; }\n"
		. designer_blocks_ferry_test_cut('    // ── Block families in their own files', '    // ── Block stylesheet')
		. designer_blocks_ferry_test_cut('    function _sdFxSpec() {', '    // The data-pg-fx-* attributes')
		. "var _SD_BLOCK_CSS = {}, _SD_BLOCK_CSS_NEEDS = {};\n"
		. "var StyleDesigner = { registerBlockFamily: _sdRegisterBlockFamily };\n"
		. designer_blocks_ferry_test_file() . "\n"
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
		. "report.unknown = fam.build('ferry-none');\n"
		. "console.log(JSON.stringify(report));\n";

	$file = tempnam(sys_get_temp_dir(), 'pgfry');
	file_put_contents($file, $script);
	$out = array();
	exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	unlink($file);
	pg_assert_same(0, $code, implode("\n", $out));
	return $got = json_decode((string) end($out), true);
}

function test_designer_blocks_ferry_family_registers()
{
	$got = designer_blocks_ferry_test_run();
	if ($got === null) {
		pg_assert_true(true, 'Node is not installed: the run is skipped');
		return;
	}
	pg_assert_same('ferry-', $got['prefix'], 'the family\'s componentType prefix');
	pg_assert_same('Ferry', $got['group'], 'the family has its own palette group');
	pg_assert_same(array('ferry-departures', 'ferry-timetable', 'ferry-crossings', 'ferry-fares'), array_keys($got['blocks']), 'the four blocks');
	pg_assert_same(null, $got['unknown'], 'an unknown type draws nothing');
}

function test_designer_blocks_ferry_blocks_draw_sound_trees()
{
	$got = designer_blocks_ferry_test_run();
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
		pg_assert_same(array(), array_values(preg_grep('/^(fy|pfy)-/', $b['classes'])), $ctype . ' writes no template class');
		pg_assert_same(array(), array_values(preg_grep('/\\{var/', $b['texts'])), $ctype . ' fills every {var} slot');
		pg_assert_same(array(), array_values(preg_grep('/\\b(19|20)\\d\\d\\b/', $b['texts'])), $ctype . ' names no year');
		pg_assert_true(count($b['fx']) >= 1, $ctype . ' has an entrance');
		pg_assert_same(array(), array_values(array_diff($b['fx'], array('ok'))), $ctype . '\'s entrances survive the normalizer');
		pg_assert_same($b['cells'], count($b['scopes']), $ctype . ': every head cell names its scope');
		foreach ($b['times'] as $t) {
			pg_assert_true((bool) preg_match('/^(\\d\\d:\\d\\d)=\\1$/', $t), $ctype . ': a time carries its value: ' . $t);
		}
	}

	$board = $got['blocks']['ferry-departures'];
	pg_assert_same(array(array('name' => 'data-bs-theme', 'value' => 'dark')), $board['attrs'], 'the departure board is a dark band');
	pg_assert_same(array(), array_values(array_diff(array('bg-body', 'text-body'), $board['classes'])), 'the dark band paints its own surface and text');
	pg_assert_same(5, count($board['badges']), 'a status pill per sailing');
	pg_assert_same(array(), array_values(array_diff($board['badges'], array('icon+heading'))), 'every status says it with an icon and a word');
	pg_assert_same(5, count(preg_grep('/^th:row$/', $board['scopes'])), 'each sailing\'s time heads its row');
	pg_assert_true(in_array('Now leaving at 11:20', $board['texts'], true), 'a delay names the new time');

	$timetable = $got['blocks']['ferry-timetable'];
	pg_assert_same(2, count(array_keys($timetable['headings'], 'h3', true)), 'a card per direction');
	pg_assert_same(20, count($timetable['times']), 'every departure is a time');
	pg_assert_true(in_array('Departures from Pine Bay', $timetable['texts'], true), 'a direction names its port');

	$strip = $got['blocks']['ferry-crossings'];
	pg_assert_same(4, count(array_keys($strip['headings'], 'h3', true)), 'a strip per route');
	pg_assert_same(4, count(array_keys($strip['classes'], 'pg-ferry-wake', true)), 'each route draws its wake');
	pg_assert_true(in_array('Seabreeze · 10 sailings a day', $strip['texts'], true), 'a route names its ship and sailings');

	$fares = $got['blocks']['ferry-fares'];
	pg_assert_same(3, count(preg_grep('/^th:rowgroup$/', $fares['scopes'])), 'three groups of fares');
	pg_assert_same(8, count(preg_grep('/^th:row$/', $fares['scopes'])), 'a row per traveller and vehicle');
	pg_assert_same(3, count(preg_grep('/^th:col$/', $fares['scopes'])), 'a column per crossing length');
	pg_assert_true(in_array('Not offered', $fares['texts'], true), 'a fare a crossing does not offer says so in words');
}

// The rules the blocks need are read from the family file by the PHP
// stylesheet reader, so a template can ship them.
function test_designer_blocks_ferry_rules_reach_the_stylesheet()
{
	$part = _pg_designer_block_css_parse(designer_blocks_ferry_test_file());
	pg_assert_same(array('pg-ferry-figures', 'pg-ferry-wake'), array_keys($part['rules']), 'the family\'s rules');
	pg_assert_same(array('pg-ferry-wake'), $part['needs']['ferry-crossings'], 'the crossing strip needs the wake');
	$source = _pg_designer_block_css_source();
	pg_assert_true(isset($source['rules']['pg-ferry-wake']), 'the block stylesheet holds the family\'s rules');
	foreach ($part['rules'] as $css) {
		pg_assert_true((bool) preg_match('/var\\(--bs-[a-z-]+\\)|font-variant-numeric/', $css), 'a rule colours with a theme variable: ' . $css);
	}
}
