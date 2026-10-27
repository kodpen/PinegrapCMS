<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The file manager's sub-actions on files' content: a Word or Excel file
 * read for a preview, a picture turned a quarter to the right, and selected
 * pictures optimized or converted to WebP. Each handler answers through respond(), which ends the request. Called by
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

// The status a batch over selected images answers with: success with no
// errors, partial when some files were done in spite of errors, else error.
function pg_explorer_batch_status($error_count, $done_count)
{
    if ($error_count == 0) {
        return 'success';
    }

    return ($done_count > 0) ? 'partial' : 'error';
}

// The base a WebP copy is named from: the name without its last extension.
function pg_explorer_webp_base_name($name)
{
    $dot = mb_strrpos($name, '.');

    return ($dot === false) ? $name : mb_substr($name, 0, $dot);
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

// ── Bulk optimize selected images ───────────────────────────────
function pg_explorer_handle_optimize($type, $request, $user)
{
    // Recompressing images is the slow part of this screen; nothing
    // below writes to the session, so the lock is not held for it.
    pg_explorer_release_session();

    $items = isset($request['items']) ? $request['items'] : array();

    if ((is_array($items) == false) || (count($items) == 0)) {
        respond(array('status' => 'error', 'message' => lang('Invalid request.')));
    }

    // Recompressing many images takes a while; the same allowance
    // the files screen gives itself.
    @ini_set('max_execution_time', '600');
    @ini_set('memory_limit', '-1');

    require_once(PG_FUNCTIONS_DIR . '/optimize_image.php');

    // 'resize' also scales the longest edge down to the configured
    // ceiling — and, unlike plain optimize, is offered to images
    // that are already optimized (compressed but still oversized is
    // the exact case it exists for).
    $optimize_mode = ((isset($request['mode']) && ($request['mode'] == 'resize')) ? 'resize' : 'optimize');

    $optimizable_types = array('jpg', 'jpeg', 'png', 'gif', 'bmp', 'tiff', 'webp');
    $optimized_count = 0;
    $errors = array();

    // What each file had to say for itself. A run over one file
    // reports that one file's figures -- the sizes before and after
    // and the percentage -- rather than "1 images were optimized",
    // which is what the old optimize.php screen told the operator
    // and what the file manager now tells them in its place.
    $reports = array();
    $skipped = array();

    foreach ($items as $item) {

        $item_id = (int) (isset($item['id']) ? $item['id'] : 0);

        if ($item_id <= 0) {
            continue;
        }

        $file = db_item("SELECT id, name, folder, type, design, optimized FROM files WHERE id = '" . e($item_id) . "'");

        if (!$file) {
            continue;
        }

        if (
            (check_edit_access($file['folder']) == false)
            || (($file['design'] == 1) && ($user['role'] > 1))
        ) {
            $errors[] = lang(array('string' => 'Access denied for {var:1}.', 'vars' => $file['name']));
            continue;
        }

        if (in_array(mb_strtolower($file['type']), $optimizable_types, true) == false) {
            $skipped[] = lang(array('string' => 'Sorry, we don\'t support optimizing that type of file ({var:1}). The following types are supported: jpg, jpeg, png, gif, bmp, tiff.', 'vars' => $file['name']));
            continue;
        }

        if (($optimize_mode != 'resize') && ($file['optimized'] == 1)) {
            $skipped[] = lang(array('string' => 'Sorry, that image ({var:1}) has already been optimized.', 'vars' => $file['name']));
            continue;
        }

        $result = optimize_image($file['id'], $optimize_mode);

        if ($result['status'] == 'success') {
            $optimized_count++;
            $reports[] = $result['message'];
        } else {
            $errors[] = $result['message'];
        }
    }

    // One file, one answer: its own figures, or the reason it was left
    // alone. Several files get the count, as before.
    if ((count($items) == 1) && (count($errors) == 0)) {

        if (($optimized_count == 1) && (count($reports) == 1)) {
            respond(array(
                'status' => 'success',
                'request' => $type,
                'optimized' => 1,
                'errors' => array(),
                'message' => $reports[0]));
        }

        if (($optimized_count == 0) && (count($skipped) == 1)) {
            respond(array(
                'status' => 'error',
                'request' => $type,
                'optimized' => 0,
                'errors' => array($skipped[0]),
                'message' => $skipped[0]));
        }
    }

    if ($optimized_count > 0) {
        if ($optimized_count > 1) {
            $message = lang(array('string' => '{var:1} images were optimized', 'vars' => $optimized_count));
        } else {
            $message = lang('1 image was optimized');
        }

        log_activity($message, $_SESSION['sessionusername']);
    } else {
        $message = lang(array('string' => '{var:1} images were optimized', 'vars' => 0));
    }

    respond(array(
        'status' => pg_explorer_batch_status(count($errors), $optimized_count),
        'request' => $type,
        'optimized' => $optimized_count,
        'errors' => $errors,
        'message' => (count($errors) > 0) ? implode(' ', $errors) : $message));
}

// ── Convert selected images to WebP ─────────────────────────────
//
// The row's name is the address, so the .webp extension means a new
// address. Disk first, row second, old file last: the new file is
// written (temp + rename), then the row is pointed at it, and only
// then is the old file removed — a crash between the steps leaves an
// orphan on disk for clean_up.php, never a row without its file.
function pg_explorer_handle_webp($type, $request, $user)
{
    $items = isset($request['items']) ? $request['items'] : array();

    if ((is_array($items) == false) || (count($items) == 0)) {
        respond(array('status' => 'error', 'message' => lang('Invalid request.')));
    }

    @ini_set('max_execution_time', '600');
    @ini_set('memory_limit', '-1');

    // webp is deliberately not a source: converting webp to webp is
    // the optimize button's job.
    $source_types = array('jpg', 'jpeg', 'png', 'gif', 'bmp', 'tiff');
    $converted_count = 0;
    $errors = array();

    foreach ($items as $item) {

        $item_id = (int) (isset($item['id']) ? $item['id'] : 0);

        if ($item_id <= 0) {
            continue;
        }

        $file = db_item("SELECT id, name, folder, type, design FROM files WHERE id = '" . e($item_id) . "'");

        if (!$file) {
            continue;
        }

        if (
            (check_edit_access($file['folder']) == false)
            || (($file['design'] == 1) && ($user['role'] > 1))
        ) {
            $errors[] = lang(array('string' => 'Access denied for {var:1}.', 'vars' => $file['name']));
            continue;
        }

        if (in_array(mb_strtolower($file['type']), $source_types, true) == false) {
            continue;
        }

        $old_path = FILE_DIRECTORY_PATH . '/' . $file['name'];

        $report = pg_process_image_file($old_path, array('write' => false, 'format' => 'webp'));

        if (($report['status'] !== 'success') || ($report['data'] === null)) {

            if ($report['reason'] === 'animated') {
                $errors[] = lang(array('string' => 'Sorry, {var:1} is an animated image. Optimizing it would leave only the first frame, so it was left alone.', 'vars' => $file['name']));
            } elseif ($report['reason'] === 'color_profile') {
                $errors[] = lang(array('string' => 'Sorry, {var:1} has a colour profile this server cannot carry over. Changing it here would wash out its colours, so it was left alone.', 'vars' => $file['name']));
            } elseif ($report['reason'] === 'orientation_unreadable') {
                $errors[] = lang(array('string' => 'Sorry, {var:1} has a rotation tag this server cannot read. Changing it here could leave the picture on its side, so it was left alone.', 'vars' => $file['name']));
            } elseif ($report['reason'] === 'missing') {
                $errors[] = lang(array('string' => 'Sorry, {var:1} is recorded here but is not on the disk.', 'vars' => $file['name']));
            } elseif ($report['reason'] === 'no_webp') {
                $errors[] = lang('This server cannot produce WebP images (neither Imagick nor GD supports it).');
                break;
            } elseif ($report['reason'] === 'no_library') {
                $errors[] = lang('No image library (Imagick or GD) is installed on this server, so images cannot be optimized or resized.');
                break;
            } else {
                $errors[] = lang(array('string' => 'Sorry, we could not convert that image ({var:1}).', 'vars' => $file['name']));
            }

            continue;
        }

        // New address: the old base name with .webp, made unique
        // across the shared page/file namespace.
        $new_name = get_unique_name(array('name' => prepare_file_name(pg_explorer_webp_base_name($file['name']) . '.webp'), 'type' => 'file'));
        $new_path = FILE_DIRECTORY_PATH . '/' . $new_name;

        $temporary_path = $new_path . '.pgwebp' . getmypid();

        if ((@file_put_contents($temporary_path, $report['data']) === false) || (@rename($temporary_path, $new_path) == false)) {
            @unlink($temporary_path);
            $errors[] = lang(array('string' => 'The WebP file could not be written to the file system ({var:1}).', 'vars' => $file['name']));
            continue;
        }

        @clearstatcache(true, $new_path);

        // The engine already recompressed, so the optimized flag is
        // set — running the optimize button over the result would
        // only degrade it a second time.
        $audit_before = pg_audit_fetch('files', $file['id']);
        db(
            "UPDATE files
            SET
                name = '" . e($new_name) . "',
                type = 'webp',
                size = '" . e(strlen($report['data'])) . "',
                optimized = '1',
                optimization_percent = '0',
                image_width = '" . (int) $report['width'] . "',
                image_height = '" . (int) $report['height'] . "',
                timestamp = UNIX_TIMESTAMP(),
                user = '" . USER_ID . "'
            WHERE id = '" . e($file['id']) . "'");
        pg_audit_record('update', 'files', $file['id'], $audit_before);

        @unlink($old_path);

        log_activity(lang(array('string' => 'file ({var:1}) was converted to {var:2}', 'vars' => array($file['name'], 'WEBP'))), $_SESSION['sessionusername']);

        $converted_count++;
    }

    if ($converted_count > 1) {
        $message = lang(array('string' => '{var:1} images were converted to WebP', 'vars' => $converted_count));
    } elseif ($converted_count == 1) {
        $message = lang('1 image was converted to WebP');
    } else {
        $message = lang(array('string' => '{var:1} images were converted to WebP', 'vars' => 0));
    }

    respond(array(
        'status' => pg_explorer_batch_status(count($errors), $converted_count),
        'request' => $type,
        'converted' => $converted_count,
        'errors' => $errors,
        'message' => (count($errors) > 0) ? implode(' ', $errors) : $message));
}
