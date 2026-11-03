<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source tests for the task priority badge in workspace.js: the task lists
 * (taskRows(): a channel's Tasks tab, My tasks, the record window) and the
 * Today card (homeDue()) draw the same .ws-priority badge, high in the
 * warning colour and urgent in the danger colour, so the two can be told
 * apart everywhere.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function workspace_priority_badge_source($path)
{
	return (string) file_get_contents(dirname(__DIR__) . '/pinegrap/' . $path);
}

function test_workspace_priority_badge_shared_in_js()
{
	$js = workspace_priority_badge_source('assets/js/workspace.js');
	pg_assert_same(0, preg_match("/'badge theme-danger', BOOT\.priorities\[/", $js), 'taskRows() no longer draws high and urgent as the same danger badge');
	pg_assert_same(2, preg_match_all("/'ws-priority ws-priority-' \+ task\.priority/", $js), 'taskRows() and homeDue() draw the same priority badge');
	pg_assert_same(0, preg_match('/ws-home-priority/', $js), 'the Today-only badge class is gone');
}

function test_workspace_priority_badge_colours()
{
	$css = workspace_priority_badge_source('assets/css/backend.src.css');
	pg_assert_same(1, preg_match('/\n\.ws-priority\s*\{[^}]*--pg-warning/', $css), 'high priority is drawn in the warning colour');
	pg_assert_same(1, preg_match('/\n\.ws-priority-urgent\s*\{[^}]*--pg-danger/', $css), 'urgent priority is drawn in the danger colour');
}
