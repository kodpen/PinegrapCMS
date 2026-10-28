<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the commission lists (includes/fn/commission_list.php): the
 * date range kept in the session, the sort, the search columns (every word
 * searched over the whole list, not one page of it), amounts and labels in
 * the search box, and the rows of both lists.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/commission_list.php');

foreach (array('PATH' => '/', 'OUTPUT_PATH' => '/', 'OUTPUT_SOFTWARE_DIRECTORY' => 'pinegrap', 'BASE_CURRENCY_SYMBOL' => '$', 'DATE_FORMAT' => 'day_month', 'TIME_FORMAT' => 'twenty_four_hours') as $commission_list_test_name => $commission_list_test_value) {
	if (!defined($commission_list_test_name)) {
		define($commission_list_test_name, $commission_list_test_value);
	}
}

function commission_list_test_commission($values = array())
{
	return array_merge(array(
		'id' => '7', 'affiliate_code' => 'AFF<1>', 'reference_code' => 'R&1', 'status' => 'payable', 'amount' => '1250',
		'created_timestamp' => (string) time(), 'last_modified_timestamp' => (string) time(), 'affiliate_name' => 'Ayşe',
		'order_number' => '1001', 'period' => null, 'product_name' => null, 'product_short_description' => null,
		'created_username' => 'ali', 'last_modified_username' => null,
	), $values);
}

function test_commission_list_range_defaults_and_bad_parts()
{
	$_SESSION['software']['ecommerce']['view_commissions'] = array(
		'start_month' => '2', 'start_day' => '3', 'start_year' => '2025',
		'stop_month' => 'x', 'stop_day' => '', 'stop_year' => '2026',
	);

	$range = pg_commission_list_range();
	pg_assert_same(mktime(0, 0, 0, 2, 3, 2025), $range['start']);
	pg_assert_same(date('m'), $_SESSION['software']['ecommerce']['view_commissions']['stop_month'], 'a bad part falls back to today');
	pg_assert_same(mktime(23, 59, 59, (int) date('m'), (int) date('d'), 2026), $range['stop']);
}

function test_commission_list_sort_ends_with_the_id()
{
	unset($_SESSION['software']['list_state']['view_commissions'], $_SESSION['software']['list_state']['view_recurring_commission_profiles']);

	pg_assert_same('commissions.last_modified_timestamp desc, commissions.id desc', pg_commission_list_sort()['order_by']);
	pg_assert_same('recurring_commission_profiles.last_modified_timestamp desc, recurring_commission_profiles.id desc', pg_commission_profile_list_sort()['order_by']);

	$_SESSION['software']['list_state']['view_commissions'] = array('sort' => 'amount', 'order' => 'asc');
	pg_assert_same('commissions.amount asc, commissions.id desc', pg_commission_list_sort()['order_by']);
}

function test_commission_list_search()
{
	$columns = pg_commission_list_search_columns();

	$where = pg_list_search_where(array('ayşe'), $columns['columns'], $columns['labels']);
	pg_assert_contains('contacts.affiliate_name LIKE ?', $where['sql']);
	pg_assert_contains('commissions.reference_code LIKE ?', $where['sql']);
	pg_assert_false(strpos($where['sql'], 'commissions.amount') !== false, 'a word is not an amount');
	pg_assert_same(substr_count($where['sql'], '?'), count($where['params']));

	$where = pg_list_search_where(array('12,50'), $columns['columns'], $columns['labels']);
	pg_assert_contains('commissions.amount LIKE ?', $where['sql']);
	pg_assert_true(in_array('%1250%', $where['params'], true));

	$where = pg_list_search_where(array(lang('Payable')), $columns['columns'], $columns['labels']);
	pg_assert_contains('commissions.status IN (?)', $where['sql'], 'the status as shown');
	pg_assert_true(in_array('payable', $where['params'], true));

	$where = pg_list_search_where(array(lang('One-Time')), $columns['columns'], $columns['labels']);
	pg_assert_contains("IFNULL(recurring_commission_profiles.period, '') IN (?)", $where['sql'], 'a one-time commission has no profile');
	pg_assert_true(in_array('', $where['params'], true));

	$columns = pg_commission_profile_list_search_columns();
	$where = pg_list_search_where(array('x'), $columns['columns'], $columns['labels']);
	pg_assert_contains('recurring_commission_profiles.affiliate_code LIKE ?', $where['sql']);
}

function test_commission_list_row()
{
	$html = pg_list_row_html(pg_commission_list_row(commission_list_test_commission()));

	pg_assert_contains('edit_commission.php?id=7', $html);
	pg_assert_contains('AFF&lt;1&gt;', $html);
	pg_assert_contains('R&amp;1', $html, 'the reference code is escaped');
	pg_assert_contains('bg-primary fg-white fw-light', $html);
	pg_assert_contains(lang('One-Time'), $html);
	pg_assert_same(11, count(pg_commission_list_row(commission_list_test_commission())['cells']), 'one cell per heading');

	$html = pg_list_row_html(pg_commission_list_row(commission_list_test_commission(array('period' => 'Monthly', 'product_name' => 'Box', 'product_short_description' => 'Small'))));
	pg_assert_contains(lang('Monthly'), $html);
	pg_assert_contains('Box - Small', $html);
}

function test_commission_profile_list_row()
{
	$profile = array(
		'id' => '3', 'affiliate_code' => 'A1', 'enabled' => '1', 'amount' => '500', 'start_date' => '2026-01-02',
		'period' => 'Weekly', 'number_of_commissions' => '0', 'product_name' => 'Box', 'product_short_description' => '',
		'created_timestamp' => (string) time(), 'last_modified_timestamp' => (string) time(), 'affiliate_name' => 'B',
		'order_number' => '9', 'created_username' => '', 'last_modified_username' => 'ali',
	);
	$row = pg_commission_profile_list_row($profile);
	$html = pg_list_row_html($row);

	pg_assert_same(12, count($row['cells']), 'one cell per heading');
	pg_assert_contains('edit_recurring_commission_profile.php?id=3', $html);
	pg_assert_contains(lang('Unlimited'), $html);
	pg_assert_contains('task_alt', $html);
	pg_assert_false(strpos($html, 'Box - ') !== false, 'no dash without a short description');
}
