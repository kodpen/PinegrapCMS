<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the rename on Edit Design File (edit_design_file.php): the file
 * is renamed on the disk before the record takes the new name, and a failed
 * rename stops the save, so the record never names a file that is not there.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_design_file_rename_before_record_update()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/edit_design_file.php');

	$rename = strpos($source, "@rename(FILE_DIRECTORY_PATH . '/' . \$stored_name, FILE_DIRECTORY_PATH . '/' . \$name)");
	$update = strpos($source, 'UPDATE files');

	pg_assert_same(true, $rename !== false, 'the rename result is read');
	pg_assert_same(true, ($rename !== false) && ($update !== false) && ($rename < $update), 'the disk rename comes before the record update');
	pg_assert_contains("lang('The file could not be renamed on the file system. Check write permission for the file directory.')", $source, 'a failed rename stops the save with a message');
}
