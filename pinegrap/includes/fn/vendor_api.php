<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The Kodpen API client (api.kodpen.com/v1): the switch that turns it on,
 * the address every call starts from, the headers and timeouts every call
 * carries, the reader of an application/problem+json answer and the
 * canonical site name the licence server binds a licence's site rights to:
 * what the licence check (license_check(), includes/fn/output.php) and the
 * software update (includes/fn/update.php) build their calls to it from.
 *
 * While PG_VENDOR_API_V1 is false nothing here makes a call:
 * pg_vendor_api_request() refuses before cURL runs, and the licence check
 * and the update ask the www.kodpen.com/api2 server as before.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// The switch. Its line keeps exactly this form, alone on the line: the
// hosting platform reads this file as text to tell which server a site asks,
// and tests/vendor_api_test.php holds the line to it.
define('PG_VENDOR_API_V1', false);

/**
 * The address every call starts from: https://api.kodpen.com/v1, or
 * PG_VENDOR_API_URL when data/config.php sets one for a test server.
 *
 * @return string '' when PG_VENDOR_API_URL is anything but an https address
 *                ending in /v1: then nothing is called, the production
 *                server neither
 */
function pg_vendor_api_base()
{
    return pg_vendor_api_base_from(defined('PG_VENDOR_API_URL') ? PG_VENDOR_API_URL : null);
}

/**
 * pg_vendor_api_base() for a PG_VENDOR_API_URL value (null: not set). Taken
 * only as https, a host, an optional port and path, ending in /v1: no user,
 * query, fragment, blank or control byte.
 *
 * @param mixed $override
 * @return string
 */
function pg_vendor_api_base_from($override)
{
    if ($override === null) {
        return 'https://api.kodpen.com/v1';
    }

    if (is_string($override) && (preg_match('#\Ahttps://[A-Za-z0-9._-]+(?::[0-9]{1,5})?(?:/[A-Za-z0-9._~-]+)*/v1\z#', $override) === 1)) {
        return $override;
    }

    return '';
}

/**
 * The address of a call: the base followed by $path ('/licenses/verify').
 *
 * @param string $path
 * @return string '' when there is no base
 */
function pg_vendor_api_url($path)
{
    $base = pg_vendor_api_base();

    return ($base === '') ? '' : $base . $path;
}

/**
 * The version a call may send (the body's version, the query's from):
 * VERSION when it is a release number, else '' and the call leaves it out.
 *
 * @return string
 */
function pg_vendor_api_version()
{
    return pg_vendor_api_version_value(defined('VERSION') ? VERSION : '');
}

/**
 * $version when it is a release number such as 2026.11.0, else ''.
 *
 * @param mixed $version
 * @return string
 */
function pg_vendor_api_version_value($version)
{
    return (is_string($version) && (preg_match('/\A[0-9]{4}\.[0-9]{1,2}\.[0-9]{1,3}\z/', $version) === 1)) ? $version : '';
}

/**
 * The X-Pinegrap-Site value: the hostname setting as it is, which the
 * server canonicalises by the rule pg_vendor_api_canonical_site() follows.
 *
 * @param mixed $hostname HOSTNAME_SETTING
 * @return string '' when it cannot go out as a header value: empty, over
 *                253 bytes or holding a control byte. A release call or a
 *                download then goes without the header, and the licence
 *                check is not made (site_required).
 */
function pg_vendor_api_site_value($hostname)
{
    if (!is_string($hostname) || ($hostname === '') || (strlen($hostname) > 253) || (preg_match('/[\x00-\x1F\x7F]/', $hostname) === 1)) {
        return '';
    }

    return $hostname;
}

/**
 * SERVER_SOFTWARE up to its first space or '(': 'Apache/2.4.58 (Ubuntu)'
 * gives 'Apache/2.4.58'.
 *
 * @param mixed $software
 * @return string
 */
function pg_vendor_api_server_software($software)
{
    $software = is_string($software) ? $software : '';

    return (string) substr($software, 0, strcspn($software, ' ('));
}

/**
 * The X-Pinegrap-Env value: the edition, PHP, the database server, the web
 * server and the update channel, and nothing more about the machine (no
 * uname, address, operating system or installer flag). Each value loses ';'
 * and every control byte (CR and LF among them) and is cut to 64 bytes, so
 * no value starts a pair or a header of its own and the whole stays under
 * 512 bytes.
 *
 * @param array $parts edition, php, db, server, channel
 * @return string
 */
function pg_vendor_api_env_value($parts)
{
    $pairs = array();

    foreach (array('edition', 'php', 'db', 'server', 'channel') as $name) {
        $value = (isset($parts[$name]) && is_scalar($parts[$name])) ? (string) $parts[$name] : '';
        $value = trim((string) preg_replace('/[;\x00-\x1F\x7F]/', '', $value));

        $pairs[] = $name . '=' . trim((string) substr($value, 0, 64));
    }

    return implode('; ', $pairs);
}

/**
 * pg_vendor_api_env_value() for this installation.
 *
 * @return string
 */
function pg_vendor_api_env()
{
    $db = '';

    if (class_exists('db', false) && (db::$con instanceof mysqli)) {
        $db = pg_db_server_version((string) mysqli_get_server_info(db::$con))['label'];
    }

    return pg_vendor_api_env_value(array(
        'edition' => defined('EDITION') ? EDITION : '',
        'php'     => PHP_VERSION,
        'db'      => $db,
        'server'  => pg_vendor_api_server_software(isset($_SERVER['SERVER_SOFTWARE']) ? $_SERVER['SERVER_SOFTWARE'] : ''),
        'channel' => pg_update_channel(),
    ));
}

/**
 * The pg_curl_run() options of a call to the Kodpen API. Every call: no
 * redirect, 4 s to connect, X-Pinegrap-Site and X-Pinegrap-Env when they
 * have a value. A JSON call, the default, accepts JSON or a problem object
 * and takes at most 8 s and 64 KB of body.
 *
 * @param string $site    pg_vendor_api_site_value(); '' leaves the header out
 * @param string $env     pg_vendor_api_env_value(); '' leaves the header out
 * @param array  $options (all optional)
 *   body     the request body, JSON, sent with Content-Type: application/json
 *   headers  more "Name: value" lines (Authorization)
 *   json     false for an answer that is not JSON (the release signature,
 *            the package): no Accept line
 *   timeout  seconds for the whole call (default 8; the package takes 120)
 *   cap      bytes the body may have (default 64 KB)
 *   sink     an open file the body is written to (the package)
 * @return array
 */
function pg_vendor_api_options($site, $env, $options)
{
    $headers = array();

    if (!isset($options['json']) || ($options['json'] !== false)) {
        $headers[] = 'Accept: application/json, application/problem+json';
    }

    if (isset($options['body'])) {
        $headers[] = 'Content-Type: application/json';
    }

    if ((string) $site !== '') {
        $headers[] = 'X-Pinegrap-Site: ' . $site;
    }

    if ((string) $env !== '') {
        $headers[] = 'X-Pinegrap-Env: ' . $env;
    }

    if (!empty($options['headers'])) {
        $headers = array_merge($headers, array_values($options['headers']));
    }

    $out = array(
        'headers'         => $headers,
        'timeout'         => isset($options['timeout']) ? (int) $options['timeout'] : 8,
        'connect_timeout' => 4,
        'follow'          => 0,
        'cap'             => isset($options['cap']) ? (int) $options['cap'] : 65536,
    );

    if (isset($options['body'])) {
        $out['body'] = (string) $options['body'];
    }

    if (isset($options['sink'])) {
        $out['sink'] = $options['sink'];
    }

    return $out;
}

/**
 * One call to the Kodpen API through pg_curl_run(), with
 * pg_vendor_api_options(). Nothing is called while PG_VENDOR_API_V1 is off,
 * without an address ($url '', see pg_vendor_api_url()), or, with
 * site_required, when the site name cannot be sent (the licence check binds
 * the licence to it): the answer is then pg_curl_run()'s shape with the
 * reason in error and errno 0.
 *
 * @param string $method
 * @param string $url     pg_vendor_api_url(), or an address an answer of the
 *                        API gave, which the caller has checked
 * @param array  $options pg_vendor_api_options(), plus site_required
 * @return array pg_curl_run()'s answer
 */
function pg_vendor_api_request($method, $url, $options = array())
{
    $site = pg_vendor_api_site_value(defined('HOSTNAME_SETTING') ? HOSTNAME_SETTING : '');
    $refused = '';

    if (PG_VENDOR_API_V1 !== true) {
        $refused = 'The Kodpen API client is switched off.';
    } elseif ((string) $url === '') {
        $refused = 'There is no Kodpen API address: PG_VENDOR_API_URL is not an https address ending in /v1.';
    } elseif (!empty($options['site_required']) && ($site === '')) {
        $refused = 'The site name cannot be sent to the Kodpen API.';
    }

    if ($refused !== '') {
        return array('ok' => false, 'status' => 0, 'body' => '', 'errno' => 0, 'error' => $refused,
            'connect' => 0, 'request_size' => 0, 'content_length' => -1, 'content_type' => '');
    }

    return pg_curl_run($method, $url, pg_vendor_api_options($site, pg_vendor_api_env(), $options));
}

/**
 * The media type of a Content-Type value, lower case and without its
 * parameters: 'application/problem+json; charset=utf-8' gives
 * 'application/problem+json'.
 *
 * @param mixed $content_type
 * @return string
 */
function pg_vendor_api_media_type($content_type)
{
    $parts = explode(';', is_string($content_type) ? $content_type : '', 2);

    return strtolower(trim($parts[0]));
}

/**
 * The problem an answer reports: its HTTP status and the code of its
 * application/problem+json object. The client decides by the two alone:
 * title, detail, Retry-After and every other member are never read.
 *
 * @param array $answer pg_curl_run()'s answer: status, content_type, body
 * @return array|null array('status' => int, 'code' => string); null unless
 *                    the status is 4xx or 5xx and the body is a problem
 *                    object whose code is a lower-case name (an HTML page
 *                    or an empty body is no problem object)
 */
function pg_vendor_api_problem($answer)
{
    $status = isset($answer['status']) ? (int) $answer['status'] : 0;

    if (($status < 400) || ($status > 599)
        || (pg_vendor_api_media_type(isset($answer['content_type']) ? $answer['content_type'] : '') !== 'application/problem+json')) {
        return null;
    }

    $data = json_decode(isset($answer['body']) ? (string) $answer['body'] : '');

    if (!($data instanceof stdClass) || !isset($data->code) || !is_string($data->code)
        || (preg_match('/\A[a-z][a-z0-9_]{0,63}\z/', $data->code) !== 1)) {
        return null;
    }

    return array('status' => $status, 'code' => $data->code);
}

/**
 * The canonical site name: the rule the licence server applies to
 * X-Pinegrap-Site before comparing it with the names that hold a licence's
 * site rights (tests/fixtures/vendor_api/site_host.json). In order: A-Z
 * lower-cased, a final port and one trailing dot removed, a name with a
 * byte outside ASCII converted to its xn-- form, one leading "www."
 * removed; then only 1 to 253 characters of dot-separated labels of a-z,
 * 0-9 and '-' are accepted. Every step reads up to the very end of the
 * name, so a final line break is refused, never trimmed.
 *
 * @param mixed     $name
 * @param bool|null $idn  whether idn_to_ascii() may be used; null asks PHP
 * @return string|null|false the name; null when it is refused; false when
 *                           it has a byte outside ASCII and intl is
 *                           missing, so it cannot be told here
 */
function pg_vendor_api_canonical_site($name, $idn = null)
{
    if (!is_string($name)) {
        return null;
    }

    // strtolower() follows the locale before PHP 8.2.
    $name = strtr($name, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz');
    $name = (string) preg_replace('/:[0-9]+\z/', '', $name);

    if (substr($name, -1) === '.') {
        $name = (string) substr($name, 0, -1);
    }

    if (preg_match('/[\x80-\xFF]/', $name) === 1) {
        if ($idn === null) {
            $idn = function_exists('idn_to_ascii') && defined('INTL_IDNA_VARIANT_UTS46');
        }

        if (!$idn) {
            return false;
        }

        $name = idn_to_ascii($name, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);

        if (!is_string($name)) {
            return null;
        }
    }

    if (strpos($name, 'www.') === 0) {
        $name = (string) substr($name, 4);
    }

    $label = '[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?';

    if ((strlen($name) > 253) || (preg_match('/\A' . $label . '(?:\.' . $label . ')*\z/', $name) !== 1)) {
        return null;
    }

    return $name;
}

/**
 * Whether a canonical name is one only this machine or its network knows:
 * localhost, a name under .test or .local, or an address in 10/8,
 * 172.16/12, 192.168/16 or 127/8. Such a name still takes a site right of
 * its own, which the licence key field warns about.
 *
 * @param mixed $canonical pg_vendor_api_canonical_site()
 * @return bool
 */
function pg_vendor_api_site_local($canonical)
{
    $canonical = is_string($canonical) ? $canonical : '';

    if (($canonical === 'localhost') || (substr($canonical, -5) === '.test') || (substr($canonical, -6) === '.local')) {
        return true;
    }

    if (filter_var($canonical, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
        return false;
    }

    $octets = array_map('intval', explode('.', $canonical));

    return ($octets[0] === 10) || ($octets[0] === 127)
        || (($octets[0] === 192) && ($octets[1] === 168))
        || (($octets[0] === 172) && ($octets[1] >= 16) && ($octets[1] <= 31));
}
