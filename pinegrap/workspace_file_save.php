<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - where the image editor writes a picture edited in a
 * conversation. The editor (assets/js/pg_image_editor.js, save.url) posts
 * what it posts for a save, the document and the overlay, as JSON; the
 * picture is drawn here from the message's file (pg_image_editor_render())
 * and the message and what to do with the picture ride in the address:
 *
 *   workspace_file_save.php?message=<id>&as=replace|send
 *     { token, doc, overlay?, colour? } -> { status, mode, name, url, message_id }
 *
 * 'replace' puts the edited picture in the place of the one in the person's
 * own message and keeps the old one as an earlier version; 'send' posts it as
 * the person's own message in reply, the original staying as it is. The rules
 * are in includes/workspace/file_edits.php.
 *
 * A canvas (includes/workspace/canvas.php) is an artboard the editor draws
 * itself: its body carries the PNG (picture) in place of the overlay, and
 * the document is kept beside the file written, so it opens with its layers
 * again:
 *
 *   workspace_file_save.php?channel=<id>   a new canvas posted in a channel
 *     { token, doc, picture, name? } -> { status, mode: 'new', name, url, message_id }
 *   workspace_file_save.php?note=<id>      a new canvas kept for a note
 *     { token, doc, picture, name? } -> { status, mode: 'new', id, name, url }
 *   workspace_file_save.php?message=<id>   with doc.artboard: as above, the
 *     canvas drawn again replacing or answering the message's picture
 *   workspace_file_save.php?message=<id>   { token, action: 'canvas' }
 *     -> { status, doc }: the canvas document the message's picture was
 *     drawn from, or null
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');

$user = validate_user();

header('Content-Type: application/json; charset=utf-8');

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php');

/**
 * Answers with an error and stops. Every answer is JSON: the editor shows
 * the message in its footer.
 *
 * @param string $message
 * @param int    $status
 */
function ws_file_save_refuse($message, $status = 200)
{
    http_response_code($status);
    echo encode_json(array('status' => 'error', 'message' => $message));
    exit();
}

$body = file_get_contents('php://input');

if (trim((string) $body) === '') {
    if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
        ws_file_save_refuse(lang(array('string' => 'The file is larger than {var:1} MB.', 'vars' => (int) floor(ws_upload_max_bytes() / 1048576))), 413);
    }

    ws_file_save_refuse(lang('Invalid request.'));
}

$request = json_decode($body, true);
// The document once more as objects, to be kept as the editor sent it (an
// empty {} stays an object).
$objects = json_decode($body);
$doc_object = (is_object($objects) && isset($objects->doc)) ? $objects->doc : null;
unset($body, $objects);

if (!is_array($request)) {
    ws_file_save_refuse(lang('Invalid request.'));
}

// The JSON body never fills $_POST, so the token is compared here.
$token = (string) ($request['token'] ?? '');

if ((($_SESSION['software']['token'] ?? '') === '') || !hash_equals((string) $_SESSION['software']['token'], $token)) {
    ws_file_save_refuse(lang('Sorry, we could not accept your request because it appears that your session expired or you logged out.'), 403);
}

if (!ws_ready() || !ws_enabled()) {
    ws_file_save_refuse(lang('The workspace is not switched on.'));
}

$viewer = ws_viewer($user);

if (!$viewer['member']) {
    ws_file_save_refuse(lang('Access denied.'), 403);
}

session_write_close();

$canvas = ws_canvas_is_doc($request['doc'] ?? null);

// A new canvas kept for a note: linked in the note by the screen.
if (isset($_GET['note'])) {
    $note = ws_note_for($viewer, (int) $_GET['note'], 'edit');

    if (!$note) {
        ws_file_save_refuse(lang('That note could not be found.'));
    }

    $picture = $canvas ? ws_canvas_picture($request) : array('error' => lang('Invalid request.'), 'data' => '');

    if ($picture['error'] !== '') {
        ws_file_save_refuse($picture['error']);
    }

    $result = ws_note_file_store_bytes($viewer, $note, ws_canvas_file_name($request['name'] ?? ''), $picture['data']);

    if (!$result['ok']) {
        ws_file_save_refuse($result['error']);
    }

    ws_canvas_keep($result['id'], $doc_object, $viewer['id']);

    echo encode_json(array('status' => 'success', 'mode' => 'new', 'id' => (int) $result['id'], 'name' => $result['name'], 'url' => $result['url']));
    exit();
}

// A new canvas posted in a channel as the person's own message.
if (isset($_GET['channel'])) {
    $channel = ws_channel((int) $_GET['channel']);

    if (!$channel || !ws_can_read_channel($viewer, $channel)) {
        ws_file_save_refuse(lang('Access denied.'), 403);
    }

    $picture = $canvas ? ws_canvas_picture($request) : array('error' => lang('Invalid request.'), 'data' => '');

    if ($picture['error'] !== '') {
        ws_file_save_refuse($picture['error']);
    }

    $result = ws_message_send_file_bytes($viewer, $channel, '', ws_canvas_file_name($request['name'] ?? ''), $picture['data']);

    if (!$result['ok']) {
        ws_file_save_refuse($result['error']);
    }

    $saved = ws_message((int) $result['message_id']);

    if ($saved) {
        ws_canvas_keep((int) $saved['file_id'], $doc_object, $viewer['id']);
    }

    $stored = $saved ? (string) db_value("SELECT name FROM files WHERE id = '" . (int) $saved['file_id'] . "'") : '';

    echo encode_json(array(
        'status'     => 'success',
        'mode'       => 'new',
        'name'       => $saved ? (string) $saved['file_name'] : '',
        'url'        => ($stored !== '') ? PATH . encode_url_path($stored) : '',
        'message_id' => (int) $result['message_id'],
    ));
    exit();
}

$target = ws_file_edit_target($viewer, (int) ($_GET['message'] ?? 0), 'image');

if (!is_array($target)) {
    ws_file_save_refuse($target);
}

list($message, $channel) = $target;

// Before the editor opens a picture of the conversation: the canvas it was
// drawn from, if it was one.
if (($request['action'] ?? '') === 'canvas') {
    echo encode_json(array('status' => 'success', 'doc' => ws_canvas_open_doc((int) $message['file_id'])));
    exit();
}

if ($canvas) {
    // The canvas drawn again: its PNG is the picture.
    $picture = ws_canvas_picture($request);
    $picture['extension'] = 'png';
} else {
    $stored_name = (string) db_value("SELECT name FROM files WHERE id = '" . (int) $message['file_id'] . "'");
    $picture = pg_image_editor_render(FILE_DIRECTORY_PATH . '/' . $stored_name, $request);
}

unset($request);

if ($picture['error'] !== '') {
    ws_file_save_refuse($picture['error']);
}

$extension = $picture['extension'];
// A canvas has no redaction to burn in.
$redacted = !empty($picture['redacted']);
$mime = array('jpg' => 'jpeg', 'png' => 'png', 'webp' => 'webp', 'gif' => 'gif');
$data = 'data:image/' . $mime[$extension] . ';base64,' . base64_encode($picture['data']);
unset($picture);

$name = ws_file_edit_name($message['file_name'], $extension);
$as = (($_GET['as'] ?? '') === 'replace') ? 'replace' : 'send';

if ($as === 'replace') {
    $result = ws_message_file_replace($viewer, $message, $channel, $name, $data, $redacted);
    $message_id = (int) $message['id'];
} else {
    $result = ws_message_file_send($viewer, $message, $channel, $name, $data);
    $message_id = (int) ($result['message_id'] ?? 0);
}

if (!$result['ok']) {
    ws_file_save_refuse($result['error']);
}

$saved = ws_message($message_id);
$stored = $saved ? (string) db_value("SELECT name FROM files WHERE id = '" . (int) $saved['file_id'] . "'") : '';

if ($canvas && $saved) {
    ws_canvas_keep((int) $saved['file_id'], $doc_object, $viewer['id']);
}

echo encode_json(array(
    'status'     => 'success',
    'mode'       => $as,
    'name'       => $saved ? (string) $saved['file_name'] : $name,
    'url'        => ($stored !== '') ? PATH . encode_url_path($stored) : '',
    'message_id' => $message_id,
));
