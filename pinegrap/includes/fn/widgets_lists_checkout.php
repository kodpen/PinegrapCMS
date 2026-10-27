<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// A module of functions.php: the express order's lists (widgets_lists.php),
// so a designer draws what the checkout shows besides its controls from
// elements of their own:
//
//   checkout.offers       the offers applied to the order (a cart discount,
//                         an item's or a recipient's bonus);
//   checkout.suggestions  the offers the cart could still earn, with their
//                         message and the page they point to;
//   checkout.gift_cards   the gift cards applied to the order: masked code,
//                         the amount taken, the balance left, a remove link;
//   checkout.recipients   the recipients of the order; a list of the cart's
//                         products (cart.items) inside a row lists theirs.
//
// The lists read ^^__ship_to_key^^, as the cart and order lists do: 0 (the
// static value) is the whole order, a recipient row's id that recipient.
// The recipient tokens carry the names of the express order's recipient
// loop (^^__recipient_name^^ ...). The address controls, the shipping
// method, the delivery date and the checkout flow stay the widget's own.
// _render_system_widget_express_order() publishes the rows.
//
// Loaded by functions.php through require_once, never on its own.

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// The checkout sources, in pg_sw_list_sources()'s shape.
function pg_sw_list_checkout_sources()
{
    return array(
        'checkout.offers' => array(
            'family'       => 'checkout',
            'key'          => '__ship_to_key',
            'label'        => lang('Offers applied to the order'),
            'arg'          => '',
            'arg_required' => false,
            'tokens'       => array(
                array('__offer_code', lang('Offer code')),
                array('__offer_text', lang('Offer text')),
            ),
        ),
        'checkout.suggestions' => array(
            'family'       => 'checkout',
            'key'          => '__ship_to_key',
            'label'        => lang('Offers to suggest'),
            'arg'          => '',
            'arg_required' => false,
            'tokens'       => array(
                array('__offer_text', lang('Offer text')),
                array('__offer_action_url', lang('Offer page URL')),
                array('__offer_action_label', lang('Offer button label')),
            ),
        ),
        'checkout.gift_cards' => array(
            'family'       => 'checkout',
            'key'          => '__ship_to_key',
            'label'        => lang('Gift cards applied to the order'),
            'arg'          => '',
            'arg_required' => false,
            'tokens'       => array(
                array('__gift_card_code', lang('Gift card code (masked)')),
                array('__gift_card_amount', lang('Amount taken from the card')),
                array('__gift_card_balance', lang('Remaining Balance')),
                array('__gift_card_remove_url', lang('Remove gift card URL')),
            ),
        ),
        'checkout.recipients' => array(
            'family'       => 'checkout',
            'key'          => '__ship_to_key',
            'label'        => lang('Recipients in the cart'),
            'arg'          => '',
            'arg_required' => false,
            'tokens'       => array(
                array('__ship_to_key', lang('Recipient in the order')),
                array('__recipient_index', lang('Recipient number')),
                array('__recipient_name', lang('Recipient name')),
                array('__recipient_address_first_name', lang('Recipient First Name')),
                array('__recipient_address_last_name', lang('Recipient Last Name')),
                array('__recipient_address_company', lang('Company')),
                array('__recipient_address_address_1', lang('Address line 1')),
                array('__recipient_address_address_2', lang('Address line 2')),
                array('__recipient_address_city', lang('City')),
                array('__recipient_address_state', lang('State / Province')),
                array('__recipient_address_zip_code', lang('Zip Code')),
                array('__recipient_address_country', lang('Country')),
                array('__recipient_address_phone_number', lang('Phone')),
                array('__ship_to_item_count', lang('Number of items')),
            ),
        ),
    );
}

// The offers applied to order $order_id, enabled ones only, each
// array('id', 'code', 'description'): the order's discount offer, the
// items' and the recipients' bonus offers. What the applied offers section
// and the offers list both show.
function pg_sw_list_checkout_applied_offers($order_id)
{
    $order_id = (int)$order_id;
    if ($order_id <= 0) return array();
    $ids = array();
    $row = db_item("SELECT discount_offer_id FROM orders WHERE id = '" . $order_id . "' LIMIT 1");
    if (is_array($row) && !empty($row['discount_offer_id'])) $ids[(int)$row['discount_offer_id']] = true;
    foreach ((array)db_items("SELECT DISTINCT offer_id FROM order_items WHERE order_id = '" . $order_id . "' AND offer_id > 0" . pg_order_item_active_sql()) as $r) {
        $ids[(int)$r['offer_id']] = true;
    }
    foreach ((array)db_items("SELECT DISTINCT offer_id FROM ship_tos WHERE order_id = '" . $order_id . "' AND offer_id > 0") as $r) {
        $ids[(int)$r['offer_id']] = true;
    }
    if (!$ids) return array();
    // offers.status is an enum('enabled','disabled'), not a boolean.
    $offers = db_items("SELECT id, code, description FROM offers WHERE id IN (" . implode(',', array_keys($ids)) . ") AND status = 'enabled' ORDER BY id ASC");
    return is_array($offers) ? $offers : array();
}

// The applied gift cards of order $order_id as the gift card list shows
// them: each card takes what is left of $discount (minor units, the gift
// card discount of the totals) up to its balance, in the order applied, as
// submit_order() redeems them. Each array('id', 'code', 'givex', 'balance',
// 'amount', 'left'), amounts in minor units.
function pg_sw_list_checkout_gift_cards($order_id, $discount)
{
    $order_id = (int)$order_id;
    if ($order_id <= 0) return array();
    $rows = db_items("SELECT id, code, old_balance, givex FROM applied_gift_cards WHERE order_id = '" . $order_id . "' ORDER BY id ASC");
    return pg_sw_list_checkout_redeem(is_array($rows) ? $rows : array(), $discount);
}

// The redemption of pg_sw_list_checkout_gift_cards(), pure: $rows the
// applied_gift_cards rows (id, code, old_balance, givex).
function pg_sw_list_checkout_redeem($rows, $discount)
{
    $left = max(0, (int)$discount);
    $out  = array();
    foreach ((array)$rows as $r) {
        $balance = max(0, (int)$r['old_balance']);
        $amount  = min($balance, $left);
        $left   -= $amount;
        $out[] = array(
            'id'      => (int)$r['id'],
            'code'    => (string)$r['code'],
            'givex'   => !empty($r['givex']),
            'balance' => $balance,
            'amount'  => $amount,
            'left'    => $balance - $amount,
        );
    }
    return $out;
}

// The rows of the checkout lists, as pg_sw_list_publish('checkout') files
// them: array(source => array(0 => rows)). $data:
//   'offers'      pg_sw_list_checkout_applied_offers()
//   'suggestions' the upsell offers of apply_offers_to_cart() (upsell_message,
//                 description, upsell_action_page_id, upsell_action_button_label)
//   'gift_cards'  pg_sw_list_checkout_gift_cards()
//   'recipients'  the ship_tos rows the widget read
//   'items'       the cart's lines (ship_to_id), for the item counts
//   'fmt'         formats minor units
//   'remove_base' the remove_gift_card_from_order.php address up to its id
//   'remove_tail' what follows the id (send_to, token)
//   'page_url'    maps a page id to its address
// Raw values: the pass escapes them once.
function pg_sw_list_checkout_rows($data)
{
    $get = function ($k) use ($data) { return (isset($data[$k]) && is_array($data[$k])) ? $data[$k] : array(); };
    $fmt = (isset($data['fmt']) && is_callable($data['fmt'])) ? $data['fmt'] : function ($c) { return (string)$c; };
    $out = array('checkout.offers' => array(0 => array()), 'checkout.suggestions' => array(0 => array()),
                 'checkout.gift_cards' => array(0 => array()), 'checkout.recipients' => array(0 => array()));

    foreach ($get('offers') as $o) {
        $text = pg_offer_public_label($o);
        if ($text === '') continue;
        $out['checkout.offers'][0][] = array('__offer_code' => (string)$o['code'], '__offer_text' => $text);
    }

    foreach ($get('suggestions') as $o) {
        if (!is_array($o)) continue;
        $text = !empty($o['upsell_message']) ? (string)$o['upsell_message'] : (!empty($o['description']) ? (string)$o['description'] : '');
        if ($text === '') continue;
        $url = '';
        $label = '';
        if (!empty($o['upsell_action_page_id']) && isset($data['page_url']) && is_callable($data['page_url'])) {
            $url   = (string)call_user_func($data['page_url'], (int)$o['upsell_action_page_id']);
            $label = !empty($o['upsell_action_button_label']) ? (string)$o['upsell_action_button_label'] : (string)lang('More Info');
        }
        $out['checkout.suggestions'][0][] = array('__offer_text' => $text, '__offer_action_url' => $url, '__offer_action_label' => $label);
    }

    $remove_base = isset($data['remove_base']) ? (string)$data['remove_base'] : '';
    $remove_tail = isset($data['remove_tail']) ? (string)$data['remove_tail'] : '';
    foreach ($get('gift_cards') as $g) {
        $out['checkout.gift_cards'][0][] = array(
            '__gift_card_code'       => $g['givex'] ? protect_givex_gift_card_code($g['code']) : protect_gift_card_code($g['code']),
            '__gift_card_amount'     => $fmt($g['amount']),
            '__gift_card_balance'    => $fmt($g['left']),
            '__gift_card_remove_url' => ($remove_base === '') ? '' : $remove_base . (int)$g['id'] . $remove_tail,
        );
    }

    $counts = array();
    foreach ($get('items') as $it) {
        $st = isset($it['ship_to_id']) ? (int)$it['ship_to_id'] : 0;
        $counts[$st] = (isset($counts[$st]) ? $counts[$st] : 0) + 1;
    }
    $recipients = $get('recipients');
    foreach (array_values($recipients) as $i => $r) {
        $rid = isset($r['id']) ? (int)$r['id'] : 0;
        $v   = function ($k) use ($r) { return isset($r[$k]) ? (string)$r[$k] : ''; };
        $name = $v('ship_to_name');
        if ($name === '') $name = trim($v('first_name') . ' ' . $v('last_name'));
        if ($name === '') $name = lang('Recipient') . ' ' . ($i + 1);
        $out['checkout.recipients'][0][] = array(
            '__ship_to_key'                    => (string)$rid,
            '__recipient_index'                => (string)($i + 1),
            '__recipient_name'                 => $name,
            '__recipient_address_first_name'   => $v('first_name'),
            '__recipient_address_last_name'    => $v('last_name'),
            '__recipient_address_company'      => $v('company'),
            '__recipient_address_address_1'    => $v('address_1'),
            '__recipient_address_address_2'    => $v('address_2'),
            '__recipient_address_city'         => $v('city'),
            '__recipient_address_state'        => $v('state'),
            '__recipient_address_zip_code'     => $v('zip_code'),
            '__recipient_address_country'      => $v('country'),
            '__recipient_address_phone_number' => $v('phone_number'),
            '__ship_to_item_count'             => (string)(isset($counts[$rid]) ? $counts[$rid] : 0),
        );
    }
    return $out;
}

// The express order's lines in the cart list's item shape
// (pg_sw_list_cart_rows()): $items the widget's lines (item_id,
// product_name, short_description, quantity, price, image_name,
// address_name, ship_to_id); $fmt formats minor units; $remove_url maps a
// line id to its remove address.
function pg_sw_list_checkout_cart_items($items, $fmt, $remove_url)
{
    $base   = defined('OUTPUT_PATH') ? OUTPUT_PATH : '/';
    $detail = null;
    $out    = array();
    foreach ((array)$items as $it) {
        if (!is_array($it)) continue;
        $qty  = (int)$it['quantity'];
        $unit = (int)$it['price'];
        $slug = isset($it['address_name']) ? (string)$it['address_name'] : '';
        // The catalog detail page is looked up once, and only for a line
        // that links to its product.
        if ($slug !== '' && $detail === null) $detail = pg_sw_catalog_detail_page_name(0);
        $out[] = array(
            'item_id'           => (int)$it['item_id'],
            'name'              => (string)$it['product_name'],
            'short_description' => isset($it['short_description']) ? (string)$it['short_description'] : '',
            'qty'               => $qty,
            'unit_price'        => $fmt($unit),
            'line_total'        => $fmt($unit * $qty),
            'image'             => !empty($it['image_name']) ? $base . encode_url_path((string)$it['image_name']) : '',
            'url'               => ($slug === '') ? '' : $base . (($detail !== '') ? encode_url_path($detail) . '/' : '') . encode_url_path($slug),
            'remove_url'        => (string)$remove_url((int)$it['item_id']),
            'ship_to_id'        => isset($it['ship_to_id']) ? (int)$it['ship_to_id'] : 0,
        );
    }
    return $out;
}
