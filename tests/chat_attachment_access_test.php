<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the panel chat attachment gate: a file a team chat message
 * carries opens only for a side of that conversation or a role-0
 * administrator (pg_chat_attachment_access_verdict() in
 * includes/authentication.php), and get_file.php asks it before the folder
 * and serves the file private, no-store.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_chat_attachment_access_verdict()
{
	$conversations = array(array('initiator_user_id' => '4', 'target_user_id' => '9'));

	pg_assert_true(pg_chat_attachment_access_verdict(4, 2, $conversations), 'the initiator');
	pg_assert_true(pg_chat_attachment_access_verdict(9, 1, $conversations), 'the target');
	pg_assert_true(pg_chat_attachment_access_verdict(7, 0, $conversations), 'an administrator');
	pg_assert_false(pg_chat_attachment_access_verdict(7, 1, $conversations), 'another designer');
	pg_assert_false(pg_chat_attachment_access_verdict(7, 2, $conversations), 'another manager');
	pg_assert_false(pg_chat_attachment_access_verdict(0, 0, $conversations), 'nobody signed in');
	pg_assert_true(pg_chat_attachment_access_verdict(7, 2, array_merge($conversations, array(array('initiator_user_id' => 7, 'target_user_id' => 4)))), 'a side of another conversation carrying the same file');
}

function test_get_file_gates_chat_attachments()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/get_file.php');
	$gate = strpos($source, 'pg_chat_attachment_access_verdict(');

	pg_assert_true($gate !== false, 'the gate is asked');
	pg_assert_true($gate < strpos($source, '$access_control_type = ($erp_document'), 'before the folder decides');
	pg_assert_contains("c.channel = 'backend'", $source, 'panel conversations only');
	pg_assert_contains("(\$erp_document || \$chat_attachment) ? 'private, no-store'", $source, 'kept by no cache');
}
