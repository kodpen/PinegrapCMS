<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for Workspace note groups (includes/workspace/note_groups.php): how a
 * group name is kept, how a note is put at a place in a group's order and how
 * an asked order of groups is laid over the order there is.
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
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/note_groups.php');

function test_workspace_note_group_name_trimmed_and_cut()
{
	pg_assert_same('Work notes', ws_note_group_name("  Work \n\t notes  "), 'spaces run together and trimmed');
	pg_assert_same('', ws_note_group_name('   '), 'only spaces is no name');
	pg_assert_same(100, mb_strlen(ws_note_group_name(str_repeat('ş', 150))), 'cut to 100 characters, not bytes');
	pg_assert_same('Ab', ws_note_group_name(str_repeat(' ', 2) . 'Ab'), 'leading spaces go');
	pg_assert_same(100, mb_strlen(ws_note_group_name(str_repeat('a', 98) . ' b')), 'a name of exactly 100 stays whole');
	pg_assert_same(99, mb_strlen(ws_note_group_name(str_repeat('a', 99) . ' b')), 'a cut that ends in a space is trimmed again');
}

function test_workspace_note_group_order_insert()
{
	pg_assert_same(array(5, 1, 2, 3), ws_note_group_order_insert(array(1, 2, 3), 5, 0), 'first');
	pg_assert_same(array(1, 2, 3, 5), ws_note_group_order_insert(array(1, 2, 3), 5, -1), 'below 0 is the last');
	pg_assert_same(array(1, 2, 3, 5), ws_note_group_order_insert(array(1, 2, 3), 5, 99), 'past the end is the last');
	pg_assert_same(array(1, 3, 2), ws_note_group_order_insert(array(1, 2, 3), 2, 2), 'moved along: taken out first, then put in');
	pg_assert_same(array(3, 1, 2), ws_note_group_order_insert(array(1, 2, 3), 3, 0), 'moved to the front');
	pg_assert_same(array(1, 4), ws_note_group_order_insert(array(1, 1, 0, -2), 4, 5), 'repeats and empty ids dropped');
	pg_assert_same(array(7), ws_note_group_order_insert(array(), 7, 0), 'into an empty group');
}

function test_workspace_note_group_order_merge()
{
	pg_assert_same(array(3, 1, 2), ws_note_group_order_merge(array(1, 2, 3), array(3, 1, 2)), 'the order asked');
	pg_assert_same(array(3, 1, 2), ws_note_group_order_merge(array(1, 2, 3), array(3)), 'ids not named keep their order after the ones named');
	pg_assert_same(array(2, 1, 3), ws_note_group_order_merge(array(1, 2, 3), array(9, 2, 2, 'x')), 'unknown ids and repeats dropped');
	pg_assert_same(array(1, 2), ws_note_group_order_merge(array(1, 2), array()), 'nothing asked changes nothing');
}

function test_workspace_note_group_present()
{
	$group = ws_note_group_present(array('id' => '4', 'name' => 'Ideas', 'color' => '99', 'sort' => '2', 'locked' => '1', 'notes' => '6'));

	pg_assert_same(4, $group['id'], 'id');
	pg_assert_same(0, $group['color'], 'a colour that is not a palette place is none');
	pg_assert_same('', $group['hex'], 'no colour code for none');
	pg_assert_same(true, $group['locked'], 'locked');
	pg_assert_same(6, $group['count'], 'count');

	$group = ws_note_group_present(array('id' => 5, 'name' => 'B', 'color' => 7));

	pg_assert_same(7, $group['color'], 'a palette place is kept');
	pg_assert_same(ws_palette_hex(7), $group['hex'], 'and drawn as its colour code');
	pg_assert_same(0, $group['count'], 'no count read is 0');
}
