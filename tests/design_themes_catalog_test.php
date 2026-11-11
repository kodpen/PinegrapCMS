<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the built-in looks and colour palettes of the visual editor
 * (includes/fn/design_themes.php): every look has its stylesheet and a card
 * preview the editor can draw, every palette has a current stylesheet, and
 * the generator keeps text readable on both colours in light and dark mode.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The colour a palette stylesheet gives $var in the block that starts with
// $selector, as an RGB triple; null when it is missing.
function design_themes_catalog_test_var($css, $selector, $var)
{
	$start = strpos($css, $selector . ' {');
	if ($start === false) return null;
	$end = strpos($css, '}', $start);
	$block = substr($css, $start, $end - $start);
	if (!preg_match('/\s' . preg_quote($var, '/') . ':\s*(#[0-9a-f]{6});/i', $block, $m)) return null;
	return _pg_theme_rgb($m[1]);
}

function test_design_themes_catalog_new_looks_are_listed()
{
	$looks = pg_design_looks();
	foreach (array('display', 'precision') as $key) {
		pg_assert_true(isset($looks[$key]), 'look ' . $key . ' is listed');
	}
	pg_assert_same('modern-soft', pg_design_default_look(), 'the default look stays');
}

function test_design_themes_catalog_every_look_has_its_stylesheet()
{
	foreach (pg_design_looks() as $key => $look) {
		$file = pg_design_theme_dir() . '/looks/' . $key . '.css';
		pg_assert_true(is_file($file), 'looks/' . $key . '.css exists');
		$css = (string)@file_get_contents($file);
		pg_assert_contains('/*!' . "\n" . ' * Pinegrap look: ', $css, 'looks/' . $key . '.css starts with its header comment');
		pg_assert_contains(":root,\n[data-bs-theme] {", $css, 'looks/' . $key . '.css declares on :root and [data-bs-theme]');
		$tokens = pg_design_look_tokens($key);
		foreach (array('--pg-heading-weight', '--pg-heading-tracking', '--pg-display-weight', '--pg-radius', '--pg-shadow', '--pg-card-shadow') as $token) {
			pg_assert_true(isset($tokens[$token]), 'looks/' . $key . '.css sets ' . $token);
		}
		pg_assert_true(is_string($look['name']) && $look['name'] !== '', 'look ' . $key . ' has a name');
		pg_assert_true(is_string($look['description']) && $look['description'] !== '', 'look ' . $key . ' has a description');
	}
}

function test_design_themes_catalog_previews_use_known_keys()
{
	// The keys the look card (_sdThemeLookDemo()) and the design thumbnail
	// (pg_design_thumb_vars()) draw; anything else falls back to none / sans.
	$shadows  = array('none', 'soft', 'crisp', 'glow', 'hard', 'deep');
	$headings = array('sans', 'serif', 'rounded', 'mono', 'grotesk');
	foreach (pg_design_looks() as $key => $look) {
		$pv = $look['preview'];
		foreach (array('radius', 'btn_radius', 'shadow', 'border', 'heading', 'weight', 'caps') as $field) {
			pg_assert_true(array_key_exists($field, $pv), 'preview of ' . $key . ' has ' . $field);
		}
		pg_assert_true(in_array($pv['shadow'], $shadows, true), 'preview shadow of ' . $key . ' is known');
		pg_assert_true(in_array($pv['heading'], $headings, true), 'preview heading of ' . $key . ' is known');
		pg_assert_true(is_int($pv['weight']) && $pv['weight'] >= 100 && $pv['weight'] <= 900, 'preview weight of ' . $key . ' is a font weight');
		pg_assert_true(is_bool($pv['caps']), 'preview caps of ' . $key . ' is a boolean');
	}
}

function test_design_themes_catalog_new_looks_keep_their_character()
{
	$display = pg_design_look_tokens('display');
	pg_assert_true((int)$display['--pg-display-weight'] <= 300, 'display: light display type');
	pg_assert_true(strpos($display['--pg-heading-tracking'], '-') === 0, 'display: tight heading tracking');
	pg_assert_true($display['--pg-card-shadow'] !== 'none', 'display: soft depth on cards');

	$precision = pg_design_look_tokens('precision');
	foreach (array('--pg-radius', '--pg-radius-card', '--pg-radius-btn', '--pg-radius-input') as $token) {
		pg_assert_same('0', $precision[$token], 'precision: square ' . $token);
	}
	foreach (array('--pg-shadow-sm', '--pg-shadow', '--pg-shadow-lg', '--pg-card-shadow', '--pg-btn-shadow') as $token) {
		pg_assert_same('none', $precision[$token], 'precision: flat ' . $token);
	}
	pg_assert_true((int)$precision['--pg-heading-weight'] <= 400, 'precision: thin heading weight');
}

function test_design_themes_catalog_new_palettes_are_listed()
{
	$palettes = pg_design_palettes();
	foreach (array('onyx', 'silver', 'electric') as $key) {
		pg_assert_true(isset($palettes[$key]), 'palette ' . $key . ' is listed');
	}
	// No palette repeats another's pair of colours.
	$seen = array();
	foreach ($palettes as $key => $p) {
		$pair = strtolower($p['primary'] . '/' . $p['secondary']);
		pg_assert_false(isset($seen[$pair]), 'palette ' . $key . ' is not a copy of ' . (isset($seen[$pair]) ? $seen[$pair] : ''));
		$seen[$pair] = $key;
	}
}

function test_design_themes_catalog_every_palette_file_is_current()
{
	foreach (pg_design_palettes() as $key => $p) {
		$file = pg_design_theme_dir() . '/palettes/' . $key . '.css';
		pg_assert_true(is_file($file), 'palettes/' . $key . '.css exists (php tools/build_theme_palettes.php)');
		pg_assert_same(pg_design_palette_css($p['primary'], $p['secondary'], $p['name']), (string)@file_get_contents($file),
			'palettes/' . $key . '.css is current (php tools/build_theme_palettes.php)');
	}
}

function test_design_themes_catalog_palette_text_reads_in_light_and_dark()
{
	$white   = array(255, 255, 255);
	$dark_bg = array(33, 37, 41);
	foreach (pg_design_palettes() as $key => $p) {
		$css = pg_design_palette_css($p['primary'], $p['secondary'], $p['name']);
		foreach (array('primary', 'secondary') as $role) {
			$c  = _pg_theme_rgb($p[$role]);
			$on = design_themes_catalog_test_var($css, ":root,\n[data-bs-theme=light]", '--pg-on-' . $role);
			pg_assert_true(_pg_theme_contrast($c, $on) >= 4.5, $key . ': text on ' . $role . ' reads at 4.5:1');
			$fg = design_themes_catalog_test_var($css, ":root,\n[data-bs-theme=light]", '--pg-' . $role . '-fg');
			pg_assert_true(_pg_theme_contrast($fg, $white) >= 4.5, $key . ': ' . $role . ' as text reads at 4.5:1 on white');
			$fg = design_themes_catalog_test_var($css, '[data-bs-theme=dark]', '--pg-' . $role . '-fg');
			pg_assert_true(_pg_theme_contrast($fg, $dark_bg) >= 4.5, $key . ': ' . $role . ' as text reads at 4.5:1 on the dark body');
		}
	}
}
