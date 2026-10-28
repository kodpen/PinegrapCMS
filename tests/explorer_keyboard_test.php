<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source checks for the file manager's keyboard use (view_folders.php):
 * the screen's shortcuts leave keys to a focused button, link or menu;
 * the breadcrumb and its overflow menu take focus; Quick Look is named,
 * takes focus, keeps Tab inside and gives focus back; the right-click menu
 * opens from the ContextMenu key and Shift+F10 and is walked with the
 * arrows. A unit test cannot press keys in the browser, so these pin the
 * source.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function explorer_keyboard_source()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/view_folders.php');
}

// The body of one function in the screen's script, from its declaration to
// the next function declared at the same indent.
function explorer_keyboard_function($name)
{
	$source = explorer_keyboard_source();
	$start = strpos($source, "\n    function " . $name . '(');
	if ($start === false) {
		return '';
	}
	$end = strpos($source, "\n    function ", $start + 1);

	return $end === false ? substr($source, $start) : substr($source, $start, $end - $start);
}

function test_explorer_shortcuts_leave_keys_to_a_focused_control()
{
	$guard = explorer_keyboard_function('keyBelongsToControl');

	pg_assert_true($guard !== '', 'keyBelongsToControl() is found in view_folders.php');

	foreach (array('button', 'a[href]', '[role="button"]', '[role="menuitem"]', '[tabindex]', '#pg-chat-root', '#explorer_menu', '.menu.show') as $selector) {
		pg_assert_contains($selector, $guard, 'a key on ' . $selector . ' is left to it');
	}

	$source = explorer_keyboard_source();
	$quickLook = strpos($source, 'if (quickLookIsOpen()) {');
	$controlGuard = strpos($source, 'if (keyBelongsToControl(event.target)) { return; }');

	pg_assert_true($controlGuard !== false, 'the shortcut handler asks keyBelongsToControl() first');
	pg_assert_true(($quickLook !== false) && ($quickLook < $controlGuard), 'Quick Look still takes its keys from its own buttons');

	// A button pressed with the mouse keeps the focus; Enter afterwards is
	// meant for the selected item, as it was before.
	pg_assert_contains('event.detail === 0', $source, 'a pointer click on a screen button gives the focus up');
}

function test_explorer_breadcrumb_takes_focus()
{
	$crumbs = explorer_keyboard_function('renderBreadcrumb');

	pg_assert_true($crumbs !== '', 'renderBreadcrumb() is found');
	pg_assert_true(strpos($crumbs, '<a data-folder-id="0" class="pg-link-secondary">') === false, 'no root crumb without href');
	pg_assert_true(strpos($crumbs, '<a data-catalog-id="0" class="pg-link-secondary">') === false, 'no catalog root crumb without href');
	pg_assert_true(substr_count($crumbs, '<a href="#" draggable="false" data-folder-id="') >= 7, 'every folder crumb and overflow entry has an href');
	pg_assert_true(substr_count($crumbs, '<a href="#" draggable="false" data-catalog-id="') >= 2, 'every catalog crumb has an href');
	pg_assert_contains('<a href="#" draggable="false" class="pg-link-secondary" data-bs-toggle="menu"', $crumbs, 'the overflow toggle has an href');

	$source = explorer_keyboard_source();
	pg_assert_contains("#explorer_breadcrumb a[data-folder-id]", $source, 'the drop target selector still matches the crumbs');
	pg_assert_contains("if (event.target.closest('a[href=\"#\"]')) { event.preventDefault(); }", $source, 'a crumb does not follow its href');
}

function test_explorer_quick_look_is_named_and_holds_focus()
{
	$source = explorer_keyboard_source();

	pg_assert_contains('<div id="explorer_quicklook" class="d-none" role="dialog" aria-modal="true" aria-labelledby="quicklook_name">', $source, 'Quick Look is named by the file name');
	pg_assert_contains('id="quicklook_close" aria-label="<?php echo h(lang(\'Close\')); ?>"', $source, 'the close button is named');

	$open = explorer_keyboard_function('openQuickLook');
	pg_assert_contains('quickLookReturnFocus = document.activeElement', $open, 'opening remembers what had the focus');
	pg_assert_contains("getElementById('quicklook_close').focus(", $open, 'opening moves the focus to Close');

	$close = explorer_keyboard_function('closeQuickLook');
	pg_assert_contains('restoreFocus(quickLookReturnFocus)', $close, 'closing gives the focus back');

	$trap = explorer_keyboard_function('trapQuickLookTab');
	pg_assert_true($trap !== '', 'trapQuickLookTab() is found');
	pg_assert_contains('event.shiftKey', $trap, 'Shift+Tab wraps too');

	pg_assert_contains("if (key === 'Tab') { trapQuickLookTab(event); return; }", $source, 'Tab stays inside Quick Look');
	pg_assert_contains("if ((key === 'Enter') && onControl) { return; }", $source, 'Enter presses a focused Quick Look control');
}

function test_explorer_context_menu_opens_from_the_keyboard()
{
	$source = explorer_keyboard_source();

	pg_assert_contains("(key === 'ContextMenu') || ((key === 'F10') && event.shiftKey)", $source, 'ContextMenu and Shift+F10 open the menu');

	$open = explorer_keyboard_function('openMenuFromKeyboard');
	pg_assert_true($open !== '', 'openMenuFromKeyboard() is found');
	pg_assert_contains('getBoundingClientRect()', $open, 'the menu opens at the item');
	pg_assert_contains('focusMenuEntry(', $open, 'the first entry takes the focus');

	$keys = explorer_keyboard_function('menuKeydown');
	pg_assert_true($keys !== '', 'menuKeydown() is found');
	foreach (array("'ArrowDown'", "'ArrowUp'", "'Home'", "'End'", "'ArrowRight'", "'ArrowLeft'", "'Escape'", "'Tab'") as $key) {
		pg_assert_contains($key, $keys, $key . ' is handled in the menu');
	}
	pg_assert_contains('event.stopPropagation()', $keys, 'Bootstrap\'s menu handler never sees the key');
	pg_assert_contains("window.addEventListener('keydown', menuKeydown, true)", $source, 'the menu takes its keys on the window, capture phase');

	pg_assert_contains('restoreFocus(menuReturnFocus)', explorer_keyboard_function('hideMenu'), 'closing the menu gives the focus back');
}
