<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source pins for password managers: the default Set Password card carries
 * an autocomplete="username" field naming the account, so the new password
 * is saved against it, and the API console's secret box says new-password,
 * so the browser does not fill the saved panel sign-in into it.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function password_manager_fields_source($path)
{
	return (string) file_get_contents(dirname(__DIR__) . '/pinegrap/' . $path);
}

function test_set_password_card_names_the_account()
{
	$source = password_manager_fields_source('get_set_password.php');
	$start = strpos($source, 'if ((int)$page_id <= 0) {');
	$end = strpos($source, 'render_layout(array(', (int) $start);
	pg_assert_true($start !== false && $end !== false, 'default card block found');
	$block = substr($source, (int) $start, (int) $end - (int) $start);

	pg_assert_true(
		(bool) preg_match('/<input type="text" id="set_password_username"[^>]*autocomplete="username"/', $block),
		'default card has a username field for password managers'
	);
	pg_assert_contains("h(\$user['email'] ?: \$user['username'])", $block, 'username field holds the e-mail or user name');

	// The field is in the form but posts nothing set_password.php would read.
	pg_assert_true(
		!preg_match('/<input[^>]*id="set_password_username"[^>]*\bname=/', $block),
		'username field has no name'
	);
	$form = strpos($block, '<form ');
	$field = strpos($block, '$output_username');
	$closing = strpos($block, '</form>');
	pg_assert_true($form !== false && $field > $form && $field < $closing, 'username field sits inside the form');
}

function test_api_console_secret_is_new_password()
{
	$source = password_manager_fields_source('includes/api/console.php');
	pg_assert_true(
		(bool) preg_match('/<input type="password"[^>]*id="con_secret"[^>]*autocomplete="new-password"/', $source),
		'console secret box says new-password'
	);
}
