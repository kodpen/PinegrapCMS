<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - where the image editor writes a picture edited in a
 * conversation. The editor (assets/js/image_editor_modal.js) posts the
 * picture as JSON; the message and what to do with the picture ride in the
 * address:
 *
 *   workspace_file_save.php?message=<id>&as=replace|send
 *     { token, data: '<data: URL>', ... } -> { status, mode, name, url, message_id }
 *
 * 'replace' puts the edited picture in the place of the one in the person's
 * own message and keeps the old one as an earlier version; 'send' posts it as
 * the person's own message in reply, the original staying as it is. The rules
 * are in includes/workspace/file_edits.php.
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
unset($body);

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

$target = ws_file_edit_target($viewer, (int) ($_GET['message'] ?? 0), 'image');

if (!is_array($target)) {
    ws_file_save_refuse($target);
}

list($message, $channel) = $target;

$data = (string) ($request['data'] ?? '');
$extension = ws_file_image_extension($data);

if ($extension === '') {
    ws_file_save_refuse(lang('This file type is not allowed.'));
}

$name = ws_file_edit_name($message['file_name'], $extension);
$as = (($_GET['as'] ?? '') === 'replace') ? 'replace' : 'send';

if ($as === 'replace') {
    $result = ws_message_file_replace($viewer, $message, $channel, $name, $data);
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

echo encode_json(array(
    'status'     => 'success',
    'mode'       => $as,
    'name'       => $saved ? (string) $saved['file_name'] : $name,
    'url'        => ($stored !== '') ? PATH . encode_url_path($stored) : '',
    'message_id' => $message_id,
));
