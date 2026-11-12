<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - live pages an agent makes, in the external API and MCP: make
 * one in a channel (POST /workspace/channels/{id}/pages), read its source
 * (GET /workspace/messages/{id}/page) and write a new version of it (PATCH
 * the same address). Storage and the mark of a page are in page.php; the
 * guarded frame that shows it is includes/fn/guarded_frame.php.
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

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/page.php');

/**
 * The page routes, merged into ws_api_routes().
 *
 * @return array[]
 */
function ws_page_api_routes()
{
    $limit = 'The HTML is one whole document, its styles and script inside it, UTF-8, at most 500 KB. It runs only in a guarded frame of the panel, in an origin of its own: no cookie or storage of the panel, no request out of it (no fetch, socket or form; pictures and fonts only as data: inside the document), no window, download or move to another address.';

    return array(
        array(
            'id'          => 'workspace.channels.pages.create',
            'method'      => 'POST',
            'path'        => '/workspace/channels/{id}/pages',
            'scope'       => 'workspace:write',
            'handler'     => 'ws_api_pages_create',
            'dry_run'     => true,
            'status'      => 201,
            'returns'     => array('message' => 'WorkspaceMessage', 'page' => ws_api_page_returns()),
            'summary'     => 'Make a live page in a channel',
            'description' => 'Posts a live page into a channel as a message of the application: an HTML document a person opens and uses in the channel, such as a chart that shows a value on hover, a filter or a small tool. ' . $limit . ' Only an agent of the channel makes one: an application with a row in GET /workspace/channels/{id}/agents (or Claude, where Claude may be asked), in a channel the owner may post in. The first 20 versions are not all kept: the first and the newest 19 are. Refusals: no HTML, over 500 KB or not UTF-8 422 validation_failed on html; not an agent of the channel 403 forbidden.',
            'params'      => array(
                array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
                array('name' => 'html', 'in' => 'body', 'type' => 'string', 'max_length' => 512000, 'required' => true, 'description' => 'The page, one whole HTML document.'),
                array('name' => 'text', 'in' => 'body', 'type' => 'string', 'max_length' => 4000, 'description' => 'Words beside the page. Tags are written as tokens, as in a message.'),
                array('name' => 'name', 'in' => 'body', 'type' => 'string', 'max_length' => 120, 'description' => 'The name of the page\'s file; Page when left out. Always .html.'),
                array('name' => 'parent_id', 'in' => 'body', 'type' => 'int', 'min' => 1, 'description' => 'The message the page answers, of this channel.'),
            ),
        ),

        array(
            'id'          => 'workspace.messages.page.get',
            'method'      => 'GET',
            'path'        => '/workspace/messages/{id}/page',
            'scope'       => 'workspace:read',
            'handler'     => 'ws_api_page_get',
            'returns'     => ws_api_page_returns(),
            'summary'     => 'Read the live page of a message',
            'description' => 'The live page a message carries: its HTML as it is now, the number of its newest version and who made each version kept (the agent\'s handle). A message that carries no live page answers 404 not_found.',
            'params'      => array(
                array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
            ),
        ),

        array(
            'id'           => 'workspace.messages.page.edit',
            'method'       => 'PATCH',
            'also_accepts' => array('POST'),
            'path'         => '/workspace/messages/{id}/page',
            'scope'        => 'workspace:write',
            'handler'      => 'ws_api_page_edit',
            'dry_run'      => true,
            'returns'      => ws_api_page_returns(),
            'summary'      => 'Write a new version of a live page',
            'description'  => 'Replaces a live page of the channel with a whole new document, kept as a new version made by the agent; the earlier ones stay among the versions. ' . $limit . ' version is the newest version you read (GET /workspace/messages/{id}/page): when the page changed since, the call answers 409 conflict; read it again and redo. Only an agent of the channel edits, as for POST /workspace/channels/{id}/pages (403 forbidden). POST is accepted as well.',
            'params'       => array(
                array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
                array('name' => 'version', 'in' => 'body', 'type' => 'int', 'min' => 1, 'required' => true, 'description' => 'The newest version you read.'),
                array('name' => 'html', 'in' => 'body', 'type' => 'string', 'max_length' => 512000, 'required' => true, 'description' => 'The page, one whole HTML document.'),
            ),
        ),
    );
}

/**
 * The page as the routes answer it, declared inline.
 *
 * @return array
 */
function ws_api_page_returns()
{
    return array(
        'message_id' => 'integer',
        'file_id'    => 'integer',
        'name'       => 'string',
        'html'       => 'string',
        'size'       => 'integer',
        'version'    => 'integer',
        'versions'   => array(array(
            'version'    => 'integer',
            'source'     => 'string',
            'user_id'    => 'integer?',
            'agent_id'   => 'integer?',
            'agent'      => 'string?',
            'size'       => 'integer',
            'created_at' => 'string?',
        )),
    );
}

/**
 * The page of a message as the API gives it, or null when it carries none.
 *
 * @param array $message ws_messages row
 * @return array|null
 */
function ws_api_page_present($message)
{
    $file_id = (int) $message['file_id'];
    $rows = ws_page_versions($file_id);
    $stored = $rows ? (string) db_value("SELECT name FROM files WHERE id = '" . $file_id . "'") : '';
    $path = ($stored !== '') ? pg_file_reader_path($stored) : '';
    $html = ($path !== '') ? @file_get_contents($path) : false;

    if (!$rows || !is_string($html)) {
        return null;
    }

    $last = end($rows);

    return array(
        'message_id' => (int) $message['id'],
        'file_id'    => $file_id,
        'name'       => (string) $message['file_name'],
        'html'       => $html,
        'size'       => strlen($html),
        'version'    => (int) $last['version_no'],
        'versions'   => ws_page_versions_read($rows, 'api_time'),
    );
}

/**
 * The HTML of a call, or the refusal.
 *
 * @param array $params
 * @return string
 */
function ws_api_page_html($params)
{
    $html = $params['html'] ?? null;
    $error = ws_page_html_error($html);

    if ($error !== '') {
        api_fail_validation(ws_page_html_error_text($error), 'html');
    }

    return $html;
}

function ws_api_pages_create($params)
{
    $viewer = ws_api_viewer();
    $channel = ws_api_channel_or_404($viewer, $params['id']);

    if (!ws_can_post_channel($viewer, $channel)) {
        api_fail(403, 'forbidden', lang('You cannot post in that channel.'));
    }

    $agent = ws_api_canvas_agent($channel, lang('A live page is made here only by an agent of the channel.'));

    // The versions mark the file as a page: without the table there is none.
    if (!pg_file_versions_ready()) {
        api_fail(503, 'service_unavailable', lang('The database has to be updated first.'));
    }

    $html = ws_api_page_html($params);
    $text = (string) ($params['text'] ?? '');
    $app_id = ws_api_writer_app_id();
    $name = ws_page_file_name($params['name'] ?? '');

    // The checks ws_message_send_file_bytes() makes but the refusal of an
    // .html upload: only this route keeps one.
    $checked = ws_message_send_check($viewer, $channel, $text, array('app_id' => $app_id, 'file_id' => PHP_INT_MAX));

    if (!$checked['ok']) {
        api_fail_validation($checked['error'], 'text');
    }

    $parent_id = (int) ($params['parent_id'] ?? 0);
    $refused = ws_message_parent_check($channel, $parent_id);

    if ($refused !== '') {
        api_fail_validation($refused, 'parent_id');
    }

    api_dry_run_stop('created', 'workspace_page', array(
        'channel_id' => (int) $channel['id'],
        'name'       => $name,
        'size'       => strlen($html),
        'parent_id'  => ($parent_id > 0) ? $parent_id : null,
    ));

    $stored = ws_store_file_bytes($channel, $name, 'html', $html, (int) $viewer['id']);

    if (!$stored['ok']) {
        api_fail(503, 'service_unavailable', $stored['error']);
    }

    if (!ws_page_version_add($stored['file_id'], $html, $agent, (int) $viewer['id'])) {
        ws_upload_discard($stored['file_id']);
        api_fail(503, 'service_unavailable', lang('The file could not be saved.'));
    }

    $result = ws_message_send($viewer, $channel, $text, array(
        'app_id'    => $app_id,
        'parent_id' => $parent_id,
        'file_id'   => $stored['file_id'],
        'file_name' => $stored['name'],
    ));

    if (!$result['ok']) {
        pg_file_versions_delete_file($stored['file_id']);
        ws_upload_discard($stored['file_id']);
        api_fail_validation($result['error'], 'text');
    }

    $row = ws_message($result['message_id']);

    api_ok(array(
        'message' => ws_api_message_present($viewer, $row, ws_refs_resolve($viewer, ws_tokens($row['body']))),
        'page'    => ws_api_page_present($row),
    ), 201);
}

function ws_api_page_get($params)
{
    $viewer = ws_api_viewer();
    $message = ws_api_message_or_404($viewer, $params['id']);
    $page = ws_api_page_present($message);

    if ($page === null) {
        api_fail_not_found(lang('Live page'));
    }

    api_ok($page);
}

function ws_api_page_edit($params)
{
    $viewer = ws_api_viewer();
    $message = ws_api_message_or_404($viewer, $params['id']);
    $channel = ws_api_channel_or_404($viewer, $message['channel_id']);

    if (!ws_can_post_channel($viewer, $channel)) {
        api_fail(403, 'forbidden', lang('You cannot post in that channel.'));
    }

    $agent = ws_api_canvas_agent($channel, lang('A live page is made here only by an agent of the channel.'));
    $rows = ws_page_versions((int) $message['file_id']);

    if (!$rows) {
        api_fail_not_found(lang('Live page'));
    }

    $newest = (int) end($rows)['version_no'];

    if ((int) $params['version'] !== $newest) {
        api_fail(409, 'conflict', lang(array('string' => 'The page changed since: its newest version is {var:1}. Read it again.', 'vars' => $newest)));
    }

    $html = ws_api_page_html($params);

    api_dry_run_stop('updated', 'workspace_page', array(
        'message_id' => (int) $message['id'],
        'version'    => $newest + 1,
        'size'       => strlen($html),
    ));

    if (!ws_page_write($message, $html, $agent, (int) $viewer['id'])) {
        api_fail(503, 'service_unavailable', lang('The file could not be saved.'));
    }

    api_ok(ws_api_page_present($message));
}
