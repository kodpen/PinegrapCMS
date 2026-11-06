<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * A receipt or payment names an account that exists: the panel screen and
 * a bank statement line send the posted id as it came, and a missing one
 * wrote a ledger row no account owned while the till still moved.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/erp/cash.php');

function test_erp_receipt_account_row_refusal()
{
	pg_assert_true(erp_receipt_account_row_refusal(null) !== '', 'a missing account is refused');
	pg_assert_true(erp_receipt_account_row_refusal(false) !== '', 'a failed read is refused');
	pg_assert_same('', erp_receipt_account_row_refusal(array('id' => '5')), 'a found account passes');
}

function test_erp_post_receipt_check_reads_the_account()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/erp/cash.php');
	$start = strpos($source, 'function erp_post_receipt_check(');
	$body = substr($source, $start, strpos($source, "\nfunction ", $start + 1) - $start);

	pg_assert_contains("erp_receipt_account_row_refusal(db_item(\"SELECT id FROM erp_accounts WHERE id = '\" . \$account_id . \"' LIMIT 1\"))", $body, 'the account is read before the till');
}
