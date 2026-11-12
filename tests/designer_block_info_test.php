<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the description lines of the visual editor's ready-made blocks,
 * shown in the palette hover card under the block's drawing: every core block
 * of _sdBlockPaletteItems() has one line in _sdBlockInfo() (style_designer.src.js),
 * no line is left for a block that is gone, every block of a family file
 * has a `desc` in its item, and each line is one translatable sentence used
 * once. The hover card itself is run under Node in
 * designer_block_sketch_test.php.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The core from $from up to the first $to after it, asserted found.
function designer_block_info_test_cut($from, $to)
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

// The componentTypes of the core block list, in palette order; family files
// add theirs after this list and are not read here.
function designer_block_info_test_core_blocks()
{
	$list = designer_block_info_test_cut('    function _sdBlockPaletteItems() {', '_sdBlockFamilies().forEach(');
	preg_match_all("/componentType:\\s*'([^']+)'/", $list, $m);
	return $m[1];
}

// The lines of the block info map: componentType => English text; a line
// that is not `'type': _sdT('text')` is kept under its line number with a
// null text.
function designer_block_info_test_map()
{
	$body = designer_block_info_test_cut('    function _sdBlockInfo() {', "\n    }\n");
	$open = strpos($body, '_sdBlockInfoMap = {');
	$close = strpos($body, "\n        };");
	pg_assert_true($open !== false && $close !== false, 'the map is one object literal');
	if ($open === false || $close === false) {
		return array();
	}
	$lines = explode("\n", trim(substr($body, $open + strlen('_sdBlockInfoMap = {'), $close - $open - strlen('_sdBlockInfoMap = {'))));
	$map = array();
	foreach ($lines as $n => $line) {
		if (preg_match("/^\\s*'([a-z0-9-]+)':\\s*_sdT\\('((?:[^'\\\\]|\\\\.)+)'\\),?\\s*$/", $line, $m)) {
			$map[$m[1]] = isset($map[$m[1]]) ? null : $m[2];
		} else {
			$map['line ' . ($n + 1)] = null;
		}
	}
	return $map;
}

// One description line's faults: one sentence of at most 140 characters
// ending in a full stop.
function designer_block_info_test_sentence_faults($text)
{
	return (substr($text, -1) !== '.' || strlen($text) > 140 || strpos($text, '. ') !== false)
		? 'one sentence of at most 140 characters ending in a full stop'
		: '';
}

// Families whose blocks have no description line yet; a family leaves the
// list in the change that writes its lines, and a family not on it has a
// line for every block.
function designer_block_info_test_undescribed_families()
{
	return array('launch', 'podcast');
}

// The items of every family file: family => componentType => its desc text,
// '' without one, or null for a desc that is not one `desc: _sdT('text')`
// literal.
function designer_block_info_test_families()
{
	static $families = null;
	if ($families !== null) {
		return $families;
	}
	$families = array();
	foreach (array_slice(pg_designer_js_sources(), 1) as $file) {
		$family = preg_replace('/^assets\/js\/style_designer_blocks_|\.js$/', '', $file);
		$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
		$start = strpos($js, "\n        items: [");
		$end = $start === false ? false : strpos($js, "\n        ],", $start);
		pg_assert_true($start !== false && $end !== false, $family . ': its items are one array');
		if ($start === false || $end === false) {
			continue;
		}
		$families[$family] = array();
		foreach (array_slice(explode('{ label:', substr($js, $start, $end - $start)), 1) as $item) {
			if (!preg_match("/componentType:\\s*'([^']+)'/", $item, $m)) {
				continue;
			}
			$desc = '';
			if (strpos($item, 'desc:') !== false) {
				$one = substr_count($item, 'desc:') === 1
					&& preg_match("/\\bdesc:\\s*_sdT\\('((?:[^'\\\\]|\\\\.)+)'\\)\\s*\\},?\\s*$/", $item, $d);
				$desc = $one ? $d[1] : null;
			}
			$families[$family][$m[1]] = $desc;
		}
	}
	return $families;
}

function test_designer_block_info_every_core_block_has_a_line()
{
	$blocks = designer_block_info_test_core_blocks();
	$map = designer_block_info_test_map();
	pg_assert_true(count($blocks) >= 127, 'the core block list is read');
	pg_assert_same(array(), array_values(array_diff($blocks, array_keys($map))), 'core blocks without a description line in _sdBlockInfo()');
	pg_assert_same(array(), array_values(array_diff(array_keys($map), $blocks)), 'description lines for no core block (or not one `\'type\': _sdT(\'text\')` line)');
}

function test_designer_block_info_lines_are_single_sentences()
{
	$map = designer_block_info_test_map();
	$bad = array();
	$seen = array();
	foreach ($map as $type => $text) {
		if ($text === null) {
			$bad[] = $type . ': written twice or not as one _sdT() literal';
			continue;
		}
		$fault = designer_block_info_test_sentence_faults($text);
		if ($fault !== '') {
			$bad[] = $type . ': ' . $fault;
		}
		if (isset($seen[$text])) {
			$bad[] = $type . ': the same line as ' . $seen[$text];
		}
		$seen[$text] = $type;
	}
	pg_assert_same(array(), $bad, 'block description lines');
}

function test_designer_block_info_every_family_block_has_a_line()
{
	$families = designer_block_info_test_families();
	$waiting = designer_block_info_test_undescribed_families();
	$described = 0;
	$missing = array();
	$done = array();
	foreach ($families as $family => $items) {
		$without = array_keys(array_filter($items, function ($desc) {
			return $desc === '';
		}));
		if (in_array($family, $waiting, true)) {
			if (count($items) > 0 && count($without) === 0) {
				$done[] = $family;
			}
			continue;
		}
		$described++;
		pg_assert_true(count($items) > 0, $family . ': its items are read');
		foreach ($without as $type) {
			$missing[] = $family . ': ' . $type;
		}
	}
	pg_assert_true($described >= 35, 'the described families are read (' . $described . ')');
	pg_assert_same(array(), $missing, 'family blocks without a desc line in their item');
	pg_assert_same(array(), $done, 'families with a line for every block, still listed as undescribed');
}

function test_designer_block_info_family_lines_are_single_sentences()
{
	$seen = array();
	foreach (designer_block_info_test_map() as $type => $text) {
		if ($text !== null) {
			$seen[$text] = $type;
		}
	}
	$bad = array();
	foreach (designer_block_info_test_families() as $family => $items) {
		foreach ($items as $type => $text) {
			if ($text === '') {
				continue;
			}
			if ($text === null) {
				$bad[] = $type . ': written twice or not as one desc: _sdT() literal closing its item';
				continue;
			}
			$fault = designer_block_info_test_sentence_faults($text);
			if ($fault !== '') {
				$bad[] = $type . ': ' . $fault;
			}
			if (isset($seen[$text])) {
				$bad[] = $type . ': the same line as ' . $seen[$text];
			}
			$seen[$text] = $type;
		}
	}
	pg_assert_same(array(), $bad, 'family block description lines');
}
