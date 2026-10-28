<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for answering an e-mail conversation by e-mail
 * (includes/workspace/mail_reply.php) and the parts of email() it uses:
 * the answer's Message-ID, subject, threading headers and text, how a box
 * sends, the threading headers email() lets through and the site's mark.
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

function test_workspace_mail_reply_message_id()
{
	pg_assert_same('pg.c42.ab12@shop.example.com', ws_mail_reply_message_id(42, 'AB12', 'Shop.Example.com'));
	pg_assert_same('pg.c42.ab@localhost', ws_mail_reply_message_id(42, 'a<b>', ''), 'no host, and nothing that breaks the id');
}

function test_workspace_mail_reply_subject()
{
	pg_assert_same('Re: Order 15 [#T7]', ws_mail_reply_subject('Order 15', 7));
	pg_assert_same('RE: Order 15 [#T7]', ws_mail_reply_subject('RE: Order 15', 7), 'already an answer');
	pg_assert_same('Ynt: Sipariş [#T7]', ws_mail_reply_subject('Ynt: Sipariş', 7), 'a Turkish mail program\'s answer');
	pg_assert_same('Re: Order 15 [#T7]', ws_mail_reply_subject('Re: Order 15 [#T7]', 7), 'the tag is not added twice');
	pg_assert_same('Re: Order [#T3] [#T7]', ws_mail_reply_subject('Order [#T3]', 7), 'another conversation\'s tag is not ours');
	pg_assert_same('Re: [#T7]', ws_mail_reply_subject('', 7), 'no subject');
	pg_assert_true(mb_strlen(ws_mail_reply_subject(str_repeat('ş', 300), 7)) <= 255, 'fits the column');
}

function test_workspace_mail_reply_headers()
{
	pg_assert_same(array('Message-ID' => '<pg.c1.ab@shop.test>'), ws_mail_reply_headers(null, 'pg.c1.ab@shop.test'), 'nothing answered');

	$answered = array('rfc_message_id' => 'm3@client.test', 'reference_ids' => 'm1@client.test pg.c1.x@shop.test');

	pg_assert_same(array(
		'Message-ID'  => '<pg.c1.ab@shop.test>',
		'In-Reply-To' => '<m3@client.test>',
		'References'  => '<m1@client.test> <pg.c1.x@shop.test> <m3@client.test>',
	), ws_mail_reply_headers($answered, 'pg.c1.ab@shop.test'));

	$many = array();

	for ($i = 1; $i <= 30; $i++) {
		$many[] = 'r' . $i . '@client.test';
	}

	$headers = ws_mail_reply_headers(array('rfc_message_id' => 'last@client.test', 'reference_ids' => implode(' ', $many)), 'pg.c1.ab@shop.test');
	$ids = explode(' ', $headers['References']);

	pg_assert_same(WS_MAIL_REPLY_REFERENCES, count($ids), 'the oldest are dropped');
	pg_assert_same('<last@client.test>', end($ids), 'the answered mail is last');

	pg_assert_same(array('Message-ID', 'In-Reply-To', 'References'), array_keys(pg_mail_thread_headers($headers)), 'email() takes them all');
}

function test_workspace_mail_reply_body()
{
	pg_assert_same("Thanks.\n\n-- \nAyşe Yılmaz\nSupport", ws_mail_reply_body("Thanks.\r\n", 'Ayşe Yılmaz', 'Support', null));

	$answered = array('from_name' => 'Can', 'from_email' => 'can@client.test', 'sent_at' => mktime(9, 5, 0, 11, 2, 2026), 'created_at' => 0,
		'body_text' => "Where is my order?\n\nCan");
	$body = ws_mail_reply_body('On its way.', 'Ayşe', 'Support', $answered);

	pg_assert_contains("On its way.\n\n-- \nAyşe\nSupport\n\n", $body);
	pg_assert_contains('02.11.2026 09:05', $body, 'when the mail was written');
	pg_assert_contains('Can <can@client.test>', $body);
	pg_assert_contains("> Where is my order?\n>\n> Can", $body, 'quoted, empty lines without a trailing space');

	$long = ws_mail_reply_body('Ok', '', '', array_merge($answered, array('body_text' => str_repeat("line\n", 100))));

	pg_assert_same(WS_MAIL_REPLY_QUOTE_LINES + 1, substr_count($long, "\n> "), 'the quote is cut, with a mark');
	pg_assert_contains('> …', $long);
	pg_assert_true(strpos($long, '-- ') === false, 'no names, no signature');
}

function test_workspace_mail_reply_route()
{
	$box = array('address' => 'support@shop.test', 'reply_via' => 'box',
		'settings' => json_encode(array('host' => 'imap.shop.test', 'user' => 'support@shop.test', 'secret' => 'sealed:iv', 'smtp_host' => 'smtp.shop.test', 'smtp_port' => 465)));

	pg_assert_same(array('ok' => true, 'error' => '', 'smtp' => array('host' => 'smtp.shop.test', 'port' => 465, 'user' => 'support@shop.test', 'secret' => 'sealed:iv')),
		ws_mail_reply_route($box, 'shop.test'), 'the box\'s own server');

	$no_smtp = array_merge($box, array('settings' => json_encode(array('host' => 'imap.shop.test', 'user' => 'u', 'secret' => 's'))));

	pg_assert_same('no_smtp', ws_mail_reply_route($no_smtp, 'shop.test')['error'], 'no sending server');
	pg_assert_same('no_smtp', ws_mail_reply_route(array_merge($box, array('settings' => json_encode(array('smtp_host' => 'smtp.shop.test')))), 'shop.test')['error'], 'no sign-in');

	$site = array_merge($no_smtp, array('reply_via' => 'site'));

	pg_assert_same(array('ok' => true, 'error' => '', 'smtp' => null), ws_mail_reply_route($site, 'Shop.Test'), 'the site\'s settings on its own domain');
	pg_assert_same('domain', ws_mail_reply_route($site, 'other.test')['error'], 'another domain would fail DMARC');
	pg_assert_same('domain', ws_mail_reply_route($site, '')['error']);
	pg_assert_same('connection', ws_mail_reply_route(array_merge($box, array('reply_via' => 'connection')), 'shop.test')['error'], 'a connected account waits for G4');
}

function test_workspace_mail_reply_error_texts()
{
	foreach (array('empty', 'too_long', 'no_access', 'no_box', 'no_customer', 'no_smtp', 'domain', 'connection', 'not_sent') as $error) {
		pg_assert_true(ws_mail_reply_error_text($error) !== '', $error);
	}

	pg_assert_same(ws_mail_reply_error_text('not_sent'), ws_mail_reply_error_text('anything'), 'an unknown reason');
}

function test_mail_thread_headers_lets_only_threading_ids_through()
{
	pg_assert_same(array('Message-ID' => '<a@b.test>', 'In-Reply-To' => '<c@d.test>', 'References' => '<e@f.test> <c@d.test>'),
		pg_mail_thread_headers(array('message-id' => '<a@b.test>', 'In-Reply-To' => ' <c@d.test> ', 'References' => "<e@f.test>\n <c@d.test>")));

	pg_assert_same(array(), pg_mail_thread_headers(array('Bcc' => '<a@b.test>', 'X-Other' => 'x')), 'no other header');
	pg_assert_same(array(), pg_mail_thread_headers(array('In-Reply-To' => "<a@b.test>\r\nBcc: x@y.test")), 'no header smuggled in');
	pg_assert_same(array(), pg_mail_thread_headers(array('Message-ID' => '<a@b.test> <c@d.test>')), 'one id for a Message-ID');
	pg_assert_same(array(), pg_mail_thread_headers(array('Message-ID' => 'a@b.test')), 'brackets required');
	pg_assert_same(array(), pg_mail_thread_headers(array('References' => array('<a@b.test>'))), 'text only');
	pg_assert_same(array(), pg_mail_thread_headers('nonsense'));
}

function test_mail_site_mark()
{
	pg_assert_same(1, preg_match('/^[0-9a-f]{16}$/', pg_mail_site_mark()), 'sixteen hex characters');
	pg_assert_same(pg_mail_site_mark(), pg_mail_site_mark(), 'the same on every mail');

	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/mail.php');

	pg_assert_contains("\$mail->addCustomHeader('X-Pinegrap-Mail', pg_mail_site_mark());", $source, 'every mail carries it');
	pg_assert_same(strtolower('X-Pinegrap-Mail'), WS_MAIL_LOOP_HEADER, 'the header the loop gate reads');
}

function test_workspace_mail_reply_offer()
{
	$item = array('conversation' => array('id' => 3, 'message_id' => 100));

	pg_assert_same(3, ws_mail_reply_offer(array('id' => 100), $item, true), 'the card');
	pg_assert_same(0, ws_mail_reply_offer(array('id' => 101), $item, true), 'not an answer under it');
	pg_assert_same(0, ws_mail_reply_offer(array('id' => 100), $item, false), 'a reader who cannot write');
	pg_assert_same(0, ws_mail_reply_offer(array('id' => 100), array('conversation' => null), true), 'no conversation');
	pg_assert_same(0, ws_mail_reply_offer(array('id' => 100), null, true), 'the mail gone');
}

function test_workspace_mail_reply_reaches_the_screen()
{
	$messages = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/messages.php');
	$actions = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/actions.php');
	$screen = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace.js');
	$script = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace_mail.js');

	pg_assert_contains("'mail_reply'  => (\$mail && !\$deleted && function_exists('ws_mail_reply_offer'))", $messages, 'the payload offers it');
	pg_assert_contains("case 'ws_mail_reply':", $actions, 'the action');
	pg_assert_contains('ws_mail_reply_send($viewer,', $actions, 'checked and sent by the server');
	pg_assert_contains('window.PGWsMail.node(message, self, featureHelpers())', $screen, 'drawn under the card');
	pg_assert_contains("h.api('ws_mail_reply', { conversation_id: message.mail_reply, text: text.value })", $script);
	pg_assert_true(in_array('assets/js/workspace_mail.js', pg_js_bundles()['assets/js/workspace.min.js'], true), 'in the bundle');

	foreach (array_keys(ws_mail_js_strings()) as $key) {
		pg_assert_contains("'" . $key . "'", $script, $key . ' is used');
	}
}
