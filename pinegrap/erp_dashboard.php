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
                            <td class="text-end text-nowrap text-body-secondary">' . h(lang(array('string' => '{var:1} days', 'vars' => (int) $account['oldest_days']))) . '</td>
                            <td class="text-end text-nowrap"><a href="erp_invoices.php?filter=overdue&amp;direction=sales&amp;account_id=' . (int) $account['id'] . '" class="link-body-emphasis">' . h(erp_money_out((int) $account['total'])) . '</a></td>
                        </tr>';
}
if ($output_top === '') {
    $output_top = '
                        <tr><td colspan="3" class="text-body-secondary">' . lang('Nothing is overdue.') . '</td></tr>';
}

$card_header = 'card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold';

echo pg_page_shell(array(
    'title'               => lang('ERP'),
    'extra classes'       => 'erp erp_dashboard',
    'icon'                => 'store',
    'heading'             => lang('ERP'),
    'heading_description' => lang('Cash and bank balances, receipts and payments, overdue receivables and e-document failures.'),
    'cancel'              => false,
)) . '
<main id="content" class="container-fluid">
    <div class="row">
        <div class="col-12">
            ' . $liveform->output_errors() . '
            ' . $liveform->get_warnings() . '
            ' . $liveform->output_notices() . '
        </div>
    </div>
    <div class="row">' . $output_cards . '
    </div>
    <div class="row">
        <div class="col-12 col-lg-4 my-2">
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
        </div>
        <div class="col-12 col-lg-4 my-2">
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
        <div class="col-12 col-lg-4 my-2">
            <div class="card h-100">
                <div class="' . $card_header . ' d-flex align-items-center">
                    <span>' . lang('Top overdue accounts') . '</span>
                    <a href="erp_invoices.php?filter=overdue&amp;direction=sales" class="ms-auto small text-decoration-none fw-normal text-lowercase"><i class="bi bi-arrow-right-short"></i>' . lang('Overdue') . '</a>
                </div>
                <div class="card-body">
                    <table class="table table-sm mb-0">
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
</main>' . output_footer();

$liveform->remove_form();
