<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// A module of functions.php: conditions on the facts of the visit and on
// the record the page opens, for elements of a page outside any system
// widget.
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
//
// A value of the page's record bound to an element outside a widget is
// printed: the stored body keeps a placeholder (pg_page_record_bind_props())
// that every request fills, escaped, once the widgets are drawn.

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

// The record a page opens by its address: the product of a product page,
// the event of an event page, the record of a form record page. Its widget
// publishes it while it draws (pg_page_record_publish()); an element
// anywhere on the page may be shown by whether it was found
// (__record_found) and by its allow-listed values, each read as
// __record<token> (__record__is_free_shipping). The values come from
// pg_sw_entity_fields(), the same allow-list the widget offers. Output that
// depends on them depends on the address alone, so a cached copy stays
// right.
function pg_page_record_entities()
{
    return array('product', 'calendar_event', 'form_record');
}

// The record tokens a page condition may read.
function pg_page_record_tokens()
{
    $out = array('__record_found');
    foreach (pg_page_record_entities() as $entity) {
        foreach (array_keys(pg_sw_entity_fields($entity)) as $token) $out[] = '__record' . $token;
    }
    return array_values(array_unique($out));
}

// Whether $token names a form record's field by name
// (__record__field_<name>, pg_sw_entity_field_values()). Fields are the
// form's own, so the token is matched by its shape, not by a list.
function pg_page_record_field_token($token)
{
    return is_string($token) && (bool)preg_match('/^__record__field_[a-z0-9_]+$/', $token);
}

// The record slot of the page being drawn: null while no widget published
// one, else array('entity' => ..., 'values' => token => value, or null for
// a record the address named and the widget did not find).
function &_pg_page_record_slot()
{
    static $slot = null;
    return $slot;
}

// The record of the page, published by the widget that opens it. The first
// one counts: a second detail widget on the page does not change it.
function pg_page_record_publish($entity, $values)
{
    $slot = &_pg_page_record_slot();
    if ($slot !== null || !in_array($entity, pg_page_record_entities(), true)) return;
    $slot = array('entity' => $entity, 'values' => is_array($values) ? $values : null);
}

// A page draw starts with no record and gives back the slot it found when
// it ends (pg_page_record_end()): an e-mail drawn while a page is drawn
// keeps neither's record.
function pg_page_record_begin()
{
    $slot = &_pg_page_record_slot();
    $prev = $slot;
    $slot = null;
    return $prev;
}

function pg_page_record_end($prev)
{
    $slot = &_pg_page_record_slot();
    $slot = $prev;
}

// The record tokens with the published record's values: every token is
// there, '' when there is no record or it does not have the value; a form
// record's fields by name besides.
function pg_page_record_values()
{
    $slot = _pg_page_record_slot();
    $out = array_fill_keys(pg_page_record_tokens(), '');
    if (is_array($slot) && is_array($slot['values'])) {
        $out['__record_found'] = '1';
        foreach (array_keys(pg_sw_entity_fields($slot['entity'])) as $token) {
            if (isset($slot['values'][$token]) && is_scalar($slot['values'][$token])) {
                $out['__record' . $token] = (string)$slot['values'][$token];
            }
        }
        if ($slot['entity'] === 'form_record') {
            foreach ($slot['values'] as $token => $value) {
                if (is_scalar($value) && pg_page_record_field_token('__record' . $token)) $out['__record' . $token] = (string)$value;
            }
        }
    }
    return $out;
}

// The record values an element outside a widget may print: every record
// token but __record_found (W17). A yes/no value prints '1'; the editor
// offers it as a condition only.
function pg_page_record_value_tokens()
{
    return array_values(array_diff(pg_page_record_tokens(), array('__record_found')));
}

// $props of a node drawn outside any widget with each binding that names a
// value of the page's record (a text, a link's address, a picture's address
// or its alt text) turned into a placeholder the stored body keeps:
// {pg-rv:TOKEN} for a text, {pg-ru:TOKEN} for an address. Every request
// fills it (pg_page_record_fill()); the binding is taken off, so the
// widget's own binding pass never sees it. Plain characters, so they pass
// h() in an attribute as they are. $rich: the node prints its text as
// written, and the value takes the place of the words its markup wraps
// (pg_binding_text_slot()).
function pg_page_record_bind_props($props, $rich = false)
{
    if (empty($props['_bindings']) || !is_array($props['_bindings'])) return $props;
    static $slots = array('text' => 'rv', 'alt' => 'rv', 'href' => 'ru', 'src' => 'ru');
    $tokens = null;
    foreach ($props['_bindings'] as $prop => $token) {
        if (!isset($slots[$prop]) || !is_string($token) || strpos($token, '__record__') !== 0) continue;
        if ($tokens === null) $tokens = pg_page_record_value_tokens();
        $token = strtolower($token);
        if (!in_array($token, $tokens, true) && !pg_page_record_field_token($token)) continue;
        $slot = '{pg-' . $slots[$prop] . ':' . $token . '}';
        $props[$prop] = ($rich && $prop === 'text' && isset($props['text'])) ? pg_binding_text_slot($props['text'], $slot) : $slot;
        unset($props['_bindings'][$prop]);
        if (isset($props['_bindFormats'][$prop])) unset($props['_bindFormats'][$prop]);
        if (isset($props['_bindParams'][$prop])) unset($props['_bindParams'][$prop]);
    }
    if (empty($props['_bindings'])) unset($props['_bindings']);

    return $props;
}

// $html with every record value placeholder filled from $values
// (pg_page_record_values()), escaped once: a text as it is, an address only
// when it is an http(s) or site address, else empty. A placeholder of a
// token not in $values is emptied.
function pg_page_record_fill($html, $values)
{
    if (strpos($html, '{pg-r') === false) return $html;
    if (!preg_match_all('/\{pg-r([vu]):(__record__[a-z0-9_]+)\}/', $html, $m, PREG_SET_ORDER)) return $html;
    $map = array();
    foreach ($m as $hit) {
        $value = isset($values[$hit[2]]) ? (string)$values[$hit[2]] : '';
        if ($hit[1] === 'u' && !preg_match('#^(https?://|/)#i', $value)) $value = '';
        $map[$hit[0]] = h($value);
    }

    return strtr($html, $map);
}

// Whether every condition of $cond (pg_sw_cond_specs()) reads a fact of
// the visit or the page's record. A node that mixes one with any other
// value draws as before.
function pg_page_fact_spec($cond)
{
    if (!is_array($cond) || empty($cond['conds'])) return false;
    $facts = array_merge(pg_page_fact_tokens(), pg_page_record_tokens());
    foreach ($cond['conds'] as $c) {
        $token = strtolower((string)$c['token']);
        if (!in_array($token, $facts, true) && !pg_page_record_field_token($token)) return false;
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
// A form record field the record does not have reads as ''.
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
        if (!array_key_exists($token, $values) && pg_page_record_field_token($token)) $values[$token] = '';
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
    ) + pg_page_record_values();
}

// The page conditions of a drawn page decided for this visit, then the
// record's values filled in; the facts are read only when the page holds a
// condition.
function pg_page_facts_apply($html, $email = false)
{
    if (strpos($html, '<!--pg-pf:') !== false) $html = pg_page_facts_resolve($html, pg_page_fact_values($email));
    return pg_page_record_fill($html, pg_page_record_values());
}
