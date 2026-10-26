<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - the external API's side of asking Claude in a channel: the
 * queue the routine works through, and the channel context it reads.
 *
 * The queue endpoints answer only the application chosen for Claude in the
 * Workspace Settings; any other key gets 403. That application reads only
 * the channels Claude may be asked in, through these endpoints and the
 * ordinary channel ones alike (ws_api_channel_or_404()). A channel or a
 * discussion it was asked in that the owner of the application is not in is
 * read (the context) and answered through the person who asked, while the
 * request is open (ws_claude_channel_asker(), ws_assistant_writer()).
 *
 * The rest is in claude.php beside this file.
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

/**
 * The route rows of the queue and the context.
 *
 * A route whose handler calls ws_api_claude_guard() carries 'mcp' => false:
 * it answers the key of Claude's application alone, never an OAuth
 * connection, and the routine calls it over REST, so as an MCP tool it would
 * refuse every client. The context has no guard and stays a tool.
 *
 * @return array
 */
function ws_claude_api_routes()
{
    return array(

        array(
            'id'          => 'workspace.claude.requests.list',
            'method'      => 'GET',
            'path'        => '/workspace/claude/requests',
            'scope'       => 'workspace:read',
            'handler'     => 'ws_api_claude_requests_list',
            'mcp'         => false,
            'returns'     => array('list' => 'WorkspaceClaudeRequest'),
            'summary'     => 'The requests waiting for Claude',
            'description' => 'For the application chosen for Claude in the Workspace Settings only. status open (the default) is what is waiting to be claimed, oldest first; all is the latest 50 of every state. message.text is the request with every tag spelled out.',
            'params'      => array(
                array('name' => 'status', 'in' => 'query', 'type' => 'enum', 'values' => array('open', 'running', 'answered', 'failed', 'cancelled', 'all'), 'default' => 'open'),
            ),
        ),

        array(
            'id'          => 'workspace.claude.requests.claim',
            'method'      => 'POST',
            'path'        => '/workspace/claude/requests/{id}/claim',
            'scope'       => 'workspace:write',
            'handler'     => 'ws_api_claude_claim',
            'mcp'         => false,
            'dry_run' => true,
            'returns'     => 'WorkspaceClaudeRequest',
            'summary'     => 'Take a request',
            'description' => 'Marks the request as being worked on and leaves an eye on it in the channel. 409 when another run has taken it or it is closed.',
            'params'      => array(
                array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
            ),
        ),

        array(
            'id'          => 'workspace.claude.requests.answer',
            'method'      => 'POST',
            'path'        => '/workspace/claude/requests/{id}/answer',
            'scope'       => 'workspace:write',
            'handler'     => 'ws_api_claude_answer',
            'mcp'         => false,
            'dry_run' => true,
            'returns'     => 'WorkspaceClaudeRequest',
            'summary'     => 'Answer a request',
            'description' => 'Writes the answer under the request, as the application, with the person who asked mentioned (they are told), and swaps the eye for a tick. In a channel or a discussion the owner of the application is not in, it is posted as long as the person who asked may still write there. A request whose design block says from_editor came from the Visual Page Editor: it has no channel, takes text only, and its page change is proposed first with POST /design/pages/{id}/proposals and request_id; proposals made that way for a channel request are shown under this answer. A request whose task block has an id is a run on a task: the words are kept on the task, it takes no tasks, and its changes wait on the task until the person who asked applies or sets them aside. tasks are proposals, not tasks: they wait under the answer until somebody in the channel opens or sets them aside. changes are proposals too, for changing, adding and deleting records: a record is never written here; the person who asked applies the change with one click, with their own rights, and it is kept among the channel\'s decisions. The whole answer is refused (422, the change named) when a change cannot be made: a field that does not exist, a value of the wrong kind, a record that is not there, nothing that differs from what the record holds, an action the kind of record does not take, or a person who asked who may not change that kind of record. Tags such as <@user:12> and <#order:1045> are kept.',
            'params'      => array(
                array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
                array('name' => 'text', 'in' => 'body', 'type' => 'string', 'max_length' => 3900, 'required' => true),
                array('name' => 'tasks', 'in' => 'body', 'type' => 'list', 'of' => array('title' => 'string', 'description' => 'string', 'assignees' => 'integer[]', 'due_date' => 'string', 'priority' => 'string'), 'max_items' => 10, 'description' => 'Proposed tasks, as objects of {title, description, assignees (user ids), due_date (YYYY-MM-DD), priority (low, normal, high, urgent)}.'),
                ws_api_changes_param('the person who asked'),
            ),
        ),

        array(
            'id'          => 'workspace.claude.requests.fail',
            'method'      => 'POST',
            'path'        => '/workspace/claude/requests/{id}/fail',
            'scope'       => 'workspace:write',
            'handler'     => 'ws_api_claude_fail',
            'mcp'         => false,
            'dry_run' => true,
            'returns'     => 'WorkspaceClaudeRequest',
            'summary'     => 'Give a request up',
            'description' => 'Closes the request as not done, writes the reason under it for the person who asked and takes the eye back.',
            'params'      => array(
                array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
                array('name' => 'reason', 'in' => 'body', 'type' => 'string', 'max_length' => 250, 'required' => true),
            ),
        ),

        array(
            'id'          => 'workspace.claude.change_types',
            'method'      => 'GET',
            'path'        => '/workspace/claude/requests/{id}/change-types',
            'scope'       => 'workspace:read',
            'handler'     => 'ws_api_claude_change_types',
            'mcp'         => false,
            'returns'     => array('list' => 'WorkspaceChangeType'),
            'summary'     => 'What a request\'s changes may set',
            'description' => 'The kinds of record the person who asked may have changes proposed for, from the table the answer\'s changes are checked against: each kind\'s actions, its fields with their kind of value (text, long, money in minor units, bool, int, enum, email, date, datetime, keywords, decimal, code, lines, ids, role), their limits and labels, the fields only a new record takes, and what a new one needs.',
            'params'      => array(
                array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
            ),
        ),

        array(
            'id'          => 'workspace.claude.record',
            'method'      => 'GET',
            'path'        => '/workspace/claude/requests/{id}/records/{type}/{record_id}',
            'scope'       => 'workspace:read',
            'handler'     => 'ws_api_claude_record',
            'mcp'         => false,
            'returns'     => 'WorkspaceRecord',
            'summary'     => 'A record as a change is laid against it',
            'description' => 'One record, read with the rights of the person who asked: its tag, name and state, every field a change may set with what the record holds in it now (money in minor units, the names the changes of the answer use), and an order\'s lines. A change whose from differs from this at the moment it is applied is not applied. The key must also hold the scope the kind is read with elsewhere in the API (orders:read for an order, products:read for a product or its stock, calendars:read for a calendar event...), or the answer is 403 insufficient_scope. 404 for a record the person may not see.',
            'params'      => array(
                array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
                array('name' => 'type', 'in' => 'path', 'type' => 'string', 'max_length' => 32, 'required' => true, 'description' => 'A kind from GET /workspace/claude/requests/{id}/change-types.'),
                array('name' => 'record_id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
            ),
        ),

        array(
            'id'          => 'workspace.channels.context',
            'method'      => 'GET',
            'path'        => '/workspace/channels/{id}/context',
            'scope'       => 'workspace:read',
            'handler'     => 'ws_api_channel_context',
            'returns'     => 'WorkspaceChannelContext',
            'summary'     => 'What a channel is about, in one call',
            'description' => 'The channel\'s summary, its latest decisions and notes, its latest messages (oldest first, tags spelled out) and its open tasks: what an assistant reads before it answers in the channel. For a discussion, thread holds the channel it was started in and the message it talks over (message is null when that was deleted); it is null for a channel. The application chosen for Claude also reads a channel or a discussion its owner is not in while a request to Claude asked there (or a run on one of its tasks) is open: as the person who asked reads it.',
            'params'      => array(
                array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
                array('name' => 'limit', 'in' => 'query', 'type' => 'int', 'min' => 1, 'max' => 100, 'default' => 50),
            ),
        ),

    );
}

/**
 * The owner of the application Claude works through, or a 403 for any other
 * application.
 *
 * @return array the viewer
 */
function ws_api_claude_guard()
{
    if (!ws_claude_schema_ready() || !ws_claude_config()['enabled']) {
        api_fail(403, 'forbidden', lang('Claude is not switched on on this site.'));
    }

    if (!ws_api_is_claude()) {
        api_fail(403, 'forbidden', lang('Only the application Claude works through may use this endpoint.'));
    }

    return ws_api_viewer();
}

/**
 * Is the call Claude's: the key of the application chosen for Claude, used
 * as itself? A person signed in through that application - a team device or
 * an OAuth connection reports the application it came through - is that
 * person, not Claude: the routine signs in with the key and secret, and what
 * Claude may read and answer (ws_claude_channel_asker(), ws_assistant_writer())
 * must not open to every session of the application.
 *
 * @return bool
 */
function ws_api_is_claude()
{
    return function_exists('ws_claude_schema_ready') && ws_claude_schema_ready()
        && (ws_claude_config()['app_id'] > 0) && (ws_api_app_id() === (int) ws_claude_config()['app_id'])
        && (!function_exists('api_current_device') || (api_current_device() === null));
}

function ws_api_claude_request_present($viewer, $row)
{
    $message = ws_message($row['message_id']);
    $channel = ws_channel($row['channel_id']);
    $body = ($message && ((int) $message['deleted_at'] === 0)) ? (string) $message['body'] : '';

    // A request written as a reply (to one of Claude's answers, say): the
    // message it answers.
    $parent = ($message && ((int) $message['parent_id'] > 0)) ? ws_message($message['parent_id']) : null;
    $parent = ($parent && ((int) $parent['deleted_at'] === 0)) ? $parent : null;

    // A request written in a note (4.110): the line that asked, and the note
    // as it is now, figures worked out, as the context.
    $note = null;

    if ((int) ($row['note_id'] ?? 0) > 0) {
        $body = (string) $row['note_text'];
        $note = function_exists('ws_note') ? ws_note($row['note_id']) : null;
    }

    // A request from the Visual Page Editor (5.70): the words that asked, and
    // the page and element it is about. The page is read with
    // GET /design/pages/{id}?request={id}, which shows it as the editor had
    // it when it asked.
    $design = array('page_id' => 0, 'page_name' => '', 'node_id' => '', 'scope' => '', 'from_editor' => false);

    if (((int) ($row['page_id'] ?? 0) > 0) && function_exists('ws_design_ai') && ws_design_ai()) {
        $page = db_item("SELECT page_name FROM page WHERE page_id = '" . (int) $row['page_id'] . "' LIMIT 1");
        $from_editor = pg_design_ai_is_design_request($row);

        if ($from_editor) {
            $body = (string) $row['note_text'];
        }

        $design = array(
            'page_id'     => (int) $row['page_id'],
            'page_name'   => is_array($page) ? (string) $page['page_name'] : '',
            'node_id'     => (string) $row['node_id'],
            'scope'       => ((string) $row['node_id'] !== '') ? 'node' : 'page',
            'from_editor' => $from_editor,
        );
    }

    // A run on a task (delegation.php): the task as the person who asked sees
    // it, its latest notes, and what the assistants were asked and answered
    // on it before; message.text is the instruction, "" when the task itself
    // says what to do.
    $task = ws_api_claude_task_present($row);

    if ($task['id'] > 0) {
        $body = (string) $row['note_text'];
    }

    $kind = '';

    if ($channel) {
        $kind = (string) $channel['kind'];
    } elseif ((int) ($row['note_id'] ?? 0) > 0) {
        $kind = 'note';
    } elseif ((int) ($row['task_id'] ?? 0) > 0) {
        $kind = 'task';
    }

    return array(
        'id'               => (int) $row['id'],
        'status'           => (string) $row['status'],
        'channel'          => array(
            'id'   => (int) $row['channel_id'],
            'name' => $channel ? (string) $channel['name'] : '',
            'kind' => $kind,
        ),
        'message'          => array(
            'id'            => (int) $row['message_id'],
            'text'          => ($body !== '') ? ws_plain_text($viewer, $body) : '',
            'body'          => $body,
            'reply_to_id'   => $parent ? (int) $parent['id'] : null,
            'reply_to_text' => $parent ? ws_plain_text($viewer, (string) $parent['body']) : '',
        ),
        'note'             => array(
            'id'    => $note ? (int) $note['id'] : 0,
            'title' => $note ? (string) $note['title'] : '',
            'body'  => $note ? ws_calc_plain((string) $note['body']) : '',
        ),
        'design'           => $design,
        'task'             => $task,
        'requested_by'     => array('user_id' => (int) $row['requested_by'], 'name' => ws_person_name($row['requested_by'])),
        'reply_message_id' => ((int) $row['reply_message_id'] > 0) ? (int) $row['reply_message_id'] : null,
        'error'            => (string) $row['error'],
        'created_at'       => api_time($row['created_at']),
        'claimed_at'       => ((int) $row['claimed_at'] > 0) ? api_time($row['claimed_at']) : null,
    );
}

// What ws_api_claude_request_present() returns.
function ws_api_claude_request_schema()
{
    return array(
        'id'               => 'integer',
        'status'           => 'string',
        'channel'          => array('id' => 'integer', 'name' => 'string', 'kind' => 'string'),
        'message'          => array('id' => 'integer', 'text' => 'string', 'body' => 'string', 'reply_to_id' => 'integer?', 'reply_to_text' => 'string'),
        'note'             => array('id' => 'integer', 'title' => 'string', 'body' => 'string'),
        'design'           => array('page_id' => 'integer', 'page_name' => 'string', 'node_id' => 'string', 'scope' => 'string', 'from_editor' => 'boolean'),
        'task'             => ws_api_claude_task_schema(),
        'requested_by'     => array('user_id' => 'integer', 'name' => 'string'),
        'reply_message_id' => 'integer?',
        'error'            => 'string',
        'created_at'       => 'string',
        'claimed_at'       => 'string?',
    );
}

/**
 * The fields of a kind of record, as ws_api_claude_change_type_present()
 * lists them: name, kind of value, label, limit, values.
 *
 * @param array $list name => [kind, limit, label, column]
 * @return array[]
 */
/**
 * The task block of a request: filled for a run on a task, empty otherwise.
 * The task is read with the rights of the person who asked; channel_id is
 * set only where Claude may be asked in the task's channel and that person
 * reads it.
 *
 * @param array $row
 * @return array
 */
function ws_api_claude_task_present($row)
{
    $out = array(
        'id'          => 0,
        'number'      => '',
        'title'       => '',
        'description' => '',
        'status'      => '',
        'priority'    => '',
        'start_date'  => null,
        'due_date'    => null,
        'channel_id'  => 0,
        'handed_over' => false,
        'assignees'   => array(),
        'notes'       => array(),
        'earlier'     => array(),
    );

    if (((int) ($row['task_id'] ?? 0) <= 0) || !function_exists('ws_task_delegation_context')) {
        return $out;
    }

    $asker = ws_change_viewer_for_id((int) $row['requested_by']);
    $context = $asker ? ws_task_delegation_context($asker, $row) : null;

    if (!$context) {
        return $out;
    }

    $task = $context['task'];
    $read = ws_ai_task_present($asker, $task);
    $channel = ((int) $task['channel_id'] > 0) ? ws_channel($task['channel_id']) : null;
    $earlier = array();

    foreach ($context['earlier'] as $item) {
        $earlier[] = array(
            'assistant'   => $item['agent'],
            'asked_by'    => $item['asked_by'],
            'instruction' => $item['instruction'],
            'answer'      => $item['answer'],
            'error'       => $item['error'],
        );
    }

    return array(
        'id'          => (int) $task['id'],
        'number'      => (string) $read['number'],
        'title'       => (string) $read['title'],
        'description' => (string) $read['description'],
        'status'      => (string) $read['status'],
        'priority'    => (string) $read['priority'],
        'start_date'  => $read['start_date'],
        'due_date'    => $read['due_date'],
        'channel_id'  => ($channel && ws_claude_channel_allowed($channel) && ws_can_read_channel($asker, $channel)) ? (int) $channel['id'] : 0,
        'handed_over' => (bool) $context['delegated'],
        'assignees'   => array_values($read['assignees']),
        'notes'       => array_values($read['notes']),
        'earlier'     => $earlier,
    );
}

// What ws_api_claude_task_present() returns.
function ws_api_claude_task_schema()
{
    return array(
        'id'          => 'integer',
        'number'      => 'string',
        'title'       => 'string',
        'description' => 'string',
        'status'      => 'string',
        'priority'    => 'string',
        'start_date'  => 'string?',
        'due_date'    => 'string?',
        'channel_id'  => 'integer',
        'handed_over' => 'boolean',
        'assignees'   => array('string'),
        'notes'       => array('string'),
        'earlier'     => array(array('assistant' => 'string', 'asked_by' => 'string', 'instruction' => 'string', 'answer' => 'string', 'error' => 'string')),
    );
}

function ws_api_claude_change_fields_present($list)
{
    $out = array();

    foreach ((array) $list as $name => $field) {
        $limit = null;
        $values = array();

        if (in_array($field[0], array('enum', 'role'), true)) {
            $values = array_values((array) $field[1]);
        } elseif ($field[0] === 'int') {
            $limit = array('min' => (int) $field[1][0], 'max' => (int) $field[1][1]);
        } elseif ($field[0] === 'decimal') {
            $limit = array('min' => $field[1][0], 'max' => $field[1][1], 'decimals' => (int) $field[1][2], 'may_be_empty' => !empty($field[1][3]));
        } elseif (in_array($field[0], array('lines', 'erp_lines'), true)) {
            // A document's lines: max_length is a line's description.
            $limit = array('max_items' => (int) $field[1][0], 'max_length' => (int) $field[1][1]);
        } elseif ($field[0] === 'ids') {
            $values = array((string) $field[1]);
        } elseif ($field[0] === 'money') {
            // The column's ceiling in minor units, when the column has one.
            $limit = ((int) $field[1] > 0) ? array('max' => (int) $field[1]) : null;
        } elseif (is_numeric($field[1]) && ((int) $field[1] > 0)) {
            $limit = array('max_length' => (int) $field[1]);
        }

        $out[] = array(
            'name'   => (string) $name,
            'kind'   => (string) $field[0],
            'label'  => (string) $field[2],
            'limit'  => $limit,
            'values' => $values,
        );
    }

    return $out;
}

/**
 * One kind of record a change may be proposed for, as the API shows it.
 *
 * @param string $key
 * @param array  $type ws_change_types() row
 * @return array
 */
function ws_api_claude_change_type_present($key, $type)
{
    return array(
        'type'          => (string) $key,
        'label'         => (string) $type['label'],
        'actions'       => array_values((array) $type['actions']),
        'fields'        => ws_api_claude_change_fields_present($type['fields']),
        'create_fields' => ws_api_claude_change_fields_present($type['create'] ?? array()),
        'member_fields' => ws_api_claude_change_fields_present($type['members'] ?? array()),
        'required'      => array_values((array) ($type['required'] ?? array())),
    );
}

function ws_api_claude_change_type_schema()
{
    $field = array(array('name' => 'string', 'kind' => 'string', 'label' => 'string', 'limit' => 'object?', 'values' => 'string[]'));

    return array(
        'type'          => 'string',
        'label'         => 'string',
        'actions'       => 'string[]',
        'fields'        => $field,
        'create_fields' => $field,
        'member_fields' => $field,
        'required'      => 'string[]',
    );
}

/**
 * A record as the API shows it for a change (ws_ai_record_present()).
 *
 * @param array $read
 * @return array
 */
function ws_api_claude_record_present($read)
{
    return array(
        'tag'    => (string) ($read['tag'] ?? ''),
        'name'   => (string) ($read['name'] ?? ''),
        'about'  => (string) ($read['about'] ?? ''),
        'status' => (string) ($read['status'] ?? ''),
        'fields' => isset($read['fields']) ? (object) $read['fields'] : null,
        'lines'  => isset($read['lines']) ? array_values($read['lines']) : null,
    );
}

// lines is null for every record but an order, the only one read with its
// lines (ws_ai_record_present()).
function ws_api_claude_record_schema()
{
    return array(
        'tag'    => 'string',
        'name'   => 'string',
        'about'  => 'string',
        'status' => 'string',
        'fields' => 'object?',
        'lines'  => 'WorkspaceRecordLine[]?',
    );
}

// One line of a document a record reads (ws_api_claude_record_schema()).
function ws_api_claude_record_line_schema()
{
    return array('product' => 'string?', 'name' => 'string', 'quantity' => 'integer', 'unit_price' => 'string', 'line_tax' => 'string');
}

/**
 * The person a request was asked by, as the changes check them, or a 404.
 *
 * @param array $row ws_ai_requests
 * @return array viewer
 */
function ws_api_claude_asker($row)
{
    $asker = function_exists('ws_change_viewer_for_id') ? ws_change_viewer_for_id((int) $row['requested_by']) : null;

    if (!$asker) {
        api_fail_not_found(lang('Request'));
    }

    return $asker;
}

function ws_api_claude_change_types($params)
{
    ws_api_claude_guard();

    $row = ws_api_claude_request_or_404($params['id']);
    $asker = ws_api_claude_asker($row);
    $out = array();

    foreach (ws_change_types() as $key => $type) {
        if (ws_change_allowed($asker, $key, ($key === 'channel') ? (int) $row['channel_id'] : 0)) {
            $out[] = ws_api_claude_change_type_present($key, $type);
        }
    }

    api_ok_list($out, count($out));
}

function ws_api_claude_record($params)
{
    ws_api_claude_guard();

    $row = ws_api_claude_request_or_404($params['id']);
    $asker = ws_api_claude_asker($row);
    $type = (string) $params['type'];
    $record_id = (int) $params['record_id'];
    $types = ws_change_types();

    if (!isset($types[$type])) {
        api_fail_not_found(lang('Record'));
    }

    // The route itself asks only workspace:read: the key must also hold the
    // scope its kind of record is read with (ws_ai_type_scope(), the one the
    // API search and Pinegrap AI ask), or this route would read an order or
    // a calendar event the key cannot read anywhere else. Asked before the
    // record is looked up, so the refusal says nothing about the record.
    api_require_scope(ws_ai_type_scope((string) $types[$type]['tag']));

    // Read as a reader: the ERP's read-only right reads an ERP account here
    // as on the ERP screens, while change_types leaves it out.
    if (!ws_change_allowed(ws_change_reader($asker), $type, $record_id)) {
        api_fail_not_found(lang('Record'));
    }

    $read = ws_ai_record_present($asker, $type, $record_id);

    if (isset($read['error'])) {
        api_fail_not_found(lang('Record'));
    }

    api_ok(ws_api_claude_record_present($read));
}

function ws_api_claude_request_or_404($id)
{
    // Claude's requests only: the ones to Pinegrap AI are answered by the
    // site itself (ai.php).
    $row = db_item("SELECT * FROM ws_ai_requests WHERE id = '" . (int) $id . "'" . ws_claude_only());

    if (!is_array($row)) {
        api_fail_not_found(lang('Request'));
    }

    return $row;
}

function ws_api_claude_requests_list($params)
{
    $viewer = ws_api_claude_guard();

    ws_claude_watchdog();

    $status = (string) ($params['status'] ?? 'open');

    if ($status === 'all') {
        $rows = (array) db_items("SELECT * FROM ws_ai_requests WHERE 1 = 1" . ws_claude_only() . " ORDER BY id DESC LIMIT 50");
    } else {
        $where = ($status === 'open') ? "status IN ('queued', 'sent')" : "status = '" . e($status) . "'";
        $rows = (array) db_items("SELECT * FROM ws_ai_requests WHERE " . $where . ws_claude_only() . " ORDER BY id ASC LIMIT 50");
    }

    $out = array();

    foreach ($rows as $row) {
        $out[] = ws_api_claude_request_present($viewer, $row);
    }

    api_ok_list($out, 50);
}

function ws_api_claude_claim($params)
{
    $viewer = ws_api_claude_guard();
    $row = ws_api_claude_request_or_404($params['id']);

    if ($row['status'] === 'running') {
        api_fail(409, 'already_claimed', lang('Another run is working on this request.'));
    }

    if (!in_array($row['status'], array('queued', 'sent'), true)) {
        api_fail(409, 'closed', lang('This request is already closed.'));
    }

    api_dry_run_stop('claimed', 'workspace_claude_request', array('id' => (int) $row['id']));

    // One run wins when two reach for the same request.
    db("UPDATE ws_ai_requests SET status = 'running', claimed_at = '" . time() . "'
        WHERE id = '" . (int) $row['id'] . "' AND status IN ('queued', 'sent')");

    if (mysqli_affected_rows(db::$con) < 1) {
        api_fail(409, 'already_claimed', lang('Another run is working on this request.'));
    }

    ws_claude_react((int) $row['message_id'], '👀', true);

    api_ok(ws_api_claude_request_present($viewer, ws_api_claude_request_or_404($row['id'])));
}

/**
 * The tasks an answer proposes, checked.
 *
 * @param mixed $tasks
 * @return array[] rows for ws_ai_drafts
 */
function ws_api_claude_drafts_input($tasks)
{
    $team = array_map('intval', ws_team_ids());
    $out = array();

    foreach (array_slice(is_array($tasks) ? $tasks : array(), 0, 10) as $task) {
        if (!is_array($task)) {
            api_fail_validation(lang('Each proposed task is an object with a title.'), 'tasks');
        }

        $title = trim(mb_substr(preg_replace('/\s+/u', ' ', (string) ($task['title'] ?? '')), 0, 255));

        if ($title === '') {
            api_fail_validation(lang('A proposed task needs a title.'), 'tasks');
        }

        $due = (string) ($task['due_date'] ?? '');
        $due = (preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/', $due) && (strtotime($due . ' 12:00:00') !== false)) ? $due : '';
        $priority = (string) ($task['priority'] ?? 'normal');
        $people = array_values(array_intersect(array_map('intval', (array) ($task['assignees'] ?? array())), $team));

        $out[] = array(
            'title'       => $title,
            'description' => ws_tokens_normalise(trim(mb_substr((string) ($task['description'] ?? ''), 0, 4000))),
            'assignees'   => implode(',', array_slice(array_unique($people), 0, 20)),
            'due_date'    => $due,
            'priority'    => in_array($priority, array('low', 'normal', 'high', 'urgent'), true) ? $priority : 'normal',
        );
    }

    return $out;
}

/**
 * The channel of a request, open to Claude, and who the answer goes into it
 * through: the owner of Claude's application, or the person who asked where
 * the owner is not in the channel or the discussion (ws_assistant_writer()).
 *
 * @param array $viewer the owner of the application
 * @param array $row
 * @return array channel, writer
 */
function ws_api_claude_channel($viewer, $row)
{
    $channel = ws_channel($row['channel_id']);

    if (!$channel) {
        api_fail_not_found(lang('Channel'));
    }

    if (!ws_claude_channel_allowed($channel)) {
        api_fail(403, 'forbidden', lang('Claude may not be asked in this channel.'));
    }

    $writer = ws_assistant_writer($viewer, ws_change_viewer_for_id((int) $row['requested_by']), $channel);

    if (!$writer) {
        api_fail(403, 'forbidden', lang('The answer cannot be posted there any more: the person who asked has left that channel or discussion, or it was archived or concluded.'));
    }

    return array('channel' => $channel, 'writer' => $writer);
}

function ws_api_claude_answer($params)
{
    $viewer = ws_api_claude_guard();
    $row = ws_api_claude_request_or_404($params['id']);

    if (!in_array($row['status'], array('queued', 'sent', 'running'), true)) {
        api_fail(409, 'closed', lang('This request is already closed.'));
    }

    // Asked from the Visual Page Editor: the words go on the request, where
    // the editor shows them beside the page proposals made with
    // POST /design/pages/{id}/proposals and this request's id.
    if (((int) ($row['page_id'] ?? 0) > 0) && function_exists('ws_design_ai') && ws_design_ai() && pg_design_ai_is_design_request($row)) {
        if (!empty($params['tasks']) || !empty($params['changes'])) {
            api_fail_validation(lang('A request from the Visual Page Editor is answered with text only; the page change is proposed with POST /design/pages/{id}/proposals.'), empty($params['tasks']) ? 'changes' : 'tasks');
        }

        api_dry_run_stop('answered', 'workspace_claude_request', array('id' => (int) $row['id'], 'origin' => 'editor'));

        pg_design_ai_request_answer($row, ws_tokens_normalise(trim((string) $params['text'])));

        ws_api_log(lang('Claude answered a request from the Visual Page Editor'));

        api_ok(ws_api_claude_request_present($viewer, ws_api_claude_request_or_404($row['id'])));
    }

    // Asked in a note: the answer is kept for the note, written into it under
    // the line that asked when somebody has the note open.
    if ((int) ($row['note_id'] ?? 0) > 0) {
        if (!empty($params['tasks']) || !empty($params['changes'])) {
            api_fail_validation(lang('A request from a note is answered with text only: no tasks, no changes.'), empty($params['tasks']) ? 'changes' : 'tasks');
        }

        api_dry_run_stop('answered', 'workspace_claude_request', array('id' => (int) $row['id'], 'origin' => 'note'));

        $now = time();

        db("UPDATE ws_ai_requests SET status = 'answered', note_reply = '" . e(ws_tokens_normalise(trim((string) $params['text']))) . "',
                answered_at = '" . $now . "', claimed_at = IF(claimed_at = 0, '" . $now . "', claimed_at), error = ''
            WHERE id = '" . (int) $row['id'] . "'");

        ws_api_log(lang('Claude answered a request written in a note'));

        // The answer waits in the request until somebody has the note open;
        // the one who asked is told it is there.
        ws_notify((int) $row['requested_by'], 'note_answer', array('note_id' => (int) $row['note_id'], 'actor_id' => 0));

        api_ok(ws_api_claude_request_present($viewer, ws_api_claude_request_or_404($row['id'])));
    }

    // A run on a task (delegation.php): the words are kept on the run and
    // shown on the task, with the changes it proposes waiting under them for
    // the person who asked, who is told.
    if ((int) ($row['task_id'] ?? 0) > 0) {
        if (!empty($params['tasks'])) {
            api_fail_validation(lang('A run on a task proposes no tasks: its changes wait on the task.'), 'tasks');
        }

        $changes = ws_changes_input($params['changes'] ?? array(), (int) $row['requested_by'], 0, (int) $row['task_id']);

        if (!$changes['ok']) {
            api_fail_validation($changes['error'], 'changes');
        }

        api_dry_run_stop('answered', 'workspace_claude_request', array('id' => (int) $row['id'], 'origin' => 'task', 'changes' => count($changes['rows'])));

        if (ws_task_delegation_answer($row, (string) $params['text'])) {
            ws_changes_store($row, 0, $changes['rows']);
            ws_api_log(lang(array('string' => 'Claude answered on task {var:1}', 'vars' => ws_task_number($row['task_id']))));
        }

        api_ok(ws_api_claude_request_present($viewer, ws_api_claude_request_or_404($row['id'])));
    }

    $place = ws_api_claude_channel($viewer, $row);
    $channel = $place['channel'];
    $drafts = ws_api_claude_drafts_input($params['tasks'] ?? array());

    // Checked before anything is written: a change that cannot be made
    // refuses the answer, so it comes back with the change fixed or left out.
    $changes = ws_changes_input($params['changes'] ?? array(), (int) $row['requested_by'], (int) $row['channel_id']);

    if (!$changes['ok']) {
        api_fail_validation($changes['error'], 'changes');
    }

    // The person who asked is told, whatever the answer says.
    $text = trim((string) $params['text']);
    $mention = '<@user:' . (int) $row['requested_by'] . '>';

    if (strpos($text, $mention) === false) {
        $text = $mention . ' ' . $text;
    }

    $options = array(
        'parent_id' => (int) $row['message_id'],
        'app_id'    => ws_api_app_id(),
    );

    // The checks ws_message_send() makes, made first so that a dry run meets
    // the same refusals.
    $checked = ws_message_send_check($place['writer'], $channel, $text, $options);

    if (!$checked['ok']) {
        api_fail_validation($checked['error'], 'text');
    }

    api_dry_run_stop('answered', 'workspace_claude_request', array(
        'id'         => (int) $row['id'],
        'origin'     => 'channel',
        'channel_id' => (int) $channel['id'],
        'tasks'      => count($drafts),
        'changes'    => count($changes['rows']),
    ));

    $sent = ws_message_send($place['writer'], $channel, $text, $options);

    if (!$sent['ok']) {
        api_fail_validation($sent['error'], 'text');
    }

    $now = time();

    foreach ($drafts as $draft) {
        db("INSERT INTO ws_ai_drafts (request_id, channel_id, message_id, title, description, assignees, due_date, priority, created_at)
            VALUES (
                '" . (int) $row['id'] . "',
                '" . (int) $channel['id'] . "',
                '" . (int) $sent['message_id'] . "',
                '" . e($draft['title']) . "',
                '" . e($draft['description']) . "',
                '" . e($draft['assignees']) . "',
                " . (($draft['due_date'] !== '') ? "'" . e($draft['due_date']) . "'" : 'NULL') . ",
                '" . e($draft['priority']) . "',
                '" . $now . "')");
    }

    ws_changes_store($row, (int) $sent['message_id'], $changes['rows']);

    // Page proposals made for this request go under the answer.
    if (function_exists('ws_design_ai') && ws_design_ai()) {
        pg_design_ai_attach((int) $row['id'], (int) $channel['id'], (int) $sent['message_id']);
    }

    db("UPDATE ws_ai_requests SET status = 'answered', reply_message_id = '" . (int) $sent['message_id'] . "',
            answered_at = '" . $now . "', claimed_at = IF(claimed_at = 0, '" . $now . "', claimed_at), error = ''
        WHERE id = '" . (int) $row['id'] . "'");

    ws_claude_react((int) $row['message_id'], '👀', false);
    ws_claude_react((int) $row['message_id'], '✅', true);

    ws_api_log(lang(array('string' => 'Claude answered a request in #{var:1}', 'vars' => $channel['name'])));

    api_ok(ws_api_claude_request_present($viewer, ws_api_claude_request_or_404($row['id'])));
}

function ws_api_claude_fail($params)
{
    $viewer = ws_api_claude_guard();
    $row = ws_api_claude_request_or_404($params['id']);

    if (!in_array($row['status'], array('queued', 'sent', 'running'), true)) {
        api_fail(409, 'closed', lang('This request is already closed.'));
    }

    $reason = trim(mb_substr(preg_replace('/\s+/u', ' ', (string) $params['reason']), 0, 250));
    $reply_id = 0;
    $channel = ((int) ($row['note_id'] ?? 0) > 0) ? null : ws_channel($row['channel_id']);

    // Written the way an answer is (ws_api_claude_channel()): through the
    // person who asked where the owner of the application is not in the room.
    $writer = ($channel && ws_claude_channel_allowed($channel))
        ? ws_assistant_writer($viewer, ws_change_viewer_for_id((int) $row['requested_by']), $channel)
        : null;
    $told = (bool) $writer;

    api_dry_run_stop('failed', 'workspace_claude_request', array('id' => (int) $row['id'], 'told_in_channel' => $told));

    // The reason goes to the person who asked, where they asked. A channel
    // that closed to Claude meanwhile keeps only the state on the request.
    if ($told) {
        $sent = ws_message_send($writer, $channel, '<@user:' . (int) $row['requested_by'] . '> ' . $reason, array(
            'parent_id' => (int) $row['message_id'],
            'app_id'    => ws_api_app_id(),
        ));

        $reply_id = $sent['ok'] ? (int) $sent['message_id'] : 0;
    }

    db("UPDATE ws_ai_requests SET status = 'failed', error = '" . e($reason) . "', reply_message_id = '" . $reply_id . "',
            answered_at = '" . time() . "'
        WHERE id = '" . (int) $row['id'] . "' AND status IN ('queued', 'sent', 'running')");

    // A run on a task: the person who asked is told it ended undone.
    if (((int) ($row['task_id'] ?? 0) > 0) && (mysqli_affected_rows(db::$con) > 0) && function_exists('ws_task_delegation_told')) {
        ws_task_delegation_told($row, 'failed');
    }

    ws_claude_react((int) $row['message_id'], '👀', false);

    api_ok(ws_api_claude_request_present($viewer, ws_api_claude_request_or_404($row['id'])));
}

/**
 * The reader and the channel of a read an assistant makes before it answers
 * (the channel context, the channel's customer). Claude reads a channel or a
 * discussion the owner of its application is not in through the person who
 * asked it there, as that person reads it: their tags, their tasks, their
 * rights (ws_claude_channel_asker()). Every other channel is read the way
 * every endpoint reads one, by the owner, with the 404 of every channel route
 * and Claude's own limit (ws_api_channel_or_404()).
 *
 * @param int $id the channel
 * @return array the viewer, the channel row
 */
function ws_api_assistant_channel_reader($id)
{
    $owner = ws_api_viewer();
    $channel = ws_channel((int) $id);

    $viewer = ($channel && ws_api_is_claude() && ws_claude_channel_allowed($channel) && !ws_app_reads_channel($owner, $channel))
        ? ws_claude_channel_asker($channel)
        : null;

    if (!$viewer) {
        $viewer = $owner;
        $channel = ws_api_channel_or_404($viewer, $id);
    }

    return array($viewer, $channel);
}

function ws_api_channel_context($params)
{
    list($viewer, $channel) = ws_api_assistant_channel_reader($params['id']);

    $limit = max(1, min(100, (int) ($params['limit'] ?? 50)));

    $rows = ws_messages_page($channel['id'], 0, $limit);
    $decided = (array) db_items("SELECT * FROM ws_messages
        WHERE channel_id = '" . (int) $channel['id'] . "' AND kind IN ('decision', 'note') AND deleted_at = 0
        ORDER BY marked_at DESC, id DESC
        LIMIT 10");

    // A discussion talks over one message of a channel: that message and the
    // channel it was written in, which whoever reads the discussion reads too
    // (ws_can_read_channel()). Pinegrap AI is given the same (ws_ai_start()).
    $parent = ws_channel_is_thread($channel) ? ws_thread_parent_channel($channel) : null;
    $thread_row = $parent ? ws_thread($channel['id']) : null;
    $about = $thread_row ? ws_message((int) $thread_row['message_id']) : null;
    $about = ($about && ((int) $about['deleted_at'] === 0)) ? $about : null;

    $tokens = ws_tokens((string) $channel['summary']);

    foreach (array_merge($rows, $decided, $about ? array($about) : array()) as $row) {
        $tokens = array_merge($tokens, ws_tokens($row['body']));
    }

    $refs = ws_refs_resolve($viewer, $tokens);

    $author = function ($row) {
        if ($row['sender_kind'] === 'user') {
            return ws_person_name($row['sender_id']);
        }

        return ($row['sender_kind'] === 'app') ? (string) ws_app_sender((int) $row['sender_id'])['name'] : '';
    };

    $thread = $parent ? ws_api_context_thread_present($parent, $about, $about ? $author($about) : '', $about ? ws_plain_text($viewer, $about['body'], $refs) : '') : null;

    $messages = array();

    foreach ($rows as $row) {
        if ((int) $row['deleted_at'] > 0) {
            continue;
        }

        $messages[] = array(
            'id'          => (int) $row['id'],
            'parent_id'   => ((int) $row['parent_id'] > 0) ? (int) $row['parent_id'] : null,
            'kind'        => (string) $row['kind'],
            'sender_kind' => (string) $row['sender_kind'],
            'author'      => $author($row),
            'text'        => ws_plain_text($viewer, $row['body'], $refs),
            'task_id'     => ((int) $row['task_id'] > 0) ? (int) $row['task_id'] : null,
            'created_at'  => api_time($row['created_at']),
        );
    }

    $decisions = array();

    foreach ($decided as $row) {
        $decisions[] = array(
            'id'         => (int) $row['id'],
            'kind'       => (string) $row['kind'],
            'author'     => $author($row),
            'text'       => ws_plain_text($viewer, $row['body'], $refs),
            'created_at' => api_time($row['created_at']),
        );
    }

    $tasks = array();

    foreach (ws_tasks_list($viewer, array('scope' => 'channel', 'channel_id' => (int) $channel['id'], 'status' => 'open', 'limit' => 30)) as $brief) {
        $people = array();

        foreach ((array) $brief['assignees'] as $person) {
            $people[] = array('user_id' => (int) $person['id'], 'name' => (string) $person['name']);
        }

        $tasks[] = array(
            'id'        => (int) $brief['id'],
            'number'    => (string) $brief['number'],
            'title'     => (string) $brief['title'],
            'status'    => (string) $brief['status'],
            'due_date'  => $brief['due_date'],
            'assignees' => $people,
        );
    }

    api_ok(array(
        'channel'    => array(
            'id'      => (int) $channel['id'],
            'name'    => (string) $channel['name'],
            'kind'    => (string) $channel['kind'],
            'topic'   => (string) $channel['topic'],
            'summary' => ws_plain_text($viewer, (string) $channel['summary'], $refs),
        ),
        'thread'     => $thread,
        'decisions'  => $decisions,
        'messages'   => $messages,
        'open_tasks' => $tasks,
    ));
}

/**
 * What a discussion is about, in its context: the channel it was started in
 * and the message it talks over, null when that message was deleted.
 *
 * @param array      $parent the channel row the discussion was started in
 * @param array|null $about  the ws_messages row it talks over
 * @param string     $author who wrote that message, in words
 * @param string     $text   its words as the reader reads them
 * @return array
 */
function ws_api_context_thread_present($parent, $about, $author, $text)
{
    return array(
        'channel' => array(
            'id'   => (int) $parent['id'],
            'name' => (string) $parent['name'],
            'kind' => (string) $parent['kind'],
        ),
        'message' => is_array($about) ? array(
            'id'          => (int) $about['id'],
            'kind'        => (string) $about['kind'],
            'sender_kind' => (string) $about['sender_kind'],
            'author'      => (string) $author,
            'text'        => (string) $text,
            'task_id'     => ((int) $about['task_id'] > 0) ? (int) $about['task_id'] : null,
            'created_at'  => api_time($about['created_at']),
        ) : null,
    );
}

// What ws_api_channel_context() returns.
function ws_api_channel_context_schema()
{
    return array(
        'channel'    => array('id' => 'integer', 'name' => 'string', 'kind' => 'string', 'topic' => 'string', 'summary' => 'string'),
        'thread'     => 'WorkspaceContextThread?',
        'decisions'  => array(array('id' => 'integer', 'kind' => 'string', 'author' => 'string', 'text' => 'string', 'created_at' => 'string')),
        'messages'   => array(array('id' => 'integer', 'parent_id' => 'integer?', 'kind' => 'string', 'sender_kind' => 'string', 'author' => 'string', 'text' => 'string', 'task_id' => 'integer?', 'created_at' => 'string')),
        'open_tasks' => array(array('id' => 'integer', 'number' => 'string', 'title' => 'string', 'status' => 'string', 'due_date' => 'string?', 'assignees' => array(array('user_id' => 'integer', 'name' => 'string')))),
    );
}

// What ws_api_context_thread_present() returns. The message is declared on
// its own, as an object written inline cannot say it may be null.
function ws_api_context_thread_schema()
{
    return array(
        'channel' => array('id' => 'integer', 'name' => 'string', 'kind' => 'string'),
        'message' => 'WorkspaceContextMessage?',
    );
}

function ws_api_context_message_schema()
{
    return array('id' => 'integer', 'kind' => 'string', 'sender_kind' => 'string', 'author' => 'string', 'text' => 'string', 'task_id' => 'integer?', 'created_at' => 'string');
}
