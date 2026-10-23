<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Front-end translation - the Translations screen's own questions: why a
 * page is kept out of the search engines in a language (which of the texts
 * drawn on it have no translation, or one nobody has approved), and the
 * selected texts kept as they are written in the source language and
 * approved in one go.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/** The most texts one "keep as in the source" call writes. */
define('PG_TR_ACCEPT_SOURCE_LIMIT', 500);

/**
 * What a page answered the search engines the last time it was drawn in a
 * language, as the page list shows it.
 *
 * The stored answer is made from the page's texts only; a page its own SEO
 * setting (page.noindex) closes writes noindex in every language anyway, so
 * that state comes first and the stored answer is not read.
 *
 * @param array $verdicts     page id => 0|1 (pg_tr_page_verdicts())
 * @param int   $page_id
 * @param bool  $page_noindex the page's own noindex setting
 * @return string indexed | noindex | unknown (not drawn since the
 *                translations last changed) | own_noindex (closed by the
 *                page's own setting)
 */
function pg_tr_screen_verdict_state($verdicts, $page_id, $page_noindex = false)
{
    if ($page_noindex) {
        return 'own_noindex';
    }

    $page_id = (int) $page_id;

    if (!is_array($verdicts) || !isset($verdicts[$page_id])) {
        return 'unknown';
    }

    return ((int) $verdicts[$page_id] === 1) ? 'indexed' : 'noindex';
}

/**
 * SQL condition on translation_strings s LEFT JOIN translations t: a text
 * that keeps a page out of the search engines under the reviewed policy - no
 * translation at all, or one that is not reviewed and is not the source text
 * itself. The same test the page draws with (pg_tr_render_text()) and the
 * sitemap counts with (pg_tr_index_stats()).
 *
 * @return string
 */
function pg_tr_screen_blocking_sql()
{
    return "(t.id IS NULL OR (t.status <> 'reviewed'
        AND CAST(LOWER(TRIM(t.text)) AS BINARY) <> CAST(LOWER(TRIM(s.source_text)) AS BINARY)))";
}

/**
 * The places the texts that keep a page out come from, one line each: the
 * page itself first, then its title and description, then what it places
 * (shared components, menus, the records a list includes) with the most
 * texts first. Rows of the same place are added up; a place with no text is
 * left out.
 *
 * @param array $rows  array of array(owner_type, owner_id, count)
 * @param array $names 'owner_type:owner_id' => name
 * @return array of array(kind, id, name, count)
 */
function pg_tr_screen_reason_lines($rows, $names = array())
{
    $lines = array();

    foreach ((array) $rows as $row) {
        if (!is_array($row) || !isset($row['owner_type'])) {
            continue;
        }

        $kind = (string) $row['owner_type'];
        $id = isset($row['owner_id']) ? (int) $row['owner_id'] : 0;
        $count = isset($row['count']) ? (int) $row['count'] : 0;

        if ($count <= 0) {
            continue;
        }

        $key = $kind . ':' . $id;

        if (!isset($lines[$key])) {
            $lines[$key] = array(
                'kind'  => $kind,
                'id'    => $id,
                'name'  => isset($names[$key]) ? (string) $names[$key] : '',
                'count' => 0,
            );
        }

        $lines[$key]['count'] += $count;
    }

    $rank = array('page' => 0, 'page_seo' => 1);

    usort($lines, function ($a, $b) use ($rank) {
        $ra = isset($rank[$a['kind']]) ? $rank[$a['kind']] : 2;
        $rb = isset($rank[$b['kind']]) ? $rank[$b['kind']] : 2;

        if ($ra !== $rb) {
            return $ra - $rb;
        }

        if ($a['count'] !== $b['count']) {
            return $b['count'] - $a['count'];
        }

        $by_kind = strcmp($a['kind'], $b['kind']);

        return ($by_kind !== 0) ? $by_kind : strcmp($a['name'], $b['name']);
    });

    return $lines;
}

/**
 * The names of the places a page draws texts from, for the reason lines:
 * a shared component's name, a menu's name, the form page whose records a
 * list includes.
 *
 * @param array $owners array of array(owner_type, owner_id)
 * @return array 'owner_type:owner_id' => name
 */
function pg_tr_screen_owner_names($owners)
{
    $ids = array('shared' => array(), 'menu' => array(), 'form_records' => array());

    foreach ((array) $owners as $owner) {
        if (is_array($owner) && isset($ids[$owner[0]]) && ((int) $owner[1] > 0)) {
            $ids[$owner[0]][(int) $owner[1]] = true;
        }
    }

    $queries = array(
        'shared'       => 'SELECT id, name FROM shared_components WHERE id IN (%s)',
        'menu'         => 'SELECT id, name FROM menus WHERE id IN (%s)',
        'form_records' => 'SELECT page_id AS id, page_name AS name FROM page WHERE page_id IN (%s)',
    );

    $names = array();

    foreach ($queries as $kind => $sql) {
        if (!$ids[$kind]) {
            continue;
        }

        foreach ((array) db_items(sprintf($sql, implode(',', array_keys($ids[$kind])))) as $row) {
            $names[$kind . ':' . (int) $row['id']] = (string) $row['name'];
        }
    }

    return $names;
}

/**
 * Where the texts that keep a page out of the search engines in a language
 * come from: counted over the page's texts as last read and those of what
 * it places (pg_tr_page_owners()).
 *
 * @param string $language
 * @param int    $page_id
 * @return array of array(kind, id, name, count) (pg_tr_screen_reason_lines())
 */
function pg_tr_screen_reasons($language, $page_id)
{
    $owners = pg_tr_page_owners($page_id);
    $owner_where = pg_tr_owner_where($owners);

    $rows = db_items("SELECT u.owner_type, u.owner_id, COUNT(DISTINCT u.string_id) AS count
                      FROM translation_uses u
                      INNER JOIN translation_strings s ON s.id = u.string_id
                      LEFT JOIN translations t ON t.string_id = s.id AND t.language = '" . e($language) . "'
                      WHERE u.string_id > 0 AND ($owner_where) AND " . pg_tr_screen_blocking_sql() . "
                      GROUP BY u.owner_type, u.owner_id");

    return pg_tr_screen_reason_lines(is_array($rows) ? $rows : array(), pg_tr_screen_owner_names($owners));
}

/**
 * For each of the listed texts, one place of the page it is drawn from: the
 * page itself first, then its title and description, then the others by
 * kind and id.
 *
 * @param array $string_ids
 * @param int   $page_id
 * @return array string id => array(kind, id)
 */
function pg_tr_screen_string_owners($string_ids, $page_id)
{
    $string_ids = array_values(array_filter(array_map('intval', (array) $string_ids)));
    $owners = array();

    if (!$string_ids) {
        return $owners;
    }

    $rows = db_items("SELECT u.string_id, u.owner_type, u.owner_id FROM translation_uses u
                      WHERE u.string_id IN (" . implode(',', $string_ids) . ") AND (" . pg_tr_owner_where(pg_tr_page_owners($page_id)) . ")");

    $rank = array('page' => 0, 'page_seo' => 1);
    $sort_key = function ($kind, $owner_id) use ($rank) {
        return sprintf('%d|%s|%010d', isset($rank[$kind]) ? $rank[$kind] : 2, $kind, $owner_id);
    };

    foreach ((array) $rows as $row) {
        $id = (int) $row['string_id'];
        $owner = array('kind' => (string) $row['owner_type'], 'id' => (int) $row['owner_id']);

        if (!isset($owners[$id]) || (strcmp($sort_key($owner['kind'], $owner['id']), $sort_key($owners[$id]['kind'], $owners[$id]['id'])) < 0)) {
            $owners[$id] = $owner;
        }
    }

    return $owners;
}

/**
 * The text ids a request names: positive whole numbers, each once. More
 * than $limit is refused as a whole (an empty answer) rather than cut, so a
 * selection is never half done.
 *
 * @param mixed $value
 * @param int   $limit
 * @return array of int
 */
function pg_tr_screen_string_ids($value, $limit = PG_TR_ACCEPT_SOURCE_LIMIT)
{
    if (!is_array($value)) {
        return array();
    }

    $ids = array();

    foreach ($value as $item) {
        if (is_int($item) || (is_string($item) && preg_match('/^\d{1,10}$/', $item))) {
            $id = (int) $item;

            if ($id > 0) {
                $ids[$id] = $id;
            }
        }
    }

    if (count($ids) > (int) $limit) {
        return array();
    }

    return array_values($ids);
}

/**
 * The source text "keep as in the source" writes as the translation: the
 * sanitized source, only when sanitizing leaves it as it is. A plain text is
 * stored decoded and escaped on output, so the sanitizer's strip_tags() and
 * entity decoding would shorten one like "Size <M>" to "Size"; such a source
 * gets '' and is not written.
 *
 * @param string $source the stored source text
 * @param string $format text | inline
 * @return string the text to write, or '' when it would change or is empty
 */
function pg_tr_accept_source_text($source, $format)
{
    $format = ($format === 'inline') ? 'inline' : 'text';
    $text = pg_tr_sanitize_incoming((string) $source, $format);

    if (($text === '') || ($text !== pg_tr_normalize((string) $source, $format))) {
        return '';
    }

    return $text;
}

/**
 * Keeps the selected texts as they are written in the source language: the
 * source text becomes the translation, approved by hand - a name, a code, a
 * word that reads the same in both languages. A text that already has a
 * reviewed translation is left as it is.
 *
 * @param string $language
 * @param array  $string_ids
 * @param int    $user_id
 * @return array('written' => int, 'kept' => int, 'changed' => int) kept:
 *         reviewed ones left alone; changed: sources the incoming-text
 *         sanitizer would alter, not written (pg_tr_accept_source_text())
 */
function pg_tr_accept_source($language, $string_ids, $user_id = 0)
{
    $result = array('written' => 0, 'kept' => 0, 'changed' => 0);
    $string_ids = array_values(array_filter(array_map('intval', (array) $string_ids)));

    if (!$string_ids) {
        return $result;
    }

    $rows = db_items("SELECT s.id, s.source_text, s.format, t.status
                      FROM translation_strings s
                      LEFT JOIN translations t ON t.string_id = s.id AND t.language = '" . e($language) . "'
                      WHERE s.id IN (" . implode(',', $string_ids) . ")");

    foreach ((array) $rows as $row) {
        if ($row['status'] === 'reviewed') {
            $result['kept']++;
            continue;
        }

        $text = pg_tr_accept_source_text((string) $row['source_text'], (string) $row['format']);

        if ($text === '') {
            if (trim((string) $row['source_text']) !== '') {
                $result['changed']++;
            }

            continue;
        }

        if (pg_tr_save_translation((int) $row['id'], $language, $text, 'manual', 'reviewed', (int) $user_id, 0, true)) {
            $result['written']++;
        }
    }

    return $result;
}
