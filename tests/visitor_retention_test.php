<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the pure parts of the visitor retention period in
 * includes/fn/visitor_retention.php: the period as typed into the settings,
 * the boundary it gives, the remainder row of the daily summary, and the SQL
 * the device and channel are counted with.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Empty and 0 keep every row; a whole number within the bounds is a period.
function test_visitor_retention_days_from_input_accepts_periods()
{
	pg_assert_same(0, pg_visitor_retention_days_from_input(''));
	pg_assert_same(0, pg_visitor_retention_days_from_input('0'));
	pg_assert_same(0, pg_visitor_retention_days_from_input(' 0 '));
	pg_assert_same(90, pg_visitor_retention_days_from_input('90'));
	pg_assert_same(90, pg_visitor_retention_days_from_input(' 90 '));
	pg_assert_same(PG_VISITOR_RETENTION_MIN_DAYS, pg_visitor_retention_days_from_input((string) PG_VISITOR_RETENTION_MIN_DAYS));
	pg_assert_same(PG_VISITOR_RETENTION_MAX_DAYS, pg_visitor_retention_days_from_input((string) PG_VISITOR_RETENTION_MAX_DAYS));
}

// Anything else is refused rather than read as some other number.
function test_visitor_retention_days_from_input_refuses_the_rest()
{
	pg_assert_same(null, pg_visitor_retention_days_from_input((string) (PG_VISITOR_RETENTION_MIN_DAYS - 1)), 'below the minimum');
	pg_assert_same(null, pg_visitor_retention_days_from_input((string) (PG_VISITOR_RETENTION_MAX_DAYS + 1)), 'above the maximum');
	pg_assert_same(null, pg_visitor_retention_days_from_input('-30'), 'negative');
	pg_assert_same(null, pg_visitor_retention_days_from_input('30.5'), 'fraction');
	pg_assert_same(null, pg_visitor_retention_days_from_input('30 days'), 'words');
	pg_assert_same(null, pg_visitor_retention_days_from_input('1e3'), 'exponent');
	pg_assert_same(null, pg_visitor_retention_days_from_input('00'), 'zero spelled twice');
}

// No period, no boundary.
function test_visitor_retention_cutoff_without_a_period()
{
	pg_assert_same(0, pg_visitor_retention_cutoff(0, time()));
	pg_assert_same(0, pg_visitor_retention_cutoff(-5, time()));
}

// The boundary is midnight of the day N days back, by the site's clock, also
// across a daylight saving change: 86400-second steps would land an hour off.
function test_visitor_retention_cutoff_is_midnight_across_dst()
{
	$zone = date_default_timezone_get();
	date_default_timezone_set('Europe/Berlin');

	try {
		// Summer time began on 29 March 2026.
		$now = mktime(15, 30, 0, 4, 2, 2026);

		$cutoff = pg_visitor_retention_cutoff(7, $now);

		pg_assert_same(mktime(0, 0, 0, 3, 26, 2026), $cutoff, '7 days before 2 April');
		pg_assert_same('2026-03-26 00:00', date('Y-m-d H:i', $cutoff));

		$cutoff = pg_visitor_retention_cutoff(90, mktime(0, 0, 1, 11, 1, 2026));

		pg_assert_same('2026-08-03 00:00', date('Y-m-d H:i', $cutoff), '90 days before 1 November');
	} finally {
		date_default_timezone_set($zone);
	}
}

// What the top values do not cover becomes one remainder row, figure by
// figure.
function test_visitor_retention_summary_rest_row()
{
	$total = array('visits' => 10, 'page_views' => 25, 'forms_submitted' => 1, 'orders_created' => 2, 'orders_retrieved' => 0, 'orders_checked_out' => 1, 'orders_completed' => 1, 'order_total' => 5000);

	$rows = array(
		array('value' => 'google.com', 'visits' => '6', 'page_views' => '15', 'forms_submitted' => '1', 'orders_created' => '1', 'orders_retrieved' => '0', 'orders_checked_out' => '1', 'orders_completed' => '1', 'order_total' => '5000'),
		array('value' => '', 'visits' => '3', 'page_views' => '6', 'forms_submitted' => '0', 'orders_created' => '0', 'orders_retrieved' => '0', 'orders_checked_out' => '0', 'orders_completed' => '0', 'order_total' => '0'),
	);

	$lines = pg_visitor_summary_with_rest($rows, $total);

	pg_assert_same(3, count($lines), 'two values and the rest');
	pg_assert_same('google.com', $lines[0]['value']);
	pg_assert_same(0, $lines[0]['rest']);
	pg_assert_same(6, $lines[0]['visits']);
	pg_assert_same('', $lines[1]['value'], 'an empty value is a value, not the rest');
	pg_assert_same(0, $lines[1]['rest']);
	pg_assert_same(1, $lines[2]['rest']);
	pg_assert_same(1, $lines[2]['visits']);
	pg_assert_same(4, $lines[2]['page_views']);
	pg_assert_same(1, $lines[2]['orders_created']);
	pg_assert_same(0, $lines[2]['order_total']);
}

// When the top values cover every visit there is no remainder row.
function test_visitor_retention_summary_no_rest_when_covered()
{
	$total = array('visits' => 4, 'page_views' => 4, 'forms_submitted' => 0, 'orders_created' => 0, 'orders_retrieved' => 0, 'orders_checked_out' => 0, 'orders_completed' => 0, 'order_total' => 0);

	$lines = pg_visitor_summary_with_rest(array(array('value' => 'TR', 'visits' => 4, 'page_views' => 4)), $total);

	pg_assert_same(1, count($lines));
	pg_assert_same(0, $lines[0]['rest']);
	pg_assert_same(0, $lines[0]['order_total'], 'a figure the query left out counts as 0');
}

// Tablets are told apart before phones; no user agent is no device.
function test_visitor_retention_device_sql()
{
	$sql = pg_visitor_device_sql('visitors.user_agent');

	pg_assert_contains("WHEN (visitors.user_agent = '') THEN ''", $sql);
	pg_assert_true(strpos($sql, "'tablet'") < strpos($sql, "'mobile'"), 'tablet is tested first');
	pg_assert_contains("ELSE 'desktop' END", $sql);
}

// With no pay-per-click flag set nothing is paid: an empty pattern would match
// every visit.
function test_visitor_retention_channel_sql_without_flag()
{
	if (defined('PAY_PER_CLICK_FLAG') && (trim((string) PAY_PER_CLICK_FLAG) !== '')) {
		pg_assert_contains("THEN 'pay_per_click'", pg_visitor_channel_sql());
		return;
	}

	$sql = pg_visitor_channel_sql('v.');

	pg_assert_false(strpos($sql, 'pay_per_click') !== false, 'no paid branch without a flag');
	pg_assert_contains("WHEN (v.referring_search_engine <> '') THEN 'organic'", $sql);
}
