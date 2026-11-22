<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the pure parts of Edit Product's Performance card
 * (includes/fn/product_insights.php): the periods and their chart buckets,
 * the bounds of the grouped sales read and its fold, the change line, the
 * shares, conversion, whether the content rollup holds a period, the margin
 * on the sums, the assembled figures and the ten-minute keep.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_ERP_ENTRY')) {
	define('PG_ERP_ENTRY', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/erp/order_profit.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/product_insights.php');

// Runs a check in a fixed time zone, so day and month starts are known.
function product_insights_test_in_zone($zone, $check)
{
	$was = date_default_timezone_get();
	date_default_timezone_set($zone);

	try {
		$check();
	} finally {
		date_default_timezone_set($was);
	}
}

function test_product_insights_period_in()
{
	pg_assert_same('30d', pg_product_insights_period_in('30d'));
	pg_assert_same('12m', pg_product_insights_period_in('12m'));
	pg_assert_same('all', pg_product_insights_period_in('all'));
	pg_assert_same('30d', pg_product_insights_period_in('7d'));
	pg_assert_same('30d', pg_product_insights_period_in(array('all')));
	pg_assert_same('30d', pg_product_insights_period_in(null));
}

function test_product_insights_window_30_days()
{
	product_insights_test_in_zone('Europe/Istanbul', function () {
		$now = mktime(15, 30, 0, 3, 10, 2026);
		$window = pg_product_insights_window('30d', $now);

		pg_assert_same('day', $window['unit']);
		pg_assert_same(30, count($window['buckets']));
		pg_assert_same(mktime(0, 0, 0, 2, 9, 2026), $window['start']);
		pg_assert_same(mktime(0, 0, 0, 3, 10, 2026), $window['buckets'][29]);
		pg_assert_same(mktime(0, 0, 0, 1, 10, 2026), $window['prev_start']);
		pg_assert_same($now, $window['end']);
	});
}

function test_product_insights_window_30_days_over_a_clock_change()
{
	// Europe/Berlin moves to summer time on 29 March 2026: each bucket still
	// starts at midnight.
	product_insights_test_in_zone('Europe/Berlin', function () {
		$window = pg_product_insights_window('30d', mktime(12, 0, 0, 4, 5, 2026));

		foreach ($window['buckets'] as $start) {
			pg_assert_same('00:00', date('H:i', $start));
		}

		pg_assert_same('2026-03-07', date('Y-m-d', $window['start']));
	});
}

function test_product_insights_window_12_months()
{
	product_insights_test_in_zone('Europe/Istanbul', function () {
		$window = pg_product_insights_window('12m', mktime(9, 0, 0, 3, 31, 2026));

		pg_assert_same('month', $window['unit']);
		pg_assert_same(12, count($window['buckets']));
		pg_assert_same('2025-04-01', date('Y-m-d', $window['start']));
		pg_assert_same('2026-03-01', date('Y-m-d', $window['buckets'][11]));
		pg_assert_same('2024-04-01', date('Y-m-d', $window['prev_start']));
	});
}

function test_product_insights_window_all()
{
	product_insights_test_in_zone('Europe/Istanbul', function () {
		$window = pg_product_insights_window('all', mktime(9, 0, 0, 6, 1, 2026));

		pg_assert_same('year', $window['unit']);
		pg_assert_same(0, $window['start']);
		pg_assert_same(0, $window['prev_start']);
		pg_assert_same(10, count($window['buckets']));
		pg_assert_same('2017-01-01', date('Y-m-d', $window['buckets'][0]));
		pg_assert_same('2026-01-01', date('Y-m-d', $window['buckets'][9]));
	});
}

function test_product_insights_bounds()
{
	$with = array('prev_start' => 100, 'buckets' => array(200, 300));
	$without = array('prev_start' => 0, 'buckets' => array(200, 300));

	pg_assert_same(array(100, 200, 300), pg_product_insights_bounds($with));
	pg_assert_same(array(200, 300), pg_product_insights_bounds($without));
}

function test_product_insights_sales_fold_with_an_earlier_period()
{
	// Bounds: earlier start, then three buckets. INTERVAL() slot 1 is the
	// earlier period, 2 to 4 the buckets.
	$window = array('prev_start' => 100, 'buckets' => array(200, 300, 400));
	$rows = array(
		array('slot' => '1', 'units' => '5', 'revenue' => '5000', 'orders' => '4'),
		array('slot' => '2', 'units' => '2', 'revenue' => '2000', 'orders' => '2'),
		array('slot' => '4', 'units' => '3', 'revenue' => '2500', 'orders' => '1'),
	);

	$fold = pg_product_insights_sales_fold($window, $rows);

	pg_assert_same(5, $fold['units']);
	pg_assert_same(4500, $fold['revenue']);
	pg_assert_same(3, $fold['orders']);
	pg_assert_same(array('units' => 5, 'revenue' => 5000, 'orders' => 4), $fold['before']);
	pg_assert_same(array(
		array('start' => 200, 'units' => 2),
		array('start' => 300, 'units' => 0),
		array('start' => 400, 'units' => 3),
	), $fold['chart']);
}

function test_product_insights_sales_fold_all_counts_older_sales()
{
	// All: slot 0 is before the chart's first year; it counts in the
	// figures but in no bar.
	$window = array('prev_start' => 0, 'buckets' => array(200, 300));
	$rows = array(
		array('slot' => '0', 'units' => '7', 'revenue' => '700', 'orders' => '3'),
		array('slot' => '2', 'units' => '1', 'revenue' => '100', 'orders' => '1'),
	);

	$fold = pg_product_insights_sales_fold($window, $rows);

	pg_assert_same(8, $fold['units']);
	pg_assert_same(800, $fold['revenue']);
	pg_assert_same(4, $fold['orders']);
	pg_assert_same(null, $fold['before']);
	pg_assert_same(array(array('start' => 200, 'units' => 0), array('start' => 300, 'units' => 1)), $fold['chart']);
}

function test_product_insights_sales_fold_no_sales()
{
	$fold = pg_product_insights_sales_fold(array('prev_start' => 10, 'buckets' => array(20)), array());

	pg_assert_same(0, $fold['units']);
	pg_assert_same(array('units' => 0, 'revenue' => 0, 'orders' => 0), $fold['before']);
	pg_assert_same(array(array('start' => 20, 'units' => 0)), $fold['chart']);
}

function test_product_insights_change()
{
	pg_assert_same(50.0, pg_product_insights_change(150, 100));
	pg_assert_same(-33.3, pg_product_insights_change(2, 3));
	pg_assert_same(-100.0, pg_product_insights_change(0, 4));
	pg_assert_same(null, pg_product_insights_change(5, 0));
	pg_assert_same(null, pg_product_insights_change(5, null));
}

function test_product_insights_share_and_conversion()
{
	pg_assert_same(12.5, pg_product_insights_share(1, 8));
	pg_assert_same(null, pg_product_insights_share(1, 0));
	pg_assert_same(null, pg_product_insights_conversion(3, 19));
	pg_assert_same(15.0, pg_product_insights_conversion(3, 20));
	pg_assert_same(0.0, pg_product_insights_conversion(0, 50));
	pg_assert_same(null, pg_product_insights_conversion(3, null));
}

function test_product_insights_views_held()
{
	product_insights_test_in_zone('Europe/Istanbul', function () {
		$start = mktime(0, 0, 0, 3, 1, 2026);

		pg_assert_true(pg_product_insights_views_held('2026-03-01', $start));
		pg_assert_true(pg_product_insights_views_held('2025-01-01', $start));
		pg_assert_true(!pg_product_insights_views_held('2026-03-02', $start));
		pg_assert_true(pg_product_insights_views_held('2026-03-02', 0), 'All is held from the rollup start');
		pg_assert_true(!pg_product_insights_views_held(null, 0), 'an empty rollup holds nothing');
	});
}

function test_product_insights_margin_fold()
{
	$rows = array(
		array('revenue' => 1000, 'net' => 250, 'cost_product' => 600, 'state' => 'final'),
		array('revenue' => 1000, 'net' => 150, 'cost_product' => 700, 'state' => 'estimate'),
	);

	pg_assert_same(array('margin' => 20.0, 'state' => 'estimate'), pg_product_insights_margin_fold($rows));

	$rows[] = array('revenue' => 0, 'net' => 0, 'cost_product' => 0, 'state' => 'incomplete');
	pg_assert_same('incomplete', pg_product_insights_margin_fold($rows)['state']);

	// No product cost known, or no revenue: left out.
	pg_assert_same(null, pg_product_insights_margin_fold(array(array('revenue' => 1000, 'net' => 1000, 'cost_product' => 0, 'state' => 'incomplete'))));
	pg_assert_same(null, pg_product_insights_margin_fold(array()));
}

function test_product_insights_figures()
{
	$window = array('period' => '30d', 'unit' => 'day', 'prev_start' => 1, 'buckets' => array(2));
	$sales = array('units' => 10, 'revenue' => 9000, 'orders' => 8,
		'before' => array('units' => 5, 'revenue' => 0, 'orders' => 8), 'chart' => array(array('start' => 2, 'units' => 10)));
	$views = array('views' => 200, 'before' => 100, 'since' => '2025-01-01');
	$carts = array('left_carts' => 2, 'left_units' => 3, 'open_carts' => 1, 'open_units' => 1, 'saved_carts' => null);
	$interest = array('favourites' => 4, 'waiting' => null, 'rating_avg' => 4.5, 'rating_count' => 2);

	$out = pg_product_insights_figures($window, $sales, 1700000000, $views, 2, false, $carts, $interest, 1700000500);

	pg_assert_same('30d', $out['period']);
	pg_assert_same(false, $out['no_sales']);
	pg_assert_same(600, $out['keep']);
	pg_assert_same(4.0, $out['figures']['conversion']);
	pg_assert_same(20.0, $out['figures']['returns_share']);
	pg_assert_same(100.0, $out['figures']['change']['units']);
	pg_assert_same(null, $out['figures']['change']['revenue'], 'an earlier zero gives no change');
	pg_assert_same(0.0, $out['figures']['change']['orders']);
	pg_assert_same(100.0, $out['figures']['change']['views']);
	pg_assert_true(!array_key_exists('margin', $out['figures']), 'no margin key for a person who may not see it');
	pg_assert_same($carts, $out['carts']);
	pg_assert_same($interest, $out['interest']);

	$out = pg_product_insights_figures($window, $sales, 0, array('views' => null, 'before' => null, 'since' => ''), null,
		null, $carts, $interest, 1);

	pg_assert_same(null, $out['figures']['views']);
	pg_assert_same(null, $out['figures']['conversion']);
	pg_assert_same(null, $out['figures']['change']['views']);
	pg_assert_same(null, $out['figures']['returns_share']);
	pg_assert_same(null, $out['figures']['margin'], 'margin key present, no cost known');
	pg_assert_same('', $out['figures']['margin_state']);
}

function test_product_insights_figures_no_sales()
{
	$window = array('period' => 'all', 'unit' => 'year', 'prev_start' => 0, 'buckets' => array());
	$sales = array('units' => 0, 'revenue' => 0, 'orders' => 0, 'before' => null, 'chart' => array());

	$out = pg_product_insights_figures($window, $sales, 0, array('views' => 40, 'before' => null, 'since' => '2026-01-01'),
		0, false, array(), array(), 1);

	pg_assert_same(true, $out['no_sales']);
	pg_assert_same(40, $out['figures']['views']);
	pg_assert_same(0.0, $out['figures']['conversion']);
	pg_assert_same(null, $out['figures']['change']['units']);
	pg_assert_same(null, $out['figures']['returns_share']);
}

function test_product_insights_keep()
{
	$key = pg_product_insights_keep_key(12, '12m', true);
	pg_assert_same('12|12m|m', $key);
	pg_assert_same('12|30d|-', pg_product_insights_keep_key('12', 'nonsense', false));

	$kept = pg_product_insights_keep(array(), $key, array('a' => 1), 1000);

	pg_assert_same(array('a' => 1), pg_product_insights_kept($kept, $key, 1000));
	pg_assert_same(array('a' => 1), pg_product_insights_kept($kept, $key, 1599));
	pg_assert_same(null, pg_product_insights_kept($kept, $key, 1600), 'ten minutes old is gone');
	pg_assert_same(null, pg_product_insights_kept($kept, $key, 999), 'a read from the future is not trusted');
	pg_assert_same(null, pg_product_insights_kept($kept, 'other', 1000));
	pg_assert_same(null, pg_product_insights_kept('broken', $key, 1000));
	pg_assert_same(null, pg_product_insights_kept(array($key => array('at' => 1000)), $key, 1000));

	// A stale read is dropped when another is kept.
	$kept = pg_product_insights_keep($kept, 'b', array('b' => 1), 1700);
	pg_assert_same(array('b'), array_keys($kept));
}

function test_product_insights_keep_limit()
{
	$kept = array();

	for ($i = 1; $i <= PG_PRODUCT_INSIGHTS_KEPT_MAX + 3; $i++) {
		$kept = pg_product_insights_keep($kept, 'k' . $i, array($i), 1000 + $i);
	}

	pg_assert_same(PG_PRODUCT_INSIGHTS_KEPT_MAX, count($kept));
	pg_assert_true(!isset($kept['k1']) && !isset($kept['k3']), 'the oldest go first');
	pg_assert_true(isset($kept['k' . (PG_PRODUCT_INSIGHTS_KEPT_MAX + 3)]));
}

function test_product_insights_sales_from_reads_the_product_lines()
{
	$sql = pg_product_insights_sales_from(42, 1700000000);

	pg_assert_true(strpos($sql, "order_items.product_id = '42'") !== false);
	pg_assert_true(strpos($sql, "orders.status IN ('complete', 'exported')") !== false);
	pg_assert_true(strpos($sql, "orders.order_date >= '1700000000'") !== false);
	pg_assert_true(strpos(pg_product_insights_sales_from(42, 0), 'order_date') === false, 'no floor for All');
}
