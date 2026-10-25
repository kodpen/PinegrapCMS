<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Byte ranges (RFC 9110 14): which part of a file a request asks for, and
 * sending that part.
 *
 * A media player asks for a sound or a video in parts: Safari plays nothing
 * that is not answered 206 Partial Content, and every player winds forward by
 * asking for the part it is about to play. The external API's file downloads
 * and get_file.php answer the same way from this one copy. It lives here,
 * outside functions.php, because get_file.php never loads functions.php
 * (router.php dispatches it that way on purpose). One copy, two includers:
 *
 *   functions.php   require_once at the top - everything ordinary
 *   get_file.php    require_once before it sends a file
 *
 * CONTRACT - the functions in this file may only call each other and PHP
 * itself.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR') && !defined('PG_GET_FILE')) {
    exit;
}

/**
 * The part of a file a Range header asks for (RFC 9110 14.2).
 *
 * A video player opens a file by asking for its first bytes and then for the
 * part it is about to play, so a clip starts and can be wound forward without
 * the whole file being fetched first. One range is served; a header asking
 * for several, a unit other than bytes, or anything that does not parse is
 * ignored and the whole file is sent, which the RFC allows and every client
 * copes with.
 *
 * @param string $header the Range header as sent
 * @param int    $size   the file's size in bytes
 * @return array|null|false array(first, last) byte positions, both included;
 *                          null to send the whole file; false when the range
 *                          lies past the end of the file (416)
 */
function pg_http_byte_range($header, $size)
{
    $size = max(0, (int) $size);

    if (!preg_match('/^\s*bytes\s*=\s*([^,]*?)\s*$/i', (string) $header, $match)) {
        return null;
    }

    if (!preg_match('/^(\d*)\s*-\s*(\d*)$/', $match[1], $bounds) || (($bounds[1] === '') && ($bounds[2] === ''))) {
        return null;
    }

    // A position longer than any file this software stores is past the end
    // as a first byte and the end itself as a last one; reading it as an int
    // would wrap.
    $huge = 18;

    if ($bounds[1] === '') {

        // bytes=-500 is the last 500 bytes.
        $suffix = (strlen(ltrim($bounds[2], '0')) > $huge) ? $size : (int) $bounds[2];

        if (($suffix <= 0) || ($size === 0)) {
            return false;
        }

        return array(max(0, $size - $suffix), $size - 1);
    }

    if ((strlen(ltrim($bounds[1], '0')) > $huge) || ((int) $bounds[1] >= $size)) {
        return false;
    }

    $first = (int) $bounds[1];
    $last = (($bounds[2] === '') || (strlen(ltrim($bounds[2], '0')) > $huge)) ? ($size - 1) : (int) $bounds[2];

    if ($last < $first) {
        return null;
    }

    return array($first, min($last, $size - 1));
}

/**
 * The part of a file to send, with the request's If-Range taken into account.
 *
 * If-Range asks for the part only while the file is still the one the client
 * holds a piece of; otherwise the whole file goes out, never a 416. It names
 * an ETag or a Last-Modified date, and must equal one of the validators this
 * response carries exactly: a weak ETag (W/...) never does.
 *
 * @param string $range      the Range header as sent ('' when there is none)
 * @param string $if_range   the If-Range header as sent ('' when there is none)
 * @param array  $validators the ETag and/or Last-Modified this response sends
 * @param int    $size       the file's size in bytes
 * @return array|null|false as pg_http_byte_range()
 */
function pg_http_range_answer($range, $if_range, $validators, $size)
{
    if (trim((string) $range) === '') {
        return null;
    }

    $if_range = trim((string) $if_range);

    if (($if_range !== '') && !in_array($if_range, array_map('strval', (array) $validators), true)) {
        return null;
    }

    return pg_http_byte_range($range, $size);
}

/**
 * The Content-Range header's value: the part sent (206), or the file's size
 * alone for a range that cannot be served (416).
 *
 * @param array|false $range array(first, last) as pg_http_byte_range() gives
 * @param int         $size  the file's size in bytes
 * @return string
 */
function pg_http_content_range($range, $size)
{
    if (!is_array($range)) {
        return 'bytes */' . max(0, (int) $size);
    }

    return 'bytes ' . (int) $range[0] . '-' . (int) $range[1] . '/' . max(0, (int) $size);
}

/**
 * Streams $length bytes of a file from $first in pieces, so a part of a large
 * video is never read into memory whole.
 *
 * @param string $path   the file on disk
 * @param int    $first  the first byte to send
 * @param int    $length how many bytes to send
 * @return void
 */
function pg_http_send_file_part($path, $first, $length)
{
    $handle = @fopen($path, 'rb');

    if (!$handle) {
        return;
    }

    if (($first > 0) && (@fseek($handle, $first) !== 0)) {
        fclose($handle);
        return;
    }

    while (($length > 0) && !feof($handle)) {
        $chunk = fread($handle, (int) min(1048576, $length));

        if (($chunk === false) || ($chunk === '')) {
            break;
        }

        echo $chunk;
        $length -= strlen($chunk);
        flush();
    }

    fclose($handle);
}
