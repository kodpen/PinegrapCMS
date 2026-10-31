<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the channel's catch-up line (ws_channel_catchup(),
 * includes/workspace/briefing.php): shown only when something came or a task
 * is late, its counts as words, and the message it opens.
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

function test_workspace_channel_catchup_is_quiet_when_nothing_came()
{
	pg_assert_same(null, ws_channel_catchup(array()), 'no signals');
	pg_assert_same(null, ws_channel_catchup(array('messages' => 0, 'mentions' => 0, 'overdue' => 0, 'first_unread' => 40)), 'nothing new, nothing late');
}

function test_workspace_channel_catchup_says_the_counts_and_opens_the_first_mention()
{
	$line = ws_channel_catchup(array('messages' => 12, 'mentions' => 1, 'overdue' => 2, 'first_unread' => 40, 'first_mention' => 52));

	pg_assert_same('While you were away', $line['lead']);
	pg_assert_same(array('12 new messages', 'a mention', '2 overdue tasks'), $line['parts']);
	pg_assert_same(52, $line['go'], 'a mention is opened before the first unread');
	pg_assert_true($line['mention']);
	pg_assert_true($line['tasks']);
}

function test_workspace_channel_catchup_opens_the_first_unread_without_a_mention()
{
	$line = ws_channel_catchup(array('messages' => 1, 'first_unread' => 40));

	pg_assert_same(array('a new message'), $line['parts']);
	pg_assert_same(40, $line['go']);
	pg_assert_false($line['mention']);
	pg_assert_false($line['tasks']);
}

function test_workspace_channel_catchup_for_late_tasks_alone_opens_no_message()
{
	$line = ws_channel_catchup(array('overdue' => 1, 'first_unread' => 40));

	pg_assert_same('Waiting for you', $line['lead'], 'late tasks did not come while the reader was away');
	pg_assert_same(array('an overdue task'), $line['parts']);
	pg_assert_same(0, $line['go'], 'no message came, so none is offered');
	pg_assert_true($line['tasks']);
}
