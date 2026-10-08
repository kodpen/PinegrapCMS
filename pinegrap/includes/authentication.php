<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Shared authentication primitives.
 *
 * The functions that decide who a request belongs to and, further down, which
 * folders that visitor may view or edit. They live here, outside
 * functions.php, because get_file.php serves protected files without ever
 * loading functions.php (router.php dispatches it that way on purpose - a
 * file request should not parse megabytes of PHP), yet it has to make the
 * same sign-in and folder access decisions as every other screen. One copy,
 * two includers:
 *
 *   functions.php   require_once at the top - everything ordinary
 *   get_file.php    require_once at the top - reached through router.php
 *
 * CONTRACT - this file defines no helpers of its own and may only call:
 *
 *   db(), db_value(), db_item(),   provided by whichever file included us;
 *   db_items(), escape()           both includers define all five
 *                                  (includes/fn/core.php and the local
 *                                  copies at the bottom of get_file.php)
 *   hash(), hash_equals(), ...     PHP itself
 *
 * Anything beyond that list would quietly reintroduce the dependency this
 * file exists to avoid, so an addition here must keep to it (see the
 * function_exists() guard in pg_auth_token_revoke() for the pattern).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

/**
 * Does the user table carry the ERP permission columns yet?
 *
 * software_update.php replaces the files and runs the upgrade afterwards, and a
 * site whose files were copied by hand may not have run it at all. In that gap
 * this version's code reads the previous version's tables, so the login path
 * cannot name a column that the upgrade has not added: the panel answered
 * "Unknown column user.manage_erp" and locked the operator out of the very
 * screen that runs the upgrade.
 *
 * Asked once per request and cached, the same way the password-algo probe is.
 *
 * @return bool
 */
function pg_user_has_erp_columns()
{
    static $has = null;

    if ($has === null) {
        $result = @mysqli_query(db::$con, "SHOW COLUMNS FROM user LIKE 'manage_erp'");
        $has = ($result && (mysqli_num_rows($result) > 0));
    }

    return $has;
}

/**
 * Does the user table carry the workspace permission columns yet?
 *
 * The same gap pg_user_has_erp_columns() covers: new files can be served by
 * the previous version's table until the upgrade runs, and a login query that
 * names a missing column takes the panel down with it. The four columns arrive
 * in one step, so the last of them answers for all four. An exact name match,
 * not LIKE: the underscores in the name are wildcards there.
 *
 * @return bool
 */
function pg_user_has_ws_columns()
{
    static $has = null;

    if ($has === null) {
        $result = @mysqli_query(db::$con, "SHOW COLUMNS FROM user WHERE Field = 'manage_workspace_settings'");
        $has = ($result && (mysqli_num_rows($result) > 0));
    }

    return $has;
}

/**
 * Does the user table carry the ERP's read-only right yet (2026.4.4, 4.78)?
 * Asked for the same reason as pg_user_has_erp_columns(), by exact name.
 *
 * @return bool
 */
function pg_user_has_erp_readonly_column()
{
    static $has = null;

    if ($has === null) {
        $result = @mysqli_query(db::$con, "SHOW COLUMNS FROM user WHERE Field = 'manage_erp_readonly'");
        $has = ($result && (mysqli_num_rows($result) > 0));
    }

    return $has;
}

/**
 * The read-only right as a piece of the user screens' SQL, or '' before the
 * upgrade: 'columns' and 'values' for an INSERT, 'set' for an UPDATE. Each
 * piece ends in a comma, like the lines around it.
 *
 * @param string $mode
 * @return string
 */
function pg_user_erp_readonly_sql($mode)
{
    if (!pg_user_has_erp_readonly_column()) {
        return '';
    }

    $value = (!empty($_POST['manage_erp_readonly']) ? 1 : 0);

    if ($mode === 'columns') {
        return 'manage_erp_readonly,';
    }

    if ($mode === 'values') {
        return "'" . $value . "',";
    }

    return "manage_erp_readonly = '" . $value . "',";
}

// Verify a cookie value and return the user id, or false. An expired or
// mismatched token is not trusted; an expired row is also deleted in passing.
function pg_auth_token_verify($cookie_value)
{
    if (!is_string($cookie_value) || (strpos($cookie_value, ':') === false)) {
        return false;
    }

    list($selector, $validator) = explode(':', $cookie_value, 2);

    // Must be hex of the exact lengths, or it was never one of ours - and this
    // keeps anything odd out of the lookup.
    if (!preg_match('/^[a-f0-9]{24}$/', $selector) || !preg_match('/^[a-f0-9]{64}$/', $validator)) {
        return false;
    }

    $row = db_item("SELECT validator_hash, user_id, expires_at
                    FROM auth_tokens WHERE selector = '" . escape($selector) . "'");

    if (!is_array($row) || !isset($row['validator_hash'])) {
        return false;
    }

    if ((int) $row['expires_at'] < time()) {
        pg_auth_token_revoke($selector);
        return false;
    }

    // Constant-time compare of the stored hash against this validator's hash.
    if (!hash_equals((string) $row['validator_hash'], hash('sha256', $validator))) {
        return false;
    }

    db("UPDATE auth_tokens SET last_used_at = '" . time() . "'
        WHERE selector = '" . escape($selector) . "'");

    return (int) $row['user_id'];
}

// Revoke one token (by selector) or every token a user holds. The second is
// what a password change and a "sign out everywhere" both call.
function pg_auth_token_revoke($selector, $respect_pin = false)
{
    // $respect_pin is for the actions a MEMBER can trigger and for the device
    // limit's own housekeeping; an operator's revoke, a sign-out on the device
    // itself and account deletion pass it by and remove the row regardless.
    $sql = "DELETE FROM auth_tokens WHERE selector = '" . escape($selector) . "'";

    // pg_auth_tokens_have_pin() stays in functions.php (it talks to db::$con
    // directly). Every caller that passes $respect_pin = true lives behind
    // functions.php, so the guard changes nothing there - it only keeps a
    // future misuse from get_file.php's side from being a fatal error.
    if ($respect_pin && function_exists('pg_auth_tokens_have_pin') && pg_auth_tokens_have_pin()) {
        $sql .= " AND pinned = 0";
    }

    db($sql);

    // Device notifications belong to the browser this token identifies, so they
    // end when it does: signing out, an operator ending the session from the
    // list, the device limit retiring the oldest one. Written here rather than
    // in each caller because this is the one place all of them pass through.
    //
    // The subscription is not the session - it survives a closed browser on
    // purpose - so nothing else would ever take it away, and the browser would
    // go on being woken about a site nobody on it is signed in to.
    if (pg_auth_push_bound()) {
        db("DELETE FROM push_subscriptions WHERE auth_selector = '" . escape($selector) . "'");
    }
}

// Whether subscriptions record which browser they belong to yet. Files land
// before the schema does, so the column is asked for rather than assumed;
// information_schema rather than SHOW COLUMNS LIKE, because '_' is a wildcard
// in LIKE and every name here is full of them.
function pg_auth_push_bound()
{
    static $bound = null;

    if ($bound === null) {

        $count = db_value("SELECT COUNT(*) FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = 'push_subscriptions'
            AND COLUMN_NAME = 'auth_selector'");

        $bound = ((int) $count > 0);
    }

    return $bound;
}

// Load one user row with the contacts join and the exact aliases the
// initialize_user() of both includers work with, keyed by user id. The shape
// lives in one place so the two sides can never drift apart.
function pg_load_user_row($user_id)
{
    // The ERP columns are named only when they exist. Between new files landing
    // and the upgrade running, this query is served by the previous version's
    // table, and naming a column that is not there takes the whole panel down -
    // including the screen that runs the upgrade.
    $erp_columns = pg_user_has_erp_columns()
        ? "user.manage_erp, user.manage_erp_cash, user.manage_erp_settings,"
        : "0 AS manage_erp, 0 AS manage_erp_cash, 0 AS manage_erp_settings,";

    // The ERP's read-only right (4.78), under the same rule.
    $erp_columns .= pg_user_has_erp_readonly_column() ? " user.manage_erp_readonly," : " 0 AS manage_erp_readonly,";

    // The workspace rights, under the same rule.
    $ws_columns = pg_user_has_ws_columns()
        ? "user.manage_workspace, user.manage_workspace_assign, user.manage_workspace_board, user.manage_workspace_settings,"
        : "0 AS manage_workspace, 0 AS manage_workspace_assign, 0 AS manage_workspace_board, 0 AS manage_workspace_settings,";

    return db_item("SELECT

            " . $erp_columns . "

            " . $ws_columns . "

            user.user_id AS id,

            user.user_username AS username,

            user.user_email AS email_address,

            user.user_role AS role,

            user.user_home AS start_page_id,

            user.user_manage_ecommerce AS manage_ecommerce,

            user.manage_ecommerce_reports,

            user.user_manage_forms AS manage_forms,

            user.timezone,

            contacts.id AS contact_id,

            contacts.member_id AS member_id,

            contacts.expiration_date AS expiration_date

        FROM user

        LEFT JOIN contacts ON user.user_contact = contacts.id

        WHERE user.user_id = '" . (int) $user_id . "'");
}

// Promote the current session to a signed-in one. Every sign-in path ends
// here - the password forms, remember-me, Google, registration, activation,
// auto-registration, the device-limit confirmation - so the identity is
// written the same way everywhere and each of them gets a fresh session id.
//
// The fresh id is what defeats session fixation: an id the browser held
// before the visitor authenticated (planted through a link or a script on a
// shared machine) refers to nothing once the sign-in completes, because the
// old session is deleted. $_SESSION itself carries over unchanged, so the
// CSRF token, the cart and order keys and any pending redirect survive.
//
// Regeneration needs a running session and unsent headers (the new id
// travels in a Set-Cookie header); when either is missing the identity is
// still written and the id is left as it was - PHP would only warn and keep
// the old id anyway.
function pg_session_sign_in($user_id, $username)
{
    if ((session_status() === PHP_SESSION_ACTIVE) && !headers_sent()) {
        session_regenerate_id(true);
    }

    $_SESSION['sessionuserid']  = $user_id;
    $_SESSION['sessionusername'] = $username;
}

// ---------------------------------------------------------------------------
// Folder access - shared with get_file.php
// ---------------------------------------------------------------------------
//
// get_file.php decides whether a file may be served with the same rules the
// pages use, so these live here rather than in includes/fn/auth.php. They read
// the USER_* constants that initialize_user() defines on either side.

// Determine what access control type a folder has.
function get_access_control_type($folder_id)
{
    // Per-request memoization. view_files.php walks every file row twice
    // (count loop + render loop) and recurses up the parent chain on each
    // call, so without caching this issues thousands of identical lookups.
    static $resolved = array();
    static $rows = null;

    if (isset($resolved[$folder_id])) {
        return $resolved[$folder_id];
    }

    // Lazy-load the entire folder tree once. For typical sites this is a few
    // KB of memory and replaces N recursive single-row queries with one scan.
    if ($rows === null) {
        $rows = array();
        foreach (db_items("SELECT folder_id, folder_parent, folder_access_control_type FROM folder") as $r) {
            $rows[$r['folder_id']] = $r;
        }
    }

    // Walk the parent chain in-memory until we hit an explicit access control
    // type or the root folder.
    $current = $folder_id;
    $visited = array(); // guard against malformed loops in folder_parent
    while (isset($rows[$current]) && !isset($visited[$current])) {
        $visited[$current] = true;
        $r = $rows[$current];
        if (!empty($r['folder_access_control_type'])) {
            $resolved[$folder_id] = $r['folder_access_control_type'];
            return $resolved[$folder_id];
        }
        if ($r['folder_parent'] == 0) {
            $resolved[$folder_id] = 'public';
            return 'public';
        }
        $current = $r['folder_parent'];
    }

    // Folder row missing or loop detected — fall back to public to match
    // historical behaviour.
    $resolved[$folder_id] = 'public';
    return 'public';
}

// Create function that will check if a visitor has view access to a folder.
// This does not just check private access.  It checks for all types of access control.
// Since any visitor can get access to registration or guest content by registering or choosing to be a guest,
// you can set $always_grant_access_for_registration_and_guest to true which will grant access
// regardless of whether the visitor is logged in or not.
function check_view_access($folder_id, $always_grant_access_for_registration_and_guest = false)
{
    // If the user is logged in and the user is an administrator, designer, or manager,
    // then they have view access to all folders, so just grant access.
    if (USER_LOGGED_IN && (USER_ROLE < 3)) {
        return true;
    }
    // Assume that visitor does not have access until we find out otherwise.
    $access = false;
    // Check if visitor has access differently based on the access control type of the folder.
    switch (get_access_control_type($folder_id)) {
        case 'public':
            $access = true;
            break;
        case 'private':
            $access_check = check_private_access($folder_id);
            // If the visitor has private access to this folder, then visitor has access.
            if ($access_check['access'] == true) {
                $access = true;
            }
            break;
        case 'guest':
            // If the visitor should always be granted access for guest access control,
            // or if the visitor has logged in, or if the visitor has selected to be a guest,
            // then the visitor has access.
            if (($always_grant_access_for_registration_and_guest == true) || (USER_LOGGED_IN == true) || ($_SESSION['software']['guest'] == true)) {
                $access = true;
            }
            break;
        case 'registration':
            // If the visitor should always be granted access for registration access control,
            // or if the visitor has logged in, then the visitor has access.
            if (($always_grant_access_for_registration_and_guest == true) || (USER_LOGGED_IN == true)) {
                $access = true;
            }
            break;
        case 'membership':
            // If the visitor is logged in and is a member
            // or has edit access, then the visitor has access.
            if ((USER_LOGGED_IN == true) && ((USER_MEMBER == true) || (check_edit_access($folder_id) == true))) {
                $access = true;
            }
            break;
    }
    return $access;
}

// Whether one person may edit what a folder holds.
//
// The walk is the one check_edit_access() has always done: a manager or above
// passes everywhere, anybody else needs rights of 2 on the folder itself or on
// one of its parents. It takes the person as arguments instead of reading the
// session because the panel is no longer the only caller - a device
// notification is prepared for people who are not the one making the request,
// and the answer has to be theirs rather than the sender's.
function pg_folder_edit_access($folder_id, $user_id, $user_role)
{
    if ($user_role < 3) {
        return true;
    }

    // Determine what type of access user has to folder.
    $row = db_item("SELECT

            aclfolder.aclfolder_rights AS rights,

            folder.folder_parent AS parent_folder_id

        FROM aclfolder

        LEFT JOIN folder ON aclfolder.aclfolder_folder = folder.folder_id

        WHERE

            (aclfolder.aclfolder_user = '" . (int) $user_id . "')

            AND (aclfolder.aclfolder_folder = '" . escape($folder_id) . "')");

    $rights = isset($row['rights']) ? $row['rights'] : '';
    $parent_folder_id = isset($row['parent_folder_id']) ? $row['parent_folder_id'] : '';

    // If this user has edit rights to this folder, then remember that.
    if ($rights == 2) {
        return true;
    }

    // If the parent folder has not been found yet, then get it.
    if ($parent_folder_id == '') {
        $parent_folder_id = db_value("SELECT folder_parent AS parent_folder_id FROM folder WHERE folder_id = '" . escape($folder_id) . "'");
    }

    // If this is not the root folder, then use recursion to check parent folder for access.
    if ($parent_folder_id != 0) {
        return pg_folder_edit_access($parent_folder_id, $user_id, $user_role);
    }

    return false;
}

// Create function in order to check if a visitor has edit access to a folder.
// Answers for whoever is signed in; pg_folder_edit_access() is the same test
// asked about somebody else.
function check_edit_access($folder_id)
{
    if (USER_LOGGED_IN != true) {
        return false;
    }

    return pg_folder_edit_access($folder_id, USER_ID, USER_ROLE);
}

// Create function in order to check if a visitor has access to a private folder.
// This function returns an array with two properties: "access" (set to true
// if the user has access and false if user does not have access) and "expired"
// (set to true if the access has expired and false otherwise).
// Visitors with edit rights to a folder also have private access to that folder.
function check_private_access($folder_id)
{
    $result = array();
    // Assume that the visitor does not have access and access has not expired until we find out otherwise.
    $result['access'] = false;
    $result['expired'] = false;
    // If the visitor is logged in, then continue to check if the visitor has private access.
    if (USER_LOGGED_IN == true) {
        // If the user is a manager or above, then the user has private access.
        if (USER_ROLE < 3) {
            $result['access'] = true;
            // Otherwise the user has a user role, so continue to check if user has private access.
        } else {
            // Determine what type of access user has to folder.
            $row = db_item("SELECT

                    aclfolder.aclfolder_rights AS rights,

                    aclfolder.expiration_date,

                    folder.folder_parent AS parent_folder_id

                FROM aclfolder

                LEFT JOIN folder ON aclfolder.aclfolder_folder = folder.folder_id

                WHERE

                    (aclfolder.aclfolder_user = '" . USER_ID . "')

                    AND (aclfolder.aclfolder_folder = '" . escape($folder_id) . "')");
            // No ACL row for this user and folder is the common case for a
            // plain member browsing a private folder; the parent walk below
            // then decides.
            $rights = isset($row['rights']) ? $row['rights'] : '';
            $expiration_date = isset($row['expiration_date']) ? $row['expiration_date'] : '';
            $parent_folder_id = isset($row['parent_folder_id']) ? $row['parent_folder_id'] : '';
            // If this user has edit rights to this folder, then they also have private access.
            if ($rights == 2) {
                $result['access'] = true;
                // Otherwise if this user has private access, then determine if access has expired.
            } else if ($rights == 1) {
                // date() below must use the site's timezone. init.php sets it
                // up front; get_file.php never runs init.php and sets it
                // lazily with its own initialize_timezone(), which exists
                // only there.
                if (function_exists('initialize_timezone')) {
                    initialize_timezone();
                }
                // If there is an expiration date and it has expired, then remember that.
                if (($expiration_date != '0000-00-00') && ($expiration_date < date('Y-m-d'))) {
                    $result['expired'] = true;
                    // Otherwise the private access has not expired, so user has access.
                } else {
                    $result['access'] = true;
                }
                // Otherwise we do not know if access has been granted, so if this is not the root folder
                // then use recursion to check for access in parent folder.
            } else {
                // If the parent folder has not been found yet, then get it.
                if ($parent_folder_id == '') {
                    $parent_folder_id = db_value("SELECT folder_parent AS parent_folder_id FROM folder WHERE folder_id = '" . escape($folder_id) . "'");
                }
                // If this is not the root folder, then use recursion to check parent folder for access.
                if ($parent_folder_id != 0) {
                    $result = check_private_access($parent_folder_id);
                }
            }
        }
    }
    return $result;
}
