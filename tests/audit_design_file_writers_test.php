<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the design file, theme, Create File and code editor screens:
 * each INSERT into files records a create line after reading the new id,
 * each UPDATE or DELETE reads the row before the write and records an
 * update or delete line after it, as Add File and Edit File do.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function audit_design_file_writers_test_source($relative)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $relative);
}

// Every position of $needle in $source, in order.
function audit_design_file_writers_test_positions($source, $needle)
{
	$positions = array();
	$offset = 0;
	while (($found = strpos($source, $needle, $offset)) !== false) {
		$positions[] = $found;
		$offset = $found + 1;
	}
	return $positions;
}

// The first position of $needle after $from, or false.
function audit_design_file_writers_test_after($source, $needle, $from)
{
	return strpos($source, $needle, $from);
}

// Each INSERT INTO files is followed by a create line before the next INSERT.
function audit_design_file_writers_test_creates($file, $expected_inserts)
{
	$source = audit_design_file_writers_test_source($file);
	$inserts = audit_design_file_writers_test_positions($source, 'INSERT INTO files');
	pg_assert_same($expected_inserts, count($inserts), $file . ': INSERT INTO files count');

	foreach ($inserts as $i => $insert) {
		$record = audit_design_file_writers_test_after($source, "pg_audit_record('create', 'files', ", $insert);
		$next = isset($inserts[$i + 1]) ? $inserts[$i + 1] : strlen($source);
		pg_assert_true(($record !== false) && ($record < $next), $file . ': insert ' . ($i + 1) . ' records a create line');
	}
}

// Each UPDATE files / DELETE FROM files has a read before it and a record after it.
function audit_design_file_writers_test_writes($file, $needle, $action, $expected)
{
	$source = audit_design_file_writers_test_source($file);
	$writes = audit_design_file_writers_test_positions($source, $needle);
	pg_assert_same($expected, count($writes), $file . ': ' . $needle . ' count');

	$previous = 0;
	foreach ($writes as $i => $write) {
		$before = substr($source, $previous, $write - $previous);
		$read = strrpos($before, "pg_audit_fetch('files', ");
		pg_assert_true($read !== false, $file . ': ' . $needle . ' ' . ($i + 1) . ' reads the row first');
		$record = audit_design_file_writers_test_after($source, "pg_audit_record('" . $action . "', 'files', ", $write);
		$next = isset($writes[$i + 1]) ? $writes[$i + 1] : strlen($source);
		pg_assert_true(($record !== false) && ($record < $next), $file . ': ' . $needle . ' ' . ($i + 1) . ' records a ' . $action . ' line');
		$previous = $write;
	}
}

function test_audit_add_design_file_records_each_upload()
{
	audit_design_file_writers_test_creates('add_design_file.php', 1);
	$source = audit_design_file_writers_test_source('add_design_file.php');
	pg_assert_true(strpos($source, '$new_file_id = (int) mysqli_insert_id(db::$con);') !== false, 'add_design_file.php: the new id is read before the line');
}

function test_audit_edit_design_file_records_save_and_delete()
{
	audit_design_file_writers_test_writes('edit_design_file.php', 'DELETE FROM files', 'delete', 1);
	audit_design_file_writers_test_writes('edit_design_file.php', 'UPDATE files', 'update', 1);
}

// The two system-theme paths record after the size UPDATE, the upload path after its INSERT.
function test_audit_add_theme_file_records_one_create_per_theme()
{
	$file = 'add_theme_file.php';
	$source = audit_design_file_writers_test_source($file);
	$inserts = audit_design_file_writers_test_positions($source, 'INSERT INTO files');
	$records = audit_design_file_writers_test_positions($source, "pg_audit_record('create', 'files', ");
	$size_update = strpos($source, 'UPDATE files SET size = ? WHERE id = ?');

	pg_assert_same(3, count($inserts), $file . ': three inserts');
	pg_assert_same(2, count($records), $file . ': one line after the size update, one after the upload');
	pg_assert_true(($inserts[1] < $size_update) && ($size_update < $records[0]) && ($records[0] < $inserts[2]), $file . ': system themes record after their size');
	pg_assert_true($inserts[2] < $records[1], $file . ': the upload records after its insert');
}

// Save, delete, and the deactivation of the previously active desktop and mobile theme.
function test_audit_edit_theme_file_records_save_delete_and_deactivation()
{
	$file = 'edit_theme_file.php';
	audit_design_file_writers_test_writes($file, 'DELETE FROM files', 'delete', 1);
	audit_design_file_writers_test_writes($file, 'UPDATE files', 'update', 3);
	$source = audit_design_file_writers_test_source($file);
	pg_assert_same(2, substr_count($source, "pg_audit_record('update', 'files', (int) \$old_activated_theme_id, "), $file . ': both deactivations record the old theme');
}

// The code editors record their copy and their save.
function test_audit_code_editors_record_copy_and_save()
{
	foreach (array('edit_javascript.php', 'edit_theme_css.php', 'editor_edit_file.php') as $file) {
		audit_design_file_writers_test_creates($file, 1);
		$source = audit_design_file_writers_test_source($file);
		$read = strpos($source, 'pg_audit_fetch_file((int) $id, ');
		$write = ($read === false) ? false : strpos($source, 'pg_file_write_whole(', $read);
		$update = ($write === false) ? false : strpos($source, 'UPDATE files', $write);
		$record = ($update === false) ? false : strpos($source, 'pg_audit_record_file((int) $id, $audit_before, ', $update);
		pg_assert_true(($write !== false) && ($update !== false), $file . ': the row and its content digest are read before the file is written');
		pg_assert_true($record !== false, $file . ': the save records a line with the new content digest');
	}
}

function test_audit_create_file_records_the_new_file()
{
	audit_design_file_writers_test_creates('create_file.php', 1);
}

// Edit File writes the posted code to disk: the row and the content digest are
// read from the stored path before the rename and the write, and the save
// records a line with the digest of the file under its new name.
function test_audit_edit_file_records_content_digest()
{
	$source = audit_design_file_writers_test_source('edit_file.php');
	$read = strpos($source, 'pg_audit_fetch_file((int) $file_id, $stored_file_path)');
	$rename = ($read === false) ? false : strpos($source, '@rename($old_file_path, $file_path)', $read);
	$write = ($rename === false) ? false : strpos($source, "fwrite(\$handle, \$_POST['code'])", $rename);
	$update = ($write === false) ? false : strpos($source, 'UPDATE files SET', $write);
	$record = ($update === false) ? false : strpos($source, 'pg_audit_record_file((int) $file_id, $audit_before_content, $file_path)', $update);
	pg_assert_true($rename !== false && $write !== false, 'the digest is read before the rename and the write');
	pg_assert_true($record !== false, 'the code save records a line with the new content digest');
}
