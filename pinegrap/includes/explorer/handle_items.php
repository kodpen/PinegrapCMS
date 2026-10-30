<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The file manager's tree sidebar, new folder, new file, rename and move
 * sub-actions. Each handler answers through respond(), which ends the
 * request.
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

// The name a new file is created under: a bare name gets a text extension,
// the way desktop file managers create "New file.txt".
function pg_explorer_new_file_name($name)
{
    if (mb_strrpos($name, '.') === false) {
        $name .= '.txt';
    }

    return $name;
}

// A move's answer: success when nothing failed, partial when some items
// moved and some did not, error when none moved and something failed.
function pg_explorer_move_status($moved, $errors)
{
    if (count($errors) == 0) {
        return 'success';
    }

    return ($moved > 0) ? 'partial' : 'error';
}

// The tree sidebar's children of one folder, loaded lazily as a branch
// opens: the folders the person may see, with what the tree draws for each.
function pg_explorer_handle_tree($type, $request, $user, $folders_that_user_has_access_to)
{
    // Read-only: nothing below writes to the session.
    pg_explorer_release_session();

    $node_id = (int) (isset($request['node_id']) ? $request['node_id'] : 0);
    $counts = pg_explorer_folder_counts();
    $children = array();

    foreach (pg_explorer_child_folders($node_id, $user, $folders_that_user_has_access_to) as $row) {
        $id = (int) $row['folder_id'];
        $count = isset($counts[$id]) ? $counts[$id] : array('folders' => 0, 'pages' => 0, 'files' => 0);

        $children[] = array(
            'id' => $id,
            'parent_id' => (int) $row['folder_parent'],
            'name' => $row['folder_name'],
            'access_control_type' => get_access_control_type($id),
            'archived' => ($row['folder_archived'] == '1'),
            'has_children' => ($count['folders'] > 0),
            'is_root' => ((int) $row['folder_parent'] == 0),
            'empty' => (($count['folders'] + ($count['pages'] ?? 0) + ($count['files'] ?? 0)) == 0),
            'can_edit' => check_edit_access($id));
    }

    respond(array(
        'status' => 'success',
        'request' => $type,
        'node_id' => $node_id,
        'children' => $children));
}

// A new folder inside a folder the person may edit; a name already taken
// gets a unique one.
function pg_explorer_handle_create_folder($type, $request, $user)
{
    $parent_id = (int) (isset($request['folder_id']) ? $request['folder_id'] : 0);

    if (($parent_id <= 0) || (check_edit_access($parent_id) == false) || pg_recycle_is_inside($parent_id)) {
        log_activity(lang('access denied because user does not have access to create folder in parent folder'), $_SESSION['sessionusername']);
        respond(array('status' => 'error', 'message' => lang('Access denied')));
    }

    $name = trim(isset($request['name']) ? $request['name'] : '');

    if ($name == '') {
        respond(array('status' => 'error', 'message' => lang('The folder must have a name. Please type in a name for the folder.')));
    }

    $name = get_unique_name(array('name' => $name, 'type' => 'folder'));

    $parent_level = (int) db_value("SELECT folder_level FROM folder WHERE folder_id = '" . e($parent_id) . "'");

    db(
        "INSERT INTO folder (
            folder_name,
            folder_parent,
            folder_level,
            folder_order,
            folder_access_control_type,
            folder_archived,
            folder_style,
            mobile_style_id,
            folder_timestamp,
            folder_user)
        VALUES (
            '" . e($name) . "',
            '" . e($parent_id) . "',
            '" . e($parent_level + 1) . "',
            '0',
            NULL,
            '0',
            '0',
            '0',
            UNIX_TIMESTAMP(),
            '" . e($user['id']) . "')");

    $new_folder_id = mysqli_insert_id(db::$con);

    log_activity(lang(array('string' => 'folder ({var:1}) was created', 'vars' => array($name))), $_SESSION['sessionusername']);

    respond(array(
        'status' => 'success',
        'request' => $type,
        'folder' => array('id' => (int) $new_folder_id, 'name' => $name),
        'message' => lang('The folder was created successfully.')));
}

// An empty file, named in place and edited later.
function pg_explorer_handle_create_file($type, $request, $user)
{
    $parent_id = (int) (isset($request['folder_id']) ? $request['folder_id'] : 0);

    if (($parent_id <= 0) || (check_edit_access($parent_id) == false) || pg_recycle_is_inside($parent_id)) {
        respond(array('status' => 'error', 'message' => lang('Access denied')));
    }

    $name = trim(isset($request['name']) ? $request['name'] : '');

    if ($name == '') {
        respond(array('status' => 'error', 'message' => lang('Please enter a name.')));
    }

    $name = pg_explorer_new_file_name($name);

    // Creating "shell.php" is uploading it with an extra step.
    if (pg_upload_name_blocked($name)) {
        respond(array('status' => 'error', 'message' => pg_upload_blocked_message($name)));
    }

    $name = prepare_file_name($name);
    $name = get_unique_name(array('name' => $name, 'type' => 'file'));

    $file_path = FILE_DIRECTORY_PATH . '/' . $name;

    if (@file_put_contents($file_path, '') === false) {
        respond(array('status' => 'error', 'message' => lang('The file could not be saved. Check write permission for the file directory.')));
    }

    $file_extension = mb_strtolower(pathinfo($name, PATHINFO_EXTENSION));

    db(
        "INSERT INTO files (
            name,
            folder,
            type,
            size,
            design,
            optimized,
            user,
            timestamp)
        VALUES (
            '" . e($name) . "',
            '" . e($parent_id) . "',
            '" . e($file_extension) . "',
            '0',
            '0',
            '0',
            '" . e($user['id']) . "',
            UNIX_TIMESTAMP())");

    $file_id = mysqli_insert_id(db::$con);

    pg_audit_record('create', 'files', (int) $file_id);

    log_activity(lang(array('string' => 'file ({var:1}) was created', 'vars' => $name)), $_SESSION['sessionusername']);

    respond(array(
        'status' => 'success',
        'request' => $type,
        'file' => array('id' => (int) $file_id, 'name' => $name),
        'message' => lang('The file was created successfully.')));
}

// A new name for one folder, page or file.
function pg_explorer_handle_rename($type, $request, $user)
{
    $item_kind = isset($request['item_kind']) ? $request['item_kind'] : '';
    $item_id = (int) (isset($request['item_id']) ? $request['item_id'] : 0);
    $name = trim(isset($request['name']) ? $request['name'] : '');

    if (($item_id <= 0) || ($name == '')) {
        respond(array('status' => 'error', 'message' => lang('Please enter a name.')));
    }

    switch ($item_kind) {

        case 'folder':

            $folder = db_item("SELECT folder_id, folder_name FROM folder WHERE folder_id = '" . e($item_id) . "'");

            if (!$folder) {
                respond(array('status' => 'error', 'message' => lang('Sorry, the folder could not be found.')));
            }

            if (check_edit_access($item_id) == false) {
                log_activity(lang('access denied because user does not have access to modify folder'), $_SESSION['sessionusername']);
                respond(array('status' => 'error', 'message' => lang('Access denied')));
            }

            db(
                "UPDATE folder SET
                    folder_name = '" . e($name) . "',
                    folder_timestamp = UNIX_TIMESTAMP(),
                    folder_user = '" . e($user['id']) . "'
                WHERE folder_id = '" . e($item_id) . "'");

            log_activity(lang(array('string' => 'folder ({var:1}) was modified', 'vars' => array($name))), $_SESSION['sessionusername']);

            respond(array('status' => 'success', 'request' => $type, 'name' => $name));
            break;

        case 'page':

            $page = db_item("SELECT page_id, page_name, page_folder FROM page WHERE page_id = '" . e($item_id) . "'");

            if (!$page) {
                respond(array('status' => 'error', 'message' => lang('Sorry, the page could not be found.')));
            }

            if (check_edit_access($page['page_folder']) == false) {
                log_activity(lang('access denied because user does not have access to modify folder'), $_SESSION['sessionusername']);
                respond(array('status' => 'error', 'message' => lang('Access denied')));
            }

            if ($name != $page['page_name']) {

                // Pages and files share the web root namespace, so a
                // page may not take a name that any page or file
                // already answers to.
                if (check_name_availability(array('name' => $name, 'ignore_item_id' => $item_id, 'ignore_item_type' => 'page')) == false) {
                    respond(array('status' => 'error', 'message' => lang('That name is already in use. Please choose a different name.')));
                }

                $audit_before = pg_audit_fetch('page', $item_id);
                db(
                    "UPDATE page SET
                        page_name = '" . e($name) . "',
                        page_timestamp = UNIX_TIMESTAMP(),
                        page_user = '" . e($user['id']) . "'
                    WHERE page_id = '" . e($item_id) . "'");
                pg_audit_record('update', 'page', $item_id, $audit_before);

                log_activity(lang(array('string' => 'page ({var:1}) was modified', 'vars' => array($name))), $_SESSION['sessionusername']);
            }

            respond(array('status' => 'success', 'request' => $type, 'name' => $name));
            break;

        case 'file':

            $file = db_item(
                "SELECT id, name, folder, design
                FROM files
                WHERE id = '" . e($item_id) . "'");

            if (!$file) {
                respond(array('status' => 'error', 'message' => lang('Sorry, the file could not be found.')));
            }

            if (
                (check_edit_access($file['folder']) == false)
                || (($file['design'] == 1) && ($user['role'] > 1))
            ) {
                log_activity(lang('access denied to modify files because user does not have access to file'), $_SESSION['sessionusername']);
                respond(array('status' => 'error', 'message' => lang('Access denied')));
            }

            // Renaming "photo.jpg" to "photo.php" is uploading a
            // PHP file with an extra step, so the rename keeps the
            // upload rule.
            if (($name != $file['name']) && pg_upload_name_blocked($name)) {
                respond(array('status' => 'error', 'message' => pg_upload_blocked_message($name)));
            }

            // Under another name the signing key would be served.
            if (($name != $file['name']) && pg_file_withheld($file['name'])) {
                respond(array('status' => 'error', 'message' => lang('Access denied')));
            }

            // Same preparation the file edit screen applies: ASCII
            // conversion, reserved names, length and character rules.
            $name = prepare_file_name($name);

            if ($name == $file['name']) {
                respond(array('status' => 'success', 'request' => $type, 'name' => $name));
            }

            if (check_name_availability(array('name' => $name, 'ignore_item_id' => $item_id, 'ignore_item_type' => 'file')) == false) {
                respond(array('status' => 'error', 'message' => lang('That name is already in use. Please choose a different name.')));
            }

            // The record's name is the address, so the disk file must
            // move first — and the row is only updated if it did.
            $old_file_path = FILE_DIRECTORY_PATH . '/' . $file['name'];
            $new_file_path = FILE_DIRECTORY_PATH . '/' . $name;

            if ((file_exists($old_file_path) == true) && (@rename($old_file_path, $new_file_path) == false)) {
                respond(array('status' => 'error', 'message' => lang('The file could not be renamed on the file system. Check write permission for the file directory.')));
            }

            $file_extension = mb_strtolower(pathinfo($name, PATHINFO_EXTENSION));

            $audit_before = pg_audit_fetch('files', $item_id);
            db(
                "UPDATE files SET
                    name = '" . e($name) . "',
                    type = '" . e($file_extension) . "',
                    timestamp = UNIX_TIMESTAMP(),
                    user = '" . e($user['id']) . "'
                WHERE id = '" . e($item_id) . "'");
            pg_audit_record('update', 'files', $item_id, $audit_before);

            log_activity(lang(array('string' => 'file ({var:1}) was modified', 'vars' => $name)), $_SESSION['sessionusername']);

            respond(array('status' => 'success', 'request' => $type, 'name' => $name));
            break;
    }

    respond(array('status' => 'error', 'message' => lang('Invalid request.')));
}

// Folders, pages and files moved into another folder (drag & drop, cut and
// paste). Each item is checked on its own; the answer names what failed.
function pg_explorer_handle_move($type, $request, $user)
{
    // A thousand rows may be about to move; nothing below writes to
    // the session, so the lock is not held for the duration.
    pg_explorer_release_session();

    $target_id = (int) (isset($request['target_folder_id']) ? $request['target_folder_id'] : 0);
    $items = isset($request['items']) ? $request['items'] : array();

    if (($target_id <= 0) || (is_array($items) == false) || (count($items) == 0)) {
        respond(array('status' => 'error', 'message' => lang('Invalid request.')));
    }

    if (check_edit_access($target_id) == false) {
        respond(array('status' => 'error', 'message' => lang('You do not have access to move items to the folder that you selected.')));
    }

    // The bin is only fed by the delete action, so restore metadata
    // always exists for what sits inside it.
    if (pg_recycle_is_inside($target_id)) {
        respond(array('status' => 'error', 'message' => lang('You do not have access to move items to the folder that you selected.')));
    }

    $moved = 0;
    $errors = array();

    foreach ($items as $item) {

        $item_kind = isset($item['kind']) ? $item['kind'] : '';
        $item_id = (int) (isset($item['id']) ? $item['id'] : 0);

        if ($item_id <= 0) {
            continue;
        }

        switch ($item_kind) {

            case 'file':

                $file = db_item("SELECT id, name, folder, design FROM files WHERE id = '" . e($item_id) . "'");

                if (!$file) {
                    $errors[] = lang('Sorry, the file could not be found.');
                    break;
                }

                if (
                    (check_edit_access($file['folder']) == false)
                    || (($file['design'] == 1) && ($user['role'] > 1))
                ) {
                    $errors[] = lang(array('string' => 'Access denied for {var:1}.', 'vars' => $file['name']));
                    break;
                }

                if ((int) $file['folder'] != $target_id) {

                    $audit_before = pg_audit_fetch('files', $item_id);
                    db(
                        "UPDATE files SET
                            folder = '" . e($target_id) . "',
                            timestamp = UNIX_TIMESTAMP(),
                            user = '" . e($user['id']) . "'
                        WHERE id = '" . e($item_id) . "'");
                    pg_audit_record('update', 'files', $item_id, $audit_before);

                    $moved++;
                }
                break;

            case 'page':

                $page = db_item("SELECT page_id, page_name, page_folder FROM page WHERE page_id = '" . e($item_id) . "'");

                if (!$page) {
                    $errors[] = lang('Sorry, the page could not be found.');
                    break;
                }

                if (check_edit_access($page['page_folder']) == false) {
                    $errors[] = lang(array('string' => 'Access denied for {var:1}.', 'vars' => $page['page_name']));
                    break;
                }

                if ((int) $page['page_folder'] != $target_id) {

                    $audit_before = pg_audit_fetch('page', $item_id);
                    db(
                        "UPDATE page SET
                            page_folder = '" . e($target_id) . "',
                            page_timestamp = UNIX_TIMESTAMP(),
                            page_user = '" . e($user['id']) . "'
                        WHERE page_id = '" . e($item_id) . "'");
                    pg_audit_record('update', 'page', $item_id, $audit_before);

                    $moved++;
                }
                break;

            case 'folder':

                $folder = db_item("SELECT folder_id, folder_name, folder_parent, folder_level FROM folder WHERE folder_id = '" . e($item_id) . "'");

                if (!$folder) {
                    $errors[] = lang('Sorry, the folder could not be found.');
                    break;
                }

                if ((int) $folder['folder_parent'] == 0) {
                    $errors[] = lang('The root folder cannot be moved.');
                    break;
                }

                if (check_edit_access($item_id) == false) {
                    $errors[] = lang(array('string' => 'Access denied for {var:1}.', 'vars' => $folder['folder_name']));
                    break;
                }

                // A folder cannot move into itself or its own subtree,
                // that would detach the branch from the tree.
                if (pg_explorer_is_self_or_descendant($item_id, $target_id)) {
                    $errors[] = lang(array('string' => 'A folder cannot be moved into itself ({var:1}).', 'vars' => $folder['folder_name']));
                    break;
                }

                if ((int) $folder['folder_parent'] != $target_id) {

                    $target_level = (int) db_value("SELECT folder_level FROM folder WHERE folder_id = '" . e($target_id) . "'");

                    db(
                        "UPDATE folder SET
                            folder_parent = '" . e($target_id) . "',
                            folder_level = '" . e($target_level + 1) . "',
                            folder_timestamp = UNIX_TIMESTAMP(),
                            folder_user = '" . e($user['id']) . "'
                        WHERE folder_id = '" . e($item_id) . "'");

                    // Levels below the moved folder shift with it.
                    pg_explorer_change_level($item_id, $target_level + 1);

                    $moved++;
                }
                break;
        }
    }

    if ($moved > 0) {
        $target_name = db_value("SELECT folder_name FROM folder WHERE folder_id = '" . e($target_id) . "'");
        log_activity(($moved == 1)
            ? lang(array('string' => '{var:1} item was moved to the folder {var:2}', 'vars' => array($moved, $target_name)))
            : lang(array('string' => '{var:1} items were moved to the folder {var:2}', 'vars' => array($moved, $target_name))), $_SESSION['sessionusername']);

        // An item dragged out of the bin is restored: forget it.
        if (pg_recycle_ready()) {
            foreach ($items as $item) {
                $item_kind = isset($item['kind']) ? $item['kind'] : '';
                $item_id = (int) (isset($item['id']) ? $item['id'] : 0);

                if (($item_id > 0) && in_array($item_kind, array('folder', 'page', 'file'), true)) {
                    db("DELETE FROM recycle_bin WHERE (item_type = '" . e($item_kind) . "') AND (item_id = '" . e($item_id) . "')");
                }
            }
        }
    }

    respond(array(
        'status' => pg_explorer_move_status($moved, $errors),
        'request' => $type,
        'moved' => $moved,
        'errors' => $errors,
        'message' => (count($errors) > 0)
            ? implode(' ', $errors)
            : (($moved == 1)
                ? lang(array('string' => '{var:1} item was moved.', 'vars' => $moved))
                : lang(array('string' => '{var:1} items were moved.', 'vars' => $moved)))));
}
