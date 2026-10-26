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
    );
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
// builds it. In the order the renderer added them.
function pg_sw_default_loop_tail_parts()
{
    return array(
        'order_view' => array(
            array(array('__item_form_data_html', 'order.item_forms'), 'pg_sw_part_order_item_form'),
            array(array('__item_gift_card_html', 'order.item_gift_cards'), 'pg_sw_part_order_item_gift_card'),
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
    $loop = &pg_sw_main_loop_ref($tree);
    if ($loop === null) return false;
    $row = array('type' => 'root', 'children' => isset($loop['children']) && is_array($loop['children']) ? $loop['children'] : array());
    $added = false;
    foreach ($all[$kind] as $pair) {
        if (pg_sw_placed($row, $pair[0])) continue;
        $loop['children'] = array_merge(isset($loop['children']) && is_array($loop['children']) ? $loop['children'] : array(), call_user_func($pair[1], $cfg));
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
        if (strpos($tree_json, $token) !== false) { $found = true; break; }
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
    $mail = $email && strpos($tree_json, 'pg-ov-timeline') !== false;
    if (!$found && !$due && !$loop_due && !$mail) return $tree_json;

    $tree = json_decode($tree_json, true);
    if (!is_array($tree)) return $tree_json;
    $cfg = is_array($cfg) ? $cfg : array();
    $changed = $found ? pg_sw_default_parts_tree($tree, $parts, $cfg) : false;
    if (!pg_sw_tree_is_placeholder($tree)) {
        $changed = pg_sw_default_loop_tails($tree, $kind, $cfg) || $changed;
    }
    if ($due && !pg_sw_tree_is_placeholder($tree)) {
        $placed = $tree;
        if (!isset($tree['children']) || !is_array($tree['children'])) $tree['children'] = array();
        foreach ($due as $builder) {
            $tree['children'] = array_merge($tree['children'], call_user_func($builder, $cfg, $placed));
        }
        $changed = true;
    }
    if ($email) $changed = pg_sw_parts_mail_styles($tree) || $changed;
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
// the token alone (^^token^^, as older layouts wrote it). '' for none.
function pg_sw_part_token_of($node, $tokens)
{
    $props = (isset($node['props']) && is_array($node['props'])) ? $node['props'] : array();
    if (isset($props['_bindings']['text']) && in_array($props['_bindings']['text'], $tokens, true)) {
        return $props['_bindings']['text'];
    }
    if (isset($props['text']) && is_string($props['text'])
        && preg_match('/^\s*\^\^([A-Za-z0-9_]+)\^\^\s*$/', $props['text'], $m)
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
        unset($props['contentType'], $props['lead'], $props['align']);
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
