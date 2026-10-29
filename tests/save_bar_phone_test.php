<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests that the sticky save bar lays its actions out two per row on a phone
 * instead of one full-width row each, while keeping the chat clearance.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function save_bar_phone_test_block()
{
	$css = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/css/backend.src.css');

	// The phone block that holds the save bar rules, up to the next top-level rule.
	if (preg_match('/@media \(max-width: 575\.98px\) \{\s*nav\.buttons\.navigation \{(.*?)\n\}/s', $css, $block) !== 1) {
		return '';
	}

	return $block[1];
}

function save_bar_phone_test_rule($block, $selector)
{
	if (preg_match('/(?:^|\n)\s*' . preg_quote($selector, '/') . '\s*\{([^}]*)\}/', $block, $rule) !== 1) {
		return '';
	}

	return $rule[1];
}

function test_save_bar_phone_container_wraps_in_rows()
{
	$block = save_bar_phone_test_block();

	pg_assert_true($block !== '', 'phone block exists');

	$container = save_bar_phone_test_rule($block, 'nav.buttons.navigation > .container');

	pg_assert_true(strpos($container, 'flex-direction: row') !== false, 'container lays actions in a row');
	pg_assert_true(strpos($container, 'flex-wrap: wrap') !== false, 'container wraps');
	pg_assert_true(strpos($container, 'flex-direction: column') === false, 'no one-per-row column');
}

function test_save_bar_phone_actions_two_per_row()
{
	$button = save_bar_phone_test_rule(save_bar_phone_test_block(), 'nav.buttons.navigation .btn');

	pg_assert_true(strpos($button, 'flex: 1 1 calc(50% - 0.25rem)') !== false, 'each action takes half a row less half the gap');
	pg_assert_true(strpos($button, 'width: 100%') === false, 'actions are not full width');
}

function test_save_bar_phone_keeps_chat_clearance()
{
	$block = save_bar_phone_test_block();

	$bar = save_bar_phone_test_rule($block, 'body:has(#pg-chat-root) nav.buttons.navigation');

	pg_assert_true(strpos($bar, 'bottom: calc(0.5rem + var(--pg-chat-clearance)) !important') !== false, 'bar stays above the chat bubble');
}
