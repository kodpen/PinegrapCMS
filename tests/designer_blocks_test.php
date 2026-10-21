<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the ready-made blocks of the Visual Page Editor's block palette:
 * every entry of _sdBlockPaletteItems() has a builder in createFromPalette()
 * and a group in _sdBlockPaletteGroups(), every rule a block asks for in
 * _SD_BLOCK_CSS_NEEDS exists in _SD_BLOCK_CSS, and no block rule uses
 * !important (Bootstrap utilities would lose the override war silently).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function _test_blocks_js()
{
	static $js = null;
	if ($js === null) {
		$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.js');
	}
	return $js;
}

// The source between $start_marker and the first $end_marker after it, or ''.
function _test_blocks_slice($start_marker, $end_marker)
{
	$js = _test_blocks_js();
	$start = strpos($js, $start_marker);
	$end = $start === false ? false : strpos($js, $end_marker, $start);
	if ($start === false || $end === false) {
		return '';
	}
	return substr($js, $start, $end - $start);
}

// componentType of every palette entry, in palette order.
function _test_blocks_palette_ctypes()
{
	$body = _test_blocks_slice('function _sdBlockPaletteItems()', '];');
	preg_match_all("/componentType:\\s*'([a-z0-9-]+)'/", $body, $m);
	return $m[1];
}

// Every prefix listed in the match arrays of _sdBlockPaletteGroups().
function _test_blocks_group_prefixes()
{
	$body = _test_blocks_slice('function _sdBlockPaletteGroups()', 'var other');
	preg_match_all('/match:\s*\[([^\]]*)\]/', $body, $lists);
	$out = array();
	foreach ($lists[1] as $list) {
		preg_match_all("/'([a-z0-9-]+)'/", $list, $m);
		$out = array_merge($out, $m[1]);
	}
	return $out;
}

// Body of the _SD_BLOCK_CSS object literal.
function _test_blocks_css_body()
{
	return _test_blocks_slice('var _SD_BLOCK_CSS = {', "\n    };");
}

// Rule names defined in _SD_BLOCK_CSS.
function _test_blocks_css_names()
{
	preg_match_all("/^\\s*'(pg-[a-z0-9-]+)'\\s*:/m", _test_blocks_css_body(), $m);
	return $m[1];
}

// ctype => rule names it lists in _SD_BLOCK_CSS_NEEDS.
function _test_blocks_css_needs()
{
	$body = _test_blocks_slice('var _SD_BLOCK_CSS_NEEDS = {', "\n    };");
	preg_match_all("/^\\s*'([a-z0-9-]+)'\\s*:\\s*\\[([^\\]]*)\\]/m", $body, $rows, PREG_SET_ORDER);
	$out = array();
	foreach ($rows as $row) {
		preg_match_all("/'([a-z0-9-]+)'/", $row[2], $m);
		$out[$row[1]] = $m[1];
	}
	return $out;
}

function test_designer_blocks_palette_is_found()
{
	$ctypes = _test_blocks_palette_ctypes();
	pg_assert_true(count($ctypes) > 0, '_sdBlockPaletteItems() is found in style_designer.js');
	pg_assert_same(array_values(array_unique($ctypes)), $ctypes, 'no block is listed twice');
	pg_assert_true(count(_test_blocks_group_prefixes()) > 0, '_sdBlockPaletteGroups() is found');
	pg_assert_true(count(_test_blocks_css_names()) > 0, '_SD_BLOCK_CSS is found');
	pg_assert_true(count(_test_blocks_css_needs()) > 0, '_SD_BLOCK_CSS_NEEDS is found');
}

function test_designer_blocks_every_palette_entry_has_a_builder()
{
	$js = _test_blocks_js();
	$missing = array();
	foreach (_test_blocks_palette_ctypes() as $ctype) {
		if (strpos($js, "ctype === '" . $ctype . "'") === false) {
			$missing[] = $ctype;
		}
	}
	pg_assert_same(array(), $missing, 'palette entries without a createFromPalette() builder');
}

function test_designer_blocks_every_palette_entry_has_a_group()
{
	$prefixes = _test_blocks_group_prefixes();
	$orphans = array();
	foreach (_test_blocks_palette_ctypes() as $ctype) {
		$home = false;
		foreach ($prefixes as $prefix) {
			if (strpos($ctype, $prefix) === 0) {
				$home = true;
				break;
			}
		}
		if (!$home) {
			$orphans[] = $ctype;
		}
	}
	pg_assert_same(array(), $orphans, 'palette entries that would fall into "Other"');
}

function test_designer_blocks_every_needed_rule_exists()
{
	$names = _test_blocks_css_names();
	$missing = array();
	foreach (_test_blocks_css_needs() as $ctype => $rules) {
		foreach ($rules as $rule) {
			if (!in_array($rule, $names, true)) {
				$missing[] = $ctype . ' => ' . $rule;
			}
		}
	}
	pg_assert_same(array(), $missing, '_SD_BLOCK_CSS_NEEDS names that are not keys of _SD_BLOCK_CSS');
}

function test_designer_blocks_rules_never_use_important()
{
	pg_assert_false(stripos(_test_blocks_css_body(), '!important') !== false, 'no _SD_BLOCK_CSS rule uses !important');
}
