<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// A module of functions.php: where a system widget's blocks come from.
//
// Loaded by functions.php through require_once, never on its own.
//
// One rule: a part the designer placed always wins, and a renderer adds its
// own copy of a block only when the saved tree places none of its tokens
// (pg_sw_placed()).
//
// Default parts: a token that used to stand for a block of markup the
// renderer built (the order view's timeline and status badge) is drawn by a
// part made of real design elements, a list and elements with conditions,
// which the designer can select and change. A saved tree still binding the
// old token is given the part where the widget's tree is read for a render
// (_expand_system_widgets()); the editor gives it the same part when the
// widget loads (_sdDefaultParts() in style_designer.js), and the next save
// stores it.
if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// Whether the widget tree $tree (decoded, or its JSON) places any of
// $tokens: as a binding, a literal ^^token^^ or an attribute {{token}}. A
// value condition names a token without placing it, so conditions are
// taken out first (pg_sw_cond_tree_json()). A token is matched whole:
// '__returns' is not placed by '__returns_list'.
function pg_sw_placed($tree, $tokens)
{
    $json = pg_sw_cond_tree_json($tree);
    foreach ((array)$tokens as $token) {
        $token = (string)$token;
        if ($token === '' || strpos($json, $token) === false) continue;
        if (preg_match('/(?<![A-Za-z0-9_])' . preg_quote($token, '/') . '(?![A-Za-z0-9_])/', $json)) return true;
    }
    return false;
}

// The tokens drawn by a default part, by widget kind: pairs of the token
// and the function that builds the part's nodes from the widget's settings.
// Old names, so no renderer fills them and the editor offers none (pairs,
// not keys, so tools/check_bindings.php does not read them as filled).
function pg_sw_default_part_tokens()
{
    return array(
        'order_view' => array(
            array('__timeline', 'pg_sw_part_order_timeline'),
            array('__order_status_badge', 'pg_sw_part_order_status_badge'),
            array('__item_form_data_html', 'pg_sw_part_order_item_form'),
            array('__item_gift_card_html', 'pg_sw_part_order_item_gift_card'),
        ),
        'comments' => array(
            array('__comment_stars_html', 'pg_sw_part_comment_stars'),
        ),
        // A section binding and a content type are read as the tokens
        // 'section:<name>' and 'content:<type>' (pg_sw_part_token_of()).
        'catalog_listing' => array(
            array('__breadcrumb_html', 'pg_sw_part_catalog_breadcrumb_listing'),
            array('__breadcrumb_inner_html', 'pg_sw_part_catalog_crumbs'),
            array('section:breadcrumb', 'pg_sw_part_catalog_crumbs'),
            array('content:breadcrumb', 'pg_sw_part_catalog_breadcrumb_listing'),
        ),
        'catalog_item_view' => array(
            array('__breadcrumb_html', 'pg_sw_part_catalog_breadcrumb_item'),
            array('__breadcrumb_inner_html', 'pg_sw_part_catalog_crumbs'),
            array('section:breadcrumb', 'pg_sw_part_catalog_crumbs'),
            array('content:breadcrumb', 'pg_sw_part_catalog_breadcrumb_item'),
            array('__cross_sell_html', 'pg_sw_part_catalog_cross_sell'),
        ),
    );
}

// The blocks a renderer put before a widget's main loop whose layout
// placed none of their tokens, now default parts added just before the
// main loop once, by widget kind: pairs of the tokens that place the block
// and the function that builds it from the widget's settings.
function pg_sw_default_lead_parts()
{
    return array(
        'catalog_listing' => array(
            array(array('__breadcrumb_html', '__breadcrumb_inner_html', 'catalog.crumbs'), 'pg_sw_part_catalog_breadcrumb_listing'),
        ),
    );
}

// Puts $nodes just before the main loop under $node (pg_sw_main_loop_ref()).
// Returns whether there was a main loop to put them before.
function pg_sw_insert_before_main_loop(&$node, $nodes)
{
    if (!is_array($node) || pg_sw_list_is_sourced($node) || !isset($node['children']) || !is_array($node['children'])) return false;
    foreach ($node['children'] as $i => $child) {
        if (is_array($child) && isset($child['type']) && $child['type'] === 'loop_area' && !pg_sw_list_is_sourced($child)) {
            array_splice($node['children'], $i, 0, $nodes);
            return true;
        }
        if (pg_sw_insert_before_main_loop($node['children'][$i], $nodes)) return true;
    }
    return false;
}

// The blocks a renderer added after a widget whose layout placed none of
// their tokens, now default parts added to the end of the tree once, by
// widget kind: pairs of the tokens that place the block and the function
// that builds it from the widget's settings and tree. In the order the
// renderer added them.
function pg_sw_default_tail_parts()
{
    return array(
        'order_view' => array(
            array(array('__return_form', '__returns'), 'pg_sw_part_order_returns'),
            array(array('__legal_preinfo_url', '__legal_contract_url'), 'pg_sw_part_order_legal_documents'),
        ),
    );
}

// The blocks a renderer added after each row of a widget's main loop whose
// row placed none of their tokens, now default parts added to the end of
// the main loop once, by widget kind: pairs of the tokens that place the
// block (the old token, the list the part draws) and the function that
// builds it, then 'tree' when the block is placed by its tokens anywhere in
// the tree (one block a page, added to the end of the tree when there is no
// main loop). In the order the renderer added them.
function pg_sw_default_loop_tail_parts()
{
    return array(
        'order_view' => array(
            array(array('__item_form_data_html', 'order.item_forms'), 'pg_sw_part_order_item_form'),
            array(array('__item_gift_card_html', 'order.item_gift_cards'), 'pg_sw_part_order_item_gift_card'),
        ),
        'catalog_item_view' => array(
            array(array('__cross_sell_html', 'product.related'), 'pg_sw_part_catalog_cross_sell', 'tree'),
        ),
    );
}

// The main loop of the widget tree $node (its first loop_area without a
// source, lists not searched), by reference; null for none.
function &pg_sw_main_loop_ref(&$node)
{
    $none = null;
    if (!is_array($node) || pg_sw_list_is_sourced($node)) return $none;
    if (isset($node['type']) && $node['type'] === 'loop_area') return $node;
    if (isset($node['children']) && is_array($node['children'])) {
        foreach ($node['children'] as $i => $child) {
            $found = &pg_sw_main_loop_ref($node['children'][$i]);
            if ($found !== null) return $found;
            unset($found);
        }
    }
    return $none;
}

// Adds to the end of $tree's main loop each row block of $kind its row
// does not place (pg_sw_default_loop_tail_parts()). Returns whether
// anything was added.
function pg_sw_default_loop_tails(&$tree, $kind, $cfg)
{
    $all = pg_sw_default_loop_tail_parts();
    if (!isset($all[$kind])) return false;
    $placed = $tree;
    $loop = &pg_sw_main_loop_ref($tree);
    $row = array('type' => 'root', 'children' => ($loop !== null && isset($loop['children']) && is_array($loop['children'])) ? $loop['children'] : array());
    $added = false;
    foreach ($all[$kind] as $pair) {
        $whole = !empty($pair[2]);
        if (pg_sw_placed($whole ? $placed : $row, $pair[0])) continue;
        if ($loop !== null) {
            $loop['children'] = array_merge(isset($loop['children']) && is_array($loop['children']) ? $loop['children'] : array(), call_user_func($pair[1], $cfg));
        } elseif ($whole) {
            $tree['children'] = array_merge(isset($tree['children']) && is_array($tree['children']) ? $tree['children'] : array(), call_user_func($pair[1], $cfg));
        } else {
            continue;
        }
        $added = true;
    }
    return $added;
}

// $tree_json with every node that draws an old token of $kind given its
// default part, each block the layout does not place added at its end
// (pg_sw_default_tail_parts()), and in an e-mail the parts' look written
// inline. JSON that needs none of it comes back as it is.
function pg_sw_default_parts_apply($tree_json, $kind, $cfg = array(), $email = false)
{
    $tree_json = (string)$tree_json;
    $all   = pg_sw_default_part_tokens();
    $parts = array();
    foreach (isset($all[$kind]) ? $all[$kind] : array() as $pair) $parts[$pair[0]] = $pair[1];
    $found = false;
    foreach ($parts as $token => $builder) {
        $colon = strpos($token, ':');
        if (strpos($tree_json, ($colon === false) ? $token : '"' . substr($token, $colon + 1) . '"') !== false) { $found = true; break; }
    }
    $tails = pg_sw_default_tail_parts();
    $due = array();
    foreach (isset($tails[$kind]) ? $tails[$kind] : array() as $pair) {
        if (!pg_sw_placed($tree_json, $pair[0])) $due[] = $pair[1];
    }
    // A row block is due unless the whole tree places it (then the row
    // itself is read below).
    $loop_due = false;
    $loop_tails = pg_sw_default_loop_tail_parts();
    foreach (isset($loop_tails[$kind]) ? $loop_tails[$kind] : array() as $pair) {
        if (!pg_sw_placed($tree_json, $pair[0])) { $loop_due = true; break; }
    }
    // A lead block is read again once the parts are given: a section
    // binding or a content type places it only as its part.
    $leads = pg_sw_default_lead_parts();
    $lead_due = false;
    foreach (isset($leads[$kind]) ? $leads[$kind] : array() as $pair) {
        if (!pg_sw_placed($tree_json, $pair[0])) { $lead_due = true; break; }
    }
    $mail = $email && (strpos($tree_json, 'pg-ov-timeline') !== false || strpos($tree_json, 'pg-comment-stars') !== false);
    if (!$found && !$due && !$loop_due && !$lead_due && !$mail) return $tree_json;

    $tree = json_decode($tree_json, true);
    if (!is_array($tree)) return $tree_json;
    $cfg = is_array($cfg) ? $cfg : array();
    $changed = $found ? pg_sw_default_parts_tree($tree, $parts, $cfg) : false;
    if (!pg_sw_tree_is_placeholder($tree)) {
        $changed = pg_sw_default_loop_tails($tree, $kind, $cfg) || $changed;
    }
    if ($lead_due && !pg_sw_tree_is_placeholder($tree)) {
        foreach ($leads[$kind] as $pair) {
            if (pg_sw_placed($tree, $pair[0])) continue;
            $changed = pg_sw_insert_before_main_loop($tree, call_user_func($pair[1], $cfg)) || $changed;
        }
    }
    if ($due && !pg_sw_tree_is_placeholder($tree)) {
        $placed = $tree;
        if (!isset($tree['children']) || !is_array($tree['children'])) $tree['children'] = array();
        foreach ($due as $builder) {
            $tree['children'] = array_merge($tree['children'], call_user_func($builder, $cfg, $placed));
        }
        $changed = true;
    }
    if ($email) {
        $changed = pg_sw_parts_mail_styles($tree) || $changed;
        $changed = pg_sw_parts_mail_stars($tree) || $changed;
    }
    if (!$changed) return $tree_json;
    $out = json_encode($tree, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return ($out === false) ? $tree_json : $out;
}

// Whether $tree is the placeholder a design template leaves for the editor
// to fill with the kind's starter layout (_swIsTemplatePlaceholder()): a
// root holding one empty loop area. It draws nothing, and gets no part.
function pg_sw_tree_is_placeholder($tree)
{
    if (!is_array($tree) || !isset($tree['type']) || $tree['type'] !== 'root' || !empty($tree['props'])) return false;
    if (!isset($tree['children']) || !is_array($tree['children']) || count($tree['children']) !== 1) return false;
    $la = $tree['children'][0];
    return is_array($la) && isset($la['type']) && $la['type'] === 'loop_area' && empty($la['children']) && empty($la['props']);
}

// Gives every node under $node that draws a token of $parts its part.
// Returns whether anything changed.
function pg_sw_default_parts_tree(&$node, $parts, $cfg)
{
    if (!is_array($node)) return false;
    $token = pg_sw_part_token_of($node, array_keys($parts));
    if (strncmp($token, 'content:', 8) === 0) {
        // The content node stood for the whole block: the part takes its
        // place.
        $built = call_user_func($parts[$token], $cfg);
        $node = $built[0];
        return true;
    }
    if (strncmp($token, 'section:', 8) === 0) {
        // The bound element keeps its tag, classes and attributes; the
        // part replaces what it held, as the section's markup did.
        unset($node['props']['_bindings']['section']);
        if (!$node['props']['_bindings']) unset($node['props']['_bindings']);
        $node['children'] = call_user_func($parts[$token], $cfg);
        return true;
    }
    if ($token !== '') {
        $host = pg_sw_part_host($node);
        $host['children'] = array_merge(
            call_user_func($parts[$token], $cfg),
            (isset($host['children']) && is_array($host['children'])) ? $host['children'] : array()
        );
        $node = $host;
        return true;
    }
    $changed = false;
    if (isset($node['children']) && is_array($node['children'])) {
        foreach ($node['children'] as $i => $child) {
            if (pg_sw_default_parts_tree($child, $parts, $cfg)) {
                $node['children'][$i] = $child;
                $changed = true;
            }
        }
    }
    return $changed;
}

// The token of $tokens that $node draws: its text binding, or a text that is
// the token alone (^^token^^, as older layouts wrote it); its section
// binding as 'section:<name>', a content node as 'content:<type>'. '' for
// none.
function pg_sw_part_token_of($node, $tokens)
{
    $props = (isset($node['props']) && is_array($node['props'])) ? $node['props'] : array();
    if (isset($props['_bindings']['section']) && is_string($props['_bindings']['section'])
        && in_array('section:' . $props['_bindings']['section'], $tokens, true)) {
        return 'section:' . $props['_bindings']['section'];
    }
    if (isset($node['type'], $props['contentType']) && $node['type'] === 'content' && is_string($props['contentType'])
        && in_array('content:' . $props['contentType'], $tokens, true)) {
        return 'content:' . $props['contentType'];
    }
    if (isset($props['_bindings']['text']) && in_array($props['_bindings']['text'], $tokens, true)) {
        return $props['_bindings']['text'];
    }
    // A custom HTML node holding the token alone counts as its text.
    $text = (isset($props['contentType']) && $props['contentType'] === 'custom_html' && isset($props['html'])) ? $props['html']
          : (isset($props['text']) ? $props['text'] : null);
    if (is_string($text)
        && preg_match('/^\s*\^\^([A-Za-z0-9_]+)\^\^\s*$/', $text, $m)
        && in_array($m[1], $tokens, true)) {
        return $m[1];
    }
    return '';
}

// $node as an element that holds a part: its text and text binding go, a
// content node (a paragraph, a heading, a span) becomes the element it
// drew, its classes, attributes, name and other bindings kept.
function pg_sw_part_host($node)
{
    $props = (isset($node['props']) && is_array($node['props'])) ? $node['props'] : array();
    unset($props['text']);
    foreach (array('_bindings', '_bindParams', '_bindFormats') as $key) {
        if (!isset($props[$key]) || !is_array($props[$key])) continue;
        unset($props[$key]['text']);
        if (!$props[$key]) unset($props[$key]);
    }
    $type = isset($node['type']) ? $node['type'] : '';
    if ($type === 'content') {
        $ct  = isset($props['contentType']) ? $props['contentType'] : '';
        $tag = 'div';
        if ($ct === 'paragraph') $tag = 'p';
        elseif ($ct === 'span' || $ct === 'text') $tag = 'span';
        elseif ($ct === 'heading') $tag = (isset($props['tag']) && preg_match('/^h[1-6]$/i', (string)$props['tag'])) ? strtolower($props['tag']) : 'h2';
        // The classes _render_content_html() writes for these two props.
        $cls = array();
        if (!empty($props['lead']) && $ct === 'paragraph') $cls[] = 'lead';
        if (!empty($props['align'])) $cls[] = 'text-' . $props['align'];
        if (isset($props['cssClass']) && (string)$props['cssClass'] !== '') $cls[] = (string)$props['cssClass'];
        unset($props['contentType'], $props['lead'], $props['align'], $props['html']);
        $props['tag'] = $tag;
        $props['cssClass'] = implode(' ', $cls);
        $type = 'semantic';
    } elseif ($type !== 'semantic') {
        // Any other node moves into a <div> that takes its place.
        $node['props'] = $props;
        return array('type' => 'semantic', 'props' => array('tag' => 'div'), 'children' => array($node));
    }
    $node['type']  = $type;
    $node['props'] = $props;
    if (!isset($node['children']) || !is_array($node['children'])) $node['children'] = array();
    return $node;
}

// One element of a part: a semantic node of $tag with $class, the
// editor's $name, $props on top and $children.
function pg_sw_part_el($tag, $class, $name, $props = array(), $children = array())
{
    return array(
        'type'     => 'semantic',
        'props'    => array_merge(array('tag' => $tag, 'cssClass' => $class, 'customName' => $name), $props),
        'children' => $children,
    );
}

// The props of an element drawn only when $token passes $op ($value).
function pg_sw_part_when($token, $op, $value = '')
{
    $param = array('op' => $op);
    if ($value !== '') $param['value'] = $value;
    return array('_bindings' => array('show_if' => $token), '_bindParams' => array('show_if' => $param));
}

// The order timeline: one item per step of the order (the order.steps
// list), its icon and colour chosen by the step's kind, an estimated date
// greyed, the label, a cancellation's reason after a dash, the date. The
// look is in pg_carousel_layout.css (.pg-ov-timeline).
function pg_sw_part_order_timeline($cfg = array())
{
    $icon = function ($colour, $bi, $props = array()) {
        return pg_sw_part_el('span', 'pg-ov-timeline-icon bg-' . $colour, 'Icon', $props, array(
            pg_sw_part_el('i', 'bi ' . $bi, 'Icon'),
        ));
    };
    $kind = function ($kind, $children) {
        return pg_sw_part_el('span', '', 'Step: ' . $kind, pg_sw_part_when('__step_kind', 'eq', $kind), $children);
    };
    $dated = function ($colour, $bi) use ($icon) {
        return array(
            $icon($colour, $bi, pg_sw_part_when('__step_is_estimate', 'empty')),
            $icon('secondary', $bi, pg_sw_part_when('__step_is_estimate', 'not_empty')),
        );
    };
    $text = function ($tag, $class, $name, $token, $sample) {
        return pg_sw_part_el($tag, $class, $name, array('text' => $sample, '_bindings' => array('text' => $token)));
    };
    return array(
        pg_sw_part_el('ul', 'pg-ov-timeline', 'Order timeline', array(), array(
            array('type' => 'loop_area', 'props' => array('source' => 'order.steps'), 'children' => array(
                pg_sw_part_el('li', 'pg-ov-timeline-item', 'Step', array(), array(
                    $kind('created',   array($icon('primary', 'bi-receipt'))),
                    $kind('paid',      array($icon('success', 'bi-credit-card'))),
                    $kind('shipped',   $dated('info', 'bi-truck')),
                    $kind('delivered', $dated('success', 'bi-box-seam')),
                    $kind('cancelled', array($icon('danger', 'bi-x-circle'))),
                    pg_sw_part_el('div', 'pg-ov-timeline-label', 'Step', array(), array(
                        $text('span', '', 'Step', '__step_label', 'Order Created'),
                        pg_sw_part_el('span', '', 'Dash', array_merge(array('text' => ' — '), pg_sw_part_when('__step_note', 'not_empty'))),
                        $text('span', '', 'Note (the reason of a cancellation)', '__step_note', ''),
                    )),
                    $text('div', 'pg-ov-timeline-ts', 'Date', '__step_date', '20.10.2026 10:00'),
                )),
            )),
        )),
    );
}

// The stages of an order's status badge, in the order the part lists them
// (_pg_order_status_stage()).
function pg_sw_order_status_stages()
{
    return array('complete', 'pending', 'shipped', 'cancelled', 'refunded', 'default');
}

// The order's status badge: one badge per stage of the status, drawn when
// the order is at that stage, with the classes the widget's settings gave
// that stage (_pg_order_status_badge_class()). The text is the status as
// the order view shows it.
function pg_sw_part_order_status_badge($cfg = array())
{
    $out = array();
    foreach (pg_sw_order_status_stages() as $stage) {
        $out[] = pg_sw_part_el('span', _pg_order_status_badge_class($stage, $cfg), 'Status: ' . $stage, array_merge(
            array('text' => 'Completed', '_bindings' => array('text' => '__order_status_text', 'show_if' => '__order_status_stage')),
            array('_bindParams' => array('show_if' => array('op' => 'eq', 'value' => $stage)))
        ));
    }
    return $out;
}

// The props of an element showing $token, $sample on the canvas, drawn
// only when $token passes $op when one is given.
function pg_sw_part_value($token, $sample, $op = '')
{
    $props = array('text' => $sample, '_bindings' => array('text' => $token));
    if ($op !== '') {
        $props['_bindings']['show_if'] = $token;
        $props['_bindParams'] = array('show_if' => array('op' => $op));
    }
    return $props;
}

// The heading of an item's form or gift card: its name, and its number
// among the item's when there is more than one.
function pg_sw_part_order_item_legend($props, $name, $number, $count)
{
    return pg_sw_part_el('legend', 'float-none w-auto px-2 fs-6 fw-semibold', 'Heading', $props, array(
        pg_sw_part_el('span', '', 'Name', $name),
        pg_sw_part_el('span', '', 'Number', array_merge(array('text' => ' (^^' . $number . '^^ / ^^' . $count . '^^)'), pg_sw_part_when($count, 'gt', '1'))),
    ));
}

// One label above its value, as the order view prints a form's answers:
// the value element takes $props and $children.
function pg_sw_part_order_item_line($label, $props, $children = array())
{
    return pg_sw_part_el('div', 'mb-2', $label, array(), array(
        pg_sw_part_el('div', 'form-label mb-0 fw-semibold', 'Label', array('text' => $label)),
        pg_sw_part_el('div', 'pg-ov-form-value', $label, $props, $children),
    ));
}

// The answers an item's buyer gave in the product's order form, printed: a
// box per form (order.item_forms; one per unit for 'One Form per
// Quantity'), its heading, then each field's label above its answer
// (order.item_form_fields); an information field shows its label and text.
// The box carries the item's id for page scripts (data-pg-ov-item-form).
function pg_sw_part_order_item_form($cfg = array())
{
    return array(pg_sw_part_el('div', 'pg-ov-item-form mt-2', 'Product Form Answers', array_merge(
        array('_attrs' => array(array('name' => 'data-pg-ov-item-form', 'value' => '{{__item_id}}'))),
        pg_sw_part_when('__item_has_form_data', 'not_empty')
    ), array(
        array('type' => 'loop_area', 'props' => array('source' => 'order.item_forms'), 'children' => array(
            pg_sw_part_el('fieldset', 'pg-ov-item-form-block border rounded p-3 mt-2', 'Form', array(), array(
                pg_sw_part_order_item_legend(pg_sw_part_when('__form_has_legend', 'not_empty'),
                    pg_sw_part_value('__form_name', 'Order Form'), '__form_number', '__form_count'),
                array('type' => 'loop_area', 'props' => array('source' => 'order.item_form_fields'), 'children' => array(
                    pg_sw_part_el('div', 'mb-2', 'Field', pg_sw_part_when('__field_is_info', 'empty'), array(
                        pg_sw_part_el('div', 'form-label mb-0 fw-semibold', 'Label', pg_sw_part_value('__field_label', 'Label')),
                        pg_sw_part_el('div', 'pg-ov-form-value', 'Answer', pg_sw_part_value('__field_value_html', 'Answer')),
                    )),
                    pg_sw_part_el('div', 'mb-2 small', 'Information', pg_sw_part_when('__field_is_info', 'not_empty'), array(
                        pg_sw_part_el('div', 'fw-semibold', 'Label', pg_sw_part_value('__field_label', 'Label', 'not_empty')),
                        pg_sw_part_el('div', 'text-muted', 'Information', pg_sw_part_value('__field_info_html', '')),
                    )),
                )),
            )),
        )),
    )));
}

// An item's gift cards, printed: a box per card filled in
// (order.item_gift_cards), its heading, then the amount, the recipient's
// address, the sender's name, the message and the delivery date, each
// label above its value. The box carries the item's id for page scripts
// (data-pg-ov-item-gift-card).
function pg_sw_part_order_item_gift_card($cfg = array())
{
    return array(pg_sw_part_el('div', 'pg-ov-item-gift-card mt-2', 'Gift Card Details', array_merge(
        array('_attrs' => array(array('name' => 'data-pg-ov-item-gift-card', 'value' => '{{__item_id}}'))),
        pg_sw_part_when('__item_has_gift_card', 'not_empty')
    ), array(
        array('type' => 'loop_area', 'props' => array('source' => 'order.item_gift_cards'), 'children' => array(
            pg_sw_part_el('fieldset', 'pg-ov-item-gift-card-block border rounded p-3 mt-2', 'Gift card', array(), array(
                pg_sw_part_order_item_legend(array(), array('text' => lang('Gift card')), '__gift_card_number', '__gift_card_count'),
                pg_sw_part_order_item_line(lang('Amount'), array(), array(
                    pg_sw_part_el('strong', '', 'Amount', pg_sw_part_value('__gift_card_amount', '100,00')),
                )),
                pg_sw_part_order_item_line(lang('Recipient Email'), pg_sw_part_value('__gift_card_email', 'name@example.com')),
                pg_sw_part_order_item_line(lang('Your Name'), pg_sw_part_value('__gift_card_from_name', 'Name')),
                pg_sw_part_order_item_line(lang('Message'), pg_sw_part_value('__gift_card_message_html', 'Message')),
                pg_sw_part_order_item_line(lang('Delivery Date'), pg_sw_part_value('__gift_card_delivery', '20.10.2026')),
            )),
        )),
    )));
}

// A card added after the order view: the shell the returns and legal
// documents cards had, a card with a small heading, shown by $flag.
function pg_sw_part_order_card($class, $name, $flag, $heading, $body)
{
    return pg_sw_part_el('div', 'container pb-4 ' . $class, $name, array('_bindings' => array('eo_visible_if' => $flag)), array(
        pg_sw_part_el('div', 'card', 'Card', array(), array(
            pg_sw_part_el('div', 'card-header', 'Card header', array(), array(
                pg_sw_part_el('h6', 'mb-0 fw-semibold', 'Heading', array('text' => $heading)),
            )),
            pg_sw_part_el('div', 'card-body', 'Card body', array(), $body),
        )),
    ));
}

// The returns card: the order's return requests and the request form,
// both still built by returns.php (__returns, __return_form), shown while
// either has something and the setting is on (show_returns_card).
function pg_sw_part_order_returns($cfg = array(), $tree = array())
{
    $block = function ($token, $name) {
        return pg_sw_part_el('div', '', $name, array('_bindings' => array('text' => $token)));
    };
    return array(pg_sw_part_order_card('pg-ov-returns-block', 'Returns', 'show_returns_card', lang('Returns'), array(
        $block('__returns', 'Return requests'),
        $block('__return_form', 'Return request form'),
    )));
}

// The legal documents card: a link to each copy kept with the order, in a
// new window, and the last day of the right of withdrawal under them unless
// the layout shows that day itself.
function pg_sw_part_order_legal_documents($cfg = array(), $tree = array())
{
    $types = pg_legal_document_types();
    $link = function ($type, $token, $flag) use ($types) {
        return pg_sw_part_el('li', '', $types[$type], array('_bindings' => array('eo_visible_if' => $flag)), array(
            pg_sw_part_el('a', '', $types[$type], array(
                'text' => $types[$type], 'href' => '#', 'target' => '_blank', 'rel' => 'noopener',
                '_bindings' => array('href' => $token),
            )),
        ));
    };
    $body = array(pg_sw_part_el('ul', 'list-unstyled mb-0', 'Links', array(), array(
        $link('preinfo', '__legal_preinfo_url', 'has_legal_preinfo'),
        $link('contract', '__legal_contract_url', 'has_legal_contract'),
    )));
    if (!pg_sw_placed($tree, array('__withdrawal_deadline', '__withdrawal_text'))) {
        $body[] = pg_sw_part_el('p', 'small text-muted mt-2 mb-0', 'Right of Withdrawal', array(
            'text' => '', '_bindings' => array('text' => '__withdrawal_text', 'eo_visible_if' => 'has_withdrawal_deadline'),
        ));
    }
    return array(pg_sw_part_order_card('pg-ov-legal-documents', 'Legal Documents', 'has_legal_documents', lang('Legal Documents'), $body));
}

// A comment's stars: five drawings in a span said once as "Rated N out of
// 5" (__comment_rating_label), the first N filled (__comment_rating gte
// the star's place), the rest empty. Drawn only for a rating; the colour
// comes from the design (text-warning in the starters). The look that
// keeps the drawings side by side is in pg_carousel_layout.css.
function pg_sw_part_comment_stars($cfg = array())
{
    $stars = array();
    for ($i = 1; $i <= 5; $i++) {
        $stars[] = pg_sw_part_comment_star(true, $i);
        $stars[] = pg_sw_part_comment_star(false, $i);
    }
    return array(pg_sw_part_el('span', 'pg-comment-stars', 'Stars', array_merge(
        array('_attrs' => array(
            array('name' => 'role', 'value' => 'img'),
            array('name' => 'aria-label', 'value' => '{{__comment_rating_label}}'),
        )),
        pg_sw_part_when('__comment_rating', 'not_empty')
    ), $stars));
}

// The star at place $place, filled or empty: an SVG drawing node, the one
// _pg_comments_star_svg() draws, shown by the comment's rating.
function pg_sw_part_comment_star($filled, $place)
{
    return array(
        'type'     => 'content',
        'props'    => array_merge(
            array('contentType' => 'svg', 'html' => _pg_comments_star_svg($filled), 'customName' => $filled ? 'Filled star' : 'Empty star'),
            pg_sw_part_when('__comment_rating', $filled ? 'gte' : 'lt', (string)$place)
        ),
        'children' => array(),
    );
}

// The crumbs of the catalog path as the catalog widgets drew them: an
// ol.breadcrumb of one item per crumb (catalog.crumbs), a link, words, or
// where the visitor is (span.active), by the crumb's kind. Drawn only when
// the widget draws a path (__has_breadcrumb), unless $when is false (the
// nav around it asks).
function pg_sw_part_catalog_crumbs($cfg = array(), $when = true)
{
    $crumb = function ($tag, $class, $name, $kind, $props = array()) {
        return pg_sw_part_el($tag, $class, $name, array_merge($props, array(
            'text'        => '',
            '_bindings'   => array_merge(isset($props['_bindings']) ? $props['_bindings'] : array(), array('text' => '__crumb_label', 'show_if' => '__crumb_kind')),
            '_bindParams' => array('show_if' => array('op' => 'eq', 'value' => $kind)),
        )));
    };
    return array(pg_sw_part_el('ol', 'breadcrumb mb-0', 'Breadcrumb List', $when ? pg_sw_part_when('__has_breadcrumb', 'not_empty') : array(), array(
        array('type' => 'loop_area', 'props' => array('source' => 'catalog.crumbs'), 'children' => array(
            pg_sw_part_el('li', 'breadcrumb-item', 'Crumb', array(), array(
                $crumb('a', '', 'Link', 'link', array('href' => '#', '_bindings' => array('href' => '__crumb_url'))),
                $crumb('span', 'active', 'Where the visitor is', 'active'),
                $crumb('span', '', 'Words', 'text'),
            )),
        )),
    )));
}

// The breadcrumb a catalog widget drew: a nav of $class said as
// "breadcrumb" around the crumbs, drawn only when the widget draws a path.
function pg_sw_part_catalog_breadcrumb($class)
{
    return array(pg_sw_part_el('nav', $class, 'Breadcrumb', array_merge(
        array('_attrs' => array(array('name' => 'aria-label', 'value' => 'breadcrumb'))),
        pg_sw_part_when('__has_breadcrumb', 'not_empty')
    ), pg_sw_part_catalog_crumbs(array(), false)));
}

// The listing's breadcrumb (pg-sw-breadcrumb), as it stood before the
// toolbar.
function pg_sw_part_catalog_breadcrumb_listing($cfg = array())
{
    return pg_sw_part_catalog_breadcrumb('pg-sw-breadcrumb mb-3');
}

// The detail view's breadcrumb (pg-civ-breadcrumb).
function pg_sw_part_catalog_breadcrumb_item($cfg = array())
{
    return pg_sw_part_catalog_breadcrumb('pg-civ-breadcrumb mb-3');
}

// The products bought together with the one a catalog detail view shows,
// as the view added them after its record: a box the variant script finds
// (data-pg-civ-cross-sell), drawn unless switched off (__has_related
// empty), holding, when there are any (__has_related 1, 0 for none), a
// heading and a row of up to four cards (product.related), each a link
// around the picture (or a grey box without one), the short description
// (else the name) and the price, struck through beside the sale price when
// a discount applies. The pictures' shape is in pg_carousel_layout.css.
function pg_sw_part_catalog_cross_sell($cfg = array())
{
    $value = function ($tag, $class, $name, $token, $op = '', $test = '') {
        $props = array('text' => '', '_bindings' => array('text' => $token));
        if ($op !== '') {
            $props['_bindings']['show_if'] = $test !== '' ? $test : $token;
            $props['_bindParams'] = array('show_if' => array('op' => $op));
        }
        return pg_sw_part_el($tag, $class, $name, $props);
    };
    $has = pg_sw_part_when('__has_related', 'eq', '1');
    $card = pg_sw_part_el('a', 'card h-100 text-decoration-none text-body', 'Product card', array('href' => '#', '_bindings' => array('href' => '__related_url')), array(
        array('type' => 'content', 'props' => array(
            'contentType' => 'image', 'src' => '', 'alt' => '', 'fluid' => false, 'rounded' => false, 'loading' => 'lazy',
            'cssClass' => 'card-img-top', 'customName' => 'Picture',
            '_bindings' => array('src' => '__related_image_url', 'show_if' => '__related_image_url'),
            '_bindParams' => array('show_if' => array('op' => 'not_empty')),
        ), 'children' => array()),
        pg_sw_part_el('div', 'card-img-top bg-body-tertiary', 'No picture', pg_sw_part_when('__related_image_url', 'empty')),
        pg_sw_part_el('div', 'card-body p-2', 'Card Body', array(), array(
            $value('div', 'small fw-semibold text-truncate', 'Short Description', '__related_short_description', 'not_empty'),
            $value('div', 'small fw-semibold text-truncate', 'Name', '__related_name', 'empty', '__related_short_description'),
            pg_sw_part_el('div', 'small mt-1', 'Price', array(), array(
                pg_sw_part_el('span', 'pg-price-block has-discount', 'Discounted price', pg_sw_part_when('__related_original_price', 'not_empty'), array(
                    $value('span', 'pg-price-original', 'Price before the discount', '__related_original_price'),
                    $value('span', 'pg-price-current', 'Price', '__related_price'),
                )),
                pg_sw_part_el('span', 'pg-price-block', 'Price', pg_sw_part_when('__related_original_price', 'empty'), array(
                    $value('span', 'pg-price', 'Price', '__related_price'),
                )),
            )),
        )),
    ));
    return array(pg_sw_part_el('div', 'pg-civ-cross-sell mt-4', 'Products bought together', array_merge(array(
        '_attrs' => array(array('name' => 'data-pg-civ-cross-sell', 'value' => '1')),
    ), pg_sw_part_when('__has_related', 'not_empty')), array(
        pg_sw_part_el('h3', 'h5 mb-3', 'Heading', array_merge(array('text' => lang('Frequently bought together')), $has)),
        pg_sw_part_el('div', 'row g-3', 'Products', $has, array(
            array('type' => 'loop_area', 'props' => array('source' => 'product.related', 'limit' => 4), 'children' => array(
                pg_sw_part_el('div', 'col-6 col-md-3', 'Product', array(), array($card)),
            )),
        )),
    )));
}

// The stylesheet a page needs for the parts in a widget's output $html
// (pg_carousel_layout.css: the comment stars side by side), once a
// request; '' when $html holds none. Live pages only: an e-mail draws the
// stars as characters (pg_sw_parts_mail_stars()).
function pg_sw_parts_css($html)
{
    return (strpos((string)$html, 'pg-comment-stars') !== false) ? _pg_widget_layout_css_once() : '';
}

// The look of the parts in an e-mail, where the stylesheet may be dropped:
// written inline before the element's own style, which wins on a repeat.
// Plain boxes and colours only: no flex, no positioning, no pseudo element.
function pg_sw_parts_mail_style_map()
{
    return array(
        'pg-ov-timeline'       => 'list-style:none;padding:0;margin:0',
        'pg-ov-timeline-item'  => 'padding:0 0 12px 0',
        'pg-ov-timeline-icon'  => 'display:inline-block;width:12px;height:12px;border-radius:50%;margin-right:8px;vertical-align:middle',
        'pg-ov-timeline-label' => 'display:inline;font-weight:600',
        'pg-ov-timeline-ts'    => 'color:#6c757d;font-size:14px;padding-left:20px',
    );
}

// Writes the e-mail look on every node under $node carrying a class of the
// map, an icon's colour from its bg-* class. Returns whether anything
// changed.
function pg_sw_parts_mail_styles(&$node)
{
    if (!is_array($node)) return false;
    static $colours = array(
        'primary' => '#0d6efd', 'success' => '#198754', 'info' => '#0dcaf0',
        'danger'  => '#dc3545', 'secondary' => '#6c757d', 'warning' => '#ffc107',
    );
    $changed = false;
    $classes = isset($node['props']['cssClass']) ? preg_split('/\s+/', trim((string)$node['props']['cssClass'])) : array();
    $css = '';
    $map = pg_sw_parts_mail_style_map();
    foreach ($classes as $c) {
        if (isset($map[$c])) $css .= $map[$c] . ';';
        if (strpos($c, 'bg-') === 0 && isset($colours[substr($c, 3)]) && in_array('pg-ov-timeline-icon', $classes, true)) {
            $css .= 'background-color:' . $colours[substr($c, 3)] . ';';
        }
    }
    if ($css !== '') {
        $attrs = (isset($node['props']['_attrs']) && is_array($node['props']['_attrs'])) ? $node['props']['_attrs'] : array();
        $done = false;
        foreach ($attrs as $i => $a) {
            if (is_array($a) && isset($a['name']) && strtolower((string)$a['name']) === 'style') {
                $attrs[$i]['value'] = $css . (isset($a['value']) ? (string)$a['value'] : '');
                $done = true;
                break;
            }
        }
        if (!$done) $attrs[] = array('name' => 'style', 'value' => rtrim($css, ';'));
        $node['props']['_attrs'] = $attrs;
        $changed = true;
    }
    if (isset($node['children']) && is_array($node['children'])) {
        foreach ($node['children'] as $i => $child) {
            if (pg_sw_parts_mail_styles($child)) {
                $node['children'][$i] = $child;
                $changed = true;
            }
        }
    }
    return $changed;
}

// In an e-mail, the star drawings of every comment stars span under $node
// (pg_sw_part_comment_stars()) become the stars as characters
// (__comment_stars_text): mail clients drop SVG. The span's other children
// stay. Returns whether anything changed.
function pg_sw_parts_mail_stars(&$node)
{
    if (!is_array($node)) return false;
    $changed = false;
    $classes = isset($node['props']['cssClass']) ? preg_split('/\s+/', trim((string)$node['props']['cssClass'])) : array();
    if (in_array('pg-comment-stars', $classes, true) && isset($node['children']) && is_array($node['children'])) {
        $kids = array();
        $text = false;
        foreach ($node['children'] as $child) {
            $is_star = is_array($child) && isset($child['type'], $child['props']['contentType'], $child['props']['html'])
                && $child['type'] === 'content' && $child['props']['contentType'] === 'svg'
                && preg_match('/\bbi-star(-fill)?\b/', (string)$child['props']['html']);
            if (!$is_star) {
                $kids[] = $child;
                continue;
            }
            if (!$text) {
                $kids[] = array('type' => 'content', 'props' => array('contentType' => 'custom_html', 'html' => '^^__comment_stars_text^^'), 'children' => array());
                $text = true;
            }
            $changed = true;
        }
        $node['children'] = $kids;
    }
    if (isset($node['children']) && is_array($node['children'])) {
        foreach ($node['children'] as $i => $child) {
            if (pg_sw_parts_mail_stars($child)) {
                $node['children'][$i] = $child;
                $changed = true;
            }
        }
    }
    return $changed;
}
