<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the product detail page chosen when a widget names none
 * (pg_sw_first_viewable_page(), pg_sw_auto_catalog_detail_page() in
 * includes/fn/widgets.php): only a page the visitor can open is chosen, so a
 * product result never links to a private draft that sends the visitor to
 * the sign-in page.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_first_viewable_page_skips_pages_the_visitor_cannot_open()
{
	$pages = array(
		array('page_id' => '1714', 'page_name' => 'pg-test-parca', 'page_folder' => '305'),
		array('page_id' => '1678', 'page_name' => 'urun-detay', 'page_folder' => '305'),
		array('page_id' => '20', 'page_name' => 'urun', 'page_folder' => '1'),
	);
	$public = function ($folder_id) { return (int) $folder_id !== 305; };
	$picked = pg_sw_first_viewable_page($pages, $public);
	pg_assert_same('urun', $picked['page_name'], 'the private drafts are skipped');

	pg_assert_same(null, pg_sw_first_viewable_page(array_slice($pages, 0, 2), $public), 'no page the visitor can open: none');
	pg_assert_same(null, pg_sw_first_viewable_page(array(), $public), 'no page at all');

	$all = function ($folder_id) { return true; };
	$picked = pg_sw_first_viewable_page($pages, $all);
	pg_assert_same('pg-test-parca', $picked['page_name'], 'the order is kept');
}

// Both the search and the product links of lists, cart and checkout take
// the page from the one picker, which reads the folder of every candidate.
function test_auto_detail_page_is_shared_and_checks_access()
{
	$src = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/widgets.php');
	pg_assert_contains('function pg_sw_auto_catalog_detail_page(', $src);
	foreach (array('_pg_sw_search_collect', 'pg_sw_catalog_detail_page_name') as $name) {
		$start = strpos($src, 'function ' . $name . '(');
		pg_assert_true($start !== false, $name);
		$end = strpos($src, "\nfunction ", (int) $start + 10);
		$body = substr($src, (int) $start, (int) $end - (int) $start);
		pg_assert_contains('pg_sw_auto_catalog_detail_page()', $body, $name . ' uses the picker');
		pg_assert_false(strpos($body, "pg_sw_widget_pages('catalog_item_view')") !== false, $name . ' takes no first page itself');
	}
	$start = strpos($src, 'function pg_sw_auto_catalog_detail_page(');
	$body = substr($src, (int) $start, (int) strpos($src, "\nfunction ", (int) $start + 10) - (int) $start);
	pg_assert_contains('pg_sw_visitor_may_open_folder', $body, 'the folder access of the visitor is checked');
	$start = strpos($src, 'function pg_sw_visitor_may_open_folder(');
	$body = substr($src, (int) $start, (int) strpos($src, "\nfunction ", (int) $start + 10) - (int) $start);
	pg_assert_contains('check_view_access(', $body, 'the folder access of the visitor is checked');
	pg_assert_contains('get_access_control_type(', $body, 'without a visitor, the folder must be open to anyone');
}
