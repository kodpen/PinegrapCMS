<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Sign in with Google, Microsoft, Apple and any other provider whose
 * definition in includes/connect/providers/ carries a 'signin' block.
 *
 * The provider registry and the applications are the connected accounts'
 * (includes/fn/connect.php, connect_apps); no token is kept, because signing
 * in only needs to know once who the person is. Google is the exception on
 * two counts, both kept so a site that had Google sign-in before needs no
 * change in Google Cloud Console: its application is the one of the security
 * settings (config.oauth_google_*) and its return address is google_auth.php.
 *
 * The flow (signin.php): the button sends the browser here with the provider
 * and what the screen allows (sign in only, open an account, or hand over to
 * the membership form); state, nonce and the PKCE verifier stay in the
 * session; the provider sends the browser back with a code, which is traded
 * for an id_token at the provider's token endpoint. The account is found by
 * the identity (user_identities), else by an e-mail address the provider
 * vouches for, else opened when the screen allows it - the same rules for
 * every provider.
 *
 * A provider's 'signin' block:
 *
 *   order            where its button stands
 *   scopes           what is asked for
 *   authorize_params replace the provider's own on the sign-in request
 *   return           the root script the provider sends people back to,
 *                    signin.php when absent
 *   pkce             false when the provider does not take PKCE
 *   subject_claims   claims that together name the person instead of sub
 *   verified_tenants tid values whose e-mail claim counts as verified
 *   verified_claims  claims that, when true, make the e-mail verified
 *   client_secret    'apple_jwt': the secret is a JWT signed with a key
 *   user_field       the posted field carrying the name on a first sign-in
 *
 * Tables (2026.4.10, 10.74): user_identities; config.signin_providers lists
 * the providers whose buttons are on, Google's switch is
 * config.oauth_google_enabled. Google identities moved there from
 * user.user_google_id with 10.75; a link that is still in that column moves
 * the first time it signs in.
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

/**
 * The root script a provider sends people back to: its 'return' (Google's
 * google_auth.php, the address sites registered before Google joined this
 * flow), else signin.php.
 *
 * @param array|null $provider
 * @return string
 */
function pg_signin_return_file($provider)
{
    $file = (is_array($provider) && isset($provider['signin']['return'])) ? (string) $provider['signin']['return'] : '';

    return preg_match('/^[a-z0-9_]{1,40}\.php$/', $file) ? $file : 'signin.php';
}

/**
 * The extra parameters of a provider's sign-in request: the 'signin' block's
 * own when it has them (Google asks for a refresh token when connecting an
 * account, never when signing in), else the provider's.
 *
 * @param array|null $provider
 * @return array
 */
function pg_signin_authorize_params($provider)
{
    if (is_array($provider) && isset($provider['signin']['authorize_params']) && is_array($provider['signin']['authorize_params'])) {
        return $provider['signin']['authorize_params'];
    }

    return (is_array($provider) && isset($provider['authorize_params']) && is_array($provider['authorize_params'])) ? $provider['authorize_params'] : array();
}

/**
 * Sign-in providers of several accounts at once, for the lists: user_id =>
 * provider keys in button order. $rows are user_identities rows with
 * user_id and provider.
 *
 * @param array $rows
 * @param array $order provider keys in button order
 * @return array<int,array>
 */
function pg_signin_group_identities($rows, $order)
{
    $rank = array_flip(array_values($order));
    $grouped = array();

    foreach ((array) $rows as $row) {
        if (!is_array($row) || !isset($row['user_id'], $row['provider'])) {
            continue;
        }

        $grouped[(int) $row['user_id']][] = (string) $row['provider'];
    }

    foreach ($grouped as $user_id => $keys) {
        $keys = array_values(array_unique($keys));

        usort($keys, function ($a, $b) use ($rank) {
            $x = isset($rank[$a]) ? $rank[$a] : PHP_INT_MAX;
            $y = isset($rank[$b]) ? $rank[$b] : PHP_INT_MAX;

            return ($x === $y) ? strcmp($a, $b) : (($x < $y) ? -1 : 1);
        });

        $grouped[$user_id] = $keys;
    }

    return $grouped;
}

/**
 * The page of this site a referrer names, as a path with its query, so the
 * failure screen can send the visitor back to the screen the button was on.
 * '' for another site's address, a malformed one, or the sign-in's own
 * scripts (a page that only redirects is nowhere to go back to).
 *
 * @param string $referer the Referer header
 * @param string $host    the host this request was made to
 * @return string
 */
function pg_signin_referer_path($referer, $host)
{
    $referer = is_string($referer) ? trim($referer) : '';
    $host = strtolower(preg_replace('/:\d+$/', '', is_string($host) ? trim($host) : ''));

    if (($referer === '') || ($host === '') || preg_match('/[\x00-\x1F\x7F]/', $referer)) {
        return '';
    }

    $parts = parse_url($referer);

    if (!is_array($parts) || empty($parts['host']) || (strtolower($parts['host']) !== $host)
        || !isset($parts['scheme']) || !in_array(strtolower($parts['scheme']), array('http', 'https'), true)) {
        return '';
    }

    $path = isset($parts['path']) ? $parts['path'] : '/';

    if (($path === '') || ($path[0] !== '/') || (strpos($path, '//') === 0) || (strpos($path, '/\\') === 0)
        || preg_match('~/(signin|google_auth)\.php$~i', $path)) {
        return '';
    }

    return $path . (isset($parts['query']) ? '?' . $parts['query'] : '');
}

/**
 * The card of the screen a sign-in that did not complete ends on: what
 * happened and where to go from here. Its own screen rather than the site's
 * error page: that page is designed for "page not found" on most sites, and a
 * refused sign-in shown there reads as a broken link.
 *
 * @param string $title
 * @param string $message plain text
 * @param array  $links   each array('url' => ..., 'label' => ..., 'primary' => bool)
 * @param bool   $cancelled the person stopped at the provider: a notice, not an error
 * @return string
 */
function pg_signin_failure_card($title, $message, $links, $cancelled = false)
{
    $buttons = '';

    foreach ((array) $links as $link) {
        if (!is_array($link) || empty($link['url']) || !isset($link['label'])) {
            continue;
        }

        $buttons .= '<a class="btn ' . (!empty($link['primary']) ? 'btn-primary' : 'btn-outline-secondary') . '" href="' . h($link['url']) . '" rel="nofollow">'
            . h($link['label']) . '</a>';
    }

    return '<main class="container py-4 py-md-5">
        <div class="card mx-auto shadow-sm pg-signin-result" style="max-width: 32rem;">
            <div class="card-body p-4">
                <h1 class="h4 mb-3"><i class="bi ' . ($cancelled ? 'bi-info-circle' : 'bi-exclamation-triangle text-danger') . ' me-2" aria-hidden="true"></i>' . h($title) . '</h1>
                <p class="mb-4" role="' . ($cancelled ? 'status' : 'alert') . '">' . h($message) . '</p>
                ' . (($buttons !== '') ? '<div class="d-flex flex-wrap gap-2">' . $buttons . '</div>' : '') . '
            </div>
        </div>
    </main>';
}

/**
 * How an account signs in, as badges for the user and session lists: one
 * per provider, and a warning when a single provider is the only way in.
 *
 * @param array $providers provider keys
 * @param int   $password_algo user.user_password_algo; 3 is no password
 * @return string
 */
function pg_signin_identity_badges($providers, $password_algo)
{
    $providers = array_values(array_filter(array_map('strval', (array) $providers), 'strlen'));

    if (!$providers) {
        return '<span class="text-muted">&mdash;</span>';
    }

    if ((count($providers) === 1) && ((int) $password_algo === 3)) {
        $label = pg_signin_provider_label($providers[0]);

        return '<span class="badge bg-warning text-dark fw-light" title="'
            . h(lang(array('string' => 'This account has no password of its own - disconnecting {var:1} would lock it out.', 'vars' => $label))) . '">'
            . h(lang(array('string' => '{var:1} only', 'vars' => $label))) . '</span>';
    }

    $html = array();

    foreach ($providers as $key) {
        $label = pg_signin_provider_label($key);

        $html[] = '<span class="badge bg-light text-dark border fw-light" title="'
            . h(lang(array('string' => 'This account can sign in with {var:1}.', 'vars' => $label))) . '">' . h($label) . '</span>';
    }

    return implode(' ', $html);
}

/**
 * Why a "Connect" button may not start connecting a provider to the account
 * signed in now; '' when it may. The token is the one of this session
 * (pg_signin_connect_token()), so only a page drawn for this browser starts
 * it. An operator signed in as the person may not: the provider account would
 * be the operator's, a way into the person's account that outlives the visit.
 *
 * @param int    $user_id       the account signed in now, 0 when none
 * @param bool   $impersonated  an operator is signed in as the person
 * @param string $token         the token the button carried
 * @param string $session_token the token of this session
 * @param bool   $connected     the account already has an identity at the provider
 * @return string '', 'signed_out', 'impersonated', 'token' or 'connected'
 */
function pg_signin_connect_problem($user_id, $impersonated, $token, $session_token, $connected)
{
    if ((int) $user_id <= 0) {
        return 'signed_out';
    }

    if ($impersonated) {
        return 'impersonated';
    }

    if (!is_string($token) || !is_string($session_token) || ($session_token === '') || !hash_equals($session_token, $token)) {
        return 'token';
    }

    return $connected ? 'connected' : '';
}

/**
 * What a finished "Connect" does with the identity the provider vouched for.
 * The account is the one that pressed the button, and it has to be the one
 * still signed in; the identity is tied to it whatever e-mail address either
 * side has, unless another account here holds it already (never taken over)
 * or the account holds another person at the same provider (disconnected
 * first, by the person).
 *
 * @param int  $connect_user  the account the button was pressed for
 * @param int  $session_user  the account signed in now, 0 when none
 * @param bool $impersonated  an operator is signed in as the person
 * @param int  $holder        the account holding the identity, 0 when none
 * @param bool $has_provider  the account has an identity at this provider
 * @return string 'signed_out', 'impersonated', 'already', 'other_account',
 *                'has_other' or 'connect'
 */
function pg_signin_connect_outcome($connect_user, $session_user, $impersonated, $holder, $has_provider)
{
    $connect_user = (int) $connect_user;

    if (($connect_user <= 0) || ((int) $session_user !== $connect_user)) {
        return 'signed_out';
    }

    if ($impersonated) {
        return 'impersonated';
    }

    if ((int) $holder === $connect_user) {
        return 'already';
    }

    if ((int) $holder > 0) {
        return 'other_account';
    }

    return $has_provider ? 'has_other' : 'connect';
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
 * The providers the operator turned on: config.signin_providers, and Google
 * by its own switch (config.oauth_google_enabled).
 *
 * @return array
 */
function pg_signin_switched_on()
{
    $list = pg_connect_scope_list(str_replace(',', ' ', defined('SIGNIN_PROVIDERS') ? (string) SIGNIN_PROVIDERS : ''));

    if (defined('OAUTH_GOOGLE_ENABLED') && OAUTH_GOOGLE_ENABLED && !in_array('google', $list, true)) {
        array_unshift($list, 'google');
    }

    return $list;
}

/**
 * The application a provider signs people in with: Google's is the one of
 * the security settings, every other provider's the connected accounts' one.
 *
 * @param string $key
 * @return array|null in the shape of pg_connect_app()
 */
function pg_signin_app($key)
{
    return ($key === 'google') ? pg_signin_google_app() : pg_connect_app($key);
}

/**
 * The "Sign in with Google" application of the security settings, or null
 * when it has no client ID. The secret is kept encrypted in config and read
 * only when asked for.
 *
 * @return array|null
 */
function pg_signin_google_app()
{
    if (!defined('OAUTH_GOOGLE_CLIENT_ID') || (OAUTH_GOOGLE_CLIENT_ID === '')) {
        return null;
    }

    $secret_row = db_item("SELECT oauth_google_client_secret, oauth_google_secret_iv FROM config");

    $client_secret = is_array($secret_row)
        ? (string) decode_ssl_keys((string) $secret_row['oauth_google_client_secret'], (string) $secret_row['oauth_google_secret_iv'])
        : '';

    return array(
        'client_id'     => (string) OAUTH_GOOGLE_CLIENT_ID,
        'client_secret' => $client_secret,
        'secret_stored' => ($client_secret !== ''),
        'options'       => array(),
        'enabled'       => true,
        'source'        => 'signin',
    );
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
        && pg_signin_app_ready($providers[$key], pg_signin_app($key));

    return $cache[$key];
}

/**
 * Whether any sign-in button is on.
 *
 * @return bool
 */
function pg_signin_any_enabled()
{
    foreach (array_keys(pg_signin_providers()) as $key) {
        if (pg_signin_enabled($key)) {
            return true;
        }
    }

    return false;
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
 * The return address to register with a provider for signing in. It carries
 * the host the visitor is on, so the callback finds the session the sign-in
 * started in.
 *
 * @param string $key '' for signin.php, the address of most providers
 * @return string
 */
function pg_signin_redirect_uri($key = '')
{
    return URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/'
        . pg_signin_return_file(((string) $key !== '') ? pg_connect_provider($key) : null);
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
 * A provider's mark as inline SVG. Google's is the one copy in auth.php.
 *
 * @param string $key
 * @param int    $size
 * @return string
 */
function pg_signin_logo_svg($key, $size = 18)
{
    $size = max(1, (int) $size);

    if ($key === 'google') {
        return pg_google_logo_svg($size);
    }

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
 * A provider's button colours and font, as its brand guidelines give them:
 * border, background, text colour and font family.
 *
 * @param string $key
 * @return array
 */
function pg_signin_button_style($key)
{
    $styles = array(
        'google'    => array('border' => '#dadce0', 'background' => '#fff', 'color' => '#3c4043', 'font' => 'Roboto,Arial,sans-serif'),
        'microsoft' => array('border' => '#8c8c8c', 'background' => '#fff', 'color' => '#5e5e5e', 'font' => "'Segoe UI',-apple-system,BlinkMacSystemFont,Roboto,Arial,sans-serif"),
        'apple'     => array('border' => '#000', 'background' => '#000', 'color' => '#fff', 'font' => "-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial,sans-serif"),
    );

    return isset($styles[$key]) ? $styles[$key] : array(
        'border' => '#dadce0', 'background' => '#fff', 'color' => '#3c4043', 'font' => "'Segoe UI',-apple-system,BlinkMacSystemFont,Roboto,Arial,sans-serif");
}

/**
 * One "Sign in with ..." button, the same markup for every provider. Inline
 * styles, so it looks the same on any front-end theme; the buttons share a
 * minimum width so a column of them lines up. The mark is spaced with a
 * margin rather than flex gap, which older browsers ignore.
 *
 * @param string $key
 * @param string $url
 * @param string $label
 * @param string $align 'center' under a sign-in form, 'left' in an account's list
 * @return string
 */
function pg_signin_button_html($key, $url, $label, $align = 'center')
{
    $style = pg_signin_button_style($key);

    return '<div class="pg-signin-button pg-signin-' . h($key) . '" style="margin-top:1em;text-align:' . (($align === 'left') ? 'left' : 'center') . '">'
        . '<a href="' . h($url) . '" rel="nofollow" style="display:inline-flex;align-items:center;justify-content:center;min-width:240px;max-width:100%;box-sizing:border-box;padding:9px 18px;border:1px solid ' . $style['border'] . ';border-radius:4px;background:' . $style['background'] . ' !important;color:' . $style['color'] . ' !important;font-weight:500;text-decoration:none !important;font-family:' . $style['font'] . ';font-size:14px !important;line-height:1.4">'
        . '<span style="display:inline-flex;margin-right:10px">' . pg_signin_logo_svg($key, 18) . '</span>'
        // !important on the label: a theme that paints links white would
        // otherwise leave a button with a mark and no words.
        . '<span style="color:' . $style['color'] . ' !important;font-size:14px !important;white-space:nowrap">' . h($label) . '</span></a></div>';
}

/**
 * The "Sign in with ..." button of one provider, or '' when it is off.
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

    if ($key === 'google') {
        $label = lang('Sign in with Google');
    } elseif ($key === 'apple') {
        $label = lang('Sign in with Apple');
    } elseif ($key === 'microsoft') {
        $label = lang('Sign in with Microsoft');
    } else {
        $label = lang(array('string' => 'Sign in with {var:1}', 'vars' => pg_signin_provider_label($key)));
    }

    return pg_signin_button_html($key, $url, $label);
}

/**
 * Every sign-in button a screen shows, in button order, in one block; '' when
 * none is on. This is the one place the buttons come from: the login,
 * registration and membership screens, their widgets (the google_signin and
 * google_signup sections) and custom layouts ($google_signin) all print it,
 * so a provider turned on in the settings appears everywhere at once.
 *
 * @param string $send_to
 * @param mixed  $signup
 * @return string
 */
function pg_signin_buttons($send_to = '', $signup = 'create')
{
    $html = '';

    foreach (array_keys(pg_signin_providers()) as $key) {
        $html .= pg_signin_button($key, $send_to, $signup);
    }

    return ($html === '') ? '' : '<div class="pg-signin-buttons">' . $html . '</div>';
}

/**
 * The providers whose buttons visitors see now, by label in button order; and
 * the ones switched on whose button stays hidden, because their application
 * is not complete or the upgrade has not run. For the settings card.
 *
 * @return array array('shown' => array(key => label), 'hidden' => array(key => label))
 */
function pg_signin_button_state()
{
    $state = array('shown' => array(), 'hidden' => array());
    $switched_on = pg_signin_switched_on();

    foreach (pg_signin_providers() as $key => $provider) {
        if (pg_signin_enabled($key)) {
            $state['shown'][$key] = (string) $provider['label'];
        } elseif (in_array($key, $switched_on, true)) {
            $state['hidden'][$key] = (string) $provider['label'];
        }
    }

    return $state;
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
        return ($provider_key === 'google') ? pg_signin_google_legacy_user($subject) : 0;
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
 * Whether user.user_google_id is there to read: where Google identities were
 * kept until 10.75 moved them to user_identities.
 *
 * @return bool
 */
function pg_signin_google_legacy_column()
{
    return pg_schema_has('user', 'user_google_id');
}

/**
 * The account whose Google link is still in user.user_google_id, moved to
 * user_identities on the way so it is found there from now on; 0 when there
 * is none. A link 10.75 has not moved yet keeps signing in.
 *
 * @param string $subject Google's sub
 * @return int
 */
function pg_signin_google_legacy_user($subject)
{
    if (((string) $subject === '') || !pg_signin_google_legacy_column()) {
        return 0;
    }

    $row = db_item("SELECT user_id, user_email FROM user WHERE user_google_id = '" . e($subject) . "'");

    if (!is_array($row)) {
        return 0;
    }

    $user_id = (int) $row['user_id'];

    if (pg_signin_identity_add($user_id, 'google', $subject, (string) $row['user_email'])) {
        db("UPDATE user SET user_google_id = NULL WHERE user_id = '" . $user_id . "'");
    }

    return $user_id;
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

    // A Google link 10.75 has not moved yet is a way in too.
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
 * Removes an account's identity at one provider; for Google, a link not yet
 * moved out of user.user_google_id too.
 *
 * @param int    $user_id
 * @param string $provider_key
 */
function pg_signin_identity_remove($user_id, $provider_key)
{
    if (pg_signin_schema_ready()) {
        db("DELETE FROM user_identities WHERE user_id = '" . (int) $user_id . "' AND provider = '" . e($provider_key) . "'");
    }

    if (($provider_key === 'google') && pg_signin_google_legacy_column()) {
        db("UPDATE user SET user_google_id = NULL WHERE user_id = '" . (int) $user_id . "'");
    }
}

/**
 * The sign-in providers of several accounts, for the user and session
 * lists: user_id => provider keys in button order.
 *
 * @param array $user_ids
 * @return array<int,array>
 */
function pg_signin_identities_of($user_ids)
{
    $ids = array_values(array_unique(array_filter(array_map('intval', (array) $user_ids))));

    if (!$ids || !pg_signin_schema_ready()) {
        return array();
    }

    $rows = db_items(
        "SELECT user_id, provider
        FROM user_identities
        WHERE user_id IN (" . implode(',', $ids) . ")
        ORDER BY id");

    return pg_signin_group_identities($rows, array_keys(pg_signin_providers()));
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
 * @param string $back    the page the button was on (pg_signin_referer_path())
 * @param int    $connect_user the account a "Connect" button was pressed for
 *                        (pg_signin_connect_begin()); 0 for a sign-in
 * @param bool   $panel   see pg_signin_connect_url()
 * @return array ok, url, error
 */
function pg_signin_start($key, $send_to, $signup, $back = '', $connect_user = 0, $panel = false)
{
    if (!pg_signin_enabled($key)) {
        return array('ok' => false, 'url' => '', 'error' => lang('This sign-in is not available.'));
    }

    $providers = pg_signin_providers();
    $provider = $providers[$key];
    $signin = $provider['signin'];
    $app = pg_signin_app($key);

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
        'signup'   => (pg_google_signup_allowed() && ((int) $connect_user <= 0)) ? pg_signin_signup_mode($signup) : 'none',
        'send_to'  => pg_safe_back_url($send_to, ''),
        'back'     => pg_safe_back_url((string) $back, ''),
        'started'  => time(),
        'connect_user' => max(0, (int) $connect_user),
        'panel'    => (bool) $panel,
    );

    $_SESSION['software']['signin'] = $pending;

    $params = array(
        'response_type' => 'code',
        'client_id'     => (string) $app['client_id'],
        'redirect_uri'  => pg_signin_redirect_uri($key),
        'scope'         => implode(' ', pg_connect_scope_list(isset($signin['scopes']) ? $signin['scopes'] : array('openid', 'email'))),
        'state'         => $state,
        'nonce'         => $nonce,
    );

    if ($with_pkce) {
        $params['code_challenge'] = $pkce['challenge'];
        $params['code_challenge_method'] = 'S256';
    }

    foreach (pg_signin_authorize_params($provider) as $name => $value) {
        if (!isset($params[$name])) {
            $params[$name] = (string) $value;
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
 *               email_verified, first_name, last_name, signup, send_to, back,
 *               connect_user (the account a "Connect" was pressed for, 0 for
 *               a sign-in), panel
 */
function pg_signin_finish($params)
{
    $result = array(
        'ok' => false, 'cancelled' => false, 'error' => '', 'provider' => '', 'label' => '',
        'subject' => '', 'email' => '', 'email_verified' => false, 'first_name' => '', 'last_name' => '',
        'signup' => 'none', 'send_to' => '', 'back' => '', 'connect_user' => 0, 'panel' => false,
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

    // No sign-in waiting under this state in this session: the browser came
    // back without the session the sign-in began in, or with an answer that
    // was already used.
    if (!is_array($pending)) {
        log_activity(lang('sign-in refused: no sign-in started in this browser session was waiting for the answer'), '');

        return $fail(lang('This sign-in could not be verified, because this browser did not come back with the session the sign-in started in. This happens when the browser blocks or clears this site\'s cookies, when the sign-in finished in another browser or app, or when the return address was opened again (from the history, a bookmark or a copied link: each one works once). Please start the sign-in again from this site.'));
    }

    $result['signup'] = (string) $pending['signup'];
    $result['send_to'] = (string) $pending['send_to'];
    $result['provider'] = (string) $pending['provider'];
    $result['back'] = isset($pending['back']) ? (string) $pending['back'] : '';
    $result['connect_user'] = isset($pending['connect_user']) ? (int) $pending['connect_user'] : 0;
    $result['panel'] = !empty($pending['panel']);

    if ((int) $pending['started'] < (time() - 900)) {
        return $fail(lang('The sign-in took too long. Please try again.'));
    }

    $providers = pg_signin_providers();
    $key = $result['provider'];
    $app = pg_signin_app($key);

    if (!isset($providers[$key]) || !pg_signin_app_ready($providers[$key], $app)) {
        return $fail(lang('This sign-in is not available.'));
    }

    $provider = $providers[$key];
    $signin = $provider['signin'];
    $result['label'] = (string) $provider['label'];

    if (isset($params['error'])) {
        $error = is_string($params['error']) ? $params['error'] : '';

        // The person backed out, or the provider would not ask them: said
        // so on the failure screen, because a silent return to the sign-in
        // page reads as a button that did nothing.
        if (in_array($error, array('access_denied', 'user_cancelled_authorize', 'consent_required', 'interaction_required'), true)) {
            $result['cancelled'] = true;

            if ($result['connect_user'] > 0) {
                return $fail(lang(array('string' => 'Connecting was cancelled at {var:1}, so nothing changed on your account.', 'vars' => $provider['label'])));
            }

            return $fail(lang(array('string' => 'The sign-in was cancelled at {var:1}, so you are not signed in.', 'vars' => $provider['label'])));
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
        'redirect_uri' => pg_signin_redirect_uri($key),
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
    // never taken over, Google's still in the old column included.
    $legacy_google = (($identity['provider'] === 'google') && pg_signin_google_legacy_column())
        ? " AND (user.user_google_id IS NULL OR user.user_google_id = '')"
        : '';

    $match_id = (int) db_value(
        "SELECT user.user_id
        FROM user
        LEFT JOIN user_identities
            ON user_identities.user_id = user.user_id
            AND user_identities.provider = '" . e($identity['provider']) . "'
        WHERE user.user_email = '" . e($identity['email']) . "' AND user_identities.id IS NULL" . $legacy_google . "
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

/**
 * The account that uses an e-mail address, or 0. Asked only after
 * pg_signin_match_user() found nobody for a verified address: an account
 * here is then already tied to another person at the provider, and is
 * neither taken over nor opened a second time.
 *
 * @param string $email
 * @return int
 */
function pg_signin_address_user($email)
{
    if ((string) $email === '') {
        return 0;
    }

    return (int) db_value("SELECT user_id FROM user WHERE user_email = '" . e($email) . "' ORDER BY user_id LIMIT 1");
}


/* ---------------------------------------------------------------------------
   Connecting a provider while signed in
   --------------------------------------------------------------------------- */

/**
 * The token a "Connect" button carries: one per session, so a link planted
 * elsewhere cannot start connecting a provider to whoever opens it.
 *
 * @return string
 */
function pg_signin_connect_token()
{
    if (empty($_SESSION['software']['signin_connect_token']) || !is_string($_SESSION['software']['signin_connect_token'])) {
        $_SESSION['software']['signin_connect_token'] = bin2hex(random_bytes(16));
    }

    return $_SESSION['software']['signin_connect_token'];
}

/**
 * Whether an account has an identity at a provider, Google's link still in
 * user.user_google_id included.
 *
 * @param int        $user_id
 * @param string     $provider_key
 * @param array|null $identities pg_signin_identities() of the account, when
 *                               already read
 * @return bool
 */
function pg_signin_connected($user_id, $provider_key, $identities = null)
{
    if (!is_array($identities)) {
        $identities = pg_signin_identities($user_id);
    }

    if (array_key_exists((string) $provider_key, $identities)) {
        return true;
    }

    return ($provider_key === 'google') && pg_signin_google_legacy_column()
        && ((string) db_value("SELECT user_google_id FROM user WHERE user_id = '" . (int) $user_id . "'") !== '');
}

/**
 * The providers an account can still connect: switched on with a complete
 * application, and not connected to it yet. None while an operator is signed
 * in as the person (pg_signin_connect_problem()).
 *
 * @param int $user_id
 * @return array provider keys in button order
 */
function pg_signin_connectable($user_id)
{
    if (((int) $user_id <= 0) || !empty($_SESSION['software']['logged_in_as_different_user'])) {
        return array();
    }

    $identities = pg_signin_identities($user_id);
    $list = array();

    foreach (array_keys(pg_signin_providers()) as $key) {
        if (pg_signin_enabled($key) && !pg_signin_connected($user_id, $key, $identities)) {
            $list[] = $key;
        }
    }

    return $list;
}

/**
 * The address of a "Connect" button: signin.php with the provider and this
 * session's token; '' when the provider is off.
 *
 * @param string $key
 * @param string $send_to the page to come back to, a same-site address
 * @param bool   $panel   pressed on the panel's user screen, which shows the
 *                        connection itself; the account pages get a notice
 * @return string
 */
function pg_signin_connect_url($key, $send_to = '', $panel = false)
{
    if (!pg_signin_enabled($key)) {
        return '';
    }

    $query = array('provider' => $key, 'connect' => pg_signin_connect_token());

    if ((string) $send_to !== '') {
        $query['send_to'] = (string) $send_to;
    }

    if ($panel) {
        $query['from'] = 'panel';
    }

    return OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/signin.php?' . http_build_query($query);
}

/**
 * The "Connect ..." buttons of an account, one per provider it can still
 * connect, in the markup of the sign-in buttons; '' when there is none.
 *
 * @param int    $user_id
 * @param string $send_to
 * @param bool   $panel see pg_signin_connect_url()
 * @return string
 */
function pg_signin_connect_buttons($user_id, $send_to = '', $panel = false)
{
    $html = '';

    foreach (pg_signin_connectable($user_id) as $key) {
        $url = pg_signin_connect_url($key, $send_to, $panel);

        if ($url !== '') {
            $html .= pg_signin_button_html($key, $url,
                lang(array('string' => 'Connect with {var:1}', 'vars' => pg_signin_provider_label($key))), 'left');
        }
    }

    return ($html === '') ? '' : '<div class="pg-signin-buttons pg-signin-connect">' . $html . '</div>';
}

/**
 * Checks a "Connect" request before it leaves for the provider: the account
 * signed in now, which the identity will be tied to. Ends on the failure
 * screen when it may not start.
 *
 * @param string $key
 * @param string $token   the token the button carried
 * @param string $send_to
 * @param bool   $panel
 * @return int the account
 */
function pg_signin_connect_begin($key, $token, $send_to, $panel)
{
    $user_id = pg_session_signed_in() ? (int) $_SESSION['sessionuserid'] : 0;
    $label = pg_signin_provider_label($key);
    $context = array('provider' => $key, 'send_to' => $send_to, 'connect' => true, 'panel' => $panel);

    if (!pg_signin_enabled($key)) {
        $context['provider'] = '';

        pg_signin_fail(lang('This sign-in is not available.'), $context);
    }

    $problem = pg_signin_connect_problem($user_id, !empty($_SESSION['software']['logged_in_as_different_user']),
        $token, isset($_SESSION['software']['signin_connect_token']) ? $_SESSION['software']['signin_connect_token'] : '',
        ($user_id > 0) && pg_signin_connected($user_id, $key));

    if ($problem === 'signed_out') {
        pg_signin_fail(lang(array('string' => 'You are not signed in, so {var:1} cannot be connected to an account. Please sign in, then connect {var:1} from your account page.', 'vars' => $label)), $context);
    }

    if ($problem === 'impersonated') {
        pg_signin_fail(lang(array('string' => '{var:1} cannot be connected while signed in as another user.', 'vars' => $label)), $context);
    }

    if ($problem === 'token') {
        pg_signin_fail(lang(array('string' => 'This link to connect {var:1} is not from this browser session. Please open your account page and press the button there.', 'vars' => $label)), $context);
    }

    if ($problem === 'connected') {
        pg_signin_fail(lang(array('string' => 'This account is already connected to {var:1}. To connect another {var:1} account, disconnect this one first.', 'vars' => $label)), $context);
    }

    return $user_id;
}

/**
 * Finishes a "Connect": ties the identity the provider vouched for to the
 * account that pressed the button, whatever e-mail address either side has,
 * and goes back to the page the button was on. Nobody is signed in or
 * opened. An identity held by another account here is refused on the
 * failure screen, as a sign-in into that account would be. Does not return.
 *
 * @param array $identity what pg_signin_finish() returned
 */
function pg_signin_connect_finish($identity)
{
    $label = $identity['label'];
    $email = $identity['email'];
    $provider_key = $identity['provider'];
    $user_id = (int) $identity['connect_user'];
    $session_user = pg_session_signed_in() ? (int) $_SESSION['sessionuserid'] : 0;
    $holder = pg_signin_identity_user($provider_key, $identity['subject']);
    $reported = ($email !== '') ? $email : $identity['subject'];

    $outcome = pg_signin_connect_outcome($user_id, $session_user, !empty($_SESSION['software']['logged_in_as_different_user']),
        $holder, ($user_id > 0) && pg_signin_connected($user_id, $provider_key));

    if ($outcome === 'connect') {
        // Not tied when a request a moment earlier tied the same identity to
        // another account: the same refusal as below.
        $outcome = pg_signin_identity_add($user_id, $provider_key, $identity['subject'], $email) ? 'connected' : 'other_account';
    }

    if ($outcome === 'signed_out') {
        pg_signin_fail(lang(array('string' => 'You are no longer signed in to the account that asked to connect {var:1}, so nothing was connected. Please sign in and connect {var:1} again from your account page.', 'vars' => $label)), $identity);
    }

    if ($outcome === 'impersonated') {
        pg_signin_fail(lang(array('string' => '{var:1} cannot be connected while signed in as another user.', 'vars' => $label)), $identity);
    }

    $username = (string) db_value("SELECT user_username FROM user WHERE user_id = '" . $user_id . "'");

    if ($outcome === 'other_account') {
        log_activity(lang(array(
            'string' => '{var:1} connection refused: the {var:1} account ({var:2}) is connected to another account',
            'vars'   => array($label, $reported))), $username);
        pg_signin_fail(lang(array(
            'string' => 'You signed in to {var:1} as {var:2}. That {var:1} account is already connected to another account on this site, so it cannot be connected to yours. Sign in to the other account and disconnect {var:1} there, or ask the site owner to disconnect it.',
            'vars'   => array($label, $reported))), $identity);
    }

    if ($outcome === 'has_other') {
        pg_signin_fail(lang(array('string' => 'This account is already connected to another {var:1} account. To connect this one, disconnect the other first.', 'vars' => $label)), $identity);
    }

    if ($outcome === 'connected') {
        log_activity(lang(array('string' => 'user connected {var:1} ({var:2})', 'vars' => array($label, $reported))), $username);
        $notice = lang(array('string' => '{var:1} is connected. From now on you can sign in with {var:1} too.', 'vars' => $label));
    } else {
        pg_signin_identity_touch($provider_key, $identity['subject'], $identity['email_verified'] ? $email : '');
        $notice = lang(array('string' => 'This {var:1} account was already connected to your account.', 'vars' => $label));
    }

    // The panel's user screen shows the connection in its list; the account
    // pages say it in their messages.
    if (empty($identity['panel'])) {
        $messages = new liveform('my_account_profile');
        $messages->add_notice($notice);
    }

    $landing = pg_safe_back_url($identity['send_to'], pg_safe_back_url($identity['back'], ''));

    if ($landing === '') {
        $landing = (string) get_page_type_url('my account');
    }

    go($landing);
    exit();
}


/* ---------------------------------------------------------------------------
   The failure screen
   --------------------------------------------------------------------------- */

/**
 * Where the failure screen sends the visitor back to: the page the button
 * was on, else the screen of the kind that started the sign-in (sign in,
 * registration, membership), else the home page.
 *
 * @param string $back
 * @param string $signup
 * @return string
 */
function pg_signin_back_url($back, $signup)
{
    $back = pg_safe_back_url((string) $back, '');

    if ($back !== '') {
        return $back;
    }

    $types = array('create' => 'registration entrance', 'membership' => 'membership entrance');
    $type = isset($types[$signup]) ? $types[$signup] : 'login';
    $url = get_page_type_url($type);

    if ((($url === false) || ($url === '')) && ($type !== 'login')) {
        $url = get_page_type_url('login');
    }

    return (($url !== false) && ($url !== '')) ? (string) $url : PATH;
}

/**
 * Ends a sign-in that did not complete on its own screen: what happened, a
 * way back to the page the button was on, and another try with the same
 * provider while it is on. Does not return.
 *
 * @param string $message plain text
 * @param array  $context provider, send_to, signup, back, cancelled (what
 *                        pg_signin_finish() returned carries them all);
 *                        connect (or connect_user) and panel for a
 *                        "Connect", whose way
 *                        back is the page the button was on (send_to) and
 *                        whose other try is another "Connect"
 */
function pg_signin_fail($message, $context = array())
{
    $context = is_array($context) ? $context : array();
    $provider_key = isset($context['provider']) ? (string) $context['provider'] : '';

    // google_auth.php names its provider even when the session holds nothing.
    if (($provider_key === '') && defined('PG_SIGNIN_PROVIDER')) {
        $provider_key = (string) PG_SIGNIN_PROVIDER;
    }

    $signup = isset($context['signup']) ? (string) $context['signup'] : 'none';
    $send_to = isset($context['send_to']) ? (string) $context['send_to'] : '';
    $cancelled = !empty($context['cancelled']);

    $connecting = !empty($context['connect']) || !empty($context['connect_user']);
    $back = isset($context['back']) ? (string) $context['back'] : '';

    if ($connecting) {
        $title = $cancelled ? lang('Connecting cancelled') : lang('Connecting did not complete');
        $back = ($send_to !== '') ? $send_to : $back;
    } else {
        $title = $cancelled ? lang('Sign-in cancelled') : lang('Sign-in did not complete');
    }

    $links = array(array(
        'url'     => pg_signin_back_url($back, $connecting ? 'none' : $signup),
        'label'   => lang('Go Back'),
        'primary' => true,
    ));

    if ($provider_key === '') {
        $again = '';
    } elseif ($connecting) {
        // Another try only for the account still signed in.
        $again = (pg_session_signed_in() && empty($_SESSION['software']['logged_in_as_different_user']))
            ? pg_signin_connect_url($provider_key, $send_to, !empty($context['panel']))
            : '';
    } else {
        $again = pg_signin_url($provider_key, $send_to, $signup);
    }

    if ($again !== '') {
        $links[] = array(
            'url'   => $again,
            'label' => lang(array('string' => 'Try again with {var:1}', 'vars' => pg_signin_provider_label($provider_key))),
        );
    }

    if (!headers_sent()) {
        header('Cache-Control: no-store');
        header('X-Robots-Tag: noindex, nofollow');
    }

    echo output_header_secure(array('title' => $title))
        . pg_signin_failure_card($title, $message, $links, $cancelled)
        . output_footer_secure();
    exit();
}
