<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Workspace and the Incoming e-mail screen on a phone: the
 * expanded writing box stays above the keyboard, the writing box's tools,
 * the table size picker and the mail pane's channel pill are touch
 * targets, and the emoji search, the poll window's fields and the code
 * areas are 16px on a touch screen so iOS does not zoom in. Pins
 * assets/css/backend.src.css.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function workspace_phone_touch_test_css()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/css/backend.src.css');
}

// The rules of every block of the sheet opened by $media, joined.
function workspace_phone_touch_test_blocks($media)
{
	$css = workspace_phone_touch_test_css();
	$head = '@media ' . $media . ' {';
	$out = '';
	$from = 0;
	$len = strlen($css);

	while (($at = strpos($css, $head, $from)) !== false) {
		$depth = 0;

		for ($i = $at + strlen($head) - 1; $i < $len; $i++) {
			if ($css[$i] === '{') {
				$depth++;
			} elseif ($css[$i] === '}') {
				$depth--;

				if ($depth === 0) {
					break;
				}
			}
		}

		$out .= substr($css, $at, $i - $at + 1) . "\n";
		$from = $i;
	}

	return $out;
}

// The declarations of the first rule whose selector list holds $selector.
function workspace_phone_touch_test_rule($css, $selector)
{
	$quoted = preg_quote($selector, '/');

	if (!preg_match('/(?:^|[\n,])\s*' . $quoted . '\s*(?:,[^{}]*)?\{([^}]*)\}/', $css, $m)) {
		return null;
	}

	return $m[1];
}

function test_workspace_phone_expanded_box_rides_on_the_keyboard()
{
	$phone = workspace_phone_touch_test_blocks('(max-width: 767.98px)');

	$field = workspace_phone_touch_test_rule($phone, 'body.ws-keyboard .ws-composer .ws-rich.is-expanded');
	pg_assert_true($field !== null, 'the phone block caps the expanded field while the keyboard is up');
	pg_assert_contains('max-height: 6rem', (string) $field, 'the expanded field leaves room for the tools and Send');
	pg_assert_contains('min-height: 2.5rem', (string) $field, 'the expanded field is as low as the one-line box');

	$hint = workspace_phone_touch_test_rule($phone, 'body.ws-keyboard .ws-composer-hint');
	pg_assert_true($hint !== null && strpos($hint, 'display: none') !== false, 'the Ctrl+Enter hint gives its line back while the keyboard is up');
}

function test_workspace_phone_composer_tools_are_touch_targets()
{
	$phone = workspace_phone_touch_test_blocks('(max-width: 767.98px)');
	$tools = workspace_phone_touch_test_rule($phone, '.ws-composer-tools .btn');

	pg_assert_true($tools !== null, 'the phone block styles the tools');
	pg_assert_contains('min-height: 2.75rem', (string) $tools, 'the tools and Send are 44 px high');
	pg_assert_contains('padding: .3rem .4rem', (string) $tools, 'the narrow pads keep the row on one line at 375 px');
}

function test_workspace_phone_table_picker_cells_on_touch()
{
	$touch = workspace_phone_touch_test_blocks('(pointer: coarse)');

	$grid = workspace_phone_touch_test_rule($touch, '.ws-table-pick-grid');
	pg_assert_true($grid !== null && strpos($grid, 'repeat(6, 2.5rem)') !== false, 'the picker has six 2.5rem columns on touch');

	$cell = workspace_phone_touch_test_rule($touch, '.ws-table-pick-cell');
	pg_assert_true($cell !== null, 'the picker cells have a touch size');
	pg_assert_contains('width: 2.5rem', (string) $cell, 'a cell is 2.5rem wide on touch');
	pg_assert_contains('height: 2.5rem', (string) $cell, 'a cell is 2.5rem high on touch');
}

function test_workspace_phone_fields_are_16px_on_touch()
{
	$css = workspace_phone_touch_test_css();
	$touch = workspace_phone_touch_test_blocks('(pointer: coarse)');

	foreach (array('.ws-emoji-search', '#ws-poll-form .form-control', '.ws-code-edit') as $selector) {
		$rule = workspace_phone_touch_test_rule($touch, $selector);
		pg_assert_true($rule !== null && strpos($rule, 'font-size: var(--pg-text-field-touch)') !== false, $selector . ' is 16px on touch');
	}

	pg_assert_true((bool) preg_match('/--pg-text-field-touch:\\s*16px;/', $css), 'the touch field size is 16px');

	// The code area's own size is set outside the touch block: the touch
	// rule has to come after it to win.
	$base = strpos($css, "\n.ws-code-edit {");
	$touchAt = strpos($css, '.ws-code-edit {', $base + 1);
	pg_assert_true($base !== false && $touchAt !== false, 'the code area has a base and a touch rule');
}

function test_mail_pane_channel_pill_is_a_touch_target()
{
	$touch = workspace_phone_touch_test_blocks('(pointer: coarse)');
	$pill = workspace_phone_touch_test_rule($touch, '.ws-mail-pane-channel');

	pg_assert_true($pill !== null, 'the channel pill has a touch size');
	pg_assert_contains('min-height: 2rem', (string) $pill, 'the pill is 32 px high on touch');

	$base = workspace_phone_touch_test_rule(workspace_phone_touch_test_css(), '.ws-mail-pane-channel');
	pg_assert_true($base !== null && strpos($base, 'min-height') === false, 'the pill keeps its look under a mouse');
}
