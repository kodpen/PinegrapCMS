<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the redirect manager's rules (includes/fn/redirects.php): how an
 * address is written for comparison, how patterns match and fill their
 * targets, which rule answers an address, the address a visitor is sent to,
 * which rules are refused (self, loop, chain, form of the addresses), and the
 * CSV file read and written.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// A rule row as the table gives it.
function redirects_test_rule($id, $from, $to, $code = 301)
{
	return array(
		'id' => $id,
		'from_path' => $from,
		'to_url' => $to,
		'code' => $code,
		'is_pattern' => pg_redirect_is_pattern($from) ? 1 : 0,
	);
}

// The code of the problem with a rule, or null.
function redirects_test_problem($from, $to, $rules = array(), $code = 301, $id = 0)
{
	$problem = pg_redirect_rule_problem(
		array('id' => $id, 'from_path' => $from, 'to_url' => $to, 'code' => $code),
		$rules,
		'example.com',
		'/'
	);

	return ($problem === null) ? null : $problem['code'];
}

// One leading slash, single slashes, no trailing slash, decoded, no query.
function test_redirects_normalize_path()
{
	pg_assert_same('', pg_redirect_normalize_path(''));
	pg_assert_same('', pg_redirect_normalize_path(array('/a')));
	pg_assert_same('/', pg_redirect_normalize_path('/'));
	pg_assert_same('/old-page', pg_redirect_normalize_path('old-page'));
	pg_assert_same('/old-page', pg_redirect_normalize_path(' /old-page/ '));
	pg_assert_same('/a/b', pg_redirect_normalize_path('//a///b//'));
	pg_assert_same('/old', pg_redirect_normalize_path('/old?x=1#top'));
	pg_assert_same('/a/b', pg_redirect_normalize_path('https://old.example.com/a/b/?q=1'));
	pg_assert_same('/', pg_redirect_normalize_path('https://old.example.com'));
	pg_assert_same('/ürün', pg_redirect_normalize_path('/%C3%BCr%C3%BCn'));
	pg_assert_same('/%FF', pg_redirect_normalize_path('/%FF'));
	pg_assert_same('/blog/*', pg_redirect_normalize_path('/blog/**/'));
}

// The site's base path comes off the front, only as a whole segment.
function test_redirects_normalize_path_base()
{
	pg_assert_same('/old', pg_redirect_normalize_path('/sub/old', '/sub/'));
	pg_assert_same('/old', pg_redirect_normalize_path('https://example.com/sub/old', '/sub/'));
	pg_assert_same('/', pg_redirect_normalize_path('/sub/', '/sub/'));
	pg_assert_same('/subway', pg_redirect_normalize_path('/subway', '/sub/'));
}

// A request target becomes the path below the base, its query string apart.
function test_redirects_request_path_and_query()
{
	pg_assert_same('/old-page', pg_redirect_request_path('/old-page?utm_source=x', '/'));
	pg_assert_same('/en/old', pg_redirect_request_path('/sub/en/old/', '/sub/'));
	pg_assert_same('/ürün', pg_redirect_request_path('/%C3%BCr%C3%BCn', '/'));
	pg_assert_same('x=1&y=2', pg_redirect_request_query('/a?x=1&y=2'));
	pg_assert_same('x=1', pg_redirect_request_query('/a?x=1#f'));
	pg_assert_same('', pg_redirect_request_query('/a'));
}

// Case does not make two addresses.
function test_redirects_key()
{
	pg_assert_same(pg_redirect_key('/old'), pg_redirect_key('/OLD'));
	pg_assert_same(pg_redirect_key('/ürün'), pg_redirect_key('/ÜRÜN'));
	pg_assert_false(pg_redirect_key('/a') === pg_redirect_key('/b'));
	pg_assert_same(40, strlen(pg_redirect_key('/a')));
}

// Each * matches as a group; case is ignored and kept in what was matched.
function test_redirects_pattern_captures()
{
	pg_assert_same(array('2024/hello'), pg_redirect_pattern_captures('/blog/*', '/blog/2024/hello'));
	pg_assert_same(null, pg_redirect_pattern_captures('/blog/*', '/blog'));
	pg_assert_same(null, pg_redirect_pattern_captures('/blog/*', '/news/x'));
	pg_assert_same(array('x', 'y/z'), pg_redirect_pattern_captures('/a/*/b/*', '/a/x/b/y/z'));
	pg_assert_same(array('Hello'), pg_redirect_pattern_captures('/Blog/*', '/blog/Hello'));
	pg_assert_same(array('ürün'), pg_redirect_pattern_captures('/shop/*.html', '/shop/ürün.html'));
	pg_assert_same(array('a'), pg_redirect_pattern_captures('/x(1)/*', '/x(1)/a'));
}

// $n takes what its * matched, one encoded segment at a time.
function test_redirects_fill_target()
{
	pg_assert_same('/news/a%20b/c%3Fd', pg_redirect_fill_target('/news/$1', array('a b/c?d')));
	pg_assert_same('/news/a b/c?d', pg_redirect_fill_target('/news/$1', array('a b/c?d'), false));
	pg_assert_same('/y-x', pg_redirect_fill_target('/$2-$1', array('x', 'y')));
	pg_assert_same('/news/', pg_redirect_fill_target('/news/$3', array('x')));
	pg_assert_same(2, pg_redirect_target_references('/a/$2/$1'));
	pg_assert_same(0, pg_redirect_target_references('/a/b'));
}

// Exact rules first, then the most specific pattern, then the oldest.
function test_redirects_resolve_order()
{
	$rules = array(
		redirects_test_rule(1, '/blog/*', '/news/$1'),
		redirects_test_rule(2, '/blog/2019/*', '/archive/$1'),
		redirects_test_rule(3, '/blog/special', '/special'),
		redirects_test_rule(4, '/shop/*', '/store/$1'),
		redirects_test_rule(5, '/shop/*', '/second/$1'),
	);

	$match = pg_redirect_resolve('/blog/special', $rules);
	pg_assert_same(3, $match['rule']['id']);
	pg_assert_same('/special', $match['target']);

	$match = pg_redirect_resolve('/blog/2019/old-post', $rules);
	pg_assert_same(2, $match['rule']['id']);
	pg_assert_same('/archive/old-post', $match['target']);

	$match = pg_redirect_resolve('/Blog/2024/Post', $rules);
	pg_assert_same(1, $match['rule']['id']);
	pg_assert_same('/news/2024/Post', $match['target']);

	$match = pg_redirect_resolve('/shop/x', $rules);
	pg_assert_same(4, $match['rule']['id']);

	pg_assert_same(null, pg_redirect_resolve('/other', $rules));
}

// Where the visitor goes: the site's address and base, their query string on.
function test_redirects_location()
{
	pg_assert_same('https://example.com/new?utm=1', pg_redirect_location('/new', 'utm=1', 'https://example.com', '/'));
	pg_assert_same('https://example.com/sub/new', pg_redirect_location('/new', '', 'https://example.com', '/sub/'));
	pg_assert_same('https://example.com/new?a=1&b=2#top', pg_redirect_location('/new?a=1#top', 'b=2', 'https://example.com', '/'));
	pg_assert_same('https://other.example/x?b=2', pg_redirect_location('https://other.example/x', 'b=2', 'https://example.com', '/'));
	pg_assert_same('https://example.com/%C3%BCr%C3%BCn%20yeni', pg_redirect_location('/ürün yeni', '', 'https://example.com', '/'));
	pg_assert_same('https://example.com/a%20b', pg_redirect_location('/a%20b', '', 'https://example.com', '/'));
}

// A target of this site, by path or by its own full address.
function test_redirects_internal_path()
{
	pg_assert_same('/x', pg_redirect_internal_path('/x?y=1', 'example.com', '/'));
	pg_assert_same('/x', pg_redirect_internal_path('https://www.example.com/sub/x', 'example.com', '/sub/'));
	pg_assert_same('/x', pg_redirect_internal_path('http://EXAMPLE.com/x', 'example.com:443', '/'));
	pg_assert_same(null, pg_redirect_internal_path('https://other.example/x', 'example.com', '/'));
	pg_assert_same(null, pg_redirect_internal_path('https://example.com/x', '', '/'));
}

// The form of the two addresses and the code.
function test_redirects_problem_form()
{
	pg_assert_same(null, redirects_test_problem('/old', '/new'));
	pg_assert_same(null, redirects_test_problem('https://old.example/old', 'https://new.example/x', array(), 302));
	pg_assert_same('from_required', redirects_test_problem(' ', '/new'));
	pg_assert_same('from_home', redirects_test_problem('/', '/new'));
	pg_assert_same('from_home', redirects_test_problem('https://old.example/', '/new'));
	pg_assert_same('from_query', redirects_test_problem('/page.php?id=3', '/new'));
	pg_assert_same('from_query', redirects_test_problem('https://old.example/page?id=3', '/new'));
	pg_assert_same('from_invalid', redirects_test_problem("/a\nb", '/new'));
	pg_assert_same('from_long', redirects_test_problem('/' . str_repeat('a', PG_REDIRECT_FROM_MAX), '/new'));
	pg_assert_same('from_wildcards', redirects_test_problem('/' . str_repeat('*/', 10) . 'x', '/new'));
	pg_assert_same('to_required', redirects_test_problem('/old', ''));
	pg_assert_same('to_form', redirects_test_problem('/old', 'new-page'));
	pg_assert_same('to_form', redirects_test_problem('/old', 'javascript:alert(1)'));
	pg_assert_same('to_form', redirects_test_problem('/old', '//evil.example/x'));
	pg_assert_same('to_form', redirects_test_problem('/old', '/\\evil.example/x'));
	pg_assert_same('to_form', redirects_test_problem('/old', 'ftp://example.com/x'));
	pg_assert_same('to_invalid', redirects_test_problem('/old', "/new\r\nSet-Cookie: x=1"));
	pg_assert_same('to_long', redirects_test_problem('/old', '/' . str_repeat('a', PG_REDIRECT_TO_MAX)));
	pg_assert_same('to_references', redirects_test_problem('/old', '/new/$1'));
	pg_assert_same('to_references', redirects_test_problem('/old/*', '/new/$2'));
	pg_assert_same('code_invalid', redirects_test_problem('/old', '/new', array(), 307));
	pg_assert_same('code_invalid', redirects_test_problem('/old', '/new', array(), 0));
}

// A rule cannot send an address to itself, nor a pattern into itself.
function test_redirects_problem_self()
{
	pg_assert_same('to_self', redirects_test_problem('/a', '/A/'));
	pg_assert_same('to_self', redirects_test_problem('/a', 'https://example.com/a?x=1'));
	pg_assert_same('to_self', redirects_test_problem('/a/*', '/a/b/$1'));
	pg_assert_same(null, redirects_test_problem('/blog/*', '/news/$1'));
	pg_assert_same(null, redirects_test_problem('/a', 'https://other.example/a'));
	// The home page always answers, so a catch-all to it ends there.
	pg_assert_same(null, redirects_test_problem('/*', '/'));
}

// Loops through other rules, long chains, and a second rule for an address.
function test_redirects_problem_rules()
{
	$rules = array(redirects_test_rule(1, '/b', '/a'));
	pg_assert_same('to_loop', redirects_test_problem('/a', '/b', $rules));

	$rules = array(redirects_test_rule(1, '/news/*', '/blog/$1'));
	pg_assert_same('to_loop', redirects_test_problem('/blog/*', '/news/$1', $rules));

	$rules = array(redirects_test_rule(1, '/b', '/c'), redirects_test_rule(2, '/c', 'https://other.example/'));
	pg_assert_same(null, redirects_test_problem('/a', '/b', $rules));

	$rules = array();
	for ($i = 1; $i <= 11; $i++) {
		$rules[] = redirects_test_rule($i, '/c' . $i, '/c' . ($i + 1));
	}
	pg_assert_same('to_chain', redirects_test_problem('/c0', '/c1', $rules));
	pg_assert_same(null, redirects_test_problem('/c0', '/c3', array_slice($rules, 0, 5)));

	// A loop elsewhere that the new rule leads into is a chain without end.
	$rules = array(redirects_test_rule(1, '/x', '/y'), redirects_test_rule(2, '/y', '/x'));
	pg_assert_same('to_chain', redirects_test_problem('/a', '/x', $rules));

	$rules = array(redirects_test_rule(1, '/old', '/new'));
	pg_assert_same('from_taken', redirects_test_problem('/OLD/', '/other', $rules));
	pg_assert_same(null, redirects_test_problem('/OLD/', '/other', $rules, 302, 1));

	// Changing a rule's own target is checked against the others only.
	$rules = array(redirects_test_rule(1, '/a', '/b'), redirects_test_rule(2, '/b', '/c'));
	pg_assert_same('to_loop', redirects_test_problem('/b', '/a', $rules, 301, 2));
	pg_assert_same(null, redirects_test_problem('/b', '/d', $rules, 301, 2));
}

// The CSV reader: separators, a heading, a byte order mark, Excel's Turkish
// code page, quoted cells, empty lines and an optional code.
function test_redirects_csv_rows()
{
	$rows = pg_redirect_csv_rows("\xEF\xBB\xBFfrom,to,code\n/a,/b,302\n\n\"/c,d\",https://example.com/e\n");
	pg_assert_same(2, count($rows));
	pg_assert_same(array('line' => 2, 'from_path' => '/a', 'to_url' => '/b', 'code' => '302'), $rows[0]);
	pg_assert_same(array('line' => 4, 'from_path' => '/c,d', 'to_url' => 'https://example.com/e', 'code' => ''), $rows[1]);

	$rows = pg_redirect_csv_rows("Eski adres;Yeni adres\r\n/x;/y\r\n");
	pg_assert_same(1, count($rows));
	pg_assert_same('/x', $rows[0]['from_path']);
	pg_assert_same('/y', $rows[0]['to_url']);

	$rows = pg_redirect_csv_rows("/x\t/y\t301\n");
	pg_assert_same('/y', $rows[0]['to_url']);
	pg_assert_same('301', $rows[0]['code']);

	$rows = pg_redirect_csv_rows("/\xFCr\xFCn,/yeni\n");
	pg_assert_same('/ürün', $rows[0]['from_path']);

	$rows = pg_redirect_csv_rows("\n\nOld URL,New URL\n/p,/q\n");
	pg_assert_same(1, count($rows));
	pg_assert_same(4, $rows[0]['line']);

	pg_assert_same(array(), pg_redirect_csv_rows(''));
}

// The CSV writer quotes what needs it and reads back the same.
function test_redirects_csv_export()
{
	$rules = array(
		redirects_test_rule(1, '/a', '/b'),
		redirects_test_rule(2, '/c,d', 'https://example.com/x?y="1"', 302),
	);

	$csv = pg_redirect_csv($rules);

	pg_assert_same("\xEF\xBB\xBF", substr($csv, 0, 3));
	pg_assert_contains("from,to,code\r\n/a,/b,301\r\n", $csv);
	pg_assert_contains('"/c,d","https://example.com/x?y=""1""",302', $csv);

	$rows = pg_redirect_csv_rows($csv);
	pg_assert_same(2, count($rows));
	pg_assert_same('/c,d', $rows[1]['from_path']);
	pg_assert_same('https://example.com/x?y="1"', $rows[1]['to_url']);
	pg_assert_same('302', $rows[1]['code']);
}

// A prepared set answers like the list it was made from, and stays current
// as rules are added or moved (the importer's path).
function test_redirects_prepared_set()
{
	$rules = array(
		redirects_test_rule(1, '/a', '/b'),
		redirects_test_rule(2, '/news/*', '/blog/$1'),
	);

	$prepared = pg_redirect_prepare($rules);

	pg_assert_same('from_taken', pg_redirect_rule_problem(array('from_path' => '/A', 'to_url' => '/x', 'code' => 301), $prepared, 'example.com', '/')['code']);
	pg_assert_same('to_loop', pg_redirect_rule_problem(array('from_path' => '/b', 'to_url' => '/a', 'code' => 301), $prepared, 'example.com', '/')['code']);
	pg_assert_same('to_loop', pg_redirect_rule_problem(array('from_path' => '/blog/*', 'to_url' => '/news/$1', 'code' => 301), $prepared, 'example.com', '/')['code']);

	// Rule 1 moves from /a to /c: /a is free, /c is taken, and a new /b -> /a
	// no longer loops.
	pg_redirect_prepared_put($prepared, redirects_test_rule(1, '/c', '/b'));
	pg_assert_same(null, pg_redirect_rule_problem(array('from_path' => '/a', 'to_url' => '/x', 'code' => 301), $prepared, 'example.com', '/'));
	pg_assert_same('from_taken', pg_redirect_rule_problem(array('from_path' => '/c', 'to_url' => '/x', 'code' => 301), $prepared, 'example.com', '/')['code']);
	pg_assert_same(null, pg_redirect_rule_problem(array('from_path' => '/b', 'to_url' => '/a', 'code' => 301), $prepared, 'example.com', '/'));
	pg_assert_same('to_loop', pg_redirect_rule_problem(array('from_path' => '/b', 'to_url' => '/c', 'code' => 301), $prepared, 'example.com', '/')['code']);

	// A new pattern joins the patterns in their order; replacing it keeps one.
	pg_redirect_prepared_put($prepared, redirects_test_rule(3, '/news/2019/*', '/archive/$1'));
	pg_redirect_prepared_put($prepared, redirects_test_rule(3, '/news/2019/*', '/old/$1'));
	pg_assert_same(2, count($prepared['index']['patterns']));
	pg_assert_same('/old/x', pg_redirect_resolve_in('/news/2019/x', $prepared['index'])['target']);
	pg_assert_same('/blog/2020/x', pg_redirect_resolve_in('/news/2020/x', $prepared['index'])['target']);

	// Checking a rule never changes the set it was checked against.
	pg_redirect_rule_problem(array('id' => 2, 'from_path' => '/other/*', 'to_url' => '/z/$1', 'code' => 301), $prepared, 'example.com', '/');
	pg_assert_same('/blog/2020/x', pg_redirect_resolve_in('/news/2020/x', $prepared['index'])['target']);
}
