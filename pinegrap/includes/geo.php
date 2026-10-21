<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Visitor location: the country a request comes from.
 *
 * Loaded by waf.php, so it runs under the same constraint: no functions.php,
 * no session, no lang(). router.php reaches it before anything else is
 * loaded, and the firewall's country restriction asks it there.
 *
 * The answer is taken from the first source that has one:
 *
 *   1. the country header of the edge the request came through - Cloudflare's
 *      CF-IPCountry, CloudFront's CloudFront-Viewer-Country - believed exactly
 *      when waf_client_ip() believes that edge's address header: the peer is a
 *      Cloudflare edge address or an operator-declared trusted proxy;
 *   2. a country the web server itself set (mod_geoip, mod_maxminddb), only
 *      when no proxy stands between the visitor and the server, because those
 *      modules locate the address they were connected from;
 *   3. the site's own copy of an IP-to-country database (data/geo/), looked up
 *      by the visitor's real address with a binary search on disk. Built by
 *      includes/fn/geo.php from DB-IP's free "IP to Country Lite" list.
 *
 * Nothing here ever makes a network call: a request is never slowed down or
 * sent to a third party to learn where it came from.
 *
 * Database files: data/geo/meta.json names the current pair of files and
 * describes them. Each file is a 16-byte header ("PGGEO1", the family '4' or
 * '6', the key length, the record count as a big-endian uint32, four spare
 * bytes) followed by fixed-size records sorted by key: the first address of a
 * range (IPv4: 4 bytes, IPv6: the 8 bytes of its /64) and a two-letter
 * country code, "--" for an address no country holds. Ranges are contiguous,
 * so the last record whose key is not above an address is its range.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('WAF_LOADED')) {
    exit;
}

if (defined('PG_GEO_LOADED')) {
    return;
}

define('PG_GEO_LOADED', true);

// Header of a database file and its length in bytes.
define('PG_GEO_MAGIC', 'PGGEO1');
define('PG_GEO_HEADER_BYTES', 16);

/**
 * The visitor's country for this request.
 *
 * @return array code (two upper-case letters, 'T1' for Tor where Cloudflare
 *               says so, '' when no source knows) and source ('cloudflare',
 *               'cloudfront', 'server', 'database' or '')
 */
function pg_geo_country()
{
    static $answer = null;

    if ($answer !== null) {
        return $answer;
    }

    $answer = pg_geo_header_country();

    if ($answer['code'] !== '') {
        return $answer;
    }

    $answer = pg_geo_server_country();

    if ($answer['code'] !== '') {
        return $answer;
    }

    $code = pg_geo_db_lookup(waf_client_ip());
    $answer = array('code' => $code, 'source' => ($code !== '') ? 'database' : '');

    return $answer;
}

/**
 * The visitor's country code alone, '' when unknown.
 */
function pg_geo_country_code()
{
    $country = pg_geo_country();

    return $country['code'];
}

/**
 * Whether the peer is an edge whose headers are believed: a Cloudflare edge
 * address, or a proxy the operator listed as trusted (the address of a
 * Cloudflare Tunnel's cloudflared, a load balancer). The same rule
 * waf_client_ip() applies to the address headers, so the country and the
 * address of a request always come from the same place.
 */
function pg_geo_trusted_edge()
{
    static $trusted = null;

    if ($trusted !== null) {
        return $trusted;
    }

    $remote = isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : '';
    $trusted = false;

    if (($remote === '') || !waf_is_ip($remote)) {
        return $trusted;
    }

    if (waf_ip_in_any($remote, waf_cloudflare_ranges())) {
        $trusted = true;
        return $trusted;
    }

    $proxies = waf_parse_list(waf_setting('waf_trusted_proxies', ''));
    $trusted = ($proxies && waf_ip_in_any($remote, $proxies));

    return $trusted;
}

/**
 * The country an edge put on the request, when the edge is trusted.
 *
 * Cloudflare sends XX for an address it cannot place and T1 for the Tor
 * network; XX counts as unknown, T1 is kept because a site may want to treat
 * Tor on its own.
 *
 * @return array code, source
 */
function pg_geo_header_country()
{
    $none = array('code' => '', 'source' => '');

    if (!pg_geo_trusted_edge()) {
        return $none;
    }

    foreach (array('HTTP_CF_IPCOUNTRY' => 'cloudflare', 'HTTP_CLOUDFRONT_VIEWER_COUNTRY' => 'cloudfront') as $header => $source) {
        if (empty($_SERVER[$header])) {
            continue;
        }

        $value = strtoupper(trim((string) $_SERVER[$header]));

        if (($value === 'T1') && ($source === 'cloudflare')) {
            return array('code' => 'T1', 'source' => $source);
        }

        $code = pg_geo_normalize_code($value);

        if ($code !== '') {
            return array('code' => $code, 'source' => $source);
        }
    }

    return $none;
}

/**
 * The country a module of the web server set. Such a module places the
 * address the server was connected from, which behind a proxy is the proxy:
 * believed only when the visitor's address and the peer are the same.
 *
 * @return array code, source
 */
function pg_geo_server_country()
{
    $none = array('code' => '', 'source' => '');
    $remote = isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : '';

    if (($remote === '') || (waf_client_ip() !== waf_ip_normalize($remote))) {
        return $none;
    }

    foreach (array('GEOIP_COUNTRY_CODE', 'MM_COUNTRY_CODE') as $variable) {
        if (!empty($_SERVER[$variable])) {
            $code = pg_geo_normalize_code((string) $_SERVER[$variable]);

            if ($code !== '') {
                return array('code' => $code, 'source' => 'server');
            }
        }
    }

    return $none;
}

/**
 * Two letters, upper-cased; '' for anything else and for the codes that mean
 * "nowhere" (XX, ZZ, and the "--" of the database files).
 *
 * @param string $value
 * @return string
 */
function pg_geo_normalize_code($value)
{
    $value = strtoupper(trim((string) $value));

    if (!preg_match('/^[A-Z]{2}$/', $value) || ($value === 'XX') || ($value === 'ZZ')) {
        return '';
    }

    return $value;
}

/**
 * Whether an address is on the public internet. A private, loopback or
 * reserved address has no country, and asking the database about one would
 * answer with whatever range happens to be near it.
 */
function pg_geo_ip_is_public($ip)
{
    return (bool) filter_var((string) $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
}

/* ─────────────────────────────────────────────────────────────────────────
   THE DATABASE
   ───────────────────────────────────────────────────────────────────────── */

/**
 * Where the database lives. data/ is closed to the web on both servers
 * (data/.htaccess, the "Block direct access to data" rule of web.config).
 */
function pg_geo_db_dir()
{
    return dirname(dirname(__FILE__)) . '/data/geo';
}

/**
 * The description of the current database (data/geo/meta.json), or null when
 * there is none. Read once per request.
 *
 * @return array|null source, version, built_at, file4, file6, rows4, rows6
 */
function pg_geo_db_meta()
{
    static $meta = false;

    if ($meta !== false) {
        return $meta;
    }

    $meta = null;
    $file = pg_geo_db_dir() . '/meta.json';

    if (!is_file($file)) {
        return $meta;
    }

    $decoded = json_decode((string) @file_get_contents($file), true);

    if (is_array($decoded) && !empty($decoded['file4']) && !empty($decoded['file6'])) {
        $meta = $decoded;
    }

    return $meta;
}

/**
 * The country of an address from the site's database; '' when the address is
 * not public, the database is missing or the address is in no country.
 *
 * @param string $ip
 * @return string
 */
function pg_geo_db_lookup($ip)
{
    static $cache = array();

    $ip = trim((string) $ip);

    if (isset($cache[$ip])) {
        return $cache[$ip];
    }

    $cache[$ip] = '';

    if (!pg_geo_ip_is_public($ip)) {
        return '';
    }

    $meta = pg_geo_db_meta();

    if ($meta === null) {
        return '';
    }

    $binary = @inet_pton($ip);

    if ($binary === false) {
        return '';
    }

    // An IPv4 visitor on a dual-stack socket arrives as ::ffff:a.b.c.d.
    if ((strlen($binary) === 16) && (substr($binary, 0, 12) === "\0\0\0\0\0\0\0\0\0\0\xff\xff")) {
        $binary = substr($binary, 12);
    }

    if (strlen($binary) === 4) {
        $file = pg_geo_db_dir() . '/' . basename((string) $meta['file4']);
        $code = pg_geo_db_search($file, $binary);
    } else {
        $file = pg_geo_db_dir() . '/' . basename((string) $meta['file6']);
        $code = pg_geo_db_search($file, substr($binary, 0, 8));
    }

    $cache[$ip] = pg_geo_normalize_code($code);

    return $cache[$ip];
}

/**
 * The binary search over one database file: the country of the last record
 * whose key is not above $key, '' when the file is unreadable or the key is
 * before the first record.
 *
 * The file is read a record at a time with buffering off, so a lookup in a
 * file of half a million ranges reads about twenty records, not megabytes.
 * Keys are compared as byte strings, which for big-endian addresses is the
 * numeric order, without PHP's signed 64-bit integers getting in the way.
 *
 * @param string $file
 * @param string $key  4 or 8 bytes
 * @return string two letters or '--'; '' when nothing is found
 */
function pg_geo_db_search($file, $key)
{
    $handle = @fopen($file, 'rb');

    if (!$handle) {
        return '';
    }

    if (function_exists('stream_set_read_buffer')) {
        stream_set_read_buffer($handle, 0);
    }

    $header = fread($handle, PG_GEO_HEADER_BYTES);

    if (!is_string($header) || (strlen($header) !== PG_GEO_HEADER_BYTES) || (substr($header, 0, 6) !== PG_GEO_MAGIC)) {
        fclose($handle);
        return '';
    }

    $key_bytes = ord($header[7]);
    $count = unpack('N', substr($header, 8, 4));
    $count = (int) $count[1];
    $record_bytes = $key_bytes + 2;

    if (($key_bytes !== strlen($key)) || ($count < 1)) {
        fclose($handle);
        return '';
    }

    $low = 0;
    $high = $count - 1;
    $found = -1;

    while ($low <= $high) {
        $middle = ($low + $high) >> 1;

        fseek($handle, PG_GEO_HEADER_BYTES + ($middle * $record_bytes));
        $start = fread($handle, $key_bytes);

        if (!is_string($start) || (strlen($start) !== $key_bytes)) {
            break;
        }

        if (strcmp($start, $key) <= 0) {
            $found = $middle;
            $low = $middle + 1;
        } else {
            $high = $middle - 1;
        }
    }

    $code = '';

    if ($found >= 0) {
        fseek($handle, PG_GEO_HEADER_BYTES + ($found * $record_bytes) + $key_bytes);
        $read = fread($handle, 2);
        $code = (is_string($read) && (strlen($read) === 2)) ? $read : '';
    }

    fclose($handle);

    return $code;
}
