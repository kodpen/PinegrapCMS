<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Payments - the contract every payment driver keeps, and the one door to it.
 *
 * A payment service (iyzico, Stripe, PayTR, PayPal) is a driver: a file under
 * includes/payments/<key>.php, or a file an add-on registers with
 * pg_payment_driver_register() (includes/fn/payments.php). The driver is a
 * set of functions with one prefix, pg_payment_<key>_, the way the
 * e-document and SMS layers work: what is not defined is not supported, and
 * pg_payment_can() says so before anyone tries. Callers never call a driver
 * function by name; they go through pg_payment_call(), which loads the
 * driver, catches what it throws and hands back an answer of one fixed shape.
 *
 *   pg_payment_<key>_capabilities()             required: what the service can
 *                                               do beyond the operations below
 *                                               (pg_payment_capability_names())
 *   pg_payment_<key>_fields()                   the settings the Ecommerce card
 *                                               asks for (pg_payment_fields_normalize());
 *                                               none means the driver keeps its own
 *   pg_payment_<key>_prepare($order, $amount)   before the payment form is shown:
 *                                               what the browser needs (a public key,
 *                                               a client secret, an iframe token)
 *   pg_payment_<key>_authorize($order, $amount, $payment)
 *                                               charge, or hold, what the visitor
 *                                               submitted ($payment: the checkout's
 *                                               values for this attempt - the posted
 *                                               card fields, instalment count, token,
 *                                               order lines, return address)
 *   pg_payment_<key>_complete($order, $amount, $return, $state)
 *                                               the visitor is back from a 3-D Secure
 *                                               or hosted page ($amount: what the
 *                                               order totals now; $return: the request
 *                                               the service sent back; $state: what
 *                                               authorize() asked to keep)
 *   pg_payment_<key>_capture($order, $amount, $transaction_id)
 *                                               take a held amount
 *   pg_payment_<key>_refund($order, $amount, $lines)
 *                                               give back all or part of a payment
 *                                               ($lines: order_items rows, for a
 *                                               service that refunds per line)
 *   pg_payment_<key>_cancel($order, $transaction_id)
 *                                               void a payment before it settles
 *   pg_payment_<key>_notify($headers, $body)    a message the service sends on its
 *                                               own (webhook, server callback); the
 *                                               driver checks its signature
 *
 * $order is the orders row (SELECT *), $amount whole hundredths of the order's
 * currency as everywhere in the store: the driver converts to the service's
 * units (pg_payment_decimal()). A driver never reads $_POST, $_GET, the
 * session or the database tables of an order itself, and never writes them:
 * the caller passes what it needs and records what it answers, so one place
 * decides what an order looks like after a payment.
 *
 * Every operation answers an array; pg_payment_answer() fills what is missing
 * and refuses what cannot be trusted, so callers read every key without
 * checking it:
 *
 *   success           bool
 *   error             string  a sentence for the visitor or the operator, '' on success
 *   status            string  pg_payment_statuses(), '' for prepare()
 *   transaction_id    string  the service's id of the payment
 *   amount            int|null hundredths the service says it took or returned
 *   redirect_url      string  where the visitor continues (status redirect)
 *   html              string  a page to output instead (a bank's 3-D Secure form)
 *   client            array   public values for the browser (prepare())
 *   state             array   what complete() needs back; the caller keeps it
 *                             server side, never in the page
 *   installments      int     how many instalments the payment was taken in,
 *                             0 when the service does not say
 *   card_brand        string  the card's scheme as the service names it
 *   refund_reference  string  the service's id of a refund
 *   order_reference   string  notify(): which order the message is about
 *   reply             string  notify(): the body the service expects back
 *   log               array   sentences for the site's activity log, in order;
 *                             the caller writes them, failure or not
 *   raw               array   the service's answer, for the log only; a
 *                             failure keeps the service's own words under
 *                             message ('' when it gave none), and
 *                             pg_payment_call() puts a thrown exception's
 *                             class under exception and its text under message
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
 * The operations a driver may define, with the number of arguments each is
 * called with.
 *
 * @return array operation => argument count
 */
function pg_payment_operations()
{
    return array(
        'prepare'   => 2,
        'authorize' => 3,
        'complete'  => 4,
        'capture'   => 3,
        'refund'    => 3,
        'cancel'    => 2,
        'notify'    => 2,
    );
}

/**
 * What a service can do that is not an operation of its own. A screen asks
 * pg_payment_can() before it offers the matching choice, so a driver that
 * gains one needs no change anywhere else.
 *
 * @return array
 */
function pg_payment_capability_names()
{
    return array(
        'installments',   // the visitor picks a number of instalments
        'wallets',        // Apple Pay, Google Pay or a similar wallet
        'recurring',      // a recurring payment plan is charged through it
        'saved_cards',    // a customer's card is kept as the service's token
        'partial_refund', // refund() takes less than the whole payment
        'threeds',        // 3-D Secure; complete() finishes the payment
    );
}

/**
 * The statuses a payment operation can answer with.
 *
 *   paid       the money is taken
 *   authorized the money is held; capture() takes it
 *   pending    the service decides later and tells notify()
 *   redirect   the visitor continues at redirect_url, or with html
 *   refunded   refund() gave the amount back
 *   cancelled  cancel() voided the payment
 *
 * @return array
 */
function pg_payment_statuses()
{
    return array('paid', 'authorized', 'pending', 'redirect', 'refunded', 'cancelled');
}

/**
 * The statuses each operation may answer a success with. An operation that
 * moves money must say where it is; a success without one of these is not
 * believed.
 *
 * @param string $operation
 * @return array an empty list means no status is expected
 */
function pg_payment_operation_statuses($operation)
{
    switch ((string) $operation) {
        case 'authorize':
        case 'complete':
            return array('paid', 'authorized', 'pending', 'redirect');

        case 'capture':
            return array('paid');

        case 'refund':
            return array('refunded', 'pending');

        case 'cancel':
            return array('cancelled');

        case 'notify':
            return array('paid', 'authorized', 'pending', 'refunded', 'cancelled');
    }

    return array();
}

/**
 * Whether a string can be a driver key: lowercase ASCII letters and digits, a
 * letter first, single underscores between words, 2 to 32 characters, so
 * 'payment_' plus the key fits connect_apps.provider and a double underscore
 * can separate the key from a field name (pg_payment_input_name()).
 *
 * @param mixed $key
 * @return bool
 */
function pg_payment_key_valid($key)
{
    return is_string($key)
        && (strlen($key) >= 2)
        && (strlen($key) <= 32)
        && (preg_match('/^[a-z][a-z0-9]*(_[a-z0-9]+)*$/', $key) === 1);
}

/**
 * The name of a driver's function for one operation.
 *
 * @param string $key
 * @param string $operation
 * @return string
 */
function pg_payment_function_name($key, $operation)
{
    return 'pg_payment_' . $key . '_' . $operation;
}

/**
 * A driver's declared capabilities, reduced to the known names, each once,
 * in the order pg_payment_capability_names() lists them.
 *
 * @param mixed $declared what pg_payment_<key>_capabilities() returned
 * @return array
 */
function pg_payment_capabilities_normalize($declared)
{
    if (!is_array($declared)) {
        return array();
    }

    $declared = array_filter($declared, 'is_string');

    return array_values(array_intersect(pg_payment_capability_names(), $declared));
}

/**
 * Whole hundredths as the decimal text most services take: 12345 -> "123.45".
 * The same text the legacy gateways have always sent.
 *
 * @param int|float|string $hundredths
 * @return string
 */
function pg_payment_decimal($hundredths)
{
    return sprintf('%01.2f', round((float) $hundredths) / 100);
}

/**
 * A decimal amount from a service's answer as whole hundredths: "123.45" ->
 * 12345. Rounded, never cut: 19.99 is 1998.9999... as a float.
 *
 * @param mixed $decimal
 * @return int|null null when the value is not a number
 */
function pg_payment_hundredths($decimal)
{
    if (is_int($decimal) || is_float($decimal)) {
        return (int) round($decimal * 100);
    }

    if (!is_string($decimal) || !is_numeric(trim($decimal))) {
        return null;
    }

    return (int) round((float) trim($decimal) * 100);
}

/**
 * A failed answer.
 *
 * @param string $error a sentence the visitor or the operator can read
 * @param array  $raw   what to keep for the log
 * @return array
 */
function pg_payment_failure($error, $raw = array())
{
    return pg_payment_answer('', array(
        'success' => false,
        'error'   => (string) $error,
        'raw'     => is_array($raw) ? $raw : array(),
    ));
}

/**
 * A failed answer that also has lines for the activity log.
 *
 * @param string $error
 * @param array  $log sentences, in order
 * @return array
 */
function pg_payment_failure_logged($error, $log)
{
    return pg_payment_answer('', array(
        'success' => false,
        'error'   => (string) $error,
        'log'     => is_array($log) ? $log : array(),
    ));
}

/**
 * A driver's answer in the fixed shape (see the header of this file).
 *
 * A success the caller cannot act on is turned into a failure, because the
 * caller would otherwise record a payment nobody can account for: no status
 * where the operation must give one, a status the operation cannot have, a
 * redirect with neither an https address nor a page, a callback that names
 * no order.
 *
 * @param string $operation pg_payment_operations() key, '' for none
 * @param mixed  $answer    what the driver returned
 * @return array
 */
function pg_payment_answer($operation, $answer)
{
    $shape = array(
        'success'          => false,
        'error'            => '',
        'status'           => '',
        'transaction_id'   => '',
        'amount'           => null,
        'installments'     => 0,
        'card_brand'       => '',
        'redirect_url'     => '',
        'html'             => '',
        'client'           => array(),
        'state'            => array(),
        'refund_reference' => '',
        'order_reference'  => '',
        'reply'            => '',
        'log'              => array(),
        'raw'              => array(),
    );

    if (!is_array($answer)) {
        $shape['error'] = lang('The payment service gave no answer.');

        return $shape;
    }

    foreach (array('error', 'status', 'transaction_id', 'card_brand', 'redirect_url', 'html', 'refund_reference', 'order_reference', 'reply') as $text) {
        if (isset($answer[$text]) && is_scalar($answer[$text])) {
            $shape[$text] = trim((string) $answer[$text]);
        }
    }

    foreach (array('client', 'state', 'raw') as $list) {
        if (isset($answer[$list]) && is_array($answer[$list])) {
            $shape[$list] = $answer[$list];
        }
    }

    if (isset($answer['log']) && is_array($answer['log'])) {
        $shape['log'] = array_values(array_map('strval', array_filter($answer['log'], 'is_scalar')));
    }

    if (isset($answer['installments']) && is_int($answer['installments']) && ($answer['installments'] > 0)) {
        $shape['installments'] = $answer['installments'];
    }

    if (isset($answer['amount']) && is_int($answer['amount'])) {
        $shape['amount'] = $answer['amount'];
    } elseif (isset($answer['amount']) && is_string($answer['amount']) && (preg_match('/^-?[0-9]+$/', $answer['amount']) === 1)) {
        $shape['amount'] = (int) $answer['amount'];
    }

    if (!in_array($shape['status'], pg_payment_statuses(), true)) {
        $shape['status'] = '';
    }

    $shape['success'] = (isset($answer['success']) && ($answer['success'] === true));

    if (!$shape['success']) {
        $shape['status'] = '';

        if ($shape['error'] === '') {
            $shape['error'] = lang('The payment was not accepted.');
        }

        return $shape;
    }

    $shape['error'] = '';
    $expected = pg_payment_operation_statuses($operation);

    $unreadable = ($expected && !in_array($shape['status'], $expected, true))
        || (($shape['status'] === 'redirect')
            && ($shape['html'] === '')
            && (preg_match('#^https://[^\s/]+#i', $shape['redirect_url']) !== 1))
        || (($operation === 'notify') && ($shape['order_reference'] === ''));

    if ($unreadable) {
        $shape['success'] = false;
        $shape['status'] = '';
        $shape['error'] = lang('The payment service gave an answer that cannot be read. Check the payment on the service\'s own screen before trying again.');
    }

    if ($shape['status'] !== 'redirect') {
        $shape['redirect_url'] = '';
        $shape['html'] = '';
    }

    return $shape;
}

/**
 * Whether a driver can do something: an operation it defines, or a
 * capability it declares.
 *
 * @param string $key
 * @param string $what an operation (pg_payment_operations()) or a capability
 *                     (pg_payment_capability_names())
 * @return bool
 */
function pg_payment_can($key, $what)
{
    if (!pg_payment_load($key)) {
        return false;
    }

    if (array_key_exists((string) $what, pg_payment_operations())) {
        return function_exists(pg_payment_function_name($key, $what));
    }

    return in_array((string) $what, pg_payment_capabilities($key), true);
}

/**
 * A driver's capabilities.
 *
 * @param string $key
 * @return array
 */
function pg_payment_capabilities($key)
{
    if (!pg_payment_load($key)) {
        return array();
    }

    try {
        return pg_payment_capabilities_normalize(call_user_func(pg_payment_function_name($key, 'capabilities')));
    } catch (\Throwable $exception) {
        return array();
    }
}

/**
 * Runs one operation of a driver. Never throws: a driver that is missing,
 * does not define the operation, or throws, answers a failure, with the
 * exception's class and message under raw for the log.
 *
 * @param string $key
 * @param string $operation pg_payment_operations() key
 * @param array  $args      the operation's arguments, in order
 * @return array pg_payment_answer()
 */
function pg_payment_call($key, $operation, $args = array())
{
    $operations = pg_payment_operations();
    $operation = (string) $operation;

    if (!isset($operations[$operation]) || !pg_payment_load($key)) {
        return pg_payment_failure(lang('This payment method is not available.'));
    }

    $function = pg_payment_function_name($key, $operation);

    if (!function_exists($function)) {
        return pg_payment_failure(lang('This payment method cannot do that.'));
    }

    $args = is_array($args) ? array_values($args) : array();
    $args = array_pad(array_slice($args, 0, $operations[$operation]), $operations[$operation], null);

    try {
        $answer = call_user_func_array($function, $args);
    } catch (\Throwable $exception) {
        return pg_payment_failure(
            lang('The payment service could not be reached.'),
            array('exception' => get_class($exception), 'message' => $exception->getMessage()));
    }

    return pg_payment_answer($operation, $answer);
}
