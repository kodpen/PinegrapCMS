<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the redirects router.php and get_page.php answer themselves: a
 * short link that shows its address "as always" (answer 0) completes it as a
 * 301 link does, an old /pages/ address and a record's list address keep the
 * language directory the visitor came in with.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function router_redirects_test_source($relative)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $relative);
}

// A link with answer 0 and an address rebuilt the address from parse_url():
// the port and the user part of a full address were dropped
// (https://shop.example.com:8443/x went to https://shop.example.com/x) and
// other characters went into the Location header unencoded. It now asks
// pg_short_link_location() as the 301 link does, still without the visitor's
// query string.
function test_router_redirects_answer_zero_address_is_completed_like_301()
{
	$router = router_redirects_test_source('router.php');
	$case = (string) substr($router, (int) strpos($router, "case 'url':"));
	$case = (string) substr($case, 0, (int) strpos($case, 'exit();'));

	pg_assert_contains("pg_short_link_location(\$short_link, array(), ''", $case, 'the address comes from pg_short_link_location() without the visitor\'s query');
	pg_assert_true(strpos($case, 'parse_url($short_link') === false, 'the address is not rebuilt from parse_url()');

	$link = array('name' => 'magaza', 'destination_type' => 'url', 'url' => 'https://shop.example.com:8443/sepet?k=1#ozet');
	pg_assert_same('https://shop.example.com:8443/sepet?k=1#ozet', pg_short_link_location($link, array(), '', 'https://example.com', '/sub/', '/sub/'), 'the port is kept');

	$link['url'] = 'yeni sayfa/çay';
	pg_assert_same('https://example.com/sub/yeni%20sayfa/%C3%A7ay', pg_short_link_location($link, array(), '', 'https://example.com', '/sub/', '/sub/'), 'a path below the site, encoded');

	$link['url'] = '/kok';
	pg_assert_same('https://example.com/kok', pg_short_link_location($link, array(), '', 'https://example.com', '/sub/', '/sub/'), 'a path from the host root');
}

// /en/pages/about was sent to /about: the visitor left the language they
// came in with.
function test_router_redirects_old_pages_address_keeps_language()
{
	$router = router_redirects_test_source('router.php');
	$block = (string) substr($router, (int) strpos($router, "(\$first_6_characters == 'pages/')"));
	$block = (string) substr($block, 0, (int) strpos($block, 'exit();'));

	pg_assert_contains("(defined('LANGUAGE_PATH') ? LANGUAGE_PATH : PATH) . pg_uri_ascii(\$new_request_url_without_path)", $block, 'the language directory stays in the address');
}

// /en/blog/<name> on a list page answered 301 to /blog-post/<name>, the
// record page in the site's own language.
function test_router_redirects_record_list_address_keeps_language()
{
	$function = new ReflectionFunction('pg_sw_route_record_address');
	$lines = file($function->getFileName());
	$source = implode('', array_slice($lines, $function->getStartLine() - 1, $function->getEndLine() - $function->getStartLine() + 1));

	pg_assert_contains("\$record['address_name'], true, defined('LANGUAGE_PATH') ? LANGUAGE_PATH : null)", $source, 'the record address is built under the language directory');
	pg_assert_same('/en/blog-post/ilk-yazi', pg_sw_record_url('blog-post', 'ABC', 'ilk-yazi', true, '/en/'), 'record address under a language base');
}
