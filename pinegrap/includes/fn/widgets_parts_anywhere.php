<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// A module of functions.php: read-only parts of the page's record placed
// outside the detail widget that draws them.
//
// Loaded by functions.php through require_once, never on its own.
//
// A node of the page outside any widget whose props.partOf names the shared
// id of a detail widget (product, event or form record view) is a part of
// that widget: a price block, a gallery, a breadcrumb, a rating, a stock
// line. The stored body keeps it as a marker holding its subtree:
//   <!--pg-wpart:SID:SPEC-->
// SPEC is the subtree as URL-safe base64 JSON. On every request
// _expand_system_widgets() hands the subtrees to their widget: each is added
// to the end of the widget's main loop between two markers, so the
// widget's renderer draws it in the same call, from the same record, with
// the same tokens, conditions and finish pass. The drawn span is then cut
// out of the widget's output and put where the part's marker stands. No
// second query and no new value: a part shows what its widget shows, and
// nothing when the widget draws no record. Parts never post: a subtree
// holding a control (pg_sw_away_read_only()) is not drawn.

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// The widget kinds a part may be tied to: the views of the record the
// address opens (page_facts.php).
function pg_sw_away_kinds()
{
    return array('catalog_item_view', 'form_item_view', 'calendar_event_view');
}

// The widget a part node names, or 0 for a node that is no part.
function pg_sw_away_of($node)
{
    if (!is_array($node) || !isset($node['props']) || !is_array($node['props'])) return 0;
    $of = isset($node['props']['partOf']) ? $node['props']['partOf'] : 0;
    return (is_int($of) || (is_string($of) && ctype_digit($of))) ? max(0, (int)$of) : 0;
}

// Whether the subtree $node shows only: no form, control, button that
// posts, server-built section, messages node, region, shared component,
// main loop or code. A part that sends something stays inside its widget,
// where its liveform names and messages belong.
function pg_sw_away_read_only($node)
{
    if (!is_array($node)) return true;
    $type  = isset($node['type']) ? (string)$node['type'] : '';
    $props = (isset($node['props']) && is_array($node['props'])) ? $node['props'] : array();
    if (in_array($type, array('shared_ref', 'region', 'recipient_loop_area'), true)) return false;
    if ($type === 'loop_area' && !pg_sw_list_is_sourced($node)) return false;
    if ($type === 'semantic') {
        $tag = strtolower(isset($props['tag']) ? (string)$props['tag'] : '');
        if (in_array($tag, array('form', 'input', 'select', 'textarea', 'button', 'option', 'script'), true)) return false;
    }
    $content = strtolower(isset($props['contentType']) ? (string)$props['contentType'] : '');
    if (in_array($content, array('messages', 'custom_php', 'comments_block', 'applied_offers', 'variant_attr', 'language_switcher'), true)) return false;
    $bindings = (isset($props['_bindings']) && is_array($props['_bindings'])) ? $props['_bindings'] : array();
    if (!empty($bindings['action']) || !empty($bindings['section'])) return false;
    if (isset($node['children']) && is_array($node['children'])) {
        foreach ($node['children'] as $child) {
            if (!pg_sw_away_read_only($child)) return false;
        }
    }
    return true;
}

// The marker the stored body keeps for the part node $node: its subtree
// without partOf, so the widget draws it as one of its own nodes. A node
// that is no part, or holds a control, gives ''.
function pg_sw_away_marker($node)
{
    $sid = pg_sw_away_of($node);
    if ($sid <= 0 || !pg_sw_away_read_only($node)) return '';
    unset($node['props']['partOf']);
    $spec = rtrim(strtr(base64_encode(json_encode($node)), '+/', '-_'), '=');
    return '<!--pg-wpart:' . $sid . ':' . $spec . '-->';
}

// The part markers of $html in order: each array('sid' => ..., 'node' =>
// decoded subtree, or null when it cannot be read).
function pg_sw_away_collect($html)
{
    $out = array();
    if (strpos((string)$html, '<!--pg-wpart:') === false) return $out;
    if (!preg_match_all('/<!--pg-wpart:(\d+):([A-Za-z0-9_-]+)-->/', $html, $m, PREG_SET_ORDER)) return $out;
    foreach ($m as $hit) {
        $b64 = strtr($hit[2], '-_', '+/');
        $pad = strlen($b64) % 4;
        if ($pad) $b64 .= str_repeat('=', 4 - $pad);
        $json = base64_decode($b64, true);
        $node = ($json === false) ? null : json_decode($json, true);
        if (!is_array($node) || !isset($node['type']) || !pg_sw_away_read_only($node)) $node = null;
        $out[] = array('sid' => (int)$hit[1], 'node' => $node);
    }
    return $out;
}

// The marker nodes around a part inside its widget's tree.
function pg_sw_away_fence($key, $end)
{
    return array('type' => 'content', 'props' => array('contentType' => 'custom_html',
        'html' => '<!--pg-wpart-' . ($end ? 'e' : 's') . ':' . $key . '-->'), 'children' => array());
}

// $tree_json with each part of $parts (key => subtree) added to the end of
// the main loop, fenced by its key; a tree without a main loop takes them
// at its end. JSON that cannot be read comes back as it is.
function pg_sw_away_inject($tree_json, $parts)
{
    if (!$parts) return $tree_json;
    $tree = json_decode((string)$tree_json, true);
    if (!is_array($tree)) return $tree_json;
    $nodes = array();
    foreach ($parts as $key => $node) {
        $nodes[] = pg_sw_away_fence($key, false);
        $nodes[] = $node;
        $nodes[] = pg_sw_away_fence($key, true);
    }
    $loop = &pg_sw_main_loop_ref($tree);
    if ($loop !== null) {
        $loop['children'] = array_merge(isset($loop['children']) && is_array($loop['children']) ? $loop['children'] : array(), $nodes);
    } else {
        $tree['children'] = array_merge(isset($tree['children']) && is_array($tree['children']) ? $tree['children'] : array(), $nodes);
    }
    unset($loop);
    return json_encode($tree);
}

// The spans of $keys cut out of the widget output $html: key => the part
// as drawn. A key the widget did not draw (no record, a condition cut it)
// is missing.
function pg_sw_away_take(&$html, $keys)
{
    $out = array();
    foreach ($keys as $key) {
        $open  = '<!--pg-wpart-s:' . $key . '-->';
        $close = '<!--pg-wpart-e:' . $key . '-->';
        $from = strpos($html, $open);
        if ($from === false) continue;
        $to = strpos($html, $close, $from);
        if ($to === false) {
            $html = substr($html, 0, $from) . substr($html, $from + strlen($open));
            continue;
        }
        $out[$key] = trim(substr($html, $from + strlen($open), $to - $from - strlen($open)));
        $cut_from = $from;
        $cut_to = $to + strlen($close);
        // The line breaks the renderer wrote around the fence go with it.
        while ($cut_from > 0 && ($html[$cut_from - 1] === ' ' || $html[$cut_from - 1] === "\n")) $cut_from--;
        $html = substr($html, 0, $cut_from) . substr($html, $cut_to);
    }
    return $out;
}

// $html with the n-th part marker replaced by $spans[$prefix . n], or by
// nothing when its widget did not draw it. With $sids, a marker of another
// widget stays for a later pass; without, every marker goes.
function pg_sw_away_place($html, $spans, $prefix = '', $sids = null)
{
    if (strpos((string)$html, '<!--pg-wpart:') === false) return $html;
    $n = 0;
    return preg_replace_callback('/<!--pg-wpart:(\d+):[A-Za-z0-9_-]+-->/', function ($m) use (&$n, $spans, $prefix, $sids) {
        $key = $prefix . ($n++);
        if (is_array($sids) && !in_array((int)$m[1], $sids, true)) return $m[0];
        return isset($spans[$key]) ? $spans[$key] : '';
    }, $html);
}
