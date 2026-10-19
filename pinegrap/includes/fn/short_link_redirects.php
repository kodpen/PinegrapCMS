<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Short links that send visitors on (2026.11.0, 11.0.12): how a link answers,
 * names that are old addresses, and names with a * in them.
 *
 * short_links.redirect_code is the answer: 0 is the answer a link always
 * gave (a page, product group, product or file shown at the short address,
 * an address off the site sent on by how the link opens), 301 and 302 send
 * the visitor to the destination's own address with that status, which is
 * what the old address of a moved page should do. router.php answers an exact
 * name itself (router_short_link_redirect(), with pg_short_link_location()
 * from here); the visitor's query string travels on, so campaign parameters
 * survive the move.
 *
 * A name with a * is a pattern (short_links.is_pattern): "blog/*" stands for
 * every address under blog/. router.php never takes one as an exact name.
 * It is tried only once get_page.php knows that no page answers: output_error()
 * and error() with a 404 and pg_sw_item_missing() ask
 * pg_short_link_not_found(), so a pattern can never hide a live page. The
 * most specific pattern wins (most characters outside the stars, then the
 * oldest); a target address reuses what each * matched as $1, $2.
 *
 * Names follow pg_short_link_name_problem(): letters of any language,
 * numbers and a few path characters, as an old address typed or pasted
 * (pg_short_link_name_from_address() keeps only the path of a full address).
 * A link that sends visitors on is checked before it is saved
 * (pg_short_link_redirect_problem()): not to itself, not round a loop through
 * other links, not down a chain of more than PG_SHORT_LINK_MAX_HOPS links.
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
 * Links followed through other links when one is checked for a loop; a longer
 * chain is refused too, since every hop is a round trip for the visitor.
 */
define('PG_SHORT_LINK_MAX_HOPS', 10);

/**
 * Pattern links read on one request: they are tried one by one in PHP.
 */
define('PG_SHORT_LINK_PATTERN_LIMIT', 5000);

/**
 * True when short links carry an answer and a pattern flag (2026.11.0,
 * 11.0.12).
 *
 * @return bool
 */
function pg_short_link_redirects_ready()
{
    return pg_schema_has('short_links', 'is_pattern');
}

/**
 * The longest name and destination address the columns take: 191 and 2048
 * since 11.0.12, 100 and 255 before it (a longer value would be cut short in
 * silence).
 *
 * @return int
 */
function pg_short_link_name_max()
{
    return pg_short_link_redirects_ready() ? 191 : 100;
}

function pg_short_link_url_max()
{
    return pg_short_link_redirects_ready() ? 2048 : 255;
}

/**
 * The answers a link can give.
 *
 * @return int[]
 */
function pg_short_link_answer_codes()
{
    return array(0, 301, 302);
}

/**
 * The answers as the forms offer them, label => value.
 *
 * @return array
 */
function pg_short_link_answer_options()
{
    return array(
        lang('Open as a short link') => '0',
        lang('Redirect for good to the destination\'s own address (301)') => '301',
        lang('Redirect for now to the destination\'s own address (302)') => '302',
    );
}

/**
 * A stored answer as the list shows it ('' for the answer links always gave).
 *
 * @param mixed $code
 * @return string
 */
function pg_short_link_answer_label($code)
{
    switch ((int) $code) {
        case 301:
            return lang('Redirect 301');
        case 302:
            return lang('Redirect 302');
    }

    return '';
}

/**
 * The status a link sends its visitors on with, or 0 when it shows its
 * destination. A link that closes (timed, one-time) answers 302 even when it
 * was saved with 301: a browser keeps a 301 for good and would go on to the
 * destination without asking once the link has closed.
 *
 * @param array $link A short_links row
 * @return int
 */
function pg_short_link_status($link)
{
    $code = isset($link['redirect_code']) ? (int) $link['redirect_code'] : 0;

    if (($code !== 301) && ($code !== 302)) {
        return 0;
    }

    $closes = ((isset($link['link_mode']) ? (string) $link['link_mode'] : 'permanent') !== 'permanent')
        || ((isset($link['expires_at']) ? (int) $link['expires_at'] : 0) > 0);

    return $closes ? 302 : $code;
}

/**
 * The answer a link is saved with: a pattern always sends visitors on, since
 * there is no one address to show the destination at, so it gets 301 when it
 * was given none.
 *
 * @param mixed  $code
 * @param string $name
 * @return int
 */
function pg_short_link_answer_for($code, $name)
{
    $code = (is_scalar($code) && in_array((int) $code, pg_short_link_answer_codes(), true)) ? (int) $code : 0;

    if (($code === 0) && pg_short_link_is_pattern($name)) {
        return 301;
    }

    return $code;
}

/**
 * A name as an operator types or pastes it, written the way short links are
 * stored: a full address (https://old.example.com/a/b?x=1) gives its path, a
 * query string and a fragment go, the path is decoded (left as it was when
 * decoding does not give UTF-8), the site's base path comes off the front,
 * spaces become _, runs of / and of * become one, and no / is kept at either
 * end. Only http:// and https:// make a full address.
 *
 * @param mixed  $address
 * @param string $base_path The site's base path (PATH)
 * @return string
 */
function pg_short_link_name_from_address($address, $base_path = null)
{
    if (!is_scalar($address)) {
        return '';
    }

    $name = trim((string) $address);

    if ($name === '') {
        return '';
    }

    if (preg_match('#^https?://#i', $name) === 1) {
        $parsed = @parse_url($name);
        $name = (is_array($parsed) && isset($parsed['path'])) ? (string) $parsed['path'] : '';
    } else {
        $name = substr($name, 0, strcspn($name, '?#'));
    }

    $decoded = rawurldecode($name);

    if (mb_check_encoding($decoded, 'UTF-8')) {
        $name = $decoded;
    }

    $name = str_replace(' ', '_', $name);
    $name = (string) preg_replace('#/{2,}#', '/', $name);
    $name = (string) preg_replace('#\*{2,}#', '*', $name);

    if ($base_path === null) {
        $base_path = defined('PATH') ? PATH : '/';
    }

    $base = trim((string) $base_path, '/');
    $name = trim($name, '/');

    if (($base !== '') && (stripos($name . '/', $base . '/') === 0) && (substr($address, 0, 1) === '/' || preg_match('#^https?://#i', (string) $address) === 1)) {
        $name = trim((string) substr($name, strlen($base)), '/');
    }

    return $name;
}

/**
 * What is wrong with a stored-form name, or '' when it can be used.
 *
 * @param string $name pg_short_link_name_from_address()
 * @return string
 */
function pg_short_link_name_problem($name)
{
    $name = (string) $name;

    if ($name === '') {
        return lang('Name is required.');
    }

    if (mb_strlen($name, 'UTF-8') > pg_short_link_name_max()) {
        return lang(array('string' => 'The name can be at most {var:1} characters long.', 'vars' => array(pg_short_link_name_max())));
    }

    if (preg_match('/^[\p{L}\p{M}\p{N}._\-\/\[\]~()+,!=@*]+$/u', $name) !== 1) {
        return pg_short_link_name_rule_text();
    }

    if (substr_count($name, '*') > 9) {
        return lang('A name can have at most 9 wildcards (*).');
    }

    if (trim($name, '*/') === '') {
        return lang('A name needs at least one letter or number beside its wildcards.');
    }

    return '';
}

/**
 * The name rule in one sentence, for the forms and their errors.
 *
 * @return string
 */
function pg_short_link_name_rule_text()
{
    return lang('The name may contain letters, numbers and . _ - / [ ] ~ ( ) + , ! = @; a * stands for any part of an address.');
}

/**
 * True when a name holds a wildcard.
 *
 * @param string $name
 * @return bool
 */
function pg_short_link_is_pattern($name)
{
    return (strpos((string) $name, '*') !== false);
}

/**
 * The form two names are compared in, as close to the database's comparison
 * of an exact name as PHP gets: without case and without the marks on Latin
 * letters (utf8mb4_unicode_ci finds "urun" for "ürün" too).
 *
 * @param string $name
 * @return string
 */
function pg_short_link_key($name)
{
    $name = (string) $name;

    if (function_exists('pg_transliterate_to_ascii')) {
        $name = pg_transliterate_to_ascii($name);
    }

    return mb_strtolower($name, 'UTF-8');
}

/**
 * The regular expression a pattern name matches with: anchored, each *
 * capturing as little as it can (the anchors still make it reach as far as
 * it must), case-insensitive.
 *
 * @param string $pattern
 * @return string
 */
function pg_short_link_pattern_regex($pattern)
{
    $quoted = array();

    foreach (explode('*', (string) $pattern) as $part) {
        $quoted[] = preg_quote($part, '#');
    }

    return '#^' . implode('(.*?)', $quoted) . '$#iu';
}

/**
 * What each * of a pattern matched in a name, or null when it does not
 * match.
 *
 * @param string $pattern
 * @param string $name
 * @return string[]|null
 */
function pg_short_link_pattern_captures($pattern, $name)
{
    if (@preg_match(pg_short_link_pattern_regex($pattern), (string) $name, $matches) !== 1) {
        return null;
    }

    array_shift($matches);

    return array_map('strval', $matches);
}

/**
 * Pattern links in the order they are tried: the most characters outside the
 * stars first, then the oldest.
 *
 * @param array[] $links Rows with name and id
 * @return array[]
 */
function pg_short_link_sort_patterns($links)
{
    $links = array_values((array) $links);

    usort($links, function ($a, $b) {
        $weight = mb_strlen(str_replace('*', '', (string) $b['name']), 'UTF-8') - mb_strlen(str_replace('*', '', (string) $a['name']), 'UTF-8');

        if ($weight !== 0) {
            return $weight;
        }

        return ((int) $a['id']) - ((int) $b['id']);
    });

    return $links;
}

/**
 * A destination address with what the wildcards matched written into $1 to
 * $9. Encoded, each matched piece goes in one segment at a time, so a decoded
 * ? or # cannot start a query string or a fragment; unencoded is for
 * comparing the address with names.
 *
 * @param string   $target
 * @param string[] $captures
 * @param bool     $encode
 * @return string
 */
function pg_short_link_fill_target($target, $captures, $encode = true)
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
 * The highest $n an address refers to (0 for none).
 *
 * @param string $target
 * @return int
 */
function pg_short_link_target_references($target)
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
 * The name on this site a destination address leads to, the way router.php
 * completes an address: a full address on this site's host, a path from the
 * host's root (/x) or a path below the site (x). '' is the home page; null is
 * an address elsewhere, or not one a link can follow.
 *
 * @param string $url
 * @param string $site_host The site's host name (HOSTNAME_SETTING)
 * @param string $base_path The site's base path (PATH)
 * @return string|null
 */
function pg_short_link_url_name($url, $site_host = '', $base_path = '/')
{
    $url = trim((string) $url);
    $parts = @parse_url($url);

    if (!is_array($parts)) {
        return null;
    }

    if (isset($parts['scheme']) && !in_array(strtolower($parts['scheme']), array('http', 'https'), true)) {
        return null;
    }

    if (isset($parts['host'])) {
        $host = preg_replace('/^www\./', '', strtolower((string) $parts['host']));
        $site = preg_replace('/^www\./', '', strtolower((string) preg_replace('/:\d+$/', '', (string) $site_host)));

        if (($site === '') || ($host !== $site)) {
            return null;
        }
    }

    $path = isset($parts['path']) ? (string) $parts['path'] : '';
    $decoded = rawurldecode($path);

    if (mb_check_encoding($decoded, 'UTF-8')) {
        $path = $decoded;
    }

    $path = (string) preg_replace('#/{2,}#', '/', $path);

    // A path from the host's root has to pass under the site's base path.
    if (isset($parts['host']) || (substr($path, 0, 1) === '/')) {
        $base = trim((string) $base_path, '/');
        $path = trim($path, '/');

        if ($base !== '') {
            if (($path !== $base) && (stripos($path . '/', $base . '/') !== 0)) {
                return null;
            }

            $path = trim((string) substr($path, strlen($base)), '/');
        }

        return $path;
    }

    return trim($path, '/');
}

/**
 * Links arranged for lookups: exact names by pg_short_link_key(), patterns
 * in the order they are tried.
 *
 * @param array[] $links Rows with id, name, destination_type, url, redirect_code
 * @return array array('exact' => array(name => row), 'patterns' => array of rows)
 */
function pg_short_link_index($links)
{
    $index = array('exact' => array(), 'patterns' => array());

    foreach ((array) $links as $link) {
        if ((string) $link['name'] === '') {
            continue;
        }

        if (pg_short_link_is_pattern($link['name'])) {
            $index['patterns'][] = $link;
        } else {
            $index['exact'][pg_short_link_key($link['name'])] = $link;
        }
    }

    $index['patterns'] = pg_short_link_sort_patterns($index['patterns']);

    return $index;
}

/**
 * The link that answers a name, from pg_short_link_index(), with what its
 * stars matched (none for an exact name).
 *
 * @param string $name
 * @param array  $index
 * @param bool   $exact_too Whether exact names are tried (the router has, by the time a 404 asks)
 * @return array|null array('link' => row, 'captures' => array)
 */
function pg_short_link_match_in($name, $index, $exact_too = true)
{
    $key = pg_short_link_key($name);

    if ($exact_too && isset($index['exact'][$key])) {
        return array('link' => $index['exact'][$key], 'captures' => array());
    }

    foreach ($index['patterns'] as $link) {
        $captures = pg_short_link_pattern_captures($link['name'], $name);

        if ($captures !== null) {
            return array('link' => $link, 'captures' => $captures);
        }
    }

    return null;
}

/**
 * What is wrong with where a link sends visitors, or null when it can be
 * saved.
 *
 * $n is for a pattern whose destination is an address, and only up to the
 * number of its stars. An address that leads back to the link's own name (or,
 * for a pattern, to a name it matches itself) is refused, and so is one that
 * comes back to it through other links or goes on through more than
 * PG_SHORT_LINK_MAX_HOPS of them. A page, product group, product or file
 * always answers at its own address, and the home page always answers, so a
 * chain ends there.
 *
 * @param array   $link      id, name (stored form), destination_type, url
 * @param array[] $links     The stored links, or pg_short_link_prepare() of them
 * @param string  $site_host The site's host name
 * @param string  $base_path The site's base path
 * @return array|null array('field' => name|url, 'code' => string, 'message' => string)
 */
function pg_short_link_redirect_problem($link, $links = array(), $site_host = '', $base_path = '/')
{
    $id = isset($link['id']) ? (int) $link['id'] : 0;
    $name = isset($link['name']) ? (string) $link['name'] : '';
    $type = isset($link['destination_type']) ? (string) $link['destination_type'] : '';
    $url = isset($link['url']) ? (string) $link['url'] : '';
    $is_pattern = pg_short_link_is_pattern($name);
    $stars = $is_pattern ? substr_count($name, '*') : 0;

    $problem = function ($field, $code, $message) {
        return array('field' => $field, 'code' => $code, 'message' => $message);
    };

    // $1 to $9 mean something only to a pattern: in the address of an exact
    // name they are plain text, as they always were.
    $references = (($type === 'url') && $is_pattern) ? pg_short_link_target_references($url) : 0;

    if ($references > $stars) {
        return $problem('url', 'to_references', lang(array('string' => 'The address uses ${var:1}, but the name has {var:2} wildcard(s) (*).', 'vars' => array($references, $stars))));
    }

    // What a star matched comes from the visitor's request, so it may not
    // decide which site the visitor is sent to: in the host of a full
    // address, "https://$1/" would send anyone anywhere.
    if (($references > 0) && (preg_match('#^[a-z][a-z0-9+.-]*:/*([^/?\#]*)#i', $url, $authority) === 1) && (preg_match('/\$[1-9]/', $authority[1]) === 1)) {
        return $problem('url', 'to_host', lang('A wildcard can be reused ($1 to $9) only after the host name of the address; in the host it could send visitors to any site.'));
    }

    if (($type !== 'url') || ($name === '')) {
        return null;
    }

    $start = $is_pattern ? pg_short_link_fill_target($url, array_fill(0, max(1, $stars), 'pg-sample'), false) : $url;
    $path = pg_short_link_url_name($start, $site_host, $base_path);

    if (($path === null) || ($path === '')) {
        return null;
    }

    $own = function ($candidate) use ($is_pattern, $name) {
        if ($is_pattern) {
            return (pg_short_link_pattern_captures($name, $candidate) !== null);
        }

        return (pg_short_link_key($candidate) === pg_short_link_key($name));
    };

    if ($own($path)) {
        return $problem('url', 'to_self', $is_pattern
            ? lang('The address matches the name itself, so this link would send its visitors around in a loop.')
            : lang('This link would send visitors to its own address.'));
    }

    $prepared = (is_array($links) && !empty($links['prepared'])) ? $links : pg_short_link_prepare($links);
    $index = $prepared['index'];

    if (($id > 0) && isset($prepared['by_id'][$id])) {
        pg_short_link_index_remove($index, $prepared['by_id'][$id]);
    }

    pg_short_link_index_add($index, array('id' => $id, 'name' => $name, 'destination_type' => $type, 'url' => $url));

    for ($hop = 1; $hop <= PG_SHORT_LINK_MAX_HOPS; $hop++) {
        $next = pg_short_link_match_in($path, $index);

        if (($next === null) || ((string) $next['link']['destination_type'] !== 'url')) {
            return null;
        }

        $next_url = pg_short_link_is_pattern($next['link']['name'])
            ? pg_short_link_fill_target($next['link']['url'], $next['captures'], false)
            : (string) $next['link']['url'];

        $path = pg_short_link_url_name($next_url, $site_host, $base_path);

        if (($path === null) || ($path === '')) {
            return null;
        }

        if ($own($path)) {
            return $problem('url', 'to_loop', lang(array('string' => 'This link would close a loop: its address leads back to it through the short link {var:1}.', 'vars' => array($next['link']['name']))));
        }
    }

    return $problem('url', 'to_chain', lang(array('string' => 'The address goes on through more than {var:1} short links; point it at the final address instead.', 'vars' => array(PG_SHORT_LINK_MAX_HOPS))));
}

/**
 * Links prepared for checking new ones against them, built once: an import
 * checks every line against the same links and keeps the set current with
 * pg_short_link_prepared_put().
 *
 * @param array[] $links
 * @return array
 */
function pg_short_link_prepare($links)
{
    $prepared = array('prepared' => true, 'by_id' => array(), 'index' => array('exact' => array(), 'patterns' => array()));

    foreach ((array) $links as $link) {
        $prepared['by_id'][(int) $link['id']] = $link;
    }

    $prepared['index'] = pg_short_link_index($prepared['by_id']);

    return $prepared;
}

/**
 * Adds a stored link to a prepared set, or puts it in place of its old self.
 *
 * @param array $prepared
 * @param array $link
 * @return void
 */
function pg_short_link_prepared_put(&$prepared, $link)
{
    $id = (int) $link['id'];

    if (isset($prepared['by_id'][$id])) {
        pg_short_link_index_remove($prepared['index'], $prepared['by_id'][$id]);
    }

    $prepared['by_id'][$id] = $link;
    pg_short_link_index_add($prepared['index'], $link);
}

/**
 * Takes a link out of a lookup index.
 *
 * @param array $index
 * @param array $link
 * @return void
 */
function pg_short_link_index_remove(&$index, $link)
{
    if (!pg_short_link_is_pattern($link['name'])) {
        $key = pg_short_link_key($link['name']);

        if (isset($index['exact'][$key]) && ((int) $index['exact'][$key]['id'] === (int) $link['id'])) {
            unset($index['exact'][$key]);
        }

        return;
    }

    foreach ($index['patterns'] as $position => $pattern) {
        if ((int) $pattern['id'] === (int) $link['id']) {
            array_splice($index['patterns'], $position, 1);
            return;
        }
    }
}

/**
 * Puts a link into a lookup index.
 *
 * @param array $index
 * @param array $link
 * @return void
 */
function pg_short_link_index_add(&$index, $link)
{
    if ((string) $link['name'] === '') {
        return;
    }

    if (!pg_short_link_is_pattern($link['name'])) {
        $index['exact'][pg_short_link_key($link['name'])] = $link;
        return;
    }

    $index['patterns'][] = $link;
    $index['patterns'] = pg_short_link_sort_patterns($index['patterns']);
}

/**
 * The links a new one is checked against: every named, live link.
 *
 * @return array[]
 */
function pg_short_link_redirect_links()
{
    $live = pg_schema_has('short_links', 'recycled') ? " AND recycled = '0'" : '';

    return db_items("SELECT id, name, destination_type, url FROM short_links
        WHERE name <> '' AND destination_type <> 'workspace_guest'" . $live);
}

/**
 * Percent-encodes what a URL cannot carry as it is (spaces, letters outside
 * ASCII, quotes, angle brackets); what is already encoded stays as it is.
 *
 * @param string $url
 * @return string
 */
function pg_short_link_encode_url($url)
{
    return (string) preg_replace_callback('/[^\x21-\x7E]|["<>\\\\^`{|}]/', function ($match) {
        return rawurlencode($match[0]);
    }, (string) $url);
}

/**
 * Where a link that sends visitors on sends them.
 *
 * A page is reached at the page's own address (under the language prefix the
 * visitor came in with), a product group or product at its catalog page's,
 * a file at the file's, each with the link's tracking code as ?t= unless the
 * visitor brings one. An address is completed the way the router always
 * completed it: a full address as it is, /x from the host's root, x below the
 * site. The visitor's query string goes on after the destination's own,
 * before its fragment.
 *
 * @param array    $link     A short_links row with page_name, product_group_address_name, product_address_name, file_name
 * @param string[] $captures What the stars of a pattern matched
 * @param string   $query    The visitor's query string, without '?'
 * @param string   $origin   Scheme and host ("https://example.com")
 * @param string   $base     The site's base path (PATH)
 * @param string   $language The base for pages (LANGUAGE_PATH, else PATH)
 * @return string '' when the destination is gone
 */
function pg_short_link_location($link, $captures, $query, $origin, $base = '/', $language = null)
{
    $origin = rtrim((string) $origin, '/');
    $base = '/' . ltrim((string) $base, '/');
    $language = ($language === null) ? $base : '/' . ltrim((string) $language, '/');

    $encode_path = function ($path) {
        return str_replace('%2F', '/', rawurlencode((string) $path));
    };

    $type = (string) $link['destination_type'];
    $tracking = '';

    switch ($type) {
        case 'url':
            // Only a pattern fills in $1 to $9; the address of an exact name
            // is used as it was written.
            $target = pg_short_link_is_pattern(isset($link['name']) ? $link['name'] : '')
                ? pg_short_link_fill_target((string) $link['url'], $captures, true)
                : (string) $link['url'];

            // A path is put after the site's own scheme and host, always
            // behind a /, so nothing in it can move the host (//x, @x, \x
            // stay a path on this site); what the stars matched is
            // percent-encoded segment by segment besides.
            if (preg_match('#^https?://#i', $target) !== 1) {
                $target = $origin . ((substr($target, 0, 1) === '/') ? '' : $base) . $target;
            }

            break;

        case 'file':
            if ((string) ($link['file_name'] ?? '') === '') {
                return '';
            }

            $target = $origin . $base . $encode_path($link['file_name']);

            break;

        default:
            if ((string) ($link['page_name'] ?? '') === '') {
                return '';
            }

            $target = $origin . $language . $encode_path($link['page_name']);

            if (($type === 'product_group') && ((string) ($link['product_group_address_name'] ?? '') !== '')) {
                $target .= '/' . $encode_path($link['product_group_address_name']);
            }

            if (($type === 'product') && ((string) ($link['product_address_name'] ?? '') !== '')) {
                $target .= '/' . $encode_path($link['product_address_name']);
            }

            $tracking = (string) ($link['tracking_code'] ?? '');

            break;
    }

    $fragment = '';
    $hash = strpos($target, '#');

    if ($hash !== false) {
        $fragment = substr($target, $hash);
        $target = substr($target, 0, $hash);
    }

    $query = (string) $query;

    if (($tracking !== '') && (preg_match('/(^|&)t=/', $query) !== 1)) {
        $query = 't=' . rawurlencode($tracking) . (($query !== '') ? '&' . $query : '');
    }

    if ($query !== '') {
        $target .= ((strpos($target, '?') === false) ? '?' : '&') . $query;
    }

    return pg_short_link_encode_url($target . $fragment);
}

/**
 * The query string of a request target, without its question mark.
 *
 * @param string $request_url
 * @return string
 */
function pg_short_link_request_query($request_url)
{
    $url = (string) $request_url;
    $url = substr($url, 0, strcspn($url, '#'));
    $mark = strpos($url, '?');

    return ($mark === false) ? '' : (string) substr($url, $mark + 1);
}

/**
 * Sends the visitor of a missing address on, when a pattern link stands for
 * it.
 *
 * Asked where a request is about to answer 404 (see the file header). It
 * does something only once per request, only for a page request that
 * router.php handed to get_page.php after finding no exact link
 * (PG_SHORT_LINK_PATH, the name the router looked up), only for GET and HEAD
 * (a form posted to an old address is not sent somewhere else), never while
 * the SEO pass renders and never after output has started. A pattern that
 * would send the address to itself is passed over. Nothing here may stop the
 * 404: a failed query is logged and the request goes on.
 *
 * @return bool false when nothing was sent on (it does not return otherwise)
 */
function pg_short_link_not_found()
{
    static $asked = false;

    if ($asked) {
        return false;
    }

    $asked = true;

    if (!defined('PG_SHORT_LINK_PATH') || headers_sent()) {
        return false;
    }

    $method = isset($_SERVER['REQUEST_METHOD']) ? strtoupper((string) $_SERVER['REQUEST_METHOD']) : 'GET';

    if (($method !== 'GET') && ($method !== 'HEAD')) {
        return false;
    }

    if (function_exists('pg_seo_rendering') && pg_seo_rendering()) {
        return false;
    }

    if (!isset(db::$con) || !db::$con || !pg_short_link_redirects_ready()) {
        return false;
    }

    $name = trim((string) PG_SHORT_LINK_PATH, '/');

    if (($name === '') || (mb_strlen($name, 'UTF-8') > 2048)) {
        return false;
    }

    $live = pg_schema_has('short_links', 'recycled') ? " AND short_links.recycled = '0'" : '';
    $open = pg_short_link_modes_ready() ? " AND (short_links.expires_at = '0' OR short_links.expires_at > '" . time() . "')" : '';

    $result = @mysqli_query(db::$con,
        "SELECT
            short_links.*,
            page.page_name,
            product_groups.address_name AS product_group_address_name,
            products.address_name AS product_address_name,
            files.name AS file_name
        FROM short_links
        LEFT JOIN page ON short_links.page_id = page.page_id
        LEFT JOIN product_groups ON short_links.product_group_id = product_groups.id
        LEFT JOIN products ON short_links.product_id = products.id
        LEFT JOIN files ON short_links.file_id = files.id
        WHERE short_links.is_pattern = '1' AND short_links.destination_type <> 'workspace_guest'" . $live . $open . "
        ORDER BY short_links.id
        LIMIT " . PG_SHORT_LINK_PATTERN_LIMIT);

    if ($result === false) {
        pg_short_link_log_failure('reading the pattern links');
        return false;
    }

    $patterns = array();

    while ($row = mysqli_fetch_assoc($result)) {
        $patterns[] = $row;
    }

    if (!$patterns) {
        return false;
    }

    $match = pg_short_link_match_in($name, array('exact' => array(), 'patterns' => pg_short_link_sort_patterns($patterns)), false);

    if ($match === null) {
        return false;
    }

    $link = $match['link'];
    $site_host = defined('HOSTNAME_SETTING') ? HOSTNAME_SETTING : '';
    $base = defined('PATH') ? PATH : '/';

    // A pattern whose address leads back to the address asked for (written
    // before the checks, or matched by an odd request) is not followed: the
    // 404 is the better answer.
    if ((string) $link['destination_type'] === 'url') {
        $back = pg_short_link_url_name(pg_short_link_fill_target($link['url'], $match['captures'], false), $site_host, $base);

        if (($back !== null) && (pg_short_link_key($back) === pg_short_link_key($name))) {
            return false;
        }
    }

    $location = pg_short_link_location(
        $link,
        $match['captures'],
        defined('REQUEST_URL') ? pg_short_link_request_query(REQUEST_URL) : '',
        (defined('URL_SCHEME') ? URL_SCHEME : 'https://') . $site_host,
        $base,
        defined('LANGUAGE_PATH') ? LANGUAGE_PATH : $base);

    if ($location === '') {
        return false;
    }

    $code = pg_short_link_status($link);

    if ($code === 0) {
        $code = 301;
    }

    if (pg_short_link_modes_ready()) {
        @mysqli_query(db::$con, "UPDATE short_links SET use_count = use_count + 1, last_used_at = '" . time() . "' WHERE id = '" . (int) $link['id'] . "'");
    }

    header('Location: ' . $location, true, $code);
    exit();
}

/**
 * Puts a failed statement in the error log without ending the request.
 *
 * @param string $step
 * @return void
 */
function pg_short_link_log_failure($step)
{
    if (!function_exists('pg_error_write') || !function_exists('pg_error_entry')) {
        return;
    }

    pg_error_write(pg_error_entry(
        'warning',
        'E_USER_WARNING',
        'Short links: ' . $step . ' failed: ' . mysqli_error(db::$con),
        __FILE__,
        __LINE__,
        array()));
}

/**
 * The answer and pattern columns of a link about to be saved, or the problem
 * that stops it.
 *
 * Every save path calls this once it knows the name and the destination: the
 * classic screens, the file manager and the external API. $code null keeps
 * the stored answer (a form that does not offer the choice). Before 11.0.12
 * the columns are not written at all.
 *
 * @param array    $link   id (0 for a new link), name (stored form), destination_type, url
 * @param int|null $code   The answer asked for
 * @param int      $stored The stored answer, for $code null
 * @param array    $links  The links to check against (pg_short_link_redirect_links() when null)
 * @return array array('columns' => array, 'problem' => array|null)
 */
function pg_short_link_redirect_columns($link, $code = null, $stored = 0, $links = null)
{
    if (!pg_short_link_redirects_ready()) {
        return array('columns' => array(), 'problem' => null);
    }

    $name = isset($link['name']) ? (string) $link['name'] : '';

    $problem = pg_short_link_redirect_problem(
        $link,
        ($links === null) ? pg_short_link_redirect_links() : $links,
        defined('HOSTNAME_SETTING') ? HOSTNAME_SETTING : '',
        defined('PATH') ? PATH : '/');

    return array(
        'columns' => array(
            'redirect_code' => pg_short_link_answer_for(($code === null) ? $stored : $code, $name),
            'is_pattern' => pg_short_link_is_pattern($name) ? 1 : 0,
        ),
        'problem' => $problem,
    );
}
