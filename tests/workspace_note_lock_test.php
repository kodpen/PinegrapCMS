<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Workspace notes lock (includes/workspace/note_lock.php): the
 * PIN's shape, the times a person can choose, when an open lock still holds,
 * the e-mailed code and the masked address.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/notes.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/note_lock.php');

function test_workspace_note_lock_pin_shape()
{
	pg_assert_true(ws_note_lock_pin_valid('1234'), 'four digits');
	pg_assert_true(ws_note_lock_pin_valid('12345678'), 'eight digits');
	pg_assert_true(!ws_note_lock_pin_valid('123'), 'three digits are too few');
	pg_assert_true(!ws_note_lock_pin_valid('123456789'), 'nine digits are too many');
	pg_assert_true(!ws_note_lock_pin_valid('12a4'), 'letters refused');
	pg_assert_true(!ws_note_lock_pin_valid("1234\n"), 'a trailing new line refused');
	pg_assert_true(!ws_note_lock_pin_valid(' 1234'), 'spaces refused');
	pg_assert_true(!ws_note_lock_pin_valid('١٢٣٤'), 'other digits than 0-9 refused');
	pg_assert_true(!ws_note_lock_pin_valid(1234), 'only a string');
	pg_assert_true(!ws_note_lock_pin_valid(array('1234')), 'an array refused');
}

function test_workspace_note_lock_new_pin_typed_twice()
{
	pg_assert_same('', ws_note_lock_new_pin_refusal('2468', '2468'), 'the same twice is kept');
	pg_assert_same('pin', ws_note_lock_new_pin_refusal('24', '24'), 'its shape first');
	pg_assert_same('pin2', ws_note_lock_new_pin_refusal('2468', '2469'), 'two that differ');
	pg_assert_same('pin2', ws_note_lock_new_pin_refusal('2468', null), 'the second missing');
}

function test_workspace_note_lock_minutes()
{
	foreach (array(0, 1, 5, 15, 30, 60, 65535) as $minutes) {
		pg_assert_same($minutes, ws_note_lock_minutes((string) $minutes), $minutes . ' is a time to choose');
	}

	pg_assert_same(15, ws_note_lock_minutes(15), 'an integer too');
	pg_assert_same(null, ws_note_lock_minutes('10'), 'not offered');
	pg_assert_same(null, ws_note_lock_minutes('-5'), 'negative');
	pg_assert_same(null, ws_note_lock_minutes('5.5'), 'a fraction');
	pg_assert_same(null, ws_note_lock_minutes(''), 'empty');
	pg_assert_same(null, ws_note_lock_minutes(true), 'a boolean');
	pg_assert_same(7, count(ws_note_lock_times()), 'seven labels, one per time');
}

function test_workspace_note_lock_open_holds()
{
	$now = 1000000;
	$open = array('user_id' => 7, 'used_at' => $now - 200, 'note_id' => 0);

	pg_assert_true(ws_note_lock_open_holds($open, 7, 5, $now), 'used 200 s ago, open 5 minutes');
	pg_assert_true(!ws_note_lock_open_holds($open, 7, 1, $now), 'used 200 s ago, open 1 minute');
	pg_assert_true(!ws_note_lock_open_holds($open, 8, 5, $now), 'another person');
	pg_assert_true(!ws_note_lock_open_holds(null, 7, 5, $now), 'never opened');
	pg_assert_true(!ws_note_lock_open_holds($open + array(), 0, 5, $now), 'nobody');
	pg_assert_true(ws_note_lock_open_holds(array('user_id' => 7, 'used_at' => $now - 86400 * 30), 7, 65535, $now), 'until sign-out');
	pg_assert_true(!ws_note_lock_open_holds(array('user_id' => 7, 'used_at' => $now + 60), 7, 65535, $now), 'a use in the future is not believed');

	$one = array('user_id' => 7, 'used_at' => $now - 60, 'note_id' => 42);

	pg_assert_true(ws_note_lock_open_holds($one, 7, 0, $now, 42), 'every time: the note it was opened for');
	pg_assert_true(!ws_note_lock_open_holds($one, 7, 0, $now, 43), 'every time: not another note');
	pg_assert_true(!ws_note_lock_open_holds($one, 7, 0, $now), 'every time: not the lock as a whole');
	pg_assert_true(!ws_note_lock_open_holds(array('user_id' => 7, 'used_at' => $now - WS_NOTE_LOCK_EVERY_TIME_IDLE - 1, 'note_id' => 42), 7, 0, $now, 42), 'every time: a note left alone asks again');
}

function test_workspace_note_lock_code()
{
	$now = 2000000;
	$store = null;

	ws_note_lock_code_keep($store, 7, '123456', $now);
	pg_assert_true(strpos(json_encode($store), '123456') === false, 'the code itself is not kept');
	pg_assert_same('code_expired', ws_note_lock_code_check($store, 8, '123456', $now), 'another person');
	pg_assert_same('code', ws_note_lock_code_check($store, 7, '000000', $now + 10), 'a wrong code counts');
	pg_assert_same('', ws_note_lock_code_check($store, 7, ' 123 456 ', $now + 20), 'spaces ignored');
	pg_assert_same('code_expired', ws_note_lock_code_check($store, 7, '123456', $now + 30), 'used once');

	ws_note_lock_code_keep($store, 7, '123456', $now);
	pg_assert_same('code_expired', ws_note_lock_code_check($store, 7, '123456', $now + 601), 'ten minutes');

	ws_note_lock_code_keep($store, 7, '123456', $now);

	for ($i = 1; $i <= 4; $i++) {
		pg_assert_same('code', ws_note_lock_code_check($store, 7, '000000', $now), 'wrong ' . $i);
	}

	pg_assert_same('code_expired', ws_note_lock_code_check($store, 7, '000000', $now), 'the fifth wrong one ends it');
	pg_assert_same('code_expired', ws_note_lock_code_check($store, 7, '123456', $now), 'then the right one no longer works');
}

function test_workspace_note_lock_mask_email()
{
	pg_assert_same('e***@kodpen.com', ws_note_lock_mask_email('erdal@kodpen.com'), 'name part hidden');
	pg_assert_same('ş***@örnek.com', ws_note_lock_mask_email('şule@örnek.com'), 'by characters, not bytes');
	pg_assert_same('', ws_note_lock_mask_email('@kodpen.com'), 'no name part');
	pg_assert_same('', ws_note_lock_mask_email('nothing'), 'not an address');
	pg_assert_same('', ws_note_lock_mask_email(null), 'nothing');
}

function test_workspace_note_lock_every_time_list_level()
{
	$now = 3000000;
	$list = array('user_id' => 7, 'used_at' => $now - 30, 'note_id' => 0);

	pg_assert_true(ws_note_lock_open_holds($list, 7, 0, $now), 'every time: opened for the list, the list is open');
	pg_assert_true(!ws_note_lock_open_holds($list, 7, 0, $now, 42), 'every time: a note still asks');
}

function test_workspace_note_lock_behind()
{
	$own_locked = array('id' => 1, 'user_id' => 7, 'locked' => 1);
	$own_open = array('id' => 2, 'user_id' => 7, 'locked' => 0);
	$theirs = array('id' => 3, 'user_id' => 9, 'locked' => 1);

	pg_assert_true(ws_note_lock_behind($own_locked, 7, true, array()), 'own locked note');
	pg_assert_true(!ws_note_lock_behind($own_locked, 7, false, array()), 'no PIN, nothing is behind a lock');
	pg_assert_true(!ws_note_lock_behind($own_open, 7, true, array()), 'own unlocked note outside a locked group');
	pg_assert_true(ws_note_lock_behind($own_open, 7, true, array(2)), 'own note in a locked group');
	pg_assert_true(!ws_note_lock_behind($theirs, 7, true, array()), 'somebody else\'s lock is not the reader\'s');
	pg_assert_true(ws_note_lock_behind($theirs, 7, true, array('3')), 'a note shared with the reader in the reader\'s locked group');
}

function test_workspace_note_lock_present_closed_keeps_the_title_only()
{
	$now = 4000000;
	$view = array('has' => true, 'group_note_ids' => array(), 'minutes' => 5, 'open' => null);
	$present = array('id' => 1, 'title' => 'Bank', 'name' => 'Bank', 'excerpt' => 'IBAN TR00', 'body' => 'IBAN TR00', 'html' => '<p>IBAN TR00</p>', 'labels' => array('x' => 'y'), 'claude' => array('a'));
	$row = array('id' => 1, 'user_id' => 7, 'locked' => 1);

	$closed = ws_note_lock_present($present, $row, 7, $view, $now);
	pg_assert_same('Bank', $closed['name'], 'the title stays');
	pg_assert_true($closed['locked'] && $closed['lock_closed'], 'marked locked and closed');
	pg_assert_true(strpos(json_encode($closed), 'TR00') === false, 'nothing of the text is left');

	$untitled = ws_note_lock_present(array('title' => '', 'name' => 'IBAN TR00') + $present, $row, 7, $view, $now);
	pg_assert_true(strpos(json_encode($untitled), 'TR00') === false, 'a name taken from the first line goes too');

	$view['open'] = array('user_id' => 7, 'used_at' => $now - 10, 'note_id' => 0);
	$open = ws_note_lock_present($present, $row, 7, $view, $now);
	pg_assert_same('IBAN TR00', $open['body'], 'with the lock open the text is there');
	pg_assert_true($open['locked'] && !$open['lock_closed'], 'locked, open');

	$reader = ws_note_lock_present($present, $row, 9, array('has' => true, 'group_note_ids' => array(), 'minutes' => 5, 'open' => null), $now);
	pg_assert_true(!$reader['locked'] && !$reader['lock_closed'], 'the owner\'s mark is the owner\'s');
}

function test_workspace_note_lock_list_filter()
{
	$notes = array(
		array('id' => 1, 'title' => 'Bank', 'lock_group' => false, 'lock_closed' => true),
		array('id' => 2, 'title' => 'In a locked group', 'lock_group' => true, 'lock_closed' => true),
		array('id' => 3, 'title' => 'Shopping', 'lock_group' => false, 'lock_closed' => false),
	);

	$ids = function ($rows) { return array_map(function ($row) { return $row['id']; }, $rows); };

	pg_assert_same(array(1, 3), $ids(ws_note_lock_list_filter($notes, false, '')), 'a closed locked group\'s notes are not listed');
	pg_assert_same(array(1, 2, 3), $ids(ws_note_lock_list_filter($notes, true, '')), 'with the list open they are');
	pg_assert_same(array(1, 3), $ids(ws_note_lock_list_filter($notes, false, 'bank')), 'a closed note found by its title');
	pg_assert_same(array(3), $ids(ws_note_lock_list_filter($notes, false, 'IBAN')), 'not by its text');
}

function test_workspace_note_lock_groups_redact()
{
	$groups = array(array('id' => 1, 'locked' => true, 'count' => 4), array('id' => 2, 'locked' => false, 'count' => 2));

	$closed = ws_note_lock_groups_redact($groups, false);
	pg_assert_same(null, $closed[0]['count'], 'a closed locked group has no count');
	pg_assert_true($closed[0]['lock_closed'] && !$closed[1]['lock_closed'], 'marked');
	pg_assert_same(2, $closed[1]['count'], 'others keep theirs');
	pg_assert_same(4, ws_note_lock_groups_redact($groups, true)[0]['count'], 'open: the count is back');
}

function test_workspace_note_lock_leave_out()
{
	$rows = array(array('id' => 1, 'user_id' => 7, 'locked' => 1), array('id' => 2, 'user_id' => 7, 'locked' => 0), array('id' => 3, 'user_id' => 9, 'locked' => 0));
	$view = array('has' => true, 'group_note_ids' => array(3));
	$kept = ws_note_lock_leave_out($rows, 7, $view);

	pg_assert_same(array(2), array_column($kept['rows'], 'id'), 'locked and in a locked group left out');
	pg_assert_same(2, $kept['left_out'], 'counted');
	pg_assert_same(3, count(ws_note_lock_leave_out($rows, 7, array('has' => false))['rows']), 'without a PIN nothing is left out');
}
