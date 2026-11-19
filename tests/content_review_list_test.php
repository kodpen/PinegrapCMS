<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Content review list (includes/fn/content_review_list.php):
 * the filters each role is offered, the condition of each filter, which
 * filters page through the items' own tables and how they sort, the list's
 * ids, the branches' SQL, the cells' states and who may do which action.
 * The runner has no connection, so only the pure parts are run here.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/content_review_list.php');

function test_content_review_list_filters_by_role()
{
	pg_assert_same(array('all', 'overdue', 'soon', 'nobody', 'notset', 'mine'), array_keys(pg_content_review_list_filters(2)), 'a manager gets every filter, All first');
	pg_assert_same(array('mine', 'overdue', 'soon'), array_keys(pg_content_review_list_filters(3)), 'a user gets only their own items');
	pg_assert_same('all', pg_content_review_list_filter(null, 0), 'no filter: All');
	pg_assert_same('notset', pg_content_review_list_filter('notset', 1), 'an offered filter stays');
	pg_assert_same('mine', pg_content_review_list_filter('all', 3), 'a user cannot ask for All');
	pg_assert_same('mine', pg_content_review_list_filter('notset', 3), 'nor for Not set');
	pg_assert_same('all', pg_content_review_list_filter(array('x'), 2), 'an array is no filter');
}

function test_content_review_list_conditions()
{
	$overdue = pg_content_review_list_condition('overdue', '2026-11-16', 7, 2);
	pg_assert_same('(cr.review_on < ?)', $overdue['sql'], 'overdue: before today');
	pg_assert_same(array('2026-11-16'), $overdue['params'], 'today is a bound value, never CURDATE()');

	$soon = pg_content_review_list_condition('soon', '2026-11-16', 7, 2);
	pg_assert_same(array('2026-11-16', '2026-12-16'), $soon['params'], 'due soon: today to the 30th day');

	$nobody = pg_content_review_list_condition('nobody', '2026-11-16', 7, 2);
	pg_assert_same('(cr.review_on IS NOT NULL AND cr.user_id = 0)', $nobody['sql'], 'nobody\'s: a date and no person');

	$mine = pg_content_review_list_condition('mine', '2026-11-16', 7, 2);
	pg_assert_same(array('sql' => '(cr.user_id = ?)', 'params' => array('7')), $mine, 'mine: the signed-in user');

	$user = pg_content_review_list_condition('overdue', '2026-11-16', 9, 3);
	pg_assert_same('(cr.review_on < ?) AND (cr.user_id = ?)', $user['sql'], 'a user is narrowed to their own items in every filter');
	pg_assert_same(array('2026-11-16', '9'), $user['params'], 'with their id bound');

	$all = pg_content_review_list_condition('all', '2026-11-16', 7, 2);
	pg_assert_same('(cr.review_on IS NOT NULL)', $all['sql'], 'All: the dated part comes from content_review');
	pg_assert_same(false, strpos(implode(' ', array($overdue['sql'], $soon['sql'], $all['sql'])), 'CURDATE') !== false, 'no CURDATE()');
}

function test_content_review_list_ids()
{
	pg_assert_same(84, pg_content_review_list_id('page', 42), 'a page is even');
	pg_assert_same(85, pg_content_review_list_id('record', 42), 'a record is odd');
	pg_assert_same(array('kind' => 'page', 'item_id' => 42), pg_content_review_list_id_parts(84), 'back to the page');
	pg_assert_same(array('kind' => 'record', 'item_id' => 42), pg_content_review_list_id_parts('85'), 'back to the record');
	pg_assert_same(null, pg_content_review_list_id_parts(1), 'no item 0');
	pg_assert_same(null, pg_content_review_list_id_parts('-4'), 'no negative id');
	pg_assert_same(null, pg_content_review_list_id_parts('8 OR 1'), 'no text');
}

function test_content_review_list_all_and_not_set_page_through_the_items()
{
	$parts = array('bin' => array(5, 9), 'records' => true, 'traffic' => '', 'views' => 'NULL');
	$condition = pg_content_review_list_condition('all', '2026-11-16', 1, 0);

	$all = pg_content_review_list_branches('all', $condition, $parts);
	pg_assert_same(4, count($all), 'All: dated pages and records, then undated pages and records');
	pg_assert_true(strpos($all[2]['sql'], 'FROM page LEFT JOIN content_review AS cr') !== false, 'undated pages read the page table');
	pg_assert_true(strpos($all[3]['sql'], 'FROM forms JOIN custom_form_pages') !== false, 'undated records read forms');
	pg_assert_true(strpos($all[3]['sql'], "'9999-12-31' AS review_sort") !== false, 'an undated branch sorts by a constant, so forms keeps its key order');
	pg_assert_true(strpos($all[0]['sql'], 'page.page_folder NOT IN (5, 9)') !== false, 'a binned page is left out');
	pg_assert_true(strpos($all[1]['sql'], 'cfp.collection = 1') !== false, 'a record counts only in a collection');

	$notset = pg_content_review_list_branches('notset', pg_content_review_list_condition('notset', '2026-11-16', 1, 0), $parts);
	pg_assert_same(2, count($notset), 'Not set: only the undated items');
	pg_assert_true(strpos($notset[0]['sql'], 'WHERE (cr.review_on IS NULL)') !== false, 'no row, or a row without a date');

	$overdue = pg_content_review_list_branches('overdue', pg_content_review_list_condition('overdue', '2026-11-16', 1, 0), $parts);
	pg_assert_same(2, count($overdue), 'a filter of a date reads content_review alone');
	pg_assert_true(strpos($overdue[0]['sql'], 'FROM content_review AS cr JOIN page') !== false, 'joined to its page by the primary key');
	pg_assert_same(array('2026-11-16'), $overdue[0]['params'], 'with the filter\'s values');

	$no_records = pg_content_review_list_branches('all', $condition, array('records' => false));
	pg_assert_same(2, count($no_records), 'no collections: pages only');
}

function test_content_review_list_sorts()
{
	$all = pg_content_review_list_columns('all');
	pg_assert_same('', $all['name']['sort'], 'All does not sort by name: that sorts forms whole');
	pg_assert_same('', $all['views']['sort'], 'nor by views');
	pg_assert_same('review_sort', $all['review']['sort'], 'All sorts by the review date, overdue first');

	$mine = pg_content_review_list_columns('mine');
	pg_assert_same('item_name', $mine['name']['sort'], 'a bounded filter sorts by name');
	pg_assert_same('views', $mine['views']['sort'], 'and by views');
	pg_assert_same(false, pg_content_review_list_reads_items('overdue'), 'overdue reads content_review');
	pg_assert_same(true, pg_content_review_list_reads_items('notset'), 'not set reads the items');
}

function test_content_review_list_cells()
{
	pg_assert_same(array('label' => 'published', 'at' => 200), pg_content_review_list_last(200, 100), 'a designer page: its newest revision');
	pg_assert_same(array('label' => 'changed', 'at' => 100), pg_content_review_list_last(0, 100), 'else the stored change time, "changed"');
	pg_assert_same('none', pg_content_review_list_last(0, 0)['label'], 'nothing kept');

	pg_assert_same('none', pg_content_review_list_a11y_state('record', 3, true), 'a record has no accessibility findings: a dash');
	pg_assert_same('unchecked', pg_content_review_list_a11y_state('page', 0, false), 'before a pass has finished: Not checked');
	pg_assert_same('ok', pg_content_review_list_a11y_state('page', 0, true), 'after it: no problem found');
	pg_assert_same('found', pg_content_review_list_a11y_state('page', 2, false), 'findings show even before a full pass');

	pg_assert_same('rose', pg_content_review_list_state_tone('overdue'), 'overdue tone');
	pg_assert_same('amber', pg_content_review_list_state_tone('due_soon'), 'due soon tone');
	pg_assert_same('gray', pg_content_review_list_state_tone('not_set'), 'not set tone');
}

function test_content_review_list_actions_by_role()
{
	foreach (array('set_person', 'set_date', 'mark_reviewed', 'clear') as $action) {
		pg_assert_true(pg_content_review_list_action_allowed($action, 2), 'a manager may ' . $action);
	}

	pg_assert_true(pg_content_review_list_action_allowed('mark_reviewed', 3), 'a user may mark reviewed');
	pg_assert_same(false, pg_content_review_list_action_allowed('set_person', 3), 'a user may not set the person');
	pg_assert_same(false, pg_content_review_list_action_allowed('set_date', 3), 'nor the date');
	pg_assert_same(false, pg_content_review_list_action_allowed('clear', 3), 'nor clear');
	pg_assert_true(pg_content_review_list_action_allowed('make_task', 2), 'a manager may make a task');
	pg_assert_same(false, pg_content_review_list_action_allowed('make_task', 3), 'a user may not');
	pg_assert_same(false, pg_content_review_list_action_allowed('delete', 0), 'an unknown action is refused');
}

function test_content_review_list_action_values()
{
	pg_assert_same(array('user_id' => 7, 'review_on' => '2027-01-31', 'interval_months' => 6),
		pg_content_review_list_action_values(array('user_id' => '7', 'review_on' => '2027-01-31', 'interval_months' => '6')), 'an ISO date');
	pg_assert_same(null, pg_content_review_list_action_values(array('review_on' => '2027-02-30')), 'an impossible date is refused');
	pg_assert_same(null, pg_content_review_list_action_values(array('review_on' => 'soon')), 'text is refused');

	$none = pg_content_review_list_action_values(array('user_id' => 'x', 'review_on' => '', 'interval_months' => '5'));
	pg_assert_same(array('user_id' => 0, 'review_on' => null, 'interval_months' => 0), $none, 'no date, no person, an interval not offered');
}
