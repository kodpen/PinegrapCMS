<?php
/**
 * PineGrap - Enterprise Website Platform
 *
 * Originally developed as LiveSite by Camelback Web Architects.
 * Since 2017, maintained and evolved by Erdal Güral (Kodpen) under the name PineGrap.
 * The final LiveSite update (2019) has been integrated into PineGrap.
 * LiveSite remains available as a separate downloadable legacy version.
 *
 * @author      Camelback Web Architects
 *              Erdal Güral (Kodpen)
 * @link        https://livesite.com
 *              https://kodpen.com
 * @copyright   2001–2019 Camelback Consulting, Inc.
 *              2016–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');

validate_token_field();
require_cookies();
initialize_order();

include_once('liveform.class.php');
$liveform = new liveform('catalog_detail');
$liveform->add_fields_to_session();

// get information about product
$query =
    "SELECT
        name,
        enabled,
        short_description,
        selection_type,
        price,
        default_quantity,
        inventory,
        inventory_quantity,
        backorder,
        shippable
    FROM products
    WHERE id = '" . escape($liveform->get_field_value('product_id')) . "'";
$result = mysqli_query(db::$con, $query) or output_error('Query failed');

// If a product could not be found, then output error.
if (mysqli_num_rows($result) == 0) {
    $liveform->mark_error('product_id', lang('Sorry, we could not determine which item you wanted to add. Please make sure you complete the selections.'));
    go($liveform->get_field_value('current_url'));
}

$row = mysqli_fetch_assoc($result);

$name = $row['name'];
$enabled = $row['enabled'];
$short_description = $row['short_description'];
$selection_type = $row['selection_type'];
$price = $row['price'];
$default_quantity = $row['default_quantity'];
$inventory = $row['inventory'];
$inventory_quantity = $row['inventory_quantity'];
$backorder = $row['backorder'];
$shippable = $row['shippable'];

// if the product is not available because it is disabled or because of inventory issues,
// then add error and forward user back to previous page
if (
    ($enabled == 0)
    ||
    (
        ($inventory == 1)
        && ($inventory_quantity == 0)
        && ($backorder == 0)
    )
) {
    // prepare product description for error
    $product_description = '';
    
    // if there is a name, then add it to the description
    if ($name != '') {
        $product_description .= $name;
    }
    
    // if there is a short description, then add it to the description
    if ($short_description != '') {
        // if the description is not blank, then add separator
        if ($product_description != '') {
            $product_description .= ' - ';
        }
        
        $product_description .= $short_description;
    }
    
    $liveform->mark_error('product_id', lang(array('string' => 'Sorry, {var:1} is not currently available.', 'vars' => array(h($product_description)))));
    header('Location: ' . URL_SCHEME . HOSTNAME . $liveform->get_field_value('current_url'));
    exit();
}

// If multi-recipient shipping is enabled and this product is shippable, we
// need a recipient. Three cases:
//
//   (a) ship_to is a real recipient name ("myself", "Alice", …) → proceed.
//   (b) ship_to is empty (no picker on the page / picker not submitted) AND
//       add_name is empty → silently default to "myself". The original code
//       errored here, but it punished visitors using designer layouts that
//       don't render the picker (or who hit Add-to-Cart from a card on the
//       listing page where no per-card picker exists). Defaulting matches
//       the historical single-recipient behaviour and is what the existing
//       hidden field on __add_to_cart_button cards already does.
//   (c) ship_to is the "+ Add new" sentinel AND add_name is blank → real
//       error: the visitor PICKED "add new" but didn't type a name. We
//       compare against BOTH the English literal AND the localized value
//       because the rendered <option value> uses lang('- add name below -')
//       which is the translated string in non-English locales.
$_ship_to_val   = (string)$liveform->get_field_value('ship_to');
$_add_name_val  = (string)$liveform->get_field_value('add_name');
$_add_sentinels = array('- add name below -', lang('- add name below -'));

if (
    (ECOMMERCE_SHIPPING == true)
    && (ECOMMERCE_RECIPIENT_MODE == 'multi-recipient')
    && ($shippable == 1)
) {
    if ($_ship_to_val === '' && $_add_name_val === '') {
        // Case (b) — silently default to "myself" so the order can proceed.
        $liveform->assign_field_value('ship_to', 'myself');
    } elseif (in_array($_ship_to_val, $_add_sentinels, true) && $_add_name_val === '') {
        // Case (c) — visitor explicitly picked "Add new" but left name blank.
        $liveform->mark_error('ship_to', lang('Please select or enter a recipient.'));
        $liveform->mark_error('add_name');
        go($liveform->get_field_value('current_url'));
    }
}

// If a quantity was entered, then remove commas.
if ($liveform->get_field_value('quantity') != '') {
    $liveform->assign_field_value('quantity', str_replace(',', '', $liveform->get_field_value('quantity')));
}

// if product is a donation
if ($selection_type == 'donation') {
    $quantity = 1;
    
    // If a quantity field was displayed to the user, and the quantity is valid,
    // then use that for the quantity.
    if (
        ($liveform->get_field_value('quantity') != '')
        && (preg_match('/^\d+$/', $liveform->get_field_value('quantity')) == 1)
    ) {
        $actual_quantity = $liveform->get_field_value('quantity');
        
    // Otherwise a quantity field was not displayed to the user, or the quantity is invalid,
    // so use default quantity.
    } else {
        $actual_quantity = $default_quantity;
    }
    
    $donation_amount = $price * $actual_quantity;
    
// else product is not a donation, so just prepare quantity
} else {
    // If the quantity is not valid, then output error.
    if (preg_match('/^\d+$/', $liveform->get_field_value('quantity')) == 0) {
        $liveform->mark_error('quantity', lang('Please enter a valid quantity.'));
        header('Location: ' . URL_SCHEME . HOSTNAME . $liveform->get_field_value('current_url'));
        exit();
    }

    $quantity = $liveform->get_field_value('quantity');
    $donation_amount = 0;
}

// Stock cap — when the product tracks inventory and backorder is off,
// reject any quantity that exceeds the available stock. Without this
// check the visitor could request 100 of a product with 2 in stock and
// the order would still be created. Donation-mode products skip this
// check (no inventory concept).
if (
    $donation_amount == 0
    && ($inventory == 1)
    && ($backorder == 0)
    && ($quantity > $inventory_quantity)
) {
    $liveform->mark_error('quantity', lang(array(
        'string' => 'Yeterli stok yok. Mevcut: {var:1}',
        'vars'   => array((int)$inventory_quantity),
    )));
    header('Location: ' . URL_SCHEME . HOSTNAME . $liveform->get_field_value('current_url'));
    exit();
}

// add order item to order
add_order_item($liveform->get_field_value('product_id'), $quantity, $donation_amount, $liveform->get_field_value('ship_to'), $liveform->get_field_value('add_name'));

// An item has been added to the order, so offers for this order
// need to be refreshed, so that the subtotal in a cart region is accurate.
update_order_item_prices();
apply_offers_to_cart();

// next_url override — catalog_listing's per-card "Sepete Ekle" form uses
// this to keep the visitor on the current catalog page (with `?cart_added=1`
// appended so a JS toast can fire). Honored only when it's a same-host path
// (no protocol / domain) to avoid open-redirect risk via a forged POST.
$next_url_raw = (string)$liveform->get_field_value('next_url');
if ($next_url_raw !== '') {
    // Accept "/path", "/path?query", "?query" — but never absolute http(s)://
    if (preg_match('#^(?:/|\?)#', $next_url_raw) && stripos($next_url_raw, '://') === false) {
        header('Location: ' . URL_SCHEME . HOSTNAME . $next_url_raw);
        $liveform->remove_form();
        exit();
    }
}

// get next page id — system-layout product pages (Visual Pinegrap Editor's
// catalog_item_view widget) submit a `next_page_id` hidden field driven by
// the widget's "Sonraki Sayfa" config. When present, prefer it over the
// legacy catalog_detail_pages table lookup so the widget's per-page
// destination wins without needing a catalog_detail_pages row.
$next_page_id = (int)$liveform->get_field_value('next_page_id');
if ($next_page_id <= 0) {
    $query = "SELECT next_page_id FROM catalog_detail_pages WHERE page_id = '" . escape($liveform->get_field_value('page_id')) . "'";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
    $row = mysqli_fetch_assoc($result);
    $next_page_id = $row['next_page_id'];
}

// send user to next page
header('Location: ' . URL_SCHEME . HOSTNAME . PATH . encode_url_path(get_page_name($next_page_id)));

$liveform->remove_form();
?>