<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Anonymous page cache: which page answers may be kept by a shared cache, the
 * standard headers that let a CDN or reverse proxy keep them, and Pinegrap's
 * own copies on disk that router.php answers from.
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
 * The favourites cookie a visitor's first save started (wishlist_cookie)
 * counts too: it is the session's copy of the pg_wishlist cookie, which no
 * stored page is drawn from (the hearts are filled in by script).
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
        'wishlist_cookie',
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
 * The query part of a request address, from its first '?' on ('' when it
 * has none): a bare '?' is a query too, since a cache keys /about? apart from
 * /about.
 *
 * @param string $uri
 * @return string
 */
function pg_page_cache_query_string($uri)
{
    $at = strpos((string) $uri, '?');

    return ($at === false) ? '' : (string) substr((string) $uri, $at);
}

/**
 * Whether a page answer may be kept by a shared cache. The checks run in a
 * fixed order and the first that fails gives the reason.
 *
 * Keys of $r: ttl, method, status, query_string (the request address from
 * its first '?' on), get (the query parameters), rendered_html_page, toolbar,
 * access_control_type, mobile, cookies (as
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
    } elseif (((string) ($r['query_string'] ?? '') !== '') || (array_diff(array_map('strval', array_keys($get)), array('page')) !== array())) {
        // The router writes $_GET['page'] itself, so only the request address
        // tells /about from /about?page=x, which would be one more cache entry.
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
 * The X-Pinegrap-Cache line of an answer that is not cacheable. The reason
 * tells what the page runs and what the visitor's session holds, so only
 * staff see it; everyone else gets the bare word.
 *
 * @param string $reason
 * @param bool   $staff
 * @return string
 */
function pg_page_cache_bypass_header($reason, $staff)
{
    $reason = preg_replace('/[^a-z0-9_]/', '', strtolower((string) $reason));

    return 'X-Pinegrap-Cache: bypass' . (($staff && ($reason !== '')) ? '; reason=' . $reason : '');
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
        pg_page_cache_marker_cookie('1', null);
    } elseif ($action === 'clear') {
        pg_page_cache_marker_cookie('', time() - 86400);
    }
}

/**
 * The lifetime and path of the marker cookie, taken from the session cookie's
 * so the marker lives exactly as long as the session it stands for: a session
 * cookie that outlives the browser (session.cookie_lifetime > 0) keeps the
 * marker too, and both cover the same paths.
 *
 * @param array $params session_get_cookie_params()
 * @param int   $now
 * @return array [expires, path]
 */
function pg_page_cache_marker_cookie_params(array $params, $now)
{
    $lifetime = (int) ($params['lifetime'] ?? 0);
    $path = (string) ($params['path'] ?? '');

    if (($path === '') || ($path[0] !== '/') || preg_match('/[;,\s]/', $path)) {
        $path = '/';
    }

    return array(($lifetime > 0) ? ((int) $now + $lifetime) : 0, $path);
}

/**
 * Writes the marker cookie: as long and as wide as the session cookie,
 * HttpOnly, SameSite Lax, Secure over https. $expires null sets it, a past
 * time clears it.
 *
 * @param string   $value
 * @param int|null $expires
 */
function pg_page_cache_marker_cookie($value, $expires)
{
    $secure = function_exists('pg_request_is_https') && pg_request_is_https();

    list($session_expires, $path) = pg_page_cache_marker_cookie_params(session_get_cookie_params(), time());

    if ($expires === null) {
        $expires = $session_expires;
    }

    if (PHP_VERSION_ID >= 70300) {
        setcookie(PG_PAGE_CACHE_MARKER, $value, array(
            'expires' => $expires,
            'path' => $path,
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ));
    } else {
        // Before 7.3 setcookie() has no SameSite parameter; the attribute
        // rides on the path, which is printed as it is.
        setcookie(PG_PAGE_CACHE_MARKER, $value, $expires, $path . '; samesite=Lax', '', $secure, true);
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
        'query_string' => pg_page_cache_query_string(isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : ''),
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
        header(pg_page_cache_bypass_header($decision['reason'],
            !empty($context['toolbar'])
            || (defined('USER_LOGGED_IN') && USER_LOGGED_IN && defined('USER_ROLE') && ((int) USER_ROLE < 3))));

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

// ── The file cache ─────────────────────────────────────────────────────────
//
// While the lifetime is on, the pages the gate above finds cacheable are also
// kept as files under data/cache/page, and router.php answers the next
// anonymous visit to the same address from them: no session, no init.php, no
// page query, the firewall's router pass kept. PAGE_CACHE_FILE false in
// data/config.php leaves only the headers, for a site with a CDN or reverse
// proxy in front that wants no copies on its own disk.
//
// Every copy sits in the folder of one generation, config.page_cache_generation
// (2026.11.0, 11.0.29). A write that may change what pages show raises the
// number once its request ends, so no copy made before it is read again; the
// general job deletes the old folders. A visit answered from a copy is counted
// through a small signed request back to page_view.php.
//
// Everything the router calls here runs without functions.php: no lang(), no
// db(), no other module.

// Largest page, in bytes, the cache keeps.
define('PG_PAGE_CACHE_MAX_BYTES', 2097152);

// Seconds a re-render may hold a stale copy's lock before another request
// takes it over.
define('PG_PAGE_CACHE_LOCK_SECONDS', 30);

// The layout of a stored file; a file of another layout is a miss.
define('PG_PAGE_CACHE_FORMAT', 1);

/**
 * The folder the stored pages live in.
 *
 * @return string
 */
function pg_page_cache_directory()
{
    return PG_FUNCTIONS_DIR . '/data/cache/page';
}

/**
 * Whether stored copies are made and served: the lifetime is on and
 * data/config.php does not say PAGE_CACHE_FILE false.
 *
 * @return bool
 */
function pg_page_cache_file_enabled()
{
    return (pg_page_cache_ttl() > 0) && (!defined('PAGE_CACHE_FILE') || PAGE_CACHE_FILE);
}

/**
 * The scheme the request came in on, as this server sees it, and whether a
 * proxy in front says https ('https', else ''). Both go into the key: Secure
 * Mode may trust the proxy's word and redirect, so a copy drawn for one must
 * never answer the other. The proxy's word is read from the same headers as
 * check_proxy_ssl_headers() (core.php, not loaded in the router) and reduced
 * to one of two values, so a made-up header value draws no copy of its own.
 *
 * @param array $server $_SERVER
 * @return array [scheme, forwarded]
 */
function pg_page_cache_request_scheme(array $server)
{
    $https = (!empty($server['HTTPS']) && (strtolower((string) $server['HTTPS']) !== 'off'))
        || (isset($server['SERVER_PORT']) && ((int) $server['SERVER_PORT'] === 443));

    $header = function ($name) use ($server) {
        return (isset($server[$name]) && is_scalar($server[$name])) ? strtolower(trim((string) $server[$name])) : '';
    };

    $first = explode(',', $header('HTTP_X_FORWARDED_PROTO'));

    $proxy_https = (trim($first[0]) === 'https')
        || (strpos($header('HTTP_CF_VISITOR'), '"scheme":"https"') !== false)
        || (bool) preg_match('/proto\s*=\s*"?https"?/', $header('HTTP_FORWARDED'))
        || ($header('HTTP_X_FORWARDED_SSL') === 'on')
        || ($header('HTTP_FRONT_END_HTTPS') === 'on')
        || ($header('HTTP_X_FORWARDED_PORT') === '443');

    return array($https ? 'https' : 'http', $proxy_https ? 'https' : '');
}

/**
 * The name of a stored page: the scheme, the proxy's word on it, the host,
 * the address without its query and the software version, so an upgrade
 * starts from empty.
 *
 * @param array $parts scheme, forwarded, host, path, version
 * @return string 40 hex characters
 */
function pg_page_cache_key(array $parts)
{
    $path = (string) ($parts['path'] ?? '');
    $at = strpos($path, '?');

    if ($at !== false) {
        $path = (string) substr($path, 0, $at);
    }

    return sha1(implode("\n", array(
        'v' . PG_PAGE_CACHE_FORMAT,
        (string) ($parts['scheme'] ?? ''),
        (string) ($parts['forwarded'] ?? ''),
        strtolower((string) ($parts['host'] ?? '')),
        $path,
        (string) ($parts['version'] ?? ''),
    )));
}

/**
 * Where a stored page lives: <directory>/g<generation>/<first two of the
 * key>/<key>.html; '' for anything that is not a key.
 *
 * @param string $directory
 * @param int    $generation
 * @param string $key
 * @return string
 */
function pg_page_cache_file_path($directory, $generation, $key)
{
    if (!is_string($key) || !preg_match('/^[0-9a-f]{40}$/', $key)) {
        return '';
    }

    return rtrim((string) $directory, '/\\') . '/g' . max(0, (int) $generation) . '/' . substr($key, 0, 2) . '/' . $key . '.html';
}

/**
 * Whether the router may answer this request from a stored copy: '' when it
 * may, else the reason it may not. Only what is known before the session and
 * the page is asked; the gate decides the rest when the copy is made.
 *
 * Keys of $r: ttl, file (the file cache is on), generation_known (the
 * config column exists), method, uri (the request address), host (the Host
 * header), hostname (the site's own, config.hostname), authorization (the
 * request carries credentials), cookies, mobile (the mobile view is on),
 * maintenance (maintenance mode is on).
 *
 * @param array $r
 * @return string
 */
function pg_page_cache_serve_check(array $r)
{
    $cookies = (isset($r['cookies']) && is_array($r['cookies'])) ? $r['cookies'] : array();
    $method = strtoupper((string) ($r['method'] ?? ''));
    $host = (string) ($r['host'] ?? '');

    if ((int) ($r['ttl'] ?? 0) <= 0) {
        return 'off';
    }

    if (empty($r['file'])) {
        return 'file_off';
    }

    // Before the upgrade step there is no number to raise, so a change could
    // not reach a stored copy.
    if (empty($r['generation_known'])) {
        return 'generation';
    }

    if (($method !== 'GET') && ($method !== 'HEAD')) {
        return 'method';
    }

    if (strpos((string) ($r['uri'] ?? ''), '?') !== false) {
        return 'query';
    }

    // Only the site's own host: any other name would be one more copy per
    // made-up Host header, and pages print the host they were asked on.
    if (($host === '') || (strcasecmp($host, (string) ($r['hostname'] ?? '')) !== 0)) {
        return 'host';
    }

    if (!empty($r['authorization'])) {
        return 'authorization';
    }

    if (isset($cookies[PG_PAGE_CACHE_MARKER])) {
        return 'marker';
    }

    if (isset($cookies['software']) && is_array($cookies['software']) && isset($cookies['software']['auth'])) {
        return 'signed_in';
    }

    if (isset($cookies['pg_consent'])) {
        return 'consent';
    }

    if (!empty($r['mobile'])) {
        return 'mobile';
    }

    if (!empty($r['maintenance'])) {
        return 'maintenance';
    }

    return '';
}

/**
 * How a copy made at $created stands at $now: 'fresh' within the lifetime,
 * 'stale' within twice the lifetime (served while one request draws it
 * again), 'expired' after that, with the cache off, or made in the future.
 *
 * @param int $created
 * @param int $now
 * @param int $ttl
 * @return string
 */
function pg_page_cache_freshness($created, $now, $ttl)
{
    $ttl = (int) $ttl;
    $age = (int) $now - (int) $created;

    if (($ttl <= 0) || ($age < 0)) {
        return 'expired';
    }

    if ($age < $ttl) {
        return 'fresh';
    }

    return ($age < (2 * $ttl)) ? 'stale' : 'expired';
}

/**
 * Whether a drawn page is kept: '' when it is, else the reason it is not.
 *
 * Keys of $r: key (what the router named it; only the home page and a page
 * asked for by its own address get one), cacheable (the gate's verdict), bot
 * (IS_BOT), automated (pg_request_is_automated()), bytes.
 *
 * @param array $r
 * @return string
 */
function pg_page_cache_store_check(array $r)
{
    $bytes = (int) ($r['bytes'] ?? 0);

    if (!is_string($r['key'] ?? null) || !preg_match('/^[0-9a-f]{40}$/', $r['key'])) {
        return 'no_key';
    }

    if (empty($r['cacheable'])) {
        return 'not_cacheable';
    }

    // A crawler's page may lack what a person's has (the consent notice).
    if (!empty($r['bot'])) {
        return 'bot';
    }

    if (!empty($r['automated'])) {
        return 'automated';
    }

    if ($bytes <= 0) {
        return 'empty';
    }

    if ($bytes > PG_PAGE_CACHE_MAX_BYTES) {
        return 'size';
    }

    return '';
}

/**
 * The response headers a stored page answers with again: its type and
 * language, Link, X-Robots-Tag and the document policy headers init.php
 * sends (the router does not). Never a cookie, a cache header or a line
 * with a line break in it.
 *
 * @param array $headers as headers_list() gives them
 * @return array
 */
function pg_page_cache_kept_headers(array $headers)
{
    $names = array(
        'content-type', 'content-language', 'link', 'x-robots-tag',
        'content-security-policy', 'content-security-policy-report-only', 'strict-transport-security',
    );

    $kept = array();

    foreach ($headers as $header) {
        if (!is_string($header) || preg_match('/[\r\n\0]/', $header)) {
            continue;
        }

        $colon = strpos($header, ':');

        if (($colon !== false) && in_array(strtolower(trim(substr($header, 0, $colon))), $names, true)) {
            $kept[] = $header;
        }
    }

    return $kept;
}

/**
 * A stored file: one line of JSON (layout, time made, headers), then the
 * page as it is sent.
 *
 * @param array  $meta created, headers
 * @param string $body
 * @return string '' when the headers cannot be written as JSON
 */
function pg_page_cache_pack(array $meta, $body)
{
    $line = json_encode(array(
        'v' => PG_PAGE_CACHE_FORMAT,
        'created' => (int) ($meta['created'] ?? 0),
        'headers' => array_values(pg_page_cache_kept_headers((array) ($meta['headers'] ?? array()))),
    ), JSON_UNESCAPED_SLASHES);

    return is_string($line) ? $line . "\n" . (string) $body : '';
}

/**
 * Reads a stored file back; null for anything that is not one whole (an
 * empty or cut file, another layout).
 *
 * @param string $raw
 * @return array|null ['meta' => [created, headers], 'body' => string]
 */
function pg_page_cache_unpack($raw)
{
    $raw = (string) $raw;
    $eol = strpos($raw, "\n");

    if ($eol === false) {
        return null;
    }

    $meta = json_decode(substr($raw, 0, $eol), true);

    if (!is_array($meta) || ((int) ($meta['v'] ?? 0) !== PG_PAGE_CACHE_FORMAT)
        || !isset($meta['created']) || !isset($meta['headers']) || !is_array($meta['headers'])) {
        return null;
    }

    return array(
        'meta' => array(
            'created' => (int) $meta['created'],
            'headers' => pg_page_cache_kept_headers($meta['headers']),
        ),
        'body' => (string) substr($raw, $eol + 1),
    );
}

/**
 * Writes a stored file whole or not at all: into a file of its own in the
 * same folder first, then renamed over the old copy. Never throws; false when
 * the folder cannot be made or written.
 *
 * @param string $file
 * @param string $raw
 * @return bool
 */
function pg_page_cache_write($file, $raw)
{
    $file = (string) $file;
    $raw = (string) $raw;

    if (($file === '') || ($raw === '')) {
        return false;
    }

    $directory = dirname($file);

    if (!is_dir($directory) && !@mkdir($directory, 0775, true) && !is_dir($directory)) {
        return false;
    }

    $temporary = $file . '.' . str_replace('.', '', uniqid('', true)) . '.tmp';

    if (@file_put_contents($temporary, $raw) !== strlen($raw)) {
        @unlink($temporary);
        return false;
    }

    if (!@rename($temporary, $file)) {
        @unlink($temporary);
        return false;
    }

    return true;
}

/**
 * The lock this request holds while it draws a stale copy again; with an
 * argument, sets it ('' when none).
 *
 * @param string|null $path
 * @return string
 */
function pg_page_cache_held_lock($path = null)
{
    static $held = '';

    if ($path !== null) {
        $held = (string) $path;
    }

    return $held;
}

/**
 * Takes the lock of a stale copy, so one request draws it again while the
 * others are answered from it. A lock older than PG_PAGE_CACHE_LOCK_SECONDS
 * belongs to a request that died and is taken over.
 *
 * @param string $file
 * @return bool
 */
function pg_page_cache_lock($file)
{
    $lock = (string) $file . '.lock';
    $handle = @fopen($lock, 'x');

    if ($handle === false) {
        // PHP keeps a file's time in its stat cache, and touch() does not clear
        // it on every build: a lock this process looked at before would keep
        // its old time and never be taken over.
        clearstatcache(true, $lock);
        $made = @filemtime($lock);

        if (($made === false) || ($made > (time() - PG_PAGE_CACHE_LOCK_SECONDS))) {
            return false;
        }

        @unlink($lock);
        $handle = @fopen($lock, 'x');

        if ($handle === false) {
            return false;
        }
    }

    fclose($handle);

    pg_page_cache_held_lock($lock);
    register_shutdown_function('pg_page_cache_unlock');

    return true;
}

/**
 * Gives the lock back, whether or not the page was stored.
 */
function pg_page_cache_unlock()
{
    $lock = pg_page_cache_held_lock();

    if ($lock !== '') {
        @unlink($lock);
        pg_page_cache_held_lock('');
    }
}

/**
 * The router's question: may this request be answered from a stored copy?
 * 'hit' (fresh) and 'stale' come with the copy; 'miss' with the key and
 * generation the page is to be stored under; 'bypass' with the reason.
 *
 * @param array  $config the config row
 * @param array  $server $_SERVER
 * @param array  $cookies $_COOKIE
 * @param string $uri the request address
 * @param int    $now
 * @return array state, reason, key, generation, file, entry, age
 */
function pg_page_cache_lookup(array $config, array $server, array $cookies, $uri, $now)
{
    $ttl = pg_page_cache_ttl();

    $reason = pg_page_cache_serve_check(array(
        'ttl' => $ttl,
        'file' => pg_page_cache_file_enabled(),
        'generation_known' => array_key_exists('page_cache_generation', $config),
        'method' => isset($server['REQUEST_METHOD']) ? (string) $server['REQUEST_METHOD'] : '',
        'uri' => (string) $uri,
        'host' => isset($server['HTTP_HOST']) ? (string) $server['HTTP_HOST'] : '',
        'hostname' => isset($config['hostname']) ? (string) $config['hostname'] : '',
        'authorization' => !empty($server['HTTP_AUTHORIZATION']) || !empty($server['REDIRECT_HTTP_AUTHORIZATION']) || !empty($server['PHP_AUTH_USER']),
        'cookies' => $cookies,
        'mobile' => !empty($config['mobile']),
        'maintenance' => !empty($config['maintenance_mode']),
    ));

    $out = array('state' => 'bypass', 'reason' => $reason, 'key' => '', 'generation' => 0, 'file' => '', 'entry' => null, 'age' => 0);

    if ($reason !== '') {
        return $out;
    }

    list($scheme, $forwarded) = pg_page_cache_request_scheme($server);

    $out['state'] = 'miss';
    $out['generation'] = (int) $config['page_cache_generation'];
    $out['key'] = pg_page_cache_key(array(
        'scheme' => $scheme,
        'forwarded' => $forwarded,
        'host' => (string) $server['HTTP_HOST'],
        'path' => (string) $uri,
        'version' => isset($config['version']) ? (string) $config['version'] : '',
    ));
    $out['file'] = pg_page_cache_file_path(pg_page_cache_directory(), $out['generation'], $out['key']);

    $raw = is_file($out['file']) ? @file_get_contents($out['file']) : false;
    $entry = ($raw === false) ? null : pg_page_cache_unpack($raw);

    if ($entry === null) {
        return $out;
    }

    $freshness = pg_page_cache_freshness($entry['meta']['created'], $now, $ttl);

    if (($freshness === 'fresh') || (($freshness === 'stale') && !pg_page_cache_lock($out['file']))) {
        $out['state'] = ($freshness === 'fresh') ? 'hit' : 'stale';
        $out['entry'] = $entry;
        $out['age'] = max(0, (int) $now - $entry['meta']['created']);
    }

    return $out;
}

/**
 * Sends a stored copy: its own headers, the shared-cache headers for what is
 * left of its lifetime (none for a stale one), and the page, except to HEAD.
 *
 * @param array  $lookup pg_page_cache_lookup() with state hit or stale
 * @param string $method
 */
function pg_page_cache_send(array $lookup, $method)
{
    foreach ($lookup['entry']['meta']['headers'] as $line) {
        header($line);
    }

    foreach (pg_page_cache_headers(max(0, pg_page_cache_ttl() - (int) $lookup['age'])) as $line) {
        header($line);
    }

    header('X-Pinegrap-Cache: ' . (($lookup['state'] === 'hit') ? 'hit' : 'stale'));

    if (strtoupper((string) $method) !== 'HEAD') {
        echo $lookup['entry']['body'];
    }
}

/**
 * get_page.php, after the gate and before the page is printed: stores the
 * page when the router named it and the gate found it cacheable, with the
 * visitor beacon in it when this view would have been counted. Gives the
 * stale copy's lock back either way.
 *
 * @param string $content the page as it is sent
 * @param int    $page_id
 * @param string $page_name
 */
function pg_page_cache_store_page($content, $page_id, $page_name)
{
    $key = defined('PG_PAGE_CACHE_KEY') ? PG_PAGE_CACHE_KEY : '';

    $reason = pg_page_cache_store_check(array(
        'key' => $key,
        'cacheable' => pg_page_cache_cacheable(),
        'bot' => defined('IS_BOT') && IS_BOT,
        'automated' => function_exists('pg_request_is_automated') && pg_request_is_automated(),
        'bytes' => strlen((string) $content),
    ));

    if (($reason !== '') || !pg_page_cache_file_enabled()) {
        pg_page_cache_unlock();
        return;
    }

    $body = (string) $content;

    $item = function_exists('pg_track_content') ? pg_track_content() : array('type' => '', 'id' => 0);
    $record = function_exists('pg_sw_record_view_seen') ? pg_sw_record_view_seen() : array();

    if ((defined('VISITOR_TRACKING') && VISITOR_TRACKING) || !empty($record['form_id'])) {
        $payload = pg_page_cache_beacon_payload(array(
            'page_id' => $page_id,
            'page_name' => $page_name,
            'pretty' => defined('PRETTY_URL_PATH') ? PRETTY_URL_PATH : '',
            'item_type' => $item['type'] ?? '',
            'item_id' => $item['id'] ?? 0,
            'form_id' => $record['form_id'] ?? 0,
            'record_page_id' => $record['page_id'] ?? 0,
        ));

        $body = pg_page_cache_beacon_inject($body, $payload,
            pg_page_cache_beacon_sign($payload, defined('ENCRYPTION_KEY') ? (string) ENCRYPTION_KEY : ''),
            (defined('PATH') ? PATH : '/') . (defined('SOFTWARE_DIRECTORY') ? SOFTWARE_DIRECTORY : 'pinegrap') . '/page_view.php');
    }

    pg_page_cache_write(
        pg_page_cache_file_path(pg_page_cache_directory(), defined('PG_PAGE_CACHE_GENERATION') ? PG_PAGE_CACHE_GENERATION : 0, $key),
        pg_page_cache_pack(array('created' => time(), 'headers' => headers_list()), $body));

    pg_page_cache_unlock();
}

// ── The visitor beacon ─────────────────────────────────────────────────────

/**
 * What a stored page tells page_view.php about the view it stands for, as
 * JSON: the page, the address the visitor used (PRETTY_URL_PATH), the
 * catalogue item (pg_track_content()) and the record a record page showed.
 *
 * @param array $view page_id, page_name, pretty, item_type, item_id, form_id, record_page_id
 * @return string '' when it cannot be written
 */
function pg_page_cache_beacon_payload(array $view)
{
    $json = json_encode(array(
        'page_id' => (int) ($view['page_id'] ?? 0),
        'page_name' => (string) ($view['page_name'] ?? ''),
        'pretty' => (string) ($view['pretty'] ?? ''),
        'item_type' => (string) preg_replace('/[^a-z_]/', '', (string) ($view['item_type'] ?? '')),
        'item_id' => (int) ($view['item_id'] ?? 0),
        'form_id' => (int) ($view['form_id'] ?? 0),
        'record_page_id' => (int) ($view['record_page_id'] ?? 0),
    ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    return is_string($json) ? $json : '';
}

/**
 * The signature of a beacon payload with the site's key; '' without a key,
 * and then no beacon is written.
 *
 * @param string $payload
 * @param string $key ENCRYPTION_KEY
 * @return string
 */
function pg_page_cache_beacon_sign($payload, $key)
{
    $payload = (string) $payload;
    $key = (string) $key;

    if (($payload === '') || ($key === '')) {
        return '';
    }

    return hash_hmac('sha256', 'page-view:' . $payload, $key);
}

/**
 * Whether a beacon payload carries the signature this site gave it.
 *
 * @param mixed  $payload
 * @param mixed  $signature
 * @param string $key
 * @return bool
 */
function pg_page_cache_beacon_verify($payload, $signature, $key)
{
    if (!is_string($payload) || !is_string($signature) || ($signature === '')) {
        return false;
    }

    $expected = pg_page_cache_beacon_sign($payload, $key);

    return ($expected !== '') && hash_equals($expected, $signature);
}

/**
 * A verified beacon payload read back; null when it names no page.
 *
 * @param string $payload
 * @return array|null
 */
function pg_page_cache_beacon_read($payload)
{
    $data = json_decode((string) $payload, true);

    if (!is_array($data) || !isset($data['page_id']) || ((int) $data['page_id'] <= 0)) {
        return null;
    }

    return array(
        'page_id' => (int) $data['page_id'],
        'page_name' => is_string($data['page_name'] ?? null) ? $data['page_name'] : '',
        'pretty' => is_string($data['pretty'] ?? null) ? $data['pretty'] : '',
        'item_type' => is_string($data['item_type'] ?? null) ? $data['item_type'] : '',
        'item_id' => (int) ($data['item_id'] ?? 0),
        'form_id' => (int) ($data['form_id'] ?? 0),
        'record_page_id' => (int) ($data['record_page_id'] ?? 0),
    );
}

/**
 * Puts the beacon into a page, just before its last </body>: one inline
 * script that posts the payload, its signature and document.referrer to
 * page_view.php with navigator.sendBeacon, or fetch with keepalive where
 * that is missing. Nothing without a signature or a </body>.
 *
 * @param string $html
 * @param string $payload
 * @param string $signature
 * @param string $url page_view.php
 * @return string
 */
function pg_page_cache_beacon_inject($html, $payload, $signature, $url)
{
    $html = (string) $html;
    $at = strripos($html, '</body>');

    if (((string) $payload === '') || ((string) $signature === '') || ($at === false)) {
        return $html;
    }

    // Every < > & ' " is written as \uXXXX, so nothing in the strings can end
    // the script or the page.
    $flags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES;
    $url_js = json_encode((string) $url, $flags);
    $payload_js = json_encode((string) $payload, $flags);
    $signature_js = json_encode((string) $signature, $flags);

    if (!is_string($url_js) || !is_string($payload_js) || !is_string($signature_js)) {
        return $html;
    }

    $script = '<script>(function(){try{var u=' . $url_js . ',d=new FormData();'
        . 'd.append("p",' . $payload_js . ');d.append("s",' . $signature_js . ');d.append("r",document.referrer||"");'
        . 'if(navigator.sendBeacon&&navigator.sendBeacon(u,d)){return;}'
        . 'if(window.fetch){fetch(u,{method:"POST",body:d,keepalive:true,credentials:"same-origin"});}'
        . '}catch(e){}})();</script>';

    return substr($html, 0, $at) . $script . substr($html, $at);
}

/**
 * The referrer a beacon reports, as init_tracking() reads HTTP_REFERER: an
 * absolute http(s) address of at most 2048 characters, else ''.
 *
 * @param mixed $raw
 * @return string
 */
function pg_page_cache_referer($raw)
{
    if (!is_string($raw)) {
        return '';
    }

    $raw = trim($raw);

    if (!preg_match('#^https?://[^/\s]#i', $raw) || preg_match('/[\x00-\x1f\x7f]/', $raw)) {
        return '';
    }

    return (string) substr($raw, 0, 2048);
}

// ── Making stored pages old ────────────────────────────────────────────────

/**
 * Whether a request may change what pages show, so that every stored page is
 * made old once it ends: a write (not GET, HEAD or OPTIONS) by a signed-in
 * user or an API client. Left out: the beacon, api.php (decided per action,
 * pg_page_cache_panel_dirties()), the chat and the update check, which poll,
 * the favourites, whose hearts a page's script fills in for each visitor,
 * a back-in-stock request, which no page shows, and the workspace, whose
 * page and product changes are announced (pg_page_cache_announce_dirties()).
 *
 * @param string $method
 * @param string $script SCRIPT_NAME
 * @param bool   $signed_in
 * @param bool   $api_client integration.php
 * @return bool
 */
function pg_page_cache_request_dirties($method, $script, $signed_in, $api_client)
{
    $method = strtoupper((string) $method);

    if (in_array($method, array('', 'GET', 'HEAD', 'OPTIONS'), true)) {
        return false;
    }

    $script = strtolower(basename(str_replace('\\', '/', (string) $script)));

    if (in_array($script, array('page_view.php', 'api.php', 'chat.php', 'software_update_check.php', 'wishlist.php', 'stock_notify.php'), true)
        || (strpos($script, 'workspace') === 0)) {
        return false;
    }

    return (bool) $signed_in || (bool) $api_client;
}

/**
 * Whether an api.php action may change what pages show: a write of the
 * content, designer, commerce, shared component or file explorer handlers,
 * except the shop's visitor actions. Dashboard, notification and system
 * polls are writes of their own and leave stored pages alone. The actions
 * that carry many sub-actions on one write row (pg_page_cache_panel_sub_actions())
 * count only for a sub-action that changes what a visitor may see: the
 * editor's presence heartbeat alone runs every twenty seconds.
 *
 * @param string     $action
 * @param array|null $entry   its pg_panel_actions() row
 * @param array      $request the request api.php decoded
 * @return bool
 */
function pg_page_cache_panel_dirties($action, $entry, array $request = array())
{
    if (!is_array($entry) || empty($entry['write'])) {
        return false;
    }

    $action = (string) $action;

    if (in_array($action, array('add_to_cart', 'get_delivery_date', 'get_shipping_methods'), true)) {
        return false;
    }

    $sub_actions = pg_page_cache_panel_sub_actions();

    if (isset($sub_actions[$action])) {
        $key = $sub_actions[$action]['key'];
        $sub = (isset($request[$key]) && is_string($request[$key])) ? $request[$key] : '';

        return in_array($sub, $sub_actions[$action]['dirty'], true);
    }

    return in_array((string) ($entry['file'] ?? ''), array('content.php', 'designer.php', 'commerce.php', 'shared_component.php', 'explorer.php'), true);
}

/**
 * The api.php actions that carry many sub-actions on one write row, the
 * request key that names the sub-action, the ones that may change what a
 * visitor sees ('dirty') and the ones that do not ('clean': reads, polls,
 * the editor's own state, templates and assistant proposals that reach no
 * page until it is saved). A sub-action in neither list leaves stored pages
 * alone; tests/page_cache_test.php checks that every one the handlers answer
 * is in a list.
 *
 * @return array
 */
function pg_page_cache_panel_sub_actions()
{
    return array(
        'designer' => array(
            'key' => 'sub_action',
            'dirty' => array('form_adopt', 'page_detach', 'page_delete', 'import_paste', 'design_publish',
                'design_rename', 'design_delete', 'theme_save', 'theme_look_save', 'theme_palette_save'),
            'clean' => array('presence', 'leave', 'lock_release', 'note_save', 'notes_fetch', 'form_fields',
                'design_targets', 'selectable_pages', 'page_load', 'import_fragment', 'seo_check',
                'widget_ghosts', 'preview_widgets', 'template_prepare', 'template_from_design',
                'template_delete', 'template_discard', 'tab_layout', 'revisions_list', 'revision_get',
                'theme_look_css', 'theme_palette_css',
                'prototype_links', 'prototype_link_create', 'prototype_link_address', 'prototype_link_revoke',
                'ai_state', 'ai_ask', 'ai_status', 'ai_cancel', 'ai_apply', 'ai_dismiss'),
        ),
        'designer_file' => array(
            'key' => 'sub_action',
            'dirty' => array('create', 'delete', 'save'),
            'clean' => array('list_folders', 'read'),
        ),
        'dynamic_region_code' => array(
            'key' => 'sub_action',
            'dirty' => array('save'),
            'clean' => array('read'),
        ),
        'layout_code' => array(
            'key' => 'sub_action',
            'dirty' => array('save'),
            'clean' => array('read', 'regenerate'),
        ),
        'shared_component' => array(
            'key' => 'sub_action',
            'dirty' => array('create', 'from_designer_region', 'update', 'content_update', 'set_content_editable', 'update_config',
                'rename', 'delete', 'delete_many'),
            'clean' => array('list', 'usage_all', 'prefetch', 'get', 'list_custom_forms',
                'list_form_item_view_pages', 'list_calendars', 'list_calendar_event_pages',
                'list_product_groups', 'list_catalog_pages', 'list_catalog_listing_pages',
                'list_catalog_detail_pages', 'list_shopping_cart_pages', 'list_all_pages', 'list_form_fields', 'template_layouts'),
        ),
        'file_explorer' => array(
            'key' => 'type',
            'dirty' => array('explorer_create_folder', 'explorer_create_file', 'explorer_rename', 'explorer_move',
                'explorer_paste', 'explorer_delete_files', 'explorer_upload', 'explorer_folder_access_set',
                'explorer_recycle_delete', 'explorer_recycle_restore', 'explorer_hard_delete',
                'explorer_optimize', 'explorer_webp', 'explorer_folder_settings_set',
                'explorer_pages_bulk_edit', 'explorer_files_bulk_edit', 'explorer_file_save',
                'explorer_rotate', 'explorer_backup_extract', 'explorer_zip_create', 'explorer_zip_extract',
                'explorer_short_link_create', 'explorer_short_link_rename', 'explorer_short_link_update',
                'explorer_short_link_duplicate', 'explorer_short_link_delete', 'explorer_catalog_recycle',
                'explorer_catalog_restore', 'explorer_catalog_purge', 'explorer_catalog_enable',
                'explorer_products_bulk_edit', 'explorer_catalog_quick_edit', 'explorer_catalog_access_set',
                'explorer_catalog_membership_remove', 'explorer_catalog_create_group',
                'explorer_catalog_rename', 'explorer_catalog_paste', 'delete_file'),
            'clean' => array('explorer_list', 'explorer_tree', 'explorer_folder_options',
                'explorer_folder_access_get', 'explorer_delete_check', 'explorer_folder_settings_get',
                'explorer_bulk_page_options', 'explorer_bulk_file_options', 'explorer_shared_list',
                'explorer_files_design', 'explorer_file_get', 'explorer_office_preview',
                'explorer_file_usage', 'explorer_backups_list', 'explorer_backup_zip',
                'explorer_backup_rename', 'explorer_backup_copy', 'explorer_backup_delete',
                'explorer_backup_upload', 'explorer_backup_chmod',
                'explorer_erp_tree', 'explorer_erp_list', 'explorer_short_links_list',
                'explorer_short_link_options', 'explorer_catalog_list', 'explorer_catalog_tree',
                'explorer_catalog_pages', 'explorer_bulk_product_options', 'explorer_catalog_access_get',
                'get_folder_id', 'get_breadcrumb', 'get_tables'),
        ),
    );
}

/**
 * Whether a tool call of the MCP server changed what pages may show: its API
 * route writes (not GET or HEAD), it was no rehearsal (X-Dry-Run; null is an
 * invalid flag, refused) and the route answered with success. The server
 * answers reads with POST too, so the request method says nothing.
 *
 * @param string    $method      the route's method
 * @param bool|null $dry_run
 * @param int       $status_code what the route answered with
 * @return bool
 */
function pg_page_cache_mcp_dirties($method, $dry_run, $status_code)
{
    if (in_array(strtoupper((string) $method), array('', 'GET', 'HEAD', 'OPTIONS'), true)) {
        return false;
    }

    if ($dry_run !== false) {
        return false;
    }

    return ((int) $status_code >= 200) && ((int) $status_code < 300);
}

/**
 * Whether an announced event (pg_announce()) changes what pages show: a page,
 * a product, a product group or a file. Stock moves with every sale and is
 * left to the lifetime; a product that sells out is caught where it happens
 * (submit_order.php).
 *
 * @param string $event
 * @return bool
 */
function pg_page_cache_announce_dirties($event)
{
    return (bool) preg_match('/^(page|product|product_group|file)\./', (string) $event);
}

/**
 * Marks this request as one that changed what pages show: once it ends
 * without an error status, every stored page is made old. Nothing while the
 * cache is off.
 */
function pg_page_cache_mark_dirty()
{
    static $registered = false;

    if ($registered || !pg_page_cache_file_enabled()) {
        return;
    }

    $registered = true;

    register_shutdown_function('pg_page_cache_dirty_shutdown');
}

/**
 * Shutdown function of pg_page_cache_mark_dirty(): a request that ended in an
 * error changed nothing.
 */
function pg_page_cache_dirty_shutdown()
{
    $code = http_response_code();

    if (($code !== false) && ((int) $code >= 400)) {
        return;
    }

    pg_page_cache_bump();
}

/**
 * Makes every stored page old: raises config.page_cache_generation. One
 * UPDATE, the number is read by the router from the config row it reads
 * anyway; nothing before the upgrade step.
 *
 * @return bool
 */
function pg_page_cache_bump()
{
    if (!class_exists('db') || empty(db::$con) || !function_exists('pg_schema_has') || !pg_schema_has('config', 'page_cache_generation')) {
        return false;
    }

    return (bool) @mysqli_query(db::$con, "UPDATE config SET page_cache_generation = page_cache_generation + 1");
}

/**
 * The generation in force, read from the database; 0 before the upgrade
 * step.
 *
 * @return int
 */
function pg_page_cache_generation()
{
    if (!class_exists('db') || empty(db::$con) || !function_exists('pg_schema_has') || !pg_schema_has('config', 'page_cache_generation')) {
        return 0;
    }

    $result = @mysqli_query(db::$con, "SELECT page_cache_generation FROM config LIMIT 1");
    $row = $result ? mysqli_fetch_row($result) : null;

    return is_array($row) ? (int) $row[0] : 0;
}

// ── Purge and sweep ────────────────────────────────────────────────────────

/**
 * The purge (pg_purge_caches()): every stored page is made old and the
 * folder is set aside, for the general job to delete.
 *
 * @return bool whether there was anything to drop
 */
function pg_page_cache_purge()
{
    $directory = pg_page_cache_directory();
    $bumped = pg_page_cache_bump();

    if (!is_dir($directory)) {
        return $bumped;
    }

    return @rename($directory, $directory . '.old-' . time() . '-' . mt_rand(1000, 9999)) || $bumped;
}

/**
 * Deletes a folder tree for as long as the deadline allows.
 *
 * @param string $path
 * @param float  $deadline microtime(true)
 * @param int    $older_than delete only files changed before this time; 0 for all
 * @return array finished (bool), files (int)
 */
function pg_page_cache_remove_tree($path, $deadline, $older_than = 0)
{
    $result = array('finished' => true, 'files' => 0);
    $entries = @scandir($path);

    if (!is_array($entries)) {
        return $result;
    }

    foreach ($entries as $entry) {
        if (($entry === '.') || ($entry === '..')) {
            continue;
        }

        if (microtime(true) > $deadline) {
            $result['finished'] = false;
            return $result;
        }

        $item = $path . '/' . $entry;

        if (is_dir($item) && !is_link($item)) {
            $inner = pg_page_cache_remove_tree($item, $deadline, $older_than);
            $result['files'] += $inner['files'];

            if (!$inner['finished']) {
                $result['finished'] = false;
                return $result;
            }

            @rmdir($item);
            continue;
        }

        $changed = @filemtime($item);

        if (($older_than > 0) && ($changed !== false) && ($changed >= $older_than)) {
            continue;
        }

        if (@unlink($item)) {
            $result['files']++;
        }
    }

    return $result;
}

/**
 * The general job's pass, at most once every ten minutes and for as many
 * seconds as it is given: folders a purge set aside, generations other than
 * the one in force, and in that one the copies older than twice the lifetime
 * and the locks and half-written files a dead request left. With the cache
 * off, everything goes.
 *
 * @param int      $seconds
 * @param int|null $generation the one in force; read from the database when
 *                             there is work to do
 * @return array state (done, more, skipped), files
 */
function pg_page_cache_sweep_run($seconds = 10, $generation = null)
{
    $directory = pg_page_cache_directory();
    $parent = dirname($directory);
    $result = array('state' => 'skipped', 'files' => 0);
    $now = time();
    $deadline = microtime(true) + max(1, (int) $seconds);

    $aside = glob($directory . '.old-*', GLOB_ONLYDIR);
    $aside = is_array($aside) ? $aside : array();

    $stamp = $parent . '/page-sweep.txt';
    $last = is_file($stamp) ? (int) @file_get_contents($stamp) : 0;

    if (!$aside && (!is_dir($directory) || ($last > ($now - 600)))) {
        return $result;
    }

    $result['state'] = 'done';
    $ttl = pg_page_cache_file_enabled() ? pg_page_cache_ttl() : 0;

    if ($generation === null) {
        $generation = pg_page_cache_generation();
    }

    foreach ($aside as $folder) {
        $removed = pg_page_cache_remove_tree($folder, $deadline);
        $result['files'] += $removed['files'];

        if (!$removed['finished']) {
            $result['state'] = 'more';
            return $result;
        }

        @rmdir($folder);
    }

    $entries = is_dir($directory) ? @scandir($directory) : array();

    foreach ((is_array($entries) ? $entries : array()) as $entry) {
        if (!preg_match('/^g(\d+)$/', $entry, $match)) {
            continue;
        }

        $current = ($ttl > 0) && ((int) $match[1] === (int) $generation);
        $removed = pg_page_cache_remove_tree($directory . '/' . $entry, $deadline, $current ? ($now - (2 * $ttl)) : 0);
        $result['files'] += $removed['files'];

        if (!$removed['finished']) {
            $result['state'] = 'more';
            return $result;
        }

        if (!$current) {
            @rmdir($directory . '/' . $entry);
        }
    }

    @file_put_contents($stamp, (string) $now);

    return $result;
}
