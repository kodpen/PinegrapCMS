<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * A form upload field whose folder was deleted or is no longer offered:
 * Edit Field keeps the stored folder selected and marked with a line asking
 * for another one, and the File Manager's folder delete confirmation says
 * how many upload fields use the folder or a folder inside it.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/forms/field_form.php');

function test_upload_folder_kept_listed_folder_unchanged()
{
	$options = '<option value="1">Root</option><option value="7" selected="selected">Applications</option>';
	$kept = pg_field_form_kept_folder($options, '7', 'Applications');

	pg_assert_same($options, $kept['options'], 'a listed folder leaves the options as they are');
	pg_assert_same('', $kept['help'], 'no help line for a listed folder');

	$kept = pg_field_form_kept_folder($options, '0', null);
	pg_assert_same($options, $kept['options'], 'no stored folder: nothing added');
	pg_assert_same('', $kept['help'], 'no stored folder: no help line');
}

function test_upload_folder_kept_deleted_folder()
{
	$options = '<option value="1">Root</option>';
	$kept = pg_field_form_kept_folder($options, '7', null);

	pg_assert_contains('<option value="7" selected>' . h(lang('(deleted folder)')) . '</option>', $kept['options'], 'a deleted folder stays selected and is marked');
	pg_assert_same(0, strpos($kept['options'], $options), 'the listed options come first');
	pg_assert_contains('id="upload_folder_id_help"', $kept['help'], 'the help line has the id the select points at');
	pg_assert_contains(h(lang('The folder this field uploads to was deleted. Choose another folder.')), $kept['help'], 'the deleted folder line');
}

function test_upload_folder_kept_not_offered_folder()
{
	$kept = pg_field_form_kept_folder('<option value="1">Root</option>', 7, 'CVs <old>');

	pg_assert_contains('<option value="7" selected>' . h('CVs <old> ' . lang('(no longer offered)')) . '</option>', $kept['options'], 'a binned folder keeps its escaped name and is marked');
	pg_assert_contains(h(lang('The folder this field uploads to is no longer offered here. Choose another folder.')), $kept['help'], 'the not offered line');
}

function test_upload_folder_kept_screens_wired()
{
	$form = file_get_contents(PG_FUNCTIONS_DIR . '/includes/forms/field_form.php');
	pg_assert_contains('pg_field_form_kept_folder($folder_options, $field[\'upload_folder_id\'], pg_field_form_folder_name(', $form, 'Edit Field keeps the stored upload folder');
	pg_assert_contains('aria-describedby="upload_folder_id_help"', $form, 'the select points at its help line');

	$check = file_get_contents(PG_FUNCTIONS_DIR . '/includes/explorer/handle_recycle.php');
	pg_assert_contains("WHERE (upload_folder_id IN (\" . \$sql_ids . \")) AND (type IN ('file upload', 'signature'))", $check, 'the delete check counts the upload fields of the whole subtree');
	pg_assert_contains("'upload_fields' => \$upload_fields,", $check, 'and answers the count');

	$explorer = file_get_contents(PG_FUNCTIONS_DIR . '/view_folders.php');
	pg_assert_contains("'delete_folder_upload_fields' => lang('{var:1} form field(s) save their uploads in this folder or a folder inside it.", $explorer, 'the line is translated');
	pg_assert_contains('if (response.upload_fields > 0) {', $explorer, 'the confirmation says it only when a field uses the folder');
}
