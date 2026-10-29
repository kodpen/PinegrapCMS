<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source checks for the Create Order screen's focus across redraws
 * (assets/js/add_order.js): the cart rows, the quick-amount chips and the
 * customer card are rewritten from every server answer, and the control
 * that had focus is found again in the fresh markup. A unit test cannot
 * press keys on the screen, so these pin the source.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function order_cart_focus_source()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/add_order.js');
}

// The body of one function in the screen's script, from its declaration to
// the next function declared at the same indent.
function order_cart_focus_function($name)
{
	$source = order_cart_focus_source();
	$start = strpos($source, "\n    function " . $name . '(');
	if ($start === false) {
		return '';
	}
	$end = strpos($source, "\n    function ", $start + 1);

	return $end === false ? substr($source, $start) : substr($source, $start, $end - $start);
}

function test_order_cart_focus_key_names_the_redrawn_controls()
{
	$save = order_cart_focus_function('saveFocus');

	pg_assert_true($save !== '', 'saveFocus() is found in add_order.js');
	pg_assert_contains('document.activeElement', $save, 'the key is read from the focused control');
	pg_assert_contains('container.contains(', $save, 'only a control inside the redrawn part is kept');

	$source = order_cart_focus_source();
	foreach (array('data-sale-inc', 'data-sale-dec', 'data-sale-qty', 'data-sale-rm', 'data-sale-recv', 'data-sale-cust') as $attr) {
		pg_assert_contains("'" . $attr . "'", $source, $attr . ' is a focus key');
	}

	$restore = order_cart_focus_function('restoreFocus');
	pg_assert_true($restore !== '', 'restoreFocus() is found in add_order.js');
	pg_assert_contains('preventScroll: true', $restore, 'the restored focus does not scroll the screen');
}

function test_order_cart_focus_wraps_every_rewrite()
{
	$cart = order_cart_focus_function('renderCart');
	pg_assert_true(strpos($cart, 'saveFocus(el.rows)') < strpos($cart, 'el.rows.innerHTML'), 'renderCart() saves the focus before the rows are rewritten');
	pg_assert_contains('restoreFocus(', $cart, 'renderCart() restores the focus');

	$customer = order_cart_focus_function('renderCustomer');
	pg_assert_true(strpos($customer, 'saveFocus(el.customer)') !== false && strpos($customer, 'saveFocus(el.customer)') < strpos($customer, 'el.customer.innerHTML = customer.html'), 'renderCustomer() saves the focus before the card is rewritten');
	pg_assert_contains('restoreFocus(', $customer, 'renderCustomer() restores the focus');

	$change = order_cart_focus_function('refreshChange');
	pg_assert_true(strpos($change, 'saveFocus(el.quick)') !== false && strpos($change, 'saveFocus(el.quick)') < strpos($change, 'el.quick.innerHTML'), 'refreshChange() saves the focus before the chips are rewritten');
	pg_assert_contains('restoreFocus(', $change, 'refreshChange() restores the focus');
}

function test_order_cart_focus_fallbacks()
{
	$restore = order_cart_focus_function('restoreFocus') . order_cart_focus_function('focusTarget');

	pg_assert_contains('key.next', $restore, 'a removed line hands the focus to the next row');
	pg_assert_contains('key.prev', $restore, 'else to the previous row');
	pg_assert_contains('el.product', $restore, 'an emptied cart hands the focus to the product box');
	pg_assert_contains('el.received', $restore, 'a chip that is gone hands the focus to the Received box');
	pg_assert_contains('[data-sale-cust="change"]', $restore, 'Remove the customer hands the focus to the Pick button');
}

function test_order_cart_focus_enter_keeps_the_quantity_box()
{
	$source = order_cart_focus_source();

	pg_assert_true(strpos($source, 'input.blur()') === false, 'Enter in a quantity box no longer drops the focus to <body>');
	pg_assert_contains('input.defaultValue', $source, 'Enter sends the quantity only when it changed');
}
