<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Parser for incoming e-mail: a raw RFC 5322 message (an .eml file, an IMAP
 * fetch) becomes an array of decoded headers, the plain text and HTML
 * bodies in UTF-8 and the attachment list. Pure functions, no database and
 * no files, so the whole module is reachable from unit tests.
 *
 * Mail is untrusted input: every function returns a usable value for broken,
 * truncated or hostile messages and records what it skipped in the result's
 * 'errors' list instead of raising. Size, nesting depth, part count and
 * header size are bounded (pg_mail_parse_limits()).
 *
 * The outgoing mail path (mail.php, PHPMailer) is separate and unchanged.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/**
 * Default limits of pg_mail_parse(); each can be overridden through its
 * $options argument.
 *
 * max_bytes:        a larger message is not parsed past its headers
 *                   (the design's 25 MB per mail).
 * max_depth:        multipart nesting below this is skipped.
 * max_parts:        body parts walked in one message.
 * max_header_bytes: one header block is cut at this size.
 * max_headers:      header fields kept from one block.
 *
 * @return array
 */
function pg_mail_parse_limits()
{
    return array(
        'max_bytes' => 25 * 1024 * 1024,
        'max_depth' => 10,
        'max_parts' => 200,
        'max_header_bytes' => 256 * 1024,
        'max_headers' => 500,
    );
}

/**
 * Parses a raw message.
 *
 * Options: the keys of pg_mail_parse_limits(), and 'with_content' (false by
 * default) to put each attachment's decoded bytes into its 'content' key.
 *
 * Result keys:
 * - headers:     lower-case field name => list of decoded values, in order.
 * - subject:     decoded subject ('' when missing).
 * - from:        first address of From as array('email' => , 'name' => ), or
 *                null; to, cc, reply_to: lists of such addresses.
 * - message_id:  Message-ID without angle brackets ('' when missing);
 *                in_reply_to and references: lists of IDs, same form.
 * - date:        Unix time of the Date header, 0 when missing or unreadable.
 * - text, html:  the bodies in UTF-8 ('' when the message has none); inline
 *                parts of the same kind are joined.
 * - attachments: list of array('filename', 'content_type', 'size',
 *                'disposition', 'content_id', 'inline', 'part'
 *                [, 'content']); 'part' is the MIME path ('1', '2.1').
 * - errors:      list of codes for what was cut or skipped: too_large,
 *                header_too_large, too_many_headers, too_deep,
 *                too_many_parts, no_boundary, no_closing_boundary,
 *                bad_base64.
 *
 * @param string $raw
 * @param array  $options
 *
 * @return array
 */
function pg_mail_parse($raw, $options = array())
{
    $options = array_merge(pg_mail_parse_limits(), array('with_content' => false), (array) $options);

    $state = array(
        'options' => $options,
        'text' => array(),
        'html' => array(),
        'attachments' => array(),
        'errors' => array(),
        'parts' => 0,
    );

    $raw = (string) $raw;

    // An mbox "From " separator line is not a header.
    if (strncmp($raw, 'From ', 5) === 0) {
        $newline = strpos($raw, "\n");
        $raw = ($newline === false) ? '' : (string) substr($raw, $newline + 1);
    }

    $too_large = (strlen($raw) > $options['max_bytes']);

    if ($too_large) {
        pg_mail_parse_error($state, 'too_large');
    }

    $raw = str_replace("\r\n", "\n", $raw);
    list($head, $body) = pg_mail_split_head_body($raw);
    $fields = pg_mail_parse_header_block($head, $state);

    if (!$too_large) {
        pg_mail_parse_entity($fields, $body, 0, '', $state);
    }

    $headers = array();

    foreach ($fields as $field) {
        $headers[$field[0]][] = pg_mail_header_decode($field[1]);
    }

    $from = pg_mail_address_list(pg_mail_parse_field($fields, 'from'));
    $message_ids = pg_mail_message_ids(pg_mail_parse_field($fields, 'message-id'));

    return array(
        'headers' => $headers,
        'subject' => pg_mail_header_decode(pg_mail_parse_field($fields, 'subject')),
        'from' => $from ? $from[0] : null,
        'to' => pg_mail_address_list(pg_mail_parse_join_fields($fields, 'to')),
        'cc' => pg_mail_address_list(pg_mail_parse_join_fields($fields, 'cc')),
        'reply_to' => pg_mail_address_list(pg_mail_parse_field($fields, 'reply-to')),
        'message_id' => $message_ids ? $message_ids[0] : '',
        'in_reply_to' => pg_mail_message_ids(pg_mail_parse_field($fields, 'in-reply-to')),
        'references' => pg_mail_message_ids(pg_mail_parse_field($fields, 'references')),
        'date' => pg_mail_parse_date(pg_mail_parse_field($fields, 'date')),
        'text' => implode("\n\n", $state['text']),
        'html' => implode("\n", $state['html']),
        'attachments' => $state['attachments'],
        'errors' => $state['errors'],
    );
}

/**
 * Records an error code once.
 *
 * @param array  $state
 * @param string $code
 */
function pg_mail_parse_error(&$state, $code)
{
    if (!in_array($code, $state['errors'], true)) {
        $state['errors'][] = $code;
    }
}

/**
 * All values of a repeatable address field (To, Cc) joined with commas, raw.
 *
 * @param array  $fields
 * @param string $name
 *
 * @return string
 */
function pg_mail_parse_join_fields($fields, $name)
{
    $values = array();

    foreach ($fields as $field) {
        if ($field[0] === $name) {
            $values[] = $field[1];
        }
    }

    return implode(', ', $values);
}

/**
 * Splits an entity (LF line endings) at its first empty line. A message
 * without one is all header when it starts like a header, all body
 * otherwise.
 *
 * @param string $raw
 *
 * @return array array($head, $body)
 */
function pg_mail_split_head_body($raw)
{
    $raw = (string) $raw;

    if (($raw === '') || ($raw[0] === "\n")) {
        return array('', (string) substr($raw, 1));
    }

    $position = strpos($raw, "\n\n");

    if ($position === false) {
        if (preg_match('/^[!-9;-~]+:/', $raw)) {
            return array($raw, '');
        }

        return array('', $raw);
    }

    return array((string) substr($raw, 0, $position), (string) substr($raw, $position + 2));
}

/**
 * Unfolds a header block into fields, raw values (not decoded).
 *
 * @param string $head
 * @param array  $state receives header_too_large / too_many_headers
 *
 * @return array list of array(lower-case name, value)
 */
function pg_mail_parse_header_block($head, &$state)
{
    $head = (string) $head;

    if (strlen($head) > $state['options']['max_header_bytes']) {
        pg_mail_parse_error($state, 'header_too_large');
        $head = substr($head, 0, $state['options']['max_header_bytes']);
    }

    $fields = array();
    $current = -1;

    foreach (explode("\n", $head) as $line) {
        $line = rtrim($line, "\r");

        if ($line === '') {
            continue;
        }

        if (($line[0] === ' ') || ($line[0] === "\t")) {
            if ($current >= 0) {
                $fields[$current][1] .= ' ' . ltrim($line);
            }

            continue;
        }

        $colon = strpos($line, ':');

        if (($colon === false) || ($colon === 0)) {
            $current = -1;
            continue;
        }

        if (count($fields) >= $state['options']['max_headers']) {
            pg_mail_parse_error($state, 'too_many_headers');
            break;
        }

        $fields[] = array(strtolower(trim(substr($line, 0, $colon))), trim((string) substr($line, $colon + 1)));
        $current = count($fields) - 1;
    }

    return $fields;
}

/**
 * The raw value of the first field with this name, or ''.
 *
 * @param array  $fields from pg_mail_parse_header_block()
 * @param string $name   lower-case
 *
 * @return string
 */
function pg_mail_parse_field($fields, $name)
{
    foreach ($fields as $field) {
        if ($field[0] === $name) {
            return $field[1];
        }
    }

    return '';
}

/**
 * Walks one entity: a multipart container is split and its parts walked, a
 * leaf becomes a text or HTML body or an attachment.
 *
 * @param array  $fields  the entity's header fields
 * @param string $body    the entity's body, LF line endings
 * @param int    $depth   nesting level, 0 for the message itself
 * @param string $path    MIME part path, '' for the message itself
 * @param array  $state
 * @param string $default content type when the entity has none
 */
function pg_mail_parse_entity($fields, $body, $depth, $path, &$state, $default = 'text/plain')
{
    if ($state['parts'] >= $state['options']['max_parts']) {
        pg_mail_parse_error($state, 'too_many_parts');
        return;
    }

    $state['parts']++;

    $content_type = pg_mail_header_params(pg_mail_parse_field($fields, 'content-type'));
    $type = $content_type['value'];

    if (!preg_match('~^[a-z0-9!#$&^_.+-]+/[a-z0-9!#$&^_.+-]+$~', $type)) {
        $type = $default;
    }

    if (strncmp($type, 'multipart/', 10) === 0) {
        if ($depth >= $state['options']['max_depth']) {
            pg_mail_parse_error($state, 'too_deep');
            return;
        }

        $boundary = isset($content_type['params']['boundary']) ? $content_type['params']['boundary'] : '';

        if ($boundary === '') {
            pg_mail_parse_error($state, 'no_boundary');
            pg_mail_parse_leaf($fields, $body, 'text/plain', $content_type['params'], ($path === '') ? '1' : $path, $state);
            return;
        }

        $closed = true;
        $parts = pg_mail_split_multipart($body, $boundary, $closed);

        if (!$closed) {
            pg_mail_parse_error($state, 'no_closing_boundary');
        }

        // No boundary line at all: the body is shown as text, not lost.
        if (!$parts) {
            pg_mail_parse_leaf($fields, $body, 'text/plain', $content_type['params'], ($path === '') ? '1' : $path, $state);
            return;
        }

        $child_default = ($type === 'multipart/digest') ? 'message/rfc822' : 'text/plain';

        foreach ($parts as $index => $part) {
            list($head, $part_body) = pg_mail_split_head_body($part);
            $child_path = (($path === '') ? '' : $path . '.') . ($index + 1);
            pg_mail_parse_entity(pg_mail_parse_header_block($head, $state), $part_body, $depth + 1, $child_path, $state, $child_default);
        }

        return;
    }

    pg_mail_parse_leaf($fields, $body, $type, $content_type['params'], ($path === '') ? '1' : $path, $state);
}

/**
 * Stores one leaf part as a body or an attachment.
 *
 * @param array  $fields
 * @param string $body
 * @param string $type   lower-case content type
 * @param array  $params content-type parameters
 * @param string $path
 * @param array  $state
 */
function pg_mail_parse_leaf($fields, $body, $type, $params, $path, &$state)
{
    $encoding = strtolower(trim(pg_mail_parse_field($fields, 'content-transfer-encoding')));
    $data = pg_mail_decode_body($body, $encoding, $state);

    $disposition = pg_mail_header_params(pg_mail_parse_field($fields, 'content-disposition'));
    $filename = isset($disposition['params']['filename']) ? $disposition['params']['filename'] : '';

    if (($filename === '') && isset($params['name'])) {
        $filename = $params['name'];
    }

    $filename = pg_mail_safe_filename($filename);
    $is_attachment = ($disposition['value'] === 'attachment') || ($filename !== '');

    if (!$is_attachment && (($type === 'text/plain') || ($type === 'text/html'))) {
        $charset = isset($params['charset']) ? $params['charset'] : '';
        $text = pg_mail_charset_to_utf8($data, $charset);

        if ($type === 'text/plain') {
            $state['text'][] = rtrim($text);
        } else {
            $state['html'][] = $text;
        }

        return;
    }

    $content_id = pg_mail_message_ids(pg_mail_parse_field($fields, 'content-id'));

    $attachment = array(
        'filename' => $filename,
        'content_type' => $type,
        'size' => strlen($data),
        'disposition' => ($disposition['value'] === 'inline') ? 'inline' : 'attachment',
        'content_id' => $content_id ? $content_id[0] : '',
        'inline' => ($disposition['value'] === 'inline') || (!$is_attachment && $content_id),
        'part' => $path,
    );

    if ($state['options']['with_content']) {
        $attachment['content'] = $data;
    }

    $state['attachments'][] = $attachment;
}

/**
 * Splits a multipart body at its boundary lines. Preamble and epilogue are
 * dropped. $closed is set to false when the closing boundary is missing; the
 * parts found up to the end are still returned.
 *
 * @param string $body
 * @param string $boundary
 * @param bool   $closed
 *
 * @return array list of raw parts (head and body, LF line endings)
 */
function pg_mail_split_multipart($body, $boundary, &$closed)
{
    $closed = false;
    $parts = array();
    $current = null;
    $delimiter = '--' . $boundary;
    $length = strlen($delimiter);

    foreach (explode("\n", (string) $body) as $line) {
        if ((strncmp($line, $delimiter, $length) === 0)) {
            $rest = rtrim(substr($line, $length), " \t\r");

            if ($rest === '--') {
                if ($current !== null) {
                    $parts[] = implode("\n", $current);
                }

                $closed = true;
                $current = null;
                break;
            }

            if ($rest === '') {
                if ($current !== null) {
                    $parts[] = implode("\n", $current);
                }

                $current = array();
                continue;
            }
        }

        if ($current !== null) {
            $current[] = $line;
        }
    }

    if ($current !== null) {
        $parts[] = implode("\n", $current);
    }

    return $parts;
}

/**
 * Decodes a body by its Content-Transfer-Encoding. 7bit, 8bit, binary and
 * unknown encodings are returned as they are.
 *
 * @param string     $body
 * @param string     $encoding lower-case
 * @param array|null $state    receives bad_base64
 *
 * @return string bytes
 */
function pg_mail_decode_body($body, $encoding, &$state = null)
{
    $body = (string) $body;

    if ($encoding === 'base64') {
        $clean = preg_replace('~[^A-Za-z0-9+/]~', '', $body);

        // A lone sixth bit cannot form a byte; missing padding is fine.
        if (strlen($clean) % 4 === 1) {
            $clean = substr($clean, 0, -1);
        }

        $decoded = base64_decode($clean);

        if (($decoded === false) || ((trim($body) !== '') && ($clean === ''))) {
            if (is_array($state)) {
                pg_mail_parse_error($state, 'bad_base64');
            }

            return '';
        }

        return $decoded;
    }

    if ($encoding === 'quoted-printable') {
        return pg_mail_decode_quoted_printable($body);
    }

    return $body;
}

/**
 * Quoted-printable decoding that leaves a broken "=" sequence as it is.
 *
 * @param string $body
 *
 * @return string
 */
function pg_mail_decode_quoted_printable($body)
{
    $body = preg_replace('~[ \t]+(?=\r?\n)~', '', (string) $body);
    $body = preg_replace('~=\r?\n~', '', $body);

    return preg_replace_callback('~=([0-9A-Fa-f]{2})~', function ($matches) {
        return chr(hexdec($matches[1]));
    }, $body);
}

/**
 * Decodes RFC 2047 encoded words in a header value to UTF-8 and makes the
 * rest valid UTF-8. White space between two encoded words is dropped;
 * adjacent words in one charset are joined before conversion, so a
 * character split across two words survives.
 *
 * @param string $value
 *
 * @return string
 */
function pg_mail_header_decode($value)
{
    $value = (string) $value;
    $pattern = '~=\?([^?\s]+)\?([BbQq])\?([^?\s]*)\?=~';

    if (strpos($value, '=?') === false) {
        return pg_mail_header_one_line(pg_mail_charset_to_utf8($value, ''));
    }

    $output = '';
    $offset = 0;
    $pending_charset = null;
    $pending_bytes = '';

    while (preg_match($pattern, $value, $match, PREG_OFFSET_CAPTURE, $offset)) {
        $start = $match[0][1];
        $between = substr($value, $offset, $start - $offset);
        $charset = strtolower(preg_replace('~\*.*$~', '', $match[1][0]));
        $text = $match[3][0];

        if (strtoupper($match[2][0]) === 'B') {
            $bytes = (string) base64_decode(preg_replace('~[^A-Za-z0-9+/]~', '', $text));
        } else {
            $bytes = preg_replace_callback('~=([0-9A-Fa-f]{2})~', function ($matches) {
                return chr(hexdec($matches[1]));
            }, str_replace('_', ' ', $text));
        }

        $joins = ($pending_charset !== null) && (trim($between) === '');

        if ($joins && ($pending_charset === $charset)) {
            $pending_bytes .= $bytes;
        } else {
            if ($pending_charset !== null) {
                $output .= pg_mail_charset_to_utf8($pending_bytes, $pending_charset);
            }

            if (!$joins) {
                $output .= pg_mail_charset_to_utf8($between, '');
            }

            $pending_charset = $charset;
            $pending_bytes = $bytes;
        }

        $offset = $start + strlen($match[0][0]);
    }

    if ($pending_charset !== null) {
        $output .= pg_mail_charset_to_utf8($pending_bytes, $pending_charset);
    }

    $output .= pg_mail_charset_to_utf8((string) substr($value, $offset), '');

    return pg_mail_header_one_line($output);
}

/**
 * Replaces control characters, line breaks included, by spaces: a decoded
 * word must not bring them into a one-line value.
 *
 * @param string $value
 *
 * @return string
 */
function pg_mail_header_one_line($value)
{
    return preg_replace('~[\x00-\x08\x0A-\x1F\x7F]~', ' ', $value);
}

/**
 * The content of a quoted string: backslash escapes removed.
 *
 * @param string $value without the surrounding quotes
 *
 * @return string
 */
function pg_mail_unquote($value)
{
    return preg_replace('~\\\\(.)~s', '$1', (string) $value);
}

/**
 * Splits a structured header value ("text/plain; charset=utf-8") into its
 * lower-case main value and its parameters. Parameter names are lower-case;
 * RFC 2231 continuations (name*0*=, name*1=) and charset-tagged values
 * (name*=utf-8''%C3%A7) are joined and decoded to UTF-8; RFC 2047 words
 * inside a quoted value, which many mailers send, are decoded too.
 *
 * @param string $value
 *
 * @return array array('value' => string, 'params' => array)
 */
function pg_mail_header_params($value)
{
    $value = (string) $value;
    $tokens = pg_mail_header_param_tokens($value);
    $main = strtolower(trim(array_shift($tokens)));
    $plain = array();
    $sections = array();

    foreach ($tokens as $token) {
        $equals = strpos($token, '=');

        if ($equals === false) {
            continue;
        }

        $name = strtolower(trim(substr($token, 0, $equals)));
        $raw = trim((string) substr($token, $equals + 1));
        $quoted = (strlen($raw) >= 2) && ($raw[0] === '"') && (substr($raw, -1) === '"');

        if ($quoted) {
            $raw = pg_mail_unquote(substr($raw, 1, -1));
        }

        if (preg_match('~^([^*]+)\*(?:(\d+)\*?)?$~', $name, $match)) {
            $section = isset($match[2]) && ($match[2] !== '') ? (int) $match[2] : 0;
            $encoded = (substr($name, -1) === '*');
            $sections[$match[1]][$section] = array($raw, $encoded);
            continue;
        }

        if ($name !== '') {
            $plain[$name] = $raw;
        }
    }

    $params = array();

    foreach ($plain as $name => $raw) {
        $params[$name] = pg_mail_header_decode($raw);
    }

    foreach ($sections as $name => $pieces) {
        ksort($pieces);
        $charset = '';
        $bytes = '';
        $first = true;

        foreach ($pieces as $piece) {
            list($raw, $encoded) = $piece;

            if ($encoded) {
                if ($first && preg_match("~^([^']*)'[^']*'(.*)$~s", $raw, $match)) {
                    $charset = $match[1];
                    $raw = $match[2];
                }

                $raw = rawurldecode($raw);
            }

            $bytes .= $raw;
            $first = false;
        }

        $params[$name] = pg_mail_charset_to_utf8($bytes, $charset);
    }

    return array('value' => $main, 'params' => $params);
}

/**
 * Splits a header value at semicolons outside quotes.
 *
 * @param string $value
 *
 * @return array
 */
function pg_mail_header_param_tokens($value)
{
    $tokens = array();
    $current = '';
    $quoted = false;
    $length = strlen($value);

    for ($i = 0; $i < $length; $i++) {
        $character = $value[$i];

        if ($quoted && ($character === '\\') && ($i + 1 < $length)) {
            $current .= $character . $value[++$i];
            continue;
        }

        if ($character === '"') {
            $quoted = !$quoted;
        } elseif (($character === ';') && !$quoted) {
            $tokens[] = $current;
            $current = '';
            continue;
        }

        $current .= $character;
    }

    $tokens[] = $current;

    return $tokens;
}

/**
 * Converts text in the named charset to valid UTF-8. An empty, unknown or
 * wrong charset falls back on the bytes themselves: valid UTF-8 is kept,
 * anything else is read as windows-1254 (Turkish, a superset of ISO-8859-9
 * and close to windows-1252), the likeliest source of unlabelled 8-bit mail
 * on these sites. Text labelled UTF-8 keeps its valid characters; only its
 * broken bytes become U+FFFD.
 *
 * @param string $text
 * @param string $charset
 *
 * @return string
 */
function pg_mail_charset_to_utf8($text, $charset)
{
    $text = (string) $text;
    $charset = strtolower(trim((string) $charset, " \t\"'"));

    if (($text === '') || !preg_match('~[\x80-\xFF]~', $text)) {
        return $text;
    }

    $aliases = array(
        'utf8' => 'utf-8',
        'latin1' => 'iso-8859-1',
        'latin5' => 'iso-8859-9',
        'cp1252' => 'windows-1252',
        'cp1254' => 'windows-1254',
        'x-cp1254' => 'windows-1254',
        'iso8859-9' => 'iso-8859-9',
        'iso_8859-9' => 'iso-8859-9',
        'iso-8859-1' => 'iso-8859-1',
        'us-ascii' => '',
        'ascii' => '',
        'unknown-8bit' => '',
        'x-unknown' => '',
    );

    if (isset($aliases[$charset])) {
        $charset = $aliases[$charset];
    }

    if (pg_mail_is_utf8($text) && (($charset === 'utf-8') || ($charset === ''))) {
        return $text;
    }

    if ($charset === 'utf-8') {
        return pg_mail_utf8_scrub($text);
    }

    if ($charset === '') {
        return pg_mail_single_byte_to_utf8($text, 'windows-1254');
    }

    if (in_array($charset, array('iso-8859-1', 'iso-8859-9', 'windows-1252', 'windows-1254'), true)) {
        return pg_mail_single_byte_to_utf8($text, $charset);
    }

    $converted = false;

    if (preg_match('~^[a-z0-9._:-]{1,40}$~', $charset)) {
        if (function_exists('mb_convert_encoding') && function_exists('mb_list_encodings')
            && in_array($charset, array_map('strtolower', pg_mail_mb_encodings()), true)) {
            $converted = @mb_convert_encoding($text, 'UTF-8', $charset);
        } elseif (function_exists('iconv')) {
            $converted = @iconv($charset, 'UTF-8//IGNORE', $text);
        }
    }

    if (is_string($converted) && pg_mail_is_utf8($converted)) {
        return $converted;
    }

    return pg_mail_charset_to_utf8($text, '');
}

/**
 * The names and aliases mbstring knows, read once per request.
 *
 * @return array
 */
function pg_mail_mb_encodings()
{
    static $names = null;

    if ($names === null) {
        $names = array();

        foreach (mb_list_encodings() as $encoding) {
            $names[] = $encoding;

            foreach ((array) @mb_encoding_aliases($encoding) as $alias) {
                $names[] = $alias;
            }
        }
    }

    return $names;
}

/**
 * True when the bytes are valid UTF-8.
 *
 * @param string $text
 *
 * @return bool
 */
function pg_mail_is_utf8($text)
{
    return (bool) preg_match('//u', (string) $text);
}

/**
 * Replaces each byte that is not part of a valid UTF-8 sequence by U+FFFD.
 *
 * @param string $text
 *
 * @return string
 */
function pg_mail_utf8_scrub($text)
{
    $valid = '(?:[\x00-\x7F]|[\xC2-\xDF][\x80-\xBF]|\xE0[\xA0-\xBF][\x80-\xBF]|[\xE1-\xEC\xEE\xEF][\x80-\xBF]{2}'
        . '|\xED[\x80-\x9F][\x80-\xBF]|\xF0[\x90-\xBF][\x80-\xBF]{2}|[\xF1-\xF3][\x80-\xBF]{3}|\xF4[\x80-\x8F][\x80-\xBF]{2})';

    $scrubbed = preg_replace('~' . $valid . '(*SKIP)(*FAIL)|[\x80-\xFF]~', "\xEF\xBF\xBD", (string) $text);

    return is_string($scrubbed) ? $scrubbed : '';
}

/**
 * Converts ISO-8859-1/-9 or windows-1252/-1254 text to UTF-8 without
 * mbstring or iconv. The windows code pages fill 0x80-0x9F with printable
 * characters; their undefined bytes become U+FFFD.
 *
 * @param string $text
 * @param string $charset one of the four names above
 *
 * @return string
 */
function pg_mail_single_byte_to_utf8($text, $charset)
{
    $windows = array(
        0x80 => 0x20AC, 0x82 => 0x201A, 0x83 => 0x0192, 0x84 => 0x201E, 0x85 => 0x2026, 0x86 => 0x2020,
        0x87 => 0x2021, 0x88 => 0x02C6, 0x89 => 0x2030, 0x8A => 0x0160, 0x8B => 0x2039, 0x8C => 0x0152,
        0x8E => 0x017D, 0x91 => 0x2018, 0x92 => 0x2019, 0x93 => 0x201C, 0x94 => 0x201D, 0x95 => 0x2022,
        0x96 => 0x2013, 0x97 => 0x2014, 0x98 => 0x02DC, 0x99 => 0x2122, 0x9A => 0x0161, 0x9B => 0x203A,
        0x9C => 0x0153, 0x9E => 0x017E, 0x9F => 0x0178,
    );

    // The six letters ISO-8859-9 and windows-1254 put in place of Icelandic
    // ones; windows-1254 also leaves 0x8E and 0x9E undefined.
    $turkish = array(0xD0 => 0x011E, 0xDD => 0x0130, 0xDE => 0x015E, 0xF0 => 0x011F, 0xFD => 0x0131, 0xFE => 0x015F);

    $map = array();

    if (($charset === 'windows-1252') || ($charset === 'windows-1254')) {
        $map = $windows;
    }

    if (($charset === 'iso-8859-9') || ($charset === 'windows-1254')) {
        $map = $turkish + $map;
    }

    if ($charset === 'windows-1254') {
        unset($map[0x8E], $map[0x9E]);
    }

    $windows_page = (strncmp($charset, 'windows-', 8) === 0);
    $output = '';
    $length = strlen($text);

    for ($i = 0; $i < $length; $i++) {
        $byte = ord($text[$i]);

        if ($byte < 0x80) {
            $output .= $text[$i];
            continue;
        }

        if (isset($map[$byte])) {
            $code = $map[$byte];
        } elseif ($windows_page && ($byte <= 0x9F)) {
            $code = 0xFFFD;
        } else {
            $code = $byte;
        }

        $output .= pg_mail_utf8_character($code);
    }

    return $output;
}

/**
 * One Unicode code point (up to U+FFFF) as UTF-8.
 *
 * @param int $code
 *
 * @return string
 */
function pg_mail_utf8_character($code)
{
    if ($code < 0x80) {
        return chr($code);
    }

    if ($code < 0x800) {
        return chr(0xC0 | ($code >> 6)) . chr(0x80 | ($code & 0x3F));
    }

    return chr(0xE0 | ($code >> 12)) . chr(0x80 | (($code >> 6) & 0x3F)) . chr(0x80 | ($code & 0x3F));
}

/**
 * Parses an address header (From, To, Cc, Reply-To) into a list of
 * array('email' => , 'name' => ). Handles "Name" <a@b>, Name <a@b>, bare
 * a@b, a@b (Name), RFC 2047 names and groups ("team: a@b, c@d;"). The domain
 * is lower-cased; entries without a usable address are dropped.
 *
 * @param string $value raw (not decoded) header value
 *
 * @return array
 */
function pg_mail_address_list($value)
{
    $value = (string) $value;
    $addresses = array();
    $entries = array();
    $current = '';
    $quoted = false;
    $angle = false;
    $comment = 0;
    $length = strlen($value);

    for ($i = 0; $i < $length; $i++) {
        $character = $value[$i];

        if ($quoted && ($character === '\\') && ($i + 1 < $length)) {
            $current .= $character . $value[++$i];
            continue;
        }

        if ($character === '"' && !$comment) {
            $quoted = !$quoted;
        } elseif (!$quoted && ($character === '(')) {
            $comment++;
        } elseif (!$quoted && ($character === ')') && $comment) {
            $comment--;
        } elseif (!$quoted && !$comment && ($character === '<')) {
            $angle = true;
        } elseif (!$quoted && !$comment && ($character === '>')) {
            $angle = false;
        } elseif (!$quoted && !$comment && !$angle && (($character === ',') || ($character === ';'))) {
            $entries[] = $current;
            $current = '';
            continue;
        } elseif (!$quoted && !$comment && !$angle && ($character === ':')) {
            // A group name: what came before it is not an address.
            $current = '';
            continue;
        }

        $current .= $character;
    }

    $entries[] = $current;

    foreach ($entries as $entry) {
        $entry = trim($entry);

        if ($entry === '') {
            continue;
        }

        $name = '';

        if (preg_match('~^(.*)<([^<>]*)>\s*(?:\(.*\))?\s*$~s', $entry, $match)) {
            $email = trim($match[2]);
            $name = trim($match[1]);
        } else {
            $email = trim(preg_replace('~\(.*\)~s', '', $entry));

            if (preg_match('~\((.*)\)~s', $entry, $match)) {
                $name = trim($match[1]);
            }
        }

        $email = preg_replace('~^mailto:~i', '', trim($email, " \t\"'"));
        $at = strrpos($email, '@');

        if (($at === false) || ($at === 0) || ($at === strlen($email) - 1) || preg_match('~[\s<>,;"]~', $email)) {
            continue;
        }

        $email = substr($email, 0, $at + 1) . strtolower(substr($email, $at + 1));

        if ((strlen($name) >= 2) && ($name[0] === '"') && (substr($name, -1) === '"')) {
            $name = pg_mail_unquote(substr($name, 1, -1));
        }

        $name = trim(pg_mail_header_decode($name));

        $addresses[] = array('email' => pg_mail_charset_to_utf8($email, ''), 'name' => $name);
    }

    return $addresses;
}

/**
 * The message IDs in a Message-ID, In-Reply-To or References value, without
 * angle brackets, in order and without repeats. A bare ID without brackets
 * is accepted when it is the whole value.
 *
 * @param string $value
 *
 * @return array
 */
function pg_mail_message_ids($value)
{
    $value = trim((string) $value);
    $ids = array();

    if (preg_match_all('~<([^<>\s]+)>~', $value, $matches)) {
        $ids = $matches[1];
    } elseif (preg_match('~^[^<>\s]+@[^<>\s]+$~', $value)) {
        $ids = array($value);
    }

    $ids = array_values(array_unique(array_filter($ids, function ($id) {
        return strlen($id) <= 998;
    })));

    return $ids;
}

/**
 * Unix time of a Date header value, 0 when it cannot be read.
 *
 * @param string $value
 *
 * @return int
 */
function pg_mail_parse_date($value)
{
    $value = trim(preg_replace('~\([^)]*\)~', '', (string) $value));

    if ($value === '') {
        return 0;
    }

    $time = strtotime($value);

    return ($time === false) ? 0 : (int) $time;
}

/**
 * A file name from a mail made safe to show and to store under: no path,
 * no control characters, no leading dots, at most 200 bytes of valid UTF-8
 * with the extension kept.
 *
 * @param string $name decoded name
 *
 * @return string '' when nothing usable is left
 */
function pg_mail_safe_filename($name)
{
    $name = pg_mail_charset_to_utf8((string) $name, '');
    $name = str_replace('\\', '/', $name);
    $slash = strrpos($name, '/');

    if ($slash !== false) {
        $name = substr($name, $slash + 1);
    }

    $name = preg_replace('~[\x00-\x1F\x7F]+~', '', $name);
    $name = preg_replace('~[<>:"|?*]~', '_', $name);
    $name = trim(ltrim(trim($name), '.'));

    if (strlen($name) > 200) {
        $dot = strrpos($name, '.');
        $extension = (($dot !== false) && (strlen($name) - $dot <= 16)) ? substr($name, $dot) : '';
        $name = substr($name, 0, 200 - strlen($extension));

        // Cut back to a whole character.
        while (($name !== '') && !pg_mail_is_utf8($name)) {
            $name = substr($name, 0, -1);
        }

        $name .= $extension;
    }

    return $name;
}
