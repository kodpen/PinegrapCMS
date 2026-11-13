<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Theme parts of a Legacy design turned into what the designer draws itself,
 * for its trial design on Bootstrap 5.3.8 (legacy_bootstrap_trial.php) and
 * the pages moved to it (legacy_bootstrap_move.php):
 *
 * - a photo background (a .background-image-holder holding one image, which
 *   the theme's scripts.js turns into a CSS background) becomes a box with
 *   the photo as its background-image, which the editor's Background panel
 *   edits; its class is PG_LBS_PHOTO_CLASS, which the trial's sheet copies
 *   give the holder's rules (pg_lbs_css() with twins on);
 * - a flexslider started by scripts.js (.slider-all-controls,
 *   .slider-paging-controls, .slider-arrow-controls over a ul.slides)
 *   becomes a Bootstrap carousel with the same controls, never playing by
 *   itself (designer-block-motion);
 * - a lightbox link (data-lightbox, or a link of a .lightbox-grid) becomes a
 *   Fancybox link (data-fancybox), the lightbox the designer loads.
 *
 * A part with no twin (the logo carousel, the tweets and thumbnail sliders,
 * a holder that holds a video) stays as it is and is counted, so its script
 * row stays (pg_lbs_trial_assets()).
 *
 * Pure: no query, no file. A second pass changes nothing.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// The class of a photo background's twin.
const PG_LBS_PHOTO_CLASS = 'legacy-photo';

/**
 * Whether a class attribute holds a name.
 *
 * Pure: no query.
 */
function pg_lbs_twin_has($class, $name)
{
    return preg_match('/(^|\s)' . preg_quote($name, '/') . '(\s|$)/', (string)$class) === 1;
}

/**
 * A class attribute with names taken out and others added (each once).
 *
 * Pure: no query.
 *
 * @param string $class
 * @param array  $remove
 * @param array  $add
 * @return string
 */
function pg_lbs_twin_classes($class, $remove, $add)
{
    $names = preg_split('/\s+/', trim((string)$class), -1, PREG_SPLIT_NO_EMPTY);
    $names = array_values(array_diff($names, (array)$remove));
    foreach ((array)$add as $name) {
        if (!in_array($name, $names, true)) $names[] = $name;
    }
    return implode(' ', $names);
}

/**
 * The value of a node's _attrs entry, null when it has none.
 *
 * Pure: no query.
 */
function pg_lbs_twin_attr($node, $name)
{
    foreach ((array)($node['props']['_attrs'] ?? array()) as $a) {
        if (is_array($a) && ($a['name'] ?? '') === $name) return (string)($a['value'] ?? '');
    }
    return null;
}

/**
 * A node with an _attrs entry set (null takes it out).
 *
 * Pure: no query.
 */
function pg_lbs_twin_set_attr($node, $name, $value)
{
    $attrs = array();
    $done = false;
    foreach ((array)($node['props']['_attrs'] ?? array()) as $a) {
        if (is_array($a) && ($a['name'] ?? '') === $name) {
            if ($value !== null && !$done) $attrs[] = array('name' => $name, 'value' => (string)$value);
            $done = true;
            continue;
        }
        $attrs[] = $a;
    }
    if (!$done && $value !== null) $attrs[] = array('name' => $name, 'value' => (string)$value);
    if ($attrs) {
        $node['props']['_attrs'] = $attrs;
    } else {
        unset($node['props']['_attrs']);
    }
    return $node;
}

/**
 * The slider kinds scripts.js starts with flexslider: class => the controls
 * of its twin. The kinds with no twin are listed with ''.
 *
 * Pure: no query.
 *
 * @return array  class => 'all' (arrows and dots), 'paging' (dots),
 *                'arrow' (arrows) or '' (no twin)
 */
function pg_lbs_twin_slider_kinds()
{
    return array('slider-all-controls' => 'all', 'slider-paging-controls' => 'paging', 'slider-arrow-controls' => 'arrow',
                 'slider-thumb-controls' => '', 'logo-carousel' => '', 'tweets-slider' => '');
}

// A semantic node of a tag.
function pg_lbs_twin_is($node, $tag)
{
    return is_array($node) && ($node['type'] ?? '') === 'semantic' && strtolower((string)($node['props']['tag'] ?? '')) === $tag;
}

/**
 * A photo background's twin, null when the node is no holder of one image.
 *
 * Pure: no query.
 */
function pg_lbs_twin_photo($node)
{
    if (!is_array($node) || ($node['type'] ?? '') !== 'semantic') return null;
    if (!pg_lbs_twin_has($node['props']['cssClass'] ?? '', 'background-image-holder')) return null;
    $children = array_values((array)($node['children'] ?? array()));
    if (count($children) !== 1) return null;
    $img = $children[0];
    if (!is_array($img) || ($img['type'] ?? '') !== 'content' || ($img['props']['contentType'] ?? '') !== 'image') return null;
    $src = trim((string)($img['props']['src'] ?? ''));
    if ($src === '' || $src === '#') return null;

    // A quote or a parenthesis would end the url(): written escaped.
    $url = str_replace(array('"', '(', ')', "\n", "\r"), array('%22', '%28', '%29', '', ''), $src);
    $style = trim((string)pg_lbs_twin_attr($node, 'style'));
    if ($style !== '' && substr($style, -1) !== ';') $style .= ';';
    $style = trim($style . ' background-image: url("' . $url . '"); background-size: cover; background-position: center;');
    $node['props']['cssClass'] = pg_lbs_twin_classes($node['props']['cssClass'], array('background-image-holder'), array(PG_LBS_PHOTO_CLASS, 'fadeIn'));
    $node = pg_lbs_twin_set_attr($node, 'style', $style);
    $node['children'] = array();
    return $node;
}

/**
 * A slider's twin: a Bootstrap carousel over the same slides, null when
 * the node is no slider of a kind with a twin or its slides are not a
 * ul.slides of li items.
 *
 * Pure: no query.
 *
 * @param array  $node
 * @param array  $labels  'prev', 'next', 'slide' (with {var:1})
 * @return array|null
 */
function pg_lbs_twin_slider($node, $labels)
{
    if (!is_array($node) || ($node['type'] ?? '') !== 'semantic') return null;
    $class = (string)($node['props']['cssClass'] ?? '');
    $kind = '';
    $trigger = '';
    foreach (pg_lbs_twin_slider_kinds() as $name => $k) {
        if (pg_lbs_twin_has($class, $name)) {
            if ($k === '') return null;
            $kind = $k;
            $trigger = $name;
        }
    }
    if ($kind === '') return null;
    $at = -1;
    foreach ((array)($node['children'] ?? array()) as $i => $child) {
        if (pg_lbs_twin_is($child, 'ul') && pg_lbs_twin_has($child['props']['cssClass'] ?? '', 'slides')) $at = $i;
    }
    if ($at < 0) return null;
    $list = $node['children'][$at];
    $slides = array_values((array)($list['children'] ?? array()));
    if (!$slides) return null;
    foreach ($slides as $li) {
        if (!pg_lbs_twin_is($li, 'li')) return null;
    }

    $id = trim((string)($node['props']['id'] ?? ''));
    if ($id === '') {
        $id = 'legacy-slider-' . substr(md5(json_encode($node)), 0, 8);
        $node['props']['id'] = $id;
    }
    $node['props']['cssClass'] = pg_lbs_twin_classes($class, array($trigger), array('carousel', 'slide'));
    $list['props']['cssClass'] = pg_lbs_twin_classes($list['props']['cssClass'], array(), array('carousel-inner'));
    foreach ($slides as $i => $li) {
        $slides[$i]['props']['cssClass'] = pg_lbs_twin_classes($li['props']['cssClass'] ?? '', array('active'), $i === 0 ? array('carousel-item', 'active') : array('carousel-item'));
    }
    $list['children'] = $slides;

    $children = array();
    if ($kind === 'all' || $kind === 'paging') {
        $dots = array();
        foreach ($slides as $i => $li) {
            $attrs = array(array('name' => 'type', 'value' => 'button'), array('name' => 'data-bs-target', 'value' => '#' . $id),
                           array('name' => 'data-bs-slide-to', 'value' => (string)$i),
                           array('name' => 'aria-label', 'value' => str_replace('{var:1}', (string)($i + 1), (string)$labels['slide'])));
            if ($i === 0) $attrs[] = array('name' => 'aria-current', 'value' => 'true');
            $dots[] = array('type' => 'semantic', 'props' => array('tag' => 'button', 'cssClass' => $i === 0 ? 'active' : '', '_attrs' => $attrs), 'children' => array());
        }
        $children[] = array('type' => 'semantic', 'props' => array('tag' => 'div', 'cssClass' => 'carousel-indicators'), 'children' => $dots);
    }
    foreach ((array)$node['children'] as $i => $child) {
        $children[] = ($i === $at) ? $list : $child;
    }
    if ($kind === 'all' || $kind === 'arrow') {
        foreach (array('prev', 'next') as $way) {
            $children[] = array('type' => 'semantic', 'props' => array('tag' => 'button', 'cssClass' => 'carousel-control-' . $way, '_attrs' => array(
                array('name' => 'type', 'value' => 'button'), array('name' => 'data-bs-target', 'value' => '#' . $id),
                array('name' => 'data-bs-slide', 'value' => $way), array('name' => 'aria-label', 'value' => (string)$labels[$way]))),
                'children' => array(array('type' => 'semantic', 'props' => array('tag' => 'span', 'cssClass' => 'carousel-control-' . $way . '-icon',
                    '_attrs' => array(array('name' => 'aria-hidden', 'value' => 'true'))), 'children' => array())));
        }
    }
    $node['children'] = $children;
    return $node;
}

/**
 * The theme names of the parts without a twin found in a piece of HTML (a
 * custom HTML node): they stay with their scripts.
 *
 * Pure: no query.
 *
 * @return array  'slider', 'lightbox' (counts)
 */
function pg_lbs_twin_html_kept($html)
{
    $out = array('slider' => 0, 'lightbox' => 0);
    $html = (string)$html;
    if (strpos($html, '<') === false) return $out;
    foreach (array_keys(pg_lbs_twin_slider_kinds()) as $name) {
        $out['slider'] += preg_match_all('/class\s*=\s*["\'][^"\']*(?<![\w-])' . preg_quote($name, '/') . '(?![\w-])/i', $html);
    }
    $out['lightbox'] = preg_match_all('/\sdata-lightbox\s*=|(?<![\w-])lightbox-grid(?![\w-])/i', $html);
    return $out;
}

/**
 * The lightbox links of a piece of HTML (a paragraph's text) made Fancybox
 * links: data-lightbox="x" becomes data-fancybox="x" ("gallery" for
 * "true" or none), the rest byte for byte.
 *
 * Pure: no query.
 *
 * @return array  'html', 'count'
 */
function pg_lbs_twin_html_links($html)
{
    $count = 0;
    $out = preg_replace_callback('/(\s)data-lightbox(?:\s*=\s*(["\'])(.*?)\2)?(?=[\s\/>])/is', function ($m) use (&$count) {
        $count++;
        $name = isset($m[3]) ? trim($m[3]) : '';
        if ($name === '' || $name === 'true') $name = 'gallery';
        return $m[1] . 'data-fancybox="' . str_replace('"', '&quot;', $name) . '"';
    }, (string)$html);
    return array('html' => ($out === null) ? (string)$html : $out, 'count' => $count);
}

/**
 * A tree with its theme parts turned into their twins (see the file's
 * header). A placed part (shared_ref) is left as it is: its own tree is
 * converted on its own.
 *
 * Pure: no query.
 *
 * @param array $tree
 * @param array $labels  'prev', 'next', 'slide' (aria labels of a
 *                       carousel's controls, 'slide' with {var:1})
 * @return array  'tree', 'twins' ('photo', 'slider', 'lightbox' made),
 *                'kept' ('photo', 'slider', 'lightbox' left as they are)
 */
function pg_lbs_twins($tree, $labels = array())
{
    $labels += array('prev' => 'Previous', 'next' => 'Next', 'slide' => 'Slide {var:1}');
    $acc = array('twins' => array('photo' => 0, 'slider' => 0, 'lightbox' => 0), 'kept' => array('photo' => 0, 'slider' => 0, 'lightbox' => 0));
    $walk = function ($node, $gallery) use (&$walk, &$acc, $labels) {
        if (!is_array($node) || ($node['type'] ?? '') === 'shared_ref') return $node;
        $class = (string)($node['props']['cssClass'] ?? '');

        if (pg_lbs_twin_has($class, 'background-image-holder')) {
            $twin = pg_lbs_twin_photo($node);
            if ($twin !== null) {
                $acc['twins']['photo']++;
                return $twin;
            }
            $acc['kept']['photo']++;
        }
        foreach (pg_lbs_twin_slider_kinds() as $name => $kind) {
            if (!pg_lbs_twin_has($class, $name)) continue;
            $twin = pg_lbs_twin_slider($node, $labels);
            if ($twin !== null) {
                $acc['twins']['slider']++;
                $node = $twin;
            } else {
                $acc['kept']['slider']++;
            }
            break;
        }
        if (pg_lbs_twin_has($class, 'lightbox-grid')) {
            $title = trim((string)pg_lbs_twin_attr($node, 'data-gallery-title'));
            $gallery = ($title !== '') ? $title : 'gallery';
        }
        $link = pg_lbs_twin_is($node, 'a') || (($node['type'] ?? '') === 'content' && ($node['props']['contentType'] ?? '') === 'link');
        if ($link) {
            $own = pg_lbs_twin_attr($node, 'data-lightbox');
            // A link a pass made a Fancybox link already keeps its gallery.
            $done = ($own === null && pg_lbs_twin_attr($node, 'data-fancybox') !== null);
            if (!$done && ($own !== null || $gallery !== null)) {
                $name = ($own !== null && $own !== '' && $own !== 'true') ? $own : (($gallery !== null) ? $gallery : 'gallery');
                $node = pg_lbs_twin_set_attr(pg_lbs_twin_set_attr($node, 'data-lightbox', null), 'data-fancybox', $name);
                $acc['twins']['lightbox']++;
            }
        }
        foreach ((array)($node['props'] ?? array()) as $k => $v) {
            if ($k !== '_attrs' && is_string($v) && strpos($v, '<') !== false) {
                $links = pg_lbs_twin_html_links($v);
                $acc['twins']['lightbox'] += $links['count'];
                $node['props'][$k] = $v = $links['html'];
                $kept = pg_lbs_twin_html_kept($v);
                $acc['kept']['slider'] += $kept['slider'];
                $acc['kept']['lightbox'] += $kept['lightbox'];
            }
        }
        if (!empty($node['children']) && is_array($node['children'])) {
            foreach ($node['children'] as $i => $child) {
                $node['children'][$i] = $walk($child, $gallery);
            }
        }
        return $node;
    };
    $acc['tree'] = $walk($tree, null);
    return $acc;
}

/**
 * The counts of several twin passes added up.
 *
 * Pure: no query.
 */
function pg_lbs_twins_add($a, $b)
{
    $out = array();
    foreach (array('twins', 'kept') as $k) {
        foreach (array('photo', 'slider', 'lightbox') as $part) {
            $out[$k][$part] = (int)($a[$k][$part] ?? 0) + (int)($b[$k][$part] ?? 0);
        }
    }
    return $out;
}

/**
 * The aria labels of a twin carousel's controls, in the panel's language.
 *
 * @return array  pg_lbs_twins()'s $labels
 */
function pg_lbs_twin_labels()
{
    return array('prev' => lang('Previous'), 'next' => lang('Next'), 'slide' => lang('Slide {var:1}'));
}

/**
 * A rule's selector list with a photo twin's copy of each selector that
 * names .background-image-holder, so the twin (PG_LBS_PHOTO_CLASS) wears
 * the holder's rules. Left out: a selector that names it inside :not() (the
 * copy would widen it), one under .parallax (the twin does not move, and
 * the parallax offsets would push its photo up) and a copy already there.
 *
 * Pure: no query. A second pass changes nothing.
 *
 * @param string $prelude  the selector list with the whitespace around it
 * @return string
 */
function pg_lbs_twin_selectors($prelude)
{
    $prelude = (string)$prelude;
    $token = '/\.background-image-holder(?![_a-zA-Z0-9-])/';
    if (!preg_match($token, $prelude)) return $prelude;

    // Split at the commas outside brackets and parentheses.
    $list = array();
    $depth = 0;
    $from = 0;
    $n = strlen($prelude);
    for ($i = 0; $i < $n; $i++) {
        $c = $prelude[$i];
        if ($c === '(' || $c === '[') $depth++;
        if ($c === ')' || $c === ']') $depth = max(0, $depth - 1);
        if ($c === ',' && $depth === 0) {
            $list[] = substr($prelude, $from, $i - $from);
            $from = $i + 1;
        }
    }
    $list[] = substr($prelude, $from);
    $have = array_map('trim', $list);

    $copies = array();
    foreach ($have as $sel) {
        if (!preg_match($token, $sel) || preg_match('/:not\([^)]*\.background-image-holder/', $sel)) continue;
        if (preg_match('/\.parallax(?![_a-zA-Z0-9-])/', $sel)) continue;
        $copy = preg_replace($token, '.' . PG_LBS_PHOTO_CLASS, $sel);
        if (!in_array($copy, $have, true) && !in_array($copy, $copies, true)) $copies[] = $copy;
    }
    if (!$copies) return $prelude;
    preg_match('/\s*$/', $prelude, $tail);
    return rtrim($prelude) . ', ' . implode(', ', $copies) . $tail[0];
}
