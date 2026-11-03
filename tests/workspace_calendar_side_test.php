<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the side cards of the work calendar (workspace_calendar.php,
 * workspace.js): a duration never breaks between its number and unit, and
 * the "who carries the work" rows read the whole name, the task count under
 * it (#5189).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_workspace_calendar_side_cards()
{
	$people = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/people.php');
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace.js');
	$css = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/css/backend.src.css');

	pg_assert_true((bool) preg_match('~function ws_minutes_label\(\$minutes\)\s*\{.*?str_replace\(\' \', "\\\\xC2\\\\xA0".*?\n\}~s', $people), 'ws_minutes_label() keeps number and unit together');
	pg_assert_contains("text.appendChild(el('small', 'ws-extreme-count', t('task_count', item[0].total)));", $js, 'the count sits under the name');
	pg_assert_false(strpos($js, "row.appendChild(el('span', 'ws-extreme-count'") !== false, 'no count beside the name');
	pg_assert_false((bool) preg_match('~\.ws-extreme b\s*\{[^}]*text-overflow:\s*ellipsis~', $css), 'the name is not cut');
}

