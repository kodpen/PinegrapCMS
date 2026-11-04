<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the commission lists on the list table driver
 * (includes/fn/commission_list.php): their columns, the gate of their
 * sources, the editor link that leads each row and their place among the
 * driver's lists.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_INIT_LOADED')) {
	define('PG_INIT_LOADED', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/commission_list.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/list_table.php');

foreach (array('PATH' => '/', 'OUTPUT_PATH' => '/', 'OUTPUT_SOFTWARE_DIRECTORY' => 'pinegrap', 'BASE_CURRENCY_SYMBOL' => '$', 'DATE_FORMAT' => 'day_month', 'TIME_FORMAT' => 'twenty_four_hours') as $commission_driver_test_name => $commission_driver_test_value) {
	if (!defined($commission_driver_test_name)) {
		define($commission_driver_test_name, $commission_driver_test_value);
	}
}

function test_commission_list_driver_columns()
{
	$columns = pg_list_table_columns(pg_commission_list_columns());

	pg_assert_same(array('affiliate_name', 'affiliate_code', 'reference_code', 'status', 'amount', 'frequency', 'order_number', 'product', 'created', 'last_modified', 'actions'), array_keys($columns));
	pg_assert_same(false, $columns['affiliate_name']['hideable'], 'the affiliate name with the editor link is never hidden');
	pg_assert_same('commissions.amount', $columns['amount']['sort']);
	pg_assert_same('end', $columns['amount']['align']);
	pg_assert_contains('FIELD(commissions.status', $columns['status']['sort'], 'the status sorts by its label');
	pg_assert_false($columns['actions']['sortable']);
}

function test_commission_profile_list_driver_columns()
{
	$columns = pg_list_table_columns(pg_commission_profile_list_columns());

	pg_assert_same(array('affiliate_name', 'affiliate_code', 'enabled', 'amount', 'start_date', 'frequency', 'commissions', 'order_number', 'product', 'created', 'last_modified', 'actions'), array_keys($columns));
	pg_assert_same('recurring_commission_profiles.start_date', $columns['start_date']['sort']);
	pg_assert_same('center', $columns['enabled']['align']);
}

function test_commission_list_sources_gate()
{
	pg_assert_same(null, pg_commission_list_source(array('role' => 3, 'manage_ecommerce' => 0), array()), 'a user without commerce rights is refused');
	pg_assert_same(null, pg_commission_profile_list_source(array('role' => 3, 'manage_ecommerce' => 0), array()));
	pg_assert_same(null, pg_commission_list_source(array(), array()), 'no role, no list');

	$source = pg_commission_profile_list_source(array('role' => 3, 'manage_ecommerce' => 1), array());
	pg_assert_same('view_recurring_commission_profiles', $source['list']);
	pg_assert_same('recurring_commission_profiles.id', $source['id']);

	$_SESSION['software']['ecommerce']['view_commissions'] = array(
		'range_picked' => true,
		'start_month' => '2', 'start_day' => '3', 'start_year' => '2025',
		'stop_month' => '4', 'stop_day' => '5', 'stop_year' => '2025',
	);
	$source = pg_commission_list_source(array('role' => 2), array());
	pg_assert_same('view_commissions', $source['list']);
	pg_assert_same(array((string) mktime(0, 0, 0, 2, 3, 2025), (string) mktime(23, 59, 59, 4, 5, 2025)), $source['params'], 'the range the screen kept bounds the list');
}

function test_commission_list_driver_rows_lead_with_the_editor_link()
{
	$row = pg_commission_list_row(array(
		'id' => '7', 'affiliate_code' => 'A1', 'reference_code' => 'R1', 'status' => 'paid', 'amount' => '1250',
		'created_timestamp' => (string) time(), 'last_modified_timestamp' => (string) time(), 'affiliate_name' => 'Ayşe <b>',
		'order_number' => '1001', 'period' => null, 'product_name' => null, 'product_short_description' => null,
		'created_username' => 'ali', 'last_modified_username' => null,
	));

	pg_assert_same(7, $row['id']);
	pg_assert_contains('data-pg-open href="edit_commission.php?id=7"', $row['cells']['affiliate_name']['html']);
	pg_assert_contains('Ayşe &lt;b&gt;', $row['cells']['affiliate_name']['html']);
	pg_assert_contains('href="edit_commission.php?id=7"', $row['cells']['actions']['html']);
}

function test_commission_lists_are_driver_lists()
{
	pg_assert_same('pg_commission_list_source', pg_list_table_definition('view_commissions')['source']);
	pg_assert_same('pg_commission_profile_list_source', pg_list_table_definition('view_recurring_commission_profiles')['source']);
}
