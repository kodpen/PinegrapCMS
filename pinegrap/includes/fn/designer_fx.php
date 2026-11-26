<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Interactions of visual editor nodes: one effect per node, kept in the
 * node's props as `_fx`, written on the page as data-pg-fx-* attributes and
 * played by assets/js/pg_fx.src.js (CSS transitions and IntersectionObserver,
 * scroll-linked effects once per frame, a number that counts up once seen,
 * a muted hero video that plays by itself with a pause button, a section
 * whose colours the whole page takes while it holds the window's middle,
 * a pinned scene that shows its layers one at a time as the page scrolls
 * through it, nothing moving under prefers-reduced-motion).
 *
 * pg_fx_normalize() keeps the known fields in their ranges and drops the
 * rest; pg_fx_attrs() writes the effect, its trigger and only the values
 * that differ from the script's defaults. The editor's twins are
 * _sdFxNormalize() and _sdFxAttrs() in assets/js/style_designer.js.
 * pg_fx_inject() adds the head style that keeps entrance targets hidden
 * until the script arms them, and the script itself, to a page that holds
 * a marker.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2026 Erdal Güral. All rights reserved.
 * @license     Proprietary, see license-editors.txt
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/**
 * The effects a node can have.
 *
 * @return array effect names
 */
function pg_fx_effects()
{
    return array('fade', 'slide-up', 'slide-down', 'slide-left', 'slide-right', 'scale',
        'zoom-out', 'blur-in', 'rotate-in', 'reveal', 'sticky', 'parallax',
        'scroll-zoom', 'scroll-fade', 'scroll-rise', 'count', 'autoplay', 'chapter', 'scene');
}

/**
 * The scroll-linked effects and the range of their one field, `from`: the
 * state the node starts in while it is below the window, from which it
 * goes to its own as its middle comes up to the window's middle.
 * effect => array(min, max, default): scroll-zoom a scale in percent,
 * scroll-fade an opacity in percent, scroll-rise a distance in px below
 * its place.
 *
 * @return array
 */
function pg_fx_scroll_from()
{
    return array(
        'scroll-zoom' => array(25, 200, 80),
        'scroll-fade' => array(0, 100, 0),
        'scroll-rise' => array(0, 400, 80),
    );
}

/**
 * The fields of the count effect: name => array(min, max, default). The
 * page holds the number itself; once the node is seen the script counts up
 * to it from zero for `duration` ms after `delay` ms. A count runs longer
 * than a transition, so its duration has its own default.
 *
 * @return array
 */
function pg_fx_count_numbers()
{
    return array(
        'duration' => array(100, 5000, 2000),
        'delay'    => array(0, 5000, 0),
    );
}

/**
 * The triggers of a moving effect; sticky, parallax, the scroll-linked
 * effects, count, chapter and scene have none.
 *
 * @return array trigger names, the default first
 */
function pg_fx_triggers()
{
    return array('view', 'load', 'hover', 'click');
}

/**
 * The timing functions an interaction can use.
 *
 * @return array easing names, the default first
 */
function pg_fx_easings()
{
    return array('ease-out', 'ease', 'ease-in', 'ease-in-out', 'linear');
}

/**
 * The numeric fields: name => array(min, max, default). A value equal to
 * its default is not written on the page; pg_fx.src.js knows the defaults.
 * `distance` has none (0 = the script's own: 24 px for an entrance, 6 px
 * for hover and click).
 *
 * @return array
 */
function pg_fx_numbers()
{
    return array(
        'duration' => array(100, 5000, 600),
        'delay'    => array(0, 5000, 0),
        'stagger'  => array(0, 1000, 0),
        'distance' => array(0, 400, 0),
        'offset'   => array(0, 1000, 0),
        'speed'    => array(5, 100, 20),
    );
}

/**
 * A node's interaction with every field known and in range, or null when
 * it has none (no array, no known effect).
 *
 * Fields that do not apply to the effect or trigger are dropped: a trigger,
 * timing and easing for sticky, parallax and the scroll-linked effects,
 * `stagger` outside load and view, `repeat` outside view, `offset` outside
 * sticky, `speed` outside parallax, `from` outside the scroll-linked
 * effects, `distance` for fade, zoom-out, reveal, sticky, parallax and the
 * scroll-linked effects (blur-in reads it as the blur in px, rotate-in as
 * the angle in degrees). Reveal (a clip that opens from the bottom) is an
 * entrance only: a hover or click trigger is read as view. Count keeps its duration and delay alone, in its own ranges
 * (pg_fx_count_numbers()). Autoplay (a <video> that plays by itself,
 * muted and looping) has no field. Chapter (the page takes the section's
 * background and text colour while it holds the window's middle) keeps
 * its duration alone: the length of the colour change. Scene (a tall
 * section whose layers, marked data-pg-scene-layer, show one at a time in
 * a sticky stage as the page scrolls through it) has no field.
 *
 * @param mixed $fx props._fx
 * @return array|null
 */
function pg_fx_normalize($fx)
{
    if (!is_array($fx) || !isset($fx['effect']) || !is_string($fx['effect'])) return null;
    $effect = strtolower($fx['effect']);
    if (!in_array($effect, pg_fx_effects(), true)) return null;

    $out = array('effect' => $effect);
    $nums = pg_fx_numbers();
    $scroll = pg_fx_scroll_from();
    if (isset($scroll[$effect])) $nums['from'] = $scroll[$effect];
    if ($effect === 'count') $nums = pg_fx_count_numbers() + $nums;
    $num = function ($name) use ($fx, $nums) {
        list($min, $max, $def) = $nums[$name];
        if (!isset($fx[$name]) || !is_numeric($fx[$name])) return $def;
        return (int) max($min, min($max, round((float) $fx[$name])));
    };

    if ($effect === 'autoplay' || $effect === 'scene') {
        return $out;
    }
    if ($effect === 'sticky') {
        $out['offset'] = $num('offset');
        return $out;
    }
    if ($effect === 'parallax') {
        $out['speed'] = $num('speed');
        return $out;
    }
    if (isset($scroll[$effect])) {
        $out['from'] = $num('from');
        return $out;
    }
    if ($effect === 'count') {
        $out['duration'] = $num('duration');
        $out['delay'] = $num('delay');
        return $out;
    }
    if ($effect === 'chapter') {
        $out['duration'] = $num('duration');
        return $out;
    }

    $triggers = pg_fx_triggers();
    $trigger = isset($fx['trigger']) && is_string($fx['trigger']) ? strtolower($fx['trigger']) : '';
    $out['trigger'] = in_array($trigger, $triggers, true) ? $trigger : $triggers[0];
    if ($effect === 'reveal' && ($out['trigger'] === 'hover' || $out['trigger'] === 'click')) $out['trigger'] = $triggers[0];

    $easings = pg_fx_easings();
    $easing = pg_fx_easing_clean(isset($fx['easing']) ? $fx['easing'] : '');
    $out['duration'] = $num('duration');
    $out['delay']    = $num('delay');
    $out['easing']   = $easing !== '' ? $easing : $easings[0];
    if (!in_array($effect, array('fade', 'zoom-out', 'reveal'), true)) $out['distance'] = $num('distance');
    if ($out['trigger'] === 'load' || $out['trigger'] === 'view') $out['stagger'] = $num('stagger');
    if ($out['trigger'] === 'view') $out['repeat'] = !empty($fx['repeat']);

    return $out;
}

/**
 * The four numbers of an easing curve from "x1, y1, x2, y2": each in its
 * shortest form, x1 and x2 between 0 and 1, y1 and y2 held between -0.5
 * and 1.5 (the graph's room above and below). null when the text is not
 * four numbers or an x leaves 0 to 1.
 *
 * @param mixed $text the numbers, comma separated
 * @return array|null four strings
 */
function pg_fx_curve_numbers($text)
{
    if (!is_string($text)) return null;
    $parts = explode(',', $text);
    if (count($parts) !== 4) return null;
    $out = array();
    foreach ($parts as $i => $p) {
        $p = trim($p);
        if (!preg_match('/^-?(\d{1,3}(\.\d*)?|\.\d+)$/', $p)) return null;
        $n = (float)$p;
        if ($i % 2 === 0 && ($n < 0 || $n > 1)) return null;
        if ($i % 2 === 1) $n = max(-0.5, min(1.5, $n));
        $s = rtrim(rtrim(sprintf('%.3F', round($n, 3)), '0'), '.');
        $out[] = ($s === '' || $s === '-0') ? '0' : $s;
    }
    return $out;
}

/**
 * An easing in its written form: a named one (pg_fx_easings()), a curve
 * as cubic-bezier(x1, y1, x2, y2), or a stored curve of the design as
 * var(--pg-var-c-<key>, cubic-bezier(...)) whose numbers are its
 * fallback. '' for anything else. Twin of _sdEaseClean() in the editor.
 *
 * @param mixed $easing the easing as given
 * @return string
 */
function pg_fx_easing_clean($easing)
{
    if (!is_string($easing)) return '';
    $s = strtolower(trim($easing));
    if (in_array($s, pg_fx_easings(), true)) return $s;
    $key = '';
    if (preg_match('/^var\(\s*--pg-var-c-([a-z0-9][a-z0-9-]{0,29})\s*,\s*(.+)\)$/', $s, $m)) {
        $key = $m[1];
        $s = trim($m[2]);
    }
    if (!preg_match('/^cubic-bezier\(([^()]*)\)$/', $s, $m)) return '';
    $p = pg_fx_curve_numbers($m[1]);
    if ($p === null) return '';
    $curve = 'cubic-bezier(' . implode(', ', $p) . ')';
    return $key === '' ? $curve : 'var(--pg-var-c-' . $key . ', ' . $curve . ')';
}

/**
 * The data-pg-fx-* attributes of a node's interaction, '' without one.
 * Order: effect, trigger, then the non-default values in a fixed order;
 * `from` against its effect's own default, a count's duration against
 * its own.
 *
 * @param array $props the node's props
 * @return string attributes, each with a leading space
 */
function pg_fx_attrs($props)
{
    $fx = pg_fx_normalize(isset($props['_fx']) ? $props['_fx'] : null);
    if ($fx === null) return '';

    $out = ' data-pg-fx="' . $fx['effect'] . '"';
    if (isset($fx['trigger'])) $out .= ' data-pg-fx-trigger="' . $fx['trigger'] . '"';

    $nums = pg_fx_numbers();
    if ($fx['effect'] === 'count') $nums = pg_fx_count_numbers() + $nums;
    foreach (array('duration', 'delay', 'easing', 'stagger', 'distance', 'offset', 'speed') as $name) {
        if (!isset($fx[$name])) continue;
        if ($name === 'easing') {
            $easings = pg_fx_easings();
            if ($fx[$name] !== $easings[0]) $out .= ' data-pg-fx-easing="' . htmlspecialchars($fx[$name], ENT_QUOTES) . '"';
            continue;
        }
        if ($fx[$name] !== $nums[$name][2]) $out .= ' data-pg-fx-' . $name . '="' . $fx[$name] . '"';
    }
    if (isset($fx['from'])) {
        $scroll = pg_fx_scroll_from();
        if ($fx['from'] !== $scroll[$fx['effect']][2]) $out .= ' data-pg-fx-from="' . $fx['from'] . '"';
    }
    if (!empty($fx['repeat'])) $out .= ' data-pg-fx-repeat';

    return $out;
}

/**
 * True for an Attributes panel entry the interaction writes itself
 * (data-pg-fx and data-pg-fx-*), left out of the list on a node that has
 * one, so no attribute is written twice.
 *
 * @param string $lname lower-case attribute name
 * @return bool
 */
function pg_fx_attr_name($lname)
{
    return $lname === 'data-pg-fx' || strpos($lname, 'data-pg-fx-') === 0;
}

/**
 * The marker of a node with an interaction: the data-pg-fx attribute
 * itself. The editor's Preview tests the markup with the same expression
 * (_SD_PAGE_SCRIPTS in assets/js/style_designer.js).
 *
 * @return string PCRE pattern
 */
function pg_fx_pattern()
{
    return '/\sdata-pg-fx(?=[\s=>\/])/i';
}

/**
 * The head style of a page with interactions: entrance targets (trigger
 * load or view) stay hidden until pg_fx.src.js arms them, so they do not
 * show and vanish again. Only where the visitor allows motion and runs
 * scripts; a script that never arrives lets them show after 3 s.
 *
 * @return string a <style> element
 */
function pg_fx_head_style()
{
    $hidden = '[data-pg-fx-trigger="load"]:not(.pg-fx-armed),[data-pg-fx-trigger="view"]:not(.pg-fx-armed)';
    return '<style id="pg-fx-css">'
        . '@media (prefers-reduced-motion:no-preference) and (scripting:enabled){'
        . $hidden . '{opacity:0;animation:pg-fx-failsafe 0s 3s forwards}'
        . '@keyframes pg-fx-failsafe{to{opacity:1}}}'
        . '[data-pg-fx="sticky"]{position:sticky;top:0;z-index:1020}'
        . '</style>' . "\n";
}

/**
 * The style and script of interactions, for a page whose HTML holds a
 * marker (pg_fx_pattern()): the style before </head> (at the start of a
 * fragment), the script before the last </body>. Asked once every shared
 * component and system widget is drawn in (get_page_content.php), like
 * pg_scroller_inject().
 *
 * @param string      $content page HTML
 * @param string|null $src     the script's address; null serves the
 *                             pg_fx bundle (pg_js_bundle_urls())
 * @return string
 */
function pg_fx_inject($content, $src = null)
{
    if (stripos($content, 'data-pg-fx') === false || !preg_match(pg_fx_pattern(), $content)) return $content;
    if (strpos($content, 'id="pg-fx-css"') !== false) return $content;

    $urls = ($src === null) ? pg_js_bundle_urls('assets/js/pg_fx.min.js') : array($src);
    $file = '';
    foreach ($urls as $url) {
        $file .= '<script src="' . h($url) . '" defer></script>' . "\n";
    }

    $head = stripos($content, '</head>');
    $content = ($head !== false) ? substr_replace($content, pg_fx_head_style(), $head, 0) : pg_fx_head_style() . $content;

    // The last </body>: an earlier one can belong to markup the page shows
    // as text or carries in an attribute.
    $body = strripos($content, '</body>');
    return ($body !== false) ? substr_replace($content, $file, $body, 0) : $content . $file;
}
