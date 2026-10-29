<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// A module of functions.php: the order view's and the my account widget's
// lists (widgets_lists.php), so a designer draws an order's parts and a
// member's orders from elements of their own instead of server-built
// blocks:
//
//   order.shipments  the order's recipients (ship_tos), one row each;
//   order.items      the order's items, or one recipient's;
//   order.parcels    the tracking numbers, or one recipient's;
//   order.steps      the order's steps (created, paid, shipped, delivered,
//                    cancelled), what the order timeline part draws;
//   order.item_forms       an item's product form blocks (one per quantity
//                          or one), keyed by ^^__item_id^^;
//   order.item_form_fields a form block's fields and answers, keyed by
//                          ^^__form_key^^;
//   order.item_gift_cards  an item's gift cards, keyed by ^^__item_id^^:
//                          what the item form and gift card parts draw;
//   order.returns    the order's return requests, what the returns part
//                    draws;
//   order.return_lines     a return's products, keyed by ^^__return_id^^;
//   order.return_deliveries the deliveries a return may still be asked
//                    for, one request form each (the return form part);
//   order.return_delivery_lines a delivery's products with what may go
//                    back, keyed by ^^__return_delivery_id^^;
//   account.orders   the signed-in member's orders, what the my account
//                    widget's order history section draws.
//
// The order lists read ^^__ship_to_key^^: 0 outside a shipments list (the
// static value: the whole order), the recipient's id inside one, so an items
// or parcels list there lists that recipient's. Shipments and steps have
// rows under 0 only. The item lists read the item they stand in (the item
// loop's row, or an items list's). The account list reads ^^account_id^^.
//
// The row tokens carry the names the widgets already fill (an item row the
// main loop's ^^__item_name^^, a recipient row ^^__shipping_name^^, a parcel
// ^^__tracking_code^^): inside a list a token is the row's. The renderers
// publish the rows (_render_system_widget_order_view(),
// _render_system_widget_my_account()), the order receipt e-mail included.
//
// Loaded by functions.php through require_once, never on its own.

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// The order and account sources, in pg_sw_list_sources()'s shape.
function pg_sw_list_order_sources()
{
    return array(
        'order.shipments' => array(
            'family'       => 'order',
            'key'          => '__ship_to_key',
            'label'        => lang('Recipients of the order'),
            'arg'          => '',
            'arg_required' => false,
            'tokens'       => array_merge(array(
                array('__ship_to_key', lang('Recipient in the order')),
                array('__shipping_name', lang('Recipient Full Name')),
                array('__shipping_first_name', lang('Recipient First Name')),
                array('__shipping_last_name', lang('Recipient Last Name')),
                array('__shipping_company', lang('Company')),
                array('__shipping_phone', lang('Phone')),
                array('__shipping_address_1', lang('Address line 1')),
                array('__shipping_address_2', lang('Address line 2')),
                array('__shipping_city', lang('City')),
                array('__shipping_state', lang('State / Province')),
                array('__shipping_zip', lang('Zip Code')),
                array('__shipping_country', lang('Country')),
                array('__shipping_full_address', lang('Full address (single line)')),
                array('__shipping_method', lang('Shipping method / carrier')),
                array('__arrival_date', lang('Estimated delivery date')),
                array('__ship_to_item_count', lang('Number of items')),
            ), pg_sw_list_order_entity_tokens('ship_to')),
        ),
        'order.items' => array(
            'family'       => 'order',
            'key'          => '__ship_to_key',
            'label'        => lang('Items of the order'),
            'arg'          => '',
            'arg_required' => false,
            'tokens'       => array_merge(array(
                array('__item_id', lang('Row ID (order_items.id)')),
                array('__item_name', lang('Product name (products.name)')),
                array('__item_number', lang('Stock code (SKU)')),
                array('__item_short_description', lang('Short description (display name)')),
                array('__item_summary', lang('Description summary (one line, plain text)')),
                array('__item_qty', lang('Quantity')),
                array('__item_price', lang('Unit price')),
                array('__item_total', lang('Row total')),
                array('__item_image', lang('Image URL')),
                array('__item_image_alt', lang('Image alt text')),
                array('__item_url', lang('Product detail page URL')),
                array('__item_ship_to', lang('This item\'s shipping address')),
                array('__item_has_form_data', lang('Has product form answers (1 or empty)')),
                array('__item_has_gift_card', lang('Has gift cards (1 or empty)')),
            ), pg_sw_list_order_entity_tokens('order_line'), pg_sw_list_order_entity_tokens('product')),
        ),
        'order.parcels' => array(
            'family'       => 'order',
            'key'          => '__ship_to_key',
            'label'        => lang('Parcels of the order'),
            'arg'          => '',
            'arg_required' => false,
            'tokens'       => array(
                array('__tracking_code', lang('Shipment tracking number')),
                array('__tracking_link', lang('Shipment tracking link (carrier deep link)')),
                array('__tracking_company_name', lang('Carrier name')),
            ),
        ),
        'order.steps' => array(
            'family'       => 'order',
            'key'          => '__ship_to_key',
            'label'        => lang('Steps of the order'),
            'arg'          => '',
            'arg_required' => false,
            'tokens'       => array(
                array('__step_label', lang('Step')),
                array('__step_date', lang('Date')),
                array('__step_kind', lang('Kind of step (created, paid, shipped, delivered, cancelled)')),
                array('__step_note', lang('Note (the reason of a cancellation)')),
                array('__step_is_estimate', lang('Estimated (1 or empty)')),
            ),
        ),
        'order.item_forms' => array(
            'family'       => 'order',
            'key'          => '__item_id',
            'label'        => lang('Product forms of the item'),
            'arg'          => '',
            'arg_required' => false,
            'tokens'       => array(
                array('__form_key', lang('Form in the order')),
                array('__form_name', lang('Form name')),
                array('__form_number', lang('Number of the form')),
                array('__form_count', lang('Number of forms')),
                array('__form_has_legend', lang('Has a heading (1 or empty)')),
            ),
        ),
        'order.item_form_fields' => array(
            'family'       => 'order',
            'key'          => '__form_key',
            'label'        => lang('Fields of the product form'),
            'arg'          => '',
            'arg_required' => false,
            'tokens'       => array(
                array('__field_label', lang('Label')),
                array('__field_value_html', lang('Answer (HTML)')),
                array('__field_info_html', lang('Information (HTML)')),
                array('__field_is_info', lang('Information field (1 or empty)')),
            ),
        ),
        'order.item_gift_cards' => array(
            'family'       => 'order',
            'key'          => '__item_id',
            'label'        => lang('Gift cards of the item'),
            'arg'          => '',
            'arg_required' => false,
            'tokens'       => array(
                array('__gift_card_number', lang('Number of the gift card')),
                array('__gift_card_count', lang('Number of gift cards')),
                array('__gift_card_amount', lang('Amount')),
                array('__gift_card_email', lang('Recipient Email')),
                array('__gift_card_from_name', lang('Your Name')),
                array('__gift_card_message_html', lang('Message (HTML)')),
                array('__gift_card_delivery', lang('Delivery Date')),
            ),
        ),
        'order.returns' => array(
            'family'       => 'order',
            'key'          => '__ship_to_key',
            'label'        => lang('Return requests of the order'),
            'arg'          => '',
            'arg_required' => false,
            'tokens'       => array(
                array('__return_id', lang('Return request ID')),
                array('__return_title', lang('Heading (Return request #12)')),
                array('__return_asked', lang('Date asked (Asked on …)')),
                array('__return_status', lang('Status code (requested, approved, rejected, shipped, received, refunded, closed)')),
                array('__return_status_label', lang('Status')),
                array('__return_has_lines', lang('Has products (1 or empty)')),
                array('__return_reason', lang('Reason')),
                array('__return_customer_note', lang('Your note')),
                array('__return_customer_note_html', lang('Your note (HTML)')),
                array('__return_decision_note', lang('Note from the shop')),
                array('__return_decision_note_html', lang('Note from the shop (HTML)')),
                array('__return_how', lang('How to send the goods back')),
                array('__return_code', lang('Return code')),
                array('__return_tracking_number', lang('Tracking number')),
                array('__return_refund_deadline', lang('Refund due by')),
                array('__return_actions_html', lang('The customer\'s moves: sent, withdraw (HTML)')),
                array('__return_can_mark_sent', lang('The customer may say the parcel is sent (1 or empty)')),
                array('__return_can_withdraw', lang('The customer may withdraw the request (1 or empty)')),
                array('__return_label_url', lang('Return label download URL')),
                array('__return_has_label', lang('Has a return label to download (1 or empty)')),
            ),
        ),
        'order.return_lines' => array(
            'family'       => 'order',
            'key'          => '__return_id',
            'label'        => lang('Products of the return request'),
            'arg'          => '',
            'arg_required' => false,
            'tokens'       => array(
                array('__return_line_name', lang('Product name')),
                array('__return_line_qty', lang('Quantity')),
            ),
        ),
        // The return request form part (pg_sw_part_order_return_form()):
        // one form per delivery a return may still be asked for, whose
        // controls are the system's (pg_sw_order_return_form_wire()).
        'order.return_deliveries' => array(
            'family'       => 'order',
            'key'          => '__ship_to_key',
            'label'        => lang('Deliveries a return may be asked for'),
            'arg'          => '',
            'arg_required' => false,
            'tokens'       => array(
                array('__return_delivery_id', lang('Recipient ID')),
                array('__return_delivery_label', lang('Recipient')),
                array('__return_delivery_several', lang('The order has several deliveries (1 or empty)')),
                array('__return_delivery_deadline', lang('Return requests accepted until')),
                array('__return_reason_options_html', lang('Choices of the reason (HTML)')),
            ),
        ),
        'order.return_delivery_lines' => array(
            'family'       => 'order',
            'key'          => '__return_delivery_id',
            'label'        => lang('Products of the delivery'),
            'arg'          => '',
            'arg_required' => false,
            'tokens'       => array(
                array('__return_item_id', lang('Order item ID')),
                array('__return_item_name', lang('Product name')),
                array('__return_item_ordered', lang('Ordered')),
                array('__return_item_left', lang('How many may go back')),
                array('__return_item_qty_label', lang('Label of the quantity box')),
            ),
        ),
        'account.orders' => array(
            'family'       => 'account',
            'key'          => 'account_id',
            'label'        => lang('Orders of the member'),
            'arg'          => '',
            'arg_required' => false,
            'tokens'       => array(
                array('__order_id', lang('Order ID (numeric)')),
                array('__order_no', lang('Order number')),
                array('__order_date', lang('Order date')),
                array('__order_total', lang('Grand total')),
                array('__order_status', lang('Status (raw text)')),
                array('__order_is_cancelled', lang('Cancelled (1 or empty)')),
                array('__order_url', lang('Order detail URL')),
            ),
        ),
    );
}

// "zip city state" of an address row, a state named like its city written
// once, as the order view writes it.
function _pg_sw_list_order_city_line($zip, $city, $state)
{
    $state = (mb_strtolower(trim((string)$state)) === mb_strtolower(trim((string)$city))) ? '' : trim((string)$state);
    return implode(' ', array_filter(array(trim((string)$zip), trim((string)$city), $state)));
}

// The allow-listed values (pg_sw_entity_fields($entity)) a row of the
// order's lists carries beside its own tokens, as array(token, label), a
// yes/no value with 'cond' after (a condition reads it, never a text
// binding): the twin of the editor's SW_*_ENTITY_TOKENS list.
function pg_sw_list_order_entity_tokens($entity)
{
    $labels = array(
        'ship_to' => array(
            '__ship_to_name'            => lang('Ship-to name'),
            '__shipping_salutation'     => lang('Salutation (Mr/Ms)'),
            '__shipping_address_type'   => lang('Address type (Residential address, Business address)'),
            '__shipping_ship_date'      => lang('Ship date (shipped or planned)'),
            '__shipping_delivered_date' => lang('Delivered date'),
        ),
        'order_line' => array(
            '__item_shipped_qty'         => lang('Quantity shipped (when the shop shows it)'),
            '__item_vat'                 => lang('Line VAT (with symbol)'),
            '__item_payment_period'      => lang('Subscription payment period'),
            '__item_payment_count'       => lang('Subscription number of payments'),
            '__item_first_payment_date'  => lang('First payment date (subscriptions)'),
            '__item_event_name'          => lang('Reserved event name'),
            '__item_event_date'          => lang('Reserved event date and time'),
            '__item_added_by_offer'      => lang('Added by an offer'),
            '__item_discounted_by_offer' => lang('Discounted by an offer'),
        ),
        'product' => array(
            '__keywords'            => lang('Keywords'),
            '__length'              => lang('Length'),
            '__width'               => lang('Width'),
            '__height'              => lang('Height'),
            '__minimum_quantity'    => lang('Minimum order quantity'),
            '__maximum_quantity'    => lang('Maximum order quantity'),
            '__preparation_days'    => lang('Preparation time (days)'),
            '__extra_shipping_cost' => lang('Extra shipping cost (with symbol)'),
            '__vat_rate'            => lang('VAT rate (number, without the % sign)'),
            '__payment_period'      => lang('Subscription payment period'),
            '__number_of_payments'  => lang('Subscription number of payments'),
            '__is_free_shipping'    => lang('Ships free'),
            '__is_gift_card'        => lang('Is a gift card'),
            '__is_donation'         => lang('Is a donation'),
        ),
    );

    $out = array();
    foreach (pg_sw_entity_fields($entity) as $token => $spec) {
        $row = array($token, isset($labels[$entity][$token]) ? (string)$labels[$entity][$token] : $token);
        if ($spec[1] === 'yesno') $row[] = 'cond';
        $out[] = $row;
    }

    return $out;
}

// One recipient's row from its ship_tos row (first_name, last_name,
// company, phone_number, address_1, address_2, city, state, zip_code,
// country, arrival_date, shipping_method: the order view's shipping
// columns) as the order view's shipping tokens print them. $arrival
// formats a Y-m-d arrival date, and the shipped and delivered dates of its
// allow-listed values (pg_sw_entity_fields('ship_to')), which ride along.
// The item count is set by pg_sw_list_order_rows().
function pg_sw_list_order_shipment_row($st, $arrival)
{
    $g = function ($k) use ($st) { return isset($st[$k]) ? (string)$st[$k] : ''; };
    $name = implode(' ', array_filter(array(trim($g('first_name')), trim($g('last_name')))));
    $full = implode(', ', array_filter(array(
        $name, $g('company'), $g('address_1'), $g('address_2'),
        _pg_sw_list_order_city_line($g('zip_code'), $g('city'), $g('state')), $g('country'),
    )));
    $date = $g('arrival_date');
    return array(
        '__ship_to_key'           => (string)(isset($st['id']) ? (int)$st['id'] : 0),
        '__shipping_name'         => $name,
        '__shipping_first_name'   => $g('first_name'),
        '__shipping_last_name'    => $g('last_name'),
        '__shipping_company'      => $g('company'),
        '__shipping_phone'        => $g('phone_number'),
        '__shipping_address_1'    => $g('address_1'),
        '__shipping_address_2'    => $g('address_2'),
        '__shipping_city'         => $g('city'),
        '__shipping_state'        => $g('state'),
        '__shipping_zip'          => $g('zip_code'),
        '__shipping_country'      => $g('country'),
        '__shipping_full_address' => $full,
        '__shipping_method'       => $g('shipping_method'),
        '__arrival_date'          => ($date !== '' && $date !== '0000-00-00' && is_callable($arrival)) ? (string)$arrival($date) : '',
        '__ship_to_item_count'    => '0',
    ) + pg_sw_entity_values('ship_to', $st, array(
        'format_stamp' => function ($ts) use ($arrival) { return is_callable($arrival) ? (string)$arrival(date('Y-m-d', $ts)) : date('Y-m-d', $ts); },
    ));
}

// One item's row from the order view's item (its $items entry), raw: the
// pass escapes it once.
// Its allow-listed line and product values ride along, those the order
// view read ('entity'), the rest empty: they are the list's own tokens.
function pg_sw_list_order_item_row($item)
{
    $g = function ($k) use ($item) { return isset($item[$k]) ? (string)$item[$k] : ''; };
    $entity = array();
    foreach (array_merge(pg_sw_entity_fields('order_line'), pg_sw_entity_fields('product')) as $token => $spec) {
        $entity[$token] = isset($item['entity'][$token]) ? (string)$item['entity'][$token] : '';
    }
    return array(
        '__item_id'                => (string)(int)$g('id'),
        '__item_name'              => $g('name'),
        '__item_number'            => $g('item_number'),
        '__item_short_description' => $g('short_description'),
        '__item_summary'           => pg_sw_item_summary($g('full_description')),
        '__item_qty'               => (string)(int)$g('qty'),
        '__item_price'             => $g('unit_price'),
        '__item_total'             => $g('line_total'),
        '__item_image'             => $g('image'),
        '__item_image_alt'         => ($g('short_description') !== '') ? $g('short_description') : $g('name'),
        '__item_url'               => $g('url'),
        '__item_ship_to'           => $g('ship_to'),
        '__item_has_form_data'     => !empty($item['has_form_data']) ? '1' : '',
        '__item_has_gift_card'     => !empty($item['has_gift_card']) ? '1' : '',
    ) + $entity;
}

// The order view's parcels from its shipping_tracking_numbers rows (number,
// ship_to_id, shipping_method_code, and since 11.0.20 carrier and
// tracking_url): array('number', 'url', 'carrier', 'ship_to') each, the
// carrier as its name. Every row goes through pg_shipment_view(), so the
// carrier and the link the row names win, and a row without them is
// recognised from the method code, $default and the number's shape, as the
// order screen, the e-mails and the SMS show it. A row without a number is
// no parcel.
function pg_sw_order_parcels($rows, $default)
{
    $parcels = array();
    foreach ((array)$rows as $row) {
        if (!is_array($row)) continue;
        $view = pg_shipment_view($row, isset($row['shipping_method_code']) ? trim((string)$row['shipping_method_code']) : '', (string)$default);
        if ($view['number'] === '') continue;
        $parcels[] = array(
            'number'  => $view['number'],
            'url'     => $view['url'],
            'carrier' => $view['carrier_name'],
            'ship_to' => isset($row['ship_to_id']) ? (int)$row['ship_to_id'] : 0,
        );
    }
    return $parcels;
}

// The rows of the order lists, as pg_sw_list_publish('order') files them:
// source => key => rows. $shipments: shipment rows
// (pg_sw_list_order_shipment_row()); $items: the order view's items, each
// with its 'ship_to_id'; $parcels: array('number', 'url', 'carrier',
// 'ship_to') each; $steps: the timeline's events, each with 'kind',
// 'step_label', 'ts', 'note' and 'estimate'. Key 0 holds the whole order, a
// recipient's id its items and parcels; a recipient's item count is
// counted here.
function pg_sw_list_order_rows($shipments, $items, $parcels, $steps)
{
    $out = array('order.shipments' => array(), 'order.items' => array(), 'order.parcels' => array(), 'order.steps' => array());
    foreach ((array)$items as $item) {
        if (!is_array($item)) continue;
        $row = pg_sw_list_order_item_row($item);
        $out['order.items'][0][] = $row;
        $st = isset($item['ship_to_id']) ? (int)$item['ship_to_id'] : 0;
        if ($st > 0) $out['order.items'][$st][] = $row;
    }
    foreach ((array)$shipments as $row) {
        if (!is_array($row)) continue;
        $st = (int)$row['__ship_to_key'];
        $row['__ship_to_item_count'] = (string)(isset($out['order.items'][$st]) ? count($out['order.items'][$st]) : 0);
        $out['order.shipments'][0][] = $row;
    }
    foreach ((array)$parcels as $p) {
        if (!is_array($p)) continue;
        $row = array(
            '__tracking_code'         => isset($p['number']) ? (string)$p['number'] : '',
            '__tracking_link'         => isset($p['url']) ? (string)$p['url'] : '',
            '__tracking_company_name' => isset($p['carrier']) ? (string)$p['carrier'] : '',
        );
        $out['order.parcels'][0][] = $row;
        $st = isset($p['ship_to']) ? (int)$p['ship_to'] : 0;
        if ($st > 0) $out['order.parcels'][$st][] = $row;
    }
    foreach ((array)$steps as $e) {
        if (!is_array($e) || empty($e['ts'])) continue;
        $out['order.steps'][0][] = array(
            '__step_label'       => isset($e['step_label']) ? (string)$e['step_label'] : (isset($e['label']) ? (string)$e['label'] : ''),
            '__step_date'        => (string)$e['ts'],
            '__step_kind'        => isset($e['kind']) ? (string)$e['kind'] : '',
            '__step_note'        => isset($e['note']) ? (string)$e['note'] : '',
            '__step_is_estimate' => !empty($e['estimate']) ? '1' : '',
        );
    }
    return $out;
}

// The rows of the account list, as pg_sw_list_publish('account') files
// them: array('account.orders' => array($account_id => rows)). $orders: the
// rows the order history reads (id, order_number, order_date as a unix
// time, total in major units, status), newest first; $view_url: the view
// order page ('' for none: the row links nowhere, '#'). The total is what
// the order history prints, as plain text.
function pg_sw_list_account_rows($account_id, $orders, $view_url, $date_format)
{
    $rows = array();
    foreach ((array)$orders as $o) {
        if (!is_array($o)) continue;
        $id = isset($o['id']) ? (int)$o['id'] : 0;
        $rows[] = array(
            '__order_id'           => (string)$id,
            '__order_no'           => isset($o['order_number']) ? (string)$o['order_number'] : '',
            '__order_date'         => _pg_sw_short_date(isset($o['order_date']) ? (int)$o['order_date'] : 0, $date_format),
            '__order_total'        => html_entity_decode(prepare_amount(isset($o['total']) ? $o['total'] : 0), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            '__order_status'       => isset($o['status']) ? (string)$o['status'] : '',
            '__order_is_cancelled' => (isset($o['status']) && $o['status'] === 'cancelled') ? '1' : '',
            '__order_url'          => ((string)$view_url !== '') ? $view_url . '?id=' . $id : '#',
        );
    }
    return array('account.orders' => array((int)$account_id => $rows));
}

// The rows of an item's product form lists: array('order.item_forms' =>
// array(item id => rows), 'order.item_form_fields' => array(form key =>
// rows)). $item: the order view's item (id, qty, form_name translated,
// form_quantity_type); $fields: the product's form_fields rows (id, label,
// type, information, wysiwyg) in their order; $data: the item's form_data
// rows (form_field_id, data, quantity_number). An item with no saved
// answer has no form. 'One Form per Quantity' gives a block per unit (at
// most 100), numbered when there is more than one; a heading shows when
// the form has a name or a number. A block's key is the item id times
// 1000 plus its number. Answers as the order pages print them: several
// values of one field joined with ", ", rich text filtered, the rest
// prepared for HTML; an information field gives its label and text (an
// empty one nothing).
function pg_sw_list_order_item_form_rows($item, $fields, $data)
{
    $out = array('order.item_forms' => array(), 'order.item_form_fields' => array());
    $id  = isset($item['id']) ? (int)$item['id'] : 0;
    if ($id <= 0 || !$fields || !$data) return $out;

    $by_q = array();
    foreach ((array)$data as $r) {
        $by_q[max(1, (int)$r['quantity_number'])][(int)$r['form_field_id']][] = (string)$r['data'];
    }
    $per_qty = isset($item['form_quantity_type']) && $item['form_quantity_type'] === 'One Form per Quantity';
    $count   = $per_qty ? min(100, max(1, isset($item['qty']) ? (int)$item['qty'] : 1)) : 1;
    $name    = isset($item['form_name']) ? (string)$item['form_name'] : '';

    for ($q = 1; $q <= $count; $q++) {
        $key = (string)($id * 1000 + $q);
        $out['order.item_forms'][$id][] = array(
            '__form_key'        => $key,
            '__form_name'       => $name,
            '__form_number'     => (string)$q,
            '__form_count'      => (string)$count,
            '__form_has_legend' => ($name !== '' || $count > 1) ? '1' : '',
        );
        $vals = isset($by_q[$q]) ? $by_q[$q] : array();
        $rows = array();
        foreach ((array)$fields as $f) {
            $type  = isset($f['type']) ? (string)$f['type'] : '';
            $label = isset($f['label']) ? (string)$f['label'] : '';
            if ($type === 'information') {
                $info = isset($f['information']) ? (string)$f['information'] : '';
                if ($label === '' && $info === '') continue;
                $rows[] = array('__field_label' => $label, '__field_value_html' => '', '__field_info_html' => $info, '__field_is_info' => '1');
                continue;
            }
            $fid   = isset($f['id']) ? (int)$f['id'] : 0;
            $value = isset($vals[$fid]) ? implode(', ', $vals[$fid]) : '';
            // Rich text holds the shopper's markup: filtered, not escaped.
            $html = !empty($f['wysiwyg'])
                ? prepare_form_data_for_output(pg_defuse_php_tags(pg_sanitize_rich_text($value)), $type, false)
                : prepare_form_data_for_output($value, $type, true);
            $rows[] = array('__field_label' => $label, '__field_value_html' => (string)$html, '__field_info_html' => '', '__field_is_info' => '');
        }
        if ($rows) $out['order.item_form_fields'][$key] = $rows;
    }
    return $out;
}

// The rows of an item's gift card list: array('order.item_gift_cards' =>
// array(item id => rows)). $item: the order view's item (id, qty);
// $cards: its order_item_gift_cards rows (quantity_number, from_name,
// recipient_email_address, message, delivery_date); $amount: one card's
// amount as the order view prints it; $fmt_date formats a Y-m-d date. One
// row per card filled in, numbered among the item's quantity (at most
// 100); a card without a delivery date is delivered at once ("Immediate").
function pg_sw_list_order_item_gift_card_rows($item, $cards, $amount, $fmt_date)
{
    $id  = isset($item['id']) ? (int)$item['id'] : 0;
    $qty = isset($item['qty']) ? (int)$item['qty'] : 0;
    if ($id <= 0 || $qty <= 0 || !$cards) return array('order.item_gift_cards' => array());

    $by_q = array();
    foreach ((array)$cards as $c) $by_q[max(1, (int)$c['quantity_number'])] = $c;
    $count = min(100, $qty);
    $rows  = array();
    for ($q = 1; $q <= $count; $q++) {
        if (!isset($by_q[$q])) continue;
        $c    = $by_q[$q];
        $date = isset($c['delivery_date']) ? (string)$c['delivery_date'] : '';
        $rows[] = array(
            '__gift_card_number'       => (string)$q,
            '__gift_card_count'        => (string)$count,
            '__gift_card_amount'       => (string)$amount,
            '__gift_card_email'        => isset($c['recipient_email_address']) ? (string)$c['recipient_email_address'] : '',
            '__gift_card_from_name'    => isset($c['from_name']) ? (string)$c['from_name'] : '',
            '__gift_card_message_html' => nl2br(h(isset($c['message']) ? (string)$c['message'] : '')),
            '__gift_card_delivery'     => ($date === '' || $date === '0000-00-00') ? lang('Immediate') : (string)call_user_func($fmt_date, $date),
        );
    }
    return array('order.item_gift_cards' => $rows ? array($id => $rows) : array());
}

// Reads the product form answers and gift cards of the order view's
// $items when the layout lists them ($wanted: pg_sw_list_wanted('order')),
// one query each, and returns pg_sw_list_order_item_parts_rows().
function pg_sw_list_order_item_parts_read(&$items, $wanted, $fmt_money, $fmt_date)
{
    $forms = isset($wanted['order.item_forms']) || isset($wanted['order.item_form_fields']);
    $cards = isset($wanted['order.item_gift_cards']);
    $form_items = array();
    $card_items = array();
    $products   = array();
    foreach ((array)$items as $item) {
        if ($forms && !empty($item['has_form']) && (int)$item['product_id'] > 0) {
            $form_items[(int)$item['id']] = true;
            $products[(int)$item['product_id']] = true;
        }
        if ($cards && !empty($item['is_gift_card'])) $card_items[(int)$item['id']] = true;
    }

    $fields = array();
    $data   = array();
    if ($form_items) {
        foreach ((array)db_items(
            "SELECT id, product_id, label, type, information, wysiwyg
             FROM form_fields
             WHERE product_id IN (" . implode(',', array_keys($products)) . ") AND form_type = 'product'
             ORDER BY sort_order ASC, id ASC"
        ) as $f) {
            $fields[(int)$f['product_id']][] = $f;
        }
        foreach ((array)db_items(
            "SELECT order_item_id, form_field_id, data, quantity_number
             FROM form_data
             WHERE order_item_id IN (" . implode(',', array_keys($form_items)) . ")
             ORDER BY quantity_number ASC, id ASC"
        ) as $d) {
            $data[(int)$d['order_item_id']][] = $d;
        }
    }
    $gift_cards = array();
    if ($card_items) {
        foreach ((array)db_items(
            "SELECT order_item_id, quantity_number, from_name, recipient_email_address, message, delivery_date
             FROM order_item_gift_cards
             WHERE order_item_id IN (" . implode(',', array_keys($card_items)) . ")
             ORDER BY quantity_number ASC"
        ) as $c) {
            $gift_cards[(int)$c['order_item_id']][] = $c;
        }
    }
    return pg_sw_list_order_item_parts_rows($items, $forms ? $fields : array(), $data, $cards ? $gift_cards : array(), $fmt_money, $fmt_date);
}

// The rows of the three item lists for the order view's $items:
// $fields: product id => form_fields rows; $data: item id => form_data
// rows; $gift_cards: item id => order_item_gift_cards rows. Marks the
// items that have rows (has_form_data, has_gift_card). A form's name is
// translated here; a card's amount is the item's unit price ($fmt_money
// formats minor units), $fmt_date formats a delivery date.
function pg_sw_list_order_item_parts_rows(&$items, $fields, $data, $gift_cards, $fmt_money, $fmt_date)
{
    $out = array('order.item_forms' => array(), 'order.item_form_fields' => array(), 'order.item_gift_cards' => array());
    if (!is_array($items)) return $out;
    foreach ($items as $i => $item) {
        $id  = isset($item['id']) ? (int)$item['id'] : 0;
        $pid = isset($item['product_id']) ? (int)$item['product_id'] : 0;
        if (!empty($item['has_form']) && !empty($fields[$pid]) && !empty($data[$id])) {
            $name = isset($item['form_name']) ? (string)$item['form_name'] : '';
            $item['form_name'] = ($name !== '') ? (string)lang($name) : '';
            $rows = pg_sw_list_order_item_form_rows($item, $fields[$pid], $data[$id]);
            if ($rows['order.item_forms']) {
                $items[$i]['has_form_data'] = true;
                $out['order.item_forms'] += $rows['order.item_forms'];
                $out['order.item_form_fields'] += $rows['order.item_form_fields'];
            }
        }
        if (!empty($item['is_gift_card']) && !empty($gift_cards[$id])) {
            $amount = call_user_func($fmt_money, isset($item['unit_cents']) ? (int)$item['unit_cents'] : 0);
            $rows = pg_sw_list_order_item_gift_card_rows($item, $gift_cards[$id], $amount, $fmt_date);
            if ($rows['order.item_gift_cards']) {
                $items[$i]['has_gift_card'] = true;
                $out['order.item_gift_cards'] += $rows['order.item_gift_cards'];
            }
        }
    }
    return $out;
}

// The rows of the return form lists: array('order.return_deliveries' =>
// array(0 => rows), 'order.return_delivery_lines' => array(delivery id =>
// rows)). $deliveries: pg_return_customer_view()'s deliveries (ship_to_id,
// label, deadline, lines: id, name, quantity, returnable); a delivery with
// nothing left to give back gets no form, as pg_return_form_html() skips
// it. Raw words: the pass escapes them once, the reasons are markup.
function pg_sw_list_order_return_form_rows($deliveries)
{
    $out = array('order.return_deliveries' => array(), 'order.return_delivery_lines' => array());
    $deliveries = is_array($deliveries) ? $deliveries : array();
    $options = '<option value="">' . h(lang('Choose a reason')) . '</option>';
    foreach (pg_return_reasons() as $code => $label) {
        $options .= '<option value="' . h($code) . '">' . h($label) . '</option>';
    }
    $several = (count($deliveries) > 1);
    foreach ($deliveries as $delivery) {
        if (!is_array($delivery)) continue;
        $id = (int)$delivery['ship_to_id'];
        $lines = array();
        $any = false;
        foreach ((array)$delivery['lines'] as $line) {
            $left = max(0, (int)$line['returnable']);
            if ($left > 0) $any = true;
            $lines[] = array(
                '__return_item_id'        => (string)(int)$line['id'],
                '__return_item_name'      => (string)$line['name'],
                '__return_item_ordered'   => (string)(int)$line['quantity'],
                '__return_item_left'      => (string)$left,
                '__return_item_qty_label' => lang(array('string' => 'How many to return: {var:1}', 'vars' => array((string)$line['name']))),
            );
        }
        if (!$any) continue;
        $label = isset($delivery['label']) ? (string)$delivery['label'] : '';
        $out['order.return_deliveries'][0][] = array(
            '__return_delivery_id'         => (string)$id,
            '__return_delivery_label'      => $label,
            '__return_delivery_several'    => ($several && $label !== '') ? '1' : '',
            '__return_delivery_deadline'   => isset($delivery['deadline']) ? (string)$delivery['deadline'] : '',
            '__return_reason_options_html' => $options,
        );
        $out['order.return_delivery_lines'][$id] = $lines;
    }
    return $out;
}

// The rows of the returns lists: array('order.returns' => array(0 =>
// rows), 'order.return_lines' => array(return id => rows)). $returns:
// pg_return_present() rows; $form: what pg_return_actions_html() posts
// with. Words as pg_returns_list_html() writes them, raw: the pass escapes
// them once; the notes keep their line breaks and the customer's moves are
// the forms returns.php builds, both HTML.
function pg_sw_list_order_return_rows($returns, $form)
{
    $out = array('order.returns' => array(), 'order.return_lines' => array());
    foreach ((array)$returns as $return) {
        if (!is_array($return)) continue;
        $id = (int)$return['id'];
        $lines = array();
        foreach ((array)$return['lines'] as $line) {
            $lines[] = array('__return_line_name' => (string)$line['name'], '__return_line_qty' => (string)(int)$line['quantity']);
        }
        if ($lines) $out['order.return_lines'][$id] = $lines;
        $how = ((string)$return['status'] === 'approved') ? pg_return_how($return) : array('text' => '');
        $asked = (string)$return['requested'];
        // Set only while the label is offered (pg_return_customer_view()).
        $label = isset($return['label_url']) ? (string)$return['label_url'] : '';
        $out['order.returns'][0][] = array(
            '__return_id'                 => (string)$id,
            '__return_title'              => lang(array('string' => 'Return request #{var:1}', 'vars' => array($id))),
            '__return_asked'              => ($asked !== '') ? lang(array('string' => 'Asked on {var:1}', 'vars' => array($asked))) : '',
            '__return_status'             => (string)$return['status'],
            '__return_status_label'       => (string)$return['status_label'],
            '__return_has_lines'          => $lines ? '1' : '',
            '__return_reason'             => (string)$return['reason_label'],
            '__return_customer_note'      => (string)$return['customer_note'],
            '__return_customer_note_html' => nl2br(h((string)$return['customer_note'])),
            '__return_decision_note'      => (string)$return['decision_note'],
            '__return_decision_note_html' => nl2br(h((string)$return['decision_note'])),
            '__return_how'                => $how['text'],
            '__return_code'               => (string)$return['carrier_code'],
            '__return_tracking_number'    => (string)$return['tracking_number'],
            '__return_refund_deadline'    => (string)$return['refund_deadline'],
            '__return_actions_html'       => pg_return_actions_html($return, $form),
            '__return_can_mark_sent'      => !empty($return['can_mark_sent']) ? '1' : '',
            '__return_can_withdraw'       => !empty($return['can_withdraw']) ? '1' : '',
            '__return_label_url'          => $label,
            '__return_has_label'          => ($label !== '') ? '1' : '',
        );
    }
    return $out;
}
