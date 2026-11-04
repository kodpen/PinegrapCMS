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
require_once(PG_FUNCTIONS_DIR . '/includes/fn/list_table.php');

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
	// A range the user picked: its good parts stay, a bad part falls back.
	$_SESSION['software']['ecommerce']['view_commissions'] = array(
		'range_picked' => true,
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

	pg_assert_same('commissions.last_modified_timestamp desc, commissions.id desc', pg_list_table_order_sql(pg_commission_list_source(array('role' => 0), array())));
	pg_assert_same('recurring_commission_profiles.last_modified_timestamp desc, recurring_commission_profiles.id desc', pg_list_table_order_sql(pg_commission_profile_list_source(array('role' => 0), array())));

	$_SESSION['software']['list_state']['view_commissions'] = array('sort' => 'amount', 'order' => 'asc');
	pg_assert_same('commissions.amount asc, commissions.id desc', pg_list_table_order_sql(pg_commission_list_source(array('role' => 0), array())));
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

// A commission or profile of a cancelled order carries the badge, whatever
// its status; any other order state, or no order, shows none.
function test_commission_list_cancelled_order_badge()
{
	pg_assert_same('', pg_commission_list_cancelled_badge('complete'));
	pg_assert_same('', pg_commission_list_cancelled_badge(null), 'no order');
	pg_assert_contains(lang('Cancelled order'), pg_commission_list_cancelled_badge('cancelled'));

	$plain = pg_list_row_html(pg_commission_list_row(commission_list_test_commission(array('order_status' => 'complete'))));
	pg_assert_false(strpos($plain, lang('Cancelled order')) !== false, 'a complete order has no badge');

	foreach (array('pending', 'payable', 'ineligible', 'paid') as $status) {
		$html = pg_list_row_html(pg_commission_list_row(commission_list_test_commission(array('status' => $status, 'order_status' => 'cancelled'))));
		pg_assert_contains(lang('Cancelled order'), $html, $status . ' of a cancelled order is marked');
	}

	$profile = array(
		'id' => '3', 'affiliate_code' => 'A1', 'enabled' => '0', 'amount' => '500', 'start_date' => '2026-01-02',
		'period' => 'Weekly', 'number_of_commissions' => '0', 'product_name' => 'Box', 'product_short_description' => '',
		'created_timestamp' => (string) time(), 'last_modified_timestamp' => (string) time(), 'affiliate_name' => 'B',
		'order_number' => '9', 'order_status' => 'cancelled', 'created_username' => '', 'last_modified_username' => 'ali',
	);
	pg_assert_contains(lang('Cancelled order'), pg_list_row_html(pg_commission_profile_list_row($profile)), 'the profile of a cancelled order is marked');
}

function test_commission_posted_status()
{
	// Only the four stored statuses are saved; anything else would be
	// written as '' into the enum column under the site's non-strict
	// sql_mode and drop the commission out of every total.
	foreach (array('pending', 'payable', 'ineligible', 'paid') as $status) {
		pg_assert_same($status, pg_commission_posted_status($status), $status . ' is kept');
	}
	pg_assert_same('', pg_commission_posted_status('approved'), 'an unknown status is refused');
	pg_assert_same('', pg_commission_posted_status(''), 'an empty status is refused');
	pg_assert_same('', pg_commission_posted_status('Paid'), 'the stored value is lower case');
	pg_assert_same('', pg_commission_posted_status(array('paid')), 'an array is refused');
	pg_assert_same('', pg_commission_posted_status(null), 'a missing status is refused');
}

// The edit screens name the record they change: affiliate, amount, order.
function test_commission_edit_summary()
{
	$html = pg_commission_edit_summary(array(
		'affiliate_name' => 'Ann <B>', 'affiliate_code' => 'A&1', 'reference_code' => 'R1', 'amount' => '179940',
		'order_id' => '12', 'order_number' => '1192', 'order_status' => 'cancelled',
	));
	pg_assert_contains('Ann &lt;B&gt;', $html, 'the affiliate name, escaped');
	pg_assert_contains('A&amp;1', $html, 'the affiliate code, escaped');
	pg_assert_contains('R1', $html, 'the reference code');
	pg_assert_contains(pg_format_money(1799.40, BASE_CURRENCY_SYMBOL), $html, 'the amount');
	pg_assert_contains('view_order.php?id=12', $html, 'the order links to its screen');
	pg_assert_contains('>1192</a>', $html, 'the order number');
	pg_assert_contains(lang('Cancelled order'), $html, 'a cancelled order is marked');

	$plain = pg_commission_edit_summary(array('affiliate_name' => '', 'affiliate_code' => 'A1', 'amount' => '0', 'order_id' => '0', 'order_number' => ''));
	pg_assert_false(strpos($plain, 'view_order.php') !== false, 'no order, no link');
	pg_assert_false(strpos($plain, lang('Reference Code')) !== false, 'a profile has no reference code row');

	$profile = pg_commission_edit_summary(array(
		'affiliate_name' => 'B', 'affiliate_code' => 'A1', 'amount' => '500', 'order_id' => '3', 'order_number' => '9',
		'product_name' => 'Box', 'product_short_description' => 'Small', 'start_date' => '2026-01-02', 'period' => 'Weekly', 'number_of_commissions' => '0',
	));
	pg_assert_contains('Box - Small', $profile, 'the product');
	pg_assert_contains(lang('Weekly'), $profile, 'the frequency');
	pg_assert_contains(lang('Unlimited'), $profile, 'the number of commissions');
	pg_assert_contains(lang('Start Date'), $profile, 'the start date row');
}

function test_commission_edit_screens_print_the_summary()
{
	foreach (array('edit_commission.php', 'edit_recurring_commission_profile.php') as $file) {
		$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
		pg_assert_contains('pg_commission_edit_layout(', $source, $file . ' names the record');
		pg_assert_contains('orders.status AS order_status', $source, $file . ' reads the order status');
	}
}

// The commission and profile edit screens use the wide layout: the Status
// card in the form column, the Summary in the sticky status column.
function test_commission_edit_layout()
{
	$html = pg_commission_edit_layout('<div id="cards"></div>', array('affiliate_name' => 'Ann', 'affiliate_code' => 'A1', 'amount' => '100', 'order_id' => '0', 'order_number' => ''), '<button id="b"></button>');

	pg_assert_contains('col-12 lg:col-8 2xl:col-9 order-1">' . '<div id="cards"></div>', $html, 'the cards in the form column');
	pg_assert_contains('col-12 lg:col-4 2xl:col-3 order-2', $html, 'the status column');
	pg_assert_contains('position-sticky" style="top:3.3rem;"', $html, 'its sticky box below the top bar');
	pg_assert_true(strpos($html, 'order-2') < strpos($html, lang('Summary')), 'the Summary sits in the status column');
	pg_assert_true(strpos($html, 'order-3') < strpos($html, '<button id="b">'), 'the buttons in the save bar');
	pg_assert_same(4, substr_count($html, '<dt class="fw-normal">'), 'name, code, amount and order rows');

	foreach (array('edit_commission.php', 'edit_recurring_commission_profile.php') as $file) {
		$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
		pg_assert_contains('pg_commission_edit_layout(', $source, $file . ': the layout');
		pg_assert_contains("'name'=>'id', 'value'=>\$id", $source, $file . ': the checked id');
	}
}
