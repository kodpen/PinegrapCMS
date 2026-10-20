<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for short links that redirect (includes/fn/short_link_redirects.php):
 * a name taken from a pasted address and the name rule, names with a * and
 * what each * matched, where an address on this site leads, the checks that
 * keep a link from leading to itself or round a loop, the address a visitor
 * is sent to and the status it is sent with, and the CSV file of links that
 * the list exports and the import reads. The runner has no connection,
 * so the columns of 2026.11.0 (11.0.12) read as missing: names stop at 100
 * characters and pg_short_link_redirect_columns() writes nothing, as on a
 * site that has not run the upgrade.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// A url-type link as the checks read it.
function short_link_redirects_test_link($id, $name, $url)
{
	return array('id' => $id, 'name' => $name, 'destination_type' => 'url', 'url' => $url);
}

// The code of the problem a link has, or '' when it can be saved.
function short_link_redirects_test_problem($link, $links = array())
{
	$problem = pg_short_link_redirect_problem($link, $links, 'example.com', '/');

	return ($problem === null) ? '' : $problem['code'];
}

function test_short_link_redirects_name_from_a_pasted_address_keeps_its_path()
{
	pg_assert_same('blog/Ürün_Sayfası', pg_short_link_name_from_address('https://old.example.com/blog/Ürün Sayfası?x=1#top', '/'), 'full address');
	pg_assert_same('urunler/çanta', pg_short_link_name_from_address('/urunler/%C3%A7anta', '/'), 'percent-encoded path is decoded');
	pg_assert_same('a/b/c', pg_short_link_name_from_address('/a//b///c/', '/'), 'runs of slashes');
	pg_assert_same('eski/*', pg_short_link_name_from_address('eski/***', '/'), 'runs of stars');
	pg_assert_same('%FF', pg_short_link_name_from_address('%FF', '/'), 'not UTF-8 once decoded: left as it was');
	pg_assert_same('sayfa', pg_short_link_name_from_address('  sayfa?utm_source=x  ', '/'), 'query string');
	pg_assert_same('', pg_short_link_name_from_address(array('x'), '/'), 'not a string');
	pg_assert_same('', pg_short_link_name_from_address('https://old.example.com/', '/'), 'the home page has no name');
}

function test_short_link_redirects_name_from_address_strips_the_base_path_of_full_paths_only()
{
	pg_assert_same('blog/x', pg_short_link_name_from_address('/site/blog/x', '/site/'), 'path from the root');
	pg_assert_same('blog/x', pg_short_link_name_from_address('https://example.com/site/blog/x', '/site/'), 'full address');
	pg_assert_same('site/blog/x', pg_short_link_name_from_address('site/blog/x', '/site/'), 'a name typed below the site');
	pg_assert_same('siteler/x', pg_short_link_name_from_address('/siteler/x', '/site/'), 'a longer first segment');
}

function test_short_link_redirects_name_rule()
{
	pg_assert_same('', pg_short_link_name_problem('ürün/eski-sayfa.html'), 'Turkish letters and a file name');
	pg_assert_same('', pg_short_link_name_problem('kampanya_(2026)~a+b,c!d=e@f'), 'the punctuation the rule allows');
	pg_assert_same('', pg_short_link_name_problem('blog/*'), 'a pattern');
	pg_assert_same('', pg_short_link_name_problem(str_repeat('a', 100)), '100 characters before the upgrade');

	pg_assert_true(pg_short_link_name_problem('') !== '', 'empty');
	pg_assert_true(pg_short_link_name_problem(str_repeat('a', 101)) !== '', 'too long before the upgrade');
	pg_assert_true(pg_short_link_name_problem('a b') !== '', 'space');
	pg_assert_true(pg_short_link_name_problem('a<b') !== '', 'angle bracket');
	pg_assert_true(pg_short_link_name_problem('a"b') !== '', 'quote');
	pg_assert_true(pg_short_link_name_problem('ftp:/x') !== '', 'colon');
	pg_assert_true(pg_short_link_name_problem('a?b') !== '', 'question mark');
	pg_assert_true(pg_short_link_name_problem('*') !== '', 'only a star');
	pg_assert_true(pg_short_link_name_problem('*/*') !== '', 'only stars and slashes');
	pg_assert_true(pg_short_link_name_problem('a*b*c*d*e*f*g*h*i*j*k') !== '', 'ten stars');
	pg_assert_same('', pg_short_link_name_problem('a*b*c*d*e*f*g*h*i*j'), 'nine stars');
}

function test_short_link_redirects_key_ignores_case_and_turkish_marks()
{
	pg_assert_same(pg_short_link_key('urun'), pg_short_link_key('ÜRÜN'), 'Ü');
	pg_assert_same(pg_short_link_key('istanbul'), pg_short_link_key('İstanbul'), 'dotted capital I');
	pg_assert_true(pg_short_link_key('a') !== pg_short_link_key('b'), 'different names');
}

function test_short_link_redirects_pattern_captures()
{
	pg_assert_same(array('2019/05/yazi'), pg_short_link_pattern_captures('blog/*', 'blog/2019/05/yazi'), 'one star, many segments');
	pg_assert_same(array('a'), pg_short_link_pattern_captures('blog/*', 'BLOG/a'), 'without case');
	pg_assert_same(array('x'), pg_short_link_pattern_captures('ürün/*', 'ÜRÜN/x'), 'without case, Turkish letters');
	pg_assert_same(array('tr', 'canta'), pg_short_link_pattern_captures('*/eski-*.html', 'tr/eski-canta.html'), 'two stars');
	pg_assert_same(null, pg_short_link_pattern_captures('blog/*', 'blogx/a'), 'another first segment');
	pg_assert_same(null, pg_short_link_pattern_captures('a.b*', 'aXb1'), 'a dot is a dot');
	pg_assert_same(array(''), pg_short_link_pattern_captures('blog/*', 'blog/'), 'a star may match nothing');
}

function test_short_link_redirects_patterns_are_tried_most_specific_first()
{
	$sorted = pg_short_link_sort_patterns(array(
		array('id' => 1, 'name' => '*'),
		array('id' => 2, 'name' => 'blog/*'),
		array('id' => 3, 'name' => 'blog/2019/*'),
		array('id' => 4, 'name' => 'blog/*'),
	));

	pg_assert_same(array(3, 2, 4, 1), array_map(function ($link) {
		return (int) $link['id'];
	}, $sorted));
}

function test_short_link_redirects_exact_names_win_over_patterns()
{
	$index = pg_short_link_index(array(
		short_link_redirects_test_link(1, 'blog/*', '/yazilar/$1'),
		short_link_redirects_test_link(2, 'blog/ozel', '/ozel'),
		short_link_redirects_test_link(3, '', '/token-link'),
	));

	$exact = pg_short_link_match_in('BLOG/OZEL', $index);
	$pattern = pg_short_link_match_in('blog/diger', $index);
	$at_404 = pg_short_link_match_in('blog/ozel', $index, false);

	pg_assert_same(2, (int) $exact['link']['id'], 'exact name, without case');
	pg_assert_same(1, (int) $pattern['link']['id'], 'pattern');
	pg_assert_same(array('diger'), $pattern['captures'], 'captures');
	pg_assert_same(1, (int) $at_404['link']['id'], 'patterns only, as at a 404');
	pg_assert_same(null, pg_short_link_match_in('baska', $index), 'nothing');
}

function test_short_link_redirects_fill_target()
{
	pg_assert_same('/yeni/a%20b/c%3Fd', pg_short_link_fill_target('/yeni/$1', array('a b/c?d')), 'encoded segment by segment');
	pg_assert_same('/yeni/a b/c?d', pg_short_link_fill_target('/yeni/$1', array('a b/c?d'), false), 'unencoded, for comparing');
	pg_assert_same('/x//y', pg_short_link_fill_target('/x/$2/y', array('a')), 'a star the name does not have');
	pg_assert_same(3, pg_short_link_target_references('/a/$1/$3'), 'highest reference');
	pg_assert_same(0, pg_short_link_target_references('/a/b'), 'none');
}

function test_short_link_redirects_url_name_reads_addresses_on_this_site()
{
	pg_assert_same('a/b', pg_short_link_url_name('https://www.example.com/a/b', 'example.com', '/'), 'full address, www');
	pg_assert_same('a', pg_short_link_url_name('https://example.com:8080/a', 'example.com:8080', '/'), 'port');
	pg_assert_same(null, pg_short_link_url_name('https://other.com/a', 'example.com', '/'), 'another site');
	pg_assert_same('a/b', pg_short_link_url_name('/a/b', 'example.com', '/'), 'path from the root');
	pg_assert_same('a/b', pg_short_link_url_name('a/b', 'example.com', '/'), 'path below the site');
	pg_assert_same('', pg_short_link_url_name('/', 'example.com', '/'), 'home page');
	pg_assert_same('ürün', pg_short_link_url_name('/%C3%BCr%C3%BCn', 'example.com', '/'), 'encoded path');
	pg_assert_same('a', pg_short_link_url_name('/sub/a', 'example.com', '/sub/'), 'under the base path');
	pg_assert_same(null, pg_short_link_url_name('/other/a', 'example.com', '/sub/'), 'outside the base path');
	pg_assert_same(null, pg_short_link_url_name('mailto:x@example.com', 'example.com', '/'), 'not a web address');
}

function test_short_link_redirects_refuses_a_link_to_itself()
{
	pg_assert_same('to_self', short_link_redirects_test_problem(short_link_redirects_test_link(0, 'eski', '/eski')), 'same path');
	pg_assert_same('to_self', short_link_redirects_test_problem(short_link_redirects_test_link(0, 'Ürün', 'https://www.example.com/urun')), 'same name to the database');
	pg_assert_same('to_self', short_link_redirects_test_problem(short_link_redirects_test_link(0, 'blog/*', '/blog/$1')), 'a pattern into itself');
	pg_assert_same('', short_link_redirects_test_problem(short_link_redirects_test_link(0, 'blog/*', '/yazilar/$1')), 'a pattern elsewhere');
	pg_assert_same('', short_link_redirects_test_problem(short_link_redirects_test_link(0, 'eski', '/')), 'the home page always answers');
	pg_assert_same('', short_link_redirects_test_problem(short_link_redirects_test_link(0, 'eski', 'https://other.com/eski')), 'another site');
	pg_assert_same('', short_link_redirects_test_problem(array('id' => 0, 'name' => 'eski', 'destination_type' => 'page', 'url' => '')), 'a page answers at its own address');
}

function test_short_link_redirects_refuses_loops_and_long_chains()
{
	$links = array(short_link_redirects_test_link(1, 'b', '/a'));

	pg_assert_same('to_loop', short_link_redirects_test_problem(short_link_redirects_test_link(0, 'a', '/b'), $links), 'a -> b -> a');
	pg_assert_same('', short_link_redirects_test_problem(short_link_redirects_test_link(0, 'c', '/b'), $links), 'c -> b -> a ends at a page');

	// The link being changed is checked as it will stand, not as it stood.
	pg_assert_same('', short_link_redirects_test_problem(short_link_redirects_test_link(1, 'b', '/c'), $links), 'changed link');

	// Through a pattern: eski/x -> blog/x -> eski/x.
	$through_pattern = array(short_link_redirects_test_link(2, 'blog/*', '/eski/$1'));

	pg_assert_same('to_loop', short_link_redirects_test_problem(short_link_redirects_test_link(0, 'eski/*', '/blog/$1'), $through_pattern), 'two patterns');

	$chain = array();

	for ($i = 1; $i <= 11; $i++) {
		$chain[] = short_link_redirects_test_link($i, 'n' . $i, '/n' . ($i + 1));
	}

	pg_assert_same('to_chain', short_link_redirects_test_problem(short_link_redirects_test_link(0, 'start', '/n1'), $chain), 'more than ten hops');
	pg_assert_same('', short_link_redirects_test_problem(short_link_redirects_test_link(0, 'start', '/n3'), array_slice($chain, 0, 10)), 'eight hops');
}

function test_short_link_redirects_wildcard_references()
{
	pg_assert_same('to_references', short_link_redirects_test_problem(short_link_redirects_test_link(0, 'a/*', '/x/$2')), 'more references than stars');
	pg_assert_same('to_host', short_link_redirects_test_problem(short_link_redirects_test_link(0, 'git/*', 'https://$1/')), 'a star choosing the host');
	pg_assert_same('to_host', short_link_redirects_test_problem(short_link_redirects_test_link(0, 'git/*', 'https://example.com$1')), 'a star after the host, without a slash');
	pg_assert_same('', short_link_redirects_test_problem(short_link_redirects_test_link(0, 'git/*', 'https://other.com/$1')), 'a star in the path');
	pg_assert_same('', short_link_redirects_test_problem(short_link_redirects_test_link(0, 'kampanya', 'https://other.com/?kod=$1')), 'plain text in the address of an exact name');
}

function test_short_link_redirects_prepared_set_follows_changes()
{
	$prepared = pg_short_link_prepare(array(short_link_redirects_test_link(1, 'b', '/c')));

	pg_assert_same('', short_link_redirects_test_problem(short_link_redirects_test_link(0, 'a', '/b'), $prepared), 'b leads on to c');

	pg_short_link_prepared_put($prepared, short_link_redirects_test_link(1, 'b', '/a'));

	pg_assert_same('to_loop', short_link_redirects_test_problem(short_link_redirects_test_link(0, 'a', '/b'), $prepared), 'b now leads back to a');

	pg_short_link_prepared_put($prepared, short_link_redirects_test_link(1, 'd', '/a'));

	pg_assert_same('', short_link_redirects_test_problem(short_link_redirects_test_link(0, 'a', '/b'), $prepared), 'b renamed to d');
}

function test_short_link_redirects_location_of_each_destination()
{
	$page = array('destination_type' => 'page', 'page_name' => 'hakkımızda', 'tracking_code' => 'kampanya');

	pg_assert_same('https://example.com/en/hakk%C4%B1m%C4%B1zda?t=kampanya&utm_source=x', pg_short_link_location($page, array(), 'utm_source=x', 'https://example.com', '/', '/en/'), 'page, tracking code first, language prefix');
	pg_assert_same('https://example.com/hakk%C4%B1m%C4%B1zda?t=ziyaretci', pg_short_link_location($page, array(), 't=ziyaretci', 'https://example.com/', '/'), 'the visitor\'s own tracking code');

	$group = array('destination_type' => 'product_group', 'page_name' => 'magaza', 'product_group_address_name' => 'ayakkabi', 'tracking_code' => '');

	pg_assert_same('https://example.com/magaza/ayakkabi', pg_short_link_location($group, array(), '', 'https://example.com', '/'), 'product group');

	$product = array('destination_type' => 'product', 'page_name' => 'urun', 'product_address_name' => 'kirmizi canta', 'tracking_code' => '');

	pg_assert_same('https://example.com/urun/kirmizi%20canta', pg_short_link_location($product, array(), '', 'https://example.com', '/'), 'product');
	pg_assert_same('https://example.com/sub/belge.pdf', pg_short_link_location(array('destination_type' => 'file', 'file_name' => 'belge.pdf'), array(), '', 'https://example.com', '/sub/'), 'file');
	pg_assert_same('', pg_short_link_location(array('destination_type' => 'file', 'file_name' => ''), array(), '', 'https://example.com', '/'), 'a file that is gone');
	pg_assert_same('', pg_short_link_location(array('destination_type' => 'page', 'page_name' => null), array(), '', 'https://example.com', '/'), 'a page that is gone');
}

function test_short_link_redirects_location_of_addresses()
{
	$url = function ($name, $target) {
		return array('destination_type' => 'url', 'name' => $name, 'url' => $target);
	};

	pg_assert_same('https://example.com/sub/yeni/sayfa', pg_short_link_location($url('eski', 'yeni/sayfa'), array(), '', 'https://example.com', '/sub/'), 'below the site');
	pg_assert_same('https://example.com/yeni', pg_short_link_location($url('eski', '/yeni'), array(), '', 'https://example.com', '/sub/'), 'from the root');
	pg_assert_same('https://other.com/a?b=1&c=2#bolum', pg_short_link_location($url('eski', 'https://other.com/a?b=1#bolum'), array(), 'c=2', 'https://example.com', '/'), 'query before the fragment');
	pg_assert_same('https://example.com/yazilar/2019/%C3%BCr%C3%BCn', pg_short_link_location($url('blog/*', '/yazilar/$1'), array('2019/ürün'), '', 'https://example.com', '/'), 'what the star matched');
	pg_assert_same('https://other.com/?kod=$1', pg_short_link_location($url('kampanya', 'https://other.com/?kod=$1'), array(), '', 'https://example.com', '/'), 'an exact name keeps $1 as written');
	pg_assert_same('https://example.com//other.com/x', pg_short_link_location($url('git/*', '/$1'), array('/other.com/x'), '', 'https://example.com', '/'), 'a match cannot leave the site');
	pg_assert_same('https://example.com/a%0D%0AX:%20y', pg_short_link_location($url('eski', '/a' . "\r\n" . 'X: y'), array(), '', 'https://example.com', '/'), 'no header can be split');
	pg_assert_same('b=1', pg_short_link_request_query('/eski?b=1#x'), 'query of the request');
	pg_assert_same('', pg_short_link_request_query('/eski'), 'no query');
}

function test_short_link_redirects_status_and_saved_answer()
{
	pg_assert_same(301, pg_short_link_status(array('redirect_code' => '301', 'link_mode' => 'permanent', 'expires_at' => '0')), 'permanent 301');
	pg_assert_same(302, pg_short_link_status(array('redirect_code' => '301', 'link_mode' => 'timed', 'expires_at' => '1900000000')), 'a timed link is never remembered for good');
	pg_assert_same(302, pg_short_link_status(array('redirect_code' => '301', 'link_mode' => 'once')), 'nor is a one-time link');
	pg_assert_same(302, pg_short_link_status(array('redirect_code' => '302')), '302');
	pg_assert_same(0, pg_short_link_status(array('redirect_code' => '0')), 'shown at its own address');
	pg_assert_same(0, pg_short_link_status(array('redirect_code' => '307')), 'an answer it does not give');
	pg_assert_same(0, pg_short_link_status(array()), 'before the upgrade');

	pg_assert_same(301, pg_short_link_answer_for(0, 'blog/*'), 'a pattern always redirects');
	pg_assert_same(302, pg_short_link_answer_for('302', 'blog/*'), 'a pattern for now');
	pg_assert_same(301, pg_short_link_answer_for('301', 'eski'), '301');
	pg_assert_same(0, pg_short_link_answer_for('abc', 'eski'), 'not a number');
	pg_assert_same(0, pg_short_link_answer_for(500, 'eski'), 'not an answer');
	pg_assert_same(0, pg_short_link_answer_for(array(301), 'eski'), 'a list');
}

function test_short_link_redirects_columns_wait_for_the_upgrade()
{
	$answer = pg_short_link_redirect_columns(short_link_redirects_test_link(0, 'eski', '/eski'), 301, 0, array());

	pg_assert_same(array(), $answer['columns'], 'nothing is written before the upgrade');
	pg_assert_same(null, $answer['problem'], 'nor checked');
	pg_assert_same(100, pg_short_link_name_max(), 'the name column before the upgrade');
	pg_assert_same(255, pg_short_link_url_max(), 'the address column before the upgrade');
}

function test_short_link_redirects_name_parts_that_cannot_be_opened()
{
	pg_assert_true(pg_short_link_name_problem('../gizli') !== '', 'two dots at the start');
	pg_assert_true(pg_short_link_name_problem('a/./b') !== '', 'one dot in the middle');
	pg_assert_true(pg_short_link_name_problem('a/..') !== '', 'two dots at the end');
	pg_assert_same('', pg_short_link_name_problem('a/..b/c.'), 'dots inside a part');
	pg_assert_same('', pg_short_link_name_problem('.well'), 'a part that starts with a dot');
}

function test_short_link_redirects_url_problem()
{
	pg_assert_same('', pg_short_link_url_problem('https://example.com/a?b=1#c'), 'web address');
	pg_assert_same('', pg_short_link_url_problem('/yeni/$1'), 'path from the root');
	pg_assert_same('', pg_short_link_url_problem('yeni'), 'path below the site');
	pg_assert_true(pg_short_link_url_problem('') !== '', 'empty');
	pg_assert_true(pg_short_link_url_problem('/a b') !== '', 'space');
	pg_assert_true(pg_short_link_url_problem('javascript:alert(1)') !== '', 'another scheme');
	pg_assert_true(pg_short_link_url_problem('//other.com/x') !== '', 'protocol-relative');
	pg_assert_true(pg_short_link_url_problem('/\\other.com') !== '', 'backslash');
	pg_assert_true(pg_short_link_url_problem('/' . str_repeat('a', 300)) !== '', 'longer than the column before the upgrade');
	pg_assert_true(pg_short_link_url_problem(array('/a')) !== '', 'a list');
}

function test_short_link_redirects_csv_reader()
{
	$rows = pg_short_link_csv_rows("\xEF\xBB\xBF" . "from,to,code\r\n/eski,/yeni,301\r\n\r\n\"/a,b\",\"/c \"\"d\"\"\",\r\n'=eski,'+yeni,302\r\n");

	pg_assert_same(3, count($rows), 'heading and empty line skipped');
	pg_assert_same(array('line' => 2, 'from' => '/eski', 'to' => '/yeni', 'code' => '301'), $rows[0], 'plain line');
	pg_assert_same(array('line' => 4, 'from' => '/a,b', 'to' => '/c "d"', 'code' => ''), $rows[1], 'quoted cells');
	pg_assert_same(array('line' => 5, 'from' => '=eski', 'to' => '+yeni', 'code' => '302'), $rows[2], 'the apostrophe before a formula character comes off');

	$semicolon = pg_short_link_csv_rows("Eski adres;Yeni adres\n/a;/b\n");
	pg_assert_same(1, count($semicolon), 'semicolons, Turkish heading');
	pg_assert_same('/b', $semicolon[0]['to'], 'semicolon cell');

	$tab = pg_short_link_csv_rows("/a\t/b\t302\n");
	pg_assert_same(array('line' => 1, 'from' => '/a', 'to' => '/b', 'code' => '302'), $tab[0], 'tabs, no heading');

	$windows = pg_short_link_csv_rows("/\xFCr\xFCn,/\xE7anta\n");
	pg_assert_same('/ürün', $windows[0]['from'], 'Windows-1254 bytes read as Turkish');
	pg_assert_same('/çanta', $windows[0]['to'], 'Windows-1254 second cell');

	pg_assert_same(array(), pg_short_link_csv_rows(''), 'empty file');
}

function test_short_link_redirects_csv_writer()
{
	$links = array(
		array('name' => 'ürün/eski', 'destination_type' => 'url', 'url' => 'https://example.com/a,b', 'redirect_code' => '301'),
		array('name' => 'kampanya', 'destination_type' => 'page', 'page_name' => 'yaz', 'tracking_code' => 'tw 1', 'url' => '', 'redirect_code' => '0'),
		array('name' => 'urun', 'destination_type' => 'product', 'page_name' => 'magaza', 'product_address_name' => 'canta', 'tracking_code' => '', 'url' => ''),
		array('name' => 'belge', 'destination_type' => 'file', 'file_name' => 'belge.pdf', 'tracking_code' => '', 'url' => '', 'redirect_code' => '302'),
		array('name' => 'formul', 'destination_type' => 'url', 'url' => '=1+1', 'redirect_code' => '301'),
	);

	$csv = pg_short_link_csv($links, '/site/');

	pg_assert_same("\xEF\xBB\xBF" . "from,to,code\r\n"
		. "\"/site/ürün/eski\",\"https://example.com/a,b\",301\r\n"
		. "\"/site/kampanya\",\"/site/yaz?t=tw%201\",0\r\n"
		. "\"/site/urun\",\"/site/magaza/canta\",0\r\n"
		. "\"/site/belge\",\"/site/belge.pdf\",302\r\n"
		. "\"/site/formul\",\"'=1+1\",301\r\n", $csv, 'file');

	// What the writer wrote, the reader reads back.
	$rows = pg_short_link_csv_rows($csv);

	pg_assert_same(5, count($rows), 'round trip');
	pg_assert_same('ürün/eski', pg_short_link_name_from_address($rows[0]['from'], '/site/'), 'name back without the base path');
	pg_assert_same('https://example.com/a,b', $rows[0]['to'], 'address with a comma');
	pg_assert_same('=1+1', $rows[4]['to'], 'formula guard taken off');
	pg_assert_same('', pg_short_link_csv_target(array('destination_type' => 'page', 'page_name' => null), '/'), 'a page that is gone');
}
