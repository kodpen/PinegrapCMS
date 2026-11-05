<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests the affiliate link the affiliate welcome e-mail carries: built on
 * the site's configured address (scheme, hostname, path) as the My Account
 * page builds it, never on the Host header of the request that sent the
 * mail.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_affiliate_welcome_link_on_configured_address()
{
	pg_assert_same('https://shop.example/?a=ABC123', pg_affiliate_link('https://', 'shop.example', '/', 'ABC123'), 'site at the root');
	pg_assert_same('https://shop.example/store/?a=ABC123', pg_affiliate_link('https://', 'shop.example', '/store/', 'ABC123'), 'site in a folder keeps its path');
}

function test_affiliate_welcome_link_encodes_the_code()
{
	pg_assert_same('http://shop.example/?a=A%26B+C', pg_affiliate_link('http://', 'shop.example', '/', 'A&B C'), 'the code is URL-encoded');
}

function test_affiliate_welcome_content_does_not_read_the_request_host()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/get_affiliate_welcome_screen_content.php');

	pg_assert_false(strpos($source, 'HTTP_HOST') !== false, 'the welcome content never takes the Host header');
	pg_assert_true(strpos($source, 'pg_affiliate_link(URL_SCHEME, HOSTNAME_SETTING, PATH') !== false, 'the link is built on the configured address');
}
