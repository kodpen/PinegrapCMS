<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the order of POST /auth/refresh (api_auth_refresh(),
 * includes/api/resources/account.php): the device application is checked
 * before the refresh token is exchanged. A refusal for a temporary reason
 * (switch off, IP allow list, expiry) must leave the presented token current;
 * exchanged first, the client would hold only the row's previous token, and
 * presenting it after the 60 s grace revokes the device.
 *
 * The exchange and the application read need a database, so the order is
 * read from the function's source.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The body of api_auth_refresh(), up to the next function.
function api_auth_refresh_order_test_body()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/api/resources/account.php');

	$start = strpos($source, 'function api_auth_refresh(');

	pg_assert_true($start !== false, 'api_auth_refresh() is in account.php');

	$end = strpos($source, "\nfunction ", $start + 1);

	return substr($source, $start, ($end === false) ? null : ($end - $start));
}

// The row is found without touching it, the application is checked, and only
// then is the token exchanged.
function test_api_auth_refresh_checks_the_application_before_the_exchange()
{
	$body = api_auth_refresh_order_test_body();

	$find   = strpos($body, 'api_device_find_refresh(');
	$check  = strpos($body, 'api_device_app_check(');
	$rotate = strpos($body, 'api_device_rotate(');

	pg_assert_true($find !== false, 'the row is looked up without an exchange');
	pg_assert_true($check !== false, 'the application is checked');
	pg_assert_true($rotate !== false, 'the token is exchanged');
	pg_assert_true($find < $check, 'the lookup comes before the check');
	pg_assert_true($check < $rotate, 'the check comes before the exchange');
}

// The application checked is the one of the row found, not of the exchanged
// row, which does not exist yet at that point.
function test_api_auth_refresh_checks_the_found_rows_application()
{
	$body = api_auth_refresh_order_test_body();

	$rotate = strpos($body, 'api_device_rotate(');
	$read   = strpos($body, 'FROM api_apps');

	pg_assert_true($read !== false, 'the application is read');
	pg_assert_true($read < $rotate, 'the application is read before the exchange');
}
