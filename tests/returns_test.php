<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the customer return model in includes/fn/returns.php: the
 * statuses and the moves between them, which returns still hold their goods,
 * the reasons, the withdrawal window and the refund deadline, and the check of
 * the lines and quantities a request names.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The seven statuses, in the order a return goes through them.
function test_returns_statuses()
{
	pg_assert_same(array('requested', 'approved', 'rejected', 'shipped', 'received', 'refunded', 'closed'), pg_return_statuses());

	pg_assert_same('approved', pg_return_status(' Approved '), 'case and spaces');
	pg_assert_same('', pg_return_status('cancelled'), 'not a return status');
	pg_assert_same('', pg_return_status(array('requested')), 'not a string');
}

// The moves staff may make; everything else is refused.
function test_returns_staff_moves()
{
	$allowed = array(
		'requested' => array('approved', 'rejected', 'closed'),
		'approved'  => array('shipped', 'received', 'closed'),
		'shipped'   => array('received', 'closed'),
		'received'  => array('refunded', 'closed'),
		'refunded'  => array('closed'),
		'rejected'  => array(),
		'closed'    => array(),
	);

	foreach ($allowed as $from => $targets) {
		pg_assert_same($targets, pg_return_next_statuses($from), 'next statuses of ' . $from);

		foreach (pg_return_statuses() as $to) {
			pg_assert_same(in_array($to, $targets, true), pg_return_can_move($from, $to), $from . ' -> ' . $to);
		}
	}

	pg_assert_same(array(), pg_return_next_statuses('unknown'), 'an unknown status goes nowhere');
	pg_assert_false(pg_return_can_move('requested', 'requested'), 'staying put is not a move');
}

// A customer may only withdraw a request and say the parcel is on its way.
function test_returns_customer_moves()
{
	pg_assert_true(pg_return_can_move('requested', 'closed', 'customer'), 'withdraw a request');
	pg_assert_true(pg_return_can_move('approved', 'shipped', 'customer'), 'sent back');

	pg_assert_false(pg_return_can_move('requested', 'approved', 'customer'), 'approve');
	pg_assert_false(pg_return_can_move('approved', 'closed', 'customer'), 'close an approved return');
	pg_assert_false(pg_return_can_move('shipped', 'received', 'customer'), 'receive');
	pg_assert_false(pg_return_can_move('received', 'refunded', 'customer'), 'refund');

	pg_assert_same(array('closed'), pg_return_next_statuses('requested', 'customer'));
	pg_assert_same(array('shipped'), pg_return_next_statuses('approved', 'customer'));
	pg_assert_same(array(), pg_return_next_statuses('shipped', 'customer'));
}

// Each status the return reaches is stamped in its own column.
function test_returns_stamp_columns()
{
	pg_assert_same('requested_at', pg_return_stamp_column('requested'));
	pg_assert_same('decided_at', pg_return_stamp_column('approved'));
	pg_assert_same('decided_at', pg_return_stamp_column('rejected'));
	pg_assert_same('shipped_at', pg_return_stamp_column('shipped'));
	pg_assert_same('received_at', pg_return_stamp_column('received'));
	pg_assert_same('refunded_at', pg_return_stamp_column('refunded'));
	pg_assert_same('closed_at', pg_return_stamp_column('closed'));
	pg_assert_same('', pg_return_stamp_column('lost'));
}

// The goods of a return count against the order line until it is rejected or
// closed before anything was sent back; a return closed after the parcel left
// keeps them.
function test_returns_holds_goods()
{
	foreach (array('requested', 'approved', 'shipped', 'received', 'refunded') as $status) {
		pg_assert_true(pg_return_holds_goods(array('status' => $status)), $status);
	}

	pg_assert_false(pg_return_holds_goods(array('status' => 'rejected', 'shipped_at' => 0)), 'rejected');
	pg_assert_false(pg_return_holds_goods(array('status' => 'closed', 'shipped_at' => 0, 'received_at' => 0, 'refunded_at' => 0)), 'withdrawn request');
	pg_assert_true(pg_return_holds_goods(array('status' => 'closed', 'shipped_at' => 1760000000)), 'closed after it was sent back');
	pg_assert_true(pg_return_holds_goods(array('status' => 'closed', 'received_at' => '1760000000')), 'closed after it arrived');
	pg_assert_true(pg_return_holds_goods(array('status' => 'closed', 'refunded_at' => 1760000000)), 'closed after the refund');
	pg_assert_false(pg_return_holds_goods(array('status' => '')), 'no status');
}

// Reasons come from a fixed list; anything else is no reason.
function test_returns_reasons()
{
	pg_assert_same(array('changed_mind', 'damaged', 'faulty', 'wrong_item', 'not_as_described', 'size', 'other'), array_keys(pg_return_reasons()));

	foreach (pg_return_reasons() as $code => $label) {
		pg_assert_true(is_string($label) && ($label !== ''), 'label of ' . $code);
	}

	pg_assert_same('damaged', pg_return_reason(' DAMAGED '));
	pg_assert_same('', pg_return_reason('broken'));
	pg_assert_same('', pg_return_reason(null));
}

// Dates are read as Y-m-d; an empty or impossible date is no date.
function test_returns_dates()
{
	pg_assert_same('2026-10-19', pg_return_date('2026-10-19'));
	pg_assert_same('', pg_return_date('0000-00-00'), 'MySQL empty date');
	pg_assert_same('', pg_return_date(''), 'empty');
	pg_assert_same('', pg_return_date('2026-02-30'), 'no such day');
	pg_assert_same('', pg_return_date('19.10.2026'), 'other format');
	pg_assert_same('', pg_return_date(null), 'null');

	pg_assert_same('2026-11-02', pg_return_add_days('2026-10-19', 14), 'two weeks');
	pg_assert_same('2026-03-01', pg_return_add_days('2026-02-26', 3), 'over the end of February');
	pg_assert_same('2024-03-01', pg_return_add_days('2024-02-27', 3), 'leap year');
	pg_assert_same('2027-01-04', pg_return_add_days('2026-12-21', 14), 'over the new year');
	pg_assert_same('', pg_return_add_days('', 14), 'no date');
}

// A known delivery date opens the 14 days by itself.
function test_returns_window_from_delivery()
{
	$window = pg_return_window(array('delivered' => '2026-10-01', 'ship_date' => '2026-09-20'), '2026-10-10');

	pg_assert_same('2026-10-01', $window['delivered']);
	pg_assert_false($window['estimated'], 'known');
	pg_assert_same('2026-10-15', $window['deadline']);
	pg_assert_true($window['open'], 'inside');
}

// Without one, the parcel is taken as delivered three days after it was
// sent, or on the recipient's own expected date when that is later.
function test_returns_window_from_ship_date()
{
	$window = pg_return_window(array('ship_date' => '2026-10-01'), '2026-10-04');

	pg_assert_same('2026-10-04', $window['delivered'], 'three days after it was sent');
	pg_assert_true($window['estimated'], 'estimated');
	pg_assert_same('2026-10-18', $window['deadline']);

	$later = pg_return_window(array('ship_date' => '2026-10-01', 'delivery_date' => '2026-10-08'), '2026-10-04');
	pg_assert_same('2026-10-08', $later['delivered'], 'the later expected date');
	pg_assert_same('2026-10-22', $later['deadline']);

	$earlier = pg_return_window(array('ship_date' => '2026-10-01', 'delivery_date' => '2026-10-02'), '2026-10-04');
	pg_assert_same('2026-10-04', $earlier['delivered'], 'never earlier than three days');

	$expected_only = pg_return_window(array('ship_date' => '0000-00-00', 'delivery_date' => '2026-10-08'), '2026-10-04');
	pg_assert_same('2026-10-08', $expected_only['delivered'], 'only an expected date');
	pg_assert_true($expected_only['estimated']);
}

// The last day is still inside; the day after is not. No date, no window.
function test_returns_window_edges()
{
	pg_assert_true(pg_return_window(array('delivered' => '2026-10-01'), '2026-10-15')['open'], 'last day');
	pg_assert_false(pg_return_window(array('delivered' => '2026-10-01'), '2026-10-16')['open'], 'the day after');
	pg_assert_true(pg_return_window(array('delivered' => '2026-10-01'), '2026-09-28')['open'], 'before the delivery');

	pg_assert_same(null, pg_return_window(array('ship_date' => '0000-00-00', 'delivery_date' => '0000-00-00'), '2026-10-04'), 'nothing known');
	pg_assert_same(null, pg_return_window(array(), '2026-10-04'), 'empty');
	pg_assert_same(null, pg_return_window(array('delivered' => '2026-10-01'), 'today'), 'no valid today');
}

// The refund is due 14 days after the parcel reached the carrier, or after it
// arrived when nobody said when it was sent.
function test_returns_refund_deadline()
{
	pg_assert_same('2026-10-24', pg_return_refund_deadline('2026-10-10', '2026-10-13'), 'from the carrier');
	pg_assert_same('2026-10-27', pg_return_refund_deadline('', '2026-10-13'), 'from the arrival');
	pg_assert_same('', pg_return_refund_deadline('', ''), 'neither');
	pg_assert_same('', pg_return_refund_deadline('0000-00-00', 'never'), 'neither, written badly');
}

// The goods have left when a tracking number is written, a quantity is
// marked shipped or the ship date has come.
function test_returns_goods_left()
{
	pg_assert_true(pg_return_goods_left(true, 0, '', '2026-10-04'), 'tracking number');
	pg_assert_true(pg_return_goods_left(false, 1, '', '2026-10-04'), 'shipped quantity');
	pg_assert_true(pg_return_goods_left(false, 0, '2026-10-04', '2026-10-04'), 'ship date today');
	pg_assert_true(pg_return_goods_left(false, 0, '2026-10-01', '2026-10-04'), 'ship date past');
	pg_assert_false(pg_return_goods_left(false, 0, '2026-10-05', '2026-10-04'), 'planned for tomorrow');
	pg_assert_false(pg_return_goods_left(false, 0, '0000-00-00', '2026-10-04'), 'nothing');
}

// What is left to return on a line.
function test_returns_returnable()
{
	pg_assert_same(3, pg_return_returnable(3, 0));
	pg_assert_same(1, pg_return_returnable('3', '2'));
	pg_assert_same(0, pg_return_returnable(2, 2));
	pg_assert_same(0, pg_return_returnable(1, 5), 'never below zero');
}

// The lines of a request: positive whole quantities of the delivery's lines,
// at most what is left on each.
function test_returns_check_lines()
{
	$lines = array(
		11 => array('name' => 'Mug', 'returnable' => 2),
		12 => array('name' => 'Plate', 'returnable' => 1),
		13 => array('name' => 'Bowl', 'returnable' => 0),
	);

	$result = pg_return_check_lines(array('11' => '2', '12' => '0'), $lines);
	pg_assert_true($result['ok'], 'valid');
	pg_assert_same(array(11 => 2), $result['lines'], 'zero lines are left out');

	$result = pg_return_check_lines(array(11 => 1, 12 => 1), $lines);
	pg_assert_same(array(11 => 1, 12 => 1), $result['lines'], 'two lines');

	pg_assert_false(pg_return_check_lines(array(), $lines)['ok'], 'nothing chosen');
	pg_assert_false(pg_return_check_lines(array(11 => 0), $lines)['ok'], 'only zeros');
	pg_assert_false(pg_return_check_lines(array(11 => 3), $lines)['ok'], 'more than is left');
	pg_assert_false(pg_return_check_lines(array(13 => 1), $lines)['ok'], 'already returned');
	pg_assert_false(pg_return_check_lines(array(99 => 1), $lines)['ok'], 'not a line of the delivery');
	pg_assert_false(pg_return_check_lines(array(11 => -1), $lines)['ok'], 'negative');
	pg_assert_false(pg_return_check_lines(array(11 => '1.5'), $lines)['ok'], 'a fraction');
	pg_assert_false(pg_return_check_lines(array(11 => array(1)), $lines)['ok'], 'not a number');
	pg_assert_false(pg_return_check_lines('11', $lines)['ok'], 'not a list');

	$result = pg_return_check_lines(array(11 => 3), $lines);
	pg_assert_contains('Mug', $result['error'], 'the error names the line');
}

// Free text is trimmed, cut and stripped of control characters; line breaks stay.
function test_returns_text()
{
	pg_assert_same("Box was torn.\nSecond line", pg_return_text("  Box was torn.\r\nSecond line\x07  ", 2000));
	pg_assert_same('abc', pg_return_text('abcdef', 3), 'cut');
	pg_assert_same('çğış', pg_return_text('çğışöü', 4), 'cut by characters, not bytes');
	pg_assert_same('', pg_return_text(array('x'), 10), 'not text');
	pg_assert_same('', pg_return_text(null, 10), 'null');
}
