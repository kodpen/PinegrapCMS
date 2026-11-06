<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the file manager's content readers: the Office preview and the
 * file window's text hand out a file's contents only where get_file.php
 * would serve the file. A submitted-form or comment attachment needs edit
 * rights on its folder, and a team chat attachment opens only for a side of
 * its conversation or a role-0 administrator, whatever the folder allows
 * (pg_file_panel_content_verdict() in includes/fn/files.php).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_file_panel_content_verdict()
{
	$chat = array(array('initiator_user_id' => '4', 'target_user_id' => '9'));

	pg_assert_true(pg_file_panel_content_verdict(0, false, 7, 3, array()), 'an ordinary file in a folder the user may view');
	pg_assert_false(pg_file_panel_content_verdict(1, false, 7, 3, array()), 'a form attachment, view rights only');
	pg_assert_false(pg_file_panel_content_verdict('1', false, 7, 3, array()), 'a form attachment flag read from the row');
	pg_assert_true(pg_file_panel_content_verdict(1, true, 7, 3, array()), 'a form attachment, edit rights on its folder');
	pg_assert_true(pg_file_panel_content_verdict(1, true, 7, 2, array()), 'a form attachment, a manager');
	pg_assert_false(pg_file_panel_content_verdict(0, true, 7, 2, $chat), 'a chat attachment, a manager outside the conversation');
	pg_assert_false(pg_file_panel_content_verdict(0, true, 7, 1, $chat), 'a chat attachment, a designer outside the conversation');
	pg_assert_true(pg_file_panel_content_verdict(0, false, 9, 3, $chat), 'a chat attachment, a side of the conversation');
	pg_assert_true(pg_file_panel_content_verdict(0, true, 7, 0, $chat), 'a chat attachment, an administrator');
	pg_assert_false(pg_file_panel_content_verdict(0, true, 0, 0, $chat), 'a chat attachment, nobody signed in');
}

function test_file_manager_content_readers_ask_the_verdict()
{
	$media = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/explorer/handle_files_media.php');
	$office = substr($media, strpos($media, 'function pg_explorer_handle_office_preview('));
	$office = substr($office, 0, strpos($office, "\nfunction "));

	pg_assert_contains('pg_file_panel_content_verdict(', $office, 'the Office preview asks it');
	pg_assert_true(strpos($office, 'pg_file_panel_content_verdict(') < strpos($office, 'pg_office_read('), 'before the file is read');

	$window = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/explorer/handle_file_window.php');
	$get = substr($window, strpos($window, 'function pg_explorer_handle_file_get('));
	$get = substr($get, 0, strpos($get, "\nfunction "));

	pg_assert_contains('pg_file_panel_content_verdict(', $get, 'the file window asks it');
	pg_assert_true(strpos($get, 'pg_file_panel_content_verdict(') < strpos($get, 'file_get_contents('), 'before the text is read');

	$files = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/files.php');
	pg_assert_contains("c.channel = 'backend'", $files, 'panel conversations only, as get_file.php');
}
