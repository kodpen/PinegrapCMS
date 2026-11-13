<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for pg_safe_redirect_path() (includes/fn/core.php), the check every
 * send_to redirect and go() pass through: a target a browser would follow off
 * the site gives the fallback; a path on this site comes back as a Location
 * header may carry it, UTF-8 and spaces percent-encoded (pg_uri_ascii()).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_safe_redirect_path_refuses_targets_off_the_site()
{
	$cases = array(
		'//evil.example/x' => 'protocol-relative',
		'/\\evil.example/x' => 'a backslash after the slash',
		'@evil.example/x' => 'userinfo',
		'.evil.example/x' => 'another host name',
		'https://evil.example/' => 'an absolute URL',
		"/a\r\nSet-Cookie: x=1" => 'a header split',
		"/\t/evil.example" => 'a tab inside the path',
		'' => 'empty',
	);

	foreach ($cases as $target => $label) {
		pg_assert_same('/__none__', pg_safe_redirect_path($target, '/__none__'), $label);
	}

	pg_assert_same('/__none__', pg_safe_redirect_path(array('/x'), '/__none__'), 'a list is no target');
}

// A Location value is ASCII only: the browser read the raw UTF-8 of a Turkish
// page name as Latin-1 and IIS answered the address it made with 400 (#8983).
function test_safe_redirect_path_percent_encodes_what_a_header_cannot_carry()
{
	pg_assert_same('/pg-test-dgr-%C3%87%C4%9E%C4%B0%C3%96%C5%9E%C3%9C', pg_safe_redirect_path('/pg-test-dgr-ÇĞİÖŞÜ', '/__none__'));
	pg_assert_same('/a%20b?x=%C4%9F#c', pg_safe_redirect_path('/a b?x=ğ#c', '/__none__'), 'the query string and the fragment too');
	pg_assert_same('/pg-%C4%9F?send_to=%2Fa', pg_safe_redirect_path('/pg-%C4%9F?send_to=%2Fa', '/__none__'), 'encoded once, kept');
	pg_assert_same('/product/x?previous_url_id=2&a[]=1#software_add_comment', pg_safe_redirect_path('/product/x?previous_url_id=2&a[]=1#software_add_comment', '/__none__'), 'ASCII as it came');
	pg_assert_same('/%C3%A7ay', pg_safe_redirect_path('//evil.example', '/çay'), 'the fallback too');
	pg_assert_same(pg_safe_redirect_path('/pg-ğ', '/__none__'), pg_safe_redirect_path(pg_safe_redirect_path('/pg-ğ', '/__none__'), '/__none__'), 'twice is once');
}
