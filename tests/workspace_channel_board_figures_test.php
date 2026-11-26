<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the four figures above a channel's board
 * (ws_channel_board_card_figures(), ws_channel_board_figures(),
 * includes/workspace/channel_board.php): open tasks, due this week, overdue
 * and done in the last seven days, a figure as a filter, and the due chip
 * of a card (ws_channel_board_due_chip()).
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

// Wednesday 2026-11-25, noon; the week ends on Sunday 2026-11-29.
function ws_board_figures_test_now()
{
	return mktime(12, 0, 0, 11, 25, 2026);
}

function ws_board_figures_test_card($status, $due = null, $completed_at = 0)
{
	return array('status' => $status, 'open' => ws_task_is_open($status), 'due_date' => $due, 'completed_at' => $completed_at);
}

function test_workspace_channel_board_figures_keys()
{
	pg_assert_same(array('open', 'week', 'overdue', 'done'), ws_channel_board_figure_keys(), 'the four figures, in the order they stand');
}

function test_workspace_channel_board_figures_card()
{
	$today = '2026-11-25';
	$now = ws_board_figures_test_now();

	pg_assert_same(array('open'), ws_channel_board_card_figures(ws_board_figures_test_card('todo'), $today, $now), 'an open task without a date is only open');
	pg_assert_same(array('open', 'week'), ws_channel_board_card_figures(ws_board_figures_test_card('doing', '2026-11-25'), $today, $now), 'due today is due this week');
	pg_assert_same(array('open', 'week'), ws_channel_board_card_figures(ws_board_figures_test_card('waiting', '2026-11-29'), $today, $now), 'due on Sunday too');
	pg_assert_same(array('open'), ws_channel_board_card_figures(ws_board_figures_test_card('todo', '2026-11-30'), $today, $now), 'the Monday after is not');
	pg_assert_same(array('open', 'overdue'), ws_channel_board_card_figures(ws_board_figures_test_card('todo', '2026-11-24'), $today, $now), 'a day late is overdue, not due this week');
	pg_assert_same(array('done'), ws_channel_board_card_figures(ws_board_figures_test_card('done', '2026-11-20', $now - 86400), $today, $now), 'done yesterday, late or not, is only done');
	pg_assert_same(array(), ws_channel_board_card_figures(ws_board_figures_test_card('done', null, $now - (8 * 86400)), $today, $now), 'done eight days ago is in no figure');
	pg_assert_same(array(), ws_channel_board_card_figures(ws_board_figures_test_card('cancelled', '2026-11-20', $now), $today, $now), 'a cancelled task is in none');
}

function test_workspace_channel_board_figures_counts()
{
	$today = '2026-11-25';
	$now = ws_board_figures_test_now();
	$cards = array(
		ws_board_figures_test_card('todo'),
		ws_board_figures_test_card('todo', '2026-11-26'),
		ws_board_figures_test_card('doing', '2026-11-23'),
		ws_board_figures_test_card('waiting', '2026-12-10'),
		ws_board_figures_test_card('done', '2026-11-25', $now - 3600),
		ws_board_figures_test_card('done', null, $now - (10 * 86400)),
		ws_board_figures_test_card('cancelled'),
	);

	pg_assert_same(array('open' => 4, 'week' => 1, 'overdue' => 1, 'done' => 1), ws_channel_board_figures($cards, $today, $now), 'counted once each');
	pg_assert_same(array('open' => 0, 'week' => 0, 'overdue' => 0, 'done' => 0), ws_channel_board_figures(array(), $today, $now), 'an empty board counts zero');
}

function test_workspace_channel_board_figures_filter()
{
	$today = '2026-11-25';
	$now = ws_board_figures_test_now();
	$cards = array(
		ws_board_figures_test_card('todo', '2026-11-23'),
		ws_board_figures_test_card('doing', '2026-11-27'),
		ws_board_figures_test_card('done', null, $now - 60),
	);

	pg_assert_same(1, count(ws_channel_board_figure_filter($cards, 'overdue', $today, $now)), 'a figure keeps its own tasks');
	pg_assert_same(3, count(ws_channel_board_figure_filter($cards, '', $today, $now)), 'no figure keeps every task');
	pg_assert_same(3, count(ws_channel_board_figure_filter($cards, 'nonsense', $today, $now)), 'an unknown figure is none');
	pg_assert_same(array(0, 1), array_keys(ws_channel_board_figure_filter($cards, 'open', $today, $now)), 'the list stays a list');
}

function test_workspace_channel_board_due_chip()
{
	$today = '2026-11-25';

	pg_assert_same(array('tone' => '', 'late' => 0), ws_channel_board_due_chip(null, true, $today), 'no date, no tone');
	pg_assert_same(array('tone' => '', 'late' => 0), ws_channel_board_due_chip('2026-11-30', true, $today), 'a later day is gray');
	pg_assert_same(array('tone' => 'soon', 'late' => 0), ws_channel_board_due_chip('2026-11-25', true, $today), 'today is amber');
	pg_assert_same(array('tone' => 'soon', 'late' => 0), ws_channel_board_due_chip('2026-11-26', true, $today), 'tomorrow too');
	pg_assert_same(array('tone' => 'late', 'late' => 2), ws_channel_board_due_chip('2026-11-23', true, $today), 'two days late is rose');
	pg_assert_same(array('tone' => 'late', 'late' => 25), ws_channel_board_due_chip('2026-10-31', true, $today), 'counted across a month');
	pg_assert_same(array('tone' => 'done', 'late' => 0), ws_channel_board_due_chip('2026-11-23', false, $today), 'a closed task is never late');
}

function test_workspace_channel_board_figures_in_the_answer()
{
	$php = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/channel_board.php');
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace_board_channel.js');

	pg_assert_contains("'figures'  => \$figures,", $php, 'the board answers with its figures');
	pg_assert_contains('ws_channel_board_figure_filter($tasks, $figure, $today, $now)', $php, 'and narrows the cards to the figure chosen');
	pg_assert_contains('figure: self.figure', $js, 'both views send the figure chosen');
	pg_assert_contains("aria-pressed", $js, 'a figure is a button that says whether it is on');
}

function test_workspace_channel_board_recent_closed_read_apart()
{
	$merged = ws_channel_board_merge_briefs(
		array(array('id' => 3), array('id' => 1)),
		array(array('id' => 1), array('id' => 7), array('id' => 0))
	);

	pg_assert_same(array(3, 1, 7), array_map(function ($brief) { return (int) $brief['id']; }, $merged), 'each task once, the second read after the first');

	// A channel past the read limit: the tasks finished lately are read on
	// their own, or "Done in the last 7 days" and the Done column miss them.
	$php = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/channel_board.php');

	pg_assert_contains("'status'        => 'closed',", $php, 'the closed tasks of the last days are read apart');
	pg_assert_contains("if (count(\$briefs) >= WS_CHANNEL_BOARD_READ_LIMIT) {", $php, 'only when the first read was full');
}
