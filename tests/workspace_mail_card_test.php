<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the mail card in a Workspace channel and the mailboxes card of
 * the settings screen (includes/workspace/mail.php, mail_settings.php): the
 * card draws mail as plain text with the verified badge, the subject only on
 * the conversation's card, "Show all" when the message holds less than the
 * mail, the attachments not taken in; the sender drawn like a guest; the
 * message payload uses them; a mailbox form is checked; an .eml brought in
 * by hand is answered for every outcome of ws_mail_ingest().
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

function workspace_mail_card_test_item($overrides = array())
{
	return array_merge(array(
		'id'            => 7,
		'from_email'    => 'ayse@example.com',
		'from_name'     => 'Ayşe Yılmaz',
		'subject'       => 'Order <42>',
		'body_text'     => "Hello <b>team</b>\nsecond line",
		'auth'          => 'pass',
		'refused_files' => '',
		'conversation'  => array('id' => 3, 'message_id' => 100),
	), $overrides);
}

function test_workspace_mail_card_is_plain_text_with_the_subject_on_the_card()
{
	$row = array('id' => 100, 'file_id' => 0, 'body' => "Hello <b>team</b>\nsecond line");
	$html = ws_mail_card_html($row, workspace_mail_card_test_item());

	pg_assert_contains('Hello &lt;b&gt;team&lt;/b&gt;<br>' . "\n" . 'second line', $html, 'escaped, line breaks kept');
	pg_assert_contains('<div class="fw-semibold mb-1">Order &lt;42&gt;</div>', $html, 'the subject on the card');
	pg_assert_contains('ayse@example.com', $html, 'the address');
	pg_assert_contains('theme-info', $html, 'a verified sender');
	pg_assert_true(strpos($html, '<details') === false, 'no Show all when the message holds the whole mail');

	$answer = ws_mail_card_html(array('id' => 101, 'file_id' => 0, 'body' => 'Thanks'), workspace_mail_card_test_item());

	pg_assert_true(strpos($answer, 'fw-semibold') === false, 'an answer under the card has no subject');
}

function test_workspace_mail_card_unverified_cut_refused_and_missing()
{
	$item = workspace_mail_card_test_item(array('auth' => 'none', 'body_text' => 'full <text> here', 'refused_files' => "setup.exe\npage.html"));
	$html = ws_mail_card_html(array('id' => 100, 'file_id' => 0, 'body' => 'full…'), $item);

	pg_assert_contains('theme-warning', $html, 'an unverified sender');
	pg_assert_contains(h(lang('Sender not verified')), $html, 'says so');
	pg_assert_contains('<details class="mt-1"><summary class="small">' . h(lang('Show all')) . '</summary><div class="mt-1">full &lt;text&gt; here</div></details>', $html, 'the whole mail behind Show all, escaped');
	pg_assert_contains(h(lang(array('string' => 'Not taken in: {var:1}', 'vars' => 'setup.exe, page.html'))), $html, 'the refused files by name');

	pg_assert_same('', ws_mail_card_html(array('id' => 102, 'file_id' => 9, 'body' => ''), $item), 'a message carrying only an attachment shows its file alone');
	pg_assert_contains(h(lang('This e-mail is no longer available.')), ws_mail_card_html(array('id' => 103, 'file_id' => 0, 'body' => 'x'), null), 'the mail row gone');
}

function test_workspace_mail_sender_is_drawn_like_a_guest()
{
	$sender = ws_mail_sender(workspace_mail_card_test_item());

	pg_assert_same(0, $sender['id'], 'no account');
	pg_assert_same('Ayşe Yılmaz', $sender['name'], 'the name the mail gives');
	pg_assert_same('ayse@example.com', $sender['title'], 'the address under it');
	pg_assert_true(!empty($sender['mail']), 'marked as mail');
	pg_assert_same('bob@example.com', ws_mail_sender(workspace_mail_card_test_item(array('from_name' => ' ', 'from_email' => 'bob@example.com')))['name'], 'the address when there is no name');
	pg_assert_same(lang('E-mail'), ws_mail_sender(null)['name'], 'a mail row gone');
}

function test_workspace_mail_payload_uses_the_card_for_mail()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/messages.php');

	pg_assert_contains("'mail'       => function_exists('ws_mail_items_map') ? ws_mail_items_map(\$rows) : array(),", $source, 'one read of the mails for a page');
	pg_assert_contains("\$sender = ws_mail_sender(\$extra['mail'][(int) \$row['sender_id']] ?? null);", $source, 'the sender');
	pg_assert_contains("\$mail ? ws_mail_card_html(\$row, \$extra['mail'][(int) \$row['sender_id']] ?? null) :", $source, 'never ws_render_body() for a mail');
	pg_assert_same(array(), ws_mail_items_map(array(array('sender_kind' => 'user', 'sender_id' => 1))), 'no mail, no read');
}

function test_workspace_mail_box_input()
{
	$ok = ws_mail_box_input(array('name' => ' Support ', 'address' => ' Support@Example.COM ', 'channel_id' => '4', 'auth_server' => 'MX.Google.com', 'enabled' => '1'));

	pg_assert_true($ok['ok'], 'a full form');
	pg_assert_same(array(
		'name' => 'Support', 'address' => 'support@example.com', 'channel_id' => 4, 'auth_server' => 'mx.google.com', 'enabled' => 1,
		'provider' => '', 'host' => '', 'port' => 993, 'user' => '', 'password' => '', 'folder' => 'INBOX', 'after_fetch' => 'seen', 'move_folder' => '',
		'reply_via' => 'box', 'smtp_host' => '', 'smtp_port' => 0, 'sent_copy' => 0, 'sent_folder' => '', 'connection_id' => 0,
	), $ok['values'], 'cleaned; no server is an .eml-only box');

	$base = array('name' => 'Support', 'address' => 'support@example.com', 'channel_id' => 4, 'auth_server' => '');

	pg_assert_true(ws_mail_box_input($base)['ok'], 'no checking server is allowed');
	pg_assert_same(0, ws_mail_box_input($base)['values']['enabled'], 'off unless ticked');
	pg_assert_same(lang('Give the mailbox a name of at most 100 characters.'), ws_mail_box_input(array_merge($base, array('name' => '')))['error'], 'a name');
	pg_assert_same(lang('Give the mailbox a name of at most 100 characters.'), ws_mail_box_input(array_merge($base, array('name' => str_repeat('a', 101))))['error'], 'not too long');
	pg_assert_same(lang('Enter the mailbox\'s e-mail address.'), ws_mail_box_input(array_merge($base, array('address' => 'support')))['error'], 'an address');
	pg_assert_same(lang('Choose the channel its mail goes to.'), ws_mail_box_input(array_merge($base, array('channel_id' => '')))['error'], 'a channel');
	pg_assert_same(lang('The checking server is a host name such as mx.google.com.'), ws_mail_box_input(array_merge($base, array('auth_server' => 'mx google;com')))['error'], 'a host name');
	pg_assert_true(!ws_mail_box_input(array_merge($base, array('auth_server' => 'localhost')))['ok'], 'with a dot');
}

function test_workspace_mail_ingest_message_for_every_outcome()
{
	$stored = ws_mail_ingest_message(array('status' => 'stored', 'reason' => ''));

	pg_assert_true($stored['notice'], 'stored is good news');
	pg_assert_true(ws_mail_ingest_message(array('status' => 'duplicate', 'reason' => ''))['notice'], 'a duplicate changes nothing');

	foreach (array(array('loop', 'own'), array('held', ''), array('refused', 'too_large'), array('refused', 'no_sender'), array('refused', 'no_channel'), array('refused', 'not_saved'), array('refused', 'other')) as $case) {
		$said = ws_mail_ingest_message(array('status' => $case[0], 'reason' => $case[1]));

		pg_assert_true(!$said['notice'] && ($said['text'] !== ''), $case[0] . ' ' . $case[1] . ' is an error with words');
	}
}

function test_workspace_mail_settings_card_is_on_the_screen()
{
	$screen = (string) file_get_contents(PG_FUNCTIONS_DIR . '/workspace_settings.php');

	pg_assert_contains("case 'mail_box_save':\n        case 'mail_eml':\n        case 'mail_test':\n            ws_mail_settings_post(\$viewer, \$action, \$liveform);", $screen, 'its posts');
	pg_assert_contains("' . ws_mail_settings_card(\$self_url, \$viewer) . '", $screen, 'its card');

	$settings = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/mail_settings.php');

	pg_assert_contains('enctype="multipart/form-data"', $settings, 'the .eml form uploads a file');
	pg_assert_contains("is_uploaded_file((string) \$file['tmp_name'])", $settings, 'only an uploaded file is read');
}

function test_workspace_mail_box_input_server()
{
	$base = array('name' => 'Support', 'address' => 'support@example.com', 'channel_id' => 4, 'enabled' => '1');
	$gmail = ws_mail_box_input(array_merge($base, array('provider' => 'gmail', 'user' => ' support@example.com ', 'password' => ' app pass ')));

	pg_assert_true($gmail['ok'], 'a provider alone is enough');
	pg_assert_same('imap.gmail.com', $gmail['values']['host'], 'its server filled');
	pg_assert_same(993, $gmail['values']['port']);
	pg_assert_same('mx.google.com', $gmail['values']['auth_server'], 'its checking server filled');
	pg_assert_same('support@example.com', $gmail['values']['user']);
	pg_assert_same(' app pass ', $gmail['values']['password'], 'a password is kept as typed');

	$typed = ws_mail_box_input(array_merge($base, array('provider' => 'gmail', 'host' => 'IMAP.Example.com', 'port' => '1993', 'auth_server' => 'mx.example.com', 'user' => 'u')));
	pg_assert_same(array('imap.example.com', 1993, 'mx.example.com'), array($typed['values']['host'], $typed['values']['port'], $typed['values']['auth_server']), 'what was typed wins');

	pg_assert_same('', ws_mail_box_input(array_merge($base, array('provider' => 'nope')))['values']['provider'], 'an unknown provider is none');
	pg_assert_same(lang('The IMAP server is a host name such as imap.gmail.com, with a port from 1 to 65535.'), ws_mail_box_input(array_merge($base, array('host' => 'localhost', 'user' => 'u')))['error']);
	pg_assert_same(lang('The IMAP server is a host name such as imap.gmail.com, with a port from 1 to 65535.'), ws_mail_box_input(array_merge($base, array('host' => 'imap.example.com', 'port' => '70000', 'user' => 'u')))['error']);
	pg_assert_same(lang('Enter the user name the mailbox signs in with.'), ws_mail_box_input(array_merge($base, array('host' => 'imap.example.com')))['error']);
	pg_assert_same(lang('The password cannot be kept as typed.'), ws_mail_box_input(array_merge($base, array('host' => 'imap.example.com', 'user' => 'u', 'password' => "a\nb")))['error']);

	$server = array_merge($base, array('host' => 'imap.example.com', 'user' => 'u'));
	pg_assert_same('seen', ws_mail_box_input(array_merge($server, array('after_fetch' => 'other', 'move_folder' => 'Done')))['values']['after_fetch'], 'seen unless move');
	pg_assert_same('', ws_mail_box_input(array_merge($server, array('move_folder' => 'Done')))['values']['move_folder'], 'no move folder without move');
	pg_assert_same(lang('Name the folder to read and, to move mail after reading, the folder it goes to.'), ws_mail_box_input(array_merge($server, array('after_fetch' => 'move')))['error']);
	pg_assert_same(lang('Mail cannot be moved into the folder it is read from.'), ws_mail_box_input(array_merge($server, array('after_fetch' => 'move', 'move_folder' => 'inbox')))['error']);
	pg_assert_true(ws_mail_box_input(array_merge($server, array('after_fetch' => 'move', 'move_folder' => "\xC4\xB0\xC5\x9Flendi", 'folder' => 'Support')))['ok'], 'a Turkish folder name');

	foreach (ws_mail_providers() as $key => $provider) {
		pg_assert_true(($provider['host'] === '') || pg_imap_host_valid($provider['host']), $key . ' server is a host name');
		pg_assert_true($provider['hint'] !== '', $key . ' says what it needs');
	}
}

function test_workspace_mail_authserv_id()
{
	pg_assert_same('mx.google.com', ws_mail_authserv_id("Authentication-Results: mx.google.com;\r\n       dkim=pass header.i=@example.com;\r\n       dmarc=pass (p=NONE) header.from=example.com\r\n"));
	pg_assert_same('mx.zohomail.com', ws_mail_authserv_id("authentication-results:\r\n\tMX.Zohomail.com 1 (version);\r\n dmarc=pass\r\n"), 'folded, a version and a comment');
	pg_assert_same('mx.google.com', ws_mail_authserv_id("Authentication-Results: mx.google.com; spf=pass\r\nAuthentication-Results: forged.example; dmarc=pass\r\n"), 'the topmost one');
	pg_assert_same('', ws_mail_authserv_id("Authentication-Results: spf=pass (sender IP is 192.0.2.1) smtp.mailfrom=example.com; dmarc=pass\r\n"), 'a header without an id');
	pg_assert_same('', ws_mail_authserv_id("Subject: hi\r\n"));
}

function test_workspace_mail_box_test_and_errors()
{
	$box = array('id' => 1, 'folder' => 'INBOX', 'after_fetch' => 'seen', 'settings' => '{"host":""}', 'checked_at' => 0, 'last_error' => '');
	pg_assert_same('no_server', ws_mail_box_test($box)['error'], 'nothing to test without a server');

	$box['settings'] = json_encode(array('host' => 'imap.example.com', 'user' => 'u', 'secret' => 'not:sealed'));
	pg_assert_same('no_password', ws_mail_box_test($box)['error'], 'an unreadable password is never sent');

	pg_assert_same(lang('The server refused the user name or password.') . ' ' . lang('Gmail and Google Workspace take an app password here, not the account\'s own password.') . ' ' . lang(array('string' => 'The server said: {var:1}', 'vars' => 'Invalid')),
		ws_mail_error_text('auth', 'Invalid', 'imap.gmail.com'), 'the Gmail hint and the server\'s words');
	pg_assert_same(lang('The server ended the conversation unexpectedly.'), ws_mail_error_text('protocol', '', 'imap.example.com'));
	pg_assert_same('auth|Invalid credentials', ws_mail_last_error('auth', 'Invalid credentials'));
	pg_assert_true(strlen(ws_mail_last_error('auth', str_repeat("\xC5\x9F", 200))) <= 255, 'cut to the column');
	pg_assert_true((bool) preg_match('//u', ws_mail_last_error('auth', str_repeat("\xC5\x9F", 200))), 'no half character');

	pg_assert_same(h(lang('Not checked yet')), ws_mail_box_check_line($box));
	pg_assert_contains(h(ws_mail_error_text('tls', '', 'imap.example.com')), ws_mail_box_check_line(array_merge($box, array('checked_at' => 1700000000, 'last_error' => 'tls|'))), 'the kept error in words');
	pg_assert_contains('fg-success', ws_mail_box_check_line(array_merge($box, array('checked_at' => 1700000000))));
	pg_assert_same(h(lang('Not read from a server')), ws_mail_box_check_line(array_merge($box, array('settings' => ''))));
}

function test_workspace_mail_box_form_never_draws_the_password()
{
	$box = array('id' => 3, 'name' => 'Support', 'address' => 'support@example.com', 'channel_id' => 0, 'folder' => 'INBOX', 'after_fetch' => 'move', 'enabled' => 1,
		'settings' => json_encode(array('host' => 'imap.example.com', 'user' => 'u', 'secret' => 'SEALEDVALUE:iv', 'move_folder' => 'Done', 'provider' => 'zoho')));
	$html = ws_mail_box_form('workspace_settings.php', $box, array());

	pg_assert_true(strpos($html, 'SEALEDVALUE') === false, 'the sealed password is not printed');
	pg_assert_contains('name="password" id="ws_mail_3_password" value="" autocomplete="new-password" placeholder="' . h(lang('Stored. Type a new one to replace it.')) . '"', $html);
	pg_assert_contains('<option value="zoho" selected>', $html);
	pg_assert_contains('<option value="move" selected>', $html);
	pg_assert_contains('value="imap.example.com"', $html);
}

function test_workspace_mail_box_input_sending()
{
	$base = array('name' => 'Support', 'address' => 'support@example.com', 'channel_id' => 4, 'auth_server' => '');

	$gmail = ws_mail_box_input(array_merge($base, array('provider' => 'gmail', 'user' => 'support@example.com')));
	pg_assert_same(array('box', 'smtp.gmail.com', 465), array($gmail['values']['reply_via'], $gmail['values']['smtp_host'], $gmail['values']['smtp_port']), 'a service fills its sending server');

	$typed = ws_mail_box_input(array_merge($base, array('smtp_host' => 'SMTP.Example.com', 'smtp_port' => '2525', 'user' => 'u')));
	pg_assert_true($typed['ok'], 'sending without reading');
	pg_assert_same(array('smtp.example.com', 2525), array($typed['values']['smtp_host'], $typed['values']['smtp_port']));
	pg_assert_same(587, ws_mail_box_input(array_merge($base, array('smtp_host' => 'smtp.example.com', 'user' => 'u')))['values']['smtp_port'], 'the submission port by default');

	pg_assert_same(lang('Enter the user name the mailbox signs in with.'), ws_mail_box_input(array_merge($base, array('smtp_host' => 'smtp.example.com')))['error'], 'a sending server signs in');
	pg_assert_same(lang('The sending server is a host name such as smtp.gmail.com, with a port from 1 to 65535.'), ws_mail_box_input(array_merge($base, array('smtp_host' => 'localhost', 'user' => 'u')))['error']);
	pg_assert_same(lang('The sending server is a host name such as smtp.gmail.com, with a port from 1 to 65535.'), ws_mail_box_input(array_merge($base, array('smtp_host' => 'smtp.example.com', 'smtp_port' => '99999', 'user' => 'u')))['error']);

	$site = ws_mail_box_input(array_merge($base, array('provider' => 'gmail', 'user' => 'u', 'reply_via' => 'site', 'smtp_host' => 'smtp.example.com')));
	pg_assert_same(array('site', '', 0), array($site['values']['reply_via'], $site['values']['smtp_host'], $site['values']['smtp_port']), 'the site\'s settings keep no server');
	pg_assert_same(lang('Choose the connected account answers are sent through.'), ws_mail_box_input(array_merge($base, array('reply_via' => 'connection')))['error'], 'answers through an account need one');
	pg_assert_same('box', ws_mail_box_input(array_merge($base, array('reply_via' => 'odd')))['values']['reply_via'], 'an unknown way is the box\'s server');

	// Microsoft 365: a connected account signs in as the box's address and
	// answers through it; no password, no sending server.
	$microsoft = ws_mail_box_input(array_merge($base, array('provider' => 'microsoft', 'connection_id' => '7', 'reply_via' => 'connection', 'smtp_host' => 'smtp.example.com')));
	pg_assert_true($microsoft['ok'], (string) $microsoft['error']);
	pg_assert_same(array('outlook.office365.com', 993, 7, 'connection', '', $base['address']),
		array($microsoft['values']['host'], $microsoft['values']['port'], $microsoft['values']['connection_id'], $microsoft['values']['reply_via'], $microsoft['values']['smtp_host'], $microsoft['values']['user']));
	pg_assert_same(lang('A connected account reads the mailbox over IMAP: enter its server, or choose the service.'), ws_mail_box_input(array_merge($base, array('connection_id' => '7')))['error']);
	pg_assert_true(!empty(ws_mail_providers()['microsoft']['connection']));

	foreach (ws_mail_providers() as $key => $provider) {
		pg_assert_true(($provider['smtp_host'] === '') || pg_imap_host_valid($provider['smtp_host']), $key . ' sending server is a host name');
	}

	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/mail_settings.php');
	pg_assert_contains("(\$settings['smtp_host'] ?? '') !== \$values['smtp_host']", $source, 'a new sending server asks for the password again');
}

function test_workspace_mail_box_form_sending_fields()
{
	$box = array('id' => 3, 'name' => 'Support', 'address' => 'support@example.com', 'channel_id' => 0, 'folder' => 'INBOX', 'after_fetch' => 'seen', 'enabled' => 1, 'reply_via' => 'site',
		'settings' => json_encode(array('host' => 'imap.example.com', 'user' => 'u', 'smtp_host' => 'smtp.example.com', 'smtp_port' => 465)));
	$html = ws_mail_box_form('workspace_settings.php', $box, array());

	pg_assert_contains('<option value="site" selected>', $html);
	pg_assert_contains('name="smtp_host" id="ws_mail_3_smtp_host" maxlength="253" value="smtp.example.com"', $html);
	pg_assert_contains('name="smtp_port" id="ws_mail_3_smtp_port" min="1" max="65535" value="465"', $html);
}

function test_workspace_mail_sent_answer_is_drawn_as_sent()
{
	$item = workspace_mail_card_test_item(array('direction' => 'out', 'user_id' => 5, 'from_email' => 'support@shop.test', 'to_list' => 'ayse@example.com', 'auth' => 'pass'));
	$html = ws_mail_card_html(array('id' => 101, 'file_id' => 0, 'body' => 'On its way.'), $item);

	pg_assert_contains(h(lang('Sent by e-mail')), $html);
	pg_assert_contains(h(lang(array('string' => 'To {var:1}', 'vars' => 'ayse@example.com'))), $html, 'whom it went to');
	pg_assert_true(strpos($html, h(lang('Sender not verified'))) === false);

	pg_assert_same('Ayşe Yılmaz', ws_mail_sender(workspace_mail_card_test_item(array('direction' => 'out', 'user_id' => 0)))['name'], 'a writer gone: the name kept on the mail');
}
