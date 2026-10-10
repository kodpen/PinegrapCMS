<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * ERP - the quotes: every offer written to a customer, newest first, by what
 * became of it. The work lives in includes/erp/quotes.php.
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
$liveform = new liveform('erp_quotes');

$statuses = erp_quote_statuses();
$state = isset($statuses[(string) ($_GET['state'] ?? '')]) ? (string) $_GET['state'] : '';
$search = mb_substr(trim((string) ($_GET['search'] ?? '')), 0, 100);
$account_id = max(0, (int) ($_GET['account_id'] ?? 0));
$readonly = defined('USER_ERP_READONLY') && USER_ERP_READONLY;

if (!erp_quotes_ready()) {
    $liveform->mark_error('', lang('Quotes come with the software update; run the update to use them.'));
}

$rows = erp_quote_rows(array('state' => $state, 'search' => $search, 'account_id' => $account_id, 'limit' => 300));
$output_rows = '';

foreach ($rows as $row) {
    $row_state = erp_quote_state($row);
    $status = $statuses[$row_state] ?? array($row_state, 'secondary');

    $output_rows .= '
        <tr>
            <td class="text-nowrap"><a class="link-body-emphasis fw-semibold" href="edit_erp_quote.php?id=' . (int) $row['id'] . '">' . h((string) $row['full_number']) . '</a></td>
            <td class="text-nowrap" data-sort="' . h(str_replace('-', '', (string) $row['issue_date'])) . '">' . h(prepare_form_data_for_output((string) $row['issue_date'], 'date')) . '</td>
            <td style="max-width:280px" class="text-truncate">' . h((string) $row['account_title']) . '</td>
            <td class="text-nowrap" data-sort="' . h(str_replace('-', '', (string) $row['valid_until'])) . '">' . h(prepare_form_data_for_output((string) $row['valid_until'], 'date')) . '</td>
            <td class="text-end text-nowrap" data-sort="' . (int) $row['grand_total'] . '">' . h(erp_money_out_currency((int) $row['grand_total'], (string) $row['currency'])) . '</td>
            <td><span class="badge text-bg-' . h($status[1]) . '">' . h($status[0]) . '</span>'
                . (!empty($row['signed']) ? ' <i class="bi bi-pen text-success" title="' . h(lang('Signed')) . '" aria-label="' . h(lang('Signed')) . '"></i>' : '')
                . (($row_state === 'invoiced')
                    ? ' <a class="small link-body-emphasis" href="' . (((string) $row['invoice_status'] === 'draft') ? 'edit_erp_invoice_draft.php' : 'edit_erp_invoice.php') . '?id=' . (int) $row['invoice_id'] . '">'
                        . h(((string) $row['invoice_number'] !== '') ? (string) $row['invoice_number'] : lang('Draft')) . '</a>'
                    : '') . '</td>
        </tr>';
}

if ($output_rows === '') {
    $output_rows = '<tr data-pg-sort-fixed><td colspan="6" class="text-center text-body-secondary py-4">'
        . ((($state === '') && ($search === '') && ($account_id === 0)) ? lang('No quote yet. "New quote" writes an offer to a customer.') : lang('No quote matches.')) . '</td></tr>';
}

// One account's quotes, from its card: said above the list, with the way
// back to every account's.
$output_filter_note = '';

if ($account_id > 0) {
    $account_title = (string) db_value("SELECT title FROM erp_accounts WHERE id = '" . $account_id . "' LIMIT 1");
    $clear_query = http_build_query(array_filter(array('state' => $state, 'search' => $search)));
    $output_filter_note = '
            <div class="alert alert-light d-flex align-items-center gap-2 py-2 mb-3" role="status">
                <i class="bi bi-funnel" aria-hidden="true"></i>
                <span>' . h(lang(array('string' => 'Showing: {var:1}', 'vars' => ($account_title !== '') ? $account_title : ('#' . $account_id)))) . '</span>
                <span class="text-body-secondary">(' . count($rows) . ')</span>
                <a href="erp_quotes.php' . (($clear_query !== '') ? '?' . h($clear_query) : '') . '" class="ms-auto link-body-emphasis">' . lang('Clear the filter') . '</a>
            </div>';
}

$filter_link = function ($value, $label) use ($state, $search, $account_id) {
    $query = http_build_query(array_filter(array('state' => $value, 'search' => $search, 'account_id' => $account_id)));

    return '<a class="btn btn-sm btn-ghost' . (($state === $value) ? ' active' : '') . '" href="erp_quotes.php' . (($query !== '') ? '?' . h($query) : '') . '">' . $label . '</a>';
};

$output_filters = $filter_link('', lang('All'));
foreach ($statuses as $code => $status) {
    $output_filters .= $filter_link($code, h($status[0]) . (($code === 'open') ? ' (' . (int) erp_quote_open_count($account_id) . ')' : ''));
}

echo
pg_page_shell([
    'title' => lang('Quotes'),
    'extra classes' => 'erp erp_invoices',
    'icon' => 'erp',
    'heading' => lang('Quotes'),
    'heading_description' => lang('Priced offers to customers, and what became of them. A quote moves no money and no stock until it becomes an invoice.'),
    'cancel' => false,
]) . '
<main id="content" class="container-fluid">
    <div class="row">
        <div class="col-12">
            ' . $liveform->output_errors() . '
            ' . $liveform->get_warnings() . '
            ' . $liveform->output_notices() . '

            <nav id="button_bar" class="pg-toolbar navigation" aria-label="' . lang('Button Bar') . '">
                ' . ((!$readonly && erp_quotes_ready()) ? '<a class="btn btn-sm btn-primary rounded-pill px-3" href="add_erp_quote.php' . (($account_id > 0) ? '?account_id=' . $account_id : '') . '" data-loading-content="' . lang(array('string' => 'Loading')) . '"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>' . lang('New quote') . '</a>' : '') . '
                <div class="pg-toolbar-grow"></div>
                <form method="get" action="erp_quotes.php" class="input-group input-group-sm rounded-pill pg-toolbar-search disable_shortcut" role="search">
                    ' . (($state !== '') ? '<input type="hidden" name="state" value="' . h($state) . '" />' : '') . '
                    ' . (($account_id > 0) ? '<input type="hidden" name="account_id" value="' . $account_id . '" />' : '') . '
                    <span class="input-group-text bg-transparent border-end-0 rounded-start-pill"><i class="bi bi-search" aria-hidden="true"></i></span>
                    <input type="search" class="form-control border-start-0 rounded-end-pill" name="search" value="' . h($search) . '" placeholder="' . h(lang('Number or account')) . '" aria-label="' . h(lang('Search')) . '" autocomplete="off" />
                </form>
                <div class="btn-group btn-group-sm flex-wrap" role="group" aria-label="' . h(lang('Status')) . '">' . $output_filters . '</div>
            </nav>
            ' . $output_filter_note . '

            <div class="card my-4">
                <div class="card-body p-0 table-responsive">
                    <table class="table table-hover align-middle mb-0" data-pg-sort>
                        <thead>
                            <tr>
                                <th>' . lang('Quote No') . '</th>
                                <th>' . lang('Date') . '</th>
                                <th>' . lang('Account') . '</th>
                                <th>' . lang('Valid until') . '</th>
                                <th class="text-end">' . lang('Total') . '</th>
                                <th>' . lang('Status') . '</th>
                            </tr>
                        </thead>
                        <tbody>' . $output_rows . '</tbody>
                    </table>
                </div>
                <div class="form-text px-3 pb-3">' . h(lang(array('string' => 'The latest {var:1} quotes; search or pick a status to narrow the list.', 'vars' => 300))) . '</div>
            </div>
        </div>
    </div>
</main>' .
output_footer();

$liveform->remove_form();
