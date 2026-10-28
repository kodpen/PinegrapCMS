<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Order completion: what makes a cart an order once its payment is settled.
 *
 * pg_order_complete() is fed by the order's id and the values the caller
 * settled for it, never by the visitor's session or the posted form, so the
 * same completion can run from the visitor's return to the checkout and from
 * a payment service's message after the visitor left. Its first write,
 * UPDATE … WHERE status = 'incomplete', is the once-only lock: of two
 * requests completing one order, exactly one changes the row and carries on;
 * the other gets null and writes nothing more.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/**
 * The orders columns a completion may write besides the status and the
 * order number, which are its own.
 *
 * @return string[]
 */
function pg_order_complete_columns()
{
    return array(
        'payment_method', 'erp_on_account', 'erp_account_id',
        'card_type', 'card_number', 'expiration_month', 'expiration_year', 'cardholder', 'card_verification_number',
        'subtotal', 'discount', 'tax', 'shipping', 'gift_card_discount', 'surcharge', 'total',
        'payment_installment', 'installment_charges', 'commission', 'order_date', 'user_id', 'special_offer_code',
        'last_modified_timestamp', 'transaction_id', 'paid_at', 'authorization_code', 'tracking_code',
        'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'affiliate_code', 'ip_address',
    );
}

/**
 * The completing UPDATE for db_q(): the given columns in their order, then
 * status = 'complete', for the order only while it is still incomplete.
 * Every value is written as quoted text, as the checkout always wrote them
 * (figures are integer minor units the caller settled); ip_address is the
 * visitor's address as text, stored through INET_ATON() (0 when unreadable).
 * Pure.
 *
 * Returns null for no order, a column not in pg_order_complete_columns() or
 * a value that is not a scalar.
 *
 * @param int   $order_id
 * @param array $values column => value
 * @return array|null array('sql' => string, 'params' => array)
 */
function pg_order_complete_query($order_id, array $values)
{
    $order_id = (int) $order_id;

    if ($order_id <= 0) {
        return null;
    }

    $columns = pg_order_complete_columns();
    $set = array();
    $params = array();

    foreach ($values as $column => $value) {
        if (!in_array($column, $columns, true) || (($value !== null) && !is_scalar($value))) {
            return null;
        }

        $set[] = ($column === 'ip_address') ? 'ip_address = IFNULL(INET_ATON(?), 0)' : $column . ' = ?';
        $params[] = (string) $value;
    }

    $set[] = "status = 'complete'";
    $params[] = $order_id;

    return array(
        'sql' => 'UPDATE orders SET ' . implode(', ', $set) . " WHERE id = ? AND status = 'incomplete'",
        'params' => $params,
    );
}

/**
 * Completes an incomplete order: writes the settled values and the status,
 * takes off the lines saved for later, gives the order its number, keeps the
 * legal documents the customer accepted, queues order.created and the
 * customer's text message.
 *
 * Only the request whose UPDATE changed the row goes on; for an order that is
 * not incomplete (already completed by another request, cancelled, gone) it
 * returns null having written nothing. A failed query ends the request, as
 * the checkout always did.
 *
 * @param int   $order_id
 * @param array $values    column => value (pg_order_complete_columns())
 * @param array $documents the legal document types the customer accepted
 *                         (preinfo, contract, kvkk)
 * @return string|int|null the order number, null when this request did not
 *                         complete the order
 */
function pg_order_complete($order_id, array $values, array $documents = array())
{
    $order_id = (int) $order_id;
    $query = pg_order_complete_query($order_id, $values);

    if ($query === null) {
        if ($order_id > 0) {
            error_log('Pinegrap: pg_order_complete() was given a value it cannot write for order ' . $order_id . '.');
        }

        return null;
    }

    db_q($query['sql'], $query['params']) or output_error('Query failed.');

    // The lock: a row that was no longer incomplete was not changed.
    if (mysqli_affected_rows(db::$con) < 1) {
        return null;
    }

    // The placed order holds what was charged: lines saved for later come off
    // it before the receipt and the e-mails read it.
    pg_order_drop_saved_lines($order_id);

    // lock table, so no one can read table, so we can get the order number
    mysqli_query(db::$con, "LOCK TABLES next_order_number WRITE") or output_error('Query failed.');

    $result = mysqli_query(db::$con, "SELECT next_order_number FROM next_order_number") or output_error('Query failed.');

    // if a next order number was found, then use it
    if (mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        $order_number = $row['next_order_number'];

    // else a next order number was not found, so use 1 and create next order number record
    } else {
        mysqli_query(db::$con, "INSERT INTO next_order_number VALUES (1)") or output_error('Query failed.');

        $order_number = 1;
    }

    mysqli_query(db::$con, "UPDATE next_order_number SET next_order_number = next_order_number + 1") or output_error('Query failed.');

    mysqli_query(db::$con, "UNLOCK TABLES") or output_error('Query failed.');

    db_q("UPDATE orders
         SET order_number = ?
         WHERE id = ?", array((string) $order_number, (string) $order_id)) or output_error('Query failed.');

    // Keep the checkout documents with the order as the customer saw them,
    // now that the order has its number. Only reads the order; its totals are
    // final by this point.
    pg_legal_documents_store($order_id, array_values(array_intersect(array('preinfo', 'contract', 'kvkk'), $documents)));

    // Tell whoever is subscribed that an order was placed, once the order
    // number exists, because that is the number every other system knows the
    // order by. Only queued: the cron delivers it, so a slow receiver never
    // holds up the customer.
    require_once(PG_FUNCTIONS_DIR . '/includes/api/outbound/webhooks.php');

    api_webhook_enqueue('order.created', array(
        'id'           => $order_id,
        'order_number' => $order_number
    ));

    // The customer's text message, when the store sends one: only queued, so
    // the provider is never waited on here.
    pg_sms_order_received($order_id);

    return $order_number;
}

/**
 * The addresses a receipt is copied to: the store's order address first,
 * then each product's receipt BCC address in line order, once each, the
 * ones validate_email_address() accepts. Pure.
 *
 * @param string   $store_address     ECOMMERCE_EMAIL_ADDRESS
 * @param string[] $product_addresses products.order_receipt_bcc_email_address of each line
 * @return string[]
 */
function pg_order_receipt_bcc_list($store_address, array $product_addresses)
{
    $addresses = array();

    if ((string) $store_address !== '') {
        $addresses[] = (string) $store_address;
    }

    foreach ($product_addresses as $address) {
        $address = (string) $address;

        if (($address !== '') && !in_array($address, $addresses) && validate_email_address($address)) {
            $addresses[] = $address;
        }
    }

    return $addresses;
}

/**
 * E-mails the receipt of a completed order: one copy to the customer with
 * the BCC addresses, or, for an order that opened an account, the customer's
 * copy on its own (it carries the set password link) and one copy to each
 * BCC address (pg_order_receipt_copies()). The BCC addresses come from the
 * order's lines. Queued, never sent in the request.
 *
 * @param int        $order_id
 * @param string|int $order_number
 * @param array      $receipt        the checkout page's receipt settings:
 *                                   email (1 = send), subject, format
 *                                   (plain_text or html), header, footer, page_id
 * @param string     $customer_email the billing e-mail address
 * @param bool       $new_account    the order opened an account whose link
 *                                   the customer's copy carries
 * @return void
 */
function pg_order_complete_receipt($order_id, $order_number, array $receipt, $customer_email, $new_account)
{
    $order_id = (int) $order_id;

    if (($order_id <= 0) || (($receipt['email'] ?? '') != 1)) {
        return;
    }

    $format = (string) ($receipt['format'] ?? '');
    $header = (string) ($receipt['header'] ?? '');
    $footer = (string) ($receipt['footer'] ?? '');
    $subject = (string) ($receipt['subject'] ?? '');

    $product_addresses = array();
    $result = db_q(
        "SELECT products.order_receipt_bcc_email_address
        FROM order_items
        LEFT JOIN products ON order_items.product_id = products.id
        WHERE order_items.order_id = ?" . pg_order_item_active_sql() . "
        ORDER BY order_items.id ASC", array($order_id)) or output_error('Query failed.');

    while ($row = mysqli_fetch_assoc($result)) {
        $product_addresses[] = (string) ($row['order_receipt_bcc_email_address'] ?? '');
    }

    $bcc_email_addresses = pg_order_receipt_bcc_list(ECOMMERCE_EMAIL_ADDRESS, $product_addresses);

    foreach (pg_order_receipt_copies($customer_email, $bcc_email_addresses, (bool) $new_account) as $receipt_copy) {
        pg_order_receipt_audience($receipt_copy['audience']);

        if ($format == 'plain_text') {
            $body = '';

            // A header starts the body, followed by a blank line.
            if ($header != '') {
                $body .= $header . "\n" . "\n";
            }

            include_once(PG_FUNCTIONS_DIR . '/get_order_receipt_in_plain_text.php');

            $body .= get_order_receipt_in_plain_text($order_id);

            // A footer ends the body, after a blank line.
            if ($footer != '') {
                $body .= "\n" . "\n" . $footer;
            }

        } else {
            require_once(PG_FUNCTIONS_DIR . '/get_page_content.php');

            // The order receipt page's HTML; a page built in the visual editor
            // draws the order with its order view widget, which is told the
            // order here (there is no ?order_id= in an e-mail).
            $body = get_page_content($receipt['page_id'] ?? '', $system_content = '', $extra_system_content = '', $mode = 'preview', $email = true,
                array('order_id' => $order_id));

            // The receipt reads in the language the order was placed in.
            if (function_exists('pg_tr_email')) {
                $body = pg_tr_email($body);
            }
        }

        pg_order_receipt_audience('screen');

        email(array(
            'to' => $receipt_copy['to'],
            'bcc' => $receipt_copy['bcc'],
            'from_name' => ORGANIZATION_NAME,
            'from_email_address' => EMAIL_ADDRESS,
            'subject' => (function_exists('pg_tr_text') ? pg_tr_text($subject) : $subject) . $order_number,
            'format' => $format,
            'body' => $body,
            // The customer's copy may carry the set password link, which
            // must stay out of the failure log and the delivery-failed notice.
            'secret' => (($receipt_copy['audience'] === 'customer') && $new_account),
            // The checkout documents the customer accepted go with the
            // receipt as they were kept with the order.
            'attachments' => pg_legal_documents_attachments($order_id, $order_number),
            'queue' => true));
    }
}

/**
 * The date reached by counting transit days from $start, $start included:
 * $direction -1 walks back (a ship date from the arrival date), 1 walks
 * forward (a delivery date from the ship date). A Sunday or Saturday the
 * shipping method does not carry on, and an excluded date of the method,
 * is not counted. Returns the date on which day $days + 1 is counted. Pure.
 *
 * @param string   $start     Y-m-d
 * @param int      $days      transit days
 * @param int      $direction -1 or 1
 * @param bool     $sunday    transit on Sunday
 * @param bool     $saturday  transit on Saturday
 * @param string[] $excluded  Y-m-d dates the method does not carry on
 * @return string Y-m-d
 */
function pg_order_transit_date($start, $days, $direction, $sunday, $saturday, array $excluded)
{
    $step = ($direction < 0) ? '-1 day' : '+1 day';
    $date = '';
    $count = 0;

    while ($count <= $days) {
        $date = ($date === '') ? (string) $start : date('Y-m-d', strtotime($step, strtotime($date)));

        $day_name = date('l', strtotime($date));

        if (
            (($day_name != 'Sunday') || $sunday)
            && (($day_name != 'Saturday') || $saturday)
            && !in_array($date, $excluded)
        ) {
            $count++;
        }
    }

    return $date;
}

/**
 * A ship date moved by a ship date adjustment (days, negative is earlier),
 * never before the order date. Pure.
 *
 * @param string $ship_date  Y-m-d
 * @param int    $adjustment days
 * @param string $order_date Y-m-d
 * @return string Y-m-d
 */
function pg_order_ship_date_adjusted($ship_date, $adjustment, $order_date)
{
    $adjustment = (int) $adjustment;

    if ($adjustment == 0) {
        return $ship_date;
    }

    $ship_date = date('Y-m-d', strtotime((($adjustment < 0) ? $adjustment : '+' . $adjustment) . ' day', strtotime($ship_date)));

    return ($ship_date < $order_date) ? $order_date : $ship_date;
}

/**
 * Writes each recipient's ship and estimated delivery dates, and, for an
 * order whose customer auto-registration created ('new') or found
 * ('existing') an account, adds the recipients to that account's address
 * book (an existing account only gains a name it does not have, so one
 * customer never overwrites another's book). A recipient whose ship date
 * cannot be worked out keeps its dates and goes on to the next.
 *
 * @param int    $order_id
 * @param int    $order_date        unix time the order was placed
 * @param string $auto_registration '', 'new' or 'existing'
 * @param int    $user_id           the account auto-registration used
 * @return void
 */
function pg_order_complete_ship_dates($order_id, $order_date, $auto_registration, $user_id)
{
    $order_id = (int) $order_id;

    if (!ECOMMERCE_SHIPPING || ($order_id <= 0)) {
        return;
    }

    // The excluded transit dates of every shipping method, read once.
    $excluded_transit_dates = array();

    foreach (db_items("SELECT shipping_method_id, date FROM excluded_transit_dates") as $excluded_transit_date_item) {
        $excluded_transit_dates[$excluded_transit_date_item['shipping_method_id']][] = $excluded_transit_date_item['date'];
    }

    $ship_tos = db_items(
        "SELECT
            ship_tos.id,
            ship_tos.ship_to_name,
            ship_tos.salutation,
            ship_tos.first_name,
            ship_tos.last_name,
            ship_tos.company,
            ship_tos.address_1,
            ship_tos.address_2,
            ship_tos.city,
            ship_tos.state,
            ship_tos.zip_code,
            ship_tos.country,
            ship_tos.address_type,
            ship_tos.phone_number,
            ship_tos.arrival_date,
            shipping_methods.id AS shipping_method_id,
            shipping_methods.base_transit_days,
            shipping_methods.adjust_transit,
            shipping_methods.transit_on_sunday,
            shipping_methods.transit_on_saturday,
            countries.id AS country_id,
            countries.transit_adjustment_days
        FROM ship_tos
        LEFT JOIN shipping_methods ON ship_tos.shipping_method_id = shipping_methods.id
        LEFT JOIN countries ON ship_tos.country = countries.code
        WHERE ship_tos.order_id = '" . $order_id . "'");

    foreach ($ship_tos as $ship_to) {
        // The address book comes before the dates, because the dates below
        // skip to the next recipient when no ship date can be worked out.
        if (
            $auto_registration == 'new'
            or (
                $auto_registration == 'existing'
                and !db(
                    "SELECT id FROM address_book
                    WHERE
                        user = '" . e($user_id) . "'
                        AND ship_to_name = '" . e($ship_to['ship_to_name']) . "'")
            )
        ) {
            db(
                "INSERT INTO address_book (
                    user,
                    ship_to_name,
                    salutation,
                    first_name,
                    last_name,
                    company,
                    address_1,
                    address_2,
                    city,
                    state,
                    zip_code,
                    country,
                    address_type,
                    phone_number)
                VALUES (
                    '" . e($user_id) . "',
                    '" . e($ship_to['ship_to_name']) . "',
                    '" . e($ship_to['salutation']) . "',
                    '" . e($ship_to['first_name']) . "',
                    '" . e($ship_to['last_name']) . "',
                    '" . e($ship_to['company']) . "',
                    '" . e($ship_to['address_1']) . "',
                    '" . e($ship_to['address_2']) . "',
                    '" . e($ship_to['city']) . "',
                    '" . e($ship_to['state']) . "',
                    '" . e($ship_to['zip_code']) . "',
                    '" . e($ship_to['country']) . "',
                    '" . e($ship_to['address_type']) . "',
                    '" . e($ship_to['phone_number']) . "')");
        }

        $method_excluded = $excluded_transit_dates[$ship_to['shipping_method_id']] ?? array();
        $transit_days = (int) $ship_to['base_transit_days'];

        // Transit days to the recipient's country, when the method adds them.
        if (($ship_to['adjust_transit'] == 1) && ($ship_to['country_id'] != '')) {
            $transit_days += (int) $ship_to['transit_adjustment_days'];
        }

        $ship_date = '';

        // Arrival at once: the ship date follows from the longest
        // preparation time of the recipient's products.
        if ($ship_to['arrival_date'] == '0000-00-00') {
            $preparation_time = db(
                "SELECT MAX(products.preparation_time)
                FROM order_items
                LEFT JOIN products ON order_items.product_id = products.id
                WHERE order_items.ship_to_id = '" . e($ship_to['id']) . "'");

            require_once(PG_FUNCTIONS_DIR . '/shipping.php');

            $response = get_ship_date(array(
                'preparation_time' => $preparation_time,
                'shipping_method' => array('id' => $ship_to['shipping_method_id'])));

            if ($response['status'] == 'error') {
                continue;
            }

            $ship_date = $response['ship_date'];

        // A requested arrival date: work back from it by the transit time.
        } else if ($ship_to['shipping_method_id'] != '') {
            $ship_date = pg_order_transit_date($ship_to['arrival_date'], $transit_days, -1, ($ship_to['transit_on_sunday'] == 1), ($ship_to['transit_on_saturday'] == 1), $method_excluded);

            // A ship date adjustment for the zip code prefix and method applies
            // when the requested date is an active arrival date of the store.
            if (
                db_value(
                    "SELECT COUNT(*)
                    FROM arrival_dates
                    WHERE
                        (status = 'enabled')
                        AND (start_date <= CURRENT_DATE())
                        AND (concat(end_date, ' " . ECOMMERCE_END_OF_DAY_TIME . "') > NOW())
                        AND (arrival_date = '" . e($ship_to['arrival_date']) . "')")
                > 0
            ) {
                $adjustment = db_value(
                    "SELECT adjustment
                    FROM ship_date_adjustments
                    WHERE
                        (zip_code_prefix = '" . escape(mb_substr($ship_to['zip_code'], 0, 3)) . "')
                        AND (shipping_method_id = '" . e($ship_to['shipping_method_id']) . "')");

                if ($adjustment) {
                    $ship_date = pg_order_ship_date_adjusted($ship_date, $adjustment, date('Y-m-d', (int) $order_date));
                }
            }
        }

        // Arrival at once with a known method: the delivery date is the ship
        // date plus the transit time. Otherwise it is the requested date.
        if ($ship_to['arrival_date'] == '0000-00-00' and $ship_to['shipping_method_id']) {
            $delivery_date = pg_order_transit_date($ship_date, $transit_days, 1, ($ship_to['transit_on_sunday'] == 1), ($ship_to['transit_on_saturday'] == 1), $method_excluded);
        } else {
            $delivery_date = $ship_to['arrival_date'];
        }

        db_q("UPDATE ship_tos SET ship_date = ?, delivery_date = ? WHERE id = ?",
            array((string) $ship_date, (string) $delivery_date, (int) $ship_to['id']));
    }
}

/**
 * The end date of a membership or a folder access bought now: days x
 * quantity added to the current end when it is still ahead, else to today.
 * An empty end (none yet) counts as past. Pure.
 *
 * @param string $end      Y-m-d or ''
 * @param int    $days     days one unit gives
 * @param int    $quantity units bought
 * @param string $today    Y-m-d
 * @return string Y-m-d
 */
function pg_order_extended_until($end, $days, $quantity, $today)
{
    $from = ((string) $end <= (string) $today) ? $today : $end;

    return date('Y-m-d', strtotime($from) + ($days * $quantity * 86400));
}

/**
 * Whether a folder access purchase writes the user's access row: not over
 * edit rights (2), nor over a private access that never ends. Pure.
 *
 * @param string $rights          aclfolder_rights, '' for no row
 * @param string $expiration_date the row's expiration date, '' for no row
 * @return bool
 */
function pg_order_folder_access_extends($rights, $expiration_date)
{
    return ($rights != 2) && (($rights != 1) || ($expiration_date != '0000-00-00'));
}

/**
 * Takes an order line's quantity off its product's stock, when the product
 * tracks stock and has some, marks it out of stock when this sale emptied it
 * (once: notification and page cache), and announces the change
 * (pg_product_stock_changed(): updated_since stamp, inventory.changed,
 * stock.low on the crossing, the marketplace queue).
 *
 * The first line of a product reads its stock again right before the
 * decrement (the line was read before the payment, and a sale or restock
 * made meanwhile would otherwise be the 'before' the stock.low crossing is
 * decided from); a later line of the same product (another recipient)
 * carries on from what the line above left, kept in $stock_left.
 *
 * @param array  $line       an order line joined to its product: order_item_id,
 *                           product_id, product_name, short_description,
 *                           quantity, inventory, inventory_quantity
 * @param array  $stock_left product id => stock left, carried between lines
 * @param string $actor      user name on the out-of-stock notification
 * @return void
 */
function pg_order_complete_line_stock(array $line, array &$stock_left, $actor)
{
    $product_id = $line['product_id'];

    // A bundle takes its components' stock and keeps the contents it was
    // sold with (includes/fn/bundles.php); its own cached stock follows from
    // the refresh the components' announcements run.
    if (pg_bundle_sale_takes_components($product_id, $line['order_item_id'] ?? 0, pg_bundle_ids_among(array($product_id)))) {
        pg_bundle_take_stock($line['order_item_id'], $product_id, $line['quantity'], 'checkout', $stock_left);
        return;
    }

    $inventory = $line['inventory'];
    $inventory_quantity = $line['inventory_quantity'];

    if (isset($stock_left[$product_id])) {
        $inventory = 1;
        $inventory_quantity = $stock_left[$product_id];
    } else {
        $stock_now = db_item("SELECT inventory, inventory_quantity FROM products WHERE id = '" . (int) $product_id . "' LIMIT 1");

        if ($stock_now) {
            $inventory = $stock_now['inventory'];
            $inventory_quantity = $stock_now['inventory_quantity'];
        }
    }

    if (($inventory != 1) || ($inventory_quantity == 0)) {
        return;
    }

    db_q("UPDATE products SET inventory_quantity = (inventory_quantity - ?) WHERE id = ?",
        array((string) $line['quantity'], (string) $product_id)) or output_error('Query failed.');

    // Out of stock from the stock left after this line, not from the stock
    // read before: the column is unsigned and the session sql_mode is not
    // strict, so a sale through the stock leaves 0. The out_of_stock guard
    // makes the flag and the warning happen once per product.
    db_q(
        "UPDATE products SET
            out_of_stock = '1',
            out_of_stock_timestamp = UNIX_TIMESTAMP()
        WHERE
            (id = ?)
            AND (inventory_quantity <= 0)
            AND (out_of_stock != '1')",
        array((string) $product_id)) or output_error('Query failed.');

    if (mysqli_affected_rows(db::$con) > 0) {
        // The product shows as sold out from now on: every page the page
        // cache keeps is made old once the order is through.
        if (function_exists('pg_page_cache_mark_dirty')) {
            pg_page_cache_mark_dirty();
        }

        create_notification(array('action'=>'out_stock', 'type'=>'warning', 'title'=>$line['product_name'] . ' - ' . $line['short_description'], 'product_id'=>$product_id, 'user'=>$actor));
    }

    // Queued, not sent: a completion never waits on somebody else's server.
    // The stock left is read back, because the decrement happened in the
    // UPDATE.
    $stock_left[$product_id] = pg_product_stock_changed(
        $product_id,
        (int) $inventory_quantity,
        null,
        'checkout');
}

/**
 * For an order line that reserves a calendar event (calendars on,
 * reservations still on for the event): takes the quantity off the event's
 * remaining spots while the event still limits them (a first reservation
 * starts the count from the initial spots), and creates the event's
 * reserved auto e-mail campaigns for the contact.
 *
 * @param array $line       calendar_event_id, recurrence_number, reservations,
 *                          limit_reservations, number_of_initial_spots, quantity
 * @param int   $contact_id
 * @return void
 */
function pg_order_complete_line_reservation(array $line, $contact_id)
{
    $calendar_event_id = $line['calendar_event_id'];
    $recurrence_number = $line['recurrence_number'];
    $quantity = $line['quantity'];

    if ((CALENDARS != TRUE) || ($calendar_event_id == '') || ($line['reservations'] != 1)) {
        return;
    }

    if ($line['limit_reservations'] == 1) {
        $result = db_q(
            "SELECT calendar_event_id
            FROM remaining_reservation_spots
            WHERE
                (calendar_event_id = ?)
                AND (recurrence_number = ?)",
            array((string) $calendar_event_id, (string) $recurrence_number)) or output_error('Query failed.');

        if (mysqli_num_rows($result) > 0) {
            db_q(
                "UPDATE remaining_reservation_spots
                SET number_of_remaining_spots = (number_of_remaining_spots - ?)
                WHERE
                    (calendar_event_id = ?)
                    AND (recurrence_number = ?)",
                array((int) $quantity, (string) $calendar_event_id, (string) $recurrence_number)) or output_error('Query failed.');

        // No record yet: the initial spots less the spots this line reserves.
        } else {
            db_q(
                "INSERT INTO remaining_reservation_spots (
                    calendar_event_id,
                    recurrence_number,
                    number_of_remaining_spots)
                VALUES (
                    ?,
                    ?,
                    ?)",
                array((string) $calendar_event_id, (string) $recurrence_number, (string) ($line['number_of_initial_spots'] - $quantity))) or output_error('Query failed.');
        }
    }

    create_auto_email_campaigns(array(
        'action' => 'calendar_event_reserved',
        'action_item_id' => $calendar_event_id,
        'calendar_event_recurrence_number' => $recurrence_number,
        'contact_id' => $contact_id));
}

/**
 * For a membership renewal line: gives the contact a member id (the order's
 * reference code) when it has none, and moves the membership's expiration
 * date on by the line's days x quantity (pg_order_extended_until()) for
 * every contact with that member id.
 *
 * @param array  $line           membership_renewal (days), quantity
 * @param int    $contact_id
 * @param string $reference_code the order's reference code
 * @return bool true when the line renewed a membership
 */
function pg_order_complete_line_membership(array $line, $contact_id, $reference_code)
{
    if (!$line['membership_renewal']) {
        return false;
    }

    $result = db_q("SELECT member_id, expiration_date FROM contacts WHERE id = ?", array((string) $contact_id)) or output_error('Query failed');
    $row = mysqli_fetch_assoc($result);
    $member_id = $row['member_id'];

    if (!$member_id) {
        $member_id = $reference_code;
        $audit_before = pg_audit_fetch('contacts', (int) $contact_id);
        db_q("UPDATE contacts SET member_id = ? WHERE id = ?", array((string) $member_id, (string) $contact_id)) or output_error('Query failed');
        pg_audit_site_record('update', 'contacts', (int) $contact_id, $audit_before);
    }

    $new_expiration_date = pg_order_extended_until($row['expiration_date'], $line['membership_renewal'], $line['quantity'], date('Y-m-d'));

    $audit_members = pg_audit_fetch_by('contacts', 'member_id', $member_id);
    db_q("UPDATE contacts SET expiration_date = ? WHERE member_id = ?", array((string) $new_expiration_date, (string) $member_id)) or output_error('Query failed');
    pg_audit_site_record_many('update', 'contacts', $audit_members);

    return true;
}

/**
 * For a line that grants private access: writes the user's access row for
 * the product's folder (pg_order_folder_access_extends() decides; a limited
 * access ends days x quantity on, pg_order_extended_until()), and sets the
 * product's send-to page as the user's home page.
 *
 * @param array $line    grant_private_access, private_folder, private_days,
 *                       send_to_page, quantity
 * @param int   $user_id
 * @return void
 */
function pg_order_complete_line_folder_access(array $line, $user_id)
{
    if (!$line['grant_private_access']) {
        return;
    }

    $user_id = (int) $user_id;
    $private_folder = (int) $line['private_folder'];

    $row = db_item(
        "SELECT
            aclfolder_rights AS rights,
            expiration_date
        FROM aclfolder
        WHERE
            (aclfolder_user = '$user_id')
            AND (aclfolder_folder = '$private_folder')");

    // A first purchase finds no access row yet.
    $rights = $row['rights'] ?? '';
    $expiration_date = $row['expiration_date'] ?? '';

    if (pg_order_folder_access_extends($rights, $expiration_date)) {
        // The row is replaced, not updated: there may be none.
        db(
            "DELETE FROM aclfolder
            WHERE
                (aclfolder_user = '$user_id')
                AND (aclfolder_folder = '$private_folder')");

        $new_expiration_date = $line['private_days'] ? pg_order_extended_until($expiration_date, $line['private_days'], $line['quantity'], date('Y-m-d')) : '';

        db(
            "INSERT INTO aclfolder (
                aclfolder_user,
                aclfolder_folder,
                aclfolder_rights,
                expiration_date)
            VALUES (
                '$user_id',
                '$private_folder',
                '1',
                '" . e($new_expiration_date) . "')");
    }

    if ($line['send_to_page']) {
        db_q("UPDATE user SET user_home = ? WHERE user_id = ?", array((string) $line['send_to_page'], (string) $user_id)) or output_error('Query failed');
    }
}

/**
 * How many gift cards an order line issues: one per unit, at most 100 (each
 * unit has its own recipient form, and a larger quantity would mean as many
 * forms). Pure.
 *
 * @param int $quantity the line's quantity
 * @return int
 */
function pg_order_gift_card_count($quantity)
{
    return (int) min(100, max(0, (int) $quantity));
}

/**
 * The day a gift card ends: the validity days counted from its delivery
 * date, or from today when it is sent at once ('0000-00-00'); '' when the
 * store sets no validity days. Pure.
 *
 * @param string $delivery_date Y-m-d or '0000-00-00'
 * @param int    $validity_days ECOMMERCE_GIFT_CARD_VALIDITY_DAYS
 * @param int    $now           the current Unix time
 * @return string Y-m-d or ''
 */
function pg_order_gift_card_expiration($delivery_date, $validity_days, $now)
{
    if (!$validity_days) {
        return '';
    }

    $start = ($delivery_date == '0000-00-00') ? $now : strtotime($delivery_date, $now);

    return date('Y-m-d', strtotime('+' . (int) $validity_days . ' day', $start));
}

/**
 * When a gift card's e-mail goes out: noon of the delivery date the buyer
 * chose, or '' (at once) when none was chosen. Pure.
 *
 * @param string $delivery_date Y-m-d or '0000-00-00'
 * @return string
 */
function pg_order_gift_card_send_time($delivery_date)
{
    return ($delivery_date != '0000-00-00') ? $delivery_date . ' 12:00:00' : '';
}

/**
 * For a gift card line: issues one gift card per unit (pg_order_gift_card_count())
 * worth the line's price, from the recipient details the buyer gave for that
 * unit (order_item_gift_cards), and creates the transactional e-mail campaign
 * that sends the card to its recipient, at once or at noon of the delivery
 * date.
 *
 * @param array  $line            order_item_id, gift_card, price, quantity,
 *                                gift_card_email_subject, _format, _body, _page_id
 * @param int    $order_id
 * @param string $created_user_id user id written as the cards' creator
 * @param string $actor           user name on the activity log
 * @return void
 */
function pg_order_complete_line_gift_cards(array $line, $order_id, $created_user_id, $actor)
{
    if (!$line['gift_card']) {
        return;
    }

    $number_of_gift_cards = pg_order_gift_card_count($line['quantity']);

    // One card and one e-mail per unit, each from the recipient form filled
    // for that unit.
    for ($quantity_number = 1; $quantity_number <= $number_of_gift_cards; $quantity_number++) {
        // Get gift card data from database.
        $order_item_gift_card = db_item(
            "SELECT
                id,
                from_name,
                recipient_email_address,
                message,
                delivery_date
            FROM order_item_gift_cards
            WHERE
                (order_item_id = '" . $line['order_item_id'] . "')
                AND (quantity_number = '" . $quantity_number . "')");

        $code = generate_gift_card_code();

        $expiration_date = pg_order_gift_card_expiration($order_item_gift_card['delivery_date'], ECOMMERCE_GIFT_CARD_VALIDITY_DAYS, time());

        db(
            "INSERT INTO gift_cards (
                code,
                amount,
                balance,
                expiration_date,
                order_id,
                order_item_id,
                quantity_number,
                from_name,
                recipient_email_address,
                message,
                delivery_date,
                created_user_id,
                created_timestamp,
                last_modified_user_id,
                last_modified_timestamp)
            VALUES (
                '" . $code . "',
                '" . $line['price'] . "',
                '" . $line['price'] . "',
                '" . $expiration_date . "',
                '" . (int) $order_id . "',
                '" . $line['order_item_id'] . "',
                '" . $quantity_number . "',
                '" . escape($order_item_gift_card['from_name']) . "',
                '" . escape($order_item_gift_card['recipient_email_address']) . "',
                '" . escape($order_item_gift_card['message']) . "',
                '" . $order_item_gift_card['delivery_date'] . "',
                '" . $created_user_id . "',
                UNIX_TIMESTAMP(),
                '" . $created_user_id . "',
                UNIX_TIMESTAMP())");

        log_activity('gift card (' . output_gift_card_code($code) . ') was created because gift card product was ordered', $actor);

        // Create an array that will store the fields for variables that need to be replaced.
        $fields = array();

        $fields[] = array(
            'name' => 'code',
            'data' => output_gift_card_code($code),
            'type' => '');

        // We remove ".00" (",00") from the end of the value if it exists.
        $gift_card_separators = pg_number_separators();

        $fields[] = array(
            'name' => 'amount',
            'data' => preg_replace('~' . preg_quote($gift_card_separators['decimal'], '~') . '0+$~', '', pg_format_number($line['price'] / 100, 2)),
            'type' => '');

        $fields[] = array(
            'name' => 'from_name',
            'data' => $order_item_gift_card['from_name'],
            'type' => '');

        $fields[] = array(
            'name' => 'recipient_email_address',
            'data' => $order_item_gift_card['recipient_email_address'],
            'type' => '');

        $fields[] = array(
            'name' => 'message',
            'data' => $order_item_gift_card['message'],
            'type' => '');

        $delivery_date = $order_item_gift_card['delivery_date'];

        // If there is no delivery date, then change it from 0000-00-00 to blank,
        // so that the admin can use a conditional structure to output "Immediate".
        // Example: [[^^delivery_date^^||Immediate]] 
        if ($delivery_date == '0000-00-00') {
            $delivery_date = '';
        }

        $fields[] = array(
            'name' => 'delivery_date',
            'data' => $delivery_date,
            'type' => 'date');

        // Replace variables in subject (e.g. ^^code^^).
        $subject = replace_variables(array(
            'content' => $line['gift_card_email_subject'],
            'fields' => $fields,
            'format' => 'plain_text'));

        // If plain text was selected for the format, then store body in variable
        // and clear page id so that we don't store it with the e-mail campaign.
        if ($line['gift_card_email_format'] == 'plain_text') {
            $body = $line['gift_card_email_body'];
            $line['gift_card_email_page_id'] = '';

        // Otherwise HTML was selected for the format, so prepare body for that format.
        } else {
            require_once(PG_FUNCTIONS_DIR . '/get_page_content.php');

            // Get html for page.
            $body = get_page_content($line['gift_card_email_page_id'], $system_content = '', $extra_system_content = '', $mode = 'preview', $email = true);
            
            // Find if there is a base tag in the HTML.
            $base_in_html = preg_match('/<\s*base\s+[^>]*href\s*=\s*["\'](?:http:\/\/|https:\/\/|ftp:\/\/).*?["\']/is', $body);

            // If there is not a base tag in the HTML, add base tag and convert relative links to absolute links.
            if (!$base_in_html) {
                $base = '<head>' . "\n" . '<base href="' . URL_SCHEME . HOSTNAME_SETTING . '/" />';
                $body = preg_replace('/<head>/i', $base, $body);

                // Change relative URLs to absolute URLs for links.
                $body = preg_replace('/(<\s*a\s+[^>]*href\s*=\s*["\'])(?!ftp:\/\/|https:\/\/|mailto:|http:\/\/)(?:\/|\.\.\/|\.\/|)(.*?["\'].*?>)/is', "$1" . URL_SCHEME . HOSTNAME_SETTING . "/$2", $body);

                // Change relative URLs to absolute URLs for images.
                $body = preg_replace('/(<\s*img\s+[^>]*src\s*=\s*["\'])(?!http:\/\/|https:\/\/)(?:\/|\.\.\/|\.\/|)(.*?["\'].*?>)/is', "$1" . URL_SCHEME . HOSTNAME_SETTING . "/$2", $body);

                // Change relative URLs to absolute URLs for CSS background images.
                $body = preg_replace('/(background-image\s*:\s*url\s*\(\s*(?:"|\'|))(?!http:\/\/|https:\/\/)(?:\/|\.\.\/|\.\/|)(.*?(?:"|\'|).*?\))/is', "$1" . URL_SCHEME . HOSTNAME_SETTING . "/$2", $body);

                // Change relative URLs to absolute URLs for HTML background images.
                $body = preg_replace('/(background\s*=\s*["\'])(?!http:\/\/|https:\/\/)(?:\/|\.\.\/|\.\/|)(.*?["\'])/is', "$1" . URL_SCHEME . HOSTNAME_SETTING . "/$2", $body);
            }
            
            // get URL for page
            $page_url = URL_SCHEME . HOSTNAME_SETTING . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_email_campaign.php?r=<reference_code></reference_code>';
            
            $footer =
                '<div class="software_email_footer" style="font-family: arial; font-size: 11px; color: #666666; text-align: center; background-color: #ffffff; padding: 5px; margin-top: 15px">
                    <a href="' . $page_url . '" style="color: #666666">View this email at our site</a><br>
                </div>
                </body>';
            $body = preg_replace('/<\/body>/i', $footer, $body);
        }

        // Wrap long lines (RFC 821).
        $body = wordwrap($body, 900, "\n", 1);

        // Replace variables in body (e.g. ^^code^^).
        $body = replace_variables(array(
            'content' => $body,
            'fields' => $fields,
            'format' => $line['gift_card_email_format']));

        $start_date_and_time = pg_order_gift_card_send_time($order_item_gift_card['delivery_date']);
        
        // Create e-mail campaign.
        db(
            "INSERT INTO email_campaigns (
                type,
                action,
                from_name,
                from_email_address,
                subject,
                format,
                body,
                page_id,
                start_time,
                status,
                purpose,
                created_timestamp,
                last_modified_timestamp)
            VALUES (
                'automatic',
                'gift_card_ordered',
                '" . escape(ORGANIZATION_NAME) . "',
                '" . escape(EMAIL_ADDRESS) . "',
                '" . escape($subject) . "',
                '" . $line['gift_card_email_format'] . "',
                '" . escape($body) . "',
                '" . $line['gift_card_email_page_id'] . "',
                '" . $start_date_and_time . "',
                'ready',
                'transactional',
                UNIX_TIMESTAMP(),
                UNIX_TIMESTAMP())");
        
        $email_campaign_id = mysqli_insert_id(db::$con);

        // Create record for e-mail recipient.
        db(
            "INSERT INTO email_recipients (
                email_campaign_id,
                type,
                email_address)
            VALUES (
                '$email_campaign_id',
                'automatic',
                '" . escape($order_item_gift_card['recipient_email_address']) . "')");
        
        log_activity('auto campaign was created because gift card was ordered (' . output_gift_card_code($code) . ')', $actor);
    }
}

/**
 * The variables an order line's submitted form, comment and e-mailed page
 * can use (^^billing_first_name^^, ^^order_number^^, ^^quantity^^ ...), from
 * the order row. Pure.
 *
 * @param array $order    the order's billing_* columns, custom_field_1,
 *                        custom_field_2, po_number, total (minor units),
 *                        order_number, order_date (Unix time)
 * @param int   $quantity the line's quantity
 * @return array list of array('name', 'data'[, 'type']) for replace_variables()
 */
function pg_order_line_variable_fields(array $order, $quantity)
{
    $fields = array();

    foreach (array(
        'billing_salutation', 'billing_first_name', 'billing_last_name', 'billing_company',
        'billing_address_1', 'billing_address_2', 'billing_city', 'billing_state',
        'billing_zip_code', 'billing_country', 'billing_phone_number', 'billing_fax_number',
        'billing_email_address', 'custom_field_1', 'custom_field_2', 'po_number') as $name) {
        $fields[] = array('name' => $name, 'data' => $order[$name]);
    }

    $fields[] = array('name' => 'total', 'data' => sprintf("%01.2lf", $order['total'] / 100));
    $fields[] = array('name' => 'order_number', 'data' => $order['order_number']);
    $fields[] = array('name' => 'order_date_and_time', 'data' => date('Y-m-d H:i:s', $order['order_date']), 'type' => 'date and time');
    $fields[] = array('name' => 'quantity', 'data' => $quantity);

    $plural = ($quantity != 1);
    $fields[] = array('name' => 'quantity_plural_suffix', 'data' => $plural ? 's' : '');
    $fields[] = array('name' => 'quantity_plural_suffix_es', 'data' => $plural ? 'es' : '');

    return $fields;
}

/**
 * The product form answers of an order line as variables, one list per unit
 * (quantity number); a field with several values (a check box group) holds
 * them joined by commas. Pure.
 *
 * @param array $items form_data rows in id order: quantity_number,
 *                     form_field_id, data, name, type
 * @return array quantity number => list of array('name', 'data', 'type')
 */
function pg_order_product_form_fields(array $items)
{
    $forms = array();

    foreach ($items as $item) {
        $quantity_number = (int) $item['quantity_number'];
        $field_id = $item['form_field_id'];

        if (isset($forms[$quantity_number][$field_id])) {
            $forms[$quantity_number][$field_id]['data'] .= ', ' . $item['data'];
        } else {
            $forms[$quantity_number][$field_id] = array('name' => $item['name'], 'data' => $item['data'], 'type' => $item['type']);
        }
    }

    foreach ($forms as $quantity_number => $form) {
        $forms[$quantity_number] = array_values($form);
    }

    return $forms;
}

/**
 * Reads an order line's product form answers (pg_order_product_form_fields()).
 *
 * @param int $order_item_id
 * @return array quantity number => list of variable fields
 */
function pg_order_line_product_form_fields($order_item_id)
{
    return pg_order_product_form_fields(db_items(
        "SELECT
            quantity_number,
            form_field_id,
            data,
            name,
            type
        FROM form_data
        WHERE
            (order_item_id = '" . (int) $order_item_id . "')
            AND (name != '')
        ORDER BY id ASC"));
}

/**
 * Which unit's product form fills the variables of a unit's submitted form
 * or comment: the unit's own form, else the first unit's; 0 for none. Pure.
 *
 * @param int   $quantity_number
 * @param array $product_forms   pg_order_product_form_fields()
 * @return int
 */
function pg_order_product_form_number($quantity_number, array $product_forms)
{
    if (($quantity_number != 1) && isset($product_forms[$quantity_number])) {
        return (int) $quantity_number;
    }

    return isset($product_forms[1]) ? 1 : 0;
}

/**
 * Replaces the order's variables in a text, then the unit's product form
 * variables when it has a product form.
 *
 * @param string     $content
 * @param array      $variable_fields pg_order_line_variable_fields()
 * @param array|null $product_form    the unit's product form fields, null for none
 * @return string
 */
function pg_order_line_fill($content, array $variable_fields, $product_form)
{
    $content = replace_variables(array(
        'content' => $content,
        'fields' => $variable_fields,
        'format' => 'plain_text'));

    if ($product_form !== null) {
        $content = replace_variables(array(
            'content' => $content,
            'fields' => $product_form,
            'format' => 'plain_text'));
    }

    return $content;
}

/**
 * The form_data type a submitted form value is stored as: a date, date and
 * time or time field keeps its type, a rich text area is html, else
 * standard. Pure.
 *
 * @param string $field_type form_fields.type
 * @param mixed  $wysiwyg    form_fields.wysiwyg
 * @return string
 */
function pg_order_form_data_type($field_type, $wysiwyg)
{
    if (in_array($field_type, array('date', 'date and time', 'time'), true)) {
        return $field_type;
    }

    return (($field_type == 'text area') && ($wysiwyg == 1)) ? 'html' : 'standard';
}

/**
 * For a line whose product updates a submitted form: finds the oldest
 * submitted form of the product's custom form page whose "where" field holds
 * the line's "where" value (variables filled from the order and the unit's
 * product form), and writes the product's update fields into it. A field
 * whose value is ^^quantity^^ adds the line's quantity to a number already
 * there.
 *
 * @param array      $line            product_id, quantity, submit_form_update,
 *                                    submit_form_update_where_field, _where_value,
 *                                    submit_form_custom_form_page_id
 * @param array      $variable_fields pg_order_line_variable_fields()
 * @param array|null $product_form    the unit's product form fields, null for none
 * @param string     $user_id         written as the form's last modifier
 * @param bool       $pretty_urls     whether the form page uses pretty URLs
 * @return int the updated submitted form's id, 0 when none was updated
 */
function pg_order_complete_line_form_update(array $line, array $variable_fields, $product_form, $user_id, $pretty_urls)
{
    if (!$line['submit_form_update'] || !$line['submit_form_update_where_field']) {
        return 0;
    }

    $where_field = $line['submit_form_update_where_field'];
    $where_value = $line['submit_form_update_where_value'];

    // The "where" value may name the order's or the product form's values
    // (a reference code the buyer typed).
    if ($where_value != '') {
        $where_value = pg_order_line_fill($where_value, $variable_fields, $product_form);
    }

    if ($where_field == 'reference_code') {
        $sql_where = "(reference_code = '" . e($where_value) . "')";
    } else {
        $field_value = "(SELECT form_data.data FROM form_data WHERE (form_data.form_id = forms.id) AND (form_data.form_field_id = '" . e($where_field) . "') LIMIT 1)";

        // A blank value also matches a form without the field.
        if ($where_value == '') {
            $sql_where = "(($field_value = '') OR ($field_value IS NULL))";
        } else {
            $sql_where = "($field_value = '" . e($where_value) . "')";
        }
    }

    // The oldest match, so a submitted form inventory is used from its
    // oldest form on.
    $submitted_form_id = db_value(
        "SELECT id
        FROM forms
        WHERE
            (page_id = '" . e($line['submit_form_custom_form_page_id']) . "')
            AND $sql_where
        ORDER BY submitted_timestamp
        LIMIT 1");

    if (!$submitted_form_id) {
        return 0;
    }

    db(
        "UPDATE forms
        SET
            last_modified_user_id = '" . e($user_id) . "',
            last_modified_timestamp = UNIX_TIMESTAMP()
        WHERE id = '$submitted_form_id'");

    $submit_form_fields = db_items(
        "SELECT
            product_submit_form_fields.form_field_id,
            product_submit_form_fields.value,
            form_fields.name,
            form_fields.type,
            form_fields.wysiwyg
        FROM product_submit_form_fields
        LEFT JOIN form_fields ON product_submit_form_fields.form_field_id = form_fields.id
        WHERE
            (form_fields.id IS NOT NULL)
            AND (product_submit_form_fields.product_id = '" . (int) $line['product_id'] . "')
            AND (product_submit_form_fields.action = 'update')
            AND (form_fields.type != 'information')
            AND (form_fields.type != 'file upload')");

    foreach ($submit_form_fields as $submit_form_field) {
        $value = '';

        // ^^quantity^^ counts up: the line's quantity is added to the number
        // already there.
        if ($submit_form_field['value'] == '^^quantity^^') {
            $old_quantity = db_value(
                "SELECT data
                FROM form_data
                WHERE
                    (form_id = '$submitted_form_id')
                    AND (form_field_id = '" . $submit_form_field['form_field_id'] . "')");

            if (is_numeric($old_quantity) == true) {
                $value = $old_quantity + $line['quantity'];
            }
        }

        if ($value == '') {
            $value = pg_order_line_fill($submit_form_field['value'], $variable_fields, $product_form);
        }

        $form_data_type = pg_order_form_data_type($submit_form_field['type'], $submit_form_field['wysiwyg']);

        db(
            "DELETE FROM form_data
            WHERE
                (form_id = '$submitted_form_id')
                AND (form_id != '0')
                AND (form_field_id = '" . $submit_form_field['form_field_id'] . "')");

        db(
            "INSERT INTO form_data (
                form_id,
                form_field_id,
                data,
                name,
                type)
            VALUES (
                '$submitted_form_id',
                '" . $submit_form_field['form_field_id'] . "',
                '" . escape($value) . "',
                '" . escape($submit_form_field['name']) . "',
                '$form_data_type')");
    }

    if ($pretty_urls == true) {
        update_submitted_form_address_name($submitted_form_id);
    }

    return (int) $submitted_form_id;
}

/**
 * The user an order line names as a watcher: order_items.add_watcher holds
 * the user name or e-mail address the add watcher feature stored when the
 * line went into the cart.
 *
 * @param array $line add_watcher
 * @return array id and email_address, empty when none is named or found
 */
function pg_order_line_watcher(array $line)
{
    if ($line['add_watcher'] == '') {
        return array();
    }

    $user = db_item(
        "SELECT
            user_id AS id,
            user_email AS email_address
        FROM user
        WHERE
            (user_username = '" . escape($line['add_watcher']) . "')
            OR (user_email = '" . escape($line['add_watcher']) . "')");

    return is_array($user) ? $user : array();
}

/**
 * Where an order came from, as its row keeps it: the tracking and affiliate
 * codes, the referring address and the IP address (the number INET_ATON()
 * made of it). Read from the order, not the request, so a completion
 * without the visitor writes the same values.
 *
 * @param int $order_id
 * @return array tracking_code, affiliate_code, http_referer, ip_address;
 *               empty when there is no such order
 */
function pg_order_form_origin($order_id)
{
    $origin = db_item(
        "SELECT
            tracking_code,
            affiliate_code,
            http_referer,
            ip_address
        FROM orders
        WHERE id = '" . (int) $order_id . "'");

    return is_array($origin) ? $origin : array();
}

/**
 * The INSERT of a submitted form an order line creates, for db_q(): complete,
 * submitted now, its origin from the order row (pg_order_form_origin()).
 * Pure.
 *
 * @param int    $page_id        the custom form page
 * @param string $user_id        the buyer's user, '' for none
 * @param string $contact_id     the buyer's contact
 * @param string $reference_code the new form's reference code
 * @param array  $origin         pg_order_form_origin()
 * @return array array('sql' => string, 'params' => array)
 */
function pg_order_form_insert_query($page_id, $user_id, $contact_id, $reference_code, array $origin)
{
    return array(
        'sql' => "INSERT INTO forms (
                page_id,
                complete,
                user_id,
                contact_id,
                reference_code,
                tracking_code,
                affiliate_code,
                http_referer,
                ip_address,
                submitted_timestamp,
                last_modified_user_id,
                last_modified_timestamp)
            VALUES (?, '1', ?, ?, ?, ?, ?, ?, ?, UNIX_TIMESTAMP(), ?, UNIX_TIMESTAMP())",
        'params' => array(
            (string) (int) $page_id,
            (string) $user_id,
            (string) $contact_id,
            (string) $reference_code,
            (string) ($origin['tracking_code'] ?? ''),
            (string) ($origin['affiliate_code'] ?? ''),
            (string) ($origin['http_referer'] ?? ''),
            (string) (int) ($origin['ip_address'] ?? 0),
            (string) $user_id,
        ),
    );
}

/**
 * Who gets the submitter e-mail of a form an order line created: the
 * billing address, then the line's watcher when one was found; each gets a
 * copy of their own. Pure.
 *
 * @param string $billing_email_address
 * @param array  $watcher               pg_order_line_watcher()
 * @return string[]
 */
function pg_order_form_submitter_addresses($billing_email_address, array $watcher)
{
    $addresses = array($billing_email_address);

    if (!empty($watcher['id'])) {
        $addresses[] = $watcher['email_address'];
    }

    return $addresses;
}

/**
 * Who gets the administrator e-mail of a form an order line created: the
 * addresses of the chosen options, each once, then the page's to address
 * when it is not among them. Pure.
 *
 * @param string[] $option_addresses in field order
 * @param string   $to               custom_form_pages.administrator_email_to_email_address
 * @return string[]
 */
function pg_order_form_admin_addresses(array $option_addresses, $to)
{
    $addresses = array();

    foreach ($option_addresses as $address) {
        if (!in_array($address, $addresses)) {
            $addresses[] = $address;
        }
    }

    if (($to != '') && !in_array($to, $addresses)) {
        $addresses[] = $to;
    }

    return $addresses;
}

/**
 * For a line whose product creates a submitted form: creates it on the
 * product's custom form page with the product's create fields (variables
 * filled from the order and the unit's product form), then sends the page's
 * submitter e-mail (to the billing address and the watcher) and
 * administrator e-mail, and starts the page's custom_form_submitted
 * campaigns. Its tracking and affiliate codes, referrer and IP address come
 * from the order row, so the visitor's request is not needed.
 *
 * @param array      $line            product_id, submit_form_custom_form_page_id
 * @param array      $order           id, user_id, contact_id, billing_email_address
 * @param array      $variable_fields pg_order_line_variable_fields()
 * @param array|null $product_form    the unit's product form fields, null for none
 * @param array      $watcher         pg_order_line_watcher()
 * @param bool       $pretty_urls     whether the form page uses pretty URLs
 * @return int the created submitted form's id, 0 when the page is not a form page
 */
function pg_order_complete_line_form_create(array $line, array $order, array $variable_fields, $product_form, array $watcher, $pretty_urls)
{
    $page_id = (int) $line['submit_form_custom_form_page_id'];

    $custom_form_page = db_item(
        "SELECT
            page.page_id AS id,
            custom_form_pages.submitter_email,
            custom_form_pages.submitter_email_from_email_address,
            custom_form_pages.submitter_email_subject,
            custom_form_pages.submitter_email_format,
            custom_form_pages.submitter_email_body,
            custom_form_pages.submitter_email_page_id,
            custom_form_pages.administrator_email,
            custom_form_pages.administrator_email_to_email_address,
            custom_form_pages.administrator_email_bcc_email_address,
            custom_form_pages.administrator_email_subject,
            custom_form_pages.administrator_email_format,
            custom_form_pages.administrator_email_body,
            custom_form_pages.administrator_email_page_id
        FROM page
        LEFT JOIN custom_form_pages ON page.page_id = custom_form_pages.page_id
        WHERE
            (page.page_id = '$page_id')
            AND " . pg_form_page_sql('page'));

    if (empty($custom_form_page['id'])) {
        return 0;
    }

    $billing_email_address = $order['billing_email_address'];

    $query = pg_order_form_insert_query($page_id, $order['user_id'], $order['contact_id'], generate_form_reference_code(), pg_order_form_origin($order['id']));
    db_q($query['sql'], $query['params']) or output_error('Query failed.');

    $submitted_form_id = mysqli_insert_id(db::$con);

    $submit_form_fields = db_items(
        "SELECT
            product_submit_form_fields.form_field_id,
            product_submit_form_fields.value,
            form_fields.name,
            form_fields.type,
            form_fields.wysiwyg
        FROM product_submit_form_fields
        LEFT JOIN form_fields ON product_submit_form_fields.form_field_id = form_fields.id
        WHERE
            (form_fields.id IS NOT NULL)
            AND (product_submit_form_fields.product_id = '" . (int) $line['product_id'] . "')
            AND (product_submit_form_fields.action = 'create')
            AND (form_fields.type != 'information')
            AND (form_fields.type != 'file upload')");

    $option_addresses = array();

    foreach ($submit_form_fields as $submit_form_field) {
        $value = pg_order_line_fill($submit_form_field['value'], $variable_fields, $product_form);

        $form_data_type = pg_order_form_data_type($submit_form_field['type'], $submit_form_field['wysiwyg']);

        db(
            "INSERT INTO form_data (
                form_id,
                form_field_id,
                data,
                name,
                type)
            VALUES (
                '$submitted_form_id',
                '" . $submit_form_field['form_field_id'] . "',
                '" . escape($value) . "',
                '" . escape($submit_form_field['name']) . "',
                '$form_data_type')");

        // A chosen option of a pick list, radio button or check box may name
        // an administrator to e-mail.
        if (in_array($submit_form_field['type'], array('pick list', 'radio button', 'check box'), true)) {
            $option_addresses = array_merge($option_addresses, db_values(
                "SELECT email_address
                FROM form_field_options
                WHERE
                    (form_field_id = '" . $submit_form_field['form_field_id'] . "')
                    AND (email_address != '')
                    AND (value = '" . escape($value) . "')"));
        }
    }

    if ($pretty_urls == true) {
        update_submitted_form_address_name($submitted_form_id);
    }

    if ($custom_form_page['submitter_email'] == 1) {
        $submitter_email_format = $custom_form_page['submitter_email_format'];

        $from_email_address = ($custom_form_page['submitter_email_from_email_address'] != '')
            ? $custom_form_page['submitter_email_from_email_address']
            : EMAIL_ADDRESS;

        // A separate e-mail to each address, so they do not see each other's.
        foreach (pg_order_form_submitter_addresses($billing_email_address, $watcher) as $to_email_address) {
            // The signed links (^^email_preferences_url^^, ^^confirm_subscription_url^^)
            // go into the billing address's own copy only; a watcher's copy gets them empty.
            // The order's form has no contact of its own, so there is no confirmation link.
            if ($to_email_address == $billing_email_address) {
                pg_cf_mail_links_context(array(
                    'email_address' => $billing_email_address,
                    'contact_id' => 0,
                    'custom_form_page_id' => $page_id,
                    'form_id' => (int) $submitted_form_id));
            } else {
                pg_cf_mail_links_context(false);
            }

            $mail_link_values = pg_cf_mail_link_values();

            // The link fields go into the template before the submitted values, so a
            // value holding "[[" or "]]" cannot open or close a block around other text.
            $subject = get_variable_submitted_form_data_for_content($page_id, $submitted_form_id, pg_cf_fill_mail_links($custom_form_page['submitter_email_subject'], $mail_link_values, 'plain_text'), false);

            if ($submitter_email_format == 'plain_text') {
                // An empty body becomes the list of the form's fields (pg_cf_email_body_or_default()).
                $body = get_variable_submitted_form_data_for_content($page_id, $submitted_form_id, pg_cf_fill_mail_links(pg_cf_email_body_or_default($page_id, $custom_form_page['submitter_email_body'], true), $mail_link_values, 'plain_text'), false);
            } else {
                require_once(PG_FUNCTIONS_DIR . '/get_page_content.php');

                $body = get_page_content($custom_form_page['submitter_email_page_id'], '', '', 'preview', true, array('form_id' => $submitted_form_id));

                // Bare link fields only: the body already holds the submitted values.
                $body = pg_cf_fill_mail_link_tokens($body, $mail_link_values, 'html');
            }

            email(array(
                'to' => $to_email_address,
                'from_name' => ORGANIZATION_NAME,
                'from_email_address' => $from_email_address,
                'subject' => $subject,
                'format' => $submitter_email_format,
                'body' => $body,
                // A signed link stays out of the failure log and the delivery-failed notice.
                'secret' => pg_cf_mail_links_in($subject . $body, $mail_link_values),
                'queue' => true));

            pg_cf_mail_links_context(false);
        }
    }

    $administrator_email_addresses = pg_order_form_admin_addresses($option_addresses, $custom_form_page['administrator_email_to_email_address']);
    $administrator_email_bcc_email_address = $custom_form_page['administrator_email_bcc_email_address'];

    if (($custom_form_page['administrator_email'] == 1) && (($administrator_email_bcc_email_address != '') || (count($administrator_email_addresses) > 0))) {
        $administrator_email_format = $custom_form_page['administrator_email_format'];

        // The signed links belong to the buyer alone: empty in the administrator's
        // e-mail, filled into the template before the submitted values go in.
        $subject = pg_cf_fill_mail_links($custom_form_page['administrator_email_subject'], array(), 'plain_text');
        $subject = get_variable_submitted_form_data_for_content($page_id, $submitted_form_id, $subject, false);

        if ($administrator_email_format == 'plain_text') {
            // An empty body becomes the list of the form's fields (pg_cf_email_body_or_default()).
            $body = get_variable_submitted_form_data_for_content($page_id, $submitted_form_id, pg_cf_fill_mail_links(pg_cf_email_body_or_default($page_id, $custom_form_page['administrator_email_body']), array(), 'plain_text'), false);
        } else {
            require_once(PG_FUNCTIONS_DIR . '/get_page_content.php');

            // The administrator's e-mail in the site's own language.
            if (function_exists('pg_tr_suspend')) {
                pg_tr_suspend(true);
            }

            $body = get_page_content($custom_form_page['administrator_email_page_id'], '', '', 'preview', true, array('form_id' => $submitted_form_id));

            if (function_exists('pg_tr_suspend')) {
                pg_tr_suspend(false);
            }

            $body = pg_cf_fill_mail_link_tokens($body, array(), 'html');
        }

        // Sent from this site with a reply-to of the buyer: a mail provider
        // using DMARC refuses mail from the buyer's own domain.
        email(array(
            'to' => $administrator_email_addresses,
            'bcc' => $administrator_email_bcc_email_address,
            'from_name' => ORGANIZATION_NAME,
            'from_email_address' => EMAIL_ADDRESS,
            'reply_to' => $billing_email_address,
            'subject' => $subject,
            'format' => $administrator_email_format,
            'body' => $body,
            'queue' => true));
    }

    create_auto_email_campaigns(array(
        'action' => 'custom_form_submitted',
        'action_item_id' => $page_id,
        'contact_id' => $order['contact_id']));

    return (int) $submitted_form_id;
}

/**
 * Makes the line's watcher a watcher of the unit's submitted form on the
 * product's add comment page, when that page is a form item view with
 * comments and a watcher e-mail, and the user is not one already.
 *
 * @param array $line              add_comment_page_id
 * @param array $watcher           pg_order_line_watcher()
 * @param int   $submitted_form_id the unit's created or updated form, 0 for none
 * @return void
 */
function pg_order_complete_line_watcher(array $line, array $watcher, $submitted_form_id)
{
    if (empty($watcher['id']) || !$line['add_comment_page_id']) {
        return;
    }

    $page_id = db_value(
        "SELECT page_id
        FROM page
        WHERE
            (page_id = '" . (int) $line['add_comment_page_id'] . "')
            AND (page_type = 'form item view')
            AND (comments = '1')
            AND (comments_watcher_email_page_id != '0')");

    if (!$page_id) {
        return;
    }

    $user_id = (int) $watcher['id'];
    $submitted_form_id = (int) $submitted_form_id;

    if (db_value(
        "SELECT COUNT(*)
        FROM watchers
        WHERE
            (user_id = '$user_id')
            AND (page_id = '$page_id')
            AND (item_id = '$submitted_form_id')
            AND (item_type = 'submitted_form')") == 0
    ) {
        db(
            "INSERT INTO watchers (
                user_id,
                page_id,
                item_id,
                item_type)
            VALUES (
                '$user_id',
                '$page_id',
                '$submitted_form_id',
                'submitted_form')");
    }
}
