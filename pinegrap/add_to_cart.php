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
 *              2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
	exit;
}

// Used by things like a myself upsell to add a product to the cart.  Only supports adding to myself
// recipient for now.

function add_to_cart($request) {

    // The quantity is optional: the storefront button sends none, which means one.
    // When it is present it has to be a whole number of at least one, otherwise
    // add_order_item() would store the cast value (0 for "-5" or "abc") as a cart line.
    $quantity = 1;

    if (isset($request['quantity']) && ($request['quantity'] !== '')) {

        // A list or an object ([2], {"a": 2}) is not a quantity either.
        if (
            !is_scalar($request['quantity'])
            || (preg_match('/^\d+$/', (string) $request['quantity']) == 0)
            || ((int) $request['quantity'] < 1)
        ) {
            return error_response(lang('Please enter a valid quantity.'));
        }

        $quantity = (int) $request['quantity'];
    }

    // The body is JSON, so any of these can arrive as a list, an object or
    // not at all. A product id that is not one value names no product; a
    // form or notice that is not text is left out, as when none is sent.
    $product_id = is_array($request['product'] ?? null) ? ($request['product']['id'] ?? '') : '';
    $product_id = (is_string($product_id) || is_int($product_id)) ? $product_id : '';
    $form_name = is_string($request['form'] ?? null) ? $request['form'] : '';
    $notice = is_string($request['notice'] ?? null) ? $request['notice'] : '';

    initialize_order();

    $item = array();

    $item['id'] = add_order_item($product_id, $quantity, $donation_amount = 0, 'myself', '');

    $form = '';

    if ($form_name) {
        $form = new liveform($form_name);
    }

    if (!$item['id']) {

        $message = 'Sorry, that product could not be added. The product might no longer exist or might have recently been disabled.';

        if ($form) {
            $form->mark_error('', $message);
        }

        return error_response($message);
    }

    if ($form and $notice) {
        $form->add_notice(h($notice));
    }
    
    // Initialize arrays and counters
    $order_items = array();
    $order_total_raw = 0;   // raw numeric total (without symbol)
    $items_total = '0.00';
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
            WHERE order_items.order_id = ?" . pg_order_item_active_sql();

        // Execute query
        $result = db_q($query, array((string) ($_SESSION['ecommerce']['order_id'] ?? ''))) or output_error('Query failed.');

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
        $items_total_info = pg_format_money($order_total_raw / 100, BASE_CURRENCY_SYMBOL); // with symbol, site separators
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