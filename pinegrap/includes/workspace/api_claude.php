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
 * ordinary channel ones alike (ws_api_channel_or_404()).
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
            'returns'     => 'WorkspaceClaudeRequest',
            'summary'     => 'Answer a request',
            'description' => 'Writes the answer under the request, as the application, with the person who asked mentioned (they are told), and swaps the eye for a tick. tasks are proposals, not tasks: they wait under the answer until somebody in the channel opens or sets them aside. changes are proposals too, for changing, adding and deleting records: a record is never written here; the person who asked applies the change with one click, with their own rights, and it is kept among the channel\'s decisions. The whole answer is refused (422, the change named) when a change cannot be made: a field that does not exist, a value of the wrong kind, a record that is not there, nothing that differs from what the record holds, an action the kind of record does not take, or a person who asked who may not change that kind of record. Tags such as <@user:12> and <#order:1045> are kept.',
            'params'      => array(
                array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
                array('name' => 'text', 'in' => 'body', 'type' => 'string', 'max_length' => 3900, 'required' => true),
                array('name' => 'tasks', 'in' => 'body', 'type' => 'list', 'max_items' => 10, 'description' => 'Proposed tasks, as objects of {title, description, assignees (user ids), due_date (YYYY-MM-DD), priority (low, normal, high, urgent)}.'),
                array('name' => 'changes', 'in' => 'body', 'type' => 'list', 'max_items' => 10, 'description' => 'Proposed record changes, as objects of {type, action, id, fields, product_ids, reason}. type: product, stock, order, contact, erp_account, product_group, channel, user, page, file, offer or calendar_event. action: update (the default), create (no id; product, contact, erp_account, product_group, calendar_event), delete (no fields; product, contact, product_group, page, file, offer, calendar_event: a product, a group, a page or a file goes to the Recycle Bin), add or remove (product_group only: product_ids, a list of product ids put into the group or taken out of it). fields: an object of field => new value. product: name, title, short_description, full_description, meta_description, meta_keywords, keywords, brand, gtin, mpn, price (minor units, as GET /products gives it), enabled, notes. stock: quantity (the new count). order: status (incomplete, complete, exported, cancelled), notes, cancellation_reason (with cancelled only). contact: salutation, first_name, last_name, company, title, email, phone, address_1, address_2, city, state, zip, country, description. erp_account: title, email, phone, address, district, city, state, postcode, tax_number, tax_office, payment_days, status (active, passive), notes. product_group: name, title, short_description, full_description, meta_description, meta_keywords, keywords, enabled. channel: summary; id is the channel the request came from. user: role (manager or user; applied by an administrator only, never to an administrator). page: title, meta_description, search, search_keywords (a list or comma-separated words), sitemap, noindex. file: description, folder_id, content (the words of a text file only). offer: status (enabled, disabled), description, start_date, end_date (YYYY-MM-DD). calendar_event: name, short_description, location, start_time, end_time (YYYY-MM-DD HH:MM), all_day, published; a new one also takes calendar_ids (a list) and needs name, start_time and calendar_ids. A new product also takes quantity and group_ids (a list), a new ERP account kind (customer, supplier, both) and is_person, a new product group parent_id (a group id; the top of the catalogue when left out) and product_ids. A new product needs name, a new product group name, a new ERP account title, a new contact one of first_name, last_name, company, email. A new product or product group is not on sale / published unless enabled is true. reason: one line on why.'),
            ),
        ),

        array(
            'id'          => 'workspace.claude.requests.fail',
            'method'      => 'POST',
            'path'        => '/workspace/claude/requests/{id}/fail',
            'scope'       => 'workspace:write',
            'handler'     => 'ws_api_claude_fail',
            'returns'     => 'WorkspaceClaudeRequest',
            'summary'     => 'Give a request up',
            'description' => 'Closes the request as not done, writes the reason under it for the person who asked and takes the eye back.',
            'params'      => array(
                array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
                array('name' => 'reason', 'in' => 'body', 'type' => 'string', 'max_length' => 250, 'required' => true),
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
            'description' => 'The channel\'s summary, its latest decisions and notes, its latest messages (oldest first, tags spelled out) and its open tasks: what an assistant reads before it answers in the channel.',
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

    if (ws_api_app_id() !== (int) ws_claude_config()['app_id']) {
        api_fail(403, 'forbidden', lang('Only the application Claude works through may use this endpoint.'));
    }

    return ws_api_viewer();
}

/**
 * Is the current application the one Claude works through?
 *
 * @return bool
 */
function ws_api_is_claude()
{
    return function_exists('ws_claude_schema_ready') && ws_claude_schema_ready()
        && (ws_claude_config()['app_id'] > 0) && (ws_api_app_id() === (int) ws_claude_config()['app_id']);
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

    return array(
        'id'               => (int) $row['id'],
        'status'           => (string) $row['status'],
        'channel'          => array(
            'id'   => (int) $row['channel_id'],
            'name' => $channel ? (string) $channel['name'] : '',
            'kind' => $channel ? (string) $channel['kind'] : (((int) ($row['note_id'] ?? 0) > 0) ? 'note' : ''),
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
        'requested_by'     => array('user_id' => 'integer', 'name' => 'string'),
        'reply_message_id' => 'integer?',
        'error'            => 'string',
        'created_at'       => 'string',
        'claimed_at'       => 'string?',
    );
}

function ws_api_claude_request_or_404($id)
{
    $row = db_item("SELECT * FROM ws_ai_requests WHERE id = '" . (int) $id . "'");

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
        $rows = (array) db_items("SELECT * FROM ws_ai_requests ORDER BY id DESC LIMIT 50");
    } else {
        $where = ($status === 'open') ? "status IN ('queued', 'sent')" : "status = '" . e($status) . "'";
        $rows = (array) db_items("SELECT * FROM ws_ai_requests WHERE " . $where . " ORDER BY id ASC LIMIT 50");
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
 * The channel of a request, open to Claude and to its application's owner.
 *
 * @param array $viewer
 * @param array $row
 * @return array
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

    if (!ws_can_post_channel($viewer, $channel)) {
        api_fail(403, 'forbidden', lang('The owner of Claude\'s application cannot post in that channel. In a private channel they must be a member.'));
    }

    return $channel;
}

function ws_api_claude_answer($params)
{
    $viewer = ws_api_claude_guard();
    $row = ws_api_claude_request_or_404($params['id']);

    if (!in_array($row['status'], array('queued', 'sent', 'running'), true)) {
        api_fail(409, 'closed', lang('This request is already closed.'));
    }

    // Asked in a note: the answer is kept for the note, written into it under
    // the line that asked when somebody has the note open.
    if ((int) ($row['note_id'] ?? 0) > 0) {
        if (!empty($params['tasks']) || !empty($params['changes'])) {
            api_fail_validation(lang('A request from a note is answered with text only: no tasks, no changes.'), empty($params['tasks']) ? 'changes' : 'tasks');
        }

        $now = time();

        db("UPDATE ws_ai_requests SET status = 'answered', note_reply = '" . e(ws_tokens_normalise(trim((string) $params['text']))) . "',
                answered_at = '" . $now . "', claimed_at = IF(claimed_at = 0, '" . $now . "', claimed_at), error = ''
            WHERE id = '" . (int) $row['id'] . "'");

        ws_api_log(lang('Claude answered a request written in a note'));

        api_ok(ws_api_claude_request_present($viewer, ws_api_claude_request_or_404($row['id'])));
    }

    $channel = ws_api_claude_channel($viewer, $row);
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

    $sent = ws_message_send($viewer, $channel, $text, array(
        'parent_id' => (int) $row['message_id'],
        'app_id'    => ws_api_app_id(),
    ));

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

    // The reason goes to the person who asked, where they asked. A channel
    // that closed to Claude meanwhile keeps only the state on the request.
    if ($channel && ws_claude_channel_allowed($channel) && ws_can_post_channel($viewer, $channel)) {
        $sent = ws_message_send($viewer, $channel, '<@user:' . (int) $row['requested_by'] . '> ' . $reason, array(
            'parent_id' => (int) $row['message_id'],
            'app_id'    => ws_api_app_id(),
        ));

        $reply_id = $sent['ok'] ? (int) $sent['message_id'] : 0;
    }

    db("UPDATE ws_ai_requests SET status = 'failed', error = '" . e($reason) . "', reply_message_id = '" . $reply_id . "',
            answered_at = '" . time() . "'
        WHERE id = '" . (int) $row['id'] . "'");

    ws_claude_react((int) $row['message_id'], '👀', false);

    api_ok(ws_api_claude_request_present($viewer, ws_api_claude_request_or_404($row['id'])));
}

function ws_api_channel_context($params)
{
    $viewer = ws_api_viewer();
    $channel = ws_api_channel_or_404($viewer, $params['id']);
    $limit = max(1, min(100, (int) ($params['limit'] ?? 50)));

    $rows = ws_messages_page($channel['id'], 0, $limit);
    $decided = (array) db_items("SELECT * FROM ws_messages
        WHERE channel_id = '" . (int) $channel['id'] . "' AND kind IN ('decision', 'note') AND deleted_at = 0
        ORDER BY marked_at DESC, id DESC
        LIMIT 10");

    $tokens = ws_tokens((string) $channel['summary']);

    foreach (array_merge($rows, $decided) as $row) {
        $tokens = array_merge($tokens, ws_tokens($row['body']));
    }

    $refs = ws_refs_resolve($viewer, $tokens);

    $author = function ($row) {
        if ($row['sender_kind'] === 'user') {
            return ws_person_name($row['sender_id']);
        }

        return ($row['sender_kind'] === 'app') ? (string) ws_app_sender((int) $row['sender_id'])['name'] : '';
    };

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
        'decisions'  => $decisions,
        'messages'   => $messages,
        'open_tasks' => $tasks,
    ));
}

// What ws_api_channel_context() returns.
function ws_api_channel_context_schema()
{
    return array(
        'channel'    => array('id' => 'integer', 'name' => 'string', 'kind' => 'string', 'topic' => 'string', 'summary' => 'string'),
        'decisions'  => array(array('id' => 'integer', 'kind' => 'string', 'author' => 'string', 'text' => 'string', 'created_at' => 'string')),
        'messages'   => array(array('id' => 'integer', 'parent_id' => 'integer?', 'kind' => 'string', 'sender_kind' => 'string', 'author' => 'string', 'text' => 'string', 'task_id' => 'integer?', 'created_at' => 'string')),
        'open_tasks' => array(array('id' => 'integer', 'number' => 'string', 'title' => 'string', 'status' => 'string', 'due_date' => 'string?', 'assignees' => array(array('user_id' => 'integer', 'name' => 'string')))),
    );
}
