<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * ERP - profit and loss: what was sold, what the goods cost, what else was
 * spent, and what is left, for a month, a quarter, a year or two dates; with
 * the months side by side and the margin of each product and account. The
 * figures are worked out in includes/erp/profit.php.
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
$liveform = new liveform('erp_profit');

$input = array(
    'range' => (string) ($_GET['range'] ?? 'year'),
    'month' => (string) ($_GET['month'] ?? date('Y-m')),
    'quarter' => (string) ($_GET['quarter'] ?? (date('Y') . '-Q' . (int) ceil(date('n') / 3))),
    'year' => (string) ($_GET['year'] ?? date('Y')),
    'from' => (string) ($_GET['from'] ?? ''),
    'to' => (string) ($_GET['to'] ?? ''),
);

$period = erp_profit_period($input);

// Whether the completed orders without an invoice count: asked on the form,
// then remembered in this browser (the ERP dashboard reads the same choice).
$with_orders = erp_profit_orders_choice(isset($_GET['orders']) ? (string) $_GET['orders'] : null);

if ($period['error'] !== '') {
    $liveform->add_warning(h($period['error']));
}

$report = erp_profit_report($period['from'], $period['to'], array('orders' => $with_orders));
$totals = $report['totals'];
$base = erp_base_currency();

// ------------------------------------------------------------------ export
if ((string) ($_GET['export'] ?? '') === 'xlsx') {
    $path = tempnam(sys_get_temp_dir(), 'pgp');

    if (($path === false) || !erp_profit_workbook($report, $path)) {
        if ($path !== false) {
            @unlink($path);
        }
        $liveform->mark_error('_error', lang('The workbook could not be written.'));
        go(PATH . SOFTWARE_DIRECTORY . '/erp_profit.php');
    }

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . erp_accountant_file_part(lang('Profit and loss')) . '_' . $period['from'] . '_' . $period['to'] . '.xlsx"');
    header('Content-Length: ' . filesize($path));
    header('Cache-Control: private, no-store');
    readfile($path);
    @unlink($path);
    exit();
}

// ------------------------------------------------------------------ display
$money = function ($kurus, $class = '') {
    $kurus = (int) $kurus;

    return '<span class="text-nowrap' . (($kurus < 0) ? ' text-danger' : '') . ($class !== '' ? ' ' . $class : '') . '">' . h(erp_money_out($kurus)) . '</span>';
};
$margin = function ($value) {
    return ($value === null) ? '<span class="text-body-secondary">—</span>' : h(erp_profit_margin_text($value));
};

$gross_margin = erp_profit_margin($totals['gross'], $totals['revenue']);
$net_margin = erp_profit_margin($totals['net'], $totals['revenue']);
$costs = $totals['purchase_costs'] + $totals['expenses'];

// The statement.
$output_expense_lines = '';
foreach ((array) $report['expenses']['categories'] as $category) {
    $output_expense_lines .= '
        <tr class="small">
            <td class="ps-4 text-body-secondary">' . h($category['name']) . (($category['code'] !== '') ? ' <span class="font-monospace">' . h($category['code']) . '</span>' : '') . '</td>
            <td class="text-end text-body-secondary">' . $money(-$category['cost']) . '</td>
        </tr>';
}

$output_statement = '
    <table class="table table-sm align-middle mb-0">
        <tbody>
            <tr><td>' . lang('Sales') . '</td><td class="text-end">' . $money($totals['sales']) . '</td></tr>
            ' . ($report['orders']['included'] ? '<tr><td>' . lang('Completed orders without an invoice') . '<div class="small text-body-secondary">' . h(lang(array('string' => '{var:1} order(s), by the order date', 'vars' => (int) $report['orders']['count']))) . '</div></td><td class="text-end">' . $money($totals['order_sales']) . '</td></tr>' : '') . '
            <tr><td>' . lang('Returns') . '</td><td class="text-end">' . $money(-$totals['returns']) . '</td></tr>
            <tr class="fw-semibold border-top"><td>' . lang('Net sales') . '</td><td class="text-end">' . $money($totals['revenue']) . '</td></tr>
            <tr><td>' . lang('Cost of goods sold')
                . (((int) $totals['cogs_estimated'] !== 0) ? '<div class="small text-body-secondary">' . h(lang(array('string' => 'of which {var:1} estimated at today\'s average cost', 'vars' => erp_money_out((int) $totals['cogs_estimated'])))) . '</div>' : '')
                . '</td><td class="text-end">' . $money(-$totals['cogs']) . '</td></tr>
            <tr class="fw-semibold border-top"><td>' . lang('Gross profit') . ' <span class="text-body-secondary fw-normal small">' . $margin($gross_margin) . '</span></td><td class="text-end">' . $money($totals['gross']) . '</td></tr>
            <tr><td>' . lang('Costs from purchase invoices') . '<div class="small text-body-secondary">' . lang('Purchase lines with no product: services and costs bought on an invoice.') . '</div></td><td class="text-end">' . $money(-$totals['purchase_costs']) . '</td></tr>
            <tr><td>' . lang('Expenses') . ' <a class="small" href="erp_expenses.php?' . h(http_build_query(array('period' => 'dates', 'from' => $period['from'], 'to' => $period['to']))) . '">' . lang('List') . '</a></td><td class="text-end">' . $money(-$totals['expenses']) . '</td></tr>
            ' . $output_expense_lines . '
            <tr class="fw-bold border-top border-2"><td>' . lang('Net profit') . ' <span class="text-body-secondary fw-normal small">' . $margin($net_margin) . '</span></td><td class="text-end">' . $money($totals['net'], 'fs-5') . '</td></tr>
        </tbody>
    </table>';

// Month by month.
$output_months = '';
$chart = array('labels' => array(), 'revenue' => array(), 'costs' => array(), 'net' => array());

foreach ($report['months'] as $month => $figures) {
    $label = get_month_name_from_number(substr($month, 5, 2)) . ' ' . substr($month, 0, 4);
    $chart['labels'][] = $label;
    $chart['revenue'][] = round($figures['revenue'] / 100, 2);
    $chart['costs'][] = round(($figures['cogs'] + $figures['purchase_costs'] + $figures['expenses']) / 100, 2);
    $chart['net'][] = round($figures['net'] / 100, 2);

    $output_months .= '
        <tr>
            <td class="text-nowrap" data-sort="' . h(str_replace('-', '', $month)) . '">' . h($label) . '</td>
            <td class="text-end" data-sort="' . (int) $figures['revenue'] . '">' . $money($figures['revenue']) . '</td>
            <td class="text-end" data-sort="' . (int) $figures['cogs'] . '">' . $money($figures['cogs']) . '</td>
            <td class="text-end" data-sort="' . (int) $figures['gross'] . '">' . $money($figures['gross']) . '</td>
            <td class="text-end" data-sort="' . (int) ($figures['purchase_costs'] + $figures['expenses']) . '">' . $money($figures['purchase_costs'] + $figures['expenses']) . '</td>
            <td class="text-end fw-semibold" data-sort="' . (int) $figures['net'] . '">' . $money($figures['net']) . '</td>
            <td class="text-end" data-sort="' . h((string) (erp_profit_margin($figures['net'], $figures['revenue']) ?? '')) . '">' . $margin(erp_profit_margin($figures['net'], $figures['revenue'])) . '</td>
        </tr>';
}

$output_months .= '
        <tr class="fw-bold border-top" data-pg-sort-fixed>
            <td>' . lang('Total') . '</td>
            <td class="text-end">' . $money($totals['revenue']) . '</td>
            <td class="text-end">' . $money($totals['cogs']) . '</td>
            <td class="text-end">' . $money($totals['gross']) . '</td>
            <td class="text-end">' . $money($costs) . '</td>
            <td class="text-end">' . $money($totals['net']) . '</td>
            <td class="text-end">' . $margin($net_margin) . '</td>
        </tr>';

// Products and accounts: the full lists, sortable; the first rows are the
// most profitable.
$output_products = '';
foreach (array_slice($report['products'], 0, 500) as $product) {
    $output_products .= '
        <tr>
            <td>' . h($product['name'])
                . ((($product['sku'] !== '') && ($product['sku'] !== $product['name'])) ? '<div class="small text-body-secondary font-monospace">' . h($product['sku']) . '</div>' : '')
                . (((int) $product['uncosted'] > 0) ? '<div class="small text-warning-emphasis">' . lang('no cost known') . '</div>' : '') . '</td>
            <td class="text-end" data-sort="' . h((string) $product['quantity']) . '">' . h(erp_quantity_text($product['quantity'])) . '</td>
            <td class="text-end" data-sort="' . (int) $product['revenue'] . '">' . $money($product['revenue']) . '</td>
            <td class="text-end" data-sort="' . (int) $product['cost'] . '">' . $money($product['cost'])
                . (((int) $product['estimated'] > 0) ? ' <i class="bi bi-info-circle text-body-secondary" title="' . h(lang('Partly estimated at today\'s average cost')) . '"></i>' : '') . '</td>
            <td class="text-end fw-semibold" data-sort="' . (int) $product['profit'] . '">' . $money($product['profit']) . '</td>
            <td class="text-end" data-sort="' . h((string) ($product['margin'] ?? '')) . '">' . $margin($product['margin']) . '</td>
        </tr>';
}
if ($output_products === '') {
    $output_products = '<tr data-pg-sort-fixed><td colspan="6" class="text-center text-body-secondary py-4">' . lang('Nothing was sold in this period.') . '</td></tr>';
}

$output_accounts = '';
foreach (array_slice($report['accounts'], 0, 500) as $account) {
    $output_accounts .= '
        <tr>
            <td>' . (($account['account_id'] > 0) ? '<a class="link-body-emphasis" href="edit_erp_account.php?id=' . (int) $account['account_id'] . '">' . h($account['title']) . '</a>' : h($account['title'])) . '</td>
            <td class="text-end" data-sort="' . (int) $account['documents'] . '">' . (int) $account['documents'] . '</td>
            <td class="text-end" data-sort="' . (int) $account['revenue'] . '">' . $money($account['revenue']) . '</td>
            <td class="text-end" data-sort="' . (int) $account['cost'] . '">' . $money($account['cost']) . '</td>
            <td class="text-end fw-semibold" data-sort="' . (int) $account['profit'] . '">' . $money($account['profit']) . '</td>
            <td class="text-end" data-sort="' . h((string) ($account['margin'] ?? '')) . '">' . $margin($account['margin']) . '</td>
        </tr>';
}
if ($output_accounts === '') {
    $output_accounts = '<tr data-pg-sort-fixed><td colspan="6" class="text-center text-body-secondary py-4">' . lang('Nothing was sold in this period.') . '</td></tr>';
}

// What the reader should know about the figures.
$notes = array();
if ((int) $report['estimated_lines'] > 0) {
    $notes[] = h(lang(array(
        'string' => '{var:1} line(s) sold before the ERP kept stock moves are costed at the product\'s average cost today.',
        'vars' => (int) $report['estimated_lines'],
    )));
}
if ((int) $report['uncosted_lines'] > 0) {
    $notes[] = h(lang(array(
        'string' => '{var:1} line(s) are of products no purchase invoice has given a cost yet, so their cost counts as nothing and their profit is overstated.',
        'vars' => (int) $report['uncosted_lines'],
    ))) . ' <a href="erp_stock.php">' . lang('Stock and cost') . '</a>';
}
if ($report['orders']['included'] && ((int) $report['orders']['count'] > 0)) {
    $notes[] = h(lang(array(
        'string' => '{var:1} completed order(s) without an invoice are counted as sales, by their order date; their goods are costed at the average cost today.',
        'vars' => (int) $report['orders']['count'],
    )));
}
$output_notes = empty($notes) ? '' : '<div class="alert alert-info small my-3"><ul class="mb-0 ps-3"><li>' . implode('</li><li>', $notes) . '</li></ul></div>';

// Orders the invoices do not cover: said plainly, one click to count them.
if (!$report['orders']['included'] && ((int) $report['orders']['count'] > 0)) {
    $output_notes = '<div class="alert alert-warning my-3 d-flex flex-wrap align-items-center gap-2">
            <span><i class="bi bi-cart-check me-1" aria-hidden="true"></i>' . h(lang(array(
                'string' => '{var:1} completed order(s) of this period have no invoice (about {var:2} without tax), so they are not in these figures.',
                'vars' => array((int) $report['orders']['count'], erp_money_out((int) $report['orders']['net'])),
            ))) . '</span>
            <a class="btn btn-sm btn-warning ms-auto" href="erp_profit.php?' . h(http_build_query(array('range' => 'dates', 'from' => $period['from'], 'to' => $period['to'], 'orders' => '1'))) . '">' . lang('Count them') . '</a>
        </div>' . $output_notes;
}

// The period picker.
$range = $period['range'];
$output_month_options = '';
for ($i = 0; $i < 24; $i++) {
    $month = date('Y-m', strtotime(date('Y-m-01') . ' -' . $i . ' month'));
    $output_month_options .= '<option value="' . h($month) . '"' . ((($range === 'month') && (substr($period['from'], 0, 7) === $month)) ? ' selected' : '') . '>'
        . h(get_month_name_from_number(substr($month, 5, 2)) . ' ' . substr($month, 0, 4)) . '</option>';
}
$output_quarter_options = '';
for ($i = 0; $i < 8; $i++) {
    $first = date('Y-m-01', strtotime(date('Y') . '-' . sprintf('%02d', ((int) ceil(date('n') / 3) - 1) * 3 + 1) . '-01 -' . ($i * 3) . ' month'));
    $value = substr($first, 0, 4) . '-Q' . (int) ceil(((int) substr($first, 5, 2)) / 3);
    $output_quarter_options .= '<option value="' . h($value) . '"' . ((($range === 'quarter') && ($period['from'] === $first)) ? ' selected' : '') . '>' . h(str_replace('-Q', ' / Q', $value)) . '</option>';
}
$output_year_options = '';
for ($year = (int) date('Y'); $year >= (int) date('Y') - 5; $year--) {
    $output_year_options .= '<option value="' . $year . '"' . ((($range === 'year') && ($period['from'] === $year . '-01-01')) ? ' selected' : '') . '>' . $year . '</option>';
}

$range_options = '';
foreach (array('month' => lang('Month'), 'quarter' => lang('Quarter'), 'year' => lang('Year'), 'dates' => lang('From - to')) as $key => $label) {
    $range_options .= '<option value="' . $key . '"' . (($range === $key) ? ' selected' : '') . '>' . h($label) . '</option>';
}

$export_url = 'erp_profit.php?' . h(http_build_query(array('range' => 'dates', 'from' => $period['from'], 'to' => $period['to'], 'orders' => $with_orders ? '1' : '0', 'export' => 'xlsx')));
$many_months = (count($report['months']) > 1);

echo
pg_page_shell([
    'title' => lang('Profit and loss'),
    'extra classes' => 'erp erp_profit',
    'icon' => 'erp',
    'heading' => lang('Profit and loss'),
    'heading_description' => h(lang(array('string' => 'What was sold, what it cost and what is left, in {var:1} and without tax.', 'vars' => $base))),
    'cancel' => false,
]) . '
<main id="content" class="container-fluid">
    <div class="row">
        <div class="col-12">
            ' . $liveform->output_errors() . '
            ' . $liveform->get_warnings() . '
            ' . $liveform->output_notices() . '

            <form method="get" action="erp_profit.php" class="card my-4" id="erp_profit_period">
                <div class="card-body row g-2 align-items-end">
                    <div class="col-6 col-md-2">
                        <label class="form-label small text-body-secondary mb-1" for="range">' . lang('Period') . '</label>
                        <select class="form-select form-select-sm" id="range" name="range">' . $range_options . '</select>
                    </div>
                    <div class="col-6 col-md-3" data-range="month"' . (($range === 'month') ? '' : ' hidden') . '>
                        <label class="form-label small text-body-secondary mb-1" for="month">' . lang('Month') . '</label>
                        <select class="form-select form-select-sm" id="month" name="month">' . $output_month_options . '</select>
                    </div>
                    <div class="col-6 col-md-3" data-range="quarter"' . (($range === 'quarter') ? '' : ' hidden') . '>
                        <label class="form-label small text-body-secondary mb-1" for="quarter">' . lang('Quarter') . '</label>
                        <select class="form-select form-select-sm" id="quarter" name="quarter">' . $output_quarter_options . '</select>
                    </div>
                    <div class="col-6 col-md-3" data-range="year"' . (($range === 'year') ? '' : ' hidden') . '>
                        <label class="form-label small text-body-secondary mb-1" for="year">' . lang('Year') . '</label>
                        <select class="form-select form-select-sm" id="year" name="year">' . $output_year_options . '</select>
                    </div>
                    <div class="col-6 col-md-2" data-range="dates"' . (($range === 'dates') ? '' : ' hidden') . '>
                        <label class="form-label small text-body-secondary mb-1" for="from">' . lang('From') . '</label>
                        <input type="date" class="form-control form-control-sm" id="from" name="from" value="' . h($period['from']) . '" />
                    </div>
                    <div class="col-6 col-md-2" data-range="dates"' . (($range === 'dates') ? '' : ' hidden') . '>
                        <label class="form-label small text-body-secondary mb-1" for="to">' . lang('To') . '</label>
                        <input type="date" class="form-control form-control-sm" id="to" name="to" value="' . h($period['to']) . '" />
                    </div>
                    <div class="col-12 col-md-auto">
                        <input type="hidden" name="orders" value="0" />
                        <div class="form-check form-switch mb-1">
                            <input class="form-check-input" type="checkbox" id="orders" name="orders" value="1"' . ($with_orders ? ' checked' : '') . ' />
                            <label class="form-check-label small" for="orders">' . lang('Count orders without an invoice') . '</label>
                        </div>
                    </div>
                    <div class="col-12 col-md-auto">
                        <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-arrow-repeat me-1" aria-hidden="true"></i>' . lang('Show') . '</button>
                        <a class="btn btn-sm btn-outline-secondary" href="' . $export_url . '"><i class="bi bi-file-earmark-spreadsheet me-1" aria-hidden="true"></i>' . lang('Excel') . '</a>
                    </div>
                    <div class="col-12 small text-body-secondary">' . h(prepare_form_data_for_output($period['from'], 'date', false) . ' – ' . prepare_form_data_for_output($period['to'], 'date', false)) . '</div>
                </div>
            </form>

            <div class="row g-3">
                <div class="col-6 col-lg-3"><div class="card h-100"><div class="card-body">
                    <div class="small text-uppercase text-body-secondary">' . lang('Net sales') . '</div>
                    <div class="h4 mb-0">' . $money($totals['revenue']) . '</div>
                    <div class="small text-body-secondary">' . h(lang(array('string' => '{var:1} returned', 'vars' => erp_money_out((int) $totals['returns'])))) . '</div>
                </div></div></div>
                <div class="col-6 col-lg-3"><div class="card h-100"><div class="card-body">
                    <div class="small text-uppercase text-body-secondary">' . lang('Gross profit') . '</div>
                    <div class="h4 mb-0">' . $money($totals['gross']) . '</div>
                    <div class="small text-body-secondary">' . h(lang(array('string' => 'Margin {var:1}', 'vars' => erp_profit_margin_text($gross_margin)))) . '</div>
                </div></div></div>
                <div class="col-6 col-lg-3"><div class="card h-100"><div class="card-body">
                    <div class="small text-uppercase text-body-secondary">' . lang('Costs and expenses') . '</div>
                    <div class="h4 mb-0">' . $money($costs) . '</div>
                    <div class="small text-body-secondary">' . h(lang(array('string' => '{var:1} expense(s)', 'vars' => (int) ($report['expenses']['total']['count'] ?? 0)))) . '</div>
                </div></div></div>
                <div class="col-6 col-lg-3"><div class="card h-100 ' . (($totals['net'] < 0) ? 'border-danger' : 'border-success') . '"><div class="card-body">
                    <div class="small text-uppercase text-body-secondary">' . lang('Net profit') . '</div>
                    <div class="h4 mb-0">' . $money($totals['net']) . '</div>
                    <div class="small text-body-secondary">' . h(lang(array('string' => 'Margin {var:1}', 'vars' => erp_profit_margin_text($net_margin)))) . '</div>
                </div></div></div>
            </div>

            ' . $output_notes . '

            <div class="row g-3 my-1">
                <div class="col-12 col-xl-5">
                    <div class="card h-100">
                        <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">' . lang('Profit and loss statement') . '</div>
                        <div class="card-body">' . $output_statement . '</div>
                    </div>
                </div>
                <div class="col-12 col-xl-7">
                    <div class="card h-100">
                        <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">' . lang('By month') . '</div>
                        <div class="card-body">
                            ' . ($many_months ? '<div style="height:16rem"><canvas id="erp_profit_chart" aria-label="' . h(lang('By month')) . '" role="img"></canvas></div>' : '') . '
                            <div class="table-responsive' . ($many_months ? ' mt-3' : '') . '">
                                <table class="table table-sm table-hover align-middle mb-0" data-pg-sort id="erp_profit_months">
                                    <thead>
                                        <tr>
                                            <th>' . lang('Month') . '</th>
                                            <th class="text-end">' . lang('Net sales') . '</th>
                                            <th class="text-end">' . lang('Cost of goods') . '</th>
                                            <th class="text-end">' . lang('Gross profit') . '</th>
                                            <th class="text-end">' . lang('Costs and expenses') . '</th>
                                            <th class="text-end">' . lang('Net profit') . '</th>
                                            <th class="text-end">' . lang('Profit margin') . '</th>
                                        </tr>
                                    </thead>
                                    <tbody>' . $output_months . '</tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-3 my-1">
                <div class="col-12 col-xxl-7">
                    <div class="card h-100">
                        <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">' . lang('By product') . '</div>
                        <div class="card-body p-0 table-responsive" style="max-height:36rem">
                            <table class="table table-sm table-hover align-middle mb-0" data-pg-sort id="erp_profit_products">
                                <thead class="sticky-top">
                                    <tr>
                                        <th>' . lang('Product') . '</th>
                                        <th class="text-end">' . lang('Quantity') . '</th>
                                        <th class="text-end">' . lang('Net sales') . '</th>
                                        <th class="text-end">' . lang('Cost') . '</th>
                                        <th class="text-end">' . lang('Gross profit') . '</th>
                                        <th class="text-end">' . lang('Profit margin') . '</th>
                                    </tr>
                                </thead>
                                <tbody>' . $output_products . '</tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-xxl-5">
                    <div class="card h-100">
                        <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">' . lang('By account') . '</div>
                        <div class="card-body p-0 table-responsive" style="max-height:36rem">
                            <table class="table table-sm table-hover align-middle mb-0" data-pg-sort id="erp_profit_accounts">
                                <thead class="sticky-top">
                                    <tr>
                                        <th>' . lang('Account') . '</th>
                                        <th class="text-end">' . lang('Documents') . '</th>
                                        <th class="text-end">' . lang('Net sales') . '</th>
                                        <th class="text-end">' . lang('Cost') . '</th>
                                        <th class="text-end">' . lang('Gross profit') . '</th>
                                        <th class="text-end">' . lang('Profit margin') . '</th>
                                    </tr>
                                </thead>
                                <tbody>' . $output_accounts . '</tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <p class="small text-body-secondary my-3">' . lang('A management view, not the books: sales and returns by the date they were issued, the cost of the goods as the stock moves recorded it, costs and expenses by their date. Cancelled documents and expenses count for nothing.') . '</p>
        </div>
    </div>
</main>
' . ($many_months ? '<script src="assets/lib/chartjs/chart.umd.min.js"></script>' : '') . '
<script>
(function () {
    var form = document.getElementById("erp_profit_period");
    var range = document.getElementById("range");
    if (form && range) {
        range.addEventListener("change", function () {
            form.querySelectorAll("[data-range]").forEach(function (el) { el.hidden = (el.getAttribute("data-range") !== range.value); });
            if (range.value !== "dates") { form.submit(); }
        });
        ["month", "quarter", "year", "orders"].forEach(function (name) {
            var select = document.getElementById(name);
            if (select) { select.addEventListener("change", function () { form.submit(); }); }
        });
    }

    var canvas = document.getElementById("erp_profit_chart");
    if (!canvas || typeof Chart === "undefined") { return; }
    var data = ' . json_encode($chart) . ';
    var styles = getComputedStyle(document.documentElement);
    var color = function (name, fallback) { return (styles.getPropertyValue(name) || "").trim() || fallback; };
    var locale = document.documentElement.lang || undefined;
    var format = function (value) { return Number(value).toLocaleString(locale, { maximumFractionDigits: 0 }); };
    new Chart(canvas, {
        data: {
            labels: data.labels,
            datasets: [
                { type: "bar", label: ' . json_encode(lang('Net sales')) . ', data: data.revenue, backgroundColor: color("--bs-primary", "#0d6efd"), borderRadius: 4 },
                { type: "bar", label: ' . json_encode(lang('Costs and expenses')) . ', data: data.costs, backgroundColor: color("--bs-secondary-bg", "#adb5bd"), borderRadius: 4 },
                { type: "line", label: ' . json_encode(lang('Net profit')) . ', data: data.net, borderColor: color("--bs-success", "#198754"), backgroundColor: color("--bs-success", "#198754"), tension: 0.3, pointRadius: 3 }
            ]
        },
        options: {
            maintainAspectRatio: false,
            interaction: { mode: "index", intersect: false },
            plugins: {
                legend: { position: "bottom" },
                tooltip: { callbacks: { label: function (ctx) { return ctx.dataset.label + ": " + format(ctx.parsed.y); } } }
            },
            scales: { y: { ticks: { callback: function (value) { return format(value); } } } }
        }
    });
})();
</script>
' .
output_footer();

$liveform->remove_form();
