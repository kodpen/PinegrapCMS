<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// A module of functions.php: the read-only parts of a detail widget's record
// placed outside the widget box.
//
// Loaded by functions.php through require_once, never on its own.
//
// A `widget_part` node in the page tree names a detail widget the page
// places (props.widgetId) and one of its read-only parts (props.part); its
// children are the part's design elements. The page body stores it as one
// marker carrying the children:
//
//   <!--pg-widget-part:SID:PART:BASE64URL-->
//
// _expand_system_widgets() gives those children to the widget's own render,
// at the end of its tree, inside the widget scope: drawn in the same request
// from the same record, between two marks of this request,
//
//   <!--pg-wpart:NONCE:N-->  the part  <!--/pg-wpart:NONCE:N-->
//
// which are taken out of the widget's finished output and put where the
// marker stood. A part whose widget is not drawn, or which the widget does
// not draw (no record), leaves nothing. Controls and anything else that posts
// stay inside the widget: they are dropped from a part.

/**
 * The read-only parts each detail widget kind can draw outside its box.
 *
 * @return array  kind => list of part names
 */
function pg_sw_outside_part_kinds()
{
    return array(
        'catalog_item_view' => array('price', 'gallery', 'breadcrumb', 'rating', 'stock'),
    );
}

/**
 * True when $part is a part $kind draws outside its box; with $kind '' when
 * any kind draws it.
 *
 * @param string $kind
 * @param string $part
 * @return bool
 */
function pg_sw_outside_part_known($kind, $part)
{
    if (!is_string($part) || $part === '') return false;
    foreach (pg_sw_outside_part_kinds() as $k => $parts) {
        if ($kind !== '' && $k !== $kind) continue;
        if (in_array($part, $parts, true)) return true;
    }
    return false;
}

/**
 * The content types a part may hold: texts, links, pictures, icons and
 * markup. Messages, variant pickers, comment blocks, regions and server code
 * stay out.
 *
 * @return array
 */
function pg_sw_outside_part_content_types()
{
    return array('heading', 'paragraph', 'span', 'text', 'link', 'image', 'carousel', 'icon', 'divider', 'spacer', 'svg', 'custom_html');
}

/**
 * $nodes without what a read-only part may not hold, at any depth: form
 * controls, nodes bound to an action, a posted value or a section, widgets,
 * shared components, regions, a main loop (a list with a source stays) and
 * content types outside pg_sw_outside_part_content_types().
 *
 * @param mixed $nodes  list of tree nodes
 * @return array
 */
function pg_sw_outside_part_clean($nodes)
{
    if (!is_array($nodes)) return array();
    static $controls = array('form', 'input', 'select', 'textarea', 'button', 'option', 'optgroup');
    static $types    = array('container', 'row', 'col', 'semantic', 'content', 'component', 'loop_area');
    $kept = array();
    foreach ($nodes as $node) {
        if (!is_array($node) || !isset($node['type']) || !in_array($node['type'], $types, true)) continue;
        $props = (isset($node['props']) && is_array($node['props'])) ? $node['props'] : array();
        if ($node['type'] === 'loop_area' && !pg_sw_list_is_sourced($node)) continue;
        if ($node['type'] === 'semantic') {
            $tag = isset($props['tag']) ? strtolower((string)$props['tag']) : 'div';
            if (in_array($tag, $controls, true)) continue;
        }
        if ($node['type'] === 'content') {
            $ct = isset($props['contentType']) ? (string)$props['contentType'] : '';
            if (!in_array($ct, pg_sw_outside_part_content_types(), true)) continue;
        }
        $bind = (isset($props['_bindings']) && is_array($props['_bindings'])) ? $props['_bindings'] : array();
        if (!empty($bind['action']) || !empty($bind['value']) || !empty($bind['section'])) continue;
        $node['children'] = pg_sw_outside_part_clean(isset($node['children']) ? $node['children'] : array());
        $kept[] = $node;
    }
    return $kept;
}

/**
 * The page body's marker for a `widget_part` node drawn outside a widget;
 * '' when it names no widget or no known part.
 *
 * @param array $props     the node's props (widgetId, part)
 * @param mixed $children  the node's children
 * @return string
 */
function pg_sw_outside_part_marker($props, $children)
{
    $sid  = (is_array($props) && isset($props['widgetId'])) ? (int)$props['widgetId'] : 0;
    $part = (is_array($props) && isset($props['part']) && is_string($props['part'])) ? $props['part'] : '';
    if ($sid <= 0 || !pg_sw_outside_part_known('', $part)) return '';
    $json = json_encode(pg_sw_outside_part_clean($children), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) return '';
    return '<!--pg-widget-part:' . $sid . ':' . $part . ':' . rtrim(strtr(base64_encode($json), '+/', '-_'), '=') . '-->';
}

/**
 * The part markers in $html, numbered from 0 in the order they stand.
 *
 * @param string $html
 * @return array  list of array('marker', 'sid', 'part', 'children')
 */
function pg_sw_outside_parts_find($html)
{
    if (!is_string($html) || strpos($html, '<!--pg-widget-part:') === false) return array();
    if (!preg_match_all('/<!--pg-widget-part:(\d+):([a-z_]+):([A-Za-z0-9_-]*)-->/', $html, $m, PREG_SET_ORDER)) return array();
    $found = array();
    foreach ($m as $hit) {
        $json     = base64_decode(strtr($hit[3], '-_', '+/'), true);
        $children = ($json === false) ? null : json_decode($json, true);
        $found[] = array(
            'marker'   => $hit[0],
            'sid'      => (int)$hit[1],
            'part'     => $hit[2],
            'children' => pg_sw_outside_part_clean($children),
        );
    }
    return $found;
}

/**
 * Takes from $pending the parts of widget $sid that a $kind widget draws,
 * the first time that widget is drawn: a second placement of the widget
 * gets none.
 *
 * @param array  $pending  pg_sw_outside_parts_find() result; the taken ones leave it
 * @param int    $sid
 * @param string $kind
 * @return array  number => part
 */
function pg_sw_outside_parts_for(&$pending, $sid, $kind)
{
    $own = array();
    foreach ($pending as $n => $part) {
        if ($part['sid'] !== (int)$sid || !pg_sw_outside_part_known((string)$kind, $part['part'])) continue;
        $own[$n] = $part;
        unset($pending[$n]);
    }
    return $own;
}

/**
 * $tree_json with each of $parts added to the end of its root as a
 * `widget_part` node carrying its number; $tree_json as it is without parts
 * or when it does not read.
 *
 * @param string $tree_json
 * @param array  $parts  number => part (pg_sw_outside_parts_for())
 * @return string
 */
function pg_sw_outside_parts_tree($tree_json, $parts)
{
    if (!$parts) return $tree_json;
    $tree = json_decode((string)$tree_json, true);
    if (!is_array($tree)) return $tree_json;
    if (!isset($tree['children']) || !is_array($tree['children'])) $tree['children'] = array();
    foreach ($parts as $n => $part) {
        $tree['children'][] = array(
            'type'     => 'widget_part',
            'props'    => array('part' => $part['part'], '_partNo' => (int)$n),
            'children' => $part['children'],
        );
    }
    $json = json_encode($tree, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return ($json === false) ? $tree_json : $json;
}

/**
 * The two marks around part $n in a widget's output.
 *
 * @param string $nonce
 * @param int    $n
 * @return array  array(open, close)
 */
function pg_sw_outside_part_marks($nonce, $n)
{
    return array('<!--pg-wpart:' . $nonce . ':' . (int)$n . '-->', '<!--/pg-wpart:' . $nonce . ':' . (int)$n . '-->');
}

/**
 * $html, a widget's finished output, without the parts drawn in it; each
 * part's markup goes to $drawn under its number.
 *
 * @param string $html
 * @param array  $numbers  the part numbers this render drew
 * @param array  $drawn    number => markup, added to
 * @param string $nonce
 * @return string
 */
function pg_sw_outside_parts_take($html, $numbers, &$drawn, $nonce)
{
    foreach ($numbers as $n) {
        list($open, $close) = pg_sw_outside_part_marks($nonce, $n);
        $from = strpos($html, $open);
        if ($from === false) continue;
        $to = strpos($html, $close, $from);
        if ($to === false) continue;
        $drawn[$n] = trim(substr($html, $from + strlen($open), $to - $from - strlen($open)));
        $html = substr($html, 0, $from) . substr($html, $to + strlen($close));
    }
    return $html;
}

/**
 * $html with each marker of $parts replaced by its drawn markup, or by
 * nothing when the widget drew none.
 *
 * @param string $html
 * @param array  $parts  number => part
 * @param array  $drawn  number => markup
 * @return string
 */
function pg_sw_outside_parts_place($html, $parts, $drawn)
{
    foreach ($parts as $n => $part) {
        $pos = strpos($html, $part['marker']);
        if ($pos === false) continue;
        $html = substr($html, 0, $pos) . (isset($drawn[$n]) ? $drawn[$n] : '') . substr($html, $pos + strlen($part['marker']));
    }
    return $html;
}

/**
 * $html without the part markers no widget drew: the page holds no such
 * widget, or not one of a kind that draws the part.
 *
 * @param string $html
 * @return string
 */
function pg_sw_outside_parts_sweep($html)
{
    if (!is_string($html) || strpos($html, '<!--pg-widget-part:') === false) return $html;
    return preg_replace('/<!--pg-widget-part:\d+:[a-z_]+:[A-Za-z0-9_-]*-->/', '', $html);
}
