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
//   account.orders   the signed-in member's orders, what the my account
//                    widget's order history section draws.
//
// The order lists read ^^__ship_to_key^^: 0 outside a shipments list (the
// static value: the whole order), the recipient's id inside one, so an items
// or parcels list there lists that recipient's. Shipments and steps have
// rows under 0 only. The account list reads ^^account_id^^.
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
            'tokens'       => array(
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
            ),
        ),
        'order.items' => array(
            'family'       => 'order',
            'key'          => '__ship_to_key',
            'label'        => lang('Items of the order'),
            'arg'          => '',
            'arg_required' => false,
            'tokens'       => array(
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
            ),
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

// One recipient's row from its ship_tos row (first_name, last_name,
// company, phone_number, address_1, address_2, city, state, zip_code,
// country, arrival_date, shipping_method: the order view's shipping
// columns) as the order view's shipping tokens print them. $arrival
// formats a Y-m-d arrival date. The item count is set by
// pg_sw_list_order_rows().
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
    );
}

// One item's row from the order view's item (its $items entry), raw: the
// pass escapes it once.
function pg_sw_list_order_item_row($item)
{
    $g = function ($k) use ($item) { return isset($item[$k]) ? (string)$item[$k] : ''; };
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
    );
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
