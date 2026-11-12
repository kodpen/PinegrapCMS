<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a channel's conversation leaving as a Markdown file
 * (includes/workspace/channel_export.php): the range read from the drawer's
 * dates, the messages kept (a deleted one and one outside the range left
 * out), tags written out as names and links, a discussion under its message,
 * a poll's, an approval's and a task's one line, the head, decisions and
 * file list.
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
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/notes_io.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/channel_export.php');

// A message payload as ws_message_payloads() gives it, with the stored text.
function workspace_channel_export_test_message($id, $name, $time, $body, $extra = array())
{
	return array_merge(array(
		'id'          => $id,
		'kind'        => 'message',
		'sender_kind' => 'user',
		'sender'      => array('name' => $name),
		'deleted'     => false,
		'timestamp'   => $time,
		'body'        => $body,
		'html'        => '',
		'poll'        => null,
		'approval'    => null,
		'file'        => null,
		'thread'      => null,
	), $extra);
}

function test_workspace_channel_export_range_reads_dates()
{
	$all = ws_channel_export_range('', '');
	pg_assert_true($all['ok'], 'no dates is everything');
	pg_assert_same(0, $all['from'], 'open start');
	pg_assert_same(0, $all['to'], 'open end');

	$range = ws_channel_export_range('2026-11-01', '2026-11-09');
	pg_assert_true($range['ok'], 'two dates');
	pg_assert_same(mktime(0, 0, 0, 11, 1, 2026), $range['from'], 'the first day from its start');
	pg_assert_same(mktime(23, 59, 59, 11, 9, 2026), $range['to'], 'the last day to its end');

	pg_assert_false(ws_channel_export_range('2026-11-09', '2026-11-01')['ok'], 'the first date after the last is refused');
	pg_assert_false(ws_channel_export_range('2026-02-30', '')['ok'], 'a day that does not exist is refused');
	pg_assert_false(ws_channel_export_range('1.11.2026', '')['ok'], 'another format is refused');
	pg_assert_same('', ws_channel_export_range('2026-11-01', '')['to_date'], 'an open end stays open');
}

function test_workspace_channel_export_keep_leaves_out_deleted_and_out_of_range()
{
	$from = mktime(0, 0, 0, 11, 2, 2026);
	$to = mktime(23, 59, 59, 11, 3, 2026);
	$messages = array(
		workspace_channel_export_test_message(1, 'Ayşe', mktime(10, 0, 0, 11, 1, 2026), 'before'),
		workspace_channel_export_test_message(2, 'Ayşe', mktime(10, 0, 0, 11, 2, 2026), 'first day'),
		workspace_channel_export_test_message(3, 'Ali', mktime(11, 0, 0, 11, 2, 2026), '', array('deleted' => true)),
		workspace_channel_export_test_message(4, 'Ali', mktime(23, 59, 0, 11, 3, 2026), 'last day'),
		workspace_channel_export_test_message(5, 'Ali', mktime(0, 0, 0, 11, 4, 2026), 'after'),
	);

	$kept = array_map(function ($message) { return $message['id']; }, ws_channel_export_keep($messages, $from, $to));
	pg_assert_same(array(2, 4), $kept, 'only the range, and no deleted message');

	$all = array_map(function ($message) { return $message['id']; }, ws_channel_export_keep($messages, 0, 0));
	pg_assert_same(array(1, 2, 4, 5), $all, 'an open range keeps every message but the deleted one');
}

function test_workspace_channel_export_markdown_writes_tags_poll_and_discussion()
{
	$refs = array(
		'order:1045' => array('type' => 'order', 'id' => 1045, 'label' => 'Order #1045', 'url' => '/pinegrap/view_order.php?id=1045', 'known' => true),
		'user:12'    => array('type' => 'user', 'id' => 12, 'label' => '@Ayşe Yılmaz', 'url' => '', 'known' => true),
	);
	$day = mktime(9, 30, 0, 11, 5, 2026);

	$poll = array('closed' => true, 'options' => array(
		array('label' => 'Pizza', 'count' => 3),
		array('label' => 'Kebab', 'count' => 1),
	));
	$approval = array('title' => 'Discount 10%', 'outcome' => 'approved', 'open' => false);

	$messages = array(
		workspace_channel_export_test_message(1, 'Ali Veli', $day, 'See <#order:1045>, <@user:12>.', array(
			'discussion' => array('title' => 'Shipping', 'messages' => array(
				workspace_channel_export_test_message(7, 'Ayşe Yılmaz', $day + 60, "Sent today.\nTracking follows."),
			)),
		)),
		workspace_channel_export_test_message(2, 'Ali Veli', $day + 120, 'Lunch?', array('poll' => $poll)),
		workspace_channel_export_test_message(3, 'Ali Veli', $day + 180, 'Discount 10%', array('approval' => $approval)),
		workspace_channel_export_test_message(4, 'Ali Veli', $day + 86400, 'Here it is', array(
			'file' => array('name' => 'quote [v2].pdf', 'url' => '/files/quote v2.pdf'),
		)),
	);

	$decisions = array(workspace_channel_export_test_message(9, 'Ali Veli', $day, "Ship from\nIzmir"));
	$files = array(workspace_channel_export_test_message(4, 'Ali Veli', $day + 86400, '', array(
		'file' => array('name' => 'quote [v2].pdf', 'url' => '/files/quote v2.pdf'),
	)));
	$head = array('channel' => 'customer-acme', 'range' => 'All messages', 'by' => 'Erdal', 'at' => $day);

	$out = ws_channel_export_markdown($head, $messages, $decisions, $files, $refs, 'https://shop.test');

	pg_assert_contains("# customer-acme\n", $out, 'the channel name heads it');
	pg_assert_contains('Range: All messages', $out, 'the range');
	pg_assert_contains('Exported by Erdal on 05.11.2026 09:30', $out, 'who exported and when');
	pg_assert_contains('- 05.11.2026 · Ali Veli: Ship from Izmir', $out, 'a decision on one line');
	pg_assert_contains("### 05.11.2026\n\n**Ali Veli** · 09:30\nSee [Order #1045](https://shop.test/pinegrap/view_order.php?id=1045), @Ayşe Yılmaz.", $out, 'tags written out, the link made absolute');
	pg_assert_contains("> **Discussion: Shipping**\n>\n> **Ayşe Yılmaz** · 05.11.2026 09:31\n> Sent today.\n> Tracking follows.", $out, 'the discussion quoted under its message');
	pg_assert_contains('Poll (closed): Lunch? — Pizza 3 · Kebab 1', $out, 'a poll as one line with its outcome');
	pg_assert_contains('Approved: Discount 10%', $out, 'an approval as its outcome');
	pg_assert_contains("### 06.11.2026", $out, 'a new day gets its heading');
	pg_assert_contains("Here it is\n\n[quote (v2).pdf](https://shop.test/files/quote%20v2.pdf)", $out, 'a file as its name and link');
	pg_assert_contains("## Files\n\n- [quote (v2).pdf](https://shop.test/files/quote%20v2.pdf) — Ali Veli, 06.11.2026", $out, 'the file list at the end');
	pg_assert_true(strpos($out, '## Decisions') < strpos($out, '## Messages'), 'decisions before the messages');
	pg_assert_true(strpos($out, '## Messages') < strpos($out, '## Files'), 'files after the messages');
}

function test_workspace_channel_export_markdown_empty_range()
{
	$head = array('channel' => 'general', 'range' => 'x', 'by' => 'Erdal', 'at' => 0);
	$out = ws_channel_export_markdown($head, array(), array(), array(), array());

	pg_assert_contains("## Messages\n\nNo messages in this range.", $out, 'an empty range says so');
	pg_assert_false(strpos($out, '## Decisions') !== false, 'no empty decisions section');
	pg_assert_false(strpos($out, '## Files') !== false, 'no empty file list');
}

function test_workspace_channel_export_approval_waiting_and_name()
{
	pg_assert_same('Approval request: Price (waiting)', ws_channel_export_approval_line(array('title' => 'Price', 'outcome' => '', 'open' => true)), 'an open request waits');
	pg_assert_same('Rejected: Price', ws_channel_export_approval_line(array('title' => 'Price', 'outcome' => 'rejected', 'open' => false)), 'a refused one');
	pg_assert_same('a-b-' . date('Y-m-d'), ws_channel_export_name('a/b'), 'a slash made safe in the file name');
	pg_assert_same('channel-' . date('Y-m-d'), ws_channel_export_name('::'), 'nothing left: a plain name');
}

// A task posted to the channel is one line: its number, title, status, due
// date and people; one the reader may not see is its number only, as its
// card on the screen. The due date is a fixed day, never "Today".
function test_workspace_channel_export_task_line()
{
	pg_assert_same('Task: T-44 · Write the brief · To do · Due: 15.11.2026 · Ayşe Yılmaz, Ali Veli', ws_channel_export_task_line(array(
		'number' => 'T-44', 'title' => 'Write the brief ', 'status' => 'To do', 'due' => '15.11.2026', 'people' => array('Ayşe Yılmaz', 'Ali Veli'),
	)), 'every part');
	pg_assert_same('Task: T-45 · Call back · Done', ws_channel_export_task_line(array(
		'number' => 'T-45', 'title' => 'Call back', 'status' => 'Done', 'due' => '', 'people' => array(),
	)), 'no due date and nobody on it');
	pg_assert_same('Task: T-46', ws_channel_export_task_line(array('number' => 'T-46', 'hidden' => true)), 'a task the reader may not see');

	pg_assert_same('15.11.2026', ws_channel_export_task_due(array('due_date' => '2026-11-15', 'due_time' => '')), 'a day');
	pg_assert_same('15.11.2026 09:30', ws_channel_export_task_due(array('due_date' => '2026-11-15', 'due_time' => '09:30:00')), 'a day and its time');
	pg_assert_same('', ws_channel_export_task_due(array('due_date' => '0000-00-00')), 'the empty date');
	pg_assert_same('', ws_channel_export_task_due(array('due_date' => null, 'due_time' => null)), 'no date');
}

// A task message has no text of its own: the Markdown carries the task's
// line under the head, in a discussion as well; the print page draws its
// card (task_html). The export reads the tasks for the messages it holds.
function test_workspace_channel_export_markdown_writes_a_task_message()
{
	$day = mktime(1, 44, 0, 11, 12, 2026);
	$messages = array(
		workspace_channel_export_test_message(1, 'Erdal Güral', $day, '', array('kind' => 'task', 'task_id' => 44, 'task' => array(
			'number' => 'T-44', 'title' => 'Write the brief', 'status' => 'To do', 'due' => '', 'people' => array('Ayşe Yılmaz'),
		))),
		workspace_channel_export_test_message(2, 'Erdal Güral', $day + 60, 'Who takes it?', array('discussion' => array('title' => 'Brief', 'messages' => array(
			workspace_channel_export_test_message(3, 'Ayşe Yılmaz', $day + 120, '', array('kind' => 'task', 'task_id' => 45, 'task' => array('number' => 'T-45', 'hidden' => true))),
		)))),
		workspace_channel_export_test_message(4, 'Ali Veli', $day + 180, 'From the call', array('kind' => 'task', 'task_id' => 46, 'task' => array(
			'number' => 'T-46', 'title' => 'Send the quote', 'status' => 'In progress', 'due' => '13.11.2026', 'people' => array(),
		))),
	);
	$head = array('channel' => 'general', 'range' => 'All messages', 'by' => 'Erdal', 'at' => $day);
	$out = ws_channel_export_markdown($head, $messages, array(), array(), array());

	pg_assert_contains("**Erdal Güral** · 01:44\nTask: T-44 · Write the brief · To do · Ayşe Yılmaz\n", $out, 'the task under its head');
	pg_assert_contains("> **Ayşe Yılmaz** · 12.11.2026 01:46\n> Task: T-45\n", $out, 'in a discussion, a hidden task by its number');
	pg_assert_contains("**Ali Veli** · 01:47\nFrom the call\n\nTask: T-46 · Send the quote · In progress · Due: 13.11.2026\n", $out, 'a text and its task');

	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/channel_export.php');
	pg_assert_contains('$messages = ws_channel_export_tasks($viewer, $messages);', $source, 'the export reads the tasks');
	pg_assert_contains("if (!ws_can_see_task(\$viewer, \$task, \$on)) {\n            \$lines[\$task_id] = array('number' => ws_task_number(\$task_id), 'hidden' => true);", $source, 'a hidden task keeps its number only');
	pg_assert_contains("\$html .= '<div class=\"body ws-body\">' . (string) \$message['task_html'] . '</div>';", $source, 'the print page draws the card');
}
