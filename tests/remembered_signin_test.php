<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the remembered sign-in (the software[auth] token) refusals that
 * initialize_user() and get_file.php share from includes/authentication.php:
 * a disabled account, a member on the e-mail block list and an account whose
 * role requires a second factor it does not have.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function remembered_signin_test_body($file, $function)
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
	$start = strpos($source, 'function ' . $function . '(');

	if ($start === false) {
		return '';
	}

	$next = strpos($source, "\nfunction ", $start + 1);

	return ($next === false) ? substr($source, $start) : substr($source, $start, $next - $start);
}

// The decision itself: the first reason wins, in the order the panel asks.
function test_remembered_refusal_order()
{
	pg_assert_same('', pg_auth_remembered_refusal(false, false, false), 'nothing refuses');
	pg_assert_same('disabled', pg_auth_remembered_refusal(true, false, false), 'a disabled account');
	pg_assert_same('disabled', pg_auth_remembered_refusal(true, true, true), 'disabled is named first');
	pg_assert_same('blocked', pg_auth_remembered_refusal(false, true, false), 'a blocked member');
	pg_assert_same('blocked', pg_auth_remembered_refusal(false, true, true), 'blocked before the factor');
	pg_assert_same('factor', pg_auth_remembered_refusal(false, false, true), 'a required factor missing');
}

// The block list match, shared by pg_email_blocked() and get_file.php.
function test_remembered_email_on_list()
{
	$list = "spam@example.com\n*@junk.test; Şirket.com.tr,  ";

	pg_assert_true(pg_auth_email_on_list('spam@example.com', $list), 'a whole address');
	pg_assert_true(pg_auth_email_on_list('SPAM@Example.com', $list), 'letter case aside');
	pg_assert_true(pg_auth_email_on_list('anyone@junk.test', $list), 'a starred domain');
	pg_assert_true(pg_auth_email_on_list('ali@şirket.com.tr', $list), 'a bare domain, multibyte folded');
	pg_assert_false(pg_auth_email_on_list('ham@example.com', $list), 'another address of the domain');
	pg_assert_false(pg_auth_email_on_list('a@notjunk.test', $list), 'a domain that only ends the same');
	pg_assert_false(pg_auth_email_on_list('', $list), 'no address');
	pg_assert_false(pg_auth_email_on_list('spam@example.com', ''), 'an empty list');
}

// initialize_user() and get_file.php ask the same function, so a file
// request never opens a session the panel would refuse.
function test_remembered_refusal_shared_by_both_paths()
{
	$panel = remembered_signin_test_body('includes/fn/auth.php', 'initialize_user');
	pg_assert_contains('pg_auth_remembered_signin_refusal($token_user_id)', $panel, 'initialize_user() asks it');

	$file = remembered_signin_test_body('get_file.php', 'initialize_user');
	pg_assert_contains('pg_auth_remembered_signin_refusal($token_user_id)', $file, 'get_file.php asks it');
	pg_assert_true(strpos($file, 'pg_auth_user_disabled($token_user_id)') === false, 'get_file.php no longer checks only the disabled flag');

	// pg_email_blocked() keeps one match with the get_file.php side.
	pg_assert_contains('pg_auth_email_on_list(', remembered_signin_test_body('includes/fn/auth.php', 'pg_email_blocked'), 'pg_email_blocked() delegates');
}
