<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a channel's board holding tasks only: the groupings it offers
 * (ws_channel_board_groupings(), includes/workspace/channel_board.php), the
 * former "By kind" read as "By status" on the server and in the board's
 * script, and no card that is not a task or a table row.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_API_PANEL')) {
	define('PG_API_PANEL', true);
}

if (!defined('PG_INIT_LOADED')) {
	define('PG_INIT_LOADED', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/api.php');

function test_workspace_channel_board_tasks_only_groupings()
{
	pg_assert_same(array('status', 'person', 'date'), array_keys(ws_channel_board_groupings()), 'by status, by person and by date; no "By kind"');
	pg_assert_false(function_exists('ws_channel_board_decisions'), 'the board no longer reads decisions');
	pg_assert_false(function_exists('ws_channel_board_files'), 'nor files');
}

function test_workspace_channel_board_tasks_only_sources()
{
	$php = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/channel_board.php');
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace_board_channel.js');

	pg_assert_contains("\$group_by = 'status';", $php, 'an unknown grouping, the former "type" too, is read as by status');
	pg_assert_false(strpos($php, "case 'type':") !== false, 'no column of decisions, files, the pin or the summary');
	pg_assert_false(strpos($php, 'ws_messages') !== false, 'the stamp reads tasks only');
	pg_assert_contains("var GROUPS = ['status', 'person', 'date'];", $js, 'the chips are the three groupings');
	pg_assert_contains('prefs.group = knownGroup(prefs.group);', $js, 'a remembered "By kind" falls back to the first grouping');
	pg_assert_false(strpos($js, 'otherCard') !== false, 'no card for a decision, a file, the pin or the summary');
}

function test_workspace_channel_board_tasks_only_summary_shows_the_pin()
{
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace.js');
	$start = strpos($js, '        drawSummary: function () {');
	$body = substr($js, $start, strpos($js, "\n        },\n", $start) - $start);

	pg_assert_contains('side.appendChild(self.summaryPinCard(channel));', $body, 'the Summary tab draws the pinned message');
	pg_assert_contains("t('pin_card')", $js, 'under its own heading');
	pg_assert_contains("'pin_card'", (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/pins.php'), 'whose words the screen is given');
}
