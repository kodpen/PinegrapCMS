<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Dashboard widget 8 - Orders: the most recent orders.
 *
 * Loaded and called by includes/dashboard/widgets.php; see that file for the
 * contract every widget follows.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_DASHBOARD_WIDGETS')) {
    exit;
}

function pg_dashboard_widget_8($request, $user)
{
    if ((ECOMMERCE == true) && (($user['role'] < 3) || ($user['manage_ecommerce'] == true))) {
        $orders = array();
        $query = "SELECT
                orders.id ,
                orders.order_number,
                orders.status,
                user.user_username as username,
                contacts.first_name,
                contacts.last_name,
                orders.tracking_code,
                orders.total,
                orders.order_date as timestamp
            FROM orders
            LEFT JOIN user ON orders.user_id = user.user_id
            LEFT JOIN contacts ON orders.contact_id = contacts.id
            LEFT JOIN ship_tos ON orders.id = ship_tos.order_id
            WHERE status != 'incomplete'
            ORDER BY orders.order_date DESC
            LIMIT 50";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');

        // loop through the result in order to prepare array of items
        while ($row = mysqli_fetch_assoc($result)) {
            $orders[] = $row;
        }

        $output_order_rows = '';


        if (!empty($orders)) {

            // loop through the orders, in order to output rows
            foreach ($orders as $order) {
                $output_link_url = 'view_order.php?id=' . $order['id'];

                $name = '';

                // if there is a username then use that for the name
                if ($order['username'] != '') {
                    $name = $order['username'];

                    // else there is not a username, so use contact name

                } else {
                    // if there is a first name, then add it to the name
                    if ($order['first_name'] != '') {
                        $name .= $order['first_name'];
                    }

                    // if there is a last name, then add it to the name
                    if ($order['last_name'] != '') {
                        // if the name is not blank, then add space
                        if ($name != '') {
                            $name .= ' ';
                        }

                        $name .= $order['last_name'];
                    }
                }

                // if the name is blank, then set it to placeholder
                if ($name == '') {
                    $name = '[' . lang('Visitor') . ']';
                }

                $id = $order['id'];
                $shipped = false;
                if (ECOMMERCE_SHIPPING == true) {
                    $ship_result = mysqli_query(db::$con, "SELECT id FROM shipping_tracking_numbers WHERE order_id = '" . $id . "' LIMIT 1") or output_error('Query failed.');
                    $shipped = (bool) mysqli_fetch_assoc($ship_result);
                }
                $ship_icon_color = $shipped ? '#10b981' : '#a1a1a1';
                $ship_title = $shipped ? lang('Shipped') : lang('Not shipped yet');

                $order_canceled = ($order['status'] == 'cancelled');
                // Shipped and not-shipped are both ordinary states,
                // so the glyph separates them and the tile stays on
                // the card accent. Cancelled is not ordinary, so it
                // takes the tile.
                $output_order_rows .= pg_widget_row(array(
                    'href'  => $output_link_url,
                    'color' => $order_canceled ? '#dc3545' : '',
                    'badge' => $order_canceled
                        ? '<i class="bi bi-x-circle" title="' . lang('Canceled') . '"></i>'
                        : '<i class="bi ' . ($shipped ? 'bi-truck' : 'bi-box-seam') . '" title="' . $ship_title . '"></i>',
                    'name'  => h($name),
                    'aside' => prepare_amount($order['total'] / 100),
                    'meta'  => h($order['order_number'])
                        . ($order_canceled ? ' &mdash; <span class="text-danger">' . lang('Canceled') . '</span>' : '')
                        . ' &middot; ' . get_relative_time(array('timestamp' => $order['timestamp'])),
                ));
            }
        } else {
            $output_order_rows = pg_widget_empty('bi-cart4', lang(array('string' => 'There is no {var:1} right now.', 'vars' => lang('Order'))));
        }

        $carts = array();
        $query = "SELECT
                orders.id,
                user.user_username as username,
                contacts.first_name,
                contacts.last_name,
                orders.reference_code,
                orders.order_date as timestamp
            FROM orders
            LEFT JOIN user ON orders.user_id = user.user_id
            LEFT JOIN contacts ON orders.contact_id = contacts.id
            WHERE status = 'incomplete'
            ORDER BY orders.order_date DESC
            LIMIT 50";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        // loop through the result in order to prepare array of items
        while ($row = mysqli_fetch_assoc($result)) {
            $carts[] = $row;
        }

        $output_cart_rows = '';
        if (!empty($carts)) {
            // loop through the carts, in order to output rows
            foreach ($carts as $cart) {

                // Cart total: every line's price times its own
                // quantity, summed. Prices are stored in kurus.
                $cart_total = db_value(
                    "SELECT SUM(order_items.price * order_items.quantity)
                    FROM order_items
                    WHERE order_id = '" . (int) $cart['id'] . "'");
                $total = pg_format_money(round((float) $cart_total) / 100, BASE_CURRENCY_SYMBOL);

                $output_link_url = 'view_order.php?id=' . $cart['id'];

                $name = '';

                // if there is a username then use that for the name
                if ($cart['username'] != '') {
                    $name = $cart['username'];

                    // else there is not a username, so use contact name

                } else {
                    // if there is a first name, then add it to the name
                    if ($cart['first_name'] != '') {
                        $name .= $cart['first_name'];
                    }

                    // if there is a last name, then add it to the name
                    if ($cart['last_name'] != '') {
                        // if the name is not blank, then add space
                        if ($name != '') {
                            $name .= ' ';
                        }

                        $name .= $cart['last_name'];
                    }
                }

                // if the name is blank, then set it to placeholder
                if ($name == '') {
                    $name = '[' . lang('Visitor') . ']';
                }


                // Amber tile, not the card accent. Orders and carts
                // sit side by side now, and two lists of identically
                // green rows read as one list split down the middle.
                // The colour is the difference between what sold and
                // what did not, so it carries the distinction.
                $output_cart_rows .= pg_widget_row(array(
                    'href'  => $output_link_url,
                    'color' => '#f59e0b',
                    'badge' => '<i class="bi bi-cart"></i>',
                    'name'  => h($name),
                    'aside' => $total,
                    'meta'  => h($cart['reference_code'])
                        . ' &middot; ' . get_relative_time(array('timestamp' => $cart['timestamp'])),
                ));
            }
        } else {
            $output_cart_rows = pg_widget_empty('bi-basket', lang(array('string' => 'There is no {var:1} right now.', 'vars' => lang('Shopping Cart'))));
        }

        // Orders per day for the last eight -- the figure the
        // activity summary used to carry. Same status filter the
        // list below uses, so the headline and the rows cannot
        // disagree about what counts as an order.
        $output_headline = pg_widget_headline(array(
            'id' => 'w8_spark',
            'rgb' => '5,150,105',
            'unit' => lang('orders today'),
            'series' => pg_activity_daily('orders', 'orders.order_date', " AND (orders.status = 'complete')"),
        ));

        // Orders and carts side by side rather than behind tabs. They
        // are read together - an abandoned cart is only interesting
        // next to what did convert - and a tab hides half the card
        // behind a click that most operators never make. The fixed
        // 240px panes are gone with them: each half now fills the
        // card, so the widget matches every other one on the grid.
        $output_data = '
            <div class="card-body p-0 pg-split">
                <div class="pg-split-half">
                    ' . $output_headline . '
                    <div class="pg-list">' . $output_order_rows . '</div>
                </div>
                <div class="pg-split-half">
                    ' . pg_widget_row_heading(lang('Carts')) . '
                    <div class="pg-list">' . $output_cart_rows . '</div>
                </div>
            </div>';

        //return success json output
        $response = array(
            'status' => 'success',
            'message' => 'Action Success',
            'data' => $output_data,
        );
        return $response;


    } else {
        $response = array(
            'status' => 'error',
            'message' => 'Access denied'
        );
        return $response;
    }
}
