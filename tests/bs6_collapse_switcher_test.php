<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source checks for the panel's collapse switchers on Bootstrap 6. v6 hides
 * `.collapse:not(.show)` with display:none and block-size:0 outside any
 * layer, so an inline display alone leaves a switched-on section 0 px high:
 * the switcher code adds and removes .show together with the display. The
 * same unlayered overflow-y:clip cut off a switch callout's arrow, so the
 * callout rule takes overflow and transition back.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function bs6_collapse_switcher_source($path)
{
	return (string) file_get_contents(dirname(__DIR__) . '/pinegrap/' . $path);
}

function test_bs6_collapse_switcher_toggles_show_with_display()
{
	$js = bs6_collapse_switcher_source('assets/js/backend.src.js');

	pg_assert_same(
		2,
		substr_count($js, "\$(target).toggle(returned);\n        pgCollapseSwitcherShow(target, returned);"),
		'the click handler and the first paint both set .show with the display');
	pg_assert_contains(
		"\$(\$(this).attr(\"data-bs-target\")).toggle(false);\n                    pgCollapseSwitcherShow(\$(this).attr(\"data-bs-target\"), false);",
		$js,
		'a sibling radio\'s section loses .show when it is hidden');
	pg_assert_contains("\$(target).filter('.collapse:not(.popover)').toggleClass('show', !!shown);", $js, 'only plain .collapse targets; callouts keep their own .show toggle');
	pg_assert_contains("if (\$(target).hasClass('popover')) {\n            \$(target).toggleClass('show');", $js, 'the callout toggle stays');
}

function test_bs6_collapse_switcher_callout_is_not_clipped()
{
	$css = bs6_collapse_switcher_source('assets/css/backend.src.css');

	pg_assert_true(
		(bool) preg_match('/\n\.collapse\.popover \{[^}]*overflow: visible !important;[^}]*transition: none !important;[^}]*\}/', $css),
		'the callout overrides v6\'s unlayered overflow-y:clip and its reveal transition');
}
