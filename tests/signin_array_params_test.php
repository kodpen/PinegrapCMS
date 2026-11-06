<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests that the password sign-in screens (index.php, registration_entrance.php,
 * membership_entrance.php) treat an account name or password posted as an
 * array (email[]=) as not given: such a value reached escape() in
 * validate_login() and stopped the request with a TypeError on PHP 8.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Asserts that $list stands in $file after $after and before $before.
function signin_array_params_test_order($file, $list, $after, $before)
{
	$code = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
	$at = strpos($code, $list);

	pg_assert_true($at !== false, $file . ': the credential fields are single values');
	pg_assert_true($at !== false && $at > strpos($code, $after), $file . ': after the fields are stored');
	pg_assert_true($at !== false && $at < strpos($code, $before), $file . ': before the throttle reads the account name');
}

function test_index_login_fields_are_single_values()
{
	signin_array_params_test_order('index.php',
		"pg_liveform_single_values(\$liveform, array('email', 'password', 'remember_me'));",
		'$liveform->add_fields_to_session();', 'pg_login_throttle_guard($username);');
}

function test_registration_entrance_login_fields_are_single_values()
{
	signin_array_params_test_order('registration_entrance.php',
		"pg_liveform_single_values(\$login_form, array('email', 'password', 'login_remember_me'));",
		'$login_form->add_fields_to_session();', 'pg_login_throttle_guard($username);');
}

function test_membership_entrance_login_fields_are_single_values()
{
	signin_array_params_test_order('membership_entrance.php',
		"pg_liveform_single_values(\$liveform, array('u', 'p', 'login_remember_me'));",
		'$liveform->add_fields_to_session();', 'pg_login_throttle_guard($username);');
}

function test_sign_in_screens_take_one_send_to()
{
	foreach (array('index.php', 'membership_entrance.php') as $file) {
		$code = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
		pg_assert_contains("pg_request_single_values(array('send_to'", $code, $file . ': send_to is one value');
	}
}
