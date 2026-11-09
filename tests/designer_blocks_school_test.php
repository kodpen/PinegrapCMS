<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the School block family
 * (assets/js/style_designer_blocks_school.js): the core's registration
 * call and helper kit are cut out of style_designer.src.js and run under
 * Node with the family file (skipped on a machine without Node). Every
 * block draws a section with one top heading and no skipped level, no
 * fixed colour class, every {var} slot filled, an entrance the interaction
 * normalizer keeps and no fixed year or month; the hero finds a programme
 * by age, the age ladder lays the programmes on one age scale with the
 * class groups under it, the school year shows its terms and breaks.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function designer_blocks_school_test_file()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer_blocks_school.js');
}

// The core between $from and the first $to after it, asserted found.
function designer_blocks_school_test_cut($from, $to)
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
function designer_blocks_school_test_run()
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
		. designer_blocks_school_test_cut('    function _sdT(key, vars) {', "\n    }\n") . "\n    }\n"
		. "var _ids = 0;\n"
		. "function createNode(type, props, children) { return { id: 'n' + (++_ids), type: type, props: props || {}, children: children || [] }; }\n"
		. designer_blocks_school_test_cut('    // ── Block families in their own files', '    // ── Block stylesheet')
		. designer_blocks_school_test_cut('    function _sdFxSpec() {', '    // The data-pg-fx-* attributes')
		. "var _SD_BLOCK_CSS = {}, _SD_BLOCK_CSS_NEEDS = {};\n"
		. "var StyleDesigner = { registerBlockFamily: _sdRegisterBlockFamily };\n"
		. designer_blocks_school_test_file() . "\n"
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
		. "report.unknown = fam.build('school-none');\n"
		. "console.log(JSON.stringify(report));\n";

	$file = tempnam(sys_get_temp_dir(), 'pgsch');
	file_put_contents($file, $script);
	$out = array();
	exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	unlink($file);
	pg_assert_same(0, $code, implode("\n", $out));
	return $got = json_decode((string) end($out), true);
}

function test_designer_blocks_school_family_registers()
{
	$got = designer_blocks_school_test_run();
	if ($got === null) {
		pg_assert_true(true, 'Node is not installed: the run is skipped');
		return;
	}
	pg_assert_same('school-', $got['prefix'], 'the family\'s componentType prefix');
	pg_assert_same('School', $got['group'], 'the family has its own palette group');
	pg_assert_same(array('school-hero', 'school-ages', 'school-year'), array_keys($got['blocks']), 'the three blocks');
	pg_assert_same(null, $got['unknown'], 'an unknown type draws nothing');
}

function test_designer_blocks_school_blocks_draw_sound_trees()
{
	$got = designer_blocks_school_test_run();
	if ($got === null) {
		pg_assert_true(true, 'Node is not installed: the run is skipped');
		return;
	}
	$fixed = array('bg-white', 'bg-light', 'bg-dark', 'text-white', 'text-white-50', 'text-light', 'text-dark', 'text-muted', 'text-bg-light', 'text-bg-dark', 'btn-light', 'btn-dark', 'btn-outline-light', 'link-light', 'navbar-dark');
	foreach ($got['blocks'] as $ctype => $b) {
		// The hero is a page's way in, so its title is the h1; the
		// others are sections under a page's own h1.
		$top = $ctype === 'school-hero' ? 1 : 2;
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

	$hero = $got['blocks']['school-hero'];
	pg_assert_same(1, $hero['images'], 'one picture');
	pg_assert_true(in_array('nav', $hero['tags'], true), 'the age finder is a navigation');
	pg_assert_same(5, count(array_keys($hero['tags'], 'li', true)), 'a way in per programme');
	pg_assert_true(in_array('pg-school-finder', $hero['classes'], true), 'the age finder rule');
	pg_assert_true(in_array('Ages 2 to 3', $hero['texts'], true), 'a programme is found by its ages');

	$ages = $got['blocks']['school-ages'];
	pg_assert_true(in_array('pg-school-ages', $ages['classes'], true), 'the age scale rule');
	pg_assert_same(5, count(array_keys($ages['headings'], 'h3', true)) - 1, 'a card per programme beside the class groups heading');
	pg_assert_same(1, count(array_keys($ages['tags'], 'ol', true)), 'the programmes are in age order');
	foreach (array('pg-school-from-2', 'pg-school-to-3', 'pg-school-from-6', 'pg-school-to-10') as $span) {
		pg_assert_true(in_array($span, $ages['classes'], true), 'a programme spans its ages: ' . $span);
	}
	pg_assert_true(in_array('Acorns', $ages['texts'], true) && in_array('Years 3 and 4', $ages['texts'], true), 'the class groups');

	$year = $got['blocks']['school-year'];
	pg_assert_same(3, count(array_keys($year['headings'], 'h3', true)), 'a card per term');
	pg_assert_same(1, count(array_keys($year['tags'], 'ol', true)), 'the terms are in order');
	pg_assert_same(3, count(array_keys($year['classes'], 'pg-school-term', true)), 'each term has its rule');
	pg_assert_same(2, count(array_keys($year['classes'], 'pg-school-break', true)), 'a break between the terms');
	pg_assert_true(in_array('Autumn term', $year['texts'], true), 'a term is named by its season');
}

// The rules the blocks need are read from the family file by the PHP
// stylesheet reader, so a template can ship them.
function test_designer_blocks_school_rules_reach_the_stylesheet()
{
	$part = _pg_designer_block_css_parse(designer_blocks_school_test_file());
	pg_assert_same(array('pg-school-finder', 'pg-school-ages', 'pg-school-term', 'pg-school-break'), array_keys($part['rules']), 'the family\'s rules');
	pg_assert_same(array('pg-school-finder'), $part['needs']['school-hero'], 'the hero needs the age finder');
	pg_assert_same(array('pg-school-ages'), $part['needs']['school-ages'], 'the ladder needs the age scale');
	pg_assert_same(array('pg-school-term', 'pg-school-break'), $part['needs']['school-year'], 'the year needs the term and break rules');
	$source = _pg_designer_block_css_source();
	pg_assert_true(isset($source['rules']['pg-school-ages']), 'the block stylesheet holds the family\'s rules');
	foreach ($part['rules'] as $css) {
		pg_assert_true((bool) preg_match('/var\\(--bs-[a-z-]+\\)/', $css), 'a rule colours with a theme variable: ' . $css);
	}
	// Every age a programme can start or end at has its grid line.
	for ($age = 2; $age <= 9; $age++) {
		pg_assert_true(strpos($part['rules']['pg-school-ages'], '.pg-school-from-' . $age . ' ') !== false, 'a programme can start at ' . $age);
	}
	for ($age = 3; $age <= 10; $age++) {
		pg_assert_true(strpos($part['rules']['pg-school-ages'], '.pg-school-to-' . $age . ' ') !== false, 'a programme can end at ' . $age);
	}
}
