<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The CBOR reader (RFC 8949) and the base64url helpers of the WebAuthn
 * verifier (includes/webauthn/webauthn.php).
 *
 * Only what a WebAuthn response carries is read: unsigned and negative
 * integers, byte and text strings, arrays, maps, false, true, null, undefined
 * and floats. Authenticators write length-prefixed items (CTAP2 canonical
 * CBOR), so indefinite lengths, tags and the other simple values are refused
 * rather than half supported, and so are lengths past the end of the input,
 * nesting deeper than PG_WEBAUTHN_CBOR_MAX_DEPTH, arrays and maps of more
 * than PG_WEBAUTHN_CBOR_MAX_ITEMS entries, text that is not UTF-8 and map
 * keys that are neither integers nor text.
 *
 * Decoded values: integers as int, text as string, byte strings as
 * PgWebAuthnBytes, arrays as PgWebAuthnList, maps as PHP arrays keyed by
 * their integer or text keys, false and true as bool, null and undefined as
 * null, floats as float. Each CBOR type keeps its own PHP shape because the
 * formats tell them apart: "fmt" is text and "authData" bytes, "attStmt" a
 * map and "x5c" an array, and in a PHP array an empty map, an empty list and
 * {0: x} against [x] would read the same. A text key PHP would store as an
 * integer key ("3") is refused, as is a key that appears twice: a COSE label
 * is the integer 3, and the text "3" must not be read as that label.
 *
 * Errors are thrown as PgWebAuthnException with a short code as the message;
 * the public functions in webauthn.php catch them and answer
 * array('ok' => false, 'error' => <code>). Nothing here writes a PHP warning:
 * every read is checked against the length first, because the input comes
 * from the browser.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// Deepest nesting read. A credential public key sits two levels down in an
// attestation object; extension outputs add a few more.
if (!defined('PG_WEBAUTHN_CBOR_MAX_DEPTH')) {
    define('PG_WEBAUTHN_CBOR_MAX_DEPTH', 16);
}

// Most entries one array or map may have. WebAuthn structures have a handful
// (a TPM statement six, a certificate chain a few); the cap keeps a map of
// colliding integer keys, which PHP stores in one hash bucket, from turning
// the reading of 4 KB of authenticator data into seconds of work.
if (!defined('PG_WEBAUTHN_CBOR_MAX_ITEMS')) {
    define('PG_WEBAUTHN_CBOR_MAX_ITEMS', 64);
}

/**
 * A failed check. The message is the error code the caller reports
 * ('cbor', 'auth_data', 'signature' ...).
 */
class PgWebAuthnException extends Exception
{
}

/**
 * A CBOR byte string, kept apart from text strings.
 */
final class PgWebAuthnBytes
{
    /** @var string the raw bytes */
    public $value;

    /**
     * @param string $value
     */
    public function __construct($value)
    {
        $this->value = (string) $value;
    }
}

/**
 * A CBOR array, kept apart from maps (which are the PHP arrays).
 */
final class PgWebAuthnList
{
    /** @var array the items, in order, keyed 0 to n - 1 */
    public $items;

    /**
     * @param array $items
     */
    public function __construct($items)
    {
        $this->items = array_values((array) $items);
    }
}

/**
 * Stops the check that is running with an error code.
 *
 * @param string $code
 * @throws PgWebAuthnException always
 */
function pg_webauthn_fail($code)
{
    throw new PgWebAuthnException((string) $code);
}

/**
 * Base64url without padding (RFC 4648 section 5), the encoding WebAuthn uses
 * for every binary value in JSON.
 *
 * @param string $bytes
 * @return string
 */
function pg_webauthn_base64url_encode($bytes)
{
    return rtrim(strtr(base64_encode((string) $bytes), '+/', '-_'), '=');
}

/**
 * Bytes from base64url text. Trailing padding is accepted; any other
 * character outside the alphabet, an impossible length, or an encoding that
 * is not the canonical one for its bytes (stray low bits in the last
 * character) gives false, so one value has exactly one spelling.
 *
 * @param mixed $text
 * @return string|false
 */
function pg_webauthn_base64url_decode($text)
{
    if (!is_string($text)) {
        return false;
    }

    $text = rtrim($text, '=');

    if ((preg_match('/^[A-Za-z0-9_-]*$/', $text) !== 1) || ((strlen($text) % 4) === 1)) {
        return false;
    }

    $bytes = base64_decode(strtr($text, '-_', '+/') . str_repeat('=', (4 - (strlen($text) % 4)) % 4), true);

    if (!is_string($bytes) || (pg_webauthn_base64url_encode($bytes) !== $text)) {
        return false;
    }

    return $bytes;
}

/**
 * One complete CBOR item: bytes left over after it are an error.
 *
 * @param string $data
 * @param string $error code thrown when the input is not acceptable CBOR
 * @return mixed
 * @throws PgWebAuthnException
 */
function pg_webauthn_cbor_decode($data, $error = 'cbor')
{
    $data = (string) $data;
    $offset = 0;
    $value = pg_webauthn_cbor_read($data, $offset, $error);

    if ($offset !== strlen($data)) {
        pg_webauthn_fail($error);
    }

    return $value;
}

/**
 * Reads one item at $offset and moves $offset past it. The authenticator data
 * needs this form: its credential public key is a CBOR item followed by more
 * bytes, and only the reader knows where the item ends.
 *
 * @param string $data
 * @param int    $offset
 * @param string $error
 * @param int    $depth nesting level of this item
 * @return mixed
 * @throws PgWebAuthnException
 */
function pg_webauthn_cbor_read($data, &$offset, $error = 'cbor', $depth = 0)
{
    $length = strlen($data);

    if (($depth > PG_WEBAUTHN_CBOR_MAX_DEPTH) || ($offset < 0) || ($offset >= $length)) {
        pg_webauthn_fail($error);
    }

    $initial = ord($data[$offset]);
    $offset++;

    $major = $initial >> 5;
    $info = $initial & 0x1f;

    if ($major === 7) {
        return pg_webauthn_cbor_simple($data, $offset, $info, $error);
    }

    // Major type 6 (tags) never appears in a WebAuthn structure.
    if ($major === 6) {
        pg_webauthn_fail($error);
    }

    $argument = pg_webauthn_cbor_argument($data, $offset, $info, $error);

    switch ($major) {

        case 0:
            return $argument;

        case 1:
            // -1 - n; n is at most PHP_INT_MAX, so this stays an integer.
            return -1 - $argument;

        case 2:
            return new PgWebAuthnBytes(pg_webauthn_cbor_take($data, $offset, $argument, $error));

        case 3:
            $text = pg_webauthn_cbor_take($data, $offset, $argument, $error);

            if (preg_match('//u', $text) !== 1) {
                pg_webauthn_fail($error);
            }

            return $text;

        case 4:
            // Every item takes at least one byte: a count larger than what is
            // left cannot be honest, and must not drive a long loop.
            if (($argument > PG_WEBAUTHN_CBOR_MAX_ITEMS) || ($argument > ($length - $offset))) {
                pg_webauthn_fail($error);
            }

            $items = array();

            for ($i = 0; $i < $argument; $i++) {
                $items[] = pg_webauthn_cbor_read($data, $offset, $error, $depth + 1);
            }

            return new PgWebAuthnList($items);

        default:
            // Major type 5, a map: two items per entry.
            if (($argument > PG_WEBAUTHN_CBOR_MAX_ITEMS) || ($argument > intdiv($length - $offset, 2))) {
                pg_webauthn_fail($error);
            }

            $map = array();

            for ($i = 0; $i < $argument; $i++) {

                $key = pg_webauthn_cbor_read($data, $offset, $error, $depth + 1);

                if (is_string($key)) {
                    // PHP keeps "3" as the integer key 3; such a text key
                    // would be indistinguishable from the integer label.
                    if (is_numeric($key) && ((string) (int) $key === $key)) {
                        pg_webauthn_fail($error);
                    }
                } elseif (!is_int($key)) {
                    pg_webauthn_fail($error);
                }

                if (array_key_exists($key, $map)) {
                    pg_webauthn_fail($error);
                }

                $map[$key] = pg_webauthn_cbor_read($data, $offset, $error, $depth + 1);

            }

            return $map;

    }
}

/**
 * The argument of an initial byte: the value itself below 24, otherwise the
 * 1, 2, 4 or 8 bytes that follow (big-endian). Indefinite lengths (31) and
 * the reserved values (28-30) are refused, and so is an 8-byte value beyond
 * PHP_INT_MAX.
 *
 * @param string $data
 * @param int    $offset
 * @param int    $info
 * @param string $error
 * @return int
 * @throws PgWebAuthnException
 */
function pg_webauthn_cbor_argument($data, &$offset, $info, $error)
{
    if ($info < 24) {
        return $info;
    }

    switch ($info) {

        case 24:
            return ord(pg_webauthn_cbor_take($data, $offset, 1, $error));

        case 25:
            $value = unpack('n', pg_webauthn_cbor_take($data, $offset, 2, $error));
            return $value[1];

        case 26:
            $value = pg_webauthn_uint32(pg_webauthn_cbor_take($data, $offset, 4, $error));

            // Only 32-bit PHP gets a float here (2^31 and up).
            if (!is_int($value)) {
                pg_webauthn_fail($error);
            }

            return $value;

        case 27:
            $bytes = pg_webauthn_cbor_take($data, $offset, 8, $error);
            $high = pg_webauthn_uint32(substr($bytes, 0, 4));
            $low = pg_webauthn_uint32(substr($bytes, 4, 4));

            if (PHP_INT_SIZE < 8) {
                if (($high > 0) || !is_int($low)) {
                    pg_webauthn_fail($error);
                }

                return $low;
            }

            if ($high > 0x7fffffff) {
                pg_webauthn_fail($error);
            }

            return ($high << 32) | $low;

    }

    pg_webauthn_fail($error);
}

/**
 * Major type 7: false, true, null, undefined and the three float widths.
 *
 * @param string $data
 * @param int    $offset
 * @param int    $info
 * @param string $error
 * @return bool|float|null
 * @throws PgWebAuthnException
 */
function pg_webauthn_cbor_simple($data, &$offset, $info, $error)
{
    switch ($info) {

        case 20:
            return false;

        case 21:
            return true;

        case 22:
        case 23:
            return null;

        case 25:
            return pg_webauthn_cbor_half(pg_webauthn_cbor_take($data, $offset, 2, $error));

        case 26:
            $value = unpack('f', pg_webauthn_machine_order(pg_webauthn_cbor_take($data, $offset, 4, $error)));
            return (float) $value[1];

        case 27:
            $value = unpack('d', pg_webauthn_machine_order(pg_webauthn_cbor_take($data, $offset, 8, $error)));
            return (float) $value[1];

    }

    // Simple values 0-19 and 24, the reserved 28-30 and the "break" byte.
    pg_webauthn_fail($error);
}

/**
 * An IEEE 754 half-precision float (RFC 8949 Appendix D).
 *
 * @param string $bytes two bytes, big-endian
 * @return float
 */
function pg_webauthn_cbor_half($bytes)
{
    $half = (ord($bytes[0]) << 8) | ord($bytes[1]);
    $exponent = ($half >> 10) & 0x1f;
    $mantissa = $half & 0x3ff;

    if ($exponent === 0) {
        $value = $mantissa * pow(2, -24);
    } elseif ($exponent !== 31) {
        $value = ($mantissa + 1024) * pow(2, $exponent - 25);
    } else {
        $value = ($mantissa === 0) ? INF : NAN;
    }

    return ($half & 0x8000) ? -$value : (float) $value;
}

/**
 * $count bytes at $offset, moving $offset past them; fewer left is an error.
 *
 * @param string $data
 * @param int    $offset
 * @param int    $count
 * @param string $error
 * @return string
 * @throws PgWebAuthnException
 */
function pg_webauthn_cbor_take($data, &$offset, $count, $error)
{
    if (($count < 0) || ($count > (strlen($data) - $offset))) {
        pg_webauthn_fail($error);
    }

    $bytes = (string) substr($data, $offset, $count);
    $offset += $count;

    return $bytes;
}

/**
 * An unsigned 32-bit big-endian integer. unpack('N') answers a negative
 * number on 32-bit PHP for values from 2^31; this gives the value itself
 * (a float there, an int on 64-bit PHP).
 *
 * @param string $bytes four bytes
 * @return int|float
 */
function pg_webauthn_uint32($bytes)
{
    $value = unpack('N', $bytes);
    $value = $value[1];

    return ($value < 0) ? ($value + 4294967296) : $value;
}

/**
 * Big-endian bytes of a float in the order unpack('f') and unpack('d')
 * read on this machine. The explicit big-endian codes ('G', 'E') arrived in
 * PHP 7.1.1, after the oldest version the software supports.
 *
 * @param string $bytes
 * @return string
 */
function pg_webauthn_machine_order($bytes)
{
    static $little_endian = null;

    if ($little_endian === null) {
        $little_endian = (pack('L', 1) === "\x01\x00\x00\x00");
    }

    return $little_endian ? strrev($bytes) : $bytes;
}
