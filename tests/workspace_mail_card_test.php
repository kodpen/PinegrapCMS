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
	pg_assert_same(array('name' => 'Support', 'address' => 'support@example.com', 'channel_id' => 4, 'auth_server' => 'mx.google.com', 'enabled' => 1), $ok['values'], 'cleaned');

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

	pg_assert_contains("case 'mail_box_save':\n        case 'mail_eml':\n            ws_mail_settings_post(\$viewer, \$action, \$liveform);", $screen, 'its posts');
	pg_assert_contains("' . ws_mail_settings_card(\$self_url, \$viewer) . '", $screen, 'its card');

	$settings = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/mail_settings.php');

	pg_assert_contains('enctype="multipart/form-data"', $settings, 'the .eml form uploads a file');
	pg_assert_contains("is_uploaded_file((string) \$file['tmp_name'])", $settings, 'only an uploaded file is read');
}
