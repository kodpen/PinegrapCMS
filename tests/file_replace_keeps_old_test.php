<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the screens and passes that write over a stored file: Edit
 * File's code save and SVG conversion (edit_file.php), the refresh of
 * imported style sheets and scripts (update_imported_items.php), the
 * visual designer's custom CSS/JS asset files (pg_designer_sync_asset_files()),
 * each file Import My Site and Import ZIP create (import_design.php,
 * import_zip.php), the visual designer's file save, New File and theme
 * saves (includes/panel/designer.php, pg_design_theme_save_file()), the
 * panel's upload_file action (pg_panel_upload_file()), the designer's
 * HTML/ZIP import (pg_di_store_file()) and the site migration's design files
 * (pg_migration_write_design_files()).
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

// Import My Site and Import ZIP write each file as a whole before its row is
// inserted; a failed write moves a backed-up old file back under its name.
function file_replace_keeps_old_test_importer($relative)
{
	$source = file_replace_keeps_old_test_source($relative);

	pg_assert_same(false, strpos($source, "@file_put_contents(FILE_DIRECTORY_PATH . '/' . \$name"), $relative . ': no unchecked write');

	$write = strpos($source, "if (!pg_file_write_whole(FILE_DIRECTORY_PATH . '/' . \$name, \$content)) {");
	$insert = strpos($source, 'INSERT INTO files');
	pg_assert_true(($write !== false) && ($insert !== false) && ($write < $insert), $relative . ': the row is inserted only after a whole write');

	$branch = ($write !== false) ? substr($source, $write, 1400) : '';
	$restore = strpos($branch, "@rename(FILE_DIRECTORY_PATH . '/' . \$backup_name, FILE_DIRECTORY_PATH . '/' . \$name)");
	$rename_row = strpos($branch, "SET name = '\" . e(\$name) . \"'");
	pg_assert_true(($restore !== false) && ($rename_row !== false) && ($restore < $rename_row), $relative . ': the backup goes back before its row');
	pg_assert_contains('continue;', $branch, $relative . ': the item is skipped');

	return $branch;
}

function test_file_replace_keeps_old_import_design()
{
	$branch = file_replace_keeps_old_test_importer('import_design.php');
	pg_assert_contains("'reason' => 'write'", $branch, 'reported as not written');
}

function test_file_replace_keeps_old_import_zip()
{
	$branch = file_replace_keeps_old_test_importer('import_zip.php');
	pg_assert_contains("\$skipped_unread[] = lang(array('string' => '{var:1} could not be written to disk.'", $branch, 'reported as not written');
}

// The part of $source from $start up to $end (or the end of the source).
function file_replace_keeps_old_test_section($source, $start, $end)
{
	$from = strpos($source, $start);
	$to = ($from === false) ? false : strpos($source, $end, $from + strlen($start));

	return ($from === false) ? '' : (($to === false) ? substr($source, $from) : substr($source, $from, $to - $from));
}

// $first comes before $second in $section, and both are there.
function file_replace_keeps_old_test_before($section, $first, $second)
{
	$a = strpos($section, $first);
	$b = strpos($section, $second);

	return ($a !== false) && ($b !== false) && ($a < $b);
}

// The visual designer's file save, New File and theme save write as a whole
// before the row is written or updated.
function test_file_replace_keeps_old_designer_file_endpoint()
{
	$source = file_replace_keeps_old_test_source('includes/panel/designer.php');

	pg_assert_same(false, strpos($source, '@file_put_contents($df_path, $df_content)'), 'no in-place write in save');
	pg_assert_same(false, strpos($source, "@fopen(\$df_path, 'w')"), 'no truncating fopen() in New File');
	pg_assert_same(false, strpos($source, "@file_put_contents(FILE_DIRECTORY_PATH . '/' . \$file_name, \$css)"), 'no unchecked theme write');

	$save = file_replace_keeps_old_test_section($source, "case 'save':", 'default:');
	pg_assert_true(file_replace_keeps_old_test_before($save, 'if (!pg_file_write_whole($df_path, $df_content)) {', 'UPDATE files'), 'save updates the row only after a whole write');
	pg_assert_contains("'{var:1} could not be written to disk.', 'vars' => \$df_name_safe", $save, 'save names the file it could not write');

	$create = file_replace_keeps_old_test_section($source, "case 'create':", "case 'read':");
	pg_assert_true(file_replace_keeps_old_test_before($create, 'if (!pg_file_write_whole($df_path, $df_content)) {', 'INSERT INTO files'), 'New File inserts the row only after a whole write');
	pg_assert_true(file_replace_keeps_old_test_before($create, 'pg_file_write_whole(', 'log_activity('), 'New File logs only after a whole write');
	pg_assert_contains("'{var:1} could not be written to disk.', 'vars' => \$df_name)", $create, 'New File names the file it could not write');

	$theme = file_replace_keeps_old_test_section($source, "case 'theme_save':", "case 'theme_look_css':");
	pg_assert_true(file_replace_keeps_old_test_before($theme, "if (!pg_file_write_whole(FILE_DIRECTORY_PATH . '/' . \$file_name, \$css)) {", 'INSERT INTO files'), 'theme_save inserts the row only after a whole write');
}

// The look and palette builders' save writes the stylesheet as a whole
// before its row is inserted.
function test_file_replace_keeps_old_design_theme_save_file()
{
	$source = file_replace_keeps_old_test_source('includes/fn/design_themes.php');
	$body = file_replace_keeps_old_test_section($source, 'function pg_design_theme_save_file(', "\n}\n");

	pg_assert_same(false, strpos($body, 'file_put_contents('), 'no unchecked theme write');
	pg_assert_true(file_replace_keeps_old_test_before($body, "if (!pg_file_write_whole(FILE_DIRECTORY_PATH . '/' . \$file_name, \$css)) {", 'INSERT INTO files'), 'the row is inserted only after a whole write');
}

// The panel's upload_file action writes the upload as a whole and answers
// with an error before any row is inserted. Before, an unchecked fopen()
// reached fwrite(false), a fatal error on PHP 8, and a row was inserted for
// a file that was not written.
function test_file_replace_keeps_old_panel_upload_file()
{
	$source = file_replace_keeps_old_test_source('includes/panel/content.php');
	$body = file_replace_keeps_old_test_section($source, 'function pg_panel_upload_file(', "\n}\n");

	pg_assert_same(false, strpos($body, 'fopen('), 'no unchecked fopen()');
	$write = "if (!pg_file_write_whole(FILE_DIRECTORY_PATH . '/' . \$file_name, \$image_data)) {";
	pg_assert_true(file_replace_keeps_old_test_before($body, $write, 'INSERT INTO files'), 'the row is inserted only after a whole write');

	$branch = file_replace_keeps_old_test_section($body, $write, "\n    }\n");
	pg_assert_contains("respond(array('status' => 'error', 'message' => lang(array('string' => '{var:1} could not be written to disk.', 'vars' => \$file_name))));", $branch, 'a failed write answers with an error');
	pg_assert_same(false, strpos($branch, 'INSERT INTO files'), 'a failed write inserts no row');
}

// The visual designer's HTML/ZIP import and the site migration's design
// files write each file as a whole before its row is inserted; a cut-off
// write was taken for a whole one before and left a full-size row.
function test_file_replace_keeps_old_designer_import_store_file()
{
	$source = file_replace_keeps_old_test_source('includes/designer_import.php');
	$body = file_replace_keeps_old_test_section($source, 'function pg_di_store_file(', "\n}\n");

	pg_assert_same(false, strpos($body, 'file_put_contents('), 'no unchecked write');
	$write = "if (!pg_file_write_whole(FILE_DIRECTORY_PATH . '/' . \$name, \$content)) {";
	pg_assert_true(file_replace_keeps_old_test_before($body, $write, 'INSERT INTO files'), 'the row is inserted only after a whole write');

	$branch = file_replace_keeps_old_test_section($body, $write, "\n    }\n");
	pg_assert_contains("'{var:1} could not be written to disk.', 'vars' => \$path", $branch, 'a failed write is a warning');
	pg_assert_contains('return;', $branch, 'a failed write inserts no row');
}

function test_file_replace_keeps_old_site_migration_design_files()
{
	$source = file_replace_keeps_old_test_source('includes/fn/site_migration.php');
	$body = file_replace_keeps_old_test_section($source, 'function pg_migration_write_design_files(', "\n}\n");

	pg_assert_same(false, strpos($body, 'file_put_contents('), 'no unchecked write');
	$write = "if (!pg_file_write_whole(FILE_DIRECTORY_PATH . '/' . \$name, \$f['content'])) {";
	pg_assert_true(file_replace_keeps_old_test_before($body, $write, 'INSERT INTO files'), 'the row is inserted only after a whole write');

	$branch = file_replace_keeps_old_test_section($body, $write, "\n        }\n");
	pg_assert_contains("\$out['kept'][] = \$name;", $branch, 'a failed write is listed as kept');
	pg_assert_contains('continue;', $branch, 'a failed write inserts no row');
}
