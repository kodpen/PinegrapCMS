<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Catalog Manager's Commerce access save
 * (includes/explorer/handle_catalog_edit.php): the Offline Payment right is
 * written only while offline payment is switched on, as Edit User does, so
 * a save while it is off keeps each user's stored right.
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

// Offline payment on: all three rights are written from the row.
function test_catalog_access_columns_with_offline_payment_on()
{
	pg_assert_same(
		array('user_manage_ecommerce' => 'yes', 'manage_ecommerce_reports' => '1', 'user_set_offline_payment' => '1'),
		pg_explorer_catalog_access_columns(array('manage' => true, 'reports' => true, 'offline_payment' => true), true),
		'all granted');

	pg_assert_same(
		array('user_manage_ecommerce' => 'no', 'manage_ecommerce_reports' => '0', 'user_set_offline_payment' => '0'),
		pg_explorer_catalog_access_columns(array('manage' => false, 'reports' => false, 'offline_payment' => false), true),
		'all taken away');

	pg_assert_same(
		array('user_manage_ecommerce' => 'no', 'manage_ecommerce_reports' => '0', 'user_set_offline_payment' => '0'),
		pg_explorer_catalog_access_columns(array(), true),
		'missing keys are off');
}

// Offline payment off: the drawer drew no switch, the browser still sends
// false, and the stored right is left alone.
function test_catalog_access_columns_with_offline_payment_off()
{
	$columns = pg_explorer_catalog_access_columns(array('manage' => true, 'reports' => false, 'offline_payment' => false), false);

	pg_assert_same(array('user_manage_ecommerce' => 'yes', 'manage_ecommerce_reports' => '0'), $columns, 'manage and reports only');
	pg_assert_false(array_key_exists('user_set_offline_payment', $columns), 'offline payment right not written');
}

// The save builds its UPDATE from the helper, with the same switch the read
// handler uses to draw the Offline Payment column.
function test_catalog_access_set_uses_the_columns_helper()
{
	$handlers = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/explorer/handle_catalog_edit.php');

	pg_assert_contains('pg_explorer_catalog_access_columns($item, pg_explorer_catalog_offline_payment_on())', $handlers, 'save uses the helper');
	pg_assert_contains("'offline_payment' => pg_explorer_catalog_offline_payment_on(),", $handlers, 'read uses the same switch');
	pg_assert_false(strpos($handlers, "user_set_offline_payment = '\" . e(\$offline) . \"'") !== false, 'no unconditional write left');
}
