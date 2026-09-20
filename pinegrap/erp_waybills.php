<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * ERP - delivery notes: the register.
 *
 * Every note the store has written, with where it stands - invoiced, waiting
 * to be invoiced, cancelled - and the way in to writing one, by hand or for a
 * shipped order.
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
$liveform = new liveform('erp_waybills');

$filter_names = array(
    'open' => lang('Not yet invoiced'),
    'invoiced' => lang('Invoiced'),
    'cancelled' => lang('Cancelled'),
);

$filter = (string) ($_GET['filter'] ?? '');
if (!isset($filter_names[$filter])) {
    $filter = '';
}

// The invoice's state is read through the join: a note whose invoice was
// cancelled, or whose draft was thrown away, is back to waiting.
$where = array();

if ($filter === 'cancelled') {
    $where[] = "w.status = 'cancelled'";
} elseif ($filter === 'invoiced') {
    $where[] = "w.status <> 'cancelled' AND i.id IS NOT NULL AND i.status <> 'cancelled'";
} elseif ($filter === 'open') {
    $where[] = "w.status <> 'cancelled' AND (i.id IS NULL OR i.status = 'cancelled')";
}

$waybills = (array) db_items("SELECT w.*, a.title AS account_title, o.order_number,
        i.full_number AS invoice_number, i.status AS invoice_status,
        (SELECT COUNT(*) FROM erp_waybill_items l WHERE l.waybill_id = w.id) AS line_count
    FROM erp_waybills w
    LEFT JOIN erp_accounts a ON a.id = w.account_id
    LEFT JOIN orders o ON o.id = w.order_id
    LEFT JOIN erp_invoices i ON i.id = w.invoice_id
    " . ($where ? ('WHERE ' . implode(' AND ', $where)) : '') . "
    ORDER BY w.issue_date DESC, w.id DESC");

// Orders that have shipped and carry no note yet, for the picker in the
// toolbar. Complete and linked to a contact, the way the invoice picker asks.
$open_orders = (array) db_items("SELECT orders.id, orders.order_number, orders.billing_first_name, orders.billing_last_name, orders.billing_company
    FROM orders
    WHERE orders.status = 'complete'
      AND orders.contact_id > 0
      AND NOT EXISTS (SELECT 1 FROM erp_waybills w WHERE w.order_id = orders.id AND w.status <> 'cancelled')
    ORDER BY orders.order_date DESC
    LIMIT 300");

$output_order_options = '<option value="">' . lang('Delivery note for an order') . '</option>';
foreach ($open_orders as $order) {
    $who = trim((string) $order['billing_company']);
    if ($who === '') {
        $who = trim($order['billing_first_name'] . ' ' . $order['billing_last_name']);
    }
    $output_order_options .= '<option value="' . (int) $order['id'] . '">' . h('#' . $order['order_number'] . ' - ' . $who) . '</option>';
}

$output_rows = '';

foreach ($waybills as $waybill) {
    $id = (int) $waybill['id'];
    $is_cancelled = ((string) $waybill['status'] === 'cancelled');
    $invoice_state = erp_waybill_invoice_state($waybill);

    if ($is_cancelled) {
        $output_state = '<span class="text-danger">' . lang('Cancelled') . '</span>';
    } elseif ($invoice_state === 'invoiced') {
        $output_state = '<span class="text-success">' . lang('Invoiced') . '</span>';
    } elseif ($invoice_state === 'draft') {
        $output_state = '<span class="text-warning">' . lang('Invoice drafted') . '</span>';
    } else {
        $output_state = '<span class="text-primary">' . lang('Not yet invoiced') . '</span>';
    }

    $output_invoice = '';
    if ((int) $waybill['invoice_id'] > 0 && $invoice_state !== 'none') {
        $output_invoice = ($invoice_state === 'draft')
            ? '<a href="edit_erp_invoice_draft.php?id=' . (int) $waybill['invoice_id'] . '" class="fst-italic">' . lang('Draft') . '</a>'
            : '<a href="edit_erp_invoice.php?id=' . (int) $waybill['invoice_id'] . '">' . h($waybill['invoice_number']) . '</a>';
    }

    $output_order = ((int) $waybill['order_id'] > 0)
        ? '<a href="view_order.php?id=' . (int) $waybill['order_id'] . '">' . h($waybill['order_number'] ?: ('#' . (int) $waybill['order_id'])) . '</a>'
        : '';

    $ship_to = trim((string) $waybill['ship_to_city']);
    if (trim((string) $waybill['ship_to_title']) !== '' && trim((string) $waybill['ship_to_title']) !== trim((string) $waybill['account_title'])) {
        $ship_to = trim((string) $waybill['ship_to_title']) . (($ship_to !== '') ? ', ' . $ship_to : '');
    }

    $output_rows .=
        '<tr' . ($is_cancelled ? ' class="text-body-secondary"' : '') . '>
            <td class="align-middle text-start">
                <button type="button" class="m-1 btn-data-control btn btn-outline-primary border-2" data-loading-content=" " title="' . lang('View') . '" onclick="window.location.href=\'edit_erp_waybill.php?id=' . $id . '\'"><i class="bi bi-eye"></i></button>
            </td>
            <td class="align-middle text-nowrap chart_label" data-order="' . h($waybill['full_number']) . '">' . h($waybill['full_number']) . '</td>
            <td class="align-middle text-nowrap" data-order="' . h($waybill['issue_date']) . '">' . h(prepare_form_data_for_output($waybill['issue_date'], 'date')) . '</td>
            <td class="align-middle text-nowrap" data-order="' . h($waybill['ship_date']) . '">' . h(prepare_form_data_for_output($waybill['ship_date'], 'date')) . '</td>
            <td style="max-width:240px" class="text-nowrap text-truncate align-middle">' . h($waybill['account_title']) . '</td>
            <td style="max-width:220px" class="text-nowrap text-truncate align-middle">' . h($ship_to) . '</td>
            <td style="max-width:160px" class="text-nowrap text-truncate align-middle">' . h($waybill['carrier_title']) . '</td>
            <td class="align-middle text-end" data-order="' . (int) $waybill['line_count'] . '">' . (int) $waybill['line_count'] . '</td>
            <td class="align-middle">' . $output_order . '</td>
            <td class="align-middle">' . $output_invoice . '</td>
            <td class="align-middle">' . $output_state . '</td>
        </tr>';
}

echo
pg_page_shell([
        'title' => lang('Delivery Notes'),
        'extra_classes' => 'erp erp_waybills',
        'icon' => 'store',
        'heading' => lang('Delivery Notes'),
        'heading_description' => lang('Delivery notes and the invoices they turn into.'),
        'cancel' => false,
    ]) . '
<main id="content" class="container-fluid">
    <div class="row">
        <div class="col-12">
            ' . $liveform->output_errors() . '
            ' . $liveform->get_warnings() . '
            ' . $liveform->output_notices() . '

            <nav id="button_bar" class="pg-toolbar navigation" aria-label="' . lang('Button Bar') . '">
                <a class="btn btn-sm btn-primary rounded-pill px-3" href="add_erp_waybill.php" data-loading-content="' . lang(array('string' => 'Loading')) . '"><i class="bi bi-plus-lg me-1"></i>' . lang('New Delivery Note') . '</a>
                <form action="add_erp_waybill.php" method="get" class="d-flex align-items-center gap-1">
                    <select name="order_id" class="form-select form-select-sm" aria-label="' . lang('Order') . '" style="max-width:20rem">' . $output_order_options . '</select>
                    <button type="submit" class="btn btn-sm btn-outline-secondary text-nowrap"><i class="bi bi-cart me-1"></i>' . lang('Open') . '</button>
                </form>
                <div class="pg-toolbar-grow"></div>
                <div class="btn-group btn-group-sm" role="group" aria-label="' . lang('Status') . '">
                    <a class="btn btn-sm btn-ghost' . (($filter === '') ? ' active' : '') . '" href="erp_waybills.php">' . lang('All') . '</a>
                    <a class="btn btn-sm btn-ghost' . (($filter === 'open') ? ' active' : '') . '" href="erp_waybills.php?filter=open">' . lang('Not yet invoiced') . '</a>
                    <a class="btn btn-sm btn-ghost' . (($filter === 'invoiced') ? ' active' : '') . '" href="erp_waybills.php?filter=invoiced">' . lang('Invoiced') . '</a>
                    <a class="btn btn-sm btn-ghost' . (($filter === 'cancelled') ? ' active' : '') . '" href="erp_waybills.php?filter=cancelled">' . lang('Cancelled') . '</a>
                </div>
            </nav>
            <div class="card my-4">
                <div class="card-body p-0 position-relative">
                    <table class="chart table-hover table " style="width:100%;display:none;">
                        <thead>
                            <tr>
                                <th class="noVis">' . lang(array('string' => 'Action')) . '</th>
                                <th>' . lang('Document Number') . '</th>
                                <th>' . lang('Date') . '</th>
                                <th>' . lang('Shipment Date') . '</th>
                                <th>' . lang('Account') . '</th>
                                <th>' . lang('Delivered to') . '</th>
                                <th>' . lang('Carrier') . '</th>
                                <th class="text-end">' . lang('Lines') . '</th>
                                <th>' . lang('Order') . '</th>
                                <th>' . lang('Invoice') . '</th>
                                <th>' . lang('Status') . '</th>
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
