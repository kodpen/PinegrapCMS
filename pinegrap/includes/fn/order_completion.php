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
