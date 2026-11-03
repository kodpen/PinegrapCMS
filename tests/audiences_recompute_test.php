<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the recompute of contact groups filled by rules
 * (includes/fn/audiences.php): the difference a page writes, the chunked
 * statements, the page and due queries, the budget stop and the job hook.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_audiences_diff_on_fixed_sets()
{
	pg_assert_same(
		array('add' => array(1, 4), 'remove' => array(7, 9), 'convert' => array(), 'drop' => array()),
		pg_audience_diff(array(4, 1, 2, 3), array(2, 3, 7, 9), array()),
		'new contacts added, the ones no longer selected removed'
	);

	pg_assert_same(
		array('add' => array(5), 'remove' => array(), 'convert' => array(6), 'drop' => array()),
		pg_audience_diff(array(5, 6, 6, '6', 0, -3), array(), array(6)),
		'a member by hand is not added again, its row becomes a rule row; repeats and bad ids ignored'
	);

	pg_assert_same(
		array('add' => array(), 'remove' => array(8, 11), 'convert' => array(), 'drop' => array()),
		pg_audience_diff(array(), array(8), array(8, 11)),
		'every row of a contact the rule drops goes, whatever its source'
	);

	pg_assert_same(array('add' => array(), 'remove' => array(), 'convert' => array(), 'drop' => array()), pg_audience_diff(array(3), array(3), array()), 'nothing to do');
}

function test_audiences_write_in_chunks()
{
	$add = range(1, PG_AUDIENCE_WRITE_CHUNK + 1);
	$queries = pg_audience_write_queries(7, array('add' => $add, 'remove' => array(20, 21)));

	pg_assert_same(3, count($queries), 'two insert chunks and one delete');
	pg_assert_same(PG_AUDIENCE_WRITE_CHUNK * 2, count($queries[0]['params']));
	pg_assert_same(array(PG_AUDIENCE_WRITE_CHUNK + 1, 7), $queries[1]['params']);
	pg_assert_same("INSERT INTO contacts_contact_groups_xref (contact_id, contact_group_id, source) VALUES (?, ?, 'rule')", $queries[1]['sql']);
	pg_assert_same("DELETE FROM contacts_contact_groups_xref WHERE contact_group_id = ? AND contact_id IN (?, ?)", $queries[2]['sql']);
	pg_assert_same(array(7, 20, 21), $queries[2]['params']);

	foreach ($queries as $query) {
		pg_assert_same(substr_count($query['sql'], '?'), count($query['params']), 'placeholders fit');
	}

	pg_assert_same(array(), pg_audience_write_queries(7, array('add' => array(), 'remove' => array())), 'no change, no statement');
}

function test_audiences_page_query()
{
	$query = pg_audience_sql_page(array('conditions' => array(array('key' => 'customer', 'op' => 'yes'))), 100, 10100, 1800000000);

	$tail = ' AND contacts.id > ? AND contacts.id <= ?';
	pg_assert_true(substr($query['sql'], -strlen($tail)) === $tail, 'narrowed to the page');
	pg_assert_same(array(100, 10100), $query['params']);

	$nobody = pg_audience_sql_page(array(), 0, 10, 1800000000);
	pg_assert_same('SELECT contacts.id FROM contacts WHERE 1 = 0 AND contacts.id > ? AND contacts.id <= ?', $nobody['sql'], 'no condition still selects nobody');
}

function test_audiences_budget_stop()
{
	pg_assert_same('done', pg_audience_next_step(true, 99.0, 5), 'the last page ends the pass whatever the time');
	pg_assert_same('next', pg_audience_next_step(false, 1.5, 5));
	pg_assert_same('stop', pg_audience_next_step(false, 5.0, 5), 'the budget spent keeps the cursor');
}

function test_audiences_due_order()
{
	$due = pg_audience_due_sql(1800000000);

	pg_assert_contains("kind = 'dynamic'", $due['sql']);
	pg_assert_contains('compute_cursor > 0 OR computed_at <= ?', $due['sql'], 'a pass under way or an hour since the last');
	pg_assert_contains('ORDER BY (compute_cursor > 0) DESC, computed_at, id', $due['sql'], 'the one waiting longest first');
	pg_assert_same(array(1800000000 - PG_AUDIENCE_INTERVAL), $due['params']);
	pg_assert_same(20, PG_AUDIENCE_TICK_SECONDS);
	pg_assert_same(10000, PG_AUDIENCE_PAGE);
	pg_assert_same(3600, PG_AUDIENCE_INTERVAL);
}

function test_audiences_job_without_the_upgrade()
{
	pg_assert_same(array('groups' => 0, 'added' => 0, 'removed' => 0), pg_audience_job_run(), 'nothing before the upgrade');
	pg_assert_same(null, pg_audience_recompute(1, 5));

	$job = (string) file_get_contents(PG_FUNCTIONS_DIR . '/job.php');
	pg_assert_contains('$audiences = pg_audience_job_run();', $job, 'the general job runs it');

	$actions = pg_panel_actions_audience_row();
	pg_assert_same('pg_panel_audience_refresh', $actions['handler']);
	pg_assert_true($actions['token'] && $actions['write'], 'Refresh now checks the token');
}

function pg_panel_actions_audience_row()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/panel/actions.php');

	if (preg_match("/'audience_refresh' => array\\('file' => 'audiences\\.php', 'handler' => '([a-z_]+)', 'exempt' => false, 'any_role' => true, 'token' => (true|false), 'write' => (true|false)\\)/", $source, $m) !== 1) {
		return array('handler' => '', 'token' => false, 'write' => false);
	}

	return array('handler' => $m[1], 'token' => $m[2] === 'true', 'write' => $m[3] === 'true');
}
