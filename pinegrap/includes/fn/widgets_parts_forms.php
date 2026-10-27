<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// A module of functions.php: the order view's forms drawn as default parts
// (widgets_parts.php): the cancel form and the return request form.
//
// Loaded by functions.php through require_once, never on its own.
//
// Every element of these forms is the designer's; what a control sends and
// whether it is required are the system's. A form element bound to the
// form's action gets its address, method and hidden fields; a control
// bound to its value gets its name, id and limits; a submit button bound
// to the form's submit stays a submit. A required control or the submit
// button deleted from the design comes back at the end of its form.
if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// Rewrites the attributes $managed names on $node to $add; the rest stay.
function pg_sw_form_set_attrs(&$node, $managed, $add)
{
    $kept = array();
    foreach ((isset($node['props']['_attrs']) && is_array($node['props']['_attrs'])) ? $node['props']['_attrs'] : array() as $a) {
        if (is_array($a) && isset($a['name']) && !isset($managed[strtolower((string)$a['name'])])) $kept[] = $a;
    }
    foreach ($add as $name => $value) $kept[] = array('name' => $name, 'value' => (string)$value);
    $node['props']['_attrs'] = $kept;
}

// The value of the attribute $name on $node, or null.
function pg_sw_form_attr($node, $name)
{
    foreach ((isset($node['props']['_attrs']) && is_array($node['props']['_attrs'])) ? $node['props']['_attrs'] : array() as $a) {
        if (is_array($a) && isset($a['name']) && strtolower((string)$a['name']) === $name) return isset($a['value']) ? (string)$a['value'] : '';
    }
    return null;
}

// Whether a node under $node carries the binding $key = $value.
function pg_sw_form_has_binding($node, $key, $value)
{
    if (!is_array($node)) return false;
    if (isset($node['props']['_bindings'][$key]) && $node['props']['_bindings'][$key] === $value) return true;
    foreach ((isset($node['children']) && is_array($node['children'])) ? $node['children'] : array() as $c) {
        if (pg_sw_form_has_binding($c, $key, $value)) return true;
    }
    return false;
}

// A submit button bound to $action: a semantic button gets type submit, a
// button component becomes a submit button.
function pg_sw_form_submit(&$node)
{
    if (!isset($node['type'])) return;
    if ($node['type'] === 'semantic' && isset($node['props']['tag']) && strtolower((string)$node['props']['tag']) === 'button') {
        pg_sw_form_set_attrs($node, array('type' => 1), array('type' => 'submit'));
    } elseif ($node['type'] === 'component' && isset($node['props']['componentType']) && $node['props']['componentType'] === 'btn') {
        $node['props']['btnElement'] = 'button';
        $node['props']['btnType']    = 'submit';
        unset($node['props']['href']);
    }
}

// Makes the semantic form $node post to $action with $hidden (markup: the
// token and the form's own fields) first inside it.
function pg_sw_form_post(&$node, $action, $hidden, $more = array())
{
    pg_sw_form_set_attrs($node, array_merge(array('action' => 1, 'method' => 1), array_fill_keys(array_keys($more), 1)),
        array_merge(array('action' => $action, 'method' => 'post'), $more));
    if (!isset($node['children']) || !is_array($node['children'])) $node['children'] = array();
    array_unshift($node['children'], array('type' => 'content',
        'props' => array('contentType' => 'custom_html', 'html' => $hidden), 'children' => array()));
}

// Gives each label among $node's children that names no control the id of
// the control beside it bound to one of $values (_bindings.value), and a
// help line (.form-text) beside it the id the control's aria-describedby
// names, when the control names one.
function pg_sw_form_label_for(&$node, $values)
{
    if (empty($node['children']) || !is_array($node['children'])) return;
    $id = '';
    $help = '';
    foreach ($node['children'] as $c) {
        $v = (is_array($c) && isset($c['props']['_bindings']['value'])) ? $c['props']['_bindings']['value'] : '';
        if ($v !== '' && in_array($v, $values, true) && !empty($c['props']['id'])) {
            $id = (string)$c['props']['id'];
            $help = (string)pg_sw_form_attr($c, 'aria-describedby');
            break;
        }
    }
    if ($id === '') return;
    foreach ($node['children'] as &$c) {
        if (!is_array($c) || !isset($c['props']['tag'])) continue;
        $tag = strtolower((string)$c['props']['tag']);
        if ($tag === 'label' && pg_sw_form_attr($c, 'for') === null) {
            $attrs = (isset($c['props']['_attrs']) && is_array($c['props']['_attrs'])) ? $c['props']['_attrs'] : array();
            $attrs[] = array('name' => 'for', 'value' => $id);
            $c['props']['_attrs'] = $attrs;
        } elseif ($help !== '' && empty($c['props']['id']) && in_array('form-text', preg_split('/\s+/', isset($c['props']['cssClass']) ? (string)$c['props']['cssClass'] : ''), true)) {
            $c['props']['id'] = $help;
            $help = '';
        }
    }
    unset($c);
}

// ── Cancel form ────────────────────────────────────────────────────────

// The cancel form's submit button, as the part draws it.
function pg_sw_part_order_cancel_submit()
{
    return pg_sw_part_el('button', 'btn btn-outline-danger', 'Cancel button', array('_bindings' => array('action' => 'order_cancel_submit')), array(
        pg_sw_part_el('i', 'bi bi-x-circle me-1', 'Icon'),
        pg_sw_part_el('span', '', 'Text', array('text' => lang('Cancel Order'))),
    ));
}

// The order's cancel form (the old __cancel_form): the refund warning
// while the order was paid through a payment provider
// (cancel_needs_refund), the optional reason and the destructive button.
// It posts to cancel_order.php and asks the visitor to confirm first
// (pg_sw_order_cancel_wire()).
function pg_sw_part_order_cancel_form($cfg = array())
{
    return array(pg_sw_part_el('form', 'pg-ov-cancel-form', 'Cancellation form', array('_bindings' => array('action' => 'order_cancel_form')), array(
        pg_sw_part_el('div', 'alert alert-warning small mb-3', 'Refund warning', array(
            '_bindings' => array('eo_visible_if' => 'cancel_needs_refund'), '_attrs' => array(array('name' => 'role', 'value' => 'alert'))), array(
            pg_sw_part_el('i', 'bi bi-exclamation-triangle me-1', 'Icon'),
            pg_sw_part_el('span', '', 'Text', array('text' => lang('Payment refunds are processed manually. Please contact us for refund status.'))),
        )),
        pg_sw_part_el('div', 'mb-3', 'Reason', array(), array(
            pg_sw_part_el('label', 'form-label small fw-semibold', 'Label', array('text' => lang('Cancellation reason (optional)'))),
            pg_sw_part_el('textarea', 'form-control', 'Reason', array('_bindings' => array('value' => 'cancel_reason'), '_attrs' => array(
                array('name' => 'rows', 'value' => '2'),
                array('name' => 'placeholder', 'value' => lang('Tell us why you\'re cancelling — helps us improve.')),
            ))),
        )),
        pg_sw_part_order_cancel_submit(),
    )));
}

// Wires the cancel forms under $node: a form bound to order_cancel_form
// posts to cancel_order.php with the CSRF token, the order and the page to
// come back to, and asks to confirm; its reason box sends
// cancellation_reason (at most 500 characters); a form without its submit
// button gets it back. $ctx: action, token, order_id, send_to.
function pg_sw_order_cancel_wire(&$node, $ctx)
{
    if (!is_array($node)) return;
    $b   = (isset($node['props']['_bindings']) && is_array($node['props']['_bindings'])) ? $node['props']['_bindings'] : array();
    $tag = isset($node['props']['tag']) ? strtolower((string)$node['props']['tag']) : '';
    $sem = isset($node['type']) && $node['type'] === 'semantic';
    $oid = (int)$ctx['order_id'];
    if ($sem && $tag === 'form' && isset($b['action']) && $b['action'] === 'order_cancel_form') {
        if (!pg_sw_form_has_binding($node, 'action', 'order_cancel_submit')) {
            if (!isset($node['children']) || !is_array($node['children'])) $node['children'] = array();
            $node['children'][] = pg_sw_part_order_cancel_submit();
        }
        pg_sw_form_post($node, $ctx['action'],
            (string)$ctx['token'] . '<input type="hidden" name="order_id" value="' . $oid . '">'
            . '<input type="hidden" name="send_to" value="' . h((string)$ctx['send_to']) . '">',
            array('data-pg-confirm-msg' => (string)lang('Are you sure you want to cancel this order? This cannot be undone.'),
                  'onsubmit' => 'return window.confirm(this.dataset.pgConfirmMsg);'));
    } elseif ($sem && $tag === 'textarea' && isset($b['value']) && $b['value'] === 'cancel_reason') {
        pg_sw_form_set_attrs($node, array('name' => 1, 'maxlength' => 1, 'id' => 1),
            array('name' => 'cancellation_reason', 'maxlength' => '500'));
        $node['props']['id'] = 'pg-ov-cancel-reason-' . $oid;
        $node['props']['text'] = '';
        $node['children'] = array();
    } elseif (isset($b['action']) && $b['action'] === 'order_cancel_submit') {
        pg_sw_form_submit($node);
    }
    if (!empty($node['children']) && is_array($node['children'])) {
        foreach ($node['children'] as &$child) pg_sw_order_cancel_wire($child, $ctx);
        unset($child);
        pg_sw_form_label_for($node, array('cancel_reason'));
    }
}

// ── Return request form ────────────────────────────────────────────────

// The quantity cell of a delivery line: the box while some may go back,
// else the words saying the rest is already in a return.
function pg_sw_part_order_return_qty_cell()
{
    return pg_sw_part_el('td', 'text-end', 'To return', array(), array(
        pg_sw_part_el('input', 'form-control form-control-sm text-end', 'Quantity', array_merge_recursive(
            array('_bindings' => array('value' => 'return_qty')), pg_sw_part_when('__return_item_left', 'gt', '0'))),
        pg_sw_part_el('span', 'small text-muted', 'Already in a return', array_merge(array('text' => lang('Already in a return')),
            pg_sw_part_when('__return_item_left', 'eq', '0'))),
    ));
}

// The reason of a return request, as the part draws it.
function pg_sw_part_order_return_reason()
{
    return pg_sw_part_el('div', 'mb-3', 'Reason', array(), array(
        pg_sw_part_el('label', 'form-label small fw-semibold', 'Label', array('text' => lang('Reason for the return'))),
        pg_sw_part_el('select', 'form-select', 'Reason', array('_bindings' => array('value' => 'return_reason'))),
    ));
}

// The return request form's submit button, as the part draws it.
function pg_sw_part_order_return_submit()
{
    return pg_sw_part_el('button', 'btn btn-outline-primary', 'Request button', array('_bindings' => array('action' => 'order_return_submit')), array(
        pg_sw_part_el('i', 'bi bi-arrow-return-left me-1', 'Icon', array('_attrs' => array(array('name' => 'aria-hidden', 'value' => 'true')))),
        pg_sw_part_el('span', '', 'Text', array('text' => lang('Request a return'))),
    ));
}

// A sentence with one value (W26): $text holds {var:1} where the span
// bound to $token stands.
function pg_sw_part_sentence($tag, $class, $name, $text, $token, $props = array())
{
    return pg_sw_part_el($tag, $class, $name, array_merge(array('text' => $text, '_sentence' => true), $props), array(
        pg_sw_part_el('span', '', 'Value', array('_bindings' => array('text' => $token))),
    ));
}

// The return request form (the old __return_form): a form per delivery a
// return may still be asked for (order.return_deliveries), the recipient
// when the order has several, the last day, the delivery's products with a
// quantity box each (order.return_delivery_lines), the reason, a note and
// the request button. Posts to order_return.php (pg_sw_order_return_form_wire()).
function pg_sw_part_order_return_form($cfg = array())
{
    $th = function ($class, $text) {
        return pg_sw_part_el('th', $class, $text, array('text' => $text, '_attrs' => array(array('name' => 'scope', 'value' => 'col'))));
    };
    return array(array('type' => 'loop_area', 'props' => array('source' => 'order.return_deliveries'), 'children' => array(
        pg_sw_part_el('form', 'pg-ov-return-form mb-4', 'Return request form', array('_bindings' => array('action' => 'order_return_form')), array(
            pg_sw_part_sentence('h6', 'fw-semibold mb-2', 'Recipient', lang('Sent to {var:1}'), '__return_delivery_label',
                pg_sw_part_when('__return_delivery_several', 'not_empty')),
            pg_sw_part_sentence('p', 'small text-muted mb-2', 'Last day', lang('You can ask to return these items until {var:1}.'), '__return_delivery_deadline',
                pg_sw_part_when('__return_delivery_deadline', 'not_empty')),
            pg_sw_part_el('div', 'table-responsive', 'Products', array(), array(
                pg_sw_part_el('table', 'table table-sm align-middle mb-3', 'Table', array(), array(
                    pg_sw_part_el('thead', '', 'Head', array(), array(
                        pg_sw_part_el('tr', '', 'Row', array(), array(
                            $th('', lang('Product')), $th('text-end', lang('Ordered')), $th('text-end', lang('To return')),
                        )),
                    )),
                    pg_sw_part_el('tbody', '', 'Body', array(), array(
                        array('type' => 'loop_area', 'props' => array('source' => 'order.return_delivery_lines'), 'children' => array(
                            pg_sw_part_el('tr', '', 'Product', array(), array(
                                pg_sw_part_el('td', '', 'Name', array('_bindings' => array('text' => '__return_item_name'))),
                                pg_sw_part_el('td', 'text-end', 'Ordered', array('_bindings' => array('text' => '__return_item_ordered'))),
                                pg_sw_part_order_return_qty_cell(),
                            )),
                        )),
                    )),
                )),
            )),
            pg_sw_part_order_return_reason(),
            pg_sw_part_el('div', 'mb-3', 'Note', array(), array(
                pg_sw_part_el('label', 'form-label small fw-semibold', 'Label', array('text' => lang('Note (optional)'))),
                pg_sw_part_el('textarea', 'form-control', 'Note', array('_bindings' => array('value' => 'return_note'),
                    '_attrs' => array(array('name' => 'rows', 'value' => '2')))),
                pg_sw_part_el('div', 'form-text', 'Help', array('text' => lang('Needed when the reason is "Another reason".'))),
            )),
            pg_sw_part_order_return_submit(),
        )),
    )));
}

// Wires the return request forms under $node: a form bound to
// order_return_form posts a request to order_return.php with the CSRF
// token, the order, its delivery (the order.return_deliveries row) and the
// page to come back to; its quantity boxes send qty[<item>] within what
// may go back, the reason is required with the shop's reasons, the note
// sends at most PG_RETURN_NOTE_MAX characters. A form without its reason
// or its button gets them back, a delivery line without its quantity box
// gets the cell back. $ctx: action, token, order_id, send_to.
function pg_sw_order_return_form_wire(&$node, $ctx)
{
    if (!is_array($node)) return;
    $b   = (isset($node['props']['_bindings']) && is_array($node['props']['_bindings'])) ? $node['props']['_bindings'] : array();
    $tag = isset($node['props']['tag']) ? strtolower((string)$node['props']['tag']) : '';
    $sem = isset($node['type']) && $node['type'] === 'semantic';
    $value = isset($b['value']) ? $b['value'] : '';
    if ($sem && $tag === 'form' && isset($b['action']) && $b['action'] === 'order_return_form') {
        if (!isset($node['children']) || !is_array($node['children'])) $node['children'] = array();
        if (!pg_sw_form_has_binding($node, 'value', 'return_reason')) $node['children'][] = pg_sw_part_order_return_reason();
        if (!pg_sw_form_has_binding($node, 'action', 'order_return_submit')) $node['children'][] = pg_sw_part_order_return_submit();
        pg_sw_form_post($node, $ctx['action'],
            (string)$ctx['token'] . '<input type="hidden" name="action" value="request">'
            . '<input type="hidden" name="order_id" value="' . (int)$ctx['order_id'] . '">'
            . '<input type="hidden" name="ship_to_id" value="^^__return_delivery_id^^">'
            . '<input type="hidden" name="send_to" value="' . h((string)$ctx['send_to']) . '">');
    } elseif (pg_sw_list_is_sourced($node) && $node['props']['source'] === 'order.return_delivery_lines') {
        if (!pg_sw_form_has_binding($node, 'value', 'return_qty') && isset($node['children'][0]['type']) && $node['children'][0]['type'] === 'semantic') {
            if (!isset($node['children'][0]['children']) || !is_array($node['children'][0]['children'])) $node['children'][0]['children'] = array();
            $node['children'][0]['children'][] = pg_sw_part_order_return_qty_cell();
        }
    } elseif ($sem && $tag === 'input' && $value === 'return_qty') {
        $add = array('type' => 'number', 'name' => 'qty[^^__return_item_id^^]', 'min' => '0', 'max' => '^^__return_item_left^^',
                     'step' => '1', 'value' => '0', 'inputmode' => 'numeric');
        if (pg_sw_form_attr($node, 'aria-label') === null) $add['aria-label'] = '^^__return_item_qty_label^^';
        pg_sw_form_set_attrs($node, array_fill_keys(array('type', 'name', 'min', 'max', 'step', 'value', 'inputmode', 'id'), 1), $add);
        $node['props']['id'] = 'pg-ov-return-qty-^^__return_item_id^^';
    } elseif ($sem && $tag === 'select' && $value === 'return_reason') {
        pg_sw_form_set_attrs($node, array('name' => 1, 'required' => 1, 'id' => 1), array('name' => 'reason', 'required' => ''));
        $node['props']['id'] = 'pg-ov-return-reason-^^__return_delivery_id^^';
        $node['props']['text'] = '^^__return_reason_options_html^^';
        $node['children'] = array();
    } elseif ($sem && $tag === 'textarea' && $value === 'return_note') {
        pg_sw_form_set_attrs($node, array('name' => 1, 'maxlength' => 1, 'id' => 1, 'aria-describedby' => 1),
            array('name' => 'note', 'maxlength' => (string)PG_RETURN_NOTE_MAX, 'aria-describedby' => 'pg-ov-return-note-help-^^__return_delivery_id^^'));
        $node['props']['id'] = 'pg-ov-return-note-^^__return_delivery_id^^';
        $node['props']['text'] = '';
        $node['children'] = array();
    } elseif (isset($b['action']) && $b['action'] === 'order_return_submit') {
        pg_sw_form_submit($node);
    }
    if (!empty($node['children']) && is_array($node['children'])) {
        foreach ($node['children'] as &$child) pg_sw_order_return_form_wire($child, $ctx);
        unset($child);
        pg_sw_form_label_for($node, array('return_reason', 'return_note'));
    }
}
