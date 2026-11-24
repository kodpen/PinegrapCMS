<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The file tag's search in a Workspace writing box (ws_ref_search(), type
 * file) leaves out every file under the Workspace folder: a channel's
 * attachment or a note's file belongs to the conversation or note it was
 * posted in, and staff, who may edit every folder, were offered the files
 * of private channels they are not in by name.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/library.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/refs.php');

function test_workspace_ref_file_search_leaves_the_workspace_branch_out()
{
	if (!function_exists('ws_ref_file_search_where')) {
		pg_assert_true(false, 'ws_ref_file_search_where() is missing');
		return;
	}

	// 1 Files, 5 Workspace, 6 a channel's folder, 12 a discussion's notes, 9 Pictures.
	$parents = array(1 => 0, 5 => 1, 6 => 5, 12 => 6, 9 => 1);

	pg_assert_same("folder > 0 AND folder NOT IN (5,6,12)", ws_ref_file_search_where($parents, 5), 'the Workspace folder and everything below it are left out');
	pg_assert_same('folder > 0', ws_ref_file_search_where($parents, 0), 'no Workspace folder yet: every foldered file');
	pg_assert_same('folder > 0', ws_ref_file_search_where($parents, 77), 'a stored Workspace folder that is gone leaves nothing out');
}

function test_workspace_ref_file_search_uses_the_rule()
{
	$source = (string) file_get_contents(dirname(__DIR__) . '/pinegrap/includes/workspace/refs.php');
	$start = strpos($source, "        case 'file':\n            // Files");

	pg_assert_true($start !== false, 'ws_ref_search() has its file case');
	pg_assert_true(strpos(substr($source, (int) $start, 600), 'ws_ref_file_search_where(') !== false, 'the file case asks ws_ref_file_search_where()');
}
