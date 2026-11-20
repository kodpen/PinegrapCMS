<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace external API - what the channel screen reads about one channel
 * that no other route gave: its detail (ws_channel_detail()), the Files and
 * Board tabs (ws_channel_files(), ws_channel_board()), the list of earlier
 * versions of its conversation (ws_channel_eras()) and its export as
 * Markdown (ws_channel_export_collect(), ws_channel_export_markdown()) and
 * the Activity tab of Channel info (ws_channel_activity()).
 *
 * Every route reads the channel through ws_api_channel_or_404(), the
 * panel's reading rule (ws_can_read_channel()), and gives no more than the
 * screen gives that reader. The export is REST only: it is a file, not a
 * tool's answer.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// The export's reading and Markdown, loaded by its screen alone otherwise.
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/channel_export.php');

/**
 * The routes of this file, merged into ws_api_routes().
 *
 * @return array[]
 */
function ws_channel_read_api_routes()
{
    $id = array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true);
    $unread = ' A channel the owner of the application may not read answers 404, as the panel shows it nothing.';

    return array(
        array(
            'id'          => 'workspace.channels.get',
            'method'      => 'GET',
            'path'        => '/workspace/channels/{id}',
            'scope'       => 'workspace:read',
            'handler'     => 'ws_api_channel_get',
            'returns'     => 'WorkspaceChannelDetail',
            'summary'     => 'One channel, as its Channel info shows it',
            'description' => 'A channel the owner of the application reads, as the sidebar lists it (channel), with what its Channel info shows every reader: who owns it, how many people and agents are in it (GET /workspace/channels/{id}/members and /agents list them), the guests an open share lets read it (seen, when a guest was last here, only for an owner who may manage the channel, as on the screen; null otherwise), how many earlier versions its conversation has (GET /workspace/channels/{id}/eras), the pinned message, the counts on its Decisions and Tasks tabs, whether it is a discussion, how many of the owner\'s own messages wait to be posted in it, what the owner may do there (post, manage, make it public or private, clear its conversation, pin, share with guests), and open_items: the polls and agent questions still taking answers, the running check-in and the approval requests still waiting, as the line over the conversation lists them (the caller\'s own first, at most 10; mine is true when the item waits for the caller\'s answer: for the person\'s own session or connection, as on their screen; for an application key, only an approval request that asked its own agent and still waits for that agent). reading_as_staff is true when staff read a private channel they are not in.' . $unread,
            'params'      => array($id),
        ),

        array(
            'id'          => 'workspace.channels.files.list',
            'method'      => 'GET',
            'path'        => '/workspace/channels/{id}/files',
            'scope'       => 'workspace:read',
            'handler'     => 'ws_api_channel_files_list',
            'returns'     => array('list' => 'WorkspaceMessage'),
            'summary'     => 'The files posted in a channel',
            'description' => 'The channel\'s Files tab: the messages with a file posted in it, newest first, at most 300, as GET /workspace/channels/{id}/messages shows a message (file carries its name and address). A deleted message, and one the owner deleted for themselves, is left out.' . $unread,
            'params'      => array($id),
        ),

        array(
            'id'          => 'workspace.channels.board.get',
            'method'      => 'GET',
            'path'        => '/workspace/channels/{id}/board',
            'scope'       => 'workspace:read',
            'handler'     => 'ws_api_channel_board_get',
            'returns'     => 'WorkspaceChannelBoard',
            'summary'     => 'A channel\'s Board tab',
            'description' => 'The channel\'s tasks, decisions and files as the Board tab groups them: group_by status (a column per task status; finished tasks of the last 14 days unless done_all, cancelled ones only with cancelled), person (a column per member and one for the tasks nobody is on; open tasks), date (overdue, today, this week, later, no due date; open tasks) or type (tasks, decisions, files, and the pinned message with the summary). Each column has its key, title and cards; more counts the finished tasks left out. A card is a task (task_id, number, status, priority, due_date, assignees), a decision, a file or the pin (message_id), or the summary. A discussion and a guest\'s room have no board: 404.' . $unread,
            'params'      => array(
                $id,
                array('name' => 'group_by', 'in' => 'query', 'type' => 'enum', 'values' => array_keys(ws_channel_board_groupings()), 'default' => 'status'),
                array('name' => 'priority', 'in' => 'query', 'type' => 'enum', 'values' => array_keys(ws_task_priorities()), 'description' => 'Only the tasks of this priority.'),
                array('name' => 'person', 'in' => 'query', 'type' => 'int', 'min' => -1, 'description' => 'Only the tasks this user is on; -1 the tasks nobody is on.'),
                array('name' => 'mine', 'in' => 'query', 'type' => 'bool', 'description' => 'Only the tasks the owner is on.'),
                array('name' => 'q', 'in' => 'query', 'type' => 'string', 'max_length' => 100, 'description' => 'Words in a card\'s title.'),
                array('name' => 'cancelled', 'in' => 'query', 'type' => 'bool', 'description' => 'With group_by status, a column of the cancelled tasks too.'),
                array('name' => 'done_all', 'in' => 'query', 'type' => 'bool', 'description' => 'With group_by status, every finished task, not only those of the last 14 days.'),
            ),
        ),

        array(
            'id'          => 'workspace.channels.activity.get',
            'method'      => 'GET',
            'path'        => '/workspace/channels/{id}/activity',
            'scope'       => 'workspace:read',
            'handler'     => 'ws_api_channel_activity_get',
            'returns'     => 'WorkspaceChannelActivity',
            'summary'     => 'A channel\'s state, as its Activity tab shows it',
            'description' => 'The Activity tab of Channel info in one read: the summary of the agents\' states (working, waiting, not seen, ready, paused, and the words of the line), the project (the newest one an agent reported, else the linked repository\'s owner/name; null for neither), a row per agent in the order working, waiting, not seen, ready, paused (while working: who asked and the message when the request is in this channel, since when, the progress note; the reported model, context, project and branch and when it was reported, stale after a day), the requests of the channel\'s agents (waiting with the oldest\'s age, in work, answered and failed today), the polls, agent questions and approvals waiting for a person (oldest first, at most 5, more counts the rest), today\'s decisions (the count and the newest 3), each reported plan window once with the agents that reported it, and the repository\'s numbers of today. Everything an agent reported is as it said it; the site cannot check it (usage_tip). context, project, branch and repository are null where nothing was reported or counted. It only tells: nothing is changed.' . $unread,
            'params'      => array($id),
        ),

        array(
            'id'          => 'workspace.channels.eras.list',
            'method'      => 'GET',
            'path'        => '/workspace/channels/{id}/eras',
            'scope'       => 'workspace:read',
            'handler'     => 'ws_api_channel_eras_list',
            'returns'     => 'WorkspaceChannelEras',
            'summary'     => 'The versions of a channel\'s conversation',
            'description' => 'The earlier versions a cleared conversation left, newest first (number, title, label, how many messages, from and to, who cleared it, the last message\'s id), and the current conversation: since when and how many messages are written in it. can_clear says whether the owner may clear it (staff inside the channel); clearing is done in the panel.' . $unread,
            'params'      => array($id),
        ),

        array(
            'id'          => 'workspace.channels.export',
            'method'      => 'GET',
            'path'        => '/workspace/channels/{id}/export',
            'scope'       => 'workspace:read',
            'handler'     => 'ws_api_channel_export',
            // A file the reader saves, as the Export drawer gives it.
            'mcp'         => false,
            'returns'     => 'WorkspaceChannelExport',
            'summary'     => 'Export a channel\'s conversation as Markdown',
            'description' => 'The Export drawer\'s Markdown file, as text: the channel\'s name, the range, who exported it and when, its decisions, its messages oldest first (a discussion\'s under the message it is about, when the owner reads the discussion; deleted messages and those the owner hid left out) and its files. from and to are dates (YYYY-MM-DD, either left out for an open end). A range of more than 5,000 messages answers 422; a date that is not one answers 422 naming it. The export is written in the audit log, as from the panel. No MCP tool.' . $unread,
            'params'      => array(
                $id,
                array('name' => 'from', 'in' => 'query', 'type' => 'string', 'max_length' => 10, 'description' => 'YYYY-MM-DD, the first day.'),
                array('name' => 'to', 'in' => 'query', 'type' => 'string', 'max_length' => 10, 'description' => 'YYYY-MM-DD, the last day.'),
            ),
        ),
    );
}

/* ---------------------------------------------------------------------------
   One channel's detail
   --------------------------------------------------------------------------- */

/**
 * What the API shows of ws_channel_detail(): what Channel info shows every
 * reader, the lists that have their own routes counted. A guest's last seen
 * stays as the panel gives it, empty for a reader who does not manage the
 * channel. Pure.
 *
 * @param array $channel ws_api_channel_present()
 * @param array $detail  ws_channel_detail()
 * @return array
 */
function ws_api_channel_detail_present($channel, $detail, $open_items = array())
{
    $guests = array();

    foreach ((array) ($detail['guests'] ?? array()) as $guest) {
        $guests[] = array(
            'guest_id' => (int) ($guest['guest_id'] ?? 0),
            'name'     => (string) ($guest['name'] ?? ''),
            'access'   => (string) ($guest['access'] ?? 'read'),
            'seen'     => ((string) ($guest['seen'] ?? '') !== '') ? (string) $guest['seen'] : null,
        );
    }

    $pin = $detail['pin'] ?? null;
    $counts = (array) ($detail['tab_counts'] ?? array());

    return array(
        'channel'           => $channel,
        'owner'             => ws_api_person_ref((int) ($detail['owner_id'] ?? 0), array((int) ($detail['owner_id'] ?? 0) => array('name' => (string) ($detail['owner_name'] ?? '')))),
        'member_count'      => count((array) ($detail['members'] ?? array())),
        'agent_count'       => count((array) ($detail['agents'] ?? array())),
        'guests'            => $guests,
        'eras'              => (int) ($detail['eras'] ?? 0),
        'pinned_message_id' => (is_array($pin) && ((int) ($pin['id'] ?? 0) > 0)) ? (int) $pin['id'] : null,
        'decisions'         => (int) ($counts['decisions'] ?? 0),
        'open_tasks'        => (int) ($counts['tasks'] ?? 0),
        'discussion'        => array_key_exists('thread', (array) $detail),
        'scheduled_mine'    => (int) ($detail['scheduled_mine'] ?? 0),
        'reading_as_staff'  => !empty($detail['audit']),
        'can_post'          => !empty($detail['can_post']),
        'can_manage'        => !empty($detail['can_manage']),
        'can_change_kind'   => !empty($detail['can_change_kind']),
        'can_clear'         => !empty($detail['can_clear']),
        'can_pin'           => !empty($detail['can_pin']),
        'can_share'         => !empty($detail['can_share']),
        'open_items'        => ws_api_channel_open_items_present($open_items),
    );
}

/**
 * ws_channel_open_items() as the route gives it. Pure.
 *
 * @param array[] $items
 * @return array[] kind, message_id, text, mine
 */
function ws_api_channel_open_items_present($items)
{
    $out = array();

    foreach ((array) $items as $item) {
        $out[] = array(
            'kind'       => (string) ($item['kind'] ?? ''),
            'message_id' => (int) ($item['message_id'] ?? 0),
            'text'       => (string) ($item['text'] ?? ''),
            'mine'       => !empty($item['mine']),
        );
    }

    return $out;
}

/**
 * Whose answer the open items are marked for (ws_channel_open_items()). Pure.
 *
 * Any device - the person's team devices session or an OAuth connection -
 * acts for the person and keeps the person's marks (null). Only an
 * application key reads for itself: its own agent's request value, 'claude'
 * for Claude's key, '' for a key with no agent.
 *
 * @param array|null $device api_devices row, null for an application key
 * @param bool       $claude ws_api_is_claude()
 * @param array|null $agent  ws_api_agent_caller_row()
 * @return string|null
 */
function ws_api_open_items_caller_of($device, $claude, $agent)
{
    if (is_array($device)) {
        return null;
    }

    if ($claude) {
        return 'claude';
    }

    return is_array($agent) ? ws_agent_request_value($agent) : '';
}

/**
 * ws_api_open_items_caller_of() for this call.
 *
 * @return string|null
 */
function ws_api_open_items_caller()
{
    $device = function_exists('api_current_device') ? api_current_device() : null;

    if (is_array($device)) {
        return ws_api_open_items_caller_of($device, false, null);
    }

    return ws_api_open_items_caller_of(null, function_exists('ws_api_is_claude') && ws_api_is_claude(),
        function_exists('ws_api_agent_caller_row') ? ws_api_agent_caller_row() : null);
}

// What ws_api_channel_detail_present() returns.
function ws_api_channel_detail_schema()
{
    return array(
        'channel'           => 'WorkspaceChannel',
        'owner'             => 'WorkspacePerson?',
        'member_count'      => 'integer',
        'agent_count'       => 'integer',
        'guests'            => array(array('guest_id' => 'integer', 'name' => 'string', 'access' => 'string', 'seen' => 'string?')),
        'eras'              => 'integer',
        'pinned_message_id' => 'integer?',
        'decisions'         => 'integer',
        'open_tasks'        => 'integer',
        'discussion'        => 'boolean',
        'scheduled_mine'    => 'integer',
        'reading_as_staff'  => 'boolean',
        'can_post'          => 'boolean',
        'can_manage'        => 'boolean',
        'can_change_kind'   => 'boolean',
        'can_clear'         => 'boolean',
        'can_pin'           => 'boolean',
        'can_share'         => 'boolean',
        'open_items'        => array(array('kind' => 'string', 'message_id' => 'integer', 'text' => 'string', 'mine' => 'boolean')),
    );
}

function ws_api_channel_get($params)
{
    $viewer = ws_api_viewer();
    $channel = ws_api_channel_or_404($viewer, $params['id']);

    api_ok(ws_api_channel_detail_present(ws_api_channel_present(ws_api_channel_brief($viewer, $channel)), ws_channel_detail($viewer, $channel),
        ws_channel_open_items($viewer, $channel, ws_api_open_items_caller())));
}

/* ---------------------------------------------------------------------------
   The Files tab
   --------------------------------------------------------------------------- */

function ws_api_channel_files_list($params)
{
    $viewer = ws_api_viewer();
    $channel = ws_api_channel_or_404($viewer, $params['id']);
    $rows = ws_channel_file_rows((int) $channel['id']);

    // The tab leaves out what the person deleted for themselves.
    $hidden = ws_message_hidden_ids($viewer['id'], array_map(function ($row) { return (int) $row['id']; }, $rows));
    $rows = array_values(array_filter($rows, function ($row) use ($hidden) { return !isset($hidden[(int) $row['id']]); }));
    $out = ws_api_messages_present($viewer, $rows);

    api_ok_list($out, count($out));
}

/* ---------------------------------------------------------------------------
   The Board tab
   --------------------------------------------------------------------------- */

/**
 * One card of the board as the API shows it. Pure.
 *
 * @param array $item a card of ws_channel_board()
 * @return array
 */
function ws_api_channel_board_card($item)
{
    $task = ((string) ($item['kind'] ?? '') === 'task');

    return array(
        'kind'       => (string) ($item['kind'] ?? ''),
        'title'      => (string) ($item['title'] ?? ''),
        'sub'        => (string) ($item['sub'] ?? ''),
        'message_id' => ((int) ($item['message_id'] ?? 0) > 0) ? (int) $item['message_id'] : null,
        'task_id'    => $task ? (int) $item['id'] : null,
        'number'     => $task ? (string) $item['number'] : null,
        'status'     => $task ? (string) $item['status'] : null,
        'priority'   => $task ? (string) $item['priority'] : null,
        'due_date'   => ($task && ((string) ($item['due_date'] ?? '') !== '')) ? (string) $item['due_date'] : null,
        'overdue'    => $task && !empty($item['overdue']),
        'assignees'  => $task ? array_map(function ($face) {
            return array('user_id' => (int) $face['id'], 'name' => (string) $face['name']);
        }, (array) $item['assignees']) : array(),
        'url'        => ((string) ($item['url'] ?? '') !== '') ? (string) $item['url'] : null,
    );
}

/**
 * The board as the API shows it: its grouping and columns of cards. Pure.
 *
 * @param array $board ws_channel_board()
 * @return array
 */
function ws_api_channel_board_present($board)
{
    $columns = array();

    foreach ((array) $board['columns'] as $column) {
        $columns[] = array(
            'key'   => (string) $column['key'],
            'title' => (string) $column['title'],
            'more'  => (int) ($column['more'] ?? 0),
            'cards' => array_map('ws_api_channel_board_card', (array) $column['items']),
        );
    }

    return array(
        'group_by'   => (string) $board['group_by'],
        'columns'    => $columns,
        'open_tasks' => (int) ($board['counts']['tasks'] ?? 0),
        'can_post'   => !empty($board['can_post']),
    );
}

// What ws_api_channel_board_card() returns.
function ws_api_channel_board_card_schema()
{
    return array(
        'kind'       => 'string',
        'title'      => 'string',
        'sub'        => 'string',
        'message_id' => 'integer?',
        'task_id'    => 'integer?',
        'number'     => 'string?',
        'status'     => 'string?',
        'priority'   => 'string?',
        'due_date'   => 'date?',
        'overdue'    => 'boolean',
        'assignees'  => 'WorkspacePerson[]',
        'url'        => 'string?',
    );
}

// What ws_api_channel_board_present() returns.
function ws_api_channel_board_schema()
{
    return array(
        'group_by'   => 'string',
        'columns'    => array(array('key' => 'string', 'title' => 'string', 'more' => 'integer', 'cards' => 'WorkspaceChannelBoardCard[]')),
        'open_tasks' => 'integer',
        'can_post'   => 'boolean',
    );
}

function ws_api_channel_board_get($params)
{
    $viewer = ws_api_viewer();
    $channel = ws_api_channel_or_404($viewer, $params['id']);

    if (!ws_channel_board_available($channel)) {
        api_fail_not_found(lang('Board'));
    }

    api_ok(ws_api_channel_board_present(ws_channel_board($viewer, $channel, array(
        'group_by'  => (string) ($params['group_by'] ?? 'status'),
        'priority'  => (string) ($params['priority'] ?? ''),
        'person'    => (int) ($params['person'] ?? 0),
        'mine'      => !empty($params['mine']),
        'q'         => (string) ($params['q'] ?? ''),
        'cancelled' => !empty($params['cancelled']),
        'done_all'  => !empty($params['done_all']),
    ))));
}

/* ---------------------------------------------------------------------------
   The Activity tab
   --------------------------------------------------------------------------- */

/**
 * ws_channel_activity() as the route gives it: the same answer, part by
 * part. Pure.
 *
 * @param array $activity ws_channel_activity()
 * @return array
 */
function ws_api_channel_activity_present($activity)
{
    return array(
        'channel_id' => (int) $activity['channel_id'],
        'summary'    => $activity['summary'],
        'project'    => $activity['project'],
        'agents'     => $activity['agents'],
        'requests'   => $activity['requests'],
        'waiting'    => $activity['waiting'],
        'decisions'  => $activity['decisions'],
        'usage'      => $activity['usage'],
        'usage_tip'  => (string) $activity['usage_tip'],
        'repository' => $activity['repository'],
    );
}

// What ws_api_channel_activity_present() returns.
function ws_api_channel_activity_schema()
{
    $agent_ref = array(array('id' => 'integer', 'handle' => 'string', 'name' => 'string'));

    return array(
        'channel_id' => 'integer',
        'summary'    => array('working' => 'integer', 'waiting' => 'integer', 'late' => 'integer', 'idle' => 'integer', 'paused' => 'integer', 'words' => 'string'),
        'project'    => 'object?',
        'agents'     => array(array(
            'id'          => 'integer',
            'handle'      => 'string',
            'name'        => 'string',
            'kind'        => 'string',
            'avatar'      => 'string',
            'role'        => 'string',
            'role_key'    => 'string',
            'state'       => 'string',
            'state_words' => 'string',
            'usage_high'  => 'boolean',
            'request'     => 'object?',
            'model'       => 'string',
            'reported_at' => 'integer?',
            'reported'    => 'string',
            'stale'       => 'boolean',
            'context'     => 'object?',
            'project'     => 'string?',
            'branch'      => 'string?',
        )),
        'requests'   => array('waiting' => 'integer', 'oldest_age' => 'string', 'oldest_message_id' => 'integer?', 'working' => 'integer', 'answered' => 'integer', 'failed' => 'integer'),
        'waiting'    => array('items' => array(array('kind' => 'string', 'message_id' => 'integer', 'text' => 'string', 'age' => 'string', 'created_at' => 'integer')), 'more' => 'integer'),
        'decisions'  => array('count' => 'integer', 'items' => array(array('message_id' => 'integer', 'text' => 'string'))),
        'usage'      => array(array('name' => 'string', 'label' => 'string', 'percent' => 'integer', 'resets_at' => 'integer', 'resets' => 'string', 'high' => 'boolean', 'words' => 'string', 'agents' => $agent_ref)),
        'usage_tip'  => 'string',
        'repository' => 'object?',
    );
}

function ws_api_channel_activity_get($params)
{
    $viewer = ws_api_viewer();
    $channel = ws_api_channel_or_404($viewer, $params['id']);

    api_ok(ws_api_channel_activity_present(ws_channel_activity($viewer, $channel, time())));
}

/* ---------------------------------------------------------------------------
   The versions of the conversation
   --------------------------------------------------------------------------- */

/**
 * One earlier version as the API shows it. Pure but for api_time() and
 * lang().
 *
 * @param array $era ws_channel_eras() row
 * @return array
 */
function ws_api_channel_era_present($era)
{
    $title = (string) ($era['title'] ?? '');

    return array(
        'id'              => (int) $era['id'],
        'number'          => (int) $era['number'],
        'title'           => $title,
        'label'           => ($title !== '') ? $title : lang(array('string' => 'Version {var:1}', 'vars' => (int) $era['number'])),
        'messages'        => (int) $era['message_count'],
        'from'            => ((int) $era['started_at'] > 0) ? api_time($era['started_at']) : null,
        'to'              => ((int) $era['ended_at'] > 0) ? api_time($era['ended_at']) : null,
        'cleared_by'      => ws_api_person_ref((int) $era['cleared_by'], array((int) $era['cleared_by'] => array('name' => (string) ($era['cleared_by_name'] ?? '')))),
        'last_message_id' => ((int) $era['last_message_id'] > 0) ? (int) $era['last_message_id'] : null,
    );
}

// What ws_api_channel_eras_list() answers.
function ws_api_channel_eras_schema()
{
    return array(
        'eras'      => array(array(
            'id' => 'integer', 'number' => 'integer', 'title' => 'string', 'label' => 'string', 'messages' => 'integer',
            'from' => 'datetime?', 'to' => 'datetime?', 'cleared_by' => 'WorkspacePerson?', 'last_message_id' => 'integer?',
        )),
        'current'   => array('since' => 'datetime?', 'messages' => 'integer'),
        'can_clear' => 'boolean',
    );
}

function ws_api_channel_eras_list($params)
{
    $viewer = ws_api_viewer();
    $channel = ws_api_channel_or_404($viewer, $params['id']);
    $eras = ws_channel_eras($channel['id']);
    $names = ws_people(array_unique(array_filter(array_map(function ($era) { return (int) $era['cleared_by']; }, $eras))));

    foreach ($eras as $index => $era) {
        $eras[$index]['cleared_by_name'] = (string) ($names[(int) $era['cleared_by']]['name'] ?? '');
    }

    // The current conversation, counted as the panel's list counts it
    // (the ws_channel_eras action).
    $span = db_item("SELECT MIN(created_at) AS since, SUM(deleted_at = 0 AND sender_kind <> 'system') AS written
        FROM ws_messages WHERE channel_id = '" . (int) $channel['id'] . "' AND id > '" . ws_channel_floor($channel['id']) . "'");
    $since = is_array($span) ? (int) $span['since'] : 0;

    api_ok(array(
        'eras'      => array_map('ws_api_channel_era_present', $eras),
        'current'   => array('since' => ($since > 0) ? api_time($since) : null, 'messages' => is_array($span) ? (int) $span['written'] : 0),
        'can_clear' => ws_can_clear_channel($viewer, $channel),
    ));
}

/* ---------------------------------------------------------------------------
   The export
   --------------------------------------------------------------------------- */

// What ws_api_channel_export() answers.
function ws_api_channel_export_schema()
{
    return array(
        'channel_id' => 'integer',
        'file_name'  => 'string',
        'from'       => 'date?',
        'to'         => 'date?',
        'messages'   => 'integer',
        'markdown'   => 'string',
    );
}

function ws_api_channel_export($params)
{
    $viewer = ws_api_viewer();
    $channel = ws_api_channel_or_404($viewer, $params['id']);
    $range = ws_channel_export_range((string) ($params['from'] ?? ''), (string) ($params['to'] ?? ''));

    if (!$range['ok']) {
        // The first date alone refused names from; otherwise to, or the
        // two the wrong way round.
        api_fail_validation($range['error'], ws_channel_export_range((string) ($params['from'] ?? ''), '')['ok'] ? 'to' : 'from');
    }

    $export = ws_channel_export_collect($viewer, $channel, $range);

    if (!$export['ok']) {
        api_fail_validation($export['error']);
    }

    $now = time();
    $head = array('channel' => (string) $channel['name'], 'range' => ws_channel_export_range_label($range), 'by' => ws_person_name((int) $viewer['id']), 'at' => $now);
    $base = (defined('URL_SCHEME') && defined('HOSTNAME_SETTING')) ? URL_SCHEME . HOSTNAME_SETTING : '';

    // Written in the audit log as the Export drawer writes it.
    pg_audit('export', 'ws_channel', (int) $channel['id'], array(), array(
        'format'   => 'markdown',
        'from'     => $range['from_date'],
        'to'       => $range['to_date'],
        'messages' => (int) $export['count'],
    ), (string) $channel['name']);

    api_ok(array(
        'channel_id' => (int) $channel['id'],
        'file_name'  => ws_channel_export_name($channel['name']) . '.md',
        'from'       => ($range['from_date'] !== '') ? $range['from_date'] : null,
        'to'         => ($range['to_date'] !== '') ? $range['to_date'] : null,
        'messages'   => (int) $export['count'],
        'markdown'   => ws_channel_export_markdown($head, $export['messages'], $export['decisions'], $export['files'], $export['refs'], $base),
    ));
}
