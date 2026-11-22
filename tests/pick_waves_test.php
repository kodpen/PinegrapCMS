<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the pick wave rules (includes/fn/pick_pack.php): a shipped
 * address stays out of a wave, the rule read from the Make a wave drawer,
 * ticking pick list lines, the wave's status after a tick, the ship-by
 * tone and the address a single-item scan finds.
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

function test_pick_waves_shipped_address_stays_out()
{
	$address = array('order_id' => 1, 'ship_to_id' => 2, 'status' => 'complete', 'paid' => true, 'units' => 1);

	pg_assert_true(pg_pick_wave_rule_match($address, array()), 'a paid address waits');
	pg_assert_false(pg_pick_wave_rule_match($address + array('shipped' => true), array()), 'a shipped address has nothing to pick');
}

function test_pick_waves_rule_from_the_drawer()
{
	$today = mktime(0, 0, 0, 11, 22, 2026);

	$rule = pg_pick_wave_rule_clean(array(
		'carrier' => ' yurtici ',
		'marketplace' => '4',
		'location_id' => '7',
		'ship_by' => 'today',
		'single_item' => '1',
	), $today);

	pg_assert_same('yurtici', $rule['carrier'], 'the carrier trimmed');
	pg_assert_same('4', $rule['marketplace'], 'the marketplace account');
	pg_assert_same(7, $rule['location_id'], 'the location');
	pg_assert_same('today', $rule['ship_by'], 'the ship-by choice kept');
	pg_assert_same($today, $rule['ship_by_until'], 'due today or late');
	pg_assert_true($rule['single_item'], 'single-item addresses only');

	$tomorrow = pg_pick_wave_rule_clean(array('ship_by' => 'tomorrow'), $today);
	pg_assert_same(mktime(0, 0, 0, 11, 23, 2026), $tomorrow['ship_by_until'], 'due by tomorrow');

	$any = pg_pick_wave_rule_clean(array('carrier' => array('x'), 'marketplace' => 'trendyol', 'location_id' => '-3', 'ship_by' => 'later'), $today);
	pg_assert_same(
		array('carrier' => '', 'marketplace' => '', 'location_id' => 0, 'ship_by' => '', 'ship_by_until' => 0, 'single_item' => false),
		$any,
		'unknown values let every address through'
	);
	pg_assert_same(20, strlen(pg_pick_wave_rule_clean(array('carrier' => str_repeat('a', 30)), $today)['carrier']), 'a carrier key is at most 20 characters');
}

function test_pick_waves_tick_lines()
{
	pg_assert_same('12:0', pg_pick_line_key(12, 0), 'a line is its product and location');

	$picked = pg_pick_picked_toggle(array(), '5:1', true);
	$picked = pg_pick_picked_toggle($picked, '3:1', true);
	$picked = pg_pick_picked_toggle($picked, '5:1', true);
	pg_assert_same(array('3:1', '5:1'), $picked, 'ticked once, sorted');
	pg_assert_same(array('5:1'), pg_pick_picked_toggle($picked, '3:1', false), 'untick');
	pg_assert_same(array('3:1', '5:1'), pg_pick_picked_toggle($picked, 'x', true), 'not a line key');

	$lines = array(
		array('product_id' => 1, 'location_id' => 0, 'bin' => 'A-1'),
		array('product_id' => 2, 'location_id' => 0, 'bin' => 'A-2'),
		array('product_id' => 3, 'location_id' => 0, 'bin' => 'A-3'),
	);
	$ticked = pg_pick_list_ticked($lines, array('1:0', '9:0'));

	pg_assert_same(1, $ticked['done'], 'a key of no line is not counted');
	pg_assert_same(3, $ticked['total'], 'every line');
	pg_assert_same(array('A-2', 'A-3', 'A-1'), array_map(function ($line) {
		return $line['bin'];
	}, $ticked['lines']), 'a ticked line sinks to the end, the rest keep shelf order');
	pg_assert_true($ticked['lines'][2]['picked'], 'the ticked line is marked');
	pg_assert_same('2:0', $ticked['lines'][0]['key'], 'each line carries its key');
}

function test_pick_waves_status_after_a_tick()
{
	pg_assert_same(PG_WAVE_PICKED, pg_pick_wave_status_after(PG_WAVE_OPEN, 3, 3), 'every line ticked: picked');
	pg_assert_same(PG_WAVE_OPEN, pg_pick_wave_status_after(PG_WAVE_PICKED, 2, 3), 'a line unticked: open again');
	pg_assert_same(PG_WAVE_OPEN, pg_pick_wave_status_after(PG_WAVE_OPEN, 0, 0), 'an empty list is never picked');
	pg_assert_same(PG_WAVE_CLOSED, pg_pick_wave_status_after(PG_WAVE_CLOSED, 3, 3), 'a closed wave stays closed');
}

function test_pick_waves_ship_by_tone()
{
	$today = mktime(0, 0, 0, 11, 22, 2026);

	pg_assert_same('late', pg_pick_ship_by_tone(mktime(0, 0, 0, 11, 21, 2026), $today), 'yesterday is late');
	pg_assert_same('today', pg_pick_ship_by_tone($today, $today), 'due today');
	pg_assert_same('', pg_pick_ship_by_tone(mktime(0, 0, 0, 11, 23, 2026), $today), 'tomorrow');
	pg_assert_same('', pg_pick_ship_by_tone(0, $today), 'no day known');
}

function test_pick_waves_single_item_scan_finds_the_oldest_address()
{
	$rows = array(
		array('ship_to_id' => 1, 'order_id' => 1, 'product_id' => 5, 'ship_by' => 300, 'done' => false),
		array('ship_to_id' => 2, 'order_id' => 2, 'product_id' => 5, 'ship_by' => 100, 'done' => true),
		array('ship_to_id' => 3, 'order_id' => 4, 'product_id' => 5, 'ship_by' => 200, 'done' => false),
		array('ship_to_id' => 4, 'order_id' => 3, 'product_id' => 5, 'ship_by' => 200, 'done' => false),
		array('ship_to_id' => 5, 'order_id' => 5, 'product_id' => 5, 'ship_by' => 0, 'done' => false),
		array('ship_to_id' => 6, 'order_id' => 6, 'product_id' => 8, 'ship_by' => 50, 'done' => false),
	);

	pg_assert_same(4, pg_pick_single_item_address($rows, 5), 'oldest ship-by day, then the older order, a packed one skipped');
	pg_assert_same(6, pg_pick_single_item_address($rows, 8), 'the address that wants the product');
	pg_assert_same(null, pg_pick_single_item_address($rows, 9), 'no address wants it');

	$late = array(
		array('ship_to_id' => 5, 'order_id' => 5, 'product_id' => 5, 'ship_by' => 0, 'done' => false),
		array('ship_to_id' => 1, 'order_id' => 1, 'product_id' => 5, 'ship_by' => 300, 'done' => false),
	);
	pg_assert_same(1, pg_pick_single_item_address($late, 5), 'an unknown day comes last');
}
