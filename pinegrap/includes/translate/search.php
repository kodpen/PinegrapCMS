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
 * The items (pages, products) a search finds among the translated texts read
 * for it, best first. An item is found when every word is in its texts taken
 * together. The items holding the whole typed phrase come first; then the
 * most hits, a hit in the 'seo' part (a page's title and description, a
 * product's name line, title and keywords) counting three times; then the
 * key in natural order. Compared folded (pg_tr_search_fold()). Pure.
 *
 * @param array $texts key => array('seo' => string, 'body' => string), plain text
 * @param array $words pg_tr_search_words()
 * @return array the keys
 */
function pg_tr_search_rank(array $texts, array $words)
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

    foreach ($texts as $key => $text) {
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
        $rows[] = array($key, $whole ? 1 : 0, $hits);
    }

    usort($rows, function ($a, $b) {
        if ($a[1] !== $b[1]) {
            return $b[1] - $a[1];
        }

        if ($a[2] !== $b[2]) {
            return $b[2] - $a[2];
        }

        return strnatcmp((string) $a[0], (string) $b[0]);
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

    $ranked = pg_tr_search_rank($texts, $words);

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

/**
 * The part of a product's or product group's text a translated field belongs
 * to for the ranking: the line read as its name (short_description), its
 * title and keywords count as 'seo', the rest as 'body'. A field stored in
 * several texts ("full_description#2", "details@3#0") is read by its base.
 * Pure.
 *
 * @param string $field translation_uses.field
 * @return string 'seo' or 'body'
 */
function pg_tr_search_catalog_part($field)
{
    $base = preg_replace('~[@#].*$~', '', (string) $field);

    return in_array($base, array('short_description', 'title', 'keywords'), true) ? 'seo' : 'body';
}

/**
 * The translated texts of the catalog, as pg_tr_search_rank() reads them:
 * keyed "product:<id>" or "product_group:<id>", HTML dropped. Pure.
 *
 * @param array $rows array(owner_type, owner_id, field, text)
 * @return array key => array('seo' => string, 'body' => string)
 */
function pg_tr_search_catalog_texts(array $rows)
{
    $texts = array();

    foreach ($rows as $row) {
        $key = $row['owner_type'] . ':' . (int) $row['owner_id'];
        $plain = html_entity_decode(strip_tags((string) $row['text']), ENT_QUOTES, 'UTF-8');
        $part = pg_tr_search_catalog_part($row['field']);

        if (!isset($texts[$key])) {
            $texts[$key] = array('seo' => '', 'body' => '');
        }

        $texts[$key][$part] .= ($texts[$key][$part] === '' ? '' : "\n") . $plain;
    }

    return $texts;
}

/**
 * The site and catalog search on a page served in another language: the
 * products and product groups whose translated texts hold every word, best
 * first. Which of them may be listed (enabled, inside the searched group) is
 * the caller's to check.
 *
 * @param string $query
 * @param int    $limit
 * @return string[] "product:<id>" and "product_group:<id>" keys
 */
function pg_tr_search_catalog_find($query, $limit = 100)
{
    $language = pg_tr_language();
    $words = pg_tr_search_words($query);

    if (($language === '') || !$words) {
        return array();
    }

    $rows = db_items("SELECT u.owner_type, u.owner_id, u.field, t.text
                      FROM translations t
                      INNER JOIN translation_uses u ON u.string_id = t.string_id
                      WHERE t.language = '" . e($language) . "'
                        AND u.owner_type IN ('product', 'product_group')
                        AND " . pg_tr_search_like_sql('t.text', $words) . "
                      LIMIT 2000");

    return array_slice(pg_tr_search_rank(pg_tr_search_catalog_texts((array) $rows), $words), 0, (int) $limit);
}

/**
 * The record a form_records use belongs to, from its field
 * ("r<record>:<form field>", maybe "#n" or "@n#n" after it); 0 when the
 * field names none. Pure.
 *
 * @param string $field translation_uses.field
 * @return int forms.id
 */
function pg_tr_search_record_id($field)
{
    return preg_match('~^r(\d+):~', (string) $field, $m) ? (int) $m[1] : 0;
}

/**
 * The translated texts of form records, as pg_tr_search_rank() reads them:
 * keyed by record id, every field in the body (which field names a record
 * is the record view's to say). Pure.
 *
 * @param array $rows array(field, text)
 * @return array record id => array('seo' => '', 'body' => string)
 */
function pg_tr_search_record_texts(array $rows)
{
    $texts = array();

    foreach ($rows as $row) {
        $id = pg_tr_search_record_id($row['field']);

        if ($id <= 0) {
            continue;
        }

        $plain = html_entity_decode(strip_tags((string) $row['text']), ENT_QUOTES, 'UTF-8');

        if (!isset($texts[$id])) {
            $texts[$id] = array('seo' => '', 'body' => '');
        }

        $texts[$id]['body'] .= ($texts[$id]['body'] === '' ? '' : "\n") . $plain;
    }

    return $texts;
}

/**
 * Whether a search item is a record's: its address is the record's on the
 * item's page, by address name or by reference code, as
 * pg_sw_search_index_record_url() writes it. Pure.
 *
 * @param string $url       search_items.url
 * @param string $page_name the item's page
 * @param array  $record    reference_code, address_name
 * @return bool
 */
function pg_tr_search_record_item_matches($url, $page_name, array $record)
{
    $url = (string) $url;
    $base = encode_url_path((string) $page_name);
    $code = trim((string) $record['reference_code']);
    $name = trim((string) $record['address_name']);

    if (($code !== '') && ($url === $base . '?r=' . rawurlencode($code))) {
        return true;
    }

    return ($name !== '') && ($url === $base . '/' . encode_url_path($name));
}

/**
 * The site search on a page served in another language: the form records
 * whose translated texts hold every word, best first.
 *
 * @param string $query
 * @param int    $limit
 * @return int[] forms.id
 */
function pg_tr_search_records_find($query, $limit = 100)
{
    $language = pg_tr_language();
    $words = pg_tr_search_words($query);

    if (($language === '') || !$words) {
        return array();
    }

    $rows = db_items("SELECT u.field, t.text
                      FROM translations t
                      INNER JOIN translation_uses u ON u.string_id = t.string_id
                      WHERE t.language = '" . e($language) . "'
                        AND u.owner_type = 'form_records'
                        AND " . pg_tr_search_like_sql('t.text', $words) . "
                      LIMIT 2000");

    return array_slice(pg_tr_search_rank(pg_tr_search_record_texts((array) $rows), $words), 0, (int) $limit);
}

/**
 * The search index items of the records a translated search found, in the
 * records' order: only a record the index holds is listed, at that item's
 * address, and only where the caller's fence lets the item through.
 *
 * @param int[]  $record_ids pg_tr_search_records_find()
 * @param string $fence      SQL on search_items ending in "AND " or ''
 *                           (the visitor's folders), written as it is
 * @param string $live       SQL starting with " AND " (pg_search_items_live_sql())
 * @return array search_items rows (url, title, description, page_id,
 *               page_name, page_title)
 */
function pg_tr_search_record_items(array $record_ids, $fence, $live)
{
    $ids = array_values(array_filter(array_map('intval', $record_ids)));

    if (!$ids) {
        return array();
    }

    $records = db_items("SELECT id, reference_code, address_name FROM forms
                         WHERE id IN (" . implode(',', $ids) . ") AND complete = '1'", 'id');

    $likes = array();

    foreach ((array) $records as $record) {
        if (trim((string) $record['reference_code']) !== '') {
            $likes[] = "search_items.url LIKE '%?r=" . e(escape_like(rawurlencode(trim((string) $record['reference_code'])))) . "'";
        }

        if (trim((string) $record['address_name']) !== '') {
            $likes[] = "search_items.url LIKE '%/" . e(escape_like(encode_url_path(trim((string) $record['address_name'])))) . "'";
        }
    }

    if (!$likes) {
        return array();
    }

    $items = db_items("SELECT search_items.url, search_items.title, search_items.description, search_items.page_id,
                              sp.page_name, sp.page_title
                       FROM search_items LEFT JOIN page AS sp ON sp.page_id = search_items.page_id
                       WHERE $fence (" . implode(' OR ', $likes) . ")$live
                       LIMIT 500");

    $out = array();

    foreach ($ids as $id) {
        if (!isset($records[$id])) {
            continue;
        }

        foreach ((array) $items as $item) {
            if (pg_tr_search_record_item_matches($item['url'], (string) $item['page_name'], $records[$id])) {
                $out[] = $item;
            }
        }
    }

    return $out;
}
