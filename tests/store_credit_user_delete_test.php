<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Deleting an account voids its open store credit before the account row
 * goes, and releases only cards brought to zero (#9016).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function store_credit_user_delete_test_source($name)
{
	$function = new ReflectionFunction($name);
	$lines = file($function->getFileName());

	return implode('', array_slice($lines, $function->getStartLine() - 1, $function->getEndLine() - $function->getStartLine() + 1));
}

function test_store_credit_user_delete_voids_before_the_account_goes()
{
	pg_assert_true(function_exists('pg_credit_forget_user'), 'the account\'s credit has a forget step');

	// user_write.php is not loaded by the runner: read the function from the file.
	$file = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/user_write.php');
	$start = strpos($file, 'function pg_user_delete(');
	pg_assert_true($start !== false, 'pg_user_delete() is found');
	$source = substr($file, (int) $start);
	$end = strpos($source, "\n}\n");
	$source = substr($source, 0, ($end === false) ? strlen($source) : $end);
	$forget = strpos($source, 'pg_credit_forget_user($user_id)');
	$delete = strpos($source, 'DELETE FROM user WHERE');
	pg_assert_true(($forget !== false) && ($delete !== false) && ($forget < $delete), 'credit is voided before the user row goes');
}

function test_store_credit_forget_user_voids_then_releases_only_closed_cards()
{
	$source = store_credit_user_delete_test_source('pg_credit_forget_user');
	$void = strpos($source, 'pg_credit_void(');
	$release = strpos($source, 'SET user_id = 0');
	pg_assert_true(($void !== false) && ($release !== false) && ($void < $release), 'voided through pg_credit_void(), then released');
	pg_assert_contains("lang('Account deleted')", $source, 'the void note names the reason');
	pg_assert_true(preg_match('/SET user_id = 0[^"]*balance = 0/', $source) === 1, 'a card still holding a balance stays bound to the id');
}
