<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - canvases an agent draws by writing SVG, in the external API
 * and MCP: make one in a channel (POST /workspace/channels/{id}/canvases)
 * and read any canvas of a message (GET /workspace/messages/{id}/canvas).
 * The document is the image editor's (canvas_svg.php builds it as Import
 * does), so people open it in the editor with its layers; its picture is
 * blank until a member's browser draws it.
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

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/canvas.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/canvas_svg.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/canvas_edit.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/canvas_split.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/canvas_layer_write.php');

/**
 * The canvas routes, merged into ws_api_routes().
 *
 * @return array[]
 */
function ws_canvas_api_routes()
{
    return array(
        array(
            'id'          => 'workspace.channels.canvases.create',
            'method'      => 'POST',
            'path'        => '/workspace/channels/{id}/canvases',
            'scope'       => 'workspace:write',
            'handler'     => 'ws_api_canvases_create',
            'dry_run'     => true,
            'status'      => 201,
            'returns'     => array('message' => 'WorkspaceMessage', 'canvas' => ws_api_canvas_returns()),
            'summary'     => 'Draw a canvas in a channel from SVG',
            'description' => 'Posts a canvas into a channel as a message of the application: the SVG becomes a picture people see and open in the image editor, its layers editable. Only an agent of the channel draws one: an application with a row in GET /workspace/channels/{id}/agents (or Claude, where Claude may be asked), in a channel the owner may post in. The SVG is sanitized (scripts, outside addresses and events go), at most 1 MB; the artboard is its root\'s width and height (the view box when they are missing, 300 x 150 when both are), each side 16 to 8000 pixels, the drawing one layer covering it, or with layers split a layer per top-level element as the image editor\'s Break apart makes them (each where it was drawn, named by its element\'s id; a group stays one layer; definitions and style rules kept for the layers that use them): a text becomes a text layer when the editor can hold it (one colour, no outline, a font the editor offers, lines as tspans under one another), a picture whose address is a file of the site - one a message of the channel carries, or with files:read one any visitor may fetch - a picture layer; anything else stays an svg layer. A picture inside it must be an address, not data. The canvas\'s picture is blank in the background colour until a member\'s browser draws it: canvas.picture is pending until then. Refusals: nothing left to draw, over 1 MB, a picture as data or a background that is no colour 422 validation_failed on svg or background; not an agent of the channel 403 forbidden.',
            'params'      => array(
                array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
                array('name' => 'svg', 'in' => 'body', 'type' => 'string', 'max_length' => 1048576, 'required' => true, 'description' => 'The drawing, a whole <svg> element with the SVG namespace.'),
                array('name' => 'text', 'in' => 'body', 'type' => 'string', 'max_length' => 4000, 'description' => 'Words beside the canvas. Tags are written as tokens, as in a message.'),
                array('name' => 'name', 'in' => 'body', 'type' => 'string', 'max_length' => 120, 'description' => 'The name of the canvas\'s file; Canvas when left out. Always a PNG.'),
                array('name' => 'background', 'in' => 'body', 'type' => 'string', 'max_length' => 11, 'description' => 'The artboard\'s colour as #rrggbb, or transparent; white when left out.'),
                array('name' => 'layers', 'in' => 'body', 'type' => 'enum', 'values' => array('split', 'one'), 'description' => 'split: a layer per top-level element (texts as text layers, pictures of the site\'s files as picture layers), so one part can be changed by its layer later; one (the default): the drawing as one layer.'),
                array('name' => 'parent_id', 'in' => 'body', 'type' => 'int', 'min' => 1, 'description' => 'The message the canvas answers, of this channel.'),
            ),
        ),

        array(
            'id'          => 'workspace.messages.canvas.get',
            'method'      => 'GET',
            'path'        => '/workspace/messages/{id}/canvas',
            'scope'       => 'workspace:read',
            'handler'     => 'ws_api_canvas_get',
            'returns'     => ws_api_canvas_returns(),
            'summary'     => 'Read the canvas of a message',
            'description' => 'The canvas a message carries, drawn by an agent or by a person in the image editor: its artboard, the address of its picture (an agent that sees pictures looks at it too; pending until a member\'s browser has drawn it), the number of its newest version and who made each version kept, and its layers from the bottom up - each with its id, kind, box on the artboard and name (null when it has none); a text layer\'s text and colour; an svg layer\'s markup. A message whose picture is no canvas, or was changed outside the editor since, answers 404 not_found.',
            'params'      => array(
                array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
            ),
        ),

        array(
            'id'           => 'workspace.messages.canvas.edit',
            'method'       => 'POST',
            'also_accepts' => array('PATCH'),
            'path'         => '/workspace/messages/{id}/canvas',
            'scope'        => 'workspace:write',
            'handler'      => 'ws_api_canvas_edit',
            'dry_run'      => true,
            'returns'      => ws_api_canvas_returns(),
            'summary'      => 'Edit a canvas in place',
            'description'  => 'Changes a canvas of the channel - an agent\'s or a person\'s - with operations applied in order to its newest version, all or none: add (svg; x, y, w, h, rot optional: a new layer on top, its own size made to fit and centred when no box is given; layers split: a layer per top-level element, placed where that one layer would have been), replace (layer, svg: an svg layer\'s drawing, its box kept), remove (layer), text (layer, text; color #rrggbb optional: a text layer\'s words), box (layer; any of x, y, w, h, rot: move, resize or turn a layer), svg (svg; background and layers optional: the whole canvas anew), style (layer and the look to change: a text\'s font, size, weight, italic, align, color, bg, lineHeight, spacing; a shape\'s color, fill none, solid or soft, stroke, dash solid, dashed or dotted, radius; an icon\'s color; any layer\'s opacity 0 to 1), order (layer, to front, back, above or below, target for above and below), name (layer, name: 1 to 80 letters no other layer has) and lock (layer, locked true or false; the only change a locked layer takes besides its name). add also takes type: text (text; font from GET /workspace/canvas/fonts, size, weight, italic, align, color, bg, lineHeight, spacing), picture (file_id or file, the address of a file of this channel, or with files:read of a public file), shape (shape rectangle or ellipse with x, y, w, h, or line with x1, y1, x2, y2; color, fill, stroke, dash, radius) or icon (icon, a Bootstrap Icons name such as bar-chart; color); the look\'s defaults are the image editor\'s, a layer without x and y is centred; and name and lock for any type. A layer is named by its id, or by its name when one layer alone has it. Layer ids and the version come from GET /workspace/messages/{id}/canvas. version is the newest version you read: when somebody saved since, the call answers 409 conflict; read again and redo. The edit is a new version made by the agent, kept with the original and the last ten; its picture is pending again until a member\'s browser draws it. A person whose canvas an agent edited is told. Only an agent of the channel edits, as for POST /workspace/channels/{id}/canvases (403 forbidden). A refused operation answers 422 validation_failed on operations, its message naming the operation by its place (from 0); a locked layer is not changed. PATCH is accepted as well.',
            'params'       => array(
                array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
                array('name' => 'version', 'in' => 'body', 'type' => 'int', 'min' => 1, 'required' => true, 'description' => 'The newest version you read.'),
                array('name' => 'operations', 'in' => 'body', 'type' => 'list', 'of' => array('op' => 'string', 'layer' => 'string', 'type' => 'string', 'svg' => 'string', 'text' => 'string', 'color' => 'string', 'background' => 'string', 'layers' => 'string', 'x' => 'number', 'y' => 'number', 'w' => 'number', 'h' => 'number', 'rot' => 'number',
                    'font' => 'string', 'size' => 'number', 'weight' => 'integer', 'italic' => 'boolean', 'align' => 'string', 'bg' => 'string', 'lineHeight' => 'number', 'spacing' => 'number', 'opacity' => 'number',
                    'shape' => 'string', 'fill' => 'string', 'stroke' => 'number', 'dash' => 'string', 'radius' => 'number', 'x1' => 'number', 'y1' => 'number', 'x2' => 'number', 'y2' => 'number',
                    'icon' => 'string', 'file' => 'string', 'file_id' => 'integer', 'name' => 'string', 'lock' => 'boolean', 'locked' => 'boolean', 'to' => 'string', 'target' => 'string'), 'max_items' => 20, 'required' => true, 'description' => 'Objects of {op, ...}, applied in order; see the description.'),
            ),
        ),

        array(
            'id'          => 'workspace.canvas.fonts',
            'method'      => 'GET',
            'path'        => '/workspace/canvas/fonts',
            'scope'       => 'workspace:read',
            'handler'     => 'ws_api_canvas_fonts',
            'returns'     => array('fonts' => array(array('font' => 'string', 'family' => 'string', 'source' => 'string'))),
            'summary'     => 'List the fonts a canvas text may use',
            'description' => 'The fonts the image editor offers for a text layer, each with the value to give as font in a canvas edit: the editor\'s system stacks (sans, serif, hand, display, mono), then the families the site\'s styles load and the Google Fonts catalogue, as g:<family>. source is system, site or google.',
            'params'      => array(),
        ),
    );
}

/**
 * The canvas as the routes answer it, declared inline.
 *
 * @return array
 */
function ws_api_canvas_returns()
{
    return array(
        'message_id' => 'integer',
        'file_id'    => 'integer',
        'name'       => 'string',
        'url'        => 'string',
        'api_url'    => 'string',
        'artboard'   => array('width' => 'integer', 'height' => 'integer', 'background' => 'string'),
        'picture'    => 'string',
        'version'    => 'integer',
        'layers'     => array(array(
            'id'     => 'string',
            'type'   => 'string',
            'kind'   => 'string?',
            'name'   => 'string?',
            'x'      => 'number',
            'y'      => 'number',
            'w'      => 'number',
            'h'      => 'number',
            'rot'    => 'number',
            'locked' => 'boolean',
            'text'   => 'string?',
            'color'  => 'string?',
            'markup' => 'string?',
        )),
        'versions'   => array(array(
            'version'    => 'integer',
            'source'     => 'string',
            'user_id'    => 'integer?',
            'agent_id'   => 'integer?',
            'agent'      => 'string?',
            'created_at' => 'string?',
        )),
    );
}

/**
 * The agent a version's document names as its maker (doc.by), for a
 * version an agent made; null otherwise. Pure.
 *
 * @param mixed $doc decoded as arrays
 * @return array|null id, kind, handle
 */
function ws_canvas_doc_agent($doc)
{
    $by = (is_array($doc) && isset($doc['by']) && is_array($doc['by'])) ? $doc['by'] : null;

    if (!$by || !isset($by['handle']) || !is_string($by['handle']) || ($by['handle'] === '')) {
        return null;
    }

    return array('id' => (int) ($by['id'] ?? 0), 'kind' => (string) ($by['kind'] ?? ''), 'handle' => $by['handle']);
}

/**
 * A layer as an agent reads it: what it is, its box, a text layer's text
 * and colour, an svg layer's markup. Pure.
 *
 * @param mixed $layer decoded as arrays
 * @return array|null
 */
function ws_canvas_layer_read($layer)
{
    if (!is_array($layer) || !isset($layer['id'], $layer['type'])) {
        return null;
    }

    $number = function ($key) use ($layer) {
        return (isset($layer[$key]) && is_numeric($layer[$key])) ? $layer[$key] + 0 : 0;
    };
    $type = (string) $layer['type'];

    return array(
        'id'     => (string) $layer['id'],
        'type'   => $type,
        'kind'   => (isset($layer['kind']) && is_string($layer['kind'])) ? $layer['kind'] : null,
        'name'   => (isset($layer['name']) && is_string($layer['name']) && ($layer['name'] !== '')) ? $layer['name'] : null,
        'x'      => $number('x'),
        'y'      => $number('y'),
        'w'      => $number('w'),
        'h'      => $number('h'),
        'rot'    => $number('rot'),
        'locked' => !empty($layer['locked']),
        'text'   => (($type === 'text') && isset($layer['text'])) ? (string) $layer['text'] : null,
        'color'  => (($type === 'text') && isset($layer['color']) && is_string($layer['color'])) ? $layer['color'] : null,
        'markup' => (($type === 'svg') && isset($layer['markup'])) ? (string) $layer['markup'] : null,
    );
}

/**
 * A canvas's document and versions as an agent reads them: the artboard,
 * whether its picture is drawn, the newest version's number, who made each
 * version (the person it was made for; for an agent's version also the
 * agent's id and handle from its document) and the layers. Pure.
 *
 * @param mixed   $doc      the newest version's document, decoded as arrays
 * @param array[] $versions file_versions rows, oldest first, each with an
 *                          agent's document decoded as doc when it is one
 * @return array artboard, picture, version, layers, versions
 */
function ws_canvas_read($doc, $versions)
{
    $board = (array) ($doc['artboard'] ?? array());
    $layers = array();
    $made = array();

    foreach ((array) ($doc['layers'] ?? array()) as $layer) {
        $read = ws_canvas_layer_read($layer);

        if ($read !== null) {
            $layers[] = $read;
        }
    }

    foreach ($versions as $row) {
        $agent = ((string) $row['source'] === 'agent') ? ws_canvas_doc_agent($row['doc'] ?? null) : null;

        $made[] = array(
            'version'    => (int) $row['version_no'],
            'source'     => (string) $row['source'],
            'user_id'    => ((int) $row['user_id'] > 0) ? (int) $row['user_id'] : null,
            'agent_id'   => ($agent && ($agent['id'] > 0)) ? $agent['id'] : null,
            'agent'      => $agent ? $agent['handle'] : null,
            'created_at' => (int) $row['created_at'],
        );
    }

    $last = end($versions);

    return array(
        'artboard' => array(
            'width'      => (int) ($board['width'] ?? 0),
            'height'     => (int) ($board['height'] ?? 0),
            'background' => (isset($board['background']) && is_string($board['background'])) ? $board['background'] : '#ffffff',
        ),
        // A person's save may carry the mark of the agent's document it
        // opened; only an agent's version waits for a browser.
        'picture'  => ws_canvas_doc_pending($doc, $last ? (string) $last['source'] : 'agent') ? 'pending' : 'drawn',
        'version'  => $last ? (int) $last['version_no'] : 0,
        'layers'   => $layers,
        'versions' => $made,
    );
}

/**
 * The canvas of a message as the API gives it, or null when its picture is
 * no canvas (or was changed outside the editor since).
 *
 * @param array       $message ws_messages row
 * @param object|null $made    the document just written, answered with no
 *                             versions when it could not be kept
 * @return array|null
 */
function ws_api_canvas_present($message, $made = null)
{
    $file_id = (int) $message['file_id'];
    $doc = ($file_id > 0) ? ws_canvas_open_doc($file_id) : null;
    $doc = $doc ?: $made;

    if (!$doc) {
        return null;
    }

    $versions = ($doc === $made) ? array() : pg_file_versions_rows($file_id);
    $agents = array();

    if ($versions) {
        foreach ((array) db_items("SELECT version_no, doc_json FROM file_versions WHERE file_id = '" . $file_id . "' AND source = 'agent'") as $row) {
            $agents[(int) $row['version_no']] = json_decode((string) $row['doc_json'], true);
        }
    }

    foreach ($versions as $i => $row) {
        $versions[$i]['doc'] = $agents[(int) $row['version_no']] ?? null;
    }

    $read = ws_canvas_read(json_decode(json_encode($doc), true), $versions);
    $stored = (string) db_value("SELECT name FROM files WHERE id = '" . $file_id . "'");

    foreach ($read['versions'] as $i => $version) {
        $read['versions'][$i]['created_at'] = api_time($version['created_at']);
    }

    return array(
        'message_id' => (int) $message['id'],
        'file_id'    => $file_id,
        'name'       => (string) $message['file_name'],
        'url'        => URL_SCHEME . HOSTNAME_SETTING . PATH . encode_url_path($stored),
        'api_url'    => ws_api_file_url($message['id']),
    ) + $read;
}

/**
 * The agent a canvas is drawn by in this channel, or the refusal: Claude's
 * application where Claude may be asked (ws_api_channel_or_404() has
 * checked that), else an application with a row in the channel's agents.
 * A person's own device draws in the panel's editor, not here.
 *
 * The live page routes (api_page.php) ask the same, in their own words.
 *
 * @param array       $channel
 * @param string|null $refused the refusal's words; the canvas's when null
 * @return array id, kind, handle (the document's by)
 */
function ws_api_canvas_agent($channel, $refused = null)
{
    $device = function_exists('api_current_device') ? api_current_device() : null;
    $refused = ($refused !== null) ? $refused : lang('A canvas is drawn here only by an agent of the channel.');

    if (ws_api_device_writes_as_person($device)) {
        api_fail(403, 'forbidden', $refused);
    }

    if (ws_api_is_claude()) {
        $row = function_exists('ws_agent_claude_row') ? ws_agent_claude_row() : null;

        return array('id' => $row ? (int) $row['id'] : 0, 'kind' => 'claude', 'handle' => $row ? (string) $row['handle'] : 'claude');
    }

    $app_id = ws_api_app_id();
    $found = null;

    foreach (ws_channel_agents($channel['id']) as $row) {
        if ($row['allowed'] && ($row['agent']['kind'] === 'app') && ($app_id > 0) && ((int) $row['agent']['app_id'] === $app_id)) {
            $found = array('id' => (int) $row['agent']['id'], 'kind' => 'app', 'handle' => (string) $row['agent']['handle']);
            break;
        }
    }

    if ($found === null) {
        api_fail(403, 'forbidden', $refused);
    }

    return $found;
}

function ws_api_canvases_create($params)
{
    $viewer = ws_api_viewer();
    $channel = ws_api_channel_or_404($viewer, $params['id']);

    if (!ws_can_post_channel($viewer, $channel)) {
        api_fail(403, 'forbidden', lang('You cannot post in that channel.'));
    }

    $agent = ws_api_canvas_agent($channel);

    // The document lives in the file's versions: without the table there is
    // no canvas to keep, only a blank picture.
    if (!pg_file_versions_ready()) {
        api_fail(503, 'service_unavailable', lang('The database has to be updated first.'));
    }

    $mode = ws_canvas_split_mode($params['layers'] ?? null);
    $made = ($mode === null) ? array('error' => 'layers') : ws_canvas_from_svg($params['svg'] ?? '', (string) ($params['background'] ?? ''), $mode === 'split', ws_canvas_split_resolver((int) $channel['id'], api_has_scope(api_current_scopes(), 'files:read')));

    if ($made['error'] !== '') {
        api_fail_validation(ws_canvas_from_svg_error_text($made['error']), in_array($made['error'], array('background', 'layers'), true) ? $made['error'] : 'svg');
    }

    $doc = $made['doc'];
    $doc->by = (object) $agent;

    if (ws_canvas_doc_json($doc) === '') {
        api_fail_validation(ws_canvas_from_svg_error_text('not_kept'), 'svg');
    }

    $text = (string) ($params['text'] ?? '');
    $app_id = ws_api_writer_app_id();
    $name = ws_canvas_file_name($params['name'] ?? '');
    $png = ws_canvas_blank_png($doc->artboard->width, $doc->artboard->height, $doc->artboard->background);

    // The checks ws_message_send_file_bytes() makes, made first so that a
    // dry run meets the same refusals.
    $upload = ws_upload_check($viewer, $channel, $name, $png);

    if (!$upload['ok']) {
        api_fail_validation($upload['error'], 'svg');
    }

    $checked = ws_message_send_check($viewer, $channel, $text, array('app_id' => $app_id, 'file_id' => PHP_INT_MAX));

    if (!$checked['ok']) {
        api_fail_validation($checked['error'], 'text');
    }

    $parent_id = (int) ($params['parent_id'] ?? 0);
    $refused = ws_message_parent_check($channel, $parent_id);

    if ($refused !== '') {
        api_fail_validation($refused, 'parent_id');
    }

    api_dry_run_stop('created', 'workspace_canvas', array(
        'channel_id' => (int) $channel['id'],
        'artboard'   => array('width' => (int) $doc->artboard->width, 'height' => (int) $doc->artboard->height, 'background' => (string) $doc->artboard->background),
        'layers'     => count($doc->layers),
        'parent_id'  => ($parent_id > 0) ? $parent_id : null,
    ));

    $result = ws_message_send_file_bytes($viewer, $channel, $text, $name, $png, array('app_id' => $app_id, 'parent_id' => $parent_id));

    if (!$result['ok']) {
        api_fail_validation($result['error'], 'text');
    }

    $row = ws_message($result['message_id']);

    // The version keeps the document beside the blank picture.
    pg_image_editor_canvas_keep((int) $row['file_id'], $doc, (int) $viewer['id'], 'agent');

    api_ok(array(
        'message' => ws_api_message_present($viewer, $row, ws_refs_resolve($viewer, ws_tokens($row['body']))),
        'canvas'  => ws_api_canvas_present($row, $doc),
    ), 201);
}

function ws_api_canvas_fonts($params)
{
    ws_api_viewer();

    api_ok(array('fonts' => ws_canvas_write_fonts()));
}

function ws_api_canvas_get($params)
{
    $viewer = ws_api_viewer();
    $message = ws_api_message_or_404($viewer, $params['id']);
    $canvas = ws_api_canvas_present($message);

    if ($canvas === null) {
        api_fail_not_found(lang('Canvas'));
    }

    api_ok($canvas);
}

/**
 * The canvas of a message as an agent of its channel edits it: the message,
 * its channel and the newest document, or the refusal.
 *
 * @param array $viewer
 * @param int   $id
 * @return array message, channel, agent, doc (decoded as arrays), version
 */
function ws_api_canvas_edit_target($viewer, $id)
{
    $message = ws_api_message_or_404($viewer, $id);
    $channel = ws_api_channel_or_404($viewer, $message['channel_id']);

    if (!ws_can_post_channel($viewer, $channel)) {
        api_fail(403, 'forbidden', lang('You cannot post in that channel.'));
    }

    $agent = ws_api_canvas_agent($channel);
    $doc = ((int) $message['file_id'] > 0) ? ws_canvas_open_doc((int) $message['file_id']) : null;
    $newest = $doc ? pg_file_versions_newest((int) $message['file_id']) : null;

    if (!$doc || !$newest) {
        api_fail_not_found(lang('Canvas'));
    }

    return array(
        'message' => $message,
        'channel' => $channel,
        'agent'   => $agent,
        'doc'     => json_decode(json_encode($doc), true),
        'version' => (int) $newest['version_no'],
    );
}

function ws_api_canvas_edit($params)
{
    $viewer = ws_api_viewer();
    $target = ws_api_canvas_edit_target($viewer, $params['id']);

    if ((int) $params['version'] !== $target['version']) {
        api_fail(409, 'conflict', lang(array('string' => 'The canvas changed since: its newest version is {var:1}. Read it again.', 'vars' => $target['version'])));
    }

    $edited = ws_canvas_edit_apply($target['doc'], $params['operations'] ?? null, ws_canvas_split_resolver((int) $target['channel']['id'], api_has_scope(api_current_scopes(), 'files:read')), ws_canvas_write_file_resolver((int) $target['channel']['id'], api_has_scope(api_current_scopes(), 'files:read')));

    if ($edited['error'] !== '') {
        $where = ($edited['index'] >= 0) ? lang(array('string' => 'Operation {var:1}', 'vars' => $edited['index'])) . ': ' : '';

        api_fail_validation($where . ws_canvas_edit_error_text($edited['error']), 'operations');
    }

    $doc = json_decode(json_encode($edited['doc']));
    $doc->by = (object) $target['agent'];

    if (ws_canvas_doc_json($doc) === '') {
        api_fail_validation(ws_canvas_from_svg_error_text('not_kept'), 'operations');
    }

    $message = $target['message'];

    api_dry_run_stop('updated', 'workspace_canvas', array(
        'message_id' => (int) $message['id'],
        'version'    => $target['version'] + 1,
        'layers'     => count($doc->layers),
    ));

    if (!pg_image_editor_canvas_keep((int) $message['file_id'], $doc, (int) $viewer['id'], 'agent')) {
        api_fail(503, 'service_unavailable', lang('The file could not be saved.'));
    }

    pg_file_versions_prune((int) $message['file_id']);

    // A person's canvas: its author hears that an agent changed it.
    if (($message['sender_kind'] === 'user') && ((int) $message['sender_id'] !== (int) $viewer['id'])) {
        ws_notify((int) $message['sender_id'], 'canvas_edited', array('channel_id' => (int) $message['channel_id'], 'message_id' => (int) $message['id'], 'actor_id' => 0));
    }

    api_ok(ws_api_canvas_present($message));
}
