<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - a note written in a channel, in the external API and MCP
 * (POST /workspace/channels/{id}/notes): the note and its card in one move,
 * as "Write a note" under the composer's paperclip does. The route runs the
 * panel's own check (ws_note_channel_write_check()) before its dry run stops,
 * so both meet the same refusals.
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
            'id'          => 'workspace.channels.notes.create',
            'method'      => 'POST',
            'path'        => '/workspace/channels/{id}/notes',
            'scope'       => 'workspace:write',
            'handler'     => 'ws_api_channel_notes_create',
            'dry_run'     => true,
            'returns'     => ws_api_channel_note_written_schema(),
            'status'      => 201,
            'summary'     => 'Write a note in a channel',
            'description' => 'Writes a new note of the owner\'s and posts its card in a channel the owner may post in, in one move, as Write a note under the panel\'s paperclip does. The card shows the note as it is whenever it is read; a change to the note (POST /workspace/notes/{id} with revision) touches the card\'s message, so changed_since returns it, and writes no new message. body is Markdown: a heading of four or more # becomes ###, a line underlined with = or - becomes a heading. A body longer than 60,000 characters is refused with 422 on body, never cut. channel_edit true lets whoever may post in the channel change the note (access edit); left out, the channel reads it only. Who the card\'s message is written as follows POST /workspace/channels/{id}/files. The answer gives the card\'s message id, the note id and its revision, the base of the next change. Refusals: a channel the owner may not post in 403 forbidden; an empty note, one too long or the owner\'s note limit 422 validation_failed. Send an Idempotency-Key so a retried call does not write twice.',
            'params'      => array(
                array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
                array('name' => 'title', 'in' => 'body', 'type' => 'string', 'max_length' => 200, 'description' => 'The note\'s title. Left out, its first line names it.'),
                array('name' => 'body', 'in' => 'body', 'type' => 'string', 'required' => true, 'description' => 'The note\'s text, in Markdown, at most 60,000 characters.'),
                array('name' => 'channel_edit', 'in' => 'body', 'type' => 'bool', 'description' => 'Whether the people who may post in the channel may change the note. Left out, false.'),
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
