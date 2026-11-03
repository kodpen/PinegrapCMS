<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Page experiments (2026.11.0, 11.0.153): the model and the visitor's bucket.
 *
 * An experiment (table experiments) names a live designed page, its variants
 * (experiment_variants: variant A is the live page itself, draft_page_id 0;
 * the others are draft pages) with their weights, a goal and a status. A
 * page has at most one running experiment, read once per request by
 * pg_experiment_for_page().
 *
 * A visitor's first request on such a page draws a variant
 * (pg_experiment_draw(), weighted) and keeps it in the strictly functional
 * pg_exp cookie for 90 days as "<experiment id>:<key>" pairs, so later
 * requests show the same variant. Crawlers, staff, the designer, Preview and
 * prototype play never join (pg_experiment_request_excluded()): they always
 * get the live page.
 *
 * Views and goals go to the hourly rollup experiment_hourly; serving the
 * variant and counting come with the router and the page cache.
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
 * The bucket cookie's name.
 */
define('PG_EXPERIMENT_COOKIE', 'pg_exp');

/**
 * Days the bucket cookie lives.
 */
define('PG_EXPERIMENT_COOKIE_DAYS', 90);

/**
 * Experiments one bucket cookie remembers; the oldest drop first.
 */
define('PG_EXPERIMENT_COOKIE_PAIRS', 20);

/**
 * Goals one address may count in a minute (pg_experiment_goal_rate_exceeded()).
 */
define('PG_EXPERIMENT_GOAL_RATE_LIMIT', 10);

/**
 * The statuses of an experiment, in their usual order.
 *
 * @return array
 */
function pg_experiment_statuses()
{
    return array('draft', 'running', 'paused', 'ended');
}

/**
 * Whether an experiment may move from one status to another: a draft
 * starts; a running one pauses or ends; a paused one runs again or ends; an
 * ended one stays ended. A draft that is not wanted is deleted, not ended.
 * Pure.
 *
 * @param string $from
 * @param string $to
 * @return bool
 */
function pg_experiment_may_move($from, $to)
{
    $moves = array(
        'draft' => array('running'),
        'running' => array('paused', 'ended'),
        'paused' => array('running', 'ended'),
        'ended' => array(),
    );

    $from = (string) $from;

    return isset($moves[$from]) && in_array((string) $to, $moves[$from], true);
}

/**
 * The variant key a random number falls on. Each key holds a share of
 * [0, 1) as large as its weight, in the order given; a zero, negative or
 * non-numeric weight holds none. Pure.
 *
 * @param array $weights key => weight
 * @param float $random  in [0, 1); held inside it
 * @return string the key, '' when no weight is positive
 */
function pg_experiment_draw(array $weights, $random)
{
    $clean = array();
    $total = 0;

    foreach ($weights as $key => $weight) {
        $weight = is_numeric($weight) ? (float) $weight : 0.0;

        if ($weight > 0) {
            $clean[(string) $key] = $weight;
            $total += $weight;
        }
    }

    if ($total <= 0) {
        return '';
    }

    $random = is_numeric($random) ? (float) $random : 0.0;
    $random = max(0.0, min($random, 1.0));

    $target = $random * $total;
    $sum = 0.0;
    $last = '';

    foreach ($clean as $key => $weight) {
        $sum += $weight;
        $last = (string) $key;

        if ($target < $sum) {
            return $last;
        }
    }

    // A random of 1 (or a rounding at the top) lands on the last key.
    return $last;
}

/**
 * A random number in [0, 1) for pg_experiment_draw().
 *
 * @return float
 */
function pg_experiment_random()
{
    if (function_exists('random_int')) {
        try {
            return random_int(0, 999999) / 1000000;
        } catch (Exception $e) {
            // Fall through to mt_rand() when the system has no source.
        }
    }

    return mt_rand(0, 999999) / 1000000;
}

/**
 * Whether a string is a valid variant key: 1-8 lower-case letters or digits
 * (experiment_variants.variant_key is VARCHAR(8)). Pure.
 *
 * @param mixed $key
 * @return bool
 */
function pg_experiment_key_valid($key)
{
    return is_string($key) && (preg_match('/^[a-z0-9]{1,8}$/', $key) === 1);
}

/**
 * Reads the bucket cookie's value: "<experiment id>:<key>" pairs joined by
 * dots. Malformed pairs are dropped, a later pair for the same experiment
 * wins, keys are read in lower case and an oversized value is ignored. Pure.
 *
 * @param mixed $value
 * @return array experiment id => key, by id
 */
function pg_experiment_bucket_parse($value)
{
    if (!is_string($value) || ($value === '') || (strlen($value) > 1024)) {
        return array();
    }

    $pairs = array();

    foreach (explode('.', $value) as $pair) {
        $parts = explode(':', $pair);

        // A third part is the day a goal was last counted for the pair
        // (pg_experiment_goal_seen_parse()); the bucket is read without it.
        if ((count($parts) < 2) || (count($parts) > 3) || (preg_match('/^[1-9][0-9]{0,9}$/', $parts[0]) !== 1)) {
            continue;
        }

        $key = strtolower($parts[1]);

        if (!pg_experiment_key_valid($key)) {
            continue;
        }

        $pairs[(int) $parts[0]] = $key;
    }

    ksort($pairs);

    return $pairs;
}

/**
 * Writes the bucket cookie's value from experiment id => key pairs, sorted
 * by id. Invalid pairs are left out; beyond PG_EXPERIMENT_COOKIE_PAIRS the
 * lowest ids (the oldest experiments) drop. A pair whose experiment has a
 * goal mark gets the day as a third part ("<id>:<key>:<day>"). Pure.
 *
 * @param array $pairs
 * @param array $seen experiment id => day number (pg_experiment_day_number())
 *                    a goal was last counted
 * @return string
 */
function pg_experiment_bucket_format(array $pairs, array $seen = array())
{
    $clean = array();

    foreach ($pairs as $id => $key) {
        if ((is_int($id) || ctype_digit((string) $id)) && ((int) $id > 0) && pg_experiment_key_valid($key)) {
            $clean[(int) $id] = $key;
        }
    }

    ksort($clean);

    if (count($clean) > PG_EXPERIMENT_COOKIE_PAIRS) {
        $clean = array_slice($clean, -PG_EXPERIMENT_COOKIE_PAIRS, null, true);
    }

    $out = array();

    foreach ($clean as $id => $key) {
        $day = isset($seen[$id]) ? $seen[$id] : null;
        $out[] = $id . ':' . $key . (pg_experiment_day_valid($day) ? ':' . (int) $day : '');
    }

    return implode('.', $out);
}

/**
 * When a bucket cookie written now expires. Pure.
 *
 * @param int $now
 * @return int
 */
function pg_experiment_bucket_expires($now)
{
    return (int) $now + PG_EXPERIMENT_COOKIE_DAYS * 86400;
}

/**
 * The visitor's buckets from the request's pg_exp cookie.
 *
 * @return array experiment id => key
 */
function pg_experiment_bucket_read()
{
    return pg_experiment_bucket_parse(isset($_COOKIE[PG_EXPERIMENT_COOKIE]) ? $_COOKIE[PG_EXPERIMENT_COOKIE] : '');
}

/**
 * Sends the pg_exp cookie with the given buckets for 90 days: path /,
 * HttpOnly, SameSite Lax, Secure over https. Strictly functional, so it
 * needs no consent; it is listed in pg_consent_catalogue(). $_COOKIE is
 * updated too, so a later read in this request sees it.
 *
 * @param array     $pairs  experiment id => key
 * @param bool|null $secure Secure attribute; null asks pg_request_is_https()
 *                          (the router, before it is loaded, gives its own)
 * @param array|null $seen  goal marks (experiment id => day); null keeps the
 *                          ones the request's cookie holds
 * @return bool false when headers are already sent
 */
function pg_experiment_bucket_write(array $pairs, $secure = null, $seen = null)
{
    // The goal marks the cookie holds stay with their pairs.
    $value = pg_experiment_bucket_format($pairs, is_array($seen) ? $seen : pg_experiment_goal_seen_read());

    if (headers_sent()) {
        return false;
    }

    if ($secure === null) {
        $secure = function_exists('pg_request_is_https') && pg_request_is_https();
    }

    $secure = (bool) $secure;
    $expires = ($value === '') ? time() - 86400 : pg_experiment_bucket_expires(time());

    if (PHP_VERSION_ID >= 70300) {
        setcookie(PG_EXPERIMENT_COOKIE, $value, array(
            'expires' => $expires,
            'path' => '/',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ));
    } else {
        // Before 7.3 setcookie() has no SameSite parameter; the attribute
        // rides on the path, which is printed as it is.
        setcookie(PG_EXPERIMENT_COOKIE, $value, $expires, '/; samesite=Lax', '', $secure, true);
    }

    if ($value === '') {
        unset($_COOKIE[PG_EXPERIMENT_COOKIE]);
    } else {
        $_COOKIE[PG_EXPERIMENT_COOKIE] = $value;
    }

    pg_experiment_bucket_sent(true);

    return true;
}

/**
 * Whether this request sent the pg_exp cookie (the router's draw or the
 * page's); with true, notes that it did.
 *
 * @param bool|null $set
 * @return bool
 */
function pg_experiment_bucket_sent($set = null)
{
    static $sent = false;

    if ($set === true) {
        $sent = true;
    }

    return $sent;
}

/**
 * Why a request never joins an experiment and gets the live page: 'bot' (a
 * crawler or script by the firewall's verdict), 'staff' (a signed-in panel
 * user), 'designer' (the designer's own draw), 'preview' (the designer's or
 * the panel's Preview), 'prototype' (prototype play); '' for a visitor who
 * joins. The first reason in that order is given. Pure.
 *
 * @param array $r bot, staff, designer, preview, prototype (bools)
 * @return string
 */
function pg_experiment_request_excluded(array $r)
{
    foreach (array('bot', 'staff', 'designer', 'preview', 'prototype') as $reason) {
        if (!empty($r[$reason])) {
            return $reason;
        }
    }

    return '';
}

/**
 * The facts pg_experiment_request_excluded() reads, from this request.
 *
 * @param string $mode get_page_content()'s mode: 'edit' is the designer's
 *                     own draw, 'preview' with the panel's marker a Preview
 * @return array
 */
function pg_experiment_request_context($mode = '')
{
    $staff = defined('USER_LOGGED_IN') && USER_LOGGED_IN && defined('USER_ROLE') && ((int) USER_ROLE < 3);

    return array(
        'bot' => function_exists('pg_request_is_automated') && pg_request_is_automated(),
        'staff' => $staff,
        'designer' => ((string) $mode === 'edit'),
        'preview' => function_exists('pg_request_is_panel_preview')
            && pg_request_is_panel_preview($_GET, defined('USER_LOGGED_IN') && USER_LOGGED_IN),
        'prototype' => defined('PG_PROTOTYPE_ENTRY'),
    );
}

/**
 * The running experiment of a page with its variants, or null. A page has
 * at most one; when the data holds more, the oldest is taken. Read once per
 * request and page. A site whose upgrade has not created the tables yet has
 * none.
 *
 * @param int $page_id
 * @return array|null id, page_id, name, goal_kind, goal_target, started_at,
 *                    variants (key => id, draft_page_id, variant_key, weight)
 */
function pg_experiment_for_page($page_id)
{
    static $cache = array();

    $page_id = (int) $page_id;

    if ($page_id <= 0) {
        return null;
    }

    if (array_key_exists($page_id, $cache)) {
        return $cache[$page_id];
    }

    $cache[$page_id] = null;

    if (!function_exists('pg_schema_has') || !pg_schema_has('experiments') || !pg_schema_has('experiment_variants')) {
        return null;
    }

    $result = db_q(
        "SELECT id, page_id, name, goal_kind, goal_target, started_at
        FROM experiments
        WHERE page_id = ? AND status = 'running'
        ORDER BY id
        LIMIT 1",
        array($page_id));

    $experiment = $result ? mysqli_fetch_assoc($result) : null;

    if (!$experiment) {
        return null;
    }

    $result = db_q(
        "SELECT id, draft_page_id, variant_key, weight
        FROM experiment_variants
        WHERE experiment_id = ?
        ORDER BY variant_key",
        array((int) $experiment['id']));

    $variants = array();

    while ($result && ($row = mysqli_fetch_assoc($result))) {
        if (pg_experiment_key_valid($row['variant_key'])) {
            $variants[$row['variant_key']] = array(
                'id' => (int) $row['id'],
                'draft_page_id' => (int) $row['draft_page_id'],
                'variant_key' => $row['variant_key'],
                'weight' => (int) $row['weight'],
            );
        }
    }

    if (count($variants) < 2) {
        return null;
    }

    $experiment['id'] = (int) $experiment['id'];
    $experiment['page_id'] = (int) $experiment['page_id'];
    $experiment['started_at'] = (int) $experiment['started_at'];
    $experiment['variants'] = $variants;

    $cache[$page_id] = $experiment;

    return $experiment;
}


// ── Serving a variant ──────────────────────────────────────────────────────
//
// get_page.php asks pg_experiment_serve() once, before the page is drawn: a
// visitor who joins gets their bucket read or drawn, and get_page_content()
// takes the body of the variant's draft (pg_experiment_body_page_id()) while
// the title, meta tags, structured data and address stay the live page's.
// The page cache keeps one copy per variant (pg_page_cache_variant_key()) and
// the router draws the bucket itself from the weights the stored copy
// carries (pg_page_cache_experiment_route()).

/**
 * The variant key that marks the live page: the one without a draft. '' when
 * every variant has a draft. Pure.
 *
 * @param array $variants key => [draft_page_id, ...]
 * @return string
 */
function pg_experiment_control_key(array $variants)
{
    foreach ($variants as $key => $variant) {
        if ((int) ($variant['draft_page_id'] ?? 0) === 0) {
            return (string) $key;
        }
    }

    return '';
}

/**
 * The weights of an experiment's variants, key => weight. Pure.
 *
 * @param array $variants key => [weight, ...]
 * @return array
 */
function pg_experiment_weights(array $variants)
{
    $weights = array();

    foreach ($variants as $key => $variant) {
        $weights[(string) $key] = max(0, (int) ($variant['weight'] ?? 0));
    }

    return $weights;
}

/**
 * The variant a visitor sees: the key their bucket holds while that variant
 * still has a weight, else a new draw. Pure.
 *
 * @param array    $weights key => weight
 * @param string   $bucket  the key the cookie holds for this experiment, ''
 * @param float    $random  for the draw
 * @return array ['key' => string ('' when nothing can be drawn), 'drawn' => bool]
 */
function pg_experiment_pick(array $weights, $bucket, $random)
{
    $bucket = (string) $bucket;

    if (($bucket !== '') && isset($weights[$bucket]) && is_numeric($weights[$bucket]) && ((float) $weights[$bucket] > 0)) {
        return array('key' => $bucket, 'drawn' => false);
    }

    $key = pg_experiment_draw($weights, $random);

    return array('key' => $key, 'drawn' => ($key !== ''));
}

/**
 * The page whose body is drawn for a page: the variant's draft while this
 * request serves one of it, else the page itself. Pure over the served
 * state.
 *
 * @param int        $page_id
 * @param array|null $served pg_experiment_served()
 * @return int
 */
function pg_experiment_body_page_for($page_id, $served)
{
    $page_id = (int) $page_id;

    if (!is_array($served) || ((int) ($served['page_id'] ?? 0) !== $page_id) || ((int) ($served['draft_page_id'] ?? 0) <= 0)) {
        return $page_id;
    }

    return (int) $served['draft_page_id'];
}

/**
 * The experiment this request serves, set once by pg_experiment_serve();
 * with an argument, sets it (false clears it).
 *
 * @param array|false|null $set
 * @return array|null experiment_id, page_id, key, variant_id, draft_page_id,
 *                    control_key (the live page's key), weights, drawn
 */
function pg_experiment_served($set = null)
{
    static $served = null;

    if ($set === false) {
        $served = null;
    } elseif (is_array($set)) {
        $served = $set;
    }

    return $served;
}

/**
 * get_page.php, before the page is drawn: whether a running experiment of
 * the page serves this request a variant. A visitor who joins gets the key
 * their pg_exp cookie holds or a new draw, and the cookie is sent when it
 * was drawn now. Crawlers, staff, the designer, Preview, prototype play and
 * e-mail bodies get the live page and nothing is set.
 *
 * @param int    $page_id
 * @param string $mode  get_page_content()'s mode
 * @param bool   $email the page is drawn as an e-mail body
 * @return array|null pg_experiment_served()
 */
function pg_experiment_serve($page_id, $mode = 'preview', $email = false)
{
    pg_experiment_served(false);

    if ($email) {
        return null;
    }

    $experiment = pg_experiment_for_page($page_id);

    if (($experiment === null) || (pg_experiment_request_excluded(pg_experiment_request_context($mode)) !== '')) {
        return null;
    }

    $weights = pg_experiment_weights($experiment['variants']);
    $buckets = pg_experiment_bucket_read();
    $pick = pg_experiment_pick($weights, isset($buckets[$experiment['id']]) ? $buckets[$experiment['id']] : '', pg_experiment_random());

    if ($pick['key'] === '') {
        return null;
    }

    if ($pick['drawn']) {
        $buckets[$experiment['id']] = $pick['key'];
        pg_experiment_bucket_write($buckets);
    }

    $variant = $experiment['variants'][$pick['key']];

    return pg_experiment_served(array(
        'experiment_id' => $experiment['id'],
        'page_id' => (int) $page_id,
        'key' => $pick['key'],
        'variant_id' => $variant['id'],
        'draft_page_id' => $variant['draft_page_id'],
        'control_key' => pg_experiment_control_key($experiment['variants']),
        'weights' => $weights,
        'drawn' => $pick['drawn'],
    ));
}

/**
 * get_page_content(): the page whose body is drawn for $page_id in this
 * request (the served variant's draft, else the page).
 *
 * @param int $page_id
 * @return int
 */
function pg_experiment_body_page_id($page_id)
{
    return pg_experiment_body_page_for($page_id, pg_experiment_served());
}

/**
 * The generated body of a variant's draft; '' when the draft is gone or has
 * none, and then the live page's body is drawn.
 *
 * @param int $draft_page_id
 * @return string
 */
function pg_experiment_variant_code($draft_page_id)
{
    if (((int) $draft_page_id <= 0) || !function_exists('pg_multi_page_design_ready') || !pg_multi_page_design_ready()) {
        return '';
    }

    $code = db_value("SELECT page_tree_code FROM page WHERE page_id = '" . (int) $draft_page_id . "' LIMIT 1");

    return is_string($code) ? $code : '';
}

/**
 * pg_page_cache_apply() took every Set-Cookie line of a cacheable answer
 * away: a bucket sent in this request (drawn by the router or by
 * pg_experiment_serve()) is sent again, or the visitor would draw anew on
 * every page.
 */
function pg_experiment_bucket_resend()
{
    if (pg_experiment_bucket_sent()) {
        pg_experiment_bucket_write(pg_experiment_bucket_read());
    }
}

/**
 * The bucket key of an experiment_hourly row: sha1 of
 * "day|hour|experiment|variant". Pure.
 *
 * @param string $day Y-m-d
 * @param int    $hour
 * @param int    $experiment_id
 * @param int    $variant_id
 * @return string
 */
function pg_experiment_hourly_key($day, $hour, $experiment_id, $variant_id)
{
    return sha1((string) $day . '|' . (int) $hour . '|' . (int) $experiment_id . '|' . (int) $variant_id);
}

/**
 * Counts one view of a variant into this hour's experiment_hourly row: one
 * INSERT ... ON DUPLICATE KEY UPDATE.
 *
 * @param int $experiment_id
 * @param int $variant_id
 * @return bool
 */
function pg_experiment_count_view($experiment_id, $variant_id)
{
    $experiment_id = (int) $experiment_id;
    $variant_id = (int) $variant_id;

    if (($experiment_id <= 0) || ($variant_id <= 0) || !pg_schema_has('experiment_hourly')) {
        return false;
    }

    $day = date('Y-m-d');
    $hour = (int) date('G');

    return db_q(
        "INSERT INTO experiment_hourly (id, day, hour, experiment_id, variant_id, views, goals)
        VALUES (?, ?, ?, ?, ?, 1, 0)
        ON DUPLICATE KEY UPDATE views = views + 1",
        array(pg_experiment_hourly_key($day, $hour, $experiment_id, $variant_id), $day, $hour, $experiment_id, $variant_id)) !== false;
}

/**
 * The experiment and variant a page-view beacon names, when that experiment
 * still runs on the page and the variant is one of its own; null otherwise.
 * Pure over the running experiment.
 *
 * @param array|null $experiment pg_experiment_for_page() of the beacon's page
 * @param int        $experiment_id
 * @param int        $variant_id
 * @return array|null [experiment_id, variant_id]
 */
function pg_experiment_beacon_view($experiment, $experiment_id, $variant_id)
{
    if (!is_array($experiment) || ((int) $experiment_id <= 0) || ((int) $experiment['id'] !== (int) $experiment_id)) {
        return null;
    }

    foreach ($experiment['variants'] as $variant) {
        if ((int) $variant['id'] === (int) $variant_id) {
            return array((int) $experiment_id, (int) $variant_id);
        }
    }

    return null;
}


// ── Goals ──────────────────────────────────────────────────────────────────
//
// A goal is counted for the variant the visitor's pg_exp cookie holds, into
// the hour's experiment_hourly row (goals = goals + 1), once a day per
// experiment: the day of the last count rides on the cookie's pair as a
// third part ("<id>:<key>:<day>"), so nothing is written per visitor. The
// kinds: a custom form sent (custom_form.php), an order completed
// (pg_order_complete()), a click on an element marked data-pg-goal (a small
// script on the experiment's page posts it to page_view.php) and a page
// reached (get_page.php, and page_view.php for a cached copy).

/**
 * The day number of a Y-m-d date: days since 1970-01-01, whatever the
 * server's time zone; 0 for a value that is no date. Pure.
 *
 * @param string $ymd
 * @return int
 */
function pg_experiment_day_number($ymd)
{
    if (!is_string($ymd) || (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $ymd, $m) !== 1) || !checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
        return 0;
    }

    return (int) floor(gmmktime(0, 0, 0, (int) $m[2], (int) $m[3], (int) $m[1]) / 86400);
}

/**
 * Whether a value is a day number the cookie can carry (1-6 digits). Pure.
 *
 * @param mixed $day
 * @return bool
 */
function pg_experiment_day_valid($day)
{
    return (is_int($day) || (is_string($day) && ctype_digit($day))) && ((int) $day > 0) && ((int) $day < 1000000);
}

/**
 * The goal marks of a pg_exp cookie value: experiment id => the day number
 * a goal was last counted. A pair without one, or with a malformed one, has
 * none. Pure.
 *
 * @param mixed $value
 * @return array
 */
function pg_experiment_goal_seen_parse($value)
{
    $buckets = pg_experiment_bucket_parse($value);
    $seen = array();

    if ($buckets === array()) {
        return $seen;
    }

    foreach (explode('.', $value) as $pair) {
        $parts = explode(':', $pair);

        if ((count($parts) === 3) && isset($buckets[(int) $parts[0]]) && (preg_match('/^[1-9][0-9]{0,5}$/', $parts[2]) === 1)) {
            $seen[(int) $parts[0]] = (int) $parts[2];
        }
    }

    ksort($seen);

    return $seen;
}

/**
 * The goal marks of the request's pg_exp cookie.
 *
 * @return array experiment id => day number
 */
function pg_experiment_goal_seen_read()
{
    return pg_experiment_goal_seen_parse(isset($_COOKIE[PG_EXPERIMENT_COOKIE]) ? $_COOKIE[PG_EXPERIMENT_COOKIE] : '');
}

/**
 * Whether a goal of an experiment may be counted today: not yet counted on
 * that day for this cookie. Pure.
 *
 * @param array $seen experiment id => day number
 * @param int   $experiment_id
 * @param int   $today day number
 * @return bool
 */
function pg_experiment_goal_due(array $seen, $experiment_id, $today)
{
    return !isset($seen[(int) $experiment_id]) || ((int) $seen[(int) $experiment_id] !== (int) $today);
}

/**
 * Whether an event is an experiment's goal. Pure.
 * - form: a custom form sent on the page goal_target names, or, without
 *   one, on the experiment's page or one of its variant drafts (page_id)
 * - order: any completed order
 * - click: a marked element on the experiment's own page (experiment_id)
 *   whose goal name is goal_target, or any marked element without one (name)
 * - page: the page goal_target names was viewed (page_id)
 *
 * @param array  $experiment id, page_id, goal_kind, goal_target, variants
 * @param string $kind
 * @param array  $event page_id, experiment_id, name
 * @return bool
 */
function pg_experiment_goal_matches(array $experiment, $kind, array $event)
{
    $kind = (string) $kind;

    if ((string) ($experiment['goal_kind'] ?? '') !== $kind) {
        return false;
    }

    $target = trim((string) ($experiment['goal_target'] ?? ''));
    $page_id = (int) ($event['page_id'] ?? 0);

    switch ($kind) {
        case 'form':
            if ($page_id <= 0) {
                return false;
            }

            if ($target !== '') {
                return ctype_digit($target) && ((int) $target === $page_id);
            }

            if ((int) ($experiment['page_id'] ?? 0) === $page_id) {
                return true;
            }

            foreach ((array) ($experiment['variants'] ?? array()) as $variant) {
                if ((int) ($variant['draft_page_id'] ?? 0) === $page_id) {
                    return true;
                }
            }

            return false;

        case 'order':
            return true;

        case 'click':
            return ((int) ($event['experiment_id'] ?? 0) === (int) ($experiment['id'] ?? -1))
                && (($target === '') || ($target === (string) ($event['name'] ?? '')));

        case 'page':
            return ($page_id > 0) && ctype_digit($target) && ((int) $target === $page_id);
    }

    return false;
}

/**
 * The count lines an event gives: one [experiment id, variant id] per
 * running experiment the event is a goal of, whose bucket the visitor holds
 * for a variant it still has, not counted yet today; with the goal marks
 * updated for them. Pure.
 *
 * @param array  $experiments running experiments (pg_experiment_running_for_goals())
 * @param array  $buckets experiment id => key
 * @param array  $seen    experiment id => day number
 * @param string $kind
 * @param array  $event
 * @param int    $today day number
 * @return array lines, seen
 */
function pg_experiment_goal_lines(array $experiments, array $buckets, array $seen, $kind, array $event, $today)
{
    $lines = array();

    foreach ($experiments as $experiment) {
        $id = (int) ($experiment['id'] ?? 0);

        if (($id <= 0) || !isset($buckets[$id]) || !isset($experiment['variants'][$buckets[$id]])
            || !pg_experiment_goal_due($seen, $id, $today) || !pg_experiment_goal_matches($experiment, $kind, $event)) {
            continue;
        }

        $lines[] = array($id, (int) $experiment['variants'][$buckets[$id]]['id']);
        $seen[$id] = (int) $today;
    }

    ksort($seen);

    return array('lines' => $lines, 'seen' => $seen);
}

/**
 * The running experiments among some ids whose goal is of a kind, with
 * their variants, as pg_experiment_for_page() gives them.
 *
 * @param array  $ids experiment ids
 * @param string $kind
 * @return array
 */
function pg_experiment_running_for_goals(array $ids, $kind)
{
    $ids = array_values(array_filter(array_map('intval', $ids), function ($id) {
        return $id > 0;
    }));

    if (($ids === array()) || !function_exists('pg_schema_has') || !pg_schema_has('experiments') || !pg_schema_has('experiment_variants')) {
        return array();
    }

    $list = implode(',', $ids);

    $result = db_q(
        "SELECT id, page_id, goal_kind, goal_target
        FROM experiments
        WHERE id IN ($list) AND status = 'running' AND goal_kind = ?",
        array((string) $kind));

    $experiments = array();

    while ($result && ($row = mysqli_fetch_assoc($result))) {
        $row['id'] = (int) $row['id'];
        $row['page_id'] = (int) $row['page_id'];
        $row['variants'] = array();
        $experiments[$row['id']] = $row;
    }

    if ($experiments === array()) {
        return array();
    }

    $result = db_q(
        "SELECT id, experiment_id, draft_page_id, variant_key, weight
        FROM experiment_variants
        WHERE experiment_id IN (" . implode(',', array_keys($experiments)) . ")");

    while ($result && ($row = mysqli_fetch_assoc($result))) {
        if (pg_experiment_key_valid($row['variant_key']) && isset($experiments[(int) $row['experiment_id']])) {
            $experiments[(int) $row['experiment_id']]['variants'][$row['variant_key']] = array(
                'id' => (int) $row['id'],
                'draft_page_id' => (int) $row['draft_page_id'],
                'variant_key' => $row['variant_key'],
                'weight' => (int) $row['weight'],
            );
        }
    }

    return array_values($experiments);
}

/**
 * Counts one goal of a variant into this hour's experiment_hourly row: one
 * INSERT ... ON DUPLICATE KEY UPDATE.
 *
 * @param int $experiment_id
 * @param int $variant_id
 * @return bool
 */
function pg_experiment_count_goal($experiment_id, $variant_id)
{
    $experiment_id = (int) $experiment_id;
    $variant_id = (int) $variant_id;

    if (($experiment_id <= 0) || ($variant_id <= 0) || !pg_schema_has('experiment_hourly')) {
        return false;
    }

    $day = date('Y-m-d');
    $hour = (int) date('G');

    return db_q(
        "INSERT INTO experiment_hourly (id, day, hour, experiment_id, variant_id, views, goals)
        VALUES (?, ?, ?, ?, ?, 0, 1)
        ON DUPLICATE KEY UPDATE goals = goals + 1",
        array(pg_experiment_hourly_key($day, $hour, $experiment_id, $variant_id), $day, $hour, $experiment_id, $variant_id)) !== false;
}

/**
 * An event that may be a goal: counted for every running experiment of that
 * goal kind the visitor's pg_exp cookie holds a bucket of, once a day each,
 * and the day marked in the cookie. Nothing without a bucket, for crawlers
 * and scripts, or for signed-in staff. When the cookie can no longer be sent
 * (headers already out), nothing is counted, so a goal never counts twice.
 *
 * @param string $kind form, order, click, page
 * @param array  $event page_id, experiment_id, name (pg_experiment_goal_matches())
 * @return int the goals counted
 */
function pg_experiment_goal($kind, array $event = array())
{
    $buckets = pg_experiment_bucket_read();

    if (($buckets === array()) || headers_sent()) {
        return 0;
    }

    $context = pg_experiment_request_context('');

    if (!empty($context['bot']) || !empty($context['staff'])) {
        return 0;
    }

    $experiments = pg_experiment_running_for_goals(array_keys($buckets), $kind);

    if ($experiments === array()) {
        return 0;
    }

    $plan = pg_experiment_goal_lines($experiments, $buckets, pg_experiment_goal_seen_read(), $kind, $event, pg_experiment_day_number(date('Y-m-d')));

    if (($plan['lines'] === array()) || pg_experiment_goal_rate_exceeded()) {
        return 0;
    }

    pg_experiment_bucket_write($buckets, null, $plan['seen']);

    foreach ($plan['lines'] as $line) {
        pg_experiment_count_goal($line[0], $line[1]);
    }

    return count($plan['lines']);
}

/**
 * Whether the visitor's address counted more goals than
 * PG_EXPERIMENT_GOAL_RATE_LIMIT in the last minute (the firewall's counter,
 * as the page speed beacon's). The cookie that keeps a goal to once a day is
 * the visitor's own, so a script that rewrites it is held here; asked only
 * when a goal would count.
 *
 * @return bool
 */
function pg_experiment_goal_rate_exceeded()
{
    if (!function_exists('waf_rate_exceeded') || !function_exists('waf_client_ip')) {
        return false;
    }

    $ip = waf_client_ip();

    if (function_exists('waf_ip_subject')) {
        $ip = waf_ip_subject($ip);
    }

    return waf_rate_exceeded($ip, 'exp_goal', PG_EXPERIMENT_GOAL_RATE_LIMIT, 60);
}

/**
 * A click goal's name as an element's data-pg-goal gives it: letters,
 * digits, - and _, at most 64; '' otherwise. Pure.
 *
 * @param mixed $name
 * @return string
 */
function pg_experiment_goal_name($name)
{
    return (is_string($name) && (preg_match('/^[A-Za-z0-9_-]{1,64}$/', $name) === 1)) ? $name : '';
}

/**
 * Puts the click goal's script before the last </body>: a click on an
 * element marked data-pg-goal (or inside one) posts the experiment id and
 * the element's goal name to page_view.php, once per name and page load.
 * The variant is the one the visitor's cookie holds, so the script carries
 * nothing else. Pure.
 *
 * @param string $html
 * @param int    $experiment_id
 * @param string $url page_view.php
 * @return string
 */
function pg_experiment_click_inject($html, $experiment_id, $url)
{
    $html = (string) $html;
    $at = strripos($html, '</body>');
    $experiment_id = (int) $experiment_id;

    if (($experiment_id <= 0) || ($at === false)) {
        return $html;
    }

    $url_js = json_encode((string) $url, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES);

    if (!is_string($url_js)) {
        return $html;
    }

    $script = '<script>(function(){var u=' . $url_js . ',s={};'
        . 'document.addEventListener("click",function(e){try{'
        . 'var t=e.target&&e.target.closest?e.target.closest("[data-pg-goal]"):null;if(!t){return;}'
        . 'var n=String(t.getAttribute("data-pg-goal")||"");if(s[n]){return;}s[n]=1;'
        . 'var d=new FormData();d.append("xg",' . $experiment_id . ');d.append("xn",n);'
        . 'if(navigator.sendBeacon&&navigator.sendBeacon(u,d)){return;}'
        . 'if(window.fetch){fetch(u,{method:"POST",body:d,keepalive:true,credentials:"same-origin"});}'
        . '}catch(x){}},true);})();</script>';

    return substr($html, 0, $at) . $script . substr($html, $at);
}

/**
 * get_page.php: the click goal's script for the experiment this request
 * serves, when its goal is a click; the page as it is otherwise. The stored
 * copy of the page cache keeps the script.
 *
 * @param string $html
 * @return string
 */
function pg_experiment_click_script_for_served($html)
{
    $served = pg_experiment_served();

    if (!is_array($served)) {
        return $html;
    }

    $experiment = pg_experiment_for_page($served['page_id']);

    if (!is_array($experiment) || ((string) ($experiment['goal_kind'] ?? '') !== 'click') || ((int) $experiment['id'] !== (int) $served['experiment_id'])) {
        return $html;
    }

    return pg_experiment_click_inject($html, $experiment['id'],
        (defined('PATH') ? PATH : '/') . (defined('SOFTWARE_DIRECTORY') ? SOFTWARE_DIRECTORY : 'pinegrap') . '/page_view.php');
}
