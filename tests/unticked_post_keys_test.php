<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for save handlers that read an unticked checkbox or an empty multiple
 * select straight from $_POST. A browser posts nothing for either, so Create
 * User (Notify User and the permission gates), Create and Edit Shipping Zone
 * (Allowed Countries and Allowed States) and the Paraşüt orders export
 * (Product taxes included) warned in ordinary use; a zone without states
 * also handed foreach() a null.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Create User reads Notify User and the permission gates with a default.
function test_unticked_add_user_checkboxes()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/add_user.php');

	pg_assert_same(0, substr_count($source, "\$_POST['notify_user'] =="), 'Notify User read without a default');
	pg_assert_same(2, substr_count($source, "(\$_POST['notify_user'] ?? '') == 1"), 'Notify User read with a default');
	foreach (array('manage_calendars', 'manage_forms', 'manage_visitors', 'manage_contacts', 'manage_emails', 'manage_ecommerce', 'manage_ecommerce_reports') as $key) {
		pg_assert_same(0, preg_match("/\\\$_POST\['" . $key . "'\](?! \?\?)/", $source), $key . ' read without a default');
	}
}

// Both zone screens read the country and state lists as arrays.
function test_unticked_zone_lists()
{
	foreach (array('add_zone', 'edit_zone') as $screen) {
		$source = file_get_contents(PG_FUNCTIONS_DIR . '/' . $screen . '.php');

		foreach (array('allowed_countries', 'allowed_states') as $key) {
			pg_assert_same(0, substr_count($source, "= \$_POST['" . $key . "'];"), $screen . ' reads ' . $key . ' without a check');
			pg_assert_same(1, substr_count($source, "(isset(\$_POST['" . $key . "']) && is_array(\$_POST['" . $key . "'])) ? \$_POST['" . $key . "'] : array();"), $screen . ' reads ' . $key . ' as an array');
		}
	}
}

// The Paraşüt export reads Product taxes included with a default.
function test_unticked_parasut_tax_included()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/edit_orders.php');

	pg_assert_same(0, substr_count($source, "= \$_POST['tax_rate_included_for_parasut'];"), 'taxes included read without a default');
	pg_assert_same(1, substr_count($source, "= \$_POST['tax_rate_included_for_parasut'] ?? '';"), 'taxes included read with a default');
}
