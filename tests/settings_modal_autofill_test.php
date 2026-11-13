<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The Site Settings dialog's guard against password-manager fills
 * (assets/js/backend.src.js, pgSettingsModal) counts only a person: a
 * script's keydown, pointerdown, paste or drop is no gesture, a focus opens a
 * shut field only right after a trusted key or press, the focused field is
 * the operator's only when a person moved the focus there, and the press that
 * asked for a pane is forgotten once it is painted.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function settings_modal_autofill_test_dialog()
{
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/backend.src.js');
	$from = strpos($js, "var STORE = 'pg_settings_last_pane';");

	pg_assert_true($from !== false, 'the settings dialog is in backend.src.js');

	$end = strpos($js, 'return { ready: ready, open: open', (int) $from);

	return substr($js, (int) $from, ($end === false) ? 200000 : ($end - $from));
}

// The listener bound in the capture phase for one event, up to its close.
function settings_modal_autofill_test_listener($dialog, $event)
{
	// The gesture listeners follow the note saying a script's event is no
	// person; the dialog's Escape handler is a keydown listener of its own.
	$note = strpos($dialog, 'Only a trusted event is a person.');
	$at = ($note === false) ? false : strpos($dialog, "document.addEventListener('" . $event . "', function (event) {", $note);

	pg_assert_true($at !== false, $event . ' is listened on the document');

	$end = strpos($dialog, '}, true);', (int) $at);

	return substr($dialog, (int) $at, ($end === false) ? 2000 : ($end - $at));
}

function test_settings_modal_autofill_script_events_are_no_gesture()
{
	$dialog = settings_modal_autofill_test_dialog();

	foreach (array('pointerdown', 'keydown') as $event) {
		$listener = settings_modal_autofill_test_listener($dialog, $event);
		$guard = strpos($listener, 'if (!event.isTrusted) {');

		pg_assert_true($guard !== false, $event . ' from a script is ignored');
		pg_assert_true($guard < strpos($listener, 'unlatch(event.target);'), $event . ': before it opens a field');
		pg_assert_true($guard < strpos($listener, 'gesture = Date.now();'), $event . ': before it starts the clock');
	}

	$paste = strpos($dialog, "$(el).on('paste drop', function (event) {");
	pg_assert_true($paste !== false, 'paste and drop read their event');
	$body = substr($dialog, (int) $paste, 400);
	pg_assert_true(strpos($body, '!event.originalEvent.isTrusted') < strpos($body, 'gesture = Date.now();'), 'a paste or drop from a script is ignored');
}

function test_settings_modal_autofill_focus_opens_only_after_a_person()
{
	$listener = settings_modal_autofill_test_listener(settings_modal_autofill_test_dialog(), 'focusin');

	pg_assert_contains('((Date.now() - gesture) < 1200)', $listener, 'a focus right after a trusted gesture');
	pg_assert_contains('(gestureKey || !gestureControl || (gestureControl === control))', $listener, 'a key, or a press on this field or on no field');
	pg_assert_contains("if (led) {\n                unlatch(event.target);", $listener, 'only a led focus opens the field');
	pg_assert_contains('focusControl = led ? control : null;', $listener, 'and only a led focus claims it');
	pg_assert_true(!preg_match('/^\s{12}unlatch\(event\.target\);/m', $listener), 'no focus opens a field unconditionally');
}

function test_settings_modal_autofill_focus_alone_is_not_ownership()
{
	$dialog = settings_modal_autofill_test_dialog();
	$at = strpos($dialog, '    function sweep(');
	$sweep = substr($dialog, (int) $at, (int) strpos($dialog, "\n    function ", (int) $at + 10) - $at);

	pg_assert_contains('((control === document.activeElement) && (control === focusControl))', $sweep, 'the focused field counts when a person put the focus there');
	pg_assert_true(strpos($sweep, '|| (control === document.activeElement)) {') === false, 'a script focus no longer makes a fill the operator\'s');
}

function test_settings_modal_autofill_pane_forgets_the_press_that_asked_for_it()
{
	$dialog = settings_modal_autofill_test_dialog();
	$hold = strpos($dialog, "baseline = snapshot();\n        hold();");

	pg_assert_true($hold !== false, 'the pane is held when it is painted');

	$after = substr($dialog, (int) $hold, 600);
	foreach (array('gesture = 0;', 'gestureControl = null;', 'gestureKey = false;', 'focusControl = null;') as $reset) {
		pg_assert_contains($reset, $after, 'after the paint: ' . $reset);
	}
}
