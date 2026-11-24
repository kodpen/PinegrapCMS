<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// A module of functions.php: a picture's focus point, the part of the
// picture that stays in view when its frame cuts it. The value is the
// picture's object position (an Image element) or background position (a
// background picture): a pair of percentages, or a pair of words typed
// before, kept as typed until it is moved. A value per screen size goes out
// as three custom properties and the pg-focus class, whose rule (the
// designer's block rules, pg_designer_block_css('pg-focus')) picks one per
// media query. The designer's twins are the _sdFocus*() functions of
// assets/js/style_designer.src.js; both sides must write the same text.
//
// Loaded by functions.php through require_once, never on its own.
if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/**
 * Reads a position as a pair of percentages: '30% 20%', one percentage (the
 * other is 50 %), '0', and the words left, center, right, top and bottom in
 * either order ('center top', 'top center', 'left 20%'). Percentages are
 * clamped to 0..100.
 *
 * @return array|null  array(x, y) as floats; null for a value that is not a
 *                     position in percentages (lengths, three parts, junk)
 */
function pg_focus_parse($value)
{
    $parts = preg_split('/\s+/', strtolower(trim((string)$value)), -1, PREG_SPLIT_NO_EMPTY);
    $n = count($parts);
    if ($n < 1 || $n > 2) return null;
    $words = array('left' => array('x', 0.0), 'right' => array('x', 100.0), 'top' => array('y', 0.0), 'bottom' => array('y', 100.0), 'center' => array('', 50.0));
    $read = array();
    foreach ($parts as $p) {
        if (isset($words[$p])) {
            $read[] = $words[$p];
        } elseif (preg_match('/^(-?(?:\d+\.?\d*|\.\d+))%$/', $p, $m) || preg_match('/^(0)$/', $p, $m)) {
            $read[] = array('n', max(0.0, min(100.0, (float)$m[1])));
        } else {
            return null;
        }
    }
    if ($n === 1) {
        return $read[0][0] === 'y' ? array(50.0, $read[0][1]) : array($read[0][1], 50.0);
    }
    // Words may come in either order; a number keeps its place (x first).
    list($a, $b) = $read;
    if ($a[0] === 'y' || $b[0] === 'x') {
        if ($a[0] === 'n' || $b[0] === 'n') return null;
        list($a, $b) = array($b, $a);
    }
    if ($a[0] === 'y' || $b[0] === 'x') return null;
    return array($a[1], $b[1]);
}

/**
 * The pair as the designer writes it: whole percentages, 0..100.
 */
function pg_focus_format($x, $y)
{
    $x = (int)round(max(0, min(100, (float)$x)));
    $y = (int)round(max(0, min(100, (float)$y)));
    return $x . '% ' . $y . '%';
}

/**
 * The screen a width belongs to, as Bootstrap's breakpoints cut them: a
 * phone below 768 px, a tablet below 992 px, a desktop from there on and
 * for the full width (0 or empty).
 */
function pg_focus_screen($width)
{
    $w = (int)$width;
    if ($w <= 0 || $w >= 992) return 'desktop';
    return $w < 768 ? 'phone' : 'tablet';
}

/**
 * An Image element's values per screen from its props: objectPosition (the
 * value of every screen, as before), objectPositionTablet and
 * objectPositionPhone.
 *
 * @return array  'desktop', 'tablet', 'phone' => string ('' for none)
 */
function pg_focus_values($props)
{
    $props = (array)$props;
    $out = array();
    foreach (array('desktop' => 'objectPosition', 'tablet' => 'objectPositionTablet', 'phone' => 'objectPositionPhone') as $screen => $key) {
        $out[$screen] = isset($props[$key]) && is_scalar($props[$key]) ? trim((string)$props[$key]) : '';
    }
    return $out;
}

/**
 * The props with $values written: an empty value removes its prop.
 */
function pg_focus_image_props($props, $values)
{
    $props = (array)$props;
    foreach (array('desktop' => 'objectPosition', 'tablet' => 'objectPositionTablet', 'phone' => 'objectPositionPhone') as $screen => $key) {
        // Removed first, so the three props always come in this order.
        $v = isset($values[$screen]) ? (string)$values[$screen] : '';
        unset($props[$key]);
        if ($v !== '') $props[$key] = $v;
    }
    return $props;
}

/**
 * The value a screen shows: its own, else the next larger screen's.
 */
function pg_focus_for_screen($values, $screen)
{
    $order = array('phone', 'tablet', 'desktop');
    $from = array_search($screen, $order, true);
    if ($from === false) $from = 2;
    for ($i = $from; $i < 3; $i++) {
        if (isset($values[$order[$i]]) && (string)$values[$order[$i]] !== '') return (string)$values[$order[$i]];
    }
    return '';
}

/**
 * $values with $screen's value set; a value set on the desktop is every
 * screen's that has none of its own.
 */
function pg_focus_set($values, $screen, $value)
{
    if (!in_array($screen, array('desktop', 'tablet', 'phone'), true)) $screen = 'desktop';
    $values[$screen] = trim((string)$value);
    return $values;
}

/**
 * "Same on every screen": the desktop's value, the others removed.
 */
function pg_focus_same_everywhere($values)
{
    return array('desktop' => isset($values['desktop']) ? (string)$values['desktop'] : '', 'tablet' => '', 'phone' => '');
}

/**
 * Whether a smaller screen has a value of its own that can be written (one
 * that reads as a position, pg_focus_parse()).
 */
function pg_focus_has_screens($values)
{
    foreach (array('tablet', 'phone') as $s) {
        if (isset($values[$s]) && pg_focus_parse($values[$s]) !== null) return true;
    }
    return false;
}

/**
 * The style declarations for $values, $css_prop being object-position or
 * background-position. One value is written as before ("object-position:
 * center top"), so a page with no value per screen draws byte for byte as
 * it did. With a smaller screen's own value, the three custom properties,
 * each filled from the next larger screen, and the position reading
 * --pg-focus (set per media query by the pg-focus rule), the desktop's
 * value its fallback where the rule is missing.
 *
 * @return string  declarations joined by ';', '' for none
 */
function pg_focus_decls($values, $css_prop)
{
    $d = isset($values['desktop']) ? trim((string)$values['desktop']) : '';
    if (!pg_focus_has_screens($values)) {
        return $d !== '' ? $css_prop . ':' . $d : '';
    }
    $t = isset($values['tablet']) && pg_focus_parse($values['tablet']) !== null ? trim((string)$values['tablet']) : '';
    $p = isset($values['phone']) && pg_focus_parse($values['phone']) !== null ? trim((string)$values['phone']) : '';
    $d = $d !== '' ? $d : '50% 50%';
    $t = $t !== '' ? $t : $d;
    $p = $p !== '' ? $p : $t;
    return $css_prop . ':var(--pg-focus,' . $d . ');--pg-focus-d:' . $d . ';--pg-focus-t:' . $t . ';--pg-focus-p:' . $p;
}

/**
 * An Image element's focus style (pg_focus_decls() on its props) and the
 * class it needs ('pg-focus' with a value per screen, else '').
 */
function pg_focus_image_style($props)
{
    return pg_focus_decls(pg_focus_values($props), 'object-position');
}

function pg_focus_image_class($props)
{
    return pg_focus_has_screens(pg_focus_values($props)) ? 'pg-focus' : '';
}

/**
 * A style attribute's declarations as array(name, value) pairs, a ';'
 * inside parentheses or quotes (a data: address) kept in its value.
 */
function _pg_focus_style_split($style)
{
    $style = (string)$style;
    $out = array();
    $cur = '';
    $depth = 0;
    $quote = '';
    $len = strlen($style);
    for ($i = 0; $i <= $len; $i++) {
        $c = $i < $len ? $style[$i] : ';';
        if ($quote !== '') {
            if ($c === $quote) $quote = '';
        } elseif ($c === '"' || $c === "'") {
            $quote = $c;
        } elseif ($c === '(') {
            $depth++;
        } elseif ($c === ')') {
            $depth = max(0, $depth - 1);
        } elseif ($c === ';' && $depth === 0) {
            $colon = strpos($cur, ':');
            if ($colon !== false && trim(substr($cur, 0, $colon)) !== '') {
                $out[] = array(strtolower(trim(substr($cur, 0, $colon))), trim(substr($cur, $colon + 1)));
            }
            $cur = '';
            continue;
        }
        $cur .= $c;
    }
    return $out;
}

/**
 * A background picture's values per screen from its node's style
 * attribute: the custom properties pg_focus_style_write() left, else the
 * background-position. A smaller screen's value equal to the next larger
 * one's is no value of its own.
 */
function pg_focus_style_read($style)
{
    $decl = array();
    foreach (_pg_focus_style_split($style) as $pair) {
        $decl[$pair[0]] = $pair[1];
    }
    if (isset($decl['--pg-focus-d'])) {
        $d = $decl['--pg-focus-d'];
        $t = isset($decl['--pg-focus-t']) ? $decl['--pg-focus-t'] : $d;
        $p = isset($decl['--pg-focus-p']) ? $decl['--pg-focus-p'] : $t;
        return array('desktop' => $d, 'tablet' => $t !== $d ? $t : '', 'phone' => $p !== $t ? $p : '');
    }
    return array('desktop' => isset($decl['background-position']) ? $decl['background-position'] : '', 'tablet' => '', 'phone' => '');
}

/**
 * The style attribute with $values written as the background's focus: the
 * declarations of pg_focus_decls() where the background-position stood (at
 * the end when there was none), every other declaration kept in its place.
 * The node takes the pg-focus class while pg_focus_has_screens($values).
 */
function pg_focus_style_write($style, $values)
{
    $mine = array('background-position', '--pg-focus-d', '--pg-focus-t', '--pg-focus-p');
    $decls = pg_focus_decls($values, 'background-position');
    $parts = array();
    $placed = false;
    foreach (_pg_focus_style_split($style) as $pair) {
        if (in_array($pair[0], $mine, true)) {
            if (!$placed && $decls !== '') $parts[] = $decls;
            $placed = true;
            continue;
        }
        $parts[] = $pair[0] . ':' . $pair[1];
    }
    if (!$placed && $decls !== '') $parts[] = $decls;
    return implode(';', $parts);
}
