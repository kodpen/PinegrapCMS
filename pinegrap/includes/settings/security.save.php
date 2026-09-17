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


    // Device limit lives in a column a not-yet-upgraded database may not have
    // yet (migration 4.14). Save it on its own, only when the column exists, so
    // the rest of Settings still saves either way.
    $rml_col = @mysqli_query(db::$con, "SHOW COLUMNS FROM config LIKE 'remember_me_device_limit'");
    if ($rml_col && mysqli_num_rows($rml_col) > 0) {
        db("UPDATE config SET remember_me_device_limit_enabled = '" . ((post_value('remember_me_device_limit_enabled') == '1') ? 1 : 0) . "', remember_me_device_limit = '" . max(1, (int) post_value('remember_me_device_limit')) . "'");

        // Strict mode arrives with migration 4.16, one release later than the
        // limit itself, so it gets its own column check.
        $strict_column = @mysqli_query(db::$con, "SHOW COLUMNS FROM config LIKE 'remember_me_device_limit_strict'");

        if ($strict_column && (mysqli_num_rows($strict_column) > 0)) {
            db("UPDATE config SET remember_me_device_limit_strict = '" . ((post_value('remember_me_device_limit_strict') == '1') ? 1 : 0) . "'");
        }
    }

    // Google client secret: stored encrypted (AES-256-CBC + IV) but written
    // as shown - the field carries the current value like the other API
    // secrets on this screen, so whatever is in the field on save is the
    // truth. Clearing the field clears the stored secret.
    $oauth_google_secret_input = trim(post_value('oauth_google_client_secret'));
    if ($oauth_google_secret_input !== '') {
        list($oauth_google_secret_enc, $oauth_google_secret_iv) = encrypt_string_with_iv($oauth_google_secret_input);
        $query = "UPDATE config SET oauth_google_client_secret = '" . escape($oauth_google_secret_enc) . "', oauth_google_secret_iv = '" . escape($oauth_google_secret_iv) . "'";
        mysqli_query(db::$con, $query) or output_error('Query failed.');
    } else {
        db("UPDATE config SET oauth_google_client_secret = '', oauth_google_secret_iv = ''");
    }
