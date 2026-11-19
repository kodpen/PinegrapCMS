<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the delete branch of Edit User: the Site Log line names the
 * account that was deleted, as stored, not the text in the Username field.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The delete branch of edit_user.php, from the Delete button check to the
// save branch.
function edit_user_delete_log_test_slice()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/edit_user.php');
	$start = strpos($source, "if ((\$_POST['submit_delete'] ?? '') == 'Delete') {");
	pg_assert_true($start !== false, 'the delete branch is found');
	$end = strpos($source, '// else the user is being edited', $start);
	pg_assert_true($end !== false, 'the save branch follows it');

	return substr($source, $start, $end - $start);
}

function test_edit_user_delete_logs_the_stored_username()
{
	$slice = edit_user_delete_log_test_slice();

	pg_assert_same(false, strpos($slice, "\$_POST['username']"), 'the posted Username field is not what the log names');
	pg_assert_true((bool) preg_match('/SELECT user_role, user_username FROM user WHERE user_id = \?/', $slice), 'the stored username is read with the role');
	pg_assert_true((bool) preg_match("/'user \(\{var:1\}\) was deleted','vars'=>\\\$deleted_username\)/", $slice), 'the log line names the stored username');
}

function test_edit_user_delete_reads_the_name_before_deleting()
{
	$slice = edit_user_delete_log_test_slice();
	$read = strpos($slice, 'SELECT user_role, user_username');
	$delete = strpos($slice, 'pg_user_delete(');
	pg_assert_true($read !== false && $delete !== false, 'read and delete found');
	pg_assert_true($read < $delete, 'the name is read while the row still exists');
}
