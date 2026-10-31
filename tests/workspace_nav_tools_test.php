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
		'mail'          => true,
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

	pg_assert_same(array('notes', 'wtables', 'flows', 'calendar', 'board', 'timeline', 'mail'), workspace_nav_tools_test_keys($groups, 'tools'), 'the tiles');
	pg_assert_same(array('scheduled', 'mcp', 'calendar_feed', 'archived', 'audit'), workspace_nav_tools_test_keys($groups, 'more'), 'behind More');
	pg_assert_false(isset($groups['work']), 'Work and planning is gone');
	pg_assert_false(isset($groups['records']), 'Decisions and archive is gone');
}

function test_workspace_nav_tools_keep_the_gate_of_each_screen()
{
	$member = ws_nav_groups(array('role' => 3, 'settings' => false), workspace_nav_tools_test_flags(array(
		'notes' => false,
		'mail'  => false,
		'mcp'   => false,
	)));

	pg_assert_same(array('wtables', 'flows', 'calendar', 'board', 'timeline'), workspace_nav_tools_test_keys($member, 'tools'), 'no Notes or E-mails without their tables and boxes');
	pg_assert_same(array('scheduled', 'calendar_feed', 'archived'), workspace_nav_tools_test_keys($member, 'more'), 'no MCP connections without a server, no private channels below staff');
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

	pg_assert_same(array('calendar' => 'Calendar', 'board' => 'Board', 'timeline' => 'Decisions'), $short, 'Calendar, Board and Decisions');
}
