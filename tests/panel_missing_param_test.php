<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for panel screens opened without the parameter they need (a stale
 * link, a hand-typed URL). They wrote PHP notices for every detail of the
 * record that was never found, then showed a half empty screen. A screen that
 * works on a record now stops on "Record not found." before it reads the
 * record's details; one that only answers a question reads the missing value
 * as empty and gives the answer it always gave.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Screen => the first read of the record's details, which has to come after
// the "not found" stop.
function panel_missing_param_gated_screens()
{
	return array(
		'add_field'                 => '$pg_breadcrumb_items = ',
		'add_menu_item'             => '$menu_name = $row[\'name\'];',
		'generate_layout'           => 'generate_layout_content($_GET[\'page_id\'])',
		'preview_form'              => '$product_name = $row[\'name\'];',
		'get_theme_designer_module' => '$_SESSION[\'software\'][\'theme_designer\'][$file_id]',
	);
}

function test_panel_missing_param_record_gate_before_details()
{
	foreach (panel_missing_param_gated_screens() as $screen => $first_read) {
		$source = file_get_contents(PG_FUNCTIONS_DIR . '/' . $screen . '.php');
		$gate = strpos($source, "output_error(lang('Record not found.')");
		$read = strpos($source, $first_read);

		pg_assert_true($gate !== false, $screen . ': no "Record not found." stop');
		pg_assert_true($read !== false, $screen . ': first read of the details not found');
		pg_assert_true(($gate !== false) && ($read !== false) && ($gate < $read), $screen . ': details read before the stop');
	}
}

// The image editor checks that the file exists before it reads the file's row.
function test_panel_missing_param_image_editor_checks_file_first()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/image_editor_edit.php');
	$check = strpos($source, 'if (mysqli_num_rows($result) == 0)');
	$read = strpos($source, '$file_name_extension = $row[\'type\'];');

	pg_assert_true(($check !== false) && ($read !== false) && ($check < $read), 'image_editor_edit: file row read before the existence check');
}

// Screens that answer without a record read the missing value as empty.
function test_panel_missing_param_empty_reads()
{
	$reads = array(
		'browse_catalog_menu'  => array("get_page_name(\$_GET['current_page_id'] ?? '')", "(\$_GET['previous_url_id'] ?? '')"),
		'check_if_file_exists' => array("prepare_file_name(\$_GET['file_name'] ?? '')"),
		'get_region_content'   => array("switch (\$_GET['region_type'] ?? '')"),
		'print_packing_slip'   => array("(string) (\$_GET['ship_to_id'] ?? '')"),
		'add_email_campaign'   => array("\$page_name = \$row['page_name'] ?? '';", "\$folder_id = \$row['page_folder'] ?? '';"),
	);

	foreach ($reads as $screen => $needles) {
		$source = file_get_contents(PG_FUNCTIONS_DIR . '/' . $screen . '.php');

		foreach ($needles as $needle) {
			pg_assert_contains($needle, $source, $screen);
		}
	}
}
