<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The file manager's folder access panel sub-actions: read and write the
 * access control type and the basic users' folder rights. Each handler
 * answers through respond(), which ends the request.
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

// The expiration date a rights row stores. It only applies to view access
// (rights 1), the same way the user edit screen stores it, and only a real
// Y-m-d date is kept; anything else is stored empty, which means no end.
function pg_explorer_folder_access_expiration_date($rights, $expiration_date)
{
    $sql_expiration_date = '';

    if (($rights == 1) && ($expiration_date != '')) {
        $expiration_date_parts = explode('-', $expiration_date);

        if (
            (count($expiration_date_parts) == 3)
            && is_numeric($expiration_date_parts[0])
            && is_numeric($expiration_date_parts[1])
            && is_numeric($expiration_date_parts[2])
            && checkdate((int) $expiration_date_parts[1], (int) $expiration_date_parts[2], (int) $expiration_date_parts[0])
        ) {
            $sql_expiration_date = $expiration_date;
        }
    }

    return $sql_expiration_date;
}

// The folder access panel: the folder's own and effective access type,
// where an inherited type comes from, and for a manager each basic user's
// direct and inherited rights.
function pg_explorer_handle_folder_access_get($type, $request, $user)
{
    $folder_id = (int) (isset($request['item_folder_id']) ? $request['item_folder_id'] : 0);

    $folder = db_item("SELECT folder_id, folder_name, folder_parent, folder_access_control_type FROM folder WHERE folder_id = '" . e($folder_id) . "'");

    if (!$folder) {
        respond(array('status' => 'error', 'message' => lang('Sorry, the folder could not be found.')));
    }

    if (check_edit_access($folder_id) == false) {
        respond(array('status' => 'error', 'message' => lang('Access denied')));
    }

    // Where the effective type comes from when this folder inherits.
    $map = pg_explorer_folder_map();
    $inherited_from = '';
    $current = (int) $folder['folder_parent'];
    $guard = array();

    if ((string) $folder['folder_access_control_type'] == '') {
        while (($current > 0) && isset($map[$current]) && (isset($guard[$current]) == false)) {
            $guard[$current] = true;

            if ((string) $map[$current]['folder_access_control_type'] != '') {
                $inherited_from = $map[$current]['folder_name'];
                break;
            }

            $current = (int) $map[$current]['folder_parent'];
        }
    }

    $response = array(
        'status' => 'success',
        'request' => $type,
        'folder' => array(
            'id' => (int) $folder['folder_id'],
            'name' => $folder['folder_name'],
            'own_access_control_type' => (string) $folder['folder_access_control_type'],
            'access_control_type' => get_access_control_type($folder_id),
            'inherited_from' => $inherited_from),
        'can_manage_users' => ($user['role'] <= 2),
        'users' => array());

    // Per-user rights are a manager concern, the same area rule the
    // user edit screen enforces.
    if ($user['role'] <= 2) {

        // Ancestor chain for inherited rights.
        $chain = array();
        $current = (int) $folder['folder_parent'];
        $guard = array();

        while (($current > 0) && isset($map[$current]) && (isset($guard[$current]) == false)) {
            $guard[$current] = true;
            $chain[] = $current;
            $current = (int) $map[$current]['folder_parent'];
        }

        $direct_rights = array();
        $inherited_rights = array();

        $rows = db_items(
            "SELECT aclfolder_user, aclfolder_folder, aclfolder_rights, expiration_date
            FROM aclfolder
            WHERE aclfolder_folder IN ('" . e($folder_id) . "'" . ((count($chain) > 0) ? ", " . implode(',', array_map('intval', $chain)) : "") . ")
                AND aclfolder_rights > 0");

        if ($rows) {
            foreach ($rows as $row) {
                $row_user_id = (int) $row['aclfolder_user'];

                if ((int) $row['aclfolder_folder'] == $folder_id) {
                    $direct_rights[$row_user_id] = array(
                        'rights' => (int) $row['aclfolder_rights'],
                        'expiration_date' => (string) $row['expiration_date']);
                } else {
                    $existing = isset($inherited_rights[$row_user_id]) ? $inherited_rights[$row_user_id] : 0;
                    $inherited_rights[$row_user_id] = max($existing, (int) $row['aclfolder_rights']);
                }
            }
        }

        $users = db_items(
            "SELECT user_id, user_username
            FROM user
            WHERE user_role = '3'
            ORDER BY user_username");

        if ($users) {
            foreach ($users as $row) {
                $row_user_id = (int) $row['user_id'];

                $response['users'][] = array(
                    'id' => $row_user_id,
                    'username' => $row['user_username'],
                    'rights' => isset($direct_rights[$row_user_id]) ? $direct_rights[$row_user_id]['rights'] : 0,
                    'expiration_date' => isset($direct_rights[$row_user_id]) ? $direct_rights[$row_user_id]['expiration_date'] : '',
                    'inherited_rights' => isset($inherited_rights[$row_user_id]) ? $inherited_rights[$row_user_id] : 0);
            }
        }
    }

    respond($response);
}

// Saves the folder access panel: the access control type, and for a
// manager the basic users' rights.
function pg_explorer_handle_folder_access_set($type, $request, $user)
{
    $folder_id = (int) (isset($request['item_folder_id']) ? $request['item_folder_id'] : 0);

    $folder = db_item("SELECT folder_id, folder_name FROM folder WHERE folder_id = '" . e($folder_id) . "'");

    if (!$folder) {
        respond(array('status' => 'error', 'message' => lang('Sorry, the folder could not be found.')));
    }

    if (check_edit_access($folder_id) == false) {
        log_activity(lang('access denied because user does not have access to modify folder'), $_SESSION['sessionusername']);
        respond(array('status' => 'error', 'message' => lang('Access denied')));
    }

    // The access control type: '' means inherit from the parent.
    if (isset($request['access_control_type'])) {

        $access_control_type = $request['access_control_type'];

        if (in_array($access_control_type, array('', 'public', 'guest', 'registration', 'membership', 'private'), true) == false) {
            respond(array('status' => 'error', 'message' => lang('Invalid request.')));
        }

        db(
            "UPDATE folder SET
                folder_access_control_type = '" . e($access_control_type) . "',
                folder_timestamp = UNIX_TIMESTAMP(),
                folder_user = '" . e($user['id']) . "'
            WHERE folder_id = '" . e($folder_id) . "'");

        log_activity(lang(array('string' => 'folder ({var:1}) was modified', 'vars' => array($folder['folder_name']))), $_SESSION['sessionusername']);
    }

    // Per-user rights: managers and above only, and only for basic
    // users — the same boundary the user edit screen draws.
    if (isset($request['user_rights']) && is_array($request['user_rights'])) {

        if ($user['role'] > 2) {
            respond(array('status' => 'error', 'message' => lang('Access denied')));
        }

        foreach ($request['user_rights'] as $entry) {

            $target_user_id = (int) (isset($entry['user_id']) ? $entry['user_id'] : 0);
            $rights = (int) (isset($entry['rights']) ? $entry['rights'] : 0);
            $expiration_date = trim(isset($entry['expiration_date']) ? $entry['expiration_date'] : '');

            if (($target_user_id <= 0) || (in_array($rights, array(0, 1, 2), true) == false)) {
                continue;
            }

            // Only basic users are governed by folder rights.
            $target_role = db_value("SELECT user_role FROM user WHERE user_id = '" . e($target_user_id) . "'");

            if ($target_role === false || (int) $target_role != 3) {
                continue;
            }

            $sql_expiration_date = pg_explorer_folder_access_expiration_date($rights, $expiration_date);

            $existing = db_value(
                "SELECT COUNT(*)
                FROM aclfolder
                WHERE (aclfolder_user = '" . e($target_user_id) . "') AND (aclfolder_folder = '" . e($folder_id) . "')");

            if ($existing > 0) {
                db(
                    "UPDATE aclfolder SET
                        aclfolder_rights = '" . e($rights) . "',
                        expiration_date = '" . e($sql_expiration_date) . "'
                    WHERE (aclfolder_user = '" . e($target_user_id) . "') AND (aclfolder_folder = '" . e($folder_id) . "')");
            } else {
                db(
                    "INSERT INTO aclfolder (aclfolder_user, aclfolder_folder, aclfolder_rights, expiration_date)
                    VALUES ('" . e($target_user_id) . "', '" . e($folder_id) . "', '" . e($rights) . "', '" . e($sql_expiration_date) . "')");
            }
        }

        log_activity(lang(array('string' => 'folder access rights were updated for folder ({var:1})', 'vars' => array($folder['folder_name']))), $_SESSION['sessionusername']);
    }

    respond(array(
        'status' => 'success',
        'request' => $type,
        'message' => lang('The folder was edited successfully.')));
}
