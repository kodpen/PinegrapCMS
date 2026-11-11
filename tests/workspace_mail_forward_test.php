<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for Forward as e-mail (mail_forward.php): the subject, the quoted
 * original, the files carried within the limits of a mail, the line under a
 * forwarded mail, who is offered the button, and the wiring of the action,
 * the screen and the script. The reads and writes need a database and are
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

function workspace_mail_forward_test_source($relative)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $relative);
}

function workspace_mail_forward_test_item($overrides = array())
{
	return array_merge(array(
		'id'            => 7,
		'direction'     => 'in',
		'conversation_id' => 3,
		'user_id'       => 0,
		'from_email'    => 'ayse@example.com',
		'from_name'     => 'Ayşe Yılmaz',
		'to_list'       => 'support@shop.test',
		'cc_list'       => '',
		'bcc_list'      => 'hidden@shop.test',
		'subject'       => 'Order 42',
		'body_text'     => "Hello team\r\nsecond line\n",
		'auth'          => 'pass',
		'refused_files' => '',
		'message_id'    => 100,
		'created_at'    => 1800000000,
		'sent_at'       => 0,
		'conversation'  => array('id' => 3, 'message_id' => 100, 'channel_id' => 12),
	), $overrides);
}

function test_workspace_mail_forward_ids_and_subject()
{
	pg_assert_same(array(4, 2, 9), ws_mail_forward_ids('4,2,4, 9,x,-1,0'), 'a string: positive, each once, in order');
	pg_assert_same(array(5, 6), ws_mail_forward_ids(array('5', 6, array(7))), 'a list; a nested list names nothing');
	pg_assert_same(array(), ws_mail_forward_ids(null));
	pg_assert_same(100, count(ws_mail_forward_ids(range(1, 150))), 'at most 100');

	pg_assert_same('Fwd: Order 42', ws_mail_forward_subject('Order 42'));
	pg_assert_same('Fwd: Re: Order 42', ws_mail_forward_subject('Re: Order 42 [#T42]'), 'the conversation\'s tag dropped');
	pg_assert_same('Fwd: Order', ws_mail_forward_subject('Fwd: Order'), 'once');
	pg_assert_same('FW: Order', ws_mail_forward_subject('FW: Order'), 'another client\'s prefix counts');
	pg_assert_same('Fwd:', ws_mail_forward_subject(''), 'no subject');
	pg_assert_same(200, mb_strlen(ws_mail_forward_subject(str_repeat('ş', 300))), 'cut to the subject field');
}

function test_workspace_mail_forward_cut_and_take()
{
	pg_assert_same('short', ws_mail_forward_cut('short', 10));
	pg_assert_same("abcde\n[…]", ws_mail_forward_cut('abcdefghijkl', 9), 'cut where it was too long');
	pg_assert_true(mb_strlen(ws_mail_forward_cut(str_repeat('ğ', 60000), WS_MAIL_FORWARD_QUOTE_MAX)) <= WS_MAIL_FORWARD_QUOTE_MAX, 'within the quote limit');
	pg_assert_true(WS_MAIL_FORWARD_QUOTE_MAX < WS_MAIL_REPLY_MAX, 'room left for the person\'s words');

	$mb = 1048576;
	$take = ws_mail_forward_take(array(
		array('id' => 1, 'name' => 'a.pdf', 'size' => 15 * $mb),
		array('id' => 2, 'name' => 'b.zip', 'size' => 8 * $mb),
		array('id' => 3, 'name' => 'c.png', 'size' => 5 * $mb),
	));
	pg_assert_same(array(array('id' => 1, 'name' => 'a.pdf', 'size' => 15 * $mb), array('id' => 3, 'name' => 'c.png', 'size' => 5 * $mb)), $take['taken'], 'taken while they fit');
	pg_assert_same(array('b.zip'), $take['left'], 'the one over the limit is named');

	$many = array();

	for ($i = 1; $i <= WS_MAIL_SEND_FILES + 2; $i++) {
		$many[] = array('id' => $i, 'name' => $i . '.txt', 'size' => 10);
	}

	$take = ws_mail_forward_take($many);
	pg_assert_same(WS_MAIL_SEND_FILES, count($take['taken']), 'at most the files of a mail');
	pg_assert_same(array((WS_MAIL_SEND_FILES + 1) . '.txt', (WS_MAIL_SEND_FILES + 2) . '.txt'), $take['left']);
}

function test_workspace_mail_forward_quotes()
{
	$quote = ws_mail_forward_quote_mail(workspace_mail_forward_test_item(array('cc_list' => 'boss@example.com')));

	pg_assert_contains('---------- ' . lang('Forwarded message') . ' ----------', $quote);
	pg_assert_contains(lang('From') . ': Ayşe Yılmaz <ayse@example.com>', $quote, 'the sender');
	pg_assert_contains(lang('Date') . ': ' . date('d.m.Y H:i', 1800000000), $quote, 'when it came');
	pg_assert_contains(lang('Subject') . ': Order 42', $quote);
	pg_assert_contains(lang('To') . ': support@shop.test', $quote);
	pg_assert_contains(lang('Cc') . ': boss@example.com', $quote);
	pg_assert_true(strpos($quote, 'hidden@shop.test') === false, 'never the Bcc');
	pg_assert_true(substr($quote, -strlen("\n\nHello team\nsecond line")) === "\n\nHello team\nsecond line", 'the text last, its line ends made plain');

	$sent = ws_mail_forward_quote_mail(workspace_mail_forward_test_item(array('from_name' => '', 'sent_at' => 1800003600, 'to_list' => '')));
	pg_assert_contains(lang('From') . ': ayse@example.com', $sent, 'an address alone');
	pg_assert_contains(date('d.m.Y H:i', 1800003600), $sent, 'an answer\'s time is when it left');
	pg_assert_true(strpos($sent, lang('To') . ':') === false, 'no empty To');

	$messages = ws_mail_forward_quote_messages('Messages from #sales', array(
		array('name' => 'Ali', 'time' => 1800000000, 'text' => "Price list\r\nattached", 'file_name' => 'prices.pdf'),
		array('name' => 'Ayşe', 'time' => 1800000060, 'text' => '', 'file_name' => 'photo.jpg'),
	));
	pg_assert_same("---------- Messages from #sales ----------\n\nAli · " . date('d.m.Y H:i', 1800000000) . "\nPrice list\nattached\n"
		. lang(array('string' => 'File: {var:1}', 'vars' => 'prices.pdf')) . "\n\nAyşe · " . date('d.m.Y H:i', 1800000060) . "\n"
		. lang(array('string' => 'File: {var:1}', 'vars' => 'photo.jpg')), $messages, 'each message as its writer, time, text and file');
}

function test_workspace_mail_forward_plain_keeps_lines()
{
	$viewer = array('id' => 1, 'role' => 0);

	pg_assert_same("Price list\nattached", ws_mail_forward_plain($viewer, "Price list\r\nattached", array()), 'a line the writer typed stays a line');
	pg_assert_same("Bold start\n\nafter a gap", ws_mail_forward_plain($viewer, "**Bold** start\n\n\n\nafter a gap", array()), 'marks come off, one blank line kept');
	pg_assert_same("☐ milk\n☑ bread", ws_mail_forward_plain($viewer, "- [ ] milk\n- [x] bread", array()), 'a checklist line by line');
	pg_assert_same('', ws_mail_forward_plain($viewer, "\n  \n", array()), 'nothing for blank lines');
}

function test_workspace_mail_forward_fill_and_request()
{
	$fill = ws_mail_forward_fill(array('ok' => true, 'kind' => 'mail', 'ids' => array(7), 'source' => 'mail:7', 'subject' => 'Fwd: Order 42', 'text' => 'quote',
		'files' => array(array('id' => 4, 'name' => 'a.pdf', 'size' => 10)), 'left' => array('big.zip'), 'channel_id' => 12, 'card' => array('id' => 100)));

	pg_assert_same(array('kind' => 'mail', 'ids' => array(7), 'source' => 'mail:7', 'subject' => 'Fwd: Order 42', 'text' => "\n\nquote",
		'files' => array(array('id' => 4, 'name' => 'a.pdf', 'size' => 10)), 'left' => array('big.zip'), 'channel_id' => 12), $fill, 'the person writes above the quote; the card stays on the server');

	pg_assert_same(array('ok' => true, 'error' => '', 'source' => null, 'files' => array()), ws_mail_forward_request(array('id' => 4, 'member' => true), array('to' => 'a@x.test')), 'a New e-mail carries no forward');
	pg_assert_same(array('ok' => true, 'error' => '', 'source' => null, 'files' => array()), ws_mail_forward_request(array('id' => 4, 'member' => true), array('forward_kind' => array('mail'))), 'a list is no kind');

	$source = workspace_mail_forward_test_source('includes/workspace/mail_forward.php');
	pg_assert_contains("\$source = ws_mail_forward_source(\$viewer, \$kind, \$request['forward_ids'] ?? '');", $source, 'the source is read again when sent');
	pg_assert_contains("if (!in_array((int) \$file['id'], \$kept, true)) {", $source, 'only the source\'s own files are read');
	pg_assert_contains("\$picked = ws_forward_pick(\$viewer, \$ids);", $source, 'messages the person reads, of one channel');
	pg_assert_contains("\$readable = \$readable || (\$channel && ws_can_read_channel(\$viewer, \$channel));", $source, 'a mail of a channel the person reads');
	pg_assert_contains("|| ws_mail_item_is_bounce(\$item)) {\n        return \$refused;", $source, 'never a delivery failure');
}

function test_workspace_mail_forward_line_under_the_source()
{
	$item = workspace_mail_forward_test_item(array('id' => 30, 'direction' => 'out', 'user_id' => 5, 'from_email' => 'support@shop.test', 'to_list' => 'boss@example.com',
		'message_id' => 210, 'send_state' => 'queued', 'conversation' => array('id' => 9, 'message_id' => 210, 'channel_id' => 14)));
	$line = array('id' => 205, 'file_id' => 0, 'body' => '');

	pg_assert_true(ws_mail_forward_is_line($line, $item), 'a message of the new mail that is neither its text nor a file');
	pg_assert_false(ws_mail_forward_is_line(array('id' => 210, 'file_id' => 0, 'body' => ''), $item), 'the new mail\'s own card');
	pg_assert_false(ws_mail_forward_is_line(array('id' => 211, 'file_id' => 8, 'body' => ''), $item), 'its file');
	pg_assert_false(ws_mail_forward_is_line($line, array_merge($item, array('direction' => 'in'))), 'an incoming mail');
	pg_assert_false(ws_mail_forward_is_line($line, array_merge($item, array('message_id' => 0))), 'a mail not written yet');
	pg_assert_false(ws_mail_forward_is_line($line, null));

	$html = ws_mail_card_html($line, $item);
	pg_assert_contains('ws-mail-forward-line', $html, 'the card draws the line');
	pg_assert_contains(h(lang(array('string' => 'Forwarded this e-mail to {var:1}', 'vars' => 'boss@example.com'))), $html, 'whom it went to');
	pg_assert_contains('href="workspace.php?channel=14&amp;message=210"', $html, 'the new card');
	pg_assert_contains(h(lang('Waiting to be sent')), $html, 'its state');
	pg_assert_true(strpos($html, 'Order 42') === false, 'not the mail itself');

	$source = workspace_mail_forward_test_source('includes/workspace/mail_forward.php');
	pg_assert_contains("if (!\$channel || ((int) \$channel['archived_at'] > 0) || !ws_can_read_channel(\$viewer, \$channel)) {\n        return 0;", $source, 'not into an archived or unread channel');
	pg_assert_contains("\$line = ws_mail_message_write((int) \$channel['id'], \$parent, (int) \$item_id, '', 0, '', time());", $source, 'a message of the new mail');
}

function test_workspace_mail_forward_who_is_offered()
{
	$member = array('id' => 4, 'member' => true);
	$item = workspace_mail_forward_test_item();
	$card = array('id' => 100, 'file_id' => 0);

	pg_assert_same(7, ws_mail_forward_offer($card, $item, $member), 'a mail\'s own text');
	pg_assert_same(0, ws_mail_forward_offer($card, $item, array('id' => 4, 'member' => false)), 'not a guest');
	pg_assert_same(0, ws_mail_forward_offer(array('id' => 101, 'file_id' => 0), $item, $member), 'not another message of it');
	pg_assert_same(0, ws_mail_forward_offer(array('id' => 100, 'file_id' => 3), $item, $member), 'not a file');
	pg_assert_same(0, ws_mail_forward_offer($card, array_merge($item, array('conversation_id' => 0, 'user_id' => 5)), $member), 'not a delivery failure');
	pg_assert_same(0, ws_mail_forward_offer($card, null, $member));
	pg_assert_false(ws_mail_forward_may(array('id' => 4, 'member' => false)), 'a guest has no mailbox');

	$pane = ws_mail_forward_pane(array('items' => array(array('id' => 1, 'message_id' => 100, 'direction' => 'in', 'conversation_id' => 3), array('id' => 2, 'message_id' => 0, 'direction' => 'out'))), true);
	pg_assert_same(array(true, false), array($pane['items'][0]['forward'], $pane['items'][1]['forward']), 'a mail with its message');
	pg_assert_false(ws_mail_forward_pane($pane, false)['items'][0]['forward'], 'nothing for whoever may not send');

	$html = ws_mail_pane_item_html(array_merge(workspace_mail_forward_test_item(), array('files' => array(), 'bounces' => array(), 'forward' => true)));
	pg_assert_contains('data-ws-mail-forward="7"', $html, 'the pane\'s button');
	pg_assert_true(strpos(ws_mail_pane_item_html(array_merge(workspace_mail_forward_test_item(), array('files' => array(), 'bounces' => array()))), 'data-ws-mail-forward') === false, 'none unmarked');
}

function test_workspace_mail_forward_wiring()
{
	$actions = workspace_mail_forward_test_source('includes/workspace/actions.php');
	$case = substr($actions, (int) strpos($actions, "case 'ws_mail_forward':"), 1200);

	pg_assert_contains("if (empty(\$viewer['member'])) {\n                return ws_action_error(ws_mail_send_error_text('no_box'));", $case, 'a guest is refused');
	pg_assert_contains("'boxes'   => ws_mail_compose_boxes(ws_mail_send_boxes(\$viewer)),", $case, 'the boxes the person may send from');
	pg_assert_contains("'forward' => ws_mail_forward_fill(\$source),", $case, 'what the form is filled with');

	$new = substr($actions, (int) strpos($actions, "case 'ws_mail_new':"), 2000);
	pg_assert_contains("\$forward = ws_mail_forward_request(\$viewer, \$request);", $new, 'a forward is read again when sent');
	pg_assert_contains("ws_mail_compose_mail(\$request, array_merge(\$forward['files'], \$files['files']));", $new, 'its files go with the mail, through the one send path');
	pg_assert_contains("ws_mail_forward_line(\$viewer, \$forward['source'], (int) \$result['item_id']);", $new, 'the line under the source once sent');
	pg_assert_contains("\$pane = ws_mail_forward_pane(\$pane, ws_mail_forward_may(\$viewer));", $actions, 'the pane marks its mails');

	pg_assert_contains("'mail_forward' => (\$mail && !\$deleted && function_exists('ws_mail_forward_offer'))", workspace_mail_forward_test_source('includes/workspace/messages.php'), 'each message says');
	$screen = workspace_mail_forward_test_source('includes/workspace/screen.php');
	pg_assert_contains("'mail_forward' => (\$mode === 'channels') && function_exists('ws_mail_forward_may') && ws_mail_forward_may(\$viewer),", $screen, 'the channel screen is told');
	pg_assert_contains('ws_mail_forward_js_strings()', $screen, 'its words');

	$js = workspace_mail_forward_test_source('assets/js/workspace.js');
	pg_assert_contains("window.PGWsMail.forward(featureHelpers(), { kind: 'messages', ids: ids }", $js, 'the chosen-messages bar');
	pg_assert_contains("message.mail_forward ? { kind: 'mail', ids: [message.mail_forward] } : { kind: 'messages', ids: [message.id] }", $js, 'the message menu');

	$mail = workspace_mail_forward_test_source('assets/js/workspace_mail.js');
	pg_assert_contains("h.api('ws_mail_forward', { kind: source.kind, ids: source.ids.join(',') })", $mail, 'the form asks for the fill');
	pg_assert_contains("post.forward_files = carried.map(function (file) { return file.id; }).join(',');", $mail, 'Send names the files still carried');
	pg_assert_contains("[data-ws-mail-forward]", $mail, 'the pane\'s button');
	pg_assert_contains("if (message.mail_forward && h.cfg.mail_forward) {", $mail, 'the card\'s button');
}

// The Reply by e-mail drawer and New e-mail each keep their own function:
// two of one name in one scope left the later one in place of both.
function test_workspace_mail_forward_reply_drawer_keeps_its_name()
{
	$mail = workspace_mail_forward_test_source('assets/js/workspace_mail.js');

	pg_assert_same(1, substr_count($mail, 'function compose('), 'one compose()');
	pg_assert_same(1, substr_count($mail, 'function replyForm(message, view, h, draft) {'), 'the drawer');
	pg_assert_same(3, substr_count($mail, 'replyForm(') - 1, 'the card, the AI draft and the pane open it');

	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);

	if ($code !== 0) {
		return;
	}

	$now = 1800000000000;
	$script = "'use strict';\nvar window = {};\n" . $mail
		. "\nvar M = window.PGWsMail;\nconsole.log(JSON.stringify(["
		. "M.compose.length, typeof M.forward,"
		. "M.newDraftFresh(JSON.stringify({box: 3, channel: 12, to: '', subject: 'Fwd: Hi', text: 'x', source: 'mail:7', at: " . $now . "}), " . $now . ")"
		. "]));\n";
	$file = tempnam(sys_get_temp_dir(), 'pgwsmailfwd');
	file_put_contents($file, $script);
	$out = array();
	exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	unlink($file);
	$answer = json_decode(implode("\n", $out), true);

	pg_assert_true(is_array($answer), 'Node printed JSON: ' . implode("\n", $out));
	pg_assert_same(2, $answer[0], 'compose(h, options) is New e-mail\'s');
	pg_assert_same('function', $answer[1]);
	pg_assert_same('mail:7', $answer[2]['source'], 'a kept forward names its source');
}
