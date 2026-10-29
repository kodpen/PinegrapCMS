<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source pins for dialogs and script-built fields that carry their own
 * accessible name: the file manager's delete, access, one-time link and
 * inline edit boxes, the Orders list Paraşüt export dialog, the image
 * editors and the Page Designer dialogs. These fields are built after
 * DOMContentLoaded, so the label binding in backend.src.js never reaches them.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function screen_dialog_names_source($path)
{
	return (string) file_get_contents(dirname(__DIR__) . '/pinegrap/' . $path);
}

function test_screen_dialog_names_image_editor_modal()
{
	$source = screen_dialog_names_source('assets/js/image_editor_modal.js');
	pg_assert_true(strpos($source, 'id="pgImageEditorModal" aria-labelledby="pgImageEditorTitle"') !== false, 'Pintura dialog names its title');
}

function test_screen_dialog_names_file_manager()
{
	$source = screen_dialog_names_source('view_folders.php');
	pg_assert_true(strpos($source, '<dialog class="dialog" id="folder_delete_modal" aria-labelledby="folder_delete_title"') !== false, 'delete dialog names its title');
	pg_assert_true(strpos($source, 'class="dialog-title fg-danger" id="folder_delete_title"') !== false, 'delete dialog title has its id');
	pg_assert_true(substr_count($source, '<label class="form-label" for="folder_delete_name_input">') === 2, 'both confirm labels point at the box');
	pg_assert_true(strpos($source, '<label class="form-label" for="access_type_select">') !== false, 'access type label points at its select');
	pg_assert_true(strpos($source, 'id="sl_address" aria-label="\' + esc(L.link_once_title) + \'"') !== false, 'one-time link box is named');
	pg_assert_true(strpos($source, 'rename-input" aria-label="\' + esc(L.rename) + \'"') !== false, 'inline rename box is named');
	pg_assert_true(strpos($source, 'rename-input" aria-label="\' + esc(label) + \'"') !== false, 'quick-edit box is named');
	pg_assert_true(strpos($source, 'quickEditLabel(field)') !== false, 'quick-edit passes the field name');
}

function test_screen_dialog_names_parasut_export()
{
	$source = screen_dialog_names_source('view_orders.php');
	pg_assert_true(strpos($source, '<dialog id="xlsl_setup_modal" class="dialog" aria-labelledby="xlsl_setup_title">') !== false, 'export dialog names its title');
	pg_assert_true(strpos($source, '<h5 class="dialog-title" id="xlsl_setup_title">') !== false, 'export dialog title has its id');
}

function test_screen_dialog_names_image_editor_edit()
{
	$source = screen_dialog_names_source('image_editor_edit.php');
	pg_assert_true(strpos($source, '<dialog class="dialog" id="confirm_modal" data-bs-backdrop="static" aria-labelledby="save_option_title">') !== false, 'save option dialog names its title and lets Escape close it');
	pg_assert_true(strpos($source, 'data-bs-keyboard="false"') === false, 'Escape is not blocked');
	pg_assert_true(strpos($source, 'id="save_option_title"') !== false, 'save option title has its id');
	pg_assert_true(strpos($source, 'id="save_option_item_type" name="save_option_item_type" aria-label="\' . h(lang(\'File Format\')) . \'"') !== false, 'file format select is named');
	pg_assert_true(strpos($source, 'exampleModalLabel') === false, 'no shared placeholder id');
}

function test_screen_dialog_names_page_designer()
{
	$source = screen_dialog_names_source('assets/js/page_designer.js');
	pg_assert_true(strpos($source, 'aria-label="Close"') === false, 'no untranslated Close label');
	pg_assert_true(substr_count($source, 'aria-label="${h(labels[\'Close\'])}"') === 4, 'four close buttons use the label map');
	pg_assert_true(strpos($source, 'id="editor_region_modal" aria-labelledby="editor_region_title"') !== false, 'System Tags dialog names its title');
	pg_assert_true(strpos($source, 'id="editor_region_title"') !== false, 'System Tags title has its id');
	pg_assert_true(strpos($source, 'exampleModalLabel') === false, 'no shared placeholder id');

	$php = screen_dialog_names_source('page_designer.php');
	pg_assert_true(strpos($php, '"Close":"\' . lang(\'Close\') . \'"') !== false, 'label map carries Close');
}
