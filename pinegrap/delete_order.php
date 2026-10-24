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

function delete_order($request) {

    $order = db_item(
        "SELECT id, order_number, reference_code FROM orders
        WHERE id = '" . e($request['order']['id']) . "'");

    if (!$order) {
        return error_response(lang('The order could not be found.'));
    }

    $audit_before = pg_audit_fetch('orders', $order['id']);

    db("DELETE FROM orders WHERE id = '" . e($order['id']) . "'");

    pg_audit_record('delete', 'orders', (int) $order['id'], $audit_before);
    db("DELETE FROM order_items WHERE order_id = '" . e($order['id']) . "'");
    db("DELETE FROM ship_tos WHERE order_id = '" . e($order['id']) . "'");
    db("DELETE FROM order_item_gift_cards WHERE order_id = '" . e($order['id']) . "'");
    db("DELETE FROM applied_gift_cards WHERE order_id = '" . e($order['id']) . "'");
    db("DELETE FROM form_data WHERE (order_id = '" . e($order['id']) . "') AND (order_id != '0')");
    db("DELETE FROM shipping_tracking_numbers WHERE order_id = '" . e($order['id']) . "'");

    // The legal documents the customer accepted are copies that hold the
    // buyer's name, address, phone, e-mail, IP and browser. Left behind they
    // are personal data nothing can reach any more, and an order that later
    // gets the same id would find them "already stored" and show them as its
    // own (pg_legal_documents_store()).
    if (pg_schema_has('order_documents')) {
        db("DELETE FROM order_documents WHERE order_id = '" . e($order['id']) . "'");
    }

    // The order's return requests and their lines name its order lines by id.
    pg_returns_delete_for_order($order['id']);

    // Cancel the order abandoned and review request auto campaigns of this
    // order that have not been sent yet: both would reach the customer about
    // an order that is gone.
    db(
        "UPDATE email_campaigns
        SET
            email_campaigns.status = 'cancelled',
            email_campaigns.last_modified_user_id = '" . (defined('USER_ID') ? (int) USER_ID : 0) . "',
            email_campaigns.last_modified_timestamp = UNIX_TIMESTAMP()
        WHERE
            (email_campaigns.action IN ('order_abandoned', 'order_review_request'))
            AND
            (
                (email_campaigns.status = 'ready')
                OR (email_campaigns.status = 'paused')
            )
            AND (email_campaigns.order_id = '" . e($order['id']) . "')");

    // If the order that is being deleted is the active order then remove active order from session
    if ($order['id'] == ($_SESSION['ecommerce']['order_id'] ?? '')) {
        unset($_SESSION['ecommerce']['order_id']);
    }

    if ($order['order_number']) {
        $log_code = $order['order_number'];
    } else {
        $log_code = $order['reference_code'];
    }

    log_activity(lang(array('string'=>'Order ({var:1}) was deleted.','vars'=>$log_code)) );

    return array(
        'status' => 'success',
        'order' => $order);
}