<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * One return request, for whoever manages the orders: its lines, the
 * customer's reason and words, the delivery and its deadlines, and the moves
 * staff may make (includes/fn/returns.php, pg_return_staff_actions()):
 *
 *   action=approve   note (shown to the customer), carrier, carrier_code
 *   action=reject    note (required: the customer is told why)
 *   action=shipped   tracking_number, carrier (the parcel left the customer)
 *   action=received  the goods are back
 *   action=close     the case is settled outside these steps
 *   action=refund    refund_amount, refund_method (driver or manual),
 *                    refund_reference: the money back once the goods are here
 *
 * Every form also posts status_was, the status the page showed: a return
 * that is in another status by then is not moved (the page is shown again).
 *
 * Approving, rejecting and refunding mail the customer. Every move is written
 * to the audit trail and the activity log. A refund of an order with an
 * issued invoice goes on to its return invoice, filled in with the same lines.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');

if (pg_post_body_discarded()) {
    output_error(lang('The request was too large for this server and arrived empty.'), 413);
}

$user = validate_user();
validate_ecommerce_access($user);

if (!defined('ECOMMERCE') || (ECOMMERCE !== true)) {
    output_error(lang('E-commerce is not enabled on this site.'));
}

$liveform = new liveform('view_return');
$return_id = isset($_REQUEST['id']) ? (int) $_REQUEST['id'] : 0;
$return = pg_return_load($return_id);

if (!$return) {
    output_error(lang('The return could not be found.') . ' <a href="view_returns.php">' . h(lang('All Returns')) . '</a>');
}

$return_id = (int) $return['id'];
$here = PATH . SOFTWARE_DIRECTORY . '/view_return.php?id=' . $return_id;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_token_field();

    // What was typed comes back only with a refusal; a move that went
    // through leaves the forms empty.
    $refuse = function ($field, $message) use ($liveform, $here) {
        $liveform->add_fields_to_session();
        $liveform->mark_error($field, h($message));
        go($here);
    };

    $targets = array(
        'approve'  => 'approved',
        'reject'   => 'rejected',
        'shipped'  => 'shipped',
        'received' => 'received',
        'close'    => 'closed',
    );

    $action = isset($_POST['action']) ? (string) $_POST['action'] : '';

    // The status the page showed. A form without it was drawn before it
    // carried the status, and is refused like any other page left open while
    // the return moved. This check gives every such page one sentence; the
    // guard is the same comparison made again under the order's return lock
    // (status_was below, pg_return_move()), where a click that waited on a
    // refund is caught too. The refund re-reads under that lock as well and
    // takes only a received return (pg_return_refund()).
    $shown = (isset($_POST['status_was']) && is_string($_POST['status_was'])) ? $_POST['status_was'] : '';

    if (!pg_return_status_seen($shown, (string) $return['status'])) {
        $refuse('_error', lang('The return was changed in the meantime. Open it again.'));
    }

    // The money back: its own path, because it moves money before it moves
    // the return (pg_return_refund()).
    if ($action === 'refund') {
        $cents = pg_return_amount_cents(isset($_POST['refund_amount']) ? $_POST['refund_amount'] : '');

        if ($cents < 0) {
            $refuse('refund_amount', lang('Enter the amount to refund, such as 120 or 120.50.'));
        }

        $refunded = pg_return_refund($return_id, array(
            'amount'    => $cents,
            'method'    => isset($_POST['refund_method']) ? (string) $_POST['refund_method'] : 'manual',
            'reference' => isset($_POST['refund_reference']) ? (string) $_POST['refund_reference'] : '',
        ), (int) $user['id']);

        if (!$refunded['ok']) {
            $refuse('_error', $refunded['error']);
        }

        if ($refunded['amount'] === 0) {
            $done = lang('The return was marked as refunded.');
        } elseif ($refunded['mailed']) {
            $done = lang(array('string' => 'The refund of {var:1} was recorded. The customer was told by e-mail.', 'vars' => array(pg_return_money_text($refunded['amount']))));
        } else {
            $done = lang(array('string' => 'The refund of {var:1} was recorded, but the e-mail to the customer could not be sent.', 'vars' => array(pg_return_money_text($refunded['amount']))));
        }

        // An issued invoice gets its return invoice for the same lines next,
        // filled in; whoever may use the ERP checks it and saves. Nothing is
        // issued without them.
        $erp = $refunded['erp'];

        if (is_array($erp) && ((int) $erp['invoice_id'] > 0) && !empty($erp['quantities']) && pg_return_erp_hand_off($user)) {

            $liveform_return = new liveform('add_erp_return');
            $liveform_return->add_notice(h($done) . ' ' . h(lang('Issue the return invoice for the same lines to finish.')));

            go(PATH . SOFTWARE_DIRECTORY . '/add_erp_return.php?' . http_build_query(array(
                'invoice_id' => (int) $erp['invoice_id'],
                'back_order' => (int) $return['order_id'],
                'qty'        => $erp['quantities'],
            )));
        }

        $liveform->add_notice(h($done));
        go($here);
    }

    if (!isset($targets[$action])) {
        $refuse('_error', lang('Invalid request.'));
    }

    $to = $targets[$action];

    // A target the status does not allow (the page showed this status, so
    // only a crafted request gets here): said plainly rather than as the two
    // statuses.
    if (!in_array($to, pg_return_staff_actions((string) $return['status']), true)) {
        $refuse('_error', lang('This return was changed in the meantime and can no longer be moved that way.'));
    }

    $options = array('status_was' => $shown);

    if (($to === 'approved') || ($to === 'rejected')) {
        $options['note'] = isset($_POST['note']) ? (string) $_POST['note'] : '';
    }

    if ($to === 'approved') {
        $options['carrier'] = isset($_POST['carrier']) ? (string) $_POST['carrier'] : '';
        $options['carrier_code'] = isset($_POST['carrier_code']) ? (string) $_POST['carrier_code'] : '';
    }

    if ($to === 'shipped') {
        $options['tracking_number'] = isset($_POST['tracking_number']) ? (string) $_POST['tracking_number'] : '';

        // An empty choice keeps the carrier given with the approval.
        if (isset($_POST['carrier']) && (trim((string) $_POST['carrier']) !== '')) {
            $options['carrier'] = (string) $_POST['carrier'];
        }
    }

    $moved = pg_return_staff_move($return_id, $to, $options, (int) $user['id']);

    if (!$moved['ok']) {
        $refuse((($to === 'rejected') && ($return['status'] === 'requested')) ? 'note' : '_error', $moved['error']);
    }

    switch ($to) {
        case 'approved':
            $liveform->add_notice(h($moved['mailed']
                ? lang('The return was approved. The customer was told by e-mail how to send the goods.')
                : lang('The return was approved, but the e-mail to the customer could not be sent.')));
            break;
        case 'rejected':
            $liveform->add_notice(h($moved['mailed']
                ? lang('The return was rejected. The customer was told by e-mail.')
                : lang('The return was rejected, but the e-mail to the customer could not be sent.')));
            break;
        case 'shipped':
            $liveform->add_notice(h(lang('The parcel was recorded as sent back.')));
            break;
        case 'received':
            $liveform->add_notice(h(lang('The goods were marked as received.')));
            break;
        case 'closed':
            $liveform->add_notice(h(lang('The return was closed.')));
            break;
    }

    go($here);
}

// ---------------------------------------------------------------------------
// What the screen shows.
// ---------------------------------------------------------------------------

$order = db_item(
    "SELECT id, order_number, status, payment_method, order_date, user_id,
        billing_first_name, billing_last_name, billing_email_address, billing_phone_number
    FROM orders
    WHERE id = '" . (int) $return['order_id'] . "'");

$order_number = ($order && ((string) $order['order_number'] !== '')) ? (string) $order['order_number'] : (string) (int) $return['order_id'];
$status = (string) $return['status'];
$state = pg_return_delivery_state($return['order_id'], $return['ship_to_id']);
$lines = pg_return_order_lines($return['order_id'], $return['ship_to_id']);
$actions = pg_return_staff_actions($status);
$reasons = pg_return_reasons();
$carriers = function_exists('pg_shipping_carriers') ? pg_shipping_carriers() : array();
$people = pg_return_user_names(array($return['requested_by'], $return['decided_by']));

// What every form posts besides its own fields: the token, the return and the
// status this page shows. The status is printed from the row, never through
// the liveform: a refused post keeps its fields in the session, and a status
// filled back from there would send the stale one again on the next click.
// autocomplete="off" is outside the HTML spec on a hidden input, but it stops
// Firefox from restoring a status saved before a reload.
$form_keys = get_token_field() . '
                        <input type="hidden" name="id" value="' . $return_id . '">
                        <input type="hidden" name="status_was" value="' . h($status) . '" autocomplete="off">';

// The lines of the return, with what each one is in the order.
$output_lines = '';

foreach ($return['items'] as $item) {
    $line = isset($lines[(int) $item['order_item_id']]) ? $lines[(int) $item['order_item_id']] : null;

    $output_lines .= '
                            <tr>
                                <td>' . h((string) $item['name']) . (($line && ($line['code'] !== '')) ? '<div class="small fg-secondary">' . h($line['code']) . '</div>' : '') . '</td>
                                <td class="text-end">' . ($line ? (int) $line['quantity'] : '') . '</td>
                                <td class="text-end fw-semibold">' . (int) $item['quantity'] . '</td>
                                <td class="text-end">' . ($line ? prepare_amount($line['price'] / 100) : '') . '</td>
                            </tr>';
}

// The carrier choices: the known carriers, plus the stored or suggested one
// when it is a name typed by hand.
$carrier_options = function ($current, $empty_label) use ($carriers) {
    $html = '<option value="">' . h($empty_label) . '</option>';
    $current = (string) $current;
    $found = false;

    foreach ($carriers as $key => $entry) {
        $selected = ($key === $current);
        $found = $found || $selected;
        $html .= '<option value="' . h($key) . '"' . ($selected ? ' selected' : '') . '>' . h((string) $entry['name']) . '</option>';
    }

    if (($current !== '') && !$found) {
        $html .= '<option value="' . h($current) . '" selected>' . h($current) . '</option>';
    }

    return $html;
};

$field_value = function ($field, $default) use ($liveform) {
    return $liveform->field_in_session($field) ? (string) $liveform->get_field_value($field) : (string) $default;
};

$output_forms = '';

// Approve or reject a request.
if (in_array('approved', $actions, true)) {
    $suggested = pg_return_suggested_approval($return, $state);

    $output_forms .= '
            <div class="card mb-7">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span class="text-uppercase h5 fg-primary fw-bold mb-0">' . h(lang('Answer the Request')) . '</span>
                </div>
                <div class="card-body">
                    <form method="post" action="view_return.php">
                        ' . $form_keys . '
                        <div class="row g-5">
                            <div class="col-12">
                                <label for="note" class="form-label">' . h(lang('Note to the customer')) . '</label>
                                ' . $liveform->output_field(array('type' => 'textarea', 'name' => 'note', 'id' => 'note', 'rows' => '3', 'maxlength' => PG_RETURN_NOTE_MAX, 'class' => 'form-control')) . '
                                <div id="note_help" class="form-text">' . h(lang('Sent to the customer with the answer. Needed when you reject the request.')) . '</div>
                            </div>
                            <div class="col-12 md:col-6">
                                <label for="carrier" class="form-label">' . h(lang('Return carrier')) . '</label>
                                <select class="form-control" id="carrier" name="carrier">' . $carrier_options($field_value('carrier', $suggested['carrier']), lang('Not named')) . '</select>
                            </div>
                            <div class="col-12 md:col-6">
                                <label for="carrier_code" class="form-label">' . h(lang('Return code')) . '</label>
                                ' . $liveform->output_field(array('type' => 'text', 'name' => 'carrier_code', 'id' => 'carrier_code', 'value' => $suggested['carrier_code'], 'maxlength' => '64', 'class' => 'form-control', 'autocomplete' => 'off')) . '
                                <div id="carrier_code_help" class="form-text">' . h(lang('The code the customer gives the carrier so that the shop pays for the parcel.')) . '</div>
                            </div>
                        </div>
                        <div class="d-flex flex-wrap gap-3 mt-5">
                            <button type="submit" name="action" value="approve" class="btn btn-solid theme-primary"><i class="bi bi-check2-circle me-1" aria-hidden="true"></i>' . h(lang('Approve the Return')) . '</button>
                            <button type="submit" name="action" value="reject" class="btn btn-sm btn-outline theme-warning align-self-center" data-confirm-content="' . h(lang('The request will be rejected and the customer is told why by e-mail.')) . '"><i class="bi bi-x-circle me-1" aria-hidden="true"></i>' . h(lang('Reject the Return')) . '</button>
                        </div>
                    </form>
                </div>
            </div>';
}

// The goods on their way back, and their arrival.
if (in_array('shipped', $actions, true) || in_array('received', $actions, true)) {
    $output_shipped = '';

    if (in_array('shipped', $actions, true)) {
        $output_shipped = '
                    <form method="post" action="view_return.php" class="mb-5">
                        ' . $form_keys . '
                        <div class="row g-5 align-items-end">
                            <div class="col-12 md:col-5">
                                <label for="tracking_number" class="form-label">' . h(lang('Tracking number of the parcel')) . '</label>
                                ' . $liveform->output_field(array('type' => 'text', 'name' => 'tracking_number', 'id' => 'tracking_number', 'maxlength' => '100', 'class' => 'form-control', 'autocomplete' => 'off')) . '
                            </div>
                            <div class="col-12 md:col-4">
                                <label for="shipped_carrier" class="form-label">' . h(lang('Return carrier')) . '</label>
                                <select class="form-control" id="shipped_carrier" name="carrier">' . $carrier_options('', lang('As approved')) . '</select>
                            </div>
                            <div class="col-12 md:col-3">
                                <button type="submit" name="action" value="shipped" class="btn btn-sm btn-outline theme-secondary w-100">' . h(lang('Record as Sent Back')) . '</button>
                            </div>
                        </div>
                        <div class="form-text">' . h(lang('When the customer says the parcel left without doing so on their order page. The refund is due 14 days after the parcel reached the carrier.')) . '</div>
                    </form>';
    }

    $output_received = in_array('received', $actions, true) ? '
                    <form method="post" action="view_return.php">
                        ' . $form_keys . '
                        <button type="submit" name="action" value="received" class="btn btn-solid theme-primary" data-confirm-content="' . h(lang('The goods of this return will be marked as received.')) . '"><i class="bi bi-box-seam me-1" aria-hidden="true"></i>' . h(lang('Mark the Goods Received')) . '</button>
                    </form>' : '';

    $output_forms .= '
            <div class="card mb-7">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span class="text-uppercase h5 fg-primary fw-bold mb-0">' . h(lang('The Goods')) . '</span>
                </div>
                <div class="card-body">
                    ' . $output_shipped . '
                    ' . $output_received . '
                </div>
            </div>';
}

// The money back, once the goods are here: through the payment's driver when
// it can give money back (pg_return_refund_plan()), otherwise recorded after
// it went back another way.
$refund_plan = ($status === 'received') ? pg_return_refund_plan($return) : null;

if ($refund_plan !== null) {
    $money = function ($cents) {
        return pg_format_money((int) $cents / 100, BASE_CURRENCY_SYMBOL);
    };

    $by_driver = ($refund_plan['driver'] !== '');
    $method = $field_value('refund_method', $by_driver ? 'driver' : 'manual');

    if (!$by_driver) {
        $method = 'manual';
    }

    $output_methods = $by_driver ? '
                        <div class="mb-5">
                            <div class="form-field">
                                <input class="radio" type="radio" name="refund_method" id="refund_method_driver" value="driver"' . (($method === 'driver') ? ' checked' : '') . '>
                                <label class="" for="refund_method_driver">' . h(lang(array('string' => 'Refund through {var:1}', 'vars' => array($refund_plan['driver_label'])))) . '</label>
                            </div>
                            <div class="form-field">
                                <input class="radio" type="radio" name="refund_method" id="refund_method_manual" value="manual"' . (($method === 'manual') ? ' checked' : '') . '>
                                <label class="" for="refund_method_manual">' . h(lang('Record a refund made another way')) . '</label>
                            </div>
                        </div>' : '
                        <input type="hidden" name="refund_method" value="manual">
                        <p class="small fg-secondary">' . h(lang('The payment of this order cannot be refunded from here. Give the money back another way (a bank transfer, for example), then record it here.')) . '</p>';

    // The return invoice opens after the refund only for whoever may use the
    // ERP (the redirect above); anyone else is told it still has to be issued.
    $output_erp_note = '';

    if ((int) $refund_plan['erp']['invoice_id'] > 0) {
        $output_erp_note = '
                        <p class="small fg-secondary mb-0 mt-3">' . h(pg_return_erp_hand_off($user)
                            ? lang(array('string' => 'After the refund, the return invoice for {var:1} opens with the same lines, ready to be checked and issued.', 'vars' => array($refund_plan['erp']['invoice_number'])))
                            : lang(array('string' => 'The return invoice for {var:1} is not issued here: someone who uses the ERP must issue it after the refund.', 'vars' => array($refund_plan['erp']['invoice_number'])))) . '</p>';
    }

    $output_forms .= '
            <div class="card mb-7">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span class="text-uppercase h5 fg-primary fw-bold mb-0">' . h(lang('Payment Refund')) . '</span>
                </div>
                <div class="card-body">
                    <div class="row small mb-5">
                        <div class="col-12 md:col-4"><span class="fg-secondary">' . h(lang('The returned items come to')) . ':</span> <strong>' . $money($refund_plan['estimate']) . '</strong></div>
                        <div class="col-12 md:col-4"><span class="fg-secondary">' . h(lang('Refunded on this order so far')) . ':</span> ' . $money($refund_plan['refunded']) . '</div>
                        <div class="col-12 md:col-4"><span class="fg-secondary">' . h(lang('Left to refund on this order')) . ':</span> ' . $money($refund_plan['remaining']) . '</div>
                    </div>
                    <form method="post" action="view_return.php">
                        ' . $form_keys . '
                        ' . $output_methods . '
                        <div class="row g-5">
                            <div class="col-12 md:col-4">
                                <label for="refund_amount" class="form-label">' . h(lang('Amount to refund')) . '</label>
                                ' . $liveform->output_field(array('type' => 'number', 'name' => 'refund_amount', 'id' => 'refund_amount', 'value' => number_format($refund_plan['suggested'] / 100, 2, '.', ''), 'min' => '0', 'max' => number_format($refund_plan['remaining'] / 100, 2, '.', ''), 'step' => '0.01', 'inputmode' => 'decimal', 'class' => 'form-control', 'required' => 'required')) . '
                            </div>
                            <div class="col-12 md:col-8">
                                <label for="refund_reference" class="form-label">' . h(lang('Refund reference')) . ' <span class="fg-secondary fw-normal">(' . h(lang('optional')) . ')</span></label>
                                ' . $liveform->output_field(array('type' => 'text', 'name' => 'refund_reference', 'id' => 'refund_reference', 'maxlength' => '255', 'class' => 'form-control', 'autocomplete' => 'off')) . '
                            </div>
                        </div>
                        <div class="form-text">' . h(lang('Shipping is not included; add it when the whole order comes back. Enter 0 when the money already went back from the order screen. The reference is kept only with a refund made another way.')) . '</div>
                        ' . $output_erp_note . '
                        <button type="submit" name="action" value="refund" class="btn btn-solid theme-primary mt-5" data-confirm-content="' . h(lang('The refund will be made and the return marked as refunded. The customer is told by e-mail.')) . '"><i class="bi bi-cash-coin me-1" aria-hidden="true"></i>' . h(lang('Refund the Money')) . '</button>
                    </form>
                </div>
            </div>';
}

// The deliveries' parcels, and how they were recognised.
$output_parcels = '';

foreach (($state && !empty($state['parcels'])) ? $state['parcels'] : array() as $parcel) {
    $output_parcels .= '<li>' . h($parcel['number'])
        . (($parcel['carrier'] !== '') ? ' · ' . h(pg_return_carrier_label($parcel['carrier'])) : '')
        . (($parcel['delivered'] !== '') ? ' · ' . h(lang(array('string' => 'delivered on {var:1}', 'vars' => array(pg_return_day_text($parcel['delivered']))))) : '')
        . '</li>';
}

// Whether the request came in time, read as GET /orders/{id}/returns reads
// it (pg_return_timing()): the delivery as it is known today, the request's
// own day.
$window = $state ? $state['window'] : null;
$requested_day = ((int) $return['requested_at'] > 0) ? date('Y-m-d', (int) $return['requested_at']) : '';
$timing = pg_return_timing($window, $requested_day);
$output_window = '';

if ($timing) {
    $output_window = '
                        <div class="row">
                            <span class="col fg-secondary">' . h($timing['estimated'] ? lang('Delivered (estimated)') : lang('Delivered')) . ':</span>
                            <span class="col text-end">' . h(pg_return_day_text($timing['delivered'])) . '</span>
                        </div>
                        <div class="row">
                            <span class="col fg-secondary">' . h(lang('Return period ends')) . ':</span>
                            <span class="col text-end">' . h(pg_return_day_text($timing['deadline'])) . '</span>
                        </div>
                        <div class="row">
                            <span class="col fg-secondary">' . h(lang('Asked')) . ':</span>
                            <span class="col text-end">' . ($timing['in_time']
                                ? '<span class="fg-success">' . h(lang('within the return period')) . '</span>'
                                : '<span class="fg-danger">' . h(lang('after the return period')) . '</span>') . '</span>
                        </div>';
} else {
    $output_window = '
                        <p class="small fg-secondary mb-0">' . h(lang('No delivery date is known for this delivery, so the return period cannot be worked out.')) . '</p>';
}

$refund_due = pg_return_refund_due($return);

// The refund the return keeps (11.0.34), once it was refunded: how much, how
// it was made, the reference and a warning when the payment service gave no
// clear answer. A refund recorded as 0 says the money went back from the
// order screen rather than showing an amount of 0. A return refunded before
// the refund was kept, whose refund could not be found again, says so.
$output_refund = '';

if (((int) $return['refunded_at'] > 0) && array_key_exists('refund_method', $return)) {
    $refund_method = (string) $return['refund_method'];
    $refund_reference = (string) $return['refund_reference'];

    if (($refund_method === 'manual') && ((int) $return['refund_amount'] === 0)) {
        $output_refund = '
                        <div class="mt-5 small">
                            <p class="mb-1">' . h(lang('Marked as refunded: the money went back from the order screen.')) . '</p>
                            ' . (($refund_reference !== '') ? '<div class="row">
                                <span class="col fg-secondary">' . h(lang('Refund reference')) . ':</span>
                                <span class="col text-end text-break">' . h($refund_reference) . '</span>
                            </div>' : '') . '
                        </div>';
    } elseif (in_array($refund_method, array('driver', 'manual'), true)) {
        $output_refund = '
                        <div class="mt-5 small">
                            <div class="row">
                                <span class="col fg-secondary">' . h(lang('Amount refunded')) . ':</span>
                                <span class="col text-end fw-semibold">' . h(pg_return_money_text((int) $return['refund_amount'])) . '</span>
                            </div>
                            <div class="row">
                                <span class="col fg-secondary">' . h(lang('Refund method')) . ':</span>
                                <span class="col text-end">' . h(($refund_method === 'driver') ? lang('Through the payment service') : lang('Recorded, made another way')) . '</span>
                            </div>
                            ' . (($refund_reference !== '') ? '<div class="row">
                                <span class="col fg-secondary">' . h(lang('Refund reference')) . ':</span>
                                <span class="col text-end text-break">' . h($refund_reference) . '</span>
                            </div>' : '') . '
                            ' . (((int) $return['refund_unclear'] === 1) ? '<div class="alert theme-warning small mt-3 mb-0">' . h(lang('The payment service has not confirmed this refund. If this note stays, check the refund on the service\'s screen.')) . '</div>' : '') . '
                        </div>';
    } else {
        $output_refund = '
                        <p class="small fg-secondary mt-5 mb-0">' . h(lang('The amount and the way of this refund were not kept.')) . '</p>';
    }
}

// Who asked: a person when one is known, else where the request came from.
$sources = array(
    'customer' => lang('The customer, from their account'),
    'panel'    => lang('Staff, from the panel'),
    'api'      => lang('An app, through the API'),
);

$requested_by = isset($people[(int) $return['requested_by']]) ? $people[(int) $return['requested_by']] : '';
$source = isset($sources[(string) $return['source']]) ? $sources[(string) $return['source']] : (string) $return['source'];

$stamp_rows = '';
// Each stamp under the name of the status it marks; a decision is the one
// that was made.
$stamps = array(
    'requested_at' => pg_return_status_label('requested'),
    'decided_at'   => pg_return_status_label(($status === 'rejected') ? 'rejected' : 'approved'),
    'shipped_at'   => pg_return_status_label('shipped'),
    'received_at'  => pg_return_status_label('received'),
    'refunded_at'  => pg_return_status_label('refunded'),
    'closed_at'    => pg_return_status_label('closed'),
);

foreach ($stamps as $column => $label) {
    if ((int) $return[$column] <= 0) {
        continue;
    }

    $who = '';

    if (($column === 'decided_at') && isset($people[(int) $return['decided_by']])) {
        $who = $people[(int) $return['decided_by']];
    } elseif ($column === 'requested_at') {
        $who = ($requested_by !== '') ? $requested_by : $source;
    }

    $stamp_rows .= '
                        <li class="mb-1"><span class="fw-semibold">' . h($label) . '</span> · ' . h(pg_return_time_text($return[$column]))
                        . (($who !== '') ? '<div class="small fg-secondary">' . h($who) . '</div>' : '') . '</li>';
}

$output_close = in_array('closed', $actions, true) ? '
                    <form method="post" action="view_return.php" class="mt-5">
                        ' . $form_keys . '
                        <button type="submit" name="action" value="close" class="btn btn-sm btn-outline theme-warning" data-confirm-content="' . h(lang('The return will be closed and can no longer be moved. A request closed before the goods were sent frees them to be asked for again.')) . '"><i class="bi bi-archive me-1" aria-hidden="true"></i>' . h(lang('Close the Return')) . '</button>
                    </form>' : '';

$customer = $order ? trim(trim((string) $order['billing_first_name']) . ' ' . trim((string) $order['billing_last_name'])) : '';

$output_customer_note = ((string) $return['customer_note'] !== '') ? '
                    <div class="mt-5"><div class="small fg-secondary mb-1">' . h(lang('The customer\'s note')) . '</div><div class="border rounded-4 p-3 bg-1">' . nl2br(h((string) $return['customer_note'])) . '</div></div>' : '';

$output_decision = '';

if ((int) $return['decided_at'] > 0) {
    $output_decision = '
                    <div class="mt-5">
                        ' . (((string) $return['decision_note'] !== '') ? '<div class="small fg-secondary mb-1">' . h(lang('Note to the customer')) . '</div><div class="border rounded-4 p-3 bg-1 mb-3">' . nl2br(h((string) $return['decision_note'])) . '</div>' : '') . '
                        ' . (((string) $return['carrier'] !== '') ? '<div><span class="fg-secondary">' . h(lang('Return carrier')) . ':</span> ' . h(pg_return_carrier_label((string) $return['carrier'])) . '</div>' : '') . '
                        ' . (((string) $return['carrier_code'] !== '') ? '<div><span class="fg-secondary">' . h(lang('Return code')) . ':</span> <strong>' . h((string) $return['carrier_code']) . '</strong></div>' : '') . '
                        ' . (((string) $return['tracking_number'] !== '') ? '<div><span class="fg-secondary">' . h(lang('Tracking number')) . ':</span> ' . h((string) $return['tracking_number']) . '</div>' : '') . '
                    </div>';
}

echo
    pg_page_shell(array(
        'title'               => lang(array('string' => 'Return request #{var:1}', 'vars' => array($return_id))),
        'extra classes'       => 'store',
        'icon'                => 'store',
        'heading'             => lang(array('string' => 'Return request #{var:1}', 'vars' => array($return_id))),
        'heading_description' => lang(array('string' => 'Order {var:1}', 'vars' => array($order_number))),
        'cancel'              => array('enable' => 'true', 'url' => 'view_returns.php'),
        'breadcrumb'          => array(
            array('label' => lang('All Orders'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_orders.php'),
            array('label' => lang('Returns'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_returns.php'),
            array('label' => '#' . $return_id),
        ),
    )) . '
<main id="content" class="container-fluid">
    ' . $liveform->output_errors() . '
    ' . $liveform->output_notices() . '
    <nav id="button_bar" class="pg-toolbar navigation" aria-label="' . h(lang('Button Bar')) . '">
        <a class="btn btn-sm btn-ghost" href="view_returns.php"><i class="bi bi-arrow-return-left me-1" aria-hidden="true"></i>' . h(lang('All Returns')) . '</a>
        <a class="btn btn-sm btn-ghost" href="view_order.php?id=' . (int) $return['order_id'] . '"><i class="bi bi-receipt me-1" aria-hidden="true"></i>' . h(lang('View Order')) . '</a>
    </nav>
    <div class="row g-7">
        <div class="col-12 lg:col-8 2xl:col-9 order-1">
            <div class="card mb-7">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span class="text-uppercase h5 fg-primary fw-bold mb-0">' . h(lang('Items to Return')) . '</span>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead>
                                <tr>
                                    <th scope="col">' . h(lang('Product')) . '</th>
                                    <th scope="col" class="text-end">' . h(lang('Ordered')) . '</th>
                                    <th scope="col" class="text-end">' . h(lang('To return')) . '</th>
                                    <th scope="col" class="text-end">' . h(lang('Unit price')) . '</th>
                                </tr>
                            </thead>
                            <tbody>' . $output_lines . '
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-5"><span class="fg-secondary">' . h(lang('Reason')) . ':</span> ' . h(isset($reasons[(string) $return['reason']]) ? $reasons[(string) $return['reason']] : (string) $return['reason']) . '</div>
                    ' . $output_customer_note . '
                    ' . $output_decision . '
                </div>
            </div>
            ' . $output_forms . '
        </div>
        <div class="col-12 lg:col-4 2xl:col-3 order-2">
            <div class="position-sticky" style="top:1rem;">
                <div class="card mb-7">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-5">
                            <span class="fg-secondary">' . h(lang('Status')) . '</span>
                            <span class="' . h(pg_return_badge_class($status)) . ' fs-md">' . h(pg_return_status_label($status)) . '</span>
                        </div>
                        <ul class="list-unstyled small mb-0">' . $stamp_rows . '
                        </ul>
                        ' . $output_refund . '
                        ' . (($refund_due !== '') ? '<div class="mt-5"><span class="fg-secondary">' . h(lang('Refund due by')) . ':</span> <span class="' . ((strcmp(date('Y-m-d'), $refund_due) > 0) ? 'fg-danger fw-semibold' : 'fw-semibold') . '">' . h(pg_return_day_text($refund_due)) . '</span></div>' : '') . '
                        ' . $output_close . '
                    </div>
                </div>
                <div class="card mb-7">
                    <div class="card-header text-uppercase h6 fg-primary fw-bold mb-0">' . h(lang('Order')) . '</div>
                    <div class="card-body small">
                        <div class="row">
                            <span class="col fg-secondary">' . h(lang('Order Number')) . ':</span>
                            <span class="col text-end"><a href="view_order.php?id=' . (int) $return['order_id'] . '">' . h($order_number) . '</a></span>
                        </div>
                        ' . ($order ? '<div class="row">
                            <span class="col fg-secondary">' . h(lang('Order Date')) . ':</span>
                            <span class="col text-end">' . h(pg_return_time_text($order['order_date'])) . '</span>
                        </div>
                        <div class="row">
                            <span class="col fg-secondary">' . h(lang('Payment Method')) . ':</span>
                            <span class="col text-end">' . h((string) $order['payment_method']) . '</span>
                        </div>' : '') . '
                        <hr>
                        <div class="fw-semibold">' . h($customer) . '</div>
                        ' . ($order && ((string) $order['billing_email_address'] !== '') ? '<div><a href="mailto:' . h((string) $order['billing_email_address']) . '">' . h((string) $order['billing_email_address']) . '</a></div>' : '') . '
                        ' . ($order && ((string) $order['billing_phone_number'] !== '') ? '<div>' . h((string) $order['billing_phone_number']) . '</div>' : '') . '
                    </div>
                </div>
                <div class="card mb-7">
                    <div class="card-header text-uppercase h6 fg-primary fw-bold mb-0">' . h(lang('Delivery')) . '</div>
                    <div class="card-body small">
                        ' . (($state && ($state['ship_to']['name'] !== '')) ? '<div class="fw-semibold mb-3">' . h(lang(array('string' => 'Sent to {var:1}', 'vars' => array($state['ship_to']['name'])))) . '</div>' : '') . '
                        ' . (($output_parcels !== '') ? '<ul class="ps-5 mb-3">' . $output_parcels . '</ul>' : '') . '
                        ' . $output_window . '
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>' . output_footer();

$liveform->remove_form();
