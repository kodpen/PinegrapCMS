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
 * message came from an integration and not from a colleague. A person signed
 * in on their own device is that colleague, and writes in their own name
 * (ws_api_writer_app_id()).
 *
 * The handlers and presenters are in api_resources.php beside this file, and
 * in the api_*.php files it loads.
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
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/api_flows.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/api_threads.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/api_day.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/api_sync.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/api_inbox.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/api_mcp.php');

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
        'workspace.task.delegated'      => 'A workspace task was handed to an assistant (agent: ai, claude, or mcp:<server id> for an MCP server\'s alias); the people on it stay responsible (delegated_by, request_id)',
        'workspace.task.assistant_answered' => 'An assistant finished a run on a workspace task (outcome: answered or failed; agent, request_id, requested_by). The answer itself, and what it proposed, are read with GET /workspace/assistant-requests and GET /workspace/changes by task_id',
        'workspace.approval.requested'  => 'An approval request was made in a public workspace channel: its title, rule, deadline, the people asked (guests: the names of the people outside the team) and the record it is about',
        'workspace.approval.decided'    => 'An approval request in a public workspace channel was settled - approved, rejected, expired or closed without a decision - with each person\'s answer, the answers from outside the team under guests',
        'workspace.channel_group.created'        => 'A group of workspace channels was made, in the channel list or through the API (id, name, parent_id, path, color, created_by)',
        'workspace.channel_group.updated'        => 'A group of workspace channels was renamed, given another colour or moved inside another group: changed lists which, previous holds the group as it was. A new place among the groups of the same level is not announced',
        'workspace.channel_group.access_changed' => 'Access on a group of workspace channels was given (added) or taken back (removed): user ids. The channel memberships that access brings or ends follow from it and are not announced one by one',
        'workspace.channel_group.deleted'        => 'A group of workspace channels was taken away: its channels (channel_ids) and groups (group_ids) moved up to parent_id and the access given on it ended (access_ended). Subscriptions narrowed to the groups that moved up hear it; those narrowed to the removed group are switched off first and do not',
        'workspace.app.mentioned'       => 'An application was mentioned (<@app:N>) in a workspace channel or discussion. Only that application\'s own subscriptions hear it, once per message (an edit tells only the applications it adds, with edited true), and only where it may read the words: its key active, not expired and holding workspace:read, in a public channel or in a private channel, guest room or discussion its owner is in. Elsewhere nothing is sent, nor to the applications Claude and Pinegrap AI work through. Its own messages never reach it; app_id names another application that wrote. In a discussion, channel_id is the discussion\'s own and thread holds the channel and the message it is about. Answer with POST /workspace/channels/{channel_id}/messages',
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
        'WorkspaceContextThread'  => 'ws_api_context_thread_schema',
        'WorkspaceContextMessage' => 'ws_api_context_message_schema',
        'WorkspaceChangeType'     => 'ws_api_claude_change_type_schema',
        'WorkspaceRecord'         => 'ws_api_claude_record_schema',
        'WorkspaceRecordLine'     => 'ws_api_claude_record_line_schema',
        'WorkspaceChannelMember'  => 'ws_api_channel_member_schema',
        'WorkspaceChannelMembers' => 'ws_api_channel_members_schema',
        'WorkspaceChange'         => 'ws_api_change_schema',
        'WorkspaceAssistantRequest' => 'ws_api_assistant_request_schema',
        'WorkspaceNote'           => 'ws_api_note_schema',
        'WorkspaceTemplate'        => 'ws_api_template_schema',
        'WorkspaceTemplateApplied' => 'ws_api_template_applied_schema',
        'WorkspaceTaskTime'       => 'ws_api_task_time_schema',
        'WorkspaceApproval'       => 'ws_api_approval_schema',
        'WorkspaceThread'           => 'ws_api_thread_schema',
        'WorkspaceScheduledMessage' => 'ws_api_scheduled_message_schema',
        'WorkspaceNoteVersion'      => 'ws_api_note_version_schema',
        'WorkspacePlanItem'         => 'ws_api_plan_item_schema',
        'WorkspaceSearchHit'        => 'ws_api_search_hit_schema',
        'WorkspaceMyDay'            => 'ws_api_my_day_schema',
        'WorkspaceSync'             => 'ws_api_sync_schema',
        'WorkspaceSyncThread'       => 'ws_api_sync_thread_schema',
        'WorkspaceInboxItem'        => 'ws_api_inbox_schema',
        'WorkspaceSyncChannelState' => 'ws_api_sync_channel_state_schema',
        'WorkspaceSyncPin'          => 'ws_api_sync_pin_schema',
        'WorkspaceSyncTabCounts'    => 'ws_api_sync_tab_counts_schema',
        'WorkspacePoll'             => 'ws_api_poll_schema',
        'WorkspaceChannelGroup'     => 'ws_api_channel_group_schema',
        'WorkspaceChannelGroupMove' => 'ws_api_channel_group_move_schema',
        'WorkspacePerson'           => 'ws_api_person_schema',
        'WorkspaceMessageSender'    => 'ws_api_message_sender_schema',
        'WorkspaceMessageFile'      => 'ws_api_message_file_schema',
        'WorkspaceMessageThread'    => 'ws_api_message_thread_schema',
        'WorkspaceApprovalRecord'   => 'ws_api_approval_record_schema',
        'WorkspaceChannelGroupMemberships'  => 'ws_api_channel_group_memberships_schema',
        'WorkspaceChannelGroupChange'       => 'ws_api_channel_group_change_schema',
        'WorkspaceChannelGroupRemoval'      => 'ws_api_channel_group_removal_schema',
        'WorkspaceChannelGroupAccess'       => 'ws_api_channel_group_access_schema',
        'WorkspaceChannelGroupAccessChange' => 'ws_api_channel_group_access_change_schema',
        'WorkspaceDecisionChange'   => 'ws_api_decision_change_schema',
        'WorkspaceDecisionFile'     => 'ws_api_decision_file_schema',
        'WorkspaceNoteSource'       => 'ws_api_note_source_schema',
        'WorkspaceFlow'             => 'ws_api_flow_schema',
        'WorkspaceFlowVersion'      => 'ws_api_flow_version_schema',
        'WorkspaceFlowGraph'        => 'ws_api_flow_graph_schema',
        'WorkspaceFlowProblem'      => 'ws_api_flow_problem_schema',
        'WorkspaceMcpConnector'     => 'ws_api_mcp_connector_schema',
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
        array('name' => 'refs', 'in' => 'body', 'type' => 'list', 'of' => array('type' => 'string', 'id' => 'integer'), 'max_items' => 20, 'description' => 'Records the task is about, as objects of {type, id}: order, product, product_group, offer, contact, user_account, erp_account, invoice, waybill, receipt, edoc, erp_quote, form, calendar_event, file or page.'),
        array('name' => 'force', 'in' => 'body', 'type' => 'bool', 'description' => 'Hand the work over even though somebody on it is away on those days. Refused unless the owner may override the board.'),
        array('name' => 'blocked_by', 'in' => 'body', 'type' => 'list', 'of' => 'integer', 'max_items' => 20, 'description' => 'Ids of the tasks this one cannot start before; replaces the list (links to tasks the owner cannot see are kept). A list that would make tasks wait for each other is refused with 422. Not enforced: a blocked task can still be started.'),
    );

    $plan_item_fields = array(
        array('name' => 'kind', 'in' => 'body', 'type' => 'enum', 'values' => array_keys(ws_event_kinds()), 'description' => 'meeting when left out on a new item.'),
        array('name' => 'title', 'in' => 'body', 'type' => 'string', 'max_length' => 255, 'description' => 'The kind\'s name when left empty.'),
        array('name' => 'note', 'in' => 'body', 'type' => 'string', 'max_length' => 500),
        array('name' => 'end_date', 'in' => 'body', 'type' => 'string', 'max_length' => 10, 'description' => 'YYYY-MM-DD, for an item over several days; the start day when left out.'),
        array('name' => 'all_day', 'in' => 'body', 'type' => 'bool', 'description' => 'Always true for leave and holidays.'),
        array('name' => 'start_time', 'in' => 'body', 'type' => 'string', 'max_length' => 5, 'description' => 'HH:MM, when not all day; 09:00 when left out on a new item.'),
        array('name' => 'end_time', 'in' => 'body', 'type' => 'string', 'max_length' => 5, 'description' => 'HH:MM on the end day, when not all day; 10:00 when left out on a new item.'),
        array('name' => 'scope', 'in' => 'body', 'type' => 'enum', 'values' => array('people', 'department', 'company'), 'description' => 'Who the item is about; people when left out on a new item.'),
        array('name' => 'department_id', 'in' => 'body', 'type' => 'int', 'min' => 1, 'description' => 'For scope department. See GET /workspace/departments.'),
        array('name' => 'people', 'in' => 'body', 'type' => 'list', 'of' => 'integer', 'max_items' => 50, 'description' => 'User ids, for scope people; ids of people outside the team are left out.'),
        array('name' => 'meeting_url', 'in' => 'body', 'type' => 'string', 'max_length' => 500, 'description' => 'The video call: a Google Meet, Teams, Zoom, Jitsi or other http(s) address. Empty clears it. A timed item with one tells its people ten minutes before it starts.'),
    );

    // The parts of a flow's draft (includes/workspace/flows.php); a change
    // replaces each part sent as a whole.
    $flow_fields = array(
        array('name' => 'name', 'in' => 'body', 'type' => 'string', 'max_length' => 160, 'description' => 'What the flow is called. Runs of spaces are made one.'),
        array('name' => 'channel_id', 'in' => 'body', 'type' => 'int', 'min' => 0, 'description' => 'Its home: a public or private channel the owner of the application reads, whose readers may run the flow once it is live. 0 for none, a flow only its owner sees.'),
        array('name' => 'nodes', 'in' => 'body', 'type' => 'list', 'of' => array('id' => 'string', 'kind' => 'string', 'label' => 'string', 'x' => 'integer', 'y' => 'integer', 'config' => 'object'), 'max_items' => 1000, 'description' => 'The boxes, as objects of {id, kind, label, x, y, config}. id: small letters, digits and _, starting with a letter, at most 32 characters, once in the flow. kind: manual, the box that starts the flow by hand (config {subject}: empty, or the kind of record a run is about, such as order or contact); task, which opens a task and waits for it (config {title, up to 200 characters; description; assignees, up to 20 user ids, or assign: creator, whoever starts the run, department_lead, department:ID, user:ID or none; due_in, days from when the box is reached, 0-3650, or empty for none; priority: low, normal, high or urgent}); or end. x and y place it on the canvas. Replaces every box of the draft; left out of a change, the boxes are kept.'),
        array('name' => 'edges', 'in' => 'body', 'type' => 'list', 'of' => array('from' => 'string', 'to' => 'string', 'on' => 'string'), 'max_items' => 5000, 'description' => 'The arrows, as objects of {from, to, on}: from the box with the id from to the box with the id to, taken when from ends on the outcome on: next for a start box, done or cancelled for a task; left out, the box\'s first. A box with several arrows in waits for all of them. Replaces every arrow of the draft; left out of a change, the arrows are kept.'),
        array('name' => 'notes', 'in' => 'body', 'type' => 'list', 'of' => array('id' => 'string', 'text' => 'string', 'x' => 'integer', 'y' => 'integer', 'w' => 'integer', 'h' => 'integer'), 'max_items' => 1000, 'description' => 'Notes on the canvas, as objects of {id, text, x, y, w, h}, up to 2,000 characters each; they are never checked. Replaces every note of the draft; left out of a change, the notes are kept.'),
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
            'description' => 'The channels the owner of the application may read: every public channel, and the private ones the owner is in. unread counts what the owner has not read. notify says how the channel tells the owner about what is written in it (POST /workspace/channels/{id}/notify). They come in the owner\'s own order, as their sidebar shows them: the pinned ones first, then by order, then by name; pinned and order on each channel say where it stands (POST /workspace/channels/{id}/prefs). color is the channel\'s colour from the palette, null for none; icon is the Bootstrap Icons class it is drawn with: the one chosen for it when icon_chosen is true (a private, guest or customer channel then shows its mark in the corner), else the one its kind gives. active_since keeps the ones written in since a moment, which is how an app that already holds the list refreshes it.',
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
            'status'      => 201,
            'summary'     => 'Create a channel',
            'description' => 'Creates a channel the way the channel form does, with the owner of the application as its owner: a public channel is read by the whole team, a private one only by the people in it. members are brought in and told; ids of people who are not in the team are left out. The channel gets its first line in the owner\'s name. A name another open channel has is refused with 409 name_taken. Send an Idempotency-Key so a retried call does not create two channels.',
            'params'      => array(
                array('name' => 'name', 'in' => 'body', 'type' => 'string', 'max_length' => 80, 'required' => true, 'description' => 'A leading # is dropped and runs of spaces are made one.'),
                array('name' => 'kind', 'in' => 'body', 'type' => 'enum', 'values' => array('public', 'private'), 'default' => 'public'),
                array('name' => 'topic', 'in' => 'body', 'type' => 'string', 'max_length' => 255),
                array('name' => 'department_id', 'in' => 'body', 'type' => 'int', 'min' => 0, 'description' => 'See GET /workspace/departments. 0 for none.'),
                array('name' => 'members', 'in' => 'body', 'type' => 'list', 'of' => 'integer', 'max_items' => 100, 'description' => 'User ids of the colleagues to bring in besides the owner.'),
                array('name' => 'color', 'in' => 'body', 'type' => 'enum', 'values' => ws_palette_hexes(), 'nullable' => true, 'description' => 'One of the palette\'s colours, as color on a channel group shows it; null or empty for none.'),
                array('name' => 'icon', 'in' => 'body', 'type' => 'enum', 'values' => array_keys(ws_channel_icon_choices()), 'nullable' => true, 'description' => 'A Bootstrap Icons class from the channel form\'s list; null or empty keeps the icon the kind gives (a hash, or a lock for a private channel).'),
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
                array('name' => 'color', 'in' => 'body', 'type' => 'enum', 'values' => ws_palette_hexes(), 'nullable' => true, 'description' => 'One of the palette\'s colours; null or empty for none.'),
                array('name' => 'icon', 'in' => 'body', 'type' => 'enum', 'values' => array_keys(ws_channel_icon_choices()), 'nullable' => true, 'description' => 'A Bootstrap Icons class from the channel form\'s list; null or empty brings back the icon the kind gives.'),
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
            'description' => 'Messages oldest first. Pass since_id to read only what arrived after a message - the way to follow a channel - or before_id to page back. changed_since reads instead the messages already written that changed at or after a moment: an edit, a deletion, a reaction, a tick, a vote or a read receipt, each as it is now (deleted true, edited true and so on), and for the person alone a message they deleted for themselves since then, on this device or another (hidden true). Pass the moment of the previous read by the site\'s clock, the Date header of its answer; when as many messages as limit come back, ask again with since_id set to the last one. hidden is true on a message the person deleted for themselves (POST /workspace/messages/{id}/hide): leave it out of view, as the panel does. text is the message with every tag spelled out, each item of a list and each quoted line on a line of its own ("- item", "1. item", "> line"); body keeps the tags as written. id may also be a discussion\'s (thread.id of a message, or GET /workspace/channels/{id}/threads), read by the people in it. thread is set on a message a discussion was started from (POST /workspace/threads/{id}/join brings the person into it); from_thread_id on a decision, a note or a task card copied into the channel from a discussion that is still there. poll is set on a message that asks a poll: its options with their counts, who chose them unless it is anonymous, and mine on the person\'s own choices; can_vote, can_edit and can_close say whether the person may vote (POST /workspace/polls/{id}/vote), change it (POST /workspace/polls/{id}) or close it (POST /workspace/polls/{id}/close), and POST /workspace/channels/{id}/polls asks one. file is set on a message that carries one: its name, kind (image, video, audio, pdf or file), size in bytes, url - the site\'s own address, which opens only in a browser signed in to the site - and api_url, which fetches it with the same token (GET /workspace/messages/{id}/file).',
            'params'      => array(
                array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
                array('name' => 'since_id', 'in' => 'query', 'type' => 'int', 'min' => 0),
                array('name' => 'before_id', 'in' => 'query', 'type' => 'int', 'min' => 0),
                array('name' => 'changed_since', 'in' => 'query', 'type' => 'datetime', 'description' => 'Only the messages that changed at or after this moment; before_id is then ignored.'),
                array('name' => 'limit', 'in' => 'query', 'type' => 'int', 'min' => 1, 'max' => 200, 'default' => 50),
            ),
        ),

        array(
            'id'          => 'workspace.sync',
            'method'      => 'GET',
            'path'        => '/workspace/sync',
            'scope'       => 'workspace:read',
            'handler'     => 'ws_api_sync',
            'returns'     => 'WorkspaceSync',
            'mcp'         => false,
            'summary'     => 'Keep an open app up to date',
            'description' => 'What a team app on a phone or a computer needs to stay current, in one call, the way the panel\'s own screen keeps itself up to date. Call it every few seconds while the app is in front, passing back the cursor of the previous answer; leave it out on the first call. channels is the whole list GET /workspace/channels gives, and threads the open discussions the person is in, each sent only when something in it changed since the cursor (a new message, an unread count, a name); null means keep what you have. inbox_unread counts the workspace inbox; GET /workspace/inbox lists it. inbox_changed_at is the last moment a line of it was written, sent again, read, taken away or given back (null for an empty inbox): when it moves, ask GET /workspace/inbox?changed_since= with the value you held before. notifications_unread is the number on the person\'s bell, as GET /notifications/unread-count gives it, for a caller holding the Own account read permission (a team devices session always does; an application or an OAuth connection only when it was given it); null otherwise. With channel_id, messages holds what was written there since the cursor - the newest 50 when the channel was not open at the previous call; page back with GET /workspace/channels/{id}/messages?before_id= - and changed the messages already sent that were edited, deleted, reacted to, ticked, voted on or acknowledged since, each as it is now, and the ones the person deleted for themselves since, on this device or another, with hidden true. more true means more new messages wait: call again at once. reload true means more changed than one answer carries: read the channel again. gone true means the person can no longer read channel_id. From a team devices session, channel_state carries what the panel\'s channel screen shows around the messages of channel_id, sent again only when something in it changed (null means keep what you have): pin is the message pinned to the top of the channel (null when none; hidden true when the person closed it on the panel, so leave it closed), tab_counts the decisions and notes, the open tasks and whether the summary is written (null for a discussion), and board a mark that moves whenever the channel\'s tasks, decisions, files, pin or summary change, so read again the ones the app shows (GET /workspace/tasks?scope=channel&channel_id=, GET /workspace/decisions?channel_id=; null for a discussion or a guest\'s room). When nothing changed, the answer is the previous one again, cursor included, so its ETag in If-None-Match gets 304 with no body. From a team devices session (POST /auth/login) the call also counts as the person looking at the workspace, as the panel\'s open screen does: a mention waits about a minute before it wakes their devices, and is not sent once they have read it. While a request to Pinegrap AI or an MCP server\'s own agent waits in channel_id, a call from a team devices session also carries it on, as the panel\'s open screen does: where the server can send the answer first, right after it; otherwise (IIS) before the answer, one model or agent call at a time, so that call takes as long and brings what was written, while another app\'s call meanwhile returns at once. Nothing is marked read here; POST /workspace/channels/{id}/read does that.',
            'params'      => array(
                array('name' => 'cursor', 'in' => 'query', 'type' => 'string', 'max_length' => 200, 'description' => 'The cursor of the previous answer. Left out on the first call.'),
                array('name' => 'channel_id', 'in' => 'query', 'type' => 'int', 'min' => 1, 'description' => 'The channel open on the screen, or a discussion\'s id (threads[].id).'),
            ),
        ),

        array(
            'id'          => 'workspace.inbox.list',
            'method'      => 'GET',
            'path'        => '/workspace/inbox',
            'scope'       => 'workspace:read',
            'handler'     => 'ws_api_inbox_list',
            'returns'     => array('list' => 'WorkspaceInboxItem', 'reload' => 'boolean'),
            'summary'     => 'The owner\'s workspace inbox',
            'description' => 'What the owner of the application was told in the workspace, newest first, as the inbox on the workspace screen says it: title and body are the panel\'s sentences, url its address, and the ids say what the line is about, so an app opens its own screen. kind is one of mention, assigned, completed, note, unblocked, invited, thread, guest_message, note_shared, note_updated, note_answer, note_answer_ai, task_note, task_assistant, task_reminder, task_overdue, approval_requested, approval_decided, approval_reminder, approval_answered, ack_reminder, agent_answer, scheduled_notice, scheduled_failed, scheduled_message and meeting_soon; more may come, so treat an unknown kind by its url. channel_id may be a discussion\'s (read it with GET /workspace/channels/{id}/messages), and parent_channel_id is then the channel it was started in; for kind thread, message_id is the message the discussion was started from, in parent_channel_id. meeting_url is the video call of a meeting about to start. A workspace line in the bell (GET /notifications) has subject type workspace_inbox and the id of its line here. Lines about a channel, a task, a message or a note the owner can no longer see are left out and count as read, so a page can be shorter than limit with a next_cursor; follow the cursor as usual. changed_since reads instead, oldest first, the lines that changed at or after a moment: written, sent again (a note changed once more) or read - with POST /workspace/inbox/read, on the workspace screen or in the bell - each as it is now. Pass the moment of the previous read by the site\'s clock, the Date header of its answer; when as many lines as limit come back, ask again with since_id set to the last one. A line taken away since - a mention in a message deleted, a note deleted or no longer shared, a concluded discussion deleted - comes back with deleted true and only its id, kind and created_at: drop it, and ignore an id you never held. So does a line about something the owner can no longer see, from the moment it went out of their sight - a private channel they left or were taken out of, or that was made private without them, its discussions and its tasks that are not theirs; should GET /workspace/inbox/{id} answer 404 for a line held, drop it as well. When the owner is let back in - added to the channel again, brought back by a group, the channel made public, a discussion joined again - those lines come back among the changes from that moment, each as it is now and still read: hold them again. Lines taken away are remembered for 30 days: reload is true when changed_since is further back than that, and the lines that came back may then be missing some taken away, so read the inbox again from the first page and ask for changes from then on (reload is always false without changed_since). Reading marks nothing read; POST /workspace/inbox/read does.',
            'params'      => array(
                array('name' => 'unread', 'in' => 'query', 'type' => 'bool', 'description' => 'Only the lines not read yet.'),
                array('name' => 'limit', 'in' => 'query', 'type' => 'int', 'min' => 1, 'max' => 100, 'default' => 30, 'description' => 'Lines per page.'),
                array('name' => 'cursor', 'in' => 'query', 'type' => 'string', 'max_length' => 200, 'description' => 'next_cursor from the previous page.'),
                array('name' => 'changed_since', 'in' => 'query', 'type' => 'datetime', 'description' => 'Only the lines written, sent again, read or taken away at or after this moment, oldest first; unread and cursor are then ignored.'),
                array('name' => 'since_id', 'in' => 'query', 'type' => 'int', 'min' => 0, 'description' => 'With changed_since: only the lines after this one, to page through the changes.'),
            ),
        ),

        array(
            'id'          => 'workspace.inbox.read',
            'method'      => 'POST',
            'path'        => '/workspace/inbox/read',
            'scope'       => 'workspace:write',
            'handler'     => 'ws_api_inbox_read',
            'dry_run' => true,
            'returns'     => array('updated' => 'integer', 'unread' => 'integer'),
            'summary'     => 'Mark inbox lines read',
            'description' => 'Marks lines of the person\'s workspace inbox read, the way the inbox on the workspace screen does: send ids, or all true for every line. Their lines in the bell are marked read with them, and a banner still waiting for one is not sent. Ids of somebody else\'s lines, and lines already read, are skipped rather than refused: updated says how many were marked, and unread is the count afterwards. Only a device session may - a team devices session (a Bearer token from POST /auth/login) or an OAuth connection: an application key is refused with 403 forbidden, since an integration reading the inbox is not its owner reading it.',
            'params'      => array(
                array('name' => 'ids', 'in' => 'body', 'type' => 'list', 'of' => 'integer', 'max_items' => 200, 'description' => 'Inbox line ids (GET /workspace/inbox, or the subject id of a bell line).'),
                array('name' => 'all', 'in' => 'body', 'type' => 'bool', 'description' => 'Every line not read yet.'),
            ),
        ),

        array(
            'id'          => 'workspace.inbox.get',
            'method'      => 'GET',
            'path'        => '/workspace/inbox/{id}',
            'scope'       => 'workspace:read',
            'handler'     => 'ws_api_inbox_get',
            'returns'     => 'WorkspaceInboxItem',
            'summary'     => 'One line of the workspace inbox',
            'description' => 'One line of the owner\'s workspace inbox, by its id: what an app woken by a banner reads to open the right screen, when the bell line (GET /notifications) has subject type workspace_inbox. A line that is somebody else\'s, or about something the owner can no longer see, is a 404. Reading it does not mark it read; POST /workspace/inbox/read does.',
            'params'      => array(
                array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
            ),
        ),

        array(
            'id'          => 'workspace.messages.create',
            'method'      => 'POST',
            'path'        => '/workspace/channels/{id}/messages',
            'scope'       => 'workspace:write',
            'handler'     => 'ws_api_messages_create',
            'dry_run'     => true,
            'returns'     => 'WorkspaceMessage',
            'status'      => 201,
            'summary'     => 'Write in a channel',
            'description' => 'Writes a message into a channel the owner may post in. Written with an application key or through an OAuth connection, readers see it as written by the application. Written by a person signed in on their own device (POST /auth/login), it is theirs, as if written in the panel: it carries their name, counts against their limit of twenty messages a minute (422 beyond it), joins them to a public channel they were not in and marks the channel read for them; a message (kind message) of theirs that names @Claude, @ai or an MCP server\'s alias asks that assistant, with the same rules as the panel (a channel where it may not be asked gets a line saying why). Claude is started at once; Pinegrap AI and an MCP server\'s own agent are carried on right after the answer where the server can send the answer first, otherwise by the app\'s next GET /workspace/sync with the channel open, the screens that have it open and the hourly job. Follow the request with GET /workspace/assistant-requests. Tags are written as tokens - <@user:12> mentions a person (they are told), <@app:7> an application (its subscriptions to workspace.app.mentioned hear it), <#order:1045> points at an order. kind decision or note keeps the message on the channel\'s Decisions tab. id may also be a discussion\'s the owner is in: a decision, a note or a task card written there is copied into its channel as well. ack true asks the channel\'s members to say they read it; the writer of a message or staff may ask, so for a message written as the application only an owner who is staff may, and anybody else is refused with 403 before anything is written. A dry run makes every check, the person\'s limit included, and its detail.joins says whether the message would join the person to the channel. Send an Idempotency-Key so a retried call does not post twice.',
            'params'      => array(
                array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
                array('name' => 'text', 'in' => 'body', 'type' => 'string', 'max_length' => 4000, 'required' => true),
                array('name' => 'kind', 'in' => 'body', 'type' => 'enum', 'values' => array('message', 'note', 'decision')),
                array('name' => 'ack', 'in' => 'body', 'type' => 'bool', 'description' => 'Ask for read receipts on the message once it is written.'),
            ),
        ),

        array(
            'id'          => 'workspace.files.create',
            'method'      => 'POST',
            'path'        => '/workspace/channels/{id}/files',
            'scope'       => 'workspace:write',
            'handler'     => 'ws_api_files_create',
            'dry_run'     => true,
            'returns'     => 'WorkspaceMessage',
            'status'      => 201,
            'form_file'   => 'file',
            'mcp'         => false,
            'summary'     => 'Post a file in a channel',
            'description' => 'Posts a picture, a video, a document or any other file into a channel the owner may post in, as a message carrying it, the way the paperclip in the panel does; text goes with it. Send the file as multipart/form-data in a part named file, with name and text as form fields if wanted - the way for a large file - or as content_base64 in a JSON body with name. What the channel takes is what the panel takes: at most 20 MB, or less where the server sets a lower limit (422 beyond it); no programs, scripts or server files, no SVG, HTML, XML or JavaScript, and no Office files with macros; a picture has to be a picture. The file goes into the channel\'s folder in the file manager, and a discussion\'s into its channel\'s. Who it is written as follows POST /workspace/channels/{id}/messages: the application with an application key or an OAuth connection, the person from a team devices session (POST /auth/login), with their limit of twenty messages a minute. parent_id makes the file an answer to a message, as Reply does in the panel (where a sound\'s own microphone answers it with a voice message): the message must be one of this channel or discussion, not deleted and not in an earlier version of the conversation, or the call is refused with 422 on parent_id. A voice message is a sound file posted this way; the panel records it as voice-YYYYMMDD-HHMMSS with .weba, .ogg or .m4a. The answer is the message; its file.api_url fetches the file with the same token (GET /workspace/messages/{id}/file). A dry run checks the file and parent_id the same way without keeping anything - nothing reaches the disk or the file manager - and its detail.file gives the name, kind and size that would be posted. Send an Idempotency-Key so a retried upload does not post twice; the same key sent with another file, or another name, text or parent_id, is refused with 409 idempotency_key_reused.',
            'params'      => array(
                array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
                array('name' => 'name', 'in' => 'body', 'type' => 'string', 'max_length' => 200, 'description' => 'The file\'s name with its extension, as readers see it. Required with content_base64; a form part\'s own file name is used when it is left out.'),
                array('name' => 'content_base64', 'in' => 'body', 'type' => 'string', 'description' => 'The file, base64 encoded, in a JSON body. Leave it out when the file is sent as a form part named file.'),
                array('name' => 'text', 'in' => 'body', 'type' => 'string', 'max_length' => 4000, 'description' => 'Words to go with the file. Tags are written as tokens, as when writing.'),
                array('name' => 'parent_id', 'in' => 'body', 'type' => 'int', 'min' => 1, 'description' => 'The message the file answers (GET /workspace/channels/{id}/messages), in the same channel or discussion. Left out, the file is a message of its own.'),
            ),
        ),

        array(
            'id'          => 'workspace.files.get',
            'method'      => 'GET',
            'path'        => '/workspace/messages/{id}/file',
            'scope'       => 'workspace:read',
            'handler'     => 'ws_api_files_get',
            'binary'      => true,
            'mcp'         => false,
            'summary'     => 'Download the file of a message',
            'description' => 'The file a message carries, as its own bytes rather than JSON, with the Bearer token or the application key every other call uses; file.url in the message is the site\'s own address, which opens only in a browser signed in to the site. Whoever may read the message may fetch its file; a message deleted for everyone has none (404). Pictures, sound, video and PDF come with their own Content-Type and inline; anything else as application/octet-stream and as an attachment. Content-Disposition carries the name the author gave it. The ETag stays the same while the file does, so If-None-Match gets 304 with no body. A Range header (bytes=first-last, one range) gets that part alone with 206 and Content-Range, so a player starts a video and jumps ahead without downloading all of it; a range past the end is 416, and If-Range with an ETag that no longer matches gets the whole file. size asks for a smaller copy of a picture for a list (422 for any other kind of file). id may be a message of a discussion the person is in.',
            'params'      => array(
                array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
                array('name' => 'size', 'in' => 'query', 'type' => 'int', 'min' => 1, 'max' => 4096, 'description' => 'For a picture: a smaller copy whose longest edge is the first of 160, 320, 640, 1280 or 1920 pixels that is at least size (1920 at most), upright and in the picture\'s own format. A picture already that small, or one that cannot be drawn smaller here (an animated GIF, PNG or WebP), comes as it is.'),
            ),
        ),

        // Changing, deleting and hiding the person's own messages come from
        // their team devices session only (ws_api_own_words_only()); an MCP
        // client signs in through an OAuth connection, which is refused, so
        // none of them is offered to it as a tool.
        array(
            'id'           => 'workspace.messages.update',
            'method'       => 'POST',
            'also_accepts' => array('PATCH'),
            'path'         => '/workspace/messages/{id}',
            'scope'        => 'workspace:write',
            'handler'      => 'ws_api_messages_update',
            'dry_run'      => true,
            'mcp'          => false,
            'returns'      => 'WorkspaceMessage',
            'summary'      => 'Change one\'s own message',
            'description'  => 'Changes the text of a message, a note or a decision the person wrote, the way Edit in the message menu does: edited is then true, and only the people and applications the new text mentions for the first time are told. Only a team devices session (a Bearer token from POST /auth/login) may: an application key and an OAuth connection write as the application and are refused with 403 forbidden, so an integration does not rewrite its owner\'s words. Somebody else\'s message, a decision that records a change to a record, an approval request and a message of an earlier version of the conversation cannot be changed. PATCH is accepted beside POST.',
            'params'       => array(
                array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
                array('name' => 'text', 'in' => 'body', 'type' => 'string', 'max_length' => 4000, 'required' => true, 'description' => 'The whole new text. Tags are written as tokens, as when writing.'),
            ),
        ),

        array(
            'id'          => 'workspace.messages.delete',
            'method'      => 'DELETE',
            'path'        => '/workspace/messages/{id}',
            'scope'       => 'workspace:write',
            'handler'     => 'ws_api_messages_delete',
            'dry_run'     => true,
            'mcp'         => false,
            'returns'     => 'WorkspaceMessage',
            'summary'     => 'Delete a message for everyone',
            'description' => 'Deletes a message the way Delete in the message menu does: the person their own, staff anybody\'s or a guest\'s. Its text, file and tags go, and the channel shows "message deleted" in its place, so the answer has deleted true. It cannot be undone. Only a team devices session (a Bearer token from POST /auth/login) may: an application key and an OAuth connection are refused with 403 forbidden. A message of an application, or of an earlier version of the conversation, cannot be deleted. POST /workspace/messages/{id}/delete does the same for servers that refuse DELETE.',
            'params'      => array(
                array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
            ),
        ),

        array(
            'id'          => 'workspace.messages.delete_post',
            'method'      => 'POST',
            'path'        => '/workspace/messages/{id}/delete',
            'scope'       => 'workspace:write',
            'handler'     => 'ws_api_messages_delete',
            'dry_run'     => true,
            'mcp'         => false,
            'returns'     => 'WorkspaceMessage',
            'summary'     => 'Delete a message, addressed as a POST',
            'description' => 'The same deletion as DELETE /workspace/messages/{id}, for clients and servers that cannot send DELETE. It has an address of its own because POST /workspace/messages/{id} is the change.',
            'params'      => array(
                array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
            ),
        ),

        array(
            'id'          => 'workspace.messages.hide',
            'method'      => 'POST',
            'path'        => '/workspace/messages/{id}/hide',
            'scope'       => 'workspace:write',
            'handler'     => 'ws_api_messages_hide',
            'dry_run'     => true,
            'mcp'         => false,
            'returns'     => 'WorkspaceMessage',
            'summary'     => 'Delete a message for oneself',
            'description' => 'Takes a message out of the person\'s own view, the way Delete for me in the message menu does: the others still see it as it is. Any message the person can read may go, whoever wrote it, but a line the workspace wrote itself; a mention in it leaves the bell. The answer is the message with hidden true, and every later read gives it with hidden true so the app leaves it out while since_id and before_id still count it. The person\'s other devices learn it as a change from the moment it went: changed_since on GET /workspace/channels/{id}/messages and changed in GET /workspace/sync bring it with hidden true, to that person alone; nobody else\'s device is told anything. It cannot be undone. Only a team devices session (a Bearer token from POST /auth/login) may: an application key and an OAuth connection are refused with 403 forbidden.',
            'params'      => array(
                array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
            ),
        ),

        // The pin bar of the channel screen, for the person's own app; a
        // model has no pin bar to draw, and its connection is refused anyway.
        array(
            'id'          => 'workspace.messages.pin',
            'method'      => 'POST',
            'path'        => '/workspace/messages/{id}/pin',
            'scope'       => 'workspace:write',
            'handler'     => 'ws_api_messages_pin',
            'dry_run'     => true,
            'mcp'         => false,
            'returns'     => 'WorkspaceSyncChannelState',
            'summary'     => 'Pin a message to the top of its channel, or take the pin off',
            'description' => 'Pins a message, a note, a decision or a task card to the top of its channel, the way Pin to the top in the message menu does: it takes the place of the message pinned there, and the channel says who pinned it. on false takes the pin off, the way Take the pin off does. Pinning the message already pinned, or taking the pin off a message that is not the one pinned, changes nothing and writes nothing in the channel, so a retried call is safe and a newer pin the app has not seen yet stays. Whoever may write in the channel may pin; an archived channel is refused with 403, and a discussion, which has no pinned message, with 422. Only a team devices session (a Bearer token from POST /auth/login) may, since the channel names the person: an application key and an OAuth connection are refused with 403 forbidden. The answer is the channel\'s state as GET /workspace/sync gives it in channel_state; the panel\'s screens that have the channel open show the change on their next look.',
            'params'      => array(
                array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
                array('name' => 'on', 'in' => 'body', 'type' => 'bool', 'default' => true, 'description' => 'true (the default) pins the message, false takes the pin off.'),
            ),
        ),

        array(
            'id'          => 'workspace.messages.pin_close',
            'method'      => 'POST',
            'path'        => '/workspace/messages/{id}/pin/close',
            'scope'       => 'workspace:write',
            'handler'     => 'ws_api_messages_pin_close',
            'dry_run'     => true,
            'mcp'         => false,
            'returns'     => 'WorkspaceSyncChannelState',
            'summary'     => 'Close the pinned message for oneself',
            'description' => 'Closes the message pinned to the top of the channel for the person, the way the cross on the pin bar does: channel_state.pin.hidden is then true, on the panel too, until another message is pinned. The others still see it. id is the pinned message; for any other message nothing changes, so a newer pin stays open. Only the people in a channel keep it closed (409 not_member otherwise; close it on the app\'s own screen instead). Only a team devices session (a Bearer token from POST /auth/login) may: an application key and an OAuth connection are refused with 403 forbidden. The answer is the channel\'s state as GET /workspace/sync gives it in channel_state.',
            'params'      => array(
                array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
            ),
        ),

        array(
            'id'          => 'workspace.channels.read',
            'method'      => 'POST',
            'path'        => '/workspace/channels/{id}/read',
            'scope'       => 'workspace:write',
            'handler'     => 'ws_api_channels_read',
            'dry_run'     => true,
            'returns'     => 'WorkspaceChannel',
            'summary'     => 'Mark a channel read',
            'description' => 'Marks a channel read up to a message, the way opening it in the panel does: unread counts only what came after it, and the person\'s mentions up to there leave the bell. Without message_id, up to the newest message. A mark never moves back. Only a device session may - a team devices session (a Bearer token from POST /auth/login) or an OAuth connection: an application key is refused with 403 forbidden, since an integration reading a channel is not its owner reading it. Only the people in the channel keep a mark there; anybody else is answered with 409 not_member. id may also be a discussion\'s the person is in.',
            'params'      => array(
                array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
                array('name' => 'message_id', 'in' => 'body', 'type' => 'int', 'min' => 1, 'description' => 'A message of this channel. Left out, the newest one.'),
            ),
        ),

        array(
            'id'          => 'workspace.channels.notify',
            'method'      => 'POST',
            'path'        => '/workspace/channels/{id}/notify',
            'scope'       => 'workspace:write',
            'handler'     => 'ws_api_channels_notify',
            'dry_run'     => true,
            'mcp'         => false,
            'returns'     => 'WorkspaceChannel',
            'summary'     => 'Choose how a channel notifies the person',
            'description' => 'Sets what the bell in the channel menu sets, for the person only. all (the default) and mentions are the menu\'s Tell me about every message and Only when I am mentioned; none (Never) mutes the channel: a mention there reaches neither the bell nor the person\'s devices, and the panel shows no unread count for it. notify on the channel says what is set. Choosing it in a public channel the person is not in joins them to it, as the panel does; in a private channel or a discussion they are not in it is answered with 409 not_member. Only a device session may - a team devices session (a Bearer token from POST /auth/login) or an OAuth connection: an application key is refused with 403 forbidden, since how a channel calls for a person is the person\'s own choice. id may also be a discussion\'s the person is in.',
            'params'      => array(
                array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
                array('name' => 'notify', 'in' => 'body', 'type' => 'enum', 'values' => array('all', 'mentions', 'none'), 'required' => true),
            ),
        ),

        array(
            'id'          => 'workspace.channels.prefs',
            'method'      => 'POST',
            'path'        => '/workspace/channels/{id}/prefs',
            'scope'       => 'workspace:write',
            'handler'     => 'ws_api_channels_prefs',
            'dry_run'     => true,
            'mcp'         => false,
            'returns'     => 'WorkspaceChannel',
            'summary'     => 'Pin a channel or move it in the person\'s list',
            'description' => 'Sets the person\'s own place for a channel in their list, as the panel\'s sidebar keeps it; only the fields sent change. pinned keeps the channel at the top of the list, as the pin beside its name does, and false takes the pin off. order moves it to that place among the channels the person is in (1 is the top), as dragging it does: the ones from that place on move one down. Pinned channels come first, so a pinned channel takes a place among the pinned ones and any other one a place after them; a place past either end is taken as the nearest it can be. Each call numbers the person\'s channels again as the list shows them, so order on every channel is its place in GET /workspace/channels, which lists them in that order (null on a channel the person is not in, and on all of them until the list is first numbered, by a call here or a drag on the panel). Either field in a public channel the person is not in joins them to it, as the panel does; in a private channel they are not in it is answered with 409 not_member, and a discussion is refused with 422. Only a device session may - a team devices session (a Bearer token from POST /auth/login) or an OAuth connection: an application key is refused with 403 forbidden, since the list is the person\'s own.',
            'params'      => array(
                array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
                array('name' => 'pinned', 'in' => 'body', 'type' => 'bool', 'description' => 'Keep the channel at the top of the person\'s list; false takes the pin off.'),
                array('name' => 'order', 'in' => 'body', 'type' => 'int', 'min' => 1, 'max' => 100000, 'description' => 'The place among the channels the person is in, 1 for the top.'),
            ),
        ),

        array(
            'id'          => 'workspace.channel_groups.list',
            'method'      => 'GET',
            'path'        => '/workspace/channel-groups',
            'scope'       => 'workspace:read',
            'handler'     => 'ws_api_channel_groups_list',
            'returns'     => array('list' => 'WorkspaceChannelGroup'),
            'summary'     => 'List the groups of channels',
            'description' => 'The groups the sidebar sorts channels into. They are the same for everybody and only staff arrange them; whoever holds access on a group becomes a member of every channel in it and in the groups inside it. Staff see every group, with access counting the people who hold access on it; anybody else sees only the groups that hold a channel they see and the groups those are in, and access is null for them. parent_id builds the tree (null at the top) and path names a group with the ones above it, as "Customers › Istanbul". mine is true on a group that gave the owner of the application access themselves. group_id on a channel says which group it is in; POST /workspace/channels/{id}/group moves it. Staff create, change and take away groups with POST /workspace/channel-groups, POST /workspace/channel-groups/{id} and DELETE /workspace/channel-groups/{id}, and give access with /workspace/channel-groups/{id}/access.',
            'params'      => array(),
        ),

        array(
            'id'          => 'workspace.channel_groups.create',
            'method'      => 'POST',
            'path'        => '/workspace/channel-groups',
            'scope'       => 'workspace:write',
            'handler'     => 'ws_api_channel_groups_create',
            'dry_run'     => true,
            'returns'     => 'WorkspaceChannelGroup',
            'status'      => 201,
            'summary'     => 'Create a group of channels',
            'description' => 'Adds a group to the channel list the way New group in the sidebar does: a heading with a name and a colour, at the top level or inside another group (parent_id), last among the groups there. Groups are the same for everybody and only staff arrange them, so the owner of the application must be staff; anybody else is refused with 403. Groups go at most ' . WS_GROUP_DEPTH . ' levels deep (422 on parent_id). A new group is empty: POST /workspace/channels/{id}/group moves a channel into it and POST /workspace/channel-groups/{id}/access gives people access on it. The activity log gets the line the panel writes. Send an Idempotency-Key so a retried call does not create two groups.',
            'params'      => array(
                array('name' => 'name', 'in' => 'body', 'type' => 'string', 'max_length' => 80, 'required' => true, 'description' => 'Runs of spaces are made one.'),
                array('name' => 'color', 'in' => 'body', 'type' => 'enum', 'values' => ws_palette_hexes(), 'nullable' => true, 'description' => 'One of the palette\'s colours, as color on a group shows it; null or empty for none.'),
                array('name' => 'parent_id', 'in' => 'body', 'type' => 'int', 'min' => 0, 'nullable' => true, 'description' => 'The group it goes inside (GET /workspace/channel-groups); 0 or null for the top level.'),
            ),
        ),

        array(
            'id'           => 'workspace.channel_groups.update',
            'method'       => 'POST',
            'also_accepts' => array('PATCH'),
            'path'         => '/workspace/channel-groups/{id}',
            'scope'        => 'workspace:write',
            'handler'      => 'ws_api_channel_groups_update',
            'dry_run'      => true,
            'returns'      => 'WorkspaceChannelGroupChange',
            'summary'      => 'Change a group of channels',
            'description'  => 'Changes only the fields sent, the way Change the group, Move up, Move down and dragging a group in the sidebar do; staff alone may (403 otherwise). parent_id moves the group with everything in it inside another group, or to the top level with 0 or null, last there unless before_id says where; before_id puts it in front of another group of the same level, or last with 0; any other before_id - a group removed or moved meanwhile, or the group itself - is refused with 422 on before_id and nothing changes, as the panel refuses a drag in a list that changed meanwhile. A group cannot go inside itself or one of its own groups, nor deeper than ' . WS_GROUP_DEPTH . ' levels (422 on parent_id). A move changes who is in its channels: whoever holds access on a group above its new place comes into every channel in it and in the groups inside it, the private ones too, and the people only its old place had brought in leave. memberships names them channel by channel, in the answer and in a dry run (X-Dry-Run: true): ask the dry run first to show the question before the move. A channel the owner of the application does not read is only counted. A new name or colour writes the panel\'s line in the activity log. PATCH is accepted beside POST.',
            'params'       => array(
                array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
                array('name' => 'name', 'in' => 'body', 'type' => 'string', 'max_length' => 80),
                array('name' => 'color', 'in' => 'body', 'type' => 'enum', 'values' => ws_palette_hexes(), 'nullable' => true, 'description' => 'One of the palette\'s colours; null or empty for none.'),
                array('name' => 'parent_id', 'in' => 'body', 'type' => 'int', 'min' => 0, 'nullable' => true, 'description' => 'The group it goes inside; 0 or null for the top level.'),
                array('name' => 'before_id', 'in' => 'body', 'type' => 'int', 'min' => 0, 'nullable' => true, 'description' => 'Another group of the level it ends up on, which it goes in front of; 0 or null puts it last. Any other value is refused (422).'),
            ),
        ),

        array(
            'id'          => 'workspace.channel_groups.delete',
            'method'      => 'DELETE',
            'path'        => '/workspace/channel-groups/{id}',
            'scope'       => 'workspace:write',
            'handler'     => 'ws_api_channel_groups_delete',
            'dry_run'     => true,
            'returns'     => 'WorkspaceChannelGroupRemoval',
            'summary'     => 'Take a group of channels away',
            'description' => 'Takes a group away the way Take the group away in its menu does; staff alone may (403 otherwise). No channel is deleted: the channels and the groups in it move up to the group it was in (parent_id, null for the top level), and channels and groups list them. The access given on it ends (access names whose), and with it the memberships only that access had given: memberships names who leaves which channel. Ask a dry run (X-Dry-Run: true) first to show all of it before anything changes. A channel the owner of the application does not read is only counted (unseen_channels). The activity log gets the line the panel writes. It cannot be undone. POST /workspace/channel-groups/{id}/delete does the same for servers that refuse DELETE.',
            'params'      => array(
                array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
            ),
        ),

        array(
            'id'          => 'workspace.channel_groups.delete_post',
            'method'      => 'POST',
            'path'        => '/workspace/channel-groups/{id}/delete',
            'scope'       => 'workspace:write',
            'handler'     => 'ws_api_channel_groups_delete',
            'dry_run'     => true,
            'returns'     => 'WorkspaceChannelGroupRemoval',
            'summary'     => 'Take a group of channels away, addressed as a POST',
            'description' => 'The same as DELETE /workspace/channel-groups/{id}, for clients and servers that cannot send DELETE. It has an address of its own because POST /workspace/channel-groups/{id} is the change.',
            'params'      => array(
                array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
            ),
        ),

        array(
            'id'          => 'workspace.channel_groups.access',
            'method'      => 'GET',
            'path'        => '/workspace/channel-groups/{id}/access',
            'scope'       => 'workspace:read',
            'handler'     => 'ws_api_channel_groups_access',
            'returns'     => 'WorkspaceChannelGroupAccess',
            'summary'     => 'Who holds access on a group of channels',
            'description' => 'The people who hold access on the group itself and who gave it to them, as Access on the group in its menu lists them; above lists the people whose access on a group further up reaches here too. Whoever holds access on a group is a member of every channel in it and in the groups inside it, the private ones too, and of the channels moved into them later. reach counts what that is: the groups inside it, and the channels with the private and the archived ones among them. Staff alone may ask (403 otherwise).',
            'params'      => array(
                array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
            ),
        ),

        array(
            'id'          => 'workspace.channel_groups.access.grant',
            'method'      => 'POST',
            'path'        => '/workspace/channel-groups/{id}/access',
            'scope'       => 'workspace:write',
            'handler'     => 'ws_api_channel_groups_access_grant',
            'dry_run'     => true,
            'returns'     => 'WorkspaceChannelGroupAccessChange',
            'summary'     => 'Give people access on a group of channels',
            'description' => 'Gives these colleagues access on the group the way Give access does; staff alone may (403 otherwise). They become members of every channel in it and in the groups inside it, the private ones too, and of the channels moved into them later; each channel gets a line naming who came in. Ids of people who are not in the team are left out (422 when nobody is left), and somebody who already holds the access keeps it as it was. memberships names who comes into which channel, in the answer and in a dry run (X-Dry-Run: true): ask the dry run first to show the question the panel asks. A channel the owner of the application does not read is only counted. The activity log gets the line the panel writes.',
            'params'      => array(
                array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
                array('name' => 'user_ids', 'in' => 'body', 'type' => 'list', 'of' => 'integer', 'max_items' => 100, 'required' => true, 'description' => 'User ids of the colleagues who get the access.'),
            ),
        ),

        array(
            'id'           => 'workspace.channel_groups.access.revoke',
            'method'       => 'DELETE',
            'also_accepts' => array('POST'),
            'path'         => '/workspace/channel-groups/{id}/access/{user_id}',
            'scope'        => 'workspace:write',
            'handler'      => 'ws_api_channel_groups_access_revoke',
            'dry_run'      => true,
            'returns'      => 'WorkspaceChannelGroupAccessChange',
            'summary'      => 'Take back somebody\'s access on a group of channels',
            'description'  => 'Takes the access on the group back from one person the way Take the access back does; staff alone may (403 otherwise). They leave the channels only that access had brought them into: a channel they joined by themselves, one they own and one a group still brings them into keeps them. Each channel they leave gets a line saying so. Access given on a group further up is taken back there, not here. Somebody who did not hold access on the group is not an error. memberships names who leaves which channel, in the answer and in a dry run (X-Dry-Run: true). The activity log gets the line the panel writes. POST is accepted as well, because a default IIS install answers DELETE itself before PHP is reached.',
            'params'       => array(
                array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
                array('name' => 'user_id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
            ),
        ),

        array(
            'id'          => 'workspace.channels.group',
            'method'      => 'POST',
            'path'        => '/workspace/channels/{id}/group',
            'scope'       => 'workspace:write',
            'handler'     => 'ws_api_channels_group',
            'dry_run' => true,
            'returns'     => 'WorkspaceChannelGroupMove',
            'summary'     => 'Move a channel into a group',
            'description' => 'Moves a public or private channel into a group of channels (GET /workspace/channel-groups), or out of every group with group_id 0 or null, the way dragging it onto a group or Move to a group in its menu does. A group is the same for everybody, so this is staff work: the owner of the application must be staff who may change the channel, and of a private channel only while they are in it; anybody else is refused with 403. Whoever holds access on the new group or on a group above it becomes a member of the channel (of a private one too, so they read it from then on), and the people only the old group had brought in leave it; the owner of the channel and the people who joined it themselves stay. joining and leaving name them, in the answer and in a dry run (X-Dry-Run: true): ask the dry run first to show the question the panel asks before the move. The channel gets a line naming who came in and who left. Moving a channel to the group it is in changes nothing, and changes is then false. A discussion or a room with a guest in it is refused with 422.',
            'params'      => array(
                array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
                array('name' => 'group_id', 'in' => 'body', 'type' => 'int', 'min' => 0, 'required' => true, 'nullable' => true, 'description' => 'A group from GET /workspace/channel-groups; 0 or null takes the channel out of its group.'),
            ),
        ),

        array(
            'id'          => 'workspace.messages.ack_request',
            'method'      => 'POST',
            'path'        => '/workspace/messages/{id}/ack-request',
            'scope'       => 'workspace:write',
            'handler'     => 'ws_api_messages_ack_request',
            'dry_run'     => true,
            'returns'     => 'WorkspaceMessage',
            'summary'     => 'Ask for read receipts on a message, or stop asking',
            'description' => 'Asks the members of the message\'s channel to say they read it, the way the message menu does: they see a button under it, and those who have not answered a day later are reminded once. Only the person who wrote the message, or an owner who is staff, may ask - a message written by an application is asked about by staff only - and only while they may write in the channel; anybody else is refused with 403. on false stops asking and drops the answers given. ack on the message says how many have read it.',
            'params'      => array(
                array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
                array('name' => 'on', 'in' => 'body', 'type' => 'bool', 'default' => true, 'description' => 'true (the default) asks for read receipts, false stops asking.'),
            ),
        ),

        array(
            'id'          => 'workspace.threads.start',
            'method'      => 'POST',
            'path'        => '/workspace/messages/{id}/thread',
            'scope'       => 'workspace:write',
            'handler'     => 'ws_api_thread_start',
            'dry_run'     => true,
            'returns'     => 'WorkspaceThread',
            'status'      => array(201, 200),
            'summary'     => 'Start a discussion about a message',
            'description' => 'Opens a room of its own to talk over one message of a public or private channel the owner of the application may write in - a message, a decision, a note or a task card - the way the message menu does. The owner and the person who wrote the message are in it; staff who read the channel may join it in the panel. A message has one discussion: when it already has one, that one is answered with 200 instead of a second being made, and a new one is answered with 201. Its id is then read and written with GET and POST /workspace/channels/{id}/messages. A decision, a note or a task card written in it is copied into the channel. Concluded discussions are kept, read only, for 30 days and then deleted.',
            'params'      => array(
                array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
                array('name' => 'title', 'in' => 'body', 'type' => 'string', 'max_length' => 80, 'description' => 'Left out, the first words of the message.'),
            ),
        ),

        array(
            'id'          => 'workspace.threads.list',
            'method'      => 'GET',
            'path'        => '/workspace/channels/{id}/threads',
            'scope'       => 'workspace:read',
            'handler'     => 'ws_api_threads_list',
            'returns'     => array('list' => 'WorkspaceThread'),
            'summary'     => 'List the discussions of a channel',
            'description' => 'The discussions started from messages of a channel that the owner of the application may read - the ones they are in - the latest written in first: who is in them (the first eight), how many messages they hold, what the owner has not read and whether they were concluded. GET /workspace/channels/{id}/messages with a discussion\'s id reads it; a message of the channel says in its thread field that a discussion was started from it, also when the owner is not in it.',
            'params'      => array(
                array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
                array('name' => 'state', 'in' => 'query', 'type' => 'enum', 'values' => array('open', 'closed', 'all'), 'default' => 'all', 'description' => 'closed keeps the concluded ones.'),
                array('name' => 'limit', 'in' => 'query', 'type' => 'int', 'min' => 1, 'max' => 100, 'default' => 50),
            ),
        ),

        array(
            'id'          => 'workspace.threads.join',
            'method'      => 'POST',
            'path'        => '/workspace/threads/{id}/join',
            'scope'       => 'workspace:write',
            'handler'     => 'ws_api_threads_join',
            'dry_run'     => true,
            'mcp'         => false,
            'returns'     => 'WorkspaceThread',
            'summary'     => 'Join a discussion',
            'description' => 'Brings the person into a discussion they are not in, the way Join on the bar under its message does; id is thread.id of the message. Staff who read the channel may join while the discussion and its channel are open (can_join on the discussion says so); the User role is refused with 403 forbidden, and a concluded discussion or an archived channel with 422. The discussion gets a line saying who joined, and from then on it is read and written with GET and POST /workspace/channels/{id}/messages. Somebody already in it is answered with the discussion and nothing changes. Only a device session may - a team devices session (a Bearer token from POST /auth/login) or an OAuth connection: an application key is refused with 403 forbidden.',
            'params'      => array(
                array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
            ),
        ),

        array(
            'id'          => 'workspace.threads.leave',
            'method'      => 'POST',
            'path'        => '/workspace/threads/{id}/leave',
            'scope'       => 'workspace:write',
            'handler'     => 'ws_api_threads_leave',
            'dry_run'     => true,
            'mcp'         => false,
            'returns'     => 'WorkspaceThread',
            'summary'     => 'Leave a discussion',
            'description' => 'Takes the person out of a discussion, the way Leave in its menu does: they no longer read it, and it gets a line saying who left. The last one to leave concludes it, as if they had concluded it: it is kept, read only, for 30 days and then deleted. Somebody who was not in it is not an error: the answer is the discussion with joined false either way. Only a device session may - a team devices session (a Bearer token from POST /auth/login) or an OAuth connection: an application key is refused with 403 forbidden.',
            'params'      => array(
                array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
            ),
        ),

        array(
            'id'          => 'workspace.scheduled_messages.list',
            'method'      => 'GET',
            'path'        => '/workspace/channels/{id}/scheduled-messages',
            'scope'       => 'workspace:read',
            'handler'     => 'ws_api_scheduled_messages_list',
            'returns'     => array('list' => 'WorkspaceScheduledMessage'),
            'summary'     => 'The owner\'s scheduled messages in a channel',
            'description' => 'The messages the owner of the application wrote to be posted later in a channel, the next one first: nobody else sees them until they are posted, administrators included. status active waits for send_at; failed could not be posted when its time came (the owner left the channel, the channel was archived) and error says why - it stays until it is deleted. A message that was posted is no longer listed.',
            'params'      => array(
                array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
            ),
        ),

        // Posted later in the person's own name, so scheduled from their team
        // devices session only; an MCP client's OAuth connection is refused,
        // and is offered no tool for it. Listing and deleting stay tools.
        array(
            'id'          => 'workspace.scheduled_messages.create',
            'method'      => 'POST',
            'path'        => '/workspace/channels/{id}/scheduled-messages',
            'scope'       => 'workspace:write',
            'handler'     => 'ws_api_scheduled_messages_create',
            'dry_run'     => true,
            'mcp'         => false,
            'returns'     => 'WorkspaceScheduledMessage',
            'status'      => 201,
            'summary'     => 'Schedule a message',
            'description' => 'Writes a message to be posted later in a channel the person may post in, the way the clock beside the send button does. It is posted in the person\'s own name when its time comes, with their rights as they are then, so only a team devices session (a Bearer token from POST /auth/login) may schedule one: an application key and an OAuth connection write as the application and are refused with 403 forbidden. send_at is read to the minute on the site\'s clock and must be later than now and at most a year ahead. A command (text starting with /) cannot be scheduled, except /ai and /claude, which ask the assistant when the message is posted. Fifty messages may wait per person.',
            'params'      => array(
                array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
                array('name' => 'text', 'in' => 'body', 'type' => 'string', 'max_length' => 4000, 'required' => true, 'description' => 'Tags such as <#order:1045> or <@user:12> are kept as tags.'),
                array('name' => 'send_at', 'in' => 'body', 'type' => 'datetime', 'required' => true, 'description' => 'When to post it, as an ISO-8601 moment; seconds are dropped.'),
            ),
        ),

        array(
            'id'           => 'workspace.scheduled_messages.delete',
            'method'       => 'DELETE',
            'also_accepts' => array('POST'),
            'path'         => '/workspace/scheduled-messages/{id}',
            'scope'        => 'workspace:write',
            'handler'      => 'ws_api_scheduled_messages_delete',
            'dry_run'      => true,
            'returns'      => 'WorkspaceScheduledMessage',
            'summary'      => 'Delete a scheduled message',
            'description'  => 'Deletes one of the owner\'s scheduled messages before it is posted, waiting or failed, and answers with it as it was. Somebody else\'s, or one already posted, is answered with 404; one being posted at this very moment is refused with 422. POST is accepted as well, because a default IIS install answers DELETE itself before PHP is reached.',
            'params'       => array(
                array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
            ),
        ),

        array(
            'id'          => 'workspace.messages.react',
            'method'      => 'POST',
            'path'        => '/workspace/messages/{id}/reactions',
            'scope'       => 'workspace:write',
            'handler'     => 'ws_api_messages_react',
            'dry_run'     => true,
            'returns'     => 'WorkspaceMessage',
            'summary'     => 'Leave an emoji on a message',
            'description' => 'Leaves an emoji on a message in a channel the owner may post in, as the application with an application key or an OAuth connection, or as the person from a team devices session (POST /auth/login) - for instance an eye when an integration has seen a request and a tick when it is done. on false takes it back. Readers are not notified. A dry run\'s detail.changes is false when the emoji already is there, or with on false is not.',
            'params'      => array(
                array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
                array('name' => 'emoji', 'in' => 'body', 'type' => 'string', 'max_length' => 32, 'required' => true),
                array('name' => 'on', 'in' => 'body', 'type' => 'bool', 'description' => 'true (the default) leaves the emoji, false takes it back.'),
            ),
        ),

        array(
            'id'          => 'workspace.messages.ack',
            'method'      => 'POST',
            'path'        => '/workspace/messages/{id}/ack',
            'scope'       => 'workspace:write',
            'handler'     => 'ws_api_messages_ack',
            'dry_run'     => true,
            'returns'     => 'WorkspaceMessage',
            'summary'     => 'Say a message was read',
            'description' => 'Gives the read receipt a message asks for, in the name of the person signed in. Only a device session may - a team devices session (a Bearer token from POST /auth/login) or an OAuth connection: an application key is refused with 403 forbidden, since an integration saying its owner read something would be a receipt nobody gave. The person has to be in the message\'s channel and not its writer. ack on the message says how many of the channel\'s members have read it. A dry run\'s detail.changes is false when the person already said so.',
            'params'      => array(
                array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
            ),
        ),

        array(
            'id'          => 'workspace.polls.vote',
            'method'      => 'POST',
            'path'        => '/workspace/polls/{id}/vote',
            'scope'       => 'workspace:write',
            'handler'     => 'ws_api_polls_vote',
            'dry_run'     => true,
            'mcp'         => false,
            'returns'     => 'WorkspaceMessage',
            'summary'     => 'Vote in a poll',
            'description' => 'Casts the person\'s vote in a poll, the way the poll in a channel does: id is poll.id of the message that asks it, and option_ids are ids from its poll.options. The options sent replace the ones the person chose before, and an empty list takes the vote back. A poll that is not multiple takes one option (422 otherwise). A closed poll, a poll whose closes_at has passed and a poll of an earlier version of the conversation are refused with 422; somebody who may only read the channel with 403. The answer is the message with its poll counted again. Only a device session may - a team devices session (a Bearer token from POST /auth/login) or an OAuth connection: an application key is refused with 403 forbidden, since a vote is the person\'s own answer.',
            'params'      => array(
                array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
                array('name' => 'option_ids', 'in' => 'body', 'type' => 'list', 'of' => 'integer', 'max_items' => 50, 'required' => true, 'description' => 'Ids from poll.options of the message. An empty list takes the vote back.'),
            ),
        ),

        array(
            'id'          => 'workspace.polls.create',
            'method'      => 'POST',
            'path'        => '/workspace/channels/{id}/polls',
            'scope'       => 'workspace:write',
            'handler'     => 'ws_api_polls_create',
            'dry_run'     => true,
            'mcp'         => false,
            'returns'     => 'WorkspaceMessage',
            'status'      => 201,
            'summary'     => 'Ask a poll in a channel',
            'description' => 'Asks the channel a question with options to choose from, the way Poll by the composer and the /poll command do. The question is a message of the person\'s, written as from the panel: it carries their name, counts against their limit of twenty messages a minute (422 beyond it) and joins them to a public channel they were not in; the people it mentions are told. Options are two to ten different texts (blank and repeated ones are left out). multiple lets a voter choose several options; anonymous keeps who chose what from everybody. closes_at closes it by itself and writes the result into the channel; left out, it stays open until it is closed with POST /workspace/polls/{id}/close. The answer is the message, with its poll (201). id may also be a discussion\'s the person is in. Only a team devices session (a Bearer token from POST /auth/login) may: an application key and an OAuth connection write as the application and are refused with 403 forbidden, since a poll is asked in the person\'s own name. Send an Idempotency-Key so a retried call does not ask twice.',
            'params'      => array(
                array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
                array('name' => 'question', 'in' => 'body', 'type' => 'string', 'max_length' => 4000, 'required' => true, 'description' => 'The question. Tags are written as tokens, as when writing.'),
                array('name' => 'options', 'in' => 'body', 'type' => 'list', 'of' => 'string', 'max_items' => 10, 'required' => true, 'description' => 'The texts to choose from, two to ten, each up to 255 characters, in the order shown.'),
                array('name' => 'multiple', 'in' => 'body', 'type' => 'bool', 'description' => 'A voter may choose several options. Left out, one.'),
                array('name' => 'anonymous', 'in' => 'body', 'type' => 'bool', 'description' => 'Nobody sees who chose what, only the counts.'),
                array('name' => 'closes_at', 'in' => 'body', 'type' => 'datetime', 'description' => 'When it closes by itself, at least a minute from now. Left out, it stays open until it is closed.'),
            ),
        ),

        array(
            'id'           => 'workspace.polls.update',
            'method'       => 'POST',
            'also_accepts' => array('PATCH'),
            'path'         => '/workspace/polls/{id}',
            'scope'        => 'workspace:write',
            'handler'      => 'ws_api_polls_update',
            'dry_run'      => true,
            'mcp'          => false,
            'returns'      => 'WorkspaceMessage',
            'summary'      => 'Change a poll',
            'description'  => 'Changes an open poll the person asked, the way Edit the poll on it does; poll.can_edit says whether they may. A field left out stays as it is. options, when sent, is the whole new list: an item with the id of one of poll.options keeps that option (and its votes) under the label sent, an item without an id adds an option, and an option left out is removed. Once somebody has voted, an option with votes has to stay with its words unchanged, and multiple and anonymous no longer change (422): a secret vote does not turn public, and a second answer is not taken away. New options can still be added, the question worded better and the closing moment moved; closes_at null leaves it open until it is closed. Somebody else\'s poll is refused with 403, a closed poll and one of an earlier version of the conversation with 422. The answer is the message with its poll. Only a team devices session (a Bearer token from POST /auth/login) may: an application key and an OAuth connection are refused with 403 forbidden, so an integration does not rewrite its owner\'s words. PATCH is accepted beside POST.',
            'params'       => array(
                array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
                array('name' => 'question', 'in' => 'body', 'type' => 'string', 'max_length' => 4000, 'description' => 'The whole new question. Tags are written as tokens, as when writing.'),
                array('name' => 'options', 'in' => 'body', 'type' => 'list', 'of' => array('id' => 'integer', 'label' => 'string'), 'max_items' => 10, 'description' => 'Objects of {id, label}, two to ten, in the order shown: id from poll.options keeps that option, no id adds one.'),
                array('name' => 'multiple', 'in' => 'body', 'type' => 'bool'),
                array('name' => 'anonymous', 'in' => 'body', 'type' => 'bool'),
                array('name' => 'closes_at', 'in' => 'body', 'type' => 'datetime', 'nullable' => true, 'description' => 'When it closes by itself, at least a minute from now. null leaves it open until it is closed.'),
            ),
        ),

        array(
            'id'          => 'workspace.polls.close',
            'method'      => 'POST',
            'path'        => '/workspace/polls/{id}/close',
            'scope'       => 'workspace:write',
            'handler'     => 'ws_api_polls_close',
            'dry_run'     => true,
            'mcp'         => false,
            'returns'     => 'WorkspaceMessage',
            'summary'     => 'Close a poll',
            'description' => 'Closes a poll, the way Close the poll on it does: the person who asked closes their own, and whoever runs the channel any (poll.can_close says whether the person may; 403 otherwise). The result is written into the channel: a clear winner as a decision in the name of the person who asked, so it lands on the Decisions tab, and a tie or a poll nobody voted in as a line of the workspace\'s. The answer is the message with its poll, now closed, and poll.result_message_id the result. A poll already closed is not an error: the answer is the same. A poll of an earlier version of the conversation is refused with 422. Only a device session may - a team devices session (a Bearer token from POST /auth/login) or an OAuth connection: an application key is refused with 403 forbidden.',
            'params'      => array(
                array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
            ),
        ),

        array(
            'id'          => 'workspace.approvals.list',
            'method'      => 'GET',
            'path'        => '/workspace/channels/{id}/approvals',
            'scope'       => 'workspace:read',
            'handler'     => 'ws_api_approvals_list',
            'returns'     => array('list' => 'WorkspaceApproval'),
            'summary'     => 'List the approval requests of a channel',
            'description' => 'The approval requests of a channel the owner of the application may read, newest first: the people asked and each one\'s answer, the rule, the deadline and, once settled, the outcome and the decision it wrote into the channel.',
            'params'      => array(
                array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
                array('name' => 'state', 'in' => 'query', 'type' => 'enum', 'values' => array('open', 'closed', 'all'), 'default' => 'all'),
                array('name' => 'limit', 'in' => 'query', 'type' => 'int', 'min' => 1, 'max' => 100, 'default' => 50),
            ),
        ),

        array(
            'id'          => 'workspace.approvals.create',
            'method'      => 'POST',
            'path'        => '/workspace/channels/{id}/approvals',
            'scope'       => 'workspace:write',
            'handler'     => 'ws_api_approvals_create',
            'dry_run'     => true,
            'returns'     => 'WorkspaceApproval',
            'status'      => 201,
            'summary'     => 'Ask for approval in a channel',
            'description' => 'Writes an approval request into a channel the owner may post in; readers see it as written by the application with an application key or an OAuth connection, or by the person from a team devices session (POST /auth/login), and the people asked are told. approvers have to be members of the channel who may write in it, up to ten. guests are people outside the team - a customer - asked by e-mail: each gets a link of their own and answers with no account; only staff (roles 0-2) may name them, up to five, and with them approvers may be left out. rule any is met by the first approval, all by everybody\'s; a refusal settles it at once and a deadline that passes lets it expire. The result is written into the channel as a locked decision, and onto the task when the request is about one. Send an Idempotency-Key so a retried call does not ask twice.',
            'params'      => array(
                array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
                array('name' => 'title', 'in' => 'body', 'type' => 'string', 'max_length' => 255, 'required' => true, 'description' => 'What is to be approved, in a line.'),
                array('name' => 'text', 'in' => 'body', 'type' => 'string', 'max_length' => 3800, 'description' => 'The details. Tags such as <#invoice:88> are kept; the first record tagged is the record the request is about, or else the first task or plan item tagged.'),
                array('name' => 'approvers', 'in' => 'body', 'type' => 'list', 'of' => 'integer', 'max_items' => 10, 'description' => 'User ids of the people asked. Needed unless guests are named.'),
                array('name' => 'guests', 'in' => 'body', 'type' => 'list', 'of' => array('name' => 'string', 'email' => 'string'), 'max_items' => 5, 'description' => 'Objects of {name, email}: people outside the team asked by e-mail, each with a link of their own. Staff only.'),
                array('name' => 'rule', 'in' => 'body', 'type' => 'enum', 'values' => array('any', 'all'), 'default' => 'any'),
                array('name' => 'closes_at', 'in' => 'body', 'type' => 'datetime', 'description' => 'The deadline. Left out, it waits until it is answered.'),
            ),
        ),

        array(
            'id'          => 'workspace.approvals.decide',
            'method'      => 'POST',
            'path'        => '/workspace/approvals/{id}/decide',
            'scope'       => 'workspace:write',
            'handler'     => 'ws_api_approvals_decide',
            // The person's answer is given on the card: no MCP client gives
            // it in their name.
            'mcp'         => false,
            'dry_run'     => true,
            'returns'     => 'WorkspaceApproval',
            'summary'     => 'Approve or reject a request',
            'description' => 'The answer of the person signed in, who has to be one of the people asked and may answer once. Only a device session may - a team devices session (a Bearer token from POST /auth/login) or an OAuth connection: an application key is refused with 403 forbidden - an integration does not approve in its owner\'s name. A request that is closed, or whose deadline passed, is refused with 422. A dry run\'s detail.outcome says what the request would be after the answer: open, approved or rejected.',
            'params'      => array(
                array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
                array('name' => 'decision', 'in' => 'body', 'type' => 'enum', 'values' => array('approve', 'reject'), 'required' => true),
                array('name' => 'note', 'in' => 'body', 'type' => 'string', 'max_length' => 255, 'description' => 'Why, shown beside the answer; a refusal\'s note goes into the decision.'),
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
            'description' => 'The record changes Claude (agent claude) or Pinegrap AI (agent ai) proposed, newest first: under their answers in the channels the owner of the application may read, and on the tasks the owner may see, where an assistant the task was handed to proposes them (task_id is set, channel_id and message_id are 0). Only the person who asked applies a change, with their own rights: on its card, or with POST /workspace/changes/{id}/apply from a device session. status pending is waiting, applying is being written at this moment (decided_by and decided_at say who took it and when; a change to one record still applying 15 minutes later stopped half way and turns failed, perhaps written in part), applied was written (decision_message_id is the locked decision it left in a channel; a change on a task leaves none), dismissed was set aside, stale was not applied because the record changed after the proposal, failed could not be written (error says why). fields and reason are shown only when the owner may see records of that kind; hidden says when they are not. from_shown and to_shown are the values as the card prints them. can_apply and can_dismiss say what the owner may do with it now. A call of a tool that writes on an MCP server of the Workspace waits the same way (record.type mcp_call, action run, record.id the call, record.name the alias and the tool, fields its arguments); applied, it runs with the asker\'s own connected account on that service. A message to an MCP server that answers on its own (agent mcp) through a tool that changes things there waits the same way before it is sent, its fields the message; applied, it goes to the server with the asker\'s own account, and decision_message_id is the agent\'s answer under the message (none on a task, where the answer is the run\'s). Page with next_cursor.',
            'params'      => array(
                array('name' => 'status', 'in' => 'query', 'type' => 'enum', 'values' => array('pending', 'applying', 'applied', 'dismissed', 'stale', 'failed')),
                array('name' => 'channel_id', 'in' => 'query', 'type' => 'int', 'min' => 1),
                array('name' => 'task_id', 'in' => 'query', 'type' => 'int', 'min' => 1, 'description' => 'Only the changes proposed on this task.'),
                array('name' => 'type', 'in' => 'query', 'type' => 'string', 'max_length' => 32, 'description' => 'A kind of record: product, stock, order, contact, erp_account, product_group, channel, user, page, form, file, offer, calendar_event, task, task_note or note, of the ones this site runs; or mcp_call for the calls of MCP server tools.'),
                array('name' => 'cursor', 'in' => 'query', 'type' => 'string', 'max_length' => 200),
                array('name' => 'limit', 'in' => 'query', 'type' => 'int', 'min' => 1, 'max' => 100, 'default' => 50),
            ),
        ),

        array(
            'id'          => 'workspace.changes.apply',
            'method'      => 'POST',
            'path'        => '/workspace/changes/{id}/apply',
            'scope'       => 'workspace:write',
            'handler'     => 'ws_api_changes_apply',
            // Applying is the person's decision on the card: no MCP client
            // applies a change, its own proposal included.
            'mcp'         => false,
            'dry_run' => true,
            'returns'     => 'WorkspaceChange',
            'summary'     => 'Apply a change an assistant proposed',
            'description' => 'Writes a pending change, as Apply on its card does: only the person who asked the assistant, with their own rights to that record, signed in on a device (a Bearer token from POST /auth/login or a connection they made); an application key is refused with 403 forbidden, so an assistant never applies what it proposed. Applied in a channel, it leaves a locked decision under the answer; on a task, the task\'s activity keeps it. A record that changed after the proposal is not written over: 409 stale. A change somebody already decided about is 409 already_decided. A change to many records at once starts here and goes on a slice at a time; its status stays applying until it is done.',
            'params'      => array(
                array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
            ),
        ),

        array(
            'id'          => 'workspace.changes.dismiss',
            'method'      => 'POST',
            'path'        => '/workspace/changes/{id}/dismiss',
            'scope'       => 'workspace:write',
            'handler'     => 'ws_api_changes_dismiss',
            // Setting a change aside is the person's decision on the card too.
            'mcp'         => false,
            'dry_run' => true,
            'returns'     => 'WorkspaceChange',
            'summary'     => 'Set aside a change an assistant proposed',
            'description' => 'Sets a pending change aside, as on its card: the person who asked, or staff, signed in on a device; an application key is refused with 403 forbidden. Nothing is written to the record.',
            'params'      => array(
                array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
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
            'description' => 'The requests to Claude (agent claude), to Pinegrap AI (agent ai) and to MCP servers that answer their alias on their own (agent mcp), newest first: those asked in the channels the owner of the application may read, the runs on the tasks the owner may see, and the owner\'s own requests written in a note or in the Visual Page Editor (origin says where). status queued and sent are waiting (a request of agent mcp that is sent waits for the person who asked to approve it on its card: GET /workspace/changes?type=mcp_call), running is being worked on, answered has its answer (reply_message_id in a channel), failed and cancelled ended without one (error says why). A run on a task (origin task) carries what the assistant was told (instruction, empty when it worked from the task itself) and what it answered (answer); the changes it proposed are listed by GET /workspace/changes with the same task_id. The words of any other request are read where it was asked. Page with next_cursor.',
            'params'      => array(
                array('name' => 'agent', 'in' => 'query', 'type' => 'enum', 'values' => array('claude', 'ai', 'mcp')),
                array('name' => 'task_id', 'in' => 'query', 'type' => 'int', 'min' => 1, 'description' => 'Only the runs on this task.'),
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
            'description' => 'The personal notes of the owner of the application - the pinned ones first, then the latest changed - followed by the notes colleagues shared with them, the latest changed first. A note is seen by nobody but the one who wrote it until it is shared, administrators included, so nobody else\'s notes are listed here. scope mine or shared keeps one of the two. search and updated_since are applied before the list is paged, so every note that matches comes in some page; page.total counts them. Page with next_cursor until has_more is false. A note changed while the pages are read moves to the top and may be missed in that pass; read again with updated_since from the moment the first page was asked for. text and body are null in the listing; GET /workspace/notes/{id} carries them.',
            'params'      => array(
                array('name' => 'scope', 'in' => 'query', 'type' => 'enum', 'values' => array('all', 'mine', 'shared'), 'default' => 'all'),
                array('name' => 'search', 'in' => 'query', 'type' => 'string', 'max_length' => 100, 'description' => 'Words in the title or the text.'),
                array('name' => 'updated_since', 'in' => 'query', 'type' => 'datetime', 'description' => 'Only notes changed at or after this moment.'),
                array('name' => 'cursor', 'in' => 'query', 'type' => 'string', 'max_length' => 200, 'description' => 'next_cursor from the previous page.'),
                array('name' => 'limit', 'in' => 'query', 'type' => 'int', 'min' => 1, 'max' => 200, 'default' => 100, 'description' => 'Notes per page.'),
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
            'description' => 'A note of the owner\'s, one shared with them, or one shared in a channel they may read. text is the note with every tag spelled out and every figure worked out, each item of a list and each quoted line on a line of its own; body keeps it as written. Reading it here does not mark it read.',
            'params'      => array(
                array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
            ),
        ),

        array(
            'id'          => 'workspace.notes.create',
            'method'      => 'POST',
            'path'        => '/workspace/notes',
            'scope'       => 'workspace:write',
            'handler'     => 'ws_api_notes_create',
            'dry_run' => true,
            'returns'     => 'WorkspaceNote',
            'status'      => 201,
            'summary'     => 'Write a note',
            'description' => 'Writes a new personal note of the owner of the application, as the notes screen does: nobody else sees it until the owner shares it there. body is Markdown; tags such as <#order:1045> or <@user:12> are kept as tags and checklists, tables and figures work as on the screen. A note without a title is called by its first line. Send an Idempotency-Key so a retried call does not write two notes.',
            'params'      => array(
                array('name' => 'title', 'in' => 'body', 'type' => 'string', 'max_length' => 200),
                array('name' => 'body', 'in' => 'body', 'type' => 'string', 'max_length' => 60000, 'description' => 'The text of the note, in Markdown.'),
                array('name' => 'pinned', 'in' => 'body', 'type' => 'bool', 'description' => 'Keep it at the top of the owner\'s notes.'),
            ),
        ),

        array(
            'id'           => 'workspace.notes.update',
            'method'       => 'POST',
            'also_accepts' => array('PATCH'),
            'path'         => '/workspace/notes/{id}',
            'scope'        => 'workspace:write',
            'handler'      => 'ws_api_notes_update',
            'dry_run' => true,
            'returns'      => 'WorkspaceNote',
            'summary'      => 'Change a note',
            'description'  => 'Changes only the fields sent, on a note of the owner\'s or one shared with them to change (access edit). body replaces the whole text: read the note first and send it back changed. The state before is kept among the versions as on the screen, and the others who keep the note are told it changed. Send base, the updated_at the note was read at, to have the change refused with 409 changed_meanwhile when somebody else changed the note since. pinned is the owner\'s alone. PATCH is accepted beside POST.',
            'params'       => array(
                array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
                array('name' => 'title', 'in' => 'body', 'type' => 'string', 'max_length' => 200),
                array('name' => 'body', 'in' => 'body', 'type' => 'string', 'max_length' => 60000, 'description' => 'The whole new text of the note, in Markdown.'),
                array('name' => 'pinned', 'in' => 'body', 'type' => 'bool'),
                array('name' => 'base', 'in' => 'body', 'type' => 'datetime', 'description' => 'The updated_at the note was read at.'),
            ),
        ),

        array(
            'id'          => 'workspace.notes.versions.list',
            'method'      => 'GET',
            'path'        => '/workspace/notes/{id}/versions',
            'scope'       => 'workspace:read',
            'handler'     => 'ws_api_note_versions_list',
            'returns'     => array('list' => 'WorkspaceNoteVersion'),
            'summary'     => 'The earlier versions of a note',
            'description' => 'The states a note had before it was changed, the latest first, for whoever may read the note: when that state was saved (saved_at, to the minute) and by whom, its title and its length in characters (size). A state is kept when the note is changed after a pause, when somebody else changes it, or when a change takes away much of it; only the latest ones are kept. text and body are null in the listing; GET /workspace/notes/{id}/versions/{version_id} carries them.',
            'params'      => array(
                array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
            ),
        ),

        array(
            'id'          => 'workspace.notes.versions.get',
            'method'      => 'GET',
            'path'        => '/workspace/notes/{id}/versions/{version_id}',
            'scope'       => 'workspace:read',
            'handler'     => 'ws_api_note_versions_get',
            'returns'     => 'WorkspaceNoteVersion',
            'summary'     => 'One earlier version of a note, with its text',
            'description' => 'text is that state of the note with every tag spelled out and every figure worked out, as the reader would see it today; body keeps it as written. Putting a version back is done on the notes screen.',
            'params'      => array(
                array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
                array('name' => 'version_id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
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
            'description' => 'Tasks the owner of the application may see. scope mine is the owner\'s own tasks, created those they gave out, department the tasks of a department, channel those of a channel, all every task (only for an owner who holds the team board). Pass ref_type and ref_id to find the tasks about one record, updated_since to fetch only what changed, delegate to find the tasks handed to an assistant (any, or ai or claude) or to none.',
            'params'      => array(
                array('name' => 'scope', 'in' => 'query', 'type' => 'enum', 'values' => array('mine', 'created', 'department', 'channel', 'all'), 'default' => 'mine'),
                array('name' => 'status', 'in' => 'query', 'type' => 'enum', 'values' => array('open', 'closed', 'all', 'todo', 'doing', 'waiting', 'done', 'cancelled'), 'default' => 'open'),
                array('name' => 'department_id', 'in' => 'query', 'type' => 'int', 'min' => 1),
                array('name' => 'channel_id', 'in' => 'query', 'type' => 'int', 'min' => 1),
                array('name' => 'ref_type', 'in' => 'query', 'type' => 'enum', 'values' => ws_record_type_keys()),
                array('name' => 'ref_id', 'in' => 'query', 'type' => 'int', 'min' => 1),
                array('name' => 'updated_since', 'in' => 'query', 'type' => 'datetime'),
                array('name' => 'delegate', 'in' => 'query', 'type' => 'string', 'max_length' => 32, 'description' => 'Handed to an assistant: any, none, ai, claude, or mcp:<server id> for an MCP server\'s alias.'),
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
            'description' => 'delegate is the assistant the task was handed to (ai or claude; the people on it stay responsible), assistant_working the one on a run on it now, and waiting_changes how many changes the assistants proposed on it wait to be applied or set aside.',
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
            'dry_run' => true,
            'returns'     => 'WorkspaceTask',
            'status'      => 201,
            'summary'     => 'Create a task',
            'description' => 'Creates a task. channel_id posts its card into that channel, written as POST /workspace/channels/{id}/messages writes: as the application with an application key or an OAuth connection, as the person from a team devices session. warnings says what the assignment does to the people\'s days (over their capacity, not in the department); a clash with somebody\'s leave is refused with 422 unless force is sent by an owner who may override. Send an Idempotency-Key so a retried call does not create two tasks.',
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
            'dry_run' => true,
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
            'dry_run' => true,
            'returns'     => 'WorkspaceTask',
            'summary'     => 'Mark a task done',
            'description' => 'The same as setting status to done: the creator is told and the task\'s channel gets a line.',
            'params'      => array(
                array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
            ),
        ),

        array(
            'id'          => 'workspace.tasks.delegate',
            'method'      => 'POST',
            'path'        => '/workspace/tasks/{id}/delegate',
            'scope'       => 'tasks:write',
            'handler'     => 'ws_api_tasks_delegate',
            'dry_run' => true,
            'returns'     => 'WorkspaceAssistantRequest',
            'status'      => 201,
            'summary'     => 'Hand a task to an assistant',
            'description' => 'Hands an open task to Pinegrap AI (agent ai), Claude (agent claude) or an MCP server\'s alias (agent mcp:<server id>, answered by Pinegrap AI with that server\'s tools; a tool that writes waits on the task as a change of type mcp_call that only the owner can run, with their own account; a server that is an agent itself answers on its own, without Pinegrap AI, and its run is a request of agent mcp; when the tool it answers with changes things on the service, the run first waits on the task as a change of type mcp_call for the owner to approve, and then goes with their own account), as the task drawer does, and starts a run: the assistant reads the task, its notes, its channel and the records it is about (an alias reads only the task\'s title, description, state and dates and the instruction; a server\'s own agent the task\'s number, title and description, the instruction and its own last answer on the task), answers, and proposes what it would change. It writes nothing itself; its proposals wait on the task for the owner to apply (GET /workspace/changes?task_id=). The people on the task stay responsible; a task nobody is on gets the owner. For a task already with that assistant, the call is one more run with a new instruction. Only for an owner who may change the task, and never from an application an assistant works through. One run at a time on a task: 409 assistant_busy while one is under way. The answer is the run; follow it with GET /workspace/assistant-requests?task_id=, or the workspace.task.assistant_answered event. Claude is started at once; Pinegrap AI and an MCP server\'s own agent are carried on by the screens that are open and by the hourly job.',
            'params'      => array(
                array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
                array('name' => 'agent', 'in' => 'body', 'type' => 'string', 'max_length' => 32, 'required' => true, 'description' => 'ai, claude, or mcp:<server id> for an MCP server\'s alias.'),
                array('name' => 'instruction', 'in' => 'body', 'type' => 'string', 'max_length' => 2000, 'description' => 'What it should do. Left out, it works from the task itself. Tags such as <#order:1045> are kept as tags.'),
            ),
        ),

        array(
            'id'          => 'workspace.tasks.delegate_stop',
            'method'      => 'POST',
            'path'        => '/workspace/tasks/{id}/delegate/stop',
            'scope'       => 'tasks:write',
            'handler'     => 'ws_api_tasks_delegate_stop',
            'dry_run' => true,
            'returns'     => 'WorkspaceTask',
            'summary'     => 'Stop an assistant\'s run on a task',
            'description' => 'Stops the run under way on the task, if any; take_back also takes the task back from the assistant (delegate becomes null). For the person who started the run, or an owner who may change the task. What the assistant proposed before stays on the task to be applied or set aside.',
            'params'      => array(
                array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
                array('name' => 'take_back', 'in' => 'body', 'type' => 'bool', 'description' => 'Take the task back from the assistant as well.'),
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
            'dry_run'     => true,
            'returns'     => 'WorkspaceTaskNote',
            'status'      => 201,
            'summary'     => 'Add a note to a task',
            'description' => 'Written in the application\'s name with an application key or an OAuth connection, or in the person\'s from a team devices session (POST /auth/login). Tags such as <#order:1045> or <@user:12> are kept; a person mentioned is told.',
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
            'status'      => 201,
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
            'dry_run' => true,
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
            'dry_run' => true,
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
            'description' => 'The messages and the tasks that point at one record - an order, a product or product group, an offer, a contact, a user, a current account, an invoice, a delivery note, a cash receipt, an incoming e-invoice, a sales quote, a form submission, a calendar event, a file or a page - in the channels the owner may read. For a contact, the channels tied to them as well.',
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
            'id'          => 'workspace.plan_items.list',
            'method'      => 'GET',
            'path'        => '/workspace/plan-items',
            'scope'       => 'tasks:read',
            'handler'     => 'ws_api_plan_items_list',
            'returns'     => array('list' => 'WorkspacePlanItem'),
            'summary'     => 'List plan items',
            'description' => 'The meetings, customer visits, leave, holidays and other items on the planning board and the calendar between two days, the earliest first, as the whole team sees them. A yearly holiday is listed in every year of the range with the same id. user_id keeps the items about one person: theirs, their departments\' and the company\'s.',
            'params'      => array(
                array('name' => 'from', 'in' => 'query', 'type' => 'string', 'max_length' => 10, 'description' => 'YYYY-MM-DD. Today when left out.'),
                array('name' => 'days', 'in' => 'query', 'type' => 'int', 'min' => 1, 'max' => 92, 'default' => 14),
                array('name' => 'user_id', 'in' => 'query', 'type' => 'int', 'min' => 1),
                array('name' => 'kind', 'in' => 'query', 'type' => 'enum', 'values' => array_keys(ws_event_kinds())),
            ),
        ),

        array(
            'id'          => 'workspace.plan_items.get',
            'method'      => 'GET',
            'path'        => '/workspace/plan-items/{id}',
            'scope'       => 'tasks:read',
            'handler'     => 'ws_api_plan_items_get',
            'returns'     => 'WorkspacePlanItem',
            'summary'     => 'One plan item',
            'params'      => array(
                array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
            ),
        ),

        array(
            'id'          => 'workspace.plan_items.create',
            'method'      => 'POST',
            'path'        => '/workspace/plan-items',
            'scope'       => 'tasks:write',
            'handler'     => 'ws_api_plan_items_create',
            'dry_run' => true,
            'returns'     => 'WorkspacePlanItem',
            'status'      => 201,
            'summary'     => 'Add a plan item',
            'description' => 'Puts a meeting, a customer visit, leave or another item on the planning board, as the calendar does; it takes its hours (a whole day for leave and holidays) from the people\'s capacity. scope people is about the people listed - the owner when nobody is - and the owner may plan only for themselves and the people of the departments they lead; department is the lead\'s or a settings holder\'s, company a settings holder\'s alone. Leave and holidays are whole days. Send an Idempotency-Key so a retried call does not add two items.',
            'params'      => array_merge(array(
                array('name' => 'start_date', 'in' => 'body', 'type' => 'string', 'max_length' => 10, 'required' => true, 'description' => 'YYYY-MM-DD.'),
            ), $plan_item_fields),
        ),

        array(
            'id'           => 'workspace.plan_items.update',
            'method'       => 'POST',
            'also_accepts' => array('PATCH'),
            'path'         => '/workspace/plan-items/{id}',
            'scope'        => 'tasks:write',
            'handler'      => 'ws_api_plan_items_update',
            'dry_run' => true,
            'returns'      => 'WorkspacePlanItem',
            'summary'      => 'Change a plan item',
            'description'  => 'Changes only the fields sent, on an item the owner added, of a department they lead, or any item for a settings holder (can_edit). people replaces the list. A holiday read from a calendar address (from_calendar) is written over by the next reading of that calendar. PATCH is accepted beside POST.',
            'params'       => array_merge(array(
                array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
                array('name' => 'start_date', 'in' => 'body', 'type' => 'string', 'max_length' => 10, 'description' => 'YYYY-MM-DD.'),
            ), $plan_item_fields),
        ),

        array(
            'id'          => 'workspace.plan_items.delete',
            'method'      => 'DELETE',
            'path'        => '/workspace/plan-items/{id}',
            'scope'       => 'tasks:write',
            'handler'     => 'ws_api_plan_items_delete',
            'dry_run'     => true,
            'returns'     => array('id' => 'integer', 'deleted' => 'boolean'),
            'summary'     => 'Remove a plan item',
            'description' => 'Takes an item off the board and the calendar, for whoever may change it (can_edit). It cannot be undone. POST /workspace/plan-items/{id}/delete does the same for servers that refuse DELETE.',
            'params'      => array(
                array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
            ),
        ),

        array(
            'id'          => 'workspace.plan_items.delete_post',
            'method'      => 'POST',
            'path'        => '/workspace/plan-items/{id}/delete',
            'scope'       => 'tasks:write',
            'handler'     => 'ws_api_plan_items_delete',
            'dry_run'     => true,
            'returns'     => array('id' => 'integer', 'deleted' => 'boolean'),
            'summary'     => 'Remove a plan item, addressed as a POST',
            'description' => 'The same removal as DELETE /workspace/plan-items/{id}, for clients and servers that cannot send DELETE.',
            'params'      => array(
                array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
            ),
        ),

        array(
            'id'          => 'workspace.search',
            'method'      => 'GET',
            'path'        => '/workspace/search',
            'scope'       => 'workspace:read',
            'handler'     => 'ws_api_search',
            'returns'     => array('list' => 'WorkspaceSearchHit'),
            'summary'     => 'Search the workspace',
            'description' => 'The search box of the workspace screens (Ctrl+K): channels, messages, decisions, tasks, notes, files posted in channels and panel records, a few hits of each group in that order, from what the owner of the application may see. Starting q with # looks only among records (#order 1045 among one kind); with @, among the people of the team. Tasks are searched only with tasks:read, and each kind of record only with the scope that reads it (orders:read for orders, customers:read for contacts and so on). url opens the hit on the panel; for a file, the file itself.',
            'params'      => array(
                array('name' => 'q', 'in' => 'query', 'type' => 'string', 'max_length' => 200, 'required' => true, 'description' => 'At least two characters, or one after # with a kind.'),
                array('name' => 'type', 'in' => 'query', 'type' => 'enum', 'values' => array('channels', 'messages', 'decisions', 'tasks', 'notes', 'files', 'records'), 'description' => 'Only this group.'),
                array('name' => 'channel_id', 'in' => 'query', 'type' => 'int', 'min' => 1, 'description' => 'Only what was written in this channel: messages, decisions, tasks and files.'),
                array('name' => 'limit', 'in' => 'query', 'type' => 'int', 'min' => 1, 'max' => 20, 'default' => 8, 'description' => 'Hits per group.'),
            ),
        ),

        array(
            'id'          => 'workspace.my_day.get',
            'method'      => 'GET',
            'path'        => '/workspace/my-day',
            'scope'       => 'workspace:read',
            'handler'     => 'ws_api_my_day_get',
            'returns'     => 'WorkspaceMyDay',
            'summary'     => 'The owner\'s day',
            'description' => 'What the owner of the application has on their plate today, in one call: their open tasks due today or overdue, the timer they have running, today\'s plan items about them, the approval requests and read receipts waiting for their answer, the changes the assistants proposed at their request that wait for them to apply or set aside (proposed_changes, in channels and on tasks), and what was written to them lately (replies to their messages and mentions of them, read or not). The tasks, the timer and the plan items need tasks:read; without it they are empty and left_out names the scope.',
            'params'      => array(),
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
            'description' => 'Adds the template\'s tasks and notes to a public or private channel the owner of the application may write in, the way the channel menu does. The tasks are made by the owner, their dates counted in calendar days from today; a task meant for somebody the owner may not give work to is made with nobody on it and counted in unassigned. The notes become the owner\'s own and are shared in the channel. The summary and the pinned message are written only where the channel has none (summary_kept and pinned_kept say when it had); the pinned message is written as the application\'s with an application key or an OAuth connection, or as the person\'s from a team devices session (POST /auth/login). The channel gets a line saying the template was applied, and workspace.template.applied is announced.',
            'params'      => array(
                array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
                array('name' => 'template_id', 'in' => 'body', 'type' => 'string', 'max_length' => 40, 'required' => true, 'description' => 'An id from GET /workspace/templates.'),
            ),
        ),

        array(
            'id'          => 'workspace.flows.list',
            'method'      => 'GET',
            'path'        => '/workspace/flows',
            'scope'       => 'workspace:read',
            'handler'     => 'ws_api_flows_list',
            'returns'     => array('list' => 'WorkspaceFlow'),
            'summary'     => 'List flows',
            'description' => 'The flows of the owner of the application - drafts, live and paused ones - and the live flows of others whose home channel (channel_id) the owner reads, which the owner may run; the latest changed first. A flow is seen by nobody but its owner until it is published with a home channel, administrators included. access says edit (the owner\'s own; building flows is for staff in the team) or run. version is the live version, null before the first publish. draft, graph and problems are null in the listing; GET /workspace/flows/{id} carries them. Deleted flows are not listed.',
            'params'      => array(
                array('name' => 'scope', 'in' => 'query', 'type' => 'enum', 'values' => array('all', 'mine', 'shared'), 'default' => 'all', 'description' => 'mine keeps the owner\'s own, shared the ones of others.'),
            ),
        ),

        array(
            'id'          => 'workspace.flows.get',
            'method'      => 'GET',
            'path'        => '/workspace/flows/{id}',
            'scope'       => 'workspace:read',
            'handler'     => 'ws_api_flows_get',
            'returns'     => 'WorkspaceFlow',
            'summary'     => 'One flow, with its graph',
            'description' => 'A flow the owner of the application reaches. To its owner it carries draft, the graph being edited, with draft_rev, the number to send back with a change, and problems, what stops the draft from being published (an empty list when nothing does): each has a code (too_many, bad_id, duplicate_id, unknown_kind, bad_config, no_trigger, two_triggers, bad_edge, arrow_into_trigger, bad_outcome, duplicate_edge, cycle, unreachable), the box (node) or the place of the arrow in edges (edge) it is about, and a text to show. graph is the live version as it was published, null before the first publish. To somebody who may only run the flow, draft and problems are null.',
            'params'      => array(
                array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
            ),
        ),

        array(
            'id'          => 'workspace.flows.create',
            'method'      => 'POST',
            'path'        => '/workspace/flows',
            'scope'       => 'workspace:write',
            'handler'     => 'ws_api_flows_create',
            'dry_run'     => true,
            'returns'     => 'WorkspaceFlow',
            'status'      => 201,
            'summary'     => 'Draft a flow',
            'description' => 'Starts a new draft flow of the owner of the application, as the flow canvas does. Building flows is for staff in the team: anybody else is refused with 403. A draft never runs, so an application key or an OAuth connection may write one - an assistant can lay a process out for the person to look at and publish. It may hold problems, which the answer lists instead of refusing it; a dry run answers them too. A draft is at most ' . (int) (WS_FLOW_GRAPH_BYTES / 1024) . ' KB of JSON, a published flow at most ' . WS_FLOW_MAX_NODES . ' boxes, and one person keeps at most ' . WS_FLOW_MAX_PER_OWNER . ' flows. Send an Idempotency-Key so a retried call does not create two flows.',
            'params'      => array_merge(array(
                array('name' => 'name', 'in' => 'body', 'type' => 'string', 'max_length' => 160, 'required' => true, 'description' => 'What the flow is called. Runs of spaces are made one.'),
            ), array_slice($flow_fields, 1)),
        ),

        array(
            'id'           => 'workspace.flows.update',
            'method'       => 'POST',
            'also_accepts' => array('PATCH'),
            'path'         => '/workspace/flows/{id}',
            'scope'        => 'workspace:write',
            'handler'      => 'ws_api_flows_update',
            'dry_run'      => true,
            'returns'      => 'WorkspaceFlow',
            'summary'      => 'Change a flow\'s draft',
            'description'  => 'Changes only the fields sent, on a flow of the owner\'s own (access edit): its name, its home channel, or the parts of its draft - nodes, edges and notes each replace that part as a whole, and a part left out is kept. draft_rev is the one the flow was read at: when the draft was saved meanwhile from another screen or device the change is refused with 409 changed_meanwhile; the answer carries the new draft_rev. A live flow goes on running its published version until the draft is published again. An application key or an OAuth connection may change a draft. PATCH is accepted beside POST.',
            'params'       => array_merge(array(
                array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
                array('name' => 'draft_rev', 'in' => 'body', 'type' => 'int', 'min' => 0, 'required' => true, 'description' => 'The draft_rev the flow was read at.'),
            ), $flow_fields),
        ),

        // Published, a flow later opens tasks in the person's own name, so it
        // is published from their team devices session only; an MCP client's
        // OAuth connection is refused, and is offered no tool for it. Drafting
        // stays a tool.
        array(
            'id'          => 'workspace.flows.publish',
            'method'      => 'POST',
            'path'        => '/workspace/flows/{id}/publish',
            'scope'       => 'workspace:write',
            'handler'     => 'ws_api_flows_publish',
            'dry_run'     => true,
            'mcp'         => false,
            'returns'     => 'WorkspaceFlow',
            'summary'     => 'Publish a flow',
            'description' => 'Publishes the draft of a flow of the owner\'s own as its next version and makes the flow live; earlier versions are kept for the runs that started on them. A published flow later opens tasks in the person\'s own name, so only a team devices session (a Bearer token from POST /auth/login) may publish one: an application key and an OAuth connection write as the application and are refused with 403 forbidden. A draft with problems is refused with 422 (GET /workspace/flows/{id} lists them), and so is a task box with people on it the owner may not give work to. Send draft_rev, the one the draft was read at, to have the publish refused with 409 changed_meanwhile when the draft changed since; a second publish of the same moment is refused with 409 too. Publishing a paused flow makes it live again. The activity log gets a line.',
            'params'      => array(
                array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
                array('name' => 'draft_rev', 'in' => 'body', 'type' => 'int', 'min' => 0, 'description' => 'The draft_rev the draft was read at.'),
            ),
        ),

        array(
            'id'          => 'workspace.flows.pause',
            'method'      => 'POST',
            'path'        => '/workspace/flows/{id}/pause',
            'scope'       => 'workspace:write',
            'handler'     => 'ws_api_flows_pause',
            'dry_run'     => true,
            'returns'     => 'WorkspaceFlow',
            'summary'     => 'Pause a flow',
            'description' => 'Pauses a live flow of the owner\'s own: nothing starts it until it is published again, and its versions stay as they are. A flow that is not live is refused with 422. The activity log gets a line.',
            'params'      => array(
                array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
            ),
        ),

        array(
            'id'          => 'workspace.flows.delete',
            'method'      => 'DELETE',
            'path'        => '/workspace/flows/{id}',
            'scope'       => 'workspace:write',
            'handler'     => 'ws_api_flows_delete',
            'dry_run'     => true,
            'returns'     => 'WorkspaceFlow',
            'summary'     => 'Delete a flow',
            'description' => 'Deletes a flow of the owner\'s own, in any state: nobody reaches it any more, and the answer is the flow as it was, with status binned. It cannot be undone through the API. The activity log gets a line. POST /workspace/flows/{id}/delete does the same for servers that refuse DELETE.',
            'params'      => array(
                array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
            ),
        ),

        array(
            'id'          => 'workspace.flows.delete_post',
            'method'      => 'POST',
            'path'        => '/workspace/flows/{id}/delete',
            'scope'       => 'workspace:write',
            'handler'     => 'ws_api_flows_delete',
            'dry_run'     => true,
            'returns'     => 'WorkspaceFlow',
            'summary'     => 'Delete a flow, addressed as a POST',
            'description' => 'The same as DELETE /workspace/flows/{id}, for clients and servers that cannot send DELETE. It has an address of its own because POST /workspace/flows/{id} is the change.',
            'params'      => array(
                array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
            ),
        ),

        array(
            'id'          => 'workspace.mcp_servers.catalogue',
            'method'      => 'GET',
            'path'        => '/workspace/mcp-servers/catalogue',
            'scope'       => 'workspace:read',
            'handler'     => 'ws_api_mcp_catalogue',
            'mcp'         => false,
            'returns'     => array('list' => 'WorkspaceMcpConnector'),
            'summary'     => 'Ready-made MCP connectors',
            'description' => 'The other services\' MCP servers the Workspace Settings add with one click, for an application whose owner is an administrator (403 for anybody else). auth says how the site signs in: none (the server answers without an account), oauth (the service registers the site on its own and an administrator signs in once) or bearer (a token made on token_url). alias is the one offered when it is free; tools read_only means the first read of the tools turns on the ones the server marks as reading only. added says whether the site already has a server at url, under server_alias. The list is bundled with the site, not fetched. Adding a server, its credential and its sign-in are done in the Workspace Settings only; no MCP tool lists this.',
            'params'      => array(),
        ),

    );

    // Asking Claude in a channel (api_claude.php).
    return array_merge($routes, ws_claude_api_routes());
}
