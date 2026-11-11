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

function workspace_mail_compose_test_entry($box_id, $own, $channels)
{
	return array(
		'box'      => array('id' => $box_id, 'name' => 'Box ' . $box_id, 'address' => 'b' . $box_id . '@example.com', 'channel_id' => $own,
			'settings' => json_encode(array('signature' => 'Sig ' . $box_id))),
		'route'    => array('ok' => true),
		'channels' => array_map(function ($id) {
			return array('id' => $id, 'name' => 'c' . $id);
		}, $channels),
	);
}

function test_workspace_mail_compose_boxes_and_channel()
{
	$entries = array(workspace_mail_compose_test_entry(3, 12, array(20, 12)), workspace_mail_compose_test_entry(5, 30, array(30)));
	$boxes = ws_mail_compose_boxes($entries);

	pg_assert_same(2, count($boxes), 'one entry a box');
	pg_assert_same(array('id' => 12, 'name' => 'c12'), $boxes[0]['channels'][0], 'the box\'s own channel first');
	pg_assert_same(array('id' => 20, 'name' => 'c20'), $boxes[0]['channels'][1], 'a route\'s channel after it');
	pg_assert_same('Sig 3', $boxes[0]['signature'], 'with its Signature');
	pg_assert_same('b5@example.com', $boxes[1]['address']);
	pg_assert_true(!array_key_exists('route', $boxes[0]) && !array_key_exists('settings', $boxes[0]), 'no route or settings go to the browser');

	pg_assert_true(ws_mail_compose_in_channel($entries, 20), 'a route\'s channel has the menu entry');
	pg_assert_true(ws_mail_compose_in_channel($entries, '30'), 'and a box\'s own');
	pg_assert_true(!ws_mail_compose_in_channel($entries, 99), 'no box keeps mail there');
	pg_assert_true(!ws_mail_compose_in_channel(array(), 12), 'nobody may send');
}

function test_workspace_mail_compose_request()
{
	$mail = ws_mail_compose_mail(array('box_id' => '3', 'channel_id' => '12', 'to' => 'a@x.test, b@x.test', 'cc' => array('x'), 'bcc' => '',
		'subject' => 'Offer', 'text' => 'Hello', 'other' => 'ignored'), array(array('name' => 'a.pdf', 'bytes' => '%PDF')));

	pg_assert_same(array('box_id' => 3, 'channel_id' => 12, 'to' => 'a@x.test, b@x.test', 'cc' => '', 'bcc' => '', 'subject' => 'Offer', 'text' => 'Hello',
		'files' => array(array('name' => 'a.pdf', 'bytes' => '%PDF'))), $mail, 'what ws_mail_send() takes; a list is no text');
	pg_assert_same(0, ws_mail_compose_mail(array(), array())['box_id'], 'nothing asked');

	pg_assert_same(array('ok' => true, 'error' => '', 'files' => array()), ws_mail_compose_files(null), 'no files');
	pg_assert_same(array(), ws_mail_compose_files(array('name' => array(''), 'error' => array(UPLOAD_ERR_NO_FILE), 'tmp_name' => array('')))['files'], 'an empty part');
	pg_assert_same(ws_mail_send_error_text('too_many_files'), ws_mail_compose_files(array('name' => array_fill(0, WS_MAIL_SEND_FILES + 1, 'a.txt')))['error'], 'too many parts');
	pg_assert_true(!ws_mail_compose_files(array('name' => array('a.txt'), 'error' => array(UPLOAD_ERR_OK), 'tmp_name' => array('/etc/hostname')))['ok'], 'a file that is no upload of this request');
}

function test_workspace_mail_compose_who_may_send()
{
	pg_assert_same(array(), ws_mail_send_boxes(array('id' => 4, 'member' => false)), 'a guest has no mailbox');
	pg_assert_same('not_ready', ws_mail_send_check(array('id' => 4, 'member' => true), array('box_id' => 1))['error'], 'nothing without the database');

	$actions = workspace_mail_compose_test_source('includes/workspace/actions.php');
	$case = substr($actions, (int) strpos($actions, "case 'ws_mail_compose':"), 2000);

	pg_assert_contains("case 'ws_mail_new':", $case, 'the send action');
	pg_assert_contains("if (empty(\$viewer['member'])) {\n                return ws_action_error(ws_mail_send_error_text('no_box'));", $case, 'a guest is refused');
	pg_assert_contains("ws_mail_compose_boxes(ws_mail_send_boxes(\$viewer))", $case, 'the form offers only the boxes the person may send from');
	pg_assert_contains("\$files = ws_mail_compose_files(\$_FILES['files'] ?? null);", $case, 'files as form parts');
	pg_assert_contains("\$result = ws_mail_send(\$viewer, \$mail);", $case, 'sent through the one send path, which checks box, channel, addresses and files');
	pg_assert_true(strpos($case, 'ws_mail_reply_send') === false && strpos($case, 'email(') === false, 'no other way out');

	$check = workspace_mail_compose_test_source('includes/workspace/mail_send.php');
	pg_assert_contains("\$check = ws_mail_send_check(\$viewer, \$mail);", $check, 'ws_mail_send() checks first');
	pg_assert_contains("foreach (ws_mail_send_boxes(\$viewer) as \$entry) {", $check, 'the box comes from the person\'s boxes');

	pg_assert_true(strpos(workspace_mail_compose_test_source('includes/workspace/mcp.php'), 'ws_mail_new') === false, 'no MCP tool');
}

function test_workspace_mail_compose_toolbar_and_menu()
{
	$filters = ws_mail_list_filters(array());
	pg_assert_contains('data-ws-mail-new', ws_mail_list_filters_html($filters, array(), true), 'the button for whoever may send');
	pg_assert_true(strpos(ws_mail_list_filters_html($filters, array(), false), 'data-ws-mail-new') === false, 'none otherwise');
	pg_assert_contains('ws_mail_send_ready() && (bool) ws_mail_send_boxes($viewer)', workspace_mail_compose_test_source('workspace_mail.php'), 'the screen asks');

	pg_assert_contains("\$brief['mail_new'] = function_exists('ws_mail_send_ready') && ws_mail_send_ready() && !empty(\$viewer['member'])",
		workspace_mail_compose_test_source('includes/workspace/channels.php'), 'the channel says whether its menu has the entry');

	$js = workspace_mail_compose_test_source('assets/js/workspace.js');
	pg_assert_contains("if (channel.mail_new && window.PGWsMail) {\n                item(t('mail_new'), 'bi-envelope-plus'", $js, 'the channel menu entry');
	pg_assert_contains("window.PGWsMail.compose(featureHelpers(), { channel: channel.id", $js, 'opens the form on that channel');

	$mail = workspace_mail_compose_test_source('assets/js/workspace_mail.js');
	pg_assert_contains("h.api('ws_mail_compose')", $mail, 'the form asks for the boxes');
	pg_assert_contains("form.append('action', 'ws_mail_new');", $mail, 'and sends');
	pg_assert_contains("form.append('files[]', file, file.name);", $mail, 'files as form parts');
	pg_assert_contains("[data-ws-mail-new]", $mail, 'the screen\'s button');
	pg_assert_contains('ws_mail_compose_js_strings()', workspace_mail_compose_test_source('includes/workspace/screen.php'), 'its words');
}

// The unsent form's slot and what is read back, under Node.
function test_workspace_mail_compose_unsent_form()
{
	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);

	if ($code !== 0) {
		return;
	}

	$now = 1800000000000;
	$script = "'use strict';\nvar window = {};\n" . workspace_mail_compose_test_source('assets/js/workspace_mail.js')
		. "\nvar M = window.PGWsMail;\nconsole.log(JSON.stringify(["
		. "M.newDraftKey('/pg/api.php', 7), M.newDraftKey('/pg/api.php', '8'), M.newDraftKey('/shop/api.php', 7),"
		. "M.newDraftFresh(JSON.stringify({box: 3, channel: 12, to: 'a@x.test', cc: '', bcc: '', subject: '', text: '', at: " . ($now - 1000) . "}), " . $now . "),"
		. "M.newDraftFresh(JSON.stringify({box: 3, to: 'a@x.test', at: " . ($now - 15 * 86400000) . "}), " . $now . "),"
		. "M.newDraftFresh(JSON.stringify({box: 3, to: ' ', at: " . $now . "}), " . $now . "),"
		. "M.newDraftFresh('{broken', " . $now . "),"
		. "M.newDraftFresh(JSON.stringify({text: 5, subject: 'Hi', at: " . $now . "}), " . $now . ")"
		. "]));\n";
	$file = tempnam(sys_get_temp_dir(), 'pgwsmailnew');
	file_put_contents($file, $script);
	$out = array();
	exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	unlink($file);
	$answer = json_decode(implode("\n", $out), true);

	pg_assert_true(is_array($answer), 'Node printed JSON: ' . implode("\n", $out));
	pg_assert_same('ws-mail-new:/pg/api.php|7', $answer[0], 'one slot per site and person');
	pg_assert_same('ws-mail-new:/pg/api.php|8', $answer[1], 'another person');
	pg_assert_same('ws-mail-new:/shop/api.php|7', $answer[2], 'another site');
	pg_assert_same(array('box' => 3, 'channel' => 12, 'at' => $now - 1000, 'to' => 'a@x.test', 'cc' => '', 'bcc' => '', 'subject' => '', 'text' => ''), $answer[3], 'a fresh form');
	pg_assert_same(null, $answer[4], 'older than 14 days');
	pg_assert_same(null, $answer[5], 'no words');
	pg_assert_same(null, $answer[6], 'broken');
	pg_assert_same('', $answer[7]['text'], 'a field that is no text is empty');
	pg_assert_same('Hi', $answer[7]['subject']);

	$mail = workspace_mail_compose_test_source('assets/js/workspace_mail.js');
	pg_assert_contains("newDraftStore(key, null);\n                send.disabled = false;", $mail, 'cleared once sent');
	pg_assert_contains("form.addEventListener('input', keep);", $mail, 'kept while typing');
	pg_assert_true(strpos(substr($mail, (int) strpos($mail, 'function current()'), 600), 'files') === false, 'files are never kept');
}
