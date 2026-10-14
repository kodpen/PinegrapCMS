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
