<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - a canvas as one thing in the external API and MCP: the card
 * of a channel message with its parts (a drawing, a live page, the tables
 * bound to it) and one list of versions, the tables bound and taken off,
 * and a new version of a part written by its address.
 *
 * The parts themselves are kept where they were: a drawing in the canvas
 * document of the message's file (canvas.php, api_canvas.php), a live page
 * in the html versions of it (page.php, api_page.php), the bindings in
 * ws_canvas_tables (canvas_tables.php). The older message addresses stay
 * as they are; these routes read and write the same parts under one name.
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

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/canvas_tables.php');

/**
 * The canvas routes, merged into ws_api_routes().
 *
 * @return array[]
 */
function ws_canvases_api_routes()
{
    $id = array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true, 'description' => 'The canvas: the id of the message carrying it.');
    $bound = ' A canvas uses at most ' . WS_CANVAS_MAX_TABLES . ' tables.';

    return array(
        array(
            'id'          => 'workspace.canvases.get',
            'method'      => 'GET',
            'path'        => '/workspace/canvases/{id}',
            'scope'       => 'workspace:read',
            'handler'     => 'ws_api_canvases_get',
            'returns'     => ws_api_canvas_one_returns(),
            'summary'     => 'Read a canvas with its parts',
            'description' => 'A canvas of a channel as one thing: its name, its channel and the parts it has - a drawing (GET /workspace/messages/{id}/canvas reads its layers), a live page (GET /workspace/messages/{id}/page reads its HTML) and the workflow tables bound to it, which its page may read or write through the panel. A part the canvas does not have is null. versions is one list over the parts, oldest first, each saying which part it belongs to; each part keeps its own numbering. A table the caller\'s owner does not reach is counted with no name and reached false. A message that carries neither a drawing nor a page answers 404 not_found.',
            'params'      => array($id),
        ),

        array(
            'id'          => 'workspace.canvases.tables.bind',
            'method'      => 'POST',
            'path'        => '/workspace/canvases/{id}/tables',
            'scope'       => 'workspace:write',
            'handler'     => 'ws_api_canvases_tables_bind',
            'dry_run'     => true,
            'returns'     => ws_api_canvas_one_returns(),
            'summary'     => 'Bind a workflow table to a canvas',
            'description' => 'Lets the canvas\'s live page read a workflow table, or read and write it: the page asks the panel, which answers for the bound tables and no other, with the rights of the person looking at it - never with the owner\'s. Binding a table already bound changes its access. Only where the owner of the application may write in the canvas\'s channel and may change the table, for a read binding too, so a table nobody may change is not put under a page others run (403 forbidden); a table that is not there or out of the owner\'s reach is 404 not_found.' . $bound . ' One more answers 409 too_many_tables. The answer is the canvas as GET /workspace/canvases/{id} gives it.',
            'params'      => array(
                $id,
                array('name' => 'table_id', 'in' => 'body', 'type' => 'int', 'min' => 1, 'required' => true, 'description' => 'The workflow table (GET /workspace/tables).'),
                array('name' => 'access', 'in' => 'body', 'type' => 'enum', 'values' => array('read', 'write'), 'default' => 'read', 'description' => 'read: the page reads the rows. write: it also adds and changes rows, in the name of whoever opens it, who is asked first.'),
            ),
        ),

        array(
            'id'          => 'workspace.canvases.tables.unbind',
            'method'      => 'DELETE',
            'path'        => '/workspace/canvases/{id}/tables/{table_id}',
            'scope'       => 'workspace:write',
            'handler'     => 'ws_api_canvases_tables_unbind',
            'dry_run'     => true,
            'returns'     => ws_api_canvas_one_returns(),
            'summary'     => 'Take a workflow table off a canvas',
            'description' => 'Takes a table off the canvas: its page reaches it no longer. Only where the owner may write in the channel and may change the table (403 forbidden); a table the owner no longer reaches may be taken off by anyone who may write in the channel, so a binding never outlives everybody\'s reach. A table the canvas does not bind answers 404 not_found. POST /workspace/canvases/{id}/tables/{table_id}/delete does the same for servers that refuse DELETE.',
            'params'      => array(
                $id,
                array('name' => 'table_id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true, 'description' => 'The bound table.'),
            ),
        ),

        array(
            'id'          => 'workspace.canvases.tables.unbind_post',
            'method'      => 'POST',
            'path'        => '/workspace/canvases/{id}/tables/{table_id}/delete',
            'scope'       => 'workspace:write',
            'handler'     => 'ws_api_canvases_tables_unbind',
            'dry_run'     => true,
            'returns'     => ws_api_canvas_one_returns(),
            'summary'     => 'Take a workflow table off a canvas, addressed as a POST',
            'description' => 'The same as DELETE /workspace/canvases/{id}/tables/{table_id}, for clients and servers that cannot send DELETE.',
            'params'      => array(
                $id,
                array('name' => 'table_id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true, 'description' => 'The bound table.'),
            ),
        ),

        array(
            'id'           => 'workspace.canvases.page.put',
            'method'       => 'POST',
            'also_accepts' => array('PATCH', 'PUT'),
            'path'         => '/workspace/canvases/{id}/page',
            'scope'        => 'workspace:write',
            'handler'      => 'ws_api_canvases_page_put',
            'dry_run'      => true,
            'returns'      => ws_api_canvas_one_returns(),
            'summary'      => 'Write a new version of a canvas\'s page',
            'description'  => 'Replaces the live page of the canvas with a whole new document, kept as a new version made by the agent; the earlier ones stay among the versions and a member of the channel may undo the edit. version is the number the page part has now (GET /workspace/canvases/{id}): when it changed since, the call answers 409 conflict. A canvas with no page part answers 409 part_missing: make the page with POST /workspace/channels/{id}/pages, which posts it into the channel as its own card. Only an agent of the channel writes, as for that route (403 forbidden). The answer is the canvas as GET /workspace/canvases/{id} gives it.',
            'params'       => ws_api_canvas_one_page_params($id),
        ),

        array(
            'id'           => 'workspace.canvases.drawing.put',
            'method'       => 'POST',
            'also_accepts' => array('PATCH', 'PUT'),
            'path'         => '/workspace/canvases/{id}/drawing',
            'scope'        => 'workspace:write',
            'handler'      => 'ws_api_canvases_drawing_put',
            'dry_run'      => true,
            'returns'      => ws_api_canvas_one_returns(),
            'summary'      => 'Write a new version of a canvas\'s drawing',
            'description'  => 'Draws the canvas anew from SVG, kept as a new version made by the agent, as POST /workspace/messages/{id}/canvas does with single operations; the earlier versions stay and a member may undo the edit. version is the number the drawing part has now (GET /workspace/canvases/{id}): when it changed since, the call answers 409 conflict. A canvas with no drawing part answers 409 part_missing: draw one with POST /workspace/channels/{id}/canvases. The picture is drawn by the server where it can, else by the first member\'s browser that shows it (picture pending). Only an agent of the channel writes (403 forbidden).',
            'params'       => ws_api_canvas_one_drawing_params($id),
        ),
    );
}

/**
 * The parameters of the page route: the page edit's, under the canvas's
 * own address.
 *
 * @param array $id the path parameter
 * @return array[]
 */
function ws_api_canvas_one_page_params($id)
{
    $params = array($id, array('name' => 'version', 'in' => 'body', 'type' => 'int', 'min' => 1, 'required' => true, 'description' => 'The number the page part has now.'));

    foreach (ws_page_api_routes() as $route) {
        if ($route['id'] !== 'workspace.messages.page.edit') {
            continue;
        }

        foreach ($route['params'] as $param) {
            if (in_array($param['name'], array('html', 'libraries'), true)) {
                $params[] = $param;
            }
        }
    }

    return $params;
}

/**
 * The parameters of the drawing route: the create route's drawing ones,
 * with the version the write is made against.
 *
 * @param array $id the path parameter
 * @return array[]
 */
function ws_api_canvas_one_drawing_params($id)
{
    $params = array($id, array('name' => 'version', 'in' => 'body', 'type' => 'int', 'min' => 1, 'required' => true, 'description' => 'The number the drawing part has now.'));

    foreach (ws_canvas_api_routes() as $route) {
        if ($route['id'] !== 'workspace.channels.canvases.create') {
            continue;
        }

        foreach ($route['params'] as $param) {
            if (in_array($param['name'], array('svg', 'background', 'layers'), true)) {
                $params[] = ($param['name'] === 'svg') ? array('required' => true) + $param : $param;
            }
        }
    }

    return $params;
}

/**
 * The canvas as the routes answer it, declared inline.
 *
 * @return array
 */
function ws_api_canvas_one_returns()
{
    return array(
        'id'         => 'integer',
        'name'       => 'string',
        'channel_id' => 'integer',
        'parts'      => array(
            'drawing' => array(
                'artboard' => array('width' => 'integer', 'height' => 'integer', 'background' => 'string'),
                'picture'  => 'string',
                'layers'   => 'integer',
                'version'  => 'integer',
            ),
            'page'    => array(
                'size'      => 'integer',
                'version'   => 'integer',
                'libraries' => array('string'),
            ),
            'data'    => array(array(
                'id'        => 'integer',
                'name'      => 'string',
                'access'    => 'string',
                'reached'   => 'boolean',
                'row_count' => 'integer?',
            )),
        ),
        'versions'   => array(array(
            'part'       => 'string',
            'version'    => 'integer',
            'source'     => 'string',
            'user_id'    => 'integer?',
            'agent_id'   => 'integer?',
            'agent'      => 'string?',
            'size'       => 'integer?',
            'created_at' => 'string?',
        )),
    );
}

/**
 * The versions of the parts as one list: oldest first, each saying which
 * part it belongs to, a part's own numbering kept. Pure.
 *
 * @param array $parts part name => version rows (version, source, user_id,
 *                     agent_id, agent, size, created_at as a moment)
 * @return array[]
 */
function ws_canvas_versions_merge($parts)
{
    $merged = array();

    foreach ($parts as $part => $rows) {
        foreach ((array) $rows as $row) {
            $merged[] = array(
                'part'       => (string) $part,
                'version'    => (int) ($row['version'] ?? 0),
                'source'     => (string) ($row['source'] ?? ''),
                'user_id'    => isset($row['user_id']) ? $row['user_id'] : null,
                'agent_id'   => isset($row['agent_id']) ? $row['agent_id'] : null,
                'agent'      => isset($row['agent']) ? $row['agent'] : null,
                'size'       => isset($row['size']) ? (int) $row['size'] : null,
                'created_at' => (int) ($row['created_at'] ?? 0),
            );
        }
    }

    // Oldest first; two versions of the same second keep the part's own
    // order, so a list never reads as if a part went backwards.
    usort($merged, function ($a, $b) {
        if ($a['created_at'] !== $b['created_at']) {
            return ($a['created_at'] < $b['created_at']) ? -1 : 1;
        }

        if ($a['part'] !== $b['part']) {
            return strcmp($a['part'], $b['part']);
        }

        return ($a['version'] === $b['version']) ? 0 : (($a['version'] < $b['version']) ? -1 : 1);
    });

    return $merged;
}

/**
 * The tables bound to the canvas as the API gives them: the name and the
 * row count of each the owner reaches, the rest counted and nameless.
 *
 * @param array $viewer
 * @param int   $message_id
 * @return array[]
 */
function ws_api_canvas_one_tables($viewer, $message_id)
{
    $out = array();

    foreach (ws_canvas_tables($message_id) as $row) {
        $table = ws_wtable_for($viewer, $row['table_id'], 'view');
        $out[] = array(
            'id'        => (int) $row['table_id'],
            'name'      => $table ? (string) $table['name'] : '',
            'access'    => (string) $row['access'],
            'reached'   => (bool) $table,
            'row_count' => $table ? (int) $table['row_count'] : null,
        );
    }

    return $out;
}

/**
 * The canvas of a message as the API gives it, or null when the message
 * carries neither a drawing nor a page.
 *
 * @param array $viewer
 * @param array $message ws_messages row
 * @return array|null
 */
function ws_api_canvas_one_present($viewer, $message)
{
    $drawing = ws_api_canvas_present($message);
    $page = ws_api_page_present($message);

    if (($drawing === null) && ($page === null)) {
        return null;
    }

    $versions = array();

    if ($drawing !== null) {
        $versions['drawing'] = $drawing['versions'];
    }

    if ($page !== null) {
        $versions['page'] = ws_page_versions_read(ws_page_versions((int) $message['file_id']));
    }

    $merged = ws_canvas_versions_merge($versions);

    foreach ($merged as $i => $row) {
        $merged[$i]['created_at'] = api_time($row['created_at']);
    }

    return array(
        'id'         => (int) $message['id'],
        'name'       => (string) $message['file_name'],
        'channel_id' => (int) $message['channel_id'],
        'parts'      => array(
            'drawing' => ($drawing === null) ? null : array(
                'artboard' => $drawing['artboard'],
                'picture'  => $drawing['picture'],
                'layers'   => count($drawing['layers']),
                'version'  => $drawing['version'],
            ),
            'page'    => ($page === null) ? null : array(
                'size'      => $page['size'],
                'version'   => $page['version'],
                'libraries' => $page['libraries'],
            ),
            'data'    => ws_api_canvas_one_tables($viewer, (int) $message['id']),
        ),
        'versions'   => $merged,
    );
}

/**
 * The canvas a call names, with the message it is kept on, or the refusal.
 *
 * @param array $viewer
 * @param int   $id
 * @return array message, canvas (the presented canvas)
 */
function ws_api_canvas_one_or_404($viewer, $id)
{
    $message = ws_api_message_or_404($viewer, $id);
    $canvas = ws_api_canvas_one_present($viewer, $message);

    if ($canvas === null) {
        api_fail_not_found(lang('Canvas'));
    }

    return array('message' => $message, 'canvas' => $canvas);
}

/**
 * The canvas a write names: the message, its channel and the canvas, with
 * the channel checked for a write. The parts' own writers check who may
 * write them (ws_api_canvas_agent()).
 *
 * @param array $viewer
 * @param int   $id
 * @return array message, channel, canvas
 */
function ws_api_canvas_one_write_target($viewer, $id)
{
    $found = ws_api_canvas_one_or_404($viewer, $id);
    $channel = ws_api_channel_or_404($viewer, $found['message']['channel_id']);

    if (!ws_can_post_channel($viewer, $channel)) {
        api_fail(403, 'forbidden', lang('You cannot post in that channel.'));
    }

    return $found + array('channel' => $channel);
}

function ws_api_canvases_get($params)
{
    $viewer = ws_api_viewer();
    $found = ws_api_canvas_one_or_404($viewer, $params['id']);

    api_ok($found['canvas']);
}

function ws_api_canvases_tables_bind($params)
{
    $viewer = ws_api_viewer();

    if (!ws_canvas_tables_ready()) {
        api_fail(503, 'service_unavailable', lang('The database has to be updated first.'));
    }

    $found = ws_api_canvas_one_write_target($viewer, $params['id']);
    $message = $found['message'];
    $table_id = (int) $params['table_id'];
    $access = (string) ($params['access'] ?? 'read');
    $code = ws_canvas_table_bind_check($viewer, $message, $table_id, $access);

    if ($code !== '') {
        ws_api_canvas_table_fail($code);
    }

    api_dry_run_stop('updated', 'workspace_canvas_table', array(
        'canvas_id' => (int) $message['id'],
        'table_id'  => $table_id,
        'access'    => $access,
    ));

    $result = ws_canvas_table_bind($viewer, $message, $table_id, $access, ws_api_canvas_table_binder());

    if (!$result['ok']) {
        ws_api_canvas_table_fail($result['error']);
    }

    api_ok(ws_api_canvas_one_present($viewer, ws_message($message['id'])));
}

function ws_api_canvases_tables_unbind($params)
{
    $viewer = ws_api_viewer();

    if (!ws_canvas_tables_ready()) {
        api_fail(503, 'service_unavailable', lang('The database has to be updated first.'));
    }

    $found = ws_api_canvas_one_write_target($viewer, $params['id']);
    $message = $found['message'];
    $table_id = (int) $params['table_id'];

    if (!ws_canvas_table_binding((int) $message['id'], $table_id)) {
        api_fail_not_found(lang('Bound table'));
    }

    $code = ws_canvas_table_unbind_check($viewer, $message, $table_id);

    if ($code !== '') {
        ws_api_canvas_table_fail($code);
    }

    api_dry_run_stop('deleted', 'workspace_canvas_table', array(
        'canvas_id' => (int) $message['id'],
        'table_id'  => $table_id,
    ));

    $result = ws_canvas_table_unbind($viewer, $message, $table_id);

    if (!$result['ok']) {
        ws_api_canvas_table_fail($result['error']);
    }

    api_ok(ws_api_canvas_one_present($viewer, ws_message($message['id'])));
}

/**
 * A binding's refusal as the API answers it; never returns.
 *
 * @param string $code ws_canvas_table_bind_refusal()
 */
function ws_api_canvas_table_fail($code)
{
    $text = ws_canvas_table_refusal_text($code);

    if ($code === 'not_found') {
        api_fail_not_found(lang('Workflow table'));
    }

    if ($code === 'too_many') {
        api_fail(409, 'too_many_tables', $text);
    }

    if ($code === 'access_invalid') {
        api_fail_validation($text, 'access');
    }

    api_fail(403, 'forbidden', $text);
}

/**
 * Who a binding written now is kept under: the application, or the person
 * when they write from their own device.
 *
 * @return array kind (app|user), id
 */
function ws_api_canvas_table_binder()
{
    $app_id = ws_api_writer_app_id();

    return ($app_id > 0) ? array('kind' => 'app', 'id' => $app_id) : array('kind' => 'user', 'id' => 0);
}

function ws_api_canvases_page_put($params)
{
    $viewer = ws_api_viewer();
    $found = ws_api_canvas_one_write_target($viewer, $params['id']);

    if ($found['canvas']['parts']['page'] === null) {
        api_fail(409, 'part_missing', lang('This canvas has no live page: make one with POST /workspace/channels/{id}/pages.'));
    }

    ws_api_page_new_version($viewer, $found['message'], $found['channel'], $params);

    api_ok(ws_api_canvas_one_present($viewer, ws_message($found['message']['id'])));
}

function ws_api_canvases_drawing_put($params)
{
    $viewer = ws_api_viewer();
    $found = ws_api_canvas_one_write_target($viewer, $params['id']);

    if ($found['canvas']['parts']['drawing'] === null) {
        api_fail(409, 'part_missing', lang('This canvas has no drawing: draw one with POST /workspace/channels/{id}/canvases.'));
    }

    ws_api_canvas_new_version($viewer, $found['message'], $found['channel'], $params);

    api_ok(ws_api_canvas_one_present($viewer, ws_message($found['message']['id'])));
}
