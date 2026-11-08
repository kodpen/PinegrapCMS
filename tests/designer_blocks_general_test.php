<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the general block families that extend the core's Hours &
 * Locations group (assets/js/style_designer_blocks_hours.js and
 * style_designer_blocks_locations.js): the core's registration call and
 * helper kit are cut out of style_designer.src.js and run under Node with
 * each family file (skipped on a machine without Node). A general family
 * opens no palette group of its own; each block draws a section with one h2
 * and no skipped heading level, no fixed colour class, every {var} slot
 * filled, no year, an entrance the interaction normalizer keeps, and the
 * structure its name promises.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function designer_blocks_general_test_file($name)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer_blocks_' . $name . '.js');
}

// The core between $from and the first $to after it, asserted found.
function designer_blocks_general_test_cut($from, $to)
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
function designer_blocks_general_test_run($name)
{
	static $runs = array();
	if (array_key_exists($name, $runs)) {
		return $runs[$name];
	}
	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);
	if ($code !== 0) {
		return $runs[$name] = null;
	}

	$script = "'use strict';\n"
		. designer_blocks_general_test_cut('    function _sdT(key, vars) {', "\n    }\n") . "\n    }\n"
		. "var _ids = 0;\n"
		. "function createNode(type, props, children) { return { id: 'n' + (++_ids), type: type, props: props || {}, children: children || [] }; }\n"
		. designer_blocks_general_test_cut('    // ── Block families in their own files', '    // ── Block stylesheet')
		. designer_blocks_general_test_cut('    function _sdFxSpec() {', '    // The data-pg-fx-* attributes')
		. "var _SD_BLOCK_CSS = {}, _SD_BLOCK_CSS_NEEDS = {};\n"
		. "var StyleDesigner = { registerBlockFamily: _sdRegisterBlockFamily };\n"
		. designer_blocks_general_test_file($name) . "\n"
		. "var fam = _sdBlockFamilies()[0];\n"
		. "var report = { prefix: fam.prefix, group: fam.group ? fam.group.title : null, blocks: {} };\n"
		. "fam.items.forEach(function (it) {\n"
		. "  var ct = it.extra.componentType, root = fam.build(ct);\n"
		. "  var b = { root: [root.type, root.props.tag], attrs: root.props._attrs || [], classes: [], headings: [], texts: [], fx: [], badges: [], cells: [] };\n"
		. "  (function walk(n) {\n"
		. "    var p = n.props;\n"
		. "    String(p.cssClass || '').split(/\\s+/).forEach(function (c) { if (c) b.classes.push(c); });\n"
		. "    if (p.text) b.texts.push(p.text);\n"
		. "    if (p.alt) b.texts.push(p.alt);
"
		. "    var tag = p.tag || '';\n"
		. "    if (/^t[hd]$/.test(tag)) b.cells.push(tag + ':' + ((p._attrs || []).filter(function (a) { return a.name === 'scope'; }).map(function (a) { return a.value; })[0] || ''));\n"
		. "    if (/^h[1-6]$/.test(tag)) b.headings.push(tag);\n"
		. "    if (p._fx) b.fx.push(_sdFxNormalize(p._fx) ? 'ok' : 'dropped');\n"
		. "    if (/(^| )badge( |$)/.test(p.cssClass || '') && n.children.length) {\n"
		. "      b.badges.push(n.children.map(function (k) { return k.props.contentType || k.props.tag; }).join('+'));\n"
		. "    }\n"
		. "    n.children.forEach(walk);\n"
		. "  })(root);\n"
		. "  report.blocks[ct] = b;\n"
		. "});\n"
		. "report.unknown = fam.build(fam.prefix + 'none');\n"
		. "console.log(JSON.stringify(report));\n";

	$file = tempnam(sys_get_temp_dir(), 'pggen');
	file_put_contents($file, $script);
	$out = array();
	exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	unlink($file);
	pg_assert_same(0, $code, implode("\n", $out));
	return $runs[$name] = json_decode((string) end($out), true);
}

function test_designer_blocks_general_families_join_core_groups()
{
	$expect = array('hours' => array('hours-', array('hours-seasons')), 'locations' => array('locations-', array('locations-spaces')));
	foreach ($expect as $name => $want) {
		$got = designer_blocks_general_test_run($name);
		if ($got === null) {
			pg_assert_true(true, 'Node is not installed: the run is skipped');
			return;
		}
		pg_assert_same($want[0], $got['prefix'], $name . ': a core group\'s prefix');
		pg_assert_same(null, $got['group'], $name . ': no palette group of its own');
		pg_assert_same($want[1], array_keys($got['blocks']), $name . ': its blocks');
		pg_assert_same(null, $got['unknown'], $name . ': an unknown type draws nothing');
	}
}

function test_designer_blocks_general_blocks_draw_sound_trees()
{
	$fixed = array('bg-white', 'bg-light', 'bg-dark', 'text-white', 'text-white-50', 'text-light', 'text-dark', 'text-muted', 'text-bg-light', 'text-bg-dark', 'btn-light', 'btn-dark', 'btn-outline-light', 'link-light', 'navbar-dark');
	$blocks = array();
	foreach (array('hours', 'locations') as $name) {
		$got = designer_blocks_general_test_run($name);
		if ($got === null) {
			pg_assert_true(true, 'Node is not installed: the run is skipped');
			return;
		}
		$blocks += $got['blocks'];
	}
	foreach ($blocks as $ctype => $b) {
		pg_assert_same(array('semantic', 'section'), $b['root'], $ctype . ' is a section');
		pg_assert_same(1, count(array_keys($b['headings'], 'h2', true)), $ctype . ' has one h2');
		$levels = array_values(array_unique(array_map(function ($h) { return (int) substr($h, 1); }, $b['headings'])));
		sort($levels);
		pg_assert_same(range(2, max($levels)), $levels, $ctype . ' skips no heading level');
		pg_assert_same(array(), array_values(array_intersect($b['classes'], $fixed)), $ctype . ' writes no fixed colour class');
		pg_assert_same(array(), array_values(preg_grep('/\\{var/', $b['texts'])), $ctype . ' fills every {var} slot');
		pg_assert_same(array(), array_values(preg_grep('/\\b(19|20)\\d\\d\\b/', $b['texts'])), $ctype . ' names no year');
		pg_assert_same(array('ok'), $b['fx'], $ctype . '\'s one entrance survives the normalizer');
	}

	$hours = $blocks['hours-seasons'];
	pg_assert_same(array('th:col', 'th:col', 'th:col'), array_slice($hours['cells'], 0, 3), 'a column head per season after the day column');
	pg_assert_same(5, count(array_keys($hours['cells'], 'th:row', true)), 'a row head per day');
	pg_assert_same(10, count(array_keys($hours['cells'], 'td:', true)), 'an opening time per day and season');
	pg_assert_true(in_array('Closed', $hours['texts'], true), 'a closed day says so in a word');
	pg_assert_true(in_array('table-responsive', $hours['classes'], true), 'the table scrolls sideways on a phone');

	$spaces = $blocks['locations-spaces'];
	pg_assert_same(3, count(array_keys($spaces['headings'], 'h3', true)), 'three space cards');
	pg_assert_same(9, count(array_keys($spaces['classes'], 'badge', true)), 'three amenities on each card');
	pg_assert_true(in_array('Up to 120 guests', $spaces['texts'], true) && in_array('180 m²', $spaces['texts'], true), 'a space says how many it holds and its size');
	pg_assert_true(in_array('pg-ratio-16x9', $spaces['classes'], true) && in_array('object-fit-cover', $spaces['classes'], true), 'pictures keep one shape');
}

// The rules the blocks need are read from the family files by the PHP
// stylesheet reader, so a template can ship them.
function test_designer_blocks_general_rules_reach_the_stylesheet()
{
	$hours = _pg_designer_block_css_parse(designer_blocks_general_test_file('hours'));
	pg_assert_same(array('pg-hours-table'), array_keys($hours['rules']), 'the hours family\'s rule');
	pg_assert_same(array('pg-hours-table'), $hours['needs']['hours-seasons'], 'seasonal hours need it');
	$spaces = _pg_designer_block_css_parse(designer_blocks_general_test_file('locations'));
	pg_assert_same(array('pg-space-facts'), array_keys($spaces['rules']), 'the locations family\'s rule');
	pg_assert_same(array('pg-ratio-16x9', 'pg-space-facts'), $spaces['needs']['locations-spaces'], 'the space cards need the core picture ratio and their own rule');
	$source = _pg_designer_block_css_source();
	pg_assert_true(isset($source['rules']['pg-hours-table'], $source['rules']['pg-space-facts']), 'the block stylesheet holds both rules');
}
