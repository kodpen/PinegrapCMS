<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Visual Page Editor - the layout gallery's "From templates" source: the
 * layouts a design template ships for a system widget kind, offered in a
 * design made from that template. Only layouts that work where they land
 * are offered: every placeholder resolved against this design's pages, no
 * part that would need rows of its own, and for a form view the field
 * names of the form it was made for.
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
 * The layouts template $tpl ships for widgets of $kind.
 *
 * $pages maps a template page key to the page this design made from it:
 * array(key => array('url' => <address>, 'id' => <page id>)). A layout that
 * names a page missing from it, or holds a placeholder that would need rows
 * opening the template makes (folders, product and contact groups, nested
 * widgets and shared parts), is left out.
 *
 * Each layout: array('key' => <widget key>, 'label' => <the page's title>,
 * 'tree' => <tree>, 'fields' => <field names of the form a form view was
 * made for> or null). The site's name, e-mail address and year are filled.
 *
 * @param array  $tpl   a template (pg_design_template())
 * @param string $kind  pg_sw_widget_kind() of the widget
 * @param array  $pages template page key => array('url', 'id')
 * @return array
 */
function pg_sw_template_layouts($tpl, $kind, $pages)
{
    if (!is_array($tpl) || !is_string($kind) || $kind === '') return array();
    $widgets = (isset($tpl['widgets']) && is_array($tpl['widgets'])) ? $tpl['widgets'] : array();
    $titles  = array();
    foreach ((isset($tpl['pages']) && is_array($tpl['pages'])) ? $tpl['pages'] : array() as $p) {
        if (!is_array($p) || !isset($p['key'])) continue;
        if (!_pg_tpl_requirement_met(isset($p['requires']) ? $p['requires'] : '')) continue;
        $titles[(string)$p['key']] = isset($p['title']) ? (string)$p['title'] : (string)$p['key'];
    }
    $vars = array(
        'site_name'  => (defined('ORGANIZATION_NAME') && trim((string)ORGANIZATION_NAME) !== '') ? (string)ORGANIZATION_NAME : lang('My Site'),
        'site_email' => defined('EMAIL_ADDRESS') ? (string)EMAIL_ADDRESS : '',
        'year'       => date('Y'),
        'pages'      => array(),
    );
    $ids = array();
    foreach (is_array($pages) ? $pages : array() as $key => $p) {
        if (!is_array($p) || empty($p['id'])) continue;
        $vars['pages'][(string)$key] = isset($p['url']) ? (string)$p['url'] : '';
        $ids[(string)$key] = (int)$p['id'];
    }

    $out  = array();
    $seen = array();
    foreach ($widgets as $wkey => $w) {
        if (!is_array($w) || !isset($w['tree']) || !is_array($w['tree'])) continue;
        if (!_pg_tpl_requirement_met(isset($w['requires']) ? $w['requires'] : '')) continue;
        $cfg = (isset($w['config']) && is_array($w['config'])) ? $w['config'] : array();
        if (pg_sw_widget_kind($cfg) !== $kind) continue;
        $page_key = isset($w['page']) ? (string)$w['page'] : '';
        if ($page_key !== '' && !isset($titles[$page_key])) continue;

        // A page link given as a page id: the page this design made.
        $tree = _pg_sw_gallery_tabs($w['tree'], $ids);
        if ($tree === null) continue;
        $json = json_encode($tree);
        if (!is_string($json) || strpos($json, 'templateWidget') !== false || strpos($json, 'templateShared') !== false) continue;
        // _pg_tpl_fill() turns these into 0 or a sample when it has no row
        // for them, which would read as resolved below.
        if (preg_match('/\{\{(tab|folder|product_group|product_group_path|product_group_image|contact_group):/', $json)) continue;
        if (preg_match_all('/\{\{page:([a-z0-9_-]+)\}\}/', $json, $m)) {
            foreach ($m[1] as $pk) {
                if (!isset($vars['pages'][$pk])) continue 2;
            }
        }
        $tree = _pg_tpl_fill($tree, $vars);
        $json = json_encode($tree);
        if (!is_string($json) || preg_match('/\{\{[a-z_]+(:[a-z0-9_-]+)?\}\}/', $json)) continue;

        $fields = null;
        if (($kind === 'form_list_view') || ($kind === 'form_item_view')) {
            $fields = _pg_sw_gallery_form_fields($widgets, $cfg);
            if ($fields === null) continue;
        }

        $label = ($page_key !== '') ? $titles[$page_key] : (isset($tpl['name']) ? (string)$tpl['name'] : (string)$wkey);
        $seen[$label] = isset($seen[$label]) ? $seen[$label] + 1 : 1;
        if ($seen[$label] > 1) $label .= ' (' . $seen[$label] . ')';
        $out[] = array('key' => (string)$wkey, 'label' => $label, 'tree' => $tree, 'fields' => $fields);
    }
    return $out;
}

/**
 * $node with every value that is only `{{tab:<key>}}` replaced by the id of
 * the page in $ids; null when a key has none.
 */
function _pg_sw_gallery_tabs($node, $ids)
{
    if (is_array($node)) {
        foreach ($node as $k => $v) {
            $v = _pg_sw_gallery_tabs($v, $ids);
            if ($v === null) return null;
            $node[$k] = $v;
        }
        return $node;
    }
    if (is_string($node) && preg_match('/^\{\{tab:([a-z0-9_-]+)\}\}$/', $node, $m)) {
        return isset($ids[$m[1]]) ? (string)$ids[$m[1]] : null;
    }
    return $node;
}

/**
 * The sorted field names of the form a template's form view was made for:
 * the controls of the custom form widget on the page its setting names.
 * null when that form is not among the template's widgets.
 */
function _pg_sw_gallery_form_fields($widgets, $cfg)
{
    $ref = isset($cfg['custom_form_page_id']) ? $cfg['custom_form_page_id'] : (isset($cfg['form_page_id']) ? $cfg['form_page_id'] : '');
    if (!is_string($ref) || !preg_match('/^\{\{tab:([a-z0-9_-]+)\}\}$/', $ref, $m)) return null;
    foreach ($widgets as $w) {
        if (!is_array($w) || !isset($w['page']) || (string)$w['page'] !== $m[1]) continue;
        $wcfg = (isset($w['config']) && is_array($w['config'])) ? $w['config'] : array();
        if (pg_sw_widget_kind($wcfg) !== 'custom_form' || !isset($w['tree']) || !is_array($w['tree'])) continue;
        $names = array_keys(_pg_cf_controls($w['tree']));
        $names = array_values(array_unique(array_map('strtolower', array_map('strval', $names))));
        sort($names);
        return $names;
    }
    return null;
}

/**
 * The pages design $style_id made from template $tpl's pages: key =>
 * array('url', 'id'). A template page is taken under its own name, or the
 * name with the lowest -2, -3 … suffix opening gave it when the name was
 * taken (_pg_tpl_unique_page_name()).
 */
function pg_sw_template_page_map($tpl, $style_id)
{
    $style_id = (int)$style_id;
    if ($style_id <= 0 || !is_array($tpl) || empty($tpl['pages'])) return array();
    $rows = db_items("SELECT page_id, page_name FROM page WHERE page_style = '$style_id'");
    $by_name = array();
    foreach ($rows ? $rows : array() as $r) {
        $by_name[mb_strtolower((string)$r['page_name'], 'UTF-8')] = array('id' => (int)$r['page_id'], 'name' => (string)$r['page_name']);
    }
    $out = array();
    foreach ($tpl['pages'] as $p) {
        if (!is_array($p) || !isset($p['key'])) continue;
        $base = trim((string)(isset($p['name']) ? $p['name'] : $p['key']));
        if ($base === '') continue;
        for ($n = 1; $n < 50; $n++) {
            $lower = mb_strtolower(($n === 1) ? $base : $base . '-' . $n, 'UTF-8');
            if (!isset($by_name[$lower])) continue;
            $hit = $by_name[$lower];
            $out[(string)$p['key']] = array(
                'id'  => $hit['id'],
                'url' => (defined('OUTPUT_PATH') ? OUTPUT_PATH : '/') . encode_url_path($hit['name']),
            );
            break;
        }
    }
    return $out;
}
