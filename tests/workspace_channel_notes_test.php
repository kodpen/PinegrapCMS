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
	pg_assert_contains("'can_edit'      => !empty(\$data['channel_edit'])", $write, 'the switch goes to the share');
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
