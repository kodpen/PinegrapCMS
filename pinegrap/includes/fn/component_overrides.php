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
                    if ($value === false && !$root) return null;
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
