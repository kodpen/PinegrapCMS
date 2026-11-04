<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Booking deposits. A bookable service with a price may ask for part of it
 * in advance: booking_services.deposit (minor units, 0 = none) charged as a
 * one-line order of the product deposit_product_id names, the same order a
 * designed form's fee writes (includes/fn/form_payments.php). The booking
 * points at the order (bookings.order_id); the order carries nothing.
 * bookings.paid_at is written once, when the order itself is paid, from
 * pg_order_complete() and pg_order_mark_paid(), and the booking confirms
 * then. An offline payment ("paid on arrival") completes the order with
 * paid_at 0, so it marks nothing.
 *
 * Everything reads behind pg_booking_deposit_ready(): files land before the
 * 2026.11.0 upgrade (11.0.169) adds the columns.
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
 * Whether this installation stores deposits (2026.11.0, 11.0.169).
 *
 * @return bool
 */
function pg_booking_deposit_ready()
{
    return function_exists('pg_booking_ready') && pg_booking_ready()
        && pg_schema_has('booking_services', 'deposit')
        && pg_schema_has('booking_services', 'deposit_product_id')
        && pg_schema_has('bookings', 'order_id')
        && pg_schema_has('bookings', 'paid_at');
}

/**
 * The deposit boxes of one posted service row, checked against the
 * service's price. Pure. $deposit is the box's minor units
 * (pg_booking_settings_price_cents(): null for a figure too large).
 *
 * A deposit asks for a price above 0 and is at most the price; a deposit
 * names the product its order line is. 0 is no deposit, whatever product
 * is picked.
 *
 * @param int|null $deposit
 * @param mixed    $product_id
 * @param int      $price
 * @return array values => deposit, deposit_product_id; error => '',
 *               'deposit' or 'deposit_product'
 */
function pg_booking_deposit_clean($deposit, $product_id, $price)
{
    $product_id = is_scalar($product_id) ? max(0, (int) $product_id) : 0;
    $values = array(
        'deposit'            => ($deposit === null) ? 0 : max(0, (int) $deposit),
        'deposit_product_id' => $product_id,
    );

    $error = '';

    if (($deposit === null) || (($values['deposit'] > 0) && (($values['deposit'] > (int) $price) || ((int) $price <= 0)))) {
        $error = 'deposit';
    } elseif (($values['deposit'] > 0) && ($product_id <= 0)) {
        $error = 'deposit_product';
    }

    return array('values' => $values, 'error' => $error);
}

/**
 * The deposit a booking of this service owes. Pure.
 *
 * @param array $service booking_services row
 * @return array|null amount, product_id; null when the service asks for none
 */
function pg_booking_deposit_due($service)
{
    $service = is_array($service) ? $service : array();
    $amount = (int) ($service['deposit'] ?? 0);
    $product_id = (int) ($service['deposit_product_id'] ?? 0);
    $price = (int) ($service['price'] ?? 0);

    if (($amount <= 0) || ($product_id <= 0) || ($price <= 0) || ($amount > $price)) {
        return null;
    }

    return array('amount' => $amount, 'product_id' => $product_id);
}

/**
 * A booking's one name as first and last name: the last word is the last
 * name, a single word is the first name. Pure.
 *
 * @param string $name
 * @return array first_name, last_name
 */
function pg_booking_name_split($name)
{
    $name = trim((string) preg_replace('/\s+/u', ' ', (string) $name));
    $cut = strrpos($name, ' ');

    if ($cut === false) {
        return array('first_name' => $name, 'last_name' => '');
    }

    return array('first_name' => substr($name, 0, $cut), 'last_name' => substr($name, $cut + 1));
}

/**
 * The columns of a booking's deposit order and its one line. Pure: the
 * form fee's shape (pg_form_payment_order_columns()), billing from the
 * booking's customer. Offline payment is allowed when the store allows it:
 * the visitor may pay on arrival.
 *
 * @param array  $booking      bookings row
 * @param array  $due          pg_booking_deposit_due()
 * @param string $product_name
 * @param string $currency
 * @param bool   $offline_on
 * @return array|null order, item; null for no deposit
 */
function pg_booking_deposit_order_columns($booking, $due, $product_name, $currency, $offline_on)
{
    if (!is_array($due)) {
        return null;
    }

    $booking = is_array($booking) ? $booking : array();
    $fee = array(
        'ok'         => true,
        'amount'     => (int) ($due['amount'] ?? 0),
        'product_id' => (int) ($due['product_id'] ?? 0),
        'offline'    => 1,
    );
    $contact = pg_booking_name_split($booking['name'] ?? '') + array(
        'user_id'       => (int) ($booking['user_id'] ?? 0),
        'email_address' => (string) ($booking['email'] ?? ''),
        'phone_number'  => (string) ($booking['phone'] ?? ''),
    );

    return pg_form_payment_order_columns($fee, $contact, $product_name, $currency, $offline_on);
}

/**
 * Writes the deposit order of a booking and points the booking at it. A
 * booking that already has an order keeps it, so a retried payment charges
 * the same order; a service that asks for no deposit writes nothing.
 *
 * @param int $booking_id
 * @return int the order's id, 0 when none was written
 */
function pg_booking_deposit_order_write($booking_id)
{
    $booking_id = (int) $booking_id;

    if (($booking_id <= 0) || !pg_booking_deposit_ready()) {
        return 0;
    }

    $booking = db_item("SELECT * FROM bookings WHERE id = '$booking_id' LIMIT 1");

    if (!$booking) {
        return 0;
    }

    if ((int) $booking['order_id'] > 0) {
        return (int) $booking['order_id'];
    }

    $due = pg_booking_deposit_due(db_item("SELECT price, deposit, deposit_product_id FROM booking_services WHERE id = '" . (int) $booking['service_id'] . "' LIMIT 1"));

    if ($due === null) {
        return 0;
    }

    $product_name = (string) db_value("SELECT name FROM products WHERE id = '" . (int) $due['product_id'] . "' LIMIT 1");
    $order_id = pg_form_payment_order_insert(pg_booking_deposit_order_columns(
        $booking, $due, $product_name, BASE_CURRENCY_CODE, ECOMMERCE_OFFLINE_PAYMENT == true));

    if ($order_id <= 0) {
        return 0;
    }

    // Only a booking that still points at no order takes this one.
    db_q("UPDATE bookings SET order_id = ? WHERE id = ? AND order_id = 0", array($order_id, $booking_id)) or output_error('Query failed.');

    return $order_id;
}

/**
 * The statement that marks the booking of a paid deposit order paid and
 * confirms it. Pure. paid_at is written once and only when the order itself
 * is paid (orders.paid_at > 0, as pg_form_payment_mark_paid_query()).
 *
 * A booking still waiting (held, or requested) is confirmed when its hold
 * has not run out (hold_until 0 or not past): its slot is still its own. A
 * hold that ran out before the payment arrived may have lost its time to
 * another booking, so it becomes requested and staff decide. A cancelled,
 * no-show or done booking keeps its status and only records the payment.
 *
 * @param int $order_id
 * @param int $now
 * @return array|null sql, params; null for no order
 */
function pg_booking_deposit_mark_paid_query($order_id, $now)
{
    $order_id = (int) $order_id;

    if ($order_id <= 0) {
        return null;
    }

    $now = max(1, (int) $now);

    return array(
        'sql'    => 'UPDATE bookings INNER JOIN orders ON orders.id = bookings.order_id'
            . ' SET bookings.status = CASE'
            . " WHEN bookings.status IN ('held', 'requested') AND (bookings.hold_until = 0 OR bookings.hold_until >= ?) THEN 'confirmed'"
            . " WHEN bookings.status = 'held' THEN 'requested'"
            . ' ELSE bookings.status END,'
            . ' bookings.hold_until = 0, bookings.paid_at = ?, bookings.updated_at = ?'
            . ' WHERE bookings.order_id = ? AND bookings.paid_at = 0 AND orders.paid_at > 0',
        'params' => array($now, $now, $now, $order_id),
    );
}

/**
 * The event a deposit's payment announces for the booking (pure): a held
 * row was never announced, so it is created now (and confirmed with it); a
 * requested one that became confirmed is confirmed. '' for nothing.
 *
 * @param string $from status before the payment
 * @param string $to   status after it
 * @return string created, confirmed or ''
 */
function pg_booking_deposit_event($from, $to)
{
    if ($from === 'held') {
        return 'created';
    }

    return (($from === 'requested') && ($to === 'confirmed')) ? 'confirmed' : '';
}

/**
 * Marks the booking a paid order paid for, called by pg_order_complete()
 * for every order it completes with a payment and by pg_order_mark_paid()
 * when staff record one. An order no booking points at, or one not yet
 * paid, changes nothing; a repeat changes nothing.
 *
 * A held booking gets its manage link here, as pg_booking_confirm() gives
 * it, and is announced with its mails (pg_booking_created()).
 *
 * @param int      $order_id
 * @param int|null $now
 * @return bool whether a booking was marked by this call
 */
function pg_booking_deposit_mark_paid($order_id, $now = null)
{
    if (!pg_booking_deposit_ready()) {
        return false;
    }

    $now = ($now === null) ? time() : (int) $now;
    $query = pg_booking_deposit_mark_paid_query($order_id, $now);

    if ($query === null) {
        return false;
    }

    $before = db_item("SELECT id, status FROM bookings WHERE order_id = '" . (int) $order_id . "' AND paid_at = 0 LIMIT 1");

    if (!$before) {
        return false;
    }

    db_q($query['sql'], $query['params']) or output_error('Query failed.');

    if (mysqli_affected_rows(db::$con) <= 0) {
        return false;
    }

    $id = (int) $before['id'];
    $after = db_item("SELECT status, end_at FROM bookings WHERE id = '$id' LIMIT 1");
    $status = $after ? (string) $after['status'] : '';
    $event = pg_booking_deposit_event((string) $before['status'], $status);

    if ($event === 'created') {
        $token = pg_booking_manage_token($id, (int) $after['end_at'], pg_booking_key());

        if ($token !== '') {
            db_q("UPDATE bookings SET manage_token = ? WHERE id = ?", array(hash('sha256', $token), $id));
        }

        if (function_exists('pg_booking_created')) {
            pg_booking_created($id, $status);
        }
    } elseif (($event === 'confirmed') && function_exists('pg_booking_changed')) {
        pg_booking_changed($id, 'confirmed');
    }

    return true;
}
