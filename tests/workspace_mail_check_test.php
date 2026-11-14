<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for Check e-mail on Communication › Incoming e-mail
 * (includes/workspace/mail_check.php): which boxes a person reads, the
 * 15-second gap per box, the presses a minute per person, the line the list
 * shows after a check, the panel action and the toolbar button.
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
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/mail_check.php');

function test_workspace_mail_check_reads_box()
{
	$box = array('channel_id' => 7, 'settings' => json_encode(array('host' => 'imap.example.com', 'routes' => array(array('match' => '+sales', 'channel_id' => 9)))));

	pg_assert_true(ws_mail_check_reads_box($box, array(7)), 'the box\'s own channel');
	pg_assert_true(ws_mail_check_reads_box($box, array(9)), 'a route\'s channel');
	pg_assert_false(ws_mail_check_reads_box($box, array(8)), 'neither');
	pg_assert_false(ws_mail_check_reads_box(array('channel_id' => 0, 'settings' => '{}'), array(0)), 'no channel at all');
}

function test_workspace_mail_check_due()
{
	$now = 1000000;

	pg_assert_true(ws_mail_check_due(array('checked_at' => 0), $now), 'never read');
	pg_assert_true(ws_mail_check_due(array('checked_at' => $now - 15), $now), 'read 15 seconds ago');
	pg_assert_false(ws_mail_check_due(array('checked_at' => $now - 14), $now), 'read 14 seconds ago');
	pg_assert_false(ws_mail_check_due(array('checked_at' => $now + 3), $now), 'a clock ahead');
	pg_assert_same(15, WS_MAIL_CHECK_GAP, 'the gap');
}

function test_workspace_mail_check_over_limit()
{
	pg_assert_false(ws_mail_check_over_limit(0), 'no counter (fails open)');
	pg_assert_false(ws_mail_check_over_limit(WS_MAIL_CHECK_PER_MINUTE), 'the last allowed press');
	pg_assert_true(ws_mail_check_over_limit(WS_MAIL_CHECK_PER_MINUTE + 1), 'one more');
	pg_assert_same(4, WS_MAIL_CHECK_PER_MINUTE, 'a few presses a minute');
}

function test_workspace_mail_check_text()
{
	pg_assert_same('No new e-mail', ws_mail_check_text(0), 'none');
	pg_assert_same('1 new e-mail', ws_mail_check_text(1), 'one');
	pg_assert_same('3 new e-mails', ws_mail_check_text(3), 'several');
}

function test_workspace_mail_check_line_html()
{
	$html = ws_mail_check_line_html(array('stored' => 3, 'failed' => array(), 'waiting' => 0));

	pg_assert_contains('3 new e-mails', $html, 'the count');
	pg_assert_false(strpos($html, 'bi-exclamation-triangle') !== false, 'no failure');

	$failed = array(array('name' => 'Support <b>', 'error' => 'timeout', 'detail' => '', 'host' => 'imap.example.com'));
	$html = ws_mail_check_line_html(array('stored' => 0, 'read' => 1, 'failed' => $failed, 'waiting' => 0));

	pg_assert_contains('No new e-mail', $html, 'one box read, none came');
	pg_assert_contains('Support &lt;b&gt;', $html, 'the box\'s name, escaped');
	pg_assert_contains(h(ws_mail_error_text('timeout', '', 'imap.example.com')), $html, 'the card\'s own words');

	$html = ws_mail_check_line_html(array('stored' => 0, 'read' => 0, 'failed' => $failed, 'waiting' => 0));

	pg_assert_false(strpos($html, 'No new e-mail') !== false, 'no box read: no count');
	pg_assert_contains(h(ws_mail_error_text('timeout', '', 'imap.example.com')), $html, 'only the failure');

	pg_assert_contains('No new e-mail', ws_mail_check_line_html(array('stored' => 0, 'read' => 0, 'failed' => array(), 'waiting' => 0)), 'nothing failed');

	$html = ws_mail_check_line_html(array('stored' => 0, 'failed' => array(), 'waiting' => 2));

	pg_assert_contains(h(lang('Some mailboxes are being read already; their e-mail comes in a moment.')), $html, 'a box another reader holds');
}

function test_workspace_mail_check_wiring()
{
	require_once(PG_FUNCTIONS_DIR . '/includes/panel/actions.php');

	pg_assert_true(isset(pg_panel_actions()['ws_mail_check']), 'a panel action');

	$actions = file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/actions.php');

	pg_assert_contains("case 'ws_mail_check':", $actions, 'handled');

	$toolbar = ws_mail_list_filters_html(ws_mail_list_filters(array()), array(), false, true, array(), true);

	pg_assert_contains('data-ws-mail-check="0"', $toolbar, 'the button, every box');
	pg_assert_contains(h(lang('Check e-mail')), $toolbar, 'its label');
	pg_assert_false(strpos(ws_mail_list_filters_html(ws_mail_list_filters(array()), array(), false, true, array(), false), 'data-ws-mail-check') !== false, 'no box to read: no button');

	$toolbar = ws_mail_list_filters_html(ws_mail_list_filters(array('box' => '4')), array(4 => 'Support', 5 => 'Sales'), false, true, array(), true);

	pg_assert_contains('data-ws-mail-check="4"', $toolbar, 'a box\'s list reads that box');

	$script = file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace_mail.js');

	pg_assert_contains("h.api('ws_mail_check'", $script, 'the script asks the action');
	pg_assert_contains("w('checking')", $script, 'the button says it is working');
	pg_assert_true(isset(ws_mail_screen_js_strings()['checking']), 'its words');
	pg_assert_contains('data-ws-mail-check-line', file_get_contents(PG_FUNCTIONS_DIR . '/messages_mail.php'), 'the line under the toolbar');
}
