<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests that the forgot-password form answers every address the same way:
 * no screen of it reads or shows the account's password hint. Showing the
 * hint for a known address, and the confirmation for an unknown one, told
 * anyone which addresses hold an account and what their hint said.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The source of one PHP function, from its declaration to the next top-level
// function declaration (or the end of the file).
function pg_test_forgot_password_function_source($file, $function)
{
	$source = (string)file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
	$start = strpos($source, 'function ' . $function . '(');
	if ($start === false) {
		return '';
	}
	$end = strpos($source, "\nfunction ", $start + 1);
	return ($end === false) ? substr($source, $start) : substr($source, $start, $end - $start);
}

// The processor, the legacy screen and its layouts hold no hint read and no
// hint screen.
function test_forgot_password_no_hint_screens_hold_no_hint()
{
	$files = array(
		'forgot_password.php',
		'get_forgot_password.php',
		'includes/templates/forgot_password.php',
		'includes/templates/forgot_password_system.php',
	);

	foreach ($files as $file) {
		$source = (string)file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
		pg_assert_false(stripos($source, 'user_password_hint') !== false, $file . ' reads user_password_hint');
		pg_assert_false(strpos($source, "'password_hint')") !== false, $file . ' has a password_hint screen');
	}
}

// The designed forgot-password widget neither reads the hint nor fills a
// password_hint section or token.
function test_forgot_password_no_hint_widget_holds_no_hint()
{
	$source = pg_test_forgot_password_function_source('includes/fn/widgets.php', '_render_system_widget_forgot_password');
	pg_assert_true($source !== '', 'renderer found');
	pg_assert_false(stripos($source, 'password_hint') !== false, 'renderer mentions password_hint');
}

// The designer twin's forgot-password starter has no hint section, and the
// token and section lists offer none for this widget.
function test_forgot_password_no_hint_designer_twin()
{
	$source = (string)file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	pg_assert_false(strpos($source, 'only on the hint screen') !== false, 'designer lists a hint-screen section or token');
	pg_assert_false(strpos($source, 'Password hint (filled only on the hint screen)') !== false, 'designer lists the hint token');
}
