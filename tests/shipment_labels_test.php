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
	pg_assert_same(array(
		array('name' => 'Mug', 'quantity' => 2, 'price' => 0, 'weight' => 0.454, 'hs_code' => '', 'origin_country' => '', 'sku' => ''),
		array('name' => 'Plate', 'quantity' => 1, 'price' => 0, 'weight' => 0.227, 'hs_code' => '', 'origin_country' => '', 'sku' => ''),
	), $shipment['goods'], 'goods for customs, nothing filled');

	$customs = pg_shipment_label_shipment(array(), array(), array(
		array('product_name' => 'Mug', 'quantity' => 1, 'price' => '1249.5', 'weight' => '1', 'hs_code' => '6912.00', 'origin_country' => 'tr', 'sku' => ' MUG-01 '),
	));

	pg_assert_same(array('name' => 'Mug', 'quantity' => 1, 'price' => 1250, 'weight' => 0.454, 'hs_code' => '6912.00', 'origin_country' => 'TR', 'sku' => 'MUG-01'), $customs['goods'][0], 'unit price rounded, unit weight in kg, SKU trimmed');
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

// Only active outbound labels are printed, in the order the orders were
// selected, then by address and age; a non-PDF one is set apart.
function test_shipment_label_print_selection()
{
	$rows = array(
		array('id' => 5, 'order_id' => 20, 'ship_to_id' => 2, 'state' => 'active', 'is_return' => 0, 'label_format' => 'pdf'),
		array('id' => 3, 'order_id' => 10, 'ship_to_id' => 9, 'state' => 'active', 'is_return' => 0, 'label_format' => 'pdf'),
		array('id' => 4, 'order_id' => 10, 'ship_to_id' => 1, 'state' => 'active', 'is_return' => 0, 'label_format' => 'zpl'),
		array('id' => 6, 'order_id' => 10, 'ship_to_id' => 1, 'state' => 'cancelled', 'is_return' => 0, 'label_format' => 'pdf'),
		array('id' => 7, 'order_id' => 20, 'ship_to_id' => 2, 'state' => 'active', 'is_return' => 1, 'label_format' => 'pdf'),
		array('id' => 2, 'order_id' => 20, 'ship_to_id' => 2, 'state' => 'active', 'is_return' => 0, 'label_format' => ''),
		array('id' => 8, 'order_id' => 30, 'ship_to_id' => 4, 'state' => 'active', 'is_return' => 0, 'label_format' => 'pdf'),
	);

	$selection = pg_shipment_label_print_selection($rows, array(20, 10));

	pg_assert_same(array(2, 5, 4, 3), array_map(function ($row) {
		return $row['id'];
	}, $selection), 'active outbound labels of the selected orders, in order');
}

// Cancelling a label keeps every other number of the address.
function test_shipment_label_numbers_after_cancel()
{
	pg_assert_same(array('AB123', '777'), pg_shipment_label_numbers_after_cancel(array('AB123', ' ab999 ', '777'), 'AB999'), 'case and spaces ignored');
	pg_assert_same(array('AB123'), pg_shipment_label_numbers_after_cancel(array('AB123'), ''), 'nothing named, nothing removed');
}

// What a cancel answer writes: only a success changes the row.
function test_shipment_label_cancel_update()
{
	pg_assert_same(array('state' => 'cancelled', 'cancelled_at' => 1700000000), pg_shipment_label_cancel_update(array('success' => true), 1700000000), 'cancelled');
	pg_assert_same(array(), pg_shipment_label_cancel_update(array('success' => false, 'unclear' => true), 1700000000), 'unclear: nothing written');
	pg_assert_same(array(), pg_shipment_label_cancel_update(array('success' => false), 1700000000), 'refused: nothing written');
}

// A return shipment carries the flag the driver reads; its row is marked.
function test_shipment_label_return_flag()
{
	$shipment = pg_shipment_label_shipment(array(), array('first_name' => 'A'), array(), '', true);

	pg_assert_same(true, $shipment['return'], 'return shipment');

	shipment_labels_test_register();
	$answer = pg_label_call('pgtestlabel', 'create', array(array('order_number' => '1002', 'return' => true), array()));
	$row = pg_shipment_label_row(7, 9, 'pgtestlabel', $answer, '', 3, 1700000000, true);

	pg_assert_same(1, $row['is_return'], 'return row');
}

function test_shipment_label_svg_file_name_is_safe()
{
	$name = pg_shipment_label_file_name(42, 7, 'basitkargo', '888-6AR-OUP', 'svg');
	pg_assert_true((bool) preg_match('/^42_7_basitkargo_[0-9a-f]{16}\.svg$/', $name), $name);
	pg_assert_true(pg_shipment_label_file_safe($name));
	pg_assert_false(pg_shipment_label_file_safe('42_7_basitkargo_0123456789abcdef.svgz'));
	pg_assert_same('', pg_shipment_label_file_name(42, 7, 'basitkargo', '888-6AR-OUP', 'html'));
}

// An SVG never reaches the panel as a document of its own: the print page
// only holds it as an image, and the download is sandboxed.
function test_shipment_label_serving_headers()
{
	$page = pg_shipment_label_headers('svg', 'label-BK1', false);
	pg_assert_contains('Content-Type: text/html; charset=utf-8', $page);
	pg_assert_contains('X-Content-Type-Options: nosniff', $page);
	pg_assert_contains('Cache-Control: private, no-store', $page);
	$csp = array_values(preg_grep('/^Content-Security-Policy: /', $page));
	pg_assert_same(1, count($csp));
	pg_assert_contains("default-src 'none'", $csp[0]);
	pg_assert_contains('img-src data:', $csp[0]);

	$download = pg_shipment_label_headers('svg', 'label-BK1', true);
	pg_assert_contains('Content-Type: image/svg+xml', $download);
	pg_assert_contains('Content-Disposition: attachment; filename="label-BK1.svg"', $download);
	pg_assert_contains('Content-Security-Policy: sandbox', $download);

	pg_assert_contains('Content-Disposition: inline; filename="label-BK1.pdf"', pg_shipment_label_headers('pdf', 'label-BK1', false));
	pg_assert_contains('Content-Disposition: attachment; filename="label-BK1.zpl"', pg_shipment_label_headers('zpl', 'label-BK1', false));
	pg_assert_contains('Content-Type: application/octet-stream', pg_shipment_label_headers('zpl', 'label-BK1', true));
}

function test_shipment_label_svg_page_holds_the_svg_as_an_image_only()
{
	$svg = '<svg xmlns="http://www.w3.org/2000/svg" width="100mm" height="150mm"><text>&lt;b&gt;</text></svg>';
	$html = pg_shipment_label_svg_page($svg, 'Label <BK1>', 'shipment_label.php?id=5&download=1');

	pg_assert_contains('<img src="data:image/svg+xml;base64,' . base64_encode($svg) . '"', $html);
	pg_assert_false(strpos($html, '<svg'), 'never inline');
	pg_assert_contains('Label &lt;BK1&gt;', $html);
	pg_assert_contains('href="shipment_label.php?id=5&amp;download=1"', $html);
}

function test_shipment_label_svg_size_in_points()
{
	pg_assert_same(array(283.46, 425.2), pg_shipment_label_svg_size('<svg width="100mm" height="150mm" viewBox="0 0 400 600"></svg>'));
	pg_assert_same(array(288.0, 432.0), pg_shipment_label_svg_size("<svg height='6in' width='4in'></svg>"));
	pg_assert_same(array(300.0, 450.0), pg_shipment_label_svg_size('<svg width="400" height="600"></svg>'), 'pixels');
	pg_assert_same(array(283.46, 425.19), pg_shipment_label_svg_size('<svg viewBox="0 0 400 600"></svg>'), 'the view box at 100 mm wide');
	pg_assert_same(array(297.64, 419.53), pg_shipment_label_svg_size('<svg width="100%"></svg>'), 'A6 without a size');
}

function test_shipment_label_svg_drawn_into_a_pdf_page()
{
	$svg = (string) file_get_contents(dirname(__FILE__) . '/fixtures/basitkargo/label.svg');
	$pdf = pg_shipment_label_svg_pdf($svg);

	pg_assert_true(pg_label_document_valid($pdf, 'pdf'), 'dompdf drew the label');
	pg_assert_true((bool) preg_match('#/MediaBox\s*\[\s*0(?:\.0+)?\s+0(?:\.0+)?\s+283\.4[0-9]*\s+425\.[0-9]+\s*\]#', $pdf), 'a page of the label\'s size');
	pg_assert_same(1, preg_match_all('#/Type\s*/Page\b(?!s)#', $pdf), 'one page');
	pg_assert_true(pg_label_document_valid(pg_pdf_merge(array($pdf, $pdf)), 'pdf'), 'the merger reads it');
}

function test_shipment_label_svg_fonts_name_the_unicode_files()
{
	$svg = pg_shipment_label_svg_fonts('<svg width="1"><text font-family="monospace">a</text><text font-family=\'Times New Roman\'>b</text><text style="font-size:9px;font-family:Arial, sans-serif">ş</text></svg>', '/f');

	pg_assert_contains('<svg font-family="/f/DejaVuSans" width="1">', $svg);
	pg_assert_contains('font-family="/f/DejaVuSansMono"', $svg);
	pg_assert_contains('font-family="/f/DejaVuSerif"', $svg);
	pg_assert_contains('font-size:9px;font-family:/f/DejaVuSans"', $svg);
	pg_assert_same('<svg font-family="/f/DejaVuSans"></svg>', pg_shipment_label_svg_fonts('<svg font-family="sans-serif"></svg>', '/f'), 'the root keeps its own');
}

// A request's return label: only on an approved request, one active label
// per request (a second press finds it and never calls the service again),
// a label of another request on the same address does not stop it, and the
// driver is the one that ships with the approval's carrier.
function test_shipment_label_return_plan_one_label_per_request()
{
	$drivers = array('first' => array('yurtici'), 'second' => array('aras'));
	$return = array('id' => 12, 'status' => 'approved', 'carrier' => 'aras');

	pg_assert_same(array('driver' => 'second', 'error' => '', 'label_id' => 0), pg_shipment_label_return_plan($return, array(), $drivers), 'the driver of the carrier');

	$own = array('id' => 40, 'order_return_id' => 12, 'is_return' => 1, 'state' => 'active');
	$plan = pg_shipment_label_return_plan($return, array($own), $drivers);

	pg_assert_same('', $plan['driver'], 'no second paid call');
	pg_assert_same(40, $plan['label_id'], 'the active label is named');
	pg_assert_true($plan['error'] !== '', 'said why');

	$others = array(
		array('id' => 41, 'order_return_id' => 13, 'is_return' => 1, 'state' => 'active'),
		array('id' => 42, 'order_return_id' => 0, 'is_return' => 1, 'state' => 'active'),
		array('id' => 43, 'order_return_id' => 12, 'is_return' => 1, 'state' => 'cancelled'),
		array('id' => 44, 'order_return_id' => 0, 'is_return' => 0, 'state' => 'active'),
	);

	pg_assert_same('second', pg_shipment_label_return_plan($return, $others, $drivers)['driver'], 'another request, an order-screen label or a cancelled one does not stop it');

	$requested = pg_shipment_label_return_plan(array('id' => 12, 'status' => 'requested', 'carrier' => 'aras'), array(), $drivers);
	pg_assert_same('', $requested['driver'], 'not before approval');
	pg_assert_true($requested['error'] !== '', 'said why before approval');
}

// No driver reaches the carrier: no call, and staff are told why.
function test_shipment_label_return_plan_without_a_driver()
{
	$return = array('id' => 12, 'status' => 'approved', 'carrier' => 'ups');
	$plan = pg_shipment_label_return_plan($return, array(), array('first' => array('yurtici')));

	pg_assert_same('', $plan['driver'], 'never another carrier');
	pg_assert_contains('by hand', $plan['error'], 'the approval goes on by hand');

	$none = pg_shipment_label_return_plan(array('id' => 12, 'status' => 'approved', 'carrier' => ''), array(), array());
	pg_assert_same('', $none['driver'], 'no driver on');
	pg_assert_true($none['error'] !== '', 'said why without a driver');

	pg_assert_same('first', pg_shipment_label_return_plan(array('id' => 12, 'status' => 'approved', 'carrier' => ''), array(), array('first' => array('yurtici')))['driver'], 'no carrier named: the service picks');
}

// The code the customer gives the carrier: the barcode, else the number.
function test_shipment_label_return_code()
{
	pg_assert_same('BC1', pg_shipment_label_return_code(array('barcode' => 'BC1', 'number' => 'N1')), 'barcode');
	pg_assert_same('N1', pg_shipment_label_return_code(array('barcode' => '', 'number' => 'N1')), 'number');
}

// A return label never becomes one of the address's parcels, whose delivery
// would announce the order delivered; a request's row carries its id.
function test_shipment_label_return_is_never_a_delivery_parcel()
{
	shipment_labels_test_register();
	$answer = pg_label_call('pgtestlabel', 'create', array(array('order_number' => '1003', 'return' => true), array()));

	$outbound = pg_shipment_label_row(7, 9, 'pgtestlabel', $answer, '', 3, 1700000000);
	$order_screen = pg_shipment_label_row(7, 9, 'pgtestlabel', $answer, '', 3, 1700000000, true);
	$request = pg_shipment_label_row(7, 9, 'pgtestlabel', $answer, '', 3, 1700000000, true, 12);

	pg_assert_true(pg_shipment_label_records_parcel($outbound), 'an outbound label is a parcel');
	pg_assert_false(pg_shipment_label_records_parcel($order_screen), 'a return label is not');
	pg_assert_false(pg_shipment_label_records_parcel($request), 'a request label is not');
	pg_assert_same(12, $request['order_return_id'], 'the request is named');
	pg_assert_false(array_key_exists('order_return_id', $order_screen), 'an order-screen row writes no request');
}

// The customer is handed the newest active label of their request; a
// cancelled one never.
function test_shipment_label_customer_pick()
{
	$new = array('id' => 9, 'state' => 'cancelled', 'is_return' => 1, 'order_return_id' => 4);
	$old = array('id' => 7, 'state' => 'active', 'is_return' => 1, 'order_return_id' => 4);

	pg_assert_same(7, pg_shipment_label_customer_pick(array($new, $old))['id'], 'the active one under a cancelled one');
	pg_assert_same(null, pg_shipment_label_customer_pick(array($new)), 'cancelled: none');
	pg_assert_same(null, pg_shipment_label_customer_pick(array()), 'no label: none');
	pg_assert_same(null, pg_shipment_label_customer_pick('nothing'), 'not a list');
}

// The link is offered while the goods are to go back, and only with a label.
function test_shipment_label_customer_offered()
{
	pg_assert_true(pg_shipment_label_customer_offered('approved', true), 'approved');
	pg_assert_true(pg_shipment_label_customer_offered('shipped', true), 'on its way: printed again');
	pg_assert_false(pg_shipment_label_customer_offered('approved', false), 'no active label');

	foreach (array('requested', 'rejected', 'received', 'refunded', 'closed', '') as $status) {
		pg_assert_false(pg_shipment_label_customer_offered($status, true), $status);
	}
}

// Only the order's signed-in owner, under the returns' rate limit, for a
// return of that order that offers a label.
function test_shipment_label_customer_refusal()
{
	$order = array('id' => 30, 'user_id' => 5);
	$return = array('id' => 4, 'order_id' => 30, 'status' => 'approved');
	$label = array('id' => 7, 'state' => 'active', 'order_return_id' => 4);

	pg_assert_same(null, pg_shipment_label_customer_refusal(5, $order, $return, $label, false), 'the owner gets it');

	$other = pg_shipment_label_customer_refusal(6, $order, $return, $label, false);
	pg_assert_same(403, $other['status'], 'another customer');
	pg_assert_same($other, pg_shipment_label_customer_refusal(6, null, null, null, false), 'the same answer whether or not the order exists');
	pg_assert_same(403, pg_shipment_label_customer_refusal(0, array('id' => 31, 'user_id' => 0), $return, $label, false)['status'], 'signed out, a guest order');

	pg_assert_same(429, pg_shipment_label_customer_refusal(5, $order, $return, $label, true)['status'], 'rate limit hit');
	pg_assert_same(403, pg_shipment_label_customer_refusal(6, $order, $return, $label, true)['status'], 'the owner check comes first');

	pg_assert_same(404, pg_shipment_label_customer_refusal(5, $order, array('id' => 4, 'order_id' => 31, 'status' => 'approved'), $label, false)['status'], 'a return of another order');
	pg_assert_same(404, pg_shipment_label_customer_refusal(5, $order, null, $label, false)['status'], 'no such return');
	pg_assert_same(404, pg_shipment_label_customer_refusal(5, $order, $return, null, false)['status'], 'no active label');
	pg_assert_same(404, pg_shipment_label_customer_refusal(5, $order, $return, array('id' => 7, 'state' => 'cancelled', 'order_return_id' => 4), false)['status'], 'a cancelled label');
	pg_assert_same(404, pg_shipment_label_customer_refusal(5, $order, $return, array('id' => 8, 'state' => 'active', 'order_return_id' => 3), false)['status'], 'a label of another request');
	pg_assert_same(404, pg_shipment_label_customer_refusal(5, $order, array_merge($return, array('status' => 'refunded')), $label, false)['status'], 'a request past sending');
}

// The customer's download: a PDF as is, an SVG only as a sandboxed
// attachment (never the print page), a ZPL file saved.
function test_shipment_label_customer_headers()
{
	$pdf = pg_shipment_label_customer_headers('pdf', 'label-R1');
	pg_assert_contains('Content-Type: application/pdf', $pdf);
	pg_assert_contains('Content-Disposition: inline; filename="label-R1.pdf"', $pdf);
	pg_assert_contains('X-Content-Type-Options: nosniff', $pdf);
	pg_assert_contains('Cache-Control: private, no-store', $pdf);

	$svg = pg_shipment_label_customer_headers('svg', 'label-R1');
	pg_assert_contains('Content-Type: image/svg+xml', $svg);
	pg_assert_contains('Content-Disposition: attachment; filename="label-R1.svg"', $svg);
	pg_assert_contains('Content-Security-Policy: sandbox', $svg);
	pg_assert_false(in_array('Content-Type: text/html; charset=utf-8', $svg, true), 'never the print page');

	$zpl = pg_shipment_label_customer_headers('zpl', 'label-R1');
	pg_assert_contains('Content-Type: application/octet-stream', $zpl);
	pg_assert_contains('Content-Disposition: attachment; filename="label-R1.zpl"', $zpl);
}

// A package whose marketplace makes its label for its own carrier now gets no
// carrier label service's label: named after an active label, before a
// missing driver.
function test_shipment_label_create_refusal_marketplace_label()
{
	$order = array('status' => 'complete');
	$refusal = pg_shipment_label_create_refusal(true, $order, 0, 'geliver', false, 'Trendyol');

	pg_assert_same('marketplace_label', $refusal['code']);
	pg_assert_contains('Trendyol', $refusal['error']);
	pg_assert_same('label_exists', pg_shipment_label_create_refusal(true, $order, 1, 'geliver', false, 'Trendyol')['code'], 'the label kept first');
	pg_assert_same('marketplace_label', pg_shipment_label_create_refusal(true, $order, 0, '', false, 'Trendyol')['code'], 'before a missing driver');
	pg_assert_same(array('code' => '', 'error' => ''), pg_shipment_label_create_refusal(true, $order, 0, 'geliver', false, ''));
	pg_assert_same(array('code' => '', 'error' => ''), pg_shipment_label_create_refusal(true, $order, 0, 'geliver'), 'none named: as before');
}

// A package the marketplace carries on its own carrier but whose label it
// does not make (another carrier, or no longer open) gets no carrier label
// service's label either: that would be a second, paid shipment.
function test_shipment_label_create_refusal_marketplace_carried()
{
	$order = array('status' => 'complete');
	$refusal = pg_shipment_label_create_refusal(true, $order, 0, 'geliver', false, '', 'Trendyol');

	pg_assert_same('marketplace_carried', $refusal['code']);
	pg_assert_contains('Trendyol', $refusal['error']);
	pg_assert_same('marketplace_carried', pg_shipment_label_create_refusal(true, $order, 0, '', false, '', 'Trendyol')['code'], 'before a missing driver');
	pg_assert_same('label_exists', pg_shipment_label_create_refusal(true, $order, 1, 'geliver', false, '', 'Trendyol')['code'], 'the label kept first');
	pg_assert_same('marketplace_label', pg_shipment_label_create_refusal(true, $order, 0, 'geliver', false, 'Trendyol', '')['code'], 'covered: the common label');
	pg_assert_same(array('code' => '', 'error' => ''), pg_shipment_label_create_refusal(true, $order, 0, 'geliver', false, '', ''), 'ships itself or no marketplace');
}

// The orders list's bulk buttons show for a carrier label service, or for a
// marketplace account whose label is fetched; Request Pickup keeps its own.
function test_shipment_label_bulk_buttons_gate()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/view_orders.php');

	pg_assert_contains('if (pg_shipment_labels_ready() && (pg_shipment_label_drivers() || pg_shipment_label_marketplace_offers())) {', $source);
	pg_assert_contains("if (\$output_shipment_label_buttons !== '' && pg_shipment_label_pickup_drivers()) {", $source);
	pg_assert_contains('A Trendyol common label is fetched for every qualifying package of the selected orders that has no label yet, 20 at a time. Continue?', $source);
}

// The name a label's maker is shown by.
function test_shipment_label_driver_name()
{
	pg_assert_same('Geliver', pg_shipment_label_driver_name('geliver'));
	pg_assert_same('no_such_maker', pg_shipment_label_driver_name('no_such_maker'));
	pg_assert_same('../x', pg_shipment_label_driver_name('../x'), 'not a key: shown as it is, nothing loaded for it');
}

// A bulk .zpl keeps the selection's order, one newline between labels and
// one at the end; no label gives an empty file.
function test_shipment_label_zpl_join()
{
	pg_assert_same("^XA^FDone^XZ\n^XA^FDtwo^XZ\n^XA^FDthree^XZ\n", pg_shipment_label_zpl_join(array("^XA^FDone^XZ\r\n\n", "^XA^FDtwo^XZ", "^XA^FDthree^XZ \n")), 'order kept, one newline between and after');
	pg_assert_same('', pg_shipment_label_zpl_join(array()), 'an empty list is an empty file');
}

// Only active outbound labels whose document is readable ZPL go into the
// bulk .zpl.
function test_shipment_label_bulk_zpl_filter()
{
	$rows = array(
		array('id' => 1, 'order_id' => 10, 'ship_to_id' => 1, 'state' => 'active', 'is_return' => 0, 'label_format' => 'zpl'),
		array('id' => 2, 'order_id' => 10, 'ship_to_id' => 1, 'state' => 'cancelled', 'is_return' => 0, 'label_format' => 'zpl'),
		array('id' => 3, 'order_id' => 10, 'ship_to_id' => 2, 'state' => 'active', 'is_return' => 1, 'label_format' => 'zpl'),
		array('id' => 4, 'order_id' => 10, 'ship_to_id' => 3, 'state' => 'active', 'is_return' => 0, 'label_format' => 'pdf'),
		array('id' => 5, 'order_id' => 10, 'ship_to_id' => 4, 'state' => 'active', 'is_return' => 0, 'label_format' => 'zpl'),
		array('id' => 6, 'order_id' => 10, 'ship_to_id' => 5, 'state' => 'active', 'is_return' => 0, 'label_format' => 'zpl'),
	);
	$documents = array(
		1 => array('ok' => true, 'document' => '^XA^FD1^XZ', 'format' => 'zpl'),
		2 => array('ok' => true, 'document' => '^XA^FD2^XZ', 'format' => 'zpl'),
		3 => array('ok' => true, 'document' => '^XA^FD3^XZ', 'format' => 'zpl'),
		4 => array('ok' => true, 'document' => '%PDF-1.4', 'format' => 'pdf'),
		5 => array('ok' => true, 'document' => 'not a label', 'format' => 'zpl'),
		6 => array('ok' => false, 'document' => '', 'format' => ''),
	);
	$kept = array();

	foreach (pg_shipment_label_print_selection($rows, array(10)) as $row) {
		if (pg_shipment_label_bulk_zpl_readable($documents[$row['id']])) {
			$kept[] = $row['id'];
		}
	}

	pg_assert_same(array(1), $kept, 'only an active outbound label with readable ZPL');
}

// The bulk PDF's last page counts the ZPL labels of the .zpl download and
// lists only the labels that are in neither file.
function test_shipment_label_bulk_note()
{
	$unreadable = array('order_number' => '1001', 'number' => 'AB12', 'label_format' => 'zpl');
	$note = pg_shipment_label_bulk_note(array($unreadable), 2);

	pg_assert_true(strpos($note, 'Download ZPL Labels') !== false, 'the ZPL count names the download');
	pg_assert_true(strpos($note, 'AB12') !== false, 'an unreadable label is listed');

	$only_zpl = pg_shipment_label_bulk_note(array(), 3);

	pg_assert_true(strpos($only_zpl, '<li>') === false, 'a readable ZPL label is not listed');
	pg_assert_true(strpos($only_zpl, 'Labels not in this PDF') === false, 'no list heading without a left-out label');
	pg_assert_same('', pg_shipment_label_bulk_note(array(), 0), 'nothing to say, no page');
}
