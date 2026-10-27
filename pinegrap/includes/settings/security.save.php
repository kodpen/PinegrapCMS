<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Site Settings - what the Security screen writes: who gets an account and how they sign in.
 *
 * Runs in the scope of includes/settings/screen.php, after the token check and
 * after post_value() is defined. Only the columns edited by the cards on this
 * screen are written, so a screen that was not submitted cannot have its
 * settings overwritten -- which is what the single screen used to do.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_SETTINGS_ENTRY')) {
    exit;
}

    // Sign in with Apple's application is checked before anything on this
    // screen is written: a refused field leaves every setting as it was, and
    // the form keeps what was typed.
    $apple_post = (post_value('apple_client_id') !== NULL)
        && function_exists('pg_signin_schema_ready') && pg_signin_schema_ready();

    if ($apple_post) {
        $apple_client_id = trim((string) post_value('apple_client_id'));
        $apple_team_id = strtoupper(trim((string) post_value('apple_team_id')));
        $apple_key_id = strtoupper(trim((string) post_value('apple_key_id')));
        $apple_key = trim((string) post_value('apple_private_key'));

        if (($apple_client_id !== '') && !preg_match('/^[A-Za-z0-9.\-]{1,255}$/', $apple_client_id)) {
            $liveform->mark_error('apple_client_id', lang('A Services ID is written like a domain in reverse, for example com.example.signin.'));
        }

        foreach (array('apple_team_id' => $apple_team_id, 'apple_key_id' => $apple_key_id) as $apple_field => $apple_value) {
            if (($apple_value !== '') && !preg_match('/^[A-Z0-9]{10}$/', $apple_value)) {
                $liveform->mark_error($apple_field, lang('Apple writes this as ten letters and digits.'));
            }
        }

        $apple_problem = ($apple_key !== '') ? pg_signin_apple_key_problem($apple_key) : '';

        if ($apple_problem !== '') {
            $liveform->mark_error('apple_private_key', $apple_problem);
        }

        if ((($apple_client_id !== '') || ($apple_key !== '')) && !pg_connect_can_seal()) {
            $liveform->mark_error('apple_private_key', lang('This site has no encryption key, so it cannot keep the permissions of a connected account.'));
        }

        if ($liveform->check_form_errors()) {
            return;
        }
    }

    // The Audit Trail card: checked before anything is written, and written
    // on its own, only once the 2026.11.0 step has added the column and the
    // card's field was posted.
    $audit_retention_post = (post_value('audit_log_retention_years') !== NULL)
        && pg_schema_has('config', 'audit_log_retention_years');
    $audit_retention_input = $audit_retention_post ? pg_audit_retention_years_from_input(post_value('audit_log_retention_years')) : null;

    if ($audit_retention_post && ($audit_retention_input === null)) {
        $audit_retention_limits = pg_privacy_retention_limits();

        $liveform->mark_error('audit_log_retention_years', lang(array('string' => 'Keep audit trail lines for 0 years (no limit), or for {var:1} to {var:2} years.', 'vars' => array($audit_retention_limits['audit_min'], $audit_retention_limits['audit_max']))));

        return;
    }

    // The Spam Protection card's CAPTCHA provider: checked, and its keys
    // asked about, before anything is written, like Apple's application.
    $captcha_settings_post = function_exists('pg_captcha_settings_check') ? pg_captcha_settings_check($_POST, $liveform) : null;

    if ($liveform->check_form_errors()) {
        return;
    }



    // ── Firewall settings ────────────────────────────────────────────────
    // Built as a fragment rather than inlined, so an install that has not run
    // the 2026.2.4 upgrade simply saves nothing here instead of failing the
    // whole settings save with an unknown-column error.
    // Sign-in throttle settings. Built as a fragment for the same reason as
    // the firewall block below: an install that has not run the 2026.4.4
    // upgrade saves nothing here instead of failing the whole settings save
    // with an unknown-column error.
    $sql_login_throttle = '';

    if (function_exists('waf_table_has_column')
        && waf_table_has_column('config', 'login_throttle')
    ) {
        // Clamped to the same bounds pg_login_throttle_limits() enforces at
        // read time, so a value typed here cannot mean one thing in the box
        // and another in the counter.
        $login_post_attempts = max(1, min(1000,  (int) post_value('login_throttle_attempts')));
        $login_post_minutes  = max(1, min(1440,  (int) post_value('login_throttle_minutes')));
        $login_post_lockout  = max(1, min(43200, (int) post_value('login_throttle_lockout')));

        $sql_login_throttle =
            "login_throttle = '" . escape(post_value('login_throttle') ? 1 : 0) . "',
             login_throttle_attempts = " . $login_post_attempts . ",
             login_throttle_minutes = " . $login_post_minutes . ",
             login_throttle_lockout = " . $login_post_lockout . ",";

        // The sign-in question arrives with 4.40; own guard, same reason.
        if (waf_table_has_column('config', 'login_throttle_captcha_after')) {
            $sql_login_throttle .=
                "login_throttle_captcha_after = " . max(0, min(100, (int) post_value('login_throttle_captcha_after'))) . ",";
        }
    }
    // Form submission limits (2026.11.0): the same fragment pattern, clamped
    // to the bounds pg_cf_throttle_limits() applies when it reads them.
    $sql_form_limits = '';

    if (function_exists('pg_schema_has') && pg_schema_has('config', 'form_limit_ip_hour')) {
        $sql_form_limits =
            "form_limit_ip_hour = " . max(0, min(10000, (int) post_value('form_limit_ip_hour'))) . ",
             form_limit_recipient_day = " . max(0, min(10000, (int) post_value('form_limit_recipient_day'))) . ",
             form_limit_domain_hour = " . max(0, min(10000, (int) post_value('form_limit_domain_hour'))) . ",
             form_spam_blacklist = " . (post_value('form_spam_blacklist') ? 1 : 0) . ",";
    }

    // Only what the cards on this screen edit.
    db("UPDATE config
        SET
            captcha = '" . escape(post_value('captcha')) . "',
            mass_deletion = '" . escape(post_value('mass_deletion')) . "',
            strong_password = '" . escape(post_value('strong_password')) . "',
            password_hint = '" . escape(post_value('password_hint')) . "',
            remember_me = '" . escape(post_value('remember_me')) . "',
            forgot_password_link = '" . escape(post_value('forgot_password_link')) . "',
            " . $sql_login_throttle . "
            " . $sql_form_limits . "
            oauth_google_enabled = '" . escape(post_value('oauth_google_enabled')) . "',
            oauth_google_client_id = '" . escape(trim(post_value('oauth_google_client_id'))) . "',
            registration_contact_group_id = '" . escape(post_value('registration_contact_group_id')) . "',
            registration_email_address = '" . escape(post_value('registration_email_address')) . "',
            member_id_label = '" . escape(post_value('member_id_label')) . "',
            membership_contact_group_id = '" . escape(post_value('membership_contact_group_id')) . "',
            membership_email_address = '" . escape(post_value('membership_email_address')) . "',
            membership_expiration_warning_email = '" . escape(post_value('membership_expiration_warning_email')) . "',
            membership_expiration_warning_email_subject = '" . escape(post_value('membership_expiration_warning_email_subject')) . "',
            membership_expiration_warning_email_page_id = '" . escape(post_value('membership_expiration_warning_email_page_id')) . "',
            membership_expiration_warning_email_days_before_expiration = '" . escape(post_value('membership_expiration_warning_email_days_before_expiration')) . "',
            last_modified_user_id = '" . USER_ID . "',
            last_modified_timestamp = UNIX_TIMESTAMP()");

    if (function_exists('pg_captcha_settings_save')) {
        pg_captcha_settings_save($captcha_settings_post, USER_ID, $liveform);
    }

    if ($audit_retention_post) {
        db("UPDATE config SET audit_log_retention_years = '" . (int) $audit_retention_input . "'");
    }


    // Device limit lives in a column a not-yet-upgraded database may not have
    // yet (migration 4.14). Save it on its own, only when the column exists, so
    // the rest of Settings still saves either way.
    if (function_exists('pg_schema_has') && pg_schema_has('config', 'remember_me_device_limit')) {
        db("UPDATE config SET remember_me_device_limit_enabled = '" . ((post_value('remember_me_device_limit_enabled') == '1') ? 1 : 0) . "', remember_me_device_limit = '" . max(1, (int) post_value('remember_me_device_limit')) . "'");

        // Strict mode arrives with migration 4.16, one release later than the
        // limit itself, so it gets its own column check.
        if (pg_schema_has('config', 'remember_me_device_limit_strict')) {
            db("UPDATE config SET remember_me_device_limit_strict = '" . ((post_value('remember_me_device_limit_strict') == '1') ? 1 : 0) . "'");
        }
    }

    // Two-step verification (8.40): own query, only when the column exists
    // and the field was posted. A disabled select is not posted; reading the
    // missing value as 0 would oblige administrators to have a second factor.
    if (function_exists('waf_table_has_column')
        && waf_table_has_column('config', 'mfa_required_role')
        && (post_value('mfa_required_role') !== NULL)
    ) {
        $mfa_post_role = (int) post_value('mfa_required_role');

        if (!in_array($mfa_post_role, array(0, 1, 2, 3, 99), true)) {
            $mfa_post_role = 99;
        }

        $mfa_previous_role = (int) db_value("SELECT mfa_required_role FROM config");

        // 99 is "no role"; as a bound below it covers nobody.
        $mfa_previous_bound = ($mfa_previous_role >= 99) ? -1 : $mfa_previous_role;

        db("UPDATE config SET mfa_required_role = " . $mfa_post_role);

        // Accounts the rule now covers but did not before, and that have no
        // second factor (authenticator key or passkey), lose their remembered
        // devices: a remembered sign-in would otherwise keep opening a
        // session on a password typed before the rule applied. The operator
        // saving the rule keeps their own session (the session is bound to
        // its token) and is asked at their next sign-in; a pinned device is left to the sign-in gate, which
        // sends it to setup on its next use.
        if (($mfa_post_role < 99) && ($mfa_post_role > $mfa_previous_role)
            && function_exists('pg_mfa_table_exists') && pg_mfa_table_exists()
            && function_exists('pg_auth_tokens_table_exists') && pg_auth_tokens_table_exists()) {

            $mfa_uncovered_ids = db_values(
                "SELECT user.user_id
                FROM user
                LEFT JOIN user_mfa ON user_mfa.user_id = user.user_id
                WHERE user.user_role <= " . $mfa_post_role . "
                    AND user.user_role > " . $mfa_previous_bound . "
                    AND user.user_id <> '" . (int) USER_ID . "'
                    AND (user_mfa.user_id IS NULL OR user_mfa.enabled_at = 0 OR user_mfa.totp_secret = '')"
                    // A passkey satisfies the rule where the site can ask for one.
                    . ((function_exists('pg_passkeys_available') && pg_passkeys_available())
                        ? " AND NOT EXISTS (SELECT 1 FROM user_passkeys WHERE user_passkeys.user_id = user.user_id)"
                        : ""));

            foreach ($mfa_uncovered_ids as $mfa_uncovered_id) {
                db("DELETE FROM auth_tokens WHERE user_id = '" . (int) $mfa_uncovered_id . "'"
                    . (pg_auth_tokens_have_pin() ? " AND pinned = 0" : ""));
            }
        }
    }

    // Sign-in with a passkey alone (11.0.41). A switch that is off is not
    // posted, so the column is written whenever it exists.
    if (function_exists('pg_schema_has') && pg_schema_has('config', 'passkey_signin')) {
        db("UPDATE config SET passkey_signin = " . ((post_value('passkey_signin') == '1') ? 1 : 0));
    }

    // Sign in with Microsoft and Apple (10.74): the switches, and Apple's
    // application. Both wait for the upgrade, like the columns above.
    if (function_exists('pg_signin_schema_ready') && pg_signin_schema_ready() && pg_schema_has('config', 'signin_providers')) {

        $signin_list = array();

        foreach (array('microsoft', 'apple') as $signin_key) {
            if (post_value('signin_' . $signin_key) == '1') {
                $signin_list[] = $signin_key;
            }
        }

        // A provider this screen has no card for keeps its switch.
        foreach (pg_connect_scope_list(str_replace(',', ' ', (string) db_value("SELECT signin_providers FROM config"))) as $signin_key) {
            if (!in_array($signin_key, array('microsoft', 'apple'), true)) {
                $signin_list[] = $signin_key;
            }
        }

        db("UPDATE config SET signin_providers = '" . escape(implode(',', $signin_list)) . "'");

        if ($apple_post) {
            $apple_existing = pg_connect_app('apple');

            // Nothing typed and nothing stored: no row is opened for an
            // application the site does not use.
            if (($apple_client_id !== '') || ($apple_team_id !== '') || ($apple_key_id !== '') || ($apple_key !== '')
                || (is_array($apple_existing) && ($apple_existing['source'] === 'connect'))) {
                pg_connect_app_save(
                    'apple',
                    $apple_client_id,
                    ($apple_key !== '') ? pg_signin_pem_private_key($apple_key) : null,
                    array('team_id' => $apple_team_id, 'key_id' => $apple_key_id),
                    true,
                    USER_ID);
            }
        }
    }

    // Google client secret: stored encrypted (AES-256-CBC + IV) but written
    // as shown - the field carries the current value like the other API
    // secrets on this screen, so whatever is in the field on save is the
    // truth. Clearing the field clears the stored secret. Each encryption
    // takes a new random IV, so the stored secret is compared first and an
    // unchanged one is left as it is: re-encrypting it would change both
    // columns and log "changed" in the audit trail on every save.
    $oauth_google_secret_input = trim(post_value('oauth_google_client_secret'));
    $oauth_google_secret_stored = db_item("SELECT oauth_google_client_secret, oauth_google_secret_iv FROM config LIMIT 1");
    $oauth_google_secret_was = (is_array($oauth_google_secret_stored) && ((string) $oauth_google_secret_stored['oauth_google_client_secret'] !== ''))
        ? decode_ssl_keys($oauth_google_secret_stored['oauth_google_client_secret'], $oauth_google_secret_stored['oauth_google_secret_iv'])
        : '';
    $oauth_google_secret_empty = !is_array($oauth_google_secret_stored)
        || (((string) $oauth_google_secret_stored['oauth_google_client_secret'] === '') && ((string) $oauth_google_secret_stored['oauth_google_secret_iv'] === ''));
    // A stored value that no longer decrypts reads as '' and is still
    // cleared when the field is empty.
    $oauth_google_secret_write = ($oauth_google_secret_input !== $oauth_google_secret_was)
        || (($oauth_google_secret_input === '') && !$oauth_google_secret_empty);
    if (!$oauth_google_secret_write) {
        // Unchanged: both columns stay as they are.
    } elseif ($oauth_google_secret_input !== '') {
        list($oauth_google_secret_enc, $oauth_google_secret_iv) = encrypt_string_with_iv($oauth_google_secret_input);
        $query = "UPDATE config SET oauth_google_client_secret = '" . escape($oauth_google_secret_enc) . "', oauth_google_secret_iv = '" . escape($oauth_google_secret_iv) . "'";
        mysqli_query(db::$con, $query) or output_error('Query failed.');
    } else {
        db("UPDATE config SET oauth_google_client_secret = '', oauth_google_secret_iv = ''");
    }
