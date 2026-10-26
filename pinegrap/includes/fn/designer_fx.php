<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Interactions of visual editor nodes: one effect per node, kept in the
 * node's props as `_fx`, written on the page as data-pg-fx-* attributes and
 * played by assets/js/pg_fx.src.js (CSS transitions and IntersectionObserver,
 * nothing under prefers-reduced-motion).
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
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
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
    return array('fade', 'slide-up', 'slide-down', 'slide-left', 'slide-right', 'scale', 'sticky', 'parallax');
}

/**
 * The triggers of a moving effect; sticky and parallax have none.
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
 * timing and easing for sticky and parallax, `stagger` outside load and
 * view, `repeat` outside view, `offset` outside sticky, `speed` outside
 * parallax, `distance` for fade, sticky and parallax.
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
    $num = function ($name) use ($fx, $nums) {
        list($min, $max, $def) = $nums[$name];
        if (!isset($fx[$name]) || !is_numeric($fx[$name])) return $def;
        return (int) max($min, min($max, round((float) $fx[$name])));
    };

    if ($effect === 'sticky') {
        $out['offset'] = $num('offset');
        return $out;
    }
    if ($effect === 'parallax') {
        $out['speed'] = $num('speed');
        return $out;
    }

    $triggers = pg_fx_triggers();
    $trigger = isset($fx['trigger']) && is_string($fx['trigger']) ? strtolower($fx['trigger']) : '';
    $out['trigger'] = in_array($trigger, $triggers, true) ? $trigger : $triggers[0];

    $easings = pg_fx_easings();
    $easing = isset($fx['easing']) && is_string($fx['easing']) ? strtolower($fx['easing']) : '';
    $out['duration'] = $num('duration');
    $out['delay']    = $num('delay');
    $out['easing']   = in_array($easing, $easings, true) ? $easing : $easings[0];
    if ($effect !== 'fade') $out['distance'] = $num('distance');
    if ($out['trigger'] === 'load' || $out['trigger'] === 'view') $out['stagger'] = $num('stagger');
    if ($out['trigger'] === 'view') $out['repeat'] = !empty($fx['repeat']);

    return $out;
}

/**
 * The data-pg-fx-* attributes of a node's interaction, '' without one.
 * Order: effect, trigger, then the non-default values in a fixed order.
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
    foreach (array('duration', 'delay', 'easing', 'stagger', 'distance', 'offset', 'speed') as $name) {
        if (!isset($fx[$name])) continue;
        if ($name === 'easing') {
            $easings = pg_fx_easings();
            if ($fx[$name] !== $easings[0]) $out .= ' data-pg-fx-easing="' . $fx[$name] . '"';
            continue;
        }
        if ($fx[$name] !== $nums[$name][2]) $out .= ' data-pg-fx-' . $name . '="' . $fx[$name] . '"';
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
