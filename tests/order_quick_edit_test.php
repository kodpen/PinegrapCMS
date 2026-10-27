<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the order list's quick edit in includes/fn/ecommerce.php: which
 * statuses the list offers (pg_order_quick_edit_columns()) and how the status
 * cell is drawn for the list and for the answer to an edit
 * (pg_order_list_status_html()). The write itself queries the database and
 * is checked in the sandbox.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function order_quick_edit_row($changes = array())
{
	return array_merge(array(
		'status'             => 'complete',
		'payment_method'     => 'Credit/Debit Card',
		'paid_at'            => 0,
		'parasut_exported'   => '0',
		'parasut_invoice_id' => '',
	), $changes);
}

function test_order_quick_edit_offers_only_complete_and_exported()
{
	$columns = pg_order_quick_edit_columns();

	pg_assert_same(array('status'), array_keys($columns), 'the status is the one column');
	pg_assert_same('select', $columns['status']['type'], 'a choice list');
	pg_assert_same(array('complete', 'exported'), array_keys($columns['status']['options']), 'cancelling and incomplete are not offered');
}

function test_order_quick_edit_refuses_cancelled_and_incomplete_as_values()
{
	$column = pg_order_quick_edit_columns()['status'];

	pg_assert_same('exported', pg_list_edit_parse($column, 'exported')['value'], 'exported');
	pg_assert_same('complete', pg_list_edit_parse($column, 'complete')['value'], 'complete');

	foreach (array('cancelled', 'canceled', 'incomplete', 'Complete', '') as $typed) {
		pg_assert_false(pg_list_edit_parse($column, $typed)['ok'], $typed);
	}
}

function test_order_quick_edit_status_badge_per_status()
{
	pg_assert_contains('theme-warning', pg_order_list_status_html(order_quick_edit_row(array('status' => 'incomplete')), true), 'incomplete');
	pg_assert_contains('theme-primary', pg_order_list_status_html(order_quick_edit_row(array('status' => 'complete')), true), 'complete');
	pg_assert_contains('theme-success', pg_order_list_status_html(order_quick_edit_row(array('status' => 'exported')), true), 'exported');
	pg_assert_contains('theme-danger', pg_order_list_status_html(order_quick_edit_row(array('status' => 'cancelled')), true), 'cancelled');
	pg_assert_contains('theme-danger', pg_order_list_status_html(order_quick_edit_row(array('status' => 'canceled')), true), 'the old spelling');
}

function test_order_quick_edit_awaiting_payment_needs_the_paid_column()
{
	$unpaid = order_quick_edit_row(array('payment_method' => 'Offline Payment', 'paid_at' => 0));
	$badge = lang('Awaiting Payment');

	pg_assert_contains($badge, pg_order_list_status_html($unpaid, true), 'unpaid bank transfer');
	pg_assert_same(false, strpos(pg_order_list_status_html($unpaid, false), $badge) !== false, 'nothing known before the upgrade');
	pg_assert_same(false, strpos(pg_order_list_status_html(order_quick_edit_row(array('payment_method' => 'Offline Payment', 'paid_at' => 1700000000)), true), $badge) !== false, 'paid');
	pg_assert_contains($badge, pg_order_list_status_html(array_merge($unpaid, array('status' => 'exported')), true), 'an exported order can still be unpaid');
}

function test_order_quick_edit_parasut_mark_names_what_happened()
{
	$file = pg_order_list_status_html(order_quick_edit_row(array('parasut_exported' => '1')), true);
	$invoice = pg_order_list_status_html(order_quick_edit_row(array('parasut_invoice_id' => ' 42 ')), true);

	pg_assert_contains('parasut-emblem.png', $file, 'exported to a file');
	pg_assert_contains(h(lang('This order has been exported for upload to parasut.com')), $file, 'file tooltip');
	pg_assert_contains(h(lang('An invoice was created for this order in Parasut')), $invoice, 'invoice tooltip');
	pg_assert_same(false, strpos(pg_order_list_status_html(order_quick_edit_row(), true), 'parasut') !== false, 'no mark otherwise');
}

// The unsure mark Create Invoice leaves after a create that got no answer
// is no invoice: the list says to check Paraşüt instead of drawing the
// emblem, and a spreadsheet export keeps its own emblem beside it.
function test_order_quick_edit_parasut_unsure_mark_is_not_an_invoice()
{
	$unsure = pg_order_list_status_html(order_quick_edit_row(array('parasut_invoice_id' => '?')), true);
	$exported = pg_order_list_status_html(order_quick_edit_row(array('parasut_invoice_id' => '?', 'parasut_exported' => '1')), true);

	pg_assert_false(strpos($unsure, 'parasut-emblem.png') !== false, 'no emblem');
	pg_assert_false(strpos($unsure, h(lang('An invoice was created for this order in Parasut'))) !== false, 'not called invoiced');
	pg_assert_contains('theme-warning', $unsure);
	pg_assert_contains(h(lang('Check Paraşüt')), $unsure);

	pg_assert_contains(h(lang('This order has been exported for upload to parasut.com')), $exported, 'the file emblem');
	pg_assert_false(strpos($exported, h(lang('An invoice was created for this order in Parasut'))) !== false);
	pg_assert_contains(h(lang('Check Paraşüt')), $exported);
}

function test_order_quick_edit_cell_carries_the_status_to_open_with()
{
	$cell = pg_list_edit_cell(pg_order_quick_edit_columns(), 'status', 12, 'exported');

	pg_assert_contains(' data-pg-edit="status" data-pg-edit-id="12"', $cell, 'field and order');
	pg_assert_contains(' data-pg-edit-value="exported"', $cell, 'the stored status');
}
