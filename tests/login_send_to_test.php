<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the login form's send_to parameter (get_login.php): a send_to
 * sent as an array (?send_to[]=x) must not reach urlencode() or h(), which
 * throw a TypeError on PHP 8 and turn the login screen into a 500 page.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_login_send_to_is_read_once_as_a_scalar()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/get_login.php');

	pg_assert_same(1, substr_count($source, "\$_GET['send_to']"), 'get_login() reads send_to from the query string once');
	pg_assert_contains("\$send_to = is_scalar(\$_GET['send_to'] ?? null) ? (string) \$_GET['send_to'] : '';", $source, 'an array send_to counts as not given');
}
