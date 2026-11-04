<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * ERP - one purchase order: changed while it is open or sent, printed,
 * e-mailed to the supplier or marked sent by hand, cancelled and opened
 * again, and turned into purchase invoice drafts as the goods come, with
 * what has come against each line. The work lives in
 * includes/erp/purchase_orders.php.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');
$user = validate_user();
if (!validate_erp_access($user)) {
    exit();
}

require_once(PG_FUNCTIONS_DIR . '/includes/erp/bootstrap.php');
require_once(PG_FUNCTIONS_DIR . '/includes/erp/invoice_form.php');
include_once('liveform.class.php');
$liveform = new liveform('edit_erp_purchase_order');

$list_url = OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/erp_purchase_orders.php';

// The filtered list the order was opened from: the back arrow, the
// breadcrumb and every reload and form of this screen keep it.
$send_to = pg_send_to_url('');
$send_to_query = ($send_to !== '') ? '&send_to=' . urlencode($send_to) : '';
$self_action = h('edit_erp_purchase_order.php' . (($send_to !== '') ? '?send_to=' . urlencode($send_to) : ''));
$order_id = is_scalar($_REQUEST['id'] ?? null) ? (int) $_REQUEST['id'] : 0;
$order = erp_purchase_order($order_id);

if ($order === null) {
    output_error(lang('The purchase order could not be found.') . ' <a href="' . h($list_url) . '">' . lang('Purchase orders') . '</a>');
    exit();
}

$self_url = OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/edit_erp_purchase_order.php?id=' . $order_id . $send_to_query;
$readonly = defined('USER_ERP_READONLY') && USER_ERP_READONLY;

if ($_POST) {
    validate_token_field();

    // Every field of the forms but the lines is one value. One posted as an
    // array counts as not given: (int) read an array account as account 1,
    // (string) the others as "Array" with a notice, into the e-mail too.
    pg_request_single_values(array_merge(erp_invoice_form_single_fields(), array('erp_action', 'mail_to', 'mail_subject', 'mail_message')));

    $action = (string) ($_POST['erp_action'] ?? 'save');

    if ($action === 'save') {
        $liveform->add_fields_to_session();
        $expected_typed = trim((string) $liveform->get_field_value('due_date'));
        $read = erp_invoice_form_read($liveform, $user);

        if (!$read['success']) {
            $liveform->mark_error($read['field'], $read['error']);
            go($self_url);
        }

        $data = $read['data'];
        if ($expected_typed === '') {
            $data['due_date'] = '';
        }

        $result = erp_purchase_order_save($data, $order_id, (int) $user['id']);

        if (!$result['success']) {
            $liveform->mark_error($result['field'] ?? '_error', h($result['error']));
            go($self_url);
        }

        log_activity(lang(array('string' => 'erp purchase order ({var:1}) was changed', 'vars' => $order['full_number'])), $_SESSION['sessionusername']);
        $liveform->remove_form();
        $liveform = new liveform('edit_erp_purchase_order');
        $liveform->add_notice(h(lang(array('string' => 'Purchase order {var:1} saved.', 'vars' => $order['full_number']))));
        go($self_url);
    }

    if ($action === 'sent') {
        $result = erp_purchase_order_mark_sent($order_id);

        if (!$result['success']) {
            $liveform->mark_error('_error', h($result['error']));
            go($self_url);
        }

        log_activity(lang(array('string' => 'erp purchase order ({var:1}) was marked sent', 'vars' => $order['full_number'])), $_SESSION['sessionusername']);
        $liveform->add_notice(h(lang('The purchase order is marked as sent to the supplier.')));
        go($self_url);
    }

    if (in_array($action, array('cancelled', 'open'), true)) {
        $result = erp_purchase_order_set_status($order_id, $action);

        if (!$result['success']) {
            $liveform->mark_error('_error', h($result['error']));
            go($self_url);
        }

        log_activity(lang(array('string' => ($action === 'cancelled') ? 'erp purchase order ({var:1}) was cancelled' : 'erp purchase order ({var:1}) was opened again', 'vars' => $order['full_number'])), $_SESSION['sessionusername']);
        $liveform->add_notice(h(($action === 'cancelled') ? lang('The purchase order was cancelled.') : lang('The purchase order is open again.')));
        go($self_url);
    }

    // What has not come yet becomes a purchase invoice draft; the supplier's
    // bill is checked against it and issued, which moves the stock.
    if ($action === 'invoice') {
        $result = erp_purchase_order_to_invoice($order_id, (int) $user['id']);

        if (!$result['success']) {
            $liveform->mark_error('_error', h($result['error']));
            go($self_url);
        }

        log_activity(lang(array('string' => 'erp purchase order ({var:1}) was turned into a purchase invoice draft', 'vars' => $order['full_number'])), $_SESSION['sessionusername']);
        $liveform_draft = new liveform('edit_erp_invoice_draft');
        $liveform_draft->add_notice(h(lang(array('string' => 'The purchase invoice draft was made from purchase order {var:1}, for what has not come yet. Match it to the supplier\'s bill: the quantities, their number and date. Then issue it.', 'vars' => $order['full_number']))));
        go(OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/edit_erp_invoice_draft.php?id=' . (int) $result['invoice_id']);
    }

    // The page lands on the e-mail card, so the answer is drawn in that card
    // (see $mail_result below), not at the page top out of sight.
    if ($action === 'mail') {
        $mail_to = (string) ($_POST['mail_to'] ?? '');
        $sent = erp_purchase_order_mail_send($order_id, $mail_to, (string) ($_POST['mail_subject'] ?? ''), (string) ($_POST['mail_message'] ?? ''), (int) $user['id']);

        if (!$sent['success']) {
            $_SESSION['software']['erp_purchase_order_mail_draft'][$order_id] = array(
                'to' => mb_substr($mail_to, 0, 500),
                'subject' => mb_substr((string) ($_POST['mail_subject'] ?? ''), 0, 255),
                'message' => mb_substr((string) ($_POST['mail_message'] ?? ''), 0, 5000),
            );
            $addresses = erp_mail_addresses($mail_to);
            $_SESSION['software']['erp_purchase_order_mail_result'][$order_id] = array(
                'type' => 'error',
                'text' => (string) $sent['error'],
                'field' => (($addresses['error'] !== '') || empty($addresses['addresses'])) ? 'mail_to' : '',
            );
            go($self_url . '#erp-mail');
        }

        $_SESSION['software']['erp_purchase_order_mail_result'][$order_id] = array(
            'type' => 'notice',
            'text' => lang('The purchase order was e-mailed to the supplier.'),
            'field' => '',
        );
        go($self_url . '#erp-mail');
    }

    go($self_url);
}

$status_key = (string) $order['status'];
$statuses = erp_purchase_order_statuses();
$editable = erp_purchase_order_editable($order) && !$readonly;
$status = $statuses[$status_key] ?? array($status_key, 'secondary');
$money = function ($kurus) use ($order) {
    return h(erp_money_out_currency((int) $kurus, (string) $order['currency']));
};

// A quantity with its unit's name ("2 kilogram"), as the PDF prints it.
$quantity = function ($value, $unit_code) {
    $unit = trim((string) $unit_code);

    return h(erp_quantity_text($value)) . (($unit !== '') ? ' <span class="fg-secondary">' . h(erp_waybill_unit_label($unit)) . '</span>' : '');
};

// The actions: each is a small form, so a readonly account never sees one.
// A menu row ($class 'menu-item ...') is a full-width form in the overflow menu.
$action_button = function ($action, $label, $icon, $class, $confirm = '') use ($order_id, $self_action) {
    $menu = (strpos($class, 'menu-item') === 0);
    return '<form method="post" action="' . $self_action . '" class="' . ($menu ? 'disable_shortcut' : 'd-inline') . '">' . get_token_field()
        . '<input type="hidden" name="id" value="' . $order_id . '" /><input type="hidden" name="erp_action" value="' . h($action) . '" />'
        . '<button type="submit" class="' . ($menu ? '' : 'btn btn-sm ') . $class . '"' . (($confirm !== '') ? ' data-confirm-content="' . h($confirm) . '"' : '') . '><i class="bi ' . $icon . ' ' . ($menu ? 'me-3' : 'me-1') . '" aria-hidden="true"></i>' . $label . '</button></form>';
};

// What has come against each line, and the invoices it came on.
$progress = erp_purchase_order_order_progress($order);
$remaining_total = 0.0;
$received_any = false;
foreach ($progress as $row) {
    $remaining_total += (float) $row['remaining'];
    $received_any = $received_any || ((float) $row['received'] > 0);
}
$can_invoice = !$readonly && in_array($status_key, array('open', 'sent', 'partial'), true) && ($remaining_total > 0);

// The next step is the toolbar's primary button; the status changes are
// rarer and sit in the three-dots menu.
$output_invoice_button = '';
$status_actions = array();
if (!$readonly) {
    $menu_row = 'menu-item pg-link-body-emphasis';
    if ($can_invoice) {
        $output_invoice_button = $action_button('invoice', lang('Make a purchase invoice draft'), 'bi-receipt', 'btn-solid theme-primary rounded-pill px-5', lang('Make a purchase invoice draft for what has not come yet, at the order\'s prices and dated today?'));
    }
    if ($status_key === 'open') {
        $status_actions[] = $action_button('sent', lang('Mark as sent'), 'bi-send-check', $menu_row, lang('Mark this order as sent to the supplier (by phone, on paper)? Sending it by e-mail does this by itself.'));
    }
    if (in_array($status_key, array('open', 'sent', 'partial'), true)) {
        $status_actions[] = $action_button('cancelled', lang('Cancel the order'), 'bi-x-circle', $menu_row, lang('Cancel this purchase order? It stays on the list, marked cancelled; what has already come in stays on its purchase invoices.'));
    }
    if ($status_key === 'cancelled') {
        $status_actions[] = $action_button('open', lang('Open again'), 'bi-arrow-counterclockwise', $menu_row);
    }
}

// Open or sent: the editor. Otherwise the order as it stands.
$output_body = '';

if ($editable) {
    if (!$liveform->field_in_session('issue_date')) {
        erp_invoice_form_prefill($liveform, array(
            'direction' => 'purchase',
            'account_id' => (int) $order['account_id'],
            'issue_date' => (string) $order['issue_date'],
            'due_date' => (string) $order['expected_date'],
            'currency' => (string) $order['currency'],
            'exchange_rate' => (float) $order['exchange_rate'],
            'supplier_invoice_no' => '',
            'supplier_invoice_date' => '0000-00-00',
            'notes' => (string) $order['notes'],
        ));
    }

    $output_body = '
            <form name="form" action="' . $self_action . '" method="post" autocomplete="off">
                ' . get_token_field() . '
                <input type="hidden" name="id" value="' . $order_id . '" />
                <input type="hidden" name="erp_action" value="save" />
                ' . erp_invoice_form_cards($liveform, array('purchase_order' => true, 'keep_account_id' => (int) $order['account_id'], 'lines' => erp_invoice_form_lines($liveform, erp_purchase_order_editor_rows($order)))) . '
                <nav class="buttons navigation text-center position-sticky mb-7" style="bottom:.5rem;" aria-label="' . h(lang('data edit buttons')) . '">
                    <div class="container">
                        <button type="submit" data-pg-save-shortcut class="btn my-1 btn-solid theme-success"><i class="bi bi-check-circle me-3" aria-hidden="true"></i><span class="pg-btn-label">' . lang(array('string' => 'Save')) . '</span></button>
                    </div>
                </nav>
            </form>
            ' . erp_account_quick_modal();
} else {
    $rows = '';
    foreach (erp_purchase_order_editor_rows($order) as $line) {
        $base = (int) $line['line_total'] - (int) $line['discount_amount'];
        $rows .= '
            <tr>
                <td>' . (int) $line['line_no'] . '</td>
                <td class="text-break">' . h((string) $line['description']) . ((trim((string) ($line['product_name'] ?? '')) !== '' && (trim((string) $line['product_name']) !== trim((string) $line['description']))) ? '<div class="small fg-secondary">' . h((string) $line['product_name']) . '</div>' : '') . '</td>
                <td class="text-end text-nowrap">' . $quantity($line['quantity'], $line['unit_code'] ?? '') . '</td>
                <td class="text-end">' . $money($line['unit_price']) . '</td>
                <td class="text-end">' . (((int) $line['discount_amount'] > 0) ? $money($line['discount_amount']) : '') . '</td>
                <td class="text-end">' . h(erp_percent_text($line['tax_rate'])) . '</td>
                <td class="text-end">' . $money($base + (int) $line['tax_total']) . '</td>
            </tr>';
    }

    $output_body = '
            <div class="card my-7">
                <div class="card-body p-0 table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead><tr><th>#</th><th>' . lang('Description') . '</th><th class="text-end">' . lang('Quantity') . '</th><th class="text-end">' . lang('Unit price') . '</th><th class="text-end">' . lang('Discount') . '</th><th class="text-end">' . h(erp_tax_label('percent')) . '</th><th class="text-end">' . lang('Line total') . '</th></tr></thead>
                        <tbody>' . $rows . '</tbody>
                    </table>
                </div>
                <div class="card-body border-top">
                    <div class="row">
                        <div class="col-12 lg:col-7">' . ((trim((string) $order['notes']) !== '') ? '<div class="small fg-secondary">' . lang('Notes') . '</div><div>' . nl2br(h((string) $order['notes'])) . '</div>' : '') . '</div>
                        <div class="col-12 lg:col-5">
                            <div class="d-flex justify-content-between py-1"><span>' . lang('Subtotal') . '</span><b>' . $money($order['subtotal']) . '</b></div>
                            ' . (((int) $order['discount_total'] !== 0) ? '<div class="d-flex justify-content-between py-1"><span>' . lang('Discount') . '</span><b>&minus;' . $money($order['discount_total']) . '</b></div>' : '') . '
                            <div class="d-flex justify-content-between py-1"><span>' . h(erp_tax_label('tax')) . '</span><b>' . $money($order['tax_total']) . '</b></div>
                            ' . (((int) $order['withholding_total'] !== 0) ? '<div class="d-flex justify-content-between py-1"><span>' . lang('VAT withholding') . '</span><b>&minus;' . $money($order['withholding_total']) . '</b></div>' : '') . '
                            <div class="d-flex justify-content-between py-3 border-top h5 mb-0"><span>' . lang('Total') . '</span><b>' . $money($order['grand_total']) . '</b></div>
                        </div>
                    </div>
                </div>
            </div>';
}

// The goods: per line what was ordered, has come and is on drafts, and the
// purchase invoices made from the order.
$output_goods = '';
$linked_invoices = erp_purchase_order_invoices($order_id);

if (!empty($linked_invoices) || in_array($status_key, array('partial', 'received'), true)) {
    $units = array();
    foreach (erp_purchase_order_lines($order) as $line) {
        $units[(int) $line['line_no']] = (string) ($line['unit_code'] ?? '');
    }

    $goods_rows = '';
    foreach ($progress as $row) {
        $unit = $units[(int) $row['line_no']] ?? '';
        $full = ((float) $row['ordered'] > 0) && ((float) $row['received'] >= (float) $row['ordered']);
        $goods_rows .= '
            <tr>
                <td>' . (int) $row['line_no'] . '</td>
                <td class="text-break">' . h($row['description']) . '</td>
                <td class="text-end text-nowrap">' . $quantity($row['ordered'], $unit) . '</td>
                <td class="text-end text-nowrap' . ($full ? ' fg-success fw-semibold' : '') . '">' . $quantity($row['received'], $unit) . '</td>
                <td class="text-end text-nowrap fg-secondary">' . (((float) $row['drafted'] > 0) ? $quantity($row['drafted'], $unit) : '—') . '</td>
                <td class="text-end text-nowrap' . (((float) $row['open'] > 0) ? ' fw-semibold' : ' fg-secondary') . '">' . $quantity($row['open'], $unit) . '</td>
            </tr>';
    }

    $invoice_statuses = array(
        'draft' => array(lang('Draft'), 'secondary'),
        'issued' => array(lang('Issued'), 'primary'),
        'partially_paid' => array(lang('Partly paid'), 'warning'),
        'paid' => array(lang('Paid'), 'success'),
        'cancelled' => array(lang('Cancelled'), 'inverse'),
    );
    $invoice_rows = '';
    foreach ($linked_invoices as $linked) {
        $is_draft = ((string) $linked['status'] === 'draft');
        $linked_status = $invoice_statuses[(string) $linked['status']] ?? array((string) $linked['status'], 'secondary');
        $invoice_rows .= '
            <tr' . (((string) $linked['status'] === 'cancelled') ? ' class="opacity-75"' : '') . '>
                <td class="text-nowrap"><a class="pg-link-body-emphasis fw-semibold" href="' . ($is_draft ? 'edit_erp_invoice_draft.php' : 'edit_erp_invoice.php') . '?id=' . (int) $linked['id'] . '">'
                    . h($is_draft ? lang('Draft Invoice') : (string) $linked['full_number']) . '</a></td>
                <td class="text-nowrap">' . h(prepare_form_data_for_output((string) $linked['issue_date'], 'date')) . '</td>
                <td class="text-break">' . h((string) $linked['supplier_invoice_no']) . '</td>
                <td class="text-end text-nowrap">' . h(erp_money_out_currency((int) $linked['grand_total'], (string) $linked['currency'])) . '</td>
                <td><span class="badge theme-' . h($linked_status[1]) . '">' . h($linked_status[0]) . '</span></td>
            </tr>';
    }

    $output_goods = '
            <div class="card my-7" id="erp-goods">
                <div class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">' . lang('Goods received') . '</div>
                <div class="card-body p-0 table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead><tr><th>#</th><th>' . lang('Description') . '</th><th class="text-end">' . lang('Ordered') . '</th><th class="text-end">' . lang('Received') . '</th><th class="text-end">' . lang('On drafts') . '</th><th class="text-end">' . lang('Still to come') . '</th></tr></thead>
                        <tbody>' . $goods_rows . '</tbody>
                    </table>
                </div>
                <div class="form-text px-5 pb-3">' . lang('Received is what the issued purchase invoices made from this order carry; a cancelled invoice counts for nothing. Drafts count once they are issued.') . '</div>'
                . (($invoice_rows !== '') ? '
                <div class="card-body p-0 border-top table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead><tr><th>' . lang('Purchase invoice') . '</th><th>' . lang('Date') . '</th><th>' . lang('Supplier Invoice Number') . '</th><th class="text-end">' . lang('Total') . '</th><th>' . lang('Status') . '</th></tr></thead>
                        <tbody>' . $invoice_rows . '</tbody>
                    </table>
                </div>' : '') . '
            </div>';
}

// E-mail: the form and every e-mail the order already went out in.
$output_mail = '';
$output_mail_button = '';
$output_mail_result = '';

if (function_exists('erp_mail_ready') && erp_mail_ready()) {
    $defaults = erp_purchase_order_mail_defaults($order);
    $draft = $_SESSION['software']['erp_purchase_order_mail_draft'][$order_id] ?? null;
    unset($_SESSION['software']['erp_purchase_order_mail_draft'][$order_id]);
    $mail_result = $_SESSION['software']['erp_purchase_order_mail_result'][$order_id] ?? null;
    unset($_SESSION['software']['erp_purchase_order_mail_result'][$order_id]);
    $mail_to_invalid = is_array($mail_result) && (($mail_result['field'] ?? '') === 'mail_to');

    if (is_array($draft)) {
        $defaults = array_merge($defaults, $draft);
    }

    $mail_statuses = erp_mail_statuses();
    $mail_rows = '';

    foreach (erp_mail_history('purchase_order', $order_id) as $mail) {
        $mail_status = $mail_statuses[(string) $mail['status']] ?? array((string) $mail['status'], 'secondary');
        $mail_rows .= '
            <tr>
                <td class="text-nowrap">' . h(prepare_form_data_for_output(date('Y-m-d H:i:s', (int) $mail['created_at']), 'date and time')) . '</td>
                <td class="text-break">' . h((string) $mail['to_address'])
                    . (((string) $mail['error'] !== '') ? '<div class="small fg-danger">' . h((string) $mail['error']) . '</div>' : '') . '</td>
                <td>' . h(((string) $mail['created_by_name'] !== '') ? (string) $mail['created_by_name'] : '—') . '</td>
                <td><span class="badge theme-' . h($mail_status[1]) . '">' . h($mail_status[0]) . '</span></td>
            </tr>';
    }

    // The same refusal as erp_purchase_order_mail_send(): a cancelled order
    // and one whose goods have all come are not sent to the supplier again.
    $can_mail = !$readonly && (erp_purchase_order_status_refusal($order, 'sent') === '');
    $output_mail_form = '';

    if ($can_mail) {
        $output_mail_button = '<a class="btn btn-sm btn-text theme-secondary" href="#erp-mail"><i class="bi bi-envelope me-1" aria-hidden="true"></i>' . lang('E-mail') . '</a>';
        $output_mail_form = '
            <form name="mail_form" action="' . $self_action . '" method="post" class="row g-3" data-erp-send-once>
                ' . get_token_field() . '
                <input type="hidden" name="id" value="' . $order_id . '" />
                <input type="hidden" name="erp_action" value="mail" />
                <div class="col-12 md:col-6 lg:col-12">
                    <label class="form-label" for="mail_to">' . lang('Recipient e-mail') . '</label>
                    <input type="text" class="form-control' . ($mail_to_invalid ? ' is-invalid' : '') . '" id="mail_to" name="mail_to" value="' . h((string) $defaults['to']) . '" maxlength="500" inputmode="email" spellcheck="false" autocapitalize="off" autocomplete="off" required' . ($mail_to_invalid ? ' aria-invalid="true" aria-describedby="erp-mail-result"' : '') . ' />
                    <div class="form-text">' . (((string) $defaults['to'] === '') ? lang('The account has no e-mail address. Type one, or add it to the account.') : lang('More than one address: separate them with commas.')) . '</div>
                </div>
                <div class="col-12 md:col-6 lg:col-12">
                    <label class="form-label" for="mail_subject">' . lang('Subject') . '</label>
                    <input type="text" class="form-control" id="mail_subject" name="mail_subject" value="' . h((string) $defaults['subject']) . '" maxlength="255" />
                </div>
                <div class="col-12">
                    <label class="form-label" for="mail_message">' . lang('Message') . '</label>
                    <textarea class="form-control" id="mail_message" name="mail_message" rows="3" maxlength="5000">' . h((string) $defaults['message']) . '</textarea>
                    <div class="form-text"><i class="bi bi-paperclip" aria-hidden="true"></i> ' . lang('The purchase order PDF is attached. Once it has gone, the order is marked as sent.') . '</div>
                </div>
                <div class="col-12 d-flex flex-wrap gap-3 align-items-center">
                    <button type="submit" class="btn btn-sm btn-solid theme-primary"><i class="bi bi-envelope me-3" aria-hidden="true"></i>' . lang('Send the e-mail') . '</button>
                    <span class="small fg-secondary" role="status" aria-live="polite" data-erp-send-once-status></span>
                </div>
            </form>';
    }

    if (is_array($mail_result)) {
        $mail_error = (($mail_result['type'] ?? '') === 'error');
        $output_mail_result = '<div id="erp-mail-result" class="alert theme-' . ($mail_error ? 'danger' : 'success') . ' d-flex align-items-start gap-3" role="' . ($mail_error ? 'alert' : 'status') . '">'
            . '<i class="bi ' . ($mail_error ? 'bi-exclamation-triangle-fill' : 'bi-check-circle-fill') . '" aria-hidden="true"></i><div class="flex-grow-1 min-w-0">' . h((string) ($mail_result['text'] ?? '')) . '</div></div>';
    }

    if (($output_mail_form !== '') || ($mail_rows !== '') || ($output_mail_result !== '')) {
        $output_mail = '
            <div class="card my-7" id="erp-mail">
                <div class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">' . lang('E-mail to the supplier') . '</div>
                <div class="card-body">' . $output_mail_result . $output_mail_form . '</div>'
                . (($mail_rows !== '') ? '
                <div class="card-body p-0 border-top table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>' . lang('Date') . '</th><th>' . lang('Recipient') . '</th><th>' . lang('Sent by') . '</th><th>' . lang('Status') . '</th></tr></thead>
                        <tbody>' . $mail_rows . '</tbody>
                    </table>
                </div>' : '') . '
            </div>';
    }
}


// A copy is always a new open order, whatever became of this one.
$output_copy_button = (!$readonly && erp_purchase_orders_ready())
    ? '<a class="menu-item pg-link-body-emphasis" href="add_erp_purchase_order.php?copy=' . $order_id . '"><i class="bi bi-files me-3" aria-hidden="true"></i>' . lang('Copy') . '</a>'
    : '';

// The same gate as the audit trail screen: the ERP settings managers and the accountant.
$output_audit_button = (function_exists('erp_audit_ready') && erp_audit_ready() && ((((int) $user['role'] < 3) || !empty($user['manage_erp_settings']) || $readonly)))
    ? '<a class="menu-item pg-link-body-emphasis" href="erp_audit.php?type=purchase_order&amp;id=' . $order_id . '"><i class="bi bi-shield-check me-3" aria-hidden="true"></i>' . lang('Audit trail') . '</a>'
    : '';

// Who the order went to and when, beside the status in the page body: the
// heading's description is hidden below 1024px, and an order that can no
// longer be edited shows its supplier nowhere else.
$facts = array(
    '<span class="fg-secondary me-2">' . lang('Status') . '</span><span class="badge theme-' . h($status[1]) . '">' . h($status[0]) . '</span>',
    '<span class="fg-secondary me-2">' . lang('Supplier') . '</span><a class="pg-link-body-emphasis fw-semibold" href="edit_erp_account.php?id=' . (int) $order['account_id'] . '">' . h((string) $order['account_title']) . '</a>',
    '<span class="fg-secondary me-2">' . lang('Order Date') . '</span>' . h(prepare_form_data_for_output((string) $order['issue_date'], 'date', false)),
);
if ((string) $order['expected_date'] > '0000-00-00') {
    $facts[] = '<span class="fg-secondary me-2">' . lang('Expected') . '</span>' . h(prepare_form_data_for_output((string) $order['expected_date'], 'date', false));
}
if ((int) $order['sent_at'] > 0) {
    $facts[] = '<span class="fg-secondary me-2">' . lang('Sent') . '</span>' . h(prepare_form_data_for_output(date('Y-m-d H:i:s', (int) $order['sent_at']), 'date and time', false));
}

// One toolbar: the next step first, the PDF and the e-mail beside it, the
// facts in the grow slot and the rarer things in the three-dots menu.
$pdf_link = function ($class) use ($order_id) {
    return '<a class="' . $class . '" href="get_erp_purchase_order_pdf.php?id=' . $order_id . '" target="_blank" rel="noopener"><i class="bi bi-file-earmark-pdf me-1" aria-hidden="true"></i>' . lang('PDF') . '</a>';
};
$toolbar_more = array_values(array_filter(array(
    '<a class="menu-item pg-link-body-emphasis" href="get_erp_purchase_order_pdf.php?id=' . $order_id . '&amp;download=1"><i class="bi bi-download me-3" aria-hidden="true"></i>' . lang('Download') . '</a>',
    $output_copy_button,
    $output_audit_button,
)));
if ($status_actions) {
    $toolbar_more[] = '<hr class="menu-divider">';
    $toolbar_more = array_merge($toolbar_more, $status_actions);
}

$output_toolbar = pg_screen_toolbar(array(
    'primary' => ($output_invoice_button !== '') ? $output_invoice_button : $pdf_link('btn btn-sm btn-solid theme-primary rounded-pill px-5'),
    'secondary' => (($output_invoice_button !== '') ? $pdf_link('btn btn-sm btn-text theme-secondary') : '') . $output_mail_button,
    'grow' => '<div class="small d-flex flex-wrap align-items-center column-gap-5 row-gap-1"><span>' . implode('</span><span>', $facts) . '</span></div>',
    'overflow' => '<div class="pg-menu-wrap">
                <button type="button" class="btn btn-sm btn-text theme-secondary" data-bs-toggle="menu" data-bs-display="static" data-bs-placement="bottom-end" aria-expanded="false" title="' . h(lang('More')) . '" aria-label="' . h(lang('More')) . '"><i class="bi bi-three-dots-vertical" aria-hidden="true"></i></button>
                <ul class="menu pg-menu-end">
                    <li>' . implode('</li>
                    <li>', $toolbar_more) . '</li>
                </ul>
            </div>',
));

echo
pg_page_shell([
    'title' => h((string) $order['full_number']),
    'extra classes' => 'erp erp_invoices',
    'icon' => 'erp',
    'heading' => h(lang(array('string' => 'Purchase order {var:1}', 'vars' => (string) $order['full_number']))) . ' <span class="badge theme-' . h($status[1]) . ' ms-1 align-middle fs-md">' . h($status[0]) . '</span>',
    // The back arrow is named for where it goes: "Cancel" read the same as
    // the button that cancels the order.
    'cancel' => array('enable' => 'true', 'url' => pg_send_to_url('erp_purchase_orders.php')) + array('title' => lang('Purchase orders')),
    'breadcrumb' => array(
        array('label' => lang('Purchase orders'), 'url' => pg_send_to_list_url($list_url)),
        array('label' => (string) $order['full_number']),
    ),
]) . '
<main id="content" class="container-fluid">
    <div class="row">
        <div class="col-12">
            ' . $liveform->output_errors() . '
            ' . $liveform->get_warnings() . '
            ' . $liveform->output_notices() . '
            ' . (($output_mail === '') ? $output_mail_result : '') . '

            ' . $output_toolbar . '
        </div>

        <!--
            The form column: the order and the goods received against it (a
            per-line table as wide as the order\'s). The e-mail card goes in the
            status column after it, so a narrow screen puts it below the order.
            That column is not sticky: it holds a form, can be taller than the
            window and is reached by the #erp-mail jump, and a sticky box taller
            than the window hides its own end. The line editor needs about
            1,060px, so an open order splits only from 2xl; below that the card
            follows the editor. Without an e-mail card the order takes the width.
        -->
        <div class="col-12 ' . (($output_mail === '') ? '' : ($editable ? '' : 'lg:col-8 ') . '2xl:col-9 ') . 'order-1">
            ' . ((!$editable && !$readonly && in_array($status_key, array('partial', 'received'), true)) ? '<div class="form-text mb-3">' . lang('Goods have come in against this order, so its lines are no longer changed; a new order asks for more.') . '</div>' : '') . '
            ' . ((!$editable && !$readonly && ($status_key === 'cancelled')) ? '<div class="form-text mb-3">' . ($received_any
                ? lang('A cancelled order is not changed. Goods have already come in against it, so "Open again" brings back its received status and its lines stay as they are.')
                : lang('A cancelled order is not changed; "Open again" makes it editable.')) . '</div>' : '') . '
            ' . $output_body . '
            ' . $output_goods . '
        </div>
        ' . (($output_mail !== '') ? '<div class="col-12 ' . ($editable ? '' : 'lg:col-4 ') . '2xl:col-3 order-2">
            ' . $output_mail . '
        </div>' : '') . '
    </div>
</main>
<script>
(function () {
    // Send the e-mail is sent once: building the PDF and sending the mail run
    // inside the request and can take longer than the 3 seconds the window
    // guard covers, and a second press then sent the same e-mail again. The
    // button is never disabled; a submit a handler cancelled does not count.
    var sent = [];
    window.addEventListener("submit", function (event) {
        var form = event.target;
        if (!form || !form.hasAttribute || !form.hasAttribute("data-erp-send-once")) { return; }
        if (sent.indexOf(form) !== -1) { event.preventDefault(); return; }
        if (event.defaultPrevented) { return; }
        sent.push(form);
        form.setAttribute("aria-busy", "true");
        var status = form.querySelector("[data-erp-send-once-status]");
        if (status) { status.textContent = "' . escape_javascript(lang('Working on it. Wait for the answer before leaving this page.')) . '"; }
    });
    window.addEventListener("pageshow", function (event) {
        if (!event.persisted) { return; }
        sent.forEach(function (form) {
            form.removeAttribute("aria-busy");
            var status = form.querySelector("[data-erp-send-once-status]");
            if (status) { status.textContent = ""; }
        });
        sent = [];
    });
})();
</script>
' .
output_footer();

$liveform->remove_form();
