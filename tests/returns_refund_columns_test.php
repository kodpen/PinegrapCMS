<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the refund kept on a return (order_returns.refund_amount,
 * refund_method, refund_reference, refund_unclear): the values the upgrade
 * step fills in for returns refunded before the columns existed
 * (pg_return_refund_backfill_values(), read from the audit trail and the
 * order_refunds row), where pg_return_refund() writes them, and the step in
 * the open version's call list.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function returns_refund_columns_test_source($relative)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $relative);
}

// The source of one top-level function, up to the next one.
function returns_refund_columns_test_body($source, $name)
{
	$start = strpos($source, "\nfunction " . $name . '(');

	if ($start === false) {
		return '';
	}

	$next = strpos($source, "\nfunction ", $start + 1);

	return ($next === false) ? substr($source, $start) : substr($source, $start, $next - $start);
}

// An order_refunds row as the step reads it.
function returns_refund_columns_test_row($changes = array())
{
	return array_merge(array(
		'order_id' => '9008', 'amount_cents' => '25000', 'refund_type' => 'return', 'transaction_id' => 'R-77', 'notes' => 'Return request #12',
	), $changes);
}

// The audit line pg_return_refund() writes keeps only what changed: the
// amount, the method and, when there was one, the reference.
function test_returns_refund_columns_backfill_from_the_audit_trail()
{
	pg_assert_same(
		array('amount' => 5000, 'method' => 'manual', 'reference' => 'EFT-1', 'unclear' => 0),
		pg_return_refund_backfill_values(array('status' => 'refunded', 'refund_amount' => '5000', 'refund_method' => 'manual', 'refund_reference' => 'EFT-1'), array(), 12),
		'a manual refund with its reference');

	pg_assert_same(
		array('amount' => 0, 'method' => 'manual', 'reference' => '', 'unclear' => 0),
		pg_return_refund_backfill_values(array('refund_amount' => '0', 'refund_method' => 'manual'), array(), 12),
		'0 recorded, no reference: the unchanged empty reference is not in the line');

	$other = returns_refund_columns_test_row(array('amount_cents' => '100', 'refund_type' => 'return_manual', 'transaction_id' => 'T-row'));

	pg_assert_same(
		array('amount' => 25000, 'method' => 'driver', 'reference' => 'R-77', 'unclear' => 0),
		pg_return_refund_backfill_values(array('refund_amount' => '25000', 'refund_method' => 'driver', 'refund_reference' => 'R-77'), array($other), 12),
		'the audit line wins over a row that says otherwise');

	pg_assert_same(
		array('amount' => 25000, 'method' => 'driver', 'reference' => 'R-77', 'unclear' => 1),
		pg_return_refund_backfill_values(array('refund_amount' => '25000', 'refund_method' => 'driver', 'refund_reference' => 'R-77'),
			array(returns_refund_columns_test_row(array('amount_cents' => '100', 'refund_type' => 'return_manual', 'transaction_id' => 'T-row', 'notes' => 'Return request #12 (unconfirmed: x)'))), 12),
		'the row only adds that the answer was unclear');
}

// The trail keeps a text that reads as JSON decoded (a typed reference
// "[12345]" comes back as a list): the row's own text is taken instead.
function test_returns_refund_columns_backfill_reference_that_reads_as_json()
{
	$row = returns_refund_columns_test_row(array('refund_type' => 'return_manual', 'amount_cents' => '5000', 'transaction_id' => '[12345]'));

	pg_assert_same('[12345]', pg_return_refund_backfill_values(array('refund_amount' => '5000', 'refund_method' => 'manual', 'refund_reference' => array(12345)), array($row), 12)['reference'], 'the row\'s text');
	pg_assert_same('', pg_return_refund_backfill_values(array('refund_amount' => '0', 'refund_method' => 'manual', 'refund_reference' => array()), array(), 12)['reference'], 'no row: empty');
	pg_assert_same('R-77', pg_return_refund_backfill_values(array('refund_amount' => '25000', 'refund_method' => 'driver'), array(returns_refund_columns_test_row()), 12)['reference'], 'none in the line: the row\'s');
}

// An unclear answer is not in the audit line; the order_refunds note says it.
function test_returns_refund_columns_backfill_unclear_from_the_note()
{
	$row = returns_refund_columns_test_row(array('transaction_id' => '', 'notes' => 'Return request #12 (unconfirmed: no clear answer from Pay with iyzico)'));

	pg_assert_same(
		array('amount' => 25000, 'method' => 'driver', 'reference' => '', 'unclear' => 1),
		pg_return_refund_backfill_values(array('refund_amount' => '25000', 'refund_method' => 'driver'), array($row), 12),
		'audit line and the unconfirmed note');

	pg_assert_same(
		array('amount' => 25000, 'method' => 'driver', 'reference' => '', 'unclear' => 1),
		pg_return_refund_backfill_values(null, array($row), 12),
		'the note alone');
}

// Without an audit line the order_refunds row gives the amount, the method
// (by its type) and the reference.
function test_returns_refund_columns_backfill_from_the_refund_row()
{
	pg_assert_same(
		array('amount' => 25000, 'method' => 'driver', 'reference' => 'R-77', 'unclear' => 0),
		pg_return_refund_backfill_values(null, array(returns_refund_columns_test_row()), 12),
		'a driver refund');

	pg_assert_same(
		array('amount' => 5000, 'method' => 'manual', 'reference' => '', 'unclear' => 0),
		pg_return_refund_backfill_values(array(), array(returns_refund_columns_test_row(array('amount_cents' => '5000', 'refund_type' => 'return_manual', 'transaction_id' => null))), 12),
		'a manual refund with no reference (NULL column)');
}

// Only the return's own note: #1 is not #12, and other refunds of the order
// (the order screen's, another return's) are not this return's.
function test_returns_refund_columns_backfill_matches_the_exact_note()
{
	$rows = array(
		returns_refund_columns_test_row(array('notes' => 'Return request #12', 'amount_cents' => '100')),
		returns_refund_columns_test_row(array('notes' => 'Return request #120', 'amount_cents' => '200')),
		returns_refund_columns_test_row(array('notes' => 'Return request #1 (unconfirmed: no clear answer from iyzico)', 'amount_cents' => '300')),
		returns_refund_columns_test_row(array('notes' => 'Return request #1', 'refund_type' => 'refund', 'amount_cents' => '400')),
	);

	pg_assert_same(null, pg_return_refund_backfill_values(null, array($rows[0], $rows[1]), 1), '#12 and #120 are not #1');
	pg_assert_same(300, pg_return_refund_backfill_values(null, $rows, 1)['amount'], 'the unconfirmed note of #1');
	pg_assert_same(100, pg_return_refund_backfill_values(null, $rows, 12)['amount'], '#12, not #120');
	pg_assert_same(200, pg_return_refund_backfill_values(null, $rows, 120)['amount']);
	pg_assert_same(null, pg_return_refund_backfill_values(null, array($rows[3]), 1), 'a refund of another type is not a return\'s');
	pg_assert_same(null, pg_return_refund_backfill_values(null, array(returns_refund_columns_test_row(array('notes' => 'Return request #12x'))), 12), 'a longer note');
}

// Nothing found: the row is left to come back on the next run.
function test_returns_refund_columns_backfill_nothing_found()
{
	pg_assert_same(null, pg_return_refund_backfill_values(null, array(), 12));
	pg_assert_same(null, pg_return_refund_backfill_values(array('status' => 'closed'), array(), 12), 'an audit line of another move');
	pg_assert_same(null, pg_return_refund_backfill_values(array('refund_method' => 'cash', 'refund_amount' => '10'), array(), 12), 'not a method the model writes');
	pg_assert_same(null, pg_return_refund_backfill_values('x', 'y', 12), 'nothing readable');
	pg_assert_same(0, pg_return_refund_backfill_values(array('refund_amount' => '-5', 'refund_method' => 'manual'), array(), 12)['amount'], 'never below 0 (an unsigned column)');
}

// pg_return_refund() keeps the refund on the return once the outcome stands
// (after the order_refunds row), while it still holds the order's lock, on a
// return that was refunded, closed afterwards too; a driver refund is first
// marked as asked and unconfirmed, and the refusal that goes back to received
// clears it.
function test_returns_refund_columns_written_after_the_outcome_before_the_lock_is_let_go()
{
	$refund = returns_refund_columns_test_body(returns_refund_columns_test_source('includes/fn/returns.php'), 'pg_return_refund');
	$move = strpos($refund, "pg_return_move(\$return['id'], 'refunded'");
	$provisional = strpos($refund, "refund_method = 'driver',");
	$call = strpos($refund, 'pg_payment_call(');
	$revert = strpos($refund, "status = 'received', refunded_at = 0");
	$booked = strpos($refund, 'INSERT INTO order_refunds');
	$write = strrpos($refund, 'SET refund_amount = ');
	$release = strrpos($refund, '$release();');

	pg_assert_true(($provisional !== false) && ($provisional > $move) && ($provisional < $call), 'a driver refund is marked as asked before the service is called');
	pg_assert_contains("refund_reference = '',\n                    refund_unclear = 1", substr($refund, (int) $provisional, 300), 'unconfirmed until the service answers');
	pg_assert_contains("refund_amount = 0, refund_method = '', refund_reference = '', refund_unclear = 0", $refund, 'a refusal clears it');
	pg_assert_true(($write !== false) && ($write > $revert), 'the outcome is written after the refusal went back');
	pg_assert_true(($write !== false) && ($write > $booked), 'after the money is booked');
	pg_assert_true(($write !== false) && ($write < $release), 'before the lock is let go');
	pg_assert_contains('(refunded_at > 0)', substr($refund, (int) $write, 600), 'on a return that was refunded, closed afterwards too');
	pg_assert_false(strpos(substr($refund, (int) $write, 600), "(status = 'refunded')") !== false, 'not only while it is refunded');
	pg_assert_contains("pg_schema_has('order_returns', 'refund_method')", $refund, 'probed: the code lands before the step runs');
}

// A staff move takes the order's return lock, so a return cannot be closed
// while its refund waits on the payment service; the lock is let go on every
// path, and a busy lock is refused with the create's own sentence.
function test_returns_refund_columns_staff_move_holds_the_order_lock()
{
	$staff = returns_refund_columns_test_body(returns_refund_columns_test_source('includes/fn/returns.php'), 'pg_return_staff_move');
	$lock = strpos($staff, 'GET_LOCK(\'" . e($lock) . "\', 5)');
	$move = strpos($staff, 'pg_return_move(');
	$audit = strpos($staff, 'pg_audit(');

	pg_assert_contains('pg_return_lock_name(', $staff, 'the same lock the create and the refund hold');
	pg_assert_true(($lock !== false) && ($lock < $move), 'taken before the move');
	pg_assert_contains("lang('Another return is being saved for this order. Try again in a moment.')", $staff, 'busy: the create\'s sentence');
	$release = ($move !== false) ? strpos($staff, 'RELEASE_LOCK', $move) : false;
	$refused = strpos($staff, "if (!\$moved['ok']) {");

	pg_assert_true(($release !== false) && ($release < $refused), 'let go right after the move, before either answer: every path');
	pg_assert_true(($release !== false) && ($release < $audit), 'let go before the audit line, the log and the mail');
	pg_assert_contains("'announce' => false", $staff, 'the move does not announce under the lock');
	pg_assert_true(($release !== false) && (strpos($staff, "pg_return_announce('order.return.' . \$to", $release) !== false), 'approved and received are announced after the lock is let go');
}

// The step is called from the open version with its label, adds the four
// columns with the idempotent helper and fills the old refunds in again
// only where nothing is kept yet.
function test_returns_refund_columns_step_is_in_the_call_list()
{
	$source = returns_refund_columns_test_source('includes/migrations/2026.11.0.php');
	$entry = returns_refund_columns_test_body($source, 'upgrade_to_2026_11_0');
	$step = returns_refund_columns_test_body($source, 'upgrade_2026_11_0_order_return_refund');

	pg_assert_true(preg_match('/upgrade_2026_11_0_order_return_refund\(\);\s+\/\/ 11\.0\.34/', $entry) === 1, 'called with its label');
	pg_assert_same(1, preg_match_all('/\/\/ 11\.0\.34\b/', $entry), 'the label is used once');

	foreach (array(
		"install_add_column('order_returns', 'refund_amount', \"INT UNSIGNED NOT NULL DEFAULT 0\")",
		"install_add_column('order_returns', 'refund_method', \"VARCHAR(10) NOT NULL DEFAULT ''\")",
		"install_add_column('order_returns', 'refund_reference', \"VARCHAR(255) NOT NULL DEFAULT ''\")",
		"install_add_column('order_returns', 'refund_unclear', \"TINYINT UNSIGNED NOT NULL DEFAULT 0\")",
	) as $column) {
		pg_assert_contains($column, $step);
	}

	pg_assert_contains("refund_method = ''", $step, 're-runnable: only returns with nothing kept');
	pg_assert_contains('refunded_at > 0', $step);
	pg_assert_contains('pg_return_refund_backfill_values(', $step);
	pg_assert_contains('install_note(', $step);
	pg_assert_false(strpos($step, 'db("ALTER') !== false, 'no raw ALTER');
}
