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
    if (isset($tpl['assets']) && is_array($tpl['assets'])) {
        foreach (array('css', 'js') as $k) {
            if (isset($tpl['assets'][$k])) $tpl['assets'][$k] = pg_migration_design_asset_list($tpl['assets'][$k]);
        }
    }
    return array('name' => $name, 'template' => $tpl, 'files' => array_values($files));
}

/**
 * A received template's CSS or JS asset list (a JSON list) without the
 * entries the install would write to a wrong place. Installing a design
 * writes every 'css', 'js' and 'json' entry whose name ends in one of those
 * extensions into the files directory under that name
 * (pg_designer_sync_asset_files()), so such a name has to be a managed
 * design file name (no folder, no leading dot) and one the upload rule
 * allows ("x.php.js" is refused). Inline entries without a file name and
 * external ones are kept. A value that is not a JSON list becomes ''.
 *
 * @param mixed $json
 * @return string
 */
function pg_migration_design_asset_list($json)
{
    if (!is_string($json) || $json === '') return '';
    $list = json_decode($json, true);
    if (!is_array($list)) return '';
    $kept = array();
    foreach ($list as $asset) {
        if (is_array($asset) && isset($asset['type'], $asset['name'])
            && in_array($asset['type'], array('css', 'js', 'json'), true)
            && preg_match('/\.(css|js|json)$/i', (string)$asset['name'])
            && (!pg_migration_design_file_name_valid($asset['name'])
                || (function_exists('pg_upload_name_blocked') && pg_upload_name_blocked($asset['name'])))) {
            continue;
        }
        $kept[] = $asset;
    }
    if (count($kept) === count($list)) return $json;
    return json_encode($kept, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

/**
 * Whether the classic copy leaves a page of the source out: a page of a
 * visual design (the source marks it with 'visual_design'). Its classic
 * columns hold no content, so it travels only with its design.
 */
function pg_migration_page_carried_by_design($page)
{
    return is_array($page) && !empty($page['visual_design']);
}

/**
 * Whether the classic copy leaves a style of the source out: a visual
 * design (style_layout 'visual_designer'), which travels only as a design.
 */
function pg_migration_style_carried_by_design($style)
{
    return is_array($style) && isset($style['layout']) && $style['layout'] === 'visual_designer';
}

/**
 * The source folders the classic copy leaves out when the designs are
 * carried: a folder whose subtree holds design pages and no classic page
 * (the design install creates its own folders), and every folder below
 * one left out. $folders and $pages are the source's rows, before the
 * design pages are filtered. Returns array(source folder id => true).
 */
function pg_migration_design_only_folder_ids($folders, $pages)
{
    $parent = array();
    foreach ((array)$folders as $folder) {
        if (is_array($folder) && isset($folder['id'])) {
            $parent[(string)$folder['id']] = isset($folder['parent_folder_id']) ? (string)$folder['parent_folder_id'] : '0';
        }
    }

    // Count design and classic pages in each folder's subtree.
    $design = array();
    $classic = array();
    foreach ((array)$pages as $page) {
        if (!is_array($page) || !isset($page['folder_id'])) continue;
        $is_design = pg_migration_page_carried_by_design($page);
        $id = (string)$page['folder_id'];
        $seen = array();
        while (isset($parent[$id]) && !isset($seen[$id])) {
            $seen[$id] = true;
            if ($is_design) {
                $design[$id] = true;
            } else {
                $classic[$id] = true;
            }
            $id = $parent[$id];
        }
    }

    $skip = array();
    foreach ($parent as $id => $unused) {
        $cur = (string)$id;
        $seen = array();
        while (isset($parent[$cur]) && !isset($seen[$cur])) {
            $seen[$cur] = true;
            if (isset($design[$cur]) && !isset($classic[$cur])) {
                $skip[$id] = true;
                break;
            }
            $cur = $parent[$cur];
        }
    }

    return $skip;
}

// ========================= SOURCE SITE ===========================================================

/**
 * The folders a migration never sends: the Recycle Bin, the Drafts folder
 * and the Workspace folder, each with every folder below it. Read once per
 * request. Returns array(folder id => true).
 */
function pg_migration_skipped_folder_ids()
{
    static $ids = null;
    if ($ids !== null) return $ids;
    $roots = array();
    if (function_exists('pg_recycle_bin_folder_id')) $roots[] = (int)pg_recycle_bin_folder_id();
    if (function_exists('pg_page_draft_folder_id')) $roots[] = (int)pg_page_draft_folder_id();
    if (function_exists('pg_workspace_folder_id')) $roots[] = (int)pg_workspace_folder_id();
    $rows = db_items("SELECT folder_id, folder_parent FROM folder");
    $ids = pg_migration_folder_subtrees(is_array($rows) ? $rows : array(), $roots);
    return $ids;
}

// The SQL condition that leaves the skipped folders out, after $keyword
// (WHERE or AND); '' when there are none.
function pg_migration_skipped_folders_sql($column, $keyword = 'AND')
{
    $ids = array_keys(pg_migration_skipped_folder_ids());
    if (!$ids) return '';
    return $keyword . " $column NOT IN (" . implode(',', array_map('intval', $ids)) . ")";
}

/**
 * The managed files of a design the source sends with it: a design file of
 * the file manager (files.design = 1, not a theme file), none a download must
 * keep back (pg_file_withheld()), up to PG_MIGRATION_DESIGN_FILE_MAX bytes.
 * Returns a list of array('name', 'content').
 */
function pg_migration_design_files($css_json, $js_json)
{
    $out = array();
    if (!defined('FILE_DIRECTORY_PATH')) return $out;
    foreach (pg_migration_design_file_names($css_json, $js_json) as $name) {
        if (function_exists('pg_file_withheld') && pg_file_withheld($name)) continue;
        $row = db_item("SELECT design, theme FROM files WHERE name = '" . e($name) . "' LIMIT 1");
        if (!is_array($row) || (int)$row['design'] !== 1 || (int)$row['theme'] === 1) continue;
        $path = FILE_DIRECTORY_PATH . '/' . $name;
        if (!is_file($path) || (int)@filesize($path) > PG_MIGRATION_DESIGN_FILE_MAX) continue;
        $content = @file_get_contents($path);
        if ($content === false) continue;
        $out[] = array('name' => $name, 'content' => $content);
    }
    return $out;
}

/**
 * The get_designs answer: every visual design of this site as a template
 * (pg_design_template_build(), the template-from-design export) with its
 * managed files. A design that cannot be built is listed with its error, so
 * the target can say which one stayed behind.
 */
function pg_migration_export_designs()
{
    $designs = array();
    $styles = db_items("SELECT style_id, style_name, style_custom_css, style_custom_js FROM style
                        WHERE style_layout = 'visual_designer' ORDER BY style_id ASC");
    $opts = array('skip_folders' => pg_migration_skipped_folder_ids(), 'explicit_trees' => true);
    foreach ((is_array($styles) ? $styles : array()) as $s) {
        $built = pg_design_template_build((int)$s['style_id'], array('name' => (string)$s['style_name']), $opts);
        if (empty($built['ok'])) {
            $designs[] = array('name' => (string)$s['style_name'], 'error' => isset($built['error']) ? (string)$built['error'] : '');
            continue;
        }
        $designs[] = array(
            'name'     => (string)$s['style_name'],
            'template' => $built['template'],
            'files'    => pg_migration_design_files((string)$s['style_custom_css'], (string)$s['style_custom_js']),
            'dropped'  => (int)$built['dropped'],
        );
    }
    return $designs;
}

// ========================= TARGET SITE ===========================================================

/**
 * Write the managed files a carried design brings that this site does not
 * have: the file on disk and its files row (a design file in the top
 * folder), as designer_file/create writes them. A name this site already
 * uses (a file, a page, a folder) keeps what this site has.
 *
 * Returns array('written' => names, 'kept' => names).
 */
function pg_migration_write_design_files($files, $user_id)
{
    $out = array('written' => array(), 'kept' => array());
    if (!defined('FILE_DIRECTORY_PATH')) return $out;
    $root = 0;
    foreach ((array)$files as $f) {
        $name = $f['name'];
        $taken = (prepare_file_name($name) !== $name)
            || (function_exists('pg_file_withheld') && pg_file_withheld($name))
            || (get_unique_name(array('name' => $name, 'type' => 'file')) !== $name);
        if ($taken) {
            $out['kept'][] = $name;
            continue;
        }
        if ($root <= 0) $root = (int)db_value("SELECT folder_id FROM folder WHERE folder_parent = '0' ORDER BY folder_id LIMIT 1");
        if (@file_put_contents(FILE_DIRECTORY_PATH . '/' . $name, $f['content']) === false) {
            $out['kept'][] = $name;
            continue;
        }
        $ext = strtolower((string)pathinfo($name, PATHINFO_EXTENSION));
        db("INSERT INTO files (name, folder, description, type, size, design, user, timestamp)
            VALUES (
                '" . e($name) . "',
                '" . (int)$root . "',
                '',
                '" . e($ext) . "',
                '" . (int)strlen($f['content']) . "',
                '1',
                '" . (int)$user_id . "',
                UNIX_TIMESTAMP())");
        $out['written'][] = $name;
    }
    return $out;
}

/**
 * Install one carried design on this site. $entry is a checked entry
 * (pg_migration_design_entry()). The template is stored for the length of
 * the install and removed after it: pg_design_template_install() makes the
 * design, its pages (none of them the home page), folders, widgets, shared
 * components and forms with this site's ids, or takes back what it wrote.
 *
 * Returns array('ok' => true, 'style_name', 'pages' => count, 'warnings',
 * 'files' => pg_migration_write_design_files()) or array('ok' => false,
 * 'error' => text).
 */
function pg_migration_import_design($entry, $user)
{
    $files = pg_migration_write_design_files($entry['files'], (int)$user['id']);
    $saved = pg_design_template_custom_save(array('template' => $entry['template'], 'source_style_id' => 0), $user);
    if (empty($saved['ok'])) return $saved;

    $opts = array('home' => false);
    foreach (array('look', 'palette') as $k) {
        if (isset($entry['template'][$k]) && is_string($entry['template'][$k]) && $entry['template'][$k] !== '') $opts[$k] = $entry['template'][$k];
    }
    $installed = pg_design_template_install((string)$saved['template_id'], $user, $opts);
    db("DELETE FROM design_template WHERE id = '" . (int)$saved['row_id'] . "' LIMIT 1");
    if (empty($installed['ok'])) {
        return array('ok' => false, 'error' => isset($installed['error']) ? (string)$installed['error'] : lang('An error occurred'));
    }
    return array(
        'ok'         => true,
        'style_name' => (string)$installed['style_name'],
        'pages'      => count($installed['pages']),
        'warnings'   => (array)$installed['warnings'],
        'files'      => $files,
    );
}
