<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source pins for focus around the panel's drawers and dialogs: the phone
 * main menu drawer is inert while shut and gives focus back to its bar
 * button on Escape, the settings dialog's phone list drawer gives focus back
 * to its button, the product screen's barcode dialogs are named by their
 * titles, and the pgToast and pgConfirm close buttons are translated.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function panel_drawer_focus_source()
{
	return (string) file_get_contents(dirname(__DIR__) . '/pinegrap/assets/js/backend.src.js');
}

// The body of a named function in backend.src.js, up to the next function at
// the same indent.
function panel_drawer_focus_function($name)
{
	$source = panel_drawer_focus_source();
	$found = preg_match('/^( *)function ' . preg_quote($name, '/') . '\(.*?\n\1\}\n/ms', $source, $match);
	pg_assert_true((bool) $found, $name . '() not found');

	return $found ? $match[0] : '';
}

function test_panel_drawer_focus_menu_drawer_inert_when_shut()
{
	$paint = panel_drawer_focus_function('paintMenuToggle');
	pg_assert_contains('paintMenuInert();', $paint);
	pg_assert_true(strpos($paint, 'paintMenuInert();') < strpos($paint, 'if (!toggle)'), 'inert must be painted before the early return');

	$inert = panel_drawer_focus_function('paintMenuInert');
	pg_assert_contains('.software-sidebar', $inert);
	pg_assert_contains('isMenuDrawerMode() && !isMenuDrawerOpen()', $inert);
	pg_assert_contains("setAttribute('inert', '')", $inert);
	pg_assert_contains("removeAttribute('inert')", $inert);
}

function test_panel_drawer_focus_menu_escape_returns_focus()
{
	$init = panel_drawer_focus_function('initMenuDrawer');
	pg_assert_contains("closest('.software-sidebar')", $init);
	pg_assert_contains("getElementById('menu_mode_toggle')", $init);
}

function test_panel_drawer_focus_settings_drawer_returns_focus()
{
	$drawer = panel_drawer_focus_function('drawer');
	pg_assert_contains("matchMedia('(max-width: 575.98px)')", $drawer);
	pg_assert_contains("byId('pg_settings_modal_side')", $drawer);
	pg_assert_contains('button.focus(', $drawer);
}

function test_panel_drawer_focus_barcode_dialogs_named()
{
	$source = panel_drawer_focus_source();
	pg_assert_contains('id="\' + MODAL_ID + \'" aria-labelledby="pg-bc-template-title"', $source);
	pg_assert_contains('<h5 class="dialog-title fs-md fw-semibold" id="pg-bc-template-title">', $source);
	pg_assert_contains('id="\' + BLIST_ID + \'" aria-labelledby="pg-barcodes-list-title"', $source);
	pg_assert_contains('<h6 class="dialog-title" id="pg-barcodes-list-title">', $source);
}

function test_panel_drawer_focus_toast_and_confirm_close_translated()
{
	$source = panel_drawer_focus_source();
	pg_assert_true(strpos($source, 'data-bs-dismiss="toast" aria-label="Close"') === false, 'pgToast close button is untranslated');
	pg_assert_true(strpos($source, 'id="pg-confirm-title">\' + escapeHtml(title) + \'</h5>\' +' . "\n" . '                            \'<button type="button" class="btn-close" data-bs-dismiss="dialog" aria-label="Close">') === false, 'pgConfirm close button is untranslated');
	pg_assert_contains('data-bs-dismiss="toast" aria-label="\' + escapeHtml(lang(\'Close\')) + \'"', $source);
	pg_assert_contains('data-bs-dismiss="dialog" aria-label="\' + escapeHtml(lang(\'Close\')) + \'"', $source);
}

function test_panel_drawer_focus_menu_drawer_shuts_when_focus_leaves()
{
	$lost = panel_drawer_focus_function('drawerLostFocus');
	pg_assert_contains('!isMenuDrawerMode() || !isMenuDrawerOpen()', $lost);
	pg_assert_contains("closest('[data-pg-menu-hold]')", $lost);
	pg_assert_contains("'menu_mode_toggle'", $lost);
	pg_assert_contains('closeMenuDrawer();', $lost);

	$init = panel_drawer_focus_function('initMenuDrawer');
	pg_assert_contains("$('.software-sidebar').on('focusout'", $init);
	pg_assert_contains('drawerLostFocus(document.activeElement)', $init, 'focus with no relatedTarget is read on the next turn');
}

function test_panel_drawer_focus_settings_drawer_shuts_when_focus_leaves()
{
	$lost = panel_drawer_focus_function('sideLostFocus');
	pg_assert_contains("matchMedia('(max-width: 575.98px)')", $lost);
	pg_assert_contains('drawer(false);', $lost);
	pg_assert_contains("el.addEventListener('focusout'", panel_drawer_focus_function('wire'));
}
