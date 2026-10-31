<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The file manager's paste, delete and design flag sub-actions. Each
 * handler answers through respond(), which ends the request.
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

// The design flag a request asks for: '1' only for an exact "1", '0' for
// anything else, a missing value included.
function pg_explorer_files_design_flag($request)
{
    return ((isset($request['design']) ? (string) $request['design'] : '') == '1') ? '1' : '0';
}

// Paste: a copy of each file, folder (the whole subtree) and page into the
// target folder.
function pg_explorer_handle_paste($type, $request, $user)
{
    $target_id = (int) (isset($request['target_folder_id']) ? $request['target_folder_id'] : 0);
    $items = isset($request['items']) ? $request['items'] : array();

    if (($target_id <= 0) || (is_array($items) == false) || (count($items) == 0)) {
        respond(array('status' => 'error', 'message' => lang('Invalid request.')));
    }

    if ((check_edit_access($target_id) == false) || pg_recycle_is_inside($target_id)) {
        respond(array('status' => 'error', 'message' => lang('You do not have access to move items to the folder that you selected.')));
    }

    $target_folder = db_item("SELECT folder_id AS id, folder_name AS name FROM folder WHERE folder_id = '" . e($target_id) . "'");

    $copied = 0;
    $errors = array();
    $created = array();

    foreach ($items as $item) {

        $item_kind = isset($item['kind']) ? $item['kind'] : '';
        $item_id = (int) (isset($item['id']) ? $item['id'] : 0);

        if ($item_id <= 0) {
            continue;
        }

        switch ($item_kind) {

            case 'file':

                $file = db_item(
                    "SELECT id, name, folder, description, design, optimized, image_width, image_height, optimization_percent
                    FROM files
                    WHERE id = '" . e($item_id) . "'");

                if (!$file) {
                    $errors[] = lang('Sorry, the file could not be found.');
                    break;
                }

                // A copy of the signing key would carry another name,
                // and get_file.php serves it under that name.
                if (
                    (check_edit_access($file['folder']) == false)
                    || (($file['design'] == 1) && ($user['role'] > 1))
                    || pg_file_withheld($file['name'])
                ) {
                    $errors[] = lang(array('string' => 'Access denied for {var:1}.', 'vars' => $file['name']));
                    break;
                }

                $source_path = FILE_DIRECTORY_PATH . '/' . $file['name'];

                if (file_exists($source_path) == false) {
                    $errors[] = lang(array('string' => 'The file does not exist on the file system ({var:1}).', 'vars' => $file['name']));
                    break;
                }

                $new_name = get_unique_name(array('name' => prepare_file_name($file['name']), 'type' => 'file'));
                $new_path = FILE_DIRECTORY_PATH . '/' . $new_name;

                if (@copy($source_path, $new_path) == false) {
                    $errors[] = lang(array('string' => 'The file could not be copied ({var:1}).', 'vars' => $file['name']));
                    break;
                }

                $file_extension = mb_strtolower(pathinfo($new_name, PATHINFO_EXTENSION));

                db(
                    "INSERT INTO files (
                        name,
                        folder,
                        description,
                        type,
                        size,
                        design,
                        optimized,
                        image_width,
                        image_height,
                        optimization_percent,
                        user,
                        timestamp)
                    VALUES (
                        '" . e($new_name) . "',
                        '" . e($target_id) . "',
                        '" . e($file['description']) . "',
                        '" . e($file_extension) . "',
                        '" . e(filesize($new_path)) . "',
                        '" . e($file['design']) . "',
                        '" . e($file['optimized']) . "',
                        " . (($file['image_width'] !== null && $file['image_width'] !== '') ? "'" . e($file['image_width']) . "'" : "NULL") . ",
                        " . (($file['image_height'] !== null && $file['image_height'] !== '') ? "'" . e($file['image_height']) . "'" : "NULL") . ",
                        " . (($file['optimization_percent'] !== null && $file['optimization_percent'] !== '') ? "'" . e($file['optimization_percent']) . "'" : "NULL") . ",
                        '" . e($user['id']) . "',
                        UNIX_TIMESTAMP())");

                $created[] = array('kind' => 'file', 'id' => (int) mysqli_insert_id(db::$con));

                pg_audit_record('create', 'files', $created[count($created) - 1]['id']);

                log_activity(lang(array('string' => '{var:1} ({var:2}) was duplicated', 'vars' => array(lang('file'), $file['name']))), $_SESSION['sessionusername']);

                $copied++;
                break;

            case 'folder':

                // A folder copy is the whole subtree. Pasting one
                // into itself (or into something it contains) would
                // be a copy with no end, so that is refused rather
                // than depth-capped.
                if (pg_explorer_is_self_or_descendant($item_id, $target_id)) {
                    $errors[] = lang('Sorry, a folder cannot be copied into itself.');
                    break;
                }

                if (pg_recycle_is_inside($item_id)) {
                    $errors[] = lang('Sorry, the folder could not be found.');
                    break;
                }

                $copied += pg_explorer_copy_folder($item_id, $target_id, $user, $errors, $created);
                break;

            case 'page':

                // duplicate_page() carries the full copy: settings,
                // regions, layout file, and it validates the page
                // creation right and source folder access itself.
                require_once(PG_FUNCTIONS_DIR . '/duplicate_page_f.php');

                $response = duplicate_page(array(
                    'page' => array('id' => $item_id),
                    'folder' => $target_folder,
                    'find_replace_keywords' => ''));

                if (isset($response['status']) && ($response['status'] == 'success')) {
                    $copied++;

                    if (isset($response['page']['id'])) {
                        $created[] = array('kind' => 'page', 'id' => (int) $response['page']['id']);
                    }
                } else {
                    $errors[] = isset($response['message']) ? $response['message'] : lang('Sorry, the page could not be found.');
                }
                break;
        }
    }

    respond(array(
        'status' => (count($errors) == 0) ? 'success' : ((($copied > 0)) ? 'partial' : 'error'),
        'request' => $type,
        'copied' => $copied,
        'created' => $created,
        'errors' => $errors,
        'message' => (count($errors) > 0)
            ? implode(' ', $errors)
            : lang(array('string' => '{var:1} item(s) were pasted.', 'vars' => $copied))));
}

// Delete a set of files outright, with their theme css rules and preview
// styles.
function pg_explorer_handle_delete_files($type, $request, $user)
{
    $ids = isset($request['file_ids']) ? $request['file_ids'] : array();

    if ((is_array($ids) == false) || (count($ids) == 0)) {
        respond(array('status' => 'error', 'message' => lang('Invalid request.')));
    }

    $deleted = 0;
    $errors = array();

    foreach ($ids as $file_id) {

        $file_id = (int) $file_id;

        $file = db_item("SELECT id, name, folder, design FROM files WHERE id = '" . e($file_id) . "'");

        if (!$file) {
            continue;
        }

        if (
            (check_edit_access($file['folder']) == false)
            || (($file['design'] == 1) && ($user['role'] > 1))
        ) {
            log_activity(lang('access denied to delete files because user does not have access to file'), $_SESSION['sessionusername']);
            $errors[] = lang(array('string' => 'Access denied for {var:1}.', 'vars' => $file['name']));
            continue;
        }

        $audit_before = pg_audit_fetch('files', $file_id);

        db("DELETE FROM files WHERE id = '" . e($file_id) . "'");

        pg_audit_record('delete', 'files', (int) $file_id, $audit_before);

        // A file can carry theme css rules and preview styles; those
        // rows die with it, the same way the files screen deletes.
        db("DELETE FROM system_theme_css_rules WHERE file_id = '" . e($file_id) . "'");
        db("DELETE FROM preview_styles WHERE theme_id = '" . e($file_id) . "'");

        @unlink(FILE_DIRECTORY_PATH . '/' . $file['name']);

        $deleted++;
    }

    if ($deleted > 0) {
        log_activity(lang(array('string' => '{var:1} file(s) were deleted', 'vars' => $deleted)), $_SESSION['sessionusername']);
    }

    respond(array(
        'status' => (count($errors) == 0) ? 'success' : ((($deleted > 0)) ? 'partial' : 'error'),
        'request' => $type,
        'deleted' => $deleted,
        'errors' => $errors,
        'message' => (count($errors) > 0)
            ? implode(' ', $errors)
            : lang(array('string' => '{var:1} file(s) were deleted', 'vars' => $deleted))));
}

// Design files are the theme's own assets: everybody sees them, only
// designers may touch them. The flag itself is therefore a designer
// decision, which is the rule edit_files.php applies too.
function pg_explorer_handle_files_design($type, $request, $user)
{
    if ($user['role'] > 1) {
        respond(array('status' => 'error', 'message' => lang('Access denied')));
    }

    $items = isset($request['items']) ? $request['items'] : array();
    $design = pg_explorer_files_design_flag($request);

    if ((is_array($items) == false) || (count($items) == 0)) {
        respond(array('status' => 'error', 'message' => lang('Invalid request.')));
    }

    $allowed = array();
    $errors = array();

    foreach ($items as $item) {

        $item_id = (int) (isset($item['id']) ? $item['id'] : 0);

        if ($item_id <= 0) {
            continue;
        }

        $file = db_item("SELECT id, name, folder FROM files WHERE id = '" . e($item_id) . "'");

        if (!$file) {
            continue;
        }

        if (check_edit_access($file['folder']) == false) {
            $errors[] = lang(array('string' => 'Access denied for {var:1}.', 'vars' => $file['name']));
            continue;
        }

        $allowed[] = (int) $file['id'];
    }

    if (count($allowed) > 0) {
        $audit_before = pg_audit_fetch_many('files', $allowed);

        db(
            "UPDATE files
            SET
                design = '" . e($design) . "',
                timestamp = UNIX_TIMESTAMP(),
                user = '" . USER_ID . "'
            WHERE id IN (" . implode(',', $allowed) . ")");

        pg_audit_record_many('update', 'files', $audit_before);

        log_activity(((count($allowed) == 1) ? lang(array('string' => '{var:1} file was updated', 'vars' => 1)) : lang(array('string' => '{var:1} files were updated', 'vars' => count($allowed)))), $_SESSION['sessionusername']);
    }

    respond(array(
        'status' => (count($errors) == 0) ? 'success' : ((count($allowed) > 0) ? 'partial' : 'error'),
        'request' => $type,
        'updated' => count($allowed),
        'errors' => $errors,
        'message' => (count($errors) > 0)
            ? implode(' ', $errors)
            : ((count($allowed) == 1) ? lang(array('string' => '{var:1} file was updated', 'vars' => 1)) : lang(array('string' => '{var:1} files were updated', 'vars' => count($allowed))))));
}
