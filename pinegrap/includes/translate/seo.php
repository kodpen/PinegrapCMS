<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Front-end translation - what search engines and the site search see of the
 * other languages: the language addresses in sitemap.xml with their
 * alternates, the robots.txt rules repeated under every language directory,
 * and the site search on a page served in another language.
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
    static $names = null;
    static $home = null;

    if ($names === null) {
        $names = array();

        foreach ((array) db_items("SELECT page_id, page_name, page_home FROM page") as $row) {
            $names[mb_strtolower((string) $row['page_name'], 'UTF-8')] = (int) $row['page_id'];

            if (($home === null) && ($row['page_home'] === 'yes')) {
                $home = (int) $row['page_id'];
            }
        }
    }

    $path = (string) parse_url($url, PHP_URL_PATH);
    $path = rawurldecode(($path === '') ? '/' : $path);

    if (strpos($path, PATH) !== 0) {
        return 0;
    }

    $rest = trim(substr($path, strlen(PATH)), '/');

    if ($rest === '') {
        return (int) $home;
    }

    if (strpos($rest, '/') !== false) {
        return 0;
    }

    $key = mb_strtolower($rest, 'UTF-8');

    return isset($names[$key]) ? $names[$key] : 0;
}

/**
 * Whether a language's address of a page may be listed for the search
 * engines under the language's index policy: never for "none", always for
 * "all", and for "reviewed" only when every text of the page has a reviewed
 * translation - the same rule the page's own robots meta follows.
 *
 * @param array  $row site_languages row
 * @param string $url the source address
 * @return bool
 */
function pg_tr_indexable($row, $url)
{
    static $stats = array();

    $policy = (string) $row['index_policy'];

    if ($policy === 'none') {
        return false;
    }

    if ($policy === 'all') {
        return true;
    }

    $page_id = pg_tr_page_id_of_url($url);

    if ($page_id === 0) {
        return false;
    }

    $code = (string) $row['code'];

    if (!isset($stats[$code])) {
        $stats[$code] = pg_tr_page_stats($code);
    }

    if (!isset($stats[$code][$page_id])) {
        return false;
    }

    $page = $stats[$code][$page_id];

    return ($page['total'] > 0) && ($page['reviewed'] >= $page['total']);
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
