<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the order view's returns list part
 * (includes/fn/widgets_parts.php, includes/fn/widgets_lists_order.php):
 * the order's return requests are drawn by real design elements, showing
 * the customer the same words, classes and forms as the list returns.php
 * built before; the editor builds the same part.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function _pret_node($type, $props, $children = array())
{
	return array('type' => $type, 'props' => $props, 'children' => $children);
}

function _pret_form()
{
	return array('action' => '/software/order_return.php', 'send_to' => '/order?id=9', 'token' => '<input type="hidden" name="token" value="t">', 'order_id' => 9);
}

// Four requests: asked (a note over two lines, may be withdrawn), approved
// with a carrier and a code (may be marked sent), approved with nothing
// (no lines, no date asked), refunded (a tracking number, a refund date).
function _pret_returns()
{
	$base = array(
		'id' => 5, 'status' => 'requested', 'status_label' => 'Return requested',
		'requested' => '19 October 2026', 'lines' => array(array('name' => 'Mug <XL>', 'quantity' => 2), array('name' => 'Cup', 'quantity' => 1)),
		'reason_label' => 'It arrived damaged', 'customer_note' => "Box <torn>\nsorry", 'decision_note' => '',
		'carrier' => '', 'carrier_code' => '', 'tracking_number' => '', 'refund_deadline' => '',
		'can_withdraw' => true, 'can_mark_sent' => false,
	);
	return array(
		$base,
		array_merge($base, array('id' => 6, 'status' => 'approved', 'status_label' => 'Return approved',
			'carrier' => 'Yurtiçi Kargo', 'carrier_code' => 'IADE<77>', 'decision_note' => 'Use the code.',
			'customer_note' => '', 'can_withdraw' => false, 'can_mark_sent' => true)),
		array_merge($base, array('id' => 7, 'status' => 'approved', 'status_label' => 'Return approved',
			'requested' => '', 'lines' => array(), 'reason_label' => '', 'customer_note' => '', 'can_withdraw' => false)),
		array_merge($base, array('id' => 8, 'status' => 'refunded', 'status_label' => 'Money refunded',
			'customer_note' => '', 'tracking_number' => 'TR123', 'refund_deadline' => '2 November 2026', 'can_withdraw' => false)),
	);
}

function test_widget_parts_returns_rows()
{
	$rows = pg_sw_list_order_return_rows(_pret_returns(), _pret_form());
	pg_assert_same(array(0), array_keys($rows['order.returns']), 'the whole order\'s');
	pg_assert_same(array('5', '6', '7', '8'), array_column($rows['order.returns'][0], '__return_id'));
	$r = $rows['order.returns'][0];
	pg_assert_same(lang(array('string' => 'Return request #{var:1}', 'vars' => array(5))), $r[0]['__return_title']);
	pg_assert_same(lang(array('string' => 'Asked on {var:1}', 'vars' => array('19 October 2026'))), $r[0]['__return_asked']);
	pg_assert_same('', $r[2]['__return_asked'], 'no date asked');
	pg_assert_same(array('1', '1', '', '1'), array_column($r, '__return_has_lines'));
	pg_assert_same('Box &lt;torn&gt;<br />' . "\n" . 'sorry', $r[0]['__return_customer_note_html'], 'escaped, line breaks kept');
	pg_assert_same('', $r[0]['__return_how'], 'how only once approved');
	pg_assert_same(lang(array('string' => 'Send the parcel with {var:1} and give them this return code:', 'vars' => array('Yurtiçi Kargo'))), $r[1]['__return_how']);
	pg_assert_same('IADE<77>', $r[1]['__return_code'], 'raw: the pass escapes it');
	pg_assert_same(lang('Your return was approved. You can send the goods back now.'), $r[2]['__return_how']);
	pg_assert_contains('value="withdraw"', $r[0]['__return_actions_html']);
	pg_assert_contains('value="shipped"', $r[1]['__return_actions_html']);
	pg_assert_same('', $r[3]['__return_actions_html'], 'nothing left to do');
	pg_assert_same(array(5, 6, 8), array_keys($rows['order.return_lines']), 'a return without products has no lines');
	pg_assert_same(array(array('__return_line_name' => 'Mug <XL>', '__return_line_qty' => '2'), array('__return_line_name' => 'Cup', '__return_line_qty' => '1')),
		$rows['order.return_lines'][5]);

	// Every row carries the source's tokens.
	$sources = pg_sw_list_sources();
	pg_assert_same(array_column($sources['order.returns']['tokens'], 0), array_keys($r[0]), 'order.returns tokens');
	pg_assert_same(array_column($sources['order.return_lines']['tokens'], 0), array_keys($rows['order.return_lines'][5][0]), 'order.return_lines tokens');

	pg_assert_same(array('order.returns' => array(), 'order.return_lines' => array()), pg_sw_list_order_return_rows(array(), _pret_form()), 'none');
}
