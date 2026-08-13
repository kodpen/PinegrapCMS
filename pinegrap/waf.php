<?php
/**
 * PineGrap - Web Application Firewall
 *
 * Self-contained request firewall. Deliberately has NO dependency on
 * functions.php: it is included from two different bootstraps that do not
 * share the same set of loaded libraries.
 *
 *   router.php  -> covers get_file.php / robots.txt / sitemap.xml, which never
 *                  reach init.php.
 *   init.php    -> covers every directly requested software script
 *                  (apps.php, api.php, custom_form.php, cart_action.php ...).
 *
 * Both call waf_run(). The WAF_RAN guard makes the second call a no-op.
 *
 * DESIGN RULE - FAIL OPEN.
 * Every entry point is wrapped so that an error inside the firewall can never
 * take the site down. If the schema is missing, a regex errors, or the rate
 * table is unavailable, the request is allowed through. A firewall that breaks
 * a live storefront is worse than no firewall.
 *
 * @author      Erdal Güral (Kodpen)
 * @copyright   2016-2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// Guard: this file is included from two bootstraps.
if (defined('WAF_LOADED')) {
    return;
}
define('WAF_LOADED', true);

// Anomaly score at which a request is considered an attack, per sensitivity.
// Scoring (rather than "first rule wins") is what keeps the false positive
// rate survivable: a single weak signal never blocks on its own.
define('WAF_THRESHOLD_LOW', 15);
define('WAF_THRESHOLD_MEDIUM', 10);
define('WAF_THRESHOLD_HIGH', 6);

// Hard caps so a malicious payload cannot make the scanner itself the DoS.
define('WAF_MAX_VALUE_BYTES', 8192);
define('WAF_MAX_VALUES', 250);
define('WAF_MIN_VALUE_BYTES', 3);

// Firewall log aggregation bucket, in seconds. Identical events inside one
// bucket become a single row with a hit counter rather than N rows. Five
// minutes keeps enough resolution to read an attack's shape while collapsing
// a flood by three or four orders of magnitude.
define('WAF_LOG_WINDOW', 300);


/* ─────────────────────────────────────────────────────────────────────────
   CONFIGURATION
   ───────────────────────────────────────────────────────────────────────── */

/**
 * Hand the WAF the config row a caller has already fetched.
 *
 * init.php reads the whole config table anyway; without this it would be
 * read a second time on every request just to answer "is the firewall on".
 */
function waf_prime_config($row)
{
    if (is_array($row)) {
        waf_config($row);
    }
}

/**
 * Read WAF settings from the config table.
 *
 * Returns false when the schema has not been upgraded yet. Every caller
 * treats false as "firewall disabled", so a legacy install that never ran
 * the upgrade behaves exactly as it did before this file existed.
 */
function waf_config($preloaded = null)
{
    static $config = null;

    if ($preloaded !== null && $config === null) {
        $config = $preloaded;
        return $config;
    }

    if ($config !== null) {
        return $config;
    }

    $config = false;

    if (!isset(db::$con) || !db::$con) {
        return $config;
    }

    // SELECT * rather than naming the columns: on an install that has not
    // run the upgrade yet, naming waf_enabled would be a fatal query error,
    // and this code runs on every single request. Presence is checked on the
    // returned row instead, which costs one query rather than a probe plus
    // a select.
    $result = @mysqli_query(db::$con, "SELECT * FROM config LIMIT 1");

    if (!$result) {
        return $config;
    }

    $row = @mysqli_fetch_assoc($result);

    if (!$row) {
        return $config;
    }

    $config = $row;

    return $config;
}

/**
 * Has the 2026.2.4 upgrade run?
 *
 * Kept separate from waf_config() because the config row is still needed on a
 * pre-upgrade install: block_unknown_bots lives there and predates this file.
 * Folding the two together is what would silently switch off an operator's
 * existing bot filter the moment they took this update without upgrading the
 * database.
 */
function waf_schema_ready()
{
    $config = waf_config();

    return (is_array($config) && isset($config['waf_enabled']));
}

/**
 * Effective mode: 'off', 'monitor' or 'block'.
 *
 * 'monitor' is the shipped default. It records exactly what 'block' would
 * have stopped without stopping anything, so an operator can read the log,
 * confirm there are no false positives on their own traffic, and only then
 * switch to 'block'.
 */
function waf_mode()
{
    // The constant wins when it exists: init.php defines it from the config
    // row, so every caller in the request gets the same answer without a
    // second read and without any chance of drift.
    if (defined('WAF_ENABLED') && !WAF_ENABLED) {
        return 'off';
    }

    if (!waf_schema_ready()) {
        return 'off';
    }

    $config = waf_config();

    if (empty($config['waf_enabled'])) {
        return 'off';
    }

    return ($config['waf_mode'] === 'block') ? 'block' : 'monitor';
}

function waf_setting($key, $default = '')
{
    $config = waf_config();

    if (!$config || !isset($config[$key])) {
        return $default;
    }

    return $config[$key];
}

function waf_threshold()
{
    $sensitivity = waf_setting('waf_sensitivity', 'medium');

    if ($sensitivity === 'low') {
        return WAF_THRESHOLD_LOW;
    }

    if ($sensitivity === 'high') {
        return WAF_THRESHOLD_HIGH;
    }

    return WAF_THRESHOLD_MEDIUM;
}


/* ─────────────────────────────────────────────────────────────────────────
   CLIENT IP RESOLUTION
   ───────────────────────────────────────────────────────────────────────── */

/**
 * Cloudflare edge ranges, used to decide whether CF-Connecting-IP can be
 * trusted. Source: https://www.cloudflare.com/ips/ (updated infrequently).
 *
 * These are only ever used to VALIDATE the peer address. An attacker who
 * sends a forged CF-Connecting-IP header from some other network is ignored,
 * because their REMOTE_ADDR is not in this list.
 */
function waf_cloudflare_ranges()
{
    return array(
        '173.245.48.0/20', '103.21.244.0/22', '103.22.200.0/22',
        '103.31.4.0/22', '141.101.64.0/18', '108.162.192.0/18',
        '190.93.240.0/20', '188.114.96.0/20', '197.234.240.0/22',
        '198.41.128.0/17', '162.158.0.0/15', '104.16.0.0/13',
        '104.24.0.0/14', '172.64.0.0/13', '131.0.72.0/22',
        '2400:cb00::/32', '2606:4700::/32', '2803:f800::/32',
        '2405:b500::/32', '2405:8100::/32', '2a06:98c0::/29',
        '2c0f:f248::/32',
    );
}

/**
 * Resolve the real visitor IP.
 *
 * Behind a reverse proxy or CDN, REMOTE_ADDR is the proxy, not the visitor.
 * That single fact breaks IP banning (you ban the CDN, i.e. everyone or
 * no one), visitor statistics and order fraud records alike.
 *
 * Proxy headers are only honoured when the peer is a KNOWN proxy, because
 * these headers are trivially forged by the client. Trusting them blindly
 * would let any attacker walk straight past an IP ban.
 */
function waf_client_ip()
{
    static $ip = null;

    if ($ip !== null) {
        return $ip;
    }

    $remote = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
    $ip = $remote;

    if ($remote === '') {
        return $ip;
    }

    // Cloudflare (and other CDNs that copy its header) - only when the peer
    // really is a Cloudflare edge address.
    if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])
        && waf_ip_in_any($remote, waf_cloudflare_ranges())
    ) {
        $candidate = trim($_SERVER['HTTP_CF_CONNECTING_IP']);

        if (waf_is_ip($candidate)) {
            $ip = $candidate;
            return $ip;
        }
    }

    // Operator-declared trusted proxies (load balancer, Varnish, nginx,
    // Sucuri, a hosting edge...). Empty by default: no header is trusted
    // unless the operator explicitly says which peer may set it.
    $trusted = waf_parse_list(waf_setting('waf_trusted_proxies', ''));

    if (!$trusted || !waf_ip_in_any($remote, $trusted)) {
        return $ip;
    }

    // Vendor-specific single-value headers first: they are unambiguous.
    //
    // CF-Connecting-IP leads the list because of Cloudflare Tunnel. With a
    // tunnel there is no inbound connection from a Cloudflare edge address at
    // all — cloudflared runs beside the origin and connects outward, so the
    // peer is 127.0.0.1 and the edge-range check above never fires. The header
    // is still present and still authoritative; it just has to be authorised
    // by the operator listing the tunnel's own address as a trusted proxy.
    //
    // It is also preferred over X-Forwarded-For because cloudflared is known
    // to pass through a bogus XFF when the visitor supplies one of their own
    // (cloudflared issue #1426).
    $single_headers = array(
        'HTTP_CF_CONNECTING_IP',
        'HTTP_X_SUCURI_CLIENTIP',
        'HTTP_TRUE_CLIENT_IP',
        'HTTP_FASTLY_CLIENT_IP',
        'HTTP_INCAP_CLIENT_IP',
        'HTTP_X_REAL_IP',
    );

    foreach ($single_headers as $header) {
        if (!empty($_SERVER[$header])) {
            $candidate = trim($_SERVER[$header]);

            if (waf_is_ip($candidate)) {
                $ip = $candidate;
                return $ip;
            }
        }
    }

    // X-Forwarded-For is a chain: client, proxy1, proxy2. Walk it from the
    // right and stop at the first address that is NOT one of our own
    // trusted proxies. Anything further left was supplied by the client and
    // cannot be believed.
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $chain = array_map('trim', explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']));

        for ($i = count($chain) - 1; $i >= 0; $i--) {
            $candidate = $chain[$i];

            if (!waf_is_ip($candidate)) {
                continue;
            }

            if (waf_ip_in_any($candidate, $trusted)) {
                continue;
            }

            $ip = $candidate;
            return $ip;
        }
    }

    return $ip;
}

function waf_is_ip($value)
{
    return (bool) filter_var($value, FILTER_VALIDATE_IP);
}

/**
 * Is this address infrastructure rather than a visitor?
 *
 * True for loopback, and for anything the operator declared as a trusted
 * proxy. Both mean the same thing: IP resolution did not produce a visitor.
 *
 * This guard exists because the failure it prevents is total. Put the site
 * behind a Cloudflare Tunnel without listing the tunnel under Trusted
 * Proxies, and every visitor resolves to 127.0.0.1 — one address. The global
 * rate limit is then reached within seconds, the automatic ban fires, and
 * 127.0.0.1 goes on the block list. That bans the entire internet.
 *
 * So when the resolved address is infrastructure, per-address enforcement is
 * skipped entirely. Signature scanning still runs: it judges the request, not
 * the sender.
 */
function waf_ip_is_infrastructure($ip)
{
    if (!waf_is_ip($ip)) {
        return true;
    }

    if (waf_ip_in_any($ip, array('127.0.0.0/8', '::1/128'))) {
        return true;
    }

    $trusted = waf_parse_list(waf_setting('waf_trusted_proxies', ''));

    return ($trusted && waf_ip_in_any($ip, $trusted));
}


/* ─────────────────────────────────────────────────────────────────────────
   EXTERNAL WAF / CDN DETECTION
   ───────────────────────────────────────────────────────────────────────── */

/**
 * Detect whether the request already passed through a third-party WAF or CDN.
 *
 * Shown next to the setting so an operator understands their actual posture:
 * with Cloudflare in front, edge rules run first and this firewall is the
 * second layer (it still sees anything Cloudflare lets through, including
 * traffic that reaches the origin IP directly, which is the usual bypass).
 *
 * Returns array('key' => ..., 'name' => ...) or false.
 */
function waf_detect_external()
{
    static $detected = null;

    if ($detected !== null) {
        return $detected;
    }

    $detected = false;

    // Ordered: the most specific fingerprint wins.
    $providers = array(
        array('key' => 'cloudflare', 'name' => 'Cloudflare',
              'headers' => array('HTTP_CF_RAY', 'HTTP_CF_CONNECTING_IP', 'HTTP_CF_IPCOUNTRY')),
        array('key' => 'sucuri', 'name' => 'Sucuri',
              'headers' => array('HTTP_X_SUCURI_ID', 'HTTP_X_SUCURI_CLIENTIP')),
        array('key' => 'incapsula', 'name' => 'Imperva (Incapsula)',
              'headers' => array('HTTP_INCAP_CLIENT_IP', 'HTTP_X_IINFO', 'HTTP_X_CDN')),
        array('key' => 'akamai', 'name' => 'Akamai',
              'headers' => array('HTTP_AKAMAI_ORIGIN_HOP', 'HTTP_X_AKAMAI_CONFIG_LOG_DETAIL')),
        array('key' => 'cloudfront', 'name' => 'AWS CloudFront',
              'headers' => array('HTTP_X_AMZ_CF_ID', 'HTTP_CLOUDFRONT_VIEWER_COUNTRY')),
        array('key' => 'fastly', 'name' => 'Fastly',
              'headers' => array('HTTP_FASTLY_CLIENT_IP', 'HTTP_X_SERVED_BY')),
        array('key' => 'stackpath', 'name' => 'StackPath',
              'headers' => array('HTTP_X_SP_EDGE', 'HTTP_X_HW')),
        array('key' => 'azure', 'name' => 'Azure Front Door',
              'headers' => array('HTTP_X_AZURE_REF', 'HTTP_X_AZURE_FDID')),
    );

    foreach ($providers as $provider) {
        foreach ($provider['headers'] as $header) {
            if (!empty($_SERVER[$header])) {
                $detected = array('key' => $provider['key'], 'name' => $provider['name']);
                return $detected;
            }
        }
    }

    return $detected;
}

/**
 * Remember the detected provider so the settings screen can report it even
 * when the admin panel is reached over a path that bypasses the CDN.
 *
 * Writes only when the value actually changes, or once a day, so this costs
 * nothing on the request path.
 */
function waf_record_external($external)
{
    $config = waf_config();

    if (!$config) {
        return;
    }

    $key = $external ? $external['key'] : '';
    $stored = isset($config['waf_external_provider']) ? $config['waf_external_provider'] : '';
    $seen = isset($config['waf_external_seen']) ? (int) $config['waf_external_seen'] : 0;

    // Nothing detected and nothing stored: no work to do.
    if ($key === '' && $stored === '') {
        return;
    }

    if ($key === $stored && $seen > (time() - 86400)) {
        return;
    }

    // Only refresh the timestamp when a provider is actually present. A single
    // direct-to-origin request must not erase a real Cloudflare detection.
    if ($key === '') {
        return;
    }

    @mysqli_query(
        db::$con,
        "UPDATE config SET
            waf_external_provider = '" . waf_escape($key) . "',
            waf_external_seen = " . time()
    );
}


/* ─────────────────────────────────────────────────────────────────────────
   IP MATCHING - exact, wildcard, CIDR, IPv4 and IPv6
   ───────────────────────────────────────────────────────────────────────── */

/**
 * Split a textarea / comma list into trimmed, non-empty entries.
 * Accepts newline, comma or semicolon separators so operators can paste
 * lists from anywhere without them silently failing.
 */
function waf_parse_list($raw)
{
    if (!is_string($raw) || $raw === '') {
        return array();
    }

    $parts = preg_split('/[\r\n,;]+/', $raw);
    $out = array();

    foreach ($parts as $part) {
        $part = trim($part);

        if ($part !== '') {
            $out[] = $part;
        }
    }

    return $out;
}

/**
 * Normalise a stored list into the comma-separated form the tag input wants.
 *
 * These settings used to be newline-separated textareas. waf_parse_list()
 * accepts either, so the stored value of an existing install is read back
 * correctly and simply re-saved in the new shape the first time the operator
 * touches the screen.
 */
function waf_list_to_string($raw)
{
    return implode(',', waf_parse_list($raw));
}

function waf_ip_in_any($ip, $ranges)
{
    foreach ($ranges as $range) {
        if (waf_ip_matches($ip, $range)) {
            return true;
        }
    }

    return false;
}

/**
 * Match an IP against a pattern.
 *
 * Supported: 1.2.3.4  |  1.2.3.*  |  1.2.3.0/24  |  2001:db8::1  |  2001:db8::/32
 *
 * The legacy banned_ip_addresses format was IPv4-with-asterisks only, so the
 * wildcard branch is kept verbatim in behaviour for existing rows.
 */
function waf_ip_matches($ip, $pattern)
{
    $ip = trim($ip);
    $pattern = trim($pattern);

    if ($ip === '' || $pattern === '') {
        return false;
    }

    if ($ip === $pattern) {
        return true;
    }

    // CIDR
    if (strpos($pattern, '/') !== false) {
        return waf_ip_in_cidr($ip, $pattern);
    }

    // Legacy IPv4 wildcard (1.2.3.* / *.*.*.*)
    if (strpos($pattern, '*') !== false) {
        if (strpos($ip, ':') !== false) {
            // An all-wildcard pattern is understood as "everything", which
            // has to include IPv6 or a blanket ban would quietly leak.
            return (bool) preg_match('/^\*(\.\*){0,3}$/', $pattern);
        }

        $ip_parts = explode('.', $ip);
        $pattern_parts = explode('.', $pattern);

        if (count($ip_parts) !== 4 || count($pattern_parts) !== 4) {
            return false;
        }

        for ($i = 0; $i < 4; $i++) {
            if ($pattern_parts[$i] === '*') {
                continue;
            }

            if ($pattern_parts[$i] !== $ip_parts[$i]) {
                return false;
            }
        }

        return true;
    }

    return false;
}

function waf_ip_in_cidr($ip, $cidr)
{
    $parts = explode('/', $cidr, 2);
    $subnet = trim($parts[0]);
    $bits = isset($parts[1]) ? (int) $parts[1] : -1;

    $ip_bin = @inet_pton($ip);
    $subnet_bin = @inet_pton($subnet);

    if ($ip_bin === false || $subnet_bin === false) {
        return false;
    }

    // Never compare an IPv4 address against an IPv6 subnet or vice versa:
    // inet_pton returns 4 bytes vs 16 and the prefix maths would be nonsense.
    if (strlen($ip_bin) !== strlen($subnet_bin)) {
        return false;
    }

    $max_bits = strlen($ip_bin) * 8;

    if ($bits < 0 || $bits > $max_bits) {
        $bits = $max_bits;
    }

    $whole_bytes = (int) ($bits / 8);
    $remaining_bits = $bits % 8;

    if ($whole_bytes > 0
        && strncmp($ip_bin, $subnet_bin, $whole_bytes) !== 0
    ) {
        return false;
    }

    if ($remaining_bits === 0) {
        return true;
    }

    $mask = chr(0xFF << (8 - $remaining_bits) & 0xFF);

    return ($ip_bin[$whole_bytes] & $mask) === ($subnet_bin[$whole_bytes] & $mask);
}

/**
 * Validate a pattern an operator typed into the allow / block list.
 * Used by settings.php so bad entries are rejected with a notice instead of
 * being silently stored and never matching anything.
 */
function waf_valid_ip_pattern($pattern)
{
    $pattern = trim($pattern);

    if ($pattern === '') {
        return false;
    }

    if (waf_is_ip($pattern)) {
        return true;
    }

    if (strpos($pattern, '/') !== false) {
        $parts = explode('/', $pattern, 2);

        if (!waf_is_ip(trim($parts[0]))) {
            return false;
        }

        $bits = trim($parts[1]);

        // preg_match rather than ctype_digit: nothing else in the codebase
        // relies on the ctype extension, and this file must not be the one
        // thing that breaks on a stripped-down PHP build.
        if ($bits === '' || !preg_match('/^\d+$/', $bits)) {
            return false;
        }

        $max = (strpos($parts[0], ':') !== false) ? 128 : 32;

        return ((int) $bits >= 0 && (int) $bits <= $max);
    }

    // IPv4 wildcard form.
    return (bool) preg_match('/^(?:\d{1,3}|\*)(?:\.(?:\d{1,3}|\*)){3}$/', $pattern);
}


/* ─────────────────────────────────────────────────────────────────────────
   IP ALLOW / BLOCK LISTS
   ───────────────────────────────────────────────────────────────────────── */

/**
 * Load the IP lists from banned_ip_addresses.
 *
 * The table predates this firewall and originally held only permanent IPv4
 * block entries. The extra columns are probed rather than assumed so an
 * install that has not run the upgrade still gets its old blocks enforced.
 */
function waf_ip_lists()
{
    static $lists = null;

    if ($lists !== null) {
        return $lists;
    }

    $lists = array('allow' => array(), 'block' => array());

    if (!isset(db::$con) || !db::$con) {
        return $lists;
    }

    $has_columns = waf_table_has_column('banned_ip_addresses', 'list_type');

    if ($has_columns) {
        $query = "SELECT ip_address, list_type
                  FROM banned_ip_addresses
                  WHERE (expires_at = 0 OR expires_at > " . time() . ")";
    } else {
        $query = "SELECT ip_address, 'block' AS list_type FROM banned_ip_addresses";
    }

    $result = @mysqli_query(db::$con, $query);

    if (!$result) {
        return $lists;
    }

    while ($row = @mysqli_fetch_assoc($result)) {
        $type = ($row['list_type'] === 'allow') ? 'allow' : 'block';
        $lists[$type][] = $row['ip_address'];
    }

    return $lists;
}

/**
 * Cheap cached "does this table have this column" probe.
 * Used everywhere instead of assuming the upgrade has run.
 */
function waf_table_has_column($table, $column)
{
    static $cache = array();

    $key = $table . '.' . $column;

    if (isset($cache[$key])) {
        return $cache[$key];
    }

    $cache[$key] = false;

    if (!isset(db::$con) || !db::$con) {
        return false;
    }

    $result = @mysqli_query(
        db::$con,
        "SHOW COLUMNS FROM `" . str_replace('`', '', $table) . "`
         LIKE '" . waf_escape($column) . "'"
    );

    if ($result && @mysqli_num_rows($result)) {
        $cache[$key] = true;
    }

    return $cache[$key];
}

function waf_ip_is_allowed($ip)
{
    $lists = waf_ip_lists();

    return waf_ip_in_any($ip, $lists['allow']);
}

function waf_ip_is_blocked($ip)
{
    $lists = waf_ip_lists();

    return waf_ip_in_any($ip, $lists['block']);
}

/**
 * Add a temporary automatic ban.
 *
 * Auto bans always expire. A permanent ban placed by an automated rule is
 * how a firewall ends up locking out a customer's whole office for good with
 * nobody knowing why.
 */
function waf_auto_ban($ip, $minutes, $note)
{
    if (!waf_table_has_column('banned_ip_addresses', 'list_type')) {
        return;
    }

    if (!waf_is_ip($ip)) {
        return;
    }

    $expires = time() + ($minutes * 60);

    // Single atomic statement against the (ip_address, list_type, source)
    // unique key added in 2026.2.7.
    //
    // The previous SELECT-then-INSERT was wrong twice over. It raced — two
    // simultaneous requests from the same attacker could both see "no
    // existing row" and both insert — and it silently failed outright while
    // ip_address was too narrow to hold an IPv6 address, because the lookup
    // used the full address and the stored value was a truncated stub. That
    // produced a fresh ban row on every single offence.
    //
    // Insert-or-update through the key cannot do either. The note is not
    // rewritten on repeats: the first reason is the useful one.
    @mysqli_query(
        db::$con,
        "INSERT INTO banned_ip_addresses
            (ip_address, list_type, source, note, expires_at, created_at, hit_count)
         VALUES
            ('" . waf_escape($ip) . "', 'block', 'auto',
             '" . waf_escape(mb_substr($note, 0, 250)) . "',
             " . $expires . ", " . time() . ", 1)
         ON DUPLICATE KEY UPDATE
            expires_at = " . $expires . ",
            hit_count = hit_count + 1"
    );
}


/* ─────────────────────────────────────────────────────────────────────────
   BOT CLASSIFICATION
   ───────────────────────────────────────────────────────────────────────── */

/**
 * Search engines, social preview fetchers and uptime monitors.
 *
 * The old filter matched the bare substring "bot", which blocked Twitterbot,
 * LinkedInBot, Applebot and AdsBot-Google (killing link previews and Google
 * Ads landing page checks) while also blocking real customers on CUBOT
 * phones, whose Android user agent contains the letters c-u-b-o-t.
 * Matching is now against explicit product tokens instead.
 *
 * 'verify' names the reverse DNS suffixes that prove the claim. An empty
 * verify list means "cannot be verified by rDNS", which is fine - it just
 * means the entry is trusted on its user agent alone, exactly as before.
 */
function waf_good_bots()
{
    return array(
        // Search engines - all rDNS verifiable.
        'googlebot'            => array('name' => 'Googlebot',        'verify' => array('.googlebot.com', '.google.com')),
        'adsbot-google'        => array('name' => 'AdsBot-Google',    'verify' => array('.googlebot.com', '.google.com')),
        'mediapartners-google' => array('name' => 'Mediapartners',    'verify' => array('.googlebot.com', '.google.com')),
        'googleother'          => array('name' => 'GoogleOther',      'verify' => array('.googlebot.com', '.google.com')),
        'google-inspectiontool'=> array('name' => 'Google Inspection','verify' => array('.googlebot.com', '.google.com')),
        'storebot-google'      => array('name' => 'StoreBot-Google',  'verify' => array('.googlebot.com', '.google.com')),
        'bingbot'              => array('name' => 'Bingbot',          'verify' => array('.search.msn.com')),
        'adidxbot'             => array('name' => 'AdIdxBot',         'verify' => array('.search.msn.com')),
        'msnbot'               => array('name' => 'MSNBot',           'verify' => array('.search.msn.com')),
        'bingpreview'          => array('name' => 'BingPreview',      'verify' => array('.search.msn.com')),
        'yandexbot'            => array('name' => 'YandexBot',        'verify' => array('.yandex.ru', '.yandex.net', '.yandex.com')),
        'yandeximages'         => array('name' => 'YandexImages',     'verify' => array('.yandex.ru', '.yandex.net', '.yandex.com')),
        'baiduspider'          => array('name' => 'Baiduspider',      'verify' => array('.baidu.com', '.baidu.jp')),
        'duckduckbot'          => array('name' => 'DuckDuckBot',      'verify' => array()),
        'applebot'             => array('name' => 'Applebot',         'verify' => array('.applebot.apple.com')),
        'slurp'                => array('name' => 'Yahoo! Slurp',     'verify' => array('.crawl.yahoo.net')),
        'sogou'                => array('name' => 'Sogou',            'verify' => array()),
        'exabot'               => array('name' => 'Exabot',           'verify' => array()),
        'seznambot'            => array('name' => 'SeznamBot',        'verify' => array('.seznam.cz')),
        'naver'                => array('name' => 'Naver',            'verify' => array()),
        'qwantbot'             => array('name' => 'Qwant',            'verify' => array()),

        // Social preview fetchers. Blocking these silently breaks every link
        // shared to that network - the page renders with no title or image.
        'facebookexternalhit'  => array('name' => 'Facebook',   'verify' => array()),
        'facebookcatalog'      => array('name' => 'Facebook Catalog', 'verify' => array()),
        'facebot'              => array('name' => 'Facebook',   'verify' => array()),
        'twitterbot'           => array('name' => 'Twitterbot', 'verify' => array()),
        'linkedinbot'          => array('name' => 'LinkedInBot','verify' => array()),
        'whatsapp'             => array('name' => 'WhatsApp',   'verify' => array()),
        'telegrambot'          => array('name' => 'TelegramBot','verify' => array()),
        'slackbot'             => array('name' => 'Slackbot',   'verify' => array()),
        'discordbot'           => array('name' => 'Discordbot', 'verify' => array()),
        'pinterest'            => array('name' => 'Pinterest',  'verify' => array()),
        'redditbot'            => array('name' => 'Redditbot',  'verify' => array()),
        'embedly'              => array('name' => 'Embedly',    'verify' => array()),

        // Feed readers. Each request here stands for a real person who
        // subscribed; blocking them silently empties the feed for readers who
        // asked for it, and nobody reports a feed that simply stopped.
        'feedly'               => array('name' => 'Feedly',     'verify' => array()),
        'inoreader'            => array('name' => 'Inoreader',  'verify' => array()),
        'feedbin'              => array('name' => 'Feedbin',    'verify' => array()),
        'newsblur'             => array('name' => 'NewsBlur',   'verify' => array()),
        'theoldreader'         => array('name' => 'The Old Reader', 'verify' => array()),
        'feedfetcher'          => array('name' => 'FeedFetcher','verify' => array()),
        'netvibes'             => array('name' => 'Netvibes',   'verify' => array()),
        'tiny tiny rss'        => array('name' => 'Tiny Tiny RSS', 'verify' => array()),
        'miniflux'             => array('name' => 'Miniflux',   'verify' => array()),
        'skypeuripreview'      => array('name' => 'Skype',      'verify' => array()),
        'vkshare'              => array('name' => 'VK',         'verify' => array()),

        // Uptime and performance monitoring. Blocking these makes the site
        // look permanently down on the operator's own dashboard.
        'uptimerobot'          => array('name' => 'UptimeRobot',  'verify' => array()),
        'pingdom'              => array('name' => 'Pingdom',      'verify' => array()),
        'statuscake'           => array('name' => 'StatusCake',   'verify' => array()),
        'site24x7'             => array('name' => 'Site24x7',     'verify' => array()),
        'betteruptime'         => array('name' => 'Better Uptime','verify' => array()),
        'newrelicpinger'       => array('name' => 'New Relic',    'verify' => array()),
        'gtmetrix'             => array('name' => 'GTmetrix',     'verify' => array()),
        'chrome-lighthouse'    => array('name' => 'Lighthouse',   'verify' => array()),
        'google page speed'    => array('name' => 'PageSpeed',    'verify' => array()),

        // Payment gateways and platform callbacks. These must never be
        // blocked: a 403 on a webhook loses an order.
        'iyzico'               => array('name' => 'Iyzico',   'verify' => array()),
        'stripe'               => array('name' => 'Stripe',   'verify' => array()),
        'paypal'               => array('name' => 'PayPal',   'verify' => array()),
        'garanti'              => array('name' => 'Garanti',  'verify' => array()),
        'letsencrypt'          => array('name' => "Let's Encrypt", 'verify' => array()),
    );
}

/**
 * Aggressive commercial crawlers and AI scrapers. Not attackers - they are
 * simply expensive, and most storefront operators want them gone.
 */
function waf_bad_bots()
{
    return array(
        'ahrefsbot', 'semrushbot', 'mj12bot', 'dotbot', 'blexbot',
        'petalbot', 'bytespider', 'dataforseobot', 'serpstatbot',
        'zoominfobot', 'barkrowler', 'seekport', 'megaindex',
        'linkdexbot', 'spbot', 'sistrix', 'seokicks', 'magpie-crawler',
        'domaincrawler', 'netestate', 'trendictionbot', 'ltx71',
        'gptbot', 'ccbot', 'claudebot', 'anthropic-ai', 'claude-web',
        'perplexitybot', 'youbot', 'imagesiftbot', 'omgili', 'diffbot',
        'amazonbot', 'meta-externalagent', 'bytedance', 'timpibot',
    );
}

/**
 * Penetration testing and exploitation tooling.
 *
 * The old filter missed every one of these: none of them contain the words
 * "bot", "crawler" or "spider", so sqlmap, Nikto and masscan were classified
 * as ordinary human visitors.
 */
function waf_attack_tools()
{
    return array(
        'sqlmap', 'nikto', 'nmap', 'masscan', 'zgrab', 'nuclei',
        'wpscan', 'acunetix', 'netsparker', 'dirbuster', 'gobuster',
        'feroxbuster', 'havij', 'arachni', 'w3af', 'metasploit',
        'xsser', 'commix', 'hydra', 'openvas', 'zaproxy', 'burpsuite',
        'wfuzz', 'ffuf', 'sqlninja', 'nessus', 'whatweb', 'joomscan',
        'droopescan', 'skipfish', 'grabber', 'brutus', 'crackmapexec',
        'dirsearch', 'xray', 'jaeles', 'httprobe', 'sqlpowerinjector',
    );
}

/**
 * Generic HTTP clients. Ambiguous by nature: the same curl that scrapes a
 * catalogue is also how the operator's own integration calls the REST API,
 * which is why API endpoints are exempt from this class.
 */
function waf_generic_clients()
{
    // Tokens here must be specific enough that they cannot appear in the user
    // agent of a real person's device.
    //
    // A bare 'httpclient' was removed after it blocked a customer on a Galaxy
    // A51: Android's own stack identifies as "AndroidHttpClient ... Cronet/..."
    // and in-app browsers send exactly that. Only the server-side Java client
    // is matched now. 'got ' went for the same reason — too short to be safe
    // and worth nothing.
    return array(
        'curl/', 'wget', 'python-requests', 'python-urllib', 'aiohttp',
        'go-http-client', 'libwww-perl', 'java/', 'axios',
        'node-fetch', 'guzzlehttp', 'scrapy', 'apache-httpclient',
        'postmanruntime', 'insomnia', 'restsharp', 'lwp::simple',
        'mechanize', 'phantomjs', 'headlesschrome', 'puppeteer', 'playwright',
        'selenium', 'reqwest', 'urlgrabber',
    );
}

/**
 * Classify the user agent.
 *
 * Returns array(
 *   'class' => 'human' | 'good' | 'bad' | 'attack' | 'generic' | 'empty',
 *   'name'  => matched product name,
 *   'token' => matched token,
 * )
 */
function waf_classify_bot($user_agent)
{
    $ua = trim($user_agent);

    if ($ua === '') {
        return array('class' => 'empty', 'name' => '', 'token' => '');
    }

    $lower = strtolower($ua);

    // Attack tooling first: a scanner that also claims to be Googlebot is
    // still a scanner. The old filter got this backwards - it checked the
    // allow list first, so appending "googlebot" to any user agent was a
    // complete bypass of the block.
    foreach (waf_attack_tools() as $token) {
        if (strpos($lower, $token) !== false) {
            return array('class' => 'attack', 'name' => $token, 'token' => $token);
        }
    }

    // Operator's own allow list (the "Allowed Bots" setting). Checked after
    // attack tooling but before everything else, so an operator can permit a
    // crawler this file would otherwise class as bad or generic — while still
    // never being able to whitelist sqlmap.
    //
    // Without this the setting would be dead: the classifier ships with its
    // own built-in list of search engines and social fetchers, and an operator
    // who had added a niche crawler here would silently lose it.
    foreach (waf_parse_list(waf_setting('allowed_bots', '')) as $token) {
        $token = strtolower(trim($token));

        if ($token !== '' && strpos($lower, $token) !== false) {
            return array('class' => 'good', 'name' => $token, 'token' => $token);
        }
    }

    // Known-bad crawlers are also judged before the allow list, for the same
    // reason: a user agent that names both AhrefsBot and Googlebot is lying
    // about one of them, and it is not lying about being AhrefsBot. The real
    // Googlebot never mentions a competitor's crawler in its user agent, so
    // this ordering cannot misclassify a genuine one.
    foreach (waf_bad_bots() as $token) {
        if (strpos($lower, $token) !== false) {
            return array('class' => 'bad', 'name' => $token, 'token' => $token);
        }
    }

    foreach (waf_good_bots() as $token => $meta) {
        if (strpos($lower, $token) !== false) {
            return array('class' => 'good', 'name' => $meta['name'], 'token' => $token);
        }
    }

    foreach (waf_generic_clients() as $token) {
        if (strpos($lower, $token) !== false) {
            return array('class' => 'generic', 'name' => trim($token), 'token' => $token);
        }
    }

    // Operator-supplied extra keywords, matched last so a site-specific rule
    // can never override a payment gateway or a search engine.
    foreach (waf_parse_list(waf_setting('waf_blocked_agents', '')) as $token) {
        $token = strtolower($token);

        if ($token !== '' && strpos($lower, $token) !== false) {
            return array('class' => 'bad', 'name' => $token, 'token' => $token);
        }
    }

    // Residual crawler signals. Deliberately last and deliberately anchored
    // to a token boundary so "cubot" and "abbott" are not caught.
    if (preg_match('/(?:^|[^a-z0-9])(?:bot|crawler|spider|scraper|fetcher)(?:[^a-z0-9]|$)/i', $lower)) {
        return array('class' => 'bad', 'name' => 'unknown bot', 'token' => 'generic');
    }

    return array('class' => 'human', 'name' => '', 'token' => '');
}

/**
 * Confirm a good-bot claim by reverse DNS, then forward-confirm the hostname.
 *
 * This is the only defence against the trivial spoof: anyone can put
 * "Googlebot" in a user agent and inherit crawler privileges.
 *
 * Verdicts:
 *   'verified' - rDNS and forward lookup agree with the claimed operator
 *   'spoofed'  - rDNS resolved to something else entirely
 *   'unknown'  - no rDNS, lookup failed, or the bot is not verifiable
 *
 * Only 'spoofed' is ever acted on. A DNS outage must not start 403ing
 * Googlebot, so anything short of positive proof of forgery fails open.
 * Results are cached in the database because rDNS costs real wall time.
 */
function waf_verify_bot($ip, $token)
{
    $bots = waf_good_bots();

    if (!isset($bots[$token]) || !$bots[$token]['verify']) {
        return 'unknown';
    }

    if (!waf_is_ip($ip)) {
        return 'unknown';
    }

    // Cached verdict, valid for 7 days.
    $cached = @mysqli_query(
        db::$con,
        "SELECT verdict FROM waf_ip_reputation
         WHERE ip_address = '" . waf_escape($ip) . "'
           AND bot_token = '" . waf_escape($token) . "'
           AND checked_at > " . (time() - 604800) . "
         LIMIT 1"
    );

    if ($cached && @mysqli_num_rows($cached)) {
        $row = @mysqli_fetch_assoc($cached);
        return $row['verdict'];
    }

    $host = @gethostbyaddr($ip);

    // gethostbyaddr returns the input unchanged when there is no PTR record.
    if (!$host || $host === $ip) {
        waf_store_reputation($ip, $token, 'unknown', '');
        return 'unknown';
    }

    $host_lower = strtolower($host);
    $suffix_ok = false;

    foreach ($bots[$token]['verify'] as $suffix) {
        $len = strlen($suffix);

        if (strlen($host_lower) > $len
            && substr($host_lower, -$len) === $suffix
        ) {
            $suffix_ok = true;
            break;
        }
    }

    if (!$suffix_ok) {
        waf_store_reputation($ip, $token, 'spoofed', $host);
        return 'spoofed';
    }

    // Forward-confirm: the PTR record itself is controlled by whoever owns
    // the IP block, so it has to resolve back to the same address.
    $forward = @gethostbynamel($host);

    if (!$forward) {
        waf_store_reputation($ip, $token, 'unknown', $host);
        return 'unknown';
    }

    $verdict = in_array($ip, $forward, true) ? 'verified' : 'spoofed';
    waf_store_reputation($ip, $token, $verdict, $host);

    return $verdict;
}

function waf_store_reputation($ip, $token, $verdict, $host)
{
    @mysqli_query(
        db::$con,
        "INSERT INTO waf_ip_reputation
            (ip_address, bot_token, verdict, host_name, checked_at)
         VALUES
            ('" . waf_escape($ip) . "', '" . waf_escape($token) . "',
             '" . waf_escape($verdict) . "', '" . waf_escape(mb_substr($host, 0, 250)) . "',
             " . time() . ")
         ON DUPLICATE KEY UPDATE
            verdict = VALUES(verdict),
            host_name = VALUES(host_name),
            checked_at = VALUES(checked_at)"
    );
}


/* ─────────────────────────────────────────────────────────────────────────
   SIGNATURE ENGINE
   ───────────────────────────────────────────────────────────────────────── */

/**
 * Rule set.
 *
 * Each rule: id, category, score, regex.
 *
 * Scores are an anomaly budget, not a verdict. Weak-but-legitimate-looking
 * signals (a quote followed by a dash, a long hex string) score low on
 * purpose so that ordinary content - a surname like O'Brien, a Turkish
 * address, a product description containing "<" - cannot reach the
 * threshold on its own. Only unambiguous exploitation patterns score 9-10
 * and therefore block by themselves.
 */
function waf_rules()
{
    static $rules = null;

    if ($rules !== null) {
        return $rules;
    }

    $rules = array(
        // ── SQL injection ────────────────────────────────────────────────
        array('id' => 'sqli-union',   'cat' => 'sqli', 'score' => 10,
              're'  => '/\bunion\b[\s\S]{0,60}?\bselect\b/i'),
        array('id' => 'sqli-tauto',   'cat' => 'sqli', 'score' => 10,
              're'  => '/[\'"]\s*(?:or|and)\s+[\'"]?[\w\s]{0,12}?[\'"]?\s*(?:=|like)\s*[\'"]?[\w\s]{0,12}?[\'"]?(?:\s*(?:--|#|\/\*))?/i'),
        array('id' => 'sqli-numeq',   'cat' => 'sqli', 'score' => 10,
              're'  => '/\b(?:or|and)\b\s+\d+\s*=\s*\d+/i'),
        array('id' => 'sqli-timing',  'cat' => 'sqli', 'score' => 10,
              're'  => '/\b(?:sleep|benchmark|pg_sleep|dbms_pipe\.receive_message)\s*\(|\bwaitfor\s+delay\b/i'),
        array('id' => 'sqli-schema',  'cat' => 'sqli', 'score' => 10,
              're'  => '/\b(?:information_schema|sysobjects|syscolumns|pg_catalog|mysql\s*\.\s*user)\b/i'),
        array('id' => 'sqli-stacked', 'cat' => 'sqli', 'score' => 10,
              're'  => '/;\s*(?:drop|truncate|alter|create|delete|update|insert|grant|shutdown)\s+(?:table|database|schema|from|into|user)\b/i'),
        array('id' => 'sqli-outfile', 'cat' => 'sqli', 'score' => 10,
              're'  => '/\b(?:load_file\s*\(|into\s+(?:out|dump)file\b|extractvalue\s*\(|updatexml\s*\(|group_concat\s*\()/i'),
        // Weak on purpose: a surname like O'Brien followed by a dash, or a
        // long hex string, must never block on its own.
        array('id' => 'sqli-comment', 'cat' => 'sqli', 'score' => 4,
              're'  => '/[\'"]\s*(?:--\s|--$|#|\/\*)/'),
        array('id' => 'sqli-hex',     'cat' => 'sqli', 'score' => 4,
              're'  => '/0x[0-9a-f]{20,}/i'),

        // ── Cross-site scripting ─────────────────────────────────────────
        array('id' => 'xss-script',   'cat' => 'xss', 'score' => 10,
              're'  => '/<\s*\/?\s*script\b/i'),
        array('id' => 'xss-handler',  'cat' => 'xss', 'score' => 10,
              're'  => '/\bon(?:error|load|click|mouseover|mouseenter|focus|blur|submit|abort|toggle|animationstart|animationend|transitionend|beforeunload|pointerover|contextmenu)\s*=/i'),
        array('id' => 'xss-tag',      'cat' => 'xss', 'score' => 10,
              're'  => '/<\s*(?:iframe|object|embed|applet|meta|base|svg|math|link|form|isindex|marquee|frameset)\b/i'),
        // Scoped away from BODY: prose legitimately contains the word
        // "javascript:" (a product review, a support message about code),
        // whereas a redirect parameter carrying it is always an attack.
        array('id' => 'xss-jsuri',    'cat' => 'xss', 'score' => 10,
              'targets' => array('ARGS', 'COOKIE', 'REQUEST_URI', 'REFERER', 'USER_AGENT'),
              're'  => '/(?:javascript|vbscript|livescript)\s*:/i'),
        array('id' => 'xss-datauri',  'cat' => 'xss', 'score' => 10,
              'targets' => array('ARGS', 'COOKIE', 'REQUEST_URI', 'REFERER'),
              're'  => '/data\s*:\s*(?:text\/html|image\/svg\+xml)/i'),
        array('id' => 'xss-sink',     'cat' => 'xss', 'score' => 6,
              're'  => '/\b(?:document\s*\.\s*(?:cookie|write|domain)|window\s*\.\s*location|localStorage|sessionStorage|String\s*\.\s*fromCharCode|eval\s*\(|atob\s*\()/i'),
        array('id' => 'xss-css',      'cat' => 'xss', 'score' => 6,
              're'  => '/(?:expression|behaviou?r|@import|-moz-binding)\s*[:\(]/i'),

        // ── Path traversal / local file inclusion ────────────────────────
        array('id' => 'lfi-traverse', 'cat' => 'lfi', 'score' => 10,
              're'  => '/(?:\.\.[\/\\\\]){2,}|(?:%2e%2e(?:%2f|%5c|\/|\\\\)){1,}|\.\.%c0%af/i'),
        array('id' => 'lfi-unix',     'cat' => 'lfi', 'score' => 10,
              're'  => '/(?:\/etc\/(?:passwd|shadow|group|hosts|my\.cnf)|\/proc\/self\/(?:environ|cmdline)|\/var\/log\/)/i'),
        array('id' => 'lfi-windows',  'cat' => 'lfi', 'score' => 10,
              're'  => '/(?:boot\.ini|win\.ini|windows[\/\\\\]system32|cmd\.exe)/i'),
        array('id' => 'lfi-wrapper',  'cat' => 'lfi', 'score' => 10,
              're'  => '/\b(?:php|phar|zip|expect|glob|data|file)\s*:\s*\/\//i'),
        array('id' => 'lfi-secrets',  'cat' => 'lfi', 'score' => 10,
              'targets' => array('ARGS', 'REQUEST_URI'),
              're'  => '/(?:^|[\/\\\\])(?:\.env|\.git[\/\\\\]config|wp-config\.php|config\.php\.bak|id_rsa|\.htpasswd)(?:$|[?&])/i'),

        // ── Remote code execution ────────────────────────────────────────
        array('id' => 'rce-shell',    'cat' => 'rce', 'score' => 10,
              're'  => '/(?:;|\||&&|\$\(|`|%0a)\s*(?:cat|ls|id|pwd|whoami|uname|wget|curl|nc|netcat|bash|sh|python|perl|ruby|chmod|chown|rm|mv|cp|ping|nslookup|dig)\s/i'),
        // Split by certainty: these names are never ordinary prose.
        array('id' => 'rce-php',      'cat' => 'rce', 'score' => 10,
              're'  => '/\b(?:system|shell_exec|passthru|popen|proc_open|pcntl_exec|create_function|preg_replace\s*\(\s*[\'"][^\'"]*\/e)\s*\(/i'),
        // Softer: "exec(" and "base64_decode(" do turn up in support
        // messages and code snippets, so they corroborate rather than convict.
        array('id' => 'rce-php-soft', 'cat' => 'rce', 'score' => 5,
              're'  => '/\b(?:exec|assert|call_user_func(?:_array)?|file_put_contents|base64_decode|gzinflate|str_rot13)\s*\(/i'),
        array('id' => 'rce-shellshock','cat' => 'rce', 'score' => 10,
              're'  => '/\(\s*\)\s*\{[^}]{0,40}\}\s*;/'),
        array('id' => 'rce-log4j',    'cat' => 'rce', 'score' => 10,
              're'  => '/\$\{\s*(?:jndi|ldap|rmi|dns|env|sys|lower|upper|date)\s*:/i'),
        array('id' => 'rce-deser',    'cat' => 'rce', 'score' => 10,
              're'  => '/(?:^|[;&=])\s*(?:O:\d+:"|a:\d+:\{[isd]:|rO0AB)/'),
        array('id' => 'rce-ssti',     'cat' => 'rce', 'score' => 7,
              're'  => '/\{\{\s*(?:\d+\s*[\*\+\-]\s*\d+|config\b|self\b|request\b|__class__)/i'),
        array('id' => 'rce-xxe',      'cat' => 'rce', 'score' => 10,
              're'  => '/<!(?:DOCTYPE|ENTITY)\b[^>]{0,200}\bSYSTEM\b/i'),

        // ── Protocol abuse ───────────────────────────────────────────────
        array('id' => 'proto-null',   'cat' => 'protocol', 'score' => 10,
              're'  => '/\x00|%00/'),
        array('id' => 'proto-crlf',   'cat' => 'protocol', 'score' => 10,
              're'  => '/(?:%0d%0a|%0a|\r\n)\s*(?:set-cookie|location|content-length|content-type)\s*:/i'),
    );

    return $rules;
}

/**
 * Collect every value worth inspecting, keyed by a human-readable target
 * name so the log entry says WHERE the hit was, not just that there was one.
 */
function waf_collect_targets()
{
    $targets = array();
    $count = 0;

    $sources = array(
        'ARGS'   => isset($_GET) ? $_GET : array(),
        'BODY'   => isset($_POST) ? $_POST : array(),
        'COOKIE' => isset($_COOKIE) ? $_COOKIE : array(),
    );

    foreach ($sources as $label => $source) {
        waf_flatten($source, $label, $targets, $count);
    }

    if ($count < WAF_MAX_VALUES) {
        if (!empty($_SERVER['REQUEST_URI'])) {
            $targets['REQUEST_URI'] = (string) $_SERVER['REQUEST_URI'];
        }

        if (!empty($_SERVER['HTTP_USER_AGENT'])) {
            $targets['USER_AGENT'] = (string) $_SERVER['HTTP_USER_AGENT'];
        }

        if (!empty($_SERVER['HTTP_REFERER'])) {
            $targets['REFERER'] = (string) $_SERVER['HTTP_REFERER'];
        }
    }

    return $targets;
}

/**
 * Flatten nested arrays (form[address][city]) into scannable scalars.
 * Depth-limited: a deliberately deep payload must not blow the stack.
 */
function waf_flatten($value, $prefix, &$targets, &$count, $depth = 0)
{
    if ($count >= WAF_MAX_VALUES || $depth > 6) {
        return;
    }

    if (is_array($value)) {
        foreach ($value as $key => $item) {
            if ($count >= WAF_MAX_VALUES) {
                return;
            }

            // Keys are attacker-controlled too, and are a classic blind spot.
            if (is_string($key) && strlen($key) >= WAF_MIN_VALUE_BYTES) {
                $targets[$prefix . ':' . $key . ' (key)'] = $key;
                $count++;
            }

            waf_flatten($item, $prefix . ':' . $key, $targets, $count, $depth + 1);
        }

        return;
    }

    if (is_object($value) || $value === null || is_bool($value)) {
        return;
    }

    $value = (string) $value;

    if (strlen($value) < WAF_MIN_VALUE_BYTES) {
        return;
    }

    $targets[$prefix] = substr($value, 0, WAF_MAX_VALUE_BYTES);
    $count++;
}

/**
 * Run the rule set over the request.
 *
 * Returns array('score' => int, 'hits' => array of hit descriptors).
 *
 * Each value is tested both raw and URL-decoded once, because a payload that
 * arrives percent-encoded in the path is still the same payload. Decoding is
 * capped at one pass: chasing arbitrary nesting is a well-known way to burn
 * CPU on request.
 */
function waf_scan_request()
{
    $result = array('score' => 0, 'hits' => array());
    $rules = waf_rules();
    $targets = waf_collect_targets();

    foreach ($targets as $name => $value) {
        $variants = array($value);
        $decoded = rawurldecode($value);

        if ($decoded !== $value) {
            $variants[] = $decoded;
        }

        // Target prefix (ARGS / BODY / COOKIE / REQUEST_URI / ...) so rules
        // can be scoped to where their pattern is unambiguous.
        $prefix = $name;
        $colon = strpos($name, ':');

        if ($colon !== false) {
            $prefix = substr($name, 0, $colon);
        }

        foreach ($rules as $rule) {
            if (isset($rule['targets'])
                && !in_array($prefix, $rule['targets'], true)
            ) {
                continue;
            }

            foreach ($variants as $variant) {
                $matched = @preg_match($rule['re'], $variant, $m);

                // A PCRE failure (backtrack limit, bad UTF-8) returns false.
                // Treat it as "no match": a firewall that 403s because its
                // own regex engine gave up is a firewall that takes the site
                // down under load.
                if ($matched !== 1) {
                    continue;
                }

                $result['score'] += $rule['score'];
                $result['hits'][] = array(
                    'id'      => $rule['id'],
                    'cat'     => $rule['cat'],
                    'score'   => $rule['score'],
                    'target'  => $name,
                    'matched' => isset($m[0]) ? substr($m[0], 0, 200) : '',
                );

                // One hit per rule per value: a repeated pattern in a long
                // string should not inflate the score.
                break;
            }
        }
    }

    return $result;
}


/* ─────────────────────────────────────────────────────────────────────────
   RATE LIMITING
   ───────────────────────────────────────────────────────────────────────── */

/**
 * Scripts where automated abuse is expensive: credential stuffing, card
 * testing, form spam, coupon brute-forcing.
 */
function waf_sensitive_scripts()
{
    return array(
        'index.php', 'membership_entrance.php', 'registration_entrance.php',
        'affiliate_entrance.php', 'custom_form.php', 'submit_form.php',
        'cart_action.php', 'add_to_cart.php', 'submit_order.php',
        'get_express_order.php', 'forgot_password.php', 'reset_password.php',
        'email_a_friend.php', 'add_comment.php', 'apps.php', 'api.php',
        'cancel_order.php', 'retrieve_order.php', 'order_history_retrieve_order.php',
    );
}

function waf_is_sensitive_request()
{
    if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
        return true;
    }

    $script = waf_script_name();

    return in_array($script, waf_sensitive_scripts(), true);
}

function waf_script_name()
{
    static $script = null;

    if ($script !== null) {
        return $script;
    }

    $script = '';

    if (!empty($_SERVER['SCRIPT_NAME'])) {
        $script = basename($_SERVER['SCRIPT_NAME']);
    }

    return $script;
}

/**
 * Fixed-window counter, one row per IP per scope.
 *
 * Implemented as a single INSERT ... ON DUPLICATE KEY UPDATE so it is atomic
 * without a transaction or a table lock - two concurrent requests from the
 * same IP cannot both read "1" and both write "2".
 *
 * Returns true when the caller is over the limit.
 */
function waf_rate_exceeded($ip, $scope, $limit, $window_seconds)
{
    if ($limit <= 0) {
        return false;
    }

    $now = time();
    $window_start = $now - ($now % $window_seconds);
    $key = substr(sha1($ip . '|' . $scope), 0, 40) . '|' . $scope;

    $query = "INSERT INTO waf_rate (bucket_key, hits, window_start)
              VALUES ('" . waf_escape($key) . "', 1, " . $window_start . ")
              ON DUPLICATE KEY UPDATE
                  hits = IF(window_start < " . $window_start . ", 1, hits + 1),
                  window_start = " . $window_start;

    if (!@mysqli_query(db::$con, $query)) {
        // Table missing or unavailable - fail open.
        return false;
    }

    $result = @mysqli_query(
        db::$con,
        "SELECT hits FROM waf_rate WHERE bucket_key = '" . waf_escape($key) . "' LIMIT 1"
    );

    if (!$result || !@mysqli_num_rows($result)) {
        return false;
    }

    $row = @mysqli_fetch_assoc($result);

    return ((int) $row['hits'] > $limit);
}

/**
 * Probabilistic cleanup, same approach the performance log uses: roughly one
 * request in two hundred pays for the sweep instead of adding a cron job.
 */
function waf_sweep()
{
    // The row cap runs far more often than the rest of the sweep. Time-based
    // retention is the slow, tidy job; the cap is the safety valve, and a
    // safety valve checked once every two hundred requests is not a safety
    // valve during a flood.
    if (mt_rand(1, 20) === 1) {
        waf_enforce_log_cap();
    }

    if (mt_rand(1, 200) !== 1) {
        return;
    }

    @mysqli_query(db::$con, "DELETE FROM waf_rate WHERE window_start < " . (time() - 3600));

    $days = (int) waf_setting('waf_log_retention_days', 14);

    if ($days > 0) {
        @mysqli_query(
            db::$con,
            "DELETE FROM waf_log WHERE log_timestamp < " . (time() - ($days * 86400)) . " LIMIT 5000"
        );
    }

    if (waf_table_has_column('banned_ip_addresses', 'expires_at')) {
        @mysqli_query(
            db::$con,
            "DELETE FROM banned_ip_addresses
             WHERE source = 'auto' AND expires_at > 0 AND expires_at < " . time()
        );
    }

    @mysqli_query(
        db::$con,
        "DELETE FROM waf_ip_reputation WHERE checked_at < " . (time() - 2592000)
    );
}


/* ─────────────────────────────────────────────────────────────────────────
   LOGGING AND RESPONSE
   ───────────────────────────────────────────────────────────────────────── */

/**
 * Trim the log back to its configured maximum number of rows.
 *
 * Deliberately does NOT use COUNT(*): on InnoDB that is a full index scan,
 * and this runs on live requests. The primary key is auto-increment, so the
 * distance between MAX(id) and the cap gives a cut-off that is both accurate
 * enough and answerable from the index in constant time.
 *
 * Retention by age cannot do this job on its own. Fourteen days of a quiet
 * site is a few hundred rows; fourteen days containing one determined
 * attacker is however many rows that attacker felt like writing. The cap is
 * what makes the table's worst case knowable.
 */
function waf_enforce_log_cap()
{
    $max_rows = (int) waf_setting('waf_log_max_rows', 20000);

    if ($max_rows <= 0) {
        return;
    }

    $result = @mysqli_query(db::$con, "SELECT MAX(id) AS max_id FROM waf_log");

    if (!$result) {
        return;
    }

    $row = @mysqli_fetch_assoc($result);

    if (!$row || !$row['max_id']) {
        return;
    }

    $cut_off = (int) $row['max_id'] - $max_rows;

    if ($cut_off <= 0) {
        return;
    }

    // LIMIT keeps a single sweep short. Repeated calls converge on the cap
    // rather than one call stalling the request that happened to trigger it.
    @mysqli_query(db::$con, "DELETE FROM waf_log WHERE id <= " . $cut_off . " LIMIT 10000");
}

function waf_escape($value)
{
    if (isset(db::$con) && db::$con) {
        return mysqli_real_escape_string(db::$con, (string) $value);
    }

    return addslashes((string) $value);
}

/**
 * Record an event.
 *
 * 'action' says what the firewall DID, which is not the same as what it
 * detected: in monitor mode a request that scored past the threshold is
 * logged as 'would-block' so the operator can read the log and see exactly
 * what enabling blocking would have cost them.
 */
function waf_log_event($action, $rule_id, $category, $score, $target, $matched)
{
    // Pre-upgrade installs still run the legacy bot filter, but have no
    // waf_log table to write to. Skip rather than fire a failing query on
    // every blocked request.
    if (!waf_schema_ready()) {
        return;
    }

    $url = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
    $method = isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : '';
    $agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
    $ip = waf_client_ip();
    $user_id = 0;

    if (isset($_SESSION['software']['user_id'])) {
        $user_id = (int) $_SESSION['software']['user_id'];
    }

    // Aggregation identity. The query string is deliberately excluded: it
    // carries the attack payload, which varies on every request of a fuzzing
    // run, so including it would defeat aggregation exactly when aggregation
    // matters most. The payload is still kept as a sample in `matched`.
    $path = $url;
    $question = strpos($path, '?');

    if ($question !== false) {
        $path = substr($path, 0, $question);
    }

    $event_key = sha1($ip . '|' . $rule_id . '|' . $action . '|' . $category . '|' . $path);

    $now = time();
    $window_start = $now - ($now % WAF_LOG_WINDOW);

    // One statement whether this is the first occurrence or the ten
    // thousandth. On a repeat it touches a single row through the unique key
    // and the table does not grow at all — which is the entire point: under
    // a flood the log must stop being a write amplifier.
    //
    // The sample columns are NOT overwritten on repeats. The first occurrence
    // is the useful one to keep, and rewriting five string columns on every
    // blocked request would put the cost straight back.
    @mysqli_query(
        db::$con,
        "INSERT INTO waf_log
            (event_key, window_start, hit_count, ip_address, action, rule_id,
             category, score, method, request_url, target, matched,
             user_agent, user_id, reference, log_timestamp, last_seen)
         VALUES
            ('" . waf_escape($event_key) . "',
             " . $window_start . ",
             1,
             '" . waf_escape($ip) . "',
             '" . waf_escape($action) . "',
             '" . waf_escape(substr($rule_id, 0, 40)) . "',
             '" . waf_escape(substr($category, 0, 32)) . "',
             " . (int) $score . ",
             '" . waf_escape(substr($method, 0, 8)) . "',
             '" . waf_escape(substr($url, 0, 500)) . "',
             '" . waf_escape(substr($target, 0, 64)) . "',
             '" . waf_escape(substr($matched, 0, 250)) . "',
             '" . waf_escape(substr($agent, 0, 250)) . "',
             " . $user_id . ",
             '" . waf_escape(waf_reference()) . "',
             " . $now . ",
             " . $now . ")
         ON DUPLICATE KEY UPDATE
            hit_count = hit_count + 1,
            last_seen = " . $now . ",
            score = GREATEST(score, " . (int) $score . ")"
    );
}

/**
 * Terminate the request.
 *
 * Deliberately terse: an attacker learns nothing about which rule fired, and
 * a legitimate visitor caught by a false positive gets something they can
 * quote to support. Sends 429 for rate limiting so well-behaved clients back
 * off instead of retrying immediately.
 */
function waf_deny($status, $reference)
{
    // Last line of defence for Monitor mode.
    //
    // Every caller already checks $blocking before reaching here, but "every
    // caller" is exactly the kind of invariant that breaks the moment a new
    // branch is added. Monitor mode promises the operator that nothing will
    // be rejected; that promise is enforced in one place, here, rather than
    // trusted to a dozen call sites.
    if (waf_mode() !== 'block') {
        return;
    }

    if (!headers_sent()) {
        http_response_code($status);
        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('X-Content-Type-Options: nosniff');

        if ($status === 429) {
            header('Retry-After: 60');
        }
    }

    // lang() lives in functions.php, which the router bootstrap does not load.
    // Fall back to English rather than fatally erroring on a request the
    // firewall is already in the middle of rejecting.
    $title = ($status === 429)
        ? waf_text('Too Many Requests')
        : waf_text('Request Blocked');

    $body = ($status === 429)
        ? waf_text('Too many requests from your address. Please wait a moment and try again.')
        : waf_text('This request was blocked by the site firewall. If you believe this is a mistake, please contact the site owner and quote the reference below.');

    $reference_label = waf_text('Reference');
    $page_language = function_exists('lang') ? lang(array('info' => true)) : 'en';

    echo '<!DOCTYPE html><html lang="' . htmlspecialchars($page_language, ENT_QUOTES, 'UTF-8') . '"><head><meta charset="utf-8">'
       . '<meta name="viewport" content="width=device-width, initial-scale=1">'
       . '<meta name="robots" content="noindex, nofollow">'
       . '<title>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</title>'
       . '<style>body{font-family:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;'
       . 'background:#f6f7f9;color:#212529;margin:0;display:flex;min-height:100vh;'
       . 'align-items:center;justify-content:center;padding:1.5rem}'
       . '.b{background:#fff;border:1px solid #dee2e6;border-radius:.75rem;padding:2rem;'
       . 'max-width:34rem;box-shadow:0 .5rem 1rem rgba(0,0,0,.05)}'
       . 'h1{font-size:1.25rem;margin:0 0 .75rem}p{margin:0 0 1rem;line-height:1.6;color:#495057}'
       . 'code{background:#f1f3f5;padding:.2rem .45rem;border-radius:.25rem;font-size:.875rem}'
       . '.l{color:#868e96;font-size:.8125rem;text-transform:uppercase;letter-spacing:.03em}'
       . '</style></head><body><div class="b"><h1>'
       . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h1><p>'
       . htmlspecialchars($body, ENT_QUOTES, 'UTF-8') . '</p><p>'
       . '<span class="l">' . htmlspecialchars($reference_label, ENT_QUOTES, 'UTF-8') . '</span> <code>'
       . htmlspecialchars($reference, ENT_QUOTES, 'UTF-8')
       . '</code></p></div></body></html>';

    exit();
}

/**
 * Translate a string if the language layer is loaded, otherwise return it
 * unchanged.
 *
 * This file is included from router.php, which never loads functions.php, so
 * lang() cannot simply be called. Every user-visible string in the firewall
 * goes through here so the block page is Turkish for a Turkish visitor when
 * the request came through init.php, and still renders (in English) when it
 * did not.
 */
/**
 * User agent for requests this installation makes to other servers.
 *
 * Pinegrap sent no User-Agent header at all on its licence checks, update
 * checks and gateway calls. Any Pinegrap site whose own firewall was armed
 * therefore classified those requests as "no user agent" and rejected them —
 * the software was blocking itself, and the symptom was a licence server
 * that appeared to be unreachable.
 *
 * Deliberately contains no "bot", "crawler" or client-library token, so it
 * classifies as an ordinary visitor: identified, but claiming no privilege
 * and still subject to rate limiting like anything else.
 */
function pinegrap_user_agent()
{
    $version = defined('VERSION') ? VERSION : '';
    $host = defined('HOSTNAME_SETTING') ? HOSTNAME_SETTING : '';

    return 'Pinegrap' . ($version !== '' ? '/' . $version : '')
        . ($host !== '' ? ' (+' . $host . ')' : '');
}

/**
 * Paths that are served to everyone, whatever the bot policy says.
 *
 * robots.txt is the file that tells a crawler what it may not crawl. Refusing
 * to hand it over is self-defeating: the crawler cannot learn the rule it was
 * about to break, so it carries on guessing. The log for one site showed
 * exactly this — AhrefsBot, SemrushBot and DotBot being turned away from
 * /robots.txt over and over, each time losing the chance to be told to leave.
 *
 * .well-known/ carries ACME certificate renewal. Blocking it does not fail
 * loudly; it fails in ninety days, when the certificate expires and the whole
 * site goes down with it.
 *
 * Rate limiting and the IP block list still apply. This exempts a path from
 * BOT POLICY, not from abuse protection.
 */
function waf_path_is_always_served()
{
    $url = isset($_SERVER['REQUEST_URI']) ? strtolower($_SERVER['REQUEST_URI']) : '';

    if ($url === '') {
        return false;
    }

    $question = strpos($url, '?');

    if ($question !== false) {
        $url = substr($url, 0, $question);
    }

    if (substr($url, -11) === '/robots.txt' || $url === 'robots.txt') {
        return true;
    }

    return (strpos($url, '/.well-known/') !== false);
}

/**
 * Is this request another Pinegrap installation calling in?
 *
 * Used for visitor tracking, NOT for access control. The distinction matters:
 *
 *   - It is automated traffic, so counting it as a visitor is simply wrong.
 *     A licence server called by fifty sites every few hours would otherwise
 *     report thousands of daily "visitors" that are all itself.
 *
 *   - It is not privileged. A user agent is a claim anyone can type, so this
 *     never skips rate limiting or any rule. Wiring it to IS_BOT would do
 *     exactly that, because IS_BOT also exempts a request from the rate
 *     limiter — which is how a convenience turns into a bypass.
 */
function waf_is_pinegrap_client()
{
    if (empty($_SERVER['HTTP_USER_AGENT'])) {
        return false;
    }

    return (stripos($_SERVER['HTTP_USER_AGENT'], 'Pinegrap/') !== false);
}

function waf_text($string)
{
    if (function_exists('lang')) {
        return lang($string);
    }

    return $string;
}

/**
 * Stable reference for this request.
 *
 * Generated once and reused, so the code printed on the block page is the
 * same one stored on the log row. It used to be computed fresh at deny time
 * and never written anywhere, which meant the visitor was handed a code the
 * operator had no way to look up — the one piece of information the page
 * exists to convey was useless to both of them.
 */
function waf_reference()
{
    static $reference = null;

    if ($reference === null) {
        $reference = strtoupper(substr(
            sha1(waf_client_ip() . '|' . microtime(true) . '|' . mt_rand()),
            0,
            12
        ));
    }

    return $reference;
}


/* ─────────────────────────────────────────────────────────────────────────
   EXEMPTIONS
   ───────────────────────────────────────────────────────────────────────── */

/**
 * A logged-in software user is exempt from content inspection.
 *
 * This is not laziness, it is a correctness requirement. Page regions are
 * edited from the page's own front-end URL, and dynamic regions, custom
 * styles and hook code are literally HTML, JavaScript and SQL. Scanning an
 * authenticated designer saving a <script> block would make the product
 * unusable while catching nothing: whoever is logged in as a designer can
 * already execute code by design.
 *
 * The login screen itself has no session yet, so it is always inspected -
 * which is where credential attacks actually arrive.
 */
function waf_user_is_authenticated()
{
    return (!empty($_SESSION['sessionusername']) || !empty($_SESSION['software']['user_id']));
}

/**
 * REST API endpoints. Their whole purpose is to be called by scripts, so the
 * generic-HTTP-client bot class must not apply to them. They authenticate
 * with their own key/secret and are still rate limited.
 */
function waf_is_api_request()
{
    $script = waf_script_name();

    return ($script === 'apps.php' || $script === 'api.php');
}

/**
 * Operator-defined path exclusions.
 *
 * The realistic need is payment gateway callbacks: a 3-D Secure return posts
 * a large opaque blob that can trip a signature by coincidence, and losing
 * that request loses a paid order.
 */
function waf_is_excluded()
{
    $patterns = waf_parse_list(waf_setting('waf_exclusions', ''));

    if (!$patterns) {
        return false;
    }

    // Match against the PATH only, never the query string.
    //
    // Matching the whole request URI was a bypass of the entire firewall:
    // the pattern can be put in a query parameter by anyone. With "api2"
    // excluded, a request for
    //
    //     /hesabim?x=1&foo=api2&q=' OR 1=1--
    //
    // matched the exclusion and skipped every check — signature scanning,
    // rate limiting, the IP block list, all of it. An exclusion has to name
    // something the caller cannot append at will, and the path is that.
    //
    // A consequence worth knowing: a feed cannot be excluded by its query
    // parameter (?rss=true), because that is exactly the string an attacker
    // would append. Excluding a feed means excluding its path.
    $url = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
    $question = strpos($url, '?');

    if ($question !== false) {
        $url = substr($url, 0, $question);
    }

    $script = waf_script_name();

    foreach ($patterns as $pattern) {
        if ($pattern === '') {
            continue;
        }

        if ($script !== '' && strcasecmp($script, $pattern) === 0) {
            return true;
        }

        if ($url !== '' && stripos($url, $pattern) !== false) {
            return true;
        }
    }

    return false;
}


/* ─────────────────────────────────────────────────────────────────────────
   MAIN ENTRY POINT
   ───────────────────────────────────────────────────────────────────────── */

/**
 * Run the firewall.
 *
 * Called twice per request from two different bootstraps:
 *
 *   'router' - router.php, before the request is dispatched. The session has
 *              not started yet, so only checks that do not depend on who the
 *              visitor is run here.
 *   'init'   - init.php, after session_start(). Content inspection happens
 *              here because only now can an authenticated designer be told
 *              apart from an anonymous attacker.
 *
 * Every stage is individually latched, so the second call repeats nothing.
 */
function waf_run($stage = 'init')
{
    static $done = array();

    // The whole body is wrapped: a firewall must never be the reason a page
    // 500s. Any unexpected condition means the request proceeds unfiltered.
    try {
        if (!isset(db::$con) || !db::$con) {
            return;
        }

        // ── Not an HTTP request at all ───────────────────────────────────
        //
        // Scheduled tasks run the same scripts from the command line, where
        // there is no remote address, no URL and no user agent. The firewall
        // has nothing to judge and no one to judge it against, so every cron
        // run was being recorded as an anonymous client with no user agent —
        // a run every five minutes producing a steady drip of "would block"
        // entries that looked like an attack and buried the real ones.
        //
        // Two checks because a task can be invoked either by the CLI binary
        // or by a SAPI that still leaves the request superglobals empty.
        // The same "was this reached over the web" test guards session
        // handling in get_file.php.
        if (PHP_SAPI === 'cli'
            || php_sapi_name() === 'cli'
            || empty($_SERVER['REMOTE_ADDR'])
            || empty($_SERVER['HTTP_HOST'])
        ) {
            return;
        }

        // Never interfere with the installer or an unconfigured site.
        if (defined('INSTALLING') || waf_script_name() === 'install') {
            return;
        }

        $mode = waf_mode();

        // The Enable Firewall checkbox is an absolute off switch. Nothing
        // below this line runs when it is unticked — no bot filtering, no
        // signature scanning, no rate limiting, no logging.
        //
        // An earlier version kept the bot filter alive here on the grounds
        // that "Block Unknown Bots" predates the firewall and has its own
        // switch. That was wrong, and it cost a production outage: turning
        // the firewall off did not stop it blocking, so an operator
        // debugging a false positive had no way to make the blocking stop.
        // A switch that does not switch anything off is worse than no switch.
        if ($mode === 'off') {
            return;
        }

        $blocking = ($mode === 'block');
        $ip = waf_client_ip();

        if (empty($done['external'])) {
            $done['external'] = true;
            waf_record_external(waf_detect_external());
        }

        if (waf_is_excluded()) {
            return;
        }

        // ── Allow list wins over everything ──────────────────────────────
        // An operator who has allow-listed their office must not be able to
        // lock themselves out by tuning a rule badly.
        if (waf_ip_is_allowed($ip)) {
            return;
        }

        // ── Block list ───────────────────────────────────────────────────
        if (empty($done['iplist'])) {
            $done['iplist'] = true;

            if (waf_ip_is_blocked($ip)) {
                waf_log_event($blocking ? 'block' : 'would-block', 'ip-list', 'iplist', 10, 'REMOTE_ADDR', $ip);

                if ($blocking) {
                    waf_deny(403, waf_reference());
                }
            }
        }

        // ── Bot classification ───────────────────────────────────────────
        if (empty($done['bot'])) {
            $done['bot'] = true;
            waf_handle_bot($ip, $blocking);
        }

        // ── Rate limiting ────────────────────────────────────────────────
        // The two limits are latched separately on purpose.
        //
        // The global limit is generous and identity-independent, so it can run
        // at the router stage and cover get_file.php, which never reaches
        // init.php.
        //
        // The sensitive limit is much tighter and exempts signed-in staff —
        // and the session does not exist yet at the router stage. Running it
        // there would silently ignore the exemption, and since page regions
        // are edited by POSTing to the page's own front-end URL, a designer
        // saving repeatedly would be rate limited out of their own site.
        if (waf_setting('waf_rate_limit', 1)) {
            if (empty($done['rate_global'])) {
                $done['rate_global'] = true;
                waf_handle_rate_global($ip, $blocking);
            }

            if ($stage === 'init' && empty($done['rate_sensitive'])) {
                $done['rate_sensitive'] = true;
                waf_handle_rate_sensitive($ip, $blocking);
            }
        }

        // ── Signature inspection ─────────────────────────────────────────
        // Init stage only: the session is what distinguishes a designer
        // saving markup from an attacker sending the same bytes.
        if ($stage === 'init'
            && empty($done['scan'])
            && waf_setting('waf_signature_scan', 1)
        ) {
            $done['scan'] = true;

            if (!waf_user_is_authenticated()) {
                waf_handle_scan($ip, $blocking);
            }
        }

        if (empty($done['sweep'])) {
            $done['sweep'] = true;
            waf_sweep();
        }

    } catch (Exception $e) {
        return;
    } catch (Error $e) {
        // PHP 7+ throwables. Harmless on PHP 5 because the block is simply
        // never reached - "Error" is just an unmatched class name there.
        return;
    }
}

/**
 * Bot policy.
 *
 * Order matters and is the main fix over the previous implementation:
 * tooling is judged before the allow list, so appending "googlebot" to a
 * scanner's user agent no longer grants it a free pass.
 */
function waf_handle_bot($ip, $blocking, $legacy_only = false)
{
    $agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
    $bot = waf_classify_bot($agent);

    if ($bot['class'] === 'human') {
        return;
    }

    // Attack tooling: always hostile, independent of the bot settings.
    // Skipped when the firewall is off, because this is a new capability and
    // turning the product on should not start rejecting traffic nobody asked
    // it to reject.
    if ($bot['class'] === 'attack') {
        if (!$legacy_only && waf_setting('waf_block_attack_tools', 1)) {
            waf_log_event($blocking ? 'block' : 'would-block', 'ua-' . $bot['token'], 'tool', 10, 'USER_AGENT', substr($agent, 0, 200));
            waf_register_offence($ip, 10, $blocking);

            if ($blocking) {
                waf_deny(403, waf_reference());
            }
        }

        return;
    }

    if ($bot['class'] === 'good') {
        // Mark the request so visitor tracking can skip it.
        if (!defined('IS_BOT')) {
            define('IS_BOT', true);
        }

        if ($legacy_only || !waf_setting('waf_verify_bots', 1)) {
            return;
        }

        $verdict = waf_verify_bot($ip, $bot['token']);

        // Only a positive rDNS mismatch is acted on. No PTR record, a DNS
        // timeout or an unverifiable operator all fail open, so a resolver
        // outage cannot start returning 403 to Googlebot.
        if ($verdict === 'spoofed') {
            waf_log_event($blocking ? 'block' : 'would-block', 'bot-spoof', 'bot', 10, 'USER_AGENT', $bot['name'] . ' (forged)');
            waf_register_offence($ip, 10, $blocking);

            if ($blocking) {
                waf_deny(403, waf_reference());
            }
        }

        return;
    }

    // robots.txt and ACME challenges are served to anyone. See
    // waf_path_is_always_served() — turning a crawler away from the file that
    // would have told it to go away achieves nothing.
    if (waf_path_is_always_served()) {
        return;
    }

    // Everything below is only enforced when the operator asked for it.
    if (!waf_setting('block_unknown_bots', 0)) {
        if (!defined('IS_BOT')) {
            define('IS_BOT', true);
        }

        return;
    }

    // Generic HTTP clients are how the REST API is legitimately consumed.
    if ($bot['class'] === 'generic' && waf_is_api_request()) {
        return;
    }

    // An empty user agent is suspicious but not proof of anything, and some
    // corporate proxies strip it. Never a standalone block on the API.
    if ($bot['class'] === 'empty' && waf_is_api_request()) {
        return;
    }

    if (!defined('IS_BOT')) {
        define('IS_BOT', true);
    }

    $label = ($bot['class'] === 'empty') ? '(no user agent)' : $bot['name'];

    waf_log_event($blocking ? 'block' : 'would-block', 'bot-' . $bot['class'], 'bot', 6, 'USER_AGENT', $label);

    if ($blocking) {
        waf_deny(403, waf_reference());
    }
}

function waf_handle_rate_global($ip, $blocking)
{
    // A verified good bot crawling fast is doing its job.
    if (defined('IS_BOT') && IS_BOT) {
        return;
    }

    // Counting requests against an unresolved address would count the whole
    // internet as one visitor. See waf_ip_is_infrastructure().
    if (waf_ip_is_infrastructure($ip)) {
        return;
    }

    if (waf_user_is_authenticated()) {
        return;
    }

    $limit = (int) waf_setting('waf_rate_limit_requests', 300);

    if (!waf_rate_exceeded($ip, 'g', $limit, 60)) {
        return;
    }

    waf_log_event($blocking ? 'rate' : 'would-rate', 'rate-global', 'rate', 5, 'REMOTE_ADDR', $limit . '/min');

    if ($blocking) {
        waf_deny(429, waf_reference());
    }
}

function waf_handle_rate_sensitive($ip, $blocking)
{
    if (defined('IS_BOT') && IS_BOT) {
        return;
    }

    // Counting requests against an unresolved address would count the whole
    // internet as one visitor. See waf_ip_is_infrastructure().
    if (waf_ip_is_infrastructure($ip)) {
        return;
    }

    // Signed-in staff are exempt. This check is the reason the sensitive limit
    // runs at the init stage rather than the router stage.
    if (waf_user_is_authenticated()) {
        return;
    }

    if (!waf_is_sensitive_request()) {
        return;
    }

    $limit = (int) waf_setting('waf_rate_limit_sensitive', 30);

    if (!waf_rate_exceeded($ip, 's', $limit, 60)) {
        return;
    }

    waf_log_event($blocking ? 'rate' : 'would-rate', 'rate-sensitive', 'rate', 7, waf_script_name(), $limit . '/min');
    waf_register_offence($ip, 5, $blocking);

    if ($blocking) {
        waf_deny(429, waf_reference());
    }
}

function waf_handle_scan($ip, $blocking)
{
    $scan = waf_scan_request();

    if (!$scan['hits']) {
        return;
    }

    $threshold = waf_threshold();
    $over = ($scan['score'] >= $threshold);

    // Below the threshold the individual hits are still recorded, at a
    // lower severity. That log is what lets an operator tune sensitivity on
    // evidence from their own traffic instead of guessing.
    $action = $over
        ? ($blocking ? 'block' : 'would-block')
        : 'log';

    $first = $scan['hits'][0];

    waf_log_event(
        $action,
        $first['id'],
        $first['cat'],
        $scan['score'],
        $first['target'],
        $first['matched']
    );

    if (!$over) {
        return;
    }

    waf_register_offence($ip, $scan['score'], $blocking);

    if ($blocking) {
        waf_deny(403, waf_reference());
    }
}

/**
 * Count repeat offences and escalate to a temporary ban.
 *
 * Rationale: a single blocked request costs an attacker nothing, they simply
 * try the next payload. Banning after a handful of hits turns a long probing
 * session into one short one. Bans always expire, and only ever happen in
 * blocking mode - in monitor mode the firewall observes and never acts.
 */
function waf_register_offence($ip, $score, $blocking)
{
    if (!$blocking || !waf_setting('waf_auto_ban', 1)) {
        return;
    }

    if (!waf_is_ip($ip) || waf_ip_is_allowed($ip)) {
        return;
    }

    // Banning loopback or the tunnel's own address takes the site off the air.
    if (waf_ip_is_infrastructure($ip)) {
        return;
    }

    $threshold = (int) waf_setting('waf_auto_ban_threshold', 5);

    if ($threshold <= 0) {
        return;
    }

    // Reuse the rate bucket machinery: a 10 minute window of offences.
    if (!waf_rate_exceeded($ip, 'o', $threshold, 600)) {
        return;
    }

    $minutes = (int) waf_setting('waf_auto_ban_minutes', 60);

    if ($minutes <= 0) {
        $minutes = 60;
    }

    waf_auto_ban($ip, $minutes, 'Automatic: ' . $threshold . '+ firewall events');
    waf_log_event('ban', 'auto-ban', 'ban', $score, 'REMOTE_ADDR', $minutes . ' min');
}
