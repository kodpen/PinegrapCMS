<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Signing out of an account opened with "log in as" (login_as_user.php)
 * landed the operator on the site's logout page, which greeted them signed in
 * as themselves (the remember-me cookie logout() keeps) and asked "Do you want
 * to log out?". logout.php now sends them back to Edit User of the account
 * they left (pg_logout_login_as_return()).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_logout_login_as_return_only_after_log_in_as()
{
	$session = $_SESSION ?? null;
	$cookie = $_COOKIE['software'] ?? null;

	$_SESSION = array('sessionuserid' => 5, 'sessionusername' => 'elif');
	$_COOKIE['software']['auth'] = str_repeat('a', 24) . ':' . str_repeat('b', 64);

	pg_assert_same(array(), pg_logout_login_as_return(), 'an ordinary sign-out goes on to the logout page');

	$_SESSION['software']['logged_in_as_different_user'] = true;
	unset($_COOKIE['software']['auth']);

	pg_assert_same(array(), pg_logout_login_as_return(), 'without the operator\'s remember-me cookie the sign-out ends both accounts');

	$_SESSION = is_array($session) ? $session : array();

	if ($cookie === null) {
		unset($_COOKIE['software']);
	} else {
		$_COOKIE['software'] = $cookie;
	}
}

function test_logout_reads_the_return_before_the_session_ends()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/logout.php');

	$read = strpos($source, '$login_as_return = pg_logout_login_as_return();');
	$logout = strpos($source, "\nlogout();");
	$back = strpos($source, 'if ($login_as_return) {');
	$send_to = strpos($source, "if ((\$_REQUEST['send_to'] ?? '') != '') {\n    \$send_to = pg_safe_redirect_path");

	pg_assert_true(($read !== false) && ($logout !== false) && ($read < $logout), 'the return is read while the session still holds the account left');
	pg_assert_true(($back !== false) && ($back > $logout) && ($send_to !== false) && ($back < $send_to), 'the operator goes back to the panel before send_to and the logout page are looked at');
	pg_assert_contains("new liveform((\$login_as_return['user_id'] > 0) ? 'edit_user' : 'view_users')", $source, 'the notice waits on the screen the operator lands on');
}
