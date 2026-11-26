<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source pin for the Add account screen: an opening balance dated inside the
 * locked books is refused on the Date field before the account card is
 * saved, so a refused opening leaves no account behind without its opening
 * balance.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_erp_account_opening_lock_asked_before_save()
{
	$source = (string) file_get_contents(dirname(__DIR__) . '/pinegrap/add_erp_account.php');
	$check = "if ((\$opening !== 0) && (\$liveform->check_form_errors() == false) && ((\$refusal = erp_lock_refusal(\$opening_date_sql)) !== '')) {";
	$at_check = strpos($source, $check);
	$at_save = strpos($source, '$result = erp_account_save(');

	pg_assert_true($at_check !== false, 'opening date asked against the lock');
	pg_assert_true(($at_save !== false) && ($at_check < $at_save), 'asked before the card is saved');
	pg_assert_contains("\$liveform->mark_error('opening_date', \$refusal);", substr($source, (int) $at_check, 300), 'refusal marked on the Date field');
}
