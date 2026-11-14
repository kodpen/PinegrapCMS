<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for Add to my calendar (includes/workspace/personal_plan_mail.php):
 * which mail offers it, the drawer's fill from a mail, and where the menu,
 * the pane and the drawer are wired.
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

function test_workspace_personal_plan_mail_offer()
{
	$member = array('id' => 7, 'member' => true);
	$row = array('id' => 40, 'file_id' => 0);
	$item = array('id' => 12, 'message_id' => 40, 'direction' => 'in', 'subject' => 'Meeting', 'from_email' => 'a@example.com', 'body_text' => 'Hello');

	pg_assert_same(12, ws_personal_plan_mail_offer($row, $item, $member, true), 'the mail\'s own text');
	pg_assert_same(0, ws_personal_plan_mail_offer($row, $item, $member, false), 'not before the plan tables');
	pg_assert_same(0, ws_personal_plan_mail_offer($row, $item, array('id' => 9, 'member' => false), true), 'a guest has no calendar');
	pg_assert_same(0, ws_personal_plan_mail_offer(array('id' => 41, 'file_id' => 0), $item, $member, true), 'a forward line or another message of the mail');
	pg_assert_same(0, ws_personal_plan_mail_offer(array('id' => 40, 'file_id' => 3), $item, $member, true), 'a file of the mail');
	pg_assert_same(0, ws_personal_plan_mail_offer($row, null, $member, true), 'no mail');
}

function test_workspace_personal_plan_mail_title()
{
	pg_assert_same('Project kick-off', ws_personal_plan_mail_title('Re: Fwd: Project kick-off'));
	pg_assert_same('Toplantı', ws_personal_plan_mail_title('YNT:  ilt: Toplantı'));
	pg_assert_same('Review', ws_personal_plan_mail_title("RE[2]: \n Review"));
	pg_assert_same('Reports due', ws_personal_plan_mail_title('Reports due'), 'a word starting like a mark stays');
	pg_assert_same(255, mb_strlen(ws_personal_plan_mail_title(str_repeat('ş', 300))), 'cut to the title\'s length');
}

function test_workspace_personal_plan_mail_fill()
{
	$at = strtotime('2026-11-20 14:30:00');
	$fill = ws_personal_plan_mail_fill(array('id' => 12, 'subject' => 'Re: Budget', 'sent_at' => $at, 'created_at' => $at + 600));

	pg_assert_same('Budget', $fill['title']);
	pg_assert_same('2026-11-20', $fill['start'], 'the mail\'s day');
	pg_assert_same('2026-11-20', $fill['end']);
	pg_assert_true($fill['all_day']);
	pg_assert_same(12, $fill['mail_item'], 'the mail the plan is tied to');

	$fill = ws_personal_plan_mail_fill(array('id' => 13, 'subject' => '', 'sent_at' => 0, 'created_at' => $at), array('subject' => 'Fwd: Offer'));
	pg_assert_same('Offer', $fill['title'], 'the conversation\'s subject when the mail has none');
	pg_assert_same('2026-11-20', $fill['start'], 'the day it came when it names none');

	// The fill passes the drawer's own check.
	$clean = ws_personal_plan_clean(array('title' => $fill['title'], 'start' => $fill['start'], 'end' => $fill['end'], 'all_day' => 1));
	pg_assert_false(isset($clean['error']));
}

function test_workspace_personal_plan_mail_pane_marks_mails()
{
	$pane = array('items' => array(
		array('id' => 1, 'message_id' => 5, 'direction' => 'in', 'subject' => 'A', 'from_email' => 'a@example.com', 'body_text' => 'x'),
		array('id' => 2, 'message_id' => 0, 'direction' => 'in', 'subject' => 'B', 'from_email' => 'b@example.com', 'body_text' => 'x'),
	));

	$marked = ws_personal_plan_mail_pane($pane, true);
	pg_assert_true($marked['items'][0]['calendar']);
	pg_assert_false($marked['items'][1]['calendar'], 'a mail without its message');
	pg_assert_false(ws_personal_plan_mail_pane($pane, false)['items'][0]['calendar'], 'not for a guest or before the plan tables');

	$html = ws_mail_pane_item_html(array_merge($marked['items'][0], array('from_name' => '', 'sent_at' => 0, 'created_at' => time(), 'to_list' => '', 'cc_list' => '',
		'files' => array(), 'refused_files' => '', 'bounces' => array(), 'send_status' => '', 'send_error' => '')));
	pg_assert_contains('data-ws-mail-calendar="1"', $html, 'the pane\'s button');
}

function test_workspace_personal_plan_mail_wiring()
{
	$read = function ($path) {
		return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $path);
	};

	pg_assert_contains("case 'ws_personal_plan_mail':", $read('includes/workspace/actions.php'));
	pg_assert_contains("'ws_personal_plan_mail',", $read('includes/panel/actions.php'), 'a panel action');
	pg_assert_contains("'mail_calendar' =>", $read('includes/workspace/messages.php'), 'offered on the mail\'s message');
	pg_assert_contains("t('mail_calendar')", $read('assets/js/workspace.js'), 'in the message menu');
	pg_assert_contains('data-ws-mail-calendar', $read('assets/js/workspace_mail.js'), 'the pane\'s button opens the drawer');
	pg_assert_contains('fromMail: fromMail', $read('assets/js/workspace_my_calendar.js'));
	pg_assert_contains("mail_item: plan ? 0 : (defaults.mail_item || 0)", $read('assets/js/workspace_my_calendar.js'), 'a new plan only is tied to the mail');
	pg_assert_contains('ws_personal_plan_mail_item($viewer, (int) $data[\'mail_item\'])', $read('includes/workspace/personal_plans.php'), 'the mail is read again on save');
}
