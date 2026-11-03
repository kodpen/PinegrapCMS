<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source test for the panel's colour tokens on Bootstrap 6: the neutral
 * bridge reads Bootstrap 6's gray scale instead of Bootstrap 5's literal
 * greys, text and line neutrals are Bootstrap 6's own, and the v5 accent
 * names nothing reads any more (-rgb, -text-emphasis, -border-subtle) are
 * gone from the accent bridge.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// backend.src.css with the comments taken out.
function bs6_colour_tokens_test_code()
{
	$css = (string) file_get_contents(dirname(__DIR__) . '/pinegrap/assets/css/backend.src.css');
	return preg_replace('~/\*.*?\*/~s', '', $css);
}

function test_bs6_colour_tokens_surfaces_on_the_gray_scale()
{
	$css = bs6_colour_tokens_test_code();

	pg_assert_same(1, preg_match_all('/--bs-bg-1:\s*light-dark\(var\(--bs-gray-025\), var\(--bs-gray-900\)\);/', $css), 'bg-1 on the gray scale');
	pg_assert_same(1, preg_match_all('/--bs-bg-2:\s*light-dark\(var\(--bs-gray-050\), var\(--bs-gray-800\)\);/', $css), 'bg-2 on the gray scale');
	pg_assert_same(1, preg_match_all('/--bs-bg-1:/', $css), 'bg-1 declared once');
	pg_assert_same(1, preg_match_all('/--bs-bg-2:/', $css), 'bg-2 declared once');
}

function test_bs6_colour_tokens_text_and_lines_are_bootstraps()
{
	$css = bs6_colour_tokens_test_code();

	foreach (array('--bs-fg-body', '--bs-fg-2', '--bs-fg-3', '--bs-border-color-translucent') as $name) {
		pg_assert_same(0, preg_match_all('/' . preg_quote($name, '/') . ':/', $css), $name . ' is left to Bootstrap 6');
	}
	// The only border colour set is the navbar separator's, on the gray scale.
	pg_assert_same(0, preg_match_all('/--bs-border-color:\s*+(?!var\(--bs-gray-)/', $css), 'border colour only from the gray scale');
	// No Bootstrap 5 grey literal is left in a neutral declaration.
	foreach (array('#f8f9fa', '#e9ecef', '#2b3035', '#343a40', '#212529', '#dee2e6', '#495057') as $hex) {
		pg_assert_same(0, preg_match_all('/--bs-(bg|fg|border)[a-z0-9-]*:[^;]*' . $hex . '/i', $css), $hex . ' not in a neutral');
	}
}

function test_bs6_colour_tokens_no_dead_v5_accent_names()
{
	$css = bs6_colour_tokens_test_code();

	pg_assert_same(0, preg_match_all('/--bs-(primary|secondary|success|danger|warning|info)-(rgb|text-emphasis|border-subtle):/', $css), 'v5 accent names');
	pg_assert_same(0, preg_match_all('/--bs-link-(hover-)?color-rgb:/', $css), 'v5 link -rgb names');
	// The plain fills stay: panel charts read them (view_orders.php, erp_profit.php).
	foreach (array('primary', 'secondary', 'success', 'danger', 'warning', 'info') as $tone) {
		pg_assert_same(1, preg_match_all('/--bs-' . $tone . ':\s*var\(--pg-' . $tone . '\);/', $css), $tone . ' fill for the charts');
	}
	pg_assert_same(1, preg_match_all('/--bs-link-color:/', $css), 'link colour declared once');
	pg_assert_same(1, preg_match_all('/--bs-link-hover-color:/', $css), 'link hover colour declared once');
}
