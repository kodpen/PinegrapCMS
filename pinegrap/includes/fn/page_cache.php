<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Anonymous page cache: which page answers may be kept by a shared cache, and
 * the standard headers that let a CDN or reverse proxy keep them.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// A module of functions.php. PAGE_CACHE_TTL in data/config.php (Site
// Settings, Features, Page Cache) is the lifetime in seconds; 0 or missing is
// off, and then nothing here changes a response.
//
// While it is on, get_page.php asks pg_page_cache_apply() about every page it
// is about to print. A page drawn the same for every anonymous visitor goes out
// with "Cache-Control: public, max-age=0, s-maxage=<ttl>", no Set-Cookie and
// the per-session CSRF token taken out of its inline script; everything else
// keeps PHP's no-store session headers. Every rule fails closed: a session key,
// cookie or flag the gate does not know makes the answer personal.
//
// The pg_nc cookie marks a browser whose session is personal (a cart, a
// sign-in, a notice), so a cache in front can pass its requests through
// without reading the session.
if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// Longest lifetime, in seconds, a shared cache may keep a page.
define('PG_PAGE_CACHE_TTL_MAX', 3600);

// Shortest lifetime the settings screen stores, and what it offers first.
define('PG_PAGE_CACHE_TTL_MIN', 30);
define('PG_PAGE_CACHE_TTL_DEFAULT', 120);

// The marker cookie of a personal session.
define('PG_PAGE_CACHE_MARKER', 'pg_nc');

/**
 * A lifetime as stored in data/config.php, made safe: an integer from 0 to
 * PG_PAGE_CACHE_TTL_MAX, 0 for anything that is not a whole number.
 *
 * @param mixed $raw
 * @return int
 */
function pg_page_cache_ttl_value($raw)
{
    if (is_int($raw)) {
        $ttl = $raw;
    } elseif (is_string($raw) && preg_match('/^\s*\d{1,9}\s*$/', $raw)) {
        $ttl = (int) trim($raw);
    } else {
        return 0;
    }

    return max(0, min(PG_PAGE_CACHE_TTL_MAX, $ttl));
}

/**
 * The lifetime in force on this request; 0 means the cache is off.
 *
 * @return int
 */
function pg_page_cache_ttl()
{
    return pg_page_cache_ttl_value(defined('PAGE_CACHE_TTL') ? PAGE_CACHE_TTL : 0);
}

/**
 * The lifetime the settings screen stores: 0 with the switch off; otherwise
 * the typed number of seconds, held between the minimum and the maximum, or,
 * when nothing usable was typed, the lifetime in force or the default.
 *
 * @param mixed $enabled the switch
 * @param mixed $posted  the typed lifetime
 * @param int   $current the lifetime in force
 * @return int
 */
function pg_page_cache_ttl_from_form($enabled, $posted, $current)
{
    if (!$enabled) {
        return 0;
    }

    $posted = is_string($posted) ? trim($posted) : '';

    if (($posted !== '') && ctype_digit($posted) && (strlen($posted) <= 9)) {
        return max(PG_PAGE_CACHE_TTL_MIN, min(PG_PAGE_CACHE_TTL_MAX, (int) $posted));
    }

    $current = (int) $current;

    return ($current > 0) ? max(PG_PAGE_CACHE_TTL_MIN, min(PG_PAGE_CACHE_TTL_MAX, $current)) : PG_PAGE_CACHE_TTL_DEFAULT;
}

/**
 * Marks this answer as drawn for this visitor, or, without an argument,
 * returns the first reason given ('' when none was). Called by the renderers
 * whose output depends on who asks: the first-visit language redirect and the
 * PHP a designer wrote into a page.
 *
 * @param string|null $reason
 * @return string
 */
function pg_page_cache_personal($reason = null)
{
    static $first = '';

    if (($reason !== null) && ($first === '')) {
        $first = preg_replace('/[^a-z0-9_]/', '', strtolower((string) $reason));

        if ($first === '') {
            $first = 'personal';
        }
    }

    return $first;
}

/**
 * Whether a session holds nothing but what every anonymous visitor's session
 * holds: the CSRF token, the device type and what visitor tracking keeps.
 * Anything else (a sign-in, an order, a currency, an offer code, a liveform
 * notice, a guest pass, an auto dialog's visit start, a stored URL id) makes
 * it personal, and so does a key added later that is not on this list.
 *
 * @param array $session
 * @return bool
 */
function pg_page_cache_session_anonymous(array $session)
{
    $allowed = array(
        'token', 'device_type', 'visitor_id', 'http_referer', 'tracking_code', 'affiliate_code',
        'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'cookie_id',
    );

    foreach ($session as $key => $value) {
        if ($key !== 'software') {
            return false;
        }

        if (!is_array($value)) {
            return false;
        }

        foreach ($value as $software_key => $software_value) {
            if (!in_array((string) $software_key, $allowed, true) || is_array($software_value) || is_object($software_value)) {
                return false;
            }
        }
    }

    return true;
}

/**
 * Takes the session's CSRF token out of the page's inline script
 * (var software_token = "..."), which only the editing tools read.
 *
 * @param string $html
 * @param string $token
 * @return array [the page, how many times the token is still in it]
 */
function pg_page_cache_strip_token($html, $token)
{
    $html = (string) $html;
    $token = (string) $token;

    if ($token === '') {
        return array($html, 0);
    }

    $html = str_replace('var software_token = "' . $token . '";', 'var software_token = "";', $html);

    return array($html, substr_count($html, $token));
}

/**
 * The names of the cookies a list of response headers sets, as
 * headers_list() gives them.
 *
 * @param array $headers
 * @return array
 */
function pg_page_cache_set_cookie_names(array $headers)
{
    $names = array();

    foreach ($headers as $header) {
        if (preg_match('/^set-cookie:\s*([^=;\s]+)\s*=/i', (string) $header, $match)) {
            $names[] = $match[1];
        }
    }

    return array_values(array_unique($names));
}

/**
 * Whether a page answer may be kept by a shared cache. The checks run in a
 * fixed order and the first that fails gives the reason.
 *
 * Keys of $r: ttl, method, status, get (the query parameters),
 * rendered_html_page, toolbar, access_control_type, mobile, cookies (as
 * $_COOKIE holds them), session, set_cookies (names), session_name,
 * token_left, personal.
 *
 * @param array $r
 * @return array ['cacheable' => bool, 'reason' => string]
 */
function pg_page_cache_decide(array $r)
{
    $cookies = (isset($r['cookies']) && is_array($r['cookies'])) ? $r['cookies'] : array();
    $get = (isset($r['get']) && is_array($r['get'])) ? $r['get'] : array();
    $method = strtoupper((string) ($r['method'] ?? ''));

    if ((int) ($r['ttl'] ?? 0) <= 0) {
        $reason = 'off';
    } elseif (($method !== 'GET') && ($method !== 'HEAD')) {
        $reason = 'method';
    } elseif ((int) ($r['status'] ?? 0) !== 200) {
        $reason = 'status';
    } elseif (array_diff(array_map('strval', array_keys($get)), array('page')) !== array()) {
        $reason = 'query';
    } elseif (empty($r['rendered_html_page'])) {
        $reason = 'not_html';
    } elseif (!empty($r['toolbar'])) {
        $reason = 'toolbar';
    } elseif ((string) ($r['access_control_type'] ?? '') !== 'public') {
        $reason = 'access';
    } elseif (!empty($r['mobile'])) {
        $reason = 'mobile';
    } elseif (isset($cookies[PG_PAGE_CACHE_MARKER])) {
        $reason = 'marker';
    } elseif (isset($cookies['pg_consent'])) {
        $reason = 'consent';
    } elseif (isset($cookies['software']) && is_array($cookies['software']) && isset($cookies['software']['auth'])) {
        $reason = 'signed_in';
    } elseif (!pg_page_cache_session_anonymous((isset($r['session']) && is_array($r['session'])) ? $r['session'] : array())) {
        $reason = 'session';
    } elseif (array_diff((isset($r['set_cookies']) && is_array($r['set_cookies'])) ? $r['set_cookies'] : array(), array((string) ($r['session_name'] ?? ''))) !== array()) {
        $reason = 'set_cookie';
    } elseif ((int) ($r['token_left'] ?? 0) > 0) {
        $reason = 'token';
    } elseif ((string) ($r['personal'] ?? '') !== '') {
        $reason = 'personal_' . $r['personal'];
    } else {
        return array('cacheable' => true, 'reason' => '');
    }

    return array('cacheable' => false, 'reason' => $reason);
}

/**
 * The header lines of a cacheable answer: browsers ask again every time, a
 * shared cache keeps the page for the lifetime. Pragma and Expires, which the
 * session sent, are removed by the caller.
 *
 * @param int $ttl
 * @return array
 */
function pg_page_cache_headers($ttl)
{
    return array(
        'Cache-Control: public, max-age=0, s-maxage=' . pg_page_cache_ttl_value((int) $ttl),
        'Vary: Accept-Encoding',
    );
}

/**
 * What to do with the marker cookie: 'set' for a personal session that does
 * not carry it yet, 'clear' for an anonymous one that still does, '' when it
 * is right already or the answer is going to a shared cache.
 *
 * @param bool $anonymous  the session is anonymous
 * @param bool $has_cookie the request carried the marker
 * @param bool $cacheable  this answer may be kept by a shared cache
 * @return string
 */
function pg_page_cache_marker_action($anonymous, $has_cookie, $cacheable)
{
    if ($cacheable) {
        return '';
    }

    if (!$anonymous && !$has_cookie) {
        return 'set';
    }

    if ($anonymous && $has_cookie) {
        return 'clear';
    }

    return '';
}

/**
 * Whether this request's answer was found cacheable; set once by
 * pg_page_cache_apply().
 *
 * @param bool|null $value
 * @return bool
 */
function pg_page_cache_cacheable($value = null)
{
    static $cacheable = false;

    if ($value !== null) {
        $cacheable = (bool) $value;
    }

    return $cacheable;
}

/**
 * The functions to run when PHP is about to send the headers; with an
 * argument, adds one first.
 *
 * @param string|null $callback a function name
 * @return array
 */
function pg_header_callbacks($callback = null)
{
    static $callbacks = array();

    if (($callback !== null) && !in_array($callback, $callbacks, true)) {
        $callbacks[] = $callback;
    }

    return $callbacks;
}

/**
 * Runs a function when PHP is about to send the headers.
 * header_register_callback() keeps one callback per request and a second
 * registration silently replaces the first, so every caller goes through this
 * list instead.
 *
 * @param string $callback a function name
 * @return bool false when the headers are gone already
 */
function pg_header_callback_add($callback)
{
    static $registered = false;

    if (headers_sent() || !function_exists('header_register_callback')) {
        return false;
    }

    pg_header_callbacks($callback);

    if (!$registered) {
        $registered = header_register_callback('pg_header_callbacks_run');
    }

    return $registered;
}

/**
 * The single header callback: runs the list pg_header_callback_add() keeps.
 */
function pg_header_callbacks_run()
{
    foreach (pg_header_callbacks() as $callback) {
        if (is_callable($callback)) {
            call_user_func($callback);
        }
    }
}

/**
 * init.php, right after the session has started: while the cache is on, the
 * marker cookie follows the session on every answer, the POST and redirect
 * that fill a cart or sign in included.
 */
function pg_page_cache_watch_session()
{
    if (pg_page_cache_ttl() > 0) {
        pg_header_callback_add('pg_page_cache_marker_hook');
    }
}

/**
 * The header callback pg_page_cache_watch_session() registers.
 */
function pg_page_cache_marker_hook()
{
    $session = (isset($_SESSION) && is_array($_SESSION)) ? $_SESSION : array();

    $action = pg_page_cache_marker_action(
        pg_page_cache_session_anonymous($session),
        isset($_COOKIE[PG_PAGE_CACHE_MARKER]),
        pg_page_cache_cacheable());

    if ($action === 'set') {
        pg_page_cache_marker_cookie('1', 0);
    } elseif ($action === 'clear') {
        pg_page_cache_marker_cookie('', time() - 86400);
    }
}

/**
 * Writes the marker cookie: for the browser session, the whole site,
 * HttpOnly, SameSite Lax, Secure over https.
 *
 * @param string $value
 * @param int    $expires
 */
function pg_page_cache_marker_cookie($value, $expires)
{
    $secure = function_exists('pg_request_is_https') && pg_request_is_https();

    if (PHP_VERSION_ID >= 70300) {
        setcookie(PG_PAGE_CACHE_MARKER, $value, array(
            'expires' => $expires,
            'path' => '/',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ));
    } else {
        // Before 7.3 setcookie() has no SameSite parameter; the attribute
        // rides on the path, which is printed as it is.
        setcookie(PG_PAGE_CACHE_MARKER, $value, $expires, '/; samesite=Lax', '', $secure, true);
    }
}

/**
 * get_page.php, just before the page is printed: decides whether the answer
 * may be kept by a shared cache and, when it may, sends the headers that say
 * so and returns the page without the session's token. With the cache off the
 * page comes back untouched and no header changes.
 *
 * @param string $content
 * @param array  $context rendered_html_page, toolbar, access_control_type
 * @return string
 */
function pg_page_cache_apply($content, array $context)
{
    $ttl = pg_page_cache_ttl();

    if (($ttl <= 0) || headers_sent()) {
        return $content;
    }

    $token = (isset($_SESSION['software']['token']) && is_string($_SESSION['software']['token'])) ? $_SESSION['software']['token'] : '';

    list($stripped, $token_left) = pg_page_cache_strip_token($content, $token);

    $session_name = (string) session_name();

    $decision = pg_page_cache_decide(array(
        'ttl' => $ttl,
        'method' => isset($_SERVER['REQUEST_METHOD']) ? (string) $_SERVER['REQUEST_METHOD'] : '',
        'status' => http_response_code(),
        'get' => $_GET,
        'rendered_html_page' => !empty($context['rendered_html_page']),
        'toolbar' => !empty($context['toolbar']),
        'access_control_type' => isset($context['access_control_type']) ? (string) $context['access_control_type'] : '',
        'mobile' => defined('MOBILE') && MOBILE,
        'cookies' => $_COOKIE,
        'session' => (isset($_SESSION) && is_array($_SESSION)) ? $_SESSION : array(),
        'set_cookies' => pg_page_cache_set_cookie_names(headers_list()),
        'session_name' => $session_name,
        'token_left' => $token_left,
        'personal' => pg_page_cache_personal(),
    ));

    if (!$decision['cacheable']) {
        header('X-Pinegrap-Cache: bypass; reason=' . $decision['reason']);

        return $content;
    }

    pg_page_cache_cacheable(true);

    // The gate let through no cookie but the session's own, which only a
    // session started by this request sends.
    header_remove('Set-Cookie');
    header_remove('Pragma');
    header_remove('Expires');

    foreach (pg_page_cache_headers($ttl) as $line) {
        header($line);
    }

    header('X-Pinegrap-Cache: cacheable');

    // A session this request started has had its cookie taken away, so
    // nobody can come back to it: drop it instead of leaving a file behind.
    $sent_id = (isset($_COOKIE[$session_name]) && is_string($_COOKIE[$session_name])) ? $_COOKIE[$session_name] : '';

    if (($sent_id === '') || ($sent_id !== session_id())) {
        register_shutdown_function('pg_page_cache_drop_new_session');
    }

    return $stripped;
}

/**
 * Shutdown function: empties and destroys a session the visitor never got
 * the cookie of.
 */
function pg_page_cache_drop_new_session()
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        $_SESSION = array();
        @session_destroy();
    }
}
