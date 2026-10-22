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
 * The answer a window posted, or null when it sent none, so the stored answer
 * stays: a window drawn before the upgrade has no such field, and a list
 * posted in its place is no answer. A value that is not one of the answers is
 * saved as 0 by pg_short_link_answer_for(), as on the classic screens.
 *
 * @param mixed $value
 * @return int|null
 */
function pg_short_link_answer_posted($value)
{
    if (!is_scalar($value) || (trim((string) $value) === '')) {
        return null;
    }

    return (int) $value;
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

    $name = pg_short_link_path_name($name);
    $name = (string) preg_replace('#\*{2,}#', '*', $name);

    if ($base_path === null) {
        $base_path = defined('PATH') ? PATH : '/';
    }

    $base = trim((string) $base_path, '/');

    if (($base !== '') && (stripos($name . '/', $base . '/') === 0) && (substr($address, 0, 1) === '/' || preg_match('#^https?://#i', (string) $address) === 1)) {
        $name = trim((string) substr($name, strlen($base)), '/');
    }

    return $name;
}

/**
 * A decoded path in the form names are stored in: spaces become _, runs of /
 * become one, and no / is kept at either end. A name is saved this way
 * (pg_short_link_name_from_address()), so the old address a visitor brings
 * (/blog/post/, /Old%20Page, //post) is looked up in this form too when it is
 * no name as it came: router.php for an exact name, pg_short_link_match_in()
 * for the checks and the patterns.
 *
 * @param string $path
 * @return string
 */
function pg_short_link_path_name($path)
{
    $path = str_replace(' ', '_', (string) $path);

    return trim((string) preg_replace('#/{2,}#', '/', $path), '/');
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

    // A browser resolves . and .. in a path before it asks, so a name with
    // such a part could never be opened.
    if (preg_match('#(^|/)\.{1,2}(/|$)#', $name) === 1) {
        return lang('A part of the name between slashes cannot be . or .. alone.');
    }

    return '';
}

/**
 * A posted name in the stored form, with what is wrong with it ('' when it
 * can be used). The classic screens and the external API ask the same two
 * functions; the file manager asks this, so its errors say what the classic
 * screens say. A list posted in its place is no name.
 *
 * @param mixed $name
 * @return array array('name' => string, 'problem' => string)
 */
function pg_short_link_name_read($name)
{
    $name = is_scalar($name) ? pg_short_link_name_from_address($name) : '';

    return array('name' => $name, 'problem' => pg_short_link_name_problem($name));
}

/**
 * A name with the [n] a copy or a renamed restore gets, the end of the name
 * cut away when the suffix would make it longer than the column takes: a
 * longer value is cut in silence on INSERT, so the copy would be written under
 * the very name it was numbered to avoid.
 *
 * @param string   $wanted A stored-form name
 * @param int      $index
 * @param int|null $max    pg_short_link_name_max() when null
 * @return string '' when no part of the name fits beside the suffix
 */
function pg_short_link_numbered_name($wanted, $index, $max = null)
{
    $max = ($max === null) ? pg_short_link_name_max() : (int) $max;
    $suffix = '[' . (int) $index . ']';
    $room = $max - strlen($suffix);
    $base = ($room > 0) ? mb_substr((string) $wanted, 0, $room, 'UTF-8') : '';

    return ($base === '') ? '' : $base . $suffix;
}

/**
 * What is wrong with the address a link sends visitors to, or '' when it can
 * be used: a web address (http or https) or a path on this site, without
 * spaces or a backslash, not protocol-relative, within the column. The
 * external API and the CSV import ask this.
 *
 * @param mixed $url
 * @return string
 */
function pg_short_link_url_problem($url)
{
    $url = trim(is_scalar($url) ? (string) $url : '');

    if ($url === '') {
        return lang('Destination URL is required.');
    }

    if (preg_match('/[\x00-\x20\x7f]/', $url) === 1) {
        return lang('Sorry, you may not enter a space in the URL.');
    }

    if (((preg_match('/^([A-Za-z][A-Za-z0-9+.-]*):/', $url, $found) === 1) && !in_array(strtolower($found[1]), array('http', 'https'), true))
        || (strpos($url, '//') === 0) || (strpos($url, '\\') !== false)) {
        return lang('The address must be a web address or a path on this site.');
    }

    // The column takes 2048 characters since 11.0.12 (255 before); a longer
    // address would be cut short in silence.
    if (mb_strlen($url, 'UTF-8') > pg_short_link_url_max()) {
        return lang(array('string' => 'The address can be at most {var:1} characters long.', 'vars' => array(pg_short_link_url_max())));
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
 * The form two names are compared in. Two names are the same name when
 * router.php's WHERE short_links.name = '…' finds one for the other, so the
 * database answers: the key is the weight string of the name under the name
 * column's collation (utf8mb4_unicode_ci finds "swieta" for "święta" and
 * "urun" for "ürün", but keeps "ı" and "i" apart). PHP does not imitate the
 * collation; pg_short_link_fold() is only for a run without a connection
 * (the test runner), and its keys never meet the database's.
 *
 * @param string $name
 * @return string
 */
function pg_short_link_key($name)
{
    $name = (string) $name;
    $keys = pg_short_link_keys(array($name));

    return $keys[$name];
}

/**
 * The keys of many names (pg_short_link_key()), asked of the database a
 * hundred at a time and kept for the request. A name the database could not
 * be asked about gets a key of its own, so it equals no other name.
 *
 * @param string[] $names
 * @return array name => key
 */
function pg_short_link_keys($names)
{
    static $keys = array();

    $names = array_map('strval', (array) $names);
    $missing = array();

    foreach ($names as $name) {
        if (!isset($keys[$name])) {
            $missing[$name] = $name;
        }
    }

    if ($missing) {
        $collation = pg_short_link_name_collation();

        if ($collation === '') {
            foreach ($missing as $name) {
                $keys[$name] = 'php:' . pg_short_link_fold($name);
            }
        } else {
            // The charset is the collation's first part (utf8mb4_unicode_ci,
            // utf8_general_ci): the literal is converted the way the router's
            // comparison converts it.
            $charset = substr($collation, 0, strpos($collation . '_', '_'));

            foreach (array_chunk(array_values($missing), 100) as $chunk) {
                $columns = array();

                foreach ($chunk as $name) {
                    $columns[] = "HEX(WEIGHT_STRING(CONVERT(_utf8mb4'" . mysqli_real_escape_string(db::$con, $name) . "' USING " . $charset . ") COLLATE " . $collation . "))";
                }

                $result = @mysqli_query(db::$con, "SELECT " . implode(', ', $columns));
                $row = $result ? mysqli_fetch_row($result) : null;

                if (!is_array($row)) {
                    pg_short_link_log_failure('comparing names');
                }

                foreach ($chunk as $position => $name) {
                    $keys[$name] = (is_array($row) && isset($row[$position])) ? 'db:' . $row[$position] : 'name:' . $name;
                }
            }
        }
    }

    $found = array();

    foreach ($names as $name) {
        $found[$name] = $keys[$name];
    }

    return $found;
}

/**
 * The collation of short_links.name, read once per request, or '' when there
 * is no connection to ask (the test runner) or the answer is not a collation
 * name.
 *
 * @return string
 */
function pg_short_link_name_collation()
{
    static $collation = null;

    if ($collation !== null) {
        return $collation;
    }

    $collation = '';

    if (!class_exists('db', false) || !isset(db::$con) || !db::$con) {
        return $collation;
    }

    $result = @mysqli_query(db::$con, "SHOW FULL COLUMNS FROM short_links WHERE Field = 'name'");
    $row = $result ? mysqli_fetch_assoc($result) : null;
    $name = is_array($row) && isset($row['Collation']) ? (string) $row['Collation'] : '';

    if (preg_match('/^[a-z0-9]+_[a-z0-9_]+$/', $name) === 1) {
        $collation = $name;
    }

    return $collation;
}

/**
 * Two names as a run without a database compares them: without case, without
 * the marks a letter decomposes into (é, ę, ș; not ı, ø, ł, which have none),
 * compatibility forms as their letters. An approximation of
 * utf8mb4_unicode_ci for tests; without the intl extension only case goes.
 *
 * @param string $name
 * @return string
 */
function pg_short_link_fold($name)
{
    $name = (string) $name;

    if (class_exists('Normalizer')) {
        $decomposed = Normalizer::normalize($name, Normalizer::FORM_KD);

        if (is_string($decomposed)) {
            $name = (string) preg_replace('/\p{Mn}+/u', '', $decomposed);
        }
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

    // Every key in a few queries instead of one per link.
    pg_short_link_keys(array_map(function ($link) {
        return (string) $link['name'];
    }, array_values((array) $links)));

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
 * stars matched (none for an exact name). As router.php asks: exact names
 * first, the name as it came and then in its stored form
 * (pg_short_link_path_name()), then each pattern in its turn, in the same two
 * forms.
 *
 * @param string $name
 * @param array  $index
 * @param bool   $exact_too Whether exact names are tried (the router has, by the time a 404 asks)
 * @return array|null array('link' => row, 'captures' => array)
 */
function pg_short_link_match_in($name, $index, $exact_too = true)
{
    $name = (string) $name;
    $forms = array_unique(array($name, pg_short_link_path_name($name)));

    if ($exact_too) {
        foreach ($forms as $form) {
            $key = pg_short_link_key($form);

            if (isset($index['exact'][$key])) {
                return array('link' => $index['exact'][$key], 'captures' => array());
            }
        }
    }

    foreach ($index['patterns'] as $link) {
        foreach ($forms as $form) {
            $captures = pg_short_link_pattern_captures($link['name'], $form);

            if ($captures !== null) {
                return array('link' => $link, 'captures' => $captures);
            }
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
 * PG_SHORT_LINK_MAX_HOPS of them. A pattern is followed from a sample
 * request and from the address of every stored link it answers. A page,
 * product group, product or file always answers at its own address, and the
 * home page always answers, so a chain ends there.
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

    // Another site or the home page ends it; what the stars match cannot
    // change that (a star in the host is refused above).
    if (($path === null) || ($path === '')) {
        return null;
    }

    // An address reaches the link the way the router finds it: as it is or in
    // its stored form (pg_short_link_match_in()).
    $own = function ($candidate) use ($is_pattern, $name) {
        foreach (array_unique(array($candidate, pg_short_link_path_name($candidate))) as $form) {
            if ($is_pattern ? (pg_short_link_pattern_captures($name, $form) !== null) : (pg_short_link_key($form) === pg_short_link_key($name))) {
                return true;
            }
        }

        return false;
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

    // Whether a path is answered by this link. A pattern answers only what no
    // exact name and no pattern tried before it takes.
    $answers = function ($candidate) use ($is_pattern, $own, $index, $id, $name) {
        if (!$is_pattern) {
            return $own($candidate);
        }

        if (!$own($candidate)) {
            return false;
        }

        $match = pg_short_link_match_in($candidate, $index);

        return ($match !== null) && ((int) $match['link']['id'] === $id) && ((string) $match['link']['name'] === $name);
    };

    // Follows the links from a path on; a chain longer than the limit counts
    // only on the walk from this link's own address.
    $walk = function ($path, $chain_counts) use ($answers, $index, $site_host, $base_path, $problem) {
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

            if ($answers($path)) {
                return $problem('url', 'to_loop', lang(array('string' => 'This link would close a loop: its address leads back to it through the short link {var:1}.', 'vars' => array($next['link']['name']))));
            }
        }

        return $chain_counts
            ? $problem('url', 'to_chain', lang(array('string' => 'The address goes on through more than {var:1} short links; point it at the final address instead.', 'vars' => array(PG_SHORT_LINK_MAX_HOPS))))
            : null;
    };

    $found = $walk($path, true);

    if ($found !== null) {
        return $found;
    }

    if (!$is_pattern) {
        return null;
    }

    // The sample above stands for any request; a loop may need a particular
    // one. A visitor reaches the pattern from wherever another link sends
    // them, so every stored link whose address the pattern answers is a way
    // in: the pattern's address is filled with what its stars matched there
    // and followed on from it.
    foreach ($prepared['by_id'] as $other_id => $other) {
        if (((int) $other_id === $id) || ((string) $other['destination_type'] !== 'url')) {
            continue;
        }

        $other_url = pg_short_link_is_pattern($other['name'])
            ? pg_short_link_fill_target($other['url'], array_fill(0, max(1, substr_count((string) $other['name'], '*')), 'pg-sample'), false)
            : (string) $other['url'];

        $entry = pg_short_link_url_name($other_url, $site_host, $base_path);

        if (($entry === null) || ($entry === '') || !$answers($entry)) {
            continue;
        }

        $match = pg_short_link_match_in($entry, $index);
        $target = pg_short_link_url_name(pg_short_link_fill_target($url, $match['captures'], false), $site_host, $base_path);

        if (($target === null) || ($target === '')) {
            continue;
        }

        if ($answers($target)) {
            return $problem('url', 'to_self', lang('The address matches the name itself, so this link would send its visitors around in a loop.'));
        }

        $found = $walk($target, false);

        if ($found !== null) {
            return $found;
        }
    }

    return null;
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

        if (($back !== null) && (pg_short_link_key(pg_short_link_path_name($back)) === pg_short_link_key(pg_short_link_path_name($name)))) {
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

/**
 * The lines of a short link CSV file.
 *
 * Columns: the address (a name, a path or a full old address), where it sends
 * visitors and, optionally, the code (301 when empty). The separator is the
 * comma, semicolon or tab that splits the most lines into two or three cells
 * (pg_short_link_csv_separator()). The text is read as UTF-8
 * (pg_short_link_csv_text(): UTF-16 as Excel's "Unicode Text" writes it,
 * the Windows Turkish code page for bytes that are not UTF-8). A first line
 * whose second cell holds no / is a heading (from,to, Eski adres;Yeni adres)
 * and is skipped, as are empty lines. The apostrophe pg_short_link_csv() puts
 * before a formula character is taken off again.
 *
 * @param string $bytes
 * @return array[] array('line' => int, 'from' => string, 'to' => string, 'code' => string)
 */
function pg_short_link_csv_rows($bytes)
{
    $bytes = str_replace(array("\r\n", "\r"), "\n", pg_short_link_csv_text($bytes));
    $delimiter = pg_short_link_csv_separator($bytes);

    $handle = fopen('php://temp', 'r+');
    fwrite($handle, $bytes);
    rewind($handle);

    $cell = function ($cells, $index) {
        $text = isset($cells[$index]) ? trim((string) $cells[$index]) : '';

        return (preg_match("/^'[=+\-@]/", $text) === 1) ? substr($text, 1) : $text;
    };

    $rows = array();
    $line = 0;
    $first = true;

    while (($cells = fgetcsv($handle, 0, $delimiter, '"', '\\')) !== false) {
        $line++;

        if (!is_array($cells) || ((count($cells) === 1) && (trim((string) $cells[0]) === ''))) {
            continue;
        }

        $row = array('line' => $line, 'from' => $cell($cells, 0), 'to' => $cell($cells, 1), 'code' => $cell($cells, 2));

        if ($first) {
            $first = false;

            if (strpos($row['to'], '/') === false) {
                continue;
            }
        }

        $rows[] = $row;
    }

    fclose($handle);

    return $rows;
}

/**
 * A CSV file's bytes as UTF-8 text without a byte order mark. UTF-16 is what
 * Excel's "Unicode Text" writes (FF FE, little-endian, tab-separated); without
 * a mark it is told by its NUL bytes, which text in any 8-bit encoding does
 * not have. Other bytes that are not UTF-8 are read as the Windows Turkish
 * code page (what Excel writes in Turkey; ISO-8859-9 where mbstring lacks it).
 *
 * @param string $bytes
 * @return string
 */
function pg_short_link_csv_text($bytes)
{
    $bytes = (string) $bytes;
    $utf16 = '';

    if (substr($bytes, 0, 2) === "\xFF\xFE") {
        $utf16 = 'UTF-16LE';
    } elseif (substr($bytes, 0, 2) === "\xFE\xFF") {
        $utf16 = 'UTF-16BE';
    } elseif (strpos($bytes, "\0") !== false) {
        // ASCII in UTF-16LE puts the NUL after each letter, in UTF-16BE before.
        $sample = substr($bytes, 0, 4096);
        $odd = 0;
        $even = 0;

        for ($at = 0, $length = strlen($sample); $at < $length; $at++) {
            if ($sample[$at] === "\0") {
                if ($at % 2) {
                    $odd++;
                } else {
                    $even++;
                }
            }
        }

        $utf16 = ($odd >= $even) ? 'UTF-16LE' : 'UTF-16BE';
    }

    if ($utf16 !== '') {
        $bytes = (string) mb_convert_encoding($bytes, 'UTF-8', $utf16);
    }

    if (substr($bytes, 0, 3) === "\xEF\xBB\xBF") {
        $bytes = substr($bytes, 3);
    }

    if (!mb_check_encoding($bytes, 'UTF-8')) {
        $encodings = array_map('strtolower', mb_list_encodings());
        $bytes = (string) mb_convert_encoding($bytes, 'UTF-8', in_array('windows-1254', $encodings, true) ? 'Windows-1254' : 'ISO-8859-9');
    }

    return $bytes;
}

/**
 * The separator of a short link CSV file: of the comma, the semicolon and the
 * tab, the one that splits the most of its first lines into two or three
 * cells (from, to, code), the comma first when they split as many. A comma
 * inside one address of a semicolon file (a name may hold one) no longer makes
 * the whole file read as comma-separated.
 *
 * @param string $text UTF-8, lines ending in \n
 * @return string
 */
function pg_short_link_csv_separator($text)
{
    $lines = array();

    foreach (explode("\n", (string) $text) as $line) {
        if (trim($line) !== '') {
            $lines[] = $line;
        }

        if (count($lines) >= 20) {
            break;
        }
    }

    $best = ',';
    $best_score = -1;

    foreach (array(',', ';', "\t") as $candidate) {
        $score = 0;

        foreach ($lines as $line) {
            $cells = count(str_getcsv($line, $candidate, '"', '\\'));

            if (($cells === 2) || ($cells === 3)) {
                $score++;
            }
        }

        if ($score > $best_score) {
            $best = $candidate;
            $best_score = $score;
        }
    }

    return $best;
}

/**
 * Where a link leads, as the CSV file writes it: the address of a URL link as
 * it was entered, a page, product group, product or file as its path from the
 * site's root (a page with the link's tracking code). '' when the
 * destination is gone.
 *
 * @param array  $link A short_links row with page_name, the catalog address names and file_name
 * @param string $base The site's base path (PATH)
 * @return string
 */
function pg_short_link_csv_target($link, $base = '/')
{
    $base = '/' . ltrim((string) $base, '/');
    $type = (string) $link['destination_type'];

    if ($type === 'url') {
        return (string) $link['url'];
    }

    if ($type === 'file') {
        return ((string) ($link['file_name'] ?? '') === '') ? '' : $base . $link['file_name'];
    }

    if ((string) ($link['page_name'] ?? '') === '') {
        return '';
    }

    $target = $base . $link['page_name'];

    if (($type === 'product_group') && ((string) ($link['product_group_address_name'] ?? '') !== '')) {
        $target .= '/' . $link['product_group_address_name'];
    }

    if (($type === 'product') && ((string) ($link['product_address_name'] ?? '') !== '')) {
        $target .= '/' . $link['product_address_name'];
    }

    if ((string) ($link['tracking_code'] ?? '') !== '') {
        $target .= '?t=' . rawurlencode((string) $link['tracking_code']);
    }

    return $target;
}

/**
 * Links as a CSV file: a heading line (from,to,code), then one line per link,
 * the address as a path from the site's root. Every cell is quoted, a cell
 * that starts with a formula character gets an apostrophe in front
 * (csv_cell()), and the byte order mark lets a spreadsheet read the file as
 * UTF-8.
 *
 * @param array[] $links Rows for pg_short_link_csv_target(), with name and redirect_code
 * @param string  $base  The site's base path (PATH)
 * @return string
 */
function pg_short_link_csv($links, $base = '/')
{
    $base = '/' . ltrim((string) $base, '/');
    $lines = array('from,to,code');

    foreach ((array) $links as $link) {
        $lines[] = '"' . csv_cell($base . $link['name']) . '","' . csv_cell(pg_short_link_csv_target($link, $base)) . '",' . (isset($link['redirect_code']) ? (int) $link['redirect_code'] : 0);
    }

    return "\xEF\xBB\xBF" . implode("\r\n", $lines) . "\r\n";
}

/**
 * Adds the lines of a CSV file as short links to addresses.
 *
 * Each line is checked the way a link saved on its own screen is: the name
 * rule, a name no page, file, folder or other link holds, an address a link
 * can send visitors to, and the loop checks, against the links already there
 * and the ones added by the lines before it, so a file may chain its own
 * addresses but cannot close a loop. A line whose name a short link already
 * has is skipped, or, with $replace, gives that link its new address and code
 * when it is a link to an address; a link that shows a page, product or file
 * is never turned into one.
 *
 * @param array[] $rows    pg_short_link_csv_rows()
 * @param bool    $replace
 * @param int     $user_id
 * @return array array('added' => int, 'updated' => int, 'skipped' => int, 'errors' => array of array('line' => int, 'message' => string))
 */
function pg_short_link_import($rows, $replace, $user_id = 0)
{
    $summary = array('added' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => array());

    // The answer and the pattern flag have no column before 11.0.12; the
    // import screen does not open then.
    if (!pg_short_link_redirects_ready()) {
        return $summary;
    }

    $prepared = pg_short_link_prepare(pg_short_link_redirect_links());
    $by_key = array();

    // "A short link has this name" is the database's answer (pg_short_link_key()),
    // the one check_name_availability() and the router give: under
    // utf8mb4_unicode_ci "kilif" is not "kılıf", "swieta" is "święta".
    foreach ($prepared['by_id'] as $link) {
        $by_key[pg_short_link_key($link['name'])] = $link;
    }

    pg_short_link_keys(array_map(function ($row) {
        return pg_short_link_name_from_address($row['from']);
    }, array_values((array) $rows)));

    $modes = pg_short_link_modes_ready();

    foreach ((array) $rows as $row) {
        $line = (int) $row['line'];
        $name = pg_short_link_name_from_address($row['from']);
        $url = trim((string) $row['to']);
        $code = ((string) $row['code'] === '') ? '301' : (string) $row['code'];

        $problem = pg_short_link_name_problem($name);

        if ($problem === '') {
            $problem = pg_short_link_url_problem($url);
        }

        if (($problem === '') && ((preg_match('/^\d+$/', $code) !== 1) || !in_array((int) $code, pg_short_link_answer_codes(), true))) {
            $problem = lang('The code must be 301, 302 or 0; an empty code is 301.');
        }

        $key = pg_short_link_key($name);
        $existing = ($problem === '') && isset($by_key[$key]) ? $by_key[$key] : null;

        if ($existing !== null) {
            if (!$replace) {
                $summary['skipped']++;
                continue;
            }

            if ((string) $existing['destination_type'] !== 'url') {
                $problem = lang('A short link to a page, product or file has this name; it is changed on its own screen.');
            }
        } elseif (($problem === '') && (check_name_availability(array('name' => $name)) == false)) {
            $problem = lang('The name that you entered is already in use, so please enter a different name.');
        }

        $link = array('id' => ($existing !== null) ? (int) $existing['id'] : 0, 'name' => $name, 'destination_type' => 'url', 'url' => $url);

        if ($problem === '') {
            $answer = pg_short_link_redirect_columns($link, (int) $code, 0, $prepared);

            if ($answer['problem'] !== null) {
                $problem = $answer['problem']['message'];
            }
        }

        if ($problem !== '') {
            $summary['errors'][] = array('line' => $line, 'message' => $problem);
            continue;
        }

        $columns = "url = '" . escape($url) . "',
            redirect_code = '" . (int) $answer['columns']['redirect_code'] . "',
            is_pattern = '" . (int) $answer['columns']['is_pattern'] . "',
            last_modified_user_id = '" . (int) $user_id . "',
            last_modified_timestamp = UNIX_TIMESTAMP()";

        if ($existing !== null) {
            db("UPDATE short_links SET " . $columns . " WHERE id = '" . (int) $existing['id'] . "'");
            $summary['updated']++;
        } else {
            db("INSERT INTO short_links SET
                name = '" . escape($name) . "',
                destination_type = 'url',
                " . ($modes ? "link_mode = 'permanent'," : "") . "
                created_user_id = '" . (int) $user_id . "',
                created_timestamp = UNIX_TIMESTAMP(),
                " . $columns);

            $link['id'] = (int) mysqli_insert_id(db::$con);
            $summary['added']++;
        }

        pg_short_link_prepared_put($prepared, $link);
        $by_key[$key] = $link;
    }

    return $summary;
}
