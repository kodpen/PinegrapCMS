<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The SVG allowlist sanitizer: an SVG written by the image editor, or opened
 * in it, is rebuilt element by element from a fixed list of drawing
 * elements and attributes. Scripts, event handlers, foreign content, links,
 * animations, outside addresses and document types never come through, so
 * the result is safe to serve from the site's own address.
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
 * The largest SVG the sanitizer reads, in bytes.
 *
 * @return int
 */
function pg_svg_sanitize_max_bytes()
{
    return 5 * 1024 * 1024;
}

/**
 * The elements kept, each with the attributes it may carry beyond the
 * common ones (pg_svg_sanitize_common_attributes()).
 *
 * @return array element name => list of its own attributes
 */
function pg_svg_sanitize_elements()
{
    $gradient = array('gradientUnits', 'gradientTransform', 'spreadMethod', 'href', 'xlink:href');
    $box = array('x', 'y', 'width', 'height');

    return array(
        'svg'            => array_merge($box, array('viewBox', 'preserveAspectRatio', 'version', 'xmlns', 'xmlns:xlink')),
        'g'              => array(),
        'defs'           => array(),
        'title'          => array(),
        'desc'           => array(),
        'symbol'         => array_merge($box, array('viewBox', 'preserveAspectRatio')),
        'use'            => array_merge($box, array('href', 'xlink:href')),
        'path'           => array('d', 'pathLength'),
        'rect'           => array_merge($box, array('rx', 'ry', 'pathLength')),
        'circle'         => array('cx', 'cy', 'r', 'pathLength'),
        'ellipse'        => array('cx', 'cy', 'rx', 'ry', 'pathLength'),
        'line'           => array('x1', 'y1', 'x2', 'y2', 'pathLength'),
        'polyline'       => array('points', 'pathLength'),
        'polygon'        => array('points', 'pathLength'),
        'text'           => array('x', 'y', 'dx', 'dy', 'rotate', 'textLength', 'lengthAdjust'),
        'tspan'          => array('x', 'y', 'dx', 'dy', 'rotate', 'textLength', 'lengthAdjust'),
        'image'          => array_merge($box, array('href', 'xlink:href', 'preserveAspectRatio')),
        'linearGradient' => array_merge($gradient, array('x1', 'y1', 'x2', 'y2')),
        'radialGradient' => array_merge($gradient, array('cx', 'cy', 'r', 'fx', 'fy', 'fr')),
        'stop'           => array('offset'),
        'pattern'        => array_merge($box, array('patternUnits', 'patternContentUnits', 'patternTransform', 'viewBox', 'preserveAspectRatio', 'href', 'xlink:href')),
        'clipPath'       => array('clipPathUnits'),
        'mask'           => array_merge($box, array('maskUnits', 'maskContentUnits')),
        'marker'         => array('viewBox', 'refX', 'refY', 'markerWidth', 'markerHeight', 'markerUnits', 'orient', 'preserveAspectRatio'),
        'filter'         => array_merge($box, array('filterUnits', 'primitiveUnits')),
        'feGaussianBlur' => array_merge($box, array('in', 'result', 'stdDeviation', 'edgeMode')),
        'feOffset'       => array_merge($box, array('in', 'result', 'dx', 'dy')),
        'feFlood'        => array_merge($box, array('result')),
        'feBlend'        => array_merge($box, array('in', 'in2', 'result', 'mode')),
        'feComposite'    => array_merge($box, array('in', 'in2', 'result', 'operator', 'k1', 'k2', 'k3', 'k4')),
        'feColorMatrix'  => array_merge($box, array('in', 'result', 'type', 'values')),
        'feMerge'        => array_merge($box, array('result')),
        'feMergeNode'    => array('in'),
        'feDropShadow'   => array_merge($box, array('in', 'result', 'dx', 'dy', 'stdDeviation')),
        'style'          => array('type', 'media'));
}

/**
 * Attributes any kept element may carry: identity, transform and the
 * presentation attributes (which are also the style properties kept).
 *
 * @return array attribute names
 */
function pg_svg_sanitize_common_attributes()
{
    return array_merge(array('id', 'class', 'style', 'transform', 'xml:space', 'lang', 'xml:lang'), pg_svg_sanitize_properties());
}

/**
 * Presentation properties, kept as attributes and as declarations of a
 * style attribute.
 *
 * @return array property names
 */
function pg_svg_sanitize_properties()
{
    return array(
        'fill', 'fill-opacity', 'fill-rule', 'stroke', 'stroke-width', 'stroke-opacity', 'stroke-linecap',
        'stroke-linejoin', 'stroke-miterlimit', 'stroke-dasharray', 'stroke-dashoffset', 'opacity', 'color',
        'display', 'visibility', 'overflow', 'clip-path', 'clip-rule', 'mask', 'filter', 'marker-start',
        'marker-mid', 'marker-end', 'stop-color', 'stop-opacity', 'flood-color', 'flood-opacity',
        'mix-blend-mode', 'isolation', 'paint-order', 'vector-effect', 'shape-rendering', 'text-rendering',
        'image-rendering', 'color-interpolation-filters', 'font-family', 'font-size', 'font-weight',
        'font-style', 'font-variant', 'font-stretch', 'letter-spacing', 'word-spacing', 'text-anchor',
        'text-decoration', 'dominant-baseline', 'alignment-baseline', 'baseline-shift', 'writing-mode',
        'direction', 'unicode-bidi', 'white-space', 'line-height', 'transform-origin', 'transform-box');
}

/**
 * Whether a value is free of anything that could reach outside the file or
 * run code: url() only to an id in the file, no escapes, no expression().
 *
 * @return bool
 */
function pg_svg_sanitize_value_ok($value)
{
    $lower = strtolower($value);

    if (preg_match('/[\\\\<>]|expression\s*\(|javascript\s*:|vbscript\s*:|@import|behavior\s*:|-moz-binding/', $lower)) {
        return false;
    }

    // Each url() names an id of this file.
    if (preg_match_all('/url\s*\(\s*([^)]*)\)/', $lower, $found)) {
        foreach ($found[1] as $target) {
            if (!preg_match('/^["\']?#[a-z0-9_.:-]+["\']?\s*$/i', $target)) {
                return false;
            }
        }
    }

    // A "url" not in a url() form (u r l split, a stray url without its
    // brackets matched above) is not passed on.
    return preg_match_all('/\burl\b/', $lower) === preg_match_all('/\burl\s*\(/', $lower);
}

/**
 * A reference attribute's value when it names an id of this file or carries
 * an embedded raster picture; '' otherwise.
 *
 * @return string
 */
function pg_svg_sanitize_href($value)
{
    $value = trim($value);

    if (preg_match('/^#[A-Za-z0-9_.:-]+$/', $value)) {
        return $value;
    }

    if (preg_match('#^data:image/(png|jpeg|gif|webp|avif);base64,[A-Za-z0-9+/=\s]+$#', $value)) {
        return $value;
    }

    return '';
}

/**
 * A style attribute with only the declarations of kept properties whose
 * values pass pg_svg_sanitize_value_ok(); '' when none is left.
 *
 * @return string
 */
function pg_svg_sanitize_style($value)
{
    $properties = array_flip(pg_svg_sanitize_properties());
    $kept = array();

    foreach (explode(';', $value) as $declaration) {
        $parts = explode(':', $declaration, 2);

        if (count($parts) !== 2) {
            continue;
        }

        $name = strtolower(trim($parts[0]));
        $item = trim($parts[1]);

        if (isset($properties[$name]) && ($item !== '') && pg_svg_sanitize_value_ok($item)) {
            $kept[] = $name . ': ' . $item;
        }
    }

    return implode('; ', $kept);
}

/**
 * A style sheet's text when it is plain rules over kept values; null when
 * it imports, reaches outside, escapes or could close its element.
 *
 * @return string|null
 */
function pg_svg_sanitize_sheet($text)
{
    if (!pg_svg_sanitize_value_ok($text) || preg_match('/@(?!keyframes|media|font-feature-values)/i', $text)) {
        return null;
    }

    return $text;
}

/**
 * Copies $source's kept attributes and children into $target.
 */
function pg_svg_sanitize_copy($source, $target, $out, $elements, $common)
{
    $name = $source->localName;
    $allowed = array_flip(array_merge($common, $elements[$name]));

    foreach ($source->attributes as $attribute) {
        $namespace = $attribute->namespaceURI;

        if ($namespace === 'http://www.w3.org/1999/xlink') {
            $key = 'xlink:' . $attribute->localName;
        } elseif ($namespace === 'http://www.w3.org/XML/1998/namespace') {
            $key = 'xml:' . $attribute->localName;
        } elseif (($namespace === null) || ($namespace === '')) {
            $key = $attribute->localName;
        } else {
            continue;
        }

        if (!isset($allowed[$key]) || (strpos($key, 'xmlns') === 0)) {
            continue;
        }

        $value = $attribute->value;

        if (($key === 'href') || ($key === 'xlink:href')) {
            $value = pg_svg_sanitize_href($value);
        } elseif ($key === 'style') {
            $value = pg_svg_sanitize_style($value);
        } elseif (!pg_svg_sanitize_value_ok($value)) {
            $value = '';
        }

        if ($value === '') {
            continue;
        }

        if ($key === 'xlink:href') {
            $target->setAttributeNS('http://www.w3.org/1999/xlink', 'xlink:href', $value);
        } elseif (strpos($key, 'xml:') === 0) {
            $target->setAttributeNS('http://www.w3.org/XML/1998/namespace', $key, $value);
        } else {
            $target->setAttribute($key, $value);
        }
    }

    foreach ($source->childNodes as $child) {
        if (($child->nodeType === XML_TEXT_NODE) || ($child->nodeType === XML_CDATA_SECTION_NODE)) {
            if ($name !== 'style') {
                $target->appendChild($out->createTextNode($child->nodeValue));
            }
            continue;
        }

        if ($child->nodeType !== XML_ELEMENT_NODE) {
            continue;
        }

        if (($child->namespaceURI !== 'http://www.w3.org/2000/svg') || !isset($elements[$child->localName]) || ($child->localName === 'svg')) {
            continue;
        }

        // A reference element with nothing left to point at draws nothing.
        if (($child->localName === 'use') && (pg_svg_sanitize_href($child->getAttribute('href') . $child->getAttributeNS('http://www.w3.org/1999/xlink', 'href')) === '')) {
            continue;
        }

        if ($child->localName === 'style') {
            $sheet = pg_svg_sanitize_sheet($child->textContent);

            if ($sheet === null) {
                continue;
            }

            $element = $out->createElementNS('http://www.w3.org/2000/svg', 'style');
            $element->appendChild($out->createTextNode($sheet));
            $target->appendChild($element);
            continue;
        }

        $element = $out->createElementNS('http://www.w3.org/2000/svg', $child->localName);
        $target->appendChild($element);
        pg_svg_sanitize_copy($child, $element, $out, $elements, $common);
    }
}

/**
 * An SVG rebuilt from the allowlist: listed elements and attributes only,
 * href to an id of the file or an embedded raster picture, no script, event
 * handler, foreignObject, link, animation, outside url(), @import, document
 * type or entity.
 *
 * @param string $svg the SVG's text
 * @return string the sanitized SVG starting at its <svg> root, or '' when
 *                the input is not an SVG, is broken, too large or carries a
 *                document type
 */
function pg_svg_sanitize($svg)
{
    if (!is_string($svg) || ($svg === '') || (strlen($svg) > pg_svg_sanitize_max_bytes()) || !class_exists('DOMDocument')) {
        return '';
    }

    // A document type is how entities (outside reads, expansion bombs) get
    // in; a drawing never needs one.
    if (stripos($svg, '<!DOCTYPE') !== false || stripos($svg, '<!ENTITY') !== false) {
        return '';
    }

    $source = new DOMDocument();
    $errors = libxml_use_internal_errors(true);
    $loaded = $source->loadXML($svg, LIBXML_NONET | LIBXML_COMPACT);
    libxml_clear_errors();
    libxml_use_internal_errors($errors);

    $root = $loaded ? $source->documentElement : null;

    if (!$root || ($root->localName !== 'svg') || ($root->namespaceURI !== 'http://www.w3.org/2000/svg')) {
        return '';
    }

    $out = new DOMDocument('1.0', 'UTF-8');
    $element = $out->createElementNS('http://www.w3.org/2000/svg', 'svg');
    $out->appendChild($element);
    pg_svg_sanitize_copy($root, $element, $out, pg_svg_sanitize_elements(), pg_svg_sanitize_common_attributes());

    $text = $out->saveXML($out->documentElement);

    return is_string($text) ? $text : '';
}
