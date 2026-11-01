<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the per-request index behind pg_sw_widget_pages()
 * (includes/fn/widgets.php): the shared component ids a layout tree names,
 * matched exactly as the former LIKE '%"sharedId":N,%' / '%"sharedId":N}%'
 * pair did, and the pages picked for a set of ids in the listing order.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_sw_tree_shared_ids_matches_the_like_pair()
{
	$tree = '{"type":"root","children":[{"type":"shared_ref","props":{"sharedId":12}},'
		. '{"type":"shared_ref","props":{"sharedId":7,"x":1}},'
		. '{"type":"shared_ref","props":{"sharedId":12}}]}';

	pg_assert_same(array(12, 7), pg_sw_tree_shared_ids($tree), 'followed by } or , and listed once');
	pg_assert_same(array(), pg_sw_tree_shared_ids(''), 'no tree');
	pg_assert_same(array(), pg_sw_tree_shared_ids('{"sharedId": 5}'), 'a space is not matched');
	pg_assert_same(array(), pg_sw_tree_shared_ids('{"sharedId":5]'), 'other closing characters are not matched');
	pg_assert_same(array(), pg_sw_tree_shared_ids('{"sharedId":"5"}'), 'a quoted id is not matched');
	pg_assert_same(array(), pg_sw_tree_shared_ids('{"sharedId":05}'), 'a leading zero names no id');
	pg_assert_same(array(5), pg_sw_tree_shared_ids('{"SHAREDID":5}'), 'case-insensitive like the column collation');
	pg_assert_same(array(3), pg_sw_tree_shared_ids('{"type":"box","meta":{"sharedId":3}}'), 'on any node, as LIKE');
}

function test_sw_pick_widget_pages_keeps_the_listing_order()
{
	$pages = array(
		array('page_id' => '4', 'page_name' => 'a', 'page_title' => 'A'),
		array('page_id' => '2', 'page_name' => 'b', 'page_title' => 'B'),
		array('page_id' => '9', 'page_name' => 'c', 'page_title' => 'C'),
	);
	$by_sid = array(10 => array(2, 0), 11 => array(0), 12 => array(1));

	pg_assert_same(array($pages[0], $pages[2]), pg_sw_pick_widget_pages($pages, $by_sid, array(10, 11)), 'each page once, in order');
	pg_assert_same(array($pages[1]), pg_sw_pick_widget_pages($pages, $by_sid, array(12, 99)), 'an id on no page');
	pg_assert_same(array(), pg_sw_pick_widget_pages($pages, $by_sid, array()), 'no ids');
}
