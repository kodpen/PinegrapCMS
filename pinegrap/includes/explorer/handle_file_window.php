<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The file manager's file window sub-actions: read a file for the window
 * and save what the window changed. Each handler answers through respond(),
 * which ends the request.
 * Called by pg_explorer_handle() and loaded by view_folder_and_files_f.php.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// The design flag the file window saves. It is a designer's decision, and
// the switch is not drawn for anyone else -- so for anyone else the value is
// the one the file already carries.
function pg_explorer_file_save_design($request, $user, $file)
{
    return ($user['role'] <= 1)
        ? ((isset($request['design']) && ((string) $request['design'] == '1')) ? '1' : '0')
        : (($file['design'] == 1) ? '1' : '0');
}

// What edit_file.php was: a file's name, folder, description and
// design flag, and for a text format its contents, in one place --
// now a window on the file manager rather than a screen of its own.
// The text is read here and written by explorer_file_save; every
// other change to a picture goes through the image editor, and the
// conversions go through the actions that already exist for them.
function pg_explorer_handle_file_get($type, $request, $user)
{
    $file_id = (int) (isset($request['id']) ? $request['id'] : 0);
    $file = pg_explorer_file_row($file_id);

    if (!$file) {
        respond(array('status' => 'error', 'message' => lang('Sorry, the file could not be found.')));
    }

    if (pg_explorer_file_editable_by($file, $user) == false) {
        log_activity(lang('access denied because user does not have access to file'), $_SESSION['sessionusername']);
        respond(array('status' => 'error', 'message' => lang('Access denied')));
    }

    $payload = pg_explorer_file_payload($file, $user);
    $extension = mb_strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $path = FILE_DIRECTORY_PATH . '/' . $file['name'];

    // The signing key is shown as a file with no preview: its
    // content never leaves the server (pg_file_withheld()).
    $payload['editable'] = in_array($extension, pg_explorer_editable_formats(), true);
    $payload['viewable'] = in_array($extension, pg_explorer_viewable_formats(), true)
        && (pg_file_withheld($file['name']) == false);
    $payload['editor_language'] = pg_explorer_editor_language($extension);
    $payload['content'] = null;
    $payload['content_too_large'] = false;
    $payload['on_disk'] = is_file($path);

    if ($payload['viewable'] && $payload['on_disk']) {

        if (filesize($path) > pg_explorer_editor_max_bytes()) {
            $payload['content_too_large'] = true;
        } else {
            $payload['content'] = (string) @file_get_contents($path);
        }
    }

    $payload['editor_max_label'] = convert_bytes_to_string(pg_explorer_editor_max_bytes());

    respond(array('status' => 'success', 'request' => $type, 'file' => $payload));
}

// Saves the file window: name, folder, description, design flag, and the
// text or the Office cells and paragraphs it was sent.
function pg_explorer_handle_file_save($type, $request, $user)
{
    $file_id = (int) (isset($request['id']) ? $request['id'] : 0);
    $file = pg_explorer_file_row($file_id);

    if (!$file) {
        respond(array('status' => 'error', 'message' => lang('Sorry, the file could not be found.')));
    }

    if (pg_explorer_file_editable_by($file, $user) == false) {
        log_activity(lang('access denied to modify files because user does not have access to file'), $_SESSION['sessionusername']);
        respond(array('status' => 'error', 'message' => lang('Access denied')));
    }

    $requested_name = trim(isset($request['name']) ? (string) $request['name'] : $file['name']);

    // Renaming "photo.jpg" to "photo.php" is uploading a PHP file
    // with an extra step, so the window keeps the upload rule.
    if (($requested_name != $file['name']) && pg_upload_name_blocked($requested_name)) {
        respond(array('status' => 'error', 'message' => pg_upload_blocked_message($requested_name)));
    }

    // The same preparation every other way of naming a file applies:
    // ASCII conversion, reserved names, length and character rules.
    $name = prepare_file_name($requested_name);

    if ($name == '') {
        respond(array('status' => 'error', 'message' => lang('Please enter a name.')));
    }

    // Under another name the signing key would be served.
    if (($name != $file['name']) && pg_file_withheld($file['name'])) {
        respond(array('status' => 'error', 'message' => lang('Access denied')));
    }

    if (($name != $file['name']) && (check_name_availability(array('name' => $name, 'ignore_item_id' => $file_id, 'ignore_item_type' => 'file')) == false)) {
        respond(array('status' => 'error', 'message' => lang('That name is already in use. Please choose a different name.')));
    }

    $folder_id = (int) (isset($request['folder_id']) ? $request['folder_id'] : $file['folder']);

    if ($folder_id != (int) $file['folder']) {

        if (($folder_id <= 0) || (check_edit_access($folder_id) == false)) {
            respond(array('status' => 'error', 'message' => lang('You do not have access to move items to the folder that you selected.')));
        }

        // The bin is only fed by the delete action, so restore
        // metadata always exists for what sits inside it.
        if (pg_recycle_is_inside($folder_id)) {
            respond(array('status' => 'error', 'message' => lang('You do not have access to move items to the folder that you selected.')));
        }
    }

    $description = isset($request['description']) ? (string) $request['description'] : (string) $file['description'];

    $design = pg_explorer_file_save_design($request, $user, $file);

    $old_path = FILE_DIRECTORY_PATH . '/' . $file['name'];
    $new_path = FILE_DIRECTORY_PATH . '/' . $name;
    $size_sql = '';

    // Cell values changed in a workbook's preview, or paragraph texts
    // in a document's (includes/fn/office_edit.php). They go into the
    // file before anything else moves, so a refused edit -- the file
    // changed since it was opened, a cell or a paragraph that may not
    // be changed -- leaves the file, its name and its record exactly
    // as they were.
    if (isset($request['cells']) || isset($request['paragraphs'])) {

        $office_kind = pg_office_kind($file['name']);
        $office_changes = ($office_kind === 'xlsx') ? ($request['cells'] ?? null) : (($office_kind === 'docx') ? ($request['paragraphs'] ?? null) : null);

        if (!is_array($office_changes)) {
            respond(array('status' => 'error', 'message' => lang('This file cannot be edited here.')));
        }

        // A large sheet takes a few seconds to rewrite.
        @set_time_limit(120);

        $office_version = isset($request['office_version']) ? (string) $request['office_version'] : '';
        $office_saved = ($office_kind === 'xlsx')
            ? pg_office_xlsx_save($old_path, $office_changes, $office_version)
            : pg_office_docx_save($old_path, $office_changes, $office_version);

        if (!$office_saved['ok']) {
            respond(array('status' => 'error', 'message' => $office_saved['error']));
        }

        $size_sql = "size = '" . e((int) $office_saved['size']) . "',";
    }

    // The record's name is the address, so the disk file moves first
    // and the row is only told if it did -- a row pointing at nothing
    // is exactly the state this order exists to prevent.
    if ($name != $file['name']) {
        if ((file_exists($old_path) == true) && (@rename($old_path, $new_path) == false)) {
            respond(array('status' => 'error', 'message' => lang('The file could not be renamed on the file system. Check write permission for the file directory.')));
        }
    }

    $extension = mb_strtolower(pathinfo($name, PATHINFO_EXTENSION));

    // Text is written only for a format the editor may write, and only
    // when the window sent some: a window opened on a picture sends
    // none, and a file too large for the editor was never shown.
    if (isset($request['content']) && is_string($request['content']) && in_array($extension, pg_explorer_editable_formats(), true)) {

        if (@file_put_contents($new_path, $request['content']) === false) {
            respond(array('status' => 'error', 'message' => lang('The file could not be written to the file system.')));
        }

        clearstatcache(true, $new_path);
        $size_sql = "size = '" . e((int) filesize($new_path)) . "',";
    }

    $audit_before = pg_audit_fetch('files', $file_id);
    db(
        "UPDATE files SET
            name = '" . e($name) . "',
            folder = '" . e($folder_id) . "',
            description = '" . e($description) . "',
            type = '" . e($extension) . "',
            design = '" . e($design) . "',
            " . $size_sql . "
            timestamp = UNIX_TIMESTAMP(),
            user = '" . e($user['id']) . "'
        WHERE id = '" . e($file_id) . "'");
    pg_audit_record('update', 'files', $file_id, $audit_before);

    log_activity(lang(array('string' => 'file ({var:1}) was modified', 'vars' => $name)), $_SESSION['sessionusername']);

    respond(array(
        'status' => 'success',
        'request' => $type,
        'file' => pg_explorer_file_payload(pg_explorer_file_row($file_id), $user),
        'message' => lang('The file was edited successfully.')));
}
