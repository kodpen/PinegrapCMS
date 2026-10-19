<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the session <-> device token binding in
 * includes/authentication.php: what a sign-in keeps or drops, and the checks
 * pg_session_token_binding_broken() makes before it asks the database.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function _session_binding_reset()
{
	$_SESSION = array();
	unset($_COOKIE['software']);
}

// Only a well-formed selector is bound.
function test_session_binding_bind_requires_selector()
{
	_session_binding_reset();
	pg_session_bind_token('not-a-selector', 5);
	pg_assert_true(empty($_SESSION['software']['auth_selector']));

	pg_session_bind_token('0123456789abcdef01234567', 5);
	pg_assert_same('0123456789abcdef01234567', $_SESSION['software']['auth_selector']);
	pg_assert_same(5, $_SESSION['software']['auth_user_id']);
}

// A sign-in as the same account keeps the binding the sign-in screen just made.
function test_session_binding_sign_in_keeps_own_binding()
{
	_session_binding_reset();
	pg_session_bind_token('0123456789abcdef01234567', 5);
	pg_session_sign_in(5, 'alice');
	pg_assert_same('0123456789abcdef01234567', $_SESSION['software']['auth_selector']);
}

// A sign-in as another account (login_as_user.php) drops the binding.
function test_session_binding_sign_in_drops_foreign_binding()
{
	_session_binding_reset();
	pg_session_bind_token('0123456789abcdef01234567', 5);
	pg_session_sign_in(9, 'bob');
	pg_assert_true(empty($_SESSION['software']['auth_selector']));
	pg_assert_same(9, $_SESSION['sessionuserid']);
}

// A session without a binding, or without a user, has nothing to check.
function test_session_binding_unbound_session_passes()
{
	_session_binding_reset();
	pg_assert_same('', pg_session_token_binding_broken());

	$_SESSION['sessionuserid'] = 5;
	pg_assert_same('', pg_session_token_binding_broken());
}

// A cookie naming another selector than the session's is refused before any lookup.
function test_session_binding_cookie_mismatch_is_broken()
{
	_session_binding_reset();
	$_SESSION['sessionuserid'] = 5;
	pg_session_bind_token('0123456789abcdef01234567', 5);
	$_COOKIE['software']['auth'] = 'fedcba9876543210fedcba98:' . str_repeat('0', 64);

	pg_assert_same('session token does not match the device', pg_session_token_binding_broken());
	_session_binding_reset();
}
