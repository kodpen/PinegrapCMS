<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * A classic style (a `style` row whose style_code is edited as text) opened
 * in the visual designer. The style's code is the frame around every page
 * that uses it, so it becomes one shared component, read by the HTML
 * importer (pg_designer_import_html()): the region placeholders stay region
 * nodes and print the same tags, so the page regions, the system region and
 * its custom layout are filled on each page as before. Every page of the
 * style gets a tree of its own placing that component, with the style's
 * body attributes and title on its root.
 *
 * Nothing on the site changes when the editor opens: the component is made
 * (or an unused one made before is brought back to the style's code), the
 * pages are not written. The design's Save writes the pages' trees and
 * makes the style row a visual design (save_system_style()); the style's
 * code stays on the row as the fallback of a page with no tree of its own.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/designer_import.php');

/**
 * Why a style row cannot be opened in the visual designer, or '' when it
 * can: only a classic style edited as code (style_type 'custom') with code
 * of its own. A visual design is already one; an older system style is
 * drawn from its cells, not from code.
 *
 * @param array $row  style_type, style_layout, style_code
 */
function pg_classic_style_refusal($row)
{
    if (!is_array($row)) return lang('Record not found.');
    if ((string)($row['style_layout'] ?? '') === 'visual_designer') {
        return lang('This style is already a visual design.');
    }
    if ((string)($row['style_type'] ?? '') !== 'custom') {
        return lang('Only a style edited as code can be opened in the visual designer.');
    }
    if (trim((string)($row['style_code'] ?? '')) === '') {
        return lang('The style has no code to open.');
    }
    return '';
}

/**
 * A classic style's code read as the frame of its pages. The theme's own
 * framework files ({path}bootstrap.css, jquery) are kept: the theme is not
 * written for the designer's Bootstrap 5, so the design is a custom one.
 *
 * Pure: no query, no file.
 *
 * @param string        $style_code
 * @param callable|null $lookup  function ($name) for a <cregion> tag, as the
 *                               $lookup of pg_di_designer_region_tree()
 * @return array  'frame' (root node of the component), 'root_props' (body
 *                attributes and title for each page's root), 'css', 'js',
 *                'fonts' (assets panel entries), 'warnings'
 */
function pg_classic_style_frame($style_code, $lookup = null)
{
    $ctx = new stdClass();
    $ctx->user_id        = 0;
    $ctx->files          = array();
    $ctx->folders        = array();
    $ctx->warnings       = array();
    $ctx->root_id        = 0;
    $ctx->project        = 'style';
    $ctx->keep_framework = true;
    if ($lookup !== null) $ctx->region_lookup = $lookup;

    $res = pg_designer_import_html((string)$style_code, 'style.html', $ctx);
    $tree = is_array($res['tree']) ? $res['tree'] : pg_di_root(array());
    $props = (isset($tree['props']) && is_array($tree['props'])) ? $tree['props'] : array();

    $frame = pg_di_root(isset($tree['children']) ? $tree['children'] : array());
    $seq = 0;
    pg_di_assign_ids($frame, $seq);

    return array(
        'frame'      => $frame,
        'root_props' => $props,
        'css'        => pg_di_dedupe_assets($res['css']),
        'js'         => pg_di_dedupe_assets($res['js']),
        'fonts'      => $res['fonts'],
        'warnings'   => array_values(array_unique(array_merge($res['warnings'], $ctx->warnings))),
    );
}

/**
 * The file name a classic style's link to one of the site's own files
 * names ({path}custom.css, or the same under the site's output path), or ''
 * for any other reference: a CDN, a software file ({software_directory}), a
 * path below a folder (files are stored flat, by name).
 *
 * Pure: no query, no file.
 *
 * @param string $ref          the link's href or the script's src
 * @param string $output_path  OUTPUT_PATH ('' to skip that form)
 */
function pg_classic_style_site_file_name($ref, $output_path)
{
    $ref = trim((string)$ref);
    $rest = null;
    if (stripos($ref, '{path}') === 0) {
        $rest = (string)substr($ref, 6);
    } elseif ($output_path !== '' && $output_path !== '/' && strpos($ref, (string)$output_path) === 0) {
        $rest = (string)substr($ref, strlen($output_path));
    } elseif ($output_path === '/' && $ref !== '' && $ref[0] === '/' && substr($ref, 0, 2) !== '//') {
        $rest = (string)substr($ref, 1);
    }
    if ($rest === null || $rest === '' || stripos($rest, '{software_directory}') !== false) return '';
    $rest = (string)preg_replace('/[?#].*$/s', '', $rest);
    if ($rest === '' || strpos($rest, '/') !== false || strpos($rest, '\\') !== false) return '';
    return rawurldecode($rest);
}

/**
 * The style's own CSS/JS files made managed file entries of the design
 * (the shape designer_file/create writes): the assets panel opens them as
 * editable tabs (designer_file/read, save) once the page designer is gone.
 * A link to anything else stays as the importer wrote it, and so does a
 * theme file: the theme screens edit it.
 *
 * Pure apart from $is_own_file.
 *
 * @param array    $list         assets panel entries (css or js)
 * @param callable $is_own_file  function ($name): true for a design file of
 *                               the site that is not a theme
 * @param string   $output_path  OUTPUT_PATH
 */
function pg_classic_style_managed_files($list, $is_own_file, $output_path)
{
    $out = array();
    foreach ((array)$list as $a) {
        if (is_array($a) && empty($a['_file']) && in_array($a['type'] ?? '', array('external-css', 'external-js'), true)) {
            $name = pg_classic_style_site_file_name((string)($a['content'] ?? ''), (string)$output_path);
            if ($name !== '' && $is_own_file($name)) {
                $a['name']    = $name;
                $a['content'] = (string)$output_path . $name;
                $a['_file']   = true;
            }
        }
        $out[] = $a;
    }
    return pg_di_dedupe_assets($out);
}

/**
 * The tree of one page of a converted style: the style's body attributes
 * and title on the root, the frame component as its only child.
 *
 * @param array  $root_props  pg_classic_style_frame()'s 'root_props'
 * @param int    $shared_id   the frame component's id
 * @param string $shared_name its name (a display snapshot)
 */
function pg_classic_style_page_tree($root_props, $shared_id, $shared_name)
{
    $ref = pg_di_mk('shared_ref', array('sharedId' => (int)$shared_id, 'sharedName' => (string)$shared_name));
    $ref['_id'] = 'sd_frame';
    return pg_di_root(array($ref), is_array($root_props) ? $root_props : array());
}

// The category that marks the shared component made from a classic style.
function pg_classic_style_component_category($style_id)
{
    return 'classic_style:' . (int)$style_id;
}

/**
 * The shared component drawing a classic style's frame: the one made before
 * (category "classic_style:<id>"), brought back to the style's code while no
 * page or other component places it, else a new designer-only row. A row a
 * saved page places is left as it is: it is the design's now.
 *
 * @return array|null  array('id' => int, 'name' => string), null when it could not be written
 */
function pg_classic_style_component($style_id, $name, $frame, $user)
{
    $tree_json = pg_designer_tree_encode($frame);
    if ($tree_json === '') return null;
    $marker = pg_classic_style_component_category($style_id);
    $now = time();

    $have = db_item("SELECT id, name FROM shared_components
                     WHERE category = '" . e($marker) . "' AND (system_region_config IS NULL OR system_region_config = '')
                     ORDER BY id ASC LIMIT 1");
    if (is_array($have)) {
        $sid = (int)$have['id'];
        if (!_pg_tpl_row_in_use($sid)) {
            db("UPDATE shared_components SET tree_json = '" . e($tree_json) . "', updated_at = '$now' WHERE id = '$sid' LIMIT 1");
        }
        return array('id' => $sid, 'name' => (string)$have['name']);
    }

    $unique = _sc_unique_name((string)$name);
    db("INSERT INTO shared_components (name, description, tree_json, category, created_by, created_at, updated_at)
        VALUES ('" . e($unique) . "', '', '" . e($tree_json) . "', '" . e($marker) . "', '" . (int)$user['id'] . "', '$now', '$now')");
    $sid = (int)mysqli_insert_id(db::$con);
    if ($sid <= 0) return null;
    return array('id' => $sid, 'name' => $unique);
}

/**
 * The $lookup of pg_classic_style_frame() for a <cregion> tag. A common
 * region already made a shared component is placed as that component: its
 * tag draws the component anyway (pg_common_region_components()), and the
 * canvas shows the content instead of a placeholder. Any other <cregion>
 * stays a region node of its kind; no region is converted here.
 *
 * @return callable
 */
function pg_classic_style_region_lookup()
{
    return function ($name) {
        $hit = db_item("SELECT cregion_id, cregion_designer_type FROM cregion WHERE cregion_name = '" . e($name) . "' LIMIT 1");
        if (!is_array($hit)) return array('regionType' => 'common');
        if ($hit['cregion_designer_type'] === 'yes') return array('regionType' => 'designer');
        $made = db_item("SELECT id, name FROM shared_components
                         WHERE category = '" . e(pg_common_region_component_category($hit['cregion_id'])) . "'
                           AND (system_region_config IS NULL OR system_region_config = '')
                         ORDER BY id ASC LIMIT 1");
        if (is_array($made)) return array('sharedId' => (int)$made['id'], 'sharedName' => (string)$made['name']);
        return array('regionType' => 'common');
    };
}

/**
 * A classic style opened in the visual designer (edit_system_style.php
 * ?classic=1): the frame component made, and every page that names the
 * style as its own (page_style) with its converted tree, not yet saved; a
 * page with a tree of its own keeps it.
 * A page that takes the style from its folder is not one of the design's
 * pages: it is listed in 'folder_pages', and Save converts it only when the
 * editor's "Also convert" box is left on (pg_classic_style_convert_folder_pages()).
 *
 * @return array  'error' ('' when opened), 'pages' (pg_designer_load_pages()
 *                rows with tree_json and converted = 1), 'css', 'js', 'fonts'
 *                (the assets panel lists, JSON), 'warnings', 'folder_pages'
 *                (pg_classic_style_folder_pages())
 */
function pg_classic_style_design_open($style_id, $user)
{
    $style_id = (int)$style_id;
    $out = array('error' => '', 'pages' => array(), 'css' => '', 'js' => '', 'fonts' => '', 'warnings' => array(), 'folder_pages' => array());

    if ((int)$user['role'] > 1) {
        $out['error'] = lang('Only a designer can open a classic style in the visual designer.');
        return $out;
    }
    $row = db_item("SELECT style_id, style_name, style_type, style_layout, style_code FROM style WHERE style_id = '$style_id' LIMIT 1");
    $refusal = pg_classic_style_refusal($row);
    if ($refusal !== '') {
        $out['error'] = $refusal;
        return $out;
    }

    $frame = pg_classic_style_frame((string)$row['style_code'], pg_classic_style_region_lookup());

    // The style's own design files stay editable in the designer.
    $is_own_file = function ($name) {
        $file = db_item("SELECT theme FROM files WHERE design = '1' AND name = '" . e($name) . "' LIMIT 1");
        return is_array($file) && (string)$file['theme'] !== '1';
    };
    $frame['css'] = pg_classic_style_managed_files($frame['css'], $is_own_file, OUTPUT_PATH);
    $frame['js']  = pg_classic_style_managed_files($frame['js'],  $is_own_file, OUTPUT_PATH);

    $component = pg_classic_style_component($style_id, (string)$row['style_name'], $frame['frame'], $user);
    if (!is_array($component)) {
        $out['error'] = lang('The style\'s frame could not be saved as a shared component.');
        return $out;
    }

    $tree_json = pg_designer_tree_encode(pg_classic_style_page_tree($frame['root_props'], $component['id'], $component['name']));
    foreach (pg_designer_load_pages($style_id, '', true) as $page) {
        // A page that already has a tree of its own keeps it.
        if ($page['tree_json'] !== '') {
            $out['pages'][] = $page;
            continue;
        }
        $page['tree_json'] = $tree_json;
        $page['converted'] = 1;
        $out['pages'][] = $page;
    }

    $out['css']   = $frame['css']   ? encode_json($frame['css'])   : '';
    $out['js']    = $frame['js']    ? encode_json($frame['js'])    : '';
    $out['fonts'] = $frame['fonts'] ? encode_json($frame['fonts']) : '';
    $out['warnings'] = $frame['warnings'];
    $out['folder_pages'] = pg_classic_style_folder_pages($style_id);
    return $out;
}

/**
 * Each folder's desktop style as get_style() works it out: the folder's own
 * folder_style, else its parent's, up to the root folder (0 there). A
 * parent that is missing or a loop in the rows ends the walk with 0.
 *
 * Pure: no query.
 *
 * @param array $folders  rows with folder_id, folder_parent, folder_style, folder_level
 * @return array  folder_id => style id
 */
function pg_classic_style_folder_styles($folders)
{
    $by_id = array();
    foreach ((array)$folders as $f) {
        if (is_array($f) && isset($f['folder_id'])) $by_id[(int)$f['folder_id']] = $f;
    }
    $out = array();
    foreach (array_keys($by_id) as $start) {
        $path = array();
        $id = $start;
        $style = 0;
        while (true) {
            if (isset($out[$id])) { $style = $out[$id]; break; }
            if (!isset($by_id[$id]) || isset($path[$id])) break;
            $path[$id] = true;
            $f = $by_id[$id];
            if ((int)($f['folder_style'] ?? 0) > 0) { $style = (int)$f['folder_style']; break; }
            if ((int)($f['folder_level'] ?? 0) === 0) break;
            $id = (int)($f['folder_parent'] ?? 0);
        }
        foreach (array_keys($path) as $seen) $out[$seen] = $style;
        $out[$start] = $style;
    }
    return $out;
}

/**
 * The pages that take a style from their folder: no page_style of their
 * own, no tree of their own, not in the recycle bin, in a folder whose
 * style (pg_classic_style_folder_styles()) is this one. By name.
 *
 * @return array  rows: page_id, page_name, page_folder (ints and strings)
 */
function pg_classic_style_folder_pages($style_id)
{
    $style_id = (int)$style_id;
    if ($style_id <= 0 || !pg_multi_page_design_ready()) return array();

    $folders = db_items("SELECT folder_id, folder_parent, folder_style, folder_level FROM folder");
    $ids = array();
    foreach (pg_classic_style_folder_styles(is_array($folders) ? $folders : array()) as $folder_id => $folder_style) {
        if ($folder_style === $style_id) $ids[] = (int)$folder_id;
    }
    if (!$ids) return array();

    $rows = db_items(
        "SELECT page_id, page_name, page_folder FROM page
         WHERE (page_style = 0 OR page_style IS NULL)
           AND (page_tree_json IS NULL OR page_tree_json = '')
           AND page_folder IN (" . implode(',', $ids) . ")" . pg_designer_not_binned_sql() . "
         ORDER BY page_name ASC, page_id ASC");
    $out = array();
    foreach ((is_array($rows) ? $rows : array()) as $r) {
        $out[] = array('page_id' => (int)$r['page_id'], 'page_name' => (string)$r['page_name'], 'page_folder' => (int)$r['page_folder']);
    }
    return $out;
}

/**
 * The editor's "Also convert the N pages that take this style from their
 * folder" box, on by default, with the pages listed. It sits outside the
 * editor's form and posts with it through its form attribute.
 *
 * @param array $pages  pg_classic_style_folder_pages()
 * @return string  '' when there is none
 */
function pg_classic_style_folder_pages_field($pages)
{
    $pages = (array)$pages;
    if (!$pages) return '';
    $items = '';
    foreach ($pages as $p) {
        $items .= '<li>' . h((string)$p['page_name']) . '</li>';
    }
    return '
            <div class="alert theme-secondary py-3 small mb-0" id="sd-classic-folder-pages">
                <div class="form-field mb-0">
                    <input class="check" type="checkbox" id="sd-classic-folder-pages-on" name="classic_folder_pages" value="1" form="style_designer_form" checked>
                    <label for="sd-classic-folder-pages-on">' . h(count($pages) === 1
                        ? lang('Also convert the page that takes this style from its folder')
                        : lang(array('string' => 'Also convert the {var:1} pages that take this style from their folder', 'vars' => array(count($pages))))) . '</label>
                </div>
                <details class="mt-2">
                    <summary>' . h(lang('Pages')) . '</summary>
                    <ul class="mb-0 mt-1">' . $items . '</ul>
                </details>
            </div>';
}

/**
 * The tree of a page that took the converted style from its folder: the
 * same tree as the style's own pages (pg_classic_style_page_tree()), with
 * the design-wide body classes the save folds onto every page added to the
 * root's classes.
 *
 * Pure: no query.
 *
 * @param string $body_classes  space-separated classes, '' for none
 */
function pg_classic_style_folder_page_tree($root_props, $shared_id, $shared_name, $body_classes)
{
    $tree = pg_classic_style_page_tree($root_props, $shared_id, $shared_name);
    $merged = array();
    foreach (preg_split('/\s+/', (string)$body_classes . ' ' . (string)($tree['props']['cssClass'] ?? '')) as $cls) {
        if ($cls !== '' && !in_array($cls, $merged, true)) $merged[] = $cls;
    }
    if ($merged) $tree['props']['cssClass'] = implode(' ', $merged);
    return $tree;
}

/**
 * Save of a converted style with "Also convert" on: every page that takes
 * the style from its folder gets page_style and a tree placing the frame
 * component, as the style's own pages got at open, and its body drawn. A
 * page given a style or a tree meanwhile is left alone. Only a designer.
 *
 * @param string $body_classes  the design-wide body classes being folded onto the pages
 * @return array  page ids converted
 */
function pg_classic_style_convert_folder_pages($style_id, $user, $body_classes = '')
{
    $style_id = (int)$style_id;
    if ((int)$user['role'] > 1 || !pg_multi_page_design_ready()) return array();
    $pages = pg_classic_style_folder_pages($style_id);
    if (!$pages) return array();

    $row = db_item("SELECT style_name, style_code FROM style WHERE style_id = '$style_id' LIMIT 1");
    if (!is_array($row) || trim((string)$row['style_code']) === '') return array();
    $frame = pg_classic_style_frame((string)$row['style_code'], pg_classic_style_region_lookup());
    $component = pg_classic_style_component($style_id, (string)$row['style_name'], $frame['frame'], $user);
    if (!is_array($component)) return array();

    $tree = pg_classic_style_folder_page_tree($frame['root_props'], $component['id'], $component['name'], $body_classes);
    $tree_json = pg_designer_tree_encode($tree);
    if ($tree_json === '') return array();

    // Drawing a page shows and uses up the session's pending form messages;
    // they belong to the screen this request answers.
    $has_liveforms = isset($_SESSION['software']['liveforms']);
    $liveforms     = $has_liveforms ? $_SESSION['software']['liveforms'] : null;
    unset($_SESSION['software']['liveforms']);

    $done = array();
    foreach ($pages as $p) {
        $page_id = (int)$p['page_id'];
        $code = generate_style_code_from_tree($tree, (string)$p['page_name'], '');
        db("UPDATE page
            SET page_style = '$style_id',
                page_tree_json = '" . e($tree_json) . "',
                page_tree_code = '" . e((string)$code) . "'
            WHERE page_id = '$page_id'
              AND (page_style = 0 OR page_style IS NULL)
              AND (page_tree_json IS NULL OR page_tree_json = '')
            LIMIT 1");
        if (mysqli_affected_rows(db::$con) > 0) $done[] = $page_id;
    }

    if ($has_liveforms) {
        $_SESSION['software']['liveforms'] = $liveforms;
    }
    return $done;
}
