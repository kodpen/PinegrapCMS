<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source pins for the File and Catalog Manager drawers and dialogs whose
 * rows are drawn by script after load: every access select, commerce switch
 * block, shipping-zone list, the bulk description box, the backup upload
 * path and the file window's code field carry their own accessible name.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function explorer_drawer_names_source()
{
	return (string) file_get_contents(dirname(__DIR__) . '/pinegrap/view_folders.php');
}

function test_explorer_drawer_names_access_select_names_its_user()
{
	$source = explorer_drawer_names_source();
	pg_assert_true(strpos($source, "'rights_for_user' => lang('Rights for {var:1}')") !== false, 'label map carries the per-user rights name');
	pg_assert_true(strpos($source, 'access-rights-select" aria-label="\' + esc(fmt(L.rights_for_user, [userRow.username])) + \'"') !== false, 'each access select is named by its user');
}

function test_explorer_drawer_names_commerce_block_is_a_group()
{
	$source = explorer_drawer_names_source();
	pg_assert_true(strpos($source, 'role="group" aria-labelledby="commerce_user_\' + userRow.id + \'"') !== false, 'each user block is a named group');
	pg_assert_true(strpos($source, 'id="commerce_user_\' + userRow.id + \'"') !== false, 'the username carries the group id');
}

function test_explorer_drawer_names_zone_lists_are_groups()
{
	$source = explorer_drawer_names_source();
	pg_assert_true(strpos($source, '<div class="mb-5" role="group" aria-labelledby="\' + name + \'_label">') !== false, 'each zone list is a named group');
	pg_assert_true(strpos($source, '<div class="form-label" id="\' + name + \'_label">') !== false, 'the caption carries the group id');
}

function test_explorer_drawer_names_bulk_description_has_label()
{
	$source = explorer_drawer_names_source();
	pg_assert_true(strpos($source, '<label class="form-label" for="bulk_description">\' + esc(L.bulk_description_text) + \'</label>') !== false, 'bulk description box has a label');
}

function test_explorer_drawer_names_backup_path_takes_folder_label()
{
	$source = explorer_drawer_names_source();
	pg_assert_true(strpos($source, '<label class="form-label" for="upload_folder" id="upload_folder_label">') !== false, 'folder label has an id');
	pg_assert_true(strpos($source, "document.getElementById('upload_folder_label').htmlFor = backupArea ? 'upload_backup_path' : 'upload_folder';") !== false, 'folder label follows the visible box');
}

function test_explorer_drawer_names_file_window_code_field()
{
	$source = explorer_drawer_names_source();
	pg_assert_true(strpos($source, "'file_content' => lang('File Content')") !== false, 'label map carries the code field name');
	pg_assert_true(strpos($source, "textarea.setAttribute('aria-label', L.file_content);") !== false, 'fallback textarea is named');
	pg_assert_true(strpos($source, "extensions: window.PgCM.view.EditorView.contentAttributes.of({ 'aria-label': L.file_content, 'aria-describedby': 'file_edit_editor_note' })") !== false, 'editor content is named');
}

function test_explorer_drawer_names_file_window_says_how_tab_leaves()
{
	$source = explorer_drawer_names_source();
	pg_assert_true(strpos($source, "'tab_hint' => lang('Tab indents. {var} makes Tab move to the next field.')") !== false, 'label map carries the Tab hint');
	pg_assert_true(strpos($source, "note.textContent = L.tab_hint.replace('{var}', /mac|iphone|ipad|ipod/i.test(platform) ? 'Shift+Alt+M' : 'Ctrl+M');") !== false, 'an editable file shows the way out under the editor');
	pg_assert_true(strpos($source, "contentAttributes.of({ 'aria-label': L.file_content, 'aria-describedby': 'file_edit_editor_note' })") !== false, 'editor content is described by the note under it');
}
