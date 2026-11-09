<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Zoo block family (assets/js/style_designer_blocks_zoo.js):
 * the core's registration call and helper kit are cut out of
 * style_designer.src.js and run under Node with the family file (skipped on
 * a machine without Node). Every block draws a section with one h2 and no
 * skipped heading level, no fixed colour class, a kind or status pill that
 * always carries an icon and a word, numbers filled into their {var:n}
 * slots, interactions the normalizer keeps, and no fixed year in its sample
 * text.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function designer_blocks_zoo_test_file()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer_blocks_zoo.js');
}

// The core between $from and the first $to after it, asserted found.
function designer_blocks_zoo_test_cut($from, $to)
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
function designer_blocks_zoo_test_run()
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
		. designer_blocks_zoo_test_cut('    function _sdT(key, vars) {', "\n    }\n") . "\n    }\n"
		. "var _ids = 0;\n"
		. "function createNode(type, props, children) { return { id: 'n' + (++_ids), type: type, props: props || {}, children: children || [] }; }\n"
		. designer_blocks_zoo_test_cut('    // ── Block families in their own files', '    // ── Block stylesheet')
		. designer_blocks_zoo_test_cut('    function _sdFxSpec() {', '    // The data-pg-fx-* attributes')
		. "var _SD_BLOCK_CSS = {}, _SD_BLOCK_CSS_NEEDS = {};\n"
		. "var StyleDesigner = { registerBlockFamily: _sdRegisterBlockFamily };\n"
		. designer_blocks_zoo_test_file() . "\n"
		. "var fam = _sdBlockFamilies()[0];\n"
		. "var report = { prefix: fam.prefix, group: fam.group.title, blocks: {} };\n"
		. "fam.items.forEach(function (it) {\n"
		. "  var ct = it.extra.componentType, root = fam.build(ct);\n"
		. "  var b = { root: [root.type, root.props.tag], attrs: root.props._attrs || [], classes: [], headings: [], texts: [], fx: [], badges: [], current: [], tags: [] };\n"
		. "  (function walk(n) {\n"
		. "    var p = n.props;\n"
		. "    String(p.cssClass || '').split(/\\s+/).forEach(function (c) { if (c) b.classes.push(c); });\n"
		. "    if (p.text) b.texts.push(p.text);\n"
		. "    if (p.alt) b.texts.push(p.alt);\n"
		. "    var tag = p.tag || '';\n"
		. "    if (/^h[1-6]$/.test(tag)) b.headings.push(tag);\n"
		. "    if (tag) b.tags.push(tag);\n"
		. "    (p._attrs || []).forEach(function (a) { if (a.name === 'aria-current') b.current.push(tag + '=' + a.value); });\n"
		. "    if (p._fx) b.fx.push(_sdFxNormalize(p._fx) ? p._fx.effect : 'dropped');\n"
		. "    if (/(^| )badge( |$)/.test(p.cssClass || '') && n.children.length) {\n"
		. "      b.badges.push(n.children.map(function (k) { return k.props.contentType || k.props.tag; }).join('+'));\n"
		. "    }\n"
		. "    n.children.forEach(walk);\n"
		. "  })(root);\n"
		. "  report.blocks[ct] = b;\n"
		. "});\n"
		. "report.unknown = fam.build('zoo-none');\n"
		. "console.log(JSON.stringify(report));\n";

	$file = tempnam(sys_get_temp_dir(), 'pgzoo');
	file_put_contents($file, $script);
	$out = array();
	exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	unlink($file);
	pg_assert_same(0, $code, implode("\n", $out));
	return $got = json_decode((string) end($out), true);
}

function test_designer_blocks_zoo_family_registers()
{
	$got = designer_blocks_zoo_test_run();
	if ($got === null) {
		pg_assert_true(true, 'Node is not installed: the run is skipped');
		return;
	}
	pg_assert_same('zoo-', $got['prefix'], 'the family\'s componentType prefix');
	pg_assert_same('Zoo', $got['group'], 'the family has its own palette group');
	pg_assert_same(array('zoo-today', 'zoo-animal', 'zoo-map', 'zoo-conservation'), array_keys($got['blocks']), 'the four blocks');
	pg_assert_same(null, $got['unknown'], 'an unknown type draws nothing');
}

function test_designer_blocks_zoo_blocks_draw_sound_trees()
{
	$got = designer_blocks_zoo_test_run();
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
		pg_assert_same(array(), array_values(preg_grep('/^(pzo|zoo)-/', $b['classes'])), $ctype . ' writes no template class');
		pg_assert_same(array(), array_values(preg_grep('/\\{var/', $b['texts'])), $ctype . ' fills every {var} slot');
		pg_assert_same(array(), array_values(preg_grep('/\\b(19|20)\\d\\d\\b/', $b['texts'])), $ctype . ' names no year');
		pg_assert_same(array(), array_values(array_keys($b['fx'], 'dropped', true)), $ctype . '\'s interactions survive the normalizer');
		pg_assert_same(array(), array_values(array_diff($b['badges'], array('icon+heading', 'heading'))), $ctype . ' draws pills from an icon and a word');
	}

	$today = $got['blocks']['zoo-today'];
	pg_assert_same(5, count(array_keys($today['tags'], 'time', true)), 'a time element per slot');
	pg_assert_same(array('li=time'), $today['current'], 'the next slot is the current time');
	pg_assert_true(in_array('Habitat 7 · Polar Coast', $today['texts'], true), 'a slot names its habitat number and zone');
	pg_assert_same(array(), array_values(array_diff(array('Feeding', 'Keeper talk', 'Next up'), $today['texts'])), 'the kinds and the next slot are said in words');
	pg_assert_same(array('slide-up'), $today['fx'], 'the schedule rises in');

	$animal = $got['blocks']['zoo-animal'];
	pg_assert_same(array('scale'), $animal['fx'], 'the spotlight photo scales in on view');
	pg_assert_same(array('li=true'), $animal['current'], 'one status level is the animal\'s');
	pg_assert_same(array(), array_values(array_diff(array('Least Concern', 'Endangered', 'Extinct'), $animal['texts'])), 'every level has its name for screen readers');
	pg_assert_same(4, count(array_keys($animal['tags'], 'dt', true)), 'four facts in the fact sheet');

	$map = $got['blocks']['zoo-map'];
	pg_assert_same(13, count(array_keys($map['classes'], 'pg-zoo-pin', true)), 'a numbered pin per habitat');
	pg_assert_same(array(), array_values(array_diff(array('Savanna', 'Polar Coast', 'Rainforest', 'Aquarium'), $map['texts'])), 'each zone is named in words beside its colour');
	pg_assert_true(in_array('figure', $map['tags'], true), 'the map is a figure with its caption');

	$cons = $got['blocks']['zoo-conservation'];
	pg_assert_same(3, count(array_keys($cons['headings'], 'h3', true)), 'three projects');
	pg_assert_same(3, count($cons['badges']), 'a status pill per project');
	pg_assert_true(in_array('CR · Critically Endangered', $cons['texts'], true), 'the status says its code and its name');
}

// The rules the blocks need are read from the family file by the PHP
// stylesheet reader, so a template can ship them.
function test_designer_blocks_zoo_rules_reach_the_stylesheet()
{
	$part = _pg_designer_block_css_parse(designer_blocks_zoo_test_file());
	pg_assert_same(array('pg-zoo-time', 'pg-zoo-pin'), array_keys($part['rules']), 'the family\'s rules');
	pg_assert_same(array('pg-zoo-pin'), $part['needs']['zoo-map'], 'the map needs the pin rule');
	$source = _pg_designer_block_css_source();
	pg_assert_true(isset($source['rules']['pg-zoo-pin']), 'the block stylesheet holds the family\'s rules');
}
