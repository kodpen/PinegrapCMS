<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * System Status gauge motion: the blurred layers sit in their own SVGs apart
 * from the animated cells, so a cell frame never re-runs a blur; the glow and
 * bloom breathe on their layer, every infinite animation stays behind
 * prefers-reduced-motion, an off-screen dial pauses, and the loading curtain
 * stops its loops once it is done.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function dashboard_gauge_source($path)
{
	return (string) file_get_contents(dirname(__DIR__) . '/pinegrap/' . $path);
}

// Every <svg …>…</svg> element of the gauge markup, opening tag and body.
function dashboard_gauge_svgs()
{
	preg_match_all('/<svg\b([^>]*)>(.*?)<\/svg>/s', dashboard_gauge_source('includes/dashboard/widgets/widget_2.php'), $matches, PREG_SET_ORDER);

	return $matches;
}

// The CSS with its comments removed, so a word in a note never counts as a rule.
function dashboard_gauge_css()
{
	return (string) preg_replace('#/\*.*?\*/#s', '', dashboard_gauge_source('assets/css/backend.src.css'));
}

// The body of the @media block that opens at $offset, braces balanced.
function dashboard_gauge_block($css, $offset)
{
	$start = strpos($css, '{', $offset);
	$depth = 0;

	for ($i = $start; $i < strlen($css); $i++) {
		if ($css[$i] === '{') {
			$depth++;
		} elseif ($css[$i] === '}' && --$depth === 0) {
			return substr($css, $start + 1, $i - $start - 1);
		}
	}

	return '';
}

function test_dashboard_gauge_cells_svg_has_no_filter()
{
	$images = 0;

	foreach (dashboard_gauge_svgs() as $svg) {
		if (strpos($svg[1], 'role="img"') === false) {
			continue;
		}

		$images++;
		pg_assert_contains('pg-health-cells', $svg[2], 'the cells sit in the labelled SVG');
		pg_assert_contains('aria-label="', $svg[1], 'the labelled SVG keeps its label');
		pg_assert_true(strpos($svg[2], 'filter=') === false && strpos($svg[2], '<filter') === false, 'the SVG with the animated cells holds no filter');
	}

	pg_assert_same(1, $images, 'exactly one SVG of the gauge is an image');
}

function test_dashboard_gauge_filters_in_hidden_layers()
{
	$filtered = 0;

	foreach (dashboard_gauge_svgs() as $svg) {
		if (strpos($svg[2], 'filter="url(#pg_health_') === false) {
			continue;
		}

		$filtered++;
		pg_assert_contains('pg-health-layer', $svg[1], 'a filtered SVG is a layer');
		pg_assert_contains('aria-hidden="true"', $svg[1], 'a layer is hidden from assistive technology');
		pg_assert_contains('focusable="false"', $svg[1], 'a layer takes no focus');
	}

	pg_assert_same(3, $filtered, 'bloom, glow and halo each sit in their own layer');
}

function test_dashboard_gauge_pauses_off_screen()
{
	$widget = dashboard_gauge_source('includes/dashboard/widgets/widget_2.php');
	$css = dashboard_gauge_css();

	pg_assert_contains('IntersectionObserver', $widget, 'the dial watches whether it is on screen');
	pg_assert_contains('.disconnect()', $widget, 'a redraw replaces the observer rather than adding one');
	pg_assert_contains('is-paused', $widget);
	pg_assert_true((bool) preg_match('/\.pg-health-dial\.is-paused \.pg-health-cells > rect,\s*\.pg-health-dial\.is-paused \.pg-health-layer\s*\{\s*animation-play-state:\s*paused;/', $css), 'a paused dial stops its cells and layers');
}

function test_dashboard_gauge_infinite_only_without_reduced_motion()
{
	$css = dashboard_gauge_css();
	$outside = $css;
	$inside = '';
	$offset = 0;

	while (($pos = strpos($css, '@media (prefers-reduced-motion: no-preference)', $offset)) !== false) {
		$block = dashboard_gauge_block($css, $pos);
		$inside .= $block;
		$outside = str_replace($block, '', $outside);
		$offset = $pos + 1;
	}

	pg_assert_false((bool) preg_match('/animation-name:\s*pg-health-(cell|glow|bloom)/', $outside), 'no gauge animation outside the no-preference block');
	pg_assert_contains('animation-name: pg-health-cell;', $inside);

	foreach (array('.pg-health-layer-glow', '.pg-health-layer-bloom') as $layer) {
		pg_assert_true((bool) preg_match('/' . preg_quote($layer, '/') . '\s*\{[^}]*animation-name:\s*pg-health-/', $inside), $layer . ' breathes');
	}

	pg_assert_false((bool) preg_match('/\.pg-health-(glow|bloom)\s*[,{][^}]*animation-name/', $inside), 'the filtered circles themselves do not animate');
}

function test_dashboard_gauge_layers_composited()
{
	$css = dashboard_gauge_css();

	pg_assert_true((bool) preg_match('/\.pg-health-gauge\s*\{[^}]*will-change:\s*transform/', $css), 'the cells SVG has its own layer');
	pg_assert_true((bool) preg_match('/\.pg-health-layer\s*\{[^}]*position:\s*absolute/', $css), 'the layers stack under the cells');
	pg_assert_true((bool) preg_match('/\.pg-health-layer-glow,\s*\.pg-health-layer-bloom\s*\{[^}]*will-change:\s*opacity/', $css), 'the breathing layers are composited');
}

function test_dashboard_gauge_curtain_pauses_when_done()
{
	pg_assert_true((bool) preg_match('/\.pg-preloader\.is-done \.pg-preloader-bar i,\s*\.pg-preloader\.is-done \.pg-preloader-dots i\s*\{\s*animation-play-state:\s*paused;/', dashboard_gauge_css()), 'the done curtain stops its loops');
}
