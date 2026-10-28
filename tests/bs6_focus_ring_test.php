<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source test for the panel's focus ring on the Bootstrap 6 focus-ring
 * tokens: rules read --pg-focus-ring-width / --pg-focus-ring-color, the
 * colour is declared once, and no ring is built from an -rgb triple or
 * Bootstrap's variables directly.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function bs6_focus_ring_test_css($path)
{
	return (string) file_get_contents(dirname(__DIR__) . '/pinegrap/assets/css/' . $path);
}

// The source text of the declarations with the comments taken out.
function bs6_focus_ring_test_code($path)
{
	return preg_replace('~/\*.*?\*/~s', '', bs6_focus_ring_test_css($path));
}

function test_bs6_focus_ring_tokens_declared()
{
	$css = bs6_focus_ring_test_code('backend.src.css');

	pg_assert_same(1, preg_match_all('/--pg-focus-ring-width:\s*var\(--bs-focus-ring-width\);/', $css), 'width alias');
	pg_assert_same(1, preg_match_all('/--pg-focus-ring-color:\s*color-mix\(in srgb, var\(--pg-primary\) 35%, transparent\);/', $css), 'panel ring colour');
	pg_assert_same(1, preg_match_all('/--bs-focus-ring-color:/', $css), 'Bootstrap ring colour declared once');
	pg_assert_same(1, preg_match_all('/--bs-focus-ring-color:\s*var\(--pg-focus-ring-color\);/', $css), 'bridge reads the panel colour');
	pg_assert_same(0, preg_match_all('/--pg-focus-ring-color:\s*var\(--bs-focus-ring-color\)/', $css), 'no alias of the Bootstrap colour');
}

function test_bs6_focus_ring_rules_read_pg_tokens()
{
	foreach (array('backend.src.css', 'designer_home.css') as $path) {
		$css = bs6_focus_ring_test_code($path);
		// Only the bridge line may name the Bootstrap ring variables.
		$css = preg_replace('/^\s*--bs-focus-ring-color:\s*var\(--pg-focus-ring-color\);$/m', '', $css);
		$css = preg_replace('/^\s*--pg-focus-ring-width:\s*var\(--bs-focus-ring-width\);$/m', '', $css);
		pg_assert_same(0, preg_match_all('/var\(--bs-focus-ring-(color|width|offset)\b/', $css), $path . ' reads no --bs-focus-ring-* in a rule');
	}

	$css = bs6_focus_ring_test_code('backend.src.css');
	pg_assert_same(0, preg_match_all('/0 0 0 0\.2rem rgba\(var\(--pg-primary-rgb\)/', $css), 'the global ring has no -rgb colour');
}
