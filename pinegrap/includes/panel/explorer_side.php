<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Panel actions - the explorer's tree in the main menu.
 *
 *   explorer_side  the branches of the File Manager's folder tree or the
 *                  Catalog Manager's product group tree that a menu panel
 *                  draws as links (assets/js/explorer_side.js); read only.
 *                  Asked: area (files, catalog), node_ids (0 is the top),
 *                  shortcut_ids (the Pinned and Recent lists this browser
 *                  keeps). Answered: nodes, a list of {id, rows}, and
 *                  shortcuts, the asked ones this person may see, {id,
 *                  name}, in the order asked.
 *
 * Called by pg_panel_dispatch() (includes/panel/actions.php); see that file
 * for the contract.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_PANEL_ACTIONS')) {
    exit;
}

function pg_panel_explorer_side($request, $action)
{
    // Global on purpose: check_folder_access_in_array() reads both through
    // `global`; as locals an administrator would see no folders at all.
    global $user, $folders_that_user_has_access_to;

    $user = validate_user();
    validate_token();

    $area = isset($request['area']) ? (string) $request['area'] : '';
    $role = (int) $user['role'];
    $has_edit_acl = (($area === 'files') && ($role === 3)) ? (no_acl_check($user['id']) == true) : false;

    // The same people the area's menu row and screen admit, refused in JSON.
    if (!pg_explorer_side_allowed($area, $role, ($user['manage_ecommerce'] == true), $has_edit_acl, (defined('ECOMMERCE') && (ECOMMERCE === true)))) {
        respond(array('status' => 'error', 'message' => lang('Access denied')));
    }

    require_once(PG_FUNCTIONS_DIR . '/view_folder_and_files_f.php');

    // Read only: nothing below writes to the session, and the folder and
    // view the explorer keeps there are left alone.
    pg_explorer_release_session();

    if (!headers_sent()) {
        header('Cache-Control: private, no-store');
    }

    $folders_that_user_has_access_to = ($role === 3) ? get_folders_that_user_has_access_to($user['id']) : array();
    $nodes = array();

    foreach (pg_explorer_side_ids(isset($request['node_ids']) ? $request['node_ids'] : array(0)) as $node) {
        $nodes[] = array(
            'id' => $node,
            'rows' => ($area === 'files')
                ? pg_explorer_side_folders($node, $user, $folders_that_user_has_access_to)
                : pg_explorer_side_groups($node));
    }

    // Pinned and Recent come from the browser, which may have kept them for
    // someone else or before a right was taken away: only the folders or
    // groups this person may see go back, under their names of today.
    $shortcuts = array();

    foreach (pg_explorer_side_ids(isset($request['shortcut_ids']) ? $request['shortcut_ids'] : array(), 50) as $id) {
        $row = ($area === 'files')
            ? pg_explorer_side_folder_shortcut($id, $folders_that_user_has_access_to)
            : pg_explorer_side_group_shortcut($id);

        if ($row !== null) {
            $shortcuts[] = $row;
        }
    }

    respond(array('status' => 'success', 'area' => $area, 'nodes' => $nodes, 'shortcuts' => $shortcuts));
}

/**
 * A folder of the Pinned or Recent list, if this person sees it (the
 * explorer's rule; never the bin).
 *
 * @return array|null id, name
 */
function pg_explorer_side_folder_shortcut($id, $folders_that_user_has_access_to)
{
    $map = pg_explorer_folder_map();

    if (($id < 1) || !isset($map[$id]) || ($id === (int) pg_recycle_folder_id(false)) || !pg_explorer_folder_visible($id, $folders_that_user_has_access_to)) {
        return null;
    }

    return array('id' => $id, 'name' => (string) $map[$id]['folder_name']);
}

/**
 * A product group of the Pinned or Recent list, if it is a live group.
 *
 * @return array|null id, name
 */
function pg_explorer_side_group_shortcut($id)
{
    $map = pg_catalog_group_map();

    if (($id < 1) || !isset($map[$id])) {
        return null;
    }

    return array('id' => $id, 'name' => (string) $map[$id]['name']);
}

/**
 * The folders under one folder that this person sees, from the explorer's
 * own rule (pg_explorer_child_folders(): the bin left out, a role 3 user's
 * granted forest at the top).
 *
 * @return array pg_explorer_side_folder_row() rows
 */
function pg_explorer_side_folders($node, $user, $folders_that_user_has_access_to)
{
    static $under = null;

    // Which folders hold a folder this person sees, from the one folder map.
    if ($under === null) {
        $under = array();
        $bin = (int) pg_recycle_folder_id(false);

        foreach (pg_explorer_folder_map() as $id => $row) {
            if (($id !== $bin) && pg_explorer_folder_visible($id, $folders_that_user_has_access_to)) {
                $under[(int) $row['folder_parent']] = true;
            }
        }
    }

    $rows = array();

    foreach (pg_explorer_child_folders($node, $user, $folders_that_user_has_access_to) as $row) {
        $id = (int) $row['folder_id'];
        $rows[] = pg_explorer_side_folder_row($row, get_access_control_type($id), isset($under[$id]));
    }

    return $rows;
}

/**
 * The live product groups under one group (pg_catalog_child_groups()).
 *
 * @return array pg_explorer_side_group_row() rows
 */
function pg_explorer_side_groups($node)
{
    static $under = null;

    if ($under === null) {
        $under = array();

        foreach (pg_catalog_group_map() as $row) {
            $under[(int) $row['parent_id']] = true;
        }
    }

    $rows = array();

    foreach (pg_catalog_child_groups($node) as $row) {
        $rows[] = pg_explorer_side_group_row($row, isset($under[(int) $row['id']]));
    }

    return $rows;
}
