<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the chat attachment check of the doors that copy a file
 * somewhere else: a File Manager paste or folder copy, a zip archive, the
 * Workspace file picker. A team chat attachment opens only for a side of its
 * conversation or a role-0 administrator (get_file.php); a copy is an
 * ordinary file of its folder, so those doors refuse anyone else.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function _file_copy_gate_rows()
{
	return array(
		// File 10: one conversation between users 2 and 3.
		array('file_id' => 10, 'initiator_user_id' => 2, 'target_user_id' => 3),
		// File 11: forwarded, so carried by two conversations.
		array('file_id' => 11, 'initiator_user_id' => 2, 'target_user_id' => 3),
		array('file_id' => 11, 'initiator_user_id' => 4, 'target_user_id' => 5),
	);
}

function test_file_copy_gate_outsider_manager_refused()
{
	$refused = pg_chat_attachment_refused_from_rows(_file_copy_gate_rows(), 7, 2);
	sort($refused);
	pg_assert_same(array(10, 11), $refused, 'a manager outside both conversations copies neither');
}

function test_file_copy_gate_designer_outsider_refused()
{
	pg_assert_same(array(10, 11), pg_chat_attachment_refused_from_rows(_file_copy_gate_rows(), 7, 1), 'a designer is no side either');
}

function test_file_copy_gate_side_of_one_conversation_allowed()
{
	pg_assert_same(array(10), pg_chat_attachment_refused_from_rows(_file_copy_gate_rows(), 5, 2), 'a side of the second conversation may copy file 11 only');
	pg_assert_same(array(), pg_chat_attachment_refused_from_rows(_file_copy_gate_rows(), 3, 3), 'a side of the first may copy both');
}

function test_file_copy_gate_administrator_allowed()
{
	pg_assert_same(array(), pg_chat_attachment_refused_from_rows(_file_copy_gate_rows(), 1, 0), 'a role-0 administrator opens every attachment');
}

function test_file_copy_gate_no_rows_nothing_refused()
{
	pg_assert_same(array(), pg_chat_attachment_refused_from_rows(array(), 7, 2), 'a file no chat message carries is no chat attachment');
	pg_assert_same(array(), pg_chat_attachment_refused_ids(array(0, -3), array('id' => 7, 'role' => 2)), 'no ids, no query');
}

function test_file_copy_gate_signed_out_refused()
{
	pg_assert_same(array(10, 11), pg_chat_attachment_refused_from_rows(_file_copy_gate_rows(), 0, 0), 'nobody signed in opens none');
}

// The doors ask the gate before they copy.
function _file_copy_gate_source($file, $from, $to)
{
	$src = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
	$start = strpos($src, $from);
	if ($start === false) return '';
	$end = ($to === '') ? strlen($src) : strpos($src, $to, $start + strlen($from));
	return ($end === false) ? '' : substr($src, $start, $end - $start);
}

function test_file_copy_gate_doors_ask_before_copying()
{
	$paste = _file_copy_gate_source('includes/explorer/handle_files.php', "case 'file':", '@copy(');
	pg_assert_true(strpos($paste, 'pg_chat_attachment_refused(') !== false, 'the paste asks before copy()');

	$folder = _file_copy_gate_source('view_folder_and_files_f.php', '// Files.', '@copy(');
	pg_assert_true(strpos($folder, 'pg_chat_attachment_refused_ids(') !== false, 'the folder copy asks before copy()');

	$zip = _file_copy_gate_source('includes/explorer/handle_zip.php', 'function pg_explorer_handle_zip_create(', '->addFile(');
	pg_assert_true(strpos($zip, 'pg_chat_attachment_refused(') !== false, 'the selection zip asks before addFile()');

	$zip_folder = _file_copy_gate_source('includes/explorer/archives.php', 'function pg_explorer_zip_add_folder(', '->addFile(');
	pg_assert_true(strpos($zip_folder, 'pg_chat_attachment_refused_ids(') !== false, 'the folder zip asks before addFile()');

	$library = _file_copy_gate_source('includes/workspace/library.php', 'function ws_library_file(', "'ok' => true");
	pg_assert_true(strpos($library, 'pg_chat_attachment_refused(') !== false, 'the Workspace picker asks before it gives the file');
}

// An archive holding a submitted-form or comment upload stays one, as a
// pasted copy does, so get_file.php keeps it to whoever may edit its folder.
function test_file_copy_gate_zip_keeps_the_attachment_mark()
{
	$insert = _file_copy_gate_source('includes/explorer/handle_zip.php', 'INSERT INTO files (name, folder', 'UNIX_TIMESTAMP())');
	pg_assert_true(strpos($insert, 'attachment,') !== false, 'the archive row writes the column');
	pg_assert_true(strpos($insert, '$holds_attachment ? 1 : 0') !== false, 'from what went in');

	$zip_folder = _file_copy_gate_source('includes/explorer/archives.php', 'function pg_explorer_zip_add_folder(', 'return $added;');
	pg_assert_true(strpos($zip_folder, '&$holds_attachment') !== false, 'the folder zip reports it');
	pg_assert_true(strpos($zip_folder, "\$file['attachment']") !== false, 'from the file rows');
}
