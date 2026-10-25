<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// A module of functions.php: the finish pass every system widget render
// goes through.
//
// Loaded by functions.php through require_once, never on its own.
//
// The outer callback of _expand_system_widgets() is the one door of every
// widget render: the page, an e-mail, the editor's Preview and its ghost
// rows, and the widgets nested in a widget. It opens a scope around the
// renderer and hands the finished markup to pg_sw_finish_widget_html().
// While a scope is open, _render_tree_node() writes a node that carries a
// value condition between two markers, with a carrier after the node:
//
//   <!--pg-if:NONCE:N:OP:OPERAND-->  the node  <!--pg-cv:NONCE:N-->^^token^^<!--/pg-cv:NONCE:N-->  <!--/pg-if:NONCE:N-->
//
// The renderer fills ^^token^^ in the carrier with its own fill, whatever
// that is (pg_sw_apply_tokens(), strtr(), str_replace()), as it fills the
// node, so a span copied into every row carries that row's value. The pass
// then reads each carrier, keeps or cuts its span and takes every marker
// out. The nonce is random per request: a value that holds text shaped like
// a marker is not one. Outside a scope no marker is ever written, so a page
// body saved from a render holds none.
if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// The marker nonce of this request.
function pg_sw_marker_nonce()
{
    static $nonce = null;
    if ($nonce === null) $nonce = bin2hex(random_bytes(8));
    return $nonce;
}

// The open widget scopes, innermost last.
function &_pg_sw_finish_stack()
{
    static $stack = array();
    return $stack;
}

// The kind a widget renders as. The early names of the form list
// (submitted_forms_list, form_list, blog_list) render through the form list
// renderer, so they are that kind here too.
function pg_sw_finish_kind($kind)
{
    $kind = is_string($kind) ? $kind : '';
    if ($kind === 'submitted_forms_list' || $kind === 'form_list' || $kind === 'blog_list') return 'form_list_view';
    return $kind;
}

// Opens the scope of one widget render: array('sid' => widget id, 'kind' =>
// its regionType, 'email' => whether it is drawn for an e-mail). Returns the
// scope as it was stored.
function pg_sw_finish_open($scope)
{
    $scope = is_array($scope) ? $scope : array();
    $scope = array(
        'sid'   => isset($scope['sid']) ? (int)$scope['sid'] : 0,
        'kind'  => pg_sw_finish_kind(isset($scope['kind']) ? $scope['kind'] : ''),
        'email' => !empty($scope['email']),
    );
    $stack = &_pg_sw_finish_stack();
    $stack[] = $scope;
    return $scope;
}

// Closes the innermost scope.
function pg_sw_finish_close()
{
    $stack = &_pg_sw_finish_stack();
    array_pop($stack);
}

// Whether a widget is being rendered.
function pg_sw_finish_active()
{
    $stack = &_pg_sw_finish_stack();
    return !empty($stack);
}

// The scope of the widget being rendered, or null.
function pg_sw_finish_scope()
{
    $stack = &_pg_sw_finish_stack();
    return $stack ? $stack[count($stack) - 1] : null;
}

// $html (a node drawn inside a widget) between the markers of the
// condition $spec (pg_sw_cond_spec()), with the carrier of its token after
// the node: the row id passes skip only leading comments, so a row's first
// element stays first.
function pg_sw_finish_wrap($html, $spec)
{
    static $seq = 0;
    $n       = ++$seq;
    $nonce   = pg_sw_marker_nonce();
    $operand = rtrim(strtr(base64_encode((string)$spec['operand']), '+/', '-_'), '=');
    return '<!--pg-if:' . $nonce . ':' . $n . ':' . $spec['op'] . ':' . $operand . '-->'
         . $html
         . '<!--pg-cv:' . $nonce . ':' . $n . '-->^^' . $spec['token'] . '^^<!--/pg-cv:' . $nonce . ':' . $n . '-->'
         . '<!--/pg-if:' . $nonce . ':' . $n . '-->';
}

// The operand a marker carries (URL-safe base64 without padding).
function _pg_sw_finish_operand($b64)
{
    $b64 = strtr((string)$b64, '-_', '+/');
    $pad = strlen($b64) % 4;
    if ($pad) $b64 .= str_repeat('=', 4 - $pad);
    $operand = base64_decode($b64, true);
    return ($operand === false) ? '' : $operand;
}

// What a cut span leaves of itself: a cut point, which the pg-hide-if-empty
// rerun follows to the elements around it. When the cut node is the value
// element of a "label: value" row (class pg-field-value, as the form
// starters bind it), an empty value element stands after the cut point, so
// the row is judged as one whose field was left blank. The strip stage
// takes out whatever of it is still standing.
function pg_sw_finish_cut_mark($nonce)
{
    return '<!--pg-cut:' . $nonce . '-->';
}

function pg_sw_finish_cut_shell($nonce)
{
    return pg_sw_finish_cut_mark($nonce) . '<span class="pg-field-value"></span>';
}

// Whether the first element of $html (after white space and comments)
// carries the class pg-field-value.
function _pg_sw_finish_is_field_value($html)
{
    $at  = 0;
    $len = strlen($html);
    while (true) {
        $at += strspn($html, " \t\r\n\f", $at);
        if ($at >= $len) return false;
        if (substr($html, $at, 4) !== '<!--') break;
        $end = strpos($html, '-->', $at + 4);
        if ($end === false) return false;
        $at = $end + 3;
    }
    if (!preg_match('/\G<[a-z][a-z0-9]*\b[^>]*>/i', $html, $m, 0, $at)) return false;
    return (bool)preg_match('/\bclass="[^"]*\bpg-field-value\b[^"]*"/i', $m[0]);
}

// Where the pair whose body starts at $from closes: array(offset of its
// close marker, offset just past it), or null when it never does. $open is
// the start of the open marker (up to its number), $close the whole close
// marker. A pair of the same number inside it (a row template placed in a
// copy of itself, a reply in its comment) is counted, so the pairs match.
// An inner open marker is looked for only before the next close marker, so
// a pair costs the length of its own body, not of the markup after it (a
// search to the end per pair made a page of many records quadratic).
function _pg_sw_finish_pair_end($html, $open, $close, $from)
{
    $depth = 1;
    while (($c = strpos($html, $close, $from)) !== false) {
        $o = ($c > $from) ? strpos(substr($html, $from, $c - $from), $open) : false;
        if ($o !== false) {
            $depth++;
            $from += $o + strlen($open);
            continue;
        }
        if (--$depth === 0) return array($c, $c + strlen($close));
        $from = $c + strlen($close);
    }
    return null;
}

// Keeps or cuts every condition span of $nonce in $html, outermost first; a
// span that is kept has the spans inside it resolved in turn. A span's
// value is its own carrier, the last one of its number in its body (the
// wrap writes it just before the close marker), taken out of the body; a
// token the renderer neither filled nor swept reads as empty, never as
// '^^token^^'. A cut span leaves its cut point (pg_sw_finish_cut_mark()).
// The spans are found with strpos(), never with one pattern over a body,
// so no body is too big to judge. A marker without its pair is left for
// the strip stage. $cut is set to true when a span was cut.
function pg_sw_finish_resolve($html, $nonce, &$cut)
{
    $open = '<!--pg-if:' . $nonce . ':';
    if (!is_string($html) || strpos($html, $open) === false) return $html;

    $out = '';
    $pos = 0;
    while (($at = strpos($html, $open, $pos)) !== false) {
        $head_end = strpos($html, '-->', $at);
        if ($head_end === false) break;
        $after = $head_end + 3;
        $head  = substr($html, $at + strlen($open), $head_end - $at - strlen($open));
        $end   = preg_match('/^(\d+):([a-z_]+):([A-Za-z0-9_\-]*)$/', $head, $h)
            ? _pg_sw_finish_pair_end($html, $open . $h[1] . ':', '<!--/pg-if:' . $nonce . ':' . $h[1] . '-->', $after) : null;
        if ($end === null) {
            $out .= substr($html, $pos, $after - $pos);
            $pos  = $after;
            continue;
        }

        $out  .= substr($html, $pos, $at - $pos);
        $body  = substr($html, $after, $end[0] - $after);
        $pos   = $end[1];
        $value = '';
        $cv_open  = '<!--pg-cv:' . $nonce . ':' . $h[1] . '-->';
        $cv_close = '<!--/pg-cv:' . $nonce . ':' . $h[1] . '-->';
        $c0 = strrpos($body, $cv_open);
        $c1 = ($c0 === false) ? false : strpos($body, $cv_close, $c0);
        if ($c1 !== false) {
            $value = pg_sw_cond_text(pg_sw_sweep_tokens(substr($body, $c0 + strlen($cv_open), $c1 - $c0 - strlen($cv_open))));
            $body  = substr($body, 0, $c0) . substr($body, $c1 + strlen($cv_close));
        }

        if (pg_sw_cond_eval($h[2], $value, _pg_sw_finish_operand($h[3]))) {
            $out .= pg_sw_finish_resolve($body, $nonce, $cut);
        } else {
            $cut  = true;
            $out .= _pg_sw_finish_is_field_value($body) ? pg_sw_finish_cut_shell($nonce) : pg_sw_finish_cut_mark($nonce);
        }
    }
    return $out . substr($html, $pos);
}

// Every carrier of this request taken out with what it holds, so a measure
// of what a visitor sees (pg_sw_has_content()) never counts a value that
// is only there for a condition. Found with strpos(), as the spans are.
function pg_sw_finish_strip_carriers($html, $nonce = null)
{
    if (!is_string($html) || strpos($html, '<!--pg-cv:') === false) return $html;
    $nonce = ($nonce === null) ? pg_sw_marker_nonce() : (string)$nonce;
    $open  = '<!--pg-cv:' . $nonce . ':';
    $out   = '';
    $pos  = 0;
    while (($at = strpos($html, $open, $pos)) !== false) {
        $head_end = strpos($html, '-->', $at);
        if ($head_end === false) break;
        $n     = substr($html, $at + strlen($open), $head_end - $at - strlen($open));
        $close = '<!--/pg-cv:' . $nonce . ':' . $n . '-->';
        $c     = preg_match('/^\d+$/', $n) ? strpos($html, $close, $head_end + 3) : false;
        if ($c === false) {
            $out .= substr($html, $pos, $head_end + 3 - $pos);
            $pos  = $head_end + 3;
            continue;
        }
        $out .= substr($html, $pos, $at - $pos);
        $pos  = $c + strlen($close);
    }
    return $out . substr($html, $pos);
}

// Whether a record holds a value condition of this request, so its edit
// pencil waits for the pass (pg_sw_add_edit_chip()).
function pg_sw_finish_holds_condition($html)
{
    return is_string($html) && strpos($html, '<!--pg-if:' . pg_sw_marker_nonce() . ':') !== false;
}

// A record's edit pencil, kept for the pass: the record between two pencil
// markers, the open one carrying the pencil's address, title and tone (JSON,
// URL-safe base64). Put in at once, the pencil lands in the record's first
// element, which a condition may cut or the pg-hide-if-empty rerun drop with
// the pencil inside, and a box would count it as content. null when the
// values cannot be written.
function pg_sw_finish_defer_chip($html, $url, $title, $tone)
{
    static $seq = 0;
    $json = json_encode(array((string)$url, (string)$title, (string)$tone));
    if (!is_string($json)) return null;
    $n     = ++$seq;
    $nonce = pg_sw_marker_nonce();
    return '<!--pg-chip:' . $nonce . ':' . $n . ':' . rtrim(strtr(base64_encode($json), '+/', '-_'), '=') . '-->'
         . $html
         . '<!--/pg-chip:' . $nonce . ':' . $n . '-->';
}

// Every pencil kept for the pass put in the first element its record kept,
// by the rules of pg_sw_insert_edit_chip() (none when that element cannot
// hold one or the record kept nothing), its markers taken out. A record in
// a record (a reply in its comment) gets its own. A marker without its pair
// is left for the strip stage.
function pg_sw_finish_place_chips($html, $nonce)
{
    $open = '<!--pg-chip:' . $nonce . ':';
    if (!is_string($html) || strpos($html, $open) === false) return $html;

    $out = '';
    $pos = 0;
    while (($at = strpos($html, $open, $pos)) !== false) {
        $head_end = strpos($html, '-->', $at);
        if ($head_end === false) break;
        $after = $head_end + 3;
        $head  = substr($html, $at + strlen($open), $head_end - $at - strlen($open));
        $end   = preg_match('/^(\d+):([A-Za-z0-9_\-]*)$/', $head, $h)
            ? _pg_sw_finish_pair_end($html, $open . $h[1] . ':', '<!--/pg-chip:' . $nonce . ':' . $h[1] . '-->', $after) : null;
        if ($end === null) {
            $out .= substr($html, $pos, $after - $pos);
            $pos  = $after;
            continue;
        }

        $out   .= substr($html, $pos, $at - $pos);
        $record = pg_sw_finish_place_chips(substr($html, $after, $end[0] - $after), $nonce);
        $chip   = json_decode(_pg_sw_finish_operand($h[2]), true);
        if (is_array($chip) && count($chip) === 3 && is_string($chip[0]) && is_string($chip[1]) && is_string($chip[2])) {
            $record = pg_sw_insert_edit_chip($record, $chip[0], $chip[1], $chip[2]);
        }
        $out .= $record;
        $pos  = $end[1];
    }
    return $out . substr($html, $pos);
}

// The finish pass of one widget's markup. Its stages, in order:
//   1. conditions (pg_sw_finish_resolve());
//   2. when a span was cut, pg-hide-if-empty again in the kinds whose
//      renderer honours it, on the elements around a cut point only, so a
//      row whose value left and a box whose last row left go with it, and
//      an element no cut touched is judged as the renderer judged it;
//   3. every marker, carrier and cut point of this request still standing
//      taken out;
//   4. each record's edit pencil put in the first element the record kept
//      (pg_sw_finish_place_chips()), then any pencil marker left taken out.
// Markup that holds no marker of this request comes back as it was.
function pg_sw_finish_widget_html($html, $scope)
{
    if (!is_string($html) || $html === '') return $html;
    $nonce = pg_sw_marker_nonce();
    if (strpos($html, $nonce) === false) return $html;

    $cut  = false;
    $html = pg_sw_finish_resolve($html, $nonce, $cut);

    $kind = (is_array($scope) && isset($scope['kind'])) ? pg_sw_finish_kind($scope['kind']) : '';
    if ($cut && in_array($kind, array('form_list_view', 'form_item_view', 'comments'), true)) {
        $html = pg_sw_hide_empty_fields($html, pg_sw_finish_cut_mark($nonce));
    }

    $html = pg_sw_finish_strip_carriers($html, $nonce);
    $html = str_replace(array(pg_sw_finish_cut_shell($nonce), pg_sw_finish_cut_mark($nonce)), '', $html);
    $out  = preg_replace('/<!--\/?pg-(?:if|cv):' . preg_quote($nonce, '/') . ':[^>]*?-->/', '', $html);
    if ($out !== null) $html = $out;

    $html = pg_sw_finish_place_chips($html, $nonce);
    $out  = preg_replace('/<!--\/?pg-chip:' . preg_quote($nonce, '/') . ':[^>]*?-->/', '', $html);
    return ($out === null) ? $html : $out;
}
