<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the note a cheque or note step leaves (includes/erp/cheques.php,
 * erp_cheque_step_note()): it says what the step did, so handing a cheque to
 * the bank does not claim a movement it never wrote.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/erp/cheques.php');

function test_erp_cheque_step_note_per_step()
{
	$moved = lang('Done. The movement is in the till and on the account.');
	$transfer = lang('Done. The amount moved between your tills as a transfer.');

	pg_assert_same(lang('Handed to the bank for collection. The money moves when the bank collects it.'), erp_cheque_step_note('deposit'), 'deposit moves no money');
	pg_assert_same($transfer, erp_cheque_step_note('collect'), 'collect is a transfer');
	pg_assert_same($transfer, erp_cheque_step_note('pay'), 'paid by the bank is a transfer');
	pg_assert_same($moved, erp_cheque_step_note('endorse'), 'endorse moves the account');
	pg_assert_same($moved, erp_cheque_step_note('bounce'), 'bounce moves the account');
}

// The cheque screen shows the step's own note.
function test_erp_cheque_screen_uses_step_note()
{
	$screen = (string) file_get_contents(PG_FUNCTIONS_DIR . '/erp_cheque.php');

	pg_assert_contains('add_notice(h(erp_cheque_step_note($step)))', $screen, 'the notice comes from the step');
}
