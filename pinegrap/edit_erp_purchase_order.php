<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * ERP - one purchase order: changed while it is open or sent, printed,
 * e-mailed to the supplier or marked sent by hand, cancelled and opened
 * again. The work lives in includes/erp/purchase_orders.php.
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
$order_id = (int) ($_REQUEST['id'] ?? 0);
$order = erp_purchase_order($order_id);

if ($order === null) {
    output_error(lang('The purchase order could not be found.') . ' <a href="' . h($list_url) . '">' . lang('Purchase orders') . '</a>');
    exit();
}

$self_url = OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/edit_erp_purchase_order.php?id=' . $order_id;
$readonly = defined('USER_ERP_READONLY') && USER_ERP_READONLY;

if ($_POST) {
    validate_token_field();

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
            $liveform->mark_error($result['field'] ?? '_error', $result['error']);
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

    if ($action === 'mail') {
        $sent = erp_purchase_order_mail_send($order_id, (string) ($_POST['mail_to'] ?? ''), (string) ($_POST['mail_subject'] ?? ''), (string) ($_POST['mail_message'] ?? ''), (int) $user['id']);

        if (!$sent['success']) {
            $_SESSION['software']['erp_purchase_order_mail_draft'][$order_id] = array(
                'to' => mb_substr((string) ($_POST['mail_to'] ?? ''), 0, 500),
                'subject' => mb_substr((string) ($_POST['mail_subject'] ?? ''), 0, 255),
                'message' => mb_substr((string) ($_POST['mail_message'] ?? ''), 0, 5000),
            );
            $liveform->mark_error('_error', h($sent['error']));
            go($self_url . '#erp-mail');
        }

        $liveform->add_notice(h(lang('The purchase order was e-mailed to the supplier.')));
        go($self_url . '#erp-mail');
    }

    go($self_url);
}

$status_key = (string) $order['status'];
$statuses = erp_purchase_order_statuses();
$editable = erp_purchase_order_editable($order) && !$readonly;
$money = function ($kurus) use ($order) {
    return h(erp_money_out_currency((int) $kurus, (string) $order['currency']));
};

// The actions: each is a small form, so a readonly account never sees one.
$action_button = function ($action, $label, $icon, $class, $confirm = '') use ($order_id) {
    return '<form method="post" action="edit_erp_purchase_order.php" class="d-inline">' . get_token_field()
        . '<input type="hidden" name="id" value="' . $order_id . '" /><input type="hidden" name="erp_action" value="' . h($action) . '" />'
        . '<button type="submit" class="btn btn-sm ' . $class . '"' . (($confirm !== '') ? ' data-confirm-content="' . h($confirm) . '"' : '') . ' data-loading-content="' . lang(array('string' => 'Please Wait')) . '"><i class="bi ' . $icon . ' me-1" aria-hidden="true"></i>' . $label . '</button></form>';
};

$output_actions = '';
if (!$readonly) {
    if ($status_key === 'open') {
        $output_actions .= $action_button('sent', lang('Mark as sent'), 'bi-send-check', 'btn-outline-primary', lang('Mark this order as sent to the supplier (by phone, on paper)? Sending it by e-mail does this by itself.'));
    }
    if (in_array($status_key, array('open', 'sent', 'partial'), true)) {
        $output_actions .= $action_button('cancelled', lang('Cancel'), 'bi-x-circle', 'btn-outline-secondary', lang('Cancel this purchase order? It stays on the list, marked cancelled; what has already come in stays on its purchase invoices.'));
    }
    if ($status_key === 'cancelled') {
        $output_actions .= $action_button('open', lang('Open again'), 'bi-arrow-counterclockwise', 'btn-outline-secondary');
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
            <form name="form" action="edit_erp_purchase_order.php" method="post" autocomplete="off">
                ' . get_token_field() . '
                <input type="hidden" name="id" value="' . $order_id . '" />
                <input type="hidden" name="erp_action" value="save" />
                ' . erp_invoice_form_cards($liveform, array('purchase_order' => true, 'lines' => erp_invoice_form_lines($liveform, erp_purchase_order_editor_rows($order)))) . '
                <nav class="buttons navigation text-center position-sticky mb-4" style="bottom:.5rem;" aria-label="data edit buttons">
                    <div class="container">
                        <button type="submit" class="btn my-1 btn-success" data-loading-content="' . lang(array('string' => 'Saving')) . '"><i class="bi bi-check-circle me-2" aria-hidden="true"></i><span class="btn-text">' . lang(array('string' => 'Save')) . '</span></button>
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
                <td>' . h((string) $line['description']) . ((trim((string) ($line['product_name'] ?? '')) !== '' && (trim((string) $line['product_name']) !== trim((string) $line['description']))) ? '<div class="small text-body-secondary">' . h((string) $line['product_name']) . '</div>' : '') . '</td>
                <td class="text-end">' . h(erp_quantity_text($line['quantity'])) . '</td>
                <td class="text-end">' . $money($line['unit_price']) . '</td>
                <td class="text-end">' . (((int) $line['discount_amount'] > 0) ? $money($line['discount_amount']) : '') . '</td>
                <td class="text-end">' . h(erp_percent_text($line['tax_rate'])) . '</td>
                <td class="text-end">' . $money($base + (int) $line['tax_total']) . '</td>
            </tr>';
    }

    $output_body = '
            <div class="card my-4">
                <div class="card-body p-0 table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead><tr><th>#</th><th>' . lang('Description') . '</th><th class="text-end">' . lang('Quantity') . '</th><th class="text-end">' . lang('Unit price') . '</th><th class="text-end">' . lang('Discount') . '</th><th class="text-end">' . h(erp_tax_label('percent')) . '</th><th class="text-end">' . lang('Line total') . '</th></tr></thead>
                        <tbody>' . $rows . '</tbody>
                    </table>
                </div>
                <div class="card-body border-top">
                    <div class="row">
                        <div class="col-12 col-lg-7">' . ((trim((string) $order['notes']) !== '') ? '<div class="small text-body-secondary">' . lang('Notes') . '</div><div>' . nl2br(h((string) $order['notes'])) . '</div>' : '') . '</div>
                        <div class="col-12 col-lg-5">
                            <div class="d-flex justify-content-between py-1"><span>' . lang('Subtotal') . '</span><b>' . $money($order['subtotal']) . '</b></div>
                            ' . (((int) $order['discount_total'] !== 0) ? '<div class="d-flex justify-content-between py-1"><span>' . lang('Discount') . '</span><b>&minus;' . $money($order['discount_total']) . '</b></div>' : '') . '
                            <div class="d-flex justify-content-between py-1"><span>' . h(erp_tax_label('tax')) . '</span><b>' . $money($order['tax_total']) . '</b></div>
                            ' . (((int) $order['withholding_total'] !== 0) ? '<div class="d-flex justify-content-between py-1"><span>' . lang('VAT withholding') . '</span><b>&minus;' . $money($order['withholding_total']) . '</b></div>' : '') . '
                            <div class="d-flex justify-content-between py-2 border-top h5 mb-0"><span>' . lang('Total') . '</span><b>' . $money($order['grand_total']) . '</b></div>
                        </div>
                    </div>
                </div>
            </div>';
}

// E-mail: the form and every e-mail the order already went out in.
$output_mail = '';
$output_mail_button = '';

if (function_exists('erp_mail_ready') && erp_mail_ready()) {
    $defaults = erp_purchase_order_mail_defaults($order);
    $draft = $_SESSION['software']['erp_purchase_order_mail_draft'][$order_id] ?? null;
    unset($_SESSION['software']['erp_purchase_order_mail_draft'][$order_id]);

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
                    . (((string) $mail['error'] !== '') ? '<div class="small text-danger">' . h((string) $mail['error']) . '</div>' : '') . '</td>
                <td>' . h(((string) $mail['created_by_name'] !== '') ? (string) $mail['created_by_name'] : '—') . '</td>
                <td><span class="badge text-bg-' . h($mail_status[1]) . '">' . h($mail_status[0]) . '</span></td>
            </tr>';
    }

    $can_mail = !$readonly && ($status_key !== 'cancelled');
    $output_mail_form = '';

    if ($can_mail) {
        $output_mail_button = '<a class="btn btn-sm btn-outline-secondary" href="#erp-mail"><i class="bi bi-envelope me-1" aria-hidden="true"></i>' . lang('E-mail') . '</a>';
        $output_mail_form = '
            <form name="mail_form" action="edit_erp_purchase_order.php" method="post" class="row g-2">
                ' . get_token_field() . '
                <input type="hidden" name="id" value="' . $order_id . '" />
                <input type="hidden" name="erp_action" value="mail" />
                <div class="col-12 col-md-6">
                    <label class="form-label" for="mail_to">' . lang('Recipient e-mail') . '</label>
                    <input type="text" class="form-control" id="mail_to" name="mail_to" value="' . h((string) $defaults['to']) . '" maxlength="500" autocomplete="off" required />
                    <div class="form-text">' . (((string) $defaults['to'] === '') ? lang('The account has no e-mail address. Type one, or add it to the account.') : lang('More than one address: separate them with commas.')) . '</div>
                </div>
                <div class="col-12 col-md-6">
                    <label class="form-label" for="mail_subject">' . lang('Subject') . '</label>
                    <input type="text" class="form-control" id="mail_subject" name="mail_subject" value="' . h((string) $defaults['subject']) . '" maxlength="255" />
                </div>
                <div class="col-12">
                    <label class="form-label" for="mail_message">' . lang('Message') . '</label>
                    <textarea class="form-control" id="mail_message" name="mail_message" rows="3" maxlength="5000">' . h((string) $defaults['message']) . '</textarea>
                    <div class="form-text"><i class="bi bi-paperclip" aria-hidden="true"></i> ' . lang('The purchase order PDF is attached. Once it has gone, the order is marked as sent.') . '</div>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-sm btn-primary" data-loading-content="' . lang(array('string' => 'Please Wait')) . '"><i class="bi bi-envelope me-2" aria-hidden="true"></i>' . lang('Send the e-mail') . '</button>
                </div>
            </form>';
    }

    if (($output_mail_form !== '') || ($mail_rows !== '')) {
        $output_mail = '
            <div class="card my-4" id="erp-mail">
                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">' . lang('E-mail to the supplier') . '</div>
                <div class="card-body">' . $output_mail_form . '</div>'
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

$status = $statuses[$status_key] ?? array($status_key, 'secondary');

// A copy is always a new open order, whatever became of this one.
$output_copy_button = (!$readonly && erp_purchase_orders_ready())
    ? '<a class="btn btn-sm btn-outline-secondary" href="add_erp_purchase_order.php?copy=' . $order_id . '" data-loading-content="' . lang(array('string' => 'Loading')) . '"><i class="bi bi-files me-1" aria-hidden="true"></i>' . lang('Copy') . '</a>'
    : '';

// The same gate as the audit trail screen: the ERP settings managers and the accountant.
$output_audit_button = (function_exists('erp_audit_ready') && erp_audit_ready() && ((((int) $user['role'] < 3) || !empty($user['manage_erp_settings']) || $readonly)))
    ? '<a class="btn btn-sm btn-outline-secondary" href="erp_audit.php?type=purchase_order&amp;id=' . $order_id . '"><i class="bi bi-shield-check me-1" aria-hidden="true"></i>' . lang('Audit trail') . '</a>'
    : '';

$heading_parts = array((string) $order['account_title']);
if ((string) $order['expected_date'] > '0000-00-00') {
    $heading_parts[] = lang(array('string' => 'Expected {var:1}', 'vars' => prepare_form_data_for_output((string) $order['expected_date'], 'date', false)));
}
if ((int) $order['sent_at'] > 0) {
    $heading_parts[] = lang(array('string' => 'Sent {var:1}', 'vars' => prepare_form_data_for_output(date('Y-m-d H:i:s', (int) $order['sent_at']), 'date and time', false)));
}

echo
pg_page_shell([
    'title' => (string) $order['full_number'],
    'extra classes' => 'erp erp_invoices',
    'icon' => 'erp',
    'heading' => h(lang(array('string' => 'Purchase order {var:1}', 'vars' => (string) $order['full_number']))) . ' <span class="badge text-bg-' . h($status[1]) . ' ms-1 align-middle fs-6">' . h($status[0]) . '</span>',
    'heading_description' => implode(' · ', array_filter($heading_parts, 'strlen')),
    'cancel' => array('enable' => 'true', 'url' => 'erp_purchase_orders.php'),
    'breadcrumb' => array(
        array('label' => lang('Purchase orders'), 'url' => $list_url),
        array('label' => (string) $order['full_number']),
    ),
]) . '
<main id="content" class="container-fluid">
    <div class="row">
        <div class="col-12">
            ' . $liveform->output_errors() . '
            ' . $liveform->get_warnings() . '
            ' . $liveform->output_notices() . '

            <nav id="button_bar" class="pg-toolbar navigation d-flex flex-wrap gap-1" aria-label="' . lang('Button Bar') . '">
                <a class="btn btn-sm btn-outline-secondary" href="get_erp_purchase_order_pdf.php?id=' . $order_id . '" target="_blank" rel="noopener"><i class="bi bi-file-earmark-pdf me-1" aria-hidden="true"></i>' . lang('PDF') . '</a>
                <a class="btn btn-sm btn-outline-secondary" href="get_erp_purchase_order_pdf.php?id=' . $order_id . '&amp;download=1"><i class="bi bi-download me-1" aria-hidden="true"></i>' . lang('Download') . '</a>
                ' . $output_mail_button . '
                ' . $output_copy_button . '
                ' . $output_audit_button . '
                <span class="ms-auto d-flex flex-wrap gap-1">' . $output_actions . '</span>
            </nav>

            ' . ((!$editable && !$readonly && in_array($status_key, array('partial', 'received'), true)) ? '<div class="form-text mb-2">' . lang('Goods have come in against this order, so its lines are no longer changed; a new order asks for more.') . '</div>' : '') . '
            ' . ((!$editable && !$readonly && ($status_key === 'cancelled')) ? '<div class="form-text mb-2">' . lang('A cancelled order is not changed; "Open again" makes it editable.') . '</div>' : '') . '
            ' . $output_body . '
            ' . $output_mail . '
        </div>
    </div>
</main>' .
output_footer();

$liveform->remove_form();
