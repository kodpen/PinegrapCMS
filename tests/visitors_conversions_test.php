<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the conversions section of the Visitors overview
 * (includes/visitors/conversions.php): which tiles commerce adds, the
 * conversion rate, the funnel, the chart buckets and the empty check.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_VISITORS_SCREEN')) {
	define('PG_VISITORS_SCREEN', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/visitors/shell.php');
require_once(PG_FUNCTIONS_DIR . '/includes/visitors/conversions.php');

function visitors_conversions_test_totals($overrides = array())
{
	return array_merge(array(
		'visits'             => 200,
		'page_views'         => 900,
		'forms_submitted'    => 6,
		'orders_created'     => 20,
		'orders_retrieved'   => 0,
		'orders_checked_out' => 10,
		'orders_completed'   => 5,
		'order_total'        => 123456,
	), $overrides);
}

function test_visitors_conversions_tiles_are_forms_alone_without_commerce()
{
	$tiles = pg_visitors_conversions_tiles(visitors_conversions_test_totals(), null, false);

	pg_assert_same(array('forms_submitted'), array_column($tiles, 'key'));
	pg_assert_same(6, $tiles[0]['value']);
	pg_assert_same(null, $tiles[0]['before'], 'no earlier figure when not compared');
}

function test_visitors_conversions_tiles_with_commerce_and_comparison()
{
	$before = visitors_conversions_test_totals(array('visits' => 100, 'orders_completed' => 1, 'forms_submitted' => 3));
	$tiles = pg_visitors_conversions_tiles(visitors_conversions_test_totals(), $before, true);

	pg_assert_same(
		array('forms_submitted', 'orders_created', 'orders_checked_out', 'orders_completed', 'order_total', 'rate'),
		array_column($tiles, 'key')
	);
	pg_assert_same(3, $tiles[0]['before']);
	pg_assert_same('money', $tiles[4]['format']);
	pg_assert_same(2.5, $tiles[5]['value'], '5 completed over 200 visits');
	pg_assert_same(1.0, $tiles[5]['before']);
	pg_assert_same('percent', $tiles[5]['format']);
}

function test_visitors_conversions_rate_without_visits_is_null()
{
	pg_assert_same(null, pg_visitors_conversions_rate(array('visits' => 0, 'orders_completed' => 3)));
	pg_assert_same(null, pg_visitors_conversions_rate(null));
	pg_assert_same(33.33, pg_visitors_conversions_rate(array('visits' => 3, 'orders_completed' => 1)));
	pg_assert_same('–', pg_visitors_conversions_format(null, 'percent'));
	pg_assert_same(pg_format_number(1234, 0), pg_visitors_conversions_format(1234, 'count'));
}

function test_visitors_conversions_funnel_shares_of_visits()
{
	$rows = pg_visitors_conversions_funnel(visitors_conversions_test_totals());

	pg_assert_same(4, count($rows));
	pg_assert_same(200, $rows[0]['value']);
	pg_assert_same(pg_format_number(200, 0), $rows[0]['text'], 'the visits row carries no share');
	pg_assert_same(pg_format_number(10, 0) . ' · ' . pg_visitors_percent(5, 2), $rows[2]['text']);
	pg_assert_same(array(), pg_visitors_conversions_funnel(visitors_conversions_test_totals(array('visits' => 0))));
}

function test_visitors_conversions_has_figures()
{
	$zero = visitors_conversions_test_totals(array('forms_submitted' => 0, 'orders_created' => 0, 'orders_checked_out' => 0, 'orders_completed' => 0, 'order_total' => 0));

	pg_assert_false(pg_visitors_conversions_has_figures($zero, true), 'visits alone are not conversions');
	pg_assert_true(pg_visitors_conversions_has_figures(array_merge($zero, array('orders_created' => 1)), true));
	pg_assert_false(pg_visitors_conversions_has_figures(array_merge($zero, array('orders_created' => 1)), false), 'orders do not count with commerce off');
	pg_assert_true(pg_visitors_conversions_has_figures(array_merge($zero, array('forms_submitted' => 1)), false));
}

function test_visitors_conversions_buckets_follow_the_grain_cut_to_the_period()
{
	$now = strtotime('2026-11-04 15:00:00');

	pg_assert_same(array(), pg_visitors_conversions_buckets(pg_visitor_period(array('p' => 'today'), $now)), 'one day has no chart');

	$week = pg_visitors_conversions_buckets(pg_visitor_period(array('p' => '7d'), $now));
	pg_assert_same(7, count($week));
	pg_assert_same(array('bucket' => '2026-10-29', 'from' => '2026-10-29', 'to' => '2026-10-29'), $week[0]);

	$weeks = pg_visitors_conversions_buckets(pg_visitor_period(array('p' => 'custom', 'from' => '2026-07-01', 'to' => '2026-09-30'), $now));
	pg_assert_same(array('bucket' => '2026-06-29', 'from' => '2026-07-01', 'to' => '2026-07-05'), $weeks[0], 'the first week is cut to the period');
	pg_assert_same('2026-09-30', $weeks[count($weeks) - 1]['to']);
}
