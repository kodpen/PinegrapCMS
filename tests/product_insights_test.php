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

// An order the Customers report does not count (cancelled, or refunded in
// full: pg_customer_value_order_counts()) sells nothing here either.
function test_product_insights_counted_sql_follows_the_customer_value_rule()
{
	$sql = pg_product_insights_counted_sql(true, true);

	pg_assert_true(strpos($sql, "orders.status IN ('complete', 'exported')") !== false);
	pg_assert_true(strpos($sql, 'COALESCE(orders.cancelled_at, 0) = 0') !== false, 'a cancelled order is left out');
	pg_assert_true(strpos($sql, 'orders.refunded_amount <= 0 OR orders.refunded_amount < orders.total') !== false, 'a full refund is left out');

	$bare = pg_product_insights_counted_sql(false, false);
	pg_assert_true(strpos($bare, 'cancelled_at') === false, 'no column before its migration');
	pg_assert_true(strpos($bare, 'refunded_amount') === false, 'no column before its migration');
}

// A request the customer withdrew before sending anything holds no goods
// (pg_return_holds_goods()), so it is no return; one closed after the parcel
// left still is.
function test_product_insights_returns_held_sql()
{
	$sql = pg_product_insights_returns_held_sql();

	pg_assert_true(strpos($sql, "order_returns.status IN ('requested', 'approved', 'shipped', 'received', 'refunded')") !== false);
	pg_assert_true(strpos($sql, "order_returns.status = 'closed' AND (order_returns.shipped_at > 0 OR order_returns.received_at > 0 OR order_returns.refunded_at > 0)") !== false);
	pg_assert_true(strpos($sql, "<> 'rejected'") === false, 'no longer every status but rejected');
}

// Revenue is shown in the store's currency, so a marketplace order in
// another unit (pg_customer_value_order_unit()) adds its units and its order
// but no money.
function test_product_insights_base_unit_sql()
{
	$sql = pg_product_insights_base_unit_sql('try', true);

	pg_assert_true(strpos($sql, "orders.type <> 'marketplace'") !== false);
	pg_assert_true(strpos($sql, "TRIM(COALESCE(orders.currency_code, '')) = ''") !== false, 'no code is the base unit');
	pg_assert_true(strpos($sql, "UPPER(TRIM(orders.currency_code)) = 'TRY'") !== false, 'the base code in any case');
	pg_assert_same('1 = 1', pg_product_insights_base_unit_sql('TRY', false), 'no type column: every order is a web order');
	pg_assert_true(strpos(pg_product_insights_base_unit_sql("X'Y", true), "= 'XY'") !== false, 'only the letters of the code reach the SQL');
}

// Try again reads the period on screen: with storage blocked the stored one
// is always 30 days, so it used to answer another period than the pressed one.
function test_product_insights_script_retries_the_period_shown()
{
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/product_insights.js');

	pg_assert_true(strpos($js, 'load(shown);') !== false, 'Try again loads the period shown');
	pg_assert_true(strpos($js, 'shown = period;') !== false, 'load() remembers the period it asks for');
	pg_assert_same(1, substr_count($js, 'load(storedPeriod())'), 'only the first load reads storage');
}

function test_product_insights_head_pills()
{
	$pills = pg_product_insights_head_pills(array('enabled' => '1', 'inventory' => '1', 'inventory_quantity' => '14', 'backorder' => '0'));
	pg_assert_same(array(lang('Enabled'), 'teal'), $pills[0]);
	pg_assert_same('teal', $pills[1][1]);
	pg_assert_contains('14', $pills[1][0]);

	pg_assert_same(array(array(lang('Disabled'), 'gray')), pg_product_insights_head_pills(array('enabled' => '0', 'inventory' => '0', 'inventory_quantity' => '0')), 'no stock pill without inventory');
	pg_assert_same(array(lang('Out of stock'), 'rose'), pg_product_insights_stock_pill(array('inventory' => '1', 'inventory_quantity' => '0', 'backorder' => '0')));
	pg_assert_same(array(lang('Backorder'), 'amber'), pg_product_insights_stock_pill(array('inventory' => '1', 'inventory_quantity' => '-2', 'backorder' => '1')));
}

function test_product_insights_change_note()
{
	pg_assert_same(array('', ''), pg_product_insights_change_note(null));
	pg_assert_same('is-good', pg_product_insights_change_note(12.0)[1]);
	pg_assert_contains('↑', pg_product_insights_change_note(12.0)[0]);
	pg_assert_contains('12', pg_product_insights_change_note(12.0)[0]);
	pg_assert_same('is-bad', pg_product_insights_change_note(-3.5)[1]);
	pg_assert_contains('↓', pg_product_insights_change_note(-3.5)[0]);
	pg_assert_same('', pg_product_insights_change_note(0.0)[1]);
}

function test_product_insights_bucket_name()
{
	$start = mktime(0, 0, 0, 11, 5, 2026);
	pg_assert_same('2026', pg_product_insights_bucket_name($start, 'year'));
	pg_assert_same(lang('Nov') . ' 2026', pg_product_insights_bucket_name($start, 'month'));
	pg_assert_same('5 ' . lang('Nov'), pg_product_insights_bucket_name($start, 'day'));
}

// A month of figures as pg_product_insights_figures() answers them.
function product_insights_test_card_data($overrides = array())
{
	return array_replace_recursive(array(
		'period' => '12m',
		'unit' => 'month',
		'keep' => 600,
		'no_sales' => false,
		'figures' => array(
			'units' => 128, 'revenue' => 3454024, 'orders' => 97, 'views' => 4210, 'views_since' => '', 'conversion' => 2.3,
			'returns' => 4, 'returns_share' => 3.1, 'last_sale' => mktime(12, 0, 0, 11, 19, 2026),
			'change' => array('units' => 12.0, 'revenue' => 8.0, 'orders' => -3.0, 'views' => null),
		),
		'chart' => array(array('start' => mktime(0, 0, 0, 10, 1, 2026), 'units' => 0), array('start' => mktime(0, 0, 0, 11, 1, 2026), 'units' => 17)),
		'carts' => array('left_carts' => 23, 'left_units' => 31, 'open_carts' => 5, 'open_units' => 6, 'saved_carts' => null),
		'interest' => array('favourites' => 41, 'waiting' => null, 'rating_avg' => null, 'rating_count' => null),
	), $overrides);
}

function test_product_insights_card_html()
{
	$now = mktime(12, 0, 0, 11, 22, 2026);
	$html = pg_product_insights_card_html(product_insights_test_card_data(), 'A&B 1', '&#8378;', $now);

	pg_assert_same(7, substr_count($html, 'class="pg-figure-grid-cell"'), 'no margin key: seven cells');
	pg_assert_contains('&#8378;' . pg_format_number(34540.24, 2), $html, 'revenue in major units with the symbol as stored');
	pg_assert_same(2, substr_count($html, ' style="height:'), 'one bar a month');
	pg_assert_contains('class="is-zero"', $html, 'an empty month is a faint bar');
	pg_assert_contains('style="height:100%"', $html, 'the highest bar fills the chart');
	pg_assert_contains('href="view_orders.php?reset=true&amp;product_name=A%26B%201&amp;status=incomplete"', $html, 'View carts');
	pg_assert_contains('href="view_orders.php?reset=true&amp;product_name=A%26B%201"', $html, 'Orders of this product');
	pg_assert_contains(lang('Favourites'), $html);
	pg_assert_true(strpos($html, lang('Waiting for stock')) === false, 'a feature that is off has no row');
	pg_assert_true(strpos($html, lang('Saved for later')) === false, 'saved lines off: no row');
}

function test_product_insights_card_html_left_out_figures()
{
	$now = mktime(12, 0, 0, 11, 22, 2026);
	$data = product_insights_test_card_data();
	$data['figures']['views'] = null;
	$data['figures']['conversion'] = null;
	$data['figures']['returns'] = null;
	$data['figures']['margin'] = null;
	$data['figures']['last_sale'] = 0;
	$html = pg_product_insights_card_html($data, 'X', '$', $now);

	pg_assert_same(4, substr_count($html, 'class="pg-figure-grid-cell"'), 'units, revenue, orders, last sale');
	pg_assert_contains(lang('Never sold'), $html);

	$data = product_insights_test_card_data();
	$data['figures']['conversion'] = null;
	$data['figures']['margin'] = 38.0;
	$data['figures']['margin_state'] = 'estimate';
	$html = pg_product_insights_card_html($data, 'X', '$', $now);
	pg_assert_same(8, substr_count($html, 'class="pg-figure-grid-cell"'), 'views known: conversion keeps its cell; margin shown');
	pg_assert_contains(lang(array('string' => 'Shown from {var:1} views', 'vars' => array(20))), $html);
	pg_assert_contains(lang('Estimate: some costs are estimated'), $html);
}

function test_product_insights_card_html_no_sales()
{
	$data = product_insights_test_card_data(array('no_sales' => true));
	$data['figures']['views'] = 312;
	$html = pg_product_insights_card_html($data, 'X', '$', mktime(12, 0, 0, 11, 22, 2026));

	pg_assert_contains(lang('No sales in this period'), $html);
	pg_assert_same(0, substr_count($html, 'pg-figure-grid-cell'), 'no figure grid');
	pg_assert_true(strpos($html, 'pg-figure-grid-bars') === false, 'no chart');
	pg_assert_contains('312', $html, 'views still shown');
	pg_assert_contains(lang('Carts and interest'), $html, 'interest still shown');
}
