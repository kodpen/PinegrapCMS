<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the refund groups of a bulk cancel on the Orders list
 * (includes/fn/ecommerce.php, pg_order_bulk_cancel_refunds()): the orders
 * whose payment was refunded, the ones whose payment must be returned by hand
 * (by bank transfer or in the provider's dashboard) and the gift card part,
 * decided as the per-row Cancel button decides them.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function order_bulk_cancel_refunds_row($id, $number, $method, $total, $paid_at, $gift_card = 0)
{
	return array(
		'id'                 => $id,
		'order_number'       => $number,
		'payment_method'     => $method,
		'total'              => $total,
		'refunded_amount'    => 0,
		'paid_at'            => $paid_at,
		'gift_card_discount' => $gift_card,
	);
}

function test_order_bulk_cancel_refunds_empty()
{
	pg_assert_same(
		array('refunded' => array(), 'transfer' => array(), 'provider' => array(), 'gift_card' => array(), 'gift_card_amount' => 0),
		pg_order_bulk_cancel_refunds(array()),
		'no cancelled order gives empty groups');
}

function test_order_bulk_cancel_refunds_groups()
{
	$groups = pg_order_bulk_cancel_refunds(array(
		array('order' => order_bulk_cancel_refunds_row(456, '1185', 'Offline Payment', 4103568, 1790000000), 'refund_status' => 'manual_required'),
		array('order' => order_bulk_cancel_refunds_row(457, '1186', 'Credit/Debit Card', 5000, 1790000000), 'refund_status' => 'refunded'),
		array('order' => order_bulk_cancel_refunds_row(458, '1187', 'Credit/Debit Card', 7000, 1790000000), 'refund_status' => 'failed'),
		array('order' => order_bulk_cancel_refunds_row(459, '1188', 'Offline Payment', 9000, 0), 'refund_status' => ''),
		array('order' => order_bulk_cancel_refunds_row(460, '', 'Credit/Debit Card', 0, 1790000000, 2500), 'refund_status' => 'manual_required'),
		array('order' => order_bulk_cancel_refunds_row(461, '1190', 'Credit/Debit Card', 3000, 1790000000, 1500), 'refund_status' => 'none'),
	));

	pg_assert_same(array('1186'), $groups['refunded'], 'a refunded payment is told');
	pg_assert_same(array('1185'), $groups['transfer'], 'a paid bank transfer goes back by transfer; one never received owes nothing');
	pg_assert_same(array('1187'), $groups['provider'], 'a failed card refund goes to the provider dashboard');
	pg_assert_same(array('460', '1190'), $groups['gift_card'], 'gift card parts are named by number, the row id without one');
	pg_assert_same(4000, $groups['gift_card_amount'], 'the gift card amounts add up in minor units');
}
