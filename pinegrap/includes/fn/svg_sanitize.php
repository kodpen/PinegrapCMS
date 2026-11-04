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

    // CSS fetches pictures from plain strings as well as from url().
    if (preg_match('/image-set\s*\(|cross-fade\s*\(|\belement\s*\(|\bimage\s*\(|\bsrc\s*\(/', $lower)) {
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
 * The prefix a file's ids and classes get while it has a style sheet, made
 * from the root's id: always starting with pgsvg-, so a rule left unscoped
 * (pg_svg_sanitize_selectors()) never names the page's own ids or classes,
 * whatever the file's root id is.
 *
 * @return string
 */
function pg_svg_sanitize_prefix($root_id)
{
    return ((strpos($root_id, 'pgsvg-') === 0) ? $root_id : 'pgsvg-' . $root_id) . '-';
}

/**
 * An id or class name with the file's prefix; unchanged without a prefix,
 * when it carries the prefix already (a file this sanitizer wrote), when it
 * is the root's id, which stays as the sheet's scope, or when it is a motion
 * group's pgm-n, which pg_image_editor_svg_motion_add() styles after the
 * sanitizer.
 *
 * @return string
 */
function pg_svg_sanitize_rename($name, $prefix, $root_id = '')
{
    if (($prefix === '') || ($name === '') || ($name === $root_id) || (strpos($name, $prefix) === 0) || preg_match('/^pgm-[0-9]+$/', $name)) {
        return $name;
    }

    return $prefix . $name;
}

/**
 * A value whose url(#…) references name prefixed ids
 * (pg_svg_sanitize_rename()).
 *
 * @return string
 */
function pg_svg_sanitize_rename_urls($value, $prefix, $root_id)
{
    if ($prefix === '') {
        return $value;
    }

    return preg_replace_callback('/(url\s*\(\s*["\']?#)([A-Za-z0-9_.:-]+)/i', function ($m) use ($prefix, $root_id) {
        return $m[1] . pg_svg_sanitize_rename($m[2], $prefix, $root_id);
    }, $value);
}

/**
 * A style attribute with only the declarations of kept properties whose
 * values pass pg_svg_sanitize_value_ok(); '' when none is left. With a
 * prefix, url(#…) references follow the renamed ids.
 *
 * @return string
 */
function pg_svg_sanitize_style($value, $prefix = '', $root_id = '')
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
            $kept[] = $name . ': ' . pg_svg_sanitize_rename_urls($item, $prefix, $root_id);
        }
    }

    return implode('; ', $kept);
}

/**
 * The selectors of a rule that stay: names, classes and ids joined by
 * descendant or child combinators. Attribute selectors, pseudo-classes,
 * pseudo-elements, the universal selector and the sibling combinators go,
 * and so does a selector starting at the root's own element name.
 *
 * With a prefix, ids and classes get it (pg_svg_sanitize_rename()) and a
 * selector whose every compound names a prefixed id or class stays as it
 * is: what a <use> draws matches selectors inside the use's own tree, so a
 * rule under the root's id would not reach symbol or definition content,
 * and the prefix alone keeps such a rule to the file's own elements. Any
 * other selector goes under the root's id.
 *
 * @return string the kept selectors, comma separated, or ''
 */
function pg_svg_sanitize_selectors($prelude, $root_id, $prefix = '')
{
    $compound = '(?:[a-zA-Z][a-zA-Z0-9_-]*(?:[.#][a-zA-Z_-][a-zA-Z0-9_-]*)*|(?:[.#][a-zA-Z_-][a-zA-Z0-9_-]*)+)';
    $kept = array();

    foreach (explode(',', $prelude) as $part) {
        $part = preg_replace('/\s*>\s*/', ' > ', trim(preg_replace('/\s+/', ' ', $part)));

        // A rule already scoped (a file this sanitizer wrote) is scoped once.
        while (strpos($part, '#' . $root_id . ' ') === 0) {
            $part = trim(ltrim(substr($part, strlen($root_id) + 2), ' >'));
        }

        if (!preg_match('/^' . $compound . '(?: (?:> )?' . $compound . ')*$/', $part) || preg_match('/^svg(?![a-zA-Z0-9_-])/i', $part)) {
            continue;
        }

        if ($prefix !== '') {
            $part = preg_replace_callback('/([.#])([a-zA-Z_-][a-zA-Z0-9_-]*)/', function ($m) use ($prefix, $root_id) {
                return $m[1] . pg_svg_sanitize_rename($m[2], $prefix, ($m[1] === '#') ? $root_id : '');
            }, $part);

            $own = true;

            foreach (explode(' ', str_replace(' > ', ' ', $part)) as $item) {
                if (!preg_match('/[.#]' . preg_quote($prefix, '/') . '/', $item)) {
                    $own = false;
                    break;
                }
            }

            if ($own) {
                $kept[] = $part;
                continue;
            }
        }

        $kept[] = '#' . $root_id . ' ' . $part;
    }

    return implode(', ', $kept);
}

/**
 * A style sheet rebuilt from its plain rules: each rule's selectors kept by
 * pg_svg_sanitize_selectors() (prefixed, or scoped to the root's id), its
 * declarations kept as a style attribute's are (pg_svg_sanitize_style()). Comments, at-rules
 * (@import, @media, @keyframes, @font-face) and any rule left without a
 * selector or a declaration go; an inline drawing's sheet then reaches
 * nothing outside its own root.
 *
 * @return string|null the sheet, or null when no rule is left
 */
function pg_svg_sanitize_sheet($text, $root_id, $prefix = '')
{
    $text = preg_replace('#/\*.*?(\*/|$)#s', ' ', (string) $text);
    $length = strlen($text);
    $depth = 0;
    $start = 0;
    $prelude = '';
    $body = 0;
    $rules = array();

    for ($i = 0; $i < $length; $i++) {
        $c = $text[$i];

        if ($c === '{') {
            if ($depth === 0) {
                $prelude = substr($text, $start, $i - $start);
                $body = $i + 1;
            }

            $depth++;
        } elseif ($c === '}') {
            if ($depth === 0) {
                $start = $i + 1;
                continue;
            }

            $depth--;

            if ($depth === 0) {
                $declarations = substr($text, $body, $i - $body);
                $start = $i + 1;

                // An at-rule's block, or a rule with blocks inside, goes whole.
                if ((strpos(ltrim($prelude), '@') === 0) || (strpos($declarations, '{') !== false)) {
                    continue;
                }

                $selectors = pg_svg_sanitize_selectors($prelude, $root_id, $prefix);
                $style = pg_svg_sanitize_style($declarations, $prefix, $root_id);

                if (($selectors !== '') && ($style !== '')) {
                    $rules[] = $selectors . '{' . $style . '}';
                }
            }
        } elseif (($c === ';') && ($depth === 0)) {
            // A statement at-rule (@import, @charset) ends here.
            $start = $i + 1;
        }
    }

    return $rules ? implode("\n", $rules) : null;
}

/**
 * Copies $source's kept attributes and children into $target; with the
 * context's prefix, ids (but the root's), classes, href and url(#…)
 * references get it.
 */
function pg_svg_sanitize_copy($source, $target, $out, $elements, $common, &$context)
{
    $name = $source->localName;
    $allowed = array_flip(array_merge($common, $elements[$name]));
    $prefix = $context['prefix'];
    $root_id = $context['root_id'];
    $is_root = ($target === $out->documentElement);

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

            if (($value !== '') && ($value[0] === '#')) {
                $value = '#' . pg_svg_sanitize_rename(substr($value, 1), $prefix, $root_id);
            }
        } elseif ($key === 'style') {
            $value = pg_svg_sanitize_style($value, $prefix, $root_id);
        } elseif (!pg_svg_sanitize_value_ok($value)) {
            $value = '';
        } elseif (($key === 'id') && !$is_root) {
            $value = pg_svg_sanitize_rename($value, $prefix, $root_id);
        } elseif ($key === 'class') {
            $names = preg_split('/\s+/', trim($value), -1, PREG_SPLIT_NO_EMPTY);

            foreach ($names as $i => $item) {
                $names[$i] = pg_svg_sanitize_rename($item, $prefix);
            }

            $value = implode(' ', $names);
        } else {
            $value = pg_svg_sanitize_rename_urls($value, $prefix, $root_id);
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
            $sheet = pg_svg_sanitize_sheet($child->textContent, $root_id, $prefix);

            if ($sheet === null) {
                continue;
            }

            $context['sheet'] = true;

            $element = $out->createElementNS('http://www.w3.org/2000/svg', 'style');
            $element->appendChild($out->createTextNode($sheet));
            $target->appendChild($element);
            continue;
        }

        $element = $out->createElementNS('http://www.w3.org/2000/svg', $child->localName);
        $target->appendChild($element);
        pg_svg_sanitize_copy($child, $element, $out, $elements, $common, $context);
    }
}

/**
 * An SVG rebuilt from the allowlist: listed elements and attributes only,
 * href to an id of the file or an embedded raster picture, no script, event
 * handler, foreignObject, link, animation, outside url(), @import, document
 * type or entity; style sheets keep only plain rules, scoped to the root's id
 * or, for rules of the file's own ids and classes, to their prefix.
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

    // Checked again on the parsed document: another encoding (UTF-16)
    // hides a document type from the text search above.
    if ($loaded && ($source->doctype !== null)) {
        return '';
    }

    $root = $loaded ? $source->documentElement : null;

    if (!$root || ($root->localName !== 'svg') || ($root->namespaceURI !== 'http://www.w3.org/2000/svg')) {
        return '';
    }

    // Style sheets are scoped to the root's id: its own when it is a plain
    // name, else one made from the file. A file with a sheet that keeps a
    // rule also gets its ids and classes prefixed (pg_svg_sanitize_prefix()).
    $id = $root->getAttribute('id');
    $root_id = preg_match('/^[A-Za-z][A-Za-z0-9_-]{0,63}$/', $id) ? $id : 'pgsvg-' . substr(sha1($svg), 0, 10);
    $prefix = '';

    foreach ($source->getElementsByTagNameNS('http://www.w3.org/2000/svg', 'style') as $style) {
        if (pg_svg_sanitize_sheet($style->textContent, $root_id) !== null) {
            $prefix = pg_svg_sanitize_prefix($root_id);
            break;
        }
    }

    $context = array(
        'root_id' => $root_id,
        'prefix'  => $prefix,
        'sheet'   => false);

    $out = new DOMDocument('1.0', 'UTF-8');
    $element = $out->createElementNS('http://www.w3.org/2000/svg', 'svg');
    $out->appendChild($element);
    pg_svg_sanitize_copy($root, $element, $out, pg_svg_sanitize_elements(), pg_svg_sanitize_common_attributes(), $context);

    if ($context['sheet'] || ($prefix !== '')) {
        $element->setAttribute('id', $root_id);
    }

    $text = $out->saveXML($out->documentElement);

    return is_string($text) ? $text : '';
}
