<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the new dashboard's Next up card
 * (includes/dashboard/v2/cards/queue.php): which counts make a row, their
 * order, their actions and the row limit.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_DASHBOARD_V2')) {
	define('PG_DASHBOARD_V2', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/dashboard/v2/cards.php');
require_once(PG_FUNCTIONS_DIR . '/includes/dashboard/v2/cards/queue.php');

function test_dashboard_v2_queue_nothing_waiting_makes_no_rows()
{
	pg_assert_same(array(), pg_dashboard_v2_queue_rows(array()), 'no sources');
	pg_assert_same(array(), pg_dashboard_v2_queue_rows(array(
		'forms'    => array('count' => 0, 'oldest' => 0, 'url' => ''),
		'comments' => array('count' => 0),
		'seo'      => array('critical' => 0, 'average' => 82),
		'a11y'     => null,
	)), 'only zero counts and a source the person may not see');
}

function test_dashboard_v2_queue_rows_most_urgent_first()
{
	$rows = pg_dashboard_v2_queue_rows(array(
		'forms'     => array('count' => 3, 'oldest' => 0, 'url' => 'view_submitted_forms.php?x=1'),
		'comments'  => array('count' => 1),
		'seo'       => array('critical' => 2, 'average' => 61),
		'a11y'      => array('issues' => 6, 'pages' => 1),
		'overdue'   => array('count' => 1, 'open' => 4),
		'approvals' => array('count' => 2, 'url' => ''),
	));

	pg_assert_same(array('team', 'content', 'team', 'form', 'content', 'content'), array_column($rows, 'module'), 'modules in order');
	pg_assert_same(array('bad', 'bad', 'warn', 'warn', 'warn', 'plain'), array_column($rows, 'tone'), 'the urgent ones first');
	pg_assert_same('workspace_tasks.php', $rows[0]['url'], 'overdue tasks open the task list');
	pg_assert_same('view_pages.php?filter=seo_critical', $rows[1]['url'], 'critical pages open the filtered list');
	pg_assert_same('workspace.php', $rows[2]['url'], 'approvals without an address open the Workspace');
	pg_assert_same('view_submitted_forms.php?x=1', $rows[3]['url'], 'forms open the list on the counted period');
	pg_assert_same('1 comment waiting for moderation', $rows[4]['text'], 'one comment');
	pg_assert_same('on 1 page', $rows[5]['note'], 'one page');

	foreach ($rows as $row) {
		pg_assert_true(($row['action'] !== '') && ($row['tag'] !== ''), 'every row has a tag and an action');
	}
}

function test_dashboard_v2_queue_keeps_to_the_limit()
{
	$rows = pg_dashboard_v2_queue_rows(array(
		'seo'       => array('critical' => 2, 'average' => 61),
		'overdue'   => array('count' => 5, 'open' => 9),
		'approvals' => array('count' => 1, 'url' => 'workspace.php?approval=7'),
	), 2);

	pg_assert_same(2, count($rows), 'two rows');
	pg_assert_same('5 overdue tasks', $rows[0]['text'], 'plural');
	pg_assert_same('9 open in all', $rows[0]['note'], 'the open count');
}
