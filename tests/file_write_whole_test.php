<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for pg_file_write_whole() (includes/fn/files.php), the write behind
 * Save and Duplicate of Edit Theme CSS, Edit JavaScript and the text file
 * editor, and behind Create File: the stored file is replaced only once the
 * whole content is on the disk, and a failed write leaves the old file and
 * no temporary file behind. The failures are made without file modes (the
 * tests may run as root): a folder that does not exist, a folder that is a
 * file, a target that is a folder.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function file_write_whole_test_folder()
{
	$folder = sys_get_temp_dir() . '/pg_write_whole_' . bin2hex(random_bytes(6));
	mkdir($folder);
	return $folder;
}

function file_write_whole_test_remove($path)
{
	if (is_dir($path) && !is_link($path)) {
		foreach (scandir($path) as $name) {
			if ($name !== '.' && $name !== '..') {
				file_write_whole_test_remove($path . '/' . $name);
			}
		}
		rmdir($path);
	} elseif (file_exists($path) || is_link($path)) {
		unlink($path);
	}
}

function file_write_whole_test_names($folder)
{
	return array_values(array_diff(scandir($folder), array('.', '..')));
}

function test_file_write_whole_writes_a_new_file_and_replaces_an_old_one()
{
	$folder = file_write_whole_test_folder();
	$path = $folder . '/theme.css';

	pg_assert_true(pg_file_write_whole($path, "body { color: red; }\n"), 'a new file is written');
	pg_assert_same("body { color: red; }\n", file_get_contents($path), 'with the whole content');

	pg_assert_true(pg_file_write_whole($path, 'p{}'), 'an existing file is replaced');
	pg_assert_same('p{}', file_get_contents($path), 'by the new content, the old one gone');

	pg_assert_true(pg_file_write_whole($path, ''), 'an empty content is a write too');
	pg_assert_same('', file_get_contents($path), 'the file is emptied');

	$large = str_repeat("0123456789abcdef\n", 200000);
	pg_assert_true(pg_file_write_whole($path, $large), 'a large content is written');
	pg_assert_same(strlen($large), filesize($path), 'every byte of it');

	pg_assert_same(array('theme.css'), file_write_whole_test_names($folder), 'no temporary file is left');

	file_write_whole_test_remove($folder);
}

function test_file_write_whole_fails_when_the_folder_is_missing_or_a_file()
{
	$folder = file_write_whole_test_folder();

	pg_assert_same(false, pg_file_write_whole($folder . '/missing/theme.css', 'p{}'), 'a folder that does not exist');

	file_put_contents($folder . '/plain', 'x');
	pg_assert_same(false, pg_file_write_whole($folder . '/plain/theme.css', 'p{}'), 'a folder that is a file');
	pg_assert_same('x', file_get_contents($folder . '/plain'), 'the file in the way is untouched');

	pg_assert_same(array('plain'), file_write_whole_test_names($folder), 'nothing else was created');

	file_write_whole_test_remove($folder);
}

function test_file_write_whole_leaves_a_folder_in_the_way_and_no_temporary_file()
{
	$folder = file_write_whole_test_folder();
	mkdir($folder . '/theme.css');
	file_put_contents($folder . '/theme.css/inside.txt', 'kept');

	pg_assert_same(false, pg_file_write_whole($folder . '/theme.css', 'p{}'), 'a target that is a folder');
	pg_assert_true(is_dir($folder . '/theme.css'), 'the folder stays');
	pg_assert_same('kept', file_get_contents($folder . '/theme.css/inside.txt'), 'with what it holds');
	pg_assert_same(array('theme.css'), file_write_whole_test_names($folder), 'the temporary file is deleted');

	file_write_whole_test_remove($folder);
}

function test_file_write_whole_refuses_an_empty_path()
{
	pg_assert_same(false, pg_file_write_whole('', 'p{}'), 'no path');
}

// The screens write through the helper and keep no unchecked fopen() write;
// the editors keep the typed code for the screen they open again.
function test_file_write_whole_used_by_the_editors_and_create_file()
{
	foreach (array('edit_theme_css.php', 'edit_javascript.php', 'editor_edit_file.php', 'create_file.php') as $screen) {
		$source = file_get_contents(PG_FUNCTIONS_DIR . '/' . $screen);
		pg_assert_true(strpos($source, 'pg_file_write_whole(') !== false, $screen . ' writes through the helper');
		pg_assert_true(strpos($source, 'fopen(') === false, $screen . ' has no unchecked fopen() write');
		pg_assert_true(strpos($source, "'{var:1} could not be written to disk.'") !== false, $screen . ' names the file that could not be written');
	}

	foreach (array('edit_theme_css.php', 'edit_javascript.php', 'editor_edit_file.php') as $screen) {
		$source = file_get_contents(PG_FUNCTIONS_DIR . '/' . $screen);
		pg_assert_true(strpos($source, "assign_field_value('code', \$_POST['code'])") !== false, $screen . ' keeps the typed code');
		pg_assert_true(strpos($source, "get_field_value('code')") < strpos($source, 'file_get_contents('), $screen . ' shows the kept code before reading the file');
	}
}
