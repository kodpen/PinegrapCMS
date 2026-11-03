<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the File Manager's copies (a pasted file and a copied folder):
 * a submitted form's upload stays marked as one (files.attachment), so
 * get_file.php keeps refusing the copy to visitors without edit access.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The INSERT INTO files statement that follows $marker in $file.
function _explorer_copy_attachment_insert($file, $marker)
{
	$src = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
	$at = strpos($src, $marker);
	if ($at === false) return '';
	$start = strpos($src, 'INSERT INTO files', $at);
	if ($start === false) return '';
	$end = strpos($src, 'UNIX_TIMESTAMP())', $start);
	return ($end === false) ? '' : substr($src, $start, $end - $start);
}

function test_explorer_copy_attachment_folder_copy_keeps_the_mark()
{
	$sql = _explorer_copy_attachment_insert('view_folder_and_files_f.php', 'function pg_explorer_copy_folder(');
	pg_assert_true($sql !== '', 'the folder copy inserts files');
	pg_assert_true(strpos($sql, 'attachment,') !== false, 'the column is written');
	pg_assert_true(strpos($sql, "(int) \$file['attachment']") !== false, 'from the source row');
}

function test_explorer_copy_attachment_file_paste_keeps_the_mark()
{
	$sql = _explorer_copy_attachment_insert('includes/explorer/handle_files.php', "case 'file':");
	pg_assert_true($sql !== '', 'the paste inserts a file');
	pg_assert_true(strpos($sql, 'attachment,') !== false, 'the column is written');
	pg_assert_true(strpos($sql, "(int) \$file['attachment']") !== false, 'from the source row');
}
