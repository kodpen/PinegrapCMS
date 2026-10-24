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
// the node: pg_sw_add_edit_chip() and the row id passes skip only leading
// comments, so a row's first element stays first.
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

// Keeps or cuts every condition span of $nonce in $html, outermost first; a
// span that is kept has the spans inside it resolved in turn. A span's
// value is its own carrier, taken out of the body; a token the renderer
// neither filled nor swept reads as empty, never as '^^token^^'. $cut is
// set to true when a span was cut.
function pg_sw_finish_resolve($html, $nonce, &$cut)
{
    if (!is_string($html) || strpos($html, '<!--pg-if:' . $nonce . ':') === false) return $html;
    $q  = preg_quote($nonce, '/');
    $re = '/<!--pg-if:' . $q . ':(\d+):([a-z_]+):([A-Za-z0-9_\-]*)-->(.*?)<!--\/pg-if:' . $q . ':\1-->/s';
    $out = preg_replace_callback($re, function ($m) use ($nonce, $q, &$cut) {
        $body  = $m[4];
        $value = '';
        $carrier = '/<!--pg-cv:' . $q . ':' . $m[1] . '-->(.*?)<!--\/pg-cv:' . $q . ':' . $m[1] . '-->/s';
        if (preg_match($carrier, $body, $c)) {
            $value = pg_sw_cond_text(pg_sw_sweep_tokens($c[1]));
            $body  = (string)preg_replace($carrier, '', $body);
        }
        if (!pg_sw_cond_eval($m[2], $value, _pg_sw_finish_operand($m[3]))) {
            $cut = true;
            return '';
        }
        return pg_sw_finish_resolve($body, $nonce, $cut);
    }, $html);
    // A backtracking limit hit leaves the markup as it was; the strip stage
    // still takes the markers out.
    return ($out === null) ? $html : $out;
}

// Every carrier of this request taken out with what it holds, so a measure
// of what a visitor sees (pg_sw_has_content()) never counts a value that
// is only there for a condition.
function pg_sw_finish_strip_carriers($html, $nonce = null)
{
    if (!is_string($html) || strpos($html, '<!--pg-cv:') === false) return $html;
    $q   = preg_quote(($nonce === null) ? pg_sw_marker_nonce() : (string)$nonce, '/');
    $out = preg_replace('/<!--pg-cv:' . $q . ':(\d+)-->.*?<!--\/pg-cv:' . $q . ':\1-->/s', '', $html);
    return ($out === null) ? $html : $out;
}

// The finish pass of one widget's markup. Its stages, in order:
//   1. conditions (pg_sw_finish_resolve());
//   2. when a span was cut, pg-hide-if-empty again in the kinds whose
//      renderer honours it, so a box whose last row left goes with it;
//   3. every marker and carrier of this request still standing taken out.
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
        $html = pg_sw_hide_empty_fields($html);
    }

    $q    = preg_quote($nonce, '/');
    $html = pg_sw_finish_strip_carriers($html, $nonce);
    $out  = preg_replace('/<!--\/?pg-(?:if|cv):' . $q . ':[^>]*?-->/', '', $html);
    return ($out === null) ? $html : $out;
}
