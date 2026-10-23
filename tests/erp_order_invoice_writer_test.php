<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the writer of an order's invoice (erp_invoice_from_order_locked(),
 * includes/erp/order_bridge.php): it refuses an order that is not a finished
 * sale itself, before a number is taken, whichever screen called it (the
 * documents card, a delivery note, the Add Invoice form).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The body of the writer, up to its closing brace.
function erp_order_invoice_writer_test_body()
{
	$source = str_replace("\r\n", "\n", (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/erp/order_bridge.php'));
	$from = strpos($source, 'function erp_invoice_from_order_locked(');

	if ($from === false) {
		return '';
	}

	$to = strpos($source, "\n}\n", $from);

	return substr($source, $from, (($to === false) ? strlen($source) : $to) - $from);
}

// A cancelled or unfinished order is refused by the writer, after the order
// is read and before the transaction and the number.
function test_erp_order_invoice_writer_refuses_an_order_that_is_not_a_sale()
{
	$body = erp_order_invoice_writer_test_body();
	pg_assert_true($body !== '', 'the writer is there');

	$loaded = strpos($body, 'SELECT * FROM orders');
	$gate = strpos($body, "in_array((string) \$order['status'], erp_order_billable_statuses(), true)");
	$begin = strpos($body, 'erp_tx_begin(');
	$number = strpos($body, 'erp_next_number(');

	pg_assert_true($loaded !== false, 'the order is read');
	pg_assert_true($gate !== false, 'the order\'s status is checked against the billable statuses');
	pg_assert_true(($gate !== false) && ($gate > $loaded), 'once the order is read');
	pg_assert_true(($gate !== false) && ($begin !== false) && ($gate < $begin), 'before the transaction');
	pg_assert_true(($gate !== false) && ($number !== false) && ($gate < $number), 'before a number is taken');
}

// The refusal says what the documents card says beside the step.
function test_erp_order_invoice_writer_refusal_reads_as_the_card()
{
	$body = erp_order_invoice_writer_test_body();

	pg_assert_contains("lang('A cancelled order is not invoiced.')", $body, 'a cancelled order');
	pg_assert_contains("lang('An order is invoiced once it is complete.')", $body, 'an order that is not complete');
}
