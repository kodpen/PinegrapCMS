<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The guide's workspace topic (includes/fn/agent_guide.php): the Workspace's
 * records, which tools do which job, and what a write in a channel needs
 * first, and how a message is written (the lines of
 * includes/workspace/writing_rules.php, shared with the agent instructions).
 * Drawing rules stay in the canvas guide (workspace.canvas.guide);
 * this topic only points at it. Tools are named as {tool:route.id}, so a
 * renamed or removed route fails tests/agent_guide_test.php.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
	exit;
}

function pg_agent_guide_workspace_sections() {

	require_once(PG_FUNCTIONS_DIR . '/includes/workspace/writing_rules.php');

	return array(

		array(
			'id'      => 'records',
			'heading' => 'What the Workspace is made of',
			'lines'   => array(
				'Channel: a conversation of the team; public, private, a customer\'s or a mailbox. {tool:workspace.channels.list}',
				'Message: a line in a channel; a reply opens a discussion (thread) under it. {tool:workspace.messages.list}',
				'Task: work given to people, with a due date, notes and time; its card may sit in a channel. {tool:workspace.tasks.list}',
				'Proposal: a card of record changes a person applies or sets aside with their own rights. {tool:workspace.changes.list}',
				'Approval: a question put to named people, settled by their answers. {tool:workspace.approvals.list}',
				'Table: a workflow table of typed columns and rows. {tool:workspace.tables.list}',
				'Note: a personal Markdown page, seen by others only once shared. {tool:workspace.notes.list}',
				'Memory: what a channel keeps known, read before answering in it. {tool:workspace.memory.list}',
				'Flow: steps run on a start (a message, a time, a record event). {tool:workspace.flows.list}'
			)
		),

		array(
			'id'      => 'jobs',
			'heading' => 'Which tools do which job',
			'lines'   => array(
				'Before answering in a channel, read its summary, decisions, latest messages and open tasks in one call: {tool:workspace.channels.context}.',
				'Follow a channel: {tool:workspace.messages.list} with since_id the last id read.',
				'Find anything (channels, messages, tasks, notes, records): {tool:workspace.search}.',
				'Write a message: {tool:workspace.messages.create}; a task: {tool:workspace.tasks.create}, a note on it: {tool:workspace.tasks.notes.create}, done: {tool:workspace.tasks.complete}.',
				'Change a site record (product, stock, order, customer, page, task): {tool:workspace.changes.propose}, or {tool:workspace.tasks.changes.propose} on a task.',
				'Ask people to decide: {tool:workspace.approvals.create}.',
				'A row in a table: {tool:workspace.table_rows.create}; read the columns first with {tool:workspace.tables.get}.',
				'Draw a chart or picture: read {tool:workspace.canvas.guide} first, then {tool:workspace.channels.canvases.create}. A live HTML page: {tool:workspace.channels.pages.create}.'
			)
		),

		array(
			'id'      => 'writes',
			'heading' => 'Before a write',
			'lines'   => array(
				'Every write takes dry_run: send it true first, then the same call without it.',
				'What a key or a connection writes, readers see as written by the application; tell the person before writing in their name.',
				'Propose rather than write: a proposal holds at most 10 changes, and an application makes at most 30 an hour (429 rate_limited). Applying or dismissing one is the person\'s, never a tool\'s.',
				'Name records and people as tags: <#order:1045>, <@user:12>. A person mentioned is told.',
				'A canvas or a live page is posted only by an agent of the channel; anyone else is refused.'
			)
		),

		array(
			'id'      => 'writing',
			'heading' => 'How a message is written',
			'lines'   => ws_writing_rules_lines()
		),

		array(
			'id'      => 'mistakes',
			'heading' => 'Frequent mistakes',
			'lines'   => array(
				'Polling the whole channel: pass since_id, and read context once rather than every message.',
				'Writing a record directly when the person should see it first: propose it.',
				'Drawing from memory: the canvas guide gives the boards, type, palettes and chart rules; a picture without them is redrawn.',
				'A table cell in the wrong form: money is minor units and dates are YYYY-MM-DD; a refused cell names its column in field.'
			)
		)

	);

}
