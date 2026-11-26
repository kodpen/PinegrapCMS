<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The site's own library of design sections ("My library"): an item is a
 * subtree of a design kept as a start for another page or another design.
 *
 * The item model is pure: pg_library_item_from_subtree() turns a subtree,
 * the design's User Styles, its variables and the rows the subtree places
 * into an item; pg_library_item_into_design() turns an item into what one
 * design needs to insert it as its own copy (class rules added or renamed,
 * missing variables added, widget bindings the site lacks cleared and
 * marked). Neither reads the database: the callers hand in what the site
 * has.
 *
 * The store is files: one design file of the file manager per item
 * (`library-<name>.json`, files.design = 1), the way a theme is a design
 * CSS file. No table and no column.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

define('PG_LIBRARY_PREFIX', 'library-');
define('PG_LIBRARY_FORMAT', 'pinegrap-library-item');
define('PG_LIBRARY_VERSION', 1);
// One item file at most; a section with its rules is a few KB.
define('PG_LIBRARY_ITEM_MAX', 1048576);
// The longest name and group the item keeps.
define('PG_LIBRARY_NAME_MAX', 60);

// ── Rights ─────────────────────────────────────────────────────────────

/**
 * Whether a user may do one thing with the library.
 *
 *   use     see the items and insert a copy: every role that may add
 *           elements in the editor (designer access full or content)
 *   save, manage, export, import: the designer roles only (full)
 *
 * @param string $action
 * @param array  $user validate_user() row
 * @return bool
 */
function pg_library_may($action, $user)
{
    $access = pg_designer_access($user);
    if ($action === 'use') {
        return $access === PG_DESIGNER_ACCESS_FULL || $access === PG_DESIGNER_ACCESS_CONTENT;
    }
    if (in_array($action, array('save', 'manage', 'export', 'import'), true)) {
        return $access === PG_DESIGNER_ACCESS_FULL;
    }
    return false;
}

// ── CSS helpers (pure) ─────────────────────────────────────────────────

/**
 * Splits CSS text into its style rules, each with the text that writes it.
 * Comments go first (they may hold braces). A rule inside @media, @supports,
 * @layer or @container keeps that prelude chain; other at-blocks
 * (@keyframes, @font-face) are skipped.
 *
 * @return array list of array('media' => list of preludes, 'selector', 'body')
 */
function pg_library_css_rules($css)
{
    $css = preg_replace('#/\*.*?\*/#s', '', (string)$css);
    $rules = array();
    $pos = 0;
    _pg_library_css_block($css, $pos, strlen($css), array(), $rules, 0);
    return $rules;
}

function _pg_library_css_block($css, &$pos, $end, $media, &$rules, $depth)
{
    while ($pos < $end) {
        $start = $pos;
        $stop = _pg_library_css_scan($css, $pos, $end, '{;}');
        if ($stop === null) {
            $pos = $end;
            return;
        }
        $char = $css[$stop];
        $prelude = trim(substr($css, $start, $stop - $start));
        $pos = $stop + 1;
        if ($char === '}') return;
        if ($char === ';') continue;
        if ($prelude !== '' && $prelude[0] === '@') {
            if ($depth < 8 && preg_match('/^@(media|supports|layer|container)\b/i', $prelude)) {
                $inner = $media;
                $inner[] = preg_replace('/\s+/', ' ', $prelude);
                _pg_library_css_block($css, $pos, $end, $inner, $rules, $depth + 1);
            } else {
                _pg_library_css_skip($css, $pos, $end);
            }
            continue;
        }
        $body_start = $pos;
        _pg_library_css_skip($css, $pos, $end);
        $body = substr($css, $body_start, max(0, $pos - 1 - $body_start));
        if ($prelude === '') continue;
        $rules[] = array('media' => $media, 'selector' => preg_replace('/\s+/', ' ', $prelude), 'body' => trim($body));
    }
}

// The offset of the first of $stops outside strings and brackets, or null.
function _pg_library_css_scan($css, $pos, $end, $stops)
{
    $depth = 0;
    $quote = '';
    for ($i = $pos; $i < $end; $i++) {
        $c = $css[$i];
        if ($quote !== '') {
            if ($c === '\\') $i++;
            elseif ($c === $quote) $quote = '';
        } elseif ($c === '"' || $c === "'") {
            $quote = $c;
        } elseif ($c === '\\') {
            $i++;
        } elseif ($c === '(' || $c === '[') {
            $depth++;
        } elseif ($c === ')' || $c === ']') {
            $depth--;
        } elseif ($depth <= 0 && strpos($stops, $c) !== false) {
            return $i;
        }
    }
    return null;
}

// Moves $pos after the brace that closes the block it is in.
function _pg_library_css_skip($css, &$pos, $end)
{
    $level = 1;
    while ($pos < $end && $level > 0) {
        $stop = _pg_library_css_scan($css, $pos, $end, '{}');
        if ($stop === null) {
            $pos = $end;
            return;
        }
        $level += ($css[$stop] === '{') ? 1 : -1;
        $pos = $stop + 1;
    }
}

// The text of one rule as the item and the design write it.
function pg_library_css_rule_text($rule)
{
    $body = preg_replace('/\s*;\s*/', ";\n    ", trim((string)$rule['body']));
    $body = rtrim($body);
    $text = $rule['selector'] . " {\n    " . $body . "\n}";
    $media = isset($rule['media']) ? (array)$rule['media'] : array();
    for ($i = count($media) - 1; $i >= 0; $i--) {
        $text = $media[$i] . " {\n" . preg_replace('/^/m', '    ', $text) . "\n}";
    }
    return $text;
}

// A rule reduced to what it does, for telling two rules of one name apart.
function _pg_library_rule_key($rule)
{
    $decls = array();
    foreach (explode(';', (string)$rule['body']) as $d) {
        $d = trim(preg_replace('/\s+/', ' ', $d));
        if ($d === '') continue;
        $d = preg_replace('/\s*:\s*/', ':', $d, 1);
        $decls[] = $d;
    }
    return implode(' & ', (array)$rule['media']) . '|' . $rule['selector'] . '{' . implode(';', $decls) . '}';
}

// The class names a selector list names.
function pg_library_selector_classes($selector)
{
    $out = array();
    // Attribute selectors and :not() arguments name classes too; strings do not.
    $s = preg_replace('/"[^"]*"|\'[^\']*\'/', '', (string)$selector);
    if (preg_match_all('/\.(-?[_a-zA-Z][_a-zA-Z0-9-]*)/', $s, $m)) {
        foreach ($m[1] as $c) $out[$c] = true;
    }
    return array_keys($out);
}

// Writes $to for the class $from in a selector list.
function _pg_library_selector_rename($selector, $from, $to)
{
    return preg_replace('/\.' . preg_quote($from, '/') . '(?![_a-zA-Z0-9-])/', '.' . $to, $selector);
}

/**
 * The custom properties a CSS text defines, outside @media: name => value,
 * the first definition of each. A value that could end its rule early is
 * left out.
 */
function pg_library_css_var_defs($css)
{
    $defs = array();
    foreach (pg_library_css_rules($css) as $rule) {
        if (!empty($rule['media'])) continue;
        foreach (explode(';', $rule['body']) as $decl) {
            if (!preg_match('/^\s*(--[_a-zA-Z0-9-]+)\s*:(.*)$/s', $decl, $m)) continue;
            $value = trim($m[2]);
            if ($value === '' || !pg_library_var_value_ok($value) || isset($defs[$m[1]])) continue;
            $defs[$m[1]] = $value;
        }
    }
    return $defs;
}

function pg_library_var_value_ok($value)
{
    return is_string($value) && $value !== '' && strlen($value) <= 500 && strpbrk($value, '{};<>') === false;
}

// The custom properties a text reads with var(); Bootstrap's own (--bs-*)
// are on every design and are left out.
function pg_library_var_uses($text, &$out)
{
    if (!is_string($text) || strpos($text, 'var(') === false) return;
    if (preg_match_all('/var\(\s*(--[_a-zA-Z0-9-]+)/', $text, $m)) {
        foreach ($m[1] as $name) {
            if (strncmp($name, '--bs-', 5) === 0) continue;
            $out[$name] = true;
        }
    }
}

// ── Trees (pure) ───────────────────────────────────────────────────────

// Calls $fn(&$node) on every node of a tree, the root first.
function _pg_library_walk(&$node, $fn, $depth = 0)
{
    if (!is_array($node) || $depth > 80) return;
    $fn($node);
    if (isset($node['children']) && is_array($node['children'])) {
        foreach ($node['children'] as $i => $child) {
            _pg_library_walk($node['children'][$i], $fn, $depth + 1);
        }
    }
}

// Every string of a props array, nested ones included.
function _pg_library_prop_strings($value, &$out, $depth = 0)
{
    if ($depth > 20) return;
    if (is_string($value)) {
        $out[] = $value;
    } elseif (is_array($value)) {
        foreach ($value as $v) _pg_library_prop_strings($v, $out, $depth + 1);
    }
}

function _pg_library_node_classes($node)
{
    if (!isset($node['props']['cssClass']) || !is_string($node['props']['cssClass'])) return array();
    return preg_split('/\s+/', trim($node['props']['cssClass']), -1, PREG_SPLIT_NO_EMPTY);
}

/**
 * A node made portable: the editor's own state (_id, _expanded,
 * _sharedDirty) and the team's notes go; a shared_ref placing a row of
 * $rows points at the item's own entry for it (props.libraryWidget or
 * props.libraryShared); one placing a row that is not given is left out and
 * counted in $dropped. Returns the node, or null when it is left out.
 */
function _pg_library_portable($node, $rows, &$item, &$dropped, $seen, $depth = 0)
{
    if (!is_array($node) || $depth > 80) return null;
    unset($node['_id'], $node['_expanded'], $node['_sharedDirty']);

    if (isset($node['type']) && $node['type'] === 'shared_ref') {
        $sid = isset($node['props']['sharedId']) ? (int)$node['props']['sharedId'] : 0;
        if ($sid <= 0 || !isset($rows[$sid]) || isset($seen[$sid])) {
            $dropped++;
            return null;
        }
        $row = $rows[$sid];
        $is_widget = isset($row['config']) && is_array($row['config']);
        $key = ($is_widget ? 'w' : 's') . $sid;
        $bucket = $is_widget ? 'widgets' : 'shared';
        if (!isset($item[$bucket][$key])) {
            // Taken before the walk into its tree, so a row placed twice is
            // carried once.
            $item[$bucket][$key] = array();
            $inner_seen = $seen;
            $inner_seen[$sid] = true;
            $tree = _pg_library_portable(isset($row['tree']) ? $row['tree'] : array('type' => 'div', 'children' => array()),
                $rows, $item, $dropped, $inner_seen, $depth + 1);
            $entry = array('name' => preg_replace('/\s*\[\d+\]$/', '', (string)(isset($row['name']) ? $row['name'] : '')),
                           'sid' => $sid, 'tree' => $tree);
            if ($is_widget) {
                $config = $row['config'];
                unset($config['template_origin']);
                $entry['kind'] = isset($config['regionType']) ? (string)$config['regionType'] : '';
                $entry['config'] = $config;
            }
            $item[$bucket][$key] = $entry;
        }
        $props = array($is_widget ? 'libraryWidget' : 'libraryShared' => $key);
        if (isset($node['props']['sharedName'])) $props['sharedName'] = (string)$node['props']['sharedName'];
        $node['props'] = $props;
        $node['children'] = array();
        return $node;
    }

    if (isset($node['props']) && is_array($node['props'])) {
        unset($node['props']['_notes'], $node['props']['_notes_by'], $node['props']['_notes_at']);
    }
    if (isset($node['children']) && is_array($node['children'])) {
        $children = array();
        foreach ($node['children'] as $child) {
            $child = _pg_library_portable($child, $rows, $item, $dropped, $seen, $depth + 1);
            if ($child !== null) $children[] = $child;
        }
        $node['children'] = $children;
    }
    return $node;
}

// The kind of record a settings key points at: page (a page, which is also
// what a form or a collection is), group (a contact group), folder,
// product_group (a catalog's group, its root among them), calendar; '' for
// any other key.
function pg_library_ref_kind($key)
{
    $key = (string)$key;
    if (strpos($key, 'product_group_id') !== false || strpos($key, 'root_group_id') !== false) return 'product_group';
    if (strpos($key, 'calendar_id') !== false) return 'calendar';
    if (strpos($key, 'contact_group_id') !== false || $key === 'group_id') return 'group';
    if (strpos($key, 'folder_id') !== false) return 'folder';
    if (strpos($key, 'page_id') !== false || strpos($key, 'form_id') !== false) return 'page';
    return '';
}

/**
 * Calls $fn($kind, $id, $raw) for every record id a settings array or a
 * node's props name; the value is replaced by what $fn returns. A node's
 * form reference field (_cf.ref) points at a page. A menu is named, not
 * numbered (menu_name): $fn('menu', 0, $name).
 */
function _pg_library_map_refs($arr, $fn, $parent_key = '', $depth = 0)
{
    if (!is_array($arr) || $depth > 20) return $arr;
    foreach ($arr as $k => $v) {
        $name = is_int($k) ? (string)$parent_key : (string)$k;
        if ($name === '_bindings') continue;
        if (is_array($v)) {
            $arr[$k] = _pg_library_map_refs($v, $fn, $name, $depth + 1);
            continue;
        }
        if ($name === 'menu_name' && is_string($v) && trim($v) !== '') {
            $arr[$k] = $fn('menu', 0, $v);
            continue;
        }
        $kind = ($parent_key === '_cf' && $k === 'ref') ? 'page' : pg_library_ref_kind($name);
        if ($kind === '' || !(is_int($v) || (is_string($v) && ctype_digit($v))) || (int)$v <= 0) continue;
        $arr[$k] = $fn($kind, (int)$v, $v);
    }
    return $arr;
}

// Calls _pg_library_walk() with $fn on every tree an item holds: the main
// tree, then the trees of its widgets and shared components.
function _pg_library_each_tree(&$item, $fn)
{
    if (isset($item['tree']) && is_array($item['tree'])) _pg_library_walk($item['tree'], $fn);
    foreach (array('widgets', 'shared') as $bucket) {
        if (empty($item[$bucket]) || !is_array($item[$bucket])) continue;
        foreach (array_keys($item[$bucket]) as $key) {
            if (isset($item[$bucket][$key]['tree']) && is_array($item[$bucket][$key]['tree'])) {
                _pg_library_walk($item[$bucket][$key]['tree'], $fn);
            }
        }
    }
}

// ── The item (pure) ────────────────────────────────────────────────────

/**
 * Trims a name or a group: one line, at most PG_LIBRARY_NAME_MAX characters.
 */
function pg_library_clean_label($text)
{
    $text = trim(preg_replace('/\s+/u', ' ', (string)$text));
    if (function_exists('mb_substr')) return mb_substr($text, 0, PG_LIBRARY_NAME_MAX, 'UTF-8');
    return substr($text, 0, PG_LIBRARY_NAME_MAX);
}

/**
 * A subtree of a design made into a library item.
 *
 * $node       the subtree (a section, row, card or group)
 * $meta       'name', 'group'
 * $design     'css'  the design's User Styles
 *             'vars' name => value of the custom properties the design
 *                    defines (its User Styles, look, palette, variables)
 *             'framework' set of class => true that belong to the CSS
 *                    framework (Bootstrap): never a rule name of the item
 * $site       'rows'   sid => array('name', 'tree', 'config' (null for a
 *                      plain shared component)) for every row the subtree
 *                      places, and the rows those place
 *             'pages', 'groups', 'folders'  id => name of the site's records
 *                      (pages hold the forms and collections)
 *
 * Returns the item: format, version, name, group, tree, rules (list of
 * array('selector', 'media', 'body')), variables (name => value), widgets
 * and shared (key => entry), refs (kind => id => name), dropped (placements
 * of rows not given).
 */
function pg_library_item_from_subtree($node, $meta, $design, $site)
{
    $rows = isset($site['rows']) && is_array($site['rows']) ? $site['rows'] : array();
    $framework = isset($design['framework']) && is_array($design['framework']) ? $design['framework'] : array();
    $item = array(
        'format'    => PG_LIBRARY_FORMAT,
        'version'   => PG_LIBRARY_VERSION,
        'name'      => pg_library_clean_label(isset($meta['name']) ? $meta['name'] : ''),
        'group'     => pg_library_clean_label(isset($meta['group']) ? $meta['group'] : ''),
        'tree'      => null,
        'rules'     => array(),
        'variables' => array(),
        'widgets'   => array(),
        'shared'    => array(),
        'refs'      => array(),
    );
    $dropped = 0;
    $item['tree'] = _pg_library_portable($node, $rows, $item, $dropped, array());
    $item['dropped'] = $dropped;

    // The item's classes, and the strings that may read a variable.
    $classes = array();
    $strings = array();
    _pg_library_each_tree($item, function (&$n) use (&$classes, &$strings) {
        foreach (_pg_library_node_classes($n) as $c) $classes[$c] = true;
        if (isset($n['props'])) _pg_library_prop_strings($n['props'], $strings);
    });

    // The rules of the design's own stylesheet that name one of its own
    // classes (not a framework class).
    $own = array();
    foreach (array_keys($classes) as $c) {
        if (!isset($framework[$c])) $own[$c] = true;
    }
    foreach (pg_library_css_rules(isset($design['css']) ? $design['css'] : '') as $rule) {
        foreach (pg_library_selector_classes($rule['selector']) as $c) {
            if (isset($own[$c])) {
                $item['rules'][] = $rule;
                $strings[] = $rule['body'];
                break;
            }
        }
    }

    // The variables it reads, and the ones their values read in turn.
    $uses = array();
    foreach ($strings as $s) pg_library_var_uses($s, $uses);
    $vars = isset($design['vars']) && is_array($design['vars']) ? $design['vars'] : array();
    $queue = array_keys($uses);
    while ($queue) {
        $name = array_shift($queue);
        if (isset($item['variables'][$name])) continue;
        if (!isset($vars[$name]) || !pg_library_var_value_ok($vars[$name])) continue;
        $item['variables'][$name] = $vars[$name];
        $more = array();
        pg_library_var_uses($vars[$name], $more);
        foreach (array_keys($more) as $m) $queue[] = $m;
    }
    ksort($item['variables']);

    // The records the widgets and nodes point at, by name, so another site
    // can find its own of the same name.
    $names = array(
        'page'   => isset($site['pages']) ? (array)$site['pages'] : array(),
        'group'  => isset($site['groups']) ? (array)$site['groups'] : array(),
        'folder' => isset($site['folders']) ? (array)$site['folders'] : array(),
        'product_group' => isset($site['product_groups']) ? (array)$site['product_groups'] : array(),
        'calendar' => isset($site['calendars']) ? (array)$site['calendars'] : array(),
    );
    $refs = array();
    $collect = function ($kind, $id, $raw) use (&$refs, $names) {
        if ($kind === 'menu') return $raw;
        $refs[$kind][$id] = isset($names[$kind][$id]) ? (string)$names[$kind][$id] : '';
        return $raw;
    };
    foreach ($item['widgets'] as $key => $w) {
        _pg_library_map_refs($w['config'], $collect);
    }
    _pg_library_each_tree($item, function (&$n) use ($collect) {
        if (isset($n['props'])) _pg_library_map_refs($n['props'], $collect);
    });
    foreach ($refs as $kind => $list) ksort($refs[$kind]);
    $item['refs'] = $refs;
    return $item;
}

/**
 * What one design needs to insert an item as its own copy.
 *
 * $design  'css'  its User Styles
 *          'vars' name => value it defines (User Styles, look, palette,
 *                 variables)
 *          'framework' set of framework classes, as for the item
 * $site    'pages', 'groups', 'folders'  id => name of the records it has
 *          'shared' sid => name of its shared components
 *          'menus' id => name of its menus (a menu widget names its menu)
 *          'path', 'host', 'has_file'  the site's path and host and a
 *                 callable($name) telling whether a file of that name is
 *                 in its file manager; without it no picture is counted
 *
 * The design's own wins: a class rule it has the same stays as it is; one
 * of the same name that differs stays too, and the item's class gets a new
 * name ('renamed'); a missing rule is added. A variable the design defines
 * keeps the design's value, so the copy takes this design's look; a missing
 * one is added with the item's value. A record reference keeps its id when
 * the site has that record under the same name, takes the id of the one
 * record of that name otherwise, and is cleared ('') when there is none: the
 * widget's or node's settings are cleared and its placement is marked
 * (props._libraryUnbound, the kinds cleared).
 *
 * Returns array(
 *   'tree'     the tree to insert (shared_ref nodes carry libraryWidget /
 *              libraryShared keys, or sharedId for a component kept),
 *   'widgets'  key => array('kind', 'name', 'config', 'tree', 'cleared')
 *              rows the caller makes as new widgets,
 *   'shared'   key => array('name', 'tree') rows the caller makes, the
 *              ones the site does not have,
 *   'css'      text to append to the User Styles ('' when nothing),
 *   'rules_added', 'renamed' (old class => new), 'vars_added' (name => value),
 *   'cleared'  number of references cleared,
 *   'pictures_missing'  number of the copy's distinct picture addresses
 *              that point at this site's own files and have no file there;
 *              the addresses themselves stay as written)
 */
function pg_library_item_into_design($item, $design, $site)
{
    $framework = isset($design['framework']) && is_array($design['framework']) ? $design['framework'] : array();
    $out = array('tree' => null, 'widgets' => array(), 'shared' => array(), 'css' => '',
                 'rules_added' => 0, 'renamed' => array(), 'vars_added' => array(), 'cleared' => 0,
                 'pictures_missing' => 0);

    // ── Rules: by the item's own class names.
    $design_rules = pg_library_css_rules(isset($design['css']) ? $design['css'] : '');
    $design_classes = array();
    $design_by_class = array();
    foreach ($design_rules as $rule) {
        foreach (pg_library_selector_classes($rule['selector']) as $c) {
            $design_classes[$c] = true;
            $design_by_class[$c][] = _pg_library_rule_key($rule);
        }
    }
    $item_rules = isset($item['rules']) && is_array($item['rules']) ? array_values($item['rules']) : array();
    $item_by_class = array();
    $item_classes = array();
    foreach ($item_rules as $i => $rule) {
        foreach (pg_library_selector_classes($rule['selector']) as $c) {
            $item_classes[$c] = true;
            if (isset($framework[$c])) continue;
            $item_by_class[$c][] = _pg_library_rule_key($rule);
        }
    }
    $scan = $item;
    _pg_library_each_tree($scan, function (&$n) use (&$item_classes) {
        foreach (_pg_library_node_classes($n) as $c) $item_classes[$c] = true;
    });

    // same: the design writes the class exactly as the item does
    $state = array();
    foreach ($item_by_class as $c => $keys) {
        if (!isset($design_by_class[$c])) {
            $state[$c] = 'missing';
            continue;
        }
        $a = array_values(array_unique($keys));
        $b = array_values(array_unique($design_by_class[$c]));
        sort($a);
        sort($b);
        if ($a === $b) {
            $state[$c] = 'same';
            continue;
        }
        $state[$c] = 'renamed';
        for ($n = 2; ; $n++) {
            $new = $c . '-' . $n;
            if (!isset($design_classes[$new]) && !isset($item_classes[$new]) && !in_array($new, $out['renamed'], true)) break;
        }
        $out['renamed'][$c] = $new;
    }

    $added = array();
    foreach ($item_rules as $rule) {
        $add = false;
        foreach (pg_library_selector_classes($rule['selector']) as $c) {
            if (isset($state[$c]) && $state[$c] !== 'same') $add = true;
        }
        if (!$add) continue;
        foreach ($out['renamed'] as $from => $to) {
            $rule['selector'] = _pg_library_selector_rename($rule['selector'], $from, $to);
        }
        $added[] = pg_library_css_rule_text($rule);
    }
    $out['rules_added'] = count($added);

    // ── Variables: the design's value wins.
    $have = isset($design['vars']) && is_array($design['vars']) ? $design['vars'] : array();
    foreach ((isset($item['variables']) ? (array)$item['variables'] : array()) as $name => $value) {
        if (!preg_match('/^--[_a-zA-Z0-9-]+$/', (string)$name) || !pg_library_var_value_ok($value)) continue;
        if (isset($have[$name])) continue;
        $out['vars_added'][$name] = $value;
    }
    $css = '';
    if ($out['vars_added']) {
        $css .= ":root {\n";
        foreach ($out['vars_added'] as $name => $value) $css .= '    ' . $name . ': ' . $value . ";\n";
        $css .= "}\n";
    }
    if ($added) $css .= implode("\n", $added) . "\n";
    $out['css'] = $css;

    // ── Record references.
    $refs = isset($item['refs']) && is_array($item['refs']) ? $item['refs'] : array();
    $site_names = array(
        'page'   => isset($site['pages']) ? (array)$site['pages'] : array(),
        'group'  => isset($site['groups']) ? (array)$site['groups'] : array(),
        'folder' => isset($site['folders']) ? (array)$site['folders'] : array(),
        'product_group' => isset($site['product_groups']) ? (array)$site['product_groups'] : array(),
        'calendar' => isset($site['calendars']) ? (array)$site['calendars'] : array(),
    );
    // A menu is kept by its name when the site has one of that name (the
    // menu widget looks it up by name, case aside).
    $menus = array();
    foreach ((isset($site['menus']) ? (array)$site['menus'] : array()) as $menu) {
        $menus[strtolower(trim((string)$menu))] = true;
    }
    $resolve = function ($kind, $id) use ($refs, $site_names) {
        $name = isset($refs[$kind][$id]) ? (string)$refs[$kind][$id] : '';
        $have = isset($site_names[$kind]) ? $site_names[$kind] : array();
        if (isset($have[$id]) && (string)$have[$id] === $name) return $id;
        if ($name === '') return 0;
        $found = array_keys($have, $name, true);
        if (!$found) {
            foreach ($have as $hid => $hname) {
                if ((string)$hname === $name) $found[] = $hid;
            }
        }
        return count($found) === 1 ? (int)$found[0] : 0;
    };
    $cleared_kinds = array();
    $map = function ($kind, $id, $raw) use ($resolve, $menus, &$cleared_kinds) {
        if ($kind === 'menu') {
            if (isset($menus[strtolower(trim((string)$raw))])) return $raw;
            $cleared_kinds['menu'] = true;
            return '';
        }
        $new = $resolve($kind, $id);
        if ($new <= 0) {
            $cleared_kinds[$kind] = true;
            return '';
        }
        return is_int($raw) ? $new : (string)$new;
    };

    $copy = $item;
    $rename = $out['renamed'];
    _pg_library_each_tree($copy, function (&$n) use ($rename, $map, &$cleared_kinds, &$out) {
        if ($rename && isset($n['props']['cssClass']) && is_string($n['props']['cssClass'])) {
            $list = _pg_library_node_classes($n);
            foreach ($list as $i => $c) {
                if (isset($rename[$c])) $list[$i] = $rename[$c];
            }
            $n['props']['cssClass'] = implode(' ', $list);
        }
        if (!isset($n['props']) || !is_array($n['props'])) return;
        $cleared_kinds = array();
        $n['props'] = _pg_library_map_refs($n['props'], $map);
        if ($cleared_kinds) {
            $n['props']['_libraryUnbound'] = array_keys($cleared_kinds);
            $out['cleared'] += 1;
        }
    });

    // A widget is always made anew (the copy is the page's own); a shared
    // component the site has (same row, same name) is placed again.
    $site_shared = isset($site['shared']) ? (array)$site['shared'] : array();
    $kept_shared = array();
    foreach ((isset($copy['shared']) ? (array)$copy['shared'] : array()) as $key => $entry) {
        $sid = isset($entry['sid']) ? (int)$entry['sid'] : 0;
        $name = isset($entry['name']) ? (string)$entry['name'] : '';
        if ($sid > 0 && isset($site_shared[$sid]) && preg_replace('/\s*\[\d+\]$/', '', (string)$site_shared[$sid]) === $name) {
            $kept_shared[$key] = $sid;
        }
    }
    $widget_cleared = array();
    foreach ((isset($copy['widgets']) ? (array)$copy['widgets'] : array()) as $key => $entry) {
        $cleared_kinds = array();
        $copy['widgets'][$key]['config'] = _pg_library_map_refs(isset($entry['config']) ? (array)$entry['config'] : array(), $map);
        $widget_cleared[$key] = array_keys($cleared_kinds);
        if ($cleared_kinds) $out['cleared'] += 1;
    }

    // A placement of a widget whose settings were cleared carries the mark;
    // a placement of a component the site has points at it again. A row id
    // of a site written into the item's tree (a file made by hand: an export
    // writes the item's own keys) is not this site's row: it is cleared and
    // marked.
    _pg_library_each_tree($copy, function (&$n) use ($widget_cleared, $kept_shared, &$out) {
        if (!isset($n['type']) || $n['type'] !== 'shared_ref') return;
        if (isset($n['props']) && is_array($n['props']) && array_key_exists('sharedId', $n['props'])) {
            unset($n['props']['sharedId'], $n['props']['sharedName']);
            $n['props']['_libraryUnbound'] = array('shared');
            $out['cleared'] += 1;
        }
        $w = isset($n['props']['libraryWidget']) ? (string)$n['props']['libraryWidget'] : '';
        if ($w !== '' && !empty($widget_cleared[$w])) {
            $n['props']['_libraryUnbound'] = $widget_cleared[$w];
        }
        $s = isset($n['props']['libraryShared']) ? (string)$n['props']['libraryShared'] : '';
        if ($s !== '' && isset($kept_shared[$s])) {
            $n['props']['sharedId'] = $kept_shared[$s];
            unset($n['props']['libraryShared']);
        }
    });

    foreach ((isset($copy['shared']) ? (array)$copy['shared'] : array()) as $key => $entry) {
        if (isset($kept_shared[$key])) continue;
        $out['shared'][$key] = array('name' => isset($entry['name']) ? (string)$entry['name'] : '',
                                     'tree' => isset($entry['tree']) ? $entry['tree'] : null);
    }
    foreach ((isset($copy['widgets']) ? (array)$copy['widgets'] : array()) as $key => $entry) {
        $out['widgets'][$key] = array(
            'kind'    => isset($entry['kind']) ? (string)$entry['kind'] : '',
            'name'    => isset($entry['name']) ? (string)$entry['name'] : '',
            'config'  => $entry['config'],
            'tree'    => isset($entry['tree']) ? $entry['tree'] : null,
            'cleared' => $widget_cleared[$key],
        );
    }
    $out['tree'] = $copy['tree'];

    // ── Pictures: what is inserted (the tree, new rows, added rules).
    if (isset($site['has_file']) && is_callable($site['has_file'])) {
        $urls = array();
        $trees = array($out['tree']);
        foreach (array_merge($out['widgets'], $out['shared']) as $entry) {
            if (isset($entry['tree']) && is_array($entry['tree'])) $trees[] = $entry['tree'];
            if (isset($entry['config'])) _pg_library_string_urls($entry['config'], '', $urls);
        }
        foreach ($trees as $tree) {
            if (is_array($tree)) _pg_library_tree_urls($tree, $urls);
        }
        _pg_library_text_urls($out['css'], '', $urls);
        $out['pictures_missing'] = pg_library_missing_pictures($urls, isset($site['path']) ? $site['path'] : '/',
            $site['has_file'], isset($site['host']) ? $site['host'] : '');
    }
    return $out;
}

// The addresses one text holds: the text itself under an address key
// (each srcset candidate), the address attributes of its tags, and its
// style url()s. Appended to $urls.
function _pg_library_text_urls($text, $key, &$urls)
{
    if (!is_string($text) || $text === '') return;
    if ($key !== '' && pg_library_address_key($key)) {
        foreach (_pg_library_value_urls($key, $text) as $u) $urls[] = $u;
        return;
    }
    if (strpos($text, '<') !== false) {
        foreach (_pg_library_markup_tags($text) as $tag) {
            if (!preg_match_all('/[\s\/"\']([a-zA-Z:-]+)\s*=\s*("([^"]*)"|\'([^\']*)\'|([^\s>]+))/', $tag, $attrs, PREG_SET_ORDER)) continue;
            foreach ($attrs as $a) {
                if (!pg_library_address_key($a[1])) continue;
                $value = (isset($a[5]) && $a[5] !== '') ? $a[5] : ((isset($a[4]) && $a[4] !== '') ? $a[4] : $a[3]);
                foreach (_pg_library_value_urls($a[1], _pg_library_entity_decode($value)) as $u) $urls[] = $u;
            }
        }
    }
    if (stripos($text, 'url(') !== false
        && preg_match_all('/url\(\s*(?:"([^"]*)"|\'([^\']*)\'|([^)]*))/i', _pg_library_css_unescape($text), $m, PREG_SET_ORDER)) {
        foreach ($m as $one) {
            $urls[] = isset($one[3]) && $one[3] !== '' ? $one[3] : (isset($one[2]) && $one[2] !== '' ? $one[2] : $one[1]);
        }
    }
}

// The addresses of the strings of an array, each read with the nearest
// named key above it.
function _pg_library_string_urls($value, $key, &$urls, $depth = 0)
{
    if ($depth > 40) return;
    if (is_string($value)) {
        _pg_library_text_urls($value, $key, $urls);
        return;
    }
    if (!is_array($value)) return;
    foreach ($value as $k => $v) _pg_library_string_urls($v, is_int($k) ? $key : (string)$k, $urls, $depth + 1);
}

// The addresses of a tree's props, an _attrs value read under its name.
function _pg_library_tree_urls($tree, &$urls)
{
    _pg_library_walk($tree, function (&$n) use (&$urls) {
        if (!isset($n['props']) || !is_array($n['props'])) return;
        $props = $n['props'];
        if (isset($props['_attrs']) && is_array($props['_attrs'])) {
            foreach ($props['_attrs'] as $a) {
                if (is_array($a) && isset($a['name'], $a['value'])) _pg_library_text_urls((string)$a['value'], strtolower(trim((string)$a['name'])), $urls);
            }
            unset($props['_attrs']);
        }
        _pg_library_string_urls($props, '', $urls);
    });
}

/**
 * How many of the distinct picture addresses point at this site's own
 * files and have no file there. A file of the file manager is served at
 * the site's path and its name, so an address counts when it is that path
 * (or a name relative to it) and a picture's name, on no host or on
 * $host. Another host's address, a data: address, a variable or a
 * placeholder ({{token}}, var()) and an address under a folder cannot be
 * checked and are not counted. $has_file($name) tells whether the site
 * has a file of that name. Pure.
 */
function pg_library_missing_pictures($urls, $path, $has_file, $host = '')
{
    $path = '/' . trim((string)$path, '/');
    if ($path !== '/') $path .= '/';
    $host = strtolower((string)preg_replace('/:\d+$/', '', (string)$host));
    $names = array();
    foreach ((array)$urls as $url) {
        $u = trim(_pg_library_entity_decode((string)$url));
        if ($u === '' || preg_match('/[{}$]|var\(/i', $u)) continue;
        $u = (string)preg_replace('/[?#].*$/s', '', $u);
        if (preg_match('#^(?:(https?):)?//([^/]*)(/.*)?$#i', $u, $m)) {
            $h = strtolower((string)preg_replace('/:\d+$/', '', $m[2]));
            if ($host === '' || $h !== $host) continue;
            $u = isset($m[3]) ? $m[3] : '/';
        } elseif (preg_match('/^[a-zA-Z][a-zA-Z0-9+.\-]*:/', $u)) {
            continue;
        }
        if ($u[0] === '/') {
            if (strpos($u, $path) !== 0) continue;
            $u = substr($u, strlen($path));
        } elseif (strpos($u, './') === 0) {
            $u = substr($u, 2);
        }
        $name = rawurldecode($u);
        if ($name === '' || strpos($name, '/') !== false || strpos($name, '\\') !== false) continue;
        if (!preg_match('/\.(jpe?g|png|gif|webp|avif|svg|bmp|ico|tiff?)$/i', $name)) continue;
        $names[$name] = true;
    }
    $missing = 0;
    foreach (array_keys($names) as $name) {
        if (!call_user_func($has_file, (string)$name)) $missing++;
    }
    return $missing;
}

/**
 * The groups in use, sorted, for the group field's offers.
 */
function pg_library_groups($items)
{
    $groups = array();
    foreach ((array)$items as $item) {
        $g = isset($item['group']) ? (string)$item['group'] : '';
        if ($g !== '') $groups[$g] = true;
    }
    $groups = array_keys($groups);
    natcasesort($groups);
    return array_values($groups);
}

/**
 * The items in the order the Blocks list and "Manage library" draw them:
 * the ones with no group first, then each group where its first item
 * stands, every item keeping its place within its group.
 */
function pg_library_drawn_order($items)
{
    $loose = array();
    $groups = array();
    foreach ((array)$items as $item) {
        $g = isset($item['group']) ? (string)$item['group'] : '';
        if ($g === '') {
            $loose[] = $item;
        } else {
            $groups[$g][] = $item;
        }
    }
    $out = $loose;
    foreach ($groups as $list) {
        foreach ($list as $item) $out[] = $item;
    }
    return $out;
}

/**
 * What a "Manage library" call changes, worked out on the items as
 * pg_library_list() gives them (each with 'id'). Returns
 * array('write' => id => item, 'delete' => ids) with only the items whose
 * name, group or order changed, or a string error message.
 *
 *   library_rename  id, name
 *   library_move    ids, group ('' for no group): after the group's last item
 *   library_order   order: the drawn list as array(id, group) pairs; an item
 *                   left out keeps its place after the ones named
 *   library_delete  ids
 *
 * Every change but a delete numbers the whole library again in the drawn
 * order, so the order kept is the order seen.
 */
function pg_library_manage_changes($items, $sub, $request)
{
    $by_id = array();
    foreach ((array)$items as $item) $by_id[(int)$item['id']] = $item;
    $ids = array();
    foreach ((isset($request['ids']) && is_array($request['ids']) ? $request['ids'] : array()) as $id) {
        $id = (int)$id;
        if (isset($by_id[$id]) && !in_array($id, $ids, true)) $ids[] = $id;
    }
    $missing = lang('The library item was not found.');

    if ($sub === 'library_delete') {
        if (!$ids) return $missing;
        return array('write' => array(), 'delete' => $ids);
    }

    $next = array_values($by_id);
    if ($sub === 'library_rename') {
        $id = isset($request['id']) ? (int)$request['id'] : 0;
        if (!isset($by_id[$id])) return $missing;
        $name = pg_library_clean_label(isset($request['name']) ? $request['name'] : '');
        if ($name === '') return lang(array('string' => '{var:1} is required', 'vars' => lang('Name')));
        foreach ($next as $i => $item) {
            if ((int)$item['id'] === $id) $next[$i]['name'] = $name;
        }
        $next = pg_library_drawn_order($next);
    } elseif ($sub === 'library_move') {
        if (!$ids) return $missing;
        $group = pg_library_clean_label(isset($request['group']) ? $request['group'] : '');
        $stay = array();
        $moved = array();
        foreach (pg_library_drawn_order($next) as $item) {
            if (in_array((int)$item['id'], $ids, true)) {
                $item['group'] = $group;
                $moved[] = $item;
            } else {
                $stay[] = $item;
            }
        }
        // After the group's last item; a new group after every other.
        $at = count($stay);
        foreach ($stay as $i => $item) {
            if ((string)$item['group'] === $group) $at = $i + 1;
        }
        if ($group === '' && !array_filter($stay, function ($it) { return (string)$it['group'] === ''; })) $at = 0;
        array_splice($stay, $at, 0, $moved);
        $next = pg_library_drawn_order($stay);
    } elseif ($sub === 'library_order') {
        $named = array();
        $list = array();
        foreach ((isset($request['order']) && is_array($request['order']) ? $request['order'] : array()) as $pair) {
            $id = is_array($pair) && isset($pair['id']) ? (int)$pair['id'] : 0;
            if (!isset($by_id[$id]) || isset($named[$id])) continue;
            $named[$id] = true;
            $item = $by_id[$id];
            $item['group'] = pg_library_clean_label(isset($pair['group']) ? $pair['group'] : '');
            $list[] = $item;
        }
        if (!$list) return $missing;
        foreach (pg_library_drawn_order($next) as $item) {
            if (!isset($named[(int)$item['id']])) $list[] = $item;
        }
        $next = pg_library_drawn_order($list);
    } else {
        return lang('Invalid action.');
    }

    $write = array();
    foreach (array_values($next) as $i => $item) {
        $item['order'] = $i + 1;
        $was = $by_id[(int)$item['id']];
        if ((int)$was['order'] !== $item['order'] || (string)$was['group'] !== (string)$item['group'] || (string)$was['name'] !== (string)$item['name']) {
            $write[(int)$item['id']] = $item;
        }
    }
    return array('write' => $write, 'delete' => array());
}

// ── The file (pure) ────────────────────────────────────────────────────

// The item as the file writes it.
function pg_library_item_encode($item)
{
    unset($item['dropped'], $item['id'], $item['file']);
    return json_encode($item, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
}

/**
 * An item read from its file. Returns the item array, or a string telling
 * why the text is not an item.
 */
function pg_library_item_decode($json)
{
    if (!is_string($json) || $json === '') return 'empty';
    if (strlen($json) > PG_LIBRARY_ITEM_MAX) return 'too_large';
    $item = json_decode($json, true);
    if (!is_array($item) || !isset($item['format']) || $item['format'] !== PG_LIBRARY_FORMAT) return 'not_an_item';
    if (!isset($item['version']) || (int)$item['version'] < 1 || (int)$item['version'] > PG_LIBRARY_VERSION) return 'version';
    if (!isset($item['tree']) || !is_array($item['tree']) || !isset($item['tree']['type'])) return 'no_tree';
    $item['name'] = pg_library_clean_label(isset($item['name']) ? $item['name'] : '');
    $item['group'] = pg_library_clean_label(isset($item['group']) ? $item['group'] : '');
    if ($item['name'] === '') return 'no_name';
    foreach (array('rules', 'variables', 'widgets', 'shared', 'refs') as $k) {
        if (!isset($item[$k]) || !is_array($item[$k])) $item[$k] = array();
    }
    $rules = array();
    foreach ($item['rules'] as $rule) {
        if (!is_array($rule) || !isset($rule['selector'], $rule['body']) || !is_string($rule['selector']) || !is_string($rule['body'])) continue;
        // A rule that could close its block early is not a rule of this item.
        if (strpbrk($rule['selector'] . $rule['body'], '{}<') !== false) continue;
        $media = array();
        foreach ((isset($rule['media']) ? (array)$rule['media'] : array()) as $m) {
            if (is_string($m) && preg_match('/^@(media|supports|layer|container)\b[^{}<;]*$/i', $m)) $media[] = $m;
        }
        $rules[] = array('media' => $media, 'selector' => $rule['selector'], 'body' => $rule['body']);
    }
    $item['rules'] = $rules;
    $vars = array();
    foreach ($item['variables'] as $name => $value) {
        if (preg_match('/^--[_a-zA-Z0-9-]+$/', (string)$name) && pg_library_var_value_ok($value)) $vars[$name] = $value;
    }
    $item['variables'] = $vars;
    $item['order'] = isset($item['order']) ? (int)$item['order'] : 0;
    return $item;
}

// The file name an item is given: library-<name>.json, before a number is
// added for a name in use.
function pg_library_file_slug($name)
{
    $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', (string)$name), '-'));
    if ($slug === '') $slug = 'item';
    return PG_LIBRARY_PREFIX . substr($slug, 0, 60);
}

// ── The store (files) ──────────────────────────────────────────────────

// The SQL condition that picks the library's files.
function pg_library_files_where()
{
    return "(files.design = '1') AND (files.type = 'json') AND (files.name LIKE '" . PG_LIBRARY_PREFIX . "%')"
        . (function_exists('pg_design_files_bin_where') ? pg_design_files_bin_where() : '');
}

/**
 * Every item of the library, in its order then by name: each with 'id' (the
 * files row) and 'file'. A file that is not an item is left out.
 */
function pg_library_list()
{
    $out = array();
    $rows = db_items("SELECT files.id, files.name FROM files WHERE " . pg_library_files_where() . " ORDER BY files.id ASC");
    foreach ((array)$rows as $row) {
        $item = _pg_library_read_file((string)$row['name']);
        if (!is_array($item)) continue;
        $item['id'] = (int)$row['id'];
        $item['file'] = (string)$row['name'];
        $out[] = $item;
    }
    usort($out, function ($a, $b) {
        if ($a['order'] !== $b['order']) return $a['order'] < $b['order'] ? -1 : 1;
        $c = strcasecmp($a['name'], $b['name']);
        return $c !== 0 ? $c : ($a['id'] - $b['id']);
    });
    return $out;
}

// One item by its files row id, or null.
function pg_library_get($id)
{
    $id = (int)$id;
    if ($id <= 0) return null;
    $name = db_value("SELECT files.name FROM files WHERE (files.id = '" . $id . "') AND " . pg_library_files_where());
    if (!$name) return null;
    $item = _pg_library_read_file((string)$name);
    if (!is_array($item)) return null;
    $item['id'] = $id;
    $item['file'] = (string)$name;
    return $item;
}

function _pg_library_read_file($name)
{
    if (!defined('FILE_DIRECTORY_PATH') || basename($name) !== $name) return null;
    $path = FILE_DIRECTORY_PATH . '/' . $name;
    if (!is_file($path) || (int)@filesize($path) > PG_LIBRARY_ITEM_MAX) return null;
    $item = pg_library_item_decode((string)@file_get_contents($path));
    return is_array($item) ? $item : null;
}

/**
 * Writes a new item as a design file. Returns array('id', 'file') or a
 * string error message.
 */
function pg_library_save($item, $user_id)
{
    $json = pg_library_item_encode($item);
    $check = pg_library_item_decode($json);
    if (!is_array($check)) return lang('The library item could not be saved.');
    $slug = pg_library_file_slug($check['name']);
    $file_name = $slug . '.json';
    for ($i = 2; check_name_availability(array('name' => $file_name)) == false && $i < 1000; $i++) {
        $file_name = $slug . '-' . $i . '.json';
    }
    if (check_name_availability(array('name' => $file_name)) == false) {
        return lang('The library item could not be saved.');
    }
    if (!pg_file_write_whole(FILE_DIRECTORY_PATH . '/' . $file_name, $json)) {
        return lang(array('string' => '{var:1} could not be written to disk.', 'vars' => $file_name));
    }
    $folder_id = (int)db_value("SELECT folder_id FROM folder WHERE folder_parent = '0' ORDER BY folder_id LIMIT 1");
    db("INSERT INTO files (name, folder, description, type, size, user, design, theme, timestamp)
        VALUES ('" . e($file_name) . "', '$folder_id', '', 'json', '" . (int)strlen($json) . "', '" . (int)$user_id . "', '1', '0', UNIX_TIMESTAMP())");
    $id = (int)mysqli_insert_id(db::$con);
    if ($id <= 0) {
        @unlink(FILE_DIRECTORY_PATH . '/' . $file_name);
        return lang('The library item could not be saved.');
    }
    return array('id' => $id, 'file' => $file_name);
}

/**
 * Writes an item over its file (a rename, a new group or order). Returns
 * true or a string error message.
 */
function pg_library_update($id, $item, $user_id)
{
    $current = pg_library_get($id);
    if (!$current) return lang('The library item was not found.');
    $json = pg_library_item_encode($item);
    if (!is_array(pg_library_item_decode($json))) return lang('The library item could not be saved.');
    if (!pg_file_write_whole(FILE_DIRECTORY_PATH . '/' . $current['file'], $json)) {
        return lang(array('string' => '{var:1} could not be written to disk.', 'vars' => $current['file']));
    }
    db("UPDATE files SET size = '" . (int)strlen($json) . "', user = '" . (int)$user_id . "', timestamp = UNIX_TIMESTAMP()
        WHERE id = '" . (int)$current['id'] . "'");
    return true;
}

// Deletes an item: its file and its row. Returns true or a string error.
function pg_library_delete($id)
{
    $current = pg_library_get($id);
    if (!$current) return lang('The library item was not found.');
    $path = FILE_DIRECTORY_PATH . '/' . $current['file'];
    if (is_file($path) && !@unlink($path)) {
        return lang(array('string' => '{var:1} could not be written to disk.', 'vars' => $current['file']));
    }
    db("DELETE FROM files WHERE id = '" . (int)$current['id'] . "'");
    return true;
}

/**
 * The framework classes (Bootstrap 5.3.8 as the site loads it), read once
 * from the designer's vocabulary: class => true.
 */
function pg_library_framework_classes()
{
    static $set = null;
    if ($set !== null) return $set;
    $set = array();
    $path = PG_FUNCTIONS_DIR . '/assets/js/designer_vocab/bootstrap5.json';
    $data = is_file($path) ? json_decode((string)@file_get_contents($path), true) : null;
    if (is_array($data) && isset($data['classes']) && is_array($data['classes'])) {
        foreach (array_keys($data['classes']) as $c) $set[(string)$c] = true;
    }
    return $set;
}

// ── What the editor shows (pure) ───────────────────────────────────────

// The number of elements a tree holds, the root included.
function pg_library_node_count($tree, $depth = 0)
{
    if (!is_array($tree) || $depth > 80) return 0;
    $n = 1;
    if (isset($tree['children']) && is_array($tree['children'])) {
        foreach ($tree['children'] as $child) $n += pg_library_node_count($child, $depth + 1);
    }
    return $n;
}

/**
 * What an item's root is, for its row and its card: section, row, card or
 * group (any other element that holds others).
 */
function pg_library_root_kind($node)
{
    $type = isset($node['type']) ? (string)$node['type'] : '';
    $tag = isset($node['props']['tag']) ? strtolower((string)$node['props']['tag']) : '';
    $classes = _pg_library_node_classes($node);
    if ($type === 'row' || in_array('row', $classes, true)) return 'row';
    if (in_array('card', $classes, true)) return 'card';
    $sections = array('section', 'header', 'footer', 'main', 'article', 'aside', 'nav');
    if (in_array($type, $sections, true) || in_array($tag, $sections, true)) return 'section';
    return 'group';
}

// Whether an item's elements use a class of the framework (Bootstrap).
function pg_library_uses_framework($item, $framework)
{
    $uses = false;
    _pg_library_each_tree($item, function (&$n) use ($framework, &$uses) {
        foreach (_pg_library_node_classes($n) as $c) {
            if (isset($framework[$c])) $uses = true;
        }
    });
    return $uses;
}

/**
 * One item as the Blocks list draws it: its row, its card's drawing (the
 * tree), the variables it reads (the editor tells which the design has),
 * and how many widgets and components an insert makes.
 */
function pg_library_card($item, $framework)
{
    return array(
        'id'        => isset($item['id']) ? (int)$item['id'] : 0,
        'name'      => (string)$item['name'],
        'group'     => (string)$item['group'],
        'kind'      => pg_library_root_kind($item['tree']),
        'count'     => pg_library_node_count($item['tree']),
        'tree'      => $item['tree'],
        'variables' => array_keys((array)$item['variables']),
        'rows'      => count((array)$item['widgets']) + count((array)$item['shared']),
        'bootstrap' => pg_library_uses_framework($item, $framework),
    );
}

/**
 * The tree of an insert with the rows just made: a placement of a widget
 * or component of the item (props.libraryWidget / libraryShared) points at
 * its new row. $made: key => array('id', 'name'). A key not made is left
 * as it is.
 */
function pg_library_tree_place_rows($tree, $made)
{
    _pg_library_walk($tree, function (&$n) use ($made) {
        if (!isset($n['type']) || $n['type'] !== 'shared_ref' || !isset($n['props']) || !is_array($n['props'])) return;
        foreach (array('libraryWidget', 'libraryShared') as $k) {
            $key = isset($n['props'][$k]) ? (string)$n['props'][$k] : '';
            if ($key === '' || !isset($made[$key])) continue;
            unset($n['props'][$k]);
            $n['props']['sharedId'] = (int)$made[$key]['id'];
            $n['props']['sharedName'] = (string)$made[$key]['name'];
        }
    });
    return $tree;
}

// ── What the editor asks (database) ────────────────────────────────────

/**
 * The rows a subtree places, and the rows those place: sid => array('name',
 * 'tree', 'config' (null for a plain shared component)).
 */
function pg_library_site_rows($node)
{
    $rows = array();
    $queue = array($node);
    for ($guard = 0; $queue && $guard < 200; $guard++) {
        $want = array();
        foreach ($queue as $tree) {
            _pg_library_walk($tree, function (&$n) use (&$want, $rows) {
                if (!isset($n['type']) || $n['type'] !== 'shared_ref') return;
                $sid = isset($n['props']['sharedId']) ? (int)$n['props']['sharedId'] : 0;
                if ($sid > 0 && !isset($rows[$sid])) $want[$sid] = true;
            });
        }
        $queue = array();
        if (!$want) break;
        $found = db_items("SELECT id, name, tree_json, system_region_config FROM shared_components
            WHERE id IN (" . implode(',', array_map('intval', array_keys($want))) . ")");
        foreach ((array)$found as $row) {
            $tree = json_decode((string)$row['tree_json'], true);
            $config = $row['system_region_config'] !== null && $row['system_region_config'] !== ''
                ? json_decode((string)$row['system_region_config'], true) : null;
            $rows[(int)$row['id']] = array('name' => (string)$row['name'],
                'tree' => is_array($tree) ? $tree : array('type' => 'div', 'children' => array()),
                'config' => is_array($config) ? $config : null);
            if (is_array($tree)) $queue[] = $tree;
        }
    }
    return $rows;
}

// The site's records an item may point at, id => name, by kind.
function pg_library_site_records()
{
    $out = array('pages' => array(), 'groups' => array(), 'folders' => array(), 'product_groups' => array(), 'calendars' => array(),
                 'menus' => array());
    foreach ((array)db_items("SELECT page_id, page_name FROM page") as $r) $out['pages'][(int)$r['page_id']] = (string)$r['page_name'];
    foreach ((array)db_items("SELECT id, name FROM contact_groups") as $r) $out['groups'][(int)$r['id']] = (string)$r['name'];
    foreach ((array)db_items("SELECT folder_id, folder_name FROM folder") as $r) $out['folders'][(int)$r['folder_id']] = (string)$r['folder_name'];
    foreach ((array)(db_items("SELECT id, name FROM product_groups") ?: array()) as $r) $out['product_groups'][(int)$r['id']] = (string)$r['name'];
    foreach ((array)(db_items("SELECT id, name FROM calendars") ?: array()) as $r) $out['calendars'][(int)$r['id']] = (string)$r['name'];
    foreach ((array)(db_items("SELECT id, name FROM menus") ?: array()) as $r) $out['menus'][(int)$r['id']] = (string)$r['name'];
    return $out;
}

// sid => name of the site's shared components an item's entries name.
function pg_library_site_shared($item)
{
    $ids = array();
    foreach ((array)$item['shared'] as $entry) {
        if (!empty($entry['sid'])) $ids[] = (int)$entry['sid'];
    }
    $out = array();
    if (!$ids) return $out;
    foreach ((array)db_items("SELECT id, name FROM shared_components WHERE id IN (" . implode(',', $ids) . ")") as $r) {
        $out[(int)$r['id']] = (string)$r['name'];
    }
    return $out;
}

// The design as the editor hands it in: its User Styles, and the values of
// the variables it was asked about that the canvas defines.
function _pg_library_request_design($request)
{
    $css = isset($request['css']) && is_string($request['css']) ? $request['css'] : '';
    $vars = pg_library_css_var_defs($css);
    if (isset($request['vars']) && is_array($request['vars'])) {
        foreach ($request['vars'] as $name => $value) {
            if (is_string($name) && preg_match('/^--[_a-zA-Z0-9-]+$/', $name) && pg_library_var_value_ok(trim((string)$value))) {
                if (!isset($vars[$name])) $vars[$name] = trim((string)$value);
            }
        }
    }
    return array('css' => $css, 'vars' => $vars, 'framework' => pg_library_framework_classes());
}

/**
 * The visual designer's library calls (sub actions of the designer panel
 * action). Answers the response array.
 *
 *   library_list     the items as the Blocks list draws them ('use')
 *   library_save     a subtree saved as a new item ('save'): tree, name,
 *                    group, css, vars
 *   library_prepare  an item made ready for this design ('use'): id, css,
 *                    vars; with make=1 the widgets and components it holds
 *                    are made as rows and the tree points at them
 *   library_rename, library_move, library_order, library_delete
 *                    "Manage library" ('manage', pg_library_manage_changes()):
 *                    answers the items as library_list does
 */
function pg_library_editor($sub, $request, $user)
{
    $deny = array('status' => 'error', 'message' => lang('Permission denied.'));
    if ($sub === 'library_list') {
        if (!pg_library_may('use', $user)) return $deny;
        $framework = pg_library_framework_classes();
        $items = array();
        foreach (pg_library_list() as $item) $items[] = pg_library_card($item, $framework);
        return array('status' => 'success', 'items' => $items, 'may_save' => pg_library_may('save', $user));
    }

    if ($sub === 'library_save') {
        if (!pg_library_may('save', $user)) return $deny;
        $tree = isset($request['tree']) ? $request['tree'] : null;
        if (is_string($tree)) $tree = json_decode($tree, true);
        if (!is_array($tree) || !isset($tree['type'])) return array('status' => 'error', 'message' => lang('The library item could not be saved.'));
        $name = pg_library_clean_label(isset($request['name']) ? $request['name'] : '');
        if ($name === '') return array('status' => 'error', 'message' => lang(array('string' => '{var:1} is required', 'vars' => lang('Name'))));
        $site = pg_library_site_records();
        $site['rows'] = pg_library_site_rows($tree);
        $item = pg_library_item_from_subtree($tree, array('name' => $name, 'group' => isset($request['group']) ? $request['group'] : ''),
            _pg_library_request_design($request), $site);
        // A new item goes to the end of the library's order.
        $last = 0;
        foreach (pg_library_list() as $other) $last = max($last, (int)$other['order']);
        $item['order'] = $last + 1;
        $saved = pg_library_save($item, (int)$user['id']);
        if (!is_array($saved)) return array('status' => 'error', 'message' => $saved);
        $item['id'] = $saved['id'];
        return array('status' => 'success', 'item' => pg_library_card($item, pg_library_framework_classes()));
    }

    if ($sub === 'library_prepare') {
        if (!pg_library_may('use', $user)) return $deny;
        $item = pg_library_get(isset($request['id']) ? $request['id'] : 0);
        if (!$item) return array('status' => 'error', 'message' => lang('The library item was not found.'));
        $site = pg_library_site_records();
        $site['shared'] = pg_library_site_shared($item);
        // The address readers of the import checks count the pictures.
        require_once(PG_FUNCTIONS_DIR . '/includes/fn/designer_library_io.php');
        $site['path'] = defined('PATH') ? PATH : '/';
        $site['host'] = defined('HOSTNAME') ? (string)HOSTNAME : '';
        $site['has_file'] = function ($name) {
            return (bool)db_value("SELECT id FROM files WHERE name = '" . e($name) . "' LIMIT 1");
        };
        $out = pg_library_item_into_design($item, _pg_library_request_design($request), $site);
        $made = array();
        if (!empty($request['make']) && ($out['widgets'] || $out['shared'])) {
            // Rows of the design are a designer's to make.
            if (!pg_library_may('save', $user)) {
                return array('status' => 'error', 'message' => lang('This item holds widgets or shared components: a designer adds it.'));
            }
            $made = _pg_library_make_rows($out, (int)$user['id']);
            $out['tree'] = pg_library_tree_place_rows($out['tree'], $made);
        }
        $unbound = array();
        foreach ($out['widgets'] as $key => $w) {
            if (!empty($w['cleared'])) $unbound[] = $w['name'];
        }
        return array('status' => 'success', 'tree' => $out['tree'], 'css' => $out['css'], 'renamed' => $out['renamed'],
            'cleared' => (int)$out['cleared'], 'pictures_missing' => (int)$out['pictures_missing'], 'unbound' => $unbound, 'rows' => count($out['widgets']) + count($out['shared']),
            'made' => $made);
    }

    if (in_array($sub, array('library_rename', 'library_move', 'library_order', 'library_delete'), true)) {
        if (!pg_library_may('manage', $user)) return $deny;
        $changes = pg_library_manage_changes(pg_library_list(), $sub, $request);
        if (!is_array($changes)) return array('status' => 'error', 'message' => $changes);
        foreach ($changes['delete'] as $id) {
            $done = pg_library_delete($id);
            if ($done !== true) return array('status' => 'error', 'message' => $done);
        }
        foreach ($changes['write'] as $id => $item) {
            $done = pg_library_update($id, $item, (int)$user['id']);
            if ($done !== true) return array('status' => 'error', 'message' => $done);
        }
        $framework = pg_library_framework_classes();
        $items = array();
        foreach (pg_library_list() as $item) $items[] = pg_library_card($item, $framework);
        return array('status' => 'success', 'items' => $items);
    }

    return array('status' => 'error', 'message' => lang('Invalid action.'));
}

// Makes the widgets and shared components an insert needs as new rows:
// key => array('id', 'name'). A component placed inside another is made
// first, so the outer one's tree points at it.
function _pg_library_make_rows($out, $user_id)
{
    $made = array();
    $now = time();
    $pending = array();
    foreach ($out['shared'] as $key => $entry) $pending[$key] = array('entry' => $entry, 'config' => null);
    foreach ($out['widgets'] as $key => $entry) {
        $config = is_array($entry['config']) ? $entry['config'] : array();
        if ($entry['kind'] !== '' && !isset($config['regionType'])) $config['regionType'] = $entry['kind'];
        $pending[$key] = array('entry' => $entry, 'config' => $config);
    }
    for ($round = 0; $pending && $round < 20; $round++) {
        foreach ($pending as $key => $p) {
            // Wait while its tree places a row not made yet.
            $waits = false;
            _pg_library_walk($p['entry']['tree'], function (&$n) use ($made, $pending, $key, &$waits) {
                foreach (array('libraryWidget', 'libraryShared') as $k) {
                    $inner = isset($n['props'][$k]) ? (string)$n['props'][$k] : '';
                    if ($inner !== '' && $inner !== $key && !isset($made[$inner]) && isset($pending[$inner])) $waits = true;
                }
            });
            if ($waits && $round < 19) continue;
            $tree = pg_library_tree_place_rows(is_array($p['entry']['tree']) ? $p['entry']['tree'] : array('type' => 'div', 'children' => array()), $made);
            $name = _sc_unique_name($p['entry']['name'] !== '' ? $p['entry']['name'] : lang('Shared Component'));
            $cfg_sql = $p['config'] !== null ? "'" . e(json_encode($p['config'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) . "'" : 'NULL';
            db("INSERT INTO shared_components (name, description, tree_json, system_region_config, category, created_by, created_at, updated_at)
                VALUES ('" . e($name) . "', '', '" . e(pg_designer_tree_encode($tree)) . "', $cfg_sql, '', '" . (int)$user_id . "', '$now', '$now')");
            $sid = (int)mysqli_insert_id(db::$con);
            if ($sid > 0) $made[$key] = array('id' => $sid, 'name' => $name);
            unset($pending[$key]);
        }
    }
    return $made;
}
