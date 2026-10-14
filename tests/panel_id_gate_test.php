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
