<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Visual Page Editor - the Tag Cloud system widget (kind 'tag_cloud_region'):
 * the classic <tcloud> region drawn by the designer. It names a search
 * results page and lists the 25 keywords used most by what that page
 * searches (its catalog items, or the site's pages), each a link to the
 * page searching for it. The keywords stay where they are written: on the
 * products and pages (tag_cloud_keywords); the widget reads them on every
 * request.
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
 * The tag cloud's rows, as the classic region draws them: keywords in
 * alphabetical order, each sized from 100% (used least) to 200% (used
 * most), and a weight of 1 to 5 from that size for a design to pick its
 * look by.
 *
 * @param array    $keywords  rows (keyword, count), most used first, as the query returns them
 * @param string   $page_name the search results page's name
 * @param callable $translate the word shown for a keyword (null: the keyword)
 * @return array rows of tokens
 */
function pg_tcw_rows($keywords, $page_name, $translate = null)
{
    $list = array();
    foreach (is_array($keywords) ? $keywords : array() as $row) {
        if (!is_array($row) || !isset($row['keyword'])) continue;
        $list[] = array('keyword' => (string)$row['keyword'], 'count' => (string)(int)(isset($row['count']) ? $row['count'] : 0));
    }
    if (!$list) return array();

    $max = (int)$list[0]['count'];
    $min = (int)$list[count($list) - 1]['count'];
    $step = ($max !== $min) ? 100 / ($max - $min) : 1;
    // The classic region's own sort: by keyword, then by count.
    sort($list);

    $base = (defined('OUTPUT_PATH') ? OUTPUT_PATH : '/') . encode_url_path((string)$page_name) . '?query=';
    $rows = array();
    foreach ($list as $row) {
        $size = (int)round(100 + ((int)$row['count'] - $min) * $step);
        $rows[] = array(
            '__tag_name'    => ($translate !== null) ? (string)call_user_func($translate, $row['keyword']) : $row['keyword'],
            '__tag_keyword' => $row['keyword'],
            '__tag_url'     => $base . urlencode($row['keyword']),
            '__tag_count'   => $row['count'],
            '__tag_size'    => (string)$size,
            '__tag_weight'  => (string)((int)round(($size - 100) / 25) + 1),
        );
    }
    return $rows;
}

/**
 * The 25 keywords used most by what the search results page searches: its
 * catalog items when it searches them, else the site's pages; most used
 * first, as the classic region reads them.
 *
 * @param array $page search_results_pages row joined to page (page_id, search_catalog_items)
 * @return array rows (keyword, count)
 */
function pg_tcw_keywords($page)
{
    if (!is_array($page) || empty($page['page_id'])) return array();
    // A joined list of the page's items: the classic region's OR of every
    // item grows with the catalog.
    $join = ((int)$page['search_catalog_items'] === 1)
        ? "INNER JOIN (SELECT DISTINCT item_id, item_type FROM tag_cloud_keywords_xref
            WHERE search_results_page_id = '" . (int)$page['page_id'] . "') AS items
            ON items.item_id = tag_cloud_keywords.item_id AND items.item_type = tag_cloud_keywords.item_type"
        : '';
    $where = ($join === '') ? "WHERE tag_cloud_keywords.item_type = 'page'" : '';
    return (array)db_items(
        "SELECT tag_cloud_keywords.keyword, COUNT(tag_cloud_keywords.keyword) AS count
        FROM tag_cloud_keywords
        $join
        $where
        GROUP BY tag_cloud_keywords.keyword
        ORDER BY count DESC, tag_cloud_keywords.keyword ASC
        LIMIT 25");
}

// Render a 'tag_cloud_region' system widget.
//
// loop_area: one row per keyword (pg_tcw_rows()). Row tokens:
// ^^__tag_name^^ (translated on a page in another language),
// ^^__tag_keyword^^ (as stored), ^^__tag_url^^ (the search results page
// searching for it), ^^__tag_count^^, ^^__tag_size^^ (100-200, the classic
// font size in percent) and ^^__tag_weight^^ (1-5, for show_if).
// Tokens: ^^__search_page_name^^, ^^__tag_total^^.
// Flags: has_tags, no_tags. Messages: 'tag_cloud_region'.
// cfg: search_page_name (the search results page, by its name as the
// classic tag names it).
function _render_system_widget_tag_cloud_region($tree_json, $widget_id, $cfg = array(), $mode = 'preview')
{
    static $placement = 0;
    $widget_id = (int)$widget_id;
    if ($tree_json === '' || $tree_json === null) return '';
    if (!is_array($cfg)) $cfg = array();
    $tree = json_decode($tree_json, true);
    if (!is_array($tree)) return '';

    $name = isset($cfg['search_page_name']) && is_string($cfg['search_page_name']) ? trim($cfg['search_page_name']) : '';
    $page = ($name !== '') ? db_item(
        "SELECT page.page_id, page.page_name, search_results_pages.search_catalog_items
        FROM search_results_pages
        LEFT JOIN page ON page.page_id = search_results_pages.page_id
        WHERE page.page_name = '" . e($name) . "'") : null;
    $rows = is_array($page) ? pg_tcw_rows(pg_tcw_keywords($page), (string)$page['page_name'],
        defined('FRONTEND_LANGUAGE') ? function ($word) { return pg_tr_text($word, 'text'); } : null) : array();

    _eo_apply_visibility_bindings($tree, array('has_tags' => (bool)$rows, 'no_tags' => !$rows));
    _pg_inject_messages_node($tree, 'tag_cloud_region');

    $split = _split_widget_tree($tree);
    $loop_rendered = '';
    if ($split['loop_children'] !== null) {
        $loop_template = '';
        foreach ($split['loop_children'] as $child) $loop_template .= _render_tree_node($child, 0, 0);
        $loop_template = trim($loop_template);
        foreach ($rows as $i => $row) {
            $row_html = pg_sw_sweep_tokens(_pg_sw_fill_tokens($loop_template, pg_sw_escape_token_values($row)));
            $loop_rendered .= pg_sw_uniquify_row_ids($row_html, $widget_id, $i + 1);
        }
    }

    $static = trim(_render_tree_node($split['static_tree'], 0, 0));
    $tokens = pg_sw_escape_token_values(array(
        '__search_page_name' => is_array($page) ? (string)$page['page_name'] : '',
        '__tag_total'        => (string)count($rows),
    ));
    // A page may place one cloud twice.
    $static = pg_sw_uniquify_row_ids(pg_sw_sweep_tokens(_pg_sw_fill_tokens($static, $tokens)), $widget_id, 1000 + (++$placement));
    return str_replace('<!--pg-loop-slot-->', $loop_rendered, $static);
}
