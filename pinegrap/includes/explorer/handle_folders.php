<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The file manager's upload folder choices and folder settings (order,
 * archive, page styles) sub-actions. Each handler answers through
 * respond(), which ends the request.
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

// The folder_archived value a request asks for: '1' only for an exact "1",
// an empty string (not archived) for anything else.
function pg_explorer_folder_archived_value($request)
{
    return ((isset($request['archived']) ? (string) $request['archived'] : '') == '1') ? '1' : '';
}

// Asked for when the upload window opens rather than baked into the
// page. The page is loaded once and then lived in: a folder made five
// minutes ago was not in a list rendered at load, so the window fell
// back to the top folder and quietly uploaded into the wrong place.
//
// Walked with pg_explorer_child_folders(), which is what draws the
// tree in the sidebar, so the list and the tree can never disagree
// about which folders exist or what order they come in. Each one is
// then held to check_edit_access() -- the same gate explorer_upload
// applies, so nothing is offered that the upload would refuse.
function pg_explorer_handle_folder_options($type, $request, $user, $folders_that_user_has_access_to)
{
    $folder_choices = array();

    $collect_folders = function ($parent_id, $depth) use (&$collect_folders, &$folder_choices, $user, $folders_that_user_has_access_to) {

        // Deep enough for any real site; a folder table with a loop in
        // it would otherwise take the request down with it.
        if ($depth > 50) {
            return;
        }

        foreach (pg_explorer_child_folders($parent_id, $user, $folders_that_user_has_access_to) as $folder_row) {

            $folder_row_id = (int) $folder_row['folder_id'];

            // The bin is not a destination, and skipping it skips what
            // is inside it: a binned folder has the bin as its parent.
            if (pg_recycle_is_inside($folder_row_id)) {
                continue;
            }

            if (check_edit_access($folder_row_id)) {
                $folder_choices[] = array(
                    'id' => $folder_row_id,
                    'name' => (string) $folder_row['folder_name'],
                    'depth' => $depth,
                    'archived' => ($folder_row['folder_archived'] == '1'));
            }

            $collect_folders($folder_row_id, $depth + 1);
        }
    };

    $collect_folders(0, 0);

    respond(array(
        'status' => 'success',
        'request' => $type,
        'folders' => $folder_choices));
}

// The everyday subset of edit_folder.php, with its rules: order and
// archive for anyone who can edit the folder, the two style fields
// only for managers and above — exactly the roles that screen gives
// the dropdowns to.
function pg_explorer_handle_folder_settings_get($type, $request, $user)
{
    $folder_id = (int) (isset($request['item_folder_id']) ? $request['item_folder_id'] : 0);

    $folder = db_item("SELECT folder_id, folder_name, folder_order, folder_style, mobile_style_id, folder_archived FROM folder WHERE folder_id = '" . e($folder_id) . "'");

    if (!$folder) {
        respond(array('status' => 'error', 'message' => lang('Sorry, the folder could not be found.')));
    }

    if (check_edit_access($folder_id) == false) {
        respond(array('status' => 'error', 'message' => lang('Access denied')));
    }

    $styles = null;

    if ($user['role'] < 3) {
        $styles = array(
            'desktop' => select_style($folder['folder_style']),
            'mobile' => get_mobile_style_options($folder['mobile_style_id']));
    }

    respond(array(
        'status' => 'success',
        'request' => $type,
        'folder' => array(
            'id' => (int) $folder['folder_id'],
            'name' => $folder['folder_name'],
            'order' => (int) $folder['folder_order'],
            'archived' => ($folder['folder_archived'] == '1'),
            'access_control_type' => get_access_control_type($folder_id)),
        'styles' => $styles));
}

// Saves what pg_explorer_handle_folder_settings_get() reads, under the same
// rules.
function pg_explorer_handle_folder_settings_set($type, $request, $user)
{
    $folder_id = (int) (isset($request['item_folder_id']) ? $request['item_folder_id'] : 0);

    $folder = db_item("SELECT folder_id, folder_name FROM folder WHERE folder_id = '" . e($folder_id) . "'");

    if (!$folder) {
        respond(array('status' => 'error', 'message' => lang('Sorry, the folder could not be found.')));
    }

    if (check_edit_access($folder_id) == false) {
        respond(array('status' => 'error', 'message' => lang('Access denied')));
    }

    $order = (int) (isset($request['order']) ? $request['order'] : 0);
    $archived = pg_explorer_folder_archived_value($request);

    $sql_style_fields = '';

    if ($user['role'] < 3) {
        $style = (int) (isset($request['style']) ? $request['style'] : 0);
        $mobile_style_id = (int) (isset($request['mobile_style_id']) ? $request['mobile_style_id'] : 0);

        $sql_style_fields =
            "folder_style = '" . e($style) . "',
            mobile_style_id = '" . e($mobile_style_id) . "',";
    }

    db(
        "UPDATE folder
        SET
            folder_order = '" . e($order) . "',
            folder_archived = '" . e($archived) . "',
            $sql_style_fields
            folder_timestamp = UNIX_TIMESTAMP(),
            folder_user = '" . USER_ID . "'
        WHERE folder_id = '" . e($folder_id) . "'");

    log_activity(lang(array('string' => 'folder ({var:1}) was modified', 'vars' => array($folder['folder_name']))), $_SESSION['sessionusername']);

    respond(array(
        'status' => 'success',
        'request' => $type,
        'message' => lang('The folder was edited successfully.')));
}
