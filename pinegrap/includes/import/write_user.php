<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The user writer of the import framework (includes/fn/importers.php): an
 * author of an export becomes a disabled panel user with no password, so it
 * cannot sign in until someone enables it by hand on the user screen (it then
 * sets a password through "forgot password" or links a provider).
 *
 * - An address an existing user holds makes no second account: the item is
 *   skipped and named, and the existing row is left as it is.
 * - Undo removes the account only while it is still disabled and has no
 *   password, passkey or provider: one enabled or given a credential has
 *   been taken over and stays, named in the undo's answer.
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
 * The account an author's address already belongs to, 0 when none.
 *
 * @param string $email
 * @return int
 */
function pg_import_user_by_email($email)
{
    $email = trim((string) $email);

    if ($email === '') {
        return 0;
    }

    return (int) db_value("SELECT user_id FROM user WHERE user_email = '" . e($email) . "' ORDER BY user_id LIMIT 1");
}

/**
 * Why the writer skips an author for its address, '' when the address is
 * free: none or not valid, or a user holds it. The dry run asks it too.
 *
 * @param array $item
 * @return string
 */
function pg_import_precheck_user($item)
{
    $email = trim((string) (((array) ($item['data'] ?? array()))['email'] ?? ''));

    if (!validate_email_address($email)) {
        return lang('The author has no valid e-mail address.');
    }

    if (pg_import_user_by_email($email) > 0) {
        return lang('A user with this e-mail address exists already.');
    }

    return '';
}

/**
 * The username an author asks for: the old login when it is plain letters,
 * digits, dots, dashes and underscores, else the part of the address before
 * the @ when that is, else "author". Pure; get_unique_username() makes it unique.
 *
 * @param array $author login, name, email
 * @return string
 */
function pg_import_user_username_base($author)
{
    foreach (array((string) ($author['login'] ?? ''), (string) strstr((string) ($author['email'] ?? ''), '@', true)) as $base) {
        if (preg_match('/^[A-Za-z0-9._-]+$/', $base) === 1) {
            return substr($base, 0, 90);
        }
    }

    return 'author';
}

/**
 * @param array $item
 * @param array $import
 * @return array{result:string,id:int,reason:string}
 */
function pg_import_write_user($item, $import)
{
    $author = (array) ($item['data'] ?? array());
    $email = trim((string) ($author['email'] ?? ''));

    if (($import['mapping']['authors'] ?? '') !== 'users') {
        return array('result' => 'skipped', 'id' => 0, 'reason' => lang('Authors are kept as names.'));
    }

    if (!pg_user_has_disabled_at()) {
        return array('result' => 'failed', 'id' => 0, 'reason' => lang('The database is not up to date.'));
    }

    $refusal = pg_import_precheck_user($item);

    if ($refusal !== '') {
        return array('result' => 'skipped', 'id' => 0, 'reason' => $refusal);
    }

    $user_id = create_member_user(array(
        'email'       => $email,
        'username'    => get_unique_username(pg_import_user_username_base($author)),
        'role'        => '3',
        'disabled_at' => time(),
    ));

    if ($user_id <= 0) {
        return array('result' => 'failed', 'id' => 0, 'reason' => lang('The user could not be saved.'));
    }

    return array('result' => 'made', 'id' => $user_id, 'reason' => '');
}

/**
 * Has someone taken this account over? Enabled, or given a password, a
 * passkey or a provider since the import. Pure.
 *
 * @param array $row         user row: user_disabled_at, user_password
 * @param int   $credentials passkeys and provider links counted
 * @return bool
 */
function pg_import_user_claimed($row, $credentials)
{
    return !pg_user_is_disabled($row)
        || (trim((string) ($row['user_password'] ?? '')) !== '')
        || ($credentials > 0);
}

/**
 * Removes an imported author that nobody took over. Answers true when the
 * account is gone, or array('kept' => name) when it stays.
 *
 * @param int $user_id
 * @return true|array{kept:string}
 */
function pg_import_unwrite_user($user_id)
{
    $user_id = (int) $user_id;
    $row = db_item("SELECT * FROM user WHERE user_id = '" . $user_id . "'");

    if (!$row) {
        return true;
    }

    $credentials = 0;

    foreach (array('user_passkeys', 'user_identities') as $table) {
        if (pg_schema_has($table)) {
            $credentials += (int) db_value("SELECT COUNT(*) FROM " . $table . " WHERE user_id = '" . $user_id . "'");
        }
    }

    if (trim((string) ($row['user_google_id'] ?? '')) !== '') {
        $credentials++;
    }

    if (pg_import_user_claimed($row, $credentials)) {
        return array('kept' => (string) $row['user_username']);
    }

    // What a never-used account can carry: rights given on the user screen
    // while it waited. The rest of edit_user.php's delete has nothing to
    // remove for an account that never signed in.
    pg_auth_token_revoke_user($user_id);

    if (function_exists('pg_mfa_reset')) {
        pg_mfa_reset($user_id);
    }

    foreach (array(
        'aclfolder'                 => 'aclfolder_user',
        'users_contact_groups_xref' => 'user_id',
        'users_calendars_xref'      => 'user_id',
        'users_ad_regions_xref'     => 'user_id',
        'users_common_regions_xref' => 'user_id',
    ) as $table => $column) {
        db("DELETE FROM " . $table . " WHERE " . $column . " = '" . $user_id . "'");
    }

    db("DELETE FROM user WHERE user_id = '" . $user_id . "'");

    return true;
}
