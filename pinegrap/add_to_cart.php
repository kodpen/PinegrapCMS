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

// Used by things like a myself upsell to add a product to the cart.  Only supports adding to myself
// recipient for now.

function add_to_cart($request) {

    $quantity = $request['quantity'];

    if (!$quantity) {
        $quantity = 1;
    }

    initialize_order();

    $item = array();

    $item['id'] = add_order_item($request['product']['id'], $quantity, $donation_amount = 0, 'myself', '');

    $form = '';

    if ($request['form']) {
        $form = new liveform($request['form']);
    }

    if (!$item['id']) {

        $message = 'Sorry, that product could not be added. The product might no longer exist or might have recently been disabled.';

        if ($form) {
            $form->mark_error('', $message);
        }

        return error_response($message);
    }

    if ($form and $request['notice']) {
        $form->add_notice($request['notice']);
    }
    
    // Initialize arrays and counters
    $order_items = array();
    $order_total_raw = 0;   // raw numeric total (without symbol)
    $items_total_info = '';
    $items_quantity = 0;    // total quantity of items in cart
    
    if (isset($_SESSION['ecommerce']['order_id']) && ($_SESSION['ecommerce']['order_id'] ?? '') != '') {

        // Query to get all order items with product details
        $query =
            "SELECT
                order_items.id,
                order_items.product_id,
                order_items.quantity,
                order_items.price,
                products.name,
                products.required_product,
                products.recurring,
                products.recurring_schedule_editable_by_customer,
                products.start,
                products.number_of_payments,
                products.payment_period,
                products.form,
                products.form_quantity_type,
                products.gift_card,
                products.submit_form,
                products.submit_form_custom_form_page_id,
                products.submit_form_update,
                products.submit_form_update_where_field,
                products.submit_form_update_where_value,
                products.submit_form_quantity_type
            FROM order_items
            LEFT JOIN products ON order_items.product_id = products.id
            WHERE order_items.order_id = '" . ($_SESSION['ecommerce']['order_id'] ?? '') . "'";

        // Execute query
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');

        // Loop through all items
        while ($row = mysqli_fetch_assoc($result)) {
            $order_items[] = $row;

            // Calculate raw total (price * quantity)
            $order_total_raw += ($row['price'] * $row['quantity']);

            // Add quantity to total items count
            $items_quantity += $row['quantity'];
        }

        // Format totals
        $items_total = number_format($order_total_raw / 100, 2, '.', ','); // without symbol
        $items_total_info = BASE_CURRENCY_SYMBOL . $items_total;           // with symbol
    }

    // Return response array
    return array(
        'status' => 'success',
        'item' => $item,
        'items' => $order_items,
        'items_total' => $items_total,           // numeric string without symbol
        'items_total_info' => $items_total_info, // formatted string with symbol
        'items_quantity' => $items_quantity      // total quantity of items in cart
    );
}