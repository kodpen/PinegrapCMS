<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * A written e-mail's HTML body (includes/workspace/mail_send.php,
 * mail_compose.php): the form's HTML is cleaned by the allowlist cleaner and
 * kept beside the text, the mail leaves as HTML with the writer, the
 * Signature and the quote under the written part, and a mail without HTML
 * leaves as plain text as before.
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
require_once(PG_FUNCTIONS_DIR . '/includes/fn/mail_html.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php');

function workspace_mail_html_body_test_mail($changes = array())
{
	return array_merge(array('to' => 'ayse@example.com', 'subject' => 'Order', 'text' => 'Hello team'), $changes);
}

function test_workspace_mail_html_body_input_cleans_the_html()
{
	$input = ws_mail_send_input(workspace_mail_html_body_test_mail());
	pg_assert_true($input['ok'], 'a plain mail');
	pg_assert_same('', $input['html'], 'no HTML without one given');

	$input = ws_mail_send_input(workspace_mail_html_body_test_mail(array('html' => '<p>Hello <b>team</b><script>alert(1)</script></p><a href="javascript:x()">x</a><a href="https://example.com">site</a>')));
	pg_assert_true($input['ok'], 'with HTML');
	pg_assert_contains('<b>team</b>', $input['html'], 'bold kept');
	pg_assert_true(strpos($input['html'], 'script') === false, 'no script');
	pg_assert_true(strpos($input['html'], 'javascript:') === false, 'no javascript link');
	pg_assert_contains('href="https://example.com"', $input['html'], 'a web link kept');
	pg_assert_same('Hello team', $input['text'], 'the text stays the plain part');

	$input = ws_mail_send_input(workspace_mail_html_body_test_mail(array('html' => array('<b>x</b>'))));
	pg_assert_same('', $input['html'], 'a list is no HTML');

	$input = ws_mail_send_input(workspace_mail_html_body_test_mail(array('html' => str_repeat('<p>' . str_repeat('a', 1000) . '</p>', 1100))));
	pg_assert_same('too_long', $input['error'], 'HTML over the cleaner\'s limit is refused, not sent bare');
}

function test_workspace_mail_html_body_answer_html_wraps_signature_and_quote()
{
	$box = array('name' => 'Destek', 'settings' => '{}');
	$html = ws_mail_answer_html('<p>Hello <b>team</b></p>', 'Can <Demir>', $box, null);

	pg_assert_contains('<p>Hello <b>team</b></p>', $html, 'the written part as written');
	pg_assert_contains('-- <br>', $html, 'the signature line');
	pg_assert_contains('Can &lt;Demir&gt;', $html, 'the writer, escaped');
	pg_assert_contains('Destek', $html, 'the box without a Signature');
	pg_assert_true(strpos($html, '<html') === 0 || strpos($html, '<!DOCTYPE') === 0, 'a whole document');

	$answered = array('from_name' => 'Ayşe', 'from_email' => 'ayse@example.com', 'sent_at' => 0, 'created_at' => 1700000000, 'body_text' => "Where is <my> order?");
	$html = ws_mail_answer_html('<p>On its way</p>', 'Can', $box, $answered);
	pg_assert_contains('&gt; Where is &lt;my&gt; order?', $html, 'the quote under it, escaped');
	pg_assert_true(strpos($html, 'On its way') < strpos($html, 'Where is'), 'the answer above the quote');
}

function test_workspace_mail_html_body_properties_send_html_only_when_kept()
{
	$box = array('name' => 'Destek', 'address' => 'destek@example.com', 'settings' => '{}');
	$item = array('id' => 9, 'conversation_id' => 3, 'message_id' => 0, 'in_reply_to' => '', 'to_list' => 'a@example.com', 'cc_list' => '', 'bcc_list' => '',
		'subject' => 'Order', 'body_text' => 'Hello team', 'from_name' => 'Can', 'rfc_message_id' => '<x@example.com>', 'reference_ids' => '');

	$plain = ws_mail_send_properties($item, $box, array());
	pg_assert_false(isset($plain['format']) && ($plain['format'] === 'html'), 'no HTML kept: plain text as before');

	$item['body_html'] = '<p>Hello <b>team</b></p>';
	$html = ws_mail_send_properties($item, $box, array());
	pg_assert_same('html', $html['format'] ?? '', 'sent as HTML');
	pg_assert_contains('<b>team</b>', $html['body'], 'the kept HTML');
	pg_assert_contains('Can', $html['body'], 'with the writer');
}

function test_workspace_mail_html_body_is_written_and_asked()
{
	$send = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/mail_send.php');
	pg_assert_contains('ws_mail_send_keep_html($item_id, $input[\'html\']);', $send, 'kept for a new mail and an answer');

	$actions = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/mail_compose.php');
	pg_assert_contains('function ws_mail_markup_parts(', $actions, 'the writing box\'s markup turned into text and HTML');
}

function test_workspace_mail_html_body_plain_form_stays_plain()
{
	$mail = array('to' => 'a@example.com', 'subject' => 'S', 'text' => '**not bold**');
	pg_assert_same($mail, ws_mail_markup_apply(array('id' => 1), $mail, array()), 'no format: as it came');
	pg_assert_same($mail, ws_mail_markup_apply(array('id' => 1), $mail, array('format' => 'html')), 'only the writing box\'s markup is turned');

	$actions = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/actions.php');
	pg_assert_same(2, substr_count($actions, 'ws_mail_markup_apply($viewer, ws_mail_compose_mail('), 'New e-mail and Reply both ask it');
}
