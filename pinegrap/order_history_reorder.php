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

include('init.php');

validate_token_field();

// An id sent as an array counts as not given (it would read as order 1).
$old_order_id = is_scalar($_GET['id'] ?? null) ? (int) $_GET['id'] : 0;

// get order information
$query =
    "SELECT
        user_id,
        status
    FROM orders
    WHERE id = ?";
$result = db_q($query, array((string) $old_order_id)) or output_error(lang('Query failed.'));

// if the order was not found, output error
if (mysqli_num_rows($result) == 0) {
    output_error(lang('The order could not be found.') . ' <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.');
}

$row = mysqli_fetch_assoc($result);
$order_user_id = $row['user_id'];
$status = $row['status'];

// get user information
$query =
    "SELECT
        user_id,
        user_contact
    FROM user
    WHERE user_username = ?";
$result = db_q($query, array((string) $_SESSION['sessionusername'])) or output_error(lang('Query failed.'));
$row = mysqli_fetch_assoc($result);
$user_id = $row['user_id'];
$contact_id = $row['user_contact'];

// if order user id is different than user id, output error
if ($order_user_id != $user_id) {
    output_error(lang('You do not have access to this order.') . ' <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.');
}

// if the order is incomplete, output error
if ($status == 'incomplete') {
    output_error(lang('The order is incomplete. You may only reorder complete orders.') . ' <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.');
}

$reference_code = generate_order_reference_code();

$offline_payment_allowed = '0';

// if offline payment is on, and if only on specific orders is off, then set the allow offline payment to 1
if ((ECOMMERCE_OFFLINE_PAYMENT == TRUE) && (ECOMMERCE_OFFLINE_PAYMENT_ONLY_SPECIFIC_ORDERS == FALSE)) {
    $offline_payment_allowed = '1';
}

$query =
    "INSERT INTO orders (
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
        affiliate_code,
        http_referer,
        ip_address,
        currency_code,
        offline_payment_allowed)
    VALUES (
        UNIX_TIMESTAMP(),
        UNIX_TIMESTAMP(),
        ?,
        ?,
        ?,
        ?,
        ?,
        ?,
        ?,
        ?,
        ?,
        ?,
        ?,
        IFNULL(INET_ATON(?), 0),
        ?,
        ?)";
$result = db_q($query, array(
    (string) $user_id,
    (string) $contact_id,
    (string) $reference_code,
    (string) get_tracking_code(),
    (string) ($_SESSION['software']['utm_source'] ?? ''),
    (string) ($_SESSION['software']['utm_medium'] ?? ''),
    (string) ($_SESSION['software']['utm_campaign'] ?? ''),
    (string) ($_SESSION['software']['utm_term'] ?? ''),
    (string) ($_SESSION['software']['utm_content'] ?? ''),
    (string) get_affiliate_code(),
    (string) ($_SESSION['software']['http_referer'] ?? ''),
    (string) $_SERVER['REMOTE_ADDR'],
    (string) VISITOR_CURRENCY_CODE,
    (string) $offline_payment_allowed)) or output_error(lang('Query failed.'));
$new_order_id = mysqli_insert_id(db::$con);

$order_switched = false;

// if there is already an active order in this user's session, then take note of that
if (($_SESSION['ecommerce']['order_id'] ?? '')) {
    $order_switched = true;
}

// store order id in session
$_SESSION['ecommerce']['order_id'] = $new_order_id;

$ship_tos = array();

// check if there is at least one unshippable order item
$query =
    "SELECT COUNT(*)
    FROM order_items
    LEFT JOIN products ON order_items.product_id = products.id
    WHERE
        (order_items.order_id = ?)
        AND (order_items.ship_to_id = '0')
        AND (products.id IS NOT NULL)
        AND (order_items.added_by_offer = '0')";
$result = db_q($query, array((string) $old_order_id)) or output_error(lang('Query failed.'));
$row = mysqli_fetch_row($result);

// if there is at least one unshippable order item, add empty ship to to ship tos array
if ($row[0] > 0) {
    $ship_tos[] = array('id' => '0');
}

// get all ship tos
$query =
    "SELECT
        id,
        ship_to_name
    FROM ship_tos
    WHERE order_id = ?";
$result = db_q($query, array((string) $old_order_id)) or output_error(lang('Query failed.'));

while($row = mysqli_fetch_assoc($result)) {
    $ship_tos[] = $row;
}

// loop through all ship tos
foreach ($ship_tos as $ship_to) {
    // Get all order items for ship to where the product still exists and is enabled
    // and the order item was not originally added by an offer.
    $query =
        "SELECT
            products.id as product_id,
            products.name as product_name,
            order_items.quantity,
            order_items.price as old_price,
            products.price as new_price,
            products.selection_type
        FROM order_items
        LEFT JOIN products ON order_items.product_id = products.id
        WHERE
            (order_items.order_id = ?)
            AND (order_items.ship_to_id = ?)
            AND (products.id IS NOT NULL)
            AND (products.enabled = '1')
            AND (order_items.added_by_offer = '0')";
    $result = db_q($query, array((string) $old_order_id, (string) $ship_to['id'])) or output_error(lang('Query failed.'));
    
    // add order items to array
    $order_items = array();

    while($row = mysqli_fetch_assoc($result)) {
        $order_items[] = $row;
    }
    
    // if there are order items for this ship to, then continue
    if ($order_items) {
        // if this ship to is a real ship to, then create ship to record for new order
        if ($ship_to['id'] != 0) {
            $query =
                "INSERT INTO ship_tos (
                    order_id,
                    ship_to_name)
                VALUES (
                    ?,
                    ?)";
            $result = db_q($query, array((string) $new_order_id, (string) $ship_to['ship_to_name'])) or output_error(lang('Query failed.'));
            $new_ship_to_id = mysqli_insert_id(db::$con);
            
        // else this ship to is not a real ship to, so set ship to id to 0
        } else {
            $new_ship_to_id = 0;
        }
        
        // loop through order items in order to create order item records for new order
        foreach ($order_items as $order_item) {
            // if order item is a donation, then use donation amount from old order
            if ($order_item['selection_type'] == 'donation') {
                $price = $order_item['old_price'];
                
            // else order item is not a donation, so use new product price
            } else {
                $price = $order_item['new_price'];
            }
            
            $query =
                "INSERT INTO order_items (
                    order_id,
                    ship_to_id,
                    product_id,
                    product_name,
                    quantity,
                    price)
                VALUES (
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?)";
            $result = db_q($query, array(
                (string) $new_order_id,
                (string) $new_ship_to_id,
                (string) $order_item['product_id'],
                (string) $order_item['product_name'],
                (string) $order_item['quantity'],
                (string) $price)) or output_error(lang('Query failed.'));
        }
    }
}

// if visitor tracking is on, update visitor record with order information, if visitor has not already created a previous order
// (a visitor who signed in without a page view first has no visitor record)
if ((VISITOR_TRACKING == true) && !empty($_SESSION['software']['visitor_id'])) {
    $query =
        "UPDATE visitors
        SET
            order_id = ?,
            order_created = '1',
            stop_timestamp = UNIX_TIMESTAMP()
        WHERE
            (id = ?)
            AND (order_created = '0')";
    $result = db_q($query, array((string) $new_order_id, (string) $_SESSION['software']['visitor_id'])) or output_error(lang('Query failed.'));
}

include_once('liveform.class.php');

// if there is a retrieve order next page set, then prepare notice for page
if (ECOMMERCE_RETRIEVE_ORDER_NEXT_PAGE_ID) {
    // get page type for page, in order to determine which liveform to set
    $query = "SELECT page_type FROM page WHERE page_id = ?";
    $result = db_q($query, array((string) ECOMMERCE_RETRIEVE_ORDER_NEXT_PAGE_ID)) or output_error(lang('Query failed.'));
    $row = mysqli_fetch_assoc($result);
    $page_type = $row['page_type'];
    
    switch ($page_type) {
        case 'shopping cart':
            $liveform = new liveform('shopping_cart');
            break;
            
        case 'express order':
            $liveform = new liveform('express_order');
            break;
    }
    
    // if a liveform was set, then prepare notice
    if ($liveform) {
        $order_switched_notice = '';
        
        // if orders were switched then prepare to alert user to that fact
        if ($order_switched == true) {
            $order_switched_notice = ' ' . lang('Your previous order has been saved and may be retrieved when needed.');
        }
        
        // prepare confirmation notice for next screen
        $liveform->add_notice(lang('A new order has been created, and it appears below.') . $order_switched_notice);
    }
    
    // send user to next page
    header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . encode_url_path(get_page_name(ECOMMERCE_RETRIEVE_ORDER_NEXT_PAGE_ID)));
    
// else there is not a retrieve order next page set, so prepare notice for my account screen
} else {
    $liveform = new liveform('my_account');
    
    $order_switched_notice = '';
    
    // if orders were switched then prepare to alert user to that fact
    if ($order_switched == true) {
        $order_switched_notice = ' ' . lang('Your previous order has been saved and may be retrieved when needed.');
    }
    
    // prepare confirmation notice for next screen
    $liveform->add_notice(lang('A new order has been created.') . $order_switched_notice);

    go(get_page_type_url('my account'));
}