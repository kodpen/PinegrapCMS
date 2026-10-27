<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The file manager's sub-actions on one file's content: a Word or Excel
 * file read for a preview, and a picture turned a quarter to the right. Each
 * handler answers through respond(), which ends the request. Called by
 * pg_explorer_handle() and loaded by view_folder_and_files_f.php.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// Whether a picture can be turned: the formats the image engine can decode
// and encode, by the file name's extension.
function pg_explorer_rotatable($name)
{
    $extension = mb_strtolower(pathinfo($name, PATHINFO_EXTENSION));

    return in_array($extension, array('jpg', 'jpeg', 'png', 'gif', 'bmp', 'tiff', 'tif', 'webp'), true);
}

// ── A Word or Excel file, read for a preview ────────────────────
//
// A Word or Excel file read on the server for the file window and
// Quick Look (includes/fn/office.php): rows for a workbook, a safe
// subset of HTML for a document. Reading only, so it is open to
// whoever may view the folder; a document the ERP module keeps to
// whoever may read the ERP (get_file.php's rule for those).
//
// The file window asks for a workbook's cells or a document's
// paragraphs to be offered for editing (edit): then the answer also
// says which may be changed and carries the version a save must hand
// back -- only for someone who may change the file, never for an ERP
// document. The changes are written by explorer_file_save.
function pg_explorer_handle_office_preview($type, $request, $user)
{
    $office_file = pg_explorer_file_row((int) (isset($request['id']) ? $request['id'] : 0));

    if (!$office_file || (pg_office_kind($office_file['name']) === '')) {
        respond(array('status' => 'error', 'message' => lang('There is no preview for this kind of file.')));
    }

    $office_erp = pg_files_include_erp_document(array($office_file['id']));

    if ($office_erp) {
        if (!defined('ERP_ENABLED') || !ERP_ENABLED || (((int) $user['role'] >= 3) && empty($user['manage_erp']))) {
            respond(array('status' => 'error', 'message' => lang('Access denied')));
        }
    } elseif (check_view_access($office_file['folder']) == false) {
        respond(array('status' => 'error', 'message' => lang('Access denied')));
    }

    $office_edit = !empty($request['edit']) && !$office_erp && pg_explorer_file_editable_by($office_file, $user);

    $office = pg_office_read(FILE_DIRECTORY_PATH . '/' . $office_file['name'], $office_file['name'], $office_edit);

    if (!$office['ok']) {
        respond(array('status' => 'error', 'message' => $office['error']));
    }

    unset($office['ok'], $office['error']);

    respond(array('status' => 'success', 'request' => $type, 'office' => $office));
}

// ── Turn a picture a quarter to the right ────────────────────────
//
// A photo taken with the phone on its side is the reason this exists:
// one press per quarter turn, from the file window, with no editor to
// open and nothing to save afterwards. To the right only -- a second
// press finishes a half turn, a third the other direction -- because a
// direction switch for a rare need is a control most people would
// never touch.
//
// The pixels go through the same engine as optimize and webp, which
// bakes the EXIF orientation in first, so the turn is a turn of what
// the operator was looking at.
function pg_explorer_handle_rotate($type, $request, $user)
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

    // The formats the engine can decode and encode; svg has no pixels
    // to turn and is not offered the button either.
    if (pg_explorer_rotatable($file['name']) == false) {
        respond(array('status' => 'error', 'message' => lang('Sorry, that type of file cannot be rotated here.')));
    }

    $path = FILE_DIRECTORY_PATH . '/' . $file['name'];

    if (is_file($path) == false) {
        respond(array('status' => 'error', 'message' => lang('The file is recorded here but is not on the disk.')));
    }

    // A large photo takes a couple of seconds to re-encode.
    @set_time_limit(120);

    // Encoded at a high quality rather than the optimize ladder: the
    // operator asked for the picture the other way up, not for a
    // lighter file, and each turn would otherwise cost another
    // generation of compression.
    $report = pg_process_image_file($path, array('rotate' => 90, 'quality' => 92));

    if ($report['status'] !== 'success') {

        if ($report['reason'] === 'animated') {
            $message = lang(array('string' => 'Sorry, {var:1} is an animated image. Optimizing it would leave only the first frame, so it was left alone.', 'vars' => $file['name']));
        } elseif ($report['reason'] === 'color_profile') {
            $message = lang(array('string' => 'Sorry, {var:1} has a colour profile this server cannot carry over. Changing it here would wash out its colours, so it was left alone.', 'vars' => $file['name']));
        } elseif ($report['reason'] === 'orientation_unreadable') {
            $message = lang(array('string' => 'Sorry, {var:1} has a rotation tag this server cannot read. Changing it here could leave the picture on its side, so it was left alone.', 'vars' => $file['name']));
        } elseif ($report['reason'] === 'missing') {
            $message = lang(array('string' => 'Sorry, {var:1} is recorded here but is not on the disk.', 'vars' => $file['name']));
        } elseif ($report['reason'] === 'no_library') {
            $message = lang('No image library (Imagick or GD) is installed on this server, so images cannot be optimized or resized.');
        } else {
            $message = lang(array('string' => 'Sorry, we could not rotate that image ({var:1}).', 'vars' => $file['name']));
        }

        respond(array('status' => 'error', 'message' => $message));
    }

    @clearstatcache(true, $path);

    // Its edges swapped and its bytes changed; and it has been
    // re-encoded outside the site's picture profile, so the optimized
    // mark no longer holds and the button is offered again.
    $audit_before = pg_audit_fetch('files', $file_id);
    db(
        "UPDATE files SET
            size = '" . e((int) filesize($path)) . "',
            image_width = '" . e((int) $report['width']) . "',
            image_height = '" . e((int) $report['height']) . "',
            optimized = '0',
            timestamp = UNIX_TIMESTAMP(),
            user = '" . e($user['id']) . "'
        WHERE id = '" . e($file_id) . "'");
    pg_audit_record('update', 'files', $file_id, $audit_before);

    log_activity(lang(array('string' => 'image ({var:1}) was rotated', 'vars' => $file['name'])), $_SESSION['sessionusername']);

    respond(array(
        'status' => 'success',
        'request' => $type,
        'file' => pg_explorer_file_payload(pg_explorer_file_row($file_id), $user),
        'message' => lang('The image was rotated.')));
}
