<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the description lines of the visual editor's ready-made blocks
 * (_sdBlockInfo() in style_designer.src.js), shown in the palette hover
 * card under the block's drawing: every core block of
 * _sdBlockPaletteItems() has one line, no line is left for a block that is
 * gone, and each line is one translatable sentence. The hover card itself
 * is run under Node in designer_block_sketch_test.php.
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
		if (substr($text, -1) !== '.' || strlen($text) > 140 || strpos($text, '. ') !== false) {
			$bad[] = $type . ': one sentence of at most 140 characters ending in a full stop';
		}
		if (isset($seen[$text])) {
			$bad[] = $type . ': the same line as ' . $seen[$text];
		}
		$seen[$text] = $type;
	}
	pg_assert_same(array(), $bad, 'block description lines');
}
