<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// Database availability guard: a circuit breaker in front of mysqli_connect().
//
// Measured on a live site (2026-08-31): a bot flooded forgot_password.php,
// which holds its database connection across an SMTP send. max_user_connections
// filled, and from then on EVERY request died inside db_connect() -- including
// the administrator's own panel. The firewall could not intervene because it
// runs 512 lines after the connection is opened, so the site's defences became
// unreachable at exactly the moment they were needed.
//
// Two things made it worse rather than self-limiting:
//
//   1. Every new request still attempted a connection. A saturated server
//      spends work rejecting connections too, so the pool could not drain
//      while traffic continued.
//   2. mysqli throws by default since PHP 8.1 and db_connect() did not catch
//      it, so each request wrote a full uncaught-exception stack trace to the
//      error log -- tens of megabytes an hour, on hosting with a disk quota.
//
// This file is deliberately standalone: no database, no functions.php, no
// session. router.php loads it without functions.php at all, which is the same
// constraint waf.php and get_file.php work under.

if (!defined('PG_DB_GUARD_LOADED')) {

    define('PG_DB_GUARD_LOADED', true);

    /**
     * Directory holding the breaker's state files.
     *
     * data/temp already holds every other cache this software keeps and is
     * writable wherever the software works at all.
     */
    function pg_db_guard_dir()
    {
        return dirname(dirname(__FILE__)) . '/data/temp';
    }

    /**
     * How long a tripped breaker keeps failing fast, in seconds.
     *
     * Short by design. The window only has to outlast the burst that filled
     * the pool; anything longer keeps the site down after the database has
     * recovered. The breaker expires on its own, so a stuck state file can
     * never lock a site out permanently.
     */
    function pg_db_guard_window()
    {
        $seconds = defined('DB_UNAVAILABLE_BACKOFF') ? (int) DB_UNAVAILABLE_BACKOFF : 30;

        if ($seconds < 5) {
            $seconds = 5;
        }

        if ($seconds > 300) {
            $seconds = 300;
        }

        return $seconds;
    }

    /**
     * Age of the breaker file in seconds, or false when it is not set.
     *
     * Read once per request. The result is reused by the success path so a
     * healthy request costs exactly one stat() rather than two.
     */
    function pg_db_guard_tripped_at($recheck = false)
    {
        static $cached = null;

        if ($cached !== null && !$recheck) {
            return $cached;
        }

        $cached = false;
        $file = pg_db_guard_dir() . '/db_unavailable';

        if (@is_file($file)) {
            $mtime = @filemtime($file);

            if ($mtime) {
                $cached = (int) $mtime;
            }
        }

        return $cached;
    }

    /**
     * A reference an operator can quote, the same one for the whole outage.
     *
     * Anchored to the trip time rather than the request, so every 503 served
     * during one window carries the same code and pg_db_guard_log_recovery()
     * can stamp that same code on the firewall row it writes when the site
     * comes back. That is the whole point: the person who saw the page and the
     * operator reading the log are looking at one string. Twelve hex digits to
     * match the firewall's own reference shape.
     *
     * Falls back to a window-sized time bucket when there is no trip file (the
     * page should never be blank of a reference), which still groups the burst
     * of requests around one moment under one code.
     */
    function pg_db_guard_reference($tripped = null)
    {
        if ($tripped === null) {
            $tripped = pg_db_guard_tripped_at();
        }

        if (!$tripped) {
            $tripped = time() - (time() % max(1, pg_db_guard_window()));
        }

        return strtoupper(substr(md5('pg-db-guard|' . (int) $tripped), 0, 12));
    }

    /**
     * True while the breaker is open and the connection should not be tried.
     */
    function pg_db_guard_is_open()
    {
        $tripped = pg_db_guard_tripped_at();

        if ($tripped === false) {
            return false;
        }

        // A timestamp in the future means someone's clock moved; treat it as
        // expired rather than trusting it, so the site cannot be held down by
        // a bad mtime.
        if ($tripped > time()) {
            return false;
        }

        return (($tripped + pg_db_guard_window()) > time());
    }

    /**
     * Open the breaker.
     *
     * Every failure is silent: if data/temp is not writable the breaker simply
     * does not engage and the request behaves as it did before this file
     * existed. A guard that could itself throw would be worse than no guard.
     */
    function pg_db_guard_trip()
    {
        $dir = pg_db_guard_dir();

        if (!@is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        @touch($dir . '/db_unavailable');
        pg_db_guard_tripped_at(true);
    }

    /**
     * Close the breaker after a successful connection.
     *
     * Only called when the cached stat says a file is actually there, so a
     * healthy request never pays for an unlink() syscall.
     */
    function pg_db_guard_clear()
    {
        $tripped = pg_db_guard_tripped_at();

        if ($tripped === false) {
            return;
        }

        @unlink(pg_db_guard_dir() . '/db_unavailable');
        pg_db_guard_tripped_at(true);

        // Leave a mark for the recovery notice below. The breaker itself runs
        // where nothing can be written to the database - by definition - so
        // the only moment an outage can be recorded is the first request that
        // gets a connection back.
        $GLOBALS['pg_db_guard_recovered_from'] = $tripped;
    }

    /**
     * Record a finished outage in the firewall log.
     *
     * The incident this file exists for produced no firewall event whatsoever:
     * the operator discovered days of downtime by opening the PHP error log on
     * a hunch. An outage that leaves no trace on the screen the operator
     * actually watches will be found the same way next time.
     *
     * Called from db_connect() on the first successful connection after a trip,
     * which is the earliest point a row can be written at all.
     */
    function pg_db_guard_log_recovery()
    {
        if (empty($GLOBALS['pg_db_guard_recovered_from'])) {
            return;
        }

        $tripped = (int) $GLOBALS['pg_db_guard_recovered_from'];
        unset($GLOBALS['pg_db_guard_recovered_from']);

        if (!function_exists('waf_log_event')) {
            return;
        }

        $seconds = max(1, time() - $tripped);

        // The same reference the 503 pages showed for this outage, so the row
        // an operator finds here is the one the person on the page can quote.
        $reference = pg_db_guard_reference($tripped);

        // Logged as an observation, never as a block: no visitor did anything
        // wrong here, and scoring it would let a database outage auto-ban the
        // first person who happened to arrive as the site came back.
        waf_log_event('log', 'db-unavailable', 'system', 0, 'database',
            'connections exhausted, ' . $seconds . 's [ref: ' . $reference . ']');
    }

    /**
     * Whether this connection error is worth backing off from.
     *
     * Overload and unreachable-server errors are transient: retrying in a
     * tight loop is what keeps them from clearing. Credentials and missing
     * databases are not - backing off would hide a misconfiguration behind a
     * "come back later" page the operator can do nothing about, so those keep
     * the old behaviour and report the error.
     *
     *   1040 ER_CON_COUNT_ERROR          too many connections
     *   1203 ER_TOO_MANY_USER_CONNECTIONS  max_user_connections (this incident)
     *   1226 ER_USER_LIMIT_REACHED       account resource limit
     *   2002 CR_CONNECTION_ERROR         socket refused
     *   2003 CR_CONN_HOST_ERROR          host unreachable
     *   2006 CR_SERVER_GONE_ERROR        server went away
     *   2013 CR_SERVER_LOST              lost during query
     */
    function pg_db_guard_is_overload($errno)
    {
        return in_array((int) $errno, array(1040, 1203, 1226, 2002, 2003, 2006, 2013), true);
    }

    /**
     * Write one line to the error log, at most once a minute.
     *
     * The incident produced a stack trace per request. The information in the
     * hundredth copy is the same as in the first, and the volume is what buries
     * the unrelated warnings an operator actually needs to read.
     */
    function pg_db_guard_log($message)
    {
        $dir = pg_db_guard_dir();
        $file = $dir . '/db_unavailable_logged';

        $last = @is_file($file) ? (int) @filemtime($file) : 0;

        if (($last + 60) > time()) {
            return;
        }

        if (!@is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        @touch($file);
        @error_log('Pinegrap: ' . $message);
    }

    /**
     * Serve "temporarily unavailable" and stop.
     *
     * 503 with Retry-After is the correct answer and it matters beyond
     * tidiness: a blank page or a 200 carrying an error string teaches search
     * engines the page is broken, while a 503 tells them - and every
     * well-behaved crawler - to come back later. Nothing here touches the
     * database, the session or the translation files, because this runs
     * precisely when the parts that need them are unavailable.
     */
    function pg_db_guard_unavailable($retry_after = 0)
    {
        $retry_after = ((int) $retry_after > 0) ? (int) $retry_after : pg_db_guard_window();

        if (!headers_sent()) {
            header('HTTP/1.1 503 Service Unavailable');
            header('Status: 503 Service Unavailable');
            header('Retry-After: ' . $retry_after);
            header('Cache-Control: no-store, max-age=0');
            header('Content-Type: text/html; charset=utf-8');
        }

        // The reference is the string an operator can match against the
        // firewall log. Same value for the whole outage; see the recovery
        // logger, which stamps the same code on the row it writes.
        $reference = pg_db_guard_reference();

        // Bilingual and hard-coded. lang() reads a JSON file and would work,
        // but this page has to render when the installation is at its least
        // healthy, so it depends on nothing.
        //
        // The Turkish uses numeric entities for the letters HTML has no named
        // entity for: &#351; is ş (s with a cedilla), not &scaron; which is š
        // (s with a caron) and rendered the word as "mešgul".
        echo '<!DOCTYPE html><html lang="tr"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<meta name="robots" content="noindex">'
            . '<title>503</title></head>'
            . '<body style="font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif;'
            . 'margin:0;display:flex;min-height:100vh;align-items:center;justify-content:center;'
            . 'text-align:center;color:#333;background:#fafafa">'
            . '<div style="padding:2rem;max-width:32rem">'
            . '<h1 style="font-size:1.25rem;margin:0 0 .75rem">Site ge&ccedil;ici olarak me&#351;gul</h1>'
            . '<p style="margin:0 0 1.25rem;line-height:1.5">Sunucu &#351;u anda yo&#287;un. '
            . 'L&uuml;tfen birka&ccedil; saniye sonra tekrar deneyin.</p>'
            . '<h2 style="font-size:1rem;margin:0 0 .5rem;font-weight:600">Temporarily unavailable</h2>'
            . '<p style="margin:0 0 1.25rem;line-height:1.5">The server is busy. Please try again in a few seconds.</p>'
            . '<p style="margin:0;font-size:.8rem;color:#888">Referans / Reference: '
            . '<code style="font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace">'
            . htmlspecialchars($reference, ENT_QUOTES, 'UTF-8') . '</code></p>'
            . '</div></body></html>';

        exit();
    }

    /* ─────────────────────────────────────────────────────────────────────
       BAN SHIELD

       A copy of the firewall's block list, kept in a file so a banned address
       can be turned away before a database connection is opened.

       The firewall proper reads banned_ip_addresses, which means it cannot
       act while the connection pool is full - the state where turning an
       attacker away matters most. This mirror closes that gap for the one
       decision cheap enough to make without a database.

       Honest limits, so nobody expects more of it than it gives:

         - It only knows addresses that were ALREADY banned. The first wave
           still has to reach the database for the firewall to notice and ban
           it. This shortens an incident; it does not prevent one.
         - Blocks only. The allow list is not mirrored: failing to recognise a
           friend costs a firewall check, failing to recognise an enemy costs
           the site.
         - No rate limiting, no counting, no signature scanning, no logging.
           Those need shared counters and settings that only the database has.

       That last line used to be shorter, because this file also carried a
       "siege mode" that counted requests per address in a file so a flood
       could be stopped before the connection was opened. It was removed on
       review, and the reasons are worth keeping so it is not rebuilt the same
       way:

         - The counter's window never closed. Appending a byte updates the
           file's mtime, so the "start a fresh window" test measured silence
           since the LAST request, not elapsed time since the first. A visitor
           making ten requests a minute crossed a sixty-request threshold in
           six minutes, and static files served through router.php counted too
           - one page with thirty images is thirty counts.
         - It ran twice per request. router.php and init.php both call the
           guard and there was no latch, so the effective threshold was half
           the configured one.
         - An attacker opted out by sending one X-Forwarded-For header. The
           header made the address unverifiable, and rather than count the
           wrong address the check returned - so the only clients actually
           counted were ordinary browsers, which never send it. A limiter that
           the attacker skips and the customer cannot is worse than none.
         - A routine MySQL restart (error 2006) armed it for ten minutes.
         - There was no allow list at this layer, so a false positive on the
           office address had no way out but FTP.

       The first three are fixable; the third is not a bug but a design that
       cannot work here, because the address cannot be trusted before the
       database says which proxies may speak for someone else. Counting
       belongs where that answer is available.
       ───────────────────────────────────────────────────────────────────── */

    /**
     * Read the mirrored block list, or false when there is not a usable one.
     *
     * A stale mirror is worse than none: it would keep enforcing bans the
     * operator has since lifted, from a file they have no screen for. Rather
     * than trust it indefinitely the file carries its own expiry, and the
     * database remains the only authority that can renew it.
     */
    function pg_ban_shield_list()
    {
        static $cached = null;

        if ($cached !== null) {
            return $cached;
        }

        $cached = array('proxies' => array(), 'blocks' => array(), 'allows' => array(), 'log' => true);

        $file = pg_db_guard_dir() . '/ban_shield.txt';
        $raw = '';
        $fresh = false;

        // The mirror is optional. A missing or unreadable file leaves both
        // lists empty and the request proceeds exactly as it did before this
        // file existed - the shield can only ever subtract from what reaches
        // the database, never add to it.
        if (@is_file($file)) {
            $contents = @file_get_contents($file);

            if ($contents !== false) {
                $raw = $contents;

                // Six hours. Long enough to cover an outage, short enough that
                // a mirror nobody refreshes stops being consulted rather than
                // hardening into a second, invisible ban list the operator has
                // no screen for.
                $fresh = (((int) @filemtime($file) + 21600) >= time());
            }
        }

        // Two prefixes: P for a trusted proxy, B for a blocked address. The
        // proxy lines are waf_client_ip()'s resolved view - embedded CDN
        // ranges plus whatever the operator declared - written out by
        // waf_ban_shield_refresh() so this file does not have to reimplement
        // that decision.
        foreach (explode("\n", $raw) as $line) {
            $line = trim($line);

            if (strlen($line) < 3) {
                continue;
            }

            $value = trim(substr($line, 2));

            if ($value === '') {
                continue;
            }

            if ($line[0] === 'P') {

                // Proxy lines do NOT expire with the rest of the mirror, and
                // the asymmetry is deliberate.
                //
                // A stale ban is dangerous - it enforces something the
                // operator may have lifted, from a file they have no screen
                // for. A stale proxy range is not: CDN address blocks change
                // over months, and the line only decides WHICH address a
                // request is attributed to.
                //
                // Expiring them together would be worse than useless behind a
                // CDN. With no proxy lines this file falls back to
                // REMOTE_ADDR, which is the edge rather than the visitor, so
                // every ban would be matched against an address no ban list
                // ever contains - the shield would quietly stop working while
                // still appearing to run. And a long outage is exactly when
                // the mirror cannot be refreshed, because refreshing it needs
                // the database.
                $cached['proxies'][] = $value;

            } elseif ($line[0] === 'B' && $fresh) {
                $cached['blocks'][] = $value;

            } elseif ($line[0] === 'A') {

                // Allow lines are read whether or not the mirror is fresh, the
                // same asymmetry the proxy lines get and for the same reason:
                // a stale allow entry lets somebody in who should have been
                // checked one layer later, a stale block entry turns somebody
                // away with no screen anywhere to explain it. Only one of
                // those two mistakes can take a site off the air.
                $cached['allows'][] = $value;

            } elseif ($line[0] === 'L') {
                // Whether refusals go to the plain-text log; written by
                // waf_ban_shield_refresh() from the setting this file cannot
                // read for itself.
                $cached['log'] = ($value !== '0');
            }
        }

        return $cached;
    }

    /**
     * Note one refusal for the firewall log.
     *
     * A line per refused request: when, who, and which path. Capped at a
     * quarter of a megabyte - roughly five thousand lines - because under a
     * flood from a banned address this is written on every single request and
     * the drain only runs when somebody who is NOT banned comes along. Past
     * the cap the file simply stops growing; the rows already in it carry the
     * counter that says an attack is under way, which is the part an operator
     * needs. Tab separated: a path can contain almost anything, a tab is the
     * one thing it cannot.
     */
    function pg_ban_shield_record($ip)
    {
        $file = pg_db_guard_dir() . '/shield_pending.log';
        $size = @filesize($file);

        if ($size !== false && $size > 262144) {
            return;
        }

        $path = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';

        $question = strpos($path, '?');

        if ($question !== false) {
            $path = substr($path, 0, $question);
        }

        $path = substr(str_replace(array("\t", "\n", "\r"), '', $path), 0, 200);

        @file_put_contents($file, time() . "\t" . $ip . "\t" . $path . "\n", FILE_APPEND | LOCK_EX);
    }

    /**
     * The shield's copy of waf_text_log(), for the same file in the same
     * shape: one DENY line per refused request, local time, ip= first.
     *
     * Duplicated rather than shared because this file must not load waf.php.
     * Same cap, too - five megabytes and one rotated copy - because a refused
     * flood is exactly when this line is written most.
     */
    function pg_ban_shield_text_log($ip)
    {
        $file = dirname(dirname(__FILE__)) . '/data/firewall.log';
        $size = @filesize($file);

        if ($size !== false && $size > 5242880) {
            @unlink($file . '.1');
            @rename($file, $file . '.1');
        }

        @file_put_contents(
            $file,
            date('Y-m-d H:i:s') . ' pinegrap-firewall DENY ip=' . $ip . ' status=403 rule=ip-list-shield' . "\n",
            FILE_APPEND | LOCK_EX
        );
    }

    /**
     * Resolve the visitor's address well enough to match the mirror.
     *
     * A reduced stand-in for waf_client_ip(), which cannot be used here: it
     * lives in waf.php and reads its trusted-proxy list from the database,
     * neither of which is available this early. The proxy list comes from the
     * mirror instead, so the two agree on which peers may speak for someone
     * else even though only one of them can ask the database.
     *
     * Only the two unambiguous single-value headers are read. X-Forwarded-For
     * is a client-appendable chain, and parsing it correctly needs the full
     * trusted-hop walk in waf.php; getting it half right here would let an
     * attacker choose which address the shield judges - the opposite of the
     * point.
     */
    function pg_ban_shield_client_ip($proxies)
    {
        $remote = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';

        if ($remote === '' || !$proxies) {
            return $remote;
        }

        $trusted = false;

        foreach ($proxies as $proxy) {
            if (waf_ip_matches_simple($remote, $proxy)) {
                $trusted = true;
                break;
            }
        }

        if (!$trusted) {
            return $remote;
        }

        foreach (array('HTTP_CF_CONNECTING_IP', 'HTTP_TRUE_CLIENT_IP') as $header) {

            if (empty($_SERVER[$header])) {
                continue;
            }

            $candidate = trim($_SERVER[$header]);

            if (@inet_pton($candidate) !== false) {
                return $candidate;
            }
        }

        return $remote;
    }

    /**
     * Refuse the request when this address is on the mirrored block list.
     *
     * Answers 403 and nothing else - no explanation, no reference number. The
     * firewall's own block page can say more because it has a database to log
     * the event in; this path has none, and a code the operator cannot look up
     * later would only be noise.
     */
    function pg_ban_shield_check()
    {
        $list = pg_ban_shield_list();

        if ($list === false) {
            return;
        }

        $ip = pg_ban_shield_client_ip($list['proxies']);

        if ($ip === '') {
            return;
        }

        // The allow list wins over everything, here as in waf_run().
        foreach ($list['allows'] as $pattern) {
            if (waf_ip_matches_simple($ip, $pattern)) {
                return;
            }
        }

        foreach ($list['blocks'] as $pattern) {
            if (waf_ip_matches_simple($ip, $pattern)) {

                // Recorded for the firewall log, which this file cannot write
                // to: waf_shield_drain() picks the line up on the next request
                // that has a database. Kept separate from the fail2ban text
                // log above - that one is an operator's switch, this one is
                // the event making it onto the screen at all, and a rule that
                // is enforced unconditionally has to be visible
                // unconditionally.
                pg_ban_shield_record($ip);

                if (!empty($list['log'])) {
                    pg_ban_shield_text_log($ip);
                }

                if (!headers_sent()) {
                    header('HTTP/1.1 403 Forbidden');
                    header('Status: 403 Forbidden');
                    header('Cache-Control: no-store, max-age=0');
                    header('Content-Type: text/plain; charset=utf-8');
                }

                echo 'Forbidden';
                exit();
            }
        }
    }

    /**
     * Match an address against one mirrored pattern.
     *
     * Deliberately a reduced copy of waf_ip_matches(): exact match and CIDR
     * only. The legacy asterisk form is not handled here, because this runs
     * before waf.php is loaded and duplicating the whole matcher would create
     * two implementations of one security decision that could drift apart.
     * Patterns this cannot read are simply skipped - the firewall still
     * enforces them a moment later, once the database is reachable.
     */
    function waf_ip_matches_simple($ip, $pattern)
    {
        $pattern = trim($pattern);

        if ($pattern === '') {
            return false;
        }

        if (strcasecmp($ip, $pattern) === 0) {
            return true;
        }

        if (strpos($pattern, '/') === false) {
            return false;
        }

        $parts = explode('/', $pattern, 2);
        $prefix = trim($parts[1]);

        // A prefix that is not a plain number makes the pattern meaningless,
        // and it has to be REJECTED rather than interpreted. "1.2.3.4/" and
        // "1.2.3.4/abc" both reached (int) as 0, and 0 is a valid prefix
        // length meaning "compare no bits at all" - so this returned true for
        // every address on earth, from a file that has no screen, before the
        // database was even reached. The same fault was in waf_ip_in_cidr().
        if ($prefix === '' || !ctype_digit($prefix)) {
            return false;
        }

        $subnet_bin = @inet_pton(trim($parts[0]));
        $ip_bin = @inet_pton($ip);

        if ($subnet_bin === false || $ip_bin === false) {
            return false;
        }

        // Never compare across address families: inet_pton returns 4 bytes for
        // IPv4 and 16 for IPv6, and the prefix arithmetic would be nonsense.
        if (strlen($ip_bin) !== strlen($subnet_bin)) {
            return false;
        }

        $bits = (int) $prefix;
        $max_bits = strlen($ip_bin) * 8;

        if ($bits > $max_bits) {
            $bits = $max_bits;
        }

        $whole = (int) ($bits / 8);
        $rest = $bits % 8;

        if ($whole > 0 && strncmp($ip_bin, $subnet_bin, $whole) !== 0) {
            return false;
        }

        if ($rest === 0) {
            return true;
        }

        $mask = ~((1 << (8 - $rest)) - 1) & 0xFF;

        return ((ord($ip_bin[$whole]) & $mask) === (ord($subnet_bin[$whole]) & $mask));
    }

    /**
     * Fail fast when the breaker is open, then when the address is mirrored.
     *
     * Called at the top of init.php and router.php rather than next to the
     * connection itself. By the time db_connect() runs, init.php has already
     * required functions.php -- megabytes of parsing this request will throw
     * away. Checking here costs one stat() and skips all of it.
     *
     * Order matters: the breaker is one stat() and applies to everyone, so it
     * comes first. The shield reads a file and is only consulted when the site
     * is otherwise about to serve the request normally.
     *
     * Latched, because BOTH files call it for the same request: router.php is
     * the front controller and the page it dispatches to includes init.php.
     * The work is idempotent, so running twice was harmless here - but a later
     * check that counts anything would silently count double, which is exactly
     * how the removed siege counter ended up with half the threshold it
     * advertised.
     */
    function pg_db_guard_check()
    {
        static $done = false;

        if ($done) {
            return;
        }

        $done = true;

        if (pg_db_guard_is_open()) {
            pg_db_guard_unavailable();
        }

        pg_ban_shield_check();
    }
}
