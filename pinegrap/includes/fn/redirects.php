<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The redirect manager: rules in the redirects table (2026.11.0, 11.0.11)
 * that send an old address on to where its content is now, with a 301
 * (moved for good) or a 302 (moved for now).
 *
 * A rule answers only an address that turned out to be nothing: router.php
 * serves files, robots.txt, the sitemap and short links itself and hands
 * everything else to get_page.php, defining PG_REDIRECT_LOOKUP first. Only
 * once get_page.php knows that no page answers (output_error() and error()
 * with a 404, or a designer widget that finds no product or record for the
 * address, pg_sw_item_missing()) is pg_redirect_not_found() asked; it sends
 * the visitor on and ends the request, or returns and the 404 goes out as
 * before. A live page therefore always wins over a rule written for its
 * address, and a page that is renamed back takes its address back.
 *
 * Addresses are compared as paths below the site's base path (PATH): decoded,
 * one leading slash, single slashes, no trailing slash, no query string, and
 * without regard to case (pg_redirect_normalize_path(), pg_redirect_key()).
 * The language prefix of a translated page is part of the path, so /en/old
 * and /old are two addresses with a rule each. The exact rule for an address
 * is found first; a rule whose address holds a * is a pattern, tried after
 * the exact rules, the most specific (longest) first, and its target reuses
 * what each * matched as $1, $2. The visitor's query string travels on to the
 * target, so campaign parameters survive the move.
 *
 * A rule is saved only through pg_redirect_save(), which refuses an address
 * or a target it cannot use, a second rule for the same address, a rule that
 * sends an address to itself and one that closes a loop with the rules
 * already there (pg_redirect_rule_problem()). The screens are
 * view_redirects.php (list, delete, CSV export), edit_redirect.php (one rule)
 * and import_redirects.php (CSV import); all are for managers and above.
 *
 * not_found_log, created by the same step, is for the log of addresses that
 * answered 404; nothing writes it yet.
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
 * Hops followed through other rules when a rule is checked for a loop; a
 * longer chain is refused as well, since every hop is a round trip for the
 * visitor and search engines stop following after a few.
 */
define('PG_REDIRECT_MAX_HOPS', 10);

/**
 * Longest address and target a rule keeps (the column widths).
 */
define('PG_REDIRECT_FROM_MAX', 1024);
define('PG_REDIRECT_TO_MAX', 2048);

/**
 * Pattern rules read on one request: they are tried one by one in PHP.
 */
define('PG_REDIRECT_PATTERN_LIMIT', 5000);

/**
 * True when the redirect tables are in the database (2026.11.0, 11.0.11).
 *
 * @return bool
 */
function pg_redirects_ready()
{
    return pg_schema_has('redirects', 'from_hash');
}

/**
 * The status codes a rule can answer with.
 *
 * @return int[]
 */
function pg_redirect_codes()
{
    return array(301, 302);
}

/**
 * The name of a status code as the screens show it.
 *
 * @param int $code
 * @return string
 */
function pg_redirect_code_label($code)
{
    return ((int) $code === 302) ? lang('302 Temporary') : lang('301 Permanent');
}

/**
 * An address as rules compare it.
 *
 * A full address (https://old.example.com/a/b) gives its path; the site's base
 * path is cut off the front when it is there. Only http:// and https:// make
 * a full address: a request for //a/b is the path /a/b, not the host a. The
 * query string and the fragment are dropped, the path is decoded (left as it
 * was when decoding does not give UTF-8), runs of slashes become one, a
 * trailing slash goes, and a leading one is added. Runs of * become one. ''
 * for an empty address.
 *
 * @param mixed  $address
 * @param string $base_path The site's base path (PATH), '/' for a site at the root
 * @return string
 */
function pg_redirect_normalize_path($address, $base_path = '/')
{
    if (!is_scalar($address)) {
        return '';
    }

    $path = trim((string) $address);

    if ($path === '') {
        return '';
    }

    if (preg_match('#^https?://#i', $path) === 1) {
        $parsed = @parse_url($path);
        $path = (is_array($parsed) && isset($parsed['path'])) ? (string) $parsed['path'] : '/';
    } else {
        $cut = strcspn($path, '?#');
        $path = substr($path, 0, $cut);
    }

    $decoded = rawurldecode($path);

    if (mb_check_encoding($decoded, 'UTF-8')) {
        $path = $decoded;
    }

    $path = (string) preg_replace('#/{2,}#', '/', $path);
    $path = (string) preg_replace('#\*{2,}#', '*', $path);

    if (substr($path, 0, 1) !== '/') {
        $path = '/' . $path;
    }

    $base = '/' . trim((string) $base_path, '/');

    if (($base !== '/') && (stripos($path . '/', $base . '/') === 0)) {
        $path = substr($path, strlen($base));

        if ($path === '') {
            $path = '/';
        }
    }

    if ($path !== '/') {
        $path = rtrim($path, '/');

        if ($path === '') {
            $path = '/';
        }
    }

    return $path;
}

/**
 * The address a request asked for, as rules compare it.
 *
 * @param string $request_url The request line's target (REQUEST_URL): path and query string
 * @param string $base_path   The site's base path (PATH)
 * @return string
 */
function pg_redirect_request_path($request_url, $base_path = '/')
{
    $url = (string) $request_url;
    $url = substr($url, 0, strcspn($url, '?#'));
    $base = (string) $base_path;

    if (($base !== '') && ($base !== '/') && (strpos($url, $base) === 0)) {
        $url = '/' . substr($url, strlen($base));
    }

    return pg_redirect_normalize_path($url, '/');
}

/**
 * The query string of a request target, without its question mark.
 *
 * @param string $request_url
 * @return string
 */
function pg_redirect_request_query($request_url)
{
    $url = (string) $request_url;
    $hash = strpos($url, '#');

    if ($hash !== false) {
        $url = substr($url, 0, $hash);
    }

    $mark = strpos($url, '?');

    return ($mark === false) ? '' : (string) substr($url, $mark + 1);
}

/**
 * The key of an address: the same for every way of writing it that rules
 * treat as one (case).
 *
 * @param string $path A normalized path
 * @return string
 */
function pg_redirect_key($path)
{
    return sha1(mb_strtolower((string) $path, 'UTF-8'));
}

/**
 * True when an address holds a wildcard.
 *
 * @param string $path
 * @return bool
 */
function pg_redirect_is_pattern($path)
{
    return (strpos((string) $path, '*') !== false);
}

/**
 * True for a target that names a scheme and a host.
 *
 * @param string $url
 * @return bool
 */
function pg_redirect_is_absolute($url)
{
    return (preg_match('#^https?://[^/?\#\s]+#i', (string) $url) === 1);
}

/**
 * The regular expression a pattern address matches with: anchored, each *
 * capturing as little as it can (the anchors still make it reach as far as it
 * must), case-insensitive.
 *
 * @param string $pattern A normalized address with at least one *
 * @return string
 */
function pg_redirect_pattern_regex($pattern)
{
    $quoted = array();

    foreach (explode('*', (string) $pattern) as $part) {
        $quoted[] = preg_quote($part, '#');
    }

    return '#^' . implode('(.*?)', $quoted) . '$#iu';
}

/**
 * What each * of a pattern matched in an address, or null when the pattern
 * does not match it.
 *
 * @param string $pattern
 * @param string $path    A normalized path
 * @return string[]|null
 */
function pg_redirect_pattern_captures($pattern, $path)
{
    if (@preg_match(pg_redirect_pattern_regex($pattern), (string) $path, $matches) !== 1) {
        return null;
    }

    array_shift($matches);

    return array_map('strval', $matches);
}

/**
 * How specific a pattern is: the characters it names outside its wildcards.
 *
 * @param string $pattern
 * @return int
 */
function pg_redirect_pattern_weight($pattern)
{
    return mb_strlen(str_replace('*', '', (string) $pattern), 'UTF-8');
}

/**
 * Pattern rules in the order they are tried: the most specific first, then
 * the oldest.
 *
 * @param array[] $rules Rows with from_path and id
 * @return array[]
 */
function pg_redirect_sort_patterns($rules)
{
    $rules = array_values((array) $rules);

    usort($rules, function ($a, $b) {
        $weight = pg_redirect_pattern_weight($b['from_path']) - pg_redirect_pattern_weight($a['from_path']);

        if ($weight !== 0) {
            return $weight;
        }

        return ((int) $a['id']) - ((int) $b['id']);
    });

    return $rules;
}

/**
 * A rule's target with what the wildcards matched written into $1 to $9.
 *
 * Encoded, each matched piece goes in one segment at a time, so a decoded ?
 * or # in the old address cannot start a query string or a fragment in the
 * new one. Unencoded is for comparing the target with other addresses.
 *
 * @param string   $target
 * @param string[] $captures
 * @param bool     $encode
 * @return string
 */
function pg_redirect_fill_target($target, $captures, $encode = true)
{
    $captures = array_values((array) $captures);

    return (string) preg_replace_callback('/\$([1-9])/', function ($match) use ($captures, $encode) {
        $index = ((int) $match[1]) - 1;

        if (!isset($captures[$index])) {
            return '';
        }

        if (!$encode) {
            return $captures[$index];
        }

        return implode('/', array_map('rawurlencode', explode('/', $captures[$index])));
    }, (string) $target);
}

/**
 * The highest $n a target refers to (0 for none).
 *
 * @param string $target
 * @return int
 */
function pg_redirect_target_references($target)
{
    $highest = 0;

    if (preg_match_all('/\$([1-9])/', (string) $target, $matches)) {
        foreach ($matches[1] as $number) {
            $highest = max($highest, (int) $number);
        }
    }

    return $highest;
}

/**
 * The key of a rule row: its stored from_hash, or the key of its address.
 *
 * @param array $rule
 * @return string
 */
function pg_redirect_rule_key($rule)
{
    if (isset($rule['from_hash']) && (preg_match('/^[0-9a-f]{40}$/', (string) $rule['from_hash']) === 1)) {
        return (string) $rule['from_hash'];
    }

    return pg_redirect_key(pg_redirect_normalize_path(isset($rule['from_path']) ? $rule['from_path'] : '', '/'));
}

/**
 * Rules arranged for lookups: exact rules by key, patterns in the order they
 * are tried (pg_redirect_sort_patterns()).
 *
 * @param array[] $rules Rows with id, from_path, to_url, code, is_pattern
 * @return array array('exact' => array(key => row), 'patterns' => array of rows)
 */
function pg_redirect_index($rules)
{
    $index = array('exact' => array(), 'patterns' => array());

    foreach ((array) $rules as $rule) {
        if (!empty($rule['is_pattern'])) {
            $index['patterns'][] = $rule;
        } else {
            $index['exact'][pg_redirect_rule_key($rule)] = $rule;
        }
    }

    $index['patterns'] = pg_redirect_sort_patterns($index['patterns']);

    return $index;
}

/**
 * The rule that answers an address, and its target, from pg_redirect_index().
 *
 * The exact rule for the address first, then the first pattern that matches
 * it.
 *
 * @param string $path   A normalized path
 * @param array  $index
 * @param bool   $encode Whether the matched pieces are encoded into the target
 * @return array|null array('rule' => row, 'target' => string)
 */
function pg_redirect_resolve_in($path, $index, $encode = true)
{
    $key = pg_redirect_key($path);

    if (isset($index['exact'][$key])) {
        return array('rule' => $index['exact'][$key], 'target' => (string) $index['exact'][$key]['to_url']);
    }

    foreach ($index['patterns'] as $rule) {
        $captures = pg_redirect_pattern_captures($rule['from_path'], $path);

        if ($captures !== null) {
            return array('rule' => $rule, 'target' => pg_redirect_fill_target($rule['to_url'], $captures, $encode));
        }
    }

    return null;
}

/**
 * The rule that answers an address among a set of rules, and its target.
 *
 * @param string  $path   A normalized path
 * @param array[] $rules  Rows with id, from_path, to_url, code, is_pattern
 * @param bool    $encode Whether the matched pieces are encoded into the target
 * @return array|null array('rule' => row, 'target' => string)
 */
function pg_redirect_resolve($path, $rules, $encode = true)
{
    return pg_redirect_resolve_in($path, pg_redirect_index($rules), $encode);
}

/**
 * Rules prepared for checking new ones against them: their keys and the
 * lookup index, built once. An import checks every line against the same
 * rules and keeps the set current with pg_redirect_prepared_put(), so a long
 * file is not a full pass over every rule per line.
 *
 * @param array[] $rules Rows with id, from_path, to_url, code, is_pattern (from_hash when read)
 * @return array
 */
function pg_redirect_prepare($rules)
{
    $prepared = array('prepared' => true, 'by_id' => array(), 'keys' => array(), 'index' => array('exact' => array(), 'patterns' => array()));

    foreach ((array) $rules as $rule) {
        $prepared['by_id'][(int) $rule['id']] = $rule;
        $prepared['keys'][pg_redirect_rule_key($rule)] = (int) $rule['id'];
    }

    $prepared['index'] = pg_redirect_index($prepared['by_id']);

    return $prepared;
}

/**
 * Adds a stored rule to a prepared set, or puts it in place of its old self.
 *
 * @param array $prepared pg_redirect_prepare()
 * @param array $rule     The rule as stored (with its id)
 * @return void
 */
function pg_redirect_prepared_put(&$prepared, $rule)
{
    $id = (int) $rule['id'];

    pg_redirect_index_remove($prepared['index'], isset($prepared['by_id'][$id]) ? $prepared['by_id'][$id] : null);

    if (isset($prepared['by_id'][$id])) {
        unset($prepared['keys'][pg_redirect_rule_key($prepared['by_id'][$id])]);
    }

    $prepared['by_id'][$id] = $rule;
    $prepared['keys'][pg_redirect_rule_key($rule)] = $id;

    pg_redirect_index_add($prepared['index'], $rule);
}

/**
 * Takes a rule out of a lookup index (nothing for null).
 *
 * @param array      $index pg_redirect_index()
 * @param array|null $rule
 * @return void
 */
function pg_redirect_index_remove(&$index, $rule)
{
    if (!is_array($rule)) {
        return;
    }

    if (empty($rule['is_pattern'])) {
        $key = pg_redirect_rule_key($rule);

        if (isset($index['exact'][$key]) && ((int) $index['exact'][$key]['id'] === (int) $rule['id'])) {
            unset($index['exact'][$key]);
        }

        return;
    }

    foreach ($index['patterns'] as $position => $pattern) {
        if ((int) $pattern['id'] === (int) $rule['id']) {
            array_splice($index['patterns'], $position, 1);
            return;
        }
    }
}

/**
 * Puts a rule into a lookup index.
 *
 * @param array $index pg_redirect_index()
 * @param array $rule
 * @return void
 */
function pg_redirect_index_add(&$index, $rule)
{
    if (empty($rule['is_pattern'])) {
        $index['exact'][pg_redirect_rule_key($rule)] = $rule;
        return;
    }

    $index['patterns'][] = $rule;
    $index['patterns'] = pg_redirect_sort_patterns($index['patterns']);
}

/**
 * The path of a target on this site, or null when it leads elsewhere.
 *
 * @param string $target
 * @param string $site_host The site's host name (HOSTNAME_SETTING)
 * @param string $base_path The site's base path (PATH)
 * @return string|null A normalized path
 */
function pg_redirect_internal_path($target, $site_host = '', $base_path = '/')
{
    $target = (string) $target;

    if (pg_redirect_is_absolute($target)) {
        $host = strtolower((string) @parse_url($target, PHP_URL_HOST));
        $site = strtolower(preg_replace('/:\d+$/', '', (string) $site_host));

        if (($host === '') || ($site === '') || (preg_replace('/^www\./', '', $host) !== preg_replace('/^www\./', '', $site))) {
            return null;
        }

        return pg_redirect_normalize_path($target, $base_path);
    }

    return pg_redirect_normalize_path($target, '/');
}

/**
 * The address the visitor is sent to.
 *
 * A path of this site is put under the site's address and base path; the
 * visitor's query string goes on after the target's own, before its
 * fragment. Characters a Location header cannot carry (spaces, letters
 * outside ASCII, quotes, angle brackets) are percent-encoded; what is
 * already encoded stays as it is.
 *
 * @param string $target    The rule's target, wildcards filled
 * @param string $query     The visitor's query string, without '?'
 * @param string $origin    Scheme and host of the site ("https://example.com")
 * @param string $base_path The site's base path (PATH)
 * @return string
 */
function pg_redirect_location($target, $query, $origin, $base_path = '/')
{
    $target = (string) $target;
    $fragment = '';
    $hash = strpos($target, '#');

    if ($hash !== false) {
        $fragment = substr($target, $hash);
        $target = substr($target, 0, $hash);
    }

    if ((string) $query !== '') {
        $target .= ((strpos($target, '?') === false) ? '?' : '&') . $query;
    }

    $target .= $fragment;

    if (!pg_redirect_is_absolute($target)) {
        $target = rtrim((string) $origin, '/') . rtrim('/' . trim((string) $base_path, '/'), '/') . '/' . ltrim($target, '/');
    }

    return pg_redirect_encode_url($target);
}

/**
 * Percent-encodes what a URL cannot carry as it is.
 *
 * @param string $url
 * @return string
 */
function pg_redirect_encode_url($url)
{
    return (string) preg_replace_callback('/[^\x21-\x7E]|["<>\\\\^`{|}]/', function ($match) {
        return rawurlencode($match[0]);
    }, (string) $url);
}

/**
 * What is wrong with a rule, or null when it can be saved.
 *
 * The address must be one a visitor can ask for and that can turn out to be
 * missing (not the home page), the target a path of this site or an http(s)
 * address, the code 301 or 302. A target may use only as many $n as the
 * address has wildcards. A rule may not send its own address to itself, and,
 * followed through the rules already there (every hop treated as an address
 * that may be missing too), may not come back to its own address or go on for
 * more than PG_REDIRECT_MAX_HOPS hops. A second rule for the same address is
 * refused here when the list holds it; the unique key refuses it otherwise.
 *
 * @param array   $rule      from_path, to_url, code; id when it replaces a stored rule
 * @param array   $rules     The stored rules (id, from_path, to_url, code, is_pattern), or pg_redirect_prepare() of them
 * @param string  $site_host The site's host name, to tell its own full addresses
 * @param string  $base_path The site's base path
 * @return array|null array('field' => from_path|to_url|code, 'code' => string, 'message' => string)
 */
function pg_redirect_rule_problem($rule, $rules = array(), $site_host = '', $base_path = '/')
{
    $from_raw = isset($rule['from_path']) && is_scalar($rule['from_path']) ? trim((string) $rule['from_path']) : '';
    $to = isset($rule['to_url']) && is_scalar($rule['to_url']) ? trim((string) $rule['to_url']) : '';
    $code = isset($rule['code']) && is_scalar($rule['code']) ? (int) $rule['code'] : 0;
    $id = isset($rule['id']) ? (int) $rule['id'] : 0;

    $problem = function ($field, $code, $message) {
        return array('field' => $field, 'code' => $code, 'message' => $message);
    };

    if ($from_raw === '') {
        return $problem('from_path', 'from_required', lang('Enter the old address to redirect.'));
    }

    if (preg_match('/[\x00-\x1F\x7F]/', $from_raw) === 1) {
        return $problem('from_path', 'from_invalid', lang('The old address cannot contain line breaks or control characters.'));
    }

    if (strpos($from_raw, '?') !== false) {
        return $problem('from_path', 'from_query', lang('The old address is matched without its query string; leave out the part from the question mark on.'));
    }

    $from = pg_redirect_normalize_path($from_raw, $base_path);

    if (mb_strlen($from, 'UTF-8') > PG_REDIRECT_FROM_MAX) {
        return $problem('from_path', 'from_long', lang(array('string' => 'The old address can be at most {var:1} characters long.', 'vars' => array(PG_REDIRECT_FROM_MAX))));
    }

    if ($from === '/') {
        return $problem('from_path', 'from_home', lang('The home page always answers, so its address cannot be redirected.'));
    }

    $is_pattern = pg_redirect_is_pattern($from);

    if ($is_pattern && (substr_count($from, '*') > 9)) {
        return $problem('from_path', 'from_wildcards', lang('An address can have at most 9 wildcards (*).'));
    }

    if ($to === '') {
        return $problem('to_url', 'to_required', lang('Enter where the address goes now.'));
    }

    if (preg_match('/[\x00-\x1F\x7F]/', $to) === 1) {
        return $problem('to_url', 'to_invalid', lang('The new address cannot contain line breaks or control characters.'));
    }

    if (mb_strlen($to, 'UTF-8') > PG_REDIRECT_TO_MAX) {
        return $problem('to_url', 'to_long', lang(array('string' => 'The new address can be at most {var:1} characters long.', 'vars' => array(PG_REDIRECT_TO_MAX))));
    }

    if (!pg_redirect_is_absolute($to) && ((substr($to, 0, 1) !== '/') || (substr($to, 0, 2) === '//') || (substr($to, 0, 2) === '/\\'))) {
        return $problem('to_url', 'to_form', lang('Enter the new address as a path of this site that starts with / (for example /new-page) or as a full address that starts with http:// or https://.'));
    }

    $references = pg_redirect_target_references($to);
    $wildcards = $is_pattern ? substr_count($from, '*') : 0;

    if ($references > $wildcards) {
        return $problem('to_url', 'to_references', lang(array('string' => 'The new address uses ${var:1}, but the old address has {var:2} wildcard(s) (*).', 'vars' => array($references, $wildcards))));
    }

    if (!in_array($code, pg_redirect_codes(), true)) {
        return $problem('code', 'code_invalid', lang('Choose 301 (permanent) or 302 (temporary).'));
    }

    $from_key = pg_redirect_key($from);
    $prepared = (is_array($rules) && !empty($rules['prepared'])) ? $rules : pg_redirect_prepare($rules);

    if (isset($prepared['keys'][$from_key]) && ($prepared['keys'][$from_key] !== $id)) {
        return $problem('from_path', 'from_taken', lang('There is already a redirect for this address.'));
    }

    $self = array(
        'id' => ($id > 0) ? $id : 0,
        'from_path' => $from,
        'to_url' => $to,
        'code' => $code,
        'is_pattern' => $is_pattern ? 1 : 0,
    );

    // Where the rule sends an address of its own: the address itself, or for
    // a pattern one that every wildcard matches with a sample segment.
    $sample = array_fill(0, max(1, $wildcards), 'pg-sample');
    $start_target = $is_pattern ? pg_redirect_fill_target($to, $sample, false) : $to;
    $own = function ($path) use ($is_pattern, $from, $from_key) {
        if ($is_pattern) {
            return (pg_redirect_pattern_captures($from, $path) !== null);
        }

        return (pg_redirect_key($path) === $from_key);
    };

    $path = pg_redirect_internal_path($start_target, $site_host, $base_path);

    // A target elsewhere, or the home page, which always answers: the
    // visitor stops there.
    if (($path === null) || ($path === '/')) {
        return null;
    }

    if ($own($path)) {
        if ($is_pattern) {
            return $problem('to_url', 'to_self', lang('The new address matches the old one, so this redirect would send its visitors around in a loop.'));
        }

        return $problem('to_url', 'to_self', lang('This redirect would send the address to itself.'));
    }

    // The rules as they would be with this one saved: the stored version of
    // the rule it replaces taken out, this one put in.
    $index = $prepared['index'];

    if (($id > 0) && isset($prepared['by_id'][$id])) {
        pg_redirect_index_remove($index, $prepared['by_id'][$id]);
    }

    pg_redirect_index_add($index, $self);

    for ($hop = 1; $hop <= PG_REDIRECT_MAX_HOPS; $hop++) {
        // The home page always answers: a chain that reaches it ends there.
        if ($path === '/') {
            return null;
        }

        $next = pg_redirect_resolve_in($path, $index, false);

        if ($next === null) {
            return null;
        }

        $path = pg_redirect_internal_path($next['target'], $site_host, $base_path);

        if ($path === null) {
            return null;
        }

        if ($own($path)) {
            return $problem('to_url', 'to_loop', lang(array('string' => 'This redirect would close a loop: the new address leads back to the old one through the redirect from {var:1}.', 'vars' => array($next['rule']['from_path']))));
        }
    }

    return $problem('to_url', 'to_chain', lang(array('string' => 'The new address goes on through more than {var:1} redirects; point it at the final address instead.', 'vars' => array(PG_REDIRECT_MAX_HOPS))));
}

/**
 * Puts a failed statement in the error log without ending the request.
 *
 * @param string $step
 * @return void
 */
function pg_redirect_log_failure($step)
{
    if (!function_exists('pg_error_write') || !function_exists('pg_error_entry')) {
        return;
    }

    pg_error_write(pg_error_entry(
        'warning',
        'E_USER_WARNING',
        'Redirects: ' . $step . ' failed: ' . mysqli_error(db::$con),
        __FILE__,
        __LINE__,
        array()));
}

/**
 * Every rule, oldest first.
 *
 * @return array[]
 */
function pg_redirect_rules()
{
    if (!pg_redirects_ready()) {
        return array();
    }

    return db_items("SELECT id, from_path, from_hash, to_url, code, is_pattern, hits, last_hit, created_by, created_at FROM redirects ORDER BY id");
}

/**
 * One rule, or null.
 *
 * @param int $id
 * @return array|null
 */
function pg_redirect_get($id)
{
    if (!pg_redirects_ready() || ((int) $id <= 0)) {
        return null;
    }

    $row = db_item("SELECT id, from_path, from_hash, to_url, code, is_pattern, hits, last_hit, created_by, created_at FROM redirects WHERE id = '" . (int) $id . "'");

    return is_array($row) ? $row : null;
}

/**
 * Saves one rule after pg_redirect_rule_problem() has nothing against it.
 *
 * A new rule when $rule has no id, else the stored one is changed; its hit
 * counter is kept when only the target or the code changes and starts again
 * when the address changes.
 *
 * @param array $rule    from_path, to_url, code, optional id
 * @param int   $user_id The user who writes it (0: the software)
 * @param array $rules   The rules to check against (a list or pg_redirect_prepare()); read from the table when null
 * @return array array('ok' => bool, 'id' => int, 'created' => bool, 'problem' => array|null)
 */
function pg_redirect_save($rule, $user_id = 0, $rules = null)
{
    $result = array('ok' => false, 'id' => 0, 'created' => false, 'problem' => null);

    if (!pg_redirects_ready()) {
        $result['problem'] = array('field' => '', 'code' => 'not_ready', 'message' => lang('The redirect table is not in the database yet; run the software update first.'));
        return $result;
    }

    $id = isset($rule['id']) ? (int) $rule['id'] : 0;
    $stored = null;

    if ($id > 0) {
        $stored = pg_redirect_get($id);

        if ($stored === null) {
            $result['problem'] = array('field' => '', 'code' => 'missing', 'message' => lang('The redirect could not be found. It may have been deleted.'));
            return $result;
        }
    }

    if ($rules === null) {
        $rules = pg_redirect_rules();
    }

    $problem = pg_redirect_rule_problem($rule, $rules, defined('HOSTNAME_SETTING') ? HOSTNAME_SETTING : '', defined('PATH') ? PATH : '/');

    if ($problem !== null) {
        $result['problem'] = $problem;
        return $result;
    }

    $from = pg_redirect_normalize_path($rule['from_path'], defined('PATH') ? PATH : '/');
    $to = trim((string) $rule['to_url']);
    $code = (int) $rule['code'];
    $is_pattern = pg_redirect_is_pattern($from) ? 1 : 0;

    if ($stored === null) {
        $saved = @mysqli_query(db::$con, "INSERT INTO redirects (from_path, from_hash, to_url, code, is_pattern, created_by, created_at)
            VALUES ('" . e($from) . "', '" . e(pg_redirect_key($from)) . "', '" . e($to) . "', '" . $code . "', '" . $is_pattern . "', '" . (int) $user_id . "', '" . time() . "')");

        $id = $saved ? (int) mysqli_insert_id(db::$con) : 0;
        $result['created'] = true;
    } else {
        $moved = (pg_redirect_key($stored['from_path']) !== pg_redirect_key($from));

        $saved = @mysqli_query(db::$con, "UPDATE redirects SET
                from_path = '" . e($from) . "',
                from_hash = '" . e(pg_redirect_key($from)) . "',
                to_url = '" . e($to) . "',
                code = '" . $code . "',
                is_pattern = '" . $is_pattern . "'" . ($moved ? ",
                hits = 0,
                last_hit = 0" : '') . "
            WHERE id = '" . $id . "'");
    }

    if (!$saved) {
        // The unique key: another request wrote a rule for the address in
        // the meantime.
        if (mysqli_errno(db::$con) === 1062) {
            $result['problem'] = array('field' => 'from_path', 'code' => 'from_taken', 'message' => lang('There is already a redirect for this address.'));
        } else {
            pg_redirect_log_failure('saving a rule');
            $result['problem'] = array('field' => '', 'code' => 'failed', 'message' => lang('The redirect could not be saved.'));
        }

        return $result;
    }

    $result['ok'] = true;
    $result['id'] = $id;

    return $result;
}

/**
 * Deletes rules.
 *
 * @param int[] $ids
 * @return int The rules deleted
 */
function pg_redirect_delete($ids)
{
    if (!pg_redirects_ready()) {
        return 0;
    }

    $list = array();

    foreach ((array) $ids as $id) {
        if (is_scalar($id) && ((int) $id > 0)) {
            $list[(int) $id] = (int) $id;
        }
    }

    if (!$list) {
        return 0;
    }

    db("DELETE FROM redirects WHERE id IN ('" . implode("','", $list) . "')");

    return max(0, (int) mysqli_affected_rows(db::$con));
}

/**
 * What already answers at a rule's address, so the screen can say the rule
 * will wait: a page, a file or a short link of that name. '' for nothing.
 * Only plain addresses of one segment can be a page or file name.
 *
 * @param string $from A normalized path
 * @return string page|file|short_link|''
 */
function pg_redirect_address_taken($from)
{
    $from = (string) $from;

    if (pg_redirect_is_pattern($from) || ($from === '/')) {
        return '';
    }

    $name = ltrim($from, '/');

    if (strpos($name, '/') !== false) {
        return '';
    }

    if (db_value("SELECT COUNT(*) FROM page WHERE page_name = '" . e($name) . "'") > 0) {
        return 'page';
    }

    if (db_value("SELECT COUNT(*) FROM files WHERE name = '" . e($name) . "'") > 0) {
        return 'file';
    }

    if (db_value("SELECT COUNT(*) FROM short_links WHERE name = '" . e($name) . "'") > 0) {
        return 'short_link';
    }

    return '';
}

/**
 * Sends the visitor of a missing address on, when a rule says where.
 *
 * Asked where a request is about to answer 404 (see the file header). It
 * does something only once per request, only on a page request that
 * router.php handed to get_page.php (PG_REDIRECT_LOOKUP), only for GET and
 * HEAD (a form posted to an old address is not re-sent somewhere else), and
 * never while the SEO pass renders. A rule that would send the address to
 * itself is ignored. Nothing here may stop the 404: a failed query is
 * logged and the request goes on.
 *
 * @return bool false when nothing was redirected (it does not return otherwise)
 */
function pg_redirect_not_found()
{
    static $asked = false;

    if ($asked) {
        return false;
    }

    $asked = true;

    if (!defined('PG_REDIRECT_LOOKUP') || !defined('REQUEST_URL') || headers_sent()) {
        return false;
    }

    $method = isset($_SERVER['REQUEST_METHOD']) ? strtoupper((string) $_SERVER['REQUEST_METHOD']) : 'GET';

    if (($method !== 'GET') && ($method !== 'HEAD')) {
        return false;
    }

    if (function_exists('pg_seo_rendering') && pg_seo_rendering()) {
        return false;
    }

    if (!isset(db::$con) || !db::$con || !pg_redirects_ready()) {
        return false;
    }

    $base_path = defined('PATH') ? PATH : '/';
    $path = pg_redirect_request_path(REQUEST_URL, $base_path);

    if (($path === '') || ($path === '/') || (mb_strlen($path, 'UTF-8') > PG_REDIRECT_FROM_MAX)) {
        return false;
    }

    $columns = "id, from_path, to_url, code, is_pattern";
    $rules = array();

    $exact = @mysqli_query(db::$con, "SELECT " . $columns . " FROM redirects
        WHERE from_hash = '" . pg_redirect_key($path) . "' AND is_pattern = 0
        LIMIT 1");

    if ($exact === false) {
        pg_redirect_log_failure('reading the rule for an address');
        return false;
    }

    $row = mysqli_fetch_assoc($exact);

    if (is_array($row)) {
        $rules[] = $row;
    } else {
        $patterns = @mysqli_query(db::$con, "SELECT " . $columns . " FROM redirects
            WHERE is_pattern = 1
            ORDER BY id
            LIMIT " . PG_REDIRECT_PATTERN_LIMIT);

        if ($patterns === false) {
            pg_redirect_log_failure('reading the pattern rules');
            return false;
        }

        while ($pattern = mysqli_fetch_assoc($patterns)) {
            $rules[] = $pattern;
        }
    }

    $index = pg_redirect_index($rules);
    $match = pg_redirect_resolve_in($path, $index);

    if ($match === null) {
        return false;
    }

    // A rule that leads back to the address asked for (written around the
    // checks, or by a pattern) is not followed: the 404 is the better answer.
    $site_host = defined('HOSTNAME_SETTING') ? HOSTNAME_SETTING : '';
    $raw = pg_redirect_resolve_in($path, $index, false);
    $internal = pg_redirect_internal_path($raw['target'], $site_host, $base_path);

    if (($internal !== null) && (pg_redirect_key($internal) === pg_redirect_key($path))) {
        return false;
    }

    $origin = (defined('URL_SCHEME') ? URL_SCHEME : 'https://') . $site_host;
    $location = pg_redirect_location($match['target'], pg_redirect_request_query(REQUEST_URL), $origin, $base_path);
    $code = in_array((int) $match['rule']['code'], pg_redirect_codes(), true) ? (int) $match['rule']['code'] : 301;

    @mysqli_query(db::$con, "UPDATE redirects SET hits = hits + 1, last_hit = '" . time() . "' WHERE id = '" . (int) $match['rule']['id'] . "'");

    header('Location: ' . $location, true, $code);
    exit();
}

/**
 * The rows of a redirect CSV file.
 *
 * Columns: the old address, the new one and, optionally, the code (301 when
 * empty). The separator is a comma, or the semicolon or tab a spreadsheet
 * wrote when the first line has one of those and no comma. A byte order mark
 * is dropped, and bytes that are not UTF-8 are read as the Windows Turkish
 * code page (what Excel writes in Turkey). A first line whose first cell is
 * not an address (from, Old URL, Eski adres) is a heading and skipped, as are
 * empty lines.
 *
 * @param string $bytes
 * @return array[] array('line' => int, 'from_path' => string, 'to_url' => string, 'code' => string)
 */
function pg_redirect_csv_rows($bytes)
{
    $bytes = (string) $bytes;

    if (substr($bytes, 0, 3) === "\xEF\xBB\xBF") {
        $bytes = substr($bytes, 3);
    }

    if (!mb_check_encoding($bytes, 'UTF-8')) {
        $encodings = array_map('strtolower', mb_list_encodings());
        $bytes = mb_convert_encoding($bytes, 'UTF-8', in_array('windows-1254', $encodings, true) ? 'Windows-1254' : 'ISO-8859-9');
    }

    $bytes = str_replace(array("\r\n", "\r"), "\n", $bytes);
    $first_line = (string) strtok($bytes, "\n");
    $delimiter = ',';

    if (strpos($first_line, ',') === false) {
        if (strpos($first_line, ';') !== false) {
            $delimiter = ';';
        } elseif (strpos($first_line, "\t") !== false) {
            $delimiter = "\t";
        }
    }

    $handle = fopen('php://temp', 'r+');
    fwrite($handle, $bytes);
    rewind($handle);

    $rows = array();
    $line = 0;
    $first = true;

    while (($cells = fgetcsv($handle, 0, $delimiter, '"', '\\')) !== false) {
        $line++;

        if (!is_array($cells) || ((count($cells) === 1) && (trim((string) $cells[0]) === ''))) {
            continue;
        }

        $from = trim((string) $cells[0]);
        $to = isset($cells[1]) ? trim((string) $cells[1]) : '';
        $code = isset($cells[2]) ? trim((string) $cells[2]) : '';

        if ($first) {
            $first = false;

            if (preg_match('#^(/|https?://|//)#i', $from) !== 1) {
                continue;
            }
        }

        $rows[] = array('line' => $line, 'from_path' => $from, 'to_url' => $to, 'code' => $code);
    }

    fclose($handle);

    return $rows;
}

/**
 * Rules as a CSV file: a heading line (from,to,code), then one line per rule
 * (RFC 4180: a field holding a quote, comma, line break or edge space is
 * quoted with its quotes doubled). The byte order mark lets a spreadsheet
 * read the file as UTF-8.
 *
 * @param array[] $rules
 * @return string
 */
function pg_redirect_csv($rules)
{
    $quote = function ($text) {
        $text = (string) $text;

        if (($text !== trim($text)) || (strpbrk($text, "\",\r\n") !== false)) {
            return '"' . str_replace('"', '""', $text) . '"';
        }

        return $text;
    };

    $lines = array('from,to,code');

    foreach ((array) $rules as $rule) {
        $lines[] = $quote($rule['from_path']) . ',' . $quote($rule['to_url']) . ',' . (int) $rule['code'];
    }

    return "\xEF\xBB\xBF" . implode("\r\n", $lines) . "\r\n";
}

/**
 * Imports the rows of a CSV file as rules.
 *
 * Each row is checked like a rule saved on its own screen, against the rules
 * already there and the ones added by the rows before it, so a file may chain
 * its own redirects but cannot close a loop. A row for an address that has a
 * rule replaces that rule's target and code when $replace is set, and is
 * skipped otherwise.
 *
 * @param array[] $rows    pg_redirect_csv_rows() rows
 * @param bool    $replace
 * @param int     $user_id
 * @return array array('added' => int, 'updated' => int, 'skipped' => int, 'errors' => array of array('line' => int, 'message' => string))
 */
function pg_redirect_import($rows, $replace, $user_id = 0)
{
    $summary = array('added' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => array());
    $prepared = pg_redirect_prepare(pg_redirect_rules());
    $base_path = defined('PATH') ? PATH : '/';

    foreach ((array) $rows as $row) {
        $rule = array(
            'from_path' => $row['from_path'],
            'to_url' => $row['to_url'],
            'code' => ($row['code'] === '') ? 301 : $row['code'],
        );

        // "301 Permanent" or "moved" is not a code: the line is refused with
        // the code's own message rather than read as 301.
        if (preg_match('/^\d+$/', (string) $rule['code']) !== 1) {
            $rule['code'] = 0;
        }

        $key = pg_redirect_key(pg_redirect_normalize_path($row['from_path'], $base_path));

        if (isset($prepared['keys'][$key])) {
            if (!$replace) {
                $summary['skipped']++;
                continue;
            }

            $rule['id'] = $prepared['keys'][$key];
        }

        $saved = pg_redirect_save($rule, $user_id, $prepared);

        if (!$saved['ok']) {
            $summary['errors'][] = array('line' => (int) $row['line'], 'message' => $saved['problem']['message']);
            continue;
        }

        $stored = pg_redirect_get($saved['id']);

        if ($stored === null) {
            continue;
        }

        pg_redirect_prepared_put($prepared, $stored);

        if ($saved['created']) {
            $summary['added']++;
        } else {
            $summary['updated']++;
        }
    }

    return $summary;
}
