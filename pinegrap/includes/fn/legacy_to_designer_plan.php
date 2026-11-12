<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The bulk move of classic styles to the visual designer: what a run would
 * write for each chosen style (the dry run, pg_ltd_plan()) and the server
 * twins of the editor steps a run takes without a browser.
 *
 *   - Page regions into a page tree: pg_ltd_page_regions() and
 *     pg_ltd_page_region_splice(), the twin of _sdPageRegionsConvert() on
 *     the blocks of pg_page_region_blocks().
 *   - Region nodes of the style's frame component to widgets:
 *     pg_ltd_frame_targets(), pg_ltd_widget_config(), pg_ltd_widget_name(),
 *     pg_ltd_widget_tree() (widgets_region_starters.php) and
 *     pg_ltd_frame_splice(), the twin of
 *     _sdRegionMakeWidget() (SD_REGION_WIDGETS, _swTypeDefaults(),
 *     _swAutoName(), _buildStarterTree()).
 *
 * The planner reads and never writes. The run itself uses the existing
 * converters (pg_common_region_component(), pg_designer_region_component(),
 * pg_classic_style_design_open(), pg_designer_save_page(),
 * pg_classic_style_convert_folder_pages(), pg_classic_style_mark_design()).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2026 Erdal Güral. All rights reserved.
 * @license     Proprietary, see license-editors.txt
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/legacy_to_designer.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/widgets_region_starters.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/legacy_design_theme.php');

/**
 * The classic data-driven regions a widget draws: the widget kind and the
 * setting that names the region. Twin of SD_REGION_WIDGETS
 * (style_designer.src.js).
 *
 * @return array  region type => array('kind' => widget kind, 'key' => setting)
 */
function pg_ltd_region_widgets()
{
    return array(
        'menu'          => array('kind' => 'menu_region',          'key' => 'menu_name'),
        'menu_sequence' => array('kind' => 'menu_sequence_region', 'key' => 'menu_name'),
        'ad'            => array('kind' => 'ad_region',            'key' => 'ad_region_name'),
        'tag_cloud'     => array('kind' => 'tag_cloud_region',     'key' => 'search_page_name'),
    );
}

/**
 * What a run changes inside a style's frame component: the named designer
 * region nodes (each becomes its shared component) and the named menu,
 * menu sequence, ad and tag cloud region nodes (each becomes a widget, one
 * per region and type, placed wherever the frame names it), and the page
 * region nodes it holds. Nested shared components are not entered: they
 * are not the frame.
 *
 * Pure: no query.
 *
 * @param array $frame  the frame component's tree
 * @return array  'designer' (names, each once), 'widgets' (list of
 *                array('type', 'name', 'kind', 'key'), each region once),
 *                'page_regions' (int)
 */
function pg_ltd_frame_targets($frame)
{
    $map = pg_ltd_region_widgets();
    $out = array('designer' => array(), 'widgets' => array(), 'page_regions' => 0);
    $seen = array();
    $walk = function ($node) use (&$walk, &$out, &$seen, $map) {
        if (!is_array($node)) return;
        $type = isset($node['type']) ? (string)$node['type'] : '';
        if ($type === 'shared_ref') return;
        if ($type === 'region') {
            $rtype = (string)($node['props']['regionType'] ?? '');
            $name = (string)($node['props']['regionName'] ?? '');
            if ($rtype === 'page') {
                ++$out['page_regions'];
            } elseif ($name !== '' && $rtype === 'designer') {
                if (!in_array($name, $out['designer'], true)) $out['designer'][] = $name;
            } elseif ($name !== '' && isset($map[$rtype]) && !isset($seen[$rtype . "\n" . $name])) {
                $seen[$rtype . "\n" . $name] = true;
                $out['widgets'][] = array('type' => $rtype, 'name' => $name, 'kind' => $map[$rtype]['kind'], 'key' => $map[$rtype]['key']);
            }
        }
        foreach ((array)($node['children'] ?? array()) as $child) $walk($child);
    };
    $walk($frame);
    return $out;
}

/**
 * The region nodes of a tree replaced by shared_ref nodes: each node whose
 * region type and name are in $made takes that component's place, as
 * _sdRegionMakeWidget() and the designer region conversion put it. Nested
 * shared components are not entered.
 *
 * Pure: no query.
 *
 * @param array $tree
 * @param array $made  "<regionType>\n<regionName>" => array('id' => int, 'name' => string)
 * @param int   &$count  nodes replaced
 * @return array  the tree
 */
function pg_ltd_frame_splice($tree, $made, &$count = 0)
{
    if (!is_array($tree) || ($tree['type'] ?? '') === 'shared_ref' || empty($tree['children']) || !is_array($tree['children'])) return $tree;
    foreach ($tree['children'] as $i => $child) {
        if (is_array($child) && ($child['type'] ?? '') === 'region') {
            $key = (string)($child['props']['regionType'] ?? '') . "\n" . (string)($child['props']['regionName'] ?? '');
            if (isset($made[$key]) && (int)$made[$key]['id'] > 0) {
                $ref = pg_di_mk('shared_ref', array('sharedId' => (int)$made[$key]['id'], 'sharedName' => (string)$made[$key]['name']));
                if (!empty($child['_id'])) $ref['_id'] = $child['_id'];
                $tree['children'][$i] = $ref;
                ++$count;
                continue;
            }
        }
        $tree['children'][$i] = pg_ltd_frame_splice($child, $made, $count);
    }
    return $tree;
}

/**
 * The page region nodes of a page tree, the way the site fills them (the
 * Nth <pregion> tag takes the page's Nth row): 'own' counts those of the
 * page tree, 'shared' those inside the shared components it places, and
 * 'missing' tells a placed component whose tree was not given. Twin of
 * _sdPageRegionNodes().
 *
 * Pure: no query.
 *
 * @param array $tree
 * @param array $shared  component id => its tree (decoded), for the ones the tree places
 * @return array  'own' (int), 'shared' (int), 'missing' (bool)
 */
function pg_ltd_page_regions($tree, $shared)
{
    $out = array('own' => 0, 'shared' => 0, 'missing' => false);
    $open = array();
    $walk = function ($node, $in_shared) use (&$walk, &$out, &$open, $shared) {
        if (!is_array($node)) return;
        $type = (string)($node['type'] ?? '');
        if ($type === 'region' && ($node['props']['regionType'] ?? '') === 'page') {
            if ($in_shared) ++$out['shared']; else ++$out['own'];
        }
        if ($type === 'shared_ref') {
            $sid = (int)($node['props']['sharedId'] ?? 0);
            if ($sid <= 0 || isset($open[$sid])) return;
            if (!isset($shared[$sid]) || !is_array($shared[$sid])) {
                $out['missing'] = true;
                return;
            }
            $open[$sid] = true;
            $walk($shared[$sid], true);
            unset($open[$sid]);
            return;
        }
        foreach ((array)($node['children'] ?? array()) as $child) $walk($child, $in_shared);
    };
    $walk($tree, false);
    return $out;
}

/**
 * Every page region node of a page tree replaced, in document order, by
 * the block made from the page's row with the same number
 * (pg_page_region_blocks()). All of them or none, as the editor does: a node
 * left behind would be filled with another row once the ones before it are
 * gone. Twin of the splice in _sdPageRegionsConvert().
 *
 * Pure: no query.
 *
 * @param array $tree
 * @param array $blocks  one block per own page region node
 * @return array|null  the tree, null when the count does not match
 */
function pg_ltd_page_region_splice($tree, $blocks)
{
    $blocks = array_values((array)$blocks);
    $i = 0;
    $walk = function ($node) use (&$walk, &$i, $blocks) {
        if (!is_array($node) || ($node['type'] ?? '') === 'shared_ref' || empty($node['children']) || !is_array($node['children'])) return $node;
        foreach ($node['children'] as $k => $child) {
            if (is_array($child) && ($child['type'] ?? '') === 'region' && ($child['props']['regionType'] ?? '') === 'page') {
                $node['children'][$k] = isset($blocks[$i]) ? $blocks[$i] : null;
                ++$i;
                continue;
            }
            $node['children'][$k] = $walk($child);
        }
        return $node;
    };
    $out = $walk($tree);
    return ($i === count($blocks) && $i > 0) ? $out : null;
}

/**
 * A slug as the editor writes one into a widget's name: Turkish letters
 * folded, lower case, runs of anything else one hyphen, at most 40
 * characters. Twin of _swSlug().
 *
 * Pure: no query.
 */
function pg_ltd_slug($s)
{
    $s = strtr((string)$s, array('ı' => 'i', 'İ' => 'i', 'ş' => 's', 'Ş' => 's', 'ğ' => 'g', 'Ğ' => 'g',
                                 'ü' => 'u', 'Ü' => 'u', 'ö' => 'o', 'Ö' => 'o', 'ç' => 'c', 'Ç' => 'c'));
    $s = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($s)), '-');
    return substr($s, 0, 40);
}

/**
 * The name of a widget made inside a style's frame component:
 * "<design>-<kind>-widget", as _swAutoName() names one dropped into a
 * shared component. The server adds " [n]" when it is taken.
 *
 * @param string $style_name
 * @param string $kind  a pg_ltd_region_widgets() kind
 */
function pg_ltd_widget_name($style_name, $kind)
{
    static $slugs = array('menu_region' => 'menu', 'menu_sequence_region' => 'menu-sequence',
                          'ad_region' => 'ad-region', 'tag_cloud_region' => 'tag-cloud');
    $owner = pg_ltd_slug($style_name);
    if ($owner === '') $owner = pg_ltd_slug(lang('design'));
    if ($owner === '') $owner = 'design';
    $info = isset($slugs[$kind]) ? pg_ltd_slug(lang($slugs[$kind])) : '';
    return $owner . ($info !== '' ? '-' . $info : '') . '-widget';
}

/**
 * The settings a widget made from a region node starts with: its kind and
 * the region's name, the keys _swTypeDefaults() gives these kinds.
 *
 * Pure: no query.
 *
 * @param array $target  a pg_ltd_frame_targets() widget entry
 * @return array
 */
function pg_ltd_widget_config($target)
{
    return array('regionType' => (string)$target['kind'], (string)$target['key'] => (string)$target['name']);
}

/**
 * What a run would write for one style, from what the planner read. A
 * style the designer refuses, or one with none of its pages free, is
 * skipped with its reason. Pages that already have a tree keep it; pages
 * somebody holds in the designer are skipped and listed; the rest get the
 * tree placing the frame. Page region nodes inside the frame stay: the
 * frame is drawn with every page's rows, as the editor refuses to move them
 * into one page.
 *
 * Pure: no query.
 *
 * @param array $facts  'refusal' (string), 'common' (names of the style's
 *                      common regions still to make components), 'frame'
 *                      (pg_ltd_frame_targets() of the frame tree),
 *                      'frame_exists' (bool), 'designer_made' (names of
 *                      designer regions already components), 'pages'
 *                      (rows: page_id, page_name, source 'own'|'folder',
 *                      has_tree, holder ('' when free), own_regions (page
 *                      region nodes of an existing tree)), 'warnings',
 *                      'theme_after' (the theme the design loads when the
 *                      classic head loaded CSS after it, else '')
 * @return array  'skip' ('' or a reason code), 'components' (list of
 *                array('kind' => 'common'|'designer', 'name', 'exists')),
 *                'frame' ('make'|'update'), 'pages' (rows with 'action':
 *                'tree', 'keep', 'regions' or 'held'), 'widgets',
 *                'frame_page_regions', 'warnings', 'writes' (int)
 */
function pg_ltd_plan_style($facts)
{
    $f = (array)$facts;
    $frame = isset($f['frame']) && is_array($f['frame']) ? $f['frame'] : array('designer' => array(), 'widgets' => array(), 'page_regions' => 0);
    $out = array('skip' => '', 'components' => array(), 'frame' => empty($f['frame_exists']) ? 'make' : 'update',
                 'pages' => array(), 'widgets' => $frame['widgets'], 'frame_page_regions' => (int)$frame['page_regions'],
                 'warnings' => array_values((array)($f['warnings'] ?? array())), 'writes' => 0);
    if ((string)($f['refusal'] ?? '') !== '') {
        $out['skip'] = 'refusal';
        $out['widgets'] = array();
        return $out;
    }
    // The design prints its theme after all of its stylesheets; CSS the
    // classic head loaded after the theme now loses to it.
    if ((string)($f['theme_after'] ?? '') !== '') {
        $out['warnings'][] = lang(array('string' => 'The style\'s head loads stylesheets after its theme {var:1}. The design loads the theme after all of its stylesheets, so where both set the same rule the theme now wins.', 'vars' => array((string)$f['theme_after'])));
    }

    foreach ((array)($f['common'] ?? array()) as $name) {
        $out['components'][] = array('kind' => 'common', 'name' => (string)$name, 'exists' => false);
    }
    $made = array_flip(array_map('strval', (array)($f['designer_made'] ?? array())));
    foreach ($frame['designer'] as $name) {
        $out['components'][] = array('kind' => 'designer', 'name' => (string)$name, 'exists' => isset($made[$name]));
    }

    $free = 0;
    foreach ((array)($f['pages'] ?? array()) as $p) {
        $p = (array)$p;
        if ((string)($p['holder'] ?? '') !== '') {
            $p['action'] = 'held';
        } elseif (!empty($p['has_tree'])) {
            $p['action'] = ((int)($p['own_regions'] ?? 0) > 0) ? 'regions' : 'keep';
        } else {
            $p['action'] = 'tree';
        }
        if ($p['action'] === 'tree' || $p['action'] === 'regions') ++$free;
        $out['pages'][] = $p;
    }

    foreach ($out['components'] as $c) {
        if (!$c['exists']) ++$out['writes'];
    }
    // The frame component, the style itself, the pages and the widgets.
    $out['writes'] += 2 + $free + count($out['widgets']);
    return $out;
}

/**
 * The asset rows of a set's design. A stylesheet or script file is a row
 * once, on every page: every style of the set loads the same ones in the
 * same order (pg_ltd_set_key()). An inline block (the importer's 'css' or
 * 'js' row of a style) that every style with pages has is a row on every
 * page too; one only some styles have is a row kept to their pages
 * ('pages', read by pg_design_asset_on_page()), named after the first of
 * them. A style with no page adds no inline row: there is nowhere to load it.
 *
 * Pure: no query.
 *
 * @param array $styles  list of array('id', 'name', 'pages' (page ids), 'css',
 *                       'js' (the importer's asset entries of its code))
 * @return array  'css', 'js': asset entries in the order they first stand;
 *                a kept row carries 'pages' and 'styles' (the names)
 */
function pg_ltd_set_assets($styles)
{
    $with_pages = 0;
    foreach ((array)$styles as $s) {
        if (!empty($s['pages'])) ++$with_pages;
    }
    $out = array('css' => array(), 'js' => array());
    foreach (array('css', 'js') as $kind) {
        $files = array();
        $inline = array();
        $order = array();
        foreach ((array)$styles as $s) {
            $pages = array_map('intval', (array)($s['pages'] ?? array()));
            foreach ((array)($s[$kind] ?? array()) as $a) {
                if (!is_array($a)) continue;
                $type = (string)($a['type'] ?? '');
                $content = (string)($a['content'] ?? '');
                if ($type === 'css' || $type === 'js') {
                    if (!$pages) continue;
                    $k = $type . '|' . (empty($a['module']) ? '' : 'module|') . md5($content);
                    if (!isset($inline[$k])) {
                        $inline[$k] = array('entry' => $a, 'styles' => array(), 'pages' => array());
                        $order[] = array('inline', $k);
                    }
                    if (!in_array((string)$s['name'], $inline[$k]['styles'], true)) {
                        $inline[$k]['styles'][] = (string)$s['name'];
                        $inline[$k]['pages'] = array_merge($inline[$k]['pages'], $pages);
                    }
                    continue;
                }
                $k = $type . '|' . $content;
                if (!isset($files[$k])) {
                    $files[$k] = $a;
                    $order[] = array('file', $k);
                }
            }
        }
        foreach ($order as $o) {
            if ($o[0] === 'file') {
                $out[$kind][] = $files[$o[1]];
                continue;
            }
            $row = $inline[$o[1]];
            $entry = $row['entry'];
            if (count($row['styles']) < $with_pages) {
                $name = (string)($entry['name'] ?? '');
                $at = strpos($name, '-inline');
                $slug = pg_ltd_slug($row['styles'][0]);
                $entry['name'] = ($slug !== '' ? $slug : 'style') . ($at !== false ? substr($name, $at) : '-inline.' . $kind);
                $entry['pages'] = array_values(array_unique($row['pages']));
                $entry['styles'] = $row['styles'];
                $entry['id'] = 'imp_' . substr(md5($entry['type'] . '|' . $entry['name'] . '|' . $entry['content'] . '|' . implode(',', $entry['pages'])), 0, 10);
            }
            $out[$kind][] = $entry;
        }
    }
    return $out;
}

/**
 * What a run would write for a set: one Legacy design (its own style row,
 * with the set's asset rows), then per style what pg_ltd_plan_style() lists
 * (its frame component, its pages, components and widgets) but no flip of
 * the classic style stays as it is, its pages take the
 * design as their style. A common or designer region several styles place
 * becomes one shared component, listed (and counted) under the first of
 * them. A style the designer refuses is skipped with its reason; a set none
 * of whose styles can move is skipped as 'empty', one whose moving styles
 * have no page (a set moved before) as 'no_pages'.
 *
 * Pure: no query.
 *
 * @param array $facts  'key', 'name' (the design's), 'theme' (its theme
 *                      file's name, '' for the activated theme),
 *                      'collection', 'settings' (pg_ltd_sets()'s), 'social_on'
 *                      (the site shows sharing buttons), 'mobile_on' (the
 *                      site serves phones their mobile style), 'styles' (list of
 *                      array('id', 'name', 'state', 'facts'
 *                      (pg_ltd_plan_style()'s), 'body_classes', 'style_head'
 *                      (bool), 'mobile_pages', 'mobile', 'social_position',
 *                      'layout_type')), 'assets' (pg_ltd_set_assets())
 * @return array  'kind' => 'set', 'key', 'name', 'theme', 'collection',
 *                'settings', 'social_on', 'assets', 'styles' (each id, name,
 *                state, pg_ltd_plan_style()'s keys, 'folder_pages' (folder
 *                pages that move), 'body_classes', 'style_head',
 *                'mobile_pages', 'mobile', 'changes' (list of
 *                array('setting', 'from', 'to'): what its pages would show
 *                differently), 'writes' (its own, the design not counted)),
 *                'mobile_pages' (pages of its moving styles that have a
 *                mobile style), 'skip' ('', 'empty' or 'no_pages'), 'writes'
 */
function pg_ltd_plan_set($facts)
{
    $f = (array)$facts;
    $out = array('kind' => 'set', 'key' => (string)($f['key'] ?? ''), 'name' => (string)($f['name'] ?? ''),
                 'theme' => (string)($f['theme'] ?? ''), 'collection' => (string)($f['collection'] ?? ''),
                 'settings' => (array)($f['settings'] ?? array()), 'social_on' => !empty($f['social_on']),
                 'mobile_on' => !empty($f['mobile_on']),
                 'assets' => isset($f['assets']) && is_array($f['assets']) ? $f['assets'] : array('css' => array(), 'js' => array()),
                 'styles' => array(), 'mobile_pages' => 0, 'skip' => '', 'writes' => 0);
    $moving = 0;
    $pages = 0;
    $made_common = array();
    $made_designer = array();
    foreach ((array)($f['styles'] ?? array()) as $s) {
        // A region an earlier style of the set makes a component is there
        // already when the run reaches this one.
        $sf = (array)($s['facts'] ?? array());
        $common = array();
        foreach ((array)($sf['common'] ?? array()) as $name) {
            if (!isset($made_common[(string)$name])) $common[] = (string)$name;
        }
        $sf['common'] = $common;
        $sf['designer_made'] = array_merge(array_map('strval', (array)($sf['designer_made'] ?? array())), array_map('strval', array_keys($made_designer)));
        $plan = pg_ltd_plan_style($sf);
        if ($plan['skip'] === '') {
            foreach ($plan['components'] as $c) {
                if ($c['kind'] === 'common') $made_common[$c['name']] = true;
                else $made_designer[$c['name']] = true;
            }
        }
        $folder = 0;
        foreach ($plan['pages'] as $p) {
            if (($p['source'] ?? '') === 'folder' && ($p['action'] === 'tree' || $p['action'] === 'regions')) ++$folder;
        }
        // The settings the design holds once, where this style had another.
        $changes = array();
        foreach (array('social_networking_position' => 'social_position', 'layout_type' => 'layout_type') as $setting => $field) {
            if ($setting === 'social_networking_position' && !$out['social_on']) continue;
            $to = (string)($out['settings'][$setting] ?? '');
            $from = (string)($s[$field] ?? '');
            if ($from !== $to) $changes[] = array('setting' => $setting, 'from' => $from, 'to' => $to);
        }
        $out['styles'][] = array_merge(
            array('id' => (int)($s['id'] ?? 0), 'name' => (string)($s['name'] ?? ''), 'state' => (string)($s['state'] ?? '')),
            $plan,
            array('folder_pages' => $folder, 'body_classes' => trim((string)($s['body_classes'] ?? '')),
                  // Mobile styles are read only while the site serves them.
                  'style_head' => !empty($s['style_head']), 'mobile_pages' => $out['mobile_on'] ? (int)($s['mobile_pages'] ?? 0) : 0,
                  'mobile' => $out['mobile_on'] ? (int)($s['mobile'] ?? 0) : 0, 'changes' => $changes,
                  // pg_ltd_plan_style() counts the style's flip; here the
                  // design is one row of its own, counted once below.
                  'writes' => $plan['skip'] === '' ? $plan['writes'] - 1 : 0));
        if ($plan['skip'] !== '') continue;
        ++$moving;
        $pages += count($plan['pages']);
        $out['writes'] += $plan['writes'] - 1;
        if ($out['mobile_on']) $out['mobile_pages'] += (int)($s['mobile_pages'] ?? 0);
    }
    if ($moving === 0 || $pages === 0) {
        // A set moved before keeps its classic styles, with no page left.
        $out['skip'] = ($moving === 0) ? 'empty' : 'no_pages';
        $out['writes'] = 0;
    } else {
        ++$out['writes'];
    }
    return $out;
}

/**
 * What the dry run reads for one style of the report: its common regions
 * still to make components, its frame's targets, the frame component made
 * before, its pages with their holders, the importer's warnings and asset
 * entries ('_css', '_js', the style's own design files made managed ones as
 * the editor's open makes them).
 *
 * @param array $s          a pg_ltd_report() entry
 * @param array $converted  common region id => true for those already components
 * @return array  pg_ltd_plan_style()'s facts plus '_css', '_js'
 */
function pg_ltd_plan_facts($s, $converted)
{
    $facts = array('refusal' => $s['refusal'], 'common' => array(), 'pages' => array(), 'warnings' => array(),
                   'designer_made' => array(), 'frame_exists' => false, '_css' => array(), '_js' => array());
    if ($s['refusal'] === '') {
        $code = (string)db_value("SELECT style_code FROM style WHERE style_id = '" . (int)$s['id'] . "' LIMIT 1");
        $common = array();
        foreach (pg_ltd_style_tags($code)['regions'] as $r) {
            if ($r['type'] !== 'common' || isset($common[$r['name']])) continue;
            $row = db_item("SELECT cregion_id FROM cregion WHERE cregion_name = '" . e($r['name']) . "' AND cregion_designer_type = 'no' LIMIT 1");
            if (is_array($row) && !isset($converted[(int)$row['cregion_id']])) $common[$r['name']] = true;
        }
        $facts['common'] = array_keys($common);

        // Common regions still to convert are placed as components by
        // the run; read as region nodes here, they change no target.
        $frame = pg_classic_style_frame($code, pg_classic_style_region_lookup());
        $facts['frame'] = pg_ltd_frame_targets($frame['frame']);
        $facts['warnings'] = $frame['warnings'];
        $files = pg_classic_style_frame_assets($frame);
        $facts['_css'] = $files['css'];
        $facts['_js'] = $files['js'];
        foreach ($facts['frame']['designer'] as $name) {
            $made = db_value("SELECT sc.id FROM shared_components sc
                              JOIN cregion ON sc.category = CONCAT('cregion:', cregion.cregion_id)
                              WHERE cregion.cregion_name = '" . e($name) . "' AND cregion.cregion_designer_type = 'yes' LIMIT 1");
            if ($made) $facts['designer_made'][] = $name;
        }
        $facts['frame_exists'] = (bool)db_value("SELECT id FROM shared_components
            WHERE category = '" . e(pg_classic_style_component_category($s['id'])) . "' LIMIT 1");
        // The theme the design will load, when the head loaded CSS after it.
        if (pg_legacy_style_css_after_theme($code)) {
            $active = pg_legacy_design_activated_themes();
            $theme = pg_legacy_design_theme(array(
                'own'         => db_item("SELECT files.id, files.name FROM style JOIN files ON style.theme_id = files.id
                                          WHERE style.style_id = '" . (int)$s['id'] . "' LIMIT 1"),
                'classic'     => true,
                'device'      => 'desktop',
                'desktop'     => $active['desktop'],
                'mobile'      => $active['mobile'],
                'rows'        => array(),
                'output_path' => OUTPUT_PATH,
            ));
            $facts['theme_after'] = $theme ? $theme['name'] : '';
        }
    }

    $holders = array();
    foreach ($s['locked'] as $p) $holders[$p['page_id']] = $p['holder'] !== '' ? $p['holder'] : lang('Unknown');
    foreach (array('own' => $s['own'], 'folder' => $s['folder']) as $source => $rows) {
        foreach ($rows as $p) {
            $own_regions = 0;
            if ($p['has_tree']) {
                $tree = json_decode((string)db_value("SELECT page_tree_json FROM page WHERE page_id = '" . (int)$p['page_id'] . "' LIMIT 1"), true);
                $own_regions = is_array($tree) ? pg_ltd_page_regions($tree, array())['own'] : 0;
            }
            $facts['pages'][] = array(
                'page_id'     => (int)$p['page_id'],
                'page_name'   => (string)$p['page_name'],
                'source'      => $source,
                'has_tree'    => (bool)$p['has_tree'],
                'holder'      => isset($holders[$p['page_id']]) ? $holders[$p['page_id']] : '',
                'own_regions' => $own_regions,
            );
        }
    }
    return $facts;
}

/**
 * The dry run: for each chosen set and each style chosen alone what a run
 * would write, read from the readiness report, the style's code through the
 * frame importer and the stored rows. A style ticked alone and inside a
 * chosen set is shown in its set. Writes nothing.
 *
 * @param array $style_ids
 * @param array $set_keys  pg_ltd_sets() keys
 * @return array  sets first (pg_ltd_plan_set()), then styles: array('kind'
 *                => 'style', 'id', 'name', 'state') + pg_ltd_plan_style()
 */
function pg_ltd_plan($style_ids, $set_keys = array())
{
    $want = array();
    foreach ((array)$style_ids as $id) {
        if ((int)$id > 0) $want[(int)$id] = true;
    }
    $want_sets = array();
    foreach ((array)$set_keys as $key) {
        if (is_string($key) && preg_match('/^[0-9a-f]{16}$/', $key)) $want_sets[$key] = true;
    }
    if (!$want && !$want_sets) return array();

    $report = pg_ltd_report();
    $by_id = array();
    foreach ($report as $s) $by_id[$s['id']] = $s;
    $converted = array_flip(pg_common_region_converted_ids());
    $out = array();
    foreach (($want_sets ? pg_ltd_sets($report)['sets'] : array()) as $set) {
        if (!isset($want_sets[$set['key']])) continue;
        $styles = array();
        $assets = array();
        foreach ($set['styles'] as $id) {
            $s = $by_id[$id];
            unset($want[$id]);
            $facts = pg_ltd_plan_facts($s, $converted);
            $styles[] = array('id' => $s['id'], 'name' => $s['name'], 'state' => $s['state'], 'facts' => $facts,
                              'body_classes' => $s['body_classes'], 'style_head' => $s['style_head'], 'mobile_pages' => $s['mobile_pages'],
                              'mobile' => $s['mobile'], 'social_position' => $s['social_position'], 'layout_type' => $s['layout_type']);
            $pages = array();
            foreach (array_merge($s['own'], $s['folder']) as $p) $pages[] = (int)$p['page_id'];
            $assets[] = array('id' => $s['id'], 'name' => $s['name'], 'pages' => $pages, 'css' => $facts['_css'], 'js' => $facts['_js']);
        }
        $out[] = pg_ltd_plan_set(array('key' => $set['key'], 'name' => $set['name'], 'theme' => $set['theme'],
                                       'collection' => $set['collection'], 'settings' => $set['settings'],
                                       'social_on' => defined('SOCIAL_NETWORKING') && SOCIAL_NETWORKING,
                                       'mobile_on' => defined('MOBILE') && MOBILE,
                                       'styles' => $styles, 'assets' => pg_ltd_set_assets($assets)));
    }
    foreach ($report as $s) {
        if (!isset($want[$s['id']])) continue;
        $out[] = array_merge(array('kind' => 'style', 'id' => $s['id'], 'name' => $s['name'], 'state' => $s['state']),
                             pg_ltd_plan_style(pg_ltd_plan_facts($s, $converted)));
    }
    return $out;
}

/**
 * The lines of one style's dry run: the components it makes, its frame,
 * its pages, widgets, the page regions inside its frame and the importer's
 * warnings. Alone the style itself becomes the design; in a set it stays
 * classic and its pages take the set's design, so the lines say how its
 * folder pages, body classes, Head code and mobile style fall instead.
 *
 * @param array $p       a pg_ltd_plan_style() result with 'name' (in a set,
 *                       a pg_ltd_plan_set() style)
 * @param bool  $in_set
 * @return string  <li> items
 */
function pg_ltd_plan_items($p, $in_set = false)
{
    $line = function ($text, $tone = '') {
        return '<li' . ($tone !== '' ? ' class="' . $tone . '"' : '') . '>' . h($text) . '</li>';
    };
    $names = function ($rows) {
        $out = array();
        foreach ($rows as $r) $out[] = (string)$r['page_name'];
        return implode(', ', $out);
    };
    if ($p['skip'] === 'refusal') {
        return $line(lang('Skipped: the visual designer does not open this style.'), 'fg-danger');
    }
    $items = '';
    foreach ($p['components'] as $c) {
        if ($c['exists']) continue;
        $items .= $line($c['kind'] === 'common'
            ? lang(array('string' => 'The common region "{var:1}" becomes a shared component.', 'vars' => array($c['name'])))
            : lang(array('string' => 'The designer region "{var:1}" becomes a shared component in the frame.', 'vars' => array($c['name']))));
    }
    $items .= $line($p['frame'] === 'make' ? lang('The style\'s frame is made a shared component.') : lang('The style\'s frame component made before is used.'));
    $by = array('tree' => array(), 'keep' => array(), 'regions' => array(), 'held' => array());
    foreach ($p['pages'] as $page) $by[$page['action']][] = $page;
    if ($by['tree']) {
        $items .= $line(lang(array('string' => 'Pages given a tree placing the frame ({var:1}): {var:2}', 'vars' => array(count($by['tree']), $names($by['tree'])))));
    }
    if ($by['regions']) {
        $items .= $line(lang(array('string' => 'Pages whose page regions move into their tree ({var:1}): {var:2}', 'vars' => array(count($by['regions']), $names($by['regions'])))));
    }
    if ($by['keep']) {
        $items .= $line(lang(array('string' => 'Pages that keep their tree ({var:1}): {var:2}', 'vars' => array(count($by['keep']), $names($by['keep'])))), 'fg-secondary');
    }
    foreach ($by['held'] as $page) {
        $items .= $line(lang(array('string' => 'Skipped: "{var:1}" is being edited by {var:2}.', 'vars' => array($page['page_name'], $page['holder']))), 'fg-warning');
    }
    foreach ($p['widgets'] as $w) {
        $items .= $line(lang(array('string' => 'The region "{var:1}" becomes a widget you design: {var:2}', 'vars' => array($w['name'], pg_ltd_widget_name($p['name'], $w['kind'])))));
    }
    if ($p['frame_page_regions'] > 0) {
        $items .= $line(lang('Page regions inside the frame stay: each page fills them from its own content.'), 'fg-secondary');
    }
    if (!$in_set) {
        $items .= $line(lang('The style becomes a visual design.'));
    } else {
        if ($p['folder_pages'] > 0) {
            $items .= $line(lang(array('string' => '{var:1} of these pages take the style from their folder: they get the design as their own style, and the folder keeps the classic style.', 'vars' => array($p['folder_pages']))), 'fg-secondary');
        }
        if ($p['body_classes'] !== '') {
            $items .= $line(lang(array('string' => 'Its body classes "{var:1}" go on its pages.', 'vars' => array($p['body_classes']))), 'fg-secondary');
        }
        if ($p['style_head']) {
            $items .= $line(lang('Its Head code was not printed on its classic pages and is not carried into the design.'), 'fg-secondary');
        }
        if ($p['mobile'] > 0) {
            $items .= $line(lang('A folder or page uses it as its mobile style: the classic style stays and keeps serving phones.'), 'fg-secondary');
        }
        foreach ($p['changes'] as $c) {
            $vars = array(pg_ltd_setting_label($c['setting'], $c['from']), pg_ltd_setting_label($c['setting'], $c['to']));
            $items .= $line($c['setting'] === 'layout_type'
                ? lang(array('string' => 'Layout type override: {var:1} on its classic pages, {var:2} in the design.', 'vars' => $vars))
                : lang(array('string' => 'Social Networking Position: {var:1} on its classic pages, {var:2} in the design.', 'vars' => $vars)), 'fg-warning');
        }
    }
    foreach ($p['warnings'] as $w) $items .= $line((string)$w, 'fg-warning');
    return $items;
}

/**
 * A setting's value as the custom style form names it.
 *
 * @param string $setting  'social_networking_position' or 'layout_type'
 * @param string $value
 * @return string
 */
function pg_ltd_setting_label($setting, $value)
{
    if ($setting === 'layout_type') {
        if ($value === 'system') return lang('System');
        if ($value === 'custom') return lang('Custom');
        return lang('No override (the page type layout)');
    }
    $labels = array('top_left' => lang('Top Left'), 'top_right' => lang('Top Right'), 'bottom_left' => lang('Bottom Left'),
                    'bottom_right' => lang('Bottom Right'), 'disabled' => lang('Disabled'));
    return isset($labels[$value]) ? $labels[$value] : $labels['bottom_left'];
}

/**
 * A chosen style or set of the dry run on the report screen: what a run
 * would write, what it skips and why.
 *
 * @param array $p  a pg_ltd_plan() entry
 * @return string
 */
function pg_ltd_plan_html($p)
{
    if (($p['kind'] ?? '') === 'set') return pg_ltd_plan_set_html($p);
    return '
        <div class="mb-5">
            <h3 class="h6 mb-1">' . h($p['name']) . ($p['skip'] === '' ? ' <span class="small fg-secondary fw-normal">' . h(lang(array('string' => '{var:1} writes', 'vars' => array(pg_format_number($p['writes'], 0))))) . '</span>' : '') . '</h3>
            <ul class="small mb-0 ps-12">' . pg_ltd_plan_items($p) . '</ul>
        </div>';
}

/**
 * A set of the dry run: the one design (its theme file, the stylesheets and
 * scripts on every page), then each style with its frame, its pages and the
 * rows kept to them.
 *
 * @param array $p  a pg_ltd_plan_set() result
 * @return string
 */
function pg_ltd_plan_set_html($p)
{
    $line = function ($text, $tone = '') {
        return '<li' . ($tone !== '' ? ' class="' . $tone . '"' : '') . '>' . h($text) . '</li>';
    };
    $every = array();
    $kept = array();
    foreach (array('css', 'js') as $kind) {
        foreach ((array)($p['assets'][$kind] ?? array()) as $a) {
            if (!empty($a['pages'])) $kept[] = $a;
            else $every[] = (string)($a['name'] ?? '');
        }
    }
    $items = '';
    if ($p['skip'] === 'empty') {
        $items .= $line(lang('Skipped: no style of this set can be moved.'), 'fg-danger');
    } elseif ($p['skip'] === 'no_pages') {
        $items .= $line(lang('Skipped: no page uses a style of this set any more. A set moved before keeps its classic styles.'), 'fg-secondary');
    } else {
        $items .= $line(lang(array('string' => 'One Legacy design "{var:1}" is made for these {var:2} styles: their pages take it as their style, and the classic styles stay as they are.', 'vars' => array($p['name'], count($p['styles'])))));
        $items .= $line($p['theme'] !== ''
            ? lang(array('string' => 'Theme file: {var:1}', 'vars' => array($p['theme'])))
            : lang('Theme file: none of its own, so the site\'s activated theme loads, as on the classic pages.'));
        if ($every) {
            $items .= $line(lang(array('string' => 'Stylesheets and scripts on every page ({var:1}): {var:2}', 'vars' => array(count($every), implode(', ', $every)))));
        }
        if ($p['social_on']) {
            $items .= $line(lang(array('string' => 'Social Networking Position: {var:1}', 'vars' => array(pg_ltd_setting_label('social_networking_position', (string)($p['settings']['social_networking_position'] ?? ''))))));
        }
        if ((string)($p['settings']['layout_type'] ?? '') !== '') {
            $items .= $line(lang(array('string' => 'Layout type override: {var:1}', 'vars' => array(pg_ltd_setting_label('layout_type', (string)$p['settings']['layout_type'])))));
        }
        if ($p['mobile_pages'] > 0) {
            $items .= $line(lang(array('string' => '{var:1} of these pages have a mobile style: on a phone they are still drawn with that style, not with the design.', 'vars' => array(pg_format_number($p['mobile_pages'], 0)))), 'fg-warning');
        }
    }
    $styles = '';
    // A set moved before writes nothing; its styles have nothing to show.
    foreach (($p['skip'] === 'no_pages' ? array() : $p['styles']) as $s) {
        $only = array();
        foreach ($kept as $a) {
            if (in_array($s['name'], (array)($a['styles'] ?? array()), true)) $only[] = (string)$a['name'];
        }
        // A style with something to look at opens by itself.
        $look = $s['skip'] !== '' || $s['changes'] || $s['warnings'];
        foreach ($s['pages'] as $page) {
            if ($page['action'] === 'held') $look = true;
        }
        $styles .= '
            <details class="mt-2"' . ($look ? ' open' : '') . '><summary><h4 class="d-inline fs-sm fw-semibold mb-0">' . h($s['name']) . '</h4>'
            . ($s['skip'] === '' ? ' <span class="small fg-secondary">' . h(lang(array('string' => '{var:1} writes', 'vars' => array(pg_format_number($s['writes'], 0))))) . '</span>' : '') . '</summary>
            <ul class="small mb-0 mt-1 ps-12">' . pg_ltd_plan_items($s, true)
            . ($only && $s['skip'] === '' ? $line(lang(array('string' => 'Only on its pages: {var:1}', 'vars' => array(implode(', ', $only))))) : '') . '</ul></details>';
    }
    return '
        <div class="mb-5">
            <h3 class="h6 mb-1">' . h($p['name']) . ' <span class="badge theme-secondary fw-normal">' . h(lang('Legacy')) . '</span>'
            . ($p['skip'] === '' ? ' <span class="small fg-secondary fw-normal">' . h(lang(array('string' => '{var:1} writes', 'vars' => array(pg_format_number($p['writes'], 0))))) . '</span>' : '') . '</h3>
            <ul class="small mb-0 ps-12">' . $items . '</ul>' . $styles . '
        </div>';
}

