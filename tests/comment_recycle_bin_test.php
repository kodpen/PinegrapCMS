<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for comments on pages in the Recycle Bin (includes/fn/comments.php):
 * the SQL fragment that leaves them out of the moderation queue, its count and
 * the greeting (pg_comment_live_page_sql()), and the page name shown for them
 * without the bin's pgbin_<page_id>_ prefix (pg_comment_page_label()). Then
 * the places that use them: the dashboard widget, the greeting, the Workspace
 * counter, and the Comments list and edit screens.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function comment_recycle_bin_test_source($path)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $path);
}

// No bin folder adds nothing; ids are cast, kept above 0, de-duplicated and
// sorted, and a comment without a page (NULL folder) stays in.
function test_comment_recycle_bin_live_page_sql()
{
	pg_assert_same('', pg_comment_live_page_sql(array()));
	pg_assert_same('', pg_comment_live_page_sql(null));
	pg_assert_same('', pg_comment_live_page_sql('5'));
	pg_assert_same('', pg_comment_live_page_sql(array(0, -3, 'x')));
	pg_assert_same(
		' AND (page.page_folder IS NULL OR page.page_folder NOT IN (5,7))',
		pg_comment_live_page_sql(array(7, '5', 0, -2, 'x', 5))
	);
}

// Only the prefix the bin gave this page is taken off, and only when a name
// follows it.
function test_comment_recycle_bin_page_label()
{
	pg_assert_same('rehber-2', pg_comment_page_label('pgbin_1380_rehber-2', 1380));
	pg_assert_same('rehber-2', pg_comment_page_label('pgbin_1380_rehber-2', '1380'));
	pg_assert_same('pgbin_1380_rehber-2', pg_comment_page_label('pgbin_1380_rehber-2', 99));
	pg_assert_same('pgbin_5_', pg_comment_page_label('pgbin_5_', 5));
	pg_assert_same('about-us', pg_comment_page_label('about-us', 5));
	pg_assert_same('', pg_comment_page_label(array(), 1));
	pg_assert_same('', pg_comment_page_label(null, 1));
}

// The queue, its count, the greeting and the Workspace counter all leave out
// comments on binned pages, joining page for its folder.
function test_comment_recycle_bin_counts_use_fragment()
{
	foreach (array('includes/dashboard/widgets/widget_25.php', 'includes/dashboard/brief.php', 'includes/workspace/scheduled.php') as $file) {
		pg_assert_contains('pg_comment_live_page_sql(pg_recycle_bin_folder_ids())', comment_recycle_bin_test_source($file), $file);
	}

	pg_assert_contains(
		"SELECT COUNT(*) FROM comments LEFT JOIN page ON comments.page_id = page.page_id WHERE comments.published = '0'",
		comment_recycle_bin_test_source('includes/dashboard/brief.php')
	);
	pg_assert_contains(
		"SELECT COUNT(*) FROM comments LEFT JOIN page ON comments.page_id = page.page_id WHERE comments.published = '0'",
		comment_recycle_bin_test_source('includes/workspace/scheduled.php')
	);
}

// The Comments list and edit screens mark a binned page's comment and give
// it no link; the edit screen shows the page's own name.
function test_comment_recycle_bin_screens_mark_comment()
{
	foreach (array('view_comments.php', 'edit_comment.php') as $file) {
		$source = comment_recycle_bin_test_source($file);
		pg_assert_contains('pg_recycle_bin_folder_ids()', $source, $file);
		pg_assert_contains("lang('In the Recycle Bin')", $source, $file);
	}

	pg_assert_contains('pg_comment_page_label(get_page_name($page_id), $page_id)', comment_recycle_bin_test_source('edit_comment.php'));
}
