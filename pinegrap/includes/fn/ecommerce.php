<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// A module of functions.php: Orders, cart items, offers, taxes, zones, inventory, gift cards, barcodes, product duplication and order cancellation.
//
// Loaded by functions.php through require_once, never on its own. The
// functions were moved here from functions.php as they were; PG_FUNCTIONS_DIR
// is the software directory (what dirname(__FILE__) was in functions.php),
// see the note at the top of functions.php.
if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}
function add_order_item($product_id, $quantity, $donation_amount, $ship_to, $add_name, $calendar_event_id = 0, $recurrence_number = 0)
{
    // Check if product exists and is enabled and get product info.
    $query = "SELECT
            id,
            name,
            price,
            shippable,
            selection_type,
            submit_form,
            form
        FROM products
        WHERE
            (id = '" . escape($product_id) . "')
            AND (enabled = '1')";
    $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
    $product = mysqli_fetch_assoc($result);
    // If an enabled product was not found, then return false.
    if (!$product or ($product['id'] == '')) {
        return false;
    }
    // if shipping is on and product is shippable, create ship to record or get existing ship to id
    if ((ECOMMERCE_SHIPPING == true) && ($product['shippable'] == 1)) {
        $ship_to_id = create_or_get_ship_to($ship_to, $add_name);
        // else shipping is not on and/or product is not shippable
    } else {
        $ship_to_id = 0;
    }
    // Don't allow a negative donation amount.
    if ($donation_amount < 0) {
        $donation_amount = 0;
    }
    // find out if product has already been added to order
    // ignore order items that have been added by offers because they might be discounted and we don't want to mess with that
    $query = "SELECT id
        FROM order_items
        WHERE
            (order_id = '" . ($_SESSION['ecommerce']['order_id'] ?? '') . "')
            AND (product_id = '" . escape($product_id) . "')
            AND (ship_to_id = '" . escape($ship_to_id) . "')
            AND (calendar_event_id = '" . escape($calendar_event_id) . "')
            AND (recurrence_number = '" . escape($recurrence_number) . "')
            AND (added_by_offer = '0')";
    $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
    // if product has already been added to order, update order item
    if (mysqli_num_rows($result) > 0) {
        // get order item id
        $row = mysqli_fetch_assoc($result);
        $order_item_id = $row['id'];
        // if this product is a donation product, update price
        if ($product['selection_type'] == 'donation') {
            $query = "UPDATE order_items
                SET
                    price = (price + '" . e($donation_amount) . "')
                WHERE
                    (order_id = '" . ($_SESSION['ecommerce']['order_id'] ?? '') . "')
                    AND (product_id = '" . escape($product_id) . "')
                    AND (ship_to_id = '" . escape($ship_to_id) . "')
                    AND (calendar_event_id = '" . escape($calendar_event_id) . "')
                    AND (recurrence_number = '" . escape($recurrence_number) . "')";
            $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
            // else this product is not a donation product, so update quantity
        } else {
            $query = "UPDATE order_items
                SET
                    quantity = (quantity + '" . e($quantity) . "')
                WHERE
                    (order_id = '" . ($_SESSION['ecommerce']['order_id'] ?? '') . "')
                    AND (product_id = '" . escape($product_id) . "')
                    AND (ship_to_id = '" . escape($ship_to_id) . "')
                    AND (calendar_event_id = '" . escape($calendar_event_id) . "')
                    AND (recurrence_number = '" . escape($recurrence_number) . "')";
            $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
            // if there is a ship to for this order item
            if ($ship_to_id) {
                // get ship to information
                $query = "SELECT complete

                    FROM ship_tos

                    WHERE id = '" . escape($ship_to_id) . "'";
                $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
                $row = mysqli_fetch_assoc($result);
                $complete = $row['complete'];
                // if ship to is complete, then update shipping cost for ship to
                if ($complete == 1) {
                    require_once(PG_FUNCTIONS_DIR . '/shipping.php');
                    update_shipping_cost_for_ship_to($ship_to_id);
                }
            }
        }
        // else product has not been added to order, so create order item record
    } else {
        // if this product is a donation product, use donation amount for price of order item
        if ($product['selection_type'] == 'donation') {
            $price = $donation_amount;
            // else this product is not a donation product, so use price of product for price of order item
        } else {
            $price = $product['price'];
        }
        $add_watcher = '';
        // If this product submits a form and add watcher info is in the session,
        // then add watcher info for this order item, so that a watcher is added
        // to the form and page when the order is submitted.
        if (($product['submit_form'] == 1) && (!empty($_SESSION['software']['product_submit_form']['add_watcher']))) {
            $add_watcher = ($_SESSION['software']['product_submit_form']['add_watcher'] ?? '');
        }
        $query = "INSERT INTO order_items (
                    order_id,
                    ship_to_id,
                    product_id,
                    product_name,
                    quantity,
                    price,
                    calendar_event_id,
                    recurrence_number,
                    add_watcher)
                 VALUES (
                    '" . ($_SESSION['ecommerce']['order_id'] ?? '') . "',
                    '" . escape($ship_to_id) . "',
                    '" . escape($product_id) . "',
                    '" . escape($product['name']) . "',
                    '" . escape($quantity) . "',
                    '" . escape($price) . "',
                    '" . escape($calendar_event_id) . "',
                    '" . escape($recurrence_number) . "',
                    '" . escape($add_watcher) . "')";
        $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
        $order_item_id = mysqli_insert_id(db::$con);
        // if there is a ship to for this order item
        if ($ship_to_id) {
            // get ship to information
            $query = "SELECT
                    complete,
                    country,
                    state
                FROM ship_tos
                WHERE id = '" . escape($ship_to_id) . "'";
            $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
            $row = mysqli_fetch_assoc($result);
            $complete = $row['complete'];
            $country_code = $row['country'];
            $state_code = $row['state'];
            // if ship to is complete, then do some checks
            if ($complete == 1) {
                // if order item is valid for destination and arrival date, then update shipping for ship to
                if ((validate_product_for_destination($product_id, $country_code, $state_code) == true) && (validate_order_item_for_arrival_date($order_item_id) == true)) {
                    require_once(PG_FUNCTIONS_DIR . '/shipping.php');
                    update_shipping_cost_for_ship_to($ship_to_id);
                    // else there are problems, so mark ship to as incomplete
                } else {
                    $query = "UPDATE ship_tos SET complete = 0 WHERE id = '" . escape($ship_to_id) . "'";
                    $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
                }
            }
        }
        // If this product has a product form, and there is prefill info in the session,
        // then determine if we need to prefill a product form field.
        // Prefill data is added to the session via the do.php script
        // which allows a product form field to be prefilled with a value,
        // based on where a visitor came from.
        // It is necessary to prefill the product form fields now,
        // when the order item is added, because if we prefilled on the shopping cart
        // the values would not actually be saved until the visitor updated the cart,
        // which the visitor might not do before saving or sharing the cart link.
        if (($product['form'] == 1) && (isset($_SESSION['software']['prefill_product_form'])) && (is_array($_SESSION['software']['prefill_product_form']))) {
            // Loop through the fields to be prefilled.
            foreach ($_SESSION['software']['prefill_product_form'] as $field) {
                $value = $field['value'];
                // Check if there is a field in the product form with the field name in the session.
                $field = db_item("SELECT
                        id,
                        name,
                        type,
                        wysiwyg
                    FROM form_fields
                    WHERE
                        (product_id = '" . $product['id'] . "')
                        AND (form_type = 'product')
                        AND (name = '" . e($field['name']) . "')
                    LIMIT 1");
                // If a field was found, then continue to prefill field.
                if ($field['id']) {
                    // assume that the form data type is standard until we find out otherwise
                    $form_data_type = 'standard';
                    // if the form field's type is date, date and time, or time, then set form data type to the form field type
                    if (($field['type'] == 'date') || ($field['type'] == 'date and time') || ($field['type'] == 'time')) {
                        $form_data_type = $field['type'];
                        // else if the form field is a wysiwyg text area, then set type to html
                    } elseif (($field['type'] == 'text area') && ($field['wysiwyg'] == 1)) {
                        $form_data_type = 'html';
                    }
                    // Prefill value for field in database.
                    db("INSERT INTO form_data (
                            order_id,
                            order_item_id,
                            quantity_number,
                            form_field_id,
                            data,
                            name,
                            type)
                        VALUES (
                            '" . ($_SESSION['ecommerce']['order_id'] ?? '') . "',
                            '" . $order_item_id . "',
                            '1',
                            '" . $field['id'] . "',
                            '" . escape(prepare_form_data_for_input($value, $field['type'])) . "',
                            '" . escape($field['name']) . "',
                            '$form_data_type')");
                }
            }
        }
    }
    return $order_item_id;
}
function remove_order_item($order_item_id)
{
    // before we remove order item from order, get ship to id, so we can remove ship to if necessary
    $query = "SELECT ship_to_id FROM order_items WHERE id = '" . escape($order_item_id) . "'";
    $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
    $row = mysqli_fetch_assoc($result);
    $ship_to_id = $row['ship_to_id'];
    // delete order item
    $query = "DELETE FROM order_items WHERE id = '" . escape($order_item_id) . "'";
    $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
    db("DELETE FROM order_item_gift_cards WHERE order_item_id = '" . escape($order_item_id) . "'");
    // delete product form data for order item
    $query = "DELETE FROM form_data WHERE (order_item_id = '" . escape($order_item_id) . "') AND (order_item_id != '0')";
    $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
    // get all order items for ship to id, so we can figure out if we may delete ship to record
    $query = "SELECT id FROM order_items WHERE ship_to_id = '" . escape($ship_to_id) . "'";
    $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
    // if there are no order items left for ship to, then delete ship to
    if (mysqli_num_rows($result) == 0) {
        $query = "DELETE FROM ship_tos WHERE id = '$ship_to_id'";
        $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
        // delete custom shipping form data for ship to
        $query = "DELETE FROM form_data WHERE (ship_to_id = '$ship_to_id') AND (ship_to_id != '0')";
        $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
        // else there are order items left for ship to, so update shipping cost for ship to, if necessary
    } else {
        // if there is a ship to for this order item
        if ($ship_to_id) {
            // get ship to information
            $query = "SELECT complete

                FROM ship_tos

                WHERE id = '" . escape($ship_to_id) . "'";
            $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
            $row = mysqli_fetch_assoc($result);
            $complete = $row['complete'];
            // if ship to is complete, then update shipping cost for ship to
            if ($complete == 1) {
                require_once(PG_FUNCTIONS_DIR . '/shipping.php');
                update_shipping_cost_for_ship_to($ship_to_id);
            }
        }
    }
}
// Which recipient should the product an offer gives away join?
//
// A gift belongs with what earned it. The cart is asked for the recipients
// that hold the paid items the offer requires; when the offer has no product
// rule, every recipient holding a paid item counts. One answer means the
// software can place the gift itself and the shopper is not asked at all -
// which also keeps a free item from ending up alone in a shipment of its own,
// something the checkout refuses. Anything else (nobody, or several possible
// recipients) returns 0 and the caller keeps asking.
//
// $allowed_ship_to_ids narrows the answer to an offer that is restricted to
// certain recipients.
function get_ship_to_id_for_offer_gift($offer_id, $allowed_ship_to_ids = array())
{
    $order_id = ($_SESSION['ecommerce']['order_id'] ?? '');
    if (!$order_id) {
        return 0;
    }
    $sql_products = '';
    $offer_rule_id = db_value("SELECT offer_rule_id FROM offers WHERE id = '" . e($offer_id) . "'");
    if ($offer_rule_id) {
        $required_products = db_values("SELECT product_id

            FROM offer_rules_products_xref

            WHERE offer_rule_id = '" . e($offer_rule_id) . "'");
        if ($required_products) {
            $escaped_products = array();
            foreach ($required_products as $product_id) {
                $escaped_products[] = "'" . e($product_id) . "'";
            }
            $sql_products = " AND (product_id IN (" . implode(', ', $escaped_products) . "))";
        }
    }
    $ship_to_ids = db_values("SELECT DISTINCT ship_to_id

        FROM order_items

        WHERE

            (order_id = '" . e($order_id) . "')

            AND (ship_to_id > 0)

            AND (price > 0)

            AND (added_by_offer = '0')" . $sql_products);
    // The required product may sit in a recipient of its own; when it is not in
    // the cart at all (an offer that only asks for a subtotal), every paying
    // recipient is a candidate.
    if (!$ship_to_ids && $sql_products) {
        $ship_to_ids = db_values("SELECT DISTINCT ship_to_id

            FROM order_items

            WHERE

                (order_id = '" . e($order_id) . "')

                AND (ship_to_id > 0)

                AND (price > 0)

                AND (added_by_offer = '0')");
    }
    if ($allowed_ship_to_ids) {
        $narrowed = array();
        foreach ($ship_to_ids as $ship_to_id) {
            if (in_array((int) $ship_to_id, array_map('intval', $allowed_ship_to_ids))) {
                $narrowed[] = $ship_to_id;
            }
        }
        $ship_to_ids = $narrowed;
    }
    if (count($ship_to_ids) == 1) {
        return (int) $ship_to_ids[0];
    }
    return 0;
}
// The shopper took an offer's gift out of the cart; remember or forget that.
//
// The refusal is what keeps a removed gift from coming straight back on the
// next refresh, but it is not meant to outlive the reason for it: as soon as
// the offer stops applying to this cart, it is dropped, so qualifying again
// brings the gift back.
function pg_offer_gift_refused($offer_id, $offer_action_id, $forget = false)
{
    $order_id = ($_SESSION['ecommerce']['order_id'] ?? '');
    if (!$order_id) {
        return false;
    }
    $key = $order_id . '_' . $offer_id . '_' . $offer_action_id;
    if (empty($_SESSION['ecommerce']['declined_offer_gifts'])) {
        return false;
    }
    $position = array_search($key, $_SESSION['ecommerce']['declined_offer_gifts']);
    if ($position === false) {
        return false;
    }
    if ($forget) {
        unset($_SESSION['ecommerce']['declined_offer_gifts'][$position]);
        $_SESSION['ecommerce']['declined_offer_gifts'] = array_values($_SESSION['ecommerce']['declined_offer_gifts']);
        return false;
    }
    return true;
}

// Put the product an offer gives away into the cart.
//
// The offer already qualified; this only decides how the gift shows up. When
// the shopper is already paying for that product - a required product added
// itself, or they simply put one in the basket - the gift is that line's
// discount, not a second line of the same thing. Otherwise the product is
// added, marked as put there by the offer, so that the cart takes it back out
// when the offer stops applying.
//
// A gift the shopper removed on purpose is not pushed back in: remove_item_from_cart.php
// remembers the refusal for the rest of the order.
//
// $ship_to_id is 0 when the site does not ship or ships to one address.
// Returns false only when nothing could be done, so the caller can fall back
// to asking.
function apply_offer_gift_to_cart($offer_id, $offer_action_id, $ship_to_id = 0)
{
    $order_id = ($_SESSION['ecommerce']['order_id'] ?? '');
    if (!$order_id) {
        return false;
    }
    $action = db_item("SELECT

            offer_actions.add_product_product_id,

            offer_actions.add_product_quantity,

            offer_actions.add_product_discount_amount,

            offer_actions.add_product_discount_percentage,

            products.name AS product_name,

            products.price AS product_price

        FROM offer_actions

        LEFT JOIN products ON offer_actions.add_product_product_id = products.id

        WHERE offer_actions.id = '" . e($offer_action_id) . "'");
    // The action no longer adds anything, or the product is gone.
    if (!$action || !$action['add_product_product_id'] || !$action['add_product_quantity'] || ($action['product_name'] === NULL)) {
        return true;
    }
    if ($action['add_product_discount_amount'] != 0) {
        $discounted_price = $action['product_price'] - $action['add_product_discount_amount'];
    } else {
        $discounted_price = $action['product_price'] - ($action['product_price'] * ($action['add_product_discount_percentage'] / 100));
    }
    if ($discounted_price < 0) {
        $discounted_price = 0;
    }
    $discounted_by_offer = (($action['add_product_discount_amount'] != 0) || ($action['add_product_discount_percentage'] != 0)) ? 1 : 0;

    $sql_ship_to = $ship_to_id ? " AND (ship_to_id = '" . e($ship_to_id) . "')" : "";

    // Already placed by this offer.
    if (db_value("SELECT id FROM order_items

        WHERE

            (order_id = '" . e($order_id) . "')

            AND (offer_id = '" . e($offer_id) . "')

            AND (offer_action_id = '" . e($offer_action_id) . "')" . $sql_ship_to)) {
        return true;
    }

    // The shopper is already paying for this product: discount that line
    // instead of adding the same thing twice.
    $paid_order_item_id = db_value("SELECT id FROM order_items

        WHERE

            (order_id = '" . e($order_id) . "')

            AND (product_id = '" . e($action['add_product_product_id']) . "')

            AND (added_by_offer = '0')" . $sql_ship_to . "

        ORDER BY id ASC LIMIT 1");
    if ($paid_order_item_id) {
        // Note this happens even when the shopper refused the free copy: they
        // are buying this one, and the offer's discount is theirs.
        db("UPDATE order_items

            SET

                price = '" . e($discounted_price) . "',

                offer_id = '" . e($offer_id) . "',

                offer_action_id = '" . e($offer_action_id) . "',

                discounted_by_offer = '1'

            WHERE id = '" . e($paid_order_item_id) . "'");
        return true;
    }

    // A gift the shopper took out of this order is not pushed back in.
    if (pg_offer_gift_refused($offer_id, $offer_action_id)) {
        return true;
    }

    db("INSERT INTO order_items (

            order_id,

            ship_to_id,

            product_id,

            product_name,

            quantity,

            price,

            offer_id,

            offer_action_id,

            added_by_offer,

            discounted_by_offer

        ) VALUES (

            '" . e($order_id) . "',

            '" . e($ship_to_id) . "',

            '" . e($action['add_product_product_id']) . "',

            '" . escape($action['product_name']) . "',

            '" . e($action['add_product_quantity']) . "',

            '" . e($discounted_price) . "',

            '" . e($offer_id) . "',

            '" . e($offer_action_id) . "',

            '1',

            '" . e($discounted_by_offer) . "')");
    $order_item_id = mysqli_insert_id(db::$con);

    // Shipping for the recipient has to be recalculated, and a destination the
    // product cannot go to makes the recipient incomplete again - the same
    // checks the claim button used to run.
    if ($ship_to_id) {
        $ship_to = db_item("SELECT complete, country, state FROM ship_tos WHERE id = '" . e($ship_to_id) . "'");
        if ($ship_to && ($ship_to['complete'] == 1)) {
            if ((validate_product_for_destination($action['add_product_product_id'], $ship_to['country'], $ship_to['state']) == true)
                && (validate_order_item_for_arrival_date($order_item_id) == true)) {
                require_once(PG_FUNCTIONS_DIR . '/shipping.php');
                update_shipping_cost_for_ship_to($ship_to_id);
            } else {
                db("UPDATE ship_tos SET complete = 0 WHERE id = '" . e($ship_to_id) . "'");
            }
        }
    }
    return true;
}
function create_or_get_ship_to($ship_to, $add_name)
{
    // get ship to name
    // if add name text field was used, use that name for ship to name
    if ($add_name) {
        $ship_to_name = $add_name;
        // else if a name was selected from the ship_to selection drop-down, then use that value
    } elseif (($ship_to != '') && ($ship_to != lang('- add name below -'))) {
        $ship_to_name = $ship_to;
        // else use 'myself'
    } else {
        $ship_to_name = lang('myself');
    }

    // The recipient pick lists carry "myself" as the option value while they
    // show the translated label, so a selection arrives as the English word on
    // a translated site. It means the buyer - the same recipient the cart
    // creates by itself - so it is folded into that name instead of opening a
    // second recipient the buyer never asked for. A gift landing in a
    // recipient of its own is what made the cart refuse the order: a free item
    // has to travel with a paid one.
    if (mb_strtolower($ship_to_name) == 'myself') {
        $ship_to_name = lang('myself');
    }

    // figure out if ship_to record has already been created for ship to name
    $query = "SELECT id FROM ship_tos WHERE ship_to_name = '" . escape($ship_to_name) . "' AND order_id = '" . ($_SESSION['ecommerce']['order_id'] ?? '') . "'";
    $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
    // if ship_to record has already been created, get ship to id
    if (mysqli_num_rows($result)) {
        $row = mysqli_fetch_assoc($result);
        $ship_to_id = $row['id'];
        // else ship_to record has not been created, so create record
    } else {
        $query = "INSERT INTO ship_tos (order_id, ship_to_name) VALUES ('" . ($_SESSION['ecommerce']['order_id'] ?? '') . "', '" . escape($ship_to_name) . "')";
        $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
        $ship_to_id = mysqli_insert_id(db::$con);
    }
    // add recipient to session so that ship to name appears in ship to pick lists
    add_recipient($ship_to_name);
    return $ship_to_id;
}
function get_page_name($page_id)
{
    return db("SELECT page_name FROM page WHERE page_id = '" . e($page_id) . "'");
}


// Marks a card number that was encrypted with the OpenSSL implementation below.
// Values without this prefix are legacy mcrypt (Rijndael-256) blobs, which can
// only be decrypted while the mcrypt extension is still present (PHP < 7.2).
if (!defined('PG_CARD_NUMBER_CIPHER_PREFIX')) {
    define('PG_CARD_NUMBER_CIPHER_PREFIX', 'pgc1:');
}

/**
 * Whether stored card numbers can be encrypted and decrypted on this server:
 * an ENCRYPTION_KEY is configured and the OpenSSL extension is loaded.
 *
 * @return bool
 */
function credit_card_encryption_is_available()
{
    return (defined('ENCRYPTION_KEY') == TRUE)
        && (ENCRYPTION_KEY != '')
        && (extension_loaded('openssl') == TRUE)
        && (function_exists('openssl_encrypt') == TRUE);
}

function encrypt_credit_card_number($credit_card_number, $key)
{
    // if the key is too large, then someone has messed with it, so output error
    if (mb_strlen($key) > 32) {
        output_error(lang('Encryption key is too large.'));
    }
    $method = 'aes-256-cbc';
    // derive a fixed-length binary key so the configured key length does not matter to the cipher
    $binary_key = hash('sha256', $key, true);
    $iv_size = openssl_cipher_iv_length($method);
    $iv = function_exists('random_bytes') ? random_bytes($iv_size) : openssl_random_pseudo_bytes($iv_size);
    $encrypted = openssl_encrypt($credit_card_number, $method, $binary_key, OPENSSL_RAW_DATA, $iv);
    // if encryption failed, then return empty string so that the caller never stores the plain-text number by mistake
    if ($encrypted === FALSE) {
        return '';
    }
    // prepend iv, then base64 encode so that the value does not contain binary characters and can be stored easily
    return PG_CARD_NUMBER_CIPHER_PREFIX . base64_encode($iv . $encrypted);
}
function decrypt_credit_card_number($credit_card_number, $key)
{
    // if the key is too large, then someone has messed with it, so output error
    if (mb_strlen($key) > 32) {
        output_error(lang('Encryption key is too large.'));
    }
    $prefix_length = strlen(PG_CARD_NUMBER_CIPHER_PREFIX);
    // if the value does not carry the OpenSSL prefix, then it is a legacy mcrypt value
    if (substr($credit_card_number, 0, $prefix_length) !== PG_CARD_NUMBER_CIPHER_PREFIX) {
        return _pg_decrypt_legacy_credit_card_number($credit_card_number, $key);
    }
    $method = 'aes-256-cbc';
    $binary_key = hash('sha256', $key, true);
    $iv_size = openssl_cipher_iv_length($method);
    // base64 decode in order to get binary iv and encrypted credit card number
    $binary = base64_decode(substr($credit_card_number, $prefix_length), true);
    if (($binary === FALSE) || (strlen($binary) <= $iv_size)) {
        return '';
    }
    $iv = substr($binary, 0, $iv_size);
    $encrypted = substr($binary, $iv_size);
    $decrypted = openssl_decrypt($encrypted, $method, $binary_key, OPENSSL_RAW_DATA, $iv);
    // a wrong key fails the padding check; return empty string so that the caller's is_numeric() test reports a decryption error
    if ($decrypted === FALSE) {
        return '';
    }
    return $decrypted;
}
/**
 * Decrypt a card number stored by the pre-OpenSSL implementation
 * (Rijndael-256 CBC, IV prepended, base64). Only possible while the mcrypt
 * extension is loaded; on PHP 7.2 and later the value cannot be recovered and
 * an empty string is returned, which callers already treat as a decryption
 * error.
 *
 * @param string $credit_card_number Stored value without the OpenSSL prefix
 * @param string $key                Encryption key
 * @return string                    Decrypted card number, or '' on failure
 */
function _pg_decrypt_legacy_credit_card_number($credit_card_number, $key)
{
    if (
        (extension_loaded('mcrypt') == FALSE)
        || (function_exists('mcrypt_decrypt') == FALSE)
        || (in_array('rijndael-256', mcrypt_list_algorithms()) == FALSE)
    ) {
        return '';
    }
    $credit_card_number = base64_decode($credit_card_number);
    $iv_size = mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_CBC);
    if (strlen($credit_card_number) <= $iv_size) {
        return '';
    }
    $iv = substr($credit_card_number, 0, $iv_size);
    $credit_card_number = substr($credit_card_number, $iv_size);
    $decrypted_credit_card_number = mcrypt_decrypt(MCRYPT_RIJNDAEL_256, $key, $credit_card_number, MCRYPT_MODE_CBC, $iv);
    // remove null padding from the end
    return rtrim((string) $decrypted_credit_card_number, "\0");
}
function protect_credit_card_number($card_number)
{
    $protected_length = mb_strlen($card_number) - 4;

    // built up in the loop below, so it has to start out empty
    $credit_card_number = '';

    for ($i = 1; $i <= $protected_length; $i++) {
        $credit_card_number .= '*';
    }
    $credit_card_number .= mb_substr($card_number, -4);
    return $credit_card_number;
}
/**
 * Classify a raw `orders.status` value into a lifecycle stage key.
 *
 * The status column is a free-ish enum that has picked up Turkish values over
 * the years, so matching is substring-based in both languages. Order matters:
 * 'cancelled' is checked first because a cancelled order may still carry
 * words from an earlier stage in some installs.
 *
 * @param string $status Raw orders.status
 * @return string        One of: cancelled|complete|shipped|pending|refunded|default
 */
function _pg_order_status_stage($status)
{
    $s = mb_strtolower(trim((string)$status), 'UTF-8');
    if ($s === '') return 'default';
    if (strpos($s, 'cancel')   !== false || strpos($s, 'iptal')   !== false) return 'cancelled';
    if (strpos($s, 'refund')   !== false || strpos($s, 'iade')    !== false) return 'refunded';
    if (strpos($s, 'ship')     !== false || strpos($s, 'kargo')   !== false) return 'shipped';
    if (strpos($s, 'complete') !== false || strpos($s, 'tamam')   !== false) return 'complete';
    if (strpos($s, 'export')   !== false)                                    return 'complete';
    if (strpos($s, 'paid')     !== false || strpos($s, 'ödend')   !== false) return 'complete';
    if (strpos($s, 'pend')     !== false || strpos($s, 'beklem')  !== false) return 'pending';
    if (strpos($s, 'incomplete') !== false)                                  return 'pending';
    return 'default';
}

/**
 * Whether an order is still waiting for its bank transfer.
 *
 * Derived, not a status of its own: the order stays 'complete' (or 'exported')
 * so every report, filter and export keyed on orders.status keeps working, and
 * paid_at is the fact that says whether the money arrived. An offline order
 * whose paid_at is still 0 is awaiting payment; a cancelled or incomplete one
 * is not, whatever its payment method.
 *
 * @param array $order Row carrying payment_method, paid_at and status
 * @return bool
 */
function pg_order_awaiting_payment(array $order): bool
{
    return ((string) ($order['payment_method'] ?? '') === 'Offline Payment')
        && ((int) ($order['paid_at'] ?? 0) === 0)
        && in_array((string) ($order['status'] ?? ''), array('complete', 'exported'), true);
}

/**
 * Record that the payment for an order has arrived.
 *
 * Only the first recording sticks: the paid_at = 0 condition makes a repeated
 * click, or an ERP receipt landing after the operator already pressed the
 * button, a no-op instead of moving the payment date.
 *
 * @param int $order_id
 * @param int $user_id Operator recording the payment; 0 for a system path
 * @return bool True when this call marked the order paid
 */
function pg_order_mark_paid(int $order_id, int $user_id = 0): bool
{
    if ($order_id <= 0) {
        return false;
    }

    db("UPDATE orders SET paid_at = '" . time() . "' WHERE id = '" . e($order_id) . "' AND paid_at = 0");

    if (mysqli_affected_rows(db::$con) <= 0) {
        return false;
    }

    $order_number = (string) db_value("SELECT order_number FROM orders WHERE id = '" . e($order_id) . "'");

    log_activity(
        'Payment received for order (#' . $order_number . ')'
        . ($user_id > 0 ? ' recorded by user_id=' . $user_id : ' recorded by the system')
    );

    return true;
}

/**
 * Resolve the CSS classes for an order-status badge.
 *
 * Designer-owned: each stage has its own widget setting, so the colour
 * decision lives in the settings panel rather than in this renderer. The
 * defaults below are mirrored in style_designer.js as
 * SD_OV_STATUS_CLASS_DEFAULTS — change one, change the other, or the canvas
 * preview and the front end drift apart.
 *
 * Input is sanitised to class-name characters: the value lands unescaped-ish
 * inside a class attribute, and a designer pasting a quote shouldn't be able
 * to break out of it.
 *
 * @param string $stage One of the keys from _pg_order_status_stage()
 * @param array  $cfg   System widget config bag
 * @return string       Space-separated class list
 */
function _pg_order_status_badge_class($stage, $cfg = array())
{
    static $defaults = array(
        'complete'  => 'badge bg-success',
        'pending'   => 'badge bg-warning text-dark',
        'shipped'   => 'badge bg-info text-dark',
        'cancelled' => 'badge bg-danger',
        'refunded'  => 'badge bg-warning text-dark',
        'default'   => 'badge bg-secondary',
    );
    if (!isset($defaults[$stage])) $stage = 'default';

    $custom = '';
    if (is_array($cfg) && isset($cfg['status_class_' . $stage]) && is_string($cfg['status_class_' . $stage])) {
        $custom = trim(preg_replace('/[^A-Za-z0-9 _-]/', '', $cfg['status_class_' . $stage]));
    }
    return $custom !== '' ? $custom : $defaults[$stage];
}

/**
 * Turn whatever `orders.card_number` holds into a display-safe masked string.
 *
 * The column carries three different shapes depending on how the order was
 * paid (see submit_order.php ~4029):
 *   • already masked  — "************1111"  (gateway transaction succeeded;
 *                        submit_order stored protect_credit_card_number())
 *   • encrypted blob  — base64, longer than 16 chars (ENCRYPTION_KEY path)
 *   • plain digits    — no encryption configured
 *
 * The resolution order below is copied from get_order_receipt.php:1265 and
 * get_view_order_screen_content.php:1409 so the customer-facing system widget
 * shows exactly what the legacy receipt shows — including refusing to show
 * anything when an encrypted value can't be decrypted. Values written by the
 * old mcrypt implementation cannot be decrypted once PHP dropped mcrypt (7.2),
 * so for those the encrypted branch deliberately yields ''.
 *
 * Output shape: the BIN prefix is preserved when we actually have it
 * (plaintext / decrypted → "4111********1111"). When the stored value was
 * already masked the first digits are gone for good, so it stays
 * "************1111" — inventing a prefix would be a lie.
 *
 * @param string $stored Raw orders.card_number
 * @return string        Masked number, or '' when nothing can be shown.
 */
function _pg_mask_order_card_number($stored)
{
    $card = trim((string)$stored);
    if ($card === '') return '';

    // Already masked by submit_order — hand it back untouched.
    if (mb_substr($card, 0, 1) === '*') return $card;

    if (mb_strlen($card) > 16) {
        // Encrypted blob. Only attempt when encryption is genuinely
        // available; otherwise show nothing.
        if (credit_card_encryption_is_available()) {
            $card = decrypt_credit_card_number($card, ENCRYPTION_KEY);
            // A failed decrypt yields garbage, not digits — bail rather than
            // printing binary noise to the visitor.
            if (!is_numeric($card)) return '';
        } else {
            return '';
        }
    }

    $digits = preg_replace('/\D/', '', $card);
    if (mb_strlen($digits) < 4) return '';

    $last4 = mb_substr($digits, -4);
    // 12+ digits means we have a real BIN prefix worth showing.
    if (mb_strlen($digits) >= 12) {
        $bin = mb_substr($digits, 0, 4);
        return $bin . str_repeat('*', mb_strlen($digits) - 8) . $last4;
    }
    return str_repeat('*', max(0, mb_strlen($digits) - 4)) . $last4;
}

function protect_card_verification_number($card_verification_number)
{
    $protected_length = mb_strlen($card_verification_number);
    $protected_card_verification_number = '';
    for ($i = 1; $i <= $protected_length; $i++) {
        $protected_card_verification_number .= '*';
    }
    return $protected_card_verification_number;
}


function get_valid_zones($ship_to_id, $country_code, $state_code)
{
    // get all products in cart for this ship to
    $query = "SELECT product_id

             FROM order_items

             WHERE order_id = '" . ($_SESSION['ecommerce']['order_id'] ?? '') . "' AND ship_to_id = '" . escape($ship_to_id) . "'";
    $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
    $order_items = array();
    // add all items in cart to array
    while ($row = mysqli_fetch_assoc($result)) {
        $order_items[] = $row['product_id'];
    }
    $zones_for_order_items = array();
    // loop through all order items
    foreach ($order_items as $key => $product_id) {
        $zones_for_order_item = array();
        // get all allowed zones for this order item
        $query = "SELECT zone_id

                 FROM products_zones_xref

                 WHERE product_id = '$product_id'";
        $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
        while ($row = mysqli_fetch_assoc($result)) {
            $zones_for_order_item[] = $row['zone_id'];
        }
        // if this is the first order item, load $zones_for_order_items array with values
        if ($key == 0) {
            $zones_for_order_items = $zones_for_order_item;
        }
        // set zones for order items to only allowed zones from past order items and allowed zones for this order item
        $zones_for_order_items = array_intersect($zones_for_order_items, $zones_for_order_item);
    }
    $zones_for_destination = get_valid_zones_for_destination($country_code, $state_code);
    $zones = array();
    // loop through all valid zones for order items
    foreach ($zones_for_order_items as $zone_id) {
        // if zone is also valid for destination, then zone is valid
        if (in_array($zone_id, $zones_for_destination) == true) {
            $zones[] = $zone_id;
        }
    }
    return $zones;
}
function get_valid_zones_for_destination($country_code, $state_code)
{
    // declare zones for country array that we will use to store all valid zones for recipient's country
    $zones_for_country = array();
    // get all zones that contain country
    $query = "SELECT zone_id

             FROM zones_countries_xref

             LEFT JOIN countries ON countries.id = zones_countries_xref.country_id

             WHERE countries.code = '" . escape($country_code) . "'";
    $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
    while ($row = mysqli_fetch_assoc($result)) {
        $zones_for_country[] = $row['zone_id'];
    }
    // declare zones for state array that we will use to store all valid zones for recipient's state
    $zones_for_state = array();
    // declare zones array that we will use to store all valid zones for recipient's address
    $zones_for_destination = array();
    // get country id
    $query = "SELECT id FROM countries WHERE code = '" . escape($country_code) . "'";
    $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
    $row = mysqli_fetch_assoc($result);

    // $row is null when the country code is not in the database.
    $country_id = isset($row['id']) ? $row['id'] : '';
    // find out if recipient's state is found in database and if it belongs to receipient's country
    $query = "SELECT id

        FROM states

        WHERE

            (code = '" . escape($state_code) . "')

            AND (country_id = '$country_id')";
    $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
    // if recipient's state was found in database and it belongs to recipient's country, find what zones contain recipient's state
    if (mysqli_num_rows($result) > 0) {
        $query = "SELECT zone_id

                 FROM zones_states_xref

                 LEFT JOIN states ON states.id = zones_states_xref.state_id

                 WHERE states.code = '" . escape($state_code) . "'";
        $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
        while ($row = mysqli_fetch_assoc($result)) {
            $zones_for_state[] = $row['zone_id'];
        }
        // loop through all zones for country
        foreach ($zones_for_country as $zone_id) {
            // if zone id in zone for country is also a valid zone for state, then it is a valid zone, so add it to zones array
            if (in_array($zone_id, $zones_for_state) == true) {
                $zones_for_destination[] = $zone_id;
            }
        }
        // else recipient's state was not found in database, so valid zones are all valid zones for country
    } else {
        $zones_for_destination = $zones_for_country;
    }
    return $zones_for_destination;
}
function validate_product_for_destination($product_id, $country_code, $state_code)
{
    // get country id
    $query = "SELECT id FROM countries WHERE code = '" . escape($country_code) . "'";
    $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
    $row = mysqli_fetch_assoc($result);

    // $row is null when the country code is not in the database.
    $country_id = isset($row['id']) ? $row['id'] : '';
    // get all allowed zones for this product
    $query = "SELECT zone_id

             FROM products_zones_xref

             WHERE product_id = '" . escape($product_id) . "'";
    $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
    $zones = array();
    // loop through all allowed zones for this product
    while ($row = mysqli_fetch_assoc($result)) {
        $zones[] = $row['zone_id'];
    }
    foreach ($zones as $key => $zone_id) {
        // determine if recipient's country is in this zone
        $query = "SELECT zone_id FROM zones_countries_xref

                 LEFT JOIN countries ON zones_countries_xref.country_id = countries.id

                 WHERE (zone_id = '$zone_id') AND (countries.code = '" . escape($country_code) . "')";
        $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
        // if result was found, then product can be shipped to recipient's country
        if (mysqli_num_rows($result) > 0) {
            $valid_product = true;
        } else {
            $valid_product = false;
        }
        // if product is valid for recipient's country, check on state
        if ($valid_product == true) {
            // find out if recipient's state is found in database and if it belongs to recipient's country
            $query = "SELECT id

                FROM states

                WHERE

                    (code = '" . escape($state_code) . "')

                    AND (country_id = '$country_id')";
            $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
            // if recipient's state was found in database and it belongs to recipient's country, check to see if this product can be shipped to recipient's state
            if (mysqli_num_rows($result) > 0) {
                // determine if recipient's state is in this zone
                $query = "SELECT zone_id FROM zones_states_xref

                         LEFT JOIN states ON zones_states_xref.state_id = states.id

                         WHERE (zone_id = '$zone_id') AND (states.code = '" . escape($state_code) . "')";
                $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
                // if result was found, then product can be shipped to recipient's state
                if (mysqli_num_rows($result) > 0) {
                    $valid_product = true;
                    // else result was not found, so product cannot be shipped to recipient's state
                } else {
                    $valid_product = false;
                }
            }
        }
        // if this product is valid for the destination, return true
        if ($valid_product == true) {
            return true;
        }
    }
    return false;
}
function get_order_subtotal()
{
    $query = "SELECT price, quantity FROM order_items WHERE order_id = '" . ($_SESSION['ecommerce']['order_id'] ?? '') . "'";
    $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
    $order_subtotal = 0;
    while ($row = mysqli_fetch_assoc($result)) {
        $order_subtotal += $row['price'] * $row['quantity'];
    }
    return $order_subtotal;
}
// Create function that is responsible for removing order items from an order
// if product no longer exists or is disabled and also updating order item prices.
function update_order_item_prices()
{
    // get all order items for this order
    $query = "SELECT

                order_items.id as id,

                order_items.offer_id,

                products.id as product_id,

                products.enabled AS product_enabled,

                products.price as product_price,

                products.selection_type as product_selection_type

             FROM order_items

             LEFT JOIN products ON order_items.product_id = products.id

             WHERE order_items.order_id = '" . ($_SESSION['ecommerce']['order_id'] ?? '') . "'";
    $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
    $order_items = array();
    while ($row = mysqli_fetch_assoc($result)) {
        $order_items[] = $row;
    }
    // loop through all order items
    foreach ($order_items as $key => $value) {
        // If a product was found for the order item and the product is enabled,
        // then continue to determine if price needs to be updated.
        if (($order_items[$key]['product_id']) && ($order_items[$key]['product_enabled'] == 1)) {
            // if order item was not modified by a special offer and order item is not a donation, update order item price
            if (($order_items[$key]['offer_id'] == 0) && ($order_items[$key]['product_selection_type'] != 'donation')) {
                $query = "UPDATE order_items

                         SET price = '" . $order_items[$key]['product_price'] . "'

                         WHERE id = '" . $order_items[$key]['id'] . "'";
                $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
            }
            // Otherwise a product was not found for order item (an admin recently deleted product),
            // or product is now disabled, so remove order item from cart.
        } else {
            remove_order_item($order_items[$key]['id']);
        }
    }
}
/**
 * The rate that applies to one product once the address has said tax is due.
 *
 * The tax zone answers WHETHER a sale is taxed; this answers HOW MUCH. A
 * destination-based sales tax has one rate for the whole basket and products
 * carry no rate of their own, which is the case NULL covers - the zone's rate
 * stands. A value-added tax puts the rate on the article instead, so a product
 * that names one wins over the zone.
 *
 * NULL and 0.000 are different answers: no opinion, versus zero-rated.
 *
 * @param  string|float|null $product_rate products.tax_rate
 * @param  float             $zone_rate    the rate resolved from the address
 * @return float
 */
/**
 * A product's stock or price changed. Tell whoever is selling it elsewhere.
 *
 * One line at every place that writes those two columns, and the reason it is a
 * function rather than the call itself is that the callers must not have to
 * know whether this installation has a marketplace at all. On a shop with no
 * accounts this is one indexed read that finds nothing.
 *
 * Deliberately only a queue write. The marketplace is not called here: this
 * runs inside checkout, inside a barcode scan, inside a bulk edit of four
 * hundred products, and none of those may wait on somebody else's server. The
 * accounting integration in this tree does exactly that and it is why an order
 * can take eleven seconds to save.
 *
 * @param int $product_id
 * @param int $priority 1 sends on the next pass, 5 is ordinary
 */
function pg_marketplace_product_changed($product_id, $priority = 5)
{
    $product_id = (int) $product_id;

    if (!$product_id or !defined('DB_CONNECTED')) {
        return 0;
    }

    $connectors = PG_FUNCTIONS_DIR . '/includes/api/outbound/connectors/base.php';

    if (!is_file($connectors)) {
        return 0;
    }

    require_once($connectors);

    return mp_enqueue($product_id, 'stock_price', $priority);
}


function get_effective_tax_rate($product_rate, $zone_rate)
{
    if ($product_rate === null || $product_rate === '') {
        return (float) $zone_rate;
    }

    return (float) $product_rate;
}


/**
 * A typed tax rate as it is stored, or NULL when the field was left empty.
 *
 * Empty is not zero. Empty means the product has no rate of its own and the
 * buyer's tax zone decides, which is how every product behaved before the
 * column existed; zero means zero-rated, taxed at 0% wherever tax applies.
 *
 * A comma is accepted as the decimal separator because that is what a Turkish
 * keyboard produces and MySQL would read "8,25" as 8.
 *
 * @param  string $raw
 * @return string|null
 */
function parse_tax_rate($raw)
{
    $raw = trim((string) $raw);

    if ($raw === '') {
        return NULL;
    }

    $raw = str_replace(',', '.', $raw);

    if (!is_numeric($raw)) {
        return NULL;
    }

    $rate = (float) $raw;

    if ($rate < 0) {
        $rate = 0;
    }

    // The column is DECIMAL(6,3) and a rate is a percentage; anything past a
    // hundred is a typo, and letting it through would overcharge a customer.
    if ($rate > 100) {
        $rate = 100;
    }

    return number_format($rate, 3, '.', '');
}


/**
 * A stored rate as an operator should see it.
 *
 * 20.000 reads as 20 and 8.250 as 8.25, but 0.000 reads as 0 rather than
 * collapsing to an empty box: empty and zero are different answers on this
 * field, and a display that blurred them would let a save turn a zero-rated
 * product back into one that follows the zone.
 *
 * @param  string|float|null $rate
 * @return string
 */
function format_tax_rate($rate)
{
    if ($rate === NULL || $rate === '') {
        return '';
    }

    $formatted = rtrim(rtrim(number_format((float) $rate, 3, '.', ''), '0'), '.');

    return ($formatted === '') ? '0' : $formatted;
}


/**
 * The rate the site's own country resolves to.
 *
 * Used where there is no buyer yet - the cart summary before checkout, the
 * placeholder on the product screen, the figure the external API reports so an
 * integration can work out what a product with no rate of its own is charged
 * at. The order calculation never uses this: it resolves the rate from the
 * address, which is the entire point of the zone table.
 *
 * Answers false when the site's country is in no zone, which is the same
 * "no tax here" that get_tax_rate_for_address() returns.
 *
 * @return float|false
 */
function get_default_tax_rate()
{
    static $default_rate = null;

    if ($default_rate !== null) {
        return $default_rate;
    }

    $default_rate = false;

    if (!defined('DB_CONNECTED') || !function_exists('get_tax_rate_for_address')) {
        return $default_rate;
    }

    $country = (string) db_value(
        "SELECT code FROM countries WHERE default_selected = 1 ORDER BY id ASC LIMIT 1"
    );

    if ($country !== '') {
        $default_rate = get_tax_rate_for_address($country, '');
    }

    return $default_rate;
}


function update_order_item_taxes()
{
    // get information from order
    $query = "SELECT

                billing_state,

                billing_country,

                tax_exempt

             FROM orders

             WHERE id = '" . ($_SESSION['ecommerce']['order_id'] ?? '') . "'";
    $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
    $row = mysqli_fetch_assoc($result);

    // $row is null when there is no order in the session yet.
    $billing_state = isset($row['billing_state']) ? $row['billing_state'] : '';
    $billing_country = isset($row['billing_country']) ? $row['billing_country'] : '';
    $tax_exempt = isset($row['tax_exempt']) ? $row['tax_exempt'] : '';
    // if order is tax-exempt, clear tax from all order items
    if ($tax_exempt) {
        $query = "UPDATE order_items

                 SET tax_total = 0, tax = 0

                 WHERE order_id = '" . ($_SESSION['ecommerce']['order_id'] ?? '') . "'";
        $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
        // else order is not tax-exempt, so we need to apply taxes to order items
    } else {
        // get tax rate for billing address
        $billing_address_tax_rate = get_tax_rate_for_address($billing_country, $billing_state);
        // get all ship tos
        $query = "SELECT

                    DISTINCT order_items.ship_to_id,

                    ship_tos.state,

                    ship_tos.country

                 FROM order_items

                 LEFT JOIN ship_tos ON order_items.ship_to_id = ship_tos.id

                 WHERE order_items.order_id = '" . ($_SESSION['ecommerce']['order_id'] ?? '') . "'

                 ORDER BY order_items.ship_to_id";
        $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
        $ship_tos = array();
        // foreach ship to, add ship to to array
        while ($row = mysqli_fetch_assoc($result)) {
            $ship_tos[] = $row;
        }
        // loop through all ship tos
        foreach ($ship_tos as $ship_to) {
            // Cleared every pass so a recipient in no tax zone cannot inherit
            // the rate resolved for the previous one.
            $shipping_address_tax_rate = false;
            // if this ship to is a real recipient, get tax rate for shipping address
            if ($ship_to['ship_to_id'] > 0) {
                $shipping_address_tax_rate = get_tax_rate_for_address($ship_to['country'], $ship_to['state']);
            }
            // get all order items for this ship to
            $query = "SELECT

                        order_items.id,

                        order_items.price,

                        order_items.quantity,

                        products.taxable,

                        products.tax_rate

                     FROM order_items

                     LEFT JOIN products ON order_items.product_id = products.id

                     WHERE order_id = '" . ($_SESSION['ecommerce']['order_id'] ?? '') . "' AND ship_to_id = '" . $ship_to['ship_to_id'] . "'";
            $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
            $order_items = array();
            // foreach order item for this ship to, add order item to array
            while ($row = mysqli_fetch_assoc($result)) {
                $order_items[] = $row;
            }
            // foreach order item
            foreach ($order_items as $order_item) {
                // if order item is taxable
                if ($order_item['taxable']) {
                    // if this order item has a recipient
                    if ($ship_to['ship_to_id'] > 0) {
                        // if shipping address is in a tax zone, apply tax
                        if ($shipping_address_tax_rate) {
                            $apply_tax = true;
                            $tax_rate = $shipping_address_tax_rate;
                            // else shipping address is not in a tax zone, so don't apply tax
                        } else {
                            $apply_tax = false;
                        }
                        // else this order item does not have a recipient
                    } else {
                        // if billing address is in a tax zone, apply tax
                        if ($billing_address_tax_rate) {
                            $apply_tax = true;
                            $tax_rate = $billing_address_tax_rate;
                            // else billing address is not in a tax zone, so don't apply tax
                        } else {
                            $apply_tax = false;
                        }
                    }
                    // else order item is not taxable
                } else {
                    $apply_tax = false;
                }
                // if taxes should be applied to this order item, apply them
                if ($apply_tax == true) {
                    // The zone said tax is due; the product says at what rate.
                    // Worked out on the line and not on one unit: rounding a unit
                    // and then multiplying gives a different answer above quantity
                    // one, and the line is the base the cart summary, Parasut and
                    // the e-document format all use.
                    //
                    // tax is left at zero. It held the unit amount and is no longer
                    // written, so anything still reading it shows nothing rather
                    // than quietly multiplying a line amount by the quantity again.
                    $effective_tax_rate = get_effective_tax_rate($order_item['tax_rate'], $tax_rate);
                    $line_quantity = max(1, (int) $order_item['quantity']);
                    $tax_amount = round($effective_tax_rate / 100 * $order_item['price'] * $line_quantity);
                    $query = "UPDATE order_items

                             SET tax_total = '$tax_amount', tax = '0'

                             WHERE id = '" . $order_item['id'] . "'";
                    $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
                    // else taxes should not be applied to order item, so clear taxes
                } else {
                    $query = "UPDATE order_items

                             SET tax_total = '0', tax = '0'

                             WHERE id = '" . $order_item['id'] . "'";
                    $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
                }
            }
        }
    }
}
// Does this shopper meet the conditions an offer sets on them?
//
// offer_rules asks what is in the basket; these ask who is holding it. The
// rows live in offer_conditions, one offer's own, and an offer with none of
// them - which is nearly all of them - costs a single cached query per
// request.
//
// A visitor with no account passes "has never ordered": there is no order
// history to hold against them. "Registered in the last N days" needs a
// registration date, so it is false for a visitor with no account and for
// accounts made before the software started recording it (user_created = 0) -
// an offer for new customers must not fall out to everyone.
// The products of one product group, as a flat list of ids. Read once per
// group per request: the discount loops ask for the same group on every order
// item they walk past.
function pg_offer_group_products($group_id)
{
    static $cache = array();
    $group_id = (int) $group_id;
    if ($group_id <= 0) {
        return array();
    }
    if (!isset($cache[$group_id])) {
        $cache[$group_id] = array_map('intval', (array) db_values(
            "SELECT DISTINCT product FROM products_groups_xref

            WHERE product_group = '" . e($group_id) . "'"));
    }
    return $cache[$group_id];
}

// Does this 'discount product' action apply to this cart item? The action
// names either one product or a product group; a group behaves as though every
// product in it had been named by hand, and a product added to the group later
// is in the campaign without the offer being edited.
function pg_offer_action_targets_product($offer_action, $product_id)
{
    $group_id = isset($offer_action['discount_product_group_id']) ? (int) $offer_action['discount_product_group_id'] : 0;
    if ($group_id > 0) {
        return in_array((int) $product_id, pg_offer_group_products($group_id));
    }
    return ($offer_action['discount_product_product_id'] == $product_id);
}

// Which way the action is aimed. Rows written before the column existed, and
// installations that have not run the upgrade, read as 'product', which is what
// they have always meant.
function pg_offer_action_target($offer_action)
{
    $target = isset($offer_action['discount_product_target']) ? (string) $offer_action['discount_product_target'] : '';
    if (($target != 'group') && ($target != 'cheapest')) {
        $target = 'product';
    }
    return $target;
}

// The least expensive line the customer is paying for. Lines an offer put in
// the cart are skipped - a gift is already free, and discounting it again would
// let one offer eat another's. A tie goes to the lowest row id so the same line
// wins every time the cart is recalculated.
//
// Read fresh on every call: apply_offers_to_cart() collects its discounts and
// writes them after the pass, so the prices this reads do not move underneath
// it, but a second pass in the same request must see the new ones.
function pg_offer_cheapest_item_id($ship_to_id = 0)
{
    $order_id = (int) ($_SESSION['ecommerce']['order_id'] ?? 0);
    if ($order_id <= 0) {
        return 0;
    }
    $sql_recipient = ((int) $ship_to_id > 0) ? "AND (ship_to_id = '" . e($ship_to_id) . "')" : '';
    return (int) db_value("SELECT id FROM order_items

        WHERE (order_id = '" . e($order_id) . "') AND (added_by_offer = '0') AND (price > 0) $sql_recipient

        ORDER BY price ASC, id ASC

        LIMIT 1");
}

// Does this product discount apply to this cart line? A cheapest-line target
// cannot answer from the product alone, so the line and the scope come in too.
function pg_offer_action_targets_item($offer_action, $order_item, $ship_to_id = 0)
{
    if (pg_offer_action_target($offer_action) == 'cheapest') {
        return ((int) $order_item['id'] == pg_offer_cheapest_item_id($ship_to_id));
    }
    return pg_offer_action_targets_product($offer_action, $order_item['product_id']);
}

// An action with nothing to discount is skipped before the cart is walked. A
// group target counts as "has a target" even when the group is empty today;
// the item loop simply finds nothing to discount.
function pg_offer_action_has_product_target($offer_action)
{
    if (pg_offer_action_target($offer_action) == 'cheapest') {
        return true;
    }
    if ((isset($offer_action['discount_product_group_id'])) && ((int) $offer_action['discount_product_group_id'] > 0)) {
        return true;
    }
    return (isset($offer_action['discount_product_id']) && ($offer_action['discount_product_id'] != ''));
}

// Order scope or recipient scope for a product discount. A product that is not
// shipped has no recipient, so the discount belongs to the order. A group
// answers with its products: if none of them ships, the group is in the same
// position as a single unshippable product.
function pg_offer_action_target_shippable($offer_action)
{
    // The cheapest line can be any product, so the discount is worked out at
    // the order level rather than guessed per recipient.
    if (pg_offer_action_target($offer_action) == 'cheapest') {
        return 0;
    }
    $group_id = isset($offer_action['discount_product_group_id']) ? (int) $offer_action['discount_product_group_id'] : 0;
    if ($group_id > 0) {
        $product_ids = pg_offer_group_products($group_id);
        if (!$product_ids) {
            return 0;
        }
        return (int) db_value("SELECT id FROM products

            WHERE (id IN ('" . implode("', '", array_map('e', $product_ids)) . "')) AND (shippable = '1')

            LIMIT 1") ? 1 : 0;
    }
    return isset($offer_action['discount_product_shippable']) ? (int) $offer_action['discount_product_shippable'] : 0;
}

// What the customer is shown for an offer in the cart's "Applied offers" list.
// The message is optional in the offer editor, and an offer without one used to
// print an empty bullet; the code is the next best thing the customer can
// recognise, since it is what they typed when the offer asks for a code.
function pg_offer_public_label($offer)
{
    if (!is_array($offer)) {
        return '';
    }
    $description = isset($offer['description']) ? trim((string) $offer['description']) : '';
    if ($description != '') {
        return $description;
    }
    return isset($offer['code']) ? trim((string) $offer['code']) : '';
}

// The offer_conditions rows of one offer. The table arrived with 2026.4.4, so
// an installation that has not run the upgrade answers with an empty list and
// every caller behaves as it did before. Rows do not change while a request
// runs, so they are read once per offer.
function pg_offer_conditions($offer_id)
{
    static $rows = array();
    static $table_exists = null;
    $offer_id = (int) $offer_id;
    if ($table_exists === null) {
        $table_exists = (bool) db_item("SHOW TABLES LIKE 'offer_conditions'");
    }
    if (!$table_exists) {
        return array();
    }
    if (!isset($rows[$offer_id])) {
        $rows[$offer_id] = (array) db_items("SELECT type, int_value, text_value

            FROM offer_conditions

            WHERE offer_id = '" . e($offer_id) . "'");
    }
    return $rows[$offer_id];
}

function pg_offer_customer_conditions_met($offer_id)
{
    static $answers = array();
    $offer_id = (int) $offer_id;
    if (isset($answers[$offer_id])) {
        return $answers[$offer_id];
    }
    $conditions = pg_offer_conditions($offer_id);
    if (!$conditions) {
        $answers[$offer_id] = true;
        return true;
    }
    $user_id = (defined('USER_ID') && USER_ID) ? (int) USER_ID : 0;
    $met = true;
    foreach ($conditions as $condition) {
        switch ($condition['type']) {
            case 'new customer':
                $mode = ($condition['text_value'] != '') ? $condition['text_value'] : 'no_orders';
                $no_orders = true;
                $registered_recently = false;
                if ($user_id) {
                    // A cancelled or unfinished order is not a purchase, so it
                    // does not spend the customer's "new" status.
                    $no_orders = !db_value("SELECT id FROM orders

                        WHERE

                            (user_id = '" . e($user_id) . "')

                            AND (status IN ('complete', 'exported'))

                        LIMIT 1");
                    $created = (int) db_value("SELECT user_created FROM user WHERE user_id = '" . e($user_id) . "'");
                    $days = ((int) $condition['int_value'] > 0) ? (int) $condition['int_value'] : 30;
                    $registered_recently = ($created > 0) && ($created >= (time() - ($days * 86400)));
                }
                if ($mode == 'registered') {
                    $met = $registered_recently;
                } else if ($mode == 'both') {
                    $met = ($no_orders && $registered_recently);
                } else {
                    $met = $no_orders;
                }
                break;

            // int_value is the ceiling for the whole shop, text_value the one
            // for a single customer; 0 on either side means no ceiling there.
            case 'usage limit':
                $total_limit = (int) $condition['int_value'];
                $customer_limit = (int) $condition['text_value'];
                if (($total_limit > 0) || ($customer_limit > 0)) {
                    $counts = pg_offer_usage_counts($offer_id);
                    if (($total_limit > 0) && ($counts['total'] >= $total_limit)) {
                        $met = false;
                    } elseif (($customer_limit > 0) && ($counts['customer'] >= $customer_limit)) {
                        $met = false;
                    }
                }
                break;
        }
        if (!$met) {
            break;
        }
    }
    $answers[$offer_id] = $met;
    return $met;
}

// "The cart holds at least N products from these groups." The groups are
// expanded into their products and handed to the same cart check the
// offer_rules product list uses, so a group behaves exactly like listing every
// product in it by hand. The answer depends on the cart, which changes while
// apply_offers_to_cart() runs, so nothing here is cached.
// How many finished orders this offer has already discounted, in total and
// for the shopper in front of us.
//
// "Used" means the offer actually changed an order, not that somebody typed
// its code: an order discount leaves orders.discount_offer_id, a product
// discount or a gift leaves order_items.offer_id, a shipping discount leaves
// ship_tos.offer_id. Between them they cover every result type, and they are
// written whether the offer came from a typed code, a key code or by itself -
// which is what the limit is meant to catch.
//
// The shopper is the account when there is one, and the billing address on the
// order otherwise; a visitor who is neither signed in nor has given an address
// yet cannot be recognised, and the per-customer limit does not hold them back.
// Finished orders do not change while a request runs, so this is read once.
function pg_offer_usage_counts($offer_id)
{
    static $answers = array();
    $offer_id = (int) $offer_id;
    if (isset($answers[$offer_id])) {
        return $answers[$offer_id];
    }
    $used = "(orders.status IN ('complete', 'exported'))

        AND (
            (orders.discount_offer_id = '" . e($offer_id) . "')

            OR (orders.id IN (SELECT order_id FROM order_items WHERE offer_id = '" . e($offer_id) . "'))

            OR (orders.id IN (SELECT order_id FROM ship_tos WHERE offer_id = '" . e($offer_id) . "'))
        )";

    $counts = array('total' => 0, 'customer' => 0);
    $counts['total'] = (int) db_value("SELECT COUNT(DISTINCT orders.id) FROM orders WHERE " . $used);

    $who = '';
    $user_id = (defined('USER_ID') && USER_ID) ? (int) USER_ID : 0;
    if ($user_id) {
        $who = "(orders.user_id = '" . e($user_id) . "')";
    } else {
        // No account: the billing address of the cart in hand is the only
        // thing that ties this visitor to an earlier order.
        $order_id = (int) ($_SESSION['ecommerce']['order_id'] ?? 0);
        if ($order_id > 0) {
            $email = trim((string) db_value("SELECT billing_email_address FROM orders WHERE id = '" . e($order_id) . "'"));
            if ($email != '') {
                $who = "(orders.user_id = '0') AND (orders.billing_email_address = '" . e($email) . "')";
            }
        }
    }
    if ($who != '') {
        $counts['customer'] = (int) db_value("SELECT COUNT(DISTINCT orders.id) FROM orders WHERE " . $used . " AND " . $who);
    }
    $answers[$offer_id] = $counts;
    return $counts;
}

// A tiered order discount: "spend 100 get 5%, spend 250 get 10%". The ladder
// is one offer_conditions row, "<cents>:<percent>" pairs separated by commas,
// read back sorted so the richest tier the cart reaches is found first.
//
// It is stored next to the offer rather than on the action because the action
// row can be shared between offers, and a ladder belongs to one campaign. The
// action keeps the first tier's percentage, so an installation that has not run
// the upgrade still discounts - at the entry rate, never at a higher one.
function pg_offer_tiers($offer_id)
{
    $tiers = array();
    foreach (pg_offer_conditions($offer_id) as $condition) {
        if ($condition['type'] != 'tiers') {
            continue;
        }
        foreach (explode(',', (string) $condition['text_value']) as $pair) {
            $parts = explode(':', $pair);
            if (count($parts) != 2) {
                continue;
            }
            $min = (int) $parts[0];
            $percent = (int) $parts[1];
            if (($min < 0) || ($percent <= 0) || ($percent > 100)) {
                continue;
            }
            $tiers[] = array('min' => $min, 'percent' => $percent);
        }
    }
    if ($tiers) {
        usort($tiers, function ($a, $b) {
            return ($b['min'] == $a['min']) ? 0 : (($b['min'] < $a['min']) ? -1 : 1);
        });
    }
    return $tiers;
}

// The percentage the cart has earned, or 0 when the offer has no ladder or the
// cart has not reached the first rung.
function pg_offer_tier_percentage($offer_id, $subtotal)
{
    foreach (pg_offer_tiers($offer_id) as $tier) {
        if ($subtotal >= $tier['min']) {
            return $tier['percent'];
        }
    }
    return 0;
}

// Conditions about the cart that do not fit the offer_rules row. The answer
// depends on what is in the basket right now, and apply_offers_to_cart() moves
// the basket while it runs, so nothing here is cached.
function pg_offer_cart_conditions_met($offer_id, $ship_to_id = 0)
{
    $conditions = pg_offer_conditions($offer_id);
    if (!$conditions) {
        return true;
    }
    require_once(PG_FUNCTIONS_DIR . '/check_for_products_in_cart.php');
    foreach ($conditions as $condition) {
        if ($condition['type'] == 'cart quantity') {
            $needed = ((int) $condition['int_value'] > 0) ? (int) $condition['int_value'] : 1;
            if (pg_offer_cart_quantity($ship_to_id) < $needed) {
                return false;
            }
            continue;
        }
        if ($condition['type'] != 'product group') {
            continue;
        }
        $group_ids = array();
        foreach (explode(',', (string) $condition['text_value']) as $group_id) {
            $group_id = (int) $group_id;
            if ($group_id > 0) {
                $group_ids[] = $group_id;
            }
        }
        // A condition that names no group left (the groups were deleted) is
        // not something the customer can satisfy, so the offer does not apply.
        if (!$group_ids) {
            return false;
        }
        $product_ids = (array) db_values("SELECT DISTINCT product FROM products_groups_xref

            WHERE product_group IN ('" . implode("', '", array_map('e', $group_ids)) . "')");
        if (!$product_ids) {
            return false;
        }
        $products = array();
        foreach ($product_ids as $product_id) {
            $products[] = array('id' => (int) $product_id);
        }
        $quantity = ((int) $condition['int_value'] > 0) ? (int) $condition['int_value'] : 1;
        if (check_for_products_in_cart(array(
            'products'  => $products,
            'quantity'  => $quantity,
            'recipient' => array('id' => $ship_to_id))) == false) {
            return false;
        }
    }
    return true;
}

// Everything in the cart, counted by the piece. A gift the offer itself put
// there does not help the customer reach the threshold that earns it, so rows
// an offer added are left out.
function pg_offer_cart_quantity($ship_to_id = 0)
{
    $order_id = (int) ($_SESSION['ecommerce']['order_id'] ?? 0);
    if ($order_id <= 0) {
        return 0;
    }
    $sql_recipient = ((int) $ship_to_id > 0) ? "AND (ship_to_id = '" . e($ship_to_id) . "')" : '';
    return (int) db_value("SELECT SUM(quantity) FROM order_items

        WHERE (order_id = '" . e($order_id) . "') AND (added_by_offer = '0') $sql_recipient");
}

function validate_offer($offer_id, $ship_to_id = 0, $subtotal = '')
{
    // get offer info
    $query = "SELECT

            offers.scope,

            offer_rules.id AS offer_rule_id,

            offer_rules.required_subtotal,

            offer_rules.required_quantity

        FROM offers

        LEFT JOIN offer_rules ON offer_rules.id = offers.offer_rule_id

        WHERE

            (offers.id = '" . e($offer_id) . "')

            AND (offers.status = 'enabled')

            AND (offers.start_date <= CURRENT_DATE())

            AND (CURRENT_DATE() <= offers.end_date)";
    $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
    // if an offer was not found, then the offer is not valid, so return false
    if (mysqli_num_rows($result) == 0) {
        return false;
    }
    $row = mysqli_fetch_assoc($result);
    $scope = $row['scope'];
    // Conditions that live outside the offer_rules row are checked before
    // anything else: an offer with no rule at all is otherwise valid for
    // everybody. The recipient only narrows the cart for a recipient-scope
    // offer; an order-scope offer looks at the whole cart.
    if (pg_offer_customer_conditions_met($offer_id) == false) {
        return false;
    }
    $group_ship_to_id = 0;
    if ((ECOMMERCE_SHIPPING == true) && (ECOMMERCE_RECIPIENT_MODE == 'multi-recipient') && ($scope == 'recipient')) {
        $group_ship_to_id = $ship_to_id;
    }
    if (pg_offer_cart_conditions_met($offer_id, $group_ship_to_id) == false) {
        return false;
    }
    $offer_rule_id = $row['offer_rule_id'];
    $required_subtotal = $row['required_subtotal'];
    $required_quantity = $row['required_quantity'];
    // If this offer does not have an offer rule, then offer is valid.
    if (!$offer_rule_id) {
        return true;
    }
    $required_products = db_items("

        SELECT product_id AS id FROM offer_rules_products_xref

        WHERE offer_rule_id = '" . e($offer_rule_id) . "'");
    // If there is no required subtotal and no required products, then the offer is valid.
    if (!$required_subtotal and !$required_products) {
        return true;
    }
    // if this offer needs to be validated at a recipient level
    if ((ECOMMERCE_SHIPPING == true) && (ECOMMERCE_RECIPIENT_MODE == 'multi-recipient') && ($scope == 'recipient') && ($ship_to_id)) {
        // Assume that required subtotal is not valid until we find otherwise.
        $required_subtotal_valid = false;
        // If there is a required subtotal for the recipient,
        // then determine if this recipient has that subtotal.
        if ($required_subtotal) {
            // Since the scope is recipient, we consider the subtotal requirement
            // to be the recipient's subtotal and not the entire order subtotal.
            $recipient_subtotal = db_value("SELECT SUM(price * CAST(quantity AS signed))

                FROM order_items

                WHERE ship_to_id = '" . e($ship_to_id) . "'");
            if ($recipient_subtotal >= $required_subtotal) {
                $required_subtotal_valid = true;
            }
            // Otherwise there is no subtotal rule, so subtotal is valid.
        } else {
            $required_subtotal_valid = true;
        }
        // Assume that required product is not valid until we find otherwise.
        $required_product_valid = false;
        // If the required subtotal is valid then continue to check if required product is valid.
        if ($required_subtotal_valid) {
            // If there is a required product for the recipient,
            // then determine if recipient has that product quantity.
            if ($required_products) {
                require_once(PG_FUNCTIONS_DIR . '/check_for_products_in_cart.php');
                if (
                    check_for_products_in_cart(array(
                        'products' => $required_products,
                        'quantity' => $required_quantity,
                        'recipient' => array(
                            'id' => $ship_to_id
                        )
                    ))
                ) {
                    $required_product_valid = true;
                }
                // Otherwise there is no required product, so it is valid.
            } else {
                $required_product_valid = true;
            }
        }
        // If both the required subtotal and required product requirements
        // have been met, then the offer is valid.
        if ($required_subtotal_valid and $required_product_valid) {
            return true;
        } else {
            return false;
        }
        // else this offer needs to be validated at an order level
    } else {
        // if a subtotal was not passed then get dynamic subtotal
        if ($subtotal === '') {
            $subtotal = get_order_subtotal();
        }
        require_once(PG_FUNCTIONS_DIR . '/check_for_products_in_cart.php');
        // If required subtotal is valid and there are no required products or required products
        // and quantity are in cart, then offer is valid.
        if (
            $subtotal >= $required_subtotal and (!$required_products or check_for_products_in_cart(array(
                'products' => $required_products,
                'quantity' => $required_quantity
            )))
        ) {
            return true;
            // else offer is not valid
        } else {
            return false;
        }
    }
}
function get_offer_code_for_special_offer_code($special_offer_code)
{
    $offer_code = '';
    $query = "SELECT code FROM offers WHERE code = '" . escape($special_offer_code) . "'";
    $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
    // If an offer was found, use that code.
    if (mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        $offer_code = $row['code'];
        // Otherwise an offer was not found, so check if there is an active key code.
    } else {
        $offer_code = db_value("SELECT offers.code

            FROM key_codes

            LEFT JOIN offers ON key_codes.offer_code = offers.code

            WHERE

                (key_codes.code = '" . e($special_offer_code) . "')

                AND (key_codes.enabled = '1')

                AND

                (

                    (key_codes.expiration_date = '0000-00-00')

                    OR (key_codes.expiration_date >= CURRENT_DATE())

                )");
    }
    return $offer_code;
}
// Create function that is responsible for updating and applying offers to an order.
function apply_offers_to_cart()
{
    // An empty cart is a fresh start: gifts the shopper refused earlier in this
    // order are forgotten with the items they belonged to.
    if (!db_value("SELECT id FROM order_items WHERE order_id = '" . e(($_SESSION['ecommerce']['order_id'] ?? '')) . "' LIMIT 1")) {
        unset($_SESSION['ecommerce']['declined_offer_gifts']);
    }
    // get all order items that are discounted by an offer in order to remove discount so we can add discounts later
    // this does not include order items that were added and discounted by an offer, because we will deal with those later
    $query = "SELECT

            order_items.id,

            order_items.added_by_offer,

            products.id AS product_id,

            products.price AS product_price

        FROM order_items

        LEFT JOIN products ON order_items.product_id = products.id

        WHERE

            (order_items.order_id = '" . ($_SESSION['ecommerce']['order_id'] ?? '') . "')

            AND (order_items.discounted_by_offer = '1')

            AND (order_items.added_by_offer = '0')";
    $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
    $order_items = array();
    while ($row = mysqli_fetch_assoc($result)) {
        $order_items[] = $row;
    }
    // loop through the order items that are discounted by offers in order to remove discount
    foreach ($order_items as $order_item) {
        // if a product was found for this order item, then remove discount
        if ($order_item['product_id'] != '') {
            $query = "UPDATE order_items

                SET

                    price = '" . $order_item['product_price'] . "',

                    offer_id = '0',

                    discounted_by_offer = '0'

                WHERE id = '" . $order_item['id'] . "'";
            $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
            // else a product was not found for this order item (e.g. an admin recently deleted product), so remove order item from order
        } else {
            remove_order_item($order_item['id']);
        }
    }
    // if shipping is on, then remove shipping discounts so we can cleanly add shipping discounts later
    if (ECOMMERCE_SHIPPING == true) {
        $query = "UPDATE ship_tos

            SET

                shipping_cost = original_shipping_cost,

                original_shipping_cost = '0',

                offer_id = '0'

            WHERE

                (order_id = '" . ($_SESSION['ecommerce']['order_id'] ?? '') . "')

                AND (offer_id != '0')";
        $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
    }
    // get special offer code for this order
    $query = "SELECT special_offer_code FROM orders WHERE id = '" . ($_SESSION['ecommerce']['order_id'] ?? '') . "'";
    $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
    $row = mysqli_fetch_assoc($result);

    // $row is null when there is no order in the session yet.
    $special_offer_code = isset($row['special_offer_code']) ? $row['special_offer_code'] : '';
    $offer_code = '';
    // if there is a special offer code for this order, then get offer code because the special offer code might just be a key code
    if ($special_offer_code != '') {
        $offer_code = get_offer_code_for_special_offer_code($special_offer_code);
    }
    // Get all active offers so we can apply offers to order.
    // We order by offer code in order to help us process competing offers later.
    $query = "SELECT

            offers.id,

            offers.code,

            offers.scope,

            offers.multiple_recipients,

            offers.description,

            offers.upsell,

            offers.upsell_message,

            offers.upsell_trigger_subtotal,

            offers.upsell_trigger_quantity,

            offers.upsell_action_button_label,

            offers.upsell_action_page_id,

            offers.only_apply_best_offer,

            offer_rules.id AS offer_rule_id,

            offer_rules.required_subtotal AS offer_rule_required_subtotal,

            offer_rules.required_quantity AS offer_rule_required_quantity

        FROM offers

        LEFT JOIN offer_rules ON offers.offer_rule_id = offer_rules.id

        WHERE

            (offers.status = 'enabled')

            AND (offers.start_date <= CURRENT_DATE())

            AND (CURRENT_DATE() <= offers.end_date)

            AND ((offers.require_code = 0) OR (offers.code = '" . escape($offer_code) . "'))

        ORDER BY offers.code ASC";
    $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
    $offers = array();
    while ($row = mysqli_fetch_assoc($result)) {
        $offers[] = $row;
    }
    // prepare to get best offers (including non-competing offers and competing offers that are the best offer)
    // competing offers are offers that share the same code but where only the best offer will be applied
    $best_offers = array();
    // create array to store the best offer id's for offer codes, so we don't have to look up the best offer multiple times
    $best_offer_ids = array();
    // loop through the offers in order to get best offers
    foreach ($offers as $key => $offer) {
        $previous_offer_code = '';
        // if this is not the first offer, then get previous offer code
        if ($key != 0) {
            $previous_offer_code = $offers[$key - 1]['code'];
        }
        $next_offer_code = '';
        // if this is not the last offer, then get next offer code
        if ($key != (count($offers) - 1)) {
            $next_offer_code = $offers[$key + 1]['code'];
        }
        // if this is not the first offer and the previous offer had the same code,
        // or if this is not the last offer and the next offer had the same code,
        // and if this offer is set so only the best offer is applied,
        // then this is a competing offer, so determine if offer is the best offer
        if ((($key != 0) && ($offer['code'] == $previous_offer_code) || ($key != (count($offers) - 1)) && ($offer['code'] == $next_offer_code)) && ($offer['only_apply_best_offer'] == 1)) {
            $best_offer_id = '';
            // if the best offer id has already been found for this offer code, then get it
            if (isset($best_offer_ids[$offer['code']]) == true) {
                $best_offer_id = $best_offer_ids[$offer['code']];
                // else the best offer id has not already been found for this offer code, so get it
                // and then remember it by adding to array
            } else {
                $best_offer_id = get_best_offer_id($offer['code']);
                $best_offer_ids[$offer['code']] = $best_offer_id;
            }
            // if this offer is the best offer, then add it to array
            if ($offer['id'] == $best_offer_id) {
                $best_offers[] = $offer;
            }
            // else this is not a competing offer, so it is a best offer, so add it to array
        } else {
            $best_offers[] = $offer;
        }
    }
    // set active offers to all the best offers
    $offers = $best_offers;
    // get order items so we can prepare array for ship tos and order items so we can loop through them later when preparing to apply offers
    $query = "SELECT

            order_items.id,

            order_items.product_id,

            order_items.added_by_offer,

            order_items.ship_to_id,

            order_items.quantity,

            products.price AS product_price,

            ship_tos.complete,

            ship_tos.shipping_method_id,

            ship_tos.shipping_cost

        FROM order_items

        LEFT JOIN ship_tos ON order_items.ship_to_id = ship_tos.id

        LEFT JOIN products ON order_items.product_id = products.id

        WHERE order_items.order_id = '" . ($_SESSION['ecommerce']['order_id'] ?? '') . "'";
    $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
    $ship_tos = array();
    while ($row = mysqli_fetch_assoc($result)) {
        // if this ship to has not been added yet, then add it
        if (isset($ship_tos[$row['ship_to_id']]) == false) {
            $ship_tos[$row['ship_to_id']] = array();
            $ship_tos[$row['ship_to_id']]['id'] = $row['ship_to_id'];
            $ship_tos[$row['ship_to_id']]['complete'] = $row['complete'];
            $ship_tos[$row['ship_to_id']]['shipping_method_id'] = $row['shipping_method_id'];
            $ship_tos[$row['ship_to_id']]['shipping_cost'] = $row['shipping_cost'];
            $ship_tos[$row['ship_to_id']]['order_items'] = array();
        }
        $ship_tos[$row['ship_to_id']]['order_items'][] = $row;
    }
    // loop through all active offers in order to get offer actions
    foreach ($offers as $key => $offer) {
        $query = "SELECT

                offer_actions.id,

                offer_actions.name,

                offer_actions.type,

                offer_actions.discount_order_amount,

                offer_actions.discount_order_percentage,

                offer_actions.discount_product_product_id,

                offer_actions.discount_product_group_id,

                offer_actions.discount_product_target,

                offer_actions.discount_product_amount,

                offer_actions.discount_product_percentage,

                offer_actions.add_product_product_id,

                offer_actions.add_product_quantity,

                offer_actions.add_product_discount_amount,

                offer_actions.add_product_discount_percentage,

                offer_actions.discount_shipping_percentage,

                discount_products.id AS discount_product_id,

                discount_products.shippable AS discount_product_shippable,

                add_products.id AS add_product_id,

                add_products.shippable AS add_product_shippable

            FROM offers_offer_actions_xref

            LEFT JOIN offer_actions ON offers_offer_actions_xref.offer_action_id = offer_actions.id

            LEFT JOIN products AS discount_products ON offer_actions.discount_product_product_id = discount_products.id

            LEFT JOIN products AS add_products ON offer_actions.add_product_product_id = add_products.id

            WHERE offers_offer_actions_xref.offer_id = '" . $offer['id'] . "'";
        $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
        $offers[$key]['offer_actions'] = mysqli_fetch_items($result);
    }
    $discounted_order_items = array();
    // get subtotal
    // we need the subtotal because we need to keep track of how the subtotal is changed as order items are discounted
    // in order for us to determine if offers are valid
    $subtotal = get_order_subtotal();
    // prepare array to store upsell offers
    // upsell offers are offers that have not had their requirements met but have hit their offer upsell triggers
    $upsell_offers = array();
    // loop through the offers in order to prepare to discount order items
    // we have to discount order items before we apply other offers,
    // because discounted order items affect the subtotal which might affect the rules for an offer
    // we are not actually discounting order items in the database in this loop for database efficiency,
    // since multiple offers might discount the same order item
    foreach ($offers as $offer) {
        // loop through the offers actions for this offer, in order to discount order items
        foreach ($offer['offer_actions'] as $offer_action) {
            // if this offer action discounts order items, then continue
            if (($offer_action['type'] == 'discount product') && (pg_offer_action_has_product_target($offer_action)) && (($offer_action['discount_product_amount'] != 0) || ($offer_action['discount_product_percentage'] != 0))) {
                $scope = '';
                // if this offer should be applied at the order level then remember that
                if ((ECOMMERCE_SHIPPING == false) || (ECOMMERCE_RECIPIENT_MODE == 'single recipient') || ($offer['scope'] == 'order') || (pg_offer_action_target_shippable($offer_action) == 0)) {
                    $scope = 'order';
                    // else this offer should be applied at the recipient level, so remember that
                } else {
                    $scope = 'recipient';
                }
                // if the scope is order and the offer is valid or the scope is recipient,
                // then prepare to discount order items
                if ((($scope == 'order') && (validate_offer($offer['id'], 0, $subtotal) == true)) || ($scope == 'recipient')) {
                    // loop through ship tos in order to loop through order items
                    foreach ($ship_tos as $ship_to) {
                        // if the scope is recipient and the offer is valid for this recipient or the scope is order,
                        // then prepare to discount order items
                        if ((($scope == 'recipient') && (validate_offer($offer['id'], $ship_to['id'], $subtotal) == true)) || ($scope == 'order')) {
                            // loop through order items for this ship to in order to prepare to discount them
                            foreach ($ship_to['order_items'] as $order_item) {
                                // if this offer discounts this product
                                // and this order item was not added by an offer,
                                // then prepare to discount it
                                if ((pg_offer_action_targets_item($offer_action, $order_item, ($scope == 'recipient') ? $ship_to['id'] : 0)) && ($order_item['added_by_offer'] == 0)) {
                                    $discounted_price = 0;
                                    // if discount is by amount, then get discounted price
                                    if ($offer_action['discount_product_amount']) {
                                        $discounted_price = $order_item['product_price'] - $offer_action['discount_product_amount'];
                                        // else discount is by percentage, so get discounted price
                                    } else {
                                        $discounted_price = $order_item['product_price'] - ($order_item['product_price'] * ($offer_action['discount_product_percentage'] / 100));
                                    }
                                    // if the discounted price is less than 0, then set discounted price to 0
                                    if ($discounted_price < 0) {
                                        $discounted_price = 0;
                                    }
                                    // if this offer is going to discount the product more than any previous offer so far, use this offer
                                    if ((isset($discounted_order_items[$order_item['id']]) == false) || ($discounted_price < $discounted_order_items[$order_item['id']]['price'])) {
                                        // if this is the first offer that has discounted this order item, then prepare array
                                        if (isset($discounted_order_items[$order_item['id']]) == false) {
                                            $discounted_order_items[$order_item['id']] = array();
                                            $discounted_order_items[$order_item['id']]['id'] = $order_item['id'];
                                        }
                                        $discounted_order_items[$order_item['id']]['price'] = $discounted_price;
                                        $discounted_order_items[$order_item['id']]['offer_id'] = $offer['id'];
                                        $discounted_order_items[$order_item['id']]['offer_action_id'] = $offer_action['id'];
                                        // add previous discount for this order item (if one exists) back to subtotal
                                        $subtotal = $subtotal + ($discounted_order_items[$order_item['id']]['discount'] ?? 0);
                                        // set new discount for this order item
                                        $discounted_order_items[$order_item['id']]['discount'] = ($order_item['product_price'] - $discounted_price) * $order_item['quantity'];
                                        // reduce subtotal by new discount for this order item
                                        // we do this so when we validate other offers, we will be validating with the most recent subtotal that we know about
                                        $subtotal = $subtotal - $discounted_order_items[$order_item['id']]['discount'];
                                    }
                                }
                            }
                            // else this offer is not valid, so if an upsell has not already been added for it,
                            // and there is an upsell, then add upsell
                        } else if ((isset($upsell_offers[$offer['id']]) == false) && (check_upsell($offer, $ship_to['id'], $subtotal) == true)) {
                            $upsell_offers[$offer['id']] = $offer;
                        }
                    }
                    // else this offer is not valid, so if an upsell has not already been added for it,
                    // and there is an upsell, then add upsell
                } else if ((isset($upsell_offers[$offer['id']]) == false) && (check_upsell($offer, 0, $subtotal) == true)) {
                    $upsell_offers[$offer['id']] = $offer;
                }
            }
        }
    }
    // loop through discounted order items in order to discount them
    foreach ($discounted_order_items as $discounted_order_item) {
        $query = "UPDATE order_items

            SET

                price = '" . $discounted_order_item['price'] . "',

                offer_id = '" . $discounted_order_item['offer_id'] . "',

                offer_action_id = '" . $discounted_order_item['offer_action_id'] . "',

                discounted_by_offer = 1

            WHERE id = '" . $discounted_order_item['id'] . "'";
        $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
    }
    // Get all order items that were added by an offer in order to determine if they are still valid.
    // We don't just want to remove them all and then add them down below because it would be too
    // much thrashing on the database and we would lose info about recipient it was added to and etc.
    // We join the offers_offer_actions_xref in order to join the offer actions table in order to
    // verify that offer action is still allowed for offer.
    $query = "SELECT

            order_items.id,

            order_items.ship_to_id,

            order_items.offer_id,

            order_items.product_id,

            offers.require_code AS offer_require_code,

            offers.code AS offer_code,

            offers.scope AS offer_scope,

            offers.multiple_recipients AS offer_multiple_recipients,

            offers.only_apply_best_offer AS offer_only_apply_best_offer,

            products.shippable AS product_shippable,

            products.price AS product_price,

            offer_actions.id AS offer_action_id,

            offer_actions.type AS offer_action_type,

            offer_actions.add_product_product_id AS offer_action_add_product_product_id,

            offer_actions.add_product_quantity AS offer_action_add_product_quantity,

            offer_actions.add_product_discount_amount AS offer_action_add_product_discount_amount,

            offer_actions.add_product_discount_percentage AS offer_action_add_product_discount_percentage

        FROM order_items

        LEFT JOIN offers ON order_items.offer_id = offers.id

        LEFT JOIN offers_offer_actions_xref ON ((order_items.offer_id = offers_offer_actions_xref.offer_id) AND (order_items.offer_action_id = offers_offer_actions_xref.offer_action_id))

        LEFT JOIN offer_actions ON offers_offer_actions_xref.offer_action_id = offer_actions.id

        LEFT JOIN products ON order_items.product_id = products.id

        WHERE

            (order_items.order_id = '" . ($_SESSION['ecommerce']['order_id'] ?? '') . "')

            AND (order_items.added_by_offer = '1')

        ORDER BY order_items.id ASC";
    $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
    $order_items = array();
    while ($row = mysqli_fetch_assoc($result)) {
        $order_items[] = $row;
    }
    // create array that will keep track of offers and actions that have added products
    $offers_and_offer_actions = array();
    // loop through the order items that were added by offers in order to determine if they are still valid
    foreach ($order_items as $order_item) {
        // if this offer does not require a code or it has the same code that the customer entered,
        // and this offer is valid,
        // and not only the best offer should be applied for this code or it is the best offer for the code,
        // and the action is still set to add product,
        // and the action still adds the same product as the product for this order item,
        // and
        // if this product has not already been added for this offer and action,
        // or the product is allowed to be added multiple times,
        // then the order item is valid, so update quantity and discount for product
        if ((($order_item['offer_require_code'] == 0) || (mb_strtolower((string) $order_item['offer_code']) == mb_strtolower((string) $offer_code))) && (validate_offer($order_item['offer_id'], $order_item['ship_to_id']) == true) && (($order_item['offer_only_apply_best_offer'] == 0) || ($order_item['offer_id'] == get_best_offer_id($order_item['offer_code']))) && ($order_item['offer_action_type'] == 'add product') && ($order_item['offer_action_add_product_product_id'] == $order_item['product_id']) && ((in_array($order_item['offer_id'] . '_' . $order_item['offer_action_id'], $offers_and_offer_actions) == false) || (ECOMMERCE_SHIPPING == false) || (ECOMMERCE_RECIPIENT_MODE == 'single recipient') || ($order_item['offer_scope'] == 'order') || ($order_item['product_shippable'] == 0) || ($order_item['offer_multiple_recipients'] == 1))) {
            $discounted_price = 0;
            // if discount is by amount, get discounted price
            if ($order_item['offer_action_add_product_discount_amount'] != 0) {
                $discounted_price = $order_item['product_price'] - $order_item['offer_action_add_product_discount_amount'];
                // else discount is by percentage, so get discounted price
            } else {
                $discounted_price = $order_item['product_price'] - ($order_item['product_price'] * ($order_item['offer_action_add_product_discount_percentage'] / 100));
            }
            $discounted_by_offer = 0;
            // if the product that was added is also being discounted, prepare to mark order item accordingly
            if (($order_item['offer_action_add_product_discount_amount'] != 0) || ($order_item['offer_action_add_product_discount_percentage'] != 0)) {
                $discounted_by_offer = 1;
            }
            // update order item quantity and price and mark order item as having been affected by an offer
            $query = "UPDATE order_items

                SET

                    quantity = '" . $order_item['offer_action_add_product_quantity'] . "',

                    price = '" . $discounted_price . "',

                    discounted_by_offer = '" . $discounted_by_offer . "'

                WHERE id = '" . $order_item['id'] . "'";
            $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
            // if it has not already been remembered that this offer and action have added a product, then remember that
            if (in_array($order_item['offer_id'] . '_' . $order_item['offer_action_id'], $offers_and_offer_actions) == false) {
                $offers_and_offer_actions[] = $order_item['offer_id'] . '_' . $order_item['offer_action_id'];
            }
            // else the order item is no longer valid, so remove order item
        } else {
            remove_order_item($order_item['id']);
        }
    }
    $best_order_discount = 0;
    $best_order_discount_offer_id = 0;
    $best_order_discount_amount = 0;
    $best_order_discount_percentage = 0;
    // pending offers are offers that add product(s) that are ready for the customer to select to add them to the order
    $pending_offers = array();
    $discounted_ship_tos = array();
    // loop through the offers in order prepare to apply offers that discount order, add product, and/or discount shipping
    foreach ($offers as $offer) {
        // loop through the offers actions for this offer, in order to prepare to apply offers
        foreach ($offer['offer_actions'] as $offer_action) {
            // apply offer differently depending on the type of action
            switch ($offer_action['type']) {
                case 'discount order':
                    // if this action actually discounts the order,
                    // then prepare to apply discount
                    if (($offer_action['discount_order_amount'] != 0) || ($offer_action['discount_order_percentage'] != 0)) {
                        // if this offer is valid,
                        // then prepare to apply discount
                        if (validate_offer($offer['id']) == true) {
                            $order_discount = 0;
                            // if discount is by amount, get order discount
                            if ($offer_action['discount_order_amount'] != 0) {
                                $subtotal = get_order_subtotal();
                                // If the subtotal is greater than or equal to the discount,
                                // then use the whole discount.
                                if ($subtotal >= $offer_action['discount_order_amount']) {
                                    $order_discount = $offer_action['discount_order_amount'];
                                    // Otherwise the subtotal is less than the possible discount,
                                    // so just set the discount to the amount of the subtotal.
                                    // This prevents us from showing a larger discount than
                                    // was actually applied on the cart, preview, and etc. screens.
                                    // It also prevents the total from ever showing a negative number,
                                    // which would be confusing to the customer.
                                } else {
                                    $order_discount = $subtotal;
                                }
                                // else discount is by percentage, so get order discount
                            } else {
                                // A ladder replaces the flat rate; without one
                                // pg_offer_tier_percentage() answers 0 and the
                                // action's own percentage stands.
                                $percentage = pg_offer_tier_percentage($offer['id'], get_order_subtotal());
                                if ($percentage <= 0) {
                                    $percentage = $offer_action['discount_order_percentage'];
                                }
                                $order_discount = round(get_order_subtotal() * ($percentage / 100));
                            }
                            // if this offer provides the best order discount so far then remember that
                            if ($order_discount > $best_order_discount) {
                                $best_order_discount = $order_discount;
                                $best_order_discount_offer_id = $offer['id'];
                                // Kept so the amount can be worked out again at
                                // the end: a gift or a product discount further
                                // down this loop still lowers the subtotal this
                                // percentage is a percentage of.
                                $best_order_discount_amount = $offer_action['discount_order_amount'];
                                $best_order_discount_percentage = $offer_action['discount_order_percentage'];
                            }
                            // else this offer is not valid, so if an upsell has not already been added for it,
                            // and there is an upsell, then add upsell
                        } else if ((isset($upsell_offers[$offer['id']]) == false) && (check_upsell($offer) == true)) {
                            $upsell_offers[$offer['id']] = $offer;
                        }
                    }
                    break;
                case 'add product':
                    // if this action actually adds a product, then continue to add pending offer
                    if (($offer_action['add_product_id'] != '') && ($offer_action['add_product_quantity'] != 0)) {
                        // if this offer should be applied at the order level then continue to add pending offer
                        if ((ECOMMERCE_SHIPPING == false) || (ECOMMERCE_RECIPIENT_MODE == 'single recipient') || ($offer['scope'] == 'order') || ($offer_action['add_product_shippable'] == 0)) {
                            // if this offer is valid at the order level, then continue to add pending offer
                            if (validate_offer($offer['id']) == true) {
                                // check if this offer and action have already been applied to this order
                                $query = "SELECT id

                                    FROM order_items

                                    WHERE

                                        (order_id = '" . ($_SESSION['ecommerce']['order_id'] ?? '') . "')

                                        AND (offer_id = '" . $offer['id'] . "')

                                        AND (offer_action_id = '" . $offer_action['id'] . "')";
                                $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
                                // The offer applies, so the gift is placed by the
                                // software: either as a discount on the line the
                                // shopper is already paying for, or as a line of
                                // its own. A recipient that cannot be worked out
                                // is the only thing that still needs asking, and
                                // then the claim button appears as before.
                                if (mysqli_num_rows($result) == 0) {
                                    $gift_ship_to_id = 0;
                                    $gift_needs_recipient = (ECOMMERCE_SHIPPING == true)
                                        && (ECOMMERCE_RECIPIENT_MODE == 'multi-recipient')
                                        && ($offer_action['add_product_shippable'] == 1);
                                    if ($gift_needs_recipient) {
                                        $gift_ship_to_id = get_ship_to_id_for_offer_gift($offer['id']);
                                    }
                                    if ((!$gift_needs_recipient || $gift_ship_to_id)
                                        && (apply_offer_gift_to_cart($offer['id'], $offer_action['id'], $gift_ship_to_id) == true)) {
                                        // placed
                                    } else {
                                        // if this offer has not been added as a pending offer yet, then prepare array
                                        if (isset($pending_offers[$offer['id']]) == false) {
                                            $pending_offers[$offer['id']] = $offer;
                                            $pending_offers[$offer['id']]['offer_actions'] = array();
                                        }
                                        $pending_offers[$offer['id']]['offer_actions'][] = $offer_action;
                                    }
                                }
                                // else this offer is not valid, so a gift the
                                // shopper refused is forgotten - the reason for
                                // the refusal is gone - and if an upsell has not
                                // already been added for it, and there is an
                                // upsell, then add upsell
                            } else {
                                pg_offer_gift_refused($offer['id'], $offer_action['id'], true);
                                if ((isset($upsell_offers[$offer['id']]) == false) && (check_upsell($offer) == true)) {
                                    $upsell_offers[$offer['id']] = $offer;
                                }
                            }
                            // else this offer should be applied at the recipient level, so continue to add pending offer
                        } else {
                            // check if this offer and action have already been applied to this order
                            $query = "SELECT id

                                FROM order_items

                                WHERE

                                    (order_id = '" . ($_SESSION['ecommerce']['order_id'] ?? '') . "')

                                    AND (offer_id = '" . $offer['id'] . "')

                                    AND (offer_action_id = '" . $offer_action['id'] . "')";
                            $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
                            // if this offer and action have not already been applied to order or offer is allowed to be applied to multiple recipients, then continue to add pending offer
                            if ((mysqli_num_rows($result) == 0) || ($offer['multiple_recipients'] == 1)) {
                                // loop through recipients
                                foreach ($ship_tos as $ship_to) {
                                    // if offer is valid for this recipient
                                    if (validate_offer($offer['id'], $ship_to['id']) == true) {
                                        // check if this offer and action have already been applied to this recipient
                                        $query = "SELECT id

                                            FROM order_items

                                            WHERE

                                                (order_id = '" . ($_SESSION['ecommerce']['order_id'] ?? '') . "')

                                                AND (ship_to_id = '" . $ship_to['id'] . "')

                                                AND (offer_id = '" . $offer['id'] . "')

                                                AND (offer_action_id = '" . $offer_action['id'] . "')";
                                        $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
                                        // The recipient is known here - the offer
                                        // qualified for this one - so the gift is
                                        // placed rather than offered.
                                        if (mysqli_num_rows($result) == 0) {
                                            if (apply_offer_gift_to_cart($offer['id'], $offer_action['id'], $ship_to['id']) == false) {
                                                // if this offer has not been added as a pending offer yet, then prepare array
                                                if (isset($pending_offers[$offer['id']]) == false) {
                                                    $pending_offers[$offer['id']] = $offer;
                                                    $pending_offers[$offer['id']]['offer_actions'] = array();
                                                }
                                                // if this offer action has not been added as a pending offer action yet, then add it and prepare allowed recipients
                                                if (isset($pending_offers[$offer['id']]['offer_actions'][$offer_action['id']]) == false) {
                                                    $pending_offers[$offer['id']]['offer_actions'][$offer_action['id']] = $offer_action;
                                                    $pending_offers[$offer['id']]['offer_actions'][$offer_action['id']]['allowed_recipients'] = array();
                                                }
                                                $pending_offers[$offer['id']]['offer_actions'][$offer_action['id']]['allowed_recipients'][] = $ship_to['id'];
                                            }
                                        }
                                        // else this offer is not valid for this
                                        // recipient, so a refused gift is
                                        // forgotten and an upsell is offered
                                    } else {
                                        pg_offer_gift_refused($offer['id'], $offer_action['id'], true);
                                        if ((isset($upsell_offers[$offer['id']]) == false) && (check_upsell($offer, $ship_to['id']) == true)) {
                                            $upsell_offers[$offer['id']] = $offer;
                                        }
                                    }
                                }
                            }
                        }
                    }
                    break;
                case 'discount shipping':
                    // if shipping is on and there is an actual discount shipping percentage, then continue to discount shipping
                    if ((ECOMMERCE_SHIPPING == true) && ($offer_action['discount_shipping_percentage'] != 0)) {
                        $scope = '';
                        // if this offer should be applied at the order level then remember that
                        if ((ECOMMERCE_RECIPIENT_MODE == 'single recipient') || ($offer['scope'] == 'order')) {
                            $scope = 'order';
                            // else this offer should be applied at the recipient level, so remember that
                        } else {
                            $scope = 'recipient';
                        }
                        // if the scope is order and the offer is valid or the scope is recipient,
                        // then prepare to discount shipping
                        if ((($scope == 'order') && (validate_offer($offer['id']) == true)) || ($scope == 'recipient')) {
                            // loop through the ship tos in order to discount shipping
                            foreach ($ship_tos as $ship_to) {
                                // if this is a real ship to,
                                // and the ship to is complete,
                                // and if the scope is recipient and the offer is valid for this recipient,
                                // or the scope is order,
                                // then prepare to discount shipping
                                if (($ship_to['id'] != 0) && ($ship_to['complete'] == 1) && ((($scope == 'recipient') && (validate_offer($offer['id'], $ship_to['id']) == true)) || ($scope == 'order'))) {
                                    // check if this offer and action is valid for the shipping method
                                    $query = "SELECT offer_action_id

                                        FROM offer_actions_shipping_methods_xref

                                        WHERE

                                            (offer_action_id = '" . $offer_action['id'] . "')

                                            AND (shipping_method_id = '" . $ship_to['shipping_method_id'] . "')";
                                    $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
                                    // if this offer and action is valid for the shipping method then continue to prepare to discount shipping
                                    if (mysqli_num_rows($result) != 0) {
                                        $original_shipping_cost = $ship_to['shipping_cost'];
                                        $discounted_shipping_cost = $original_shipping_cost - ($original_shipping_cost * ($offer_action['discount_shipping_percentage'] / 100));
                                        // if this offer is going to discount the ship to more than any previous offer so far, use this offer
                                        if ((isset($discounted_ship_tos[$ship_to['id']]) == false) || ($discounted_shipping_cost < $discounted_ship_tos[$ship_to['id']]['shipping_cost'])) {
                                            // if this is the first offer that has discounted this ship to, then prepare array
                                            if (isset($discounted_ship_tos[$ship_to['id']]) == false) {
                                                $discounted_ship_tos[$ship_to['id']] = array();
                                                $discounted_ship_tos[$ship_to['id']]['id'] = $ship_to['id'];
                                            }
                                            $discounted_ship_tos[$ship_to['id']]['shipping_cost'] = $discounted_shipping_cost;
                                            $discounted_ship_tos[$ship_to['id']]['original_shipping_cost'] = $original_shipping_cost;
                                            $discounted_ship_tos[$ship_to['id']]['offer_id'] = $offer['id'];
                                        }
                                    }
                                    // else this offer is not valid, so if an upsell has not already been added for it,
                                    // and there is an upsell, then add upsell
                                } else if ((isset($upsell_offers[$offer['id']]) == false) && (check_upsell($offer, $ship_to['id']) == true)) {
                                    $upsell_offers[$offer['id']] = $offer;
                                }
                            }
                            // else this offer is not valid, so if an upsell has not already been added for it,
                            // and there is an upsell, then add upsell
                        } else if ((isset($upsell_offers[$offer['id']]) == false) && (check_upsell($offer) == true)) {
                            $upsell_offers[$offer['id']] = $offer;
                        }
                    }
                    break;
            }
        }
    }
    // if there is an order discount, then add order discount to order
    if ($best_order_discount_offer_id != 0) {
        // The winning discount was worked out while the loop was still running,
        // and the loop can lower an item's price after that - a gift, a product
        // discount. So it is worked out once more against the cart as it now
        // stands: a percentage of what is left, and never more than what is
        // left, because an order total is not a negative number.
        $final_subtotal = get_order_subtotal();
        $tier_percentage = pg_offer_tier_percentage($best_order_discount_offer_id, $final_subtotal);
        if ($tier_percentage > 0) {
            $best_order_discount = round($final_subtotal * ($tier_percentage / 100));
        } else if ($best_order_discount_percentage != 0) {
            $best_order_discount = round($final_subtotal * ($best_order_discount_percentage / 100));
        } else if ($best_order_discount_amount != 0) {
            $best_order_discount = $best_order_discount_amount;
        }
        if ($best_order_discount > $final_subtotal) {
            $best_order_discount = $final_subtotal;
        }
        $query = "UPDATE orders SET discount_offer_id = '" . $best_order_discount_offer_id . "' WHERE id = '" . ($_SESSION['ecommerce']['order_id'] ?? '') . "'";
        $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
        $_SESSION['ecommerce']['order_discount'] = $best_order_discount;
        // else there is not an order discount, so if there was before, then remove it
    } else if (isset($_SESSION['ecommerce']['order_discount']) == true) {
        // remove order discount until we find out below if there is an order discount
        $query = "UPDATE orders SET discount_offer_id = '0' WHERE id = '" . ($_SESSION['ecommerce']['order_id'] ?? '') . "'";
        $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
        unset($_SESSION['ecommerce']['order_discount']);
    }
    // if shipping is on, then loop through discounted ship tos in order to discount them
    if (ECOMMERCE_SHIPPING == true) {
        foreach ($discounted_ship_tos as $discounted_ship_to) {
            $query = "UPDATE ship_tos

                SET

                    shipping_cost = '" . $discounted_ship_to['shipping_cost'] . "',

                    original_shipping_cost = '" . $discounted_ship_to['original_shipping_cost'] . "',

                    offer_id = '" . $discounted_ship_to['offer_id'] . "'

                WHERE id = '" . $discounted_ship_to['id'] . "'";
            $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
        }
    }
    return array(
        'pending_offers' => $pending_offers,
        'upsell_offers' => $upsell_offers
    );
}
// "Almost there" for a product-group condition: the cart already holds the
// required count minus the trigger. Same shape as the offer_rules product
// check, so an offer that names a group nudges the customer the same way one
// that lists the products does.
function pg_offer_group_upsell_met($offer_id, $trigger_quantity, $ship_to_id = 0)
{
    $conditions = pg_offer_conditions($offer_id);
    if (!$conditions) {
        return true;
    }
    require_once(PG_FUNCTIONS_DIR . '/check_for_products_in_cart.php');
    foreach ($conditions as $condition) {
        if ($condition['type'] != 'product group') {
            continue;
        }
        $group_ids = array();
        foreach (explode(',', (string) $condition['text_value']) as $group_id) {
            $group_id = (int) $group_id;
            if ($group_id > 0) {
                $group_ids[] = $group_id;
            }
        }
        if (!$group_ids) {
            return false;
        }
        $needed = ((int) $condition['int_value'] > 0) ? (int) $condition['int_value'] : 1;
        $needed = $needed - (int) $trigger_quantity;
        // The trigger reaches back further than the condition asks for, so
        // there is nothing left to nudge about: the condition is already met.
        if ($needed < 1) {
            continue;
        }
        $product_ids = (array) db_values("SELECT DISTINCT product FROM products_groups_xref

            WHERE product_group IN ('" . implode("', '", array_map('e', $group_ids)) . "')");
        if (!$product_ids) {
            return false;
        }
        $products = array();
        foreach ($product_ids as $product_id) {
            $products[] = array('id' => (int) $product_id);
        }
        if (check_for_products_in_cart(array(
            'products'  => $products,
            'quantity'  => $needed,
            'recipient' => array('id' => $ship_to_id))) == false) {
            return false;
        }
    }
    return true;
}

function check_upsell($offer, $ship_to_id = 0, $subtotal = '')
{
    // if upsell is disabled for offer, then return false
    if (!$offer['upsell']) {
        return false;
    }
    // A condition about the customer is not something the cart can fix, so an
    // offer they do not qualify for is never offered as an up-sell.
    if (pg_offer_customer_conditions_met($offer['id']) == false) {
        return false;
    }
    if (pg_offer_group_upsell_met($offer['id'], $offer['upsell_trigger_quantity'], $ship_to_id) == false) {
        return false;
    }
    // if a subtotal was not passed then get dynamic subtotal
    if ($subtotal === '') {
        $subtotal = get_order_subtotal();
    }
    $required_products = db_items("

        SELECT product_id AS id FROM offer_rules_products_xref

        WHERE offer_rule_id = '" . e($offer['offer_rule_id']) . "'");
    require_once(PG_FUNCTIONS_DIR . '/check_for_products_in_cart.php');
    // if required subtotal is valid and required product and quantity is valid with triggers taken into account,
    // then offer should be displayed as an upsell offer
    if (
        (($offer['upsell_trigger_subtotal'] == 0) or ($offer['offer_rule_required_subtotal'] == 0) or ($subtotal >= $offer['offer_rule_required_subtotal'] - $offer['upsell_trigger_subtotal'])) and (!$offer['upsell_trigger_quantity'] or !$offer['offer_rule_required_quantity'] or !$required_products or check_for_products_in_cart(array(
            'products' => $required_products,
            'quantity' => $offer['offer_rule_required_quantity'] - $offer['upsell_trigger_quantity'],
            'recipient' => array(
                'id' => $ship_to_id
            )
        )))
    ) {
        return true;
    }
    return false;
}
// get the best offer for offers that share the same offer code
// we determine the best offer by finding the offer which gives the largest discount
function get_best_offer_id($offer_code, $scope = '')
{
    $sql_scope = "";
    // if scope is defined, then prepare to only get offers for that scope
    if ($scope != '') {
        $sql_scope = "AND (offers.scope = '" . $scope . "')";
    }
    // Get all active offers for the offer code so we can determine which is the best.
    $query = "SELECT

            offers.id,

            offers.scope,

            offers.multiple_recipients

        FROM offers

        WHERE

            (offers.status = 'enabled')

            AND (offers.start_date <= CURRENT_DATE())

            AND (CURRENT_DATE() <= offers.end_date)

            AND (offers.code = '" . escape($offer_code) . "')

            " . $sql_scope;
    $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
    $offers = array();
    while ($row = mysqli_fetch_assoc($result)) {
        $offers[] = $row;
    }
    // if there is one offer, it is the best offer, so return id
    if (count($offers) == 1) {
        return $offers[0]['id'];
    }
    $best_order_discount = 0;
    $best_order_discount_offer_id = 0;
    // get order items so we can prepare array for ship tos and order items so we can loop through them later to determine discounts
    $query = "SELECT

            order_items.id,

            order_items.product_id,

            order_items.added_by_offer,

            order_items.ship_to_id,

            order_items.quantity,

            products.price AS product_price,

            ship_tos.complete,

            ship_tos.shipping_method_id,

            ship_tos.shipping_cost,

            ship_tos.original_shipping_cost

        FROM order_items

        LEFT JOIN ship_tos ON order_items.ship_to_id = ship_tos.id

        LEFT JOIN products ON order_items.product_id = products.id

        WHERE order_items.order_id = '" . ($_SESSION['ecommerce']['order_id'] ?? '') . "'";
    $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
    $ship_tos = array();
    while ($row = mysqli_fetch_assoc($result)) {
        // if this ship to has not been added yet, then add it
        if (isset($ship_tos[$row['ship_to_id']]) == false) {
            $ship_tos[$row['ship_to_id']] = array();
            $ship_tos[$row['ship_to_id']]['id'] = $row['ship_to_id'];
            $ship_tos[$row['ship_to_id']]['complete'] = $row['complete'];
            $ship_tos[$row['ship_to_id']]['shipping_method_id'] = $row['shipping_method_id'];
            $ship_tos[$row['ship_to_id']]['shipping_cost'] = $row['shipping_cost'];
            $ship_tos[$row['ship_to_id']]['original_shipping_cost'] = $row['original_shipping_cost'];
            $ship_tos[$row['ship_to_id']]['order_items'] = array();
        }
        $ship_tos[$row['ship_to_id']]['order_items'][] = $row;
    }
    $largest_discount_amount = 0;
    $largest_number_of_added_products = 0;
    $best_offer_id = 0;
    // loop through active offers so we can determine which offer gives the largest discount
    foreach ($offers as $offer) {
        // get offer actions for this offer
        $query = "SELECT

                offer_actions.id,

                offer_actions.type,

                offer_actions.discount_order_amount,

                offer_actions.discount_order_percentage,

                offer_actions.discount_product_product_id,

                offer_actions.discount_product_group_id,

                offer_actions.discount_product_target,

                offer_actions.discount_product_amount,

                offer_actions.discount_product_percentage,

                offer_actions.add_product_product_id,

                offer_actions.add_product_quantity,

                offer_actions.add_product_discount_amount,

                offer_actions.add_product_discount_percentage,

                offer_actions.discount_shipping_percentage,

                discount_products.id AS discount_product_id,

                discount_products.shippable AS discount_product_shippable,

                add_products.id AS add_product_id,

                add_products.shippable AS add_product_shippable,

                add_products.price AS add_product_price

            FROM offers_offer_actions_xref

            LEFT JOIN offer_actions ON offers_offer_actions_xref.offer_action_id = offer_actions.id

            LEFT JOIN products AS discount_products ON offer_actions.discount_product_product_id = discount_products.id

            LEFT JOIN products AS add_products ON offer_actions.add_product_product_id = add_products.id

            WHERE offers_offer_actions_xref.offer_id = '" . $offer['id'] . "'";
        $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
        $offer_actions = array();
        while ($row = mysqli_fetch_assoc($result)) {
            $offer_actions[] = $row;
        }
        $discounted_order_items = array();
        // get subtotal
        // we need the subtotal because we need to keep track of how the subtotal is changed as order items are discounted
        // in order for us to determine if offers are valid
        $subtotal = get_order_subtotal();
        // loop through the offer actions in order to determine order item discounts
        // we check for order item discounts first, because they will affect the subtotal which could affect the validity of other types of offers
        foreach ($offer_actions as $offer_action) {
            // if this offer action discounts order items, then continue
            if (($offer_action['type'] == 'discount product') && (pg_offer_action_has_product_target($offer_action)) && (($offer_action['discount_product_amount'] != 0) || ($offer_action['discount_product_percentage'] != 0))) {
                $scope = '';
                // if this offer should be applied at the order level then remember that
                if ((ECOMMERCE_SHIPPING == false) || (ECOMMERCE_RECIPIENT_MODE == 'single recipient') || ($offer['scope'] == 'order') || (pg_offer_action_target_shippable($offer_action) == 0)) {
                    $scope = 'order';
                    // else this offer should be applied at the recipient level, so remember that
                } else {
                    $scope = 'recipient';
                }
                // if the scope is order and the offer is valid or the scope is recipient,
                // then determine discount amount
                if ((($scope == 'order') && (validate_offer($offer['id'], 0, $subtotal) == true)) || ($scope == 'recipient')) {
                    // loop through ship tos in order to loop through order items
                    foreach ($ship_tos as $ship_to) {
                        // if the scope is recipient and the offer is valid for this recipient or the scope is order,
                        // then determine discount amount
                        if ((($scope == 'recipient') && (validate_offer($offer['id'], $ship_to['id'], $subtotal) == true)) || ($scope == 'order')) {
                            // loop through order items for this ship to in order to determine discount amount
                            foreach ($ship_to['order_items'] as $order_item) {
                                // if this offer discounts this product
                                // and this order item was not added by an offer,
                                // then determine discount
                                if ((pg_offer_action_targets_item($offer_action, $order_item, ($scope == 'recipient') ? $ship_to['id'] : 0)) && ($order_item['added_by_offer'] == 0)) {
                                    $discounted_price = 0;
                                    // if discount is by amount, then get discounted price
                                    if ($offer_action['discount_product_amount']) {
                                        $discounted_price = $order_item['product_price'] - $offer_action['discount_product_amount'];
                                        // else discount is by percentage, so get discounted price
                                    } else {
                                        $discounted_price = $order_item['product_price'] - ($order_item['product_price'] * ($offer_action['discount_product_percentage'] / 100));
                                    }
                                    // if the discounted price is less than 0, then set discounted price to 0
                                    if ($discounted_price < 0) {
                                        $discounted_price = 0;
                                    }
                                    // if this offer is going to discount the product more than any previous offer so far, use this offer
                                    if ((isset($discounted_order_items[$order_item['id']]) == false) || ($discounted_price < $discounted_order_items[$order_item['id']]['price'])) {
                                        // if this is the first offer that has discounted this order item, then prepare array
                                        if (isset($discounted_order_items[$order_item['id']]) == false) {
                                            $discounted_order_items[$order_item['id']] = array();
                                        }
                                        $discounted_order_items[$order_item['id']]['price'] = $discounted_price;
                                        // add previous discount for this order item (if one exists) back to subtotal
                                        $subtotal = $subtotal + ($discounted_order_items[$order_item['id']]['discount'] ?? 0);
                                        // set new discount for this order item
                                        $discounted_order_items[$order_item['id']]['discount'] = ($order_item['product_price'] - $discounted_price) * $order_item['quantity'];
                                        // reduce subtotal by new discount for this order item
                                        // we do this so when we validate other offers, we will be validating with the most recent subtotal that we know about
                                        $subtotal = $subtotal - $discounted_order_items[$order_item['id']]['discount'];
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
        $best_order_discount = 0;
        $add_product_discount = 0;
        $discounted_ship_tos = array();
        $number_of_added_products = 0;
        // loop through the offer actions in order to determine the discount amount for discount order, add product, and/or discount shipping actions
        foreach ($offer_actions as $offer_action) {
            // determine discount amount differently based on the type of action
            switch ($offer_action['type']) {
                case 'discount order':
                    // if this action actually discounts the order,
                    // then continue to determine discount amount
                    if (($offer_action['discount_order_amount'] != 0) || ($offer_action['discount_order_percentage'] != 0)) {
                        // if this offer is valid,
                        // then determine discount amount
                        if (validate_offer($offer['id'], 0, $subtotal) == true) {
                            $order_discount = 0;
                            // if discount is by amount, get order discount
                            if ($offer_action['discount_order_amount'] != 0) {
                                $order_discount = $offer_action['discount_order_amount'];
                                // else discount is by percentage, so get order discount
                            } else {
                                $percentage = pg_offer_tier_percentage($offer['id'], $subtotal);
                                if ($percentage <= 0) {
                                    $percentage = $offer_action['discount_order_percentage'];
                                }
                                $order_discount = round($subtotal * ($percentage / 100));
                            }
                            // if the order discount is greater than the subtotal, then set the order discount to the subtotal
                            if ($order_discount > $subtotal) {
                                $order_discount = $subtotal;
                            }
                            // if this is the best order discount, then remember that
                            if ($order_discount > $best_order_discount) {
                                $best_order_discount = $order_discount;
                            }
                        }
                    }
                    break;
                case 'add product':
                    // If this action actually adds a product, then continue to determine discount
                    // amount and number of products added.
                    if (($offer_action['add_product_id'] != '') && ($offer_action['add_product_quantity'] != 0)) {
                        // if this offer should be applied at the order level then continue to determine discount amount
                        if ((ECOMMERCE_SHIPPING == false) || (ECOMMERCE_RECIPIENT_MODE == 'single recipient') || ($offer['scope'] == 'order') || ($offer_action['add_product_shippable'] == 0)) {
                            // if this offer is valid at the order level, then continue to to determine discount amount
                            if (validate_offer($offer['id'], 0, $subtotal) == true) {
                                // If the product is discounted then get discount.
                                if ($offer_action['add_product_discount_amount'] or $offer_action['add_product_discount_percentage']) {
                                    $discounted_price = 0;
                                    // if discount is by amount, then get discounted price
                                    if ($offer_action['add_product_discount_amount']) {
                                        $discounted_price = $offer_action['add_product_price'] - $offer_action['add_product_discount_amount'];
                                        // else discount is by percentage, so get discounted price
                                    } else {
                                        $discounted_price = $offer_action['add_product_price'] - ($offer_action['add_product_price'] * ($offer_action['add_product_discount_percentage'] / 100));
                                    }
                                    // if the discounted price is less than 0, then set discounted price to 0
                                    if ($discounted_price < 0) {
                                        $discounted_price = 0;
                                    }
                                    // add discount for this added product to add product discount (multiply discount by quantity)
                                    $add_product_discount = $add_product_discount + (($offer_action['add_product_price'] - $discounted_price) * $offer_action['add_product_quantity']);
                                }
                                $number_of_added_products += $offer_action['add_product_quantity'];
                            }
                            // else this offer should be applied at the recipient level, so continue to determine discount amount for added product
                        } else {
                            // loop through recipients in order to determine the discount amount for the added product
                            foreach ($ship_tos as $ship_to) {
                                // if offer is valid for this recipient then continue to determine the discount amount for the added product
                                if (validate_offer($offer['id'], $ship_to['id'], $subtotal) == true) {
                                    // If the product is discounted then get discount.
                                    if ($offer_action['add_product_discount_amount'] or $offer_action['add_product_discount_percentage']) {
                                        $discounted_price = 0;
                                        // if discount is by amount, then get discounted price
                                        if ($offer_action['add_product_discount_amount']) {
                                            $discounted_price = $offer_action['add_product_price'] - $offer_action['add_product_discount_amount'];
                                            // else discount is by percentage, so get discounted price
                                        } else {
                                            $discounted_price = $offer_action['add_product_price'] - ($offer_action['add_product_price'] * ($offer_action['add_product_discount_percentage'] / 100));
                                        }
                                        // if the discounted price is less than 0, then set discounted price to 0
                                        if ($discounted_price < 0) {
                                            $discounted_price = 0;
                                        }
                                        // add discount for this added product to discount amount (multiply discount by quantity)
                                        $add_product_discount = $add_product_discount + (($offer_action['add_product_price'] - $discounted_price) * $offer_action['add_product_quantity']);
                                    }
                                    $number_of_added_products += $offer_action['add_product_quantity'];
                                    // if this offer is not allowed to be applied to multiple recipients,
                                    // then we don't need to loop through any more recipients so break out of the loop
                                    if ($offer['multiple_recipients'] == 0) {
                                        break;
                                    }
                                }
                            }
                        }
                    }
                    break;
                case 'discount shipping':
                    // if shipping is on and there is an actual discount shipping percentage, then continue to determine discount amount
                    if ((ECOMMERCE_SHIPPING == true) && ($offer_action['discount_shipping_percentage'] != 0)) {
                        $scope = '';
                        // if this offer should be applied at the order level then remember that
                        if ((ECOMMERCE_RECIPIENT_MODE == 'single recipient') || ($offer['scope'] == 'order')) {
                            $scope = 'order';
                            // else this offer should be applied at the recipient level, so remember that
                        } else {
                            $scope = 'recipient';
                        }
                        // if the scope is order and the offer is valid or the scope is recipient,
                        // then continue to determine discount amount
                        if ((($scope == 'order') && (validate_offer($offer['id'], 0, $subtotal) == true)) || ($scope == 'recipient')) {
                            // loop through the ship tos in order to determine discount amount
                            foreach ($ship_tos as $ship_to) {
                                // if this is a real ship to,
                                // and the ship to is complete,
                                // and if the scope is recipient and the offer is valid for this recipient,
                                // or the scope is order,
                                // then determine discount amount
                                if (($ship_to['id'] != 0) && ($ship_to['complete'] == 1) && ((($scope == 'recipient') && (validate_offer($offer['id'], $ship_to['id'], $subtotal) == true)) || ($scope == 'order'))) {
                                    // check if this offer and action is valid for the shipping method
                                    $query = "SELECT offer_action_id

                                        FROM offer_actions_shipping_methods_xref

                                        WHERE

                                            (offer_action_id = '" . $offer_action['id'] . "')

                                            AND (shipping_method_id = '" . $ship_to['shipping_method_id'] . "')";
                                    $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
                                    // if this offer and action is valid for the shipping method then determine discount amount
                                    if (mysqli_num_rows($result) != 0) {
                                        // if there is an original shipping cost, then use that for the original shipping cost
                                        if ($ship_to['original_shipping_cost'] != 0) {
                                            $original_shipping_cost = $ship_to['original_shipping_cost'];
                                            // else there is not an original shipping cost, so use shipping cost for original shipping cost
                                        } else {
                                            $original_shipping_cost = $ship_to['shipping_cost'];
                                        }
                                        $discounted_shipping_cost = $original_shipping_cost - ($original_shipping_cost * ($offer_action['discount_shipping_percentage'] / 100));
                                        // if this offer is going to discount the ship to more than any previous offer so far, use this offer
                                        if ((isset($discounted_ship_tos[$ship_to['id']]) == false) || ($discounted_shipping_cost < $discounted_ship_tos[$ship_to['id']]['shipping_cost'])) {
                                            // if this is the first offer that has discounted this ship to, then prepare array
                                            if (isset($discounted_ship_tos[$ship_to['id']]) == false) {
                                                $discounted_ship_tos[$ship_to['id']] = array();
                                            }
                                            $discounted_ship_tos[$ship_to['id']]['shipping_cost'] = $discounted_shipping_cost;
                                            // set new discount for this order item
                                            $discounted_ship_tos[$ship_to['id']]['discount'] = $original_shipping_cost - $discounted_shipping_cost;
                                        }
                                    }
                                }
                            }
                        }
                    }
                    break;
            }
        }
        // start discount amount off with best order discount for this offer
        $discount_amount = $best_order_discount;
        // loop through the discounted order items for this offer in order to add to discount amount
        foreach ($discounted_order_items as $discounted_order_item) {
            $discount_amount += $discounted_order_item['discount'];
        }
        // add the add product discount to the discount amount
        $discount_amount += $add_product_discount;
        // loop through the discounted ship tos for this offer in order to add to discount amount
        foreach ($discounted_ship_tos as $discounted_ship_to) {
            $discount_amount += $discounted_ship_to['discount'];
        }
        // If this offer is the first offer in this group or if the discount amount for this offer
        // is the largest discount amount, or if this offer has the same discount however it adds
        // more products, then remember that this is the best offer so far.  We take into account
        // added products, because sometimes an offer will add a free product, where the price of
        // the product is already zero, and is not discounted by the offer.  Therefore, we want
        // to give some credit to the offer as possibly being the best offer if it adds products.
        // A discount has the highest importance, and the number of added products has a
        // secondary importance.
        if (($best_offer_id == 0) or ($discount_amount > $largest_discount_amount) or ($discount_amount == $largest_discount_amount and $number_of_added_products > $largest_number_of_added_products)) {
            $largest_discount_amount = $discount_amount;
            $largest_number_of_added_products = $number_of_added_products;
            $best_offer_id = $offer['id'];
        }
    }
    return $best_offer_id;
}
function get_best_shipping_discount_offer($ship_to_id, $shipping_method_id)
{
    // get special offer code that shopper might have entered
    $query = "SELECT special_offer_code FROM orders WHERE id = '" . ($_SESSION['ecommerce']['order_id'] ?? '') . "'";
    $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
    $row = mysqli_fetch_assoc($result);

    // $row is null when there is no order in the session yet.
    $special_offer_code = isset($row['special_offer_code']) ? $row['special_offer_code'] : '';
    $offer_code = '';
    // if there is a special offer code for this order, then get offer code because the special offer code might just be a key code
    if ($special_offer_code != '') {
        $offer_code = get_offer_code_for_special_offer_code($special_offer_code);
    }
    // get all active offers so we can find the best shipping discount
    $query = "SELECT

            offers.id,

            offers.code,

            offers.only_apply_best_offer

        FROM offers

        WHERE

            (offers.status = 'enabled')

            AND (offers.start_date <= CURRENT_DATE())

            AND (CURRENT_DATE() <= offers.end_date)

            AND ((offers.require_code = 0) OR (offers.code = '" . escape($offer_code) . "'))

        ORDER BY offers.code ASC";
    $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
    $offers = array();
    while ($row = mysqli_fetch_assoc($result)) {
        $offers[] = $row;
    }
    // prepare to get best offers (including non-competing offers and competing offers that are the best offer)
    // competing offers are offers that share the same code but where only the best offer will be applied
    $best_offers = array();
    // create array to store the best offer id's for offer codes, so we don't have to look up the best offer multiple times
    $best_offer_ids = array();
    // loop through the offers in order to get best offers
    foreach ($offers as $key => $offer) {
        $previous_offer_code = '';
        // if this is not the first offer, then get previous offer code
        if ($key != 0) {
            $previous_offer_code = $offers[$key - 1]['code'];
        }
        $next_offer_code = '';
        // if this is not the last offer, then get next offer code
        if ($key != (count($offers) - 1)) {
            $next_offer_code = $offers[$key + 1]['code'];
        }
        // if this is not the first offer and the previous offer had the same code,
        // or if this is not the last offer and the next offer had the same code,
        // and if this offer is set so only the best offer is applied,
        // then this is a competing offer, so determine if offer is the best offer
        if ((($key != 0) && ($offer['code'] == $previous_offer_code) || ($key != (count($offers) - 1)) && ($offer['code'] == $next_offer_code)) && ($offer['only_apply_best_offer'] == 1)) {
            $best_offer_id = '';
            // if the best offer id has already been found for this offer code, then get it
            if (isset($best_offer_ids[$offer['code']]) == true) {
                $best_offer_id = $best_offer_ids[$offer['code']];
                // else the best offer id has not already been found for this offer code, so get it
                // and then remember it by adding to array
            } else {
                $best_offer_id = get_best_offer_id($offer['code']);
                $best_offer_ids[$offer['code']] = $best_offer_id;
            }
            // if this offer is the best offer, then add it to array
            if ($offer['id'] == $best_offer_id) {
                $best_offers[] = $offer;
            }
            // else this is not a competing offer, so it is a best offer, so add it to array
        } else {
            $best_offers[] = $offer;
        }
    }
    // set active offers to all the best offers
    $offers = $best_offers;
    $best_offer_id = 0;
    $best_discount_shipping_percentage = 0;
    // loop through all active offers so we can find the best shipping discount
    foreach ($offers as $offer) {
        // if the offer is valid for this recipient, then continue to check if this offer has the best shipping discount
        if (validate_offer($offer['id'], $ship_to_id) == true) {
            // get offer actions for this offer that discount shipping and that are valid for this shipping method
            $query = "SELECT offer_actions.discount_shipping_percentage

                FROM offers_offer_actions_xref

                LEFT JOIN offer_actions ON offers_offer_actions_xref.offer_action_id = offer_actions.id

                LEFT JOIN offer_actions_shipping_methods_xref ON offer_actions.id = offer_actions_shipping_methods_xref.offer_action_id

                WHERE

                    (offers_offer_actions_xref.offer_id = '" . $offer['id'] . "')

                    AND (offer_actions.type = 'discount shipping')

                    AND (offer_actions.discount_shipping_percentage != '0')

                    AND (offer_actions_shipping_methods_xref.shipping_method_id = '" . escape($shipping_method_id) . "')";
            $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
            $offer_actions = array();
            while ($row = mysqli_fetch_assoc($result)) {
                $offer_actions[] = $row;
            }
            // loop through the offer actions in order to find the best shipping discount
            foreach ($offer_actions as $offer_action) {
                // if this offer provides the best shipping discount so far then remember that
                if ($offer_action['discount_shipping_percentage'] > $best_discount_shipping_percentage) {
                    $best_offer_id = $offer['id'];
                    $best_discount_shipping_percentage = $offer_action['discount_shipping_percentage'];
                }
            }
        }
    }
    // if an offer was found, then return it
    if ($best_discount_shipping_percentage > 0) {
        return array(
            'id' => $best_offer_id,
            'discount_shipping_percentage' => $best_discount_shipping_percentage
        );
        // else an offer was not found, so return false
    } else {
        return false;
    }
}
// create function that will check if there are any active shipping discounts
// we do this in order to improve performance by not running unnecessary queries
function check_if_active_shipping_discount_offer_exists()
{
    // get special offer code that shopper might have entered
    $query = "SELECT special_offer_code FROM orders WHERE id = '" . ($_SESSION['ecommerce']['order_id'] ?? '') . "'";
    $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
    $row = mysqli_fetch_assoc($result);

    // $row is null when there is no order in the session yet.
    $special_offer_code = isset($row['special_offer_code']) ? $row['special_offer_code'] : '';
    $offer_code = '';
    // if there is a special offer code for this order, then get offer code because the special offer code might just be a key code
    if ($special_offer_code != '') {
        $offer_code = get_offer_code_for_special_offer_code($special_offer_code);
    }
    $query = "SELECT COUNT(offers_offer_actions_xref.offer_action_id)

        FROM offers_offer_actions_xref

        LEFT JOIN offers ON offers_offer_actions_xref.offer_id = offers.id

        LEFT JOIN offer_actions ON offers_offer_actions_xref.offer_action_id = offer_actions.id

        WHERE

            (offers.status = 'enabled')

            AND (offers.start_date <= CURRENT_DATE())

            AND (CURRENT_DATE() <= offers.end_date)

            AND ((offers.require_code = 0) OR (offers.code = '" . escape($offer_code) . "'))

            AND (offer_actions.type = 'discount shipping')

            AND (offer_actions.discount_shipping_percentage != '0')";
    $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
    $row = mysqli_fetch_row($result);
    // if there is at least one active shipping discount offer, then return that
    if ($row[0] > 0) {
        return true;
        // else there is not an active shipping discount offer, so return that
    } else {
        return false;
    }
}
function add_pending_offers($liveform)
{
    // if pending offers form was submitted
    if (!empty($_POST['pending_offers'])) {
        // loop through all submitted fields in order to determine if a pending offer was requested to be added
        foreach ($_POST as $key => $value) {
            // if the name of the field starts with "add_pending_offer_",
            // then a pending offer was requested to be added, so continue to add pending offers
            if (mb_substr($key, 0, 18) == 'add_pending_offer_') {
                // get the offer id and offer action id from the field name
                $offer_id_and_offer_action_id = mb_substr($key, 18);
                $offer_id_and_offer_action_id_parts = explode('_', $offer_id_and_offer_action_id);
                $offer_id = $offer_id_and_offer_action_id_parts[0];
                $offer_action_id = $offer_id_and_offer_action_id_parts[1];
                // get special offer code for this order
                $query = "SELECT special_offer_code FROM orders WHERE id = '" . ($_SESSION['ecommerce']['order_id'] ?? '') . "'";
                $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
                $row = mysqli_fetch_assoc($result);
                $special_offer_code = $row['special_offer_code'] ?? '';
                $offer_code = '';
                // if there is a special offer code for this order, then get offer code because the special offer code might just be a key code
                if ($special_offer_code != '') {
                    $offer_code = get_offer_code_for_special_offer_code($special_offer_code);
                }
                // get offer and action info for the pending offer that the customer requested to add
                $query = "SELECT

                        offer_actions.id,

                        offer_actions.add_product_product_id,

                        offer_actions.add_product_quantity,

                        offer_actions.add_product_discount_amount,

                        offer_actions.add_product_discount_percentage,

                        offers.id AS offer_id,

                        offers.scope AS offer_scope,

                        offers.multiple_recipients AS offer_multiple_recipients,

                        add_products.shippable AS add_product_shippable,

                        add_products.price AS add_product_price,

                        add_products.name AS add_product_name

                    FROM offers_offer_actions_xref

                    LEFT JOIN offer_actions ON offers_offer_actions_xref.offer_action_id = offer_actions.id

                    LEFT JOIN offers ON offers_offer_actions_xref.offer_id = offers.id

                    LEFT JOIN products AS add_products ON offer_actions.add_product_product_id = add_products.id

                    WHERE

                        (offer_actions.id = '" . escape($offer_action_id) . "')

                        AND (offers.id = '" . escape($offer_id) . "')

                        AND (offers.status = 'enabled')

                        AND (offers.start_date <= CURRENT_DATE())

                        AND (CURRENT_DATE() <= offers.end_date)

                        AND ((offers.require_code = 0) OR (offers.code = '" . escape($offer_code) . "'))

                        AND (offer_actions.type = 'add product')

                        AND (add_products.id IS NOT NULL)

                        AND (offer_actions.add_product_quantity != '0')";
                $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
                // if an offer was not found
                if (mysqli_num_rows($result) == 0) {
                    $liveform->mark_error('pending_offer', lang('The offer that you tried to add no longer exists or is no longer active.'));
                }
                // if there are no errors so far, continue
                if ($liveform->check_form_errors() == false) {
                    $offer_action = mysqli_fetch_assoc($result);
                    // Only the multi-recipient branch below resolves a recipient; every other
                    // path inserts the item without one.
                    $ship_to_id = 0;
                    $ship_to = '';
                    $add_name = '';
                    // if shipping is on, recipient mode is multi-recipient, and the offer is adding a product that is shippable
                    // make sure that a recipient was selected or entered
                    if ((ECOMMERCE_SHIPPING == true) && (ECOMMERCE_RECIPIENT_MODE == 'multi-recipient') && ($offer_action['add_product_shippable'] == 1)) {
                        $ship_to = $_POST['pending_offer_' . $offer_action['offer_id'] . '_' . $offer_action['id'] . '_ship_to'] ?? '';
                        $add_name = $_POST['pending_offer_' . $offer_action['offer_id'] . '_' . $offer_action['id'] . '_add_name'] ?? '';
                        if ($add_name == lang('or add name')) {
                            $add_name = '';
                        }
                        // Nothing was chosen because the screen had nothing to
                        // ask: the gift belongs to whoever is paying for what
                        // earned it, and the software knows that recipient.
                        if (!$ship_to && !$add_name) {
                            $ship_to_id = get_ship_to_id_for_offer_gift($offer_action['offer_id']);
                            if ($ship_to_id) {
                                $ship_to = (string) db_value("SELECT ship_to_name FROM ship_tos WHERE id = '" . e($ship_to_id) . "'");
                            }
                        }
                        // if ship to was not selected and a name was not entered, then prepare error
                        if (!$ship_to && !$add_name) {
                            $liveform->mark_error('pending_offer_' . $offer_action['offer_id'] . '_' . $offer_action['id'] . '_ship_to', lang('The offer that you attempted to add requires a recipient.'));
                            $liveform->mark_error('pending_offer_' . $offer_action['offer_id'] . '_' . $offer_action['id'] . '_add_name', '');
                            $liveform->assign_field_value('pending_offer_' . $offer_action['offer_id'] . '_' . $offer_action['id'] . '_add_name', lang('or add name'));
                            // else get ship to id
                        } else {
                            if ($add_name) {
                                $ship_to_name = $add_name;
                            } else {
                                $ship_to_name = $ship_to;
                            }
                            // get ship to id for ship to name
                            $query = "SELECT id FROM ship_tos WHERE (order_id = '" . escape(($_SESSION['ecommerce']['order_id'] ?? '')) . "') AND (ship_to_name = '" . escape($ship_to_name) . "')";
                            $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
                            $row = mysqli_fetch_assoc($result);
                            $ship_to_id = $row['id'];
                        }
                    }
                    // if there are no errors so far, continue
                    if ($liveform->check_form_errors() == false) {
                        // if this offer should be applied at the order level then continue to add pending offer
                        if ((ECOMMERCE_SHIPPING == false) || (ECOMMERCE_RECIPIENT_MODE == 'single recipient') || ($offer_action['offer_scope'] == 'order') || ($offer_action['add_product_shippable'] == 0)) {
                            // if offer is valid for order
                            if (validate_offer($offer_action['offer_id']) == true) {
                                // check if this offer and action have already been applied to order
                                $query = "SELECT id

                                    FROM order_items

                                    WHERE

                                        (order_id = '" . ($_SESSION['ecommerce']['order_id'] ?? '') . "')

                                        AND (offer_id = '" . $offer_action['offer_id'] . "')

                                        AND (offer_action_id = '" . $offer_action['id'] . "')";
                                $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
                                // if this offer and action have not already been applied to this order, add pending offer
                                if (mysqli_num_rows($result) == 0) {
                                    $discounted_price = 0;
                                    // if discount is by amount, get discounted price
                                    if ($offer_action['add_product_discount_amount'] != 0) {
                                        $discounted_price = $offer_action['add_product_price'] - $offer_action['add_product_discount_amount'];
                                        // else discount is by percentage, get discounted price
                                    } else {
                                        $discounted_price = $offer_action['add_product_price'] - ($offer_action['add_product_price'] * ($offer_action['add_product_discount_percentage'] / 100));
                                    }
                                    // if product that is being added is also being discounted, prepare to mark order item accordingly
                                    if (($offer_action['add_product_discount_amount'] > 0) || ($offer_action['add_product_discount_percentage'] > 0)) {
                                        $discounted_by_offer = 1;
                                    } else {
                                        $discounted_by_offer = 0;
                                    }
                                    if ((ECOMMERCE_SHIPPING == true) && $offer_action['add_product_shippable']) {
                                        $ship_to_id = create_or_get_ship_to($ship_to, $add_name);
                                    }
                                    $query = "INSERT INTO order_items (

                                            order_id,

                                            ship_to_id,

                                            product_id,

                                            product_name,

                                            quantity,

                                            price,

                                            offer_id,

                                            offer_action_id,

                                            added_by_offer,

                                            discounted_by_offer

                                        ) VALUES (

                                            '" . ($_SESSION['ecommerce']['order_id'] ?? '') . "',

                                            '" . (int) $ship_to_id . "',

                                            '" . $offer_action['add_product_product_id'] . "',

                                            '" . escape($offer_action['add_product_name']) . "',

                                            '" . $offer_action['add_product_quantity'] . "',

                                            '$discounted_price',

                                            '" . $offer_action['offer_id'] . "',

                                            '" . $offer_action['id'] . "',

                                            1,

                                            $discounted_by_offer)";
                                    $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
                                    $order_item_id = mysqli_insert_id(db::$con);
                                    // if there is a ship to for this order item
                                    if ($ship_to_id) {
                                        // get ship to information
                                        $query = "SELECT

                                                complete,

                                                country,

                                                state

                                            FROM ship_tos

                                            WHERE id = '" . escape($ship_to_id) . "'";
                                        $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
                                        $row = mysqli_fetch_assoc($result);
                                        $complete = $row['complete'];
                                        $country_code = $row['country'];
                                        $state_code = $row['state'];
                                        // if ship to is complete, then do some checks
                                        if ($complete == 1) {
                                            // if order item is valid for destination and arrival date, then update shipping for ship to
                                            if ((validate_product_for_destination($offer_action['add_product_product_id'], $country_code, $state_code) == true) && (validate_order_item_for_arrival_date($order_item_id) == true)) {
                                                require_once(PG_FUNCTIONS_DIR . '/shipping.php');
                                                update_shipping_cost_for_ship_to($ship_to_id);
                                                // else there are problems, so mark ship to as incomplete
                                            } else {
                                                $query = "UPDATE ship_tos SET complete = 0 WHERE id = '" . escape($ship_to_id) . "'";
                                                $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
                                            }
                                        }
                                    }
                                }
                            }
                            // else this offer should be applied at the recipient level, so continue to add pending offer
                        } else {
                            // check if this offer and action have already been applied to order
                            $query = "SELECT id

                                FROM order_items

                                WHERE

                                    (order_id = '" . ($_SESSION['ecommerce']['order_id'] ?? '') . "')

                                    AND (offer_id = '" . $offer_action['offer_id'] . "')

                                    AND (offer_action_id = '" . $offer_action['id'] . "')";
                            $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
                            // if offer has not already been applied to order or offer is allowed to be applied to multiple recipients, then continue
                            if ((mysqli_num_rows($result) == 0) || ($offer_action['offer_multiple_recipients'] == 1)) {
                                // if offer is valid for this recipient
                                if (validate_offer($offer_action['offer_id'], $ship_to_id) == true) {
                                    // check if offer and action have already been applied to this recipient
                                    $query = "SELECT id

                                        FROM order_items

                                        WHERE

                                            (order_id = '" . ($_SESSION['ecommerce']['order_id'] ?? '') . "')

                                            AND (ship_to_id = '$ship_to_id')

                                            AND (offer_id = '" . $offer_action['offer_id'] . "')

                                            AND (offer_action_id = '" . $offer_action['id'] . "')";
                                    $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
                                    // if offer has not already been applied to this recipient, then add pending offer
                                    if (mysqli_num_rows($result) == 0) {
                                        $discounted_price = 0;
                                        // if discount is by amount, get discounted price
                                        if ($offer_action['add_product_discount_amount'] != 0) {
                                            $discounted_price = $offer_action['add_product_price'] - $offer_action['add_product_discount_amount'];
                                            // else discount is by percentage, get discounted price
                                        } else {
                                            $discounted_price = $offer_action['add_product_price'] - ($offer_action['add_product_price'] * ($offer_action['add_product_discount_percentage'] / 100));
                                        }
                                        // if product that is being added is also being discounted, prepare to mark order item accordingly
                                        if (($offer_action['add_product_discount_amount'] > 0) || ($offer_action['add_product_discount_percentage'] > 0)) {
                                            $discounted_by_offer = 1;
                                        } else {
                                            $discounted_by_offer = 0;
                                        }
                                        $query = "INSERT INTO order_items (

                                                order_id,

                                                ship_to_id,

                                                product_id,

                                                product_name,

                                                quantity,

                                                price,

                                                offer_id,

                                                offer_action_id,

                                                added_by_offer,

                                                discounted_by_offer

                                            ) VALUES (

                                                '" . ($_SESSION['ecommerce']['order_id'] ?? '') . "',

                                                '$ship_to_id',

                                                '" . $offer_action['add_product_product_id'] . "',

                                                '" . escape($offer_action['add_product_name']) . "',

                                                '" . $offer_action['add_product_quantity'] . "',

                                                '$discounted_price',

                                                '" . $offer_action['offer_id'] . "',

                                                '" . $offer_action['id'] . "',

                                                1,

                                                $discounted_by_offer)";
                                        $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
                                        $order_item_id = mysqli_insert_id(db::$con);
                                        // if there is a ship to for this order item
                                        if ($ship_to_id) {
                                            // get ship to information
                                            $query = "SELECT

                                                    complete,

                                                    country,

                                                    state

                                                FROM ship_tos

                                                WHERE id = '" . escape($ship_to_id) . "'";
                                            $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
                                            $row = mysqli_fetch_assoc($result);
                                            $complete = $row['complete'];
                                            $country_code = $row['country'];
                                            $state_code = $row['state'];
                                            // if ship to is complete, then do some checks
                                            if ($complete == 1) {
                                                // if order item is valid for destination and arrival date, then update shipping for ship to
                                                if ((validate_product_for_destination($offer_action['add_product_product_id'], $country_code, $state_code) == true) && (validate_order_item_for_arrival_date($order_item_id) == true)) {
                                                    require_once(PG_FUNCTIONS_DIR . '/shipping.php');
                                                    update_shipping_cost_for_ship_to($ship_to_id);
                                                    // else there are problems, so mark ship to as incomplete
                                                } else {
                                                    $query = "UPDATE ship_tos SET complete = 0 WHERE id = '" . escape($ship_to_id) . "'";
                                                    $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
                header('Location: ' . URL_SCHEME . HOSTNAME . PATH . get_page_name($_POST['page_id']));
                exit();
            }
        }
    }
}
function initialize_order()
{
    // If the visitor already has an order in the session, then use it. We have already verified
    // the status of the order in init.php, to make sure it is still incomplete, so we don't need
    // to do that here.
    if (($_SESSION['ecommerce']['order_id'] ?? '')) {
        // If the user is logged in and not ghosting, then check if we should update user/contact
        // for order, because this function is run when an update is made to the order, and we want
        // to set the user/contact for an order when a user updates the order.
        if (USER_LOGGED_IN and empty($_SESSION['software']['ghost'])) {
            $order = db_item("SELECT user_id, contact_id FROM orders

                WHERE id = '" . e(($_SESSION['ecommerce']['order_id'] ?? '')) . "'");
            // If the order's user/contact info is different from this user, then update
            // user/contact info for order.
            if ($order['user_id'] != USER_ID or $order['contact_id'] != USER_CONTACT_ID) {
                db("UPDATE orders

                    SET

                        user_id = '" . e(USER_ID) . "',

                        contact_id = '" . e(USER_CONTACT_ID) . "'

                    WHERE id = '" . e(($_SESSION['ecommerce']['order_id'] ?? '')) . "'");
            }
        }
        // Otherwise the visitor does not have an active order in session, so create order.
    } else {
        $reference_code = generate_order_reference_code();
        $offline_payment_allowed = '0';
        // if offline payment is on, and if only on specific orders is off, then set the allow offline payment to 1
        if ((ECOMMERCE_OFFLINE_PAYMENT == true) && (ECOMMERCE_OFFLINE_PAYMENT_ONLY_SPECIFIC_ORDERS == false)) {
            $offline_payment_allowed = '1';
        }
        $query = "INSERT INTO orders (

                    order_date,

                    last_modified_timestamp,

                    user_id,

                    contact_id,

                    reference_code,

                    tracking_code,

                    utm_source,

                    utm_medium,

                    utm_campaign,

                    utm_term,

                    utm_content,

                    currency_code,

                    affiliate_code,

                    http_referer,

                    ip_address,

                    offline_payment_allowed)

                 VALUES (

                    UNIX_TIMESTAMP(),

                    UNIX_TIMESTAMP(),

                    '" . e(USER_ID) . "',

                    '" . e(USER_CONTACT_ID) . "',

                    '$reference_code',

                    '" . escape(get_tracking_code()) . "',

                    '" . e($_SESSION['software']['utm_source'] ?? '') . "',

                    '" . e($_SESSION['software']['utm_medium'] ?? '') . "',

                    '" . e($_SESSION['software']['utm_campaign'] ?? '') . "',

                    '" . e($_SESSION['software']['utm_term'] ?? '') . "',

                    '" . e($_SESSION['software']['utm_content'] ?? '') . "',

                    '" . escape(VISITOR_CURRENCY_CODE) . "',

                    '" . escape(get_affiliate_code()) . "',

                    '" . escape($_SESSION['software']['http_referer'] ?? '') . "',

                    IFNULL(INET_ATON('" . escape($_SERVER['REMOTE_ADDR']) . "'), 0),

                    '" . $offline_payment_allowed . "')";
        $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
        // store order id in session
        $_SESSION['ecommerce']['order_id'] = mysqli_insert_id(db::$con);
        // if visitor tracking is on, update visitor record with order information, if visitor has not already created a previous order
        if (VISITOR_TRACKING == true) {
            $query = "UPDATE visitors

                     SET

                        order_id = '" . ($_SESSION['ecommerce']['order_id'] ?? '') . "',

                        order_created = '1',

                        stop_timestamp = UNIX_TIMESTAMP()

                     WHERE (id = '" . $_SESSION['software']['visitor_id'] . "') AND (order_created = '0')";
            $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
        }
    }
}
function get_price_range($product_group_id, $discounted_product_prices)
{
    // in order to resolve an apparent bug in Zend Guard, we had to rename $largest_price to $large_price for some reason
    // assume that there are not any non-donation products, until we find out otherwise
    $non_donation_products_exist = false;
    // assume that there are 0 products in this product group, until we find out otherwise
    // we use this to be able to show when there is a discount when there is only one product in a product group
    $number_of_products = 0;
    // assume that there are no discounted products until we find out otherwise
    // we use this to be able to show when there is a discount when there is only one product in a product group
    $discounted_products_exist = false;
    // initialize variable for storing the original price for a discounted product
    // we use this to be able to show when there is a discount when there is only one product in a product group
    $original_price = 0;
    $product_groups = array();
    // get all product groups in this product group
    $query = "SELECT id

        FROM product_groups

        WHERE

            (parent_id = '" . escape($product_group_id) . "')

            AND (enabled = '1')";
    $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
    // loop through all product groups in order to add them to array
    while ($row = mysqli_fetch_assoc($result)) {
        $product_groups[] = $row;
    }
    // loop through all product groups in order to get price range
    foreach ($product_groups as $product_group) {
        $price_range = get_price_range($product_group['id'], $discounted_product_prices);
        // if non donation products exist in this product, then store that and determine if the smallest and largest prices need to be updated
        if ($price_range['non_donation_products_exist'] == true) {
            $non_donation_products_exist = true;
            // if smallest price is not set or the smallest price of this product group is the smallest price so far, then store smallest price
            if ((isset($smallest_price) == false) || ($price_range['smallest_price'] < $smallest_price)) {
                $smallest_price = $price_range['smallest_price'];
            }
            // if largest price is not set or the largest price of this product group is the largest price so far, then store largest price
            if ((isset($large_price) == false) || ($price_range['largest_price'] > $large_price)) {
                $large_price = $price_range['largest_price'];
            }
        }
        $number_of_products = $number_of_products + $price_range['number_of_products'];
        // if a discounted product exists in this product group, then remember that and store original price
        if ($price_range['discounted_products_exist'] == true) {
            $discounted_products_exist = true;
            $original_price = $price_range['original_price'];
        }
    }
    $products = array();
    // get all non-donation products that are in this product group
    $query = "SELECT

            products.id,

            products.price

        FROM products_groups_xref

        LEFT JOIN products ON products.id = products_groups_xref.product

        WHERE

            (products_groups_xref.product_group = '" . escape($product_group_id) . "')

            AND (products.enabled = '1')

            AND (products.selection_type != 'donation')";
    $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
    // loop through all products in order to add them to array
    while ($row = mysqli_fetch_assoc($result)) {
        $products[] = $row;
    }
    // update the number of products in this product group
    $number_of_products = $number_of_products + count($products);
    // loop through all products in order to get price range
    foreach ($products as $product) {
        // remember that non-donation products exist
        $non_donation_products_exist = true;
        // if this product is discounted by an offer, then remember that discounted products exist, store original price, and set the product price to the discounted price
        if (isset($discounted_product_prices[$product['id']]) == true) {
            $discounted_products_exist = true;
            $original_price = $product['price'];
            $product['price'] = $discounted_product_prices[$product['id']];
        }
        // if smallest price is not set or the price of this product is the smallest price so far, then store smallest price
        if ((isset($smallest_price) == false) || ($product['price'] < $smallest_price)) {
            $smallest_price = $product['price'];
        }
        // if largest price is not set or the price of this product is the largest price so far, then store largest price
        if ((isset($large_price) == false) || ($product['price'] > $large_price)) {
            $large_price = $product['price'];
        }
    }
    // if a smallest price has not been found, then set to 0
    if (isset($smallest_price) == false) {
        $smallest_price = 0;
    }
    // if a largest price has not been found, then set to 0
    if (isset($large_price) == false) {
        $large_price = 0;
    }
    $price_range = array(
        'smallest_price' => $smallest_price,
        'largest_price' => $large_price,
        'non_donation_products_exist' => $non_donation_products_exist,
        'number_of_products' => $number_of_products,
        'discounted_products_exist' => $discounted_products_exist,
        'original_price' => $original_price
    );
    return $price_range;
}
// Gets the price or price range for a product or product group,
// that is ready to be outputted for a catalog page.
function get_price_info($properties)
{
    $item = $properties['item'];
    $discounted_product_prices = $properties['discounted_product_prices'];
    // if this item is a product group, then get price range for all product groups and products in this product group
    if ($item['type'] == 'product group') {
        // if there are non-donation products in product group, then output price range
        if ($item['price_range']['non_donation_products_exist'] == true) {
            // if the smallest price and largest price are the same, then just output a price without a range
            if ($item['price_range']['smallest_price'] == $item['price_range']['largest_price']) {
                // if there is only one product and it is discounted, then prepare to show original price and discounted price
                if (($item['price_range']['number_of_products'] == 1) && ($item['price_range']['discounted_products_exist'] == true)) {
                    return prepare_price_for_output($item['price_range']['original_price'], true, $item['price_range']['smallest_price'], 'html');
                    // else there is more than one product or there are no discounted products, so prepare to just show original price
                } else {
                    // if this product group has a select display type and there are discounted products in it, then prepare to output single price with discounted styling
                    if (($item['display_type'] == 'select') && ($item['price_range']['discounted_products_exist'] == true)) {
                        return '<span class="software_discounted_price">' . prepare_price_for_output($item['price_range']['smallest_price'], false, $discounted_price = '', 'html') . '</span>';
                        // else this product group does not have a select display type or there are no discounted products in it, so prepare to output single price without discounted styling
                    } else {
                        return prepare_price_for_output($item['price_range']['smallest_price'], false, $discounted_price = '', 'html');
                    }
                }
                // else the smallest price and largest price are not the same, so output price range
            } else {
                // if this product group has a select display type and there are discounted products in it, then prepare to output price range with discounted styling
                if (($item['display_type'] == 'select') && ($item['price_range']['discounted_products_exist'] == true)) {
                    return '<span class="software_discounted_price">' . prepare_price_for_output($item['price_range']['smallest_price'], false, $discounted_price = '', 'html', $show_code = false) . ' - ' . prepare_price_for_output($item['price_range']['largest_price'], false, $discounted_price = '', 'html') . '</span>';
                    // else this product group does not have a select display type or there are no discounted products in it, so prepare to output price without discounted styling
                } else {
                    return prepare_price_for_output($item['price_range']['smallest_price'], false, $discounted_price = '', 'html', $show_code = false) . ' - ' . prepare_price_for_output($item['price_range']['largest_price'], false, $discounted_price = '', 'html');
                }
            }
        }
        // else this item is a product, so if product is not a donation, then output product's price
    } elseif ($item['selection_type'] != 'donation') {
        // assume that the product is not discounted, until we find out otherwise
        $discounted = false;
        $discounted_price = '';
        // if the product is discounted, then prepare to show that
        if (isset($discounted_product_prices[$item['id']]) == true) {
            $discounted = true;
            $discounted_price = $discounted_product_prices[$item['id']];
        }
        return prepare_price_for_output($item['price'], $discounted, $discounted_price, 'html');
    }
    return '';
}
function get_number_of_payments_message()
{
    // if credit/debit card is selected as a payment method and a payment gateway is selected, then get message
    if ((ECOMMERCE_CREDIT_DEBIT_CARD == true) && (ECOMMERCE_PAYMENT_GATEWAY != '')) {
        switch (ECOMMERCE_PAYMENT_GATEWAY) {
            case 'Authorize.Net':
                return ' (' . lang(array('string' => '{var:1} or leave blank for no limit', 'vars' => array('1-9999'))) . ')';
            case 'ClearCommerce':
                return ' (2-999)';
            case 'First Data Global Gateway':
                return ' (1-99)';
            case 'PayPal Payflow Pro':
            case 'PayPal Payments Pro':
                return ' (' . lang('leave blank for no limit') . ')';
        }
    }
    return ' (' . lang('leave blank for no limit') . ')';
}
function send_givex_request($type, $gift_card_code, $amount = 0)
{
    $result = array();
    // if the request is a balance check, then prepare for request in a certain way
    if ($type == 'balance') {
        $method = 'balance';
        $params = array(
            '0',
            'G',
            ECOMMERCE_GIVEX_USER_ID,
            $gift_card_code
        );
        // else if the request is a redemption, so prepare for request in a different way
    } elseif ($type == 'redemption') {
        $method = 'secureRedemption';
        $params = array(
            '0',
            'G',
            ECOMMERCE_GIVEX_USER_ID,
            ECOMMERCE_GIVEX_PASSWORD,
            $gift_card_code,
            $amount
        );
    }
    $xml_params = '';
    // loop through the params in order to prepare XML for them
    foreach ($params as $param) {
        $xml_params .= '<param>

                <value>

                    <string>' . h($param) . '</string>

                </value>

            </param>';
    }
    $request = '<?xml version="1.0" ?>

        <methodCall>

            <methodName>' . $method . '</methodName>

            <params>

                ' . $xml_params . '

            </params>

        </methodCall>';
    // initialize cURL
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://' . ECOMMERCE_GIVEX_PRIMARY_HOSTNAME . ':50042');
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Content-Type: text/xml'
    ));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $request);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_FORBID_REUSE, true);
    curl_setopt($ch, CURLOPT_POST, 1);
    // if there is a proxy address, then send cURL request through proxy
    if (PROXY_ADDRESS != '') {
        curl_setopt($ch, CURLOPT_HTTPPROXYTUNNEL, true);
        curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_HTTP);
        curl_setopt($ch, CURLOPT_PROXY, PROXY_ADDRESS);
    }
    // get cURL response
    $response_data = curl_exec($ch);
    $curl_errno = curl_errno($ch);
    $curl_error = curl_error($ch);
    curl_close($ch);
    // if there was a cURL error, then send the same request to the secondary Givex server
    if ($curl_errno != 0) {
        // initialize cURL
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://' . ECOMMERCE_GIVEX_SECONDARY_HOSTNAME . ':50042');
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: text/xml'
        ));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $request);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_FORBID_REUSE, true);
        curl_setopt($ch, CURLOPT_POST, 1);
        // if there is a proxy address, then send cURL request through proxy
        if (PROXY_ADDRESS != '') {
            curl_setopt($ch, CURLOPT_HTTPPROXYTUNNEL, true);
            curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_HTTP);
            curl_setopt($ch, CURLOPT_PROXY, PROXY_ADDRESS);
        }
        // get cURL response
        $response_data = curl_exec($ch);
        $curl_errno = curl_errno($ch);
        $curl_error = curl_error($ch);
        curl_close($ch);
        // if there was also a cURL error for the secondary Givex server, then return error
        if ($curl_errno != 0) {
            $result['curl_errno'] = $curl_errno;
            $result['curl_error'] = $curl_error;
            return $result;
        }
    }
    // get all response values
    preg_match_all('/<string>(.*?)<\/string>/s', $response_data, $matches);
    // put response values in an array
    $response = $matches[1];
    // if there is an error, then return error
    if ((isset($response[1]) == false) || ($response[1] != 0)) {
        // if there is not a standard error, then get error message from first value
        if (isset($response[1]) == false) {
            $result['error_message'] = $response[0];
            // else there is a standard error, so get error message from third value
        } else {
            $result['error_message'] = $response[2];
        }
        return $result;
    }
    // if the request is a balance check, then prepare result in a certain way
    if ($type == 'balance') {
        $result['balance'] = $response[2];
        return $result;
        // else if the request is a redemption, so prepare result in a different way
    } elseif ($type == 'redemption') {
        $result['authorization_number'] = $response[2];
        $result['balance'] = $response[3];
        return $result;
    }
}
function protect_gift_card_code($gift_card_code)
{
    return '************' . mb_substr($gift_card_code, -4);
}
function protect_givex_gift_card_code($gift_card_code)
{
    // first 6 digits + next 5 digits hidden + next variable number of digits up until the last digit + last digit hidden
    return mb_substr($gift_card_code, 0, 6) . '*****' . mb_substr($gift_card_code, 11, -1) . '*';
}
// create function that will be used to get discounted product prices based on products that are discounted by offers
function get_discounted_product_prices()
{
    $discounted_product_prices = array();
    $special_offer_code = '';
    $offer_code = '';
    // if the visitor has an order, then get special offer code from order in database
    if (isset($_SESSION['ecommerce']['order_id']) == true) {
        $query = "SELECT special_offer_code FROM orders WHERE id = '" . ($_SESSION['ecommerce']['order_id'] ?? '') . "'";
        $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
        $row = mysqli_fetch_assoc($result);
        $special_offer_code = $row['special_offer_code'] ?? '';
        // else the visitor does not have an order, so if there is a special offer code in the visitor's session (e.g. passed via the query string), then set special offer code
    } else if (isset($_SESSION['ecommerce']['special_offer_code']) == true) {
        $special_offer_code = $_SESSION['ecommerce']['special_offer_code'];
    }
    // if there is a special offer code for this order, then get offer code because the special offer code might just be a key code
    if ($special_offer_code != '') {
        $offer_code = get_offer_code_for_special_offer_code($special_offer_code);
    }
    // get all active offers that have an order scope so we can get discounted product prices
    // we can't get offers that have a recipient scope, because when we show a discounted product, we don't know which recipient it might eventually be added to
    $query = "SELECT

            offers.id,

            offers.code,

            offers.only_apply_best_offer

        FROM offers

        WHERE

            (offers.status = 'enabled')

            AND (offers.start_date <= CURRENT_DATE())

            AND (CURRENT_DATE() <= offers.end_date)

            AND ((offers.require_code = 0) OR (offers.code = '" . escape($offer_code) . "'))

            AND (offers.scope = 'order')

        ORDER BY offers.code ASC";
    $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
    $offers = array();
    while ($row = mysqli_fetch_assoc($result)) {
        $offers[] = $row;
    }
    // prepare to get best offers (including non-competing offers and competing offers that are the best offer)
    // competing offers are offers that share the same code but where only the best offer will be applied
    $best_offers = array();
    // create array to store the best offer id's for offer codes, so we don't have to look up the best offer multiple times
    $best_offer_ids = array();
    // loop through the offers in order to get best offers
    foreach ($offers as $key => $offer) {
        $previous_offer_code = '';
        // if this is not the first offer, then get previous offer code
        if ($key != 0) {
            $previous_offer_code = $offers[$key - 1]['code'];
        }
        $next_offer_code = '';
        // if this is not the last offer, then get next offer code
        if ($key != (count($offers) - 1)) {
            $next_offer_code = $offers[$key + 1]['code'];
        }
        // if this is not the first offer and the previous offer had the same code,
        // or if this is not the last offer and the next offer had the same code,
        // and if this offer is set so only the best offer is applied,
        // then this is a competing offer, so determine if offer is the best offer
        if ((($key != 0) && ($offer['code'] == $previous_offer_code) || ($key != (count($offers) - 1)) && ($offer['code'] == $next_offer_code)) && ($offer['only_apply_best_offer'] == 1)) {
            $best_offer_id = '';
            // if the best offer id has already been found for this offer code, then get it
            if (isset($best_offer_ids[$offer['code']]) == true) {
                $best_offer_id = $best_offer_ids[$offer['code']];
                // else the best offer id has not already been found for this offer code, so get it
                // and then remember it by adding to array
            } else {
                $best_offer_id = get_best_offer_id($offer['code']);
                $best_offer_ids[$offer['code']] = $best_offer_id;
            }
            // if this offer is the best offer, then add it to array
            if ($offer['id'] == $best_offer_id) {
                $best_offers[] = $offer;
            }
            // else this is not a competing offer, so it is a best offer, so add it to array
        } else {
            $best_offers[] = $offer;
        }
    }
    // set active offers to all the best offers
    $offers = $best_offers;
    // loop through all active offers so we can get discounted product prices
    foreach ($offers as $offer) {
        // if the offer is valid, then continue to get discounted product prices for this offer
        if (validate_offer($offer['id']) == true) {
            // get offer actions for this offer that discount a product
            // The product join is not part of the filter any more: an action
            // that names a product group has no single product to join to, and
            // its targets are read below instead.
            $query = "SELECT

                    offer_actions.discount_product_product_id,

                    offer_actions.discount_product_group_id,

                    offer_actions.discount_product_amount,

                    offer_actions.discount_product_percentage

                FROM offers_offer_actions_xref

                LEFT JOIN offer_actions ON offers_offer_actions_xref.offer_action_id = offer_actions.id

                WHERE

                    (offers_offer_actions_xref.offer_id = '" . $offer['id'] . "')

                    AND (offer_actions.type = 'discount product')

                    AND ((offer_actions.discount_product_product_id != '0') OR (offer_actions.discount_product_group_id != '0'))

                    AND ((offer_actions.discount_product_amount != 0) OR (offer_actions.discount_product_percentage != 0))";
            $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
            $offer_actions = array();
            while ($row = mysqli_fetch_assoc($result)) {
                $offer_actions[] = $row;
            }
            // loop through the offer actions in order to apply offers to order
            foreach ($offer_actions as $offer_action) {
                // One action names either one product or every product of a
                // group; both end up as a list of products with their prices,
                // so the discount is worked out the same way for both.
                if ((int) $offer_action['discount_product_group_id'] > 0) {
                    $target_ids = pg_offer_group_products($offer_action['discount_product_group_id']);
                } else {
                    $target_ids = array((int) $offer_action['discount_product_product_id']);
                }
                if (!$target_ids) {
                    continue;
                }
                $targets = (array) db_items("SELECT id, price FROM products

                    WHERE (id IN ('" . implode("', '", array_map('e', $target_ids)) . "')) AND (price != '0')");
                foreach ($targets as $target) {
                    $product_id = (int) $target['id'];
                    $price = $target['price'];
                    $discounted_price = 0;
                    // if discount is by amount, then get discounted price
                    if ($offer_action['discount_product_amount']) {
                        $discounted_price = $price - $offer_action['discount_product_amount'];
                        // else discount is by percentage, so get discounted price
                    } else {
                        $discounted_price = $price - ($price * ($offer_action['discount_product_percentage'] / 100));
                    }
                    // if the discounted price is less than 0, then set discounted price to 0
                    if ($discounted_price < 0) {
                        $discounted_price = 0;
                    }
                    // if this offer and action provides the greatest discount so far, then store discount information in array
                    if ((isset($discounted_product_prices[$product_id]) == false) || ($discounted_price < $discounted_product_prices[$product_id])) {
                        $discounted_product_prices[$product_id] = $discounted_price;
                    }
                }
            }
        }
    }
    return $discounted_product_prices;
}
function prepare_price_for_output($original_price, $discounted, $discounted_price, $format, $show_code = true, $show_html_entity_symbol = true)
{
    $original_amount = get_currency_amount($original_price / 100, VISITOR_CURRENCY_EXCHANGE_RATE);
    $original_negative = '';
    // If the amount is negative, then prepare to show negative sign before price,
    // and convert amount to positive value.
    if ($original_amount < 0) {
        $original_negative = '-';
        $original_amount = abs($original_amount);
    }
    if ($discounted) {
        $discounted_amount = get_currency_amount($discounted_price / 100, VISITOR_CURRENCY_EXCHANGE_RATE);
        $discounted_negative = '';
        // If the amount is negative, then prepare to show negative sign before price,
        // and convert amount to positive value.
        if ($discounted_amount < 0) {
            $discounted_negative = '-';
            $discounted_amount = abs($discounted_amount);
        }
    }
    // we do not show an html entity symbol for plain text order receipts, because the entity does not appear correctly in the e-mails
    // so determine if we should show the symbol or not
    $output_symbol = '';
    // if the symbol should be shown, then show it
    if (($show_html_entity_symbol == true) || (mb_substr(VISITOR_CURRENCY_SYMBOL, 0, 1) != '&')) {
        $output_symbol = VISITOR_CURRENCY_SYMBOL;
    }
    $output_code = '';
    // if the code should be shown, then prepare to show code
    if ($show_code == true) {
        $output_code = h(VISITOR_CURRENCY_CODE_FOR_OUTPUT);
    }
    // prepare to output product price differently based on the format
    switch ($format) {
        // if the format is HTML then prepare to show price with detailed styling (e.g. strike-through)
        case 'html':
            // if the product is discounted by an offer, then prepare to show original price and discounted price
            if ($discounted == true) {
                return '<span style="text-decoration: line-through; white-space: nowrap">' . $original_negative . $output_symbol . number_format($original_amount, 2, '.', ',') . $output_code . '</span> <span style="white-space: nowrap" class="software_discounted_price">' . $discounted_negative . $output_symbol . number_format($discounted_amount, 2, '.', ',') . $output_code . '</span>';
                // else the product is not discounted by an offer, so prepare to just show the product price
            } else {
                return '<span style="white-space: nowrap">' . $original_negative . $output_symbol . number_format($original_amount, 2, '.', ',') . $output_code . '</span>';
            }
            break;
        // if the format is plain text then prepare to show price with no styling
        case 'plain_text':
            // if the product is discounted by an offer, then prepare to show original price and discounted price
            if ($discounted == true) {
                return $discounted_negative . $output_symbol . number_format($discounted_amount, 2, '.', ',') . $output_code . ' (was ' . $original_negative . $output_symbol . number_format($original_amount, 2, '.', ',') . $output_code . ')';
                // else the product is not discounted by an offer, so prepare to just show the product price
            } else {
                return $original_negative . $output_symbol . number_format($original_amount, 2, '.', ',') . $output_code;
            }
            break;
    }
}
// Takes an amount in dollars (not cents) for base currency, and returns negative symbol,
// if necessary, base currency symbol, and the amount (e.g. -$10.00).  This is used
// by various screens where currency conversion is not necessary and
// the amount is never discounted.  The purpose of this function is so that
// the negative symbol will appear before the currency symbol instead of after
// the currency symbol, which would look weird.
function prepare_amount($amount)
{
    // Remove any commas that might exist in amount, because number_format()
    // below won't work correctly when commas exist.  number_format() below
    // will add the commas back properly.
    $amount = str_replace(',', '', $amount);
    $negative_symbol = '';
    // If the amount is negative, then prepare to show negative sign before amount,
    // and convert amount to positive value.
    if ($amount < 0) {
        $negative_symbol = '-';
        $amount = abs($amount);
    }
    return $negative_symbol . BASE_CURRENCY_SYMBOL . number_format($amount, 2);
}
// Create function that is responsible for checking minimum and maximum quantity for products
// and adjusting order items as necessary.
function check_quantity($liveform)
{
    // Get order items that have a minimum or maximum quantity.
    $order_items = db_items("SELECT

            order_items.id,

            order_items.quantity,

            products.minimum_quantity,

            products.maximum_quantity,

            products.name,

            products.short_description

        FROM order_items

        LEFT JOIN products ON order_items.product_id = products.id

        WHERE 

            (order_items.order_id = '" . ($_SESSION['ecommerce']['order_id'] ?? '') . "')

            AND

            (

                (products.minimum_quantity != '0')

                OR (products.maximum_quantity != '0')

            )");
    // Loop through order items in order to check minimum and maximum quantity.
    foreach ($order_items as $order_item) {
        // If there is both a minimum and maximum quantity for the product
        // and they are the same, and the order item quantity is not the required amount,
        // then adjust the quantity and add notice.
        if (($order_item['minimum_quantity'] != 0) && ($order_item['maximum_quantity'] != 0) && ($order_item['minimum_quantity'] == $order_item['maximum_quantity']) && ($order_item['quantity'] != $order_item['minimum_quantity'])) {
            db("UPDATE order_items SET quantity = '" . $order_item['minimum_quantity'] . "' WHERE id = '" . $order_item['id'] . "'");
            // prepare product description for notice
            $product_description = '';
            // if there is a name, then add it to the description
            if ($order_item['name'] != '') {
                $product_description .= $order_item['name'];
            }
            // if there is a short description, then add it to the description
            if ($order_item['short_description'] != '') {
                // if the description is not blank, then add separator
                if ($product_description != '') {
                    $product_description .= ' - ';
                }
                $product_description .= $order_item['short_description'];
            }
            // if the description is blank, then set default description
            if ($product_description == '') {
                $product_description = lang('an item');
            }
            $liveform->add_notice(lang(array('string' => 'We\'re sorry, we require a quantity of {var:1} for {var:2}, so we have updated the quantity for you.', 'vars' => array(number_format($order_item['minimum_quantity']), h($product_description)))));
            // Otherwise, if there is a minimum quantity and the quantity is below that minimum,
            // then update quantity and add notice.
        } else if (($order_item['minimum_quantity'] != 0) && ($order_item['quantity'] < $order_item['minimum_quantity'])) {
            db("UPDATE order_items SET quantity = '" . $order_item['minimum_quantity'] . "' WHERE id = '" . $order_item['id'] . "'");
            // prepare product description for notice
            $product_description = '';
            // if there is a name, then add it to the description
            if ($order_item['name'] != '') {
                $product_description .= $order_item['name'];
            }
            // if there is a short description, then add it to the description
            if ($order_item['short_description'] != '') {
                // if the description is not blank, then add separator
                if ($product_description != '') {
                    $product_description .= ' - ';
                }
                $product_description .= $order_item['short_description'];
            }
            // if the description is blank, then set default description
            if ($product_description == '') {
                $product_description = lang('an item');
            }
            $liveform->add_notice(lang(array('string' => 'We\'re sorry, we require a minimum quantity of {var:1} for {var:2}, so we have increased the quantity for you.', 'vars' => array(number_format($order_item['minimum_quantity']), h($product_description)))));
            // Otherwise, if there is a maximum quantity and the quantity is above that maximum,
            // then update quantity and add notice.
        } else if (($order_item['maximum_quantity'] != 0) && ($order_item['quantity'] > $order_item['maximum_quantity'])) {
            db("UPDATE order_items SET quantity = '" . $order_item['maximum_quantity'] . "' WHERE id = '" . $order_item['id'] . "'");
            // prepare product description for notice
            $product_description = '';
            // if there is a name, then add it to the description
            if ($order_item['name'] != '') {
                $product_description .= $order_item['name'];
            }
            // if there is a short description, then add it to the description
            if ($order_item['short_description'] != '') {
                // if the description is not blank, then add separator
                if ($product_description != '') {
                    $product_description .= ' - ';
                }
                $product_description .= $order_item['short_description'];
            }
            // if the description is blank, then set default description
            if ($product_description == '') {
                $product_description = lang('an item');
            }
            $liveform->add_notice(lang(array('string' => 'We\'re sorry, we allow a maximum quantity of {var:1} for {var:2}, so we have decreased the quantity for you.', 'vars' => array(number_format($order_item['maximum_quantity']), h($product_description)))));
        }
    }
}
// create function for checking if order items are still valid based on inventory levels
function check_inventory($liveform)
{
    // get all of the products that are in the order that have inventory enabled
    $query = "SELECT

            DISTINCT(order_items.product_id),

            products.id,

            products.name,

            products.short_description,

            products.inventory_quantity,

            products.out_of_stock_message

        FROM order_items

        LEFT JOIN products ON order_items.product_id = products.id

        WHERE

            (order_items.order_id = '" . ($_SESSION['ecommerce']['order_id'] ?? '') . "')

            AND (products.inventory = '1')

            AND (products.backorder = '0')";
    $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
    $products = array();
    // loop through products in order to add them to array
    while ($row = mysqli_fetch_assoc($result)) {
        $products[] = $row;
    }
    // loop through products in order to check inventory for order items
    foreach ($products as $product) {
        // set the starting inventory quantity
        // so later we know if the product was completely out of stock to begin with before we started looking at order items
        $product['starting_inventory_quantity'] = $product['inventory_quantity'];
        // get all order items in order to check if there is enough inventory quantity for them
        $query = "SELECT

                order_items.id,

                order_items.quantity,

                ship_tos.id as ship_to_id,

                ship_tos.complete as ship_to_complete

            FROM order_items

            LEFT JOIN ship_tos ON order_items.ship_to_id = ship_tos.id

            WHERE

                (order_items.order_id = '" . ($_SESSION['ecommerce']['order_id'] ?? '') . "')

                AND (order_items.product_id = '" . $product['id'] . "')

            ORDER BY order_items.id ASC";
        $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
        $order_items = array();
        // loop through order items in order to add them to array
        while ($row = mysqli_fetch_assoc($result)) {
            $order_items[] = $row;
        }
        // loop through order items in order to check if there is enough inventory quantity for them
        foreach ($order_items as $order_item) {
            // prepare product description for notice
            $product_description = '';
            // if there is a name, then add it to the description
            if ($product['name'] != '') {
                $product_description .= $product['name'];
            }
            // if there is a short description, then add it to the description
            if ($product['short_description'] != '') {
                // if the description is not blank, then add separator
                if ($product_description != '') {
                    $product_description .= ' - ';
                }
                $product_description .= $product['short_description'];
            }
            // if the description is blank, then set default description
            if ($product_description == '') {
                $product_description = lang('an item');
            }
            // if there is no more inventory, then remove order item and add notice
            if ($product['inventory_quantity'] == 0) {
                remove_order_item($order_item['id']);
                // if the product is completely out of stock, then add a certain notice
                if ($product['starting_inventory_quantity'] == 0) {
                    $liveform->add_notice(lang(array('string' => 'We\'re sorry, {var:1} has been removed from your order because it is not currently available.', 'vars' => h($product_description))) . $product['out_of_stock_message']);
                    // else the product is only out of stock between there are other order items in the order that are using up inventory quantity, so add different notice
                } else {
                    $liveform->add_notice(lang(array('string' => 'We\'re sorry, {var:1} has been removed from your order because the availabilty of the item is currently limited.', 'vars' => h($product_description))));
                }
                // else there is remaining inventory, so check if quantity needs to be reduced
            } else {
                // if the order item quantity is greater than the inventory quantity, then reduce it by setting it to the inventory quantity
                // Eventually we need to deal with the fact that we might be reducing the quantity below the
                // minimum quantity property below.  This is a current bug when using minimum quantity with inventory tracking enabled.
                if ($order_item['quantity'] > $product['inventory_quantity']) {
                    $order_item['quantity'] = $product['inventory_quantity'];
                    $query = "UPDATE order_items SET quantity = '" . $order_item['quantity'] . "' WHERE id = '" . $order_item['id'] . "'";
                    $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
                    // if there is a ship to and it is complete, then update shipping cost for ship to
                    if (($order_item['ship_to_id'] != '') && ($order_item['ship_to_complete'] == 1)) {
                        require_once(PG_FUNCTIONS_DIR . '/shipping.php');
                        update_shipping_cost_for_ship_to($order_item['ship_to_id']);
                    }
                    $liveform->add_notice(lang(array('string' => 'We\'re sorry, the quantity you entered for {var:1} has been reduced because the availabilty of the item is currently limited.', 'vars' => h($product_description))));
                }
                // decrement the inventory quantity for the product,
                // so as we loop through these order items we can dynamically know how much inventory quantity is left
                // based on order items that we have already looped through
                $product['inventory_quantity'] = $product['inventory_quantity'] - $order_item['quantity'];
            }
        }
    }
}
// create function for checking if calendar event reservations are still valid and available for all order items in order
function check_reservations($liveform)
{
    // if calendars is enabled in the site settings, then proceed with checking reservations
    if (CALENDARS == true) {
        // get all calendar event reservation order items in order to verify that they are still valid
        // (e.g. check if there are remaining reservation spots)
        $query = "SELECT

                order_items.id,

                order_items.quantity,

                order_items.calendar_event_id,

                order_items.recurrence_number,

                products.id as product_id,

                products.enabled AS product_enabled,

                products.name,

                products.short_description,

                ship_tos.id as ship_to_id,

                ship_tos.complete as ship_to_complete

            FROM order_items

            LEFT JOIN products ON order_items.product_id = products.id

            LEFT JOIN ship_tos ON order_items.ship_to_id = ship_tos.id

            WHERE

                (order_items.order_id = '" . ($_SESSION['ecommerce']['order_id'] ?? '') . "')

                AND (order_items.calendar_event_id != '0')";
        $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
        $reservation_order_items = array();
        // loop through reservation order items in order to add them to array
        while ($row = mysqli_fetch_assoc($result)) {
            $reservation_order_items[] = $row;
        }
        // loop through all reservation order items in order to perform maintenance on them
        foreach ($reservation_order_items as $reservation_order_item) {
            $calendar_event = get_calendar_event($reservation_order_item['calendar_event_id'], $reservation_order_item['recurrence_number']);
            // prepare product description in case we need to add a notice
            $product_description = '';
            // if there is a name, then add it to the description
            if ($reservation_order_item['name'] != '') {
                $product_description .= $reservation_order_item['name'];
            }
            // if there is a short description, then add it to the description
            if ($reservation_order_item['short_description'] != '') {
                // if the description is not blank, then add separator
                if ($product_description != '') {
                    $product_description .= ' - ';
                }
                $product_description .= $reservation_order_item['short_description'];
            }
            // if the description is blank, then set default description
            if ($product_description == '') {
                $product_description = lang('an item');
            }
            // if the calendar event no longer exists,
            // or if the product no longer exists,
            // or if the product is disabled,
            // or if the calendar event is no longer published,
            // or if reservations are no longer enabled for the calendar event,
            // or if this event is not a recurring event or it is a recurring event but it separates reservations and the event/instance is in the past,
            // or if this event is a recurring event and it does not separate reservations and the initial instance is in the past and it does not have any recurring instances in the future,
            // then remove order item and add notice because reservations should not be allowed for this product anymore
            if (($calendar_event == false) || ($reservation_order_item['product_id'] == '') || ($reservation_order_item['product_enabled'] == 0) || ($calendar_event['published'] == 0) || ($calendar_event['reservations'] == 0) || ((($calendar_event['total_recurrence_number'] == 0) || ($calendar_event['separate_reservations'] == 1)) && (strtotime($calendar_event['end_date_and_time']) < time())) || (($calendar_event['total_recurrence_number'] > 0) && ($calendar_event['separate_reservations'] == 0) && (strtotime($calendar_event['end_date_and_time']) < time()) && (check_for_recurring_instance_in_the_future($calendar_event['id']) == false))) {
                remove_order_item($reservation_order_item['id']);
                $liveform->add_notice(lang(array('string' => 'We\'re sorry, {var:1} has been removed from your order because it is no longer available.', 'vars' => array(h($product_description)))));
                // else reservations are enabled so if reservations are limited, then check if there are available spots
            } else if ($calendar_event['limit_reservations'] == 1) {
                // if there are no remaining spots, then remove order item and add notice
                if ($calendar_event['number_of_remaining_spots'] == 0) {
                    remove_order_item($reservation_order_item['id']);
                    $liveform->add_notice('We\'re sorry, ' . h($product_description) . ' has been removed from your order because it is no longer available. ' . $calendar_event['no_remaining_spots_message']);
                    // else there are remaining spots, so if the quantity is greater than the number of remaining spots,
                    // then adjust quantity for order item and add notice
                } else if ($reservation_order_item['quantity'] > $calendar_event['number_of_remaining_spots']) {
                    $query = "UPDATE order_items SET quantity = '" . $calendar_event['number_of_remaining_spots'] . "' WHERE id = '" . $reservation_order_item['id'] . "'";
                    $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
                    // if there is a ship to and it is complete, then update shipping cost for ship to
                    if (($reservation_order_item['ship_to_id'] != '') && ($reservation_order_item['ship_to_complete'] == 1)) {
                        require_once(PG_FUNCTIONS_DIR . '/shipping.php');
                        update_shipping_cost_for_ship_to($reservation_order_item['ship_to_id']);
                    }
                    $liveform->add_notice(lang(array('string' => 'We\'re sorry, {var:1} is limited, so we were not able to add your requested quantity.', 'vars' => array(h($product_description)))));
                }
            }
        }
    }
}
// create function that will check to see if a recurring instance exists in the future for a calendar event
// we use this in order to determine if a calendar event is allowed to be reserved.
// For example it might be a calendar event that does not separate reservations and the first instance is the past
// but the ordering checks need to make sure there is an instance in the future in order to know if the event should be
// allowed to be reserved.
function check_for_recurring_instance_in_the_future($calendar_event_id)
{
    // get calendar event info
    $query = "SELECT

            id,

            recurrence_number as total_recurrence_number,

            recurrence_type,

            recurrence_day_sun,

            recurrence_day_mon,

            recurrence_day_tue,

            recurrence_day_wed,

            recurrence_day_thu,

            recurrence_day_fri,

            recurrence_day_sat,

            recurrence_month_type,

            start_time as start_date_and_time,

            end_time as end_date_and_time

        FROM calendar_events

        WHERE id = '" . escape($calendar_event_id) . "'";
    $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
    $calendar_event = mysqli_fetch_assoc($result);
    $recurrence_day_sun = $calendar_event['recurrence_day_sun'];
    $recurrence_day_mon = $calendar_event['recurrence_day_mon'];
    $recurrence_day_tue = $calendar_event['recurrence_day_tue'];
    $recurrence_day_wed = $calendar_event['recurrence_day_wed'];
    $recurrence_day_thu = $calendar_event['recurrence_day_thu'];
    $recurrence_day_fri = $calendar_event['recurrence_day_fri'];
    $recurrence_day_sat = $calendar_event['recurrence_day_sat'];
    $recurrence_month_type = $calendar_event['recurrence_month_type'];
    // get exceptions so we don't look at those instances
    $query = "SELECT recurrence_number FROM calendar_event_exceptions WHERE calendar_event_id = '" . $calendar_event['id'] . "'";
    $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
    $exceptions = array();
    // loop through exceptions in order to add them to array
    while ($row = mysqli_fetch_assoc($result)) {
        $exceptions[] = $row['recurrence_number'];
    }
    // split event start date and time into parts
    $event_start_date_and_time_parts = explode(' ', $calendar_event['start_date_and_time']);
    $event_start_date = $event_start_date_and_time_parts[0];
    $event_start_time = $event_start_date_and_time_parts[1];
    $event_start_date_parts = explode('-', $event_start_date);
    $event_start_year = $event_start_date_parts[0];
    $event_start_month = $event_start_date_parts[1];
    $event_start_day = $event_start_date_parts[2];
    // Find the difference between the original start date and end date (So we do not need to calculate the end dates new date too)
    $event_start_end_difference = strtotime($calendar_event['end_date_and_time']) - strtotime($calendar_event['start_date_and_time']);
    // If this is a monthly event and the month type is "day of the week",
    // then determine which week in the month the event is on.
    // If the week is 1-4 then we will use that, however if the week is 5,
    // then we interpret that as the last week.
    if (($calendar_event['recurrence_type'] == 'month') && ($calendar_event['recurrence_month_type'] == 'day_of_the_week')) {
        $day_of_the_week = date('l', strtotime($event_start_date));
        $first_day_of_the_month_timestamp = strtotime($event_start_year . '-' . $event_start_month . '-01');
        $week = '';
        // Create a loop in order to determine which week event falls on.
        // We only loop through 4 weeks, because we are going to set "last" below for 5th week.
        for ($week_index = 0; $week_index <= 3; $week_index++) {
            // If the event is in this week, then remember the week number and break out of this loop.
            if ($event_start_date == date('Y-m-d', strtotime('+' . $week_index . ' week ' . $day_of_the_week, $first_day_of_the_month_timestamp))) {
                $week = $week_index + 1;
                break;
            }
        }
        // If a week was not found, then that means it falls on the 5th week,
        // so set it to be the last week.
        if ($week == '') {
            $week = 'last';
        }
    }
    // loop through all recurring instances of this event in order to determine if one exists in the future
    for ($recurrence_number = 1; $recurrence_number <= $calendar_event['total_recurrence_number']; $recurrence_number++) {
        // adjust event start date depending on recurrence type
        switch ($calendar_event['recurrence_type']) {
            // Daily
            case 'day':
                $count = 0;
                // Loop through days in the future until we find a date that is valid
                // based on the valid days of the week that were selected.
                while (true) {
                    $new_time = strtotime('+1 day', strtotime($event_start_date));
                    $event_start_date = date('Y-m-d', $new_time);
                    $instance_start_date = $event_start_date;
                    $day_of_the_week = strtolower(date('D', $new_time));
                    // If this day of the week is valid for this calendar event,
                    // then we have found a valid date, so break out of the loop.
                    if (${'recurrence_day_' . $day_of_the_week} == 1) {
                        break;
                    }
                    $count++;
                    // If we have already looped 7 times, then something is wrong,
                    // so break out of this loop and the recurrence loop above.
                    // This should never happen but is added just in case in order to
                    // prevent an endless loop.
                    if ($count == 7) {
                        break 3;
                    }
                }
                break;
            // Weekly
            case 'week':
                $new_time = mktime(0, 0, 0, $event_start_month, $event_start_day + (7 * $recurrence_number), $event_start_year);
                $instance_start_date = date('Y', $new_time) . '-' . date('m', $new_time) . '-' . date('d', $new_time);
                break;
            // Monthly
            case 'month':
                switch ($recurrence_month_type) {
                    case 'day_of_the_month':
                        $new_time = mktime(0, 0, 0, $event_start_month + $recurrence_number, 1, $event_start_year);
                        $new_event_start_year = date('Y', $new_time);
                        $new_event_start_month = date('m', $new_time);
                        $new_event_start_day = $event_start_day;
                        // if date is not valid, then get last date for month
                        if (checkdate($new_event_start_month, $new_event_start_day, $new_event_start_year) == false) {
                            $new_event_start_day = date('t', mktime(0, 0, 0, $new_event_start_month, 1, $new_event_start_year));
                        }
                        $instance_start_date = $new_event_start_year . '-' . $new_event_start_month . '-' . $new_event_start_day;
                        break;
                    case 'day_of_the_week':
                        $first_day_of_the_month_timestamp = mktime(0, 0, 0, $event_start_month + $recurrence_number, 1, $event_start_year);
                        // If the week is 1-4 then find the date in a certain way.
                        if ($week != 'last') {
                            $week_index = $week - 1;
                            $new_time = strtotime('+' . $week_index . ' week ' . $day_of_the_week, $first_day_of_the_month_timestamp);
                            // Otherwise the week is last, so find the date in a different way.
                        } else {
                            $last_day_of_the_month_timestamp = strtotime(date('Y-m-t', $first_day_of_the_month_timestamp));
                            // If the last day of the month happens to be the right day of the week,
                            // then thats that day that we want.
                            if (date('l', $last_day_of_the_month_timestamp) == $day_of_the_week) {
                                $new_time = $last_day_of_the_month_timestamp;
                                // Otherwise find the day of the week that we want in the last week of the month.
                            } else {
                                $new_time = strtotime('last ' . $day_of_the_week, $last_day_of_the_month_timestamp);
                            }
                        }
                        $instance_start_date = date('Y-m-d', $new_time);
                        break;
                }
                break;
            // Yearly
            case 'year':
                $new_event_start_year = $event_start_year + $recurrence_number;
                $new_event_start_month = $event_start_month;
                $new_event_start_day = $event_start_day;
                // if date is not valid, then get last date for month
                if (checkdate($new_event_start_month, $new_event_start_day, $new_event_start_year) == false) {
                    $new_event_start_day = date('t', mktime(0, 0, 0, $new_event_start_month, 1, $new_event_start_year));
                }
                $instance_start_date = $new_event_start_year . '-' . $new_event_start_month . '-' . $new_event_start_day;
                break;
        }
        // if this instance is not an exception then continue to check it
        if (in_array($recurrence_number, $exceptions) == false) {
            // set the start date and time for this instance
            $instance_start_date_and_time = $instance_start_date . ' ' . $event_start_time;
            // set the end date and time for this instance by adding the difference in time to this instance's start date and time
            $instance_end_date_and_time = date('Y-m-d H:i:s', strtotime($instance_start_date_and_time) + $event_start_end_difference);
            // if this recurring instance is in the future, then return true
            if (strtotime($instance_end_date_and_time) >= time()) {
                return true;
            }
        }
    }
    // if we have gotten here then there are no recurring instances in the future, so return false
    return false;
}
// create function that will be responsible for checking if commissions need to be created from recurring profiles
function update_recurring_commissions()
{
    // if it is currently at least 24 hours after the last check, then complete check
    if (time() >= (LAST_RECURRING_COMMISSION_CHECK_TIMESTAMP + 86400)) {
        // update the config table to remember that the check has been completed today
        $query = "UPDATE config SET last_recurring_commission_check_timestamp = UNIX_TIMESTAMP()";
        $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
        // get all enabled recurring commission profiles where the start date is today or in the past
        $query = "SELECT

                id,

                affiliate_code,

                order_id,

                amount,

                start_date,

                period,

                number_of_commissions

            FROM recurring_commission_profiles

            WHERE

                (enabled = '1')

                AND (start_date <= '" . date('Y-m-d') . "')";
        $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
        $recurring_commission_profiles = array();
        // loop through profiles in order to add them to array
        while ($row = mysqli_fetch_assoc($result)) {
            $recurring_commission_profiles[] = $row;
        }
        // loop through the profiles in order to create commissions
        foreach ($recurring_commission_profiles as $recurring_commission_profile) {
            // get existing commissions for this profile, so we can determine if a commission already exists
            $query = "SELECT created_timestamp FROM commissions WHERE recurring_commission_profile_id = '" . $recurring_commission_profile['id'] . "'";
            $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
            $existing_commission_dates = array();
            // loop through commissions in order to add dates to array
            while ($row = mysqli_fetch_assoc($result)) {
                $existing_commission_dates[] = date('Y-m-d', $row['created_timestamp']);
            }
            // if the number of commissions is 0, then it is unlimited, so set it to a very high number
            if ($recurring_commission_profile['number_of_commissions'] == 0) {
                $recurring_commission_profile['number_of_commissions'] = 999999;
            }
            $start_date_parts = explode('-', $recurring_commission_profile['start_date']);
            $start_year = $start_date_parts[0];
            $start_month = $start_date_parts[1];
            $start_day = $start_date_parts[2];
            $commission_number = 1;
            // create a loop for the number of commissions in order to check if a commission needs to be created
            for ($commission_number = 1; $commission_number <= $recurring_commission_profile['number_of_commissions']; $commission_number++) {
                $instance_start_date = '';
                // if the commission number is 1, then set the instance start date to the start date for the profile
                if ($commission_number == 1) {
                    $instance_start_date = $recurring_commission_profile['start_date'];
                    // else the commission number is greater than 1, so calculate the start date based on the commission number
                } else {
                    $recurrence_number = $commission_number - 1;
                    // get the date for the commission differently based on the period
                    switch ($recurring_commission_profile['period']) {
                        case 'monthly':
                            $instance_time = mktime(0, 0, 0, $start_month + $recurrence_number, 1, $start_year);
                            $instance_start_year = date('Y', $instance_time);
                            $instance_start_month = date('m', $instance_time);
                            $instance_start_day = $start_day;
                            // if date is not valid, then get last date for month
                            if (checkdate($instance_start_month, $instance_start_day, $instance_start_year) == false) {
                                $instance_start_day = date('t', mktime(0, 0, 0, $instance_start_month, 1, $instance_start_year));
                            }
                            $instance_start_date = $instance_start_year . '-' . $instance_start_month . '-' . $instance_start_day;
                            break;
                        case 'yearly':
                            $instance_start_year = $start_year + $recurrence_number;
                            $instance_start_month = $start_month;
                            $instance_start_day = $start_day;
                            // if date is not valid, then get last date for month
                            if (checkdate($instance_start_month, $instance_start_day, $instance_start_year) == false) {
                                $instance_start_day = date('t', mktime(0, 0, 0, $instance_start_month, 1, $instance_start_year));
                            }
                            $instance_start_date = $instance_start_year . '-' . $instance_start_month . '-' . $instance_start_day;
                            break;
                    }
                    // if the instance start date is greater than today, then we are in the future, so we can continue to the next profile
                    if ($instance_start_date > date('Y-m-d')) {
                        continue 2;
                    }
                }
                // if there is not already an existing commission for this instance, then create commission record
                if (in_array($instance_start_date, $existing_commission_dates) == false) {
                    $query = "INSERT INTO commissions (

                            reference_code,

                            affiliate_code,

                            order_id,

                            amount,

                            status,

                            recurring_commission_profile_id,

                            created_timestamp,

                            last_modified_timestamp)

                        VALUES (

                            '" . generate_commission_reference_code() . "',

                            '" . escape($recurring_commission_profile['affiliate_code']) . "',

                            '" . $recurring_commission_profile['order_id'] . "',

                            '" . $recurring_commission_profile['amount'] . "',

                            'pending',

                            '" . $recurring_commission_profile['id'] . "',

                            '" . strtotime($instance_start_date) . "',

                            UNIX_TIMESTAMP())";
                    $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
                }
            }
        }
    }
}
function get_shipping_tracking_url($number, $method)
{
    //if shipping method(shipping_method_code) is 'yurticikargo' or 'Yurtici' or 'Yurtiçi' or 'Yurtici Kargo' or 'Yurtiçi Kargo' or 'YURTICI' or 'YURTICIKARGO' or 'TR-YURTICI'
    // than output yurtiçi cargo tracking URL.
    if (($method == 'yurticikargo') || ($method == 'Yurtici') || ($method == 'Yurtiçi') || ($method == 'Yurtici Kargo') || ($method == 'Yurtiçi Kargo') || ($method == 'YURTICI') || ($method == 'YURTICIKARGO') || ($method == 'TR-YURTICI')) {
        return 'https://www.yurticikargo.com/tr/online-servisler/gonderi-sorgula?code=' . $number;
        //if shipping method(shipping_method_code) is 'suratkargo' or 'Surat' or 'Sürat' or 'Surat Kargo' or 'Sürat Kargo' or 'SURAT' or 'SURATKARGO' or 'TR-SURAT'
        // than output yurtiçi cargo tracking URL.
    } else if (($method == 'suratkargo') || ($method == 'Surat') || ($method == 'Sürat') || ($method == 'Surat Kargo') || ($method == 'Sürat Kargo') || ($method == 'SURAT') || ($method == 'SURATKARGO') || ($method == 'TR-SURAT')) {
        return 'https://www.suratkargo.com.tr/KargoTakip/?kargotakipno=' . $number;
        //if shipping method(shipping_method_code) is 'araskargo' or 'Aras' or 'Aras Kargo' or 'ARAS' or 'ARASKARGO' or 'TR-ARAS'
        // than output yurtiçi cargo tracking URL.
    } else if (($method == 'araskargo') || ($method == 'Aras') || ($method == 'Aras Kargo') || ($method == 'ARAS') || ($method == 'ARASKARGO') || ($method == 'TR-ARAS')) {
        return 'http://kargotakip.araskargo.com.tr/mainpage.aspx?code=' . $number;
        // if the shipping carrier is UPS, then return tracking URL for it
    } else if (preg_match('/\b(1Z ?[0-9A-Z]{3} ?[0-9A-Z]{3} ?[0-9A-Z]{2} ?[0-9A-Z]{4} ?[0-9A-Z]{3} ?[0-9A-Z]|[\dT]\d\d\d ?\d\d\d\d ?\d\d\d)\b/', $number) == 1) {
        return 'https://wwwapps.ups.com/WebTracking/processInputRequest?HTMLVersion=5.0&error_carried=true&tracknums_displayed=5&TypeOfInquiryNumber=T&loc=en_US&InquiryNumber1=' . urlencode($number);
        // Otherwise if the shipping carrier is FedEx, then return tracking URL for it.
        // We include the FedEx SmartPost support below, because if we don't then they will match USPS,
        // and the FedEx tracking for SmartPost is much better than the USPS tracking.  For example,
        // the USPS tracking won't contain any info until USPS gets the package from FedEx.
    } else if (
        (preg_match('/(\b96\d{20}\b)|(\b\d{15}\b)|(\b\d{12}\b)/', $number) == 1) || (preg_match('/\b((98\d\d\d\d\d?\d\d\d\d|98\d\d) ?\d\d\d\d ?\d\d\d\d( ?\d\d\d)?)\b/', $number) == 1) || (preg_match('/^[0-9]{15}$/', $number) == 1) || (preg_match('/^927489\d{16}$/', $number) == 1) // FedEx SmartPost
        || (preg_match('/^926129\d{16}$/', $number) == 1) // FedEx SmartPost
    ) {
        return 'https://www.fedex.com/Tracking?tracknumbers=' . urlencode($number) . '&action=track';
        // else if the shipping carrier is USPS, then return tracking URL for it
    } else if ((preg_match('/(\b\d{30}\b)|(\b91\d+\b)|(\b\d{20}\b)/', $number) == 1) || (preg_match('/^E\D{1}\d{9}\D{2}$|^9\d{15,21}$/', $number) == 1) || (preg_match('/^91[0-9]+$/', $number) == 1) || (preg_match('/^[A-Za-z]{2}[0-9]+US$/', $number) == 1)) {
        return 'https://tools.usps.com/go/TrackConfirmAction?qtc_tLabels1=' . urlencode($number);
        // else a shipping carrier was not found, so return empty string
    } else {
        return '';
    }
}
/**
 * What a barcode of a given type has to look like.
 *
 * One description of the shape, used by the input that collects the code, by
 * the generator that makes one up, and by the check that runs before it is
 * stored. A box that accepts thirteen letters for an EAN13 produces a label
 * that will not scan, and the operator finds out at the till.
 *
 * EAN13 is twelve digits plus a check digit; UPC-A is eleven plus one. CODE128
 * encodes the whole printable ASCII range at any length, so it has no shape to
 * enforce beyond the column width.
 *
 * @return array type, digits_only, length (0 = free), pattern (HTML5), hint
 */
function pg_barcode_format($type = '')
{
    $type = ($type !== '') ? $type : (defined('BARCODE_DEFAULT_TYPE') ? BARCODE_DEFAULT_TYPE : 'CODE128');

    switch ($type) {

        case 'EAN13':
            return array(
                'type'        => 'EAN13',
                'digits_only' => TRUE,
                'length'      => 13,
                'pattern'     => '[0-9]{13}',
                'hint'        => lang(array('string' => '{var:1} digits', 'vars' => array(13))));

        case 'UPC':
            return array(
                'type'        => 'UPC',
                'digits_only' => TRUE,
                'length'      => 12,
                'pattern'     => '[0-9]{12}',
                'hint'        => lang(array('string' => '{var:1} digits', 'vars' => array(12))));

        default:
            return array(
                'type'        => $type,
                'digits_only' => FALSE,
                'length'      => 0,
                'pattern'     => '',
                'hint'        => '');
    }
}
/**
 * Does this code fit the type it is being stored as?
 *
 * A blank code is not invalid — it means "generate one" everywhere this is
 * called from, and that decision belongs to the caller.
 */
function pg_barcode_matches_format($barcode, $type = '')
{
    $barcode = trim((string) $barcode);

    if ($barcode === '') {
        return TRUE;
    }

    $format = pg_barcode_format($type);

    if ($format['digits_only'] && !preg_match('/^[0-9]+$/', $barcode)) {
        return FALSE;
    }

    if ($format['length'] && (mb_strlen($barcode) !== $format['length'])) {
        return FALSE;
    }

    return TRUE;
}
/**
 * Produce one barcode that is not already in use.
 *
 * The same generator was written out three times — the bulk action in
 * edit_products.php, the assign_barcodes endpoint in api.php, and now the
 * product screen. Three copies of a check-digit calculation is three chances
 * for one of them to drift, and a barcode that fails validation at the till is
 * not something the operator can debug.
 *
 * Returns '' when no free code turned up. The caller decides what that means;
 * for a bulk assign it is "skip this one", which is why this does not write
 * anything itself.
 *
 * @param string $type CODE128 (default), EAN13 or UPC
 * @param int    $product_id seeds the CODE128 form so two products made in the
 *               same second do not collide on the random half alone
 * @return string
 */
function pg_generate_unique_barcode($type = '', $product_id = 0)
{
    $type       = ($type !== '') ? $type : (defined('BARCODE_DEFAULT_TYPE') ? BARCODE_DEFAULT_TYPE : 'CODE128');
    $product_id = (int) $product_id;
    $attempts   = 0;

    do {
        if ($type === 'EAN13') {
            // 12 digits plus a modulo 10 check digit weighted 1,3,1,3...
            $digits = '';
            for ($i = 0; $i < 12; $i++) {
                $digits .= rand(0, 9);
            }
            $sum = 0;
            for ($i = 0; $i < 12; $i++) {
                $sum += (($i % 2 === 0) ? 1 : 3) * (int) $digits[$i];
            }
            $barcode = $digits . ((10 - ($sum % 10)) % 10);

        } elseif ($type === 'UPC') {
            // 11 digits, same idea, weights the other way round.
            $digits = '';
            for ($i = 0; $i < 11; $i++) {
                $digits .= rand(0, 9);
            }
            $sum = 0;
            for ($i = 0; $i < 11; $i++) {
                $sum += (($i % 2 === 0) ? 3 : 1) * (int) $digits[$i];
            }
            $barcode = $digits . ((10 - ($sum % 10)) % 10);

        } else {
            $barcode = str_pad($product_id, 5, '0', STR_PAD_LEFT) . str_pad(rand(0, 9999999), 7, '0', STR_PAD_LEFT);
        }

        $exists = db_value("SELECT COUNT(*) FROM product_barcodes WHERE barcode = '" . e($barcode) . "'");
        $attempts++;

    } while ($exists && ($attempts < 30));

    return $exists ? '' : $barcode;
}
/**
 * Attach a barcode to a product.
 *
 * A blank $barcode means "generate one". A product that already has a barcode
 * is left alone — this is called from the create path, but the same rule is
 * what the bulk assign uses, and re-running it must not pile up codes.
 *
 * Returns the stored code, or '' if nothing was written.
 */
function pg_assign_product_barcode($product_id, $barcode = '', $type = '')
{
    $product_id = (int) $product_id;

    if (!$product_id or !defined('BARCODE_ENABLED') or !BARCODE_ENABLED) {
        return '';
    }

    if (db_value("SELECT COUNT(*) FROM product_barcodes WHERE product_id = '" . e($product_id) . "'")) {
        return '';
    }

    $type    = ($type !== '') ? $type : (defined('BARCODE_DEFAULT_TYPE') ? BARCODE_DEFAULT_TYPE : 'CODE128');
    $barcode = trim($barcode);

    if ($barcode !== '') {
        // Checked here and not only in the browser. The input carries the
        // pattern, but a pattern is a hint to whoever is typing, not a rule
        // about what may be stored.
        if (!pg_barcode_matches_format($barcode, $type)) {
            return '';
        }

        // A code the operator typed is refused rather than replaced if it is
        // taken: silently generating a different one would leave them holding a
        // label that scans as another product.
        if (db_value("SELECT COUNT(*) FROM product_barcodes WHERE barcode = '" . e($barcode) . "'")) {
            return '';
        }
    } else {
        $barcode = pg_generate_unique_barcode($type, $product_id);
    }

    if ($barcode === '') {
        return '';
    }

    $now = date('Y-m-d H:i:s');

    db("INSERT INTO product_barcodes (product_id, barcode, barcode_type, created_at, updated_at)
        VALUES ('" . e($product_id) . "', '" . e($barcode) . "', '" . e($type) . "', '" . e($now) . "', '" . e($now) . "')");

    return $barcode;
}
function duplicate_product($id)
{
    // get original product's info
    $result = mysqli_query(db::$con, "SELECT * FROM products WHERE id = '" . escape($id) . "'") or output_error(lang('Query failed'));
    $row = mysqli_fetch_assoc($result);
    $original_product_name = $row['name'];
    $product_name = get_unique_name(array(
        'name' => $row['name'],
        'type' => 'product'
    ));
    // if the address name is NOT blank then use that value for the address name
    if ($row['address_name'] != '') {
        $address_name = $row['address_name'];
        // else if the short description is NOT blank then use that value
    } elseif ($row['short_description'] != '') {
        $address_name = $row['short_description'];
        // else if the name is NOT blank then use that value
    } elseif ($row['name'] != '') {
        $address_name = $row['name'];
        // else use id
    } else {
        $address_name = $row['id'];
    }
    // prepare the address name for the database
    $address_name = prepare_catalog_item_address_name($address_name);
    // insert row into product table
    $query = "INSERT INTO products (

            name,

            enabled,

            short_description,

            full_description,

            details,

            code,

            keywords,

            image_name,

            price,

            taxable,

            tax_rate,

            contact_group_id,

            order_receipt_bcc_email_address,

            email_page,

            email_bcc,

            order_receipt_message,

            required_product,

            selection_type,

            default_quantity,

            minimum_quantity,

            maximum_quantity,

            address_name,

            title,

            meta_description,

            meta_keywords,

            inventory,

            inventory_quantity,

            backorder,

            out_of_stock_message,

            shippable,

            weight,

            primary_weight_points,

            secondary_weight_points,

            length,

            width,

            height,

            container_required,

            preparation_time,

            free_shipping,

            extra_shipping_cost,

            commissionable,

            commission_rate_limit,

            recurring,

            recurring_schedule_editable_by_customer,

            start,

            number_of_payments,

            payment_period,

            recurring_profile_disabled_perform_actions,

            recurring_profile_disabled_expire_membership,

            recurring_profile_disabled_revoke_private_access,

            recurring_profile_disabled_email,

            recurring_profile_disabled_email_subject,

            recurring_profile_disabled_email_page_id,

            sage_group_id,

            membership_renewal,

            grant_private_access,

            private_folder,

            private_days,

            send_to_page,

            reward_points,

            gift_card,

            gift_card_email_subject,

            gift_card_email_format,

            gift_card_email_body,

            gift_card_email_page_id,

            submit_form,

            submit_form_custom_form_page_id,

            submit_form_create,

            submit_form_update,

            submit_form_update_where_field,

            submit_form_update_where_value,

            submit_form_quantity_type,

            add_comment,

            add_comment_page_id,

            add_comment_message,

            add_comment_name,

            add_comment_only_for_submit_form_update,

            form,

            form_name,

            form_label_column_width,

            form_quantity_type,

            custom_field_1,

            custom_field_2,

            custom_field_3,

            custom_field_4,

            notes,

            google_product_category,

            gtin,

            brand,

            mpn,

            user,

            timestamp)

        VALUES (

            '" . escape($product_name) . "',

            '" . escape($row['enabled']) . "',

            '" . escape($row['short_description']) . "',

            '" . escape($row['full_description']) . "',

            '" . escape($row['details']) . "',

            '" . escape($row['code']) . "',

            '" . escape($row['keywords']) . "',

            '" . escape($row['image_name']) . "',

            '" . escape($row['price']) . "',

            '" . escape($row['taxable']) . "',

            " . (($row['tax_rate'] === null) ? 'NULL' : "'" . escape($row['tax_rate']) . "'") . ",

            '" . escape($row['contact_group_id']) . "',

            '" . escape($row['order_receipt_bcc_email_address']) . "',

            '" . escape($row['email_page']) . "',

            '" . escape($row['email_bcc']) . "',

            '" . escape($row['order_receipt_message']) . "',

            '" . escape($row['required_product']) . "',

            '" . escape($row['selection_type']) . "',

            '" . escape($row['default_quantity']) . "',

            '" . escape($row['minimum_quantity']) . "',

            '" . escape($row['maximum_quantity']) . "',

            '" . escape($address_name) . "',

            '" . escape($row['title']) . "',

            '" . escape($row['meta_description']) . "',

            '" . escape($row['meta_keywords']) . "',

            '" . escape($row['inventory']) . "',

            '" . escape($row['inventory_quantity']) . "',

            '" . escape($row['backorder']) . "',

            '" . escape($row['out_of_stock_message']) . "',

            '" . escape($row['shippable']) . "',

            '" . escape($row['weight']) . "',

            '" . escape($row['primary_weight_points']) . "',

            '" . escape($row['secondary_weight_points']) . "',

            '" . e($row['length']) . "',

            '" . e($row['width']) . "',

            '" . e($row['height']) . "',

            '" . e($row['container_required']) . "',

            '" . escape($row['preparation_time']) . "',

            '" . escape($row['free_shipping']) . "',

            '" . escape($row['extra_shipping_cost']) . "',

            '" . escape($row['commissionable']) . "',

            '" . escape($row['commission_rate_limit']) . "',

            '" . escape($row['recurring']) . "',

            '" . escape($row['recurring_schedule_editable_by_customer']) . "',

            '" . escape($row['start']) . "',

            '" . escape($row['number_of_payments']) . "',

            '" . escape($row['payment_period']) . "',

            '" . escape($row['recurring_profile_disabled_perform_actions']) . "',

            '" . escape($row['recurring_profile_disabled_expire_membership']) . "',

            '" . escape($row['recurring_profile_disabled_revoke_private_access']) . "',

            '" . escape($row['recurring_profile_disabled_email']) . "',

            '" . escape($row['recurring_profile_disabled_email_subject']) . "',

            '" . escape($row['recurring_profile_disabled_email_page_id']) . "',

            '" . escape($row['sage_group_id']) . "',

            '" . escape($row['membership_renewal']) . "',

            '" . escape($row['grant_private_access']) . "',

            '" . escape($row['private_folder']) . "',

            '" . escape($row['private_days']) . "',

            '" . escape($row['send_to_page']) . "',

            '" . escape($row['reward_points']) . "',

            '" . escape($row['gift_card']) . "',

            '" . escape($row['gift_card_email_subject']) . "',

            '" . escape($row['gift_card_email_format']) . "',

            '" . escape($row['gift_card_email_body']) . "',

            '" . escape($row['gift_card_email_page_id']) . "',

            '" . escape($row['submit_form']) . "',

            '" . escape($row['submit_form_custom_form_page_id']) . "',

            '" . escape($row['submit_form_create']) . "',

            '" . escape($row['submit_form_update']) . "',

            '" . e($row['submit_form_update_where_field']) . "',

            '" . e($row['submit_form_update_where_value']) . "',

            '" . e($row['submit_form_quantity_type']) . "',

            '" . escape($row['add_comment']) . "',

            '" . escape($row['add_comment_page_id']) . "',

            '" . escape($row['add_comment_message']) . "',

            '" . escape($row['add_comment_name']) . "',

            '" . escape($row['add_comment_only_for_submit_form_update']) . "',

            '" . escape($row['form']) . "',

            '" . escape($row['form_name']) . "',

            '" . escape($row['form_label_column_width']) . "',

            '" . escape($row['form_quantity_type']) . "',

            '" . escape($row['custom_field_1']) . "',

            '" . escape($row['custom_field_2']) . "',

            '" . escape($row['custom_field_3']) . "',

            '" . escape($row['custom_field_4']) . "',

            '" . escape($row['notes']) . "', 

            '" . escape($row['google_product_category']) . "',

            '" . escape($row['gtin']) . "',

            '" . escape($row['brand']) . "', 

            '" . escape($row['mpn']) . "',

            '" . USER_ID . "',

            UNIX_TIMESTAMP())";
    $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed'));
    $new_product_id = mysqli_insert_id(db::$con);
    // get allowed zones for product that we are duplicating
    $query = "SELECT zone_id FROM products_zones_xref WHERE product_id = '" . escape($id) . "'";
    $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
    while ($row = mysqli_fetch_assoc($result)) {
        // insert row for allowed zone for new product
        $query = "INSERT INTO products_zones_xref (product_id, zone_id) VALUES ($new_product_id, " . $row['zone_id'] . ")";
        $result_2 = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
    }
    // get form fields for this product and duplicate them
    //
    // form_type is pinned so template rows (product_id 0 since 2026.4) can
    // never be picked up, and the copy is written by the explicit column list
    // below, which deliberately omits template_field_id: a duplicate is a
    // standalone product, not a variant generated from somebody's template.
    $query = "SELECT * FROM form_fields WHERE (product_id = '" . escape($id) . "') AND (form_type = 'product')";
    $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
    while ($row = mysqli_fetch_assoc($result)) {
        // insert row for field for new product
        $query = "INSERT INTO form_fields (

                form_type,

                product_id,

                name,

                label,

                type,

                sort_order,

                required,

                information,

                default_value,

                use_folder_name_for_default_value,

                size,

                maxlength,

                wysiwyg,

                `rows`, # Backticks for reserved word.

                cols,

                multiple,

                spacing_above,

                spacing_below,

                user,

                timestamp)

            VALUES (

                '" . e($row['form_type']) . "',

                '" . $new_product_id . "',

                '" . escape($row['name']) . "',

                '" . escape($row['label']) . "',

                '" . escape($row['type']) . "',

                '" . escape($row['sort_order']) . "',

                '" . escape($row['required']) . "',

                '" . escape($row['information']) . "',

                '" . escape($row['default_value']) . "',

                '" . escape($row['use_folder_name_for_default_value']) . "',

                '" . escape($row['size']) . "',

                '" . escape($row['maxlength']) . "',

                '" . escape($row['wysiwyg']) . "',

                '" . escape($row['rows']) . "',

                '" . escape($row['cols']) . "',

                '" . escape($row['multiple']) . "',

                '" . escape($row['spacing_above']) . "',

                '" . escape($row['spacing_below']) . "',

                '" . USER_ID . "',

                UNIX_TIMESTAMP())";
        $result_2 = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
        $new_form_field_id = mysqli_insert_id(db::$con);
        // get form field options
        $query = "SELECT * FROM form_field_options WHERE form_field_id = '" . $row['id'] . "'";
        $result2 = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
        $form_field_options = array();
        while ($row = mysqli_fetch_assoc($result2)) {
            $form_field_options[] = $row;
        }
        foreach ($form_field_options as $form_field_option) {
            // create form field option
            $query = "INSERT INTO form_field_options (

                        product_id,

                        form_field_id,

                        label,

                        value,

                        default_selected,

                        sort_order)

                     VALUES (

                        '" . $new_product_id . "',

                        '" . $new_form_field_id . "',

                        '" . escape($form_field_option['label']) . "',

                        '" . escape($form_field_option['value']) . "',

                        '" . $form_field_option['default_selected'] . "',

                        '" . $form_field_option['sort_order'] . "')";
            $result2 = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
        }
    }
    // Get submit form fields, in order to duplicate them.
    $submit_form_fields = db_items("SELECT

            action,

            form_field_id,

            value

        FROM product_submit_form_fields

        WHERE product_id = '" . escape($id) . "'");
    foreach ($submit_form_fields as $submit_form_field) {
        db("INSERT INTO product_submit_form_fields (

                product_id,

                action,

                form_field_id,

                value)

            VALUES (

                '$new_product_id',

                '" . $submit_form_field['action'] . "',

                '" . $submit_form_field['form_field_id'] . "',

                '" . escape($submit_form_field['value']) . "')");
    }
    // Get product attributes in order to duplicate them.
    $attributes = db_items("SELECT

            attribute_id,

            option_id,

            sort_order

        FROM products_attributes_xref

        WHERE product_id = '" . e($id) . "'");
    foreach ($attributes as $attribute) {
        db("INSERT INTO products_attributes_xref (

                product_id,

                attribute_id,

                option_id,

                sort_order)

            VALUES (

                '$new_product_id',

                '" . $attribute['attribute_id'] . "',

                '" . $attribute['option_id'] . "',

                '" . $attribute['sort_order'] . "')");
    }

    // Get product groups in order to duplicate them.
    $products_groups = db_items("SELECT product,product_group,sort_order,featured,featured_sort_order,new_date FROM products_groups_xref WHERE product = '" . e($id) . "'");

    foreach ($products_groups as $products_group) {

        $group_sort_order = '0';
        $group_featured = '0';
        $group_featured_sort_order = '0';
        $group_new_date = '0000-00-00';
        //if there is old sort_order we prepare add to products_groups_xref.
        if ($products_group['sort_order']) {
            $group_sort_order = $products_group['sort_order'];
        }
        //if there is old featured we prepare add to products_groups_xref.
        if ($products_group['featured']) {
            $group_featured = $products_group['featured'];
        }
        //if there is old featured_sort_order we prepare add to products_groups_xref.
        if ($products_group['featured_sort_order']) {
            $group_featured_sort_order = $products_group['featured_sort_order'];
        }
        //if there is old new_date we prepare add to products_groups_xref.
        if ($products_group['new_date']) {
            $group_new_date = $products_group['new_date'];
        }

        db("INSERT INTO products_groups_xref (
            product,
            sort_order,
            featured,
            featured_sort_order,
            new_date,
            product_group)

            VALUES (
                '$new_product_id',
                '" . $group_sort_order . "',
                '" . $group_featured . "',
                '" . $group_featured_sort_order . "',
                '" . $group_new_date . "',
                '" . $products_group['product_group'] . "')");
    }



    return $new_product_id;
}
function get_shipping_service_name($service)
{
    switch ($service) {
        case 'usps_express':
            return 'USPS Priority Mail Express';
            break;
        case 'usps_priority':
            return 'USPS Priority Mail';
            break;
        case 'usps_ground':
            return 'USPS Retail Ground';
            break;
        case 'ups_next_day_air':
            return 'UPS Next Day Air';
            break;
        case 'ups_next_day_air_early':
            return 'UPS Next Day Air Early';
            break;
        case 'ups_next_day_air_saver':
            return 'UPS Next Day Air Saver';
            break;
        case 'ups_2nd_day_air':
            return 'UPS 2nd Day Air';
            break;
        case 'ups_2nd_day_air_am':
            return 'UPS 2nd Day Air A.M.';
            break;
        case 'ups_3_day_select':
            return 'UPS 3 Day Select';
            break;
        case 'ups_ground':
            return 'UPS Ground';
            break;
        case 'fedex_first_overnight':
            return 'FedEx First Overnight';
            break;
        case 'fedex_priority_overnight':
            return 'FedEx Priority Overnight';
            break;
        case 'fedex_standard_overnight':
            return 'FedEx Standard Overnight';
            break;
        case 'fedex_2_day_am':
            return 'FedEx 2Day A.M.';
            break;
        case 'fedex_2_day':
            return 'FedEx 2Day';
            break;
        case 'fedex_express_saver':
            return 'FedEx Express Saver';
            break;
        case 'fedex_ground':
            return 'FedEx Ground';
            break;
        default:
            return '';
            break;
    }
}
// Gets flat array of product groups for menu on catalog and catalog detail pages.
function get_product_groups($properties)
{
    $id = $properties['id'];
    $display_type = $properties['display_type'];
    if (isset($properties['level'])) {
        $level = $properties['level'];
    } else {
        $level = 0;
    }
    $status = $properties['status'];
    // Get info for this product group.
    $product_group = db_item("SELECT

            id,

            name

        FROM product_groups

        WHERE id = '" . e($id) . "'");
    $product_group['level'] = $level;
    $product_groups = array();
    $product_groups[] = $product_group;
    $sql_status = "";
    if ($status != '') {
        if ($status == 'enabled') {
            $sql_status = "AND (enabled = '1')";
        } else {
            $sql_status = "AND (enabled = '0')";
        }
    }
    $sql_display_type = "";
    if ($display_type) {
        $sql_display_type = "AND (display_type = '" . e($display_type) . "')";
    }
    $child_product_groups = db_items("SELECT id

        FROM product_groups

        WHERE

            (parent_id = '" . e($id) . "')

            $sql_status

            $sql_display_type

        ORDER BY sort_order, name");
    foreach ($child_product_groups as $product_group) {
        $product_groups = array_merge($product_groups, get_product_groups(array(
            'id' => $product_group['id'],
            'level' => $level + 1,
            'display_type' => $display_type,
            'status' => $status
        )));
    }
    return $product_groups;
}
// Returns an SQL filter to only get non-protected shipping methods for visitors that should not
// have access to protected shipping methods.
function get_protected_shipping_method_filter()
{
    // If this visitor is not a manager or above, then don't get protected methods.
    if (!USER_LOGGED_IN or ((USER_ROLE == 3) and empty($_SESSION['software']['logged_in_as_different_user']))) {
        return "AND (shipping_methods.protected = '0')";
        // Otherwise this is a manager or above, so don't return any filter, so that all shipping
        // methods, including protected ones, will be retrieved.
    } else {
        return "";
    }
}

/**
 * Shared core for order cancellation (customer self-service via cancel_order.php
 * + admin per-row / bulk cancel via edit_orders.php).
 *
 * Responsibilities:
 *   1. Status check (idempotency).
 *   2. Pre-shipment guard — CUSTOMERS ONLY, keyed on tracking code.
 *      $is_admin === true skips it entirely.
 *   3. Persist cancellation columns (status / cancelled_at / cancelled_by /
 *      cancellation_reason).
 *   4. Expire gift cards that were issued by this order.
 *   5. Log activity + REFUND ACTION REQUIRED reminder for the operator.
 *   6. Optional: customer email notification (ECOMMERCE_CANCEL_EMAIL_NOTIFICATION).
 *   7. Optional: Iyzipay auto-refund (ECOMMERCE_ORDER_CANCEL_AUTO_REFUND) — writes
 *      orders.refund_status / refunded_at / refund_reference when the 2026.1.27
 *      migration has run; otherwise silently skips the columns.
 *
 * Returns associative array:
 *   ['status' => 'success'|'already'|'shipped'|'not_found'|'error',
 *    'message' => 'human-readable string for the operator/log',
 *    'refund_status' => '' | 'pending' | 'refunded' | 'failed' | 'manual_required',
 *    'order_id' => (int)]
 *
 * Callers are responsible for CSRF + ownership/permission gates. This function
 * trusts $is_admin to mean "caller has already validated the user is an admin".
 * That flag is load-bearing: besides the activity-log note, it BYPASSES the
 * pre-shipment guard. Never pass true from a visitor-reachable path without
 * having checked the role first.
 *
 * @param int      $order_id       Target order PK
 * @param string   $reason         Visitor / operator typed reason (≤ 500 chars)
 * @param bool     $is_admin       True when invoked from an admin context
 * @param int      $user_id        Canceller's user_id (0 for guest)
 * @param bool|null $attempt_refund NULL (default) follows the
 *                                 ECOMMERCE_ORDER_CANCEL_AUTO_REFUND config
 *                                 define. Pass TRUE to force the gateway void
 *                                 regardless of the define — used by the admin
 *                                 detail-screen modal, where the operator has
 *                                 explicitly confirmed "void the payment".
 *                                 Pass FALSE to suppress it.
 * @return array
 */
function process_order_cancellation($order_id, $reason = '', $is_admin = false, $user_id = 0, $attempt_refund = null)
{
    $order_id = (int) $order_id;
    if ($order_id <= 0) {
        return array('status' => 'error', 'message' => 'Order id is missing.', 'refund_status' => '', 'order_id' => 0);
    }

    // Minimal selection — enough for cancel gating + email + refund.
    $order = db_item(
        "SELECT id, status, user_id, payment_method, transaction_id, total,
                billing_first_name, billing_last_name, billing_email_address,
                order_number, order_date
         FROM orders
         WHERE id = '" . e($order_id) . "'
         LIMIT 1"
    );

    if (!$order) {
        return array('status' => 'not_found', 'message' => 'Order not found.', 'refund_status' => '', 'order_id' => $order_id);
    }

    // Already cancelled — idempotent no-op.
    if ($order['status'] === 'cancelled') {
        return array('status' => 'already', 'message' => 'Order was already cancelled.', 'refund_status' => '', 'order_id' => $order_id);
    }

    // ── Pre-shipment guard (CUSTOMERS ONLY) ─────────────────────────────
    // Admins are never blocked. An operator cancelling from the panel has
    // context the code does not — a customer phoned, the courier returned the
    // parcel, the order was a duplicate — so the shipment state is advisory
    // for them, not a lock. The ECOMMERCE_ORDER_CANCEL_UNTIL_SHIPPED define
    // therefore governs the SELF-SERVICE path only.
    //
    // "Shipped" means a tracking code exists (see _order_has_shipped).
    // ship_date is NOT consulted: it is often pre-filled with a planned
    // dispatch date at order time, and gating on it locked customers out of
    // orders that had not actually left yet.
    $cancel_until_shipped = !defined('ECOMMERCE_ORDER_CANCEL_UNTIL_SHIPPED')
        || ECOMMERCE_ORDER_CANCEL_UNTIL_SHIPPED !== false;
    if (!$is_admin && $cancel_until_shipped && _order_has_shipped($order_id)) {
        return array('status' => 'shipped', 'message' => 'Order has already shipped.', 'refund_status' => '', 'order_id' => $order_id);
    }

    $reason_clean = trim(mb_substr((string) $reason, 0, 500));

    // ── Persist cancellation ────────────────────────────────────────────
    db(
        "UPDATE orders SET
            status              = 'cancelled',
            cancelled_at        = '" . time() . "',
            cancelled_by        = '" . (int) $user_id . "',
            cancellation_reason = '" . escape($reason_clean) . "'
         WHERE id = '" . e($order_id) . "'"
    );

    // ── Expire gift cards issued by this order ──────────────────────────
    // Folded in from the removed legacy cancel_order(). A cancelled order
    // must not leave spendable gift cards behind, so we push their
    // expiration to yesterday (immediately invalid) and leave an audit note.
    // Rows that already expired earlier are left untouched.
    $yesterday   = date('Y-m-d', strtotime('-1 day'));
    $cancel_note = escape(lang('This gift card was automatically expired because the associated order was cancelled.'));
    db(
        "UPDATE gift_cards
         SET
            expiration_date = '" . $yesterday . "',
            notes = CONCAT(IF(notes IS NULL OR notes = '', '', CONCAT(notes, '\n')), '" . $cancel_note . "')
         WHERE order_id = '" . e($order_id) . "'
           AND (expiration_date = '0000-00-00' OR expiration_date > '" . $yesterday . "')"
    );

    log_activity(
        'Order #' . $order_id . ' cancelled by ' .
        ($is_admin ? 'admin' : 'customer') . ' (user_id=' . (int) $user_id . ').' .
        ($reason_clean !== '' ? ' Reason: ' . $reason_clean : '')
    );

    // ── Iyzipay auto-refund (opt-in) ────────────────────────────────────
    // Default OFF. When ECOMMERCE_ORDER_CANCEL_AUTO_REFUND === true and the
    // payment was made with Iyzipay, attempt a void via the Cancel API.
    // Failures here MUST NOT roll back the cancellation — refunds can be
    // retried manually from the gateway dashboard. We just record the
    // outcome on the order row so the operator can audit.
    $refund_status_outcome = '';
    $refund_reference      = '';
    $refund_columns_exist  = _orders_has_refund_columns();

    // $attempt_refund overrides the config define when the caller is explicit
    // (admin detail screen: the operator confirmed a modal that says the
    // payment will be voided, so honour that regardless of the site default).
    if ($attempt_refund === null) {
        $auto_refund = defined('ECOMMERCE_ORDER_CANCEL_AUTO_REFUND') && ECOMMERCE_ORDER_CANCEL_AUTO_REFUND === true;
    } else {
        $auto_refund = (bool) $attempt_refund;
    }
    $is_iyzipay  = in_array($order['payment_method'], array('Iyzipay', 'Pay With Iyzico'), true);
    $has_txn     = !empty($order['transaction_id']);
    $order_total = (int) $order['total']; // cents

    if ($auto_refund && $is_iyzipay && $has_txn && defined('ECOMMERCE_IYZIPAY_API_KEY') && $order_total > 0) {
        try {
            require_once(PG_FUNCTIONS_DIR . '/includes/iyzipay-php/IyzipayBootstrap.php');
            IyzipayBootstrap::init();

            $gateway_host = (defined('ECOMMERCE_PAYMENT_GATEWAY_MODE') && ECOMMERCE_PAYMENT_GATEWAY_MODE == 'test')
                ? 'https://sandbox-api.iyzipay.com'
                : 'https://api.iyzipay.com';

            $options = new \Iyzipay\Options();
            $options->setApiKey(ECOMMERCE_IYZIPAY_API_KEY);
            $options->setSecretKey(ECOMMERCE_IYZIPAY_SECRET_KEY);
            $options->setBaseUrl($gateway_host);

            $request = new \Iyzipay\Request\CreateCancelRequest();
            $request->setLocale(\Iyzipay\Model\Locale::TR);
            $request->setConversationId((string) $order_id . '-' . time());
            $request->setPaymentId((string) $order['transaction_id']);
            $request->setIp(isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '127.0.0.1');

            if ($reason_clean !== '') {
                $request->setDescription(mb_substr($reason_clean, 0, 240));
            }

            $cancel = \Iyzipay\Model\Cancel::create($request, $options);

            if ($cancel->getStatus() == 'success') {
                $refund_status_outcome = 'refunded';
                $refund_reference      = (string) ($cancel->getPaymentId() ?: $order['transaction_id']);
                log_activity('Auto-refund SUCCESS via Iyzipay — order #' . $order_id . ' (payment ' . $refund_reference . ').');
            } else {
                $refund_status_outcome = 'failed';
                $err_msg = method_exists($cancel, 'getErrorMessage') ? (string) $cancel->getErrorMessage() : '';
                log_activity(
                    'Auto-refund FAILED via Iyzipay — order #' . $order_id .
                    '. MANUAL REFUND REQUIRED. Gateway message: ' . ($err_msg !== '' ? $err_msg : '(no message)')
                );
                $refund_status_outcome = 'manual_required';
            }
        } catch (\Throwable $e) {
            // PHP 7+ — Throwable catches both Exception and Error. Keeps the
            // cancellation persisted; refund just needs operator follow-up.
            $refund_status_outcome = 'manual_required';
            log_activity('Auto-refund EXCEPTION — order #' . $order_id . '. MANUAL REFUND REQUIRED. ' . $e->getMessage());
        } catch (\Exception $e) {
            $refund_status_outcome = 'manual_required';
            log_activity('Auto-refund EXCEPTION — order #' . $order_id . '. MANUAL REFUND REQUIRED. ' . $e->getMessage());
        }
    } elseif ($order_total > 0 && (string) $order['payment_method'] !== '' && (string) $order['payment_method'] !== 'Offline Payment') {
        // No auto-refund — but money did change hands, so flag for the operator.
        $refund_status_outcome = 'manual_required';
        log_activity(
            'REFUND ACTION REQUIRED — order #' . $order_id . ' was cancelled. ' .
            'Payment method: ' . $order['payment_method'] . '. ' .
            'Amount: ' . number_format($order_total / 100, 2, '.', ',') . '. ' .
            'Process refund in the gateway dashboard.'
        );
    }

    if ($refund_columns_exist && $refund_status_outcome !== '') {
        $refunded_at_sql = ($refund_status_outcome === 'refunded') ? "'" . time() . "'" : 'NULL';
        db(
            "UPDATE orders SET
                refund_status    = '" . escape($refund_status_outcome) . "',
                refunded_at      = " . $refunded_at_sql . ",
                refund_reference = '" . escape($refund_reference) . "'
             WHERE id = '" . e($order_id) . "'"
        );
    }

    // ── Customer email notification ─────────────────────────────────────
    // Defensive: a flaky SMTP server must not block the cancel itself.
    $email_enabled = !defined('ECOMMERCE_CANCEL_EMAIL_NOTIFICATION')
        || ECOMMERCE_CANCEL_EMAIL_NOTIFICATION !== false;
    if ($email_enabled) {
        try {
            _send_order_cancellation_email($order, $reason_clean, $refund_status_outcome);
        } catch (\Throwable $e) {
            log_activity('Cancellation email failed for order #' . $order_id . ': ' . $e->getMessage());
        } catch (\Exception $e) {
            log_activity('Cancellation email failed for order #' . $order_id . ': ' . $e->getMessage());
        }
    }

    // One place, so every cancellation reports itself: the panel, the customer's
    // own self-service, and the API all arrive here.
    require_once(PG_FUNCTIONS_DIR . '/includes/api/outbound/webhooks.php');

    api_webhook_enqueue('order.cancelled', array(
        'id'            => $order_id,
        'order_number'  => $order['order_number'],
        'reason'        => $reason_clean,
        'refund_status' => $refund_status_outcome
    ));

    return array(
        'status'        => 'success',
        'message'       => 'Order cancelled.',
        'refund_status' => $refund_status_outcome,
        'order_id'      => $order_id,
    );
}

/**
 * Has this order physically gone out?
 *
 * The operator's workflow is the source of truth here: an order counts as
 * shipped the moment a TRACKING CODE is recorded — not when a ship_date is
 * filled in. ship_tos.ship_date is a *planned* dispatch date that gets set at
 * order time on many configurations, so gating on it blocked cancellation of
 * orders that had not left the building yet. It is deliberately ignored.
 *
 * Two stores hold tracking data and either one counts:
 *   1. orders.tracking_code             — single code for the whole order
 *                                          (what the admin list column and the
 *                                          order_view __tracking_code token use)
 *   2. shipping_tracking_numbers.number — per-ship_to codes, used by
 *                                          multi-recipient orders
 *
 * Only the CUSTOMER-facing cancel path consults this. Admins cancel regardless
 * (see process_order_cancellation()).
 *
 * @param  int  $order_id
 * @return bool
 */
function _order_has_shipped($order_id)
{
    $order_id = (int) $order_id;
    if ($order_id <= 0) return false;

    // Order-level code.
    $code = (string) db_value(
        "SELECT tracking_code FROM orders WHERE id = '" . e($order_id) . "' LIMIT 1"
    );
    if (trim($code) !== '') return true;

    // Per-recipient codes. Blank rows don't count as shipped.
    $per_recipient = (int) db_value(
        "SELECT COUNT(*) FROM shipping_tracking_numbers
         WHERE order_id = '" . e($order_id) . "'
           AND TRIM(number) != ''"
    );

    return $per_recipient > 0;
}

/**
 * Probe orders.refund_status — true only after the 2026.1.27 upgrade has run.
 * Cached per-request so we don't issue SHOW COLUMNS on every cancel.
 */
function _orders_has_refund_columns()
{
    static $cached = null;
    if ($cached !== null) return $cached;
    $row = db_item("SHOW COLUMNS FROM orders LIKE 'refund_status'");
    $cached = !empty($row);
    return $cached;
}

/**
 * Send a Turkish-localised cancellation email to the order's billing address.
 * Skips silently when the order has no billing email or required globals
 * (EMAIL_ADDRESS / ORGANIZATION_NAME) aren't configured.
 */
function _send_order_cancellation_email($order, $reason, $refund_status)
{
    $to = isset($order['billing_email_address']) ? trim((string) $order['billing_email_address']) : '';
    if ($to === '' || strpos($to, '@') === false) {
        return false;
    }
    if (!defined('EMAIL_ADDRESS') || EMAIL_ADDRESS === '') {
        return false;
    }

    $from_name = defined('ORGANIZATION_NAME') && ORGANIZATION_NAME !== ''
        ? ORGANIZATION_NAME
        : (defined('HOSTNAME_SETTING') ? HOSTNAME_SETTING : 'Pinegrap');

    $order_id     = (int) $order['id'];
    $order_number = isset($order['order_number']) && $order['order_number'] !== ''
        ? (string) $order['order_number']
        : (string) $order_id;
    $total_amount = isset($order['total']) ? (int) $order['total'] : 0;
    $amount_str   = $total_amount > 0 ? number_format($total_amount / 100, 2, ',', '.') : '';
    $customer     = trim((string) ($order['billing_first_name'] ?? '') . ' ' . (string) ($order['billing_last_name'] ?? ''));

    $subject = lang(array(
        'string' => 'Your order #{var:1} has been cancelled',
        'vars'   => array($order_number),
    ));

    $refund_paragraph = '';
    if ($refund_status === 'refunded') {
        $refund_paragraph = '<p style="margin:0 0 12px;">'
            . h(lang('Your payment has been refunded to the original payment method. Bank settlement may take a few business days.'))
            . '</p>';
    } elseif ($refund_status === 'manual_required' || $refund_status === 'failed' || $refund_status === 'pending') {
        $refund_paragraph = '<p style="margin:0 0 12px;">'
            . h(lang('Payment refunds are processed manually. Please contact us for refund status.'))
            . '</p>';
    }

    $reason_paragraph = '';
    if ($reason !== '') {
        $reason_paragraph = '<p style="margin:0 0 12px;"><strong>' . h(lang('Cancellation reason')) . ':</strong> ' . h($reason) . '</p>';
    }

    $amount_paragraph = '';
    if ($amount_str !== '') {
        $amount_paragraph = '<p style="margin:0 0 12px;"><strong>' . h(lang('Order Total')) . ':</strong> ' . h($amount_str) . '</p>';
    }

    $body = '<!doctype html><html><body style="font-family:Arial,Helvetica,sans-serif;font-size:14px;color:#222;background:#f7f7f7;padding:20px;">'
        . '<div style="max-width:560px;margin:0 auto;background:#fff;padding:24px;border-radius:6px;border:1px solid #e5e5e5;">'
        . '<h2 style="margin:0 0 16px;font-size:18px;color:#b02a37;">' . h(lang('Order Cancelled')) . '</h2>'
        . '<p style="margin:0 0 12px;">' . h(lang('Hello')) . ($customer !== '' ? ' ' . h($customer) : '') . ',</p>'
        . '<p style="margin:0 0 12px;">'
        . h(lang(array(
            'string' => 'Your order #{var:1} has been cancelled.',
            'vars'   => array($order_number),
        ))) . '</p>'
        . $amount_paragraph
        . $reason_paragraph
        . $refund_paragraph
        . '<p style="margin:16px 0 0;color:#666;font-size:12px;">' . h($from_name) . '</p>'
        . '</div></body></html>';

    return email(array(
        'to'                 => $to,
        'to_name'            => $customer,
        'from_name'          => $from_name,
        'from_email_address' => EMAIL_ADDRESS,
        'subject'            => $subject,
        'format'             => 'html',
        'body'               => $body,
        'type'               => 'system',
    ));
}

/**
 * Cancels (voids) an Iyzipay payment that must not complete an order, for example when the
 * amount confirmed by the gateway no longer matches the order. Returns true when the gateway
 * accepted the cancel. The outcome is always written to the activity log, so a cancel that the
 * gateway refused can be refunded by hand.
 *
 * @param \Iyzipay\Options $options         Configured gateway options.
 * @param string           $payment_id      Gateway payment id to cancel.
 * @param string           $conversation_id Conversation id of the payment.
 * @param string           $reason          Short English reason for the log and the gateway.
 * @return bool
 */
function iyzipay_cancel_payment($options, $payment_id, $conversation_id, $reason)
{
    $payment_id = (string) $payment_id;
    $reason = (string) $reason;

    if ($payment_id === '') {
        log_activity('Iyzipay payment could not be cancelled because the payment id is empty (' . $reason . ').');
        return false;
    }

    try {
        require_once(PG_FUNCTIONS_DIR . '/includes/iyzipay-php/IyzipayBootstrap.php');
        IyzipayBootstrap::init();

        $request = new \Iyzipay\Request\CreateCancelRequest();
        $request->setLocale(\Iyzipay\Model\Locale::TR);
        $request->setConversationId((string) $conversation_id);
        $request->setPaymentId($payment_id);
        $request->setIp(isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '127.0.0.1');
        $request->setDescription(mb_substr($reason, 0, 240));

        $cancel = \Iyzipay\Model\Cancel::create($request, $options);

        if ($cancel->getStatus() == 'success') {
            log_activity('Iyzipay payment ' . $payment_id . ' was cancelled: ' . $reason . '.');
            return true;
        }

        $error_message = method_exists($cancel, 'getErrorMessage') ? (string) $cancel->getErrorMessage() : '';
        log_activity(
            'Iyzipay payment ' . $payment_id . ' could not be cancelled (' . $reason . '). MANUAL REFUND REQUIRED. ' .
            'Gateway message: ' . ($error_message !== '' ? $error_message : '(no message)')
        );
    } catch (\Throwable $e) {
        log_activity('Iyzipay payment ' . $payment_id . ' could not be cancelled (' . $reason . '). MANUAL REFUND REQUIRED. ' . $e->getMessage());
    }

    return false;
}
