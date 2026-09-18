<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// A module of functions.php: Sign-in, passwords, remember-me tokens and device limits, CAPTCHA, login throttling, the upgrade bridge, and the validate_*_access() gates.
//
// Loaded by functions.php through require_once, never on its own. The
// functions were moved here from functions.php as they were; PG_FUNCTIONS_DIR
// is the software directory (what dirname(__FILE__) was in functions.php),
// see the note at the top of functions.php.
if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}
/**
 * Google's mark as inline SVG at the requested pixel size. One copy for every
 * place it appears (the sign-in button, the account notice), because these
 * paths are a brand asset and must not drift between copies.
 */
function pg_google_logo_svg($size = 18)
{
    $size = (int) $size;
    if ($size <= 0) {
        $size = 18;
    }

    return '<svg width="' . $size . '" height="' . $size . '" viewBox="0 0 18 18" aria-hidden="true">'
        . '<path fill="#4285F4" d="M17.64 9.2c0-.64-.06-1.25-.16-1.84H9v3.48h4.84a4.14 4.14 0 0 1-1.8 2.72v2.26h2.92c1.71-1.57 2.68-3.89 2.68-6.62z"/>'
        . '<path fill="#34A853" d="M9 18c2.43 0 4.47-.8 5.96-2.18l-2.92-2.26c-.81.54-1.85.86-3.04.86-2.34 0-4.32-1.58-5.03-3.7H.96v2.33A9 9 0 0 0 9 18z"/>'
        . '<path fill="#FBBC05" d="M3.97 10.72a5.4 5.4 0 0 1 0-3.44V4.95H.96a9 9 0 0 0 0 8.1l3.01-2.33z"/>'
        . '<path fill="#EA4335" d="M9 3.58c1.32 0 2.51.46 3.44 1.35l2.58-2.58C13.46.9 11.43 0 9 0A9 9 0 0 0 .96 4.95L3.97 7.3C4.67 5.17 6.66 3.58 9 3.58z"/>'
        . '</svg>';
}

/**
 * A compact "this account signs in with Google" line for the my-account
 * screens: the mark, a short label, and - while the account still has no
 * password of its own (algo 3) - a link to the change-password screen, which
 * renders in set-a-password mode for exactly this case. Returns '' when the
 * account has no Google connection, so a layout can print it unconditionally.
 */
function pg_google_account_notice()
{
    $user_id = (int) ($_SESSION['sessionuserid'] ?? 0);
    if ($user_id <= 0) {
        return '';
    }

    $row = db_item(
        "SELECT user_google_id AS google_id, user_password_algo AS algo
        FROM user
        WHERE user_id = '" . $user_id . "'");

    if ((is_array($row) == false) || ((string) ($row['google_id'] ?? '') === '')) {
        return '';
    }

    $output =
        '<span class="pg-google-account" style="display:inline-flex;align-items:center;gap:6px;flex-wrap:wrap">'
        . pg_google_logo_svg(16)
        . '<span>' . h(lang('Connected with Google')) . '</span>';

    // No password of its own yet: point at the screen that sets the first one.
    if (((int) ($row['algo'] ?? 0)) === 3) {
        $notice_change_password_url = get_page_type_url('change password');
        $output .= '<span>&middot;</span><span>' . h(lang('No password set')) . '</span>';

        if ($notice_change_password_url) {
            $output .= '<a href="' . h($notice_change_password_url) . '">' . h(lang('Set a password')) . '</a>';
        }
    }

    return $output . '</span>';
}

/**
 * Lifetime of the "remember me" login cookies, in seconds.
 *
 * These cookies carry a credential, not a preference: the stored value is the
 * password hash that the login query matches on, so whoever holds the pair is
 * signed in until the password itself changes. They used to be written with a
 * ten year expiry, which meant a copy taken off a shared or lost machine
 * outlived the machine.
 *
 * The constant is read defensively so an install that has not added it to
 * data/config.php keeps the default, and it is clamped so that a typo cannot
 * bring the ten year expiry back by accident.
 */
function pg_remember_me_lifetime()
{
    $days = 30;

    if (defined('REMEMBER_ME_DAYS') && ((int) REMEMBER_ME_DAYS > 0)) {
        $days = min((int) REMEMBER_ME_DAYS, 365);
    }

    return $days * 86400;
}

// ============================================================================
// Password storage and session tokens (2026.4.4).
// Full rationale: docs/_plan_parola_oturum_google.md.
//
// These are the pure building blocks: hashing, verify-and-upgrade, batch
// wrapping, and the auth_tokens store. Nothing here reads a session or a
// cookie; the sign-in flows and initialize_user() call in. Kept together so
// the whole authentication core is one block.
// ============================================================================

// The one place a new password hash is produced. PASSWORD_DEFAULT (bcrypt
// today) rather than a pinned Argon2: a bcrypt hash verifies on every PHP 5.5+
// build a backup might be restored onto, which Argon2 does not. Bkz. plan §2.
function pg_password_hash($password)
{
    return password_hash((string) $password, PASSWORD_DEFAULT);
}

// Store the modern hash for a user and stamp the column as algo 2.
function pg_password_store($user_id, $password)
{
    $hash = pg_password_hash($password);

    db("UPDATE user
        SET user_password = '" . escape($hash) . "', user_password_algo = 2
        WHERE user_id = '" . (int) $user_id . "'");

    return $hash;
}

// The one place a customer-facing (role 3) member account row is written, so a
// modern password hash is set from the start (no transient MD5) and a caller
// cannot forget the algo stamp. Each caller keeps its own surrounding flow -
// contact groups, remember-me, the confirmation e-mail, the redirect - and hands
// the finished account here. Google sign-up (Faz 6) calls this with no password
// and a provider id instead of being a seventh INSERT INTO user.
//
//   $data keys: email, username (both required; the caller uniquifies username),
//   password (optional plaintext -> modern hash, algo 2; omit/empty for an
//   external provider-only account -> stored empty, algo 3), contact_id, home
//   (start page id), password_hint, role (default '3'), google_id. Returns the
//   new user_id.
function create_member_user($data)
{
    $email    = isset($data['email'])    ? (string) $data['email']    : '';
    $username = isset($data['username']) ? (string) $data['username'] : '';
    $role     = (isset($data['role']) && ($data['role'] !== '')) ? (string) $data['role'] : '3';

    // A supplied password is stored as a modern hash (algo 2). No password means
    // an external account (e.g. Google) that can never be signed into with a
    // password: stored empty and marked algo 3, which pg_password_verify rejects.
    if (isset($data['password']) && ($data['password'] !== '')) {
        $password_value = pg_password_hash((string) $data['password']);
        $password_algo  = 2;
    } else {
        $password_value = '';
        $password_algo  = 3;
    }

    // Required columns, always written (this column order is self-contained, so
    // it need not match the table or the old per-site INSERTs).
    $columns = array('user_username', 'user_email', 'user_role', 'user_password', 'user_password_algo');
    $values  = array(
        "'" . escape($username) . "'",
        "'" . escape($email) . "'",
        "'" . escape($role) . "'",
        "'" . escape($password_value) . "'",
        (int) $password_algo,
    );

    // Optional columns, written only when the caller opts in, so each site keeps
    // the exact column set it had. password_hint is written whenever the key is
    // present (even empty), matching membership/registration which always set it.
    if (isset($data['contact_id']) && ($data['contact_id'] !== '')) {
        $columns[] = 'user_contact';
        $values[]  = "'" . escape($data['contact_id']) . "'";
    }
    if (isset($data['home']) && ($data['home'] !== '')) {
        $columns[] = 'user_home';
        $values[]  = "'" . escape($data['home']) . "'";
    }
    if (isset($data['password_hint'])) {
        $columns[] = 'user_password_hint';
        $values[]  = "'" . escape($data['password_hint']) . "'";
    }
    if (isset($data['google_id']) && ($data['google_id'] !== '')) {
        $columns[] = 'user_google_id';
        $values[]  = "'" . escape($data['google_id']) . "'";
    }

    // A brand new account's password is as old as the account, so the stamp is
    // the creation time. Skipped on a schema that predates the column.
    if (pg_user_has_password_changed_at()) {
        $columns[] = 'user_password_changed_at';
        $values[]  = 'UNIX_TIMESTAMP()';
    }

    // Creation time last, as the old INSERTs had it.
    $columns[] = 'user_timestamp';
    $values[]  = 'UNIX_TIMESTAMP()';

    db("INSERT INTO user (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $values) . ")");

    $new_user_id = (int) mysqli_insert_id(db::$con);

    pg_user_created_stamp($new_user_id);

    return $new_user_id;
}

// When did this account come into being?
//
// user_timestamp is rewritten every time an account is saved, so it answers
// "last touched", not "signed up". user_created is written once, by whichever
// screen creates the account, and offers that ask for a new customer read it.
// Sites that have not run the upgrade have no such column yet, so the write is
// skipped rather than failing the sign-up it belongs to.
function pg_user_created_stamp($user_id)
{
    static $column_exists = null;
    if ($column_exists === null) {
        $column_exists = (bool) db_item("SHOW COLUMNS FROM user LIKE 'user_created'");
    }
    if (!$column_exists || !$user_id) {
        return;
    }
    db("UPDATE user SET user_created = UNIX_TIMESTAMP() WHERE (user_id = '" . e($user_id) . "') AND (user_created = '0')");
}

// Read the claims (payload) of a JWT without verifying its signature. This is
// only safe on an id_token fetched directly from Google's TLS token endpoint,
// authenticated with the client secret (google_auth.php) - never on a token
// received from a browser. Returns the claims array, or null if the token is
// malformed.
function pg_google_jwt_payload($jwt)
{
    $parts = explode('.', (string) $jwt);
    if (count($parts) !== 3) {
        return null;
    }

    // base64url -> base64, then pad to a multiple of four.
    $payload = strtr($parts[1], '-_', '+/');
    $pad = strlen($payload) % 4;
    if ($pad) {
        $payload .= str_repeat('=', 4 - $pad);
    }

    $json = base64_decode($payload, true);
    if ($json === false) {
        return null;
    }

    $claims = json_decode($json, true);
    return is_array($claims) ? $claims : null;
}

// The "Sign in with Google" button as a self-contained block, or '' when the
// feature is off - so the login, membership and registration screens can each
// drop it in with one call and stay identical. Inline styles (Google's own
// button look) keep it working on any front-end theme, Bootstrap or not.
function pg_google_signup_allowed()
{
    // Self-registration is not a setting in this product: a site expresses it
    // by publishing (or not publishing) an entrance page. A site that wants
    // sign-in only removes those pages - and then Google must not quietly
    // create accounts behind its back either. A designed page carrying the
    // registration or membership widget is an entrance page too.
    if (db_value(
        "SELECT page_id
        FROM page
        WHERE page_type IN ('membership entrance', 'registration entrance')
        LIMIT 1") != '') {
        return true;
    }
    return function_exists('pg_member_widget_published')
        && (pg_member_widget_published('registration') || pg_member_widget_published('membership'));
}

// The address the "Sign in with Google" button points at, or '' when the
// feature is off. Split from the button so a designed page can put its own
// element on the same address (the visual designer binds a link's href to
// it) without a second copy of the URL rules.
function pg_google_signin_url($send_to = '', $signup = 'create')
{
    if (
        !defined('OAUTH_GOOGLE_ENABLED') || !OAUTH_GOOGLE_ENABLED
        || !defined('OAUTH_GOOGLE_CLIENT_ID') || OAUTH_GOOGLE_CLIENT_ID === ''
    ) {
        return '';
    }

    // What this screen may do with a Google identity nobody here matches:
    //   'create'     - open a member account straight away (registration).
    //   'membership' - hand it to the membership form, which still has to
    //                  check a membership number against the member list.
    //   'none'       - sign in only (the staff login).
    // The flag travels in the URL, so google_auth.php pairs it with the
    // server-side check above rather than trusting it on its own. Booleans
    // keep working for older call sites.
    if ($signup === true) {
        $signup = 'create';
    } elseif ($signup === false) {
        $signup = 'none';
    } elseif (($signup !== 'none') && ($signup !== 'membership')) {
        $signup = 'create';
    }

    $query = array();
    if ((string) $send_to !== '') {
        $query['send_to'] = (string) $send_to;
    }
    if ($signup === 'none') {
        $query['create'] = '0';
    } elseif ($signup === 'membership') {
        $query['create'] = 'membership';
    }

    $url = OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/google_auth.php';
    if (count($query) > 0) {
        $url .= '?' . http_build_query($query);
    }
    return $url;
}

function pg_google_signin_button($send_to = '', $signup = 'create')
{
    $url = pg_google_signin_url($send_to, $signup);
    if ($url === '') {
        return '';
    }

    $logo = pg_google_logo_svg(18);

    return '<div style="margin-top:1em;text-align:center">'
        . '<a href="' . h($url) . '" style="display:inline-flex;align-items:center;justify-content:center;gap:10px;padding:9px 18px;border:1px solid #dadce0;border-radius:4px;background:#fff !important;color:#3c4043 !important;font-weight:500;text-decoration:none !important;font-family:Roboto,Arial,sans-serif;font-size:14px !important;line-height:1.4">'
        . $logo
        // !important on the label too: a theme that paints links white (this
        // is a sign-in screen over a photo, so several do) left the button
        // showing the mark and no words at all.
        . '<span style="color:#3c4043 !important;font-size:14px !important;white-space:nowrap">' . h(lang('Sign in with Google')) . '</span></a></div>';
}

// Register a member from the 'register' liveform: the validation and the
// writes the registration entrance has always done, in one place, so the
// legacy screen and a designed page (the registration widget) run the same
// rules. The caller has already run validate_token_field(), require_cookies()
// and check_banned_ip_addresses(), and has put the POST on the form with
// add_fields_to_session(); it also decides where the visitor goes next.
//
// $opts:
//   contact_fields    column => value pairs written to the new contact row on
//                     top of the core columns (the widget's contact-bound
//                     controls; the caller resolved the columns, never the
//                     visitor).
//   contact_group_id  group the contact joins; the site setting when absent.
//
// Field names are the entrance screen's: first_name, last_name, username,
// email, email_verify, password, password_verify, password_hint, opt_in,
// register_remember_me (remember_me is accepted too), and the CAPTCHA fields.
// A form that never asked for a username gets one derived from the address.
//
// Returns array('ok' => bool, 'user_id' => int, 'contact_id' => int). When
// ok is false the errors are on the form and nothing was written.
function pg_member_register($form, $opts = array())
{
    if (!is_array($opts)) $opts = array();
    $out = array('ok' => false, 'user_id' => 0, 'contact_id' => 0);

    $has_username = $form->field_in_session('username');

    $form->validate_required_field('first_name', lang('First Name is required.'));
    $form->validate_required_field('last_name', lang('Last Name is required.'));
    if ($has_username) {
        $form->validate_required_field('username', lang('Username is required.'));
    }
    $form->validate_required_field('email', lang('Email is required.'));
    $form->validate_required_field('email_verify', lang('Please type email address again.'));
    $form->validate_required_field('password', lang('New Password is required.'));
    $form->validate_required_field('password_verify', lang('Please type new password again.'));

    $email = (string) $form->get_field_value('email');

    // Username: taken by an account, or already someone's address.
    if ($has_username && ($form->check_field_error('username') == false)) {
        $username = (string) $form->get_field_value('username');
        if (db_value(
            "SELECT COUNT(*) FROM user
            WHERE (user_username = '" . escape($username) . "') OR (user_email = '" . escape($username) . "')") > 0) {
            $form->mark_error('username', lang('The username you entered is already in use. Please enter a different username.'));
        }
    }

    // Email: the two copies agree, the address is valid, not blocked, not in use.
    if (($form->check_field_error('email') == false) && ($form->check_field_error('email_verify') == false)) {
        if ($email != $form->get_field_value('email_verify')) {
            $form->mark_error('email', lang('The two email addresses you entered did not match.'));
            $form->mark_error('email_verify');
        }
    }
    if (($form->check_field_error('email') == false) && (validate_email_address($email) == false)) {
        $form->mark_error('email', lang('The email address you entered is invalid.'));
        $form->mark_error('email_verify');
    }
    if (($form->check_field_error('email') == false) && pg_email_blocked($email)) {
        $form->mark_error('email', lang('This email address cannot be used on this site.'));
    }
    if ($form->check_field_error('email') == false) {
        if (db_value(
            "SELECT COUNT(*) FROM user
            WHERE (user_email = '" . escape($email) . "') OR (user_username = '" . escape($email) . "')") > 0) {
            $form->mark_error('email', lang('The email address you entered is already in use. Please enter a different email address.'));
            $form->mark_error('email_verify');
        }
    }

    // Password: the two copies agree, strong when the site asks, not in the hint.
    $password = (string) $form->get_field_value('password');
    if (($form->check_field_error('password') == false) && ($form->check_field_error('password_verify') == false)) {
        if ($password != $form->get_field_value('password_verify')) {
            $form->mark_error('password', lang('The two passwords you entered did not match.'));
            $form->mark_error('password_verify');
            $form->assign_field_value('password', '');
            $form->assign_field_value('password_verify', '');
        }
    }
    if (
        ($form->check_field_error('password') == false)
        && ($form->check_field_error('password_verify') == false)
        && (STRONG_PASSWORD == true)
        && (validate_password_strength($password) == false)
    ) {
        $form->mark_error('password', lang('The password you entered does not meet the requirements. Please enter a different password.'));
        $form->mark_error('password_verify');
        $form->assign_field_value('password', '');
        $form->assign_field_value('password_verify', '');
    }
    $password_hint = (string) $form->get_field_value('password_hint');
    if (($password_hint != '') && ($password != '')) {
        if (
            ($password_hint == $password)
            || (mb_strpos(mb_strtolower($password_hint), mb_strtolower($password)) !== false)
        ) {
            $form->mark_error('password_hint', lang('Your password hint cannot contain your password.'));
            $form->assign_field_value('password_hint', '');
        }
    }

    if (CAPTCHA == TRUE) {
        validate_captcha_answer($form);
    }

    if ($form->check_form_errors()) {
        return $out;
    }

    // Re-read after validation: the checks above may have blanked fields.
    $password      = (string) $form->get_field_value('password');
    $password_hint = (string) $form->get_field_value('password_hint');
    $opt_in        = ($form->get('opt_in') ? '1' : '0');
    $username      = $has_username
        ? (string) $form->get_field_value('username')
        : get_unique_username((string) strtok($email, '@'));

    // The contact first: the core columns, then whatever the caller mapped.
    // Column names come from the caller (the widget's tree), never from the
    // visitor, and are checked against the address book's own field list.
    $columns = array(
        'first_name'    => (string) $form->get_field_value('first_name'),
        'last_name'     => (string) $form->get_field_value('last_name'),
        'email_address' => $email,
        'opt_in'        => $opt_in,
    );
    if (!empty($opts['contact_fields']) && is_array($opts['contact_fields'])) {
        $allowed = function_exists('pg_cf_contact_fields') ? pg_cf_contact_fields() : array();
        foreach ($opts['contact_fields'] as $column => $value) {
            $column = (string) $column;
            if ($column === '' || $column === 'image' || !isset($allowed[$column])) continue;
            if (isset($columns[$column])) continue;
            if (is_array($value)) $value = implode(', ', array_map('strval', $value));
            $columns[$column] = (string) $value;
        }
    }
    $sql_columns = array();
    $sql_values  = array();
    foreach ($columns as $column => $value) {
        $sql_columns[] = $column;
        $sql_values[]  = "'" . escape($value) . "'";
    }
    $sql_columns[] = 'timestamp';
    $sql_values[]  = 'UNIX_TIMESTAMP()';
    db("INSERT INTO contacts (" . implode(', ', $sql_columns) . ") VALUES (" . implode(', ', $sql_values) . ")");
    $contact_id = (int) mysqli_insert_id(db::$con);

    // Every contact with this address carries the same consent answer.
    db("UPDATE contacts SET opt_in = '" . e($opt_in) . "' WHERE email_address = '" . e($email) . "'");

    $group_id = (isset($opts['contact_group_id']) && (int) $opts['contact_group_id'] > 0)
        ? (int) $opts['contact_group_id']
        : (int) REGISTRATION_CONTACT_GROUP_ID;
    if ($group_id > 0 && db_value("SELECT id FROM contact_groups WHERE id = '" . $group_id . "'")) {
        db("INSERT INTO contacts_contact_groups_xref (contact_id, contact_group_id)
            VALUES ('" . $contact_id . "', '" . $group_id . "')");
    }

    // The account. create_member_user() writes the modern hash and returns
    // the id; contact, start page and hint travel as data.
    $create = array(
        'username'      => $username,
        'email'         => $email,
        'password'      => $password,
        'password_hint' => $password_hint,
        'contact_id'    => $contact_id,
    );
    if (!empty($_SESSION['software']['start_page_id'])) {
        $create['home'] = $_SESSION['software']['start_page_id'];
    }
    $new_user_id = (int) create_member_user($create);

    // Remember me: the entrance screen's field name first, the login form's
    // as the designed page may use it.
    $remember = false;
    if (REMEMBER_ME == TRUE) {
        $remember = ($form->get_field_value('register_remember_me') == 1)
                 || ($form->get_field_value('remember_me') == 1);
        if ($remember) {
            setcookie('software[auth]', pg_auth_token_create($new_user_id, pg_remember_me_lifetime()),
                time() + pg_remember_me_lifetime(), '/', '', pg_request_is_https(), true);
        }
        setcookie('software[remember_me]', $remember ? 'true' : 'false', time() + 315360000, '/');
    }

    $_SESSION['sessionuserid']  = $new_user_id;
    $_SESSION['sessionusername'] = $username;

    // A sign-up that was not remembered still binds this session to a device
    // token while the device limit is on. No-op when the limit is off.
    if (!$remember) {
        pg_login_set_device_cookie($new_user_id, false);
    }

    require_once(PG_FUNCTIONS_DIR . '/connect_user_to_order.php');
    connect_user_to_order();

    if (REGISTRATION_EMAIL_ADDRESS) {
        email(array(
            'to'                 => REGISTRATION_EMAIL_ADDRESS,
            'from_name'          => ORGANIZATION_NAME,
            'from_email_address' => EMAIL_ADDRESS,
            'subject'            => lang('Registration Confirmation'),
            'format'             => 'html',
            'body'               => get_registration_confirmation_screen()));
    }

    $form->remove();

    $out['ok']         = true;
    $out['user_id']    = $new_user_id;
    $out['contact_id'] = $contact_id;
    return $out;
}

// Activate a membership from the 'membership_entrance' liveform: the checks
// and writes the membership entrance has always done, shared by the legacy
// screen and the membership widget. Same contract as pg_member_register():
// the caller validated the token, cookies and IP, put the POST on the form,
// and decides where the visitor goes next.
//
// Field names are the entrance screen's: first_name, last_name, member_id,
// username, email_address, email_address_verify, password, password_verify,
// password_hint, opt_in, register_remember_me. A designed page may name the
// address fields email / email_verify instead; both spellings are read.
// A form that never asked for a username gets one derived from the address.
//
// A Google sign-in that matched no account arrives here with a pending
// record in the session: the address is the verified one and a password is
// optional (the member can keep signing in with Google).
//
// $opts: contact_fields (column => value written to the member's contact,
// found or created), contact_group_id (the site setting when absent).
function pg_member_activate($form, $opts = array())
{
    if (!is_array($opts)) $opts = array();
    $out = array('ok' => false, 'user_id' => 0, 'contact_id' => 0);

    $email_field  = $form->field_in_session('email_address') ? 'email_address' : 'email';
    $verify_field = ($email_field === 'email_address') ? 'email_address_verify' : 'email_verify';
    $has_username = $form->field_in_session('username');

    $google_pending = $_SESSION['software']['google_membership_pending'] ?? null;
    $google_signup  = (is_array($google_pending)
        && ((time() - ((int) ($google_pending['time'] ?? 0))) <= 900));
    if ($google_signup) {
        $form->assign_field_value($email_field, (string) ($google_pending['email'] ?? ''));
        $form->assign_field_value($verify_field, (string) ($google_pending['email'] ?? ''));
    }

    $form->validate_required_field('first_name', lang('First Name is required.'));
    $form->validate_required_field('last_name', lang('Last Name is required.'));
    $form->validate_required_field('member_id', lang(array('string' => '{var:1} is required.', 'vars' => MEMBER_ID_LABEL)));
    if ($has_username) {
        $form->validate_required_field('username', lang('Username is required.'));
    }
    $form->validate_required_field($email_field, lang('Email is required.'));
    $form->validate_required_field($verify_field, lang('Please type email address again.'));
    if (!$google_signup) {
        $form->validate_required_field('password', lang('New Password is required.'));
        $form->validate_required_field('password_verify', lang('Please type new password again.'));
    }

    $member_id = (string) $form->get_field_value('member_id');
    $email     = (string) $form->get_field_value($email_field);
    $last_name = (string) $form->get_field_value('last_name');

    // The membership number has to be on the member list.
    $expiration_date = '';
    if ($form->check_field_error('member_id') == false) {
        $member = db_item("SELECT expiration_date FROM contacts WHERE member_id = '" . escape($member_id) . "' LIMIT 1");
        if (empty($member)) {
            $form->mark_error('member_id', lang(array(
                'string' => 'The {var:1} you entered was not found. Please enter a different {var:1}.',
                'vars'   => MEMBER_ID_LABEL)));
        } else {
            $expiration_date = (string) $member['expiration_date'];
        }
    }

    if ($has_username && ($form->check_field_error('username') == false)) {
        $username = (string) $form->get_field_value('username');
        if (db_value(
            "SELECT COUNT(*) FROM user
            WHERE (user_username = '" . escape($username) . "') OR (user_email = '" . escape($username) . "')") > 0) {
            $form->mark_error('username', lang('The username you entered is already in use. Please enter a different username.'));
        }
    }

    if (($form->check_field_error($email_field) == false) && ($form->check_field_error($verify_field) == false)) {
        if ($email != $form->get_field_value($verify_field)) {
            $form->mark_error($email_field, lang('The two email addresses you entered did not match.'));
            $form->mark_error($verify_field);
        }
    }
    if (($form->check_field_error($email_field) == false) && (validate_email_address($email) == false)) {
        $form->mark_error($email_field, lang('The email address you entered is invalid.'));
        $form->mark_error($verify_field);
    }
    if (($form->check_field_error($email_field) == false) && pg_email_blocked($email)) {
        $form->mark_error($email_field, lang('This email address cannot be used on this site.'));
        $form->mark_error($verify_field);
    }
    if ($form->check_field_error($email_field) == false) {
        if (db_value(
            "SELECT COUNT(*) FROM user
            WHERE (user_email = '" . escape($email) . "') OR (user_username = '" . escape($email) . "')") > 0) {
            $form->mark_error($email_field, lang('The email address you entered is already in use. Please enter a different email address.'));
            $form->mark_error($verify_field);
        }
    }

    $password = (string) $form->get_field_value('password');
    if (($form->check_field_error('password') == false) && ($form->check_field_error('password_verify') == false)) {
        if ($password != $form->get_field_value('password_verify')) {
            $form->mark_error('password', lang('The two passwords you entered did not match.'));
            $form->mark_error('password_verify');
            $form->assign_field_value('password', '');
            $form->assign_field_value('password_verify', '');
        }
    }
    // An empty password is a valid answer in a Google sign-up; only a
    // password the visitor typed has to be strong.
    if (
        ($form->check_field_error('password') == false)
        && ($form->check_field_error('password_verify') == false)
        && (STRONG_PASSWORD == true)
        && (($google_signup == false) || ($password !== ''))
        && (validate_password_strength($password) == false)
    ) {
        $form->mark_error('password', lang('The password you entered does not meet the requirements. Please enter a different password.'));
        $form->mark_error('password_verify');
        $form->assign_field_value('password', '');
        $form->assign_field_value('password_verify', '');
    }
    $password_hint = (string) $form->get_field_value('password_hint');
    if (($password_hint != '') && ($password != '')) {
        if (
            ($password_hint == $password)
            || (mb_strpos(mb_strtolower($password_hint), mb_strtolower($password)) !== false)
        ) {
            $form->mark_error('password_hint', lang('Your password hint cannot contain your password.'));
            $form->assign_field_value('password_hint', '');
        }
    }

    if ($form->check_form_errors()) {
        return $out;
    }

    $password      = (string) $form->get_field_value('password');
    $password_hint = (string) $form->get_field_value('password_hint');
    $opt_in        = ($form->get('opt_in') ? '1' : '0');
    $username      = $has_username
        ? (string) $form->get_field_value('username')
        : get_unique_username((string) strtok($email, '@'));

    // Extra address-book columns from the caller (see pg_member_register()).
    $extra = array();
    if (!empty($opts['contact_fields']) && is_array($opts['contact_fields'])) {
        $allowed = function_exists('pg_cf_contact_fields') ? pg_cf_contact_fields() : array();
        foreach ($opts['contact_fields'] as $column => $value) {
            $column = (string) $column;
            if ($column === '' || $column === 'image' || !isset($allowed[$column])) continue;
            if (in_array($column, array('first_name', 'last_name', 'email_address', 'opt_in', 'member_id', 'expiration_date'), true)) continue;
            if (is_array($value)) $value = implode(', ', array_map('strval', $value));
            $extra[$column] = (string) $value;
        }
    }
    $extra_set = '';
    foreach ($extra as $column => $value) {
        $extra_set .= ", " . $column . " = '" . escape($value) . "'";
    }

    // The member's own contact when number and surname match one; a new one
    // carrying the number and its expiry otherwise.
    $contact_id = (int) db_value(
        "SELECT id FROM contacts
        WHERE member_id = '" . escape($member_id) . "' AND last_name = '" . escape($last_name) . "'
        LIMIT 1");
    if ($contact_id > 0) {
        db("UPDATE contacts
            SET
                first_name = '" . escape($form->get_field_value('first_name')) . "',
                email_address = '" . escape($email) . "',
                opt_in = '" . e($opt_in) . "'" . $extra_set . "
            WHERE id = '" . $contact_id . "'");
    } else {
        $columns = array(
            'first_name'      => (string) $form->get_field_value('first_name'),
            'last_name'       => $last_name,
            'email_address'   => $email,
            'member_id'       => $member_id,
            'expiration_date' => $expiration_date,
            'opt_in'          => $opt_in,
        );
        foreach ($extra as $column => $value) $columns[$column] = $value;
        $sql_columns = array();
        $sql_values  = array();
        foreach ($columns as $column => $value) {
            $sql_columns[] = $column;
            $sql_values[]  = "'" . escape($value) . "'";
        }
        $sql_columns[] = 'timestamp';
        $sql_values[]  = 'UNIX_TIMESTAMP()';
        db("INSERT INTO contacts (" . implode(', ', $sql_columns) . ") VALUES (" . implode(', ', $sql_values) . ")");
        $contact_id = (int) mysqli_insert_id(db::$con);
    }

    db("UPDATE contacts SET opt_in = '" . e($opt_in) . "' WHERE email_address = '" . e($email) . "'");

    $group_id = (isset($opts['contact_group_id']) && (int) $opts['contact_group_id'] > 0)
        ? (int) $opts['contact_group_id']
        : (int) MEMBERSHIP_CONTACT_GROUP_ID;
    if ($group_id > 0 && db_value("SELECT id FROM contact_groups WHERE id = '" . $group_id . "'")) {
        $in_group = db_value(
            "SELECT contact_id FROM contacts_contact_groups_xref
            WHERE contact_id = '" . $contact_id . "' AND contact_group_id = '" . $group_id . "'");
        if (!$in_group) {
            db("INSERT INTO contacts_contact_groups_xref (contact_id, contact_group_id)
                VALUES ('" . $contact_id . "', '" . $group_id . "')");
        }
    }

    $create = array(
        'username'      => $username,
        'email'         => $email,
        'password'      => $password,
        'password_hint' => $password_hint,
        'contact_id'    => $contact_id,
    );
    // Bind the account to the Google identity that started this, and spend
    // the pending record so it cannot be replayed.
    if ($google_signup) {
        $create['google_id'] = (string) ($google_pending['sub'] ?? '');
        unset($_SESSION['software']['google_membership_pending']);
    }
    if (!empty($_SESSION['software']['start_page_id'])) {
        $create['home'] = $_SESSION['software']['start_page_id'];
    }
    $new_user_id = (int) create_member_user($create);

    $remember = false;
    if (REMEMBER_ME == TRUE) {
        $remember = ($form->get_field_value('register_remember_me') == 1)
                 || ($form->get_field_value('remember_me') == 1);
        if ($remember) {
            setcookie('software[auth]', pg_auth_token_create($new_user_id, pg_remember_me_lifetime()),
                time() + pg_remember_me_lifetime(), '/', '', pg_request_is_https(), true);
        }
        setcookie('software[remember_me]', $remember ? 'true' : 'false', time() + 315360000, '/');
    }

    $_SESSION['sessionuserid']  = $new_user_id;
    $_SESSION['sessionusername'] = $username;

    if (!$remember) {
        pg_login_set_device_cookie($new_user_id, false);
    }

    require_once(PG_FUNCTIONS_DIR . '/connect_user_to_order.php');
    connect_user_to_order();

    if (MEMBERSHIP_EMAIL_ADDRESS) {
        email(array(
            'to'                 => MEMBERSHIP_EMAIL_ADDRESS,
            'from_name'          => ORGANIZATION_NAME,
            'from_email_address' => EMAIL_ADDRESS,
            'subject'            => lang('Registration Confirmation'),
            'format'             => 'html',
            'body'               => get_membership_confirmation_screen()));
    }

    $form->remove();

    $out['ok']         = true;
    $out['user_id']    = $new_user_id;
    $out['contact_id'] = $contact_id;
    return $out;
}

// A short, human label for a browser User-Agent, for the device list. Best
// effort: names the common browser and OS, otherwise a trimmed UA.
/**
 * Role name for a user row. view_users.php has had its own copy forever, but
 * it is a page-local function, so anything outside that screen could not name
 * a role at all.
 */
function pg_user_role_name($role)
{
    switch ((int) $role) {
        case 0:
            return lang('Administrator');
        case 1:
            return lang('Designer');
        case 2:
            return lang('Manager');
        default:
            return lang('User');
    }
}

/**
 * How an account signs in, as a short badge: connected to Google and still has
 * a password of its own, connected and Google-only (removing the connection
 * would lock it out), or nothing connected. Shared by the sessions table and
 * the connected-accounts table so the two never drift apart.
 */
function pg_connected_account_label($google_id, $password_algo)
{
    if ((string) $google_id === '') {
        return '<span class="text-muted">&mdash;</span>';
    }

    if (((int) $password_algo) === 3) {
        return '<span class="badge bg-warning text-dark fw-light" title="'
            . h(lang('This account has no password of its own - disconnecting Google would lock it out.')) . '">'
            . h(lang('Google only')) . '</span>';
    }

    return '<span class="badge bg-light text-dark border fw-light">' . h(lang('Google')) . '</span>';
}

function pg_user_agent_label($ua)
{
    $ua = (string) $ua;

    if ($ua === '') {
        return lang('Unknown device');
    }

    // Browser, most specific first: Edge, Opera and Samsung all carry "Chrome"
    // in their string, and Chrome and Firefox on iOS carry "Safari", so a
    // looser order would label half the world Chrome. The major version is
    // part of the answer: two Chromes that differ by a version are two
    // different installs, which is exactly what an operator is trying to tell
    // apart when a member says "sign out my other computer".
    $browser = '';
    $patterns = array(
        'Edge'             => '~Edg(?:e|A|iOS)?/(\d+)~i',
        'Opera'            => '~(?:OPR|OPiOS|Opera)/(\d+)~i',
        'Samsung Internet' => '~SamsungBrowser/(\d+)~i',
        'Yandex'           => '~YaBrowser/(\d+)~i',
        'Chrome'           => '~(?:CriOS|Chrome)/(\d+)~i',
        'Firefox'          => '~(?:FxiOS|Firefox)/(\d+)~i',
        'Safari'           => '~Version/(\d+)[.\d]*\s+.*Safari~i',
    );

    foreach ($patterns as $name => $pattern) {
        if (preg_match($pattern, $ua, $m)) {
            $browser = $name . ' ' . $m[1];
            break;
        }
    }

    if (($browser === '') && (stripos($ua, 'Safari') !== false)) {
        $browser = 'Safari';
    }

    // Operating system, with a version where the string carries one. Windows
    // NT 10.0 covers both 10 and 11 - Microsoft stopped telling browsers
    // apart - so it is reported honestly as "Windows 10/11".
    $os = '';
    if (preg_match('~Windows NT ([\d.]+)~i', $ua, $m)) {
        $windows = array('10.0' => '10/11', '6.3' => '8.1', '6.2' => '8', '6.1' => '7', '6.0' => 'Vista', '5.1' => 'XP');
        $os = 'Windows' . (isset($windows[$m[1]]) ? (' ' . $windows[$m[1]]) : '');
    } elseif (preg_match('~Android ([\d.]+)~i', $ua, $m)) {
        $os = 'Android ' . $m[1];
    } elseif (preg_match('~(?:iPhone|CPU) OS ([\d_]+)~i', $ua, $m)) {
        $os = ((stripos($ua, 'iPad') !== false) ? 'iPadOS ' : 'iOS ') . str_replace('_', '.', $m[1]);
    } elseif (stripos($ua, 'CrOS') !== false) {
        $os = 'ChromeOS';
    } elseif (preg_match('~Mac OS X ([\d_.]+)~i', $ua, $m)) {
        $os = 'macOS ' . str_replace('_', '.', $m[1]);
    } elseif (stripos($ua, 'Mac OS') !== false) {
        $os = 'macOS';
    } elseif (stripos($ua, 'Linux') !== false) {
        $os = 'Linux';
    } elseif (stripos($ua, 'Windows') !== false) {
        $os = 'Windows';
    }

    // Form factor: the quickest way for a member to recognise a session
    // ("that is my phone"), and it does not depend on the browser at all.
    if ((stripos($ua, 'iPad') !== false)
        || (stripos($ua, 'Tablet') !== false)
        || ((stripos($ua, 'Android') !== false) && (stripos($ua, 'Mobile') === false))) {
        $kind = lang('Tablet');
    } elseif ((stripos($ua, 'Mobi') !== false)
        || (stripos($ua, 'iPhone') !== false)
        || (stripos($ua, 'Android') !== false)) {
        $kind = lang('Mobile');
    } else {
        $kind = lang('Desktop');
    }

    $parts = array();
    if ($browser !== '') {
        $parts[] = $browser;
    }
    if ($os !== '') {
        $parts[] = $os;
    }
    $parts[] = $kind;

    // Nothing recognisable (a script, a rare browser): show the raw string
    // rather than a confident label that would be wrong.
    if (count($parts) === 1) {
        return mb_substr($ua, 0, 60);
    }

    return implode(' · ', $parts);
}

// The "sign-in and devices" block on the my-account screen: the Google link
// status (with a disconnect that is withheld when Google is the only way in),
// the active remember-me sessions, and a sign-out-everywhere button. Returns ''
// when nobody is signed in. It only renders; every change goes through
// account_security.php, which re-checks the CSRF token and re-enforces the
// Google-only guard server-side.
function pg_account_security_section()
{
    $user_id = (int) ($_SESSION['sessionuserid'] ?? 0);
    if ($user_id <= 0) {
        return '';
    }

    $action_url = OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/account_security.php';
    $token = get_token_field();

    // The selector of this very browser's token, to mark "this device".
    $current_selector = '';
    if (isset($_COOKIE['software']['auth'])) {
        $auth_parts = explode(':', (string) $_COOKIE['software']['auth'], 2);
        $current_selector = (string) $auth_parts[0];
    }

    // Google connection.
    $google_id = db_value("SELECT user_google_id FROM user WHERE user_id = '" . $user_id . "'");
    $algo = (int) db_value("SELECT user_password_algo FROM user WHERE user_id = '" . $user_id . "'");
    $google_html = '';
    if ((string) $google_id !== '') {
        if ($algo === 3) {
            $google_html =
                '<p>' . h(lang('This account signs in with Google.')) . ' '
                . h(lang('To disconnect Google, set a password first with Forgot Password, otherwise you would lose access.')) . '</p>';
        } else {
            $google_html =
                '<p>' . h(lang('This account is connected to Google.')) . '</p>'
                . '<form method="post" action="' . h($action_url) . '" style="margin-bottom:1em">' . $token
                . '<input type="hidden" name="pg_security_action" value="unlink_google"/>'
                . '<button type="submit" class="software_input_submit_secondary">' . h(lang('Disconnect Google')) . '</button></form>';
        }
    }

    // Active remember-me sessions.
    $rows_html = '';
    $count = 0;
    $pin_column = pg_auth_tokens_have_pin();
    $result = mysqli_query(db::$con,
        "SELECT selector, user_agent, ip_address, created_at, last_used_at, expires_at"
         . ($pin_column ? ", pinned" : "") . "
         FROM auth_tokens WHERE user_id = '" . $user_id . "'
         ORDER BY (last_used_at = 0) ASC, last_used_at DESC, created_at DESC");
    if ($result) {
        while ($r = mysqli_fetch_assoc($result)) {
            $count++;
            $is_current = ($current_selector !== '' && hash_equals((string) $r['selector'], $current_selector));
            $fresh = max((int) $r['last_used_at'], (int) $r['created_at']);
            // get_relative_time() returns a <time> element, not plain text.
            $last = ((time() - $fresh) < 300)
                ? '<span class="badge bg-success fw-light">' . h(lang('Online')) . '</span>'
                : get_relative_time(array('timestamp' => $fresh));
            // Two installs of the same browser on the same machine produce the
            // same label and the same address; the raw string and the moment
            // this session began are what still tell them apart, so both are
            // on the row as a tooltip rather than in another column.
            $device_title = trim((string) $r['user_agent']);
            $device_title = (($device_title !== '') ? ($device_title . "\n") : '')
                . lang('First seen') . ': ' . date('Y-m-d H:i', (int) $r['created_at']);

            $rows_html .=
                '<tr><td style="padding:.4em .6em" title="' . h($device_title) . '">' . h(pg_user_agent_label($r['user_agent']))
                . ($is_current ? ' <span class="badge bg-success fw-light">' . h(lang('This device')) . '</span>' : '')
                . '</td><td style="padding:.4em .6em">' . h((string) $r['ip_address'])
                . '</td><td style="padding:.4em .6em">' . $last
                . '</td><td style="padding:.4em .6em;text-align:right">'
                // A locked device shows why it has no button instead of
                // offering one that would only refuse.
                . (($pin_column && isset($r['pinned']) && (((int) $r['pinned']) === 1))
                    ? '<span class="badge bg-secondary fw-light"><i class="bi bi-pin-angle-fill"></i> ' . h(lang('Locked')) . '</span>'
                    : ('<form method="post" action="' . h($action_url) . '" style="margin:0">' . $token
                        . '<input type="hidden" name="pg_security_action" value="revoke_device"/>'
                        . '<input type="hidden" name="pg_selector" value="' . h((string) $r['selector']) . '"/>'
                        . '<button type="submit" class="software_input_submit_secondary">' . h(lang('Sign out')) . '</button>'
                        . '</form>'))
                . '</td></tr>';
        }
    }

    if ($count > 0) {
        $devices_html =
            '<table style="width:100%;border-collapse:collapse" class="table">'
            . '<thead><tr>'
            . '<th style="text-align:left;padding:.4em .6em">' . h(lang('Device')) . '</th>'
            . '<th style="text-align:left;padding:.4em .6em">' . h(lang('IP address')) . '</th>'
            . '<th style="text-align:left;padding:.4em .6em">' . h(lang('Last used')) . '</th>'
            . '<th style="padding:.4em .6em"></th>'
            . '</tr></thead><tbody>' . $rows_html . '</tbody></table>'
            . '<form method="post" action="' . h($action_url) . '" style="margin-top:1em">' . $token
            . '<input type="hidden" name="pg_security_action" value="logout_all"/>'
            . '<button type="submit" class="software_input_submit_secondary">' . h(lang('Sign out of all devices')) . '</button></form>';
    } else {
        $devices_html = '<p>' . h(lang('No remembered devices.')) . '</p>';
    }

    return
        '<div class="pg-account-security" style="margin-top:2em;max-width:640px">'
        . '<div class="heading" style="margin-bottom:10px">' . h(lang('Sign-in and devices')) . '</div>'
        . $google_html . $devices_html . '</div>';
}

// The configured cap on concurrent "remember me" devices per account (Settings).
// 0 means unlimited. A missing column - before the migration runs - reads as 0.
function pg_device_limit()
{
    // Off unless the operator switched it on, regardless of the stored number.
    $enabled = defined('REMEMBER_ME_DEVICE_LIMIT_ENABLED') ? (int) REMEMBER_ME_DEVICE_LIMIT_ENABLED : 0;
    if (!$enabled) {
        return 0;
    }
    // Enabled always means at least one device - a stored 0 must never turn
    // into "no device may sign in", so floor it at 1.
    $limit = defined('REMEMBER_ME_DEVICE_LIMIT') ? (int) REMEMBER_ME_DEVICE_LIMIT : 0;
    return ($limit < 1) ? 1 : $limit;
}

// How many un-expired remember-me tokens the account currently holds.
function pg_auth_token_count($user_id)
{
    return (int) db_value(
        "SELECT COUNT(*) FROM auth_tokens WHERE user_id = '" . (int) $user_id . "' AND expires_at > '" . time() . "'");
}

// Would remembering this account on one more device pass its limit? Always
// false when the feature is off (limit 0), which is the default - so existing
// sites never reach the gate.
function pg_device_limit_exceeded($user_id)
{
    $limit = pg_device_limit();
    if ($limit <= 0) {
        return false;
    }
    return (pg_auth_token_count($user_id) >= $limit);
}

// Revoke the least-recently-active tokens until only $keep active ones remain,
// so a new device can be added without passing the limit. Ranks by the most
// recent touch (last use, or creation for a token never used) and keeps the
// freshest; returns how many were revoked.
function pg_device_limit_revoke_oldest($user_id, $keep)
{
    $user_id = (int) $user_id;
    $keep = max(0, (int) $keep);

    // Pinned sessions are counted (they are devices) but never evicted - that
    // is the whole point of pinning. So an account whose slots are all pinned
    // frees nothing here, and the caller has to notice that and refuse rather
    // than sign a second device in anyway.
    $pin_column = pg_auth_tokens_have_pin();

    $selectors = array();
    $result = mysqli_query(db::$con,
        "SELECT selector" . ($pin_column ? ", pinned" : "") . " FROM auth_tokens
         WHERE user_id = '" . $user_id . "' AND expires_at > '" . time() . "'
         ORDER BY GREATEST(last_used_at, created_at) DESC, created_at DESC");
    if ($result) {
        $index = 0;
        while ($r = mysqli_fetch_assoc($result)) {
            if (($index >= $keep) && (!$pin_column || (((int) $r['pinned']) === 0))) {
                $selectors[] = $r['selector'];
            }
            $index++;
        }
    }

    foreach ($selectors as $selector) {
        pg_auth_token_revoke($selector);
    }

    return count($selectors);
}

// The sign-in gate: call it where a remembered sign-in is about to create a
// token, before the session is set. When the account is over its device limit
// it stashes a short-lived pending sign-in and diverts to the confirmation
// screen without returning; otherwise it returns and the caller proceeds. A
// no-op unless a limit is set and already reached, so it is safe to place in
// every login flow.
function pg_device_limit_gate($user_id, $username, $send_to, $remember = false)
{
    if (!pg_device_limit_exceeded($user_id)) {
        return;
    }

    // Locked devices: no offer to sign the others out, because that offer is
    // exactly what makes a shared account workable - two people take turns
    // evicting each other and both keep using it. Here the credentials were
    // correct and the sign-in still does not happen; the way back in is to
    // sign out properly on a device that is already in, or to ask the site
    // owner, who can free a slot from the sessions screen.
    if (pg_device_limit_strict()) {
        log_activity(lang(array(
            'string' => 'sign-in refused because the account is at its device limit ({var:1})',
            'vars'   => array((string) $username))), (string) $username);

        output_error(lang('This account is already signed in on the maximum number of devices. Sign out on one of those devices and try again, or ask the site owner for help.'));
    }

    $_SESSION['software']['device_limit_pending'] = array(
        'user_id'  => (int) $user_id,
        'username' => (string) $username,
        'send_to'  => (string) $send_to,
        'remember' => $remember ? 1 : 0,
        'time'     => time(),
    );

    header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/device_limit.php');
    exit();
}

/**
 * Whether the CURRENT request actually arrived over HTTPS - directly, on the
 * standard TLS port, or behind a proxy/tunnel that says so (X-Forwarded-Proto,
 * e.g. Cloudflare Tunnel). Used for cookie Secure flags instead of URL_SCHEME:
 * URL_SCHEME is a static setting, and a Secure cookie set over a plain-http
 * request (localhost, LAN) is silently discarded by the browser - the sign-in
 * would look successful but the token cookie would never stick. Spoofing the
 * forwarded header gains an attacker nothing: the browser itself refuses to
 * store a Secure cookie received over plain http.
 */
function pg_request_is_https()
{
    if (!empty($_SERVER['HTTPS']) && (strtolower((string) $_SERVER['HTTPS']) !== 'off')) {
        return true;
    }

    if (isset($_SERVER['SERVER_PORT']) && (((int) $_SERVER['SERVER_PORT']) === 443)) {
        return true;
    }

    if (isset($_SERVER['HTTP_X_FORWARDED_PROTO'])
        && (strtolower(trim(strtok((string) $_SERVER['HTTP_X_FORWARDED_PROTO'], ','))) === 'https')) {
        return true;
    }

    return false;
}

// Non-remember logins get a session-scoped token only so they can be counted
// against the device limit; it dies with the browser and is swept afterwards.
function pg_device_limit_strict()
{
    // Only meaningful while the device limit is on: with no limit there is
    // nothing to be strict about.
    return ((pg_device_limit() > 0)
        && defined('REMEMBER_ME_DEVICE_LIMIT_STRICT')
        && (((int) REMEMBER_ME_DEVICE_LIMIT_STRICT) === 1));
}

function pg_device_session_ttl()
{
    // How long a sign-in WITHOUT "remember me" keeps its row in auth_tokens.
    //
    // It is not how long anyone stays signed in: that cookie is a session
    // cookie, so the browser drops it when it closes, whatever this says. What
    // this number decides is how long an abandoned row lingers - visible in
    // the session lists, and counted by the device limit - and how long a
    // browser that is never closed can go before its session ends.
    //
    // Set to match the remember-me lifetime, chosen deliberately over a short
    // sweep window: nobody is dropped mid-work, at the cost of closed browsers
    // still appearing as a device until the row expires. If those phantom rows
    // become a nuisance under a tight device limit, this is the one number to
    // lower - the device limit already revokes the oldest excess by itself, so
    // a stale row costs a confirmation screen, never a lockout.
    // Strict mode changes the arithmetic: a slot nobody can hand back is a
    // lockout waiting to happen, so a browser that closes without signing out
    // releases its slot in half a day instead of a month. The visitor who
    // simply went home gets in tomorrow without anyone's help; the account
    // being passed around does not.
    if (pg_device_limit_strict()) {
        return 43200; // 12 hours while devices are locked
    }

    return 2592000; // 30 days
}

// Create the sign-in token and set its cookie for a completed sign-in.
// Remembered logins get a persistent cookie and a full-lifetime token, as
// before. A non-remembered login gets nothing UNLESS the device limit is on -
// then it gets a session cookie (dies on browser close) and a short-lived token
// so it, too, counts as one device. With the limit off this is a no-op for
// non-remembered logins, so default behaviour is unchanged.
function pg_login_set_device_cookie($user_id, $remember)
{
    // Upgrade bridge: no auth_tokens table yet means no token to mint. The
    // sign-in still completes as a plain session; the caller that reads the
    // selector back (change_password.php) already treats an empty one as
    // "nothing to pin".
    if (!pg_auth_tokens_table_exists()) {
        return '';
    }

    $secure = pg_request_is_https();

    if ($remember) {
        $lifetime = pg_remember_me_lifetime();
        $cookie_expiry = time() + $lifetime;
    } else {
        // Every sign-in carries a token - device limit on or off - so every
        // session shows up in the session lists (my account, the admin
        // Sessions screen) and can be signed out remotely. Enforcement of the
        // LIMIT stays behind pg_device_limit(); this is only visibility and
        // revocability.
        $lifetime = pg_device_session_ttl();
        $cookie_expiry = 0; // session cookie
    }

    $auth_cookie = pg_auth_token_create($user_id, $lifetime);

    if (version_compare(PHP_VERSION, '5.2.0', '>=') == TRUE) {
        setcookie('software[auth]', $auth_cookie, $cookie_expiry, '/', '', $secure, true);
    } else {
        setcookie('software[auth]', $auth_cookie, $cookie_expiry, '/', '', $secure);
    }

    // The new selector, so a caller that had to replace a token (a password
    // change does) can carry a pin onto the replacement instead of dropping
    // the operator's decision on the floor.
    return strtok($auth_cookie, ':');
}

// ── Upgrade bridge ──────────────────────────────────────────────────────────
//
// Code lands before the database does. software_update.php swaps the files and
// only THEN sends the administrator to install/index.php to apply the schema;
// a site updated by hand may not even get that far. Between those two moments
// every request runs this release's code on the previous release's tables -
// and, for whoever was signed in, on the previous release's session shape.
//
// So the sign-in path, the installer's own lock and initialize_user() have to
// work on the previous schema and accept the previous session, because they
// are the only way to reach the upgrade that would make everything current.
// 2026.4.4 shipped without this once: a 4.3 site lost its session on the
// redirect (no sessionuserid in the old shape), the login then selected
// user_password_algo, and the site was locked out of itself.
//
// Rule for the code base: anything on that path that touches a column or
// table added by an unapplied migration goes through a probe first, the way
// pg_auth_tokens_have_pin() does. Everything behind the gate can assume the
// current schema.

// The newest version this code knows: the last line of
// includes/migrations/versions.php. Read with a regex rather than included,
// because that file exits unless the installer constant is defined.
function pg_code_version()
{
    static $version = null;

    if ($version === null) {
        $version = '';
        $list = @file_get_contents(PG_FUNCTIONS_DIR . '/includes/migrations/versions.php');

        if (is_string($list) && preg_match_all("/^\\s*'([0-9][0-9.]*)'\\s*,?\\s*$/m", $list, $found)) {
            $version = end($found[1]);
        }
    }

    return $version;
}

// True while the database (config.version, VERSION) is behind the code. False
// outside init.php (VERSION undefined - the installer runs functions.php
// without it) and false when the list cannot be read, so a broken read can
// never lock a healthy site into the installer.
function pg_schema_upgrade_pending()
{
    static $pending = null;

    if ($pending === null) {
        $code = pg_code_version();
        $pending = (defined('VERSION') && ($code !== '') && (version_compare((string) VERSION, $code, '<')));
    }

    return $pending;
}

// The gate. Every control-panel entry - validate_user(), the sign-in screen -
// calls this first: while the schema is behind, the visitor is sent to the
// upgrade instead of to code that would fail on the old tables. The installer
// then either recognises the session (see check_if_administrator_is_logged_in
// there, which accepts the previous release's shape) or asks for the password
// with a check that itself tolerates the old schema. Background calls get a
// 503 with a plain sentence rather than an HTML redirect they cannot follow.
function pg_require_current_schema()
{
    if (!pg_schema_upgrade_pending()) {
        return;
    }

    $is_background = (PHP_SAPI === 'cli')
        || (strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest')
        || defined('API_USERNAME');

    if ($is_background) {
        header('HTTP/1.1 503 Service Unavailable');
        header('Content-Type: text/plain; charset=utf-8');
        exit('The database has not been upgraded to this version yet. Open install/index.php to run the upgrade.');
    }

    // Plain install/index.php, not ?automated_upgrade=true: that flag answers a
    // visitor it cannot recognise with a bare 403, while the plain screen shows
    // the lock (or, for a session it does recognise, the upgrade itself).
    header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/install/index.php');
    exit();
}

// Does the user table carry user_password_algo yet? It arrives with a
// 2026.4.4 step; until then every row is a bare MD5 (algo 0). Asked once per
// request, and only on the bridge - after the gate the column is a given.
function pg_user_has_password_algo()
{
    static $has = null;

    if ($has === null) {
        $result = @mysqli_query(db::$con, "SHOW COLUMNS FROM user LIKE 'user_password_algo'");
        $has = ($result && (mysqli_num_rows($result) > 0));
    }

    return $has;
}

// Does the user table carry user_password_changed_at yet? It arrives with a
// 2026.4.4 step. Asked the same way as the algo column above, so a site running
// this code against an older schema writes the password and skips the stamp
// instead of failing the UPDATE.
function pg_user_has_password_changed_at()
{
    static $has = null;

    if ($has === null) {
        $result = @mysqli_query(db::$con, "SHOW COLUMNS FROM user LIKE 'user_password_changed_at'");
        $has = ($result && (mysqli_num_rows($result) > 0));
    }

    return $has;
}

// The SET fragment that stamps a password change, ready to drop into an UPDATE
// (leading comma, nothing when the column is missing). Only the paths that
// actually change the password use it: the rehash-on-verify path writes the same
// password in a stronger shape and must leave the date alone.
function pg_password_changed_sql()
{
    return pg_user_has_password_changed_at()
        ? ", user_password_changed_at = UNIX_TIMESTAMP()"
        : '';
}

// The columns the sign-in queries read, with or without the algo column, so
// validate_login(), the API sign-in and the installer's lock build the same
// SELECT and read the algo the same way (pg_password_row_algo()).
function pg_password_select_columns()
{
    return pg_user_has_password_algo()
        ? 'user_id, user_password, user_password_algo'
        : 'user_id, user_password';
}

function pg_password_row_algo($row)
{
    return (is_array($row) && isset($row['user_password_algo'])) ? (int) $row['user_password_algo'] : 0;
}

// The single entry point for every password check. Returns true/false, and on a
// correct password held in an older shape it upgrades the row in place - which
// is what migrates the whole member list without anyone noticing.
//
//   algo 0 legacy   bare MD5           -> compared, then rewritten as modern
//   algo 1 wrapped   password_hash(md5)-> md5 then verify, then rewritten
//   algo 2 modern    password_hash(pw) -> verify, rehashed if the cost moved
//   algo 3 external  no password       -> always false
function pg_password_verify($user_id, $password, $stored, $algo)
{
    $algo = (int) $algo;

    // No password on file can ever be signed into with one. Without this an
    // empty submitted password would meet an empty column on a Google-only
    // account and authenticate.
    if (($algo === 3) || ($stored === null) || ($stored === '')) {
        return false;
    }

    if ($algo === 0) {

        // hash_equals over the hex MD5, constant time, so a wrong password
        // cannot be told from a missing user by how long the compare takes.
        if (!hash_equals((string) $stored, md5((string) $password))) {
            return false;
        }

    } elseif ($algo === 1) {

        if (!password_verify(md5((string) $password), (string) $stored)) {
            return false;
        }

    } else {

        if (!password_verify((string) $password, (string) $stored)) {
            return false;
        }

        if (!password_needs_rehash((string) $stored, PASSWORD_DEFAULT)) {
            return true;
        }
    }

    // Correct password in an older or weaker shape: write the modern one now -
    // unless the column that records the shape is not there yet (bridge: the
    // upgrade has not run). Then the row is left as it is; the upgrade's
    // batch job rewrites it later, and a legacy MD5 keeps verifying meanwhile.
    if (pg_user_has_password_algo()) {
        pg_password_store($user_id, $password);
    }

    return true;
}

// Wrap a batch of still-legacy MD5 rows into password_hash(md5). Called from the
// general job once the verifier ships, never from the migration - wrapping a row
// while the verifier still checked WHERE user_password = md5(input) would lock it
// out the instant it was wrapped. Re-runnable: only rows still at algo 0 with a
// 32 character value are selected, and the UPDATE re-checks the marker so two
// overlapping runs cannot double-wrap.
function pg_password_wrap_batch($limit)
{
    $limit = (int) $limit;

    if ($limit <= 0) {
        return 0;
    }

    $rows = db_items("SELECT user_id, user_password FROM user
                      WHERE user_password_algo = 0 AND CHAR_LENGTH(user_password) = 32
                      ORDER BY user_id LIMIT " . $limit);

    if (!is_array($rows)) {
        return 0;
    }

    $wrapped = 0;

    foreach ($rows as $row) {

        db("UPDATE user
            SET user_password = '" . escape(password_hash($row['user_password'], PASSWORD_DEFAULT)) . "',
                user_password_algo = 1
            WHERE user_id = '" . (int) $row['user_id'] . "' AND user_password_algo = 0");

        $wrapped++;
    }

    return $wrapped;
}

// Wrap a batch of legacy rows on each general-job run, until none remain. Config
// gated so it costs one tiny query once the whole table is done. Called from
// job.php. Batches of 500 keep a single run's password_hash() cost bounded.
function pg_password_wrap_run()
{
    // Nothing to wrap into before the upgrade has added the columns; job.php
    // runs on the bridge like everything else.
    if (!pg_user_has_password_algo()) {
        return;
    }

    $row = db_item("SELECT password_wrap_done FROM config");

    if (!is_array($row) || ((int) $row['password_wrap_done'] === 1)) {
        return;
    }

    $wrapped = pg_password_wrap_batch(500);

    // A short batch means the legacy rows are exhausted; stop looking.
    if ($wrapped < 500) {
        db("UPDATE config SET password_wrap_done = 1");
    }
}

// ---- auth_tokens: the "remember me" store, done as selector:validator --------
//
// The cookie carries selector:validator; the table stores only
// sha256(validator). Reading the table therefore yields nothing that can be
// pasted into a cookie to sign in, unlike the old scheme where the stored
// password hash WAS the cookie. selector is looked up (indexed, not secret);
// validator is only ever compared as a hash, in constant time.

// Mint a token for a user, insert its row, and return the selector:validator
// string for the caller to put in the cookie.
function pg_auth_token_create($user_id, $lifetime_seconds)
{
    $selector  = bin2hex(random_bytes(12));   // 24 hex, matches CHAR(24)
    $validator = bin2hex(random_bytes(32));   // 64 hex

    $now = time();

    $user_agent = isset($_SERVER['HTTP_USER_AGENT'])
        ? mb_substr((string) $_SERVER['HTTP_USER_AGENT'], 0, 255)
        : '';

    $ip_address = function_exists('waf_client_ip')
        ? waf_client_ip()
        : (isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '');

    db("INSERT INTO auth_tokens
            (selector, validator_hash, user_id, expires_at, created_at, last_used_at, user_agent, ip_address)
        VALUES
            ('" . escape($selector) . "',
             '" . escape(hash('sha256', $validator)) . "',
             '" . (int) $user_id . "',
             '" . ($now + (int) $lifetime_seconds) . "',
             '" . $now . "',
             '0',
             '" . escape($user_agent) . "',
             '" . escape($ip_address) . "')");

    return $selector . ':' . $validator;
}

/**
 * Does auth_tokens carry the "pinned" column yet? It arrives with migration
 * 4.17, and every query that mentions it would be a fatal error on a database
 * that has not been upgraded, so each one asks here first. Answered once per
 * request - this is on the sign-in path.
 */
// Is the auth_tokens table there at all? It arrives with 2026.4.4; on the
// upgrade bridge (this code, the previous schema) a sign-in must still work
// without it - as a plain session, the way the previous release signed in.
function pg_auth_tokens_table_exists()
{
    static $exists = null;

    if ($exists === null) {
        $result = @mysqli_query(db::$con, "SHOW TABLES LIKE 'auth_tokens'");
        $exists = ($result && (mysqli_num_rows($result) > 0));
    }

    return $exists;
}

function pg_auth_tokens_have_pin()
{
    static $have = null;

    if ($have === null) {
        $result = @mysqli_query(db::$con, "SHOW COLUMNS FROM auth_tokens LIKE 'pinned'");
        $have = ($result && (mysqli_num_rows($result) > 0));
    }

    return $have;
}

/**
 * Is this session pinned? A pinned session survives everything the member can
 * do from their own screens and everything the device limit does on its own;
 * only an operator (or a deliberate sign-out on that very device) releases it.
 */
function pg_auth_token_pinned($selector)
{
    if (!pg_auth_tokens_have_pin()) {
        return false;
    }

    return (((int) db_value("SELECT pinned FROM auth_tokens WHERE selector = '" . escape($selector) . "'")) === 1);
}

// Revoke every token a user holds - what a password change and a "sign out
// everywhere" both call. Its single-selector sibling lives in
// includes/authentication.php with the rest of the token primitives.
function pg_auth_token_revoke_user($user_id, $respect_pin = false)
{
    $sql = "DELETE FROM auth_tokens WHERE user_id = '" . (int) $user_id . "'";

    if ($respect_pin && pg_auth_tokens_have_pin()) {
        $sql .= " AND pinned = 0";
    }

    db($sql);
}

// Probabilistic cleanup of expired rows, the same one-in-two-hundred approach
// waf_rate_sweep() uses, so no cron job is needed.
function pg_auth_token_sweep()
{
    if (mt_rand(1, 200) !== 1) {
        return;
    }

    db("DELETE FROM auth_tokens WHERE expires_at < " . time() . " LIMIT 5000");
}

// The username property can either be a username or email address.
/**
 * Case-folding for address comparison, multibyte-aware where the extension is
 * there. One function so the block list, its editor and any future caller fold
 * the same way - two different foldings would silently disagree about whether
 * an address is blocked.
 */
function pg_email_fold($value)
{
    $value = (string) $value;

    return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
}

/**
 * Is this address on the block list?
 *
 * Removing someone's Google connection does not stop them connecting it again,
 * and deleting an account does not stop the same address signing up again -
 * this list is what does. An entry is either a whole address
 * ("someone@example.com") or a domain ("example.com", "@example.com",
 * "*@example.com"), which is where the list earns its keep: disposable-mail
 * domains arrive as an endless supply of fresh addresses.
 *
 * Honest about its limits: it stops a known offender and known domains, not a
 * determined stranger who can make a new mailbox in a minute. Rate limiting,
 * the captcha and the firewall are what work against volume.
 */
function pg_email_blocked($email)
{
    // mb_strtolower, not strtolower: an address written with Turkish or any
    // accented letters would otherwise only match if the operator typed it in
    // exactly the same case, which is not what "blocked" is supposed to mean.
    $email = pg_email_fold(trim((string) $email));

    if (($email === '') || !defined('BANNED_EMAIL_ADDRESSES') || (BANNED_EMAIL_ADDRESSES === '')) {
        return false;
    }

    $at = strrpos($email, '@');
    $domain = ($at === false) ? '' : substr($email, $at);   // keeps the "@"

    foreach (preg_split('/[\r\n,;]+/', (string) BANNED_EMAIL_ADDRESSES) as $entry) {
        $entry = pg_email_fold(trim($entry));
        $entry = ltrim($entry, '*');

        if ($entry === '') {
            continue;
        }

        // A bare domain is written either way round; normalise to "@domain".
        if (strpos($entry, '@') === false) {
            $entry = '@' . $entry;
        }

        if (strpos($entry, '@') === 0) {
            if (($domain !== '') && ($domain === $entry)) {
                return true;
            }
        } elseif ($entry === $email) {
            return true;
        }
    }

    return false;
}

function validate_login($username, $password)
{
    // $password is now the RAW password, not an MD5. The stored hash is checked
    // in PHP by pg_password_verify(), which also upgrades a legacy or wrapped row
    // to the modern hash on a correct password - the mechanism that migrates the
    // member list one sign-in at a time.
    //
    // Returns the user id (truthy) so a caller can open a session, or false. The
    // id compares the same as the old boolean did against == true / == false, so
    // existing callers keep working; the sign-in flows read the id.
    //
    // The algo column is asked for only when it exists (upgrade bridge, see
    // pg_user_has_password_algo()); a row without it is a legacy MD5 row.
    $row = db_item("SELECT " . pg_password_select_columns() . ", user_email, user_role
        FROM user
        WHERE
            (
                (user_username = '" . escape($username) . "')
                OR (user_email = '" . escape($username) . "')
            )
        LIMIT 1");

    if (!is_array($row) || !isset($row['user_id'])) {
        return false;
    }

    // A blocked address cannot sign in either - blocking someone who already
    // has an account would be half a measure otherwise.
    //
    // Members only (role 3). An operator who mistypes their own address into
    // the block list would otherwise lock themselves out of the screen that
    // holds the list, and there is no way back in from there. Staff accounts
    // are stopped by deleting or demoting them, which already exists.
    if ((((int) $row['user_role']) === 3) && pg_email_blocked($row['user_email'])) {
        log_activity(lang(array(
            'string' => 'sign-in refused because the email address is blocked ({var:1})',
            'vars'   => array($row['user_email']))), '');
        return false;
    }

    if (pg_password_verify($row['user_id'], $password, $row['user_password'], pg_password_row_algo($row))) {
        return (int) $row['user_id'];
    }

    return false;
}

// True when this session holds a signed-in user that still exists. Replaces the
// old pg_session_signed_in()
// page guard: the session id is the credential now (it is server side and cannot
// be forged from the browser), so no password is re-checked - only that the user
// row is still there, which catches an account deleted under a live session.
function pg_session_signed_in()
{
    if (!isset($_SESSION['sessionuserid']) || ($_SESSION['sessionuserid'] === '')) {
        return false;
    }

    return (bool) db_value("SELECT user_id FROM user
        WHERE user_id = '" . (int) $_SESSION['sessionuserid'] . "' LIMIT 1");
}
// The username can either be a username or email address for a user.
function validate_username($username)
{
    $query = "SELECT user_id FROM user WHERE (user_username = '" . escape($username) . "') OR (user_email = '" . escape($username) . "')";
    $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed'));
    if (mysqli_num_rows($result) > 0) {
        return true;
    } else {
        return false;
    }
}
// checks that user is logged in and returns array with user information
function validate_user()
{
    // Schema behind the code: to the upgrade, before anything below runs on
    // tables that are not there yet. See pg_require_current_schema().
    pg_require_current_schema();

    // The session id is the credential now, so there is no password in the
    // session to re-check. No signed-in user -> send to the login screen.
    if ((!isset($_SESSION['sessionuserid'])) || ($_SESSION['sessionuserid'] === '')) {
        include_once('liveform.class.php');
        $liveform = new liveform('login');
        $liveform->mark_error('', lang('You have not logged in, or your session has expired. Please login.'));

        header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/index.php?send_to=' . urlencode(get_request_uri()));
        exit();
    }
    $query = "SELECT * FROM user WHERE user_id = '" . (int) $_SESSION['sessionuserid'] . "'";
    $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed'));
    $row = mysqli_fetch_array($result);
    $num_rows = mysqli_num_rows($result);
    // The account was deleted out from under a live session: clear it and send
    // back to the login screen, the same end state the old invalid-login branch
    // reached.
    if ($num_rows == 0) {
        $logged_in_as_different_user = $_SESSION['software']['logged_in_as_different_user'] ?? false;
        session_unset();
        session_destroy();
        if ($logged_in_as_different_user == false) {
            setcookie('software[auth]', '', time() - 1000, '/');
        }
        output_error('<a href="javascript:history.go(-1)">' . lang('Invalid login') . '</a>');
    }
    // initialize variables
    $user_id = $row['user_id'];
    $user_username = $row['user_username'];
    $user_email = $row['user_email'];
    $user_password = $row['user_password'];
    $user_role = $row['user_role'];
    $user_manage_contacts = $row['user_manage_contacts'];
    $user_manage_visitors = $row['user_manage_visitors'];
    $user_manage_ecommerce = $row['user_manage_ecommerce'];
    $user_view_card_data = $row['user_view_card_data'];
    $manage_ecommerce_reports = $row['manage_ecommerce_reports'];
    $manage_erp = $row['manage_erp'] ?? 0;
    $manage_erp_cash = $row['manage_erp_cash'] ?? 0;
    $manage_erp_settings = $row['manage_erp_settings'] ?? 0;
    $user_set_offline_payment = $row['user_set_offline_payment'];
    $user_create_pages = $row['user_create_pages'];
    $user_delete_pages = $row['user_delete_pages'];
    $user_manage_forms = $row['user_manage_forms'];
    $user_manage_calendars = $row['user_manage_calendars'];
    $user_publish_calendar_events = $row['user_publish_calendar_events'];
    $user_manage_emails = $row['user_manage_emails'];
    $user_home = $row['user_home'];
    $user_set_page_type_email_a_friend = $row['user_set_page_type_email_a_friend'];
    $user_set_page_type_folder_view = $row['user_set_page_type_folder_view'];
    $user_set_page_type_photo_gallery = $row['user_set_page_type_photo_gallery'];
    $user_set_page_type_custom_form = $row['user_set_page_type_custom_form'];
    $user_set_page_type_custom_form_confirmation = $row['user_set_page_type_custom_form_confirmation'];
    $user_set_page_type_form_list_view = $row['user_set_page_type_form_list_view'];
    $user_set_page_type_form_item_view = $row['user_set_page_type_form_item_view'];
    $user_set_page_type_form_view_directory = $row['user_set_page_type_form_view_directory'];
    $user_set_page_type_calendar_view = $row['user_set_page_type_calendar_view'];
    $user_set_page_type_calendar_event_view = $row['user_set_page_type_calendar_event_view'];
    $user_set_page_type_catalog = $row['user_set_page_type_catalog'];
    $user_set_page_type_catalog_detail = $row['user_set_page_type_catalog_detail'];
    $user_set_page_type_express_order = $row['user_set_page_type_express_order'];
    $user_set_page_type_order_form = $row['user_set_page_type_order_form'];
    $user_set_page_type_shopping_cart = $row['user_set_page_type_shopping_cart'];
    $user_set_page_type_shipping_address_and_arrival = $row['user_set_page_type_shipping_address_and_arrival'];
    $user_set_page_type_shipping_method = $row['user_set_page_type_shipping_method'];
    $user_set_page_type_billing_information = $row['user_set_page_type_billing_information'];
    $user_set_page_type_order_preview = $row['user_set_page_type_order_preview'];
    $user_set_page_type_order_receipt = $row['user_set_page_type_order_receipt'];
    $user_reward_points = $row['user_reward_points'];
    if ($user_manage_contacts == 'yes') {
        $user_manage_contacts = true;
    } else {
        $user_manage_contacts = false;
    }
    if ($user_manage_visitors == 'yes') {
        $user_manage_visitors = true;
    } else {
        $user_manage_visitors = false;
    }
    if ($user_manage_ecommerce == 'yes') {
        $user_manage_ecommerce = true;
    } else {
        $user_manage_ecommerce = false;
    }
    if ($user_manage_forms == 'yes') {
        $user_manage_forms = true;
    } else {
        $user_manage_forms = false;
    }
    if ($user_manage_calendars == 'yes') {
        $user_manage_calendars = true;
    } else {
        $user_manage_calendars = false;
    }
    if ($user_publish_calendar_events == 'yes') {
        $user_publish_calendar_events = true;
    } else {
        $user_publish_calendar_events = false;
    }
    if ($user_manage_emails == 'yes') {
        $user_manage_emails = true;
    } else {
        $user_manage_emails = false;
    }
    // convert integer database values to boolean values for newer fields
    settype($user_view_card_data, 'boolean');
    settype($manage_ecommerce_reports, 'boolean');
    settype($manage_erp, 'boolean');
    settype($manage_erp_cash, 'boolean');
    settype($manage_erp_settings, 'boolean');
    settype($user_set_offline_payment, 'boolean');
    settype($user_create_pages, 'boolean');
    settype($user_delete_pages, 'boolean');
    settype($user_set_page_type_email_a_friend, 'boolean');
    settype($user_set_page_type_folder_view, 'boolean');
    settype($user_set_page_type_photo_gallery, 'boolean');
    settype($user_set_page_type_custom_form, 'boolean');
    settype($user_set_page_type_custom_form_confirmation, 'boolean');
    settype($user_set_page_type_form_list_view, 'boolean');
    settype($user_set_page_type_form_item_view, 'boolean');
    settype($user_set_page_type_form_view_directory, 'boolean');
    settype($user_set_page_type_calendar_view, 'boolean');
    settype($user_set_page_type_calendar_event_view, 'boolean');
    settype($user_set_page_type_catalog, 'boolean');
    settype($user_set_page_type_catalog_detail, 'boolean');
    settype($user_set_page_type_express_order, 'boolean');
    settype($user_set_page_type_order_form, 'boolean');
    settype($user_set_page_type_shopping_cart, 'boolean');
    settype($user_set_page_type_shipping_address_and_arrival, 'boolean');
    settype($user_set_page_type_shipping_method, 'boolean');
    settype($user_set_page_type_billing_information, 'boolean');
    settype($user_set_page_type_order_preview, 'boolean');
    settype($user_set_page_type_order_receipt, 'boolean');
    $user = array(
        "id" => $user_id,
        "username" => $user_username,
        "email_address" => $user_email,
        "role" => $user_role,
        "manage_contacts" => $user_manage_contacts,
        "manage_visitors" => $user_manage_visitors,
        "manage_ecommerce" => $user_manage_ecommerce,
        "view_card_data" => $user_view_card_data,
        "manage_ecommerce_reports" => $manage_ecommerce_reports,
        "manage_erp" => $manage_erp,
        "manage_erp_cash" => $manage_erp_cash,
        "manage_erp_settings" => $manage_erp_settings,
        "set_offline_payment" => $user_set_offline_payment,
        "create_pages" => $user_create_pages,
        "delete_pages" => $user_delete_pages,
        "manage_forms" => $user_manage_forms,
        "manage_calendars" => $user_manage_calendars,
        "publish_calendar_events" => $user_publish_calendar_events,
        "manage_emails" => $user_manage_emails,
        "home" => $user_home,
        'set_page_type_email_a_friend' => $user_set_page_type_email_a_friend,
        'set_page_type_folder_view' => $user_set_page_type_folder_view,
        'set_page_type_photo_gallery' => $user_set_page_type_photo_gallery,
        'set_page_type_custom_form' => $user_set_page_type_custom_form,
        'set_page_type_custom_form_confirmation' => $user_set_page_type_custom_form_confirmation,
        'set_page_type_form_list_view' => $user_set_page_type_form_list_view,
        'set_page_type_form_item_view' => $user_set_page_type_form_item_view,
        'set_page_type_form_view_directory' => $user_set_page_type_form_view_directory,
        'set_page_type_calendar_view' => $user_set_page_type_calendar_view,
        'set_page_type_calendar_event_view' => $user_set_page_type_calendar_event_view,
        'set_page_type_catalog' => $user_set_page_type_catalog,
        'set_page_type_catalog_detail' => $user_set_page_type_catalog_detail,
        'set_page_type_express_order' => $user_set_page_type_express_order,
        'set_page_type_order_form' => $user_set_page_type_order_form,
        'set_page_type_shopping_cart' => $user_set_page_type_shopping_cart,
        'set_page_type_shipping_address_and_arrival' => $user_set_page_type_shipping_address_and_arrival,
        'set_page_type_shipping_method' => $user_set_page_type_shipping_method,
        'set_page_type_billing_information' => $user_set_page_type_billing_information,
        'set_page_type_order_preview' => $user_set_page_type_order_preview,
        'set_page_type_order_receipt' => $user_set_page_type_order_receipt,
        "reward_points" => $user_reward_points,
        // Which guided tours this person has already watched.  The column is
        // read straight off the row that was fetched anyway, so a screen can
        // ask the question without a query of its own.  Whether the column is
        // there at all comes from the same row: a column that does not exist
        // cannot come back as a key, which saves pg_tour_ready() a SHOW
        // COLUMNS on every screen that carries a tour.
        'tours_seen' => (isset($row['tours_seen']) ? (string) $row['tours_seen'] : ''),
        'tours_ready' => (is_array($row) && array_key_exists('tours_seen', $row))
    );
    return ($user);
}

// check that user has access to area of software
function validate_area_access($user, $minimum_role)
{
    // convert role description into number
    switch ($minimum_role) {
        case 'administrator':
            $minimum_role_num = 0;
            break;
        case 'designer':
            $minimum_role_num = 1;
            break;
        case 'manager':
            $minimum_role_num = 2;
            break;
        case 'user':
            $minimum_role_num = 3;
            break;
    }
    // if user has a user role and does not have edit rights -> output error and return false
    if (($user['role'] == 3) && (no_acl_check($user['id']) == false)) {
        log_activity(lang('access denied because user had no edit rights'), $_SESSION['sessionusername']);
        output_error(lang('Access denied') . '. <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.');
        return false;
    }
    // if user's role is high enough, then user has access -> return true
    if ($user['role'] <= $minimum_role_num) {
        return true;
        // else user's role is not high enough -> output error and return false
    } else {
        log_activity(lang('access denied because user attempted to access area for higher role'), $_SESSION['sessionusername']);
        output_error(lang('Access denied') . '. <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.');
        return false;
    }
}
function validate_contact_access($user, $contact_id)
{
    // if user has a role greater than user role, user has access, so return true
    if ($user['role'] < 3) {
        return true;
        // else user does not have a role greater than user role, so do further checks to see if user has access
    } else {
        // get all contact groups that contact is in
        $query = "SELECT contact_group_id as id

            FROM contacts_contact_groups_xref

            WHERE contact_id = '" . escape($contact_id) . "'";
        $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
        $contact_groups = array();
        // loop through all contact groups in order to add them to array
        while ($row = mysqli_fetch_assoc($result)) {
            $contact_groups[] = $row;
        }
        // loop through all contact groups in order to determine if user has access to contact group
        foreach ($contact_groups as $contact_group) {
            // if user has access to contact group, then user has access to contact, so return true
            if (validate_contact_group_access($user, $contact_group['id'])) {
                return true;
            }
        }
        // we have not returned true by now, so user does not have access to contact, so return false
        return false;
    }
}
function validate_contacts_access($user, $only_return = false)
{
    // if user has a role greater than user role, return true
    if ($user['role'] < 3) {
        return true;
        // else if user has access to manage contacts
    } elseif ($user['manage_contacts'] == true) {
        // check if user has access to at least one contact group
        $query = "SELECT count(*)

            FROM users_contact_groups_xref

            WHERE user_id = '" . escape($user['id']) . "'";
        $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
        $row = mysqli_fetch_row($result);
        // if user has access to at least one contact group, return true
        if ($row[0] > 0) {
            return true;
            // else user does not have access to at least one contact group
        } else {
            if ($only_return == false) {
                log_activity(lang('access denied to contacts because user did not have access to at least one contact group'), $_SESSION['sessionusername']);
                output_error(lang('Access denied') . '. ' . lang('You do not have access to contacts because you do not have access to at least one contact group') . '. <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.');
            }
            return false;
        }
        // else user does not have access, so return false
    } else {
        if ($only_return == false) {
            log_activity(lang('access denied to contacts'), $_SESSION['sessionusername']);
            output_error(lang('Access denied') . '. <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.');
        }
        return false;
    }
}
function validate_contact_group_access($user, $contact_group_id)
{
    // if user has a role greater than user role, user has access, so return true
    if ($user['role'] < 3) {
        return true;
        // else user does not have a role greater than user role, so do further checks to see if user has access
    } else {
        // check to see if user has access to contact group
        $query = "SELECT user_id

            FROM users_contact_groups_xref

            WHERE

                (user_id = '" . escape($user['id']) . "')

                AND (contact_group_id = '" . escape($contact_group_id) . "')";
        $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
        // if user has access to contact group, return true
        if (mysqli_num_rows($result) > 0) {
            return true;
            // else user does not have access to contact group, so return false
        } else {
            return false;
        }
    }
}
/**
 * Gate for the ERP screens.
 *
 * Two questions, answered in order: is the module switched on for this site at
 * all, and may this person see it. A module that is off gets the operator sent
 * to the setting that turns it on rather than a flat refusal - refusing to
 * explain why a screen is missing is how a feature gets reported as broken.
 *
 * @param array  $user
 * @param string $area  '', 'cash' or 'settings'
 * @return bool
 */
function validate_erp_access($user, $area = '')
{
    if (!defined('ERP_ENABLED') || !ERP_ENABLED) {
        $liveform_url = OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/' . pg_settings_return_url('commerce', 'pgset-erp');
        output_error(lang('The ERP module is not switched on.') . ' <a href="' . h($liveform_url) . '">' . lang('Settings') . '</a>');
        return false;
    }

    $allowed = ($user['role'] < 3) || !empty($user['manage_erp']);

    if ($allowed && ($area === 'cash')) {
        $allowed = ($user['role'] < 3) || !empty($user['manage_erp_cash']);
    }

    if ($allowed && ($area === 'settings')) {
        $allowed = ($user['role'] < 3) || !empty($user['manage_erp_settings']);
    }

    if ($allowed) {
        return true;
    }

    log_activity(lang('access denied to erp'), $_SESSION['sessionusername']);
    output_error(lang('Access denied') . '. <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.');

    return false;
}

function validate_ecommerce_access($user)
{
    // if user has a role greater than user role OR user has access to manage e-commerce, return true
    if ($user['role'] < 3 || $user['manage_ecommerce'] == true) {
        return true;
        // else user does not have access, so return false
    } else {
        log_activity(lang('access denied to commerce'), $_SESSION['sessionusername']);
        output_error(lang('Access denied') . '. <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.');
        return false;
    }
}
// Used by the order report & shipping report screens to verify that the user
// has access.
function validate_ecommerce_report_access()
{
    if (!USER_LOGGED_IN or ((USER_ROLE == 3) and !USER_MANAGE_ECOMMERCE_REPORTS)) {
        log_activity(lang('access denied to commerce reports'));
        output_error(lang('Access denied') . '.');
    }
}
function validate_forms_access($user)
{
    // if user has a role greater than user role OR user has access to manage forms, return true
    if ($user['role'] < 3 || $user['manage_forms'] == true) {
        return true;
        // else user does not have access, so return false
    } else {
        log_activity(lang('access denied to forms'), $_SESSION['sessionusername']);
        output_error(lang('Access denied') . '. <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.');
        return false;
    }
}
function validate_visitors_access($user)
{
    // if user has a role greater than user role OR user has access to manage visitors, return true
    if ($user['role'] < 3 || $user['manage_visitors'] == true) {
        return true;
        // else user does not have access, so return false
    } else {
        output_error(lang('Access denied') . '. <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.');
        return false;
    }
}
function validate_calendars_access($user, $only_return = false)
{
    // if user has a role greater than user role, return true
    if ($user['role'] < 3) {
        return true;
        // else if user has access to manage calendars
    } elseif ($user['manage_calendars'] == true) {
        // check if user has access to at least one calendar
        $query = "SELECT count(*)

            FROM users_calendars_xref

            WHERE user_id = '" . escape($user['id']) . "'";
        $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
        $row = mysqli_fetch_row($result);
        // if user has access to at least one calendar, return true
        if ($row[0] > 0) {
            return true;
            // else user does not have access to at least one calendar
        } else {
            if ($only_return == false) {
                log_activity(lang('access denied to calendars because user did not have access to at least one calendar'), $_SESSION['sessionusername']);
                output_error(lang('Access denied') . '. ' . lang('You do not have access to calendars because you do not have access to at least one calendar.') . '<a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.');
            }
            return false;
        }
        // else user does not have access, so return false
    } else {
        if ($only_return == false) {
            log_activity(lang('access denied to calendars'), $_SESSION['sessionusername']);
            output_error(lang('Access denied') . '. <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.');
        }
        return false;
    }
}
function validate_calendar_access($calendar_id)
{
    // if user has a role greater than user role, user has access, so return true
    if (USER_ROLE < 3) {
        return true;
        // else user does not have a role greater than user role, so do further checks to see if user has access
    } else {
        // check to see if user has access to calendar
        $query = "SELECT user_id

            FROM users_calendars_xref

            WHERE

                (user_id = '" . USER_ID . "')

                AND (calendar_id = '" . escape($calendar_id) . "')";
        $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
        // if user has access to calendar, return true
        if (mysqli_num_rows($result) > 0) {
            return true;
            // else user does not have access to calendar, so return false
        } else {
            return false;
        }
    }
}
function validate_calendar_event_access($calendar_event_id)
{
    // if user has a role greater than user role, user has access, so return true
    if (USER_ROLE < 3) {
        return true;
        // else user does not have a role greater than user role, so do further checks to see if user has access
    } else {
        // get all calendars that this event is in
        $query = "SELECT calendar_id

            FROM calendar_events_calendars_xref

            WHERE calendar_event_id = '" . escape($calendar_event_id) . "'";
        $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
        $selected_calendars = array();
        // loop through all selected calendars in order to build array
        while ($row = mysqli_fetch_assoc($result)) {
            $selected_calendars[] = $row['calendar_id'];
        }
        // loop through all selected calendars in order to determine if user has access to any calendars that this calendar event is in
        foreach ($selected_calendars as $calendar_id) {
            // if user has access to calendar, then user has access to calendar event, so return true
            if (validate_calendar_access($calendar_id) == true) {
                return true;
            }
        }
        // if we have gotten here then the user does not have access to any calendars that calendar event is in, so return false
        return false;
    }
}
function validate_email_access($user)
{
    // if user has a role greater than user role OR user has access to manage emails, return true
    if ($user['role'] < 3 || $user['manage_emails'] == true) {
        return true;
        // else user does not have access, so return false
    } else {

        log_activity(lang('access denied to e-mail page'), $_SESSION['sessionusername']);
        output_error(lang('Access denied') . '. <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.');
        return false;
    }
}
function check_folder_access_in_array($folder_id, $folders_that_user_has_access_to)
{
    global $user;
    // if user has a role that is greater than a basic user or user has access to folder, then return true
    if (((isset($user['role']) == true) && ($user['role'] < 3)) || (in_array($folder_id, $folders_that_user_has_access_to) == true)) {
        return true;
        // else user does not have access
    } else {
        return false;
    }
}
function get_folders_that_user_has_access_to($user_id)
{
    // get folders with access set that user has access to
    $folders_with_access_set = array();
    $query = "SELECT aclfolder_folder as folder_id

        FROM aclfolder

        WHERE

            (aclfolder_user = '" . escape($user_id) . "')

            AND (aclfolder_rights = '2')";
    $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
    while ($row = mysqli_fetch_assoc($result)) {
        $folders_with_access_set[] = $row['folder_id'];
    }
    // get all folders
    $folders = array();
    $query = "SELECT

            folder_id as id,

            folder_parent as parent_folder_id

        FROM folder";
    $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
    while ($row = mysqli_fetch_assoc($result)) {
        $folders[] = $row;
    }
    $folders_that_user_has_access_to = array();
    // loop through all folders with access set
    foreach ($folders_with_access_set as $folder_id) {
        // add folder to folders that user has access to
        $folders_that_user_has_access_to[] = $folder_id;
        // add child folders to folders that user has access to
        $folders_that_user_has_access_to = array_merge($folders_that_user_has_access_to, get_child_folders($folder_id, $folders));
    }
    // remove duplicate folders from array
    $folders_that_user_has_access_to = array_unique($folders_that_user_has_access_to);
    return $folders_that_user_has_access_to;
}
function get_child_folders($folder_id, $folders)
{
    $child_folders = array();
    // loop through all folders
    foreach ($folders as $folder) {
        // if folder is a child, then add folder to array
        if ($folder['parent_folder_id'] == $folder_id) {
            $child_folders[] = $folder['id'];
            $child_folders = array_merge($child_folders, get_child_folders($folder['id'], $folders));
        }
    }
    return $child_folders;
}

// Determine whether this server hosts the site.
// Checks hostname(s) and server IP against known values.
// Compatible with PHP 7.0 - 8.5.

function we_host()
{
    // Known hostnames / IPs for this server
    $known_hosts = array(
        'cpls43.srvpanel.com',
        '94.73.151.12'
    );
    $candidates = array();
    // gethostname() available in PHP 5.3+
    if (function_exists('gethostname')) {
        $candidates[] = gethostname();
    }
    // php_uname('n') returns node name
    $candidates[] = function_exists('php_uname') ? @php_uname('n') : ''; // disable_functions on some hosts
    // common server vars
    if (!empty($_SERVER['SERVER_NAME'])) {
        $candidates[] = preg_replace('/:\d+$/', '', $_SERVER['SERVER_NAME']);
    }
    if (!empty($_SERVER['HTTP_HOST'])) {
        $candidates[] = preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST']);
    }
    if (!empty($_SERVER['SERVER_ADDR'])) {
        $server_addr = $_SERVER['SERVER_ADDR'];
    } else {
        $server_addr = null;
    }
    // normalize and filter candidates
    $candidates = array_filter(array_map('strtolower', array_unique($candidates)));
    // direct hostname check
    foreach ($candidates as $c) {
        foreach ($known_hosts as $known) {
            if (filter_var($known, FILTER_VALIDATE_IP)) {
                // skip IP here
                continue;
            }
            if ($c !== '' && stripos($c, strtolower($known)) !== false) {
                return true;
            }
        }
    }
    // check server address directly against known IPs
    if ($server_addr) {
        foreach ($known_hosts as $known) {
            if (filter_var($known, FILTER_VALIDATE_IP) && $server_addr === $known) {
                return true;
            }
        }
        // check reverse DNS for SERVER_ADDR
        $rdns = @gethostbyaddr($server_addr);
        if ($rdns && stripos($rdns, 'cpls43.srvpanel.com') !== false) {
            return true;
        }
    }
    // resolve known hostnames and compare to server IP (if available)
    if ($server_addr) {
        foreach ($known_hosts as $known) {
            if (!filter_var($known, FILTER_VALIDATE_IP)) {
                $resolved = @gethostbyname($known);
                if ($resolved && $resolved !== $known && $resolved === $server_addr) {
                    return true;
                }
            }
        }
    }
    return false;
}


// determine what access control type a folder has
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
        $result = mysqli_query(db::$con, "SELECT folder_id, folder_parent, folder_access_control_type FROM folder")
            or output_error(lang('Query failed'));
        while ($r = mysqli_fetch_assoc($result)) {
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
function get_access_control_type_name($access_control_type)
{
    switch ($access_control_type) {
        case 'public':
            return lang('Public');
            break;
        case 'guest':
            return lang('Guest');
            break;
        case 'private':
            return lang('Private');
            break;
        case 'registration':
            return lang('Registration');
            break;
        case 'membership':
            return lang('Membership');
            break;
    }
}
// You can pass the access control type in order to limit the folders to those with that type of access control.
function select_folder($folder_id = 0, $parent_folder_id = 0, $excluded_folder_id = 0, $level = 0, $folders = array(), $folders_that_user_has_access_to = array(), $access_control_type = '')
{
    global $user;
    $output = '';
    // if this is the first time this function has run, then get folders and folders that user has has access to
    if ($parent_folder_id == 0) {
        // get all folders
        $query = "SELECT
                folder_id as id,
                folder_name as name,
                folder_parent as parent_folder_id,
                folder_archived as archived,
                folder_access_control_type as folder_access_control
            FROM folder

            ORDER BY folder_level, folder_order, folder_name";
        $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
        $recycle_bin_folder_id = pg_recycle_bin_folder_id();
        while ($row = mysqli_fetch_assoc($result)) {
            // The Recycle Bin is not a destination. Skipping its row also
            // skips everything inside it: a binned folder is only reachable
            // through the bin as its parent.
            if (($recycle_bin_folder_id > 0) && ($row['id'] == $recycle_bin_folder_id)) {
                continue;
            }
            $folders[] = $row;
        }
        // if user is a basic user, then get folders that user has access to
        if ($user['role'] == 3) {
            $folders_that_user_has_access_to = get_folders_that_user_has_access_to($user['id']);
        }
    }
    $child_folders = array();
    // loop through folders array in order to get all folders that are in parent folder
    foreach ($folders as $folder) {
        // if the parent folder id for this folder is equal to the parent folder id, then this is a child folder, so add to array
        if ($folder['parent_folder_id'] == $parent_folder_id) {
            $child_folders[] = $folder;
        }
    }

    // loop through child folders
    foreach ($child_folders as $folder) {
        // if folder id is not equal to excluded folder id, then continue to prepare option and get child folders
        if ($folder['id'] != $excluded_folder_id) {
            // If user has access to folder, or if the folder is the selected folder,
            // and the access control type is valid, then output option for folder.
            $next_level = 0;
            if (((check_folder_access_in_array($folder['id'], $folders_that_user_has_access_to) == true) || ($folder['id'] == $folder_id)) && (($access_control_type == '') || ($access_control_type == get_access_control_type($folder['id'])))) {
                // prepare indentation
                $indentation = '';
                for ($i = 1; $i <= $level; $i++) {
                    $indentation .= '&nbsp;&nbsp;';
                }
                $next_level = $level + 1;
                // if this folder is the selected folder, then this option should be selected
                if ($folder['id'] == $folder_id) {
                    $selected = ' selected';
                } else {
                    $selected = '';
                }
                $archived_label = '';
                // if this folder is archived, then output archived beside the folder name
                if ($folder['archived'] == '1') {
                    $archived_label = ' [' . lang('ARCHIVED') . ']';
                }
                $output_folder_class = '';
                if ($folder['folder_access_control']) {
                    $output_folder_class = 'class="' . $folder['folder_access_control'] . '"';
                }
                // output option row
                $output .= '<option value="' . $folder['id'] . '"' . $selected . ' ' . $output_folder_class . '>' . $indentation . h($folder['name'] . $archived_label) . '</option>';
            }
            // get options for child folders
            $output .= select_folder($folder_id, $folder['id'], $excluded_folder_id, $next_level, $folders, $folders_that_user_has_access_to, $access_control_type);
        }
    }
    return $output;
}
// output selection field for selecting access control for a folder
function select_access_control_type($access_control_type = '', $default = true)
{
    $output = '';
    if ($default == true) {
        $output .= '<option value="">' . lang('Default') . ' (' . lang('inherit') . ')</option>';
    }
    // let's list access control types
    $access_control_types[] = 'Public';
    $access_control_types[] = 'Guest';
    $access_control_types[] = 'Registration';
    $access_control_types[] = 'Membership';
    $access_control_types[] = 'Private';
    foreach ($access_control_types as $label => $value) {
        // if this access control type is the selected access control type, select this option
        if ((mb_strtolower($value) == mb_strtolower($access_control_type))) {
            $selected = ' selected="selected"';
        } else {
            $selected = '';
        }
        $output .= '<option value="' . mb_strtolower($value) . '"' . $selected . ' class="' . mb_strtolower($value) . '">' . lang($value) . '</option>';
    }
    return $output;
}
// outputs the form's <select> style list for selecting a style
// this function will only get non-mobile styles
function select_style($style = '', $default_label = 'Default (inherit)')
{
    $output = '';

    $output .= '<option value="0">' . h(lang($default_label)) . '</option>';
    // Visual-editor designs are not offered: a design belongs to the pages
    // it was built with, and assigning it to a folder (or to a page made in
    // the page screen) would send those pages to the visual editor with no
    // layout of their own. The one currently stored is kept in the list so
    // an existing choice is not silently dropped on save.
    $result = mysqli_query(db::$con,
        "SELECT style_id, style_name FROM style
         WHERE style_layout != 'one_column_mobile'
           AND (style_layout != 'visual_designer' OR style_id = '" . escape((string)$style) . "')
         ORDER BY style_name") or output_error(lang('Query failed'));
    while ($row = mysqli_fetch_assoc($result)) {
        // find if style is selected
        if ($row['style_id'] == $style) {
            $selected = ' selected';
        } else {
            $selected = '';
        }
        // output option row
        $output .= '<option value="' . $row['style_id'] . '"' . $selected . '>' . h($row['style_name']) . '</option>';
    }
    return $output;
}
// create function that will get options for the mobile style pick list
function get_mobile_style_options($mobile_style_id = '', $default_label = 'Default (inherit)')
{
    $output = '<option value="0">' . h(lang($default_label)) . '</option>';
    $sql_mobile_style_filter = "";
    // If mobile is enabled in the site settings,
    // then only get styles that can be mobile styles.
    if (MOBILE == true) {
        $sql_mobile_style_filter = "WHERE (style_type = 'custom') OR (style_layout = 'one_column_mobile')";
    }
    // get all styles that might be mobile styles
    $query = "SELECT

            style_id AS id,

            style_name AS name

        FROM style

        $sql_mobile_style_filter

        ORDER BY style_name ASC";
    $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
    $mobile_styles = mysqli_fetch_items($result);
    // loop through the mobile styles in order to prepare options
    foreach ($mobile_styles as $mobile_style) {
        // if this mobile style is the selected mobile style, then select it
        if ($mobile_style['id'] == $mobile_style_id) {
            $selected = ' selected="selected"';
            // else this mobile style is not the selected mobile style, so do not select it
        } else {
            $selected = '';
        }
        $output .= '<option value="' . $mobile_style['id'] . '"' . $selected . '>' . h($mobile_style['name']) . '</option>';
    }
    return $output;
}
// returns content for column header depending on asc or desc
function asc_or_desc($column, $type, $extras = '')
{
    $sort = isset($_GET['sort']) ? $_GET['sort'] : '';
    $order = isset($_GET['order']) ? $_GET['order'] : '';

    if ($sort == $column) {
        if ($order == 'desc') {
            return ("<a href=\"$type.php?sort=$column&order=asc$extras\" class=\"link-top-row btn btn-sm\">$column</a> <span class=\"material-icons\">keyboard_arrow_down</span>");
        } else {
            return ("<a href=\"$type.php?sort=$column&order=desc$extras\" class=\"link-top-row btn btn-sm\">$column</a> <span class=\"material-icons\">keyboard_arrow_up</span>");
        }
    } else {
        return ("<a href=\"$type.php?sort=$column$extras\" class=\"link-top-row btn btn-sm\">$column</a>");
    }
}
// return content for column heading
function get_column_heading($column, $sort, $order, $extras = '')
{
    if ($column == $sort) {
        if ($order == 'asc') {
            return ('<a href="' . h($_SERVER["PHP_SELF"]) . '?sort=' . h(urlencode($column)) . '&order=desc' . $extras . '" class="link-top-row btn btn-sm">' . h($column) . '</a> <span class="material-icons">keyboard_arrow_up</span></a>');
        } else {
            return ('<a href="' . h($_SERVER["PHP_SELF"]) . '?sort=' . h(urlencode($column)) . '&order=asc' . $extras . '" class="link-top-row btn btn-sm">' . h($column) . '</a> <span class="material-icons">keyboard_arrow_down</span></a>');
        }
    } else {
        return ('<a href="' . h($_SERVER["PHP_SELF"]) . '?sort=' . h(urlencode($column)) . '&order=asc' . $extras . '" class="link-top-row btn btn-sm">' . h($column) . '<span class="material-icons">unfold_more</span></a>');
    }
}
function get_acl_folder_tree($type, $parent_folder_id = 0, $level = 0, $folders = array(), $folders_that_user_has_access_to = array(), $user_id = '')
{
    $output = '';
    // if this is the first time this function has run, then get folders
    if ($parent_folder_id == 0) {
        // get all folders
        $query = "SELECT

                folder_id as id,

                folder_name as name,

                folder_parent as parent_folder_id

            FROM folder

            ORDER BY folder_level, folder_order, folder_name";
        $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
        $recycle_bin_folder_id = pg_recycle_bin_folder_id();
        while ($row = mysqli_fetch_assoc($result)) {
            // Rights are never granted over the Recycle Bin; skipping its row
            // hides the binned subtree with it.
            if (($recycle_bin_folder_id > 0) && ($row['id'] == $recycle_bin_folder_id)) {
                continue;
            }
            $folders[] = $row;
        }
    }
    $child_folders = array();
    // loop through folders array in order to get all folders that are in parent folder
    foreach ($folders as $folder) {
        // if the parent folder id for this folder is equal to the parent folder id, then this is a child folder, so add to array
        if ($folder['parent_folder_id'] == $parent_folder_id) {
            $child_folders[] = $folder;
        }
    }
    // loop through child folders
    foreach ($child_folders as $folder) {
        // prepare indentation
        $indentation = '';
        for ($i = 1; $i <= $level; $i++) {
            $indentation .= '&nbsp;&nbsp;&nbsp;&nbsp;';
        }
        $next_level = $level + 1;
        // if type is edit or type is view and folder is private, then output line for this folder
        if (($type == 'edit') || (($type == 'view') && (get_access_control_type($folder['id']) == 'private'))) {
            $checked = '';
            // if the user has access to this folder, then prepare to check check box
            if (in_array($folder['id'], $folders_that_user_has_access_to) == true) {
                $checked = ' checked="checked"';
            }
            $output_onclick = '';
            $output_expiration_date_container = '';
            // If the type if view, then output expiration date field.
            if ($type == 'view') {
                // No onclick: the expiry line follows the checkbox's state, and
                // backend.src.js syncs it. An inline handler would miss every
                // programmatic change ("select all" is one) and would be a
                // second mechanism doing the same job.
                $output_onclick = '';
                // Assume that the expiration date field should not be shown until we find out otherwise.
                $output_expiration_date_container_style = ' style="display: none"';
                $output_expiration_date = '';
                $output_expiration_date_style = '';
                $output_expiration_date_picker = '';
                // If the check box is checked, then show expiration date field and get expiration date if one exists.
                if ($checked != '') {
                    $output_expiration_date_container_style = '';
                    // Get expiration date if one exists.
                    $expiration_date = db_value("SELECT expiration_date

                        FROM aclfolder

                        WHERE

                            (aclfolder_user = '" . escape($user_id) . "')

                            AND (aclfolder_folder = '" . $folder['id'] . "')

                            AND (aclfolder_rights = '1')");
                    // If an expiration date was found, then prepare to prefill field with value.
                    if ($expiration_date != '0000-00-00') {
                        $output_expiration_date = prepare_form_data_for_output($expiration_date, 'date');
                        // If the expiration date has expired, then make expiration date red.
                        if ($expiration_date < date('Y-m-d')) {
                            $output_expiration_date_style = '; color: red';
                        }
                    }
                    $output_expiration_date_picker = '<script>

                            $("#view_' . $folder['id'] . '_expiration_date").datepicker(datetimepicker_options);

                        </script>';
                }
                // Own line under the folder it belongs to, rather than trailing
                // the label: the label is as wide as the folder name plus its
                // indentation, so an inline field wraps and lands under the
                // checkbox column at any narrow width. Sizing lives in
                // backend.src.css (.pg-acl-expiry); the only inline style left
                // is the red an expired date carries, which onfocus clears.
                // Two states in one box. Closed it is a small button at the end
                // of the folder's own line, so a grant with no expiry costs no
                // height - which matters after "select all", where every row
                // would otherwise grow an empty date field. Open it wraps onto
                // its own line and shows the field. The input is in the DOM
                // either way, so it posts whatever it holds; backend.src.js
                // opens the box for a folder that already has a date.
                $output_expiration_date_container = '<span class="pg-acl-expiry" id="view_' . $folder['id'] . '_expiration_date_container"' . $output_expiration_date_container_style . '>'
                    . '<button type="button" class="pg-acl-expiry-add" aria-expanded="false"><i class="bi bi-calendar-plus"></i><span>' . h(lang('Add an expiry')) . '</span></button>'
                    . '<span class="pg-acl-expiry-field">'
                        . '<span class="pg-acl-expiry-label">' . lang('Expiration Date') . '</span>'
                        . '<input type="text" class="form-control form-control-sm pg-acl-expiry-input" id="view_' . $folder['id'] . '_expiration_date" name="view_' . $folder['id'] . '_expiration_date" value="' . $output_expiration_date . '" maxlength="10"' . (($output_expiration_date_style != '') ? ' style="color: red"' : '') . ' onfocus="this.style.color = \'\'" />'
                        . '<button type="button" class="pg-acl-expiry-clear" title="' . h(lang('Remove the expiry')) . '"><i class="bi bi-x-lg"></i></button>'
                    . '</span>'
                    . $output_expiration_date_picker . '</span>';
            }
            $output .= '<div class="form-check"><input type="checkbox" name="' . $type . '_' . $folder['id'] . '" id="' . $type . '_' . $folder['id'] . '" value="1" class="form-check-input  multiselect-checkbox"' . $checked . $output_onclick . ' /><label class="form-check-label" for="' . $type . '_' . $folder['id'] . '">' . $indentation . h($folder['name']) . '</label>' . $output_expiration_date_container . '</div>';
        }
        // get lines for child folders
        $output .= get_acl_folder_tree($type, $folder['id'], $next_level, $folders, $folders_that_user_has_access_to, $user_id);
    }
    return $output;
}
// find if there is an entry for the user in the access control list to edit any folder
function no_acl_check($user_id)
{
    $result = mysqli_query(db::$con, "SELECT aclfolder_user FROM aclfolder WHERE aclfolder_user = '" . escape($user_id) . "' AND aclfolder_rights = '2'") or output_error(lang('Query failed'));
    if (mysqli_num_rows($result)) {
        return true;
    } else {
        return false;
    }
}
function validate_password_strength($password)
{
    // if there are less than 10 characters then return false
    if (mb_strlen($password) < 10) {
        return false;
    }
    // if there is not a capital letter, then return false
    if (preg_match('/[A-Z]/', $password) == 0) {
        return false;
    }
    // if there are less than 2 numbers, then return false
    if (preg_match_all('/[0-9]/', $password, $matches) < 2) {
        return false;
    }
    // if there is not a non-alphanumeric character, then return false
    if (preg_match('/[^A-Za-z0-9]/', $password) == 0) {
        return false;
    }
    // if we have gotten here, then the password is strong so return true
    return true;
}
function get_strong_password_requirements()
{
    return '
    <div>' . lang('Your password must contain at least') . ':</div>
    <ul>
        <li>' . lang(array('string' => '{var:1} characters', 'vars' => '10')) . '</li>
        <li>' . lang(array('string' => '{var:1} capital letter', 'vars' => '1')) . '</li>
        <li>' . lang(array('string' => '{var:1} numbers', 'vars' => '2')) . '</li>
        <li>' . lang(array('string' => '{var:1} non-alphanumeric character (e.g. !, $)', 'vars' => '1')) . '</li>
    </ul>';
}
// Create function that will be used to get a fake answer to store in a hidden form field
// in order to trick spammers.
function get_fake_captcha_answer($real_answer)
{
    // Get random fake answer between 0 and 18.
    $fake_answer = rand(0, 18);
    // If the fake answer happens to be the real answer, then get different fake answer.
    if ($fake_answer == $real_answer) {
        return get_fake_captcha_answer($real_answer);
        // Else the fake answer is not the real answer, so use it.
    } else {
        return $fake_answer;
    }
}
function get_captcha_fields($liveform)
{
    if (!CAPTCHA) {
        return '';
    }
    $output_captcha_fields = '';
    // if the user is not logged in then show the captcha form
    if (isset($_SESSION['sessionusername']) == false) {
        // randomly generate the numbers to add and the question to be asked
        $first_number = rand(0, 9);
        $second_number = rand(0, 9);
        // Prepare an encypted version of the correct answer.
        // The encrypted format contains a random digit at the beginning and the end with a value in the middle
        // that is 2 higher than the correct value, and all of that base64 encoded.
        $correct_answer_encrypted = base64_encode(rand(0, 9) . ($first_number + $second_number + 2) . rand(0, 9));
        $liveform->assign_field_value('captcha_validation', $correct_answer_encrypted);
        // store incorrect, fake answer in hidden form field
        $liveform->assign_field_value('captcha_correct_answer', get_fake_captcha_answer($first_number + $second_number));
        // set the value for the submitted answer field to blank
        $liveform->assign_field_value('captcha_submitted_answer', '');
        // The body field is a honeypot field which we will use to determine if the submitter is a bot.
        // The captcha_correct_answer field actually contains a fake, incorrect answer.
        $captcha_label_box = '<div class="software_captcha_label" style="font-weight: bold; margin-bottom: .5em">' . lang('To prevent spam, please tell us') . ':</div>';
        $captcha_question_box = '<span class="software_captcha_question"> ' . lang(array('string' => 'What is {var:1} + {var:2} ?', 'vars' => array($first_number, $second_number))) . '</span>';
        $output_captcha_fields = '<div class="captcha">

                <div style="display: none">' . $liveform->output_field(array(
                        'type' => 'textarea',
                        'name' => 'body',
                        'cols' => '30',
                        'rows' => '4',
                        'class' => 'software_textarea'
                    )) . '</div>

                ' . $liveform->output_field(array(
                        'type' => 'hidden',
                        'name' => 'captcha_correct_answer'
                    )) . '

                ' . $liveform->output_field(array(
                        'type' => 'hidden',
                        'name' => 'captcha_validation'
                    )) . $captcha_label_box . $captcha_question_box . $liveform->output_field(array(
                        'type' => 'number',
                        'name' => 'captcha_submitted_answer',
                        'maxlength' => '2',
                        'size' => '2',
                        'required' => 'true',
                        'min' => '0',
                        'max' => '18',
                        'class' => 'software_input_text software_captcha_answer'
                    )) . '

            </div>';
    }
    return $output_captcha_fields;
}
// This is a slightly different version from the function above
// that is used by custom layouts to display captcha info.
// It is different from the function above because it does not
// output the captcha heading or question, because the custom layout will contain that.
function get_captcha_info($form)
{
    // If the CAPTCHA is disabled in the settings or the user is logged in,
    // then don't show a CAPTCHA.
    if (!CAPTCHA || USER_LOGGED_IN) {
        return false;
    }
    // randomly generate the numbers to add and the question to be asked
    $first_number = rand(0, 9);
    $second_number = rand(0, 9);
    // Prepare an encypted version of the correct answer.
    // The encrypted format contains a random digit at the beginning and the end with a value in the middle
    // that is 2 higher than the correct value, and all of that base64 encoded.
    $correct_answer_encrypted = base64_encode(rand(0, 9) . ($first_number + $second_number + 2) . rand(0, 9));
    $form->assign_field_value('captcha_validation', $correct_answer_encrypted);
    // store incorrect, fake answer in hidden form field
    $form->assign_field_value('captcha_correct_answer', get_fake_captcha_answer($first_number + $second_number));
    $question = lang(array('string' => 'What is {var:1} + {var:2} ?', 'vars' => array($first_number, $second_number)));
    // set the value for the submitted answer field to blank
    $form->assign_field_value('captcha_submitted_answer', '');
    $form->set('captcha_submitted_answer', 'maxlength', 2);
    $form->set('captcha_submitted_answer', 'required', true);
    // The body field is a honeypot field which we will use to determine if the submitter is a bot.
    // The captcha_correct_answer field actually contains a fake, incorrect answer.
    $system = '<div style="display: none">

            <textarea name="body" cols="30" rows="4"></textarea>

        </div>

        <input type="hidden" name="captcha_correct_answer">

        <input type="hidden" name="captcha_validation">';
    return array(
        'question' => $question,
        'system' => $system
    );
}
function validate_captcha_answer($liveform)
{
    // If the visitor is not logged in then make sure that he/she answered the CAPTCHA correctly.
    if (isset($_SESSION['sessionusername']) == false) {
        // If the honeypot field was filled out, then that means the submitter is a bot,
        // because the field is hidden with CSS, so add error.
        if ($liveform->get_field_value('body') != '') {
            $liveform->mark_error('captcha_submitted_answer', lang('Sorry, the answer that you entered for the question is not valid. Please try again.'));
            // Else the honeypot field was not filled out, so determine if the answer is correct.
        } else {
            // check to see if there is a submitted answer
            $liveform->validate_required_field('captcha_submitted_answer', lang('You must answer the question before continuing. Please try again.'));
            // If there is not already an error for the answer field, then determine if answer is correct.
            if ($liveform->check_field_error('captcha_submitted_answer') == false) {
                // Start off decrypting the correct answer by base64 decoding the encrypted answer.
                $correct_answer_decrypted = base64_decode($liveform->get_field_value('captcha_validation'));
                // Remove the first character which is garbage.
                $correct_answer_decrypted = mb_substr($correct_answer_decrypted, 1);
                // Remove the last character which is also garbage.
                $correct_answer_decrypted = mb_substr($correct_answer_decrypted, 0, -1);
                // Subtract the remaining value by 2 to get the correct answer.
                $correct_answer_decrypted = $correct_answer_decrypted - 2;
                // If the answer that the visitor submitted is incorrect, then add error
                if ($liveform->get_field_value('captcha_submitted_answer') != $correct_answer_decrypted) {
                    $liveform->mark_error('captcha_submitted_answer', lang('The answer you entered for the question was incorrect. Please try again.'));
                    // Otherwise the answer is correct, so if a blacklist is enabled,
                    // then determine if IP address is on a blacklist and should be rejected.
                    // We do this check after verifying that the captcha answer is correct,
                    // in order to minimize requests to stopforumspam.com.
                } else if (defined('BLACKLIST') and BLACKLIST) {
                    $response = decode_json(@file_get_contents('http://www.stopforumspam.com/api?ip=' . urlencode($_SERVER['REMOTE_ADDR']) . '&f=json'));
                    // If there was a communication error with the blacklist service, then log that.
                    if ((isset($response['success']) == false) || ($response['success'] == false)) {
                        $error = '';
                        // If blacklist service returned an error, then include that info in log message.
                        if ($response['error'] != '') {
                            $error = ' (' . $response['error'] . ')';
                        }
                        log_activity(lang(array('string' => 'an error occurred when communicating with the spam protection service {var:1}, so visitor\'s request was accepted', 'vars' => array($error))), $_SESSION['sessionusername']);
                        // Otherwise there was not a communication error with the blacklist service,
                        // so if the visitor is on the blacklist, then log activity and add error.
                    } else if ($response['ip']['appears'] == true) {
                        log_activity(lang(array('string' => 'visitor\'s request was denied because the spam protection service reported that visitor\'s IP address was used by spammers. (confidence: {var:1}%, frequency: {var:2})', 'vars' => array(round($response['ip']['confidence']), number_format($response['ip']['frequency'])))), $_SESSION['sessionusername']);
                        $liveform->mark_error('', lang('Sorry, we were not able to accept your request.'));
                    }
                }
            }
        }
    }
}
// create function to detect and set device type (i.e. desktop or mobile) in session and cookie if it has not already been done
function initialize_device_type()
{
    // if the device type (desktop or mobile) for this visitor's session is not known yet,
    // then get device type
    if (isset($_SESSION['software']['device_type']) == false) {
        // If mobile site setting is disabled,
        // or if this script is being run by the update search index process,
        // then don't detect device type and just set it to desktop.
        if ((MOBILE == false) || (defined('UPDATE_SEARCH_INDEX') and UPDATE_SEARCH_INDEX)) {
            $_SESSION['software']['device_type'] = 'desktop';
            // Otherwise detect device type.
        } else {
            // if the visitor's device type is stored in a cookie, then use that
            if (isset($_COOKIE['software']['device_type']) == true) {
                $_SESSION['software']['device_type'] = $_COOKIE['software']['device_type'];
                // else the visitor's device type is not stored in a cookie, so determine if we should detect it
            } else {
                // if the device type is mobile (based on the user agent), then set that in the session
                // we got the following code from detectmobilebrowsers.com
                if ((isset($_SERVER['HTTP_USER_AGENT']) == true) && ((preg_match('/android.+mobile|avantgo|bada\/|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|symbian|treo|up\.(browser|link)|vodafone|wap|windows (ce|phone)|xda|xiino/i', $_SERVER['HTTP_USER_AGENT'])) || (preg_match('/1207|6310|6590|3gso|4thp|50[1-6]i|770s|802s|a wa|abac|ac(er|oo|s\-)|ai(ko|rn)|al(av|ca|co)|amoi|an(ex|ny|yw)|aptu|ar(ch|go)|as(te|us)|attw|au(di|\-m|r |s )|avan|be(ck|ll|nq)|bi(lb|rd)|bl(ac|az)|br(e|v)w|bumb|bw\-(n|u)|c55\/|capi|ccwa|cdm\-|cell|chtm|cldc|cmd\-|co(mp|nd)|craw|da(it|ll|ng)|dbte|dc\-s|devi|dica|dmob|do(c|p)o|ds(12|\-d)|el(49|ai)|em(l2|ul)|er(ic|k0)|esl8|ez([4-7]0|os|wa|ze)|fetc|fly(\-|_)|g1 u|g560|gene|gf\-5|g\-mo|go(\.w|od)|gr(ad|un)|haie|hcit|hd\-(m|p|t)|hei\-|hi(pt|ta)|hp( i|ip)|hs\-c|ht(c(\-| |_|a|g|p|s|t)|tp)|hu(aw|tc)|i\-(20|go|ma)|i230|iac( |\-|\/)|ibro|idea|ig01|ikom|im1k|inno|ipaq|iris|ja(t|v)a|jbro|jemu|jigs|kddi|keji|kgt( |\/)|klon|kpt |kwc\-|kyo(c|k)|le(no|xi)|lg( g|\/(k|l|u)|50|54|e\-|e\/|\-[a-w])|libw|lynx|m1\-w|m3ga|m50\/|ma(te|ui|xo)|mc(01|21|ca)|m\-cr|me(di|rc|ri)|mi(o8|oa|ts)|mmef|mo(01|02|bi|de|do|t(\-| |o|v)|zz)|mt(50|p1|v )|mwbp|mywa|n10[0-2]|n20[2-3]|n30(0|2)|n50(0|2|5)|n7(0(0|1)|10)|ne((c|m)\-|on|tf|wf|wg|wt)|nok(6|i)|nzph|o2im|op(ti|wv)|oran|owg1|p800|pan(a|d|t)|pdxg|pg(13|\-([1-8]|c))|phil|pire|pl(ay|uc)|pn\-2|po(ck|rt|se)|prox|psio|pt\-g|qa\-a|qc(07|12|21|32|60|\-[2-7]|i\-)|qtek|r380|r600|raks|rim9|ro(ve|zo)|s55\/|sa(ge|ma|mm|ms|ny|va)|sc(01|h\-|oo|p\-)|sdk\/|se(c(\-|0|1)|47|mc|nd|ri)|sgh\-|shar|sie(\-|m)|sk\-0|sl(45|id)|sm(al|ar|b3|it|t5)|so(ft|ny)|sp(01|h\-|v\-|v )|sy(01|mb)|t2(18|50)|t6(00|10|18)|ta(gt|lk)|tcl\-|tdg\-|tel(i|m)|tim\-|t\-mo|to(pl|sh)|ts(70|m\-|m3|m5)|tx\-9|up(\.b|g1|si)|utst|v400|v750|veri|vi(rg|te)|vk(40|5[0-3]|\-v)|vm40|voda|vulc|vx(52|53|60|61|70|80|81|83|85|98)|w3c(\-| )|webc|whit|wi(g |nc|nw)|wmlb|wonu|x700|xda(\-|2|g)|yas\-|your|zeto|zte\-/i', mb_substr($_SERVER['HTTP_USER_AGENT'], 0, 4))))) {
                    $_SESSION['software']['device_type'] = 'mobile';
                    // else the device type is desktop so set that in the session
                } else {
                    $_SESSION['software']['device_type'] = 'desktop';
                }
                // store the device type in a cookie for 10 years
                // so that we can remember the device type for the next visit
                setcookie('software[device_type]', $_SESSION['software']['device_type'], time() + 315360000, '/');
            }
        }
    }
}
// Create function that will generate a token and add it to a visitor's session if it has not already been done,
// in order to prevent CSRF attacks.
function initialize_token()
{
    // If a token has not been generated for this visitor yet,
    // then generate token and set it in session.
    if (isset($_SESSION['software']['token']) == false) {
        if (function_exists('random_bytes')) {
            $_SESSION['software']['token'] = bin2hex(random_bytes(32));
        } elseif (function_exists('openssl_random_pseudo_bytes')) {
            $_SESSION['software']['token'] = bin2hex(openssl_random_pseudo_bytes(32));
        } else {
            $_SESSION['software']['token'] = md5(uniqid(mt_rand(), true));
        }
    }
}
// Create function in order to get hidden form field with a token in order to prevent CSRF attacks.
// This is used in almost all post requests.
function get_token_field()
{
    return '<input type="hidden" name="token" value="' . $_SESSION['software']['token'] . '">';
}
// Create function in order to get query string field with a token in order to prevent CSRF attacks.
// This is used in get requests that change things.
function get_token_query_string_field()
{
    return '&amp;token=' . ($_SESSION['software']['token'] ?? '');
}
// Create function in order to validate the passed token in order to prevent CSRF attacks.
// This is used for almost all post requests and some get requests that change things.
function validate_token_field()
{
    // If the token does not exist in the session,
    // or the passed token does not match the token from the session,
    // then this might be a CSRF attack so output error.
    // We don't log activity anymore for this because the log was getting filled up with these messages
    // when we tried to log it in the past.  Innocent activities (e.g. session expired, web crawlers, spammers)
    // and not CSRF attacks generate most of these errors, so it is not important to log them.
    $post_token = isset($_POST['token']) ? $_POST['token'] : '';
    $get_token = isset($_GET['token']) ? $_GET['token'] : '';
    if ((($_SESSION['software']['token'] ?? '') == '') || (($post_token != ($_SESSION['software']['token'] ?? '')) && ($get_token != ($_SESSION['software']['token'] ?? '')))) {
        // If the visitor has cookies disabled, then output unique error for that condition.
        if (!isset($_COOKIE[session_name()])) {
            output_error(lang(array('string' => 'Sorry, we could not accept your request, because it appears that cookies are disabled in your web browser. Please enable cookies and then {var:1} and try again. If the problem persists then you might need to refresh the page, after you go back.', 'vars' => '<a href="javascript:history.go(-1)">' . lang('go back') . '</a>')));
            // Otherwise cookies are enabled, but the token just does not match
            // so output error for that condition.
        } else {
            output_error(lang(array('string' => 'Sorry, we could not accept your request because it appears that your session expired or you logged out. We recommend that you {var:1} and try again. If the problem persists then you might need to refresh the page, after you go back. This issue might happen if you logged out in a different browser tab or window, or cleared your browser cookies.', 'vars' => '<a href="javascript:history.go(-1)">' . lang('go back') . '</a>')));

        }
    }
}
// Create function to handle remember me and set user information if user is logged in.
function initialize_user()
{
    // Migration from the pre-token session format. A session opened before this
    // release carries sessionusername (and the old sessionpassword) but no
    // sessionuserid, so it can no longer be validated - which showed up as a
    // half-signed-in state: the menu still printed the name while the guards
    // rejected it. Clear just those keys once (cart, kiosk and the rest of the
    // session stay), so the visitor drops to the login screen cleanly and signs
    // in again, exactly the one-time re-login the changelog describes.
    //
    // Not while the database is still behind the code: those two keys are then
    // exactly what carries the administrator across software_update.php's
    // redirect into the installer, which accepts the previous release's session
    // shape (check_if_administrator_is_logged_in() there). Clearing them here
    // would drop the one session that can run the upgrade.
    if (isset($_SESSION['sessionusername'])
        && !isset($_SESSION['sessionuserid'])
        && !defined('API_USERNAME')
        && !pg_schema_upgrade_pending()) {
        unset($_SESSION['sessionusername']);
        unset($_SESSION['sessionpassword']);
    }

    // Same migration on the cookie side. A pre-token release left the old
    // remember-me pair (software[username] / software[password]) in the browser;
    // nothing reads it now, but software[password] is the password hash sitting
    // in the browser, so expire both once. software[auth] (the new token) and
    // software[remember_me] (a preference) are left alone.
    //
    // Again not while the database is behind the code: an administrator whose
    // session is gone but whose browser still holds that pair is exactly who
    // has to reach the upgrade, and the installer accepts the pair the way the
    // previous release did (check_if_administrator_is_logged_in() there). The
    // pair expires on the first request after the schema is current.
    if ((isset($_COOKIE['software']['password']) || isset($_COOKIE['software']['username']))
        && !pg_schema_upgrade_pending()) {
        setcookie('software[username]', '', time() - 1000, '/');
        setcookie('software[password]', '', time() - 1000, '/');
        unset($_COOKIE['software']['username']);
        unset($_COOKIE['software']['password']);
    }

    // Token-bound session. A signed-in session that carries a software[auth]
    // token must be backed by a live auth_tokens row: a row that is gone was
    // revoked - another device's confirm, a password change, sign-out from the
    // device list, an administrator - and the session ends NOW, with or
    // without the device limit; that is what makes a revoke authoritative.
    // While the device limit is on, two more rules apply: a session with no
    // token at all (signed in before the limit was switched on, or its cookie
    // is gone) ends too, because it would neither count toward the limit nor
    // be revocable; and a live token no longer among the account's newest
    // <limit> (the limit was lowered, or a sign-in outside the gate pushed
    // the account over) is revoked here - the oldest excess device signs
    // itself out on its own next request. A token merely close to expiring
    // while its session is active is slid forward, so an active visitor is
    // never swept out from under themselves.
    //
    // Upgrade bridge: none of this while the auth_tokens table is not there.
    // That is not only the moment right after a file update - a database
    // restored from a backup taken before this release meets a browser that
    // still holds this release's session and cookie (seen on a site
    // reinstalled at 2026.2.x under current code: every page died here, in the
    // one function every request passes through). Without the table the
    // session is simply a session, the way the previous release treated it.
    if (isset($_SESSION['sessionuserid']) && $_SESSION['sessionuserid']
        && (!defined('UPDATE_SEARCH_INDEX') || UPDATE_SEARCH_INDEX !== true)
        && pg_auth_tokens_table_exists()) {

        $auth_bind_kick = false;
        $auth_bind_reason = '';

        if (isset($_COOKIE['software']['auth'])) {
            $auth_bind_parts = explode(':', (string) $_COOKIE['software']['auth'], 2);
            $auth_bind_selector = isset($auth_bind_parts[0]) ? $auth_bind_parts[0] : '';

            if (($auth_bind_selector !== '') && preg_match('/^[a-f0-9]{24}$/', $auth_bind_selector)) {
                $auth_bind_row = db_item(
                    "SELECT user_id, expires_at, GREATEST(last_used_at, created_at) AS freshness
                    FROM auth_tokens
                    WHERE selector = '" . escape($auth_bind_selector) . "'");

                if (is_array($auth_bind_row) && isset($auth_bind_row['expires_at'])
                    && ($auth_bind_row['expires_at'] !== '') && ($auth_bind_row['expires_at'] !== null)) {

                    if (pg_device_limit() > 0) {
                        // Rank this token among the account's live tokens: how
                        // many are strictly fresher (ties broken by selector so
                        // exactly one order exists)? If at least <limit> are
                        // fresher, this device is the oldest excess - revoke it
                        // and kick below.
                        $auth_bind_fresher = (int) db_value(
                            "SELECT COUNT(*)
                            FROM auth_tokens
                            WHERE user_id = '" . ((int) $auth_bind_row['user_id']) . "'
                                AND expires_at > '" . time() . "'
                                AND (GREATEST(last_used_at, created_at) > '" . ((int) $auth_bind_row['freshness']) . "'
                                    OR (GREATEST(last_used_at, created_at) = '" . ((int) $auth_bind_row['freshness']) . "'
                                        AND selector > '" . escape($auth_bind_selector) . "'))");

                        if ($auth_bind_fresher >= pg_device_limit()) {
                            pg_auth_token_revoke($auth_bind_selector);
                            $auth_bind_kick = true;
                            $auth_bind_reason = 'over the device limit';
                        }
                    }

                    if ($auth_bind_kick === false) {
                        if ((((int) $auth_bind_row['expires_at']) - time()) < 21600) {
                            // Slide a token close to expiring, stamping the
                            // activity while at it.
                            db("UPDATE auth_tokens SET expires_at = '" . (time() + pg_device_session_ttl()) . "', last_used_at = '" . time() . "' WHERE selector = '" . escape($auth_bind_selector) . "'");
                        } elseif ((time() - ((int) $auth_bind_row['freshness'])) > 50) {
                            // "Last seen": stamp activity at the same cadence
                            // who_is_online() uses for the per-user stamp (50
                            // seconds), so the session lists show when each
                            // device really was last active - and the device
                            // limit's oldest-excess choice follows real
                            // activity rather than sign-in order.
                            db("UPDATE auth_tokens SET last_used_at = '" . time() . "' WHERE selector = '" . escape($auth_bind_selector) . "'");
                        }
                    }
                } else {
                    // The token was revoked: fatal with or without the limit.
                    $auth_bind_kick = true;
                    $auth_bind_reason = 'session token was revoked';
                }
            } elseif (pg_device_limit() > 0) {
                // Malformed token cookie: unbound session, not allowed while a
                // device limit is in force.
                $auth_bind_kick = true;
                $auth_bind_reason = 'malformed session token';
            }
        } elseif (pg_device_limit() > 0) {
            // No token at all: unbound session, not allowed while a device
            // limit is in force.
            $auth_bind_kick = true;
            $auth_bind_reason = 'session carried no device token';
        } else {
            // No token and no limit: a session from before universal binding.
            // Bind it quietly so it appears in the session lists and can be
            // signed out remotely; from the next request on it is a normal
            // token-bound session.
            pg_login_set_device_cookie((int) $_SESSION['sessionuserid'], false);
        }

        if ($auth_bind_kick) {
            $auth_bind_username = isset($_SESSION['sessionusername']) ? $_SESSION['sessionusername'] : '';

            // Leave a trace: without it a session that ends here looks to the
            // operator like a random logout with no cause anywhere.
            log_activity(lang(array(
                'string' => 'session ended on this device ({var:1})',
                'vars'   => array($auth_bind_reason))), $auth_bind_username);

            session_unset();
            session_destroy();
            setcookie('software[auth]', '', time() - 1000, '/');
            unset($_COOKIE['software']['auth']);

            // A form POST would now fail the CSRF check (the token lived in the
            // session we just ended) and the visitor would get a confusing
            // "your session expired" wall on top of a silent sign-out. Send
            // them to the sign-in screen instead - the honest outcome. Only
            // for ordinary form posts: background calls (XHR, the search
            // indexer) must not be answered with a redirect.
            if ((($_SERVER['REQUEST_METHOD'] ?? '') === 'POST')
                && (strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !== 'xmlhttprequest')
                && (!defined('UPDATE_SEARCH_INDEX') || UPDATE_SEARCH_INDEX !== true)) {

                header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/registration_entrance.php');
                exit();
            }
        }
    }

    $user = null;

    // 1) Remember-me: a selector:validator token in software[auth]. Only when no
    // session user exists yet, and never while update_search_index.php runs (it
    // must not adopt the caller's identity from the cookie).
    //
    // Nor on a request that carries API credentials. Such a request says who it
    // is in its own body and is answered on that alone; letting it fall back to
    // whoever the browser's cookie belongs to would sign in a caller whose
    // password was wrong, and hand the endpoint's token exemption to a request
    // that never proved anything.
    //
    // And only once the auth_tokens table exists (upgrade bridge): before
    // that there is nothing to verify against, and the cookie is left alone
    // rather than cleared - after the upgrade it is checked for real.
    if ((REMEMBER_ME == true)
        && isset($_COOKIE['software']['auth'])
        && (isset($_SESSION['sessionuserid']) == false)
        && !defined('API_USERNAME')
        && (!defined('UPDATE_SEARCH_INDEX') || UPDATE_SEARCH_INDEX !== true)
        && pg_auth_tokens_table_exists()) {

        $token_user_id = pg_auth_token_verify($_COOKIE['software']['auth']);

        if ($token_user_id) {

            $user = pg_load_user_row($token_user_id);

            if (is_array($user) && isset($user['id'])) {
                $_SESSION['sessionuserid']  = $user['id'];
                $_SESSION['sessionusername'] = $user['username'];
                log_activity(lang('user logged in'), $user['username']);
            } else {
                $user = null;
            }

        } else {
            // Not a valid token: clear the cookie so the browser stops sending it.
            setcookie('software[auth]', '', time() - 1000, '/');
        }

    // 2) An API request (raw password), or a session that already carries a user id.
    } else if (defined('API_USERNAME') || isset($_SESSION['sessionuserid'])) {

        if (defined('API_USERNAME')) {

            // A password sent to an endpoint is a sign-in like any other, and
            // is counted like one. Without this the sign-in screen's lockout
            // could be walked around by posting the same guesses here, where
            // nothing counted them and the account never closed.
            pg_login_throttle_guard(API_USERNAME);

            // The API sends the raw password; verify it against the stored hash.
            // pg_password_verify() also upgrades a legacy row the first time an
            // API client signs in with it.
            $cred = db_item("SELECT " . pg_password_select_columns() . " FROM user
                WHERE (user_username = '" . e(API_USERNAME) . "') OR (user_email = '" . e(API_USERNAME) . "') LIMIT 1");

            if (is_array($cred) && isset($cred['user_id'])
                && pg_password_verify($cred['user_id'], API_PASSWORD, $cred['user_password'], pg_password_row_algo($cred))) {

                $user = pg_load_user_row($cred['user_id']);

                // The password was checked and it was right. The endpoints read
                // this, not API_USERNAME, before they waive the CSRF token:
                // sending a user name is something anybody's browser can be made
                // to do, proving it is not.
                define('API_AUTHENTICATED', true);

                pg_login_throttle_pass(API_USERNAME);

            } else {
                pg_login_record_failure(API_USERNAME);
            }

        } else {
            // The session id was verified when it was set; load the row, no
            // password re-check.
            $user = pg_load_user_row((int) $_SESSION['sessionuserid']);
        }
    }

    // if the user is logged in, then store user info in global constants to be used later
    if (isset($user) && is_array($user)) {
        define('USER_LOGGED_IN', true);
        define('USER_ID', $user['id']);
        define('USER_USERNAME', $user['username']);
        define('USER_EMAIL_ADDRESS', $user['email_address']);
        define('USER_ROLE', $user['role']);
        define('USER_CONTACT_ID', $user['contact_id']);
        define('USER_MEMBER_ID', $user['member_id']);
        define('USER_EXPIRATION_DATE', $user['expiration_date']);
        // If the user is an active member then store that.
        if ((USER_MEMBER_ID != '') && ((USER_EXPIRATION_DATE == '') || (USER_EXPIRATION_DATE == '0000-00-00') || (USER_EXPIRATION_DATE >= date('Y-m-d')))) {
            define('USER_MEMBER', true);
            // Otherwise the user is not an active member, so store that.
        } else {
            define('USER_MEMBER', false);
        }
        define('USER_START_PAGE_ID', $user['start_page_id']);
        if ((USER_ROLE < 3) || ($user['manage_ecommerce'] == 'yes')) {
            define('USER_MANAGE_ECOMMERCE', true);
        } else {
            define('USER_MANAGE_ECOMMERCE', false);
        }
        if ((USER_ROLE < 3) or $user['manage_ecommerce_reports']) {
            define('USER_MANAGE_ECOMMERCE_REPORTS', true);
        } else {
            define('USER_MANAGE_ECOMMERCE_REPORTS', false);
        }
        if ((USER_ROLE < 3) or !empty($user['manage_erp'])) {
            define('USER_MANAGE_ERP', true);
        } else {
            define('USER_MANAGE_ERP', false);
        }
        // Money in the till and the module's own settings are carved out of the
        // gate: seeing what a customer owes is not the same as seeing what is in
        // the bank, and neither is the right to change how documents are numbered.
        if (USER_MANAGE_ERP && ((USER_ROLE < 3) or !empty($user['manage_erp_cash']))) {
            define('USER_MANAGE_ERP_CASH', true);
        } else {
            define('USER_MANAGE_ERP_CASH', false);
        }
        if (USER_MANAGE_ERP && ((USER_ROLE < 3) or !empty($user['manage_erp_settings']))) {
            define('USER_MANAGE_ERP_SETTINGS', true);
        } else {
            define('USER_MANAGE_ERP_SETTINGS', false);
        }
        if ((USER_ROLE < 3) || ($user['manage_forms'] == 'yes')) {
            define('USER_MANAGE_FORMS', true);
        } else {
            define('USER_MANAGE_FORMS', false);
        }
        // If the user has a timezone set that is not the default,
        // and the PHP version is high enough to support a user timezone,
        // then set user timezone.
        if (($user['timezone'] != '') && (version_compare(PHP_VERSION, '5.2.0', '>=') == true)) {
            define('USER_TIMEZONE', $user['timezone']);
        } else {
            define('USER_TIMEZONE', '');
        }
        // else the user is not logged in, so store that
    } else {
        define('USER_LOGGED_IN', false);
        define('USER_ID', '');
        define('USER_CONTACT_ID', '');
        define('USER_USERNAME', '');
        define('USER_EMAIL_ADDRESS', '');
    }
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
// Create function in order to check if a visitor has edit access to a folder.
// It returns true or false.
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
// Create function that is used by various actions (e.g. submit custom form, submit order)
// in order to determine if visitor's IP address is banned
// and therefore should not be allowed to complete action.
// The action is just a text string to describe the action in the log message (e.g. submit custom form).
/**
 * Whether the sign-in throttle can run.
 *
 * Needs the settings columns from 2026.4.4 and the waf_rate table from
 * 2026.2.4 that does the counting. An install that took the code without
 * running the upgrade loses the feature silently instead of failing on an
 * unknown column, the same pattern as pg_page_noindex_ready().
 */
function pg_login_throttle_ready()
{
    static $ready = null;

    if ($ready !== null) {
        return $ready;
    }

    $ready = (function_exists('waf_table_has_column')
        && function_exists('waf_rate_record')
        && waf_table_has_column('config', 'login_throttle'));

    return $ready;
}

/**
 * Is the throttle switched on?
 *
 * This has its own switch rather than riding on the firewall's, for two
 * reasons. The firewall ships in Monitor mode, so gating on it would leave a
 * fresh install with no limit at all; and an operator who switches the
 * firewall off to chase a false positive must not open the password door in
 * the same movement. check_banned_ip_addresses() sits outside the firewall
 * for the same reason.
 *
 * Settings are read through waf_setting() only because that helper already
 * holds the config row this request read - it is not a firewall dependency.
 */
function pg_login_throttle_enabled()
{
    return (pg_login_throttle_ready() && (int) waf_setting('login_throttle', 1) === 1);
}

/**
 * Failures allowed per window, the window length in seconds, and how long a
 * subject stays locked out once it is over.
 */
function pg_login_throttle_limits()
{
    $attempts = (int) waf_setting('login_throttle_attempts', 10);
    $minutes  = (int) waf_setting('login_throttle_minutes', 15);
    $lockout  = (int) waf_setting('login_throttle_lockout', 60);

    $attempts = max(1, min(1000, $attempts));

    return array(
        'attempts' => $attempts,

        // The address is allowed several times the account's failures before
        // it closes, for two reasons that are really one.
        //
        // One person mistyping their own password eleven times must not take
        // their whole office off the sign-in screen. And an operator lifting
        // that person's lock by hand has to actually get them back in, which
        // it would not if the same run had closed the address as well: the
        // unlock button can only reach account counters, because the address
        // is not a property of the account and clearing it would lift the
        // limit for everyone behind it.
        //
        // The address limit exists for a machine spraying one password across
        // many accounts, and that pattern passes this multiple without
        // noticing it.
        'attempts_address' => $attempts * 5,

        'window'   => max(1, min(1440, $minutes)) * 60,
        'lockout'  => max(1, min(43200, $lockout)) * 60);
}

/**
 * The two things a sign-in attempt is counted against.
 *
 * The address catches one machine working through a password list. The
 * account catches the same guessing spread over a botnet, where no single
 * address ever reaches the limit. Neither alone is enough.
 *
 * The account is folded to lower case so that "Ali" and "ali" share a
 * counter, and it is only ever stored as a hash inside waf_rate_key().
 */
function pg_login_throttle_subjects($identifier)
{
    $subjects = array();

    $ip = function_exists('waf_client_ip') ? waf_client_ip() : '';

    // An unresolved address stands for the whole internet rather than one
    // visitor, so counting against it would lock everybody out at once.
    //
    // Counted per subject - the address for IPv4, the /64 for IPv6 - because
    // an IPv6 guesser can otherwise present a fresh address on every attempt.
    // See waf_ip_subject().
    if ($ip !== '' && (!function_exists('waf_ip_is_infrastructure') || !waf_ip_is_infrastructure($ip))) {
        $subjects['a'] = function_exists('waf_ip_subject') ? waf_ip_subject($ip) : $ip;
    }

    $identifier = trim((string) $identifier);

    if ($identifier !== '') {
        $subjects['u'] = mb_strtolower($identifier);
    }

    return $subjects;
}

/**
 * Stop the request when this address or this account is already locked out.
 *
 * Called before the password is checked, so a locked-out attempt costs one
 * indexed read and never reaches the credential comparison at all.
 *
 * The message deliberately does not say which of the two limits was hit, and
 * is the same whether or not the account exists: telling an attacker that
 * they found a real username is the one thing a sign-in screen should never
 * volunteer.
 */
function pg_login_throttle_guard($identifier)
{
    if (!pg_login_throttle_enabled()) {
        return;
    }

    $limits = pg_login_throttle_limits();

    foreach (pg_login_throttle_subjects($identifier) as $scope => $subject) {
        if (waf_rate_hits($subject, 'lock' . $scope, $limits['lockout']) > 0) {
            pg_login_throttle_deny($limits);
        }
    }
}

/**
 * Count a failure and open a lockout once the limit is passed.
 *
 * The lockout is a second, longer bucket rather than the counting window
 * itself. With one bucket the attacker simply waits for the window to roll
 * and gets the full allowance again; with two, passing the limit costs them
 * the lockout period no matter where in the window it happened.
 */
function pg_login_record_failure($identifier)
{
    if (!pg_login_throttle_enabled()) {
        return;
    }

    // Rows in waf_rate are only ever created by failures, so the cleaner rides
    // along with them and the mess arrives with its own broom. It cannot ride
    // on waf_sweep(): that sits behind the firewall's off switch, and a site
    // running this throttle with the firewall off would never delete a row.
    //
    // A sign-in attempt against a user name that does not exist is counted
    // like any other failure, which is what stops an attacker learning which
    // names are real by watching what gets counted - but it also means every
    // made-up name an attacker tries writes its own row.
    if (mt_rand(1, 50) === 1) {
        waf_rate_sweep();
    }

    $limits = pg_login_throttle_limits();
    $locked = array();

    foreach (pg_login_throttle_subjects($identifier) as $scope => $subject) {

        $allowed = ($scope === 'a') ? $limits['attempts_address'] : $limits['attempts'];

        if (waf_rate_record($subject, 'fail' . $scope, $limits['window']) <= $allowed) {
            continue;
        }

        waf_rate_record($subject, 'lock' . $scope, $limits['lockout']);
        $locked[] = $scope;
    }

    if (!$locked) {
        return;
    }

    // Which of the two closed: 'u' the account, 'a' the visitor's address.
    // Both can close on the same attempt, and the account is the more useful
    // one to name because it is the one an operator can lift by hand.
    $account_closed = in_array('u', $locked, true);

    $subject_label = $account_closed
        ? trim((string) $identifier)
        : lang('this address');

    // Report the allowance that actually closed, not the account one, or the
    // log would claim an address shut after ten failures when it took fifty.
    $closed_at = $account_closed ? $limits['attempts'] : $limits['attempts_address'];

    log_activity(
        lang(array(
            'string' => 'sign-in locked for {var:1} after {var:2} failed attempts',
            'vars'   => array($subject_label, $closed_at))),
        lang('UNKNOWN'));

    // Also recorded in the firewall log. That is the screen an operator
    // watches while a site is being probed, and a sign-in lockout belongs in
    // the same picture as a rate limit. Written whether or not the firewall is
    // switched on: waf_log_event() only needs the log table, and the throttle
    // carries its own switch.
    //
    // The action is 'rate' rather than a new value so that the screen's
    // existing badge and its blocked total both count it; the category is what
    // separates these from request rate limiting.
    if (function_exists('waf_log_event')) {
        waf_log_event(
            'rate',
            'login-lock',
            'login',
            7,
            mb_substr($subject_label, 0, 64),
            $closed_at . '/' . (int) ($limits['window'] / 60) . ' min');
    }

    pg_login_throttle_deny($limits);
}

/**
 * Forget the account's failures after a successful sign-in.
 *
 * The address counter is deliberately left alone. Clearing it would hand an
 * attacker who holds one valid account a way to reset the limit between runs
 * of guesses against every other account.
 */
function pg_login_throttle_pass($identifier)
{
    if (!pg_login_throttle_enabled()) {
        return;
    }

    $subjects = pg_login_throttle_subjects($identifier);

    if (isset($subjects['u'])) {
        waf_rate_clear($subjects['u'], 'failu');
        waf_rate_clear($subjects['u'], 'locku');
    }
}

/**
 * Refuse the attempt.
 *
 * 429 with Retry-After rather than a form error, so that a script reading
 * status codes is told to back off and the browser is not invited to resubmit
 * the login form.
 */
function pg_login_throttle_deny($limits)
{
    // A lockout is a refusal like any other for the plain-text log: a host
    // that keeps arriving at a locked door is what fail2ban is for.
    if (function_exists('waf_text_log') && function_exists('waf_client_ip')) {
        waf_text_log('DENY', waf_client_ip(), array('status' => 429, 'rule' => 'login-lock'));
    }

    if (!headers_sent()) {
        http_response_code(429);
        header('Retry-After: ' . (int) $limits['lockout']);
        header('Cache-Control: no-store, no-cache, must-revalidate');
    }

    // An endpoint's caller is reading json, not a page. Sending it the styled
    // error screen would leave it with a parse failure where a plain refusal
    // was meant, and the reason for the refusal would never be seen.
    if (defined('API_USERNAME')) {

        if (!headers_sent()) {
            header('Content-Type: application/json');
        }

        print encode_json(array(
            'status'  => 'error',
            'message' => lang(array(
                'string' => 'Too many failed sign-in attempts. Please wait {var:1} minutes and try again.',
                'vars'   => (int) ($limits['lockout'] / 60)))));

        exit();
    }

    output_error(
        lang(array(
            'string' => 'Too many failed sign-in attempts. Please wait {var:1} minutes and try again.',
            'vars'   => (int) ($limits['lockout'] / 60)))
        . ' <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.');
}

// ---- The sign-in question: a step between counting and locking out --------
//
// The lockout is the last resort and it is blunt: it turns away the account's
// owner along with the guesser. Before it, from a few failures on, an attempt
// has to answer a small arithmetic question. A person answers it in a second;
// a script working through a password list does not.
//
// A wrong answer is counted as a failed attempt - the same counters, the same
// lockout at the end - and deliberately NOT as a firewall offence. An offence
// count ends in an automatic ban of the whole address, and this question is
// meant to be the step BEFORE the lockout, not something harsher than it: a
// handful of wrong answers from one machine in an office must not put the
// office, its administrator included, behind a 403 for an hour.

/**
 * From how many failures on the question is asked. 0 switches it off.
 */
function pg_login_captcha_after()
{
    if (!pg_login_throttle_enabled()) {
        return 0;
    }

    return max(0, min(100, (int) waf_setting('login_throttle_captcha_after', 3)));
}

/**
 * Does this attempt owe an answer?
 *
 * Decided from the same counters the lockout uses, never from the session:
 * an attacker who discards the cookie discards a session flag with it, but
 * the failures counted against their address and against the account stay
 * counted. Read without counting - asking is not an attempt.
 */
function pg_login_captcha_required($identifier)
{
    $after = pg_login_captcha_after();

    if ($after <= 0) {
        return false;
    }

    $limits = pg_login_throttle_limits();

    foreach (pg_login_throttle_subjects($identifier) as $scope => $subject) {
        if (waf_rate_hits($subject, 'fail' . $scope, $limits['window']) >= $after) {
            return true;
        }
    }

    return false;
}

/**
 * Issue a question and keep its answer in the session.
 *
 * Unlike the form captcha elsewhere in the software, the answer is not
 * carried in the form: this question exists to stop a script, and a script
 * reads a hidden field as easily as a person reads the question. One
 * question per issue; used or wrong, it is gone.
 */
function pg_login_captcha_issue()
{
    $first  = mt_rand(1, 9);
    $second = mt_rand(1, 9);

    $_SESSION['software']['login_captcha'] = array('answer' => $first + $second, 'issued' => time());

    return lang(array('string' => 'What is {var:1} + {var:2} ?', 'vars' => array($first, $second)));
}

/**
 * Check the answer against the question this session was issued, and
 * consume the question either way. Ten minutes to answer.
 */
function pg_login_captcha_check($answer)
{
    $pending = isset($_SESSION['software']['login_captcha']) ? $_SESSION['software']['login_captcha'] : null;

    unset($_SESSION['software']['login_captcha']);

    if (!is_array($pending) || !isset($pending['answer']) || !isset($pending['issued'])) {
        return false;
    }

    if (((int) $pending['issued'] + 600) < time()) {
        return false;
    }

    $answer = trim((string) $answer);

    return (preg_match('/^\d{1,2}$/', $answer) && ((int) $answer === (int) $pending['answer']));
}

/**
 * Stop a sign-in attempt that owes an answer.
 *
 * Called right after pg_login_throttle_guard(), before the password is
 * looked at. The request ends on the question screen in two cases: no
 * answer was sent, or it was wrong. A wrong answer is recorded as a failed
 * attempt and as a firewall log entry, so it is visible and eventually locks
 * the address or the account out like any other failure - but it is not an
 * offence; see above.
 *
 * $screen names the form to rebuild: 'action' (URL), 'identifier' and
 * 'password' (field names). Hidden fields the original form carried travel
 * through the question screen by name; see pg_login_captcha_passthrough().
 */
function pg_login_captcha_gate($identifier, $liveform, $screen)
{
    if (!pg_login_captcha_required($identifier)) {
        return;
    }

    if (!isset($_POST['login_captcha_answer'])) {
        pg_login_captcha_screen($identifier, $liveform, $screen, '');
    }

    if (pg_login_captcha_check($_POST['login_captcha_answer'])) {
        return;
    }

    if (function_exists('waf_log_event')) {
        waf_log_event('log', 'login-captcha', 'login', 3, mb_substr(trim((string) $identifier), 0, 64), 'wrong answer');
    }

    // Counted like a wrong password: the lockout that follows enough of them
    // is the ceiling on guessing the answer. Ends the request itself when
    // that lockout closes.
    pg_login_record_failure($identifier);

    pg_login_captcha_screen($identifier, $liveform, $screen, lang('That answer is not right. Please try the new question.'));
}

/**
 * The same screen after a refused password, when the next attempt will owe
 * an answer anyway: shown now, with the sign-in error on it, instead of a
 * bounce to the plain form that would only stop the visitor again.
 */
function pg_login_captcha_after_failure($identifier, $liveform, $screen)
{
    if (!pg_login_captcha_required($identifier)) {
        return;
    }

    pg_login_captcha_screen($identifier, $liveform, $screen, '');
}

/**
 * The hidden fields a sign-in form may carry, taken from the request by name
 * so the question screen can hand them on. Names only - nothing else in the
 * request is echoed back.
 */
function pg_login_captcha_passthrough()
{
    $hidden = array();

    foreach (array('login', 'send_to', 'return_to', 'login_region', 'allow_guest', 'remember_me', 'login_remember_me', 'require_cookies') as $name) {
        if (isset($_POST[$name]) && is_scalar($_POST[$name]) && (string) $_POST[$name] !== '') {
            $hidden[$name] = (string) $_POST[$name];
        }
    }

    return $hidden;
}

/**
 * Render the question screen and end the request.
 *
 * The form asks for the account and the password again - the password is
 * never carried across - plus the answer. The liveform's own messages are
 * printed and then cleared, so the plain sign-in form does not repeat them
 * on its next visit.
 *
 * The two credential inputs carry the SAME name and id as the sign-in form
 * they stand in for, and the usual autocomplete tokens. Password managers
 * match a saved entry on those; with ids of this screen's own invention they
 * either left the fields alone or filled a different saved entry, and the
 * visitor - seeing the manager's icon on the field - submitted what looked
 * like their password and was told it was wrong. A screen that asks for a
 * password has to look like the screen the password was saved on.
 */
function pg_login_captcha_screen($identifier, $liveform, $screen, $message)
{
    $question = pg_login_captcha_issue();

    $hidden = '';

    foreach (pg_login_captcha_passthrough() as $name => $value) {
        $hidden .= '<input type="hidden" name="' . h($name) . '" value="' . h($value) . '"/>' . "\n";
    }

    $messages = '';

    if (is_object($liveform) && method_exists($liveform, 'output_errors')) {
        $messages .= $liveform->output_errors();

        // The caller copied the posted fields into the session before asking;
        // the password does not stay there while the question is pending.
        if (method_exists($liveform, 'assign_field_value')) {
            $liveform->assign_field_value($screen['password'], '');
        }
    }

    if ($message !== '') {
        $messages .= '<div class="alert alert-danger" role="alert"><i class="bi bi-exclamation-triangle-fill me-2" aria-hidden="true"></i>' . h($message) . '</div>';
    }

    if (!headers_sent()) {
        header('Cache-Control: no-store, no-cache, must-revalidate');
    }

    echo output_header_secure(array('title' => lang('One More Step')))
        . '<main class="container py-4 py-md-5">
            <div class="card mx-auto shadow-sm" style="max-width: 28rem;">
                <div class="card-body p-4">
                    <h1 class="h4 mb-2"><i class="bi bi-shield-lock me-2" aria-hidden="true"></i>' . lang('One More Step') . '</h1>
                    <p class="text-muted mb-3">' . lang('There have been several failed sign-in attempts for this account or from this address. Please sign in again and answer the question below.') . '</p>
                    ' . $messages . '
                    <form name="login_captcha" action="' . h($screen['action']) . '" method="post" autocomplete="on">
                        ' . get_token_field() . '
                        ' . $hidden . '
                        <div class="mb-3">
                            <label for="' . h($screen['identifier']) . '" class="form-label">' . lang('Email') . '</label>
                            <input type="text" id="' . h($screen['identifier']) . '" name="' . h($screen['identifier']) . '" value="' . h((string) $identifier) . '" class="form-control" required="required" autocomplete="username" spellcheck="false"/>
                        </div>
                        <div class="mb-3">
                            <label for="' . h($screen['password']) . '" class="form-label">' . lang('Password') . '</label>
                            <input type="password" id="' . h($screen['password']) . '" name="' . h($screen['password']) . '" class="form-control" required="required" autocomplete="current-password" autofocus="autofocus"/>
                        </div>
                        <div class="mb-4">
                            <label for="login_captcha_answer" class="form-label fw-semibold">' . h($question) . '</label>
                            <input type="number" id="login_captcha_answer" name="login_captcha_answer" class="form-control" style="max-width: 8rem;" min="0" max="18" required="required" autocomplete="off" inputmode="numeric"/>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">' . lang('Login') . '</button>
                    </form>
                </div>
            </div>
        </main>'
        . output_footer_secure();

    if (is_object($liveform) && method_exists($liveform, 'unmark_errors')) {
        $liveform->unmark_errors();
    }

    exit();
}

/**
 * Rate limit password reset requests before any expensive work runs.
 *
 * This is a different measure from the sign-in throttle above, which counts
 * FAILURES. A reset request has no failures to count: every accepted one sends
 * an email, and that send holds the request's database connection for as long
 * as the mail server takes to answer.
 *
 * That is what a live site was taken down by on 2026-08-31. A bot posted to
 * forgot_password.php in a loop; each request occupied a connection across an
 * SMTP round trip; max_user_connections filled; and from then on every request
 * to the site - the administrator's own panel included - died before it could
 * reach the firewall that would have stopped the bot.
 *
 * Two counters, because they answer different attacks:
 *
 *   address   one machine hammering the endpoint (the resource exhaustion)
 *   target    many machines aimed at one mailbox (the inbox flood)
 *
 * The window is taken from the sign-in throttle so an operator who widened one
 * widened both, but there is no enable switch: the ceiling sits far above any
 * human use of a password reset form, and the failure it prevents takes the
 * whole site with it. Where waf_rate is unavailable the counter fails open, as
 * every other rate limit in this software does.
 */
function pg_password_reset_limits()
{
    $minutes = (int) waf_setting('login_throttle_minutes', 15);
    $window  = max(1, min(1440, $minutes)) * 60;

    return array(
        // Five in a quarter hour. A person who genuinely lost the email and
        // retried is nowhere near this; a loop reaches it in a second.
        'address'        => 5,
        'address_window' => $window,

        // Three an hour at one mailbox, whatever the source address. The
        // inbox flood is the same attack run from a botnet, and the address
        // counter cannot see it.
        'target'         => 3,
        'target_window'  => 3600);
}

/**
 * @param  string $email Address the reset was requested for
 * @return void          Denies and exits when either ceiling is passed
 */
function pg_password_reset_guard($email = '')
{
    if (!function_exists('waf_rate_exceeded') || !isset(db::$con) || !db::$con) {
        return;
    }

    $limits = pg_password_reset_limits();

    $ip = function_exists('waf_client_ip') ? waf_client_ip() : '';

    // Per subject, not per address: for IPv6 that is the /64, or the ceiling
    // would be a fresh allowance on every address of the same sender.
    if ($ip !== '' && function_exists('waf_ip_subject')) {
        $ip = waf_ip_subject($ip);
    }

    if ($ip !== '' && waf_rate_exceeded($ip, 'pwreset-ip', $limits['address'], $limits['address_window'])) {
        pg_password_reset_deny($limits['address_window']);
    }

    // Normalised and hashed: the counter needs to recognise the same mailbox
    // again, not to keep a list of who asked. Case folding stops Foo@ and foo@
    // counting as two mailboxes.
    $email = trim((string) $email);

    if ($email !== '') {
        $target = sha1(mb_strtolower($email, 'UTF-8'));

        if (waf_rate_exceeded($target, 'pwreset-to', $limits['target'], $limits['target_window'])) {
            pg_password_reset_deny($limits['target_window']);
        }
    }
}

/**
 * Refuse a reset request that passed a ceiling.
 *
 * The message says nothing about the address it was asked for. This form is
 * reachable without signing in, so anything that varies with whether an
 * account exists is an oracle for harvesting valid addresses.
 */
function pg_password_reset_deny($retry_after)
{
    // Recorded in the firewall log, not just refused.
    //
    // The incident that produced this limiter left NO firewall event at all -
    // the operator found it days later in the PHP error log, by chance. A
    // limiter that turns requests away silently would repeat exactly that:
    // the attack stops working and nobody ever learns it happened.
    //
    // Score 7 puts it above the noise floor without auto-banning on its own;
    // waf_register_offence() accumulates it so a persistent source still ends
    // up banned by the normal path.
    if (function_exists('waf_log_event') && isset(db::$con) && db::$con) {

        $blocking = (!function_exists('waf_mode') || waf_mode() === 'block');

        waf_log_event(
            $blocking ? 'rate' : 'would-rate',
            'rate-password-reset',
            'rate',
            7,
            'forgot_password.php',
            (int) round($retry_after / 60) . 'min'
        );

        if (function_exists('waf_register_offence') && function_exists('waf_client_ip')) {
            waf_register_offence(waf_client_ip(), 5, $blocking);
        }
    }

    if (!headers_sent()) {
        http_response_code(429);
        header('Retry-After: ' . (int) $retry_after);
        header('Cache-Control: no-store, no-cache, must-revalidate');
    }

    output_error(
        lang(array(
            'string' => 'Too many password reset requests. Please wait {var:1} minutes and try again.',
            'vars'   => max(1, (int) round($retry_after / 60))))
        . ' <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.');
}

/**
 * Is this account locked out of signing in, and until when?
 *
 * Both the user name and the email address are checked, because the counter is
 * opened under whatever the visitor typed at the sign-in screen and either one
 * reaches the same account.
 *
 * The address half of the throttle is deliberately not reported here. It is
 * not a property of the account: one office behind a single address would
 * otherwise show every user in that office as locked, and clearing it from a
 * user screen would lift the limit for everyone on that address.
 */
function pg_login_lock_state($username, $email)
{
    $state = array('locked' => false, 'until' => 0);

    if (!pg_login_throttle_enabled()) {
        return $state;
    }

    $limits = pg_login_throttle_limits();

    foreach (pg_login_lock_identifiers($username, $email) as $subject) {

        $bucket = waf_rate_state($subject, 'locku', $limits['lockout']);

        if (!$bucket['hits']) {
            continue;
        }

        $state['locked'] = true;
        $state['until']  = max($state['until'], $bucket['window_start'] + $limits['lockout']);
    }

    return $state;
}

/**
 * Lift a sign-in lockout by hand.
 *
 * The failure counter goes with the lockout. Clearing only the lockout would
 * leave the account one mistyped password away from being locked again, which
 * is not what an operator means when they unlock somebody.
 *
 * Gated on _ready() rather than _enabled() so that leftovers can still be
 * cleared after the feature itself has been switched off.
 */
function pg_login_unlock($username, $email)
{
    if (!pg_login_throttle_ready()) {
        return;
    }

    foreach (pg_login_lock_identifiers($username, $email) as $subject) {
        waf_rate_clear($subject, 'failu');
        waf_rate_clear($subject, 'locku');
    }
}

/**
 * The distinct counter subjects one account can be held under, folded to lower
 * case the same way pg_login_throttle_subjects() folds them.
 */
function pg_login_lock_identifiers($username, $email)
{
    $subjects = array();

    foreach (array($username, $email) as $identifier) {

        $identifier = trim((string) $identifier);

        if ($identifier === '') {
            continue;
        }

        $subjects[mb_strtolower($identifier)] = true;
    }

    return array_keys($subjects);
}

function check_banned_ip_addresses($action)
{
    // Delegates to the firewall so there is exactly one implementation of IP
    // matching in the codebase. What this buys over the old inline loop:
    //
    //   * The visitor's REAL address. This used to read REMOTE_ADDR directly,
    //     which behind Cloudflare or any reverse proxy is the proxy, not the
    //     visitor — so a ban either matched nobody or matched everybody.
    //   * CIDR and IPv6 support, not just IPv4 with asterisks.
    //   * The allow list, so an operator cannot lock themselves out.
    //   * Ban expiry, so automatic temporary bans actually lift.
    $ip_address = function_exists('waf_client_ip')
        ? waf_client_ip()
        : (isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '');

    if (!function_exists('waf_ip_is_blocked')) {
        return;
    }

    if (waf_ip_is_allowed($ip_address) || !waf_ip_is_blocked($ip_address)) {
        return;
    }

    log_activity(
        lang(array(
            'string' => 'visitor was not allowed to {var:1} because the visitor\'s IP address ({var:2}) was banned',
            'vars'   => array($action, h($ip_address)),
        )),
        isset($_SESSION['sessionusername']) ? $_SESSION['sessionusername'] : ''
    );

    output_error(
        lang('Sorry, we were not able to accept your request.')
        . ' <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.'
    );
}

// ── Puzzle captcha (general purpose) ─────────────────────────────────────
// Slide-the-piece verification. Its first consumer is the live chat's
// anonymous visitor flow; thanks to the scope parameter other screens (e.g.
// login) can reuse the same family later. The existing math-based form
// captcha (CAPTCHA constant) is untouched.
//
// Honest security note: the target is drawn client-side as a visual cutout;
// an advanced JS-running bot can read it from the DOM. The family's real
// value is in its layers: (1) the challenge must be fetched and solved via
// JS — bots that POST the form directly are eliminated outright, (2) solve
// time limit: a "solution" faster than 1 second is not human and is
// rejected, (3) 5 failed attempts lock for 60 seconds; the target is
// regenerated on every attempt, so brute-force odds stay independent of the
// attempt count. This is not a Turnstile-like service; its goal is to stop
// form-spam bots.

function pg_puzzle_captcha_state($scope)
{
    $scope = preg_replace('/[^a-z0-9_]/', '', (string) $scope);

    if (!isset($_SESSION['puzzle_captcha'][$scope]) || !is_array($_SESSION['puzzle_captcha'][$scope])) {
        $_SESSION['puzzle_captcha'][$scope] = array();
    }

    return $scope;
}

function pg_puzzle_captcha_challenge($scope)
{
    $scope = pg_puzzle_captcha_state($scope);
    $state = $_SESSION['puzzle_captcha'][$scope];

    if (isset($state['locked_until']) && time() < $state['locked_until']) {
        return array('locked' => true, 'retry_after' => (int) ($state['locked_until'] - time()));
    }

    // 14%-86%: keeps the piece on the rail and away from the edges, where
    // sticking to an end would be a free guess.
    $target = mt_rand(14, 86);

    $_SESSION['puzzle_captcha'][$scope]['target'] = $target;
    $_SESSION['puzzle_captcha'][$scope]['issued_at'] = microtime(true);
    $_SESSION['puzzle_captcha'][$scope]['solved'] = false;

    return array('locked' => false, 'target' => $target);
}

function pg_puzzle_captcha_verify($scope, $position)
{
    $scope = pg_puzzle_captcha_state($scope);
    $state = $_SESSION['puzzle_captcha'][$scope];

    if (isset($state['locked_until']) && time() < $state['locked_until']) {
        return array('ok' => false, 'locked' => true, 'retry_after' => (int) ($state['locked_until'] - time()));
    }

    if (!empty($state['solved'])) {
        return array('ok' => true);
    }

    if (!isset($state['target']) || !isset($state['issued_at'])) {
        return array('ok' => false, 'locked' => false);
    }

    $elapsed = microtime(true) - (float) $state['issued_at'];

    // A drag faster than 1 second is not human; a challenge older than 3
    // minutes is stale. In both cases this target is burned and a new one
    // must be requested.
    $position_ok = (abs((float) $position - (int) $state['target']) <= 4);

    if ($position_ok && $elapsed >= 1.0 && $elapsed <= 180.0) {
        $_SESSION['puzzle_captcha'][$scope]['solved'] = true;
        $_SESSION['puzzle_captcha'][$scope]['fails'] = 0;
        unset($_SESSION['puzzle_captcha'][$scope]['target']);

        return array('ok' => true);
    }

    unset($_SESSION['puzzle_captcha'][$scope]['target']);

    $fails = isset($state['fails']) ? (int) $state['fails'] : 0;
    $fails++;

    if ($fails >= 5) {
        $_SESSION['puzzle_captcha'][$scope]['locked_until'] = time() + 60;
        $_SESSION['puzzle_captcha'][$scope]['fails'] = 0;

        return array('ok' => false, 'locked' => true, 'retry_after' => 60);
    }

    $_SESSION['puzzle_captcha'][$scope]['fails'] = $fails;

    return array('ok' => false, 'locked' => false);
}

function pg_puzzle_captcha_solved($scope)
{
    $scope = pg_puzzle_captcha_state($scope);

    return !empty($_SESSION['puzzle_captcha'][$scope]['solved']);
}

function pg_puzzle_captcha_reset($scope)
{
    $scope = pg_puzzle_captcha_state($scope);

    $_SESSION['puzzle_captcha'][$scope] = array();
}




//Developers define to config.php to page name and an unlock pin to lock pages.
//If page name define like below, users redirect to developer_lock.php.
//Software ask's pin code to unlock and access to page.
//If user enter pin  correct, software redirect user to page try to access and no redirect developer_lock.php until Pin code is change.
function initialize_developer_security()
{
    // Ensure database connection and session user are available
    if (defined('DB_CONNECTED') && isset($_SESSION['sessionusername'])) {

        $SUser = mysqli_real_escape_string(db::$con, $_SESSION['sessionusername']);

        // Fetch developer PIN hash from database
        $query = "SELECT user_devpasspin FROM user WHERE user_username = '" . $SUser . "'";
        $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
        $row = mysqli_fetch_assoc($result);
        $devpasspin = $row['user_devpasspin'];

        // Get current script name
        $url_path_parts = explode('/', $_SERVER['SCRIPT_NAME']);
        $file_name = end($url_path_parts);

        // If page is locked and PIN is defined and not verified, redirect to lock screen
        if (
            defined('LOCKED_PAGES') &&
            in_array($file_name, LOCKED_PAGES) &&
            DEVELOPER_PIN !== '' &&
            $devpasspin !== md5(DEVELOPER_PIN)
        ) {
            $_SESSION["filename"] = $file_name;
            header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/developer_lock.php');
            exit;
        }

        // Return last attempted filename if available
        if (isset($_SESSION["filename"])) {
            return $_SESSION["filename"];
        }
    }
}

/**
 * Decrypt API/Secret keys stored in DB.
 */
function decode_ssl_keys($key_base64, $iv_base64)
{
    $crypto_method = 'aes-256-cbc';
    $crypto_key = substr(hash('sha256', ENCRYPTION_KEY, true), 0, 32);
    $key = base64_decode($key_base64);
    $iv = base64_decode($iv_base64);
    if ($key === false || $iv === false)
        return '';
    $decrypted = openssl_decrypt($key, $crypto_method, $crypto_key, OPENSSL_RAW_DATA, $iv);
    return ($decrypted !== false) ? $decrypted : '';
}

/**
 * Encrypt a string with AES-256-CBC and return [encrypted_base64, iv_base64].
 */
function encrypt_string_with_iv($plaintext)
{
    $crypto_method = 'aes-256-cbc';
    $crypto_key = substr(hash('sha256', ENCRYPTION_KEY, true), 0, 32);
    $ivlen = openssl_cipher_iv_length($crypto_method);
    $iv = function_exists('random_bytes') ? random_bytes($ivlen) : openssl_random_pseudo_bytes($ivlen);
    $encrypted_raw = openssl_encrypt($plaintext, $crypto_method, $crypto_key, OPENSSL_RAW_DATA, $iv);
    return array(base64_encode($encrypted_raw), base64_encode($iv));
}
