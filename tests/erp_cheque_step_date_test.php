<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the date of a cheque or note step (includes/erp/cheques.php,
 * erp_cheque_step_date_refusal()): a step cannot be dated before the day the
 * cheque was taken or given, or the portfolio till would show it leaving
 * before it arrived.
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

function test_erp_cheque_step_date_not_before_the_cheque()
{
	pg_assert_same('', erp_cheque_step_date_refusal('2026-11-01', '2026-11-01'), 'the same day is allowed');
	pg_assert_same('', erp_cheque_step_date_refusal('2026-12-15', '2026-11-01'), 'a later day is allowed');
	pg_assert_contains('01/11/2026', erp_cheque_step_date_refusal('2026-10-01', '2026-11-01'), 'an earlier day is refused, naming the cheque date');
	pg_assert_contains(lang('Please enter a valid date.'), erp_cheque_step_date_refusal('1/10/2026', '2026-11-01'), 'a malformed date is refused');
}

// The step itself asks, so the screen and the API get the same refusal.
function test_erp_cheque_step_asks_the_date_refusal()
{
	$src = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/erp/cheques.php');
	$step = substr($src, (int) strpos($src, 'function erp_cheque_step('));

	pg_assert_contains("erp_cheque_step_date_refusal(\$date, (string) \$cheque['doc_date'])", $step, 'the step checks the date against doc_date');
}
