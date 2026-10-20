<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the install screen's unlock form (install/index.php): a wrong
 * password there is counted by the sign-in throttle, whose counters live in
 * the firewall's functions. The screen never runs init.php, so it loads
 * waf.php itself; without it pg_login_throttle_ready() is false there and
 * neither the guard nor the count does anything.
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
 * The source of the install screen.
 *
 * @return string
 */
function install_unlock_throttle_test_source()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/install/index.php');
}

// The unlock form asks the throttle before it looks at the password and
// counts a wrong one, as the sign-in screen does.
function test_install_unlock_form_counts_through_the_sign_in_throttle()
{
	$source = install_unlock_throttle_test_source();
	$from = strpos($source, 'function output_install_lock_screen(');

	pg_assert_true($from !== false, 'the lock screen');

	$screen = (string) substr($source, (int) $from);
	$next = preg_match('/\nfunction [a-z_]+\(/', $screen, $match, PREG_OFFSET_CAPTURE, 1);
	$screen = $next ? substr($screen, 0, $match[0][1]) : $screen;

	$guard = strpos($screen, 'pg_login_throttle_guard(');
	$password = strpos($screen, 'pg_password_verify(');

	pg_assert_true($guard !== false, 'the guard is asked');
	pg_assert_true(($guard !== false) && ($password !== false) && ($guard < $password), 'before the password is checked');
	pg_assert_contains('pg_login_record_failure(', $screen, 'a wrong password is counted');
}

// The counters come from waf.php, which the screen loads at the top level
// before the lock screen can run. Nothing on the screen calls a waf_*
// function by name, so the line looks removable; it is not.
function test_install_screen_loads_the_firewall_functions_before_the_lock_screen()
{
	$source = install_unlock_throttle_test_source();

	$loaded = preg_match("~^require(?:_once)?\s*\(?\s*(?:dirname\(__FILE__\)|__DIR__)\s*\.\s*'/\.\./waf\.php'~m", $source, $match, PREG_OFFSET_CAPTURE);
	$locked = strpos($source, 'output_install_lock_screen();');

	pg_assert_same(1, $loaded, 'waf.php is required at the top level');
	pg_assert_true($locked !== false, 'the lock screen is run');
	pg_assert_true(($loaded === 1) && ($locked !== false) && ($match[0][1] < $locked), 'before the lock screen runs');

	// What pg_login_throttle_ready() looks for is defined there.
	$waf = (string) file_get_contents(PG_FUNCTIONS_DIR . '/waf.php');

	foreach (array('waf_table_has_column', 'waf_rate_record', 'waf_rate_hits') as $name) {
		pg_assert_contains('function ' . $name . '(', $waf, $name);
	}
}
