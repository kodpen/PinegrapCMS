<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for pick and pack's pure rules (includes/fn/pick_pack.php): the
 * shelf sort, the wave rule's filter, a cancelled order leaving its wave,
 * the pick list totals, the scan verdict and the pack state.
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

function pick_pack_test_bins(array $lines)
{
	$out = array();

	foreach ($lines as $line) {
		$out[] = $line['bin'] . '|' . $line['code'];
	}

	return $out;
}

function test_pick_pack_bin_numbers_compare_as_numbers()
{
	pg_assert_true(pg_pick_bin_compare('A-3', 'A-10') < 0, '"A-3" before "A-10"');
	pg_assert_true(pg_pick_bin_compare('A-10', 'A-3') > 0, '"A-10" after "A-3"');
	pg_assert_true(pg_pick_bin_compare('A-03-2', 'A-3-10') < 0, 'a leading zero is the same number');
	pg_assert_true(pg_pick_bin_compare('a-2', 'B-1') < 0, 'letters compare without case');
	pg_assert_true(pg_pick_bin_compare('A-2', 'A-2-1') < 0, 'a shorter address first');
	pg_assert_true(pg_pick_bin_compare('A-99999999999999999999', 'A-100000000000000000000') < 0, 'long numbers without overflow');
	pg_assert_same(0, pg_pick_bin_compare('A-3', 'A-3'), 'same address');
}

function test_pick_pack_sort_puts_no_address_last_by_code()
{
	$lines = array(
		array('product_id' => 5, 'bin' => '', 'code' => 'SKU-20'),
		array('product_id' => 1, 'bin' => 'A-10', 'code' => 'X'),
		array('product_id' => 2, 'bin' => ' ', 'code' => 'SKU-3'),
		array('product_id' => 3, 'bin' => 'A-3', 'code' => 'Y'),
		array('product_id' => 4, 'bin' => 'B-1', 'code' => 'Z'),
	);

	pg_assert_same(
		array('A-3|Y', 'A-10|X', 'B-1|Z', ' |SKU-3', '|SKU-20'),
		pick_pack_test_bins(pg_pick_sort_lines($lines)),
		'shelf order, no address last by product code'
	);
}

function pick_pack_test_address(array $over = array())
{
	return array_merge(array(
		'order_id' => 10,
		'ship_to_id' => 100,
		'status' => 'complete',
		'paid' => true,
		'packed' => false,
		'in_wave' => false,
		'carrier' => 'yurtici',
		'marketplace' => '',
		'location_id' => 7,
		'ship_by' => mktime(0, 0, 0, 11, 20, 2026),
		'units' => 2,
	), $over);
}

function test_pick_pack_wave_rule_filter()
{
	$rule = array();
	pg_assert_true(pg_pick_wave_rule_match(pick_pack_test_address(), $rule), 'a paid address matches an empty rule');
	pg_assert_false(pg_pick_wave_rule_match(pick_pack_test_address(array('paid' => false)), $rule), 'not paid');
	pg_assert_false(pg_pick_wave_rule_match(pick_pack_test_address(array('status' => 'cancelled')), $rule), 'cancelled');
	pg_assert_false(pg_pick_wave_rule_match(pick_pack_test_address(array('packed' => true)), $rule), 'packed already');
	pg_assert_false(pg_pick_wave_rule_match(pick_pack_test_address(array('in_wave' => true)), $rule), 'in another open wave');

	pg_assert_false(pg_pick_wave_rule_match(pick_pack_test_address(), array('carrier' => 'aras')), 'another carrier');
	pg_assert_false(pg_pick_wave_rule_match(pick_pack_test_address(), array('marketplace' => 'trendyol')), 'not from the marketplace');
	pg_assert_false(pg_pick_wave_rule_match(pick_pack_test_address(), array('location_id' => 3)), 'another location');
	pg_assert_false(pg_pick_wave_rule_match(pick_pack_test_address(), array('single_item' => true)), 'two units is not single item');
	pg_assert_true(pg_pick_wave_rule_match(pick_pack_test_address(array('units' => 1)), array('single_item' => true)), 'one unit is single item');
	pg_assert_false(
		pg_pick_wave_rule_match(pick_pack_test_address(), array('ship_by_until' => mktime(0, 0, 0, 11, 19, 2026))),
		'due after the day'
	);
	pg_assert_true(
		pg_pick_wave_rule_match(pick_pack_test_address(), array('ship_by_until' => mktime(0, 0, 0, 11, 20, 2026), 'carrier' => 'yurtici', 'location_id' => 7)),
		'every filter met'
	);
}

function test_pick_pack_wave_rule_fills_oldest_first_up_to_the_limit()
{
	$addresses = array(
		pick_pack_test_address(array('ship_to_id' => 1, 'order_id' => 1, 'ship_by' => 0)),
		pick_pack_test_address(array('ship_to_id' => 2, 'order_id' => 2, 'ship_by' => 300)),
		pick_pack_test_address(array('ship_to_id' => 3, 'order_id' => 3, 'ship_by' => 100, 'status' => 'cancelled')),
		pick_pack_test_address(array('ship_to_id' => 4, 'order_id' => 4, 'ship_by' => 200)),
		pick_pack_test_address(array('ship_to_id' => 5, 'order_id' => 5, 'ship_by' => 200)),
	);

	$ids = array_map(function ($a) {
		return $a['ship_to_id'];
	}, pg_pick_wave_rule_filter($addresses, array()));
	pg_assert_same(array(4, 5, 2, 1), $ids, 'oldest ship-by first, unknown last, cancelled out');

	pg_assert_same(2, count(pg_pick_wave_rule_filter($addresses, array(), 2)), 'the limit holds');
	pg_assert_same(200, PG_PICK_WAVE_MAX, 'a wave holds 200 addresses');
}

function test_pick_pack_ship_by_day()
{
	$paid = mktime(15, 30, 0, 11, 20, 2026);
	pg_assert_same(mktime(0, 0, 0, 11, 22, 2026), pg_pick_ship_by(0, $paid, 2), 'paid day plus handling days');
	pg_assert_same(mktime(0, 0, 0, 11, 25, 2026), pg_pick_ship_by(mktime(9, 0, 0, 11, 25, 2026), $paid, 2), 'the ship date wins');
	pg_assert_same(0, pg_pick_ship_by(0, 0, 2), 'not paid, no day');
}

function test_pick_pack_cancelled_order_leaves_its_wave()
{
	$rows = array(
		array('ship_to_id' => 1, 'removed_at' => 0, 'status' => 'complete'),
		array('ship_to_id' => 2, 'removed_at' => 0, 'status' => 'cancelled'),
		array('ship_to_id' => 3, 'removed_at' => 1700000000, 'status' => 'cancelled'),
	);

	pg_assert_same(array(2), pg_pick_wave_leavers($rows), 'only the newly cancelled one leaves');
}

function test_pick_pack_list_totals()
{
	$items = array(
		array('product_id' => 9, 'location_id' => 7, 'ship_to_id' => 1, 'quantity' => 2, 'bin' => 'A-10', 'code' => 'P9', 'name' => 'Mug'),
		array('product_id' => 9, 'location_id' => 7, 'ship_to_id' => 2, 'quantity' => 1, 'bin' => 'A-10', 'code' => 'P9', 'name' => 'Mug'),
		array('product_id' => 9, 'location_id' => 7, 'ship_to_id' => 2, 'quantity' => 3, 'bin' => 'A-10', 'code' => 'P9', 'name' => 'Mug'),
		array('product_id' => 9, 'location_id' => 3, 'ship_to_id' => 4, 'quantity' => 1, 'bin' => 'C-1', 'code' => 'P9', 'name' => 'Mug'),
		array('product_id' => 4, 'location_id' => 7, 'ship_to_id' => 1, 'quantity' => 1, 'bin' => 'A-3', 'code' => 'P4', 'name' => 'Cup'),
		array('product_id' => 6, 'location_id' => 7, 'ship_to_id' => 1, 'quantity' => 0, 'bin' => 'A-1', 'code' => 'P6', 'name' => 'None'),
	);

	$list = pg_pick_list_totals($items);

	pg_assert_same(array('A-3|P4', 'A-10|P9', 'C-1|P9'), pick_pack_test_bins($list), 'shelf order, per product and location');
	pg_assert_same(6, $list[1]['units'], 'units summed');
	pg_assert_same(2, $list[1]['addresses'], 'addresses counted once each');
	pg_assert_same(1, $list[2]['units'], 'another location is its own line');
}

function pick_pack_test_lines()
{
	return array(
		array('product_id' => 21, 'quantity' => 2, 'scanned' => 0, 'group_id' => 5, 'options' => array('Size' => 'L')),
		array('product_id' => 30, 'quantity' => 1, 'scanned' => 0, 'group_id' => 0, 'options' => array()),
	);
}

function test_pick_pack_scan_verdicts()
{
	$lines = pick_pack_test_lines();

	pg_assert_same(array('verdict' => 'good', 'line' => 0), pg_pack_scan_verdict($lines, array('id' => 21, 'group_id' => 5)), 'good');
	pg_assert_same('not_found', pg_pack_scan_verdict($lines, null)['verdict'], 'no product for the code');
	pg_assert_same('binned', pg_pack_scan_verdict($lines, array('id' => 99, 'binned' => 1))['verdict'], 'a binned product not on the address is refused');
	pg_assert_same('wrong_product', pg_pack_scan_verdict($lines, array('id' => 99, 'group_id' => 8))['verdict'], 'not on the address');
	pg_assert_same('wrong_product', pg_pack_scan_verdict($lines, array('id' => 99, 'group_id' => 0))['verdict'], 'no group is no variant');
}

// A product moved to the Recycle Bin after the order was paid is still
// sent: its units count, and once they are in the box it is too many.
function test_pick_pack_binned_product_on_the_order_counts()
{
	$lines = pick_pack_test_lines();

	pg_assert_same(array('verdict' => 'good', 'line' => 1), pg_pack_scan_verdict($lines, array('id' => 30, 'binned' => 1)), 'a binned product the address wants counts');

	$lines[1]['scanned'] = 1;
	pg_assert_same('too_many', pg_pack_scan_verdict($lines, array('id' => 30, 'binned' => 1))['verdict'], 'a binned product already in the box is too many');
	pg_assert_same('binned', pg_pack_scan_verdict($lines, array('id' => 22, 'group_id' => 5, 'binned' => 1))['verdict'], 'a binned variant of a wanted product reads binned');
}

function test_pick_pack_scan_names_the_variant()
{
	$verdict = pg_pack_scan_verdict(pick_pack_test_lines(), array('id' => 22, 'group_id' => 5, 'options' => array('Size' => 'M')));

	pg_assert_same('wrong_variant', $verdict['verdict'], 'another size of the group');
	pg_assert_same(0, $verdict['line'], 'the wanted line');
	pg_assert_same(array('Size' => 'M'), $verdict['options'], 'what was scanned');
	pg_assert_same(array('Size' => 'L'), $verdict['wanted_options'], 'what the order wants');
}

function test_pick_pack_unit_past_the_line_is_refused()
{
	$pack = array('lines' => pick_pack_test_lines(), 'scanned' => 0, 'refused' => 0);
	$mug = array('id' => 21, 'group_id' => 5);

	for ($i = 0; $i < 2; $i++) {
		$pack = pg_pack_apply_scan($pack, pg_pack_scan_verdict($pack['lines'], $mug));
	}

	pg_assert_same(2, $pack['lines'][0]['scanned'], 'two units counted');
	$verdict = pg_pack_scan_verdict($pack['lines'], $mug);
	pg_assert_same('too_many', $verdict['verdict'], 'the third unit is past the line');

	$pack = pg_pack_apply_scan($pack, $verdict);
	pg_assert_same(2, $pack['lines'][0]['scanned'], 'the line keeps two');
	pg_assert_same(2, $pack['scanned'], 'good scans');
	pg_assert_same(1, $pack['refused'], 'the refusal is counted');
}

function test_pick_pack_short_pack()
{
	$lines = pick_pack_test_lines();
	$lines[0]['scanned'] = 1;

	$open = pg_pack_state($lines);
	pg_assert_same(PG_PACK_OPEN, $open['status'], 'missing units keep it open');
	pg_assert_same(3, $open['units'], 'units wanted');
	pg_assert_same(1, $open['scanned'], 'units scanned');

	$short = pg_pack_state($lines, true);
	pg_assert_same(PG_PACK_SHORT, $short['status'], 'closed as short');
	pg_assert_same(array(21 => 1, 30 => 1), $short['missing'], 'the missing lines by product id');

	$lines[0]['scanned'] = 2;
	$lines[1]['scanned'] = 1;
	$done = pg_pack_state($lines, true);
	pg_assert_same(PG_PACK_PACKED, $done['status'], 'all scanned is packed, short or not');
	pg_assert_same(array(), $done['missing'], 'nothing missing');

	pg_assert_same(PG_PACK_OPEN, pg_pack_state(array(), true)['status'], 'an empty pack never closes');
}
