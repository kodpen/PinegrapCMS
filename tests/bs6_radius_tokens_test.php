<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source test for the panel's corner radii on Bootstrap 6: fields and
 * buttons take Bootstrap 6's own control radii (the bridge no longer holds
 * fields on Bootstrap 5's smaller corners), and cards, dialogs and menus
 * share one surface radius token whose inner corners follow it.
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
function bs6_radius_tokens_test_code()
{
	$css = (string) file_get_contents(dirname(__DIR__) . '/pinegrap/assets/css/backend.src.css');
	return preg_replace('~/\*.*?\*/~s', '', $css);
}

// The declarations of every rule whose selector list is exactly $selector.
function bs6_radius_tokens_test_rule($css, $selector)
{
	preg_match_all('/(?:^|\})\s*' . preg_quote($selector, '/') . '\s*\{([^}]*)\}/', $css, $m);
	return implode("\n", $m[1]);
}

function test_bs6_radius_tokens_controls_are_bootstraps()
{
	$css = bs6_radius_tokens_test_code();

	// Fields and buttons of every size read Bootstrap 6's control radii.
	pg_assert_same(0, preg_match_all('/--bs-btn-input(-xs|-sm|-lg)?-border-radius:/', $css), 'control radii left to Bootstrap 6');
	pg_assert_same(0, preg_match_all('/--bs-btn-border-radius:\s*var\(--pg-radius-lg\)/', $css), 'no panel-wide button radius');
	pg_assert_same(0, preg_match_all('/--bs-pagination-border-radius:/', $css), 'pagination follows the control radius');
}

function test_bs6_radius_tokens_one_surface_radius()
{
	$css = bs6_radius_tokens_test_code();

	pg_assert_same(1, preg_match_all('/--pg-radius-surface:\s*var\(--bs-radius-7\);/', $css), 'surface radius on Bootstrap 6 radius-7');
	pg_assert_contains('--bs-card-border-radius: var(--pg-radius-surface);', bs6_radius_tokens_test_rule($css, '.card'), 'card');
	pg_assert_contains('--bs-dialog-border-radius: var(--pg-radius-surface);', bs6_radius_tokens_test_rule($css, '.dialog'), 'dialog');
	pg_assert_contains('--bs-menu-border-radius: var(--pg-radius-surface);', bs6_radius_tokens_test_rule($css, '.menu'), 'menu');
	pg_assert_same(0, preg_match_all('/--bs-menu-border-radius:\s*1rem/', $css), 'no menu on its own 1rem');
}

function test_bs6_radius_tokens_inner_corners_follow_the_card()
{
	$css = bs6_radius_tokens_test_code();

	// An inner radius computed from another token than the card's own draws
	// a header or image corner that does not meet the card's edge.
	$inner = preg_match_all('/--bs-card-inner-border-radius:\s*calc\(([^;]*)\);/', $css, $m);
	pg_assert_true($inner >= 1, 'inner radius declared');
	foreach ($m[1] as $calc) {
		pg_assert_contains('var(--bs-card-border-radius)', $calc, 'inner radius from the card radius');
	}
}
