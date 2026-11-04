<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the content section of the Visitors overview
 * (includes/visitors/content.php): the rollup window, the row keys and the
 * earlier figure, the landing pages with the home page folded, and the
 * escaping of the tables.
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
require_once(PG_FUNCTIONS_DIR . '/includes/visitors/content.php');

function test_visitors_content_window_cuts_the_last_hour()
{
	$from = strtotime('2026-11-03 00:00:00');

	pg_assert_same(
		array('first' => '2026-11-03', 'last' => '2026-11-03', 'last_hour' => 14),
		pg_visitors_content_window($from, strtotime('2026-11-03 14:20:00')),
		'a day that ends now ends in the current hour'
	);
	pg_assert_same(
		array('first' => '2026-10-28', 'last' => '2026-11-03', 'last_hour' => 23),
		pg_visitors_content_window(strtotime('2026-10-28 00:00:00'), strtotime('2026-11-04 00:00:00')),
		'whole days end at hour 23'
	);
	pg_assert_same(
		array('first' => '2026-11-03', 'last' => '2026-11-03', 'last_hour' => 0),
		pg_visitors_content_window($from, $from),
		'an empty window keeps its first day'
	);
}

function test_visitors_content_kinds_follow_commerce()
{
	pg_assert_same(array('page', 'product', 'product_group', 'form'), array_keys(pg_visitors_content_kinds(true)));
	pg_assert_same(array('page', 'form'), array_keys(pg_visitors_content_kinds(false)), 'no catalog lists with commerce off');
}

function test_visitors_content_key_prefers_the_item_then_the_page()
{
	pg_assert_same('product:7', pg_visitors_content_key(array('item_type' => 'product', 'item_id' => '7', 'page_id' => 3, 'page_name' => 'shop')));
	pg_assert_same('page:3', pg_visitors_content_key(array('item_type' => '', 'item_id' => 0, 'page_id' => '3', 'page_name' => 'About')));
	pg_assert_same('name:eski-sayfa', pg_visitors_content_key(array('item_type' => '', 'item_id' => 0, 'page_id' => 0, 'page_name' => ' Eski-Sayfa ')), 'a deleted page by its name');
}

function test_visitors_content_rows_carry_the_earlier_views()
{
	$now = array(
		array('item_type' => 'product', 'item_id' => 7, 'page_id' => 3, 'page_name' => 'shop', 'views' => 40, 'label' => 'Lamp', 'url' => 'https://x/shop/lamp'),
		array('item_type' => 'product', 'item_id' => 9, 'page_id' => 3, 'page_name' => 'shop', 'views' => 12, 'label' => 'Chair', 'url' => 'https://x/shop/chair'),
		array('item_type' => 'product', 'item_id' => 11, 'page_id' => 3, 'page_name' => 'shop', 'views' => 2, 'label' => 'Desk', 'url' => ''),
	);
	$before = array(
		array('item_type' => 'product', 'item_id' => 7, 'page_id' => 5, 'page_name' => 'catalog', 'views' => 30),
	);

	$rows = pg_visitors_content_rows($now, $before, 2);

	pg_assert_same(array('Lamp', 'Chair'), array_column($rows, 'label'), 'cut to the limit');
	pg_assert_same(array(30, 0), array_column($rows, 'before'), 'an item meets its earlier views whichever page showed it');

	$rows = pg_visitors_content_rows($now, null, 10);

	pg_assert_same(array(null, null, null), array_column($rows, 'before'), 'not compared');
}

function test_visitors_content_landing_folds_the_home_page()
{
	$home = pg_visitors_content_lower_names(array('', '/', 'index.php', 'example.com/', 'Ana-Sayfa'));
	$now = array(
		array('value' => '', 'visits' => 30, 'forms_submitted' => 1, 'orders_completed' => 2),
		array('value' => 'ana-sayfa', 'visits' => 15, 'forms_submitted' => 0, 'orders_completed' => 1),
		array('value' => 'Hakkımızda', 'visits' => 20, 'forms_submitted' => 3, 'orders_completed' => 0),
		array('value' => 'hakkımızda', 'visits' => 5, 'forms_submitted' => 1, 'orders_completed' => 0),
		array('value' => 'blog', 'visits' => 25, 'forms_submitted' => 0, 'orders_completed' => 0),
	);
	$before = array(
		array('value' => '/', 'visits' => 10),
		array('value' => 'index.php', 'visits' => 4),
		array('value' => 'hakkımızda', 'visits' => 8),
		array('value' => 'BLOG', 'visits' => 6),
	);

	$rows = pg_visitors_content_landing_rows($now, $before, $home, 10);

	pg_assert_same(array('', 'Hakkımızda', 'blog'), array_column($rows, 'value'), 'home folded, case-blind, first spelling kept');
	pg_assert_same(array(true, false, false), array_column($rows, 'home'));
	pg_assert_same(array(45, 25, 25), array_column($rows, 'visits'));
	pg_assert_same(array(1, 4, 0), array_column($rows, 'forms'));
	pg_assert_same(array(3, 0, 0), array_column($rows, 'orders'));
	pg_assert_same(array(14, 8, 6), array_column($rows, 'before'));

	pg_assert_same(array('', 'Hakkımızda'), array_column(pg_visitors_content_landing_rows($now, null, $home, 2), 'value'), 'cut to the limit');
	pg_assert_same(array(), pg_visitors_content_landing_rows(array(array('value' => 'x', 'visits' => 0)), null, $home, 10), 'a page without a visit is left out');
}

function test_visitors_content_tables_escape_names()
{
	$html = pg_visitors_content_table('Pages', array(
		array('label' => '<b>x</b>', 'url' => 'https://x/a"b', 'views' => 5, 'before' => 2),
	), true);

	pg_assert_true(strpos($html, '&lt;b&gt;x&lt;/b&gt;') !== false, 'label escaped');
	pg_assert_true(strpos($html, 'href="https://x/a&quot;b"') !== false, 'link escaped');
	pg_assert_false(strpos($html, '<b>x</b>') !== false);

	$html = pg_visitors_content_landing_table(array(
		array('value' => '', 'home' => true, 'visits' => 3, 'forms' => 1, 'orders' => 0, 'before' => null),
	), false, false);

	pg_assert_true(strpos($html, lang('Homepage')) !== false, 'the home page by name');
	pg_assert_false(strpos($html, lang('Orders')) !== false, 'no orders column with commerce off');
	pg_assert_false(strpos($html, lang('Previous period')) !== false, 'no earlier column when not compared');
}
