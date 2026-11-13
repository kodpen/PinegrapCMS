<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The File Manager's upload limit and its notices. The window states and
 * checks a limit no larger than a browser can send as a data URL inside one
 * JSON request (pg_explorer_upload_max_bytes()), and a file FileReader still
 * reads as nothing is named and not sent, on both the folder and the backup
 * upload paths (#8989). A notice naming a long file wraps the name inside
 * its box, close button included (#8979).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/view_folder_and_files_f.php');

// 2^29 - 24 characters less 1 MB, as base64: 401,866,734 bytes, written
// "383.2 MB"; the server's own smaller ceiling wins.
function test_explorer_upload_max_bytes()
{
	$browser = pg_explorer_upload_max_bytes(0);

	pg_assert_same(401866734, $browser, 'the browser ceiling');
	pg_assert_true((int) ceil($browser / 3) * 4 + 1048576 <= 536870888, 'its data URL fits a Chrome string');
	pg_assert_same($browser, pg_explorer_upload_max_bytes(1181116006), 'a 1.1 GB server is capped');
	pg_assert_same(62914560, pg_explorer_upload_max_bytes(62914560), 'a 60 MB server keeps its own');
	pg_assert_same($browser, pg_explorer_upload_max_bytes(-5), 'no figure is the browser ceiling');
	pg_assert_same('383.2 MB', convert_bytes_to_string($browser, 'floor'), 'the figure the window prints');
}

// Every listing that hands the window its limit goes through the cap.
function test_explorer_upload_limit_is_capped_everywhere()
{
	$list = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/explorer/handle_list.php');
	$backups = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/explorer/handle_backups.php');

	pg_assert_same(2, substr_count($list, "\$upload_max_bytes = pg_explorer_upload_max_bytes(\$upload_limits['json_max']);"), 'the folder and the flat listings');
	pg_assert_false(strpos($list, "'upload_max_bytes' => \$upload_limits['json_max']") !== false, 'no raw figure in a listing');
	pg_assert_contains("\$upload_max_bytes = pg_explorer_upload_max_bytes(\$upload_limits['json_max']);", $backups, 'the backup listing');
	pg_assert_contains("'upload_max_label' => convert_bytes_to_string(\$upload_max_bytes, 'floor')),", $backups);
}

// An empty read is a named failure that sends nothing, on both paths.
function test_explorer_upload_empty_read_is_not_sent()
{
	$screen = (string) file_get_contents(PG_FUNCTIONS_DIR . '/view_folders.php');

	pg_assert_contains("function uploadReadUsable(result) {\n        return (typeof result === 'string') && (result.indexOf('base64,') !== -1);\n    }", $screen);
	pg_assert_contains("if (!uploadReadUsable(reader.result)) {\n                    failed++;\n                    toast(entry.file.name + ': ' + L.upload_read_failed, false);\n                    next();\n                    return;\n                }\n\n                api({\n                    type: 'explorer_backup_upload',", $screen, 'backups');
	pg_assert_contains("if (!uploadReadUsable(reader.result)) {\n                        toast(file.name + ': ' + L.upload_read_failed, false);\n                        next();\n                        return;\n                    }\n\n                    api({\n                        type: 'explorer_upload',", $screen, 'folders');
	pg_assert_contains("reader.onerror = function () { failed++; toast(entry.file.name + ': ' + L.upload_read_failed, false); next(); };", $screen, 'a backup read error is said');
	pg_assert_contains("reader.onerror = function () { toast(file.name + ': ' + L.upload_read_failed, false); next(); };", $screen, 'a folder read error names the read');
	pg_assert_contains("'upload_read_failed' => lang('The browser could not read the file, so it was not sent.'),", $screen);
}

// A long name without a break wraps inside the notice: the row fills the
// toast and the text breaks inside a word (#8979).
function test_explorer_toast_wraps_a_long_name()
{
	$screen = (string) file_get_contents(PG_FUNCTIONS_DIR . '/view_folders.php');
	$start = strpos($screen, "\n    function toast(");
	$toast = substr($screen, $start, strpos($screen, "\n    function ", $start + 1) - $start);

	pg_assert_contains('<div class="d-flex w-100"><div class="toast-body flex-grow-1 min-w-0 text-break"></div>', $toast);
	pg_assert_contains('flex-shrink-0" data-bs-dismiss="toast"', $toast, 'the close button keeps its size');

	$bootstrap = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/lib/bootstrap-6/css/bootstrap.css');

	foreach (array('.min-w-0 {', '.text-break {', '.w-100 {') as $utility) {
		pg_assert_contains($utility, $bootstrap, $utility . ' exists in Bootstrap 6');
	}
}
