<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source test for the panel's spacing on Bootstrap 6: a card takes
 * Bootstrap 6's card padding from one rule, its header text lines up with
 * its body, and a menu holds its items inset on Bootstrap 6's spacers with
 * rounded rows instead of Bootstrap 5's flush, square ones.
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
function bs6_spacing_tokens_test_code()
{
	$css = (string) file_get_contents(dirname(__DIR__) . '/pinegrap/assets/css/backend.src.css');
	return preg_replace('~/\*.*?\*/~s', '', $css);
}

// The declarations of every rule whose selector list is exactly $selector.
function bs6_spacing_tokens_test_rule($css, $selector)
{
	preg_match_all('/(?:^|\})\s*' . preg_quote($selector, '/') . '\s*\{([^}]*)\}/', $css, $m);
	return implode("\n", $m[1]);
}

function test_bs6_spacing_tokens_card_padding_from_one_rule()
{
	$card = bs6_spacing_tokens_test_rule(bs6_spacing_tokens_test_code(), '.card');

	pg_assert_contains('--bs-card-spacer-y: var(--bs-spacer-6);', $card, 'body padding on Bootstrap 6 spacer-6');
	pg_assert_contains('--bs-card-spacer-x: var(--bs-spacer-6);', $card, 'side padding on Bootstrap 6 spacer-6');
	pg_assert_contains('--bs-card-cap-padding-y: var(--bs-spacer-4);', $card, 'header padding on spacer-4');
	pg_assert_contains('--bs-card-cap-padding-x: var(--bs-card-spacer-x);', $card, 'header text in line with the body');
	// Two plain .card rules setting the same padding: the later one silently won.
	foreach (array('spacer-y', 'spacer-x', 'cap-padding-y', 'cap-padding-x') as $name) {
		pg_assert_same(1, substr_count($card, '--bs-card-' . $name . ':'), $name . ' set once');
	}
}

function test_bs6_spacing_tokens_menu_items_inset()
{
	$menu = bs6_spacing_tokens_test_rule(bs6_spacing_tokens_test_code(), '.menu');

	pg_assert_contains('--bs-menu-padding-x: var(--bs-spacer-1);', $menu, 'items inset from the sides');
	pg_assert_contains('--bs-menu-padding-y: var(--bs-spacer-1);', $menu, 'items inset from top and bottom');
	pg_assert_contains('--bs-menu-item-padding-x: var(--bs-spacer-4);', $menu, 'item side padding');
	pg_assert_contains('--bs-menu-header-padding-x: var(--bs-spacer-4);', $menu, 'header in line with the items');
	// The row's radius is the menu's minus its inset, so the corners nest.
	pg_assert_contains('--bs-menu-item-border-radius: var(--pg-radius-lg);', $menu, 'rounded rows');
	pg_assert_same(0, preg_match('/--bs-menu-item-border-radius:\s*0;/', $menu), 'no square rows');
}
