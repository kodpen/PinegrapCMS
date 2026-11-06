<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The deposit payment of the booking widget. A service that asks for a
 * deposit (pg_booking_deposit_due()) does not book at the details step: the
 * details are written on the held row, the hold is kept for the payment,
 * the deposit order is written (pg_booking_deposit_order_write()) and the
 * widget shows the payment as a designed form's payment step does
 * (includes/fn/form_payment_step.php): the choices, the card driver's box,
 * a redirect driver's page coming back to booking_slots.php, and a refused
 * payment retried on the same order.
 *
 * A paid order confirms the booking from its completion
 * (pg_booking_deposit_mark_paid()); a payment that arrives after the hold
 * ran out makes it requested. "Paid on arrival" (offline payment) completes
 * the order unpaid and the booking becomes requested.
 *
 * The session keeps the open payment in software.booking_pay: booking_id,
 * order_id, page (the widget's page to come back to), driver, state, error.
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
 * The fragment the widget's page is opened with after a payment, so the
 * widget asks for the payment's state (the page itself is the same for
 * everyone and may come from the page cache).
 */
const PG_BOOKING_PAY_FRAGMENT = 'pg-booking-payment';

/**
 * A booking's deposit as the screens show it. Pure.
 *
 * @param array $row bookings row with order_id, paid_at and, when read,
 *                   order_number
 * @return array state ('none', 'unpaid' or 'paid'), paid_at, order_id,
 *               order_number ('' when the order has none yet)
 */
function pg_booking_deposit_view($row)
{
    $row = is_array($row) ? $row : array();
    $order_id = (int) ($row['order_id'] ?? 0);
    $paid_at = (int) ($row['paid_at'] ?? 0);
    $number = (string) ($row['order_number'] ?? '');

    return array(
        'state'        => ($order_id <= 0) ? 'none' : (($paid_at > 0) ? 'paid' : 'unpaid'),
        'paid_at'      => $paid_at,
        'order_id'     => $order_id,
        'order_number' => (($number !== '') && ($number !== '0')) ? $number : '',
    );
}

/**
 * The words of a deposit's state: "Deposit paid", "Deposit not paid", ''
 * for a booking without a deposit order.
 *
 * @param array $view pg_booking_deposit_view()
 * @return string
 */
function pg_booking_deposit_label($view)
{
    $state = (string) ($view['state'] ?? 'none');

    if ($state === 'paid') {
        return lang('Deposit paid');
    }

    return ($state === 'unpaid') ? lang('Deposit not paid') : '';
}

/**
 * The agenda's small mark of a booking's deposit, after its status badge:
 * a coin, green when paid. '' for a booking without a deposit order.
 *
 * @param array $row bookings row (order_id, paid_at)
 * @return string HTML
 */
function pg_booking_deposit_badge($row)
{
    $view = pg_booking_deposit_view($row);

    if ($view['state'] === 'none') {
        return '';
    }

    $label = pg_booking_deposit_label($view);

    return ' <span class="badge badge-subtle ' . (($view['state'] === 'paid') ? 'theme-success' : 'theme-warning') . '" title="' . h($label) . '">'
         . '<i class="bi bi-coin" aria-hidden="true"></i><span class="visually-hidden">' . h($label) . '</span></span>';
}

/**
 * Adds order_number to booking rows that point at a deposit order, with one
 * query. Rows without the deposit columns are returned as they are.
 *
 * @param array $rows bookings rows
 * @return array
 */
function pg_booking_deposit_rows($rows)
{
    $rows = is_array($rows) ? $rows : array();
    $ids = array();

    foreach ($rows as $row) {
        if (is_array($row) && ((int) ($row['order_id'] ?? 0) > 0)) {
            $ids[(int) $row['order_id']] = true;
        }
    }

    $numbers = array();

    if ($ids) {
        foreach ((array) db_items("SELECT id, order_number FROM orders WHERE id IN (" . implode(',', array_keys($ids)) . ")") as $order) {
            $numbers[(int) $order['id']] = (string) $order['order_number'];
        }
    }

    foreach ($rows as $index => $row) {
        if (is_array($row) && array_key_exists('order_id', $row)) {
            $rows[$index]['order_number'] = $numbers[(int) $row['order_id']] ?? '';
        }
    }

    return $rows;
}

/**
 * The deposit a widget booking of this service owes, or null: the store is
 * on, the columns are there and the service asks for one.
 *
 * @param array $service booking_services row
 * @return array|null pg_booking_deposit_due()
 */
function pg_booking_widget_deposit($service)
{
    if (!defined('ECOMMERCE') || (ECOMMERCE != true) || !pg_booking_deposit_ready()) {
        return null;
    }

    return pg_booking_deposit_due($service);
}

/**
 * The page path the widget came from, kept for the return after a payment
 * page: a path of this site only. Pure apart from PATH.
 *
 * @param mixed $page
 * @return string
 */
function pg_booking_pay_page($page)
{
    $page = is_string($page) ? $page : '';

    return pg_safe_redirect_path((strpos($page, '/') === 0) ? $page : '', PATH);
}

/* ---------------------------------------------------------------------------
   The session's open payment
   --------------------------------------------------------------------------- */

function pg_booking_pay_pending()
{
    $pending = $_SESSION['software']['booking_pay'] ?? null;

    return (is_array($pending) && ((int) ($pending['order_id'] ?? 0) > 0) && ((int) ($pending['booking_id'] ?? 0) > 0)) ? $pending : null;
}

function pg_booking_pay_pending_set(array $pending)
{
    $_SESSION['software']['booking_pay'] = $pending;
}

function pg_booking_pay_pending_forget()
{
    unset($_SESSION['software']['booking_pay']);
}

/**
 * Keeps a held booking's time for the payment: a live hold, or one that ran
 * out while the day still offers its time, holds again for the policy's
 * minutes. False when the row is no longer a hold or its time is gone.
 *
 * It runs under the staff member's lock, as pg_booking_insert() does: a hold
 * that ran out counts as free there, so without the lock another visitor's
 * hold on an overlapping time could be written between the check and the
 * write, and both would stand. The write must change the row: a hold removed
 * or cancelled meanwhile (pg_booking_clear_expired_hold()) is not kept.
 *
 * @param int $booking_id
 * @param int $now
 * @return bool
 */
function pg_booking_pay_keep_hold($booking_id, $now)
{
    $staff_id = (int) db_value("SELECT staff_id FROM bookings WHERE id = '" . (int) $booking_id . "' LIMIT 1");

    if ($staff_id <= 0) {
        return false;
    }

    $lock = pg_booking_lock_name($staff_id);

    if ((int) db_value("SELECT GET_LOCK('" . e($lock) . "', 10)") !== 1) {
        return false;
    }

    $row = db_item("SELECT id, service_id, staff_id, start_at, status, hold_until FROM bookings WHERE id = '" . (int) $booking_id . "' LIMIT 1");
    $policy = pg_booking_widget_policy();
    $kept = false;

    if ($row && ($row['status'] === 'held')
        && (((int) $row['hold_until'] >= (int) $now)
            || pg_booking_widget_still_offered((int) $row['service_id'], (int) $row['staff_id'], (int) $row['start_at'], $now, $policy))) {
        $until = (int) $now + (max(1, (int) $policy['hold_minutes']) * 60);
        $result = db_q("UPDATE bookings SET hold_until = ?, updated_at = ? WHERE id = ? AND status = 'held'",
            array($until, (int) $now, (int) $booking_id));
        // A repeat in the same second changes no value, so MySQL counts no
        // changed row: the row is read back then.
        $kept = $result && ((mysqli_affected_rows(db::$con) === 1)
            || ((int) db_value("SELECT COUNT(*) FROM bookings WHERE id = '" . (int) $booking_id . "' AND status = 'held' AND hold_until = '" . $until . "'") === 1));
    }

    db_value("SELECT RELEASE_LOCK('" . e($lock) . "')");

    return $kept;
}

/**
 * The details step of a service with a deposit: the visitor's details go on
 * the held row, the hold is kept for the payment, the deposit order is
 * written and the answer carries the payment.
 *
 * @param int   $booking_id the session's hold
 * @param array $customer   pg_booking_customer() columns
 * @param mixed $page       the widget's page path
 * @param int   $now
 * @return array the confirm answer: status pay with pay.html, or an error
 */
function pg_booking_pay_start($booking_id, $customer, $page, $now)
{
    $failed = array('ok' => false, 'error' => 'failed', 'message' => lang('Something went wrong. Please try again.'));

    db_q("UPDATE bookings SET user_id = ?, name = ?, email = ?, phone = ?, notes = ?, updated_at = ? WHERE id = ? AND status = 'held'",
        array($customer['user_id'], $customer['name'], $customer['email'], $customer['phone'], $customer['notes'], (int) $now, (int) $booking_id)) or output_error('Query failed.');

    $order_id = pg_booking_deposit_order_write($booking_id);

    if ($order_id <= 0) {
        log_activity('The deposit order of booking #' . (int) $booking_id . ' could not be written.');
        return $failed;
    }

    $pending = pg_booking_pay_pending();

    if (($pending === null) || ((int) $pending['order_id'] !== $order_id)) {
        $pending = array('booking_id' => (int) $booking_id, 'order_id' => $order_id, 'driver' => '', 'state' => array(), 'error' => '');
    }

    $pending['page'] = pg_booking_pay_page($page);
    pg_booking_pay_pending_set($pending);

    return array('ok' => true, 'status' => 'pay', 'pay' => array('html' => pg_booking_pay_html()));
}

/**
 * The payment of the session's open deposit: the amount, the choices, the
 * reason the last attempt was refused (said once) and a Pay button, posted
 * to booking_slots.php. '' when nothing is open.
 *
 * @return string
 */
function pg_booking_pay_html()
{
    $pending = pg_booking_pay_pending();
    $order = ($pending !== null) ? db_item("SELECT * FROM orders WHERE id = '" . (int) $pending['order_id'] . "'") : null;

    if (!is_array($order)) {
        return '';
    }

    $methods = pg_form_payment_methods($order['offline_payment_allowed'] ?? 0);
    $error = (string) ($pending['error'] ?? '');

    if ($error !== '') {
        $pending['error'] = '';
        pg_booking_pay_pending_set($pending);
    }

    if (isset($methods['Offline Payment'])) {
        $methods['Offline Payment'] = lang('Pay on arrival');
    }

    $html = '<form action="' . h(OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/booking_slots.php') . '" method="post" data-pg-booking-pay>'
          . get_token_field() . '<input type="hidden" name="pg_booking_pay" value="1">'
          . '<p>' . h(lang('A deposit is asked for this service. Your time is kept while you pay.')) . '</p>';

    if ((string) ($order['transaction_id'] ?? '') !== '') {
        $html .= '<div class="alert alert-info" role="status">' . h(lang('If you have just paid, the payment may take a moment to be confirmed. Reload this page before paying again.')) . '</div>';
    }

    $step = pg_form_payment_step_html(array(
        'amount'       => _pg_form_payment_money($order['total']),
        'methods'      => $methods,
        'chosen'       => '',
        'box'          => _pg_form_payment_box($methods, $order, (int) $order['total']),
        'error'        => $error,
        'offline_note' => lang('Paying on arrival sends your booking as a request; we confirm it by e-mail.'),
    ));

    return $html . $step
         . (($methods !== array()) ? '<button type="submit" class="btn btn-primary">' . h(lang('Pay')) . '</button>' : '')
         . '</form>';
}

/* ---------------------------------------------------------------------------
   booking_slots.php: the charge, the return and the state
   --------------------------------------------------------------------------- */

/**
 * Charges the session's open deposit with the chosen method: the hold is
 * kept first, offline payment completes the order unpaid and sends the
 * booking as a request, a driver's authorize() completes the order, sends
 * the visitor to its page or is refused.
 *
 * @param string $method
 * @param string $token  the card service's token
 * @param int    $now
 * @return array pg_form_payment_answer_outcome()
 */
function pg_booking_pay_charge($method, $token, $now)
{
    $pending = pg_booking_pay_pending();
    $order = ($pending !== null) ? db_item("SELECT * FROM orders WHERE id = '" . (int) $pending['order_id'] . "'") : null;

    if (!is_array($order)) {
        return array('outcome' => 'failed', 'error' => lang('The payment could not be found.'));
    }

    if ($order['status'] !== 'incomplete') {
        return array('outcome' => 'complete', 'status' => 'paid');
    }

    if (!isset(pg_form_payment_methods($order['offline_payment_allowed'])[(string) $method])) {
        return array('outcome' => 'failed', 'error' => lang('Please choose a payment method.'));
    }

    if (!pg_booking_pay_keep_hold((int) $pending['booking_id'], $now)) {
        return array('outcome' => 'expired', 'error' => lang('The time we kept for you ran out and is no longer free. Please pick another one.'));
    }

    if ($method === 'Offline Payment') {
        $outcome = pg_form_payment_finish($order, $method, '', 'paid', array());
        $booking = db_item("SELECT * FROM bookings WHERE id = '" . (int) $pending['booking_id'] . "' LIMIT 1");
        $done = is_array($booking) ? pg_booking_confirm($booking, 'requested', $now) : array('answer' => 'expired');

        if ($done['answer'] !== 'ok') {
            log_activity('Booking #' . (int) $pending['booking_id'] . ' to be paid on arrival was no longer held; its order ' . (int) $order['id'] . ' was completed.', 'SYSTEM');
        }

        return $outcome;
    }

    $key = pg_payment_order_driver((string) $method, (string) ECOMMERCE_PAYMENT_GATEWAY);
    $return_mode = pg_payment_return_mode($key);
    $result = db_q(pg_order_lines_sql(), array((string) $order['id'])) or output_error('Query failed.');
    $lines = array();

    while ($row = mysqli_fetch_assoc($result)) {
        $lines[] = $row;
    }

    $total = (int) $order['total'];
    $answer = pg_payment_call($key, 'authorize', array($order, $total, array(
        'token'         => (string) $token,
        'recurring'     => false,
        'subtotal'      => $total,
        'discount'      => 0,
        'charged_lines' => $lines,
        'charges'       => array('shipping' => 0, 'surcharge' => 0),
        'lines'         => $lines,
        'ship_to'       => array(),
        'customer_id'   => (int) $order['user_id'],
        'ip'            => (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
        'basket_id'     => 'booking-' . (int) $pending['booking_id'],
        'return_url'    => ($return_mode !== '') ? URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/booking_slots.php?mode=' . $return_mode : '',
    )));

    $pending['driver'] = $key;
    $pending['state'] = $answer['state'];
    pg_booking_pay_pending_set($pending);

    return pg_form_payment_answered($order, $method, $answer);
}

/**
 * Ends the request for an outcome: a payment page is shown; anything else
 * goes back to the widget's page, which asks for the payment's state.
 *
 * @param array $outcome
 */
function pg_booking_pay_respond($outcome)
{
    $pending = pg_booking_pay_pending();
    $page = ($pending !== null) ? (string) ($pending['page'] ?? '') : '';

    if ($outcome['outcome'] === 'redirect') {
        if ($outcome['html'] !== '') {
            echo $outcome['html'];
        } else {
            header('Location: ' . $outcome['redirect_url']);
        }

        exit();
    }

    if ($pending !== null) {
        if ($outcome['outcome'] === 'complete') {
            $_SESSION['software']['booking_paid'] = (int) $pending['booking_id'];
            pg_booking_pay_pending_forget();
        } elseif ($outcome['outcome'] === 'expired') {
            $_SESSION['software']['booking_paid'] = -1;
            pg_booking_pay_pending_forget();
            unset($_SESSION['software']['booking_hold'], $_SESSION['software']['booking_hold_slot']);
        } else {
            $pending['error'] = $outcome['error'];
            pg_booking_pay_pending_set($pending);
        }
    }

    header('Location: ' . URL_SCHEME . HOSTNAME . pg_booking_pay_page($page) . '#' . PG_BOOKING_PAY_FRAGMENT);
    exit();
}

/**
 * Pay from the widget's payment (a form post with the form token).
 *
 * @param int $now
 */
function pg_booking_pay_retry($now)
{
    if (pg_booking_pay_pending() === null) {
        header('Location: ' . URL_SCHEME . HOSTNAME . PATH);
        exit();
    }

    pg_booking_pay_respond(pg_booking_pay_charge(
        (isset($_POST['payment_method']) && is_string($_POST['payment_method'])) ? $_POST['payment_method'] : '',
        (isset($_POST['payment_token']) && is_string($_POST['payment_token'])) ? $_POST['payment_token'] : '',
        $now));
}

/**
 * The visitor is back from a driver's 3-D Secure or hosted page
 * (booking_slots.php?mode=<return mode>): the driver finishes the payment.
 */
function pg_booking_pay_return()
{
    $pending = pg_booking_pay_pending();
    $key = ($pending !== null) ? (string) $pending['driver'] : '';
    $order = ($pending !== null) ? db_item("SELECT * FROM orders WHERE id = '" . (int) $pending['order_id'] . "'") : null;

    if (!is_array($order) || ($key === '') || (($_GET['mode'] ?? '') !== pg_payment_return_mode($key))) {
        output_error(lang('The payment could not be found.'));
    }

    if ($order['status'] !== 'incomplete') {
        pg_booking_pay_respond(array('outcome' => 'complete', 'status' => 'paid'));
    }

    $answer = pg_payment_call($key, 'complete', array($order, (int) $order['total'], $_POST, $pending['state']));
    $pending['state'] = $answer['state'];
    pg_booking_pay_pending_set($pending);

    pg_booking_pay_respond(pg_form_payment_answered($order, (string) $order['payment_method'], $answer));
}

/**
 * The widget's question after a payment: the payment still open (its
 * markup), its end (the booking's closing words) or nothing.
 *
 * @return array
 */
function pg_booking_pay_state_request()
{
    $pending = pg_booking_pay_pending();

    if ($pending !== null) {
        $order = db_item("SELECT status FROM orders WHERE id = '" . (int) $pending['order_id'] . "'");

        if (is_array($order) && ($order['status'] === 'incomplete')) {
            return array('ok' => true, 'state' => 'open', 'pay' => array('html' => pg_booking_pay_html()));
        }

        $_SESSION['software']['booking_paid'] = (int) $pending['booking_id'];
        pg_booking_pay_pending_forget();
    }

    $paid = (int) ($_SESSION['software']['booking_paid'] ?? 0);
    unset($_SESSION['software']['booking_paid']);

    if ($paid < 0) {
        return array('ok' => true, 'state' => 'expired', 'message' => lang('The time we kept for you ran out and is no longer free. Please pick another one.'));
    }

    if ($paid === 0) {
        return array('ok' => true, 'state' => 'none');
    }

    // The session's hold is a booking (or a request) now.
    unset($_SESSION['software']['booking_hold'], $_SESSION['software']['booking_hold_slot']);
    $status = (string) db_value("SELECT status FROM bookings WHERE id = '$paid' LIMIT 1");

    // A card payment still being confirmed leaves the row held: it is
    // confirmed when the service's message completes the order.
    return array('ok' => true, 'state' => 'done', 'status' => $status,
        'message' => pg_booking_widget_done_text(($status === 'confirmed') ? 'confirmed' : 'requested', pg_booking_texts()));
}
