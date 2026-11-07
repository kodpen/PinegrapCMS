<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The one answer to "did this request reach the visitor over HTTPS" when TLS
 * ends before PHP: a CDN, reverse proxy, load balancer, ingress or tunnel
 * talks plain HTTP to this server and the only evidence is a forwarded
 * header. Two questions, kept apart because their callers differ:
 *
 *   pg_forwarded_https()    what the headers claim (the parser);
 *   pg_https_peer_trusted() whether the peer that sent them may be believed.
 *
 * Secure Mode, the file server and the System Status check ask both: a
 * forged claim from an unknown peer must not redirect the site or pass the
 * Secure Mode guard. The cookie Secure flag and the page cache key ask only
 * the parser: a false https claim affects only the request that made it (a
 * Secure cookie its browser drops, a cache copy drawn for it alone), and a
 * true claim from a peer not yet listed must still give a cookie that sticks.
 *
 * The request's address is read here too (pg_request_uri_from_server()):
 * which server headers name it is the same kind of trust question.
 *
 * Loaded by functions.php, and on its own by get_file.php, router.php, the
 * router's page cache module and prototype.php, which read the request
 * before functions.php. The peer check requires
 * waf.php for the address matching and the Cloudflare ranges, when it is
 * asked and the firewall is not loaded yet (a script that loads functions.php
 * without init.php); that file is guarded and only defines. The parser needs
 * nothing.
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
 * Whether a proxy in front says the visitor's connection is HTTPS. One
 * recognised header saying so is enough: an inner proxy may rewrite
 * X-Forwarded-Proto to http while CF-Visitor still carries the visitor's
 * https (Cloudflare Flexible to nginx on port 80), and that request is https.
 *
 * Headers, each with the value that means https:
 *   X-Forwarded-Proto  "https" as the FIRST value; a chain lists one value
 *                      per hop ("https, http") and the left-most is the
 *                      visitor's. Also sent by Cloudflare.
 *   Forwarded          RFC 7239, proto=https (quoted or not).
 *   X-Forwarded-Ssl    "on" (older nginx, AWS ELB).
 *   Front-End-Https    "on" (Microsoft ISA/TMG, IIS ARR).
 *   X-ARR-SSL          any value: IIS ARR sets it only on an HTTPS front
 *                      connection, with the certificate's details.
 *   CF-Visitor         {"scheme":"https"} (Cloudflare).
 *   X-Forwarded-Port   443: some proxies rewrite the port, not the scheme.
 *
 * Pure: reads only the array it is given, so the matrix is testable.
 *
 * @param array $server $_SERVER, or an array shaped like it
 * @return bool
 */
function pg_forwarded_https($server)
{
    if (!is_array($server)) {
        return false;
    }

    $header = function ($name) use ($server) {
        return (isset($server[$name]) && is_scalar($server[$name])) ? strtolower(trim((string) $server[$name])) : '';
    };

    $first = explode(',', $header('HTTP_X_FORWARDED_PROTO'));

    if (trim($first[0]) === 'https') {
        return true;
    }

    if (preg_match('/proto\s*=\s*"?https"?/', $header('HTTP_FORWARDED'))) {
        return true;
    }

    if ($header('HTTP_X_FORWARDED_SSL') === 'on') {
        return true;
    }

    if ($header('HTTP_FRONT_END_HTTPS') === 'on') {
        return true;
    }

    if ($header('HTTP_X_ARR_SSL') !== '') {
        return true;
    }

    if (strpos($header('HTTP_CF_VISITOR'), '"scheme":"https"') !== false) {
        return true;
    }

    return ($header('HTTP_X_FORWARDED_PORT') === '443');
}

/**
 * Whether the peer that sent the forwarded headers is one whose word on the
 * scheme counts. The headers are set by whoever connects to this server, so
 * they are believed only from:
 *
 *   - any peer, when TRUST_PROXY_SSL_HEADERS is true in data/config.php
 *     (the file-level override for an origin reachable from the proxy alone);
 *   - a peer covered by Settings > Firewall > Trusted Proxies, the same list
 *     the firewall reads the visitor's address with, whether or not the
 *     firewall is on;
 *   - a Cloudflare edge address, which the firewall recognises by itself.
 *
 * A loopback or private peer is not trusted on its own: shared hosts and
 * container networks share those addresses, so a local tunnel or proxy is
 * listed by the operator.
 *
 * @param string    $remote      REMOTE_ADDR
 * @param string    $trusted_raw the Trusted Proxies list as stored
 *                               (config.waf_trusted_proxies)
 * @param bool|null $override    null reads TRUST_PROXY_SSL_HEADERS; a bool
 *                               stands in for it (tests)
 * @return bool
 */
function pg_https_peer_trusted($remote, $trusted_raw, $override = null)
{
    if ($override === null) {
        $override = (defined('TRUST_PROXY_SSL_HEADERS') && TRUST_PROXY_SSL_HEADERS === true);
    }

    if ($override) {
        return true;
    }

    // Every request through router.php or init.php has the firewall loaded
    // before this is asked; a script without it gets the same functions.
    if (!function_exists('waf_proxy_ranges')) {
        require_once(PG_FUNCTIONS_DIR . '/waf.php');
    }

    $remote = trim((string) $remote);

    if (($remote === '') || !waf_is_ip($remote)) {
        return false;
    }

    $ranges = waf_proxy_ranges((string) $trusted_raw);

    if ($ranges && waf_ip_in_any($remote, $ranges)) {
        return true;
    }

    return waf_ip_in_any($remote, waf_cloudflare_ranges());
}

/**
 * What the Firewall screen offers to list under Trusted Proxies for the peer
 * that sent an https claim this server does not trust yet, so Secure Mode can
 * be turned on in one step. The peer's own address, never its network:
 *
 *   loopback  both 127.0.0.1 and ::1, since a local tunnel or TLS proxy on
 *             this host may connect over either family;
 *   private   that one address (10/8, 172.16/12, 192.168/16, a ULA); a proxy
 *             that moves within its network is listed by hand;
 *   public    nothing: a CDN or load balancer is listed with all of its
 *             published ranges first (Cloudflare is recognised on its own);
 *   other     nothing: link-local and reserved addresses are listed by hand.
 *
 * An IPv4 peer handed over as ::ffff:a.b.c.d is offered as a.b.c.d, the form
 * the firewall matches it in. Pure: no list is read.
 *
 * @param string $remote REMOTE_ADDR
 * @return array kind ('loopback', 'private', 'public', 'other', '' for no
 *               address) and entries (the addresses to add, possibly none)
 */
function pg_https_peer_offer($remote)
{
    $remote = trim((string) $remote);

    if (preg_match('/^::ffff:(\d{1,3}(?:\.\d{1,3}){3})$/i', $remote, $mapped)) {
        $remote = $mapped[1];
    }

    if (($remote === '') || (filter_var($remote, FILTER_VALIDATE_IP) === false)) {
        return array('kind' => '', 'entries' => array());
    }

    $packed = @inet_pton($remote);

    $loopback = (strpos($remote, ':') === false)
        ? (strpos($remote, '127.') === 0)
        : ($packed === str_repeat("\0", 15) . "\x01");

    if ($loopback) {
        return array('kind' => 'loopback', 'entries' => array('127.0.0.1', '::1'));
    }

    if (filter_var($remote, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE) === false) {
        return array('kind' => 'private', 'entries' => array($remote));
    }

    if (filter_var($remote, FILTER_VALIDATE_IP, FILTER_FLAG_NO_RES_RANGE) === false) {
        return array('kind' => 'other', 'entries' => array());
    }

    return array('kind' => 'public', 'entries' => array());
}

/**
 * The request's address (path and query string) as the visitor asked for
 * it. X-Rewrite-URL carries the original address only on IIS, where
 * ISAPI_Rewrite sets it and REQUEST_URI holds the rewritten one; on any
 * other server nothing sets it but the client, whose header would replace
 * the address the router serves and the firewall checks, so it is honoured
 * on IIS only. Without REQUEST_URI (IIS with no rewriting) the address is
 * PHP_SELF with the query string.
 *
 * Pure: reads only the array it is given. Loaded on its own by router.php,
 * get_file.php and prototype.php, which read the address before
 * functions.php.
 *
 * @param array $server $_SERVER, or an array shaped like it
 * @return string
 */
function pg_request_uri_from_server($server)
{
    if (isset($server['HTTP_X_REWRITE_URL']) && ((string) $server['HTTP_X_REWRITE_URL'] !== '')
        && (stripos(isset($server['SERVER_SOFTWARE']) ? (string) $server['SERVER_SOFTWARE'] : '', 'IIS') !== false)) {
        return (string) $server['HTTP_X_REWRITE_URL'];
    }

    if (isset($server['REQUEST_URI'])) {
        return (string) $server['REQUEST_URI'];
    }

    $self = isset($server['PHP_SELF']) ? (string) $server['PHP_SELF'] : '';

    return (isset($server['QUERY_STRING']) && ((string) $server['QUERY_STRING'] !== '')) ? $self . '?' . $server['QUERY_STRING'] : $self;
}
