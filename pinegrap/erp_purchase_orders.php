<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * ERP - the purchase orders: everything the store asked its suppliers for,
 * newest first, by how far it has come. The work lives in
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
include_once('liveform.class.php');
$liveform = new liveform('erp_purchase_orders');

$statuses = erp_purchase_order_statuses();
$status = is_string($_GET['status'] ?? null) ? $_GET['status'] : '';
$status = (isset($statuses[$status]) || ($status === 'waiting')) ? $status : '';
$search = mb_substr(trim(is_string($_GET['search'] ?? null) ? $_GET['search'] : ''), 0, 100);
// One value only: an array (?account_id[]=x) cast to 1 and narrowed the
// list to the first account.
$account_id = max(0, is_scalar($_GET['account_id'] ?? null) ? (int) $_GET['account_id'] : 0);
$readonly = defined('USER_ERP_READONLY') && USER_ERP_READONLY;
$ready = erp_purchase_orders_ready();

if (!$ready) {
    $liveform->mark_error('', lang('Purchase orders come with the software update; run the update to use them.'));
}

$rows = erp_purchase_order_rows(array('status' => $status, 'search' => $search, 'account_id' => $account_id, 'limit' => 300));
$today = date('Y-m-d');
$output_rows = '';

foreach ($rows as $row) {
    $row_status = $statuses[(string) $row['status']] ?? array((string) $row['status'], 'secondary');
    $expected = (string) $row['expected_date'];
    // An order still waiting past the day its goods were expected.
    $late = ($expected > '0000-00-00') && ($expected < $today) && in_array((string) $row['status'], array('open', 'sent', 'partial'), true);

    $output_rows .= '
        <tr>
            <td class="text-nowrap"><a class="link-body-emphasis fw-semibold" href="edit_erp_purchase_order.php?id=' . (int) $row['id'] . '">' . h((string) $row['full_number']) . '</a></td>
            <td class="text-nowrap" data-sort="' . h(str_replace('-', '', (string) $row['issue_date'])) . '">' . h(prepare_form_data_for_output((string) $row['issue_date'], 'date')) . '</td>
            <td style="max-width:280px" class="text-truncate">' . h((string) $row['account_title']) . '</td>
            <td class="text-nowrap' . ($late ? ' text-danger' : '') . '" data-sort="' . h(str_replace('-', '', $expected)) . '">'
                . (($expected > '0000-00-00') ? h(prepare_form_data_for_output($expected, 'date')) : '<span class="fg-secondary">—</span>')
                . ($late ? ' <span class="badge theme-danger">' . lang('Late') . '</span>' : '') . '</td>
            <td class="text-end text-nowrap" data-sort="' . (int) $row['grand_total'] . '">' . h(erp_money_out_currency((int) $row['grand_total'], (string) $row['currency'])) . '</td>
            <td><span class="badge text-bg-' . h($row_status[1]) . '">' . h($row_status[0]) . '</span></td>
        </tr>';
}

if ($output_rows === '') {
    $output_rows = '<tr data-pg-sort-fixed><td colspan="6" class="text-center fg-secondary py-7">'
        . ((($status === '') && ($search === '') && ($account_id === 0)) ? lang('No purchase order yet. "New purchase order" writes what to ask a supplier for.') : lang('No purchase order matches.')) . '</td></tr>';
}

// One supplier's orders, from its card: said above the list, with the way
// back to every supplier's.
$output_filter_note = '';

if ($account_id > 0) {
    $account_title = (string) db_value("SELECT title FROM erp_accounts WHERE id = '" . $account_id . "' LIMIT 1");
    $clear_query = http_build_query(array_filter(array('status' => $status, 'search' => $search)));
    $output_filter_note = '
            <div class="alert alert-light d-flex align-items-center gap-3 py-3 mb-5" role="status">
                <i class="bi bi-funnel" aria-hidden="true"></i>
                <span>' . h(lang(array('string' => 'Showing: {var:1}', 'vars' => ($account_title !== '') ? $account_title : ('#' . $account_id)))) . '</span>
                <span class="fg-secondary">(' . count($rows) . ')</span>
                <a href="erp_purchase_orders.php' . (($clear_query !== '') ? '?' . h($clear_query) : '') . '" class="ms-auto link-body-emphasis">' . lang('Clear the filter') . '</a>
            </div>';
}

$filter_options = array(
    '' => lang('All'),
    'waiting' => lang(array('string' => 'Waiting for goods ({var:1})', 'vars' => erp_purchase_order_waiting_count($account_id))),
);
foreach ($statuses as $code => $label) {
    $filter_options[$code] = $label[0];
}
$output_filters = pg_filter_select('status', $filter_options, $status, array('label' => lang('Status'), 'icon' => 'funnel', 'action' => 'erp_purchase_orders.php', 'keep' => array('search', 'account_id')));

echo
pg_page_shell([
    'title' => lang('Purchase orders'),
    'extra classes' => 'erp erp_invoices',
    'icon' => 'erp',
    'heading' => lang('Purchase orders'),
    'heading_description' => lang('What the store asked its suppliers for, and how much of it has come. An order moves no money and no stock; the purchase invoice made from it does.'),
    'cancel' => false,
]) . '
<main id="content" class="container-fluid">
    <div class="row">
        <div class="col-12">
            ' . $liveform->output_errors() . '
            ' . $liveform->get_warnings() . '
            ' . $liveform->output_notices() . '

            <nav id="button_bar" class="pg-toolbar navigation" aria-label="' . lang('Button Bar') . '">
                ' . ((!$readonly && $ready) ? '<a class="btn btn-sm btn-solid theme-primary rounded-pill px-5" href="add_erp_purchase_order.php' . (($account_id > 0) ? '?account_id=' . $account_id : '') . '"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>' . lang('New purchase order') . '</a>' : '') . '
                <div class="pg-toolbar-grow"></div>
                <form method="get" action="erp_purchase_orders.php" class="input-group input-group-sm rounded-pill pg-toolbar-search disable_shortcut" role="search">
                    ' . (($status !== '') ? '<input type="hidden" name="status" value="' . h($status) . '" />' : '') . '
                    ' . (($account_id > 0) ? '<input type="hidden" name="account_id" value="' . $account_id . '" />' : '') . '
                    <span class="input-group-text bg-transparent border-end-0 rounded-start-pill"><i class="bi bi-search" aria-hidden="true"></i></span>
                    <input type="search" class="form-control border-start-0 rounded-end-pill" name="search" value="' . h($search) . '" placeholder="' . h(lang('Number or supplier')) . '" aria-label="' . h(lang('Search')) . '" autocomplete="off" />
                </form>
                ' . $output_filters . '
            </nav>
            ' . $output_filter_note . '

            <div class="card my-7">
                <div class="card-body p-0 table-responsive">
                    <table class="table table-hover align-middle mb-0" data-pg-sort>
                        <thead>
                            <tr>
                                <th>' . lang('Order No') . '</th>
                                <th>' . lang('Date') . '</th>
                                <th>' . lang('Supplier') . '</th>
                                <th>' . lang('Expected delivery') . '</th>
                                <th class="text-end">' . lang('Total') . '</th>
                                <th>' . lang('Status') . '</th>
                            </tr>
                        </thead>
                        <tbody>' . $output_rows . '</tbody>
                    </table>
                </div>
                <div class="form-text px-5 pb-5">' . h(lang(array('string' => 'The latest {var:1} purchase orders; search or pick a status to narrow the list.', 'vars' => 300))) . '</div>
            </div>
        </div>
    </div>
</main>' .
output_footer();

$liveform->remove_form();
