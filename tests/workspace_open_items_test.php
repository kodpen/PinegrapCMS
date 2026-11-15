<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a channel's open items line (includes/workspace/open_items.php):
 * the reader's own first, then the newest, bounded; when a check-in still
 * runs; the short text.
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

function test_workspace_open_items_put_the_readers_own_first_then_the_newest()
{
	$items = ws_open_items_order(array(
		array('kind' => 'poll', 'message_id' => 50, 'text' => 'a', 'mine' => false),
		array('kind' => 'approval', 'message_id' => 20, 'text' => 'b', 'mine' => true),
		array('kind' => 'checkin', 'message_id' => 70, 'text' => 'c', 'mine' => false),
		array('kind' => 'question', 'message_id' => 40, 'text' => 'd', 'mine' => true),
	));

	pg_assert_same(array(40, 20, 70, 50), array_map(function ($item) { return $item['message_id']; }, $items));
}

function test_workspace_open_items_are_bounded_and_drop_rows_without_a_message()
{
	$many = array(array('kind' => 'poll', 'message_id' => 0, 'text' => '', 'mine' => true));

	for ($i = 1; $i <= 14; $i++) {
		$many[] = array('kind' => 'poll', 'message_id' => $i, 'text' => '', 'mine' => false);
	}

	$items = ws_open_items_order($many);

	pg_assert_same(WS_OPEN_ITEMS_MAX, count($items));
	pg_assert_same(14, $items[0]['message_id']);
	pg_assert_same(array(), ws_open_items_order(array()));
	pg_assert_same(2, count(ws_open_items_order($many, 2)));
}

function test_workspace_open_items_checkin_runs_for_a_week_while_somebody_has_not_answered()
{
	$now = 1700000000;

	pg_assert_true(ws_open_items_checkin_running($now - 3600, 1, 3, $now), 'fresh, two to answer');
	pg_assert_false(ws_open_items_checkin_running($now - 3600, 3, 3, $now), 'everybody answered');
	pg_assert_false(ws_open_items_checkin_running($now - 8 * 86400, 0, 3, $now), 'older than a week');
}

function test_workspace_open_items_short_text_is_one_line_and_cut()
{
	pg_assert_same('Ship it today?', ws_open_items_short("  Ship it\n  today? "));

	$long = ws_open_items_short(str_repeat('word ', 60));

	pg_assert_same(120, mb_strlen($long));
	pg_assert_same('…', mb_substr($long, -1));
}
