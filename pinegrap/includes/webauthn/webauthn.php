<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * WebAuthn (passkeys) for the relying party, without Composer or an outside
 * library: the options a browser passes to navigator.credentials.create() and
 * .get(), the responses it sends back, and the two checks of the W3C Web
 * Authentication specification (Level 3):
 *
 *   pg_webauthn_verify_registration()  section 7.1, "Registering a New
 *                                      Credential": client data, authenticator
 *                                      data, credential public key, "none" and
 *                                      "packed" attestation
 *   pg_webauthn_verify_assertion()     section 7.2, "Verifying an
 *                                      Authentication Assertion": client data,
 *                                      flags, signature, signature counter
 *
 * Both take raw bytes and answer array('ok' => true, ...) or
 * array('ok' => false, 'error' => <code>). The codes:
 *
 *   response               the browser's JSON is not a credential response
 *   client_data            clientDataJSON is not a JSON object with type,
 *                          challenge and origin
 *   type                   a creation answered where an assertion was expected,
 *                          or the reverse
 *   challenge              not the challenge of this ceremony (also: no
 *                          challenge kept, e.g. an expired session)
 *   origin                 not one of the origins the site expects
 *   cross_origin           made inside a cross-origin frame (crossOrigin or
 *                          topOrigin present), which the site never asks for
 *   attestation_object     not a CBOR map with fmt, attStmt and authData
 *   auth_data              authenticator data malformed, with bytes left, or
 *                          carrying an attested credential in a sign-in
 *   rp_id                  made for another relying party ID
 *   user_present           the user presence flag is not set
 *   user_verified          user verification was required and not done
 *   backup_flags           backed up without being backup eligible, the
 *                          eligibility differs from what was registered, or
 *                          the stored eligibility is not a yes/no value
 *   credential             no attested credential, a credential ID over 1023
 *                          bytes, not the credential the response names, or a
 *                          stored record without its ID or counter
 *   algorithm              a key type, curve or algorithm other than ES256 or
 *                          RS256 (or one the caller did not allow)
 *   public_key             a credential public key that is malformed or weak
 *   attestation_format     an attestation statement format other than "none"
 *                          or "packed"
 *   attestation_statement  an attestation statement or certificate that does
 *                          not meet its format's rules
 *   signature              a signature that does not verify
 *   sign_count             the signature counter did not move forward, a sign
 *                          of a cloned authenticator
 *   user_handle            the response's user handle is missing where the
 *                          account is found by it, or is not the account's
 *
 * The codes are for the site's own log and logic. A visitor gets one plain
 * refusal: telling "user_handle" from "signature" would tell a stranger which
 * credential belongs to which account.
 *
 * What a credential record keeps, from a successful registration: the
 * credential ID, the PEM public key and its algorithm, the signature counter,
 * the backup eligibility, the AAGUID and the transports the browser reported.
 *
 * Attestation is never trusted here: there are no trust anchors, so a
 * "packed" statement is checked for being well formed and correctly signed,
 * then counted as unverified (WebAuthn 7.1, steps 24 and 29, let a relying
 * party register such a credential without proof of the authenticator
 * model). The creation options ask for attestation "none", and a browser
 * honouring that sends the "none" format.
 *
 * Values the caller passes in are read strictly: a missing or unreadable
 * credential ID, counter or stored flag refuses the ceremony, and an
 * unrecognised user verification setting counts as "required". A check never
 * loosens because something was left out.
 *
 * Splits: cbor.php reads CBOR and base64url, cose.php turns COSE keys into
 * PEM and checks signatures and attestation certificates.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/webauthn/cbor.php');
require_once(PG_FUNCTIONS_DIR . '/includes/webauthn/cose.php');

// Largest clientDataJSON read. A browser writes a few hundred bytes; the
// specification lets clients add members, so there is room for that.
if (!defined('PG_WEBAUTHN_MAX_CLIENT_DATA_BYTES')) {
    define('PG_WEBAUTHN_MAX_CLIENT_DATA_BYTES', 16384);
}

// Largest attestation object read. A "packed" statement with a certificate
// chain is a few kilobytes.
if (!defined('PG_WEBAUTHN_MAX_OBJECT_BYTES')) {
    define('PG_WEBAUTHN_MAX_OBJECT_BYTES', 16384);
}

// Largest authenticator data read: 37 bytes, a credential ID of at most 1023
// bytes and a public key at registration, a few extension outputs.
if (!defined('PG_WEBAUTHN_MAX_AUTH_DATA_BYTES')) {
    define('PG_WEBAUTHN_MAX_AUTH_DATA_BYTES', 4096);
}

// Largest response JSON a browser posts, both binary members base64url
// encoded.
if (!defined('PG_WEBAUTHN_MAX_RESPONSE_BYTES')) {
    define('PG_WEBAUTHN_MAX_RESPONSE_BYTES', 65536);
}

// How long the browser offers the ceremony, in milliseconds.
if (!defined('PG_WEBAUTHN_TIMEOUT')) {
    define('PG_WEBAUTHN_TIMEOUT', 300000);
}

// ---------------------------------------------------------------------------
// The site as a relying party
// ---------------------------------------------------------------------------

/**
 * Whether this PHP can verify passkeys: OpenSSL with EC keys and a source of
 * random bytes.
 *
 * @return bool
 */
function pg_webauthn_supported()
{
    return function_exists('openssl_verify')
        && function_exists('openssl_pkey_get_public')
        && function_exists('openssl_pkey_get_details')
        && function_exists('openssl_x509_parse')
        && function_exists('random_bytes')
        && defined('OPENSSL_ALGO_SHA256')
        && defined('OPENSSL_KEYTYPE_EC');
}

/**
 * A new challenge: 32 random bytes. The caller keeps it until the response
 * arrives and uses it once.
 *
 * @return string
 */
function pg_webauthn_challenge()
{
    return random_bytes(32);
}

/**
 * The relying party ID for a host name: the host alone, lower case, without
 * the port or a trailing dot, in its ASCII form. A passkey belongs to it on
 * every path and language prefix of the site. '' where no passkey can be
 * made: an IP address (browsers take a domain only), an empty or malformed
 * name, or an international name this PHP cannot convert.
 *
 * @param string $host a host name, with or without a port
 * @return string
 */
function pg_webauthn_rp_id($host)
{
    if (!is_string($host)) {
        return '';
    }

    $host = strtolower(trim($host));

    if (($host === '') || ($host[0] === '[')) {
        return '';
    }

    if (preg_match('/^(.+):([0-9]{1,5})$/', $host, $match) === 1) {
        $host = $match[1];
    }

    $host = rtrim($host, '.');

    if (($host === '') || (filter_var($host, FILTER_VALIDATE_IP) !== false)) {
        return '';
    }

    if (preg_match('/[^\x21-\x7e]/', $host) === 1) {

        if (!function_exists('idn_to_ascii')) {
            return '';
        }

        $ascii = defined('INTL_IDNA_VARIANT_UTS46')
            ? @idn_to_ascii($host, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46)
            : @idn_to_ascii($host);

        if (!is_string($ascii) || ($ascii === '')) {
            return '';
        }

        $host = strtolower($ascii);

    }

    if (strlen($host) > 253) {
        return '';
    }

    foreach (explode('.', $host) as $label) {
        if (preg_match('/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/', $label) !== 1) {
            return '';
        }
    }

    return $host;
}

/**
 * The origin a browser writes into clientDataJSON for pages on this host:
 * scheme://host, the host as pg_webauthn_rp_id() gives it (lower case, ASCII
 * form of an international name), the port only when it is a valid port that
 * is not the scheme's default.
 *
 * @param string $scheme 'https', 'https://', 'http' or 'http://'
 * @param string $host   the host name, with or without a port
 * @return string '' for anything that cannot be such an origin
 */
function pg_webauthn_origin($scheme, $host)
{
    if (!is_string($scheme) || !is_string($host)) {
        return '';
    }

    $scheme = strtolower(trim($scheme));
    $scheme = (substr($scheme, -3) === '://') ? substr($scheme, 0, -3) : $scheme;
    $host = trim($host);
    $port = '';

    if (($scheme !== 'https') && ($scheme !== 'http')) {
        return '';
    }

    if (preg_match('/^([^:]*):([^:]*)$/', $host, $match) === 1) {

        $host = $match[1];
        $port = $match[2];

        if ((preg_match('/^[1-9][0-9]{0,4}$/', $port) !== 1) || ((int) $port > 65535)) {
            return '';
        }

        if ($port === (($scheme === 'https') ? '443' : '80')) {
            $port = '';
        }

    }

    // Anything but a plain host name (a path, credentials, a second colon)
    // never reaches the name check, which would take it apart.
    if (preg_match('#[/?\\\\@\#\s:]#', $host) === 1) {
        return '';
    }

    $host = pg_webauthn_rp_id($host);

    if ($host === '') {
        return '';
    }

    return $scheme . '://' . $host . (($port !== '') ? ':' . $port : '');
}

/**
 * Whether a browser offers passkeys on pages served this way: WebAuthn needs
 * a secure context, which is https, or http on localhost while developing,
 * and a relying party ID.
 *
 * @param string $scheme
 * @param string $host
 * @return bool
 */
function pg_webauthn_secure_context($scheme, $host)
{
    if (!is_string($scheme)) {
        return false;
    }

    $scheme = strtolower(trim($scheme));
    $scheme = (substr($scheme, -3) === '://') ? substr($scheme, 0, -3) : $scheme;
    $rp_id = pg_webauthn_rp_id($host);

    if ($rp_id === '') {
        return false;
    }

    if ($scheme === 'https') {
        return true;
    }

    return ($scheme === 'http') && (($rp_id === 'localhost') || (substr($rp_id, -10) === '.localhost'));
}

/**
 * An AAGUID as the UUID text authenticator lists use, or '' when it is not
 * 16 bytes. All zeros means the authenticator did not say what it is.
 *
 * @param string $bytes
 * @return string
 */
function pg_webauthn_aaguid_text($bytes)
{
    if (!is_string($bytes) || (strlen($bytes) !== 16)) {
        return '';
    }

    $hex = bin2hex($bytes);

    return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-' . substr($hex, 12, 4)
        . '-' . substr($hex, 16, 4) . '-' . substr($hex, 20, 12);
}

// ---------------------------------------------------------------------------
// Options for the browser
// ---------------------------------------------------------------------------

/**
 * The publicKey options of navigator.credentials.create(), as the JSON form
 * PublicKeyCredential.parseCreationOptionsFromJSON() reads (binary values in
 * base64url).
 *
 * @param array  $rp        id (pg_webauthn_rp_id()), name (the site's name)
 * @param array  $user      handle (1-64 random bytes, the same for every
 *                          credential of the account, never personal data),
 *                          name (what the account is signed in with),
 *                          display_name
 * @param string $challenge from pg_webauthn_challenge()
 * @param array  $settings  exclude (list of id, transports: the account's
 *                          existing credentials, so an authenticator is not
 *                          registered twice), resident_key ('preferred',
 *                          'required', 'discouraged'), user_verification
 *                          ('preferred', 'required', 'discouraged'),
 *                          attachment ('platform', 'cross-platform' or ''),
 *                          timeout (ms)
 * @return array|false false when the relying party, user handle or challenge
 *                     is unusable
 */
function pg_webauthn_creation_options($rp, $user, $challenge, $settings = array())
{
    $rp_id = pg_webauthn_string($rp, 'id');
    $rp_name = pg_webauthn_string($rp, 'name');
    $handle = pg_webauthn_string($user, 'handle');

    if (($rp_id === '') || ($handle === '') || (strlen($handle) > 64) || !is_string($challenge) || (strlen($challenge) < 16)) {
        return false;
    }

    $resident_key = pg_webauthn_setting($settings, 'resident_key', array('preferred', 'required', 'discouraged'), 'preferred');
    $selection = array(
        'residentKey'        => $resident_key,
        'requireResidentKey' => ($resident_key === 'required'),
        'userVerification'   => pg_webauthn_setting($settings, 'user_verification', array('preferred', 'required', 'discouraged'), 'preferred'),
    );

    $attachment = pg_webauthn_setting($settings, 'attachment', array('platform', 'cross-platform'), '');

    if ($attachment !== '') {
        $selection['authenticatorAttachment'] = $attachment;
    }

    $parameters = array();

    foreach (pg_webauthn_algorithms() as $alg) {
        $parameters[] = array('type' => 'public-key', 'alg' => $alg);
    }

    return array(
        'rp'                     => array('id' => $rp_id, 'name' => ($rp_name !== '') ? $rp_name : $rp_id),
        'user'                   => array(
            'id'          => pg_webauthn_base64url_encode($handle),
            'name'        => pg_webauthn_string($user, 'name'),
            'displayName' => pg_webauthn_string($user, 'display_name'),
        ),
        'challenge'              => pg_webauthn_base64url_encode($challenge),
        'pubKeyCredParams'       => $parameters,
        'timeout'                => pg_webauthn_timeout($settings),
        'excludeCredentials'     => pg_webauthn_descriptors(isset($settings['exclude']) ? $settings['exclude'] : array()),
        'authenticatorSelection' => $selection,
        'attestation'            => 'none',
        'extensions'             => array('credProps' => true),
    );
}

/**
 * The publicKey options of navigator.credentials.get(), in the JSON form
 * PublicKeyCredential.parseRequestOptionsFromJSON() reads.
 *
 * @param string $rp_id
 * @param string $challenge
 * @param array  $settings allow (list of id, transports: the account's
 *                         credentials when the account is known; empty to let
 *                         the browser offer every passkey it has for the site),
 *                         user_verification, timeout (ms)
 * @return array|false
 */
function pg_webauthn_request_options($rp_id, $challenge, $settings = array())
{
    if (!is_string($rp_id) || ($rp_id === '') || !is_string($challenge) || (strlen($challenge) < 16)) {
        return false;
    }

    return array(
        'challenge'        => pg_webauthn_base64url_encode($challenge),
        'rpId'             => $rp_id,
        'timeout'          => pg_webauthn_timeout($settings),
        'userVerification' => pg_webauthn_setting($settings, 'user_verification', array('preferred', 'required', 'discouraged'), 'preferred'),
        'allowCredentials' => pg_webauthn_descriptors(isset($settings['allow']) ? $settings['allow'] : array()),
    );
}

/**
 * PublicKeyCredentialDescriptor entries for a list of credentials.
 *
 * @param array $credentials list of id (bytes), transports
 * @return array
 */
function pg_webauthn_descriptors($credentials)
{
    $descriptors = array();

    foreach ((array) $credentials as $credential) {

        if (pg_webauthn_string($credential, 'id') === '') {
            continue;
        }

        $descriptor = array('type' => 'public-key', 'id' => pg_webauthn_base64url_encode($credential['id']));
        $transports = pg_webauthn_transports(isset($credential['transports']) ? $credential['transports'] : array());

        if (count($transports) > 0) {
            $descriptor['transports'] = $transports;
        }

        $descriptors[] = $descriptor;

    }

    return $descriptors;
}

/**
 * The transports of a credential (getTransports()), limited to the values
 * the specification defines, each once, in the order given.
 *
 * @param mixed $transports
 * @return string[]
 */
function pg_webauthn_transports($transports)
{
    $known = array('usb', 'nfc', 'ble', 'smart-card', 'hybrid', 'internal');
    $list = array();

    foreach ((array) $transports as $transport) {
        if (is_string($transport) && in_array($transport, $known, true) && !in_array($transport, $list, true)) {
            $list[] = $transport;
        }
    }

    return $list;
}

/**
 * A setting from a fixed list of values, or its default.
 *
 * @param array  $settings
 * @param string $name
 * @param array  $allowed
 * @param string $default
 * @return string
 */
function pg_webauthn_setting($settings, $name, $allowed, $default)
{
    if (is_array($settings) && isset($settings[$name]) && is_string($settings[$name]) && in_array($settings[$name], $allowed, true)) {
        return $settings[$name];
    }

    return $default;
}

/**
 * A text value of an array, '' when it is missing or not a string. Values
 * are never cast: an array or object where text belongs gives '', which the
 * check that follows refuses.
 *
 * @param mixed  $array
 * @param string $key
 * @return string
 */
function pg_webauthn_string($array, $key)
{
    return (is_array($array) && isset($array[$key]) && is_string($array[$key])) ? $array[$key] : '';
}

/**
 * The ceremony timeout in milliseconds: the setting between one minute and
 * ten, or PG_WEBAUTHN_TIMEOUT.
 *
 * @param array $settings
 * @return int
 */
function pg_webauthn_timeout($settings)
{
    $timeout = (is_array($settings) && isset($settings['timeout']) && is_numeric($settings['timeout']))
        ? (int) $settings['timeout'] : (int) PG_WEBAUTHN_TIMEOUT;

    return max(60000, min(600000, $timeout));
}

// ---------------------------------------------------------------------------
// Responses from the browser
// ---------------------------------------------------------------------------

/**
 * A registration response as PublicKeyCredential.toJSON() writes it
 * (RegistrationResponseJSON), with its binary values decoded.
 *
 * @param string|array $input the posted JSON, or it decoded
 * @return array ok, and on success credential_id, client_data_json,
 *               attestation_object, transports, attachment,
 *               client_extensions; on failure error 'response'
 */
function pg_webauthn_registration_response($input)
{
    $data = pg_webauthn_response_input($input);

    if ($data === false) {
        return array('ok' => false, 'error' => 'response');
    }

    $response = $data['response'];
    $client_data_json = isset($response['clientDataJSON']) ? pg_webauthn_base64url_decode($response['clientDataJSON']) : false;
    $attestation_object = isset($response['attestationObject']) ? pg_webauthn_base64url_decode($response['attestationObject']) : false;

    if (($client_data_json === false) || ($client_data_json === '') || ($attestation_object === false) || ($attestation_object === '')) {
        return array('ok' => false, 'error' => 'response');
    }

    return array(
        'ok'                 => true,
        'credential_id'      => $data['credential_id'],
        'client_data_json'   => $client_data_json,
        'attestation_object' => $attestation_object,
        'transports'         => pg_webauthn_transports(isset($response['transports']) ? $response['transports'] : array()),
        'attachment'         => $data['attachment'],
        'client_extensions'  => $data['client_extensions'],
    );
}

/**
 * An authentication response as PublicKeyCredential.toJSON() writes it
 * (AuthenticationResponseJSON), with its binary values decoded.
 *
 * @param string|array $input
 * @return array ok, and on success credential_id, client_data_json,
 *               authenticator_data, signature, user_handle ('' when the
 *               authenticator gave none), attachment, client_extensions; on
 *               failure error 'response'
 */
function pg_webauthn_assertion_response($input)
{
    $data = pg_webauthn_response_input($input);

    if ($data === false) {
        return array('ok' => false, 'error' => 'response');
    }

    $response = $data['response'];
    $client_data_json = isset($response['clientDataJSON']) ? pg_webauthn_base64url_decode($response['clientDataJSON']) : false;
    $authenticator_data = isset($response['authenticatorData']) ? pg_webauthn_base64url_decode($response['authenticatorData']) : false;
    $signature = isset($response['signature']) ? pg_webauthn_base64url_decode($response['signature']) : false;

    // The user handle is absent, null or empty when the authenticator has
    // none to give (a credential that is not discoverable).
    $user_handle = '';

    if (isset($response['userHandle']) && ($response['userHandle'] !== '')) {
        $user_handle = pg_webauthn_base64url_decode($response['userHandle']);
    }

    if (($client_data_json === false) || ($client_data_json === '') || ($authenticator_data === false) || ($authenticator_data === '')
        || ($signature === false) || ($signature === '') || ($user_handle === false) || (strlen($user_handle) > 64)) {
        return array('ok' => false, 'error' => 'response');
    }

    return array(
        'ok'                 => true,
        'credential_id'      => $data['credential_id'],
        'client_data_json'   => $client_data_json,
        'authenticator_data' => $authenticator_data,
        'signature'          => $signature,
        'user_handle'        => $user_handle,
        'attachment'         => $data['attachment'],
        'client_extensions'  => $data['client_extensions'],
    );
}

/**
 * What both response forms share: type "public-key", an id that is the
 * base64url of rawId (1-1023 bytes), a response object, the attachment and
 * the client extension results.
 *
 * @param string|array $input
 * @return array|false response, credential_id, attachment, client_extensions
 */
function pg_webauthn_response_input($input)
{
    if (is_string($input)) {
        if (strlen($input) > PG_WEBAUTHN_MAX_RESPONSE_BYTES) {
            return false;
        }

        $input = json_decode($input, true, 32);
    }

    if (!is_array($input) || !isset($input['type'], $input['id'], $input['rawId'], $input['response'])
        || ($input['type'] !== 'public-key') || !is_string($input['id']) || !is_string($input['rawId'])
        || !is_array($input['response'])) {
        return false;
    }

    $credential_id = pg_webauthn_base64url_decode($input['rawId']);

    if (($credential_id === false) || ($credential_id === '') || (strlen($credential_id) > 1023)
        || (pg_webauthn_base64url_decode($input['id']) !== $credential_id)) {
        return false;
    }

    $attachment = '';

    if (isset($input['authenticatorAttachment']) && in_array($input['authenticatorAttachment'], array('platform', 'cross-platform'), true)) {
        $attachment = $input['authenticatorAttachment'];
    }

    return array(
        'response'          => $input['response'],
        'credential_id'     => $credential_id,
        'attachment'        => $attachment,
        'client_extensions' => (isset($input['clientExtensionResults']) && is_array($input['clientExtensionResults']))
            ? $input['clientExtensionResults'] : array(),
    );
}

// ---------------------------------------------------------------------------
// Registration (WebAuthn 7.1)
// ---------------------------------------------------------------------------

/**
 * Checks a new credential.
 *
 * @param array $response client_data_json, attestation_object and
 *                        credential_id (rawId), raw bytes: the output of
 *                        pg_webauthn_registration_response() fits
 * @param array $expected challenge (bytes kept for this ceremony), origins
 *                        (list; or origin), rp_id, user_verification
 *                        ('preferred' or 'discouraged'; anything else set
 *                        makes the UV flag compulsory), algorithms (defaults
 *                        to pg_webauthn_algorithms()), mediation
 *                        ('conditional' for an automatic passkey upgrade,
 *                        where the user presence flag may be unset)
 * @return array ok; on success credential: id, public_key (PEM), alg,
 *               sign_count, aaguid (16 bytes), user_verified,
 *               backup_eligible, backup_state, attestation ('none', 'self'
 *               or 'basic', never trusted), format; on failure error
 */
function pg_webauthn_verify_registration($response, $expected)
{
    try {
        return array('ok' => true, 'credential' => pg_webauthn_registration_steps($response, $expected));
    } catch (PgWebAuthnException $exception) {
        return array('ok' => false, 'error' => $exception->getMessage());
    }
}

/**
 * The steps of pg_webauthn_verify_registration(), in the specification's
 * order, throwing at the first that fails.
 *
 * @param array $response
 * @param array $expected
 * @return array the credential
 * @throws PgWebAuthnException
 */
function pg_webauthn_registration_steps($response, $expected)
{
    $expected = pg_webauthn_expected($expected);

    $client_data_json = pg_webauthn_string($response, 'client_data_json');
    $attestation_object = pg_webauthn_string($response, 'attestation_object');

    // Steps 5-12: the client data.
    pg_webauthn_client_data($client_data_json, 'webauthn.create', $expected);
    $client_data_hash = hash('sha256', $client_data_json, true);

    // Step 13: the attestation object.
    if (($attestation_object === '') || (strlen($attestation_object) > PG_WEBAUTHN_MAX_OBJECT_BYTES)) {
        pg_webauthn_fail('attestation_object');
    }

    $attestation = pg_webauthn_cbor_decode($attestation_object, 'attestation_object');

    if (!is_array($attestation)
        || !isset($attestation['fmt'], $attestation['attStmt'], $attestation['authData'])
        || !is_string($attestation['fmt']) || !is_array($attestation['attStmt'])
        || !($attestation['authData'] instanceof PgWebAuthnBytes)) {
        pg_webauthn_fail('attestation_object');
    }

    $auth_data_bytes = $attestation['authData']->value;
    $auth = pg_webauthn_auth_data($auth_data_bytes);

    // Steps 14-17: relying party, presence, verification, backup flags.
    pg_webauthn_check_flags($auth, $expected, $expected['mediation'] !== 'conditional');

    // The credential the authenticator made is the one the response names
    // (rawId); the record is built from the authenticator data.
    $response_id = pg_webauthn_string($response, 'credential_id');

    if (!$auth['attested'] || ($response_id === '') || !hash_equals($auth['credential_id'], $response_id)) {
        pg_webauthn_fail('credential');
    }

    // Step 20: the credential public key, in an algorithm the options offered.
    $key = pg_webauthn_cose_key($auth['public_key']);

    if (!in_array($key['alg'], $expected['algorithms'], true)) {
        pg_webauthn_fail('algorithm');
    }

    // Steps 21-24: the attestation statement.
    if ($attestation['fmt'] === 'none') {

        if (count($attestation['attStmt']) !== 0) {
            pg_webauthn_fail('attestation_statement');
        }

        $type = 'none';

    } elseif ($attestation['fmt'] === 'packed') {

        $type = pg_webauthn_packed($attestation['attStmt'], $auth, $auth_data_bytes . $client_data_hash, $key);

    } else {

        pg_webauthn_fail('attestation_format');

    }

    // Step 25 (credential ID length) is checked while reading the
    // authenticator data; step 26 (not yet registered) is the caller's.
    return array(
        'id'              => $auth['credential_id'],
        'public_key'      => $key['pem'],
        'alg'             => $key['alg'],
        'sign_count'      => $auth['sign_count'],
        'aaguid'          => $auth['aaguid'],
        'user_verified'   => $auth['user_verified'],
        'backup_eligible' => $auth['backup_eligible'],
        'backup_state'    => $auth['backup_state'],
        'attestation'     => $type,
        'format'          => $attestation['fmt'],
    );
}

/**
 * The "packed" attestation statement (WebAuthn 8.2): with x5c, signed by the
 * key of its first certificate, which meets the format's certificate rules;
 * without, signed by the credential's own key with the credential's
 * algorithm (self attestation).
 *
 * @param array  $statement attStmt
 * @param array  $auth      from pg_webauthn_auth_data()
 * @param string $signed    authenticator data followed by the client data hash
 * @param array  $key       the credential key from pg_webauthn_cose_key()
 * @return string 'self' or 'basic'
 * @throws PgWebAuthnException
 */
function pg_webauthn_packed($statement, $auth, $signed, $key)
{
    if (!isset($statement['alg'], $statement['sig']) || !is_int($statement['alg'])
        || !($statement['sig'] instanceof PgWebAuthnBytes)
        || (count(array_diff(array_keys($statement), array('alg', 'sig', 'x5c'))) > 0)) {
        pg_webauthn_fail('attestation_statement');
    }

    if (!array_key_exists('x5c', $statement)) {

        if ($statement['alg'] !== $key['alg']) {
            pg_webauthn_fail('attestation_statement');
        }

        $public_key = pg_webauthn_public_key($key['pem'], $key['alg']);

        if (($public_key === false) || !pg_webauthn_signature_ok($public_key, $statement['alg'], $signed, $statement['sig']->value)) {
            pg_webauthn_fail('signature');
        }

        return 'self';

    }

    // x5c is a CBOR array of certificates, attestnCert first.
    if (!($statement['x5c'] instanceof PgWebAuthnList)) {
        pg_webauthn_fail('attestation_statement');
    }

    $chain = $statement['x5c']->items;

    if ((count($chain) === 0) || (count($chain) > 8)) {
        pg_webauthn_fail('attestation_statement');
    }

    foreach ($chain as $certificate) {
        if (!($certificate instanceof PgWebAuthnBytes) || ($certificate->value === '')) {
            pg_webauthn_fail('attestation_statement');
        }
    }

    // The attestation key signs with its own algorithm, which only has to be
    // one this verifier can check.
    if (!in_array($statement['alg'], pg_webauthn_algorithms(), true)) {
        pg_webauthn_fail('attestation_statement');
    }

    $public_key = pg_webauthn_public_key(pg_webauthn_pem('CERTIFICATE', $chain[0]->value), $statement['alg']);

    if ($public_key === false) {
        pg_webauthn_fail('attestation_statement');
    }

    if (!pg_webauthn_signature_ok($public_key, $statement['alg'], $signed, $statement['sig']->value)) {
        pg_webauthn_fail('signature');
    }

    if (!pg_webauthn_packed_certificate_ok($chain[0]->value, $auth['aaguid'])) {
        pg_webauthn_fail('attestation_statement');
    }

    return 'basic';
}

// ---------------------------------------------------------------------------
// Authentication (WebAuthn 7.2)
// ---------------------------------------------------------------------------

/**
 * Checks a sign-in with a stored credential.
 *
 * @param array $response   credential_id (rawId), client_data_json,
 *                          authenticator_data, signature and user_handle (''
 *                          when the authenticator gave none), raw bytes: the
 *                          output of pg_webauthn_assertion_response() fits
 * @param array $expected   challenge, origins (or origin), rp_id,
 *                          user_verification (as for registration),
 *                          discoverable (true when the account was not known
 *                          before the ceremony: the response must then carry
 *                          the user handle)
 * @param array $credential the record the caller found by the response's
 *                          credential ID: id, public_key (PEM), alg,
 *                          sign_count, backup_eligible (null when not
 *                          recorded; database values '0' and '1' are read as
 *                          false and true), user_handle (the handle of the
 *                          account that owns the record, never the one in the
 *                          response)
 * @return array ok; on success sign_count (to store), user_verified,
 *               backup_eligible, backup_state; on failure error
 */
function pg_webauthn_verify_assertion($response, $expected, $credential)
{
    try {
        return pg_webauthn_assertion_steps($response, $expected, $credential);
    } catch (PgWebAuthnException $exception) {
        return array('ok' => false, 'error' => $exception->getMessage());
    }
}

/**
 * The steps of pg_webauthn_verify_assertion(), throwing at the first that
 * fails.
 *
 * @param array $response
 * @param array $expected
 * @param array $credential
 * @return array
 * @throws PgWebAuthnException
 */
function pg_webauthn_assertion_steps($response, $expected, $credential)
{
    $expected = pg_webauthn_expected($expected);

    // What the site stored, read before anything is compared with it: a
    // record without its ID, algorithm or counter cannot be checked against.
    $record_id = pg_webauthn_string($credential, 'id');
    $alg = pg_webauthn_stored_integer($credential, 'alg');
    $stored_count = pg_webauthn_stored_integer($credential, 'sign_count');
    $stored_eligible = pg_webauthn_stored_flag($credential, 'backup_eligible');

    if (($record_id === '') || ($stored_count === null) || ($stored_count < 0)) {
        pg_webauthn_fail('credential');
    }

    if (($alg === null) || !in_array($alg, pg_webauthn_algorithms(), true)) {
        pg_webauthn_fail('algorithm');
    }

    // Steps 5-6: the response names this credential, and a user handle in the
    // response is this account's. With no account known beforehand the
    // handle is what tied the response to the account, so it must be there.
    $response_id = pg_webauthn_string($response, 'credential_id');

    if (($response_id === '') || !hash_equals($record_id, $response_id)) {
        pg_webauthn_fail('credential');
    }

    $response_handle = pg_webauthn_string($response, 'user_handle');
    $record_handle = pg_webauthn_string($credential, 'user_handle');

    if ($expected['discoverable'] && ($response_handle === '')) {
        pg_webauthn_fail('user_handle');
    }

    if (($response_handle !== '') && (($record_handle === '') || !hash_equals($record_handle, $response_handle))) {
        pg_webauthn_fail('user_handle');
    }

    // Steps 7-14: the client data.
    $client_data_json = pg_webauthn_string($response, 'client_data_json');
    pg_webauthn_client_data($client_data_json, 'webauthn.get', $expected);

    // Steps 15-18: relying party, presence, verification, backup flags. An
    // assertion carries no attested credential data (WebAuthn 6.1).
    $authenticator_data = pg_webauthn_string($response, 'authenticator_data');
    $auth = pg_webauthn_auth_data($authenticator_data);

    if ($auth['attested']) {
        pg_webauthn_fail('auth_data');
    }

    pg_webauthn_check_flags($auth, $expected, true);

    // Step 19: backup eligibility is fixed for a credential's lifetime.
    if (($stored_eligible !== null) && ($stored_eligible !== $auth['backup_eligible'])) {
        pg_webauthn_fail('backup_flags');
    }

    // Steps 20-21: the signature over the authenticator data and the hash of
    // the client data, with the stored key.
    $public_key = pg_webauthn_public_key(pg_webauthn_string($credential, 'public_key'), $alg);

    if ($public_key === false) {
        pg_webauthn_fail('public_key');
    }

    $signature = pg_webauthn_string($response, 'signature');

    if (!pg_webauthn_signature_ok($public_key, $alg, $authenticator_data . hash('sha256', $client_data_json, true), $signature)) {
        pg_webauthn_fail('signature');
    }

    // Step 22: the signature counter moves forward unless both are zero (an
    // authenticator that keeps no counter, as synced passkeys do).
    if ((($auth['sign_count'] !== 0) || ($stored_count !== 0)) && ($auth['sign_count'] <= $stored_count)) {
        pg_webauthn_fail('sign_count');
    }

    return array(
        'ok'              => true,
        'sign_count'      => $auth['sign_count'],
        'user_verified'   => $auth['user_verified'],
        'backup_eligible' => $auth['backup_eligible'],
        'backup_state'    => $auth['backup_state'],
    );
}

// ---------------------------------------------------------------------------
// Shared steps
// ---------------------------------------------------------------------------

/**
 * The expectations of a ceremony, checked and given their defaults.
 *
 * @param mixed $expected
 * @return array challenge, origins, rp_id, user_verification, algorithms,
 *               mediation, discoverable
 * @throws PgWebAuthnException 'challenge', 'origin', 'rp_id' or 'algorithm'
 *         when the caller has nothing to compare against
 */
function pg_webauthn_expected($expected)
{
    $expected = is_array($expected) ? $expected : array();

    // A challenge shorter than 16 bytes is not one this site made; an empty
    // one is what a lost or expired session leaves.
    $challenge = (isset($expected['challenge']) && is_string($expected['challenge'])) ? $expected['challenge'] : '';

    if (strlen($challenge) < 16) {
        pg_webauthn_fail('challenge');
    }

    $origins = array();

    if (isset($expected['origins'])) {
        $origins = (array) $expected['origins'];
    } elseif (isset($expected['origin'])) {
        $origins = array($expected['origin']);
    }

    $origins = array_values(array_filter($origins, function ($origin) {
        return is_string($origin) && ($origin !== '');
    }));

    if (count($origins) === 0) {
        pg_webauthn_fail('origin');
    }

    $rp_id = (isset($expected['rp_id']) && is_string($expected['rp_id'])) ? $expected['rp_id'] : '';

    if ($rp_id === '') {
        pg_webauthn_fail('rp_id');
    }

    $algorithms = pg_webauthn_algorithms();

    if (isset($expected['algorithms'])) {
        $algorithms = array_values(array_filter($algorithms, function ($alg) use ($expected) {
            return in_array($alg, (array) $expected['algorithms'], true);
        }));
    }

    if (count($algorithms) === 0) {
        pg_webauthn_fail('algorithm');
    }

    // Only the two values that relax it do: a misspelt "Required" or a true
    // must not turn the requirement off.
    $user_verification = 'preferred';

    if (isset($expected['user_verification'])) {
        $user_verification = in_array($expected['user_verification'], array('preferred', 'discouraged'), true)
            ? $expected['user_verification'] : 'required';
    }

    return array(
        'challenge'         => $challenge,
        'origins'           => $origins,
        'rp_id'             => $rp_id,
        'user_verification' => $user_verification,
        'algorithms'        => $algorithms,
        'mediation'         => pg_webauthn_setting($expected, 'mediation', array('conditional'), ''),
        'discoverable'      => !empty($expected['discoverable']),
    );
}

/**
 * An integer the site stored ('-7' from a text column, 4 from an integer
 * one), or null when the value is missing, not a whole number in its plain
 * decimal form, or too large for this PHP's integers (a counter over 2^31 on
 * 32-bit PHP would otherwise be capped and compare as smaller than it is).
 *
 * @param mixed  $record
 * @param string $key
 * @return int|null
 */
function pg_webauthn_stored_integer($record, $key)
{
    if (!is_array($record) || !isset($record[$key])) {
        return null;
    }

    $value = $record[$key];

    if (is_int($value)) {
        return $value;
    }

    if (is_string($value) && (preg_match('/^(?:0|-?[1-9][0-9]*)$/', $value) === 1) && ((string) (int) $value === $value)) {
        return (int) $value;
    }

    return null;
}

/**
 * A yes/no value the site stored: true for true, 1 and '1'; false for false,
 * 0 and '0'; null when it was not recorded (missing or null). Anything else
 * cannot be told apart from damage and stops the check.
 *
 * @param mixed  $record
 * @param string $key
 * @return bool|null
 * @throws PgWebAuthnException 'backup_flags'
 */
function pg_webauthn_stored_flag($record, $key)
{
    if (!is_array($record) || !array_key_exists($key, $record) || ($record[$key] === null)) {
        return null;
    }

    $value = $record[$key];

    if (($value === true) || ($value === 1) || ($value === '1')) {
        return true;
    }

    if (($value === false) || ($value === 0) || ($value === '0')) {
        return false;
    }

    pg_webauthn_fail('backup_flags');
}

/**
 * The client data checks shared by both ceremonies: a JSON object of at most
 * PG_WEBAUTHN_MAX_CLIENT_DATA_BYTES (a leading byte order mark dropped) whose
 * type, challenge (the base64url of the kept challenge, compared as text)
 * and origin (exact match) are the expected ones, and which was not made in
 * a cross-origin frame.
 *
 * @param string $json
 * @param string $type 'webauthn.create' or 'webauthn.get'
 * @param array  $expected from pg_webauthn_expected()
 * @return array the client data
 * @throws PgWebAuthnException
 */
function pg_webauthn_client_data($json, $type, $expected)
{
    $json = (string) $json;

    if (($json === '') || (strlen($json) > PG_WEBAUTHN_MAX_CLIENT_DATA_BYTES)) {
        pg_webauthn_fail('client_data');
    }

    if (substr($json, 0, 3) === "\xEF\xBB\xBF") {
        $json = substr($json, 3);
    }

    $data = json_decode($json, true, 16);

    if (!is_array($data) || !isset($data['type'], $data['challenge'], $data['origin'])
        || !is_string($data['type']) || !is_string($data['challenge']) || !is_string($data['origin'])) {
        pg_webauthn_fail('client_data');
    }

    if ($data['type'] !== $type) {
        pg_webauthn_fail('type');
    }

    if (!hash_equals(pg_webauthn_base64url_encode($expected['challenge']), $data['challenge'])) {
        pg_webauthn_fail('challenge');
    }

    if (!in_array($data['origin'], $expected['origins'], true)) {
        pg_webauthn_fail('origin');
    }

    if ((array_key_exists('crossOrigin', $data) && ($data['crossOrigin'] !== false)) || array_key_exists('topOrigin', $data)) {
        pg_webauthn_fail('cross_origin');
    }

    return $data;
}

/**
 * Authenticator data (WebAuthn 6.1): the relying party ID hash, flags,
 * signature counter, and the attested credential (AT flag) and extensions
 * (ED flag) when they are there. Nothing may follow them.
 *
 * @param string $bytes
 * @return array rp_id_hash, flags, user_present, user_verified,
 *               backup_eligible, backup_state, attested, sign_count, aaguid,
 *               credential_id, public_key (decoded COSE map or null),
 *               extensions (decoded map or null)
 * @throws PgWebAuthnException 'auth_data' or 'credential'
 */
function pg_webauthn_auth_data($bytes)
{
    $bytes = (string) $bytes;
    $length = strlen($bytes);

    if (($length < 37) || ($length > PG_WEBAUTHN_MAX_AUTH_DATA_BYTES)) {
        pg_webauthn_fail('auth_data');
    }

    $flags = ord($bytes[32]);

    $auth = array(
        'rp_id_hash'      => substr($bytes, 0, 32),
        'flags'           => $flags,
        'user_present'    => ($flags & 0x01) !== 0,
        'user_verified'   => ($flags & 0x04) !== 0,
        'backup_eligible' => ($flags & 0x08) !== 0,
        'backup_state'    => ($flags & 0x10) !== 0,
        'attested'        => ($flags & 0x40) !== 0,
        'sign_count'      => pg_webauthn_uint32(substr($bytes, 33, 4)),
        'aaguid'          => '',
        'credential_id'   => '',
        'public_key'      => null,
        'extensions'      => null,
    );

    $offset = 37;

    if ($auth['attested']) {

        if ($length < 55) {
            pg_webauthn_fail('auth_data');
        }

        $auth['aaguid'] = substr($bytes, 37, 16);
        $id_length = unpack('n', substr($bytes, 53, 2));
        $id_length = $id_length[1];

        if (($id_length < 1) || ($id_length > 1023)) {
            pg_webauthn_fail('credential');
        }

        $offset = 55;

        if (($length - $offset) < $id_length) {
            pg_webauthn_fail('auth_data');
        }

        $auth['credential_id'] = substr($bytes, $offset, $id_length);
        $offset += $id_length;

        $auth['public_key'] = pg_webauthn_cbor_read($bytes, $offset, 'auth_data');

        if (!is_array($auth['public_key'])) {
            pg_webauthn_fail('auth_data');
        }

    }

    if (($flags & 0x80) !== 0) {

        $auth['extensions'] = pg_webauthn_cbor_read($bytes, $offset, 'auth_data');

        if (!is_array($auth['extensions'])) {
            pg_webauthn_fail('auth_data');
        }

    }

    if ($offset !== $length) {
        pg_webauthn_fail('auth_data');
    }

    return $auth;
}

/**
 * The flag checks shared by both ceremonies: the relying party ID hash, user
 * presence (unless a conditional registration made it optional), user
 * verification when required, and no backup state without backup
 * eligibility.
 *
 * @param array $auth
 * @param array $expected
 * @param bool  $presence_required
 * @throws PgWebAuthnException
 */
function pg_webauthn_check_flags($auth, $expected, $presence_required)
{
    if (!hash_equals(hash('sha256', $expected['rp_id'], true), $auth['rp_id_hash'])) {
        pg_webauthn_fail('rp_id');
    }

    if ($presence_required && !$auth['user_present']) {
        pg_webauthn_fail('user_present');
    }

    if (($expected['user_verification'] === 'required') && !$auth['user_verified']) {
        pg_webauthn_fail('user_verified');
    }

    if (!$auth['backup_eligible'] && $auth['backup_state']) {
        pg_webauthn_fail('backup_flags');
    }
}
