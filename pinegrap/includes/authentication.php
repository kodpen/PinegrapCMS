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
 * Whether a table has a column, for the sign-in path's probes below.
 *
 * pg_schema_has() answers from its cache file, so a signed-in request no
 * longer asks the database. get_file.php loads this file without
 * functions.php, so pg_schema_has() is not there; that request asks
 * information_schema by exact name instead, once per probe as before.
 *
 * @param string $table
 * @param string $column
 * @return bool
 */
function pg_auth_column_exists($table, $column)
{
    if (function_exists('pg_schema_has')) {
        return pg_schema_has($table, $column);
    }

    if (!isset(db::$con) || !db::$con) {
        return false;
    }

    $result = @mysqli_query(db::$con, "SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = '" . mysqli_real_escape_string(db::$con, (string) $table) . "'
        AND COLUMN_NAME = '" . mysqli_real_escape_string(db::$con, (string) $column) . "'
        LIMIT 1");

    return ($result && (mysqli_num_rows($result) > 0));
}

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
        $has = pg_auth_column_exists('user', 'manage_erp');
    }

    return $has;
}

/**
 * Does the user table carry the workspace permission columns yet?
 *
 * The same gap pg_user_has_erp_columns() covers: new files can be served by
 * the previous version's table until the upgrade runs, and a login query that
 * names a missing column takes the panel down with it. The four columns arrive
 * in one step, so the last of them answers for all four.
 *
 * @return bool
 */
function pg_user_has_ws_columns()
{
    static $has = null;

    if ($has === null) {
        $has = pg_auth_column_exists('user', 'manage_workspace_settings');
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
        $has = pg_auth_column_exists('user', 'manage_erp_readonly');
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

    // The address follows the activity, as in the session's own stamp
    // (initialize_user(), includes/fn/auth.php).
    db("UPDATE auth_tokens SET last_used_at = '" . time() . "', ip_address = '" . escape(pg_auth_request_address()) . "'
        WHERE selector = '" . escape($selector) . "'");

    return (int) $row['user_id'];
}

/**
 * The visitor's address as the firewall resolves it, for an auth_tokens row:
 * the proxy-aware answer when waf.php is loaded (every request through
 * router.php or init.php), the peer's address otherwise.
 *
 * @return string
 */
function pg_auth_request_address()
{
    if (function_exists('waf_client_ip')) {
        return (string) waf_client_ip();
    }

    return isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : '';
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
// before the schema does, so the column is asked for rather than assumed, by
// exact name: '_' is a wildcard in SHOW COLUMNS LIKE and every name here is
// full of them.
function pg_auth_push_bound()
{
    static $bound = null;

    if ($bound === null) {
        $bound = pg_auth_column_exists('push_subscriptions', 'auth_selector');
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
// old session is deleted. $_SESSION itself carries over, so the cart and
// order keys and any pending redirect survive; only the form token is
// renewed, below.
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

    // A binding made for another account (login_as_user.php signs an
    // administrator's session in as someone else) must not follow the new
    // identity; one made for this account a moment ago (the sign-in screens
    // mint the token before they call this) is kept.
    if (isset($_SESSION['software']['auth_user_id'])
        && ((int) $_SESSION['software']['auth_user_id'] !== (int) $user_id)) {
        pg_session_unbind_token();
    }

    $_SESSION['sessionuserid']  = $user_id;
    $_SESSION['sessionusername'] = $username;

    // The form token is renewed with the identity: a token that was handed
    // out while the visitor was anonymous must not keep authorising the
    // signed-in account's forms. initialize_token() lives behind
    // functions.php; get_file.php signs in without it and gets a token on
    // its next page request.
    if (function_exists('initialize_token')) {
        unset($_SESSION['software']['token']);
        initialize_token();
    }
}

// Bind the session to the auth_tokens row that identifies this device.
//
// The token cookie alone does not make a revoke authoritative: a client that
// stops sending the revoked cookie keeps its session, and is even minted a
// fresh token on its next request. So the selector is also kept in the
// session, and initialize_user() asks pg_session_token_binding_broken() on
// every request: the row must still exist, be unexpired and belong to the
// session's user, and a cookie that is sent must name the same selector.
// A password change, "sign out everywhere", an administrator ending the
// session and the device limit all delete the row, and the session ends with
// it. Every minting path passes through pg_login_set_device_cookie(), which
// calls this; the remember-me paths bind the selector they verified.
function pg_session_bind_token($selector, $user_id)
{
    $selector = (string) $selector;

    if (!preg_match('/^[a-f0-9]{24}$/', $selector)) {
        return;
    }

    $_SESSION['software']['auth_selector'] = $selector;
    $_SESSION['software']['auth_user_id']  = (int) $user_id;
}

function pg_session_unbind_token()
{
    unset($_SESSION['software']['auth_selector'], $_SESSION['software']['auth_user_id']);
}

// '' while a bound session is still backed by its token (or carries no
// binding); otherwise the reason it is not, for the activity log.
function pg_session_token_binding_broken()
{
    if (empty($_SESSION['sessionuserid']) || empty($_SESSION['software']['auth_selector'])) {
        return '';
    }

    $selector = (string) $_SESSION['software']['auth_selector'];

    if (isset($_COOKIE['software']['auth']) && is_string($_COOKIE['software']['auth'])) {
        $cookie_selector = (string) strtok($_COOKIE['software']['auth'], ':');

        if ($cookie_selector !== $selector) {
            return 'session token does not match the device';
        }
    }

    // Asked directly rather than through db_item(): a failed query is the
    // upgrade bridge (no auth_tokens table yet, or a database restored from
    // before it), where there is nothing to verify against and the session is
    // left alone the way the previous release treated it, not a fatal error.
    // Not db_q(): get_file.php loads this file without functions.php, so only
    // the helpers named at the top of this file exist on both paths.
    $result = @mysqli_query(db::$con, "SELECT user_id, expires_at FROM auth_tokens WHERE selector = '" . escape($selector) . "'");

    if (!$result) {
        return '';
    }

    $row = mysqli_fetch_assoc($result);

    if (!is_array($row) || !isset($row['expires_at'])) {
        return 'session token was revoked';
    }

    if ((int) $row['expires_at'] < time()) {
        return 'session token expired';
    }

    if ((int) $row['user_id'] !== (int) $_SESSION['sessionuserid']) {
        return 'session token belongs to another account';
    }

    return '';
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
