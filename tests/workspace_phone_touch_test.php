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
