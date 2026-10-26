<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// A module of functions.php: where a system widget's blocks come from.
//
// Loaded by functions.php through require_once, never on its own.
//
// One rule: a part the designer placed always wins, and a renderer adds its
// own copy of a block only when the saved tree places none of its tokens
// (pg_sw_placed()).
//
// Default parts: a token that used to stand for a block of markup the
// renderer built (the order view's timeline and status badge) is drawn by a
// part made of real design elements, a list and elements with conditions,
// which the designer can select and change. A saved tree still binding the
// old token is given the part where the widget's tree is read for a render
// (_expand_system_widgets()); the editor gives it the same part when the
// widget loads (_sdDefaultParts() in style_designer.js), and the next save
// stores it.
if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// Whether the widget tree $tree (decoded, or its JSON) places any of
// $tokens: as a binding, a literal ^^token^^ or an attribute {{token}}. A
// value condition names a token without placing it, so conditions are
// taken out first (pg_sw_cond_tree_json()). A token is matched whole:
// '__returns' is not placed by '__returns_list'.
function pg_sw_placed($tree, $tokens)
{
    $json = pg_sw_cond_tree_json($tree);
    foreach ((array)$tokens as $token) {
        $token = (string)$token;
        if ($token === '' || strpos($json, $token) === false) continue;
        if (preg_match('/(?<![A-Za-z0-9_])' . preg_quote($token, '/') . '(?![A-Za-z0-9_])/', $json)) return true;
    }
    return false;
}

// The tokens drawn by a default part, by widget kind: pairs of the token
// and the function that builds the part's nodes from the widget's settings.
// Old names, so no renderer fills them and the editor offers none (pairs,
// not keys, so tools/check_bindings.php does not read them as filled).
function pg_sw_default_part_tokens()
{
    return array(
        'order_view' => array(
            array('__timeline', 'pg_sw_part_order_timeline'),
            array('__order_status_badge', 'pg_sw_part_order_status_badge'),
        ),
    );
}

// $tree_json with every node that draws an old token of $kind given its
// default part, and in an e-mail the parts' look written inline. JSON that
// names none of the tokens comes back as it is.
function pg_sw_default_parts_apply($tree_json, $kind, $cfg = array(), $email = false)
{
    $tree_json = (string)$tree_json;
    $all   = pg_sw_default_part_tokens();
    $parts = array();
    foreach (isset($all[$kind]) ? $all[$kind] : array() as $pair) $parts[$pair[0]] = $pair[1];
    $found = false;
    foreach ($parts as $token => $builder) {
        if (strpos($tree_json, $token) !== false) { $found = true; break; }
    }
    $mail = $email && strpos($tree_json, 'pg-ov-timeline') !== false;
    if (!$found && !$mail) return $tree_json;

    $tree = json_decode($tree_json, true);
    if (!is_array($tree)) return $tree_json;
    $changed = $found ? pg_sw_default_parts_tree($tree, $parts, is_array($cfg) ? $cfg : array()) : false;
    if ($email) $changed = pg_sw_parts_mail_styles($tree) || $changed;
    if (!$changed) return $tree_json;
    $out = json_encode($tree, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return ($out === false) ? $tree_json : $out;
}

// Gives every node under $node that draws a token of $parts its part.
// Returns whether anything changed.
function pg_sw_default_parts_tree(&$node, $parts, $cfg)
{
    if (!is_array($node)) return false;
    $token = pg_sw_part_token_of($node, array_keys($parts));
    if ($token !== '') {
        $host = pg_sw_part_host($node);
        $host['children'] = array_merge(
            call_user_func($parts[$token], $cfg),
            (isset($host['children']) && is_array($host['children'])) ? $host['children'] : array()
        );
        $node = $host;
        return true;
    }
    $changed = false;
    if (isset($node['children']) && is_array($node['children'])) {
        foreach ($node['children'] as $i => $child) {
            if (pg_sw_default_parts_tree($child, $parts, $cfg)) {
                $node['children'][$i] = $child;
                $changed = true;
            }
        }
    }
    return $changed;
}

// The token of $tokens that $node draws: its text binding, or a text that is
// the token alone (^^token^^, as older layouts wrote it). '' for none.
function pg_sw_part_token_of($node, $tokens)
{
    $props = (isset($node['props']) && is_array($node['props'])) ? $node['props'] : array();
    if (isset($props['_bindings']['text']) && in_array($props['_bindings']['text'], $tokens, true)) {
        return $props['_bindings']['text'];
    }
    if (isset($props['text']) && is_string($props['text'])
        && preg_match('/^\s*\^\^([A-Za-z0-9_]+)\^\^\s*$/', $props['text'], $m)
        && in_array($m[1], $tokens, true)) {
        return $m[1];
    }
    return '';
}

// $node as an element that holds a part: its text and text binding go, a
// content node (a paragraph, a heading, a span) becomes the element it
// drew, its classes, attributes, name and other bindings kept.
function pg_sw_part_host($node)
{
    $props = (isset($node['props']) && is_array($node['props'])) ? $node['props'] : array();
    unset($props['text']);
    foreach (array('_bindings', '_bindParams', '_bindFormats') as $key) {
        if (!isset($props[$key]) || !is_array($props[$key])) continue;
        unset($props[$key]['text']);
        if (!$props[$key]) unset($props[$key]);
    }
    $type = isset($node['type']) ? $node['type'] : '';
    if ($type === 'content') {
        $ct  = isset($props['contentType']) ? $props['contentType'] : '';
        $tag = 'div';
        if ($ct === 'paragraph') $tag = 'p';
        elseif ($ct === 'span' || $ct === 'text') $tag = 'span';
        elseif ($ct === 'heading') $tag = (isset($props['tag']) && preg_match('/^h[1-6]$/i', (string)$props['tag'])) ? strtolower($props['tag']) : 'h2';
        // The classes _render_content_html() writes for these two props.
        $cls = array();
        if (!empty($props['lead']) && $ct === 'paragraph') $cls[] = 'lead';
        if (!empty($props['align'])) $cls[] = 'text-' . $props['align'];
        if (isset($props['cssClass']) && (string)$props['cssClass'] !== '') $cls[] = (string)$props['cssClass'];
        unset($props['contentType'], $props['lead'], $props['align']);
        $props['tag'] = $tag;
        $props['cssClass'] = implode(' ', $cls);
        $type = 'semantic';
    } elseif ($type !== 'semantic') {
        // Any other node moves into a <div> that takes its place.
        $node['props'] = $props;
        return array('type' => 'semantic', 'props' => array('tag' => 'div'), 'children' => array($node));
    }
    $node['type']  = $type;
    $node['props'] = $props;
    if (!isset($node['children']) || !is_array($node['children'])) $node['children'] = array();
    return $node;
}

// One element of a part: a semantic node of $tag with $class, the
// editor's $name, $props on top and $children.
function pg_sw_part_el($tag, $class, $name, $props = array(), $children = array())
{
    return array(
        'type'     => 'semantic',
        'props'    => array_merge(array('tag' => $tag, 'cssClass' => $class, 'customName' => $name), $props),
        'children' => $children,
    );
}

// The props of an element drawn only when $token passes $op ($value).
function pg_sw_part_when($token, $op, $value = '')
{
    $param = array('op' => $op);
    if ($value !== '') $param['value'] = $value;
    return array('_bindings' => array('show_if' => $token), '_bindParams' => array('show_if' => $param));
}

// The order timeline: one item per step of the order (the order.steps
// list), its icon and colour chosen by the step's kind, an estimated date
// greyed, the label, a cancellation's reason after a dash, the date. The
// look is in pg_carousel_layout.css (.pg-ov-timeline).
function pg_sw_part_order_timeline($cfg = array())
{
    $icon = function ($colour, $bi, $props = array()) {
        return pg_sw_part_el('span', 'pg-ov-timeline-icon bg-' . $colour, 'Icon', $props, array(
            pg_sw_part_el('i', 'bi ' . $bi, 'Icon'),
        ));
    };
    $kind = function ($kind, $children) {
        return pg_sw_part_el('span', '', 'Step: ' . $kind, pg_sw_part_when('__step_kind', 'eq', $kind), $children);
    };
    $dated = function ($colour, $bi) use ($icon) {
        return array(
            $icon($colour, $bi, pg_sw_part_when('__step_is_estimate', 'empty')),
            $icon('secondary', $bi, pg_sw_part_when('__step_is_estimate', 'not_empty')),
        );
    };
    $text = function ($tag, $class, $name, $token, $sample) {
        return pg_sw_part_el($tag, $class, $name, array('text' => $sample, '_bindings' => array('text' => $token)));
    };
    return array(
        pg_sw_part_el('ul', 'pg-ov-timeline', 'Order timeline', array(), array(
            array('type' => 'loop_area', 'props' => array('source' => 'order.steps'), 'children' => array(
                pg_sw_part_el('li', 'pg-ov-timeline-item', 'Step', array(), array(
                    $kind('created',   array($icon('primary', 'bi-receipt'))),
                    $kind('paid',      array($icon('success', 'bi-credit-card'))),
                    $kind('shipped',   $dated('info', 'bi-truck')),
                    $kind('delivered', $dated('success', 'bi-box-seam')),
                    $kind('cancelled', array($icon('danger', 'bi-x-circle'))),
                    pg_sw_part_el('div', 'pg-ov-timeline-label', 'Step', array(), array(
                        $text('span', '', 'Step', '__step_label', 'Order Created'),
                        pg_sw_part_el('span', '', 'Dash', array_merge(array('text' => ' — '), pg_sw_part_when('__step_note', 'not_empty'))),
                        $text('span', '', 'Note (the reason of a cancellation)', '__step_note', ''),
                    )),
                    $text('div', 'pg-ov-timeline-ts', 'Date', '__step_date', '20.10.2026 10:00'),
                )),
            )),
        )),
    );
}

// The stages of an order's status badge, in the order the part lists them
// (_pg_order_status_stage()).
function pg_sw_order_status_stages()
{
    return array('complete', 'pending', 'shipped', 'cancelled', 'refunded', 'default');
}

// The order's status badge: one badge per stage of the status, drawn when
// the order is at that stage, with the classes the widget's settings gave
// that stage (_pg_order_status_badge_class()). The text is the status as
// the order view shows it.
function pg_sw_part_order_status_badge($cfg = array())
{
    $out = array();
    foreach (pg_sw_order_status_stages() as $stage) {
        $out[] = pg_sw_part_el('span', _pg_order_status_badge_class($stage, $cfg), 'Status: ' . $stage, array_merge(
            array('text' => 'Completed', '_bindings' => array('text' => '__order_status_text', 'show_if' => '__order_status_stage')),
            array('_bindParams' => array('show_if' => array('op' => 'eq', 'value' => $stage)))
        ));
    }
    return $out;
}

// The look of the parts in an e-mail, where the stylesheet may be dropped:
// written inline before the element's own style, which wins on a repeat.
// Plain boxes and colours only: no flex, no positioning, no pseudo element.
function pg_sw_parts_mail_style_map()
{
    return array(
        'pg-ov-timeline'       => 'list-style:none;padding:0;margin:0',
        'pg-ov-timeline-item'  => 'padding:0 0 12px 0',
        'pg-ov-timeline-icon'  => 'display:inline-block;width:12px;height:12px;border-radius:50%;margin-right:8px;vertical-align:middle',
        'pg-ov-timeline-label' => 'display:inline;font-weight:600',
        'pg-ov-timeline-ts'    => 'color:#6c757d;font-size:14px;padding-left:20px',
    );
}

// Writes the e-mail look on every node under $node carrying a class of the
// map, an icon's colour from its bg-* class. Returns whether anything
// changed.
function pg_sw_parts_mail_styles(&$node)
{
    if (!is_array($node)) return false;
    static $colours = array(
        'primary' => '#0d6efd', 'success' => '#198754', 'info' => '#0dcaf0',
        'danger'  => '#dc3545', 'secondary' => '#6c757d', 'warning' => '#ffc107',
    );
    $changed = false;
    $classes = isset($node['props']['cssClass']) ? preg_split('/\s+/', trim((string)$node['props']['cssClass'])) : array();
    $css = '';
    $map = pg_sw_parts_mail_style_map();
    foreach ($classes as $c) {
        if (isset($map[$c])) $css .= $map[$c] . ';';
        if (strpos($c, 'bg-') === 0 && isset($colours[substr($c, 3)]) && in_array('pg-ov-timeline-icon', $classes, true)) {
            $css .= 'background-color:' . $colours[substr($c, 3)] . ';';
        }
    }
    if ($css !== '') {
        $attrs = (isset($node['props']['_attrs']) && is_array($node['props']['_attrs'])) ? $node['props']['_attrs'] : array();
        $done = false;
        foreach ($attrs as $i => $a) {
            if (is_array($a) && isset($a['name']) && strtolower((string)$a['name']) === 'style') {
                $attrs[$i]['value'] = $css . (isset($a['value']) ? (string)$a['value'] : '');
                $done = true;
                break;
            }
        }
        if (!$done) $attrs[] = array('name' => 'style', 'value' => rtrim($css, ';'));
        $node['props']['_attrs'] = $attrs;
        $changed = true;
    }
    if (isset($node['children']) && is_array($node['children'])) {
        foreach ($node['children'] as $i => $child) {
            if (pg_sw_parts_mail_styles($child)) {
                $node['children'][$i] = $child;
                $changed = true;
            }
        }
    }
    return $changed;
}
