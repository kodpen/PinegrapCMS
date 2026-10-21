<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the panel's Ctrl+S shortcut (assets/js/backend.src.js). Without
 * a focused form it falls back to the first form on the screen. A dialog that
 * saves on Ctrl+S itself (Site Settings, a code editor dialog) takes the key
 * first; the shortcut then leaves it alone instead of submitting the screen
 * behind the dialog as well, which saved that form, reloaded the page and
 * closed the dialog before its own answer arrived.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The shortcut's case for the S key, up to the end of its switch.
function save_shortcut_test_case()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/backend.src.js');
	$from = strpos($source, "case 's':");

	if ($from === false) {
		return '';
	}

	$to = strpos($source, "\n    });", $from);

	return ($to === false) ? substr($source, $from) : substr($source, $from, $to - $from);
}

// A key something else has already handled stops the shortcut before it looks
// for a form, falls back to one or presses a button.
function test_save_shortcut_leaves_a_handled_key_alone()
{
	$case = save_shortcut_test_case();

	$guard = strpos($case, 'if (event.isDefaultPrevented()) {');
	$closest = strpos($case, "closest('form:not(.disable_shortcut)')");
	$fallback = strpos($case, "\$('form:not(.disable_shortcut)').filter(':visible').first()");
	$press = strpos($case, 'button[0].click();');

	pg_assert_true($case !== '', 'the shortcut case is found');
	pg_assert_true(preg_match('/if \(event\.isDefaultPrevented\(\)\) \{\s*break;\s*\}/', $case) === 1, 'a handled key ends the case');
	pg_assert_true(($guard !== false) && ($closest !== false) && ($guard < $closest), 'before the focused form is looked for');
	pg_assert_true(($guard !== false) && ($fallback !== false) && ($guard < $fallback), 'before the first form on the screen is taken');
	pg_assert_true(($guard !== false) && ($press !== false) && ($guard < $press), 'before a button is pressed');
}

// The Site Settings dialog takes the key the way the shortcut checks for: it
// prevents the default and saves itself.
function test_save_shortcut_settings_dialog_takes_the_key()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/backend.src.js');

	pg_assert_true(
		preg_match("/el\\.addEventListener\\('keydown', function \\(event\\) \\{\\s*if \\(\\(event\\.ctrlKey \\|\\| event\\.metaKey\\) && \\(\\(event\\.key === 's'\\) \\|\\| \\(event\\.key === 'S'\\)\\)\\) \\{\\s*event\\.preventDefault\\(\\);\\s*save\\(\\);/", $source) === 1,
		'the dialog prevents the default and saves');
}
