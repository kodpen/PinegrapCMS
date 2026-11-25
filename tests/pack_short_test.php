<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the short pick and the Packing card's pure parts
 * (includes/fn/pick_pack.php): a pack closed short with its missing lines,
 * the stored missing units read back, the drawer's count fixes and the
 * units held by orders waiting to be sent, the missing
 * lines as texts, the week's first day and the card's figures.
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

function test_pack_short_state_keeps_missing_lines()
{
	$lines = array(
		array('product_id' => 4, 'quantity' => 3, 'scanned' => 1),
		array('product_id' => 9, 'quantity' => 1, 'scanned' => 1),
		array('product_id' => 0, 'quantity' => 2, 'scanned' => 0),
	);
	$state = pg_pack_state($lines, true);

	pg_assert_same(PG_PACK_SHORT, $state['status'], 'missing units and short asked');
	pg_assert_same(array(0 => 2, 4 => 2), $state['missing'], 'a gone product is missing too');
	pg_assert_same(PG_PACK_SHORT, pg_pack_state(array(array('product_id' => 4, 'quantity' => 2, 'scanned' => 0)), true)['status'], 'nothing found still closes short');
	pg_assert_same(PG_PACK_OPEN, pg_pack_state(array(), true)['status'], 'an empty pack never closes');
}

function test_pack_missing_decode_reads_both_forms()
{
	pg_assert_same(array(4 => 2, 9 => 1), pg_pack_missing_decode('{"9":1,"4":2}'), 'the stored map');
	pg_assert_same(array(4 => 3), pg_pack_missing_decode(array(array('product_id' => 4, 'units' => 1), array('product_id' => 4, 'units' => 2))), 'the event list, summed');
	pg_assert_same(array(), pg_pack_missing_decode(null), 'none stored');
	pg_assert_same(array(), pg_pack_missing_decode('not json'), 'junk');
	pg_assert_same(array(7 => 1), pg_pack_missing_decode(array('7' => 1, '8' => 0, 'x' => 3, '9' => -2)), 'zero, negative and odd keys left out');
}

function test_pack_count_fixes_only_take_missing_tracked_products()
{
	$missing = array(4 => 2, 9 => 1, 11 => 1);
	$tracked = array(4, 9);

	$fixes = pg_pack_count_fixes(
		array('4' => '1', '9' => '1', '11' => '1', '12' => '1', '5' => '0'),
		array('4' => '0', '9' => '3', '11' => '2', '12' => '5'),
		$missing,
		$tracked
	);

	pg_assert_same(array(4 => 0, 9 => 3), $fixes, 'an untracked or not missing product is left out');
	pg_assert_same(array(), pg_pack_count_fixes(array('4' => '1'), array('4' => '-1'), $missing, $tracked), 'a negative count is refused');
	pg_assert_same(array(), pg_pack_count_fixes(array('4' => '1'), array('4' => '1.5'), $missing, $tracked), 'a broken count is refused');
	pg_assert_same(array(), pg_pack_count_fixes(array('4' => '1'), array(), $missing, $tracked), 'no number typed');
	pg_assert_same(array(), pg_pack_count_fixes(array('4' => '1'), array('4' => (string) (PG_PACK_COUNT_MAX + 1)), $missing, $tracked), 'past the most');
	pg_assert_same(array(), pg_pack_count_fixes('1', '3', $missing, $tracked), 'not a list');
}

function test_pack_missing_list_names_each_line()
{
	pg_assert_same(
		array('A removed product × 1', 'Cap × 2', 'A removed product × 3'),
		pg_pack_missing_list(array(0 => 1, 4 => 2, 7 => 3), array(4 => 'Cap', 7 => ' '), 'A removed product')
	);
}

function test_pack_week_start_is_monday()
{
	$wednesday = mktime(0, 0, 0, 11, 25, 2026);

	pg_assert_same(mktime(0, 0, 0, 11, 23, 2026), pg_pack_week_start($wednesday), 'a Wednesday');
	pg_assert_same(mktime(0, 0, 0, 11, 23, 2026), pg_pack_week_start(mktime(0, 0, 0, 11, 23, 2026)), 'a Monday is its own');
	pg_assert_same(mktime(0, 0, 0, 11, 23, 2026), pg_pack_week_start(mktime(0, 0, 0, 11, 29, 2026)), 'a Sunday');
}

function test_pack_card_figures()
{
	$today = mktime(0, 0, 0, 11, 25, 2026);
	$day = 86400;
	$packs = array(
		// Packer 5: two packs, 6 units in 10 minutes in all, one today.
		array('status' => PG_PACK_PACKED, 'packer_id' => 5, 'started_at' => $today - $day, 'packed_at' => $today - $day + 300, 'units' => 2, 'refused' => 1),
		array('status' => PG_PACK_PACKED, 'packer_id' => 5, 'started_at' => $today + 100, 'packed_at' => $today + 400, 'units' => 4, 'refused' => 0),
		// Packer 6: one pack left open for hours, capped; one short pack.
		array('status' => PG_PACK_PACKED, 'packer_id' => 6, 'started_at' => $today - 3 * $day, 'packed_at' => $today - 3 * $day + 10000, 'units' => 3, 'refused' => 0),
		array('status' => PG_PACK_SHORT, 'packer_id' => 6, 'started_at' => $today, 'packed_at' => 0, 'units' => 2, 'refused' => 4),
		// Packer 7: one pack closed within seconds.
		array('status' => PG_PACK_PACKED, 'packer_id' => 7, 'started_at' => $today + 10, 'packed_at' => $today + 30, 'units' => 1, 'refused' => 0),
	);

	$figures = pg_pack_card_figures(array(0, $today - 2 * $day, $today, $today + $day), $packs, $today);

	pg_assert_same(4, $figures['waiting'], 'every waiting address');
	pg_assert_same($today - 2 * $day, $figures['oldest'], 'the earliest known ship-by day');
	pg_assert_same(2, $figures['due'], 'late and due today');
	pg_assert_same(2, $figures['packed_today'], 'packs closed packed today');
	pg_assert_same(array(5, 6, 7), array_column($figures['packers'], 'packer_id'), 'most packs first');
	pg_assert_same(array('packer_id' => 5, 'packs' => 2, 'units' => 6, 'refused' => 1, 'per_hour' => 36), $figures['packers'][0], '6 units in 10 minutes');
	pg_assert_same(3, $figures['packers'][1]['per_hour'], 'an hour at most for one pack');
	pg_assert_same(4, $figures['packers'][1]['refused'], 'a short pack\'s refused scans count');
	pg_assert_same(1, $figures['packers'][1]['packs'], 'a short pack is not a pack');
	pg_assert_same(null, $figures['packers'][2]['per_hour'], 'under a minute in all: no figure');

	$none = pg_pack_card_figures(array(), array(), $today);

	pg_assert_same(array('waiting' => 0, 'oldest' => 0, 'due' => 0, 'packed_today' => 0, 'packers' => array()), $none, 'nothing to show');
}

function test_pack_held_units_sum_other_orders_at_the_location()
{
	// Shelf 3 at location 1; orders B (2) and C (1) are paid and not yet sent.
	$rows = array(
		array('ship_to_id' => 20, 'location_id' => 1, 'units' => 2),
		array('ship_to_id' => 30, 'location_id' => 1, 'units' => 1),
		array('ship_to_id' => 40, 'location_id' => 2, 'units' => 5),
	);
	$held = pg_pack_held_units($rows, 1, 10, 0);

	pg_assert_same(3, $held, 'B and C hold 3; another location is not counted');
	pg_assert_same(0, pg_pack_count_after(3, $held), 'shelf 3 less 3 held leaves 0 to sell');
	pg_assert_same(4, pg_pack_count_after(7, $held), 'shelf 7 less 3 held leaves 4');
}

function test_pack_held_units_keep_own_unpacked_units_only()
{
	// The short pack's own address asked 4: 1 scanned into the box, 2 missing, 1 still to pack.
	$rows = array(
		array('ship_to_id' => 10, 'location_id' => 0, 'units' => 4),
		array('ship_to_id' => 20, 'location_id' => 0, 'units' => 2),
	);

	pg_assert_same(3, pg_pack_held_units($rows, 0, 10, 3), 'own unit still to pack held, scanned and missing ones not');
	pg_assert_same(2, pg_pack_held_units($rows, 0, 10, 9), 'own units never go below 0');
}

function test_pack_count_after_never_goes_below_zero()
{
	$rows = array(
		array('ship_to_id' => 20, 'location_id' => 1, 'units' => 2),
		array('ship_to_id' => 30, 'location_id' => 1, 'units' => 3),
	);

	pg_assert_same(0, pg_pack_count_after(1, pg_pack_held_units($rows, 1, 10, 0)), 'shelf 1 below 5 held writes 0');
	pg_assert_same(0, pg_pack_count_after(0, 0), 'an empty shelf with nothing held');
}
