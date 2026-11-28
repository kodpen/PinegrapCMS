<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a customer's own support requests (includes/fn/support_requests.php):
 * the signed link's token, which mails the customer sees, the state's words,
 * the My Account section, the request's page and its Reply box, the reply's
 * check and the mail built from it, the line the automatic answer carries,
 * and where it is wired. No database and no mailbox.
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
require_once(PG_FUNCTIONS_DIR . '/includes/fn/support_requests.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/widgets_support_requests.php');

function support_requests_test_key()
{
	return str_repeat('ab', 32);
}

function support_requests_test_lacks($needle, $haystack, $label = '')
{
	pg_assert_false(strpos((string) $haystack, (string) $needle) !== false, $label);
}

function test_support_requests_token_is_tied_to_the_conversation()
{
	$key = support_requests_test_key();
	$token = pg_support_request_token(12, 1700000000, $key);

	pg_assert_same(64, strlen($token));
	pg_assert_same($token, pg_support_request_token(12, 1700000000, $key), 'stable');
	pg_assert_true($token !== pg_support_request_token(13, 1700000000, $key), 'another conversation');
	pg_assert_true($token !== pg_support_request_token(12, 1700000001, $key), 'another creation time');
	pg_assert_same('', pg_support_request_token(0, 1700000000, $key), 'no id');
	pg_assert_same('', pg_support_request_token(12, 1700000000, ''), 'no key');

	pg_assert_true(pg_support_request_token_valid(12, 1700000000, $token, $key));
	pg_assert_false(pg_support_request_token_valid(12, 1700000000, strtoupper($token), $key), 'case');
	pg_assert_false(pg_support_request_token_valid(12, 1700000000, '', $key), 'empty');
	pg_assert_false(pg_support_request_token_valid(12, 1700000000, array($token), $key), 'not a string');
	pg_assert_false(pg_support_request_token_valid(12, 1700000000, $token, ''), 'no key');
}

function test_support_requests_box_shown()
{
	pg_assert_true(pg_support_request_box_shown(array('transport' => 'imap', 'settings' => '')));
	pg_assert_true(pg_support_request_box_shown(array('transport' => 'imap', 'settings' => '{"support":true}', 'enabled' => 0)), 'a box switched off keeps its requests');
	pg_assert_false(pg_support_request_box_shown(array('transport' => 'imap', 'settings' => '{"support":false}')), 'a plain box');
	pg_assert_false(pg_support_request_box_shown(array('transport' => 'whatsapp', 'settings' => '')));
	pg_assert_false(pg_support_request_box_shown(array('transport' => 'sms', 'settings' => '')));
	pg_assert_false(pg_support_request_box_shown(null));
}

function test_support_requests_customer_sees_only_their_mails()
{
	$customer = 'ayse@example.com';
	$in = array('direction' => 'in', 'from_email' => 'Ayse@Example.com', 'to_list' => 'help@shop.test', 'cc_list' => '', 'send_state' => '');
	$out = array('direction' => 'out', 'from_email' => 'help@shop.test', 'to_list' => 'ayse@example.com', 'cc_list' => '', 'send_state' => 'sent');

	pg_assert_true(pg_support_request_item_visible($in, $customer), 'the customer\'s own mail');
	pg_assert_true(pg_support_request_item_visible($out, $customer), 'an answer to the customer');
	pg_assert_true(pg_support_request_item_visible(array('send_state' => '') + $out, $customer), 'an answer from before the send state');
	pg_assert_true(pg_support_request_item_visible(array('send_state' => 'queued') + $out, $customer), 'queued');
	pg_assert_true(pg_support_request_item_visible(array('to_list' => 'x@example.com, Ayse <ayse@example.com>') + $out, $customer), 'among several');
	pg_assert_true(pg_support_request_item_visible(array('to_list' => 'x@example.com', 'cc_list' => 'ayse@example.com') + $out, $customer), 'in copy');

	pg_assert_false(pg_support_request_item_visible(array('from_email' => 'colleague@example.com') + $in, $customer), 'another person\'s mail');
	pg_assert_false(pg_support_request_item_visible(array('to_list' => 'supplier@example.com') + $out, $customer), 'an answer to someone else');
	pg_assert_false(pg_support_request_item_visible(array('to_list' => 'mayse@example.com') + $out, $customer), 'an address that only ends the same');
	pg_assert_false(pg_support_request_item_visible(array('send_state' => 'draft') + $out, $customer), 'a draft');
	pg_assert_false(pg_support_request_item_visible(array('send_state' => 'later') + $out, $customer), 'waiting to be sent');
	pg_assert_false(pg_support_request_item_visible(array('send_state' => 'failed') + $out, $customer), 'failed');
	pg_assert_false(pg_support_request_item_visible(array('direction' => 'note') + $in, $customer), 'neither in nor out');
	pg_assert_false(pg_support_request_item_visible($in, ''), 'no customer address');
}

function test_support_requests_state_words()
{
	pg_assert_same('teal', pg_support_request_state('open')['tone']);
	pg_assert_same('amber', pg_support_request_state('waiting')['tone']);
	pg_assert_same('gray', pg_support_request_state('closed')['tone']);
	pg_assert_same('open', pg_support_request_state('anything')['state']);
	pg_assert_same('success', pg_support_request_state('open')['site_tone']);
	pg_assert_same('warning', pg_support_request_state('waiting')['site_tone']);
	pg_assert_same('secondary', pg_support_request_state('closed')['site_tone']);
	pg_assert_true(pg_support_request_state('waiting')['label'] !== pg_support_request_state('open')['label']);
}

function test_support_requests_number()
{
	pg_assert_same('#T42', pg_support_request_number(42));
}

function test_support_requests_account_view_and_section()
{
	// The answer at 2023-11-15 12:00 UTC: date() formats in the runner's
	// timezone, and noon falls on the same date from UTC-11 to UTC+11.
	$rows = array(
		array('id' => 7, 'subject' => 'Broken <b>mug</b>', 'status' => 'waiting', 'created_at' => 1700000000, 'last_out_at' => 1700049600, 'last_in_at' => 1700000000),
		array('id' => 8, 'subject' => '', 'status' => 'open', 'created_at' => 1700100000, 'last_out_at' => 0, 'last_in_at' => 1700100000),
	);
	$view = pg_support_request_account_view($rows, support_requests_test_key(), 'https://shop.test/pinegrap/support_request.php', 'Y-m-d');

	pg_assert_same(2, count($view));
	pg_assert_same('#T7', $view[0]['number']);
	pg_assert_same('https://shop.test/pinegrap/support_request.php?c=7&t=' . pg_support_request_token(7, 1700000000, support_requests_test_key()), $view[0]['url']);
	pg_assert_same('2023-11-15', $view[0]['last_answer']);
	pg_assert_same('', $view[1]['last_answer'], 'not answered yet');
	pg_assert_true($view[1]['subject'] !== '', 'a request with no subject is still named');
	pg_assert_same('warning', $view[0]['tone']);

	pg_assert_same(array(), pg_support_request_account_view($rows, '', 'x', 'Y-m-d'), 'no key, no links');

	$html = pg_support_request_account_html($view);
	pg_assert_contains('pg-sw-requests', $html);
	pg_assert_contains('Broken &lt;b&gt;mug&lt;/b&gt;', $html);
	pg_assert_contains('href="https://shop.test/pinegrap/support_request.php?c=7&amp;t=', $html);
	pg_assert_contains('badge text-bg-warning', $html);
	pg_assert_contains('#T7', $html);
	support_requests_test_lacks('<b>mug', $html);
	pg_assert_same('', pg_support_request_account_html(array()));
}

function test_support_requests_reply_check()
{
	pg_assert_same('', pg_support_request_reply_check('  Thank you, it works now.  ')['error']);
	pg_assert_same('Thank you, it works now.', pg_support_request_reply_check('  Thank you, it works now.  ')['text']);
	pg_assert_true(pg_support_request_reply_check("  \n ")['error'] !== '', 'empty');
	pg_assert_true(pg_support_request_reply_check(array('x'))['error'] !== '', 'not a string');
	pg_assert_true(pg_support_request_reply_check(str_repeat('a', PG_SUPPORT_REQUEST_REPLY_MAX + 1))['error'] !== '', 'too long');
	pg_assert_same('', pg_support_request_reply_check(str_repeat('a', PG_SUPPORT_REQUEST_REPLY_MAX))['error']);
	pg_assert_same("a\nb", pg_support_request_reply_check("a\r\nb")['text']);
}

function test_support_requests_reply_mail_threads_back()
{
	$conversation = array('id' => 9, 'subject' => 'Broken mug', 'customer_email' => 'ayse@example.com', 'customer_name' => 'Ayşe Yılmaz');
	$box = array('address' => 'help@shop.test');
	$raw = pg_support_request_reply_raw($conversation, $box, "It came broken.\nPhoto attached later.", 'pg-request-9-abc@shop.test', 1700000000);

	pg_assert_contains('From: =?UTF-8?B?', $raw);
	pg_assert_contains('<ayse@example.com>', $raw);
	pg_assert_contains('To: help@shop.test', $raw);
	pg_assert_contains('Subject: Re: Broken mug [#T9]', $raw);
	pg_assert_contains('Message-ID: <pg-request-9-abc@shop.test>', $raw);
	support_requests_test_lacks('Auto-Submitted', $raw, 'the loop gate takes a person\'s reply');

	$parsed = pg_mail_parse($raw, array('with_content' => true));
	pg_assert_same('ayse@example.com', $parsed['from']['email']);
	pg_assert_same('Ayşe Yılmaz', $parsed['from']['name']);
	pg_assert_same('Re: Broken mug [#T9]', $parsed['subject']);
	pg_assert_same("It came broken.\nPhoto attached later.", trim(str_replace("\r\n", "\n", (string) $parsed['text'])));

	$evil = pg_support_request_reply_raw(array('subject' => "Hi\r\nBcc: x@evil.test") + $conversation, $box, 'x', 'id@shop.test', 1700000000);
	support_requests_test_lacks("\r\nBcc:", $evil, 'no header injection');
}

function test_support_requests_reply_to_a_long_subject_threads_back()
{
	// The intake keeps a subject to 255 characters; the page's answer has no
	// threading headers, so only its [#T<id>] tag leads it back.
	$conversation = array('id' => 9, 'subject' => str_repeat('ş', 255), 'customer_email' => 'ayse@example.com', 'customer_name' => '');
	$raw = pg_support_request_reply_raw($conversation, array('address' => 'help@shop.test'), 'Still waiting.', 'pg-request-9-def@shop.test', 1700000000);
	$parsed = pg_mail_parse($raw, array('with_content' => true));

	pg_assert_same(9, ws_mail_subject_tag((string) $parsed['subject']), 'the tag survives the cut');
}

function test_support_requests_page_html()
{
	$conversation = array('id' => 9, 'subject' => 'Broken <mug>', 'status' => 'waiting', 'customer_name' => 'Ayşe');
	$items = array(
		array('direction' => 'in', 'from_name' => 'Ayşe', 'body_text' => "It came <broken>.\nSad.", 'created_at' => 1700000000, 'sent_at' => 0),
		array('direction' => 'out', 'from_name' => 'Help desk', 'body_text' => 'We send a new one.', 'created_at' => 1700086400, 'sent_at' => 1700086400),
	);
	$html = pg_support_request_page_html($conversation, $items, array('action' => '/pinegrap/support_request.php?c=9&t=abc', 'token_field' => '<input type="hidden" name="token" value="x">',
		'text' => '', 'error' => '', 'sent' => false, 'date_format' => 'Y-m-d H:i'));

	pg_assert_contains('Broken &lt;mug&gt;', $html);
	pg_assert_contains('#T9', $html);
	pg_assert_contains('pg-pill pg-tone-amber', $html);
	pg_assert_contains('It came &lt;broken&gt;.<br', $html);
	pg_assert_contains('Help desk', $html);
	pg_assert_contains('<textarea', $html);
	pg_assert_contains('name="reply"', $html);
	pg_assert_contains('maxlength="' . PG_SUPPORT_REQUEST_REPLY_MAX . '"', $html);
	pg_assert_contains('name="token"', $html);
	pg_assert_contains('action="/pinegrap/support_request.php?c=9&amp;t=abc"', $html);
	pg_assert_contains('pg-button pg-button-fill', $html);
	support_requests_test_lacks('btn btn-', $html, 'look A buttons only');
	pg_assert_true(strpos($html, 'It came') < strpos($html, 'We send a new one.'), 'oldest first');

	$error = pg_support_request_page_html($conversation, $items, array('action' => 'x', 'token_field' => '', 'text' => 'kept <text>', 'error' => 'Write your answer.', 'sent' => false, 'date_format' => 'Y-m-d'));
	pg_assert_contains('aria-invalid="true"', $error);
	pg_assert_contains('kept &lt;text&gt;</textarea>', $error);

	$sent = pg_support_request_page_html($conversation, $items, array('action' => 'x', 'token_field' => '', 'text' => '', 'error' => '', 'sent' => true, 'date_format' => 'Y-m-d'));
	pg_assert_contains('pg-result', $sent);

	$closed = pg_support_request_page_html(array('status' => 'closed') + $conversation, $items, array('action' => 'x', 'token_field' => '', 'text' => '', 'error' => '', 'sent' => false, 'date_format' => 'Y-m-d'));
	pg_assert_contains('<textarea', $closed, 'a closed request can be written to again');
}

function test_support_requests_auto_reply_line()
{
	$text = pg_support_request_auto_reply_text('We got your mail.', 'https://shop.test/pinegrap/support_request.php?c=9&t=abc');
	pg_assert_true(strpos($text, 'We got your mail.') === 0);
	pg_assert_contains("\n\n", $text);
	pg_assert_contains('https://shop.test/pinegrap/support_request.php?c=9&t=abc', $text);
	pg_assert_same('We got your mail.', pg_support_request_auto_reply_text('We got your mail.', ''), 'no link, the text as it was');
}

function test_support_requests_wiring()
{
	$page = file_get_contents(PG_FUNCTIONS_DIR . '/support_request.php');
	pg_assert_contains('pg_support_request_find(', $page);
	pg_assert_contains('validate_token_field()', $page);
	pg_assert_contains('pg_support_request_throttled(', $page);
	pg_assert_contains("header('Cache-Control: no-store, private')", $page);
	pg_assert_contains("header('X-Robots-Tag: noindex, nofollow')", $page);
	pg_assert_contains("header('Referrer-Policy: no-referrer')", $page);

	$waf = file_get_contents(PG_FUNCTIONS_DIR . '/waf.php');
	pg_assert_contains("'support_request.php'       => array('t')", $waf);
	pg_assert_contains("'support_request.php'", substr($waf, strpos($waf, 'function waf_sensitive_scripts')));

	$account = file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/widgets_account.php');
	pg_assert_contains("'requests' => ''", $account);
	pg_assert_contains("'has_requests' => false", $account);
	pg_assert_contains('pg_support_request_account(', $account);

	$status = file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/mail_status.php');
	pg_assert_contains('pg_support_request_auto_reply_text(', $status);

	$designer = file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	pg_assert_contains("out.push(['requests',", $designer);
	pg_assert_contains("['has_requests',", $designer);
	pg_assert_contains("section === 'requests'", $designer);
	pg_assert_contains('function _sdAccountRequestsHtml(', $designer);
}
