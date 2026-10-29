<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Workspace E-mails list (includes/workspace/mail_list.php,
 * workspace_mail.php): the filters read from the address, the query's
 * conditions, the table and the filter links. Reading the conversations
 * and the channels needs the database and is not run here.
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

function test_workspace_mail_list_filters()
{
	pg_assert_same(array('status' => 'open', 'holder' => 'all', 'overdue' => false), ws_mail_list_filters(array()), 'open conversations of anybody');
	pg_assert_same(array('status' => 'all', 'holder' => 'me', 'overdue' => true), ws_mail_list_filters(array('status' => 'all', 'holder' => 'me', 'overdue' => '1')));
	pg_assert_same(array('status' => 'open', 'holder' => 'all', 'overdue' => false), ws_mail_list_filters(array('status' => 'spam', 'holder' => array('me'), 'overdue' => 'yes')), 'unknown words fall back');
}

function test_workspace_mail_list_where()
{
	$filters = ws_mail_list_filters(array());

	pg_assert_same('', ws_mail_list_where($filters, 7, array()), 'no channel read: nothing');
	pg_assert_same("channel_id IN (3,5) AND message_id > 0 AND status = 'open'", ws_mail_list_where($filters, 7, array(3, '5', 0)));
	pg_assert_same("channel_id IN (3) AND message_id > 0 AND assignee_id = '7'", ws_mail_list_where(array('status' => 'all', 'holder' => 'me', 'overdue' => false), 7, array(3)));
	pg_assert_same("channel_id IN (3) AND message_id > 0 AND status = 'waiting' AND assignee_id = 0", ws_mail_list_where(array('status' => 'waiting', 'holder' => 'none', 'overdue' => false), 7, array(3)));
	pg_assert_same("channel_id IN (3) AND message_id > 0 AND first_reply_at = 0 AND status <> 'closed'", ws_mail_list_where(array('status' => 'all', 'holder' => 'all', 'overdue' => true), 7, array(3)), 'only the unanswered can be overdue');
}

function test_workspace_mail_list_html()
{
	pg_assert_contains(h(lang('No e-mail conversation matches.')), ws_mail_list_html(array(), '/pg/', 1000));

	$row = array('id' => 4, 'channel_id' => 3, 'message_id' => 90, 'subject' => 'Order <late>', 'customer_name' => 'Ayşe', 'customer_email' => 'ayse@example.com',
		'status' => 'open', 'assignee_id' => 7, 'assignee_name' => 'Deniz', 'first_in_at' => 100, 'first_reply_at' => 0, 'last_in_at' => 100, 'last_out_at' => 0,
		'box_name' => 'Support', 'channel_name' => 'support', 'first_reply_hours' => 1, 'overdue' => true);
	$html = ws_mail_list_html(array($row), '/pg/', 100000);

	pg_assert_contains('href="/pg/workspace.php?channel=3&amp;message=90"', $html, 'opens the card');
	pg_assert_contains('Order &lt;late&gt;', $html, 'the subject as text');
	pg_assert_contains('ayse@example.com', $html);
	pg_assert_contains('Deniz', $html);
	pg_assert_contains(h(lang('First answer overdue')), $html);
	pg_assert_contains('class="ws-mail-overdue"', $html);
	pg_assert_contains('#support', $html);
	pg_assert_contains(h(lang('(no subject)')), ws_mail_list_html(array(array_merge($row, array('subject' => ' ', 'overdue' => false))), '/pg/', 100));
}

function test_workspace_mail_list_filter_links()
{
	$html = ws_mail_list_filters_html(array('status' => 'waiting', 'holder' => 'me', 'overdue' => false));

	pg_assert_contains('href="workspace_mail.php?status=closed&amp;holder=me"', $html, 'a status keeps the holder');
	pg_assert_contains('href="workspace_mail.php?status=waiting&amp;holder=me&amp;overdue=1"', $html, 'overdue switched on');
	pg_assert_contains('aria-current="true" href="workspace_mail.php?status=waiting&amp;holder=me"', $html, 'the chosen one');
	pg_assert_contains('href="workspace_mail.php?status=waiting&amp;holder=me"', ws_mail_list_filters_html(array('status' => 'waiting', 'holder' => 'me', 'overdue' => true)), 'overdue switched off');
}

function test_workspace_mail_list_wiring()
{
	$page = (string) file_get_contents(PG_FUNCTIONS_DIR . '/workspace_mail.php');
	$list = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/mail_list.php');

	pg_assert_contains('$viewer = ws_screen_gate($user);', $page, 'members only');
	pg_assert_contains('ws_mail_list($viewer, $filters, $now)', $page);
	pg_assert_contains('if ($channel && ws_can_read_channel($viewer, $channel)) {', $list, 'a channel decides, not a box');
	pg_assert_same('mail', ws_side_screen('/pinegrap/workspace_mail.php'));
}
