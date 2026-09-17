<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * ERP - read an invoice.
 *
 * An issued invoice is not edited. It is a document that has already been
 * handed to somebody, so a mistake on it is corrected by a credit note against
 * it rather than by quietly changing what it says. The lines are shown as they
 * were written, with the figure each one was checked against.
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
include_once('liveform.class.php');
$liveform = new liveform('edit_erp_invoice');

$list_url = OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/erp_invoices.php';

$invoice_id = (int) ($_REQUEST['id'] ?? 0);

$invoice = ($invoice_id > 0)
    ? db_item("SELECT i.*, a.title AS account_title, a.tax_number, a.tax_office, o.order_number
        FROM erp_invoices i
        LEFT JOIN erp_accounts a ON i.account_id = a.id
        LEFT JOIN orders o ON i.order_id = o.id
        WHERE i.id = '" . $invoice_id . "' LIMIT 1")
    : null;

if (!is_array($invoice)) {
    output_error(lang('The invoice could not be found.') . ' <a href="' . h($list_url) . '">' . lang('Invoices') . '</a>');
    exit();
}

if ($_POST) {

    validate_token_field();

    if (($_POST['erp_action'] ?? '') === 'cancel') {

        $result = erp_invoice_cancel($invoice_id, (int) $user['id']);

        if ($result['success']) {
            log_activity(lang(array('string' => 'erp document ({var:1}) was cancelled', 'vars' => $invoice['full_number'])), $_SESSION['sessionusername']);
            $liveform->add_notice(lang('The document has been cancelled.'));
        } else {
            $liveform->mark_error('_error', $result['error']);
        }

        go(OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/edit_erp_invoice.php?id=' . $invoice_id);
    }
}

$lines = (array) db_items("SELECT * FROM erp_invoice_items
    WHERE invoice_id = '" . $invoice_id . "' ORDER BY line_no ASC, id ASC");

$settlements = erp_invoice_settlements($invoice_id);
$returns = erp_invoice_returns($invoice_id);
$returned_total = erp_invoice_returned_total($invoice_id);
$open_amount = erp_invoice_open_amount($invoice);

$is_return = ((string) $invoice['doc_type'] === 'return');
$is_cancelled = ((string) $invoice['status'] === 'cancelled');

// Cancelling is for a document nothing has been hung on yet. Once money has
// been allocated to it or goods have come back against it, the way out is a
// return, not a quiet withdrawal.
$can_cancel = (!$is_cancelled && empty($settlements) && empty($returns));
$can_return = (!$is_cancelled && !$is_return && ($invoice['doc_type'] === 'invoice'));

$status_labels = array(
    'draft' => lang('Draft'),
    'issued' => lang('Issued'),
    'partially_paid' => lang('Partly paid'),
    'paid' => lang('Paid'),
    'cancelled' => lang('Cancelled'),
);

// The lines are shown in the order they were written on the document, so this
// is deliberately not a DataTable: a line number is part of what the document
// says, and sorting it away would show something the customer never received.
$output_lines = '';

foreach ($lines as $line) {
    $rate = rtrim(rtrim((string) $line['tax_rate'], '0'), '.');
    $discount = (int) $line['discount_amount'];

    $output_lines .= '
        <tr>
            <td class="align-middle text-body-secondary">' . (int) $line['line_no'] . '</td>
            <td class="align-middle">' . h($line['description']) . '</td>
            <td class="align-middle text-end">' . h(rtrim(rtrim(number_format((float) $line['quantity'], 4, '.', ''), '0'), '.')) . '</td>
            <td class="align-middle text-end">' . h(erp_money_out((int) $line['unit_price'])) . '</td>
            <td class="align-middle text-end">' . (($discount > 0) ? '&minus;' . h(erp_money_out($discount)) : '') . '</td>
            <td class="align-middle text-end">' . h(erp_money_out((int) $line['line_total'] - $discount)) . '</td>
            <td class="align-middle text-end text-nowrap">%' . h($rate) . '</td>
            <td class="align-middle text-end">' . h(erp_money_out((int) $line['tax_total'])) . '</td>
        </tr>';
}

if ($output_lines === '') {
    $output_lines = '<tr><td colspan="8" class="text-center text-body-secondary py-4">' . lang('This invoice has no lines.') . '</td></tr>';
}

// The money behind the paid figure. Shown even when it is one line, because
// "paid" without saying which receipt paid it is the thing nobody can reconcile.
$output_settlements = '';

if (!empty($settlements)) {
    $output_settlements = '
                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle mb-0 mt-3">
                            <thead>
                                <tr>
                                    <th>' . lang('Date') . '</th>
                                    <th>' . lang('Description') . '</th>
                                    <th>' . lang('Till or Bank Account') . '</th>
                                    <th class="text-end">' . lang('Amount') . '</th>
                                </tr>
                            </thead>
                            <tbody>';

    foreach ($settlements as $settlement) {
        $output_settlements .= '
                                <tr>
                                    <td class="text-nowrap">' . h(prepare_form_data_for_output($settlement['doc_date'], 'date')) . '</td>
                                    <td>' . h($settlement['description']) . '</td>
                                    <td>' . h($settlement['cash_account_name']) . '</td>
                                    <td class="text-end">' . h(erp_money_out((int) $settlement['amount'])) . '</td>
                                </tr>';
    }

    $output_settlements .= '
                            </tbody>
                        </table>
                    </div>';
}

// The returns, listed on the document they were raised against, because that is
// where somebody looking at a total that no longer matches will look first.
$output_returns = '';

if (!empty($returns)) {
    $output_returns = '
                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle mb-0 mt-3">
                            <thead>
                                <tr>
                                    <th>' . lang('Date') . '</th>
                                    <th>' . lang('Return') . '</th>
                                    <th class="text-end">' . lang('Amount') . '</th>
                                </tr>
                            </thead>
                            <tbody>';

    foreach ($returns as $return) {
        $output_returns .= '
                                <tr>
                                    <td class="text-nowrap">' . h(prepare_form_data_for_output($return['issue_date'], 'date')) . '</td>
                                    <td><a href="edit_erp_invoice.php?id=' . (int) $return['id'] . '">' . h($return['full_number']) . '</a></td>
                                    <td class="text-end">' . h(erp_money_out((int) $return['grand_total'])) . '</td>
                                </tr>';
    }

    $output_returns .= '
                            </tbody>
                        </table>
                    </div>';
}

$output_order = ((int) $invoice['order_id'] > 0)
    ? '<a href="view_order.php?id=' . (int) $invoice['order_id'] . '">' . h($invoice['order_number'] ?: ('#' . (int) $invoice['order_id'])) . '</a>'
    : '<span class="text-body-secondary">&mdash;</span>';

$output_account = ((int) $invoice['account_id'] > 0)
    ? '<a href="edit_erp_account.php?id=' . (int) $invoice['account_id'] . '">' . h($invoice['account_title']) . '</a>'
    : h($invoice['account_title']);

echo
pg_page_shell([
        'title' => $is_return ? lang('Return') : lang('Invoice'),
        'extra_classes' => 'erp erp_invoices',
        'icon' => 'store',
        'heading' => h($invoice['full_number']),
        'heading_description' => lang('The document as it was issued, and the lines it was built from.'),
        'cancel' => array('enable' => 'true', 'url' => 'erp_invoices.php'),
        'breadcrumb' => array(
            array('label' => lang('Invoices'), 'url' => $list_url),
            array('label' => h($invoice['full_number'])),
        ),
    ]) . '
<main id="content" class="container-fluid">
    <div class="row">
        <div class="col-12">
            ' . $liveform->output_errors() . '
            ' . $liveform->get_warnings() . '
            ' . $liveform->output_notices() . '

            <div class="row mb-2 flex-wrap">
                <div class="col-12 text-center text-md-start">
                    <nav id="button_bar" class="navigation" aria-label="Button Bar">
                        <a class="btn btn-sm btn-outline-secondary m-1" href="get_erp_invoice_pdf.php?id=' . $invoice_id . '" target="_blank" rel="noopener"><i class="bi bi-file-earmark-pdf me-2"></i>' . lang('PDF') . '</a>
                        <a class="btn btn-sm btn-outline-secondary m-1" href="get_erp_invoice_pdf.php?id=' . $invoice_id . '&amp;download=1"><i class="bi bi-download me-2"></i>' . lang('Download') . '</a>
                    </nav>
                </div>
            </div>

            <div class="card my-4">
                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                    ' . ($is_return ? lang('Return') : lang('Invoice')) . '
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-12 col-sm-6 col-lg-3 my-2">
                            <div class="form-label text-body-secondary">' . lang('Account') . '</div>
                            <div>' . $output_account . '</div>
                        </div>
                        <div class="col-12 col-sm-6 col-lg-3 my-2">
                            <div class="form-label text-body-secondary">' . lang('VKN / TCKN') . '</div>
                            <div>' . h($invoice['tax_number'] ?: '—') . ' <span class="text-body-secondary">' . h($invoice['tax_office']) . '</span></div>
                        </div>
                        <div class="col-12 col-sm-4 col-lg-2 my-2">
                            <div class="form-label text-body-secondary">' . lang('Date') . '</div>
                            <div>' . h(prepare_form_data_for_output($invoice['issue_date'], 'date')) . '</div>
                        </div>
                        <div class="col-12 col-sm-4 col-lg-2 my-2">
                            <div class="form-label text-body-secondary">' . lang('Status') . '</div>
                            <div>' . h($status_labels[$invoice['status']] ?? $invoice['status']) . '</div>
                        </div>
                        <div class="col-12 col-sm-4 col-lg-2 my-2">
                            <div class="form-label text-body-secondary">' . lang('Order') . '</div>
                            <div>' . $output_order . '</div>
                        </div>
                        ' . ($is_return
                            ? '<div class="col-12 col-sm-4 col-lg-2 my-2">
                            <div class="form-label text-body-secondary">' . lang('Against Invoice') . '</div>
                            <div><a href="edit_erp_invoice.php?id=' . (int) $invoice['parent_invoice_id'] . '">' . h((string) db_value("SELECT full_number FROM erp_invoices WHERE id = '" . (int) $invoice['parent_invoice_id'] . "'")) . '</a></div>
                        </div>'
                            : '') . '
                    </div>
                </div>
            </div>

            <div class="card my-4">
                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                    ' . lang('Lines') . '
                </div>
                <div class="card-body p-0 position-relative">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th style="width:3rem">#</th>
                                <th>' . lang('Description') . '</th>
                                <th class="text-end">' . lang('Quantity') . '</th>
                                <th class="text-end">' . lang('Unit price') . '</th>
                                <th class="text-end">' . lang('Discount') . '</th>
                                <th class="text-end">' . lang('Taxable Amount') . '</th>
                                <th class="text-end">' . lang('Rate') . '</th>
                                <th class="text-end">' . lang('VAT') . '</th>
                            </tr>
                        </thead>
                        <tbody>' . $output_lines . '</tbody>
                    </table>
                </div>
            </div>

            <div class="card my-4">
                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <span>' . lang('Settlement') . '</span>
                    ' . (($open_amount > 0 && (string) $invoice['status'] !== 'cancelled')
                        ? '<a class="btn btn-sm btn-primary" href="add_erp_receipt.php?direction=' . (((string) $invoice['direction'] === 'sales') ? 'collection' : 'payment') . '&amp;invoice_id=' . $invoice_id . '"><span class="bi bi-cash-coin me-2"></span>' . lang('Record a Receipt') . '</a>'
                        : '') . '
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-12 col-sm-3 my-2">
                            <div class="form-label text-body-secondary">' . lang('Invoice Total') . '</div>
                            <div class="h5 mb-0">' . h(erp_money_out((int) $invoice['grand_total'])) . '</div>
                        </div>
                        <div class="col-12 col-sm-3 my-2">
                            <div class="form-label text-body-secondary">' . lang('Paid') . '</div>
                            <div class="h5 mb-0 text-success">' . h(erp_money_out((int) $invoice['paid_total'])) . '</div>
                        </div>
                        <div class="col-12 col-sm-3 my-2">
                            <div class="form-label text-body-secondary">' . lang('Returned') . '</div>
                            <div class="h5 mb-0 ' . (($returned_total > 0) ? 'text-warning' : 'text-body-secondary') . '">' . h(erp_money_out($returned_total)) . '</div>
                        </div>
                        <div class="col-12 col-sm-3 my-2">
                            <div class="form-label text-body-secondary">' . lang('Outstanding') . '</div>
                            <div class="h5 mb-0 ' . (($open_amount > 0) ? 'text-danger' : 'text-body-secondary') . '">' . h(erp_money_out($open_amount)) . '</div>
                        </div>
                    </div>
                    ' . $output_settlements . '
                    ' . $output_returns . '
                </div>
            </div>

            ' . (($can_return || $can_cancel)
                ? '<form name="form" action="edit_erp_invoice.php" method="post">
                ' . get_token_field() . '
                <input type="hidden" name="id" value="' . $invoice_id . '" />
                <input type="hidden" name="erp_action" value="cancel" />
                <nav class="buttons navigation text-center position-sticky mb-4" style="bottom:.5rem;" aria-label="data edit buttons">
                    <div class="container">
                        <div class="btn-group flex-wrap justify-content-center">
                            ' . ($can_return
                                ? '<a class="btn my-1 btn-outline-warning" href="add_erp_return.php?invoice_id=' . $invoice_id . '"><span class="bi bi-arrow-return-left me-2"></span>' . lang('Return an Invoice') . '</a>'
                                : '') . '
                            ' . ($can_cancel
                                ? '<button type="submit" name="submit_cancel" value="Cancel" class="btn my-1 btn-outline-danger" data-loading-content="' . lang(array('string' => 'Please Wait')) . '"><span class="bi bi-x-circle me-2"></span><span class="btn-text">' . lang('Cancel the Document') . '</span></button>'
                                : '') . '
                        </div>
                    </div>
                </nav>
            </form>'
                : '') . '

            <div class="row">
                <div class="col-12 col-lg-5 ms-auto">
                    <div class="card my-4">
                        <div class="card-body">
                            <div class="d-flex justify-content-between py-1">
                                <span>' . lang('Subtotal') . '</span><b>' . h(erp_money_out((int) $invoice['subtotal'])) . '</b>
                            </div>
                            <div class="d-flex justify-content-between py-1">
                                <span>' . lang('Discount') . '</span><b>&minus;' . h(erp_money_out((int) $invoice['discount_total'])) . '</b>
                            </div>
                            <div class="d-flex justify-content-between py-1">
                                <span>' . lang('VAT') . '</span><b>' . h(erp_money_out((int) $invoice['tax_total'])) . '</b>
                            </div>
                            <div class="d-flex justify-content-between py-2 border-top h5 mb-0">
                                <span>' . lang('Total') . '</span><b>' . h(erp_money_out((int) $invoice['grand_total'])) . '</b>
                            </div>
                            ' . (((int) $invoice['gift_card_total'] > 0)
                                ? '<div class="d-flex justify-content-between py-1 text-body-secondary">
                                <span>' . lang('Settled by gift card') . '</span><b>' . h(erp_money_out((int) $invoice['gift_card_total'])) . '</b>
                            </div>'
                                : '') . '
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>
' .
output_footer();

$liveform->remove_form();
