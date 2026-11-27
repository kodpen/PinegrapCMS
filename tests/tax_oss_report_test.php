<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests the one-stop-shop report (includes/fn/tax_oss_report.php): the member
 * state an order line is taxed in (pg_tax_line_country()), the state a line is
 * reported under with and without a stored one, the period, the groups by
 * state, supply and rate with the order discount shared out, the euro
 * conversion and the CSV rows.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_tax_oss_line_country()
{
	$day = '2026-11-27';

	pg_assert_same('DE', pg_tax_line_country(true, false, 'de', $day), 'a member state');
	pg_assert_same('GR', pg_tax_line_country(true, false, 'EL', $day), 'EL is Greece');
	pg_assert_same('', pg_tax_line_country(true, false, 'TR', $day), 'outside the EU');
	pg_assert_same('', pg_tax_line_country(true, false, '', $day), 'no country');
	pg_assert_same('', pg_tax_line_country(false, false, 'DE', $day), 'tax switched off');
	pg_assert_same('', pg_tax_line_country(true, '1', 'DE', $day), 'an exempt order');
}

function test_tax_oss_line_country_stored_or_apart()
{
	$day = '2026-11-27';

	pg_assert_same(array('country' => 'FR', 'stored' => true), pg_oss_line_country('FR', 0, '', 'DE', 0, $day), 'the stored state wins over the addresses');
	pg_assert_same(array('country' => '', 'stored' => true), pg_oss_line_country('', 3, 'FR', 'DE', 0, $day), 'a stored empty state is no state');
	pg_assert_same(array('country' => 'FR', 'stored' => false), pg_oss_line_country(null, 3, 'FR', 'DE', 0, $day), 'before the column: the recipient');
	pg_assert_same(array('country' => 'DE', 'stored' => false), pg_oss_line_country(null, 0, '', 'DE', 0, $day), 'before the column: the billing address');
	pg_assert_same(array('country' => '', 'stored' => false), pg_oss_line_country(null, 0, '', 'DE', 1, $day), 'an exempt order before the column');
	pg_assert_same(array('country' => '', 'stored' => false), pg_oss_line_country(null, 0, '', 'US', 0, $day), 'outside the EU before the column');
}

function test_tax_oss_period()
{
	$today = '2026-11-27';

	$quarter = pg_oss_period('quarter', '2026-Q3', $today);
	pg_assert_same(array('2026-07-01', '2026-09-30', ''), array($quarter['from'], $quarter['to'], $quarter['error']), 'the third quarter');

	$month = pg_oss_period('month', '2024-02', $today);
	pg_assert_same(array('2024-02-01', '2024-02-29', '2024-02'), array($month['from'], $month['to'], $month['value']), 'a leap February');

	$current = pg_oss_period('quarter', '', $today);
	pg_assert_same(array('2026-Q4', '2026-10-01', '2026-12-31', ''), array($current['value'], $current['from'], $current['to'], $current['error']), 'no value: the current quarter');

	$bad = pg_oss_period('month', '2026-13', $today);
	pg_assert_same('2026-11', $bad['value'], 'a month out of range: the current one');
	pg_assert_true($bad['error'] !== '', 'and an error');

	pg_assert_same('quarter', pg_oss_period('year', '2026', $today)['range'], 'an unknown range is a quarter');

	pg_assert_same(array('2026-Q4', '2026-Q3', '2026-Q2', '2026-Q1', '2025-Q4'), pg_oss_period_values('quarter', $today, 5), 'quarters newest first');
	pg_assert_same(array('2026-02', '2026-01', '2025-12'), pg_oss_period_values('month', '2026-02-10', 3), 'months across a year');
}

function test_tax_oss_report_groups()
{
	$order = array('id' => 7, 'discount' => 0, 'tax' => 3800 + 700 + 2000);
	$lines = array(
		1 => array('amount' => 20000, 'rate' => '19.000', 'tax' => 3800, 'quantity' => 2, 'alone' => false, 'country' => 'DE', 'stored' => true, 'goods' => true),
		2 => array('amount' => 10000, 'rate' => '7.000', 'tax' => 700, 'quantity' => 1, 'alone' => false, 'country' => 'DE', 'stored' => true, 'goods' => true),
		3 => array('amount' => 10000, 'rate' => '20.000', 'tax' => 2000, 'quantity' => 1, 'alone' => false, 'country' => 'FR', 'stored' => true, 'goods' => false),
		4 => array('amount' => 5000, 'rate' => '0.000', 'tax' => 0, 'quantity' => 1, 'alone' => false, 'country' => '', 'stored' => true, 'goods' => true),
		5 => array('amount' => 9000, 'rate' => '19.000', 'tax' => 1710, 'quantity' => 1, 'alone' => true, 'country' => 'DE', 'stored' => true, 'goods' => false),
	);

	$report = pg_oss_report_add(pg_oss_report_empty(), $order, $lines);
	$rows = pg_oss_report_rows($report['stored'], 1.0);

	pg_assert_same(3, count($rows['rows']), 'DE 7, DE 19, FR 20; no state and a later recurring line left out');
	pg_assert_same(array('DE', 'goods', 7.0, 10000, 700), array($rows['rows'][0]['country'], $rows['rows'][0]['supply'], $rows['rows'][0]['rate'], $rows['rows'][0]['taxable'], $rows['rows'][0]['tax']), 'lower rate first');
	pg_assert_same(array('FR', 'services', 20.0), array($rows['rows'][2]['country'], $rows['rows'][2]['supply'], $rows['rows'][2]['rate']), 'a service');
	pg_assert_same(40000, $rows['taxable'], 'taxable total');
	pg_assert_same(6500, $rows['tax'], 'tax total');
	pg_assert_same(array(), $report['apart'], 'nothing apart');
}

function test_tax_oss_report_discount_and_apart()
{
	// 10% off the order: each line's amount and tax lowered by its share.
	$order = array('id' => 8, 'discount' => 3000, 'tax' => 3420 + 1800);
	$lines = array(
		1 => array('amount' => 20000, 'rate' => '19.000', 'tax' => 3800, 'quantity' => 1, 'alone' => false, 'country' => 'DE', 'stored' => true, 'goods' => true),
		2 => array('amount' => 10000, 'rate' => null, 'tax' => 2000, 'quantity' => 1, 'alone' => false, 'country' => 'FR', 'stored' => false, 'goods' => true),
	);

	$report = pg_oss_report_add(pg_oss_report_empty(), $order, $lines);
	$stored = pg_oss_report_rows($report['stored'], 1.0);
	$apart = pg_oss_report_rows($report['apart'], 1.0);

	pg_assert_same(array(18000, 3420), array($stored['taxable'], $stored['tax']), 'stored: 2,000 off, tax at 19% of 18,000');
	pg_assert_same(1, count($apart['rows']), 'the line without a stored state is apart');
	pg_assert_same(array('FR', 20.0, 9000, 1800), array($apart['rows'][0]['country'], $apart['rows'][0]['rate'], $apart['taxable'], $apart['tax']), 'its rate from its tax, its share off');

	// Two orders in one group count two orders.
	$report = pg_oss_report_add($report, array('id' => 9, 'discount' => 0, 'tax' => 190), array(
		3 => array('amount' => 1000, 'rate' => '19.000', 'tax' => 190, 'quantity' => 1, 'alone' => false, 'country' => 'DE', 'stored' => true, 'goods' => true),
	));
	$stored = pg_oss_report_rows($report['stored'], 1.0);
	pg_assert_same(array(2, 2), array($stored['rows'][0]['lines'], $stored['rows'][0]['orders']), 'lines and orders');
}

function test_tax_oss_euro_and_csv()
{
	pg_assert_same(1000, pg_oss_to_euro(1000, 1.0), 'a euro store');
	pg_assert_same(2000, pg_oss_to_euro(100000, 50.0), '1,000.00 at 50 per euro');
	pg_assert_same(3, pg_oss_to_euro(5, 2.0), 'half away from zero');
	pg_assert_same(-3, pg_oss_to_euro(-5, 2.0), 'half away from zero below zero');
	pg_assert_same(0, pg_oss_to_euro(1000, 0), 'no rate');

	pg_assert_same('19', pg_oss_rate_text(19.0), 'whole rate');
	pg_assert_same('25.5', pg_oss_rate_text(25.5), 'decimal rate');
	pg_assert_same('0', pg_oss_rate_text(0), 'zero rate');
	pg_assert_same('12.34', pg_oss_amount_text(1234), 'amount');
	pg_assert_same('-0.05', pg_oss_amount_text(-5), 'negative amount');

	$labels = array('part' => 'Part', 'country' => 'Member state', 'supply' => 'Supply', 'rate' => 'Rate', 'taxable' => 'Taxable', 'tax' => 'VAT', 'lines' => 'Lines',
		'goods' => 'Goods', 'services' => 'Services', 'stored_part' => 'Stored', 'apart_part' => 'Apart');
	$stored = array('rows' => array(array('country' => 'DE', 'supply' => 'goods', 'rate' => 19.0, 'taxable' => 10000, 'tax' => 1900, 'lines' => 2, 'orders' => 1)));
	$apart = array('rows' => array(array('country' => 'FR', 'supply' => 'services', 'rate' => 20.0, 'taxable' => 500, 'tax' => 100, 'lines' => 1, 'orders' => 1)));
	$csv = pg_oss_report_csv_rows($stored, $apart, $labels);

	pg_assert_same(3, count($csv), 'heading and two rows');
	pg_assert_same(array('Stored', 'DE', 'Goods', '19', '100.00', '19.00', '2'), $csv[1], 'a stored row');
	pg_assert_same(array('Apart', 'FR', 'Services', '20', '5.00', '1.00', '1'), $csv[2], 'a row apart');
}
