<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Campaign profiles (email_campaign_profiles): the write path the create and
 * edit screens and the external API (/campaigns/profiles) share, and the
 * checks the API makes for its owner where the screens rely on the signed-in
 * session's user.
 *
 * The callers check the values first (pg_email_campaign_profile_form_validate()
 * on the screens, pg_email_campaign_profile_values_problem() and
 * pg_email_campaign_profile_owner_access_problem() in the API). The write
 * functions return 0/false when a query failed.
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
 * The columns a profile is written with, in table order.
 *
 * @return string[]
 */
function pg_email_campaign_profile_columns()
{
    return array(
        'name', 'enabled', 'action', 'action_item_id', 'subject', 'format', 'body', 'page_id',
        'from_name', 'from_email_address', 'reply_email_address', 'bcc_email_address',
        'schedule_time', 'schedule_length', 'schedule_unit', 'schedule_period', 'schedule_base',
        'purpose',
    );
}

/**
 * Creates (id 0) or updates a campaign profile.
 *
 * @param int   $profile_id 0 to create
 * @param array $values     Column name => value, the columns of
 *                          pg_email_campaign_profile_columns() (as
 *                          pg_email_campaign_profile_form_columns() returns)
 * @param int   $user_id    Who writes it: the creator of a new profile, the
 *                          last modifier of both
 * @return int the profile's id, 0 when a query failed
 */
function pg_email_campaign_profile_save($profile_id, $values, $user_id)
{
    $profile_id = (int) $profile_id;
    $columns = array();

    foreach (pg_email_campaign_profile_columns() as $column) {
        $columns[$column] = isset($values[$column]) && is_scalar($values[$column]) ? (string) $values[$column] : '';
    }

    $columns['last_modified_user_id'] = (int) $user_id;

    if ($profile_id <= 0) {
        $columns['created_user_id'] = (int) $user_id;

        if (!db_q(
            "INSERT INTO email_campaign_profiles (" . implode(', ', array_keys($columns)) . ", created_timestamp, last_modified_timestamp)
            VALUES (" . implode(', ', array_fill(0, count($columns), '?')) . ", UNIX_TIMESTAMP(), UNIX_TIMESTAMP())",
            array_values($columns))) {
            return 0;
        }

        return (int) mysqli_insert_id(db::$con);
    }

    $assignments = array();

    foreach (array_keys($columns) as $column) {
        $assignments[] = $column . ' = ?';
    }

    $parameters = array_values($columns);
    $parameters[] = $profile_id;

    if (!db_q(
        "UPDATE email_campaign_profiles
        SET " . implode(', ', $assignments) . ", last_modified_timestamp = UNIX_TIMESTAMP()
        WHERE id = ?",
        $parameters)) {
        return 0;
    }

    return $profile_id;
}

/**
 * Deletes a campaign profile. The campaigns it made stay; profiles that
 * follow it send nothing until they are given another trigger.
 *
 * @param int $profile_id
 * @return bool false when the query failed
 */
function pg_email_campaign_profile_delete($profile_id)
{
    return (bool) db_q("DELETE FROM email_campaign_profiles WHERE id = ?", array((int) $profile_id));
}

/**
 * Whether another profile already has this name, compared as the create and
 * edit form compares it (the column's collation).
 *
 * @param string $name
 * @param int    $except_id The profile being edited, 0 for a new one
 * @return bool
 */
function pg_email_campaign_profile_name_taken($name, $except_id = 0)
{
    return (int) db_value(
        "SELECT COUNT(*) FROM email_campaign_profiles WHERE name = '" . escape((string) $name) . "' AND id != '" . (int) $except_id . "'"
    ) > 0;
}

/**
 * Whether an account may pick the HTML body page, checked from the account
 * rather than the signed-in session: from the manager role up every page; a
 * user-role account a page in a folder open to every signed-in account
 * (public, guest, registration) or one it may edit.
 *
 * @param int $folder_id
 * @param int $user_id
 * @param int $role
 * @return bool
 */
function pg_email_campaign_profile_owner_page_access($folder_id, $user_id, $role)
{
    if ((int) $role < 3) {
        return true;
    }

    if (in_array(get_access_control_type($folder_id), array('public', 'guest', 'registration'), true)) {
        return true;
    }

    return pg_folder_edit_access($folder_id, (int) $user_id, (int) $role);
}

/**
 * The problem with a profile's trigger item and body page for an account,
 * the checks of the form made for an owner the session does not hold: the
 * item and the page must exist, the calendar event must be on a calendar the
 * account may use, the custom form page in a folder it may edit, the
 * followed profile one it created (user role), and the body page one it may
 * pick (pg_email_campaign_profile_owner_page_access()).
 *
 * @param array $values  As for pg_email_campaign_profile_values_problem(),
 *                       already valid there
 * @param int   $user_id
 * @param int   $role
 * @return string  The reason, '' when the account may pick them
 */
function pg_email_campaign_profile_owner_access_problem($values, $user_id, $role)
{
    $item_id = (int) ($values['action_item_id'] ?? 0);
    $user_id = (int) $user_id;
    $role = (int) $role;

    switch ($values['action'] ?? '') {
        case 'calendar_event_reserved':
            if ((int) db_value("SELECT COUNT(*) FROM calendar_events WHERE id = '" . $item_id . "'") < 1) {
                return lang('The calendar event does not exist.');
            }

            if (
                ($role >= 3)
                && ((int) db_value(
                    "SELECT COUNT(*)
                    FROM calendar_events_calendars_xref
                    INNER JOIN users_calendars_xref ON users_calendars_xref.calendar_id = calendar_events_calendars_xref.calendar_id
                    WHERE calendar_events_calendars_xref.calendar_event_id = '" . $item_id . "'
                    AND users_calendars_xref.user_id = '" . $user_id . "'") < 1)
            ) {
                return lang('Sorry, you do not have access to that calendar event.');
            }

            break;

        case 'custom_form_submitted':
            $page = db_item("SELECT page_folder, page_type FROM page WHERE page_id = '" . $item_id . "'");

            if (!$page || ((string) $page['page_type'] !== 'custom form')) {
                return lang('The custom form does not exist.');
            }

            if (!pg_folder_edit_access($page['page_folder'], $user_id, $role)) {
                return lang('Sorry, you do not have access to that custom form.');
            }

            break;

        case 'email_campaign_sent':
            $creator = db_value("SELECT created_user_id FROM email_campaign_profiles WHERE id = '" . $item_id . "'");

            if ($creator === null) {
                return lang('The campaign profile it is to follow does not exist.');
            }

            if (($role >= 3) && ((int) $creator !== $user_id)) {
                return lang('Sorry, you do not have access to that campaign profile.');
            }

            break;

        case 'product_ordered':
            if ((int) db_value("SELECT COUNT(*) FROM products WHERE id = '" . $item_id . "'") < 1) {
                return lang('The product does not exist.');
            }

            break;

        case 'contact_group_joined':
            if ((int) db_value("SELECT COUNT(*) FROM contact_groups WHERE id = '" . $item_id . "'") < 1) {
                return lang('The contact group does not exist.');
            }

            if (($role >= 3) && ((int) db_value("SELECT COUNT(*) FROM users_contact_groups_xref WHERE user_id = '" . $user_id . "' AND contact_group_id = '" . $item_id . "'") < 1)) {
                return lang('Sorry, you do not have access to that contact group.');
            }

            break;
    }

    if (($values['format'] ?? '') === 'html') {
        $folder = db_value("SELECT page_folder FROM page WHERE page_id = '" . (int) ($values['page_id'] ?? 0) . "'");

        if ($folder === null) {
            return lang('The page does not exist.');
        }

        if (!pg_email_campaign_profile_owner_page_access($folder, $user_id, $role)) {
            return lang('You are not authorized to select that page. Please select a different page.');
        }
    }

    return '';
}
