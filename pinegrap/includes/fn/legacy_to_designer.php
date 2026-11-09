<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The readiness report of the move to the visual designer
 * (legacy_to_designer.php): every classic style (a `style` row that is not
 * a visual design) with the pages that use it, own and by folder, the
 * region placeholders of its code by type, what stays classic after a
 * conversion (dynamic regions, custom layouts), what stops one (the
 * refusal of pg_classic_style_refusal(), PHP in the style's code, a page
 * somebody holds in the designer) and a ready / needs-attention state.
 *
 * Read only: nothing here writes a row. The conversions stay the existing
 * ones (includes/fn/classic_style_design.php and the region converters).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2026 Erdal Güral. All rights reserved.
 * @license     Proprietary, see license-editors.txt
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/classic_style_design.php');

/**
 * The region placeholders of a classic style's code, in order, and whether
 * the code holds PHP. A tag inside a comment, a script, a style, a text
 * area or the title is text there, as pg_di_classic_placeholders() reads
 * it; the comments block marker counts as a region. PHP is looked for
 * everywhere: the page is evaluated as PHP whatever element holds it.
 *
 * Pure: no query.
 *
 * @param string $style_code
 * @return array  'regions' (list of array('type' => region type as the
 *                designer names it, 'name' => text of the tag)), 'php' (bool)
 */
function pg_ltd_style_tags($style_code)
{
    static $types = array('pregion' => 'page', 'system' => 'system', 'cregion' => 'common', 'dregion' => 'dynamic',
                          'menu' => 'menu', 'menu_sequence' => 'menu_sequence', 'login' => 'login', 'ad' => 'ad',
                          'cart' => 'cart', 'tcloud' => 'tag_cloud', 'mobile_switch' => 'mobile_switch', 'pdf' => 'pdf');
    $code = (string)$style_code;
    $out = array('regions' => array(), 'php' => (bool)preg_match('/<\?(?:php\b|=|\s)/i', $code));

    $parts = preg_split('~(<!--(?!pg-comments-block-->).*?-->|<(?:script|style|textarea|title)\b[^>]*>.*?</(?:script|style|textarea|title)\s*>)~is',
        $code, -1, PREG_SPLIT_DELIM_CAPTURE);
    foreach ((array)$parts as $i => $part) {
        if ($i % 2 === 1) continue;
        preg_match_all('~<!--pg-comments-block-->|<(pregion|system|cregion|dregion|menu_sequence|menu|login|ad|cart|tcloud|mobile_switch|pdf)(\s+"[^"<>]*")?\s*>([^<]*)</\1\s*>~i',
            $part, $m, PREG_SET_ORDER);
        foreach ($m as $hit) {
            if (!isset($hit[1]) || $hit[1] === '') {
                $out['regions'][] = array('type' => 'comments_block', 'name' => '');
                continue;
            }
            $tag = strtolower($hit[1]);
            // An attribute is the dynamic region's only; the importer leaves
            // any other tag carrying one as text.
            if ($hit[2] !== '' && $tag !== 'dregion') continue;
            $out['regions'][] = array(
                'type' => $types[$tag],
                'name' => html_entity_decode($hit[3], ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            );
        }
    }
    return $out;
}

/**
 * The regions of a style counted by what the designer makes of them. A
 * common region tag is split by its row: a designer region
 * (cregion_designer_type 'yes'), a common region already made a shared
 * component, a common region still to convert, or a name with no row
 * (it prints nothing today, and nothing after).
 *
 * Pure: no query.
 *
 * @param array $regions        pg_ltd_style_tags()'s 'regions'
 * @param array $cregions       region name => array('id' => int, 'designer' => bool)
 * @param array $converted_ids  common region ids made shared components
 * @return array  'counts' (type => n; common tags as 'common',
 *                'common_converted', 'designer', 'missing'), 'missing'
 *                (names with no row, each once)
 */
function pg_ltd_region_counts($regions, $cregions, $converted_ids)
{
    $converted = array();
    foreach ((array)$converted_ids as $id) $converted[(int)$id] = true;

    $counts = array();
    $missing = array();
    foreach ((array)$regions as $r) {
        $type = (string)($r['type'] ?? '');
        if ($type === 'common') {
            $name = (string)($r['name'] ?? '');
            $row = isset($cregions[$name]) ? $cregions[$name] : null;
            if (!is_array($row)) {
                $type = 'missing';
                if (!in_array($name, $missing, true)) $missing[] = $name;
            } elseif (!empty($row['designer'])) {
                $type = 'designer';
            } elseif (isset($converted[(int)$row['id']])) {
                $type = 'common_converted';
            }
        }
        if ($type === '') continue;
        $counts[$type] = (isset($counts[$type]) ? $counts[$type] : 0) + 1;
    }
    return array('counts' => $counts, 'missing' => $missing);
}

/**
 * The pages of each style: its own (page_style names it) and those that
 * take it from their folder (no page_style, the folder's style by
 * pg_classic_style_folder_styles()). Binned pages are left out by the
 * caller.
 *
 * Pure: no query.
 *
 * @param array $pages          rows: page_id, page_name, page_folder,
 *                              page_style, has_tree (bool), custom_layout (bool),
 *                              page_regions (int)
 * @param array $folder_styles  folder_id => style id
 * @return array  style id => array('own' => rows, 'folder' => rows)
 */
function pg_ltd_pages_by_style($pages, $folder_styles)
{
    $out = array();
    foreach ((array)$pages as $p) {
        if (!is_array($p)) continue;
        $style = (int)($p['page_style'] ?? 0);
        $kind = 'own';
        if ($style <= 0) {
            $folder = (int)($p['page_folder'] ?? 0);
            $style = isset($folder_styles[$folder]) ? (int)$folder_styles[$folder] : 0;
            $kind = 'folder';
        }
        if ($style <= 0) continue;
        if (!isset($out[$style])) $out[$style] = array('own' => array(), 'folder' => array());
        $out[$style][$kind][] = $p;
    }
    return $out;
}

/**
 * A style's state in the report. 'blocked': the designer refuses to open
 * it (the refusal is the reason). 'attention': it opens, but something
 * needs a look first (PHP in its code, which the importer does not keep, a page somebody holds, a common
 * region tag with no row, the style open in the designer right now).
 * 'ready' otherwise. Notes say what stays classic after the move (dynamic
 * regions, custom layouts) or that no page uses the style; they never
 * change the state.
 *
 * Pure: no query.
 *
 * @param array $facts  'refusal' (string), 'php' (bool), 'locked' (int),
 *                      'open' (int), 'missing' (int), 'dynamic' (int),
 *                      'custom_layouts' (int), 'pages' (int)
 * @return array  'state', 'reasons' (codes), 'notes' (codes)
 */
function pg_ltd_style_state($facts)
{
    $f = (array)$facts;
    $notes = array();
    if ((int)($f['dynamic'] ?? 0) > 0)        $notes[] = 'dynamic';
    if ((int)($f['custom_layouts'] ?? 0) > 0) $notes[] = 'custom_layout';
    if ((int)($f['pages'] ?? 0) === 0)        $notes[] = 'no_pages';

    if ((string)($f['refusal'] ?? '') !== '') {
        return array('state' => 'blocked', 'reasons' => array('refusal'), 'notes' => $notes);
    }
    $reasons = array();
    if (!empty($f['php']))                $reasons[] = 'php';
    if ((int)($f['missing'] ?? 0) > 0)    $reasons[] = 'missing';
    if ((int)($f['locked'] ?? 0) > 0)     $reasons[] = 'locked';
    if ((int)($f['open'] ?? 0) > 0)       $reasons[] = 'open';
    return array('state' => $reasons ? 'attention' : 'ready', 'reasons' => $reasons, 'notes' => $notes);
}

/**
 * What stays classic after every style is moved, for the report's summary:
 * styles the designer refuses (they stay as they are), dynamic regions
 * (they stay PHP, edited from their node), pages with a custom layout (it
 * keeps drawing inside the system region) and a card gateway with no
 * payment driver (its code stays in submit_order.php; a converted checkout
 * posts to it unchanged). Items with nothing to list are left out.
 *
 * Pure: no query.
 *
 * @param array  $report  pg_ltd_report() entries
 * @param string $frozen_gateway  the card gateway when no driver serves it, else ''
 * @return array  list of array('code', 'count', 'styles' (names))
 */
function pg_ltd_stays_classic($report, $frozen_gateway = '')
{
    $items = array(
        'blocked'       => array('code' => 'blocked', 'count' => 0, 'styles' => array()),
        'dynamic'       => array('code' => 'dynamic', 'count' => 0, 'styles' => array()),
        'custom_layout' => array('code' => 'custom_layout', 'count' => 0, 'styles' => array()),
    );
    foreach ((array)$report as $s) {
        $name = (string)($s['name'] ?? '');
        if ((string)($s['state'] ?? '') === 'blocked') {
            ++$items['blocked']['count'];
            $items['blocked']['styles'][] = $name;
        }
        $dynamic = (int)($s['regions']['counts']['dynamic'] ?? 0);
        if ($dynamic > 0) {
            $items['dynamic']['count'] += $dynamic;
            $items['dynamic']['styles'][] = $name;
        }
        $custom = (int)($s['custom_layouts'] ?? 0);
        if ($custom > 0) {
            $items['custom_layout']['count'] += $custom;
            $items['custom_layout']['styles'][] = $name;
        }
    }
    $out = array();
    foreach ($items as $item) {
        if ($item['count'] > 0) $out[] = $item;
    }
    if ((string)$frozen_gateway !== '') {
        $out[] = array('code' => 'gateway', 'count' => 1, 'styles' => array(), 'gateway' => (string)$frozen_gateway);
    }
    return $out;
}

/**
 * The readiness report: one entry per classic style, by name. Reads the
 * styles, folders, pages (binned ones left out), common regions, page
 * region counts and the designer's live page locks and presence.
 *
 * @return array  list of array('id', 'name', 'type', 'theme', 'refusal',
 *                'php', 'regions' (pg_ltd_region_counts()), 'own', 'folder'
 *                (page rows), 'trees' (pages already drawn from a tree),
 *                'page_regions', 'custom_layouts', 'locked' (page rows
 *                with 'holder'), 'open' (user names with the style open),
 *                'state', 'reasons', 'notes')
 */
function pg_ltd_report()
{
    $styles = db_items(
        "SELECT style.style_id, style.style_name, style.style_type, style.style_layout, style.style_code,
                files.name AS theme_name
         FROM style
         LEFT JOIN files ON style.theme_id = files.id
         WHERE style.style_layout <> 'visual_designer'
         ORDER BY style.style_name ASC, style.style_id ASC");
    $styles = is_array($styles) ? $styles : array();
    if (!$styles) return array();

    $folders = db_items("SELECT folder_id, folder_parent, folder_style, folder_level FROM folder");
    $folder_styles = pg_classic_style_folder_styles(is_array($folders) ? $folders : array());

    $tree_ready = pg_multi_page_design_ready();
    $region_counts = array();
    foreach ((array)db_items("SELECT pregion_page, COUNT(*) AS n FROM pregion GROUP BY pregion_page") as $r) {
        $region_counts[(int)$r['pregion_page']] = (int)$r['n'];
    }
    $rows = db_items(
        "SELECT page_id, page_name, page_folder, page_style, page_type, layout_type"
        . ($tree_ready ? ", (page_tree_json IS NOT NULL AND page_tree_json <> '') AS has_tree" : ", 0 AS has_tree") . "
         FROM page
         WHERE 1" . pg_designer_not_binned_sql() . "
         ORDER BY page_name ASC, page_id ASC");
    $pages = array();
    foreach ((is_array($rows) ? $rows : array()) as $r) {
        $id = (int)$r['page_id'];
        $pages[] = array(
            'page_id'       => $id,
            'page_name'     => (string)$r['page_name'],
            'page_folder'   => (int)$r['page_folder'],
            'page_style'    => (int)$r['page_style'],
            'has_tree'      => (bool)(int)$r['has_tree'],
            'custom_layout' => (string)$r['layout_type'] === 'custom' && check_if_page_type_supports_layout((string)$r['page_type']),
            'page_regions'  => isset($region_counts[$id]) ? $region_counts[$id] : 0,
        );
    }
    $by_style = pg_ltd_pages_by_style($pages, $folder_styles);

    $cregions = array();
    foreach ((array)db_items("SELECT cregion_id, cregion_name, cregion_designer_type FROM cregion") as $r) {
        $cregions[(string)$r['cregion_name']] = array('id' => (int)$r['cregion_id'], 'designer' => (string)$r['cregion_designer_type'] === 'yes');
    }
    $converted = pg_common_region_converted_ids();

    // Who holds a page, and who has a style open, in the designer now.
    $holders = array();
    $open = array();
    require_once(PG_FUNCTIONS_DIR . '/includes/designer_collab.php');
    if (pg_collab_ready()) {
        $cut = time() - PG_COLLAB_STALE;
        foreach ((array)db_items("SELECT l.page_id, u.user_username FROM designer_page_lock l
                                  LEFT JOIN user u ON u.user_id = l.user_id
                                  WHERE l.last_seen >= '$cut'") as $r) {
            $holders[(int)$r['page_id']] = (string)$r['user_username'];
        }
        foreach ((array)db_items("SELECT DISTINCT p.style_id, u.user_username FROM designer_presence p
                                  LEFT JOIN user u ON u.user_id = p.user_id
                                  WHERE p.last_seen >= '$cut'") as $r) {
            $open[(int)$r['style_id']][] = (string)$r['user_username'];
        }
    }

    $out = array();
    foreach ($styles as $s) {
        $id = (int)$s['style_id'];
        $tags = pg_ltd_style_tags((string)$s['style_code']);
        $regions = pg_ltd_region_counts($tags['regions'], $cregions, $converted);
        $own = isset($by_style[$id]) ? $by_style[$id]['own'] : array();
        $folder = isset($by_style[$id]) ? $by_style[$id]['folder'] : array();

        $trees = 0;
        $page_regions = 0;
        $custom_layouts = 0;
        $locked = array();
        foreach (array_merge($own, $folder) as $p) {
            if ($p['has_tree']) {
                ++$trees;
                continue;
            }
            $page_regions += $p['page_regions'];
            if ($p['custom_layout']) ++$custom_layouts;
            if (isset($holders[$p['page_id']])) {
                $p['holder'] = $holders[$p['page_id']];
                $locked[] = $p;
            }
        }
        $refusal = pg_classic_style_refusal($s);
        $state = pg_ltd_style_state(array(
            'refusal'        => $refusal,
            'php'            => $tags['php'],
            'locked'         => count($locked),
            'open'           => isset($open[$id]) ? count($open[$id]) : 0,
            'missing'        => count($regions['missing']),
            'dynamic'        => isset($regions['counts']['dynamic']) ? $regions['counts']['dynamic'] : 0,
            'custom_layouts' => $custom_layouts,
            'pages'          => count($own) + count($folder),
        ));
        $out[] = array(
            'id'             => $id,
            'name'           => (string)$s['style_name'],
            'type'           => (string)$s['style_type'],
            'theme'          => (string)$s['theme_name'],
            'refusal'        => $refusal,
            'php'            => $tags['php'],
            'regions'        => $regions,
            'own'            => $own,
            'folder'         => $folder,
            'trees'          => $trees,
            'page_regions'   => $page_regions,
            'custom_layouts' => $custom_layouts,
            'locked'         => $locked,
            'open'           => isset($open[$id]) ? $open[$id] : array(),
            'state'          => $state['state'],
            'reasons'        => $state['reasons'],
            'notes'          => $state['notes'],
        );
    }
    return $out;
}
