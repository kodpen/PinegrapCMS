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
require_once(PG_FUNCTIONS_DIR . '/includes/erp/drawer.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/list_table.php');
include_once('liveform.class.php');
$liveform = new liveform('erp_waybills');

$filter_names = array(
    'open' => lang('Not yet invoiced'),
    'drafted' => lang('Invoice drafted'),
    'invoiced' => lang('Invoiced'),
    'cancelled' => lang('Cancelled'),
);

$filter = is_string($_GET['filter'] ?? null) ? $_GET['filter'] : '';
if (!isset($filter_names[$filter])) {
    $filter = '';
}

// The invoice's state is read through the join: a note whose invoice was
// cancelled, or whose draft was thrown away, is back to waiting. The three
// states are the ones the rows show (erp_waybill_invoice_state()): a note
// whose invoice is still a draft is neither waiting nor invoiced.
$where = array();

if ($filter === 'cancelled') {
    $where[] = "w.status = 'cancelled'";
} elseif ($filter === 'invoiced') {
    $where[] = "w.status <> 'cancelled' AND i.id IS NOT NULL AND i.status NOT IN ('cancelled', 'draft')";
} elseif ($filter === 'drafted') {
    $where[] = "w.status <> 'cancelled' AND i.id IS NOT NULL AND i.status = 'draft'";
} elseif ($filter === 'open') {
    $where[] = "w.status <> 'cancelled' AND (i.id IS NULL OR i.status = 'cancelled')";
}

$waybills = (array) db_items("SELECT w.*, a.title AS account_title, o.order_number, o.id AS order_exists,
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
    WHERE " . erp_order_billable_sql('orders.status') . "
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

// Every note of the view is printed: the list table driver sorts and
// searches them in the browser (browser mode) and keeps a heading's sort.
$list_rows = array();

foreach ($waybills as $waybill) {
    $id = (int) $waybill['id'];
    $is_cancelled = ((string) $waybill['status'] === 'cancelled');
    $invoice_state = erp_waybill_invoice_state($waybill);

    if ($is_cancelled) {
        $output_state = '<span class="fg-danger">' . lang('Cancelled') . '</span>';
    } elseif ($invoice_state === 'invoiced') {
        $output_state = '<span class="fg-success">' . lang('Invoiced') . '</span>';
    } elseif ($invoice_state === 'draft') {
        $output_state = '<span class="fg-warning">' . lang('Invoice drafted') . '</span>';
    } else {
        $output_state = '<span class="fg-primary">' . lang('Not yet invoiced') . '</span>';
    }

    $output_invoice = '';
    if ((int) $waybill['invoice_id'] > 0 && $invoice_state !== 'none') {
        $output_invoice = ($invoice_state === 'draft')
            ? '<a href="edit_erp_invoice_draft.php?id=' . (int) $waybill['invoice_id'] . '" class="fst-italic">' . lang('Draft') . '</a>'
            : '<a href="edit_erp_invoice.php?id=' . (int) $waybill['invoice_id'] . '">' . h($waybill['invoice_number']) . '</a>';
    }

    $output_order = erp_order_link_html($waybill['order_id'], $waybill['order_number'], $waybill['order_exists'] !== null);

    $ship_to = trim((string) $waybill['ship_to_city']);
    if (trim((string) $waybill['ship_to_title']) !== '' && trim((string) $waybill['ship_to_title']) !== trim((string) $waybill['account_title'])) {
        $ship_to = trim((string) $waybill['ship_to_title']) . (($ship_to !== '') ? ', ' . $ship_to : '');
    }

    $list_rows[] = array(
        'id' => $id,
        'class' => $is_cancelled ? 'fg-secondary' : '',
        'cells' => array(
            'number' => pg_list_cell('<a href="' . h('edit_erp_waybill.php?id=' . $id . '&send_to=' . urlencode(REQUEST_URL)) . '" class="pg-link-body-emphasis" data-pg-open>' . h($waybill['full_number']) . '</a>', 'align-middle text-nowrap', array('data-pg-sort-value' => (string) $waybill['full_number'])),
            'date' => pg_list_cell(h(prepare_form_data_for_output($waybill['issue_date'], 'date')), 'align-middle text-nowrap', array('data-pg-sort-value' => (string) $waybill['issue_date'])),
            'ship_date' => pg_list_cell(h(prepare_form_data_for_output($waybill['ship_date'], 'date')), 'align-middle text-nowrap', array('data-pg-sort-value' => (string) $waybill['ship_date'])),
            'account' => pg_list_cell(h($waybill['account_title']), 'align-middle text-nowrap text-truncate'),
            'ship_to' => pg_list_cell(h($ship_to), 'align-middle text-nowrap text-truncate'),
            'carrier' => pg_list_cell(h($waybill['carrier_title']), 'align-middle text-nowrap text-truncate'),
            'lines' => pg_list_cell((string) (int) $waybill['line_count'], 'align-middle', array('data-pg-sort-value' => (int) $waybill['line_count'])),
            'order' => pg_list_cell($output_order),
            'invoice' => pg_list_cell($output_invoice),
            'status' => pg_list_cell($output_state),
            'actions' => pg_list_cell(erp_drawer_button($id)),
        ),
    );
}

$columns = array(
    'number' => array('heading' => lang('Document Number'), 'kind' => 'link'),
    'date' => array('heading' => lang('Date'), 'kind' => 'date'),
    'ship_date' => array('heading' => lang('Shipment Date'), 'kind' => 'date'),
    'account' => array('heading' => lang('Account')),
    'ship_to' => array('heading' => lang('Delivered to')),
    'carrier' => array('heading' => lang('Carrier')),
    'lines' => array('heading' => lang('Lines'), 'kind' => 'number'),
    'order' => array('heading' => lang('Order')),
    'invoice' => array('heading' => lang('Invoice')),
    'status' => array('heading' => lang('Status'), 'kind' => 'badge'),
    'actions' => array('heading' => lang('Action'), 'kind' => 'actions'),
);
$sort_columns = pg_list_table_printed_sort_columns($columns);
$sort_default = array('sort' => 'date', 'order' => 'desc');
$list_state = pg_list_state('erp_waybills', $sort_columns, $sort_default);
$list = pg_list_table_printed(array(
    'list' => 'erp_waybills',
    'columns' => $columns,
    'levels' => $list_state['sorts'],
    'rows' => $list_rows,
));

echo
pg_page_shell([
        'title' => lang('Delivery Notes'),
        'extra classes' => 'erp erp_waybills',
        'icon' => 'erp',
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
                <a class="btn btn-sm btn-solid theme-primary rounded-pill px-5" href="add_erp_waybill.php"><i class="bi bi-plus-lg me-1"></i>' . lang('New Delivery Note') . '</a>
                <form action="add_erp_waybill.php" method="get" class="d-flex align-items-center gap-1">
                    <select name="order_id" class="form-control form-control-sm" aria-label="' . lang('Order') . '" style="max-width:20rem">' . $output_order_options . '</select>
                    <button type="submit" class="btn btn-sm btn-outline theme-secondary text-nowrap"><i class="bi bi-cart me-1"></i>' . lang('Open it') . '</button>
                </form>
                <div class="pg-toolbar-grow"></div>
                ' . pg_list_table_search_box('erp_waybills') . '
                ' . pg_list_sort_menu('erp_waybills.php', $sort_columns, $list_state['sorts'], $sort_default, ($filter !== '') ? array('filter' => $filter) : array()) . '
                ' . pg_filter_select('filter', array('' => lang('All')) + $filter_names, $filter, array('label' => lang('Status'), 'icon' => 'funnel', 'action' => 'erp_waybills.php')) . '
            </nav>
            <div class="card my-7">
                ' . pg_list_table_html($list['source'], $list['page'], lang('Delivery Notes')) . '
            </div>
        </div>
    </div>
</main>
' . erp_drawer_markup('waybill') . pg_js_bundle_tags('assets/js/pg_list_table.min.js') .
output_footer();

$liveform->remove_form();
