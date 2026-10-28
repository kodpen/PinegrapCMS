<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the refused sign-in (includes/fn/auth.php): the one message the
 * sign-in screens give for an unknown name and a wrong password, and the
 * stand-in hash an unknown name is checked against, so both refusals cost
 * the same password_verify().
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_login_refusal_message_names_neither_half()
{
	$message = pg_login_refused_message();

	pg_assert_same('The email address, username or password you entered is incorrect. Please remember that passwords are case sensitive.', $message);
	pg_assert_false(strpos($message, 'could not be found') !== false);
}

function test_login_refusal_message_adds_the_forgot_password_hint()
{
	pg_assert_contains('forgot password link', pg_login_refused_message(true));
	pg_assert_false(strpos(pg_login_refused_message(false), 'forgot') !== false);
}

function test_login_refusal_message_for_the_current_password()
{
	pg_assert_same('The email address, username or current password you entered is incorrect. Please remember that passwords are case sensitive.', pg_login_refused_message(false, true));
}

// The stand-in hash must cost what a real one costs on this PHP: the cost
// password_hash() gives now, which is also what password_needs_rehash()
// brings every stored hash to.
function test_login_refusal_dummy_hash_costs_like_a_real_one()
{
	$dummy = password_get_info(pg_password_dummy_hash());
	$real = password_get_info(password_hash('x', PASSWORD_DEFAULT));

	pg_assert_same($real['algo'], $dummy['algo']);
	pg_assert_same($real['options']['cost'], $dummy['options']['cost']);
	pg_assert_false(password_needs_rehash(pg_password_dummy_hash(), PASSWORD_DEFAULT));
}

function test_login_refusal_dummy_verify_is_always_false()
{
	pg_assert_false(pg_password_dummy_verify(''));
	pg_assert_false(pg_password_dummy_verify('pg-dummy-password'));
	pg_assert_false(pg_password_dummy_verify(array('x')));
}
