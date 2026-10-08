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

// The primitives below touch neither the database nor the session, so they
// are covered by tests/mfa_test.php. Secrets are passed around as raw bytes;
// base32 is only the shape a person types into an app.
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
