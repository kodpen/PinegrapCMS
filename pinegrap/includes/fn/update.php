<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// A module of functions.php: The software update channel, TLS for cURL, archive checks and extraction.
//
// Loaded by functions.php through require_once, never on its own. The
// functions were moved here from functions.php as they were; PG_FUNCTIONS_DIR
// is the software directory (what dirname(__FILE__) was in functions.php),
// see the note at the top of functions.php.
if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// ----------------------------------------------------------------------------
// Performance monitor
// ----------------------------------------------------------------------------
// Records per-request metrics (duration, peak memory, CPU) into perf_log,
// after the response has been flushed to the client. Intended cost on the
// request path: a getrusage() call at start + one INSERT during shutdown.
//
// Behavior is governed by optional config.php constants — if undefined, sane
// defaults apply, so the system works out of the box once the migration runs.
//
//   PERF_MONITOR                 — enable/disable (default: true)
//   PERF_MONITOR_SAMPLE_RATE     — 1..100 percent of requests recorded (default: 100)
//   PERF_MONITOR_RETENTION_DAYS  — rows older than this are pruned (default: 30)
//   PERF_MONITOR_MIN_DURATION_MS — only log requests slower than this (default: 0)

/**
 * Verify a downloaded payload really is a ZIP archive.
 *
 * A download can "succeed" and still not be an archive: the far end may have
 * returned a 403 from its own firewall, a 404 page, or a proxy error, and all
 * of those arrive as a perfectly valid HTTP body. Writing that to disk under a
 * .zip name and handing it to ZipArchive produces a confusing failure several
 * steps later, pointing at the wrong subsystem.
 *
 * Checks the local file header signature. Empty archives use PK\x05\x06 and
 * spanned ones PK\x07\x08; all three are accepted.
 */
/**
 * Apply TLS verification to a cURL handle used for code or licence traffic.
 *
 * These paths were pinned to CURLOPT_SSL_VERIFYPEER = 0, which disables
 * certificate validation entirely. CURLOPT_SSL_VERIFYHOST = 2 sat next to it
 * and did nothing useful: with no chain validation there is no verified
 * certificate whose hostname could be checked.
 *
 * On an update channel that matters more than anywhere else. Anyone able to
 * sit between this server and the update server — poisoned DNS, a hostile
 * network, a compromised upstream — could serve their own archive, and it
 * would be unpacked straight into the web root and executed. The update
 * mechanism becomes the delivery mechanism.
 *
 * Deliberately does NOT retry without verification when it fails. A silent
 * downgrade is worse than no verification at all, because an attacker only has
 * to break the first attempt to get the insecure second one. Turning it off is
 * an explicit, documented decision the operator makes in config.php.
 */
function pg_curl_tls($ch)
{
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

    // Servers whose CA bundle is not on the default search path can point at
    // one instead of giving up verification.
    if (defined('CURL_CA_BUNDLE') && CURL_CA_BUNDLE !== '' && is_file(CURL_CA_BUNDLE)) {
        curl_setopt($ch, CURLOPT_CAINFO, CURL_CA_BUNDLE);
    }

    // Last resort for a host with no usable CA store at all.
    if (defined('ALLOW_INSECURE_UPDATE_TLS') && ALLOW_INSECURE_UPDATE_TLS === true) {
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    }
}

/**
 * The cURL options pg_curl_run() sets, by constant name so that no cURL
 * extension is needed to build them. Never a TLS option: pg_curl_tls() is
 * applied after these, so CURL_CA_BUNDLE and ALLOW_INSECURE_UPDATE_TLS reach
 * every call and nothing here can override them.
 *
 * $options (all optional):
 *   body             string; sent for any method but GET and HEAD
 *   headers          list of "Name: value" strings
 *   timeout          seconds for the whole request (default 10)
 *   connect_timeout  seconds to connect (default: timeout, at most 10)
 *   follow           how many redirects to follow, https only (default 0)
 *   max_size         bytes after which the transfer stops
 *   encoding         true to accept any compression cURL can undo
 *   forbid_reuse     true to close the connection afterwards
 *   user_agent       replaces $env['user_agent']
 *   userpwd          "user:password" for HTTP basic authentication
 *   http_version     '1.0' or '1.1' to hold the request to that HTTP
 *                    version (default: libcurl's choice)
 *   allow_http       true to accept http as well as https for this request
 *                    (never for a redirect): only for an address the
 *                    operator types and the site reaches itself, such as a
 *                    migration source on the same host or LAN; leave
 *                    check_address off with it
 *
 * $env: 'user_agent', 'proxy' (PROXY_ADDRESS or ''), 'resolve' (the
 * "host:port:ip" pin of a checked address, or '').
 *
 * @return array option name => value; a value naming cURL constants
 *               ('CURLPROTO_HTTPS', 'CURLPROTO_HTTP|CURLPROTO_HTTPS',
 *               'CURLPROXY_HTTP', 'CURL_HTTP_VERSION_1_0') is those
 *               constants or-ed together
 */
function pg_curl_run_options($method, $url, $options, $env)
{
    $method = strtoupper((string) $method);
    $timeout = isset($options['timeout']) ? (int) $options['timeout'] : 10;
    $connect = isset($options['connect_timeout']) ? (int) $options['connect_timeout'] : min($timeout, 10);
    $follow = isset($options['follow']) ? (int) $options['follow'] : 0;

    $out = array(
        'CURLOPT_URL'            => (string) $url,
        'CURLOPT_RETURNTRANSFER' => true,
        'CURLOPT_USERAGENT'      => isset($options['user_agent']) ? (string) $options['user_agent'] : (string) $env['user_agent'],
        'CURLOPT_TIMEOUT'        => $timeout,
        'CURLOPT_CONNECTTIMEOUT' => $connect,
        'CURLOPT_FOLLOWLOCATION' => ($follow > 0),
        'CURLOPT_PROTOCOLS'      => empty($options['allow_http']) ? 'CURLPROTO_HTTPS' : 'CURLPROTO_HTTP|CURLPROTO_HTTPS',
    );

    if ($follow > 0) {
        $out['CURLOPT_MAXREDIRS'] = $follow;
        $out['CURLOPT_REDIR_PROTOCOLS'] = 'CURLPROTO_HTTPS';
    }

    // A POST without a body is sent bare, as CURLOPT_CUSTOMREQUEST: with
    // CURLOPT_POST and no fields libcurl sends an empty chunked body after
    // Expect: 100-continue, which some APIs refuse.
    if (($method === 'POST') && isset($options['body'])) {
        $out['CURLOPT_POST'] = true;
    } elseif ($method === 'HEAD') {
        $out['CURLOPT_NOBODY'] = true;
    } elseif ($method !== 'GET') {
        $out['CURLOPT_CUSTOMREQUEST'] = $method;
    }

    if (isset($options['body']) && $method !== 'GET' && $method !== 'HEAD') {
        $out['CURLOPT_POSTFIELDS'] = $options['body'];
    }

    if (!empty($options['headers'])) {
        $out['CURLOPT_HTTPHEADER'] = array_values($options['headers']);
    }

    if (!empty($options['max_size'])) {
        $out['CURLOPT_MAXFILESIZE'] = (int) $options['max_size'];
    }

    if (!empty($options['encoding'])) {
        $out['CURLOPT_ENCODING'] = '';
    }

    if (!empty($options['forbid_reuse'])) {
        $out['CURLOPT_FORBID_REUSE'] = true;
    }

    if (isset($options['userpwd']) && (string) $options['userpwd'] !== '') {
        $out['CURLOPT_USERPWD'] = (string) $options['userpwd'];
    }

    $versions = array('1.0' => 'CURL_HTTP_VERSION_1_0', '1.1' => 'CURL_HTTP_VERSION_1_1');

    if (isset($options['http_version']) && isset($versions[(string) $options['http_version']])) {
        $out['CURLOPT_HTTP_VERSION'] = $versions[(string) $options['http_version']];
    }

    if (isset($env['proxy']) && (string) $env['proxy'] !== '') {
        $out['CURLOPT_HTTPPROXYTUNNEL'] = true;
        $out['CURLOPT_PROXYTYPE'] = 'CURLPROXY_HTTP';
        $out['CURLOPT_PROXY'] = (string) $env['proxy'];
    }

    if (isset($env['resolve']) && (string) $env['resolve'] !== '') {
        $out['CURLOPT_RESOLVE'] = array((string) $env['resolve']);
    }

    return $out;
}

/**
 * The CURLOPT_RESOLVE line that pins $url's host and port to $ip, or '' when
 * $url has no host.
 */
function pg_curl_run_pin($url, $ip)
{
    $parts = @parse_url((string) $url);

    if (!is_array($parts) || !isset($parts['host']) || !isset($parts['scheme'])) {
        return '';
    }

    $port = isset($parts['port']) ? (int) $parts['port'] : ((strtolower($parts['scheme']) === 'https') ? 443 : 80);

    return $parts['host'] . ':' . $port . ':' . $ip;
}

/**
 * One request to a fixed vendor host (update server, CA bundle source,
 * Cloudflare, GitHub): this installation's User-Agent, pg_curl_tls(), the
 * proxy when PROXY_ADDRESS is set, no redirect unless 'follow' asks for one.
 * Options: pg_curl_run_options(), and 'check_address' => true to run
 * api_http_check_url() and pin cURL to the address it checked. That rule is
 * opt-in: a site behind PROXY_ADDRESS may not resolve the host itself, and
 * a fixed host gains nothing from it.
 *
 * Answers like api_http_request(): array('ok' => bool (2xx), 'status' => int,
 * 'body' => string, 'errno' => int, 'error' => string, 'connect' => int,
 * 'request_size' => int, 'content_length' => int). errno is cURL's error number when the request
 * failed in cURL, 0 otherwise - also for an address refused before cURL ran,
 * whose reason is in error alone. error is cURL's own text;
 * pg_curl_tls_hint($errno) is for the caller to append. connect is the status
 * a proxy answered the tunnel request with (0 without a proxy) and
 * request_size the bytes of request headers written (0 when nothing went
 * out); together they tell whether a failed request can have reached the
 * other side. content_length is the Content-Length the server announced (-1
 * when it sent none), against which a caller can tell a body cut short.
 */
function pg_curl_run($method, $url, $options = array())
{
    $result = array('ok' => false, 'status' => 0, 'body' => '', 'errno' => 0, 'error' => '', 'connect' => 0, 'request_size' => 0, 'content_length' => -1);

    if (!function_exists('curl_init')) {
        $result['error'] = 'cURL is not available on this server.';
        return $result;
    }

    $env = array(
        'user_agent' => function_exists('pinegrap_user_agent') ? pinegrap_user_agent() : 'Pinegrap',
        'proxy'      => (defined('PROXY_ADDRESS') && PROXY_ADDRESS != '') ? (string) PROXY_ADDRESS : '',
        'resolve'    => '',
    );

    if (!empty($options['check_address'])) {
        if (!function_exists('api_http_check_url')) {
            $result['error'] = 'The address cannot be checked here.';
            return $result;
        }

        $check = api_http_check_url($url);

        if (!$check['ok']) {
            $result['error'] = $check['error'];
            return $result;
        }

        $env['resolve'] = pg_curl_run_pin($url, $check['ip']);
    }

    $ch = curl_init();

    foreach (pg_curl_run_options($method, $url, $options, $env) as $name => $value) {
        if (in_array($name, array('CURLOPT_PROTOCOLS', 'CURLOPT_REDIR_PROTOCOLS', 'CURLOPT_PROXYTYPE', 'CURLOPT_HTTP_VERSION'), true)) {
            $bits = 0;

            foreach (explode('|', $value) as $constant) {
                $bits |= constant($constant);
            }

            $value = $bits;
        }

        curl_setopt($ch, constant($name), $value);
    }

    pg_curl_tls($ch);

    $body = curl_exec($ch);
    $result['connect'] = (int) curl_getinfo($ch, CURLINFO_HTTP_CONNECTCODE);
    $result['request_size'] = (int) curl_getinfo($ch, CURLINFO_REQUEST_SIZE);

    if ($body === false) {
        $result['errno'] = (int) curl_errno($ch);
        $result['error'] = (string) curl_error($ch);
    } else {
        $result['status'] = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $result['body'] = (string) $body;
        $result['content_length'] = (int) curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD);
        $result['ok'] = ($result['status'] >= 200 && $result['status'] < 300);
    }

    // PHP 8.5 deprecates curl_close(); the handle is freed with its last
    // reference.
    unset($ch);

    return $result;
}

/**
 * The pg_curl_run() options of a licence server request ($data is the JSON
 * body): the explicit Content-Length both licence checks always sent, 10 s
 * timeouts, a fresh connection, $follow https redirects at most.
 */
function pg_license_request_options($data, $follow)
{
    return array(
        'body'            => $data,
        'headers'         => array('Content-Type: application/json', 'Content-Length: ' . strlen($data)),
        'timeout'         => 10,
        'connect_timeout' => 10,
        'forbid_reuse'    => true,
        'follow'          => (int) $follow,
    );
}

/**
 * The pg_curl_run() options of migration.php's request to the source site's
 * API ($data is the JSON body): the explicit Content-Length it always sent,
 * 60 s, a fresh connection, no redirect. The operator types the source
 * address, and a source on the same host or LAN may answer on plain http, so
 * http is allowed; the address is not checked, because api_http_check_url()
 * refuses exactly those private addresses.
 */
function pg_migration_request_options($data)
{
    return array(
        'body'         => $data,
        'headers'      => array('Content-Type: application/json', 'Content-Length: ' . strlen($data)),
        'timeout'      => 60,
        'forbid_reuse' => true,
        'allow_http'   => true,
    );
}

/**
 * Turn a certificate-related cURL error number into an actionable sentence.
 *
 * Without this the operator sees "cURL error 60" and has no idea that the
 * cause is a stale CA bundle on their own server rather than a fault at the
 * other end.
 */
function pg_curl_tls_hint($errno)
{
    $errno = (int) $errno;

    // 60 CURLE_PEER_FAILED_VERIFICATION, 77 CURLE_SSL_CACERT_BADFILE,
    // 35 CURLE_SSL_CONNECT_ERROR.
    if ($errno !== 60 && $errno !== 77 && $errno !== 35) {
        return '';
    }

    return ' The certificate could not be verified. This server is most likely'
        . ' missing an up-to-date CA bundle. Point CURL_CA_BUNDLE at a cacert.pem'
        . ' in data/config.php, or as a last resort set'
        . ' ALLOW_INSECURE_UPDATE_TLS to true there.';
}

// ----------------------------------------------------------------------------
// CA certificate bundle
// ----------------------------------------------------------------------------
// data/cacert.pem is the Mozilla root list an operator points CURL_CA_BUNDLE
// at when the host's own certificate store is missing or stale. Mozilla
// revises the list several times a year, so the copy has to be refreshable
// from the panel; the functions below read it, fetch a fresh one from curl.se
// and swap it into place. The payment library under includes/iyzipay-php/
// carries a copy of its own that is deliberately left alone: includes/ is in
// the file integrity scope and is only changed by a software release.

/**
 * Where the shipped CA bundle lives. Always the one under data/, whatever
 * CURL_CA_BUNDLE points at.
 */
function pg_ca_bundle_path()
{
    return PG_FUNCTIONS_DIR . '/data/cacert.pem';
}

/**
 * The URL a fresh bundle is fetched from.
 *
 * curl.se publishes Mozilla's list as a single PEM file. A host that cannot
 * reach it (a mirror, an internal proxy) sets CA_BUNDLE_SOURCE_URL in
 * config.php. Only https is accepted: the file decides which certificates the
 * software will trust, and fetching it over plain http would let anyone on
 * the path hand the server their own list. Returns '' when the override is
 * not an https URL, so the caller refuses rather than falling back silently.
 */
function pg_ca_bundle_source_url()
{
    $url = 'https://curl.se/ca/cacert.pem';

    if (defined('CA_BUNDLE_SOURCE_URL') && is_string(CA_BUNDLE_SOURCE_URL) && (trim(CA_BUNDLE_SOURCE_URL) !== '')) {
        $url = trim(CA_BUNDLE_SOURCE_URL);
    }

    if (!preg_match('#^https://[^\s/?\#]+(?:[/?\#]\S*)?$#i', $url)) {
        return '';
    }

    return $url;
}

/**
 * Read what a bundle says about itself.
 *
 * The header curl.se writes reads
 *   ## Certificate data from Mozilla as of: Tue Jan 10 04:12:06 2023 GMT
 * and is the only date the file carries. 'stamp' is that line as a Unix
 * time, 0 when the header is missing or unreadable. 'count' is the number of
 * "-----BEGIN CERTIFICATE-----" markers, which is the number of roots.
 */
function pg_ca_bundle_inspect($text)
{
    $text = (string) $text;

    $stamp = 0;
    $date = '';

    // The header sits within the first few lines of the file.
    if (preg_match('/^##\s*Certificate data from Mozilla as of:\s*(.+?)\s*$/m', substr($text, 0, 4096), $match)) {
        $date = $match[1];
        $parsed = strtotime($date);
        if ($parsed !== false && $parsed > 0) {
            $stamp = (int) $parsed;
        }
    }

    return array(
        'stamp' => $stamp,
        'date'  => $date,
        'count' => substr_count($text, '-----BEGIN CERTIFICATE-----'),
        'size'  => strlen($text),
    );
}

/**
 * The shipped bundle as the maintenance row reports it.
 *
 * 'mode' says what the running configuration actually verifies against:
 *   this    CURL_CA_BUNDLE points at data/cacert.pem
 *   other   CURL_CA_BUNDLE points at some other file (named in 'configured')
 *   system  CURL_CA_BUNDLE is empty, so cURL uses the host's own store
 * The tool always writes data/cacert.pem; the mode only tells the operator
 * whether that file is the one in use.
 */
function pg_ca_bundle_status()
{
    $path = pg_ca_bundle_path();

    $status = array(
        'path'       => $path,
        'exists'     => is_file($path),
        'stamp'      => 0,
        'date'       => '',
        'count'      => 0,
        'size'       => 0,
        'mode'       => 'system',
        'configured' => '',
        'source'     => pg_ca_bundle_source_url(),
    );

    if ($status['exists'] && is_readable($path)) {
        $text = @file_get_contents($path);
        if ($text !== false) {
            $status = array_merge($status, pg_ca_bundle_inspect($text));
        }
    }

    if (defined('CURL_CA_BUNDLE') && is_string(CURL_CA_BUNDLE) && (CURL_CA_BUNDLE !== '')) {
        $status['configured'] = CURL_CA_BUNDLE;

        // realpath() so that a relative path, a symlink or a differently
        // spelled separator still counts as the same file.
        $configured_real = @realpath(CURL_CA_BUNDLE);
        $shipped_real = @realpath($path);

        if (($configured_real !== false) && ($shipped_real !== false) && ($configured_real === $shipped_real)) {
            $status['mode'] = 'this';
        } else {
            $status['mode'] = 'other';
        }
    }

    return $status;
}

/**
 * Whether outbound TLS needs data/cacert.pem and the configuration does not
 * point at it - the state a reinstall leaves behind when the new config.php
 * comes with CURL_CA_BUNDLE empty on a host whose own store is missing.
 *
 * 'state':
 *   ok        nothing to do: the setting points at a file that exists, or it
 *             is empty and the host's own store verifies a connection
 *   broken    CURL_CA_BUNDLE names a file that does not exist; every
 *             outbound call then verifies against nothing and fails
 *   needed    CURL_CA_BUNDLE is empty and a connection verified against the
 *             host's own store failed on the certificate
 *   unknown   the probe could not reach the internet at all, so nothing can
 *             be said about the store
 * 'fixable' says whether the one-click repair can help: the bundled file is
 * there and data/config.php can be written.
 *
 * The probe is one HEAD request with a five-second limit, and runs only when
 * the setting is empty; the System Status widget caches its answer with the
 * rest of the checks.
 *
 * @return array state, configured, fixable, error
 */
function pg_ca_bundle_config_state()
{
    $config_file = PG_FUNCTIONS_DIR . '/data/config.php';
    $configured = (defined('CURL_CA_BUNDLE') && is_string(CURL_CA_BUNDLE)) ? CURL_CA_BUNDLE : '';

    $out = array(
        'state'      => 'ok',
        'configured' => $configured,
        'fixable'    => is_file(pg_ca_bundle_path()) && is_file($config_file) && is_writable($config_file),
        'error'      => '',
    );

    if ($configured !== '') {
        if (!is_file($configured) || !is_readable($configured)) {
            $out['state'] = 'broken';
        }

        return $out;
    }

    if (!function_exists('curl_init')) {
        $out['state'] = 'unknown';
        return $out;
    }

    $source = pg_ca_bundle_source_url();
    $probe = curl_init(($source !== '') ? $source : 'https://curl.se/ca/cacert.pem');

    curl_setopt_array($probe, array(
        CURLOPT_NOBODY         => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT        => 5,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_USERAGENT      => 'Pinegrap/' . (defined('VERSION') ? VERSION : '') . ' (ca-probe)',
    ));

    curl_exec($probe);
    $errno = (int) curl_errno($probe);
    $error = (string) curl_error($probe);
    unset($probe);

    // 60 peer certificate cannot be verified, 77 no CA file to read, 35 and
    // 51 a handshake the store could not complete. Anything else - no DNS, no
    // route, a timeout - says nothing about the store.
    if (in_array($errno, array(35, 51, 60, 77), true)) {
        $out['state'] = 'needed';
        $out['error'] = $errno . ': ' . $error;
    } elseif ($errno !== 0) {
        $out['state'] = 'unknown';
        $out['error'] = $errno . ': ' . $error;
    }

    return $out;
}

/**
 * Points CURL_CA_BUNDLE in data/config.php at the bundled data/cacert.pem.
 *
 * The define line is replaced where it is (a commented-out example is left
 * alone) or added at the end of the file, written as
 * dirname(__FILE__) . '/cacert.pem' so that it survives a move of the site to
 * another folder. Nothing else in the file is touched; the write goes to a
 * temporary file beside it that is renamed over it, so the configuration is
 * never half written. The running request keeps the old value: the next one
 * reads the new line.
 *
 * @return array('status' => 'success'|'unchanged'|'error', 'message' => string)
 */
function pg_ca_bundle_config_repair()
{
    $config_file = PG_FUNCTIONS_DIR . '/data/config.php';
    $line = "define('CURL_CA_BUNDLE', dirname(__FILE__) . '/cacert.pem');";

    if (!is_file(pg_ca_bundle_path())) {
        return array('status' => 'error', 'message' => lang('data/cacert.pem is missing. Update the CA certificate bundle first, then try again.'));
    }

    if (!is_file($config_file) || !is_writable($config_file)) {
        return array('status' => 'error', 'message' => lang('data/config.php cannot be written by the web server. Add the line by hand:') . ' ' . $line);
    }

    $text = @file_get_contents($config_file);

    if (($text === false) || ($text === '')) {
        return array('status' => 'error', 'message' => lang('data/config.php could not be read.'));
    }

    $eol = (strpos($text, "\r\n") !== false) ? "\r\n" : "\n";
    // The line up to, not including, its end: a CRLF file keeps its \r.
    $pattern = '/^[ \t]*define\(\s*([\'"])CURL_CA_BUNDLE\1\s*,[^;]*\)\s*;[^\r\n]*(?=\r?\n|\z)/m';

    if (preg_match($pattern, $text, $found)) {
        if (trim($found[0]) === $line) {
            return array('status' => 'unchanged', 'message' => lang('CURL_CA_BUNDLE already points at data/cacert.pem.'));
        }

        $new = preg_replace($pattern, $line, $text, 1);
    } else {
        // At the end, before a closing tag when the file has one.
        if (preg_match('/\?>\s*$/', $text)) {
            $new = preg_replace('/\?>\s*$/', $line . $eol . '?>' . $eol, $text, 1);
        } else {
            $new = rtrim($text) . $eol . $eol . $line . $eol;
        }
    }

    if (!is_string($new) || ($new === $text) || (strpos($new, '<?php') !== 0 && strpos($text, '<?php') === 0)) {
        return array('status' => 'error', 'message' => lang('data/config.php could not be changed safely. Add the line by hand:') . ' ' . $line);
    }

    $temporary = $config_file . '.' . bin2hex(random_bytes(4)) . '.tmp';

    if (@file_put_contents($temporary, $new, LOCK_EX) !== strlen($new)) {
        @unlink($temporary);
        return array('status' => 'error', 'message' => lang('data/config.php cannot be written by the web server. Add the line by hand:') . ' ' . $line);
    }

    // rename() over an existing file fails on Windows with some PHP builds;
    // the copy is then written in place instead.
    if (!@rename($temporary, $config_file)) {
        $written = (@file_put_contents($config_file, $new, LOCK_EX) === strlen($new));
        @unlink($temporary);

        if (!$written) {
            return array('status' => 'error', 'message' => lang('data/config.php cannot be written by the web server. Add the line by hand:') . ' ' . $line);
        }
    }

    if (function_exists('opcache_invalidate')) {
        @opcache_invalidate($config_file, true);
    }

    return array('status' => 'success', 'message' => lang('CURL_CA_BUNDLE in data/config.php now points at data/cacert.pem.'));
}

/**
 * Download the current Mozilla root list and replace data/cacert.pem with it.
 *
 * The download is verified with pg_curl_tls() -- the same rule as the update
 * channel, and for the same reason: this file decides what the software will
 * trust from now on. A file that arrives is then checked before it is
 * believed: a plausible size, the curl.se header with a date newer than the
 * installed one (a downgrade is refused, a same-day file is reported as
 * already current), at least a hundred roots, and every PEM block parsed by
 * OpenSSL. Only then is it written -- to a temporary file in data/temp/ that
 * is renamed over the target, so the bundle in use is never half written.
 *
 * Returns array('status' => 'success'|'unchanged'|'error', 'message' => ...)
 * with 'stamp' and 'count' of the new file on success. Every message is
 * already translated.
 */
function pg_ca_bundle_update()
{
    $target = pg_ca_bundle_path();
    $current = pg_ca_bundle_status();
    $source = pg_ca_bundle_source_url();

    if ($source === '') {
        return array(
            'status'  => 'error',
            'message' => lang('CA_BUNDLE_SOURCE_URL in config.php must be an https address. Nothing was downloaded.'),
        );
    }

    if (!function_exists('curl_init')) {
        return array(
            'status'  => 'error',
            'message' => lang('The cURL extension is not available, so the bundle cannot be downloaded.'),
        );
    }

    if (!function_exists('openssl_x509_read')) {
        return array(
            'status'  => 'error',
            'message' => lang('The OpenSSL extension is not available, so the downloaded certificates cannot be checked.'),
        );
    }

    // ── Download ─────────────────────────────────────────────────────────
    // A redirect is followed, but only to another https address: the scheme
    // check on the configured URL would mean nothing if the server could
    // answer with a Location header pointing at http. Anything past
    // max_size is not a CA bundle; stop reading rather than buffer it. Twice
    // the upper size bound to leave room for the check below to report the
    // real size.
    $answer = pg_curl_run('GET', $source, array(
        'timeout'         => 90,
        'connect_timeout' => 15,
        'follow'          => 3,
        'forbid_reuse'    => true,
        'max_size'        => 4 * 1024 * 1024,
    ));
    $curl_errno = $answer['errno'];
    $curl_error = $answer['error'];
    $http_code = $answer['status'];
    $body = $answer['body'];

    if ($curl_errno !== 0) {
        return array(
            'status'  => 'error',
            'message' => lang(array(
                'string' => 'The bundle could not be downloaded from {var:1}: cURL error {var:2}, {var:3}.',
                'vars'   => array($source, $curl_errno, $curl_error),
            )) . pg_curl_tls_hint($curl_errno),
        );
    }

    if ($http_code !== 200) {
        return array(
            'status'  => 'error',
            'message' => lang(array(
                'string' => 'The bundle could not be downloaded from {var:1}: the server answered HTTP {var:2}.',
                'vars'   => array($source, $http_code),
            )),
        );
    }

    // ── Validate ─────────────────────────────────────────────────────────
    $incoming = pg_ca_bundle_inspect($body);

    // Mozilla's list has been between 200 and 260 KB for years; a 404 page,
    // a proxy notice or a truncated transfer is well outside that.
    if (($incoming['size'] < 50 * 1024) || ($incoming['size'] > 2 * 1024 * 1024)) {
        return array(
            'status'  => 'error',
            'message' => lang(array(
                'string' => 'The downloaded file is {var:1}, which is not the size of a CA bundle (50 KB to 2 MB). Nothing was changed.',
                'vars'   => array(convert_bytes_to_string($incoming['size'], 1)),
            )),
        );
    }

    if ($incoming['stamp'] <= 0) {
        return array(
            'status'  => 'error',
            'message' => lang('The downloaded file has no readable "Certificate data from Mozilla as of" header, so it is not a CA bundle. Nothing was changed.'),
        );
    }

    if ($incoming['count'] < 100) {
        return array(
            'status'  => 'error',
            'message' => lang(array(
                'string' => 'The downloaded file holds {var:1} certificate(s); a CA bundle has at least 100. Nothing was changed.',
                'vars'   => array(pg_format_number($incoming['count'], 0)),
            )),
        );
    }

    // Every block has to be a certificate OpenSSL can read. One that is not
    // means a corrupt transfer or a file that only looks like a bundle, and
    // cURL would refuse the whole file at the first bad block anyway -- with
    // every outbound connection failing until somebody worked out why.
    $blocks = array();
    preg_match_all('/-----BEGIN CERTIFICATE-----\R.+?\R-----END CERTIFICATE-----/s', $body, $blocks);

    if (count($blocks[0]) !== $incoming['count']) {
        return array(
            'status'  => 'error',
            'message' => lang('The downloaded file has a certificate block without an end marker, so it is not a complete bundle. Nothing was changed.'),
        );
    }

    foreach ($blocks[0] as $index => $block) {
        $certificate = @openssl_x509_read($block);
        if ($certificate === false) {
            return array(
                'status'  => 'error',
                'message' => lang(array(
                    'string' => 'Certificate {var:1} of {var:2} in the downloaded file could not be parsed. Nothing was changed.',
                    'vars'   => array($index + 1, $incoming['count']),
                )),
            );
        }
        // Freed with the variable; openssl_x509_free() is a no-op since
        // PHP 8.0 and deprecated in 8.4.
        unset($certificate);
    }

    // The header dates are compared, not the files' own modification times:
    // a re-downloaded copy of the same list is not an update, and a mirror
    // that still serves last year's file must not overwrite this year's.
    if ($current['stamp'] > 0) {
        if ($incoming['stamp'] < $current['stamp']) {
            return array(
                'status'  => 'error',
                'message' => lang(array(
                    'string' => 'The downloaded bundle is dated {var:1}, older than the installed one dated {var:2}. A downgrade is refused; nothing was changed.',
                    'vars'   => array(pg_ca_bundle_date($incoming['stamp']), pg_ca_bundle_date($current['stamp'])),
                )),
            );
        }

        if ($incoming['stamp'] === $current['stamp']) {
            return array(
                'status'  => 'unchanged',
                'message' => lang(array(
                    'string' => 'The installed bundle is already the current one: Mozilla data as of {var:1}, {var:2} root certificate(s). Nothing was changed.',
                    'vars'   => array(pg_ca_bundle_date($current['stamp']), pg_format_number($current['count'], 0)),
                )),
                'stamp'   => $current['stamp'],
                'count'   => $current['count'],
            );
        }
    }

    // ── Write ────────────────────────────────────────────────────────────
    //
    // The new file is written beside the target and renamed over it, so a
    // request verifying a connection at that moment sees either the whole
    // old file or the whole new one. The mode of the old file is kept: an
    // operator who opened it for the FTP user should not find it closed.
    $temp_directory = PG_FUNCTIONS_DIR . '/data/temp';

    if (!is_dir($temp_directory)) {
        @mkdir($temp_directory, 0755, true);
    }

    $temp_file = @tempnam($temp_directory, 'cacert_');

    // tempnam() falls back to the system temp directory when the one it is
    // given is not writable, and rename() across file systems is a copy
    // rather than an atomic swap. Refuse rather than write somewhere else.
    if (($temp_file === false) || (dirname($temp_file) !== $temp_directory && realpath(dirname($temp_file)) !== realpath($temp_directory))) {
        if ($temp_file !== false) {
            @unlink($temp_file);
        }
        return array(
            'status'  => 'error',
            'message' => lang('data/temp/ is not writable, so the new bundle could not be staged. Nothing was changed.'),
        );
    }

    $written = @file_put_contents($temp_file, $body, LOCK_EX);

    if (($written === false) || ($written !== strlen($body))) {
        @unlink($temp_file);
        return array(
            'status'  => 'error',
            'message' => lang('The new bundle could not be written to data/temp/. Nothing was changed.'),
        );
    }

    $mode = ($current['exists'] && (@fileperms($target) !== false)) ? (fileperms($target) & 0777) : 0644;
    @chmod($temp_file, $mode);

    if (!@rename($temp_file, $target)) {
        // Some Windows builds refuse to rename over an existing file; a copy
        // is the fallback there, and the staged file is removed either way.
        $copied = ($current['exists'] && is_writable($target)) ? @copy($temp_file, $target) : false;
        @unlink($temp_file);

        if (!$copied) {
            return array(
                'status'  => 'error',
                'message' => lang('data/cacert.pem could not be replaced. Check that the web server may write to data/. Nothing was changed.'),
            );
        }
    }

    clearstatcache(true, $target);

    // The System Status card caches its checks for ten minutes and would
    // keep reporting the old date until then.
    $status_cache = PG_FUNCTIONS_DIR . '/data/temp/system_status_cache.json';

    if (file_exists($status_cache)) {
        @unlink($status_cache);
    }

    return array(
        'status'  => 'success',
        'message' => lang(array(
            'string' => 'data/cacert.pem was updated: Mozilla data as of {var:1}, {var:2} root certificate(s) (previously {var:3}, {var:4}).',
            'vars'   => array(
                pg_ca_bundle_date($incoming['stamp']),
                pg_format_number($incoming['count'], 0),
                ($current['stamp'] > 0) ? pg_ca_bundle_date($current['stamp']) : lang('no header'),
                pg_format_number($current['count'], 0),
            ),
        )),
        'stamp'   => $incoming['stamp'],
        'count'   => $incoming['count'],
    );
}

/**
 * A bundle's Mozilla date the way the rest of the panel writes dates.
 */
function pg_ca_bundle_date($stamp)
{
    $stamp = (int) $stamp;

    if ($stamp <= 0) {
        return '';
    }

    // The installer and the command line reach this before the site's
    // date format is known.
    if (defined('DATE_FORMAT')) {
        return date(get_date_format_code() . '/Y', $stamp);
    }

    return date('Y-m-d', $stamp);
}

/**
 * Which update channel this installation follows: 'stable' or 'beta'.
 *
 * One place decides, because three separate paths ask the update server the
 * same question — the daily check (software_update_check.php), the updater
 * screen (software_update.php) and the updater's own steps (api.php) — and a
 * site that checked one channel and downloaded the other would install a
 * package it never compared against its own version.
 *
 * config.software_update_channel arrives with 2026.4.4. Anything older, or a
 * request that runs before init.php has read the config row (the installer,
 * a job that includes functions.php on its own), reads 'stable': the channel
 * every installation was on before the setting existed.
 *
 * download_assistant.php deliberately stays on stable. It is the hand-run
 * rescue path for a site whose software is already broken, and the answer to
 * "my site is down" is the release everybody else is running.
 */
function pg_update_channel()
{
    if (defined('SOFTWARE_UPDATE_CHANNEL') && SOFTWARE_UPDATE_CHANNEL === 'beta') {
        return 'beta';
    }

    return 'stable';
}

/**
 * The REQUEST value the update server answers a version question with.
 *
 * Beta builds are published under their own key so that a stable site never
 * sees them: asking for latest_version is asking "what should everyone be
 * running", and that has to keep meaning exactly that.
 */
function pg_update_request_key()
{
    return (pg_update_channel() === 'beta') ? 'latest_beta_version' : 'latest_version';
}

/**
 * The package file for this channel, used both as the name to download from
 * the update server and as the name it is written under while it is unpacked.
 * The two must not drift apart: the replace step opens the file the download
 * step wrote.
 */
function pg_update_package_file()
{
    return (pg_update_channel() === 'beta') ? 'pinegrap_software_update_beta.zip' : 'pinegrap_software_update.zip';
}

function pg_looks_like_zip($bytes)
{
    if (!is_string($bytes) || strlen($bytes) < 4) {
        return false;
    }

    $magic = substr($bytes, 0, 4);

    return ($magic === "PK\x03\x04" || $magic === "PK\x05\x06" || $magic === "PK\x07\x08");
}

/**
 * Extract an update archive and PROVE every entry landed on disk.
 *
 * ZipArchive::extractTo() returns a boolean that the update flow ignored, and
 * that single omission is the cause of the recurring "the update left files
 * missing" problem. Extraction stops at the first entry it cannot write — a
 * permission denied, a file held open by another process, a full disk,
 * open_basedir — and everything after that entry is simply never created.
 * The caller saw no error and reported success, leaving a half-updated
 * installation that then had to be repaired by hand.
 *
 * Four things are checked here, in increasing order of cost:
 *
 *   1. The archive opens with CHECKCONS, so a truncated or corrupt download is
 *      rejected before a single file is touched rather than half-applied.
 *   2. Every file the archive is about to replace can be replaced. A file that
 *      exists and cannot be written to - left behind by another system user,
 *      or made read-only - is unlinked first, since deleting needs only the
 *      directory; what still cannot go is reported BEFORE anything is
 *      extracted, so the site is left whole rather than half-updated.
 *   3. extractTo() succeeded.
 *   4. Every entry the archive claims to contain now exists on disk with the
 *      size and CRC-32 the archive recorded for it. Existence alone was the
 *      old check, and existence is exactly what an old copy satisfies: a
 *      migration file the extraction did not overwrite passed it, the shorter
 *      list of steps in that copy ran, and the version was recorded with
 *      tables missing. Content is what is verified now.
 *
 * Returns array(
 *     'ok'      => bool,
 *     'message' => string,
 *     'missing' => array   entries that did not land on disk,
 *     'stale'   => array   entries that are on disk but are not the archive's,
 *     'blocked' => array   entries that could not have been replaced (nothing was extracted),
 *     'files'   => array   absolute paths written, on success
 * ).
 */
function pg_extract_archive($archive_path, $destination)
{
    $failure = array('ok' => false, 'message' => '', 'missing' => array(), 'stale' => array(), 'blocked' => array());

    if (!class_exists('ZipArchive')) {
        $failure['message'] = 'The ZipArchive extension is not available on this server.';
        return $failure;
    }

    if (!is_file($archive_path) || filesize($archive_path) < 4) {
        $failure['message'] = 'The downloaded archive is missing or empty.';
        return $failure;
    }

    $zip = new ZipArchive();

    // CHECKCONS makes the consistency of the archive a precondition. Without
    // it a damaged file can open on the strength of its central directory
    // alone and then extract nonsense.
    $opened = $zip->open($archive_path, ZipArchive::CHECKCONS);

    if ($opened !== true) {
        // Fall back to a plain open so an archive that merely fails the strict
        // check still reports a useful error rather than a bare code.
        $opened = $zip->open($archive_path);

        if ($opened !== true) {
            $failure['message'] = 'The downloaded archive could not be opened (code ' . (int) $opened . '). It is most likely incomplete — try again.';
            return $failure;
        }
    }

    // name => array(size, crc) for every file entry; directories are skipped
    $entries = array();

    for ($i = 0; $i < $zip->numFiles; $i++) {
        $stat = $zip->statIndex($i);

        if ($stat && isset($stat['name']) && substr($stat['name'], -1) !== '/') {
            // %08x prints the CRC the way hash_file('crc32b') does, on 32-bit
            // builds too, where the value arrives negative.
            $entries[$stat['name']] = array(
                'size' => isset($stat['size']) ? (int) $stat['size'] : -1,
                'crc'  => isset($stat['crc']) ? sprintf('%08x', $stat['crc']) : '',
            );
        }
    }

    $destination = rtrim($destination, '/\\') . '/';

    // Anything that cannot be overwritten is found before the archive is
    // opened on the tree. extractTo() opens each target for writing, and a
    // target that refuses - another owner, mode 0444 - stops it partway with
    // no word on which file. Deleting needs only write access to the folder,
    // so that is tried; a file that survives both is named and nothing is
    // extracted, because a package applied around one file is the incident
    // this function exists to prevent.
    clearstatcache();

    $blocked = array();

    // whether a file can be created in a directory: the directory, or the
    // nearest ancestor that exists, must be writable. Asked once per directory,
    // with pg_dir_writable(), not is_writable(): Windows folders (see there).
    $directory_writable = array();

    $can_create_in = function ($directory) use (&$directory_writable) {
        $directory = rtrim($directory, '/\\');

        if (isset($directory_writable[$directory])) {
            return $directory_writable[$directory];
        }

        $probe = $directory;

        while (($probe !== '') && !is_dir($probe)) {
            $parent = dirname($probe);

            if (($parent === $probe) || ($parent === '.') || ($parent === '')) {
                break;
            }

            $probe = $parent;
        }

        $directory_writable[$directory] = (($probe !== '') && pg_dir_writable($probe));

        return $directory_writable[$directory];
    };

    foreach ($entries as $entry => $expected) {
        $target = $destination . $entry;

        if (!file_exists($target)) {
            // a new file: its folder has to take it
            if (!$can_create_in(dirname($target))) {
                $blocked[] = $entry;
            }
        } elseif (!is_writable($target)) {
            // an existing file that refuses: the mode first, then the folder
            @chmod($target, 0644);

            if (!is_writable($target)) {
                @unlink($target);

                clearstatcache(true, $target);

                if (file_exists($target)) {
                    $blocked[] = $entry;
                }
            }
        }

        if (count($blocked) >= 25) {
            break;
        }
    }

    if ($blocked) {
        $zip->close();

        $failure['message'] = count($blocked) . ' file(s) on this server cannot be replaced or created by the web server - the file or its folder belongs to another system user or is read-only. Nothing was changed. Correct the permissions, or delete the files (FTP or the file manager), and run the update again: ' . implode(', ', array_slice($blocked, 0, 10)) . (count($blocked) > 10 ? ', …' : '');
        $failure['blocked'] = $blocked;
        return $failure;
    }

    $extracted = $zip->extractTo($destination);
    $zip->close();

    if (!$extracted) {
        $failure['message'] = 'Extraction failed. The web server may not have write permission for the software directory, or the disk may be full.';
        return $failure;
    }

    // Prove it: on disk, and the archive's bytes rather than whatever was
    // there before. The size is compared first because it is free; the CRC
    // costs one read of every file and is what actually settles it.
    clearstatcache();

    $missing = array();
    $stale = array();

    foreach ($entries as $entry => $expected) {
        $target = $destination . $entry;

        if (!file_exists($target)) {
            $missing[] = $entry;

            if (count($missing) >= 25) {
                break;
            }

            continue;
        }

        if (($expected['size'] >= 0) && (@filesize($target) !== $expected['size'])) {
            $stale[] = $entry;
        } elseif (($expected['crc'] !== '') && (@hash_file('crc32b', $target) !== $expected['crc'])) {
            $stale[] = $entry;
        }

        if (count($stale) >= 25) {
            break;
        }
    }

    if ($missing) {
        $failure['message'] = count($missing) . '+ file(s) from the archive were not written. The installation is now partially updated and should be repaired.';
        $failure['missing'] = $missing;
        return $failure;
    }

    if ($stale) {
        $failure['message'] = count($stale) . ' file(s) on disk are not the ones in the package after extraction - the server kept its old copy: ' . implode(', ', array_slice($stale, 0, 10)) . (count($stale) > 10 ? ', …' : '') . '. Delete them and run the update again.';
        $failure['stale'] = $stale;
        return $failure;
    }

    // 'files' are the absolute paths that were just written. The caller needs
    // them to invalidate the bytecode cache per file where a full reset is
    // refused (opcache.restrict_api) — the alternative is walking the whole
    // installation to find files that are already known here.
    $written = array();
    foreach ($entries as $entry => $expected) {
        $written[] = $destination . $entry;
    }

    return array('ok' => true, 'message' => '', 'missing' => array(), 'stale' => array(), 'blocked' => array(), 'files' => $written);
}

// ----------------------------------------------------------------------------
// Shared by the update screen and the Repair Software screen
// ----------------------------------------------------------------------------

/**
 * The version the update server offers this installation's channel
 * (pg_update_request_key()), asked the way the update's check step has always
 * asked: the installation's details posted as JSON, the API key, a verified
 * certificate (pg_curl_tls()), the proxy when there is one, and this
 * installation's User-Agent - sent with none, a request looks like an
 * anonymous client to the update server's firewall and is rejected.
 *
 * 'error' is '' on success, or:
 *   curl_missing      cURL is not installed
 *   curl_error        the server could not be reached ('curl_errno',
 *                     'curl_error')
 *   invalid_response  the answer named no version
 *
 * @return array('ok' => bool, 'version' => string, 'error' => string,
 *               'curl_errno' => int, 'curl_error' => string)
 */
function pg_update_server_version()
{
    $result = array(
        'ok' => false,
        'version' => '',
        'error' => '',
        'curl_errno' => 0,
        'curl_error' => '',
    );

    if (!function_exists('curl_init')) {
        $result['error'] = 'curl_missing';
        return $result;
    }

    $request = array();
    $request['hostname'] = HOSTNAME_SETTING;
    $request['url'] = URL_SCHEME . HOSTNAME_SETTING . PATH;
    $request['version'] = VERSION;
    $request['edition'] = EDITION;
    $request['uname'] = function_exists('php_uname') ? php_uname() : PHP_OS; // disable_functions on some hosts
    $request['os'] = PHP_OS;
    $request['web_server'] = $_SERVER['SERVER_SOFTWARE'];
    $request['php_version'] = phpversion();
    $request['mysql_version'] = db("SELECT VERSION()");
    $request['installer'] = INSTALLER;
    $request['private_label'] = PRIVATE_LABEL;
    $data = encode_json($request);
    $API = '59593DS72233483322T669223344';
    // Beta sites ask their own question; see pg_update_channel().
    $REQUEST = pg_update_request_key();

    // Verify the certificate. See pg_curl_tls() for why this matters most
    // on the update and licence channel.
    $answer = pg_curl_run('POST', 'https://www.kodpen.com/api2?API=' . $API . '&REQUEST=' . $REQUEST, array(
        'body'            => $data,
        'headers'         => array(
            'Content-Type: application/json',
            'Content-Length: ' . strlen($data)
        ),
        'timeout'         => 10,
        'connect_timeout' => 10,
        'forbid_reuse'    => true,
    ));
    $result['curl_errno'] = $answer['errno'];
    $result['curl_error'] = $answer['error'];

    if ($answer['errno'] !== 0) {
        $result['error'] = 'curl_error';
        return $result;
    }

    $response = decode_json($answer['body']);

    if (!is_array($response) || !isset($response['version'])) {
        $result['error'] = 'invalid_response';
        return $result;
    }

    $result['ok'] = true;
    $result['version'] = trim((string) $response['version']);

    return $result;
}

/**
 * Whether the software may be repaired with the package the update server
 * offers, and what to tell the person who asked.
 *
 * The repair downloads the channel's current package and writes it over the
 * software, so it is refused when:
 *   - update checks are off in data/config.php (SOFTWARE_UPDATE_CHECK false):
 *     the repair talks to the same server the operator chose not to ask;
 *   - the server names no version;
 *   - the server's version is older than this installation: writing it over
 *     newer files would take the code back below the database's schema.
 * The same version repairs in place; a newer one also updates, and the
 * upgrade screen that follows applies its schema steps.
 *
 * No database or network access.
 *
 * @param string $server_version  what the update server offers ('' if none)
 * @param string $installed       VERSION
 * @param bool   $checks_enabled  false when SOFTWARE_UPDATE_CHECK is false
 * @return array('ok' => bool, 'message' => string, 'newer' => bool)
 */
function pg_update_repair_decision($server_version, $installed, $checks_enabled)
{
    $server_version = trim((string) $server_version);
    $installed = (string) $installed;

    if (!$checks_enabled) {
        return array(
            'ok' => false,
            'message' => lang('Software update checks are turned off in data/config.php (SOFTWARE_UPDATE_CHECK), and the repair uses the same server. Turn them on to repair.'),
            'newer' => false,
        );
    }

    if (preg_match('/^\d+(\.\d+)*$/', $server_version) !== 1) {
        return array(
            'ok' => false,
            'message' => lang('The update server did not offer a version for this channel, so the software cannot be repaired now.'),
            'newer' => false,
        );
    }

    if (version_compare($server_version, $installed, '<')) {
        return array(
            'ok' => false,
            'message' => lang(array(
                'string' => 'The update server offers {var:1}, which is older than this installation ({var:2}). The software cannot be repaired until the server carries this version or a newer one.',
                'vars' => array($server_version, $installed),
            )),
            'newer' => false,
        );
    }

    if (version_compare($server_version, $installed, '>')) {
        return array(
            'ok' => true,
            'message' => lang(array(
                'string' => 'The server has {var:1}; the files will be replaced with it and the site updated. Downloading...',
                'vars' => array($server_version),
            )),
            'newer' => true,
        );
    }

    return array(
        'ok' => true,
        'message' => lang(array('string' => 'Downloading {var:1}...', 'vars' => array($server_version))),
        'newer' => false,
    );
}

/**
 * The version numbers of includes/migrations/versions.php, oldest first, read
 * from the file's source: the single-quoted numbers of the array it returns.
 *
 * The file cannot be included from the panel. It exits unless
 * INSTALL_OR_UPDATE is defined, and defining that constant in a panel request
 * changes how db() reports a failed query (it throws for the upgrade runner
 * instead of printing the error page).
 *
 * No database access.
 *
 * @param string $source
 * @return array
 */
function pg_upgrade_versions_from_source($source)
{
    $source = (string) $source;

    $start = strpos($source, 'return array(');

    if ($start === false) {
        return array();
    }

    $end = strpos($source, ');', $start);

    $body = ($end === false) ? substr($source, $start) : substr($source, $start, $end - $start);

    preg_match_all("/'(\\d{4}(?:\\.\\d+)*)'/", $body, $matches);

    return $matches[1];
}

/**
 * pg_upgrade_versions_from_source() for this installation's versions.php.
 *
 * @return array
 */
function pg_upgrade_versions_list()
{
    static $versions = null;

    if ($versions === null) {
        $versions = pg_upgrade_versions_from_source((string) @file_get_contents(PG_FUNCTIONS_DIR . '/includes/migrations/versions.php'));
    }

    return $versions;
}

/**
 * The versions the upgrade steps can be run again from: every version from
 * the first 2026 one up to the one before this installation's VERSION, oldest
 * first. Setting config.version to one of them makes the upgrade screen apply
 * every version after it again.
 *
 * The steps from 2026 on ask before they change anything
 * (install_add_column() and its family) and can run any number of times,
 * whatever year they belong to. The steps before 2026, in legacy.php, write
 * plain ALTER TABLE ... ADD and fail on a column that is already there, so
 * those versions are not offered.
 *
 * No database access.
 *
 * @param array|null  $versions  pg_upgrade_versions_list() when null
 * @param string|null $installed VERSION when null
 * @return array version numbers, oldest first; empty when $installed is not
 *               in the list
 */
function pg_upgrade_rerun_choices($versions = null, $installed = null)
{
    $versions = ($versions === null) ? pg_upgrade_versions_list() : array_values((array) $versions);
    $installed = ($installed === null) ? (defined('VERSION') ? (string) VERSION : '') : (string) $installed;

    $position = array_search($installed, $versions, true);

    if ($position === false) {
        return array();
    }

    // The list is in release order: everything from the first 2026 entry on
    // is offered, later years included.
    $first = false;

    foreach ($versions as $key => $version) {
        if (strpos($version, '2026') === 0) {
            $first = $key;
            break;
        }
    }

    if (($first === false) || ($first >= $position)) {
        return array();
    }

    return array_slice($versions, $first, $position - $first);
}

/**
 * The folders of the software the web server cannot write into, as the block
 * the update and repair screens show above their button.
 *
 * Both screens unpack a package over the tree as the web server, and a folder
 * that refuses keeps its old files through every extraction - the site then
 * runs new code on old files. So the folders are checked before anything is
 * downloaded, the same way the System Status card checks them: while one
 * refuses, the button stays off and a button beside the list opens them (0777
 * folders, 0666 files, see pg_write_permission_repair()). That button changes
 * who may write into the software directory, so it is an administrator's; a
 * manager sees the list and the name of who to ask. Its script is
 * pg_update_permissions_script().
 *
 * @param array $user validate_user()
 * @return array('blocked' => bool, 'html' => string) html is '' when nothing
 *               is blocked
 */
function pg_update_permissions_block($user)
{
    $block = array('blocked' => false, 'html' => '');

    if (!function_exists('pg_write_permission_scan')) {
        return $block;
    }

    $permissions = pg_write_permission_scan();

    if ($permissions['directories_count'] == 0) {
        return $block;
    }

    $block['blocked'] = true;

    $permissions_rows = '';

    foreach (array_slice($permissions['directories'], 0, 12, true) as $permissions_path => $permissions_mode) {
        $permissions_rows .= '<li><code>' . h($permissions_path) . '/</code> <span class="text-body-secondary">' . h($permissions_mode) . '</span></li>';
    }

    if ($permissions['directories_count'] > 12) {
        $permissions_rows .= '<li class="text-body-secondary">' . h(lang(array('string' => 'and {var:1} more', 'vars' => pg_format_number($permissions['directories_count'] - 12, 0)))) . '</li>';
    }

    if ((int) $user['role'] === 0) {
        $permissions_action = '
                <button type="button" class="btn btn-danger" id="write_permissions_fix"
                        data-busy-label="' . h(lang('Fixing')) . '"
                        data-idle-label="' . h(lang('Set the file permissions')) . '"
                        data-failed-label="' . h(lang('The permissions could not be changed.')) . '">
                    <i class="bi bi-wrench-adjustable me-1"></i><span id="write_permissions_fix_state">' . h(lang('Set the file permissions')) . '</span>
                </button>
                <span class="form-text d-block mt-2">' . h(lang('Sets these folders to 0777 and the files in them that refuse to 0666, so that both the web server and your FTP or file manager user can replace them. Folders that belong to another system user cannot be changed from here and are listed afterwards.')) . '</span>
                <div class="form-text mt-2 d-none" id="write_permissions_fix_result"></div>';
    } else {
        $permissions_action = '<span class="form-text d-block mt-2">' . h(lang('An administrator can open them from this screen, or set them writable over FTP.')) . '</span>';
    }

    $block['html'] = '
            <div class="alert alert-danger">
                <p class="form-text mb-2"><i class="bi bi-folder-x me-1"></i>' . h(lang(array(
                    'string' => 'The web server cannot write into {var:1} folder(s) of the software. The update cannot add or replace files there, so it does not start until they are opened:',
                    'vars' => pg_format_number($permissions['directories_count'], 0)
                ))) . '</p>
                <ul class="mb-2 small">' . $permissions_rows . '</ul>
                ' . $permissions_action . '
            </div>';

    return $block;
}

/**
 * The script behind the button of pg_update_permissions_block(): opens the
 * folders, then reads the screen again - the list and the screen's own
 * button are rendered from the scan, so a reload is what turns the button
 * on. Harmless on a screen without the block.
 *
 * @return string a <script> element
 */
function pg_update_permissions_script()
{
    return '
<script>
    $(function(){
        $("#write_permissions_fix").click(function(){
            var button = $(this),
                result = $("#write_permissions_fix_result");
            if (button.prop("disabled")) {
                return;
            }
            button.prop("disabled", true);
            $("#write_permissions_fix_state").text(button.attr("data-busy-label"));
            $.ajax({
                contentType: "application/json",
                url: "api.php",
                type: "POST",
                data: JSON.stringify({
                    action: "write_permissions_repair",
                    token: software_token
                }),
                success: function(response) {
                    result.text(response.message || button.attr("data-failed-label")).removeClass("d-none");
                    $("#write_permissions_fix_state").text(button.attr("data-idle-label"));
                    if (response.status == "success") {
                        window.setTimeout(function(){
                            window.location.reload();
                        }, 1500);
                    } else {
                        button.prop("disabled", false);
                    }
                },
                error: function() {
                    result.text(button.attr("data-failed-label")).removeClass("d-none");
                    $("#write_permissions_fix_state").text(button.attr("data-idle-label"));
                    button.prop("disabled", false);
                }
            });
        });
    });
</script>';
}

// ----------------------------------------------------------------------------
// The bytecode cache after files are replaced
// ----------------------------------------------------------------------------

/**
 * Drop what the bytecode cache holds of files that were just replaced.
 *
 * The cache still holds the OLD files. Two reasons this cannot be left to the
 * cache's own timestamp check:
 *
 *  - The update screen sends the browser to install/index.php as soon as the
 *    files are in place. Between the new files landing and the cache noticing
 *    them (opcache.revalidate_freq, two seconds by default) the upgrade would
 *    run the PREVIOUS version's code against the new schema.
 *  - Where the host turned timestamp validation off (opcache.validate_timestamps
 *    = 0) the old code keeps running until someone restarts PHP.
 *
 * extension_loaded() and function_exists() are not the question: the
 * extension can be compiled in while opcache.enable is off, when every call
 * returns false with a warning. The cache is asked whether it runs. A host
 * that blocks opcache.restrict_api answers nothing at all - status is
 * unreadable there but a reset may still be allowed, so "unknown" tries
 * anyway; per-file invalidation of the paths just written is the fallback
 * where a full reset is refused.
 *
 * @param array $files absolute paths that were written
 * @return array array('running' => bool|null (null: unknown), 'cleared' => bool)
 */
function pg_update_clear_opcache(array $files)
{
    $status = function_exists('opcache_get_status') ? @opcache_get_status(false) : null;
    $running = is_array($status) ? !empty($status['opcache_enabled']) : null;

    if ($running === false) {
        return array('running' => false, 'cleared' => false);
    }

    $cleared = false;

    if (function_exists('opcache_reset')) {
        $cleared = (bool) @opcache_reset();
    }

    if (!$cleared && function_exists('opcache_invalidate')) {
        foreach ($files as $file) {
            if ((substr((string) $file, -4) === '.php') && @opcache_invalidate($file, true)) {
                $cleared = true;
            }
        }
    }

    return array('running' => $running, 'cleared' => $cleared);
}

// ----------------------------------------------------------------------------
// The backup an update takes before it replaces the files, and the rollback
// ----------------------------------------------------------------------------
//
// Before the replace step writes the package over the software, the files it
// is about to overwrite and the database are put into one archive,
// data/backups/pre-update-<version>-<YmdHis>.zip:
//
//   sql.sql        the database, the dump every backup writes
//                  (pg_backup_dump_database()); the installation screen
//                  restores it like any backup archive, and keeps the site's
//                  files because the archive has no files/ folder
//   software.zip   the files the package replaces, as they were, under the
//                  names the package uses (relative to the web root)
//   manifest.json  the version they belong to, and the package's files that
//                  did not exist before the update
//
// A rollback (Repair Software, administrators) writes software.zip back with
// pg_extract_archive() - the update's own verified extraction - deletes the
// files the update added, and sets config.version back to the version of the
// files, so code and recorded version agree again. The database itself is
// put back only by restoring the archive on the installation screen, which
// loses what was written since the update; the screen says so.
//
// The archives sit in data/backups, where a manager can upload a backup. So a
// rollback, which replaces executable code, takes only an archive this site
// made: its SHA-256 is recorded in data/update_backups.json (outside the
// backup folder) when it is written and checked before anything is extracted.

/**
 * Whether an update backs up first (Site Settings, General, Update Channel).
 * On until the 2026.11.0 column says otherwise.
 *
 * @return bool
 */
function pg_update_backup_enabled()
{
    return defined('UPDATE_BACKUP') ? (bool) UPDATE_BACKUP : true;
}

/**
 * The archive name of a backup taken before an update.
 *
 * @param string $version the installed version, whose files are backed up
 * @param int    $time
 * @return string
 */
function pg_update_backup_name($version, $time)
{
    return 'pre-update-' . preg_replace('/[^0-9.]/', '', (string) $version) . '-' . date('YmdHis', (int) $time) . '.zip';
}

/**
 * Whether a name is one pg_update_backup_name() writes. The gate in front of
 * every read, delete and rollback.
 *
 * @param mixed $name
 * @return bool
 */
function pg_update_backup_is_name($name)
{
    return is_string($name) && (preg_match('/^pre-update-\d{4}(\.\d+)*-\d{14}\.zip$/', $name) === 1);
}

/**
 * An archive entry name made safe to write under the web root, or '' when it
 * is not a plain relative file name: absolute, a drive letter, a directory, a
 * '..' or '.' segment, an empty segment or a NUL byte.
 *
 * @param string $name
 * @return string
 */
function pg_update_safe_entry($name)
{
    $name = str_replace('\\', '/', (string) $name);

    if (($name === '') || (substr($name, -1) === '/') || ($name[0] === '/')
        || (preg_match('#^[a-zA-Z]:#', $name) === 1) || (strpos($name, "\0") !== false)) {
        return '';
    }

    foreach (explode('/', $name) as $part) {
        if (($part === '') || ($part === '.') || ($part === '..')) {
            return '';
        }
    }

    return $name;
}

/**
 * Where the record of the archives this site made is kept.
 *
 * @return string
 */
function pg_update_backup_records_path()
{
    return PG_FUNCTIONS_DIR . '/data/update_backups.json';
}

/**
 * The record: archive name => array('sha256', 'version', 'created', 'files',
 * 'added', 'package' - the SHA-256 of the package it was taken before).
 * Entries that are not well formed are left out.
 *
 * @return array
 */
function pg_update_backup_records()
{
    $decoded = json_decode((string) @file_get_contents(pg_update_backup_records_path()), true);
    $records = array();

    if (!is_array($decoded)) {
        return $records;
    }

    foreach ($decoded as $name => $record) {
        if (pg_update_backup_is_name($name) && is_array($record) && isset($record['sha256'])
            && (preg_match('/^[0-9a-f]{64}$/', (string) $record['sha256']) === 1)) {
            $records[$name] = array(
                'sha256'  => (string) $record['sha256'],
                'version' => isset($record['version']) ? (string) $record['version'] : '',
                'created' => isset($record['created']) ? (int) $record['created'] : 0,
                'files'   => isset($record['files']) ? (int) $record['files'] : 0,
                'added'   => isset($record['added']) ? (int) $record['added'] : 0,
                'package' => (isset($record['package']) && (preg_match('/^[0-9a-f]{64}$/', (string) $record['package']) === 1)) ? (string) $record['package'] : '',
            );
        }
    }

    return $records;
}

/**
 * Write the record, whole, through a temporary file.
 *
 * @param array $records
 * @return bool
 */
function pg_update_backup_records_write(array $records)
{
    $path = pg_update_backup_records_path();
    $temporary = $path . '.' . substr(md5(uniqid('', true)), 0, 8) . '.tmp';

    if (@file_put_contents($temporary, json_encode($records, JSON_PRETTY_PRINT)) === false) {
        @unlink($temporary);
        return false;
    }

    // rename() does not replace an existing file on Windows.
    if (!@rename($temporary, $path)) {
        @unlink($path);

        if (!@rename($temporary, $path)) {
            @unlink($temporary);
            return false;
        }
    }

    return true;
}

/**
 * The file entries of an update package, by safe name.
 *
 * @param string $package path of the downloaded package
 * @return array array('ok' => bool, 'error' => string, 'entries' => array)
 */
function pg_update_package_entries($package)
{
    $result = array('ok' => false, 'error' => '', 'entries' => array());

    $zip = new ZipArchive();

    if (!is_file($package) || ($zip->open($package) !== true)) {
        $result['error'] = lang('The downloaded package could not be opened.');
        return $result;
    }

    for ($index = 0; $index < $zip->numFiles; $index++) {

        $stat = $zip->statIndex($index);

        if (($stat === false) || !isset($stat['name']) || (substr($stat['name'], -1) === '/')) {
            continue;
        }

        $entry = pg_update_safe_entry($stat['name']);

        if ($entry !== '') {
            $result['entries'][] = $entry;
        }
    }

    $zip->close();

    if (!$result['entries']) {
        $result['error'] = lang('The downloaded package holds no files.');
        return $result;
    }

    $result['ok'] = true;

    return $result;
}

/**
 * The archives taken before updates that are in the backup folder, newest
 * first, with what the record says of each.
 *
 * @return array of array('name', 'version', 'created', 'size', 'files', 'added', 'recorded')
 */
function pg_update_backup_list()
{
    $directory = pg_backup_directory();
    $entries = is_dir($directory) ? @scandir($directory) : false;
    $records = pg_update_backup_records();
    $list = array();

    foreach ((array) $entries as $name) {

        if (!pg_update_backup_is_name($name) || !is_file($directory . '/' . $name)) {
            continue;
        }

        $recorded = isset($records[$name]);

        preg_match('/^pre-update-(.+)-(\d{14})\.zip$/', $name, $parts);

        $list[] = array(
            'name'     => $name,
            'version'  => $recorded ? $records[$name]['version'] : $parts[1],
            'created'  => $recorded ? $records[$name]['created'] : (int) @filemtime($directory . '/' . $name),
            'size'     => (float) @filesize($directory . '/' . $name),
            'files'    => $recorded ? $records[$name]['files'] : 0,
            'added'    => $recorded ? $records[$name]['added'] : 0,
            'recorded' => $recorded,
            'stamp'    => $parts[2],
        );
    }

    usort($list, function ($a, $b) {
        return strcmp($b['stamp'], $a['stamp']);
    });

    return $list;
}

/**
 * Which recorded archives a retention of $keep removes.
 *
 * Ordered by the time the record gives, never by the name: anyone who may
 * upload into the backup folder chooses names. Kept whatever their place:
 * the archive just written ($protect), and the newest archive of every
 * version, so that repairing the new version three times does not rotate out
 * the only copy of the old version's files.
 *
 * @param array  $records pg_update_backup_records()
 * @param int    $keep
 * @param string $protect
 * @return array names to delete
 */
function pg_update_backup_prune_list(array $records, $keep, $protect = '')
{
    uksort($records, function ($a, $b) use ($records) {
        if ($records[$a]['created'] !== $records[$b]['created']) {
            return ($records[$a]['created'] > $records[$b]['created']) ? -1 : 1;
        }
        return strcmp($b, $a);
    });

    $keep = max(1, (int) $keep);
    $versions = array();
    $position = 0;
    $delete = array();

    foreach ($records as $name => $record) {

        $newest_of_version = !isset($versions[$record['version']]);
        $versions[$record['version']] = true;

        if (($position < $keep) || ($name === $protect) || $newest_of_version) {
            $position++;
            continue;
        }

        $delete[] = $name;
    }

    return $delete;
}

/**
 * Apply the retention to the archives this site recorded, and drop the
 * record of every archive that is no longer in the folder. An archive the
 * site did not record is never deleted here: it is not this code's to judge;
 * the Backup Manager deletes it.
 *
 * @param int    $keep
 * @param string $protect the archive just written
 * @return array the names deleted
 */
function pg_update_backup_prune($keep = 3, $protect = '')
{
    $directory = pg_backup_directory();
    $records = pg_update_backup_records();
    $changed = false;

    foreach (array_keys($records) as $name) {
        if (!is_file($directory . '/' . $name)) {
            unset($records[$name]);
            $changed = true;
        }
    }

    $deleted = array();

    foreach (pg_update_backup_prune_list($records, $keep, $protect) as $name) {

        // Asked again at the point of deletion: nothing but such a name
        // reaches unlink().
        if (pg_update_backup_is_name($name) && @unlink($directory . '/' . $name)) {
            $deleted[] = $name;
            unset($records[$name]);
            $changed = true;
        }
    }

    if ($changed) {
        pg_update_backup_records_write($records);
    }

    return $deleted;
}

/**
 * Back up the files an update package is about to replace, and the database,
 * into one archive in the backup folder.
 *
 * Built in a working folder under data/temp and moved into the backup folder
 * only when complete, so a failed run leaves no half archive behind it. The
 * database goes first: of the two it is what cannot be fetched again.
 *
 * @param string $package path of the downloaded package
 * @return array array('ok' => bool, 'error' => string, 'name' => string,
 *               'size' => float, 'files' => int, 'added' => int[,
 *               'reused' => true when an earlier archive of the same day and
 *               package was kept instead])
 */
function pg_update_backup_create($package)
{
    $result = array('ok' => false, 'error' => '', 'name' => '', 'size' => 0, 'files' => 0, 'added' => 0);

    if (!class_exists('ZipArchive')) {
        $result['error'] = lang('The ZipArchive extension of PHP is not available on this server.');
        return $result;
    }

    // The dump goes through PDO; without it the dump would end the request
    // with an Error rather than an answer the screen can show.
    if (!extension_loaded('pdo_mysql')) {
        $result['error'] = lang('The PHP extension pdo_mysql is not available, so the database cannot be backed up.');
        return $result;
    }

    $listed = pg_update_package_entries($package);

    if (!$listed['ok']) {
        $result['error'] = $listed['error'];
        return $result;
    }

    $now = time();
    $package_hash = (string) @hash_file('sha256', $package);

    // A retry after a replace that failed part-way: the files on disk may
    // already be partly the new version's, so a new backup would save a mix
    // under the old version's name. The archive taken before this same
    // package earlier the same day holds the files as they were, and is
    // used again.
    foreach (pg_update_backup_records() as $recorded_name => $recorded) {
        if (($recorded['version'] === (string) VERSION) && ($recorded['package'] !== '') && hash_equals($recorded['package'], $package_hash)
            && (($now - $recorded['created']) < 86400) && is_file(pg_backup_directory() . '/' . $recorded_name)) {
            $result['ok'] = true;
            $result['reused'] = true;
            $result['name'] = $recorded_name;
            $result['size'] = (float) @filesize(pg_backup_directory() . '/' . $recorded_name);
            $result['files'] = $recorded['files'];
            $result['added'] = $recorded['added'];
            return $result;
        }
    }

    $root = dirname(PG_FUNCTIONS_DIR);
    $sources = array();
    $added = array();
    $unreadable = array();

    foreach ($listed['entries'] as $entry) {

        $path = $root . '/' . $entry;

        if (is_file($path)) {
            if (!is_readable($path)) {
                $unreadable[] = $entry;
            }
            $sources[] = array('path' => $path, 'local' => $entry);
        } elseif (!file_exists($path)) {
            $added[] = $entry;
        }
    }

    // The archive writer skips a file it cannot read, which would leave that
    // file out of a rollback without a word.
    if ($unreadable) {
        $result['error'] = lang(array(
            'string' => 'These files cannot be read by the web server, so they cannot be backed up: {var:1}',
            'vars'   => array(implode(', ', array_slice($unreadable, 0, 10)) . ((count($unreadable) > 10) ? ', …' : '')),
        ));
        return $result;
    }

    $name = pg_update_backup_name(VERSION, $now);
    $work = PG_FUNCTIONS_DIR . '/data/temp/update_backup_' . date('YmdHis', $now) . '_' . substr(md5(uniqid('', true)), 0, 8);

    if (!@mkdir($work, 0755, true)) {
        $result['error'] = lang(array('string' => 'The folder {var:1} could not be created.', 'vars' => array('data/temp/' . basename($work))));
        return $result;
    }

    $fail = function ($error) use (&$result, $work) {
        pg_backup_delete_tree($work);
        $result['error'] = (string) $error;
        return $result;
    };

    try {
        pg_backup_dump_database($work . '/sql.sql');
    } catch (\Throwable $e) {
        return $fail($e->getMessage());
    }

    if (!pg_backup_zip_create($work . '/software.zip', $sources)) {
        return $fail(lang(array('string' => 'The archive {var:1} could not be written.', 'vars' => array('software.zip'))));
    }

    $manifest = array(
        'version' => (string) VERSION,
        'created' => $now,
        'package' => basename((string) $package),
        'files'   => count($sources),
        'added'   => $added,
    );

    if (@file_put_contents($work . '/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT)) === false) {
        return $fail(lang(array('string' => '{var:1} could not be written to disk.', 'vars' => array('manifest.json'))));
    }

    $archive = $work . '/' . $name;

    $packed = pg_backup_zip_create($archive, array(
        array('path' => $work . '/sql.sql', 'local' => 'sql.sql'),
        array('path' => $work . '/software.zip', 'local' => 'software.zip'),
        array('path' => $work . '/manifest.json', 'local' => 'manifest.json'),
    ));

    $directory = pg_backup_directory();

    if (!is_dir($directory)) {
        @mkdir($directory, 0777, true);
    }

    if (!$packed || !@rename($archive, $directory . '/' . $name)) {
        return $fail(lang(array('string' => 'The archive {var:1} could not be written.', 'vars' => array($name))));
    }

    pg_backup_delete_tree($work);

    $records = pg_update_backup_records();

    $records[$name] = array(
        'sha256'  => (string) hash_file('sha256', $directory . '/' . $name),
        'version' => (string) VERSION,
        'created' => $now,
        'files'   => count($sources),
        'added'   => count($added),
        'package' => $package_hash,
    );

    // Without its record the archive can still be restored as a database
    // backup, but never rolled back to; an update that cannot be undone is
    // not what the operator asked for.
    if (!pg_update_backup_records_write($records)) {
        @unlink($directory . '/' . $name);
        $result['error'] = lang(array('string' => '{var:1} could not be written to disk.', 'vars' => array('data/update_backups.json')));
        return $result;
    }

    foreach (pg_update_backup_prune(3, $name) as $old) {
        log_activity(lang(array('string' => 'Old backup taken before an update {var:1} was deleted.', 'vars' => array($old))), 'SYSTEM');
    }

    $result['ok'] = true;
    $result['name'] = $name;
    $result['size'] = (float) @filesize($directory . '/' . $name);
    $result['files'] = count($sources);
    $result['added'] = count($added);

    return $result;
}

/**
 * Put back the software files of an archive taken before an update.
 *
 * Only an archive this site recorded, unchanged since (SHA-256), is used:
 * copied into a private working folder first, and only that copy is hashed
 * and read. Its software.zip is copied out and checked against the archive's
 * own checksum,
 * then written over the software with the update's verified extraction; the
 * files the update added are deleted, the bytecode cache and the caches that
 * remember the old files are cleared, and config.version is set back to the
 * version of the files when the database says a later one.
 *
 * @param string $name the archive's name in the backup folder
 * @return array array('ok' => bool, 'message' => string, 'version' => string,
 *               'restored' => int, 'removed' => int)
 */
function pg_update_rollback($name)
{
    $result = array('ok' => false, 'message' => '', 'version' => '', 'restored' => 0, 'removed' => 0);

    if (!pg_update_backup_is_name($name)) {
        $result['message'] = lang('That backup cannot be used.');
        return $result;
    }

    $path = pg_backup_directory() . '/' . $name;
    $records = pg_update_backup_records();

    if (!is_file($path)) {
        $result['message'] = lang('That backup is not in the backup folder any more.');
        return $result;
    }

    if (!isset($records[$name])) {
        $result['message'] = lang('This archive was not made by an update on this site, so it is not used to replace the software files. It can still be restored as a database backup on the installation screen.');
        return $result;
    }

    if (!class_exists('ZipArchive')) {
        $result['message'] = lang('The ZipArchive extension of PHP is not available on this server.');
        return $result;
    }

    $work = PG_FUNCTIONS_DIR . '/data/temp/rollback_' . date('YmdHis') . '_' . substr(md5(uniqid('', true)), 0, 8);

    if (!@mkdir($work, 0755, true)) {
        $result['message'] = lang(array('string' => 'The folder {var:1} could not be created.', 'vars' => array('data/temp/' . basename($work))));
        return $result;
    }

    $fail = function ($message) use (&$result, $work) {
        pg_backup_delete_tree($work);
        $result['message'] = (string) $message;
        return $result;
    };

    // Copied into the private working folder first, and only the copy is
    // hashed and read. A manager may write into the backup folder, so the
    // file there could be replaced between a check of it and a read of it,
    // and the entries would then come from an archive nobody checked.
    $archive = $work . '/' . $name;

    if (!@copy($path, $archive)) {
        return $fail(lang(array('string' => '{var:1} could not be written to disk.', 'vars' => array('data/temp/' . basename($work) . '/' . $name))));
    }

    if (!hash_equals($records[$name]['sha256'], (string) @hash_file('sha256', $archive))) {
        return $fail(lang('The archive has changed since the update made it, so it is not used to replace the software files.'));
    }

    $zip = new ZipArchive();

    if ($zip->open($archive) !== true) {
        return $fail(lang('The archive could not be opened. It might be damaged.'));
    }

    $manifest = json_decode((string) $zip->getFromName('manifest.json'), true);
    $index = $zip->locateName('software.zip');

    if (!is_array($manifest) || !isset($manifest['version']) || (preg_match('/^\d{4}(\.\d+)*$/', (string) $manifest['version']) !== 1) || ($index === false)) {
        $zip->close();
        return $fail(lang('The archive is not a backup taken before an update.'));
    }

    $copied = pg_restore_archive_copy($zip, $index, $work . '/software.zip');

    $zip->close();

    if ($copied !== '') {
        return $fail($copied);
    }

    $root = dirname(PG_FUNCTIONS_DIR);

    $extract = pg_extract_archive($work . '/software.zip', $root . '/');

    pg_backup_delete_tree($work);

    if (!$extract['ok']) {
        log_activity('software rollback extraction failed: ' . $extract['message'], isset($_SESSION['sessionusername']) ? $_SESSION['sessionusername'] : 'SYSTEM');
        $result['message'] = $extract['message'];
        return $result;
    }

    $result['restored'] = count($extract['files']);

    // The files the update brought that the version rolled back to did not
    // have. Only names the archive recorded, made safe, under the web root.
    $added_entries = (isset($manifest['added']) && is_array($manifest['added'])) ? $manifest['added'] : array();

    foreach ($added_entries as $added) {

        $entry = pg_update_safe_entry($added);

        if (($entry !== '') && is_file($root . '/' . $entry) && @unlink($root . '/' . $entry)) {
            $result['removed']++;
        }
    }

    $opcache = pg_update_clear_opcache($extract['files']);

    if (!$opcache['cleared'] && ($opcache['running'] === true)) {
        log_activity('software rollback: the bytecode cache could not be cleared - run Purge Cache if the rollback does not take effect', 'SYSTEM');
    }

    foreach (array('system_status_cache.json', 'hash_reference_state.json') as $cache) {
        if (file_exists(PG_FUNCTIONS_DIR . '/data/temp/' . $cache)) {
            @unlink(PG_FUNCTIONS_DIR . '/data/temp/' . $cache);
        }
    }

    if (function_exists('pg_schema_cache_clear')) {
        pg_schema_cache_clear();
    }

    $result['version'] = (string) $manifest['version'];

    // The files are the old version's now; the database is told so, or the
    // next update would think the old version's steps already ran. The
    // schema keeps what the newer steps added, which the older code ignores;
    // the next update runs those steps again, and each one asks before it
    // changes anything.
    if (defined('VERSION') && (version_compare((string) VERSION, $result['version'], '>'))) {
        db("UPDATE config SET version = '" . e($result['version']) . "'");
    }

    $result['ok'] = true;
    $result['message'] = lang(array(
        'string' => 'The software files of {var:1} are back ({var:2} files written, {var:3} files of the update removed).',
        'vars'   => array($result['version'], $result['restored'], $result['removed']),
    ));

    return $result;
}
