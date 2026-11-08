<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Planetarium block family
 * (assets/js/style_designer_blocks_planetarium.js):
 * the core's registration call and helper kit are cut out of
 * style_designer.src.js and run under Node with the family file (skipped on
 * a machine without Node). Every block draws a section with one h2 and no
 * skipped heading level, no fixed colour class, a status pill that always
 * carries an icon and a word, numbers filled into their {var} slots,
 * interactions the normalizer keeps, and no fixed year in its sample text.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function designer_blocks_planetarium_test_file()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer_blocks_planetarium.js');
}

// The core between $from and the first $to after it, asserted found.
function designer_blocks_planetarium_test_cut($from, $to)
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
function designer_blocks_planetarium_test_run()
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
		. designer_blocks_planetarium_test_cut('    function _sdT(key, vars) {', "\n    }\n") . "\n    }\n"
		. "var _ids = 0;\n"
		. "function createNode(type, props, children) { return { id: 'n' + (++_ids), type: type, props: props || {}, children: children || [] }; }\n"
		. designer_blocks_planetarium_test_cut('    // ── Block families in their own files', '    // ── Block stylesheet')
		. designer_blocks_planetarium_test_cut('    function _sdFxSpec() {', '    // The data-pg-fx-* attributes')
		. "var _SD_BLOCK_CSS = {}, _SD_BLOCK_CSS_NEEDS = {};\n"
		. "var StyleDesigner = { registerBlockFamily: _sdRegisterBlockFamily };\n"
		. designer_blocks_planetarium_test_file() . "\n"
		. "var fam = _sdBlockFamilies()[0];\n"
		. "var report = { prefix: fam.prefix, group: fam.group.title, blocks: {} };\n"
		. "fam.items.forEach(function (it) {\n"
		. "  var ct = it.extra.componentType, root = fam.build(ct);\n"
		. "  var b = { root: [root.type, root.props.tag], attrs: root.props._attrs || [], classes: [], headings: [], texts: [], fx: [], badges: [], current: [], tags: [], hidden: [] };\n"
		. "  (function walk(n) {\n"
		. "    var p = n.props;\n"
		. "    String(p.cssClass || '').split(/\\s+/).forEach(function (c) { if (c) b.classes.push(c); });\n"
		. "    if (p.text) b.texts.push(p.text);\n"
		. "    if (p.alt) b.texts.push(p.alt);\n"
		. "    var tag = p.tag || '';\n"
		. "    if (/^h[1-6]$/.test(tag)) b.headings.push(tag);\n"
		. "    if (tag) b.tags.push(tag);\n"
		. "    (p._attrs || []).forEach(function (a) { if (a.name === 'aria-current') b.current.push(tag + '=' + a.value); if (a.name === 'aria-hidden') b.hidden.push(p.cssClass || ''); });\n"
		. "    if (p._fx) b.fx.push(_sdFxNormalize(p._fx) ? p._fx.effect : 'dropped');\n"
		. "    if (/(^| )badge( |$)/.test(p.cssClass || '') && n.children.length) {\n"
		. "      b.badges.push(n.children.map(function (k) { return k.props.contentType || k.props.tag; }).join('+'));\n"
		. "    }\n"
		. "    n.children.forEach(walk);\n"
		. "  })(root);\n"
		. "  report.blocks[ct] = b;\n"
		. "});\n"
		. "report.unknown = fam.build('planetarium-none');\n"
		. "console.log(JSON.stringify(report));\n";

	$file = tempnam(sys_get_temp_dir(), 'pgsky');
	file_put_contents($file, $script);
	$out = array();
	exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	unlink($file);
	pg_assert_same(0, $code, implode("\n", $out));
	return $got = json_decode((string) end($out), true);
}

function test_designer_blocks_planetarium_family_registers()
{
	$got = designer_blocks_planetarium_test_run();
	if ($got === null) {
		pg_assert_true(true, 'Node is not installed: the run is skipped');
		return;
	}
	pg_assert_same('planetarium-', $got['prefix'], 'the family\'s componentType prefix');
	pg_assert_same('Planetarium', $got['group'], 'the family has its own palette group');
	pg_assert_same(array('planetarium-tonight', 'planetarium-shows', 'planetarium-objects', 'planetarium-moon'), array_keys($got['blocks']), 'the four blocks');
	pg_assert_same(null, $got['unknown'], 'an unknown type draws nothing');
}

function test_designer_blocks_planetarium_blocks_draw_sound_trees()
{
	$got = designer_blocks_planetarium_test_run();
	if ($got === null) {
		pg_assert_true(true, 'Node is not installed: the run is skipped');
		return;
	}
	$fixed = array('bg-white', 'bg-light', 'bg-dark', 'bg-black', 'text-white', 'text-white-50', 'text-black', 'text-light', 'text-dark', 'text-muted', 'text-bg-light', 'text-bg-dark', 'btn-light', 'btn-dark', 'btn-outline-light', 'link-light', 'navbar-dark');
	foreach ($got['blocks'] as $ctype => $b) {
		pg_assert_same(array('semantic', 'section'), $b['root'], $ctype . ' is a section');
		pg_assert_same(1, count(array_keys($b['headings'], 'h2', true)), $ctype . ' has one h2');
		$levels = array_values(array_unique(array_map(function ($h) { return (int) substr($h, 1); }, $b['headings'])));
		sort($levels);
		pg_assert_same(range(2, max($levels)), $levels, $ctype . ' skips no heading level');
		pg_assert_same(array(), array_values(array_intersect($b['classes'], $fixed)), $ctype . ' writes no fixed colour class');
		pg_assert_same(array(), array_values(preg_grep('/^(ppm|plm|planetarium)-/', $b['classes'])), $ctype . ' writes no template class');
		pg_assert_same(array(), array_values(preg_grep('/\\{var/', $b['texts'])), $ctype . ' fills every {var} slot');
		pg_assert_same(array(), array_values(preg_grep('/\\b(19|20)\\d\\d\\b/', $b['texts'])), $ctype . ' names no year');
		pg_assert_same(array(), array_values(array_keys($b['fx'], 'dropped', true)), $ctype . '\'s interactions survive the normalizer');
		pg_assert_same(array(), array_values(array_diff($b['badges'], array('icon+heading'))), $ctype . ' draws pills from an icon and a word');
		pg_assert_same(array(), array_values(array_diff($b['hidden'], preg_grep('/(^| )pg-sky-orb( |$)/', $b['hidden']))), $ctype . ' hides only its decorative markers');
	}

	$tonight = $got['blocks']['planetarium-tonight'];
	pg_assert_true(in_array('Telescopes open tonight', $tonight['texts'], true), 'whether the telescopes open is said in words');
	pg_assert_same(array('li=true'), $tonight['current'], 'one step of the cloud cover is tonight\'s');
	pg_assert_same(array(), array_values(array_diff(array('Clear', 'Mostly clear', 'Overcast'), $tonight['texts'])), 'every cover step has its name');
	pg_assert_same(12, count(array_keys($tonight['tags'], 'time', true)), 'a time element per dark-hour moment and two per planet');
	pg_assert_same(array('slide-up'), $tonight['fx'], 'the planets rise in');

	$shows = $got['blocks']['planetarium-shows'];
	pg_assert_same(array('a=date'), $shows['current'], 'the shown day is the current date');
	pg_assert_same(3, count(array_keys($shows['tags'], 'article', true)), 'three show cards');
	pg_assert_same(8, count(array_keys($shows['tags'], 'time', true)), 'a time element per showtime');
	pg_assert_same(array(), array_values(array_diff(array('Sold out', 'Few seats left', '35 min', 'Ages 6+'), $shows['texts'])), 'seats, length and age are said in words');

	$objects = $got['blocks']['planetarium-objects'];
	pg_assert_same(array('fade'), $objects['fx'], 'the object cards fade in');
	pg_assert_same(4, count(array_keys($objects['headings'], 'h3', true)), 'four objects');
	pg_assert_same(array(), array_values(array_diff(array('Naked eye', 'Binoculars', 'Telescope', 'Magnitude 0.8'), $objects['texts'])), 'what each needs is said in words');

	$moon = $got['blocks']['planetarium-moon'];
	pg_assert_same(8, count(array_keys($moon['headings'], 'h3', true)), 'eight phases');
	pg_assert_same(3, count(array_keys($moon['texts'], 'Telescope night', true)), 'the telescope nights are marked in words');
	pg_assert_same(2, count(array_keys($moon['classes'], 'pg-sky-wane', true)), 'the waxing phases are mirrored');
	pg_assert_true(in_array('In 15 days', $moon['texts'], true), 'a phase counts its days from today');
}

// The rules the blocks need are read from the family file by the PHP
// stylesheet reader, so a template can ship them.
function test_designer_blocks_planetarium_rules_reach_the_stylesheet()
{
	$part = _pg_designer_block_css_parse(designer_blocks_planetarium_test_file());
	pg_assert_same(array('pg-sky-time', 'pg-sky-orb', 'pg-sky-wane'), array_keys($part['rules']), 'the family\'s rules');
	pg_assert_same(array('pg-sky-orb', 'pg-sky-wane'), $part['needs']['planetarium-moon'], 'the moon needs the orb and the mirror rule');
	$source = _pg_designer_block_css_source();
	pg_assert_true(isset($source['rules']['pg-sky-wane']), 'the block stylesheet holds the family\'s rules');
}
