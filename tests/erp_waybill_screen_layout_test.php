<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the delivery note screen layout (edit_erp_waybill.php): the
 * delivery, transport, lines and notes in a form column, the note's facts
 * in a sticky status column written after it, the actions in the row's
 * floating save bar.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function erp_waybill_screen_layout_source()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/edit_erp_waybill.php');
}

function test_erp_waybill_screen_has_form_and_status_columns()
{
	$source = erp_waybill_screen_layout_source();

	$form = strpos($source, '<div class="col-12 lg:col-8 2xl:col-9 order-1">');
	$status = strpos($source, '<div class="col-12 lg:col-4 2xl:col-3 order-2">');

	pg_assert_true($form !== false, 'the form column is there');
	pg_assert_true($status !== false, 'the status column is there');
	pg_assert_true($form < $status, 'the status column comes after the form column in source order');
	pg_assert_contains('<div class="position-sticky" style="top:3.3rem;">', $source, 'the status box sticks below the top bar');
	$lines = strpos($source, "lang('Lines')");
	pg_assert_true($lines !== false && $lines > $form && $lines < $status, 'the lines card sits in the form column');
}

function test_erp_waybill_screen_status_column_holds_the_facts()
{
	$source = erp_waybill_screen_layout_source();
	$status_start = (int) strpos($source, '<div class="col-12 lg:col-4 2xl:col-3 order-2">');
	$bar_start = (int) strpos($source, 'class="col-12 order-3 position-sticky"');
	$status = substr($source, $status_start, $bar_start - $status_start);

	pg_assert_true($bar_start > $status_start, 'the bar column comes after the status column');

	foreach (array("lang('Account')", "lang('Date')", "lang('Shipment Date')", "lang('Status')", "lang('Order')", "lang('Invoice')") as $label) {
		pg_assert_contains($label, $status, $label . ' sits in the status column');
	}

	pg_assert_false(strpos($status, "lang('Lines')") !== false, 'the lines stay in the form column');
	pg_assert_false(strpos($status, 'name="erp_action"') !== false, 'the actions are not in the status column');
	pg_assert_contains('name="erp_action"', substr($source, $bar_start), 'the actions sit in the bar column');
}

// The delivery and transport cards stand straight in the form column, so
// they are look cards like the lines under them; the action form is the
// row's bar column, so its bar is the floating save bar.
function test_erp_waybill_screen_delivery_and_transport_are_look_cards()
{
	$source = erp_waybill_screen_layout_source();

	pg_assert_contains("lang('Delivered to')", $source, 'the delivery card is there');
	pg_assert_contains("lang('Transport')", $source, 'the transport card is there');
	pg_assert_false(strpos($source, '<div class="col-12 xl:col-6">') !== false, 'no half-width box around a card');
	pg_assert_contains('<form name="form" action="\' . $self_action . \'" method="post" class="col-12 order-3 position-sticky" style="bottom:0; z-index:3;">', $source, 'the action form is the bar column');
	pg_assert_contains('<nav class="buttons navigation text-center mb-7"', $source, 'the actions sit in the floating save bar');
}
