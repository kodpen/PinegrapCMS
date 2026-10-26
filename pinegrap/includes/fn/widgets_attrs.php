<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// A module of functions.php: values of a system widget's record in an
// element's attributes (attribute bindings).
//
// Loaded by functions.php through require_once, never on its own.
//
// An Attributes panel entry (props._attrs) or a title of an element inside
// a widget may hold {{token}}: data-id="{{submitted_form_id}}",
// aria-label="Open {{title}}". A carrier cannot stand in an attribute (h()
// escapes a comment), so while a widget renders the token is written as a
// placeholder of plain characters, and its carrier after the element:
//
//   <span data-id="pgav-NONCE-K-">...</span><!--pg-cv:NONCE:aK-->^^token^^<!--/pg-cv:NONCE:aK-->
//
// The renderer fills the carrier with its own fill, as it fills a
// condition's (widgets_finish.php), a list row's fill fills a row token in
// it, and the finish pass (pg_sw_attr_resolve()) puts each carrier's value
// in its placeholder as plain text, escaped once. Outside a widget render
// the attribute prints as written.
if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// The attributes that never take a value: the ones the element's own props
// write (id, class, style), the event handlers, and the ones a browser reads
// as an address or as markup, where a record's text could become a script
// address. A link's or an image's address has its own binding.
function pg_sw_attr_denied_names()
{
    return array(
        'id', 'class', 'style',
        'href', 'src', 'srcset', 'srcdoc', 'action', 'formaction', 'poster', 'data', 'cite',
        'background', 'ping', 'xlink:href', 'manifest', 'codebase', 'longdesc', 'usemap',
        'archive', 'lowsrc', 'dynsrc', 'content', 'http-equiv',
    );
}

// Whether attribute $name may hold a value of the record.
function pg_sw_attr_name_ok($name)
{
    $name = strtolower(trim((string)$name));
    if (!preg_match('/^[a-z][a-z0-9_.:-]*$/', $name)) return false;
    if (strncmp($name, 'on', 2) === 0) return false;
    return !in_array($name, pg_sw_attr_denied_names(), true);
}

// Whether $token may be printed in an attribute: a token a condition may
// read (no block, no sentinel), and no token whose value is markup.
function pg_sw_attr_token_ok($token)
{
    if (!pg_sw_cond_token_allowed($token)) return false;
    $keys = pg_sw_raw_token_keys();
    return !isset($keys[strtolower((string)$token)]);
}

// The tokens of a written attribute value, in order: the names inside
// {{...}} (the characters a binding keeps, no space).
function pg_sw_attr_tokens($value)
{
    if (strpos((string)$value, '{{') === false) return array();
    preg_match_all('/\{\{([A-Za-z0-9_-]+)\}\}/', (string)$value, $m);
    return $m[1];
}

// The carriers waiting for the element being drawn (_render_tree_node()
// writes them after it).
function &_pg_sw_attr_queue()
{
    static $queue = array();
    return $queue;
}

// The escaped value of attribute $name as written ($value), ready to stand
// between double quotes. Inside a widget render each allowed {{token}} is a
// placeholder whose carrier waits in the queue; a token the attribute or
// the token rules refuse prints nothing (fail closed). Outside a widget
// render, and for a value without a token, h($value).
function pg_sw_attr_value_html($name, $value)
{
    $value = (string)$value;
    if (strpos($value, '{{') === false || !pg_sw_finish_active()) return h($value);
    $parts = preg_split('/\{\{([A-Za-z0-9_-]+)\}\}/', $value, -1, PREG_SPLIT_DELIM_CAPTURE);
    if (!is_array($parts) || count($parts) < 2) return h($value);

    static $seq = 0;
    $name_ok = pg_sw_attr_name_ok($name);
    $nonce   = pg_sw_marker_nonce();
    $queue   = &_pg_sw_attr_queue();
    $out     = '';
    foreach ($parts as $i => $part) {
        if ($i % 2 === 0) {
            $out .= h($part);
            continue;
        }
        if (!$name_ok || !pg_sw_attr_token_ok($part)) continue;
        $k = ++$seq;
        $out    .= 'pgav-' . $nonce . '-' . $k . '-';
        $queue[] = '<!--pg-cv:' . $nonce . ':a' . $k . '-->^^' . $part . '^^<!--/pg-cv:' . $nonce . ':a' . $k . '-->';
    }
    return $out;
}

// The number of carriers queued so far: the mark an element's drawing
// starts from.
function pg_sw_attr_mark()
{
    return count(_pg_sw_attr_queue());
}

// The carriers queued since $mark, taken out of the queue. The elements
// drawn inside an element take their own first, so what is left after its
// mark is the element's own.
function pg_sw_attr_take($mark)
{
    $queue = &_pg_sw_attr_queue();
    if (count($queue) <= $mark) return '';
    $out = implode('', array_slice($queue, $mark));
    array_splice($queue, $mark);
    return $out;
}

// The attribute stage of the finish pass: each placeholder of $nonce gets
// the value of its carrier as plain text (tags stripped, entities decoded,
// white space collapsed), escaped once. A placeholder pairs with the next
// carrier of its number the way brackets pair, so a record template drawn
// inside a copy of itself (a reply in its comment) reads its own. The
// carriers stay for the strip; a placeholder with no carrier reads as empty.
function pg_sw_attr_resolve($html, $nonce)
{
    if (!is_string($html) || strpos($html, 'pgav-' . $nonce . '-') === false) return $html;
    $place = 'pgav-' . $nonce . '-';
    $open  = '<!--pg-cv:' . $nonce . ':a';
    $close = '<!--/pg-cv:' . $nonce . ':a';

    // Every placeholder and carrier in document order.
    $events = array();
    $pos = 0;
    while (($at = strpos($html, $place, $pos)) !== false) {
        $p = $at + strlen($place);
        $e = strpos($html, '-', $p);
        if ($e !== false && $e > $p && ctype_digit(substr($html, $p, $e - $p))) {
            $events[] = array($at, 0, substr($html, $p, $e - $p), $e + 1);
            $pos = $e + 1;
        } else {
            $pos = $p;
        }
    }
    $pos = 0;
    while (($at = strpos($html, $open, $pos)) !== false) {
        $p = $at + strlen($open);
        $e = strpos($html, '-->', $p);
        if ($e === false) break;
        $k = substr($html, $p, $e - $p);
        $c = ctype_digit($k) ? strpos($html, $close . $k . '-->', $e + 3) : false;
        if ($c === false) {
            $pos = $e + 3;
            continue;
        }
        $events[] = array($at, 1, $k, substr($html, $e + 3, $c - $e - 3));
        $pos = $c;
    }
    usort($events, function ($a, $b) {
        return ($a[0] < $b[0]) ? -1 : (($a[0] > $b[0]) ? 1 : 0);
    });

    $stack  = array();
    $values = array();
    foreach ($events as $i => $ev) {
        if ($ev[1] === 0) {
            $stack[$ev[2]][] = $i;
        } elseif (!empty($stack[$ev[2]])) {
            $values[array_pop($stack[$ev[2]])] = h(pg_sw_cond_text(pg_sw_sweep_tokens($ev[3])));
        }
    }

    $out = '';
    $pos = 0;
    foreach ($events as $i => $ev) {
        if ($ev[1] !== 0) continue;
        $out .= substr($html, $pos, $ev[0] - $pos) . (isset($values[$i]) ? $values[$i] : '');
        $pos  = $ev[3];
    }
    return $out . substr($html, $pos);
}
