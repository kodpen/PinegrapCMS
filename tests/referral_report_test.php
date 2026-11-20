<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Referrals report (includes/fn/referral_report.php): the
 * twelve months it reads, the month table and its totals from the referral
 * rows, the top referrers by their signed code, the summary figures and the
 * chart, and what referral.rewarded carries (includes/fn/referral.php).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/api/outbound/webhooks.php');

function referral_report_test_key()
{
	return 'test-key-0123456789abcdef';
}

function referral_report_test_row($code, $status, $amount, $value, $time)
{
	return array('affiliate_code' => $code, 'status' => $status, 'amount' => $amount, 'value' => $value, 'created_timestamp' => $time);
}

function test_referral_report_month_keys()
{
	$now = mktime(12, 0, 0, 2, 10, 2026);

	pg_assert_same(array('2025-12', '2026-01', '2026-02'), pg_referral_report_month_keys($now, 3), 'oldest first, across the year');
	pg_assert_same(12, count(pg_referral_report_month_keys($now)));
	pg_assert_same(mktime(0, 0, 0, 12, 1, 2025), pg_referral_report_start($now, 3), 'the first moment of the first month');
	pg_assert_same(mktime(0, 0, 0, 3, 1, 2025), pg_referral_report_start($now), 'twelve months by default');
}

function test_referral_report_months()
{
	$keys = array('2026-01', '2026-02');
	$rows = array(
		referral_report_test_row('RA-1', 'paid', 1000, 20000, mktime(9, 0, 0, 1, 5, 2026)),
		referral_report_test_row('RA-1', 'ineligible', 1000, 15000, mktime(9, 0, 0, 1, 20, 2026)),
		referral_report_test_row('RB-2', 'paid', 500, 8000, mktime(9, 0, 0, 2, 1, 2026)),
		referral_report_test_row('RB-2', 'paid', 500, 8000, mktime(9, 0, 0, 12, 1, 2025)),
	);

	$months = pg_referral_report_months($rows, $keys);

	pg_assert_same($keys, array_keys($months), 'every month, a row outside them dropped');
	pg_assert_same(array('orders' => 2, 'kept' => 1, 'taken' => 1, 'credit' => 1000, 'value' => 20000), $months['2026-01'], 'a taken-back reward counts its order but neither its credit nor its value');
	pg_assert_same(array('orders' => 1, 'kept' => 1, 'taken' => 0, 'credit' => 500, 'value' => 8000), $months['2026-02']);

	$empty = pg_referral_report_months(array(), $keys);
	pg_assert_same(array('orders' => 0, 'kept' => 0, 'taken' => 0, 'credit' => 0, 'value' => 0), $empty['2026-02'], 'an empty month is zeros');

	pg_assert_same(array('orders' => 3, 'kept' => 2, 'taken' => 1, 'credit' => 1500, 'value' => 28000), pg_referral_report_totals($months));
	pg_assert_same(array('orders' => 0, 'kept' => 0, 'taken' => 0, 'credit' => 0, 'value' => 0), pg_referral_report_totals(array()));
}

function test_referral_report_top()
{
	$key = referral_report_test_key();
	$a = pg_referral_code_make(41, $key);
	$b = pg_referral_code_make(52, $key);
	$c = pg_referral_code_make(63, $key);
	$time = mktime(9, 0, 0, 1, 5, 2026);

	$rows = array(
		referral_report_test_row($a, 'paid', 1000, 20000, $time),
		referral_report_test_row($b, 'paid', 1000, 9000, $time),
		referral_report_test_row($b, 'paid', 1000, 9000, $time),
		referral_report_test_row($b, 'ineligible', 1000, 9000, $time),
		referral_report_test_row($c, 'ineligible', 1000, 9000, $time),
		referral_report_test_row('RZZ-0000000000', 'paid', 1000, 9000, $time),
	);

	$top = pg_referral_report_top($rows, $key, 10);

	pg_assert_same(array(52, 41), array_column($top, 'contact_id'), 'most rewards kept first; a code whose signature fails and a referrer with none kept are left out');
	pg_assert_same(array('rank' => 1, 'contact_id' => 52, 'orders' => 3, 'kept' => 2, 'credit' => 2000, 'value' => 18000), $top[0]);
	pg_assert_same(1, count(pg_referral_report_top($rows, $key, 1)), 'the limit holds');
	pg_assert_same(array(), pg_referral_report_top($rows, '', 10), 'no site key, no referrer');

	// Equal rewards: the larger credit, then the lower contact id.
	$tie = array(
		referral_report_test_row($c, 'paid', 700, 1, $time),
		referral_report_test_row($b, 'paid', 700, 1, $time),
		referral_report_test_row($a, 'paid', 500, 1, $time),
	);
	pg_assert_same(array(52, 63, 41), array_column(pg_referral_report_top($tie, $key, 10), 'contact_id'));
}

function test_referral_report_figures_and_chart()
{
	$totals = array('orders' => 4, 'kept' => 3, 'taken' => 1, 'credit' => 1500, 'value' => 28000);
	$figures = pg_referral_report_figures($totals, '$');

	pg_assert_same(4, count($figures));
	pg_assert_same('4', $figures[0]['text'], 'referred orders');
	pg_assert_same('3', $figures[1]['text'], 'rewards kept');
	pg_assert_true($figures[1]['note'] !== '', 'the taken-back count under it');
	pg_assert_same(pg_report_view_money(1500, '$'), $figures[2]['text']);
	pg_assert_same(pg_report_view_money(28000, '$'), $figures[3]['text']);
	pg_assert_same('', pg_referral_report_figures(array('orders' => 0, 'kept' => 0, 'taken' => 0, 'credit' => 0, 'value' => 0), '$')[1]['note'], 'nothing taken back, no note');

	$months = array(
		'2026-01' => array('orders' => 2, 'kept' => 1, 'taken' => 1, 'credit' => 1000, 'value' => 20000),
		'2026-02' => array('orders' => 1, 'kept' => 1, 'taken' => 0, 'credit' => 500, 'value' => 8000),
	);
	$chart = pg_referral_report_chart($months, '$');

	pg_assert_same('line', $chart['kind']);
	pg_assert_same(array(200.0, 80.0), $chart['values'], 'the orders\' value in major units');
	pg_assert_same(array(10.0, 5.0), $chart['compare'], 'the credit kept beside it');
	pg_assert_same(2, count($chart['names']));
	pg_assert_same(null, pg_referral_report_chart(array('2026-01' => $months['2026-01']), '$'), 'one month draws no line');
	pg_assert_same(null, pg_referral_report_chart(pg_referral_report_months(array(), array('2026-01', '2026-02')), '$'), 'no referral, no chart');
}

function test_referral_rewarded_event()
{
	$event = pg_referral_rewarded_event(array(
		'id' => 12, 'reference_code' => 'C7Q2X9', 'affiliate_code' => 'R15-abcdefghij', 'order_id' => 1042, 'amount' => 2500,
		'contact_id' => 41, 'user_id' => 7, 'card_id' => 90,
	));

	pg_assert_same(array('id', 'reference_code', 'affiliate_code', 'order_id', 'amount', 'customer_id', 'account_id', 'card_id'), array_keys($event));
	pg_assert_same(12, $event['id']);
	pg_assert_same(1042, $event['order_id']);
	pg_assert_same(2500, $event['amount']);
	pg_assert_same(41, $event['customer_id'], 'the referrer\'s contact');
	pg_assert_same(7, $event['account_id']);
	pg_assert_same(90, $event['card_id']);
}

// The event is announced once, after the lock is released, and only for a
// reward given at completion: a restore's new card is credit.issued's.
function test_referral_rewarded_announced_after_the_lock()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/referral.php');
	$release = strpos($source, "RELEASE_LOCK('\" . e(\$lock)");
	$announce = strpos($source, "pg_announce('referral.rewarded'");

	pg_assert_true($release !== false && $announce !== false && $announce > $release, 'announced after the lock');
	pg_assert_same(1, substr_count($source, "pg_announce('referral.rewarded'"), 'one place announces it');
	pg_assert_true(isset(api_webhook_core_events()['referral.rewarded']), 'in the catalogue');
	pg_assert_true(in_array('referral.rewarded', pg_event_workspace_events(), true), 'a flow can start on it');
}
