<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - what the module adds to the external API.
 *
 * The module's whole contact surface with the API, the way includes/erp/api.php
 * is the ERP's: routes, events, objects and permissions, merged by
 * includes/api/modules.php into the API's own lists while WORKSPACE_ENABLED is
 * on. Nothing under includes/api/ is edited to add a workspace endpoint.
 *
 * An application acts with its owner's reach: it sees the channels the owner
 * may read and may hand tasks only where the owner may. What it writes into a
 * channel is shown as written by the application, so a reader always knows a
 * message came from an integration and not from a colleague.
 *
 * The handlers and presenters are in api_resources.php beside this file.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_API_ENTRY') && !defined('PG_API_PANEL') && !defined('PG_INIT_LOADED')) {
    exit;
}

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/api_resources.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/api_claude.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/api_templates.php');

/**
 * The permission rows on the Application Access screen.
 *
 * @return array
 */
function ws_scope_groups()
{
    return array(
        'workspace' => array(
            'label'       => lang('Workspace'),
            'description' => lang('Channels, messages and departments'),
            'icon'        => 'bi-chat-square-text',
            'read'        => 'workspace:read',
            'write'       => 'workspace:write',
        ),
        'tasks' => array(
            'label'       => lang('Tasks'),
            'description' => lang('Tasks and the planning board'),
            'icon'        => 'bi-check2-square',
            'read'        => 'tasks:read',
            'write'       => 'tasks:write',
        ),
    );
}

/**
 * Which of the module's scopes an application owner may delegate: all four
 * to a member of the team, none to anyone else. What an owner may do inside
 * the workspace - whom they may give work to, which private channels they are
 * in - is checked again on every call, from the owner's own rights.
 *
 * @param array $owner
 * @return array
 */
function ws_owner_scopes($owner)
{
    $rights = ws_rights($owner);

    if (!$rights['member']) {
        return array();
    }

    return array('workspace:read', 'workspace:write', 'tasks:read', 'tasks:write');
}

/**
 * The events an application may subscribe to.
 *
 * @return array
 */
function ws_webhook_events()
{
    return array(
        'workspace.task.created'        => 'A workspace task was created, from a channel, a screen or the API',
        'workspace.task.assigned'       => 'People were added to an existing workspace task (added lists them)',
        'workspace.task.status_changed' => 'A workspace task moved to another status (previous_status says from where)',
        'workspace.task.completed'      => 'A workspace task was marked done',
        'workspace.message.created'     => 'A message was written in a public workspace channel. Private channels are never announced',
        'workspace.task.note_added'     => 'A note was added to a workspace task (text is null for a task of a private channel)',
        'workspace.poll.closed'         => 'A poll in a public workspace channel closed, by hand or when its time ran out: the counts, and the winner or a tie',
        'workspace.template.applied'    => 'A channel template was applied to a channel, when the channel was made from it or later: the template, the tasks made and how many could not be assigned',
        'workspace.task.time_logged'    => 'Time spent on a workspace task was written: a stopped timer or minutes written by hand (minutes, worked_on, billable, user_id)',
    );
}

/**
 * The objects the endpoints answer with.
 *
 * @return array
 */
function ws_openapi_objects()
{
    return array(
        'WorkspaceChannel'    => 'ws_api_channel_schema',
        'WorkspaceMessage'    => 'ws_api_message_schema',
        'WorkspaceDecision'   => 'ws_api_decision_schema',
        'WorkspaceTask'       => 'ws_api_task_schema',
        'WorkspaceRecurrence' => 'ws_api_recurrence_schema',
        'WorkspaceDepartment' => 'ws_api_department_schema',
        'WorkspaceRefs'       => 'ws_api_refs_schema',
        'WorkspacePlanRow'    => 'ws_api_plan_row_schema',
        'WorkspaceTaskNote'   => 'ws_api_task_note_schema',
        'WorkspaceClaudeRequest'  => 'ws_api_claude_request_schema',
        'WorkspaceChannelContext' => 'ws_api_channel_context_schema',
        'WorkspaceChangeType'     => 'ws_api_claude_change_type_schema',
        'WorkspaceRecord'         => 'ws_api_claude_record_schema',
        'WorkspaceChannelMember'  => 'ws_api_channel_member_schema',
        'WorkspaceChannelMembers' => 'ws_api_channel_members_schema',
        'WorkspaceChange'         => 'ws_api_change_schema',
        'WorkspaceAssistantRequest' => 'ws_api_assistant_request_schema',
        'WorkspaceNote'           => 'ws_api_note_schema',
        'WorkspaceTemplate'        => 'ws_api_template_schema',
        'WorkspaceTemplateApplied' => 'ws_api_template_applied_schema',
        'WorkspaceTaskTime'       => 'ws_api_task_time_schema',
    );
}

/**
 * The route rows.
 *
 * @return array
 */
function ws_api_routes()
{
    $task_fields = array(
        array('name' => 'title', 'in' => 'body', 'type' => 'string', 'max_length' => 255, 'description' => 'What is to be done.'),
        array('name' => 'description', 'in' => 'body', 'type' => 'string', 'max_length' => 8000, 'description' => 'Longer text. Tags such as <#order:1045> or <@user:12> are kept as tags.'),
        array('name' => 'status', 'in' => 'body', 'type' => 'enum', 'values' => array('todo', 'doing', 'waiting', 'done', 'cancelled')),
        array('name' => 'priority', 'in' => 'body', 'type' => 'enum', 'values' => array('low', 'normal', 'high', 'urgent')),
        array('name' => 'start_date', 'in' => 'body', 'type' => 'string', 'max_length' => 10, 'description' => 'YYYY-MM-DD. Empty clears it.'),
        array('name' => 'due_date', 'in' => 'body', 'type' => 'string', 'max_length' => 10, 'description' => 'YYYY-MM-DD. Empty clears it.'),
        array('name' => 'estimate_minutes', 'in' => 'body', 'type' => 'int', 'min' => 0, 'max' => 100000, 'description' => 'Each person\'s share of the effort, in minutes. 0 counts the site default on the board.'),
        array('name' => 'department_id', 'in' => 'body', 'type' => 'int', 'min' => 0, 'description' => 'See GET /workspace/departments. 0 for none.'),
        array('name' => 'assignees', 'in' => 'body', 'type' => 'list', 'of' => 'integer', 'max_items' => 20, 'description' => 'User ids of the people on the task. The owner of the application may give work only to themselves unless they hold the assign right or lead the people\'s department.'),
        array('name' => 'refs', 'in' => 'body', 'type' => 'list', 'of' => array('type' => 'string', 'id' => 'integer'), 'max_items' => 20, 'description' => 'Records the task is about, as objects of {type, id}: order, product, product_group, offer, contact, user_account, erp_account, invoice, waybill, receipt, edoc, form, calendar_event, file or page.'),
        array('name' => 'force', 'in' => 'body', 'type' => 'bool', 'description' => 'Hand the work over even though somebody on it is away on those days. Refused unless the owner may override the board.'),
        array('name' => 'blocked_by', 'in' => 'body', 'type' => 'list', 'of' => 'integer', 'max_items' => 20, 'description' => 'Ids of the tasks this one cannot start before; replaces the list (links to tasks the owner cannot see are kept). A list that would make tasks wait for each other is refused with 422. Not enforced: a blocked task can still be started.'),
    );

    $routes = array(

        array(
            'id'          => 'workspace.channels.list',
            'method'      => 'GET',
            'path'        => '/workspace/channels',
            'scope'       => 'workspace:read',
            'handler'     => 'ws_api_channels_list',
            'returns'     => array('list' => 'WorkspaceChannel'),
            'summary'     => 'List workspace channels',
            'description' => 'The channels the owner of the application may read: every public channel, and the private ones the owner is in. unread counts what the owner has not read. active_since keeps the ones written in since a moment, which is how an app that already holds the list refreshes it.',
            'params'      => array(
                array('name' => 'archived', 'in' => 'query', 'type' => 'bool', 'description' => 'The archived channels instead.'),
                array('name' => 'contact_id', 'in' => 'query', 'type' => 'int', 'min' => 1, 'description' => 'Only the channels tied to this customer.'),
                array('name' => 'active_since', 'in' => 'query', 'type' => 'datetime', 'description' => 'Only channels with a message at or after this moment.'),
            ),
        ),

        array(
            'id'          => 'workspace.channels.create',
            'method'      => 'POST',
            'path'        => '/workspace/channels',
            'scope'       => 'workspace:write',
            'handler'     => 'ws_api_channels_create',
            'dry_run' => true,
            'returns'     => 'WorkspaceChannel',
            'summary'     => 'Create a channel',
            'description' => 'Creates a channel the way the channel form does, with the owner of the application as its owner: a public channel is read by the whole team, a private one only by the people in it. members are brought in and told; ids of people who are not in the team are left out. The channel gets its first line in the owner\'s name. A name another open channel has is refused with 409 name_taken. Send an Idempotency-Key so a retried call does not create two channels.',
            'params'      => array(
                array('name' => 'name', 'in' => 'body', 'type' => 'string', 'max_length' => 80, 'required' => true, 'description' => 'A leading # is dropped and runs of spaces are made one.'),
                array('name' => 'kind', 'in' => 'body', 'type' => 'enum', 'values' => array('public', 'private'), 'default' => 'public'),
                array('name' => 'topic', 'in' => 'body', 'type' => 'string', 'max_length' => 255),
                array('name' => 'department_id', 'in' => 'body', 'type' => 'int', 'min' => 0, 'description' => 'See GET /workspace/departments. 0 for none.'),
                array('name' => 'members', 'in' => 'body', 'type' => 'list', 'of' => 'integer', 'max_items' => 100, 'description' => 'User ids of the colleagues to bring in besides the owner.'),
            ),
        ),

        array(
            'id'           => 'workspace.channels.update',
            'method'       => 'POST',
            'also_accepts' => array('PATCH'),
            'path'         => '/workspace/channels/{id}',
            'scope'        => 'workspace:write',
            'handler'      => 'ws_api_channels_update',
            'dry_run' => true,
            'returns'      => 'WorkspaceChannel',
            'summary'      => 'Change a channel',
            'description'  => 'Changes only the fields sent, for an owner who may manage the channel: its own owner, or staff - of a private channel only while they are in it. archived true archives the channel and false brings it back; either leaves a line in the channel. A name another open channel has is refused with 409 name_taken, also when an archived channel is brought back under it. PATCH is accepted beside POST.',
            'params'       => array(
                array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
                array('name' => 'name', 'in' => 'body', 'type' => 'string', 'max_length' => 80),
                array('name' => 'topic', 'in' => 'body', 'type' => 'string', 'max_length' => 255, 'description' => 'Empty clears it.'),
                array('name' => 'department_id', 'in' => 'body', 'type' => 'int', 'min' => 0, 'description' => 'See GET /workspace/departments. 0 for none.'),
                array('name' => 'archived', 'in' => 'body', 'type' => 'bool'),
            ),
        ),

        array(
            'id'          => 'workspace.channels.members.list',
            'method'      => 'GET',
            'path'        => '/workspace/channels/{id}/members',
            'scope'       => 'workspace:read',
            'handler'     => 'ws_api_channel_members_list',
            'returns'     => array('list' => 'WorkspaceChannelMember'),
            'summary'     => 'The people in a channel',
            'description' => 'The owner of the channel first, then everybody else in the order they joined. A public channel is read by the whole team; its members are the people who joined it and hear about what is written in it.',
            'params'      => array(
                array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
            ),
        ),

        array(
            'id'          => 'workspace.channels.members.add',
            'method'      => 'POST',
            'path'        => '/workspace/channels/{id}/members',
            'scope'       => 'workspace:write',
            'handler'     => 'ws_api_channel_members_add',
            'dry_run' => true,
            'returns'     => 'WorkspaceChannelMembers',
            'summary'     => 'Bring people into a channel',
            'description' => 'Adds colleagues to a channel the owner of the application may post in - a private one only when the owner is in it. The people added are told, and the channel gets a line naming them. Ids of people already in it or not in the team are left out: added says who came in.',
            'params'      => array(
                array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
                array('name' => 'user_ids', 'in' => 'body', 'type' => 'list', 'of' => 'integer', 'max_items' => 100, 'required' => true),
            ),
        ),

        array(
            'id'           => 'workspace.channels.members.remove',
            'method'       => 'DELETE',
            'also_accepts' => array('POST'),
            'path'         => '/workspace/channels/{id}/members/{user_id}',
            'scope'        => 'workspace:write',
            'handler'      => 'ws_api_channel_members_remove',
            'dry_run' => true,
            'returns'      => 'WorkspaceChannelMembers',
            'summary'      => 'Take somebody out of a channel',
            'description'  => 'With the owner\'s own id the owner of the application leaves the channel; anybody else is removed only by an owner who may manage it. An owner of the channel who leaves hands it to the member who has been in it longest, and a private channel nobody is left in is archived. A private channel gets a line saying who left or was removed. Somebody who was not in the channel is not an error: removed is then empty. members is empty once the owner of the application can no longer read the channel. POST is accepted as well, because a default IIS install answers DELETE itself before PHP is reached.',
            'params'       => array(
                array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
                array('name' => 'user_id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
            ),
        ),

        array(
            'id'          => 'workspace.messages.list',
            'method'      => 'GET',
            'path'        => '/workspace/channels/{id}/messages',
            'scope'       => 'workspace:read',
            'handler'     => 'ws_api_messages_list',
            'returns'     => array('list' => 'WorkspaceMessage'),
            'summary'     => 'Read a channel',
            'description' => 'Messages oldest first. Pass since_id to read only what arrived after a message - the way to follow a channel - or before_id to page back. text is the message with every tag spelled out; body keeps the tags as written.',
            'params'      => array(
                array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
                array('name' => 'since_id', 'in' => 'query', 'type' => 'int', 'min' => 0),
                array('name' => 'before_id', 'in' => 'query', 'type' => 'int', 'min' => 0),
                array('name' => 'limit', 'in' => 'query', 'type' => 'int', 'min' => 1, 'max' => 200, 'default' => 50),
            ),
        ),

        array(
            'id'          => 'workspace.messages.create',
            'method'      => 'POST',
            'path'        => '/workspace/channels/{id}/messages',
            'scope'       => 'workspace:write',
            'handler'     => 'ws_api_messages_create',
            'returns'     => 'WorkspaceMessage',
            'summary'     => 'Write in a channel',
            'description' => 'Writes a message into a channel the owner may post in; readers see it as written by the application. Tags are written as tokens - <@user:12> mentions a person (they are told), <#order:1045> points at an order. kind decision or note keeps the message on the channel\'s Decisions tab. Send an Idempotency-Key so a retried call does not post twice.',
            'params'      => array(
                array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
                array('name' => 'text', 'in' => 'body', 'type' => 'string', 'max_length' => 4000, 'required' => true),
                array('name' => 'kind', 'in' => 'body', 'type' => 'enum', 'values' => array('message', 'note', 'decision')),
            ),
        ),

        array(
            'id'          => 'workspace.messages.react',
            'method'      => 'POST',
            'path'        => '/workspace/messages/{id}/reactions',
            'scope'       => 'workspace:write',
            'handler'     => 'ws_api_messages_react',
            'returns'     => 'WorkspaceMessage',
            'summary'     => 'Leave an emoji on a message',
            'description' => 'Leaves an emoji on a message in a channel the owner may post in, as the application - for instance an eye when an integration has seen a request and a tick when it is done. on false takes it back. Readers are not notified.',
            'params'      => array(
                array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
                array('name' => 'emoji', 'in' => 'body', 'type' => 'string', 'max_length' => 32, 'required' => true),
                array('name' => 'on', 'in' => 'body', 'type' => 'bool', 'description' => 'true (the default) leaves the emoji, false takes it back.'),
            ),
        ),

        array(
            'id'          => 'workspace.decisions.list',
            'method'      => 'GET',
            'path'        => '/workspace/decisions',
            'scope'       => 'workspace:read',
            'handler'     => 'ws_api_decisions_list',
            'returns'     => array('list' => 'WorkspaceDecision'),
            'summary'     => 'List the decisions of every channel',
            'description' => 'The decisions taken in every channel the owner of the application may read - every public channel and the private ones the owner is in, archived channels included - newest first, by the moment the message was marked as a decision (decided_at). notes adds the messages marked as notes. user_id keeps what that person wrote or marked; search looks in the text as it was written, tags included as tokens. claude_change is set when the decision records a record change Claude proposed and somebody applied. Page with next_cursor.',
            'params'      => array(
                array('name' => 'notes', 'in' => 'query', 'type' => 'bool', 'description' => 'The notes too.'),
                array('name' => 'channel_id', 'in' => 'query', 'type' => 'int', 'min' => 1),
                array('name' => 'department_id', 'in' => 'query', 'type' => 'int', 'min' => 1, 'description' => 'Only the channels of this department. See GET /workspace/departments.'),
                array('name' => 'user_id', 'in' => 'query', 'type' => 'int', 'min' => 1, 'description' => 'Only what this person wrote or marked.'),
                array('name' => 'from', 'in' => 'query', 'type' => 'string', 'max_length' => 25, 'description' => 'YYYY-MM-DD: the first day, in the site\'s time zone.'),
                array('name' => 'to', 'in' => 'query', 'type' => 'string', 'max_length' => 25, 'description' => 'YYYY-MM-DD: the last day, included.'),
                array('name' => 'search', 'in' => 'query', 'type' => 'string', 'max_length' => 100),
                array('name' => 'cursor', 'in' => 'query', 'type' => 'string', 'max_length' => 200),
                array('name' => 'limit', 'in' => 'query', 'type' => 'int', 'min' => 1, 'max' => 100, 'default' => 50),
            ),
        ),

        array(
            'id'          => 'workspace.changes.list',
            'method'      => 'GET',
            'path'        => '/workspace/changes',
            'scope'       => 'workspace:read',
            'handler'     => 'ws_api_changes_list',
            'returns'     => array('list' => 'WorkspaceChange'),
            'summary'     => 'Record changes the assistants proposed',
            'description' => 'The record changes Claude (agent claude) or Pinegrap AI (agent ai) proposed under their answers, in the channels the owner of the application may read, newest first. Nothing is applied here: only the person who asked applies a change, in its channel and with their own rights. status pending is waiting, applying is being written at this moment, applied was written (decision_message_id is the locked decision it left), dismissed was set aside, stale was not applied because the record changed after the proposal, failed could not be written (error says why). fields and reason are shown only when the owner may see records of that kind; hidden says when they are not. from_shown and to_shown are the values as the card in the channel prints them. Page with next_cursor.',
            'params'      => array(
                array('name' => 'status', 'in' => 'query', 'type' => 'enum', 'values' => array('pending', 'applying', 'applied', 'dismissed', 'stale', 'failed')),
                array('name' => 'channel_id', 'in' => 'query', 'type' => 'int', 'min' => 1),
                array('name' => 'type', 'in' => 'query', 'type' => 'string', 'max_length' => 32, 'description' => 'A kind of record: product, stock, order, contact, erp_account, product_group, channel, user, page, form, file, offer or calendar_event, of the ones this site runs.'),
                array('name' => 'cursor', 'in' => 'query', 'type' => 'string', 'max_length' => 200),
                array('name' => 'limit', 'in' => 'query', 'type' => 'int', 'min' => 1, 'max' => 100, 'default' => 50),
            ),
        ),

        array(
            'id'          => 'workspace.assistant_requests.list',
            'method'      => 'GET',
            'path'        => '/workspace/assistant-requests',
            'scope'       => 'workspace:read',
            'handler'     => 'ws_api_assistant_requests_list',
            'returns'     => array('list' => 'WorkspaceAssistantRequest'),
            'summary'     => 'What Claude and Pinegrap AI were asked',
            'description' => 'The requests to Claude (agent claude) and to Pinegrap AI (agent ai), newest first: those asked in the channels the owner of the application may read, and the owner\'s own requests written in a note or in the Visual Page Editor (origin says where). status queued and sent are waiting, running is being worked on, answered has its answer (reply_message_id in a channel), failed and cancelled ended without one (error says why). The words of a request are read where it was asked. Page with next_cursor.',
            'params'      => array(
                array('name' => 'agent', 'in' => 'query', 'type' => 'enum', 'values' => array('claude', 'ai')),
                array('name' => 'status', 'in' => 'query', 'type' => 'enum', 'values' => array('queued', 'sent', 'running', 'answered', 'failed', 'cancelled')),
                array('name' => 'cursor', 'in' => 'query', 'type' => 'string', 'max_length' => 200),
                array('name' => 'limit', 'in' => 'query', 'type' => 'int', 'min' => 1, 'max' => 100, 'default' => 50),
            ),
        ),

        array(
            'id'          => 'workspace.notes.list',
            'method'      => 'GET',
            'path'        => '/workspace/notes',
            'scope'       => 'workspace:read',
            'handler'     => 'ws_api_notes_list',
            'returns'     => array('list' => 'WorkspaceNote'),
            'summary'     => 'The owner\'s notes',
            'description' => 'The personal notes of the owner of the application - the pinned ones first, then the latest changed - followed by the notes colleagues shared with them, the latest changed first. A note is seen by nobody but the one who wrote it until it is shared, administrators included, so nobody else\'s notes are listed here. scope mine or shared keeps one of the two. text and body are null in the listing; GET /workspace/notes/{id} carries them.',
            'params'      => array(
                array('name' => 'scope', 'in' => 'query', 'type' => 'enum', 'values' => array('all', 'mine', 'shared'), 'default' => 'all'),
                array('name' => 'search', 'in' => 'query', 'type' => 'string', 'max_length' => 100, 'description' => 'Words in the title or the text.'),
                array('name' => 'updated_since', 'in' => 'query', 'type' => 'datetime', 'description' => 'Only notes changed at or after this moment.'),
            ),
        ),

        array(
            'id'          => 'workspace.notes.get',
            'method'      => 'GET',
            'path'        => '/workspace/notes/{id}',
            'scope'       => 'workspace:read',
            'handler'     => 'ws_api_notes_get',
            'returns'     => 'WorkspaceNote',
            'summary'     => 'One note, with its text',
            'description' => 'A note of the owner\'s, one shared with them, or one shared in a channel they may read. text is the note with every tag spelled out and every figure worked out; body keeps it as written. Reading it here does not mark it read.',
            'params'      => array(
                array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
            ),
        ),

        array(
            'id'          => 'workspace.tasks.list',
            'method'      => 'GET',
            'path'        => '/workspace/tasks',
            'scope'       => 'tasks:read',
            'handler'     => 'ws_api_tasks_list',
            'returns'     => array('list' => 'WorkspaceTask'),
            'summary'     => 'List tasks',
            'description' => 'Tasks the owner of the application may see. scope mine is the owner\'s own tasks, created those they gave out, department the tasks of a department, channel those of a channel, all every task (only for an owner who holds the team board). Pass ref_type and ref_id to find the tasks about one record, updated_since to fetch only what changed.',
            'params'      => array(
                array('name' => 'scope', 'in' => 'query', 'type' => 'enum', 'values' => array('mine', 'created', 'department', 'channel', 'all'), 'default' => 'mine'),
                array('name' => 'status', 'in' => 'query', 'type' => 'enum', 'values' => array('open', 'closed', 'all', 'todo', 'doing', 'waiting', 'done', 'cancelled'), 'default' => 'open'),
                array('name' => 'department_id', 'in' => 'query', 'type' => 'int', 'min' => 1),
                array('name' => 'channel_id', 'in' => 'query', 'type' => 'int', 'min' => 1),
                array('name' => 'ref_type', 'in' => 'query', 'type' => 'enum', 'values' => ws_record_type_keys()),
                array('name' => 'ref_id', 'in' => 'query', 'type' => 'int', 'min' => 1),
                array('name' => 'updated_since', 'in' => 'query', 'type' => 'datetime'),
                array('name' => 'limit', 'in' => 'query', 'type' => 'int', 'min' => 1, 'max' => 250, 'default' => 100),
            ),
        ),

        array(
            'id'          => 'workspace.tasks.get',
            'method'      => 'GET',
            'path'        => '/workspace/tasks/{id}',
            'scope'       => 'tasks:read',
            'handler'     => 'ws_api_tasks_get',
            'returns'     => 'WorkspaceTask',
            'summary'     => 'One task',
            'params'      => array(
                array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
            ),
        ),

        array(
            'id'          => 'workspace.tasks.create',
            'method'      => 'POST',
            'path'        => '/workspace/tasks',
            'scope'       => 'tasks:write',
            'handler'     => 'ws_api_tasks_create',
            'returns'     => 'WorkspaceTask',
            'summary'     => 'Create a task',
            'description' => 'Creates a task. channel_id posts its card into that channel. warnings says what the assignment does to the people\'s days (over their capacity, not in the department); a clash with somebody\'s leave is refused with 422 unless force is sent by an owner who may override. Send an Idempotency-Key so a retried call does not create two tasks.',
            'params'      => array_merge(array(
                array('name' => 'channel_id', 'in' => 'body', 'type' => 'int', 'min' => 0, 'description' => 'The channel the task belongs to; its card is posted there.'),
            ), $task_fields),
        ),

        array(
            'id'           => 'workspace.tasks.update',
            'method'       => 'POST',
            'also_accepts' => array('PATCH'),
            'path'         => '/workspace/tasks/{id}',
            'scope'        => 'tasks:write',
            'handler'      => 'ws_api_tasks_update',
            'returns'      => 'WorkspaceTask',
            'summary'      => 'Change a task',
            'description'  => 'Changes only the fields sent. assignees replaces the list; the people added are told. PATCH is accepted beside POST.',
            'params'       => array_merge(array(
                array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
            ), $task_fields),
        ),

        array(
            'id'          => 'workspace.tasks.complete',
            'method'      => 'POST',
            'path'        => '/workspace/tasks/{id}/complete',
            'scope'       => 'tasks:write',
            'handler'     => 'ws_api_tasks_complete',
            'returns'     => 'WorkspaceTask',
            'summary'     => 'Mark a task done',
            'description' => 'The same as setting status to done: the creator is told and the task\'s channel gets a line.',
            'params'      => array(
                array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
            ),
        ),

        array(
            'id'          => 'workspace.tasks.notes.list',
            'method'      => 'GET',
            'path'        => '/workspace/tasks/{id}/notes',
            'scope'       => 'tasks:read',
            'handler'     => 'ws_api_task_notes_list',
            'returns'     => array('list' => 'WorkspaceTaskNote'),
            'summary'     => 'Read the notes of a task',
            'description' => 'The dated notes people added to the task as the work went on, oldest first. Tags are written out as plain text.',
            'params'      => array(
                array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
            ),
        ),

        array(
            'id'          => 'workspace.tasks.notes.create',
            'method'      => 'POST',
            'path'        => '/workspace/tasks/{id}/notes',
            'scope'       => 'tasks:write',
            'handler'     => 'ws_api_task_notes_create',
            'returns'     => 'WorkspaceTaskNote',
            'summary'     => 'Add a note to a task',
            'description' => 'Written in the application\'s name. Tags such as <#order:1045> or <@user:12> are kept; a person mentioned is told.',
            'params'      => array(
                array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
                array('name' => 'text', 'in' => 'body', 'type' => 'string', 'max_length' => 4000, 'required' => true),
            ),
        ),

        array(
            'id'          => 'workspace.tasks.time.list',
            'method'      => 'GET',
            'path'        => '/workspace/tasks/{id}/time',
            'scope'       => 'tasks:read',
            'handler'     => 'ws_api_task_time_list',
            'returns'     => array('list' => 'WorkspaceTaskTime'),
            'summary'     => 'The time spent on a task',
            'description' => 'Every entry of time written on the task, newest day first: a stopped timer or minutes written by hand. A timer still running is listed with running true and minutes 0. invoice_id is the ERP invoice draft the entry went on.',
            'params'      => array(
                array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
            ),
        ),

        array(
            'id'          => 'workspace.tasks.time.create',
            'method'      => 'POST',
            'path'        => '/workspace/tasks/{id}/time',
            'scope'       => 'tasks:write',
            'handler'     => 'ws_api_task_time_create',
            'dry_run'     => true,
            'returns'     => 'WorkspaceTaskTime',
            'summary'     => 'Write time spent on a task',
            'description' => 'Written as the owner of the application\'s time: the owner must be on the task, have created it, or be staff. Announced as workspace.task.time_logged.',
            'params'      => array(
                array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
                array('name' => 'minutes', 'in' => 'body', 'type' => 'int', 'min' => 1, 'max' => 100000, 'required' => true),
                array('name' => 'worked_on', 'in' => 'body', 'type' => 'string', 'max_length' => 10, 'description' => 'YYYY-MM-DD, today or earlier. Today when left out.'),
                array('name' => 'note', 'in' => 'body', 'type' => 'string', 'max_length' => 255),
                array('name' => 'billable', 'in' => 'body', 'type' => 'bool', 'default' => true, 'description' => 'Whether the time may go on an invoice of the channel\'s customer.'),
            ),
        ),

        array(
            'id'           => 'workspace.tasks.time.delete',
            'method'       => 'DELETE',
            'also_accepts' => array('POST'),
            'path'         => '/workspace/tasks/{id}/time/{entry_id}',
            'scope'        => 'tasks:write',
            'handler'      => 'ws_api_task_time_delete',
            'dry_run'      => true,
            'returns'      => 'WorkspaceTaskTime',
            'summary'      => 'Delete an entry of time',
            'description'  => 'The owner\'s own entry, or anybody\'s for an owner who is staff; answers with the entry as it was. An entry on an ERP invoice draft is refused with 422 until the tie is undone. POST is accepted as well, because a default IIS install answers DELETE itself before PHP is reached.',
            'params'       => array(
                array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
                array('name' => 'entry_id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
            ),
        ),

        array(
            'id'          => 'workspace.tasks.recurrence.get',
            'method'      => 'GET',
            'path'        => '/workspace/tasks/{id}/recurrence',
            'scope'       => 'tasks:read',
            'handler'     => 'ws_api_task_recurrence_get',
            'returns'     => 'WorkspaceRecurrence',
            'summary'     => 'The repeat of a task',
            'description' => 'The series a repeating task belongs to: its rule, the due date of the next copy (next_date) and the day it is handed out (opens_date) and, once it has ended, why. 404 for a task that has never repeated.',
            'params'      => array(
                array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
            ),
        ),

        array(
            'id'          => 'workspace.tasks.recurrence.set',
            'method'      => 'POST',
            'path'        => '/workspace/tasks/{id}/recurrence',
            'scope'       => 'tasks:write',
            'handler'     => 'ws_api_task_recurrence_set',
            'returns'     => 'WorkspaceRecurrence',
            'summary'     => 'Make a task repeat, change or stop its repeat',
            'description' => 'A copy of the task is handed out to the same people for every date of the rule, whether or not the one before is finished. The rule counts from the task\'s due date (today when it has none); each copy opens as many days before its due date as the task starts before it (opens_date). frequency none stops the copies. The series ends by itself after end_date.',
            'params'      => array(
                array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
                array('name' => 'frequency', 'in' => 'body', 'type' => 'enum', 'values' => array('daily', 'weekly', 'monthly', 'yearly', 'none'), 'required' => true),
                array('name' => 'interval', 'in' => 'body', 'type' => 'int', 'min' => 1, 'max' => 99, 'default' => 1, 'description' => 'Every how many days, weeks, months or years.'),
                array('name' => 'weekdays', 'in' => 'body', 'type' => 'list', 'of' => 'integer', 'max_items' => 7, 'description' => 'Weekly only: ISO days, 1 for Monday to 7 for Sunday. Empty repeats on the weekday of the due date.'),
                array('name' => 'end_date', 'in' => 'body', 'type' => 'string', 'max_length' => 10, 'description' => 'YYYY-MM-DD, the last day a copy may fall on. Empty for no end.'),
            ),
        ),

        array(
            'id'          => 'workspace.tasks.recurrence.end',
            'method'      => 'POST',
            'path'        => '/workspace/tasks/{id}/recurrence/end',
            'scope'       => 'tasks:write',
            'handler'     => 'ws_api_task_recurrence_end',
            'returns'     => 'WorkspaceRecurrence',
            'summary'     => 'End the repeat of a task',
            'description' => 'complete marks the task done and stops the copies: the task is completed for good. stop only stops the copies. The copies already handed out stay as they are.',
            'params'      => array(
                array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
                array('name' => 'mode', 'in' => 'body', 'type' => 'enum', 'values' => array('complete', 'stop'), 'default' => 'complete'),
            ),
        ),

        array(
            'id'          => 'workspace.departments.list',
            'method'      => 'GET',
            'path'        => '/workspace/departments',
            'scope'       => 'workspace:read',
            'handler'     => 'ws_api_departments_list',
            'returns'     => array('list' => 'WorkspaceDepartment'),
            'summary'     => 'List departments',
            'description' => 'The departments with their members and leads.',
            'params'      => array(),
        ),

        array(
            'id'          => 'workspace.refs.get',
            'method'      => 'GET',
            'path'        => '/workspace/refs',
            'scope'       => 'workspace:read',
            'handler'     => 'ws_api_refs_get',
            'returns'     => 'WorkspaceRefs',
            'summary'     => 'Where a record was talked about',
            'description' => 'The messages and the tasks that point at one record - an order, a product or product group, an offer, a contact, a user, a current account, an invoice, a delivery note, a cash receipt, an incoming e-invoice, a form submission, a calendar event, a file or a page - in the channels the owner may read. For a contact, the channels tied to them as well.',
            'params'      => array(
                array('name' => 'type', 'in' => 'query', 'type' => 'enum', 'values' => ws_record_type_keys(), 'required' => true),
                array('name' => 'id', 'in' => 'query', 'type' => 'int', 'min' => 1, 'required' => true),
            ),
        ),

        array(
            'id'          => 'workspace.plan.get',
            'method'      => 'GET',
            'path'        => '/workspace/plan',
            'scope'       => 'tasks:read',
            'handler'     => 'ws_api_plan_get',
            'returns'     => array('list' => 'WorkspacePlanRow'),
            'summary'     => 'The planning board',
            'description' => 'One row per person, one entry per day: the minutes planned, the capacity and the load in percent, leave and holidays. Only the people the owner may see on the board: everyone for a board holder, the owner and the departments they lead otherwise.',
            'params'      => array(
                array('name' => 'from', 'in' => 'query', 'type' => 'string', 'max_length' => 10, 'description' => 'YYYY-MM-DD. Monday of this week when left out.'),
                array('name' => 'days', 'in' => 'query', 'type' => 'int', 'min' => 1, 'max' => 31, 'default' => 7),
                array('name' => 'department_id', 'in' => 'query', 'type' => 'int', 'min' => 1),
            ),
        ),

        array(
            'id'          => 'workspace.templates.list',
            'method'      => 'GET',
            'path'        => '/workspace/templates',
            'scope'       => 'workspace:read',
            'handler'     => 'ws_api_templates_list',
            'returns'     => array('list' => 'WorkspaceTemplate'),
            'summary'     => 'List channel templates',
            'description' => 'The templates a channel can be set up with: the three built-in ones (ids such as builtin:new_customer, in the language of the site) and the site\'s own that are not archived (numeric ids as strings). tasks and notes count what a template brings; summary, pinned and welcome say whether it carries those texts.',
            'params'      => array(),
        ),

        array(
            'id'          => 'workspace.channels.apply_template',
            'method'      => 'POST',
            'path'        => '/workspace/channels/{id}/apply-template',
            'scope'       => 'workspace:write',
            'handler'     => 'ws_api_channel_apply_template',
            'dry_run' => true,
            'returns'     => 'WorkspaceTemplateApplied',
            'summary'     => 'Apply a template to a channel',
            'description' => 'Adds the template\'s tasks and notes to a public or private channel the owner of the application may write in, the way the channel menu does. The tasks are made by the owner, their dates counted in calendar days from today; a task meant for somebody the owner may not give work to is made with nobody on it and counted in unassigned. The notes become the owner\'s own and are shared in the channel. The summary and the pinned message are written only where the channel has none (summary_kept and pinned_kept say when it had); the pinned message is written as the application\'s. The channel gets a line saying the template was applied, and workspace.template.applied is announced.',
            'params'      => array(
                array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
                array('name' => 'template_id', 'in' => 'body', 'type' => 'string', 'max_length' => 40, 'required' => true, 'description' => 'An id from GET /workspace/templates.'),
            ),
        ),

    );

    // Asking Claude in a channel (api_claude.php).
    return array_merge($routes, ws_claude_api_routes());
}
