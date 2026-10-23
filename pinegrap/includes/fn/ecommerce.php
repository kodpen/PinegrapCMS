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
            AND (added_by_offer = '0')" . pg_order_item_active_sql();
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
                    AND (recurrence_number = '" . escape($recurrence_number) . "')" . pg_order_item_active_sql();
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
                    AND (recurrence_number = '" . escape($recurrence_number) . "')" . pg_order_item_active_sql();
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

            AND (added_by_offer = '0')" . pg_order_item_active_sql() . $sql_products);
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

                AND (added_by_offer = '0')" . pg_order_item_active_sql());
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
// Does this recipient hold a line the shopper pays for? The checkout refuses
// a free item that is alone in a recipient (shopping_cart.php, submit_order.php
// look for a line with a price above 0 in the same ship_to), so a free gift
// is only placed in, or left in, a recipient that has one. $except_item_id
// leaves out the gift line itself when it is the one being weighed.
function pg_ship_to_has_paid_line($ship_to_id, $except_item_id = 0)
{
    $order_id = (int) ($_SESSION['ecommerce']['order_id'] ?? 0);
    if (($order_id <= 0) || ((int) $ship_to_id <= 0)) {
        return false;
    }
    $sql_except = ((int) $except_item_id > 0) ? " AND (id != '" . e($except_item_id) . "')" : '';
    return (bool) db_value("SELECT id FROM order_items

        WHERE

            (order_id = '" . e($order_id) . "')

            AND (ship_to_id = '" . e($ship_to_id) . "')

            AND (price > 0)" . $sql_except . pg_order_item_active_sql() . "

        LIMIT 1");
}
// The recipient the cart gives every shippable product when the site ships
// to one address: the buyer's own, the one create_or_get_ship_to('', '')
// finds or opens. Looked up here without opening it, so a caller can ask
// what it holds before deciding to put something there; 0 when the cart has
// not opened it yet.
function pg_single_recipient_ship_to_id()
{
    $order_id = (int) ($_SESSION['ecommerce']['order_id'] ?? 0);
    if ($order_id <= 0) {
        return 0;
    }
    return (int) db_value("SELECT id FROM ship_tos

        WHERE (order_id = '" . e($order_id) . "') AND (ship_to_name = '" . escape(lang('myself')) . "')");
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

// Remember that the shopper took an offer's gift out of this order, so the
// next cart refresh does not put it straight back (pg_offer_gift_refused()).
function pg_offer_gift_refuse($order_id, $offer_id, $offer_action_id)
{
    if (empty($_SESSION['ecommerce']['declined_offer_gifts']) || !is_array($_SESSION['ecommerce']['declined_offer_gifts'])) {
        $_SESSION['ecommerce']['declined_offer_gifts'] = array();
    }
    $key = $order_id . '_' . $offer_id . '_' . $offer_action_id;
    if (!in_array($key, $_SESSION['ecommerce']['declined_offer_gifts'])) {
        $_SESSION['ecommerce']['declined_offer_gifts'][] = $key;
    }
}

// The shopper took a line out of the cart: a quantity of 0 on the cart
// screens. A gift the offer placed by itself is remembered as refused, then
// the line goes the way every removal does (remove_order_item()): with its
// gift card and form rows, with its recipient when it was the last line
// there, and otherwise with the recipient's shipping worked out again.
function pg_cart_remove_line($order_item_id)
{
    $line = db_item("SELECT order_id, offer_id, offer_action_id, added_by_offer FROM order_items WHERE id = '" . e($order_item_id) . "'");
    if (!$line) {
        return;
    }
    if ($line['added_by_offer'] && $line['offer_id']) {
        pg_offer_gift_refuse($line['order_id'], $line['offer_id'], $line['offer_action_id']);
    }
    remove_order_item($order_item_id);
}

// Put the product an offer gives away into the cart.
//
// The offer already qualified; this only decides how the gift shows up. When
// the shopper is already paying for that product - a required product added
// itself, or they simply put one in the basket - the gift is a discount on as
// many units of that line as the offer gives away, not a second line of the
// same thing. Otherwise the product is
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

            products.price AS product_price,

            products.shippable AS product_shippable

        FROM offer_actions

        LEFT JOIN products ON offer_actions.add_product_product_id = products.id

        WHERE offer_actions.id = '" . e($offer_action_id) . "'");
    // The action no longer adds anything, or the product is gone.
    if (!$action || !$action['add_product_product_id'] || !$action['add_product_quantity'] || ($action['product_name'] === NULL)) {
        return true;
    }
    $discounted_price = pg_offer_gift_price($action['product_price'], $action['add_product_discount_amount'], $action['add_product_discount_percentage']);
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
    //
    // The line's own offer action tells whether its unit price is an average:
    // "the cheapest item is free" and an earlier gift discount only some of
    // the units and spread that over the line.
    $paid_order_item = db_item("SELECT

            order_items.id,

            order_items.quantity,

            order_items.price,

            order_items.discounted_by_offer,

            offer_actions.type AS line_offer_action_type,

            offer_actions.discount_product_target AS line_offer_action_target

        FROM order_items

        LEFT JOIN offer_actions ON order_items.offer_action_id = offer_actions.id

        WHERE

            (order_items.order_id = '" . e($order_id) . "')

            AND (order_items.product_id = '" . e($action['add_product_product_id']) . "')

            AND (order_items.added_by_offer = '0')" . pg_order_item_active_sql() . $sql_ship_to . "

        ORDER BY order_items.id ASC LIMIT 1");
    if ($paid_order_item) {
        // Note this happens even when the shopper refused the free copy: they
        // are buying this one, and the offer's discount is theirs.
        //
        // The gift is the action's quantity, not the whole line: the other
        // units keep the price they stand at, and a gift never raises that
        // price. That price is the line's unit price, unless the line already
        // carries a partly discounted unit spread over it (the cheapest item,
        // an earlier gift): then the paid units stand at the list price, or
        // the member's agreed price, and the gift is taken off the line's
        // total at that price (pg_offer_gift_line_unit_price()).
        $paid_unit_price = (int) $paid_order_item['price'];
        if (($paid_order_item['discounted_by_offer'] == 1)
            && (($paid_order_item['line_offer_action_type'] == 'add product') || (pg_offer_action_target(array('discount_product_target' => $paid_order_item['line_offer_action_target'])) == 'cheapest'))) {
            $member_prices = pg_erp_member_prices();
            $paid_unit_price = isset($member_prices[(int) $action['add_product_product_id']]) ? (int) $member_prices[(int) $action['add_product_product_id']]['price'] : (int) $action['product_price'];
        }
        $line_price = pg_offer_gift_line_unit_price($paid_order_item['price'], $paid_order_item['quantity'], $paid_unit_price, $discounted_price, $action['add_product_quantity']);
        db("UPDATE order_items

            SET

                price = '" . e($line_price) . "',

                offer_id = '" . e($offer_id) . "',

                offer_action_id = '" . e($offer_action_id) . "',

                discounted_by_offer = '1'

            WHERE id = '" . e($paid_order_item['id']) . "'");
        return true;
    }

    // A gift the shopper took out of this order is not pushed back in.
    if (pg_offer_gift_refused($offer_id, $offer_action_id)) {
        return true;
    }

    // With one recipient a shippable gift goes to the buyer's recipient, the
    // one add_order_item() gives every shippable product, so it is weighed,
    // charged shipping and checked against the destination with the rest.
    // Found or created only here, once the gift is placed, so a gift that is
    // not placed leaves no empty recipient behind.
    //
    // A free gift has to travel with a paid line: the checkout refuses a free
    // item alone in a recipient, and the shopper could not place the order
    // without taking the gift out. When the recipient holds nothing paid for
    // (only unshipped items in the cart, say) the gift is not placed; the
    // caller keeps the offer among the pending ones and the claim button asks,
    // as the multi-recipient branch does through get_ship_to_id_for_offer_gift().
    if (!$ship_to_id && (ECOMMERCE_SHIPPING == true) && (ECOMMERCE_RECIPIENT_MODE == 'single recipient') && ($action['product_shippable'] == 1)) {
        if (($discounted_price <= 0) && !pg_ship_to_has_paid_line(pg_single_recipient_ship_to_id())) {
            return false;
        }
        $ship_to_id = create_or_get_ship_to('', '');
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

/**
 * Whether $page_id names a page that a checkout step of $page_type ('express
 * order', 'order preview', 'billing information') is posted from: a page of
 * that type or, for an express order, a page that carries the designer's
 * express order widget (the widget gives its page an express_order_pages row).
 * The step reads that page's settings by this id: required custom fields and
 * forms, terms, the order receipt e-mail and the next page. For any other id
 * it would carry on without them. Only a string of digits can name a page, so
 * an array, an empty value or 0 is turned away before any query runs.
 *
 * @param mixed  $page_id
 * @param string $page_type
 * @return bool
 */
function pg_order_page_known($page_id, $page_type)
{
    if (is_int($page_id)) {
        $page_id = (string) $page_id;
    }

    if (!is_string($page_id) || !ctype_digit($page_id) || ((int) $page_id <= 0)) {
        return false;
    }

    if ($page_type === 'express order') {
        return (bool) db(
            "SELECT page.page_id
            FROM page
            LEFT JOIN express_order_pages ON express_order_pages.page_id = page.page_id
            WHERE
                (page.page_id = '" . e($page_id) . "')
                AND ((page.page_type = 'express order') OR (express_order_pages.page_id IS NOT NULL))
            LIMIT 1");
    }

    return (bool) db(
        "SELECT page_id FROM page
        WHERE (page_id = '" . e($page_id) . "') AND (page_type = '" . e($page_type) . "')
        LIMIT 1");
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
 * The payment is the moment a store that invoices paid orders on their own
 * gets this one's invoice (pg_erp_auto_invoice()).
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

    log_activity(pg_order_paid_log_text($order_number, $user_id));

    pg_erp_auto_invoice($order_id, 'order_paid');

    return true;
}

/**
 * The activity log line of a payment recorded on an order. Who recorded it
 * is the log's user column (the signed-in operator); the line only says
 * whether an operator pressed Payment Received or the software marked the
 * order paid itself (an ERP receipt that settled the order's invoice).
 *
 * @param string $order_number
 * @param int    $user_id  Operator recording the payment; 0 for a system path
 * @return string
 */
function pg_order_paid_log_text(string $order_number, int $user_id): string
{
    if ($user_id > 0) {
        return lang(array('string' => 'Order (#{var:1}) was marked paid.', 'vars' => array($order_number)));
    }

    return lang(array('string' => 'Order (#{var:1}) was marked paid automatically.', 'vars' => array($order_number)));
}

/**
 * Issue an order's invoice on its own, when the store chose to (ERP settings,
 * "The invoice of a web order": config.erp_auto_invoice_on) and this is the
 * moment it chose.
 *
 * Called where the moment happens: the end of the checkout (paid by card,
 * PayPal or iyzico, put on the customer's account, or nothing left to pay),
 * pg_order_mark_paid() (a bank transfer that arrived) and update_order() (a
 * recipient that just shipped). Nothing is read and no ERP file is loaded
 * unless the store invoices at exactly this moment; the rest is
 * erp_auto_invoice_for_order() (includes/erp/order_bridge.php).
 *
 * The checkout passes $after_response: the customer's page is answered first
 * where the server allows it. Inside an open ERP transaction (a receipt that
 * marks its order paid) it waits for the end of the request as well, because
 * a refusal there would roll back the receipt with it.
 *
 * Nothing it does stops the caller: an order that could not be invoiced is
 * logged and announced on the panel bell, and an error is written to the
 * error log.
 *
 * @param int    $order_id
 * @param string $trigger         'order_paid' | 'order_shipped'
 * @param bool   $after_response  Run once the response is out
 * @return array|null  erp_auto_invoice_for_order()'s answer when it ran now;
 *                     null when it did not run, or runs at the end
 */
function pg_erp_auto_invoice($order_id, $trigger, $after_response = false)
{
    $order_id = (int) $order_id;
    $trigger = (string) $trigger;

    if (($order_id <= 0) || !defined('ERP_ENABLED') || !ERP_ENABLED
        || !defined('ERP_AUTO_INVOICE_ON') || ((string) ERP_AUTO_INVOICE_ON !== $trigger)) {
        return null;
    }

    if ($after_response || (function_exists('erp_tx_depth') && (erp_tx_depth() > 0))) {
        pg_erp_auto_invoice_defer($order_id, $trigger);

        return null;
    }

    return pg_erp_auto_invoice_run($order_id, $trigger);
}

/**
 * pg_erp_auto_invoice() at the end of the request, for every order it was
 * asked about, from one shutdown function.
 *
 * The session is let go and the page answered first (where the server has
 * fastcgi_finish_request()), so the page the visitor opens next - the
 * receipt - waits neither for the invoice nor for its PDF. The queue is
 * emptied as it is taken: an order asked about while it runs gets a shutdown
 * function of its own.
 *
 * @param int    $order_id
 * @param string $trigger
 * @return void
 */
function pg_erp_auto_invoice_defer($order_id, $trigger)
{
    static $queue = null;

    if ($queue === null) {
        $queue = array();

        register_shutdown_function(function () use (&$queue) {
            $entries = (array) $queue;
            $queue = null;

            if (empty($entries)) {
                return;
            }

            if (session_status() === PHP_SESSION_ACTIVE) {
                session_write_close();
            }

            if (function_exists('fastcgi_finish_request')) {
                fastcgi_finish_request();
            }

            // A host can disable it, and PHP 8 then has no such function.
            if (function_exists('ignore_user_abort')) {
                @ignore_user_abort(true);
            }

            foreach ($entries as $entry) {
                pg_erp_auto_invoice_run($entry[0], $entry[1]);
            }
        });
    }

    $queue[(int) $order_id . ':' . (string) $trigger] = array((int) $order_id, (string) $trigger);
}

/**
 * Load the ERP and invoice the order, catching anything that goes wrong on
 * the way: whatever the caller was doing is done already and stays done.
 *
 * Refused inside an open ERP transaction: at the end of a request that can
 * only be one a failure left open, and joining it would commit or roll back
 * somebody else's half-written work.
 *
 * @param int    $order_id
 * @param string $trigger
 * @return array|null  erp_auto_invoice_for_order()'s answer, null when it did not run
 */
function pg_erp_auto_invoice_run($order_id, $trigger)
{
    try {
        require_once(PG_FUNCTIONS_DIR . '/includes/erp/bootstrap.php');

        if (erp_tx_depth() > 0) {
            return null;
        }

        return erp_auto_invoice_for_order((int) $order_id, (string) $trigger);
    } catch (Throwable $error) {
        if (function_exists('pg_error_write') && function_exists('pg_error_entry')) {
            pg_error_write(pg_error_entry(
                'error',
                get_class($error),
                'Automatic invoice for order ' . (int) $order_id . ': ' . $error->getMessage(),
                $error->getFile(),
                $error->getLine(),
                pg_error_trace_short($error->getTrace())));
        }

        return null;
    }
}

/**
 * The order list's column that changes in place (pg_list_edit_table()): the
 * status, between complete and exported. Those two are the moves that are
 * only a status write; the export makes the same one. Cancelling is not:
 * process_order_cancellation() asks for a reason, refunds and mails the
 * customer, so it stays on the row's Cancel button with its confirmation.
 * Bringing a cancelled order back stays on the order screen's Restore, and
 * an incomplete order is a cart nobody has checked out.
 *
 * @return array field => definition
 */
function pg_order_quick_edit_columns()
{
    return array(
        'status' => array('type' => 'select', 'label' => lang('Status'), 'options' => array('complete' => lang('Complete'), 'exported' => lang('Exported'))),
    );
}

/**
 * The order list's status cell, drawn the one way the list and the answer to
 * a quick edit both use: the status badge, "Awaiting Payment" for a bank
 * transfer whose money has not arrived, and the Parasut mark.
 *
 * @param array $order      status, payment_method, paid_at, parasut_exported
 *                          and parasut_invoice_id of an order
 * @param bool  $paid_known whether orders.paid_at exists (the 2026.4.4
 *                          upgrade); without it nothing is known to be unpaid
 * @return string
 */
function pg_order_list_status_html($order, $paid_known)
{
    $status = (string) ($order['status'] ?? '');
    $html = '';

    if ($status == 'incomplete') {
        $html = '<span class="badge bg-warning text-dark bg-gradient fw-light">' . lang('Incomplete') . '</span>';
    } elseif ($status == 'complete') {
        $html = '<span class="badge bg-primary bg-gradient fw-light">' . lang('Complete') . '</span>';
    } elseif ($status == 'exported') {
        $html = '<span class="badge bg-success bg-gradient fw-light">' . lang('Exported') . '</span>';
    } elseif (($status == 'canceled') || ($status == 'cancelled')) {
        $html = '<span class="badge bg-danger bg-gradient fw-light">' . lang('Cancelled') . '</span>';
    }

    // A bank transfer order whose money has not arrived is complete but not
    // paid; a second badge says so without changing the status itself.
    if ($paid_known && pg_order_awaiting_payment($order)) {
        $html .= '<span class="badge bg-warning text-dark bg-gradient fw-light ms-1">' . lang('Awaiting Payment') . '</span>';
    }

    // parasut_exported is set by two different things: the spreadsheet export
    // and an invoice raised through the API. Only parasut_invoice_id tells
    // them apart, so the tooltip is chosen from that rather than claiming an
    // invoice exists for an order that was only exported to a file.
    $parasut_invoice_id = trim((string) ($order['parasut_invoice_id'] ?? ''));

    if (((int) ($order['parasut_exported'] ?? 0) != 0) || ($parasut_invoice_id !== '')) {
        $title = ($parasut_invoice_id !== '')
            ? lang('An invoice was created for this order in Parasut')
            : lang('This order has been exported for upload to parasut.com');
        $html .= '<span title="' . h($title) . '" class="badge bg-transparent bg-gradient fw-light"><img width="20px" height="auto" src="assets/images/parasut-emblem.png"></span>';
    }

    return $html;
}

/**
 * Write one quick edit from the order list: the status moved between the
 * ones pg_order_quick_edit_columns() offers, the way the API and the
 * Workspace move it. The order is marked modified, the Site Log gets the old
 * and the new status and order.status_changed is announced (the Workspace
 * and the webhooks). The customer is not mailed: no screen mails on this
 * move.
 *
 * The write only lands while the order still has the status it was read
 * with, so a cancellation that arrives in between is not overwritten.
 *
 * @param int    $order_id
 * @param string $field  a key of pg_order_quick_edit_columns()
 * @param string $value  as pg_list_edit_parse() read it
 * @return array the answer for pg_list_edit_reply()
 */
function pg_order_quick_update($order_id, $field, $value)
{
    $order_id = (int) $order_id;
    $columns = pg_order_quick_edit_columns();

    if (!isset($columns[$field]['options'][(string) $value])) {
        return array('ok' => false, 'code' => 'invalid', 'message' => lang('This column cannot be changed here.'));
    }

    $read = function () use ($order_id) {
        return db_item("SELECT * FROM orders WHERE id = '" . $order_id . "'");
    };

    $order = $read();

    if (!$order) {
        return array('ok' => false, 'code' => 'not_found', 'message' => lang('Order not found.'));
    }

    $previous = (string) $order['status'];

    if (!isset($columns['status']['options'][$previous])) {
        return array('ok' => false, 'code' => 'status', 'message' => lang('Only a complete or exported order changes status here. Cancel an order with its Cancel Order button; bring a cancelled order back with Restore Order on the order screen.'));
    }

    // The same status again writes nothing and logs nothing; the cell is
    // still redrawn from what is stored.
    if ($previous !== (string) $value) {
        db("UPDATE orders
            SET status = '" . e($value) . "', last_modified_timestamp = UNIX_TIMESTAMP()
            WHERE id = '" . $order_id . "' AND status = '" . e($previous) . "'");

        $written = (mysqli_affected_rows(db::$con) > 0);
        $order = $read();

        if (!$written || !$order || ((string) $order['status'] !== (string) $value)) {
            return array('ok' => false, 'code' => 'changed', 'message' => lang('The order changed in the meantime. Reload the list and try again.'));
        }

        log_activity(
            lang(array(
                'string' => 'order ({var:1}) was changed on the order list: {var:2} from "{var:3}" to "{var:4}"',
                'vars'   => array((string) $order['order_number'], $columns['status']['label'], $columns['status']['options'][$previous], $columns['status']['options'][(string) $value]),
            )),
            $_SESSION['sessionusername'] ?? '');

        pg_announce('order.status_changed', array(
            'id'           => $order_id,
            'order_number' => $order['order_number'],
            'status'       => (string) $value,
            'previous'     => $previous,
        ));
    }

    return array(
        'ok'    => true,
        'value' => (string) $order['status'],
        'html'  => pg_order_list_status_html($order, array_key_exists('paid_at', $order)),
        'class' => 'align-middle',
    );
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

             WHERE order_id = '" . ($_SESSION['ecommerce']['order_id'] ?? '') . "' AND ship_to_id = '" . escape($ship_to_id) . "'" . pg_order_item_active_sql();
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

// The condition that keeps a query to the lines that are in the cart. A line
// the shopper saved for later (order_items.saved_for_later, cart_action.php)
// stays on the order but is not listed, totalled, taxed, counted towards an
// offer, a shipping rate, a stock or a reservation check, or charged.
// Appended to a WHERE clause; $table is the name or alias order_items has in
// the query. Empty while the column does not exist yet (2026.1.28).
function pg_order_item_active_sql($table = 'order_items')
{
    if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', (string) $table) !== 1) {
        $table = 'order_items';
    }
    if (!pg_schema_has('order_items', 'saved_for_later')) {
        return '';
    }
    return " AND (" . $table . ".saved_for_later = 0)";
}

// Takes the saved-for-later lines off an order that has just been placed,
// with their gift card and product form rows. They were not charged, and the
// shopper cannot reach them once the order has left the session, so the
// placed order holds what was bought: receipts, print-outs, the API, reorders
// and reviews read its lines without knowing about the flag. Recipients and
// shipping stay as they were charged.
function pg_order_drop_saved_lines($order_id)
{
    $order_id = (int) $order_id;
    if (($order_id <= 0) || !pg_schema_has('order_items', 'saved_for_later')) {
        return;
    }
    $ids = array_map('intval', (array) db_values("SELECT id FROM order_items WHERE (order_id = '" . $order_id . "') AND (saved_for_later = 1)"));
    if (!$ids) {
        return;
    }
    $list = implode(', ', $ids);
    db("DELETE FROM order_item_gift_cards WHERE order_item_id IN (" . $list . ")");
    db("DELETE FROM form_data WHERE (order_item_id IN (" . $list . ")) AND (order_item_id != '0')");
    db("DELETE FROM order_items WHERE id IN (" . $list . ")");
}

function get_order_subtotal()
{
    // The guest view (pg_offer_guest_view()) has an empty cart.
    if (pg_offer_guest_view()) {
        return 0;
    }
    $query = "SELECT price, quantity FROM order_items WHERE order_id = '" . ($_SESSION['ecommerce']['order_id'] ?? '') . "'" . pg_order_item_active_sql();
    $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
    $order_subtotal = 0;
    while ($row = mysqli_fetch_assoc($result)) {
        $order_subtotal += $row['price'] * $row['quantity'];
    }
    return $order_subtotal;
}

// Whether a cart line is charged when the order is placed: every line but a
// recurring one that starts after $today, and every recurring line on the
// ClearCommerce gateway, which charges recurring payments itself; the test
// submit_order() puts its lines through. A recurring line gets its start date
// written when the order is submitted (express_order.php, shopping_cart.php);
// until then ('0000-00-00' or empty) it starts the product's $start_days after
// today, the date it will be given.
function pg_order_line_charged_today($recurring, $start_date, $start_days, $today, $clearcommerce)
{
    if (!$recurring) {
        return true;
    }
    if ($clearcommerce) {
        return false;
    }
    $start_date = (string) $start_date;
    if (($start_date === '') || ($start_date === '0000-00-00')) {
        $start_date = date('Y-m-d', strtotime($today . ' +' . max(0, (int) $start_days) . ' days'));
    }
    return ($start_date <= (string) $today);
}

// The subtotal of the cart's lines charged today (pg_order_line_charged_today()),
// in minor units.
function pg_order_subtotal_charged_today()
{
    $clearcommerce = (ECOMMERCE_CREDIT_DEBIT_CARD == true) && (ECOMMERCE_PAYMENT_GATEWAY == 'ClearCommerce');
    $today = date('Y-m-d');
    $subtotal = 0;
    foreach ((array) db_items("SELECT

            order_items.price,

            order_items.quantity,

            order_items.recurring_start_date,

            products.recurring,

            products.start

        FROM order_items

        LEFT JOIN products ON order_items.product_id = products.id

        WHERE order_items.order_id = '" . e($_SESSION['ecommerce']['order_id'] ?? '') . "'" . pg_order_item_active_sql()) as $row) {
        if (pg_order_line_charged_today($row['recurring'], $row['recurring_start_date'], $row['start'], $today, $clearcommerce)) {
            $subtotal += $row['price'] * $row['quantity'];
        }
    }
    return $subtotal;
}

// The part of the order discount that today's charge takes off, in minor
// units. The cart works the discount out over every line (get_order_subtotal()),
// but a recurring line that starts later, or any recurring line on the
// ClearCommerce gateway, is not charged today; $subtotal is what is. The
// discount is never more than that subtotal and never below zero, so the
// order total does not drop below its tax and shipping.
function pg_order_charged_discount($discount, $subtotal)
{
    $discount = max(0, (int) round((float) $discount));
    $subtotal = (int) round((float) $subtotal);
    if ($discount > $subtotal) {
        $discount = max(0, $subtotal);
    }
    return $discount;
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
    // The signed-in member's agreed prices (pg_erp_member_prices()): an item
    // no offer has touched costs that member their price, not the list price.
    $member_prices = pg_erp_member_prices();
    // loop through all order items
    foreach ($order_items as $key => $value) {
        // If a product was found for the order item and the product is enabled,
        // then continue to determine if price needs to be updated.
        if (($order_items[$key]['product_id']) && ($order_items[$key]['product_enabled'] == 1)) {
            // if order item was not modified by a special offer and order item is not a donation, update order item price
            if (($order_items[$key]['offer_id'] == 0) && ($order_items[$key]['product_selection_type'] != 'donation')) {
                $price = isset($member_prices[(int) $order_items[$key]['product_id']])
                    ? $member_prices[(int) $order_items[$key]['product_id']]['price']
                    : $order_items[$key]['product_price'];
                $query = "UPDATE order_items

                         SET price = '" . $price . "'

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


/**
 * The low-stock line this site runs, 0 when it runs none.
 *
 * ECOMMERCE_LOW_STOCK_THRESHOLD is set by hand in data/config.php and has no
 * settings screen; the cart widget's warning reads the same constant.
 *
 * @return int
 */
function pg_product_stock_threshold()
{
    return (defined('ECOMMERCE_LOW_STOCK_THRESHOLD') && ((int) ECOMMERCE_LOW_STOCK_THRESHOLD > 0))
        ? (int) ECOMMERCE_LOW_STOCK_THRESHOLD
        : 0;
}


/**
 * What one change of a product's stock announces: the events, in order, with
 * their payloads. Pure, so the writers and the tests read the same rule.
 *
 * inventory.changed goes out on every write of a tracked product, with the
 * same keys the stock API's own writer sends, plus the writer's name as
 * source. stock.low goes out once, on the crossing: a product sitting at two
 * with a threshold of five would otherwise raise it on every sale, and a
 * reorder notice that arrives four times a day stops being read. A product
 * that did not track stock before this write ($before null) had no level to
 * be above the line, so a count at or under it is a crossing - the stock API
 * and the Workspace switch tracking on with a count the same way.
 *
 * A product that does not track stock after the write announces nothing: a
 * gift card sold without a stock limit has no level to report and no low to
 * cross.
 *
 * @param int      $product_id
 * @param string   $name       products.name, carried by stock.low
 * @param bool     $tracked    products.inventory after the write
 * @param int|null $before     stock before the write; null when it was not tracked
 * @param int      $after      stock left after the write
 * @param int      $threshold  the low-stock line, 0 for none (pg_product_stock_threshold())
 * @param string   $source     the writer: checkout, local_sale, panel, barcode, bulk_edit
 * @return array   list of array(event name, payload)
 */
function pg_product_stock_events($product_id, $name, $tracked, $before, $after, $threshold, $source)
{
    $product_id = (int) $product_id;
    $after = (int) $after;
    $threshold = (int) $threshold;

    if (($product_id <= 0) || !$tracked) {
        return array();
    }

    $events = array(
        array('inventory.changed', array(
            'product_id'   => $product_id,
            'quantity'     => $after,
            'out_of_stock' => ($after <= 0),
            'source'       => (string) $source,
        )),
    );

    $was_above = ($before === null) || ((int) $before > $threshold);

    if (($threshold > 0) && ($after <= $threshold) && $was_above) {
        $events[] = array('stock.low', array(
            'product_id' => $product_id,
            'name'       => (string) $name,
            'quantity'   => $after,
            'threshold'  => $threshold,
        ));
    }

    return $events;
}


/**
 * Whether a save that may have touched a product's stock moved it, and what
 * to hand pg_product_stock_changed(): array($before, $after), or null when
 * there is nothing to announce. For the screens that write the whole row
 * (the product screen, the bulk edit) and cannot tell from the form alone.
 *
 * Tracking switched on with a count is a change from no level ($before
 * null), as the stock API's set and the Workspace treat it. Tracking
 * switched off is not a stock change: there is no level left to report. The
 * same count written again is nothing.
 *
 * @param bool       $was_tracked  products.inventory before the write
 * @param int|string $was_quantity products.inventory_quantity before the write
 * @param bool       $tracked      products.inventory after the write
 * @param int|string $quantity     products.inventory_quantity after the write
 * @return array|null array(before or null, after), or null for no change
 */
function pg_product_stock_delta($was_tracked, $was_quantity, $tracked, $quantity)
{
    if (!$tracked) {
        return null;
    }

    $after = (int) $quantity;

    if (!$was_tracked) {
        return array(null, $after);
    }

    if ((int) $was_quantity === $after) {
        return null;
    }

    return array((int) $was_quantity, $after);
}


/**
 * The products.out_of_stock assignment for a write that sets a stock count,
 * for an UPDATE's SET list: tracked with nothing left is out of stock, any
 * other row is not (the rule of pg_pb_apply_stock_flag()).
 *
 * out_of_stock_timestamp is the "out of stock since" column, so it is
 * stamped only on the way in: a row that was flagged already keeps the day
 * it ran out.
 *
 * @param bool       $tracked     products.inventory after the write
 * @param int|string $quantity    products.inventory_quantity after the write
 * @param bool|string $was_flagged products.out_of_stock before the write
 * @return string the assignment(s), without a trailing comma
 */
function pg_product_out_of_stock_sql($tracked, $quantity, $was_flagged)
{
    if (!$tracked || ((int) $quantity > 0)) {
        return "out_of_stock = '0'";
    }

    if (($was_flagged === true) || ((string) $was_flagged === '1')) {
        return "out_of_stock = '1'";
    }

    return "out_of_stock = '1', out_of_stock_timestamp = UNIX_TIMESTAMP()";
}


/**
 * A product's stock was written somewhere on the site: the checkout, a local
 * sale, the product screen and its list, a barcode scan, the bulk edit, the
 * file manager's catalogue (quick edit and bulk edit). One
 * call after the write does everything a stock change owes the outside:
 *
 *   - products.timestamp moves, so GET /products?updated_since= lists the
 *     product; a sale that left it alone was invisible to a system that
 *     syncs that way, which kept selling the old stock.
 *   - inventory.changed, and stock.low on the crossing, are announced the way
 *     the stock API's own writer announces them (pg_product_stock_events()).
 *   - the marketplace queue is told (pg_marketplace_product_changed()).
 *
 * The stock API, the Workspace and the ERP keep their own writers: each
 * stamps and announces inside its own statement already.
 *
 * Called once per product, after the row is written. $before is the stock as
 * the caller read it before its write, or null when the product did not
 * track stock until now. $after is what the write left; null reads it back
 * from the row, which a caller whose UPDATE did the arithmetic (the checkout's
 * decrement) cannot know otherwise. The stock left is returned, so a loop
 * that meets the same product twice (two recipients of one order) carries it
 * on as the next row's $before instead of the value read before the loop.
 *
 * Queued and recorded, never sent: a checkout must not wait on anybody's
 * server. Silent on a product that is not there.
 *
 * @param int      $product_id
 * @param int|null $before  stock before the write; null when it was not tracked
 * @param int|null $after   stock left after the write; null reads the row
 * @param string   $source  the writer: checkout, local_sale, panel, barcode, bulk_edit
 * @return int|null the stock left, null when the product is not there
 */
function pg_product_stock_changed($product_id, $before, $after, $source)
{
    $product_id = (int) $product_id;

    if (($product_id <= 0) || !defined('DB_CONNECTED')) {
        return null;
    }

    db("UPDATE products SET timestamp = UNIX_TIMESTAMP() WHERE id = '" . $product_id . "' LIMIT 1");

    $row = db_item("SELECT name, inventory, inventory_quantity FROM products WHERE id = '" . $product_id . "' LIMIT 1");

    if (!$row) {
        return null;
    }

    if ($after === null) {
        $after = (int) $row['inventory_quantity'];
    }

    $events = pg_product_stock_events(
        $product_id,
        $row['name'],
        ((string) $row['inventory'] === '1'),
        ($before === null) ? null : (int) $before,
        $after,
        pg_product_stock_threshold(),
        $source);

    if (function_exists('pg_announce')) {
        foreach ($events as $event) {
            pg_announce($event[0], $event[1]);
        }
    }

    pg_marketplace_product_changed($product_id);

    return (int) $after;
}


/**
 * The statements that put a catalogue row in the Recycle Bin.
 *
 * The catalogue's bin is a flag, not a move: the row is switched off and marked
 * recycled, what 'enabled' was is kept in recycled_enabled so that restoring
 * puts it back as it was, and recycle_bin holds the record the bin lists, the
 * countdown reads and the daily purge works from. Nothing else is touched -
 * images, groups, attributes, prices, barcodes, form fields and short links
 * stay where they are - so restoring is the flag going back and nothing has to
 * be rebuilt.
 *
 * Built apart from the queries that run them so that every screen and the API
 * write the same three, and so the unit tests can read them.
 *
 * "AND recycled = '0'" keeps a row that is already in the bin from being binned
 * again: the second pass would copy 'enabled', which binning forced to 0, over
 * the kept value, and the row would come back out of the bin unpublished. The
 * record is replaced rather than added to, so one row never has two.
 *
 * @param string $item_type 'product' or 'product_group'
 * @param int    $item_id   the row the bin record is for
 * @param array  $row_ids   the rows flagged with it: the product itself, or a group and the groups under it
 * @param int    $parent_id recycle_bin.original_parent_id
 * @param int    $user_id
 * @return array the statements in the order they run; empty when there is nothing to bin
 */
function pg_catalog_bin_statements($item_type, $item_id, $row_ids, $parent_id, $user_id)
{
    $item_type = ($item_type === 'product_group') ? 'product_group' : 'product';
    $table = ($item_type === 'product_group') ? 'product_groups' : 'products';
    $item_id = (int) $item_id;

    $ids = array();

    foreach ((array) $row_ids as $row_id) {
        $row_id = (int) $row_id;

        if ($row_id > 0) {
            $ids[$row_id] = $row_id;
        }
    }

    if (($item_id <= 0) || (count($ids) == 0)) {
        return array();
    }

    return array(
        "UPDATE " . $table . "
        SET recycled = '1', recycled_enabled = enabled, enabled = '0'
        WHERE (id IN (" . implode(',', $ids) . ")) AND (recycled = '0')",

        "DELETE FROM recycle_bin WHERE (item_type = '" . $item_type . "') AND (item_id = '" . $item_id . "')",

        "INSERT INTO recycle_bin (item_type, item_id, original_parent_id, deleted_at, deleted_by)
        VALUES ('" . $item_type . "', '" . $item_id . "', '" . (int) $parent_id . "', UNIX_TIMESTAMP(), '" . (int) $user_id . "')");
}

/**
 * Whether deleting a product or a product group sends it to the Recycle Bin.
 *
 * The catalogue's bin columns arrive with an upgrade; before it a deleted
 * product is simply gone, and the screens fall back to the permanent delete
 * rather than pretend otherwise. The Catalog Manager decides it, so the answer
 * is asked of the Catalog Manager.
 *
 * @return bool
 */
function pg_catalog_bin_ready()
{
    if (!function_exists('pg_catalog_recycle_ready')) {
        require_once(PG_FUNCTIONS_DIR . '/view_folder_and_files_f.php');
    }

    return (pg_catalog_recycle_ready() == true);
}

/**
 * The WHERE fragment that leaves catalogue rows in the Recycle Bin out of a
 * picker, a search or a count.
 *
 * A row in the bin is one the operator deleted; offering it again next to the
 * live ones, marked only as switched off, lets it be chosen for an order line,
 * an offer or a group as if nothing had happened. The Catalog Manager's own
 * filter, so every screen reads the bin the same way; empty before the upgrade
 * that adds the bin, so the same queries run either way.
 *
 * $keep_id is the row a form already points at, or the rows of a multiple
 * select. It stays in the list even from the bin: a <select> that no longer
 * has the stored value falls back to its first option, and the next save would
 * quietly point the setting somewhere else, or nowhere, while the row waits to
 * be restored.
 *
 * @param string    $table   'products' or 'product_groups', or the alias the query gives it
 * @param int|array $keep_id the row (or rows) to keep whatever their state, or 0
 * @return string " AND (<table>.recycled = '0')", or ''
 */
function pg_catalog_bin_live_sql($table, $keep_id = 0)
{
    if (pg_catalog_bin_ready() == false) {
        return '';
    }

    $keep_ids = pg_catalog_keep_ids($keep_id);

    if (count($keep_ids) > 0) {
        return " AND ((" . $table . ".recycled = '0') OR (" . $table . ".id IN (" . implode(',', $keep_ids) . ")))";
    }

    return pg_catalog_live_filter($table);
}

/**
 * The whole, positive ids in what a form says it points at.
 *
 * A stored setting is a number or a string of one, a multiple select posts a
 * list, and a hand-made request can post anything, a nested list included;
 * only what reads as an id is kept, once.
 *
 * @param mixed $value
 * @return int[]
 */
function pg_catalog_keep_ids($value)
{
    $ids = array();

    foreach ((array) $value as $id) {
        if (is_scalar($id) && ((int) $id > 0)) {
            $ids[(int) $id] = (int) $id;
        }
    }

    return array_values($ids);
}

/**
 * Hangs every group of a picker on the nearest group the picker lists.
 *
 * get_product_group_options() walks the tree from the top, so a group whose
 * parent is not on its list is never reached. With the bin left out that is
 * the binned group a setting already points at (kept so the setting is not
 * lost) when the group above it went to the bin with it, and a group restored
 * on its own while the one above it is still in the bin. Each is moved up past
 * the binned groups over it, to where it would sit if they were simply not
 * there. Only binned groups are skipped: a group left off for another reason
 * (switched off, on a list of live groups only) still hides what is under it.
 *
 * @param array $rows   the picker's groups, each with 'id' and 'parent_id'
 * @param array $binned the groups in the bin, keyed by id, each with 'parent_id'
 * @return array $rows, with parent_id moved up where it pointed into the bin
 */
function pg_catalog_group_rows_past_bin($rows, $binned)
{
    $listed = array();

    foreach ($rows as $row) {
        $listed[(int) $row['id']] = true;
    }

    foreach ($rows as $key => $row) {
        $parent_id = (int) $row['parent_id'];

        // Guarded against a parent_id loop, which a hand-edited database can
        // produce; the row is then left where it was.
        $guard = count($binned);

        while (($parent_id > 0) && (isset($listed[$parent_id]) == false) && isset($binned[$parent_id]) && ($guard-- > 0)) {
            $parent_id = (int) $binned[$parent_id]['parent_id'];
        }

        if (($guard >= 0) && ($parent_id !== (int) $row['parent_id'])) {
            $rows[$key]['parent_id'] = $parent_id;
        }
    }

    return $rows;
}

/**
 * Whether a product is in the Recycle Bin.
 *
 * @param int $product_id
 * @return bool
 */
function pg_catalog_product_binned($product_id)
{
    if (pg_catalog_bin_ready() == false) {
        return false;
    }

    return ((int) db_value("SELECT recycled FROM products WHERE id = '" . (int) $product_id . "'") === 1);
}

/**
 * Whether a product group is in the Recycle Bin, deleted itself or with a
 * group above it.
 *
 * @param int $group_id
 * @return bool
 */
function pg_catalog_group_binned($group_id)
{
    if (pg_catalog_bin_ready() == false) {
        return false;
    }

    return ((int) db_value("SELECT recycled FROM product_groups WHERE id = '" . (int) $group_id . "'") === 1);
}

/**
 * Move one product to the Recycle Bin, the way the Catalog Manager's Delete does.
 *
 * product.deleted goes out with recycled: true, as it does when the API bins a
 * product: the product leaves every listing, and an integration that only
 * learned of it through a listing would otherwise never hear that it went. The
 * marketplace queue is told for the same reason - the product is switched off.
 *
 * The caller has asked pg_catalog_bin_ready() first and writes its own activity
 * line, because every screen words it its own way.
 *
 * @param int $product_id
 * @param int $user_id
 * @param int $parent_id the variant set the product went into the bin with, or 0
 * @return string|false the product's name, or false when there was nothing to bin
 */
function pg_catalog_bin_product($product_id, $user_id, $parent_id = 0)
{
    $product_id = (int) $product_id;

    $product = db_item("SELECT id, name, recycled FROM products WHERE id = '" . $product_id . "'");

    // Already in the bin: binning it again would only restart its countdown.
    if ((is_array($product) == false) || ((int) $product['recycled'] === 1)) {
        return false;
    }

    foreach (pg_catalog_bin_statements('product', $product_id, array($product_id), $parent_id, $user_id) as $statement) {
        db($statement);
    }

    pg_announce('product.deleted', array(
        'id'       => $product_id,
        'name'     => (string) $product['name'],
        'recycled' => true));

    pg_marketplace_product_changed($product_id);

    return (string) $product['name'];
}

/**
 * Move a product group, and every group under it, to the Recycle Bin.
 *
 * The same move the Catalog Manager makes: the whole subtree is flagged, the
 * way a folder goes with everything inside it, and one record is written for
 * the group that was deleted - restoring it brings the subtree back. The
 * products in it are not binned; they stay on sale in whatever other groups
 * list them, and keep their link to this one for when it comes back.
 *
 * @param int $group_id
 * @param int $user_id
 * @return string|false the group's name, or false when there was nothing to bin
 *                      (no such group, the root, or already in the bin)
 */
function pg_catalog_bin_group($group_id, $user_id)
{
    $group_id = (int) $group_id;

    if (pg_catalog_bin_ready() == false) {
        return false;
    }

    $group = db_item("SELECT id, name, parent_id, recycled FROM product_groups WHERE id = '" . $group_id . "'");

    // The root is the catalogue itself; edit_product_group.php and the
    // Catalog Manager refuse to delete it too.
    if ((is_array($group) == false) || ((int) $group['parent_id'] === 0) || ((int) $group['recycled'] === 1)) {
        return false;
    }

    foreach (pg_catalog_bin_statements('product_group', $group_id, pg_catalog_subtree_ids($group_id), $group['parent_id'], $user_id) as $statement) {
        db($statement);
    }

    return (string) $group['name'];
}


function get_effective_tax_rate($product_rate, $zone_rate)
{
    if ($product_rate === null || $product_rate === '') {
        return (float) $zone_rate;
    }

    return (float) $product_rate;
}


/**
 * The price a buyer pays for one unit, in minor units: the stored price plus
 * the tax the checkout adds on top of it.
 *
 * The shop keeps product prices without tax and update_order_item_taxes()
 * adds the rate's tax at the checkout (pg_tax_apply_rate()), so this is that
 * tax on a quantity of one. It is the figure to hand anybody who wants
 * the final price - a marketplace quotes its sale price with the tax inside -
 * and it is the price itself whenever the checkout would add nothing: the
 * shop has tax switched off, the product is not taxable, or the shop's
 * country is in no tax zone (a zone rate of false, as get_default_tax_rate()
 * answers it).
 *
 * It is the no-buyer twin of local_sale_line_rate(): with the ERP on the
 * till takes the ERP store's zone (state included), while a marketplace
 * always takes the shop country's zone, the same source its declared VAT
 * rate comes from (mp_applied_vat_rate()), so the price and the rate stated
 * beside it agree.
 *
 * @param  int|string   $price        minor units as stored in products.price
 * @param  int|string   $taxable      products.taxable
 * @param  mixed        $product_rate products.tax_rate (NULL = the zone's rate)
 * @param  float|false  $zone_rate    the shop country's zone rate, false in no zone
 * @param  bool         $tax_enabled  ECOMMERCE_TAX
 * @return int
 */
function get_tax_inclusive_price($price, $taxable, $product_rate, $zone_rate, $tax_enabled)
{
    $price = (int) $price;

    if (!$tax_enabled || !((int) $taxable) || $zone_rate === false || $zone_rate === null) {
        return $price;
    }

    $rate = get_effective_tax_rate($product_rate, $zone_rate);

    return $price + pg_tax_apply_rate($price, $rate);
}

/**
 * The price a bulk product edit (edit_products.php) gives a product.
 *
 * Methods: '1' decrease by an amount, '2' increase by an amount, '3' decrease
 * by a percentage, '4' increase by a percentage. The decrease methods refuse
 * any result at or below zero, a free or negative price included; the
 * increase methods refuse one only when the change lowers the price (a
 * negative value), so a free product raised by a percentage stays free. A
 * refused change keeps the old price. A price below zero would become an
 * order line below zero, which an e-document provider refuses on an invoice.
 *
 * @param int|string       $price   products.price, minor units
 * @param int|string       $method  '1' to '4'; any other changes nothing
 * @param int|float|string $value   minor units for '1' and '2' (rounded to whole ones), a percentage for '3' and '4'
 * @return array  ['price' => int, the new price or the old one when refused, 'refused' => bool]
 */
function pg_bulk_price_change($price, $method, $value)
{
    $price = (int) $price;
    $method = (string) $method;

    if (!in_array($method, array('1', '2', '3', '4'), true)) {
        return array('price' => $price, 'refused' => false);
    }

    switch ($method) {
        case '1':
            $new = $price - (int) round((float) $value);
            break;
        case '2':
            $new = $price + (int) round((float) $value);
            break;
        case '3':
            $new = (int) round(((100 - (float) $value) / 100) * $price);
            break;
        default:
            $new = (int) round(((100 + (float) $value) / 100) * $price);
            break;
    }

    $decrease = ($method === '1') || ($method === '3');

    if (($new <= 0) && ($decrease || ($new < $price))) {
        return array('price' => $price, 'refused' => true);
    }

    return array('price' => $new, 'refused' => false);
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

/**
 * A decimal as whole units of 1/10^$places, rounded half away from zero on
 * the digits sprintf() writes it with: 2.3 at three places is 2300, although
 * 2.3 is 2.2999... in binary.
 *
 * The checkout's copy of the ERP's erp_decimal_units() (includes/erp/money.php),
 * which a store without the ERP never loads; tests/tax_apply_rate_test.php
 * holds the two sources equal. A float, so it stays whole past PHP_INT_MAX on
 * a 32-bit build.
 *
 * @param float|int|string $value
 * @param int              $places  0 to 10
 * @return float
 */
function pg_decimal_units($value, $places)
{
    $value = (float) $value;
    $places = max(0, min(10, (int) $places));
    $parts = explode('.', sprintf('%.10F', abs($value)));
    $units = (float) ($parts[0] . substr($parts[1], 0, $places));

    if ((int) substr($parts[1], $places, 1) >= 5) {
        $units += 1;
    }

    // A negative value that rounds to nothing is 0.0, not -0.0.
    return (($value < 0) && ($units > 0)) ? -$units : $units;
}

/**
 * An amount times a decimal held as whole units of 1/$scale, rounded half
 * away from zero on the exact product, in whole numbers only.
 *
 * The checkout's copy of the ERP's erp_multiply_units() (includes/erp/money.php);
 * tests/tax_apply_rate_test.php holds the two sources equal, so the float
 * steps a 32-bit build takes stay the ERP's although CI never runs them. Both
 * factors are split at the scale, so no partial product outgrows an integer
 * while the result fits; on a 32-bit build the same steps run on whole
 * floats, exact below 2^53.
 *
 * @param int|float $amount  A whole number
 * @param int|float $units   A whole number
 * @param int       $scale   100000 for a percentage at three places
 * @return int|float  An int; a float only on a 32-bit build, for a result past PHP_INT_MAX
 */
function pg_multiply_units($amount, $units, $scale)
{
    $amount = is_int($amount) ? $amount : (float) $amount;
    $units = is_int($units) ? $units : (float) $units;
    $scale = max(1, (int) $scale);
    $negative = (($amount < 0) xor ($units < 0));

    $amount = abs($amount);
    $units = abs($units);

    // Whole units held in a float (pg_decimal_units()) take the integer
    // steps when they fit a 64-bit int.
    if ((PHP_INT_SIZE >= 8) && is_float($amount) && ($amount < PHP_INT_MAX)) {
        $amount = (int) $amount;
    }

    if ((PHP_INT_SIZE >= 8) && is_float($units) && ($units < PHP_INT_MAX)) {
        $units = (int) $units;
    }

    if (is_int($amount) && is_int($units) && (PHP_INT_SIZE >= 8)) {
        $amount_rest = $amount % $scale;
        $amount_whole = intdiv($amount, $scale);
        $units_rest = $units % $scale;
        $units_whole = intdiv($units, $scale);
        $rest = $amount_rest * $units_rest;
        $rest_rest = $rest % $scale;

        $result = ($amount_whole * $units_whole * $scale) + ($amount_whole * $units_rest)
            + ($amount_rest * $units_whole) + intdiv($rest, $scale);
    } else {
        $amount = (float) $amount;
        $units = (float) $units;
        $amount_rest = fmod($amount, $scale);
        $amount_whole = ($amount - $amount_rest) / $scale;
        $units_rest = fmod($units, $scale);
        $units_whole = ($units - $units_rest) / $scale;
        $rest = $amount_rest * $units_rest;
        $rest_rest = fmod($rest, $scale);

        $result = ($amount_whole * $units_whole * $scale) + ($amount_whole * $units_rest)
            + ($amount_rest * $units_whole) + (($rest - $rest_rest) / $scale);
    }

    if (($rest_rest * 2) >= $scale) {
        $result += 1;
    }

    if (is_float($result) && ($result < PHP_INT_MAX)) {
        $result = (int) $result;
    }

    return ($negative && ($result > 0)) ? -$result : $result;
}

/**
 * The tax at a rate on an amount: the rate at the three places the rate
 * columns keep, the exact product rounded half away from zero. 2.3% of 15.00
 * is 0.345, so 0.35, on every PHP version; round(1500 * 2.3 / 100) is 34 on
 * PHP 8.4 and later, where round() no longer pre-rounds 34.4999... The ERP's
 * erp_apply_rate(), so an order's tax is its invoice's.
 *
 * The amount is taken as it comes, not cut to an int: on a 32-bit build the
 * lines of one rate past 21,474,836.47 add up to a float, which
 * pg_multiply_units() takes whole.
 *
 * @param int|float        $amount  minor units, a whole number
 * @param float|int|string $rate    percent
 * @return int|float  minor units; a float only on a 32-bit build, past PHP_INT_MAX
 */
function pg_tax_apply_rate($amount, $rate)
{
    return pg_multiply_units($amount, pg_decimal_units($rate, 3), 100000);
}

/**
 * The tax of an order's lines, taken once on the sum of the lines at each
 * rate and spread back over them.
 *
 * Lines at one rate are a group: the group's tax is the rate on the sum of
 * their amounts, rounded half away from zero (pg_tax_apply_rate()), and it
 * is shared out over them in proportion to their amounts (pg_tax_spread()).
 * The lines add up to their group and each stays within a minor unit of what
 * it would carry alone. It is how an e-document provider that works the tax
 * out from the rates comes to its figures, and how the ERP takes a
 * document's taxes (erp_rate_vat(), which gives the same figures for lines
 * of zero or more without withholding). A line with a negative amount is
 * taxed alone, so a group's shares never run backwards.
 *
 * @param array $lines  key => ['amount' => int minor units, 'rate' => float percent]; no rate, or 0, is no tax
 * @return array  key => int tax, in the order given
 */
function pg_tax_by_rate($lines)
{
    $taxes = array();
    $groups = array();

    foreach ((array) $lines as $key => $line) {
        $amount = (int) ($line['amount'] ?? 0);
        $rate = (float) ($line['rate'] ?? 0);
        $taxes[$key] = 0;

        if ($rate <= 0) {
            continue;
        }

        if ($amount < 0) {
            $taxes[$key] = pg_tax_apply_rate($amount, $rate);
            continue;
        }

        // Three places, the scale the rates are kept at.
        $group = sprintf('%.3F', $rate);

        if (!isset($groups[$group])) {
            $groups[$group] = array('rate' => $rate, 'amount' => 0, 'members' => array());
        }

        $groups[$group]['amount'] += $amount;
        $groups[$group]['members'][$key] = $amount;
    }

    foreach ($groups as $group) {
        $tax = pg_tax_apply_rate($group['amount'], $group['rate']);

        foreach (pg_tax_spread($tax, $group['members']) as $key => $share) {
            $taxes[$key] = $share;
        }
    }

    return $taxes;
}

/**
 * Split an amount over weights: each its floor share, the minor units left
 * over to the largest remainders, the first of equal ones first. The shares
 * always add up to the amount. The same rule as the ERP's erp_allocate(), so
 * an order and its invoice share a group's tax out alike.
 *
 * @param int   $total
 * @param array $weights  key => int, zero or more
 * @return array  key => int
 */
function pg_tax_spread($total, $weights)
{
    $total = (int) $total;
    $shares = array();
    $sum = 0;

    foreach ($weights as $key => $weight) {
        $shares[$key] = 0;
        $sum += (int) $weight;
    }

    if ($sum <= 0) {
        // Nothing to go on: the whole amount on the first line rather than
        // lost.
        foreach (array_keys($shares) as $key) {
            $shares[$key] = $total;
            break;
        }

        return $shares;
    }

    $remainders = array();
    $order = array();
    $allocated = 0;

    foreach ($weights as $key => $weight) {
        $exact = ($total * (int) $weight) / $sum;
        $floor = (int) floor($exact);
        $shares[$key] = $floor;
        $remainders[$key] = $exact - $floor;
        $order[$key] = count($order);
        $allocated += $floor;
    }

    $left = $total - $allocated;

    if ($left !== 0) {
        // Largest remainder first, equal ones in their own order.
        $keys = array_keys($remainders);
        usort($keys, function ($a, $b) use ($remainders, $order) {
            if ($remainders[$a] == $remainders[$b]) {
                return $order[$a] - $order[$b];
            }
            return ($remainders[$a] < $remainders[$b]) ? 1 : -1;
        });

        if ($left < 0) {
            $keys = array_reverse($keys);
        }

        $step = ($left > 0) ? 1 : -1;

        for ($index = 0; $left !== 0; $index++) {
            $shares[$keys[$index % count($keys)]] += $step;
            $left -= $step;
        }
    }

    return $shares;
}

/**
 * The tax an order charges on its lines once the order discount is off.
 *
 * The discount is shared over the lines in proportion to their amounts
 * (pg_tax_spread(), the share the order's invoice gives each line) and the
 * tax is taken again per rate on what is left of them (pg_tax_by_rate()), so
 * the order's tax is the one its invoice and an e-document provider come to.
 * Without a discount it is the lines' own tax, which update_order_item_taxes()
 * took per rate already. A line whose rate was not kept (one placed before
 * order_items.tax_rate, or with tax switched off) cannot be taxed again; the
 * tax is then lowered by the discount's share of the subtotal. Never below
 * zero; the discount counts up to the subtotal.
 *
 * @param array $lines     key => ['amount' => int minor units, 'rate' => float|string|null, 'tax' => int],
 *                         the lines charged today in the order of their ids
 * @param int   $discount  the order discount charged today, minor units
 * @return int
 */
function pg_order_charged_tax($lines, $discount)
{
    $amounts = array();
    $subtotal = 0;
    $tax = 0;
    $rated = true;

    foreach ((array) $lines as $key => $line) {
        $amounts[$key] = (int) ($line['amount'] ?? 0);
        $subtotal += $amounts[$key];
        $tax += (int) ($line['tax'] ?? 0);

        if (!isset($line['rate']) || ($line['rate'] === '')) {
            $rated = false;
        }
    }

    $tax = max(0, $tax);
    $discount = min((int) $discount, $subtotal);

    if (($discount <= 0) || ($subtotal <= 0)) {
        return $tax;
    }

    if (!$rated) {
        return max(0, $tax - (int) round($tax * ($discount / $subtotal)));
    }

    $shares = pg_tax_spread($discount, $amounts);
    $taxed = array();

    foreach ($lines as $key => $line) {
        $taxed[$key] = array('amount' => $amounts[$key] - $shares[$key], 'rate' => (float) $line['rate']);
    }

    return max(0, array_sum(pg_tax_by_rate($taxed)));
}

/**
 * pg_order_charged_tax() for order lines given by id, read from the order:
 * for the pages that show an order's totals while drawing its lines.
 *
 * @param int[] $item_ids  the order_items charged today
 * @param int   $discount  minor units
 * @return int
 */
function pg_order_items_charged_tax($item_ids, $discount)
{
    $ids = array_values(array_filter(array_map('intval', (array) $item_ids)));

    if (!$ids) {
        return 0;
    }

    $with_rate = pg_schema_has('order_items', 'tax_rate');
    $lines = array();

    foreach ((array) db_items("SELECT id, price, quantity, tax_total" . ($with_rate ? ", tax_rate" : "") . "
        FROM order_items
        WHERE id IN (" . implode(', ', $ids) . ")
        ORDER BY id") as $item) {
        $lines[(int) $item['id']] = array(
            'amount' => (int) $item['price'] * max(1, (int) $item['quantity']),
            'rate' => $with_rate ? $item['tax_rate'] : null,
            'tax' => (int) $item['tax_total'],
        );
    }

    return pg_order_charged_tax($lines, $discount);
}

/**
 * The tax of each of an order's lines, as the checkout writes it
 * (update_order_item_taxes()).
 *
 * The lines charged together, when the order is placed, take their tax per
 * rate on their sum (pg_tax_by_rate()): the way the order's invoice and an
 * e-document provider take it. A line charged on its own takes its own tax
 * alone - a recurring line that starts later, whose payments are taken one by
 * one (pg_order_line_charged_today()), and one that is not charged at all.
 * The lines go in the order of their ids, as the order's invoice reads them,
 * so a rate's minor unit left over by the spread lands on the same line there.
 *
 * @param array $lines  id => ['amount' => int minor units, 'rate' => float percent, 'alone' => bool]
 * @return array  id => int, in the order of the ids
 */
function pg_order_line_taxes($lines)
{
    $lines = (array) $lines;
    ksort($lines);
    $together = array();
    $taxes = array();

    foreach ($lines as $id => $line) {
        if (!empty($line['alone'])) {
            $taxes[$id] = pg_tax_by_rate(array($id => $line))[$id];
        } else {
            $together[$id] = $line;
        }
    }

    foreach (pg_tax_by_rate($together) as $id => $tax) {
        $taxes[$id] = $tax;
    }

    ksort($taxes);

    return $taxes;
}

/**
 * Whether an order's shipping and surcharges hold tax: the checkout adds none
 * to them, and on the order's invoice the tax is inside the amount charged, at
 * the goods' rate. Where VAT applies the charge for delivering goods is part of
 * the supply and is taxed with them; a United States store's checkout collects
 * sales tax on the goods alone, so there the charges are left untaxed unless
 * the settings say otherwise (config.erp_shipping_tax: 'included', 'none', or
 * empty for the store's country to decide). The ERP asks the same
 * (erp_shipping_taxed()). Read once per request.
 *
 * @return bool
 */
function pg_order_charges_taxed()
{
    static $taxed = null;

    if ($taxed === null) {
        $setting = pg_schema_has('config', 'erp_shipping_tax')
            ? trim((string) db_value("SELECT erp_shipping_tax FROM config LIMIT 1"))
            : '';

        if (($setting === 'included') || ($setting === 'none')) {
            $taxed = ($setting === 'included');
        } else {
            $country = strtoupper(trim((string) db_value("SELECT code FROM countries WHERE default_selected = 1 ORDER BY id ASC LIMIT 1")));
            $taxed = ($country !== 'US');
        }
    }

    return $taxed;
}

/**
 * Whether the checkout keeps the total of every rate on its orders one that
 * the tax, taken on the whole, can reach (pg_order_charges_reachable()): the
 * store invoices its orders in the ERP, or sends them to Paraşüt from the
 * order screen without it (parasut_order_buttons_on(); those invoices carry
 * the ERP's lines, parasut_create_invoice()), charges tax, and its shipping
 * and surcharges hold tax (pg_order_charges_taxed()).
 *
 * @return bool
 */
function pg_order_charges_settle_rates()
{
    $invoiced = (defined('ERP_ENABLED') && ERP_ENABLED)
        || (function_exists('parasut_order_buttons_on') && parasut_order_buttons_on());

    return $invoiced
        && defined('ECOMMERCE_TAX') && (ECOMMERCE_TAX == true)
        && pg_order_charges_taxed();
}

/**
 * The amount before tax that comes to a total once the tax at the rate is
 * taken on the whole of it, rounded half away from zero; null when no amount
 * does. There is one at most: a minor unit more on the amount puts one or two
 * on the total, so at 20% one total in six (57.93) is never reached. The ERP's
 * erp_rate_amount(), here for the checkout, which runs without the ERP.
 *
 * @param int   $total  minor units, tax included
 * @param float $rate   percent
 * @return int|null
 */
function pg_tax_rate_amount($total, $rate)
{
    $total = (int) $total;
    $near = (int) round($total * 100 / (100 + (float) $rate));

    foreach (array($near, $near - 1, $near + 1) as $amount) {
        if (($amount >= 0) && (($amount + pg_tax_apply_rate($amount, $rate)) === $total)) {
            return $amount;
        }
    }

    return null;
}

/**
 * How a charge holding tax is shared between the rates of an order's goods.
 *
 * In proportion to what the goods of each rate come to after the order
 * discount (pg_tax_spread()). Where a rate's share would leave its total one
 * the tax, taken on the whole, never reaches (pg_tax_rate_amount()), a minor
 * unit of the charge goes to the first other rate whose total stays one it
 * reaches with it, else comes from the first that stays reachable without
 * it: one total in six is out of reach at 20%, never two in a row, so with a
 * second rate there is always a way. With a single rate nothing moves; the
 * checkout then lowers the charge (pg_order_charges_reachable()). The order's
 * invoice shares its charges this way (erp_order_charge_lines()).
 *
 * @param int   $gross   minor units, tax inside
 * @param array $groups  rate key => ['rate' => float percent, 'net' => int, what the goods of the rate
 *                       come to after the discount, above zero, 'total' => int, the rate's lines so
 *                       far with their tax], in the order the rates first appear on the lines
 * @return array  rate key => int minor units, adding up to $gross
 */
function pg_order_charge_shares($gross, $groups)
{
    $weights = array();

    foreach ($groups as $key => $group) {
        $weights[$key] = (int) $group['net'];
    }

    $shares = pg_tax_spread((int) $gross, $weights);

    if (count($groups) < 2) {
        return $shares;
    }

    $fits = function ($key, $share) use ($groups) {
        $rate = (float) $groups[$key]['rate'];

        return ($rate <= 0) || ($share <= 0) || (pg_tax_rate_amount((int) $groups[$key]['total'] + $share, $rate) !== null);
    };

    foreach (array_keys($groups) as $key) {
        if ($fits($key, $shares[$key])) {
            continue;
        }

        foreach (array_keys($groups) as $other) {
            if (($other !== $key) && $fits($other, $shares[$other] + 1)) {
                $shares[$key]--;
                $shares[$other]++;
                continue 2;
            }
        }

        foreach (array_keys($groups) as $other) {
            if (($other !== $key) && ($shares[$other] > 0) && $fits($other, $shares[$other] - 1)) {
                $shares[$key]++;
                $shares[$other]--;
                continue 2;
            }
        }
    }

    return $shares;
}

/**
 * The charges an order adds to its lines - the shipping, then the card
 * surcharge - each lowered by the fewest minor units that leave the total of
 * every rate one the tax, taken on the whole, can reach.
 *
 * A charge holding tax is shared on the order's invoice between the rates of
 * the goods (pg_order_charge_shares()), and its tax is taken with the goods of
 * the rate on their sum (erp_order_charge_lines()), the way an e-document provider
 * takes it. Some totals no amount reaches: 39.95 with 7.99 of tax and
 * shipping 9.99 come to 57.93, which no amount at 20% does. The shipping is
 * then charged at 9.98: the customer never pays more than the page showed,
 * and the invoice is the payment. A minor unit off is all it ever takes in
 * practice; the first of ten that serves is taken, else the charge stays as
 * it is. Lines without a kept rate leave the charges alone.
 *
 * @param array $lines     key => ['amount' => int minor units, 'rate' => float|string|null],
 *                         the lines charged today in the order of their ids
 * @param int   $discount  the order discount charged today, minor units
 * @param array $charges   name => int minor units, tax inside, in the order they are settled
 * @return array  name => int, as they are charged
 */
function pg_order_charges_reachable($lines, $discount, $charges)
{
    $charged = array_map('intval', (array) $charges);
    $amounts = array();
    $subtotal = 0;

    foreach ((array) $lines as $key => $line) {
        if (!isset($line['rate']) || ($line['rate'] === '')) {
            return $charged;
        }

        $amounts[$key] = (int) ($line['amount'] ?? 0);
        $subtotal += $amounts[$key];
    }

    $discount = max(0, min((int) $discount, $subtotal));
    $shares = ($discount > 0) ? pg_tax_spread($discount, $amounts) : array();

    // What the lines of each rate come to after the discount with their tax
    // as the checkout takes it (pg_tax_by_rate(): once on the sum of a rate's
    // lines): the rates' totals the invoice starts from. The rates a charge
    // is shared by are those whose lines come to something, in the order they
    // first do so.
    $nets = array();

    foreach ((array) $lines as $key => $line) {
        $nets[$key] = array('amount' => $amounts[$key] - (int) ($shares[$key] ?? 0), 'rate' => max(0, (float) $line['rate']));
    }

    $taxes = pg_tax_by_rate($nets);
    $totals = array();
    $groups = array();

    foreach ($nets as $key => $net) {
        $group = number_format($net['rate'], 3, '.', '');
        $totals[$group] = ($totals[$group] ?? 0) + $net['amount'] + (int) $taxes[$key];

        if ($net['amount'] > 0) {
            if (!isset($groups[$group])) {
                $groups[$group] = array('rate' => $net['rate'], 'net' => 0, 'total' => 0);
            }

            $groups[$group]['net'] += $net['amount'];
        }
    }

    if (empty($groups)) {
        return $charged;
    }

    foreach (array_keys($groups) as $group) {
        $groups[$group]['total'] = $totals[$group];
    }

    foreach ($charged as $name => $gross) {
        $parts = array();

        for ($less = 0; ($less <= 10) && ($less < $gross); $less++) {
            $parts = pg_order_charge_shares($gross - $less, $groups);
            $reached = true;

            foreach ($parts as $group => $part) {
                if (($part > 0) && ($groups[$group]['rate'] > 0)
                    && (pg_tax_rate_amount($groups[$group]['total'] + $part, $groups[$group]['rate']) === null)) {
                    $reached = false;
                    break;
                }
            }

            if ($reached) {
                $charged[$name] = $gross - $less;
                break;
            }
        }

        if ($charged[$name] > 0) {
            if (array_sum($parts) !== $charged[$name]) {
                $parts = pg_order_charge_shares($charged[$name], $groups);
            }

            foreach ($parts as $group => $part) {
                $groups[$group]['total'] += $part;
            }
        }
    }

    return $charged;
}

/**
 * pg_order_charges_reachable() for order lines given by id, read from the
 * order with the rate each was taxed at: for the pages that show an order's
 * totals while drawing its lines.
 *
 * @param int[] $item_ids  the order_items charged today
 * @param int   $discount  the order discount charged today, minor units
 * @param array $charges   name => int minor units, tax inside, in the order they are settled
 * @return array  name => int, as they are charged
 */
function pg_order_items_charges_reachable($item_ids, $discount, $charges)
{
    $ids = array_values(array_filter(array_map('intval', (array) $item_ids)));

    if (!$ids || !pg_schema_has('order_items', 'tax_rate')) {
        return array_map('intval', (array) $charges);
    }

    $lines = array();

    foreach ((array) db_items("SELECT id, price, quantity, tax_rate
        FROM order_items
        WHERE id IN (" . implode(', ', $ids) . ")
        ORDER BY id") as $item) {
        $lines[(int) $item['id']] = array(
            'amount' => (int) $item['price'] * max(1, (int) $item['quantity']),
            'rate' => $item['tax_rate'],
        );
    }

    return pg_order_charges_reachable($lines, $discount, $charges);
}

/**
 * The instalment charge a card payment takes: the one the bank quoted,
 * lowered by the fewest minor units that leave the total of every rate one
 * the tax, taken on the whole, can reach (pg_order_charges_reachable(), after
 * the shipping and the surcharge as they are charged). Like those charges it
 * is shared between the goods' rates on the order's invoice, so with two
 * rates or more a minor unit moves to another rate instead; with one the
 * customer pays a minor unit less, and the invoice is still the payment.
 *
 * @param array $lines        the lines charged today, as pg_order_charges_reachable() takes them
 * @param int   $discount     the order discount charged today, minor units
 * @param array $charges      name => int minor units charged, tax inside: the shipping, the surcharge
 * @param int   $installment  the instalment charge the bank quoted, minor units
 * @return int  the instalment charge to take
 */
function pg_order_installment_charge($lines, $discount, $charges, $installment)
{
    $installment = (int) $installment;

    if (($installment <= 0) || empty($lines)) {
        return $installment;
    }

    $charges = array_map('intval', (array) $charges);
    $charges['installment'] = $installment;

    return (int) pg_order_charges_reachable($lines, $discount, $charges)['installment'];
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

    // The rate each line is charged at goes with its tax (order_items.tax_rate,
    // 11.0.59): the order's invoice prints it and takes the line's tax at it
    // again, and a rate worked back out of two rounded amounts can be wrong.
    $rate_sql = function ($rate) {
        return pg_schema_has('order_items', 'tax_rate')
            ? ", tax_rate = '" . number_format((float) $rate, 3, '.', '') . "'"
            : '';
    };

    // if order is tax-exempt, clear tax from all order items
    if ($tax_exempt) {
        $query = "UPDATE order_items

                 SET tax_total = 0, tax = 0" . $rate_sql(0) . "

                 WHERE order_id = '" . ($_SESSION['ecommerce']['order_id'] ?? '') . "'";
        $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
        // else order is not tax-exempt, so we need to apply taxes to order items
    } else {
        // Each line's amount and the rate it is charged at, gathered over every
        // recipient, and whether it is charged with the order: the taxes are
        // then taken together (pg_order_line_taxes()).
        $lines = array();
        $with_saved = pg_schema_has('order_items', 'saved_for_later');
        $clearcommerce = defined('ECOMMERCE_CREDIT_DEBIT_CARD') && ECOMMERCE_CREDIT_DEBIT_CARD
            && defined('ECOMMERCE_PAYMENT_GATEWAY') && (ECOMMERCE_PAYMENT_GATEWAY == 'ClearCommerce');
        $today = date('Y-m-d');
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

                        order_items.recurring_start_date," . ($with_saved ? "

                        order_items.saved_for_later," : "") . "

                        products.taxable,

                        products.tax_rate,

                        products.recurring,

                        products.start

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
                // The zone said tax is due; the product says at what rate. The
                // base is the whole line, not one unit: rounding a unit and then
                // multiplying gives a different answer above quantity one.
                $line_quantity = max(1, (int) $order_item['quantity']);
                $lines[(int) $order_item['id']] = array(
                    'amount' => (int) $order_item['price'] * $line_quantity,
                    'rate' => ($apply_tax == true) ? (float) get_effective_tax_rate($order_item['tax_rate'], $tax_rate) : 0.0,
                    // A line saved for later is no part of the sale.
                    'alone' => !empty($order_item['saved_for_later'])
                        || !pg_order_line_charged_today($order_item['recurring'], $order_item['recurring_start_date'], $order_item['start'], $today, $clearcommerce),
                );
            }
        }

        // tax is left at zero. It held the unit amount and is no longer
        // written, so anything still reading it shows nothing rather than
        // quietly multiplying a line amount by the quantity again.
        $taxes = pg_order_line_taxes($lines);

        foreach ($lines as $id => $line) {
            $query = "UPDATE order_items

                     SET tax_total = '" . (int) $taxes[$id] . "', tax = '0'" . $rate_sql($line['rate']) . "

                     WHERE id = '" . (int) $id . "'";
            $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
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

// The least expensive line the customer is paying for, out of $lines (id,
// price, added_by_offer, product_selection_type). Lines an offer put in the
// cart are skipped - a gift is already free, and discounting it again would
// let one offer eat another's - and so are donations: their price is the
// amount the visitor typed, not an item, and a small donation was picked as
// "the cheapest item" and set to 0. A tie goes to the lowest row id so the
// same line wins every time the cart is recalculated. 0 when no line counts.
function pg_offer_cheapest_line_id($lines)
{
    $cheapest_id    = 0;
    $cheapest_price = 0;
    foreach ((array) $lines as $line) {
        $id    = (int) $line['id'];
        $price = (int) $line['price'];
        if (((int) $line['added_by_offer'] !== 0) || ($price <= 0) || (($line['product_selection_type'] ?? '') === 'donation')) {
            continue;
        }
        if (($cheapest_id === 0) || ($price < $cheapest_price) || (($price === $cheapest_price) && ($id < $cheapest_id))) {
            $cheapest_id    = $id;
            $cheapest_price = $price;
        }
    }
    return $cheapest_id;
}

// The cheapest line of the cart (pg_offer_cheapest_line_id()), of one
// recipient when $ship_to_id is given.
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
    $sql_recipient = ((int) $ship_to_id > 0) ? "AND (order_items.ship_to_id = '" . e($ship_to_id) . "')" : '';
    return pg_offer_cheapest_line_id(db_items("SELECT

            order_items.id,
            order_items.price,
            order_items.added_by_offer,
            products.selection_type AS product_selection_type

        FROM order_items

        LEFT JOIN products ON order_items.product_id = products.id

        WHERE (order_items.order_id = '" . e($order_id) . "') AND (order_items.added_by_offer = '0') AND (order_items.price > 0)" . pg_order_item_active_sql() . " $sql_recipient"));
}

// Does this product discount apply to this cart line? A cheapest-line target
// cannot answer from the product alone, so the line and the scope come in too.
function pg_offer_action_targets_item($offer_action, $order_item, $ship_to_id = 0)
{
    // A donation is the amount the visitor typed: no product discount takes
    // it, even one that names the donation product.
    if (($order_item['product_selection_type'] ?? '') === 'donation') {
        return false;
    }
    if (pg_offer_action_target($offer_action) == 'cheapest') {
        return ((int) $order_item['id'] == pg_offer_cheapest_item_id($ship_to_id));
    }
    return pg_offer_action_targets_product($offer_action, $order_item['product_id']);
}

// The unit price a line an offer discounted goes back to when
// apply_offers_to_cart() takes the discounts off before weighing the offers
// again: the member's agreed price (pg_erp_member_prices()) or the list price.
// null for a donation, which keeps its own price: the product's price is 0
// there, and putting it back threw away the amount the visitor typed.
function pg_offer_restored_line_price($order_item, $member_prices)
{
    if (($order_item['product_selection_type'] ?? '') === 'donation') {
        return null;
    }
    $product_id = (int) $order_item['product_id'];
    return isset($member_prices[$product_id]) ? $member_prices[$product_id]['price'] : $order_item['product_price'];
}

// How many units of a cart line a product discount covers. "The cheapest item
// is free" frees one item, not every unit of the line that item sits on; a
// product or group target discounts the whole line.
function pg_offer_action_discounted_units($offer_action, $quantity)
{
    if (pg_offer_action_target($offer_action) == 'cheapest') {
        return min(1, (int) $quantity);
    }
    return (int) $quantity;
}

// The unit price of a cart line when an offer discounts only some of its
// units: the cheapest item, or a gift taken from a line the shopper is already
// paying for. A line carries one unit price, so the discount on those units is
// spread over the whole line: one free item on a line of two charges each
// unit half. The spread price rounds down, so the line never costs more than
// its units at their own prices; the customer keeps at most quantity - 1
// minor units of the rounding. A line whose every unit is discounted gets the
// discounted price unchanged.
function pg_offer_spread_unit_price($unit_price, $quantity, $discounted_unit_price, $discounted_units)
{
    $quantity = (int) $quantity;
    $discounted_units = (int) $discounted_units;
    if ($discounted_units >= $quantity) {
        return $discounted_unit_price;
    }
    if ($discounted_units <= 0) {
        return $unit_price;
    }
    $line_total = ((float) $unit_price * ($quantity - $discounted_units)) + (round((float) $discounted_unit_price) * $discounted_units);
    return (int) floor($line_total / $quantity);
}

// The unit price of a cart line after a gift (an 'add product' result) is
// taken from it: $gift_units of the line are charged at $gift_unit_price,
// the rest stay at $paid_unit_price, the price the paid units stand at (list,
// member or a uniform product discount's price). Worked out from the line's
// total, $line_unit_price times the quantity, because that unit price may
// already be an average: "the cheapest item is free" or an earlier gift spread
// over the line (Y x3 at 10,00 with one free -> 666). Spreading a second gift
// from that average would count 666 as what a paid unit costs and lose part
// of the first discount; taking the gift's units off the total at the paid
// units' own price frees a second unit in full. The result rounds down, as
// pg_offer_spread_unit_price() does (at most quantity - 1 minor units per
// spread stay with the customer). A gift never raises the line's price, and
// one as large as the line charges every unit the gift price.
function pg_offer_gift_line_unit_price($line_unit_price, $quantity, $paid_unit_price, $gift_unit_price, $gift_units)
{
    $quantity = (int) $quantity;
    $gift_units = (int) $gift_units;
    $gift_unit_price = min((float) $gift_unit_price, (float) $paid_unit_price);
    if ($gift_units >= $quantity) {
        return (int) round($gift_unit_price);
    }
    if ($gift_units <= 0) {
        return (int) round((float) $line_unit_price);
    }
    $line_total = ((float) $line_unit_price * $quantity) - ($gift_units * ((float) $paid_unit_price - round($gift_unit_price)));
    if ($line_total < 0) {
        $line_total = 0;
    }
    return (int) floor($line_total / $quantity);
}

// The unit price a gift (an 'add product' result) is charged at: the product's
// price less the action's amount, or less its percentage, in minor units and
// rounded. Never below zero: an amount larger than the price, or a price
// lowered after the offer was set up, would otherwise leave a line that takes
// money off the order.
function pg_offer_gift_price($product_price, $discount_amount, $discount_percentage)
{
    if ($discount_amount != 0) {
        $price = $product_price - $discount_amount;
    } else {
        $price = $product_price - ($product_price * ($discount_percentage / 100));
    }
    if ($price < 0) {
        $price = 0;
    }
    return (int) round($price);
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
        $table_exists = pg_schema_has('offer_conditions');
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
    // The guest view (pg_offer_guest_view()) has its own answers: the same
    // request can ask for the visitor in front of it as well.
    $key = $offer_id . (pg_offer_guest_view() ? ':guest' : '');
    if (isset($answers[$key])) {
        return $answers[$key];
    }
    $conditions = pg_offer_conditions($offer_id);
    if (!$conditions) {
        $answers[$key] = true;
        return true;
    }
    $user_id = (!pg_offer_guest_view() && defined('USER_ID') && USER_ID) ? (int) USER_ID : 0;
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
    $answers[$key] = $met;
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
    $key = $offer_id . (pg_offer_guest_view() ? ':guest' : '');
    if (isset($answers[$key])) {
        return $answers[$key];
    }
    $used = "(orders.status IN ('complete', 'exported'))

        AND (
            (orders.discount_offer_id = '" . e($offer_id) . "')

            OR (orders.id IN (SELECT order_id FROM order_items WHERE offer_id = '" . e($offer_id) . "'" . pg_order_item_active_sql() . "))

            OR (orders.id IN (SELECT order_id FROM ship_tos WHERE offer_id = '" . e($offer_id) . "'))
        )";

    $counts = array('total' => 0, 'customer' => 0);
    $counts['total'] = (int) db_value("SELECT COUNT(DISTINCT orders.id) FROM orders WHERE " . $used);

    $who = '';
    // The guest view (pg_offer_guest_view()) has no account and no cart, so
    // it is nobody in particular.
    $guest = pg_offer_guest_view();
    $user_id = (!$guest && defined('USER_ID') && USER_ID) ? (int) USER_ID : 0;
    if ($user_id) {
        $who = "(orders.user_id = '" . e($user_id) . "')";
    } else {
        // No account: the billing address of the cart in hand is the only
        // thing that ties this visitor to an earlier order.
        $order_id = $guest ? 0 : (int) ($_SESSION['ecommerce']['order_id'] ?? 0);
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
    $answers[$key] = $counts;
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

// The percentage an order discount takes off this subtotal. A ladder replaces
// the action's own percentage: the rung the subtotal has reached, and nothing
// below the first rung. The action row keeps the first rung's percentage only
// so the action reads as one that discounts something (edit_offer_f.php), so
// it must not stand in for a rung the cart has not reached. Without a ladder
// the action's percentage stands. $tiers is pg_offer_tiers()' list, highest
// rung first.
function pg_offer_order_discount_percentage($tiers, $subtotal, $action_percentage)
{
    if (!$tiers) {
        return $action_percentage;
    }
    foreach ($tiers as $tier) {
        if ($subtotal >= $tier['min']) {
            return $tier['percent'];
        }
    }
    return 0;
}

// What one 'discount order' action takes off this subtotal, in minor units:
// its amount when it has one, otherwise its percentage (the ladder's rung when
// there is a ladder) of the subtotal. Never more than the subtotal, so an order
// total is never shown below zero.
function pg_offer_order_discount_value($amount, $percentage, $tiers, $subtotal)
{
    if ($amount != 0) {
        $discount = $amount;
    } else {
        $discount = round($subtotal * (pg_offer_order_discount_percentage($tiers, $subtotal, $percentage) / 100));
    }
    if ($discount > $subtotal) {
        $discount = $subtotal;
    }
    return (int) round($discount);
}

// The order discount that takes the most off this subtotal, as array(offer id,
// discount); array(0, 0) when none takes anything off. Each candidate is
// array('offer_id', 'amount', 'percentage', 'tiers'), in the order the offers
// were weighed; the first one wins a tie.
function pg_offer_best_order_discount($candidates, $subtotal)
{
    $best = array(0, 0);
    foreach ($candidates as $candidate) {
        $discount = pg_offer_order_discount_value($candidate['amount'], $candidate['percentage'], $candidate['tiers'], $subtotal);
        if ($discount > $best[1]) {
            $best = array($candidate['offer_id'], $discount);
        }
    }
    return $best;
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

// The pieces among $lines (quantity, product_selection_type) that count toward
// a "cart quantity" condition. A donation line is left out: its price is the
// amount the visitor typed, not a piece, and since a donation is never the
// cheapest item, counting it let a donation of 1 minor unit complete "buy N,
// cheapest free" and make a real product free. A line whose product was
// deleted (no selection type) still counts.
function pg_offer_counted_quantity($lines)
{
    $quantity = 0;
    foreach ((array) $lines as $line) {
        if (($line['product_selection_type'] ?? '') === 'donation') {
            continue;
        }
        $quantity += (int) $line['quantity'];
    }
    return $quantity;
}

// Everything in the cart, counted by the piece (pg_offer_counted_quantity()).
// A gift the offer itself put there does not help the customer reach the
// threshold that earns it, so rows an offer added are left out, and so are
// donations.
function pg_offer_cart_quantity($ship_to_id = 0)
{
    $order_id = (int) ($_SESSION['ecommerce']['order_id'] ?? 0);
    if (($order_id <= 0) || pg_offer_guest_view()) {
        return 0;
    }
    $sql_recipient = ((int) $ship_to_id > 0) ? "AND (order_items.ship_to_id = '" . e($ship_to_id) . "')" : '';
    return pg_offer_counted_quantity(db_items("SELECT

            order_items.quantity,
            products.selection_type AS product_selection_type

        FROM order_items

        LEFT JOIN products ON order_items.product_id = products.id

        WHERE (order_items.order_id = '" . e($order_id) . "') AND (order_items.added_by_offer = '0')" . pg_order_item_active_sql() . " $sql_recipient"));
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

                WHERE ship_to_id = '" . e($ship_to_id) . "'" . pg_order_item_active_sql());
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
    if (!db_value("SELECT id FROM order_items WHERE order_id = '" . e(($_SESSION['ecommerce']['order_id'] ?? '')) . "'" . pg_order_item_active_sql() . " LIMIT 1")) {
        unset($_SESSION['ecommerce']['declined_offer_gifts']);
    }
    // get all order items that are discounted by an offer in order to remove discount so we can add discounts later
    // this does not include order items that were added and discounted by an offer, because we will deal with those later
    $query = "SELECT

            order_items.id,

            order_items.added_by_offer,

            products.id AS product_id,

            products.price AS product_price,

            products.selection_type AS product_selection_type

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
    // The signed-in member's agreed prices (pg_erp_member_prices()): an item
    // stripped of its offer goes back to that member's price, and an offer
    // below only takes an item it makes cheaper still.
    $member_prices = pg_erp_member_prices();
    // loop through the order items that are discounted by offers in order to remove discount
    foreach ($order_items as $order_item) {
        // if a product was found for this order item, then remove discount
        if ($order_item['product_id'] != '') {
            $restored_price = pg_offer_restored_line_price($order_item, $member_prices);
            $query = "UPDATE order_items

                SET

                    " . (($restored_price !== null) ? "price = '" . e($restored_price) . "'," : '') . "

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

            products.selection_type AS product_selection_type,

            ship_tos.complete,

            ship_tos.shipping_method_id,

            ship_tos.shipping_cost

        FROM order_items

        LEFT JOIN ship_tos ON order_items.ship_to_id = ship_tos.id

        LEFT JOIN products ON order_items.product_id = products.id

        WHERE order_items.order_id = '" . ($_SESSION['ecommerce']['order_id'] ?? '') . "'" . pg_order_item_active_sql();
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
                                    // The price the item stands at without an offer: the list
                                    // price, or the member's agreed price, which an offer has to
                                    // beat to take the item (erp_storefront_price()).
                                    $line_price = $order_item['product_price'];
                                    if (isset($member_prices[(int) $order_item['product_id']])) {
                                        $member = $member_prices[(int) $order_item['product_id']];
                                        if (erp_storefront_price($member['list'], $member['terms'], $discounted_price)['source'] !== 'offer') {
                                            continue;
                                        }
                                        $line_price = $member['price'];
                                    }
                                    // The cheapest-item target discounts one unit; the
                                    // other units of its line keep the line's price.
                                    $discounted_price = pg_offer_spread_unit_price($line_price, $order_item['quantity'], $discounted_price, pg_offer_action_discounted_units($offer_action, $order_item['quantity']));
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
                                        $discounted_order_items[$order_item['id']]['discount'] = ($line_price - $discounted_price) * $order_item['quantity'];
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
            $discounted_price = pg_offer_gift_price($order_item['product_price'], $order_item['offer_action_add_product_discount_amount'], $order_item['offer_action_add_product_discount_percentage']);
            // A free gift left alone in its recipient (the last paid line
            // there was taken out) would stop the checkout, so it goes too;
            // the offer still applies, and the loop below puts it among the
            // pending ones for the claim button, as when it first qualified.
            if (($discounted_price <= 0) && ((int) $order_item['ship_to_id'] > 0) && !pg_ship_to_has_paid_line($order_item['ship_to_id'], $order_item['id'])) {
                remove_order_item($order_item['id']);
                continue;
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
    // Every order discount the cart qualifies for. The winner is chosen after
    // the loop, against the cart as it then stands (below).
    $order_discount_candidates = array();
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
                        // Every order discount is a candidate. Whether the
                        // cart qualifies for it (its required subtotal) is
                        // settled after the loop, against the subtotal the
                        // cart then has: a gift further down this loop taken
                        // from a paid line lowers it, a gift line at a price
                        // raises it, and the answer must not depend on the
                        // order the offers are walked in.
                        $order_discount_candidates[] = array(
                            'offer_id' => $offer['id'],
                            'amount' => $offer_action['discount_order_amount'],
                            'percentage' => $offer_action['discount_order_percentage'],
                            'tiers' => pg_offer_tiers($offer['id']));
                        // if this offer is not valid for the cart as it stands, then if an upsell
                        // has not already been added for it, and there is an upsell, then add upsell
                        if ((validate_offer($offer['id']) == false) && (isset($upsell_offers[$offer['id']]) == false) && (check_upsell($offer) == true)) {
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
                                    // The items that are not shipped share the
                                    // id 0 group; it is no recipient, and a
                                    // shippable gift cannot be sent with it.
                                    if (!$ship_to['id']) {
                                        continue;
                                    }
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
    // The loop can lower an item's price after an order discount was found - a
    // gift taken from a paid line - so the candidates are judged against the
    // cart as it now stands: each one's required subtotal with the subtotal
    // that is left (one answer whatever the order the offers came in), then
    // the best one, a percentage of what is left, a ladder's rung for what
    // is left, and never more than what is left.
    $best_order_discount_offer_id = 0;
    $best_order_discount = 0;
    if ($order_discount_candidates) {
        $final_subtotal = get_order_subtotal();
        $qualified_candidates = array();
        foreach ($order_discount_candidates as $candidate) {
            if (validate_offer($candidate['offer_id'], 0, $final_subtotal) == true) {
                $qualified_candidates[] = $candidate;
            }
        }
        list($best_order_discount_offer_id, $best_order_discount) = pg_offer_best_order_discount($qualified_candidates, $final_subtotal);
    }
    // if there is an order discount, then add order discount to order
    if ($best_order_discount_offer_id != 0) {
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
    // The order discount was worked out over every line, but today's charge
    // holds only the lines charged today: a recurring line that starts later
    // is not. It is never more than that charge, so the cart, the order pages
    // and submit_order() agree on a total that does not drop below its tax
    // and shipping. A discount that comes to nothing uses up no offer.
    if (!empty($_SESSION['ecommerce']['order_discount'])) {
        $charged_discount = pg_order_charged_discount($_SESSION['ecommerce']['order_discount'], pg_order_subtotal_charged_today());
        if ($charged_discount > 0) {
            $_SESSION['ecommerce']['order_discount'] = $charged_discount;
        } else {
            db("UPDATE orders SET discount_offer_id = '0' WHERE id = '" . e($_SESSION['ecommerce']['order_id'] ?? '') . "'");
            unset($_SESSION['ecommerce']['order_discount']);
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
    // The guest view (pg_offer_guest_view()) weighs the offers against an
    // empty cart.
    $cart_order_id = pg_offer_guest_view() ? '' : ($_SESSION['ecommerce']['order_id'] ?? '');
    // get order items so we can prepare array for ship tos and order items so we can loop through them later to determine discounts
    $query = "SELECT

            order_items.id,

            order_items.product_id,

            order_items.added_by_offer,

            order_items.ship_to_id,

            order_items.quantity,

            products.price AS product_price,

            products.selection_type AS product_selection_type,

            ship_tos.complete,

            ship_tos.shipping_method_id,

            ship_tos.shipping_cost,

            ship_tos.original_shipping_cost

        FROM order_items

        LEFT JOIN ship_tos ON order_items.ship_to_id = ship_tos.id

        LEFT JOIN products ON order_items.product_id = products.id

        WHERE order_items.order_id = '" . e($cart_order_id) . "'" . pg_order_item_active_sql();
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
        // The member's agreed prices count as in apply_offers_to_cart(): an
        // offer is worth only what it takes off below them.
        $member_prices = pg_erp_member_prices();
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
                                    // The price the item stands at without an offer: the list
                                    // price, or the member's agreed price, which an offer has to
                                    // beat to take the item (erp_storefront_price()).
                                    $line_price = $order_item['product_price'];
                                    if (isset($member_prices[(int) $order_item['product_id']])) {
                                        $member = $member_prices[(int) $order_item['product_id']];
                                        if (erp_storefront_price($member['list'], $member['terms'], $discounted_price)['source'] !== 'offer') {
                                            continue;
                                        }
                                        $line_price = $member['price'];
                                    }
                                    // Worked out as apply_offers_to_cart() charges it: the
                                    // cheapest-item target is worth one unit of its line.
                                    $discounted_price = pg_offer_spread_unit_price($line_price, $order_item['quantity'], $discounted_price, pg_offer_action_discounted_units($offer_action, $order_item['quantity']));
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
                                        $discounted_order_items[$order_item['id']]['discount'] = ($line_price - $discounted_price) * $order_item['quantity'];
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
                            // The same value the cart is charged: below a
                            // ladder's first rung this offer takes nothing off.
                            $order_discount = pg_offer_order_discount_value($offer_action['discount_order_amount'], $offer_action['discount_order_percentage'], pg_offer_tiers($offer['id']), $subtotal);
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
                                    $discounted_price = pg_offer_gift_price($offer_action['add_product_price'], $offer_action['add_product_discount_amount'], $offer_action['add_product_discount_percentage']);
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
                                        $discounted_price = pg_offer_gift_price($offer_action['add_product_price'], $offer_action['add_product_discount_amount'], $offer_action['add_product_discount_percentage']);
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
                        // One value each; one posted as an array counts as not given.
                        $ship_to = $_POST['pending_offer_' . $offer_action['offer_id'] . '_' . $offer_action['id'] . '_ship_to'] ?? '';
                        $add_name = $_POST['pending_offer_' . $offer_action['offer_id'] . '_' . $offer_action['id'] . '_add_name'] ?? '';
                        $ship_to = is_string($ship_to) ? $ship_to : '';
                        $add_name = is_string($add_name) ? $add_name : '';
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
                                    $discounted_price = pg_offer_gift_price($offer_action['add_product_price'], $offer_action['add_product_discount_amount'], $offer_action['add_product_discount_percentage']);
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
                                        $discounted_price = pg_offer_gift_price($offer_action['add_product_price'], $offer_action['add_product_discount_amount'], $offer_action['add_product_discount_percentage']);
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
        // (a bot, or staff ringing up a counter sale, may have no visitor record)
        if ((VISITOR_TRACKING == true) && !empty($_SESSION['software']['visitor_id'])) {
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
    pg_curl_tls($ch);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $request);
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
        pg_curl_tls($ch);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $request);
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
    return pg_givex_parse_response($type, $response_data);
}
// Reads a Givex reply: a list of strings, the transaction code, the result (0 for success),
// then the balance of a balance check, or the authorization number and the new balance of a
// redemption; a failure has the error text third, a non-standard reply first. The result has
// either error_message or the values. A reply that is none of these (empty, an HTML error page,
// a cut-off body, a balance that is not a number) is an error_message too: callers read and
// multiply the balance. A redemption with result 0 went through at Givex whatever follows it,
// so it stays a success; a new balance that cannot be read is left out (null).
function pg_givex_parse_response($type, $response_data)
{
    $unreadable = lang('The gift card service sent a reply that could not be read');

    preg_match_all('/<string>(.*?)<\/string>/s', (string) $response_data, $matches);
    $response = $matches[1];

    if (!isset($response[1])) {
        return array('error_message' => (isset($response[0]) && ($response[0] !== '')) ? $response[0] : $unreadable);
    }

    if ($response[1] != 0) {
        return array('error_message' => (isset($response[2]) && ($response[2] !== '')) ? $response[2] : $unreadable);
    }

    if ($type == 'redemption') {
        return array(
            'authorization_number' => $response[2] ?? '',
            'balance' => (isset($response[3]) && is_numeric($response[3])) ? $response[3] : null);
    }

    if (!isset($response[2]) || !is_numeric($response[2]) || ($response[2] < 0)) {
        return array('error_message' => $unreadable);
    }

    return array('balance' => $response[2]);
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
/**
 * Whether the offer checks are working out prices for everybody: the view of
 * a visitor with no account, no offer code and an empty cart, whoever's
 * request runs them. get_discounted_product_prices(false) switches it on
 * while it runs; the checks that read the signed-in user or the shopping
 * session (pg_offer_customer_conditions_met(), pg_offer_usage_counts(),
 * pg_offer_cart_quantity(), get_order_subtotal(),
 * check_for_products_in_cart(), get_best_offer_id()) read neither while it
 * is on.
 *
 * @param bool|null $on true or false switches it, null only reads it
 * @return bool
 */
function pg_offer_guest_view($on = null)
{
    static $guest = false;
    if ($on !== null) {
        $guest = (bool) $on;
    }
    return $guest;
}
// create function that will be used to get discounted product prices based on products that are discounted by offers
// $for_member: also the signed-in member's agreed prices (pg_erp_member_prices()); false
// for what is published to everyone, such as the product feeds: the prices of
// a visitor with no account, no offer code and an empty cart
// (pg_offer_guest_view()), whoever's request builds them, so a manager's
// session never reaches a feed.
function get_discounted_product_prices($for_member = true)
{
    if (!$for_member && !pg_offer_guest_view()) {
        pg_offer_guest_view(true);
        try {
            return get_discounted_product_prices(false);
        } finally {
            pg_offer_guest_view(false);
        }
    }
    $discounted_product_prices = array();
    $special_offer_code = '';
    $offer_code = '';
    $guest = pg_offer_guest_view();
    // if the visitor has an order, then get special offer code from order in database
    if (!$guest && (isset($_SESSION['ecommerce']['order_id']) == true)) {
        $query = "SELECT special_offer_code FROM orders WHERE id = '" . ($_SESSION['ecommerce']['order_id'] ?? '') . "'";
        $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
        $row = mysqli_fetch_assoc($result);
        $special_offer_code = $row['special_offer_code'] ?? '';
        // else the visitor does not have an order, so if there is a special offer code in the visitor's session (e.g. passed via the query string), then set special offer code
    } else if (!$guest && (isset($_SESSION['ecommerce']['special_offer_code']) == true)) {
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
    // The signed-in member's agreed prices (pg_erp_member_prices()) where
    // they are lower than the campaign's; the two never stack. Feeds pass
    // false: what they publish is the store's price, not one customer's.
    if ($for_member) {
        foreach (pg_erp_member_prices() as $product_id => $member) {
            $pick = erp_storefront_price($member['list'], $member['terms'], isset($discounted_product_prices[$product_id]) ? $discounted_product_prices[$product_id] : null);
            if ($pick['source'] === 'account') {
                $discounted_product_prices[$product_id] = $pick['price'];
            }
        }
    }
    return $discounted_product_prices;
}
// ── Money and number display ────────────────────────────────────────────
//
// One rule for every figure a person reads, the one the ERP uses too
// (erp_number_separators()). The software writes 1,234.56; a language file
// that writes figures differently says so by translating the sample
// "1,234.56" (tr.json: "1.234,56"), and the separators are read from that
// translation. The currency symbol stands in front of the figure ($1,234.56,
// -$10.00). Only display follows this: typed amounts are read with either
// separator (pg_parse_amount()), and form values, files and the API keep the
// machine format 1234.56.

/**
 * Decimal and thousands separators of the site language, read from the
 * translation of the sample figure "1,234.56". A translation that is missing
 * or does not have that shape leaves the software's own separators.
 *
 * @return array ['decimal' => string, 'thousands' => string]
 */
function pg_number_separators()
{
    static $separators = null;

    if ($separators === null) {
        $separators = array('decimal' => '.', 'thousands' => ',');
        $sample = function_exists('lang') ? (string) lang('1,234.56') : '1,234.56';
        // A page served in another language writes figures that language's
        // way (Settings > Languages and Translation).
        $locale = function_exists('pg_tr_locale') ? pg_tr_locale('number') : '';
        if ($locale !== '') $sample = $locale;
        // "1<thousands>234<decimal>56"; the thousands separator may be empty
        // ("1234,56") or a space.
        if (preg_match('/^1(\D?)234(\D)56$/u', $sample, $m) && ($m[1] !== $m[2])) {
            $thousands = $m[1];
            // A plain space would let a figure wrap at its grouping.
            if ($thousands === ' ') $thousands = "\u{00A0}";
            $separators = array('decimal' => $m[2], 'thousands' => $thousands);
        }
    }

    return $separators;
}

/**
 * A number for display with the site's separators.
 *
 * @param float|int|string $amount
 * @param int              $decimals
 * @return string
 */
function pg_format_number($amount, $decimals = 2)
{
    $s = pg_number_separators();
    $amount = is_numeric($amount) ? (float) $amount : 0.0;
    return number_format($amount, (int) $decimals, $s['decimal'], $s['thousands']);
}

/**
 * An amount for an input box the person edits: the site's decimal separator
 * and no grouping, so 1250,00 (tr) or 1250.00 (en). pg_parse_amount() reads
 * it back either way.
 *
 * @param float|int|string $amount
 * @return string
 */
function pg_format_input_amount($amount)
{
    $s = pg_number_separators();
    $amount = is_numeric($amount) ? (float) $amount : 0.0;
    return number_format($amount, 2, $s['decimal'], '');
}

/**
 * The attributes of a panel money box with the currency input mask
 * (Inputmask "currency", bound by data attributes): two decimals and the
 * decimal and thousands separators the panel writes figures with
 * (pg_number_separators()), so a Turkish panel reads "12.500" as twelve
 * thousand five hundred and "1.234,56" as 1234.56. The mask on its own reads
 * "." as the decimal point in every language. backend.src.js keeps a typed
 * thousands separator from being taken as the decimal point.
 *
 * The box is filled with pg_format_input_amount() (the mask reads a value
 * written with its own decimal separator; "1234.56" on a Turkish panel would
 * be 123456,00) and read back with pg_normalize_amount() or
 * pg_pb_price_to_cents(), which take either way of writing.
 *
 * @param array|null $separators pg_number_separators(); null for the panel's
 * @return array attribute => value, as $liveform->output_field() takes them
 */
function pg_money_mask_attributes($separators = null)
{
    $s = is_array($separators) ? $separators : pg_number_separators();

    return array(
        'data-inputmask-alias'          => 'currency',
        'data-inputmask-radixPoint'     => (string) $s['decimal'],
        'data-inputmask-groupSeparator' => (string) $s['thousands'],
        'data-inputmask-digits'         => '2',
        'data-inputmask-digitsOptional' => 'false',
        'data-inputmask-placeholder'    => '0',
    );
}

/**
 * pg_money_mask_attributes() written into a tag, each value escaped, with a
 * space in front of each attribute.
 *
 * @param array|null $separators pg_number_separators(); null for the panel's
 * @return string HTML
 */
function pg_money_mask_html($separators = null)
{
    $html = '';

    foreach (pg_money_mask_attributes($separators) as $name => $value) {
        $html .= ' ' . $name . '="' . h($value) . '"';
    }

    return $html;
}

/**
 * An amount of money for display: the minus sign, the symbol, the figure.
 *
 * The amount is in major units (lira, not kuruş) and already in the currency
 * the symbol belongs to - no conversion happens here.
 *
 * @param float|int|string $amount
 * @param string|null      $symbol  Currency symbol as stored (it may be an
 *                                  entity such as &#8378;); null = the
 *                                  visitor's currency.
 * @param string           $suffix  Written after the figure, e.g. " USD"
 * @return string  HTML-safe as long as $symbol and $suffix are
 */
function pg_format_money($amount, $symbol = null, $suffix = '')
{
    if ($symbol === null) {
        $symbol = defined('VISITOR_CURRENCY_SYMBOL') ? VISITOR_CURRENCY_SYMBOL : '';
    }
    $amount = is_numeric($amount) ? (float) $amount : 0.0;
    $negative = '';
    // Round first: -0.004 would otherwise print as "-₺0,00".
    if (round($amount, 2) < 0) {
        $negative = '-';
        $amount = abs($amount);
    }
    return $negative . $symbol . pg_format_number($amount, 2) . $suffix;
}

/**
 * A base-currency amount in the visitor's currency (major units): the stored
 * price times the visitor's exchange rate. 1 when the visitor has not picked
 * another currency.
 *
 * @param float|int|string $base_amount
 * @return float
 */
function pg_visitor_amount($base_amount)
{
    $rate = (defined('VISITOR_CURRENCY_EXCHANGE_RATE') && ((float) VISITOR_CURRENCY_EXCHANGE_RATE > 0))
        ? (float) VISITOR_CURRENCY_EXCHANGE_RATE : 1.0;
    return (is_numeric($base_amount) ? (float) $base_amount : 0.0) * $rate;
}

/**
 * A base-currency amount shown to the visitor: converted to the currency they
 * picked, written like pg_format_money(), with the currency code after it
 * when that is not the base currency ("€12,50 EUR") - the same thing
 * prepare_price_for_output() writes.
 *
 * @param float|int|string $base_amount  Major units, base currency
 * @param string|null      $symbol       null = the visitor's currency symbol
 * @return string HTML
 */
function pg_visitor_money($base_amount, $symbol = null)
{
    $suffix = defined('VISITOR_CURRENCY_CODE_FOR_OUTPUT') ? h(VISITOR_CURRENCY_CODE_FOR_OUTPUT) : '';
    return pg_format_money(pg_visitor_amount($base_amount), $symbol, $suffix);
}

/**
 * The same as pg_format_money(), as plain text: a symbol stored as an entity
 * (&#8378;) becomes the character, for text that is escaped later (h(),
 * plain-text e-mail, PDF text).
 *
 * @param float|int|string $amount
 * @param string|null      $symbol  null = the visitor's currency
 * @param string           $suffix
 * @return string
 */
function pg_money_text($amount, $symbol = null, $suffix = '')
{
    if ($symbol === null) {
        $symbol = defined('VISITOR_CURRENCY_SYMBOL') ? VISITOR_CURRENCY_SYMBOL : '';
    }
    if (mb_substr((string) $symbol, 0, 1) === '&') {
        $symbol = html_entity_decode((string) $symbol, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    return pg_format_money($amount, (string) $symbol, $suffix);
}

/**
 * Read an amount a person typed: 1.234,56, 1,234.56, 1234,5, 1234.50 or
 * 1 234,56 all come out as 1234.56.
 *
 * When both separators appear, the last one is the decimal one. A separator
 * that appears more than once groups thousands. A single separator with one
 * or two digits after it is the decimal one; with three digits (1.500,
 * 1,500) the site language decides, as it does for how figures are shown.
 * Anything else that is not a digit or a minus sign is ignored (symbols,
 * spaces).
 *
 * @param mixed $value
 * @return float
 */
function pg_parse_amount($value)
{
    $value = trim(str_replace(array("\u{00A0}", ' '), '', (string) $value));
    $negative = (strpos($value, '-') === 0);
    $value = preg_replace('/[^0-9.,]/', '', $value);
    if ($value === '') return 0.0;

    $last_dot   = strrpos($value, '.');
    $last_comma = strrpos($value, ',');

    if (($last_dot !== false) && ($last_comma !== false)) {
        $decimal = ($last_dot > $last_comma) ? '.' : ',';
    } else {
        $sep = ($last_dot !== false) ? '.' : (($last_comma !== false) ? ',' : '');
        if ($sep === '') {
            $decimal = '';
        } elseif (substr_count($value, $sep) > 1) {
            $decimal = '';
        } else {
            $after = strlen($value) - strrpos($value, $sep) - 1;
            if ($after === 3) {
                $s = pg_number_separators();
                $decimal = ($s['decimal'] === $sep) ? $sep : '';
            } else {
                $decimal = $sep;
            }
        }
    }

    if ($decimal === '') {
        $number = str_replace(array('.', ','), '', $value);
    } else {
        $pos = strrpos($value, $decimal);
        $number = str_replace(array('.', ','), '', substr($value, 0, $pos)) . '.'
                . str_replace(array('.', ','), '', substr($value, $pos + 1));
    }

    $number = (float) $number;
    return $negative ? -$number : $number;
}

/**
 * An amount typed into a form, in the machine format ("1.234,56" ->
 * "1234.56"), for the handlers that validate with is_numeric() before they
 * store it. A value that is not a number is returned trimmed and otherwise
 * untouched, so that validation still rejects it.
 *
 * @param mixed $value
 * @return string
 */
function pg_normalize_amount($value)
{
    $raw = trim(str_replace(array("\u{00A0}", ' '), '', (string) $value));
    if (($raw === '') || !preg_match('/^-?[0-9.,]+$/', $raw) || !preg_match('/[0-9]/', $raw)) {
        return $raw;
    }
    return (string) pg_parse_amount($raw);
}

/**
 * What the storefront scripts need to write money the same way: a data
 * attribute value (JSON) with the separators and the visitor's symbol.
 *
 * @return string JSON
 */
function pg_money_format_json()
{
    $s = pg_number_separators();
    $symbol = defined('VISITOR_CURRENCY_SYMBOL') ? VISITOR_CURRENCY_SYMBOL : '';
    if (mb_substr($symbol, 0, 1) === '&') {
        $symbol = html_entity_decode($symbol, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    return json_encode(array(
        'decimal'   => $s['decimal'],
        'thousands' => $s['thousands'],
        'symbol'    => $symbol,
        'suffix'    => defined('VISITOR_CURRENCY_CODE_FOR_OUTPUT') ? VISITOR_CURRENCY_CODE_FOR_OUTPUT : '',
        'rate'      => pg_visitor_amount(1),
    ), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP);
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
                return '<span style="text-decoration: line-through; white-space: nowrap">' . $original_negative . $output_symbol . pg_format_number($original_amount, 2) . $output_code . '</span> <span style="white-space: nowrap" class="software_discounted_price">' . $discounted_negative . $output_symbol . pg_format_number($discounted_amount, 2) . $output_code . '</span>';
                // else the product is not discounted by an offer, so prepare to just show the product price
            } else {
                return '<span style="white-space: nowrap">' . $original_negative . $output_symbol . pg_format_number($original_amount, 2) . $output_code . '</span>';
            }
            break;
        // if the format is plain text then prepare to show price with no styling
        case 'plain_text':
            // if the product is discounted by an offer, then prepare to show original price and discounted price
            if ($discounted == true) {
                return lang(array('string' => '{var:1} (was {var:2})', 'vars' => array($discounted_negative . $output_symbol . pg_format_number($discounted_amount, 2) . $output_code, $original_negative . $output_symbol . pg_format_number($original_amount, 2) . $output_code)));
                // else the product is not discounted by an offer, so prepare to just show the product price
            } else {
                return $original_negative . $output_symbol . pg_format_number($original_amount, 2) . $output_code;
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
    // A missing amount (e.g. a submitted form that lacked the field) counts
    // as zero: PHP 8 compares '' < 0 as strings, which is true, and abs('')
    // then threw a TypeError that ended the whole request.
    $amount = str_replace(',', '', (string)$amount);
    $amount = is_numeric($amount) ? (float)$amount : 0.0;
    $negative_symbol = '';
    // If the amount is negative, then prepare to show negative sign before amount,
    // and convert amount to positive value.
    if ($amount < 0) {
        $negative_symbol = '-';
        $amount = abs($amount);
    }
    return $negative_symbol . BASE_CURRENCY_SYMBOL . pg_format_number($amount, 2);
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

            (order_items.order_id = '" . ($_SESSION['ecommerce']['order_id'] ?? '') . "')" . pg_order_item_active_sql() . "

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
            $liveform->add_notice(lang(array('string' => 'We\'re sorry, we require a quantity of {var:1} for {var:2}, so we have updated the quantity for you.', 'vars' => array(pg_format_number($order_item['minimum_quantity'], 0), h($product_description)))));
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
            $liveform->add_notice(lang(array('string' => 'We\'re sorry, we require a minimum quantity of {var:1} for {var:2}, so we have increased the quantity for you.', 'vars' => array(pg_format_number($order_item['minimum_quantity'], 0), h($product_description)))));
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
            $liveform->add_notice(lang(array('string' => 'We\'re sorry, we allow a maximum quantity of {var:1} for {var:2}, so we have decreased the quantity for you.', 'vars' => array(pg_format_number($order_item['maximum_quantity'], 0), h($product_description)))));
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

            AND (products.backorder = '0')" . pg_order_item_active_sql();
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

                (order_items.order_id = '" . ($_SESSION['ecommerce']['order_id'] ?? '') . "')" . pg_order_item_active_sql() . "

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

                (order_items.order_id = '" . ($_SESSION['ecommerce']['order_id'] ?? '') . "')" . pg_order_item_active_sql() . "

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
                    $liveform->add_notice(lang(array('string' => 'We\'re sorry, {var:1} has been removed from your order because it is no longer available.', 'vars' => array(h($product_description)))) . ' ' . $calendar_event['no_remaining_spots_message']);
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
/**
 * The carriers this software can recognise, name and link to.
 *
 * One entry per carrier, and everything about it in that entry. It used to be
 * three lists in three functions keyed by the same strings - the spellings a
 * shop may have typed, the name printed beside the number, the address the
 * customer follows - which is the shape that lets a carrier be added to two of
 * them and go missing from the third.
 *
 * The keys are also the accepted values of ECOMMERCE_DEFAULT_TRACKING_PROVIDER
 * (data/config.php), so a site naming a carrier for the whole shop can be told
 * when it names one that does not exist. pg_system_status() checks exactly
 * that.
 *
 * Order matters and is not alphabetical. The shapes are matched in this order
 * and some of them overlap: a FedEx SmartPost number also matches the USPS
 * patterns, and FedEx tracks it better, so FedEx is asked first.
 *
 * Per entry:
 *   name      what the customer is shown
 *   aliases   shipping_method_code spellings that mean this carrier. The key
 *             itself is always accepted and is not repeated here.
 *   url       tracking address, {code} replaced with the number
 *   patterns  number shapes that identify the carrier when the method code
 *             says nothing. Empty where a number has no recognisable shape -
 *             the Turkish carriers all use plain digits of similar lengths, so
 *             guessing between them would be guessing.
 *
 * @return array carrier key => entry
 */
function pg_shipping_carriers()
{
    return array(

        'yurtici' => array(
            'name'     => 'Yurtiçi Kargo',
            'aliases'  => array('yurtiçi', 'yurticikargo', 'yurtici kargo', 'yurtiçi kargo', 'tr-yurtici'),
            'url'      => 'https://www.yurticikargo.com/tr/online-servisler/gonderi-sorgula?code={code}',
            'patterns' => array()),

        'surat' => array(
            'name'     => 'Sürat Kargo',
            'aliases'  => array('sürat', 'suratkargo', 'surat kargo', 'sürat kargo', 'tr-surat'),
            'url'      => 'https://www.suratkargo.com.tr/KargoTakip/?kargotakipno={code}',
            'patterns' => array()),

        'aras' => array(
            'name'     => 'Aras Kargo',
            'aliases'  => array('araskargo', 'aras kargo', 'tr-aras'),
            'url'      => 'https://kargotakip.araskargo.com.tr/mainpage.aspx?code={code}',
            'patterns' => array()),

        'mng' => array(
            'name'     => 'MNG Kargo',
            'aliases'  => array('mngkargo', 'mng kargo', 'tr-mng'),
            'url'      => 'https://kargotakip.mngkargo.com.tr/?takipNo={code}',
            'patterns' => array()),

        'ptt' => array(
            'name'     => 'PTT Kargo',
            'aliases'  => array('pttkargo', 'ptt kargo', 'tr-ptt'),
            'url'      => 'https://gonderitakip.ptt.gov.tr/Track/summary?id={code}',
            'patterns' => array()),

        'ups' => array(
            'name'     => 'UPS',
            'aliases'  => array('ups kargo', 'upskargo'),
            'url'      => 'https://wwwapps.ups.com/WebTracking/processInputRequest?HTMLVersion=5.0&error_carried=true&tracknums_displayed=5&TypeOfInquiryNumber=T&loc=en_US&InquiryNumber1={code}',
            'patterns' => array(
                '/\b(1Z ?[0-9A-Z]{3} ?[0-9A-Z]{3} ?[0-9A-Z]{2} ?[0-9A-Z]{4} ?[0-9A-Z]{3} ?[0-9A-Z]|[\dT]\d\d\d ?\d\d\d\d ?\d\d\d)\b/')),

        'fedex' => array(
            'name'     => 'FedEx',
            'aliases'  => array('fedex kargo'),
            'url'      => 'https://www.fedex.com/Tracking?tracknumbers={code}&action=track',
            'patterns' => array(
                '/(\b96\d{20}\b)|(\b\d{15}\b)|(\b\d{12}\b)/',
                '/\b((98\d\d\d\d\d?\d\d\d\d|98\d\d) ?\d\d\d\d ?\d\d\d\d( ?\d\d\d)?)\b/',
                '/^[0-9]{15}$/',
                '/^927489\d{16}$/',
                '/^926129\d{16}$/')),

        'usps' => array(
            'name'     => 'USPS',
            'aliases'  => array(),
            'url'      => 'https://tools.usps.com/go/TrackConfirmAction?qtc_tLabels1={code}',
            'patterns' => array(
                '/(\b\d{30}\b)|(\b91\d+\b)|(\b\d{20}\b)/',
                '/^E\D{1}\d{9}\D{2}$|^9\d{15,21}$/',
                '/^91[0-9]+$/',
                '/^[A-Za-z]{2}[0-9]+US$/')),

    );
}

/**
 * Whether a string is one of the carrier keys above.
 *
 * @param  string $carrier
 * @return bool
 */
function pg_shipping_carrier_exists($carrier)
{
    $carriers = pg_shipping_carriers();

    return isset($carriers[trim((string) $carrier)]);
}

/**
 * Which carrier a tracking number belongs to.
 *
 * One answer, two uses: the link the customer clicks and the name printed
 * beside it. Both used to matter only on the panel's order screen, where the
 * link was built and the carrier never named; the customer-facing order view
 * then grew its own copy of this table with its own provider keys, which is
 * how a second, disagreeing list of carriers appeared. This is the single one.
 *
 * The method is ship_tos.shipping_method_code — what the customer chose at
 * checkout, in whatever spelling the shop set up, so it is read four ways in
 * turn: the whole code against the carrier names, then the code a word at a
 * time (a shop's method is usually named for the service, "UPS-5GUN", not for
 * the courier alone), then the site's default carrier when the code names
 * none, and finally the number's own shape — which is how orders imported
 * without a method code kept their links.
 *
 * The default comes before the shape on purpose: the Turkish carriers issue
 * plain digits that the UPS, FedEx and USPS patterns also match, so an 11-digit
 * Yurtiçi number read by its shape alone is "UPS". A shop that names its
 * courier for the whole site (ECOMMERCE_DEFAULT_TRACKING_PROVIDER) has said
 * what its unnamed parcels are; a parcel sent another way says so in its
 * method code.
 *
 * @param  string $number  tracking number
 * @param  string $method  ship_tos.shipping_method_code, or a bare carrier key
 * @param  string $default the site's default carrier key; one that is not a
 *                         key of pg_shipping_carriers() is ignored
 * @return string carrier key from pg_shipping_carriers(), or ''
 */
function pg_shipping_carrier($number, $method, $default = '')
{
    $number = trim((string) $number);

    if ($number === '') {
        return '';
    }

    $carriers = pg_shipping_carriers();

    // What the shop typed, first. A method code that names the carrier beats
    // any guess made from the number.
    $named = pg_shipping_method_carrier($method);

    if ($named !== '') {
        return $named;
    }

    // The code names no carrier: the one the site names for every such parcel.
    $default = pg_shipping_carrier_text($default);

    if (($default !== '') && isset($carriers[$default])) {
        return $default;
    }

    // No method code, or one this list does not know: the number's own shape.
    foreach ($carriers as $carrier => $entry) {
        foreach ($entry['patterns'] as $pattern) {
            if (preg_match($pattern, $number) == 1) {
                return $carrier;
            }
        }
    }

    return '';
}

/**
 * The carrier a shipping method's code, or a carrier's name, spells out.
 *
 * The first two of pg_shipping_carrier()'s four readings, on their own: the
 * whole text against every carrier's key and aliases, then the text a word at
 * a time. Neither the site's default nor a number's shape takes part, so the
 * answer is a carrier somebody named - which is what a shipment row keeps
 * (pg_shipment_record()); a guess stays a guess made at read time.
 *
 * @param  string $method ship_tos.shipping_method_code, a carrier key or a
 *                        carrier's name ("Yurtiçi Kargo")
 * @return string carrier key from pg_shipping_carriers(), or ''
 */
function pg_shipping_method_carrier($method)
{
    $carriers = pg_shipping_carriers();

    $needle = pg_shipping_carrier_text($method);

    if ($needle !== '') {
        foreach ($carriers as $carrier => $entry) {
            if (in_array($needle, pg_shipping_carrier_names($carrier, $entry), true)) {
                return $carrier;
            }
        }
    }

    // Still the shop's own code, read a word at a time.
    //
    // A shipping method is usually named for the service rather than the
    // courier - "UPS-5GUN", "MNG Ertesi Gün", "tr-aras-standart" - and the
    // list above cannot hold every shop's spelling of every service. Splitting
    // the code into words and matching a whole word against a carrier's name
    // catches those without catching anything else: "5gun" is not a carrier
    // and "Standart Teslimat" still names none.
    //
    // Whole words only, and only single-word names: a code that merely
    // contains the letters of a carrier is not that carrier, and this is the
    // difference between reading "UPS-5GUN" as UPS and reading a customer's
    // surname as one.
    if ($needle !== '') {

        $words = preg_split('/[^\p{L}\p{N}]+/u', $needle, -1, PREG_SPLIT_NO_EMPTY);

        if (is_array($words) && (count($words) > 1)) {

            foreach ($carriers as $carrier => $entry) {

                foreach (pg_shipping_carrier_names($carrier, $entry) as $name) {
                    if ((strpos($name, ' ') === false) && (in_array($name, $words, true))) {
                        return $carrier;
                    }
                }

            }

        }

    }

    return '';
}

/**
 * A carrier name or method code in the one form they are compared in.
 *
 * Turkish capitals first: since PHP 7.3 mb_strtolower() turns "İ" into "i"
 * plus a combining dot, so "YURTİÇİ KARGO" matched nothing, and the Turkish
 * lower case of "I" is "ı". The letters are then folded to ASCII, so
 * "yurtiçi", "yurtıcı" and "YURTICI" typed on an English keyboard all read
 * "yurtici" and every spelling of a carrier name compares the same.
 *
 * @param  string $text
 * @return string
 */
function pg_shipping_carrier_text($text)
{
    $text = str_replace(array('İ', 'I'), array('i', 'ı'), trim((string) $text));

    return pg_transliterate_to_ascii(mb_strtolower($text, 'UTF-8'));
}

/**
 * Every name a carrier answers to - its key and its aliases - in the form
 * pg_shipping_carrier_text() gives a method code.
 *
 * @param  string $carrier key
 * @param  array  $entry   its pg_shipping_carriers() entry
 * @return array
 */
function pg_shipping_carrier_names($carrier, $entry)
{
    $names = array();

    foreach (array_merge(array($carrier), $entry['aliases']) as $name) {
        $name = pg_shipping_carrier_text($name);

        if (($name !== '') && !in_array($name, $names, true)) {
            $names[] = $name;
        }
    }

    return $names;
}

/**
 * The carrier the site names for parcels whose method code names none
 * (ECOMMERCE_DEFAULT_TRACKING_PROVIDER, data/config.php).
 *
 * @return string a carrier key, or '' when none is set or the value is not a
 *                key (System Status reports such a value)
 */
function pg_shipping_default_carrier()
{
    if (!defined('ECOMMERCE_DEFAULT_TRACKING_PROVIDER')) {
        return '';
    }

    $carrier = pg_shipping_carrier_text(ECOMMERCE_DEFAULT_TRACKING_PROVIDER);

    return pg_shipping_carrier_exists($carrier) ? $carrier : '';
}

/**
 * Where the customer follows the parcel.
 *
 * @param  string      $number  tracking number (shipping_tracking_numbers.number)
 * @param  string      $method  ship_tos.shipping_method_code
 * @param  string|null $default the default carrier; null reads the site's
 *                              (pg_shipping_default_carrier())
 * @return string absolute URL, or '' when the carrier cannot be identified
 */
function get_shipping_tracking_url($number, $method, $default = null)
{
    $number = trim((string) $number);

    $carrier = pg_shipping_carrier($number, $method, ($default === null) ? pg_shipping_default_carrier() : $default);

    if ($carrier === '') {
        return '';
    }

    $carriers = pg_shipping_carriers();

    return str_replace('{code}', rawurlencode($number), $carriers[$carrier]['url']);
}

/**
 * The carrier's name, for printing beside the number.
 *
 * @param  string      $number  tracking number
 * @param  string      $method  ship_tos.shipping_method_code
 * @param  string|null $default the default carrier; null reads the site's
 *                              (pg_shipping_default_carrier())
 * @return string carrier name, or '' when it cannot be identified
 */
function get_shipping_carrier_name($number, $method, $default = null)
{
    $carrier = pg_shipping_carrier($number, $method, ($default === null) ? pg_shipping_default_carrier() : $default);

    if ($carrier === '') {
        return '';
    }

    $carriers = pg_shipping_carriers();

    return $carriers[$carrier]['name'];
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
    // products.compare_at_price (2026.11.0) is copied where the table has it,
    // which the SELECT * above shows: the copy starts with the original's "was"
    // price as it starts with its price.
    $compare_at_column = array_key_exists('compare_at_price', $row) ? 'compare_at_price,' : '';
    $compare_at_value = array_key_exists('compare_at_price', $row) ? pg_compare_at_price_sql($row['compare_at_price']) . ',' : '';
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

            " . $compare_at_column . "

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

            " . $compare_at_value . "

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

    // The copy starts a price history of its own at the original's price.
    pg_price_history_record($new_product_id, 'panel');

    pg_audit_record('create', 'products', (int) $new_product_id);

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
            return 'USPS Ground Advantage';
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

/**
 * The shipping method list's columns that change in place
 * (pg_list_edit_table()): the base rate and the item rate, the two that move
 * with the carrier's prices. An empty box is no charge, the way the method's
 * own screen saves it.
 *
 * @return array field => definition
 */
function pg_shipping_method_edit_columns()
{
    return array(
        'base_rate' => array('type' => 'money', 'label' => lang('Base Rate'), 'empty' => true),
        'item_rate' => array('type' => 'money', 'label' => lang('Item Rate'), 'empty' => true),
    );
}

/**
 * The shipping method list's cells a quick edit changes, drawn the one way
 * the list and the answer to an edit both use. A variable base rate shows
 * every step with the subtotal it starts at.
 *
 * @param array $method a shipping_methods row with base_rate,
 *                      variable_base_rate, base_rate_2..4 and their
 *                      _subtotal, item_rate, timestamp and user (the
 *                      username of the last change, or empty)
 * @return array 'base_rate', 'item_rate' and 'last_modified' => array('html', 'class')
 */
function pg_shipping_method_list_cells($method)
{
    $money = function ($cents) {
        return pg_format_money(((int) $cents) / 100, BASE_CURRENCY_SYMBOL);
    };

    if (!empty($method['variable_base_rate'])) {
        $base_rate = $money($method['base_rate']) . ' (@' . $money(0) . ')';

        for ($number = 2; $number <= 4; $number++) {
            if (!empty($method['base_rate_' . $number . '_subtotal'])) {
                $base_rate .= '<br>' . $money($method['base_rate_' . $number]) . ' (@' . $money($method['base_rate_' . $number . '_subtotal']) . ')';
            }
        }
    } else {
        $base_rate = $money($method['base_rate']);
    }

    return array(
        'base_rate'     => array('html' => $base_rate, 'class' => 'align-middle text-end'),
        'item_rate'     => array('html' => $money($method['item_rate']), 'class' => 'align-middle text-end'),
        'last_modified' => array(
            'html'  => get_relative_time(array('timestamp' => $method['timestamp']))
                . (((string) ($method['user'] ?? '') !== '') ? ' ' . lang(array('string' => 'by {var:1}', 'vars' => array(h((string) $method['user'])))) : ''),
            'class' => 'align-middle',
        ),
    );
}

/**
 * Write one quick edit from the shipping method list the way the method's
 * screen (edit_shipping_method.php) writes the same box: the amount read by
 * pg_normalize_amount() and kept in cents, the last change stamped with who
 * made it, a line in the Site Log.
 *
 * A variable base rate has a step for each order subtotal; the list does not
 * offer it, and a request for it is refused, since one box cannot say which
 * step it means.
 *
 * @param int    $method_id
 * @param string $field   a key of pg_shipping_method_edit_columns()
 * @param string $value   as pg_list_edit_parse() read it, '' for no charge
 * @param int    $user_id
 * @return array the answer for pg_list_edit_reply()
 */
function pg_shipping_method_quick_update($method_id, $field, $value, $user_id)
{
    $method_id = (int) $method_id;
    $columns = pg_shipping_method_edit_columns();

    if (!isset($columns[$field])) {
        return array('ok' => false, 'code' => 'invalid', 'message' => lang('This column cannot be changed here.'));
    }

    $read = function () use ($method_id) {
        return db_item(
            "SELECT
                shipping_methods.id,
                shipping_methods.name,
                shipping_methods.base_rate,
                shipping_methods.variable_base_rate,
                shipping_methods.base_rate_2,
                shipping_methods.base_rate_2_subtotal,
                shipping_methods.base_rate_3,
                shipping_methods.base_rate_3_subtotal,
                shipping_methods.base_rate_4,
                shipping_methods.base_rate_4_subtotal,
                shipping_methods.item_rate,
                shipping_methods.timestamp,
                user.user_username AS user
            FROM shipping_methods
            LEFT JOIN user ON shipping_methods.user = user.user_id
            WHERE shipping_methods.id = '" . $method_id . "'");
    };

    $current = $read();

    if (!$current) {
        return array('ok' => false, 'code' => 'not_found', 'message' => lang('The shipping method could not be found.'));
    }

    if (($field === 'base_rate') && !empty($current['variable_base_rate'])) {
        return array('ok' => false, 'code' => 'variable', 'message' => lang('This shipping method has a base rate for each order subtotal. Change it in the shipping method.'));
    }

    $cents = (trim((string) $value) === '') ? 0 : round(((float) pg_normalize_amount($value)) * 100);

    // The rate columns are INT UNSIGNED.
    if ($cents > 4294967295) {
        return array('ok' => false, 'code' => 'invalid', 'message' => lang(array('string' => '{var:1} is too large.', 'vars' => array($columns[$field]['label']))));
    }

    $cents = (int) $cents;

    // The same amount again writes nothing and logs nothing; the list is
    // still redrawn from what is stored.
    if ((int) $current[$field] !== $cents) {
        db("UPDATE shipping_methods
            SET " . (($field === 'item_rate') ? 'item_rate' : 'base_rate') . " = '" . $cents . "',
                user = '" . (int) $user_id . "',
                timestamp = UNIX_TIMESTAMP()
            WHERE id = '" . $method_id . "'");

        log_activity(
            lang(array(
                'string' => 'shipping method ({var:1}) was changed on the shipping method list: {var:2} from "{var:3}" to "{var:4}"',
                'vars'   => array((string) $current['name'], $columns[$field]['label'], pg_format_number(((int) $current[$field]) / 100, 2), pg_format_number($cents / 100, 2)),
            )),
            $_SESSION['sessionusername'] ?? '');
    }

    $method = $read();
    $cells = pg_shipping_method_list_cells($method);

    return array('ok' => true, 'value' => pg_format_input_amount(((int) $method[$field]) / 100))
        + $cells[$field]
        + array('cells' => array('last_modified' => $cells['last_modified']));
}

/**
 * The key code list's columns that change in place (pg_list_edit_table()):
 * whether the code is enabled, the switch an operator turns most often
 * without the rest of the key code's screen.
 *
 * @return array field => definition
 */
function pg_key_code_edit_columns()
{
    return array(
        'enabled' => array('type' => 'select', 'label' => lang('Enabled'), 'options' => array('1' => lang('Enabled'), '0' => lang('Disabled'))),
    );
}

/**
 * Whether a key code works at checkout on the given day: enabled, not
 * expired, and the offer it names enabled and running. The list colours the
 * code by it.
 *
 * @param array  $key_code a key_codes row with enabled and expiration_date,
 *                         and offer_status, offer_start_date and
 *                         offer_end_date from the offer it names (null when
 *                         no offer has that code)
 * @param string $today    Y-m-d
 * @return bool
 */
function pg_key_code_active($key_code, $today)
{
    $expires = (string) $key_code['expiration_date'];

    return !empty($key_code['enabled'])
        && (($expires === '0000-00-00') || ($expires >= $today))
        && ((string) $key_code['offer_status'] === 'enabled')
        && ((string) $key_code['offer_start_date'] <= $today)
        && ((string) $key_code['offer_end_date'] >= $today);
}

/**
 * The key code list's cells a quick edit changes, drawn the one way the list
 * and the answer to an edit both use: the code coloured by whether it works
 * today, the enabled mark and the last change.
 *
 * @param array  $key_code a row as pg_key_code_active() reads it, with
 *                         last_modified_timestamp and last_modified_username
 *                         (empty when nobody is recorded)
 * @param string $today    Y-m-d
 * @return array 'code' => array('class'), 'enabled' and 'last_modified' => array('html', 'class')
 */
function pg_key_code_list_cells($key_code, $today)
{
    $username = (string) ($key_code['last_modified_username'] ?? '');

    return array(
        'code'          => array('class' => 'chart_label align-middle ' . (pg_key_code_active($key_code, $today) ? 'status_enabled text-success' : 'status_disabled text-danger')),
        'enabled'       => array('html' => !empty($key_code['enabled']) ? '<span class="material-icons">task_alt</span>' : '', 'class' => 'align-middle text-center'),
        'last_modified' => array(
            'html'  => get_relative_time(array('timestamp' => $key_code['last_modified_timestamp']))
                . (($username !== '') ? ' ' . lang(array('string' => 'by {var:1}', 'vars' => array(h($username)))) : ''),
            'class' => 'align-middle',
        ),
    );
}

/**
 * Write one quick edit from the key code list the way the key code's screen
 * (edit_key_code.php) writes the same field: the flag, the last change
 * stamped with who made it, a line in the Site Log.
 *
 * @param int    $key_code_id
 * @param string $field       a key of pg_key_code_edit_columns()
 * @param string $value       as pg_list_edit_parse() read it
 * @param int    $user_id
 * @return array the answer for pg_list_edit_reply()
 */
function pg_key_code_quick_update($key_code_id, $field, $value, $user_id)
{
    $key_code_id = (int) $key_code_id;
    $columns = pg_key_code_edit_columns();

    if (!isset($columns[$field]['options'][(string) $value])) {
        return array('ok' => false, 'code' => 'invalid', 'message' => lang('This column cannot be changed here.'));
    }

    // Two offers can share a code; the list joins them the same way and
    // colours the row by the first.
    $read = function () use ($key_code_id) {
        return db_item(
            "SELECT
                key_codes.id,
                key_codes.code,
                key_codes.enabled,
                key_codes.expiration_date,
                key_codes.timestamp AS last_modified_timestamp,
                last_modified_user.user_username AS last_modified_username,
                offers.status AS offer_status,
                offers.start_date AS offer_start_date,
                offers.end_date AS offer_end_date
            FROM key_codes
            LEFT JOIN offers ON key_codes.offer_code = offers.code
            LEFT JOIN user AS last_modified_user ON key_codes.user = last_modified_user.user_id
            WHERE key_codes.id = '" . $key_code_id . "'
            LIMIT 1");
    };

    $current = $read();

    if (!$current) {
        return array('ok' => false, 'code' => 'not_found', 'message' => lang('Record not found.'));
    }

    $previous = !empty($current['enabled']) ? '1' : '0';
    $enabled = ((string) $value === '1') ? '1' : '0';

    // The same value again writes nothing and logs nothing; the row is still
    // redrawn from what is stored.
    if ($previous !== $enabled) {
        db("UPDATE key_codes
            SET enabled = '" . $enabled . "',
                user = '" . (int) $user_id . "',
                timestamp = UNIX_TIMESTAMP()
            WHERE id = '" . $key_code_id . "'");

        log_activity(
            lang(array(
                'string' => 'key code ({var:1}) was changed on the key code list: {var:2} from "{var:3}" to "{var:4}"',
                'vars'   => array((string) $current['code'], $columns[$field]['label'], $columns[$field]['options'][$previous], $columns[$field]['options'][$enabled]),
            )),
            $_SESSION['sessionusername'] ?? '');
    }

    $key_code = $read();

    if (!$key_code) {
        return array('ok' => false, 'code' => 'not_found', 'message' => lang('Record not found.'));
    }

    $cells = pg_key_code_list_cells($key_code, date('Y-m-d'));

    return array('ok' => true, 'value' => !empty($key_code['enabled']) ? '1' : '0')
        + $cells['enabled']
        + array('cells' => array('code' => $cells['code'], 'last_modified' => $cells['last_modified']));
}

/**
 * The key_codes column a header cell of an imported key code file names
 * (import_key_codes.php), '' for one the import does not read. Case, white
 * space, underscores and dashes do not count ("Key Code", "key_code",
 * "keycode"), nor a byte order mark left on the first cell. The export's
 * headings (view_key_codes.php) all read.
 *
 * @param string $name
 * @return string
 */
function pg_key_code_import_column($name)
{
    $name = (string) $name;

    if (substr($name, 0, 3) === "\xEF\xBB\xBF") {
        $name = substr($name, 3);
    }

    $name = str_replace(array(' ', '_', '-', "\t", "\xC2\xA0"), '', mb_strtolower(trim($name)));

    $columns = array(
        'keycode'        => 'code',
        'offercode'      => 'offer_code',
        'enabled'        => 'enabled',
        'expirationdate' => 'expiration_date',
        'notes'          => 'notes',
        'singleuse'      => 'single_use',
        'report'         => 'report',
    );

    return isset($columns[$name]) ? $columns[$name] : '';
}

/**
 * The separator of an imported key code file, read from its header line: of
 * the comma, the semicolon and the tab, the one the line holds most; the
 * comma when it holds none of them (a file of codes alone). Excel saves a
 * CSV file with the list separator of the computer's region, a semicolon in
 * Turkish.
 *
 * @param string $header
 * @return string
 */
function pg_key_code_import_separator($header)
{
    $header = (string) $header;
    $best = ',';
    $most = substr_count($header, ',');

    foreach (array(';', "\t") as $separator) {
        $count = substr_count($header, $separator);

        if ($count > $most) {
            $best = $separator;
            $most = $count;
        }
    }

    return $best;
}

/**
 * A yes/no cell of an imported key code file (enabled, single_use): 1 or 0,
 * null for an empty cell, false for anything else. The export writes 1 and
 * 0; yes/no, true/false and Excel's TRUE/FALSE (DOĞRU/YANLIŞ in Turkish)
 * and evet/hayır read too.
 *
 * @param string $value
 * @return int|null|false
 */
function pg_key_code_import_flag($value)
{
    $value = mb_strtolower(trim((string) $value));

    if ($value === '') {
        return null;
    }

    if (in_array($value, array('1', 'yes', 'true', 'evet', 'doğru', 'dogru'), true)) {
        return 1;
    }

    if (in_array($value, array('0', 'no', 'false', 'hayır', 'hayir', 'yanlış', 'yanliş', 'yanlis'), true)) {
        return 0;
    }

    return false;
}

/**
 * An expiration date cell of an imported key code file as the column keeps
 * it: Y-m-d, '0000-00-00' for an empty cell (the code does not expire, as
 * the export writes it), false for a cell that is not a date. Besides the
 * export's Y-m-d, a date Excel wrote back in the computer's own format
 * reads: day.month.year with dots, and with slashes or dashes in the site's
 * order (DATE_FORMAT), the other order when the site's is not a date
 * (12/31/2026 on a day-first site). A two-digit year or a time after the
 * date is not read: the row is reported instead of a code that never
 * expires.
 *
 * @param string $value
 * @param string $date_format month_day | day_month
 * @return string|false
 */
function pg_key_code_import_date($value, $date_format)
{
    $value = trim((string) $value);

    if (($value === '') || ($value === '0000-00-00')) {
        return '0000-00-00';
    }

    if (preg_match('/^(\d{4})([-\/.])(\d{1,2})\2(\d{1,2})$/', $value, $parts)) {
        return checkdate((int) $parts[3], (int) $parts[4], (int) $parts[1])
            ? sprintf('%04d-%02d-%02d', $parts[1], $parts[3], $parts[4])
            : false;
    }

    if (!preg_match('/^(\d{1,2})([-\/.])(\d{1,2})\2(\d{4})$/', $value, $parts)) {
        return false;
    }

    $first = (int) $parts[1];
    $second = (int) $parts[3];
    $year = (int) $parts[4];

    // month, day pairs in the order they are tried
    if ($parts[2] === '.') {
        $orders = array(array($second, $first));
    } else if ($date_format === 'month_day') {
        $orders = array(array($first, $second), array($second, $first));
    } else {
        $orders = array(array($second, $first), array($first, $second));
    }

    foreach ($orders as $order) {
        if (checkdate($order[0], $order[1], $year)) {
            return sprintf('%04d-%02d-%02d', $year, $order[0], $order[1]);
        }
    }

    return false;
}

/**
 * One row of an imported key code file read into the columns it writes.
 * 'values' holds the code (also one too long to store, so that row is
 * reported rather than taken for a blank line) and every other column of
 * the file whose cell can be stored: an empty expiration date is "does not expire" and empty notes
 * are none, as the export writes them; an empty offer code, flag or report
 * is left out, so a code already there keeps its own and a new one takes
 * the default. 'error' names the first cell that cannot be stored (the row
 * is then skipped whole), '' when there is none.
 *
 * @param array  $row         the cells as fgetcsv() read them
 * @param array  $columns     cell index => key_codes column
 * @param string $date_format month_day | day_month
 * @return array 'values' => column => value, 'error' => string
 */
function pg_key_code_import_row($row, $columns, $date_format)
{
    $values = array('code' => '');
    $error = '';

    foreach ($columns as $index => $column) {
        $cell = (is_array($row) && isset($row[$index])) ? trim((string) $row[$index]) : '';

        switch ($column) {
            case 'code':
            case 'offer_code':
                if (mb_strlen($cell) > 50) {
                    $error = ($error !== '') ? $error : lang(array('string' => '"{var:1}" is longer than 50 characters.', 'vars' => array($cell)));

                    // A key code too long to store is still kept: the row is
                    // then reported with it, not passed over as a blank line.
                    if ($column === 'code') {
                        $values[$column] = $cell;
                    }
                } else if (($cell !== '') || ($column === 'code')) {
                    $values[$column] = $cell;
                }
                break;

            case 'enabled':
            case 'single_use':
                $flag = pg_key_code_import_flag($cell);

                if ($flag === false) {
                    $error = ($error !== '') ? $error : lang(array('string' => '"{var:1}" in the {var:2} column is neither yes nor no.', 'vars' => array($cell, $column)));
                } else if ($flag !== null) {
                    $values[$column] = $flag;
                }
                break;

            case 'expiration_date':
                $date = pg_key_code_import_date($cell, $date_format);

                if ($date === false) {
                    $error = ($error !== '') ? $error : lang(array('string' => '"{var:1}" is not an expiration date.', 'vars' => array($cell)));
                } else {
                    $values[$column] = $date;
                }
                break;

            case 'report':
                $report = str_replace(array(' ', '-'), '_', mb_strtolower($cell));

                if (in_array($report, array('key_code', 'offer_code'), true)) {
                    $values[$column] = $report;
                } else if ($cell !== '') {
                    $error = ($error !== '') ? $error : lang(array('string' => '"{var:1}" in the report column is neither key_code nor offer_code.', 'vars' => array($cell)));
                }
                break;

            case 'notes':
                $values[$column] = $cell;
                break;
        }
    }

    return array('values' => $values, 'error' => $error);
}

/**
 * The gift card list's columns that change in place (pg_list_edit_table()):
 * the notes, which an operator keeps about a card without touching its
 * money. The balance and the expiration date stay on the gift card's screen.
 *
 * @return array field => definition
 */
function pg_gift_card_edit_columns()
{
    return array(
        'notes' => array('type' => 'text', 'label' => lang('Notes')),
    );
}

/**
 * Whether a gift card's notes can change on the list. The list's box holds
 * one line and reads a line break as a space, so notes written on more lines
 * (on the gift card's screen, or the line a cancelled order adds) change only
 * on the gift card's screen, where the lines are kept.
 *
 * @param string $notes
 * @return bool
 */
function pg_gift_card_notes_fit_list($notes)
{
    return strpbrk((string) $notes, "\r\n") === false;
}

/**
 * The gift card list's cells a quick edit changes, drawn the one way the list
 * and the answer to an edit both use.
 *
 * @param array $gift_card a gift_cards row with notes,
 *                         last_modified_timestamp and last_modified_username
 *                         (empty when nobody is recorded)
 * @return array 'notes' and 'last_modified' => array('html', 'class')
 */
function pg_gift_card_list_cells($gift_card)
{
    $username = (string) ($gift_card['last_modified_username'] ?? '');

    return array(
        'notes'         => array('html' => nl2br(h((string) $gift_card['notes'])), 'class' => 'align-middle'),
        'last_modified' => array(
            'html'  => get_relative_time(array('timestamp' => $gift_card['last_modified_timestamp']))
                . (($username !== '') ? ' ' . lang(array('string' => 'by {var:1}', 'vars' => array(h($username)))) : ''),
            'class' => 'align-middle',
        ),
    );
}

/**
 * Write one quick edit from the gift card list the way the gift card's
 * screen (edit_gift_card.php) writes the notes: the last change stamped with
 * who made it, a line in the Site Log.
 *
 * Notes on more than one line are refused (pg_gift_card_notes_fit_list()),
 * and the write only lands on the notes that were read: cancelling the order
 * that bought a card adds a line to its notes, and a change typed over the
 * old notes must not wipe that line out.
 *
 * @param int    $gift_card_id
 * @param string $field        a key of pg_gift_card_edit_columns()
 * @param string $value        as pg_list_edit_parse() read it, '' for no notes
 * @param int    $user_id
 * @return array the answer for pg_list_edit_reply()
 */
function pg_gift_card_quick_update($gift_card_id, $field, $value, $user_id)
{
    $gift_card_id = (int) $gift_card_id;
    $columns = pg_gift_card_edit_columns();

    if (!isset($columns[$field])) {
        return array('ok' => false, 'code' => 'invalid', 'message' => lang('This column cannot be changed here.'));
    }

    $read = function () use ($gift_card_id) {
        return db_item(
            "SELECT
                gift_cards.id,
                gift_cards.code,
                gift_cards.notes,
                gift_cards.last_modified_timestamp,
                last_modified_user.user_username AS last_modified_username
            FROM gift_cards
            LEFT JOIN user AS last_modified_user ON gift_cards.last_modified_user_id = last_modified_user.user_id
            WHERE gift_cards.id = '" . $gift_card_id . "'");
    };

    $current = $read();

    if (!$current) {
        return array('ok' => false, 'code' => 'not_found', 'message' => lang('Record not found.'));
    }

    if (!pg_gift_card_notes_fit_list($current['notes'])) {
        return array('ok' => false, 'code' => 'lines', 'message' => lang('These notes take more than one line. Change them in the gift card.'));
    }

    $notes = (string) $value;

    // The same notes again write nothing and log nothing; the cell is still
    // redrawn from what is stored.
    if ((string) $current['notes'] !== $notes) {
        db("UPDATE gift_cards
            SET notes = '" . e($notes) . "',
                last_modified_user_id = '" . (int) $user_id . "',
                last_modified_timestamp = UNIX_TIMESTAMP()
            WHERE id = '" . $gift_card_id . "' AND notes = '" . e((string) $current['notes']) . "'");

        if (mysqli_affected_rows(db::$con) < 1) {
            return array('ok' => false, 'code' => 'changed', 'message' => lang('The gift card changed in the meantime. Reload the list and try again.'));
        }

        log_activity(
            lang(array(
                'string' => 'gift card ({var:1}) was changed on the gift card list: {var:2} from "{var:3}" to "{var:4}"',
                'vars'   => array(output_gift_card_code($current['code']), $columns[$field]['label'], (string) $current['notes'], $notes),
            )),
            $_SESSION['sessionusername'] ?? '');
    }

    $gift_card = $read();

    if (!$gift_card) {
        return array('ok' => false, 'code' => 'not_found', 'message' => lang('Record not found.'));
    }

    $cells = pg_gift_card_list_cells($gift_card);

    return array('ok' => true, 'value' => (string) $gift_card['notes'])
        + $cells['notes']
        + array('cells' => array('last_modified' => $cells['last_modified']));
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
 *   7. Optional: automatic refund through the payment method's driver
 *      (ECOMMERCE_ORDER_CANCEL_AUTO_REFUND) — writes
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
        return array('status' => 'error', 'message' => lang('Order id is missing.'), 'refund_status' => '', 'order_id' => 0);
    }

    // Minimal selection — enough for cancel gating + email + refund.
    // refunded_amount: what the order screen or a return gave back already,
    // which the cancellation must not return a second time.
    $order = db_item(
        "SELECT id, status, user_id, payment_method, transaction_id, total,
                " . (pg_schema_has('orders', 'refunded_amount') ? 'refunded_amount' : '0 AS refunded_amount') . ",
                billing_first_name, billing_last_name, billing_email_address,
                order_number, order_date
         FROM orders
         WHERE id = '" . e($order_id) . "'
         LIMIT 1"
    );

    if (!$order) {
        return array('status' => 'not_found', 'message' => lang('Order not found.'), 'refund_status' => '', 'order_id' => $order_id);
    }

    // Already cancelled — idempotent no-op.
    if ($order['status'] === 'cancelled') {
        return array('status' => 'already', 'message' => lang('Order was already cancelled.'), 'refund_status' => '', 'order_id' => $order_id);
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
        return array('status' => 'shipped', 'message' => lang('Order has already shipped.'), 'refund_status' => '', 'order_id' => $order_id);
    }

    $reason_clean = trim(mb_substr((string) $reason, 0, 500));

    // ── Persist cancellation ────────────────────────────────────────────
    // Only the request that takes the order out of its status goes on. Two
    // cancellations at once (a double submit, the row's Cancel and the bulk
    // one) both passed the check above: both voided the payment, mailed the
    // customer and announced order.cancelled, and the second void's failure
    // overwrote the first one's refund status.
    // One clock read: restore finds the expired gift cards by the day before
    // cancelled_at, so both must come from the same instant (a read on each
    // side of midnight would set the cards one day off).
    $now = time();
    db(
        "UPDATE orders SET
            status              = 'cancelled',
            cancelled_at        = '" . $now . "',
            cancelled_by        = '" . (int) $user_id . "',
            cancellation_reason = '" . escape($reason_clean) . "'
         WHERE id = '" . e($order_id) . "'
           AND status <> 'cancelled'"
    );
    if (mysqli_affected_rows(db::$con) < 1) {
        return array('status' => 'already', 'message' => lang('Order was already cancelled.'), 'refund_status' => '', 'order_id' => $order_id);
    }

    // ── Expire gift cards issued by this order ──────────────────────────
    // Folded in from the removed legacy cancel_order(). A cancelled order
    // must not leave spendable gift cards behind, so we push their
    // expiration to yesterday (immediately invalid) and leave an audit note.
    // The note names the order and the date the card had, which is what a
    // restore of the order puts back (pg_gift_card_expiration_before_cancel()).
    // Rows that already expired earlier are left untouched.
    $yesterday = date('Y-m-d', strtotime('-1 day', $now));
    $expiring_gift_cards = db_items(
        "SELECT id, expiration_date
         FROM gift_cards
         WHERE order_id = '" . e($order_id) . "'
           AND (expiration_date = '0000-00-00' OR expiration_date > '" . $yesterday . "')"
    );
    foreach ((array) $expiring_gift_cards as $expiring_gift_card) {
        if ($expiring_gift_card['expiration_date'] === '0000-00-00') {
            $cancel_note = lang(array(
                'string' => 'This gift card was automatically expired because order #{var:1} was cancelled. It had no expiration date.',
                'vars'   => array($order['order_number'])));
        } else {
            $cancel_note = lang(array(
                'string' => 'This gift card was automatically expired because order #{var:1} was cancelled. Its expiration date was {var:2}.',
                'vars'   => array($order['order_number'], $expiring_gift_card['expiration_date'])));
        }
        db(
            "UPDATE gift_cards
             SET
                expiration_date = '" . $yesterday . "',
                notes = CONCAT(IF(notes IS NULL OR notes = '', '', CONCAT(notes, '\n')), '" . e($cancel_note) . "')
             WHERE id = '" . (int) $expiring_gift_card['id'] . "'"
        );
    }

    log_activity(
        'Order #' . $order_id . ' cancelled by ' .
        ($is_admin ? 'admin' : 'customer') . ' (user_id=' . (int) $user_id . ').' .
        ($reason_clean !== '' ? ' Reason: ' . $reason_clean : '')
    );

    // ── Automatic refund (opt-in) ───────────────────────────────────────
    // Default OFF. When ECOMMERCE_ORDER_CANCEL_AUTO_REFUND === true and the
    // order's payment has a driver that can cancel (pg_payment_cancel_driver():
    // the gateway's for a card order, the method's own otherwise), the payment
    // is voided through it. Failures here MUST NOT roll back the cancellation —
    // refunds can be retried manually from the gateway dashboard. We just
    // record the outcome on the order row so the operator can audit.
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
    // The money left on the order decides (pg_order_cancel_payment_plan()):
    // an order refunded in full is not voided and not flagged, and one partly
    // refunded is not voided either (a void reverses the whole payment); its
    // remaining amount is flagged. The cancel window asks the same.
    $cancel_driver = pg_payment_cancel_driver((string) $order['payment_method']);
    $payment_plan  = pg_order_cancel_payment_plan(
        $order['total'],
        $order['refunded_amount'],
        $order['payment_method'],
        !empty($order['transaction_id']),
        $cancel_driver,
        $auto_refund
    );

    if ($payment_plan['action'] === 'void') {
        // The driver is given the orders row as the cancellation left it, so
        // the reason just typed goes to the service as the description.
        $cancelled_order = db_item("SELECT * FROM orders WHERE id = '" . e($order_id) . "' LIMIT 1");
        if (!is_array($cancelled_order)) {
            $cancelled_order = $order;
        }
        $cancelled_order['cancellation_reason'] = $reason_clean;

        // pg_payment_call() never throws: an exception inside the driver comes
        // back as a failure, which the outcome logs as an EXCEPTION line.
        $cancel_outcome = pg_payment_cancel_outcome(
            $order_id,
            (string) $order['transaction_id'],
            pg_payment_call($cancel_driver, 'cancel', array($cancelled_order, (string) $order['transaction_id'])),
            pg_payment_log_name($cancel_driver)
        );

        foreach ($cancel_outcome['log'] as $cancel_log_line) {
            log_activity($cancel_log_line);
        }

        $refund_status_outcome = $cancel_outcome['refund_status'];
        $refund_reference      = $cancel_outcome['refund_reference'];
    } elseif ($payment_plan['action'] === 'manual') {
        // No auto-refund — but money did change hands, so flag for the operator.
        $refund_status_outcome = 'manual_required';
        log_activity(lang(array(
            'string' => 'REFUND ACTION REQUIRED — order #{var:1} was cancelled. Payment method: {var:2}. Amount: {var:3}. Process refund in the gateway dashboard.',
            'vars'   => array((int) $order_id, (string) $order['payment_method'], number_format($payment_plan['remaining'] / 100, 2, '.', ',')))));
        if ($payment_plan['refunded'] > 0) {
            log_activity(lang(array(
                'string' => 'Order #{var:1}: {var:2} of the payment was refunded before the cancellation, so the payment was not voided; only the amount above is still to return.',
                'vars'   => array((int) $order_id, number_format($payment_plan['refunded'] / 100, 2, '.', ',')))));
        }
    }

    // What gift cards paid of the order does not go back to them by itself,
    // and orders.total has it taken off already: an order paid by gift card
    // alone raised no flag above. The operator is told what to return to
    // which card. The refund status stays what the payment step reported (a
    // successful void keeps 'refunded', so the customer email, the webhook
    // and the restore keep the record of it); only an order the payment step
    // left without an outcome is flagged here.
    $gift_card_refund_line = pg_order_gift_card_refund_line($order_id, (array) db_items(
        "SELECT code, gift_card_id, amount, givex FROM applied_gift_cards WHERE order_id = '" . e($order_id) . "'"));
    if ($gift_card_refund_line !== '') {
        log_activity($gift_card_refund_line);
        if ($refund_status_outcome === '') {
            $refund_status_outcome = 'manual_required';
        }
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
        'message'       => lang('Order cancelled.'),
        'refund_status' => $refund_status_outcome,
        'order_id'      => $order_id,
    );
}

// What cancelling an order does to the money its payment took. The order
// screen's cancel window and process_order_cancellation() both decide by it,
// so the window says what the cancellation then does.
// 'remaining' is the money still on the order (total less what was refunded
// already, never below 0), 'refunded' what was refunded, and 'action' one of:
//  'void'   the payment driver cancels the payment: the auto refund is on, a
//           driver can cancel ($cancel_driver, pg_payment_cancel_driver()),
//           the order has a transaction and nothing was refunded yet;
//  'manual' money is left and a payment method took it, but it is not voided:
//           the operator returns it (REFUND ACTION REQUIRED, manual_required);
//  'none'   nothing is left (refunded in full, a free order), or no method
//           took the money (Offline Payment, none).
// A void takes no amount and reverses the whole payment, so a payment partly
// refunded already (the order screen's or a return's refund) is not voided:
// what is left goes to the operator. Amounts are minor units.
function pg_order_cancel_payment_plan($total_cents, $refunded_cents, $payment_method, $has_transaction, $cancel_driver, $auto_refund)
{
    $total_cents    = (int) $total_cents;
    $refunded_cents = max(0, (int) $refunded_cents);
    $remaining      = max(0, $total_cents - $refunded_cents);
    $payment_method = (string) $payment_method;

    if ($remaining <= 0) {
        $action = 'none';
    } elseif ($auto_refund && $has_transaction && (string) $cancel_driver !== '' && $refunded_cents === 0) {
        $action = 'void';
    } elseif ($payment_method !== '' && $payment_method !== 'Offline Payment') {
        $action = 'manual';
    } else {
        $action = 'none';
    }

    return array('action' => $action, 'remaining' => $remaining, 'refunded' => $refunded_cents);
}

// The site log line that tells the operator what a cancelled order took off
// gift cards: each card with its masked code (the shop's own card also with
// its id, a Givex card marked) and the amount, '' when no card paid anything.
// $applied_gift_cards are the order's applied_gift_cards rows.
function pg_order_gift_card_refund_line($order_id, $applied_gift_cards)
{
    $parts = array();
    $total = 0;
    foreach ($applied_gift_cards as $card) {
        $amount = (int) round((float) $card['amount']);
        if ($amount <= 0) {
            continue;
        }
        $total += $amount;
        if (!empty($card['givex'])) {
            $name = protect_givex_gift_card_code((string) $card['code']) . ' (Givex)';
        } else {
            $name = protect_gift_card_code((string) $card['code']) . (!empty($card['gift_card_id']) ? ' (#' . (int) $card['gift_card_id'] . ')' : '');
        }
        $parts[] = $name . ' ' . number_format($amount / 100, 2, '.', ',');
    }
    if (!$parts) {
        return '';
    }
    return lang(array(
        'string' => 'REFUND ACTION REQUIRED — order #{var:1} was cancelled. Gift cards paid {var:2} of it: {var:3}. Return the amounts to the cards.',
        'vars'   => array((int) $order_id, number_format($total / 100, 2, '.', ','), implode(', ', $parts))));
}

// Taking a cancelled order back to $status ('complete' or 'exported'): the
// cancellation's bookkeeping (cancelled_at, cancelled_by, the reason) is
// cleared, a refund flag that did not end in a refund is dropped, and the gift
// cards the cancellation expired get their date back. The order screen's
// Restore and the external API's status change both come here; a plain status
// write kept the cancellation line and the refund banner on a completed order
// and left its gift cards expired. Only a cancelled order moves: the status
// UPDATE carries status = 'cancelled', so of two restores at once one does the
// work. Returns false when the order was not cancelled (or is not there).
// The caller records the audit row and announces the status change.
function pg_order_restore_cancelled($order_id, $status = 'complete')
{
    $order_id = (int) $order_id;
    $status   = ($status === 'exported') ? 'exported' : 'complete';

    // Read before the bookkeeping is cleared: the gift cards step needs the
    // order number and the time of the cancellation.
    $has_cancel_columns = pg_schema_has('orders', 'cancelled_at');
    $cancelled_order = db_item(
        "SELECT order_number" . ($has_cancel_columns ? ', cancelled_at' : '') . "
         FROM orders
         WHERE id = '" . $order_id . "' AND status = 'cancelled'
         LIMIT 1");
    if (!is_array($cancelled_order)) {
        return false;
    }

    db("UPDATE orders SET status = '" . $status . "' WHERE id = '" . $order_id . "' AND status = 'cancelled'");
    if (mysqli_affected_rows(db::$con) < 1) {
        return false;
    }

    // Columns are 2026.1.26 / 2026.1.27 additions, so both groups are probed.
    // A refund that went through stays on the record.
    if ($has_cancel_columns) {
        db("UPDATE orders SET cancelled_at = NULL, cancelled_by = 0, cancellation_reason = ''
            WHERE id = '" . $order_id . "'");
    }
    if (_orders_has_refund_columns()) {
        db("UPDATE orders SET refund_status = '', refunded_at = NULL, refund_reference = ''
            WHERE id = '" . $order_id . "' AND refund_status != 'refunded'");
    }

    pg_order_restore_gift_cards($order_id, (string) $cancelled_order['order_number'], $cancelled_order['cancelled_at'] ?? 0);

    log_activity('Restored cancelled order #' . $order_id . ' to ' . $status . '.');

    return true;
}

// Restoring a cancelled order: the gift cards it issued that the cancellation
// expired get back the date each had, which the cancellation wrote into the
// card's note. Only a card whose expiration is still the day before the
// cancellation is touched: one that had expired on its own, or whose date was
// changed since, stays as it is. Setting every past date to "no expiration"
// used to make expired and dated cards spendable for good. Returns how many
// cards got their date back.
function pg_order_restore_gift_cards($order_id, $order_number, $cancelled_at)
{
    $cancelled_at = (int) $cancelled_at;
    if ($cancelled_at <= 0) {
        return 0;
    }

    $expired_on = date('Y-m-d', strtotime('-1 day', $cancelled_at));
    $restored   = 0;

    $expired_gift_cards = db_items(
        "SELECT id, notes
         FROM gift_cards
         WHERE order_id = '" . e($order_id) . "'
           AND expiration_date = '" . e($expired_on) . "'"
    );

    foreach ((array) $expired_gift_cards as $expired_gift_card) {
        $expiration_before = pg_gift_card_expiration_before_cancel($expired_gift_card['notes'], $order_number);
        if ($expiration_before === null) {
            continue;
        }
        db(
            "UPDATE gift_cards
             SET expiration_date = '" . e($expiration_before) . "'
             WHERE id = '" . (int) $expired_gift_card['id'] . "'"
        );
        $restored++;
    }

    return $restored;
}

// The note templates a cancellation writes on the gift cards it expires
// (process_order_cancellation()), as pairs of 'dated' ({var:1} the order
// number, {var:2} the old date) and 'none' ({var:1} the order number): the
// English source and its wording in the current language and in Turkish, so a
// note written under another panel language is still recognised.
function pg_gift_card_cancel_note_templates()
{
    $dated = 'This gift card was automatically expired because order #{var:1} was cancelled. Its expiration date was {var:2}.';
    $none  = 'This gift card was automatically expired because order #{var:1} was cancelled. It had no expiration date.';

    $templates = array(array('dated' => $dated, 'none' => $none));

    foreach (array_unique(array((string) lang(array('info' => true)), 'tr')) as $language) {
        if ($language === '' || $language === 'en') {
            continue;
        }
        $dated_translated = lang(array('string' => 'This gift card was automatically expired because order #{var:1} was cancelled. Its expiration date was {var:2}.', 'language' => $language, 'if_known' => true));
        $none_translated  = lang(array('string' => 'This gift card was automatically expired because order #{var:1} was cancelled. It had no expiration date.', 'language' => $language, 'if_known' => true));
        if (is_string($dated_translated) && is_string($none_translated)) {
            $templates[] = array('dated' => $dated_translated, 'none' => $none_translated);
        }
    }

    return $templates;
}

// The expiration date a gift card had before the cancellation of order
// $order_number expired it, read from the note line that cancellation added:
// 'Y-m-d', '0000-00-00' when the card had none, null when there is no such
// line or its date is not a real one. A line counts only when it is, after
// trimming, one of the cancellation templates filled with the order number
// (and, for the dated one, the ISO date in the line); any other line, an
// operator's line naming the order included, is skipped. Notes are free
// text, so matching the order number alone let such a line set the date or
// make the card non-expiring. The last matching line wins: an order
// cancelled, restored and cancelled again. $templates defaults to
// pg_gift_card_cancel_note_templates().
function pg_gift_card_expiration_before_cancel($notes, $order_number, $templates = null)
{
    $order_number = trim((string) $order_number);
    if ($order_number === '') {
        return null;
    }

    if ($templates === null) {
        $templates = pg_gift_card_cancel_note_templates();
    }

    $lines = preg_split('/\r\n|\r|\n/', (string) $notes);

    for ($i = count($lines) - 1; $i >= 0; $i--) {
        $line = trim($lines[$i]);
        if ($line === '' || strpos($line, '#' . $order_number) === false) {
            continue;
        }

        preg_match_all('/(?<![0-9])([0-9]{4})-([0-9]{2})-([0-9]{2})(?![0-9])/', $line, $dates, PREG_SET_ORDER);

        foreach ((array) $templates as $template) {
            if (isset($template['none'])
                && $line === trim(str_replace('{var:1}', $order_number, (string) $template['none']))) {
                return '0000-00-00';
            }

            if (!isset($template['dated'])) {
                continue;
            }

            foreach ($dates as $date) {
                $filled = str_replace(array('{var:1}', '{var:2}'), array($order_number, $date[0]), (string) $template['dated']);
                if ($line === trim($filled)) {
                    return checkdate((int) $date[2], (int) $date[3], (int) $date[1]) ? $date[0] : null;
                }
            }
        }
    }

    return null;
}

/**
 * Has this order physically gone out?
 *
 * The operator's workflow is the source of truth here: an order counts as
 * shipped the moment a TRACKING NUMBER is recorded — not when a ship_date is
 * filled in. ship_tos.ship_date is a *planned* dispatch date that gets set at
 * order time on many configurations, so gating on it blocked cancellation of
 * orders that had not left the building yet. It is deliberately ignored.
 *
 * The numbers live in shipping_tracking_numbers, one row per parcel, hanging
 * off the ship_to rather than the order: an order can go to several addresses
 * and an address can take several parcels.
 *
 * orders.tracking_code is NOT consulted, though it was until 2026.4.4. That
 * column is the campaign code the visitor arrived with (get_tracking_code(),
 * the ?t= parameter, stored beside referral_source_code and the utm_* fields)
 * and has nothing to do with a carrier. Reading it here meant every order
 * placed through a campaign link counted as shipped from the second it was
 * placed, and the customer was told "already shipped" when they tried to
 * cancel it.
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

    // Blank rows don't count as shipped.
    $parcels = (int) db_value(
        "SELECT COUNT(*) FROM shipping_tracking_numbers
         WHERE order_id = '" . e($order_id) . "'
           AND TRIM(number) != ''"
    );

    return $parcels > 0;
}

/**
 * Probe orders.refund_status — true only after the 2026.1.27 upgrade has run.
 */
function _orders_has_refund_columns()
{
    return pg_schema_has('orders', 'refund_status');
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
    $amount_str   = $total_amount > 0 ? pg_money_text($total_amount / 100, defined('BASE_CURRENCY_SYMBOL') ? BASE_CURRENCY_SYMBOL : '') : '';
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

/**
 * How many sales invoices are open and past due, for the badge on the ERP
 * menu entry.
 *
 * The dashboard card's own number, so the badge and the card agree. Asked on
 * every panel page, so it is the cheapest possible question: status says
 * whether money is owed (the settlement code keeps it current) and the due
 * date says whether it is late; no sums. Counted once per request.
 *
 * @return int  0 when the ERP is off or the table is not there
 */
function pg_erp_overdue_badge_count()
{
    static $count = null;

    if ($count !== null) {
        return $count;
    }

    $count = 0;

    if (!defined('ERP_ENABLED') || !ERP_ENABLED) {
        return $count;
    }

    $count = (int) db_value("SELECT COUNT(*) FROM erp_invoices
        WHERE doc_type = 'invoice' AND direction = 'sales' AND status IN ('issued', 'partially_paid')
          AND IF(due_date = '0000-00-00', issue_date, due_date) < '" . escape(date('Y-m-d')) . "'");

    return $count;
}

/**
 * The store's country as the ERP reads it (countries.default_selected, the
 * same place the checkout takes it from), for screens that run without the
 * ERP loaded - the settings card. Inside the ERP it is
 * erp_default_country_code().
 *
 * @return string  Two letters, or '' when the store names none
 */
function pg_erp_store_country()
{
    static $code = null;

    if ($code === null) {
        $code = function_exists('erp_default_country_code')
            ? erp_default_country_code()
            : strtoupper(trim((string) db_value("SELECT code FROM countries WHERE default_selected = 1 ORDER BY id ASC LIMIT 1")));
    }

    return $code;
}

/**
 * The ERP account of the member signed in on the storefront: the account
 * linked to the member's contact (erp_accounts.contact_id, the side of the
 * link that is the truth). Read once per request and without loading the
 * ERP, since the storefront asks on pages the ERP has no other business on.
 *
 * @return array|null  id, kind, status; null when the ERP is off, nobody is
 *                     signed in, or the member's contact has no account
 */
function pg_erp_member_account()
{
    static $account = false;

    if ($account !== false) {
        return $account;
    }

    $account = null;
    $contact_id = defined('USER_CONTACT_ID') ? (int) USER_CONTACT_ID : 0;

    if (!defined('ERP_ENABLED') || !ERP_ENABLED || !defined('USER_LOGGED_IN') || (USER_LOGGED_IN !== true) || ($contact_id <= 0)) {
        return $account;
    }

    $row = db_item("SELECT id, kind, status FROM erp_accounts WHERE contact_id = '" . $contact_id . "' ORDER BY id ASC LIMIT 1");

    if (is_array($row)) {
        $account = array('id' => (int) $row['id'], 'kind' => (string) $row['kind'], 'status' => (string) $row['status']);
    }

    return $account;
}

/**
 * The prices the signed-in member's ERP account has agreed, as the store
 * charges them (config.erp_storefront_account_prices, off by default): a
 * product's own price on the account, or the account's discount on the list
 * price (erp_account_price_terms()). Only the products the terms make cheaper
 * than the list price are listed, never a gift card or a donation;
 * get_discounted_product_prices(), update_order_item_prices() and
 * apply_offers_to_cart() weigh them against the campaign with
 * erp_storefront_price(), the lower of the two winning.
 *
 * Empty with the setting off, for anyone not signed in, for a member whose
 * contact has no active customer account, and while staff place an order for
 * someone else (a ghost session: the order is not theirs). Read once per
 * request; the ERP is loaded only for a member who has terms to read.
 *
 * @return array  product_id => ['price' => int, 'list' => int, 'terms' => array]
 */
function pg_erp_member_prices()
{
    static $prices = null;

    if ($prices !== null) {
        return $prices;
    }

    $prices = array();

    if (!defined('ERP_STOREFRONT_ACCOUNT_PRICES') || !ERP_STOREFRONT_ACCOUNT_PRICES || !empty($_SESSION['software']['ghost'])) {
        return $prices;
    }

    $account = pg_erp_member_account();

    if (($account === null) || ($account['status'] !== 'active') || !in_array($account['kind'], array('customer', 'both'), true)) {
        return $prices;
    }

    require_once(PG_FUNCTIONS_DIR . '/includes/erp/bootstrap.php');

    if (!erp_price_lists_ready()) {
        return $prices;
    }

    // An account-wide discount reaches every product; without one only the
    // products the account has a row for.
    $listed = array();
    foreach ((array) db_items("SELECT product_id FROM erp_account_prices WHERE account_id = '" . (int) $account['id'] . "'") as $row) {
        $listed[] = (int) $row['product_id'];
    }

    $account_wide = ((float) db_value("SELECT discount_rate FROM erp_accounts WHERE id = '" . (int) $account['id'] . "' LIMIT 1") > 0);

    if (!$account_wide && !$listed) {
        return $prices;
    }

    // A gift card is money rather than goods: it sells at its face value to
    // everyone. A donation names its own amount.
    $products = (array) db_items("SELECT id, price FROM products
        WHERE enabled = 1 AND price > 0 AND selection_type != 'donation' AND gift_card = 0"
        . ($account_wide ? '' : " AND id IN ('" . implode("', '", $listed) . "')"));

    foreach ($products as $product) {
        $terms = erp_account_price_terms((int) $account['id'], (int) $product['id']);
        $pick = erp_storefront_price((int) $product['price'], $terms);

        if ($pick['source'] === 'account') {
            $prices[(int) $product['id']] = array('price' => (int) $pick['price'], 'list' => (int) $product['price'], 'terms' => $terms);
        }
    }

    return $prices;
}

/**
 * The ERP account the signed-in member may put an order on at checkout
 * (config.erp_open_account_payment, off by default), and where it stands
 * against its credit limit: the member's account (pg_erp_member_account())
 * when it is active, a customer's and has a limit. pending is what the
 * account's orders on account that have no invoice yet come to; the ledger
 * holds an order only once it is invoiced, so the balance alone would let a
 * customer place one order after another, each inside the limit.
 *
 * Null with the setting off, for anyone not signed in, for a member without
 * such an account, and while staff place an order for someone else (a ghost
 * session: the order is not theirs). Read once per request; the ERP is loaded
 * only for a member who has such an account.
 *
 * Checkout posts the choice as payment_method 'On Account'; submit_order.php
 * stores it as an offline payment with orders.erp_on_account = 1.
 *
 * @return array|null  id, limit, balance, pending, available (kurus, base
 *                     currency; available as erp_storefront_on_account() has it)
 */
function pg_erp_open_account()
{
    static $open = false;

    if ($open !== false) {
        return $open;
    }

    $open = null;

    if (!defined('ERP_OPEN_ACCOUNT_PAYMENT') || !ERP_OPEN_ACCOUNT_PAYMENT || !empty($_SESSION['software']['ghost'])) {
        return $open;
    }

    $account = pg_erp_member_account();

    if (($account === null) || ($account['status'] !== 'active') || !in_array($account['kind'], array('customer', 'both'), true)) {
        return $open;
    }

    if (!function_exists('waf_table_has_column') || !waf_table_has_column('orders', 'erp_on_account') || !waf_table_has_column('erp_accounts', 'credit_limit')) {
        return $open;
    }

    $row = db_item("SELECT credit_limit, balance FROM erp_accounts WHERE id = '" . (int) $account['id'] . "' LIMIT 1");

    if (!is_array($row) || ((int) $row['credit_limit'] <= 0)) {
        return $open;
    }

    require_once(PG_FUNCTIONS_DIR . '/includes/erp/bootstrap.php');

    $pending = (int) db_value("SELECT COALESCE(SUM(total), 0) FROM orders
        WHERE erp_on_account = 1
          AND erp_account_id = '" . (int) $account['id'] . "'
          AND erp_invoice_id = 0
          AND status IN ('complete', 'exported')");

    $standing = erp_storefront_on_account((int) $row['credit_limit'], (int) $row['balance'], $pending, 0);

    $open = array(
        'id' => (int) $account['id'],
        'limit' => (int) $row['credit_limit'],
        'balance' => (int) $row['balance'],
        'pending' => $pending,
        'available' => $standing['available'],
    );

    return $open;
}

/**
 * Why an order of this total cannot go on the member's account, or '' when
 * it can (pg_erp_open_account(), erp_storefront_on_account()).
 *
 * @param int $total  The order's total (kurus)
 * @return string
 */
function pg_erp_open_account_refusal($total)
{
    $open = pg_erp_open_account();

    if ($open === null) {
        return lang('The selected payment method is not available.');
    }

    $check = erp_storefront_on_account($open['limit'], $open['balance'], $open['pending'], (int) $total);

    if ($check['allowed']) {
        return '';
    }

    return lang(array(
        'string' => 'This order would take your account past its credit limit; {var:1} of it is available. Please choose another payment method.',
        'vars' => erp_money_out($check['available']),
    ));
}

/**
 * The "Put it on my account" choice as checkout shows it, or null when the
 * member has no account to put an order on (pg_erp_open_account()).
 *
 * @return array|null  value (what the radio posts), label, note (the credit
 *                     left on the account)
 */
function pg_erp_open_account_option()
{
    $open = pg_erp_open_account();

    if ($open === null) {
        return null;
    }

    return array(
        'value' => 'On Account',
        'label' => lang('Put it on my account'),
        'note' => lang(array('string' => 'Available credit: {var:1}', 'vars' => erp_money_out($open['available']))),
    );
}

/**
 * The name of the ERP's tax report as the menu shows it on every panel page,
 * the ERP loaded or not: "VAT report", or the report of the tax name set in
 * the ERP settings. Inside the ERP it is erp_tax_label('report').
 *
 * @return string
 */
function pg_erp_tax_report_label()
{
    if (function_exists('erp_tax_label')) {
        return erp_tax_label('report');
    }

    static $name = null;

    if ($name === null) {
        $name = (function_exists('waf_table_has_column') && waf_table_has_column('config', 'erp_tax_name'))
            ? trim((string) db_value("SELECT erp_tax_name FROM config LIMIT 1"))
            : '';
    }

    return ($name === '') ? lang('VAT report') : lang(array('string' => '{var:1} report', 'vars' => $name));
}

/**
 * How long a carrier's tax number the shipping method holds
 * (shipping_methods.carrier_vkn): 32 from 2026.4.4 (4.70), 11 before it.
 *
 * @return int
 */
function pg_carrier_number_width()
{
    static $width = null;

    if ($width === null) {
        $width = (int) db_value("SELECT CHARACTER_MAXIMUM_LENGTH FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'shipping_methods' AND COLUMN_NAME = 'carrier_vkn' LIMIT 1");
        $width = ($width > 0) ? $width : 11;
    }

    return $width;
}

/**
 * A carrier's tax number as the shipping method stores it. In Turkey it is a
 * VKN or TCKN - digits only, eleven at most - which is what the e-archive
 * invoice carries. Elsewhere it is whatever the carrier prints on its own
 * invoices (a VAT id with its country prefix, an EIN with its dash), so
 * letters, digits and the usual separators are kept, up to the column width.
 *
 * @param string $value
 * @return string
 */
function pg_carrier_number_clean($value)
{
    $value = trim((string) $value);

    if (pg_erp_store_country() === 'TR') {
        return substr(preg_replace('/\D/', '', $value), 0, 11);
    }

    $value = trim((string) preg_replace('/[^\p{L}\p{N} .\/-]/u', '', $value));

    return mb_substr($value, 0, pg_carrier_number_width());
}

/**
 * The carrier number field of the shipping method form: label, input and
 * help text for the store's country.
 *
 * @param string $value  Already escaped for HTML
 * @return string  HTML
 */
function pg_carrier_number_field($value)
{
    if (pg_erp_store_country() === 'TR') {
        return '<label for="carrier_vkn" class="form-label">' . lang('Carrier VKN') . '</label>
            <input value="' . $value . '" type="text" name="carrier_vkn" id="carrier_vkn" class="form-control" maxlength="11" inputmode="numeric" />
            <div class="form-text text-end">' . lang('Tax number of the carrier (10 digits) or ID number (11 digits)') . '</div>';
    }

    return '<label for="carrier_vkn" class="form-label">' . lang('Carrier tax number') . '</label>
        <input value="' . $value . '" type="text" name="carrier_vkn" id="carrier_vkn" class="form-control" maxlength="' . pg_carrier_number_width() . '" />
        <div class="form-text text-end">' . lang('The tax number of the carrier, as it prints it on its own invoices.') . '</div>';
}

/**
 * Whether the settings show the e-invoice card. The providers behind it are
 * Turkish (GİB integrators and Paraşüt), so a store elsewhere sees it only
 * when one of them is already switched on or documents were sent through
 * one - never losing sight of a setting that is in effect.
 *
 * @return bool
 */
function pg_edoc_settings_shown()
{
    static $shown = null;

    if ($shown === null) {
        $shown = (pg_erp_store_country() === 'TR')
            || (defined('ENABLE_PARASUT') && ((int) ENABLE_PARASUT === 1))
            || (defined('ERP_PARASUT_ENABLED') && ((int) ERP_PARASUT_ENABLED === 1))
            || (function_exists('waf_table_has_column') && waf_table_has_column('erp_edoc_providers', 'is_active')
                && ((int) db_value("SELECT COUNT(*) FROM erp_edoc_providers WHERE is_active = 1") > 0))
            || (function_exists('waf_table_has_column') && waf_table_has_column('erp_invoices', 'edoc_status')
                && ((int) db_value("SELECT COUNT(*) FROM erp_invoices WHERE edoc_status <> 'none'") > 0));
    }

    return $shown;
}

/**
 * Whether the ERP menu offers a screen that only an e-document provider can
 * fill: incoming e-invoices ('inbox') or the provider's customer and
 * supplier cards ('accounts'). The ERP itself works without any provider -
 * most stores in the world will never have one - so a link that could only
 * lead to "your provider does not do this" is not drawn at all.
 *
 * The answer comes from the same functions the screens ask, so the menu and
 * the screen never disagree. That needs the e-document registry and the
 * active driver loaded, which is done only while the ERP is on and only
 * once per request.
 *
 * @param string $screen  'inbox' | 'accounts'
 * @return bool
 */
function pg_erp_provider_offers($screen)
{
    static $answers = array();

    $screen = (string) $screen;

    if (isset($answers[$screen])) {
        return $answers[$screen];
    }

    $answers[$screen] = false;

    if (!defined('ERP_ENABLED') || !ERP_ENABLED || !in_array($screen, array('inbox', 'accounts'), true)) {
        return false;
    }

    // The gate every file under includes/erp/ checks; the ERP bootstrap
    // defines it the same way.
    if (!defined('PG_ERP_ENTRY')) {
        define('PG_ERP_ENTRY', true);
    }

    require_once(PG_FUNCTIONS_DIR . '/includes/erp/edoc/registry.php');

    if ($screen === 'inbox') {
        require_once(PG_FUNCTIONS_DIR . '/includes/erp/edoc/inbox.php');
        $answers[$screen] = erp_edoc_inbox_installed() && (erp_edoc_inbox_provider() !== '');
    } else {
        require_once(PG_FUNCTIONS_DIR . '/includes/erp/edoc/account_sync.php');
        $answers[$screen] = erp_edoc_accounts_installed() && (erp_edoc_accounts_provider() !== '');
    }

    return $answers[$screen];
}


/* ------------------------------------------------------------------ *
 * Variant price rules
 * ------------------------------------------------------------------ */

/*
 * A variant set can be priced from its options instead of one row at a time:
 * an option sets the price (pack A = 160.00), multiplies it (pack A = x8 of a
 * per-pair price), or adds an amount or a percentage (white +20 %, leather
 * +50 %). The rules are applied when the set is created and the result goes
 * into every variant's own products.price, which stays the only price the
 * cart, the orders, the API and the marketplace feeds read.
 *
 * One order, whatever order the attributes are shown in:
 *
 *   base   = the amount of the option that sets the price, else the product's price
 *   price  = (base x every multiplier + every amount) x (1 + p1/100) x (1 + p2/100) ...
 *   result = max(0, price rounded to the cent)
 *
 * Percentages compound rather than add up. "Leather is 50 % more than patent"
 * then holds for every combination: white leather is 50 % more than white
 * patent, which a sum (+20 % +50 % = +70 %) would turn into 41.7 %. Amounts go
 * in before the percentages for the same reason, and after the multipliers,
 * so "+20.00 for white" is per pack rather than per pair.
 *
 * Canonical shape, posted by the product screen and kept on the set:
 *
 *   {
 *     "version": 1,
 *     "base": 2000,                       // cents; only in the stored copy
 *     "attributes": [
 *       {"attribute_id": 3, "mode": "set",      "options": [{"option_id": 12, "amount": 16000}]},
 *       {"attribute_id": 4, "mode": "multiply", "options": [{"option_id": 15, "factor": 8}]},
 *       {"attribute_id": 5, "mode": "adjust",   "options": [{"option_id": 20, "percent": 20},
 *                                                           {"option_id": 21, "amount": -500}]}
 *     ]
 *   }
 *
 * The browser does the locale parsing ("1.234,50", "-%10") and sends plain
 * numbers; this side only checks them. pgPriceRuleCompute() in
 * assets/js/product_builder.js is the preview twin of
 * pg_variant_price_compute() and has to stay the same arithmetic, in the same
 * order: the server recomputes every row that was not typed by hand and
 * writes its own result.
 */

/**
 * Is the column that keeps a set's rules there (2026.4.6, 6.1)?
 *
 * The rules still price the variants without it; only keeping them on the set
 * waits for the upgrade. Asked by exact name: in SHOW COLUMNS ... LIKE the
 * underscore is a wildcard.
 *
 * @return bool
 */
function pg_variant_price_rules_ready()
{
    static $ready = NULL;

    if ($ready !== NULL) {
        return $ready;
    }

    $ready = (bool) db_item("SHOW COLUMNS FROM product_groups WHERE Field = 'variant_price_rules'");

    return $ready;
}


/**
 * Check and normalise a rule set.
 *
 * Returns NULL when there is nothing to apply or when the set cannot be
 * applied as a whole: two attributes that both set the price, an attribute
 * listed twice, an unknown mode. The product screen prevents all three, so
 * reaching them means a tampered or stale form; the save then keeps the prices
 * the rows carry rather than guessing which half of the rules was meant.
 *
 * An option without a usable value is dropped (blank means "no effect"), and
 * so is an attribute left without options.
 *
 * @param mixed $rules decoded JSON
 * @return array|NULL canonical rules, without "base"
 */
function pg_variant_price_rules_normalize($rules)
{
    if (!is_array($rules) || empty($rules['attributes']) || !is_array($rules['attributes'])) {
        return NULL;
    }

    $attributes    = array();
    $seen          = array();
    $sets_the_base = 0;

    foreach ($rules['attributes'] as $attribute) {

        if (!is_array($attribute)) {
            return NULL;
        }

        $attribute_id = isset($attribute['attribute_id']) ? (int) $attribute['attribute_id'] : 0;
        $mode         = isset($attribute['mode']) ? (string) $attribute['mode'] : '';

        if (($attribute_id <= 0) || !in_array($mode, array('set', 'multiply', 'adjust'), TRUE)) {
            return NULL;
        }

        if (isset($seen[$attribute_id])) {
            return NULL;
        }

        $seen[$attribute_id] = TRUE;

        $options = array();

        if (!empty($attribute['options']) && is_array($attribute['options'])) {

            foreach ($attribute['options'] as $option) {

                $option_id = (is_array($option) && isset($option['option_id'])) ? (int) $option['option_id'] : 0;

                if ($option_id <= 0) {
                    continue;
                }

                $value = pg_variant_price_rule_value($mode, $option);

                if ($value === NULL) {
                    continue;
                }

                // One value per option; a second entry for the same option
                // replaces the first instead of stacking on it.
                $options[$option_id] = array('option_id' => $option_id) + $value;
            }
        }

        if (!$options) {
            continue;
        }

        if ($mode === 'set') {
            $sets_the_base++;
        }

        $attributes[] = array(
            'attribute_id' => $attribute_id,
            'mode'         => $mode,
            'options'      => array_values($options),
        );
    }

    // Two bases for one price contradict each other.
    if (!$attributes || ($sets_the_base > 1)) {
        return NULL;
    }

    return array(
        'version'    => 1,
        'attributes' => $attributes,
    );
}


/**
 * The one value an option carries for its attribute's mode, checked.
 *
 * @param string $mode   set | multiply | adjust
 * @param array  $option posted option entry
 * @return array|NULL array('amount' => int) | array('factor' => float) |
 *                    array('percent' => float); NULL when unusable
 */
function pg_variant_price_rule_value($mode, $option)
{
    // Prices are INT cents in a signed column; anything past that is not a
    // price, it is a typo.
    $limit = 2000000000;

    if ($mode === 'set') {
        if (!isset($option['amount']) || !is_numeric($option['amount'])) {
            return NULL;
        }
        $amount = (int) round((float) $option['amount']);
        return (($amount >= 0) && ($amount <= $limit)) ? array('amount' => $amount) : NULL;
    }

    if ($mode === 'multiply') {
        if (!isset($option['factor']) || !is_numeric($option['factor'])) {
            return NULL;
        }
        $factor = (float) $option['factor'];
        return (($factor > 0) && ($factor <= 100000)) ? array('factor' => $factor) : NULL;
    }

    // adjust: an amount or a percentage, never both.
    if (isset($option['percent']) && is_numeric($option['percent'])) {
        $percent = (float) $option['percent'];
        return (($percent >= -100) && ($percent <= 100000)) ? array('percent' => $percent) : NULL;
    }

    if (isset($option['amount']) && is_numeric($option['amount'])) {
        $amount = (int) round((float) $option['amount']);
        return (abs($amount) <= $limit) ? array('amount' => $amount) : NULL;
    }

    return NULL;
}


/**
 * Do the rules name only options of their own attributes?
 *
 * The rules are matched against a variant through its attribute/option pairs,
 * so an option filed under the wrong attribute would never match, and an id
 * that is not an option at all would match nothing. Either means the form is
 * not describing this store.
 *
 * @param array $rules canonical rules
 * @return bool
 */
function pg_variant_price_rules_owned($rules)
{
    $expected = array();

    foreach ($rules['attributes'] as $attribute) {
        foreach ($attribute['options'] as $option) {

            $option_id = (int) $option['option_id'];

            // An option belongs to one attribute; listed under two, one of the
            // entries is wrong whichever attribute owns it.
            if (isset($expected[$option_id])) {
                return FALSE;
            }

            $expected[$option_id] = (int) $attribute['attribute_id'];
        }
    }

    if (!$expected) {
        return FALSE;
    }

    $rows = db_items(
        "SELECT id, product_attribute_id
        FROM product_attribute_options
        WHERE id IN (" . implode(',', array_keys($expected)) . ")");

    if (!is_array($rows) || (count($rows) !== count($expected))) {
        return FALSE;
    }

    foreach ($rows as $row) {
        if ($expected[(int) $row['id']] !== (int) $row['product_attribute_id']) {
            return FALSE;
        }
    }

    return TRUE;
}


/**
 * Price one variant from the rules.
 *
 * @param int   $base_cents the product's price, used unless an option sets it
 * @param array $rules      canonical rules
 * @param array $selection  attribute_id => option_id of the variant
 * @return array 'cents' => int, 'clamped' => bool (the rules went below zero)
 */
function pg_variant_price_compute($base_cents, $rules, $selection)
{
    $base     = (float) $base_cents;
    $multiply = 1.0;
    $add      = 0.0;
    $percents = array();

    foreach ($rules['attributes'] as $attribute) {

        $attribute_id = (int) $attribute['attribute_id'];

        if (!isset($selection[$attribute_id])) {
            continue;
        }

        $option_id = (int) $selection[$attribute_id];

        foreach ($attribute['options'] as $option) {

            if ((int) $option['option_id'] !== $option_id) {
                continue;
            }

            if ($attribute['mode'] === 'set') {
                $base = (float) $option['amount'];
            } elseif ($attribute['mode'] === 'multiply') {
                $multiply *= (float) $option['factor'];
            } elseif (isset($option['percent'])) {
                $percents[] = (float) $option['percent'];
            } else {
                $add += (float) $option['amount'];
            }

            break;
        }
    }

    $price = ($base * $multiply) + $add;

    // One factor at a time, in rule order, exactly as the browser does it:
    // multiplying the factors together first gives the same number on paper
    // and a different last bit in floating point.
    foreach ($percents as $percent) {
        $price *= (1 + ($percent / 100));
    }

    // The inner round() strips floating-point noise before the cent is
    // decided (0.1 * 3 is 0.30000000000000004); the browser does the same
    // with toFixed(6).
    $cents = (int) round(round($price, 6));

    if ($cents < 0) {
        return array('cents' => 0, 'clamped' => TRUE);
    }

    return array('cents' => $cents, 'clamped' => FALSE);
}


/**
 * Keep the rules a set was created with, together with the base they were
 * applied to.
 *
 * Nothing reads them back yet. They are kept because they cannot be worked
 * out from the prices afterwards: with the rules and the base, a variant whose
 * price no longer equals what the rules give is one somebody changed by hand.
 *
 * @param int   $group_id
 * @param array $rules      canonical rules
 * @param int   $base_cents
 * @return bool FALSE when the column is not there yet
 */
function pg_variant_price_rules_store($group_id, $rules, $base_cents)
{
    $group_id = (int) $group_id;

    if (!$group_id || !pg_variant_price_rules_ready()) {
        return FALSE;
    }

    $rules = array(
        'version'    => 1,
        'base'       => (int) $base_cents,
        'attributes' => $rules['attributes'],
    );

    // Not through db(): that ends the request on a failed query, and this runs
    // after the variants have been written. Losing the copy of the rules is
    // better than a half-finished save page.
    return (bool) @mysqli_query(db::$con,
        "UPDATE product_groups
        SET variant_price_rules = '" . e(encode_json($rules)) . "'
        WHERE id = '$group_id'");
}


/**
 * The image cell of a product row in a panel list.
 *
 * The thumbnail opens the product's pictures full size in the panel's image
 * viewer (assets/js/pg_lightbox.js, loaded by _pg_lightbox_assets_once()), so
 * an operator can tell similar products apart without opening each one. The
 * viewer builds a gallery from every element that shares a gallery id: the
 * thumbnail is the cover, and the further pictures sit in the same cell as
 * empty hidden elements, in the order the product screen lists them.
 *
 * The thumbnail is a button rather than a clickable picture so that it can be
 * reached with the keyboard. "no-submit" keeps the panel's button handler in
 * backend.js, which swaps a button's label for a loading text, away from it.
 *
 * @param string $key               unique on the screen, e.g. 'product-12'
 * @param string $image_name        the cover (products.image_name), as stored
 * @param string $name              with the short description below, the
 * @param string $short_description caption under the picture in the viewer
 * @param array  $more_images       further file names, see pg_product_more_images()
 * @return string a <td>
 */
function pg_product_image_cell($key, $image_name, $name, $short_description = '', $more_images = array())
{
    $image_name = trim((string) $image_name);

    $caption = trim((string) $name);
    $short_description = trim((string) $short_description);

    if ($short_description !== '') {
        $caption .= (($caption !== '') ? ' - ' : '') . $short_description;
    }

    if ($image_name === '') {
        return '<td class="align-middle text-start"><svg class="bd-placeholder-img img-thumbnail" width="50" height="50" xmlns="http://www.w3.org/2000/svg" role="img" ><rect width="100%" height="100%" fill="#868e96"></rect><text x="10%" y="50%" style="font-size: 8px;" fill="#dee2e6" dy=".3em">' . lang(array('string' => 'No Image')) . '</text></svg></td>';
    }

    $gallery = 'pg-thumb-' . $key;

    $output =
        '<td class="align-middle text-start">'
        . '<button type="button" class="btn p-0 border-0 no-submit" style="cursor: zoom-in;"'
        . ' data-pg-lb-gallery="' . h($gallery) . '" data-pg-lb-index="0"'
        . ' data-pg-lb-src="' . h(PATH . encode_url_path($image_name)) . '"'
        . ' data-pg-lb-alt="' . h($caption) . '" data-pg-lb-caption="' . h($caption) . '"'
        . ' aria-label="' . h(lang('View images')) . '">'
        . '<img style="width: 50px;height:50px;" class="img-fluid img-thumbnail lazy" alt="" src="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/images/loading.gif" data-src="' . h(PATH . encode_url_path($image_name)) . '" />'
        . '</button>';

    $index = 1;

    foreach ((array) $more_images as $more_image) {
        $more_image = trim((string) $more_image);

        if (($more_image === '') || ($more_image === $image_name)) {
            continue;
        }

        $output .=
            '<span hidden data-pg-lb-gallery="' . h($gallery) . '" data-pg-lb-index="' . $index . '"'
            . ' data-pg-lb-src="' . h(PATH . encode_url_path($more_image)) . '"'
            . ' data-pg-lb-alt="' . h($caption) . '" data-pg-lb-caption="' . h($caption) . '"></span>';

        $index++;
    }

    return $output . '</td>';
}


/**
 * The pictures of the given products beyond the cover, for
 * pg_product_image_cell().
 *
 * One query for a whole list. Unordered, the same as the product screen reads
 * them: products_images_xref has no order column, and rows come back in the
 * order they were written.
 *
 * @param array $product_ids
 * @return array product id => list of file names
 */
function pg_product_more_images($product_ids)
{
    $ids = array();

    foreach ((array) $product_ids as $product_id) {
        $product_id = (int) $product_id;

        if ($product_id > 0) {
            $ids[$product_id] = $product_id;
        }
    }

    if (!$ids) {
        return array();
    }

    $images = array();

    foreach (db_items(
        "SELECT product, file_name
        FROM products_images_xref
        WHERE product IN (" . implode(',', $ids) . ") AND file_name != ''") as $row) {

        $images[(int) $row['product']][] = $row['file_name'];
    }

    return $images;
}
