<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Workspace E-mails screen (includes/workspace/mail_list.php,
 * mail_pane.php, mail_reads.php, workspace_mail.php): the filters read from
 * the address, the query's conditions, the rows, Load more, the toolbar,
 * the reading pane, the unread rule and the wiring. Reading the
 * conversations, the channels and the marks needs the database and is not
 * run here.
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

// The filters of a list, defaults but for the changes.
function workspace_mail_list_test_filters($changes = array())
{
	return array_merge(array('view' => 'all', 'status' => 'all', 'holder' => 'all', 'overdue' => false, 'box' => 0, 'q' => '', 'before' => null), $changes);
}

function test_workspace_mail_list_filters()
{
	pg_assert_same(array('view' => 'incoming', 'status' => 'open', 'holder' => 'all', 'overdue' => false, 'box' => 0, 'q' => '', 'before' => null),
		ws_mail_list_filters(array()), 'open incoming conversations of anybody');
	pg_assert_same(array('view' => 'incoming', 'status' => 'all', 'holder' => 'me', 'overdue' => true, 'box' => 2, 'q' => 'kargo', 'before' => array(500, 9)),
		ws_mail_list_filters(array('status' => 'all', 'holder' => 'me', 'overdue' => '1', 'box' => '2', 'q' => '  kargo ', 'before' => '500-9')));
	pg_assert_same(array('view' => 'incoming', 'status' => 'open', 'holder' => 'all', 'overdue' => false, 'box' => 0, 'q' => '', 'before' => null),
		ws_mail_list_filters(array('view' => 'spam', 'status' => 'spam', 'holder' => array('me'), 'overdue' => 'yes', 'box' => '-1', 'q' => array('x'), 'before' => '1-2-3')), 'unknown words fall back');

	pg_assert_same('all', ws_mail_list_filters(array('view' => 'sent'))['status'], 'Sent shows every status by default');
	pg_assert_same('all', ws_mail_list_filters(array('view' => 'unsent'))['status'], 'so does Not sent');
	pg_assert_same(WS_MAIL_LIST_QUERY_MAX, mb_strlen(ws_mail_list_filters(array('q' => str_repeat('ş', 300)))['q']), 'a long search is cut');
}

function test_workspace_mail_list_cursor()
{
	pg_assert_same(array(1700000000, 42), ws_mail_list_cursor('1700000000-42'));
	pg_assert_same(null, ws_mail_list_cursor(''));
	pg_assert_same(null, ws_mail_list_cursor('5-x'));
	pg_assert_same(null, ws_mail_list_cursor(array('5-1')));
}

function test_workspace_mail_list_query()
{
	pg_assert_same('', ws_mail_list_query(ws_mail_list_filters(array())), 'defaults leave the address bare');
	pg_assert_same('view=sent&holder=me&box=2&q=kargo&before=500-9', ws_mail_list_query(ws_mail_list_filters(array('view' => 'sent', 'holder' => 'me', 'box' => '2', 'q' => 'kargo')), array('before' => '500-9')));
	pg_assert_same('view=unsent&status=open', ws_mail_list_query(ws_mail_list_filters(array('view' => 'unsent', 'status' => 'open'))), 'a status other than the view\'s default stays');
}

function test_workspace_mail_list_where()
{
	pg_assert_same('', ws_mail_list_where(ws_mail_list_filters(array()), 7, array()), 'no channel read: nothing');
	pg_assert_same("channel_id IN (3,5) AND message_id > 0 AND status = 'open' AND last_in_at > 0", ws_mail_list_where(ws_mail_list_filters(array()), 7, array(3, '5', 0)), 'Incoming');
	pg_assert_same("channel_id IN (3) AND message_id > 0 AND assignee_id = '7'", ws_mail_list_where(workspace_mail_list_test_filters(array('holder' => 'me')), 7, array(3)));
	pg_assert_same("channel_id IN (3) AND message_id > 0 AND status = 'waiting' AND assignee_id = 0", ws_mail_list_where(workspace_mail_list_test_filters(array('status' => 'waiting', 'holder' => 'none')), 7, array(3)));
	pg_assert_same("channel_id IN (3) AND message_id > 0 AND first_reply_at = 0 AND status <> 'closed'", ws_mail_list_where(workspace_mail_list_test_filters(array('overdue' => true)), 7, array(3)), 'only the unanswered can be overdue');
	pg_assert_same("channel_id IN (3) AND message_id > 0 AND box_id = '2'", ws_mail_list_where(workspace_mail_list_test_filters(array('box' => 2)), 7, array(3)), 'one mailbox');

	$sent = ws_mail_list_where(workspace_mail_list_test_filters(array('view' => 'sent')), 7, array(3));
	pg_assert_contains("i.direction = 'out' AND i.send_state IN ('', 'sent'))", $sent, 'Sent: an answer that left, or one from before the send state');
	pg_assert_contains("i.send_state IN ('queued', 'failed'))", ws_mail_list_where(workspace_mail_list_test_filters(array('view' => 'unsent')), 7, array(3)), 'Not sent: one waiting or refused');

	$search = ws_mail_list_where(workspace_mail_list_test_filters(array('q' => "kar'go")), 7, array(3), '');
	pg_assert_contains("subject LIKE '%kar\\'go%'", $search, 'the subject, escaped');
	pg_assert_contains("customer_email LIKE", $search, 'the customer');
	pg_assert_contains("EXISTS (SELECT 1 FROM ws_mail_items i WHERE i.conversation_id = ws_mail_conversations.id AND (", $search, 'or one of its mails');
	pg_assert_contains("i.body_text LIKE '%kar\\'go%'", $search, 'by its text');

	pg_assert_contains("(GREATEST(last_in_at, last_out_at) < '500' OR (GREATEST(last_in_at, last_out_at) = '500' AND id < '9'))",
		ws_mail_list_where(workspace_mail_list_test_filters(array('before' => array(500, 9))), 7, array(3)), 'the next page after the cursor');
}

// A row of ws_mail_list().
function workspace_mail_list_test_row($changes = array())
{
	return array_merge(array('id' => 4, 'channel_id' => 3, 'message_id' => 90, 'subject' => 'Order <late>', 'customer_name' => 'Ayşe', 'customer_email' => 'ayse@example.com',
		'status' => 'open', 'assignee_id' => 7, 'assignee_name' => 'Deniz', 'first_in_at' => 100, 'first_reply_at' => 0, 'last_in_at' => 100, 'last_out_at' => 0,
		'box_name' => 'Support', 'channel_name' => 'support', 'first_reply_hours' => 1, 'overdue' => true,
		'mails' => 3, 'snippet' => 'Where is <it>?', 'unread' => true, 'unsent' => ''), $changes);
}

function test_workspace_mail_list_rows_html()
{
	$html = ws_mail_list_rows_html(array(workspace_mail_list_test_row()), '/pg/', 100000);

	pg_assert_contains('<a href="/pg/workspace.php?channel=3&amp;message=90" class="ws-mail-row ws-mail-unread ws-mail-overdue" data-ws-mail-row="4">', $html, 'opens the card without the script');
	pg_assert_contains('Order &lt;late&gt;', $html, 'the subject as text');
	pg_assert_contains('Where is &lt;it&gt;?', $html, 'the newest mail\'s words');
	pg_assert_contains('<span class="ws-mail-who">Ayşe</span><span class="visually-hidden">' . h(lang('Unread')) . '</span>', $html, 'unread, said to a screen reader');
	pg_assert_contains('>3</span>', $html, 'how many mails');
	pg_assert_contains('Deniz', $html);
	pg_assert_contains(h(lang('First answer overdue')), $html);
	pg_assert_contains('#support', $html);
	pg_assert_same(false, strpos($html, h(lang('Not sent'))), 'nothing waits');

	$read = ws_mail_list_rows_html(array(workspace_mail_list_test_row(array('subject' => ' ', 'customer_name' => '', 'overdue' => false, 'unread' => false, 'mails' => 1, 'unsent' => 'failed'))), '/pg/', 100);
	pg_assert_contains('class="ws-mail-row" data-ws-mail-row="4"', $read, 'read and in time');
	pg_assert_contains(h(lang('(no subject)')), $read);
	pg_assert_contains('<span class="ws-mail-who">ayse@example.com</span>', $read, 'the address without a name');
	pg_assert_contains('<span class="badge theme-danger">' . h(lang('Not sent')) . '</span>', $read);
	pg_assert_contains(h(lang('Waiting to be sent')), ws_mail_list_rows_html(array(workspace_mail_list_test_row(array('unsent' => 'waiting'))), '/pg/', 100));
}

function test_workspace_mail_list_html_and_more()
{
	$filters = ws_mail_list_filters(array('view' => 'sent', 'q' => 'kargo'));

	pg_assert_contains(h(lang('No e-mail conversation matches.')), ws_mail_list_html(array('rows' => array(), 'next' => ''), $filters, '/pg/', 1000));

	$html = ws_mail_list_html(array('rows' => array(workspace_mail_list_test_row()), 'next' => '500-9'), $filters, '/pg/', 1000);
	pg_assert_contains('<div class="ws-mail-rows" data-ws-mail-rows>', $html);
	pg_assert_contains('data-ws-mail-more href="workspace_mail.php?view=sent&amp;q=kargo&amp;before=500-9">' . h(lang('Load more')) . '</a>', $html, 'the next page keeps the view and search');
	pg_assert_same('', ws_mail_list_more_html($filters, ''), 'the last page');
}

function test_workspace_mail_list_filter_links()
{
	$saved = $_GET;
	$_GET = array('status' => 'waiting', 'holder' => 'me');
	$html = ws_mail_list_filters_html(ws_mail_list_filters($_GET));

	// One toolbar: Status and Held by are selects, each keeping the other.
	pg_assert_contains('<nav id="button_bar" class="pg-toolbar"', $html, 'the screen toolbar');
	pg_assert_same(false, strpos($html, 'rounded-pill"'), 'no pill rows');
	pg_assert_true((bool) preg_match('/<select id="pg_filter_status" name="status"[^>]*>/', $html), 'status select');
	pg_assert_contains('<option value="waiting" selected>', $html, 'the chosen status');
	pg_assert_contains('<option value="me" selected>', $html, 'the chosen holder');
	pg_assert_contains('<input type="hidden" name="holder" value="me">', $html, 'a status keeps the holder');
	pg_assert_contains('<input type="hidden" name="status" value="waiting">', $html, 'a holder keeps the status');
	pg_assert_same(false, strpos($html, 'name="box"'), 'one mailbox: no mailbox filter');
	pg_assert_contains('aria-pressed="false" href="workspace_mail.php?status=waiting&amp;holder=me&amp;overdue=1"', $html, 'overdue switched on');

	// The views: each starts with its own status and keeps the holder.
	pg_assert_contains('active" aria-current="page" href="workspace_mail.php?holder=me">', $html, 'Incoming is the view');
	pg_assert_contains('href="workspace_mail.php?view=sent&amp;holder=me">', $html, 'Sent');
	pg_assert_contains('href="workspace_mail.php?view=unsent&amp;holder=me">', $html, 'Not sent');

	// The search keeps the other words; Mark all as read is a button for the script.
	pg_assert_contains('<input type="search" class="form-ghost" name="q" value=""', $html);
	pg_assert_contains('data-ws-mail-read-all>', $html);

	$_GET = array('status' => 'waiting', 'holder' => 'me', 'overdue' => '1', 'box' => '2');
	$html = ws_mail_list_filters_html(ws_mail_list_filters($_GET), array(1 => 'Sales', 2 => 'Support'));
	pg_assert_contains('aria-pressed="true" href="workspace_mail.php?status=waiting&amp;holder=me&amp;box=2"', $html, 'overdue switched off');
	pg_assert_contains('<input type="hidden" name="overdue" value="1">', $html, 'a select keeps overdue');
	pg_assert_contains('<option value="2" selected>Support</option>', $html, 'two mailboxes: the filter');
	pg_assert_contains('<input type="hidden" name="box" value="2">', $html, 'the search keeps the mailbox');
	$_GET = $saved;
}

function test_workspace_mail_unread_rule()
{
	pg_assert_true(ws_mail_unread(10, 5, 8), 'a mail past the mark and the floor');
	pg_assert_false(ws_mail_unread(8, 5, 8), 'the floor covers it');
	pg_assert_false(ws_mail_unread(10, 12, 0), 'the mark covers it');
	pg_assert_false(ws_mail_unread(0, 0, 0), 'no incoming mail');

	$sql = ws_mail_unread_sql(7, 30);
	pg_assert_contains("(SELECT MAX(i.id) FROM ws_mail_items i WHERE i.conversation_id = ws_mail_conversations.id AND i.direction = 'in') > GREATEST('30', ", $sql);
	pg_assert_contains("r.user_id = '7'), 0))", $sql);

	pg_assert_same(array(11, 12), ws_mail_read_item_ids(array(
		array('sender_kind' => 'mail', 'sender_id' => 11), array('sender_kind' => 'user', 'sender_id' => 5),
		array('sender_kind' => 'mail', 'sender_id' => 12), array('sender_kind' => 'mail', 'sender_id' => 11), array('sender_kind' => 'mail', 'sender_id' => 0),
	)), 'the mails behind the shown messages, once each');
}

function test_workspace_mail_pane_html()
{
	$item = array('id' => 1, 'direction' => 'in', 'from_name' => 'Ayşe', 'from_email' => 'ayse@example.com', 'to_list' => 'support@example.com', 'cc_list' => '',
		'body_text' => "Hello <b>\nthere", 'refused_files' => "virus.exe", 'user_id' => 0, 'sent_at' => 0, 'created_at' => 100, 'send_state' => '', 'send_error' => '',
		'files' => array(array('name' => 'bill.pdf', 'url' => '/files/bill.pdf')), 'bounces' => array());
	$out = array_merge($item, array('id' => 2, 'direction' => 'out', 'from_name' => 'Deniz', 'from_email' => 'support@example.com', 'to_list' => 'ayse@example.com',
		'cc_list' => 'boss@example.com', 'body_text' => 'On its way.', 'refused_files' => '', 'user_id' => 7, 'send_state' => 'failed', 'send_error' => 'Mailbox full',
		'files' => array(), 'bounces' => array(array('to_list' => 'ayse@example.com', 'body_text' => '5.2.2 full'))));
	$pane = array(
		'conversation' => array('id' => 4, 'channel_id' => 3, 'message_id' => 90, 'subject' => 'Order <late>', 'customer_name' => 'Ayşe', 'customer_email' => 'ayse@example.com'),
		'channel'      => array('id' => 3, 'name' => 'support'),
		'box_name'     => 'Support',
		'can_post'     => true,
		'items'        => array($item, $out, array_merge($out, array('id' => 3, 'user_id' => 0, 'send_state' => 'queued', 'bounces' => array())),
			array_merge($out, array('id' => 5, 'send_state' => 'sent', 'send_error' => '', 'bounces' => array()))),
		'last_id'      => 3,
	);
	$html = ws_mail_pane_html($pane, '/pg/');

	pg_assert_contains('<h2 class="h5 mb-1" tabindex="-1">Order &lt;late&gt;</h2>', $html, 'the subject as text');
	pg_assert_contains('Ayşe &lt;ayse@example.com&gt; · Support · #support', $html, 'customer, mailbox, channel');
	pg_assert_contains('href="/pg/workspace.php?channel=3&amp;message=90"', $html, 'the card in its channel');
	pg_assert_contains('<article class="ws-mail-item ws-mail-in">', $html);
	pg_assert_contains('<article class="ws-mail-item ws-mail-out">', $html);
	pg_assert_contains('Hello &lt;b&gt;<br>' . "\n" . 'there', $html, 'the text escaped, its lines kept');
	pg_assert_contains('<a href="/files/bill.pdf" target="_blank" rel="noopener">bill.pdf</a>', $html, 'its files');
	pg_assert_contains(h(lang(array('string' => 'Not taken in: {var:1}', 'vars' => 'virus.exe'))), $html, 'the files not taken in');
	pg_assert_contains(h(lang('Cc')) . ': boss@example.com', $html);
	pg_assert_contains(h(lang('Not sent')) . '</span> <span class="ms-1">Mailbox full</span>', $html, 'why it was not sent');
	pg_assert_same(false, strpos($html, h(lang('Sent by e-mail'))), 'a mail that left gets no badge');
	pg_assert_contains(h(lang(array('string' => 'Not delivered to {var:1}.', 'vars' => 'ayse@example.com'))) . ' 5.2.2 full', $html, 'the delivery failure under its answer');
	pg_assert_contains(h(lang('Waiting to be sent')), $html);
	pg_assert_contains(h(lang('Automatic answer')), $html, 'the box\'s own answer');
	pg_assert_same(false, strpos($html, h(lang(array('string' => 'The newest {var:1} e-mails are shown; the channel has them all.', 'vars' => WS_MAIL_PANE_MAX)))), 'all of them are shown');
}

function test_workspace_mail_side_count()
{
	$nav = ws_side_nav(array('tools' => array(
		array('key' => 'mail', 'label' => 'E-mails', 'icon' => 'bi-envelope', 'url' => '/m', 'count' => 3),
		array('key' => 'tasks', 'label' => 'My Tasks', 'icon' => 'bi-check2-square', 'url' => '/t'),
	)));

	pg_assert_same(3, $nav['tools'][0]['count'], 'the unread number on the tile');
	pg_assert_false(isset($nav['tools'][1]['count']), 'no number, no key');

	$groups = ws_nav_groups(array('role' => 1, 'settings' => true), array('base' => '/pg/', 'mail' => true, 'mail_unread' => 5));
	$mail = array_values(array_filter($groups['tools'], function ($item) {
		return $item['key'] === 'mail';
	}));
	pg_assert_same(5, $mail[0]['count']);
}

function test_workspace_mail_list_wiring()
{
	$page = (string) file_get_contents(PG_FUNCTIONS_DIR . '/workspace_mail.php');
	$list = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/mail_list.php');
	$actions = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/actions.php');
	$script = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace_mail.js');
	$boot = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace.js');
	$side = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace_side.js');

	pg_assert_contains('$viewer = ws_screen_gate($user);', $page, 'members only');
	pg_assert_contains('ws_mail_list($viewer, $filters, $now)', $page);
	pg_assert_contains("ws_screen_assets(\$viewer, 'mail', array('mail' => array('strings' => ws_mail_screen_js_strings())))", $page, 'the script and its words');
	pg_assert_contains('data-ws-mail-pane', $page, 'the pane beside the list');
	pg_assert_contains('if ($channel && ws_can_read_channel($viewer, $channel)) {', $list, 'a channel decides, not a box');
	pg_assert_same('mail', ws_side_screen('/pinegrap/workspace_mail.php'));

	pg_assert_contains("case 'ws_mail_pane':", $actions);
	pg_assert_contains("case 'ws_mail_rows':", $actions);
	pg_assert_contains("case 'ws_mail_read_all':", $actions);
	pg_assert_same(2, substr_count($actions, 'ws_mail_read_rows($viewer, $rows);'), 'a card shown in its channel is read, on opening and on the next sync');
	pg_assert_contains('\'view\'   => (string) ($params[\'view\'] ?? \'all\'),', (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/api_mail.php'), 'the API keeps every conversation unless a view is asked');

	pg_assert_contains("window.PGWsMail.screen(root, featureHelpers());", $boot, 'the screen starts the list');
	pg_assert_contains("h.api('ws_mail_pane', { conversation_id: id })", $script);
	pg_assert_contains("compose(message, view, h);", $script, 'Reply still opens the drawer');
	pg_assert_contains('node.appendChild(countBadge(item.count, false));', $side, 'the tile draws the number');
}
