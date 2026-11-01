<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests that every door through which the file manager writes a file refuses
 * a document the ERP module keeps (pg_files_include_erp_document()): the
 * file's own screen, the explorer actions that take a file id, the explorer's
 * file read, and the image editor's save. Such a row sits with no folder,
 * which check_edit_access() lets staff write to, so the refusal has to be
 * asked for by name at each door.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function files_erp_readonly_test_source($relative)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $relative);
}

function test_files_erp_readonly_every_write_door_asks()
{
	$doors = array(
		'edit_file.php'                     => 'pg_files_include_erp_document(array($_REQUEST[\'id\'] ?? 0, $_POST[\'id\'] ?? 0))',
		'view_folder_and_files_f.php'       => 'pg_files_include_erp_document(pg_explorer_request_file_ids($type, $request))',
		'includes/panel/explorer.php'       => 'pg_files_include_erp_document(array($row[\'id\']))',
		'includes/fn/image_editor.php'      => 'pg_files_include_erp_document(array($row[\'id\']))',
	);

	foreach ($doors as $file => $guard) {
		pg_assert_contains($guard, files_erp_readonly_test_source($file), $file . ' asks');
	}
}

// The image editor's guard sits after the row is found and before the
// folder right is asked, so neither a replace nor a copy beside the document
// can start (pg_image_editor_file(), which open and save go through).
function test_files_erp_readonly_image_editor_refuses_before_any_write()
{
	$source = files_erp_readonly_test_source('includes/fn/image_editor.php');
	$start = strpos($source, 'function pg_image_editor_file(');
	$body = substr($source, $start, strpos($source, "\n}\n", $start) - $start);
	$guard = strpos($body, 'pg_files_include_erp_document(array($row[\'id\']))');

	pg_assert_true($guard !== false, 'the guard is there');
	pg_assert_true($guard > strpos($body, '$row = '), 'after the row is read');
	pg_assert_true($guard < strpos($body, 'check_edit_access('), 'before the folder right is asked');
	pg_assert_contains("pg_image_editor_refusal(lang('ERP documents are read-only in the file manager.'), 403)", $body, 'the same sentence as the other doors');
}
