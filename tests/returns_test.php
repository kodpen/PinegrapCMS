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

// The parcels of a delivery: delivered when every one of them arrived, on the
// day the last one did; until then the latest arrival is only a lower bound.
function test_returns_parcel_delivery()
{
	pg_assert_same(array('delivered' => '2026-10-06', 'latest' => '2026-10-06'), pg_return_parcel_delivery(array('2026-10-04', '2026-10-06')), 'every parcel arrived');
	pg_assert_same(array('delivered' => '', 'latest' => '2026-10-04'), pg_return_parcel_delivery(array('2026-10-04', '')), 'one still on its way');
	pg_assert_same(array('delivered' => '', 'latest' => ''), pg_return_parcel_delivery(array('', '')), 'none known');
	pg_assert_same(array('delivered' => '', 'latest' => ''), pg_return_parcel_delivery(array()), 'no parcels');
	pg_assert_same(array('delivered' => '', 'latest' => '2026-10-04'), pg_return_parcel_delivery(array('2026-10-04', '0000-00-00')), 'a zero date is unknown');
}

// A parcel known to have arrived while another has not moves the estimate
// later, never earlier.
function test_returns_window_from_one_parcel()
{
	$window = pg_return_window(array('parcel_delivered' => '2026-10-09', 'ship_date' => '2026-10-01'), '2026-10-10');

	pg_assert_same('2026-10-09', $window['delivered'], 'the parcel that arrived');
	pg_assert_true($window['estimated'], 'still an estimate');
	pg_assert_same('2026-10-23', $window['deadline']);

	$early = pg_return_window(array('parcel_delivered' => '2026-10-02', 'ship_date' => '2026-10-01', 'delivery_date' => '2026-10-07'), '2026-10-10');
	pg_assert_same('2026-10-07', $early['delivered'], 'the later planned date stays');

	$known = pg_return_window(array('delivered' => '2026-10-05', 'parcel_delivered' => '2026-10-09'), '2026-10-10');
	pg_assert_same('2026-10-05', $known['delivered'], 'a known delivery wins');
	pg_assert_false($known['estimated']);
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

// The form settings the HTML builders below are given.
function returns_test_form()
{
	return array(
		'action'   => '/pinegrap/order_return.php',
		'send_to'  => '/e?order_id=7&x="1"',
		'token'    => '<input type="hidden" name="token" value="abc">',
		'order_id' => 7,
	);
}

// One delivery: the lines with their boxes, the reasons, the deadline and the
// hidden fields that name the order and the delivery.
function test_returns_form_html_one_delivery()
{
	$html = pg_return_form_html(array(array(
		'ship_to_id' => 31,
		'label'      => 'Jane Cooper',
		'deadline'   => '2 November 2026',
		'lines'      => array(
			array('id' => 11, 'name' => 'Mug <XL>', 'quantity' => 3, 'returnable' => 2),
			array('id' => 12, 'name' => 'Plate', 'quantity' => 1, 'returnable' => 0),
		),
	)), returns_test_form());

	pg_assert_contains('action="/pinegrap/order_return.php"', $html, 'posts to the endpoint');
	pg_assert_contains('<input type="hidden" name="token" value="abc">', $html, 'CSRF field');
	pg_assert_contains('name="action" value="request"', $html, 'action');
	pg_assert_contains('name="order_id" value="7"', $html, 'order');
	pg_assert_contains('name="ship_to_id" value="31"', $html, 'delivery');
	pg_assert_contains('value="/e?order_id=7&amp;x=&quot;1&quot;"', $html, 'send_to is escaped');
	pg_assert_contains('Mug &lt;XL&gt;', $html, 'names are escaped');
	pg_assert_contains('name="qty[11]"', $html, 'a box for a line with something left');
	pg_assert_contains('max="2"', $html, 'at most what is left');
	pg_assert_false(strpos($html, 'name="qty[12]"') !== false, 'no box for a line already asked for');
	pg_assert_contains('2 November 2026', $html, 'deadline');
	pg_assert_contains('value="damaged"', $html, 'reasons');
	pg_assert_contains('name="note"', $html, 'note');
	pg_assert_false(strpos($html, 'Jane Cooper') !== false, 'one delivery needs no heading');
	pg_assert_same(1, substr_count($html, '<form '), 'one form');
}

// Several deliveries: one form each, headed by the recipient.
function test_returns_form_html_several_deliveries()
{
	$line = array(array('id' => 11, 'name' => 'Mug', 'quantity' => 1, 'returnable' => 1));
	$html = pg_return_form_html(array(
		array('ship_to_id' => 31, 'label' => 'Jane <Cooper>', 'deadline' => '', 'lines' => $line),
		array('ship_to_id' => 32, 'label' => 'John Doe', 'deadline' => '', 'lines' => array(array('id' => 13, 'name' => 'Cup', 'quantity' => 2, 'returnable' => 2))),
	), returns_test_form());

	pg_assert_same(2, substr_count($html, '<form '), 'one form per delivery');
	pg_assert_contains('Jane &lt;Cooper&gt;', $html, 'recipient, escaped');
	pg_assert_contains('John Doe', $html, 'second recipient');
	pg_assert_contains('name="ship_to_id" value="32"', $html, 'second delivery');
	pg_assert_contains('id="pg-ov-return-reason-31"', $html, 'ids per delivery');
	pg_assert_contains('id="pg-ov-return-reason-32"', $html, 'ids per delivery');
}

// Nothing to ask for, no form.
function test_returns_form_html_empty()
{
	pg_assert_same('', pg_return_form_html(array(), returns_test_form()));
	pg_assert_same('', pg_return_form_html('nothing', returns_test_form()));
}

// The list says where each request stands and offers only the customer's
// own moves.
function test_returns_list_html()
{
	$base = array(
		'id' => 5, 'status' => 'requested', 'status_label' => 'Return requested',
		'requested' => '19 October 2026', 'lines' => array(array('name' => 'Mug <XL>', 'quantity' => 2)),
		'reason_label' => 'It arrived damaged', 'customer_note' => 'Box <torn>', 'decision_note' => '',
		'carrier' => '', 'carrier_code' => '', 'tracking_number' => '', 'refund_deadline' => '',
		'can_withdraw' => true, 'can_mark_sent' => false,
	);

	$html = pg_returns_list_html(array($base), returns_test_form());
	pg_assert_contains('Return requested', $html, 'status');
	pg_assert_contains('Mug &lt;XL&gt;', $html, 'line, escaped');
	pg_assert_contains('Box &lt;torn&gt;', $html, 'note, escaped');
	pg_assert_contains('name="action" value="withdraw"', $html, 'withdraw');
	pg_assert_contains('name="return_id" value="5"', $html, 'which return');
	pg_assert_false(strpos($html, 'value="shipped"') !== false, 'not yet approved: no "sent" form');

	$approved = array_merge($base, array('status' => 'approved', 'status_label' => 'Return approved',
		'carrier' => 'Yurtiçi Kargo', 'carrier_code' => 'IADE<77>', 'decision_note' => 'Use the code.',
		'can_withdraw' => false, 'can_mark_sent' => true));
	$html = pg_returns_list_html(array($approved), returns_test_form());
	pg_assert_contains('name="action" value="shipped"', $html, 'sent form');
	pg_assert_contains('name="tracking_number"', $html, 'tracking number box');
	pg_assert_contains('Yurtiçi Kargo', $html, 'carrier');
	pg_assert_contains('IADE&lt;77&gt;', $html, 'code, escaped');
	pg_assert_contains('Use the code.', $html, 'note from the shop');
	pg_assert_false(strpos($html, 'value="withdraw"') !== false, 'approved: no withdraw');

	$closed = array_merge($base, array('status' => 'refunded', 'status_label' => 'Money refunded',
		'tracking_number' => 'TR123', 'can_withdraw' => false, 'can_mark_sent' => false));
	$html = pg_returns_list_html(array($closed), returns_test_form());
	pg_assert_same(0, substr_count($html, '<form '), 'nothing left to do');
	pg_assert_contains('TR123', $html, 'tracking number');

	pg_assert_same('', pg_returns_list_html(array(), returns_test_form()), 'no returns');
}

// Each status has its own badge colour.
function test_returns_badge_class()
{
	pg_assert_contains('text-bg-warning', pg_return_badge_class('requested'));
	pg_assert_contains('text-bg-success', pg_return_badge_class('refunded'));
	pg_assert_contains('text-bg-danger', pg_return_badge_class('rejected'));
	pg_assert_contains('text-bg-secondary', pg_return_badge_class('closed'));
	pg_assert_contains('text-bg-secondary', pg_return_badge_class('unknown'));
}

// The customer's mail repeats what they asked for, escaped.
function test_returns_request_mail_body()
{
	$html = pg_return_request_mail_body(array(
		'customer'      => 'Jane <Cooper>',
		'order_number'  => '1172',
		'lines'         => array(array('name' => 'Mug <XL>', 'quantity' => 2)),
		'reason_label'  => 'It arrived damaged',
		'customer_note' => "Box torn\nsecond line",
		'from_name'     => 'ACME',
	));

	pg_assert_contains('Jane &lt;Cooper&gt;', $html, 'name, escaped');
	pg_assert_contains('1172', $html, 'order number');
	pg_assert_contains('Mug &lt;XL&gt;', $html, 'line, escaped');
	pg_assert_contains('It arrived damaged', $html, 'reason');
	pg_assert_contains('Box torn<br', $html, 'note line breaks');
	pg_assert_contains('ACME', $html, 'sender');
}

// The list filter: open by default (also for anything unknown), one status,
// or all.
function test_returns_filter_statuses()
{
	pg_assert_same(array('requested', 'approved', 'shipped', 'received'), pg_return_filter_statuses('open'));
	pg_assert_same(array('requested', 'approved', 'shipped', 'received'), pg_return_filter_statuses('nonsense'), 'unknown');
	pg_assert_same(array('requested', 'approved', 'shipped', 'received'), pg_return_filter_statuses(array('all')), 'not a string');
	pg_assert_same(array('rejected'), pg_return_filter_statuses('rejected'));
	pg_assert_same(pg_return_statuses(), pg_return_filter_statuses('all'));
}

// Each filter choice carries how many returns it holds.
function test_returns_filter_options()
{
	$options = pg_return_filter_options(array('requested' => 2, 'shipped' => 1, 'closed' => 4));

	pg_assert_same(array_merge(array('open'), pg_return_statuses(), array('all')), array_keys($options), 'open, each status, all');
	pg_assert_contains('3', $options['open'], 'requested and shipped are open');
	pg_assert_contains('2', $options['requested']);
	pg_assert_contains('0', $options['approved'], 'none');
	pg_assert_contains('7', $options['all']);

	pg_assert_contains('0', pg_return_filter_options(array())['all'], 'no returns at all');
}

// The approval form starts with the delivery's carrier, else the site's,
// else the last one used, and the code last given with that carrier.
function test_returns_approval_defaults()
{
	$previous = array(
		array('carrier' => 'aras', 'carrier_code' => 'AR-77'),
		array('carrier' => 'yurtici', 'carrier_code' => 'YK-123'),
		array('carrier' => 'yurtici', 'carrier_code' => 'YK-OLD'),
	);

	pg_assert_same(array('carrier' => 'yurtici', 'carrier_code' => 'YK-123'), pg_return_approval_defaults('yurtici', 'aras', $previous), 'the delivery\'s carrier, its latest code');
	pg_assert_same(array('carrier' => 'aras', 'carrier_code' => 'AR-77'), pg_return_approval_defaults('', 'aras', $previous), 'the site default');
	pg_assert_same(array('carrier' => 'aras', 'carrier_code' => 'AR-77'), pg_return_approval_defaults('', '', $previous), 'the last one used');
	pg_assert_same(array('carrier' => 'mng', 'carrier_code' => ''), pg_return_approval_defaults('mng', '', $previous), 'no code yet for that carrier');
	pg_assert_same(array('carrier' => '', 'carrier_code' => ''), pg_return_approval_defaults('', '', array()), 'nothing to go on');
	pg_assert_same(array('carrier' => 'yurtici', 'carrier_code' => 'YK-123'), pg_return_approval_defaults('YURTICI', '', $previous), 'a key in capitals');

	$typed = array(array('carrier' => 'Kendi Kargomuz', 'carrier_code' => 'KK-1'));
	pg_assert_same(array('carrier' => 'Kendi Kargomuz', 'carrier_code' => 'KK-1'), pg_return_approval_defaults('', '', $typed), 'a carrier typed by hand');
}

// From its screen staff may make every move but the refund.
function test_returns_staff_actions()
{
	pg_assert_same(array('approved', 'rejected', 'closed'), pg_return_staff_actions('requested'));
	pg_assert_same(array('shipped', 'received', 'closed'), pg_return_staff_actions('approved'));
	pg_assert_same(array('received', 'closed'), pg_return_staff_actions('shipped'));
	pg_assert_same(array('closed'), pg_return_staff_actions('received'), 'no refund here');
	pg_assert_same(array('closed'), pg_return_staff_actions('refunded'));
	pg_assert_same(array(), pg_return_staff_actions('rejected'));
	pg_assert_same(array(), pg_return_staff_actions('closed'));
}

// The audit trail compares what a move changes, not its time stamps.
function test_returns_audit_fields()
{
	$row = array('id' => 4, 'status' => 'approved', 'decision_note' => 'OK', 'carrier' => 'aras', 'carrier_code' => 'A1', 'tracking_number' => '', 'decided_at' => 1760000000);

	pg_assert_same(array('status' => 'approved', 'decision_note' => 'OK', 'carrier' => 'aras', 'carrier_code' => 'A1', 'tracking_number' => ''), pg_return_audit_fields($row));
	pg_assert_same(array('status' => '', 'decision_note' => '', 'carrier' => '', 'carrier_code' => '', 'tracking_number' => ''), pg_return_audit_fields(null), 'no row');
}

// The refund is due while the goods are on their way back or arrived.
function test_returns_refund_due()
{
	$shipped = gmmktime(12, 0, 0, 10, 10, 2026);
	$received = gmmktime(12, 0, 0, 10, 13, 2026);

	pg_assert_same('2026-10-24', pg_return_refund_due(array('status' => 'shipped', 'shipped_at' => $shipped, 'received_at' => 0)), 'sent back');
	pg_assert_same('2026-10-24', pg_return_refund_due(array('status' => 'received', 'shipped_at' => $shipped, 'received_at' => $received)), 'arrived, counted from the carrier');
	pg_assert_same('2026-10-27', pg_return_refund_due(array('status' => 'received', 'shipped_at' => 0, 'received_at' => $received)), 'arrived unannounced');
	pg_assert_same('', pg_return_refund_due(array('status' => 'refunded', 'shipped_at' => $shipped, 'received_at' => $received)), 'paid');
	pg_assert_same('', pg_return_refund_due(array('status' => 'approved', 'shipped_at' => 0, 'received_at' => 0)), 'not yet');
}

// Dates as the panel prints them, day first unless the site says otherwise.
function test_returns_day_text()
{
	if (!defined('DATE_FORMAT')) {
		pg_assert_same('9/10/2026', pg_return_day_text('2026-10-09'));
	}

	pg_assert_same('', pg_return_day_text('0000-00-00'), 'no date');
	pg_assert_same('', pg_return_time_text(0), 'no time');
}

// The answer tells the customer how to send the goods, or why not, escaped.
function test_returns_decision_mail_body()
{
	$approved = pg_return_decision_mail_body(array(
		'status'        => 'approved',
		'customer'      => 'Jane <Cooper>',
		'order_number'  => '1172',
		'lines'         => array(array('name' => 'Mug <XL>', 'quantity' => 1)),
		'carrier'       => 'Yurtiçi Kargo',
		'carrier_code'  => 'YK<1>',
		'decision_note' => "Pack it well\nthanks",
		'can_mark_sent' => true,
		'from_name'     => 'ACME',
	));

	pg_assert_contains('Jane &lt;Cooper&gt;', $approved, 'name, escaped');
	pg_assert_contains('1172', $approved, 'order number');
	pg_assert_contains('Mug &lt;XL&gt;', $approved, 'line, escaped');
	pg_assert_contains('Yurtiçi Kargo', $approved, 'carrier');
	pg_assert_contains('<strong>YK&lt;1&gt;</strong>', $approved, 'code, escaped');
	pg_assert_contains('Pack it well<br', $approved, 'note line breaks');
	pg_assert_contains('order page', $approved, 'may say it is on its way');

	$quiet = pg_return_decision_mail_body(array('status' => 'approved', 'order_number' => '9', 'can_mark_sent' => false));
	pg_assert_contains('You can send the goods back now.', $quiet, 'no carrier, no code');
	pg_assert_false(strpos($quiet, 'order page') !== false, 'the customer cannot say so there');

	$rejected = pg_return_decision_mail_body(array(
		'status'        => 'rejected',
		'order_number'  => '1172',
		'carrier'       => 'Yurtiçi Kargo',
		'carrier_code'  => 'YK-1',
		'decision_note' => 'Used <goods>',
	));

	pg_assert_contains('could not accept', $rejected, 'refused');
	pg_assert_contains('Used &lt;goods&gt;', $rejected, 'the reason, escaped');
	pg_assert_false(strpos($rejected, 'YK-1') !== false, 'no return code on a refusal');
}

// The order screen lists the order's returns, linked to their screens.
function test_returns_panel_card_html()
{
	pg_assert_same('', pg_returns_panel_card_html(array()), 'no returns, no card');

	$html = pg_returns_panel_card_html(array(
		array('id' => 7, 'status' => 'approved', 'requested_at' => gmmktime(12, 0, 0, 10, 9, 2026), 'items' => array(array('quantity' => 2), array('quantity' => 1))),
		array('id' => 3, 'status' => 'closed', 'requested_at' => 0, 'items' => array()),
	));

	pg_assert_contains('href="view_return.php?id=7"', $html, 'linked');
	pg_assert_contains('href="view_return.php?id=3"', $html, 'every return');
	pg_assert_contains('3 item(s)', $html, 'quantities added up');
	pg_assert_contains('text-bg-info', $html, 'status badge');
	pg_assert_contains('view_returns.php', $html, 'to the list');
}
