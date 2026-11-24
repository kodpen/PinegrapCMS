<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The Figma converter: one frame of a Figma file, as the REST API answers
 * GET /v1/files/:key/nodes, turned into a designer tree.
 *
 * Pure: no network call, no database, no file. Auto layout becomes flex
 * (horizontal, vertical, wrapped) or CSS grid on semantic elements, a frame
 * without auto layout places its children absolutely, text becomes headings,
 * paragraphs and links with its style and styled runs. What a later step has
 * to fetch or decide is listed beside the tree, keyed by the designer node's
 * _id: pictures to export (image fills, vectors and icon groups), component
 * instances (one component on several frames is a shared component), the
 * colours and text styles with the file's style names, the variables bound
 * to nodes (readable only where the Variables API answers), and a report of
 * what could not be carried, as codes the screen words.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/**
 * The frame $id of a GET /v1/files/:key/nodes answer: its document and the
 * styles and components maps that came with it; null when the answer does
 * not hold it. $id may be written as in a link (12-3) or as the API writes
 * it (12:3).
 */
function pg_figma_answer_frame($answer, $id)
{
    if (!is_array($answer) || empty($answer['nodes']) || !is_array($answer['nodes'])) return null;
    $id = str_replace('-', ':', (string)$id);
    if (!isset($answer['nodes'][$id]) || !is_array($answer['nodes'][$id])) return null;
    $entry = $answer['nodes'][$id];
    if (empty($entry['document']) || !is_array($entry['document'])) return null;
    return array(
        'document'   => $entry['document'],
        'styles'     => (isset($entry['styles']) && is_array($entry['styles'])) ? $entry['styles'] : array(),
        'components' => (isset($entry['components']) && is_array($entry['components'])) ? $entry['components'] : array(),
    );
}

/**
 * Convert one frame node. $styles and $components are the answer's maps
 * (style id => {name, styleType}, component id => {name}). $opts:
 * max_nodes (default 4000) and max_depth (default 40) bound the work on a
 * hostile or huge file; the node that meets a bound is reported as 'limit'
 * and the rest of the frame is left out.
 *
 * Report codes: 'type' (a node kind with no place on a page: sticky,
 * connector, slice, widget, embed, table), 'rotation', 'effect' (a blur;
 * shadows are carried), 'fill' (a radial, angular or diamond gradient, a
 * video, or stacked fills of which only the top one is kept), 'limit'.
 */
function pg_figma_convert_frame($frame, $styles = array(), $components = array(), $opts = array())
{
    $ctx = (object)array(
        'styles'      => is_array($styles) ? $styles : array(),
        'components'  => is_array($components) ? $components : array(),
        'max_nodes'   => isset($opts['max_nodes']) ? max(1, (int)$opts['max_nodes']) : 4000,
        'max_depth'   => isset($opts['max_depth']) ? max(1, (int)$opts['max_depth']) : 40,
        'nodes'       => 0,
        'seq'         => 1,
        'limited'     => false,
        'pictures'    => array(),
        'instances'   => array(),
        'colors'      => array(),
        'text_styles' => array(),
        'variables'   => array(),
        'fonts'       => array(),
        'report'      => array(),
    );

    $root = array('_id' => 'sd_1', 'type' => 'root', 'props' => array(), 'children' => array());
    if (is_array($frame) && pg_figma_visible($frame)) {
        $node = pg_figma_node($frame, null, 0, $ctx);
        if ($node !== null) $root['children'][] = $node;
    }

    $colors = array_values($ctx->colors);
    usort($colors, function ($a, $b) {
        return ($b['count'] - $a['count']) ?: ($a['order'] - $b['order']);
    });
    foreach ($colors as &$c) unset($c['order']);
    unset($c);

    return array(
        'name'        => (is_array($frame) && isset($frame['name'])) ? (string)$frame['name'] : '',
        'tree'        => $root,
        'pictures'    => $ctx->pictures,
        'instances'   => $ctx->instances,
        'colors'      => $colors,
        'text_styles' => array_values($ctx->text_styles),
        'variables'   => $ctx->variables,
        'fonts'       => array_keys($ctx->fonts),
        'report'      => $ctx->report,
    );
}

function pg_figma_visible($n)
{
    return !isset($n['visible']) || $n['visible'] !== false;
}

function pg_figma_report($n, $code, $ctx)
{
    $ctx->report[] = array(
        'figma' => isset($n['id']) ? (string)$n['id'] : '',
        'name'  => isset($n['name']) ? (string)$n['name'] : '',
        'code'  => $code,
    );
}

// Kinds drawn as a picture of their own (exported as SVG later).
function pg_figma_vector_types()
{
    static $t = array('VECTOR' => 1, 'BOOLEAN_OPERATION' => 1, 'STAR' => 1, 'LINE' => 1, 'REGULAR_POLYGON' => 1);
    return $t;
}

// Kinds converted as a box holding children.
function pg_figma_box_types()
{
    static $t = array('FRAME' => 1, 'GROUP' => 1, 'COMPONENT' => 1, 'COMPONENT_SET' => 1,
                      'INSTANCE' => 1, 'SECTION' => 1);
    return $t;
}

/**
 * True for a group or frame drawn by vectors alone (an icon): at least one
 * vector, besides only plain shapes, and no text, picture or auto layout.
 */
function pg_figma_is_icon($n)
{
    if (!empty($n['layoutMode']) && $n['layoutMode'] !== 'NONE') return false;
    $vectors = 0;
    $walk = function ($node) use (&$walk, &$vectors) {
        foreach (isset($node['children']) && is_array($node['children']) ? $node['children'] : array() as $c) {
            if (!is_array($c) || !pg_figma_visible($c)) continue;
            $type = isset($c['type']) ? $c['type'] : '';
            if (isset(pg_figma_vector_types()[$type])) { $vectors++; continue; }
            if ($type === 'ELLIPSE' || $type === 'RECTANGLE') {
                if (pg_figma_image_fill($c) !== null) return false;
                continue;
            }
            if ($type === 'GROUP' || $type === 'FRAME') {
                if (!empty($c['layoutMode']) && $c['layoutMode'] !== 'NONE') return false;
                if (!$walk($c)) return false;
                continue;
            }
            return false;
        }
        return true;
    };
    return $walk($n) && $vectors > 0;
}

function pg_figma_new_id($ctx)
{
    return 'sd_' . (++$ctx->seq);
}

/**
 * One Figma node to a designer node, or null when it is left out. $parent
 * is the Figma parent (null for the frame itself).
 */
function pg_figma_node($n, $parent, $depth, $ctx)
{
    if ($ctx->limited) return null;
    if ($ctx->nodes >= $ctx->max_nodes || $depth >= $ctx->max_depth) {
        $ctx->limited = true;
        pg_figma_report($n, 'limit', $ctx);
        return null;
    }
    $ctx->nodes++;

    $type = isset($n['type']) ? (string)$n['type'] : '';
    $name = isset($n['name']) ? (string)$n['name'] : '';
    $is_vector = isset(pg_figma_vector_types()[$type]);
    $is_box = isset(pg_figma_box_types()[$type]);
    if (!$is_vector && !$is_box && $type !== 'TEXT' && $type !== 'RECTANGLE' && $type !== 'ELLIPSE') {
        pg_figma_report($n, 'type', $ctx);
        return null;
    }
    if (!empty($n['rotation']) && abs((float)$n['rotation']) > 0.001) pg_figma_report($n, 'rotation', $ctx);

    $id = pg_figma_new_id($ctx);
    $place = pg_figma_place_style($n, $parent);
    $icon = $is_box && $parent !== null && pg_figma_is_icon($n);

    if ($type === 'TEXT') {
        $node = pg_figma_text_node($n, $place, $ctx);
    } elseif ($is_vector || $icon || (($type === 'RECTANGLE' || $type === 'ELLIPSE') && pg_figma_image_fill($n) !== null)) {
        $fill = $is_vector || $icon ? null : pg_figma_image_fill($n);
        $own = array_merge($place, pg_figma_fixed_size($n, $parent, true));
        if ($fill !== null) {
            $own[] = 'object-fit:' . ((isset($fill['scaleMode']) && $fill['scaleMode'] === 'FIT') ? 'contain' : 'cover');
            $own = array_merge($own, pg_figma_radius_style($n));
            if ($type === 'ELLIPSE') $own[] = 'border-radius:50%';
        }
        $ctx->pictures[] = array('node' => $id, 'figma' => isset($n['id']) ? (string)$n['id'] : '',
            'kind' => $fill !== null ? 'image' : 'vector',
            'image_ref' => ($fill !== null && isset($fill['imageRef'])) ? (string)$fill['imageRef'] : '');
        $node = array('type' => 'content', 'props' => array(
            'contentType' => 'image', 'src' => '', 'width' => '', 'height' => '', 'fluid' => false,
            'rounded-4' => false, 'objectPosition' => '', 'aspectRatio' => '', 'cssClass' => '',
        ), 'children' => array());
        if ($own) $node['props']['inlineStyle'] = implode(';', $own);
    } else {
        $node = pg_figma_box_node($n, $id, $parent, $place, $depth, $ctx);
    }

    if ($name !== '') $node['props']['customName'] = $name;
    $node = array('_id' => $id) + $node;

    if ($type === 'INSTANCE' && !$icon && !empty($n['componentId'])) {
        $cid = (string)$n['componentId'];
        $ctx->instances[] = array('node' => $id, 'figma' => isset($n['id']) ? (string)$n['id'] : '',
            'component_id' => $cid,
            'component_name' => isset($ctx->components[$cid]['name']) ? (string)$ctx->components[$cid]['name'] : '');
    }
    pg_figma_note_variables($n, $id, $ctx);
    return $node;
}

// A frame, group, component or instance, or a plain shape: a semantic
// element with its layout and its look as inline style.
function pg_figma_box_node($n, $id, $parent, $place, $depth, $ctx)
{
    $type = isset($n['type']) ? $n['type'] : '';
    $mode = isset($n['layoutMode']) ? (string)$n['layoutMode'] : 'NONE';
    if ($type === 'GROUP' || $type === 'RECTANGLE' || $type === 'ELLIPSE') $mode = 'NONE';

    $kids = array();
    foreach (isset($n['children']) && is_array($n['children']) ? $n['children'] : array() as $c) {
        if (is_array($c) && pg_figma_visible($c)) $kids[] = $c;
    }

    $style = $place;
    if ($parent === null) {
        $style[] = 'width:100%';
        if (isset($n['absoluteBoundingBox']['width'])) $style[] = 'max-width:' . pg_figma_px($n['absoluteBoundingBox']['width']);
        $style[] = 'margin:0 auto';
        if ($mode === 'NONE' && isset($n['absoluteBoundingBox']['height'])) $style[] = 'min-height:' . pg_figma_px($n['absoluteBoundingBox']['height']);
    } else {
        $style = array_merge($style, pg_figma_fixed_size($n, $parent, $mode === 'NONE'));
    }

    $absolute_child = false;
    foreach ($kids as $c) {
        if (isset($c['layoutPositioning']) && $c['layoutPositioning'] === 'ABSOLUTE') $absolute_child = true;
    }

    if ($mode === 'HORIZONTAL' || $mode === 'VERTICAL') {
        $style[] = 'display:flex;flex-direction:' . ($mode === 'HORIZONTAL' ? 'row' : 'column');
        $wrap = isset($n['layoutWrap']) && $n['layoutWrap'] === 'WRAP';
        if ($wrap) $style[] = 'flex-wrap:wrap';
        $justify = array('MIN' => '', 'CENTER' => 'center', 'MAX' => 'flex-end', 'SPACE_BETWEEN' => 'space-between');
        $align = array('MIN' => 'flex-start', 'CENTER' => 'center', 'MAX' => 'flex-end', 'BASELINE' => 'baseline');
        $p = isset($n['primaryAxisAlignItems']) ? (string)$n['primaryAxisAlignItems'] : 'MIN';
        $a = isset($n['counterAxisAlignItems']) ? (string)$n['counterAxisAlignItems'] : 'MIN';
        if (!empty($justify[$p])) $style[] = 'justify-content:' . $justify[$p];
        if (isset($align[$a])) $style[] = 'align-items:' . $align[$a];
        $gap = isset($n['itemSpacing']) ? (float)$n['itemSpacing'] : 0;
        $cross = ($wrap && isset($n['counterAxisSpacing'])) ? (float)$n['counterAxisSpacing'] : null;
        if ($cross !== null && $cross != $gap) {
            $style[] = $mode === 'HORIZONTAL'
                ? 'gap:' . pg_figma_px($cross) . ' ' . pg_figma_px($gap)
                : 'gap:' . pg_figma_px($gap) . ' ' . pg_figma_px($cross);
        } elseif ($gap > 0) {
            $style[] = 'gap:' . pg_figma_px($gap);
        }
    } elseif ($mode === 'GRID') {
        $style[] = 'display:grid';
        $cols = isset($n['gridColumnCount']) ? max(1, (int)$n['gridColumnCount']) : 1;
        $rows = isset($n['gridRowCount']) ? max(1, (int)$n['gridRowCount']) : 1;
        $style[] = 'grid-template-columns:' . pg_figma_grid_tracks(isset($n['gridColumnsSizing']) ? $n['gridColumnsSizing'] : null, 'repeat(' . $cols . ',minmax(0,1fr))');
        $style[] = 'grid-template-rows:' . pg_figma_grid_tracks(isset($n['gridRowsSizing']) ? $n['gridRowsSizing'] : null, 'repeat(' . $rows . ',auto)');
        $rg = isset($n['gridRowGap']) ? (float)$n['gridRowGap'] : 0;
        $cg = isset($n['gridColumnGap']) ? (float)$n['gridColumnGap'] : 0;
        if ($rg > 0 || $cg > 0) $style[] = 'gap:' . pg_figma_px($rg) . ' ' . pg_figma_px($cg);
    }
    if ($mode === 'NONE' && $kids) {
        $style[] = 'position:relative';
    } elseif ($absolute_child) {
        $style[] = 'position:relative';
    }

    if ($mode !== 'NONE') {
        $pad = array();
        foreach (array('paddingTop', 'paddingRight', 'paddingBottom', 'paddingLeft') as $k) {
            $pad[] = isset($n[$k]) ? (float)$n[$k] : 0;
        }
        if (array_sum($pad) > 0) $style[] = 'padding:' . implode(' ', array_map('pg_figma_px', $pad));
    }

    $style = array_merge($style, pg_figma_paint_style($n, $id, $ctx), pg_figma_radius_style($n));
    if ($type === 'ELLIPSE') $style[] = 'border-radius:50%';
    if (!empty($n['clipsContent'])) $style[] = 'overflow:hidden';
    if (isset($n['opacity']) && (float)$n['opacity'] < 1) $style[] = 'opacity:' . pg_figma_num($n['opacity']);

    $children = array();
    foreach ($kids as $c) {
        $child = pg_figma_node($c, $n, $depth + 1, $ctx);
        if ($child !== null) $children[] = $child;
    }

    $props = array('tag' => $parent === null ? 'section' : 'div', 'cssClass' => '');
    if ($style) $props['inlineStyle'] = implode(';', $style);
    return array('type' => 'semantic', 'props' => $props, 'children' => $children);
}

// A track list Figma wrote as CSS-like text, else the fallback. Only track
// words and numbers pass, so nothing else reaches the style attribute.
function pg_figma_grid_tracks($sizing, $fallback)
{
    if (is_string($sizing) && $sizing !== '' && preg_match('/^[a-z0-9 .,()%-]+$/i', $sizing)) return $sizing;
    return $fallback;
}

/**
 * The style that places $n in its parent: absolute offsets inside a frame
 * without auto layout (and for an absolute child of an auto layout frame),
 * flex or grid sizing inside an auto layout frame.
 */
function pg_figma_place_style($n, $parent)
{
    if ($parent === null) return array();
    $pmode = isset($parent['layoutMode']) ? (string)$parent['layoutMode'] : 'NONE';
    $ptype = isset($parent['type']) ? $parent['type'] : '';
    if ($ptype === 'GROUP') $pmode = 'NONE';
    $absolute = $pmode === 'NONE' || (isset($n['layoutPositioning']) && $n['layoutPositioning'] === 'ABSOLUTE');

    if ($absolute) {
        $box = isset($n['absoluteBoundingBox']) ? $n['absoluteBoundingBox'] : array();
        $pbox = isset($parent['absoluteBoundingBox']) ? $parent['absoluteBoundingBox'] : array();
        $x = (isset($box['x']) ? (float)$box['x'] : 0) - (isset($pbox['x']) ? (float)$pbox['x'] : 0);
        $y = (isset($box['y']) ? (float)$box['y'] : 0) - (isset($pbox['y']) ? (float)$pbox['y'] : 0);
        return array('position:absolute;left:' . pg_figma_px($x) . ';top:' . pg_figma_px($y));
    }

    $style = array();
    $row = $pmode === 'HORIZONTAL';
    $h = isset($n['layoutSizingHorizontal']) ? $n['layoutSizingHorizontal'] : '';
    $v = isset($n['layoutSizingVertical']) ? $n['layoutSizingVertical'] : '';
    if ($pmode === 'GRID') {
        if (!empty($n['gridColumnSpan']) && (int)$n['gridColumnSpan'] > 1) $style[] = 'grid-column:span ' . (int)$n['gridColumnSpan'];
        if (!empty($n['gridRowSpan']) && (int)$n['gridRowSpan'] > 1) $style[] = 'grid-row:span ' . (int)$n['gridRowSpan'];
        $self = array('MIN' => 'start', 'CENTER' => 'center', 'MAX' => 'end');
        if (isset($n['gridChildHorizontalAlign'], $self[$n['gridChildHorizontalAlign']])) $style[] = 'justify-self:' . $self[$n['gridChildHorizontalAlign']];
        if (isset($n['gridChildVerticalAlign'], $self[$n['gridChildVerticalAlign']])) $style[] = 'align-self:' . $self[$n['gridChildVerticalAlign']];
        return $style;
    }
    $main = $row ? $h : $v;
    $cross = $row ? $v : $h;
    if ($main === 'FILL') $style[] = 'flex:1 1 0;min-width:0';
    if ($cross === 'FILL') $style[] = 'align-self:stretch';
    return $style;
}

/**
 * Width and height written in pixels: a FIXED axis; with $by_box, also an
 * axis Figma gave no sizing for (shapes, frames without auto layout), taken
 * from the bounding box. A fixed main axis in a flex row or column does not
 * shrink, as in Figma.
 */
function pg_figma_fixed_size($n, $parent, $by_box)
{
    $box = isset($n['absoluteBoundingBox']) ? $n['absoluteBoundingBox'] : array();
    $style = array();
    $shrink = false;
    $pmode = ($parent !== null && isset($parent['layoutMode'])) ? $parent['layoutMode'] : 'NONE';
    foreach (array('width' => 'layoutSizingHorizontal', 'height' => 'layoutSizingVertical') as $dim => $key) {
        $sizing = isset($n[$key]) ? $n[$key] : '';
        if (($sizing === 'FIXED' || ($sizing === '' && $by_box)) && isset($box[$dim])) {
            $style[] = $dim . ':' . pg_figma_px($box[$dim]);
            if (($pmode === 'HORIZONTAL' && $dim === 'width') || ($pmode === 'VERTICAL' && $dim === 'height')) $shrink = true;
        }
    }
    if ($shrink && !(isset($n['layoutPositioning']) && $n['layoutPositioning'] === 'ABSOLUTE')) $style[] = 'flex-shrink:0';
    return $style;
}

// The topmost visible image fill of $n, or null.
function pg_figma_image_fill($n)
{
    $fills = pg_figma_visible_paints(isset($n['fills']) ? $n['fills'] : array());
    $top = end($fills);
    return ($top && isset($top['type']) && $top['type'] === 'IMAGE') ? $top : null;
}

function pg_figma_visible_paints($paints)
{
    $out = array();
    foreach (is_array($paints) ? $paints : array() as $p) {
        if (is_array($p) && pg_figma_visible($p)) $out[] = $p;
    }
    return $out;
}

/**
 * Background, border and shadow of a box. Figma stacks fills bottom to top;
 * the top one is kept and stacked ones are reported. An image fill on a box
 * is listed as a picture of kind 'background'.
 */
function pg_figma_paint_style($n, $id, $ctx)
{
    $style = array();
    $fills = pg_figma_visible_paints(isset($n['fills']) ? $n['fills'] : array());
    $reported = false;
    if (count($fills) > 1) { pg_figma_report($n, 'fill', $ctx); $reported = true; }
    $top = end($fills);
    if ($top) {
        $kind = isset($top['type']) ? $top['type'] : '';
        if ($kind === 'SOLID') {
            $color = pg_figma_color($top);
            if ($color !== '') {
                $style[] = 'background-color:' . $color;
                pg_figma_note_color($color, pg_figma_style_name($n, 'fill', $ctx), $ctx);
            }
        } elseif ($kind === 'GRADIENT_LINEAR') {
            $g = pg_figma_linear_gradient($top);
            if ($g !== '') $style[] = 'background-image:' . $g;
        } elseif ($kind === 'IMAGE') {
            $style[] = 'background-size:' . ((isset($top['scaleMode']) && $top['scaleMode'] === 'FIT') ? 'contain' : 'cover') . ';background-position:center';
            $ctx->pictures[] = array('node' => $id, 'figma' => isset($n['id']) ? (string)$n['id'] : '',
                'kind' => 'background', 'image_ref' => isset($top['imageRef']) ? (string)$top['imageRef'] : '');
        } elseif (!$reported) {
            pg_figma_report($n, 'fill', $ctx);
        }
    }

    $strokes = pg_figma_visible_paints(isset($n['strokes']) ? $n['strokes'] : array());
    $stroke = end($strokes);
    $weight = isset($n['strokeWeight']) ? (float)$n['strokeWeight'] : 0;
    if ($stroke && $weight > 0 && isset($stroke['type']) && $stroke['type'] === 'SOLID') {
        $color = pg_figma_color($stroke);
        if ($color !== '') {
            $style[] = 'border:' . pg_figma_px($weight) . ' solid ' . $color;
            pg_figma_note_color($color, pg_figma_style_name($n, 'stroke', $ctx), $ctx);
        }
    }

    $shadows = array();
    $blur = false;
    foreach (isset($n['effects']) && is_array($n['effects']) ? $n['effects'] : array() as $e) {
        if (!is_array($e) || !pg_figma_visible($e)) continue;
        $kind = isset($e['type']) ? $e['type'] : '';
        if ($kind === 'DROP_SHADOW' || $kind === 'INNER_SHADOW') {
            $color = pg_figma_color(array('color' => isset($e['color']) ? $e['color'] : array()));
            if ($color === '') continue;
            $shadows[] = ($kind === 'INNER_SHADOW' ? 'inset ' : '')
                . pg_figma_px(isset($e['offset']['x']) ? $e['offset']['x'] : 0) . ' '
                . pg_figma_px(isset($e['offset']['y']) ? $e['offset']['y'] : 0) . ' '
                . pg_figma_px(isset($e['radius']) ? $e['radius'] : 0) . ' '
                . pg_figma_px(isset($e['spread']) ? $e['spread'] : 0) . ' ' . $color;
        } else {
            $blur = true;
        }
    }
    if ($shadows) $style[] = 'box-shadow:' . implode(',', $shadows);
    if ($blur) pg_figma_report($n, 'effect', $ctx);
    return $style;
}

function pg_figma_radius_style($n)
{
    if (isset($n['rectangleCornerRadii']) && is_array($n['rectangleCornerRadii']) && count($n['rectangleCornerRadii']) === 4) {
        $r = array_map('floatval', array_values($n['rectangleCornerRadii']));
        if (max($r) <= 0) return array();
        if (count(array_unique($r)) === 1) return array('border-radius:' . pg_figma_px($r[0]));
        return array('border-radius:' . implode(' ', array_map('pg_figma_px', $r)));
    }
    if (!empty($n['cornerRadius']) && (float)$n['cornerRadius'] > 0) return array('border-radius:' . pg_figma_px($n['cornerRadius']));
    return array();
}

// A CSS linear-gradient from Figma's handle positions (box fractions, y down).
function pg_figma_linear_gradient($paint)
{
    if (empty($paint['gradientStops']) || !is_array($paint['gradientStops'])) return '';
    $angle = 180;
    $h = isset($paint['gradientHandlePositions']) ? $paint['gradientHandlePositions'] : array();
    if (isset($h[0]['x'], $h[0]['y'], $h[1]['x'], $h[1]['y'])) {
        $angle = rad2deg(atan2((float)$h[1]['x'] - (float)$h[0]['x'], -((float)$h[1]['y'] - (float)$h[0]['y'])));
        if ($angle < 0) $angle += 360;
    }
    $stops = array();
    $opacity = isset($paint['opacity']) ? $paint['opacity'] : 1;
    foreach ($paint['gradientStops'] as $s) {
        $color = pg_figma_color(array('color' => isset($s['color']) ? $s['color'] : array(), 'opacity' => $opacity));
        if ($color === '') return '';
        $stops[] = $color . ' ' . pg_figma_num((isset($s['position']) ? (float)$s['position'] : 0) * 100) . '%';
    }
    return 'linear-gradient(' . pg_figma_num($angle) . 'deg,' . implode(',', $stops) . ')';
}

/**
 * A paint's colour as CSS: #rrggbb when opaque, rgba() otherwise; the
 * paint's opacity multiplies the colour's alpha. '' for no colour.
 */
function pg_figma_color($paint)
{
    if (!isset($paint['color']) || !is_array($paint['color'])) return '';
    $c = $paint['color'];
    $ch = array();
    foreach (array('r', 'g', 'b') as $k) {
        $ch[] = max(0, min(255, (int)round((isset($c[$k]) ? (float)$c[$k] : 0) * 255)));
    }
    $a = (isset($c['a']) ? (float)$c['a'] : 1) * (isset($paint['opacity']) ? (float)$paint['opacity'] : 1);
    $a = max(0, min(1, round($a, 3)));
    if ($a >= 1) return sprintf('#%02x%02x%02x', $ch[0], $ch[1], $ch[2]);
    return 'rgba(' . implode(',', $ch) . ',' . pg_figma_num($a) . ')';
}

// A number for CSS: at most two decimals, no trailing zeros.
function pg_figma_num($v)
{
    $s = rtrim(rtrim(number_format(round((float)$v, 2), 2, '.', ''), '0'), '.');
    return ($s === '-0' || $s === '') ? '0' : $s;
}

function pg_figma_px($v)
{
    $s = pg_figma_num($v);
    return $s === '0' ? '0' : $s . 'px';
}

// The file's name of the style $n uses for $kind ('fill', 'stroke', 'text'), or ''.
function pg_figma_style_name($n, $kind, $ctx)
{
    if (empty($n['styles'][$kind])) return '';
    $sid = (string)$n['styles'][$kind];
    return isset($ctx->styles[$sid]['name']) ? (string)$ctx->styles[$sid]['name'] : '';
}

function pg_figma_note_color($value, $style, $ctx)
{
    if (!isset($ctx->colors[$value])) {
        $ctx->colors[$value] = array('value' => $value, 'style' => '', 'count' => 0, 'order' => count($ctx->colors));
    }
    $ctx->colors[$value]['count']++;
    if ($ctx->colors[$value]['style'] === '' && $style !== '') $ctx->colors[$value]['style'] = $style;
}

// Variables bound to $n's fields, kept for the step that can resolve them.
function pg_figma_note_variables($n, $id, $ctx)
{
    if (empty($n['boundVariables']) || !is_array($n['boundVariables'])) return;
    foreach ($n['boundVariables'] as $field => $bound) {
        $list = (is_array($bound) && isset($bound['id'])) ? array($bound) : (is_array($bound) ? $bound : array());
        foreach ($list as $alias) {
            if (is_array($alias) && !empty($alias['id'])) {
                $ctx->variables[] = array('node' => $id, 'figma' => isset($n['id']) ? (string)$n['id'] : '',
                    'field' => (string)$field, 'variable_id' => (string)$alias['id']);
            }
        }
    }
}

// ── Text ────────────────────────────────────────────────────────────────────

/**
 * A text node: a heading when its text style or layer is named H1-H6 or
 * "Heading n" or its size is a heading's, a link when the whole text links
 * to an address, a paragraph otherwise. Styled runs become spans, linked
 * runs links, inside the rich text.
 */
function pg_figma_text_node($n, $place, $ctx)
{
    $base = (isset($n['style']) && is_array($n['style'])) ? $n['style'] : array();
    $base_fill = pg_figma_text_fill($n);
    $text_style = pg_figma_style_name($n, 'text', $ctx);
    pg_figma_note_text_style($base, $text_style, $ctx);
    if ($base_fill !== '') pg_figma_note_color($base_fill, pg_figma_style_name($n, 'fill', $ctx), $ctx);

    $href = pg_figma_link_url($base);
    $html = pg_figma_rich_text($n, $href === '', $ctx);

    $style = array_merge($place, pg_figma_fixed_size($n, null, false));
    $style[] = 'margin:0';
    $style = array_merge($style, pg_figma_type_css($base, true));
    if ($base_fill !== '') $style[] = 'color:' . $base_fill;
    if (isset($n['opacity']) && (float)$n['opacity'] < 1) $style[] = 'opacity:' . pg_figma_num($n['opacity']);

    if ($href !== '') {
        $props = array('contentType' => 'link', 'text' => $html, 'href' => $href, 'target' => '', 'rel' => '', 'cssClass' => '');
    } else {
        $level = pg_figma_heading_level($n, $base, $text_style);
        $props = $level > 0
            ? array('contentType' => 'heading', 'tag' => 'h' . $level, 'text' => $html, 'align' => '', 'cssClass' => '')
            : array('contentType' => 'paragraph', 'text' => $html, 'lead' => false, 'align' => '', 'cssClass' => '');
    }
    $family = isset($base['fontFamily']) ? pg_figma_clean_family($base['fontFamily']) : '';
    if ($family !== '') {
        $props['fontFamily'] = $family;
        $ctx->fonts[$family] = true;
    }
    $props['inlineStyle'] = implode(';', $style);
    return array('type' => 'content', 'props' => $props, 'children' => array());
}

function pg_figma_heading_level($n, $base, $text_style)
{
    foreach (array($text_style, isset($n['name']) ? (string)$n['name'] : '') as $label) {
        if (preg_match('/(?:^|[^a-z0-9])h([1-6])(?:$|[^a-z0-9])/i', $label, $m)) return (int)$m[1];
        if (preg_match('/heading\s*([1-6])/i', $label, $m)) return (int)$m[1];
    }
    $size = isset($base['fontSize']) ? (float)$base['fontSize'] : 0;
    if ($size >= 40) return 1;
    if ($size >= 30) return 2;
    if ($size >= 24) return 3;
    return 0;
}

// A font family name with only letters, digits, spaces, dots, dashes and underscores.
function pg_figma_clean_family($family)
{
    return trim(preg_replace('/[^\p{L}\p{N} ._-]/u', '', (string)$family));
}

// The address of a TypeStyle's hyperlink when it is a web, mail or phone link, else ''.
function pg_figma_link_url($style)
{
    if (empty($style['hyperlink']['type']) || $style['hyperlink']['type'] !== 'URL' || empty($style['hyperlink']['url'])) return '';
    $url = trim((string)$style['hyperlink']['url']);
    return preg_match('~^(https?://|mailto:|tel:)~i', $url) ? $url : '';
}

function pg_figma_text_fill($n)
{
    $fills = pg_figma_visible_paints(isset($n['fills']) ? $n['fills'] : array());
    $top = end($fills);
    return ($top && isset($top['type']) && $top['type'] === 'SOLID') ? pg_figma_color($top) : '';
}

/**
 * CSS of a TypeStyle (or of a run's override, which holds only the keys it
 * changes). The family is left out on the node, where the fontFamily prop
 * writes it; a run writes it quoted.
 */
function pg_figma_type_css($t, $is_node)
{
    $css = array();
    if (!$is_node && !empty($t['fontFamily'])) {
        $family = pg_figma_clean_family($t['fontFamily']);
        if ($family !== '') $css[] = "font-family:'" . $family . "',sans-serif";
    }
    if (isset($t['fontSize'])) $css[] = 'font-size:' . pg_figma_px($t['fontSize']);
    if (isset($t['fontWeight'])) $css[] = 'font-weight:' . (int)$t['fontWeight'];
    if (!empty($t['italic'])) $css[] = 'font-style:italic';
    $unit = isset($t['lineHeightUnit']) ? $t['lineHeightUnit'] : 'PIXELS';
    if ($unit === 'FONT_SIZE_%' && isset($t['lineHeightPercentFontSize'])) {
        $css[] = 'line-height:' . pg_figma_num((float)$t['lineHeightPercentFontSize'] / 100);
    } elseif ($unit === 'PIXELS' && isset($t['lineHeightPx'])) {
        $css[] = 'line-height:' . pg_figma_px($t['lineHeightPx']);
    }
    if (isset($t['letterSpacing']) && (float)$t['letterSpacing'] != 0) $css[] = 'letter-spacing:' . pg_figma_px($t['letterSpacing']);
    $align = array('LEFT' => '', 'CENTER' => 'center', 'RIGHT' => 'right', 'JUSTIFIED' => 'justify');
    if (isset($t['textAlignHorizontal'], $align[$t['textAlignHorizontal']]) && $align[$t['textAlignHorizontal']] !== '') {
        $css[] = 'text-align:' . $align[$t['textAlignHorizontal']];
    }
    $case = array('UPPER' => 'uppercase', 'LOWER' => 'lowercase', 'TITLE' => 'capitalize');
    if (isset($t['textCase'], $case[$t['textCase']])) $css[] = 'text-transform:' . $case[$t['textCase']];
    $deco = array('UNDERLINE' => 'underline', 'STRIKETHROUGH' => 'line-through');
    if (isset($t['textDecoration'], $deco[$t['textDecoration']])) $css[] = 'text-decoration:' . $deco[$t['textDecoration']];
    if (!$is_node && !empty($t['fills'])) {
        $fills = pg_figma_visible_paints($t['fills']);
        $top = end($fills);
        if ($top && isset($top['type']) && $top['type'] === 'SOLID') {
            $color = pg_figma_color($top);
            if ($color !== '') $css[] = 'color:' . $color;
        }
    }
    return $css;
}

function pg_figma_note_text_style($t, $name, $ctx)
{
    $family = isset($t['fontFamily']) ? pg_figma_clean_family($t['fontFamily']) : '';
    $size = isset($t['fontSize']) ? $t['fontSize'] + 0 : 0;
    $weight = isset($t['fontWeight']) ? (int)$t['fontWeight'] : 400;
    $line = '';
    foreach (pg_figma_type_css(array_intersect_key($t, array_flip(array('lineHeightUnit', 'lineHeightPx', 'lineHeightPercentFontSize'))), true) as $c) {
        $line = substr($c, strlen('line-height:'));
    }
    $key = $name !== '' ? 'n:' . $name : 'v:' . $family . '|' . $weight . '|' . $size . '|' . $line;
    if (!isset($ctx->text_styles[$key])) {
        $ctx->text_styles[$key] = array('style' => $name, 'font_family' => $family, 'font_weight' => $weight,
            'font_size' => $size, 'line_height' => $line, 'count' => 0);
    }
    $ctx->text_styles[$key]['count']++;
}

/**
 * The node's characters as rich text: escaped, line breaks as <br>, each
 * run with a style override as a span (or a link, when the override links
 * to an address and $links is true). Figma counts the override positions
 * in UTF-16 units, so a character outside the basic plane takes two.
 */
function pg_figma_rich_text($n, $links, $ctx)
{
    $chars = isset($n['characters']) ? (string)$n['characters'] : '';
    $over = (isset($n['characterStyleOverrides']) && is_array($n['characterStyleOverrides'])) ? $n['characterStyleOverrides'] : array();
    $table = (isset($n['styleOverrideTable']) && is_array($n['styleOverrideTable'])) ? $n['styleOverrideTable'] : array();

    $runs = array();
    $pos = 0;
    foreach (preg_split('//u', $chars, -1, PREG_SPLIT_NO_EMPTY) as $ch) {
        $key = isset($over[$pos]) ? (string)$over[$pos] : '0';
        if (!isset($table[$key]) || !is_array($table[$key])) $key = '0';
        if ($runs && $runs[count($runs) - 1][0] === $key) {
            $runs[count($runs) - 1][1] .= $ch;
        } else {
            $runs[] = array($key, $ch);
        }
        $pos += strlen($ch) === 4 ? 2 : 1;
    }

    $html = '';
    foreach ($runs as $run) {
        $text = str_replace(array("\r\n", "\n", "\r", "\xE2\x80\xA8"), '<br>', htmlspecialchars($run[1], ENT_QUOTES, 'UTF-8'));
        if ($run[0] === '0') { $html .= $text; continue; }
        $o = $table[$run[0]];
        $css = implode(';', pg_figma_type_css($o, false));
        if (!empty($o['fontFamily'])) {
            $family = pg_figma_clean_family($o['fontFamily']);
            if ($family !== '') $ctx->fonts[$family] = true;
        }
        $style = $css !== '' ? ' style="' . htmlspecialchars($css, ENT_COMPAT, 'UTF-8') . '"' : '';
        $href = $links ? pg_figma_link_url($o) : '';
        if ($href !== '') {
            $html .= '<a href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '"' . $style . '>' . $text . '</a>';
        } elseif ($style !== '') {
            $html .= '<span' . $style . '>' . $text . '</span>';
        } else {
            $html .= $text;
        }
    }
    return $html;
}
