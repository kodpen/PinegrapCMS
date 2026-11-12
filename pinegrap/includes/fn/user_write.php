<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The writes of a panel account: create, update and delete, with the checks
 * the user screens make before them (the role an editor may give, a username
 * and e-mail address that are free). add_user.php, edit_user.php and
 * delete_users.php call them, so every path that writes an account keeps the
 * same rules.
 *
 * $fields is shaped as the user form posts it: username, email, role,
 * home_page, badge, badge_label, reward_points, the right switches
 * (manage_contacts = 'yes', view_card_data = '1'...), set_page_type_<type>,
 * the access boxes (edit_<folder id>, view_<folder id>, menu_<id>...) and
 * contact_id. A field left out writes what an unticked box writes.
 *
 * Each function stops with output_error() on a failed query, as the screens
 * did; the caller writes its own site log line.
 *
 * Loaded where it is needed (require_once), not by functions.php.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/user_permissions.php');
require_once(PG_FUNCTIONS_DIR . '/includes/forms/user_form.php');

/**
 * Whether an editor may give an account this role: an administrator any
 * role, anyone else only a role below their own. A role that is not there
 * (null) is refused to everyone but an administrator.
 *
 * @param int|string $editor_role
 * @param int|string|null $role
 * @return bool
 */
function pg_user_role_writable($editor_role, $role)
{
    return ($editor_role == 0) || ($editor_role < $role);
}

/**
 * The page types a user may be allowed to give a page, as the field names
 * after set_page_type_: the three that are always there, then those of the
 * modules that are on.
 *
 * @param bool $forms
 * @param bool $calendars
 * @param bool $ecommerce
 * @return string[]
 */
function pg_user_write_page_types($forms, $calendars, $ecommerce)
{
    $types = array('email_a_friend', 'folder_view', 'photo_gallery');

    if ($forms) {
        $types = array_merge($types, array('custom_form', 'custom_form_confirmation', 'form_list_view', 'form_item_view', 'form_view_directory'));
    }

    if ($calendars) {
        $types = array_merge($types, array('calendar_view', 'calendar_event_view'));
    }

    if ($ecommerce) {
        $types = array_merge($types, array(
            'catalog', 'catalog_detail', 'express_order', 'order_form', 'shopping_cart',
            'shipping_address_and_arrival', 'shipping_method', 'billing_information', 'order_preview', 'order_receipt'));
    }

    return $types;
}

/**
 * The page types of the modules switched on for this site.
 *
 * @return string[]
 */
function pg_user_write_site_page_types()
{
    return pg_user_write_page_types((FORMS == true), (CALENDARS == true), (ECOMMERCE == true));
}

/**
 * What stops a username and e-mail address from being written, or '':
 * username_required, email_invalid, username_taken, email_taken. A username
 * and an e-mail address share one namespace: either signs in, so neither may
 * be another account's username or address. $user_id is the account being
 * changed (0 for a new one), which may keep its own.
 *
 * @param string $username  trimmed
 * @param string $email  trimmed
 * @param int $user_id
 * @return string
 */
function pg_user_write_problem($username, $email, $user_id = 0)
{
    if ((string) $username === '') {
        return 'username_required';
    }

    if (validate_email_address($email) == false) {
        return 'email_invalid';
    }

    $taken = "SELECT user_id FROM user WHERE ((user_username = ?) OR (user_email = ?)) AND (user_id != ?)";

    $result = db_q($taken, array((string) $username, (string) $username, (string) (int) $user_id)) or output_error('Query failed.');

    if (mysqli_num_rows($result) > 0) {
        return 'username_taken';
    }

    $result = db_q($taken, array((string) $email, (string) $email, (string) (int) $user_id)) or output_error('Query failed.');

    if (mysqli_num_rows($result) > 0) {
        return 'email_taken';
    }

    return '';
}

/**
 * The sentence for a problem of pg_user_write_problem(), without a closing
 * full stop.
 *
 * @param string $problem
 * @return string
 */
function pg_user_write_problem_message($problem)
{
    switch ($problem) {
        case 'username_required':
            return lang(array('string' => '{var:1} is required', 'vars' => lang('Username')));
        case 'email_invalid':
            return lang(array('string' => '{var:1} is invalid', 'vars' => lang('The email address')));
        case 'username_taken':
            return lang('The username that you entered is already in use');
        case 'email_taken':
            return lang('The email address that you entered is already in use');
    }

    return '';
}

/**
 * Whether a contact record exists and the account already linked to it, for
 * pg_user_form_contact_problem(): 'found' => bool, 'username' => the linked
 * account's username, null when none. A contact has one account at most:
 * membership, the contact's user card and the user lookups read one.
 *
 * @param mixed $contact_id
 * @return array
 */
function pg_user_contact_holder($contact_id)
{
    $contact_id = (is_scalar($contact_id) && ctype_digit((string) $contact_id)) ? (string) (int) $contact_id : '0';

    $found = (db_value("SELECT COUNT(*) FROM contacts WHERE id = '" . e($contact_id) . "'") > 0);

    $result = db_q("SELECT user_username FROM user WHERE user_contact = ? ORDER BY user_id LIMIT 1", array($contact_id)) or output_error('Query failed.');
    $row = mysqli_fetch_assoc($result);

    return array('found' => $found, 'username' => $row ? (string) $row['user_username'] : null);
}

/**
 * Whether no other enabled administrator than this account is left: such an
 * account keeps its role and is not deleted, or nobody could reach the
 * administrator-only screens.
 *
 * @param int $user_id
 * @return bool
 */
function pg_user_is_last_admin($user_id)
{
    $result = db_q(
        "SELECT user_id
        FROM user
        WHERE
            (user_role = '0')
            AND (user_id != ?)" . pg_user_enabled_sql(),
        array((string) (int) $user_id)) or output_error('Query failed.');

    return (mysqli_num_rows($result) == 0);
}

/**
 * Creates an account with a random password nobody is told (the user sets
 * their own through the set password link), its access records and its
 * audit line. The caller has checked the role (pg_user_role_writable()) and
 * the username and e-mail address (pg_user_write_problem()).
 *
 * @param array $fields
 * @param int $editor_id  The account that creates it
 * @return array  'id' => the new account, 'password' => the random password
 */
function pg_user_create($fields, $editor_id)
{
    $random_password = get_random_string(array(
        'type' => 'lowercase_letters',
        'length' => 10));

    $sql_set_page_type_columns = '';
    $sql_set_page_type_values = '';

    foreach (pg_user_write_site_page_types() as $type) {
        $sql_set_page_type_columns .= " user_set_page_type_" . $type . ",";
        $sql_set_page_type_values .= " '" . escape($fields['set_page_type_' . $type] ?? '') . "',";
    }

    // Modern password hash plus the algo stamp; see pg_password_insert_fragments().
    $sql_password = pg_password_insert_fragments($random_password);

    // The workspace rights, when their columns exist.
    $sql_ws_columns = pg_user_permission_ws_sql('columns', $fields);
    $sql_ws_values = pg_user_permission_ws_sql('values', $fields);

    // The ERP's read-only right, when its column exists.
    $sql_erp_readonly_column = pg_user_erp_readonly_sql('columns', $fields);
    $sql_erp_readonly_value = pg_user_erp_readonly_sql('values', $fields);

    $query =
        "INSERT INTO user (
            user_username,
            user_email,
            user_password,
            {$sql_password['algo_column']}
            user_role,
            user_home,
            user_badge,
            user_badge_label,
            user_reward_points,
            user_manage_contacts,
            user_manage_visitors,
            user_create_pages,
            user_delete_pages,
            user_manage_forms,
            user_manage_calendars,
            user_manage_emails,
            user_manage_ecommerce,
            user_view_card_data,
            manage_ecommerce_reports,
            manage_erp,
            manage_erp_cash,
            manage_erp_settings,
            $sql_erp_readonly_column
            $sql_ws_columns
            user_set_offline_payment,
            user_publish_calendar_events,
            $sql_set_page_type_columns
            selected_appmenu_items_array,
            user_contact,
            user_timestamp,
            user_user)
        VALUES (
            '" . escape($fields['username'] ?? '') . "',
            '" . escape($fields['email'] ?? '') . "',
            " . $sql_password['password'] . ",
            {$sql_password['algo_value']}
            '" . escape($fields['role'] ?? '') . "',
            '" . (int) ($fields['home_page'] ?? 0) . "',
            '" . escape($fields['badge'] ?? '') . "',
            '" . escape($fields['badge_label'] ?? '') . "',
            '" . (int) pg_user_form_reward_points($fields['reward_points'] ?? null) . "',
            '" . escape($fields['manage_contacts'] ?? '') . "',
            '" . escape($fields['manage_visitors'] ?? '') . "',
            '" . escape($fields['create_pages'] ?? '') . "',
            '" . escape($fields['delete_pages'] ?? '') . "',
            '" . escape($fields['manage_forms'] ?? '') . "',
            '" . escape($fields['manage_calendars'] ?? '') . "',
            '" . escape($fields['manage_emails'] ?? '') . "',
            '" . escape($fields['manage_ecommerce'] ?? '') . "',
            '" . escape($fields['view_card_data'] ?? '') . "',
            '" . e($fields['manage_ecommerce_reports'] ?? '') . "',
            '" . e($fields['manage_erp'] ?? '') . "',
            '" . e($fields['manage_erp_cash'] ?? '') . "',
            '" . e($fields['manage_erp_settings'] ?? '') . "',
            $sql_erp_readonly_value
            $sql_ws_values
            '" . escape($fields['set_offline_payment'] ?? '') . "',
            '" . escape($fields['publish_calendar_events'] ?? '') . "',
            $sql_set_page_type_values
            'default',
            " . pg_user_contact_sql($fields['contact_id'] ?? '') . ",
            UNIX_TIMESTAMP(),
            '" . (int) $editor_id . "')";
    mysqli_query(db::$con, $query) or output_error('Query failed.');
    $user_id = (int) mysqli_insert_id(db::$con);

    // Offers that ask for a new customer read the creation date; user_timestamp
    // is rewritten on every save and cannot answer that.
    pg_user_created_stamp($user_id);

    // A new account starts with the notification history already read. Read
    // state is per person, so without this the first sign-in opens onto every
    // announcement the site has ever made, none of it theirs to act on.
    include_once(PG_FUNCTIONS_DIR . '/includes/notifications.php');
    pg_notification_seed_user($user_id);

    // A new account's password is as old as the account. Written after the
    // INSERT rather than inside it so the column list stays valid on a schema
    // that predates the 2026.4.4 step.
    if (pg_user_has_password_changed_at()) {
        db("UPDATE user SET user_password_changed_at = UNIX_TIMESTAMP() WHERE user_id = '" . e($user_id) . "'");
    }

    pg_user_form_save_access($user_id, $fields['role'] ?? 3, $fields, false);

    pg_audit_record('create', 'user', $user_id);

    return array('id' => $user_id, 'password' => $random_password);
}

/**
 * Sends a new account its "New Account" mail: the single-use set password
 * link and, when the account reaches the panel or has a start page, where to
 * sign in. The mail never carries a password.
 *
 * @param int $user_id
 * @param array $fields  As the account was created
 * @return array  'sent' => bool, 'set_password_url' => the link, '' when none was issued
 */
function pg_user_new_account_mail($user_id, $fields)
{
    $login = '';

    // An administrator, designer or manager, a user with edit rights or a
    // panel right is sent to the panel; anyone else to their start page.
    if (
        (($fields['role'] ?? 3) < 3)
        || (no_acl_check($user_id) == true)
        || (($fields['manage_calendars'] ?? '') == 'yes')
        || (($fields['manage_forms'] ?? '') == 'yes')
        || (($fields['manage_visitors'] ?? '') == 'yes')
        || (($fields['manage_contacts'] ?? '') == 'yes')
        || (($fields['manage_emails'] ?? '') == 'yes')
        || (($fields['manage_ecommerce'] ?? '') == 'yes')
        || ($fields['manage_ecommerce_reports'] ?? '')
        || ($fields['manage_erp'] ?? '')
        || ($fields['manage_workspace'] ?? '')
        || (count(get_items_user_can_edit('ad_regions', $user_id)) > 0)
    ) {
        // The link leaves the request in an e-mail, so it is built from the
        // configured host name rather than the Host header of this request.
        $login =
            lang('Login') . ':' . "\n" .
            URL_SCHEME . HOSTNAME_SETTING . PATH . SOFTWARE_DIRECTORY . '/' . "\n";

    } elseif (!empty($fields['home_page'])) {
        $login =
            lang('Login') . ':' . "\n" .
            URL_SCHEME . HOSTNAME_SETTING . PATH . encode_url_path(get_page_name($fields['home_page'])) . "\n";
    }

    // The link works for whoever reads it, so the e-mail is 'secret': a
    // failed delivery keeps its body out of the activity log and the
    // delivery-failed notice.
    $set_password_url = pg_set_password_link_issue($user_id) ? pg_set_password_link($user_id) : '';

    $sent = (bool) email(array(
        'to' => $fields['email'] ?? '',
        'from_name' => ORGANIZATION_NAME,
        'from_email_address' => EMAIL_ADDRESS,
        'subject' => lang('New Account'),
        'body' => pg_new_user_email_body($fields['email'] ?? '', $set_password_url, $login),
        'secret' => true));

    return array('sent' => $sent, 'set_password_url' => $set_password_url);
}

/**
 * Saves an existing account as the edit screen does: username, e-mail,
 * start page, badge, reward points, the rights and page types, and the role
 * (unless it is the last administrator's, or no role was given). The rights
 * of a module that is off, and the password hint while hints are off, are
 * left as stored. The "Account enabled" switch is written only when
 * account_enabled_drawn is set and pg_user_form_may_disable() allows it;
 * an account switched off loses its remembered devices. The access records
 * are replaced unless $save_access is false (the API, whose fields carry no
 * folder or item boxes). The caller has checked that the editor reaches the account,
 * the role (pg_user_role_writable()) and the username and e-mail address
 * (pg_user_write_problem()).
 *
 * @param int $user_id
 * @param array $fields
 * @param array $editor  'id' and 'role' of the account that saves
 * @param bool $save_access  Replace the folder and item access records
 * @return int  The role the account is saved with
 */
function pg_user_update($user_id, $fields, $editor, $save_access = true)
{
    $user_id = (int) $user_id;

    $result = db_q("SELECT user_role FROM user WHERE user_id = ?", array((string) $user_id)) or output_error('Query failed.');
    $role = (mysqli_fetch_assoc($result) ?: array('user_role' => null))['user_role'];

    // The last administrator keeps the role. The screen's role cards are
    // disabled then and post nothing; an empty role must not reach the
    // column, where a lenient server would store it as 0.
    $allow_role_change = !(($editor['role'] == 0) && ($role == 0) && pg_user_is_last_admin($user_id));

    if ($allow_role_change && isset($fields['role'])) {
        $sql_role = " user_role = '" . escape($fields['role']) . "',";
        $saved_role = (int) $fields['role'];
    } else {
        $sql_role = "";
        $saved_role = (int) $role;
    }

    // The optional modules' rights and the password hint, written only when
    // their rows are drawn (the same checks as the screen): a row that is not
    // drawn posts nothing, and writing its column would take the right away
    // while the module or hints are switched off.
    $sql_module_rights = '';

    $module_columns = pg_user_form_module_columns(array(
        'password_hint'   => (PASSWORD_HINT == true),
        'forms'           => (FORMS == true),
        'calendars'       => (CALENDARS == true),
        'ecommerce'       => (ECOMMERCE == true),
        'offline_payment' => (ECOMMERCE_OFFLINE_PAYMENT == true),
        'erp'             => (defined('ERP_ENABLED') && ERP_ENABLED),
    ));

    foreach ($module_columns as $column => $field) {
        $sql_module_rights .= " " . $column . " = '" . escape($fields[$field] ?? '') . "',";
    }

    $sql_set_page_types = '';

    foreach (pg_user_write_site_page_types() as $type) {
        $sql_set_page_types .= " user_set_page_type_" . $type . " = '" . escape($fields['set_page_type_' . $type] ?? '') . "',";
    }

    // The workspace rights, when their row is drawn.
    $sql_ws_rights = (pg_user_permission_ws_available() ? pg_user_permission_ws_sql('set', $fields) : '');

    // The ERP's read-only right, when the ERP row is drawn and the column
    // exists.
    $sql_erp_readonly = ((defined('ERP_ENABLED') && ERP_ENABLED) ? pg_user_erp_readonly_sql('set', $fields) : '');

    // Switched off, the account is stamped once (one already disabled keeps
    // its date); switched on, the stamp is cleared.
    $sql_disabled = '';
    $disabling = false;

    if (pg_user_has_disabled_at() && isset($fields['account_enabled_drawn'])
        && pg_user_form_may_disable($user_id, $editor['id'])) {
        if (($fields['account_enabled'] ?? '') === '1') {
            $sql_disabled = " user_disabled_at = 0,";
        } else {
            $sql_disabled = " user_disabled_at = IF(user_disabled_at = 0, UNIX_TIMESTAMP(), user_disabled_at),";
            $disabling = !pg_user_id_is_disabled($user_id);
        }
    }

    // The only write to the user row in this save; the rest go to the
    // access lists.
    $audit_before = pg_audit_fetch('user', $user_id);

    $query =
        "UPDATE user
        SET
            user_username = '" . escape($fields['username'] ?? '') . "',
            user_email = '" . escape($fields['email'] ?? '') . "',
            $sql_role
            $sql_disabled
            user_home = '" . (int) ($fields['home_page'] ?? 0) . "',
            user_badge = '" . escape($fields['badge'] ?? '') . "',
            user_badge_label = '" . escape($fields['badge_label'] ?? '') . "',
            " . pg_credit_points_staff_sql($fields['reward_points'] ?? null, $fields['reward_points_original'] ?? null) . "
            user_manage_contacts = '" . escape($fields['manage_contacts'] ?? '') . "',
            user_manage_visitors = '" . escape($fields['manage_visitors'] ?? '') . "',
            user_create_pages = '" . escape($fields['create_pages'] ?? '') . "',
            user_delete_pages = '" . escape($fields['delete_pages'] ?? '') . "',
            user_manage_emails = '" . escape($fields['manage_emails'] ?? '') . "',
            $sql_module_rights
            $sql_erp_readonly
            $sql_ws_rights
            $sql_set_page_types
            user_user = '" . (int) $editor['id'] . "',
            user_timestamp = UNIX_TIMESTAMP()
        WHERE user_id = '" . $user_id . "'";
    mysqli_query(db::$con, $query) or output_error('Query failed.');

    pg_audit_record('update', 'user', $user_id, $audit_before);

    // A disabled account keeps no remembered device: its tokens are retired
    // with the switch, as a password reset retires them.
    if ($disabling) {
        pg_auth_token_revoke_user($user_id);
    }

    if ($save_access) {
        pg_user_form_save_access($user_id, $saved_role, $fields, true);
    }

    return $saved_role;
}

/**
 * Deletes an account and everything that is only its own: sign-in tokens,
 * second factor and passkeys, linked identities, connected accounts,
 * favourites, back-in-stock requests, access records, address book, messages
 * and saved replies. The audit line keeps the row as it was. The caller has checked that the editor
 * reaches the account and that it is not the last administrator.
 *
 * @param int $user_id
 */
function pg_user_delete($user_id)
{
    $user_id = (int) $user_id;
    $id = (string) $user_id;

    // The account as it was, before anything that goes with it is removed.
    $audit_before = pg_audit_fetch('user', $user_id);

    // Sign-in tokens first: a token outliving its user is a row that still
    // names a user_id nobody owns any more, and the browser holding it keeps
    // presenting it on every request.
    pg_auth_token_revoke_user($user_id);

    // The second factor, its recovery codes and the account's passkeys go
    // with the account: MySQL 5.7 hands the highest id out again after a
    // restart, and a new account would inherit them.
    pg_mfa_reset($user_id);

    // The Microsoft and Apple identities go with the account.
    pg_signin_identities_forget($user_id);

    // Its own connected accounts (file picker, calendar, notes, MCP) are
    // ended and revoked: a later account given the same id must not use them.
    if (function_exists('pg_connect_forget_user')) {
        pg_connect_forget_user($user_id);
    }

    // So do its favourites and back-in-stock requests.
    pg_wishlist_forget_user($user_id);
    pg_stock_notify_forget_user($user_id);

    db_q("DELETE FROM user WHERE user_id = ?", array($id)) or output_error('Query failed.');

    pg_audit_record('delete', 'user', $user_id, $audit_before);

    db_q("DELETE FROM aclfolder WHERE aclfolder_user = ?", array($id)) or output_error('Query failed.');
    db_q("DELETE FROM users_contact_groups_xref WHERE user_id = ?", array($id)) or output_error('Query failed.');
    db_q("DELETE FROM users_calendars_xref WHERE user_id = ?", array($id)) or output_error('Query failed.');
    db_q("DELETE FROM address_book WHERE user = ?", array($id)) or output_error('Query failed.');
    db_q("DELETE FROM users_ad_regions_xref WHERE user_id = ?", array($id)) or output_error('Query failed.');
    db_q("DELETE FROM users_common_regions_xref WHERE user_id = ?", array($id)) or output_error('Query failed.');
    db_q("DELETE FROM users_menus_xref WHERE user_id = ?", array($id)) or output_error('Query failed.');

    db("DELETE FROM users_messages_xref WHERE user_id = '" . $user_id . "'");

    // A person's saved replies are theirs alone.
    if (pg_schema_has('ws_saved_replies')) {
        db("DELETE FROM ws_saved_replies WHERE user_id = '" . $user_id . "'");
    }

    if (pg_schema_has('users_shared_components_xref')) {
        db("DELETE FROM users_shared_components_xref WHERE user_id = '" . $user_id . "'");
    }
}
