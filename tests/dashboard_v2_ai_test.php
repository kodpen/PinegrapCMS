<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the new dashboard's Assistant card
 * (includes/dashboard/v2/cards/ai.php): chip texts and order, where a
 * proposal opens, a change's name and the card when Claude is not connected.
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

require_once(PG_FUNCTIONS_DIR . '/includes/dashboard/v2/cards/ai.php');

function test_dashboard_v2_ai_cut()
{
	pg_assert_same('Short text', pg_dashboard_v2_ai_cut("  Short \n text "), 'spaces folded');
	pg_assert_same('Write an answer to…', pg_dashboard_v2_ai_cut('Write an answer to the barista', 20), 'cut on a word');
	pg_assert_same('Abcdefghi…', pg_dashboard_v2_ai_cut('Abcdefghijklmnop', 10), 'one long word is cut where it ends');
	pg_assert_same(10, mb_strlen(pg_dashboard_v2_ai_cut('Çğıöşüçğıöşüçğıö', 10)), 'multibyte length');
}

function test_dashboard_v2_ai_proposal_url()
{
	pg_assert_same('workspace.php?channel=4&message=91', pg_dashboard_v2_ai_proposal_url(4, 91, 0), 'a channel: its message');
	pg_assert_same('workspace_tasks.php?task=12', pg_dashboard_v2_ai_proposal_url(0, 0, 12), 'a task: the task');
	pg_assert_same('', pg_dashboard_v2_ai_proposal_url(0, 5, 0), 'neither');
}

function test_dashboard_v2_ai_change_title()
{
	$types = array('page' => array('label' => 'Page'));

	pg_assert_same('H1 for 75 pages', pg_dashboard_v2_ai_change_title(array('record_type' => 'page', 'action' => 'update', 'reason' => ' H1 for 75 pages '), $types), 'the reason');
	pg_assert_same('Update Page', pg_dashboard_v2_ai_change_title(array('record_type' => 'page', 'action' => 'update', 'reason' => ''), $types), 'update');
	pg_assert_same('New Page', pg_dashboard_v2_ai_change_title(array('record_type' => 'page', 'action' => 'create'), $types), 'create');
	pg_assert_same('Delete widget', pg_dashboard_v2_ai_change_title(array('record_type' => 'widget', 'action' => 'delete'), $types), 'an unknown kind keeps its key');
}

function test_dashboard_v2_ai_chips()
{
	$task = pg_dashboard_v2_ai_chip('task', 'Answer the barista', 'workspace.php?channel=1&message=2', 100);
	pg_assert_same('task', $task['kind'], 'kind');
	pg_assert_same('Task: Answer the barista', $task['text'], 'task text');

	$change = pg_dashboard_v2_ai_chip('anything', 'H1', '', 300);
	pg_assert_same('change', $change['kind'], 'any other kind is a change');
	pg_assert_same('Change: H1', $change['text'], 'change text');

	$ordered = pg_dashboard_v2_ai_chips_order(array($task, $change, pg_dashboard_v2_ai_chip('task', 'B', '', 200)), 2);
	pg_assert_same(array(300, 200), array_column($ordered, 'time'), 'newest first, cut to the limit');
}

function test_dashboard_v2_ai_offline()
{
	$all = pg_dashboard_v2_ai_offline(true, true);
	pg_assert_same(false, $all['connected'], 'not connected');
	pg_assert_same(array('workspace_settings.php#ws-claude', 'api_settings.php'), array_column($all['buttons'], 'url'), 'both buttons');
	pg_assert_same('', $all['ask'], 'no line for somebody who connects it');

	$manager = pg_dashboard_v2_ai_offline(false, true);
	pg_assert_same(array('api_settings.php'), array_column($manager['buttons'], 'url'), 'Connections only');

	$member = pg_dashboard_v2_ai_offline(false, false);
	pg_assert_same(array(), $member['buttons'], 'no buttons');
	pg_assert_true($member['ask'] !== '', 'who connects it');
	pg_assert_true($member['note'] !== '', 'the MCP note for everyone');
}

function test_dashboard_v2_ai_summary_lines()
{
	pg_assert_same(array('3 orders wait to be shipped', 'Task #12 is late', 'Two comments wait'), pg_dashboard_v2_ai_summary_lines("- 3 orders wait to be shipped\n\n2) Task #12 is late\r\n• Two comments wait\nA fourth line"), 'list markers dropped, empty lines left out, three kept');
	pg_assert_same(array('One line only'), pg_dashboard_v2_ai_summary_lines('One line only'));
	pg_assert_same(array(), pg_dashboard_v2_ai_summary_lines("  \n "), 'nothing to show');
	$long = pg_dashboard_v2_ai_summary_lines(str_repeat('word ', 60))[0];
	pg_assert_true(mb_strlen($long) <= 140, 'a long line is cut');
	pg_assert_same('…', mb_substr($long, -1), 'and says so');
}
