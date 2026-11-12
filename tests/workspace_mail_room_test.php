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
	pg_assert_same('/panel/workspace.php?channel=6&message=40', ws_mail_notice_url('/panel/', array('id' => 6, 'kind' => 'private'), 40));
	pg_assert_same('/panel/workspace.php?channel=0&message=40', ws_mail_notice_url('/panel/', null, 40), 'a channel that is gone');
}
