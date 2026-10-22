<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Front-end translation - what search engines and the site search see of the
 * other languages: the language addresses in sitemap.xml with their
 * alternates, which of them may be offered at all (and what the pages
 * answered when they were drawn), the robots.txt rules repeated under every
 * language directory, and the site search on a page served in another
 * language.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/** Google reads at most this many addresses from one sitemap file. */
define('PG_TR_SITEMAP_LIMIT', 50000);

/**
 * The enabled target languages, keyed by code.
 *
 * @return array code => site_languages row
 */
function pg_tr_target_languages()
{
    $source = pg_tr_source_language();
    $targets = array();

    foreach (pg_tr_languages() as $code => $row) {
        if ($code !== $source) {
            $targets[$code] = $row;
        }
    }

    return $targets;
}

/**
 * The page an address of this site is, when it is one: the home page for the
 * root, the page of that name for "/name". Deeper addresses (an item below a
 * catalog or form page) are not pages of their own and answer 0.
 *
 * @param string $url absolute address, unescaped
 * @return int page id or 0
 */
function pg_tr_page_id_of_url($url)
{
    return pg_tr_page_id_of_path($url, PATH, pg_tr_page_index());
}

/**
 * pg_tr_page_id_of_url() without the database: the page an address is, given
 * the site's root path and its pages (pg_tr_page_index()). The root itself,
 * with or without its closing slash, is the home page.
 *
 * @param string $url   absolute address, unescaped
 * @param string $root  PATH, with its closing slash
 * @param array  $index names (lower-cased name => page id), home (page id)
 * @return int page id or 0
 */
function pg_tr_page_id_of_path($url, $root, $index)
{
    $path = (string) parse_url($url, PHP_URL_PATH);
    $path = rawurldecode(($path === '') ? '/' : $path);
    $root = (string) $root;

    // An address without a path (https://example.com) is the root too.
    if (rtrim($path, '/') === rtrim($root, '/')) {
        return isset($index['home']) ? (int) $index['home'] : 0;
    }

    if (strpos($path, $root) !== 0) {
        return 0;
    }

    $rest = trim(substr($path, strlen($root)), '/');

    if ($rest === '') {
        return isset($index['home']) ? (int) $index['home'] : 0;
    }

    if (strpos($rest, '/') !== false) {
        return 0;
    }

    $key = mb_strtolower($rest, 'UTF-8');

    return isset($index['names'][$key]) ? (int) $index['names'][$key] : 0;
}

/**
 * Whether a language's address of a page may be offered to the search
 * engines - listed in sitemap.xml, named as an alternate on any page - under
 * the language's index policy (pg_tr_index_decide()). For "reviewed" the
 * page answers with its own robots meta, which weighs every text drawn on it
 * (shared components, menus, the records a list shows, the catalog); what it
 * answered the last time it was drawn in the language is kept
 * (pg_tr_page_verdict_keep()) and read here. An address below a page, one
 * with a query string, and a page not drawn in the language since its answer
 * was dropped have none, and are not offered.
 *
 * @param array  $row      site_languages row
 * @param string $url      the source address, unescaped
 * @param bool   $one_page true when only this page is asked about (the
 *                         alternates in a page's head): reads its rows alone
 *                         instead of the whole language's
 * @return bool
 */
function pg_tr_indexable($row, $url, $one_page = false)
{
    $policy = (string) $row['index_policy'];
    $verdict = null;

    if (($policy !== 'none') && ($policy !== 'all')) {
        $page_id = pg_tr_page_id_of_url($url);

        if (($page_id > 0) && ((string) parse_url($url, PHP_URL_QUERY) === '')) {
            $verdicts = pg_tr_page_verdicts((string) $row['code'], $one_page ? $page_id : 0);
            $verdict = isset($verdicts[$page_id]) ? $verdicts[$page_id] : null;
        }
    }

    return pg_tr_index_decide($policy, $verdict);
}

/**
 * The one rule behind sitemap.xml and every hreflang alternate: never for the
 * "none" policy, always for "all", and for "reviewed" only when the page's
 * own address answered "index" the last time it was drawn in the language.
 * No answer is no: the texts a page draws from elsewhere (records, the
 * catalog, data values) are known only by drawing it, so counting the
 * page's own texts instead listed addresses whose robots meta said noindex.
 *
 * @param string   $policy  site_languages.index_policy
 * @param int|null $verdict 1 index, 0 noindex, null not known
 * @return bool
 */
function pg_tr_index_decide($policy, $verdict)
{
    if ($policy === 'none') {
        return false;
    }

    if ($policy === 'all') {
        return true;
    }

    return ($verdict === 1);
}

/**
 * Whether a page's robots meta keeps it out of the search engines: a
 * "noindex" directive, or "none" (noindex and nofollow together). Only whole
 * comma-separated directives count, so the value of "max-image-preview:none"
 * does not.
 *
 * @param string $html the page
 * @return bool
 */
function pg_tr_robots_says_noindex($html)
{
    if (!preg_match('~<meta\s+name="robots"\s+content="([^"]*)"~i', (string) $html, $match)) {
        return false;
    }

    return (bool) preg_match('~(?:^|,)\s*(?:noindex|none)\s*(?:,|$)~i', $match[1]);
}

/**
 * Whether the schema keeps what a page answered (page_translations.indexable,
 * 2026.4.9). Code that runs before the database upgrade asks first.
 *
 * @return bool
 */
function pg_tr_page_verdict_ready()
{
    static $ready = null;

    if ($ready === null) {
        $ready = function_exists('pg_schema_has') && pg_schema_has('page_translations', 'indexable');
    }

    return $ready;
}

/**
 * What pages answered the search engines the last time they were drawn in a
 * language under the reviewed policy: 1 indexable, 0 noindex. A page not
 * drawn since something that could turn its answer to noindex changed has
 * none: pg_tr_invalidate_pages() drops it with the drawn body (a translation
 * taken back or overwritten, the page or a component it places saved, the
 * caches purged); a translation that can only let it be indexed keeps a
 * "1".
 *
 * @param string $language
 * @param int    $page_id  one page (read with its other languages), or 0 for
 *                         every page of the language
 * @return array page id => 0|1
 */
function pg_tr_page_verdicts($language, $page_id = 0)
{
    static $by_language = array();
    static $by_page = array();

    if (!pg_tr_page_verdict_ready()) {
        return array();
    }

    // Answers given under older rules are not read.
    pg_tr_render_rules_check();

    $language = (string) $language;
    $page_id = (int) $page_id;

    if ($page_id > 0) {
        if (!isset($by_page[$page_id])) {
            $by_page[$page_id] = array();

            foreach (db_items("SELECT language, indexable FROM page_translations
                               WHERE page_id = '$page_id' AND indexable IS NOT NULL") as $row) {
                $by_page[$page_id][(string) $row['language']] = (int) $row['indexable'];
            }
        }

        return isset($by_page[$page_id][$language]) ? array($page_id => $by_page[$page_id][$language]) : array();
    }

    if (!isset($by_language[$language])) {
        $by_language[$language] = array();

        foreach (db_items("SELECT page_id, indexable FROM page_translations
                           WHERE language = '" . e($language) . "' AND indexable IS NOT NULL") as $row) {
            $by_language[$language][(int) $row['page_id']] = (int) $row['indexable'];
        }
    }

    return $by_language[$language];
}

/**
 * Keeps what a page drawn in a language answered the search engines, for
 * pg_tr_indexable(). Only a page's own address answered in full counts: an
 * address with a query string (a filtered list, one event), one below a page
 * (a catalog item) or an error answer is not the page's. Written only when
 * the answer changed.
 *
 * @param string $url       the source address of the page, unescaped
 * @param string $language
 * @param bool   $indexable
 * @return int|null the answer the address now holds, 1 or 0; null when it
 *                  holds none, which pg_tr_indexable() reads as "not offered"
 */
function pg_tr_page_verdict_keep($url, $language, $indexable)
{
    if (!pg_tr_page_verdict_ready() || ((string) parse_url($url, PHP_URL_QUERY) !== '')) {
        return null;
    }

    $status = function_exists('http_response_code') ? http_response_code() : false;

    if (($status !== false) && ((int) $status !== 200)) {
        return null;
    }

    $page_id = pg_tr_page_id_of_url($url);

    if ($page_id === 0) {
        return null;
    }

    $value = $indexable ? 1 : 0;
    $known = pg_tr_page_verdicts($language, $page_id);

    if (isset($known[$page_id]) && ($known[$page_id] === $value)) {
        return $value;
    }

    // A page without a drawn body (one that is not built in the visual
    // editor, or whose body was dropped) gets a row with an empty one, which
    // the body cache never serves.
    db("INSERT INTO page_translations (page_id, language, tree_code, indexable)
        VALUES ('$page_id', '" . e($language) . "', '', '$value')
        ON DUPLICATE KEY UPDATE indexable = VALUES(indexable)");

    return $value;
}

/**
 * sitemap.xml with the other languages: every listed address gets its
 * alternates (xhtml:link, the source, each language whose address may be
 * indexed, and x-default on the source), and each such language address is
 * listed as an entry of its own carrying the same alternates, the way Google
 * asks for them. A site without target languages gets its sitemap unchanged.
 *
 * @param string $content the sitemap as get_sitemap_info() built it
 * @return string
 */
function pg_tr_sitemap_render($content)
{
    $targets = pg_tr_target_languages();

    if (!$targets) {
        return $content;
    }

    $source = pg_tr_source_language();
    $count = substr_count($content, '<url>');
    $added = false;

    $content = preg_replace_callback('~([ \t]*)<url>\s*<loc>([^<]+)</loc>(.*?)</url>\n?~s', function ($match) use ($targets, $source, &$count, &$added) {
        $indent = $match[1];
        $loc = html_entity_decode($match[2], ENT_QUOTES | ENT_XML1, 'UTF-8');
        $rest = $match[3];
        $versions = array();

        foreach ($targets as $code => $row) {
            if (!pg_tr_indexable($row, $loc)) {
                continue;
            }

            $url = pg_tr_alternate_url($loc, $code);

            if (($url !== '') && ($url !== $loc)) {
                $versions[$code] = $url;
            }
        }

        if (!$versions) {
            return $match[0];
        }

        $added = true;

        $links = $indent . '    <xhtml:link rel="alternate" hreflang="' . h($source) . '" href="' . h($loc) . '"/>' . "\n";

        foreach ($versions as $code => $url) {
            $links .= $indent . '    <xhtml:link rel="alternate" hreflang="' . h($code) . '" href="' . h($url) . '"/>' . "\n";
        }

        $links .= $indent . '    <xhtml:link rel="alternate" hreflang="x-default" href="' . h($loc) . '"/>' . "\n";

        // The lastmod of the source entry holds for its translations too:
        // a translation changes the page only together with its source.
        $lastmod = '';

        if (preg_match('~<lastmod>[^<]*</lastmod>~', $rest, $lm)) {
            $lastmod = $indent . '    ' . $lm[0] . "\n";
        }

        $out = $indent . '<url>' . "\n"
             . $indent . '    <loc>' . h($loc) . '</loc>' . rtrim($rest) . "\n"
             . $links
             . $indent . '</url>' . "\n";

        foreach ($versions as $code => $url) {
            if ($count >= PG_TR_SITEMAP_LIMIT) {
                break;
            }

            $count++;

            $out .= $indent . '<url>' . "\n"
                  . $indent . '    <loc>' . h($url) . '</loc>' . "\n"
                  . $lastmod
                  . $links
                  . $indent . '</url>' . "\n";
        }

        return $out;
    }, $content);

    if ($added && (strpos($content, 'xmlns:xhtml=') === false)) {
        $content = preg_replace('~<urlset\b~', '<urlset xmlns:xhtml="http://www.w3.org/1999/xhtml"', $content, 1);
    }

    return $content;
}

/**
 * The robots.txt rules for the pages taken out of the search engines,
 * repeated under every language directory: a page hidden at /about is
 * hidden at /en/about too.
 *
 * @param array $rules "Disallow: /path..." lines
 * @return array
 */
function pg_tr_robots_render($rules)
{
    $targets = pg_tr_target_languages();

    if (!$targets || !$rules) {
        return $rules;
    }

    $out = $rules;

    foreach ($rules as $rule) {
        if (!preg_match('~^Disallow:\s*(.*)$~', $rule, $m) || (strpos($m[1], PATH) !== 0)) {
            continue;
        }

        $rest = substr($m[1], strlen(PATH));

        foreach ($targets as $code => $row) {
            $out[] = 'Disallow: ' . PATH . pg_tr_prefix($code) . '/' . $rest;
        }
    }

    return array_values(array_unique($out));
}

/**
 * The site search on a page served in another language: the visual pages
 * whose translated texts (body, title, description) contain the words, best
 * first. What the visitor may see is the caller's to check.
 *
 * @param string $query
 * @param int    $limit
 * @return array of array(page_id, name, folder_id, title, excerpt)
 */
function pg_tr_search_pages_find($query, $limit = 100)
{
    $language = pg_tr_language();
    $query = trim((string) $query);

    if (($language === '') || ($query === '')) {
        return array();
    }

    $like = "'%" . e(escape_like($query)) . "%'";
    $needle = mb_strtolower($query, 'UTF-8');

    $rows = db_items("SELECT u.owner_id AS page_id, u.owner_type, t.text
                      FROM translations t
                      INNER JOIN translation_uses u ON u.string_id = t.string_id
                      WHERE t.language = '" . e($language) . "'
                        AND u.owner_type IN ('page', 'page_seo')
                        AND t.text LIKE $like
                      LIMIT 2000");

    $scores = array();
    $texts = array();

    foreach ((array) $rows as $row) {
        $pid = (int) $row['page_id'];
        $plain = html_entity_decode(strip_tags((string) $row['text']), ENT_QUOTES, 'UTF-8');
        $hits = max(1, mb_substr_count(mb_strtolower($plain, 'UTF-8'), $needle));

        // A word in the title or the description counts for more, as in the
        // source search.
        if ($row['owner_type'] === 'page_seo') {
            $hits *= 3;
        }

        $scores[$pid] = (isset($scores[$pid]) ? $scores[$pid] : 0) + $hits;

        if (!isset($texts[$pid]) && ($row['owner_type'] === 'page')) {
            $texts[$pid] = $plain;
        }
    }

    if (!$scores) {
        return array();
    }

    arsort($scores);

    $binned = function_exists('pg_designer_not_binned_sql') ? pg_designer_not_binned_sql('page.page_folder') : '';
    $pages = db_items("SELECT page.page_id, page.page_name, page.page_folder, page.page_title, page.page_meta_description
                       FROM page
                       LEFT JOIN folder ON page.page_folder = folder.folder_id
                       WHERE page.page_search = '1' AND folder.folder_archived = '0'
                         AND page.page_id IN (" . implode(',', array_map('intval', array_keys($scores))) . ")$binned", 'page_id');

    $out = array();

    foreach ($scores as $pid => $score) {
        if (!isset($pages[$pid])) {
            continue;
        }

        $page = $pages[$pid];
        $description = pg_tr_render_text((string) $page['page_meta_description'], 'text', 'seo');
        $excerpt = ($description !== (string) $page['page_meta_description']) ? $description : '';

        if (($excerpt === '') && isset($texts[$pid])) {
            $excerpt = function_exists('_pg_sw_snippet') ? _pg_sw_snippet($texts[$pid], $query) : mb_substr($texts[$pid], 0, 200);
        }

        $out[] = array(
            'page_id'   => (int) $pid,
            'name'      => (string) $page['page_name'],
            'folder_id' => (int) $page['page_folder'],
            'title'     => (string) $page['page_title'],
            'excerpt'   => $excerpt,
        );

        if (count($out) >= $limit) {
            break;
        }
    }

    return $out;
}

/**
 * A Location header a script sends after a form posted from a page in
 * another language: a page address of this site gets the language's
 * directory, so the visitor lands on the confirmation, the cart or the
 * checkout step in the language they were using.
 */
function pg_tr_redirect_rewrite()
{
    if (!pg_tr_active()) {
        return;
    }

    foreach (headers_list() as $header) {
        if (stripos($header, 'Location:') !== 0) {
            continue;
        }

        $value = trim(substr($header, 9));

        // The rewrite works on attribute values, escaped in and out.
        $rewritten = html_entity_decode(pg_tr_rewrite_url(h($value), pg_tr_prefix(pg_tr_language()), true), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        if ($rewritten !== $value) {
            header('Location: ' . $rewritten, true);
        }
    }
}
