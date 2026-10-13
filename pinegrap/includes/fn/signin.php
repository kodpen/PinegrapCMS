<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Sign in with Microsoft, Apple and any other provider whose definition in
 * includes/connect/providers/ carries a 'signin' block.
 *
 * The provider registry and the applications are the connected accounts'
 * (includes/fn/connect.php, connect_apps); no token is kept, because signing
 * in only needs to know once who the person is. Google keeps its own screen
 * (google_auth.php) and column (user.user_google_id); its button is drawn
 * with the others by pg_signin_buttons().
 *
 * The flow (signin.php): the button sends the browser here with the provider
 * and what the screen allows (sign in only, open an account, or hand over to
 * the membership form); state, nonce and the PKCE verifier stay in the
 * session; the provider sends the browser back with a code, which is traded
 * for an id_token at the provider's token endpoint. The account is found by
 * the identity (user_identities), else by an e-mail address the provider
 * vouches for, else opened when the screen allows it - the same rules the
 * Google sign-in follows.
 *
 * A provider's 'signin' block:
 *
 *   order            where its button stands (Google is 10)
 *   scopes           what is asked for
 *   pkce             false when the provider does not take PKCE
 *   subject_claims   claims that together name the person instead of sub
 *   verified_tenants tid values whose e-mail claim counts as verified
 *   verified_claims  claims that, when true, make the e-mail verified
 *   client_secret    'apple_jwt': the secret is a JWT signed with a key
 *   user_field       the posted field carrying the name on a first sign-in
 *
 * Tables (2026.4.10, 10.74): user_identities; config.signin_providers lists
 * the providers whose buttons are on.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}


/* ---------------------------------------------------------------------------
   Pure helpers (tests/signin_test.php)
   --------------------------------------------------------------------------- */

/**
 * What a sign-in screen may do with an identity nobody here matches, in the
 * values the Google sign-in uses: 'create', 'membership' or 'none'. Booleans
 * are read the way pg_google_signin_url() reads them.
 *
 * @param mixed $signup
 * @return string
 */
function pg_signin_signup_mode($signup)
{
    if ($signup === true) {
        return 'create';
    }

    if (($signup === false) || ($signup === 'none') || ($signup === '0')) {
        return 'none';
    }

    return ($signup === 'membership') ? 'membership' : 'create';
}

/**
 * Whether a claim value means yes: providers send true, "true", 1 or "1".
 *
 * @param mixed $value
 * @return bool
 */
function pg_signin_claim_true($value)
{
    return ($value === true) || ($value === 1) || ($value === '1') || (is_string($value) && (strtolower($value) === 'true'));
}

/**
 * Whether the provider vouches for the e-mail address in the claims.
 *
 * An explicit email_verified claim decides (Apple, Google). A provider that
 * has none (Microsoft) is trusted for the directories listed in
 * verified_tenants and when one of verified_claims is true; anything else is
 * an address someone typed, which must not open somebody else's account.
 *
 * @param array $signin the provider's 'signin' block
 * @param array $claims
 * @return bool
 */
function pg_signin_email_verified($signin, $claims)
{
    if (!is_array($claims) || !isset($claims['email']) || (trim((string) $claims['email']) === '')) {
        return false;
    }

    if (array_key_exists('email_verified', $claims)) {
        return pg_signin_claim_true($claims['email_verified']);
    }

    $verified_claims = (isset($signin['verified_claims']) && is_array($signin['verified_claims'])) ? $signin['verified_claims'] : array();

    foreach ($verified_claims as $claim) {
        if (isset($claims[$claim]) && pg_signin_claim_true($claims[$claim])) {
            return true;
        }
    }

    $tenants = (isset($signin['verified_tenants']) && is_array($signin['verified_tenants'])) ? $signin['verified_tenants'] : array();

    return isset($claims['tid']) && in_array(strtolower((string) $claims['tid']), array_map('strtolower', $tenants), true);
}

/**
 * Who the person is at the provider: the subject_claims joined with ':' when
 * they are all there, else sub. '' when neither is.
 *
 * @param array $signin
 * @param array $claims
 * @return string
 */
function pg_signin_subject($signin, $claims)
{
    if (!is_array($claims)) {
        return '';
    }

    $names = (isset($signin['subject_claims']) && is_array($signin['subject_claims'])) ? $signin['subject_claims'] : array();

    if ($names) {
        $parts = array();

        foreach ($names as $name) {
            $value = isset($claims[$name]) ? strtolower(trim((string) $claims[$name])) : '';

            if (($value === '') || !preg_match('/^[a-z0-9._-]{1,90}$/', $value)) {
                $parts = array();
                break;
            }

            $parts[] = $value;
        }

        if ($parts) {
            return implode(':', $parts);
        }
    }

    $sub = isset($claims['sub']) ? trim((string) $claims['sub']) : '';

    // The column is ASCII; a subject that is not cannot be stored faithfully.
    return (($sub !== '') && (strlen($sub) <= 191) && !preg_match('/[^\x21-\x7e]/', $sub)) ? $sub : '';
}

/**
 * First and last name for the address book: the claims' given and family
 * names, Apple's first-sign-in user field, the display name split at its
 * last space, or the e-mail's local part.
 *
 * @param array        $claims
 * @param array|string $user_field Apple's posted 'user' (JSON) or its decoded form
 * @param string       $email
 * @return array array('first_name' => string, 'last_name' => string)
 */
function pg_signin_names($claims, $user_field, $email)
{
    $first = isset($claims['given_name']) ? trim((string) $claims['given_name']) : '';
    $last = isset($claims['family_name']) ? trim((string) $claims['family_name']) : '';

    if (is_string($user_field) && ($user_field !== '')) {
        $user_field = json_decode($user_field, true);
    }

    if (($first === '') && is_array($user_field) && isset($user_field['name']) && is_array($user_field['name'])) {
        $first = isset($user_field['name']['firstName']) ? trim((string) $user_field['name']['firstName']) : '';
        $last = isset($user_field['name']['lastName']) ? trim((string) $user_field['name']['lastName']) : '';
    }

    if (($first === '') && isset($claims['name']) && (trim((string) $claims['name']) !== '')) {
        $parts = preg_split('/\s+/u', trim((string) $claims['name']));

        if (count($parts) > 1) {
            $last = array_pop($parts);
        }

        $first = implode(' ', $parts);
    }

    if ($first === '') {
        $first = (string) strtok((string) $email, '@');
    }

    $cut = function ($text) {
        return function_exists('mb_substr') ? mb_substr($text, 0, 100) : substr($text, 0, 100);
    };

    return array('first_name' => $cut($first), 'last_name' => $cut($last));
}

/**
 * An ECDSA signature as openssl_sign() writes it (DER: a SEQUENCE of two
 * INTEGERs) in the form JWS wants (R and S side by side, each padded to the
 * curve's size; RFC 7518 3.4).
 *
 * @param string $der
 * @param int    $size 32 for P-256
 * @return string '' when the input is not such a signature
 */
function pg_signin_ecdsa_der_to_jose($der, $size = 32)
{
    $der = (string) $der;
    $length = strlen($der);

    if (($length < 8) || (ord($der[0]) !== 0x30)) {
        return '';
    }

    $offset = 2;

    // A sequence longer than 127 bytes has its length in the next byte(s).
    if (ord($der[1]) & 0x80) {
        $offset += ord($der[1]) & 0x7f;
    }

    $out = '';

    for ($i = 0; $i < 2; $i++) {
        if (($offset + 2 > $length) || (ord($der[$offset]) !== 0x02)) {
            return '';
        }

        $int_length = ord($der[$offset + 1]);
        $int = substr($der, $offset + 2, $int_length);

        if (strlen($int) !== $int_length) {
            return '';
        }

        $int = ltrim($int, "\x00");

        if (strlen($int) > $size) {
            return '';
        }

        $out .= str_pad($int, $size, "\x00", STR_PAD_LEFT);
        $offset += 2 + $int_length;
    }

    return $out;
}

/**
 * A private key as pasted: the .p8 file's contents with or without its
 * BEGIN/END lines, with any line endings, as a PEM block.
 *
 * @param string $text
 * @return string '' when there is nothing that could be one
 */
function pg_signin_pem_private_key($text)
{
    $text = trim(str_replace(array("\r\n", "\r"), "\n", (string) $text));

    if ($text === '') {
        return '';
    }

    $body = preg_replace('/-----(BEGIN|END) [A-Z ]*PRIVATE KEY-----/', '', $text);
    $body = preg_replace('/\s+/', '', $body);

    if (($body === '') || !preg_match('/^[A-Za-z0-9+\/]+={0,2}$/', $body)) {
        return '';
    }

    return "-----BEGIN PRIVATE KEY-----\n" . chunk_split($body, 64, "\n") . "-----END PRIVATE KEY-----\n";
}

/**
 * The client secret Apple takes at its token endpoint: a JWT signed with the
 * site's Sign in with Apple key (ES256), naming the team, the key and the
 * Services ID.
 *
 * @param string $client_id the Services ID
 * @param string $team_id
 * @param string $key_id
 * @param string $private_key the .p8 contents
 * @param int    $now
 * @param int    $lifetime seconds; Apple allows up to six months
 * @return string '' when the key cannot sign
 */
function pg_signin_apple_client_secret($client_id, $team_id, $key_id, $private_key, $now, $lifetime = 3600)
{
    if (!function_exists('openssl_sign') || ((string) $client_id === '') || ((string) $team_id === '') || ((string) $key_id === '')) {
        return '';
    }

    $pem = pg_signin_pem_private_key($private_key);
    $key = ($pem !== '') ? openssl_pkey_get_private($pem) : false;

    if ($key === false) {
        return '';
    }

    $input = pg_connect_base64url(json_encode(array('alg' => 'ES256', 'kid' => (string) $key_id)))
        . '.' . pg_connect_base64url(json_encode(array(
            'iss' => (string) $team_id,
            'iat' => (int) $now,
            'exp' => (int) $now + (int) $lifetime,
            'aud' => 'https://appleid.apple.com',
            'sub' => (string) $client_id,
        )));

    $der = '';

    if (!openssl_sign($input, $der, $key, OPENSSL_ALGO_SHA256)) {
        return '';
    }

    $signature = pg_signin_ecdsa_der_to_jose($der, 32);

    return ($signature === '') ? '' : $input . '.' . pg_connect_base64url($signature);
}

/**
 * What is wrong with a pasted Sign in with Apple key, or ''.
 *
 * @param string $text
 * @return string
 */
function pg_signin_apple_key_problem($text)
{
    $pem = pg_signin_pem_private_key($text);
    $key = ($pem !== '') ? @openssl_pkey_get_private($pem) : false;

    if ($key === false) {
        return lang('The private key could not be read. Paste the whole contents of the .p8 file.');
    }

    $details = openssl_pkey_get_details($key);

    if (!is_array($details) || !isset($details['type']) || ($details['type'] !== OPENSSL_KEYTYPE_EC)
        || !isset($details['ec']['curve_name']) || ($details['ec']['curve_name'] !== 'prime256v1')) {
        return lang('This is not a Sign in with Apple key: Apple\'s keys are P-256 elliptic curve keys.');
    }

    return '';
}


/* ---------------------------------------------------------------------------
   Providers
   --------------------------------------------------------------------------- */

/**
 * Whether the tables are there (10.74).
 *
 * @return bool
 */
function pg_signin_schema_ready()
{
    return pg_schema_has('user_identities') && pg_connect_schema_ready();
}

/**
 * Providers that can sign people in, in button order.
 *
 * @return array<string,array>
 */
function pg_signin_providers()
{
    $list = array();

    foreach (pg_connect_providers() as $key => $provider) {
        if (isset($provider['signin']) && is_array($provider['signin'])) {
            $list[$key] = $provider;
        }
    }

    uasort($list, function ($a, $b) {
        $x = isset($a['signin']['order']) ? (int) $a['signin']['order'] : 100;
        $y = isset($b['signin']['order']) ? (int) $b['signin']['order'] : 100;

        return ($x === $y) ? strcmp($a['key'], $b['key']) : (($x < $y) ? -1 : 1);
    });

    return $list;
}

/**
 * The providers the operator turned on (config.signin_providers).
 *
 * @return array
 */
function pg_signin_switched_on()
{
    return pg_connect_scope_list(str_replace(',', ' ', defined('SIGNIN_PROVIDERS') ? (string) SIGNIN_PROVIDERS : ''));
}

/**
 * Whether a provider's button is shown and its sign-in works: turned on, its
 * application complete, the tables there.
 *
 * @param string $key
 * @return bool
 */
function pg_signin_enabled($key)
{
    static $cache = array();

    if (isset($cache[$key])) {
        return $cache[$key];
    }

    $providers = pg_signin_providers();

    $cache[$key] = isset($providers[$key])
        && in_array($key, pg_signin_switched_on(), true)
        && pg_signin_schema_ready()
        && pg_signin_app_ready($providers[$key], pg_connect_app($key));

    return $cache[$key];
}

/**
 * Whether an application can sign people in through a provider. Apple's needs
 * its team and key ID besides the Services ID and the key.
 *
 * @param array      $provider
 * @param array|null $app
 * @return bool
 */
function pg_signin_app_ready($provider, $app)
{
    if (!pg_connect_app_ready($app)) {
        return false;
    }

    if (isset($provider['signin']['client_secret']) && ($provider['signin']['client_secret'] === 'apple_jwt')) {
        return !empty($app['options']['team_id']) && !empty($app['options']['key_id']);
    }

    return true;
}

/**
 * The return address to register with the provider for signing in. It
 * carries the host the visitor is on, like google_auth.php, so the callback
 * finds the session the sign-in started in.
 *
 * @return string
 */
function pg_signin_redirect_uri()
{
    return URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/signin.php';
}

/**
 * The address a sign-in button points at, or '' when the provider is off.
 *
 * @param string $key
 * @param string $send_to
 * @param mixed  $signup see pg_signin_signup_mode()
 * @return string
 */
function pg_signin_url($key, $send_to = '', $signup = 'create')
{
    if (!pg_signin_enabled($key)) {
        return '';
    }

    $query = array('provider' => $key);

    if ((string) $send_to !== '') {
        $query['send_to'] = (string) $send_to;
    }

    $mode = pg_signin_signup_mode($signup);

    if ($mode === 'none') {
        $query['create'] = '0';
    } elseif ($mode === 'membership') {
        $query['create'] = 'membership';
    }

    return OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/signin.php?' . http_build_query($query);
}

/**
 * A provider's mark as inline SVG.
 *
 * @param string $key
 * @param int    $size
 * @return string
 */
function pg_signin_logo_svg($key, $size = 18)
{
    $size = max(1, (int) $size);

    if ($key === 'microsoft') {
        return '<svg width="' . $size . '" height="' . $size . '" viewBox="0 0 21 21" aria-hidden="true">'
            . '<rect x="1" y="1" width="9" height="9" fill="#f25022"/>'
            . '<rect x="11" y="1" width="9" height="9" fill="#7fba00"/>'
            . '<rect x="1" y="11" width="9" height="9" fill="#00a4ef"/>'
            . '<rect x="11" y="11" width="9" height="9" fill="#ffb900"/>'
            . '</svg>';
    }

    if ($key === 'apple') {
        return '<svg width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" aria-hidden="true">'
            . '<path fill="currentColor" d="M12.152 6.896c-.948 0-2.415-1.078-3.96-1.04-2.04.027-3.91 1.183-4.961 3.014-2.117 3.675-.546 9.103 1.519 12.09 1.013 1.454 2.208 3.09 3.792 3.039 1.52-.065 2.09-.987 3.935-.987 1.831 0 2.35.987 3.96.948 1.637-.026 2.676-1.48 3.676-2.948 1.156-1.688 1.636-3.325 1.662-3.415-.039-.013-3.182-1.221-3.22-4.857-.026-3.04 2.48-4.494 2.597-4.559-1.429-2.09-3.623-2.324-4.39-2.376-2-.156-3.675 1.09-4.61 1.09zM15.53 3.83c.843-1.012 1.4-2.427 1.245-3.83-1.207.052-2.662.805-3.532 1.818-.78.896-1.454 2.338-1.273 3.714 1.338.104 2.715-.688 3.559-1.701"/>'
            . '</svg>';
    }

    return '<i class="bi bi-person-circle" aria-hidden="true"></i>';
}

/**
 * The "Sign in with ..." button of one provider, or '' when it is off. Inline
 * styles in the provider's own colours, like the Google button, so it looks
 * the same on any front-end theme.
 *
 * @param string $key
 * @param string $send_to
 * @param mixed  $signup
 * @return string
 */
function pg_signin_button($key, $send_to = '', $signup = 'create')
{
    $url = pg_signin_url($key, $send_to, $signup);

    if ($url === '') {
        return '';
    }

    if ($key === 'apple') {
        $colors = 'border:1px solid #000;background:#000 !important;color:#fff !important';
        $label = lang('Sign in with Apple');
    } elseif ($key === 'microsoft') {
        $colors = 'border:1px solid #8c8c8c;background:#fff !important;color:#5e5e5e !important';
        $label = lang('Sign in with Microsoft');
    } else {
        $providers = pg_signin_providers();
        $colors = 'border:1px solid #dadce0;background:#fff !important;color:#3c4043 !important';
        $label = lang(array('string' => 'Sign in with {var:1}', 'vars' => $providers[$key]['label']));
    }

    $text_color = ($key === 'apple') ? '#fff' : (($key === 'microsoft') ? '#5e5e5e' : '#3c4043');

    return '<div class="pg-signin-' . h($key) . '" style="margin-top:1em;text-align:center">'
        . '<a href="' . h($url) . '" rel="nofollow" style="display:inline-flex;align-items:center;justify-content:center;gap:10px;padding:9px 18px;border-radius:4px;' . $colors . ';font-weight:500;text-decoration:none !important;font-family:\'Segoe UI\',-apple-system,BlinkMacSystemFont,Roboto,Arial,sans-serif;font-size:14px !important;line-height:1.4">'
        . pg_signin_logo_svg($key, 18)
        // !important on the label: a theme that paints links white would
        // otherwise leave a button with a mark and no words.
        . '<span style="color:' . $text_color . ' !important;font-size:14px !important;white-space:nowrap">' . h($label) . '</span></a></div>';
}

/**
 * Every sign-in button a screen shows: Google's, then the other providers
 * that are on, or '' when none is. The login, registration and membership
 * screens and widgets print this where they printed the Google button.
 *
 * @param string $send_to
 * @param mixed  $signup
 * @return string
 */
function pg_signin_buttons($send_to = '', $signup = 'create')
{
    $html = function_exists('pg_google_signin_button') ? pg_google_signin_button($send_to, $signup) : '';

    foreach (array_keys(pg_signin_providers()) as $key) {
        $html .= pg_signin_button($key, $send_to, $signup);
    }

    return $html;
}


/* ---------------------------------------------------------------------------
   Identities (user_identities)
   --------------------------------------------------------------------------- */

/**
 * The account an identity belongs to, or 0. A row whose account is gone is
 * removed on the way.
 *
 * @param string $provider_key
 * @param string $subject
 * @return int
 */
function pg_signin_identity_user($provider_key, $subject)
{
    if (!pg_signin_schema_ready() || ((string) $subject === '')) {
        return 0;
    }

    $row = db_item(
        "SELECT user_identities.id, user_identities.user_id, user.user_id AS existing
        FROM user_identities
        LEFT JOIN user ON user.user_id = user_identities.user_id
        WHERE user_identities.provider = '" . e($provider_key) . "' AND user_identities.subject = '" . e($subject) . "'");

    if (!is_array($row)) {
        return 0;
    }

    if (!$row['existing']) {
        db("DELETE FROM user_identities WHERE id = '" . (int) $row['id'] . "'");

        return 0;
    }

    return (int) $row['user_id'];
}

/**
 * Ties an identity to an account. An account holds one identity per
 * provider; a later one replaces it.
 *
 * @param int    $user_id
 * @param string $provider_key
 * @param string $subject
 * @param string $email
 * @return bool
 */
function pg_signin_identity_add($user_id, $provider_key, $subject, $email)
{
    if (!pg_signin_schema_ready() || ((int) $user_id <= 0) || ((string) $subject === '')) {
        return false;
    }

    $holder = (int) db_value(
        "SELECT user_id FROM user_identities
        WHERE provider = '" . e($provider_key) . "' AND subject = '" . e($subject) . "'");

    if ($holder === (int) $user_id) {
        return true;
    }

    // The same person at the provider cannot be two accounts here.
    if ($holder) {
        return false;
    }

    db("DELETE FROM user_identities WHERE user_id = '" . (int) $user_id . "' AND provider = '" . e($provider_key) . "'");

    // Not db(): a request that tied the same identity a moment earlier would
    // turn the unique key into a fatal error page instead of a refusal.
    return (bool) @mysqli_query(db::$con,
        "INSERT INTO user_identities SET
            user_id = '" . (int) $user_id . "',
            provider = '" . e($provider_key) . "',
            subject = '" . e($subject) . "',
            email = '" . e(pg_connect_clip($email)) . "',
            created_timestamp = UNIX_TIMESTAMP(),
            last_used_timestamp = UNIX_TIMESTAMP()");
}

/**
 * Notes a sign-in with an identity and the address the provider reported.
 *
 * @param string $provider_key
 * @param string $subject
 * @param string $email
 */
function pg_signin_identity_touch($provider_key, $subject, $email)
{
    db(
        "UPDATE user_identities
        SET last_used_timestamp = UNIX_TIMESTAMP()"
            . (((string) $email !== '') ? ", email = '" . e(pg_connect_clip($email)) . "'" : '') . "
        WHERE provider = '" . e($provider_key) . "' AND subject = '" . e($subject) . "'");
}

/**
 * An account's identities by provider key, oldest first.
 *
 * @param int $user_id
 * @return array<string,array> provider => email, created_timestamp, last_used_timestamp
 */
function pg_signin_identities($user_id)
{
    if (!pg_signin_schema_ready() || ((int) $user_id <= 0)) {
        return array();
    }

    $list = array();

    foreach (db_items(
        "SELECT provider, email, created_timestamp, last_used_timestamp
        FROM user_identities
        WHERE user_id = '" . (int) $user_id . "'
        ORDER BY id") as $row) {
        $list[(string) $row['provider']] = $row;
    }

    return $list;
}

/**
 * Whether an account keeps a way to sign in without one of its sign-in
 * methods: its own password, Google, or another provider.
 *
 * @param int    $user_id
 * @param string $without 'google' or a provider key
 * @return bool
 */
function pg_signin_can_remove($user_id, $without)
{
    $row = db_item("SELECT user_password_algo, user_google_id FROM user WHERE user_id = '" . (int) $user_id . "'");

    if (!is_array($row)) {
        return false;
    }

    if ((int) $row['user_password_algo'] !== 3) {
        return true;
    }

    if (($without !== 'google') && ((string) $row['user_google_id'] !== '')) {
        return true;
    }

    foreach (array_keys(pg_signin_identities($user_id)) as $provider_key) {
        if ($provider_key !== $without) {
            return true;
        }
    }

    return false;
}

/**
 * Removes an account's identity at one provider.
 *
 * @param int    $user_id
 * @param string $provider_key
 */
function pg_signin_identity_remove($user_id, $provider_key)
{
    if (pg_signin_schema_ready()) {
        db("DELETE FROM user_identities WHERE user_id = '" . (int) $user_id . "' AND provider = '" . e($provider_key) . "'");
    }
}

/**
 * Removes every identity of a deleted account.
 *
 * @param int $user_id
 */
function pg_signin_identities_forget($user_id)
{
    if (pg_signin_schema_ready() && ((int) $user_id > 0)) {
        db("DELETE FROM user_identities WHERE user_id = '" . (int) $user_id . "'");
    }
}

/**
 * The notice of the membership form after a sign-in that matched no account:
 * the identity is verified, the membership number is what is left.
 *
 * @param array $pending the session's pending record
 * @return string
 */
function pg_signin_membership_notice($pending)
{
    $provider_key = (is_array($pending) && isset($pending['provider'])) ? (string) $pending['provider'] : 'google';

    if ($provider_key === 'google') {
        return lang('Your Google account is verified. Enter your membership details to finish - a password is optional, because you can keep signing in with Google.');
    }

    return lang(array(
        'string' => 'Your {var:1} account is verified. Enter your membership details to finish - a password is optional, because you can keep signing in with {var:1}.',
        'vars'   => pg_signin_provider_label($provider_key)));
}

/**
 * The label of a provider, for the screens that list identities.
 *
 * @param string $provider_key
 * @return string
 */
function pg_signin_provider_label($provider_key)
{
    $provider = pg_connect_provider($provider_key);

    return is_array($provider) ? (string) $provider['label'] : (string) $provider_key;
}


/* ---------------------------------------------------------------------------
   The flow
   --------------------------------------------------------------------------- */

/**
 * Begins a sign-in: the address to send the browser to, or an error.
 *
 * @param string $key
 * @param string $send_to a same-site address to land on
 * @param string $signup  'create', 'membership' or 'none'; downgraded to
 *                        'none' when the site publishes no entrance page
 * @return array ok, url, error
 */
function pg_signin_start($key, $send_to, $signup)
{
    if (!pg_signin_enabled($key)) {
        return array('ok' => false, 'url' => '', 'error' => lang('This sign-in is not available.'));
    }

    $providers = pg_signin_providers();
    $provider = $providers[$key];
    $signin = $provider['signin'];
    $app = pg_connect_app($key);

    $state = bin2hex(random_bytes(16));
    $nonce = bin2hex(random_bytes(16));
    $pkce = pg_connect_pkce();
    $with_pkce = !(isset($signin['pkce']) && ($signin['pkce'] === false));

    $pending = (isset($_SESSION['software']['signin']) && is_array($_SESSION['software']['signin']))
        ? $_SESSION['software']['signin']
        : array();

    while (count($pending) >= 5) {
        array_shift($pending);
    }

    $pending[$state] = array(
        'provider' => $key,
        'nonce'    => $nonce,
        'verifier' => $with_pkce ? $pkce['verifier'] : '',
        // The URL flag alone is forgeable; the site's own rule decides too.
        'signup'   => pg_google_signup_allowed() ? pg_signin_signup_mode($signup) : 'none',
        'send_to'  => pg_safe_back_url($send_to, ''),
        'started'  => time(),
    );

    $_SESSION['software']['signin'] = $pending;

    $params = array(
        'response_type' => 'code',
        'client_id'     => (string) $app['client_id'],
        'redirect_uri'  => pg_signin_redirect_uri(),
        'scope'         => implode(' ', pg_connect_scope_list(isset($signin['scopes']) ? $signin['scopes'] : array('openid', 'email'))),
        'state'         => $state,
        'nonce'         => $nonce,
    );

    if ($with_pkce) {
        $params['code_challenge'] = $pkce['challenge'];
        $params['code_challenge_method'] = 'S256';
    }

    if (isset($provider['authorize_params']) && is_array($provider['authorize_params'])) {
        foreach ($provider['authorize_params'] as $name => $value) {
            if (!isset($params[$name])) {
                $params[$name] = (string) $value;
            }
        }
    }

    $url = pg_connect_endpoint($provider, $app, 'authorize_url');

    return array(
        'ok'    => true,
        'url'   => $url . ((strpos($url, '?') === false) ? '?' : '&') . http_build_query($params, '', '&', PHP_QUERY_RFC3986),
        'error' => '',
    );
}

/**
 * Finishes a sign-in from the provider's answer: who the person is.
 *
 * @param array $params the callback's query (or, for form_post, its body)
 * @return array ok, cancelled, error, provider, label, subject, email,
 *               email_verified, first_name, last_name, signup, send_to
 */
function pg_signin_finish($params)
{
    $result = array(
        'ok' => false, 'cancelled' => false, 'error' => '', 'provider' => '', 'label' => '',
        'subject' => '', 'email' => '', 'email_verified' => false, 'first_name' => '', 'last_name' => '',
        'signup' => 'none', 'send_to' => '',
    );

    $fail = function ($error) use (&$result) {
        $result['error'] = $error;

        return $result;
    };

    $state = (isset($params['state']) && is_string($params['state'])) ? $params['state'] : '';
    $pending = null;

    if (($state !== '') && isset($_SESSION['software']['signin'][$state]) && is_array($_SESSION['software']['signin'][$state])) {
        $pending = $_SESSION['software']['signin'][$state];
        unset($_SESSION['software']['signin'][$state]);
    }

    if (!is_array($pending)) {
        return $fail(lang('Sorry, we could not complete the sign-in because the request could not be verified. Please try again.'));
    }

    $result['signup'] = (string) $pending['signup'];
    $result['send_to'] = (string) $pending['send_to'];
    $result['provider'] = (string) $pending['provider'];

    if ((int) $pending['started'] < (time() - 900)) {
        return $fail(lang('The sign-in took too long. Please try again.'));
    }

    $providers = pg_signin_providers();
    $key = $result['provider'];
    $app = pg_connect_app($key);

    if (!isset($providers[$key]) || !pg_signin_app_ready($providers[$key], $app)) {
        return $fail(lang('This sign-in is not available.'));
    }

    $provider = $providers[$key];
    $signin = $provider['signin'];
    $result['label'] = (string) $provider['label'];

    if (isset($params['error'])) {
        $error = is_string($params['error']) ? $params['error'] : '';

        // The person backed out: not an error worth a page of its own.
        if (in_array($error, array('access_denied', 'user_cancelled_authorize', 'consent_required', 'interaction_required'), true)) {
            $result['cancelled'] = true;

            return $fail(lang('The sign-in was cancelled.'));
        }

        return $fail(lang(array('string' => 'Sorry, {var:1} did not sign you in: {var:2}', 'vars' => array(
            $provider['label'],
            pg_connect_clip($error . ((isset($params['error_description']) && is_string($params['error_description'])) ? ' - ' . $params['error_description'] : ''))))));
    }

    // RFC 9207, as on connect.php: when the provider names itself on the
    // redirect, it has to be the one this attempt was sent to.
    if (isset($params['iss']) && !pg_connect_issuer_matches($provider, is_string($params['iss']) ? $params['iss'] : '')) {
        return $fail(lang('Sorry, we could not complete the sign-in because the request could not be verified. Please try again.'));
    }

    $code = (isset($params['code']) && is_string($params['code'])) ? $params['code'] : '';

    if ($code === '') {
        return $fail(lang('Sorry, we could not complete the sign-in because the request could not be verified. Please try again.'));
    }

    $token_app = $app;

    if (isset($signin['client_secret']) && ($signin['client_secret'] === 'apple_jwt')) {
        $token_app['client_secret'] = pg_signin_apple_client_secret(
            $app['client_id'], $app['options']['team_id'], $app['options']['key_id'], $app['client_secret'], time());

        if ($token_app['client_secret'] === '') {
            log_activity(lang(array('string' => '{var:1} sign-in failed: the private key could not sign', 'vars' => $provider['label'])), '');

            return $fail(lang(array('string' => 'Sorry, we could not reach {var:1} to complete sign-in. Please try again in a moment.', 'vars' => $provider['label'])));
        }
    }

    $request = array(
        'grant_type'   => 'authorization_code',
        'code'         => $code,
        'redirect_uri' => pg_signin_redirect_uri(),
    );

    if ((string) $pending['verifier'] !== '') {
        $request['code_verifier'] = (string) $pending['verifier'];
    }

    $response = pg_connect_token_request($provider, $token_app, $request);

    if (!$response['ok']) {
        // The operator needs the provider's reason (invalid_client,
        // redirect_uri_mismatch); the visitor gets the general one.
        log_activity(lang(array(
            'string' => '{var:1} sign-in token exchange failed ({var:2})',
            'vars'   => array($provider['label'], $response['error']))), '');

        return $fail(lang(array('string' => 'Sorry, we could not reach {var:1} to complete sign-in. Please try again in a moment.', 'vars' => $provider['label'])));
    }

    $id_token = isset($response['data']['id_token']) ? (string) $response['data']['id_token'] : '';
    $claims = ($id_token !== '') ? pg_google_jwt_payload($id_token) : null;

    if (!is_array($claims)) {
        return $fail(lang(array('string' => 'Sorry, {var:1} did not return the information we needed to sign you in. Please try again.', 'vars' => $provider['label'])));
    }

    $problem = pg_connect_claims_problem($provider, $claims, $app['client_id'], $pending['nonce'], time());

    if ($problem !== '') {
        log_activity(lang(array('string' => '{var:1} sign-in refused: {var:2}', 'vars' => array($provider['label'], $problem))), '');

        return $fail(lang(array('string' => 'Sorry, the {var:1} sign-in response did not check out. Please try again.', 'vars' => $provider['label'])));
    }

    $subject = pg_signin_subject($signin, $claims);

    if ($subject === '') {
        return $fail(lang(array('string' => 'Sorry, {var:1} did not return the information we needed to sign you in. Please try again.', 'vars' => $provider['label'])));
    }

    $email = isset($claims['email']) ? trim((string) $claims['email']) : '';

    $user_field = '';

    if (!empty($signin['user_field']) && isset($params[$signin['user_field']]) && is_string($params[$signin['user_field']])) {
        $user_field = $params[$signin['user_field']];
    }

    $names = pg_signin_names($claims, $user_field, $email);

    $result['ok'] = true;
    $result['subject'] = $subject;
    $result['email'] = (filter_var($email, FILTER_VALIDATE_EMAIL) !== false) ? $email : '';
    $result['email_verified'] = ($result['email'] !== '') && pg_signin_email_verified($signin, $claims);
    $result['first_name'] = $names['first_name'];
    $result['last_name'] = $names['last_name'];

    return $result;
}

/**
 * The account a finished sign-in belongs to: the one holding the identity,
 * else one with the verified address that has no identity at this provider
 * yet (and is then tied to it). 0 when there is none.
 *
 * @param array $identity what pg_signin_finish() returned
 * @return int
 */
function pg_signin_match_user($identity)
{
    $user_id = pg_signin_identity_user($identity['provider'], $identity['subject']);

    if ($user_id) {
        pg_signin_identity_touch($identity['provider'], $identity['subject'], $identity['email_verified'] ? $identity['email'] : '');

        return $user_id;
    }

    if (!$identity['email_verified'] || ($identity['email'] === '')) {
        return 0;
    }

    // The address only finds the row; the identity is what signs in from
    // now on. An account already tied to another person at this provider is
    // never taken over.
    $match_id = (int) db_value(
        "SELECT user.user_id
        FROM user
        LEFT JOIN user_identities
            ON user_identities.user_id = user.user_id
            AND user_identities.provider = '" . e($identity['provider']) . "'
        WHERE user.user_email = '" . e($identity['email']) . "' AND user_identities.id IS NULL
        ORDER BY user.user_id
        LIMIT 1");

    if ($match_id && pg_signin_identity_add($match_id, $identity['provider'], $identity['subject'], $identity['email'])) {
        log_activity(lang(array(
            'string' => '{var:1} sign-in was tied to the account with the same verified e-mail address ({var:2})',
            'vars'   => array($identity['label'], $identity['email']))), '');

        return $match_id;
    }

    return 0;
}

/**
 * Opens a member account for a sign-in nobody here matched, the way the
 * Google sign-in does: an address-book record in the registration group, a
 * member with no password (algo 3), the identity tied to it.
 *
 * @param array $identity
 * @return int the new user_id, 0 when it could not be opened
 */
function pg_signin_create_member($identity)
{
    $email = $identity['email'];

    db(
        "INSERT INTO contacts (first_name, last_name, email_address, opt_in, timestamp)
        VALUES (
            '" . e($identity['first_name']) . "',
            '" . e($identity['last_name']) . "',
            '" . e($email) . "',
            '0',
            UNIX_TIMESTAMP())");

    $contact_id = (int) mysqli_insert_id(db::$con);

    pg_announce_contact_created($contact_id);

    if (defined('REGISTRATION_CONTACT_GROUP_ID')
        && db_value("SELECT id FROM contact_groups WHERE id = '" . (int) REGISTRATION_CONTACT_GROUP_ID . "'")) {
        db(
            "INSERT INTO contacts_contact_groups_xref (contact_id, contact_group_id)
            VALUES ('" . $contact_id . "', '" . (int) REGISTRATION_CONTACT_GROUP_ID . "')");
    }

    $user_id = (int) create_member_user(array(
        'email'      => $email,
        'username'   => get_unique_username(strtok($email, '@')),
        'contact_id' => $contact_id,
    ));

    if ($user_id) {
        pg_signin_identity_add($user_id, $identity['provider'], $identity['subject'], $email);
    }

    return $user_id;
}
