<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for "Mark all as read" of the Workspace list's Unread filter
 * (ws_read_all(), includes/workspace/channels.php): which read marks move,
 * which channels are read, and the panel action that asks for it.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/actions.php');
require_once(PG_FUNCTIONS_DIR . '/includes/panel/actions.php');
require_once(__DIR__ . '/helpers/source_reader.php');

// Each channel past its mark is read up to its newest message; one already
// read or without a message is left alone.
function test_workspace_read_all_marks_each_channel_to_its_newest()
{
	$marks = ws_read_all_marks(array(
		array('channel_id' => 4, 'last_read_id' => 10, 'newest_id' => 157),
		array('channel_id' => '9', 'last_read_id' => '157', 'newest_id' => '157'),
		array('channel_id' => 12, 'last_read_id' => 0, 'newest_id' => 3),
		array('channel_id' => 15, 'last_read_id' => 40, 'newest_id' => null),
		array('channel_id' => 0, 'last_read_id' => 0, 'newest_id' => 8),
	));

	pg_assert_same(array(4 => 157, 12 => 3), $marks);
	pg_assert_same(array(), ws_read_all_marks(array()), 'nothing to read');
	pg_assert_same(array(), ws_read_all_marks(null), 'no rows');
}

// The ids are numbers: '9' is not past '157'.
function test_workspace_read_all_marks_compare_numbers()
{
	pg_assert_same(array(), ws_read_all_marks(array(array('channel_id' => 2, 'last_read_id' => '157', 'newest_id' => '9'))));
	pg_assert_same(array(2 => 1000), ws_read_all_marks(array(array('channel_id' => 2, 'last_read_id' => '999', 'newest_id' => '1000'))));
}

// Every membership of the reader, muted ones too, archived channels and
// mailbox rooms left out; each through ws_channel_mark_read() under the
// access check, nothing else written.
function test_workspace_read_all_reads_through_the_channel_mark()
{
	$source = pg_test_function_source('ws_read_all');

	pg_assert_contains("c.archived_at = 0 AND c.kind <> 'mailbox'", $source, 'archived channels and mailbox rooms stay');
	pg_assert_contains("WHERE m.user_id = '\" . \$me . \"'", $source, 'the reader\'s own memberships');
	pg_assert_false(strpos($source, 'notify') !== false, 'muted channels are read too');
	pg_assert_contains('ws_can_read_channel($viewer, ws_channel($channel_id))', $source);
	pg_assert_contains('ws_channel_mark_read($channel_id, $me, $message_id)', $source);
	pg_assert_same(0, preg_match('/db\\(\\s*"(UPDATE|INSERT|DELETE)/', $source), 'no write of its own');
}

function test_workspace_read_all_is_a_panel_action()
{
	$actions = pg_test_function_source('ws_handle_action');
	$case = substr($actions, strpos($actions, "case 'ws_read_all':"));
	$case = substr($case, 0, strpos($case, "case 'ws_channels':"));

	pg_assert_contains("ws_action_ok(array('marked' => ws_read_all(\$viewer)))", $case);

	$row = pg_panel_action('ws_read_all');

	pg_assert_same('pg_panel_ws', $row['handler'], 'the workspace door, with its session and member checks');
	pg_assert_true($row['token'], 'the form token is checked');
}
