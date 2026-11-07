<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for pg_request_uri_from_server() (includes/fn/request_scheme.php):
 * the request's address as get_request_uri(), router_get_request_url() and
 * the prototype's Secure Mode redirect read it. X-Rewrite-URL counts only on
 * IIS, where ISAPI_Rewrite sets it; elsewhere it is a header any client may
 * send.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_request_uri_rewrite_header_only_on_iis()
{
	$header = array('HTTP_X_REWRITE_URL' => '/other-page', 'REQUEST_URI' => '/');

	pg_assert_same('/other-page', pg_request_uri_from_server($header + array('SERVER_SOFTWARE' => 'Microsoft-IIS/10.0')), 'IIS with ISAPI_Rewrite');
	pg_assert_same('/', pg_request_uri_from_server($header + array('SERVER_SOFTWARE' => 'Apache/2.4.62 (Debian)')), 'Apache');
	pg_assert_same('/', pg_request_uri_from_server($header + array('SERVER_SOFTWARE' => 'nginx/1.27.0')), 'nginx');
	pg_assert_same('/', pg_request_uri_from_server($header), 'no server software');
	pg_assert_same('/', pg_request_uri_from_server(array('HTTP_X_REWRITE_URL' => '', 'REQUEST_URI' => '/', 'SERVER_SOFTWARE' => 'Microsoft-IIS/10.0')), 'an empty header on IIS');
}

function test_request_uri_without_header()
{
	pg_assert_same('/about?x=1', pg_request_uri_from_server(array('REQUEST_URI' => '/about?x=1', 'SERVER_SOFTWARE' => 'Microsoft-IIS/10.0')), 'IIS');
	pg_assert_same('/about', pg_request_uri_from_server(array('REQUEST_URI' => '/about', 'SERVER_SOFTWARE' => 'Apache')), 'Apache');
	pg_assert_same('/p.php?a=1', pg_request_uri_from_server(array('PHP_SELF' => '/p.php', 'QUERY_STRING' => 'a=1')), 'no REQUEST_URI');
	pg_assert_same('/p.php', pg_request_uri_from_server(array('PHP_SELF' => '/p.php', 'QUERY_STRING' => '')), 'no query string');
	pg_assert_same('', pg_request_uri_from_server(array()), 'nothing at all');
}

// Every reader of the address goes through the one choice.
function test_request_uri_callers_share_the_choice()
{
	$root = PG_FUNCTIONS_DIR;

	foreach (array('includes/fn/forms.php', 'get_file.php', 'router.php', 'includes/fn/prototype_links.php') as $file) {
		$source = (string) file_get_contents($root . '/' . $file);
		pg_assert_contains('pg_request_uri_from_server(', $source, $file);
		pg_assert_true(strpos($source, "return \$_SERVER['HTTP_X_REWRITE_URL']") === false, $file . ' reads the header itself');
	}
}
