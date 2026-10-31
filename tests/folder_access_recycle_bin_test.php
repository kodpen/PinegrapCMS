<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the folder access rule shared by pages, files and sitemap.xml
 * (includes/authentication.php): a folder inside the Recycle Bin is private
 * whatever access type it carries itself, and the readers that list or serve
 * live content leave the bin's folders out.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Root 1; bin 9 (private) under it; 10 is a public folder moved into the
// bin, 11 a member folder below it, 12 inherits; 2 is a live public folder,
// 3 a live folder that inherits.
function folder_access_recycle_bin_rows()
{
	$rows = array();
	foreach (array(
		array(1, 0, ''),
		array(2, 1, 'public'),
		array(3, 2, ''),
		array(4, 1, 'membership'),
		array(9, 1, 'private'),
		array(10, 9, 'public'),
		array(11, 10, 'membership'),
		array(12, 11, ''),
	) as $r) {
		$rows[$r[0]] = array('folder_id' => $r[0], 'folder_parent' => $r[1], 'folder_access_control_type' => $r[2]);
	}
	return $rows;
}

// Outside the bin the first type set up the chain wins, the root is public.
function test_folder_access_resolve_outside_the_bin()
{
	$rows = folder_access_recycle_bin_rows();

	pg_assert_same('public', pg_folder_access_resolve($rows, 1, 9), 'root');
	pg_assert_same('public', pg_folder_access_resolve($rows, 2, 9), 'own type');
	pg_assert_same('public', pg_folder_access_resolve($rows, 3, 9), 'inherited');
	pg_assert_same('membership', pg_folder_access_resolve($rows, 4, 9), 'own non-public type');
	pg_assert_same('public', pg_folder_access_resolve($rows, 404, 9), 'missing row');
	pg_assert_same('public', pg_folder_access_resolve($rows, 10, 0), 'no bin: own type');
}

// Anything at or below the bin is private, whatever its own type says.
function test_folder_access_resolve_inside_the_bin()
{
	$rows = folder_access_recycle_bin_rows();

	pg_assert_same('private', pg_folder_access_resolve($rows, 9, 9), 'the bin');
	pg_assert_same('private', pg_folder_access_resolve($rows, 10, 9), 'public folder in the bin');
	pg_assert_same('private', pg_folder_access_resolve($rows, 11, 9), 'member folder below it');
	pg_assert_same('private', pg_folder_access_resolve($rows, 12, 9), 'inheriting folder below it');

	pg_assert_true(pg_folder_in_bin_chain($rows, 12, 9), 'deep folder is binned');
	pg_assert_true(pg_folder_in_bin_chain($rows, 9, 9), 'bin is binned');
	pg_assert_false(pg_folder_in_bin_chain($rows, 3, 9), 'live folder');
	pg_assert_false(pg_folder_in_bin_chain($rows, 12, 0), 'no bin');

	// A loop in folder_parent ends the walk.
	$rows[20] = array('folder_id' => 20, 'folder_parent' => 21, 'folder_access_control_type' => '');
	$rows[21] = array('folder_id' => 21, 'folder_parent' => 20, 'folder_access_control_type' => '');
	pg_assert_false(pg_folder_in_bin_chain($rows, 20, 9), 'loop');
	pg_assert_same('public', pg_folder_access_resolve($rows, 20, 9), 'loop falls back to public');
}

// The page, the Pages list and the SEO summary treat a binned folder as
// archived; get_file.php and sitemap.xml read the access type above.
function test_binned_folders_left_out_of_live_readers()
{
	$page = (string) file_get_contents(PG_FUNCTIONS_DIR . '/get_page.php');
	$pages = (string) file_get_contents(PG_FUNCTIONS_DIR . '/view_pages.php');
	$auth = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/authentication.php');

	pg_assert_contains('pg_folder_binned($folder_id)', $page, 'page answers 410 in the bin');
	pg_assert_same(2, substr_count($pages, 'pg_pages_bin_sql('), 'list and SEO summary');
	pg_assert_contains('pg_folder_access_resolve($tree[\'rows\'], $folder_id, $tree[\'bin\'])', $auth, 'access type reads the bin');
}

// The SQL condition lists the bin's folders as integers; no bin, no condition.
function test_pages_bin_sql()
{
	pg_assert_same('', pg_pages_bin_sql(array(), false), 'no bin, live');
	pg_assert_same('', pg_pages_bin_sql(array(), true), 'no bin, archive');
	pg_assert_same(' AND (page.page_folder NOT IN (9, 10))', pg_pages_bin_sql(array('9', 10), false), 'live list leaves them out');
	pg_assert_same(' OR (page.page_folder IN (9, 10))', pg_pages_bin_sql(array(9, '10'), true), 'archive list takes them in');
}
