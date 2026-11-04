<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests that renaming your own account in Edit User updates the session's
 * copy of the username at once, so the greeting, the account menu, the Site
 * Log and the developer lock (which looks the user up by that name) follow
 * the new name before the next sign-in.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The save branch of edit_user.php, from the user row save
// (pg_user_update()) to the redirect.
function edit_user_self_rename_test_save_slice()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/edit_user.php');
	$start = strpos($source, "pg_user_update(\$audit_user_id, \$_POST,");
	pg_assert_true($start !== false, 'the save calls pg_user_update()');
	$end = strpos($source, "/view_users.php');", $start);
	pg_assert_true($end !== false, 'the save branch redirects to the list');

	return substr($source, $start, $end - $start);
}

function test_edit_user_self_rename_refreshes_the_session_username()
{
	$slice = edit_user_self_rename_test_save_slice();
	pg_assert_true((bool) preg_match('/if\s*\(\$audit_user_id\s*===\s*\(int\)\s*USER_ID\)\s*\{\s*\$_SESSION\[\'sessionusername\'\]\s*=\s*\(string\)\s*\$username;/', $slice),
		'the session username follows the saved name when the edited user is the signed-in user');
}

function test_edit_user_self_rename_refresh_runs_after_the_update_and_before_the_log()
{
	$slice = edit_user_self_rename_test_save_slice();
	$query = strpos($slice, 'pg_user_update(');
	$refresh = strpos($slice, "\$_SESSION['sessionusername'] = ");
	$log = strpos($slice, "'user ({var:1}) was modified'");
	pg_assert_true($query !== false && $refresh !== false && $log !== false, 'query, refresh and log found');
	pg_assert_true($query < $refresh, 'the session is refreshed only after the row is saved');
	pg_assert_true($refresh < $log, 'the save is logged under the new name');
}
