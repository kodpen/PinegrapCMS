<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the path an operator's excluded path is matched against
 * (waf_path_to_script(), waf.php): it stops at the script the server runs, so
 * PATH_INFO written after it cannot claim an exclusion.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/waf.php');

// PATH_INFO after a script is cut off; the script's own path stays.
function test_waf_exclusion_path_stops_at_the_script()
{
	pg_assert_same('/pinegrap/index.php', waf_path_to_script('/pinegrap/index.php/iyzico_callback'), 'appended to the sign-in screen');
	pg_assert_same('/pinegrap/forgot_password.php', waf_path_to_script('/pinegrap/forgot_password.php/x/iyzico_callback'), 'several segments');
	pg_assert_same('/PINEGRAP/INDEX.PHP', waf_path_to_script('/PINEGRAP/INDEX.PHP/iyzico_callback'), 'IIS letter case');
	pg_assert_same('/pinegrap/iyzipay_callback.php', waf_path_to_script('/pinegrap/iyzipay_callback.php'), 'the callback script itself');
}

// An address the router serves has no script in it and is kept whole.
function test_waf_exclusion_path_keeps_router_addresses()
{
	pg_assert_same('/odeme/iyzico_callback', waf_path_to_script('/odeme/iyzico_callback'), 'a page address');
	pg_assert_same('/', waf_path_to_script('/'), 'the home page');
	pg_assert_same('', waf_path_to_script(''), 'no path');
}
