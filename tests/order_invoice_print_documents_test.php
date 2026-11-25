<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the order documents a customer or the store prints: the
 * customer's printed invoice (order_invoice_print.php) answers a guessed id
 * the way it answers a missing one, lists the lines charged with the order
 * apart from recurring lines that start later (pg_order_print_lines()), and
 * prints the province once; the built-in ERP invoice and delivery note
 * templates break a long word instead of pushing the amounts off the page.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function _order_invoice_print_documents_source()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/order_invoice_print.php');
}

// Another customer's order and a missing one get the same answer, so order
// ids cannot be probed through the page.
function test_order_invoice_print_same_answer_for_another_customers_order()
{
	$source = _order_invoice_print_documents_source();

	pg_assert_false(strpos($source, "lang('You are not allowed to view this invoice.')") !== false,
		'no answer of its own for an order that is somebody else\'s');
	pg_assert_same(1, substr_count($source, "output_error(lang('Order not found.')"),
		'one answer for a missing order and for another customer\'s order');
	pg_assert_true((bool) preg_match('/\$order_lookup_refused = !\$order\s*\|\|/', $source), 'one flag decides both cases');
	pg_assert_true(strpos($source, "if (\$order_lookup_refused) {\n    output_error(lang('Order not found.'), 404);") !== false,
		'the flag gives the answer, as a 404');
}

// The ownership check runs before any other table is read for the order.
function test_order_invoice_print_checks_owner_before_reading_lines()
{
	$source = _order_invoice_print_documents_source();
	$check = strpos($source, '$order_lookup_refused');
	$tracking = strpos($source, 'FROM shipping_tracking_numbers');
	$items = strpos($source, 'FROM order_items');

	pg_assert_true(($check !== false) && ($tracking !== false) && ($check < $tracking) && ($check < $items),
		'the owner is checked before the parcels and the lines are read');
}

function _order_print_lines_item($id, $price, $quantity, $recurring = 0, $start_date = '0000-00-00', $start_days = 0)
{
	return array('item_id' => $id, 'price' => $price, 'quantity' => $quantity,
		'recurring' => $recurring, 'recurring_start_date' => $start_date, 'start' => $start_days);
}

// Lines that are not recurring are all charged with the order.
function test_order_print_lines_plain_order()
{
	$split = pg_order_print_lines(array(
		_order_print_lines_item(1, 1000, 2),
		_order_print_lines_item(2, 550, 1),
	), strtotime('2026-11-20 10:00:00'));

	pg_assert_same(array(1, 2), array_column($split['charged'], 'item_id'));
	pg_assert_same(array(), $split['later']);
	pg_assert_same(2550, $split['subtotal']);
}

// A recurring line whose payments start after the order date is not in the
// stored subtotal: the table above the totals leaves it out, so its rows add
// up to the subtotal printed under them.
function test_order_print_lines_recurring_starting_later_is_apart()
{
	$split = pg_order_print_lines(array(
		_order_print_lines_item(1, 1000, 1),
		_order_print_lines_item(2, 4900, 1, 1, '2026-12-20'),
		_order_print_lines_item(3, 2000, 1, 1, '2026-11-20'),
		_order_print_lines_item(4, 300, 2, 1, '0000-00-00', 14),
	), strtotime('2026-11-20 23:30:00'));

	pg_assert_same(array(1, 3), array_column($split['charged'], 'item_id'), 'a plan starting on the order day is charged with it');
	pg_assert_same(array(2, 4), array_column($split['later'], 'item_id'), 'a later start date or start days leave the line apart');
	pg_assert_same(3000, $split['subtotal']);
	pg_assert_same('2026-12-20', $split['later'][0]['starts']);
	pg_assert_same('2026-12-04', $split['later'][1]['starts'], 'start days count from the order date');
}

// A line total is price times quantity in minor units.
function test_order_print_lines_line_total()
{
	$split = pg_order_print_lines(array(_order_print_lines_item(7, 1999, 3)), strtotime('2026-11-20'));

	pg_assert_same(5997, $split['charged'][0]['line_total']);
}

// The province is printed once when it is the city again, and the
// recipient's district stands before the city.
function test_order_invoice_print_city_line_helper()
{
	$source = _order_invoice_print_documents_source();

	pg_assert_true(substr_count($source, 'pg_legal_document_city_line(') >= 2, 'both addresses build their city line with the shared helper');
	pg_assert_false(strpos($source, "(string)\$order['billing_city'] . ' ' . (string)\$order['billing_state']") !== false,
		'no city and state glued together');
	pg_assert_same('06100 Ankara', implode(' ', pg_legal_document_city_line('06100', '', 'Ankara', 'Ankara')));
	pg_assert_same('34710 Moda Kadıköy İstanbul', implode(' ', pg_legal_document_city_line('34710', 'Moda', 'Kadıköy', 'İstanbul')));
}

// A long word (an address or a name without spaces) wraps inside its cell;
// before, dompdf widened the description column to the whole word and the
// amount columns went off the page.
function test_erp_document_templates_break_long_words()
{
	foreach (array('invoice_default.html', 'waybill_default.html') as $file) {
		$template = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/erp/templates/' . $file);

		pg_assert_true((bool) preg_match('/\.lines td \{[^}]*overflow-wrap: anywhere;/', $template), $file . ': line cells break a long word');
		pg_assert_true((bool) preg_match('/\.party \{[^}]*overflow-wrap: anywhere;/', $template), $file . ': the buyer block breaks a long word');
		pg_assert_true((bool) preg_match('/\.lines \.n \{[^}]*white-space: nowrap;/', $template), $file . ': amounts stay on one line');
	}

	pg_assert_true((bool) preg_match('/td\{[^}]*overflow-wrap:anywhere/', _order_invoice_print_documents_source()),
		'the customer\'s printed invoice breaks a long word as well');
}
