<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The order screen's toolbar keeps at most four buttons beside the primary
 * one (Refund, Print, Cancel Order); the payment provider's panel, the
 * Paraşüt and ERP buttons and the workspace are rows of its three-dots menu.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function view_order_toolbar_test_source()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/view_order.php');
}

/**
 * The text of the pg_screen_toolbar() call.
 */
function view_order_toolbar_test_call()
{
	$source = view_order_toolbar_test_source();
	$start = strpos($source, 'pg_screen_toolbar(array(');
	$end = ($start === false) ? false : strpos($source, "array('class' => 'pg-print-none'))", $start);
	pg_assert_true($start !== false && $end !== false, 'view_order.php: the toolbar call is found');

	return (string) substr($source, (int) $start, (int) $end - (int) $start);
}

function test_view_order_toolbar_secondary_holds_at_most_four_buttons()
{
	$call = view_order_toolbar_test_call();
	$from = strpos($call, "'secondary' => array(");
	$to = strpos($call, "'overflow' =>");
	pg_assert_true($from !== false && $to !== false && $from < $to, 'secondary slot before the overflow slot');
	$secondary = substr($call, (int) $from, (int) $to - (int) $from);

	preg_match_all('/^\s*(\$\w+|\'<button)/m', substr($secondary, strlen("'secondary' => array(")), $items);
	pg_assert_true(count($items[1]) >= 1 && count($items[1]) <= 4, 'one to four buttons in the bar (' . count($items[1]) . ')');
	pg_assert_contains('$output_refund_button', $secondary, 'Refund stays on the bar');
	pg_assert_contains('$output_cancel_button', $secondary, 'Cancel Order stays on the bar');
	pg_assert_contains("lang('Print')", $secondary, 'Print stays on the bar');

	foreach (array('$output_gateway', '$output_parasut', '$output_erp', '$output_workspace') as $moved) {
		pg_assert_true(strpos($secondary, $moved) === false, $moved . ' is not on the bar');
	}
}

function test_view_order_toolbar_rare_buttons_are_menu_rows()
{
	$source = view_order_toolbar_test_source();
	pg_assert_contains("'overflow' => \$output_toolbar_more", view_order_toolbar_test_call(), 'the overflow slot is filled');
	pg_assert_contains('$output_gateway_menu_item = \'<a class="menu-item pg-link-body-emphasis"', $source, 'the payment provider panel is a menu row');
	pg_assert_contains("ws_record_button(\$user, 'order', (int) \$_GET['id'], lang('Order') . ' ' . \$order_number, 'menu-item pg-link-body-emphasis')", $source, 'the workspace is a menu row');
	pg_assert_contains('<ul class="menu pg-menu-end">', $source, 'the three-dots menu opens to the left');

	// Menu rows carry no colour: the document state is told by the badges.
	$menu_rows = preg_match_all('/class="menu-item[^"]*theme-(success|warning|secondary)/', $source);
	pg_assert_true($menu_rows === 0, 'no coloured menu rows');
}
