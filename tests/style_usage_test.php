<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the style usage counts of the styles list
 * (includes/fn/style_usage.php): folders and pages count the styles they
 * work out to, inherited desktop and mobile styles included, and a mobile
 * style that is the desktop style once.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/style_usage.php');

function style_usage_test_folder($id, $parent, $style, $mobile)
{
	return array('id' => $id, 'parent_folder_id' => $parent, 'style_id' => $style, 'mobile_style_id' => $mobile);
}

function style_usage_test_page($folder, $style, $mobile)
{
	return array('folder_id' => $folder, 'style_id' => $style, 'mobile_style_id' => $mobile);
}

function style_usage_test_counts($usage, $style_id)
{
	return isset($usage[$style_id]) ? array($usage[$style_id]['number_of_folders'], $usage[$style_id]['number_of_pages']) : array(0, 0);
}

// A page with neither style of its own uses both of its folder's styles:
// the old count compared the stored zeros and left the mobile style out.
function test_style_usage_page_inherits_both_styles()
{
	$usage = pg_style_usage(
		array(style_usage_test_folder(1, 0, 5, 6)),
		array(style_usage_test_page(1, 0, 0))
	);

	pg_assert_same(array(1, 1), style_usage_test_counts($usage, 5), 'desktop style: the folder and the page');
	pg_assert_same(array(1, 1), style_usage_test_counts($usage, 6), 'mobile style: the folder and the page');
}

// Its own desktop style and an inherited mobile style that is the same
// style: counted once, not twice.
function test_style_usage_same_style_counted_once()
{
	$usage = pg_style_usage(
		array(style_usage_test_folder(1, 0, 5, 5)),
		array(style_usage_test_page(1, 5, 0), style_usage_test_page(1, 7, 5))
	);

	pg_assert_same(array(1, 2), style_usage_test_counts($usage, 5), 'style 5: the folder once, each page once');
	pg_assert_same(array(0, 1), style_usage_test_counts($usage, 7), 'style 7: the second page');
}

function test_style_usage_subfolders_inherit()
{
	$usage = pg_style_usage(
		array(
			style_usage_test_folder(1, 0, 5, 6),
			style_usage_test_folder(2, 1, 0, 0),
			style_usage_test_folder(3, 2, 8, 0),
		),
		array(style_usage_test_page(2, 0, 0), style_usage_test_page(3, 0, 9))
	);

	pg_assert_same(array(2, 1), style_usage_test_counts($usage, 5), 'style 5: folders 1 and 2, the page in folder 2');
	pg_assert_same(array(3, 1), style_usage_test_counts($usage, 6), 'style 6: every folder\'s mobile, the page in folder 2');
	pg_assert_same(array(1, 1), style_usage_test_counts($usage, 8), 'style 8: folder 3 and its page');
	pg_assert_same(array(0, 1), style_usage_test_counts($usage, 9), 'style 9: the page\'s own mobile');
	pg_assert_same(false, isset($usage[0]), 'no style 0 entry');
}

function test_style_usage_loop_in_rows_ends()
{
	$usage = pg_style_usage(
		array(style_usage_test_folder(1, 0, 5, 0), style_usage_test_folder(2, 3, 6, 0), style_usage_test_folder(3, 2, 7, 0)),
		array()
	);

	pg_assert_same(array(1, 0), style_usage_test_counts($usage, 5), 'the reachable folder');
	pg_assert_same(false, isset($usage[6]), 'folders only in a loop are not reached');
}
