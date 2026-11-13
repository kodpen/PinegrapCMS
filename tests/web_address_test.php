<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the address rules of includes/fn/core.php that the address
 * fields a site page opens share: pg_web_address_problem() (the save rule
 * of a short link destination and an auto dialog URL) and
 * pg_address_opens_as_web() (the guard where a stored address is printed),
 * and the browser twin of the guard in frontend.src.js / frontend.min.js.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_web_address_problem_takes_web_addresses_and_site_paths()
{
	foreach (array('https://example.com/a?b=1#c', 'http://example.com', 'HTTPS://EXAMPLE.COM', '/newsletter', 'newsletter', '?popup=1', '#top', '  /trimmed  ', 'a/b:c') as $url) {
		pg_assert_same('', pg_web_address_problem($url), $url);
	}
}

function test_web_address_problem_refuses_other_schemes_hosts_backslashes_and_spaces()
{
	$scheme = lang('The address must be a web address or a path on this site.');
	$space = lang('Sorry, you may not enter a space in the URL.');

	foreach (array('javascript:alert(1)', 'JavaScript:void(0)', 'data:text/html,x', 'vbscript:x', 'mailto:a@example.com', 'blob:https://example.com/x', '//example.com/x', '/\\example.com', 'https:\\\\example.com') as $url) {
		pg_assert_same($scheme, pg_web_address_problem($url), $url);
	}

	foreach (array("java\tscript:alert(1)", "java\nscript:alert(1)", "\x01javascript:alert(1)", '/a b', "/a\x7f") as $url) {
		pg_assert_same($space, pg_web_address_problem($url), json_encode($url));
	}

	pg_assert_same('', pg_web_address_problem(array('javascript:x')), 'not a string: nothing to refuse, the caller asks for a value');
}

function test_web_address_short_link_rule_is_the_shared_one()
{
	foreach (array('https://example.com', '/a', 'javascript:alert(1)', '//example.com', '/a b', 'a\\b') as $url) {
		pg_assert_same(pg_web_address_problem($url), pg_short_link_url_problem($url), 'short link: ' . $url);
	}

	pg_assert_true(pg_short_link_url_problem('') !== '', 'a short link still needs a destination');
}

function test_address_opens_as_web_reads_the_scheme_as_a_browser_does()
{
	foreach (array('https://example.com', 'http://x', 'HTTP://x', '/path', 'path', '//example.com/embed', '/\\example.com', '?a=1', '#x', '', 'javascript&colon;alert(1)', 'a/b:c', "\x01 https://example.com") as $url) {
		pg_assert_true(pg_address_opens_as_web($url), json_encode($url) . ' keeps http or https');
	}

	foreach (array('javascript:alert(1)', 'JAVASCRIPT:alert(1)', ' javascript:alert(1)', "\x00\x1fjavascript:alert(1)", "java\tscript:alert(1)", "java\r\nscript:alert(1)", "javascript:alert(1)\x20", 'data:text/html,x', 'vbscript:x', 'mailto:a@example.com', 'about:blank') as $url) {
		pg_assert_false(pg_address_opens_as_web($url), json_encode($url) . ' would open with another scheme');
	}

	pg_assert_true(pg_address_opens_as_web(null), 'no address');
}

function test_address_opens_as_web_browser_twin_in_both_scripts()
{
	$src = (string) file_get_contents(PG_FUNCTIONS_DIR . '/frontend.src.js');
	$min = (string) file_get_contents(PG_FUNCTIONS_DIR . '/frontend.min.js');

	pg_assert_contains('is_web_address: function(url) {', $src, 'frontend.src.js has the guard');
	pg_assert_contains("if (!software.is_web_address(url)) {\n            return false;\n        }", $src, 'open_dialog asks it before the iframe');
	pg_assert_true(strpos($src, 'software.is_web_address(url)') < strpos($src, '<iframe id="software_dialog_'), 'the guard runs before the iframe is built');

	pg_assert_contains('is_web_address:function(e){', $min, 'frontend.min.js has the guard');
	pg_assert_contains('n=e.height;if(!software.is_web_address(i))return!1;', $min, 'the served open_dialog asks it first');
}
