<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * ERP - dashboard
 *
 * The first screen of the module: what is owed to you and by you, what of it
 * is late, what falls due this week, what the tills hold, and who owes the
 * oldest money. Every figure comes from includes/erp/aging.php, the same
 * source as the aging report and the badges on the invoice list, so a number
 * here can be followed to the documents behind it.
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

// Piggyback for the overdue reminders: staff traffic sends the period's digest
// on a site where the job was never scheduled. Throttled inside to one attempt
// an hour, so this is a no-op on almost every load.
erp_overdue_check();
include_once('liveform.class.php');
$liveform = new liveform('erp_dashboard');

$today = date('Y-m-d');
$receivables = erp_aging_summary('sales', $today, 5);
$payables = erp_aging_summary('purchase', $today, 5);
$cash = erp_aging_cash_total();
$buckets = erp_aging_buckets();

// Cash is a right of its own (manage_erp_cash): someone who may read the
// accounts need not see what the tills hold. The till card, today's figures
// and the latest movements are left out for them, not shown as zero.
$can_see_cash = ((int) $user['role'] < 3) || !empty($user['manage_erp_cash']);

// One card: a label, the figure, a line under it, and where it leads.
$card = function ($label, $figure, $note, $icon, $url, $figure_class = '') {
    return '
            <div class="col-12 col-md-6 col-xl-3 my-2">
                <a href="' . h($url) . '" class="card h-100 text-decoration-none link-body-emphasis" data-loading-content="' . lang(array('string' => 'Loading')) . '">
                    <div class="card-body d-flex align-items-start gap-3">
                        <i class="bi ' . $icon . ' fs-2 text-body-secondary"></i>
                        <div class="min-w-0">
                            <div class="small text-body-secondary text-uppercase">' . h($label) . '</div>
                            <div class="h3 mb-1 ' . $figure_class . '">' . h($figure) . '</div>
                            <div class="small text-body-secondary">' . h($note) . '</div>
                        </div>
                    </div>
                </a>
            </div>';
};

$output_cards =
    $card(lang('Receivables'), erp_money_out($receivables['total_base']),
        lang(array('string' => '{var:1} open document(s)', 'vars' => $receivables['count'])),
        'bi-arrow-down-left-circle', 'erp_aging.php?direction=sales') .
    $card(lang('Payables'), erp_money_out($payables['total_base']),
        lang(array('string' => '{var:1} open document(s)', 'vars' => $payables['count'])),
        'bi-arrow-up-right-circle', 'erp_aging.php?direction=purchase') .
    $card(lang('Overdue receivables'), erp_money_out($receivables['overdue_base']),
        lang(array('string' => '{var:1} document(s) past due', 'vars' => $receivables['overdue_count'])),
        'bi-exclamation-circle', 'erp_invoices.php?filter=overdue&direction=sales',
        ($receivables['overdue_count'] > 0) ? 'text-danger' : '') .
    $card(lang('Due within 7 days'), erp_money_out($receivables['due_soon_base']),
        lang(array('string' => '{var:1} document(s) fall due', 'vars' => $receivables['due_soon_count'])),
        'bi-calendar-week', 'erp_invoices.php?filter=due_week&direction=sales');

// This month's result and what is spent: the profit report for the month so
// far, and the expenses with what of them is still to pay.
$output_cards_month = '';

if (function_exists('erp_profit_report')) {
    $month_from = date('Y-m-01');
    $month_to = date('Y-m-t');
    // The same choice as the profit screen's about orders without an invoice.
    $month_report = erp_profit_report($month_from, $month_to, array('orders' => erp_profit_orders_choice()));
    $month_net = (int) $month_report['totals']['net'];
    $month_margin = erp_profit_margin($month_net, (int) $month_report['totals']['revenue']);

    $output_cards_month .=
        $card(lang('Net sales this month'), erp_money_out((int) $month_report['totals']['revenue']),
            lang(array('string' => 'Gross profit {var:1}', 'vars' => erp_money_out((int) $month_report['totals']['gross']))),
            'bi-bar-chart', 'erp_profit.php?range=month&month=' . substr($month_from, 0, 7)) .
        $card(lang('Net profit this month'), erp_money_out($month_net),
            lang(array('string' => 'Margin {var:1}', 'vars' => erp_profit_margin_text($month_margin))),
            'bi-graph-up', 'erp_profit.php?range=month&month=' . substr($month_from, 0, 7),
            ($month_net < 0) ? 'text-danger' : '');

    if (erp_expenses_ready()) {
        $month_expenses = $month_report['expenses']['total'];
        $expenses_open = db_item("SELECT COUNT(*) AS count, COALESCE(SUM(total_base), 0) AS total,
                SUM(IF(due_date > '0000-00-00' AND due_date < CURDATE(), 1, 0)) AS overdue
            FROM erp_expenses WHERE status = 'unpaid'");
        $open_overdue = is_array($expenses_open) ? (int) $expenses_open['overdue'] : 0;

        $output_cards_month .=
            $card(lang('Expenses this month'), erp_money_out((int) $month_expenses['total']),
                lang(array('string' => '{var:1} expense(s)', 'vars' => (int) $month_expenses['count'])),
                'bi-receipt-cutoff', 'erp_expenses.php?period=' . substr($month_from, 0, 7)) .
            $card(lang('Expenses to pay'), erp_money_out(is_array($expenses_open) ? (int) $expenses_open['total'] : 0),
                ($open_overdue > 0)
                    ? lang(array('string' => '{var:1} past their due date', 'vars' => $open_overdue))
                    : lang(array('string' => '{var:1} expense(s), any date', 'vars' => is_array($expenses_open) ? (int) $expenses_open['count'] : 0)),
                'bi-hourglass-split', 'erp_expenses.php?status=unpaid&period=dates&from=2000-01-01&to=' . date('Y-12-31'),
                ($open_overdue > 0) ? 'text-danger' : '');
    }
}

// What needs a look beyond the money: products at or below their minimum
// stock, customers past their credit limit. Shown only when there are some.
$low_stock_count = function_exists('erp_stock_low_count') ? erp_stock_low_count() : 0;

if ($low_stock_count > 0) {
    $low_first = erp_stock_low_products(1);
    $output_cards_month .= $card(lang('Low stock'), (string) $low_stock_count,
        !empty($low_first)
            ? lang(array('string' => 'Most short: {var:1}', 'vars' => erp_stock_product_label($low_first[0])))
            : lang('At or below the minimum'),
        'bi-box-seam', 'erp_stock_minimums.php?show=low', 'text-danger');
}

$over_limit_accounts = function_exists('erp_credit_over_accounts') ? erp_credit_over_accounts(100) : array();

if (!empty($over_limit_accounts)) {
    $over_total = 0;
    foreach ($over_limit_accounts as $over_account) {
        $over_total += (int) $over_account['over_by'];
    }

    $output_cards_month .= $card(lang('Over their credit limit'), (string) count($over_limit_accounts),
        lang(array('string' => '{var:1} over in all', 'vars' => erp_money_out($over_total))),
        'bi-speedometer2', 'erp_accounts.php?filter=over_limit', 'text-danger');
}

// ------------------------------------------------------------- cash and bank
$output_tills = '';
foreach ($cash['tills'] as $till) {
    $output_tills .= '
                        <tr>
                            <td class="text-truncate" style="max-width:200px">' . h($till['name']) . '</td>
                            <td class="text-end ' . (((int) $till['balance'] < 0) ? 'text-danger' : '') . '">' . h(erp_money_out((int) $till['balance'])) . '</td>
                        </tr>';
}
foreach ($cash['foreign'] as $till) {
    $output_tills .= '
                        <tr class="text-body-secondary">
                            <td class="text-truncate" style="max-width:200px">' . h($till['name']) . ' <span class="small">' . h(lang('not added in')) . '</span></td>
                            <td class="text-end">' . h(erp_money_out_currency((int) $till['balance'], (string) $till['currency'])) . '</td>
                        </tr>';
}
if ($output_tills === '') {
    $output_tills = '
                        <tr><td colspan="2" class="text-body-secondary">' . lang('No active till.') . '</td></tr>';
}

// -------------------------------------------------------- receivables by age
// Widths are the share of the open total, so the bar reads at a glance; the
// legend under it carries the figures.
$bucket_classes = array(
    'current' => 'bg-success',
    'd1_30' => 'bg-info',
    'd31_60' => 'bg-warning',
    'd61_90' => 'bg-danger',
    'd90p' => 'bg-dark',
);

$output_bar = '';
$output_legend = '';
$total = (int) $receivables['total_base'];

foreach ($buckets as $key => $label) {
    $amount = (int) $receivables['buckets'][$key];
    $share = ($total > 0) ? round($amount * 100 / $total) : 0;

    if ($amount > 0) {
        $output_bar .= '
                        <div class="progress" role="progressbar" aria-label="' . h($label) . '" aria-valuenow="' . $share . '" aria-valuemin="0" aria-valuemax="100" style="width:' . $share . '%">
                            <div class="progress-bar ' . $bucket_classes[$key] . '"></div>
                        </div>';
    }

    $output_legend .= '
                        <tr>
                            <td><span class="d-inline-block rounded-circle ' . $bucket_classes[$key] . ' me-2" style="width:.65rem;height:.65rem;"></span>' . h($label) . '</td>
                            <td class="text-end text-body-secondary">' . $share . '%</td>
                            <td class="text-end">' . (($amount > 0)
                                ? '<a href="erp_invoices.php?filter=open&amp;direction=sales&amp;bucket=' . h($key) . '" class="link-body-emphasis">' . h(erp_money_out($amount)) . '</a>'
                                : '<span class="text-body-secondary">' . h(erp_money_out(0)) . '</span>') . '</td>
                        </tr>';
}

// ---------------------------------------------------------- top overdue accounts
$output_top = '';
foreach ($receivables['top'] as $account) {
    $output_top .= '
                        <tr>
                            <td class="text-truncate" style="max-width:220px"><a href="edit_erp_account.php?id=' . (int) $account['id'] . '" class="link-body-emphasis">' . h($account['title']) . '</a></td>
                            <td class="text-end text-nowrap text-body-secondary" data-sort="' . (int) $account['oldest_days'] . '">' . h(lang(array('string' => '{var:1} days', 'vars' => (int) $account['oldest_days']))) . '</td>
                            <td class="text-end text-nowrap" data-sort="' . (int) $account['total'] . '"><a href="erp_invoices.php?filter=overdue&amp;direction=sales&amp;account_id=' . (int) $account['id'] . '" class="link-body-emphasis">' . h(erp_money_out((int) $account['total'])) . '</a></td>
                        </tr>';
}
if ($output_top === '') {
    $output_top = '
                        <tr data-pg-sort-fixed><td colspan="3" class="text-body-secondary">' . lang('Nothing is overdue.') . '</td></tr>';
}

$card_header = 'card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold';

// ------------------------------------------------------------- e-documents
// The counts are the lengths of the register's e-document views, so a click
// lands on exactly the documents the line talks about. A store that has no
// provider and never sent anything gets no card at all; one whose provider
// was switched off with documents still in the air keeps it, because those
// documents still need someone to look at them.
$output_edoc_card = '';

if (erp_edoc_installed()) {
    $edoc_provider = erp_edoc_active();
    $edoc_counts = erp_edoc_filter_counts();
    $edoc_in_flight = $edoc_counts['failed'] + $edoc_counts['created'] + $edoc_counts['pending'];

    if (($edoc_provider !== '') || ($edoc_in_flight > 0)) {
        $edoc_info = ($edoc_provider !== '') ? erp_edoc_info($edoc_provider) : null;
        $edoc_environment = erp_edoc_environment($edoc_provider);

        $output_edoc_provider = ($edoc_info !== null)
            ? '<span class="fw-semibold">' . h($edoc_info['label']) . '</span>'
                . (!empty($edoc_environment['is_test']) ? ' <span class="badge text-bg-warning">' . h($edoc_environment['label']) . '</span>' : '')
            : '<span class="text-body-secondary">' . lang('No e-document provider is selected.') . '</span>';

        $output_edoc_rows = '';
        foreach (erp_edoc_filters() as $edoc_key => $edoc_filter) {
            // Accepted documents are the good news; the card is about what
            // needs attention, and the register holds the rest.
            if ($edoc_key === 'accepted') {
                continue;
            }

            // Nothing unsent is worth a line when there is nobody to send to.
            if (($edoc_key === 'unsent') && ($edoc_provider === '')) {
                continue;
            }

            $edoc_count = (int) $edoc_counts[$edoc_key];
            $edoc_tone = ($edoc_count > 0) ? $edoc_filter['tone'] : 'secondary';

            $output_edoc_rows .= '
                        <tr>
                            <td><i class="bi ' . $edoc_filter['icon'] . ' me-2 text-' . $edoc_tone . '" aria-hidden="true"></i>' . h($edoc_filter['label']) . '</td>
                            <td class="text-end">' . (($edoc_count > 0)
                                ? '<a href="erp_invoices.php?edoc=' . $edoc_key . '" class="badge rounded-pill text-bg-' . $edoc_tone . ' text-decoration-none">' . number_format($edoc_count) . '</a>'
                                : '<span class="text-body-secondary">0</span>') . '</td>
                        </tr>';
        }

        // What suppliers sent and nobody has taken in yet, when the
        // provider hands incoming invoices over.
        if (erp_edoc_inbox_installed() && (erp_edoc_inbox_provider() !== '')) {
            $inbox_count = (int) erp_edoc_inbox_counts()['new'];
            $inbox_tone = ($inbox_count > 0) ? 'primary' : 'secondary';

            $output_edoc_rows .= '
                        <tr>
                            <td><i class="bi bi-inbox me-2 text-' . $inbox_tone . '" aria-hidden="true"></i>' . lang('Incoming e-invoices to take in') . '</td>
                            <td class="text-end">' . (($inbox_count > 0)
                                ? '<a href="erp_inbox.php" class="badge rounded-pill text-bg-' . $inbox_tone . ' text-decoration-none">' . number_format($inbox_count) . '</a>'
                                : '<a href="erp_inbox.php" class="text-body-secondary text-decoration-none">0</a>') . '</td>
                        </tr>';
        }

        $output_edoc_card = '
        <div class="col-12 ' . ($can_see_cash ? 'col-lg-4' : 'col-lg-6') . ' my-2">
            <div class="card h-100">
                <div class="' . $card_header . ' d-flex align-items-center">
                    <span>' . lang('e-Document') . '</span>
                    <a href="erp_invoices.php?edoc=failed" class="ms-auto small text-decoration-none fw-normal text-lowercase"><i class="bi bi-arrow-right-short"></i>' . lang('Invoices') . '</a>
                </div>
                <div class="card-body">
                    <div class="mb-3">' . $output_edoc_provider . '</div>
                    <table class="table table-sm mb-0">
                        <tbody>' . $output_edoc_rows . '
                        </tbody>
                    </table>
                </div>
            </div>
        </div>';
    }
}

// ----------------------------------------------------- today and the latest
// Today's figures come from the cash flow report with a one-day period, so
// they follow its rules: a transfer between own tills is not money in or
// out, and a till in another currency is not added in.
$output_movements_card = '';

if ($can_see_cash) {
    $today_flow = erp_cashflow_report(erp_cashflow_options(array('from' => $today, 'to' => $today, 'group' => 'day')));

    $recent = (array) db_items("SELECT c.id, c.doc_date, c.direction, c.amount, c.currency, c.doc_type, c.doc_id, c.description,
            c.cash_account_id, t.name AS till_name, a.title AS account_title, c.account_id,
            (SELECT r.id FROM erp_cash_transactions r WHERE r.doc_type = 'cancel' AND r.doc_id = c.id LIMIT 1) AS reversal_id,
            o.doc_type AS cancelled_type, o.doc_id AS cancelled_doc_id
        FROM erp_cash_transactions c
        LEFT JOIN erp_cash_transactions o ON c.doc_type = 'cancel' AND o.id = c.doc_id
        LEFT JOIN erp_cash_accounts t ON c.cash_account_id = t.id
        LEFT JOIN erp_accounts a ON c.account_id = a.id
        ORDER BY c.id DESC
        LIMIT 10");

    $movement_words = array(
        'collection' => lang('Receipt'),
        'payment' => lang('Payment'),
        'transfer' => lang('Transfer'),
        'cancel' => lang('Cancellation'),
        'expense' => lang('Expense'),
    );

    $output_recent = '';
    foreach ($recent as $movement) {
        $movement_type = (string) $movement['doc_type'];
        $movement_amount = (int) $movement['amount'] * (((string) $movement['direction'] === 'in') ? 1 : -1);
        $movement_label = $movement_words[$movement_type] ?? $movement_type;
        $movement_description = trim((string) $movement['description']);

        // A receipt opens on its own screen; a cancellation opens the
        // receipt it cancelled; a transfer opens the till's cash book.
        if (in_array($movement_type, array('collection', 'payment'), true)) {
            $movement_url = 'erp_receipt.php?id=' . (int) $movement['id'];
        } elseif ($movement_type === 'expense') {
            $movement_url = 'edit_erp_expense.php?id=' . (int) $movement['doc_id'];
        } elseif (($movement_type === 'cancel') && ((string) $movement['cancelled_type'] === 'expense')) {
            $movement_url = 'edit_erp_expense.php?id=' . (int) $movement['cancelled_doc_id'];
        } elseif ($movement_type === 'cancel') {
            $movement_url = 'erp_receipt.php?id=' . (int) $movement['doc_id'];
        } else {
            $movement_url = 'edit_erp_till.php?id=' . (int) $movement['cash_account_id'];
        }

        $output_recent .= '
                        <tr>
                            <td class="text-nowrap text-body-secondary" data-sort="' . h(str_replace('-', '', (string) $movement['doc_date'])) . '">' . h(prepare_form_data_for_output((string) $movement['doc_date'], 'date')) . '</td>
                            <td class="text-nowrap"><a href="' . h($movement_url) . '" class="link-body-emphasis">' . h($movement_label) . '</a>'
                                . (((int) $movement['reversal_id'] > 0) ? ' <span class="badge text-bg-secondary">' . lang('Cancelled') . '</span>' : '') . '</td>
                            <td class="text-truncate" style="max-width:220px">' . (((int) $movement['account_id'] > 0)
                                ? '<a href="edit_erp_account.php?id=' . (int) $movement['account_id'] . '" class="link-body-emphasis">' . h((string) $movement['account_title']) . '</a>'
                                : '<span class="text-body-secondary">' . h($movement_description) . '</span>') . '</td>
                            <td class="text-truncate text-body-secondary" style="max-width:160px">' . h((string) $movement['till_name']) . '</td>
                            <td class="text-end text-nowrap ' . (($movement_amount < 0) ? 'text-danger' : 'text-success') . '" data-sort="' . (int) $movement_amount . '">' . h(erp_money_out_currency($movement_amount, strtoupper(trim((string) $movement['currency'])))) . '</td>
                        </tr>';
    }
    if ($output_recent === '') {
        $output_recent = '
                        <tr data-pg-sort-fixed><td colspan="5" class="text-body-secondary">' . lang('There are no movements yet.') . '</td></tr>';
    }

    $today_url = 'erp_cashflow.php?from=' . $today . '&amp;to=' . $today;

    $output_movements_card = '
        <div class="col-12 ' . (($output_edoc_card !== '') ? 'col-lg-8' : '') . ' my-2">
            <div class="card h-100">
                <div class="' . $card_header . ' d-flex align-items-center">
                    <span>' . lang('Latest movements') . '</span>
                    <a href="erp_cash.php" class="ms-auto small text-decoration-none fw-normal text-lowercase"><i class="bi bi-arrow-right-short"></i>' . lang('Cash and Bank') . '</a>
                </div>
                <div class="card-body">
                    <a href="' . $today_url . '" class="d-flex flex-wrap gap-4 mb-3 text-decoration-none link-body-emphasis" title="' . h(lang('Transfers between your own tills are not counted.')) . '">
                        <div>
                            <div class="small text-body-secondary text-uppercase">' . lang('Money in today') . '</div>
                            <div class="h4 mb-0 text-success">' . h(erp_money_out((int) $today_flow['in'])) . '</div>
                        </div>
                        <div>
                            <div class="small text-body-secondary text-uppercase">' . lang('Money out today') . '</div>
                            <div class="h4 mb-0 text-danger">' . h(erp_money_out((int) $today_flow['out'])) . '</div>
                        </div>
                        <div>
                            <div class="small text-body-secondary text-uppercase">' . lang('Net change') . '</div>
                            <div class="h4 mb-0">' . h(erp_money_out((int) $today_flow['net'])) . '</div>
                        </div>
                    </a>
                    <div class="table-responsive">
                        <table class="table table-sm mb-0" data-pg-sort>
                            <thead>
                                <tr class="small text-body-secondary">
                                    <th class="fw-normal">' . lang('Date') . '</th>
                                    <th class="fw-normal">' . lang('Document') . '</th>
                                    <th class="fw-normal">' . lang('Account') . '</th>
                                    <th class="fw-normal">' . lang('Till') . '</th>
                                    <th class="fw-normal text-end">' . lang('Amount') . '</th>
                                </tr>
                            </thead>
                            <tbody>' . $output_recent . '
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>';
}

echo pg_page_shell(array(
    'title'               => lang('ERP'),
    'extra classes'       => 'erp erp_dashboard',
    'icon'                => 'erp',
    'heading'             => lang('Enterprise resource planning (ERP)'),
    'heading_description' => erp_edoc_in_use()
        ? lang('Cash and bank balances, receipts and payments, overdue receivables and e-document failures.')
        : lang('Cash and bank balances, receipts and payments, and overdue receivables.'),
    'cancel'              => false,
)) . '
<main id="content" class="container-fluid">
    <div class="row">
        <div class="col-12">
            ' . $liveform->output_errors() . '
            ' . $liveform->get_warnings() . '
            ' . $liveform->output_notices() . '
        </div>
    </div>'
    // A counter sale is where most of the ERP's invoices start, so the screen
    // that opens it sits at the head of the dashboard - for an operator who
    // may use it (validate_ecommerce_access).
    . ((((int) $user['role'] < 3) || !empty($user['manage_ecommerce'])) ? '
    <div class="pg-toolbar" id="button_bar">
        <a class="btn btn-sm btn-primary rounded-pill px-3" href="add_order.php"><i class="bi bi-cart-plus me-1" aria-hidden="true"></i>' . lang(array('string' => 'Add {var:1}', 'vars' => array(lang('Order')))) . '</a>
    </div>' : '') . '
    <div class="row">' . $output_cards . '
    </div>
    ' . (($output_cards_month !== '') ? '<div class="row">' . $output_cards_month . '
    </div>' : '') . '
    <div class="row">
        ' . ($can_see_cash ? '<div class="col-12 col-lg-4 my-2">
            <div class="card h-100">
                <div class="' . $card_header . ' d-flex align-items-center">
                    <span>' . lang('Cash and Bank') . '</span>
                    <a href="erp_cash.php" class="ms-auto small text-decoration-none fw-normal text-lowercase"><i class="bi bi-arrow-right-short"></i>' . lang('Cash and Bank') . '</a>
                </div>
                <div class="card-body">
                    <div class="h3 mb-3 ' . (($cash['total'] < 0) ? 'text-danger' : '') . '">' . h(erp_money_out((int) $cash['total'])) . '</div>
                    <table class="table table-sm mb-0">
                        <tbody>' . $output_tills . '
                        </tbody>
                    </table>
                </div>
            </div>
        </div>' : '') . '
        <div class="col-12 ' . ($can_see_cash ? 'col-lg-4' : 'col-lg-6') . ' my-2">
            <div class="card h-100">
                <div class="' . $card_header . ' d-flex align-items-center">
                    <span>' . lang('Receivables by age') . '</span>
                    <a href="erp_aging.php?direction=sales" class="ms-auto small text-decoration-none fw-normal text-lowercase"><i class="bi bi-arrow-right-short"></i>' . lang('Aging report') . '</a>
                </div>
                <div class="card-body">
                    <div class="progress-stacked mb-3" style="height:1rem;">' . $output_bar . '
                    </div>
                    <table class="table table-sm mb-0">
                        <tbody>' . $output_legend . '
                        </tbody>
                    </table>
                    ' . (erp_fx_enabled() ? '<div class="form-text">' . lang('Foreign-currency documents are counted at the rate they were booked at.') . '</div>' : '') . '
                </div>
            </div>
        </div>
        <div class="col-12 ' . ($can_see_cash ? 'col-lg-4' : 'col-lg-6') . ' my-2">
            <div class="card h-100">
                <div class="' . $card_header . ' d-flex align-items-center">
                    <span>' . lang('Top overdue accounts') . '</span>
                    <a href="erp_invoices.php?filter=overdue&amp;direction=sales" class="ms-auto small text-decoration-none fw-normal text-lowercase"><i class="bi bi-arrow-right-short"></i>' . lang('Overdue') . '</a>
                </div>
                <div class="card-body">
                    <table class="table table-sm mb-0" data-pg-sort>
                        <thead>
                            <tr class="small text-body-secondary">
                                <th class="fw-normal">' . lang('Account') . '</th>
                                <th class="fw-normal text-end">' . lang('Oldest') . '</th>
                                <th class="fw-normal text-end">' . lang('Overdue') . '</th>
                            </tr>
                        </thead>
                        <tbody>' . $output_top . '
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="row">' . $output_edoc_card . $output_movements_card . '
    </div>
</main>' . output_footer();

$liveform->remove_form();
