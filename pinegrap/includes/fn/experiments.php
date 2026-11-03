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

        if (count($parts) !== 2 || (preg_match('/^[1-9][0-9]{0,9}$/', $parts[0]) !== 1)) {
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
 * lowest ids (the oldest experiments) drop. Pure.
 *
 * @param array $pairs
 * @return string
 */
function pg_experiment_bucket_format(array $pairs)
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
        $out[] = $id . ':' . $key;
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
 * @param array $pairs experiment id => key
 * @return bool false when headers are already sent
 */
function pg_experiment_bucket_write(array $pairs)
{
    $value = pg_experiment_bucket_format($pairs);

    if (headers_sent()) {
        return false;
    }

    $secure = function_exists('pg_request_is_https') && pg_request_is_https();
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

    return true;
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

