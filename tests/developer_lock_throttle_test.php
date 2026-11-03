<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for developer_lock.php: the developer PIN is a shared secret of a few
 * digits, so a wrong one is counted by the sign-in throttle like every other
 * door that takes a password, and a locked name or address is refused before
 * the PIN is compared.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

/**
 * The part of developer_lock.php that handles a posted PIN.
 *
 * @return string
 */
function developer_lock_throttle_test_post_branch()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/developer_lock.php');
	$from = strpos($source, 'validate_token_field();');

	return ($from === false) ? '' : (string) substr($source, $from);
}

// The guard is asked before the PIN is compared.
function test_developer_lock_asks_the_throttle_before_the_pin()
{
	$branch = developer_lock_throttle_test_post_branch();
	$guard = strpos($branch, 'pg_login_throttle_guard(');
	$compare = strpos($branch, 'hash_equals(');

	pg_assert_true($branch !== '', 'the posted branch');
	pg_assert_true($guard !== false, 'the guard is asked');
	pg_assert_true(($guard !== false) && ($compare !== false) && ($guard < $compare), 'before the PIN is compared');
}

// A wrong PIN is counted; a right one clears the account's counters.
function test_developer_lock_counts_a_wrong_pin()
{
	$branch = developer_lock_throttle_test_post_branch();
	$wrong = strpos($branch, "lang('Unlock pin is not correct')");
	$count = strpos($branch, 'pg_login_record_failure(');
	$pass = strpos($branch, 'pg_login_throttle_pass(');
	$compare = strpos($branch, 'hash_equals(');

	pg_assert_true($count !== false, 'a wrong PIN is counted');
	pg_assert_true(($count !== false) && ($wrong !== false) && ($count > $compare), 'in the wrong-PIN branch');
	pg_assert_true(($pass !== false) && ($pass > $compare) && (($wrong === false) || ($pass < $wrong)), 'a right PIN clears the counters');
}
