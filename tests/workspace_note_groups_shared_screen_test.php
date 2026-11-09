<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for shared note groups on the My Notes screen
 * (includes/workspace/note_group_shares.php): which folder a listed note is
 * drawn in once groups are shared with the person, and in which order.
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
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/note_group_shares.php');

function test_workspace_note_groups_shared_assign()
{
	$notes = array(
		array('id' => 1, 'group_id' => 10, 'group_sort' => 2),
		array('id' => 2, 'group_id' => 0, 'group_sort' => 0),
		array('id' => 3, 'group_id' => 0, 'group_sort' => 0),
		array('id' => 4, 'group_id' => 30, 'group_sort' => 5),
		array('id' => 5, 'group_id' => 0, 'group_sort' => 0),
	);
	$places = array(
		array('note_id' => 1, 'group_id' => 30, 'sort' => 0),
		array('note_id' => 2, 'group_id' => 30, 'sort' => 1),
		array('note_id' => 4, 'group_id' => 30, 'sort' => 5),
		array('note_id' => 5, 'group_id' => 40, 'sort' => 3),
		array('note_id' => 5, 'group_id' => 30, 'sort' => 4),
	);

	$out = ws_note_groups_shared_assign($notes, array(10), $places);

	pg_assert_same(10, $out[0]['group_id'], 'a note in an own group stays in that folder');
	pg_assert_same(0, $out[0]['shared_group'], 'and is not in a shared folder');
	pg_assert_same(array(30, 1, 30), array($out[1]['group_id'], $out[1]['group_sort'], $out[1]['shared_group']), 'a note the group brought takes its place there');
	pg_assert_same(array(0, 0), array($out[2]['group_id'], $out[2]['shared_group']), 'a note shared on its own stays loose');
	pg_assert_same(array(30, 5, 30), array($out[3]['group_id'], $out[3]['group_sort'], $out[3]['shared_group']), 'an own note put in a shared group is drawn there');
	pg_assert_same(array(30, 4), array($out[4]['group_id'], $out[4]['group_sort']), 'in two shared groups: the lower group id, the same on every load');
	pg_assert_same(array(), ws_note_groups_shared_assign(array(), array(), $places), 'no notes, nothing');
}

function test_workspace_note_groups_shared_script_in_the_bundle()
{
	$bundles = pg_js_bundles();
	$list = $bundles['assets/js/workspace.min.js'];
	$shares = array_search('assets/js/workspace_note_group_shares.js', $list, true);

	pg_assert_true($shares !== false, 'the shared groups script is bundled');
	pg_assert_true(($shares !== false) && ($shares < array_search('assets/js/workspace_replies.js', $list, true)), 'before the replies script, which stays right before workspace.js');
}
