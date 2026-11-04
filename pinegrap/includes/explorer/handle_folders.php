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
// The style options of the settings drawer with the Default option naming
// the style the folder would take from its parents, as the details panel
// does (pg_explorer_style_info()). The root has no parent and keeps the
// plain label.
function pg_explorer_folder_style_default_label($options, $folder, $device)
{
    if ((int) $folder['folder_parent'] <= 0) {
        return $options;
    }

    $info = pg_explorer_style_info(0, (int) $folder['folder_parent'], $device);

    if ((string) $info['name'] === '') {
        return $options;
    }

    return pg_explorer_replace_first(
        '>' . h(lang('Default (from the parent folder)')) . '<',
        '>' . h(lang(array('string' => 'Default: {var:1} (from the parent folder)', 'vars' => array($info['name'])))) . '<',
        (string) $options);
}

// $subject with the first $search replaced by $replace.
function pg_explorer_replace_first($search, $replace, $subject)
{
    $at = strpos($subject, $search);

    return ($at === false) ? $subject : (substr($subject, 0, $at) . $replace . substr($subject, $at + strlen($search)));
}

function pg_explorer_folder_archived_value($request)
{
    return ((isset($request['archived']) ? (string) $request['archived'] : '') == '1') ? '1' : '';
}

// The order and archive values a settings save writes: the request's, or
// for the root folder (folder_parent 0) the stored ones. The root has
// neither field in the drawer: it is the only folder at its level, and
// archiving it would take the pages at the top of the site out of the
// page pickers.
function pg_explorer_folder_settings_order_archive($folder, $request)
{
    if ((int) $folder['folder_parent'] == 0) {
        return array((int) $folder['folder_order'], ($folder['folder_archived'] == '1') ? '1' : '');
    }

    // The drawer's range (-9999 to 9999): a value past the INT column was
    // cut by the database without a word.
    $order = max(-9999, min(9999, (int) (isset($request['order']) ? $request['order'] : 0)));

    return array($order, pg_explorer_folder_archived_value($request));
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

// A folder's settings: order and archive for anyone who can edit the
// folder, the two page style fields only for managers and above. The
// root folder has only the styles, which are the site's default page
// styles; its order and archive are left as they are.
function pg_explorer_handle_folder_settings_get($type, $request, $user)
{
    $folder_id = (int) (isset($request['item_folder_id']) ? $request['item_folder_id'] : 0);

    $folder = db_item("SELECT folder_id, folder_name, folder_parent, folder_order, folder_style, mobile_style_id, folder_archived FROM folder WHERE folder_id = '" . e($folder_id) . "'");

    if (!$folder) {
        respond(array('status' => 'error', 'message' => lang('Sorry, the folder could not be found.')));
    }

    if (check_edit_access($folder_id) == false) {
        respond(array('status' => 'error', 'message' => lang('Access denied')));
    }

    $styles = null;

    if ($user['role'] < 3) {
        $styles = array(
            'desktop' => pg_explorer_folder_style_default_label(select_style($folder['folder_style'], 'Default (from the parent folder)'), $folder, 'desktop'),
            'mobile' => pg_explorer_folder_style_default_label(get_mobile_style_options($folder['mobile_style_id'], 'Default (from the parent folder)'), $folder, 'mobile'));
    }

    respond(array(
        'status' => 'success',
        'request' => $type,
        'folder' => array(
            'id' => (int) $folder['folder_id'],
            'name' => $folder['folder_name'],
            'is_root' => ((int) $folder['folder_parent'] == 0),
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

    $folder = db_item("SELECT folder_id, folder_name, folder_parent, folder_order, folder_archived FROM folder WHERE folder_id = '" . e($folder_id) . "'");

    if (!$folder) {
        respond(array('status' => 'error', 'message' => lang('Sorry, the folder could not be found.')));
    }

    if (check_edit_access($folder_id) == false) {
        respond(array('status' => 'error', 'message' => lang('Access denied')));
    }

    list($order, $archived) = pg_explorer_folder_settings_order_archive($folder, $request);

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

    $message = lang('The folder was edited successfully.');

    // Archiving closes the pages directly in the folder to visitors
    // (get_page.php answers 410), so the save says how many that was.
    if (($archived === '1') && ($folder['folder_archived'] != '1')) {
        $closed = (int) db_value("SELECT COUNT(*) FROM page WHERE page_folder = '" . e($folder_id) . "'");
        if ($closed > 0) {
            $message .= ' ' . lang(array('string' => '{var:1} page(s) in it are now closed to visitors.', 'vars' => $closed));
        }
    }

    respond(array(
        'status' => 'success',
        'request' => $type,
        'message' => $message));
}
