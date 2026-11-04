<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the pages section of the Visitors overview
 * (includes/visitors/pages.php): the page key on Google addresses, pretty
 * paths, the root and its aliases and encoded Turkish letters; the join of
 * Google, visit, view and score rows; the sort; the escaping of the table.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_VISITORS_SCREEN')) {
	define('PG_VISITORS_SCREEN', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/visitors/shell.php');
require_once(PG_FUNCTIONS_DIR . '/includes/visitors/pages.php');

function test_visitors_pages_key_of_addresses()
{
	pg_assert_same('about-us', pg_visitor_page_key('about-us'));
	pg_assert_same('about-us', pg_visitor_page_key('/About-Us/'), 'slashes and case');
	pg_assert_same('about-us', pg_visitor_page_key('https://www.example.com/about-us?utm_source=x#top'), 'scheme, host, query and fragment');
	pg_assert_same('about-us', pg_visitor_page_key('/about-us.html'), 'the .html ending');
	pg_assert_same('blog/yeni-ürün', pg_visitor_page_key('/blog/yeni-%C3%BCr%C3%BCn'), 'pretty path, encoded Turkish letters');
	pg_assert_same('ığüşöç', pg_visitor_page_key('/%C4%B1%C4%9F%C3%BC%C5%9F%C3%B6%C3%A7'), 'dotless i stays');
	pg_assert_same('ürünler', pg_visitor_page_key('ÜRÜNLER'), 'upper-case Turkish letters');
}

function test_visitors_pages_key_folds_the_home_page()
{
	$home = array('ana-sayfa', 'Home');

	foreach (array('', '/', 'index.php', 'https://example.com/', 'https://example.com', 'example.com/', '/Ana-Sayfa', 'home.html') as $address) {
		pg_assert_same('', pg_visitor_page_key($address, $home), 'home alias: ' . $address);
	}

	pg_assert_same('ana-sayfa', pg_visitor_page_key('/ana-sayfa'), 'a name is a home name only when given');
	pg_assert_same('ana-sayfa/alt', pg_visitor_page_key('/ana-sayfa/alt', $home), 'a path under a home name is its own page');
}

function test_visitors_pages_join_keeps_rows_one_side_alone_knows()
{
	$google = array(
		array('name' => 'https://example.com/about-us', 'clicks' => 10, 'impressions' => 100, 'position' => 4.0),
		array('name' => 'https://example.com/about-us/', 'clicks' => 2, 'impressions' => 100, 'position' => 8.0),
		array('name' => 'https://example.com/', 'clicks' => 5, 'impressions' => 50, 'position' => 1.5),
		array('name' => 'https://example.com/only-google', 'clicks' => 1, 'impressions' => 9, 'position' => 12.0),
	);
	$landing = array(
		array('value' => 'About-Us', 'visits' => 30, 'forms_submitted' => 2, 'orders_completed' => 1),
		array('value' => '', 'visits' => 70, 'forms_submitted' => 0, 'orders_completed' => 3),
		array('value' => 'only-visits', 'visits' => 4, 'forms_submitted' => 0, 'orders_completed' => 0),
	);
	$views = array(
		array('page_name' => 'about-us', 'views' => 55),
		array('page_name' => 'home', 'views' => 120),
	);
	$pages = array(
		array('page_id' => 3, 'page_name' => 'about-us', 'page_home' => 'no', 'seo_score' => 72, 'scored' => true),
		array('page_id' => 1, 'page_name' => 'home', 'page_home' => 'yes', 'seo_score' => 90, 'scored' => true),
		array('page_id' => 8, 'page_name' => 'only-visits', 'page_home' => 'no', 'seo_score' => 0, 'scored' => false),
	);

	$rows = pg_visitor_pages_join($google, $landing, $views, $pages, 'https://example.com/', array('home'));

	pg_assert_same(array('about-us', '', 'only-google', 'only-visits'), array_keys($rows));

	pg_assert_same(12, $rows['about-us']['clicks'], 'two Google addresses of one page add up');
	pg_assert_same(200, $rows['about-us']['impressions']);
	pg_assert_same(6.0, $rows['about-us']['position'], 'position weighted by impressions');
	pg_assert_same(0.06, $rows['about-us']['ctr']);
	pg_assert_same(30, $rows['about-us']['visits']);
	pg_assert_same(55, $rows['about-us']['page_views']);
	pg_assert_same(2, $rows['about-us']['forms']);
	pg_assert_same(1, $rows['about-us']['orders']);
	pg_assert_same(72, $rows['about-us']['score']);
	pg_assert_same(3, $rows['about-us']['page_id']);

	pg_assert_same(70, $rows['']['visits'], 'the root and the home page name are one row');
	pg_assert_same(120, $rows['']['page_views']);
	pg_assert_same(5, $rows['']['clicks']);
	pg_assert_same(90, $rows['']['score']);

	pg_assert_same(null, $rows['only-visits']['clicks'], 'no Google figure is not 0');
	pg_assert_same(null, $rows['only-visits']['score'], 'a page never scored has no score');
	pg_assert_same(8, $rows['only-visits']['page_id']);
	pg_assert_same(0, $rows['only-google']['visits']);
	pg_assert_same(0, $rows['only-google']['page_id'], 'no site page known');
}

function test_visitors_pages_sort()
{
	$rows = array(
		'a' => array('key' => 'a', 'clicks' => 5, 'visits' => 1, 'score' => null),
		'b' => array('key' => 'b', 'clicks' => null, 'visits' => 9, 'score' => 40),
		'c' => array('key' => 'c', 'clicks' => 7, 'visits' => 3, 'score' => 80),
	);

	pg_assert_same(array('c', 'a', 'b'), array_keys(pg_visitor_pages_sort($rows, 'clicks')), 'no Google figure last');
	pg_assert_same(array('b', 'c', 'a'), array_keys(pg_visitor_pages_sort($rows, 'visits')));
	pg_assert_same(array('c', 'b', 'a'), array_keys(pg_visitor_pages_sort($rows, 'score')), 'no score last');
	pg_assert_same('clicks', pg_visitors_pages_sort_key('nonsense', true));
	pg_assert_same('visits', pg_visitors_pages_sort_key('clicks', false), 'clicks need Google');
	pg_assert_same('score', pg_visitors_pages_sort_key('score', false));
}

function test_visitors_pages_table_escapes_and_drops_columns()
{
	$rows = array(
		'x' => array('key' => '<b>x</b>', 'clicks' => null, 'impressions' => null, 'ctr' => null, 'position' => null,
			'visits' => 3, 'page_views' => 4, 'forms' => 0, 'orders' => 0, 'score' => null, 'page_id' => 0),
	);

	$html = pg_visitors_pages_table($rows, false, false, 'https://example.com/');

	pg_assert_true(strpos($html, '/&lt;b&gt;x&lt;/b&gt;') !== false, 'path escaped');
	pg_assert_false(strpos($html, '<b>x</b>') !== false);
	pg_assert_false(strpos($html, lang('Impressions')) !== false, 'no Google columns when not linked');
	pg_assert_false(strpos($html, lang('Orders')) !== false, 'no orders with commerce off');
}
