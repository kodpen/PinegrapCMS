<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Site Settings - what the Firewall and HTTPS screen writes: the request filter, the trusted proxies, the bot policy, the address lists and the HTTPS headers.
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

    // Turning Secure Mode on is accepted only from a request this server itself
    // sees as HTTPS. Saved from a request it sees as plain HTTP, the mode would
    // redirect every request - the operator's next one included - to a scheme
    // that never reaches the server, and the site is locked out. Only the
    // OFF -> ON transition is checked: a site already on HTTPS keeps saving,
    // and turning the mode off is how a locked-out site is recovered.
    //
    // check_if_request_is_secure() is the one definition of "is this HTTPS"
    // (direct TLS, or forwarded headers from a peer on Trusted Proxies, a
    // Cloudflare edge or under TRUST_PROXY_SSL_HEADERS). pg_request_is_https()
    // is deliberately not used here: it reads the forwarded headers without
    // the peer check, so a proxy that terminates SSL in front of a plain-HTTP
    // origin (Cloudflare "Flexible") would pass the check and the saved
    // setting would loop the site.
    //
    // When the headers say https but the peer is not trusted yet, the peer's
    // own address can be listed in the same save (pg_https_peer_offer(): a
    // loopback or private peer, never a public one or its network). The box
    // under Secure Mode says so on the screen; with it ticked, the entries go
    // into the Trusted Proxies list written below, the peer is checked against
    // that list, and the save goes on. Otherwise the message names the peer
    // and the entries, and nothing is written.
    $waf_secure_mode_entries = array();

    if (($url_scheme === 'https://') && (URL_SCHEME !== 'https://') && !check_if_request_is_secure()) {

        $secure_mode_test_link = '<a class="alert-link" href="https://' . h(HOSTNAME_SETTING) . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/test_secure_mode.php" target="_blank">' . lang('Secure Mode test page') . '</a>';

        $secure_mode_claimed = pg_forwarded_https($_SERVER);
        $secure_mode_peer = isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : '';
        $secure_mode_offer = $secure_mode_claimed ? pg_https_peer_offer($secure_mode_peer) : array('kind' => '', 'entries' => array());

        // The list column arrives with the firewall's schema step; before it
        // nothing can be listed here, and the message says to list the proxy.
        $secure_mode_offer_ready = $secure_mode_offer['entries']
            && function_exists('waf_table_has_column')
            && waf_table_has_column('config', 'waf_trusted_proxies')
            && function_exists('waf_proxy_list_on_save');

        $secure_mode_trusted = false;

        if ($secure_mode_offer_ready && post_value('secure_mode_trust_proxy')) {
            // The list as the save below will store it, offered entries
            // included, checked against the peer before anything is written.
            $secure_mode_list = waf_proxy_list_on_save(
                trim((string) post_value('waf_trusted_proxies')) . ',' . implode(',', $secure_mode_offer['entries']),
                (string) db_value("SELECT waf_trusted_proxies FROM config"));

            if (pg_https_peer_trusted($secure_mode_peer, $secure_mode_list)) {
                $secure_mode_trusted = true;
                $waf_secure_mode_entries = $secure_mode_offer['entries'];
            }
        }

        if (!$secure_mode_trusted) {

            if (!$secure_mode_claimed) {
                $liveform->add_error(lang(array(
                    'string' => 'Secure Mode was not enabled: this request did not arrive over HTTPS, so no encrypted connection is reaching this server. Install an SSL certificate first. Then check what this server sees on the {var:1} and save this setting from a session opened over HTTPS.',
                    'vars'   => $secure_mode_test_link)));
            } elseif ($secure_mode_offer_ready) {
                $liveform->add_error(lang(array(
                    'string' => 'Secure Mode was not enabled: this request reached the server through a proxy at {var:1} that reports HTTPS but is not listed under Trusted Proxies. Tick "Add this proxy to Trusted Proxies" under Secure Mode to list {var:2} and turn Secure Mode on in one step. The {var:3} shows what this server sees.',
                    'vars'   => array(h($secure_mode_peer), h(implode(', ', $secure_mode_offer['entries'])), $secure_mode_test_link))));
            } elseif ($secure_mode_offer['kind'] === 'public') {
                $liveform->add_error(lang(array(
                    'string' => 'Secure Mode was not enabled: a CDN or load balancer at {var:1} reports HTTPS, but this server itself sees plain HTTP and that address is not listed under Trusted Proxies. List all of its published address ranges there first (Cloudflare is recognised on its own), or switch it to Full (end-to-end) mode. Then check what this server sees on the {var:2} and save this setting again.',
                    'vars'   => array(h($secure_mode_peer), $secure_mode_test_link))));
            } else {
                $liveform->add_error(lang(array(
                    'string' => 'Secure Mode was not enabled: a proxy at {var:1} reports HTTPS, but this server itself sees plain HTTP and that address is not listed under Trusted Proxies. List it there first, or switch the proxy to Full (end-to-end) mode. Then check what this server sees on the {var:2} and save this setting again.',
                    'vars'   => array(h($secure_mode_peer), $secure_mode_test_link))));
            }

            // Nothing is written. The caller redirects on $url_scheme, and the
            // scheme this request arrived on is the only one known to work.
            $url_scheme = URL_SCHEME;

            return;
        }
    }

    // ── The operator's own address ──────────────────────────────────────
    //
    // Checked before anything is written: a block entry covering the address
    // this request comes from (a mistyped range, an ISP's /8, 0.0.0.0/0 left
    // from a test) shuts the operator out of this screen in every mode and at
    // the ban shield, and never expires. An allow entry covering the address
    // wins at request time, so it makes the save safe.
    $waf_own_ip = function_exists('waf_client_ip') ? waf_client_ip() : '';
    $waf_own_blocks = pg_waf_own_address_blocks(
        $waf_own_ip,
        preg_split('/[\r\n,;]+/', (string) post_value('banned_ip_addresses')),
        preg_split('/[\r\n,;]+/', (string) post_value('allowed_ip_addresses')));

    if ($waf_own_blocks) {
        $liveform->mark_error('banned_ip_addresses', lang(array(
            'string' => 'Nothing was saved: these blocked IP addresses cover your own address {var:1}: {var:2}. Remove them, or add your address to the allowed IP addresses.',
            'vars'   => array(h($waf_own_ip), h(implode(', ', $waf_own_blocks))))));

        return;
    }

    // The firewall's numbers are refused when blank or out of range rather
    // than clamped: the clamps below keep a stored value in range, but an
    // emptied box read as 0 became the strictest bound (ten requests a
    // minute for every visitor, a ban after one offence). Checked before the
    // country block below writes anything.
    if (function_exists('waf_table_has_column') && waf_table_has_column('config', 'waf_enabled')) {
        foreach (array(
            'waf_rate_limit_requests'  => array(lang('All Requests'), 10, 60000),
            'waf_rate_limit_sensitive' => array(lang('Sign-in, Forms and Checkout'), 3, 60000),
            'waf_rate_limit_api'       => array(lang('Live Chat and Store Pages'), 10, 60000),
            'waf_inflight_limit'       => array(lang('Open at Once'), 0, 50),
            'waf_auto_ban_threshold'   => array(lang('Ban After'), 1, 1000),
            'waf_auto_ban_minutes'     => array(lang('Ban Duration'), 1, 43200),
            'waf_auto_ban_max_minutes' => array(lang('Longest Ban'), 1, 525600),
            'waf_log_retention_days'   => array(lang('Log Retention'), 1, 3650),
            'waf_log_max_rows'         => array(lang('Log Size Limit'), 500, 5000000),
        ) as $waf_number_field => $waf_number_rule) {
            if (array_key_exists($waf_number_field, $_POST) && (pg_whole_number_in_range(post_value($waf_number_field), $waf_number_rule[1], $waf_number_rule[2]) === null)) {
                $liveform->mark_error($waf_number_field, lang(array('string' => '{var:1} must be a whole number from {var:2} to {var:3}.', 'vars' => $waf_number_rule)));
            }
        }

        if ($liveform->check_form_errors()) {
            return;
        }
    }

    // ── Country restriction (2026.4.10) ─────────────────────────────────
    //
    // Checked before anything is written: a list with a code that names no
    // country, an allow list naming none, or settings that would refuse the
    // operator saving them leave every setting on this screen as it was.
    $waf_country_save = (function_exists('waf_table_has_column') && waf_table_has_column('config', 'waf_country_mode'));
    $waf_country_post_mode = 'off';

    if ($waf_country_save) {

        require_once(PG_FUNCTIONS_DIR . '/includes/sales_map.php');

        $waf_country_post_mode = in_array(post_value('waf_country_mode'), array('allow', 'block'), true) ? (string) post_value('waf_country_mode') : 'off';
        $waf_country_post_list = array();
        $waf_country_post_wrong = array();
        $waf_country_known = pg_sales_map_iso_countries();

        foreach (preg_split('/[\s,;]+/', strtoupper(trim((string) post_value('waf_country_list')))) as $waf_country_entry) {
            if ($waf_country_entry === '') {
                continue;
            }

            if (($waf_country_entry === 'T1') || isset($waf_country_known[$waf_country_entry])) {
                $waf_country_post_list[$waf_country_entry] = $waf_country_entry;
            } else {
                $waf_country_post_wrong[$waf_country_entry] = $waf_country_entry;
            }
        }

        $waf_country_post_list = array_values($waf_country_post_list);
        $waf_country_post_unknown = post_value('waf_country_unknown') ? 1 : 0;
        $waf_country_post_panel = post_value('waf_country_exempt_panel') ? 1 : 0;

        if ($waf_country_post_wrong) {
            $liveform->mark_error('waf_country_list', lang(array(
                'string' => 'Nothing was saved: these are not country codes: {var:1}.',
                'vars'   => array(h(implode(', ', $waf_country_post_wrong))))));

            return;
        }

        if (($waf_country_post_mode === 'allow') && !$waf_country_post_list) {
            $liveform->mark_error('waf_country_list', lang('Nothing was saved: name at least one country to let in.'));

            return;
        }

        // The operator's own request, judged by the settings as submitted:
        // with the panel restricted, settings that refuse it would lock them
        // out of the screen that changes them. An address on the allow list
        // being saved is let in whatever its country.
        if (($waf_country_post_mode !== 'off') && !$waf_country_post_panel) {

            $waf_country_own_ip = waf_client_ip();
            $waf_country_own_allowed = false;

            foreach (preg_split('/[\r\n,;]+/', (string) post_value('allowed_ip_addresses')) as $waf_country_own_entry) {
                $waf_country_own_entry = trim($waf_country_own_entry);

                if (($waf_country_own_entry !== '') && waf_valid_ip_pattern($waf_country_own_entry) && waf_ip_matches($waf_country_own_ip, $waf_country_own_entry)) {
                    $waf_country_own_allowed = true;
                    break;
                }
            }

            $waf_country_own = pg_geo_country();

            if (!$waf_country_own_allowed && waf_country_refused($waf_country_own['code'], $waf_country_post_mode, $waf_country_post_list, (bool) $waf_country_post_unknown)) {
                $liveform->mark_error('waf_country_mode', lang(array(
                    'string' => 'Nothing was saved: these settings would refuse you, because this request comes from {var:1}. Let your country in, keep the control panel open, or add your address to the allowed IP addresses.',
                    'vars'   => array(($waf_country_own['code'] !== '') ? pg_geo_country_name($waf_country_own['code']) . ' (' . $waf_country_own['code'] . ')' : lang('an unknown country')))));

                return;
            }
        }

        db("UPDATE config SET
            waf_country_mode = '" . escape($waf_country_post_mode) . "',
            waf_country_list = '" . escape(implode(',', $waf_country_post_list)) . "',
            waf_country_unknown = '" . $waf_country_post_unknown . "',
            waf_country_exempt_panel = '" . $waf_country_post_panel . "',
            waf_country_exempt_api = '" . (post_value('waf_country_exempt_api') ? 1 : 0) . "',
            waf_country_exempt_bots = '" . (post_value('waf_country_exempt_bots') ? 1 : 0) . "'");
    }

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

        $waf_post_requests  = max(10, min(60000, pg_parse_whole_number(post_value('waf_rate_limit_requests'), 60000)));
        $waf_post_sensitive = max(3,  min(60000, pg_parse_whole_number(post_value('waf_rate_limit_sensitive'), 60000)));
        $waf_post_api       = max(10, min(60000, pg_parse_whole_number(post_value('waf_rate_limit_api'), 60000)));
        $waf_post_threshold = max(1,  min(1000,  pg_parse_whole_number(post_value('waf_auto_ban_threshold'), 1000)));
        $waf_post_minutes   = max(1,  min(43200, pg_parse_whole_number(post_value('waf_auto_ban_minutes'), 43200)));
        $waf_post_retention = max(1,  min(3650,  pg_parse_whole_number(post_value('waf_log_retention_days'), 3650)));
        $waf_post_max_rows  = max(500, min(5000000, pg_parse_whole_number(post_value('waf_log_max_rows'), 5000000)));

        // A proxy added or edited here reads X-Forwarded-For only unless its
        // vendor header is named; one already stored keeps its reading.
        $waf_post_proxies = trim((string) post_value('waf_trusted_proxies'));

        // The peer the Secure Mode offer above listed goes in with the list.
        if ($waf_secure_mode_entries) {
            $waf_post_proxies = trim($waf_post_proxies . ',' . implode(',', $waf_secure_mode_entries), ',');
        }

        if (function_exists('waf_proxy_list_on_save')) {
            $waf_post_proxies = waf_proxy_list_on_save($waf_post_proxies, (string) db_value("SELECT waf_trusted_proxies FROM config"));
        }

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
             waf_trusted_proxies = '" . escape($waf_post_proxies) . "',
             waf_exclusions = '" . escape(trim((string) post_value('waf_exclusions'))) . "',
             waf_blocked_agents = '" . escape(trim((string) post_value('waf_blocked_agents'))) . "',
             waf_log_retention_days = " . $waf_post_retention . ",
             waf_log_max_rows = " . $waf_post_max_rows . ",";

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

            $waf_post_inflight    = max(0, min(50, pg_parse_whole_number(post_value('waf_inflight_limit'), 50)));
            $waf_post_max_minutes = max($waf_post_minutes, min(525600, pg_parse_whole_number(post_value('waf_auto_ban_max_minutes'), 525600)));

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

    // The proxy the one-step offer under Secure Mode listed is on record: the
    // category's own log line says only that the settings changed.
    if ($waf_secure_mode_entries) {
        log_activity(lang(array(
            'string' => 'Secure Mode was turned on and the proxy at {var:1} was added to Trusted Proxies as {var:2}',
            'vars'   => array(h($secure_mode_peer), h(implode(', ', $waf_secure_mode_entries))))), $_SESSION['sessionusername']);
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
                            (?, ?, 'manual', ?)
                          ON DUPLICATE KEY UPDATE created_at = VALUES(created_at)";
                $query_values = array((string) $entry, (string) $list_type, time());
            } else {
                $query = "INSERT INTO banned_ip_addresses (ip_address) VALUES(?)";
                $query_values = array((string) $entry);
            }

            $result = db_q($query, $query_values) or output_error('Query failed.');
        }
    }

    // if one or more banned IP addresses were invalid, then prepare notice to warn user
    if ($invalid_banned_ip_addresses == true) {
        $liveform->add_notice(lang('One or more banned IP addresses were not added because they were invalid.') );
    }

    // The ban mirror (data/temp/ban_shield.txt) is rewritten now, not within
    // five minutes: a block entry removed here, the mode switched to Monitor
    // (whose mirror leaves automatic bans out) or a proxy listed must reach
    // the layer in front of the database before the operator's next request.
    // The config row is read again first: this request began with the old one.
    pg_waf_save_refresh_mirror();


    // The location database, when the button asks for it or the country
    // restriction is on and the site has none yet. Last, so a slow download
    // comes after everything this screen saves.
    if ($waf_country_save) {
        pg_geo_db_refresh_for_screen($liveform, (bool) post_value('geo_db_refresh'), $waf_country_post_mode !== 'off');
    }
