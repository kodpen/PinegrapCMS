<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for customer rhythm as it is read
 * (includes/fn/customer_rhythm_report.php): who came back after a reminder
 * and how the pass counts it, the reminders table, the stages table, the
 * products with their rhythm, the list's names and CSV, the settings form,
 * who may read and change, and the contact card's figures.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function _crr_test_now()
{
	return 1790000000;
}

// Orders as the walk gives them back: day offsets from now, amounts.
function _crr_test_orders($orders)
{
	$text = '';

	foreach ($orders as $order) {
		$text .= pack('VVVVC', _crr_test_now() + (int) round($order[1] * 86400), $order[2], $order[0], 0, empty($order[3]) ? 0 : 1);
	}

	return pg_customer_rhythm_pass_orders($text);
}

// An order within 14 days of the reminder counts; the first one only.
function test_customer_rhythm_report_came_back_within_14_days()
{
	$reminded = _crr_test_now() - (10 * 86400);
	$orders = _crr_test_orders(array(array(1, -40, 5000), array(2, -3, 7000), array(3, -1, 9000)));
	$came = pg_customer_rhythm_came_back($orders, $reminded, 0);

	pg_assert_same(2, $came['id'], 'the first order after the reminder');
	pg_assert_same(7000, $came['amount']);
	pg_assert_same(null, pg_customer_rhythm_came_back($orders, $reminded, 2), 'counted once');
	pg_assert_same(null, pg_customer_rhythm_came_back($orders, 0, 0), 'no reminder, no return');
}

function test_customer_rhythm_report_came_back_window_edges()
{
	$reminded = _crr_test_now() - (20 * 86400);

	pg_assert_same(null, pg_customer_rhythm_came_back(_crr_test_orders(array(array(1, -5, 5000))), $reminded, 0), '15 days after is too late');
	pg_assert_same(4, pg_customer_rhythm_came_back(_crr_test_orders(array(array(4, -6, 5000))), $reminded, 0)['id'], 'the 14th day still counts');
	pg_assert_same(null, pg_customer_rhythm_came_back(_crr_test_orders(array(array(5, -20, 5000))), $reminded, 0), 'an order at the reminder itself is not after it');
	pg_assert_same(null, pg_customer_rhythm_came_back(_crr_test_orders(array(array(6, -18, 5000, true))), $reminded, 0), 'a marketplace order does not count');
}

// The pass hands the return over with the row it worked out.
function test_customer_rhythm_report_pass_row_carries_the_return()
{
	$orders = _crr_test_orders(array(array(1, -60, 5000), array(2, -2, 8000)));
	$store = array('median' => 30.0, 'curve' => array('fixed' => true, 'bands' => array()), 'products' => array());
	$stored = array(
		'customer_key' => 'eabc', 'contact_id' => 0, 'stage' => 2, 'stage_before' => 1, 'stage_at' => _crr_test_now() - (20 * 86400),
		'interval_days' => 30, 'last_order_at' => _crr_test_now() - (60 * 86400), 'reminded_at' => _crr_test_now() - (5 * 86400),
		'held_back' => 1, 'came_back_order_id' => 0, 'updated_at' => _crr_test_now() - 86400,
	);
	$made = pg_customer_rhythm_pass_row('eabc', $orders, array(), $stored, $store, array(), _crr_test_now());

	pg_assert_same(2, $made['came_back']['id']);
	pg_assert_false(array_key_exists('came_back_order_id', $made['row']), 'the mark is written by its own statement');

	$stored['came_back_order_id'] = 2;
	pg_assert_same(null, pg_customer_rhythm_pass_row('eabc', $orders, array(), $stored, $store, array(), _crr_test_now())['came_back']);
	pg_assert_same(null, pg_customer_rhythm_pass_row('eabc', $orders, array(), null, $store, array(), _crr_test_now())['came_back'], 'a new row was never reminded');
}

function test_customer_rhythm_report_came_back_query()
{
	pg_assert_same(array('', array()), pg_customer_rhythm_came_back_query(array()));

	list($sql, $params) = pg_customer_rhythm_came_back_query(array('eabc' => array('id' => 7, 'amount' => 1), 'c12' => array('id' => 9, 'amount' => 1)));

	pg_assert_contains('UPDATE customer_rhythm SET came_back_order_id = CASE customer_key WHEN ? THEN ? WHEN ? THEN ?', $sql);
	pg_assert_contains('WHERE (came_back_order_id = 0) AND customer_key IN (?, ?)', $sql);
	pg_assert_same(array('eabc', 7, 'c12', 9, 'eabc', 'c12'), $params);
}

// Returns are counted in the reminder's month, told and held back apart.
function test_customer_rhythm_report_return_counts_and_merge()
{
	$months = array();
	pg_customer_rhythm_return_count($months, gmmktime(12, 0, 0, 3, 31, 2026), false, 7000);
	pg_customer_rhythm_return_count($months, gmmktime(12, 0, 0, 3, 2, 2026), true, 4000);
	pg_customer_rhythm_return_count($months, gmmktime(12, 0, 0, 3, 5, 2026), false, 1000);

	pg_assert_same(array('2026-03' => array(2, 8000, 1, 4000)), $months);

	$old = array();

	for ($m = 1; $m <= 24; $m++) {
		$old[sprintf('%04d-%02d', 2024 + intdiv($m - 1, 12), (($m - 1) % 12) + 1)] = array(1, 100, 0, 0);
	}

	$merged = pg_customer_rhythm_returns_merge($old, array('2025-12' => array(1, 50, 2, 10), 'bad' => array(9), '2026-01' => 'x', '2026-02' => array(1, 1, 1, 1)));

	pg_assert_same(24, count($merged), 'the last 24 months');
	pg_assert_same(array(2, 150, 2, 10), $merged['2025-12']);
	pg_assert_same(array(1, 1, 1, 1), $merged['2026-02']);
	pg_assert_false(isset($merged['2024-01']));
	pg_assert_false(isset($merged['bad']));
}

function test_customer_rhythm_report_summary_keeps_returns()
{
	$store = array('median' => 30.0, 'curve' => array('fixed' => true, 'bands' => array()), 'products' => array());
	$summary = json_decode(pg_customer_rhythm_summary_encode($store, _crr_test_now(), array('customers' => 1, 'moved' => 0, 'stages' => array(), 'reminders' => array('2026-03' => array(10, 1)), 'returns' => array('2026-03' => array(3, 21000, 0, 0)))), true);

	pg_assert_same(array(3, 21000, 0, 0), $summary['returns']['2026-03']);
	pg_assert_false(isset(json_decode(pg_customer_rhythm_summary_encode($store, _crr_test_now(), array('customers' => 1, 'moved' => 0, 'stages' => array())), true)['returns']));
}

// The reminders table: newest month first, a rate only where something was sent.
function test_customer_rhythm_report_return_rows()
{
	$rows = pg_customer_rhythm_return_rows(array(
		'reminders' => array('2026-01' => array(20, 2), '2026-02' => array(0, 0), '2026-03' => array(8, 0)),
		'returns' => array('2026-01' => array(5, 30000, 1, 4000), '2026-03' => array(2, 9000, 0, 0)),
	));

	pg_assert_same(array('2026-03', '2026-02', '2026-01'), array_column($rows, 'month'));
	pg_assert_same(25, $rows[0]['rate']);
	pg_assert_same(null, $rows[0]['held_rate'], 'none held back that month');
	pg_assert_same(null, $rows[1]['rate']);
	pg_assert_same(25, $rows[2]['rate']);
	pg_assert_same(50, $rows[2]['held_rate']);
	pg_assert_same(30000, $rows[2]['value']);
	pg_assert_same(4000, $rows[2]['held_value']);
	pg_assert_same(array(), pg_customer_rhythm_return_rows(null));
	pg_assert_same(2, count(pg_customer_rhythm_return_rows(array('reminders' => array('2026-01' => array(1, 0), '2026-02' => array(1, 0), '2026-03' => array(1, 0))), 2)));
}

// Every stage in its order; customers with no stage last, only when there are any.
function test_customer_rhythm_report_stage_rows()
{
	$rows = pg_customer_rhythm_stage_rows(array(2 => array(4, 12000), 6 => array(1, 500)));

	pg_assert_same(array('regular', 'due', 'late', 'slipping', 'lost', 'won_back'), array_column($rows, 'stage'));
	pg_assert_same(4, $rows[1]['customers']);
	pg_assert_same(12000, $rows[1]['value']);
	pg_assert_same(0, $rows[0]['customers']);

	$rows = pg_customer_rhythm_stage_rows(array(0 => array(3, 0), 1 => array(2, 100)));
	pg_assert_same('', $rows[6]['stage'], 'no stage comes last');
	pg_assert_same(3, $rows[6]['customers']);
}

function test_customer_rhythm_report_stage_pick_and_labels()
{
	pg_assert_same('due', pg_customer_rhythm_stage_pick('due'));
	pg_assert_same('won_back', pg_customer_rhythm_stage_pick('won_back'));
	pg_assert_same('', pg_customer_rhythm_stage_pick(''));
	pg_assert_same('', pg_customer_rhythm_stage_pick('vip'));
	pg_assert_same('', pg_customer_rhythm_stage_pick(array('due')));

	$names = array_keys(pg_customer_rhythm_stage_codes());
	array_shift($names);
	pg_assert_same($names, array_keys(pg_customer_rhythm_stage_labels()), 'a label for every stage');
	pg_assert_same($names, array_keys(pg_customer_rhythm_stage_bounds()));
}

function test_customer_rhythm_report_product_rows()
{
	$summary = array('products' => array('5' => array(12.5, 10, 5, 0), '9' => array(30.0, 40, 12, 1), '7' => array(8.0, 10, 6, 0)));
	$rows = pg_customer_rhythm_product_rows($summary, array(5 => array('COF-1', 'Coffee beans 1 kg'), 9 => array('TEA-1', '')));

	pg_assert_same(array(9, 5, 7), array_column($rows, 'id'), 'most repeat purchases first, then by id');
	pg_assert_same('TEA-1', $rows[0]['name'], 'the name when there is no short description');
	pg_assert_same('Coffee beans 1 kg', $rows[1]['name']);
	pg_assert_same('', $rows[2]['name']);
	pg_assert_true($rows[0]['from_group']);
	pg_assert_same(12.5, $rows[1]['days_per_unit']);
	pg_assert_same(1, count(pg_customer_rhythm_product_rows($summary, array(), 1)));
	pg_assert_same(array(), pg_customer_rhythm_product_rows(null, array()));
}

// Names: the contact's first, else the last order's billing name.
function test_customer_rhythm_report_name()
{
	pg_assert_same('Ayşe Kaya', pg_customer_rhythm_name(array('contact_first_name' => 'Ayşe', 'contact_last_name' => 'Kaya', 'billing_first_name' => 'A', 'billing_last_name' => 'K')));
	pg_assert_same('Ali Can', pg_customer_rhythm_name(array('contact_first_name' => null, 'contact_last_name' => '', 'billing_first_name' => 'Ali', 'billing_last_name' => 'Can')));
	pg_assert_same('Kaya Ltd', pg_customer_rhythm_name(array('billing_first_name' => '', 'billing_last_name' => '', 'billing_company' => 'Kaya Ltd')));
	pg_assert_same('', pg_customer_rhythm_name(array()));
}

function test_customer_rhythm_report_csv_line()
{
	$record = array(
		'id' => 3, 'contact_id' => 0, 'orders' => 4, 'first_order_at' => mktime(12, 0, 0, 1, 5, 2026), 'last_order_at' => _crr_test_now() - (45 * 86400),
		'last_order_id' => 9, 'interval_days' => 30, 'stage' => 2, 'chance' => 72, 'expected_value' => 123456, 'next_due_at' => 0,
		'contact_first_name' => '=cmd', 'contact_last_name' => '', 'order_number' => '1009',
	);
	$line = pg_customer_rhythm_csv_line($record, _crr_test_now(), 'TRY');
	$cells = str_getcsv($line);

	pg_assert_same(12, count($cells));
	pg_assert_same(count(explode(',', pg_customer_rhythm_csv_head())), count($cells));
	pg_assert_true(substr($cells[0], 0, 1) !== '=', 'the formula guard');
	pg_assert_same('due', $cells[1]);
	pg_assert_same('2026-01-05', $cells[3]);
	pg_assert_same('1009', $cells[5]);
	pg_assert_same('45', $cells[7]);
	pg_assert_same('1234.56', $cells[9]);
	pg_assert_same('', $cells[10], 'no due day');
	pg_assert_same('TRY', $cells[11]);
}

// The settings form: an unchecked switch is off, days held to their bounds.
function test_customer_rhythm_report_settings_form()
{
	$settings = pg_customer_rhythm_settings_from_post(array('rhythm_brake_days' => '200', 'rhythm_lead_days' => '5', 'rhythm_share' => '1'));

	pg_assert_false($settings['on']);
	pg_assert_same(90, $settings['brake_days']);
	pg_assert_same(5, $settings['lead_days']);
	pg_assert_true($settings['share']);

	$settings = pg_customer_rhythm_settings_from_post(array('rhythm_on' => '1', 'rhythm_brake_days' => 'x', 'rhythm_lead_days' => array('3')));
	pg_assert_true($settings['on']);
	pg_assert_same(PG_CUSTOMER_RHYTHM_BRAKE_DAYS, $settings['brake_days'], 'not a number keeps the default');
	pg_assert_same(PG_CUSTOMER_RHYTHM_LEAD_DAYS, $settings['lead_days']);
	pg_assert_false($settings['share']);

	$stored = pg_customer_rhythm_settings_encode(array('on' => false, 'brake_days' => 14, 'lead_days' => 0, 'share' => true));
	pg_assert_same('{"on":0,"brake_days":14,"lead_days":0,"share":1}', $stored);
	pg_assert_same(array('on' => false, 'brake_days' => 14, 'lead_days' => 0, 'share' => true), pg_customer_rhythm_settings_normalize($stored), 'read back as saved');
}

function test_customer_rhythm_report_rights()
{
	pg_assert_true(pg_customer_rhythm_may_read(array('role' => 2)));
	pg_assert_true(pg_customer_rhythm_may_read(array('role' => 3, 'manage_ecommerce_reports' => true)));
	pg_assert_false(pg_customer_rhythm_may_read(array('role' => 3, 'manage_ecommerce' => true)));
	pg_assert_false(pg_customer_rhythm_may_read(null));
	pg_assert_true(pg_customer_rhythm_may_change(array('role' => 2)));
	pg_assert_false(pg_customer_rhythm_may_change(array('role' => 3, 'manage_ecommerce_reports' => true)));
}

function test_customer_rhythm_report_card_view()
{
	$view = pg_customer_rhythm_card_view(array(
		'stage' => 3, 'interval_days' => 21, 'orders' => 1, 'next_due_at' => 1790500000, 'chance' => 55,
		'expected_value' => 80000, 'last_order_at' => _crr_test_now() - (50 * 86400), 'updated_at' => _crr_test_now() - 3600,
	), _crr_test_now());

	pg_assert_same('late', $view['stage']);
	pg_assert_same(21, $view['interval']);
	pg_assert_false($view['own'], 'one order: the store median stands for it');
	pg_assert_same(1790500000, $view['next_due']);
	pg_assert_same(50, $view['days_since']);
	pg_assert_same(80000, $view['expected']);
}

// The list sits on the list table driver under its own name.
function test_customer_rhythm_report_list_is_registered()
{
	$definition = pg_list_table_definition('customer_rhythm');

	pg_assert_same('includes/fn/customer_rhythm_report.php', $definition['file']);
	pg_assert_same('pg_customer_rhythm_list_source', $definition['source']);
	pg_assert_false(isset(pg_customer_rhythm_list_columns(false)['customer']), 'no name column without the contacts right');
	pg_assert_same('customer', array_keys(pg_customer_rhythm_list_columns(true))[0]);

	list($from, $select) = pg_customer_rhythm_list_from(false);
	pg_assert_same('customer_rhythm', $from, 'no name is read without the contacts right');
	pg_assert_false(strpos($select, 'billing_') !== false);
	pg_assert_contains('LEFT JOIN contacts', pg_customer_rhythm_list_from(true)[0]);
}
