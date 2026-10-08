<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Two-step sign-in: time-based one-time passwords from an authenticator app
 * (RFC 6238) and one-time recovery codes.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// Two sections. The pure primitives touch neither the database nor the
// session, so they are covered by tests/mfa_test.php. The account state
// section reads and writes user_mfa / user_mfa_recovery (schema step 8.40)
// and the pending sign-in in the session; every entry point there asks
// pg_mfa_table_exists() first, so a site whose upgrade has not run yet signs
// in exactly as before. Secrets are passed around as raw bytes; base32 is
// only the shape a person types into an app and the shape that is stored
// encrypted.
//
// Nothing here may run at load time: tools/test.php loads functions.php with
// no connection, and every module is loaded on every request.

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// ── Pure primitives ─────────────────────────────────────────────────────────

/**
 * Base32 encoding per RFC 4648 section 6 (alphabet A-Z2-7), padded with '='
 * to a multiple of eight characters.
 *
 * The bit buffer is masked after every output character so it never holds
 * more than 12 bits and stays within a 32-bit integer.
 *
 * @param string $bytes Raw bytes.
 * @return string Base32 text with padding; '' for ''.
 */
function pg_base32_encode($bytes)
{
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $bytes = (string) $bytes;
    $length = strlen($bytes);
    $output = '';
    $buffer = 0;
    $bits = 0;

    for ($i = 0; $i < $length; $i++) {
        $buffer = ($buffer << 8) | ord($bytes[$i]);
        $bits += 8;

        while ($bits >= 5) {
            $bits -= 5;
            $output .= $alphabet[($buffer >> $bits) & 31];
        }

        $buffer &= (1 << $bits) - 1;
    }

    if ($bits > 0) {
        $output .= $alphabet[($buffer << (5 - $bits)) & 31];
    }

    $remainder = strlen($output) % 8;

    if ($remainder !== 0) {
        $output .= str_repeat('=', 8 - $remainder);
    }

    return $output;
}

/**
 * Base32 decoding per RFC 4648 section 6.
 *
 * Tolerant on purpose: the key is often read off a screen and typed by hand,
 * and authenticator apps show it in lower case, in groups of four and without
 * padding. Whitespace is dropped, letters are upper-cased and trailing '='
 * is optional. Any other character outside the alphabet rejects the whole
 * input rather than decoding to a different key; no look-alike mapping
 * (0 to O, 1 to I) is done, since the alphabet has no 0 or 1 to map to.
 * Leftover bits that do not make a full byte are discarded.
 *
 * @param string $text Base32 text.
 * @return string Raw bytes; '' when the input is empty or invalid.
 */
function pg_base32_decode($text)
{
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $text = rtrim(strtoupper(preg_replace('/\s+/', '', (string) $text)), '=');
    $length = strlen($text);
    $output = '';
    $buffer = 0;
    $bits = 0;

    for ($i = 0; $i < $length; $i++) {
        $value = strpos($alphabet, $text[$i]);

        if ($value === false) {
            return '';
        }

        $buffer = ($buffer << 5) | $value;
        $bits += 5;

        if ($bits >= 8) {
            $bits -= 8;
            $output .= chr(($buffer >> $bits) & 0xFF);
        }

        $buffer &= (1 << $bits) - 1;
    }

    return $output;
}

/**
 * A new TOTP shared secret: 20 bytes from the CSPRNG, the HMAC-SHA1 key
 * length RFC 4226 section 4 recommends (160 bits).
 *
 * @return string Raw bytes, not base32.
 */
function pg_totp_secret()
{
    return random_bytes(20);
}

/**
 * The RFC 6238 time step: the number of whole periods since the Unix epoch
 * (T0 = 0).
 *
 * @param int|null $time   Unix seconds; the current time when null.
 * @param int      $period Step length in seconds (X); 30 is what every
 *                         authenticator app assumes.
 * @return int
 */
function pg_totp_step($time = null, $period = 30)
{
    if ($time === null) {
        $time = time();
    }

    return (int) floor($time / $period);
}

/**
 * The HOTP value (RFC 4226 section 5) for one TOTP step (RFC 6238 section 4),
 * with HMAC-SHA1.
 *
 * The counter is an 8-byte big-endian integer. It is packed as two 32-bit
 * words, the high one zero, because pack('J') does not exist on 32-bit PHP;
 * a time step does not pass 2^32 until the year 6053.
 *
 * @param string $secret Raw key bytes.
 * @param int    $step   Counter, usually pg_totp_step().
 * @param int    $digits Code length; 6 for apps, 8 for the RFC test vectors.
 * @return string The code, left-padded with zeros to $digits.
 */
function pg_totp_code($secret, $step, $digits = 6)
{
    $hash = hash_hmac('sha1', pack('N*', 0, (int) $step), (string) $secret, true);

    // Dynamic truncation: the low nibble of the last byte picks four bytes,
    // whose top bit is masked so the result is a positive 31-bit integer.
    $offset = ord($hash[19]) & 0x0F;
    $binary = ((ord($hash[$offset]) & 0x7F) << 24)
        | (ord($hash[$offset + 1]) << 16)
        | (ord($hash[$offset + 2]) << 8)
        | ord($hash[$offset + 3]);

    return str_pad((string) ($binary % pow(10, (int) $digits)), (int) $digits, '0', STR_PAD_LEFT);
}

/**
 * Checks a six-digit code against the steps around $now.
 *
 * Steps are tried closest first (0, -1, +1, -2, +2, ...), so when a code
 * happens to match two steps the one nearest the clock is the one recorded.
 * A step at or below $last_step is skipped: RFC 6238 section 5.2 says a code
 * must not be accepted twice, and the caller stores the step returned here
 * as the new $last_step. The comparison uses hash_equals() so the time taken
 * does not reveal how many leading digits of a guess were right.
 *
 * @param string $secret    Raw key bytes.
 * @param string $code      What the person typed; exactly six ASCII digits.
 * @param int    $now       Unix seconds.
 * @param int    $last_step Last accepted step for this key; 0 when none.
 * @param int    $window    Steps of clock drift accepted on either side.
 * @return int|false The accepted step, or false.
 */
function pg_totp_verify($secret, $code, $now, $last_step, $window = 1)
{
    $secret = (string) $secret;
    $code = (string) $code;

    if (($secret === '') || !preg_match('/^[0-9]{6}$/D', $code)) {
        return false;
    }

    $step = pg_totp_step($now);
    $last_step = (int) $last_step;
    $offsets = array(0);

    for ($i = 1; $i <= (int) $window; $i++) {
        $offsets[] = -$i;
        $offsets[] = $i;
    }

    foreach ($offsets as $offset) {
        $candidate = $step + $offset;

        if (($candidate < 0) || ($candidate <= $last_step)) {
            continue;
        }

        if (hash_equals(pg_totp_code($secret, $candidate, 6), $code)) {
            return $candidate;
        }
    }

    return false;
}

/**
 * The otpauth:// key URI understood by authenticator apps (the Key URI
 * Format published with Google Authenticator), with the defaults spelled out
 * so no app has to guess: SHA1, six digits, 30 seconds.
 *
 * The secret goes without '=' padding, which several apps refuse.
 *
 * @param string $issuer  Site name shown in the app.
 * @param string $account Account label, usually the e-mail address.
 * @param string $secret  Raw key bytes.
 * @return string
 */
function pg_totp_uri($issuer, $account, $secret)
{
    return 'otpauth://totp/' . rawurlencode((string) $issuer) . ':' . rawurlencode((string) $account)
        . '?secret=' . rtrim(pg_base32_encode($secret), '=')
        . '&issuer=' . rawurlencode((string) $issuer)
        . '&algorithm=SHA1&digits=6&period=30';
}

/**
 * One-time recovery codes in the shape XXXX-XXXX.
 *
 * The alphabet leaves out I, O, 0 and 1, which are easy to misread on paper.
 * 32 symbols and 8 characters give 40 bits per code; random_int() is the
 * CSPRNG. A duplicate is drawn again so every code in a set is distinct.
 *
 * @param int $count Number of codes.
 * @return string[]
 */
function pg_mfa_recovery_codes($count = 10)
{
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $last = strlen($alphabet) - 1;
    $codes = array();

    while (count($codes) < $count) {
        $code = '';

        for ($i = 0; $i < 8; $i++) {
            if ($i === 4) {
                $code .= '-';
            }

            $code .= $alphabet[random_int(0, $last)];
        }

        $codes[$code] = true;
    }

    return array_keys($codes);
}

/**
 * A recovery code reduced to the form that is hashed: upper case, with the
 * separator, spaces and anything else that is not A-Z or 0-9 removed.
 * Characters outside the code alphabet are kept as typed rather than mapped
 * to a look-alike; such a code simply does not match.
 *
 * @param string $code
 * @return string
 */
function pg_mfa_recovery_normalize($code)
{
    return preg_replace('/[^A-Z0-9]/', '', strtoupper((string) $code));
}

/**
 * How a recovery code is stored: SHA-256 of its normalized form. The codes
 * carry 40 random bits and are compared by exact lookup, so a fast unsalted
 * hash is enough to keep a database dump from yielding usable codes.
 *
 * @param string $code
 * @return string 64 lower-case hex characters.
 */
function pg_mfa_recovery_hash($code)
{
    return hash('sha256', pg_mfa_recovery_normalize($code));
}

/**
 * The key as it is shown for typing into an app: unpadded base32 in groups
 * of four separated by spaces. pg_base32_decode() reads it back as it is.
 *
 * @param string $secret Raw key bytes.
 * @return string
 */
function pg_mfa_format_secret($secret)
{
    $text = rtrim(pg_base32_encode($secret), '=');

    if ($text === '') {
        return '';
    }

    return implode(' ', str_split($text, 4));
}

// ── Account state (database) ────────────────────────────────────────────────

// How long a password-verified sign-in waits for its second step, and how
// long a generated but unconfirmed key stays on offer.
function pg_mfa_pending_lifetime()
{
    return 600;
}

function pg_mfa_setup_lifetime()
{
    return 1800;
}

/**
 * Whether both tables of schema step 8.40 exist. Probed by exact name in
 * information_schema (SHOW TABLES LIKE would read '_' as a wildcard) and
 * cached for the request. Between new files landing and the upgrade running
 * this is false, and every caller treats the feature as switched off.
 *
 * @return bool
 */
function pg_mfa_table_exists()
{
    static $exists = null;

    if ($exists !== null) {
        return $exists;
    }

    if (!isset(db::$con) || !db::$con) {
        return false;
    }

    $count = db_value("SELECT COUNT(*) FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME IN ('user_mfa', 'user_mfa_recovery')");

    $exists = ((int) $count === 2);

    return $exists;
}

/**
 * Whether a second factor can be set up and checked on this site: the
 * tables exist and the key can be encrypted (ENCRYPTION_KEY and openssl).
 *
 * @return bool
 */
function pg_mfa_available()
{
    return pg_mfa_table_exists()
        && defined('ENCRYPTION_KEY')
        && (ENCRYPTION_KEY !== '')
        && extension_loaded('openssl');
}

/**
 * The user_mfa row of an account, or null.
 *
 * @param int $user_id
 * @return array|null
 */
function pg_mfa_row($user_id)
{
    if (!pg_mfa_table_exists()) {
        return null;
    }

    $row = db_item(
        "SELECT user_id, method, totp_secret, enabled_at, last_step, pending_secret, pending_at
        FROM user_mfa WHERE user_id = '" . (int) $user_id . "'");

    return is_array($row) ? $row : null;
}

/**
 * Whether the account has a confirmed second factor that sign-in must ask
 * for.
 *
 * Deliberately not tied to pg_mfa_available(): a key that cannot be read
 * (ENCRYPTION_KEY or openssl gone) is still a key the account asked for, so
 * sign-in keeps asking rather than falling back to the password alone. The
 * recovery codes are hashed, not encrypted, so they keep working then.
 *
 * @param int $user_id
 * @return bool
 */
function pg_mfa_enabled($user_id)
{
    $row = pg_mfa_row($user_id);

    return ($row !== null) && ((int) $row['enabled_at'] > 0) && ((string) $row['totp_secret'] !== '');
}

/**
 * Accounts whose role is at or below this value must have a second factor;
 * 99 means no role is required to.
 *
 * @return int
 */
function pg_mfa_required_role()
{
    return defined('MFA_REQUIRED_ROLE') ? (int) MFA_REQUIRED_ROLE : 99;
}

/**
 * Whether the account's role obliges it to have a second factor.
 *
 * @param int $user_id
 * @return bool
 */
function pg_mfa_required_for_user($user_id)
{
    $required_role = pg_mfa_required_role();

    if (($required_role >= 99) || !pg_mfa_available()) {
        return false;
    }

    $role = db_value("SELECT user_role FROM user WHERE user_id = '" . (int) $user_id . "'");

    return ($role !== null) && ((int) $role <= $required_role);
}

/**
 * Encrypt a key for storage: base32, then AES-256-CBC under ENCRYPTION_KEY,
 * kept as "cipher:iv" like the connector credentials. The key is read back
 * on every sign-in to compute the expected code, so it cannot be hashed.
 *
 * @param string $secret_bytes Raw key bytes.
 * @return string
 */
function pg_mfa_secret_encode($secret_bytes)
{
    list($cipher, $iv) = encrypt_string_with_iv(pg_base32_encode($secret_bytes));

    return $cipher . ':' . $iv;
}

/**
 * Decrypt a stored key back to raw bytes; '' when the blob is empty, damaged
 * or was encrypted under a different ENCRYPTION_KEY.
 *
 * @param string $blob
 * @return string
 */
function pg_mfa_secret_decode($blob)
{
    // decode_ssl_keys() reads the constant and calls openssl directly.
    if (!defined('ENCRYPTION_KEY') || (ENCRYPTION_KEY === '') || !extension_loaded('openssl')) {
        return '';
    }

    $parts = explode(':', (string) $blob, 2);

    if ((count($parts) !== 2) || ($parts[0] === '') || ($parts[1] === '')) {
        return '';
    }

    return pg_base32_decode(decode_ssl_keys($parts[0], $parts[1]));
}

/**
 * The sign-in waiting for its second step, or null. An expired or malformed
 * record is removed on the way.
 *
 * @return array|null
 */
function pg_mfa_pending()
{
    $pending = isset($_SESSION['software']['mfa_pending']) ? $_SESSION['software']['mfa_pending'] : null;

    $valid = is_array($pending)
        && !empty($pending['user_id'])
        && isset($pending['time'])
        && ((time() - (int) $pending['time']) <= pg_mfa_pending_lifetime())
        && isset($pending['mode'])
        && in_array($pending['mode'], array('verify', 'setup'), true);

    if (!$valid) {
        pg_mfa_pending_clear();
        return null;
    }

    return $pending;
}

function pg_mfa_pending_clear()
{
    unset($_SESSION['software']['mfa_pending']);
}

// Second-factor gate for every password (or Google) sign-in. Called after the
// credentials were accepted and the throttle cleared, before the device limit
// is counted and before any token is minted: a token minted here would sign
// the browser in on its next request through initialize_user() with no code
// asked. Returns false when nothing is owed and the caller carries on;
// otherwise it stashes the verified identity as a short-lived pending record
// and sends the visitor to mfa.php without returning. 'setup' mode is for an
// account whose role must have a second factor and has none yet.
function pg_mfa_gate($user_id, $username, $send_to, $remember, $origin)
{
    $mode = '';

    if (pg_mfa_enabled($user_id)) {
        $mode = 'verify';
    } elseif (pg_mfa_required_for_user($user_id)) {
        $mode = 'setup';
    }

    if ($mode === '') {
        return false;
    }

    // The password was right: a session id that may have been planted before
    // it was typed must not carry the half-signed-in state.
    // pg_session_sign_in() renews the id once more when the sign-in completes.
    if ((session_status() === PHP_SESSION_ACTIVE) && !headers_sent()) {
        session_regenerate_id(true);
    }

    $_SESSION['software']['mfa_pending'] = array(
        'user_id'  => (int) $user_id,
        'username' => (string) $username,
        'send_to'  => (string) $send_to,
        'remember' => $remember ? 1 : 0,
        'mode'     => $mode,
        'origin'   => (string) $origin,
        'time'     => time(),
    );

    header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/mfa.php');
    exit();
}

/**
 * The key an account is about to set up, as raw bytes. A fresh unconfirmed
 * key is handed back as it is, so reloading the setup screen does not change
 * the key the person may already have typed into their app; an absent or
 * stale one is replaced.
 *
 * @param int $user_id
 * @return string Raw key bytes; '' when the feature is unavailable.
 */
function pg_mfa_begin_setup($user_id)
{
    if (!pg_mfa_available()) {
        return '';
    }

    $row = pg_mfa_row($user_id);

    if (($row !== null)
        && ((string) $row['pending_secret'] !== '')
        && ((time() - (int) $row['pending_at']) <= pg_mfa_setup_lifetime())) {

        $secret = pg_mfa_secret_decode($row['pending_secret']);

        if ($secret !== '') {
            return $secret;
        }
    }

    $secret = pg_totp_secret();
    $blob = pg_mfa_secret_encode($secret);

    db("INSERT INTO user_mfa (user_id, pending_secret, pending_at)
        VALUES ('" . (int) $user_id . "', '" . e($blob) . "', UNIX_TIMESTAMP())
        ON DUPLICATE KEY UPDATE pending_secret = VALUES(pending_secret), pending_at = VALUES(pending_at)");

    return $secret;
}

/**
 * Confirm the key being set up with the first code the app shows. On success
 * the key becomes the account's second factor, a fresh set of recovery codes
 * replaces any earlier one, and the account's other remember-me tokens and
 * its API devices are revoked: they were issued without a second step.
 *
 * @param int    $user_id
 * @param string $code
 * @return string[]|false The recovery codes in plain text (shown once), or
 *                        false when the code was not accepted.
 */
function pg_mfa_confirm_setup($user_id, $code)
{
    if (!pg_mfa_available()) {
        return false;
    }

    $row = pg_mfa_row($user_id);

    if (($row === null) || ((string) $row['pending_secret'] === '')) {
        return false;
    }

    $secret = pg_mfa_secret_decode($row['pending_secret']);
    $step = pg_totp_verify($secret, preg_replace('/\s+/', '', (string) $code), time(), 0, 1);

    if ($step === false) {
        return false;
    }

    // The pending blob is already encrypted the way totp_secret is stored, so
    // it moves across as it is. enabled_at = 0 in the WHERE keeps two
    // confirmations racing from replacing a key that is already in use.
    db("UPDATE user_mfa SET
            totp_secret = pending_secret,
            enabled_at = UNIX_TIMESTAMP(),
            last_step = '" . (int) $step . "',
            pending_secret = '',
            pending_at = 0
        WHERE user_id = '" . (int) $user_id . "' AND enabled_at = 0 AND pending_secret != ''");

    if (mysqli_affected_rows(db::$con) !== 1) {
        return false;
    }

    $codes = pg_mfa_recovery_regenerate($user_id);

    pg_mfa_revoke_other_sessions($user_id);

    log_activity(lang('user turned on two-step verification'),
        (string) db_value("SELECT user_username FROM user WHERE user_id = '" . (int) $user_id . "'"));

    return $codes;
}

/**
 * Remove the account's second factor and recovery codes, and revoke its
 * other remember-me tokens and its API devices.
 *
 * @param int $user_id
 */
function pg_mfa_disable($user_id)
{
    if (!pg_mfa_table_exists()) {
        return;
    }

    db("DELETE FROM user_mfa WHERE user_id = '" . (int) $user_id . "'");
    db("DELETE FROM user_mfa_recovery WHERE user_id = '" . (int) $user_id . "'");

    pg_mfa_revoke_other_sessions($user_id);
}

/**
 * Revoke every remember-me token and API device of the account except the
 * token of the browser making this request, when that token is the
 * account's own.
 *
 * Revoking that one too and minting a fresh one (what a password change
 * does) races the browser's own requests in flight: one that still carries
 * the old cookie finds its token gone and ends the session - with the
 * recovery codes the account screen was about to show in it. The person
 * just proved the second factor in this browser, so its token stays.
 *
 * @param int $user_id
 */
function pg_mfa_revoke_other_sessions($user_id)
{
    $keep = '';

    if (isset($_COOKIE['software']['auth'])) {
        $parts = explode(':', (string) $_COOKIE['software']['auth'], 2);

        if (preg_match('/^[a-f0-9]{24}$/', (string) $parts[0])
            && ((int) db_value("SELECT user_id FROM auth_tokens WHERE selector = '" . e($parts[0]) . "'") === (int) $user_id)) {
            $keep = (string) $parts[0];
        }
    }

    if ($keep === '') {
        pg_auth_token_revoke_user($user_id);
        return;
    }

    db("DELETE FROM auth_tokens WHERE user_id = '" . (int) $user_id . "' AND selector != '" . e($keep) . "'");

    // Same as pg_auth_token_revoke_user(): devices signed in to the external
    // API are sessions of the same person.
    require_once(PG_FUNCTIONS_DIR . '/includes/api/devices.php');

    api_devices_revoke_user($user_id);
}

/**
 * Administrator reset and account deletion. Silent when the tables do not
 * exist yet.
 *
 * @param int $user_id
 */
function pg_mfa_reset($user_id)
{
    pg_mfa_disable($user_id);
}

/**
 * Check a code typed at the second step: six digits are a TOTP code,
 * anything that normalizes to eight characters is a recovery code.
 *
 * Both are consumed with a conditional UPDATE whose affected-row count
 * decides, so two requests racing with the same code cannot both pass: the
 * TOTP step only moves forward (last_step < S) and a recovery code is only
 * spent once (used_at = 0).
 *
 * @param int    $user_id
 * @param string $code
 * @return string|false 'totp', 'recovery' or false.
 */
function pg_mfa_verify_code($user_id, $code)
{
    if (!pg_mfa_enabled($user_id)) {
        return false;
    }

    $code = (string) $code;
    $digits = preg_replace('/\s+/', '', $code);

    if (preg_match('/^[0-9]{6}$/D', $digits)) {

        $row = pg_mfa_row($user_id);
        $secret = pg_mfa_secret_decode($row['totp_secret']);
        $step = pg_totp_verify($secret, $digits, time(), (int) $row['last_step'], 1);

        if ($step === false) {
            return false;
        }

        db("UPDATE user_mfa SET last_step = '" . (int) $step . "'
            WHERE user_id = '" . (int) $user_id . "' AND last_step < '" . (int) $step . "'");

        return (mysqli_affected_rows(db::$con) === 1) ? 'totp' : false;
    }

    if (strlen(pg_mfa_recovery_normalize($code)) !== 8) {
        return false;
    }

    db("UPDATE user_mfa_recovery SET used_at = UNIX_TIMESTAMP()
        WHERE user_id = '" . (int) $user_id . "'
        AND code_hash = '" . e(pg_mfa_recovery_hash($code)) . "'
        AND used_at = 0
        LIMIT 1");

    return (mysqli_affected_rows(db::$con) === 1) ? 'recovery' : false;
}

/**
 * Count one second-step attempt and say whether the account or the address
 * is over its allowance: 5 per account and 30 per address in 10 minutes.
 * Every call is an attempt, so it is called right before a code is checked
 * and nowhere else. A correct password does not reset the account counter,
 * or signing in again would be a fresh allowance of guesses. Fails open
 * where waf_rate is unavailable, like every other rate limit here.
 *
 * @param int $user_id
 * @return bool
 */
function pg_mfa_attempt_blocked($user_id)
{
    if (!function_exists('waf_rate_exceeded') || !isset(db::$con) || !db::$con) {
        return false;
    }

    if (waf_rate_exceeded((string) (int) $user_id, 'mfa-u', 5, 600)) {
        return true;
    }

    $ip = function_exists('waf_client_ip') ? waf_client_ip() : '';

    if (($ip !== '') && function_exists('waf_ip_subject')) {
        $ip = waf_ip_subject($ip);
    }

    return ($ip !== '') && waf_rate_exceeded($ip, 'mfa-ip', 30, 600);
}

/**
 * Forget the attempt counters after a correct code.
 *
 * @param int $user_id
 */
function pg_mfa_attempt_clear($user_id)
{
    if (!function_exists('waf_rate_clear') || !isset(db::$con) || !db::$con) {
        return;
    }

    waf_rate_clear((string) (int) $user_id, 'mfa-u');

    $ip = function_exists('waf_client_ip') ? waf_client_ip() : '';

    if (($ip !== '') && function_exists('waf_ip_subject')) {
        waf_rate_clear(waf_ip_subject($ip), 'mfa-ip');
    }
}

/**
 * Replace the account's recovery codes with a fresh set of ten.
 *
 * @param int $user_id
 * @return string[] The codes in plain text; only their hashes are stored.
 */
function pg_mfa_recovery_regenerate($user_id)
{
    $codes = pg_mfa_recovery_codes(10);

    db("DELETE FROM user_mfa_recovery WHERE user_id = '" . (int) $user_id . "'");

    $values = array();

    foreach ($codes as $code) {
        $values[] = "('" . (int) $user_id . "', '" . e(pg_mfa_recovery_hash($code)) . "', 0)";
    }

    db("INSERT INTO user_mfa_recovery (user_id, code_hash, used_at) VALUES " . implode(', ', $values));

    return $codes;
}

/**
 * How many unused recovery codes the account has.
 *
 * @param int $user_id
 * @return int
 */
function pg_mfa_recovery_remaining($user_id)
{
    if (!pg_mfa_table_exists()) {
        return 0;
    }

    return (int) db_value("SELECT COUNT(*) FROM user_mfa_recovery
        WHERE user_id = '" . (int) $user_id . "' AND used_at = 0");
}

/**
 * The issuer named in the key URI: the site title, or its host name.
 *
 * @return string
 */
function pg_mfa_issuer()
{
    if (defined('TITLE') && (trim((string) TITLE) !== '')) {
        return trim((string) TITLE);
    }

    return defined('HOSTNAME') ? (string) HOSTNAME : '';
}

// ── Account screen ──────────────────────────────────────────────────────────

/**
 * Where the account screen's two-step actions return to: the page that shows
 * pg_account_security_section(), which is the profile page.
 *
 * @return string
 */
function pg_mfa_account_url()
{
    $url = get_page_type_url('my account profile');

    return ($url !== '') ? $url : get_page_type_url('my account');
}

/**
 * The two-step verification part of the account security section. Plain
 * markup in the style of the rest of that section, which is printed on the
 * front-end profile page and in custom layouts alike. Every form posts to
 * account_security.php with pg_security_action.
 *
 * @param int    $user_id
 * @param int    $password_algo user.user_password_algo; 3 is a Google-only
 *                              account, which has no password to ask for.
 * @param string $action_url
 * @param string $token         The CSRF field.
 * @return string
 */
function pg_mfa_account_section($user_id, $password_algo, $action_url, $token)
{
    if (!pg_mfa_table_exists()) {
        return '';
    }

    $heading = '<div class="heading" style="margin:1.5em 0 10px">' . h(lang('Two-step verification')) . '</div>';
    $enabled = pg_mfa_enabled($user_id);

    // An operator signed in as this person sees the state and nothing to
    // press; account_security.php refuses the actions in that mode as well.
    if (!empty($_SESSION['software']['logged_in_as_different_user'])) {
        return $heading . '<p>' . h(lang(array(
            'string' => 'Two-step verification is {var:1}.',
            'vars'   => array($enabled ? lang('on') : lang('off'))))) . '</p>';
    }

    $available = pg_mfa_available();

    $form = function ($action, $inner) use ($action_url, $token) {
        return '<form method="post" action="' . h($action_url) . '" style="margin:0 0 1em" autocomplete="off">' . $token
            . '<input type="hidden" name="pg_security_action" value="' . h($action) . '"/>' . $inner . '</form>';
    };

    $code_field = function ($id, $label = '') {
        return '<label for="' . h($id) . '" style="display:block;margin-bottom:.25em">' . h(($label !== '') ? $label : lang('Code from your authenticator app')) . '</label>'
            . '<input type="text" id="' . h($id) . '" name="code" class="software_input_text" required="required" inputmode="numeric" autocomplete="one-time-code" maxlength="20" spellcheck="false" style="margin-bottom:.5em"/> ';
    };

    // Recovery codes just issued: shown until the person says they saved
    // them, so a reload does not lose them.
    $codes = $_SESSION['software']['mfa_codes_show'] ?? null;

    if (is_array($codes) && $codes) {
        $codes_text = implode("\n", $codes);

        return $heading
            . '<p>' . h(lang('Save your recovery codes')) . '. ' . h(lang('Each code signs you in once if you lose your phone. Keep them somewhere safe; they are not shown again.')) . '</p>'
            . '<pre style="padding:.75em 1em;border:1px solid #dadce0;border-radius:6px;letter-spacing:.08em">' . h($codes_text) . '</pre>'
            . '<p><a download="recovery-codes.txt" href="data:text/plain;charset=utf-8,' . h(rawurlencode($codes_text . "\n")) . '">' . h(lang('Download')) . '</a></p>'
            . $form('mfa_codes_seen', '<button type="submit" class="software_input_submit_primary">' . h(lang('I have saved them')) . '</button>');
    }

    if ($enabled) {
        $row = pg_mfa_row($user_id);

        $required_note = pg_mfa_required_for_user($user_id)
            ? ' ' . h(lang('Your role requires two-step verification; after you turn it off you will be asked to set it up again at your next sign-in.'))
            : '';

        $password_field = ((int) $password_algo !== 3)
            ? '<label for="pg_mfa_current_password" style="display:block;margin-bottom:.25em">' . h(lang('Current password')) . '</label>'
                . '<input type="password" id="pg_mfa_current_password" name="current_password" class="software_input_password" required="required" autocomplete="current-password" style="margin-bottom:.5em"/><br/>'
            : '';

        // Without a readable key (ENCRYPTION_KEY or openssl gone) no TOTP
        // code can be checked: new recovery codes, which need one, are not
        // offered, and turning it off takes a recovery code instead.
        $unreadable_note = $available
            ? ''
            : '<p><strong>' . h(lang('The site cannot read authenticator keys right now; use one of your recovery codes, or ask the site owner.')) . '</strong></p>';

        $regenerate_html = $available
            ? '<p style="margin-bottom:.5em"><strong>' . h(lang('New recovery codes')) . '</strong></p>'
                . $form('mfa_recovery_regenerate', $code_field('pg_mfa_regenerate_code')
                    . '<button type="submit" class="software_input_submit_secondary">' . h(lang('New recovery codes')) . '</button>')
            : '';

        $disable_code_label = $available ? '' : lang('Code from your authenticator app or a recovery code');

        return $heading
            . '<p>' . h(lang(array(
                'string' => 'Two-step verification is on since {var:1}. {var:2} recovery codes left.',
                'vars'   => array(
                    // get_absolute_time() wraps the date in a <time> element;
                    // the sentence is escaped as a whole, so only the text goes in.
                    strip_tags(get_absolute_time(array('timestamp' => (int) $row['enabled_at'], 'type' => 'date'))),
                    (string) pg_mfa_recovery_remaining($user_id))))) . '</p>'
            . $unreadable_note
            . $regenerate_html
            . '<p style="margin-bottom:.5em"><strong>' . h(lang('Turn off')) . '</strong></p>'
            . '<p>' . h(lang('Turning it off also signs out your other devices.')) . $required_note . '</p>'
            . $form('mfa_disable', $password_field . $code_field('pg_mfa_disable_code', $disable_code_label)
                . '<button type="submit" class="software_input_submit_secondary">' . h(lang('Turn off')) . '</button>');
    }

    if (!$available) {
        return $heading . '<p>' . h(lang('Two-step verification cannot be turned on because the site has no encryption key. Please ask the site owner.')) . '</p>';
    }

    $row = pg_mfa_row($user_id);

    $pending_fresh = ($row !== null)
        && ((string) $row['pending_secret'] !== '')
        && ((time() - (int) $row['pending_at']) <= pg_mfa_setup_lifetime());

    if ($pending_fresh) {
        $secret = pg_mfa_secret_decode($row['pending_secret']);
        $account = (string) db_value("SELECT user_email FROM user WHERE user_id = '" . (int) $user_id . "'");
        $key_uri = pg_totp_uri(pg_mfa_issuer(), ($account !== '') ? $account : (string) ($_SESSION['sessionusername'] ?? ''), $secret);

        // Inline SVG keeps the key out of any image URL; without a code the
        // key is still there to type in.
        $qr = pg_qr_svg($key_uri, 192, array('label' => lang('QR code for the authenticator app')));

        $key_intro = ($qr !== '')
            ? '<p>' . h(lang('Scan the QR code with your authenticator app (Google Authenticator, Aegis, 1Password, Microsoft Authenticator), then enter the code it shows.')) . '</p>'
                . '<div style="margin:0 0 1em">' . $qr . '</div>'
                . '<p>' . h(lang('If you cannot scan it, add the key by hand:')) . '</p>'
            : '<p>' . h(lang('Add the key to your authenticator app by hand (Google Authenticator, Aegis, 1Password, Microsoft Authenticator), then enter the code it shows.')) . '</p>';

        return $heading
            . $key_intro
            . '<p><code id="pg_mfa_key" style="font-size:1.15em;user-select:all">' . h(pg_mfa_format_secret($secret)) . '</code></p>'
            . '<p><input type="text" id="pg_mfa_uri" class="software_input_text" readonly="readonly" style="width:100%" onclick="this.select()"'
                . ' value="' . h($key_uri) . '"'
                . ' aria-label="' . h(lang('Key link')) . '"/></p>'
            . $form('mfa_confirm', $code_field('pg_mfa_confirm_code')
                . '<button type="submit" class="software_input_submit_primary">' . h(lang('Confirm and turn on')) . '</button>')
            . $form('mfa_cancel_setup', '<button type="submit" class="software_input_submit_secondary">' . h(lang('Cancel')) . '</button>');
    }

    return $heading
        . '<p>' . h(lang('Add a second step to your sign-in: after your password, a code from an authenticator app on your phone.')) . '</p>'
        . $form('mfa_begin', '<button type="submit" class="software_input_submit_primary">' . h(lang('Turn on two-step verification')) . '</button>');
}
