<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Pack screen's pure parts (includes/fn/pick_pack.php): the
 * pack's lines built from the order lines with the stored scans kept, what
 * the pack row stores, the order code read from a scan, the marketplace
 * package accepted on a checked pack, and the order.packed data.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/pick_pack.php');

function test_pack_lines_sum_one_product_and_keep_scans()
{
	$items = array(
		array('product_id' => 4, 'quantity' => 1, 'name' => 'Shirt', 'options' => array('Size: M')),
		array('product_id' => 9, 'quantity' => 2, 'name' => 'Cap'),
		array('product_id' => 4, 'quantity' => 2, 'name' => 'Shirt'),
		array('product_id' => 0, 'quantity' => 1, 'name' => 'Gone'),
		array('product_id' => 5, 'quantity' => 0, 'name' => 'Nothing to send'),
	);
	$stored = array(array('product_id' => 4, 'scanned' => 2), array('product_id' => 9, 'scanned' => 7), 'junk');

	$lines = pg_pack_lines_build($items, $stored);

	pg_assert_same(3, count($lines), 'one line per product, a gone product on its own, a zero line skipped');
	pg_assert_same(3, $lines[0]['quantity'], 'the units of one product summed');
	pg_assert_same(2, $lines[0]['scanned'], 'the stored scans kept');
	pg_assert_same(array('Size: M'), $lines[0]['options'], 'the first line names the options');
	pg_assert_same(2, $lines[1]['scanned'], 'never more scans than units');
	pg_assert_same(0, $lines[2]['product_id'], 'a gone product cannot be scanned');

	pg_assert_same(
		array(array('product_id' => 4, 'quantity' => 3, 'scanned' => 2), array('product_id' => 9, 'quantity' => 2, 'scanned' => 2), array('product_id' => 0, 'quantity' => 1, 'scanned' => 0)),
		pg_pack_scan_lines_store($lines),
		'the row keeps product, units and scans only'
	);

	pg_assert_same(0, pg_pack_lines_build($items, null)[0]['scanned'], 'a new pack starts at nothing scanned');
}

function test_pack_scans_close_the_pack()
{
	$pack = array('lines' => pg_pack_lines_build(array(array('product_id' => 4, 'quantity' => 2)), null), 'scanned' => 0, 'refused' => 0);

	foreach (array(4, 8, 4) as $id) {
		$pack = pg_pack_apply_scan($pack, pg_pack_scan_verdict($pack['lines'], array('id' => $id)));
	}

	$state = pg_pack_state($pack['lines']);

	pg_assert_same(PG_PACK_PACKED, $state['status'], 'every unit scanned packs it');
	pg_assert_same(1, $pack['refused'], 'the wrong product counted as refused');
}

function test_pack_order_code_from_a_scan()
{
	pg_assert_same('100318', pg_pack_order_code(" 100318\n"), 'blanks dropped');
	pg_assert_same('100318', pg_pack_order_code('#100318'), 'a typed # dropped');
	pg_assert_same('', pg_pack_order_code(array('1')), 'an array is no code');
	pg_assert_same('', pg_pack_order_code(str_repeat('1', 101)), 'too long is no code');
}

function test_pack_marketplace_package_accepted_only_while_created()
{
	pg_assert_true(pg_pack_marketplace_acceptable(array('remote_status' => 'Created', 'approved' => 0)), 'a Created package is accepted');
	pg_assert_false(pg_pack_marketplace_acceptable(array('remote_status' => 'Created', 'approved' => 1)), 'one accepted already is left');
	pg_assert_false(pg_pack_marketplace_acceptable(array('remote_status' => 'Picking', 'approved' => 0)), 'one moved on is left');
}

function test_pack_event_payload_names_no_customer()
{
	$data = pg_pack_event_payload(array('order_id' => 3, 'ship_to_id' => 5, 'wave_id' => 2, 'packer_id' => 7, 'units' => 3, 'scanned' => 2, 'missing' => array(9 => 1), 'email' => 'x@example.com'), '1001');

	pg_assert_same(
		array('id' => 3, 'order_number' => '1001', 'ship_to_id' => 5, 'wave_id' => 2, 'packer_id' => 7, 'units' => 3, 'scanned' => 2, 'missing' => array(array('product_id' => 9, 'units' => 1))),
		$data,
		'order, address, wave, packer, units, missing lines; nothing else'
	);
}
