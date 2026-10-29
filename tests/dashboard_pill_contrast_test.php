<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Dashboard card pills and secondary rows: a tinted pill's text is its tone
 * pulled halfway toward the strongest text colour, so it reads at 4.5:1 on
 * its tint in both themes; zero-count and expired rows and the Comments
 * card's location line are drawn without opacity; the headline's direction
 * arrow has a text alternative.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function dashboard_pill_source($path)
{
	return (string) file_get_contents(dirname(__DIR__) . '/pinegrap/' . $path);
}

function dashboard_pill_rgb($hex)
{
	$hex = ltrim($hex, '#');

	return array(hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2)));
}

// Channel-wise sRGB mix, as color-mix(in srgb, a w, b) and alpha compositing do.
function dashboard_pill_mix($a, $b, $weight)
{
	$ra = dashboard_pill_rgb($a);
	$rb = dashboard_pill_rgb($b);
	$out = '#';

	for ($i = 0; $i < 3; $i++) {
		$out .= sprintf('%02x', (int) round($ra[$i] * $weight + $rb[$i] * (1 - $weight)));
	}

	return $out;
}

function dashboard_pill_luminance($hex)
{
	$sum = 0;

	foreach (dashboard_pill_rgb($hex) as $i => $value) {
		$c = $value / 255;
		$c = ($c <= 0.03928) ? $c / 12.92 : pow(($c + 0.055) / 1.055, 2.4);
		$sum += array(0.2126, 0.7152, 0.0722)[$i] * $c;
	}

	return $sum;
}

function dashboard_pill_ratio($a, $b)
{
	$la = dashboard_pill_luminance($a);
	$lb = dashboard_pill_luminance($b);

	return (max($la, $lb) + 0.05) / (min($la, $lb) + 0.05);
}

// Each pill prints its tone over a 0x22 tint of itself and takes the mixed text colour.
function test_dashboard_pill_markup()
{
	$mix = 'color-mix(in srgb, \' . $%s . \' 50%%, var(--pg-fg-emphasis))';
	$cases = array(
		'includes/dashboard/widgets/widget_3.php' => "p['color']",
		'includes/dashboard/widgets/widget_4.php' => 'eg_role_color',
		'includes/dashboard/widgets/widget_14.php' => 'color',
	);

	foreach ($cases as $path => $var) {
		$source = dashboard_pill_source($path);
		pg_assert_true(strpos($source, 'color:' . sprintf($mix, $var)) !== false, $path . ' mixes the pill text toward the text colour');
		pg_assert_true(strpos($source, '22;color:\' . $' . $var . ' . \'') === false, $path . ' no longer prints the bare tone on its tint');
	}
}

// The mix reaches 4.5:1 for every tone on every card ground in both themes.
function test_dashboard_pill_ratios()
{
	$tones = array('#f59e0b', '#3b82f6', '#8b5cf6', '#10b981', '#ef4444', '#6b7280', '#a1a1a1');
	$themes = array(
		'light' => array('ink' => '#000000', 'grounds' => array('#ecfdf5', '#ecfeff', '#f5f3ff')),
		'dark' => array('ink' => '#ffffff', 'grounds' => array('#0c1410', '#0c1416', '#10101a')),
	);

	foreach ($themes as $theme => $set) {
		foreach ($set['grounds'] as $ground) {
			foreach ($tones as $tone) {
				$pill = dashboard_pill_mix($tone, $ground, 0x22 / 255);
				$text = dashboard_pill_mix($tone, $set['ink'], 0.5);
				$ratio = dashboard_pill_ratio($text, $pill);
				pg_assert_true($ratio >= 4.5, sprintf('%s %s on %s: %.2f', $theme, $tone, $ground, $ratio));
			}
		}
	}
}

// Secondary rows take the secondary text colour, never an opacity.
function test_dashboard_rows_without_opacity()
{
	$widgets = dashboard_pill_source('includes/dashboard/widgets.php');
	pg_assert_true(strpos($widgets, "(\$muted ? ' fg-2' : '')") !== false, 'a muted row takes .fg-2');

	foreach (array('includes/dashboard/widgets.php', 'includes/dashboard/widgets/widget_14.php') as $path) {
		pg_assert_true(strpos(dashboard_pill_source($path), 'opacity-50') === false, $path . ' has no opacity-50');
	}

	pg_assert_true(strpos(dashboard_pill_source('includes/dashboard/widgets/widget_25.php'), 'opacity:.75') === false, 'the Comments location line has no opacity');
}

// The headline arrow is decorative; its direction is said in words.
function test_dashboard_headline_direction_named()
{
	require_once(PG_FUNCTIONS_DIR . '/includes/dashboard/widgets.php');

	foreach (array(array(5, 2, 'Up from yesterday'), array(2, 5, 'Down from yesterday'), array(3, 3, 'Unchanged from yesterday')) as $case) {
		$html = pg_widget_headline(array('id' => 'w8_test', 'unit' => 'orders', 'series' => array(1, 1, 1, 1, 1, $case[1], $case[0]), 'rgb' => '0,0,0'));
		pg_assert_true(strpos($html, 'pg-head-dir') !== false, 'the arrow is printed');
		pg_assert_true((bool) preg_match('/<i class="bi [^"]*pg-head-dir[^"]*"[^>]*aria-hidden="true"/', $html), 'the arrow is hidden from assistive technology');
		pg_assert_true(strpos($html, '<span class="visually-hidden">' . h(lang($case[2])) . '</span>') !== false, $case[2]);
	}
}
