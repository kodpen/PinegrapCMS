<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - a canvas saved into Files: its drawn picture written as a new
 * file of a folder, in a format the server writes. It is a change of the
 * file kind (create, with the canvas's message as canvas), so an agent's
 * save takes the one proposal route: a tier 1 agent's waits on a card for a
 * person, a tier 2 agent's is applied at once and named in the channel by
 * its decision.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/**
 * The formats a canvas is saved in, the image editor's export formats on
 * this server: JPEG and PNG always, WebP and AVIF where a library writes
 * them.
 *
 * @return string[]
 */
function ws_canvas_save_formats()
{
    return function_exists('pg_image_editor_export_formats') ? pg_image_editor_export_formats() : array('jpeg', 'png');
}

/**
 * The file name a save asks for: the given one, else the canvas's own, with
 * no folder part, at most 120 letters, its extension the format's (jpg for
 * jpeg). Made unique when written. Pure.
 *
 * @param mixed  $name   as sent
 * @param string $canvas the canvas file's name
 * @param string $format
 * @return string
 */
function ws_canvas_save_name($name, $canvas, $format)
{
    $name = is_string($name) ? trim(preg_replace('/[\\\\\/\x00-\x1f]+/', ' ', $name)) : '';
    $name = ($name !== '') ? $name : trim((string) $canvas);
    $name = ($name !== '') ? $name : 'Canvas';
    $dot = strrpos($name, '.');
    $base = (($dot === false) || ($dot === 0)) ? $name : substr($name, 0, $dot);
    $extension = ($format === 'jpeg') ? 'jpg' : (string) $format;

    return rtrim(mb_substr($base, 0, 120 - strlen($extension) - 1)) . '.' . $extension;
}

/**
 * The change a save of a canvas is: a new file, from the canvas of a
 * message, into a folder. Pure.
 *
 * @param int    $message_id
 * @param int    $folder_id
 * @param string $name   ws_canvas_save_name()
 * @param string $format
 * @param string $reason
 * @return array for ws_changes_input()
 */
function ws_canvas_save_change($message_id, $folder_id, $name, $format, $reason = '')
{
    return array(
        'type'   => 'file',
        'action' => 'create',
        'fields' => array('canvas' => (int) $message_id, 'folder_id' => (int) $folder_id, 'name' => (string) $name, 'format' => (string) $format),
        'reason' => (string) $reason,
    );
}

/**
 * Why a canvas cannot be saved, from what was found about it, or ''. Pure.
 *
 * @param array $found canvas (the message carries a canvas the person may
 *                     read), pending (its picture is not drawn yet), folder
 *                     (the folder is there), edit (the person may add files
 *                     to it), binned (it is in the recycle bin), format (one
 *                     this server writes)
 * @return string
 */
function ws_canvas_save_refusal($found)
{
    if (empty($found['canvas'])) {
        return lang('canvas is the id of a message that carries a canvas.');
    }

    if (!empty($found['pending'])) {
        return lang('The canvas has no picture to save yet: a member of the channel opens it and the picture is drawn.');
    }

    if (empty($found['folder'])) {
        return lang('There is no folder with that id.');
    }

    if (!empty($found['binned'])) {
        return lang('That folder is in the recycle bin.');
    }

    if (empty($found['edit'])) {
        return lang('The person this is saved for may not add files to that folder.');
    }

    if (empty($found['format'])) {
        return lang(array('string' => 'format is one of {var:1}.', 'vars' => implode(', ', ws_canvas_save_formats())));
    }

    return '';
}

/**
 * The checks of a canvas save for the person it is made for: asked when it
 * is proposed and again when it is applied.
 *
 * @param array $viewer
 * @param array $values canvas, folder_id, name, format, description
 * @return string why not, or ''
 */
function ws_canvas_save_check($viewer, $values)
{
    $message = ws_canvas_save_message($viewer, (int) ($values['canvas'] ?? 0));
    $state = $message ? ws_canvas_message_state((int) $message['file_id']) : null;
    $folder_id = (int) ($values['folder_id'] ?? 0);
    $folder = ($folder_id > 0) ? db_value("SELECT folder_id FROM folder WHERE folder_id = '" . $folder_id . "'") : null;
    $there = ($folder !== null) && ($folder !== false);

    return ws_canvas_save_refusal(array(
        'canvas'  => $message && ws_canvas_open_doc((int) $message['file_id']),
        'pending' => $state && !empty($state['pending']),
        'folder'  => $there,
        'binned'  => $there && function_exists('ws_change_recycle_ready') && ws_change_recycle_ready() && pg_recycle_is_inside($folder_id),
        'edit'    => $there && pg_folder_edit_access($folder_id, $viewer['id'], $viewer['role']),
        'format'  => in_array((string) ($values['format'] ?? ''), ws_canvas_save_formats(), true),
    ));
}

/**
 * The message a save names, when the person may read its channel and it
 * carries a file; null otherwise, the same for a message out of reach.
 *
 * @param array $viewer
 * @param int   $message_id
 * @return array|null ws_messages row
 */
function ws_canvas_save_message($viewer, $message_id)
{
    $message = ($message_id > 0) ? ws_message($message_id) : null;

    if (!$message || ((int) $message['file_id'] <= 0) || !empty($message['deleted_at'])) {
        return null;
    }

    $channel = ws_channel((int) $message['channel_id']);

    return ($channel && ws_can_read_channel($viewer, $channel)) ? $message : null;
}

/**
 * Writes the save: the canvas's drawn picture as a new file of the folder.
 *
 * @param array $viewer
 * @param array $values canvas, folder_id, name, format, description
 * @return array ok, error, id
 */
function ws_canvas_save_write($viewer, $values)
{
    $message = ws_canvas_save_message($viewer, (int) ($values['canvas'] ?? 0));
    $source = $message ? pg_file_local_copy((int) $message['file_id']) : '';

    if ($source === '') {
        return array('ok' => false, 'error' => lang('The canvas has no picture to save yet.'), 'id' => 0);
    }

    if (!function_exists('pg_image_editor_canvas_export')) {
        require_once(PG_FUNCTIONS_DIR . '/includes/fn/image_editor.php');
    }

    $format = (string) ($values['format'] ?? '');
    $name = ws_canvas_save_name($values['name'] ?? '', (string) $message['file_name'], $format);
    $saved = pg_image_editor_canvas_export($source, $name, (int) ($values['folder_id'] ?? 0), $format, (int) $viewer['id']);

    if (!$saved['ok']) {
        return array('ok' => false, 'error' => $saved['error'], 'id' => 0);
    }

    if (isset($values['description']) && ((string) $values['description'] !== '')) {
        db("UPDATE files SET description = '" . e((string) $values['description']) . "' WHERE id = '" . (int) $saved['id'] . "' LIMIT 1");
    }

    ws_change_log(lang(array('string' => 'File ({var:1}) was saved from a canvas in the Workspace.', 'vars' => $saved['name'])));

    return array('ok' => true, 'error' => '', 'id' => (int) $saved['id']);
}
