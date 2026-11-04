<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// A module of functions.php, also loaded by page_cache.php for the router
// (which runs without functions.php): audience conditions, the facts of the
// visitor an element of a page outside any system widget may be shown by.
//
// They are page conditions (page_facts.php): the node is drawn between the
// pg-pf markers and every request decides them. What they change is how the
// answer may be kept:
//   - country and device: the page cache keeps one copy per value (the key
//     grows, pg_audience_cache_key()); no shared cache keeps it;
//   - returning, utm_source, utm_campaign, experiment: the answer is drawn
//     for this visitor (pg_page_cache_personal('audience'));
//   - member and group: read for a signed-in visitor only, whose answers no
//     cache keeps; a signed-out visitor is neither, so a kept copy is right
//     for everyone it is served to (as __visit_signed_in).
//
// Tokens: __audience_returning, __audience_country (two capital letters),
// __audience_device (phone, tablet, desktop), __audience_utm_source,
// __audience_utm_campaign, __audience_member ('1' or ''),
// __audience_group_<id> ('1' when the visitor's contact is in the group),
// __audience_experiment_<id> (the variant key of the visitor's bucket).

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// The first-party cookie that tells a returning visitor: the time of the
// first page drawn for the browser. Strictly functional, listed in
// pg_consent_catalogue().
define('PG_AUDIENCE_RETURNING_COOKIE', 'pg_ret');

// How long the returning cookie lives, in days.
define('PG_AUDIENCE_RETURNING_DAYS', 365);

// A browser first seen less than this many seconds ago is on its first
// visit still (a visit ends after half an hour without a page, as visitor
// tracking counts them).
define('PG_AUDIENCE_VISIT_GAP', 1800);

/**
 * The audience tokens without a parameter, in the order the editor lists
 * them.
 *
 * @return array
 */
function pg_audience_tokens()
{
    return array(
        '__audience_returning', '__audience_country', '__audience_device',
        '__audience_utm_source', '__audience_utm_campaign', '__audience_member',
    );
}

/**
 * The kind an audience token reads: returning, country, device, utm_source,
 * utm_campaign, member, group, experiment; '' for any other token. A group
 * or an experiment is named by its id: __audience_group_12. Pure.
 *
 * @param string $token
 * @return string
 */
function pg_audience_token_kind($token)
{
    $token = strtolower((string) $token);

    if (in_array($token, pg_audience_tokens(), true)) {
        return substr($token, strlen('__audience_'));
    }

    if (preg_match('/^__audience_(group|experiment)_[1-9][0-9]{0,9}$/', $token, $m)) {
        return $m[1];
    }

    return '';
}

/**
 * The id of a group or experiment token, 0 for any other. Pure.
 *
 * @param string $token
 * @return int
 */
function pg_audience_token_id($token)
{
    return preg_match('/^__audience_(?:group|experiment)_([1-9][0-9]{0,9})$/', strtolower((string) $token), $m) ? (int) $m[1] : 0;
}

/**
 * How a page whose conditions read the audience kinds $kinds may be kept:
 * vary (the kinds the page cache keys its copies by, country before device)
 * and personal (the answer is drawn for this visitor alone). Pure.
 *
 * @param array $kinds pg_audience_token_kind() values
 * @return array vary => list, personal => bool
 */
function pg_audience_cache_rule(array $kinds)
{
    $vary = array();
    $personal = false;

    foreach (array('country', 'device') as $kind) {
        if (in_array($kind, $kinds, true)) {
            $vary[] = $kind;
        }
    }

    foreach (array('returning', 'utm_source', 'utm_campaign', 'experiment') as $kind) {
        if (in_array($kind, $kinds, true)) {
            $personal = true;
        }
    }

    return array('vary' => $vary, 'personal' => $personal);
}

/**
 * The device a user agent is: 'tablet', 'phone' or 'desktop'. A tablet is
 * named first: an Android tablet says Android without Mobile, an iPad says
 * iPad. Pure.
 *
 * @param string $user_agent
 * @return string
 */
function pg_audience_device($user_agent)
{
    $ua = (string) $user_agent;

    if ($ua === '') {
        return 'desktop';
    }

    if (preg_match('/ipad|tablet|playbook|silk|kindle|nexus (7|9|10)|sm-t[0-9]|(android(?!.*mobile))/i', $ua)) {
        return 'tablet';
    }

    if (preg_match('/mobile|iphone|ipod|android|blackberry|bb10|iemobile|windows phone|opera mini|opera mobi|webos|fennec/i', $ua)) {
        return 'phone';
    }

    return 'desktop';
}

/**
 * A country code as the audience reads it: two capital letters, or ''.
 * Pure.
 *
 * @param string $code
 * @return string
 */
function pg_audience_country_value($code)
{
    $code = strtoupper(trim((string) $code));

    return preg_match('/^[A-Z][A-Z0-9]$/', $code) ? $code : '';
}

/**
 * Whether the returning cookie's value says the browser was first seen
 * before this visit: '1' or ''. A value from the future, or one that is no
 * time, reads as a first visit. Pure.
 *
 * @param mixed $value the cookie
 * @param int   $now
 * @return string
 */
function pg_audience_returning($value, $now)
{
    if (!is_string($value) || !preg_match('/^[1-9][0-9]{0,10}$/', $value)) {
        return '';
    }

    $first = (int) $value;

    return (($first <= (int) $now) && ($first <= (int) $now - PG_AUDIENCE_VISIT_GAP)) ? '1' : '';
}

/**
 * Sends the returning cookie when the browser does not have one yet: the
 * time now, for a year, path /, HttpOnly, SameSite Lax, Secure over https.
 * Once a request. The page cache leaves it out of the cookies that make an
 * answer personal; a cacheable answer drops it, so a browser that only
 * ever gets kept copies is told on the first page drawn for it.
 *
 * @return void
 */
function pg_audience_returning_touch()
{
    static $done = false;

    if ($done || headers_sent()) {
        return;
    }

    $done = true;

    if (isset($_COOKIE[PG_AUDIENCE_RETURNING_COOKIE]) && is_string($_COOKIE[PG_AUDIENCE_RETURNING_COOKIE])
        && preg_match('/^[1-9][0-9]{0,10}$/', $_COOKIE[PG_AUDIENCE_RETURNING_COOKIE])) {
        return;
    }

    $secure = function_exists('pg_request_is_https') && pg_request_is_https();
    $expires = time() + PG_AUDIENCE_RETURNING_DAYS * 86400;
    $value = (string) time();

    if (PHP_VERSION_ID >= 70300) {
        setcookie(PG_AUDIENCE_RETURNING_COOKIE, $value, array(
            'expires' => $expires,
            'path' => '/',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ));
    } else {
        // Before 7.3 setcookie() has no SameSite parameter; the attribute
        // rides on the path, which is printed as it is.
        setcookie(PG_AUDIENCE_RETURNING_COOKIE, $value, $expires, '/; samesite=Lax', '', $secure, true);
    }
}

/**
 * A UTM value of this visit: the request's own parameter, else the one the
 * visit arrived with (the session, as visitor tracking keeps it). Plain
 * text, at most 100 characters.
 *
 * @param string $name utm_source or utm_campaign
 * @param array  $get     $_GET
 * @param array  $session $_SESSION['software']
 * @return string
 */
function pg_audience_utm($name, array $get, array $session)
{
    $value = '';

    if (isset($get[$name]) && is_string($get[$name]) && (trim($get[$name]) !== '')) {
        $value = $get[$name];
    } elseif (isset($session[$name]) && is_scalar($session[$name])) {
        $value = (string) $session[$name];
    }

    $value = trim(preg_replace('/[\x00-\x1F\x7F]/', '', $value));

    return function_exists('mb_substr') ? mb_substr($value, 0, 100) : substr($value, 0, 100);
}

/**
 * The values the page cache keys a copy by, for the kinds in $vary, from
 * the request: the country by includes/geo.php (as the language redirect
 * reads it), the device by the user agent. Runs in the router too.
 *
 * @param array $vary   pg_audience_cache_rule()'s vary
 * @param array $server $_SERVER
 * @return array kind => value
 */
function pg_audience_request_values(array $vary, array $server)
{
    $out = array();

    foreach ($vary as $kind) {
        if ($kind === 'country') {
            $out['country'] = pg_audience_country_value(function_exists('pg_geo_country_code') ? pg_geo_country_code() : '');
        } elseif ($kind === 'device') {
            $out['device'] = pg_audience_device(isset($server['HTTP_USER_AGENT']) ? (string) $server['HTTP_USER_AGENT'] : '');
        }
    }

    return $out;
}

/**
 * The kinds a stored copy is keyed by, cleaned: country and device only,
 * in that order. Pure.
 *
 * @param mixed $vary
 * @return array
 */
function pg_audience_vary_clean($vary)
{
    if (!is_array($vary)) {
        return array();
    }

    return array_values(array_intersect(array('country', 'device'), $vary));
}

/**
 * The name of an audience copy: the address's key joined with each kind and
 * its value. Pure.
 *
 * @param string $key    pg_page_cache_key()
 * @param array  $vary   the kinds
 * @param array  $values kind => value
 * @return string 40 hex characters
 */
function pg_audience_cache_key($key, array $vary, array $values)
{
    $parts = array((string) $key);

    foreach (pg_audience_vary_clean($vary) as $kind) {
        $parts[] = $kind . '=' . (isset($values[$kind]) ? (string) $values[$kind] : '');
    }

    return sha1(implode("\naud:", $parts));
}

/**
 * The kinds the drawn page varies by, recorded while its conditions are
 * decided (pg_audience_note()); without an argument, the list so far.
 *
 * @param array|null $kinds
 * @return array
 */
function pg_audience_cache_vary($kinds = null)
{
    static $vary = array();

    if (is_array($kinds)) {
        $vary = pg_audience_vary_clean(array_merge($vary, $kinds));
    }

    return $vary;
}

/**
 * The audience tokens the page conditions in $html read, every span counted,
 * also one a condition around it cuts: the copy a page is kept as must not
 * depend on which spans this visit reached. Pure.
 *
 * @param string $html
 * @return array
 */
function pg_audience_html_tokens($html)
{
    $html = (string) $html;

    if ((strpos($html, '<!--pg-pf:') === false)
        || !preg_match_all('/<!--pg-pf:[0-9a-f]{12}:([A-Za-z0-9_-]+)-->/', $html, $m)) {
        return array();
    }

    $tokens = array();

    foreach (array_unique($m[1]) as $spec) {
        $b64 = strtr($spec, '-_', '+/');
        $pad = strlen($b64) % 4;

        if ($pad) {
            $b64 .= str_repeat('=', 4 - $pad);
        }

        $json = base64_decode($b64, true);
        $data = ($json === false) ? null : json_decode($json, true);

        if (!is_array($data) || !isset($data['c']) || !is_array($data['c'])) {
            continue;
        }

        foreach ($data['c'] as $test) {
            $token = (is_array($test) && isset($test[0]) && is_string($test[0])) ? strtolower($test[0]) : '';

            if ((pg_audience_token_kind($token) !== '') && !in_array($token, $tokens, true)) {
                $tokens[] = $token;
            }
        }
    }

    return $tokens;
}

/**
 * The kinds a list of audience tokens reads. Pure.
 *
 * @param array $tokens
 * @return array
 */
function pg_audience_token_kinds(array $tokens)
{
    $kinds = array();

    foreach ($tokens as $token) {
        $kind = pg_audience_token_kind($token);

        if (($kind !== '') && !in_array($kind, $kinds, true)) {
            $kinds[] = $kind;
        }
    }

    return $kinds;
}

/**
 * The audience values the page conditions in $html read, token => value,
 * and, for a page drawn for a visit, how the answer may be kept
 * (pg_audience_note()).
 *
 * @param string $html
 * @param bool   $background the page is drawn for no visit
 * @return array
 */
function pg_audience_values_for($html, $background)
{
    $tokens = pg_audience_html_tokens($html);
    $values = array();

    foreach ($tokens as $token) {
        $values[$token] = pg_audience_value($token, $background);
    }

    if ($tokens && !$background) {
        pg_audience_note(pg_audience_token_kinds($tokens));
    }

    return $values;
}

/**
 * Records how the page drawn now may be kept, from the audience kinds its
 * conditions read: the kinds it varies by for the page cache, and a
 * personal answer.
 *
 * @param array $kinds
 * @return void
 */
function pg_audience_note(array $kinds)
{
    if (!$kinds) {
        return;
    }

    $rule = pg_audience_cache_rule($kinds);

    if ($rule['vary']) {
        pg_audience_cache_vary($rule['vary']);
    }

    if ($rule['personal'] && function_exists('pg_page_cache_personal')) {
        pg_page_cache_personal('audience');
    }
}

/**
 * The value of an audience token for this visit; '' for a page drawn for
 * no visit (an e-mail, the search index, the SEO score) and for any other
 * token. Read once per token and request.
 *
 * @param string $token
 * @param bool   $background the page is drawn for no visit
 * @return string
 */
function pg_audience_value($token, $background = false)
{
    static $cache = array();

    $token = strtolower((string) $token);
    $kind = pg_audience_token_kind($token);

    if (($kind === '') || $background) {
        return '';
    }

    if (array_key_exists($token, $cache)) {
        return $cache[$token];
    }

    $signed_in = defined('USER_LOGGED_IN') && (USER_LOGGED_IN === true);
    $session = (isset($_SESSION['software']) && is_array($_SESSION['software'])) ? $_SESSION['software'] : array();
    $value = '';

    switch ($kind) {
        case 'returning':
            $value = pg_audience_returning(isset($_COOKIE[PG_AUDIENCE_RETURNING_COOKIE]) ? $_COOKIE[PG_AUDIENCE_RETURNING_COOKIE] : null, time());
            break;
        case 'country':
        case 'device':
            $values = pg_audience_request_values(array($kind), $_SERVER);
            $value = $values[$kind];
            break;
        case 'utm_source':
        case 'utm_campaign':
            $value = pg_audience_utm($kind, $_GET, $session);
            break;
        case 'member':
            $value = ($signed_in && defined('USER_MEMBER') && USER_MEMBER) ? '1' : '';
            break;
        case 'group':
            $contact_id = ($signed_in && defined('USER_CONTACT_ID')) ? (int) USER_CONTACT_ID : 0;
            if (($contact_id > 0) && function_exists('db_value')) {
                $value = db_value("SELECT 1 FROM contacts_contact_groups_xref WHERE contact_id = '" . $contact_id
                    . "' AND contact_group_id = '" . pg_audience_token_id($token) . "' LIMIT 1") ? '1' : '';
            }
            break;
        case 'experiment':
            if (function_exists('pg_experiment_bucket_read')) {
                $buckets = pg_experiment_bucket_read();
                $id = pg_audience_token_id($token);
                $value = isset($buckets[$id]) ? (string) $buckets[$id] : '';
            }
            break;
    }

    $cache[$token] = $value;

    return $value;
}

/**
 * The contact groups the editor lists (formMeta.contactGroups): id, name and
 * kind, 'dynamic' for a group filled by rules, else 'static' (also before
 * the upgrade added the column). The group token reads either kind the same
 * way: a rule-made membership is an xref row like a hand-made one. Pure.
 *
 * @param array $rows  contact_groups rows: id, name, kind (optional)
 * @return array list of id, name, kind
 */
function pg_audience_group_options($rows)
{
    $out = array();

    foreach ((array) $rows as $row) {
        if (!is_array($row) || ((int) ($row['id'] ?? 0) <= 0)) {
            continue;
        }

        $out[] = array(
            'id' => (int) $row['id'],
            'name' => (string) ($row['name'] ?? ''),
            'kind' => ((string) ($row['kind'] ?? '') === 'dynamic') ? 'dynamic' : 'static',
        );
    }

    return $out;
}

/**
 * The experiments the editor offers a condition on: every one not ended,
 * with its variant keys; empty before the upgrade made the tables.
 *
 * @return array list of id, name, variants (keys)
 */
function pg_audience_experiment_options()
{
    if (!function_exists('pg_schema_has') || !pg_schema_has('experiments') || !pg_schema_has('experiment_variants')) {
        return array();
    }

    $out = array();

    foreach ((array) db_items(
        "SELECT experiments.id, experiments.name, experiment_variants.variant_key
        FROM experiments
        INNER JOIN experiment_variants ON experiment_variants.experiment_id = experiments.id
        WHERE experiments.status <> 'ended'
        ORDER BY experiments.id, experiment_variants.variant_key") as $row) {
        $id = (int) $row['id'];

        if (!isset($out[$id])) {
            $out[$id] = array('id' => $id, 'name' => (string) $row['name'], 'variants' => array());
        }

        $out[$id]['variants'][] = (string) $row['variant_key'];
    }

    return array_values($out);
}
