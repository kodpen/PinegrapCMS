<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a checklist item of a message made into a task
 * (includes/workspace/task_work.php): the item read as /task reads it, which
 * task belongs to which item, which messages offer it, and how the item is
 * drawn with its task or its button.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/refs.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/render.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/commands.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/interact.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/task_work.php');

function test_workspace_item_task_fields_read_as_task_command()
{
	$team = array(
		array('id' => 5, 'username' => 'ahmet'),
		array('id' => 6, 'username' => 'ayse'),
	);

	$fields = ws_checklist_item_task_fields('Call Ayşe tomorrow @ahmet 1.5 saat', 9, $team, '2026-10-27');

	pg_assert_same('Call Ayşe', $fields['title']);
	pg_assert_same('2026-10-28', $fields['due_date']);
	pg_assert_same(90, $fields['estimate_minutes']);
	pg_assert_same(array(5), $fields['assignees'], 'the typed username is the assignee');
	pg_assert_same('Call Ayşe tomorrow @ahmet 1.5 saat', $fields['description'], 'the item as written is the description');

	// Given to nobody, the task is the author's own, as with /task.
	$own = ws_checklist_item_task_fields('Order paper', 9, $team, '2026-10-27');

	pg_assert_same('Order paper', $own['title']);
	pg_assert_same(array(9), $own['assignees']);
	pg_assert_same(null, $own['due_date']);
}

function test_workspace_item_task_pairs_by_item_words()
{
	$items = ws_checklist_items("Shopping:\n- [ ] Milk\n- [x] Bread\n- [ ] Milk\n- [ ] Eggs");

	$tasks = array(
		array('id' => 40, 'description' => 'Bread'),
		array('id' => 41, 'description' => 'Milk'),
		array('id' => 42, 'description' => 'Milk'),
		array('id' => 43, 'description' => 'Milk'),
		array('id' => 44, 'description' => 'Butter'),
		array('id' => 45, 'description' => ''),
	);

	// Each task claims the first item with its words that no earlier task
	// holds; a task whose item is gone, or a third Milk, holds none.
	pg_assert_same(array(1 => 40, 0 => 41, 2 => 42), ws_checklist_item_tasks_pair($items, $tasks));

	// The list edited: Bread moved up keeps its task, Milk reworded loses it.
	$edited = ws_checklist_items("- [ ] Bread\n- [ ] Oat milk\n- [ ] Eggs");

	pg_assert_same(array(0 => 40), ws_checklist_item_tasks_pair($edited, $tasks));
	pg_assert_same(array(), ws_checklist_item_tasks_pair(array(), $tasks));
}

function test_workspace_item_task_offered_on_writable_messages_only()
{
	$message = array('kind' => 'message', 'sender_kind' => 'user', 'deleted_at' => 0, 'body' => "- [ ] Milk");

	pg_assert_true(ws_checklist_item_task_offered($message, true));
	pg_assert_false(ws_checklist_item_task_offered($message, false), 'a reader who may not post');
	pg_assert_true(ws_checklist_item_task_offered(array('kind' => 'decision', 'sender_kind' => 'app') + $message, true));
	pg_assert_false(ws_checklist_item_task_offered(array('kind' => 'task') + $message, true));
	pg_assert_false(ws_checklist_item_task_offered(array('sender_kind' => 'guest') + $message, true));
	pg_assert_false(ws_checklist_item_task_offered(array('deleted_at' => 5) + $message, true));
	pg_assert_false(ws_checklist_item_task_offered(array('body' => 'No list here') + $message, true));
}

function test_workspace_item_task_drawn_beside_the_item()
{
	$refs = array(
		'task:42' => array('type' => 'task', 'id' => 42, 'label' => 'G-42 Milk', 'url' => '', 'meta' => '', 'status' => 'Open', 'known' => true, 'icon' => 'bi-check2-square'),
	);
	$body = "- [ ] Milk\n- [ ] Eggs";

	$html = ws_render_body($body, $refs, array(), true, null, array('tasks' => array(0 => 42), 'make' => true));

	pg_assert_contains('data-ws-ref="task:42"', $html, 'the task tag beside its item');
	pg_assert_same(1, substr_count($html, 'data-ws-item-task='), 'the button only on the item without a task');
	pg_assert_contains('data-ws-item-task="1"', $html);

	// Not offered: the tag stays, no button.
	$read = ws_render_body($body, $refs, array(), false, null, array('tasks' => array(0 => 42), 'make' => false));

	pg_assert_contains('data-ws-ref="task:42"', $read);
	pg_assert_same(0, substr_count($read, 'data-ws-item-task='));

	// Without the argument the list is drawn as before.
	pg_assert_same(0, substr_count(ws_render_body($body, $refs, array(), true), 'ws-check-task'));
}
