<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Front-end translation - the site search on a page served in another
 * language: the visual pages whose translated texts hold every typed word,
 * with i, I, ı and İ read as one letter.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/** At most this many words of a search are compared. */
define('PG_TR_SEARCH_MAX_WORDS', 8);

/**
 * A text as the search compares it in PHP: lower case, i, I, ı and İ one
 * letter (_pg_search_fold()), and the marks of Latin letters dropped as the
 * database's collation drops them (ş as s, ü as u), so a row the query read
 * is not lost here. Pure.
 *
 * @param string $text
 * @return string
 */
function pg_tr_search_fold($text)
{
    return pg_transliterate_to_ascii(_pg_search_fold($text));
}

/**
 * The words of a search typed on a page in another language: the text cut at
 * spaces, a quoted phrase kept whole, operators and lone signs dropped, each
 * word once (compared folded), at most eight. Every word comes with its
 * readings of pg_search_i_variants() for the database, whose collation keeps
 * ı apart from i. Pure.
 *
 * @param string $query
 * @return array of array('text' => string, 'readings' => string[])
 */
function pg_tr_search_words($query)
{
    if (!preg_match_all('/"([^"]*)"|(\S+)/u', (string) $query, $matches, PREG_SET_ORDER)) {
        return array();
    }

    $out = array();
    $seen = array();

    foreach ($matches as $m) {
        $text = _pg_search_phrase_text(isset($m[2]) && ($m[2] !== '') ? $m[2] : $m[1]);
        $key = pg_tr_search_fold($text);

        if (($text === '') || isset($seen[$key])) {
            continue;
        }

        $seen[$key] = true;
        $out[] = array('text' => $text, 'readings' => pg_search_i_variants($text));

        if (count($out) >= PG_TR_SEARCH_MAX_WORDS) {
            break;
        }
    }

    return $out;
}

/**
 * The SQL condition a translated text must meet to be read for a search: any
 * reading of any word inside it; '' without words.
 *
 * @param string $column
 * @param array  $words pg_tr_search_words()
 * @return string
 */
function pg_tr_search_like_sql($column, array $words)
{
    $likes = array();

    foreach ($words as $word) {
        foreach ($word['readings'] as $reading) {
            $likes[] = $column . " LIKE '%" . e(escape_like($reading)) . "%'";
        }
    }

    return $likes ? '(' . implode(' OR ', array_unique($likes)) . ')' : '';
}

/**
 * The pages a search finds among the translated texts read for it, best
 * first. A page is found when every word is in its title, description and
 * body taken together. The pages holding the whole typed phrase come first;
 * then the most hits, a hit in the title or the description counting three
 * times; then the lower page id. Compared folded (pg_tr_search_fold()). Pure.
 *
 * @param array $texts page_id => array('seo' => string, 'body' => string), plain text
 * @param array $words pg_tr_search_words()
 * @return int[] page ids
 */
function pg_tr_search_rank_pages(array $texts, array $words)
{
    if (!$words) {
        return array();
    }

    $folded_words = array();

    foreach ($words as $word) {
        $folded_words[] = pg_tr_search_fold($word['text']);
    }

    $phrase = implode(' ', $folded_words);
    $rows = array();

    foreach ($texts as $pid => $text) {
        $seo = pg_tr_search_fold(isset($text['seo']) ? $text['seo'] : '');
        $body = pg_tr_search_fold(isset($text['body']) ? $text['body'] : '');
        $hits = 0;

        foreach ($folded_words as $word) {
            $found = 3 * mb_substr_count($seo, $word) + mb_substr_count($body, $word);

            if ($found === 0) {
                continue 2;
            }

            $hits += $found;
        }

        $whole = (count($folded_words) < 2) || (mb_strpos($seo . "\n" . $body, $phrase) !== false);
        $rows[] = array((int) $pid, $whole ? 1 : 0, $hits);
    }

    usort($rows, function ($a, $b) {
        if ($a[1] !== $b[1]) {
            return $b[1] - $a[1];
        }

        if ($a[2] !== $b[2]) {
            return $b[2] - $a[2];
        }

        return $a[0] - $b[0];
    });

    $out = array();

    foreach ($rows as $row) {
        $out[] = $row[0];
    }

    return $out;
}

/**
 * The words an excerpt is centred on: the whole phrase where the text holds
 * it, else the first word the text holds, else the typed text. Pure.
 *
 * @param string $text plain text
 * @param array  $words pg_tr_search_words()
 * @param string $query
 * @return string
 */
function pg_tr_search_excerpt_needle($text, array $words, $query)
{
    $candidates = array();

    if (count($words) > 1) {
        $candidates[] = implode(' ', array_map(function ($w) { return $w['text']; }, $words));
    }

    foreach ($words as $word) {
        foreach ($word['readings'] as $reading) {
            $candidates[] = $reading;
        }
    }

    foreach ($candidates as $needle) {
        if (mb_stripos((string) $text, $needle) !== false) {
            return $needle;
        }
    }

    return (string) $query;
}

/**
 * The site search on a page served in another language: the visual pages
 * whose translated texts (body, title, description) hold every word, best
 * first. What the visitor may see is the caller's to check.
 *
 * @param string $query
 * @param int    $limit
 * @return array of array(page_id, name, folder_id, title, excerpt)
 */
function pg_tr_search_pages_find($query, $limit = 100)
{
    $language = pg_tr_language();
    $words = pg_tr_search_words($query);

    if (($language === '') || !$words) {
        return array();
    }

    $rows = db_items("SELECT u.owner_id AS page_id, u.owner_type, t.text
                      FROM translations t
                      INNER JOIN translation_uses u ON u.string_id = t.string_id
                      WHERE t.language = '" . e($language) . "'
                        AND u.owner_type IN ('page', 'page_seo')
                        AND " . pg_tr_search_like_sql('t.text', $words) . "
                      LIMIT 2000");

    $texts = array();

    foreach ((array) $rows as $row) {
        $pid = (int) $row['page_id'];
        $plain = html_entity_decode(strip_tags((string) $row['text']), ENT_QUOTES, 'UTF-8');
        $part = ($row['owner_type'] === 'page_seo') ? 'seo' : 'body';

        if (!isset($texts[$pid])) {
            $texts[$pid] = array('seo' => '', 'body' => '');
        }

        $texts[$pid][$part] .= ($texts[$pid][$part] === '' ? '' : "\n") . $plain;
    }

    $ranked = pg_tr_search_rank_pages($texts, $words);

    if (!$ranked) {
        return array();
    }

    $binned = function_exists('pg_designer_not_binned_sql') ? pg_designer_not_binned_sql('page.page_folder') : '';
    $pages = db_items("SELECT page.page_id, page.page_name, page.page_folder, page.page_title, page.page_meta_description
                       FROM page
                       LEFT JOIN folder ON page.page_folder = folder.folder_id
                       WHERE page.page_search = '1' AND folder.folder_archived = '0'
                         AND page.page_id IN (" . implode(',', array_map('intval', $ranked)) . ")$binned", 'page_id');

    $out = array();

    foreach ($ranked as $pid) {
        if (!isset($pages[$pid])) {
            continue;
        }

        $page = $pages[$pid];
        $description = pg_tr_render_text((string) $page['page_meta_description'], 'text', 'seo');
        $excerpt = ($description !== (string) $page['page_meta_description']) ? $description : '';
        $body = $texts[$pid]['body'];

        if (($excerpt === '') && ($body !== '')) {
            $excerpt = function_exists('_pg_sw_snippet') ? _pg_sw_snippet($body, pg_tr_search_excerpt_needle($body, $words, $query)) : mb_substr($body, 0, 200);
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
