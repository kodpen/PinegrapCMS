<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for short links that redirect (includes/fn/short_link_redirects.php):
 * a name taken from a pasted address and the name rule, names with a * and
 * what each * matched, where an address on this site leads, the checks that
 * keep a link from leading to itself or round a loop, the address a visitor
 * is sent to and the status it is sent with, the answer and the name as a
 * window posts them, the CSV file of links that the list exports and the
 * import reads, and what a renamed page's old name may become. The runner
 * has no connection, so the columns of 2026.11.0 (11.0.12) read as missing:
 * names stop at 100 characters and pg_short_link_redirect_columns() writes
 * nothing, as on a site that has not run the upgrade.
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

// Without a connection the keys come from pg_short_link_fold(), which keeps
// to the pairs utf8mb4_unicode_ci decides: marks that decompose go, ı, ø, ł stay.
function test_short_link_redirects_key_without_a_database_follows_the_collation()
{
	pg_assert_true(pg_short_link_key('a') !== pg_short_link_key('b'), 'different names');
	pg_assert_same(pg_short_link_key('blog'), pg_short_link_key('BLOG'), 'case');

	if (!class_exists('Normalizer')) {
		return;
	}

	pg_assert_same(pg_short_link_key('urun'), pg_short_link_key('ÜRÜN'), 'Ü');
	pg_assert_same(pg_short_link_key('istanbul'), pg_short_link_key('İstanbul'), 'dotted capital I');
	pg_assert_same(pg_short_link_key('swieta'), pg_short_link_key('święta'), 'ę, as the database: ś and ę decompose');
	pg_assert_same(pg_short_link_key('sarbatoare'), pg_short_link_key('sărbătoare'), 'ă');
	pg_assert_same(pg_short_link_key('fi'), pg_short_link_key('ﬁ'), 'a ligature, as its letters');
	pg_assert_true(pg_short_link_key('hakkimizda') !== pg_short_link_key('hakkımızda'), 'ı is not i to the database');
	pg_assert_true(pg_short_link_key('o') !== pg_short_link_key('ø'), 'ø has no mark to drop');
	pg_assert_true(pg_short_link_key('l') !== pg_short_link_key('ł'), 'nor has ł');
}

function test_short_link_redirects_names_the_database_keeps_apart_are_not_a_loop()
{
	if (!class_exists('Normalizer')) {
		return;
	}

	pg_assert_same('', short_link_redirects_test_problem(short_link_redirects_test_link(0, 'hakkimizda', '/hakk%C4%B1m%C4%B1zda')), 'ı and i are two names');
	pg_assert_same('to_self', short_link_redirects_test_problem(short_link_redirects_test_link(0, 'święta', '/swieta')), 'ę and e are one');
	pg_assert_same('to_loop', short_link_redirects_test_problem(short_link_redirects_test_link(0, 'a', '/święta'), array(short_link_redirects_test_link(1, 'swieta', '/a'))), 'a loop through a name the database finds');
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

function test_short_link_redirects_a_requested_path_is_read_as_names_are_stored()
{
	pg_assert_same('blog/2019/05/yazi', pg_short_link_path_name('blog/2019/05/yazi/'), 'a slash at the end');
	pg_assert_same('eski/yazi', pg_short_link_path_name('/eski//yazi'), 'a slash at the front, a run of slashes');
	pg_assert_same('Eski_Sayfa_Adı', pg_short_link_path_name('Eski Sayfa Adı'), 'spaces');
	pg_assert_same('a/b', pg_short_link_path_name('a/b'), 'a stored name stays');

	$index = pg_short_link_index(array(
		short_link_redirects_test_link(1, 'blog/2019/05/yazi', '/yeni'),
		short_link_redirects_test_link(2, 'Eski_Sayfa', '/yeni'),
		short_link_redirects_test_link(3, 'arsiv/*', '/yeni/$1'),
	));

	$slash = pg_short_link_match_in('blog/2019/05/yazi/', $index);
	$space = pg_short_link_match_in('eski sayfa', $index);
	$double = pg_short_link_match_in('blog//2019/05/yazi', $index);
	$pattern = pg_short_link_match_in('arsiv/a b', $index, false);

	pg_assert_same(1, (int) $slash['link']['id'], 'an old address with a slash at the end');
	pg_assert_same(2, (int) $space['link']['id'], 'an old address with a space');
	pg_assert_same(1, (int) $double['link']['id'], 'an old address with a run of slashes');
	pg_assert_same(array('a b'), $pattern['captures'], 'a pattern takes the address as it came first');
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
	pg_assert_same('to_self', short_link_redirects_test_problem(short_link_redirects_test_link(0, 'Eski_Sayfa', '/Eski%20Sayfa')), 'the address reaches the name in its stored form');
	pg_assert_same('to_self', short_link_redirects_test_problem(short_link_redirects_test_link(0, 'Eski_*', '/Eski%20$1')), 'a pattern, its stored form');
}

function test_short_link_redirects_refuses_loops_and_long_chains()
{
	$links = array(short_link_redirects_test_link(1, 'b', '/a'));

	pg_assert_same('to_loop', short_link_redirects_test_problem(short_link_redirects_test_link(0, 'a', '/b'), $links), 'a -> b -> a');
	pg_assert_same('', short_link_redirects_test_problem(short_link_redirects_test_link(0, 'c', '/b'), $links), 'c -> b -> a ends at a page');
	pg_assert_same('to_loop', short_link_redirects_test_problem(short_link_redirects_test_link(0, 'x', '/a%20b/'), array(short_link_redirects_test_link(2, 'a_b', '/x'))), 'x -> a_b (asked as "a b/") -> x');

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

// A pattern saved after the links it closes a loop with: the loop shows only
// for the requests those links send to it, not for any sample request.
function test_short_link_redirects_pattern_saved_last_is_walked_from_the_links_that_reach_it()
{
	$exact = array(short_link_redirects_test_link(1, 'q/z', '/p/z'));

	pg_assert_same('to_loop', short_link_redirects_test_problem(short_link_redirects_test_link(0, 'p/*', '/q/$1'), $exact), 'p/z -> q/z -> p/z');

	$prefixed = array(short_link_redirects_test_link(1, 'b/c*', '/a/c$1'));

	pg_assert_same('to_loop', short_link_redirects_test_problem(short_link_redirects_test_link(0, 'a/*', '/b/$1'), $prefixed), 'a/cx -> b/cx -> a/cx');

	// a/x is answered by its own exact link, so coming back to it is no loop.
	$taken = array(
		short_link_redirects_test_link(1, 'c', '/a/y'),
		short_link_redirects_test_link(2, 'b/y', '/a/x'),
		short_link_redirects_test_link(3, 'a/x', 'https://other.com/'),
	);

	pg_assert_same('', short_link_redirects_test_problem(short_link_redirects_test_link(0, 'a/*', '/b/$1'), $taken), 'a/y -> b/y -> a/x (exact) -> another site');

	$elsewhere = array(short_link_redirects_test_link(1, 'q/z', '/r/z'));

	pg_assert_same('', short_link_redirects_test_problem(short_link_redirects_test_link(0, 'p/*', '/q/$1'), $elsewhere), 'no link reaches the pattern');

	// Three-link loops whose way into a pattern goes through another pattern:
	// refused whichever link is saved last.
	$loops = array(
		'q/z -> x/z -> p/z -> q/z' => array(
			short_link_redirects_test_link(1, 'x/*', '/p/$1'),
			short_link_redirects_test_link(2, 'q/z', '/x/z'),
			short_link_redirects_test_link(3, 'p/*', '/q/$1'),
		),
		'c/k -> a/k -> b/k -> c/k' => array(
			short_link_redirects_test_link(1, 'a/*', '/b/$1'),
			short_link_redirects_test_link(2, 'b/*', '/c/$1'),
			short_link_redirects_test_link(3, 'c/k', '/a/k'),
		),
	);

	foreach ($loops as $label => $links) {
		foreach ($links as $i => $new) {
			$stored = $links;
			unset($stored[$i]);

			pg_assert_same('to_loop', short_link_redirects_test_problem($new, array_values($stored)), $label . ', ' . $new['name'] . ' saved last');
		}
	}
}

// A chain that passes a pattern twice with different captures and then ends
// is no loop: r -> p/a -> q/a -> p/b -> q/b -> another site.
function test_short_link_redirects_pattern_passing_twice_with_other_captures_is_no_loop()
{
	$links = array(
		short_link_redirects_test_link(1, 'p/*', '/q/$1'),
		short_link_redirects_test_link(2, 'q/a', '/p/b'),
		short_link_redirects_test_link(3, 'q/b', 'https://other.com/'),
		short_link_redirects_test_link(4, 'r', '/p/a'),
	);

	foreach ($links as $i => $new) {
		$stored = $links;
		unset($stored[$i]);

		pg_assert_same('', short_link_redirects_test_problem($new, array_values($stored)), $new['name'] . ' saved last');
	}
}

// A loop among stored links alone is not the new pattern's doing:
// s/a -> s/b -> s/a stays as it is when p/* is saved.
function test_short_link_redirects_pattern_is_not_blamed_for_a_stored_loop()
{
	$stored = array(
		short_link_redirects_test_link(1, 'r', '/p/a'),
		short_link_redirects_test_link(2, 'q/a', '/s/a'),
		short_link_redirects_test_link(3, 's/a', '/s/b'),
		short_link_redirects_test_link(4, 's/b', '/s/a'),
	);

	pg_assert_same('', short_link_redirects_test_problem(short_link_redirects_test_link(0, 'p/*', '/q/$1'), $stored), 'r -> p/a -> q/a -> s/a -> s/b -> s/a');
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

function test_short_link_redirects_posted_answer()
{
	pg_assert_same(null, pg_short_link_answer_posted(null), 'not sent: the stored answer stays');
	pg_assert_same(null, pg_short_link_answer_posted(''), 'empty');
	pg_assert_same(null, pg_short_link_answer_posted('  '), 'blank');
	pg_assert_same(null, pg_short_link_answer_posted(array('301')), 'a list');
	pg_assert_same(0, pg_short_link_answer_posted('0'), 'shown at its own address');
	pg_assert_same(301, pg_short_link_answer_posted('301'), '301');
	pg_assert_same(302, pg_short_link_answer_posted(302), '302');
	pg_assert_same(0, pg_short_link_answer_for(pg_short_link_answer_posted('307'), 'eski'), 'not an answer is saved as 0');
	pg_assert_same(301, pg_short_link_answer_for(pg_short_link_answer_posted('0'), 'eski/*'), 'a pattern still redirects');
}

function test_short_link_redirects_name_read_says_what_is_wrong()
{
	$read = pg_short_link_name_read('https://old.example.com/blog/Ürün Sayfası?x=1');

	pg_assert_same('blog/Ürün_Sayfası', $read['name'], 'the path of a pasted address');
	pg_assert_same('', $read['problem'], 'which can be used');

	pg_assert_same(pg_short_link_name_problem(''), pg_short_link_name_read('   ')['problem'], 'empty');
	pg_assert_same(pg_short_link_name_problem(str_repeat('a', 101)), pg_short_link_name_read(str_repeat('a', 101))['problem'], 'too long, said as such');
	pg_assert_true(pg_short_link_name_read(str_repeat('a', 101))['problem'] !== pg_short_link_name_rule_text(), 'not the whole rule for a length problem');
	pg_assert_same(pg_short_link_name_rule_text(), pg_short_link_name_read('a<b')['problem'], 'a character outside the rule');
	pg_assert_same(pg_short_link_name_problem('../gizli'), pg_short_link_name_read('../gizli')['problem'], 'a part that cannot be opened');

	$list = pg_short_link_name_read(array('eski'));

	pg_assert_same('', $list['name'], 'a list is no name');
	pg_assert_true($list['problem'] !== '', 'and is refused');
}

function test_short_link_redirects_columns_wait_for_the_upgrade()
{
	$answer = pg_short_link_redirect_columns(short_link_redirects_test_link(0, 'eski', '/eski'), 301, 0, array());

	pg_assert_same(array(), $answer['columns'], 'nothing is written before the upgrade');
	pg_assert_same(null, $answer['problem'], 'nor checked');
	pg_assert_same(100, pg_short_link_name_max(), 'the name column before the upgrade');
	pg_assert_same(255, pg_short_link_url_max(), 'the address column before the upgrade');
}

function test_short_link_redirects_name_fault_codes()
{
	pg_assert_same('', pg_short_link_name_fault('blog/Ürün_Sayfası', 191), 'a name that can be used');
	pg_assert_same('empty', pg_short_link_name_fault('', 191), 'empty');
	pg_assert_same('length', pg_short_link_name_fault(str_repeat('ç', 101), 100), 'counted in letters');
	pg_assert_same('', pg_short_link_name_fault(str_repeat('ç', 100), 100), 'as long as allowed');
	pg_assert_same('characters', pg_short_link_name_fault('a?b', 191), 'a character outside the rule');
	pg_assert_same('wildcards', pg_short_link_name_fault(str_repeat('a*', 10), 191), 'ten stars');
	pg_assert_same('stars_only', pg_short_link_name_fault('*/*', 191), 'stars and slashes alone');
	pg_assert_same('dot_part', pg_short_link_name_fault('a/../b', 191), 'a part a browser resolves');
	pg_assert_same(pg_short_link_name_rule_text(), pg_short_link_name_problem('a?b'), 'the problem says the rule for a character');
}

function test_short_link_redirects_renamed_page_old_name()
{
	pg_assert_same('', pg_short_link_rename_problem('hakkimizda', 'kurumsal'), 'a new name');
	pg_assert_same('', pg_short_link_rename_problem('blog/eski', 'blog/yeni'), 'a name with a slash');
	pg_assert_same('unchanged', pg_short_link_rename_problem('About', 'about'), 'case only: the page is found by the old name');
	pg_assert_same('unchanged', pg_short_link_rename_problem('eski sayfa', 'eski_sayfa'), 'the same stored form');
	pg_assert_same('unchanged', pg_short_link_rename_problem('eski_sayfa', 'eski sayfa'), 'the same stored form, the other way');
	pg_assert_same('unchanged', pg_short_link_rename_problem('', 'yeni'), 'no old name');
	pg_assert_same('unchanged', pg_short_link_rename_problem('eski', '  '), 'no new name');
	pg_assert_same('name', pg_short_link_rename_problem('a*b', 'ab'), 'a star would make a pattern');
	pg_assert_same('name', pg_short_link_rename_problem('soru?', 'soru'), 'a character no short link takes');
	pg_assert_same('name', pg_short_link_rename_problem(str_repeat('a', 101), 'b'), 'longer than the name column takes');

	if (class_exists('Normalizer')) {
		pg_assert_same('unchanged', pg_short_link_rename_problem('ürün', 'URUN'), 'one name to the database');
		pg_assert_same('', pg_short_link_rename_problem('hakkımızda', 'hakkimizda'), 'ı and i are two names');
	}
}

function test_short_link_redirects_renamed_page_waits_for_the_upgrade()
{
	pg_assert_same('', pg_short_link_not_redirect_to_page_sql(5), 'no link redirects before the upgrade');

	$renamed = pg_short_link_page_renamed(5, 'eski', 'yeni', true, 1, 0);

	pg_assert_same(false, $renamed['added'], 'nothing is added');
	pg_assert_same(0, $renamed['removed'], 'nor removed');
	pg_assert_same('schema', $renamed['skipped'], 'and says why');
	pg_assert_same('eski', $renamed['from'], 'the old name');
}

function test_short_link_redirects_renamed_page_messages()
{
	$renamed = function ($added, $removed, $skipped) {
		return array('from' => 'eski sayfa', 'to' => 'yeni', 'added' => $added, 'removed' => $removed, 'skipped' => $skipped);
	};

	$said = pg_short_link_page_renamed_messages($renamed(true, 0, ''), '/site/');

	pg_assert_same(1, count($said['notes']), 'added: one note');
	pg_assert_true(strpos($said['notes'][0], '/site/eski sayfa') !== false && strpos($said['notes'][0], '/site/yeni') !== false, 'naming both addresses under the base path');
	pg_assert_same(array(), $said['warnings'], 'and no warning');

	$said = pg_short_link_page_renamed_messages($renamed(false, 1, 'off'), '/');

	pg_assert_same(1, count($said['notes']), 'a redirect of its own removed');
	pg_assert_true(strpos($said['notes'][0], '/yeni') !== false, 'names the address the page took back');

	foreach (array('name', 'taken', 'loop') as $skipped) {
		$said = pg_short_link_page_renamed_messages($renamed(false, 0, $skipped), '/');

		pg_assert_same(1, count($said['warnings']), 'asked for and not added: ' . $skipped);
		pg_assert_same(array(), $said['notes'], 'no note: ' . $skipped);
	}

	foreach (array('off', 'unchanged', 'draft', 'schema') as $skipped) {
		$said = pg_short_link_page_renamed_messages($renamed(false, 0, $skipped), '/');

		pg_assert_same(array('notes' => array(), 'warnings' => array()), $said, 'nothing to say: ' . $skipped);
	}
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

function test_short_link_redirects_csv_reader_reads_excel_unicode_text()
{
	$text = "Eski adres\tYeni adres\r\n/gtest-sl/utf16-a\t/about-us\r\n/gtest-sl/ürün\t/contact-us\r\n";
	$little = "\xFF\xFE" . mb_convert_encoding($text, 'UTF-16LE', 'UTF-8');
	$rows = pg_short_link_csv_rows($little);

	pg_assert_same(2, count($rows), 'UTF-16LE with its mark: heading skipped, two lines');
	pg_assert_same(array('line' => 2, 'from' => '/gtest-sl/utf16-a', 'to' => '/about-us', 'code' => ''), $rows[0], 'line numbers as the file counts them');
	pg_assert_same(array('line' => 3, 'from' => '/gtest-sl/ürün', 'to' => '/contact-us', 'code' => ''), $rows[1], 'a Turkish letter');

	pg_assert_same($rows, pg_short_link_csv_rows("\xFE\xFF" . mb_convert_encoding($text, 'UTF-16BE', 'UTF-8')), 'UTF-16BE with its mark');
	pg_assert_same($rows, pg_short_link_csv_rows(mb_convert_encoding($text, 'UTF-16LE', 'UTF-8')), 'UTF-16LE without a mark');
	pg_assert_same($rows, pg_short_link_csv_rows(mb_convert_encoding($text, 'UTF-16BE', 'UTF-8')), 'UTF-16BE without a mark');
}

function test_short_link_redirects_csv_separator_is_the_one_most_lines_split_by()
{
	$rows = pg_short_link_csv_rows("/gtest-csv/kalem,defter;/about-us;301\n/gtest-csv/eski-sayfa;/contact-us;301\n/gtest-csv/eski-iki;/contact-us;302\n");

	pg_assert_same(3, count($rows), 'semicolons, a comma inside the first address');
	pg_assert_same(array('line' => 1, 'from' => '/gtest-csv/kalem,defter', 'to' => '/about-us', 'code' => '301'), $rows[0], 'the comma stays in the address');

	pg_assert_same(',', pg_short_link_csv_separator("from,to,code\n/a,/b?x=1;y=2,301\n"), 'a semicolon in a query string');
	pg_assert_same(',', pg_short_link_csv_separator("/a,/b\n"), 'one line, commas');
	pg_assert_same(';', pg_short_link_csv_separator("Eski adres;Yeni adres\n/a;/b\n"), 'semicolons with a heading');
	pg_assert_same("\t", pg_short_link_csv_separator("/a\t/b\t302\n"), 'tabs');
	pg_assert_same(',', pg_short_link_csv_separator(''), 'nothing to go by: the comma');
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

function test_short_link_redirects_numbered_name_keeps_to_the_column()
{
	$long = 'gtest-long/' . str_repeat('a', 180);

	pg_assert_same('blog/x[1]', pg_short_link_numbered_name('blog/x', 1, 191), 'room enough');
	pg_assert_same(191, mb_strlen(pg_short_link_numbered_name($long, 1, 191), 'UTF-8'), '191 characters: the end gives way');
	pg_assert_same('gtest-long/' . str_repeat('a', 177) . '[1]', pg_short_link_numbered_name($long, 1, 191), 'the suffix is whole');
	pg_assert_same('gtest-long/' . str_repeat('b', 176) . '[12]', pg_short_link_numbered_name('gtest-long/' . str_repeat('b', 179), 12, 191), 'a longer suffix');
	pg_assert_same(str_repeat('ü', 97) . '[1]', pg_short_link_numbered_name(str_repeat('ü', 99), 1, 100), 'characters, not bytes (100 before the step)');
	pg_assert_same('', pg_short_link_numbered_name('abc', 1, 3), 'no room for any of the name');
}
