<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Architecture block family
 * (assets/js/style_designer_blocks_architecture.js): the core's
 * registration call and helper kit are cut out of style_designer.src.js
 * and run under Node with the family file (skipped on a machine without
 * Node). Every block draws a section with one top heading and no skipped
 * level, no fixed colour class, every {var} slot filled, an entrance the
 * interaction normalizer keeps and no fixed year or month; the hero
 * captions its plate, the project sheet lists its facts and credits, the
 * material board mounts each material as a sample.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function designer_blocks_architecture_test_file()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer_blocks_architecture.js');
}

// The core between $from and the first $to after it, asserted found.
function designer_blocks_architecture_test_cut($from, $to)
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
function designer_blocks_architecture_test_run()
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
		. designer_blocks_architecture_test_cut('    function _sdT(key, vars) {', "\n    }\n") . "\n    }\n"
		. "var _ids = 0;\n"
		. "function createNode(type, props, children) { return { id: 'n' + (++_ids), type: type, props: props || {}, children: children || [] }; }\n"
		. designer_blocks_architecture_test_cut('    // ── Block families in their own files', '    // ── Block stylesheet')
		. designer_blocks_architecture_test_cut('    function _sdFxSpec() {', '    // The data-pg-fx-* attributes')
		. "var _SD_BLOCK_CSS = {}, _SD_BLOCK_CSS_NEEDS = {};\n"
		. "var StyleDesigner = { registerBlockFamily: _sdRegisterBlockFamily };\n"
		. designer_blocks_architecture_test_file() . "\n"
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
		. "report.unknown = fam.build('architecture-none');\n"
		. "console.log(JSON.stringify(report));\n";

	$file = tempnam(sys_get_temp_dir(), 'pgarc');
	file_put_contents($file, $script);
	$out = array();
	exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	unlink($file);
	pg_assert_same(0, $code, implode("\n", $out));
	return $got = json_decode((string) end($out), true);
}

function test_designer_blocks_architecture_family_registers()
{
	$got = designer_blocks_architecture_test_run();
	if ($got === null) {
		pg_assert_true(true, 'Node is not installed: the run is skipped');
		return;
	}
	pg_assert_same('architecture-', $got['prefix'], 'the family\'s componentType prefix');
	pg_assert_same('Architecture', $got['group'], 'the family has its own palette group');
	pg_assert_same(array('architecture-hero', 'architecture-sheet', 'architecture-materials'), array_keys($got['blocks']), 'the three blocks');
	pg_assert_same(null, $got['unknown'], 'an unknown type draws nothing');
}

function test_designer_blocks_architecture_blocks_draw_sound_trees()
{
	$got = designer_blocks_architecture_test_run();
	if ($got === null) {
		pg_assert_true(true, 'Node is not installed: the run is skipped');
		return;
	}
	$fixed = array('bg-white', 'bg-light', 'bg-dark', 'text-white', 'text-white-50', 'text-light', 'text-dark', 'text-muted', 'text-bg-light', 'text-bg-dark', 'btn-light', 'btn-dark', 'btn-outline-light', 'btn-outline-dark', 'link-light', 'link-dark', 'navbar-dark');
	foreach ($got['blocks'] as $ctype => $b) {
		// The hero is a page's way in, so its title is the h1; the
		// others are sections under a page's own h1.
		$top = $ctype === 'architecture-hero' ? 1 : 2;
		pg_assert_same(1, count(array_keys($b['headings'], 'h' . $top, true)), $ctype . ' has one h' . $top);
		pg_assert_same(array('semantic', 'section'), $b['root'], $ctype . ' is a section');
		$levels = array_values(array_unique(array_map(function ($h) { return (int) substr($h, 1); }, $b['headings'])));
		sort($levels);
		pg_assert_same(range($top, max($levels)), $levels, $ctype . ' skips no heading level');
		pg_assert_same(array(), array_values(array_intersect($b['classes'], $fixed)), $ctype . ' writes no fixed colour class');
		pg_assert_same(array(), array_values(preg_grep('/^(pai|ai)-/', $b['classes'])), $ctype . ' writes no template class');
		pg_assert_same(array(), array_values(preg_grep('/\\{var/', $b['texts'])), $ctype . ' fills every {var} slot');
		pg_assert_same(array(), array_values(preg_grep('/\\b(19|20)\\d\\d\\b/', $b['texts'])), $ctype . ' names no year');
		pg_assert_same(array(), array_values(preg_grep('/\\b(January|February|March|April|May|June|July|August|September|October|November|December)\\b/', $b['texts'])), $ctype . ' names no month');
		pg_assert_true(count($b['fx']) >= 1, $ctype . ' has an entrance');
		pg_assert_same(array(), array_values(array_diff($b['fx'], array('ok'))), $ctype . '\'s entrances survive the normalizer');
	}

	$hero = $got['blocks']['architecture-hero'];
	pg_assert_same(1, $hero['images'], 'one photograph');
	pg_assert_true(in_array('figcaption', $hero['tags'], true), 'the plate has a caption');
	pg_assert_true(in_array('pg-arch-plate', $hero['classes'], true) && in_array('pg-arch-no', $hero['classes'], true), 'the plate rule and its number');
	pg_assert_true(in_array('ratio-21x9', $hero['classes'], true), 'the plate is wide');
	pg_assert_same(2, count(array_keys($hero['fx'], 'ok', true)), 'the words rise and the plate fades');

	$sheet = $got['blocks']['architecture-sheet'];
	pg_assert_same(1, count(array_keys($sheet['tags'], 'dl', true)), 'the facts are a description list');
	pg_assert_same(6, count(array_keys($sheet['tags'], 'dt', true)), 'six facts');
	pg_assert_same(6, count(array_keys($sheet['tags'], 'dd', true)), 'a value per fact');
	pg_assert_true(in_array('pg-arch-facts', $sheet['classes'], true), 'the facts rule');
	pg_assert_true(in_array('Floor Area', $sheet['texts'], true), 'the shared floor area label');
	pg_assert_same(4, count(array_keys($sheet['tags'], 'li', true)), 'four credits');
	pg_assert_same(1, $sheet['images'], 'one photograph');

	$board = $got['blocks']['architecture-materials'];
	pg_assert_same(6, count(array_keys($board['classes'], 'pg-arch-swatch', true)), 'six mounted samples');
	pg_assert_same(6, count(array_keys($board['headings'], 'h3', true)), 'a name per material');
	pg_assert_same(6, $board['images'], 'a sample picture per material');
	pg_assert_true(in_array('Lime plaster', $board['texts'], true), 'a material is named');
}

// The rules the blocks need are read from the family file by the PHP
// stylesheet reader, so a template can ship them.
function test_designer_blocks_architecture_rules_reach_the_stylesheet()
{
	$part = _pg_designer_block_css_parse(designer_blocks_architecture_test_file());
	pg_assert_same(array('pg-arch-plate', 'pg-arch-facts', 'pg-arch-swatch'), array_keys($part['rules']), 'the family\'s rules');
	pg_assert_same(array('pg-arch-plate'), $part['needs']['architecture-hero'], 'the hero needs the plate');
	pg_assert_same(array('pg-arch-facts'), $part['needs']['architecture-sheet'], 'the sheet needs the facts');
	pg_assert_same(array('pg-arch-swatch'), $part['needs']['architecture-materials'], 'the board needs the swatch');
	$source = _pg_designer_block_css_source();
	pg_assert_true(isset($source['rules']['pg-arch-facts']), 'the block stylesheet holds the family\'s rules');
	foreach ($part['rules'] as $css) {
		pg_assert_true((bool) preg_match('/var\\(--bs-[a-z-]+\\)/', $css), 'a rule colours with a theme variable: ' . $css);
	}
}
