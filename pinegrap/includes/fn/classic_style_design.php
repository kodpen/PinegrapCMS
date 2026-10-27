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
 * A classic style opened in the visual designer (edit_system_style.php
 * ?classic=1): the frame component made, and every page that names the
 * style as its own (page_style) with its converted tree, not yet saved; a
 * page with a tree of its own keeps it.
 * A page that takes the style from its folder is not one of the design's
 * pages: it keeps drawing the style's code.
 *
 * @return array  'error' ('' when opened), 'pages' (pg_designer_load_pages()
 *                rows with tree_json and converted = 1), 'css', 'js', 'fonts'
 *                (the assets panel lists, JSON), 'warnings'
 */
function pg_classic_style_design_open($style_id, $user)
{
    $style_id = (int)$style_id;
    $out = array('error' => '', 'pages' => array(), 'css' => '', 'js' => '', 'fonts' => '', 'warnings' => array());

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

    // A <cregion> stays a region node of its kind; making components of the
    // regions is a step of its own (common-region-to-shared-component.md).
    $lookup = function ($name) {
        $hit = db_item("SELECT cregion_designer_type FROM cregion WHERE cregion_name = '" . e($name) . "' LIMIT 1");
        return array('regionType' => (is_array($hit) && $hit['cregion_designer_type'] === 'yes') ? 'designer' : 'common');
    };
    $frame = pg_classic_style_frame((string)$row['style_code'], $lookup);

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
    return $out;
}
