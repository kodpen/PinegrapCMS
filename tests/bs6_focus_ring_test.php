<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source test for the panel's focus ring on the Bootstrap 6 focus-ring
 * tokens: rules read --pg-focus-ring-width / --pg-focus-ring-offset /
 * --pg-focus-ring-color, the colour is declared once, no ring is built from
 * an -rgb triple or Bootstrap's variables directly, and the panel's own
 * rings are Bootstrap 6's shape (a 3px outline 1px out), not a shadow halo.
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
	pg_assert_same(1, preg_match_all('/--pg-focus-ring-offset:\s*1px;/', $css), 'offset is Bootstrap 6\'s 1px, declared once');
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

// The declarations of every rule whose selector list is exactly $selector,
// joined in source order.
function bs6_focus_ring_test_rule($css, $selector)
{
	$out = '';
	if (preg_match_all('/(^|[}{;])\s*' . preg_quote($selector, '/') . '\s*\{([^}]*)\}/', $css, $m)) {
		$out = implode("\n", $m[2]);
	}
	return $out;
}

// A ring drawn as Bootstrap 6 draws it: an outline of the token width in
// $color, the token offset out, and no shadow halo.
function bs6_focus_ring_test_assert_ring($css, $selector, $color, $label)
{
	$body = bs6_focus_ring_test_rule($css, $selector);
	pg_assert_true($body !== '', $label . ': rule found');
	pg_assert_true(preg_match('/outline:\s*var\(--pg-focus-ring-width\) solid ' . preg_quote($color, '/') . '( !important)?;/', $body) === 1, $label . ': token-wide outline');
	pg_assert_true(preg_match('/outline-offset:\s*var\(--pg-focus-ring-offset\);/', $body) === 1, $label . ': token offset');
	pg_assert_same(0, preg_match('/box-shadow:\s*0 0 0/', $body), $label . ': no shadow halo');
	pg_assert_same(0, preg_match('/outline:\s*0;/', $body), $label . ': outline not taken away');
}

function test_bs6_focus_ring_panel_rings_on_bootstrap_6_shape()
{
	$css = bs6_focus_ring_test_code('backend.src.css');

	bs6_focus_ring_test_assert_ring($css, 'a:focus-visible,
button:focus-visible,
input:focus-visible,
select:focus-visible,
textarea:focus-visible,
[tabindex]:not([tabindex="-1"]):focus-visible,
.btn:focus-visible,
.nav-link:focus-visible,
.menu-item:focus-visible,
.breadcrumb-item a:focus-visible,
.form-adorn:has(> .form-ghost:focus-visible),
.chip-input.pg-chips:has(> .form-ghost:focus-visible)', 'var(--pg-primary-ink)', 'global ring');
	bs6_focus_ring_test_assert_ring($css, 'input.btn-check:focus-visible + .btn', 'var(--pg-primary-ink)', 'sibling btn-check label');
	bs6_focus_ring_test_assert_ring($css, 'a.no-popover:focus-visible,
button.no-popover:focus-visible', 'var(--pg-primary-ink)', 'no-popover links');
	bs6_focus_ring_test_assert_ring($css, '.pg-skip-link:focus,
.pg-skip-link:focus-visible', 'var(--pg-primary-ink)', 'skip link');
	bs6_focus_ring_test_assert_ring($css, '.btn-check:focus-visible + .pg-role-card', 'var(--pg-primary-ink)', 'role card');

	pg_assert_same(0, preg_match_all('/box-shadow:\s*0 0 0 0?\.2(5)?rem var\(--pg-focus-ring-color\)/', $css), 'no rem-sized halo left');

	$home = bs6_focus_ring_test_code('designer_home.css');
	foreach (array('.sdh-start:focus-visible', '.sdh-page:focus-visible') as $selector) {
		bs6_focus_ring_test_assert_ring($home, $selector, 'var(--pg-primary-ink)', $selector);
	}
	// A card has tabindex="0": the global [tabindex] ring outranks any
	// .sdh-card:focus-visible rule, so none is written.
	pg_assert_same(0, preg_match('/\.sdh-card:focus-visible/', $home), 'designer home card wears the global ring');
	pg_assert_same(0, preg_match_all('/box-shadow:\s*0 0 0 0?\.2rem/', $home), 'designer home: no rem-sized halo left');
}

// In forced colours the role card, now an outline, keeps the token size and
// takes only the system focus colour.
function test_bs6_focus_ring_role_card_forced_colors()
{
	$css = bs6_focus_ring_test_code('backend.src.css');

	pg_assert_true(preg_match('/\.btn-check:focus-visible \+ \.pg-role-card\s*\{\s*outline-color:\s*Highlight;\s*\}/', $css) === 1, 'forced colours: Highlight ring colour only');
	pg_assert_same(0, preg_match('/\.btn-check:focus-visible \+ \.pg-role-card,\s*\.pg-chips:focus-within/', $css), 'role card out of the shadow-only list');
}
