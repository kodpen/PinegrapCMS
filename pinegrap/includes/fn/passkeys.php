<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Passkeys (WebAuthn) of accounts: the user_passkeys table (2026.11.0,
 * 11.0.13), the two ceremonies around the verifier in includes/webauthn/,
 * and the challenge each ceremony keeps in the session.
 *
 *   Registration  a signed-in account asks for creation options
 *                 (pg_passkey_registration_begin()); the browser makes the
 *                 credential and pg_passkey_register() checks and keeps it.
 *   Sign-in       request options (pg_passkey_signin_begin()) for an account,
 *                 whose passkeys are the browser's allow list, or for none,
 *                 when the browser offers whatever passkey it holds for the
 *                 site; pg_passkey_signin_check() checks the answer against
 *                 the stored passkey and moves its counter forward.
 *
 * The site is the relying party under its configured scheme and host
 * (URL_SCHEME, HOSTNAME_SETTING), never the Host header of a request. A
 * challenge is used once: it is taken out of the session before the answer
 * is read, whatever the outcome.
 *
 * Every ceremony answers array('ok' => true, ...) or array('ok' => false,
 * 'error' => <code>): the verifier's codes (webauthn.php) and these:
 *
 *   unavailable  no table yet, no OpenSSL with EC keys, or a site address a
 *                browser makes no passkey for (an IP address, plain http
 *                other than localhost)
 *   host         the page was opened at another name of the site
 *   challenge    no ceremony waiting: never begun, for another account,
 *                answered already, or older than its lifetime
 *   none         the account has no passkey to sign in with
 *   limit        the account keeps PG_PASSKEYS_MAX passkeys already
 *   duplicate    the credential is registered already
 *   storage      the row could not be written
 *
 * The codes are for the log and the caller's logic; a visitor is shown one
 * plain sentence (pg_passkey_refusal()), so a stranger cannot learn which
 * credential belongs to which account.
 *
 * Endpoints: passkey.php (options and registration, JSON); mfa.php takes a
 * passkey at the second step of a sign-in; account_security.php renames and
 * removes one. The account page lists them with pg_passkey_account_section().
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// How many passkeys one account may keep: a phone, a laptop, two security
// keys and a password manager are five; the cap only keeps a script from
// filling the table.
if (!defined('PG_PASSKEYS_MAX')) {
    define('PG_PASSKEYS_MAX', 20);
}

// The longest name a passkey is given, in characters (the column's size).
if (!defined('PG_PASSKEY_NAME_MAX')) {
    define('PG_PASSKEY_NAME_MAX', 100);
}

// ── The site as a relying party ─────────────────────────────────────────────

/**
 * Loads the verifier and says whether this PHP can check passkeys with it.
 *
 * @return bool
 */
function pg_passkey_verifier()
{
    static $supported = null;

    if ($supported === null) {
        require_once(PG_FUNCTIONS_DIR . '/includes/webauthn/webauthn.php');
        $supported = pg_webauthn_supported();
    }

    return $supported;
}

/**
 * Whether the user_passkeys table exists. Probed by exact name in
 * information_schema (SHOW TABLES LIKE reads '_' as a wildcard) and cached
 * for the request. Between new files landing and the upgrade running this is
 * false, and passkeys are simply not offered.
 *
 * @return bool
 */
function pg_passkeys_table_exists()
{
    static $exists = null;

    if ($exists !== null) {
        return $exists;
    }

    if (!isset(db::$con) || !db::$con) {
        return false;
    }

    $exists = ((int) db_value("SELECT COUNT(*) FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'user_passkeys'") === 1);

    return $exists;
}

/**
 * The relying party for a scheme, host and site title: id (the host alone,
 * pg_webauthn_rp_id()), name (the title, or the host), origin (as the
 * browser writes it into clientDataJSON). null where a browser makes no
 * passkey: an IP address, a malformed host, plain http on a host other than
 * localhost.
 *
 * @param string $scheme 'https://' or 'http://' (URL_SCHEME)
 * @param string $host   the host, with a port when it has one
 * @param string $title  the site's title
 * @return array|null
 */
function pg_passkey_relying_party_for($scheme, $host, $title)
{
    if (!pg_passkey_verifier() || !pg_webauthn_secure_context($scheme, $host)) {
        return null;
    }

    $id = pg_webauthn_rp_id($host);
    $origin = pg_webauthn_origin($scheme, $host);

    if (($id === '') || ($origin === '')) {
        return null;
    }

    // Browsers show the name beside the account in their passkey list and may
    // cut it; one line of at most 64 characters.
    $name = trim((string) preg_replace('/\s+/u', ' ', (string) $title));
    $name = ($name !== '') ? mb_substr($name, 0, 64) : $id;

    return array('id' => $id, 'name' => $name, 'origin' => $origin);
}

/**
 * This site as the relying party, from its settings.
 *
 * @return array|null id, name, origin; null when passkeys cannot work here
 */
function pg_passkey_relying_party()
{
    if (!defined('URL_SCHEME') || !defined('HOSTNAME_SETTING')) {
        return null;
    }

    return pg_passkey_relying_party_for(URL_SCHEME, HOSTNAME_SETTING, defined('TITLE') ? (string) TITLE : '');
}

/**
 * Whether passkeys can be used on this site: the table exists, PHP can check
 * them and the site's address is one a browser makes passkeys for.
 *
 * @return bool
 */
function pg_passkeys_available()
{
    return pg_passkeys_table_exists() && (pg_passkey_relying_party() !== null);
}

/**
 * The relying party a ceremony of this request runs under, or the reason it
 * cannot run: 'unavailable', or 'host' when the page was opened at another
 * name of the site. A browser makes and offers passkeys only for the host of
 * the page it is on, so on an alias the ceremony would fail in the browser
 * with nothing to tell the visitor why. The Host header only chooses that
 * message; what is checked is the origin the browser signs.
 *
 * @return array ok, rp; or ok false and error
 */
function pg_passkey_ready()
{
    $rp = pg_passkeys_table_exists() ? pg_passkey_relying_party() : null;

    if ($rp === null) {
        return array('ok' => false, 'error' => 'unavailable');
    }

    if (defined('HOSTNAME') && (pg_webauthn_origin(URL_SCHEME, (string) HOSTNAME) !== $rp['origin'])) {
        return array('ok' => false, 'error' => 'host');
    }

    return array('ok' => true, 'rp' => $rp);
}

// ── Ceremonies kept in the session ──────────────────────────────────────────

/**
 * How long a ceremony waits for its answer, in seconds: the browser's own
 * timeout (PG_WEBAUTHN_TIMEOUT, five minutes) and a minute for the answer to
 * arrive.
 *
 * @return int
 */
function pg_passkey_ceremony_lifetime()
{
    return 360;
}

/**
 * Starts a ceremony: its challenge is kept for the account it is for, one
 * ceremony per purpose, a newer one replacing the older (the options asked
 * for again, another tab). The challenge is kept as base64url so a session
 * handler that stores text never meets raw bytes.
 *
 * @param mixed  $store     the session's ceremonies, by reference
 * @param string $purpose   'register' or 'signin'
 * @param int    $user_id   the account; 0 for a sign-in that does not know it
 * @param string $challenge raw bytes (pg_webauthn_challenge())
 * @param int    $now
 * @param array  $extra     other values to keep with it (the user handle a
 *                          registration offered)
 */
function pg_passkey_ceremony_begin(&$store, $purpose, $user_id, $challenge, $now, $extra = array())
{
    if (!is_array($store)) {
        $store = array();
    }

    $store[$purpose] = array_merge($extra, array(
        'user_id'   => (int) $user_id,
        'challenge' => pg_webauthn_base64url_encode($challenge),
        'time'      => (int) $now,
    ));
}

/**
 * Takes a ceremony out of the store. It is removed whatever follows, so an
 * answer, right or wrong, uses its challenge up. null when there is none for
 * the purpose, it was begun for another account, it is older than
 * pg_passkey_ceremony_lifetime() or its challenge does not read back.
 *
 * @param mixed  $store   by reference
 * @param string $purpose
 * @param int    $user_id the account the answer is for (0: not known)
 * @param int    $now
 * @return array|null the kept values, challenge as raw bytes
 */
function pg_passkey_ceremony_take(&$store, $purpose, $user_id, $now)
{
    if (!is_array($store) || !isset($store[$purpose])) {
        return null;
    }

    $ceremony = $store[$purpose];
    unset($store[$purpose]);

    if (!is_array($ceremony) || !isset($ceremony['user_id'], $ceremony['challenge'], $ceremony['time'])
        || ((int) $ceremony['user_id'] !== (int) $user_id)
        || (((int) $now - (int) $ceremony['time']) > pg_passkey_ceremony_lifetime())
        || ((int) $ceremony['time'] > (int) $now)) {
        return null;
    }

    $challenge = is_string($ceremony['challenge']) ? pg_webauthn_base64url_decode($ceremony['challenge']) : false;

    if (!is_string($challenge) || (strlen($challenge) < 16)) {
        return null;
    }

    $ceremony['challenge'] = $challenge;

    return $ceremony;
}

// ── Stored passkeys ─────────────────────────────────────────────────────────

/**
 * A name as it is kept: control characters dropped, white space collapsed,
 * at most PG_PASSKEY_NAME_MAX characters of valid UTF-8; '' when nothing is
 * left (the caller gives the default then).
 *
 * @param mixed $name
 * @return string
 */
function pg_passkey_name($name)
{
    if (!is_string($name) || !mb_check_encoding($name, 'UTF-8')) {
        return '';
    }

    $name = (string) preg_replace('/[\x00-\x1f\x7f\x{80}-\x{9f}\x{200b}-\x{200f}\x{2028}-\x{202e}\x{2066}-\x{2069}\x{feff}]/u', ' ', $name);
    $name = trim((string) preg_replace('/\s+/u', ' ', $name));

    return mb_substr($name, 0, PG_PASSKEY_NAME_MAX);
}

/**
 * The transports column read back as a list of the values the specification
 * defines.
 *
 * @param mixed $text
 * @return string[]
 */
function pg_passkey_transports_list($text)
{
    return pg_webauthn_transports(explode(',', (string) $text));
}

/**
 * The row a verified registration is kept as (column => value).
 *
 * @param array  $credential the credential of pg_webauthn_verify_registration()
 * @param array  $transports what the browser reported (getTransports())
 * @param int    $user_id
 * @param string $handle     the user handle the ceremony offered, raw bytes
 * @param string $name       already cleaned (pg_passkey_name())
 * @param int    $now
 * @return array
 */
function pg_passkey_row_values($credential, $transports, $user_id, $handle, $name, $now)
{
    return array(
        'user_id'         => (int) $user_id,
        'credential_hash' => hash('sha256', (string) $credential['id']),
        'credential_id'   => pg_webauthn_base64url_encode((string) $credential['id']),
        'public_key'      => (string) $credential['public_key'],
        'alg'             => (int) $credential['alg'],
        'sign_count'      => (int) $credential['sign_count'],
        'transports'      => implode(',', pg_webauthn_transports($transports)),
        'aaguid'          => pg_webauthn_aaguid_text(isset($credential['aaguid']) ? $credential['aaguid'] : ''),
        'backup_eligible' => !empty($credential['backup_eligible']) ? 1 : 0,
        'backup_state'    => !empty($credential['backup_state']) ? 1 : 0,
        'user_handle'     => pg_webauthn_base64url_encode((string) $handle),
        'name'            => (string) $name,
        'created_at'      => (int) $now,
        'last_used_at'    => 0,
    );
}

/**
 * A stored row as the record pg_webauthn_verify_assertion() checks against:
 * binary values decoded, the counter, algorithm and backup eligibility passed
 * as the database gave them (the verifier reads '-7', '0' and '1' strictly,
 * and refuses what it cannot read).
 *
 * @param array $row
 * @return array id, public_key, alg, sign_count, backup_eligible, user_handle
 */
function pg_passkey_record($row)
{
    $row = is_array($row) ? $row : array();
    $id = isset($row['credential_id']) ? pg_webauthn_base64url_decode((string) $row['credential_id']) : false;
    $handle = isset($row['user_handle']) ? pg_webauthn_base64url_decode((string) $row['user_handle']) : false;

    return array(
        'id'              => is_string($id) ? $id : '',
        'public_key'      => isset($row['public_key']) ? (string) $row['public_key'] : '',
        'alg'             => isset($row['alg']) ? $row['alg'] : null,
        'sign_count'      => isset($row['sign_count']) ? $row['sign_count'] : null,
        'backup_eligible' => array_key_exists('backup_eligible', $row) ? $row['backup_eligible'] : null,
        'user_handle'     => is_string($handle) ? $handle : '',
    );
}

/**
 * An account's passkeys, oldest first: id, name, credential_id (raw bytes),
 * transports, aaguid, backup_eligible, backup_state, created_at,
 * last_used_at. Empty before the upgrade.
 *
 * @param int $user_id
 * @return array
 */
function pg_passkeys_of_user($user_id)
{
    if (!pg_passkeys_table_exists() || !pg_passkey_verifier()) {
        return array();
    }

    $passkeys = array();

    foreach ((array) db_items("SELECT id, name, credential_id, transports, aaguid, backup_eligible, backup_state, created_at, last_used_at
        FROM user_passkeys WHERE user_id = '" . (int) $user_id . "' ORDER BY id") as $row) {

        $credential_id = pg_webauthn_base64url_decode((string) $row['credential_id']);

        if (!is_string($credential_id) || ($credential_id === '')) {
            continue;
        }

        $passkeys[] = array(
            'id'              => (int) $row['id'],
            'name'            => (string) $row['name'],
            'credential_id'   => $credential_id,
            'transports'      => pg_passkey_transports_list($row['transports']),
            'aaguid'          => (string) $row['aaguid'],
            'backup_eligible' => ((int) $row['backup_eligible'] === 1),
            'backup_state'    => ((int) $row['backup_state'] === 1),
            'created_at'      => (int) $row['created_at'],
            'last_used_at'    => (int) $row['last_used_at'],
        );

    }

    return $passkeys;
}

/**
 * How many passkeys an account keeps.
 *
 * @param int $user_id
 * @return int
 */
function pg_passkey_count($user_id)
{
    if (!pg_passkeys_table_exists()) {
        return 0;
    }

    return (int) db_value("SELECT COUNT(*) FROM user_passkeys WHERE user_id = '" . (int) $user_id . "'");
}

/**
 * The credential descriptors of an account's passkeys, for the exclude list
 * of a registration or the allow list of a sign-in.
 *
 * @param array $passkeys pg_passkeys_of_user()
 * @return array list of id, transports
 */
function pg_passkey_descriptors($passkeys)
{
    $list = array();

    foreach ((array) $passkeys as $passkey) {
        $list[] = array('id' => $passkey['credential_id'], 'transports' => $passkey['transports']);
    }

    return $list;
}

/**
 * The user handle of an account, raw bytes: the one its passkeys carry, or 32
 * new random bytes for its first. Never personal data and never derived from
 * the account id, so a passkey list read off a device says nothing about the
 * account.
 *
 * @param int $user_id
 * @return string
 */
function pg_passkey_user_handle($user_id)
{
    $kept = pg_passkeys_table_exists()
        ? (string) db_value("SELECT user_handle FROM user_passkeys WHERE user_id = '" . (int) $user_id . "' ORDER BY id LIMIT 1")
        : '';

    $handle = ($kept !== '') ? pg_webauthn_base64url_decode($kept) : false;

    if (is_string($handle) && ($handle !== '') && (strlen($handle) <= 64)) {
        return $handle;
    }

    return random_bytes(32);
}

/**
 * Removes every passkey of an account: the account is being deleted. Silent
 * before the upgrade.
 *
 * @param int $user_id
 */
function pg_passkeys_forget($user_id)
{
    if (!pg_passkeys_table_exists() || ((int) $user_id <= 0)) {
        return;
    }

    db("DELETE FROM user_passkeys WHERE user_id = '" . (int) $user_id . "'");
}

/**
 * The name of one passkey of an account, or null when the account has no
 * passkey by that id (another account's included).
 *
 * @param int $user_id
 * @param int $passkey_id
 * @return string|null
 */
function pg_passkey_name_of($user_id, $passkey_id)
{
    if (!pg_passkeys_table_exists() || ((int) $user_id <= 0) || ((int) $passkey_id <= 0)) {
        return null;
    }

    $name = db_value("SELECT name FROM user_passkeys
        WHERE id = '" . (int) $passkey_id . "' AND user_id = '" . (int) $user_id . "'");

    return ($name !== null) ? (string) $name : null;
}

/**
 * Gives one passkey of an account a new name, cleaned as a registration
 * cleans it; nothing left gives "Passkey".
 *
 * @param int   $user_id
 * @param int   $passkey_id
 * @param mixed $name
 * @return string|null the name kept; null when the account has no passkey by
 *                     that id
 */
function pg_passkey_rename($user_id, $passkey_id, $name)
{
    if (pg_passkey_name_of($user_id, $passkey_id) === null) {
        return null;
    }

    $name = pg_passkey_name($name);
    $name = ($name !== '') ? $name : lang('Passkey');

    db("UPDATE user_passkeys SET name = '" . e($name) . "'
        WHERE id = '" . (int) $passkey_id . "' AND user_id = '" . (int) $user_id . "'");

    return $name;
}

/**
 * Removes one passkey of an account. The credential stays on the person's
 * device; this site no longer accepts it.
 *
 * @param int $user_id
 * @param int $passkey_id
 * @return string|null the removed passkey's name; null when the account has
 *                     no passkey by that id
 */
function pg_passkey_remove($user_id, $passkey_id)
{
    $name = pg_passkey_name_of($user_id, $passkey_id);

    if ($name === null) {
        return null;
    }

    db("DELETE FROM user_passkeys WHERE id = '" . (int) $passkey_id . "' AND user_id = '" . (int) $user_id . "'");

    return (mysqli_affected_rows(db::$con) === 1) ? $name : null;
}

// ── Registration ────────────────────────────────────────────────────────────

/**
 * The account a passkey is made for, as the browser shows it: name is what
 * the person signs in with (the e-mail address, which members sign in with,
 * or the username), display name the username.
 *
 * @param array $account username, email
 * @return array name, display_name
 */
function pg_passkey_user_entity($account)
{
    $username = isset($account['username']) ? trim((string) $account['username']) : '';
    $email = isset($account['email']) ? trim((string) $account['email']) : '';
    $name = ($email !== '') ? $email : $username;

    return array(
        'name'         => mb_substr($name, 0, 64),
        'display_name' => mb_substr(($username !== '') ? $username : $name, 0, 64),
    );
}

/**
 * Creation options for a new passkey of a signed-in account, with the
 * ceremony kept in the session for its answer. The account's passkeys are
 * excluded, so an authenticator that holds one is not registered twice.
 * Discoverable credentials are preferred, not required: an older security
 * key that cannot store one still works as a second step.
 *
 * @param array $account id, username, email
 * @return array ok, options (the publicKey JSON for the browser); or error
 */
function pg_passkey_registration_begin($account)
{
    $ready = pg_passkey_ready();

    if (!$ready['ok']) {
        return $ready;
    }

    $user_id = (int) $account['id'];

    if (pg_passkey_count($user_id) >= PG_PASSKEYS_MAX) {
        return array('ok' => false, 'error' => 'limit');
    }

    $handle = pg_passkey_user_handle($user_id);
    $challenge = pg_webauthn_challenge();
    $entity = pg_passkey_user_entity($account);

    $options = pg_webauthn_creation_options(
        array('id' => $ready['rp']['id'], 'name' => $ready['rp']['name']),
        array('handle' => $handle, 'name' => $entity['name'], 'display_name' => $entity['display_name']),
        $challenge,
        array(
            'exclude'           => pg_passkey_descriptors(pg_passkeys_of_user($user_id)),
            'resident_key'      => 'preferred',
            'user_verification' => 'preferred',
        ));

    if ($options === false) {
        return array('ok' => false, 'error' => 'unavailable');
    }

    pg_passkey_ceremony_begin($_SESSION['software']['passkey_ceremonies'], 'register', $user_id, $challenge, time(),
        array('handle' => pg_webauthn_base64url_encode($handle)));

    return array('ok' => true, 'options' => $options);
}

/**
 * The checks of a registration answer that need no database: the browser's
 * JSON read and verified against the kept ceremony and the relying party.
 *
 * @param mixed $input    the posted credential JSON
 * @param array $ceremony from pg_passkey_ceremony_take()
 * @param array $rp       id, origin
 * @return array ok, credential, transports; or error
 */
function pg_passkey_registration_check($input, $ceremony, $rp)
{
    $response = pg_webauthn_registration_response($input);

    if (!$response['ok']) {
        return $response;
    }

    $result = pg_webauthn_verify_registration($response, array(
        'challenge'         => $ceremony['challenge'],
        'origins'           => array($rp['origin']),
        'rp_id'             => $rp['id'],
        'user_verification' => 'preferred',
    ));

    if (!$result['ok']) {
        return $result;
    }

    return array('ok' => true, 'credential' => $result['credential'], 'transports' => $response['transports']);
}

/**
 * Checks the answer to pg_passkey_registration_begin() and keeps the passkey.
 *
 * @param array  $account id, username, email
 * @param mixed  $input   the posted credential JSON
 * @param string $name    what the person calls it; '' gives "Passkey"
 * @return array ok, passkey (id, name, created_at, backup_eligible,
 *               backup_state); or error
 */
function pg_passkey_register($account, $input, $name = '')
{
    $ready = pg_passkey_ready();

    if (!$ready['ok']) {
        return $ready;
    }

    $user_id = (int) $account['id'];
    $ceremony = pg_passkey_ceremony_take($_SESSION['software']['passkey_ceremonies'], 'register', $user_id, time());

    if ($ceremony === null) {
        return array('ok' => false, 'error' => 'challenge');
    }

    $handle = isset($ceremony['handle']) && is_string($ceremony['handle']) ? pg_webauthn_base64url_decode($ceremony['handle']) : false;

    if (!is_string($handle) || ($handle === '') || (strlen($handle) > 64)) {
        return array('ok' => false, 'error' => 'challenge');
    }

    $checked = pg_passkey_registration_check($input, $ceremony, $ready['rp']);

    if (!$checked['ok']) {
        return $checked;
    }

    if (pg_passkey_count($user_id) >= PG_PASSKEYS_MAX) {
        return array('ok' => false, 'error' => 'limit');
    }

    $name = pg_passkey_name($name);
    $row = pg_passkey_row_values($checked['credential'], $checked['transports'], $user_id, $handle,
        ($name !== '') ? $name : lang('Passkey'), time());

    // WebAuthn 7.1 step 26: a credential ID registered already is refused,
    // whichever account holds it. The unique key catches two answers racing.
    if (db_value("SELECT id FROM user_passkeys WHERE credential_hash = '" . e($row['credential_hash']) . "'") !== null) {
        return array('ok' => false, 'error' => 'duplicate');
    }

    $values = array();

    foreach ($row as $value) {
        $values[] = "'" . e((string) $value) . "'";
    }

    // Not db(): a failed statement there prints an HTML page, and the caller
    // answers JSON.
    if (!@mysqli_query(db::$con, "INSERT INTO user_passkeys (" . implode(', ', array_keys($row)) . ") VALUES (" . implode(', ', $values) . ")")) {
        return array('ok' => false, 'error' => (mysqli_errno(db::$con) === 1062) ? 'duplicate' : 'storage');
    }

    return array('ok' => true, 'passkey' => array(
        'id'              => (int) mysqli_insert_id(db::$con),
        'name'            => $row['name'],
        'created_at'      => $row['created_at'],
        'backup_eligible' => ($row['backup_eligible'] === 1),
        'backup_state'    => ($row['backup_state'] === 1),
    ));
}

// ── Sign-in ─────────────────────────────────────────────────────────────────

/**
 * Request options for a sign-in with a passkey, with the ceremony kept in the
 * session for its answer.
 *
 * With an account (the second step of its sign-in, after its password) the
 * browser is offered that account's passkeys only, and the person need not
 * be verified on the device: the password was the first factor. With none
 * (0) the browser offers whatever passkey it holds for the site; the answer
 * then names its account by the user handle and must verify the person (PIN,
 * fingerprint, face), since the passkey is the only factor.
 *
 * @param int $user_id
 * @return array ok, options (the publicKey JSON); or error
 */
function pg_passkey_signin_begin($user_id)
{
    $ready = pg_passkey_ready();

    if (!$ready['ok']) {
        return $ready;
    }

    $user_id = (int) $user_id;
    $allow = array();

    if ($user_id > 0) {

        $allow = pg_passkey_descriptors(pg_passkeys_of_user($user_id));

        if (count($allow) === 0) {
            return array('ok' => false, 'error' => 'none');
        }

    }

    $challenge = pg_webauthn_challenge();

    $options = pg_webauthn_request_options($ready['rp']['id'], $challenge, array(
        'allow'             => $allow,
        'user_verification' => ($user_id > 0) ? 'preferred' : 'required',
    ));

    if ($options === false) {
        return array('ok' => false, 'error' => 'unavailable');
    }

    pg_passkey_ceremony_begin($_SESSION['software']['passkey_ceremonies'], 'signin', $user_id, $challenge, time());

    return array('ok' => true, 'options' => $options);
}

/**
 * The checks of a sign-in answer that need no database: the answer verified
 * against the kept ceremony, the relying party and the stored row.
 *
 * @param array $response     from pg_webauthn_assertion_response()
 * @param array $ceremony     from pg_passkey_ceremony_take()
 * @param array $rp           id, origin
 * @param array $row          the stored passkey the response names
 * @param bool  $discoverable true when no account was known before (the
 *                            passkey is the only factor)
 * @return array the verifier's answer: ok, sign_count, user_verified,
 *               backup_eligible, backup_state; or error
 */
function pg_passkey_assertion_check($response, $ceremony, $rp, $row, $discoverable)
{
    return pg_webauthn_verify_assertion($response, array(
        'challenge'         => $ceremony['challenge'],
        'origins'           => array($rp['origin']),
        'rp_id'             => $rp['id'],
        'user_verification' => $discoverable ? 'required' : 'preferred',
        'discoverable'      => (bool) $discoverable,
    ), pg_passkey_record($row));
}

/**
 * Checks a sign-in answer against the ceremony kept for it and the stored
 * passkey, and records the use: the new counter, whether it is backed up
 * now, when it was used. The counter is written only over the value it was
 * checked against, so of two answers checked at the same moment against the
 * same counter, one is refused.
 *
 * @param mixed $input   the posted credential JSON
 * @param int   $user_id the account the ceremony was begun for (0: none)
 * @return array ok, user_id, passkey_id, name; or error
 */
function pg_passkey_signin_check($input, $user_id)
{
    $ready = pg_passkey_ready();

    if (!$ready['ok']) {
        return $ready;
    }

    $user_id = (int) $user_id;
    $ceremony = pg_passkey_ceremony_take($_SESSION['software']['passkey_ceremonies'], 'signin', $user_id, time());

    if ($ceremony === null) {
        return array('ok' => false, 'error' => 'challenge');
    }

    $response = pg_webauthn_assertion_response($input);

    if (!$response['ok']) {
        return $response;
    }

    $row = db_item("SELECT id, user_id, credential_id, public_key, alg, sign_count, backup_eligible, user_handle, name
        FROM user_passkeys WHERE credential_hash = '" . e(hash('sha256', $response['credential_id'])) . "'");

    // A passkey of another account answers like one that does not exist.
    if (!is_array($row) || (($user_id > 0) && ((int) $row['user_id'] !== $user_id))) {
        return array('ok' => false, 'error' => 'credential');
    }

    $result = pg_passkey_assertion_check($response, $ceremony, $ready['rp'], $row, ($user_id === 0));

    if (!$result['ok']) {
        return $result;
    }

    $stored = (int) $row['sign_count'];
    $counted = ($result['sign_count'] !== 0) || ($stored !== 0);

    db("UPDATE user_passkeys SET
            sign_count = '" . (int) $result['sign_count'] . "',
            backup_state = '" . ($result['backup_state'] ? 1 : 0) . "',
            last_used_at = '" . time() . "'
        WHERE id = '" . (int) $row['id'] . "'"
        . ($counted ? " AND sign_count = '" . $stored . "'" : ''));

    if ($counted && (mysqli_affected_rows(db::$con) !== 1)) {
        return array('ok' => false, 'error' => 'sign_count');
    }

    return array(
        'ok'         => true,
        'user_id'    => (int) $row['user_id'],
        'passkey_id' => (int) $row['id'],
        'name'       => (string) $row['name'],
    );
}

// ── Sign-in with a passkey alone ────────────────────────────────────────────

/**
 * Whether the sign-in screens offer a passkey as the only factor: the site
 * switched it on (config.passkey_signin, read into PASSKEY_SIGNIN; off
 * before the upgrade adds the column) and passkeys work on this site.
 *
 * @return bool
 */
function pg_passkey_passwordless_enabled()
{
    return defined('PASSKEY_SIGNIN') && ((int) PASSKEY_SIGNIN === 1) && pg_passkeys_available();
}

/**
 * Why the account a passkey named may not sign in, or '' when it may: it
 * must still exist ('account'), and a member whose address is on the block
 * list is refused ('blocked') as validate_login() refuses their password.
 * Staff are never stopped by the block list, for the reason given there.
 *
 * @param array|null $account       user row: user_id, user_role
 * @param bool       $email_blocked pg_email_blocked() of its address
 * @return string
 */
function pg_passkey_passwordless_refusal($account, $email_blocked)
{
    if (!is_array($account) || empty($account['user_id'])) {
        return 'account';
    }

    if (((int) $account['user_role'] === 3) && $email_blocked) {
        return 'blocked';
    }

    return '';
}

/**
 * The "Sign in with a passkey" button printed among the social sign-in
 * buttons (pg_signin_buttons()), or '' when the site does not offer it.
 *
 * The buttons sit inside the password form on most screens, so this is a
 * plain button rather than a form of its own: pg_passkey.js shows it when
 * the browser can use a passkey, runs the ceremony and posts the answer to
 * index.php in a form it builds, carrying the token, send_to, the
 * surrounding form's remember-me box and the page to come back to when it
 * is refused. The script is linked once per page.
 *
 * @param string $send_to where a completed sign-in lands
 * @return string
 */
function pg_passkey_signin_button($send_to = '')
{
    static $script_linked = false;

    if (!pg_passkey_passwordless_enabled()) {
        return '';
    }

    $script = '';

    if (!$script_linked) {
        $script_linked = true;
        $script = '<script src="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/js/pg_passkey.js?v='
            . (int) @filemtime(PG_FUNCTIONS_DIR . '/assets/js/pg_passkey.js') . '" defer="defer"></script>';
    }

    $texts = json_encode(array(
        'cancelled' => lang('The passkey was not used. Please try again.'),
        'failed'    => lang('Your browser could not use a passkey here.'),
    ), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

    return '<div class="pg-signin-button pg-signin-passkey" style="margin-top:1em;text-align:center" hidden="hidden">'
        . '<button type="button" data-pg-passkey-signin="1"'
        . ' data-options-url="' . h(PATH . SOFTWARE_DIRECTORY . '/passkey.php') . '"'
        . ' data-action="' . h(PATH . SOFTWARE_DIRECTORY . '/index.php') . '"'
        . ' data-token="' . h((string) ($_SESSION['software']['token'] ?? '')) . '"'
        . ' data-send-to="' . h((string) $send_to) . '"'
        . ' data-return-to="' . h(get_request_uri()) . '"'
        . ' data-texts="' . h($texts) . '"'
        . ' style="display:inline-flex;align-items:center;justify-content:center;min-width:240px;max-width:100%;box-sizing:border-box;padding:9px 18px;border:1px solid #747775;border-radius:4px;background:#ffffff !important;color:#1f1f1f !important;font-weight:500;font-size:14px !important;line-height:1.4;cursor:pointer">'
        . '<i class="bi bi-key" aria-hidden="true" style="margin-right:10px"></i>'
        . '<span style="color:#1f1f1f !important;white-space:nowrap">' . h(lang('Sign in with a passkey')) . '</span></button>'
        . '<div class="pg-passkey-signin-error" role="alert" hidden="hidden" style="margin-top:.5em"></div>'
        . '</div>' . $script;
}

/**
 * Where a refused passkey sign-in goes back to: the page the button was on
 * (a designed login page prints the error there, as for a refused
 * password), else the login screen.
 *
 * @return string absolute URL
 */
function pg_passkey_passwordless_back_url()
{
    $return_to = (isset($_POST['return_to']) && is_scalar($_POST['return_to'])) ? (string) $_POST['return_to'] : '';

    if ($return_to !== '') {
        return URL_SCHEME . HOSTNAME . pg_safe_redirect_path($return_to);
    }

    return URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/';
}

/**
 * Completes a sign-in with a passkey alone, posted to index.php by
 * pg_passkey.js; does not return.
 *
 * The passkey is the first factor and, verified on the device (PIN,
 * fingerprint, face: pg_passkey_signin_check() with no account requires it),
 * the second as well, so no code is asked for. The account it names must
 * still exist and not be blocked; the rest is the order the password
 * sign-in uses after its second step: device limit (told the second step is
 * done), device token, session, order. A refusal is written to the login
 * form's messages and the visitor sent back to the page the button was on.
 */
function pg_passkey_passwordless_signin()
{
    validate_token_field();
    require_cookies();
    check_banned_ip_addresses('login');

    $error = '';
    $account = null;
    $name = '';

    if (!pg_passkey_passwordless_enabled()) {
        $error = 'passwordless_off';
    } elseif (pg_passkey_passwordless_blocked()) {
        $error = 'attempts';
    } else {
        $credential = (isset($_POST['credential']) && is_string($_POST['credential'])) ? $_POST['credential'] : '';
        $result = pg_passkey_signin_check($credential, 0);

        if (!$result['ok']) {
            $error = $result['error'];
        } else {
            $account = db_item("SELECT user_id, user_username, user_email, user_role
                FROM user WHERE user_id = '" . (int) $result['user_id'] . "'");
            $error = pg_passkey_passwordless_refusal($account,
                is_array($account) && pg_email_blocked((string) $account['user_email']));
            $name = (string) $result['name'];
        }
    }

    if ($error !== '') {
        log_activity(lang(array(
            'string' => 'access denied (passkey not accepted: {var:1})',
            'vars'   => array($error))), is_array($account) ? (string) $account['user_username'] : lang('UNKNOWN'));

        $liveform = new liveform('login');
        $liveform->mark_error('email', ($error === 'attempts')
            ? lang('Too many attempts. Please wait a few minutes and try again.')
            : pg_passkey_refusal($error, 'signin'));

        header('Location: ' . pg_passkey_passwordless_back_url());
        exit();
    }

    $user_id = (int) $account['user_id'];
    $username = (string) $account['user_username'];
    $send_to = (isset($_POST['send_to']) && is_scalar($_POST['send_to'])) ? (string) $_POST['send_to'] : '';
    // The remember-me box of the form the button sat in, when it has one.
    $remember = (REMEMBER_ME == TRUE) && isset($_POST['remember_me']) && ((string) $_POST['remember_me'] === '1');

    pg_device_limit_gate($user_id, $username, $send_to, $remember, true);
    pg_login_set_device_cookie($user_id, $remember);

    if (REMEMBER_ME == TRUE) {
        setcookie('software[remember_me]', $remember ? 'true' : 'false', time() + 315360000, '/');
    }

    pg_session_sign_in($user_id, $username);

    require_once(PG_FUNCTIONS_DIR . '/connect_user_to_order.php');
    connect_user_to_order();

    log_activity(lang(array(
        'string' => 'user signed in with a passkey ({var:1})',
        'vars'   => array($name))), $username);

    send_user_to_login_home();
    exit();
}

/**
 * Counts one passkey sign-in attempt of this address and says whether it is
 * over its allowance (30 in 10 minutes). A passkey cannot be guessed, but
 * each attempt costs a signature check and a lookup, so a script posting
 * answers in a loop is stopped. Fails open where waf_rate is unavailable,
 * like every other rate limit here.
 *
 * @return bool
 */
function pg_passkey_passwordless_blocked()
{
    if (!function_exists('waf_rate_exceeded') || !function_exists('waf_client_ip') || !isset(db::$con) || !db::$con) {
        return false;
    }

    $ip = waf_client_ip();

    if (($ip === '') || !function_exists('waf_ip_subject')) {
        return false;
    }

    return waf_rate_exceeded(waf_ip_subject($ip), 'passkey-ip', 30, 600);
}

// ── Words for the visitor ───────────────────────────────────────────────────

/**
 * The one sentence a visitor is shown for a refused ceremony. Only the codes
 * about the site, the session or the account's own list get words of their
 * own; every refusal of the credential itself reads the same.
 *
 * @param string $error   a code of this module or of the verifier
 * @param string $context 'register' or 'signin'
 * @return string
 */
function pg_passkey_refusal($error, $context)
{
    switch ($error) {
        case 'unavailable':
            return lang('Passkeys cannot be used on this site.');

        case 'host':
            return lang(array('string' => 'Passkeys work at {var:1} only. Open the site there and try again.', 'vars' => defined('HOSTNAME_SETTING') ? (string) HOSTNAME_SETTING : ''));

        case 'challenge':
            return lang('The request has expired or was used already. Please try again.');

        case 'none':
            return lang('This account has no passkey.');

        case 'limit':
            return lang(array('string' => 'An account can keep at most {var:1} passkeys. Remove one first.', 'vars' => (string) PG_PASSKEYS_MAX));

        case 'duplicate':
            return lang('This passkey is registered already.');

        case 'passwordless_off':
            return lang('Signing in with a passkey alone is not available on this site.');
    }

    return ($context === 'register')
        ? lang('The passkey could not be added. Please try again.')
        : lang('The passkey was not accepted.');
}

// ── Account screen ──────────────────────────────────────────────────────────

/**
 * What a person proves before changing the passkeys of their account, by
 * what the account has: its password (every account but a Google-only one,
 * algo 3, which has none) and, while two-step verification is on, a code
 * from the authenticator app or a recovery code. An account with neither
 * proves nothing beyond its session. Adding a passkey is offered only while
 * two-step verification is on, so adding always asks for the code.
 *
 * @param int  $password_algo user.user_password_algo
 * @param bool $mfa_enabled   pg_mfa_enabled() of the account
 * @return array password, code: whether each is asked for
 */
function pg_passkey_reauth_fields($password_algo, $mfa_enabled)
{
    return array(
        'password' => ((int) $password_algo !== 3),
        'code'     => (bool) $mfa_enabled,
    );
}

/**
 * The passkeys part of the account security section
 * (pg_account_security_section()), in the plain style of
 * pg_mfa_account_section(): the account's passkeys, each with a rename and a
 * remove form that post to account_security.php, and the form that adds one
 * through passkey.php (assets/js/pg_passkey.js). That form is hidden until
 * its script finds a browser that can make passkeys.
 *
 * Adding is offered only while two-step verification is on: a passkey
 * answers the second step of a sign-in in place of the code, and only an
 * account with two-step verification is asked for one. The current password
 * (none for a Google-only account) and a code are asked for before the
 * browser makes the passkey, so a stolen session cannot plant one.
 *
 * @param int    $user_id
 * @param int    $password_algo user.user_password_algo; 3 is a Google-only
 *                              account, which has no password to ask for.
 * @param string $action_url    account_security.php
 * @param string $token         The CSRF field.
 * @return string '' before the upgrade, and on a site where passkeys cannot
 *                work while the account has none.
 */
function pg_passkey_account_section($user_id, $password_algo, $action_url, $token)
{
    if (!pg_passkeys_table_exists()) {
        return '';
    }

    $user_id = (int) $user_id;
    $passkeys = pg_passkeys_of_user($user_id);
    $count = count($passkeys);
    $ready = pg_passkey_ready();

    // A site where passkeys never work (no OpenSSL with EC keys, an IP
    // address, plain http) says nothing about them; one opened at another of
    // its names says where they work.
    if (!$ready['ok'] && ($count === 0) && ($ready['error'] !== 'host')) {
        return '';
    }

    $heading = '<div class="heading" style="margin:1.5em 0 10px">' . h(lang('Passkeys')) . '</div>';

    // An operator signed in as this person sees how many there are and
    // nothing to press; account_security.php and passkey.php refuse the
    // changes in that mode as well.
    if (!empty($_SESSION['software']['logged_in_as_different_user'])) {
        return $heading . '<p>' . h(lang(array(
            'string' => 'This account has {var:1} passkey{suffix:1}.',
            'vars'   => (string) $count,
            'suffix' => ($count === 1) ? '' : 's'))) . '</p>';
    }

    $mfa = pg_mfa_table_exists() && pg_mfa_enabled($user_id);
    $proofs = pg_passkey_reauth_fields($password_algo, $mfa);

    $form = function ($action, $inner) use ($action_url, $token) {
        return '<form method="post" action="' . h($action_url) . '" style="margin:.5em 0 1em" autocomplete="off">' . $token
            . '<input type="hidden" name="pg_security_action" value="' . h($action) . '"/>' . $inner . '</form>';
    };

    $field = function ($id, $label, $input) {
        return '<label for="' . h($id) . '" style="display:block;margin-bottom:.25em">' . h($label) . '</label>' . $input;
    };

    $password_input = function ($id) use ($field) {
        return $field($id, lang('Current password'),
            '<input type="password" id="' . h($id) . '" name="current_password" class="software_input_password" required="required" autocomplete="current-password" style="margin-bottom:.5em"/><br/>');
    };

    // Six digits or a recovery code, so no numeric keyboard is asked for.
    $code_input = function ($id) use ($field) {
        return $field($id, lang('Code from your authenticator app or a recovery code'),
            '<input type="text" id="' . h($id) . '" name="code" class="software_input_text" required="required" autocomplete="one-time-code" maxlength="20" spellcheck="false" style="margin-bottom:.5em"/><br/>');
    };

    $html = $heading
        . '<p>' . h(lang('A passkey lets your fingerprint, face or screen lock, or a security key, stand in for the code from your authenticator app when you sign in.')) . '</p>';

    if ($count === 0) {
        $html .= '<p>' . h(lang('This account has no passkey.')) . '</p>';
    } else {
        $rows_html = '';

        foreach ($passkeys as $passkey) {
            $id = (int) $passkey['id'];
            $name = ($passkey['name'] !== '') ? $passkey['name'] : lang('Passkey');

            // get_absolute_time() wraps the date in a <time> element; only
            // the text is printed, escaped.
            $added = strip_tags(get_absolute_time(array('timestamp' => $passkey['created_at'], 'type' => 'date')));

            // get_relative_time() returns a <time> element, not plain text.
            $last = ($passkey['last_used_at'] > 0)
                ? get_relative_time(array('timestamp' => $passkey['last_used_at']))
                : h(lang('Never'));

            $synced = $passkey['backup_state']
                ? ' <span class="badge bg-secondary fw-light" title="' . h(lang('Kept in a password manager or cloud account, so your other devices can use it too.')) . '">' . h(lang('Synced')) . '</span>'
                : '';

            $rename = $form('passkey_rename',
                '<input type="hidden" name="passkey_id" value="' . $id . '"/>'
                . $field('pg_passkey_name_' . $id, lang('Name'),
                    '<input type="text" id="pg_passkey_name_' . $id . '" name="name" class="software_input_text" maxlength="' . (int) PG_PASSKEY_NAME_MAX . '" value="' . h($name) . '" style="margin-bottom:.5em"/><br/>')
                . '<button type="submit" class="software_input_submit_primary">' . h(lang('Save')) . '</button>');

            $remove = $form('passkey_remove',
                '<input type="hidden" name="passkey_id" value="' . $id . '"/>'
                . '<p style="margin-bottom:.5em">' . h(lang('After removing it here, delete it from your device or password manager as well.')) . '</p>'
                . ($proofs['password'] ? $password_input('pg_passkey_remove_password_' . $id) : '')
                . ($proofs['code'] ? $code_input('pg_passkey_remove_code_' . $id) : '')
                . '<button type="submit" class="software_input_submit_secondary">' . h(lang('Remove')) . '</button>');

            $rows_html .=
                '<tr><td style="padding:.4em .6em">' . h($name) . $synced
                . '<div style="margin-top:.25em">'
                . '<details style="display:inline-block;vertical-align:top;margin-right:1.5em"><summary style="cursor:pointer">' . h(lang('Rename')) . '</summary>' . $rename . '</details>'
                . '<details style="display:inline-block;vertical-align:top"><summary style="cursor:pointer">' . h(lang('Remove')) . '</summary>' . $remove . '</details>'
                . '</div></td>'
                . '<td style="padding:.4em .6em">' . h($added) . '</td>'
                . '<td style="padding:.4em .6em">' . $last . '</td></tr>';
        }

        $html .=
            '<table style="width:100%;border-collapse:collapse" class="table">'
            . '<thead><tr>'
            . '<th style="text-align:left;padding:.4em .6em">' . h(lang('Name')) . '</th>'
            . '<th style="text-align:left;padding:.4em .6em">' . h(lang('Added')) . '</th>'
            . '<th style="text-align:left;padding:.4em .6em">' . h(lang('Last used')) . '</th>'
            . '</tr></thead><tbody>' . $rows_html . '</tbody></table>';
    }

    if (!$ready['ok']) {
        return $html . '<p>' . h(pg_passkey_refusal($ready['error'], 'register')) . '</p>';
    }

    if (!$mfa) {
        return $html . '<p>' . h(lang('Turn on two-step verification first; a passkey then stands in for its code.')) . '</p>';
    }

    if ($count >= PG_PASSKEYS_MAX) {
        return $html . '<p>' . h(pg_passkey_refusal('limit', 'register')) . '</p>';
    }

    $json = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;

    // The add form posts nowhere itself: its script asks passkey.php for the
    // options with the proofs, lets the browser make the passkey and sends it
    // back. A browser that cannot make passkeys is told so instead.
    return $html
        . '<div id="pg_passkey_add_block" style="display:none">'
        . '<p style="margin-bottom:.5em"><strong>' . h(lang('Add a passkey')) . '</strong></p>'
        . '<form id="pg_passkey_add" method="post" action="' . h($action_url) . '" style="margin:0 0 1em" autocomplete="off">' . $token
        . $field('pg_passkey_add_name', lang('Name'),
            '<input type="text" id="pg_passkey_add_name" name="name" class="software_input_text" maxlength="' . (int) PG_PASSKEY_NAME_MAX . '" placeholder="' . h(lang('My phone')) . '" style="margin-bottom:.5em"/><br/>')
        . ($proofs['password'] ? $password_input('pg_passkey_add_password') : '')
        . $code_input('pg_passkey_add_code')
        . '<button type="submit" class="software_input_submit_primary">' . h(lang('Add a passkey')) . '</button>'
        . '<div id="pg_passkey_add_message" class="error" role="alert" style="margin-top:.5em"></div>'
        . '</form></div>'
        . '<p id="pg_passkey_add_unsupported" style="display:none">' . h(lang('This browser cannot create passkeys.')) . '</p>'
        . '<script src="' . h(OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/js/pg_passkey.js') . '?v=' . (int) @filemtime(PG_FUNCTIONS_DIR . '/assets/js/pg_passkey.js') . '"></script>
        <script>
        (function () {
            var block = document.getElementById("pg_passkey_add_block");
            var form = document.getElementById("pg_passkey_add");
            if (!block || !form) {
                return;
            }
            if (!window.PgPasskey || !window.PgPasskey.supported()) {
                document.getElementById("pg_passkey_add_unsupported").style.display = "";
                return;
            }
            var fields = form.elements;
            var button = form.querySelector("button[type=submit]");
            var notice = document.getElementById("pg_passkey_add_message");
            var texts = ' . json_encode(pg_passkey_add_texts(), $json) . ';
            block.style.display = "";
            form.addEventListener("submit", function (event) {
                event.preventDefault();
                button.disabled = true;
                notice.textContent = "";
                window.PgPasskey.register({
                    url: ' . json_encode(PATH . SOFTWARE_DIRECTORY . '/passkey.php', $json | JSON_UNESCAPED_SLASHES) . ',
                    token: fields.namedItem("token").value,
                    name: fields.namedItem("name").value,
                    currentPassword: fields.namedItem("current_password") ? fields.namedItem("current_password").value : "",
                    code: fields.namedItem("code").value
                }).then(function () {
                    window.location.reload();
                }).catch(function (error) {
                    var code = error && error.code;
                    // A code is spent once passkey.php accepts it, so a
                    // cancelled dialog needs the next one.
                    fields.namedItem("code").value = "";
                    button.disabled = false;
                    notice.textContent = (error && error.message)
                        || (((code === "NotAllowedError") || (code === "AbortError")) ? texts.cancelled
                        : (code === "InvalidStateError") ? texts.duplicate
                        : (code === "attempts") ? texts.attempts
                        : texts.failed);
                });
            });
        })();
        </script>';
}

/**
 * The sentences the add a passkey script shows when the browser or the
 * server refuses: the classic section's and the account security widget's.
 *
 * @return array cancelled, duplicate, attempts, failed, unsupported
 */
function pg_passkey_add_texts()
{
    return array(
        'cancelled'   => lang('The passkey was not created. Please try again.'),
        'duplicate'   => pg_passkey_refusal('duplicate', 'register'),
        'attempts'    => pg_mfa_reauth_message('attempts'),
        'failed'      => lang('Your browser could not use a passkey here.'),
        'unsupported' => lang('This browser cannot create passkeys.'),
    );
}

/**
 * The script of the account security widget's add a passkey forms
 * (form[data-pg-passkey-add], wired by _pg_sec_apply_action_bindings()):
 * each form stays hidden until the browser can make passkeys, else a
 * sentence takes its place. On submit it asks passkey.php for the options
 * with the form's name, current_password and code, lets the browser make
 * the passkey and reloads the page; a refusal is shown in the form's
 * [data-pg-passkey-message] element, or in one added after its last
 * control.
 *
 * @return string
 */
function pg_passkey_add_form_script()
{
    $json = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;

    return '<script src="' . h(OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/js/pg_passkey.js') . '?v=' . (int) @filemtime(PG_FUNCTIONS_DIR . '/assets/js/pg_passkey.js') . '"></script>
        <script>
        (function () {
            var texts = ' . json_encode(pg_passkey_add_texts(), $json) . ';
            var url = ' . json_encode(PATH . SOFTWARE_DIRECTORY . '/passkey.php', $json | JSON_UNESCAPED_SLASHES) . ';
            var supported = !!(window.PgPasskey && window.PgPasskey.supported());
            Array.prototype.forEach.call(document.querySelectorAll("form[data-pg-passkey-add]"), function (form) {
                if (!supported) {
                    var note = document.createElement("p");
                    note.className = "text-body-secondary";
                    note.textContent = texts.unsupported;
                    form.parentNode.insertBefore(note, form);
                    return;
                }
                var fields = form.elements;
                var value = function (name) {
                    var field = fields.namedItem(name);
                    return (field && field.value) ? field.value : "";
                };
                var notice = form.querySelector("[data-pg-passkey-message]");
                if (!notice) {
                    notice = document.createElement("div");
                    notice.className = "text-danger small mt-2";
                    notice.setAttribute("role", "alert");
                    form.appendChild(notice);
                }
                form.hidden = false;
                form.addEventListener("submit", function (event) {
                    event.preventDefault();
                    var button = form.querySelector("[type=submit]");
                    if (button) {
                        button.disabled = true;
                    }
                    notice.textContent = "";
                    window.PgPasskey.register({
                        url: url,
                        token: value("token"),
                        name: value("name"),
                        currentPassword: value("current_password"),
                        code: value("code")
                    }).then(function () {
                        window.location.reload();
                    }).catch(function (error) {
                        var code = error && error.code;
                        // A code is spent once passkey.php accepts it, so a
                        // cancelled dialog needs the next one.
                        if (fields.namedItem("code")) {
                            fields.namedItem("code").value = "";
                        }
                        if (button) {
                            button.disabled = false;
                        }
                        notice.textContent = (error && error.message)
                            || (((code === "NotAllowedError") || (code === "AbortError")) ? texts.cancelled
                            : (code === "InvalidStateError") ? texts.duplicate
                            : (code === "attempts") ? texts.attempts
                            : texts.failed);
                    });
                });
            });
        })();
        </script>';
}
