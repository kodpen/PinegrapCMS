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

// The rights rows a save writes, from the rows the panel sent: only valid
// entries (a positive user id, rights 0, 1 or 2), the last entry of a user
// winning, each with the expiration date it stores. Rights 0 means the row
// is deleted, not stored as a zero.
function pg_explorer_folder_access_entries($entries)
{
    $result = array();

    foreach ((is_array($entries) ? $entries : array()) as $entry) {

        if (!is_array($entry)) {
            continue;
        }

        $target_user_id = (int) (isset($entry['user_id']) ? $entry['user_id'] : 0);
        $rights = (isset($entry['rights']) && is_scalar($entry['rights'])) ? (int) $entry['rights'] : -1;
        $expiration_date = (isset($entry['expiration_date']) && is_scalar($entry['expiration_date'])) ? trim((string) $entry['expiration_date']) : '';

        if (($target_user_id <= 0) || !in_array($rights, array(0, 1, 2), true)) {
            continue;
        }

        $result[$target_user_id] = array(
            'rights' => $rights,
            'expiration_date' => pg_explorer_folder_access_expiration_date($rights, $expiration_date));
    }

    return $result;
}

// The basic users (role 3) whose name, username or e-mail address contains
// $term, for the access panels' Add a user box: at most $limit, by username.
// Every customer and member account has role 3, so the panels list only the
// users who hold a right and find the others through this.
function pg_explorer_access_user_search_rows($term, $limit = 20)
{
    $term = trim((string) $term);

    if ($term === '') {
        return array();
    }

    $pattern = pg_list_like_pattern($term);

    $result = db_q(
        "SELECT user.user_id, user.user_username, contacts.first_name, contacts.last_name
        FROM user
        LEFT JOIN contacts ON contacts.id = user.user_contact
        WHERE (user.user_role = '3')
            AND ((user.user_username LIKE ?) OR (user.user_email LIKE ?) OR (contacts.first_name LIKE ?) OR (contacts.last_name LIKE ?))
        ORDER BY user.user_username
        LIMIT " . max(1, min(50, (int) $limit)),
        array($pattern, $pattern, $pattern, $pattern));

    $rows = array();

    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $rows[] = array(
                'id' => (int) $row['user_id'],
                'username' => (string) $row['user_username'],
                'name' => pg_user_display_name($row['first_name'], $row['last_name'], $row['user_username']));
        }
    }

    return $rows;
}

// The Add a user box of the access panels: managers and above only.
function pg_explorer_handle_access_user_search($type, $request, $user)
{
    if ($user['role'] > 2) {
        respond(array('status' => 'error', 'message' => lang('Access denied')));
    }

    $term = (isset($request['term']) && is_scalar($request['term'])) ? (string) $request['term'] : '';

    respond(array(
        'status' => 'success',
        'request' => $type,
        'users' => pg_explorer_access_user_search_rows($term)));
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

    // What Default (inherit) would give this folder and from which parent,
    // whatever the folder has now: the panel shows it while Default is
    // selected, before the save as well.
    $map = pg_explorer_folder_map();
    $inherited_from = '';
    $inherited_type = '';
    $current = (int) $folder['folder_parent'];
    $guard = array();

    while (($current > 0) && isset($map[$current]) && (isset($guard[$current]) == false)) {
        $guard[$current] = true;

        if ((string) $map[$current]['folder_access_control_type'] != '') {
            $inherited_from = $map[$current]['folder_name'];
            $inherited_type = (string) $map[$current]['folder_access_control_type'];
            break;
        }

        $current = (int) $map[$current]['folder_parent'];
    }

    $response = array(
        'status' => 'success',
        'request' => $type,
        'folder' => array(
            'id' => (int) $folder['folder_id'],
            'name' => $folder['folder_name'],
            'own_access_control_type' => (string) $folder['folder_access_control_type'],
            'access_control_type' => get_access_control_type($folder_id),
            'inherited_from' => $inherited_from,
            'inherited_type' => $inherited_type),
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

        // Only the users who hold a right here or above: the others are
        // found through the Add a user box (customers and members are role 3
        // too, so a store would list thousands).
        $listed_ids = array_unique(array_merge(array_keys($direct_rights), array_keys($inherited_rights)));

        $users = (count($listed_ids) > 0)
            ? db_items(
                "SELECT user_id, user_username
                FROM user
                WHERE (user_role = '3') AND (user_id IN (" . implode(',', array_map('intval', $listed_ids)) . "))
                ORDER BY user_username")
            : array();

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

        // The panel sends only the rows it changed.
        $entries = pg_explorer_folder_access_entries($request['user_rights']);

        // Only basic users are governed by folder rights, checked in one query.
        $basic_ids = (count($entries) > 0)
            ? db_values("SELECT user_id FROM user WHERE (user_role = '3') AND (user_id IN (" . implode(',', array_map('intval', array_keys($entries))) . "))")
            : array();

        foreach ((array) $basic_ids as $target_user_id) {

            $entry = $entries[(int) $target_user_id];

            // One row per user and folder: the old one goes, a right comes
            // back as a new row, and None leaves none.
            db("DELETE FROM aclfolder WHERE (aclfolder_user = '" . e($target_user_id) . "') AND (aclfolder_folder = '" . e($folder_id) . "')");

            if ($entry['rights'] > 0) {
                db(
                    "INSERT INTO aclfolder (aclfolder_user, aclfolder_folder, aclfolder_rights, expiration_date)
                    VALUES ('" . e($target_user_id) . "', '" . e($folder_id) . "', '" . e($entry['rights']) . "', '" . e($entry['expiration_date']) . "')");
            }
        }

        log_activity(lang(array('string' => 'folder access rights were updated for folder ({var:1})', 'vars' => array($folder['folder_name']))), $_SESSION['sessionusername']);
    }

    respond(array(
        'status' => 'success',
        'request' => $type,
        'message' => lang('The folder was edited successfully.')));
}
