<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the block list on password sign-ins (includes/fn/auth.php): a
 * member (role 3) whose address is on the list is refused, staff are not,
 * and the password sent to api.php is held to the same rule as the sign-in
 * screens (validate_login()).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_login_email_block_refuses_a_blocked_member()
{
	pg_assert_true(pg_login_email_block_refuses(3, true));
	pg_assert_true(pg_login_email_block_refuses('3', true));
}

function test_login_email_block_leaves_staff_and_unblocked_alone()
{
	pg_assert_false(pg_login_email_block_refuses(0, true));
	pg_assert_false(pg_login_email_block_refuses('1', true));
	pg_assert_false(pg_login_email_block_refuses(2, true));
	pg_assert_false(pg_login_email_block_refuses(3, false));
}

// The body of initialize_user(), up to the next function.
function login_email_block_test_body($name)
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/auth.php');
	$start = strpos($source, 'function ' . $name . '(');

	if ($start === false) {
		return '';
	}

	$end = strpos($source, "\nfunction ", $start + 1);

	return $end === false ? substr($source, $start) : substr($source, $start, $end - $start);
}

function test_login_email_block_applies_to_the_screens_and_api_php()
{
	pg_assert_contains('pg_login_email_block_refuses($row[\'user_role\'], pg_email_blocked($row[\'user_email\']))', login_email_block_test_body('validate_login'));

	$body = login_email_block_test_body('initialize_user');
	$api = strpos($body, 'pg_login_throttle_guard(API_USERNAME);');
	$check = strpos($body, 'pg_login_email_block_refuses($cred[\'user_role\'], pg_email_blocked($cred[\'user_email\']))');
	$authenticated = strpos($body, "define('API_AUTHENTICATED', true);");

	pg_assert_true($api !== false, 'api.php sign-in present');
	pg_assert_true($check !== false, 'api.php sign-in asks the block list');
	pg_assert_true(($check > $api) && ($check < $authenticated), 'block list asked before the request is signed in');
}
