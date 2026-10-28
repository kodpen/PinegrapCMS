<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// A module of functions.php: the visitor's cart as a list
// (widgets_lists.php), so a designer draws a mini cart in the cart link (a
// header's dropdown or offcanvas) and a read-only summary of the cart beside
// the cart widget's own editable rows:
//
//   cart.items  the products in the visitor's cart, lines saved for later
//               left out (pg_order_item_active_sql()).
//   cart.item_gift_cards  a gift card line's cards, one per unit, for the
//               cart widget's gift card part (key ^^__item_id^^).
//   cart.item_schedule  the payment schedule of a line whose product lets
//               the customer set it, for the schedule part (same key).
//
// The list reads ^^__ship_to_key^^, as the order view's lists do: 0 (the
// static value) is the whole cart, a recipient's id that recipient's lines.
// The row tokens carry the cart loop's names (^^__item_name^^,
// ^^__item_total^^ ...), so a row binds as a cart row does. The renderers
// publish the rows (_render_system_widget_cart_link(),
// _render_system_widget_shopping_cart()).
//
// Loaded by functions.php through require_once, never on its own.

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// The cart source, in pg_sw_list_sources()'s shape.
function pg_sw_list_cart_sources()
{
    return array(
        'cart.items' => array(
            'family'       => 'cart',
            'key'          => '__ship_to_key',
            'label'        => lang('Products in the cart'),
            'arg'          => '',
            'arg_required' => false,
            'tokens'       => array(
                array('__item_id', lang('Row ID (order_items.id)')),
                array('__item_name', lang('Product name (products.name)')),
                array('__item_short_description', lang('Short description (display name)')),
                array('__item_qty', lang('Quantity')),
                array('__item_price', lang('Unit price')),
                array('__item_total', lang('Row total')),
                array('__item_image', lang('Image URL')),
                array('__item_image_alt', lang('Image alt text')),
                array('__item_url', lang('Product detail page URL')),
                array('__item_remove_url', lang('Remove from cart URL')),
            ),
        ),
        // The gift cards of a cart line, one row per unit (at most 100):
        // the cart widget's gift card part (pg_sw_part_cart_item_gift_card())
        // draws a box of controls per card. The controls' names and values
        // are the system's (pg_sw_cart_gift_card_control()).
        'cart.item_gift_cards' => array(
            'family'       => 'cart',
            'key'          => '__item_id',
            'label'        => lang('Gift cards of the item'),
            'arg'          => '',
            'arg_required' => false,
            'tokens'       => array(
                array('__gift_card_number', lang('Number of the gift card')),
                array('__gift_card_count', lang('Number of gift cards')),
                array('__gift_card_field', lang('Name of the card\'s fields')),
                array('__gift_card_email', lang('Recipient Email')),
                array('__gift_card_from_name', lang('From Name')),
                array('__gift_card_message', lang('Message')),
                array('__gift_card_delivery_date', lang('Delivery Date')),
            ),
        ),
        // The payment schedule of a cart line whose product lets the
        // customer set it, one row: the cart widget's schedule part
        // (pg_sw_part_cart_item_schedule()) draws its controls, whose names,
        // values and rules are the system's (pg_sw_cart_schedule_control()).
        'cart.item_schedule' => array(
            'family'       => 'cart',
            'key'          => '__item_id',
            'label'        => lang('Payment schedule of the item'),
            'arg'          => '',
            'arg_required' => false,
            'tokens'       => array(
                array('__schedule_item', lang('Number of the cart line')),
                array('__schedule_period', lang('Frequency')),
                array('__schedule_payments', lang('Number of Payments')),
                array('__schedule_start', lang('Start Date')),
                array('__schedule_payments_required', lang('Whether the number of payments is required (1 / empty)')),
                array('__schedule_payments_help', lang('The accepted number of payments')),
                array('__schedule_start_shown', lang('Whether the start date may be chosen (1 / empty)')),
                array('__schedule_period_invalid', lang('Error class of the frequency')),
                array('__schedule_payments_invalid', lang('Error class of the number of payments')),
                array('__schedule_start_invalid', lang('Error class of the start date')),
                array('__schedule_period_options_html', lang('Choices of the frequency (HTML)')),
            ),
        ),
    );
}

// The rows of the cart list, as pg_sw_list_publish('cart') files them:
// array('cart.items' => key => rows). $items: the cart widget's items
// (item_id, name, short_description, qty, unit_price, line_total, image,
// url, remove_url, ship_to_id), in cart order. Key 0 holds every line, a
// recipient's id its lines. Raw values: the pass escapes them once.
function pg_sw_list_cart_rows($items)
{
    $out = array('cart.items' => array());
    foreach ((array)$items as $item) {
        if (!is_array($item)) continue;
        $g = function ($k) use ($item) { return isset($item[$k]) ? (string)$item[$k] : ''; };
        $row = array(
            '__item_id'                => (string)(int)$g('item_id'),
            '__item_name'              => $g('name'),
            '__item_short_description' => $g('short_description'),
            '__item_qty'               => (string)(int)$g('qty'),
            '__item_price'             => $g('unit_price'),
            '__item_total'             => $g('line_total'),
            '__item_image'             => $g('image'),
            '__item_image_alt'         => ($g('short_description') !== '') ? $g('short_description') : $g('name'),
            '__item_url'               => ($g('url') !== '') ? $g('url') : '#',
            '__item_remove_url'        => $g('remove_url'),
        );
        $out['cart.items'][0][] = $row;
        $st = (int)$g('ship_to_id');
        if ($st > 0) $out['cart.items'][$st][] = $row;
    }
    return $out + pg_sw_list_cart_gift_card_rows($items) + pg_sw_list_cart_schedule_rows($items, pg_cart_schedule_rules());
}

// The rows of the cart's schedule lists: array('cart.item_schedule' =>
// array(item id => one row)). $items: the cart widget's items, a line whose
// schedule the customer may set with 'schedule' (_pg_cart_recurring_schedule());
// $rules: pg_cart_schedule_rules(). Raw values but the frequency's options,
// which are markup: the pass escapes the rest once.
function pg_sw_list_cart_schedule_rows($items, $rules)
{
    $out = array();
    foreach ((array)$items as $item) {
        if (!is_array($item) || empty($item['schedule']) || !is_array($item['schedule'])) continue;
        $s  = $item['schedule'];
        $id = isset($s['item_id']) ? (int)$s['item_id'] : 0;
        if ($id <= 0) continue;
        $bad = function ($k) use ($s) { return !empty($s['invalid'][$k]) ? 'is-invalid' : ''; };
        $out[$id][] = array(
            '__schedule_item'                => (string)$id,
            '__schedule_period'              => isset($s['period']) ? (string)$s['period'] : '',
            '__schedule_payments'            => isset($s['payments']) ? (string)$s['payments'] : '',
            '__schedule_start'               => isset($s['start']) ? (string)$s['start'] : '',
            '__schedule_payments_required'   => !empty($rules['payments_required']) ? '1' : '',
            '__schedule_payments_help'       => isset($rules['payments_help']) ? (string)$rules['payments_help'] : '',
            '__schedule_start_shown'         => !empty($rules['show_start']) ? '1' : '',
            '__schedule_period_invalid'      => $bad('period'),
            '__schedule_payments_invalid'    => $bad('payments'),
            '__schedule_start_invalid'       => $bad('start'),
            '__schedule_period_options_html' => isset($s['options_html']) ? (string)$s['options_html'] : '',
        );
    }
    return array('cart.item_schedule' => $out);
}

// The rows of the cart's gift card lists: array('cart.item_gift_cards' =>
// array(item id => rows)). $items: the cart widget's items, a gift card
// line with 'gift_cards' (number, count, prefix, and the values its
// controls show: recipient_email_address, from_name, message,
// delivery_date). Raw values: the pass escapes them once.
function pg_sw_list_cart_gift_card_rows($items)
{
    $out = array();
    foreach ((array)$items as $item) {
        if (!is_array($item) || empty($item['gift_cards']) || !is_array($item['gift_cards'])) continue;
        $id = isset($item['item_id']) ? (int)$item['item_id'] : 0;
        if ($id <= 0) continue;
        foreach ($item['gift_cards'] as $c) {
            $g = function ($k) use ($c) { return isset($c[$k]) ? (string)$c[$k] : ''; };
            $out[$id][] = array(
                '__gift_card_number'        => (string)(int)$g('number'),
                '__gift_card_count'         => (string)(int)$g('count'),
                '__gift_card_field'         => $g('prefix'),
                '__gift_card_email'         => $g('recipient_email_address'),
                '__gift_card_from_name'     => $g('from_name'),
                '__gift_card_message'       => $g('message'),
                '__gift_card_delivery_date' => $g('delivery_date'),
            );
        }
    }
    return array('cart.item_gift_cards' => $out);
}

// The cart link's lines in the cart widget's item shape
// (pg_sw_list_cart_rows()): one query over the visitor's active lines,
// ordered as the cart orders them. $fmt_money formats minor units; the
// detail page is the catalog detail page the site resolves, the removal
// goes through remove_item_from_cart.php back to this page.
function pg_sw_list_cart_read($order_id, $fmt_money)
{
    $order_id = (int)$order_id;
    if ($order_id <= 0) return array();
    $rows = db_items(
        "SELECT oi.id AS item_id, oi.product_name, oi.quantity, oi.price, oi.ship_to_id,
                p.address_name, p.image_name, p.short_description
         FROM order_items oi
         LEFT JOIN products p ON oi.product_id = p.id
         WHERE oi.order_id = '" . $order_id . "'" . pg_order_item_active_sql('oi') . "
         ORDER BY oi.ship_to_id ASC, oi.id ASC"
    );
    $base   = defined('OUTPUT_PATH') ? OUTPUT_PATH : '/';
    $detail = pg_sw_catalog_detail_page_name(0);
    $soft   = defined('OUTPUT_SOFTWARE_DIRECTORY') ? OUTPUT_SOFTWARE_DIRECTORY : 'software';
    $send   = function_exists('get_request_uri') ? get_request_uri() : (isset($_SERVER['REQUEST_URI']) ? (string)$_SERVER['REQUEST_URI'] : '/');
    $token  = isset($_SESSION['software']['token']) ? (string)$_SESSION['software']['token'] : '';
    $items  = array();
    foreach ((array)$rows as $r) {
        $qty  = (int)$r['quantity'];
        $unit = (int)$r['price'];
        $slug = (string)$r['address_name'];
        $items[] = array(
            'item_id'           => (int)$r['item_id'],
            'name'              => (string)$r['product_name'],
            'short_description' => (string)$r['short_description'],
            'qty'               => $qty,
            'unit_price'        => $fmt_money($unit),
            'line_total'        => $fmt_money($unit * $qty),
            'image'             => ((string)$r['image_name'] !== '') ? $base . encode_url_path((string)$r['image_name']) : '',
            'url'               => ($slug === '') ? '' : $base . (($detail !== '') ? encode_url_path($detail) . '/' : '') . encode_url_path($slug),
            'remove_url'        => $base . $soft . '/remove_item_from_cart.php?order_item_id=' . (int)$r['item_id']
                                   . '&screen=shopping_cart&send_to=' . urlencode($send) . '&token=' . $token,
            'ship_to_id'        => (int)$r['ship_to_id'],
        );
    }
    return $items;
}
