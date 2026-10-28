<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The user account parts shared by the create and the edit screens
 * (add_user.php, edit_user.php): the two "User Rights" cards around the
 * permission rows of includes/user_permissions.php, the folder rights a
 * posted form gives, and the access records (folders, and for a user role
 * common regions, menus, contact groups, calendars and ad regions) written
 * from it. The screens keep what differs: the account card, validation, the
 * user row itself, sessions, notifications and redirects.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/**
 * The "User Rights" cards: the full-access card a privileged role shows and
 * the card that holds the permission rows.
 *
 * @param string $permission_rows  pg_user_permission_ui()['rows']
 * @return string
 */
function pg_user_form_rights_cards($permission_rows)
{
    return '
                    <div class="card mb-5 d-none" id="pg_permissions_everything">
                        <div class="card-header bg-reset border-0 d-flex justify-content-between align-items-center">
                            <span class="text-uppercase h5 fg-primary fw-bold mb-0">' . lang('User Rights') . '</span>
                        </div>
                        <div class="card-body">
                            ' . pg_user_permission_everything() . '
                        </div>
                    </div>

                    <div class="card mb-5" id="pg_permissions_block">
                        <div class="card-header bg-reset border-0 d-flex justify-content-between align-items-center">
                            <span class="text-uppercase h5 fg-primary fw-bold mb-0">' . lang('User Rights') . '</span>
                            <span class="small fg-secondary" data-pg-tally="' . h(lang(array('string' => '{var:1} of {var:2} areas on', 'vars' => array('{n}', '{m}')))) . '"></span>
                        </div>
                        <div class="card-body p-0">
                            <div class="pg-perms" id="pg_permissions" data-pg-empty="' . h(lang('No selection')) . '">' . $permission_rows . '
                            </div>
                        </div>
                    </div>';
}

/**
 * The rights a posted form gives on one folder: edit (2), view (1), or none
 * (0). View may carry an expiration date; an invalid date gives no access.
 *
 * @param array $post  The posted form
 * @param int|string $folder_id
 * @return array  'rights' => 0, 1 or 2; 'expiration_date' => 'Y-m-d' or ''
 */
function pg_user_form_folder_rights($post, $folder_id)
{
    $expiration = '';

    // if the user was given edit rights to this folder, then set rights value to 2
    if (($post['edit_' . $folder_id] ?? '') == 1) {
        $rights = 2;

    // else if the user was given view rights to this folder, then deal with that.
    } elseif (($post['view_' . $folder_id] ?? '') == 1) {
        // Remove spaces from beginning and end of date.
        $expiration_date = trim($post['view_' . $folder_id . '_expiration_date'] ?? '');

        // If an expiration date was entered, then validate it.
        if ($expiration_date != '') {
            // Convert date to storage format.
            $expiration_date = prepare_form_data_for_input($expiration_date, 'date');

            // Split date into parts.
            $expiration_date_parts = explode('-', $expiration_date);
            $year = $expiration_date_parts[0];
            $month = $expiration_date_parts[1] ?? '';
            $day = $expiration_date_parts[2] ?? '';

            // If the expiration date is valid, then give user access and keep the expiration date.
            if ((is_numeric($month) == true) && (is_numeric($day) == true) && (is_numeric($year) == true) && (checkdate($month, $day, $year) == true)) {
                $rights = 1;
                $expiration = $expiration_date;

            // Otherwise the expiration date is not valid, so do not give user access.
            } else {
                $rights = 0;
            }

        // Otherwise an expiration date was not entered, so just give user access.
        } else {
            $rights = 1;
        }

    // else the user was was given no rights to this folder, so set rights value to 0
    } else {
        $rights = 0;
    }

    return array('rights' => $rights, 'expiration_date' => $expiration);
}

/**
 * Inserts the items of one table the posted form selected for a user, from
 * the "<prefix><id>" boxes of its permission panel.
 *
 * @param string $select_sql  SELECT of the item ids, one `id` column
 * @param string $prefix  The posted field name before the id
 * @param string $xref_table
 * @param string $xref_column
 * @param int|string $user_id
 * @param array $post
 */
function pg_user_form_save_xref($select_sql, $prefix, $xref_table, $xref_column, $user_id, $post)
{
    $result = mysqli_query(db::$con, $select_sql) or output_error('Query failed.');

    $items = array();

    while ($row = mysqli_fetch_assoc($result)) {
        $items[] = $row;
    }

    // loop through all items and input them if they were selected
    foreach ($items as $item) {
        if (($post[$prefix . $item['id']] ?? '') == 1) {
            $query =
                "INSERT INTO " . $xref_table . " (
                    user_id,
                    " . $xref_column . ")
                VALUES (
                    ?,
                    ?)";
            db_q($query, array((string) $user_id, (string) $item['id'])) or output_error('Query failed.');
        }
    }
}

/**
 * Writes the access records a posted user form gives: the rights on every
 * folder and, for a user role (3), the common regions, shared components,
 * menus, contact groups (with manage contacts or e-mails), calendars (with
 * manage calendars) and ad regions (when ads are on).
 *
 * On an existing user ($replace) the old records are deleted first; a common
 * region made a shared component is not on the screen, so its grant stays
 * for the day the component is deleted and the region draws itself again.
 *
 * @param int|string $user_id
 * @param int $role  The role the user is saved with
 * @param array $post  The posted form
 * @param bool $replace  The user existed before
 */
function pg_user_form_save_access($user_id, $role, $post, $replace)
{
    if ($replace) {
        // Delete current access control records in order to add new ones.
        db_q("DELETE FROM aclfolder WHERE aclfolder_user = ?", array((string) $user_id)) or output_error('Query failed.');
    }

    // find all folders to write the access control list
    $result = mysqli_query(db::$con, "SELECT folder_id FROM folder") or output_error('Query failed');

    while ($row = mysqli_fetch_array($result)) {
        $folder_rights = pg_user_form_folder_rights($post, $row['folder_id']);

        db_q("INSERT INTO aclfolder (aclfolder_user, aclfolder_folder, aclfolder_rights, expiration_date) VALUES (?, ?, ?, ?)", array((string) $user_id, (string) $row['folder_id'], (string) $folder_rights['rights'], (string) $folder_rights['expiration_date'])) or output_error('Query failed.');
    }

    // Only a user role carries access to single items.
    if ($role != 3) {
        return;
    }

    $sql_regions = "SELECT cregion_id AS id FROM cregion";

    if ($replace) {
        db_q("DELETE FROM users_common_regions_xref WHERE user_id = ? AND " . pg_common_region_not_converted_sql('common_region_id'), array((string) $user_id)) or output_error('Query failed.');

        $sql_regions .= " WHERE " . pg_common_region_not_converted_sql();
    }

    pg_user_form_save_xref($sql_regions, 'common_region_', 'users_common_regions_xref', 'common_region_id', $user_id, $post);

    // the shared components opened to content editors the user was given
    pg_save_shared_component_grants($user_id, $post);

    if ($replace) {
        db_q("DELETE FROM users_menus_xref WHERE user_id = ?", array((string) $user_id)) or output_error('Query failed.');
    }

    pg_user_form_save_xref("SELECT id FROM menus", 'menu_', 'users_menus_xref', 'menu_id', $user_id, $post);

    if ($replace) {
        db_q("DELETE FROM users_contact_groups_xref WHERE user_id = ?", array((string) $user_id)) or output_error('Query failed.');
    }

    // if manage contacts or manage e-mails was checked, check to see which contact groups the user needs to be given access to
    if ((($post['manage_contacts'] ?? '') == 'yes') || (($post['manage_emails'] ?? '') == 'yes')) {
        pg_user_form_save_xref("SELECT id FROM contact_groups", 'contact_group_', 'users_contact_groups_xref', 'contact_group_id', $user_id, $post);
    }

    if ($replace) {
        db_q("DELETE FROM users_calendars_xref WHERE user_id = ?", array((string) $user_id)) or output_error('Query failed.');
    }

    // if manage calendars was checked, check to see which calendars the user needs to be given access to
    if (($post['manage_calendars'] ?? '') == 'yes') {
        pg_user_form_save_xref("SELECT id FROM calendars", 'calendar_', 'users_calendars_xref', 'calendar_id', $user_id, $post);
    }

    // If ads are enabled, then store access.
    if (ADS === true) {
        if ($replace) {
            db_q("DELETE FROM users_ad_regions_xref WHERE user_id = ?", array((string) $user_id)) or output_error('Query failed.');
        }

        pg_user_form_save_xref("SELECT id FROM ad_regions", 'ad_region_', 'users_ad_regions_xref', 'ad_region_id', $user_id, $post);
    }
}
