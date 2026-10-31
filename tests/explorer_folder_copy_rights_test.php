<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the page creation right in the file manager's copies: a folder
 * copy and a page paste skip each page a user without 'Create pages' may not
 * copy, with an 'Access denied for …' line, and never reach duplicate_page(),
 * whose own refusal is an HTML page that would end the JSON answer.
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

// Designers and managers always copy pages; a user (role 3) only with the
// 'Create pages' right.
function test_explorer_can_copy_pages_follows_the_create_pages_right()
{
	pg_assert_true(pg_explorer_can_copy_pages(array('role' => 0, 'create_pages' => 0)), 'administrator');
	pg_assert_true(pg_explorer_can_copy_pages(array('role' => '1', 'create_pages' => '0')), 'designer');
	pg_assert_true(pg_explorer_can_copy_pages(array('role' => 2)), 'manager without the column');
	pg_assert_true(pg_explorer_can_copy_pages(array('role' => '3', 'create_pages' => '1')), 'user with the right');
	pg_assert_false(pg_explorer_can_copy_pages(array('role' => '3', 'create_pages' => '0')), 'user without the right');
	pg_assert_false(pg_explorer_can_copy_pages(array('role' => 3, 'create_pages' => null)), 'user with no value');
	pg_assert_false(pg_explorer_can_copy_pages(array('role' => 3)), 'user without the column');
	pg_assert_false(pg_explorer_can_copy_pages(array()), 'no role at all');
}

// The folder copy checks the right before the pages loop and skips each page
// with its name; files and subfolders still come across.
function test_explorer_folder_copy_skips_pages_without_the_right()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/view_folder_and_files_f.php');
	$start = strpos($source, 'function pg_explorer_copy_folder(');
	$end = strpos($source, "\n}\n", $start);
	$body = substr($source, $start, $end - $start);

	$check = strpos($body, 'pg_explorer_can_copy_pages($user)');
	$call = strpos($body, 'duplicate_page(array(');
	$files = strpos($body, '// Files.');

	pg_assert_true($check !== false, 'folder copy checks the right');
	pg_assert_true(($check !== false) && ($call !== false) && ($check < $call), 'checked before duplicate_page()');
	pg_assert_true(($files !== false) && ($call < $files), 'files still follow the pages');
	pg_assert_contains("'Access denied for {var:1}.', 'vars' => \$page['name']", $body, 'skipped page named');
}

// A page paste checks the right and the source folder before duplicate_page().
function test_explorer_page_paste_checks_the_right_first()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/explorer/handle_files.php');
	$start = strpos($source, "case 'page':");
	$body = substr($source, $start, strpos($source, 'respond(', $start) - $start);

	$check = strpos($body, 'pg_explorer_can_copy_pages($user)');
	$access = strpos($body, "check_edit_access(\$page['folder'])");
	$call = strpos($body, 'duplicate_page(array(');

	pg_assert_true($check !== false, 'paste checks the right');
	pg_assert_true($access !== false, 'paste checks the source folder');
	pg_assert_true(($call !== false) && ($check < $call) && ($access < $call), 'both before duplicate_page()');
	pg_assert_contains("'Access denied for {var:1}.', 'vars' => \$page['name']", $body, 'refused page named');
}
