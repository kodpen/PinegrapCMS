<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the order screen's Create Label chooser
 * (includes/fn/shipment_label_chooser.php) and the one argument it gives
 * the label path (pg_shipment_label_chosen_driver()): when the chooser
 * shows, the rows' order and the suggestion, the second line, and which
 * posted driver the label is made with.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function _slc_test_drivers()
{
	return array(
		'geliver' => array('yurtici', 'aras', 'mng'),
		'yurtici' => array('yurtici'),
		'aras' => array('aras'),
	);
}

function _slc_test_named()
{
	return array(
		'geliver' => array('name' => 'Geliver', 'carriers' => array('yurtici', 'aras')),
		'yurtici' => array('name' => 'Yurtiçi', 'carriers' => array('yurtici')),
		'aras' => array('name' => 'Aras', 'carriers' => array('aras')),
	);
}

function test_shipment_label_chooser_due()
{
	$drivers = _slc_test_drivers();
	pg_assert_same(true, pg_shipment_label_chooser_due('', '', $drivers), 'no carrier, no linked driver, three ready');
	pg_assert_same(false, pg_shipment_label_chooser_due('aras', '', $drivers), 'a method that names a carrier keeps the confirm');
	pg_assert_same(false, pg_shipment_label_chooser_due('', 'aras', $drivers), 'a method that links a driver keeps the confirm');
	pg_assert_same(false, pg_shipment_label_chooser_due('', '', array('aras' => array('aras'))), 'one ready driver: no chooser');
	pg_assert_same(false, pg_shipment_label_chooser_due('', '', array()), 'no driver: no chooser');
}

function test_shipment_label_chosen_driver()
{
	$drivers = _slc_test_drivers();
	pg_assert_same('aras', pg_shipment_label_chosen_driver('aras', '', '', $drivers, 'geliver'), 'the chosen ready driver is used');
	pg_assert_same('geliver', pg_shipment_label_chosen_driver('', '', '', $drivers, 'geliver'), 'nothing posted (bulk run, automatic label, old form): the first ready driver');
	pg_assert_same('geliver', pg_shipment_label_chosen_driver('ups', '', '', $drivers, 'geliver'), 'a driver that is not ready is ignored');
	pg_assert_same('yurtici', pg_shipment_label_chosen_driver('aras', 'yurtici', '', $drivers, 'yurtici'), 'a method that names a carrier: the posted driver is ignored');
	pg_assert_same('geliver', pg_shipment_label_chosen_driver('aras', '', 'geliver', $drivers, 'geliver'), 'a method that links a driver: the posted driver is ignored');
}

function test_shipment_label_chooser_carrier()
{
	pg_assert_same('aras', pg_shipment_label_chooser_carrier(array('aras')), 'one carrier');
	pg_assert_same('', pg_shipment_label_chooser_carrier(array('aras', 'mng')), 'several: the service picks, no carrier');
	pg_assert_same('', pg_shipment_label_chooser_carrier(array()), 'none');
}

function test_shipment_label_chooser_rows_order()
{
	$on_time = array(
		'yurtici' => array('country' => array('judged' => 100, 'on_time' => 80), 'provinces' => array('ankara' => array('judged' => 50, 'on_time' => 47))),
		'aras' => array('country' => array('judged' => 100, 'on_time' => 90), 'provinces' => array()),
	);
	$keys = function ($rows) {
		return implode(',', array_column($rows, 'key'));
	};

	$rows = pg_shipment_label_chooser_rows(_slc_test_named(), array('geliver' => 5000, 'aras' => 8450), $on_time, 'ankara');
	pg_assert_same('yurtici,aras,geliver', $keys($rows), 'the province\'s share first, then the country\'s, an unmeasured driver last');
	pg_assert_same(array(true, false, false), array_column($rows, 'suggested'), 'only the first is suggested');
	pg_assert_same(array('province', 'country', ''), array_column($rows, 'scope'), 'where each share was measured');
	pg_assert_same(94.0, $rows[0]['share'], 'the province share goes with the row');
	pg_assert_same(8450, $rows[1]['price'], 'the price goes with the row');

	$rows = pg_shipment_label_chooser_rows(_slc_test_named(), array('aras' => 6000, 'yurtici' => 5000), array(
		'yurtici' => array('country' => array('judged' => 100, 'on_time' => 90), 'provinces' => array()),
		'aras' => array('country' => array('judged' => 100, 'on_time' => 90), 'provinces' => array()),
	), 'izmir');
	pg_assert_same('yurtici,aras,geliver', $keys($rows), 'equal shares: the lower price first');

	$rows = pg_shipment_label_chooser_rows(_slc_test_named(), array('aras' => 100), array(), 'ankara');
	pg_assert_same('geliver,yurtici,aras', $keys($rows), 'no figure for any driver: today\'s order, not the price');
	pg_assert_same(array(false, false, false), array_column($rows, 'suggested'), 'no figure: no pill');
}

function test_shipment_label_chooser_line()
{
	$row = array('share' => 94.0, 'scope' => 'province', 'price' => null);
	pg_assert_same(lang(array('string' => 'On time {var:1}% in {var:2}', 'vars' => array(pg_format_number(94, 0), 'Ankara'))), pg_shipment_label_chooser_line($row, 'Ankara', '₺'), 'the province share');
	$row = array('share' => null, 'scope' => '', 'price' => 8450);
	$line = pg_shipment_label_chooser_line($row, 'Ankara', '₺');
	pg_assert_same(0, strpos($line, lang('Not enough parcels yet') . ' · '), 'no share: not enough parcels, then the price');
	pg_assert_same(true, strpos($line, pg_money_text(84.5, '₺')) !== false, 'the price in major units');
}

function test_shipment_label_chooser_rows_html()
{
	$rows = pg_shipment_label_chooser_rows(_slc_test_named(), array(), array(
		'aras' => array('country' => array('judged' => 100, 'on_time' => 90), 'provinces' => array()),
	), '');
	$html = pg_shipment_label_chooser_rows_html($rows, '', '₺');
	pg_assert_same(3, substr_count($html, 'name="label_driver"'), 'one radio a ready driver');
	pg_assert_same(3, substr_count($html, 'form="shipment-label-form"'), 'every radio posts with the label form');
	pg_assert_same(1, substr_count($html, ' checked'), 'one row chosen when the dialog opens');
	pg_assert_same(1, substr_count($html, 'pg-pill pg-tone-teal'), 'one Suggested pill');
	pg_assert_same(true, strpos($html, 'value="aras" checked') !== false, 'the suggested row is the chosen one');
}
