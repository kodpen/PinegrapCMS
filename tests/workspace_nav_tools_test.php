<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Tools tiles and the More list of the Workspace list
 * (ws_nav_groups(), includes/workspace/nav.php): which places are tiles,
 * which wait behind More, in which order, and each still under the gate of
 * its screen.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The module files hold functions and constants only; loading them runs nothing.
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php');

// Every flag of ws_nav_groups() on, for the tests that switch one off.
function workspace_nav_tools_test_flags($changes = array())
{
	return array_merge(array(
		'base'          => '/pg/',
		'notes'         => true,
		'wtables'       => true,
		'calendar_feed' => true,
		'actions'       => true,
		'messages'      => true,
		'flows'         => true,
		'mcp'           => true,
		'memory'        => true,
	), $changes);
}

// The keys of one group of ws_nav_groups(), none when the group is missing.
function workspace_nav_tools_test_keys($groups, $group)
{
	return array_map(function ($item) {
		return $item['key'];
	}, $groups[$group] ?? array());
}

function test_workspace_nav_tools_are_the_daily_screens_in_the_drawn_order()
{
	$groups = ws_nav_groups(array('role' => 1, 'settings' => true), workspace_nav_tools_test_flags());

	pg_assert_same(array('tasks', 'notes', 'wtables', 'flows', 'calendar', 'my_calendar', 'board', 'timeline'), workspace_nav_tools_test_keys($groups, 'tools'), 'the tiles, My Tasks first');
	pg_assert_same(array('scheduled', 'mcp', 'calendar_feed', 'memory', 'archived', 'audit'), workspace_nav_tools_test_keys($groups, 'more'), 'behind More');
	pg_assert_false(isset($groups['work']), 'Work and planning is gone');
	pg_assert_false(isset($groups['records']), 'Decisions and archive is gone');
}

function test_workspace_nav_tools_keep_the_gate_of_each_screen()
{
	$member = ws_nav_groups(array('role' => 3, 'settings' => false), workspace_nav_tools_test_flags(array(
		'notes' => false,
		'mcp'   => false,
		'memory' => false,
	)));

	pg_assert_same(array('tasks', 'wtables', 'flows', 'calendar', 'my_calendar', 'board', 'timeline'), workspace_nav_tools_test_keys($member, 'tools'), 'no Notes or E-mails without their tables and boxes');
	pg_assert_same(array('scheduled', 'calendar_feed', 'archived'), workspace_nav_tools_test_keys($member, 'more'), 'no MCP connections without a server, no general memory without an agent channel to post in, no private channels below staff');
}

// The general memory opens as the channel screen's drawer, only for whoever
// keeps it (ws_memory_general_can()).
function test_workspace_nav_more_lists_the_general_memory_under_its_gate()
{
	$groups = ws_nav_groups(array('role' => 3, 'settings' => false), workspace_nav_tools_test_flags());
	$memory = null;

	foreach ($groups['more'] as $item) {
		if ($item['key'] === 'memory') {
			$memory = $item;
		}
	}

	pg_assert_true(is_array($memory), 'listed behind More');
	pg_assert_same('/pg/workspace.php?view=home&panel=memory', $memory['url'], 'the channel screen opens its drawer');

	$off = ws_nav_groups(array('role' => 3, 'settings' => false), workspace_nav_tools_test_flags(array('memory' => false)));
	pg_assert_false(in_array('memory', workspace_nav_tools_test_keys($off, 'more'), true), 'hidden from whoever keeps no general memory');
}

function test_workspace_nav_tools_and_more_have_titles()
{
	$titles = ws_nav_group_titles();

	pg_assert_true(isset($titles['tools']) && ($titles['tools'] !== ''), 'Tools');
	pg_assert_true(isset($titles['more']) && ($titles['more'] !== ''), 'More');
}

// The long names have a short one for the tile; the full name stays its label.
function test_workspace_nav_tools_long_names_have_short_ones()
{
	$groups = ws_nav_groups(array('role' => 1, 'settings' => true), workspace_nav_tools_test_flags());
	$short = array();

	foreach ($groups['tools'] as $item) {
		if (isset($item['short'])) {
			$short[$item['key']] = $item['short'];
		}
	}

	// Flows is named Automation flows, as its main menu row; the tile keeps
	// the short name.
	pg_assert_same(array('flows' => 'Flows', 'calendar' => 'Calendar', 'board' => 'Board', 'timeline' => 'Decisions'), $short, 'Flows, Calendar, Board and Decisions');
}

// Workflow tables are the workspace's Data: the tile says so, while its key,
// icon and address stay as they were.
function test_workspace_nav_tools_tables_are_named_data()
{
	$groups = ws_nav_groups(array('role' => 3, 'settings' => false), workspace_nav_tools_test_flags());
	$tile = null;

	foreach ($groups['tools'] as $item) {
		if ($item['key'] === 'wtables') {
			$tile = $item;
		}
	}

	pg_assert_same('Data', $tile['label'] ?? '', 'the tile is named Data');
	pg_assert_same('/pg/workspace_wtables.php', $tile['url'] ?? '', 'the address keeps its name');
	pg_assert_same('wtables', ws_side_screen('/pg/workspace_wtables.php'), 'the screen keeps its key');
}
