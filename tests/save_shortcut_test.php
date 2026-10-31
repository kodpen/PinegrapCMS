<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the panel's Ctrl+S shortcut (assets/js/backend.src.js). Without
 * a focused form it falls back to the first visible form with a save button,
 * and it only ever presses that button: a form without one is never
 * submitted, since a bare submit skipped the button's confirmation. A dialog that
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
	$fallback = strpos($case, "\$('form:not(.disable_shortcut)').filter(':visible').filter(");
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

// The visual designer takes Ctrl+S on the window in the capture phase and
// stops it there. The code dialog (codemirror_modal.js) saves on the key
// inside its editor, so the designer has to leave a key typed in the dialog
// alone; otherwise the whole design was saved and the dialog stayed open with
// its code unapplied.
function test_save_shortcut_designer_leaves_the_code_dialog_alone()
{
	$designer = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	$dialog = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/codemirror_modal.js');

	pg_assert_true(
		preg_match("/ae\.closest\('[^']*#pgCodeEditorModal[^']*'\)\) return;\s*e\.preventDefault\(\);\s*e\.stopPropagation\(\);\s*_sdSaveShortcut\(\);/", $designer) === 1,
		'the designer returns before it stops the key and saves the design');
	pg_assert_true(strpos($dialog, 'id="pgCodeEditorModal"') !== false, 'the dialog carries the id the designer checks');
	pg_assert_true(strpos($dialog, "{ key: 'Mod-s', run: function () { _saveBtn.click(); return true; } }") !== false, 'the dialog presses its own Save on the key');
}

// The shortcut presses the save button and nothing else: a bare form.submit()
// skipped the button's click handlers and data-confirm-content, so the
// Orders list posted a bulk action refused with "No" (Delete included) and a
// subscription was paused or cancelled without its question.
function test_save_shortcut_never_submits_a_form_without_its_button()
{
	$case = save_shortcut_test_case();

	pg_assert_true($case !== '', 'the shortcut case is found');
	pg_assert_true(strpos($case, '.submit()') === false, 'no bare form submit');
	pg_assert_true(strpos($case, 'pgSaveShortcutButton(form)') !== false, 'the button comes from the shared lookup');
	pg_assert_true(strpos($case, "return pgSaveShortcutButton(\$(this)).length > 0;") !== false, 'the fallback form must have a save button');
}

// The hint goes only on the button the shortcut presses, never on the first
// submit button of a form without one ("Create Labels", "New link").
function test_save_shortcut_hint_only_on_the_pressed_button()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/backend.src.js');
	$from = strpos($source, "\$('form:not(.disable_shortcut)').each(function () {");
	$hint = ($from === false) ? '' : substr($source, $from, 600);

	pg_assert_true($hint !== '', 'the hint loop is found');
	pg_assert_true(strpos($hint, 'var button = pgSaveShortcutButton($(this));') !== false, 'the hint uses the shortcut lookup');
	pg_assert_true(strpos($hint, '[type=submit]') === false, 'no first submit button fallback');
}

// A bulk action refused with "No" clears the hidden action it wrote before
// the question, so a later submit cannot post it.
function test_save_shortcut_refused_bulk_action_is_cleared()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/backend.src.js');

	pg_assert_true(
		preg_match("/\} else \{\s*(\/\/[^\n]*\n\s*)*if \(result == true\) \{\s*document\.form\.action\.value = '';\s*\}\s*return false;/", $source) === 1,
		'the No branch empties document.form.action');
}

// Forms that only act carry disable_shortcut.
function test_save_shortcut_action_forms_are_excluded()
{
	$orders = (string) file_get_contents(PG_FUNCTIONS_DIR . '/view_orders.php');
	$subscriptions = (string) file_get_contents(PG_FUNCTIONS_DIR . '/view_subscriptions.php');
	$quotes = (string) file_get_contents(PG_FUNCTIONS_DIR . '/view_cart_quotes.php');

	pg_assert_true(strpos($orders, 'action="edit_orders.php" method="post" class="view_orders disable_shortcut"') !== false, 'the Orders list form');
	pg_assert_true(strpos($subscriptions, 'method="post" class="d-inline-block disable_shortcut"') !== false, 'the subscription action forms');
	pg_assert_true(preg_match('/<form name="form" action="\' \. h\(\$self\) \. \'" method="post" class="disable_shortcut">/', $quotes) === 1, 'the cart quotes list form');
}
