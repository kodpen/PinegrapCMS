<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for which page revision History names with the content editor whose
 * waiting change a designer applied: only the revision that apply wrote
 * (the change's steps.revision_no), never another revision with the same
 * tree; a change applied before the number was kept is matched on its
 * after_hash and the second it was applied.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/content_proposals.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/page_revisions.php');

// A revision row as pg_page_revisions_list() reads it.
function _cp_history_test_revision($no, $hash, $created_at)
{
	return array('revision_no' => $no, 'tree_hash' => $hash, 'created_at' => $created_at);
}

// An applied editor change as pg_page_revisions_list() reads it.
function _cp_history_test_change($steps, $hash, $decided_at, $editor = 'mehmet')
{
	return array('steps' => $steps, 'after_hash' => $hash, 'decided_at' => $decided_at, 'user_username' => $editor);
}

// A settings-only change keeps the tree: only the revision the apply wrote
// names the editor, not the one before with the same tree.
function test_cp_history_settings_only_change_names_its_own_revision()
{
	$changes = array(_cp_history_test_change('{"lines":["Title"],"revision_no":6}', 'tree', 1800000100));

	pg_assert_same('mehmet', pg_page_revisions_editor(_cp_history_test_revision(6, 'tree', 1800000100), $changes), 'the revision the apply wrote');
	pg_assert_same('', pg_page_revisions_editor(_cp_history_test_revision(5, 'tree', 1700000000), $changes), 'the revision before, same tree');
}

// A later save that changes only the settings keeps the tree too, and is not
// the editor's.
function test_cp_history_later_settings_only_revision_not_named()
{
	$changes = array(_cp_history_test_change('{"revision_no":6}', 'tree', 1800000100));

	pg_assert_same('', pg_page_revisions_editor(_cp_history_test_revision(7, 'tree', 1800000101), $changes), 'a second later');
	pg_assert_same('', pg_page_revisions_editor(_cp_history_test_revision(8, 'tree', 1800090000), $changes), 'a day later');
}

// A change applied before the number was kept: its tree and the second it
// was applied, which is the second of its revision or the one after.
function test_cp_history_old_change_matched_on_hash_and_second()
{
	$changes = array(_cp_history_test_change('{"lines":["Title"]}', 'tree', 1800000100));

	pg_assert_same('mehmet', pg_page_revisions_editor(_cp_history_test_revision(6, 'tree', 1800000100), $changes), 'same second');
	pg_assert_same('mehmet', pg_page_revisions_editor(_cp_history_test_revision(6, 'tree', 1800000099), $changes), 'applied the second after');
	pg_assert_same('', pg_page_revisions_editor(_cp_history_test_revision(5, 'tree', 1700000000), $changes), 'an older revision');
	pg_assert_same('', pg_page_revisions_editor(_cp_history_test_revision(7, 'tree', 1800000200), $changes), 'a later revision');
	pg_assert_same('', pg_page_revisions_editor(_cp_history_test_revision(6, 'other', 1800000100), $changes), 'another tree');
	pg_assert_same('', pg_page_revisions_editor(_cp_history_test_revision(6, 'tree', 1800000100), array(_cp_history_test_change('', 'tree', 0))), 'no time');
}

// A numbered change is matched on its number alone.
function test_cp_history_numbered_change_ignores_the_hash()
{
	$changes = array(
		_cp_history_test_change('{"revision_no":4}', 'old', 1800000000, 'ali'),
		_cp_history_test_change('{"revision_no":6}', 'tree', 1800000100, 'mehmet'),
	);

	pg_assert_same('ali', pg_page_revisions_editor(_cp_history_test_revision(4, 'old', 1800000000), $changes));
	pg_assert_same('mehmet', pg_page_revisions_editor(_cp_history_test_revision(6, 'tree', 1800000100), $changes));
	pg_assert_same('', pg_page_revisions_editor(_cp_history_test_revision(5, 'tree', 1800000100), $changes), 'same hash and second, other number');
}

// The apply keeps the number in the change's steps beside what is there.
function test_cp_history_apply_keeps_the_revision_in_steps()
{
	$steps = json_decode(pg_content_proposals_steps_revision('{"lines":["Title"]}', 6), true);

	pg_assert_same(6, $steps['revision_no']);
	pg_assert_same(array('Title'), $steps['lines']);
	pg_assert_same(array('revision_no' => 0), json_decode(pg_content_proposals_steps_revision('', 0), true), 'no revision written');

	$module = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/content_proposals.php');
	pg_assert_contains('pg_content_proposals_steps_revision((string) $row[\'steps\'], pg_page_revision_written((int) $row[\'page_id\']))', $module, 'apply keeps the number');
}

// The number is the one the save's own revision wrote, taken once.
function test_cp_history_written_number_taken_once()
{
	pg_page_revision_written(-91, 6);
	pg_assert_same(6, pg_page_revision_written(-91));
	pg_assert_same(0, pg_page_revision_written(-91), 'taken');

	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/page_revisions.php');
	pg_assert_contains('pg_page_revision_written($page_id, $revision_no);', $source, 'record keeps it');
	pg_assert_contains('$editor = pg_page_revisions_editor($row, $editors);', $source, 'the list matches on it');
}
