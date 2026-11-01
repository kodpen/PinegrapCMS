<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Light-theme contrast of the SEO score figure in the Pages and Products
 * lists (4.5:1) and of four panel icons (3:1): the pinned note, the
 * Workspace timeline check, the dashboard stat icons and the section
 * toggle of the product editor.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function panel_icon_score_source($path)
{
	return (string) file_get_contents(dirname(__DIR__) . '/pinegrap/' . $path);
}

function panel_icon_score_luminance($hex)
{
	$hex = ltrim($hex, '#');
	$sum = 0;

	foreach (array(0 => 0.2126, 2 => 0.7152, 4 => 0.0722) as $offset => $weight) {
		$c = hexdec(substr($hex, $offset, 2)) / 255;
		$c = ($c <= 0.03928) ? $c / 12.92 : pow(($c + 0.055) / 1.055, 2.4);
		$sum += $weight * $c;
	}

	return $sum;
}

function panel_icon_score_ratio($a, $b)
{
	$la = panel_icon_score_luminance($a);
	$lb = panel_icon_score_luminance($b);

	return (max($la, $lb) + 0.05) / (min($la, $lb) + 0.05);
}

// color-mix(in srgb, $a $percent%, $b) as a hex value.
function panel_icon_score_mix($a, $percent, $b)
{
	$a = ltrim($a, '#');
	$b = ltrim($b, '#');
	$out = '#';

	for ($i = 0; $i < 6; $i += 2) {
		$c = hexdec(substr($a, $i, 2)) * $percent / 100 + hexdec(substr($b, $i, 2)) * (100 - $percent) / 100;
		$out .= sprintf('%02x', (int) round($c));
	}

	return $out;
}

// The body of the first CSS rule whose selector line is exactly $selector.
function panel_icon_score_rule($css, $selector)
{
	$start = strpos($css, "\n" . $selector . " {\n");

	if ($start === false) {
		return '';
	}

	return substr($css, $start, strpos($css, '}', $start) - $start);
}

// The orange band's figure is the orange pulled toward the text colour; the
// bar keeps the plain orange fill.
function test_panel_seo_list_score_orange_figure()
{
	$seo = panel_icon_score_source('seo.php');

	// seo.php needs the database, so its band function is run on its own.
	pg_assert_true(preg_match('/\nfunction pg_seo_score_color\(\$score\)\n\{\n.*?\n\}\n/s', $seo, $m) === 1, 'the band function is found');

	if (!function_exists('panel_icon_score_band')) {
		eval(str_replace('function pg_seo_score_color(', 'function panel_icon_score_band(', $m[0]));
	}

	$color = panel_icon_score_band(45);
	pg_assert_same('#fd7e14', $color['style'], 'the bar keeps the orange fill');
	pg_assert_same('color-mix(in srgb, #fd7e14 55%, var(--pg-fg-emphasis))', isset($color['ink']) ? $color['ink'] : '', 'the figure takes the orange ink');

	$light = panel_icon_score_mix('#fd7e14', 55, '#000000');
	$dark = panel_icon_score_mix('#fd7e14', 55, '#ffffff');
	pg_assert_true(panel_icon_score_ratio($light, '#f5f5f5') >= 4.5, 'the light figure reads 4.5:1 on the list row');
	pg_assert_true(panel_icon_score_ratio($light, '#ffffff') >= 4.5, 'the light figure reads 4.5:1 on white');
	pg_assert_true(panel_icon_score_ratio($dark, '#2b3035') >= 4.5, 'the dark figure reads 4.5:1 on the dark row');

	pg_assert_true(strpos($seo, "\$text_style = ' style=\"color:' . \$color['ink'] . '\"';") !== false, 'the list figure prints the ink, not the fill');
}

// Icons drawn in a pastel fill take the theme's ink.
function test_panel_icons_read_three_to_one()
{
	$css = panel_icon_score_source('assets/css/backend.src.css');

	pg_assert_true(strpos(panel_icon_score_rule($css, '.ws-nb-item-head > .ws-nb-item-pin'), 'color: var(--pg-primary-ink);') !== false, 'the note pin reads the primary ink');
	pg_assert_true(strpos(panel_icon_score_rule($css, '.pg-settings-toggle-icon'), 'color: var(--pg-primary-ink);') !== false, 'the section toggle icon reads the primary ink');
	pg_assert_true(preg_match('/\n\.ws-home-tl-dot \{[^}]*\n  color: var\(--pg-success-ink\);/', $css) === 1, 'the timeline check reads the success ink');
	pg_assert_true(strpos(panel_icon_score_rule($css, '.pg-eg-stat .bi'), 'color: color-mix(in srgb, var(--pg-eg-ink) 75%, var(--pg-fg-emphasis));') !== false, 'a stat icon is its tone pulled toward the text colour');

	pg_assert_true(panel_icon_score_ratio('#1565c0', '#f5f5f5') >= 3, 'the primary ink reads 3:1 on the light toggle');
	pg_assert_true(panel_icon_score_ratio('#90caf9', '#2b3035') >= 3, 'the dark primary ink reads 3:1');
	pg_assert_true(panel_icon_score_ratio('#2e7d32', '#ffffff') >= 3, 'the success ink reads 3:1 on white');
	pg_assert_true(panel_icon_score_ratio('#a5d6a7', '#2b3035') >= 3, 'the dark success ink reads 3:1');

	// Both stat tones on their 8% tint over the light (#ecfeff) and dark (#0c1416) card.
	foreach (array('#10b981', '#3b82f6') as $tone) {
		$light_ground = panel_icon_score_mix($tone, 8, '#ecfeff');
		$dark_ground = panel_icon_score_mix($tone, 8, '#0c1416');
		pg_assert_true(panel_icon_score_ratio(panel_icon_score_mix($tone, 75, '#000000'), $light_ground) >= 3, $tone . ' stat icon reads 3:1 in light');
		pg_assert_true(panel_icon_score_ratio(panel_icon_score_mix($tone, 75, '#ffffff'), $dark_ground) >= 3, $tone . ' stat icon reads 3:1 in dark');
	}
}
