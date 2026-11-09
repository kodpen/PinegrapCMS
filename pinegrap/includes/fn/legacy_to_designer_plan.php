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
 *                      region nodes of an existing tree)), 'warnings'
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
 * The dry run: for each chosen style what a run would write, read from the
 * readiness report, the style's code through the frame importer and the
 * stored rows. Writes nothing.
 *
 * @param array $style_ids
 * @return array  list of array('id', 'name', 'state') + pg_ltd_plan_style()
 */
function pg_ltd_plan($style_ids)
{
    $want = array();
    foreach ((array)$style_ids as $id) {
        if ((int)$id > 0) $want[(int)$id] = true;
    }
    if (!$want) return array();

    $converted = array_flip(pg_common_region_converted_ids());
    $out = array();
    foreach (pg_ltd_report() as $s) {
        if (!isset($want[$s['id']])) continue;
        $facts = array('refusal' => $s['refusal'], 'common' => array(), 'pages' => array(), 'warnings' => array(),
                       'designer_made' => array(), 'frame_exists' => false);
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
            foreach ($facts['frame']['designer'] as $name) {
                $made = db_value("SELECT sc.id FROM shared_components sc
                                  JOIN cregion ON sc.category = CONCAT('cregion:', cregion.cregion_id)
                                  WHERE cregion.cregion_name = '" . e($name) . "' AND cregion.cregion_designer_type = 'yes' LIMIT 1");
                if ($made) $facts['designer_made'][] = $name;
            }
            $facts['frame_exists'] = (bool)db_value("SELECT id FROM shared_components
                WHERE category = '" . e(pg_classic_style_component_category($s['id'])) . "' LIMIT 1");
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
        $out[] = array_merge(array('id' => $s['id'], 'name' => $s['name'], 'state' => $s['state']), pg_ltd_plan_style($facts));
    }
    return $out;
}

/**
 * One style of the dry run on the report screen: what a run would write,
 * what it skips and why.
 *
 * @param array $p  a pg_ltd_plan() entry
 * @return string
 */
function pg_ltd_plan_html($p)
{
    $line = function ($text, $tone = '') {
        return '<li' . ($tone !== '' ? ' class="' . $tone . '"' : '') . '>' . h($text) . '</li>';
    };
    $names = function ($rows) {
        $out = array();
        foreach ($rows as $r) $out[] = (string)$r['page_name'];
        return implode(', ', $out);
    };
    $items = '';
    if ($p['skip'] === 'refusal') {
        $items .= $line(lang('Skipped: the visual designer does not open this style.'), 'fg-danger');
    } else {
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
        $items .= $line(lang('The style becomes a visual design.'));
        foreach ($p['warnings'] as $w) $items .= $line((string)$w, 'fg-warning');
    }
    return '
        <div class="mb-5">
            <h3 class="h6 mb-1">' . h($p['name']) . ($p['skip'] === '' ? ' <span class="small fg-secondary fw-normal">' . h(lang(array('string' => '{var:1} writes', 'vars' => array(pg_format_number($p['writes'], 0))))) . '</span>' : '') . '</h3>
            <ul class="small mb-0 ps-12">' . $items . '</ul>
        </div>';
}
