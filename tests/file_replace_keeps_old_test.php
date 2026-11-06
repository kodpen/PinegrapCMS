<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the screens and passes that write over a stored file: Edit
 * File's code save and SVG conversion (edit_file.php), the refresh of
 * imported style sheets and scripts (update_imported_items.php) and the
 * visual designer's custom CSS/JS asset files (pg_designer_sync_asset_files()).
 * Each writes through pg_file_write_whole(), so a full disk or a folder PHP
 * may not write leaves the old file in place; only a whole new file deletes
 * the old one or changes the record.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function file_replace_keeps_old_test_source($relative)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $relative);
}

// The code save writes as a whole, and a failed write puts a renamed file
// back under its stored name before refusing.
function test_file_replace_keeps_old_edit_file_code_save()
{
	$source = file_replace_keeps_old_test_source('edit_file.php');

	pg_assert_same(false, strpos($source, "fopen(\$file_path, 'w')"), 'no truncating fopen() over the stored file');

	$write = strpos($source, "if (!pg_file_write_whole(\$file_path, \$_POST['code'])) {");
	pg_assert_true($write !== false, 'the code is written through pg_file_write_whole()');

	$tail = ($write !== false) ? substr($source, $write, 900) : '';
	pg_assert_contains('@rename($file_path, $old_file_path)', $tail, 'a renamed file is moved back');
	pg_assert_contains("'{var:1} could not be written to disk.'", $tail, 'the refusal names the file');

	$update = strpos($source, 'name = \'" . escape($name) . "\',');
	pg_assert_true(($write !== false) && ($update !== false) && ($write < $update), 'the record is updated only after the write');
}

// The SVG overwrite deletes the SVG only after the raster file is whole.
function test_file_replace_keeps_old_edit_file_svg_convert()
{
	$source = file_replace_keeps_old_test_source('edit_file.php');

	pg_assert_same(false, strpos($source, 'file_put_contents($new_path, $image_bytes);'), 'no unchecked raster write');
	pg_assert_same(2, substr_count($source, 'if (!pg_file_write_whole($new_path, $image_bytes)) {'), 'both conversions check the write');

	$overwrite = strpos($source, '// Overwrite: replace SVG with raster');
	$branch = ($overwrite !== false) ? substr($source, $overwrite, 1600) : '';
	$write = strpos($branch, 'if (!pg_file_write_whole($new_path, $image_bytes)) {');
	$unlink = strpos($branch, "@unlink(FILE_DIRECTORY_PATH . '/' . \$row['name']);");
	pg_assert_true(($write !== false) && ($unlink !== false) && ($write < $unlink), 'the old SVG is removed after the checked write');
}

function test_file_replace_keeps_old_imported_items_refresh()
{
	$source = file_replace_keeps_old_test_source('update_imported_items.php');

	pg_assert_same(false, strpos($source, "@file_put_contents(FILE_DIRECTORY_PATH . '/' . \$item['name'], \$content);"), 'no unchecked in-place write');

	$write = strpos($source, "if (pg_file_write_whole(FILE_DIRECTORY_PATH . '/' . \$item['name'], \$content)) {");
	$update = strpos($source, 'UPDATE files');
	pg_assert_true(($write !== false) && ($update !== false) && ($write < $update), 'the size is updated only after a whole write');
}

function test_file_replace_keeps_old_designer_asset_files()
{
	$source = file_replace_keeps_old_test_source('includes/fn/designer.php');

	pg_assert_same(false, strpos($source, "@file_put_contents(FILE_DIRECTORY_PATH . '/' . \$asset['name'], \$asset['content']);"), 'no unchecked in-place write');
	pg_assert_true(strpos($source, "if (!pg_file_write_whole(FILE_DIRECTORY_PATH . '/' . \$asset['name'], (string) \$asset['content'])) continue;") !== false, 'a failed write records nothing');
}
