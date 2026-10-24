<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// A module of functions.php: value conditions of system widget nodes.
//
// Loaded by functions.php through require_once, never on its own.
//
// A node is drawn only when a value passes a test:
//   props._bindings.show_if   = '__discount_percent'   (the token, like any binding)
//   props._bindParams.show_if = {op: 'gt', value: '0'} (missing: not_empty)
// _render_tree_node() wraps such a node in markers while a widget renders,
// and the finish pass (widgets_finish.php) keeps or cuts it by the value the
// renderer filled in. Everything here is pure: the spec, the reading of a
// value as text or as a number, and the test itself. The editor's twin of
// the test reads tests/fixtures/widget_condition_vectors.json too.
if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// The operators, in the order the editor lists them: whether each needs an
// operand, and whether it compares numbers only.
function pg_sw_cond_ops()
{
    return array(
        'not_empty'    => array('operand' => false, 'numeric' => false),
        'empty'        => array('operand' => false, 'numeric' => false),
        'eq'           => array('operand' => true,  'numeric' => false),
        'neq'          => array('operand' => true,  'numeric' => false),
        'contains'     => array('operand' => true,  'numeric' => false),
        'not_contains' => array('operand' => true,  'numeric' => false),
        'starts'       => array('operand' => true,  'numeric' => false),
        'gt'           => array('operand' => true,  'numeric' => true),
        'gte'          => array('operand' => true,  'numeric' => true),
        'lt'           => array('operand' => true,  'numeric' => true),
        'lte'          => array('operand' => true,  'numeric' => true),
        'multiple_of'  => array('operand' => true,  'numeric' => true),
    );
}

// A node's condition: null when it has none, false when it has one that
// cannot be evaluated (the node is then drawn nowhere), otherwise
// array('token' => ..., 'op' => ..., 'operand' => ...). The token is
// sanitised the way _apply_bindings() sanitises every bound field, so the
// carrier asks the renderer for the same value a text binding would get.
function pg_sw_cond_spec($props)
{
    if (!is_array($props) || !isset($props['_bindings']) || !is_array($props['_bindings'])) return null;
    if (!array_key_exists('show_if', $props['_bindings'])) return null;

    // An empty binding is no binding, as in _apply_bindings().
    $raw = $props['_bindings']['show_if'];
    if ($raw === null || $raw === '' || $raw === false) return null;
    if (!is_string($raw)) return false;

    $token = preg_replace('/[^a-z0-9_\-]/i', '', $raw);
    if ($token === '' || !pg_sw_cond_token_allowed($token)) return false;

    $params = (isset($props['_bindParams']['show_if']) && is_array($props['_bindParams']['show_if']))
        ? $props['_bindParams']['show_if'] : array();

    $op = 'not_empty';
    if (isset($params['op']) && $params['op'] !== '') {
        if (!is_string($params['op'])) return false;
        $op = $params['op'];
    }

    $ops = pg_sw_cond_ops();
    if (!isset($ops[$op])) return false;

    $operand = '';
    if ($ops[$op]['operand']) {
        $operand = (isset($params['value']) && is_scalar($params['value'])) ? trim((string)$params['value']) : '';
        if ($operand === '') return false;
    }

    return array('token' => $token, 'op' => $op, 'operand' => $operand);
}

// The tokens a condition may not read. A carrier repeats its token in the
// template, so:
//   - a token a renderer looks for in the template to decide whether the
//     design placed it (the edit link, the empty-list message, the item form,
//     gift card and recurring blocks, the variant picker) would count as
//     placed and the renderer would stop adding its own;
//   - the catalog's filter blocks are decided through the bindings it
//     records, and are markup, not values;
//   - __page_link and __link_label are sentinels rewritten per node.
// Every *_html token is markup a renderer builds. A plain list on purpose:
// tools/check_bindings.php reads quoted '__name' map keys in these files as
// tokens a renderer fills.
function pg_sw_cond_denied_tokens()
{
    return array(
        '__variant_picker',
        '__filter_chips',
        '__filters_button',
        '__filters_offcanvas',
        '__clear_filters_button',
        '__edit_url',
        '__comment_edit_url',
        '__empty_message',
        '__page_link',
        '__link_label',
    );
}

// Whether a condition may read $token (see pg_sw_cond_denied_tokens()).
// The numbered forms of the two sentinels (__page_link_0, __link_label_2)
// are refused with them.
function pg_sw_cond_token_allowed($token)
{
    $token = (string)$token;
    if ($token === '') return false;
    $lower = strtolower($token);
    if (substr($lower, -5) === '_html') return false;
    if (in_array($lower, pg_sw_cond_denied_tokens(), true)) return false;
    return !preg_match('/^__(page_link|link_label)_\d+$/', $lower);
}

// Spaces as one reads them: a no-break space is a space, runs of white
// space are one, none at either end.
function pg_sw_cond_plain($s)
{
    $s = str_replace("\xC2\xA0", ' ', (string)$s);
    $collapsed = preg_replace('/\s+/u', ' ', $s);
    // Not valid UTF-8: collapse byte-wise rather than lose the value.
    if ($collapsed === null) $collapsed = preg_replace('/\s+/', ' ', $s);
    return trim((string)$collapsed);
}

// What a visitor reads in a filled carrier: the markup and comments go, the
// entities become characters.
function pg_sw_cond_text($html)
{
    $text = html_entity_decode(strip_tags((string)$html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    return pg_sw_cond_plain($text);
}

// Text folded for comparison: lower case, and the dot a capital İ leaves on
// its small i dropped, so 'İstanbul' equals 'istanbul'.
function pg_sw_cond_fold($s)
{
    return str_replace("\xCC\x87", '', mb_strtolower((string)$s, 'UTF-8'));
}

// The number a value shows, or null when it shows none. Amounts are read
// the way the site writes them: an optional minus first (pg_format_money()
// puts it before the symbol: '-₺12,50'), an optional symbol or code, the
// figure, an optional code, symbol or percent sign after it
// (pg_visitor_money(): '€12,50 EUR'). The figure is read by
// pg_parse_amount(), which takes 1.234,56 and 1,234.56; a single separator
// with three digits after it (1.500) follows the site language.
function pg_sw_cond_number($s)
{
    $s = pg_sw_cond_plain($s);
    if ($s === '') return null;
    $re = '/^(-?)\s*(?:\p{Sc}|[a-z]{2,3}\$?)?\s*([0-9., ]*[0-9][0-9., ]*?)\s*(?:\p{Sc}|[a-z]{2,3}\$?|%)?$/iu';
    if (!preg_match($re, $s, $m)) return null;
    $number = pg_parse_amount($m[2]);
    return ($m[1] === '-') ? -$number : $number;
}

// Whether a value shows a number (pg_sw_cond_number()).
function pg_sw_cond_is_number($s)
{
    return pg_sw_cond_number($s) !== null;
}

// Whether a number is whole and small enough to be exact as a float.
function pg_sw_cond_is_whole($n)
{
    return is_float($n) && is_finite($n) && (abs($n) < 9007199254740992.0) && (floor($n) === $n);
}

// The test. $value is the text a carrier holds (pg_sw_cond_text()), $operand
// the text the operator typed.
//   empty, not_empty            the text
//   eq, neq                     numbers when both sides are numbers, else folded text
//   contains, not_contains,     folded text
//   starts
//   gt, gte, lt, lte            numbers only: false when either side is not one
//   multiple_of                 whole numbers only, operand above 0
// An unknown operator is false.
function pg_sw_cond_eval($op, $value, $operand)
{
    $value   = pg_sw_cond_plain($value);
    $operand = pg_sw_cond_plain($operand);

    switch ((string)$op) {
        case 'not_empty':
            return $value !== '';
        case 'empty':
            return $value === '';
        case 'eq':
        case 'neq':
            $a = pg_sw_cond_number($value);
            $b = pg_sw_cond_number($operand);
            $same = ($a !== null && $b !== null)
                ? (abs($a - $b) < 0.000001)
                : (pg_sw_cond_fold($value) === pg_sw_cond_fold($operand));
            return ($op === 'eq') ? $same : !$same;
        case 'contains':
        case 'not_contains':
            $found = ($operand === '') || (mb_strpos(pg_sw_cond_fold($value), pg_sw_cond_fold($operand), 0, 'UTF-8') !== false);
            return ($op === 'contains') ? $found : !$found;
        case 'starts':
            $prefix = pg_sw_cond_fold($operand);
            return mb_substr(pg_sw_cond_fold($value), 0, mb_strlen($prefix, 'UTF-8'), 'UTF-8') === $prefix;
        case 'gt':
        case 'gte':
        case 'lt':
        case 'lte':
            $a = pg_sw_cond_number($value);
            $b = pg_sw_cond_number($operand);
            if ($a === null || $b === null) return false;
            if ($op === 'gt')  return $a > $b;
            if ($op === 'gte') return $a >= $b;
            if ($op === 'lt')  return $a < $b;
            return $a <= $b;
        case 'multiple_of':
            $a = pg_sw_cond_number($value);
            $b = pg_sw_cond_number($operand);
            if (!pg_sw_cond_is_whole($a) || !pg_sw_cond_is_whole($b) || $b <= 0) return false;
            return fmod($a, $b) == 0;
    }
    return false;
}
