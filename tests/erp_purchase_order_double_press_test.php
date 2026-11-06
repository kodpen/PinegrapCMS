<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for two presses at once on a purchase order
 * (includes/erp/purchase_orders.php): making a purchase invoice draft reads
 * what is left on the order with the order's row locked, so two presses
 * cannot both draft the same goods and receive them twice once issued; and
 * the status moves (sent, cancelled, opened again) are guarded updates, so a
 * second press announces nothing a second time and a cancel never overwrites
 * an order that came in full meanwhile.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The body of one function in a source file, up to the next top-level function.
function erp_purchase_order_double_press_test_body($function)
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/erp/purchase_orders.php');
	$start = strpos($source, 'function ' . $function . '(');

	if ($start === false) {
		return '';
	}

	$end = strpos($source, "\nfunction ", $start + 1);

	return ($end === false) ? substr($source, $start) : substr($source, $start, $end - $start);
}

function test_erp_purchase_order_draft_reads_what_is_left_under_the_order_lock()
{
	$body = erp_purchase_order_double_press_test_body('erp_purchase_order_to_invoice');
	$begin = strpos($body, 'erp_tx_begin()');
	$lock = strpos($body, 'FROM erp_purchase_orders WHERE id = ');
	$progress = strpos($body, 'erp_purchase_order_order_progress(');
	$save = strpos($body, 'erp_invoice_draft_save(');
	$commit = strpos($body, 'erp_tx_commit()');

	pg_assert_true($begin !== false, 'the draft is made in a transaction');
	pg_assert_true(($lock !== false) && ($lock > $begin), 'the order row is read after it starts');
	pg_assert_contains('FOR UPDATE', substr($body, (int) $lock, 200), 'locked');
	pg_assert_true(($progress !== false) && ($progress > $lock), 'what is left is read under the lock');
	pg_assert_true(($save !== false) && ($save > $progress) && ($commit !== false) && ($commit > $save), 'the draft is written before the commit');
}

function test_erp_purchase_order_cancel_is_a_guarded_update()
{
	$body = erp_purchase_order_double_press_test_body('erp_purchase_order_set_status');

	pg_assert_contains("status NOT IN ('cancelled', 'received')", $body, 'a cancel moves only an order that is still waiting');
	pg_assert_contains("AND status = 'cancelled'", $body, 'opening again moves only a cancelled order');
	pg_assert_contains('mysqli_affected_rows(db::$con)', $body, 'and announces only what it moved');
}

function test_erp_purchase_order_sent_is_announced_once()
{
	$body = erp_purchase_order_double_press_test_body('erp_purchase_order_mark_sent');

	pg_assert_contains('AND sent_at = 0', $body, 'the first send is claimed by a guarded update');
	pg_assert_contains('mysqli_affected_rows(db::$con)', $body, 'which decides the announcement');
	pg_assert_true(strpos($body, "\$first = ((int) \$order['sent_at'] === 0)") === false, 'not the row read before');
}
