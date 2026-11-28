<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Per-place values of a shared component (pure model). A node of a
 * component's tree may open some of its fields to the places that use the
 * component: `props._open` is a list of {id, kind, label}. A placing
 * shared_ref node keeps its own values in `props._place`, an object keyed by
 * the open field's stable id. These functions list the open fields, apply a
 * place's values to a copy of the component's tree and find the values that
 * no longer match an open field. Nothing here reads the database or draws.
 *
 * The browser twin is assets/js/pg_component_overrides.js
 * (window.PGComponentOverrides); the two answer the same for the same input
 * (tests/component_overrides_test.php).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// The kinds of field a node can open, with the props each one writes.
// `shown` writes no prop: false leaves the node out of the drawn tree.
function pg_co_field_kinds()
{
    return array(
        'text'    => array('text'),
        'picture' => array('src', 'alt'),
        'link'    => array('href'),
        'icon'    => array('iconName'),
        'shown'   => array(),
    );
}

// A stable field id: a lower-case letter, then letters, digits or `_`, at
// most 40 characters. New ids are `of_` and eight random characters.
function pg_co_valid_field_id($id)
{
    return is_string($id) && preg_match('/^[a-z][a-z0-9_]{0,39}$/', $id) === 1;
}

// A new field id not among $taken (a list of ids). Random so that a field
// opened after another was closed never inherits the old field's values.
function pg_co_new_field_id($taken = array())
{
    $chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
    $taken = array_flip(array_map('strval', (array) $taken));
    do {
        $id = 'of_';
        for ($i = 0; $i < 8; $i++) {
            $id .= $chars[random_int(0, 35)];
        }
    } while (isset($taken[$id]));
    return $id;
}

// The open-field entries a node declares, cleaned: a known kind and a valid
// id; the label a trimmed string. Malformed entries are dropped.
function pg_co_node_open($node)
{
    if (!is_array($node) || !isset($node['props']['_open']) || !is_array($node['props']['_open'])) {
        return array();
    }
    $kinds = pg_co_field_kinds();
    $out = array();
    foreach ($node['props']['_open'] as $entry) {
        if (!is_array($entry) || !isset($entry['id'], $entry['kind'])) continue;
        if (!pg_co_valid_field_id($entry['id']) || !is_string($entry['kind']) || !isset($kinds[$entry['kind']])) continue;
        $label = (isset($entry['label']) && is_string($entry['label'])) ? trim($entry['label']) : '';
        $out[] = array('id' => $entry['id'], 'kind' => $entry['kind'], 'label' => $label);
    }
    return $out;
}

// The component's own value of a field on $node (the hint a place shows).
function pg_co_default_value($node, $kind)
{
    $props = (isset($node['props']) && is_array($node['props'])) ? $node['props'] : array();
    $str = function ($key) use ($props) {
        return (isset($props[$key]) && (is_string($props[$key]) || is_int($props[$key]) || is_float($props[$key])))
            ? (string) $props[$key] : '';
    };
    switch ($kind) {
        case 'picture': return array('src' => $str('src'), 'alt' => $str('alt'));
        case 'shown':   return true;
        case 'text':    return $str('text');
        case 'link':    return $str('href');
        case 'icon':    return $str('iconName');
    }
    return null;
}

// The open fields of a component's tree in the order its places list them:
// each {id, kind, label, nodeId, value} where value is the component's own
// and nodeId the node's `_id` (the designer's) or `id`. The root's
// `props._openOrder` (a list of ids) comes first, in its order; the rest
// follow in document order. A placed component inside it (shared_ref) is
// not entered: its fields are its own. An id met a second time is dropped,
// so the first node keeps it.
function pg_co_open_fields($tree)
{
    $fields = array();
    $seen = array();
    $walk = function ($node) use (&$walk, &$fields, &$seen) {
        if (!is_array($node)) return;
        foreach (pg_co_node_open($node) as $entry) {
            if (isset($seen[$entry['id']])) continue;
            $seen[$entry['id']] = true;
            $fields[] = array(
                'id'     => $entry['id'],
                'kind'   => $entry['kind'],
                'label'  => $entry['label'],
                'nodeId' => pg_co_node_id($node),
                'value'  => pg_co_default_value($node, $entry['kind']),
            );
        }
        if (isset($node['type']) && $node['type'] === 'shared_ref') return;
        if (!empty($node['children']) && is_array($node['children'])) {
            foreach ($node['children'] as $child) $walk($child);
        }
    };
    $walk($tree);
    return pg_co_order_fields($fields, isset($tree['props']['_openOrder']) ? $tree['props']['_openOrder'] : null);
}

// A node's id: the designer's `_id`, else `id`, else ''.
function pg_co_node_id($node)
{
    foreach (array('_id', 'id') as $key) {
        if (isset($node[$key]) && (is_string($node[$key]) || is_int($node[$key]))) return (string) $node[$key];
    }
    return '';
}

// $fields put in the stored order: ids in $order first, then the rest as
// given. Entries of $order that name no field are skipped.
function pg_co_order_fields($fields, $order)
{
    if (!is_array($order) || empty($order) || empty($fields)) return $fields;
    $byId = array();
    foreach ($fields as $i => $field) $byId[$field['id']] = $i;
    $out = array();
    $used = array();
    foreach ($order as $id) {
        if (!is_string($id) || !isset($byId[$id]) || isset($used[$id])) continue;
        $used[$id] = true;
        $out[] = $fields[$byId[$id]];
    }
    foreach ($fields as $field) {
        if (!isset($used[$field['id']])) $out[] = $field;
    }
    return $out;
}

// A place's value made fit for its kind, or null when it cannot be:
// text, link and icon are strings; a picture is {src, alt} (a string is
// taken as its src; a missing alt stays null, the component's alt is kept);
// shown is a boolean.
function pg_co_clean_value($kind, $value)
{
    switch ($kind) {
        case 'text':
        case 'link':
        case 'icon':
            return is_string($value) ? $value : null;
        case 'shown':
            return is_bool($value) ? $value : null;
        case 'picture':
            if (is_string($value)) return array('src' => $value, 'alt' => null);
            if (!is_array($value) || !isset($value['src']) || !is_string($value['src'])) return null;
            $alt = (isset($value['alt']) && is_string($value['alt'])) ? $value['alt'] : null;
            return array('src' => $value['src'], 'alt' => $alt);
    }
    return null;
}

// The values a placing node keeps: `props._place` as an id => value array;
// anything else is no values.
function pg_co_place_values($ref)
{
    if (!is_array($ref) || !isset($ref['props']['_place']) || !is_array($ref['props']['_place'])) {
        return array();
    }
    return $ref['props']['_place'];
}

// A place's values as the save keeps them: valid ids only, each a text
// (at most 20,000 characters), a switch or a picture {src, alt?}; at most
// 200. Which field takes a value is not known here: drawing checks that
// (pg_co_safe_values()).
function pg_co_clean_place($values)
{
    if (!is_array($values)) return array();
    $out = array();
    foreach ($values as $id => $value) {
        $id = (string) $id;
        if (!pg_co_valid_field_id($id)) continue;
        if (is_bool($value)) {
            $out[$id] = $value;
        } elseif (is_string($value)) {
            $out[$id] = function_exists('mb_substr') ? mb_substr($value, 0, 20000, 'UTF-8') : substr($value, 0, 20000);
        } elseif (is_array($value) && isset($value['src']) && is_string($value['src'])) {
            $pic = array('src' => $value['src']);
            if (isset($value['alt']) && is_string($value['alt'])) $pic['alt'] = $value['alt'];
            $out[$id] = $pic;
        } else {
            continue;
        }
        if (count($out) >= 200) break;
    }
    return $out;
}

// Every placing node of a page tree keeps only what pg_co_clean_place()
// keeps, and a variant name pg_co_clean_variant_name() keeps; a place left
// with nothing keeps no `_place` (no `_variant`).
function pg_co_clean_tree_places(&$node)
{
    if (!is_array($node)) return;
    if (isset($node['type']) && $node['type'] === 'shared_ref') {
        if (isset($node['props']) && is_array($node['props']) && array_key_exists('_place', $node['props'])) {
            $clean = pg_co_clean_place($node['props']['_place']);
            if ($clean) $node['props']['_place'] = $clean;
            else unset($node['props']['_place']);
        }
        if (isset($node['props']) && is_array($node['props']) && array_key_exists('_variant', $node['props'])) {
            $name = pg_co_clean_variant_name($node['props']['_variant']);
            if ($name !== '') $node['props']['_variant'] = $name;
            else unset($node['props']['_variant']);
        }
        return;
    }
    if (!empty($node['children']) && is_array($node['children'])) {
        foreach ($node['children'] as &$child) pg_co_clean_tree_places($child);
        unset($child);
    }
}

// The component's tree as drawn at a place: each open field with a fitting
// value takes it; a node whose shown field is false is left out. Values for
// no open field, or unfit for their kind, are ignored. With nothing to
// apply the tree comes back as it was given.
function pg_co_apply($tree, $values)
{
    if (!is_array($tree) || !is_array($values) || empty($values)) return $tree;
    $fields = pg_co_open_fields($tree);
    if (empty($fields)) return $tree;

    $usable = array();
    foreach ($fields as $field) {
        if (!array_key_exists($field['id'], $values)) continue;
        $clean = pg_co_clean_value($field['kind'], $values[$field['id']]);
        if ($clean !== null) $usable[$field['id']] = $clean;
    }
    if (empty($usable)) return $tree;

    $seen = array();
    // Returns the node with its values, or null when it is not shown. The
    // root itself is never left out.
    $walk = function ($node, $root = false) use (&$walk, &$seen, $usable) {
        if (!is_array($node)) return $node;
        foreach (pg_co_node_open($node) as $entry) {
            if (isset($seen[$entry['id']])) continue;
            $seen[$entry['id']] = true;
            if (!array_key_exists($entry['id'], $usable)) continue;
            $value = $usable[$entry['id']];
            switch ($entry['kind']) {
                case 'shown':
                    if ($value === false && !$root) {
                        _pg_co_claim_ids($node, $seen);
                        return null;
                    }
                    break;
                case 'text':
                    $node['props']['text'] = $value;
                    break;
                case 'link':
                    $node['props']['href'] = $value;
                    break;
                case 'icon':
                    $node['props']['iconName'] = $value;
                    break;
                case 'picture':
                    $node['props']['src'] = $value['src'];
                    if ($value['alt'] !== null) $node['props']['alt'] = $value['alt'];
                    break;
            }
        }
        if (isset($node['type']) && $node['type'] === 'shared_ref') return $node;
        if (!empty($node['children']) && is_array($node['children'])) {
            $children = array();
            foreach ($node['children'] as $child) {
                $child = $walk($child);
                if ($child !== null) $children[] = $child;
            }
            $node['children'] = $children;
        }
        return $node;
    };
    return $walk($tree, true);
}

// A node left out of a place takes its own field ids and those of its
// subtree with it, as pg_co_open_fields() gives them to it: they are marked
// in $seen, so a later node with the same id (a duplicated element) never
// takes their values. A placed component inside is not entered.
function _pg_co_claim_ids($node, &$seen)
{
    if (!is_array($node)) return;
    foreach (pg_co_node_open($node) as $entry) $seen[$entry['id']] = true;
    if (isset($node['type']) && $node['type'] === 'shared_ref') return;
    if (!empty($node['children']) && is_array($node['children'])) {
        foreach ($node['children'] as $child) _pg_co_claim_ids($child, $seen);
    }
}

// The ids of a place's values that are left over: no open field has the id
// any more (closed, or its node removed), or the value no longer fits the
// field's kind. Given in the order the place keeps them.
function pg_co_leftovers($tree, $values)
{
    if (!is_array($values) || empty($values)) return array();
    $kinds = array();
    foreach (pg_co_open_fields($tree) as $field) $kinds[$field['id']] = $field['kind'];
    $left = array();
    foreach ($values as $id => $value) {
        $id = (string) $id;
        if (!isset($kinds[$id]) || pg_co_clean_value($kinds[$id], $value) === null) $left[] = $id;
    }
    return $left;
}

// Whether an address would run a script where a link or a picture prints
// it: javascript:, vbscript: or livescript:, and data: but for a picture's
// data:image/ (entities decoded once, blanks and control characters
// dropped, any case).
function pg_co_address_runs_script($address, $picture = false)
{
    $raw = html_entity_decode((string) $address, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $raw = strtolower(preg_replace('/[\x00-\x20]+/', '', $raw));
    if (preg_match('/^(?:javascript|vbscript|livescript):/', $raw)) return true;
    if (strpos($raw, 'data:') === 0) return !($picture && strpos($raw, 'data:image/') === 0);
    return false;
}

// A place's values as they may be drawn: only the ones an open field of
// $tree takes and that fit its kind, a link or picture that would run a
// script left out (the component's own is drawn), and a text's render
// markers (`<!--pg-`) and PHP open tags made inert, as a content editor's
// text is when it is saved (_pg_dm_neutralise_markers()).
function pg_co_safe_values($tree, $values)
{
    if (!is_array($values) || empty($values)) return array();
    $out = array();
    foreach (pg_co_open_fields($tree) as $field) {
        $id = $field['id'];
        if (!array_key_exists($id, $values) || array_key_exists($id, $out)) continue;
        $value = pg_co_clean_value($field['kind'], $values[$id]);
        if ($value === null) continue;
        if ($field['kind'] === 'link' && pg_co_address_runs_script($value)) continue;
        if ($field['kind'] === 'picture' && pg_co_address_runs_script($value['src'], true)) continue;
        if ($field['kind'] === 'text') {
            if (stripos($value, '<!--pg-') !== false) $value = str_ireplace('<!--pg-', '<!-- pg-', $value);
            $value = str_replace('<?', '&lt;?', $value);
        }
        $out[$id] = $value;
    }
    return $out;
}

// The marker a page body carries for a place's values, written just before
// the place's `<!--pg-shared-ref:ID-->` (_render_tree_node()):
// `<!--pg-place:BASE64URL-->`. Only entries with a valid id and a value of
// a kind's shape travel, and the place's variant name under `_variant`;
// none gives '' and the body stays as it was.
function pg_co_place_marker($values, $variant = '')
{
    $keep = array();
    $variant = pg_co_clean_variant_name($variant);
    if ($variant !== '') $keep['_variant'] = $variant;
    if (!is_array($values)) $values = array();
    foreach ($values as $id => $value) {
        if (!pg_co_valid_field_id((string) $id)) continue;
        if (is_string($value) || is_bool($value)) {
            $keep[(string) $id] = $value;
        } elseif (is_array($value) && isset($value['src']) && is_string($value['src'])) {
            $pic = array('src' => $value['src']);
            if (isset($value['alt']) && is_string($value['alt'])) $pic['alt'] = $value['alt'];
            $keep[(string) $id] = $pic;
        }
    }
    if (empty($keep)) return '';
    $json = json_encode($keep, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) return '';
    return '<!--pg-place:' . rtrim(strtr(base64_encode($json), '+/', '-_'), '=') . '-->';
}

// A place marker's payload back as values; array() when it does not read.
function pg_co_place_decode($payload)
{
    $json = base64_decode(strtr((string) $payload, '-_', '+/'), true);
    if ($json === false) return array();
    $values = json_decode($json, true);
    return is_array($values) ? $values : array();
}

// The tree a place draws, from the component's tree and a marker's
// decoded payload: its variant first (`_variant`), then its values, as
// they may be drawn. Nothing to apply gives the tree back as given.
function pg_co_place_tree($tree, $decoded)
{
    if (!is_array($decoded) || empty($decoded)) return $tree;
    $variant = (isset($decoded['_variant']) && is_string($decoded['_variant'])) ? $decoded['_variant'] : '';
    unset($decoded['_variant']);
    $tree = pg_co_apply_variant($tree, $variant);
    return pg_co_apply($tree, pg_co_safe_values($tree, $decoded));
}

// The nodes whose text a place gives, as drawn there (hidden ones left
// out): each array('id' => field id, 'node' => the node with the place's
// text). The site translation stores these texts with the page.
function pg_co_place_texts($tree, $values)
{
    $safe = pg_co_safe_values($tree, $values);
    $texts = array();
    foreach (pg_co_open_fields($tree) as $field) {
        if ($field['kind'] === 'text' && array_key_exists($field['id'], $safe)) $texts[$field['id']] = true;
    }
    if (empty($texts)) return array();
    // The walk of pg_co_apply() on the given tree: a node keeps the ids it
    // meets first, and a node the place hides takes its subtree's with it.
    $out = array();
    $seen = array();
    $walk = function ($node, $root) use (&$walk, &$out, &$seen, $texts, $safe) {
        if (!is_array($node)) return;
        $mine = array();
        foreach (pg_co_node_open($node) as $entry) {
            if (isset($seen[$entry['id']])) continue;
            $seen[$entry['id']] = true;
            if ($entry['kind'] === 'shown' && !$root && array_key_exists($entry['id'], $safe) && $safe[$entry['id']] === false) {
                _pg_co_claim_ids($node, $seen);
                return;
            }
            if ($entry['kind'] === 'text' && isset($texts[$entry['id']])) $mine[] = $entry['id'];
        }
        foreach ($mine as $id) {
            $drawn = $node;
            $drawn['props']['text'] = $safe[$id];
            $out[] = array('id' => $id, 'node' => $drawn);
        }
        if (isset($node['type']) && $node['type'] === 'shared_ref') return;
        if (!empty($node['children']) && is_array($node['children'])) {
            foreach ($node['children'] as $child) $walk($child, false);
        }
    };
    $walk($tree, true);
    return $out;
}

// ── Variants ─────────────────────────────────────────────────────────────
// A component may keep named sets of its nodes' classes and options: the
// root's `props._variants`, a list of {name, nodes} in the order added,
// `nodes` mapping a node's id (pg_co_node_id()) to the props the variant
// gives it. A place keeps only a variant's name, `props._variant` on its
// placing node. Texts, pictures, links and icons stay the component's.
// A renamed variant keeps its former names (`was`), so a place saved with
// one still draws it: the places live in other pages' trees, which a rename
// does not write.

// A variant's name as kept: trimmed, at most 60 characters; '' when it is
// not a string or is empty.
function pg_co_clean_variant_name($name)
{
    if (!is_string($name)) return '';
    $name = trim(preg_replace('/\s+/u', ' ', $name));
    if ($name === '') return '';
    return function_exists('mb_substr') ? mb_substr($name, 0, 60, 'UTF-8') : substr($name, 0, 60);
}

// Whether a variant may give a node this prop: a class or an option, never
// its content (text, picture, link, icon), its reference or the designer's
// own keys (`_…`).
function pg_co_variant_prop_ok($key)
{
    static $content = array(
        'text' => true, 'html' => true, 'content' => true, 'code' => true, 'src' => true, 'srcset' => true,
        'alt' => true, 'href' => true, 'iconName' => true, 'sharedId' => true, 'sharedName' => true, 'placement' => true,
    );
    return is_string($key) && $key !== '' && $key[0] !== '_' && !isset($content[$key]);
}

// One variant's props of a node, cleaned: allowed keys with a string,
// number or boolean value.
function pg_co_clean_variant_props($props)
{
    $out = array();
    if (!is_array($props)) return $out;
    foreach ($props as $key => $value) {
        $key = (string) $key;
        if (!pg_co_variant_prop_ok($key)) continue;
        if (is_string($value) || is_bool($value) || is_int($value) || is_float($value)) $out[$key] = $value;
    }
    return $out;
}

// A variant name as compared for "taken": any case.
function pg_co_variant_key($name)
{
    return function_exists('mb_strtolower') ? mb_strtolower($name, 'UTF-8') : strtolower($name);
}

// The component's variants, cleaned: a name (unique, any case; the first
// keeps it) and the nodes' props; a node with nothing left is dropped. At
// most 50. A variant renamed keeps `was`, its former names (at most 20): a
// former name that is a variant's name now, or that an earlier variant
// keeps, is dropped, and a variant with none has no `was`.
function pg_co_variants($tree)
{
    if (!is_array($tree) || !isset($tree['props']['_variants']) || !is_array($tree['props']['_variants'])) return array();
    $out = array();
    $names = array();
    $formers = array();
    foreach ($tree['props']['_variants'] as $entry) {
        if (!is_array($entry)) continue;
        $name = pg_co_clean_variant_name(isset($entry['name']) ? $entry['name'] : null);
        $key = pg_co_variant_key($name);
        if ($name === '' || isset($names[$key])) continue;
        $names[$key] = true;
        $nodes = array();
        if (isset($entry['nodes']) && is_array($entry['nodes'])) {
            foreach ($entry['nodes'] as $id => $props) {
                $props = pg_co_clean_variant_props($props);
                if ($props) $nodes[(string) $id] = $props;
            }
        }
        $out[] = array('name' => $name, 'nodes' => $nodes);
        $formers[] = (isset($entry['was']) && is_array($entry['was'])) ? $entry['was'] : array();
        if (count($out) >= 50) break;
    }
    $claimed = $names;
    foreach ($out as $i => $variant) {
        $was = array();
        foreach ($formers[$i] as $former) {
            $former = pg_co_clean_variant_name($former);
            $key = pg_co_variant_key($former);
            if ($former === '' || isset($claimed[$key])) continue;
            $claimed[$key] = true;
            $was[] = $former;
            if (count($was) >= 20) break;
        }
        if ($was) $out[$i]['was'] = $was;
    }
    return $out;
}

// The variant named $name, or null: a variant's name now first, then a
// former name of a renamed one (the same case), then a variant's name now
// in any case. The last keeps the places of a rename that changed only the
// case: its old name cannot stay a former name, and names are unique in
// any case, so it finds one variant at most.
function pg_co_variant($tree, $name)
{
    $name = pg_co_clean_variant_name($name);
    if ($name === '') return null;
    $list = pg_co_variants($tree);
    foreach ($list as $variant) {
        if ($variant['name'] === $name) return $variant;
    }
    foreach ($list as $variant) {
        if (!empty($variant['was']) && in_array($name, $variant['was'], true)) return $variant;
    }
    $key = pg_co_variant_key($name);
    foreach ($list as $variant) {
        if (pg_co_variant_key($variant['name']) === $key) return $variant;
    }
    return null;
}

// How many props a variant changes ("6 changes").
function pg_co_variant_changes($variant)
{
    $n = 0;
    if (is_array($variant) && isset($variant['nodes']) && is_array($variant['nodes'])) {
        foreach ($variant['nodes'] as $props) $n += is_array($props) ? count($props) : 0;
    }
    return $n;
}

// The variant name a placing node keeps, or ''.
function pg_co_place_variant($ref)
{
    return (is_array($ref) && isset($ref['props']['_variant'])) ? pg_co_clean_variant_name($ref['props']['_variant']) : '';
}

// The component's tree in variant $name: each node the variant names takes
// its props (a placed component inside is not entered). An unknown name, or
// a variant that changes nothing, gives the tree back as given.
function pg_co_apply_variant($tree, $name)
{
    $variant = is_array($tree) ? pg_co_variant($tree, $name) : null;
    if (!$variant || empty($variant['nodes'])) return $tree;
    $nodes = $variant['nodes'];
    $walk = function ($node, $top) use (&$walk, $nodes) {
        if (!is_array($node)) return $node;
        $id = pg_co_node_id($node);
        if ($id !== '' && isset($nodes[$id])) {
            if (!isset($node['props']) || !is_array($node['props'])) $node['props'] = array();
            foreach ($nodes[$id] as $key => $value) $node['props'][$key] = $value;
        }
        if (!$top && isset($node['type']) && $node['type'] === 'shared_ref') return $node;
        if (!empty($node['children']) && is_array($node['children'])) {
            foreach ($node['children'] as $i => $child) $node['children'][$i] = $walk($child, false);
        }
        return $node;
    };
    return $walk($tree, true);
}

// What a variant keeps after it was edited: for each node of $edited that
// $default has too, the allowed props whose value differs. A string or a
// switch the edit removed is kept as '' or false.
function pg_co_variant_nodes($default, $edited)
{
    $before = array();
    $collect = function ($node, $top) use (&$collect, &$before) {
        if (!is_array($node)) return;
        $id = pg_co_node_id($node);
        if ($id !== '') $before[$id] = (isset($node['props']) && is_array($node['props'])) ? $node['props'] : array();
        if (!$top && isset($node['type']) && $node['type'] === 'shared_ref') return;
        if (!empty($node['children']) && is_array($node['children'])) {
            foreach ($node['children'] as $child) $collect($child, false);
        }
    };
    $collect($default, true);
    $out = array();
    $diff = function ($node, $top) use (&$diff, &$out, $before) {
        if (!is_array($node)) return;
        $id = pg_co_node_id($node);
        if ($id !== '' && isset($before[$id])) {
            $old = pg_co_clean_variant_props($before[$id]);
            $new = pg_co_clean_variant_props(isset($node['props']) ? $node['props'] : array());
            $changed = array();
            foreach ($new as $key => $value) {
                if (!array_key_exists($key, $old) || $old[$key] !== $value) $changed[$key] = $value;
            }
            foreach ($old as $key => $value) {
                if (array_key_exists($key, $new)) continue;
                if (is_string($value) && $value !== '') $changed[$key] = '';
                elseif ($value === true) $changed[$key] = false;
            }
            if ($changed) $out[$id] = $changed;
        }
        if (!$top && isset($node['type']) && $node['type'] === 'shared_ref') return;
        if (!empty($node['children']) && is_array($node['children'])) {
            foreach ($node['children'] as $child) $diff($child, false);
        }
    };
    $diff($edited, true);
    return $out;
}
