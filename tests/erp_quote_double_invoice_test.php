<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for two presses at once on "Turn into an invoice" of a sales quote
 * (includes/erp/quotes.php): the quote row is locked before its state is
 * read, so two requests cannot both find it invoiceable and make two
 * invoice drafts from one quote.
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
function erp_quote_double_invoice_test_body($function)
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/erp/quotes.php');
	$start = strpos($source, 'function ' . $function . '(');

	if ($start === false) {
		return '';
	}

	$end = strpos($source, "\nfunction ", $start + 1);

	return ($end === false) ? substr($source, $start) : substr($source, $start, $end - $start);
}

function test_erp_quote_to_invoice_reads_the_state_under_the_quote_lock()
{
	$body = erp_quote_double_invoice_test_body('erp_quote_to_invoice');
	$begin = strpos($body, 'erp_tx_begin()');
	$lock = strpos($body, 'FROM erp_quotes WHERE id = ');
	$read = strpos($body, '$quote = erp_quote($quote_id)');
	$refusal = strpos($body, 'erp_quote_invoice_refusal(');
	$save = strpos($body, 'erp_invoice_draft_save(');
	$mark = strpos($body, "status = 'invoiced'");
	$commit = strpos($body, 'erp_tx_commit()');
	$event = strpos($body, "'erp.quote.invoiced'");

	pg_assert_true($begin !== false, 'the draft is made in a transaction');
	pg_assert_true(($lock !== false) && ($lock > $begin), 'the quote row is locked after it starts');
	pg_assert_contains('FOR UPDATE', substr($body, (int) $lock, 120), 'with a locking read');
	pg_assert_true(($read !== false) && ($read > $lock), 'the quote is read under the lock');
	pg_assert_true(($refusal !== false) && ($refusal > $read), 'and its state judged there');
	pg_assert_true(($save !== false) && ($save > $refusal), 'the draft is written after');
	pg_assert_true(($mark !== false) && ($mark > $save) && ($commit !== false) && ($commit > $mark), 'the quote is marked invoiced before the commit');
	pg_assert_true(($event !== false) && ($event > $commit), 'the event is announced after the commit');
}

function test_erp_quote_to_invoice_rolls_back_every_refusal()
{
	$body = erp_quote_double_invoice_test_body('erp_quote_to_invoice');

	pg_assert_contains('erp_tx_rollback()', $body, 'a refusal releases the lock');
	pg_assert_true(strpos($body, "return array('success' => false, 'invoice_id' => 0, 'error' => \$refusal)") === false, 'no refusal returns with the transaction open');
}
