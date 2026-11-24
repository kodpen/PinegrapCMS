<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - a note written in a channel, in the external API and MCP
 * (POST /workspace/channels/{id}/notes): the note and its card in one move,
 * as "Write a note" under the composer's paperclip does. The route runs the
 * panel's own check (ws_note_channel_write_check()) before its dry run stops,
 * so both meet the same refusals. GET lists the channel's own notes, as its
 * Notes tab does (ws_note_channel_page()). POST /workspace/notes/{id}/move
 * makes a note of the owner's the channel's, as the card's "Move to this
 * channel" does (ws_note_move()).
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
 * The route of a note written in a channel, merged into ws_api_routes().
 *
 * @return array[]
 */
function ws_channel_note_api_routes()
{
    return array(
        array(
            'id'          => 'workspace.channels.notes.list',
            'method'      => 'GET',
            'path'        => '/workspace/channels/{id}/notes',
            'scope'       => 'workspace:read',
            'handler'     => 'ws_api_channel_notes_list',
            'returns'     => array('list' => 'WorkspaceNote'),
            'summary'     => 'The notes of a channel',
            'description' => 'The channel\'s Notes tab: the notes written in the channel, which belong to it (owner_channel_id), the latest changed first. search finds words in the titles; page.total counts the matches. Page with next_cursor until has_more is false. text and body are null here: GET /workspace/notes/{id}. access is edit for who may post in the channel, view otherwise. A channel the owner may not read: 404.',
            'details'     => 'A channel\'s note is on nobody\'s own notes (GET /workspace/notes): it is read and changed by the channel\'s rights. owner is the one who wrote it. Its writer and whoever manages the channel delete it with DELETE /workspace/notes/{id}. A person\'s own note shared in the channel is not listed here.',
            'params'      => array(
                array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
                array('name' => 'search', 'in' => 'query', 'type' => 'string', 'max_length' => 100, 'description' => 'Words in the title.'),
                array('name' => 'cursor', 'in' => 'query', 'type' => 'string', 'max_length' => 200, 'description' => 'next_cursor from the previous page.'),
                array('name' => 'limit', 'in' => 'query', 'type' => 'int', 'min' => 1, 'max' => 200, 'default' => 100, 'description' => 'Notes per page.'),
            ),
        ),

        array(
            'id'          => 'workspace.channels.notes.create',
            'method'      => 'POST',
            'path'        => '/workspace/channels/{id}/notes',
            'scope'       => 'workspace:write',
            'handler'     => 'ws_api_channel_notes_create',
            'dry_run'     => true,
            'returns'     => ws_api_channel_note_written_schema(),
            'status'      => 201,
            'summary'     => 'Write a note in a channel',
            'description' => 'Writes a new note of the channel\'s and posts its card in a channel the owner may post in, in one move; who may post there changes it. The card always shows the note as it is; change it later with POST /workspace/notes/{id} and its revision (no new message). A body over 60,000 characters: 422, never cut. The answer gives the card\'s message id, the note id and its revision. A channel the owner may not post in: 403; an empty or too long note, or the channel\'s note limit: 422 validation_failed.',
            'details'     => 'The note belongs to the channel: GET /workspace/channels/{id}/notes lists it, and it is on nobody\'s own notes. As Write a note under the panel\'s paperclip does. A change to the note touches the card\'s message, so changed_since returns it. body Markdown: a heading of four or more # becomes ###, a line underlined with = or - becomes a heading. channel_edit gives access edit. Who the card\'s message is written as follows POST /workspace/channels/{id}/files. The revision is the base of the next change. Send an Idempotency-Key so a retried call does not write twice.',
            'params'      => array(
                array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
                array('name' => 'title', 'in' => 'body', 'type' => 'string', 'max_length' => 200, 'description' => 'The note\'s title. Left out, its first line names it.'),
                array('name' => 'body', 'in' => 'body', 'type' => 'string', 'required' => true, 'description' => 'The note\'s text, in Markdown, at most 60,000 characters.'),
                array('name' => 'channel_edit', 'in' => 'body', 'type' => 'bool', 'description' => 'Kept for older callers and ignored: who may post in the channel changes its notes.'),
            ),
        ),

        array(
            'id'          => 'workspace.notes.move',
            'method'      => 'POST',
            'path'        => '/workspace/notes/{id}/move',
            'scope'       => 'workspace:write',
            'handler'     => 'ws_api_notes_move',
            'dry_run'     => true,
            'returns'     => 'WorkspaceNote',
            'summary'     => 'Move a note into a channel',
            'description' => 'Makes a note of the owner\'s own the channel\'s, as "Move to this channel" on its card does: it leaves the owner\'s notes and limit, GET /workspace/channels/{id}/notes lists it, and who may post there changes it. A card is posted when it is not shared there yet; every other share goes. Not the owner\'s own, or locked: 403. A channel not read: 404; not posted in, or at its note limit: 422 on channel_id. Answers the note.',
            'details'     => 'Use it to move many notes written before into the channel they belong to, one call each. Its card in the channel stays; a card elsewhere says it is no longer shared, and it leaves its note group and the menu pin. The apps that keep notes in sync learn that it left the lists from deleted in GET /workspace/notes?updated_since=. The way back is in the panel: "Copy to my notes" on the channel\'s note. Who a card posted for it is written as follows POST /workspace/channels/{id}/files. Send an Idempotency-Key so a retried call does not post a second card.',
            'params'      => array(
                array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
                array('name' => 'channel_id', 'in' => 'body', 'type' => 'int', 'min' => 1, 'required' => true, 'description' => 'The channel the note moves to.'),
            ),
        ),
    );
}

// What POST /workspace/channels/{id}/notes answers.
function ws_api_channel_note_written_schema()
{
    return array('message_id' => 'integer', 'note_id' => 'integer', 'revision' => 'integer');
}

/**
 * A channel's own notes, a page at a time (ws_note_channel_page()).
 */
function ws_api_channel_notes_list($params)
{
    $viewer = ws_api_viewer();
    $channel = ws_api_channel_or_404($viewer, $params['id']);
    $after = null;

    if (isset($params['cursor']) && ($params['cursor'] !== '')) {
        $cursor = api_cursor_decode($params['cursor']);
        $after = ($cursor === null) ? null : ws_notes_position_unpack($cursor['v'], $cursor['i']);

        if (($after === null) || ((int) $after['group'] !== 0)) {
            api_fail(400, 'invalid_cursor', lang('The cursor is not readable. Start the listing again without one.'), 'cursor');
        }
    }

    $limit = (int) ($params['limit'] ?? 100);
    $page = ws_note_channel_page($viewer, $channel, array(
        'search' => (string) ($params['search'] ?? ''),
        'after'  => $after,
        'limit'  => $limit,
    ));
    $out = array();

    foreach ($page['rows'] as $note) {
        $out[] = ws_api_note_present($viewer, $note);
    }

    $next = ($page['next'] !== null)
        ? api_cursor_encode(ws_notes_position_pack(0, $page['next']['updated_at']), $page['next']['id'])
        : null;

    api_ok_list($out, $limit, $next, $page['total']);
}

/**
 * Writes a note in a channel with its card (ws_note_channel_write()).
 */
function ws_api_channel_notes_create($params)
{
    $viewer = ws_api_viewer();

    if (!ws_notes_ready()) {
        api_fail(503, 'service_unavailable', lang('The database has to be updated first.'));
    }

    $channel = ws_api_channel_or_404($viewer, $params['id']);

    if (!ws_can_post_channel($viewer, $channel)) {
        api_fail(403, 'forbidden', lang('You cannot post in that channel.'));
    }

    $writer = ws_api_message_writer();
    $data = array(
        'title'         => (string) ($params['title'] ?? ''),
        'body'          => (string) ($params['body'] ?? ''),
        'channel_edit'  => !empty($params['channel_edit']),
        'markdown'      => true,
        'app_id'        => (int) $writer['app_id'],
        'via_client_id' => (int) $writer['via_client_id'],
    );

    $checked = ws_note_channel_write_check($viewer, $channel, $data);

    if (!$checked['ok']) {
        api_fail_validation($checked['error'], ($checked['field'] !== '') ? $checked['field'] : 'body');
    }

    api_dry_run_stop('created', 'workspace_note', array(
        'channel_id'   => (int) $channel['id'],
        'title'        => (string) $checked['title'],
        'length'       => mb_strlen((string) $checked['body']),
        'channel_edit' => $data['channel_edit'],
    ));

    $result = ws_note_channel_write($viewer, $channel, $data);

    if (!$result['ok']) {
        if ($result['error'] === lang('The note could not be saved.')) {
            api_fail_server($result['error']);
        }

        api_fail_validation($result['error'], ($result['field'] !== '') ? $result['field'] : 'body');
    }

    api_ok(array('message_id' => (int) $result['message_id'], 'note_id' => (int) $result['note_id'], 'revision' => (int) $result['revision']), 201);
}

/**
 * Moves a note of the owner's into a channel (ws_note_move()).
 */
function ws_api_notes_move($params)
{
    $viewer = ws_api_viewer();

    if (!ws_notes_ready() || !ws_note_channel_owner_ready()) {
        api_fail(503, 'service_unavailable', lang('The database has to be updated first.'));
    }

    $note = ws_api_note_or_404($viewer, $params['id']);
    ws_api_note_locked_stop($viewer, $note);

    $channel = ws_api_channel_or_404($viewer, $params['channel_id']);
    $checked = ws_note_move_check($viewer, $note, $channel);

    if (!$checked['ok']) {
        if ($checked['field'] === 'note_id') {
            api_fail(403, 'forbidden', $checked['error']);
        }

        api_fail_validation($checked['error'], 'channel_id');
    }

    api_dry_run_stop('updated', 'workspace_note', array(
        'id'         => (int) $note['id'],
        'channel_id' => (int) $channel['id'],
        'card'       => ($checked['card'] !== null) ? 'kept' : 'posted',
    ));

    $writer = ws_api_message_writer();
    $result = ws_note_move($viewer, $note, $channel, array(
        'app_id'        => (int) $writer['app_id'],
        'via_client_id' => (int) $writer['via_client_id'],
    ));

    if (!$result['ok']) {
        api_fail_validation($result['error'], 'channel_id');
    }

    api_ok(ws_api_note_present($viewer, ws_note_present($viewer, ws_note_for($viewer, (int) $note['id'], 'view'))));
}
