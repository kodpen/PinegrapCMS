<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for includes/fn/shipping_import.php: reading the tracking-number CSV
 * (delimiter, header, byte order mark, blank lines, the row limit), the row
 * check, the list an address is sent (its own numbers plus the new ones,
 * since update_order() replaces the list), and the bulk "mark shipped"
 * request and its skip rule. The writes themselves go through update_order()
 * and are checked on a sandbox.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/shipping_import.php');

// A CSV string as an open stream, the way the screen hands over the upload.
function shipping_import_test_stream($text)
{
	$handle = fopen('php://memory', 'w+');
	fwrite($handle, $text);
	rewind($handle);

	return $handle;
}

// A comma, a semicolon (Excel in a Turkish locale) or a tab separates columns.
function test_shipping_import_delimiter()
{
	pg_assert_same(',', pg_shipping_import_delimiter('order_number,number,carrier'));
	pg_assert_same(';', pg_shipping_import_delimiter('order_number;number;carrier'));
	pg_assert_same("\t", pg_shipping_import_delimiter("1001\t123456789\taras"));
	pg_assert_same(',', pg_shipping_import_delimiter('1001'));
}

// The header row is skipped, the byte order mark removed, blank lines ignored
// and every cell trimmed; line numbers count the file's own lines.
function test_shipping_import_read_rows()
{
	$handle = shipping_import_test_stream("\xEF\xBB\xBFOrder_Number,number,carrier\n 1001 , 123456789 ,Aras Kargo\n\n1002,1Z999AA10123456784\n");

	$read = pg_shipping_import_read($handle, 100);

	pg_assert_false($read['more']);
	pg_assert_same(2, count($read['rows']));
	pg_assert_same(array('line' => 2, 'order_number' => '1001', 'number' => '123456789', 'carrier' => 'Aras Kargo'), $read['rows'][0]);
	pg_assert_same(array('line' => 4, 'order_number' => '1002', 'number' => '1Z999AA10123456784', 'carrier' => ''), $read['rows'][1]);
}

// A file without a header starts on its first line; semicolons work too.
function test_shipping_import_read_without_header()
{
	$read = pg_shipping_import_read(shipping_import_test_stream("1001;555666777;mng\r\n"), 100);

	pg_assert_same(1, count($read['rows']));
	pg_assert_same(1, $read['rows'][0]['line']);
	pg_assert_same('mng', $read['rows'][0]['carrier']);
}

// Past the limit reading stops and says there was more.
function test_shipping_import_read_limit()
{
	$read = pg_shipping_import_read(shipping_import_test_stream("1,111111\n2,222222\n3,333333\n"), 2);

	pg_assert_true($read['more']);
	pg_assert_same(2, count($read['rows']));
}

// A row needs an order number and a tracking number; a named carrier must be
// one the store knows. A number of the wrong shape is not refused here.
function test_shipping_import_row_error()
{
	$row = array('line' => 1, 'order_number' => '1001', 'number' => '123456789', 'carrier' => '');

	pg_assert_same('', pg_shipping_import_row_error($row));
	pg_assert_same('', pg_shipping_import_row_error(array('carrier' => 'Yurtiçi Kargo') + $row));
	pg_assert_same('', pg_shipping_import_row_error(array('number' => 'x') + $row));
	pg_assert_true(pg_shipping_import_row_error(array('order_number' => '') + $row) !== '');
	pg_assert_true(pg_shipping_import_row_error(array('number' => '') + $row) !== '');
	pg_assert_true(pg_shipping_import_row_error(array('number' => str_repeat('9', 101)) + $row) !== '');
	pg_assert_true(pg_shipping_import_row_error(array('carrier' => 'No Such Courier') + $row) !== '');
}

// The address keeps the numbers it has (plain, so each row keeps its own
// carrier) and gains the new ones; one it already has, or one an earlier row
// of the file added, is not added twice.
function test_shipping_import_entries()
{
	$rows = array(
		array('line' => 2, 'order_number' => '1001', 'number' => '222', 'carrier' => 'aras'),
		array('line' => 3, 'order_number' => '1001', 'number' => 'ab-1', 'carrier' => ''),
		array('line' => 4, 'order_number' => '1001', 'number' => '333', 'carrier' => ''),
		array('line' => 5, 'order_number' => '1001', 'number' => '333', 'carrier' => 'mng'),
	);

	$out = pg_shipping_import_entries(array('111', 'AB-1'), $rows);

	pg_assert_same(array('111', 'AB-1', array('number' => '222', 'carrier' => 'aras'), '333'), $out['entries']);
	pg_assert_same(array(2 => 'add', 3 => 'present', 4 => 'add', 5 => 'present'), $out['status']);
}

// The bulk mark tells the customer, dates the address today and sends its
// delivery date back unchanged (update_order() writes the two together), with
// no tracking numbers, so the list on the address is not touched.
function test_shipping_bulk_mark_recipient()
{
	$request = pg_shipping_bulk_mark_recipient(
		array('id' => '7', 'ship_date' => '2026-01-01', 'delivery_date' => '2026-01-05'), '2026-10-26');

	pg_assert_same(array(
		'id'            => 7,
		'shipped'       => true,
		'ship_date'     => '2026-10-26',
		'delivery_date' => '2026-01-05',
		'items'         => array(),
	), $request);
	pg_assert_false(array_key_exists('tracking_numbers', $request));
}

// Which orders the bulk mark leaves alone, and why.
function test_shipping_bulk_mark_skip()
{
	pg_assert_same('', pg_shipping_bulk_mark_skip('complete', 1, false));
	pg_assert_same('', pg_shipping_bulk_mark_skip('exported', 2, false));
	pg_assert_same('cancelled', pg_shipping_bulk_mark_skip('cancelled', 1, false));
	pg_assert_same('incomplete', pg_shipping_bulk_mark_skip('incomplete', 1, false));
	pg_assert_same('no_address', pg_shipping_bulk_mark_skip('complete', 0, false));
	pg_assert_same('shipped', pg_shipping_bulk_mark_skip('complete', 1, true));
}
