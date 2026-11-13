<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Contrast of the panel's pills and badges in both themes: filled theme
 * badges, badges on a subtle fill, the look's status pill (.pg-pill), and
 * the weight floor that keeps a badge's 12px text out of hairline weights.
 * Each text reads 4.5:1 on its own fill.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function panel_pill_source($path)
{
	return (string) file_get_contents(dirname(__DIR__) . '/pinegrap/' . $path);
}

function panel_pill_rgb($hex)
{
	$hex = ltrim($hex, '#');

	return array(hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2)));
}

// color-mix(in srgb, a <weight>, b).
function panel_pill_mix(array $a, array $b, $weight)
{
	$out = array();

	for ($i = 0; $i < 3; $i++) {
		$out[] = $a[$i] * $weight + $b[$i] * (1 - $weight);
	}

	return $out;
}

function panel_pill_ratio(array $a, array $b)
{
	$lum = function (array $c) {
		$sum = 0;

		foreach (array(0.2126, 0.7152, 0.0722) as $i => $weight) {
			$v = $c[$i] / 255;
			$v = ($v <= 0.03928) ? $v / 12.92 : pow(($v + 0.055) / 1.055, 2.4);
			$sum += $weight * $v;
		}

		return $sum;
	};

	$la = $lum($a);
	$lb = $lum($b);

	return (max($la, $lb) + 0.05) / (min($la, $lb) + 0.05);
}

// The value of a --pg-* token in the light (:root) and the dark theme block.
function panel_pill_token($css, $name)
{
	$light = strpos($css, '[data-bs-theme="light"] {');
	$dark = strpos($css, '[data-bs-theme="dark"] {');
	$values = array();

	foreach (array('light' => $light, 'dark' => $dark) as $theme => $start) {
		pg_assert_true($start !== false, 'the ' . $theme . ' theme block is found');

		if (preg_match('/--' . preg_quote($name, '/') . ':\s*(#[0-9a-fA-F]{6});/', $css, $match, 0, $start)) {
			$values[$theme] = $match[1];
		}
	}

	return $values;
}

// The text colour every theme's strongest text mixes toward (--pg-fg-emphasis).
function panel_pill_emphasis($theme)
{
	return ($theme === 'light') ? array(0, 0, 0) : array(255, 255, 255);
}

// A badge's 12px text is never set in the hairline weights: the floor is
// unlayered so it beats the weight utilities' layer, and the bundle has it.
function test_panel_pill_weight_floor()
{
	$css = panel_pill_source('assets/css/backend.src.css');
	$min = panel_pill_source('assets/css/backend.min.css');

	$floor = strpos($css, ".badge:is(.fw-lighter, .fw-light) {\n  font-weight: var(--pg-weight-regular);");
	$unlayered = strpos($css, '.software-navbar.fixed-top {');

	pg_assert_true($floor !== false, 'the badge weight floor is in backend.src.css');
	pg_assert_true($unlayered !== false && $floor > $unlayered, 'the floor sits in the unlayered part of the sheet');
	pg_assert_true(preg_match('/@layer\s+[\w.,\s]*\{/', substr($css, $unlayered, $floor - $unlayered)) === 0, 'no layer opens before the floor');
	pg_assert_true(preg_match('/\.badge:is\(\.fw-lighter,\s*\.fw-light\)\{font-weight:\s*var\(--pg-weight-regular\);?\}/', $min) === 1, 'backend.min.css carries the floor');
}

// A filled theme badge (.badge.theme-*, .badge.bg-*): the near-black accent
// ink on every pastel fill, both themes (the fills do not flip).
function test_panel_pill_filled_badges()
{
	$css = panel_pill_source('assets/css/backend.src.css');

	pg_assert_true(preg_match('/--pg-on-accent:\s*(#[0-9a-f]{6});/', $css, $ink) === 1, 'the accent ink is found');
	pg_assert_true(strpos($css, '--bs-primary-contrast: var(--pg-on-accent);') !== false, 'Bootstrap badges read the accent ink');

	foreach (array('primary', 'secondary', 'success', 'danger', 'warning', 'info') as $tone) {
		$fill = panel_pill_token($css, 'pg-' . $tone);
		pg_assert_true(isset($fill['light']), 'the ' . $tone . ' fill is found');
		$ratio = panel_pill_ratio(panel_pill_rgb($ink[1]), panel_pill_rgb($fill['light']));
		pg_assert_true($ratio >= 4.5, sprintf('a filled %s badge reads %.2f:1', $tone, $ratio));
	}
}

// A badge on a subtle fill (.badge-subtle, .bg-subtle-* + .fg-emphasis-*):
// the tone's ink pulled toward the strongest text colour, on Bootstrap's
// subtle fill. The fills are oklch mixes in Bootstrap 6; their sRGB values
// below are what Chromium computes for them.
function test_panel_pill_subtle_badges()
{
	$css = panel_pill_source('assets/css/backend.src.css');
	$fills = array(
		'primary' => array('light' => array(201, 234, 255), 'dark' => array(0, 28, 61)),
		'secondary' => array('light' => array(241, 242, 243), 'dark' => array(45, 49, 54)),
		'success' => array('light' => array(207, 241, 221), 'dark' => array(0, 39, 15)),
		'danger' => array('light' => array(255, 218, 216), 'dark' => array(54, 3, 9)),
		'warning' => array('light' => array(255, 246, 211), 'dark' => array(68, 46, 0)),
		'info' => array('light' => array(203, 242, 255), 'dark' => array(0, 40, 59)),
	);

	foreach ($fills as $tone => $grounds) {
		$pattern = '/^\.badge \{[^}]*--bs-' . $tone . '-fg: color-mix\(in srgb, var\(--pg-' . $tone . '-ink\) (\d+)%, var\(--pg-fg-emphasis\)\);[^}]*--bs-' . $tone . '-fg-emphasis: var\(--bs-' . $tone . '-fg\);/m';
		pg_assert_true(preg_match($pattern, $css, $mix) === 1, 'a badge mixes the ' . $tone . ' ink toward the text colour');

		$ink = panel_pill_token($css, 'pg-' . $tone . '-ink');

		foreach ($grounds as $theme => $fill) {
			pg_assert_true(isset($ink[$theme]), 'the ' . $theme . ' ' . $tone . ' ink is found');
			$text = panel_pill_mix(panel_pill_rgb($ink[$theme]), panel_pill_emphasis($theme), $mix[1] / 100);
			$ratio = panel_pill_ratio($text, $fill);
			pg_assert_true($ratio >= 4.5, sprintf('a subtle %s badge reads %.2f:1 in %s', $tone, $ratio, $theme));
		}
	}
}

// The look's status pill: the tone mixed toward the text colour on the
// tone's own tint, over the page and over a card, in both themes.
function test_panel_pill_status_pill()
{
	$css = panel_pill_source('assets/css/backend.src.css');

	pg_assert_true(preg_match('/^\.pg-pill \{[^}]*\n  color: color-mix\(in srgb, var\(--pg-tone, var\(--pg-tone-gray\)\) (\d+)%, var\(--pg-fg-emphasis\)\);/m', $css, $mix) === 1, 'the pill text mixes its tone toward the text colour');
	pg_assert_true(preg_match('/^\.pg-pill \{[^}]*background: color-mix\(in srgb, var\(--pg-tone, var\(--pg-tone-gray\)\) var\(--pg-tint-to\), transparent\);/m', $css) === 1, 'the pill fill is the tone at --pg-tint-to');
	pg_assert_true(preg_match_all('/--pg-tint-to: (\d+)%;/', $css, $tints) === 2, 'one tint strength per theme');
	preg_match_all('/--pg-tone-([a-z]+): light-dark\((#[0-9a-f]{6}), (#[0-9a-f]{6})\);/', $css, $tones, PREG_SET_ORDER);
	pg_assert_true(count($tones) >= 6, 'the six tones are found');

	$tint = array('light' => $tints[1][0] / 100, 'dark' => $tints[1][1] / 100);
	$grounds = array('light' => array('#ffffff', '#f5f5f5'), 'dark' => array('#101114', '#1c1e22'));

	foreach ($tones as $tone) {
		foreach (array('light' => $tone[2], 'dark' => $tone[3]) as $theme => $hex) {
			$text = panel_pill_mix(panel_pill_rgb($hex), panel_pill_emphasis($theme), $mix[1] / 100);

			foreach ($grounds[$theme] as $ground) {
				$fill = panel_pill_mix(panel_pill_rgb($hex), panel_pill_rgb($ground), $tint[$theme]);
				$ratio = panel_pill_ratio($text, $fill);
				pg_assert_true($ratio >= 4.5, sprintf('the %s pill reads %.2f:1 in %s on %s', $tone[1], $ratio, $theme, $ground));
			}
		}
	}
}

// A badge's state is never drawn with opacity (panel-content-contrast.md).
function test_panel_pill_no_opacity_on_badges()
{
	$css = panel_pill_source('assets/css/backend.src.css');

	preg_match_all('/^[^{}\n]*(?:\.badge|\.pg-pill)\b[^{}\n]*\{[^}]*\bopacity:\s*0?\.\d+/m', $css, $faded);
	pg_assert_true(count($faded[0]) === 0, 'no badge or pill rule fades its text: ' . implode(' | ', array_map('trim', $faded[0])));
}
