<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * A presentation (.pptx) read as a design: each slide as its size, its
 * background and its elements in paint order, every element placed on the
 * page with its groups resolved, for the Artifact Canvas to open as layers.
 *
 * The package is opened through the preview reader's gates and limits
 * (includes/fn/office.php): every part measured before it is read, a part
 * built into a DOM no larger than PG_OFFICE_MAX_DOM. Nothing is written and
 * nothing is taken out of the package: a picture is named by its part, which
 * the caller reads through the same gates. Places and sizes stay in EMU,
 * text sizes and spacing in hundredths of a point, as the file writes them;
 * turning them into pixels is the caller's.
 *
 * A text box is one text element: its paragraphs, the first run's style and
 * whether the other runs differ from it. A picture keeps its crop, a line its
 * ends, a shape its preset kind. What the canvas cannot hold (a chart, a
 * table, a diagram, a video, a shape with curves, an effect, a gradient) is
 * left out and counted by kind.
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
 * The most elements a slide gives; past it the slide's list is cut.
 */
define('PG_PPTX_DESIGN_MAX_ELEMENTS', 2000);

/**
 * How deep groups are followed; a deeper group is left out.
 */
define('PG_PPTX_DESIGN_MAX_DEPTH', 32);

/**
 * The preset shapes a canvas can draw (rectangles, ellipses, polygons,
 * stars, speech bubbles) and the presets that are lines.
 *
 * @return array kind => true
 */
function pg_pptx_design_presets()
{
    static $presets = null;

    if ($presets === null) {
        $presets = array_fill_keys(array(
            'rect', 'roundRect', 'ellipse', 'triangle', 'rtTriangle', 'diamond', 'pentagon', 'hexagon', 'heptagon',
            'octagon', 'decagon', 'dodecagon', 'star4', 'star5', 'star6', 'star7', 'star8', 'star10', 'star12',
            'wedgeRectCallout', 'wedgeRoundRectCallout', 'wedgeEllipseCallout',
        ), true);
    }

    return $presets;
}

/**
 * Whether a preset is a line.
 *
 * @param string $preset
 * @return bool
 */
function pg_pptx_design_is_line($preset)
{
    return in_array($preset, array('line', 'straightConnector1'), true);
}

/**
 * A presentation read as a design.
 *
 * @param string $path
 * @param array  $limits elements: the most elements a slide gives (PG_PPTX_DESIGN_MAX_ELEMENTS)
 * @return array ok, error, width, height (EMU), slides (n, hidden, background,
 *               elements, left, simplified), left (kind => count over the
 *               file), simplified (text boxes whose runs differ), truncated
 */
function pg_pptx_design_read($path, $limits = array())
{
    $none = array('ok' => false, 'error' => '', 'width' => 0, 'height' => 0, 'slides' => array(), 'left' => array(), 'simplified' => 0, 'truncated' => false);
    $zip = pg_office_open($path, $error);

    if ($zip === null) {
        $none['error'] = $error;

        return $none;
    }

    $parts = pg_office_pptx_slides($zip, $refused, $found, $main);

    if (($refused !== '') || !$found) {
        $zip->close();
        $none['error'] = ($refused !== '') ? pg_office_refusal_error($refused) : lang('The file could not be read.');

        return $none;
    }

    $width = 9144000;
    $height = 6858000;
    $presentation = pg_office_xml($zip, $main);

    if ($presentation !== null) {
        foreach ($presentation->getElementsByTagNameNS('*', 'sldSz') as $size) {
            $cx = (int) pg_office_attr($size, 'cx');
            $cy = (int) pg_office_attr($size, 'cy');

            if (($cx > 0) && ($cy > 0)) {
                $width = $cx;
                $height = $cy;
            }

            break;
        }
    }

    unset($presentation);

    $state = array(
        'zip'        => $zip,
        'refused'    => '',
        'parts'      => array(),
        'max'        => max(1, (int) ($limits['elements'] ?? PG_PPTX_DESIGN_MAX_ELEMENTS)),
        'size'       => 0,
        'truncated'  => count($parts) > PG_OFFICE_MAX_SLIDES,
    );
    $out = array('ok' => true, 'error' => '', 'width' => $width, 'height' => $height, 'slides' => array(), 'left' => array(), 'simplified' => 0, 'truncated' => false);

    foreach (array_slice($parts, 0, PG_OFFICE_MAX_SLIDES) as $index => $part) {
        $slide = pg_pptx_design_slide($state, $part, $index + 1);

        if ($state['refused'] !== '') {
            $zip->close();
            $none['error'] = pg_office_refusal_error($state['refused']);

            return $none;
        }

        foreach ($slide['left'] as $kind => $count) {
            $out['left'][$kind] = ($out['left'][$kind] ?? 0) + $count;
        }

        $out['simplified'] += $slide['simplified'];
        $out['slides'][] = $slide;

        if ($state['size'] > PG_OFFICE_MAX_HTML) {
            $state['truncated'] = true;
            break;
        }
    }

    $zip->close();
    ksort($out['left']);
    $out['truncated'] = $state['truncated'];

    return $out;
}

/**
 * A part of the package with its relationships and folder, read once.
 *
 * @param array  $state
 * @param string $part
 * @return array|null dom, rels, folder
 */
function pg_pptx_design_part(&$state, $part)
{
    if (array_key_exists($part, $state['parts'])) {
        return $state['parts'][$part];
    }

    $dom = pg_office_xml($state['zip'], $part, $status);
    pg_office_refuse($state['refused'], $status);
    $rels = pg_office_rels($state['zip'], pg_office_pptx_rels_part($part), $rels_refused);
    pg_office_refuse($state['refused'], $rels_refused);
    $entry = ($dom === null) ? null : array('dom' => $dom, 'rels' => $rels, 'folder' => pg_office_pptx_folder($part));

    // Layouts, masters and themes are shared by slides; a slide is read once.
    if (strpos($part, 'ppt/slides/') !== 0) {
        $state['parts'][$part] = $entry;
    }

    return $entry;
}

/**
 * The part a part's first relationship of a type points at, read.
 *
 * @param array      $state
 * @param array|null $entry
 * @param string     $type relationship type word (slidelayout, slidemaster, theme)
 * @return array|null
 */
function pg_pptx_design_related(&$state, $entry, $type)
{
    if ($entry === null) {
        return null;
    }

    foreach ($entry['rels'] as $rel) {
        if (($rel['type'] === $type) && !$rel['external']) {
            return pg_pptx_design_part($state, pg_office_part_name($entry['folder'], $rel['target']));
        }
    }

    return null;
}

/**
 * The theme a master names: its colours by scheme name, the master's colour
 * map, and its heading and body fonts.
 *
 * @param array      $state
 * @param array|null $master
 * @return array colors, map, major, minor
 */
function pg_pptx_design_theme(&$state, $master)
{
    $theme = array(
        'colors' => array(),
        'map'    => array('bg1' => 'lt1', 'tx1' => 'dk1', 'bg2' => 'lt2', 'tx2' => 'dk2'),
        'major'  => null,
        'minor'  => null,
    );

    if ($master === null) {
        return $theme;
    }

    foreach ($master['dom']->getElementsByTagNameNS('*', 'clrMap') as $map) {
        foreach ($map->attributes as $attribute) {
            $theme['map'][$attribute->localName] = (string) $attribute->value;
        }
        break;
    }

    $part = pg_pptx_design_related($state, $master, 'theme');

    if ($part === null) {
        return $theme;
    }

    foreach ($part['dom']->getElementsByTagNameNS('*', 'clrScheme') as $scheme) {
        foreach ($scheme->childNodes as $color) {
            if (!($color instanceof DOMElement)) {
                continue;
            }

            foreach ($color->childNodes as $value) {
                if (!($value instanceof DOMElement)) {
                    continue;
                }

                $hex = ($value->localName === 'sysClr') ? pg_office_attr($value, 'lastClr') : pg_office_attr($value, 'val');

                if (preg_match('/^[0-9a-f]{6}$/i', $hex)) {
                    $theme['colors'][$color->localName] = strtolower($hex);
                }
                break;
            }
        }
        break;
    }

    foreach (array('major' => 'majorFont', 'minor' => 'minorFont') as $key => $local) {
        foreach ($part['dom']->getElementsByTagNameNS('*', $local) as $font) {
            foreach (pg_office_pptx_children($font, 'latin') as $latin) {
                $face = trim(pg_office_attr($latin, 'typeface'));
                $theme[$key] = ($face !== '') ? $face : null;
            }
            break;
        }
    }

    return $theme;
}

/**
 * One slide: its background (its own, else its layout's, else its
 * master's) and its elements in paint order.
 *
 * @param array  $state
 * @param string $part
 * @param int    $n
 * @return array n, hidden, background, elements, left, simplified
 */
function pg_pptx_design_slide(&$state, $part, $n)
{
    $slide = array('n' => $n, 'hidden' => false, 'background' => null, 'elements' => array(), 'left' => array(), 'simplified' => 0);
    $entry = pg_pptx_design_part($state, $part);

    if (($entry === null) || ($state['refused'] !== '')) {
        return $slide;
    }

    $layout = pg_pptx_design_related($state, $entry, 'slidelayout');
    $master = pg_pptx_design_related($state, $layout, 'slidemaster');

    if ($state['refused'] !== '') {
        return $slide;
    }

    $context = array(
        'entry'   => $entry,
        'chain'   => array_values(array_filter(array($layout, $master))),
        'theme'     => pg_pptx_design_theme($state, $master),
        'groups'    => array(),
        'state_zip' => $state['zip'],
    );
    $show = strtolower((string) $entry['dom']->documentElement->getAttribute('show'));
    $slide['hidden'] = in_array($show, array('0', 'false'), true);

    foreach (array($entry, $layout, $master) as $source) {
        if ($source === null) {
            continue;
        }

        foreach ($source['dom']->getElementsByTagNameNS('*', 'cSld') as $common) {
            foreach (pg_office_pptx_children($common, 'bg') as $background) {
                $slide['background'] = pg_pptx_design_background($context, $source, $background, $slide);
                break 3;
            }
            break;
        }
    }

    foreach ($entry['dom']->getElementsByTagNameNS('*', 'spTree') as $tree) {
        pg_pptx_design_tree($state, $context, $tree, $slide, 0);
        break;
    }

    ksort($slide['left']);

    return $slide;
}

/**
 * Counts what is left out of a slide by its kind.
 *
 * @param array  $slide
 * @param string $kind
 */
function pg_pptx_design_leave(&$slide, $kind)
{
    $slide['left'][$kind] = ($slide['left'][$kind] ?? 0) + 1;
}

/**
 * A slide's background: a colour or a picture, null when none is drawn.
 *
 * @param array      $context
 * @param array      $source the part the background is written in
 * @param DOMElement $background p:bg
 * @param array      $slide
 * @return array|null
 */
function pg_pptx_design_background($context, $source, $background, &$slide)
{
    foreach ($background->childNodes as $child) {
        if (!($child instanceof DOMElement)) {
            continue;
        }

        if ($child->localName === 'bgRef') {
            $color = pg_pptx_design_color($context['theme'], $child);

            return ($color === null) ? null : array('type' => 'color') + $color;
        }

        if ($child->localName !== 'bgPr') {
            continue;
        }

        foreach ($child->childNodes as $fill) {
            if (!($fill instanceof DOMElement)) {
                continue;
            }

            if ($fill->localName === 'blipFill') {
                $picture = pg_pptx_design_blip($context, $source, $fill, $slide);

                return ($picture === null) ? null : array('type' => 'picture') + $picture;
            }

            $color = pg_pptx_design_fill($context['theme'], $fill, $slide);

            if ($color !== false) {
                return ($color === null) ? null : array('type' => 'color') + $color;
            }
        }
    }

    return null;
}

/**
 * A fill element as a colour: null for noFill, false for an element that is
 * no fill. A gradient gives its first colour, a pattern its foreground; both
 * are counted as left out.
 *
 * @param array      $theme
 * @param DOMElement $fill
 * @param array      $slide
 * @return array|null|false color, opacity
 */
function pg_pptx_design_fill($theme, $fill, &$slide)
{
    switch ($fill->localName) {
        case 'noFill':
            return null;

        case 'solidFill':
            return pg_pptx_design_color($theme, $fill);

        case 'gradFill':
            pg_pptx_design_leave($slide, 'gradient');

            foreach ($fill->getElementsByTagNameNS('*', 'gs') as $stop) {
                return pg_pptx_design_color($theme, $stop);
            }

            return null;

        case 'pattFill':
            pg_pptx_design_leave($slide, 'pattern');

            foreach (pg_office_pptx_children($fill, 'fgClr') as $foreground) {
                return pg_pptx_design_color($theme, $foreground);
            }

            return null;
    }

    return false;
}

/**
 * The colour an element holds as its child (srgbClr, schemeClr, sysClr),
 * with its alpha as the opacity and its luminance, tint and shade applied.
 *
 * @param array      $theme
 * @param DOMElement $holder
 * @return array|null color (#rrggbb), opacity (0-1)
 */
function pg_pptx_design_color($theme, $holder)
{
    foreach ($holder->childNodes as $value) {
        if (!($value instanceof DOMElement)) {
            continue;
        }

        $hex = '';

        switch ($value->localName) {
            case 'srgbClr':
                $hex = pg_office_attr($value, 'val');
                break;

            case 'sysClr':
                $hex = pg_office_attr($value, 'lastClr');
                break;

            case 'schemeClr':
                $name = pg_office_attr($value, 'val');
                $name = $theme['map'][$name] ?? $name;
                $hex = $theme['colors'][$name] ?? '';
                break;

            default:
                continue 2;
        }

        if (!preg_match('/^[0-9a-f]{6}$/i', $hex)) {
            return null;
        }

        $rgb = array(hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2)));
        $opacity = 1.0;
        $lum_mod = 1.0;
        $lum_off = 0.0;

        foreach ($value->childNodes as $modifier) {
            if (!($modifier instanceof DOMElement)) {
                continue;
            }

            $amount = max(0, (int) pg_office_attr($modifier, 'val')) / 100000;

            switch ($modifier->localName) {
                case 'alpha':
                    $opacity = min(1.0, $amount);
                    break;

                case 'lumMod':
                    $lum_mod = $amount;
                    break;

                case 'lumOff':
                    $lum_off = $amount;
                    break;

                case 'shade':
                    foreach ($rgb as $i => $channel) {
                        $rgb[$i] = $channel * min(1.0, $amount);
                    }
                    break;

                case 'tint':
                    foreach ($rgb as $i => $channel) {
                        $rgb[$i] = $channel + (255 - $channel) * (1 - min(1.0, $amount));
                    }
                    break;
            }
        }

        if (($lum_mod !== 1.0) || ($lum_off !== 0.0)) {
            $rgb = pg_pptx_design_luminance($rgb, $lum_mod, $lum_off);
        }

        $out = '#';

        foreach ($rgb as $channel) {
            $out .= sprintf('%02x', max(0, min(255, (int) round($channel))));
        }

        return array('color' => $out, 'opacity' => round($opacity, 4));
    }

    return null;
}

/**
 * A colour's luminance changed as DrawingML does: in HSL, multiplied, then
 * moved.
 *
 * @param float[] $rgb 0-255
 * @param float   $mod
 * @param float   $off
 * @return float[]
 */
function pg_pptx_design_luminance($rgb, $mod, $off)
{
    list($r, $g, $b) = array($rgb[0] / 255, $rgb[1] / 255, $rgb[2] / 255);
    $max = max($r, $g, $b);
    $min = min($r, $g, $b);
    $l = ($max + $min) / 2;
    $h = 0.0;
    $s = 0.0;
    $d = $max - $min;

    if ($d > 0) {
        $s = ($l > 0.5) ? $d / (2 - $max - $min) : $d / ($max + $min);

        if ($max === $r) {
            $h = ($g - $b) / $d + (($g < $b) ? 6 : 0);
        } elseif ($max === $g) {
            $h = ($b - $r) / $d + 2;
        } else {
            $h = ($r - $g) / $d + 4;
        }

        $h /= 6;
    }

    $l = max(0.0, min(1.0, $l * $mod + $off));

    if ($s == 0) {
        return array($l * 255, $l * 255, $l * 255);
    }

    $q = ($l < 0.5) ? $l * (1 + $s) : $l + $s - $l * $s;
    $p = 2 * $l - $q;
    $out = array();

    foreach (array($h + 1 / 3, $h, $h - 1 / 3) as $t) {
        $t = ($t < 0) ? $t + 1 : (($t > 1) ? $t - 1 : $t);

        if ($t < 1 / 6) {
            $v = $p + ($q - $p) * 6 * $t;
        } elseif ($t < 1 / 2) {
            $v = $q;
        } elseif ($t < 2 / 3) {
            $v = $p + ($q - $p) * (2 / 3 - $t) * 6;
        } else {
            $v = $p;
        }

        $out[] = $v * 255;
    }

    return $out;
}

/**
 * A picture fill (a:blipFill / p:blipFill): its part, its crop as shares of
 * the picture cut away on each side (a negative share is room around it),
 * and its opacity. Null, counted, when the part is linked or missing.
 *
 * The source rectangle (srcRect) cuts the picture; the fill rectangle
 * (stretch/fillRect) places what is left in the box, negative sides reaching
 * past it, as Canva writes a cropped picture. Both become one crop.
 *
 * @param array      $context
 * @param array      $source the part the fill is written in
 * @param DOMElement $fill
 * @param array      $slide
 * @return array|null part, ext, crop (l, t, r, b), opacity
 */
function pg_pptx_design_blip($context, $source, $fill, &$slide)
{
    $blip = null;

    foreach (pg_office_pptx_children($fill, 'blip') as $element) {
        $blip = $element;
    }

    $rel = ($blip === null) ? null : ($source['rels'][pg_office_attr($blip, 'embed')] ?? null);
    $part = (($rel === null) || $rel['external']) ? '' : pg_office_part_name($source['folder'], $rel['target']);

    if (($part === '') || ($context['state_zip']->locateName($part) === false)) {
        pg_pptx_design_leave($slide, 'picture');

        return null;
    }

    $source_crop = array('l' => 0.0, 't' => 0.0, 'r' => 0.0, 'b' => 0.0);
    $fill_rect = $source_crop;

    foreach (pg_office_pptx_children($fill, 'srcRect') as $rect) {
        foreach ($source_crop as $side => $value) {
            $source_crop[$side] = (int) pg_office_attr($rect, $side) / 100000;
        }
    }

    foreach (pg_office_pptx_children($fill, 'stretch') as $stretch) {
        foreach (pg_office_pptx_children($stretch, 'fillRect') as $rect) {
            foreach ($fill_rect as $side => $value) {
                $fill_rect[$side] = (int) pg_office_attr($rect, $side) / 100000;
            }
        }
    }

    $crop = array();

    foreach (array(array('l', 'r'), array('t', 'b')) as $pair) {
        list($start, $end) = $pair;
        $kept = 1 - $source_crop[$start] - $source_crop[$end];
        $placed = 1 - $fill_rect[$start] - $fill_rect[$end];

        foreach ($pair as $side) {
            $share = ($placed > 0) ? -$fill_rect[$side] / $placed : 0.0;
            $crop[$side] = round($source_crop[$side] + $share * $kept, 4) + 0.0;
        }
    }

    $opacity = 1.0;

    foreach (pg_office_pptx_children($blip, 'alphaModFix') as $alpha) {
        $opacity = round(min(1.0, max(0, (int) pg_office_attr($alpha, 'amt')) / 100000), 4);
    }

    $ext = strtolower((string) pathinfo($part, PATHINFO_EXTENSION));

    return array('part' => $part, 'ext' => $ext, 'crop' => array('l' => $crop['l'], 't' => $crop['t'], 'r' => $crop['r'], 'b' => $crop['b']), 'opacity' => $opacity);
}

/**
 * A place written as a:xfrm (or p:xfrm): offset, size, turn in degrees,
 * flips, and a group's frame for its children.
 *
 * @param DOMElement|null $xfrm
 * @return array|null x, y, w, h, rot, fh, fv, cx, cy, cw, ch
 */
function pg_pptx_design_xfrm($xfrm)
{
    if ($xfrm === null) {
        return null;
    }

    $place = array(
        'x'   => 0, 'y' => 0, 'w' => 0, 'h' => 0,
        'rot' => (int) pg_office_attr($xfrm, 'rot') / 60000,
        'fh'  => in_array(pg_office_attr($xfrm, 'flipH'), array('1', 'true'), true),
        'fv'  => in_array(pg_office_attr($xfrm, 'flipV'), array('1', 'true'), true),
        'cx'  => 0, 'cy' => 0, 'cw' => 0, 'ch' => 0,
    );
    $found = false;

    foreach ($xfrm->childNodes as $child) {
        if (!($child instanceof DOMElement)) {
            continue;
        }

        switch ($child->localName) {
            case 'off':
                $place['x'] = (int) pg_office_attr($child, 'x');
                $place['y'] = (int) pg_office_attr($child, 'y');
                $found = true;
                break;

            case 'ext':
                $place['w'] = max(0, (int) pg_office_attr($child, 'cx'));
                $place['h'] = max(0, (int) pg_office_attr($child, 'cy'));
                $found = true;
                break;

            case 'chOff':
                $place['cx'] = (int) pg_office_attr($child, 'x');
                $place['cy'] = (int) pg_office_attr($child, 'y');
                break;

            case 'chExt':
                $place['cw'] = (int) pg_office_attr($child, 'cx');
                $place['ch'] = (int) pg_office_attr($child, 'cy');
                break;
        }
    }

    return $found ? $place : null;
}

/**
 * A shape's own place: its properties' xfrm, else (for a placeholder) the
 * place of the layout's or master's placeholder of the same index or type.
 *
 * @param array      $context
 * @param DOMElement $shape
 * @return array|null
 */
function pg_pptx_design_own_place($context, $shape)
{
    foreach ($shape->childNodes as $child) {
        if (!($child instanceof DOMElement)) {
            continue;
        }

        if (($child->localName === 'spPr') || ($child->localName === 'grpSpPr')) {
            foreach (pg_office_pptx_children($child, 'xfrm') as $xfrm) {
                return pg_pptx_design_xfrm($xfrm);
            }
        }

        if ($child->localName === 'xfrm') {
            return pg_pptx_design_xfrm($child);
        }
    }

    $placeholder = null;

    foreach ($shape->getElementsByTagNameNS('*', 'ph') as $element) {
        $placeholder = $element;
        break;
    }

    if ($placeholder === null) {
        return null;
    }

    $type = pg_pptx_design_placeholder_type(pg_office_attr($placeholder, 'type'));
    $idx = pg_office_attr($placeholder, 'idx');

    foreach ($context['chain'] as $source) {
        $by_type = null;

        foreach ($source['dom']->getElementsByTagNameNS('*', 'ph') as $other) {
            $holder = $other->parentNode ? $other->parentNode->parentNode : null;
            $holder = $holder ? $holder->parentNode : null;

            if (!($holder instanceof DOMElement)) {
                continue;
            }

            $other_idx = pg_office_attr($other, 'idx');
            $same_type = pg_pptx_design_placeholder_type(pg_office_attr($other, 'type')) === $type;

            if (($idx !== '') && ($other_idx === $idx) && ($same_type || ($type === 'body'))) {
                $place = pg_pptx_design_own_place(array('chain' => array()), $holder);

                if ($place !== null) {
                    return $place;
                }
            }

            if ($same_type && ($by_type === null)) {
                $by_type = $holder;
            }
        }

        if ($by_type !== null) {
            $place = pg_pptx_design_own_place(array('chain' => array()), $by_type);

            if ($place !== null) {
                return $place;
            }
        }
    }

    return null;
}

/**
 * A placeholder type as the master names it: no type is a body, a centred
 * title a title, a subtitle a body.
 *
 * @param string $type
 * @return string
 */
function pg_pptx_design_placeholder_type($type)
{
    if (($type === '') || ($type === 'subTitle') || ($type === 'obj')) {
        return 'body';
    }

    return ($type === 'ctrTitle') ? 'title' : $type;
}

/**
 * A shape's place on the page: its own place carried out through the groups
 * it sits in, innermost first. A group scales its children's frame to its
 * own, mirrors them across its middle and turns them about it.
 *
 * @param array $place  pg_pptx_design_xfrm()
 * @param array $groups the groups' places, outermost first
 * @return array x, y, w, h (EMU), rot (degrees, 0-360), flip_h, flip_v
 */
function pg_pptx_design_resolve($place, $groups)
{
    $cx = $place['x'] + $place['w'] / 2;
    $cy = $place['y'] + $place['h'] / 2;
    $w = (float) $place['w'];
    $h = (float) $place['h'];
    $rot = (float) $place['rot'];
    $fh = $place['fh'];
    $fv = $place['fv'];

    foreach (array_reverse($groups) as $group) {
        $sx = ($group['cw'] > 0) ? $group['w'] / $group['cw'] : 1.0;
        $sy = ($group['ch'] > 0) ? $group['h'] / $group['ch'] : 1.0;
        $cx = $group['x'] + ($cx - $group['cx']) * $sx;
        $cy = $group['y'] + ($cy - $group['cy']) * $sy;
        $w *= $sx;
        $h *= $sy;
        $gx = $group['x'] + $group['w'] / 2;
        $gy = $group['y'] + $group['h'] / 2;

        if ($group['fh']) {
            $cx = 2 * $gx - $cx;
            $fh = !$fh;
            $rot = -$rot;
        }

        if ($group['fv']) {
            $cy = 2 * $gy - $cy;
            $fv = !$fv;
            $rot = -$rot;
        }

        if ($group['rot'] != 0) {
            $angle = deg2rad($group['rot']);
            $dx = $cx - $gx;
            $dy = $cy - $gy;
            $cx = $gx + $dx * cos($angle) - $dy * sin($angle);
            $cy = $gy + $dx * sin($angle) + $dy * cos($angle);
            $rot += $group['rot'];
        }
    }

    $rot = fmod($rot, 360.0);
    $rot = round(($rot < 0) ? $rot + 360 : $rot, 2);

    return array(
        'x'      => (int) round($cx - $w / 2),
        'y'      => (int) round($cy - $h / 2),
        'w'      => (int) round($w),
        'h'      => (int) round($h),
        'rot'    => ($rot >= 360) ? 0.0 : $rot + 0.0,
        'flip_h' => $fh,
        'flip_v' => $fv,
    );
}

/**
 * Adds an element to the slide while it has room; past the limit the slide
 * is cut and the file marked truncated.
 *
 * @param array $state
 * @param array $slide
 * @param array $element
 * @return bool whether it was added
 */
function pg_pptx_design_add(&$state, &$slide, $element)
{
    if (count($slide['elements']) >= $state['max']) {
        $state['truncated'] = true;

        return false;
    }

    $slide['elements'][] = $element;

    return true;
}

/**
 * The shapes of a tree (or a group) in paint order.
 *
 * @param array      $state
 * @param array      $context
 * @param DOMElement $tree
 * @param array      $slide
 * @param int        $depth
 */
function pg_pptx_design_tree(&$state, $context, $tree, &$slide, $depth)
{
    foreach ($tree->childNodes as $shape) {
        if (!($shape instanceof DOMElement) || ($state['size'] > PG_OFFICE_MAX_HTML)) {
            continue;
        }

        switch ($shape->localName) {
            case 'grpSp':
                $place = pg_pptx_design_own_place($context, $shape);

                if ($depth >= PG_PPTX_DESIGN_MAX_DEPTH) {
                    pg_pptx_design_leave($slide, 'other');
                    break;
                }

                $inner = $context;

                if ($place !== null) {
                    $inner['groups'][] = $place;
                }

                pg_pptx_design_tree($state, $inner, $shape, $slide, $depth + 1);
                break;

            case 'AlternateContent':
                $branch = pg_office_pptx_children($shape, 'Fallback') ?: pg_office_pptx_children($shape, 'Choice');

                if ($branch) {
                    pg_pptx_design_tree($state, $context, $branch[0], $slide, $depth + 1);
                }
                break;

            case 'sp':
            case 'cxnSp':
            case 'pic':
                pg_pptx_design_shape($state, $context, $shape, $slide);
                break;

            case 'graphicFrame':
                $kind = 'other';

                foreach ($shape->getElementsByTagNameNS('*', 'graphicData') as $data) {
                    $uri = strtolower(pg_office_attr($data, 'uri'));

                    foreach (array('table' => 'table', 'chart' => 'chart', 'diagram' => 'diagram', 'ole' => 'object') as $word => $name) {
                        if (substr($uri, -strlen($word)) === $word) {
                            $kind = $name;
                            break;
                        }
                    }
                    break;
                }

                pg_pptx_design_leave($slide, $kind);
                break;

            case 'contentPart':
                pg_pptx_design_leave($slide, 'ink');
                break;
        }
    }
}

/**
 * The properties element of a shape (p:spPr) or null.
 *
 * @param DOMElement $shape
 * @return DOMElement|null
 */
function pg_pptx_design_properties($shape)
{
    foreach (pg_office_pptx_children($shape, 'spPr') as $properties) {
        return $properties;
    }

    return null;
}

/**
 * The colour a shape's style gives for a reference (fillRef, lnRef), null
 * when the reference is to nothing (idx 0) or absent.
 *
 * @param array      $theme
 * @param DOMElement $shape
 * @param string     $ref
 * @return array|null
 */
function pg_pptx_design_style_color($theme, $shape, $ref)
{
    foreach (pg_office_pptx_children($shape, 'style') as $style) {
        foreach (pg_office_pptx_children($style, $ref) as $reference) {
            return ((int) pg_office_attr($reference, 'idx') > 0) ? pg_pptx_design_color($theme, $reference) : null;
        }
    }

    return null;
}

/**
 * A shape's outline: colour, width, dash and line ends; null when none.
 *
 * @param array           $theme
 * @param DOMElement      $shape
 * @param DOMElement|null $properties
 * @param array           $slide
 * @return array|null color, opacity, width (EMU), dash, head_start, head_end
 */
function pg_pptx_design_stroke($theme, $shape, $properties, &$slide)
{
    $line = null;

    if ($properties !== null) {
        foreach (pg_office_pptx_children($properties, 'ln') as $element) {
            $line = $element;
        }
    }

    $color = false;

    if ($line !== null) {
        foreach ($line->childNodes as $fill) {
            if ($fill instanceof DOMElement) {
                $found = pg_pptx_design_fill($theme, $fill, $slide);

                if ($found !== false) {
                    $color = $found;
                    break;
                }
            }
        }
    }

    if ($color === false) {
        $color = pg_pptx_design_style_color($theme, $shape, 'lnRef');
    }

    if ($color === null) {
        return null;
    }

    $stroke = $color + array('width' => 12700, 'dash' => '', 'head_start' => '', 'head_end' => '');

    if ($line === null) {
        return $stroke;
    }

    if (pg_office_attr($line, 'w') !== '') {
        $stroke['width'] = max(0, (int) pg_office_attr($line, 'w'));
    }

    foreach (pg_office_pptx_children($line, 'prstDash') as $dash) {
        $value = pg_office_attr($dash, 'val');
        $stroke['dash'] = ($value === 'solid') ? '' : $value;
    }

    foreach (array('headEnd' => 'head_start', 'tailEnd' => 'head_end') as $local => $key) {
        foreach (pg_office_pptx_children($line, $local) as $end) {
            $value = pg_office_attr($end, 'type');
            $stroke[$key] = ($value === 'none') ? '' : $value;
        }
    }

    return $stroke;
}

/**
 * A shape's geometry: its preset name and adjustments, 'rect' for a custom
 * path that is a plain rectangle, 'custom' for any other path, '' for none.
 *
 * @param DOMElement|null $properties
 * @return array geometry, adjust
 */
function pg_pptx_design_geometry($properties)
{
    if ($properties === null) {
        return array('', array());
    }

    foreach (pg_office_pptx_children($properties, 'prstGeom') as $preset) {
        $adjust = array();

        foreach ($preset->getElementsByTagNameNS('*', 'gd') as $guide) {
            if (preg_match('/^val\s+(-?\d+)$/', trim(pg_office_attr($guide, 'fmla')), $match)) {
                $adjust[pg_office_attr($guide, 'name')] = (int) $match[1];
            }
        }

        return array(pg_office_attr($preset, 'prst'), $adjust);
    }

    foreach (pg_office_pptx_children($properties, 'custGeom') as $custom) {
        $paths = $custom->getElementsByTagNameNS('*', 'path');

        if ($paths->length !== 1) {
            return array('custom', array());
        }

        $path = $paths->item(0);
        $w = (int) pg_office_attr($path, 'w');
        $h = (int) pg_office_attr($path, 'h');
        $corners = array();

        foreach ($path->childNodes as $step) {
            if (!($step instanceof DOMElement) || ($step->localName === 'close')) {
                continue;
            }

            if (!in_array($step->localName, array('moveTo', 'lnTo'), true)) {
                return array('custom', array());
            }

            foreach (pg_office_pptx_children($step, 'pt') as $point) {
                $corners[] = pg_office_attr($point, 'x') . ',' . pg_office_attr($point, 'y');
            }
        }

        $box = array('0,0', $w . ',0', $w . ',' . $h, '0,' . $h);
        sort($corners);
        sort($box);

        return array(($corners === $box) ? 'rect' : 'custom', array());
    }

    return array('', array());
}

/**
 * One shape, connector or picture: a picture element, a line, a shape, a
 * text over it, or what is left out of it counted.
 *
 * @param array      $state
 * @param array      $context
 * @param DOMElement $shape
 * @param array      $slide
 */
function pg_pptx_design_shape(&$state, $context, $shape, &$slide)
{
    $name = '';

    foreach ($shape->getElementsByTagNameNS('*', 'cNvPr') as $properties) {
        $name = (string) $properties->getAttribute('name');
        break;
    }

    if ($shape->getElementsByTagNameNS('*', 'videoFile')->length || $shape->getElementsByTagNameNS('*', 'audioFile')->length) {
        pg_pptx_design_leave($slide, 'media');

        return;
    }

    $place = pg_pptx_design_own_place($context, $shape);

    if ($place === null) {
        $place = array('x' => 0, 'y' => 0, 'w' => 0, 'h' => 0, 'rot' => 0, 'fh' => false, 'fv' => false);
    }

    $base = array('name' => $name) + pg_pptx_design_resolve($place, $context['groups']);
    $properties = pg_pptx_design_properties($shape);
    list($geometry, $adjust) = pg_pptx_design_geometry($properties);
    $theme = $context['theme'];

    if ($properties !== null) {
        foreach ($properties->childNodes as $child) {
            if (($child instanceof DOMElement) && in_array($child->localName, array('effectLst', 'scene3d', 'sp3d'), true) && $child->hasChildNodes()) {
                pg_pptx_design_leave($slide, 'effect');
                break;
            }
        }
    }

    // A picture: p:pic's own fill, or a shape filled with a picture.
    $blip_fill = null;

    foreach (pg_office_pptx_children($shape, 'blipFill') as $fill) {
        $blip_fill = $fill;
    }

    if (($blip_fill === null) && ($properties !== null)) {
        foreach (pg_office_pptx_children($properties, 'blipFill') as $fill) {
            $blip_fill = $fill;
        }
    }

    if ($blip_fill !== null) {
        $picture = pg_pptx_design_blip($context, $context['entry'], $blip_fill, $slide);

        if ($picture !== null) {
            if ($geometry === 'custom') {
                pg_pptx_design_leave($slide, 'mask');
            }

            $kept = (($geometry === '') || ($geometry === 'custom') || !isset(pg_pptx_design_presets()[$geometry])) ? 'rect' : $geometry;
            pg_pptx_design_add($state, $slide, array('type' => 'picture') + $base + $picture + array('geometry' => $kept));
        }

        pg_pptx_design_text($state, $context, $shape, $base, $slide);

        return;
    }

    if (pg_pptx_design_is_line($geometry) || ($shape->localName === 'cxnSp')) {
        $stroke = pg_pptx_design_stroke($theme, $shape, $properties, $slide);

        if ($stroke !== null) {
            pg_pptx_design_add($state, $slide, array('type' => 'line') + $base + pg_pptx_design_ends($base) + array('stroke' => $stroke));
        }

        return;
    }

    $fill = false;

    if ($properties !== null) {
        foreach ($properties->childNodes as $child) {
            if ($child instanceof DOMElement) {
                $found = pg_pptx_design_fill($theme, $child, $slide);

                if ($found !== false) {
                    $fill = $found;
                    break;
                }
            }
        }
    }

    if ($fill === false) {
        $fill = pg_pptx_design_style_color($theme, $shape, 'fillRef');
    }

    $stroke = pg_pptx_design_stroke($theme, $shape, $properties, $slide);

    if (($fill !== null) || ($stroke !== null)) {
        if (isset(pg_pptx_design_presets()[$geometry]) || ($geometry === '')) {
            pg_pptx_design_add($state, $slide, array('type' => 'shape') + $base + array('geometry' => ($geometry === '') ? 'rect' : $geometry, 'adjust' => $adjust, 'fill' => $fill, 'stroke' => $stroke));
        } else {
            pg_pptx_design_leave($slide, 'shape');
        }
    }

    pg_pptx_design_text($state, $context, $shape, $base, $slide);
}

/**
 * A line's two ends on the page: from its box's top left to its bottom
 * right, mirrored by its flips, turned about its middle.
 *
 * @param array $base x, y, w, h, rot, flip_h, flip_v
 * @return array x1, y1, x2, y2
 */
function pg_pptx_design_ends($base)
{
    $cx = $base['x'] + $base['w'] / 2;
    $cy = $base['y'] + $base['h'] / 2;
    $dx = $base['w'] / 2 * ($base['flip_h'] ? -1 : 1);
    $dy = $base['h'] / 2 * ($base['flip_v'] ? -1 : 1);
    $angle = deg2rad($base['rot']);
    $rx = $dx * cos($angle) - $dy * sin($angle);
    $ry = $dx * sin($angle) + $dy * cos($angle);

    return array(
        'x1' => (int) round($cx - $rx),
        'y1' => (int) round($cy - $ry),
        'x2' => (int) round($cx + $rx),
        'y2' => (int) round($cy + $ry),
    );
}

/**
 * A run's style: its own properties over the box's list style for its
 * level.
 *
 * @param array           $theme
 * @param DOMElement|null $run      a:rPr
 * @param DOMElement|null $fallback the level's a:defRPr
 * @return array font, size, bold, italic, underline, color, spacing
 */
function pg_pptx_design_run_style($theme, $run, $fallback)
{
    $style = array('font' => null, 'size' => null, 'bold' => false, 'italic' => false, 'underline' => false, 'color' => null, 'spacing' => 0);

    foreach (array($fallback, $run) as $source) {
        if ($source === null) {
            continue;
        }

        if (pg_office_attr($source, 'sz') !== '') {
            $style['size'] = max(1, (int) pg_office_attr($source, 'sz'));
        }

        foreach (array('b' => 'bold', 'i' => 'italic') as $local => $key) {
            if (pg_office_attr($source, $local) !== '') {
                $style[$key] = in_array(pg_office_attr($source, $local), array('1', 'true'), true);
            }
        }

        if (pg_office_attr($source, 'u') !== '') {
            $style['underline'] = pg_office_attr($source, 'u') !== 'none';
        }

        if (pg_office_attr($source, 'spc') !== '') {
            $style['spacing'] = (int) pg_office_attr($source, 'spc');
        }

        foreach (pg_office_pptx_children($source, 'solidFill') as $fill) {
            $style['color'] = pg_pptx_design_color($theme, $fill);
        }

        foreach (pg_office_pptx_children($source, 'latin') as $latin) {
            $face = trim(pg_office_attr($latin, 'typeface'));

            if (($face === '+mn-lt') || ($face === '+mj-lt')) {
                $face = (string) $theme[($face === '+mj-lt') ? 'major' : 'minor'];
            }

            if ($face !== '') {
                $style['font'] = $face;
            }
        }
    }

    return $style;
}

/**
 * A spacing element (a:lnSpc, a:spcBef, a:spcAft) as points in hundredths
 * or a share of the line.
 *
 * @param DOMElement $holder
 * @return array|null type (pts, pct), value
 */
function pg_pptx_design_spacing($holder)
{
    foreach (pg_office_pptx_children($holder, 'spcPts') as $points) {
        return array('type' => 'pts', 'value' => (int) pg_office_attr($points, 'val'));
    }

    foreach (pg_office_pptx_children($holder, 'spcPct') as $share) {
        return array('type' => 'pct', 'value' => round((int) pg_office_attr($share, 'val') / 100000, 4));
    }

    return null;
}

/**
 * A shape's text body as one text element in the shape's place: its
 * paragraphs (text, alignment, level, spacing), the first run's style, and
 * whether other runs differ from it (simplified, counted). A body without
 * any text but spaces gives nothing.
 *
 * @param array      $state
 * @param array      $context
 * @param DOMElement $shape
 * @param array      $base
 * @param array      $slide
 */
function pg_pptx_design_text(&$state, $context, $shape, $base, &$slide)
{
    $body = null;

    foreach (pg_office_pptx_children($shape, 'txBody') as $element) {
        $body = $element;
    }

    if ($body === null) {
        return;
    }

    $levels = array();

    foreach (pg_office_pptx_children($body, 'lstStyle') as $list) {
        foreach ($list->childNodes as $level) {
            if (($level instanceof DOMElement) && preg_match('/^lvl(\d)pPr$/', $level->localName, $match)) {
                foreach (pg_office_pptx_children($level, 'defRPr') as $default) {
                    $levels[(int) $match[1] - 1] = $default;
                }
            }
        }
    }

    $paragraphs = array();
    $styles = array();
    $all = '';

    foreach (pg_office_pptx_children($body, 'p') as $paragraph) {
        $entry = array('text' => pg_office_pptx_text($paragraph), 'align' => 'l', 'level' => 0, 'line' => null, 'before' => null, 'after' => null);

        foreach (pg_office_pptx_children($paragraph, 'pPr') as $properties) {
            $entry['align'] = (pg_office_attr($properties, 'algn') !== '') ? pg_office_attr($properties, 'algn') : 'l';
            $entry['level'] = min(8, max(0, (int) pg_office_attr($properties, 'lvl')));

            foreach (array('lnSpc' => 'line', 'spcBef' => 'before', 'spcAft' => 'after') as $local => $key) {
                foreach (pg_office_pptx_children($properties, $local) as $spacing) {
                    $entry[$key] = pg_pptx_design_spacing($spacing);
                }
            }
        }

        foreach ($paragraph->childNodes as $run) {
            if (!($run instanceof DOMElement) || !in_array($run->localName, array('r', 'fld'), true) || (trim(pg_pptx_design_run_text($run)) === '')) {
                continue;
            }

            $properties = null;

            foreach (pg_office_pptx_children($run, 'rPr') as $element) {
                $properties = $element;
            }

            $styles[] = pg_pptx_design_run_style($context['theme'], $properties, $levels[$entry['level']] ?? null);
        }

        $all .= $entry['text'];
        $paragraphs[] = $entry;
    }

    if (trim($all) === '') {
        return;
    }

    $state['size'] += strlen($all);

    if ($state['size'] > PG_OFFICE_MAX_HTML) {
        $state['truncated'] = true;

        return;
    }

    $simplified = count(array_unique(array_map('serialize', $styles))) > 1;
    $body_pr = null;

    foreach (pg_office_pptx_children($body, 'bodyPr') as $element) {
        $body_pr = $element;
    }

    $inset = array('l' => 91440, 't' => 45720, 'r' => 91440, 'b' => 45720);
    $anchor = 't';
    $wrap = true;
    $autofit = 'none';

    if ($body_pr !== null) {
        foreach (array('l' => 'lIns', 't' => 'tIns', 'r' => 'rIns', 'b' => 'bIns') as $side => $local) {
            if (pg_office_attr($body_pr, $local) !== '') {
                $inset[$side] = max(0, (int) pg_office_attr($body_pr, $local));
            }
        }

        $anchor = (pg_office_attr($body_pr, 'anchor') !== '') ? pg_office_attr($body_pr, 'anchor') : 't';
        $wrap = pg_office_attr($body_pr, 'wrap') !== 'none';

        if (pg_office_pptx_children($body_pr, 'spAutoFit')) {
            $autofit = 'shape';
        } elseif (pg_office_pptx_children($body_pr, 'normAutofit')) {
            $autofit = 'text';
        }
    }

    $element = array('type' => 'text') + $base + array(
        'paragraphs' => $paragraphs,
        'style'      => $styles ? $styles[0] : pg_pptx_design_run_style($context['theme'], null, $levels[0] ?? null),
        'simplified' => $simplified,
        'inset'      => $inset,
        'anchor'     => $anchor,
        'wrap'       => $wrap,
        'autofit'    => $autofit,
    );

    if (pg_pptx_design_add($state, $slide, $element) && $simplified) {
        $slide['simplified']++;
    }
}

/**
 * The text of one run or field.
 *
 * @param DOMElement $run
 * @return string
 */
function pg_pptx_design_run_text($run)
{
    $text = '';

    foreach (pg_office_pptx_children($run, 't') as $part) {
        $text .= $part->textContent;
    }

    return $text;
}
