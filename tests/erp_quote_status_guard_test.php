<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a sales quote's status moves (includes/erp/quotes.php,
 * erp_quote_set_status()): the write is guarded on the status and invoice
 * the move was judged on, so a move read before the quote became an invoice
 * (the guest link's acceptance, a screen or API button pressed at the same
 * moment) cannot write over "invoiced" and make the quote invoiceable a
 * second time; only a move that wrote the row is announced.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The body of one function in quotes.php, up to the next top-level function.
function erp_quote_status_guard_test_body($function)
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/erp/quotes.php');
	$start = strpos($source, 'function ' . $function . '(');

	if ($start === false) {
		return '';
	}

	$end = strpos($source, "\nfunction ", $start + 1);

	return ($end === false) ? substr($source, $start) : substr($source, $start, $end - $start);
}

function test_erp_quote_status_move_is_guarded_on_what_was_read()
{
	$body = erp_quote_status_guard_test_body('erp_quote_set_status');
	$update = strpos($body, 'UPDATE erp_quotes SET status');

	pg_assert_true($update !== false, 'the move writes the status');

	$statement = substr($body, (int) $update, 900);

	pg_assert_contains("AND status = '\" . escape((string) \$quote['status'])", $statement, 'only from the status it was judged on');
	pg_assert_contains("AND invoice_id = '\" . (int) \$quote['invoice_id']", $statement, 'and the invoice it was judged on');
}

function test_erp_quote_status_move_announces_only_what_it_wrote()
{
	$body = erp_quote_status_guard_test_body('erp_quote_set_status');
	$affected = strpos($body, 'mysqli_affected_rows(db::$con)');
	$event = strpos($body, 'erp_event_quote(');

	pg_assert_true($affected !== false, 'the rows moved are counted');
	pg_assert_true(($event !== false) && ($event > $affected), 'before the move is announced');
	pg_assert_contains('=== false', $body, 'a failed write is not reported as done');
}
