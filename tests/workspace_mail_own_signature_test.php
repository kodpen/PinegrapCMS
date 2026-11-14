<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a person's own e-mail signature (mail_signature.php): where it
 * stands in a mail, how it is kept, the panel actions, the toolbar button
 * and the forms' previews. The writes need a database and are checked in
 * their source.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/mail_parse.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php');

function workspace_mail_own_signature_test_source($relative)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $relative);
}

function test_workspace_mail_writer_lines()
{
	pg_assert_same('Ayşe Yılmaz', ws_mail_writer_lines('Ayşe Yılmaz', ''), 'no own signature: the name');
	pg_assert_same('Ayşe Yılmaz', ws_mail_writer_lines('Ayşe Yılmaz', null), 'none kept: the name');
	pg_assert_same('Ayşe Yılmaz', ws_mail_writer_lines('Ayşe Yılmaz', " \n \n"), 'only blanks: the name');
	pg_assert_same("Ayşe\nSales, Kodpen", ws_mail_writer_lines('Ayşe Yılmaz', "\nAyşe\nSales, Kodpen\n"), 'the own signature in the name\'s place');
	pg_assert_same('', ws_mail_writer_lines('', ''), 'nobody named');
}

function test_workspace_mail_own_signature_in_the_mail()
{
	pg_assert_same("Hello\n\n-- \nAyşe\nSales\nKodpen Support\n+90 212",
		ws_mail_send_body('Hello', ws_mail_writer_lines('Ayşe Yılmaz', "Ayşe\nSales"), 'Support', "Kodpen Support\n+90 212"),
		'own lines, then the box\'s Signature');
	pg_assert_same("Hello\n\n-- \nAyşe\nSales\nSupport",
		ws_mail_send_body('Hello', ws_mail_writer_lines('Ayşe Yılmaz', "Ayşe\nSales"), 'Support', ''),
		'own lines, then the box\'s name');

	$box = array('name' => 'Support', 'settings' => json_encode(array('signature' => 'Kodpen')));
	pg_assert_same("Fine.\n\n-- \nA. Y.\nKodpen", ws_mail_answer_body('Fine.', ws_mail_writer_lines('Ayşe', 'A. Y.'), $box, null), 'an answer the same');
	pg_assert_contains("A. Y.<br>\nKodpen", ws_mail_answer_html('<p>Fine.</p>', ws_mail_writer_lines('Ayşe', 'A. Y.'), $box, null), 'the HTML mail the same, escaped');
	pg_assert_contains('&lt;b&gt;', ws_mail_answer_html('<p>x</p>', ws_mail_writer_lines('Ayşe', '<b>'), $box, null), 'never markup');
}

function test_workspace_mail_own_signature_wiring()
{
	$send = workspace_mail_own_signature_test_source('includes/workspace/mail_send.php');
	pg_assert_contains("\$writer = ws_mail_writer_lines((string) \$item['from_name'], ws_mail_own_signature((int) (\$item['user_id'] ?? 0)));", $send, 'read when the mail leaves, by its writer');
	pg_assert_contains("ws_mail_answer_body((string) \$item['body_text'], \$writer, \$box,", $send, 'the text');
	pg_assert_contains("ws_mail_answer_html((string) \$item['body_html'], \$writer, \$box,", $send, 'the HTML');

	$reply = workspace_mail_own_signature_test_source('includes/workspace/mail_reply.php');
	pg_assert_contains("ws_mail_answer_body(trim((string) \$text), ws_mail_writer_lines(\$writer, ws_mail_own_signature((int) \$viewer['id'])), \$box, \$answered);", $reply, 'an answer sent at once');

	$own = workspace_mail_own_signature_test_source('includes/workspace/mail_signature.php');
	pg_assert_contains("pg_schema_has('ws_profiles', 'mail_signature')", $own, 'probed before it is read');
	pg_assert_contains('ON DUPLICATE KEY UPDATE mail_signature = VALUES(mail_signature)', $own, 'kept as notes_pinned is');
	pg_assert_contains('ws_mail_signature_input(', $own, 'the box Signature\'s rules');

	$actions = workspace_mail_own_signature_test_source('includes/workspace/actions.php');
	pg_assert_contains("case 'ws_mail_my_settings':", $actions, 'read');
	pg_assert_contains("case 'ws_mail_my_settings_save':", $actions, 'save');
	pg_assert_contains("'own_signature' => ws_mail_own_signature((int) \$viewer['id'])", $actions, 'the forms get it');

	$panel = workspace_mail_own_signature_test_source('includes/panel/actions.php');
	pg_assert_contains("'ws_mail_my_settings'", $panel, 'a panel action');
	pg_assert_contains("'ws_mail_my_settings_save'", $panel, 'a panel action');

	pg_assert_contains('data-ws-mail-my-settings', workspace_mail_own_signature_test_source('includes/workspace/mail_list.php'), 'on the toolbar');
	pg_assert_contains("upgrade_2026_11_0_ws_mail_own_signature();   // 11.0.203", workspace_mail_own_signature_test_source('includes/migrations/2026.11.0.php'), 'its migration step');

	$js = workspace_mail_own_signature_test_source('assets/js/workspace_mail.js');
	pg_assert_contains("h.api('ws_mail_my_settings'", $js, 'the dialog reads it');
	pg_assert_contains("'ws_mail_my_settings_save'", $js, 'and saves it');
	pg_assert_contains('function signatureLines(', $js, 'one preview for both forms');
}
