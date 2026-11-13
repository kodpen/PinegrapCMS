<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the built-in looks and colour palettes of the visual editor
 * (includes/fn/design_themes.php): every look has its stylesheet and a card
 * preview the editor can draw, every palette has a current stylesheet, and
 * the generator keeps text readable on both colours in light and dark mode,
 * and a colour too pale to be seen on a white page still gives its controls
 * an outline, a focus ring and a mark that can be seen there, and a colour
 * too dark to be seen on the dark surfaces draws its lines lifted there.
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
	// base.css paints the alert's leading edge in --pg-alert-accent-color
	// whatever the accent's width: with no accent the token stays unset, or
	// that one edge is drawn in the text colour and the other three are not.
	pg_assert_same('0px', $precision['--pg-alert-accent'], 'precision: no accent bar on alerts');
	pg_assert_false(isset($precision['--pg-alert-accent-color']), 'precision: an alert without an accent has four like edges');
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
	// bg-body-tertiary in dark mode: the store's account bar, light bands.
	$dark_tertiary = array(43, 48, 53);
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
			pg_assert_true(_pg_theme_contrast($fg, $dark_tertiary) >= 4.5, $key . ': ' . $role . ' as text reads at 4.5:1 on the dark tertiary surface');
		}
	}
}

// The text a palette stylesheet gives $var in the block that starts with
// $selector; null when it is missing.
function design_themes_catalog_test_raw($css, $selector, $var)
{
	$start = strpos($css, $selector . ' {');
	if ($start === false) return null;
	$end = strpos($css, "\n}", $start);
	$block = substr($css, $start, $end - $start);
	if (!preg_match('/\s' . preg_quote($var, '/') . ':\s*([^\n]+);/', $block, $m)) return null;
	return $m[1];
}

function test_design_themes_catalog_pale_colour_draws_its_lines_in_the_readable_shade()
{
	$white   = array(255, 255, 255);
	$dark_bg = array(33, 37, 41);
	$light   = ":root,\n[data-bs-theme=light]";
	$pale    = array();
	foreach (pg_design_palettes() as $key => $p) {
		$css = pg_design_palette_css($p['primary'], $p['secondary'], $p['name']);
		foreach (array('primary', 'secondary') as $role) {
			$c    = _pg_theme_rgb($p[$role]);
			$line = design_themes_catalog_test_var($css, $light, '--pg-' . $role . '-line');
			if (_pg_theme_contrast($c, $white) >= 3) {
				// The colour is its own line on white; a dark line of its own,
				// if any, is the dark-surface test's.
				pg_assert_true(($line === null) || ($line === $c), $key . ': ' . $role . ' is seen on white and is its own line there');
				continue;
			}
			$pale[] = $key . '/' . $role;
			pg_assert_true($line !== null, $key . ': pale ' . $role . ' sets a line colour for the white page');
			pg_assert_same(design_themes_catalog_test_var($css, $light, '--pg-' . $role . '-fg'), $line, $key . ': the line of pale ' . $role . ' is its readable shade');
			pg_assert_true(_pg_theme_contrast($line, $white) >= 3, $key . ': the line of pale ' . $role . ' is seen at 3:1 on white');
			pg_assert_same(_pg_theme_rgb_list($line), design_themes_catalog_test_raw($css, $light, '--pg-' . $role . '-line-rgb'), $key . ': the line of pale ' . $role . ' comes as an RGB list too');
			// On the dark body the colour itself is seen again.
			$dark = design_themes_catalog_test_var($css, '[data-bs-theme=dark]', '--pg-' . $role . '-line');
			pg_assert_same($c, $dark, $key . ': on the dark body the line of ' . $role . ' is the colour itself');
			pg_assert_same(_pg_theme_rgb_list($c), design_themes_catalog_test_raw($css, '[data-bs-theme=dark]', '--pg-' . $role . '-line-rgb'), $key . ': and its RGB list');
			pg_assert_true(_pg_theme_contrast($dark, $dark_bg) >= 3, $key . ': ' . $role . ' is seen at 3:1 on the dark body');
		}
	}
	pg_assert_same(array('silver/primary', 'electric/secondary'), $pale, 'the pale colours of the built-in palettes');
}

// Most palette colours are too dark to be seen as a line on the dark
// surfaces (Fuchsia's secondary #312e81 measured 1.17:1 on bg-body-tertiary):
// the outline of an outline button vanished in dark mode. Such a colour
// draws its lines lifted to 3:1 there, and keeps itself as the line of the
// light block, so a light section inside a dark page does not inherit it.
function test_design_themes_catalog_dark_colour_draws_its_lines_lifted_on_dark_surfaces()
{
	$dark_bg       = array(33, 37, 41);
	$dark_tertiary = array(43, 48, 53);
	$light         = ":root,\n[data-bs-theme=light]";
	$lifted        = 0;
	foreach (pg_design_palettes() as $key => $p) {
		$css = pg_design_palette_css($p['primary'], $p['secondary'], $p['name']);
		foreach (array('primary', 'secondary') as $role) {
			$c    = _pg_theme_rgb($p[$role]);
			$dark = design_themes_catalog_test_var($css, '[data-bs-theme=dark]', '--pg-' . $role . '-line');
			$seen = ($dark !== null) ? $dark : $c;
			pg_assert_true(_pg_theme_contrast($seen, $dark_tertiary) >= 3, $key . ': the line of ' . $role . ' is seen at 3:1 on the dark tertiary surface');
			pg_assert_true(_pg_theme_contrast($seen, $dark_bg) >= 3, $key . ': and on the dark body');
			if (_pg_theme_contrast($c, $dark_tertiary) >= 3) {
				pg_assert_true(($dark === null) || ($dark === $c), $key . ': ' . $role . ' seen on the dark surfaces is its own line there');
				continue;
			}
			$lifted++;
			pg_assert_same(_pg_theme_rgb_list($dark), design_themes_catalog_test_raw($css, '[data-bs-theme=dark]', '--pg-' . $role . '-line-rgb'), $key . ': the lifted line of ' . $role . ' comes as an RGB list too');
			pg_assert_same($c, design_themes_catalog_test_var($css, $light, '--pg-' . $role . '-line'), $key . ': the light block keeps ' . $role . ' itself as its line');
			pg_assert_same(_pg_theme_rgb_list($c), design_themes_catalog_test_raw($css, $light, '--pg-' . $role . '-line-rgb'), $key . ': with its RGB list');
		}
	}
	pg_assert_true($lifted > 30, 'most built-in colours are lifted on the dark surfaces');

	// A palette whose colours are seen everywhere writes no line at all.
	$css = pg_design_palette_css('#d97706', '#a1a6b0');
	pg_assert_false(strpos($css, '--pg-primary-line') !== false, 'amber is its own line in both modes');
}

function test_design_themes_catalog_focus_is_seen_on_white()
{
	$white = array(255, 255, 255);
	$light = ":root,\n[data-bs-theme=light]";
	foreach (pg_design_palettes() as $key => $p) {
		$css    = pg_design_palette_css($p['primary'], $p['secondary'], $p['name']);
		$border = design_themes_catalog_test_var($css, $light, '--pg-primary-focus-border');
		// Bootstrap's own focused border (#86b7fe) stands at 2:1 on white.
		pg_assert_true(_pg_theme_contrast($border, $white) >= 1.6, $key . ': the border of a focused field is seen on white');
		// The ring is the colour at a quarter strength: seen only when the
		// colour it is mixed from is.
		$ring = design_themes_catalog_test_raw($css, $light, '--bs-focus-ring-color');
		pg_assert_true((bool)preg_match('/^rgba\((\d+), (\d+), (\d+), 0\.25\)$/', (string)$ring, $m), $key . ': the focus ring is the colour at a quarter strength');
		pg_assert_true(_pg_theme_contrast(array((int)$m[1], (int)$m[2], (int)$m[3]), $white) >= 3, $key . ': the focus ring is mixed from a colour seen on white');
	}
}

function test_design_themes_catalog_pale_primary_carries_dark_marks()
{
	$white = array(255, 255, 255);
	$light = ":root,\n[data-bs-theme=light]";
	$marks = array('--pg-check-mark', '--pg-radio-mark', '--pg-dash-mark', '--pg-switch-mark');
	foreach (pg_design_palettes() as $key => $p) {
		$css = pg_design_palette_css($p['primary'], $p['secondary'], $p['name']);
		$c   = _pg_theme_rgb($p['primary']);
		foreach ($marks as $mark) {
			$image = design_themes_catalog_test_raw($css, $light, $mark);
			if (_pg_theme_contrast($c, $white) >= 3) {
				// Bootstrap's white tick is seen on the colour.
				pg_assert_true($image === null, $key . ': ' . $mark . ' stays Bootstrap\'s white');
				continue;
			}
			$on = design_themes_catalog_test_var($css, $light, '--pg-on-primary');
			pg_assert_true(_pg_theme_contrast($c, $on) >= 3, $key . ': the text colour on the primary is seen on it');
			pg_assert_true($image !== null && strpos($image, 'url("data:image/svg+xml,') === 0, $key . ': ' . $mark . ' is an image');
			pg_assert_contains('%23' . ltrim(_pg_theme_hex($on), '#'), (string)$image, $key . ': ' . $mark . ' is drawn in the text colour on the primary');
			pg_assert_false(strpos((string)$image, '%23fff') !== false, $key . ': ' . $mark . ' is not white');
			// Nothing in it can end the declaration or the rule.
			pg_assert_false((bool)preg_match('/[;{}<>]/', (string)$image), $key . ': ' . $mark . ' is one declaration');
		}
	}
}

function test_design_themes_catalog_bridge_reads_what_a_pale_colour_sets()
{
	$bridge = (string)@file_get_contents(pg_design_theme_dir() . '/bridge.css');
	foreach (array('primary', 'secondary') as $role) {
		pg_assert_contains('--bs-btn-border-color: var(--pg-' . $role . '-line, var(--bs-' . $role . '));', $bridge, 'the outline button of ' . $role . ' takes the line colour');
		pg_assert_contains('--bs-btn-focus-shadow-rgb: var(--pg-' . $role . '-line-rgb, var(--bs-' . $role . '-rgb));', $bridge, 'the focus ring of a ' . $role . ' button takes the line colour');
	}
	foreach (array('--pg-check-mark', '--pg-radio-mark', '--pg-dash-mark', '--pg-switch-mark') as $mark) {
		pg_assert_contains('var(' . $mark . ', url("data:image/svg+xml,', $bridge, 'the bridge draws ' . $mark . ', Bootstrap\'s white image without it');
	}
	// Without a palette every fallback is Bootstrap's own white mark.
	pg_assert_same(4, substr_count($bridge, "%23fff'"), 'four white fallbacks');
}
