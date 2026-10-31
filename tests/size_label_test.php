<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the panel's file size labels (convert_bytes_to_string() with
 * 'auto' or 'floor'): bytes and kilobytes whole, megabytes and up with one
 * decimal, and a limit cut down so it is never shown above the real ceiling.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_size_label_auto_units()
{
	$en = array('decimal' => '.', 'thousands' => ',');

	pg_assert_same('0 B', convert_bytes_to_string(0, 'auto', $en), 'zero bytes');
	pg_assert_same('1023 B', convert_bytes_to_string(1023, 'auto', array('decimal' => '.', 'thousands' => '')), 'bytes whole');
	pg_assert_same('1 KB', convert_bytes_to_string(1024, 'auto', $en), '1024 bytes is a kilobyte');
	pg_assert_same('500 KB', convert_bytes_to_string(512000, 'auto', $en), 'kilobytes whole');
	pg_assert_same('1.0 MB', convert_bytes_to_string(1048575, 'auto', $en), 'a rounded 1024 KB moves up to megabytes');
	pg_assert_same('1.3 MB', convert_bytes_to_string(1400000, 'auto', $en), 'not truncated to 1 MB');
	pg_assert_same('1.0 MB', convert_bytes_to_string(1092000, 'auto', $en), 'the optimized size beside it');
	pg_assert_same('1.5 MB', convert_bytes_to_string(1572864, 'auto', $en), 'not rounded up to 2 MB');
	pg_assert_same('1.6 GB', convert_bytes_to_string(1664500000, 'auto', $en), 'a 1.55 GB backup is not 2 GB');
}

function test_size_label_floor_never_above_limit()
{
	$en = array('decimal' => '.', 'thousands' => ',');

	pg_assert_same('154.7 MB', convert_bytes_to_string(162276175, 'floor', $en), 'a memory-derived limit is cut down, not shown as 155 MB');
	pg_assert_same('155.0 MB', convert_bytes_to_string(162529280, 'floor', $en), 'an exact limit stays');
	pg_assert_same('1.9 GB', convert_bytes_to_string(2147483647, 'floor', $en), 'just under 2 GB is not 2.0 GB');
	pg_assert_same('1023 KB', convert_bytes_to_string(1048575, 'floor', array('decimal' => '.', 'thousands' => '')), 'just under 1 MB stays in kilobytes');
}

function test_size_label_turkish_separators()
{
	$tr = array('decimal' => ',', 'thousands' => '.');

	pg_assert_same('1,3 MB', convert_bytes_to_string(1400000, 'auto', $tr), 'a decimal comma');
	pg_assert_same('1.000 KB', convert_bytes_to_string(1024000, 'auto', $tr), 'a thousands dot');
	pg_assert_same('154,7 MB', convert_bytes_to_string(162276175, 'floor', $tr), 'a floored limit with a comma');
}

function test_size_label_default_unchanged()
{
	pg_assert_same('1 MB', convert_bytes_to_string(1400000), 'site callers keep whole units');
	pg_assert_same('1.34 MB', convert_bytes_to_string(1400000, 2), 'a fixed precision still works');
}

function test_size_label_panel_call_sites()
{
	$sites = array(
		'optimize_image.php' => "'auto')",
		'view_folder_and_files_f.php' => "convert_bytes_to_string(\$file['size'], 'auto')",
		'includes/explorer/handle_upload.php' => "convert_bytes_to_string(\$upload_limits['json_max'], 'floor')",
		'includes/explorer/handle_list.php' => "convert_bytes_to_string(\$upload_max_bytes, 'floor')",
		'includes/explorer/handle_backups.php' => "convert_bytes_to_string(\$upload_limits['json_max'], 'floor')",
		'includes/explorer/erp.php' => "convert_bytes_to_string(\$size, 'auto')",
		'includes/fn/files.php' => "convert_bytes_to_string(\$image_report['bytes_after'], 'auto')",
		'view_files.php' => "convert_bytes_to_string(\$file['size'], 'auto')",
		'view_design_files.php' => "convert_bytes_to_string(\$file['size'], 'auto')",
		'editor_select_image.php' => "convert_bytes_to_string(\$images[\$key]['size'], 'auto')",
		'editor_select_page_or_file.php' => "convert_bytes_to_string(\$item['size'], 'auto')",
	);
	foreach ($sites as $path => $needle) {
		$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $path);
		pg_assert_contains($needle, $source, $path . ' writes sizes with the shared rule');
	}

	$upload = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/explorer/handle_upload.php');
	pg_assert_true(strpos($upload, "convert_bytes_to_string(filesize(\$file_path))") === false, 'the uploaded file label is not in whole units');
	$backups = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/explorer/handle_backups.php');
	pg_assert_true(strpos($backups, "convert_bytes_to_string(\$size)") === false, 'backup rows are not in whole units');
}
