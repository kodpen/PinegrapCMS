<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The file manager's "Shared" sub-action: the closed folders opened to
 * specific users. The handler answers through respond(), which ends the
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

// A rights row's expiration date as the shared list shows it. The date only
// exists for view rights; edit rights do not expire, and an empty date (or
// MySQL's zero date) means no end.
function pg_explorer_shared_expiration_date($value)
{
    $expiration_date = (string) $value;

    if (($expiration_date == '0000-00-00') || ($expiration_date == '')) {
        $expiration_date = '';
    }

    return $expiration_date;
}

// A virtual listing, not a folder: the manager's answer to "who can
// reach what". Only closed folders can be shared in any meaningful
// sense, so the list is membership and private folders that carry at
// least one rights row, each with the people it was opened to.
//
// Manager area, like every other per-user rights screen: a basic
// user is the subject of these rows, not their audience.
function pg_explorer_handle_shared_list($type, $request, $user, $folders_that_user_has_access_to)
{
    if ($user['role'] > 2) {
        respond(array('status' => 'error', 'message' => lang('Access denied')));
    }

    $map = pg_explorer_folder_map();
    $bin_id = pg_recycle_folder_id(false);

    $rows = db_items(
        "SELECT
            aclfolder.aclfolder_folder AS folder_id,
            aclfolder.aclfolder_user AS user_id,
            aclfolder.aclfolder_rights AS rights,
            aclfolder.expiration_date AS expiration_date,
            user.user_username AS username,
            user.user_contact AS contact_id,
            contacts.salutation AS contact_salutation,
            contacts.first_name AS contact_first_name,
            contacts.last_name AS contact_last_name,
            contacts.nickname AS contact_nickname,
            contacts.suffix AS contact_suffix
        FROM aclfolder
        LEFT JOIN user ON aclfolder.aclfolder_user = user.user_id
        LEFT JOIN contacts ON user.user_contact = contacts.id
        WHERE
            (aclfolder.aclfolder_rights > '0')
            AND (user.user_role = '3')
        ORDER BY user.user_username");

    $groups = array();
    $today = date('Y-m-d');

    if ($rows) {
        foreach ($rows as $row) {

            $row_folder_id = (int) $row['folder_id'];

            if (isset($map[$row_folder_id]) == false) {
                continue;
            }

            // A rights row whose user is gone is dead data: it opens
            // the folder to nobody and would list a nameless person.
            if ((string) $row['username'] == '') {
                continue;
            }

            // Binned folders keep their rights rows so a restore puts
            // them back intact; they are not shared with anybody
            // while they sit in the bin.
            if (($bin_id > 0) && pg_explorer_is_self_or_descendant($bin_id, $row_folder_id)) {
                continue;
            }

            if (pg_explorer_folder_visible($row_folder_id, $folders_that_user_has_access_to) == false) {
                continue;
            }

            // Only closed folders: rights over a public folder grant
            // nothing that everybody does not already have.
            $access_control_type = get_access_control_type($row_folder_id);

            if (in_array($access_control_type, array('membership', 'private'), true) == false) {
                continue;
            }

            if (isset($groups[$row_folder_id]) == false) {

                $crumbs = array();

                foreach (pg_explorer_breadcrumb($row_folder_id, $folders_that_user_has_access_to) as $crumb) {
                    $crumbs[] = $crumb['name'];
                }

                $groups[$row_folder_id] = array(
                    'id' => $row_folder_id,
                    'name' => $map[$row_folder_id]['folder_name'],
                    'path' => implode(' / ', $crumbs),
                    'archived' => ($map[$row_folder_id]['folder_archived'] == '1'),
                    'access_control_type' => $access_control_type,
                    'access_icon' => pg_explorer_access_icon($access_control_type),
                    'can_edit' => check_edit_access($row_folder_id),
                    'grants' => array());
            }

            $expiration_date = pg_explorer_shared_expiration_date($row['expiration_date']);

            $groups[$row_folder_id]['grants'][] = array(
                'user_id' => (int) $row['user_id'],
                'username' => (string) $row['username'],
                'contact_id' => (int) $row['contact_id'],
                'contact_name' => pg_explorer_contact_name($row),
                'rights' => (int) $row['rights'],
                'expiration_date' => $expiration_date,
                'expired' => (($expiration_date != '') && ($expiration_date < $today)));
        }
    }

    // Path order, so a subtree reads together.
    $ordered = array_values($groups);

    usort($ordered, 'pg_explorer_compare_shared_groups');

    respond(array(
        'status' => 'success',
        'request' => $type,
        'groups' => $ordered));
}
