<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * ERP - the invoice register.
 *
 * This screen only lists. Raising an invoice from an order is
 * add_erp_invoice.php, and a document is read on edit_erp_invoice.php.
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
$liveform = new liveform('erp_invoices');

$invoices = (array) db_items("SELECT i.*, a.title AS account_title, o.order_number
    FROM erp_invoices i
    LEFT JOIN erp_accounts a ON i.account_id = a.id
    LEFT JOIN orders o ON i.order_id = o.id
    ORDER BY i.issue_date DESC, i.id DESC
    LIMIT 500");

$status_labels = array(
    'draft' => lang('Draft'),
    'issued' => lang('Issued'),
    'partially_paid' => lang('Partly paid'),
    'paid' => lang('Paid'),
    'cancelled' => lang('Cancelled'),
);

$status_classes = array(
    'draft' => 'text-body-secondary',
    'issued' => 'text-primary',
    'partially_paid' => 'text-warning',
    'paid' => 'text-success',
    'cancelled' => 'text-danger',
);

$direction_labels = array(
    'sales' => lang('Sales'),
    'purchase' => lang('Purchase'),
);

$output_rows = '';

foreach ($invoices as $invoice) {
    $id = (int) $invoice['id'];
    $status = (string) $invoice['status'];

    $open = erp_invoice_open_amount($invoice);

    $output_link_url = 'edit_erp_invoice.php?id=' . $id;

    $order_cell = ((int) $invoice['order_id'] > 0)
        ? '<a href="view_order.php?id=' . (int) $invoice['order_id'] . '">' . h($invoice['order_number'] ?: ('#' . (int) $invoice['order_id'])) . '</a>'
        : '';

    // Figures in the document's own currency; a foreign document also shows
    // what its total came to in the base currency.
    $currency = strtoupper(trim((string) $invoice['currency']));
    $is_foreign = ($currency !== erp_base_currency());

    $output_rows .=
        '<tr>
            <td class="align-middle text-start">
                <button type="button" class="m-1 btn-data-control btn btn-outline-primary border-2" data-loading-content=" " title="' . lang('View') . '" onclick="window.location.href=\'' . $output_link_url . '\'"><i class="bi bi-eye"></i></button>
            </td>
            <td class="align-middle text-nowrap chart_label">' . h($invoice['full_number']) . '</td>
            <td class="align-middle text-nowrap" data-order="' . h($invoice['issue_date']) . '">' . h(prepare_form_data_for_output($invoice['issue_date'], 'date')) . '</td>
            <td style="max-width:240px" class="text-nowrap text-truncate align-middle">' . h($invoice['account_title']) . '</td>
            <td class="align-middle">' . h($direction_labels[$invoice['direction']] ?? $invoice['direction']) . '</td>
            <td class="align-middle ' . ($status_classes[$status] ?? '') . '">' . h($status_labels[$status] ?? $status) . '</td>
            <td class="align-middle">' . $order_cell . '</td>
            <td class="align-middle text-end" data-order="' . (int) $invoice['tax_total'] . '">' . h(erp_money_out_currency((int) $invoice['tax_total'], $currency)) . '</td>
            <td class="align-middle text-end fw-bold" data-order="' . (int) $invoice['grand_total_base'] . '">' . h(erp_money_out_currency((int) $invoice['grand_total'], $currency))
                . ($is_foreign ? '<div class="text-body-secondary small fw-normal">' . h(erp_money_out((int) $invoice['grand_total_base'])) . '</div>' : '') . '</td>
            <td class="align-middle text-end ' . (($open > 0) ? 'text-danger' : 'text-body-secondary') . '" data-order="' . $open . '">' . h(erp_money_out_currency($open, $currency)) . '</td>
        </tr>';
}

echo
pg_page_shell([
        'title' => lang('Invoices'),
        'extra_classes' => 'erp erp_invoices',
        'icon' => 'store',
        'heading' => lang('Invoices'),
        'heading_description' => lang('Sales and purchase invoices, their e-document status and their returns.'),
        'cancel' => false,
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
                        <a class="btn btn-sm btn-primary m-1" href="add_erp_invoice.php" data-loading-content="' . lang(array('string' => 'Loading')) . '"><span class="bi bi-plus-circle me-2"></span>' . lang(array('string' => 'Invoice an Order')) . '</a>
                    </nav>
                </div>
            </div>
            <div class="card my-4">
                <div class="card-body p-0 position-relative">
                    <table class="chart table-hover table " style="width:100%;display:none;">
                        <thead>
                            <tr>
                                <th class="noVis">' . lang(array('string' => 'Action')) . '</th>
                                <th>' . lang('Document Number') . '</th>
                                <th>' . lang('Date') . '</th>
                                <th>' . lang('Account') . '</th>
                                <th>' . lang('Direction') . '</th>
                                <th>' . lang('Status') . '</th>
                                <th>' . lang('Order') . '</th>
                                <th class="text-end">' . lang('VAT') . '</th>
                                <th class="text-end">' . lang('Total') . '</th>
                                <th class="text-end">' . lang('Outstanding') . '</th>
                            </tr>
                        </thead>
                        <tbody>' . $output_rows . '</tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</main>
' .
output_footer();

$liveform->remove_form();
