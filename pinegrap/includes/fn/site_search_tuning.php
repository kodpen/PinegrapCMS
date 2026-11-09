<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Site search tuning: synonyms and promoted results, edited under Site
 * Settings › Features › Search Tuning and applied by the search results
 * widget (_pg_sw_search_collect()).
 *
 * Synonyms are groups of words that mean the same to the visitor ("kargo,
 * teslimat, gönderi"): a search for one also searches the others
 * (pg_search_expand()), and their results are added after the ones of the
 * words typed. Promoted results pin an address to search terms ("iade,
 * return => iade-kosullari"): a search for one of the terms lists it first,
 * labelled "Recommended". Both are kept as JSON in a config_text row
 * (search_synonyms, search_promoted): config takes no new column.
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
 * Synonym groups kept at most, and words in a group.
 */
define('PG_SEARCH_SYNONYM_GROUPS_MAX', 200);
define('PG_SEARCH_SYNONYM_WORDS_MAX', 20);

/**
 * Promoted results kept at most, and terms on one.
 */
define('PG_SEARCH_PROMOTED_MAX', 200);
define('PG_SEARCH_PROMOTED_TERMS_MAX', 20);

/**
 * Searches one query is expanded to, the typed one included.
 */
define('PG_SEARCH_EXPAND_MAX', 4);

/**
 * Synonym groups from the settings box: one group per line, words or phrases
 * separated by commas. Each word in the rollup's form (pg_site_search_term()),
 * once per group; a line with fewer than two words is no group. Pure.
 *
 * @param mixed $text
 * @return array list of lists of words
 */
function pg_search_synonyms_parse($text)
{
    $groups = array();

    foreach (preg_split('/\R/u', is_string($text) ? $text : '') as $line) {
        $words = array();

        foreach (explode(',', $line) as $word) {
            $word = pg_site_search_term($word);

            if (($word !== '') && !in_array($word, $words, true)) {
                $words[] = $word;
            }
        }

        if (count($words) >= 2) {
            $groups[] = array_slice($words, 0, PG_SEARCH_SYNONYM_WORDS_MAX);
        }

        if (count($groups) >= PG_SEARCH_SYNONYM_GROUPS_MAX) {
            break;
        }
    }

    return $groups;
}

/**
 * Synonym groups as the settings box shows them. Pure.
 *
 * @param array $groups
 * @return string
 */
function pg_search_synonyms_format($groups)
{
    $lines = array();

    foreach ((array) $groups as $group) {
        if (is_array($group)) {
            $lines[] = implode(', ', array_map('strval', $group));
        }
    }

    return implode("\n", $lines);
}

/**
 * The searches a query is run as: the typed one first, then the query with a
 * word (or the whole query) that is in a synonym group replaced by each other
 * word of the group, at most $max in all, each once. A whole-query match
 * comes before single words. Pure.
 *
 * @param mixed $query
 * @param array $groups pg_search_synonyms_parse()
 * @param int   $max
 * @return array list of queries; empty for an empty query
 */
function pg_search_expand($query, $groups, $max = PG_SEARCH_EXPAND_MAX)
{
    $query = pg_site_search_term($query);

    if ($query === '') {
        return array();
    }

    $out = array($query);
    $max = max(1, (int) $max);
    $add = function ($alt) use (&$out, $max) {
        if ((count($out) < $max) && ($alt !== '') && !in_array($alt, $out, true)) {
            $out[] = $alt;
        }
    };

    $groups = is_array($groups) ? $groups : array();

    foreach ($groups as $group) {
        if (is_array($group) && in_array($query, $group, true)) {
            foreach ($group as $word) {
                $add((string) $word);
            }
        }
    }

    $words = explode(' ', $query);

    if (count($words) > 1) {
        foreach ($words as $i => $word) {
            foreach ($groups as $group) {
                if (!is_array($group) || !in_array($word, $group, true)) {
                    continue;
                }

                foreach ($group as $other) {
                    $alt = $words;
                    $alt[$i] = (string) $other;
                    $add(implode(' ', $alt));
                }
            }
        }
    }

    return $out;
}

/**
 * Promoted results from the settings box: one per line, "terms => address"
 * or "terms => address | title". Terms are separated by commas and kept in
 * the rollup's form; the address is a path on this site, written without
 * the site's directory (a page name, "urunler/kahve", a record address), a
 * leading "/" dropped; a full address, "//", "..", a scheme, a backslash or
 * markup (<, >, ") makes the line no entry; pg_search_promoted_rejected()
 * names those lines. Pure.
 *
 * @param mixed $text
 * @return array list of array('terms' => list, 'url' => path, 'title' => string)
 */
function pg_search_promoted_parse($text)
{
    $entries = array();

    foreach (preg_split('/\R/u', is_string($text) ? $text : '') as $line) {
        $parts = explode('=>', $line, 2);

        if (count($parts) !== 2) {
            continue;
        }

        $terms = array();

        foreach (explode(',', $parts[0]) as $term) {
            $term = pg_site_search_term($term);

            if (($term !== '') && !in_array($term, $terms, true)) {
                $terms[] = $term;
            }
        }

        $target = explode('|', $parts[1], 2);
        $url = trim($target[0]);
        // "//host" names another site.
        $url = (substr($url, 0, 2) === '//') ? '' : ltrim($url, '/');
        $title = isset($target[1]) ? trim(preg_replace('/\s+/u', ' ', $target[1])) : '';

        if (!$terms || ($url === '') || (mb_strlen($url, 'UTF-8') > 255)
            || preg_match('#^[a-z][a-z0-9+.\-]*:|\\\\|(^|/)\.\.(/|$)|\s|[<>"]#i', $url)) {
            continue;
        }

        $entries[] = array(
            'terms' => array_slice($terms, 0, PG_SEARCH_PROMOTED_TERMS_MAX),
            'url'   => $url,
            'title' => mb_substr($title, 0, 200, 'UTF-8'),
        );

        if (count($entries) >= PG_SEARCH_PROMOTED_MAX) {
            break;
        }
    }

    return $entries;
}

/**
 * The lines of the promoted-results box that make no entry: no "=>", no
 * term, or an address that is not a path on this site. Blank lines are not
 * counted. The save keeps the other lines and names these, so a line never
 * disappears without a word. Pure.
 *
 * @param mixed $text
 * @return array list of trimmed lines
 */
function pg_search_promoted_rejected($text)
{
    $rejected = array();

    foreach (preg_split('/\R/u', is_string($text) ? $text : '') as $line) {
        $line = trim($line);

        if (($line !== '') && !pg_search_promoted_parse($line)) {
            $rejected[] = $line;
        }
    }

    return $rejected;
}

/**
 * Promoted results as the settings box shows them. Pure.
 *
 * @param array $entries
 * @return string
 */
function pg_search_promoted_format($entries)
{
    $lines = array();

    foreach ((array) $entries as $entry) {
        if (!is_array($entry) || empty($entry['terms']) || !isset($entry['url'])) {
            continue;
        }

        $line = implode(', ', (array) $entry['terms']) . ' => ' . $entry['url'];

        if (isset($entry['title']) && ($entry['title'] !== '')) {
            $line .= ' | ' . $entry['title'];
        }

        $lines[] = $line;
    }

    return implode("\n", $lines);
}

/**
 * The promoted results of a query: the entries one of whose terms is the
 * query, in the order of the settings, each address once. Pure.
 *
 * @param mixed $query
 * @param array $entries pg_search_promoted_parse()
 * @return array
 */
function pg_search_promoted_for($query, $entries)
{
    $query = pg_site_search_term($query);
    $out = array();
    $seen = array();

    if ($query === '') {
        return $out;
    }

    foreach ((array) $entries as $entry) {
        if (is_array($entry) && isset($entry['url']) && in_array($query, (array) ($entry['terms'] ?? array()), true)
            && !isset($seen[$entry['url']])) {
            $seen[$entry['url']] = true;
            $out[] = $entry;
        }
    }

    return $out;
}

/**
 * A config_text JSON list, read once per request.
 *
 * @param string $name
 * @return array
 */
function _pg_search_tuning_read($name)
{
    if (!function_exists('pg_config_text')) {
        return array();
    }

    $data = json_decode(pg_config_text($name), true);

    return is_array($data) ? array_values($data) : array();
}

/**
 * The site's synonym groups.
 *
 * @return array
 */
function pg_search_synonyms()
{
    return _pg_search_tuning_read('search_synonyms');
}

/**
 * The site's promoted results.
 *
 * @return array
 */
function pg_search_promoted()
{
    return _pg_search_tuning_read('search_promoted');
}

/**
 * Writes the two settings boxes, each as parsed; an empty box removes its
 * row.
 *
 * @param mixed $synonyms_text the box as posted
 * @param mixed $promoted_text the box as posted
 * @return bool false when a write failed
 */
function pg_search_tuning_save($synonyms_text, $promoted_text)
{
    $synonyms = pg_search_synonyms_parse($synonyms_text);
    $promoted = pg_search_promoted_parse($promoted_text);

    $ok = pg_config_text_save('search_synonyms', $synonyms ? (string) json_encode($synonyms, JSON_UNESCAPED_UNICODE) : '');

    return pg_config_text_save('search_promoted', $promoted ? (string) json_encode($promoted, JSON_UNESCAPED_UNICODE) : '') && $ok;
}

/**
 * A result row of the search widget for a promoted address: a page the
 * visitor may not view, or one left out of site search, is no row (null).
 * The title is the one given, else the page's title, else the address.
 *
 * @param array $entry pg_search_promoted_for()
 * @return array|null
 */
function pg_search_promoted_row(array $entry)
{
    $path = defined('PATH') ? PATH : '/';
    $host = (defined('URL_SCHEME') ? URL_SCHEME : '') . (defined('HOSTNAME') ? HOSTNAME : '');
    $address = (string) $entry['url'];
    $name = (string) strtok($address, '?#');
    $title = (string) ($entry['title'] ?? '');
    $excerpt = '';

    $page = (strpos($name, '/') === false)
        ? db_item("SELECT page.page_title, page.page_meta_description, page.page_folder, page.page_search
                   FROM page WHERE page.page_name = '" . e($name) . "' LIMIT 1")
        : null;

    if (is_array($page)) {
        if (((string) $page['page_search'] !== '1') || !check_view_access($page['page_folder'], true)) {
            return null;
        }

        if ($title === '') {
            $title = (string) $page['page_title'];
        }

        $excerpt = (string) $page['page_meta_description'];
    }

    $url = $path . encode_url_path($name) . substr($address, strlen($name));

    // Another page's words: they never keep the results page out of the
    // search engines (pg_tr_data_text()).
    if (function_exists('pg_tr_data_text')) {
        $title = pg_tr_data_text($title, 'text', 'seo');
        $excerpt = pg_tr_data_text($excerpt, 'text', 'seo');
    }

    return array(
        'title'      => ($title !== '') ? $title : $host . $url,
        'url'        => $url,
        'full_url'   => $host . $url,
        'excerpt'    => $excerpt,
        'type'       => 'page',
        'type_label' => lang('Recommended'),
        'image'      => '',
        'price'      => '',
        'name'       => $name,
        'section'    => '',
        'promoted'   => true,
    );
}

/**
 * A search's results with the synonyms' results added and the promoted
 * results put first. $found is _pg_sw_search_collect()'s answer for the
 * typed query; $collect runs one more search (a query in, the same shape
 * out). A result already listed is not listed again; a promoted address
 * leaves the lists below it.
 *
 * @param string   $query
 * @param array    $found
 * @param callable $collect
 * @param array    $groups   pg_search_synonyms()
 * @param array    $promoted pg_search_promoted()
 * @param callable $row      promoted entry => row or null
 * @return array
 */
function pg_search_tuning_apply($query, array $found, $collect, $groups, $promoted, $row)
{
    $seen = array();

    foreach (array('featured', 'catalog', 'pages') as $list) {
        foreach ($found[$list] as $r) {
            $seen[$r['url']] = true;
        }
    }

    foreach (array_slice(pg_search_expand($query, $groups), 1) as $alt) {
        $more = $collect($alt);

        foreach (array('featured', 'catalog', 'pages') as $list) {
            foreach (isset($more[$list]) ? $more[$list] : array() as $r) {
                if (!isset($seen[$r['url']])) {
                    $seen[$r['url']] = true;
                    $found[$list][] = $r;
                }
            }
        }

        if (!empty($more['limited'])) {
            $found['limited'] = true;
        }
    }

    $pinned = array();

    foreach (pg_search_promoted_for($query, $promoted) as $entry) {
        $r = $row($entry);

        if (is_array($r)) {
            $pinned[$r['url']] = $r;
        }
    }

    if ($pinned) {
        foreach (array('featured', 'catalog', 'pages') as $list) {
            $found[$list] = array_values(array_filter($found[$list], function ($r) use ($pinned) {
                return !isset($pinned[$r['url']]);
            }));
        }

        $found['featured'] = array_merge(array_values($pinned), $found['featured']);
    }

    return $found;
}

/**
 * The promoted box with a line started for a term ("term => "), for the
 * site search report's "promote" button: nothing added for an empty term or
 * one a line already promotes. Pure.
 *
 * @param string $text the box as drawn
 * @param mixed  $term
 * @return string
 */
function pg_search_promoted_add_line($text, $term)
{
    $text = (string) $text;
    $term = pg_site_search_term($term);

    if (($term === '') || pg_search_promoted_for($term, pg_search_promoted_parse($text))) {
        return $text;
    }

    // A comma or "=>" in the term would split it into other terms.
    $term = trim(str_replace(array(',', '=>', '|'), ' ', $term));

    return (($text === '') ? '' : rtrim($text, "\n") . "\n") . $term . ' => ';
}
