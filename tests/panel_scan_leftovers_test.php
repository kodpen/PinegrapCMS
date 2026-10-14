<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the last warnings of the panel scan. The workspace template form
 * and the counter sale read their posted fields with a bare (string) or (int)
 * cast: an array wrote "Array" with a notice, or opened record 1. Both now
 * drop every posted field that is not one value before they read it. The
 * packing slip printed five "Undefined variable" notices for a recipient
 * without the optional address lines, and the catalog menu redirect to a
 * deleted product group read a column of a row that was never found.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Every posted field the screen reads is in its pg_request_single_values()
// call, and the call comes before the first of those reads. The token is
// checked with is_string() by validate_token_field() and the screen itself.
function test_panel_scan_leftovers_posted_fields_are_single_values()
{
	foreach (array('workspace_template', 'add_order') as $screen) {
		$source = file_get_contents(PG_FUNCTIONS_DIR . '/' . $screen . '.php');

		$call = strpos($source, 'pg_request_single_values(array(');
		pg_assert_true($call !== false, $screen . ': no pg_request_single_values() call');

		if ($call === false) {
			continue;
		}

		$end = strpos($source, '));', $call);
		preg_match_all("/'([a-z_]+)'/", substr($source, $call, $end - $call), $listed);
		$listed = $listed[1];

		preg_match_all("/\\\$_POST\\['([a-z_]+)'\\]/", $source, $reads, PREG_OFFSET_CAPTURE);

		pg_assert_true(count($reads[1]) > 0, $screen . ': no posted field read');

		foreach ($reads[1] as $read) {
			list($name, $offset) = $read;

			if ($name === 'token') {
				continue;
			}

			pg_assert_true(in_array($name, $listed, true), $screen . ': ' . $name . ' is read but not in pg_request_single_values()');
			pg_assert_true($offset > $call, $screen . ': ' . $name . ' is read before pg_request_single_values()');
		}
	}
}

// The counter's quick account passes the whole $_POST on: the fields
// erp_account_quick_create() reads are covered by the counter's call too.
function test_panel_scan_leftovers_counter_quick_account_fields()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/add_order.php');
	$call = strpos($source, 'pg_request_single_values(array(');
	$list = substr($source, $call, strpos($source, '));', $call) - $call);

	foreach (array('title', 'is_person', 'tax_number', 'email', 'phone') as $name) {
		pg_assert_contains("'" . $name . "'", $list, 'add_order: quick account field ' . $name);
	}
}

// The packing slip's optional lines start empty before any branch fills them.
function test_panel_scan_leftovers_packing_slip_defaults()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/print_packing_slip.php');

	foreach (array('$output_organization_address_2', '$output_organization_country', '$output_ship_to_salutation', '$output_ship_to_company', '$output_ship_to_address_2', '$output_ship_to_phone_number') as $variable) {
		$default = strpos($source, $variable . " = '';");

		pg_assert_true($default !== false, 'print_packing_slip: no default for ' . $variable);
		pg_assert_same(strpos($source, $variable), $default, 'print_packing_slip: ' . $variable . ' used before its default');
	}
}

// A deleted product or product group gives an empty address name, without
// reading a column of the missing row.
function test_panel_scan_leftovers_catalog_address_name_of_missing_item()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/content.php');
	$start = strpos($source, 'function get_catalog_item_address_name_from_id(');
	$body = substr($source, $start, strpos($source, "\nfunction ", $start + 1) - $start);

	pg_assert_contains("\$address_name = \$row ? \$row['address_name'] : '';", $body, 'get_catalog_item_address_name_from_id');
}
