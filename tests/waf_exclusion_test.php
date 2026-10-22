<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the path an operator's excluded path is matched against
 * (waf_exclusion_path(), waf_path_to_script(), waf.php) and for the script a
 * request names for the API exemptions (waf_names_script_in()): both follow
 * the script the server resolved, so PATH_INFO, a percent-encoded script
 * segment or a dot segment written by the caller cannot claim an exemption.
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

// Does the path an exclusion is matched against name this pattern?
function waf_exclusion_test_claims($script_name, $request_uri, $pattern)
{
	return stripos(waf_exclusion_path($script_name, $request_uri), $pattern) !== false;
}

// A script the server resolved is matched by its own name: an encoded script
// segment, a dot segment or a query cannot add the excluded name to it.
function test_waf_exclusion_path_uses_the_resolved_script()
{
	$bypasses = array(
		'/pinegrap/index.ph%70/iyzico_callback' => 'encoded letter in the script segment',
		'/pinegrap/index%2ephp/iyzico_callback' => 'encoded dot in the script segment',
		'/pinegrap/index.php%2Fiyzico_callback' => 'encoded slash after the script',
		'/pinegrap/iyzico_callback/../index.php' => 'dot segment before the script',
		'/pinegrap/index.php/iyzico_callback?x=iyzico_callback' => 'PATH_INFO and query',
	);

	foreach ($bypasses as $request_uri => $label) {
		pg_assert_false(waf_exclusion_test_claims('/pinegrap/index.php', $request_uri, 'iyzico_callback'), $label);
	}

	pg_assert_same('/pinegrap/iyzipay_callback.php', waf_exclusion_path('/pinegrap/iyzipay_callback.php', '/pinegrap/iyzipay_callback.php?token=1'), 'the callback script itself');
	pg_assert_same('/pinegrap/index.php', waf_exclusion_path('/pinegrap/index.php/iyzico_callback', '/pinegrap/index.php/iyzico_callback'), 'a script name that carries PATH_INFO');
}

// A router address is matched by its request path, decoded once and cut at
// the first script segment; a dot segment matches nothing.
function test_waf_exclusion_path_reads_router_addresses_decoded()
{
	pg_assert_false(waf_exclusion_test_claims('/pinegrap/router.php', '/pinegrap/index.ph%70/iyzico_callback', 'iyzico_callback'), 'encoded script segment');
	pg_assert_false(waf_exclusion_test_claims('/pinegrap/router.php', '/pinegrap/index.php%2Fiyzico_callback', 'iyzico_callback'), 'encoded slash');
	pg_assert_false(waf_exclusion_test_claims('/pinegrap/router.php', '/pinegrap/index.php%5Ciyzico_callback', 'iyzico_callback'), 'encoded backslash');
	pg_assert_same('', waf_exclusion_path('/pinegrap/router.php', '/iyzico_callback/../x'), 'dot segment');
	pg_assert_same('', waf_exclusion_path('/pinegrap/router.php', '/iyzico_callback/%2e%2e/x'), 'encoded dot segment');
	pg_assert_same('/odeme/iyzico_callback', waf_exclusion_path('/pinegrap/router.php', '/odeme/iyzico_callback?a=1'), 'a page address');
	pg_assert_same('/odeme/iyzico callback', waf_exclusion_path('/pinegrap/router.php', '/odeme/iyzico%20callback'), 'an encoded page address');
	pg_assert_true(waf_exclusion_test_claims('/pinegrap/router.php', '/pinegrap/integration.php/products/138', 'integration.php'), 'the IIS rewrite of an API address');
	pg_assert_same('/odeme/iyzico_callback', waf_exclusion_path('', '/odeme/iyzico_callback'), 'no script name');
}

// The API exemptions follow the same rule: the resolved script, and the
// request path only for router.php.
function test_waf_names_script_in_reads_the_path_only_for_the_router()
{
	$api = array('integration.php', 'api.php');

	pg_assert_false(waf_names_script_in('/pinegrap/index.php', '/pinegrap/index.ph%70/integration.php/x', $api), 'encoded script segment before the endpoint');
	pg_assert_false(waf_names_script_in('/pinegrap/index.php', '/pinegrap/index.php/integration.php/x', $api), 'endpoint as PATH_INFO');
	pg_assert_true(waf_names_script_in('/pinegrap/integration.php', '/pinegrap/integration.php/products/138', $api), 'the endpoint itself');
	pg_assert_true(waf_names_script_in('/pinegrap/router.php', '/pinegrap/integration.php/products/138', $api), 'the IIS rewrite of an API address');
	pg_assert_true(waf_names_script_in('/pinegrap/router.php', '/pinegrap/integration%2ephp/products/138', $api), 'the IIS rewrite, encoded');
	pg_assert_false(waf_names_script_in('/pinegrap/router.php', '/pinegrap/index.ph%70/integration.php/x', $api), 'router, encoded script segment before the endpoint');
	pg_assert_false(waf_names_script_in('/pinegrap/router.php', '/x/../pinegrap/integration.php/x', $api), 'router, dot segment');
	pg_assert_false(waf_names_script_in('/pinegrap/router.php', '/odeme?x=/integration.php', $api), 'endpoint in the query');
}
