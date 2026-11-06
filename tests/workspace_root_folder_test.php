<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Workspace folder at the top of the file manager
 * (includes/workspace/files.php): a site's own top-level folder that happens
 * to be called Workspace is taken over only when it holds nothing of the
 * site's, so its files are never made private or closed as conversation
 * files.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/files.php');

// An empty folder, or one holding only the Workspace's own channel and notes
// folders, is taken over.
function test_ws_root_folder_reusable_when_it_holds_nothing_of_the_site()
{
	pg_assert_true(ws_root_folder_reusable(0, 0), 'an empty folder');
}

// A folder with the site's files directly in it, or with folders of the
// site's below it, is left alone: a new folder is made beside it.
function test_ws_root_folder_not_reusable_with_site_content()
{
	pg_assert_false(ws_root_folder_reusable(3, 0), 'files in it');
	pg_assert_false(ws_root_folder_reusable(0, 1), 'a folder of the site below it');
	pg_assert_false(ws_root_folder_reusable(1, 2), 'both');
}

// The take-over query asks the rule above before it flips a folder private.
function test_ws_root_folder_id_asks_the_rule()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/files.php');
	$from = strpos($source, 'function ws_root_folder_id(');
	$body = substr($source, $from, strpos($source, "\n}\n", $from) - $from);

	pg_assert_contains('ws_root_folder_reusable(', $body, 'ws_root_folder_id() asks ws_root_folder_reusable()');
}
