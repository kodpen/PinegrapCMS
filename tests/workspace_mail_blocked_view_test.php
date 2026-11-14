<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the "Blocked" view of the E-mails screen and "Not blocked"
 * (includes/workspace/mail_blocks.php): the view word, who may bring a
 * blocked mail in, its Site Log line, the rows, and the wiring into the
 * ingest, the screen, the panel action and the script. No database.
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

function workspace_mail_blocked_view_test_source($relative)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $relative);
}

function test_workspace_mail_blocked_view_word()
{
	pg_assert_same('blocked', ws_mail_list_filters(array('view' => 'blocked'))['view'], 'a view of its own');
	pg_assert_same('incoming', ws_mail_list_filters(array('view' => 'nonsense'))['view'], 'an unknown word stays Incoming');
}

function test_workspace_mail_unblock_decide()
{
	$item = array('id' => 7, 'box_id' => 2, 'direction' => 'in', 'blocked_at' => 100, 'raw_path' => 'mail/2/a.eml');

	pg_assert_true(ws_mail_unblock_decide($item, array(2, 3))['ok'], 'a blocked mail of a box the person may answer in');
	pg_assert_same(lang('Only someone who may answer in this mailbox can bring this e-mail in.'), ws_mail_unblock_decide($item, array(3))['error'], 'another box');
	pg_assert_same(lang('This e-mail is no longer blocked.'), ws_mail_unblock_decide(array_merge($item, array('blocked_at' => 0)), array(2))['error'], 'already brought in');
	pg_assert_same(lang('This e-mail is no longer blocked.'), ws_mail_unblock_decide(null, array(2))['error'], 'gone');
	pg_assert_same(lang('The original of this e-mail is no longer kept, so it cannot be brought in.'),
		ws_mail_unblock_decide(array_merge($item, array('raw_path' => '')), array(2))['error'], 'no .eml');
	pg_assert_same(lang('The original of this e-mail is no longer kept, so it cannot be brought in.'),
		ws_mail_unblock_decide(array_merge($item, array('raw_path' => 'mail/../x.eml')), array(2))['error'], 'a path outside the mail folder');
}

function test_workspace_mail_unblock_log_line()
{
	pg_assert_same(lang(array('string' => 'a blocked e-mail from {var:1} was brought into {var:2}', 'vars' => array('a@x.com', 'Support'))),
		ws_mail_unblock_log_line('a@x.com', 'Support'));
}

function test_workspace_mail_blocked_html()
{
	$now = 1800000000;
	$items = array(
		array('id' => 9, 'box_id' => 1, 'from_email' => 'spam@x.com', 'from_name' => 'Spam <Co>', 'subject' => 'Offer', 'box_name' => 'Support', 'created_at' => $now - 60, 'raw_path' => 'mail/1/a.eml'),
		array('id' => 8, 'box_id' => 1, 'from_email' => 'b@y.com', 'from_name' => '', 'subject' => '', 'box_name' => 'Sales', 'created_at' => $now - 7200, 'raw_path' => ''),
	);
	$html = ws_mail_blocked_html($items, $now);

	pg_assert_contains('data-ws-mail-unblock="9"', $html, 'Not blocked per row');
	pg_assert_true(strpos($html, 'data-ws-mail-unblock="8"') === false, 'none without its original');
	pg_assert_contains('Spam &lt;Co&gt;', $html, 'the name, escaped');
	pg_assert_contains('b@y.com', $html, 'the address without a name');
	pg_assert_contains(h(lang('(no subject)')), $html, 'no subject');
	pg_assert_contains(h(lang('Not blocked')), $html, 'the button');
	pg_assert_contains(h(lang(array('string' => 'Blocked e-mails are kept for {var:1} days, then deleted. "Not blocked" brings one in as a conversation; the sender stays blocked.', 'vars' => WS_MAIL_BLOCK_DAYS))), $html, 'what the view is');
	pg_assert_contains(h(lang('No blocked e-mail.')), ws_mail_blocked_html(array(), $now), 'empty');
}

function test_workspace_mail_blocked_view_wiring()
{
	pg_assert_contains("if (\$blocks && empty(\$options['past_block']) && ws_mail_block_find(", workspace_mail_blocked_view_test_source('includes/workspace/mail.php'), 'the ingest can pass the block');

	$blocks = workspace_mail_blocked_view_test_source('includes/workspace/mail_blocks.php');
	pg_assert_contains("ws_mail_ingest(\$raw, \$box, array('past_block' => true))", $blocks, 'Not blocked takes the .eml in past the block');
	pg_assert_contains('DELETE FROM ws_mail_items WHERE id = ? AND blocked_at > 0', $blocks, 'the kept row goes first');
	pg_assert_contains('log_activity(ws_mail_unblock_log_line(', $blocks, 'a Site Log line');

	$actions = workspace_mail_blocked_view_test_source('includes/workspace/actions.php');
	pg_assert_contains("case 'ws_mail_unblock':", $actions, 'its action');
	pg_assert_contains("'ws_mail_unblock'", workspace_mail_blocked_view_test_source('includes/panel/actions.php'), 'a panel action');

	$screen = workspace_mail_blocked_view_test_source('messages_mail.php');
	pg_assert_contains("(\$filters['view'] === 'blocked')", $screen, 'the screen draws the view');
	pg_assert_contains('ws_mail_blocked_html(', $screen, 'its rows');

	pg_assert_contains("'blocked' => array(lang('Blocked e-mail'), 'slash-circle')", workspace_mail_blocked_view_test_source('includes/workspace/mail_list.php'), 'a view on the toolbar');
	pg_assert_contains("h.api('ws_mail_unblock'", workspace_mail_blocked_view_test_source('assets/js/workspace_mail.js'), 'the script posts it');
}
