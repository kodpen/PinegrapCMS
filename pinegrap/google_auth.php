<?php
/**
 * PineGrap - Enterprise Website Platform
 *
 * Originally developed as LiveSite by Camelback Web Architects.
 * Since 2017, maintained and evolved by Erdal Güral (Kodpen) under the name PineGrap.
 * The final LiveSite update (2019) has been integrated into PineGrap.
 * LiveSite remains available as a separate downloadable legacy version.
 *
 * @author      Camelback Web Architects
 *              Erdal Güral (Kodpen)
 * @link        https://livesite.com
 *              https://kodpen.com
 * @copyright   2001–2019 Camelback Consulting, Inc.
 *              2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 *
 * Google Sign-In - OAuth 2.0 Authorization Code, server to server.
 *
 * No JavaScript SDK and no JWT signature verification: the id_token is read
 * straight from Google's token endpoint over TLS, authenticated with the client
 * secret, so its origin is already proven - the same way the Parasut OAuth flow
 * already trusts its token response. Zero external dependencies (this project
 * has no Composer).
 *
 * One file, two phases, chosen by whether Google handed us a ?code=:
 *   no code  -> START:    plant state + nonce, redirect to the consent screen.
 *   ?code=   -> CALLBACK: exchange the code, read sub / email / email_verified,
 *                         find-by-sub, else link a verified email, else create,
 *                         then sign in.
 *
 * The redirect URI to register in Google Cloud Console is exactly this file's
 * URL with no query string (the settings screen prints it).
 */

include('init.php');

// The feature has to be switched on and configured, or there is nothing to do.
if (
    !defined('OAUTH_GOOGLE_ENABLED') || !OAUTH_GOOGLE_ENABLED
    || !defined('OAUTH_GOOGLE_CLIENT_ID') || OAUTH_GOOGLE_CLIENT_ID === ''
) {
    go(PATH);
}

// The redirect URI must match what the operator registered in Google Cloud
// Console byte for byte. Built the same way the membership/registration flows
// build their own front-end URLs.
$redirect_uri = URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/google_auth.php';

// ---------------------------------------------------------------------------
// CALLBACK - Google has redirected back with ?code= (and ?state=).
// ---------------------------------------------------------------------------
if (isset($_GET['code'])) {

    // CSRF: the state we planted at start must return unchanged, then it is
    // spent. A missing or mismatched state means this callback was not one we
    // started.
    $expected_state = $_SESSION['software']['google_oauth']['state'] ?? '';
    $expected_nonce = $_SESSION['software']['google_oauth']['nonce'] ?? '';
    $send_to        = $_SESSION['software']['google_oauth']['send_to'] ?? '';
    // Decided at START and read back from the session, never from this URL.
    $signup_mode    = (string) ($_SESSION['software']['google_oauth']['signup'] ?? 'none');
    unset($_SESSION['software']['google_oauth']);

    $got_state = isset($_GET['state']) ? (string) $_GET['state'] : '';
    if ($expected_state === '' || !hash_equals($expected_state, $got_state)) {
        output_error(lang('Sorry, we could not complete the Google sign-in because the request could not be verified. Please try again.'));
    }

    // Exchange the authorization code for tokens. The client secret is stored
    // encrypted in the config table; decrypt it only here, at the moment it is
    // needed, rather than defining it as a global constant on every request.
    $secret_row    = db_item("SELECT oauth_google_client_secret, oauth_google_secret_iv FROM config");
    $client_secret = decode_ssl_keys(
        $secret_row['oauth_google_client_secret'] ?? '',
        $secret_row['oauth_google_secret_iv'] ?? ''
    );

    $post = array(
        'code'          => (string) $_GET['code'],
        'client_id'     => OAUTH_GOOGLE_CLIENT_ID,
        'client_secret' => $client_secret,
        'redirect_uri'  => $redirect_uri,
        'grant_type'    => 'authorization_code',
    );

    $ch = curl_init('https://oauth2.googleapis.com/token');
    // A request with no User-Agent looks anonymous to a firewall and can be
    // rejected, so identify this installation - matching the Parasut helpers.
    curl_setopt($ch, CURLOPT_USERAGENT, function_exists('pinegrap_user_agent') ? pinegrap_user_agent() : 'Pinegrap');
    curl_setopt_array($ch, array(
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($post),
        CURLOPT_HTTPHEADER     => array('Content-Type: application/x-www-form-urlencoded'),
        CURLOPT_TIMEOUT        => 30,
    ));
    // TLS through the project's one helper: verification on, CURL_CA_BUNDLE
    // honored for hosts whose CA store is broken, and the operator's explicit
    // ALLOW_INSECURE_UPDATE_TLS last resort respected - instead of a raw
    // VERIFYPEER=true that simply fails on such hosts.
    pg_curl_tls($ch);
    $response  = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_err  = curl_error($ch);
    curl_close($ch);

    if ($curl_err || $http_code !== 200) {
        // Leave the reason in the activity log - the visitor-facing message
        // stays generic, but the operator can see whether it was TLS, DNS or
        // an HTTP rejection.
        // Google names the cause in the response body ("invalid_client",
        // "invalid_grant", "redirect_uri_mismatch"), which is the one thing
        // that turns this into a five-second diagnosis. Only the head of it,
        // and only on failure - a successful body carries tokens.
        $failure_detail = $curl_err ? ('curl: ' . $curl_err) : ('http ' . $http_code);
        if (!$curl_err && is_string($response) && ($response !== '')) {
            $failure_detail .= ' - ' . substr(preg_replace('/\s+/', ' ', strip_tags($response)), 0, 200);
        }

        log_activity(lang(array(
            'string' => 'google sign-in token exchange failed ({var:1})',
            'vars'   => array($failure_detail))), '');
        output_error(lang('Sorry, we could not reach Google to complete sign-in. Please try again in a moment.'));
    }

    $token_data = json_decode($response, true);
    if (!is_array($token_data) || empty($token_data['id_token'])) {
        output_error(lang('Sorry, Google did not return the information we needed to sign you in. Please try again.'));
    }

    // The id_token is a JWT (header.payload.signature). We read only the
    // payload and do NOT verify the signature: the token came straight from
    // Google's TLS token endpoint, authenticated with our client secret, so
    // its origin is already proven. pg_google_jwt_payload never runs on a
    // token received from the browser.
    $claims = pg_google_jwt_payload($token_data['id_token']);
    if (!is_array($claims)) {
        output_error(lang('Sorry, we could not read the Google sign-in response. Please try again.'));
    }

    $iss   = isset($claims['iss']) ? (string) $claims['iss'] : '';
    $aud   = isset($claims['aud']) ? (string) $claims['aud'] : '';
    $sub   = isset($claims['sub']) ? (string) $claims['sub'] : '';
    $email = isset($claims['email']) ? (string) $claims['email'] : '';
    $email_verified = isset($claims['email_verified'])
        && ($claims['email_verified'] === true || $claims['email_verified'] === 'true');
    $claim_nonce = isset($claims['nonce']) ? (string) $claims['nonce'] : '';

    // The claims we rely on must check out: issued by Google, addressed to this
    // client, and carrying a subject.
    $iss_ok = ($iss === 'accounts.google.com' || $iss === 'https://accounts.google.com');
    if (!$iss_ok || !hash_equals(OAUTH_GOOGLE_CLIENT_ID, $aud) || $sub === '') {
        output_error(lang('Sorry, the Google sign-in response did not check out. Please try again.'));
    }

    // Replay guard: the nonce we planted must be the one Google echoed back.
    if ($expected_nonce === '' || !hash_equals($expected_nonce, $claim_nonce)) {
        output_error(lang('Sorry, we could not verify the Google sign-in response. Please try again.'));
    }

    // v1 requires a Google-verified email. Google returns verified for normal
    // accounts; refusing the rare unverified case keeps account association
    // unambiguous, since email is how an existing account is matched.
    if (!$email_verified || $email === '') {
        output_error(lang('Sorry, your Google account email is not verified, so we cannot use it to sign in here.'));
    }

    // Names for the address-book record, from the additional "profile" scope.
    // Normal Google accounts carry given_name/family_name; fall back to
    // splitting the display name, then to the email's local part.
    $given_name  = isset($claims['given_name'])  ? trim((string) $claims['given_name'])  : '';
    $family_name = isset($claims['family_name']) ? trim((string) $claims['family_name']) : '';
    if (($given_name === '') && isset($claims['name']) && trim((string) $claims['name']) !== '') {
        $name_parts = preg_split('/\s+/', trim((string) $claims['name']));
        if (is_array($name_parts) && count($name_parts) > 0) {
            if (count($name_parts) > 1) {
                $family_name = array_pop($name_parts);
            }
            $given_name = implode(' ', $name_parts);
        }
    }
    if ($given_name === '') {
        $given_name = (string) strtok($email, '@');
    }

    // Block list, checked on the address Google verified - before matching,
    // linking or creating anything. Disconnecting a Google account does not
    // stop the same person connecting it again; this is what does.
    if (pg_email_blocked($email)) {
        log_activity(lang(array(
            'string' => 'google sign-in refused because the email address is blocked ({var:1})',
            'vars'   => array($email))), '');
        output_error(lang('This email address cannot be used on this site.'));
    }

    // 1) Already linked: a user carries this Google subject.
    $user_id = (int) db_value("SELECT user_id FROM user WHERE user_google_id = '" . escape($sub) . "'");

    // 2) Not linked yet: match an existing account by its verified email and
    //    attach the sub to it. sub is the durable identity; the email only
    //    finds the row. Never adopt a row that already belongs to another sub.
    if (!$user_id) {
        $match_id = (int) db_value(
            "SELECT user_id FROM user WHERE user_email = '" . escape($email) . "'"
            . " AND (user_google_id IS NULL OR user_google_id = '') ORDER BY user_id LIMIT 1"
        );
        if ($match_id) {
            db("UPDATE user SET user_google_id = '" . escape($sub) . "' WHERE user_id = '" . (int) $match_id . "'");
            $user_id = $match_id;
        }
    }

    // 3) Nobody yet: create a fresh member with no password (algo 3). The
    //    account is Google-only until the visitor chooses to set a password.
    // Nobody matched, and this flow may not create anyone: a sign-in-only
    // screen (the staff login), or a site with no entrance page published at
    // all. Say so plainly rather than silently opening an account - that would
    // undo the operator's decision to keep sign-up closed.
    if (!$user_id && ($signup_mode === 'none')) {
        log_activity(lang(array(
            'string' => 'google sign-in refused for an unknown account ({var:1})',
            'vars'   => array($email))), '');
        output_error(lang('There is no account here for that Google address, and new accounts cannot be created from this screen. Please sign in with your account details, or ask the site owner for access.'));
    }

    // Membership sign-up: a Google identity is not enough to open one of these
    // accounts, because the site checks a membership number against its own
    // member list. Nothing is created here - the verified identity is handed
    // to the membership form, which runs the checks it always ran (number
    // exists, number and surname match a member, expiry date, contact group,
    // confirmation screen and email). Someone without a membership number gets
    // no further than that form.
    if (!$user_id && ($signup_mode === 'membership')) {
        $_SESSION['software']['google_membership_pending'] = array(
            'sub'        => $sub,
            'email'      => $email,
            'first_name' => $given_name,
            'last_name'  => $family_name,
            'time'       => time(),
        );

        header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/membership_entrance.php'
            . (($send_to !== '') ? ('?send_to=' . urlencode($send_to)) : ''));
        exit();
    }

    if (!$user_id) {

        // The address-book record first, exactly like the registration flow
        // creates one: campaign email goes to contacts, so a member without
        // one is invisible to it. No consent checkbox was shown here, so
        // opt_in starts at 0.
        db("INSERT INTO contacts (
                first_name,
                last_name,
                email_address,
                opt_in,
                timestamp)
            VALUES (
                '" . escape($given_name) . "',
                '" . escape($family_name) . "',
                '" . escape($email) . "',
                '0',
                UNIX_TIMESTAMP())");
        $contact_id = (int) mysqli_insert_id(db::$con);

        // Signing in with Google created this contact.
        pg_announce_contact_created($contact_id);

        // If the registration contact group exists, assign the contact to it
        // - same as the registration flow.
        if (db_value("SELECT id FROM contact_groups WHERE id = '" . REGISTRATION_CONTACT_GROUP_ID . "'")) {
            db("INSERT INTO contacts_contact_groups_xref (
                    contact_id,
                    contact_group_id)
                VALUES (
                    '" . $contact_id . "',
                    '" . REGISTRATION_CONTACT_GROUP_ID . "')");
        }

        $username = get_unique_username(strtok($email, '@'));
        $user_id  = (int) create_member_user(array(
            'email'      => $email,
            'username'   => $username,
            'google_id'  => $sub,
            'contact_id' => $contact_id,
        ));
    }

    if (!$user_id) {
        output_error(lang('Sorry, we could not sign you in with Google. Please try again.'));
    }

    // Sign the visitor in: server-side session plus a remember-me token, the
    // same shape the password login uses. No password is ever involved.
    $username = db_value("SELECT user_username FROM user WHERE user_id = '" . (int) $user_id . "'");
    // Device limit: Google sign-in always "remembers", so it counts as a device;
    // divert to the confirmation screen when this would pass the limit.
    pg_device_limit_gate($user_id, $username, $send_to, true);

    pg_session_sign_in($user_id, $username);

    pg_login_set_device_cookie($user_id, true);
    setcookie('software[remember_me]', 'true', time() + 315360000, '/');

    log_activity(lang('user signed in with Google'), $username);

    // Connect any in-progress order to the freshly signed-in user, matching the
    // member-registration flows.
    require_once(dirname(__FILE__) . '/connect_user_to_order.php');
    connect_user_to_order();

    // Where a password sign-in would have landed. An explicit same-origin
    // send_to wins (the visitor was on their way somewhere); with none,
    // send_user_to_login_home() picks the member's start page / login home,
    // exactly as index.php and the membership and registration entrances do -
    // dropping everyone on the site root instead was a difference nobody
    // asked for.
    $landing = pg_safe_back_url($send_to, '');
    if ($landing !== '') {
        go($landing);
    }

    send_user_to_login_home();
    exit();
}

// ---------------------------------------------------------------------------
// START - no code yet: send the visitor to Google's consent screen.
// ---------------------------------------------------------------------------
$state = bin2hex(random_bytes(16));
$nonce = bin2hex(random_bytes(16));

$_SESSION['software']['google_oauth'] = array(
    'state'   => $state,
    'nonce'   => $nonce,
    // What this sign-in may do if nobody matches. Two conditions, both
    // required: the screen that sent the visitor here allows it, AND the site
    // actually publishes an entrance page. The URL flag alone is forgeable;
    // pg_google_signup_allowed() is not, so a site with sign-up closed stays
    // closed however this URL is called.
    'signup' => (function () {
        $mode = (string) ($_GET['create'] ?? 'create');

        if ($mode === '0') {
            $mode = 'none';
        } elseif ($mode !== 'membership') {
            $mode = 'create';
        }

        return pg_google_signup_allowed() ? $mode : 'none';
    })(),
    // Only remember a same-origin return target, so the callback's redirect
    // cannot be pointed off-site.
    'send_to' => pg_safe_back_url(($_GET['send_to'] ?? ''), ''),
);

$auth_url = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query(array(
    'client_id'     => OAUTH_GOOGLE_CLIENT_ID,
    'redirect_uri'  => $redirect_uri,
    'response_type' => 'code',
    'scope'         => 'openid email profile',
    'state'         => $state,
    'nonce'         => $nonce,
    'access_type'   => 'online',
    'prompt'        => 'select_account',
));

header('Location: ' . $auth_url);
exit();
