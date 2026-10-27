<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// A module of functions.php: conditions on the facts of the visit, for
// elements of a page outside any system widget.
//
// Loaded by functions.php through require_once, never on its own.
//
// A node outside a widget whose every value condition (show_if, show_if_2,
// show_if_3, widgets_conditions.php) reads one of the visit's facts is drawn
// between two markers, which the stored page body keeps:
//   <!--pg-pf:ID:SPEC--> node <!--/pg-pf:ID-->
// SPEC is the conditions as URL-safe base64 JSON. Every request decides the
// markers once its widgets are drawn (get_page_content.php,
// pg_page_facts_apply()): a span whose test holds keeps its node, the
// others go. The facts choose which element shows and are never printed.
// A page answer a cache may keep is drawn for a visitor who is signed out
// with an empty cart (page_cache.php bypasses a signed-in session and one
// holding an order), so a kept copy is right for everyone it is served to.

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// The facts of the visit a page condition may read: whether the visitor is
// signed in, whether the cart holds a product, how many it holds. A yes is
// '1', a no and a count of zero are '' (as an entity's yes/no and zero,
// widgets_entity_fields.php), so not_empty / empty read them.
function pg_page_fact_tokens()
{
    return array('__visit_signed_in', '__visit_cart_has_items', '__visit_cart_count');
}

// Whether every condition of $cond (pg_sw_cond_specs()) reads a fact of
// the visit. A node that mixes a fact with any other value draws as before.
function pg_page_fact_spec($cond)
{
    if (!is_array($cond) || empty($cond['conds'])) return false;
    $facts = pg_page_fact_tokens();
    foreach ($cond['conds'] as $c) {
        if (!in_array(strtolower((string)$c['token']), $facts, true)) return false;
    }
    return true;
}

// $html between the markers of $cond. The span's id is drawn from its
// conditions and its node, not a counter: a stored body drawn at save time
// and a shared component drawn now would count from 1 both, and a body
// drawn again from the same tree comes out the same. A span cannot hold one
// with its own id (what it holds is shorter); siblings with one pair in
// turn.
function pg_page_fact_wrap($html, $cond)
{
    $tests = array();
    foreach ($cond['conds'] as $c) $tests[] = array(strtolower((string)$c['token']), (string)$c['op'], (string)$c['operand']);
    $spec = rtrim(strtr(base64_encode(json_encode(array('j' => ($cond['join'] === 'any') ? 'any' : 'all', 'c' => $tests))), '+/', '-_'), '=');
    $n = substr(sha1($spec . "\n" . $html), 0, 12);
    return '<!--pg-pf:' . $n . ':' . $spec . '-->' . $html . '<!--/pg-pf:' . $n . '-->';
}

// Whether the span's SPEC holds for $values (token => value): false when it
// cannot be read, names another value or an operator that does not exist.
function pg_page_fact_holds($spec, $values)
{
    $b64 = strtr((string)$spec, '-_', '+/');
    $pad = strlen($b64) % 4;
    if ($pad) $b64 .= str_repeat('=', 4 - $pad);
    $json = base64_decode($b64, true);
    $data = ($json === false) ? null : json_decode($json, true);
    if (!is_array($data) || empty($data['c']) || !is_array($data['c'])) return false;

    $ops = pg_sw_cond_ops();
    $tests = array();
    foreach ($data['c'] as $t) {
        if (!is_array($t) || count($t) !== 3) return false;
        list($token, $op, $operand) = array_map('strval', $t);
        if (!array_key_exists($token, $values) || !isset($ops[$op])) return false;
        if ($ops[$op]['operand'] && trim($operand) === '') return false;
        $tests[] = array($op, (string)$values[$token], $operand);
    }
    return pg_sw_cond_eval_all((isset($data['j']) && $data['j'] === 'any') ? 'any' : 'all', $tests);
}

// $html with every page condition decided for $values: a span that holds
// keeps its node, one that does not goes; an open marker without its close
// is dropped and its node shows. Spans are found with strpos(), outermost
// first; a span kept is read again from its start, so the spans inside it
// are decided too.
function pg_page_facts_resolve($html, $values)
{
    $open = '<!--pg-pf:';
    $pos = 0;
    while (($pos = strpos($html, $open, $pos)) !== false) {
        $head_end = strpos($html, '-->', $pos);
        if ($head_end === false) break;
        $head = substr($html, $pos + strlen($open), $head_end - $pos - strlen($open));
        $parts = explode(':', $head, 2);
        $close = ((count($parts) === 2) && preg_match('/^[0-9a-f]{12}$/', $parts[0])) ? '<!--/pg-pf:' . $parts[0] . '-->' : '';
        $close_at = ($close !== '') ? strpos($html, $close, $head_end) : false;
        if ($close_at === false) {
            $html = substr($html, 0, $pos) . substr($html, $head_end + 3);
            continue;
        }
        $inner = substr($html, $head_end + 3, $close_at - $head_end - 3);
        $keep = pg_page_fact_holds($parts[1], $values) ? $inner : '';
        $html = substr($html, 0, $pos) . $keep . substr($html, $close_at + strlen($close));
    }
    return $html;
}

// The facts of this visit (pg_page_fact_tokens()). An e-mail, a page drawn
// for the search index or the SEO score belongs to no visit: signed out,
// nothing in the cart.
function pg_page_fact_values($email = false)
{
    $background = $email
        || (function_exists('pg_seo_rendering') && pg_seo_rendering())
        || (defined('UPDATE_SEARCH_INDEX') && UPDATE_SEARCH_INDEX === true);
    $signed_in = !$background && defined('USER_LOGGED_IN') && (USER_LOGGED_IN === true);
    $count = 0;
    $order_id = (!$background && isset($_SESSION['ecommerce']['order_id'])) ? (int)$_SESSION['ecommerce']['order_id'] : 0;
    if ($order_id > 0 && defined('ECOMMERCE') && ECOMMERCE === true) {
        $row = db_item("SELECT SUM(quantity) AS qty FROM order_items WHERE order_id = '" . $order_id . "'" . pg_order_item_active_sql());
        if (is_array($row)) $count = max(0, (int)$row['qty']);
    }
    return array(
        '__visit_signed_in'      => $signed_in ? '1' : '',
        '__visit_cart_has_items' => ($count > 0) ? '1' : '',
        '__visit_cart_count'     => ($count > 0) ? (string)$count : '',
    );
}

// The page conditions of a drawn page decided for this visit; the facts are
// read only when the page holds one.
function pg_page_facts_apply($html, $email = false)
{
    if (strpos($html, '<!--pg-pf:') === false) return $html;
    return pg_page_facts_resolve($html, pg_page_fact_values($email));
}
