<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the task priority badge of the Workspace screen: every task list
 * (Today, My tasks, a channel's Tasks tab, the record window) draws high and
 * urgent with the one priorityBadge(), high in the warning colour and urgent
 * in the danger colour (#5188).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_workspace_priority_badge_one_helper()
{
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace.js');
	$css = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/css/backend.src.css');

	pg_assert_contains('function priorityBadge(priority)', $js);
	pg_assert_contains("'ws-priority-badge ws-priority-badge-' + priority", $js);
	pg_assert_true(substr_count($js, 'priorityBadge(task.priority)') >= 2, 'Today and taskRows() both use it');
	pg_assert_false(strpos($js, "el('span', 'badge theme-danger', BOOT.priorities[task.priority])") !== false, 'no red badge for both levels');
	pg_assert_false(strpos($js, "'ws-home-priority") !== false, 'the Today-only class is gone');
	pg_assert_true((bool) preg_match('~\.ws-priority-badge\s*\{[^}]*--pg-warning~', $css), 'high: warning colour');
	pg_assert_true((bool) preg_match('~\.ws-priority-badge-urgent\s*\{[^}]*--pg-danger~', $css), 'urgent: danger colour');
}
