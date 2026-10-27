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
$order_id = is_scalar($_GET['id'] ?? null) ? (int) $_GET['id'] : 0;

// get order information
$query =
    "SELECT
        user_id,
        status
    FROM orders
    WHERE id = ?";
$result = db_q($query, array((string) $order_id)) or output_error(lang('Query failed.'));

// if the order was not found, output error
if (mysqli_num_rows($result) == 0) {
    output_error(lang('The order could not be found.') . ' <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.');
}

$row = mysqli_fetch_assoc($result);
$order_user_id = $row['user_id'];
$status = $row['status'];

// get user id for user
$query = "SELECT user_id FROM user WHERE user_username = ?";
$result = db_q($query, array((string) $_SESSION['sessionusername'])) or output_error(lang('Query failed.'));
$row = mysqli_fetch_assoc($result);
$user_id = $row['user_id'];

// if order user id is different than user id, output error
if ($order_user_id != $user_id) {
    output_error(lang('You do not have access to this order.') . ' <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.');
}

// if the order is complete, output error
if ($status != 'incomplete') {
    output_error(lang('The order is complete. You may only delete incomplete orders.') . ' <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.');
}

$order['id'] = $order_id;

require_once(dirname(__FILE__) . '/delete_order.php');

$response = delete_order(array('order' => $order));

if ($response['status'] == 'error') {
    output_error(h($response['message']));
}

// prepare confirmation notice for my account screen
include_once('liveform.class.php');
$my_account = new liveform('my_account');
$my_account->add_notice(lang('The order has been deleted.'));

go(get_page_type_url('my account'));