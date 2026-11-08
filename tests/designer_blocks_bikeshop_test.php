<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Zoo block family (assets/js/style_designer_blocks_bikeshop.js):
 * the core's registration call and helper kit are cut out of
 * style_designer.src.js and run under Node with the family file (skipped on
 * a machine without Node). Every block draws a section with one h2 and no
 * skipped heading level, no fixed colour class, a pace, drop or turnaround pill
 * that always carries an icon and a word, numbers filled into their {var:n}
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

function designer_blocks_bikeshop_test_file()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer_blocks_bikeshop.js');
}

// The core between $from and the first $to after it, asserted found.
function designer_blocks_bikeshop_test_cut($from, $to)
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
function designer_blocks_bikeshop_test_run()
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
		. designer_blocks_bikeshop_test_cut('    function _sdT(key, vars) {', "\n    }\n") . "\n    }\n"
		. "var _ids = 0;\n"
		. "function createNode(type, props, children) { return { id: 'n' + (++_ids), type: type, props: props || {}, children: children || [] }; }\n"
		. designer_blocks_bikeshop_test_cut('    // ── Block families in their own files', '    // ── Block stylesheet')
		. designer_blocks_bikeshop_test_cut('    function _sdFxSpec() {', '    // The data-pg-fx-* attributes')
		. "var _SD_BLOCK_CSS = {}, _SD_BLOCK_CSS_NEEDS = {};\n"
		. "var StyleDesigner = { registerBlockFamily: _sdRegisterBlockFamily };\n"
		. designer_blocks_bikeshop_test_file() . "\n"
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
		. "report.unknown = fam.build('bikeshop-none');\n"
		. "console.log(JSON.stringify(report));\n";

	$file = tempnam(sys_get_temp_dir(), 'pgbike');
	file_put_contents($file, $script);
	$out = array();
	exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	unlink($file);
	pg_assert_same(0, $code, implode("\n", $out));
	return $got = json_decode((string) end($out), true);
}

function test_designer_blocks_bikeshop_family_registers()
{
	$got = designer_blocks_bikeshop_test_run();
	if ($got === null) {
		pg_assert_true(true, 'Node is not installed: the run is skipped');
		return;
	}
	pg_assert_same('bikeshop-', $got['prefix'], 'the family\'s componentType prefix');
	pg_assert_same('Bike Shop', $got['group'], 'the family has its own palette group');
	pg_assert_same(array('bikeshop-workshop', 'bikeshop-sizes', 'bikeshop-rides', 'bikeshop-fit'), array_keys($got['blocks']), 'the four blocks');
	pg_assert_same(null, $got['unknown'], 'an unknown type draws nothing');
}

function test_designer_blocks_bikeshop_blocks_draw_sound_trees()
{
	$got = designer_blocks_bikeshop_test_run();
	if ($got === null) {
		pg_assert_true(true, 'Node is not installed: the run is skipped');
		return;
	}
	$fixed = array('bg-white', 'bg-light', 'bg-dark', 'text-white', 'text-white-50', 'text-light', 'text-dark', 'text-muted', 'text-bg-light', 'text-bg-dark', 'btn-light', 'btn-dark', 'btn-outline-light', 'link-light', 'navbar-dark');
	$months = '/\\b(January|February|March|April|May|June|July|August|September|October|November|December)\\b/';
	foreach ($got['blocks'] as $ctype => $b) {
		pg_assert_same(array('semantic', 'section'), $b['root'], $ctype . ' is a section');
		pg_assert_same(1, count(array_keys($b['headings'], 'h2', true)), $ctype . ' has one h2');
		$levels = array_values(array_unique(array_map(function ($h) { return (int) substr($h, 1); }, $b['headings'])));
		sort($levels);
		pg_assert_same(range(2, max($levels)), $levels, $ctype . ' skips no heading level');
		pg_assert_same(array(), array_values(array_intersect($b['classes'], $fixed)), $ctype . ' writes no fixed colour class');
		pg_assert_same(array(), array_values(preg_grep('/^(pbs|bikeshop)-/', $b['classes'])), $ctype . ' writes no template class');
		pg_assert_same(array(), array_values(preg_grep('/\\{var/', $b['texts'])), $ctype . ' fills every {var} slot');
		pg_assert_same(array(), array_values(preg_grep('/\\b(19|20)\\d\\d\\b/', $b['texts'])), $ctype . ' names no year');
		pg_assert_same(array(), array_values(preg_grep($months, $b['texts'])), $ctype . ' names no month');
		pg_assert_same(array(), array_values(array_keys($b['fx'], 'dropped', true)), $ctype . '\'s interactions survive the normalizer');
		pg_assert_same(array(), array_values(array_diff($b['badges'], array('icon+heading', 'heading'))), $ctype . ' draws pills from an icon and a word');
	}

	$shop = $got['blocks']['bikeshop-workshop'];
	pg_assert_same(array('li=true'), $shop['current'], 'one load level is this week\'s');
	pg_assert_same(5, count($shop['badges']), 'a turnaround pill per job');
	pg_assert_true(in_array('Busy: a bike dropped off today is ready in about 3 working days.', $shop['texts'], true), 'the load is said in words');
	pg_assert_same(array('slide-up'), $shop['fx'], 'the job list rises in');

	$sizes = $got['blocks']['bikeshop-sizes'];
	pg_assert_true(in_array('table', $sizes['tags'], true) && in_array('caption', $sizes['tags'], true), 'the size chart is a table with its caption');
	pg_assert_same(4, count(array_keys($sizes['tags'], 'tr', true)) - 1, 'four sizes below the header row');
	pg_assert_same(6, count(array_keys($sizes['classes'], 'pg-bike-key', true)), 'the drawing letters key the table heads and the measures');
	pg_assert_true(in_array('167–178 cm', $sizes['texts'], true), 'a rider height range fills both numbers');
	pg_assert_true(in_array('table-responsive', $sizes['classes'], true), 'the table scrolls sideways on a phone');

	$rides = $got['blocks']['bikeshop-rides'];
	pg_assert_same(3, count(array_keys($rides['tags'], 'time', true)), 'a start time per ride');
	pg_assert_same(array('article=true'), $rides['current'], 'the next ride is marked');
	pg_assert_same(array(), array_values(array_diff(array('Social', 'Steady', 'Fast', 'No-drop', 'Drop ride', 'Next ride'), $rides['texts'])), 'paces, drop rules and the next ride are said in words');
	pg_assert_same(3, count(array_keys($rides['tags'], 'dl', true)), 'a fact list per ride');

	$fit = $got['blocks']['bikeshop-fit'];
	pg_assert_same(array('fade', 'slide-up'), $fit['fx'], 'the photo fades in and the points rise');
	pg_assert_same(5, count(array_keys($fit['classes'], 'pg-bike-key', true)), 'five numbered contact points');
	pg_assert_same(4, count(array_keys($fit['tags'], 'dt', true)), 'four readings on the fit sheet');
}

// The rules the blocks need are read from the family file by the PHP
// stylesheet reader, so a template can ship them.
function test_designer_blocks_bikeshop_rules_reach_the_stylesheet()
{
	$part = _pg_designer_block_css_parse(designer_blocks_bikeshop_test_file());
	pg_assert_same(array('pg-bike-num', 'pg-bike-key'), array_keys($part['rules']), 'the family\'s rules');
	pg_assert_same(array('pg-bike-num', 'pg-bike-key'), $part['needs']['bikeshop-fit'], 'the fit needs the figure and key rules');
	$source = _pg_designer_block_css_source();
	pg_assert_true(isset($source['rules']['pg-bike-key']), 'the block stylesheet holds the family\'s rules');
}
