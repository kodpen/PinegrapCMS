<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * A customer's return request and their own moves on it, posted from the
 * order view widget of My Account or from the classic order view
 * (get_view_order_screen_content.php); the forms are built by
 * includes/fn/returns.php:
 *
 *   action=request   order_id, ship_to_id, qty[<order item id>], reason, note
 *   action=withdraw  order_id, return_id  (a request nobody answered yet)
 *   action=shipped   order_id, return_id, tracking_number (optional)
 *
 * Only for the signed-in owner of the order, and only while
 * ECOMMERCE_ORDER_RETURNS_ALLOWED is on; staff manage returns from the panel.
 * GET action=label (order_id, return_id) hands the owner the carrier return
 * label of a request, whatever the setting.
 * The outcome is told through the order view's messages (liveform
 * 'order_receipt', the name the widget's messages node carries and the
 * liveform the classic order view prints), and the browser goes back to the
 * page the form was on.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');

// GET action=label, order_id, return_id: the owner downloads the carrier
// return label of a request (pg_returns_list_html() links it). The returns
// are listed whatever the setting, and so is the label staff made for the
// customer to print; it counts on the same hourly allowance as the moves.
if ((($_SERVER['REQUEST_METHOD'] ?? '') === 'GET') && (($_GET['action'] ?? '') === 'label')) {

    if (!defined('ECOMMERCE') || ECOMMERCE !== true) {
        output_error(lang('E-commerce is not enabled on this site.'));
    }

    $order_id = is_scalar($_GET['order_id'] ?? null) ? (int) $_GET['order_id'] : 0;
    $return_id = is_scalar($_GET['return_id'] ?? null) ? (int) $_GET['return_id'] : 0;
    $user_id = defined('USER_ID') ? (int) USER_ID : 0;
    $order = ($order_id > 0) ? db_item("SELECT id, order_number, user_id FROM orders WHERE id = '" . $order_id . "'") : null;
    $owner = ($user_id > 0) && $order && ((int) $order['user_id'] === $user_id);
    $rate_hit = $owner && function_exists('waf_rate_exceeded') && waf_rate_exceeded('return|' . $user_id, 'order_return', 10, 3600);
    $return = null;
    $label = null;

    if ($owner && !$rate_hit && ($return_id > 0) && pg_returns_ready()) {
        $return = db_item("SELECT id, order_id, status FROM order_returns WHERE id = '" . $return_id . "'");
        $label = $return ? pg_shipment_label_customer_pick(pg_shipment_label_return_rows($return_id)) : null;
    }

    $refusal = pg_shipment_label_customer_refusal($user_id, $order, $return, $label, $rate_hit);

    if ($refusal) {
        if (!$owner) {
            log_activity(lang(array('string' => 'A return label download was refused for order {var:1}: the visitor does not own it.', 'vars' => array($order_id))));
        }

        output_error(h($refusal['message']), $refusal['status']);
    }

    // The kept file, else the service's copy when the sweep removed it.
    $document = pg_shipment_label_document($label);

    // The service's answer is written for staff: the log keeps it, the
    // customer is told what to do.
    if (!$document['ok']) {
        log_activity(lang(array('string' => 'Return label {var:1} could not be handed to the customer: {var:2}', 'vars' => array((int) $label['id'], $document['error']))));
        output_error(h(lang('Your return label cannot be downloaded right now. Please contact us and we will send you a new one.')), 502);
    }

    $file_name = 'return-label-' . preg_replace('/[^A-Za-z0-9-]+/', '', (string) ($label['number'] !== '' ? $label['number'] : $label['id']));

    foreach (pg_shipment_label_customer_headers((string) $document['format'], $file_name) as $header) {
        header($header);
    }

    header('Content-Length: ' . strlen($document['document']));

    log_activity(lang(array('string' => 'Return label {var:1} of order {var:2} was downloaded by the customer.', 'vars' => array((int) $label['id'], ((string) $order['order_number'] !== '') ? (string) $order['order_number'] : (string) $order_id))));

    echo $document['document'];
    exit;
}

if (pg_post_body_discarded()) {
    output_error(lang('The request was too large for this server and arrived empty.'), 413);
}

validate_token_field();

if (!defined('ECOMMERCE') || ECOMMERCE !== true) {
    output_error(lang('E-commerce is not enabled on this site.'));
}

if (!pg_returns_customer_enabled()) {
    output_error(lang('Return requests are not enabled on this site.'));
}

$order_id = isset($_POST['order_id']) ? (int) $_POST['order_id'] : 0;
$user_id = defined('USER_ID') ? (int) USER_ID : 0;
$order = ($order_id > 0) ? db_item("SELECT id, order_number, user_id FROM orders WHERE id = '" . $order_id . "'") : null;

// Order ids are sequential: anybody but the signed-in owner hears the same
// answer, whether or not the order exists.
if (($user_id <= 0) || !$order || ((int) $order['user_id'] !== $user_id)) {
    log_activity(lang(array('string' => 'A return was refused for order {var:1}: the visitor does not own it.', 'vars' => array($order_id))));
    output_error(lang('You are not allowed to return items from this order.'));
}

$order_number = ((string) $order['order_number'] !== '') ? (string) $order['order_number'] : (string) $order_id;
$back = pg_safe_redirect_path(isset($_POST['send_to']) ? $_POST['send_to'] : '', PATH);
$messages = new liveform('order_receipt');
$action = isset($_POST['action']) ? (string) $_POST['action'] : '';

// A request mails the customer and rings the staff's bell, and a withdrawn
// request can be made again: a signed-in customer gets ten actions an hour,
// more than any real return takes.
if (function_exists('waf_rate_exceeded') && waf_rate_exceeded('return|' . $user_id, 'order_return', 10, 3600)) {
    $messages->mark_error('_error', h(lang('Too many return requests in a short time. Please try again later.')));
    $action = 'refused';
}

switch ($action) {

    case 'refused':
        break;

    case 'request':

        $created = pg_return_create(
            $order_id,
            isset($_POST['ship_to_id']) ? (int) $_POST['ship_to_id'] : 0,
            isset($_POST['qty']) ? $_POST['qty'] : array(),
            isset($_POST['reason']) ? $_POST['reason'] : '',
            isset($_POST['note']) ? $_POST['note'] : '',
            array('source' => 'customer', 'user_id' => $user_id));

        if (!$created['ok']) {
            $messages->mark_error('_error', h($created['error']));
            break;
        }

        // The same form sent twice: the first one has mailed and rung already,
        // so this answer only says it was received.
        if (!empty($created['repeat'])) {
            $messages->add_notice(h(lang('Your return request was received. We will let you know what happens next.')));
            break;
        }

        // The request is kept whatever happens to the mail and the bell.
        pg_return_send_request_mail($created['id']);
        pg_return_notify_staff($created['id'], 'requested');

        log_activity(lang(array('string' => 'Return request {var:1} was made for order {var:2}.', 'vars' => array($created['id'], $order_number))));

        $messages->add_notice(h(lang('Your return request was received. We will let you know what happens next.')));
        break;

    case 'withdraw':
    case 'shipped':

        $return_id = isset($_POST['return_id']) ? (int) $_POST['return_id'] : 0;
        $target = ($action === 'shipped') ? 'shipped' : 'closed';
        $current = ($return_id > 0) ? db_item("SELECT order_id, status FROM order_returns WHERE id = '" . $return_id . "'") : null;

        // A return of another order is not one of this customer's.
        if (!$current || ((int) $current['order_id'] !== $order_id)) {
            $messages->mark_error('_error', h(lang('The return could not be found.')));
            break;
        }

        // A page left open while the shop answered: said in the customer's
        // words rather than as the two statuses.
        if (!pg_return_can_move((string) $current['status'], $target, 'customer')) {
            $messages->mark_error('_error', h(lang('This return request can no longer be changed here.')));
            break;
        }

        $options = array('by' => 'customer', 'user_id' => $user_id);

        if ($action === 'shipped') {
            $options['tracking_number'] = isset($_POST['tracking_number']) ? $_POST['tracking_number'] : '';
        }

        $moved = pg_return_move($return_id, $target, $options);

        if (!$moved['ok']) {
            $messages->mark_error('_error', h($moved['error']));
            break;
        }

        if ($action === 'shipped') {
            pg_return_notify_staff($return_id, 'shipped');
            log_activity(lang(array('string' => 'Return request {var:1} of order {var:2} was sent back by the customer.', 'vars' => array($return_id, $order_number))));
            $messages->add_notice(h(lang('Thank you. We will let you know when the goods arrive.')));
        } else {
            log_activity(lang(array('string' => 'Return request {var:1} of order {var:2} was withdrawn by the customer.', 'vars' => array($return_id, $order_number))));
            $messages->add_notice(h(lang('Your return request was withdrawn.')));
        }
        break;

    default:

        $messages->mark_error('_error', h(lang('Invalid request.')));
}

header('Location: ' . URL_SCHEME . HOSTNAME . $back);
exit();
