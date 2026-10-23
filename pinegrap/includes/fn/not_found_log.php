<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The 404 log (2026.11.0, 11.0.30): the addresses visitors asked for that no
 * page, file or short link answers, so the ones worth keeping can become
 * redirects (a 301 short link, view_not_found_log.php).
 *
 * Only the router's hand-over to get_page.php is logged (PG_SHORT_LINK_PATH:
 * no file, no exact short link, no home page), at the three places a page
 * answers 404 once the * patterns had their chance: output_error() and
 * error() with 404, and pg_sw_item_missing(). A panel screen that says
 * "not found", get_file.php, a feed and the command line renders never reach
 * it. The address is kept in the form a short link name takes
 * (pg_short_link_path_name()): no language prefix, which the router took off
 * and a redirect puts back, no query string, at most 191 characters, and only
 * a name the short link rule accepts, so every row can become a redirect as
 * it is and a scanner's odd requests are not stored.
 *
 * Bots are not counted, as for the visitor rows (pg_request_is_automated()),
 * except search engine crawlers the firewall verified (waf_bot_verified()):
 * their 404s are what the search results will drop, so they are counted in
 * crawler_hits beside the visitors. A request the firewall already logged
 * (waf_last_rule()) is left out too.
 *
 * The write waits for the end of the request (register_shutdown_function()),
 * when the answer is known to be a 404, and uses no db() helper: a failure
 * there would end in output_error() inside an error screen. One indexed
 * UPDATE for an address seen before, an INSERT for a new one; ids therefore
 * count rows, not requests, which the row cap relies on.
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
 * Days an address stays in the log after it was last asked for.
 */
define('PG_NOT_FOUND_LOG_DAYS', 90);

/**
 * Rows the log keeps at most: past it, the addresses not asked for the
 * longest go first.
 */
define('PG_NOT_FOUND_LOG_MAX_ROWS', 10000);

/**
 * One new address in this many checks the row cap.
 */
define('PG_NOT_FOUND_LOG_CAP_SAMPLE', 20);

/**
 * The longest address kept, short_links.name's width.
 */
define('PG_NOT_FOUND_LOG_PATH_MAX', 191);

/**
 * The state file of the daily sweep, under data/temp.
 */
define('PG_NOT_FOUND_LOG_SWEEP_STATE', 'not_found_log_sweep.json');

/**
 * True when the 2026.11.0 step (11.0.30) has created not_found_log.
 *
 * @return bool
 */
function pg_not_found_log_ready()
{
    return pg_schema_has('not_found_log', 'crawler_hits');
}

/**
 * The name a missing address is logged under, or '' when it is not logged:
 * the path in the stored form of a short link name (no / at either end, one
 * / between parts, _ for a space), and only when a short link could have it
 * (pg_short_link_name_fault()) without being a pattern, and when it is not
 * under the software directory (the panel and its files, never a page).
 *
 * @param mixed  $path               The path the router looked up (PG_SHORT_LINK_PATH)
 * @param string $software_directory SOFTWARE_DIRECTORY
 * @return string
 */
function pg_not_found_log_name($path, $software_directory = '')
{
    if (!is_string($path) || !mb_check_encoding($path, 'UTF-8')) {
        return '';
    }

    $name = pg_short_link_path_name($path);

    if (pg_short_link_is_pattern($name) || (pg_short_link_name_fault($name, PG_NOT_FOUND_LOG_PATH_MAX) !== '')) {
        return '';
    }

    $software_directory = trim((string) $software_directory, '/');

    if (($software_directory !== '') && (strcasecmp(explode('/', $name, 2)[0], $software_directory) === 0)) {
        return '';
    }

    return $name;
}

/**
 * The referring address as the log keeps it: scheme, host and path of an
 * http or https address, without its query string and fragment (they may
 * carry a search or a token), at most 512 characters; '' for anything else.
 *
 * @param mixed $referrer HTTP_REFERER
 * @return string
 */
function pg_not_found_log_referrer($referrer)
{
    if (!is_string($referrer) || ($referrer === '') || !mb_check_encoding($referrer, 'UTF-8')
        || (preg_match('/[\x00-\x20\x7f]/', $referrer) === 1)) {
        return '';
    }

    $parts = @parse_url($referrer);

    if (!is_array($parts) || empty($parts['host']) || !isset($parts['scheme'])
        || !in_array(strtolower((string) $parts['scheme']), array('http', 'https'), true)) {
        return '';
    }

    $kept = strtolower((string) $parts['scheme']) . '://' . strtolower((string) $parts['host'])
        . (isset($parts['port']) ? ':' . (int) $parts['port'] : '')
        . (isset($parts['path']) ? (string) $parts['path'] : '/');

    return mb_substr($kept, 0, 512, 'UTF-8');
}

/**
 * True when the browser only fetched the address ahead of a click it may
 * never make (link prefetch, speculation rules).
 *
 * @param array $server $_SERVER
 * @return bool
 */
function pg_not_found_log_is_prefetch($server)
{
    foreach (array('HTTP_SEC_PURPOSE', 'HTTP_PURPOSE', 'HTTP_X_PURPOSE', 'HTTP_X_MOZ') as $header) {
        if (isset($server[$header]) && is_string($server[$header]) && (stripos($server[$header], 'prefetch') !== false)) {
            return true;
        }
    }

    return false;
}

/**
 * Whether a request that answers 404 is logged, and as whom: 'visitor',
 * 'crawler' (a search engine crawler the firewall verified) or '' (not
 * logged). Checked in this order:
 *
 *   method     GET and HEAD only, as for the * patterns
 *   rendering  the SEO pass, the search index or an e-mail body renders a
 *              page inside another request: the status is not its own
 *   name       pg_not_found_log_name(); '' is not logged
 *   live_page  the request opened a page (pg_rendered_page()) and the name
 *              has no /: the 404 is about a record named in the query string
 *              (?r=, ?id=) of a page that is there, which no redirect fixes
 *   prefetch   pg_not_found_log_is_prefetch()
 *   waf_rule   the firewall logged this request (a signature, a rate, a bot)
 *   automated  pg_request_is_automated(); logged only as a verified crawler
 *
 * @param array $request method, rendering, name, live_page, prefetch, waf_rule, automated, verified_crawler
 * @return string
 */
function pg_not_found_log_decide($request)
{
    $request = (array) $request;
    $method = strtoupper((string) ($request['method'] ?? 'GET'));
    $name = (string) ($request['name'] ?? '');

    if ((($method !== 'GET') && ($method !== 'HEAD')) || !empty($request['rendering']) || ($name === '')) {
        return '';
    }

    if (!empty($request['live_page']) && (strpos($name, '/') === false)) {
        return '';
    }

    if (!empty($request['prefetch']) || ((string) ($request['waf_rule'] ?? '') !== '')) {
        return '';
    }

    if (!empty($request['automated'])) {
        return !empty($request['verified_crawler']) ? 'crawler' : '';
    }

    return 'visitor';
}

/**
 * The address this request is logged under, held until the end of the
 * request: array('name', 'referrer', 'class'), or null.
 *
 * @param array|null $set
 * @return array|null
 */
function pg_not_found_log_pending($set = null)
{
    static $pending = null;

    if ($set !== null) {
        $pending = $set;
    }

    return $pending;
}

/**
 * Called where a page answers 404, right after pg_short_link_not_found() had
 * its chance (output_error(), error(), pg_sw_item_missing()): decides whether
 * the request is logged and leaves the write for the end of the request,
 * when the answer is known to be a 404 (pg_not_found_log_write()). Once per
 * request: the error page drawn for the 404 may report a missing record of
 * its own. Runs no query but the schema probe, which the short link check
 * before it has usually cached.
 *
 * @return void
 */
function pg_not_found_log_mark()
{
    static $marked = false;

    if ($marked) {
        return;
    }

    $marked = true;

    if (!defined('PG_SHORT_LINK_PATH') || (PHP_SAPI === 'cli')) {
        return;
    }

    $context = function_exists('pg_sw_render_context') ? pg_sw_render_context() : array();
    $automated = function_exists('pg_request_is_automated') && pg_request_is_automated();
    $name = pg_not_found_log_name(PG_SHORT_LINK_PATH, defined('SOFTWARE_DIRECTORY') ? SOFTWARE_DIRECTORY : '');

    $class = pg_not_found_log_decide(array(
        'method'           => isset($_SERVER['REQUEST_METHOD']) ? (string) $_SERVER['REQUEST_METHOD'] : 'GET',
        'rendering'        => (function_exists('pg_seo_rendering') && pg_seo_rendering()) || defined('UPDATE_SEARCH_INDEX') || !empty($context['email']),
        'name'             => $name,
        'live_page'        => function_exists('pg_rendered_page') && (pg_rendered_page() > 0),
        'prefetch'         => pg_not_found_log_is_prefetch($_SERVER),
        'waf_rule'         => function_exists('waf_last_rule') ? waf_last_rule() : '',
        'automated'        => $automated,
        'verified_crawler' => $automated && function_exists('waf_bot_verified') && waf_bot_verified(),
    ));

    if (($class === '') || !isset(db::$con) || !db::$con || !pg_not_found_log_ready()) {
        return;
    }

    pg_not_found_log_pending(array(
        'name'     => $name,
        'referrer' => pg_not_found_log_referrer(isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : ''),
        'class'    => $class,
    ));

    register_shutdown_function('pg_not_found_log_write');
}

/**
 * The end-of-request write of the address pg_not_found_log_mark() held: only
 * when the answer went out as a 404 (a header already sent, or a redirect
 * after all, is not one). An address seen before is one UPDATE; a new one is
 * an INSERT, whose ON DUPLICATE KEY covers two first requests at once. Raw
 * queries only: a db() helper ends in output_error() on a failure, and an
 * exit() here would stop every shutdown function after this one.
 *
 * @return void
 */
function pg_not_found_log_write()
{
    $pending = pg_not_found_log_pending();

    if (!is_array($pending) || ((int) http_response_code() !== 404) || !isset(db::$con) || !db::$con) {
        return;
    }

    $name = (string) $pending['name'];
    $key = sha1($name);
    $crawler = ($pending['class'] === 'crawler') ? 1 : 0;
    $now = time();
    $referrer = (string) $pending['referrer'];
    $escape = function ($value) {
        return mysqli_real_escape_string(db::$con, (string) $value);
    };

    $updated = @mysqli_query(db::$con,
        "UPDATE not_found_log
        SET
            hit_count = hit_count + 1,
            crawler_hits = crawler_hits + " . $crawler . ",
            last_seen = '" . $now . "'" .
            (($referrer !== '') ? ", referrer = IF(referrer = '', '" . $escape($referrer) . "', referrer)" : "") . "
        WHERE path_key = '" . $key . "'");

    if ($updated === false) {
        pg_not_found_log_failure('counting an address');
        return;
    }

    // hit_count always changes, so no row changed means no row.
    if (mysqli_affected_rows(db::$con) > 0) {
        return;
    }

    $inserted = @mysqli_query(db::$con,
        "INSERT INTO not_found_log (path_key, path, referrer, hit_count, crawler_hits, first_seen, last_seen)
        VALUES ('" . $key . "', '" . $escape($name) . "', '" . $escape($referrer) . "', 1, " . $crawler . ", '" . $now . "', '" . $now . "')
        ON DUPLICATE KEY UPDATE
            hit_count = hit_count + 1,
            crawler_hits = crawler_hits + " . $crawler . ",
            last_seen = '" . $now . "'");

    if ($inserted === false) {
        pg_not_found_log_failure('adding an address');
        return;
    }

    if ((mysqli_affected_rows(db::$con) === 1) && (mt_rand(1, PG_NOT_FOUND_LOG_CAP_SAMPLE) === 1)) {
        pg_not_found_log_cap((int) mysqli_insert_id(db::$con));
    }
}

/**
 * Keeps the log under PG_NOT_FOUND_LOG_MAX_ROWS: the addresses not asked for
 * the longest go, a thousand at a time. Repeats update their row in place, so
 * ids count rows ever added, and a table whose newest id is under the cap
 * cannot be over it; only past that is the row at the cap read, along the
 * last_seen index. The cut is by last_seen, not by id as the firewall log's
 * is: the oldest rows here are the broken links asked for the longest, the
 * ones most worth a redirect.
 *
 * @param int $newest_id The id just added (0: read the largest)
 * @return int rows removed
 */
function pg_not_found_log_cap($newest_id = 0)
{
    if (!isset(db::$con) || !db::$con) {
        return 0;
    }

    if ((int) $newest_id <= 0) {
        $result = @mysqli_query(db::$con, "SELECT MAX(id) FROM not_found_log");
        $row = $result ? mysqli_fetch_row($result) : null;
        $newest_id = is_array($row) ? (int) $row[0] : 0;
    }

    if ((int) $newest_id <= PG_NOT_FOUND_LOG_MAX_ROWS) {
        return 0;
    }

    // The last row the cap keeps; rows asked for in the same second as it
    // stay with it.
    $result = @mysqli_query(db::$con,
        "SELECT last_seen FROM not_found_log
        ORDER BY last_seen DESC
        LIMIT 1 OFFSET " . ((int) PG_NOT_FOUND_LOG_MAX_ROWS - 1));

    if ($result === false) {
        pg_not_found_log_failure('reading the row cap');
        return 0;
    }

    $row = mysqli_fetch_row($result);

    if (!is_array($row)) {
        return 0;
    }

    if (@mysqli_query(db::$con, "DELETE FROM not_found_log WHERE last_seen < '" . (int) $row[0] . "' ORDER BY last_seen LIMIT 1000") === false) {
        pg_not_found_log_failure('keeping the row cap');
        return 0;
    }

    return max(0, (int) mysqli_affected_rows(db::$con));
}

/**
 * A failed query of the log goes to the PHP error log; the request goes on.
 *
 * @param string $step
 * @return void
 */
function pg_not_found_log_failure($step)
{
    if (!function_exists('pg_error_write') || !function_exists('pg_error_entry')) {
        return;
    }

    pg_error_write(pg_error_entry(
        'warning',
        'E_USER_WARNING',
        '404 log: ' . $step . ' failed: ' . mysqli_error(db::$con),
        __FILE__,
        __LINE__,
        array()));
}

/**
 * The oldest last_seen the log shows: what is older is past the retention
 * period, whether or not the general job has removed it yet.
 *
 * @param int $now
 * @return int
 */
function pg_not_found_log_since($now = 0)
{
    return (((int) $now > 0) ? (int) $now : time()) - (PG_NOT_FOUND_LOG_DAYS * 86400);
}

/**
 * Where the daily sweep keeps when it last finished.
 *
 * @return string
 */
function pg_not_found_log_sweep_file()
{
    return PG_FUNCTIONS_DIR . '/data/temp/' . PG_NOT_FOUND_LOG_SWEEP_STATE;
}

/**
 * Whether the daily sweep is owed: it has not run in the last day.
 *
 * @return bool
 */
function pg_not_found_log_sweep_due()
{
    if (!pg_not_found_log_ready()) {
        return false;
    }

    $state = @json_decode((string) @file_get_contents(pg_not_found_log_sweep_file()), true);
    $done = (is_array($state) && isset($state['done_at'])) ? (int) $state['done_at'] : 0;

    return $done < (time() - 86400);
}

/**
 * The daily pass (job.php): the addresses not asked for in
 * PG_NOT_FOUND_LOG_DAYS days go, five thousand at a time within the time
 * budget, then the row cap. Writes the state file when through.
 *
 * @param int $seconds
 * @return int rows removed
 */
function pg_not_found_log_sweep($seconds = 5)
{
    if (!pg_not_found_log_ready()) {
        return 0;
    }

    $removed = 0;
    $deadline = microtime(true) + max(1, (int) $seconds);
    $through = false;

    while (microtime(true) < $deadline) {
        if (@mysqli_query(db::$con, "DELETE FROM not_found_log WHERE last_seen < '" . pg_not_found_log_since() . "' LIMIT 5000") === false) {
            pg_not_found_log_failure('removing old addresses');
            return $removed;
        }

        $affected = (int) mysqli_affected_rows(db::$con);
        $removed += max(0, $affected);

        if ($affected < 5000) {
            $through = true;
            break;
        }
    }

    if (!$through) {
        return $removed;
    }

    $removed += pg_not_found_log_cap();

    $file = pg_not_found_log_sweep_file();

    if (!is_dir(dirname($file))) {
        @mkdir(dirname($file), 0755, true);
    }

    @file_put_contents($file, json_encode(array('done_at' => time())), LOCK_EX);

    return $removed;
}

/**
 * The WHERE of a list of the log: the retention period always, then who
 * asked ('visitors': at least one request that was not a verified crawler,
 * 'crawlers': at least one that was) and the part of the address searched
 * for (at most 191 characters, the column's width).
 *
 * @param array $filters since (int), who, search
 * @return string
 */
function pg_not_found_log_where($filters)
{
    $filters = (array) $filters;
    $where = array("last_seen >= '" . (int) ($filters['since'] ?? 0) . "'");

    switch ((string) ($filters['who'] ?? '')) {
        case 'visitors':
            $where[] = "hit_count > crawler_hits";
            break;

        case 'crawlers':
            $where[] = "crawler_hits > 0";
            break;
    }

    $search = is_string($filters['search'] ?? null) ? mb_substr(trim($filters['search']), 0, PG_NOT_FOUND_LOG_PATH_MAX, 'UTF-8') : '';

    if ($search !== '') {
        $where[] = "path LIKE '" . e('%' . escape_like($search) . '%') . "'";
    }

    return implode(' AND ', $where);
}

/**
 * What answers each logged name now, by the router's comparison
 * (pg_short_link_keys()): name => array('state' => 'redirect' (a short link
 * has it, so the router answers before any 404), 'binned' (a short link in
 * the Recycle Bin holds the name, so no new one can take it), 'page' or
 * 'file' (one has the name now), 'id' => the short link's id or 0). A name
 * nothing answers is left out.
 *
 * @param string[] $names
 * @return array
 */
function pg_not_found_log_answers($names)
{
    $names = array_values(array_unique(array_filter(array_map('strval', (array) $names), 'strlen')));
    $out = array();

    if (!$names) {
        return $out;
    }

    $keys = array();

    foreach ($names as $name) {
        $keys[$name] = pg_short_link_key($name);
    }

    $by_key = array_flip($keys);
    $recycled = pg_schema_has('short_links', 'recycled');

    foreach (array_chunk($names, 200) as $chunk) {
        $in = "'" . implode("', '", array_map('e', $chunk)) . "'";
        $found = array(
            'short_link' => db_items("SELECT id, name" . ($recycled ? ", recycled" : "") . " FROM short_links WHERE name IN ($in) AND destination_type <> 'workspace_guest'"),
            'page'       => db_items("SELECT page_name AS name FROM page WHERE page_name IN ($in)"),
            'file'       => db_items("SELECT name FROM files WHERE name IN ($in)"),
        );

        foreach ($found as $type => $rows) {
            pg_short_link_keys(array_column($rows, 'name'));

            foreach ($rows as $row) {
                $key = pg_short_link_key((string) $row['name']);

                if (!isset($by_key[$key])) {
                    continue;
                }

                $logged = $by_key[$key];

                if ($type === 'short_link') {
                    $state = !empty($row['recycled']) ? 'binned' : 'redirect';

                    // A live link wins over one in the bin of the same name.
                    if (isset($out[$logged]) && ($out[$logged]['state'] === 'redirect')) {
                        continue;
                    }

                    $out[$logged] = array('state' => $state, 'id' => (int) $row['id']);
                } elseif (!isset($out[$logged])) {
                    $out[$logged] = array('state' => $type, 'id' => 0);
                }
            }
        }
    }

    return $out;
}

/**
 * The short link screen opened to make a logged address a redirect: the name
 * filled in and the answer set to 301; the operator picks where it leads and
 * the screen's own checks apply on save.
 *
 * @param string $name
 * @return string a relative address, not escaped for HTML
 */
function pg_not_found_log_redirect_url($name)
{
    return 'add_short_link.php?' . http_build_query(array('name' => (string) $name, 'redirect_code' => 301), '', '&', PHP_QUERY_RFC3986);
}
