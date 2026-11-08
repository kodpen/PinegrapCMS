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
            'description' => 'Posts a canvas into a channel as a message of the application: the SVG becomes a picture people see and open in the image editor, its layers editable. Only an agent of the channel draws one: an application with a row in GET /workspace/channels/{id}/agents (or Claude, where Claude may be asked), in a channel the owner may post in. The SVG is sanitized (scripts, outside addresses and events go), at most 1 MB; the artboard is its root\'s width and height (the view box when they are missing, 300 x 150 when both are), each side 16 to 8000 pixels, the drawing one layer covering it. A picture inside it must be an address, not data. The canvas\'s picture is blank in the background colour until a member\'s browser draws it: canvas.picture is pending until then. Refusals: nothing left to draw, over 1 MB, a picture as data or a background that is no colour 422 validation_failed on svg or background; not an agent of the channel 403 forbidden.',
            'params'      => array(
                array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
                array('name' => 'svg', 'in' => 'body', 'type' => 'string', 'max_length' => 1048576, 'required' => true, 'description' => 'The drawing, a whole <svg> element with the SVG namespace.'),
                array('name' => 'text', 'in' => 'body', 'type' => 'string', 'max_length' => 4000, 'description' => 'Words beside the canvas. Tags are written as tokens, as in a message.'),
                array('name' => 'name', 'in' => 'body', 'type' => 'string', 'max_length' => 120, 'description' => 'The name of the canvas\'s file; Canvas when left out. Always a PNG.'),
                array('name' => 'background', 'in' => 'body', 'type' => 'string', 'max_length' => 11, 'description' => 'The artboard\'s colour as #rrggbb, or transparent; white when left out.'),
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
            'description' => 'The canvas a message carries, drawn by an agent or by a person in the image editor: its artboard, the address of its picture (an agent that sees pictures looks at it too; pending until a member\'s browser has drawn it), the number of its newest version and who made each version kept, and its layers from the bottom up - each with its box on the artboard; a text layer\'s text and colour; an svg layer\'s markup. A message whose picture is no canvas, or was changed outside the editor since, answers 404 not_found.',
            'params'      => array(
                array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
            ),
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
 * @param array $channel
 * @return array id, kind, handle (the document's by)
 */
function ws_api_canvas_agent($channel)
{
    $device = function_exists('api_current_device') ? api_current_device() : null;
    $refused = lang('A canvas is drawn here only by an agent of the channel.');

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

    $made = ws_canvas_from_svg($params['svg'] ?? '', (string) ($params['background'] ?? ''));

    if ($made['error'] !== '') {
        api_fail_validation(ws_canvas_from_svg_error_text($made['error']), ($made['error'] === 'background') ? 'background' : 'svg');
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
