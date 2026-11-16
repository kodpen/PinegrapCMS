<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for content review (includes/fn/content_review.php): the state of a
 * review date at the day's edges in the site's time, the next date after a
 * review, which items count, who may be an item's person. The runner has no
 * connection, so only the pure rules are run here.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/content_review.php');

function test_content_review_state_day_edges()
{
	pg_assert_same('overdue', pg_content_review_state('2026-11-15', '2026-11-16'), 'yesterday: overdue');
	pg_assert_same('due_soon', pg_content_review_state('2026-11-16', '2026-11-16'), 'today: due soon, not overdue');
	pg_assert_same('due_soon', pg_content_review_state('2026-12-16', '2026-11-16'), 'the 30th day: due soon');
	pg_assert_same('later', pg_content_review_state('2026-12-17', '2026-11-16'), 'the 31st day: later');
	pg_assert_same('not_set', pg_content_review_state(null, '2026-11-16'), 'no date: not set');
	pg_assert_same('not_set', pg_content_review_state('', '2026-11-16'), 'an empty date: not set');
	pg_assert_same('not_set', pg_content_review_state('0000-00-00', '2026-11-16'), 'a zero date: not set');
	pg_assert_same('not_set', pg_content_review_state('2026-02-30', '2026-11-16'), 'an impossible date: not set');
	pg_assert_same('overdue', pg_content_review_state('2025-12-31', '2026-01-01'), 'across a year: overdue');
}

function test_content_review_today_is_the_sites_day()
{
	$zone = date_default_timezone_get();
	// 2026-11-16 22:30 UTC is already 2026-11-17 in Istanbul (UTC+3).
	date_default_timezone_set('Europe/Istanbul');
	$istanbul = pg_content_review_today(1794868200);
	date_default_timezone_set('UTC');
	$utc = pg_content_review_today(1794868200);
	date_default_timezone_set($zone);

	pg_assert_same('2026-11-17', $istanbul, 'the site\'s zone decides the day');
	pg_assert_same('2026-11-16', $utc, 'the same moment in UTC');
}

function test_content_review_next_date()
{
	pg_assert_same('2027-02-16', pg_content_review_next_date('2026-11-16', 3), 'three months');
	pg_assert_same('2027-05-16', pg_content_review_next_date('2026-11-16', 6), 'six months');
	pg_assert_same('2027-11-16', pg_content_review_next_date('2026-11-16', 12), 'twelve months');
	pg_assert_same('2027-02-28', pg_content_review_next_date('2026-11-30', 3), 'a month end is kept inside the month');
	pg_assert_same(null, pg_content_review_next_date('2026-02-29', 12), 'an impossible start is no start');
	pg_assert_same('2029-02-28', pg_content_review_next_date('2028-02-29', 12), 'a leap day a year on');
	pg_assert_same(null, pg_content_review_next_date('2026-11-16', 0), 'no interval: the date is cleared');
	pg_assert_same(null, pg_content_review_next_date('2026-11-16', 5), 'an interval not offered: cleared');
	pg_assert_same(null, pg_content_review_next_date('', 3), 'no today: cleared');
}

function test_content_review_intervals()
{
	pg_assert_same(array(0, 3, 6, 12), pg_content_review_intervals(), 'not at all, 3, 6, 12 months');
	pg_assert_same(6, pg_content_review_interval('6'), 'a posted value');
	pg_assert_same(0, pg_content_review_interval('7'), 'an unknown value reads as none');
}

function test_content_review_item_counts()
{
	pg_assert_true(pg_content_review_item_counts('page', array('exists' => true)), 'a page');
	pg_assert_false(pg_content_review_item_counts('page', array('exists' => false)), 'a page that is gone');
	pg_assert_false(pg_content_review_item_counts('page', array('exists' => true, 'in_bin' => true)), 'a page in the Recycle Bin is not counted');
	pg_assert_true(pg_content_review_item_counts('record', array('exists' => true, 'collection' => 1)), 'a record of a collection');
	pg_assert_false(pg_content_review_item_counts('record', array('exists' => true, 'collection' => 0)), 'a record of a form that is no collection is refused');
	pg_assert_false(pg_content_review_item_counts('record', array('exists' => true)), 'a record whose form is unknown is refused');
	pg_assert_false(pg_content_review_item_counts('product', array('exists' => true, 'collection' => 1)), 'another kind is refused');
	pg_assert_same('', pg_content_review_kind('Page '), 'kinds are exact');
	pg_assert_same('record', pg_content_review_kind('record'), 'record');
}

function test_content_review_person_may_edit()
{
	$folders = array(4, 9);

	pg_assert_true(pg_content_review_person_may_edit('page', array('role' => 2), 7, $folders), 'a manager edits every page');
	pg_assert_true(pg_content_review_person_may_edit('record', array('role' => 1), 7, $folders), 'a designer edits every record');
	pg_assert_true(pg_content_review_person_may_edit('page', array('role' => 3), 9, $folders), 'a user with the page\'s folder');
	pg_assert_false(pg_content_review_person_may_edit('page', array('role' => 3), 7, $folders), 'a person without the right is refused');
	pg_assert_true(pg_content_review_person_may_edit('record', array('role' => 3, 'manage_forms' => 1), 4, $folders), 'a user who manages forms in the folder');
	pg_assert_false(pg_content_review_person_may_edit('record', array('role' => 3, 'manage_forms' => 0), 4, $folders), 'a user without forms is refused for a record');
	pg_assert_false(pg_content_review_person_may_edit('page', array('role' => 4), 9, $folders), 'no panel role is refused');
	pg_assert_false(pg_content_review_person_may_edit('page', array(), 9, $folders), 'no user is refused');
	pg_assert_false(pg_content_review_person_may_edit('product', array('role' => 0), 9, $folders), 'another kind is refused');
}

function content_review_test_source($relative)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $relative);
}

function test_content_review_write_problem()
{
	pg_assert_same('', pg_content_review_write_problem(true, 5, true, '2026-12-01'), 'a counted item, an allowed person, a date');
	pg_assert_same('', pg_content_review_write_problem(true, 0, false, null), 'nobody and no date');
	pg_assert_same('item', pg_content_review_write_problem(false, 0, true, null), 'a record of a form that is no collection is refused');
	pg_assert_same('person', pg_content_review_write_problem(true, 5, false, null), 'a person without the right is refused');
	pg_assert_same('date', pg_content_review_write_problem(true, 0, true, '2026-02-30'), 'an impossible date is refused');
}

function test_content_review_sweep_removes_gone_items()
{
	$sql = pg_content_review_sweep_sql(250);
	$all = implode("\n", $sql);

	pg_assert_contains("item_kind = 'page'", $sql[0], 'pages first');
	pg_assert_contains('NOT EXISTS (SELECT 1 FROM page WHERE page.page_id = content_review.item_id)', $sql[0], 'a page that is gone loses its row');
	pg_assert_contains('NOT EXISTS (SELECT 1 FROM forms WHERE forms.id = content_review.item_id)', $sql[1], 'a record that is gone loses its row');
	pg_assert_contains("item_kind NOT IN ('page', 'record')", $sql[2], 'an unknown kind goes');
	pg_assert_false(strpos($all, 'page_folder') !== false, 'a page in the Recycle Bin keeps its row');
	pg_assert_false(strpos($all, 'collection') !== false, 'a record of a form no longer a collection keeps its row');
	pg_assert_contains('SET reviewed_by = 0', $sql[3], 'a reviewer whose account is gone is cleared');
	pg_assert_contains('NOT EXISTS (SELECT 1 FROM user WHERE user.user_id = content_review.reviewed_by)', $sql[3], 'only when the account is gone');
	pg_assert_false(strpos($sql[3], 'reviewed_at') !== false, 'when it was reviewed stays');
	pg_assert_same(4, substr_count($all, 'LIMIT 250'), 'every statement is bounded');
	pg_assert_false(stripos($all, 'CURDATE') !== false || stripos($all, 'NOW()') !== false, 'no database clock');
}

function test_content_review_hooks()
{
	$migration = content_review_test_source('includes/migrations/2026.11.0.php');
	pg_assert_contains('upgrade_2026_11_0_content_review();          // 11.0.226', $migration, 'the step is called');
	pg_assert_contains("install_create_table('content_review'", $migration, 'created with the idempotent helper');
	pg_assert_contains('review_on       DATE NULL DEFAULT NULL', $migration, 'no zero date');
	pg_assert_contains('PRIMARY KEY (item_kind, item_id)', $migration, 'one row per item');
	$step = substr($migration, (int) strpos($migration, 'function upgrade_2026_11_0_content_review()'));
	pg_assert_false(strpos($step, 'updated_at') !== false, 'no column without a reader');
	pg_assert_contains('announced_on    DATE NULL DEFAULT NULL', $step, 'the announce mark');

	pg_assert_contains("'content_review',", content_review_test_source('install/index.php'), 'listed in get_tables()');
	pg_assert_contains('pg_content_review_sweep_due()', content_review_test_source('job.php'), 'the general job sweeps');
	pg_assert_contains("/includes/fn/content_review.php');", content_review_test_source('functions.php'), 'the module is loaded');
}
