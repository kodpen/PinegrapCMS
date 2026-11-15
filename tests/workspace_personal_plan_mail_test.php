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

	$marked = ws_personal_plan_mail_pane($pane, true, array());
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

	$outlook = ws_mail_invitation_read(array(array('content_type' => 'text/calendar', 'filename' => '',
		'content' => "BEGIN:VCALENDAR\r\nBEGIN:VEVENT\r\nUID:o\r\nSUMMARY:Berlin\r\nDTSTART;TZID=\"W. Europe Standard Time\":20261201T100000\r\nDTEND;TZID=\"W. Europe Standard Time\":20261201T110000\r\nEND:VEVENT\r\nEND:VCALENDAR")));
	pg_assert_same(strtotime('2026-12-01 09:00:00 UTC'), $outlook['start'], 'Outlook\'s Windows zone name is the sender\'s zone, whatever the site\'s');
	pg_assert_same(strtotime('2026-12-01 10:00:00 UTC'), $outlook['end']);

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

	$card = ws_mail_invitation_card_html($item, ws_mail_invitation_tools_html(12, 'add'));
	pg_assert_contains('data-ws-mail-calendar="12"', $card);
	pg_assert_contains('Room &lt;4&gt;', $card, 'escaped');
	pg_assert_contains('ayse@example.com', $card, 'the organiser');
	pg_assert_false(strpos(ws_mail_invitation_card_html($item, ''), 'data-ws-mail-calendar') !== false, 'no button when not offered');
	pg_assert_contains('data-ws-mail-invite-tools="12"', ws_mail_invitation_card_html($item, null), 'the place a channel fills for each reader');
	pg_assert_same('', ws_mail_invitation_card_html(array('id' => 1, 'invitation' => ''), ''), 'a mail without an invitation');
	pg_assert_same('', ws_mail_invitation_card_html(array('id' => 1, 'invitation' => '{bad'), ''), 'an unreadable column');
	pg_assert_contains(h(lang('Changed invitation')), ws_mail_invitation_card_html(array_merge($item, array('invitation' => json_encode(array_merge($invitation, array('changed' => true))))), ''));
	pg_assert_contains(h(lang('Cancelled invitation')), ws_mail_invitation_card_html(array_merge($item, array('invitation' => json_encode(array_merge($invitation, array('method' => 'CANCEL'))))), ''));
}

function test_workspace_mail_invitation_wiring()
{
	$read = function ($path) {
		return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $path);
	};

	pg_assert_contains("ws_mail_invitation_keep(\$item_id, \$box_id, \$parsed['attachments'])", $read('includes/workspace/mail.php'), 'kept at ingest');
	pg_assert_contains('array_splice($refused, $invitation_at, 1);', $read('includes/workspace/mail.php'), 'a part read into the card is not named among the refused');
	pg_assert_contains('ws_mail_invitation_card_html($item, null)', $read('includes/workspace/mail.php'), 'the card in the channel');
	pg_assert_contains("'mail_invite_tools' =>", $read('includes/workspace/messages.php'), 'each reader\'s buttons');
	pg_assert_contains("ws_mail_invitation_card_html(\$item, (string) (\$item['calendar_tools'] ?? ''))", $read('includes/workspace/mail_pane.php'), 'the card in the pane');
	pg_assert_contains('This e-mail is already in your calendar.', $read('includes/workspace/personal_plans.php'), 'one plan a person');
	pg_assert_contains("install_add_column('ws_mail_items', 'invitation', \"TEXT NULL\");", $read('includes/migrations/2026.11.0.php'));
	pg_assert_contains("querySelectorAll('[data-ws-mail-calendar]')", $read('assets/js/workspace.js'), 'the channel card\'s button, taken away for a reader not offered it');
}

function test_workspace_mail_invitation_states_and_words()
{
	$invitation = array('uid' => 'u', 'method' => 'REQUEST', 'summary' => 'Kick-off', 'start' => strtotime('2026-11-20 10:00:00'), 'end' => strtotime('2026-11-20 11:30:00'),
		'all_day' => false, 'location' => '', 'organizer' => array('name' => 'Ayşe', 'email' => 'ayse@example.org'));
	$plan = array('title' => 'Kick-off', 'starts_at' => strtotime('2026-11-20 10:00:00'), 'ends_at' => strtotime('2026-11-20 11:30:00'));
	$changed = array_merge($invitation, array('changed' => true, 'start' => strtotime('2026-11-21 10:00:00'), 'end' => strtotime('2026-11-21 11:00:00')));
	$cancel = array_merge($invitation, array('method' => 'CANCEL'));

	pg_assert_same('add', ws_personal_plan_mail_state($invitation, null));
	pg_assert_same('in', ws_personal_plan_mail_state($invitation, $plan), 'one plan a person');
	pg_assert_same('update', ws_personal_plan_mail_state($changed, $plan));
	pg_assert_same('in', ws_personal_plan_mail_state($changed, array_merge($plan, array('starts_at' => $changed['start'], 'ends_at' => $changed['end']))), 'the plan follows it already');
	pg_assert_same('', ws_personal_plan_mail_state($changed, null), 'nothing to change');
	pg_assert_same('remove', ws_personal_plan_mail_state($cancel, $plan));
	pg_assert_same('', ws_personal_plan_mail_state($cancel, null), 'nothing to remove');

	pg_assert_contains(h(lang('In your calendar')), ws_mail_invitation_tools_html(3, 'in'));
	pg_assert_contains('data-ws-mail-calendar-mode="open"', ws_mail_invitation_tools_html(3, 'in'), 'a link that opens the plan');
	pg_assert_contains('data-ws-mail-calendar-mode="update"', ws_mail_invitation_tools_html(3, 'update'));
	pg_assert_contains('data-ws-mail-calendar-mode="remove"', ws_mail_invitation_tools_html(3, 'remove'));
	pg_assert_false(strpos(ws_mail_invitation_tools_html(3, ''), 'data-ws-mail-calendar') !== false);
	pg_assert_contains(h(lang('No answer is sent to the organiser.')), ws_mail_invitation_tools_html(3, ''));

	// Words: the year when not this year's; days without hours.
	pg_assert_false(strpos(ws_mail_invitation_when($invitation, '2026'), '2026') !== false, 'this year without its number');
	pg_assert_contains('20.11.2026', ws_mail_invitation_when($invitation, '2027'));
	pg_assert_contains('10:00–11:30', ws_mail_invitation_when($invitation, '2026'));
	pg_assert_false(strpos(ws_mail_invitation_when(array_merge($invitation, array('end' => strtotime('2026-11-22 09:00:00'))), '2026'), ':') !== false, 'many days read as days');
	pg_assert_false(strpos(ws_mail_invitation_when(array_merge($invitation, array('all_day' => true, 'start' => strtotime('2026-11-20'), 'end' => strtotime('2026-11-21'))), '2026'), ':') !== false, 'all day without hours');

	// The organiser's address beside the name when it is not the sender's; plain text.
	$item = array('id' => 4, 'subject' => 'S', 'from_email' => 'ayse@example.org', 'invitation' => json_encode(array_merge($invitation, array('location' => '<a href="http://x">x</a> https://example.org'))));
	$card = ws_mail_invitation_card_html($item, '');
	pg_assert_false(strpos($card, 'ayse@example.org') !== false, 'the sender\'s own address is not repeated');
	pg_assert_contains('Ayşe &lt;office@example.org&gt;', ws_mail_invitation_card_html(array_merge($item, array('from_email' => 'other@example.org',
		'invitation' => json_encode(array_merge($invitation, array('organizer' => array('name' => 'Ayşe', 'email' => 'office@example.org')))))), ''));
	pg_assert_false(strpos($card, '<a ') !== false, 'nothing from outside is a link');
	pg_assert_contains('&lt;a href=', $card);
}

function test_workspace_mail_invitation_part_and_refused()
{
	$parts = array(array('content_type' => 'text/plain', 'filename' => 'a.txt'), array('content_type' => 'text/calendar', 'filename' => '', 'content' => 'x'));
	pg_assert_same('text/calendar', ws_mail_invitation_part($parts)['content_type']);
	pg_assert_same(null, ws_mail_invitation_part(array(array('content_type' => 'text/plain', 'filename' => 'a.txt'))));
}
