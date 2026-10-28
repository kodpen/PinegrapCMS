<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Visual Page Editor - the Folder View system widget (kind 'folder_view'):
 * the classic "folder view" page drawn by the designer. The pages and files
 * of the page's folder and of the folders below it, each folder with what
 * the visitor may see in it.
 *
 * The main loop repeats the entries of the page's folder: its sub-folders
 * first, then its pages, then its files. A list of the entries of a folder
 * (source folder_view.children), keyed by ^^__folder_item_key^^, repeats
 * the entries of the folder row it stands in, and a list inside that list
 * the level below; outside the main loop the key is the page's folder. The
 * rows are built here from what _pg_fv_read() reads, so the row shape is
 * tested without a database.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// The folder view source, in the shape of pg_sw_list_sources(). The name
// comes first: the A to Z order compares a row's first value.
function pg_sw_list_folder_view_sources()
{
    return array(
        'folder_view.children' => array(
            'family'       => 'folder_view',
            'key'          => '__folder_item_key',
            'label'        => lang('Entries of a folder'),
            'arg'          => '',
            'arg_required' => false,
            'tokens'       => array(
                array('__folder_item_name', lang('Entry name')),
                array('__folder_item_key', lang('Folder of the entry (empty for a page or a file)')),
                array('__folder_item_url', lang('Entry address (empty for a folder)')),
                array('__folder_item_description', lang('Entry description')),
                array('__folder_item_is_folder', lang('The entry is a folder (1 or empty)')),
                array('__folder_item_is_page', lang('The entry is a page (1 or empty)')),
                array('__folder_item_is_file', lang('The entry is a file (1 or empty)')),
                array('__folder_item_is_design', lang('The file is a design file (1 or empty)')),
                array('__folder_item_access', lang('Access of the folder (public, private, guest, registration or membership)')),
            ),
        ),
    );
}

/**
 * The page types a folder view leaves out: screens that only work as a
 * step of something else (a confirmation, a checkout step, a detail page),
 * as the classic page leaves them out.
 *
 * @return array
 */
function pg_fv_hidden_page_types()
{
    return array(
        'registration confirmation', 'membership confirmation', 'view order', 'custom form confirmation',
        'form item view', 'calendar event view', 'catalog detail', 'shipping address and arrival',
        'shipping method', 'billing information', 'order preview', 'order receipt',
        'affiliate sign up confirmation', 'affiliate welcome',
    );
}

/**
 * Whether the widget lists pages and files: the widget's own setting where
 * it has one, else the page's folder_view_pages row, else the classic
 * defaults (pages on, files off).
 *
 * @param array|null $row the page's folder_view_pages row, null for none
 * @param array      $cfg the widget's settings
 * @return array array('pages' => bool, 'files' => bool)
 */
function pg_fv_settings($row, $cfg)
{
    $cfg = is_array($cfg) ? $cfg : array();
    $row = is_array($row) ? $row : array();
    $out = array();
    foreach (array('pages' => 'include_pages', 'files' => 'include_files') as $col => $key) {
        if (array_key_exists($key, $cfg)) {
            $v = $cfg[$key];
            $out[$col] = ($v === true || $v === 1 || $v === '1' || $v === 'true');
        } elseif (isset($row[$col])) {
            $out[$col] = ((string)$row[$col] === '1');
        } else {
            $out[$col] = ($col === 'pages');
        }
    }
    return $out;
}

/**
 * The rows of a folder view, by the id of the folder they sit in, as the
 * classic page draws them. In each folder: the sub-folders that have
 * something to show (shown even when the visitor may not view the folder
 * itself, so the levels stay), then, when the visitor may view the folder,
 * its pages and its files. An archived folder shows nothing; the page
 * shown is left out at every level. A folder row's key is its id, a page's
 * and a file's is empty, so a list in their row lists nothing.
 *
 * @param int   $root_id the page's folder
 * @param array $data    as _pg_fv_read() reads it: 'folders' (parent id =>
 *                       rows of id, name, archived, access), 'pages' and
 *                       'files' (folder id => rows), 'view' (folder id =>
 *                       true when the visitor may view it)
 * @param int   $excluded_page_id
 * @param string $base   the site's path before a page or file name
 * @return array folder id => rows (raw values)
 */
function pg_sw_list_folder_view_rows($root_id, $data, $excluded_page_id, $base = '/')
{
    $data = is_array($data) ? $data : array();
    $part = function ($k) use ($data) {
        return (isset($data[$k]) && is_array($data[$k])) ? $data[$k] : array();
    };
    $folders = $part('folders');
    $pages   = $part('pages');
    $files   = $part('files');
    $view    = $part('view');
    $out  = array();
    $seen = array();
    $row = function ($kind, $name, $url, $description, $key = '', $design = false, $access = '') {
        return array(
            '__folder_item_name'        => (string)$name,
            '__folder_item_key'         => (string)$key,
            '__folder_item_url'         => (string)$url,
            '__folder_item_description' => (string)$description,
            '__folder_item_is_folder'   => ($kind === 'folder') ? '1' : '',
            '__folder_item_is_page'     => ($kind === 'page') ? '1' : '',
            '__folder_item_is_file'     => ($kind === 'file') ? '1' : '',
            '__folder_item_is_design'   => $design ? '1' : '',
            '__folder_item_access'      => (string)$access,
        );
    };
    // Whether $id holds anything to show; its rows go to $out[$id].
    $walk = function ($id) use (&$walk, &$out, &$seen, $folders, $pages, $files, $view, $row, $excluded_page_id, $base) {
        if (isset($seen[$id])) return false;
        $seen[$id] = true;
        $rows = array();
        foreach (isset($folders[$id]) ? (array)$folders[$id] : array() as $f) {
            $fid = (int)$f['id'];
            if (!empty($f['archived']) || !$walk($fid)) continue;
            $rows[] = $row('folder', $f['name'], '', '', (string)$fid, false, isset($f['access']) ? $f['access'] : '');
        }
        if (!empty($view[$id])) {
            foreach (isset($pages[$id]) ? (array)$pages[$id] : array() as $p) {
                if ((int)$p['id'] === (int)$excluded_page_id) continue;
                $title = (string)($p['title'] ?? '');
                $rows[] = $row('page', $title !== '' ? $title : (string)$p['name'], $base . encode_url_path((string)$p['name']),
                    (string)($p['meta_description'] ?? ''));
            }
            foreach (isset($files[$id]) ? (array)$files[$id] : array() as $file) {
                $rows[] = $row('file', $file['name'], $base . encode_url_path((string)$file['name']),
                    (string)($file['description'] ?? ''), '', !empty($file['design']));
            }
        }
        if ($rows) $out[$id] = $rows;
        return (bool)$rows;
    };
    $walk((int)$root_id);
    return $out;
}

/**
 * The widget's visibility flags.
 *
 * @param array $settings pg_fv_settings()
 * @param array $top      the rows of the page's folder
 * @param bool  $start    the visitor has a start page other than this one
 * @return array
 */
function pg_fv_flags($settings, $top, $start)
{
    $included = !empty($settings['pages']) || !empty($settings['files']);
    $items = $included && !empty($top);
    $fv_flags = array(
        'has_folder_items' => $items,
        'no_folder_items'  => $included && !$items,
        'nothing_included' => !$included,
        'has_start_page'   => (bool)$start,
    );
    return $fv_flags;
}

// What the folder view of $root_id may show, read as the classic page reads
// it, in the shape pg_sw_list_folder_view_rows() takes. A folder is read
// once, whatever its parents say.
function _pg_fv_read($root_id, $include_pages, $include_files)
{
    $data = array('folders' => array(), 'pages' => array(), 'files' => array(), 'view' => array());
    $hidden = "'" . implode("', '", array_map('e', pg_fv_hidden_page_types())) . "'";
    $queue = array((int)$root_id);
    $read = array();
    // The root counts as archived as the classic page counts it.
    if ((int)db_value("SELECT folder_archived FROM folder WHERE folder_id = '" . (int)$root_id . "'")) return $data;
    while ($queue) {
        $id = array_shift($queue);
        if (isset($read[$id])) continue;
        $read[$id] = true;
        $folders = (array)db_items(
            "SELECT folder_id AS id, folder_name AS name, folder_archived AS archived
            FROM folder
            WHERE folder_parent = '" . $id . "'
            ORDER BY folder_order ASC, folder_name ASC");
        foreach ($folders as $i => $f) {
            $folders[$i]['access'] = get_access_control_type($f['id']);
            if (empty($f['archived'])) $queue[] = (int)$f['id'];
        }
        $data['folders'][$id] = $folders;
        $data['view'][$id] = (check_view_access($id, true) == true);
        if (!$data['view'][$id]) continue;
        if ($include_pages) {
            $data['pages'][$id] = (array)db_items(
                "SELECT page_id AS id, page_name AS name, page_title AS title, page_meta_description AS meta_description
                FROM page
                WHERE (page_folder = '" . $id . "') AND (page_search = '1') AND (page_type NOT IN (" . $hidden . "))
                ORDER BY page_name ASC");
        }
        if ($include_files) {
            $data['files'][$id] = (array)db_items(
                "SELECT name, description, design FROM files WHERE folder = '" . $id . "' ORDER BY name ASC");
        }
    }
    return $data;
}

// ============================================================================
// folder_view
// ============================================================================

// Render a 'folder_view' system widget.
//
// loop_area: one row per entry of the page's folder (sub-folders, pages,
// files). Row tokens (the main loop and the folder_view.children lists
// alike, pg_sw_list_folder_view_rows()): ^^__folder_item_name^^,
// ^^__folder_item_key^^, ^^__folder_item_url^^,
// ^^__folder_item_description^^, ^^__folder_item_is_folder^^,
// ^^__folder_item_is_page^^, ^^__folder_item_is_file^^,
// ^^__folder_item_is_design^^ ('1' or empty, for conditions),
// ^^__folder_item_access^^.
// Tokens: ^^__folder_name^^, ^^__start_page_url^^; ^^__folder_item_key^^
// reads the page's folder outside the main loop, so a list there lists
// the top level.
// Flags: pg_fv_flags(). Messages: 'folder_view'.
// cfg: include_pages, include_files.
function _render_system_widget_folder_view($tree_json, $widget_id, $cfg = array(), $mode = 'preview')
{
    $widget_id = (int)$widget_id;
    if ($tree_json === '' || $tree_json === null) return '';
    if (!is_array($cfg)) $cfg = array();
    $tree = json_decode($tree_json, true);
    if (!is_array($tree)) return '';
    // What a visitor sees depends on the folders they may view.
    if (function_exists('pg_page_cache_personal')) pg_page_cache_personal('access');

    $page_id = _pg_sa_page_id();
    $folder_id = ($page_id > 0) ? (int)db_value("SELECT page_folder FROM page WHERE page_id = '" . $page_id . "'") : 0;
    $settings = pg_fv_settings($page_id > 0 ? db_item("SELECT pages, files FROM folder_view_pages WHERE page_id = '" . $page_id . "'") : null, $cfg);
    $by_folder = array();
    if ($folder_id > 0 && ($settings['pages'] || $settings['files'])) {
        $by_folder = pg_sw_list_folder_view_rows($folder_id, _pg_fv_read($folder_id, $settings['pages'], $settings['files']),
            $page_id, defined('OUTPUT_PATH') ? OUTPUT_PATH : '/');
    }
    $top = isset($by_folder[$folder_id]) ? $by_folder[$folder_id] : array();

    // The visitor's start page, unless it is this one.
    $start_url = '';
    if (defined('USER_START_PAGE_ID') && USER_START_PAGE_ID != 0 && USER_START_PAGE_ID != $page_id) {
        $start_name = get_page_name(USER_START_PAGE_ID);
        if ($start_name != '') $start_url = (defined('OUTPUT_PATH') ? OUTPUT_PATH : '/') . encode_url_path($start_name);
    }

    $fv_flags = pg_fv_flags($settings, $top, $start_url !== '');
    _eo_apply_visibility_bindings($tree, $fv_flags);
    _pg_member_drop_empty_links($tree, array('__start_page_url' => $start_url));
    _pg_inject_messages_node($tree, 'folder_view');

    $split = _split_widget_tree($tree);
    $loop_template = '';
    if ($split['loop_children'] === null) {
        $static_html = trim(_render_tree_node($tree, 0, 0));
    } else {
        foreach ($split['loop_children'] as $child) $loop_template .= _render_tree_node($child, 0, 0);
        $loop_template = trim($loop_template);
        $static_html = trim(_render_tree_node($split['static_tree'], 0, 0));
    }
    // The folder lists' rows, filed once the tree is drawn: only a widget
    // that drew such a list keeps them.
    pg_sw_list_publish('folder_view', array('folder_view.children' => $by_folder));

    $loop_rendered = '';
    if ($loop_template !== '') {
        foreach ($top as $i => $row) {
            $row_html = pg_sw_sweep_tokens(_pg_sw_fill_tokens($loop_template, pg_sw_escape_token_values($row)));
            $loop_rendered .= pg_sw_uniquify_row_ids($row_html, $widget_id, $i + 1);
        }
    }
    $static_values = pg_sw_escape_token_values(array(
        '__folder_name'     => $folder_id > 0 ? (string)db_value("SELECT folder_name FROM folder WHERE folder_id = '" . $folder_id . "'") : '',
        '__start_page_url'  => $start_url,
        // The key a folder list reads outside the main loop: the top level.
        '__folder_item_key' => (string)$folder_id,
    ));
    $static_html = pg_sw_sweep_tokens(_pg_sw_fill_tokens($static_html, $static_values));
    return str_replace('<!--pg-loop-slot-->', $loop_rendered, $static_html);
}
