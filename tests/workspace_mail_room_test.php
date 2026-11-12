<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a mailbox kept in no channel (includes/workspace/mail_room.php):
 * what the "Kept in" half of the box form may say, who is in the box's room
 * and what brings its members in step, who may answer the mail kept in it,
 * and where its notices lead. Making the room and writing its members need
 * the database and are not run here; that no reader of ws_channels lists the
 * room is held by tests/workspace_mail_room_hidden_test.php.
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
require_once(__DIR__ . '/helpers/source_reader.php');

function test_workspace_mail_room_kind()
{
	pg_assert_true(ws_channel_is_mailbox(array('kind' => 'mailbox')));
	pg_assert_false(ws_channel_is_mailbox(array('kind' => 'private')));
	pg_assert_false(ws_channel_is_mailbox(array()));
	pg_assert_false(ws_channel_is_mailbox(null));
}

function test_workspace_mail_room_kept_in_reads_the_settings()
{
	pg_assert_same('mailbox', ws_mail_box_kept_in(array('settings' => json_encode(array('kept_in' => 'mailbox')))));
	pg_assert_same('channel', ws_mail_box_kept_in(array('settings' => json_encode(array('kept_in' => 'channel')))));
	pg_assert_same('channel', ws_mail_box_kept_in(array('settings' => json_encode(array('room_id' => 7)))), 'a box that moved back keeps its room, not the word');
	pg_assert_same('channel', ws_mail_box_kept_in(array('settings' => '')), 'a box saved before the choice existed');
	pg_assert_same('channel', ws_mail_box_kept_in(null), 'a new box');
}

function test_workspace_mail_room_input_takes_only_what_can_be_chosen()
{
	$input = ws_mail_room_input(array(
		'kept_in'          => 'mailbox',
		'room_people'      => array('5', '3', '5', '99', 'x', array('1'), '-2'),
		'room_departments' => array('2', '8'),
	), array(1, 3, 5), array(2));

	pg_assert_true($input['ok']);
	pg_assert_same('mailbox', $input['kept_in']);
	pg_assert_same(array(3, 5), $input['people'], 'known accounts only, once each, in order');
	pg_assert_same(array(2), $input['departments']);
}

function test_workspace_mail_room_input_needs_a_reader()
{
	$input = ws_mail_room_input(array('kept_in' => 'mailbox', 'room_people' => array('99')), array(1, 3), array(2));

	pg_assert_false($input['ok']);
	pg_assert_same('room_people', $input['field']);
	pg_assert_true($input['error'] !== '');

	pg_assert_true(ws_mail_room_input(array('kept_in' => 'mailbox', 'room_departments' => array('2')), array(1, 3), array(2))['ok'], 'a department alone is enough');
}

function test_workspace_mail_room_input_in_a_channel_asks_for_nobody()
{
	$input = ws_mail_room_input(array('room_people' => array('3')), array(1, 3), array(2));

	pg_assert_true($input['ok']);
	pg_assert_same('channel', $input['kept_in']);
	pg_assert_same(array(3), $input['people'], 'the choice is kept for the day the box moves back');

	pg_assert_same('channel', ws_mail_room_input(array('kept_in' => 'elsewhere'), array(), array())['kept_in'], 'an unknown word is a channel');
	pg_assert_same(array(), ws_mail_room_input(array('kept_in' => 'channel', 'room_people' => '3'), array(3), array())['people'], 'a list that is no list');
}

function test_workspace_mail_room_members_are_the_people_and_the_departments()
{
	pg_assert_same(array(2, 3, 5, 7), ws_mail_room_members(array(5, 2), array(array(3, 5), array(7, 9)), array(1, 2, 3, 5, 7)),
		'named one by one or by department, once each, and only while in the team');
	pg_assert_same(array(), ws_mail_room_members(array(), array(), array(1, 2)));
	pg_assert_same(array(4), ws_mail_room_members(array('4'), array(), array('4')), 'ids as the database gives them');
}

function test_workspace_mail_room_plan()
{
	pg_assert_same(array('add' => array(5, 7), 'remove' => array(2)), ws_mail_room_plan(array(3, 5, 7), array(2, 3)));
	pg_assert_same(array('add' => array(), 'remove' => array()), ws_mail_room_plan(array(3, 5), array('5', '3')), 'in step already');
	pg_assert_same(array('add' => array(), 'remove' => array(4)), ws_mail_room_plan(array(), array(4)), 'nobody chosen any more');
}

function test_workspace_mail_room_url()
{
	pg_assert_same('/panel/workspace_mail.php?view=all&conversation=12', ws_mail_room_url('/panel/', 12), 'one conversation, whatever its status');
	pg_assert_same('/panel/workspace_mail.php?view=all&box=4', ws_mail_room_url('/panel/', 0, 4), 'the mail of one mailbox');
	pg_assert_same('/panel/workspace_mail.php?view=all', ws_mail_room_url('/panel/'));
}

function test_workspace_mail_room_notice_of_a_channel_leads_to_its_card()
{
	pg_assert_same('/panel/workspace.php?channel=6&message=40', ws_mail_notice_url('/panel/', 6, 40, array('id' => 6, 'kind' => 'private')));
	pg_assert_same('/panel/workspace.php?channel=6&message=40', ws_mail_notice_url('/panel/', 6, 40), 'a channel that is gone, or one the caller did not read');

	// A room's line finds its conversation in the database; that it asks is held in source.
	$source = pg_test_function_source('ws_mail_notice_url');

	pg_assert_contains('ws_mail_room_url($base, ws_mail_room_conversation_id(', $source, 'a room\'s line opens the conversation on the E-mails screen');
	pg_assert_contains('ws_mail_notice_url($base, (int) $row[\'channel_id\'], (int) $row[\'message_id\'], $channel)', pg_test_function_source('ws_mail_inbox_describe'), 'given to you, and the customer wrote again');
	pg_assert_contains('ws_mail_notice_url($base, (int) $row[\'channel_id\'], (int) $row[\'message_id\'], $channel)', pg_test_function_source('ws_mail_unsent_describe'), 'could not be sent');
}

function test_workspace_mail_room_notice_names_the_mailbox()
{
	pg_assert_same(array('thread' => false, 'name' => 'Support'), ws_inbox_place(array('id' => 9, 'kind' => 'mailbox', 'name' => 'Support'), null),
		'the room carries the mailbox\'s name, and is no #channel');
}

function test_workspace_mail_room_who_may_answer()
{
	$member = array('id' => 5, 'role' => 3, 'member' => true);

	pg_assert_true(ws_mail_can_answer($member, array('id' => 3, 'kind' => 'public', 'archived_at' => 0)), 'in a channel, whoever may post in it');
	pg_assert_false(ws_mail_can_answer($member, array('id' => 3, 'kind' => 'public', 'archived_at' => 50)), 'not in an archived one');
	pg_assert_false(ws_mail_can_answer($member, null));
	pg_assert_false(ws_mail_can_answer(array('id' => 5, 'role' => 0, 'member' => false), array('id' => 9, 'kind' => 'mailbox', 'archived_at' => 0)), 'a room: team accounts only');
	pg_assert_false(ws_mail_can_answer($member, array('id' => 9, 'kind' => 'mailbox', 'archived_at' => 50)), 'a room: not while it is archived');
	pg_assert_contains('(bool) ws_channel_membership($channel[\'id\'], $viewer[\'id\'])', pg_test_function_source('ws_mail_can_answer'), 'a room: its members');

	// Every gate of the mail's own actions asks this, never who may post.
	foreach (array('ws_mail_send_boxes', 'ws_mail_resend', 'ws_mail_reply_target', 'ws_mail_conversation_check', 'ws_mail_pane') as $function) {
		$source = pg_test_function_source($function);

		pg_assert_contains('ws_mail_can_answer(', $source, $function);
		pg_assert_same(false, strpos($source, 'ws_can_post_channel('), $function . ' would refuse everybody in a room');
	}
}

function test_workspace_mail_room_fields_without_the_choice()
{
	$html = ws_mail_room_fields_html('p_', null, '<option value="4">#support</option>', false, array(), array());

	pg_assert_contains('<input type="hidden" name="kept_in" value="channel">', $html, 'the fields that follow kept_in still show');
	pg_assert_contains('<div class="md:col-6" data-ws-mail-when="kept_in=channel">', $html);
	pg_assert_contains('name="channel_id" id="p_channel" required><option value="4">#support</option></select>', $html, 'the channel, as before');
	pg_assert_same(false, strpos($html, 'room_people'), 'nobody to choose');
}

function test_workspace_mail_room_fields_of_a_box_in_a_channel()
{
	$html = ws_mail_room_fields_html('p_', array('settings' => json_encode(array('room_people' => array(7)))), '<option value="4">#50% off</option>', true,
		array(5 => 'Ada <A>', 7 => 'Deniz'), array(2 => 'Sales'));

	pg_assert_contains('<option value="channel" selected>' . h(lang('A channel')) . '</option>', $html);
	pg_assert_contains('<option value="mailbox">' . h(lang('The mailbox itself')) . '</option>', $html);
	pg_assert_contains('<div class="md:col-6" data-ws-mail-when="kept_in=channel">', $html, 'the channel shows');
	pg_assert_contains('<option value="4">#50% off</option>', $html, 'a channel\'s name is drawn as given');
	pg_assert_same(2, substr_count($html, '<div class="md:col-6 d-none" data-ws-mail-when="kept_in=mailbox">'), 'the people and the departments wait for the choice');
	pg_assert_contains('name="room_people[]" value="5" id="p_room_people_5">', $html);
	pg_assert_contains('<label for="p_room_people_5">Ada &lt;A&gt;</label>', $html, 'a name is text');
	pg_assert_contains('name="room_people[]" value="7" id="p_room_people_7" checked>', $html, 'a choice kept from before stays ticked');
	pg_assert_contains('name="room_departments[]" value="2" id="p_room_departments_2">', $html);
}

function test_workspace_mail_room_fields_of_a_box_in_no_channel()
{
	$box = array('settings' => json_encode(array('kept_in' => 'mailbox', 'room_id' => 9, 'room_people' => array(5), 'room_departments' => array(2))));
	$html = ws_mail_room_fields_html('p_', $box, '<option value="">Choose</option>', true, array(5 => 'Ada', 7 => 'Deniz'), array(2 => 'Sales ' . lang('(archived)')));

	pg_assert_contains('<option value="mailbox" selected>', $html);
	pg_assert_contains('<div class="md:col-6 d-none" data-ws-mail-when="kept_in=channel">', $html, 'the channel waits for the choice');
	pg_assert_contains('name="channel_id" id="p_channel"><option', $html, 'and is not required while hidden');
	pg_assert_same(2, substr_count($html, '<div class="md:col-6" data-ws-mail-when="kept_in=mailbox">'), 'the readers show');
	pg_assert_contains('value="5" id="p_room_people_5" checked>', $html);
	pg_assert_contains('value="7" id="p_room_people_7">', $html);
	pg_assert_contains('value="2" id="p_room_departments_2" checked>', $html);
	pg_assert_contains(h('Sales ' . lang('(archived)')), $html, 'an archived department the box names stays to be seen');

	pg_assert_contains(h(lang('None yet')), ws_mail_room_fields_html('p_', $box, '', true, array(5 => 'Ada'), array()), 'a team with no department');
}

function test_workspace_mail_room_in_the_box_form()
{
	// Without a database the site cannot keep a box in no channel yet.
	$form = ws_mail_box_form('workspace_settings.php', array('id' => 4, 'name' => 'Support', 'address' => 's@example.com', 'channel_id' => 3, 'settings' => '', 'folder' => 'INBOX',
		'after_fetch' => 'seen', 'reply_via' => 'box', 'enabled' => 1, 'auto_reply' => 0, 'auto_reply_text' => ''), array(3 => array('id' => 3, 'name' => 'support')));

	pg_assert_contains('<input type="hidden" name="kept_in" value="channel">', $form);
	pg_assert_contains('<option value="3" selected>#support</option>', $form, 'its channel chosen');

	$dialog = ws_mail_box_dialog_names(ws_mail_room_fields_html('p_', null, '', true, array(5 => 'Ada'), array(2 => 'Sales')), 4, 'f');

	pg_assert_contains('name="ws_mail[4][kept_in]" form="f"', $dialog, 'Site Settings posts the choice with the box');
	pg_assert_contains('name="ws_mail[4][room_people][]" form="f" value="5"', $dialog, 'and its readers as a list');
	pg_assert_contains('name="ws_mail[4][room_departments][]" form="f" value="2"', $dialog);

	$script = ws_mail_settings_script();

	pg_assert_contains('(after_fetch|reply_via|kept_in)', $script, 'the choice shows and hides its fields');
	pg_assert_contains("channel.required = (kept.value !== \\'mailbox\\');", pg_test_function_source('ws_mail_settings_script'), 'a hidden channel is not asked for');
}

function test_workspace_mail_room_a_box_in_no_channel_never_names_its_room_as_a_channel()
{
	$box = array('id' => 4, 'name' => 'Support', 'address' => 's@example.com', 'channel_id' => 9, 'settings' => json_encode(array('kept_in' => 'mailbox', 'room_id' => 9)),
		'folder' => 'INBOX', 'after_fetch' => 'seen', 'reply_via' => 'box', 'enabled' => 1, 'auto_reply' => 0, 'auto_reply_text' => '');
	$form = ws_mail_box_form('workspace_settings.php', $box, array());

	pg_assert_same(false, strpos($form, '<option value="9"'), 'the room is not offered as "A channel you do not see"');
	pg_assert_contains("ws_mail_box_kept_in(\$box) === 'channel') && ((int) \$box['channel_id'] === \$values['channel_id'])", pg_test_function_source('ws_mail_box_save_check'),
		'and a save cannot keep it as a channel');
}

function test_workspace_mail_room_save_wiring()
{
	$check = pg_test_function_source('ws_mail_box_save_check');
	$write = pg_test_function_source('ws_mail_box_save_write');

	pg_assert_contains('ws_mail_room_input($post, ws_team_ids(), array_keys(ws_mail_room_departments($box)))', $check, 'the readers are checked before anything is written');
	pg_assert_contains("(\$room['kept_in'] === 'mailbox') && !ws_mail_room_ready()", $check, 'not before the schema knows the kind');
	pg_assert_contains("\$settings['ai_triage'] = false;", $check, 'no suggestion where no channel shows it');
	pg_assert_contains('$kept = ws_mail_room_save($viewer, $values, $checked[\'settings\']);', $write, 'the room is made and its people set before the box is stored');
	pg_assert_contains("channel_id = '\" . (int) \$kept['channel_id'] . \"'", $write, 'the box writes into its room');

	$save = pg_test_function_source('ws_mail_room_save');

	pg_assert_contains("\$settings['room_id'] = ws_mail_room_ensure(", $save);
	pg_assert_contains('ws_mail_room_sync($room, ws_mail_room_wanted($settings));', $save, 'a box that has a room keeps its people in step, wherever it keeps its mail now');

	$sync = pg_test_function_source('ws_mail_room_sync');

	foreach (array('ws_channel_membership_forget();', 'ws_channel_folder_access($room, $plan[\'add\'], true);', 'ws_inbox_reach_regained($room, $plan[\'add\']);',
		'ws_channel_folder_access($room, $plan[\'remove\'], false);', 'ws_inbox_reach_lost($room, $plan[\'remove\']);') as $step) {
		pg_assert_contains($step, $sync, 'the room\'s folder and the inbox follow its people');
	}

	pg_assert_contains("VALUES ('\" . e(\$name) . \"', 'mailbox', '', 0, 0, 0,", pg_test_function_source('ws_mail_room_ensure'), 'a room has no owner');
	pg_assert_contains('ws_mail_rooms_follow_department($department_id);', (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/team_settings.php'), 'a department\'s people changed');
}

function test_workspace_mail_room_on_the_emails_screen()
{
	$item = array('id' => 1, 'direction' => 'in', 'from_name' => 'Ayşe', 'from_email' => 'ayse@example.com', 'to_list' => 'support@example.com', 'cc_list' => '',
		'body_text' => 'Hello', 'refused_files' => '', 'user_id' => 0, 'sent_at' => 0, 'created_at' => 100, 'send_state' => '', 'send_error' => '', 'files' => array(), 'bounces' => array());
	$pane = array(
		'conversation' => array('id' => 4, 'channel_id' => 9, 'message_id' => 90, 'subject' => 'Order', 'customer_name' => 'Ayşe', 'customer_email' => 'ayse@example.com'),
		'channel'      => array('id' => 9, 'kind' => 'mailbox', 'name' => 'Support'),
		'box_name'     => 'Support',
		'can_post'     => true,
		'items'        => array($item),
		'last_id'      => 1,
	);
	$html = ws_mail_pane_html($pane, '/pg/');

	pg_assert_contains('Ayşe &lt;ayse@example.com&gt; · Support</div>', $html, 'the customer and the mailbox, no channel');
	pg_assert_same(false, strpos($html, 'workspace.php?channel='), 'nothing leads into the room');
	pg_assert_same(false, strpos($html, h(lang('Open in channel'))));

	$pane['items'] = array_fill(0, WS_MAIL_PANE_MAX, $item);

	pg_assert_contains(h(lang(array('string' => 'The newest {var:1} e-mails are shown.', 'vars' => WS_MAIL_PANE_MAX))) . '</p>', ws_mail_pane_html($pane, '/pg/'), 'no channel has the rest');

	$row = array('id' => 4, 'channel_id' => 9, 'message_id' => 90, 'subject' => 'Order', 'customer_name' => 'Ayşe', 'customer_email' => 'ayse@example.com', 'status' => 'open',
		'assignee_id' => 0, 'assignee_name' => '', 'first_in_at' => 100, 'first_reply_at' => 0, 'last_in_at' => 100, 'last_out_at' => 0, 'box_name' => 'Support', 'channel_name' => '',
		'first_reply_hours' => 0, 'overdue' => false, 'mails' => 1, 'snippet' => '', 'unread' => false, 'unsent' => '');
	$rows = ws_mail_list_rows_html(array($row), ws_mail_list_filters(array()), 200);

	pg_assert_contains('<span>Support</span>', $rows, 'a row names its mailbox');
	pg_assert_same(false, strpos($rows, '<span>#'), 'and no channel');
	pg_assert_contains("(\$channel && !ws_channel_is_mailbox(\$channel)) ? (string) \$channel['name'] : ''", pg_test_function_source('ws_mail_list'), 'the list reads a room as no channel');
}

function test_workspace_mail_room_in_new_email_and_the_customer_line()
{
	$boxes = ws_mail_compose_boxes(array(array(
		'box'      => array('id' => 3, 'name' => 'Support', 'address' => 's@example.com', 'channel_id' => 9, 'settings' => ''),
		'route'    => array('ok' => true),
		'channels' => array(array('id' => 20, 'name' => 'billing', 'kind' => 'private'), array('id' => 9, 'name' => 'Support', 'kind' => 'mailbox')),
	)));

	pg_assert_same(array('id' => 9, 'name' => 'Support', 'mailbox' => true), $boxes[0]['channels'][0], 'the mailbox itself first, marked');
	pg_assert_same(array('id' => 20, 'name' => 'billing', 'mailbox' => false), $boxes[0]['channels'][1], 'a route\'s channel stays a channel');
	pg_assert_same(lang('The mailbox itself'), ws_mail_compose_js_strings()['mail_new_in_mailbox']);
	pg_assert_contains("channel.mailbox ? t('mail_new_in_mailbox') : '#' + channel.name", (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace_mail.js'), 'the form names it so');

	$line = ws_mail_customer_line_text('Ayşe', 9, 'Order', 'Support <#channel:3>');

	pg_assert_contains('Support #channel:3', $line, 'the mailbox by its name, any tag in it taken apart');
	pg_assert_same(false, strpos($line, '<#channel:9>'), 'no tag leads to a room');
	pg_assert_contains('<#channel:12>', ws_mail_customer_line_text('Ayşe', 12, 'Order'), 'a channel keeps its tag');
	pg_assert_contains("ws_channel_is_mailbox(\$own) ? (string) \$own['name'] : ''", pg_test_function_source('ws_mail_customer_lines'));
}

function test_workspace_mail_room_no_suggestion_switch()
{
	$room = array('settings' => json_encode(array('kept_in' => 'mailbox', 'ai_triage' => true)));

	pg_assert_contains('<div class="md:col-12 d-none" data-ws-mail-when="kept_in=channel">', ws_mail_triage_field('p_', $room, true), 'hidden for a box kept in no channel');
	pg_assert_contains('<div class="md:col-12" data-ws-mail-when="kept_in=channel">', ws_mail_triage_field('p_', null, true), 'shown with a channel');
}

function test_workspace_mail_room_address_of_the_channels_screen()
{
	$viewer = array('id' => 5, 'role' => 3, 'member' => true);

	pg_assert_same('', ws_mail_room_redirect($viewer, array(), '/pg/'), 'no channel asked for');
	pg_assert_same('', ws_mail_room_redirect($viewer, array('channel' => 'x'), '/pg/'));
	pg_assert_same('', ws_mail_room_redirect($viewer, array('channel' => array('9')), '/pg/'));

	$source = pg_test_function_source('ws_mail_room_redirect');

	pg_assert_contains('!ws_channel_is_mailbox($room) || !ws_can_read_channel($viewer, $room)', $source, 'only a room, and only for who reads it');
}
