<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * ERP - one quote: changed while it is open, printed, e-mailed, marked
 * accepted or rejected, and turned into an invoice draft. The work lives in
 * includes/erp/quotes.php.
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
$liveform = new liveform('edit_erp_quote');

$list_url = OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/erp_quotes.php';
$quote_id = (int) ($_REQUEST['id'] ?? 0);
$quote = erp_quote($quote_id);

if ($quote === null) {
    output_error(lang('The quote could not be found.') . ' <a href="' . h($list_url) . '">' . lang('Quotes') . '</a>');
    exit();
}

$self_url = OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/edit_erp_quote.php?id=' . $quote_id;
$readonly = defined('USER_ERP_READONLY') && USER_ERP_READONLY;

if ($_POST) {
    validate_token_field();

    $action = (string) ($_POST['erp_action'] ?? 'save');

    if ($action === 'save') {
        $liveform->add_fields_to_session();
        $valid_typed = trim((string) $liveform->get_field_value('due_date'));
        $read = erp_invoice_form_read($liveform, $user);

        if (!$read['success']) {
            $liveform->mark_error($read['field'], $read['error']);
            go($self_url);
        }

        $data = $read['data'];
        if ($valid_typed === '') {
            $data['due_date'] = '';
        }

        $result = erp_quote_save($data, $quote_id, (int) $user['id']);

        if (!$result['success']) {
            $liveform->mark_error($result['field'] ?? '_error', $result['error']);
            go($self_url);
        }

        log_activity(lang(array('string' => 'erp quote ({var:1}) was changed', 'vars' => $quote['full_number'])), $_SESSION['sessionusername']);
        $liveform->remove_form();
        $liveform = new liveform('edit_erp_quote');
        $liveform->add_notice(h(lang(array('string' => 'Quote {var:1} saved.', 'vars' => $quote['full_number']))));
        go($self_url);
    }

    if (in_array($action, array('open', 'accepted', 'rejected', 'cancelled'), true)) {
        $result = erp_quote_set_status($quote_id, $action, (int) $user['id']);

        if (!$result['success']) {
            $liveform->mark_error('_error', h($result['error']));
            go($self_url);
        }

        $statuses = erp_quote_statuses();
        log_activity(lang(array('string' => 'erp quote ({var:1}) was marked {var:2}', 'vars' => array($quote['full_number'], $statuses[$action][0]))), $_SESSION['sessionusername']);
        $liveform->add_notice(h(lang(array('string' => 'The quote is now: {var:1}.', 'vars' => $statuses[$action][0]))));
        go($self_url);
    }

    if ($action === 'invoice') {
        $result = erp_quote_to_invoice($quote_id, (int) $user['id']);

        if (!$result['success']) {
            $liveform->mark_error('_error', h($result['error']));
            go($self_url);
        }

        log_activity(lang(array('string' => 'erp quote ({var:1}) was turned into an invoice draft', 'vars' => $quote['full_number'])), $_SESSION['sessionusername']);
        $liveform_draft = new liveform('edit_erp_invoice_draft');
        $liveform_draft->add_notice(h(lang(array('string' => 'The invoice draft was made from quote {var:1}, at the prices quoted. Check it and issue it.', 'vars' => $quote['full_number']))));
        go(OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/edit_erp_invoice_draft.php?id=' . (int) $result['invoice_id']);
    }

    if ($action === 'mail') {
        $sent = erp_quote_mail_send($quote_id, (string) ($_POST['mail_to'] ?? ''), (string) ($_POST['mail_subject'] ?? ''), (string) ($_POST['mail_message'] ?? ''), (int) $user['id']);

        if (!$sent['success']) {
            $_SESSION['software']['erp_quote_mail_draft'][$quote_id] = array(
                'to' => mb_substr((string) ($_POST['mail_to'] ?? ''), 0, 500),
                'subject' => mb_substr((string) ($_POST['mail_subject'] ?? ''), 0, 255),
                'message' => mb_substr((string) ($_POST['mail_message'] ?? ''), 0, 5000),
            );
            $liveform->mark_error('_error', h($sent['error']));
            go($self_url . '#erp-mail');
        }

        $liveform->add_notice(h(lang('The quote was e-mailed.')));
        go($self_url . '#erp-mail');
    }

    go($self_url);
}

$state = erp_quote_state($quote);
$statuses = erp_quote_statuses();
$editable = ((string) $quote['status'] === 'open') && !$readonly;
$money = function ($kurus) use ($quote) {
    return h(erp_money_out_currency((int) $kurus, (string) $quote['currency']));
};

// The actions: each is a small form, so a readonly account never sees one.
$action_button = function ($action, $label, $icon, $class, $confirm = '') use ($quote_id) {
    return '<form method="post" action="edit_erp_quote.php" class="d-inline">' . get_token_field()
        . '<input type="hidden" name="id" value="' . $quote_id . '" /><input type="hidden" name="erp_action" value="' . h($action) . '" />'
        . '<button type="submit" class="btn btn-sm ' . $class . '"' . (($confirm !== '') ? ' data-confirm-content="' . h($confirm) . '"' : '') . ' data-loading-content="' . lang(array('string' => 'Please Wait')) . '"><i class="bi ' . $icon . ' me-1" aria-hidden="true"></i>' . $label . '</button></form>';
};

$output_actions = '';
if (!$readonly) {
    if (in_array($state, array('open', 'expired', 'accepted'), true)) {
        $output_actions .= $action_button('invoice', lang('Turn into an invoice'), 'bi-receipt', 'btn-success', lang('Make an invoice draft from this quote, at the prices quoted and dated today?'));
    }
    if (in_array($state, array('open', 'expired'), true)) {
        $output_actions .= $action_button('accepted', lang('Accepted'), 'bi-hand-thumbs-up', 'btn-outline-success');
    }
    if (in_array($state, array('open', 'expired', 'accepted'), true)) {
        $output_actions .= $action_button('rejected', lang('Rejected'), 'bi-hand-thumbs-down', 'btn-outline-danger');
        $output_actions .= $action_button('cancelled', lang('Cancel'), 'bi-x-circle', 'btn-outline-secondary', lang('Cancel this quote? It stays on the list, marked cancelled.'));
    }
    if (in_array($state, array('expired', 'accepted', 'rejected', 'cancelled'), true)) {
        $output_actions .= $action_button('open', lang('Open again'), 'bi-arrow-counterclockwise', 'btn-outline-secondary');
    }
}

$output_invoice = '';
if (((string) $quote['status'] === 'invoiced') && ((string) ($quote['invoice_status'] ?? '') !== '')) {
    $is_draft = ((string) $quote['invoice_status'] === 'draft');
    $output_invoice = '<div class="alert alert-info d-flex flex-wrap gap-2 align-items-center"><i class="bi bi-receipt" aria-hidden="true"></i>'
        . '<span>' . ($is_draft ? lang('This quote became an invoice draft.') : h(lang(array('string' => 'This quote became invoice {var:1}.', 'vars' => (string) $quote['invoice_number'])))) . '</span>'
        . '<a class="ms-auto" href="' . ($is_draft ? 'edit_erp_invoice_draft.php' : 'edit_erp_invoice.php') . '?id=' . (int) $quote['invoice_id'] . '">' . ($is_draft ? lang('Open the draft') : lang('Open the invoice')) . '</a></div>';
} elseif (((string) $quote['status'] === 'invoiced')) {
    $output_invoice = '<div class="alert alert-warning">' . lang('The invoice draft made from this quote was deleted; the quote can be invoiced again.') . '</div>';
}

// Open: the editor. Otherwise the quote as it stands.
$output_body = '';

if ($editable) {
    if (!$liveform->field_in_session('issue_date')) {
        erp_invoice_form_prefill($liveform, array(
            'direction' => 'sales',
            'account_id' => (int) $quote['account_id'],
            'issue_date' => (string) $quote['issue_date'],
            'due_date' => (string) $quote['valid_until'],
            'currency' => (string) $quote['currency'],
            'exchange_rate' => (float) $quote['exchange_rate'],
            'supplier_invoice_no' => '',
            'supplier_invoice_date' => '0000-00-00',
            'notes' => (string) $quote['notes'],
        ));
    }

    $output_body = '
            <form name="form" action="edit_erp_quote.php" method="post" autocomplete="off">
                ' . get_token_field() . '
                <input type="hidden" name="id" value="' . $quote_id . '" />
                <input type="hidden" name="erp_action" value="save" />
                ' . erp_invoice_form_cards($liveform, array('quote' => true, 'valid_days' => ERP_QUOTE_VALID_DAYS, 'lines' => erp_invoice_form_lines($liveform, erp_quote_editor_rows($quote)))) . '
                <nav class="buttons navigation text-center position-sticky mb-4" style="bottom:.5rem;" aria-label="data edit buttons">
                    <div class="container">
                        <button type="submit" class="btn my-1 btn-success" data-loading-content="' . lang(array('string' => 'Saving')) . '"><i class="bi bi-check-circle me-2" aria-hidden="true"></i><span class="btn-text">' . lang(array('string' => 'Save')) . '</span></button>
                    </div>
                </nav>
            </form>';
} else {
    $rows = '';
    foreach (erp_quote_editor_rows($quote) as $line) {
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
                        <div class="col-12 col-lg-7">' . ((trim((string) $quote['notes']) !== '') ? '<div class="small text-body-secondary">' . lang('Notes') . '</div><div>' . nl2br(h((string) $quote['notes'])) . '</div>' : '') . '</div>
                        <div class="col-12 col-lg-5">
                            <div class="d-flex justify-content-between py-1"><span>' . lang('Subtotal') . '</span><b>' . $money($quote['subtotal']) . '</b></div>
                            ' . (((int) $quote['discount_total'] !== 0) ? '<div class="d-flex justify-content-between py-1"><span>' . lang('Discount') . '</span><b>&minus;' . $money($quote['discount_total']) . '</b></div>' : '') . '
                            <div class="d-flex justify-content-between py-1"><span>' . h(erp_tax_label('tax')) . '</span><b>' . $money($quote['tax_total']) . '</b></div>
                            ' . (((int) $quote['withholding_total'] !== 0) ? '<div class="d-flex justify-content-between py-1"><span>' . lang('VAT withholding') . '</span><b>&minus;' . $money($quote['withholding_total']) . '</b></div>' : '') . '
                            <div class="d-flex justify-content-between py-2 border-top h5 mb-0"><span>' . lang('Total') . '</span><b>' . $money($quote['grand_total']) . '</b></div>
                        </div>
                    </div>
                </div>
            </div>';
}

// E-mail: the form and every e-mail the quote already went out in.
$output_mail = '';
$output_mail_button = '';

if (function_exists('erp_mail_ready') && erp_mail_ready()) {
    $defaults = erp_quote_mail_defaults($quote);
    $draft = $_SESSION['software']['erp_quote_mail_draft'][$quote_id] ?? null;
    unset($_SESSION['software']['erp_quote_mail_draft'][$quote_id]);

    if (is_array($draft)) {
        $defaults = array_merge($defaults, $draft);
    }

    $mail_statuses = erp_mail_statuses();
    $mail_rows = '';

    foreach (erp_mail_history('quote', $quote_id) as $mail) {
        $status = $mail_statuses[(string) $mail['status']] ?? array((string) $mail['status'], 'secondary');
        $mail_rows .= '
            <tr>
                <td class="text-nowrap">' . h(prepare_form_data_for_output(date('Y-m-d H:i:s', (int) $mail['created_at']), 'date and time')) . '</td>
                <td class="text-break">' . h((string) $mail['to_address'])
                    . (((string) $mail['error'] !== '') ? '<div class="small text-danger">' . h((string) $mail['error']) . '</div>' : '') . '</td>
                <td>' . h(((string) $mail['created_by_name'] !== '') ? (string) $mail['created_by_name'] : '—') . '</td>
                <td><span class="badge text-bg-' . h($status[1]) . '">' . h($status[0]) . '</span></td>
            </tr>';
    }

    $can_mail = !$readonly && !in_array($state, array('rejected', 'cancelled'), true);
    $output_mail_form = '';

    if ($can_mail) {
        $output_mail_button = '<a class="btn btn-sm btn-outline-secondary" href="#erp-mail"><i class="bi bi-envelope me-1" aria-hidden="true"></i>' . lang('E-mail') . '</a>';
        $output_mail_form = '
            <form name="mail_form" action="edit_erp_quote.php" method="post" class="row g-2">
                ' . get_token_field() . '
                <input type="hidden" name="id" value="' . $quote_id . '" />
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
                    <div class="form-text"><i class="bi bi-paperclip" aria-hidden="true"></i> ' . lang('The quote PDF is attached.') . '</div>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-sm btn-primary" data-loading-content="' . lang(array('string' => 'Please Wait')) . '"><i class="bi bi-envelope me-2" aria-hidden="true"></i>' . lang('Send the e-mail') . '</button>
                </div>
            </form>';
    }

    if (($output_mail_form !== '') || ($mail_rows !== '')) {
        $output_mail = '
            <div class="card my-4" id="erp-mail">
                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">' . lang('E-mail to the customer') . '</div>
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

$status = $statuses[$state] ?? array($state, 'secondary');

echo
pg_page_shell([
    'title' => (string) $quote['full_number'],
    'extra classes' => 'erp erp_invoices',
    'icon' => 'erp',
    'heading' => h(lang(array('string' => 'Quote {var:1}', 'vars' => (string) $quote['full_number']))) . ' <span class="badge text-bg-' . h($status[1]) . ' ms-1 align-middle fs-6">' . h($status[0]) . '</span>',
    'heading_description' => (string) $quote['account_title'] . ' · ' . lang(array('string' => 'Valid until {var:1}', 'vars' => prepare_form_data_for_output((string) $quote['valid_until'], 'date', false))),
    'cancel' => array('enable' => 'true', 'url' => 'erp_quotes.php'),
    'breadcrumb' => array(
        array('label' => lang('Quotes'), 'url' => $list_url),
        array('label' => (string) $quote['full_number']),
    ),
]) . '
<main id="content" class="container-fluid">
    <div class="row">
        <div class="col-12">
            ' . $liveform->output_errors() . '
            ' . $liveform->get_warnings() . '
            ' . $liveform->output_notices() . '

            <nav id="button_bar" class="pg-toolbar navigation d-flex flex-wrap gap-1" aria-label="' . lang('Button Bar') . '">
                <a class="btn btn-sm btn-outline-secondary" href="get_erp_quote_pdf.php?id=' . $quote_id . '" target="_blank" rel="noopener"><i class="bi bi-file-earmark-pdf me-1" aria-hidden="true"></i>' . lang('PDF') . '</a>
                <a class="btn btn-sm btn-outline-secondary" href="get_erp_quote_pdf.php?id=' . $quote_id . '&amp;download=1"><i class="bi bi-download me-1" aria-hidden="true"></i>' . lang('Download') . '</a>
                ' . $output_mail_button . '
                <span class="ms-auto d-flex flex-wrap gap-1">' . $output_actions . '</span>
            </nav>

            ' . $output_invoice . '
            ' . ((!$editable && ((string) $quote['status'] !== 'open') && !$readonly) ? '<div class="form-text mb-2">' . lang('Only an open quote can be changed; "Open again" makes it editable.') . '</div>' : '') . '
            ' . $output_body . '
            ' . $output_mail . '
        </div>
    </div>
</main>' .
output_footer();

$liveform->remove_form();
