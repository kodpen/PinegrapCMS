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

// Row positions. A list's row template that uses one of the six position
// tokens is drawn between two row marks (_split_widget_tree()), so every
// copy of it the renderer prints is a row span:
//
//   <!--pg-row:NONCE-->  one row, ^^__loop_index^^ and the rest unfilled  <!--/pg-row:NONCE-->
//
// No renderer fills these tokens, and the sweep keeps them while a widget
// renders (pg_sw_sweep_tokens()); the pass numbers the spans it finds.

// The two row marks of $nonce.
function pg_sw_finish_row_marks($nonce)
{
    return array('<!--pg-row:' . $nonce . '-->', '<!--/pg-row:' . $nonce . '-->');
}

// Whether a list's row template ($children, the loop_area's children)
// should be drawn between row marks: only inside a widget render, and only
// when a node of it names a position token, in a binding (a condition
// too), a text or custom HTML. A template without one draws as before. The
// tokens in a list inside the rows (a loop_area with props.source) number
// that list's rows, not these (widgets_lists.php), so they do not count.
function pg_sw_finish_uses_positions($children)
{
    if (!pg_sw_finish_active() || !is_array($children) || !$children) return false;
    $json = json_encode(pg_sw_list_unsourced($children));
    return is_string($json) && (bool)preg_match('/__loop_(?:index|count|is_first|is_last|is_odd|is_even)\b/', $json);
}

// The values of the six position tokens for row $i (counted from 0) of $n
// rows: the row number, the number of rows, and '1' or '' for the first,
// the last, an odd and an even row.
function pg_sw_finish_position_values($i, $n)
{
    $i = (int)$i;
    $n = (int)$n;
    return array(
        '__loop_index'    => (string)($i + 1),
        '__loop_count'    => (string)$n,
        '__loop_is_first' => ($i === 0) ? '1' : '',
        '__loop_is_last'  => ($i === $n - 1) ? '1' : '',
        '__loop_is_odd'   => (($i + 1) % 2 === 1) ? '1' : '',
        '__loop_is_even'  => (($i + 1) % 2 === 0) ? '1' : '',
    );
}

// The pattern of a position token with its optional format suffix.
function _pg_sw_finish_position_pattern()
{
    return '/\^\^(__loop_(?:index|count|is_first|is_last|is_odd|is_even))\^\^(?:%%.*?%%)?/';
}

// The row spans of $html at its top level, numbered in the order they
// stand: the spans inside a span's body number among themselves first,
// then the position tokens left in the body take the span's values (the
// format suffix goes; the values are digits, '1' or '', so nothing needs
// escaping). The marks of a span are taken out; a mark without its pair
// is left. Pairs are found with strpos(), never one pattern over a body.
function _pg_sw_finish_number_rows($html, $open, $close)
{
    $spans = array();
    $pos   = 0;
    while (($at = strpos($html, $open, $pos)) !== false) {
        $after = $at + strlen($open);
        $end   = _pg_sw_finish_pair_end($html, $open, $close, $after);
        if ($end === null) {
            $pos = $after;
            continue;
        }
        $spans[] = array($at, $after, $end[0], $end[1]);
        $pos     = $end[1];
    }
    if (!$spans) return $html;

    $n       = count($spans);
    $pattern = _pg_sw_finish_position_pattern();
    $out     = '';
    $pos     = 0;
    foreach ($spans as $i => $span) {
        $out   .= substr($html, $pos, $span[0] - $pos);
        $body   = _pg_sw_finish_number_rows(substr($html, $span[1], $span[2] - $span[1]), $open, $close);
        $values = pg_sw_finish_position_values($i, $n);
        $filled = preg_replace_callback($pattern, function ($m) use ($values) {
            return $values[$m[1]];
        }, $body);
        $out .= ($filled === null) ? $body : $filled;
        $pos  = $span[3];
    }
    return $out . substr($html, $pos);
}

// The position stage of the pass: every row span of $nonce numbered
// (_pg_sw_finish_number_rows()), then every row mark and every position
// token still standing taken out: a token bound outside the loop, or one
// in markup no row mark reached, reads as empty.
function pg_sw_finish_positions($html, $nonce)
{
    if (!is_string($html) || $html === '') return $html;
    list($open, $close) = pg_sw_finish_row_marks($nonce);
    if (strpos($html, $open) !== false) {
        $html = str_replace(array($open, $close), '', _pg_sw_finish_number_rows($html, $open, $close));
    } elseif (strpos($html, $close) !== false) {
        $html = str_replace($close, '', $html);
    }
    if (strpos($html, '^^__loop_') === false) return $html;
    $out = preg_replace(_pg_sw_finish_position_pattern(), '', $html);
    return ($out === null) ? $html : $out;
}

// The finish pass of one widget's markup. What the renderer published for
// its lists is taken first, so nothing outlives the pass. Its stages, in
// order:
//   1. row positions (pg_sw_finish_positions()), first, so a condition on
//      a position token reads its row's value;
//   2. lists inside a record (pg_sw_lists_expand(), widgets_lists.php),
//      drawn from the published rows, each row with its own positions, so
//      a condition in a list's row reads that row;
//   3. conditions (pg_sw_finish_resolve());
//   4. when a span was cut or a list came out empty, pg-hide-if-empty again
//      in the kinds whose renderer honours it, on the elements around a cut
//      point only, so a row whose value left and a box whose last row left
//      go with it, and an element no cut touched is judged as the renderer
//      judged it;
//   5. every marker, carrier and cut point of this request still standing
//      taken out;
//   6. each record's edit pencil put in the first element the record kept
//      (pg_sw_finish_place_chips()), then any pencil marker left taken out.
// Markup that holds neither a marker of this request nor a position token
// comes back as it was; markup without a marker ends after stage 1.
function pg_sw_finish_widget_html($html, $scope)
{
    $published = pg_sw_list_take($scope);
    if (!is_string($html) || $html === '') return $html;
    $nonce = pg_sw_marker_nonce();
    if (strpos($html, $nonce) === false && strpos($html, '^^__loop_') === false) return $html;

    $html = pg_sw_finish_positions($html, $nonce);
    if (strpos($html, $nonce) === false) return $html;

    $cut  = false;
    $html = pg_sw_lists_expand($html, $nonce, $scope, $published, $cut);
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
