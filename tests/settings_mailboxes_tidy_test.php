<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for Site Settings › Mailboxes as folded rows: the pane's rows and
 * their order, a box's row with its state pill, the accounts as connect
 * rows, Bring in an .eml file as the last row, and a mailbox's Remove (who
 * may, what goes, that its conversations stay). No database.
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

function settings_mailboxes_tidy_test_source($relative)
{
	return str_replace("\r\n", "\n", (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $relative));
}

function test_settings_mailboxes_remove_check()
{
	$box = array('id' => 4, 'name' => 'Support', 'transport' => 'imap');

	pg_assert_true(ws_mail_box_remove_check(array('settings' => true), $box)['ok'], 'the settings right removes a box');
	pg_assert_false(ws_mail_box_remove_check(array('settings' => false), $box)['ok'], 'without the right, nothing');
	pg_assert_false(ws_mail_box_remove_check(array('settings' => true), null)['ok'], 'a box that is gone');
	pg_assert_false(ws_mail_box_remove_check(array('settings' => true), array('id' => 5, 'name' => 'WhatsApp', 'transport' => 'whatsapp'))['ok'], 'a WhatsApp box is kept by its own card');
}

function test_settings_mailboxes_remove_keeps_the_conversations()
{
	$sql = implode("\n", ws_mail_box_remove_queries(4));

	// The box's row holds its settings: the sealed password, the rules and
	// the routes go with it.
	pg_assert_contains("DELETE FROM ws_mail_boxes WHERE id = '4'", $sql, 'the box and its settings go');
	pg_assert_contains("DELETE FROM ws_mail_blocks WHERE box_id = '4'", $sql, 'its own blocked senders go');

	foreach (array('ws_mail_conversations', 'ws_mail_items', 'ws_messages', 'ws_channels', 'ws_mail_reads', 'ws_mail_reminders') as $table) {
		pg_assert_false(strpos($sql, $table) !== false, $table . ' is never touched: the conversations stay where they are');
	}

	pg_assert_same(2, count(ws_mail_box_remove_queries('4 OR 1=1')), 'an id is a number');
	pg_assert_contains("id = '4'", implode('', ws_mail_box_remove_queries('4 OR 1=1')), 'nothing else in the id');
}

function test_settings_mailboxes_remove_password_and_rules_live_in_the_row()
{
	// The rules and the sealed password are kept in ws_mail_boxes.settings,
	// so the row's delete takes them: no other table holds either.
	$rules = settings_mailboxes_tidy_test_source('includes/workspace/mail_rules.php');
	pg_assert_false(strpos($rules, 'INSERT INTO ws_mail_rules') !== false, 'no table of rules');
	pg_assert_contains('settings', $rules, 'the rules are in the box\'s settings');
}

function test_settings_mailboxes_remove_confirm_says_what_happens()
{
	$said = ws_mail_box_remove_confirm(array('name' => 'Support'));

	foreach (array('Support', 'Reading stops', 'stored password is deleted', 'rules go', 'conversations stay where they are') as $words) {
		pg_assert_contains($words, $said, 'the confirm says: ' . $words);
	}
}

function test_settings_mailboxes_remove_wired_into_the_save()
{
	$save = settings_mailboxes_tidy_test_source('includes/settings/mailboxes.save.php');

	pg_assert_contains("post_value('ws_mail_remove')", $save, 'Remove is posted as ws_mail_remove');
	pg_assert_true(strpos($save, 'ws_mail_box_remove_check(') < strpos($save, 'ws_mail_box_remove_write('), 'checked before it is written');
	pg_assert_true(strpos($save, 'ws_mail_box_save_write(') < strpos($save, "post_value('ws_mail_remove')"), 'after the drawer\'s save, as the other buttons');
}
