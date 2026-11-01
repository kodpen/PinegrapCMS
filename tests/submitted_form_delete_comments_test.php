<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Deleting a submitted form also deletes its comments, their attachments and
 * notifications, its watchers and its comment counters
 * (pg_comment_delete_record_thread() in includes/fn/comments.php), on every
 * path that deletes a form record.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Every path that deletes a forms row clears the record's thread right after
// its signature and view rows.
function test_submitted_form_delete_comments_every_path()
{
	$paths = array(
		'edit_submitted_form.php' => "pg_comment_delete_record_thread(\$_POST['id'] ?? 0);",
		'delete_submitted_forms.php' => 'pg_comment_delete_record_thread($form_id);',
		'view_submitted_forms.php' => "pg_comment_delete_record_thread(\$form['id']);",
	);

	foreach ($paths as $file => $call) {
		$source = file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
		$delete = strpos($source, 'DELETE FROM forms WHERE id = ?');

		pg_assert_true($delete !== false, $file . ' deletes a form');
		pg_assert_same(1, substr_count($source, $call), $file . ' clears the thread');
		pg_assert_true(strpos($source, $call) > $delete, $file . ' clears it after the form row');
	}
}

// The helper covers each table that names a record by item_type/item_id or
// submitted_form_id, and keeps a file another comment still uses.
function test_submitted_form_delete_comments_tables()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/comments.php');
	$start = strpos($source, 'function pg_comment_delete_record_thread(');
	pg_assert_true($start !== false, 'helper exists');

	$body = substr($source, $start, strpos($source, "\n}\n", $start) - $start);

	foreach (array('DELETE FROM notifications WHERE comment_id IN', 'DELETE FROM comments WHERE', 'DELETE FROM watchers WHERE', 'DELETE FROM allow_new_comments_for_items WHERE', 'DELETE FROM submitted_form_info WHERE submitted_form_id = ?', 'DELETE FROM files WHERE id = ?') as $statement) {
		pg_assert_contains($statement, $body, $statement);
	}

	pg_assert_contains("NOT ((item_type = 'submitted_form') AND (item_id = ?))", $body, 'shared attachment check');
	pg_assert_true(strpos($body, 'DELETE FROM notifications') < strpos($body, 'DELETE FROM comments WHERE'), 'notifications are found before their comments go');
}

// Without a connection, or for an id that names no record, nothing is run.
function test_submitted_form_delete_comments_without_db()
{
	pg_assert_same(array(), pg_comment_delete_record_thread(0), 'no record id');
	pg_assert_same(array(), pg_comment_delete_record_thread('x'), 'not an id');
	pg_assert_same(array(), pg_comment_delete_record_thread(12), 'no connection');
}
