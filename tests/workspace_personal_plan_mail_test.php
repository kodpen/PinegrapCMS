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

function test_workspace_mail_invitation_read()
{
	$ics = "BEGIN:VCALENDAR\r\nMETHOD:REQUEST\r\nBEGIN:VEVENT\r\nUID:abc-123@example.com\r\nSUMMARY:Quarterly review\\, Q4\r\n"
		. "DTSTART:20261120T100000Z\r\nDTEND:20261120T113000Z\r\nLOCATION:Room 4\r\nORGANIZER;CN=\"Ayşe Kaya\":mailto:Ayse@Example.com\r\n"
		. "ATTENDEE;CN=Bob:mailto:bob@example.com\r\nDESCRIPTION:Long text\r\nBEGIN:VALARM\r\nSUMMARY:Alarm\r\nEND:VALARM\r\nEND:VEVENT\r\n"
		. "BEGIN:VEVENT\r\nUID:second\r\nSUMMARY:Second\r\nDTSTART:20261121T100000Z\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n";
	$read = ws_mail_invitation_read(array(
		array('content_type' => 'application/pdf', 'filename' => 'a.pdf', 'content' => 'x'),
		array('content_type' => 'text/calendar', 'filename' => '', 'content' => $ics),
	));

	pg_assert_same('abc-123@example.com', $read['uid']);
	pg_assert_same('REQUEST', $read['method']);
	pg_assert_same('Quarterly review, Q4', $read['summary'], 'the first event, its escapes undone; not the alarm\'s');
	pg_assert_same(strtotime('2026-11-20 10:00:00 UTC'), $read['start']);
	pg_assert_same(strtotime('2026-11-20 11:30:00 UTC'), $read['end']);
	pg_assert_false($read['all_day']);
	pg_assert_same('Room 4', $read['location']);
	pg_assert_same(array('name' => 'Ayşe Kaya', 'email' => 'ayse@example.com'), $read['organizer']);
	pg_assert_same(array('uid', 'method', 'summary', 'start', 'end', 'all_day', 'location', 'organizer'), array_keys($read), 'no attendees, description or alarm');

	$day = ws_mail_invitation_read(array(array('content_type' => 'application/octet-stream', 'filename' => 'invite.ics',
		'content' => "BEGIN:VCALENDAR\nBEGIN:VEVENT\nUID:d\nSTATUS:CANCELLED\nSUMMARY:" . str_repeat('x', 300) . "\nDTSTART;VALUE=DATE:20261124\nEND:VEVENT\nEND:VCALENDAR")));
	pg_assert_true($day['all_day']);
	pg_assert_same('CANCEL', $day['method'], 'a cancelled event');
	pg_assert_same(strtotime('2026-11-25 00:00:00'), $day['end'], 'one day when it names no end');
	pg_assert_same(255, mb_strlen($day['summary']), 'text cut to 255');

	pg_assert_same(null, ws_mail_invitation_read(array(array('content_type' => 'text/calendar', 'filename' => '', 'content' => 'BEGIN:VCALENDAR\nnot an event'))), 'a part that does not read');
	pg_assert_same(null, ws_mail_invitation_read(array()), 'no calendar part');
}

function test_workspace_mail_invitation_fill_and_card()
{
	$invitation = array('uid' => 'u', 'method' => 'REQUEST', 'summary' => 'Re: Kick-off', 'start' => strtotime('2026-11-20 10:00:00'), 'end' => strtotime('2026-11-20 11:30:00'),
		'all_day' => false, 'location' => 'Room <4>', 'organizer' => array('name' => '', 'email' => 'ayse@example.com'));
	$item = array('id' => 12, 'subject' => 'Invitation: Kick-off', 'sent_at' => strtotime('2026-11-14 09:00:00'), 'invitation' => json_encode($invitation));
	$fill = ws_personal_plan_mail_fill($item);

	pg_assert_same('Kick-off', $fill['title'], 'the event\'s title');
	pg_assert_same('2026-11-20', $fill['start'], 'the event\'s day, not the mail\'s');
	pg_assert_same('10:00', $fill['start_time']);
	pg_assert_same('11:30', $fill['end_time']);
	pg_assert_false($fill['all_day']);
	pg_assert_same('Room <4>', $fill['note'], 'the place as the note');

	$all_day = ws_personal_plan_mail_fill(array('id' => 1, 'invitation' => json_encode(array_merge($invitation, array('all_day' => true,
		'start' => strtotime('2026-11-24 00:00:00'), 'end' => strtotime('2026-11-26 00:00:00'))))));
	pg_assert_same(array('2026-11-24', '2026-11-25'), array($all_day['start'], $all_day['end']), 'an all-day event\'s last day');

	$card = ws_mail_invitation_card_html($item, true);
	pg_assert_contains('data-ws-mail-calendar="12"', $card);
	pg_assert_contains('Room &lt;4&gt;', $card, 'escaped');
	pg_assert_contains('ayse@example.com', $card, 'the organiser');
	pg_assert_false(strpos(ws_mail_invitation_card_html($item, false), 'data-ws-mail-calendar') !== false, 'no button when not offered');
	pg_assert_same('', ws_mail_invitation_card_html(array('id' => 1, 'invitation' => ''), true), 'a mail without an invitation');
	pg_assert_same('', ws_mail_invitation_card_html(array('id' => 1, 'invitation' => '{bad'), true), 'an unreadable column');
	pg_assert_contains(h(lang('Changed invitation')), ws_mail_invitation_card_html(array_merge($item, array('invitation' => json_encode(array_merge($invitation, array('changed' => true))))), true));
	pg_assert_contains(h(lang('Cancelled invitation')), ws_mail_invitation_card_html(array_merge($item, array('invitation' => json_encode(array_merge($invitation, array('method' => 'CANCEL'))))), true));
}

function test_workspace_mail_invitation_wiring()
{
	$read = function ($path) {
		return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $path);
	};

	pg_assert_contains("ws_mail_invitation_keep(\$item_id, \$box_id, \$parsed['attachments']);", $read('includes/workspace/mail.php'), 'kept at ingest');
	pg_assert_contains('ws_mail_invitation_card_html($item, ws_personal_plans_ready())', $read('includes/workspace/mail.php'), 'the card in the channel');
	pg_assert_contains("ws_mail_invitation_card_html(\$item, !empty(\$item['calendar']))", $read('includes/workspace/mail_pane.php'), 'the card in the pane');
	pg_assert_contains("install_add_column('ws_mail_items', 'invitation', \"TEXT NULL\");", $read('includes/migrations/2026.11.0.php'));
	pg_assert_contains("querySelectorAll('[data-ws-mail-calendar]')", $read('assets/js/workspace.js'), 'the channel card\'s button, taken away for a reader not offered it');
}
