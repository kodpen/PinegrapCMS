<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Sports Club block family
 * (assets/js/style_designer_blocks_sportsclub.js): the core's
 * registration call and helper kit are cut out of style_designer.src.js and
 * run under Node with the family file (skipped on a machine without Node).
 * Every block draws a section with one h2 and no skipped heading level, no
 * fixed colour or template class, numbers filled into their {var:n} slots,
 * entrances the interaction normalizer keeps, and no fixed year; the match
 * day is a dark band with two crests and a kick-off time, every result is
 * said with an icon and a word, the league table names its heads and marks
 * the club's own row, the squad gives every player a shirt number.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function designer_blocks_sportsclub_test_file()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer_blocks_sportsclub.js');
}

// The core between $from and the first $to after it, asserted found.
function designer_blocks_sportsclub_test_cut($from, $to)
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
function designer_blocks_sportsclub_test_run()
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
		. designer_blocks_sportsclub_test_cut('    function _sdT(key, vars) {', "\n    }\n") . "\n    }\n"
		. "var _ids = 0;\n"
		. "function createNode(type, props, children) { return { id: 'n' + (++_ids), type: type, props: props || {}, children: children || [] }; }\n"
		. designer_blocks_sportsclub_test_cut('    // ── Block families in their own files', '    // ── Block stylesheet')
		. designer_blocks_sportsclub_test_cut('    function _sdFxSpec() {', '    // The data-pg-fx-* attributes')
		. "var _SD_BLOCK_CSS = {}, _SD_BLOCK_CSS_NEEDS = {};\n"
		. "var StyleDesigner = { registerBlockFamily: _sdRegisterBlockFamily };\n"
		. designer_blocks_sportsclub_test_file() . "\n"
		. "var fam = _sdBlockFamilies()[0];\n"
		. "var report = { prefix: fam.prefix, group: fam.group.title, blocks: {} };\n"
		. "fam.items.forEach(function (it) {\n"
		. "  var ct = it.extra.componentType, root = fam.build(ct);\n"
		. "  var b = { root: [root.type, root.props.tag], attrs: root.props._attrs || [], classes: [], headings: [], texts: [], fx: [], badges: [], hidden: 0, scopes: [], times: [], current: 0 };\n"
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
		. "      if (a.name === 'datetime') b.times.push(a.value);\n"
		. "      if (a.name === 'aria-current') b.current++;\n"
		. "    });\n"
		. "    if (p._fx) b.fx.push(_sdFxNormalize(p._fx) ? 'ok' : 'dropped');\n"
		. "    if (/(^| )badge( |$)/.test(p.cssClass || '') && n.children.length) {\n"
		. "      b.badges.push(n.children.map(function (k) { return k.props.contentType || k.props.tag; }).join('+'));\n"
		. "    }\n"
		. "    n.children.forEach(walk);\n"
		. "  })(root);\n"
		. "  report.blocks[ct] = b;\n"
		. "});\n"
		. "report.unknown = fam.build('sportsclub-none');\n"
		. "console.log(JSON.stringify(report));\n";

	$file = tempnam(sys_get_temp_dir(), 'pgsc');
	file_put_contents($file, $script);
	$out = array();
	exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	unlink($file);
	pg_assert_same(0, $code, implode("\n", $out));
	return $got = json_decode((string) end($out), true);
}

function test_designer_blocks_sportsclub_family_registers()
{
	$got = designer_blocks_sportsclub_test_run();
	if ($got === null) {
		pg_assert_true(true, 'Node is not installed: the run is skipped');
		return;
	}
	pg_assert_same('sportsclub-', $got['prefix'], 'the family\'s componentType prefix');
	pg_assert_same('Sports Club', $got['group'], 'the family has its own palette group');
	pg_assert_same(array('sportsclub-matchday', 'sportsclub-fixtures', 'sportsclub-table', 'sportsclub-squad'), array_keys($got['blocks']), 'the four blocks');
	pg_assert_same(null, $got['unknown'], 'an unknown type draws nothing');
}

function test_designer_blocks_sportsclub_blocks_draw_sound_trees()
{
	$got = designer_blocks_sportsclub_test_run();
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
		pg_assert_same(array(), array_values(preg_grep('/^(psp|sp)-/', $b['classes'])), $ctype . ' writes no template class');
		pg_assert_same(array(), array_values(preg_grep('/\\{var/', $b['texts'])), $ctype . ' fills every {var} slot');
		pg_assert_same(array(), array_values(preg_grep('/\\b(19|20)\\d\\d\\b/', $b['texts'])), $ctype . ' names no year');
		pg_assert_true(count($b['fx']) > 0, $ctype . ' has an entrance');
		pg_assert_same(array(), array_values(array_diff($b['fx'], array('ok'))), $ctype . ' keeps every entrance through the normalizer');
	}

	$day = $got['blocks']['sportsclub-matchday'];
	pg_assert_same(array(array('name' => 'data-bs-theme', 'value' => 'dark')), $day['attrs'], 'the match day is a dark band');
	pg_assert_same(2, count(array_keys($day['classes'], 'pg-sc-crest', true)), 'a crest for each side');
	pg_assert_same(array('15:00'), $day['times'], 'the kick-off carries its time');
	pg_assert_true(in_array('2 – 1', $day['texts'], true), 'the last score fills both numbers');
	pg_assert_same(array('icon+heading'), $day['badges'], 'the last result says it with an icon and a word');

	$board = $got['blocks']['sportsclub-fixtures'];
	pg_assert_same(4, count($board['times']), 'every fixture carries its kick-off time');
	pg_assert_same(4, count(array_keys($board['badges'], 'icon+heading', true)), 'every result says it with an icon and a word');
	foreach (array('Win', 'Drawn game', 'Loss', 'Home match', 'Away match', '0 – 2') as $text) {
		pg_assert_true(in_array($text, $board['texts'], true), 'the board says ' . $text);
	}

	$table = $got['blocks']['sportsclub-table'];
	pg_assert_same(array_merge(array_fill(0, 8, 'col'), array_fill(0, 6, 'row')), $table['scopes'], 'the table names its column and row heads');
	pg_assert_same(1, $table['current'], 'the club\'s own row is marked');
	pg_assert_same(2, count(array_keys($table['classes'], 'pg-sc-up', true)), 'two promotion places');
	pg_assert_same(2, count(array_keys($table['classes'], 'pg-sc-down', true)), 'two relegation places');
	foreach (array('Matches played', 'Goal difference', 'League points', 'Promotion places', 'Relegation places') as $text) {
		pg_assert_true(in_array($text, $table['texts'], true), 'the table says ' . $text);
	}

	$squad = $got['blocks']['sportsclub-squad'];
	pg_assert_same(4, count(array_keys($squad['headings'], 'h3', true)), 'a heading per position');
	pg_assert_same(9, count(array_keys($squad['classes'], 'pg-sc-number', true)), 'a shirt number per player');
	pg_assert_true(in_array('Okan Yıldız', $squad['texts'], true), 'a player\'s picture and name');
}

// The rules the blocks need are read from the family file by the PHP
// stylesheet reader, so a template can ship them.
function test_designer_blocks_sportsclub_rules_reach_the_stylesheet()
{
	$part = _pg_designer_block_css_parse(designer_blocks_sportsclub_test_file());
	pg_assert_same(array('pg-sc-crest', 'pg-sc-score', 'pg-sc-up', 'pg-sc-down', 'pg-sc-mark', 'pg-sc-number'), array_keys($part['rules']), 'the family\'s rules');
	pg_assert_same(array('pg-sc-number', 'pg-ratio-1x1'), $part['needs']['sportsclub-squad'], 'the squad needs its numbers and the core square crop');
	$source = _pg_designer_block_css_source();
	pg_assert_true(isset($source['rules']['pg-sc-crest']), 'the block stylesheet holds the family\'s rules');
	foreach (array('pg-sc-crest', 'pg-sc-up', 'pg-sc-down', 'pg-sc-number') as $key) {
		pg_assert_true((bool) preg_match('/var\\(--bs-[a-z-]+\\)/', $part['rules'][$key]), 'a coloured rule uses a theme variable: ' . $key);
	}
}
