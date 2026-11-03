<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The visual designs the Migration screen (migration.php) carries from a
 * source site. The source's api.php answers get_designs with every visual
 * design built by the template-from-design export
 * (pg_design_template_build()): the trees with their ids turned into
 * template placeholders, the shared components and widgets they place, the
 * folders, forms and assets, plus the content of the design's managed CSS /
 * JS / JSON files. The target writes the files it does not have yet and
 * installs each design with pg_design_template_install(), which gives the
 * placeholders this site's ids. One serializer, both ways.
 *
 * The Recycle Bin, the Drafts folder and the Workspace folder, with every
 * folder below them, are never sent: not as classic folders and pages, not
 * inside a design.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// The largest managed design file sent with a design, in bytes.
if (!defined('PG_MIGRATION_DESIGN_FILE_MAX')) define('PG_MIGRATION_DESIGN_FILE_MAX', 2097152);

// ========================= PURE HELPERS ==========================================================

/**
 * The folders in the subtrees of $roots: each root and every folder below
 * it. $rows: list of array('folder_id', 'folder_parent'). A root id of 0 or
 * less is ignored. Returns array(folder id => true), in id order.
 */
function pg_migration_folder_subtrees($rows, $roots)
{
    $children = array();
    foreach ((array)$rows as $r) {
        if (!is_array($r) || !isset($r['folder_id'])) continue;
        $children[(int)(isset($r['folder_parent']) ? $r['folder_parent'] : 0)][] = (int)$r['folder_id'];
    }
    $out = array();
    $stack = array();
    foreach ((array)$roots as $root) {
        $root = (int)$root;
        if ($root > 0 && !isset($out[$root])) {
            $out[$root] = true;
            $stack[] = $root;
        }
    }
    while ($stack) {
        $f = array_pop($stack);
        if (empty($children[$f])) continue;
        foreach ($children[$f] as $c) {
            if (isset($out[$c])) continue;
            $out[$c] = true;
            $stack[] = $c;
        }
    }
    ksort($out);
    return $out;
}

/**
 * The names of the managed files (entries with `_file` set, the shape
 * designer_file/create writes) in a design's CSS and JS asset lists, each
 * once. Only plain file names of a css, js or json file.
 */
function pg_migration_design_file_names($css_json, $js_json)
{
    $names = array();
    foreach (array($css_json, $js_json) as $json) {
        $list = json_decode((string)$json, true);
        if (!is_array($list)) continue;
        foreach ($list as $entry) {
            if (!is_array($entry) || empty($entry['_file']) || !isset($entry['name']) || !is_string($entry['name'])) continue;
            $name = $entry['name'];
            if (!pg_migration_design_file_name_valid($name)) continue;
            $names[$name] = $name;
        }
    }
    return array_values($names);
}

// A name a managed design file may have: no folder, no leading dot, a css,
// js or json extension.
function pg_migration_design_file_name_valid($name)
{
    if (!is_string($name) || $name === '' || strlen($name) > 255) return false;
    if (strpbrk($name, "/\\\0") !== false || $name[0] === '.') return false;
    return (bool)preg_match('/\.(css|js|json)$/i', $name);
}

/**
 * One design of a get_designs answer, checked before anything is written:
 * array('name', 'template' => template array, 'files' => list) or null when
 * the entry is not a design. Files that are not a managed design file are
 * left out.
 */
function pg_migration_design_entry($entry)
{
    if (!is_array($entry) || !isset($entry['template']) || !is_array($entry['template'])) return null;
    $tpl = $entry['template'];
    if (empty($tpl['pages']) || !is_array($tpl['pages'])) return null;
    foreach ($tpl['pages'] as $p) {
        if (!is_array($p) || !isset($p['key']) || !is_string($p['key']) || !isset($p['tree']) || !is_array($p['tree'])) return null;
    }
    foreach (array('widgets', 'shared', 'folders', 'contact_groups') as $k) {
        if (isset($tpl[$k]) && !is_array($tpl[$k])) return null;
    }
    // A carried design never asks for a layout the editor makes.
    foreach ((isset($tpl['widgets']) ? $tpl['widgets'] : array()) as $w) {
        if (!is_array($w) || !isset($w['tree']) || !is_array($w['tree'])) return null;
    }
    $name = isset($entry['name']) && is_scalar($entry['name']) ? trim((string)$entry['name']) : '';
    if ($name === '') $name = isset($tpl['name']) && is_scalar($tpl['name']) ? trim((string)$tpl['name']) : '';
    $files = array();
    foreach ((isset($entry['files']) && is_array($entry['files'])) ? $entry['files'] : array() as $f) {
        if (!is_array($f) || !isset($f['name'], $f['content']) || !is_string($f['content'])) continue;
        if (!pg_migration_design_file_name_valid($f['name'])) continue;
        if (strlen($f['content']) > PG_MIGRATION_DESIGN_FILE_MAX) continue;
        $files[$f['name']] = array('name' => $f['name'], 'content' => $f['content']);
    }
    $tpl['name'] = $name;
    return array('name' => $name, 'template' => $tpl, 'files' => array_values($files));
}

/**
 * Whether the classic copy leaves a page of the source out: one in a design
 * the same run carries (the source marks it with 'visual_design').
 */
function pg_migration_page_carried_by_design($page, $carry_designs)
{
    return $carry_designs && is_array($page) && !empty($page['visual_design']);
}

/**
 * Whether the classic copy leaves a style of the source out: a visual
 * design (style_layout 'visual_designer') when the same run carries the
 * designs.
 */
function pg_migration_style_carried_by_design($style, $carry_designs)
{
    return $carry_designs && is_array($style) && isset($style['layout']) && $style['layout'] === 'visual_designer';
}
