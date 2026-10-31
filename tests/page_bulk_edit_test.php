<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for one page of a bulk page edit (pg_page_bulk_decide(),
 * includes/fn/page_bulk.php): the style only for staff and never on a page
 * the visual editor opens, the SEO stale mark, the sitemap switch by page
 * type and noindex, nofollow kept only with noindex, and the site-search
 * keyword cloud.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/page_bulk.php');

// A classic standard page in folder 5, in the search and the sitemap.
function page_bulk_test_page($changes = array())
{
	return array_merge(array(
		'page_type' => 'standard',
		'page_folder' => '5',
		'page_search' => '1',
		'sitemap' => '1',
		'noindex' => '0',
		'nofollow' => '0',
		'is_visual' => '0',
	), $changes);
}

function test_page_bulk_style_only_for_staff()
{
	$set = array('style' => '7', 'mobile_style' => '0');

	$staff = pg_page_bulk_decide(page_bulk_test_page(), $set, 2);
	pg_assert_same(array('desktop' => 7, 'mobile' => 0), $staff['styles']);
	pg_assert_same(false, $staff['style_skipped']);

	$user = pg_page_bulk_decide(page_bulk_test_page(), $set, 3);
	pg_assert_same(array(), $user['styles'], 'a role 3 user sets no style');
	pg_assert_same(false, $user['style_skipped'], 'nor is it counted as skipped');
}

function test_page_bulk_visual_page_keeps_its_style()
{
	$decision = pg_page_bulk_decide(page_bulk_test_page(array('is_visual' => '1')), array('style' => '7', 'search' => '0'), 1);

	pg_assert_same(array(), $decision['styles']);
	pg_assert_same(true, $decision['style_skipped']);
	pg_assert_same(array('page_search' => 0), $decision['columns'], 'the rest is still written');
}

function test_page_bulk_stale_when_folder_search_or_sitemap_change()
{
	$page = page_bulk_test_page();

	pg_assert_same(true, pg_page_bulk_decide($page, array('folder' => '9'), 3)['stale'], 'folder');
	pg_assert_same(true, pg_page_bulk_decide($page, array('search' => '0'), 3)['stale'], 'search');
	pg_assert_same(true, pg_page_bulk_decide($page, array('sitemap' => '0'), 3)['stale'], 'sitemap');

	$same = pg_page_bulk_decide($page, array('folder' => '5', 'search' => '1', 'sitemap' => '1'), 3);
	pg_assert_same(array(), $same['columns'], 'unchanged values are not written');
	pg_assert_same(false, $same['stale']);

	$comments = pg_page_bulk_decide($page, array('comments' => '1'), 3);
	pg_assert_same(array('comments' => 1), $comments['columns']);
	pg_assert_same(false, $comments['stale'], 'comments are not scored');
}

function test_page_bulk_sitemap_follows_page_type()
{
	$off = page_bulk_test_page(array('sitemap' => '0'));

	foreach (array('standard', 'catalog detail', 'form item view', 'calendar event view') as $type) {
		$decision = pg_page_bulk_decide(array_merge($off, array('page_type' => $type)), array('sitemap' => '1'), 3);
		pg_assert_same(array('sitemap' => 1), $decision['columns'], $type);
	}

	foreach (array('login', 'error', 'logout', 'registration entrance') as $type) {
		$decision = pg_page_bulk_decide(array_merge($off, array('page_type' => $type)), array('sitemap' => '1'), 3);
		pg_assert_same(array(), $decision['columns'], $type . ' is never switched on');
	}

	$on_login = pg_page_bulk_decide(page_bulk_test_page(array('page_type' => 'login')), array('sitemap' => '0'), 3);
	pg_assert_same(array('sitemap' => 0), $on_login['columns'], 'switching off works for any type');
}

function test_page_bulk_noindex_keeps_page_out_of_sitemap()
{
	$noindex_page = page_bulk_test_page(array('sitemap' => '0', 'noindex' => '1', 'nofollow' => '1'));
	$decision = pg_page_bulk_decide($noindex_page, array('sitemap' => '1'), 3);
	pg_assert_same(array(), $decision['columns'], 'a noindex page is not put in the sitemap');

	$closing = pg_page_bulk_decide(page_bulk_test_page(), array('noindex' => '1', 'nofollow' => '1'), 3);
	pg_assert_same(array('noindex' => 1, 'nofollow' => 1, 'sitemap' => 0), $closing['columns'], 'noindex takes the page out of the sitemap');
	pg_assert_same(true, $closing['stale']);

	$opening = pg_page_bulk_decide($noindex_page, array('noindex' => '0', 'sitemap' => '1'), 3);
	pg_assert_same(array('noindex' => 0, 'nofollow' => 0, 'sitemap' => 1), $opening['columns'], 'nofollow goes with noindex');

	$lone = pg_page_bulk_decide(page_bulk_test_page(), array('nofollow' => '1'), 3);
	pg_assert_same(array(), $lone['columns'], 'nofollow is not stored without noindex');
}

function test_page_bulk_tag_cloud_follows_search_flip()
{
	pg_assert_same('remove', pg_page_bulk_decide(page_bulk_test_page(), array('search' => '0'), 3)['tag_cloud']);
	pg_assert_same('add', pg_page_bulk_decide(page_bulk_test_page(array('page_search' => '0')), array('search' => '1'), 3)['tag_cloud']);
	pg_assert_same('', pg_page_bulk_decide(page_bulk_test_page(), array('search' => '1'), 3)['tag_cloud'], 'no flip');
	pg_assert_same('', pg_page_bulk_decide(page_bulk_test_page(), array('search' => 'x'), 3)['tag_cloud'], 'not a value');
}
