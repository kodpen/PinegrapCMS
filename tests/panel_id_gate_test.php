<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the record id gate of the panel's edit, view and print screens.
 * (int) turns an array into 1, so a screen that reads ?id[]=x with
 * (int) ($_REQUEST['id'] ?? 0) opens record 1, or passes its "Record not
 * found." gate and fails further down on the array. The gate reads the id
 * only when it is one value: is_scalar(...) ? (int) ... : 0.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// No panel screen casts the request id without checking it is one value.
function test_panel_id_gate_no_bare_int_cast()
{
	$found = array();

	foreach (glob(PG_FUNCTIONS_DIR . '/*.php') as $file) {
		if (preg_match('/\(int\)\s*\(\s*\$_REQUEST\[\'id\'\]\s*\?\?\s*0\s*\)/', file_get_contents($file))) {
			$found[] = basename($file);
		}
	}

	pg_assert_same(array(), $found, 'screens casting $_REQUEST[\'id\'] without is_scalar()');
}

// The screens #417 left with the bare cast now carry the scalar gate.
function test_panel_id_gate_scalar_on_listed_screens()
{
	$screens = array('edit_calendar', 'edit_zone', 'view_visitor', 'print_order', 'edit_erp_invoice', 'get_erp_invoice_pdf', 'approve_affiliate');

	foreach ($screens as $screen) {
		pg_assert_contains("is_scalar(\$_REQUEST['id'] ?? null) ? (int) \$_REQUEST['id'] : 0", file_get_contents(PG_FUNCTIONS_DIR . '/' . $screen . '.php'), $screen);
	}
}

// The edit and view screens that had no gate at all now stop on "Record not
// found." before the id reaches a query, a liveform index or the page.
function test_panel_id_gate_on_formerly_ungated_screens()
{
	$screens = array(
		'duplicate_folder', 'duplicate_product_group', 'edit_ad_region', 'edit_arrival_date', 'edit_auto_dialog',
		'edit_calendar_event_location', 'edit_comment', 'edit_commission', 'edit_common_region', 'edit_contact_group',
		'edit_custom_style', 'edit_design_file', 'edit_designer_region', 'edit_dynamic_region',
		'edit_email_campaign_profile', 'edit_field', 'edit_file', 'edit_javascript', 'edit_login_region', 'edit_menu',
		'edit_menu_item', 'edit_recurring_commission_profile', 'edit_ship_date_adjustment', 'edit_submitted_form',
		'edit_system_style', 'edit_theme_css', 'edit_theme_file', 'edit_verified_shipping_address', 'editor_edit_file',
		'view_email_campaign', 'view_menu_items', 'view_order', 'view_system_style_source',
	);

	foreach ($screens as $screen) {
		$source = file_get_contents(PG_FUNCTIONS_DIR . '/' . $screen . '.php');
		pg_assert_contains("is_scalar(\$_REQUEST['id'] ?? null) ? (int) \$_REQUEST['id'] : 0", $source, $screen);
		pg_assert_contains("output_error(lang('Record not found.')", $source, $screen);
	}

	// edit_page.php checks the id its handler reads, never a cookie of the same name.
	pg_assert_contains('is_scalar($requested_page_id) ? (int) $requested_page_id : 0', file_get_contents(PG_FUNCTIONS_DIR . '/edit_page.php'), 'edit_page');
}

// Outside the ERP screens (tests/erp_request_values_test.php), no root screen
// casts $_GET['id'] before an is_scalar() / is_string() check of the id: the
// check sits on the same line or earlier in the file (the screen's gate).
function test_panel_id_gate_get_id_cast_after_gate()
{
	$found = array();

	foreach (glob(PG_FUNCTIONS_DIR . '/*.php') as $file) {
		if (strpos(basename($file), 'erp') !== false) {
			continue;
		}

		$source = file_get_contents($file);

		if (!preg_match_all('/\(int\)\s*\(?\s*\$_GET\[\'id\'\]/', $source, $casts, PREG_OFFSET_CAPTURE)) {
			continue;
		}

		$gate = preg_match('/is_(?:scalar|string)\(\s*\$_(?:GET|REQUEST)\[\'id\'\]/', $source, $match, PREG_OFFSET_CAPTURE) ? $match[0][1] : PHP_INT_MAX;

		foreach ($casts[0] as $cast) {
			if ($cast[1] < $gate) {
				$found[] = basename($file) . ':' . (substr_count(substr($source, 0, $cast[1]), "\n") + 1);
			}
		}
	}

	pg_assert_same(array(), $found, 'screens casting $_GET[\'id\'] before checking it is one value');
}

// The screens that read ?id with a bare cast and no gate: an array id was
// read as 1 and opened, copied, signed in as or deleted record 1.
function test_panel_id_gate_on_get_id_screens()
{
	// The record screens stop on "Record not found." (edit_offer still
	// creates without an id).
	foreach (array('duplicate_offer', 'duplicate_style', 'edit_offer') as $screen) {
		$source = file_get_contents(PG_FUNCTIONS_DIR . '/' . $screen . '.php');
		pg_assert_contains("if (isset(\$_GET['id']) && !is_scalar(\$_GET['id'])) {\n    output_error(lang('Record not found.')", $source, $screen);
	}

	// The others read an array as no id and give their "not found" answer.
	$screens = array(
		'login_as_user'                 => '$login_as_user_id = ',
		'get_seo_analysis'              => '$id = ',
		'get_view_order_screen_content' => '$order_id = ',
		'order_history_delete_order'    => '$order_id = ',
		'order_history_reorder'         => '$old_order_id = ',
		'order_history_retrieve_order'  => '$order_id = ',
	);

	foreach ($screens as $screen => $variable) {
		pg_assert_contains($variable . "is_scalar(\$_GET['id'] ?? null) ? (int) \$_GET['id'] : 0;", file_get_contents(PG_FUNCTIONS_DIR . '/' . $screen . '.php'), $screen);
	}

	// edit_offer's ?duplicate= source and the iCalendar export of a calendar event page.
	pg_assert_contains("is_scalar(\$_GET['duplicate'] ?? null) ? (int) \$_GET['duplicate'] : 0", file_get_contents(PG_FUNCTIONS_DIR . '/edit_offer.php'), 'edit_offer duplicate');
	pg_assert_contains("is_scalar(\$_GET['id'] ?? null) ? get_calendar_event(\$_GET['id'], 0) : false", file_get_contents(PG_FUNCTIONS_DIR . '/get_page.php'), 'get_page icalendar');
}

// The token-checked actions that copy, optimize, remove or send one record
// passed ?id[]=x to escape(), which fails on an array with an error page, and
// most copied an empty row when the id was missing or unknown. Each now stops
// on "Record not found." before anything reads the id.
function test_panel_id_gate_on_token_action_screens()
{
	$screens = array(
		'duplicate_calendar_event', 'duplicate_common_region', 'duplicate_designer_region',
		'duplicate_email_campaign_profile', 'duplicate_menu', 'duplicate_page', 'duplicate_product', 'optimize',
		'remove_recipient', 'send_email_campaign',
	);

	foreach ($screens as $screen) {
		$source = file_get_contents(PG_FUNCTIONS_DIR . '/' . $screen . '.php');
		$gate = strpos($source, "\$id = is_scalar(\$_GET['id'] ?? null) ? (int) \$_GET['id'] : 0;\nif ((\$id < 1) || ((int) db_value(\"SELECT COUNT(*) FROM ");

		pg_assert_true($gate !== false, $screen . ': no record gate');
		pg_assert_contains("output_error(lang('Record not found.')", $source, $screen);

		if ($gate !== false) {
			pg_assert_same($gate + strlen('$id = is_scalar('), strpos($source, "\$_GET['id']"), $screen . ': id read before the gate');
			pg_assert_true(strpos($source, 'validate_token_field();') < $gate, $screen . ': token checked after the gate');
		}
	}
}

// The file editors that show a file as text and write over it on Save (CSS,
// JavaScript, the other text formats) and the theme file screen that renames,
// deletes and exports it open only a design file under a plain name with the
// screen's own extension. Their gate reads the stored name before both the
// GET and the POST branch, so neither opening nor saving reaches an image, a
// font or a name leading out of the files folder.
function test_panel_id_gate_file_editors_check_name_and_type()
{
	$screens = array(
		'edit_theme_css'   => array('$file_name', "!== 'css')"),
		'edit_javascript'  => array('$file_name', "!== 'js')"),
		'editor_edit_file' => array('$file_name', "array('js', 'json', 'txt', 'svg', 'xml'), true)"),
		'edit_theme_file'  => array('$theme_file_name', "!== 'css')"),
	);

	foreach ($screens as $screen => $expected) {
		list($variable, $extension) = $expected;

		$source = file_get_contents(PG_FUNCTIONS_DIR . '/' . $screen . '.php');
		$gate = strpos($source, "WHERE (id = '\" . \$id . \"') AND (design = '1')");
		$not_found = strpos($source, "output_error(lang('Record not found.')");
		$get_branch = strpos($source, 'if (!$_POST) {');
		$post_branch = strpos($source, 'validate_token_field();');

		pg_assert_true($gate !== false, $screen . ' reads the record as a design file');
		pg_assert_contains("|| ({$variable} !== basename({$variable}))", $source, $screen . ' plain name');
		pg_assert_contains("|| (strpos({$variable}, '..') !== false)", $source, $screen . ' no ..');
		pg_assert_contains("pathinfo({$variable}, PATHINFO_EXTENSION)", $source, $screen . ' extension');
		pg_assert_contains($extension, $source, $screen . ' extension list');
		pg_assert_true(($not_found !== false) && ($gate < $not_found), $screen . ' stops on "Record not found."');
		pg_assert_true(($get_branch !== false) && ($not_found < $get_branch), $screen . ' gate runs before the GET branch');
		pg_assert_true(($post_branch !== false) && ($not_found < $post_branch), $screen . ' gate runs before the POST branch');
	}

	// The three text editors write the posted code over the stored name only:
	// the posted name names neither the file nor its copy, and code sent as
	// an array or not at all counts as not given (nothing written).
	foreach (array('edit_theme_css', 'edit_javascript', 'editor_edit_file') as $screen) {
		$source = file_get_contents(PG_FUNCTIONS_DIR . '/' . $screen . '.php');

		pg_assert_true(strpos($source, "\$_POST['name']") === false, $screen . ' does not read the posted name');
		pg_assert_contains("\$new_file_name = prepare_file_name(\$file_name);", $source, $screen . ' copy named after the stored file');
		pg_assert_contains("pg_request_single_values(array('code'));\n\n    if (!isset(\$_POST['code'])) {", $source, $screen . ' code is one value');
		pg_assert_true(strpos($source, "pg_request_single_values(array('code'))") < strpos($source, 'fopen('), $screen . ' code checked before a file is written');
	}

	// The CSS copy opens only on one of the two screens that link to the editor.
	pg_assert_contains("!in_array(\$_POST['from'] ?? '', array('edit_theme_file', 'edit_design_file'), true)", file_get_contents(PG_FUNCTIONS_DIR . '/edit_theme_css.php'), 'edit_theme_css from');

	// A file recorded here but missing from the disk opens empty with a note,
	// and Save deletes the old file only when it is there: no PHP warning.
	foreach (array('edit_theme_css', 'edit_javascript', 'editor_edit_file') as $screen) {
		$source = file_get_contents(PG_FUNCTIONS_DIR . '/' . $screen . '.php');

		pg_assert_contains("if (is_file(FILE_DIRECTORY_PATH . '/' . \$file_name)) {\n        \$code = file_get_contents(", $source, $screen . ' reads only a file on the disk');
		pg_assert_contains("lang('The file is recorded here but is not on the disk.')", $source, $screen . ' missing file note');
		pg_assert_contains("if (is_file(FILE_DIRECTORY_PATH . '/' . \$file_name)) {\n            unlink(", $source, $screen . ' unlinks only a file on the disk');
	}

	// The design file screen renames and deletes: only a design file under a
	// plain name opens, and the POST acts on the record the gate checked.
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/edit_design_file.php');
	$gate = strpos($source, "WHERE (id = '\" . \$id . \"') AND (design = '1')");
	$not_found = strpos($source, "output_error(lang('Record not found.')");

	pg_assert_true($gate !== false, 'edit_design_file reads the record as a design file');
	pg_assert_contains("|| (\$stored_name !== basename(\$stored_name))", $source, 'edit_design_file plain name');
	pg_assert_contains("|| (strpos(\$stored_name, '..') !== false)", $source, 'edit_design_file no ..');
	pg_assert_true(($not_found !== false) && ($gate < $not_found), 'edit_design_file stops on "Record not found."');
	pg_assert_true($not_found < strpos($source, 'validate_token_field();'), 'edit_design_file gate runs before the POST branch');
	pg_assert_contains("validate_token_field();\n\n    // Delete and rename act on the record and the stored name the gate\n    // above checked.\n    \$_POST['id'] = \$id;", $source, 'edit_design_file POST uses the gated id');
	pg_assert_contains("if ((\$name !== \$stored_name) && is_file(FILE_DIRECTORY_PATH . '/' . \$stored_name)) {", $source, 'edit_design_file renames only a file on the disk');
}
