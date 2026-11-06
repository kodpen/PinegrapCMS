<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the image editor's door and panel chat attachments: a request
 * that names a file a team chat message carries is refused unless the user
 * is a side of that conversation or a role-0 administrator, before any
 * action reads its pixels or saves a copy of it in a folder that opens to
 * others.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_image_editor_request_files()
{
	pg_assert_same(array('ids' => array(5, 7, 9), 'names' => array('a.png')), pg_image_editor_request_files(array(
		'file_id' => '5', 'to' => 7, 'target' => '9', 'file_name' => 'a.png', 'folder_id' => 3, 'version_id' => 4)), 'every key naming a file');
	pg_assert_same(array('ids' => array(5), 'names' => array()), pg_image_editor_request_files(array(
		'file_id' => 5, 'to' => '5', 'file_name' => '')), 'one id once, no empty name');
	pg_assert_same(array('ids' => array(), 'names' => array()), pg_image_editor_request_files(array(
		'file_id' => array(5), 'to' => '-2', 'target' => 'x', 'file_name' => array('a.png'))), 'arrays and non-positive values name nothing');
	pg_assert_same(array('ids' => array(), 'names' => array()), pg_image_editor_request_files(null), 'no request');
}

function test_chat_attachment_files_refused()
{
	$conversations = array(
		12 => array(array('initiator_user_id' => '4', 'target_user_id' => '9')),
		13 => array(array('initiator_user_id' => '7', 'target_user_id' => '8')));

	pg_assert_false(pg_chat_attachment_files_refused(array(12 => $conversations[12]), 4, 2), 'a side of the conversation');
	pg_assert_true(pg_chat_attachment_files_refused($conversations, 9, 2), 'a side of one but not of the other');
	pg_assert_true(pg_chat_attachment_files_refused($conversations, 5, 1), 'another designer');
	pg_assert_false(pg_chat_attachment_files_refused($conversations, 5, 0), 'an administrator');
	pg_assert_false(pg_chat_attachment_files_refused(array(), 5, 2), 'no chat attachment named');
}

function test_image_editor_api_gates_chat_attachments()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/image_editor_api.php');
	$gate = strpos($source, 'pg_chat_attachment_files_refused(');

	pg_assert_true($gate !== false, 'the door asks the gate');
	pg_assert_true($gate < strpos($source, 'pg_image_editor_api($image_editor_body'), 'before any action runs');
	pg_assert_contains('pg_chat_attachment_conversations_by_file(', $source, 'with the conversations of the files the request names');
}
