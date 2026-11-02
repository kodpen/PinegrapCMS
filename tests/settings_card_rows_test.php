<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source test for the settings card row rules in backend.src.css: a second
 * description of one setting gets a row of its own instead of the shared
 * note area, a group heading carries the divider above it, the box a switch
 * opens is as wide as the card, and a disabled ghost button draws no frame.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function settings_card_rows_css()
{
	return (string) file_get_contents(dirname(__DIR__) . '/pinegrap/assets/css/backend.src.css');
}

// The declarations of the first rule whose selector list contains $selector.
function settings_card_rows_block($css, $selector)
{
	$at = strpos($css, $selector);
	if ($at === false) {
		return '';
	}
	$open = strpos($css, '{', $at);
	$close = strpos($css, '}', $open);

	return substr($css, $open + 1, $close - $open - 1);
}

function test_settings_card_second_note_leaves_the_note_area()
{
	$css = settings_card_rows_css();
	$predicate = '.pg-set-card .row > [class]:has(> .form-field > :is(input[type="checkbox"], .switch)) > .form-field > .form-text ~ .form-text';
	$block = settings_card_rows_block($css, $predicate);
	pg_assert_same(1, preg_match('/grid-area:\s*auto\s*\/\s*1\s*\/\s*auto\s*\/\s*-1;/', $block), 'a later description of a switch row takes a full row of its own');

	$field = '.pg-set-card .row > [class]:has(> .form-label):has(> input.form-control:not(.tagin), > :is(select.form-control), > .input-group, > .form-adorn, > .form-range) > .form-text ~ .form-text';
	pg_assert_true(strpos($css, $field) !== false, 'a later description of a field row carries the full predicate');
}

function test_settings_card_heading_carries_the_divider()
{
	$css = settings_card_rows_css();
	$block = settings_card_rows_block($css, '.pg-set-card .row > * + [class]:has(> h6:first-child) {');
	pg_assert_true(strpos($block, 'border-top: var(--pg-border-width) solid') !== false, 'a group heading draws the line above it');
	pg_assert_true(strpos($block, 'padding-inline: 0') !== false, 'the heading line starts where the text does');

	$after = settings_card_rows_block($css, '.pg-set-card .row > [class]:has(> h6:first-child) + [class]:has(> .form-field > :is(input[type="checkbox"], .switch)) {');
	pg_assert_true(strpos($after, 'border-top: 0') !== false, 'the first setting under a heading draws no line');
}

function test_settings_card_inner_box_is_card_wide()
{
	$block = settings_card_rows_block(settings_card_rows_css(), '.pg-set-card .collapse.popover {');
	pg_assert_true(strpos($block, 'width: calc(100% - 0.3rem)') !== false, 'the box a switch opens fills the card');
}

function test_disabled_ghost_button_has_no_frame()
{
	$block = settings_card_rows_block(settings_card_rows_css(), '.btn-text:is(:disabled, .disabled) {');
	pg_assert_true(strpos($block, 'border-color: transparent') !== false, 'a disabled ghost button draws no border');
}

function test_settings_category_names_wrap()
{
	$block = settings_card_rows_block(settings_card_rows_css(), '.pg-sm-link span {');
	pg_assert_true(strpos($block, 'text-overflow') === false, 'a category name is not cut with an ellipsis');
	pg_assert_true(strpos($block, 'overflow-wrap: anywhere') !== false, 'a long category name wraps');
}
