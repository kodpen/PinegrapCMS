<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Invoice an Order screen's layout helpers
 * (includes/forms/invoice_order_form.php): the order counts, the columns and
 * the save bar with and without an order to pick.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/forms/invoice_order_form.php');

function test_invoice_order_form_counts()
{
	pg_assert_same(array('orders' => 0, 'awaiting' => 0), pg_invoice_order_form_counts(array()), 'no orders');
	pg_assert_same(array('orders' => 0, 'awaiting' => 0), pg_invoice_order_form_counts(null), 'not a list');

	$paid = array('status' => 'complete', 'payment_method' => 'Credit/Debit Card', 'paid_at' => '');
	$transfer = array('status' => 'complete', 'payment_method' => 'Offline Payment', 'paid_at' => '');

	$counts = pg_invoice_order_form_counts(array($paid, 'junk', $transfer));
	pg_assert_same(2, $counts['orders'], 'a row that is not an order is not counted');
	pg_assert_same(1, $counts['awaiting'], 'an unpaid bank transfer awaits payment; a card order does not');
}

function test_invoice_order_form_columns()
{
	$card = pg_invoice_order_form_card('<select id="order_id"></select>');
	pg_assert_true(strpos($card, 'lg:col-8 2xl:col-9 order-1') !== false, 'the form column');
	pg_assert_true(strpos($card, '<label for="order_id"') !== false, 'the picker keeps its label');

	$status = pg_invoice_order_form_status(array('orders' => 4, 'awaiting' => 1), '/pg/add_erp_manual_invoice.php?x="y"');
	pg_assert_true(strpos($status, 'lg:col-4 2xl:col-3 order-2') !== false, 'the status column after the form column');
	pg_assert_true(strpos($status, 'position-sticky" style="top:3.3rem;') !== false, 'the sticky box below the top bar');
	pg_assert_true(strpos($status, '<span class="badge theme-warning">1</span>') !== false, 'orders awaiting payment stand out');
	pg_assert_true(strpos($status, 'href="/pg/add_erp_manual_invoice.php?x=&quot;y&quot;"') !== false, 'the link is escaped');
	pg_assert_true(strpos(pg_invoice_order_form_status(array('orders' => 2, 'awaiting' => 0), '/m'), 'badge') === false, 'no badge while nothing awaits payment');
}

function test_invoice_order_form_layout()
{
	$open = pg_invoice_order_form_layout('<div>cols</div>', true);
	pg_assert_true(strpos($open, 'col-12 order-3 position-sticky') !== false, 'the sticky save bar');
	pg_assert_true(strpos($open, 'name="submit_create"') !== false && strpos($open, ' disabled>') === false, 'Create the Invoice works with an order to pick');
	pg_assert_true(strpos(pg_invoice_order_form_layout('', false), ' disabled>') !== false, 'Create the Invoice is disabled with no order waiting');
}
