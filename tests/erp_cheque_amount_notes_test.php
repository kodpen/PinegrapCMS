<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a cheque's amount limit (erp_cheque_amount_refusal()) and for the
 * notes a step's reason is added to (erp_cheque_notes_with_reason()) in
 * includes/erp/cheques.php.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/erp/money.php');
require_once(PG_FUNCTIONS_DIR . '/includes/erp/cheques.php');

// A figure past PHP_INT_MAX is refused as too large, without a cast warning.
function test_erp_cheque_amount_refusal_too_large()
{
	$warned = false;
	set_error_handler(function () use (&$warned) {
		$warned = true;
		return true;
	});
	$huge = erp_cheque_amount_refusal(erp_kurus('99999999999999999999'));
	restore_error_handler();

	pg_assert_same(lang('The amount is too large.'), $huge, 'twenty digits are too large');
	pg_assert_same(false, $warned, 'no warning on the way');
	pg_assert_same(lang('The amount is too large.'), erp_cheque_amount_refusal(erp_kurus('92233720368547758')), 'seventeen digits are too large');
	pg_assert_same(lang('The amount is too large.'), erp_cheque_amount_refusal(ERP_CHEQUE_MAX_AMOUNT + 1), 'one past the limit');
}

// Ordinary amounts pass; zero, negative and non-numbers ask for an amount.
function test_erp_cheque_amount_refusal_ordinary()
{
	pg_assert_same('', erp_cheque_amount_refusal(1), 'one kurus');
	pg_assert_same('', erp_cheque_amount_refusal(ERP_CHEQUE_MAX_AMOUNT), 'the limit itself');
	pg_assert_same('', erp_cheque_amount_refusal(erp_kurus('999999999999,99')), 'the largest fifteen-character figure that fits');
	pg_assert_same('', erp_cheque_amount_refusal('1500'), 'a numeric string');

	foreach (array(0, -5, 0.5, 'abc', null, array(1)) as $amount) {
		pg_assert_same(lang('Enter an amount greater than zero.'), erp_cheque_amount_refusal($amount), var_export($amount, true));
	}
}

// The reason goes in under the step's name, after the notes.
function test_erp_cheque_notes_with_reason_labels()
{
	pg_assert_same('', erp_cheque_notes_with_reason('', 'Bounced', ''), 'nothing to add');
	pg_assert_same('kept', erp_cheque_notes_with_reason(' kept ', 'Bounced', '  '), 'no reason keeps the notes');
	pg_assert_same('Bounced: no funds', erp_cheque_notes_with_reason('', 'Bounced', 'no funds'), 'no notes before');
	pg_assert_same('due in March · Bounced: no funds', erp_cheque_notes_with_reason('due in March', 'Bounced', 'no funds'), 'after the notes');
}

// Long notes are shortened at their end so the reason is never lost.
function test_erp_cheque_notes_with_reason_long_notes()
{
	$notes = str_repeat('ş', 254);
	$reason = str_repeat('r', 150);
	$result = erp_cheque_notes_with_reason($notes, 'Karşılıksız', $reason);

	pg_assert_same(255, mb_strlen($result), 'fills the column exactly');
	pg_assert_same(true, mb_substr($result, -mb_strlen('Karşılıksız: ' . $reason)) === 'Karşılıksız: ' . $reason, 'the reason is whole at the end');
	pg_assert_contains('… · Karşılıksız: ', $result, 'the old notes are marked as shortened');
}
