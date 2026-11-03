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
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
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
 * needs a look first (PHP in its code, a page somebody holds, a common
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
