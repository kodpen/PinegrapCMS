<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the report screens' shared view (includes/fn/report_view.php):
 * the chart a level gets, the summary figures from the grand total, and
 * the series of a line, bars and the map.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function pg_test_report_view_bucket($name, $count, $total, $extra = array())
{
	$bucket = pg_order_report_bucket($name);
	$bucket['count'] = $count;
	$bucket['total'] = $total;

	return array_merge($bucket, $extra);
}

function test_report_view_chart_kind()
{
	foreach (array('year', 'month', 'day') as $level) {
		pg_assert_same('line', pg_report_view_chart_kind($level), $level);
	}

	pg_assert_same('map', pg_report_view_chart_kind('billing_country'));
	pg_assert_same('none', pg_report_view_chart_kind(''));

	foreach (array('order_status', 'payment_method', 'utm_source', 'billing_state', 'billing_city', 'currency_code', 'special_offer_code') as $level) {
		pg_assert_same('bars', pg_report_view_chart_kind($level), $level);
	}

	foreach (array('card_number', 'cardholder', 'billing_email_address', 'ip_address', 'billing_last_name') as $level) {
		pg_assert_same('none', pg_report_view_chart_kind($level), $level);
	}
}

// The figures are the Grand Total row's: the count, then the money columns
// in the table's order, the charge columns only when shown.
function test_report_view_figures_follow_the_grand_total_row()
{
	$grand = pg_test_report_view_bucket('', 3, 123456, array('subtotal' => 100000, 'discount' => 1000, 'tax' => 20000, 'shipping' => 4456, 'surcharge' => 0));

	$figures = pg_report_view_figures($grand, array(), '$');

	pg_assert_same(array(lang('Orders'), lang('Subtotal'), lang('Discount'), lang('Tax'), lang('Shipping'), lang('Total')), array_column($figures, 'label'));
	pg_assert_same(pg_format_number(3, 0), $figures[0]['text']);
	pg_assert_same(pg_format_money(1234.56, '$'), $figures[5]['text'], 'total from minor units');
	pg_assert_same(pg_format_money(1000, '$'), $figures[1]['text']);

	$with_charge = pg_report_view_figures($grand, array('surcharge'), '$');

	pg_assert_same(lang('Card surcharge'), $with_charge[5]['label'], 'a charge column before Total');
	pg_assert_same(lang('Total'), $with_charge[6]['label']);
}

// A stored entity becomes the character: tiles are written as text.
function test_report_view_money_decodes_the_symbol()
{
	pg_assert_same(pg_format_money(10, "\u{20BA}"), pg_report_view_money(1000, '&#8378;'));
}

function test_report_view_line_keeps_the_time_order()
{
	$groups = array(
		0 => pg_test_report_view_bucket('09', 2, 5000),
		1 => pg_test_report_view_bucket('10', 1, 20000),
		2 => pg_test_report_view_bucket('11', 4, 1000),
	);
	$names = array(0 => 'September', 1 => 'October', 2 => 'November');

	$chart = pg_report_view_chart('line', $groups, $names, 'Month', '$');

	pg_assert_same('line', $chart['kind']);
	pg_assert_same(array(50.0, 200.0, 10.0), $chart['values']);
	pg_assert_same('September', $chart['first']);
	pg_assert_same('November', $chart['last']);
	pg_assert_same(3, count($chart['tips']));
	pg_assert_contains('October: ' . pg_format_money(200, '$'), $chart['tips'][1]);
	pg_assert_same(lang(array('string' => 'Total by {var:1}', 'vars' => 'Month')), $chart['label']);
}

function test_report_view_bars_take_the_largest()
{
	$groups = array();
	$names = array();

	for ($i = 1; $i <= 14; $i++) {
		$groups[$i] = pg_test_report_view_bucket('m' . $i, 1, $i * 100);
		$names[$i] = 'Method ' . $i;
	}

	$chart = pg_report_view_chart('bars', $groups, $names, 'Payment Method', '$');

	pg_assert_same(PG_REPORT_VIEW_ROWS, count($chart['rows']));
	pg_assert_same('Method 14', $chart['rows'][0]['text'], 'largest first');
	pg_assert_same(14.0, $chart['rows'][0]['value']);
	pg_assert_same(pg_format_money(14, '$'), $chart['rows'][0]['note']);
	pg_assert_false(isset($chart['map']));
}

function test_report_view_map_merges_spellings()
{
	$groups = array(
		0 => pg_test_report_view_bucket('TR', 5, 50000),
		1 => pg_test_report_view_bucket('Turkey', 1, 10000),
		2 => pg_test_report_view_bucket('US', 2, 30000),
		3 => pg_test_report_view_bucket('', 1, 500),
	);
	$names = array(0 => 'TR', 1 => 'Turkey', 2 => 'US', 3 => '');
	$codes = array(0 => 'TR', 1 => 'tr', 2 => 'US', 3 => '');

	$chart = pg_report_view_chart('map', $groups, $names, 'Billing Country', '$', $codes);

	pg_assert_same('world-countries.svg', $chart['map']);
	pg_assert_same(array('TR', 'US'), array_column($chart['places'], 'id'));
	pg_assert_contains(pg_format_money(600, '$'), $chart['places'][0]['text'], 'two spellings summed');
	$shades = (array) $chart['shades'];
	pg_assert_true($shades['TR'] > $shades['US'], 'the larger country is darker');
	pg_assert_same(4, count($chart['rows']), 'every group listed, an unknown one too');
}

function test_report_view_steps()
{
	pg_assert_same(array(), pg_report_view_steps(array('a' => 0)));
	pg_assert_same(array('a' => 4, 'b' => 3, 'c' => 2, 'd' => 1), pg_report_view_steps(array('a' => 40, 'b' => 30, 'c' => 20, 'd' => 10)));
	pg_assert_same(array('a' => 4), pg_report_view_steps(array('a' => 5)), 'a lone place is the darkest');
}

function test_report_view_nothing_to_draw()
{
	pg_assert_same(null, pg_report_view_chart('none', array(pg_test_report_view_bucket('a', 1, 1)), array('a'), 'x', '$'));
	pg_assert_same(null, pg_report_view_chart('line', array(), array(), 'x', '$'));
}

// Year, month and day draw the days, named by all three; the order kept.
function test_report_view_time_groups_take_the_finest_date_level()
{
	$day = function ($name, $total) {
		return pg_test_report_view_bucket($name, 1, $total);
	};
	$results = array(
		0 => array_merge(pg_test_report_view_bucket('2026', 3, 600), array('results' => array(
			0 => array_merge(pg_test_report_view_bucket('10', 2, 500), array('results' => array(0 => $day('30', 200), 1 => $day('31', 300)))),
			1 => array_merge(pg_test_report_view_bucket('11', 1, 100), array('results' => array(0 => $day('01', 100)))),
		))),
	);
	$name = function ($raw, $level) {
		return ($level === 'month') ? 'M' . $raw : $raw;
	};

	list($groups, $names, $level) = pg_report_view_time_groups($results, array('year', 'month', 'day'), $name);

	pg_assert_same(array('2026 M10 30', '2026 M10 31', '2026 M11 01'), $names);
	pg_assert_same(array(200, 300, 100), array_column($groups, 'total'));
	pg_assert_same('day', $level);

	list($groups, $names, $level) = pg_report_view_time_groups($results, array('year', 'payment_method'), $name);

	pg_assert_same(array('2026'), $names, 'a named level after the year stops the walk');
	pg_assert_same('year', $level);

	list($groups, $names, $level) = pg_report_view_time_groups($results, array('order_status'), $name);

	pg_assert_same(array(), $groups);
	pg_assert_same('', $level);
}
