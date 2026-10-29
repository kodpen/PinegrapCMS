<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Payments - what payment_callback.php does with a message a payment service
 * sends on its own (a webhook, a server-to-server callback).
 *
 * The driver's notify() checks the message's signature and says what it is
 * about (pg_payment_<key>_notify(), includes/payments/driver.php); this file
 * finds the order it names and decides what the store does. Mostly that is
 * telling the people who run the site: a payment that went through for an
 * order still incomplete, a refund or a dispute made on the service's own
 * screen each write a line to the activity log and raise an operations alert
 * (pg_ops_alert()). Two changes are made to an order: recording the payment
 * of an order placed with its payment pending (pg_order_payment_pending())
 * once the service reports it settled, for the amount the order totals; and
 * completing an order still incomplete whose own payment the service reports
 * taken, for its total in the store's currency, when the visitor left before
 * coming back (pg_order_complete_from_payment()).
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
 * The largest body accepted. A service's event is a few kilobytes.
 *
 * @return int bytes
 */
function pg_payment_callback_max_body()
{
    return 1048576;
}

/**
 * The request's headers from $_SERVER, by lowercase name with dashes:
 * HTTP_STRIPE_SIGNATURE -> stripe-signature. getallheaders() is not there
 * under every server API (PHP-FPM before 7.3).
 *
 * @param array $server
 * @return array
 */
function pg_payment_request_headers($server)
{
    $headers = array();

    foreach ((array) $server as $name => $value) {
        if (!is_string($name) || !is_scalar($value)) {
            continue;
        }

        if (strpos($name, 'HTTP_') === 0) {
            $headers[strtolower(str_replace('_', '-', substr($name, 5)))] = (string) $value;
        } elseif (in_array($name, array('CONTENT_TYPE', 'CONTENT_LENGTH'), true)) {
            $headers[strtolower(str_replace('_', '-', $name))] = (string) $value;
        }
    }

    return $headers;
}

/**
 * How the order a notify() answer names is looked up: by id when the
 * reference is an order id, else by the payment id the order keeps as its
 * transaction id.
 *
 * @param array $answer pg_payment_answer()
 * @return array id (int, 0 for none), transaction_id ('' for none)
 */
function pg_payment_notify_order_terms($answer)
{
    $reference = isset($answer['order_reference']) ? (string) $answer['order_reference'] : '';

    if (preg_match('/^[1-9][0-9]{0,18}$/', $reference) === 1) {
        return array('id' => (int) $reference, 'transaction_id' => '');
    }

    return array('id' => 0, 'transaction_id' => $reference);
}

/**
 * The orders row a notify() answer names.
 *
 * @param array $answer pg_payment_answer()
 * @return array|null id, status, transaction_id, payment_method, total,
 *                    paid_at (0 on a schema without the column)
 */
function pg_payment_notify_order($answer)
{
    $terms = pg_payment_notify_order_terms($answer);

    if ($terms['id'] > 0) {
        $where = "id = '" . $terms['id'] . "'";
    } elseif ($terms['transaction_id'] !== '') {
        $where = "transaction_id = '" . e($terms['transaction_id']) . "'";
    } else {
        return null;
    }

    $paid_at = pg_schema_has('orders', 'paid_at') ? 'paid_at' : '0 AS paid_at';
    $order = db_item("SELECT id, status, transaction_id, payment_method, total, " . $paid_at . " FROM orders WHERE " . $where . " ORDER BY id DESC LIMIT 1");

    return is_array($order) ? $order : null;
}

/**
 * Whether a payment a notify() answer reports is the incomplete order's own,
 * whole: the order keeps that payment id as its transaction id (written when
 * the visitor was sent to the service), the amount equals orders.total in
 * integer hundredths, and the currency is the store's. Pure.
 *
 * @param array  $answer         pg_payment_answer() of notify()
 * @param array  $order          pg_payment_notify_order()
 * @param string $store_currency
 * @return bool
 */
function pg_payment_notify_matches_order($answer, $order, $store_currency)
{
    $transaction_id = (string) ($answer['transaction_id'] ?? '');
    $currency = (isset($answer['raw']['currency']) && is_string($answer['raw']['currency'])) ? strtoupper($answer['raw']['currency']) : '';

    return ($transaction_id !== '')
        && ((string) ($order['transaction_id'] ?? '') === $transaction_id)
        && isset($answer['amount']) && is_int($answer['amount'])
        && isset($order['total']) && is_numeric($order['total'])
        && ($answer['amount'] === (int) round((float) $order['total']))
        && ($currency !== '')
        && ($currency === strtoupper((string) $store_currency));
}

/**
 * What the store does with a notify() answer for the order it names. Pure:
 * the caller writes the log lines and raises the alert.
 *
 *   refused        not signed, not readable, no secret: 400, nothing done
 *   error          the driver failed in a way that says nothing (it threw):
 *                  500, so the service sends it again
 *   ignored        an event the store does not use: 200
 *   unknown_order  no order of this store, or another payment than the
 *                  order's own: 200, nothing done
 *   nothing        a payment for an order already placed and paid: 200
 *   recorded       a payment for an order placed with its payment pending,
 *                  for the order's total: 200, a log line, and the caller
 *                  marks the order paid (mark_paid)
 *   complete       a payment taken or authorized for an order still
 *                  incomplete, which is the order's own payment id, for its
 *                  total, in the store's currency: 200, and the caller
 *                  completes the order (complete) and writes the log line
 *   alerted        a payment for an order still incomplete that does not
 *                  match it, a pending payment for another amount, a refund,
 *                  a cancellation or a dispute: 200, a log line and an alert
 *
 * @param string     $key            driver key
 * @param array      $answer         pg_payment_answer() of notify(); the
 *                                   payment's currency in raw.currency
 * @param array|null $order          pg_payment_notify_order()
 * @param string     $store_currency the store's currency code
 * @return array http (status code), status, log (lines), alert (detail, '' for none),
 *               mark_paid (bool), complete (bool)
 */
function pg_payment_notify_outcome($key, $answer, $order, $store_currency = '')
{
    $outcome = array('http' => 200, 'status' => '', 'log' => array(), 'alert' => '', 'mark_paid' => false, 'complete' => false);

    if (empty($answer['success'])) {
        if (!empty($answer['raw']['ignored'])) {
            $outcome['status'] = 'ignored';
        } elseif (!empty($answer['unclear'])) {
            $outcome['http'] = 500;
            $outcome['status'] = 'error';
        } else {
            $outcome['http'] = 400;
            $outcome['status'] = 'refused';
        }

        return $outcome;
    }

    $order_transaction = (is_array($order) && isset($order['transaction_id'])) ? (string) $order['transaction_id'] : '';

    // A metadata order id from another shop on the same account names an
    // order of this one by chance; its payment id gives it away.
    if (!is_array($order)
        || (($order_transaction !== '') && ($answer['transaction_id'] !== '') && ($order_transaction !== $answer['transaction_id']))) {
        $outcome['status'] = 'unknown_order';

        return $outcome;
    }

    $order_id = (int) $order['id'];
    $line = isset($answer['log'][0]) ? $answer['log'][0] : pg_payment_log_name($key) . ' reports ' . $answer['status'] . ' for payment ' . $answer['transaction_id'];

    if (($answer['status'] === 'paid') && pg_order_payment_pending($order)) {
        if (($answer['amount'] !== null) && ((int) $answer['amount'] === (int) ($order['total'] ?? -1))) {
            $outcome['status'] = 'recorded';
            $outcome['log'] = array($line . ' for order #' . $order_id . ', which was awaiting it. The payment was recorded.');
            $outcome['mark_paid'] = true;

            return $outcome;
        }

        $line .= ' for order #' . $order_id . ', which is awaiting payment, but not for the order\'s total. The payment was not recorded: check it on ' . pg_payment_log_name($key) . '\'s own screen.';
    } elseif (in_array($answer['status'], array('paid', 'authorized'), true)) {
        if ((string) $order['status'] !== 'incomplete') {
            $outcome['status'] = 'nothing';

            return $outcome;
        }

        if (pg_payment_notify_matches_order($answer, $order, $store_currency)) {
            $outcome['status'] = 'complete';
            $outcome['log'] = array($line . ' for order #' . $order_id . ', which was still incomplete. The order was completed from the message.');
            $outcome['complete'] = true;

            return $outcome;
        }

        $line .= ' for order #' . $order_id . ', which is still incomplete, but not for its own payment, its total or the store\'s currency. The order was not completed: check the payment on ' . pg_payment_log_name($key) . '\'s own screen.';
    } else {
        $line .= ' for order #' . $order_id . '. The order was not changed.';
    }

    $outcome['status'] = 'alerted';
    $outcome['log'] = array($line);
    $outcome['alert'] = $line;

    return $outcome;
}

/**
 * Sends the answer and ends the request.
 *
 * @param int   $http
 * @param array $data
 * @return void
 */
function pg_payment_callback_answer($http, $data)
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    http_response_code((int) $http);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');

    echo json_encode($data);

    exit;
}

/**
 * Answers one message: payment_callback.php?driver=<key>.
 *
 * @return void
 */
function pg_payment_callback_receive()
{
    $method = isset($_SERVER['REQUEST_METHOD']) ? strtoupper((string) $_SERVER['REQUEST_METHOD']) : '';
    $key = (isset($_GET['driver']) && is_string($_GET['driver'])) ? $_GET['driver'] : '';

    if ($method !== 'POST') {
        pg_payment_callback_answer(405, array('status' => 'method_not_allowed'));
    }

    if (!pg_payment_key_valid($key) || !pg_payment_can($key, 'notify')) {
        pg_payment_callback_answer(404, array('status' => 'unknown_driver'));
    }

    $length = isset($_SERVER['CONTENT_LENGTH']) ? (int) $_SERVER['CONTENT_LENGTH'] : 0;

    // One byte more than the limit is read, so a body sent without a length
    // is caught at the limit rather than cut there.
    $body = ($length > pg_payment_callback_max_body()) ? false : @file_get_contents('php://input', false, null, 0, pg_payment_callback_max_body() + 1);

    if (($body === false) || (strlen($body) > pg_payment_callback_max_body())) {
        pg_payment_callback_answer(413, array('status' => 'too_large'));
    }

    $answer = pg_payment_call($key, 'notify', array(pg_payment_request_headers($_SERVER), (string) $body));
    $order = $answer['success'] ? pg_payment_notify_order($answer) : null;
    $outcome = pg_payment_notify_outcome($key, $answer, $order, defined('BASE_CURRENCY_CODE') ? (string) BASE_CURRENCY_CODE : '');

    // The completion runs before the log line, which names its result: the
    // order's number, another request completing it first (nothing), or a
    // refusal, which alerts like any payment that does not match its order.
    if ($outcome['complete']) {
        $completion = pg_order_complete_from_payment((int) $order['id'], (string) $answer['status']);

        if ($completion['status'] === 'completed') {
            $outcome['status'] = 'completed';
            $outcome['log'][0] .= ' Order number: ' . $completion['order_number'] . '.';
        } elseif ($completion['status'] === 'nothing') {
            $outcome['status'] = 'nothing';
            $outcome['log'] = array();
        } else {
            $outcome['status'] = 'alerted';
            $outcome['log'] = array(str_replace('The order was completed from the message.', 'The order was not completed: ' . $completion['reason'] . '. Check the payment on ' . pg_payment_log_name($key) . '\'s own screen.', $outcome['log'][0]));
            $outcome['alert'] = $outcome['log'][0];
        }
    }

    foreach ($outcome['log'] as $line) {
        log_activity($line, 'SYSTEM');
    }

    if ($outcome['mark_paid']) {
        pg_order_mark_paid((int) $order['id'], 0);
    }

    if ($outcome['alert'] !== '') {
        pg_ops_alert('payment_notice', $outcome['alert']);
    }

    pg_payment_callback_answer($outcome['http'], array('status' => $outcome['status']));
}
