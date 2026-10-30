<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source pins for the file sizes in the drop area of Upload File
 * (add_file.php) and Upload Design Files (add_design_file.php): the size
 * under each file and the too-big refusal use the panel's units (1024-based,
 * B and KB whole, MB and up with one decimal) and its decimal mark, the same
 * way as the limit line above the drop area, and Dropzone's own
 * {{filesize}} / {{maxFilesize}} figures never reach the screen.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function upload_drop_area_sizes_source($file)
{
	return (string) file_get_contents(dirname(__DIR__) . '/pinegrap/' . $file);
}

function test_upload_drop_area_sizes_in_panel_units()
{
	foreach (array('add_file.php', 'add_design_file.php') as $file) {
		$src = upload_drop_area_sizes_source($file);

		pg_assert_contains('filesizeBase: 1024,', $src, $file . ': Dropzone sizes still base 1000');

		$start = strpos($src, 'init: function()');
		pg_assert_true($start !== false, $file . ': no init()');
		$init = substr($src, (int) $start, 2000);
		pg_assert_contains('myDropzone.filesize = function (size)', $init, $file . ': tile size left to Dropzone filesize()');
		pg_assert_contains('encode_json(pg_number_separators())', $init, $file . ': tile size without the panel separators');
		pg_assert_contains('" KB"', $init, $file . ': no KB unit');
		pg_assert_contains('" B"', $init, $file . ': bytes not written as B');
		pg_assert_contains('unit = " MB"; decimals = 1;', $init, $file . ': MB not one decimal');
	}
}

function test_upload_drop_area_too_big_names_the_server_limit()
{
	foreach (array('add_file.php', 'add_design_file.php') as $file) {
		$src = upload_drop_area_sizes_source($file);

		pg_assert_false(strpos($src, '{{filesize}}') !== false, $file . ': Dropzone {{filesize}} still shown');
		pg_assert_false(strpos($src, '{{maxFilesize}}') !== false, $file . ': Dropzone {{maxFilesize}} still shown');

		$start = strpos($src, 'dictFileTooBig: ');
		pg_assert_true($start !== false, $file . ': no dictFileTooBig');
		$option = substr($src, (int) $start, 300);
		pg_assert_contains("'string' => 'The file can be at most {var:1}.'", $option, $file . ': refusal does not name the limit');
		pg_assert_contains("convert_bytes_to_string((\$upload_limits['file_max'] > 0) ? \$upload_limits['file_max'] : \$dropzone_file_max_mb * 1048576, 1)", $option, $file . ': limit not the server-formatted figure');
		pg_assert_contains('encode_json(', $option, $file . ': refusal not written as a JSON string');
	}
}
