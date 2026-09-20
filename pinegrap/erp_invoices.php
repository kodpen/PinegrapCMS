<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * ERP - the invoice register.
 *
 * This screen only lists. Raising an invoice from an order is
 * add_erp_invoice.php, typing one in is add_erp_manual_invoice.php, a draft
 * is edited on edit_erp_invoice_draft.php and a document is read on
 * edit_erp_invoice.php.
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

// A due-date view narrows the register to the documents the aging helpers
// pick out, so the list, the aging report and the dashboard agree to the
// document. Everything in the address is checked against the values the
// screen knows; anything else reads as the plain register.
$filter_names = array(
    'overdue' => lang('Overdue'),
    'due_week' => lang('Due this week'),
    'open' => lang('Open'),
);
$filter = (string) ($_GET['filter'] ?? '');
$filter_direction = (string) ($_GET['direction'] ?? '');
$filter_direction = (($filter_direction === 'sales') || ($filter_direction === 'purchase')) ? $filter_direction : '';
$filter_account_id = (int) ($_GET['account_id'] ?? 0);
$filter_bucket = (string) ($_GET['bucket'] ?? '');
$filter_bucket = erp_aging_bucket_valid($filter_bucket) ? $filter_bucket : '';
$filter_as_of = erp_aging_as_of($_GET['as_of'] ?? '');

if (!isset($filter_names[$filter])) {
    $filter = ($filter_bucket !== '') ? 'open' : '';
}

$where = '';
$output_filter_note = '';

if ($filter !== '') {
    $aged = erp_aging_invoices($filter_direction, $filter_as_of, array(
        'account_id' => $filter_account_id,
        'overdue' => ($filter === 'overdue'),
        'due_within' => ($filter === 'due_week') ? 7 : 0,
        'bucket' => $filter_bucket,
    ));

    $ids = array();
    foreach ($aged as $aged_row) {
        $ids[] = (int) $aged_row['id'];
    }

    // An empty match still has to be a query that returns nothing.
    $where = ' WHERE i.id IN (' . (empty($ids) ? '0' : implode(',', $ids)) . ')';

    $parts = array($filter_names[$filter]);
    if ($filter_bucket !== '') {
        $parts[] = erp_aging_buckets()[$filter_bucket];
    }
    if ($filter_direction !== '') {
        $parts[] = ($filter_direction === 'purchase') ? lang('Purchase') : lang('Sales');
    }
    if ($filter_account_id > 0) {
        $account_title = (string) db_value("SELECT title FROM erp_accounts WHERE id = '" . $filter_account_id . "' LIMIT 1");
        $parts[] = ($account_title !== '') ? $account_title : ('#' . $filter_account_id);
    }
    if ($filter_as_of !== date('Y-m-d')) {
        $parts[] = lang(array('string' => 'as of {var:1}', 'vars' => prepare_form_data_for_output($filter_as_of, 'date', false)));
    }

    $output_filter_note = '
            <div class="alert alert-light d-flex align-items-center gap-2 py-2 mb-3" role="status">
                <i class="bi bi-funnel"></i>
                <span>' . h(lang(array('string' => 'Showing: {var:1}', 'vars' => implode(' · ', $parts)))) . '</span>
                <span class="text-body-secondary">(' . count($ids) . ')</span>
                <a href="erp_invoices.php" class="ms-auto link-body-emphasis">' . lang('Clear the filter') . '</a>
            </div>';
}

$invoices = (array) db_items("SELECT i.*, a.title AS live_account_title, o.order_number,
        " . erp_aging_due_sql('i') . " AS effective_due
    FROM erp_invoices i
    LEFT JOIN erp_accounts a ON i.account_id = a.id
    LEFT JOIN orders o ON i.order_id = o.id
    " . $where . "
    ORDER BY i.issue_date DESC, i.id DESC
    LIMIT 500");

$today = date('Y-m-d');
$now = time();

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

    // A draft has no number yet and opens in the editor rather than as a document.
    $is_draft = ($status === 'draft');

    // Nothing is owed on a draft: no movement has been posted for it.
    $open = $is_draft ? 0 : erp_invoice_open_amount($invoice);

    // The title as it was when the document was issued; the live card for
    // documents written before the copy existed.
    $account_title = (trim((string) $invoice['account_title']) !== '') ? (string) $invoice['account_title'] : (string) $invoice['live_account_title'];
    $output_link_url = ($is_draft ? 'edit_erp_invoice_draft.php?id=' : 'edit_erp_invoice.php?id=') . $id;
    $output_number = $is_draft
        ? '<span class="text-body-secondary fst-italic">' . lang('Draft') . '</span>'
        : h($invoice['full_number']);

    $order_cell = ((int) $invoice['order_id'] > 0)
        ? '<a href="view_order.php?id=' . (int) $invoice['order_id'] . '">' . h($invoice['order_number'] ?: ('#' . (int) $invoice['order_id'])) . '</a>'
        : '';

    // Figures in the document's own currency; a foreign document also shows
    // what its total came to in the base currency.
    $currency = strtoupper(trim((string) $invoice['currency']));
    $is_foreign = ($currency !== erp_base_currency());

    // The term, and how it stands today. Only a document with money owing on
    // it can be late; a return or a draft has no term to keep.
    $effective_due = (string) $invoice['effective_due'];
    $output_due = '';
    if (!$is_draft && ((string) $invoice['doc_type'] === 'invoice')) {
        $output_due = h(prepare_form_data_for_output($effective_due, 'date'));

        if ($open > 0) {
            $days = erp_aging_days($effective_due, $today);

            if ($days > 0) {
                // Reminders put off: the bell is crossed out until the date.
                $snoozed_until = (int) ($invoice['overdue_snoozed_until'] ?? 0);
                $output_snoozed = ($snoozed_until > $now)
                    ? ' <i class="bi bi-bell-slash text-body-secondary" title="' . h(lang(array('string' => 'Reminders put off until {var:1}', 'vars' => prepare_form_data_for_output(date('Y-m-d', $snoozed_until), 'date')))) . '"></i>'
                    : '';
                $output_due .= '<div><span class="badge text-bg-danger">' . h(lang(array('string' => '{var:1} days overdue', 'vars' => $days))) . '</span>' . $output_snoozed . '</div>';
            } elseif ($days === 0) {
                $output_due .= '<div><span class="badge text-bg-warning">' . lang('Due today') . '</span></div>';
            } elseif (-$days <= 7) {
                $output_due .= '<div><span class="badge text-bg-warning">' . h(lang(array('string' => 'due in {var:1} days', 'vars' => -$days))) . '</span></div>';
            }
        }
    }

    $output_rows .=
        '<tr>
            <td class="align-middle text-start">
                <button type="button" class="m-1 btn-data-control btn btn-outline-primary border-2" data-loading-content=" " title="' . ($is_draft ? lang('Edit') : lang('View')) . '" onclick="window.location.href=\'' . $output_link_url . '\'"><i class="bi ' . ($is_draft ? 'bi-pencil' : 'bi-eye') . '"></i></button>
            </td>
            <td class="align-middle text-nowrap chart_label" data-order="' . h($is_draft ? ('~' . $id) : $invoice['full_number']) . '">' . $output_number . '</td>
            <td class="align-middle text-nowrap" data-order="' . h($invoice['issue_date']) . '">' . h(prepare_form_data_for_output($invoice['issue_date'], 'date')) . '</td>
            <td class="align-middle text-nowrap" data-order="' . h($effective_due) . '">' . $output_due . '</td>
            <td style="max-width:240px" class="text-nowrap text-truncate align-middle">' . h($account_title) . '</td>
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

            <nav id="button_bar" class="pg-toolbar navigation" aria-label="' . lang('Button Bar') . '">
                <a class="btn btn-sm btn-primary rounded-pill px-3" href="add_erp_manual_invoice.php" data-loading-content="' . lang(array('string' => 'Loading')) . '"><i class="bi bi-plus-lg me-1"></i>' . lang('New Invoice') . '</a>
                <a class="btn btn-sm btn-outline-secondary" href="add_erp_invoice.php" data-loading-content="' . lang(array('string' => 'Loading')) . '"><i class="bi bi-receipt me-1"></i>' . lang(array('string' => 'Invoice an Order')) . '</a>
                <a class="btn btn-sm btn-outline-secondary" href="erp_export.php?entity=invoices" data-loading-content="' . lang(array('string' => 'Loading')) . '"><i class="bi bi-box-arrow-up me-1"></i>' . lang('Export') . '</a>
                <a class="btn btn-sm btn-outline-secondary" href="erp_aging.php" data-loading-content="' . lang(array('string' => 'Loading')) . '"><i class="bi bi-hourglass-split me-1"></i>' . lang('Aging report') . '</a>
                <div class="pg-toolbar-grow"></div>
                <div class="btn-group btn-group-sm" role="group" aria-label="' . lang('Due date') . '">
                    <a class="btn btn-sm btn-ghost' . (($filter === '') ? ' active' : '') . '" href="erp_invoices.php">' . lang('All') . '</a>
                    <a class="btn btn-sm btn-ghost' . (($filter === 'overdue') ? ' active' : '') . '" href="erp_invoices.php?filter=overdue"><i class="bi bi-exclamation-circle me-1"></i>' . lang('Overdue') . '</a>
                    <a class="btn btn-sm btn-ghost' . (($filter === 'due_week') ? ' active' : '') . '" href="erp_invoices.php?filter=due_week"><i class="bi bi-calendar-week me-1"></i>' . lang('Due this week') . '</a>
                </div>
            </nav>
            ' . $output_filter_note . '
            <div class="card my-4">
                <div class="card-body p-0 position-relative">
                    <table class="chart table-hover table " style="width:100%;display:none;">
                        <thead>
                            <tr>
                                <th class="noVis">' . lang(array('string' => 'Action')) . '</th>
                                <th>' . lang('Document Number') . '</th>
                                <th>' . lang('Date') . '</th>
                                <th>' . lang('Due Date') . '</th>
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
