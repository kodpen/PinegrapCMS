<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Shared authentication primitives.
 *
 * The functions that decide who a request belongs to. They live here, outside
 * functions.php, because get_file.php serves protected files without ever
 * loading functions.php (router.php dispatches it that way on purpose - a
 * file request should not parse megabytes of PHP), yet it has to make the
 * same sign-in decision as every other screen. One copy, two includers:
 *
 *   functions.php   require_once at the top - everything ordinary
 *   get_file.php    require_once at the top - reached through router.php
 *
 * CONTRACT - this file defines no helpers of its own and may only call:
 *
 *   db(), db_item(), escape()      provided by whichever file included us;
 *                                  both includers define all three
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

    return db_item("SELECT

            " . $erp_columns . "

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
