<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the daily recycle bin purge (pg_recycle_purge_if_due()) and the
 * rights the permanent delete (pg_recycle_hard_delete_item()) reads: the
 * purge acts for the software, whoever's explorer visit started it.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/view_folder_and_files_f.php');

// The purge user keeps the visitor's id and takes the administrator role, so
// a folder-rights user's or a manager's visit purges everything that is due.
function test_recycle_bin_purge_user_is_administrator()
{
	$visitor = array('id' => 42, 'role' => 3, 'delete_pages' => false, 'manage_ecommerce' => false);
	$purge_user = pg_recycle_purge_user($visitor);

	pg_assert_same(0, $purge_user['role'], 'role');
	pg_assert_same(42, $purge_user['id'], 'id kept');
	pg_assert_same(3, $visitor['role'], 'visitor untouched');
	pg_assert_same(0, pg_recycle_purge_user(array('id' => 7, 'role' => 2))['role'], 'manager');
	pg_assert_same(0, pg_recycle_purge_user(array())['id'], 'no id');
}

// The purge hands that user to the delete, and the delete decides the folder
// right from the user it was given, not from the session constants.
function test_recycle_bin_purge_runs_as_purge_user()
{
	$names = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/explorer/recycle_names.php');
	pg_assert_contains('pg_recycle_hard_delete_item($entry[\'item_type\'], $entry[\'item_id\'], $purge_user)', $names, 'purge passes the purge user');

	$bin = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/explorer/recycle_bin.php');
	$start = strpos($bin, 'function pg_recycle_hard_delete_item(');
	pg_assert_true($start !== false, 'delete found');
	$body = substr($bin, $start);

	pg_assert_false(strpos($body, 'check_edit_access(') !== false, 'no session-based folder check');
	pg_assert_contains("pg_folder_edit_access(\$file['folder'], \$user['id'], \$user['role'])", $body, 'file right from the user');
	pg_assert_contains("pg_folder_edit_access(\$item_id, \$user['id'], \$user['role'])", $body, 'folder right from the user');
}

// A binned short link the user may not see is refused, not taken for gone:
// before, its bin record was dropped and the link stayed binned for ever.
function test_recycle_bin_purge_short_link_refused_not_forgotten()
{
	$bin = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/explorer/recycle_bin.php');
	$start = strpos($bin, "case 'short_link':");
	pg_assert_true($start !== false, 'case found');
	$case = substr($bin, $start, strpos($bin, "case 'style':") - $start);

	pg_assert_contains('pg_short_link_visible($user) == false', $case, 'visibility checked');
	pg_assert_contains("lang('Access denied')", $case, 'refused');
}
