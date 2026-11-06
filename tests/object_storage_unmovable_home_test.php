<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for pg_file_storage_comes_home() (includes/fn/files.php): a file in
 * the object store that a rename or the design flag made a file the move job
 * never takes (a design file, a style sheet, a script) is brought back to
 * the disk, where the design screens, the code editors and the design export
 * read it.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/files.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/file_storage.php');

function test_object_storage_unmovable_home_design_flag()
{
	$row = array('id' => 4, 'name' => 'logo.svg', 'design' => 1, 'theme' => 0, 'storage' => 's3', 'storage_key' => 'files/4/logo.svg');

	pg_assert_true(pg_file_storage_comes_home($row), 'a stored file marked as a design file');

	$row['design'] = 0;
	pg_assert_false(pg_file_storage_comes_home($row), 'a stored upload stays in the store');
}

function test_object_storage_unmovable_home_renamed_to_code()
{
	$row = array('id' => 5, 'name' => 'custom.css', 'design' => 0, 'theme' => 0, 'storage' => 's3', 'storage_key' => 'files/5/custom.txt');
	pg_assert_true(pg_file_storage_comes_home($row), 'renamed to a style sheet: get_file.php rewrites {path} only on the disk');

	$row['name'] = 'app.JS';
	pg_assert_true(pg_file_storage_comes_home($row), 'renamed to a script');
}

function test_object_storage_unmovable_home_local_rows()
{
	pg_assert_false(pg_file_storage_comes_home(array('id' => 6, 'name' => 'site.css', 'design' => 1, 'theme' => 0, 'storage' => 'local', 'storage_key' => '')), 'already on the disk');
	pg_assert_false(pg_file_storage_comes_home(array('id' => 6, 'name' => 'site.css', 'design' => 1, 'theme' => 0)), 'a row read before the storage columns');
	pg_assert_false(pg_file_storage_comes_home(array('id' => 6, 'name' => 'site.css', 'design' => 1, 'theme' => 0, 'storage' => 's3', 'storage_key' => '')), 'no key: nothing to fetch');
}
