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
