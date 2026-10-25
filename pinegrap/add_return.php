<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Record a return request for a customer who asked by phone, by e-mail or in
 * the shop (source panel). Opened from the Returns card of the order screen
 * (?order_id=) or from the Returns list, which first asks for the order
 * number.
 *
 * One delivery at a time: its lines with a box for how many go back, the
 * reason, the customer's words and, when the order has an e-mail address, a
 * box that sends the customer the request-received e-mail (off by default).
 * Staff are not held to the customer's timing rules: a delivery whose goods
 * have not left, that has no known date or whose return period has ended is
 * shown with a warning and can still be recorded.
 *
 * The request is saved by pg_return_staff_record() (includes/fn/returns.php),
 * which writes the audit trail and the activity log; staff then land on the
 * request's own screen (view_return.php) to answer it.
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

// Each of these is one value: one sent as an array counts as not given.
pg_request_single_values(array('order_id', 'order_number', 'ship_to_id', 'reason', 'note', 'notify'));

$liveform = new liveform('add_return');
$order_id = is_scalar($_REQUEST['order_id'] ?? null) ? (int) $_REQUEST['order_id'] : 0;

$self_url = PATH . SOFTWARE_DIRECTORY . '/add_return.php';
$list_url = PATH . SOFTWARE_DIRECTORY . '/view_returns.php';
$order_url = PATH . SOFTWARE_DIRECTORY . '/view_order.php?id=' . $order_id;

// Sends the person to another screen with a message there. Nothing typed on
// this form stays in the session: the customer's words must not come back on
// the form of another order.
$leave = function ($form, $url, $message) use ($liveform) {
    $liveform->remove_form();
    $target = new liveform($form);
    $target->mark_error('_error', h($message));
    go($url);
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_token_field();

    // What was typed comes back only with a refusal the form can fix.
    $liveform->add_fields_to_session();

    $ship_to_id = is_scalar($_POST['ship_to_id'] ?? null) ? (int) $_POST['ship_to_id'] : 0;
    $back_url = $self_url . '?order_id=' . $order_id . '&ship_to_id=' . $ship_to_id;

    // The boxes of the delivery's own lines; with no delivery (or before the
    // tables exist) there are none, and the model refuses.
    $lines = (($ship_to_id > 0) && pg_returns_ready()) ? pg_return_order_lines($order_id, $ship_to_id) : array();

    $recorded = pg_return_staff_record(
        $order_id,
        $ship_to_id,
        pg_return_staff_quantities($lines, $_POST),
        isset($_POST['reason']) ? (string) $_POST['reason'] : '',
        isset($_POST['note']) ? (string) $_POST['note'] : '',
        array('notify' => !empty($_POST['notify']), 'user_id' => (int) $user['id']));

    if (!$recorded['ok']) {
        $refusal = pg_return_staff_refusal($recorded['code'], $recorded['error']);

        if ($refusal['to'] === 'list') {
            $leave('view_returns', $list_url, $refusal['message']);
        }

        if ($refusal['to'] === 'order') {
            $leave('view_order', $order_url, $refusal['message']);
        }

        $liveform->mark_error($refusal['field'], h($refusal['message']));
        go($back_url);
    }

    $liveform->remove_form();
    $liveform_return = new liveform('view_return');
    $liveform_return->add_notice(h(pg_return_staff_notice($recorded)));
    go(PATH . SOFTWARE_DIRECTORY . '/view_return.php?id=' . (int) $recorded['id']);
}

// ---------------------------------------------------------------------------
// Without an order: the order number first.
// ---------------------------------------------------------------------------

if ($order_id <= 0) {

    if (!pg_returns_ready()) {
        $leave('view_returns', $list_url, lang('Returns are not installed yet: the database has to be updated first.'));
    }

    $typed = isset($_GET['order_number']) ? trim((string) $_GET['order_number']) : '';

    if ($typed !== '') {
        $number = pg_return_order_number_input($typed);
        $found = null;

        if ($number !== '') {
            $found = db_item("SELECT id, status FROM orders WHERE order_number = '" . e($number) . "' ORDER BY id DESC LIMIT 1");

            // A number that is no order's number may be the order's id.
            if (!$found && (strlen($number) <= 9)) {
                $found = db_item("SELECT id, status FROM orders WHERE id = '" . (int) $number . "'");
            }
        }

        if (!$found) {
            $liveform->mark_error('order_number', h(lang('The order could not be found.')));
        } elseif (!in_array((string) $found['status'], array('complete', 'exported'), true)) {
            $liveform->mark_error('order_number', h(lang('Only a completed order can be returned.')));
        } else {
            $liveform->remove_form();
            go($self_url . '?order_id=' . (int) $found['id']);
        }

        $liveform->assign_field_value('order_number', $typed);
    }

    echo
        pg_page_shell(array(
            'title'               => lang('Record a Return Request'),
            'extra classes'       => 'store',
            'icon'                => 'store',
            'heading'             => lang('Record a Return Request'),
            'heading_description' => lang('For a return the customer asked for by phone, by e-mail or in the shop. Which order are the goods from?'),
            'cancel'              => array('enable' => 'true', 'url' => 'view_returns.php'),
            'breadcrumb'          => array(
                array('label' => lang('All Orders'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_orders.php'),
                array('label' => lang('Returns'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_returns.php'),
                array('label' => lang('Record a Return Request')),
            ),
        )) . '
<main id="content" class="container-fluid">
    ' . $liveform->output_errors() . '
    ' . $liveform->output_notices() . '
    <nav id="button_bar" class="pg-toolbar navigation" aria-label="' . h(lang('Button Bar')) . '">
        <a class="btn btn-sm btn-ghost" href="view_returns.php"><i class="bi bi-arrow-return-left me-1" aria-hidden="true"></i>' . h(lang('All Returns')) . '</a>
    </nav>
    <div class="row g-4">
        <div class="col-12 col-lg-8 col-xxl-9 order-1">
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span class="text-uppercase h5 text-primary fw-bold mb-0">' . h(lang('Order')) . '</span>
                </div>
                <div class="card-body">
                    <form method="get" action="add_return.php" class="row g-3 align-items-end">
                        <div class="col-12 col-md-6">
                            <label for="order_number" class="form-label">' . h(lang('Order Number')) . '</label>
                            ' . $liveform->output_field(array('type' => 'text', 'name' => 'order_number', 'id' => 'order_number', 'maxlength' => '30', 'inputmode' => 'numeric', 'class' => 'form-control', 'autocomplete' => 'off', 'autofocus' => true, 'required' => 'required')) . '
                        </div>
                        <div class="col-12 col-md-auto">
                            <button type="submit" class="btn btn-primary"><i class="bi bi-arrow-right-circle me-1" aria-hidden="true"></i>' . h(lang('Continue')) . '</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</main>' . output_footer();

    $liveform->remove_form();

} else {

    // -----------------------------------------------------------------------
    // The order's form: one delivery at a time.
    // -----------------------------------------------------------------------

    $order = db_item(
        "SELECT id, order_number, status, payment_method, order_date,
            billing_first_name, billing_last_name, billing_email_address, billing_phone_number
        FROM orders
        WHERE id = '" . $order_id . "'");

    if (!$order) {
        $liveform->remove_form();
        output_error(lang('Record not found.'), 404);
    }

    if (!pg_returns_ready()) {
        $leave('view_order', $order_url, lang('Returns are not installed yet: the database has to be updated first.'));
    }

    if (!in_array((string) $order['status'], array('complete', 'exported'), true)) {
        $leave('view_order', $order_url, lang('Only a completed order can be returned.'));
    }

    $today = date('Y-m-d');
    $order_lines = pg_return_order_lines($order_id);
    $states = array();

    foreach ($order_lines as $line) {
        if (!array_key_exists($line['ship_to_id'], $states)) {
            $states[$line['ship_to_id']] = pg_return_delivery_state($order_id, $line['ship_to_id'], $today);
        }
    }

    $deliveries = pg_return_staff_deliveries($order_lines, $states, $today);

    if (empty($deliveries)) {
        $leave('view_order', $order_url, lang('Nothing on this order can be returned: every item is already in a return or was not sent to an address.'));
    }

    // The delivery asked for when it is one of them, else the first.
    $asked = is_scalar($_GET['ship_to_id'] ?? null) ? (int) $_GET['ship_to_id'] : 0;
    $delivery = isset($deliveries[$asked]) ? $deliveries[$asked] : reset($deliveries);
    $ship_to_id = (int) $delivery['ship_to_id'];
    $state = $delivery['state'];
    $timing = $delivery['timing'];

    $order_number = ((string) $order['order_number'] !== '') ? (string) $order['order_number'] : (string) $order_id;
    $customer = trim(trim((string) $order['billing_first_name']) . ' ' . trim((string) $order['billing_last_name']));

    // The lines of the delivery, each with a box up to what is left on it.
    $output_lines = '';

    foreach ($delivery['lines'] as $line) {
        $id = (int) $line['id'];
        $left = (int) $line['returnable'];

        $output_box = ($left > 0)
            ? '<label for="qty_' . $id . '" class="visually-hidden">' . h(lang(array('string' => 'How many to return: {var:1}', 'vars' => array((string) $line['name'])))) . '</label>'
                . $liveform->output_field(array('type' => 'number', 'name' => 'qty_' . $id, 'id' => 'qty_' . $id, 'value' => '0', 'min' => '0', 'max' => (string) $left, 'step' => '1', 'inputmode' => 'numeric', 'class' => 'form-control form-control-sm text-end', 'autocomplete' => 'off'))
            : '<span class="text-body-secondary">&mdash;</span>';

        $output_lines .= '
                                <tr>
                                    <td>' . h((string) $line['name']) . ((($line['code'] !== '') && ($line['code'] !== $line['name'])) ? '<div class="small text-muted">' . h((string) $line['code']) . '</div>' : '') . '</td>
                                    <td class="text-end">' . (int) $line['quantity'] . '</td>
                                    <td class="text-end">' . (((int) $line['held'] > 0) ? (int) $line['held'] : '<span class="text-body-secondary">0</span>') . '</td>
                                    <td style="width:8rem">' . $output_box . '</td>
                                </tr>';
    }

    // The customer's timing rule this delivery breaks: a customer could not
    // ask for it from their account, staff may still record it.
    $breaches = array(
        'not_sent'              => lang('This delivery has not been sent yet. The order can be cancelled instead.'),
        'delivery_date_unknown' => lang('No delivery date is known for this delivery, so the return period cannot be worked out.'),
        'window_closed'         => lang('The return period for this delivery has ended.'),
    );

    $unknown = ($delivery['breach'] === 'delivery_date_unknown');

    $output_breach = isset($breaches[$delivery['breach']]) ? '
    <div class="alert ' . ($unknown ? 'alert-info' : 'alert-warning') . '" role="status"><i class="bi ' . ($unknown ? 'bi-info-circle' : 'bi-exclamation-triangle') . ' me-2" aria-hidden="true"></i>'
        . h($breaches[$delivery['breach']]) . ' ' . h(lang('You can still record the request: staff are not held to the customer\'s timing rules.')) . '</div>' : '';

    $output_confirm = ($delivery['breach'] !== '')
        ? ' data-confirm-content="' . h(lang('The customer could not ask for this return from their account. Record it anyway?')) . '"'
        : '';

    // The e-mail box only when the request-received e-mail can be sent: the
    // order has an address and the site a sender (pg_return_mail_context()).
    $mail_to = trim((string) $order['billing_email_address']);
    $can_mail = ($mail_to !== '') && (strpos($mail_to, '@') !== false) && defined('EMAIL_ADDRESS') && (EMAIL_ADDRESS !== '');

    $output_notify = $can_mail ? '
                        <div class="form-check">
                            ' . $liveform->output_field(array('type' => 'checkbox', 'name' => 'notify', 'id' => 'notify', 'value' => '1', 'class' => 'form-check-input')) . '
                            <label class="form-check-label" for="notify">' . h(lang(array('string' => 'Send the customer the request-received e-mail ({var:1})', 'vars' => array($mail_to)))) . '</label>
                        </div>' : '
                        <p class="small text-muted mb-0">' . h(lang('The customer cannot be told by e-mail: the order has no e-mail address, or the site has no sender address.')) . '</p>';

    // Several deliveries: one form at a time, switched in the toolbar.
    $output_switch = '';

    if (count($deliveries) > 1) {
        $switch_options = array();

        foreach ($deliveries as $choice) {
            $switch_options[$choice['ship_to_id']] = $choice['label'];
        }

        $output_switch = pg_filter_select('ship_to_id', $switch_options, (string) $ship_to_id, array('label' => lang('Delivery'), 'action' => 'add_return.php', 'keep' => array('order_id'), 'id' => 'ship_to_switch'));
    }

    // The delivery's parcels.
    $output_parcels = '';

    foreach ((!empty($state['parcels']) ? $state['parcels'] : array()) as $parcel) {
        $output_parcels .= '<li>' . h($parcel['number'])
            . (($parcel['carrier'] !== '') ? ' · ' . h(pg_return_carrier_label($parcel['carrier'])) : '')
            . (($parcel['delivered'] !== '') ? ' · ' . h(lang(array('string' => 'delivered on {var:1}', 'vars' => array(pg_return_day_text($parcel['delivered']))))) : '')
            . '</li>';
    }

    // When the return period ends, and whether a request recorded today
    // comes within it.
    if ($timing) {
        $output_window = '
                        <div class="row">
                            <span class="col text-muted">' . h($timing['estimated'] ? lang('Delivered (estimated)') : lang('Delivered')) . ':</span>
                            <span class="col text-end">' . h(pg_return_day_text($timing['delivered'])) . '</span>
                        </div>
                        <div class="row">
                            <span class="col text-muted">' . h(lang('Return period ends')) . ':</span>
                            <span class="col text-end">' . h(pg_return_day_text($timing['deadline'])) . '</span>
                        </div>
                        <div class="row">
                            <span class="col text-muted">' . h(lang('Today')) . ':</span>
                            <span class="col text-end">' . ($timing['in_time']
                                ? '<span class="text-success">' . h(lang('within the return period')) . '</span>'
                                : '<span class="text-danger">' . h(lang('after the return period')) . '</span>') . '</span>
                        </div>';
    } else {
        $output_window = '
                        <p class="small text-muted mb-0">' . h(lang('No delivery date is known for this delivery, so the return period cannot be worked out.')) . '</p>';
    }

    echo
        pg_page_shell(array(
            'title'               => lang('Record a Return Request'),
            'extra classes'       => 'store',
            'icon'                => 'store',
            'heading'             => lang('Record a Return Request'),
            'heading_description' => lang('For a return the customer asked for by phone, by e-mail or in the shop. It then follows the same steps as one asked for from their account.'),
            'cancel'              => array('enable' => 'true', 'url' => 'view_order.php?id=' . $order_id),
            'breadcrumb'          => array(
                array('label' => lang('All Orders'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_orders.php'),
                array('label' => lang(array('string' => 'Order {var:1}', 'vars' => array($order_number))), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_order.php?id=' . $order_id),
                array('label' => lang('Record a Return Request')),
            ),
        )) . '
<main id="content" class="container-fluid">
    ' . $liveform->output_errors() . '
    ' . $liveform->output_notices() . '
    <nav id="button_bar" class="pg-toolbar navigation" aria-label="' . h(lang('Button Bar')) . '">
        <a class="btn btn-sm btn-ghost" href="view_order.php?id=' . $order_id . '"><i class="bi bi-receipt me-1" aria-hidden="true"></i>' . h(lang('View Order')) . '</a>
        <a class="btn btn-sm btn-ghost" href="view_returns.php"><i class="bi bi-arrow-return-left me-1" aria-hidden="true"></i>' . h(lang('All Returns')) . '</a>
        <div class="pg-toolbar-grow"></div>
        ' . $output_switch . '
    </nav>
    ' . $output_breach . '
    <form method="post" action="add_return.php" autocomplete="off" submitshortcut="submit_create">
        ' . get_token_field() . '
        <input type="hidden" name="order_id" value="' . $order_id . '">
        <input type="hidden" name="ship_to_id" value="' . $ship_to_id . '">
        <div class="row g-4">
            <div class="col-12 col-lg-8 col-xxl-9 order-1">
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span class="text-uppercase h5 text-primary fw-bold mb-0">' . h(lang('Items to Return')) . '</span>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th scope="col">' . h(lang('Product')) . '</th>
                                        <th scope="col" class="text-end">' . h(lang('Ordered')) . '</th>
                                        <th scope="col" class="text-end">' . h(lang('Already in a return')) . '</th>
                                        <th scope="col" class="text-end">' . h(lang('To return')) . '</th>
                                    </tr>
                                </thead>
                                <tbody>' . $output_lines . '
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span class="text-uppercase h5 text-primary fw-bold mb-0">' . h(lang('Reason for the return')) . '</span>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="reason" class="form-label">' . h(lang('Reason')) . '</label>
                            ' . $liveform->output_field(array('type' => 'select', 'name' => 'reason', 'id' => 'reason', 'options' => pg_return_reason_options(lang('Choose a reason')), 'class' => 'form-select', 'required' => 'required')) . '
                        </div>
                        <div class="mb-3">
                            <label for="note" class="form-label">' . h(lang('The customer\'s note')) . '</label>
                            ' . $liveform->output_field(array('type' => 'textarea', 'name' => 'note', 'id' => 'note', 'rows' => '3', 'maxlength' => PG_RETURN_NOTE_MAX, 'class' => 'form-control')) . '
                            <div id="note_help" class="form-text">' . h(lang('The customer\'s reason in their words. Needed with "Another reason". The customer can see it on their order page and in the e-mail.')) . '</div>
                        </div>
                        ' . $output_notify . '
                    </div>
                </div>
            </div>
            <div class="col-12 col-lg-4 col-xxl-3 order-2">
                <div class="position-sticky" style="top:1rem;">
                    <div class="card mb-4">
                        <div class="card-header text-uppercase h6 text-primary fw-bold mb-0">' . h(lang('Order')) . '</div>
                        <div class="card-body small">
                            <div class="row">
                                <span class="col text-muted">' . h(lang('Order Number')) . ':</span>
                                <span class="col text-end"><a href="view_order.php?id=' . $order_id . '">' . h($order_number) . '</a></span>
                            </div>
                            <div class="row">
                                <span class="col text-muted">' . h(lang('Order Date')) . ':</span>
                                <span class="col text-end">' . h(pg_return_time_text($order['order_date'])) . '</span>
                            </div>
                            <div class="row">
                                <span class="col text-muted">' . h(lang('Payment Method')) . ':</span>
                                <span class="col text-end">' . h((string) $order['payment_method']) . '</span>
                            </div>
                            <hr>
                            <div class="fw-semibold">' . h($customer) . '</div>
                            ' . (($mail_to !== '') ? '<div><a href="mailto:' . h($mail_to) . '">' . h($mail_to) . '</a></div>' : '') . '
                            ' . (((string) $order['billing_phone_number'] !== '') ? '<div>' . h((string) $order['billing_phone_number']) . '</div>' : '') . '
                        </div>
                    </div>
                    <div class="card mb-4">
                        <div class="card-header text-uppercase h6 text-primary fw-bold mb-0">' . h(lang('Delivery')) . '</div>
                        <div class="card-body small">
                            <div class="fw-semibold mb-2">' . h($delivery['label']) . '</div>
                            ' . (($output_parcels !== '') ? '<ul class="ps-3 mb-2">' . $output_parcels . '</ul>' : '') . '
                            ' . $output_window . '
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12 order-3 position-sticky" style="bottom:0; z-index:3;">
                <nav class="buttons navigation text-center mb-4" aria-label="' . h(lang('data edit buttons')) . '">
                    <div class="container">
                        <div class="btn-group flex-wrap justify-content-center">
                            <button type="submit" name="submit_create" value="1" class="btn my-1 btn-primary"' . $output_confirm . '><i class="bi bi-arrow-return-left me-2" aria-hidden="true"></i><span class="btn-text">' . h(lang('Record the Return Request')) . '</span></button>
                        </div>
                    </div>
                </nav>
            </div>
        </div>
    </form>
</main>' . output_footer();

    $liveform->remove_form();
}
