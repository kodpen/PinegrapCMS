<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for writing a new Workspace e-mail (mail_compose.php): a mailbox's
 * Signature as typed and where it goes in the mail, the form's request
 * turned into what ws_mail_send() takes, who is offered the form, the panel
 * action and the screen's wiring. The writes need a database and are
 * checked in their source.
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

function workspace_mail_compose_test_source($relative)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $relative);
}

function test_workspace_mail_signature_input()
{
	$typed = ws_mail_signature_input("\r\n  Ayşe Yılmaz  \r\nSupport team\t\r\n+90 212 000 00 00\x07\n\n");
	pg_assert_true($typed['ok'], 'a plain signature');
	pg_assert_same("  Ayşe Yılmaz\nSupport team\n+90 212 000 00 00", $typed['signature'], 'lines kept, ends and control characters dropped');
	pg_assert_same('Kodpen', ws_mail_signature_input("-- \nKodpen")['signature'], 'a typed separator is the mail\'s own');
	pg_assert_same('', ws_mail_signature_input(array('x'))['signature'], 'a list is nothing');
	pg_assert_true(ws_mail_signature_input(str_repeat('a', WS_MAIL_SIGNATURE_MAX))['ok'], 'at the limit');

	$long = ws_mail_signature_input(str_repeat('a', WS_MAIL_SIGNATURE_MAX + 1));
	pg_assert_true(!$long['ok'], 'over the limit');
	pg_assert_same(lang(array('string' => 'A signature is at most {var:1} characters.', 'vars' => WS_MAIL_SIGNATURE_MAX)), $long['error']);

	pg_assert_true(ws_mail_signature_input(implode("\n", array_fill(0, WS_MAIL_SIGNATURE_LINES, 'x')))['ok'], 'twelve lines');
	pg_assert_true(!ws_mail_signature_input(implode("\n", array_fill(0, WS_MAIL_SIGNATURE_LINES + 1, 'x')))['ok'], 'thirteen lines');
}

function test_workspace_mail_box_signature_setting()
{
	$base = array('name' => 'Support', 'address' => 'support@example.com', 'channel_id' => '4');
	pg_assert_same("Kodpen\nSupport", ws_mail_box_input(array_merge($base, array('signature' => "Kodpen\r\nSupport\r\n")))['values']['signature'], 'the form keeps it');
	pg_assert_same('', ws_mail_box_input($base)['values']['signature'], 'none by default');
	pg_assert_same(lang(array('string' => 'A signature is at most {var:1} characters.', 'vars' => WS_MAIL_SIGNATURE_MAX)),
		ws_mail_box_input(array_merge($base, array('signature' => str_repeat('a', WS_MAIL_SIGNATURE_MAX + 1))))['error'], 'a box without servers checks it too');

	pg_assert_same("Kodpen\nSupport", ws_mail_box_signature(array('settings' => json_encode(array('signature' => "Kodpen\nSupport")))), 'read from settings');
	pg_assert_same('', ws_mail_box_signature(array('settings' => '')), 'none kept');
	pg_assert_same('', ws_mail_box_signature(array('settings' => json_encode(array('signature' => array('x'))))), 'not text');

	$settings = workspace_mail_compose_test_source('includes/workspace/mail_settings.php');
	pg_assert_contains("'reads_bounces', 'signature') as \$key) {", $settings, 'saved into settings');
	pg_assert_contains('name="signature"', $settings, 'the field on the box form');
}

function test_workspace_mail_send_body_signature()
{
	pg_assert_same("Hello\n\n-- \nAyşe\nSupport", ws_mail_send_body("Hello\r\n", 'Ayşe', 'Support', ''), 'no Signature: the box\'s name');
	pg_assert_same("Hello\n\n-- \nAyşe\nKodpen Support\n+90 212", ws_mail_send_body('Hello', 'Ayşe', 'Support', "Kodpen Support\n+90 212"), 'the Signature in the name\'s place');
	pg_assert_contains("ws_mail_send_body((string) \$item['body_text'], (string) \$item['from_name'], (string) \$box['name'], ws_mail_box_signature(\$box))",
		workspace_mail_compose_test_source('includes/workspace/mail_send.php'), 'the mail is built with it');
	pg_assert_true(strpos(workspace_mail_compose_test_source('includes/workspace/mail_reply.php'), 'signature(') === false, 'the answer keeps its own signature');
}
