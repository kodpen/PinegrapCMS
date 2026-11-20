<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the tickets of a refunded return (includes/fn/event_tickets.php):
 * which tickets of a returned line become void and in what order, only the
 * refund step voids, the void write, the places given back by the returned
 * units, the re-void after a restore, and the refund's call.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function event_tickets_returns_test_ticket($id, $unit, $state, $used_at = 0)
{
	return array('id' => $id, 'unit' => $unit, 'state' => $state, 'used_at' => $used_at);
}

function test_event_tickets_return_pick_by_quantity_highest_unit_first()
{
	$tickets = array(
		event_tickets_returns_test_ticket(11, 1, 'valid'),
		event_tickets_returns_test_ticket(12, 2, 'valid'),
		event_tickets_returns_test_ticket(13, 3, 'valid'),
	);

	pg_assert_same(array(13), pg_event_tickets_return_pick($tickets, 1), 'one returned: the highest unit');
	pg_assert_same(array(13, 12), pg_event_tickets_return_pick($tickets, 2), 'two returned: the two highest units');
	pg_assert_same(array(), pg_event_tickets_return_pick($tickets, 0), 'nothing returned, nothing void');
	pg_assert_same(array(), pg_event_tickets_return_pick(array(), 2), 'no tickets, nothing void');
}

function test_event_tickets_return_pick_unchecked_first()
{
	$tickets = array(
		event_tickets_returns_test_ticket(11, 1, 'valid'),
		event_tickets_returns_test_ticket(12, 2, 'valid'),
		event_tickets_returns_test_ticket(13, 3, 'used', 1700000000),
	);

	pg_assert_same(array(12), pg_event_tickets_return_pick($tickets, 1), 'a checked-in ticket is passed over');
	pg_assert_same(array(12, 11), pg_event_tickets_return_pick($tickets, 2), 'both unchecked ones before it');
	pg_assert_same(array(12, 11, 13), pg_event_tickets_return_pick($tickets, 3), 'the checked-in one only when nothing else is left');

	$all_used = array(
		event_tickets_returns_test_ticket(21, 1, 'used', 1700000000),
		event_tickets_returns_test_ticket(22, 2, 'used', 1700000100),
	);

	pg_assert_same(array(22), pg_event_tickets_return_pick($all_used, 1), 'all checked in: the highest unit');
}

function test_event_tickets_return_pick_skips_void_and_caps()
{
	$tickets = array(
		event_tickets_returns_test_ticket(11, 1, 'valid'),
		event_tickets_returns_test_ticket(12, 2, 'void'),
		event_tickets_returns_test_ticket(13, 3, 'valid'),
	);

	pg_assert_same(array(13), pg_event_tickets_return_pick($tickets, 1), 'a ticket an earlier return voided is not counted');
	pg_assert_same(array(13, 11), pg_event_tickets_return_pick($tickets, 5), 'more returned than tickets: all that are left');
}

function test_event_tickets_return_only_the_refund_voids()
{
	pg_assert_true(pg_event_tickets_return_voids('refunded'), 'the refund voids');

	foreach (array('requested', 'approved', 'shipped', 'received', 'rejected', 'closed', '') as $step) {
		pg_assert_false(pg_event_tickets_return_voids($step), 'the ' . ($step === '' ? 'empty' : $step) . ' step leaves the tickets');
	}

	// A return put back to received (a refused refund) is not a move that
	// gives tickets back: only a cancel's restore does, and it keeps a
	// refunded unit void (pg_event_tickets_returns_reapply()).
	pg_assert_same('void', pg_event_ticket_state_after('return', 'valid', 0), 'a return voids a valid ticket');
	pg_assert_same('void', pg_event_ticket_state_after('return', 'used', 1700000000), 'and a checked-in one');
	pg_assert_same('void', pg_event_ticket_state_after('reopen', 'void', 0), 'a reopened return gives nothing back');
}

function test_event_tickets_lines_refunded_writes()
{
	$GLOBALS['event_tickets_returns_test_sql'] = array();

	$query = function ($sql, array $params) {
		$GLOBALS['event_tickets_returns_test_sql'][] = array('sql' => $sql, 'params' => $params);

		return count($params) - 1;
	};

	$read = function ($order_id, $order_item_id) {
		$lines = array(
			501 => array(
				event_tickets_returns_test_ticket(11, 1, 'used', 1700000000),
				event_tickets_returns_test_ticket(12, 2, 'valid'),
				event_tickets_returns_test_ticket(13, 3, 'valid'),
			),
			502 => array(),
		);

		return ($order_id === 9001) ? ($lines[$order_item_id] ?? array()) : array();
	};

	pg_assert_same(2, pg_event_tickets_lines_refunded(9001, array(501 => 2, 502 => 1, 503 => 0), $query, $read), 'two tickets voided');
	pg_assert_same(1, count($GLOBALS['event_tickets_returns_test_sql']), 'one statement, for the line with tickets');

	$write = $GLOBALS['event_tickets_returns_test_sql'][0];
	pg_assert_contains("SET state = 'void'", $write['sql'], 'tickets become void');
	pg_assert_contains("(state <> 'void')", $write['sql'], 'only those not void already');
	pg_assert_same(array(9001, 13, 12), $write['params'], 'the order and the two unchecked tickets');

	$GLOBALS['event_tickets_returns_test_sql'] = array();
	pg_assert_same(0, pg_event_tickets_lines_refunded(0, array(501 => 1), $query, $read), 'no order, nothing');
	pg_assert_same(array(), $GLOBALS['event_tickets_returns_test_sql'], 'and no statement');
}

function test_event_tickets_return_place_lines()
{
	$lines = array(
		array('order_item_id' => 501, 'calendar_event_id' => 12, 'quantity' => 4, 'reservations' => 1, 'limit_reservations' => 1, 'recurrence' => 0, 'separate_reservations' => 0, 'recurrence_number' => 0),
		array('order_item_id' => 502, 'calendar_event_id' => 13, 'quantity' => 2, 'reservations' => 1, 'limit_reservations' => 1, 'recurrence' => 0, 'separate_reservations' => 0, 'recurrence_number' => 0),
		array('order_item_id' => 503, 'calendar_event_id' => 14, 'quantity' => 2, 'reservations' => 1, 'limit_reservations' => 1, 'recurrence' => 0, 'separate_reservations' => 0, 'recurrence_number' => 0),
	);

	$returned = pg_event_tickets_return_place_lines($lines, array(501 => 1, 502 => 5));

	pg_assert_same(2, count($returned), 'only the returned lines');
	pg_assert_same(1, $returned[0]['quantity'], 'the units returned');
	pg_assert_same(2, $returned[1]['quantity'], 'never more than the line');

	$places = pg_order_event_places($returned, true, true);
	pg_assert_same(array(array(12, 0, 1), array(13, 0, 2)), $places, 'the places given back');
	pg_assert_same(array(), pg_order_event_places($returned, false, true), 'nothing with tickets off');
}

function test_event_tickets_refund_and_restore_call_it()
{
	$returns = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/returns.php');
	$tickets = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/event_tickets.php');

	pg_assert_same(1, substr_count($returns, 'pg_event_tickets_return_refunded($return[\'order_id\'], $returned_quantities)'), 'the refund voids the returned tickets');
	pg_assert_same(1, substr_count($tickets, '- pg_event_tickets_returns_reapply($order_id)'), 'a restore keeps refunded units void');
}
