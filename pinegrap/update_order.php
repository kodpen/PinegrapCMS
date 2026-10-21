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

// Updates an order (currently just supports updating shipping & tracking for completed order).
//
// Tracking numbers and deliveries go through the shipment write path
// (includes/fn/shipping.php): a number already on the address is updated in
// place, a number left out of the list is removed, and a delivery is
// announced once per address. $request['source'] says who is saving
// (pg_shipment_sources(); the order screen and the Workspace are 'panel', the
// default).

function update_order($request) {

    $source = pg_shipment_source(isset($request['source']) ? $request['source'] : 'panel');

    // The addresses whose delivery this save records, and whether their
    // customer is told about it.
    $deliveries = array();

    $order = db_item(
        "SELECT id, billing_email_address, order_number, contact_id FROM orders
        WHERE id = '" . e($request['order']['id']) . "'");

    if (!$order) {
        return error_response('The order could not be found.');
    }

    if (!is_array($request['order']['recipients'])) {
        return error_response('There are no recipients in the request.');
    }

    $order['recipients'] = array();

    foreach ($request['order']['recipients'] as $recipient_request) {

        $recipient = db_item(
            "SELECT id, order_id, ship_to_name, arrival_date, ship_date, delivery_date
            FROM ship_tos WHERE id = '" . e($recipient_request['id']) . "'");

        if (!$recipient) {
            return error_response('The recipient could not be found.');
        }

        if ($recipient['order_id'] != $order['id']) {
            return error_response('The recipient does not belong to that order.');
        }

        // If the request contains a shipped override, then remember that. This allows an email to
        // be sent to customer, even if there is no tracking code (foreign address).  This overrides
        // the manual detection below for new tracking numbers or shipped qty, to determine if the
        // recipient was just shipped, so we know if we should send email or not.
        if (isset($recipient_request['shipped'])) {
            $recipient['shipped'] = $recipient_request['shipped'];
        }

        // If there is a ship date or delivery date in the request, then update recipient.
        if (isset($recipient_request['ship_date']) or isset($recipient_request['delivery_date'])) {

            // A delivery date entered, or changed, to a day that has come is
            // the goods arriving (pg_shipment_delivery_entered()). Read before
            // the write, because afterwards the two are indistinguishable. It
            // is recorded once the tracking numbers below are written, so a
            // number entered in the same save is delivered with the others.
            if (isset($recipient_request['delivery_date'])
                && pg_shipment_delivery_entered(
                    (string) $recipient['delivery_date'],
                    (string) $recipient_request['delivery_date'],
                    date('Y-m-d'))
            ) {
                // The customer is told unless the caller said not to, the same
                // switch that keeps the shipping e-mail back.
                $deliveries[$recipient['id']] = array(
                    'day'    => pg_shipment_date((string) $recipient_request['delivery_date']),
                    'notify' => !(isset($recipient_request['shipped']) && !$recipient_request['shipped']),
                );
            }

            db(
                "UPDATE ship_tos SET
                    ship_date = '" . e($recipient_request['ship_date']) . "',
                    delivery_date = '" . e($recipient_request['delivery_date']) . "'
                WHERE id = '" . e($recipient['id']) . "'");

            $recipient['ship_date'] = $recipient_request['ship_date'];
            $recipient['delivery_date'] = $recipient_request['delivery_date'];
        }

        // If there are tracking numbers in the request, then handle them. The
        // list replaces what the address has: each number is recorded (added,
        // or its row updated in place), then the rows of numbers that are no
        // longer listed are removed.
        if (isset($recipient_request['tracking_numbers'])) {

            $kept_numbers = array();

            foreach ((array) $recipient_request['tracking_numbers'] as $entry) {

                // A number, or array('number' => ..., 'carrier' => ...) when the
                // caller chose the parcel's carrier ('' is "Automatic": the
                // carrier the shipping method names, or none).
                $opts = array('source' => $source);

                if (is_array($entry)) {

                    $number = pg_shipment_number_clean(isset($entry['number']) ? $entry['number'] : '');

                    if (array_key_exists('carrier', $entry)) {
                        $opts['carrier'] = is_scalar($entry['carrier']) ? (string) $entry['carrier'] : '';
                        $opts['carrier_chosen'] = true;
                    }

                } else {
                    $number = pg_shipment_number_clean($entry);
                }

                // If there is no number then skip to next number.
                if ($number === '') {
                    continue;
                }

                $recorded = pg_shipment_record($order['id'], $recipient['id'], $number, $opts);

                if (!$recorded['ok']) {
                    return error_response(lang('The tracking numbers could not be saved.'));
                }

                // If this tracking number did not already exist, then it was just added.
                if ($recorded['created']) {

                    // If we do not already know if this recipient was just shipped, now we know,
                    // so remember for later.
                    if (!isset($recipient['shipped'])) {
                        $recipient['shipped'] = true;
                    }

                    // Kept apart from the flag above, which decides whether the
                    // customer is mailed and can be set by the caller. A number
                    // that was not there a moment ago is the shipment itself,
                    // and an integration is told about it whether or not the
                    // customer is.
                    $recipient['new_tracking_number'] = true;
                }

                $recipient['tracking_numbers'][] = $number;

                $kept_numbers[] = $number;
            }

            // Delete old tracking numbers that were not included in this request.
            pg_shipment_prune($recipient['id'], $kept_numbers);
        }

        // If there are items in the request, then deal with updating shipped qty for them.
        if (is_array($recipient_request['items'])) {
            
            foreach ($recipient_request['items'] as $item_request) {

                $item = db_item(
                    "SELECT id, ship_to_id AS recipient_id, shipped_quantity FROM order_items
                    WHERE id = '" . e($item_request['id']) . "'");

                if (!$item) {
                    return error_response('The item could not be found (id: ' . $item_request['id'] . ').');
                }

                if ($item['recipient_id'] != $recipient['id']) {
                    return error_response('The item (id: ' . $item_request['id'] . ') does not belong to that recipient (id: ' . $recipient['id'] . ').');
                }

                if (!isset($item_request['shipped_quantity'])) {
                    continue;
                }

                $show_shipped_quantity = 0;
                $shipped_quantity = 0;
                
                // If shipped quantity was not blank, then mark it to be shown.
                if ($item_request['shipped_quantity'] != '') {
                    $show_shipped_quantity = 1;
                    $shipped_quantity = $item_request['shipped_quantity'];
                }
            
                db("UPDATE order_items
                    SET
                        show_shipped_quantity = '" . e($show_shipped_quantity) . "',
                        shipped_quantity = '" . e($shipped_quantity) . "'
                    WHERE id = '" . e($item['id']) . "'");

                // If we do not already know if this recipient was just shipped, and the shipped qty
                // is larger than before, then we know it was just shipped, so remember for later.
                if (!isset($recipient['shipped']) and $shipped_quantity > $item['shipped_quantity']) {
                    $recipient['shipped'] = true;
                }
            }
        }

        // If this recipient was just shipped, then remember that order was just shipped, so we
        // know below whether to create auto email campaigns or not for entire order.
        if (!empty($recipient['shipped'])) {
            $order['shipped'] = true;
        }

        // Add this recipient to order array so we can pass data to create_auto_email_campaigns().
        $order['recipients'][] = $recipient;
    }

    db("UPDATE orders SET last_modified_timestamp = UNIX_TIMESTAMP()
        WHERE id = '" . e($order['id']) . "'");

    log_activity('Shipping info for order (' . $order['order_number'] . ') was updated.');

    // The event, once per address that gained a number. Announced here rather
    // than in the screen and the endpoint separately: order.shipped has to mean
    // the same thing whether the shipment was entered by hand or posted by a
    // fulfilment system.
    //
    // Queued, not sent: the cron delivers it, so a slow receiver cannot hold up
    // the save.
    foreach ($order['recipients'] as $shipped_recipient) {

        if (empty($shipped_recipient['new_tracking_number'])) {
            continue;
        }

        require_once(dirname(__FILE__) . '/includes/api/outbound/webhooks.php');

        api_webhook_enqueue('order.shipped', array(
            'id'               => (int) $order['id'],
            'order_number'     => $order['order_number'],
            'ship_to_id'       => (int) $shipped_recipient['id'],
            'tracking_numbers' => isset($shipped_recipient['tracking_numbers'])
                ? array_values($shipped_recipient['tracking_numbers'])
                : array(),
            'ship_date'        => (isset($shipped_recipient['ship_date'])
                && ($shipped_recipient['ship_date'] !== '0000-00-00'))
                ? $shipped_recipient['ship_date']
                : null,
        ));
    }

    // If at least one recipient was just shipped, then check if email needs to be sent to customer.
    if (!empty($order['shipped'])) {

        // Allow mail-merge fields, like ^^order_number^^ to be put in the subject or body of email.

        $fields = array();

        $fields[] = array(
            'name' => 'order_number',
            'data' => $order['order_number']);

        create_auto_email_campaigns(array(
            'action' => 'order_shipped',
            'contact_id' => $order['contact_id'],
            'email_address' => $order['billing_email_address'],
            'fields' => $fields,
            'data' => array('order' => $order)));
    }

    // The deliveries this save recorded, after the shipment so a save that
    // does both announces them in that order. pg_shipment_delivered() marks
    // the address's parcels and announces order.delivered (and starts the
    // order_delivered e-mail) the first time only; 'first' is this save's own
    // guard for an address without a tracking number to keep the record on.
    foreach ($order['recipients'] as $key => $delivered_recipient) {

        if (!isset($deliveries[$delivered_recipient['id']])) {
            continue;
        }

        $delivery = $deliveries[$delivered_recipient['id']];

        $order['recipients'][$key]['just_delivered'] = pg_shipment_delivered(
            $delivered_recipient['id'],
            pg_shipment_day_time($delivery['day'], time()),
            array('notify' => $delivery['notify'], 'first' => true));
    }

    // A store that invoices orders on their own once they ship (ERP settings)
    // gets this one's invoice now: a recipient was just shipped, or gained a
    // tracking number even where the caller keeps the customer's e-mail back.
    $just_shipped = !empty($order['shipped']);

    foreach ($order['recipients'] as $shipped_recipient) {
        if (!empty($shipped_recipient['new_tracking_number'])) {
            $just_shipped = true;
        }
    }

    if ($just_shipped) {
        pg_erp_auto_invoice((int) $order['id'], 'order_shipped');
    }

    return array(
        'status' => 'success',
        'order' => $order);
}