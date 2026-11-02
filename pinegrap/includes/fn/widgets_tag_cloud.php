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

/**
 * Keyword counts merged and cut to the 25 used most: $counts holds
 * keywords already counted (keyword => count, the pages' keywords as the
 * database groups them), each entry of $lists is one item's comma
 * separated keywords, counted once per item. Keywords differing only in
 * case count as one, written as first seen. Most used first, then by
 * keyword, as pg_tcw_keywords() returns them. Pure.
 *
 * @param array $counts keyword => count
 * @param array $lists  comma separated keyword lists, one per item
 * @param int   $limit  the number of keywords kept
 * @return array rows (keyword, count)
 */
function pg_tcw_merge_keywords(array $counts, array $lists, $limit = 25)
{
    $fold = function ($word) {
        return function_exists('mb_strtolower') ? mb_strtolower($word, 'UTF-8') : strtolower($word);
    };
    $total = array();
    $shown = array();
    $add = function ($word, $n) use (&$total, &$shown, $fold) {
        $word = trim((string)$word);
        if ($word === '' || $n <= 0) return;
        $key = $fold($word);
        if (!isset($total[$key])) {
            $total[$key] = 0;
            $shown[$key] = $word;
        }
        $total[$key] += $n;
    };
    foreach ($counts as $word => $n) $add((string)$word, (int)$n);
    foreach ($lists as $list) {
        $seen = array();
        foreach (explode(',', (string)$list) as $word) {
            $word = trim($word);
            if ($word === '' || isset($seen[$fold($word)])) continue;
            $seen[$fold($word)] = true;
            $add($word, 1);
        }
    }
    $rows = array();
    foreach ($total as $key => $n) $rows[] = array('keyword' => $shown[$key], 'count' => $n);
    usort($rows, function ($a, $b) {
        if ($a['count'] !== $b['count']) return ($a['count'] > $b['count']) ? -1 : 1;
        return strcmp($a['keyword'], $b['keyword']);
    });
    return array_slice($rows, 0, max(0, (int)$limit));
}

/**
 * The Search Results widget's settings on the page named $name, for a page
 * built with the widget instead of a classic search results page: the
 * first such widget the page carries, by shared component id.
 *
 * @param string $name the page's name
 * @return array|null array('page_id', 'page_name', 'cfg') or null
 */
function pg_tcw_widget_search_page($name)
{
    $name = trim((string)$name);
    if ($name === '' || !function_exists('pg_sw_widget_page_index')) return null;
    $page = db_item("SELECT page_id, page_name FROM page WHERE page_name = '" . e($name) . "' LIMIT 1");
    if (!is_array($page)) return null;
    $rows = db_items(
        "SELECT id, system_region_config FROM shared_components
         WHERE system_region_config LIKE '%\"regionType\":\"search_results\"%'
         ORDER BY id ASC");
    if (!$rows) return null;
    $index = pg_sw_widget_page_index();
    foreach ((array)$rows as $row) {
        $cfg = json_decode((string)$row['system_region_config'], true);
        if (!is_array($cfg) || !isset($cfg['regionType']) || $cfg['regionType'] !== 'search_results') continue;
        $sid = (int)$row['id'];
        if (!isset($index['by_sid'][$sid])) continue;
        foreach ($index['by_sid'][$sid] as $i) {
            if (isset($index['pages'][$i]) && (int)$index['pages'][$i]['page_id'] === (int)$page['page_id']) {
                return array('page_id' => (int)$page['page_id'], 'page_name' => (string)$page['page_name'], 'cfg' => $cfg);
            }
        }
    }
    return null;
}

/**
 * The 25 keywords used most by what a Search Results widget searches
 * (its search_scope): the site's pages (tag_cloud_keywords, as the
 * classic region reads them) and the catalog under its product group
 * (the root group when none is set), read from the products and the
 * product groups themselves, as the classic search results page's
 * keyword sync (update_tag_cloud_keywords_for_search_results_page_product_group())
 * collects them: product groups shown as a selection, and the enabled
 * products of the groups shown for browsing.
 *
 * @param array $cfg the Search Results widget's settings
 * @return array rows (keyword, count)
 */
function pg_tcw_widget_keywords($cfg)
{
    if (!is_array($cfg)) $cfg = array();
    $scope = (isset($cfg['search_scope']) && in_array($cfg['search_scope'], array('all', 'pages', 'products'), true))
        ? $cfg['search_scope'] : 'all';

    $counts = array();
    if ($scope !== 'products') {
        foreach ((array)db_items(
            "SELECT keyword, COUNT(keyword) AS count FROM tag_cloud_keywords
             WHERE item_type = 'page' GROUP BY keyword") as $row) {
            $counts[(string)$row['keyword']] = (int)$row['count'];
        }
    }

    $lists = array();
    if ($scope !== 'pages' && defined('ECOMMERCE') && ECOMMERCE) {
        $group_id = isset($cfg['product_group_id']) ? (int)$cfg['product_group_id'] : 0;
        if ($group_id <= 0) $group_id = (int)db_value("SELECT id FROM product_groups WHERE parent_id = '0' LIMIT 1");
        $select = array();
        $browse = array();
        foreach ((array)get_product_groups_in_product_group_tree($group_id) as $group) {
            if ($group['display_type'] === 'browse') $browse[] = (int)$group['id'];
            else $select[] = (int)$group['id'];
        }
        if ($select) {
            foreach ((array)db_items(
                "SELECT keywords FROM product_groups
                 WHERE display_type = 'select' AND id IN (" . implode(',', $select) . ")") as $row) {
                $lists[] = (string)$row['keywords'];
            }
        }
        if ($browse) {
            foreach ((array)db_items(
                "SELECT DISTINCT products.id, products.keywords FROM products_groups_xref
                 INNER JOIN products ON products.id = products_groups_xref.product
                 WHERE products.enabled = '1'
                   AND products_groups_xref.product_group IN (" . implode(',', $browse) . ")") as $row) {
                $lists[] = (string)$row['keywords'];
            }
        }
    }
    return pg_tcw_merge_keywords($counts, $lists);
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
// classic tag names it, or a page carrying the Search Results widget).
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
    $keywords = is_array($page) ? pg_tcw_keywords($page) : array();
    if (!is_array($page) && $name !== '') {
        // A page built with the Search Results widget: the keywords of what
        // that widget searches.
        $page = pg_tcw_widget_search_page($name);
        if (is_array($page)) $keywords = pg_tcw_widget_keywords($page['cfg']);
    }
    $rows = is_array($page) ? pg_tcw_rows($keywords, (string)$page['page_name'],
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

/**
 * What the designer's Tag Cloud page picker shows of each page it offers:
 * the design the page is set in and whether an anonymous visitor may open
 * it. A new widget takes a page of the design being edited that everyone
 * can open, so keyword links never lead visitors to the login page of
 * another design's draft. The first row of a name wins.
 *
 * @param array    $pages      rows (page_name, page_folder, page_style)
 * @param array    $styles     design id => design name
 * @param callable $access_of  folder id => access control type
 * @return array page name => array('style' => id, 'design' => name, 'open' => bool)
 */
function pg_tcw_page_info($pages, $styles, $access_of)
{
    $out = array();
    foreach (is_array($pages) ? $pages : array() as $row) {
        if (!is_array($row) || !isset($row['page_name'])) continue;
        $name = (string)$row['page_name'];
        if ($name === '' || isset($out[$name])) continue;
        $style = isset($row['page_style']) ? (int)$row['page_style'] : 0;
        $folder = isset($row['page_folder']) ? (int)$row['page_folder'] : 0;
        $out[$name] = array(
            'style'  => $style,
            'design' => isset($styles[$style]) ? (string)$styles[$style] : '',
            'open'   => pg_sw_search_index_folder_open(call_user_func($access_of, $folder)),
        );
    }
    return $out;
}
