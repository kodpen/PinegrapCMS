<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the sender of a message listed outside its channel (pinned bar,
 * Ctrl+K palette, Home): ws_message_row_sender() names a person, an
 * application, a guest and the sender of a mail (an incoming mail by the
 * name or address it carries, an answer the team sent by its writer), and
 * the three places read their senders once per list.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php');

function workspace_mail_sender_name_test_people()
{
	return array(
		4 => array('id' => 4, 'name' => 'Deniz Kaya', 'title' => 'Sales'),
		9 => array('id' => 9, 'name' => 'Ece Demir', 'title' => 'Support'),
	);
}

function test_workspace_message_row_sender_user_mail_and_unknown()
{
	$people = workspace_mail_sender_name_test_people();
	$mail = array(
		21 => array('id' => 21, 'direction' => 'in', 'user_id' => 0, 'from_email' => 'ayse@example.com', 'from_name' => 'Ayşe Yılmaz'),
		22 => array('id' => 22, 'direction' => 'in', 'user_id' => 0, 'from_email' => 'bob@example.com', 'from_name' => ''),
		23 => array('id' => 23, 'direction' => 'out', 'user_id' => 9, 'from_email' => 'help@example.com', 'from_name' => 'Help'),
	);

	pg_assert_same('Deniz Kaya', ws_message_row_sender(array('sender_kind' => 'user', 'sender_id' => 4), $people, $mail)['name'], 'a person');
	pg_assert_same(null, ws_message_row_sender(array('sender_kind' => 'user', 'sender_id' => 5), $people, $mail), 'a person not read');

	$in = ws_message_row_sender(array('sender_kind' => 'mail', 'sender_id' => 21), $people, $mail);

	pg_assert_same('Ayşe Yılmaz', $in['name'], 'an incoming mail by the name it carries');
	pg_assert_same('ayse@example.com', $in['title'], 'with its address');
	pg_assert_same('bob@example.com', ws_message_row_sender(array('sender_kind' => 'mail', 'sender_id' => 22), $people, $mail)['name'], 'the address when the mail has no name');

	$out = ws_message_row_sender(array('sender_kind' => 'mail', 'sender_id' => 23), $people, $mail);

	pg_assert_same('Ece Demir', $out['name'], 'an answer under its writer, read with the page');
	pg_assert_same('help@example.com', $out['title'], 'from the mailbox address');
	pg_assert_true(!empty($out['mail']), 'marked as mail');

	pg_assert_same(lang('E-mail'), ws_message_row_sender(array('sender_kind' => 'mail', 'sender_id' => 99), $people, $mail)['name'], 'a mail row gone');
	pg_assert_same(null, ws_message_row_sender(array('sender_kind' => 'system', 'sender_id' => 0), $people, $mail), 'the system has no sender');

	pg_assert_same('Ayşe Yılmaz', ws_palette_sender_name(array('sender_kind' => 'mail', 'sender_id' => 21), $people, $mail), 'Ctrl+K names the mail sender');
	pg_assert_same('', ws_palette_sender_name(array('sender_kind' => 'system', 'sender_id' => 0), $people, $mail), 'Ctrl+K names nobody for the system');
}

function test_workspace_message_row_sender_app_and_guest_branches()
{
	// Both read the database, so their routing is checked in the source.
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/messages.php');
	$start = strpos($source, 'function ws_message_row_sender(');
	$body = substr($source, $start, strpos($source, "\n}\n", $start) - $start);

	pg_assert_contains("case 'app':\n            return ws_app_sender(\$id);", $body, 'an application under its own name');
	pg_assert_contains("case 'guest':\n            return function_exists('ws_guest_sender') ? ws_guest_sender(\$id) : null;", $body, 'a guest of a room');
	pg_assert_contains("ws_mail_sender(\$mail[\$id] ?? null, \$people)", $body, 'a mail with the page\'s people');
}

function test_workspace_pinned_palette_and_home_read_senders_once()
{
	foreach (array('pins.php', 'palette.php', 'home.php', 'channel_board.php') as $file) {
		$source = file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/' . $file);

		pg_assert_true(strpos($source, 'ws_message_rows_senders(') !== false, $file . ' reads the senders of its list at once');
		pg_assert_true(strpos($source, "sender_kind'] === 'guest'") === false, $file . ' keeps no sender branches of its own');
		pg_assert_true(strpos($source, 'ws_mail_sender(') === false, $file . ' names mail only through ws_message_row_sender()');
	}

	$source = file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/messages.php');
	$start = strpos($source, 'function ws_message_rows_senders(');
	$body = substr($source, $start, strpos($source, "\n}\n", $start) - $start);

	pg_assert_same(1, substr_count($body, 'ws_mail_items_map('), 'one mail query for the list');
	pg_assert_same(1, substr_count($body, 'ws_people('), 'one people query for the list, writers included');
}
