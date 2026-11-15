<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - files in a message, in the external API and MCP: post a copy
 * of a file of the file manager into a channel
 * (POST /workspace/channels/{id}/library-files, library.php), put an edited
 * file in the place of the one the person's own message carries
 * (POST /workspace/messages/{id}/file, file_edits.php) and list the earlier
 * versions of a message's file (GET /workspace/messages/{id}/file/history).
 * Each runs the panel's own function after its check, so a dry run meets
 * the same refusals.
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

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/library.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/file_edits.php');

/**
 * The routes of a message's files, merged into ws_api_routes().
 *
 * @return array[]
 */
function ws_message_file_api_routes()
{
    return array(
        array(
            'id'          => 'workspace.channels.library_files.create',
            'method'      => 'POST',
            'path'        => '/workspace/channels/{id}/library-files',
            'scope'       => 'workspace:write',
            'handler'     => 'ws_api_library_files_create',
            'dry_run'     => true,
            'returns'     => 'WorkspaceMessage',
            'status'      => 201,
            'summary'     => 'Post a file of the file manager in a channel',
            'description' => 'Posts a copy of a file already in the site\'s file manager into a channel the owner may post in, as a message carrying it, the way From the file manager under the paperclip does; text goes with it. Only a file the owner may open in the file manager is taken, and not one that already belongs to a conversation (forward its message instead). The copy meets the checks of an upload: the channel\'s size limit and the file types it refuses. Who it is written as follows POST /workspace/channels/{id}/files. Refusals: a file that cannot be found or brought in, too large or of a refused type 422 validation_failed on file_id; words that cannot be written 422 on text; a channel the owner may not post in 403 forbidden.',
            'params'      => array(
                array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
                array('name' => 'file_id', 'in' => 'body', 'type' => 'int', 'min' => 1, 'required' => true, 'description' => 'The id of the file in the file manager.'),
                array('name' => 'text', 'in' => 'body', 'type' => 'string', 'max_length' => 4000, 'description' => 'Words to go with the file. Tags are written as tokens, as when writing.'),
            ),
        ),

        // A replacement changes what the person posted, so it comes from
        // their team devices session only (ws_api_own_words_only()) and is
        // offered to no MCP client as a tool.
        array(
            'id'          => 'workspace.messages.file.replace',
            'method'      => 'POST',
            'path'        => '/workspace/messages/{id}/file',
            'scope'       => 'workspace:write',
            'handler'     => 'ws_api_message_file_replace',
            'dry_run'     => true,
            'mcp'         => false,
            'returns'     => 'WorkspaceMessage',
            'form_file'   => 'file',
            'summary'     => 'Put an edited file in place of a message\'s file',
            'description' => 'Puts an edited picture, text file, Word document or Excel workbook in the place of the one the person\'s own message carries, as saving over it in the panel\'s editors does: the message reads edited and the file it carried stays as its earlier version (GET /workspace/messages/{id}/file/history). Only the person\'s own team devices session (POST /auth/login) can do it, only in a message they wrote, while they may write in the channel; to answer somebody else\'s file with an edited copy, post it with POST /workspace/channels/{id}/files and parent_id. The file is of the same kind as the one it replaces and keeps its name, with the extension of what it now is; it meets the checks of an upload, a text file at most 512 KB. Send it as multipart/form-data in a part named file, or as content_base64 in a JSON body. Refusals: another caller, somebody else\'s message or a channel the owner may not write in 403 forbidden; a file of another kind, too large or of a refused type 422 validation_failed on file or content_base64; a message whose file no editor opens 422.',
            'params'      => array(
                array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
                array('name' => 'name', 'in' => 'body', 'type' => 'string', 'max_length' => 200, 'description' => 'A name with the extension of the edited file, when it differs from the old one (a picture saved as JPEG in place of a PNG); only the extension is taken. Left out, the old name stays.'),
                array('name' => 'content_base64', 'in' => 'body', 'type' => 'string', 'description' => 'The edited file, base64 encoded, in a JSON body. Leave it out when the file is sent as a form part named file.'),
            ),
        ),

        array(
            'id'          => 'workspace.messages.file.history',
            'method'      => 'GET',
            'path'        => '/workspace/messages/{id}/file/history',
            'scope'       => 'workspace:read',
            'handler'     => 'ws_api_message_file_history',
            'returns'     => array('versions' => array(ws_api_message_file_version_schema())),
            'summary'     => 'List the earlier versions of a message\'s file',
            'description' => 'The files a message carried before its author put edited ones in their place, newest first, at most 50, as the panel\'s version history lists them: each with the name it went by, the site\'s address of the file (it opens in a browser signed in to the site; empty when the file is gone), who put the next version in its place and when. Versions up to an edit that burnt a redaction in are listed only to the person who made it. Whoever may read the message may read its history; a message deleted for everyone 404.',
            'params'      => array(
                array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
            ),
        ),
    );
}

// What one entry of ws_api_message_file_version() is.
function ws_api_message_file_version_schema()
{
    return array('name' => 'string', 'url' => 'string', 'edited_by' => array('user_id' => 'integer', 'name' => 'string'), 'created_at' => 'string');
}

/**
 * One earlier version of a message's file as the API shows it, from an entry
 * of ws_file_history(). Pure but for the site's address.
 *
 * @param array $version name, by, by_id, created_at, stored
 * @return array
 */
function ws_api_message_file_version($version)
{
    $stored = (string) ($version['stored'] ?? '');

    return array(
        'name'       => (string) $version['name'],
        'url'        => ($stored !== '') ? URL_SCHEME . HOSTNAME_SETTING . PATH . encode_url_path($stored) : '',
        'edited_by'  => array('user_id' => (int) ($version['by_id'] ?? 0), 'name' => (string) ($version['by'] ?? '')),
        'created_at' => api_time($version['created_at'] ?? 0),
    );
}

/**
 * The name an edited file goes by in place of the old one, or '' when it is
 * not the same kind of file: the old name with the new extension
 * (ws_file_edit_name()). Pure but for pg_office_kind().
 *
 * @param string $old_name the name the message's file goes by
 * @param string $new_name the name the caller gave, '' for none
 * @return string
 */
function ws_message_file_replace_name($old_name, $new_name)
{
    $old_name = (string) $old_name;
    $extension = strtolower((string) pathinfo(((string) $new_name !== '') ? (string) $new_name : $old_name, PATHINFO_EXTENSION));

    if ($extension === '') {
        return '';
    }

    $name = ws_file_edit_name($old_name, $extension);
    $kind = ws_file_edit_kind($old_name);

    if (($kind === '') || (ws_file_edit_kind($name) !== $kind)) {
        return '';
    }

    // A workbook stays a workbook, a document a document.
    if (($kind === 'office') && (pg_office_kind($name) !== pg_office_kind($old_name))) {
        return '';
    }

    return $name;
}

/**
 * Posts a copy of a file of the file manager into a channel, as From the
 * file manager under the paperclip does (ws_library_send()).
 */
function ws_api_library_files_create($params)
{
    $viewer = ws_api_viewer();
    $channel = ws_api_channel_or_404($viewer, $params['id']);

    if (!ws_can_post_channel($viewer, $channel)) {
        api_fail(403, 'forbidden', lang('You cannot post in that channel.'));
    }

    $writer = ws_api_message_writer();
    $options = array('app_id' => $writer['app_id'], 'via_client_id' => $writer['via_client_id']);
    $text = (string) ($params['text'] ?? '');
    $file_id = (int) $params['file_id'];

    $checked = ws_library_send_check($viewer, $channel, $file_id, $text, $options);

    if (!$checked['ok']) {
        api_fail_validation($checked['error'], $checked['field']);
    }

    api_dry_run_stop('created', 'workspace_message', array(
        'channel_id' => (int) $channel['id'],
        'file'       => array('name' => mb_substr((string) $checked['name'], 0, 200), 'kind' => ws_file_kind($checked['name']), 'size' => strlen((string) $checked['binary'])),
    ));

    unset($checked);

    $result = ws_library_send($viewer, $channel, $file_id, $text, $options);

    if (!$result['ok']) {
        if ($result['error'] === lang('The file could not be saved.')) {
            api_fail_server($result['error']);
        }

        api_fail_validation($result['error'], 'file_id');
    }

    // The words beside it ask as a message's do: the person's ask the
    // assistants (asked by ws_message_send_file_bytes()), an application's
    // wake only the registry's agents it names or answers.
    if (ws_api_message_asks_assistants($writer['app_id'], 'message', $writer['via_client_id'])) {
        ws_api_assistants_carry_on((array) ($result['asked'] ?? array()));
    } elseif (ws_api_message_wakes_agents($writer['app_id'], 'message', $writer['via_client_id']) && function_exists('ws_agent_after_app_send')) {
        ws_api_assistants_carry_on(ws_agent_after_app_send($viewer, $channel, $result['message_id'], $text));
    }

    $row = ws_message($result['message_id']);

    api_ok(ws_api_message_present($viewer, $row, ws_refs_resolve($viewer, ws_tokens($row['body']))), 201);
}

/**
 * Puts an edited file in the place of the one the person's own message
 * carries (ws_message_file_replace()), as the panel's editors save over it.
 */
function ws_api_message_file_replace($params)
{
    $viewer = ws_api_viewer();
    $message = ws_api_message_or_404($viewer, $params['id']);

    ws_api_own_words_only();

    $kind = ws_file_edit_kind((string) $message['file_name']);

    if (((int) $message['file_id'] <= 0) || ($kind === '')) {
        api_fail_validation(lang('This file cannot be edited here.'));
    }

    // The checks the panel's editors make before they write: a file the
    // message still carries, of a kind its editor opens.
    $target = ws_file_edit_target($viewer, (int) $message['id'], $kind);

    if (!is_array($target)) {
        api_fail_validation($target);
    }

    list($message, $channel) = $target;

    if (!ws_file_can_replace($viewer, $message, $channel)) {
        api_fail(403, 'forbidden', lang('Only the one who posted a file can replace it. Send an edited copy instead.'));
    }

    $ready = ws_message_file_replace_check($viewer, $message, $channel);

    if (!$ready['ok']) {
        api_fail_validation($ready['error']);
    }

    $upload = ws_api_upload_input(array('content_base64' => $params['content_base64'] ?? '', 'name' => ((string) ($params['name'] ?? '') !== '') ? $params['name'] : $message['file_name']));
    $bytes = isset($upload['bytes']) ? $upload['bytes'] : ws_upload_decode($upload['base64']);
    $name = ws_message_file_replace_name((string) $message['file_name'], (string) ($params['name'] ?? ''));

    if ($name === '') {
        api_fail_validation(lang('The edited file must be of the same kind as the one it replaces.'), 'name');
    }

    // The text window's own limit.
    if (($kind === 'text') && is_string($bytes) && (strlen($bytes) > WS_FILE_TEXT_MAX)) {
        api_fail_validation(lang(array('string' => 'The file is larger than {var:1} MB.', 'vars' => round(WS_FILE_TEXT_MAX / 1048576, 1))), $upload['field']);
    }

    $checked = ws_upload_check($viewer, $channel, $name, $bytes);

    if (!$checked['ok']) {
        api_fail_validation($checked['error'], $upload['field']);
    }

    api_dry_run_stop('updated', 'workspace_message', array(
        'id'         => (int) $message['id'],
        'channel_id' => (int) $message['channel_id'],
        'file'       => array('name' => mb_substr($checked['name'], 0, 200), 'kind' => ws_file_kind($checked['name']), 'size' => strlen((string) $bytes)),
    ));

    $result = ws_message_file_replace($viewer, $message, $channel, $name, base64_encode((string) $bytes));

    if (!$result['ok']) {
        if ($result['error'] === lang('The file could not be saved.')) {
            api_fail_server($result['error']);
        }

        api_fail_validation($result['error'], $upload['field']);
    }

    $row = ws_message($message['id']);

    api_ok(ws_api_message_present($viewer, $row, ws_refs_resolve($viewer, ws_tokens($row['body']))));
}

/**
 * The earlier versions of a message's file the owner may see, as the panel's
 * version history lists them (ws_file_history()).
 */
function ws_api_message_file_history($params)
{
    $viewer = ws_api_viewer();
    $message = ws_message((int) $params['id']);

    // The panel's reach: a message of a channel the person reads, not
    // deleted for everyone; an earlier version of the conversation is read
    // as well.
    if (!$message || ((int) $message['deleted_at'] > 0) || !ws_can_read_channel($viewer, ws_channel($message['channel_id']))) {
        api_fail_not_found(lang('Message'));
    }

    ws_api_channel_or_404($viewer, $message['channel_id']);

    api_ok(array('versions' => array_map('ws_api_message_file_version', ws_file_history((int) $message['id'], (int) $viewer['id']))));
}
