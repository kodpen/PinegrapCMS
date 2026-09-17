<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Site Settings - what the Firewall screen writes: the request filter, the bot policy and the address lists.
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



    $sql_waf_settings = '';

    // Secure Mode lives on this screen. url_scheme predates every firewall
    // column, so it is written whatever the schema version. The variable is
    // read by the caller after the save modules have run, so the redirect that
    // follows a save already uses the scheme that was just chosen.
    $url_scheme = (post_value('secure_mode') ? 'https://' : 'http://');

    if (function_exists('waf_table_has_column')
        && waf_table_has_column('config', 'waf_enabled')
    ) {
        // Clamp the numeric fields. A rate limit of 0 would lock every
        // visitor out of the site the moment blocking is turned on.
        $waf_post_mode = (post_value('waf_mode') === 'block') ? 'block' : 'monitor';

        $waf_post_sensitivity = post_value('waf_sensitivity');

        if (!in_array($waf_post_sensitivity, array('low', 'medium', 'high'), true)) {
            $waf_post_sensitivity = 'medium';
        }

        $waf_post_requests  = max(10, min(60000, (int) post_value('waf_rate_limit_requests')));
        $waf_post_sensitive = max(3,  min(60000, (int) post_value('waf_rate_limit_sensitive')));
        $waf_post_api       = max(10, min(60000, (int) post_value('waf_rate_limit_api')));
        $waf_post_threshold = max(1,  min(1000,  (int) post_value('waf_auto_ban_threshold')));
        $waf_post_minutes   = max(1,  min(43200, (int) post_value('waf_auto_ban_minutes')));
        $waf_post_retention = max(1,  min(3650,  (int) post_value('waf_log_retention_days')));
        $waf_post_max_rows  = max(500, min(5000000, (int) post_value('waf_log_max_rows')));

        $sql_waf_settings =
            "waf_enabled = '" . escape(post_value('waf_enabled') ? 1 : 0) . "',
             waf_mode = '" . escape($waf_post_mode) . "',
             waf_sensitivity = '" . escape($waf_post_sensitivity) . "',
             waf_signature_scan = '" . escape(post_value('waf_signature_scan') ? 1 : 0) . "',
             waf_rate_limit = '" . escape(post_value('waf_rate_limit') ? 1 : 0) . "',
             waf_rate_limit_requests = " . $waf_post_requests . ",
             waf_rate_limit_sensitive = " . $waf_post_sensitive . ",
             waf_auto_ban = '" . escape(post_value('waf_auto_ban') ? 1 : 0) . "',
             waf_auto_ban_threshold = " . $waf_post_threshold . ",
             waf_auto_ban_minutes = " . $waf_post_minutes . ",
             waf_block_attack_tools = '" . escape(post_value('waf_block_attack_tools') ? 1 : 0) . "',
             waf_verify_bots = '" . escape(post_value('waf_verify_bots') ? 1 : 0) . "',
             waf_trusted_proxies = '" . escape(trim((string) post_value('waf_trusted_proxies'))) . "',
             waf_exclusions = '" . escape(trim((string) post_value('waf_exclusions'))) . "',
             waf_blocked_agents = '" . escape(trim((string) post_value('waf_blocked_agents'))) . "',
             waf_log_retention_days = " . $waf_post_retention . ",
             waf_log_max_rows = " . $waf_post_max_rows . ",";

        // The AI bot switches are newer than the firewall base schema
        // (2026.4.4 vs 2026.2.4), so they carry their own column guard: an
        // install upgraded to the firewall but not this far saves the rest of
        // the firewall settings and skips these two.
        if (waf_table_has_column('config', 'waf_allow_ai_fetchers')) {
            $sql_waf_settings .=
                "waf_allow_ai_fetchers = '" . escape(post_value('waf_allow_ai_fetchers') ? 1 : 0) . "',
                 waf_allow_ai_search = '" . escape(post_value('waf_allow_ai_search') ? 1 : 0) . "',";
        }

        // The api.php allowance is the LAST step of 2026.4.4 (4.35), so it is
        // the first column to be missing on a site whose migration file was an
        // older copy than the package's. Written unguarded, it turned the
        // whole settings save into "Unknown column 'waf_rate_limit_api'" -
        // a 500 on the one screen the operator needs to reach the upgrade from.
        if (waf_table_has_column('config', 'waf_rate_limit_api')) {
            $sql_waf_settings .=
                "waf_rate_limit_api = " . $waf_post_api . ",";
        }

        // Security headers, the plain-text log and the two ceilings arrive in
        // one step (4.40); one column guards the set.
        if (waf_table_has_column('config', 'security_headers')) {

            // HSTS is a promise that the site is HTTPS-only; without Secure
            // Mode the site is not, so the promise is not made whatever the
            // box says (the box is disabled on the screen for the same reason).
            $waf_post_hsts = (post_value('security_hsts') && $url_scheme === 'https://') ? 1 : 0;

            $waf_post_csp_mode = post_value('security_csp_mode');

            if (!in_array($waf_post_csp_mode, array('off', 'report', 'enforce'), true)) {
                $waf_post_csp_mode = 'report';
            }

            // A header is one line; what is stored may keep its line breaks
            // for readability, waf_csp_policy() folds them when sending.
            $waf_post_csp_policy = mb_substr(trim((string) post_value('security_csp_policy')), 0, 4000);

            $waf_post_inflight    = max(0, min(50, (int) post_value('waf_inflight_limit')));
            $waf_post_max_minutes = max($waf_post_minutes, min(525600, (int) post_value('waf_auto_ban_max_minutes')));

            $sql_waf_settings .=
                "security_headers = '" . escape(post_value('security_headers') ? 1 : 0) . "',
                 security_frame_protection = '" . escape(post_value('security_frame_protection') ? 1 : 0) . "',
                 security_hsts = '" . $waf_post_hsts . "',
                 security_csp_mode = '" . escape($waf_post_csp_mode) . "',
                 security_csp_policy = '" . escape($waf_post_csp_policy) . "',
                 waf_text_log = '" . escape(post_value('waf_text_log') ? 1 : 0) . "',
                 waf_inflight_limit = " . $waf_post_inflight . ",
                 waf_auto_ban_max_minutes = " . $waf_post_max_minutes . ",";
        }
    }
    // Only what the cards on this screen edit.
    db("UPDATE config
        SET
            " . $sql_waf_settings . "
            url_scheme = '" . escape($url_scheme) . "',
            allowed_bots = '" . escape(trim(post_value('allowed_bots'))) . "',
            block_unknown_bots = '" . escape(post_value('block_unknown_bots')) . "',
            last_modified_user_id = '" . USER_ID . "',
            last_modified_timestamp = UNIX_TIMESTAMP()");


    // Email block list. Its column arrives with migration 4.15, so a database
    // that has not been upgraded yet must not break the rest of this save.
    // Normalised on the way in: trimmed, lower-cased, de-duplicated - the list
    // is matched case-insensitively, and a tidy list is one an operator can
    // still read a year from now.
    $bea_column = @mysqli_query(db::$con, "SHOW COLUMNS FROM config LIKE 'banned_email_addresses'");

    if ($bea_column && (mysqli_num_rows($bea_column) > 0)) {
        $bea_entries = array();

        foreach (preg_split('/[\r\n,;]+/', (string) post_value('banned_email_addresses')) as $bea_entry) {
            $bea_entry = pg_email_fold(trim($bea_entry));

            if (($bea_entry !== '') && !in_array($bea_entry, $bea_entries, true)) {
                $bea_entries[] = $bea_entry;
            }
        }

        db("UPDATE config SET banned_email_addresses = '" . escape(implode(',', $bea_entries)) . "'");

        // Blocking an address has to reach the session that is already open,
        // or the person being blocked simply carries on until their token
        // expires - which is the whole complaint the list exists to answer.
        // Only accounts that actually hold a live token are examined, and only
        // members: a revoked token ends the session on that browser's very
        // next request (initialize_user treats a missing token as fatal).
        //
        // The freshly saved list is read here rather than the constant, which
        // still holds the value this request started with.
        $bea_live = mysqli_query(db::$con,
            "SELECT DISTINCT user.user_id, user.user_email
            FROM auth_tokens
            INNER JOIN user ON auth_tokens.user_id = user.user_id
            WHERE user.user_role = 3");

        if ($bea_live) {
            $bea_list = implode(',', $bea_entries);

            while ($bea_row = mysqli_fetch_assoc($bea_live)) {
                $bea_email = pg_email_fold(trim((string) $bea_row['user_email']));

                if (($bea_email === '') || ($bea_list === '')) {
                    continue;
                }

                $bea_at = strrpos($bea_email, '@');
                $bea_domain = ($bea_at === false) ? '' : substr($bea_email, $bea_at);

                foreach ($bea_entries as $bea_entry) {
                    $bea_entry = ltrim($bea_entry, '*');

                    if (strpos($bea_entry, '@') === false) {
                        $bea_entry = '@' . $bea_entry;
                    }

                    $bea_hit = (strpos($bea_entry, '@') === 0)
                        ? (($bea_domain !== '') && ($bea_domain === $bea_entry))
                        : ($bea_entry === $bea_email);

                    if ($bea_hit) {
                        pg_auth_token_revoke_user((int) $bea_row['user_id']);

                        log_activity(lang(array(
                            'string' => 'sessions ended because the email address was blocked ({var:1})',
                            'vars'   => array($bea_row['user_email']))), $_SESSION['sessionusername'] ?? '');

                        break;
                    }
                }
            }
        }
    }
    
    // ── IP allow / block lists ───────────────────────────────────────────
    //
    // This screen only owns MANUAL entries. The old code TRUNCATEd the whole
    // table, which would now also delete every automatic temporary ban and the
    // entire allow list — so a routine settings save would quietly release
    // every address the firewall had banned, and drop the operator's own
    // lockout protection with it.
    $waf_ip_columns_ready = (function_exists('waf_table_has_column')
        && waf_table_has_column('banned_ip_addresses', 'list_type'));

    if ($waf_ip_columns_ready) {
        $query = "DELETE FROM banned_ip_addresses WHERE source = 'manual'";
    } else {
        $query = "TRUNCATE banned_ip_addresses";
    }

    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');

    // assume that no banned ip addresses are invalid until we determine otherwise
    $invalid_banned_ip_addresses = false;

    $ip_lists_to_save = array('block' => 'banned_ip_addresses');

    if ($waf_ip_columns_ready) {
        $ip_lists_to_save['allow'] = 'allowed_ip_addresses';
    }

    foreach ($ip_lists_to_save as $list_type => $field_name) {
        $submitted = isset($_POST[$field_name]) ? $_POST[$field_name] : '';
        $entries = preg_split('/[\r\n,;]+/', $submitted);
        $valid_entries = array();

        foreach ($entries as $entry) {
            $entry = trim($entry);

            if ($entry === '') {
                continue;
            }

            // Validation lives in waf.php so the format accepted here and the
            // format matched at request time can never drift apart. It accepts
            // a plain address, an IPv4 wildcard, or CIDR notation for either
            // address family — the old check here required exactly four
            // dot-separated parts, so it silently discarded every CIDR range
            // and every IPv6 address an operator typed.
            $is_valid = function_exists('waf_valid_ip_pattern')
                ? waf_valid_ip_pattern($entry)
                : (bool) preg_match('/^(?:\d{1,3}|\*)(?:\.(?:\d{1,3}|\*)){3}$/', $entry);

            if (!$is_valid) {
                $invalid_banned_ip_addresses = true;
                continue;
            }

            $valid_entries[$entry] = $entry;
        }

        foreach ($valid_entries as $entry) {
            if ($waf_ip_columns_ready) {
                // ON DUPLICATE KEY so the unique index added in 2026.2.7
                // cannot turn a re-saved settings screen into a hard
                // "Query failed" error. Re-saving simply refreshes the row.
                $query = "INSERT INTO banned_ip_addresses
                            (ip_address, list_type, source, created_at)
                          VALUES
                            ('" . escape($entry) . "', '" . escape($list_type) . "', 'manual', " . time() . ")
                          ON DUPLICATE KEY UPDATE created_at = VALUES(created_at)";
            } else {
                $query = "INSERT INTO banned_ip_addresses (ip_address) VALUES('" . escape($entry) . "')";
            }

            $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        }
    }

    // if one or more banned IP addresses were invalid, then prepare notice to warn user
    if ($invalid_banned_ip_addresses == true) {
        $liveform->add_notice(lang('One or more banned IP addresses were not added because they were invalid.') );
    }

    // The "Update Bot IP Lists" button submits the settings form with its own
    // name, so the toggles the operator just set are saved first (above) and
    // the fetch runs against the saved state. Forced: pressing the button is
    // the explicit "now" the throttle exists to protect against.
    if (post_value('waf_refresh_ranges') && function_exists('pg_waf_refresh_ai_ranges')) {

        $waf_ranges_results = pg_waf_refresh_ai_ranges(true);

        if (is_array($waf_ranges_results)) {

            $waf_ranges_updated = 0;
            $waf_ranges_failed  = 0;

            foreach ($waf_ranges_results as $waf_ranges_result) {
                if ($waf_ranges_result['status'] === 'updated') {
                    $waf_ranges_updated++;
                } elseif ($waf_ranges_result['status'] === 'failed') {
                    $waf_ranges_failed++;
                }
            }

            if ($waf_ranges_failed == 0) {
                $liveform->add_notice(lang(array(
                    'string' => 'Bot IP lists refreshed: {var:1} list{suffix:1} updated.',
                    'vars'   => $waf_ranges_updated,
                    'suffix' => ($waf_ranges_updated == 1 ? '' : 's'),
                )));
            } else {
                $liveform->add_notice(lang(array(
                    'string' => 'Bot IP lists: {var:1} updated, {var:2} could not be fetched. The stored copies were kept.',
                    'vars'   => array($waf_ranges_updated, $waf_ranges_failed),
                )));

                // Name the first failure's cause. Four sources failing at once
                // is nearly always one local condition (no CA bundle, blocked
                // outbound), so one reason diagnoses all of them and four
                // repeated hints would only bury it.
                foreach ($waf_ranges_results as $waf_ranges_provider => $waf_ranges_result) {
                    if ($waf_ranges_result['status'] === 'failed' && !empty($waf_ranges_result['reason'])) {
                        $liveform->add_notice(lang(array(
                            'string' => 'Bot IP list fetch error: {var:1}',
                            'vars'   => $waf_ranges_provider . ' — ' . $waf_ranges_result['reason'],
                        )));
                        break;
                    }
                }
            }
        }
    }
