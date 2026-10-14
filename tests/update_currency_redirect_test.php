<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the redirect of update_currency.php, the currency picker's form
 * target. It sends the visitor back to the posted send_to, which now goes
 * through pg_safe_redirect_path() like every other send_to redirect: a path on
 * this site is kept as it came, anything else sends the visitor to the home
 * page instead of to another host.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function _test_update_currency_redirect_home()
{
	return defined('PATH') ? PATH : '/';
}

// What the currency forms post: the page the visitor is on, query string and
// fragment included. It comes back unchanged.
function test_update_currency_redirect_keeps_site_path()
{
	$paths = array(
		'/',
		'/shop',
		'/shop/catalog?previous_url_id=2&tags[]=a#software_currency',
		'/ürünler/çay?sıra=artan',
	);

	foreach ($paths as $path) {
		pg_assert_same($path, pg_safe_redirect_path($path), $path);
	}
}

// Another host, in any of the forms a browser follows after
// https://site.com, ends on the home page.
function test_update_currency_redirect_external_goes_home()
{
	$targets = array(
		'@evil.com/x',
		'.evil.com/x',
		'//evil.com/x',
		'/\\evil.com/x',
		'https://evil.com/x',
		'evil.com',
		"/shop\r\nSet-Cookie: a=b",
		'',
	);

	foreach ($targets as $target) {
		pg_assert_same(_test_update_currency_redirect_home(), pg_safe_redirect_path($target), json_encode($target));
	}
}

// send_to[]=x is removed by init.php, so the redirect sees no send_to and goes
// home; the helper also refuses an array on its own.
function test_update_currency_redirect_array_send_to_goes_home()
{
	$saved = array($_GET, $_POST, $_REQUEST);
	$_GET = array();
	$_POST = array('send_to' => array('https://evil.com/x'), 'currency_id' => '2');
	$_REQUEST = $_POST;

	try {
		pg_request_single_values(array('send_to', 'from'));

		pg_assert_same(_test_update_currency_redirect_home(), pg_safe_redirect_path(($_REQUEST['send_to'] ?? '')), 'after init.php');
	} finally {
		list($_GET, $_POST, $_REQUEST) = $saved;
	}

	pg_assert_same(_test_update_currency_redirect_home(), pg_safe_redirect_path(array('/shop')), 'array given directly');
}

// update_currency.php builds its Location from the checked send_to, and keeps
// an array currency_id out of the session.
function test_update_currency_redirect_source()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/update_currency.php');

	pg_assert_contains("header('Location: ' . URL_SCHEME . HOSTNAME . pg_safe_redirect_path((\$_REQUEST['send_to'] ?? '')));", $source, 'checked redirect');
	pg_assert_contains("is_scalar(\$_REQUEST['currency_id'] ?? null)", $source, 'scalar currency_id');
}

// No screen puts a request value straight after the host name in a Location
// header, where @evil.com/x becomes https://site.com@evil.com/x.
function test_update_currency_redirect_no_raw_request_after_host()
{
	$found = array();

	foreach (glob(PG_FUNCTIONS_DIR . '/*.php') as $file) {
		if (preg_match('/Location:\s*\'\s*\.\s*URL_SCHEME\s*\.\s*(HOSTNAME|\$_SERVER\[\'HTTP_HOST\'\])\s*\.\s*\$_(REQUEST|GET|POST)\[/', file_get_contents($file))) {
			$found[] = basename($file);
		}
	}

	pg_assert_same(array(), $found, 'screens redirecting to a raw request value');
}
