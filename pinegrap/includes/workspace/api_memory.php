<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - a channel's memory and the decision mark in the external API
 * and MCP (includes/workspace/channel_memory.php): list a channel's entries
 * (the index: titles and tags), read one, write, change and delete them,
 * and mark a message as a decision or a note. Whoever may post in the
 * channel writes its memory and marks its messages; an application key or an
 * OAuth connection writes as the application (an agent), a team devices
 * session as the person.
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
 * The memory and mark routes, merged into ws_api_routes().
 *
 * @return array[]
 */
function ws_memory_api_routes()
{
    $id = array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true);
    $who = ' An application key or an OAuth connection writes as the application, a team devices session (POST /auth/login) as the person.';
    $errors = ' A refused value answers 422 validation_failed naming the field; a channel the owner may not post in 403 forbidden; the database not updated yet 503 service_unavailable.';
    $fields = array(
        array('name' => 'title', 'in' => 'body', 'type' => 'string', 'max_length' => WS_MEMORY_TITLE_MAX, 'description' => 'What the entry is about, in a few words: agents choose what to read by it.'),
        array('name' => 'text', 'in' => 'body', 'type' => 'string', 'max_length' => WS_MEMORY_TEXT_MAX, 'description' => 'What is to be remembered, plain text.'),
        array('name' => 'tags', 'in' => 'body', 'type' => 'string', 'max_length' => 400, 'description' => 'Up to 10 one-word tags, separated by commas or spaces; stored in lower case without #.'),
        array('name' => 'pinned', 'in' => 'body', 'type' => 'bool', 'description' => 'Pinned entries come first in the index every agent brief carries.'),
        array('name' => 'expires_at', 'in' => 'body', 'type' => 'datetime', 'description' => 'When the entry stops counting: it leaves the index then, and is deleted 30 days later. Left out on a new entry, it never expires.'),
        array('name' => 'no_expiry', 'in' => 'body', 'type' => 'bool', 'description' => 'true takes the expiry off.'),
    );

    return array(
        array(
            'id'          => 'workspace.messages.mark',
            'method'      => 'POST',
            'path'        => '/workspace/messages/{id}/mark',
            'scope'       => 'workspace:write',
            'handler'     => 'ws_api_messages_mark',
            'dry_run'     => true,
            'returns'     => 'WorkspaceMessage',
            'summary'     => 'Mark a message as a decision or a note, or take the mark off',
            'description' => 'Marks a message of a person or of an application (an agent\'s agreement too) in a channel the owner of the application may post in, the way Mark as decision in the message menu does: kind decision or note; message takes the mark off. Its decision line goes into the brief of every agent asked in the channel, and GET /workspace/decisions lists it with marked_by and marked_by_kind (user or app).' . $who . ' A decision that records a change to a record keeps its mark against the User role (403).',
            'params'      => array(
                $id,
                array('name' => 'kind', 'in' => 'body', 'type' => 'enum', 'values' => array('decision', 'note', 'message'), 'required' => true),
            ),
        ),

        array(
            'id'          => 'workspace.memory.list',
            'method'      => 'GET',
            'path'        => '/workspace/channels/{id}/memory',
            'scope'       => 'workspace:read',
            'handler'     => 'ws_api_memory_list',
            'returns'     => array('list' => 'WorkspaceMemoryIndexEntry'),
            'summary'     => 'The index of a channel\'s memory',
            'description' => 'The memory entries of a channel the owner of the application may read, pinned first and then the most recently changed, without their texts: read the ones needed with GET /workspace/memory/{id}. Every channel has a memory; an agent\'s brief carries the same index. Expired entries are left out unless expired is true. tag keeps the entries with that tag.',
            'params'      => array(
                $id,
                array('name' => 'expired', 'in' => 'query', 'type' => 'bool', 'description' => 'The expired entries too.'),
                array('name' => 'tag', 'in' => 'query', 'type' => 'string', 'max_length' => WS_MEMORY_TAG_MAX),
            ),
        ),

        array(
            'id'          => 'workspace.memory.get',
            'method'      => 'GET',
            'path'        => '/workspace/memory/{id}',
            'scope'       => 'workspace:read',
            'handler'     => 'ws_api_memory_get',
            'returns'     => 'WorkspaceMemoryEntry',
            'summary'     => 'Read a memory entry',
            'description' => 'One entry of a channel\'s memory with its text, in a channel the owner of the application may read; an id from the brief\'s index (#12 is 12) or from GET /workspace/channels/{id}/memory.',
            'params'      => array($id),
        ),

        array(
            'id'          => 'workspace.memory.create',
            'method'      => 'POST',
            'path'        => '/workspace/channels/{id}/memory',
            'scope'       => 'workspace:write',
            'handler'     => 'ws_api_memory_create',
            'dry_run'     => true,
            'status'      => 201,
            'returns'     => 'WorkspaceMemoryEntry',
            'summary'     => 'Write an entry into a channel\'s memory',
            'description' => 'Adds an entry to the memory of a channel the owner of the application may post in: something the members and the agents of the channel should keep knowing, such as a rule of the channel, a fact, a link or a state of work. Keep one topic per entry and a title that says it: agents see only the titles and tags until they read an entry.' . $who . $errors,
            'params'      => array_merge(array($id), array_map(function ($field) {
                return ($field['name'] === 'title') ? $field + array('required' => true) : $field;
            }, $fields)),
        ),

        array(
            'id'           => 'workspace.memory.update',
            'method'       => 'POST',
            'also_accepts' => array('PATCH'),
            'path'         => '/workspace/memory/{id}',
            'scope'        => 'workspace:write',
            'handler'      => 'ws_api_memory_update',
            'dry_run'      => true,
            'returns'      => 'WorkspaceMemoryEntry',
            'summary'      => 'Change a memory entry',
            'description'  => 'Changes an entry of a channel the owner of the application may post in; fields left out keep their value. Correct a stale entry rather than writing a second one beside it. PATCH is accepted beside POST.' . $who . $errors,
            'params'       => array_merge(array($id), $fields),
        ),

        array(
            'id'          => 'workspace.memory.delete',
            'method'      => 'DELETE',
            'path'        => '/workspace/memory/{id}',
            'scope'       => 'workspace:write',
            'handler'     => 'ws_api_memory_delete',
            'dry_run'     => true,
            'returns'     => 'WorkspaceMemoryDeletion',
            'summary'     => 'Delete a memory entry',
            'description' => 'Deletes an entry of a channel the owner of the application may post in. It cannot be undone. POST /workspace/memory/{id}/delete does the same for servers that refuse DELETE.',
            'params'      => array($id),
        ),

        array(
            'id'          => 'workspace.memory.delete_post',
            'method'      => 'POST',
            'path'        => '/workspace/memory/{id}/delete',
            'scope'       => 'workspace:write',
            'handler'     => 'ws_api_memory_delete',
            'dry_run'     => true,
            'returns'     => 'WorkspaceMemoryDeletion',
            'summary'     => 'Delete a memory entry, addressed as a POST',
            'description' => 'The same deletion as DELETE /workspace/memory/{id}, for clients and servers that cannot send DELETE.',
            'params'      => array($id),
        ),
    );
}

function ws_api_memory_index_entry_schema()
{
    return array(
        'id'         => 'integer',
        'channel_id' => 'integer',
        'title'      => 'string',
        'tags'       => array('string'),
        'pinned'     => 'boolean',
        'expires_at' => 'string?',
        'expired'    => 'boolean',
        'author'     => 'WorkspaceMemoryWriter',
        'updated_by' => 'WorkspaceMemoryWriter',
        'created_at' => 'string?',
        'updated_at' => 'string?',
    );
}

function ws_api_memory_entry_schema()
{
    return ws_api_memory_index_entry_schema() + array('text' => 'string');
}

function ws_api_memory_writer_schema()
{
    return array('kind' => 'string', 'id' => 'integer', 'name' => 'string');
}

function ws_api_memory_deletion_schema()
{
    return array('id' => 'integer', 'channel_id' => 'integer', 'deleted' => 'boolean');
}

/**
 * An entry as the API gives it: ws_memory_present() with ISO moments.
 *
 * @param array $row  ws_channel_memory
 * @param bool  $text
 * @return array
 */
function ws_api_memory_present($row, $text = true)
{
    $out = ws_memory_present($row, $text);
    $out['expires_at'] = ($out['expires_at'] > 0) ? api_time($out['expires_at']) : null;
    $out['created_at'] = api_time($out['created_at']);
    $out['updated_at'] = api_time($out['updated_at']);

    return $out;
}

/**
 * Who writes through this call: the application, or the person on their own
 * team devices session.
 *
 * @param array $viewer
 * @return array kind, id
 */
function ws_api_memory_writer($viewer)
{
    $app_id = ws_api_writer_app_id();

    return ($app_id > 0) ? array('kind' => 'app', 'id' => $app_id) : array('kind' => 'user', 'id' => (int) $viewer['id']);
}

function ws_api_memory_ready_or_503()
{
    if (!ws_memory_ready()) {
        api_fail(503, 'service_unavailable', lang('The database has to be updated first.'));
    }
}

/**
 * An entry in a channel the owner may read, or a 404.
 *
 * @param array $viewer
 * @param int   $id
 * @return array row, channel
 */
function ws_api_memory_or_404($viewer, $id)
{
    ws_api_memory_ready_or_503();

    $row = ws_memory_row($id);
    $channel = $row ? ws_channel($row['channel_id']) : null;

    if (!$row || !$channel || !ws_can_read_channel($viewer, $channel)) {
        api_fail_not_found(lang('Memory entry'));
    }

    return array('row' => $row, 'channel' => ws_api_channel_or_404($viewer, $channel['id']));
}

/**
 * The fields a write sends, as ws_memory_check() takes them.
 *
 * @param array $params
 * @return array
 */
function ws_api_memory_input($params)
{
    $input = array();

    foreach (array('title', 'text', 'tags', 'pinned', 'expires_at') as $key) {
        if (array_key_exists($key, $params)) {
            $input[$key] = $params[$key];
        }
    }

    if (!empty($params['no_expiry'])) {
        $input['expires_at'] = 0;
    }

    return $input;
}

/**
 * Checks a write, or answers the refusal.
 *
 * @param array      $viewer
 * @param array      $channel
 * @param array      $params
 * @param array|null $current
 * @return array ws_memory_check()['values']
 */
function ws_api_memory_checked($viewer, $channel, $params, $current = null)
{
    $check = ws_memory_check($viewer, $channel, ws_api_memory_input($params), $current);

    if (!$check['ok']) {
        if ($check['forbidden']) {
            api_fail(403, 'forbidden', $check['error']);
        }

        api_fail(422, 'validation_failed', $check['error'], ($check['field'] !== '') ? $check['field'] : null);
    }

    return $check['values'];
}

function ws_api_memory_list($params)
{
    $viewer = ws_api_viewer();
    $channel = ws_api_channel_or_404($viewer, $params['id']);
    $tag = mb_strtolower(ltrim(trim((string) ($params['tag'] ?? '')), '#'));
    $out = array();

    foreach (ws_memory_rows($channel['id'], !empty($params['expired'])) as $row) {
        if (($tag !== '') && !in_array($tag, ws_memory_tags((string) $row['tags']), true)) {
            continue;
        }

        $out[] = ws_api_memory_present($row, false);
    }

    api_ok_list($out, count($out));
}

function ws_api_memory_get($params)
{
    $viewer = ws_api_viewer();
    $found = ws_api_memory_or_404($viewer, $params['id']);

    api_ok(ws_api_memory_present($found['row']));
}

function ws_api_memory_create($params)
{
    $viewer = ws_api_viewer();
    ws_api_memory_ready_or_503();
    $channel = ws_api_channel_or_404($viewer, $params['id']);
    $values = ws_api_memory_checked($viewer, $channel, $params);
    $writer = ws_api_memory_writer($viewer);

    api_dry_run_stop('created', 'workspace_memory_entry', array('channel_id' => (int) $channel['id']) + $values);

    $id = ws_memory_save($channel['id'], $values, $writer['kind'], $writer['id']);

    api_ok(ws_api_memory_present(ws_memory_row($id)), 201);
}

function ws_api_memory_update($params)
{
    $viewer = ws_api_viewer();
    $found = ws_api_memory_or_404($viewer, $params['id']);
    $values = ws_api_memory_checked($viewer, $found['channel'], $params, $found['row']);
    $writer = ws_api_memory_writer($viewer);

    api_dry_run_stop('updated', 'workspace_memory_entry', array('id' => (int) $found['row']['id'], 'channel_id' => (int) $found['channel']['id']) + $values);

    ws_memory_save($found['channel']['id'], $values, $writer['kind'], $writer['id'], $found['row']);

    api_ok(ws_api_memory_present(ws_memory_row($found['row']['id'])));
}

function ws_api_memory_delete($params)
{
    $viewer = ws_api_viewer();
    $found = ws_api_memory_or_404($viewer, $params['id']);

    if (!ws_can_post_channel($viewer, $found['channel'])) {
        api_fail(403, 'forbidden', lang('You cannot post in that channel.'));
    }

    api_dry_run_stop('deleted', 'workspace_memory_entry', array('id' => (int) $found['row']['id'], 'channel_id' => (int) $found['channel']['id']));

    $result = ws_memory_delete($viewer, $found['row']);

    if (!$result['ok']) {
        api_fail(403, 'forbidden', $result['error']);
    }

    api_ok(array('id' => (int) $found['row']['id'], 'channel_id' => (int) $found['channel']['id'], 'deleted' => true));
}

function ws_api_messages_mark($params)
{
    $viewer = ws_api_viewer();
    $message = ws_api_message_or_404($viewer, $params['id']);
    $kind = (string) $params['kind'];
    $check = ws_message_mark_check($viewer, $message, $kind);

    if (!$check['ok']) {
        if ($check['forbidden']) {
            api_fail(403, 'forbidden', $check['error']);
        }

        api_fail_validation($check['error'], 'kind');
    }

    $app_id = ws_api_writer_app_id();

    if (($app_id > 0) && ($kind !== 'message') && !ws_marks_kind_ready()) {
        api_fail(503, 'service_unavailable', lang('The database has to be updated first.'));
    }

    api_dry_run_stop(($kind === 'message') ? 'unmarked' : 'marked', 'workspace_message_mark', array(
        'message_id' => (int) $message['id'],
        'channel_id' => (int) $message['channel_id'],
        'kind'       => $kind,
        'changes'    => ($kind !== (string) $message['kind']),
    ));

    $result = ws_message_mark($viewer, $message, $kind, $app_id);

    if (!$result['ok']) {
        api_fail_validation($result['error'], 'kind');
    }

    $row = ws_message($message['id']);

    api_ok(ws_api_message_present($viewer, $row, ws_refs_resolve($viewer, ws_tokens($row['body']))));
}
