<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a note written in a channel (includes/workspace/notes.php,
 * api_channel_notes.php): who may change a note through a channel share,
 * the card touched on a save, the Markdown rules of the route's body and the
 * refusals of the write check.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_API_PANEL')) {
	define('PG_API_PANEL', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/notes.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/notes_io.php');

// The reach of person 7: channel 10 they may post in, 20 they only read,
// 30 they cannot read.
function ws_channel_notes_test_reach()
{
	return function ($channel_id, $need) {
		$reach = array(10 => 'post', 20 => 'read', 30 => '');
		$got = $reach[(int) $channel_id] ?? '';

		return (($got === 'post') && ($need !== 'post')) ? 'read' : $got;
	};
}

function ws_channel_notes_test_source($function)
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/notes.php');
	$start = strpos($source, 'function ' . $function . '(');

	pg_assert_true($start !== false, $function . ' exists');

	return substr($source, $start, strpos($source, "\nfunction ", $start + 1) - $start);
}

// The owner owns it, whatever the shares say.
function test_ws_channel_notes_access_owner()
{
	pg_assert_same('owner', ws_note_access_of(7, array('user_id' => 7), array(), ws_channel_notes_test_reach()), 'owner');
}

// A share by name gives edit with can_edit, view without.
function test_ws_channel_notes_access_person_share()
{
	$reach = ws_channel_notes_test_reach();

	pg_assert_same('edit', ws_note_access_of(7, array('user_id' => 3), array(array('user_id' => 7, 'channel_id' => 0, 'can_edit' => 1)), $reach), 'person share with can_edit');
	pg_assert_same('view', ws_note_access_of(7, array('user_id' => 3), array(array('user_id' => 7, 'channel_id' => 0, 'can_edit' => 0)), $reach), 'person share without');
	pg_assert_same('', ws_note_access_of(7, array('user_id' => 3), array(array('user_id' => 8, 'channel_id' => 0, 'can_edit' => 1)), $reach), 'somebody else\'s share');
}

// A channel share with can_edit gives edit to whoever may post there, view
// to whoever only reads it; without can_edit it is view, as before.
function test_ws_channel_notes_access_channel_share()
{
	$reach = ws_channel_notes_test_reach();
	$note = array('user_id' => 3);
	$share = function ($channel_id, $can_edit) {
		return array(array('user_id' => 0, 'channel_id' => $channel_id, 'can_edit' => $can_edit));
	};

	pg_assert_same('edit', ws_note_access_of(7, $note, $share(10, 1), $reach), 'can_edit, may post');
	pg_assert_same('view', ws_note_access_of(7, $note, $share(20, 1), $reach), 'can_edit, may only read');
	pg_assert_same('', ws_note_access_of(7, $note, $share(30, 1), $reach), 'can_edit, may not read');
	pg_assert_same('view', ws_note_access_of(7, $note, $share(10, 0), $reach), 'no can_edit, may post');
	pg_assert_same('view', ws_note_access_of(7, $note, $share(20, 0), $reach), 'no can_edit, may read');
	pg_assert_same('', ws_note_access_of(7, $note, $share(30, 0), $reach), 'no can_edit, may not read');
}

// The strongest share wins, in any order.
function test_ws_channel_notes_access_strongest_share_wins()
{
	$reach = ws_channel_notes_test_reach();
	$shares = array(
		array('user_id' => 7, 'channel_id' => 0, 'can_edit' => 0),
		array('user_id' => 0, 'channel_id' => 20, 'can_edit' => 0),
		array('user_id' => 0, 'channel_id' => 10, 'can_edit' => 1),
	);

	pg_assert_same('edit', ws_note_access_of(7, array('user_id' => 3), $shares, $reach), 'the channel\'s edit after a view');
	pg_assert_same('edit', ws_note_access_of(7, array('user_id' => 3), array_reverse($shares), $reach), 'and before it');
}

// Posting is asked only where it could give edit.
function test_ws_channel_notes_access_asks_post_only_for_an_editable_share()
{
	$asked = array();
	$reach = function ($channel_id, $need) use (&$asked) {
		$asked[] = $channel_id . ':' . $need;

		return 'read';
	};

	ws_note_access_of(7, array('user_id' => 3), array(
		array('user_id' => 0, 'channel_id' => 20, 'can_edit' => 0),
		array('user_id' => 0, 'channel_id' => 10, 'can_edit' => 1),
	), $reach);

	pg_assert_same(array('20:read', '10:post'), $asked, 'what was asked');
}

// ws_note_access() hands its rows to the pure rule.
function test_ws_channel_notes_access_uses_the_pure_rule()
{
	$body = ws_channel_notes_test_source('ws_note_access');

	pg_assert_contains('ws_note_access_of((int) $viewer[\'id\'], $note, (array) $shares, $reach)', $body, 'the pure rule decides');
	pg_assert_contains('ws_can_post_channel($viewer, $channel)', $body, 'posting is the channel\'s own check');
	pg_assert_contains('ws_can_read_channel($viewer, $channel)', $body, 'reading too');
}

// A save that changed the note touches its cards; the assistant's answer too.
// The touch only stamps the messages: no message, wake or inbox line.
function test_ws_channel_notes_save_touches_the_cards()
{
	$save = ws_channel_notes_test_source('ws_note_save');
	$update = strpos($save, 'db("UPDATE ws_notes SET " . implode');

	pg_assert_true($update !== false, 'the update');
	pg_assert_true(strpos($save, 'ws_note_cards_touch($note_id);', $update) !== false, 'touched after the update');

	pg_assert_contains('ws_note_cards_touch((int) $note[\'id\']);', ws_channel_notes_test_source('ws_note_claude_deliver'), 'the assistant\'s answer touches them too');

	$touch = ws_channel_notes_test_source('ws_note_cards_touch');

	pg_assert_contains('channel_id > 0 AND message_id > 0', $touch, 'channel cards only');
	pg_assert_contains('ws_message_touch((int) $message_id);', $touch, 'each message touched');

	foreach (array('ws_message_send', 'ws_notify', 'ws_item_ask', 'INSERT') as $word) {
		pg_assert_false(strpos($touch, $word) !== false, 'no ' . $word);
	}
}

// The switch touches the card, is the owner's, refuses a locked note and
// leaves a group's share to the group.
function test_ws_channel_notes_edit_switch()
{
	$body = ws_channel_notes_test_source('ws_note_channel_edit_set');

	pg_assert_contains('(int) $note[\'user_id\'] !== (int) $viewer[\'id\']', $body, 'the owner only');
	pg_assert_contains('ws_note_lock_share_refusal($note)', $body, 'a locked note refused');
	pg_assert_true(strpos($body, 'ws_note_lock_share_refusal') < strpos($body, 'UPDATE ws_note_shares'), 'refused before the write');
	pg_assert_contains('via_group = 0', $body, 'a group\'s share is the group\'s');
	pg_assert_contains('ws_message_touch((int) $share[\'message_id\']);', $body, 'the card is drawn again');
}

// The route's body is Markdown: deep headings become ###, underlined lines
// headings; the rest stays.
function test_ws_channel_notes_markdown_rules()
{
	pg_assert_same('### Deep', ws_note_channel_body('#### Deep'), '####');
	pg_assert_same('### Deeper', ws_note_channel_body('###### Deeper'), '######');
	pg_assert_same("# Title\ntext", ws_note_channel_body("Title\r\n=====\r\ntext"), 'underlined with =');
	pg_assert_same("## Part\ntext", ws_note_channel_body("Part\n---\ntext"), 'underlined with -');
	pg_assert_same("- one\n- two", ws_note_channel_body("* one\n+ two"), 'list marks');
}

// The write check: posting first, the limit refused (never cut), the card's
// message checked before anything is written; the write deletes the note
// when its card could not be posted.
function test_ws_channel_notes_write_check_refusals()
{
	$check = ws_channel_notes_test_source('ws_note_channel_write_check');

	pg_assert_contains('ws_can_post_channel($viewer, $channel)', $check, 'may not post: refused');
	pg_assert_contains('ws_note_channel_body($body)', $check, 'Markdown converted when asked');
	pg_assert_true(strpos($check, 'ws_note_channel_body($body)') < strpos($check, 'ws_note_save_check('), 'converted before the limit is counted');
	pg_assert_contains('ws_note_save_check($viewer, array(\'note_id\' => 0', $check, 'the save\'s own refusals: the limit, the note count');
	pg_assert_contains('ws_message_send_check($viewer, $channel', $check, 'the card\'s message is checked too');
	pg_assert_false(strpos($check, 'mb_substr') !== false, 'nothing is cut');

	$limit = ws_channel_notes_test_source('ws_note_save_check');

	pg_assert_contains('mb_strlen($body) > WS_NOTE_MAX', $limit, 'the limit');
	pg_assert_contains("'vars' => WS_NOTE_MAX", $limit, 'said with the limit');
	pg_assert_same(60000, WS_NOTE_MAX, 'the limit is 60,000');

	$write = ws_channel_notes_test_source('ws_note_channel_write');

	pg_assert_true(strpos($write, 'ws_note_channel_write_check(') < strpos($write, 'ws_note_save('), 'checked before written');
	pg_assert_contains("'can_edit'      => false,", $write, 'a channel\'s note takes its access from the channel, not the card\'s share');
	pg_assert_contains('ws_note_delete($viewer, $note);', $write, 'no note left without its card');
}

// The card's message is written as the caller says, nothing else.
function test_ws_channel_notes_card_send_options()
{
	pg_assert_same(array(), ws_note_card_send_options(array()), 'a person');
	pg_assert_same(array('app_id' => 4), ws_note_card_send_options(array('app_id' => '4', 'via_client_id' => 0, 'kind' => 'task')), 'an application');
	pg_assert_same(array('via_client_id' => 9), ws_note_card_send_options(array('app_id' => 0, 'via_client_id' => 9)), 'a connection');

	$share = ws_channel_notes_test_source('ws_note_share_channel');

	pg_assert_contains('ws_note_card_send_options($options)', $share, 'the share passes them on');
	pg_assert_contains("empty(\$options['can_edit']) ? 0 : 1", $share, 'and stores the switch');
}

// Who deletes a channel's note: its writer while they read the channel, and
// whoever manages the channel.
function test_ws_channel_notes_who_deletes()
{
	pg_assert_true(ws_note_channel_may_delete(true, true, false), 'the writer who reads the channel');
	pg_assert_true(!ws_note_channel_may_delete(true, false, false), 'not a writer who left the channel');
	pg_assert_true(ws_note_channel_may_delete(false, true, true), 'whoever manages the channel');
	pg_assert_true(!ws_note_channel_may_delete(false, true, false), 'not another reader or poster');

	$check = ws_channel_notes_test_source('ws_note_delete_check');

	pg_assert_contains('ws_note_channel_may_delete(', $check, 'the delete check asks the pure rule for a channel\'s note');
	pg_assert_contains('ws_can_manage_channel($viewer, $channel)', $check, 'with the channel\'s managers');

	$actions = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/actions.php');

	pg_assert_contains("case 'ws_note_delete':\n            // ws_note_delete() checks who may", $actions, 'the panel\'s delete leaves the decision to the check');
}

// The Notes tab's list and its route.
function test_ws_channel_notes_list()
{
	pg_assert_same(array('rows' => array(), 'next' => null, 'total' => 0), ws_note_channel_page_rows(0, array()), 'no channel, no notes');

	$rows = ws_channel_notes_test_source('ws_note_channel_page_rows');

	pg_assert_contains("n.owner_channel_id = '\" . (int) \$channel_id . \"'", $rows, 'only the channel\'s own notes');
	pg_assert_contains("array('n.title')", $rows, 'the search is over the titles');
	pg_assert_contains('ORDER BY n.updated_at DESC, n.id DESC', $rows, 'the latest change first');

	$page = ws_channel_notes_test_source('ws_note_channel_page');

	pg_assert_contains("ws_can_post_channel(\$viewer, \$channel) ? 'edit' : 'view'", $page, 'access is the channel\'s');

	$routes = ws_channel_note_api_routes();
	$ids = array_column($routes, 'id');

	pg_assert_true(in_array('workspace.channels.notes.list', $ids, true), 'GET /workspace/channels/{id}/notes is a route');
	pg_assert_same('ws_api_channel_notes_list', $routes[array_search('workspace.channels.notes.list', $ids, true)]['handler'], 'with its handler');
	pg_assert_true(function_exists('ws_api_channel_notes_list'), 'which exists');
}

// Moving a person's note into channel 10: the channel's own share stays as
// its card; every other share goes, with a deletion row for the owner, each
// person and each other channel, none for channel 10.
function test_ws_note_move_plan_keeps_the_card()
{
	$note = array('id' => 5, 'user_id' => 7);
	$shares = array(
		array('id' => 1, 'user_id' => 8, 'channel_id' => 0, 'via_group' => 0, 'message_id' => 0),
		array('id' => 2, 'user_id' => 0, 'channel_id' => 10, 'via_group' => 4, 'message_id' => 70),
		array('id' => 3, 'user_id' => 0, 'channel_id' => 10, 'via_group' => 0, 'message_id' => 71),
		array('id' => 4, 'user_id' => 0, 'channel_id' => 20, 'via_group' => 0, 'message_id' => 72),
	);
	$plan = ws_note_move_plan($note, $shares, 10, 7, 1000);

	pg_assert_same(3, (int) $plan['card']['id'], 'the channel\'s own share is the card, not a group\'s');
	pg_assert_same(array(1, 2, 4), array_map(function ($share) { return (int) $share['id']; }, $plan['drop']), 'every other share goes');

	$who = array_map(function ($row) { return $row['user_id'] . '/' . $row['channel_id']; }, $plan['deletions']);

	pg_assert_same(array('7/0', '8/0', '0/20'), $who, 'the owner, the person and the other channel are told it left; channel 10 keeps it');

	foreach ($plan['deletions'] as $row) {
		pg_assert_same(array(5, 7, 1000), array((int) $row['note_id'], (int) $row['deleted_by'], (int) $row['deleted_at']), 'the note, who moved it, when');
	}
}

// Not shared in the channel yet: no card, one is to be posted.
function test_ws_note_move_plan_without_card()
{
	$plan = ws_note_move_plan(array('id' => 5, 'user_id' => 7), array(
		array('id' => 2, 'user_id' => 0, 'channel_id' => 10, 'via_group' => 4, 'message_id' => 70),
	), 10, 7, 1000);

	pg_assert_same(null, $plan['card'], 'a group\'s card is not the note\'s own');
	pg_assert_same(1, count($plan['drop']), 'the group\'s share goes');
	pg_assert_same(array(array('note_id' => 5, 'user_id' => 7, 'channel_id' => 0, 'deleted_by' => 7, 'deleted_at' => 1000)), $plan['deletions'], 'only the owner\'s list loses it');
}

// The move and the copy run their checks: who owns it, the lock, the
// channel, its limit; the copy takes only a channel's note.
function test_ws_note_move_checks()
{
	$check = ws_channel_notes_test_source('ws_note_move_check');

	foreach (array("(int) \$note['user_id'] !== (int) \$viewer['id']", 'ws_note_lock_share_refusal(', 'ws_can_post_channel(', 'WS_NOTE_CHANNEL_LIMIT', 'ws_message_send_check(') as $part) {
		pg_assert_contains(stripslashes($part), $check, 'the move checks ' . stripslashes($part));
	}

	$move = ws_channel_notes_test_source('ws_note_move');

	foreach (array('ws_note_move_check(', 'ws_note_share_channel(', "owner_channel_id = '", "pinned = '0'", 'ws_note_places_forget_note(', 'ws_note_card_retire(', 'ws_note_folder_sync(') as $part) {
		pg_assert_contains($part, $move, 'the move does ' . $part);
	}

	$copy = ws_channel_notes_test_source('ws_note_copy_mine');

	pg_assert_contains('ws_note_channel_of($note) <= 0', $copy, 'the copy takes a channel\'s note only');
	pg_assert_contains("ws_note_save(\$viewer, array('note_id' => 0,", $copy, 'and writes a new note of the reader\'s, in their limit');
}

// A channel's note keeps its card: its writer owns nothing, so taking the
// card's share back is refused before any row is read.
function test_ws_note_unshare_refuses_a_channel_note()
{
	$result = ws_note_unshare(array('id' => 7), array('id' => 5, 'user_id' => 7, 'owner_channel_id' => 10), 99);

	pg_assert_same(false, $result['ok'], 'the writer cannot take the card away');
	pg_assert_same(lang('A channel\'s note keeps its card in its channel.'), $result['error'], 'and is told why');
}

// An edit of a channel's note writes no inbox line, its writer's neither;
// a person's note tells its owner and the people it is shared with.
function test_ws_note_change_told_skips_a_channel_note()
{
	pg_assert_same(array(), ws_note_change_told(array('id' => 5, 'user_id' => 7, 'owner_channel_id' => 10), array(), 3, 0), 'a channel\'s note tells nobody');
	pg_assert_same(array(7, 8), ws_note_change_told(array('id' => 5, 'user_id' => 7, 'owner_channel_id' => 0), array(8, 3, 8), 3, 0), 'a person\'s note tells its owner and the people, not the one who changed it');
	pg_assert_same(array(8), ws_note_change_told(array('id' => 5, 'user_id' => 7), array(8), 3, 7), 'nor the one skipped');
	pg_assert_contains('ws_note_change_told(', ws_channel_notes_test_source('ws_note_notify_change'), 'the inbox follows the rule');
}

// The files of a channel's note are for the channel's members only: its
// writer who left the channel loses them.
function test_ws_note_readers_of_a_channel_note_are_its_members()
{
	$members = array(10 => array(3, 4), 20 => array(5));

	pg_assert_same(array(3, 4), ws_note_readers_of(array('id' => 5, 'user_id' => 7, 'owner_channel_id' => 10), array(array('user_id' => 0, 'channel_id' => 10)), $members), 'a channel\'s note: its channel\'s members, not its writer');
	pg_assert_same(array(3, 4), ws_note_readers_of(array('id' => 5, 'user_id' => 7, 'owner_channel_id' => 10), array(), $members), 'with no card row too');
	pg_assert_same(array(7, 8, 5), ws_note_readers_of(array('id' => 5, 'user_id' => 7, 'owner_channel_id' => 0), array(array('user_id' => 8, 'channel_id' => 0), array('user_id' => 0, 'channel_id' => 20)), $members), 'a person\'s note: its owner, the people, the channels');
	pg_assert_contains('ws_note_readers_of(', ws_channel_notes_test_source('ws_note_readers'), 'the folder follows the rule');
}

// Claude reaches a channel's note only through its channel, whatever the
// owner's access; a person's note as before.
function test_ws_note_claude_reach_of_a_channel_note_asks_its_channel()
{
	$allowed = function ($channel_id) {
		return (int) $channel_id === 20;
	};

	pg_assert_same(false, ws_note_claude_reach_of(3, array('id' => 5, 'user_id' => 7, 'owner_channel_id' => 10, 'access' => 'edit'), array(), $allowed), 'a channel closed to Claude keeps its note from Claude');
	pg_assert_same(true, ws_note_claude_reach_of(3, array('id' => 5, 'user_id' => 7, 'owner_channel_id' => 20, 'access' => 'view'), array(), $allowed), 'an allowed channel gives it');
	pg_assert_same(true, ws_note_claude_reach_of(3, array('id' => 5, 'user_id' => 3, 'owner_channel_id' => 0, 'access' => 'owner'), array(), $allowed), 'a note of the owner\'s own');
	pg_assert_same(true, ws_note_claude_reach_of(3, array('id' => 5, 'user_id' => 7, 'access' => 'view'), array(array('user_id' => 3, 'channel_id' => 0)), $allowed), 'a person share');
	pg_assert_same(false, ws_note_claude_reach_of(3, array('id' => 5, 'user_id' => 7, 'access' => 'view'), array(array('user_id' => 0, 'channel_id' => 10)), $allowed), 'a share only in a closed channel');
	pg_assert_same(true, ws_note_claude_reach_of(3, array('id' => 5, 'user_id' => 7, 'access' => 'view'), array(array('user_id' => 0, 'channel_id' => 20)), $allowed), 'a share in an allowed channel');
}

// The notes a record's mentions list are read with owner_channel_id, or a
// channel's note would count its writer as its owner.
function test_ws_ref_notes_open_reads_the_owner_channel()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/refs.php');
	$start = strpos($source, 'function ws_ref_notes_open(');
	$body = substr($source, $start, strpos($source, "\nfunction ", $start + 1) - $start);

	pg_assert_contains('owner_channel_id', $body, 'the channel a note belongs to is read');
}
