<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the channel's note when its memory changes
 * (includes/workspace/channel_memory.php, ws_memory_note()): the note's
 * text, the burst rule that extends the last note, the one write path the
 * panel, the API and MCP share, and that the note asks no agent.
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
require_once(__DIR__ . '/helpers/language_map.php');
require_once(__DIR__ . '/helpers/source_reader.php');

function test_workspace_memory_note_text()
{
	pg_assert_same('Channel memory updated', ws_memory_note_head(false));
	pg_assert_same('General memory updated', ws_memory_note_head(true));
	pg_assert_same('#12 Merge window - added', ws_memory_note_entry_line(12, 'Merge window', 'added'));
	pg_assert_same('#12 Merge window - changed', ws_memory_note_entry_line(12, 'Merge window', 'changed'));
	pg_assert_same('#12 Merge window - deleted', ws_memory_note_entry_line(12, "Merge\n window", 'deleted'), 'a title stays on one line');
	pg_assert_same('by <@user:5>', ws_memory_note_by_line('user', 5));
	pg_assert_same('by Builder', ws_memory_note_by_line('app', 9, 'Builder'), 'an application by its name');
	pg_assert_same("Channel memory updated\n#1 A - added\nby <@user:5>", ws_memory_note_body('Channel memory updated', array('#1 A - added'), 'by <@user:5>'));

	$turkish = pg_test_language_map('tr');
	pg_assert_same('Kanal belleği güncellendi', $turkish['Channel memory updated'] ?? null);
	pg_assert_same('Genel bellek güncellendi', $turkish['General memory updated'] ?? null);

	foreach (array('#{var:1} {var:2} - added', '#{var:1} {var:2} - changed', '#{var:1} {var:2} - deleted', 'by {var:1}') as $key) {
		pg_assert_true(isset($turkish[$key]), $key . ' has its Turkish');
	}
}

function test_workspace_memory_note_burst_rule()
{
	$now = 1800000000;
	$head = ws_memory_note_head(false);
	$by = ws_memory_note_by_line('user', 5);
	$first = ws_memory_note_entry_line(1, 'A', 'added');
	$second = ws_memory_note_entry_line(2, 'B', 'changed');
	$last = array('kind' => 'system', 'sender_kind' => 'system', 'deleted_at' => 0, 'created_at' => $now - 60,
		'body' => ws_memory_note_body($head, array($first), $by));

	pg_assert_same(ws_memory_note_body($head, array($first, $second), $by), ws_memory_note_merge($last, $head, $second, $by, $now), 'the same writer within minutes extends the note');
	pg_assert_same($last['body'], ws_memory_note_merge($last, $head, $first, $by, $now), 'the same line again leaves it as it is');

	pg_assert_same(null, ws_memory_note_merge($last, $head, $second, ws_memory_note_by_line('user', 6), $now), 'another writer: a new note');
	pg_assert_same(null, ws_memory_note_merge($last, ws_memory_note_head(true), $second, $by, $now), 'the other memory: a new note');
	pg_assert_same(null, ws_memory_note_merge(array('created_at' => $now - WS_MEMORY_NOTE_BURST - 1) + $last, $head, $second, $by, $now), 'after the burst window: a new note');
	pg_assert_same(null, ws_memory_note_merge(array('kind' => 'message', 'sender_kind' => 'user') + $last, $head, $second, $by, $now), 'a message in between: a new note');
	pg_assert_same(null, ws_memory_note_merge(array('deleted_at' => $now) + $last, $head, $second, $by, $now), 'a deleted note');
	pg_assert_same(null, ws_memory_note_merge(array('body' => 'Someone pinned a message.') + $last, $head, $second, $by, $now), 'another system line');
	pg_assert_same(null, ws_memory_note_merge(null, $head, $second, $by, $now), 'an empty channel');

	$long = array('body' => ws_memory_note_body($head, array_fill(0, 1, str_repeat('x', WS_MEMORY_NOTE_MAX)), $by)) + $last;
	pg_assert_same(null, ws_memory_note_merge($long, $head, $second, $by, $now), 'a full note: a new one');
}

// The panel, the API, MCP and /memory all write through ws_memory_save(),
// ws_memory_delete() and ws_memory_move_general(): each tells the channel.
function test_workspace_memory_note_in_the_shared_write_path()
{
	pg_assert_contains("'changed'", pg_test_function_source('ws_memory_save'));
	pg_assert_contains("'added'", pg_test_function_source('ws_memory_save'));
	pg_assert_contains('ws_memory_note(', pg_test_function_source('ws_memory_save'));
	pg_assert_contains("'deleted'", pg_test_function_source('ws_memory_delete'));
	pg_assert_contains('ws_memory_note(0,', pg_test_function_source('ws_memory_move_general'), 'a move is a change of the general memory');
	pg_assert_contains('ws_api_memory_writer($viewer)', pg_test_function_source('ws_api_memory_delete'), 'an agent\'s delete is named as the agent');
}

// A system message wakes nobody: no agent request, no step of a reply chain
// the loop guard counts.
function test_workspace_memory_note_asks_no_agent()
{
	$note = pg_test_function_source('ws_memory_note');
	$system = pg_test_function_source('ws_message_system');

	pg_assert_contains('ws_message_system(', $note);

	foreach (array('ws_message_send(', 'ws_agent_after_send', 'ws_agent_after_app_send', 'ws_message_ask_assistants', 'ws_ai_requests', 'ws_message_notify') as $call) {
		pg_assert_false(strpos($note, $call) !== false, 'the note does not call ' . $call);
		pg_assert_false(strpos($system, $call) !== false, 'a system message does not call ' . $call);
	}

	pg_assert_false(strpos($system, 'parent_id') !== false, 'a system message is in no reply chain');
	pg_assert_contains("'system', 0, 'system'", $system, 'written as a system message (ws-msg-system)');
}
