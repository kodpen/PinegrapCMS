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
// An export file: its format line, its size and the items it may hold.
define('PG_LIBRARY_EXPORT_FORMAT', 'pinegrap-library');
define('PG_LIBRARY_EXPORT_MAX', 16777216);
define('PG_LIBRARY_EXPORT_ITEMS', 200);

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
// what a form or a collection is), group (a contact group), folder; '' for
// any other key.
function pg_library_ref_kind($key)
{
    $key = (string)$key;
    if (strpos($key, 'contact_group_id') !== false || $key === 'group_id') return 'group';
    if (strpos($key, 'folder_id') !== false) return 'folder';
    if (strpos($key, 'page_id') !== false || strpos($key, 'form_id') !== false) return 'page';
    return '';
}

/**
 * Calls $fn($kind, $id, $raw) for every record id a settings array or a
 * node's props name; the value is replaced by what $fn returns. A node's
 * form reference field (_cf.ref) points at a page.
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
    );
    $refs = array();
    $collect = function ($kind, $id, $raw) use (&$refs, $names) {
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
 *   'cleared'  number of references cleared)
 */
function pg_library_item_into_design($item, $design, $site)
{
    $framework = isset($design['framework']) && is_array($design['framework']) ? $design['framework'] : array();
    $out = array('tree' => null, 'widgets' => array(), 'shared' => array(), 'css' => '',
                 'rules_added' => 0, 'renamed' => array(), 'vars_added' => array(), 'cleared' => 0);

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
    );
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
    $map = function ($kind, $id, $raw) use ($resolve, &$cleared_kinds) {
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
    // a placement of a component the site has points at it again.
    _pg_library_each_tree($copy, function (&$n) use ($widget_cleared, $kept_shared) {
        if (!isset($n['type']) || $n['type'] !== 'shared_ref') return;
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
    return $out;
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

// ── Export and import (pure) ───────────────────────────────────────────

/**
 * Whether an address is of a kind an imported item may carry: a path on
 * the site (no scheme: "/x", "x.html", "#id", "?q", "//host" too), http,
 * https, mailto, tel, sms, or a data: picture of a raster kind. A
 * {{token}} of a widget's record has no scheme, so it is kept. Entities
 * and the blanks and control characters a browser skips are taken out
 * first, so "java&#115;cript:" or "java\tscript:" is read as it would run.
 */
function pg_library_address_ok($url)
{
    $u = html_entity_decode((string)$url, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $u = preg_replace('/[\x00-\x20\x7f]+/', '', $u);
    if ($u === '' || !preg_match('/^([a-zA-Z][a-zA-Z0-9+.\-]*):/', $u, $m)) return true;
    $scheme = strtolower($m[1]);
    if (in_array($scheme, array('http', 'https', 'mailto', 'tel', 'sms'), true)) return true;
    if ($scheme === 'data') return (bool)preg_match('#^data:image/(png|jpe?g|gif|webp|avif)[;,]#i', $u);
    return false;
}

// The attribute and prop names that hold an address.
function pg_library_address_key($name)
{
    $name = strtolower((string)$name);
    return in_array($name, array('href', 'src', 'action', 'formaction', 'poster', 'xlink:href', 'data', 'cite',
                                 'background', 'url', 'link', 'data-src', 'data-bg', 'data-background',
                                 'srcset', 'data-srcset', 'imagesrcset'), true);
}

// The addresses of a srcset value: the first word of each candidate.
function _pg_library_srcset_urls($value)
{
    $out = array();
    foreach (explode(',', (string)$value) as $part) {
        $words = preg_split('/\s+/', trim($part), -1, PREG_SPLIT_NO_EMPTY);
        if ($words) $out[] = $words[0];
    }
    return $out;
}

/**
 * What is wrong with one text of an item ('' when nothing): a script, an
 * event attribute, or an address of a kind not allowed. Reads the text as
 * markup when it holds a tag and as a style when it holds url( or
 * expression(; $key names the prop or attribute it came from, so an address
 * field is checked whatever its text.
 */
function pg_library_text_problem($text, $key = '')
{
    if (!is_string($text) || $text === '') return '';
    if ($key !== '' && pg_library_address_key($key)) {
        $urls = (stripos($key, 'srcset') !== false) ? _pg_library_srcset_urls($text) : array($text);
        foreach ($urls as $u) {
            if (!pg_library_address_ok($u)) return 'address';
        }
    }
    $decoded = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    // A string that starts with a scheme that runs, whatever its key.
    if (preg_match('/^[\x00-\x20]*(javascript|vbscript|livescript)\s*:/i', $decoded)) return 'address';
    if (strpos($text, '<') !== false) {
        if (preg_match('/<\s*\/?\s*script\b/i', $text)) return 'script';
        if (preg_match_all('/<[a-zA-Z][^>]*>/', $text, $tags)) {
            foreach ($tags[0] as $tag) {
                if (preg_match('/[\s\/"\']on[a-zA-Z]+\s*=/', $tag)) return 'event';
                if (preg_match('/[\s\/"\']srcdoc\s*=/i', $tag)) return 'script';
                if (preg_match_all('/[\s\/"\']([a-zA-Z:-]+)\s*=\s*("([^"]*)"|\'([^\']*)\'|([^\s>]+))/', $tag, $attrs, PREG_SET_ORDER)) {
                    foreach ($attrs as $a) {
                        if (!pg_library_address_key($a[1])) continue;
                        $value = isset($a[5]) && $a[5] !== '' ? $a[5] : (isset($a[4]) && $a[4] !== '' ? $a[4] : $a[3]);
                        $urls = (stripos($a[1], 'srcset') !== false) ? _pg_library_srcset_urls($value) : array($value);
                        foreach ($urls as $u) {
                            if (!pg_library_address_ok($u)) return 'address';
                        }
                    }
                }
            }
        }
    }
    return pg_library_css_problem($decoded);
}

// What is wrong with a style text ('' when nothing): a CSS expression, a
// binding, an @import, or a url() of a kind not allowed.
function pg_library_css_problem($css)
{
    $css = (string)$css;
    if ($css === '') return '';
    $flat = preg_replace('/\\\\|\/\*.*?\*\//s', '', $css);
    if (preg_match('/expression\s*\(|-moz-binding|behavior\s*:|@import/i', $flat)) return 'script';
    if (preg_match_all('/url\(\s*(["\']?)([^"\')]*)\1\s*\)/i', $flat, $m)) {
        foreach ($m[2] as $u) {
            if (!pg_library_address_ok($u)) return 'address';
        }
    }
    return '';
}

// Calls $fn($key, $string) on every string of an array, nested ones
// included; $key is the nearest named key. Stops at the first answer that
// is not ''.
function _pg_library_strings_problem($value, $fn, $key = '', $depth = 0)
{
    if ($depth > 40) return 'deep';
    if (is_string($value)) return $fn($key, $value);
    if (!is_array($value)) return '';
    foreach ($value as $k => $v) {
        $r = _pg_library_strings_problem($v, $fn, is_int($k) ? $key : (string)$k, $depth + 1);
        if ($r !== '') return $r;
    }
    return '';
}

/**
 * What an item read from a file carries that an import refuses, as the
 * HTML and ZIP import refuses it: 'script' (a script element or node, a
 * srcdoc frame, a CSS expression or binding), 'event' (an on… attribute),
 * 'address' (an address outside the allowed kinds, pg_library_address_ok()),
 * 'deep' (a tree nested past what the editor draws); '' when nothing.
 */
function pg_library_item_problem($item)
{
    if (!is_array($item)) return 'deep';
    $problem = '';
    $text_check = function ($key, $s) {
        return pg_library_text_problem($s, $key);
    };
    $node_check = function (&$n) use (&$problem, $text_check) {
        if ($problem !== '') return;
        $type = isset($n['type']) ? strtolower((string)$n['type']) : '';
        $tag = isset($n['props']['tag']) && is_string($n['props']['tag']) ? strtolower($n['props']['tag']) : '';
        if ($type === 'script' || $tag === 'script') {
            $problem = 'script';
            return;
        }
        if (!isset($n['props']) || !is_array($n['props'])) return;
        $props = $n['props'];
        if (isset($props['_attrs']) && is_array($props['_attrs'])) {
            foreach ($props['_attrs'] as $a) {
                if (!is_array($a) || !isset($a['name'])) continue;
                $name = strtolower(trim((string)$a['name']));
                if (strpos($name, 'on') === 0) {
                    $problem = 'event';
                    return;
                }
                if ($name === 'srcdoc') {
                    $problem = 'script';
                    return;
                }
                $r = pg_library_text_problem(isset($a['value']) ? (string)$a['value'] : '', $name);
                if ($r !== '') {
                    $problem = $r;
                    return;
                }
            }
            unset($props['_attrs']);
        }
        $problem = _pg_library_strings_problem($props, $text_check);
    };
    _pg_library_each_tree($item, $node_check);
    if ($problem !== '') return $problem;
    // A tree deeper than the walk reads is refused rather than half read.
    $deep = function ($node, $d) use (&$deep) {
        if ($d > 80) return true;
        if (isset($node['children']) && is_array($node['children'])) {
            foreach ($node['children'] as $c) {
                if (is_array($c) && $deep($c, $d + 1)) return true;
            }
        }
        return false;
    };
    if (isset($item['tree']) && $deep($item['tree'], 0)) return 'deep';
    foreach (array('widgets', 'shared') as $bucket) {
        foreach ((isset($item[$bucket]) && is_array($item[$bucket]) ? $item[$bucket] : array()) as $entry) {
            if (!is_array($entry)) continue;
            if (isset($entry['tree']) && is_array($entry['tree']) && $deep($entry['tree'], 0)) return 'deep';
            if (isset($entry['config'])) {
                $r = _pg_library_strings_problem($entry['config'], $text_check);
                if ($r !== '') return $r;
            }
        }
    }
    foreach ((isset($item['rules']) ? $item['rules'] : array()) as $rule) {
        $r = pg_library_css_problem($rule['selector'] . '{' . $rule['body'] . '}');
        if ($r !== '') return $r;
    }
    foreach ((isset($item['variables']) ? $item['variables'] : array()) as $value) {
        $r = pg_library_css_problem((string)$value);
        if ($r !== '') return $r;
    }
    return '';
}

/**
 * The text of an export file: the chosen items, each as its own file holds
 * it (without the site's row id, file name and order), under one format
 * line. $at is the date it is written (Y-m-d).
 */
function pg_library_export_text($items, $at)
{
    $list = array();
    foreach ((array)$items as $item) {
        if (!is_array($item)) continue;
        unset($item['dropped'], $item['id'], $item['file'], $item['order']);
        $list[] = $item;
    }
    $doc = array('format' => PG_LIBRARY_EXPORT_FORMAT, 'version' => PG_LIBRARY_VERSION,
                 'exported' => (string)$at, 'items' => $list);
    return json_encode($doc, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
}

// The name an export file is offered under.
function pg_library_export_file_name($at)
{
    $at = preg_replace('/[^0-9-]/', '', (string)$at);
    return 'my-library' . ($at !== '' ? '-' . $at : '') . '.json';
}

/**
 * The items of an import file: an export file (format pinegrap-library) or
 * one item's own file. Every item passes pg_library_item_decode() and
 * pg_library_item_problem(); one that does not refuses the whole file.
 *
 * Returns array('items' => list) or array('error' => code, 'item' => the
 * name of the item refused, '' for the file). Codes: empty, too_large,
 * not_a_library, version, too_many, none, and the codes of
 * pg_library_item_decode() and pg_library_item_problem().
 */
function pg_library_import_read($text)
{
    if (!is_string($text) || trim($text) === '') return array('error' => 'empty', 'item' => '');
    if (strlen($text) > PG_LIBRARY_EXPORT_MAX) return array('error' => 'too_large', 'item' => '');
    $doc = json_decode($text, true);
    if (!is_array($doc) || !isset($doc['format'])) return array('error' => 'not_a_library', 'item' => '');
    if ($doc['format'] === PG_LIBRARY_FORMAT) {
        $raw = array($doc);
    } elseif ($doc['format'] === PG_LIBRARY_EXPORT_FORMAT) {
        if (!isset($doc['version']) || (int)$doc['version'] < 1 || (int)$doc['version'] > PG_LIBRARY_VERSION) {
            return array('error' => 'version', 'item' => '');
        }
        $raw = isset($doc['items']) && is_array($doc['items']) ? array_values($doc['items']) : array();
    } else {
        return array('error' => 'not_a_library', 'item' => '');
    }
    if (!$raw) return array('error' => 'none', 'item' => '');
    if (count($raw) > PG_LIBRARY_EXPORT_ITEMS) return array('error' => 'too_many', 'item' => '');
    $items = array();
    foreach ($raw as $i => $one) {
        $label = is_array($one) && isset($one['name']) ? pg_library_clean_label($one['name']) : '';
        if ($label === '') $label = '#' . ($i + 1);
        $json = is_array($one) ? json_encode($one, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : '';
        $item = pg_library_item_decode(is_string($json) ? $json : '');
        if (!is_array($item)) return array('error' => $item, 'item' => $label);
        $problem = pg_library_item_problem($item);
        if ($problem !== '') return array('error' => $problem, 'item' => $label);
        unset($item['id'], $item['file'], $item['dropped']);
        $items[] = $item;
    }
    return array('items' => $items);
}

/**
 * The names the imported items are kept under: a name the library has in
 * the same group, or one taken earlier in the same file, gets " (2)", " (3)"
 * and so on, so an import never seems to replace an item. The order of the
 * imported items follows the library's last.
 */
function pg_library_import_names($items, $existing)
{
    $taken = array();
    $last = 0;
    foreach ((array)$existing as $e) {
        $taken[_pg_library_name_key($e)] = true;
        if (isset($e['order']) && (int)$e['order'] > $last) $last = (int)$e['order'];
    }
    foreach ($items as $i => $item) {
        $base = $item['name'];
        for ($n = 2; isset($taken[_pg_library_name_key($item)]) && $n < 1000; $n++) {
            $suffix = ' (' . $n . ')';
            $cut = PG_LIBRARY_NAME_MAX - strlen($suffix);
            $item['name'] = (function_exists('mb_substr') ? mb_substr($base, 0, $cut, 'UTF-8') : substr($base, 0, $cut)) . $suffix;
        }
        $taken[_pg_library_name_key($item)] = true;
        $item['order'] = ++$last;
        $items[$i] = $item;
    }
    return $items;
}

function _pg_library_name_key($item)
{
    $name = isset($item['name']) ? (string)$item['name'] : '';
    $group = isset($item['group']) ? (string)$item['group'] : '';
    $fold = function ($s) {
        return function_exists('mb_strtolower') ? mb_strtolower($s, 'UTF-8') : strtolower($s);
    };
    return $fold($group) . "\n" . $fold($name);
}

/**
 * The sentence an import refusal shows: why, and which item.
 */
function pg_library_import_message($error, $item_name)
{
    switch ((string)$error) {
        case 'empty':     $why = lang('The file is empty.'); break;
        case 'too_large': $why = lang('The file is too large.'); break;
        case 'version':   $why = lang('The file was written by a newer version of Pinegrap.'); break;
        case 'too_many':  $why = lang('The file holds too many items.'); break;
        case 'none':      $why = lang('The file holds no item.'); break;
        case 'no_tree':   $why = lang('An item of the file has no content.'); break;
        case 'no_name':   $why = lang('An item of the file has no name.'); break;
        case 'script':    $why = lang('An item of the file holds a script.'); break;
        case 'event':     $why = lang('An item of the file holds an event attribute (on…).'); break;
        case 'address':   $why = lang('An item of the file holds an address of a kind that is not allowed.'); break;
        case 'deep':      $why = lang('An item of the file is nested too deeply.'); break;
        default:          $why = lang('This is not a library file.');
    }
    if ((string)$item_name === '') return $why;
    return $why . ' ' . lang(array('string' => 'Item: {var:1}', 'vars' => (string)$item_name));
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
 * Keeps the items of an import file (pg_library_import_read()) as items of
 * the library, each under a free name (pg_library_import_names()). Returns
 * the number kept, or a string error message; an item that could not be
 * written stops the import and the ones written before it stay.
 */
function pg_library_import_save($items, $user_id)
{
    $items = pg_library_import_names($items, pg_library_list());
    $kept = 0;
    foreach ($items as $item) {
        $saved = pg_library_save($item, $user_id);
        if (!is_array($saved)) return $saved;
        $kept++;
    }
    return $kept;
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
