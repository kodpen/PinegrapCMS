<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a sign-in step that runs out: a pending record older than its
 * lifetime is told apart from one that was never started, and the second
 * step and the device-limit question say so on the sign-in screen.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function mfa_pending_expiry_record($age)
{
	return array(
		'user_id'  => 7,
		'username' => 'ayse',
		'send_to'  => '/shop',
		'remember' => 0,
		'mode'     => 'verify',
		'origin'   => 'membership_entrance.php',
		'time'     => 1000000 - $age,
	);
}

function test_mfa_step_state_live_within_lifetime()
{
	pg_assert_same('live', pg_sign_in_step_state(mfa_pending_expiry_record(0), 600, 1000000));
	pg_assert_same('live', pg_sign_in_step_state(mfa_pending_expiry_record(600), 600, 1000000));
}

function test_mfa_step_state_expired_after_lifetime()
{
	pg_assert_same('expired', pg_sign_in_step_state(mfa_pending_expiry_record(601), 600, 1000000));
	pg_assert_same('expired', pg_sign_in_step_state(mfa_pending_expiry_record(86400), 600, 1000000));
}

function test_mfa_step_state_missing_when_never_started_or_malformed()
{
	pg_assert_same('missing', pg_sign_in_step_state(null, 600, 1000000));
	pg_assert_same('missing', pg_sign_in_step_state('x', 600, 1000000));
	pg_assert_same('missing', pg_sign_in_step_state(array(), 600, 1000000));

	$no_user = mfa_pending_expiry_record(900);
	$no_user['user_id'] = 0;
	pg_assert_same('missing', pg_sign_in_step_state($no_user, 600, 1000000));

	$no_time = mfa_pending_expiry_record(900);
	unset($no_time['time']);
	pg_assert_same('missing', pg_sign_in_step_state($no_time, 600, 1000000));
}

function test_mfa_origin_screen_keeps_only_the_sign_in_screens()
{
	pg_assert_same('index.php', pg_mfa_origin_screen('index.php'));
	pg_assert_same('membership_entrance.php', pg_mfa_origin_screen('membership_entrance.php'));
	pg_assert_same('registration_entrance.php', pg_mfa_origin_screen('registration_entrance.php'));
	pg_assert_same('index.php', pg_mfa_origin_screen('https://evil.example/'));
	pg_assert_same('index.php', pg_mfa_origin_screen(null));
}

function test_mfa_origin_back_path_carries_send_to()
{
	pg_assert_same('', pg_mfa_origin_back_path('index.php', ''));
	pg_assert_same('?send_to=%2Fshop%3Fa%3D1', pg_mfa_origin_back_path('index.php', '/shop?a=1'));
	pg_assert_same('membership_entrance.php?send_to=%2Fshop', pg_mfa_origin_back_path('membership_entrance.php', '/shop'));
	pg_assert_same('registration_entrance.php', pg_mfa_origin_back_path('registration_entrance.php', ''));
}

function test_mfa_origin_form_names_the_account_field()
{
	pg_assert_same(array('login', 'email'), pg_mfa_origin_form('index.php'));
	pg_assert_same(array('login', 'email'), pg_mfa_origin_form('registration_entrance.php'));
	pg_assert_same(array('membership_entrance', 'u'), pg_mfa_origin_form('membership_entrance.php'));
}

// Both screens answer a run-out record with a sentence on the sign-in screen
// before their silent redirect for a record that was never there.
function test_mfa_and_device_limit_screens_say_the_step_ran_out()
{
	$mfa = (string) file_get_contents(PG_FUNCTIONS_DIR . '/mfa.php');
	$expired_at = strpos($mfa, "pg_sign_in_step_state(\$_SESSION['software']['mfa_pending']");
	pg_assert_true($expired_at !== false, 'mfa.php checks for a run-out record');
	pg_assert_true($expired_at < strpos($mfa, '$pending = pg_mfa_pending();'), 'before pg_mfa_pending() clears it');
	pg_assert_true(strpos($mfa, "lang('Your sign-in waited more than 10 minutes for its second step. Please sign in again.')") !== false, 'mfa.php says so');

	$device = (string) file_get_contents(PG_FUNCTIONS_DIR . '/device_limit.php');
	pg_assert_true(strpos($device, "pg_sign_in_step_state(\$pending, 600, time())") !== false, 'device_limit.php checks for a run-out record');
	pg_assert_true(strpos($device, "lang('Your sign-in waited more than 10 minutes for your answer. Please sign in again.')") !== false, 'device_limit.php says so');
}
