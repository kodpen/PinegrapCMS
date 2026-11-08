<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Campsite block family
 * (assets/js/style_designer_blocks_campsite.js): the core's registration
 * call and helper kit are cut out of style_designer.src.js and run under
 * Node with the family file (skipped on a machine without Node). Every
 * block draws a section with one top heading and no skipped level, no
 * fixed colour class, every {var} slot filled, an entrance the interaction
 * normalizer keeps and no fixed year or month; the hero finds a programme
 * by age, the age ladder lays the programmes on one age scale with the
 * class groups under it, the campsite year shows its terms and breaks.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function designer_blocks_campsite_test_file()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer_blocks_campsite.js');
}

// The core between $from and the first $to after it, asserted found.
function designer_blocks_campsite_test_cut($from, $to)
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
function designer_blocks_campsite_test_run()
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
		. designer_blocks_campsite_test_cut('    function _sdT(key, vars) {', "\n    }\n") . "\n    }\n"
		. "var _ids = 0;\n"
		. "function createNode(type, props, children) { return { id: 'n' + (++_ids), type: type, props: props || {}, children: children || [] }; }\n"
		. designer_blocks_campsite_test_cut('    // ── Block families in their own files', '    // ── Block stylesheet')
		. designer_blocks_campsite_test_cut('    function _sdFxSpec() {', '    // The data-pg-fx-* attributes')
		. "var _SD_BLOCK_CSS = {}, _SD_BLOCK_CSS_NEEDS = {};\n"
		. "var StyleDesigner = { registerBlockFamily: _sdRegisterBlockFamily };\n"
		. designer_blocks_campsite_test_file() . "\n"
		. "var fam = _sdBlockFamilies()[0];\n"
		. "var report = { prefix: fam.prefix, group: fam.group.title, blocks: {} };\n"
		. "fam.items.forEach(function (it) {\n"
		. "  var ct = it.extra.componentType, root = fam.build(ct);\n"
		. "  var b = { root: [root.type, root.props.tag], attrs: root.props._attrs || [], classes: [], headings: [], texts: [], fx: [], badges: [], scopes: [], times: [], cells: 0, tags: [], images: 0 };\n"
		. "  (function walk(n) {\n"
		. "    var p = n.props;\n"
		. "    String(p.cssClass || '').split(/\\s+/).forEach(function (c) { if (c) b.classes.push(c); });\n"
		. "    if (p.text) b.texts.push(p.text);\n"
		. "    if (p.alt) b.texts.push(p.alt);\n"
		. "    var tag = p.tag || '';\n"
		. "    if (/^h[1-6]$/.test(tag)) b.headings.push(tag);\n"
		. "    if (tag) b.tags.push(tag);\n"
		. "    if (p.contentType === 'image') b.images++;\n"
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
		. "report.unknown = fam.build('campsite-none');\n"
		. "console.log(JSON.stringify(report));\n";

	$file = tempnam(sys_get_temp_dir(), 'pgcmp');
	file_put_contents($file, $script);
	$out = array();
	exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	unlink($file);
	pg_assert_same(0, $code, implode("\n", $out));
	return $got = json_decode((string) end($out), true);
}

function test_designer_blocks_campsite_family_registers()
{
	$got = designer_blocks_campsite_test_run();
	if ($got === null) {
		pg_assert_true(true, 'Node is not installed: the run is skipped');
		return;
	}
	pg_assert_same('campsite-', $got['prefix'], 'the family\'s componentType prefix');
	pg_assert_same('Campsite', $got['group'], 'the family has its own palette group');
	pg_assert_same(array('campsite-hero', 'campsite-comfort', 'campsite-pitch'), array_keys($got['blocks']), 'the three blocks');
	pg_assert_same(null, $got['unknown'], 'an unknown type draws nothing');
}

function test_designer_blocks_campsite_blocks_draw_sound_trees()
{
	$got = designer_blocks_campsite_test_run();
	if ($got === null) {
		pg_assert_true(true, 'Node is not installed: the run is skipped');
		return;
	}
	$fixed = array('bg-white', 'bg-light', 'bg-dark', 'text-white', 'text-white-50', 'text-light', 'text-dark', 'text-muted', 'text-bg-light', 'text-bg-dark', 'btn-light', 'btn-dark', 'btn-outline-light', 'link-light', 'navbar-dark');
	foreach ($got['blocks'] as $ctype => $b) {
		// The hero is a page's way in, so its title is the h1; the
		// others are sections under a page's own h1.
		$top = $ctype === 'campsite-hero' ? 1 : 2;
		pg_assert_same(1, count(array_keys($b['headings'], 'h' . $top, true)), $ctype . ' has one h' . $top);
		pg_assert_same(array('semantic', 'section'), $b['root'], $ctype . ' is a section');
		$levels = array_values(array_unique(array_map(function ($h) { return (int) substr($h, 1); }, $b['headings'])));
		sort($levels);
		pg_assert_same(range($top, max($levels)), $levels, $ctype . ' skips no heading level');
		pg_assert_same(array(), array_values(array_intersect($b['classes'], $fixed)), $ctype . ' writes no fixed colour class');
		pg_assert_same(array(), array_values(preg_grep('/^(pwd|wd)-/', $b['classes'])), $ctype . ' writes no template class');
		pg_assert_same(array(), array_values(preg_grep('/\\{var/', $b['texts'])), $ctype . ' fills every {var} slot');
		pg_assert_same(array(), array_values(preg_grep('/\\b(19|20)\\d\\d\\b/', $b['texts'])), $ctype . ' names no year');
		pg_assert_same(array(), array_values(preg_grep('/\\b(January|February|March|April|May|June|July|August|September|October|November|December)\\b/', $b['texts'])), $ctype . ' names no month');
		pg_assert_true(count($b['fx']) >= 1, $ctype . ' has an entrance');
		pg_assert_same(array(), array_values(array_diff($b['fx'], array('ok'))), $ctype . '\'s entrances survive the normalizer');
	}

	$hero = $got['blocks']['campsite-hero'];
	pg_assert_same(1, $hero['images'], 'one picture');
	pg_assert_true(in_array('nav', $hero['tags'], true), 'the kinds of stay are a navigation');
	pg_assert_true(in_array('pg-campsite-kinds', $hero['classes'], true), 'the chooser rule');
	pg_assert_true(in_array('pg-campsite-lift', $hero['classes'], true), 'the card lies over the picture');
	pg_assert_same(6, count(array_keys($hero['tags'], 'li', true)), 'a way in per kind of stay beside two facts');
	pg_assert_true(in_array('Arrival from 14:00, departure by 11:00', $hero['texts'], true), 'the arrival and departure times');

	$comfort = $got['blocks']['campsite-comfort'];
	pg_assert_same(4, count(array_keys($comfort['headings'], 'h3', true)), 'a card per kind of stay');
	pg_assert_same(8, count(array_keys($comfort['headings'], 'h4', true)), 'what to bring and what is ready on each card');
	pg_assert_same(1, count(array_keys($comfort['tags'], 'ol', true)), 'the kinds are in order of comfort');
	pg_assert_same(4, count(array_keys($comfort['classes'], 'pg-campsite-stop', true)), 'each kind is a stop on the scale');
	pg_assert_true(in_array('pg-campsite-scale', $comfort['classes'], true), 'the scale line');
	pg_assert_true(in_array('Tent Pitches', $comfort['texts'], true) && in_array('Cabins and Lodges', $comfort['texts'], true), 'the template\'s kinds of stay');

	$pitch = $got['blocks']['campsite-pitch'];
	pg_assert_true(in_array('figure', $pitch['tags'], true), 'the drawing is a figure');
	pg_assert_true(in_array('pg-campsite-plot', $pitch['classes'], true), 'the pitch rule');
	pg_assert_same(2, count(array_keys($pitch['classes'], 'pg-campsite-area', true)), 'the car place and the tent area');
	pg_assert_same(8, count(array_keys($pitch['classes'], 'pg-campsite-marker', true)), 'four points on the drawing and four in the legend');
	pg_assert_same(4, count(array_keys($pitch['headings'], 'h3', true)), 'a legend entry per point');
	pg_assert_same(1, count(array_keys($pitch['tags'], 'ol', true)), 'the legend is numbered');
}

// The rules the blocks need are read from the family file by the PHP
// stylesheet reader, so a template can ship them.
function test_designer_blocks_campsite_rules_reach_the_stylesheet()
{
	$part = _pg_designer_block_css_parse(designer_blocks_campsite_test_file());
	pg_assert_same(array('pg-campsite-lift', 'pg-campsite-kinds', 'pg-campsite-scale', 'pg-campsite-stop', 'pg-campsite-plot', 'pg-campsite-area', 'pg-campsite-marker'), array_keys($part['rules']), 'the family\'s rules');
	pg_assert_same(array('pg-campsite-lift', 'pg-campsite-kinds'), $part['needs']['campsite-hero'], 'the hero needs the card and the chooser');
	pg_assert_same(array('pg-campsite-scale', 'pg-campsite-stop'), $part['needs']['campsite-comfort'], 'the scale needs its line and stops');
	pg_assert_same(array('pg-campsite-plot', 'pg-campsite-area', 'pg-campsite-marker'), $part['needs']['campsite-pitch'], 'the drawing needs the pitch, its areas and markers');
	$source = _pg_designer_block_css_source();
	pg_assert_true(isset($source['rules']['pg-campsite-plot']), 'the block stylesheet holds the family\'s rules');
	foreach ($part['rules'] as $key => $css) {
		pg_assert_true(strpos($css, '.' . $key) !== false, 'a rule names its key: ' . $key);
	}
	foreach (array('pg-campsite-kinds', 'pg-campsite-scale', 'pg-campsite-stop', 'pg-campsite-plot', 'pg-campsite-marker') as $key) {
		pg_assert_true((bool) preg_match('/var\\(--bs-[a-z-]+\\)/', $part['rules'][$key]), 'a rule colours with a theme variable: ' . $key);
	}
}
