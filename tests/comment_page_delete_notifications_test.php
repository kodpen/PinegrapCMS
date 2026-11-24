<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests that every path deleting a page deletes the panel notifications of
 * the page's comments before the comments themselves
 * (pg_comment_delete_page_notifications(), includes/fn/comments.php): a
 * notification left behind showed an empty label in the bell and opened
 * edit_comment.php with no id.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Edit Page, the Pages list and the Recycle Bin each call the helper, and
// before their DELETE of the page's comments: once the comments are gone the
// subquery finds none of their ids.
function test_comment_page_delete_notifications_every_path()
{
	$paths = array(
		'edit_page.php' => 'DELETE FROM comments WHERE page_id = ?',
		'edit_pages.php' => 'DELETE FROM comments WHERE page_id = ?',
		'includes/explorer/recycle_bin.php' => "DELETE FROM comments WHERE page_id = '",
	);

	foreach ($paths as $file => $delete) {
		$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
		$call = strpos($source, 'pg_comment_delete_page_notifications(');
		$comments = strpos($source, $delete);

		pg_assert_true($call !== false, $file . ' deletes the notifications');
		pg_assert_true($comments !== false, $file . ' deletes the comments');
		pg_assert_true(($call !== false) && ($comments !== false) && ($call < $comments), $file . ' deletes the notifications first');
	}
}

// No page id, no query.
function test_comment_page_delete_notifications_needs_an_id()
{
	pg_assert_same(false, pg_comment_delete_page_notifications(0), 'zero');
	pg_assert_same(false, pg_comment_delete_page_notifications(''), 'empty');
	pg_assert_same(false, pg_comment_delete_page_notifications(array(5)), 'array');
}
