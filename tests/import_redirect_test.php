<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the redirects of a content import (includes/import/write_redirect.php):
 * the old address each reader's items carry, the short link name it becomes,
 * the destination each kind of item gets, and the run and screen wiring.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!function_exists('pg_short_link_name_from_address')) {
	require_once(PG_FUNCTIONS_DIR . '/includes/fn/short_link_redirects.php');
}

require_once(PG_FUNCTIONS_DIR . '/includes/import/write_redirect.php');

function test_import_redirect_old_address()
{
	$post = array('kind' => 'record', 'source_key' => 'example.com:post:7', 'data' => array('link' => 'https://example.com/2020/01/hello-world/'));
	pg_assert_same('https://example.com/2020/01/hello-world/', pg_import_redirect_old_address('wordpress', $post), 'a post');

	$page = array('kind' => 'page', 'source_key' => 'example.com:post:8', 'data' => array('link' => 'https://example.com/about/'));
	pg_assert_same('https://example.com/about/', pg_import_redirect_old_address('wordpress', $page), 'a page');

	$file = array('kind' => 'file', 'source_key' => 'example.com:post:9', 'data' => array('url' => 'https://example.com/wp-content/a.jpg'));
	pg_assert_same('', pg_import_redirect_old_address('wordpress', $file), 'an attachment keeps no address');

	$product = array('kind' => 'product', 'source_key' => 'shopify:product:red-shirt', 'data' => array());
	pg_assert_same('/products/red-shirt', pg_import_redirect_old_address('shopify', $product), 'a Shopify product by its handle');

	$woo = array('kind' => 'product', 'source_key' => 'woo:product:12', 'data' => array());
	pg_assert_same('', pg_import_redirect_old_address('woocommerce', $woo), 'the WooCommerce export has no address');

	$contact = array('kind' => 'contact', 'source_key' => 'shopify:customer:x', 'data' => array());
	pg_assert_same('', pg_import_redirect_old_address('shopify', $contact), 'a contact');

	$query = array('kind' => 'record', 'source_key' => 'example.com:post:10', 'data' => array('link' => 'https://example.com/?p=10'));
	pg_assert_same('', pg_import_redirect_old_address('wordpress', $query), 'an address that is only a query string');
}

// A Squarespace post by its link, a product at both of its addresses; each
// redirect has its own source key.
function test_import_redirect_squarespace_addresses()
{
	$post = array('kind' => 'record', 'source_key' => 'pine-studio.example:post:2', 'data' => array('link' => '/blog/2026/3/14/opening-day'));
	pg_assert_same(array('/blog/2026/3/14/opening-day'), pg_import_redirect_old_addresses('squarespace', $post), 'a post by its link');

	$product = array('kind' => 'product', 'source_key' => 'squarespace:product:5f1a0001', 'data' => array('addresses' => array('/shop/linen-shirt', '/shop/p/linen-shirt')));
	pg_assert_same(array('/shop/linen-shirt', '/shop/p/linen-shirt'), pg_import_redirect_old_addresses('squarespace', $product), 'a product at both addresses');
	pg_assert_same('/shop/linen-shirt', pg_import_redirect_old_address('squarespace', $product), 'the first');

	$same = array('kind' => 'product', 'source_key' => 'squarespace:product:x', 'data' => array('addresses' => array('/shop/a', '/shop/a/', '')));
	pg_assert_same(array('/shop/a'), pg_import_redirect_old_addresses('squarespace', $same), 'one name once, none empty');

	$contact = array('kind' => 'contact', 'source_key' => 'squarespace:email:a@b.c', 'data' => array());
	pg_assert_same(array(), pg_import_redirect_old_addresses('squarespace', $contact), 'a contact');
	pg_assert_same(array('/products/red-shirt'), pg_import_redirect_old_addresses('shopify', array('kind' => 'product', 'source_key' => 'shopify:product:red-shirt')), 'Shopify unchanged');

	pg_assert_same('redirect:squarespace:product:5f', pg_import_redirect_source_key('squarespace:product:5f', 0), 'the first keeps the key');
	pg_assert_same('redirect:squarespace:product:5f:2', pg_import_redirect_source_key('squarespace:product:5f', 1), 'the second is numbered');

	$long = pg_import_redirect_source_key(str_repeat('a', 300), 1);
	pg_assert_same(191, mb_strlen($long), 'cut to the column');
	pg_assert_same(':2', mb_substr($long, -2), 'the number kept when cut');
}

function test_import_redirect_name()
{
	pg_assert_same('2020/01/hello-world', pg_import_redirect_name('https://example.com/2020/01/hello-world/'), 'path, no slashes at the ends');
	pg_assert_same('products/red-shirt', pg_import_redirect_name('/products/red-shirt'), 'a path');
	pg_assert_same('blog/çay', pg_import_redirect_name('https://example.com/blog/%C3%A7ay/'), 'decoded');
	pg_assert_same('', pg_import_redirect_name('https://example.com/'), 'the home page');
}

function test_import_redirect_is_own_address()
{
	pg_assert_true(pg_import_redirect_is_own_address(pg_import_redirect_name('https://old.example.com/pg-test-sayfa/'), 'pg-test-sayfa'), 'a root page imported under its old name');
	pg_assert_true(pg_import_redirect_is_own_address('About', 'about'), 'letter case ignored');
	pg_assert_true(!pg_import_redirect_is_own_address('about', 'about-2'), 'a page renamed for a taken name gets its redirect');
	pg_assert_true(!pg_import_redirect_is_own_address('2020/01/about', 'about'), 'a nested address is not the page name');
	pg_assert_true(!pg_import_redirect_is_own_address('', ''), 'the home page');
}

function test_import_redirect_record_url()
{
	pg_assert_same('/blog/hello-world', pg_import_redirect_record_url('blog', 'AB12CD', 'hello-world', true), 'pretty');
	pg_assert_same('/blog?r=AB12CD', pg_import_redirect_record_url('blog', 'AB12CD', '', true), 'no name yet');
	pg_assert_same('/blog?r=AB12CD', pg_import_redirect_record_url('blog', 'AB12CD', 'hello-world', false), 'not pretty');
}

function test_import_redirect_wired()
{
	$framework = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/importers.php');
	$writer = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/import/write_redirect.php');
	$screen = (string) file_get_contents(PG_FUNCTIONS_DIR . '/import.php');

	pg_assert_true(strpos($framework, 'pg_import_redirect_after(') !== false, 'the run adds a redirect after a made item');
	pg_assert_true(strpos($writer, 'pg_short_link_redirect_columns(') !== false, 'loop checks');
	pg_assert_true(strpos($writer, 'check_name_availability(') !== false, 'name taken');
	pg_assert_true(strpos($writer, 'function pg_import_unwrite_redirect(') !== false, 'undo');
	pg_assert_true(strpos($screen, 'pg_short_link_csv(') !== false, 'CSV download');
}
