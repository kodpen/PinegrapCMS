<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// What each Workspace event (ws_webhook_events(); the flows and the incoming
// mail merged on their own announce the same names) carries in data, in the
// shape of the core's api_webhook_event_payloads(): its fields, each with a
// type and a meaning, and one sample. The fields are the keys the announcing
// code writes; tests/webhook_event_payloads_test.php reads it and fails when
// the two part. Moments are UTC.

if (!defined('PG_FUNCTIONS_DIR')) {
	exit;
}

function ws_webhook_event_payloads()
{
	$task = array(
		'id'            => array('integer', 'The task (GET /workspace/tasks/{id}).'),
		'title'         => array('string', 'Its title.'),
		'status'        => array('string', 'Its status, such as todo, doing or done.'),
		'priority'      => array('string', 'low, normal, high or urgent.'),
		'due_date'      => array('string?', 'The due date (YYYY-MM-DD), null for none.'),
		'department_id' => array('integer', 'Its department, 0 for none.'),
		'channel_id'    => array('integer', 'The channel it is in, 0 for none.'),
		'assignees'     => array('integer[]', 'The people on it.'),
		'creator_id'    => array('integer', 'Who made it.')
	);

	$task_sample = function ($status) {
		return array('id' => 731, 'title' => 'Call the supplier about the late parcel', 'status' => $status, 'priority' => 'high', 'due_date' => '2026-11-19', 'department_id' => 2, 'channel_id' => 14, 'assignees' => array(12, 15), 'creator_id' => 12);
	};

	$assistant = array(
		'agent'      => array('string', 'The assistant: ai, claude, or mcp:<server id>.'),
		'request_id' => array('integer', 'The assistant request (GET /workspace/assistant-requests).')
	);

	$group = array(
		'id'        => array('integer', 'The group of channels (GET /workspace/channel-groups).'),
		'name'      => array('string', 'Its name.'),
		'parent_id' => array('integer?', 'The group it is inside, null at the top.'),
		'path'      => array('string', 'Its place, the groups above it joined with ›.'),
		'color'     => array('string?', 'Its colour as hex, null for none.')
	);

	$group_sample = array('id' => 5, 'name' => 'Customers', 'parent_id' => 2, 'path' => 'Sales › Customers', 'color' => '#2f80ed');

	$run = array(
		'id'         => array('integer', 'The run (GET /workspace/flow-runs/{id}).'),
		'channel_id' => array('integer?', 'The channel the run belongs to, null for none: what a subscription narrowed to a group of channels is matched by.'),
		'flow_id'    => array('integer', 'The flow.'),
		'flow_name'  => array('string?', 'The flow\'s name; null for a run of a channel that is not public.'),
		'run'        => array('object', 'The run: id, flow_id, version_id, version, status, started_by, actor_id, channel_id, contact_id, boxes_run, started_at, updated_at, finished_at.')
	);

	$run_sample = function ($status, $finished) {
		return array('id' => 2048, 'channel_id' => 14, 'flow_id' => 9, 'flow_name' => 'New order follow-up', 'run' => array('id' => 2048, 'flow_id' => 9, 'version_id' => 31, 'version' => 4, 'status' => $status, 'started_by' => 0, 'actor_id' => 12, 'channel_id' => 14, 'contact_id' => 884, 'boxes_run' => 5, 'started_at' => '2026-11-17T09:00:00Z', 'updated_at' => '2026-11-17T09:02:10Z', 'finished_at' => $finished));
	};

	$box = 'The box: node_id, kind, name, status, outcome, task_id, attempt, finished_at, outputs and result (name, outputs and result null for a run of a channel that is not public); null when no box stands for it.';

	$box_sample = function ($status, $outcome, $result) {
		return array('node_id' => 'n7', 'kind' => 'webhook', 'name' => 'Tell the warehouse', 'status' => $status, 'outcome' => $outcome, 'task_id' => null, 'attempt' => 1, 'finished_at' => '2026-11-17T09:02:10Z', 'outputs' => array('status_code' => 200), 'result' => $result);
	};

	$row = array(
		'channel_id'  => array('integer', 'The first public channel the table is shared in, which a subscription narrowed to a group of channels is matched by.'),
		'channel_ids' => array('integer[]', 'Every public channel the table is shared in.'),
		'table'       => array('object', 'The workflow table: id and name.'),
		'row'         => array('object', 'The row as GET /workspace/tables/{id}/rows/{row_id} gives it; for a deletion only id and position.'),
		'by'          => array('integer', 'Who wrote it.')
	);

	$row_sample = array('channel_id' => 14, 'channel_ids' => array(14), 'table' => array('id' => 3, 'name' => 'Returns'), 'row' => array('id' => 88, 'position' => 4, 'cells' => array('c1' => 'Order 100318', 'c2' => 'Waiting')), 'by' => 12);

	$thread = array('integer', 'The message it answers, 0 for none.');

	$payloads = array(

		'workspace.task.created'   => array('fields' => $task, 'sample' => $task_sample('todo')),
		'workspace.task.completed' => array('fields' => $task, 'sample' => $task_sample('done')),

		'workspace.task.assigned' => array(
			'fields' => $task + array('added' => array('integer[]', 'The people added.')),
			'sample' => $task_sample('todo') + array('added' => array(15))
		),

		'workspace.task.status_changed' => array(
			'fields' => $task + array('previous_status' => array('string', 'The status before.')),
			'sample' => $task_sample('doing') + array('previous_status' => 'todo')
		),

		'workspace.task.delegated' => array(
			'fields' => $task + array('agent' => $assistant['agent'], 'delegated_by' => array('integer', 'Who handed it over.'), 'request_id' => $assistant['request_id']),
			'sample' => $task_sample('doing') + array('agent' => 'claude', 'delegated_by' => 12, 'request_id' => 640)
		),

		'workspace.task.assistant_answered' => array(
			'fields' => $task + array('agent' => $assistant['agent'], 'request_id' => $assistant['request_id'], 'requested_by' => array('integer', 'Who asked.'), 'outcome' => array('string', 'answered or failed.')),
			'sample' => $task_sample('doing') + array('agent' => 'claude', 'request_id' => 640, 'requested_by' => 12, 'outcome' => 'answered')
		),

		'workspace.task.note_added' => array(
			'fields' => array(
				'id'        => array('integer', 'The note.'),
				'task_id'   => array('integer', 'The task.'),
				'task'      => array('object', 'The task, as workspace.task.created carries it.'),
				'author_id' => array('integer', 'Who wrote it, 0 for an application.'),
				'app_id'    => array('integer', 'The application that wrote it, 0 for a person.'),
				'text'      => array('string?', 'The note as plain text; null for a task of a channel that is not public.')
			),
			'sample' => array('id' => 4410, 'task_id' => 731, 'task' => $task_sample('doing'), 'author_id' => 15, 'app_id' => 0, 'text' => 'The supplier will send it tomorrow.')
		),

		'workspace.task.time_logged' => array(
			'fields' => array(
				'id'         => array('integer', 'The time entry.'),
				'task_id'    => array('integer', 'The task.'),
				'channel_id' => array('integer', 'The task\'s channel, 0 for none.'),
				'user_id'    => array('integer', 'Whose time it is.'),
				'minutes'    => array('integer', 'How long.'),
				'worked_on'  => array('string', 'The day (YYYY-MM-DD).'),
				'billable'   => array('boolean', 'Whether it is billed.'),
				'timer'      => array('boolean', 'True for a stopped timer, false for minutes written by hand.')
			),
			'sample' => array('id' => 517, 'task_id' => 731, 'channel_id' => 14, 'user_id' => 15, 'minutes' => 45, 'worked_on' => '2026-11-17', 'billable' => true, 'timer' => true)
		),

		'workspace.message.created' => array(
			'fields' => array(
				'id'         => array('integer', 'The message.'),
				'channel_id' => array('integer', 'The public channel.'),
				'channel'    => array('string', 'The channel\'s name.'),
				'sender_id'  => array('integer', 'Who wrote it, 0 for an application.'),
				'app_id'     => array('integer', 'The application that wrote it, 0 for a person.'),
				'kind'       => array('string', 'The kind of message, such as text.'),
				'text'       => array('string', 'The message as plain text.'),
				'task_id'    => array('integer', 'The task it is about, 0 for none.')
			),
			'sample' => array('id' => 90211, 'channel_id' => 14, 'channel' => 'orders', 'sender_id' => 12, 'app_id' => 0, 'kind' => 'text', 'text' => 'Order 100318 is packed.', 'task_id' => 0)
		),

		'workspace.message.transcribed' => array(
			'fields' => array(
				'id'          => array('integer', 'The voice message.'),
				'channel_id'  => array('integer', 'The public channel.'),
				'channel'     => array('string', 'The channel\'s name.'),
				'sender_id'   => array('integer', 'Who recorded it, 0 for an application.'),
				'app_id'      => array('integer', 'The application that sent it, 0 for a person.'),
				'file_id'     => array('integer', 'The recording\'s file.'),
				'file_name'   => array('string', 'The recording\'s file name.'),
				'duration_ms' => array('integer', 'Its length in milliseconds.'),
				'transcript'  => array('string', 'The machine text.'),
				'language'    => array('string?', 'The language heard, null when not known.'),
				'provider'    => array('string', 'The transcription service.'),
				'model'       => array('string', 'Its model.')
			),
			'sample' => array('id' => 90212, 'channel_id' => 14, 'channel' => 'orders', 'sender_id' => 15, 'app_id' => 0, 'file_id' => 3301, 'file_name' => 'voice-90212.webm', 'duration_ms' => 8400, 'transcript' => 'The courier is here, I am handing over three parcels.', 'language' => 'en', 'provider' => 'openai', 'model' => 'whisper-1')
		),

		'workspace.poll.closed' => array(
			'fields' => array(
				'id'                => array('integer', 'The poll.'),
				'message_id'        => array('integer', 'Its message.'),
				'channel_id'        => array('integer', 'The public channel.'),
				'channel'           => array('string', 'The channel\'s name.'),
				'question'          => array('string', 'The question.'),
				'options'           => array('object[]', 'Each option: id, label and votes.'),
				'voters'            => array('integer', 'How many people voted.'),
				'winner'            => array('string?', 'The winning label, null for a tie or no vote.'),
				'tie'               => array('boolean', 'Whether the top options tied.'),
				'closed_by'         => array('integer', 'Who closed it, 0 when its time ran out.'),
				'result_message_id' => array('integer', 'The message that tells the result.')
			),
			'sample' => array('id' => 41, 'message_id' => 90180, 'channel_id' => 14, 'channel' => 'orders', 'question' => 'Which courier for December?', 'options' => array(array('id' => 1, 'label' => 'Yurtiçi', 'votes' => 4), array('id' => 2, 'label' => 'Aras', 'votes' => 2)), 'voters' => 6, 'winner' => 'Yurtiçi', 'tie' => false, 'closed_by' => 0, 'result_message_id' => 90230)
		),

		'workspace.template.applied' => array(
			'fields' => array(
				'channel_id'  => array('integer', 'The channel.'),
				'template_id' => array('string', 'The channel template.'),
				'template'    => array('string', 'Its name.'),
				'applied_by'  => array('integer', 'Who applied it.'),
				'app_id'      => array('integer', 'The application that applied it, 0 for a person.'),
				'task_ids'    => array('integer[]', 'The tasks it made.'),
				'notes'       => array('integer', 'How many notes it wrote.'),
				'unassigned'  => array('integer', 'How many tasks could not be assigned.')
			),
			'sample' => array('channel_id' => 14, 'template_id' => 'onboarding', 'template' => 'Customer onboarding', 'applied_by' => 12, 'app_id' => 0, 'task_ids' => array(732, 733, 734), 'notes' => 1, 'unassigned' => 0)
		),

		'workspace.approval.requested' => array(
			'fields' => array(
				'id'          => array('integer', 'The approval request.'),
				'message_id'  => array('integer', 'Its message.'),
				'channel_id'  => array('integer', 'The public channel.'),
				'channel'     => array('string', 'The channel\'s name.'),
				'title'       => array('string', 'Its title.'),
				'rule'        => array('string', 'How it is settled, such as any or all.'),
				'approvers'   => array('integer[]', 'The people asked.'),
				'agents'      => array('string[]', 'The assistants asked.'),
				'guests'      => array('object[]', 'The people outside the team asked: name.'),
				'closes_at'   => array('integer', 'When it closes, as a Unix time; 0 for no deadline.'),
				'record_type' => array('string', 'The kind of record it is about, empty for none.'),
				'record_id'   => array('integer', 'That record, 0 for none.'),
				'created_by'  => array('integer', 'Who asked.'),
				'app_id'      => array('integer', 'The application that asked, 0 for a person.')
			),
			'sample' => array('id' => 66, 'message_id' => 90240, 'channel_id' => 14, 'channel' => 'orders', 'title' => 'Refund order 100318 in full?', 'rule' => 'any', 'approvers' => array(12), 'agents' => array(), 'guests' => array(array('name' => 'Selin (accountant)')), 'closes_at' => 1763550000, 'record_type' => 'order', 'record_id' => 1042, 'created_by' => 15, 'app_id' => 0)
		),

		'workspace.approval.decided' => array(
			'fields' => array(
				'id'                => array('integer', 'The approval request.'),
				'message_id'        => array('integer', 'Its message.'),
				'channel_id'        => array('integer', 'The public channel.'),
				'channel'           => array('string', 'The channel\'s name.'),
				'title'             => array('string', 'Its title.'),
				'rule'              => array('string', 'How it was settled.'),
				'outcome'           => array('string', 'approved, rejected, expired or closed.'),
				'people'            => array('object[]', 'Each person\'s answer: user_id, decision and note.'),
				'guests'            => array('object[]', 'The answers from outside the team: name, decision and note.'),
				'agents'            => array('object[]', 'The assistants\' answers: agent, decision and note.'),
				'closed_by'         => array('integer', 'Who settled it, 0 when its time ran out.'),
				'result_message_id' => array('integer', 'The message that tells the result.')
			),
			'sample' => array('id' => 66, 'message_id' => 90240, 'channel_id' => 14, 'channel' => 'orders', 'title' => 'Refund order 100318 in full?', 'rule' => 'any', 'outcome' => 'approved', 'people' => array(array('user_id' => 12, 'decision' => 'approved', 'note' => '')), 'guests' => array(), 'agents' => array(), 'closed_by' => 12, 'result_message_id' => 90251)
		),

		'workspace.channel_group.created' => array(
			'fields' => $group + array('created_by' => array('integer', 'Who made it.')),
			'sample' => $group_sample + array('created_by' => 12)
		),

		'workspace.channel_group.updated' => array(
			'fields' => $group + array(
				'changed'    => array('string[]', 'What changed: name, color or parent_id.'),
				'previous'   => array('object', 'The group as it was, with the same fields.'),
				'updated_by' => array('integer', 'Who changed it.')
			),
			'sample' => $group_sample + array('changed' => array('name'), 'previous' => array('name' => 'Clients') + $group_sample, 'updated_by' => 12)
		),

		'workspace.channel_group.access_changed' => array(
			'fields' => $group + array(
				'added'      => array('integer[]', 'The people given access.'),
				'removed'    => array('integer[]', 'The people whose access was taken back.'),
				'changed_by' => array('integer', 'Who changed it.')
			),
			'sample' => $group_sample + array('added' => array(15), 'removed' => array(), 'changed_by' => 12)
		),

		'workspace.channel_group.deleted' => array(
			'fields' => $group + array(
				'channel_ids'  => array('integer[]', 'Its channels, moved up to parent_id.'),
				'group_ids'    => array('integer[]', 'Its groups, moved up to parent_id.'),
				'access_ended' => array('integer[]', 'The people whose access given on it ended.'),
				'deleted_by'   => array('integer', 'Who took it away.')
			),
			'sample' => $group_sample + array('channel_ids' => array(14, 16), 'group_ids' => array(), 'access_ended' => array(15), 'deleted_by' => 12)
		),

		'workspace.flow.run.finished' => array(
			'fields' => $run + array('result' => array('object?', $box)),
			'sample' => $run_sample('done', '2026-11-17T09:02:10Z') + array('result' => $box_sample('done', 'ok', 'Sent: 200'))
		),

		'workspace.flow.run.failed' => array(
			'fields' => $run + array('result' => array('object?', $box)),
			'sample' => $run_sample('failed', '2026-11-17T09:02:10Z') + array('result' => $box_sample('failed', 'error', 'The address answered 500'))
		),

		'workspace.flow.run.cancelled' => array(
			'fields' => $run + array('cancelled_by' => array('integer', 'Who cancelled it.')),
			'sample' => $run_sample('cancelled', '2026-11-17T09:05:00Z') + array('cancelled_by' => 12)
		),

		'workspace.flow.run.waiting' => array(
			'fields' => $run + array('waiting' => array('object', 'The box that waits (node_id, kind, name, status, attempt, outputs, result) and what for: for (task, approval, delay or retry), task_id, approval_id, until.')),
			'sample' => $run_sample('waiting', null) + array('waiting' => array('node_id' => 'n4', 'kind' => 'task', 'name' => 'Check the parcel', 'status' => 'waiting', 'attempt' => 1, 'outputs' => array(), 'result' => '', 'for' => 'task', 'task_id' => 735, 'approval_id' => null, 'until' => null))
		),

		'workspace.wtable.row.created'  => array('fields' => $row, 'sample' => $row_sample),
		'workspace.wtable.row.restored' => array('fields' => $row, 'sample' => $row_sample),

		'workspace.wtable.row.deleted' => array(
			'fields' => $row,
			'sample' => array_merge($row_sample, array('row' => array('id' => 88, 'position' => 4)))
		),

		'workspace.wtable.row.updated' => array(
			'fields' => $row + array('changed' => array('integer[]', 'The column ids written.')),
			'sample' => $row_sample + array('changed' => array(2))
		),

		'workspace.mail.received' => array(
			'fields' => array(
				'conversation_id' => array('integer', 'The conversation.'),
				'channel_id'      => array('integer', 'The channel it is in.'),
				'message_id'      => array('integer', 'The message.'),
				'box_id'          => array('integer', 'The mailbox or number it came to.'),
				'new'             => array('boolean', 'Whether it opened a new conversation.'),
				'transport'       => array('string', 'email, sms or whatsapp.'),
				'verified'        => array('boolean', 'For e-mail, whether the sender\'s address passed its checks; true for a text message.')
			),
			'sample' => array('conversation_id' => 310, 'channel_id' => 22, 'message_id' => 90300, 'box_id' => 1, 'new' => true, 'transport' => 'email', 'verified' => true)
		),

		'workspace.app.command' => array(
			'fields' => array(
				'command'      => array('string', 'The command typed, with its slash.'),
				'app_id'       => array('integer', 'The application it is sent to.'),
				'channel_id'   => array('integer', 'The channel or discussion.'),
				'channel'      => array('string', 'Its name.'),
				'channel_kind' => array('string', 'Its kind, such as public, private or thread.'),
				'thread'       => array('object?', 'For a discussion, its channel_id and message_id; null otherwise.'),
				'parent_id'    => $thread,
				'user_id'      => array('integer', 'Who typed it.'),
				'text'         => array('string', 'The words after the command, as plain text.'),
				'body'         => array('string', 'The words after the command, as stored.')
			),
			'sample' => array('command' => '/stock', 'app_id' => 7, 'channel_id' => 14, 'channel' => 'orders', 'channel_kind' => 'public', 'thread' => null, 'parent_id' => 0, 'user_id' => 12, 'text' => 'mug', 'body' => 'mug')
		),

		'workspace.app.mentioned' => array(
			'fields' => array(
				'id'               => array('integer', 'The message.'),
				'channel_id'       => array('integer', 'The channel or discussion.'),
				'channel'          => array('string', 'Its name.'),
				'channel_kind'     => array('string', 'Its kind, such as public, private or thread.'),
				'thread'           => array('object?', 'For a discussion, its channel_id and message_id; null otherwise.'),
				'parent_id'        => $thread,
				'sender_id'        => array('integer', 'Who wrote it, 0 for an application.'),
				'app_id'           => array('integer', 'The application that wrote it, 0 for a person.'),
				'mentioned_app_id' => array('integer', 'The application mentioned; only its subscriptions hear the event.'),
				'kind'             => array('string', 'The kind of message.'),
				'text'             => array('string', 'The message as plain text, as the application\'s owner reads it.'),
				'body'             => array('string', 'The message as stored.'),
				'task_id'          => array('integer', 'The task it is about, 0 for none.'),
				'edited'           => array('boolean', 'Whether an edit added the mention.')
			),
			'sample' => array('id' => 90310, 'channel_id' => 14, 'channel' => 'orders', 'channel_kind' => 'public', 'thread' => null, 'parent_id' => 0, 'sender_id' => 12, 'app_id' => 0, 'mentioned_app_id' => 7, 'kind' => 'text', 'text' => '@Stock bot how many mugs are left?', 'body' => '<@app:7> how many mugs are left?', 'task_id' => 0, 'edited' => false)
		)

	);

	return $payloads;
}
