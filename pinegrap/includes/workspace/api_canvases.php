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

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/canvas_parts.php');
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
            'description' => 'Lets the canvas\'s live page read a workflow table, or read and write it: the page asks the panel, which answers for the bound tables and no other, with the rights of the person looking at it - never with the owner\'s. Binding a table already bound changes its access. Only where the owner of the application may write in the canvas\'s channel and may change the table, for a read binding too, so a table nobody may change is not put under a page others run (403 forbidden); a table that is not there or out of the owner\'s reach is 404 not_found.' . $bound . ' One more answers 409 too_many_tables, and a canvas with no live page 409 part_missing, since a drawing reads no table: add the page first with POST /workspace/canvases/{id}/page. The answer is the canvas as GET /workspace/canvases/{id} gives it.',
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
            'also_accepts' => array('PATCH'),
            'path'         => '/workspace/canvases/{id}/page',
            'scope'        => 'workspace:write',
            'handler'      => 'ws_api_canvases_page_put',
            'dry_run'      => true,
            'returns'      => ws_api_canvas_one_returns(),
            'summary'      => 'Write a new version of a canvas\'s page',
            'description'  => 'Replaces the live page of the canvas with a whole new document, kept as a new version made by the agent; the earlier ones stay among the versions and a member of the channel may undo the edit. version is the number the page part has now (GET /workspace/canvases/{id}): when it changed since, the call answers 409 conflict. On a canvas with no page, version 0 adds one beside its drawing: the same canvas then has both parts, and its tables are bound to that page. A page added at the same moment by another call answers 409 conflict. Only an agent of the channel writes, as for that route (403 forbidden). The answer is the canvas as GET /workspace/canvases/{id} gives it.',
            'params'       => ws_api_canvas_one_page_params($id),
        ),

        array(
            'id'           => 'workspace.canvases.drawing.put',
            'method'       => 'POST',
            'also_accepts' => array('PATCH'),
            'path'         => '/workspace/canvases/{id}/drawing',
            'scope'        => 'workspace:write',
            'handler'      => 'ws_api_canvases_drawing_put',
            'dry_run'      => true,
            'returns'      => ws_api_canvas_one_returns(),
            'summary'      => 'Write a new version of a canvas\'s drawing',
            'description'  => 'Draws the canvas anew from SVG, kept as a new version made by the agent, as POST /workspace/messages/{id}/canvas does with single operations; the earlier versions stay and a member may undo the edit. version is the number the drawing part has now (GET /workspace/canvases/{id}): when it changed since, the call answers 409 conflict. On a canvas with no drawing, version 0 adds one beside its page; a drawing added at the same moment by another call answers 409 conflict. The picture is drawn by the server where it can, else by the first member\'s browser that shows it (picture pending). Only an agent of the channel writes (403 forbidden).',
            'params'       => ws_api_canvas_one_drawing_params($id),
        ),

        array(
            'id'          => 'workspace.canvases.versions.restore',
            'method'      => 'POST',
            'path'        => '/workspace/canvases/{id}/versions/{n}/restore',
            'scope'       => 'workspace:write',
            'handler'     => 'ws_api_canvases_version_restore',
            'dry_run'     => true,
            'returns'     => ws_api_canvas_one_returns(),
            'summary'     => 'Put an older version of a canvas\'s part back',
            'description' => 'Puts version n of the drawing or the page back as the newest one, as "Put back this version" on the canvas card does: a new version (source restore), so every version stays. Whoever may write in the channel may (403 forbidden otherwise); a version the part has not got answers 404, the version shown now 409 conflict, a drawing\'s version whose picture no browser drew yet 409 conflict. The answer is the canvas as GET /workspace/canvases/{id} gives it.',
            'params'      => array(
                $id,
                array('name' => 'n', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true, 'description' => 'The version of the part, as versions in GET /workspace/canvases/{id} numbers it.'),
                array('name' => 'part', 'in' => 'body', 'type' => 'enum', 'values' => ws_canvas_part_names(), 'required' => true, 'description' => 'The part the version is of; each part keeps its own numbering.'),
            ),
        ),

        array(
            'id'          => 'workspace.canvases.undo',
            'method'      => 'POST',
            'path'        => '/workspace/canvases/{id}/undo',
            'scope'       => 'workspace:write',
            'handler'     => 'ws_api_canvases_undo',
            // The person's own answer to an agent's edit (ws_api_person_only()).
            'mcp'         => false,
            'dry_run'     => true,
            'returns'     => ws_api_canvas_one_returns(),
            'summary'     => 'Undo an agent\'s edit of a canvas',
            'description' => 'Undo on the canvas card: part drawing or page puts back the version before an agent\'s edit, as a new version (the edit stays among the versions), while version is still the agent\'s edit the caller saw (409 conflict once it changed); part binding undoes an application\'s binding from its line in the channel (line_id, the line\'s message), taking the table off or binding it again as it was. The person alone, signed in on a device: an application key is refused with 403 forbidden. The panel\'s rule decides who else may: a drawing only its writer while they may write in the channel, a page whoever may write there, a binding whoever may bind or take off the table. No MCP tool.',
            'params'      => array(
                $id,
                array('name' => 'part', 'in' => 'body', 'type' => 'enum', 'values' => array('drawing', 'page', 'binding'), 'required' => true),
                array('name' => 'version', 'in' => 'body', 'type' => 'int', 'min' => 1, 'description' => 'With drawing or page: the version the agent\'s edit made.'),
                array('name' => 'line_id', 'in' => 'body', 'type' => 'int', 'min' => 1, 'description' => 'With binding: the id of the binding\'s line in the channel.'),
            ),
        ),

        array(
            'id'          => 'workspace.canvases.table_choices',
            'method'      => 'GET',
            'path'        => '/workspace/canvases/{id}/table-choices',
            'scope'       => 'workspace:read',
            'handler'     => 'ws_api_canvases_table_choices',
            'returns'     => array('list' => 'WorkspaceCanvasTableChoice'),
            'summary'     => 'The tables a canvas\'s page may bind',
            'description' => 'The workflow tables the owner of the application may write, as the page\'s "Bind a table" list offers them, each with the access it is bound with now (bound read or write; empty when it is not bound). Bind one with POST /workspace/canvases/{id}/tables. A canvas with no page, or one the owner may not read, answers 404.',
            'params'      => array($id),
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
    $params = array($id, array('name' => 'version', 'in' => 'body', 'type' => 'int', 'min' => 0, 'required' => true, 'description' => 'The number the page part has now; 0 to add a page to a canvas that has none.'));

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
    $params = array($id, array('name' => 'version', 'in' => 'body', 'type' => 'int', 'min' => 0, 'required' => true, 'description' => 'The number the drawing part has now; 0 to add a drawing to a canvas that has none.'));

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
            'created_at' => 'datetime?',
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
    // The times of both parts are formatted once, after the merge.
    $drawn = ws_canvas_part_message($message, 'drawing');
    $drawing = $drawn ? ws_api_canvas_present($drawn, null, true) : null;
    $rows = ws_page_versions(ws_canvas_part_file($message, 'page'));
    $page = $rows ? ws_page_versions_read($rows) : array();
    $newest = $rows ? end($rows) : null;
    $last = $page ? end($page) : null;

    if (($drawing === null) && !$page) {
        return null;
    }

    $versions = array();

    if ($drawing !== null) {
        $versions['drawing'] = $drawing['versions'];
    }

    if ($page) {
        $versions['page'] = $page;
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
            // The page part is read from its versions: the bytes of the
            // document itself are fetched by the page's own address.
            'page'    => ($last === null) ? null : array(
                'size'      => (int) $last['size'],
                'version'   => (int) $last['version'],
                'libraries' => ws_page_version_libraries($newest['doc_json']),
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

    $result = ws_canvas_table_bind($viewer, $message, $table_id, $access, ws_api_canvas_table_binder(ws_api_writer_app_id()));

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

    if ($code === 'not_page') {
        api_fail(409, 'part_missing', lang('This canvas has no live page: add one with POST /workspace/canvases/{id}/page.'));
    }

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
 * themselves when they write from their own device. Pure.
 *
 * @param int $app_id ws_api_writer_app_id(): 0 when the person writes
 * @return array kind (app|user), id
 */
function ws_api_canvas_table_binder($app_id)
{
    return ((int) $app_id > 0) ? array('kind' => 'app', 'id' => (int) $app_id) : array('kind' => 'user', 'id' => 0);
}

function ws_api_canvases_page_put($params)
{
    $viewer = ws_api_viewer();
    $found = ws_api_canvas_one_write_target($viewer, $params['id']);
    $part = ws_canvas_part_message($found['message'], 'page');

    if ($part !== null) {
        ws_api_page_new_version($viewer, $part, $found['channel'], $params);
    } else {
        ws_api_canvas_part_check($params, lang('The canvas has a page now: read it again.'));
        ws_api_canvas_page_add($viewer, $found['message'], $found['channel'], $params);
    }

    api_ok(ws_api_canvas_one_present($viewer, ws_message($found['message']['id'])));
}

function ws_api_canvases_drawing_put($params)
{
    $viewer = ws_api_viewer();
    $found = ws_api_canvas_one_write_target($viewer, $params['id']);
    $part = ws_canvas_part_message($found['message'], 'drawing');

    if ($part !== null) {
        ws_api_canvas_new_version($viewer, $part, $found['channel'], $params);
    } else {
        ws_api_canvas_part_check($params, lang('The canvas has a drawing now: read it again.'));
        ws_api_canvas_drawing_add($viewer, $found['message'], $found['channel'], $params);
    }

    api_ok(ws_api_canvas_one_present($viewer, ws_message($found['message']['id'])));
}

/**
 * What a call adding a part must hold: version 0, the part it adds not
 * being there, and the step that keeps parts having run. Never returns on
 * a refusal.
 *
 * @param array  $params
 * @param string $changed the refusal when version is not 0
 */
function ws_api_canvas_part_check($params, $changed)
{
    if ((int) $params['version'] !== 0) {
        api_fail(409, 'conflict', $changed);
    }

    if (!ws_canvas_parts_ready() || !pg_file_versions_ready()) {
        api_fail(503, 'service_unavailable', lang('The database has to be updated first.'));
    }
}

/**
 * Keeps a part's new file as the canvas's part, or takes it back when
 * another call named one first. Never returns on a refusal.
 *
 * @param array  $message
 * @param string $part
 * @param int    $file_id
 */
function ws_api_canvas_part_keep($message, $part, $file_id)
{
    if (!ws_canvas_part_add((int) $message['id'], $part, $file_id, ws_api_canvas_table_binder(ws_api_writer_app_id()))) {
        pg_file_versions_delete_file($file_id);
        ws_upload_discard($file_id);
        api_fail(409, 'conflict', lang('The canvas changed since: read it again.'));
    }

    // Open conversations and a client reading the channel's changes fetch
    // the canvas again.
    ws_message_touch((int) $message['id']);
}

/**
 * Adds a live page beside a canvas's drawing: a file of the channel,
 * named by the canvas's part row; the message stays as it is.
 *
 * @param array $viewer
 * @param array $message
 * @param array $channel
 * @param array $params html, libraries
 */
function ws_api_canvas_page_add($viewer, $message, $channel, $params)
{
    $agent = ws_api_canvas_agent($channel, lang('A live page is made here only by an agent of the channel.'));
    $html = ws_api_page_html($params);
    $libraries = ws_api_page_libraries($params['libraries'] ?? null);
    $name = ws_page_file_name((string) $message['file_name']);

    api_dry_run_stop('created', 'workspace_canvas_part', array(
        'canvas_id' => (int) $message['id'],
        'part'      => 'page',
        'size'      => strlen($html),
        'libraries' => $libraries,
    ));

    $stored = ws_store_file_bytes($channel, $name, 'html', $html, (int) $viewer['id']);

    if (!$stored['ok']) {
        api_fail(503, 'service_unavailable', $stored['error']);
    }

    if (!ws_page_version_add($stored['file_id'], $html, $agent, (int) $viewer['id'], $libraries)) {
        ws_upload_discard($stored['file_id']);
        api_fail(503, 'service_unavailable', lang('The file could not be saved.'));
    }

    ws_api_canvas_part_keep($message, 'page', (int) $stored['file_id']);
}

/**
 * Adds a drawing beside a canvas's page: a blank picture of the channel
 * keeping the document, named by the canvas's part row and drawn by the
 * server where it can; the message stays as it is.
 *
 * @param array $viewer
 * @param array $message
 * @param array $channel
 * @param array $params svg, background, layers
 */
function ws_api_canvas_drawing_add($viewer, $message, $channel, $params)
{
    $agent = ws_api_canvas_agent($channel);
    $doc = ws_api_canvas_svg_doc($params, $channel, $agent);
    $name = ws_canvas_file_name((string) $message['file_name']);
    $png = ws_canvas_blank_png($doc->artboard->width, $doc->artboard->height, $doc->artboard->background);

    api_dry_run_stop('created', 'workspace_canvas_part', array(
        'canvas_id' => (int) $message['id'],
        'part'      => 'drawing',
        'artboard'  => array('width' => (int) $doc->artboard->width, 'height' => (int) $doc->artboard->height, 'background' => (string) $doc->artboard->background),
        'layers'    => count($doc->layers),
    ));

    $stored = ws_store_file_bytes($channel, $name, 'png', $png, (int) $viewer['id']);

    if (!$stored['ok']) {
        api_fail(503, 'service_unavailable', $stored['error']);
    }

    if (!pg_image_editor_canvas_keep((int) $stored['file_id'], $doc, (int) $viewer['id'], 'agent')) {
        ws_upload_discard($stored['file_id']);
        api_fail(503, 'service_unavailable', lang('The file could not be saved.'));
    }

    ws_api_canvas_part_keep($message, 'drawing', (int) $stored['file_id']);
    ws_canvas_draw_now(array('file_id' => (int) $stored['file_id']) + $message);
}

/* ---------------------------------------------------------------------------
   A version put back, an agent's edit undone, the tables a page may bind
   --------------------------------------------------------------------------- */

/**
 * The status a canvas writer's refusal is answered with, and its code.
 * Pure.
 *
 * @param int $status the writer's status (403, 404, 409, 500, 503)
 * @return array status, code
 */
function ws_api_canvas_refusal_code($status)
{
    switch ((int) $status) {
        case 403:
            return array('status' => 403, 'code' => 'forbidden');

        case 404:
            return array('status' => 404, 'code' => 'not_found');

        case 503:
            return array('status' => 503, 'code' => 'service_unavailable');

        case 500:
            return array('status' => 500, 'code' => 'server_error');
    }

    return array('status' => 409, 'code' => 'conflict');
}

/**
 * A canvas writer's refusal as the API answers it; never returns.
 *
 * @param array $result ok, status, error
 */
function ws_api_canvas_refused($result)
{
    $answer = ws_api_canvas_refusal_code($result['status'] ?? 409);

    api_fail($answer['status'], $answer['code'], (string) $result['error']);
}

function ws_api_canvases_version_restore($params)
{
    $viewer = ws_api_viewer();
    $found = ws_api_canvas_one_write_target($viewer, $params['id']);
    $message = $found['message'];
    $part = (string) $params['part'];
    $version = (int) $params['n'];

    // The panel's own writer, asked first for its checks alone.
    $checked = ws_canvas_part_restore($viewer, $message, $part, $version, true);

    if (!$checked['ok']) {
        ws_api_canvas_refused($checked);
    }

    api_dry_run_stop('restored', 'workspace_canvas_version', array('canvas_id' => (int) $message['id'], 'part' => $part, 'version' => $version));

    $result = ws_canvas_part_restore($viewer, $message, $part, $version);

    if (!$result['ok']) {
        ws_api_canvas_refused($result);
    }

    api_ok(ws_api_canvas_one_present($viewer, ws_message($message['id'])));
}

/**
 * What an undo names, checked against the canvas: the message the panel's
 * undo of that part is given (the drawing's or the page's own message), or
 * the binding line, which must be one of this canvas. Pure.
 *
 * @param string     $part    drawing | page | binding
 * @param int        $canvas_id
 * @param array|null $line    ws_canvas_binding_line_refs() entry of line_id
 * @param int        $version
 * @return string '' when it is whole, else the field at fault
 */
function ws_api_canvas_undo_problem($part, $canvas_id, $line, $version)
{
    if ($part === 'binding') {
        return (is_array($line) && ((int) $line['canvas'] === (int) $canvas_id)) ? '' : 'line_id';
    }

    return ((int) $version > 0) ? '' : 'version';
}

function ws_api_canvases_undo($params)
{
    ws_api_person_only(lang('Only the person, signed in on a device, can undo an agent\'s edit; an application cannot do it for them.'));

    $viewer = ws_api_viewer();
    $found = ws_api_canvas_one_or_404($viewer, $params['id']);
    $message = $found['message'];
    $part = (string) $params['part'];
    $version = (int) ($params['version'] ?? 0);
    $line_id = (int) ($params['line_id'] ?? 0);
    $line = null;

    if ($part === 'binding') {
        if (!ws_canvas_tables_ready()) {
            api_fail(503, 'service_unavailable', lang('The database has to be updated first.'));
        }

        // The line is a message of a channel the person reads, of the
        // current conversation.
        if ($line_id > 0) {
            ws_api_message_or_404($viewer, $line_id);
            $line = ws_canvas_binding_line_refs(array($line_id))[$line_id] ?? null;
        }
    }

    $problem = ws_api_canvas_undo_problem($part, (int) $message['id'], $line, $version);

    if ($problem === 'line_id') {
        api_fail_not_found(lang('Binding line'));
    }

    if ($problem !== '') {
        api_fail_validation(lang('Name the version of the agent\'s edit.'), $problem);
    }

    $run = function ($check_only) use ($viewer, $message, $part, $version, $line_id) {
        if ($part === 'binding') {
            return ws_canvas_binding_undo($viewer, $line_id, $check_only);
        }

        $target = ws_canvas_part_message($message, $part);

        if (!$target) {
            return array('ok' => false, 'status' => 404, 'error' => lang('This canvas has no such part.'));
        }

        return ($part === 'page') ? ws_page_undo($viewer, $target, $version, $check_only) : ws_canvas_undo($viewer, $target, $version, $check_only);
    };

    $checked = $run(true);

    if (!$checked['ok']) {
        ws_api_canvas_refused($checked);
    }

    api_dry_run_stop('undone', 'workspace_canvas_edit', array('canvas_id' => (int) $message['id'], 'part' => $part, 'version' => ($part === 'binding') ? null : $version, 'line_id' => ($part === 'binding') ? $line_id : null));

    $result = $run(false);

    if (!$result['ok']) {
        ws_api_canvas_refused($result);
    }

    api_ok(ws_api_canvas_one_present($viewer, ws_message($message['id'])));
}

/**
 * The table choices as the API gives them: only the tables this caller
 * reaches (ws_api_wtable_reachable() by id), each with its binding's
 * access. Pure.
 *
 * @param array[] $choices   ws_canvas_tables_choices()
 * @param int[]   $reachable the ids of the tables the caller reaches
 * @return array[] table_id, name, bound
 */
function ws_api_canvas_table_choices_present($choices, $reachable)
{
    $reachable = array_flip(array_map('intval', (array) $reachable));
    $out = array();

    foreach ((array) $choices as $choice) {
        if (isset($reachable[(int) $choice['id']])) {
            $out[] = array('table_id' => (int) $choice['id'], 'name' => (string) $choice['name'], 'bound' => (string) $choice['bound']);
        }
    }

    return $out;
}

// What ws_api_canvas_table_choices_present() gives for each table.
function ws_api_canvas_table_choice_schema()
{
    return array('table_id' => 'integer', 'name' => 'string', 'bound' => 'string');
}

function ws_api_canvases_table_choices($params)
{
    $viewer = ws_api_viewer();
    $found = ws_api_canvas_one_or_404($viewer, $params['id']);
    $message = $found['message'];

    if (!ws_canvas_tables_ready() || !ws_canvas_page_reach($viewer, $message)) {
        api_fail_not_found(lang('Canvas'));
    }

    $reachable = array();

    foreach (ws_wtables_reached($viewer) as $table) {
        if (ws_api_wtable_reachable($viewer, $table)) {
            $reachable[] = (int) $table['id'];
        }
    }

    $out = ws_api_canvas_table_choices_present(ws_canvas_tables_choices($viewer, $message), $reachable);

    api_ok_list($out, count($out));
}
