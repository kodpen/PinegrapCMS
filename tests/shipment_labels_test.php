<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for shipment labels (includes/fn/shipment_labels.php): the carrier
 * and driver an address takes, when a kept document is due to go, the file
 * name and the check that keeps a stored name inside its folder, the
 * create() shipment built from an order, and the row a create() answer
 * becomes. The stub driver below lives in this file; no service and no
 * database is reached.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function pg_label_pgtestlabel_info()
{
	return array('carriers' => array('aras', 'yurtici'), 'formats' => array('pdf'));
}

function pg_label_pgtestlabel_create($shipment, $account)
{
	return array(
		'success'         => true,
		'reference'       => 'shp-' . $shipment['order_number'],
		'tracking_number' => ' 1234567890 ',
		'carrier'         => 'aras',
		'label'           => '%PDF-1.4 label',
		'label_format'    => 'pdf',
		'barcode'         => str_repeat('B', 120),
		'fee'             => 4250,
	);
}

function shipment_labels_test_register()
{
	static $done = false;

	if (!$done) {
		pg_label_driver_register('pgtestlabel', array('label' => 'Test Labels', 'file' => __FILE__));
		$done = true;
	}
}

// The method's carrier first, then what the method code spells; nothing
// named gives ''.
function test_shipment_label_carrier_from_method_then_code()
{
	pg_assert_same('aras', pg_shipment_label_carrier('aras', 'YURTICI-STANDART'), 'method carrier wins');
	pg_assert_same('yurtici', pg_shipment_label_carrier('', 'Yurtiçi Kargo'), 'method code spells it');
	pg_assert_same('yurtici', pg_shipment_label_carrier('nowhere', 'yurtici'), 'an unknown method carrier is skipped');
	pg_assert_same('', pg_shipment_label_carrier('', 'Standard'), 'nothing named');
}

// The first driver that ships with the carrier; an unknown carrier takes the
// first driver; a carrier no driver ships with gets none.
function test_shipment_label_driver_pick()
{
	$drivers = array('first' => array('yurtici'), 'second' => array('aras', 'yurtici'));

	pg_assert_same('second', pg_shipment_label_driver_pick('aras', $drivers), 'the one that ships with aras');
	pg_assert_same('first', pg_shipment_label_driver_pick('yurtici', $drivers), 'the first of two');
	pg_assert_same('first', pg_shipment_label_driver_pick('', $drivers), 'unknown carrier: the first');
	pg_assert_same('', pg_shipment_label_driver_pick('ups', $drivers), 'never another carrier');
	pg_assert_same('', pg_shipment_label_driver_pick('', array()), 'no driver');
}

// 90 days after creation at the latest, 30 days after delivery or cancel.
function test_shipment_label_expires_at()
{
	$day = 86400;
	$made = 1000000000;

	pg_assert_same($made + 90 * $day, pg_shipment_label_expires_at($made, 0, 0), 'on its way: 90 days');
	pg_assert_same($made + 35 * $day, pg_shipment_label_expires_at($made, $made + 5 * $day, 0), 'delivered on day 5');
	pg_assert_same($made + 32 * $day, pg_shipment_label_expires_at($made, 0, $made + 2 * $day), 'cancelled on day 2');
	pg_assert_same($made + 32 * $day, pg_shipment_label_expires_at($made, $made + 9 * $day, $made + 2 * $day), 'the earlier end');
	pg_assert_same($made + 90 * $day, pg_shipment_label_expires_at($made, $made + 80 * $day, 0), 'never past 90 days');
}

// The name holds no text the service sent, and only such names make a path.
function test_shipment_label_file_name_and_path_safety()
{
	$name = pg_shipment_label_file_name(12, 34, 'geliver', '../../etc/passwd', 'pdf');

	pg_assert_same(1, preg_match('/^12_34_geliver_[0-9a-f]{16}\.pdf$/', $name), 'order, address, driver, digest');
	pg_assert_true(pg_shipment_label_file_safe($name), 'its own name is safe');
	pg_assert_true($name !== pg_shipment_label_file_name(12, 34, 'geliver', 'other', 'pdf'), 'another reference, another file');
	pg_assert_same('', pg_shipment_label_file_name(12, 34, 'geliver', 'ref', 'png'), 'unknown format');
	pg_assert_same('', pg_shipment_label_file_name(0, 34, 'geliver', 'ref', 'pdf'), 'no order');
	pg_assert_same('', pg_shipment_label_file_name(12, 34, '../x', 'ref', 'pdf'), 'invalid driver');
	pg_assert_same('', pg_shipment_label_file_name(12, 34, 'geliver', '', 'pdf'), 'no reference');

	foreach (array('../12_34_geliver_0123456789abcdef.pdf', '12_34_geliver_0123456789abcdef.pdf/..', '12_34_geliver_0123456789abcdef.php',
		'a/12_34_geliver_0123456789abcdef.pdf', '', null, array(), '12_34_geliver_0123456789abcdef.pdf.php') as $bad) {
		pg_assert_true(!pg_shipment_label_file_safe($bad), 'refused: ' . json_encode($bad));
		pg_assert_same('', pg_shipment_label_path($bad), 'no path: ' . json_encode($bad));
	}

	pg_assert_same(pg_shipment_label_dir() . '/' . $name, pg_shipment_label_path($name), 'inside the folder');
}

// Pounds and inches become kilograms and centimetres; an empty parcel gets
// the floor values.
function test_shipment_label_shipment_shape()
{
	$shipment = pg_shipment_label_shipment(
		array('order_number' => '1001', 'billing_email_address' => 'a@example.com', 'currency' => 'try'),
		array('first_name' => 'Ayşe', 'last_name' => 'Yılmaz', 'address_1' => 'Cadde 1', 'city' => 'Kadıköy', 'state' => 'İstanbul',
			'zip_code' => '34710', 'country' => 'tr', 'phone_number' => '05550000000', 'total' => 12345),
		array(
			array('product_name' => 'Mug', 'quantity' => 2, 'weight' => '1.0000', 'length' => '4', 'width' => '4', 'height' => '6'),
			array('product_name' => 'Plate', 'quantity' => 1, 'weight' => '0.5', 'length' => '10', 'width' => '10', 'height' => '1'),
		),
		'TR-34'
	);

	pg_assert_same('Ayşe Yılmaz', $shipment['recipient']['name'], 'name');
	pg_assert_same('TR', $shipment['recipient']['country'], 'country upper case');
	pg_assert_same('TR-34', $shipment['recipient']['state_code'], 'plate');
	pg_assert_same(array('length' => 25.4, 'width' => 25.4, 'height' => 15.24, 'weight' => round(2.5 * 0.45359237, 3)), $shipment['parcel'], 'parcel in cm and kg');
	pg_assert_same(array(array('title' => 'Mug', 'quantity' => 2), array('title' => 'Plate', 'quantity' => 1)), $shipment['items'], 'items');
	pg_assert_same(12345, $shipment['total'], 'total in minor units');
	pg_assert_same('TRY', $shipment['currency'], 'currency');
	pg_assert_same(false, $shipment['return'], 'not a return');

	$empty = pg_shipment_label_shipment(array(), array('company' => 'Acme'), array(array('product_name' => 'Gift', 'quantity' => 0)));

	pg_assert_same('Acme', $empty['recipient']['name'], 'company when no name');
	pg_assert_same(array('length' => 10.0, 'width' => 10.0, 'height' => 10.0, 'weight' => 1.0), $empty['parcel'], 'floor values');
}

// The stub's create() through the one door becomes a row of the decided
// columns: the number cleaned, the barcode cut to its column, the service's
// own fields gone.
function test_shipment_label_row_from_a_stub_answer()
{
	shipment_labels_test_register();

	$answer = pg_label_call('pgtestlabel', 'create', array(array('order_number' => '1001'), array()));

	pg_assert_true($answer['success'], 'stub create succeeds');

	$file = pg_shipment_label_file_name(7, 9, 'pgtestlabel', $answer['reference'], $answer['label_format']);
	$row = pg_shipment_label_row(7, 9, 'pgtestlabel', $answer, $file, 3, 1700000000);

	pg_assert_same(array(
		'order_id', 'ship_to_id', 'number', 'driver', 'reference', 'barcode', 'label_format', 'label_file',
		'state', 'is_return', 'user_id', 'created_at', 'cancelled_at', 'label_removed_at',
	), array_keys($row), 'the decided columns');
	pg_assert_same('1234567890', $row['number'], 'number cleaned');
	pg_assert_same('shp-1001', $row['reference'], 'reference');
	pg_assert_same(100, strlen($row['barcode']), 'barcode cut to its column');
	pg_assert_same('pdf', $row['label_format'], 'format');
	pg_assert_same($file, $row['label_file'], 'file');
	pg_assert_same('active', $row['state'], 'active');
	pg_assert_true(in_array($row['state'], pg_shipment_label_states(), true), 'a known state');
	pg_assert_same(0, $row['is_return'], 'not a return');
	pg_assert_same(array(7, 9, 3, 1700000000, 0, 0), array($row['order_id'], $row['ship_to_id'], $row['user_id'], $row['created_at'], $row['cancelled_at'], $row['label_removed_at']), 'numbers');

	$unsafe = pg_shipment_label_row(7, 9, 'pgtestlabel', $answer, '../x.pdf', 3, 1700000000, true);

	pg_assert_same('', $unsafe['label_file'], 'an unsafe name is not kept');
	pg_assert_same(1, $unsafe['is_return'], 'a return');
	pg_assert_same(array('source' => 'carrier', 'carrier' => 'aras'), pg_label_shipment_options($answer), 'what reaches the parcel row');
}
