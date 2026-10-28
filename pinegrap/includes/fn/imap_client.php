<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// A module of functions.php: a small IMAP4rev1 client over a TLS socket.
//
// Loaded only where a mailbox is read (require_once), never on every request.
// PHP's imap extension is not used: it left the core in PHP 8.4 and many hosts
// never had it. The client speaks just what reading a mailbox needs: the
// greeting, CAPABILITY, AUTHENTICATE PLAIN or LOGIN, SELECT / EXAMINE,
// UID SEARCH, UID FETCH, UID STORE, UID MOVE (or COPY with \Deleted) and
// LOGOUT, over implicit TLS (port 993) with the certificate always checked.
//
// The protocol half is pure: the tokenizer and the response parsers read the
// wire text, literals included, so they are tested with fixtures. A session
// is an array holding an input and an output stream; a test gives it two
// memory streams, a real run one socket for both.
//
// Errors are short codes ('connect', 'tls', 'timeout', 'closed', 'auth',
// 'auth_disabled', 'folder', 'refused', 'protocol', 'too_large') with the
// server's own words beside them as 'detail'; the caller turns them into text.
// A password is never put into an error or a detail.
if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/** The longest literal a session reads, in bytes: a 25 MB mail and its headers. */
define('PG_IMAP_MAX_LITERAL', 27262976);

/** The longest response line a session reads, literals not counted, in bytes. */
define('PG_IMAP_MAX_LINE', 1048576);

/**
 * A session over two streams (one socket in a real run).
 *
 * @param resource $in
 * @param resource $out
 * @param array    $options budget (seconds the whole session may take, default 15)
 * @return array
 */
function pg_imap_session($in, $out, $options = array())
{
    return array(
        'in'           => $in,
        'out'          => $out,
        'tag'          => 0,
        'deadline'     => microtime(true) + (float) ($options['budget'] ?? 15),
        'capabilities' => array(),
        'error'        => '',
        'detail'       => '',
    );
}

/**
 * Connects to a server over implicit TLS and reads its greeting.
 *
 * The host is resolved once and checked like every address the operator
 * types (api_http_check_url(): no loopback, private or metadata address),
 * then the socket is opened to that address with the host name as the
 * certificate's expected name, so a second lookup cannot point elsewhere.
 *
 * @param string $host
 * @param int    $port
 * @param array  $options timeout (seconds per read, default 10), budget
 * @return array ok, error, detail, session
 */
function pg_imap_connect($host, $port, $options = array())
{
    $host = strtolower(trim((string) $host));
    $port = (int) $port;
    $timeout = (int) ($options['timeout'] ?? 10);
    $fail = function ($error, $detail = '') {
        return array('ok' => false, 'error' => $error, 'detail' => pg_imap_clean_text($detail), 'session' => null);
    };

    if (!pg_imap_host_valid($host) || ($port < 1) || ($port > 65535)) {
        return $fail('connect', 'Not a host name.');
    }

    if (!function_exists('stream_socket_client') || !extension_loaded('openssl')) {
        return $fail('tls', 'PHP has no OpenSSL.');
    }

    if (!function_exists('api_http_check_url')) {
        require_once(PG_FUNCTIONS_DIR . '/includes/api/outbound/http.php');
    }

    $check = api_http_check_url('https://' . $host . ':' . $port . '/');

    if (!$check['ok']) {
        return $fail('connect', (string) $check['error']);
    }

    $ssl = array(
        'verify_peer'       => true,
        'verify_peer_name'  => true,
        'allow_self_signed' => false,
        'SNI_enabled'       => true,
        'peer_name'         => $host,
    );

    if (defined('CURL_CA_BUNDLE') && (CURL_CA_BUNDLE !== '') && is_file(CURL_CA_BUNDLE)) {
        $ssl['cafile'] = CURL_CA_BUNDLE;
    }

    $address = filter_var($check['ip'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) ? '[' . $check['ip'] . ']' : $check['ip'];
    $errno = 0;
    $errstr = '';
    error_clear_last();
    $socket = @stream_socket_client('ssl://' . $address . ':' . $port, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT,
        stream_context_create(array('ssl' => $ssl)));

    if (!$socket) {
        // A refused certificate shows only in the warning the @ kept quiet.
        $last = error_get_last();
        $said = trim($errstr . ' ' . (is_array($last) ? (string) $last['message'] : ''));

        return $fail(pg_imap_connect_error($errno, $said), $said);
    }

    stream_set_timeout($socket, $timeout);

    $session = pg_imap_session($socket, $socket, $options);

    if (!pg_imap_greeting($session)) {
        fclose($socket);
        return $fail($session['error'], $session['detail']);
    }

    return array('ok' => true, 'error' => '', 'detail' => '', 'session' => $session);
}

/**
 * Which failure a refused connection was. The words also name the
 * "ssl://" address, so the TLS words looked for are the handshake's own.
 *
 * @param int    $errno
 * @param string $said errstr and the warning
 * @return string timeout | tls | connect
 */
function pg_imap_connect_error($errno, $said)
{
    if (((int) $errno === 110) || preg_match('/timed? ?out/i', (string) $said)) {
        return 'timeout';
    }

    if (preg_match('/certificate|SSL operation failed|handshake|crypto|verify/i', (string) $said)) {
        return 'tls';
    }

    return 'connect';
}

/**
 * Is this a host name or address a mailbox may name?
 *
 * @param string $host
 * @return bool
 */
function pg_imap_host_valid($host)
{
    $host = (string) $host;

    if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
        return true;
    }

    return (strlen($host) <= 253) && (bool) preg_match('/^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)+$/', $host);
}

/**
 * Reads the server's greeting: OK (capabilities kept when it names them),
 * PREAUTH is refused (a session nobody signed into is not this box's), BYE
 * is the server turning us away.
 *
 * @param array $session
 * @return bool
 */
function pg_imap_greeting(&$session)
{
    $raw = pg_imap_read_line($session);

    if ($raw === null) {
        return false;
    }

    $reply = pg_imap_parse_untagged($raw);

    if ($reply['type'] !== 'OK') {
        return pg_imap_fail($session, ($reply['type'] === 'BYE') ? 'refused' : 'protocol', $reply['text']);
    }

    if (($reply['code'] !== null) && ($reply['code']['name'] === 'CAPABILITY')) {
        $session['capabilities'] = pg_imap_capability_list($reply['code']['args']);
    }

    return true;
}

/**
 * Marks the session broken.
 *
 * @param array  $session
 * @param string $error
 * @param string $detail
 * @return false
 */
function pg_imap_fail(&$session, $error, $detail = '')
{
    if ($session['error'] === '') {
        $session['error'] = $error;
        $session['detail'] = pg_imap_clean_text($detail);
    }

    return false;
}

/**
 * Server words fit for a screen: no control characters, at most 200 bytes.
 *
 * @param string $text
 * @return string
 */
function pg_imap_clean_text($text)
{
    $text = trim(preg_replace('/[\x00-\x1F\x7F]+/', ' ', (string) $text));

    if (strlen($text) > 200) {
        $text = substr($text, 0, 200);
        // Never leave half a UTF-8 character at the cut.
        $text = preg_replace('/[\xC0-\xFF][\x80-\xBF]*$/', '', $text);
    }

    return $text;
}

/**
 * One whole response line as it came, with every literal it announced
 * ({N} at a line's end, then N bytes) read into it.
 *
 * @param array $session
 * @return string|null null once the session failed
 */
function pg_imap_read_line(&$session)
{
    if ($session['error'] !== '') {
        return null;
    }

    $raw = '';
    $line_bytes = 0;

    while (true) {
        $line = pg_imap_read_raw_line($session);

        if ($line === null) {
            return null;
        }

        $raw .= $line;
        $line_bytes += strlen($line);

        if ($line_bytes > PG_IMAP_MAX_LINE) {
            pg_imap_fail($session, 'protocol', 'Response line too long.');
            return null;
        }

        if (!preg_match('/\{(\d+)\+?\}\r?\n$/', $line, $match)) {
            return $raw;
        }

        $size = (int) $match[1];

        if ($size > PG_IMAP_MAX_LITERAL) {
            pg_imap_fail($session, 'too_large');
            return null;
        }

        $literal = pg_imap_read_bytes($session, $size);

        if ($literal === null) {
            return null;
        }

        $raw .= $literal;
    }
}

/**
 * One line up to and with its line feed.
 *
 * @param array $session
 * @return string|null
 */
function pg_imap_read_raw_line(&$session)
{
    $line = '';

    while (true) {
        if (microtime(true) > $session['deadline']) {
            pg_imap_fail($session, 'timeout');
            return null;
        }

        $part = @fgets($session['in'], 65536);

        if (($part === false) || ($part === '')) {
            pg_imap_fail($session, pg_imap_stream_timed_out($session['in']) ? 'timeout' : 'closed');
            return null;
        }

        $line .= $part;

        if (substr($line, -1) === "\n") {
            return $line;
        }

        if (strlen($line) > PG_IMAP_MAX_LINE) {
            pg_imap_fail($session, 'protocol', 'Response line too long.');
            return null;
        }
    }
}

/**
 * Exactly $size bytes.
 *
 * @param array $session
 * @param int   $size
 * @return string|null
 */
function pg_imap_read_bytes(&$session, $size)
{
    $bytes = '';

    while (strlen($bytes) < $size) {
        if (microtime(true) > $session['deadline']) {
            pg_imap_fail($session, 'timeout');
            return null;
        }

        $part = @fread($session['in'], min(65536, $size - strlen($bytes)));

        if (($part === false) || ($part === '')) {
            pg_imap_fail($session, pg_imap_stream_timed_out($session['in']) ? 'timeout' : 'closed');
            return null;
        }

        $bytes .= $part;
    }

    return $bytes;
}

/**
 * @param resource $stream
 * @return bool
 */
function pg_imap_stream_timed_out($stream)
{
    $meta = @stream_get_meta_data($stream);

    return is_array($meta) && !empty($meta['timed_out']);
}

/**
 * @param array  $session
 * @param string $bytes
 * @return bool
 */
function pg_imap_write(&$session, $bytes)
{
    if ($session['error'] !== '') {
        return false;
    }

    $length = strlen($bytes);
    $written = 0;

    while ($written < $length) {
        $count = @fwrite($session['out'], substr($bytes, $written));

        if (!$count) {
            return pg_imap_fail($session, 'closed');
        }

        $written += $count;
    }

    return true;
}

/**
 * Sends one command and reads its response.
 *
 * $parts are joined by spaces; a part given as array('literal' => bytes) is
 * sent as a literal: the size, then (unless the server has LITERAL+) the
 * server's go-ahead, then the bytes.
 *
 * @param array $session
 * @param array $parts strings, or literals
 * @return array ok, status (OK | NO | BAD | ''), code, text, untagged (parsed)
 */
function pg_imap_command(&$session, $parts)
{
    $tag = 'A' . str_pad((string) (++$session['tag']), 4, '0', STR_PAD_LEFT);
    $plus = in_array('LITERAL+', $session['capabilities'], true);
    $pending = $tag;

    foreach ((array) $parts as $part) {
        if (is_array($part)) {
            $bytes = (string) $part['literal'];
            $pending .= ' {' . strlen($bytes) . ($plus ? '+' : '') . "}\r\n";

            if (!pg_imap_write($session, $pending)) {
                return pg_imap_broken($session);
            }

            if (!$plus) {
                $reply = pg_imap_read_response($session, $tag, true);

                if (!$reply['continue']) {
                    return $reply;
                }
            }

            $pending = $bytes;
            continue;
        }

        $pending .= ' ' . $part;
    }

    if (!pg_imap_write($session, $pending . "\r\n")) {
        return pg_imap_broken($session);
    }

    return pg_imap_read_response($session, $tag, false);
}

/**
 * The answer of a command on a broken session.
 *
 * @param array $session
 * @return array
 */
function pg_imap_broken($session)
{
    return array('ok' => false, 'status' => '', 'code' => null, 'text' => $session['detail'], 'untagged' => array(), 'continue' => false);
}

/**
 * Reads untagged lines until the tagged one, or (when $continue is true)
 * until the server's "+" go-ahead.
 *
 * @param array  $session
 * @param string $tag
 * @param bool   $continue
 * @return array ok, status, code, text, untagged, continue
 */
function pg_imap_read_response(&$session, $tag, $continue)
{
    $untagged = array();

    while (true) {
        $raw = pg_imap_read_line($session);

        if ($raw === null) {
            $reply = pg_imap_broken($session);
            $reply['untagged'] = $untagged;
            return $reply;
        }

        if (strncmp($raw, '+', 1) === 0) {
            if ($continue) {
                return array('ok' => true, 'status' => '', 'code' => null, 'text' => pg_imap_clean_text(substr($raw, 1)), 'untagged' => $untagged, 'continue' => true);
            }

            // A go-ahead nobody asked for: the command can never finish.
            pg_imap_fail($session, 'protocol', 'Unexpected continuation.');
            continue;
        }

        if (strncmp($raw, '* ', 2) === 0) {
            $parsed = pg_imap_parse_untagged($raw);
            $untagged[] = $parsed;

            if ($parsed['type'] === 'BYE') {
                // The server closes after BYE; the tagged line may still come.
                $session['bye'] = $parsed['text'];
            }

            continue;
        }

        $status = pg_imap_parse_tagged($raw);

        if ($status['tag'] !== $tag) {
            pg_imap_fail($session, 'protocol', 'Unexpected tag.');
            continue;
        }

        if (($status['code'] !== null) && ($status['code']['name'] === 'CAPABILITY')) {
            $session['capabilities'] = pg_imap_capability_list($status['code']['args']);
        }

        foreach ($untagged as $line) {
            if ($line['type'] === 'CAPABILITY') {
                $session['capabilities'] = pg_imap_capability_list($line['text']);
            }
        }

        return array(
            'ok'       => ($status['status'] === 'OK'),
            'status'   => $status['status'],
            'code'     => $status['code'],
            'text'     => $status['text'],
            'untagged' => $untagged,
            'continue' => false,
        );
    }
}

/**
 * A tagged status line: "A0001 OK [CODE args] text".
 *
 * @param string $raw
 * @return array tag, status (OK | NO | BAD, '' for anything else), code, text
 */
function pg_imap_parse_tagged($raw)
{
    $line = rtrim((string) $raw, "\r\n");
    $parts = explode(' ', $line, 3);
    $status = strtoupper($parts[1] ?? '');
    $rest = pg_imap_parse_code($parts[2] ?? '');

    return array(
        'tag'    => $parts[0],
        'status' => in_array($status, array('OK', 'NO', 'BAD'), true) ? $status : '',
        'code'   => $rest['code'],
        'text'   => $rest['text'],
    );
}

/**
 * A response code at the front of a status text: "[UIDVALIDITY 3857529045] UIDs valid".
 *
 * @param string $text
 * @return array code (null, or name and args), text
 */
function pg_imap_parse_code($text)
{
    $text = (string) $text;

    if (!preg_match('/^\[([^\]]*)\]\s*(.*)$/s', $text, $match)) {
        return array('code' => null, 'text' => pg_imap_clean_text($text));
    }

    $inside = explode(' ', trim($match[1]), 2);

    return array(
        'code' => array('name' => strtoupper($inside[0]), 'args' => (string) ($inside[1] ?? '')),
        'text' => pg_imap_clean_text($match[2]),
    );
}

/**
 * An untagged line: "* 23 EXISTS", "* OK [UIDNEXT 42] ...", "* SEARCH 2 7",
 * "* 3 FETCH (UID 7 BODY[] {N}...)", "* CAPABILITY IMAP4rev1 ...".
 *
 * @param string $raw
 * @return array type (upper case), number (int|null), code, text (rest of
 *               the line; the status words for OK / NO / BAD / BYE),
 *               data (FETCH: item map; SEARCH: numbers)
 */
function pg_imap_parse_untagged($raw)
{
    $line = (string) $raw;

    if (substr($line, -2) === "\r\n") {
        $line = substr($line, 0, -2);
    } elseif (substr($line, -1) === "\n") {
        $line = substr($line, 0, -1);
    }

    $line = (string) substr($line, 2);
    $number = null;

    if (preg_match('/^(\d+) /', $line, $match)) {
        $number = (int) $match[1];
        $line = (string) substr($line, strlen($match[0]));
    }

    $space = strpos($line, ' ');
    $type = strtoupper(($space === false) ? $line : substr($line, 0, $space));
    $rest = ($space === false) ? '' : (string) substr($line, $space + 1);
    $out = array('type' => $type, 'number' => $number, 'code' => null, 'text' => '', 'data' => null);

    if (in_array($type, array('OK', 'NO', 'BAD', 'BYE', 'PREAUTH'), true)) {
        $parsed = pg_imap_parse_code($rest);
        $out['code'] = $parsed['code'];
        $out['text'] = $parsed['text'];
    } elseif ($type === 'FETCH') {
        $tokens = pg_imap_tokenize($rest);
        $out['data'] = pg_imap_fetch_map(is_array($tokens[0] ?? null) ? $tokens[0] : array());
    } elseif ($type === 'SEARCH') {
        $out['data'] = array();

        foreach (preg_split('/\s+/', trim($rest)) as $value) {
            if (ctype_digit($value)) {
                $out['data'][] = (int) $value;
            }
        }
    } else {
        $out['text'] = pg_imap_clean_text($rest);
    }

    return $out;
}

/**
 * The upper-cased words of a capability list.
 *
 * @param string $text
 * @return string[]
 */
function pg_imap_capability_list($text)
{
    $out = array();

    foreach (preg_split('/\s+/', strtoupper(trim((string) $text))) as $word) {
        if ($word !== '') {
            $out[] = $word;
        }
    }

    return $out;
}

/**
 * Splits IMAP data into tokens: an atom or a quoted string or a literal as a
 * string, NIL as null, a parenthesised list as an array. Section brackets
 * belong to their atom, spaces inside them included ("BODY[HEADER.FIELDS
 * (MESSAGE-ID)]"). Broken input ends the list; it never raises.
 *
 * @param string   $text
 * @param int|null $pos  where to start; left after the last token read
 * @param int      $depth
 * @return array
 */
function pg_imap_tokenize($text, &$pos = null, $depth = 0)
{
    $text = (string) $text;
    $pos = (int) $pos;
    $length = strlen($text);
    $tokens = array();

    while ($pos < $length) {
        $char = $text[$pos];

        if (($char === ' ') || ($char === "\r") || ($char === "\n")) {
            $pos++;
            continue;
        }

        if ($char === '(') {
            $pos++;

            if ($depth >= 20) {
                return $tokens;
            }

            $tokens[] = pg_imap_tokenize($text, $pos, $depth + 1);
            continue;
        }

        if ($char === ')') {
            $pos++;

            if ($depth > 0) {
                return $tokens;
            }

            continue;
        }

        if ($char === '"') {
            $value = '';
            $pos++;

            while ($pos < $length) {
                $char = $text[$pos];

                if ($char === '\\' && ($pos + 1 < $length)) {
                    $value .= $text[$pos + 1];
                    $pos += 2;
                    continue;
                }

                $pos++;

                if ($char === '"') {
                    break;
                }

                $value .= $char;
            }

            $tokens[] = $value;
            continue;
        }

        if (($char === '{') || (($char === '~') && (($text[$pos + 1] ?? '') === '{'))) {
            if (preg_match('/\G~?\{(\d+)\+?\}\r?\n/', $text, $match, 0, $pos)) {
                $pos += strlen($match[0]);
                $tokens[] = (string) substr($text, $pos, (int) $match[1]);
                $pos += (int) $match[1];
                continue;
            }
        }

        // An atom, with any [section] read whole.
        $start = $pos;
        $brackets = 0;

        while ($pos < $length) {
            $char = $text[$pos];

            if ($char === '[') {
                $brackets++;
            } elseif (($char === ']') && ($brackets > 0)) {
                $brackets--;
            } elseif (($brackets === 0) && (($char === ' ') || ($char === '(') || ($char === ')') || ($char === "\r") || ($char === "\n"))) {
                break;
            }

            $pos++;
        }

        $atom = (string) substr($text, $start, $pos - $start);
        $tokens[] = (strtoupper($atom) === 'NIL') ? null : $atom;
    }

    return $tokens;
}

/**
 * The items of a FETCH response as name => value. Names are upper case;
 * BODY[...] sections keep their section ("BODY[]", "BODY[HEADER.FIELDS
 * (MESSAGE-ID)]") and lose an <origin> octet mark. UID and RFC822.SIZE are
 * numbers.
 *
 * @param array $tokens
 * @return array
 */
function pg_imap_fetch_map($tokens)
{
    $out = array();
    $count = count($tokens);

    for ($i = 0; $i + 1 < $count; $i += 2) {
        if (!is_string($tokens[$i])) {
            break;
        }

        $name = strtoupper(preg_replace('/<\d+>$/', '', $tokens[$i]));
        $value = $tokens[$i + 1];

        if (in_array($name, array('UID', 'RFC822.SIZE'), true)) {
            $value = is_string($value) && ctype_digit($value) ? (int) $value : 0;
        }

        $out[$name] = $value;
    }

    return $out;
}

/**
 * The body of the first item of a FETCH map whose name starts with $prefix
 * ("BODY[HEADER" finds the header fields however the server spelt them).
 *
 * @param array  $map pg_imap_fetch_map()
 * @param string $prefix
 * @return string|null
 */
function pg_imap_fetch_section($map, $prefix)
{
    foreach ((array) $map as $name => $value) {
        if ((strncmp((string) $name, $prefix, strlen($prefix)) === 0) && is_string($value)) {
            return $value;
        }
    }

    return null;
}

/**
 * A string as an IMAP astring: quoted when it may be, a literal part (for
 * pg_imap_command()) when it holds line breaks, NUL or 8-bit bytes.
 *
 * @param string $value
 * @return string|array
 */
function pg_imap_astring($value)
{
    $value = (string) $value;

    if (preg_match('/[\x00\r\n\x80-\xFF]/', $value)) {
        return array('literal' => $value);
    }

    return '"' . strtr($value, array('\\' => '\\\\', '"' => '\\"')) . '"';
}

/**
 * A folder name in IMAP's modified UTF-7 (RFC 3501 5.1.3): printable ASCII
 * as it is ("&" as "&-"), every other run as UTF-16 in base64 with "," for
 * "/", between "&" and "-".
 *
 * @param string $name UTF-8
 * @return string
 */
function pg_imap_utf7_encode($name)
{
    $out = '';
    $run = '';
    $chars = preg_split('//u', (string) $name, -1, PREG_SPLIT_NO_EMPTY);

    if ($chars === false) {
        // Not UTF-8: only its ASCII part can be named.
        $ascii = preg_replace('/[^\x20-\x7E]/', '', (string) $name);
        $chars = ($ascii === '') ? array() : str_split($ascii);
    }

    $flush = function () use (&$out, &$run) {
        if ($run !== '') {
            $out .= '&' . str_replace('/', ',', rtrim(base64_encode($run), '=')) . '-';
            $run = '';
        }
    };

    foreach ($chars as $char) {
        $code = pg_imap_utf8_code($char);

        if (($code >= 0x20) && ($code <= 0x7E)) {
            $flush();
            $out .= ($char === '&') ? '&-' : $char;
            continue;
        }

        if ($code > 0xFFFF) {
            $code -= 0x10000;
            $run .= pack('n', 0xD800 | ($code >> 10)) . pack('n', 0xDC00 | ($code & 0x3FF));
        } else {
            $run .= pack('n', $code);
        }
    }

    $flush();

    return $out;
}

/**
 * The code point of one UTF-8 character.
 *
 * @param string $char
 * @return int
 */
function pg_imap_utf8_code($char)
{
    $bytes = array_values(unpack('C*', $char));
    $count = count($bytes);

    if ($count === 1) {
        return $bytes[0];
    }

    $code = $bytes[0] & (0xFF >> ($count + 1));

    for ($i = 1; $i < $count; $i++) {
        $code = ($code << 6) | ($bytes[$i] & 0x3F);
    }

    return $code;
}

/**
 * UIDs as a sequence set: 1,2,3,5,7,8 as "1:3,5,7:8".
 *
 * @param int[] $uids
 * @return string
 */
function pg_imap_uid_set($uids)
{
    $uids = array_values(array_unique(array_filter(array_map('intval', (array) $uids), function ($uid) {
        return $uid > 0;
    })));
    sort($uids);
    $ranges = array();
    $count = count($uids);

    for ($i = 0; $i < $count; $i++) {
        $start = $uids[$i];

        while (($i + 1 < $count) && ($uids[$i + 1] === $uids[$i] + 1)) {
            $i++;
        }

        $ranges[] = ($uids[$i] === $start) ? (string) $start : $start . ':' . $uids[$i];
    }

    return implode(',', $ranges);
}

/**
 * A day as SEARCH SINCE reads it ("28-Oct-2026"), always in English.
 *
 * @param int $time
 * @return string
 */
function pg_imap_search_date($time)
{
    return gmdate('j-M-Y', (int) $time);
}

/**
 * Signs in: AUTHENTICATE PLAIN when the server offers it (any password,
 * UTF-8 too, travels as base64), else LOGIN.
 *
 * @param array  $session
 * @param string $user
 * @param string $password
 * @return bool
 */
function pg_imap_login(&$session, $user, $password)
{
    if (!$session['capabilities'] && !pg_imap_command($session, array('CAPABILITY'))['ok']) {
        return pg_imap_fail($session, 'protocol', 'CAPABILITY failed.');
    }

    $caps = $session['capabilities'];

    if (in_array('AUTH=PLAIN', $caps, true)) {
        $reply = pg_imap_authenticate_plain($session, base64_encode("\0" . $user . "\0" . $password), in_array('SASL-IR', $caps, true));
    } elseif (in_array('LOGINDISABLED', $caps, true)) {
        return pg_imap_fail($session, 'auth_disabled');
    } else {
        $reply = pg_imap_command($session, array('LOGIN', pg_imap_astring($user), pg_imap_astring($password)));
    }

    if (!$reply['ok']) {
        // The server's words, never the command (it carried the password).
        return pg_imap_fail($session, ($reply['status'] === '') ? ($session['error'] ?: 'protocol') : 'auth', $reply['text']);
    }

    // Capabilities often change once signed in (MOVE, UIDPLUS).
    if (!(($reply['code'] !== null) && ($reply['code']['name'] === 'CAPABILITY'))) {
        pg_imap_command($session, array('CAPABILITY'));
    }

    return $session['error'] === '';
}

/**
 * AUTHENTICATE PLAIN, with the response in the command line when the server
 * allows it (SASL-IR), else after its go-ahead.
 *
 * @param array  $session
 * @param string $response base64
 * @param bool   $initial
 * @return array pg_imap_command() answer
 */
function pg_imap_authenticate_plain(&$session, $response, $initial)
{
    if ($initial) {
        return pg_imap_command($session, array('AUTHENTICATE PLAIN', $response));
    }

    $tag = 'A' . str_pad((string) (++$session['tag']), 4, '0', STR_PAD_LEFT);

    if (!pg_imap_write($session, $tag . " AUTHENTICATE PLAIN\r\n")) {
        return pg_imap_broken($session);
    }

    $reply = pg_imap_read_response($session, $tag, true);

    if (!$reply['continue']) {
        return $reply;
    }

    if (!pg_imap_write($session, $response . "\r\n")) {
        return pg_imap_broken($session);
    }

    return pg_imap_read_response($session, $tag, false);
}

/**
 * Opens a folder: SELECT, or EXAMINE when $read_only (nothing marked).
 *
 * @param array  $session
 * @param string $folder UTF-8
 * @param bool   $read_only
 * @return array ok, exists, uidvalidity, uidnext
 */
function pg_imap_select(&$session, $folder, $read_only = false)
{
    $reply = pg_imap_command($session, array($read_only ? 'EXAMINE' : 'SELECT', pg_imap_astring(pg_imap_utf7_encode($folder))));
    $out = pg_imap_select_result($reply);

    if (!$out['ok'] && ($session['error'] === '')) {
        pg_imap_fail($session, 'folder', $reply['text']);
    }

    return $out;
}

/**
 * What a SELECT answer says about the folder.
 *
 * @param array $reply pg_imap_command()
 * @return array ok, exists, uidvalidity, uidnext
 */
function pg_imap_select_result($reply)
{
    $out = array('ok' => (bool) $reply['ok'], 'exists' => 0, 'uidvalidity' => 0, 'uidnext' => 0);

    foreach ($reply['untagged'] as $line) {
        if (($line['type'] === 'EXISTS') && ($line['number'] !== null)) {
            $out['exists'] = $line['number'];
        }

        if (($line['type'] === 'OK') && ($line['code'] !== null) && in_array($line['code']['name'], array('UIDVALIDITY', 'UIDNEXT'), true)) {
            $value = trim($line['code']['args']);
            $out[strtolower($line['code']['name'])] = ctype_digit($value) ? (int) $value : 0;
        }
    }

    return $out;
}

/**
 * UID SEARCH: the matching UIDs, smallest first; null when it failed.
 *
 * @param array  $session
 * @param string $criteria built by the caller from fixed words, numbers and dates
 * @return int[]|null
 */
function pg_imap_uid_search(&$session, $criteria)
{
    $reply = pg_imap_command($session, array('UID SEARCH', $criteria));

    if (!$reply['ok']) {
        return null;
    }

    $uids = array();

    foreach ($reply['untagged'] as $line) {
        if ($line['type'] === 'SEARCH') {
            $uids = array_merge($uids, $line['data']);
        }
    }

    $uids = array_values(array_unique($uids));
    sort($uids);

    return $uids;
}

/**
 * UID FETCH: uid => item map (pg_imap_fetch_map()); null when it failed.
 *
 * @param array  $session
 * @param int[]  $uids
 * @param string $items e.g. "(UID RFC822.SIZE BODY.PEEK[])"
 * @return array|null
 */
function pg_imap_uid_fetch(&$session, $uids, $items)
{
    $set = pg_imap_uid_set($uids);

    if ($set === '') {
        return array();
    }

    $reply = pg_imap_command($session, array('UID FETCH', $set, $items));

    if (!$reply['ok']) {
        return null;
    }

    $out = array();

    foreach ($reply['untagged'] as $line) {
        if (($line['type'] === 'FETCH') && !empty($line['data']['UID'])) {
            $uid = (int) $line['data']['UID'];
            $out[$uid] = isset($out[$uid]) ? array_merge($out[$uid], $line['data']) : $line['data'];
        }
    }

    return $out;
}

/**
 * Marks mails read (\Seen).
 *
 * @param array $session
 * @param int[] $uids
 * @return bool
 */
function pg_imap_uid_mark_seen(&$session, $uids)
{
    $set = pg_imap_uid_set($uids);

    return ($set === '') || pg_imap_command($session, array('UID STORE', $set, '+FLAGS.SILENT (\\Seen)'))['ok'];
}

/**
 * Moves mails to another folder: UID MOVE where the server has it; else a
 * copy, then the originals marked \Seen \Deleted and, with UIDPLUS, expunged
 * by UID (a plain EXPUNGE would also remove what somebody else deleted).
 *
 * @param array  $session
 * @param int[]  $uids
 * @param string $folder UTF-8
 * @return bool
 */
function pg_imap_uid_move(&$session, $uids, $folder)
{
    $set = pg_imap_uid_set($uids);

    if ($set === '') {
        return true;
    }

    $target = pg_imap_astring(pg_imap_utf7_encode($folder));

    if (in_array('MOVE', $session['capabilities'], true)) {
        return pg_imap_command($session, array('UID MOVE', $set, $target))['ok'];
    }

    if (!pg_imap_command($session, array('UID COPY', $set, $target))['ok']) {
        return false;
    }

    if (!pg_imap_command($session, array('UID STORE', $set, '+FLAGS.SILENT (\\Seen \\Deleted)'))['ok']) {
        return false;
    }

    return !in_array('UIDPLUS', $session['capabilities'], true) || pg_imap_command($session, array('UID EXPUNGE', $set))['ok'];
}

/**
 * Says goodbye and closes the stream(s).
 *
 * @param array $session
 */
function pg_imap_logout(&$session)
{
    if ($session['error'] === '') {
        pg_imap_command($session, array('LOGOUT'));
    }

    if (is_resource($session['in'])) {
        @fclose($session['in']);
    }

    if (($session['out'] !== $session['in']) && is_resource($session['out'])) {
        @fclose($session['out']);
    }
}
