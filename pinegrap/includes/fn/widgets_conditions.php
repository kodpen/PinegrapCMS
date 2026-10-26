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
//     design placed it (the edit links, the empty-list message, the item
//     form, gift card and recurring blocks, the variant picker) would count
//     as placed and the renderer would stop adding its own;
//   - the catalog's filter blocks are decided through the bindings it
//     records, and are markup, not values;
//   - __page_link and __link_label are sentinels rewritten per node.
// Every *_html token is markup a renderer builds. A renderer that looks in
// the saved tree instead reads it through pg_sw_cond_tree_json(), so those
// tokens stay allowed. A plain list on purpose: tools/check_bindings.php
// reads quoted '__name' map keys in these files as tokens a renderer fills.
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

// A widget tree as JSON with its conditions taken out, for a renderer that
// looks in the saved tree for a name to decide whether the design placed
// it (pg_sw_placed(); the order view's not-found message, the cart's and
// the account's states): a condition names a token without placing it. $tree is the decoded tree or its JSON;
// JSON that holds no condition comes back as it is.
function pg_sw_cond_tree_json($tree)
{
    if (is_string($tree)) {
        if (strpos($tree, 'show_if') === false) return $tree;
        $decoded = json_decode($tree, true);
        if (!is_array($decoded)) return $tree;
        $tree = $decoded;
    }
    if (is_array($tree)) _pg_sw_cond_drop_conditions($tree);
    return (string)json_encode($tree);
}

// Takes the condition out of $node and every node under it.
function _pg_sw_cond_drop_conditions(&$node)
{
    if (isset($node['props']['_bindings']) && is_array($node['props']['_bindings'])) {
        unset($node['props']['_bindings']['show_if']);
    }
    if (isset($node['props']['_bindParams']) && is_array($node['props']['_bindParams'])) {
        unset($node['props']['_bindParams']['show_if']);
    }
    if (isset($node['children']) && is_array($node['children'])) {
        foreach ($node['children'] as $i => $child) {
            if (is_array($child)) {
                _pg_sw_cond_drop_conditions($child);
                $node['children'][$i] = $child;
            }
        }
    }
}

// Whether $node is, or holds, a form control marked required. A condition
// on such a node is not applied: the control stays on the page, because a
// form whose required control is cut could never be sent (the server still
// asks for its value). The editor's twin is _sdCondHoldsRequired().
function pg_sw_cond_holds_required($node)
{
    if (!is_array($node)) return false;
    if (!empty($node['props']['_attrs']) && is_array($node['props']['_attrs'])) {
        foreach ($node['props']['_attrs'] as $attr) {
            if (is_array($attr) && isset($attr['name']) && strtolower((string)$attr['name']) === 'required') return true;
        }
    }
    if (!empty($node['children']) && is_array($node['children'])) {
        foreach ($node['children'] as $child) {
            if (pg_sw_cond_holds_required($child)) return true;
        }
    }
    return false;
}

// Whether a condition's compared text is words a site translation should
// carry: an operator that compares text, and a value that is not a number
// or an amount (pg_sw_cond_is_number()). Numbers read the same in every
// language.
function pg_sw_cond_operand_is_words($props)
{
    if (!is_array($props) || empty($props['_bindings']['show_if'])) return false;
    $params = (isset($props['_bindParams']['show_if']) && is_array($props['_bindParams']['show_if']))
        ? $props['_bindParams']['show_if'] : array();
    $op = isset($params['op']) ? (string)$params['op'] : '';
    if (!in_array($op, array('eq', 'neq', 'contains', 'not_contains', 'starts'), true)) return false;
    $value = (isset($params['value']) && is_string($params['value'])) ? trim($params['value']) : '';
    return ($value !== '') && !pg_sw_cond_is_number($value);
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

// Spaces as one reads them: runs of white space are one space, none at
// either end. White space is this set, written out so the editor's twin
// reads the same one (PCRE's \s and JavaScript's \s differ on U+0085,
// U+180E and U+FEFF): the ASCII spaces, the no-break space and the Unicode
// spaces, U+0085, U+180E, the line and paragraph separators and U+FEFF. A
// zero-width space is not one.
function pg_sw_cond_plain($s)
{
    $s  = (string)$s;
    $ws = '\t\n\x0B\f\r \x{85}\x{A0}\x{1680}\x{180E}\x{2000}-\x{200A}\x{2028}\x{2029}\x{202F}\x{205F}\x{3000}\x{FEFF}';
    $collapsed = preg_replace('/[' . $ws . ']+/u', ' ', $s);
    // Not valid UTF-8: collapse the ASCII spaces and the no-break space
    // byte-wise rather than lose the value.
    if ($collapsed === null) $collapsed = preg_replace('/[\t\n\x0B\f\r ]+/', ' ', str_replace("\xC2\xA0", ' ', $s));
    return trim((string)$collapsed, ' ');
}

// What a visitor reads in a filled carrier: the markup and comments go, the
// entities become characters.
function pg_sw_cond_text($html)
{
    $text = html_entity_decode(strip_tags((string)$html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    return pg_sw_cond_plain($text);
}

// Text folded for comparison: lower case, the dot a capital İ leaves on its
// small i dropped and the dotless ı read as i, so 'İstanbul' equals
// 'istanbul' and 'IRMAK' (I is the capital of ı in Turkish) equals 'ırmak'.
function pg_sw_cond_fold($s)
{
    return str_replace(array("\xCC\x87", "\xC4\xB1"), array('', 'i'), mb_strtolower((string)$s, 'UTF-8'));
}

// The amount a value shows: array('number' => float, 'units' => the units
// written before and after the figure), or null when it shows none. Amounts
// are read the way the site writes them: an optional minus first
// (pg_format_money() puts it before the symbol: '-₺12,50'), an optional
// unit, the figure, an optional unit or percent sign after it
// (pg_visitor_money(): '€12,50 EUR'). A unit is a currency sign, an
// all-capital three-letter code (EUR, TRY), TL, or one or two capitals with
// a dollar sign (US$, R$); any other word makes the value text, so 'Kat 2'
// is no 2. The figure must be grouped as amounts are
// (pg_sw_cond_figure_ok()), so a dotted date is text too. pg_parse_amount()
// reads it, taking 1.234,56 and 1,234.56; a single separator with three
// digits after it (1.500) follows the site language.
function pg_sw_cond_amount($s)
{
    $s = pg_sw_cond_plain($s);
    if ($s === '') return null;
    $unit = '\p{Sc}|[A-Z]{1,2}\$|[A-Z]{3}|TL';
    $re   = '/^(-?) ?(?:(' . $unit . ') ?)?([0-9]+(?:[., ][0-9]+)*) ?(' . $unit . '|%)?$/u';
    if (!preg_match($re, $s, $m) || !pg_sw_cond_figure_ok($m[3])) return null;
    $number = pg_parse_amount($m[3]);
    $units  = array();
    if ($m[2] !== '') $units[] = $m[2];
    if (isset($m[4]) && $m[4] !== '') $units[] = $m[4];
    return array('number' => ($m[1] === '-') ? -$number : $number, 'units' => $units);
}

// The number a value shows (pg_sw_cond_amount()), or null.
function pg_sw_cond_number($s)
{
    $amount = pg_sw_cond_amount($s);
    return ($amount === null) ? null : $amount['number'];
}

// Whether eq and neq compare two values as numbers: both are amounts, and
// at most one carries a unit or the two share one. 'KAT 2' and 'ODA 2'
// compare as text; '€12,50 EUR' and '12,5' as numbers.
function pg_sw_cond_comparable($a, $b)
{
    if ($a === null || $b === null) return false;
    if (!$a['units'] || !$b['units']) return true;
    return (bool)array_intersect($a['units'], $b['units']);
}

// Whether a figure (digits with one separator between groups: '.', ',' or
// a space) is grouped the way an amount is. The last separator is the
// decimal point when it is a '.' or ',' found nowhere else in the figure;
// every separator before it groups thousands: all one kind, the first group
// one to three digits, each other exactly three. So 12,50, 1.500,
// 1.234.567,89 and 1 234,56 are figures, and 24.10.2026, 1.2,5 and 2 5 are
// not.
function pg_sw_cond_figure_ok($figure)
{
    $parts = preg_split('/([., ])/', (string)$figure, -1, PREG_SPLIT_DELIM_CAPTURE);
    $groups = array();
    $seps   = array();
    foreach ($parts as $i => $part) {
        if ($i % 2) $seps[] = $part;
        else $groups[] = $part;
    }
    $n = count($seps);
    if ($n === 0) return true;

    $last = $seps[$n - 1];
    if ($last !== ' ' && count(array_keys($seps, $last, true)) === 1) {
        if ($n === 1) return true;
        array_pop($seps);
        array_pop($groups);
    }
    if (count(array_unique($seps)) !== 1 || strlen($groups[0]) > 3) return false;
    for ($i = 1, $c = count($groups); $i < $c; $i++) {
        if (strlen($groups[$i]) !== 3) return false;
    }
    return true;
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
//   eq, neq                     numbers when both sides are amounts of one
//                               unit (pg_sw_cond_comparable()), else folded text
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
            $a = pg_sw_cond_amount($value);
            $b = pg_sw_cond_amount($operand);
            $same = pg_sw_cond_comparable($a, $b)
                ? (abs($a['number'] - $b['number']) < 0.000001)
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
