<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - canvases drawn, read and edited by the built-in assistant and
 * the model assistants, as tools of ws_ai_step() (ai.php). The same
 * document, version and picture rules as the API routes of api_canvas.php:
 * the SVG is built into the image editor's document (canvas_svg.php), the
 * message carries a blank picture a member's browser draws later, and an
 * edit is a new version made by the assistant (canvas_edit.php).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/canvas.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/canvas_svg.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/canvas_edit.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/canvas_split.php');

/**
 * The names of the canvas tools.
 *
 * @return string[]
 */
function ws_canvas_ai_tool_names()
{
    return array('draw_canvas', 'read_canvas', 'edit_canvas');
}

/**
 * The longest svg layer markup a read gives the model: the tool answer is
 * cut at 12000 characters as a whole.
 *
 * @return int
 */
function ws_canvas_ai_markup_max()
{
    return 3000;
}

/**
 * Who an assistant's canvas version names as its maker (the document's by):
 * the model assistant running the request, else Pinegrap AI. Pure.
 *
 * @param array|null $model ws_model_running()
 * @return array id, kind, handle
 */
function ws_canvas_ai_by($model)
{
    if (is_array($model) && ((int) ($model['id'] ?? 0) > 0)) {
        return array('id' => (int) $model['id'], 'kind' => 'model', 'handle' => (string) ($model['handle'] ?? ''));
    }

    return array('id' => 0, 'kind' => 'ai', 'handle' => 'ai');
}

/**
 * The operations of edit_canvas, written by the model as a JSON list (its
 * arguments are flat); one object alone is taken as a list of one. Pure.
 *
 * @param mixed $value
 * @return array|null null when it is no list of objects
 */
function ws_canvas_ai_operations($value)
{
    if (is_string($value)) {
        $value = json_decode(trim($value), true);
    }

    if (is_array($value) && isset($value['op'])) {
        $value = array($value);
    }

    if (!is_array($value) || ($value === array()) || (array_values($value) !== $value)) {
        return null;
    }

    foreach ($value as $operation) {
        if (!is_array($operation)) {
            return null;
        }
    }

    return $value;
}

/**
 * A canvas read (ws_canvas_read()) as the model is given it: the message,
 * long svg markup cut, the versions' times as dates. Pure.
 *
 * @param array $read
 * @param int   $message_id
 * @return array
 */
function ws_canvas_ai_present($read, $message_id)
{
    $max = ws_canvas_ai_markup_max();

    foreach ($read['layers'] as $i => $layer) {
        if (is_string($layer['markup']) && (mb_strlen($layer['markup']) > $max)) {
            $read['layers'][$i]['markup'] = mb_substr($layer['markup'], 0, $max) . ' …[cut: replace the whole layer to change it]';
        }

        // The model reads only what a layer has.
        $read['layers'][$i] = array_filter($read['layers'][$i], function ($value) {
            return $value !== null;
        });
    }

    foreach ($read['versions'] as $i => $version) {
        $read['versions'][$i] = array_filter($version, function ($value) {
            return $value !== null;
        });
        $read['versions'][$i]['created_at'] = gmdate('Y-m-d H:i', (int) $version['created_at']) . ' UTC';
    }

    return array('message_id' => (int) $message_id) + $read;
}

/**
 * Are the canvas tools offered for this request? A request asked in a
 * channel (not a note, a task, a page or an MCP server's alias) whose
 * channel the assistant may write in, on a site with the versions table.
 *
 * @param array      $row
 * @param array|null $asker
 * @return bool
 */
function ws_canvas_ai_offered($row, $asker)
{
    if (!$asker || ((int) ($row['note_id'] ?? 0) > 0) || ((int) ($row['task_id'] ?? 0) > 0) || ((int) ($row['page_id'] ?? 0) > 0) || ((int) ($row['mcp_server_id'] ?? 0) > 0)) {
        return false;
    }

    if (!function_exists('pg_file_versions_ready') || !pg_file_versions_ready()) {
        return false;
    }

    $channel = ws_ai_request_channel($row, $asker);

    return $channel && ws_ai_app() && ws_assistant_writer(ws_ai_owner(), $asker, $channel);
}

/**
 * The canvas tools, in the chat API's function format.
 *
 * @param callable $tool ws_ai_tools()'s maker
 * @return array[]
 */
function ws_canvas_ai_tools($tool)
{
    return array(
        $tool('draw_canvas', 'Post a picture into the channel, drawn as SVG, only when the request asks for a drawing, a diagram, a chart or another picture. It is posted under the request as a canvas people open in the image editor with its layers. Still call answer at the end.', array(
            'svg'        => array('type' => 'string', 'description' => 'A whole <svg> element with xmlns="http://www.w3.org/2000/svg", width and height (16 to 8000 pixels) or a viewBox. No scripts, no outside addresses, no pictures as data. At most 1 MB.'),
            'background' => array('type' => 'string', 'description' => 'The artboard\'s colour as #rrggbb, or transparent; white when left out.'),
            'text'       => array('type' => 'string', 'description' => 'Words beside the canvas, optional.'),
            'name'       => array('type' => 'string', 'description' => 'The canvas\'s name, optional.'),
            'layers'     => array('type' => 'string', 'enum' => array('split', 'one'), 'description' => 'split: a layer per top-level element, each named by its id, so one part can be changed later; one (the default): the drawing as one layer.'),
        ), array('svg')),
        $tool('read_canvas', 'Read a canvas of this channel, an assistant\'s or a person\'s: its artboard, its newest version and its layers (id, kind, box, name, a text layer\'s text, an svg layer\'s markup). Read it before edit_canvas.', array(
            'message_id' => array('type' => 'integer', 'description' => 'The message the canvas is in.'),
        ), array('message_id')),
        $tool('edit_canvas', 'Change a canvas of this channel in place, only when the request asks to change it. The operations are applied in order, all or none, to the newest version: add (svg; x, y, w, h, rot optional: a new layer on top; layers split for a layer per element), replace (layer, svg: an svg layer\'s drawing, its box kept), remove (layer), text (layer, text; color #rrggbb optional), box (layer; any of x, y, w, h, rot: move, resize, turn) and svg (svg; background, layers optional: the whole canvas anew). A locked layer is not changed. Still call answer at the end.', array(
            'message_id' => array('type' => 'integer', 'description' => 'The message the canvas is in.'),
            'version'    => array('type' => 'integer', 'description' => 'The newest version, as read_canvas gave it.'),
            'operations' => array('type' => 'string', 'description' => 'A JSON list of at most 20 objects such as [{"op": "text", "layer": "l2", "text": "Q4"}, {"op": "box", "layer": "l0", "x": 40}].'),
        ), array('message_id', 'version', 'operations')),
    );
}

/**
 * A canvas message of the request's channel, or the refusal.
 *
 * @param array $channel
 * @param int   $id
 * @return array message, doc (decoded as arrays), version; or error
 */
function ws_canvas_ai_target($channel, $id)
{
    $message = ((int) $id > 0) ? ws_message((int) $id) : null;

    if (!$message || ((int) $message['deleted_at'] > 0) || ((int) $message['channel_id'] !== (int) $channel['id']) || (function_exists('ws_message_in_past') && ws_message_in_past($message))) {
        return array('error' => 'There is no such message in this channel.');
    }

    $file_id = (int) $message['file_id'];
    $doc = ($file_id > 0) ? ws_canvas_open_doc($file_id) : null;
    $newest = $doc ? pg_file_versions_newest($file_id) : null;

    if (!$doc || !$newest) {
        return array('error' => 'That message carries no canvas.');
    }

    return array('message' => $message, 'doc' => json_decode(json_encode($doc), true), 'version' => (int) $newest['version_no']);
}

/**
 * A canvas as the model reads it.
 *
 * @param array $message
 * @param array $doc decoded as arrays
 * @return array
 */
function ws_canvas_ai_read($message, $doc)
{
    // ws_canvas_read() lives with the API's routes.
    require_once(PG_FUNCTIONS_DIR . '/includes/workspace/api_canvas.php');

    $file_id = (int) $message['file_id'];
    $versions = pg_file_versions_rows($file_id);
    $agents = array();

    foreach ((array) db_items("SELECT version_no, doc_json FROM file_versions WHERE file_id = '" . $file_id . "' AND source = 'agent'") as $row) {
        $agents[(int) $row['version_no']] = json_decode((string) $row['doc_json'], true);
    }

    foreach ($versions as $i => $row) {
        $versions[$i]['doc'] = $agents[(int) $row['version_no']] ?? null;
    }

    return ws_canvas_ai_present(ws_canvas_read($doc, $versions), (int) $message['id']);
}

/**
 * Runs a canvas tool.
 *
 * @param array  $row
 * @param array  $state
 * @param string $name
 * @param array  $args
 * @param array  $asker
 * @return array what the model is told
 */
function ws_canvas_ai_tool_run($row, &$state, $name, $args, $asker)
{
    if (!ws_canvas_ai_offered($row, $asker)) {
        return array('error' => 'A canvas cannot be drawn or changed for this request.');
    }

    $channel = ws_ai_request_channel($row, $asker);
    $app = ws_ai_app();
    $writer = ws_assistant_writer(ws_ai_owner(), $asker, $channel);
    $by = ws_canvas_ai_by(ws_model_running());

    if ($name === 'draw_canvas') {
        $mode = ws_canvas_split_mode($args['layers'] ?? null);
        $made = ($mode === null) ? array('error' => 'layers') : ws_canvas_from_svg((string) ($args['svg'] ?? ''), (string) ($args['background'] ?? ''), $mode === 'split');

        if ($made['error'] !== '') {
            return array('error' => ws_canvas_from_svg_error_text($made['error']));
        }

        $doc = $made['doc'];
        $doc->by = (object) $by;

        if (ws_canvas_doc_json($doc) === '') {
            return array('error' => ws_canvas_from_svg_error_text('not_kept'));
        }

        $png = ws_canvas_blank_png($doc->artboard->width, $doc->artboard->height, $doc->artboard->background);
        $text = trim(mb_substr((string) ($args['text'] ?? ''), 0, 4000));
        $sent = ws_message_send_file_bytes($writer, $channel, ws_tokens_normalise($text), ws_canvas_file_name($args['name'] ?? ''), $png, array(
            'app_id'    => (int) $app['id'],
            'parent_id' => (int) $row['message_id'],
        ));

        if (empty($sent['ok'])) {
            return array('error' => (string) $sent['error']);
        }

        $message = ws_message($sent['message_id']);

        if (!$message || !pg_image_editor_canvas_keep((int) $message['file_id'], $doc, (int) $writer['id'], 'agent')) {
            return array('error' => 'The canvas was posted, but its layers could not be kept.');
        }

        $state['canvas_done'] = 1;

        return array('ok' => true, 'message_id' => (int) $message['id'], 'version' => 1,
            'note' => 'The canvas is posted under the request; members\' browsers draw its picture. Do not draw it again.');
    }

    $target = ws_canvas_ai_target($channel, (int) ($args['message_id'] ?? 0));

    if (isset($target['error'])) {
        return $target;
    }

    if ($name === 'read_canvas') {
        return ws_canvas_ai_read($target['message'], $target['doc']);
    }

    if ((int) ($args['version'] ?? 0) !== $target['version']) {
        return array('error' => 'The canvas changed since: its newest version is ' . $target['version'] . '. Read it again with read_canvas.');
    }

    $operations = ws_canvas_ai_operations($args['operations'] ?? null);

    if ($operations === null) {
        return array('error' => 'Write operations as a JSON list of objects, each with its op.');
    }

    $edited = ws_canvas_edit_apply($target['doc'], $operations);

    if ($edited['error'] !== '') {
        return array('error' => (($edited['index'] >= 0) ? 'Operation ' . $edited['index'] . ': ' : '') . ws_canvas_edit_error_text($edited['error']));
    }

    $doc = json_decode(json_encode($edited['doc']));
    $doc->by = (object) $by;

    if (ws_canvas_doc_json($doc) === '') {
        return array('error' => ws_canvas_from_svg_error_text('not_kept'));
    }

    $message = $target['message'];

    if (!pg_image_editor_canvas_keep((int) $message['file_id'], $doc, (int) $writer['id'], 'agent')) {
        return array('error' => 'The canvas could not be saved.');
    }

    pg_file_versions_prune((int) $message['file_id']);

    // A person's canvas: its author hears that an assistant changed it.
    if (($message['sender_kind'] === 'user') && ((int) $message['sender_id'] !== (int) $writer['id'])) {
        ws_notify((int) $message['sender_id'], 'canvas_edited', array('channel_id' => (int) $message['channel_id'], 'message_id' => (int) $message['id'], 'actor_id' => 0));
    }

    if (function_exists('ws_message_touch')) {
        ws_message_touch((int) $message['id']);
    }

    $state['canvas_done'] = 1;

    return array('ok' => true, 'message_id' => (int) $message['id'], 'version' => $target['version'] + 1);
}
