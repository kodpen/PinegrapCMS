<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for pg_file_name_add_suffix(): the ZIP import's tag and its
 * "-backup" name stay within the 100 characters of files.name by shortening
 * the name before the suffix, never the suffix or the extension.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_file_name_suffix_short_names()
{
	require_once(PG_FUNCTIONS_DIR . '/includes/fn/files.php');

	pg_assert_same('photo-tag.png', pg_file_name_add_suffix('photo.png', 'tag', true), 'tag before the extension');
	pg_assert_same('site-tag.min.js', pg_file_name_add_suffix('site.min.js', 'tag', true), 'tag before the first dot');
	pg_assert_same('site.min-backup.js', pg_file_name_add_suffix('site.min.js', 'backup'), 'backup before the last dot');
	pg_assert_same('README-backup', pg_file_name_add_suffix('README', 'backup'), 'no extension: appended');
}

function test_file_name_suffix_long_names_keep_suffix_and_extension()
{
	require_once(PG_FUNCTIONS_DIR . '/includes/fn/files.php');

	$name = str_repeat('c', 93) . '[1].png';
	pg_assert_same(100, mb_strlen($name), 'fixture is 100 characters');

	$tagged = pg_file_name_add_suffix($name, 'pgtestf01', true);
	pg_assert_same(100, mb_strlen($tagged), 'tagged name fits the column');
	pg_assert_same('-pgtestf01.png', mb_substr($tagged, -14), 'tag and extension survive');

	$backup = pg_file_name_add_suffix($name, 'backup');
	pg_assert_same(100, mb_strlen($backup), 'backup name fits the column');
	pg_assert_same('-backup.png', mb_substr($backup, -11), 'backup suffix and extension survive');
}

function test_file_name_suffix_extension_without_room_cuts_the_whole_name()
{
	require_once(PG_FUNCTIONS_DIR . '/includes/fn/files.php');

	$name = 'a.' . str_repeat('x', 120);
	pg_assert_same(100, mb_strlen(pg_file_name_add_suffix($name, 'backup')), 'never longer than the column');
}

// Import My Site with a tag backs up an existing file as the ZIP import does:
// the backup name within the column, the disk file renamed first, a failed
// rename leaving the old file alone and the new one taking a name of its own.
function test_import_design_backup_renames_the_disk_file_first()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/import_design.php');
	$rename = strpos($source, "if (!@rename(FILE_DIRECTORY_PATH . '/' . \$name, FILE_DIRECTORY_PATH . '/' . \$backup_name)) {\n                        \$old_file = false;");
	$update = strpos($source, "SET \n                            name = '\" . e(\$backup_name) . \"',");

	pg_assert_contains("'name' => pg_file_name_add_suffix(\$name, 'backup'),", $source, 'the backup name fits files.name');
	pg_assert_true($rename !== false, 'a failed rename is not a backup');
	pg_assert_true(($rename !== false) && ($update !== false) && ($rename < $update), 'the disk file is renamed before the row');
	pg_assert_false(strpos($source, "'-backup.' . \$old_extension") !== false, 'no hand-made backup name is left');
}
