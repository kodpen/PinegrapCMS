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

// The period comes from the address; a value sent as an array (?month[]=x)
// counts as not given: it wrote "Array to string conversion" into the log.
$input = array(
    'range' => is_string($_GET['range'] ?? null) ? $_GET['range'] : 'year',
    'month' => is_string($_GET['month'] ?? null) ? $_GET['month'] : date('Y-m'),
    'quarter' => is_string($_GET['quarter'] ?? null) ? $_GET['quarter'] : (date('Y') . '-Q' . (int) ceil(date('n') / 3)),
    'year' => is_string($_GET['year'] ?? null) ? $_GET['year'] : date('Y'),
    'from' => is_string($_GET['from'] ?? null) ? $_GET['from'] : '',
    'to' => is_string($_GET['to'] ?? null) ? $_GET['to'] : '',
);

$period = erp_profit_period($input);

// Whether the completed orders without an invoice count: asked on the form,
// then remembered in this browser (the ERP dashboard reads the same choice).
$with_orders = erp_profit_orders_choice((isset($_GET['orders']) && is_string($_GET['orders'])) ? $_GET['orders'] : null);

if ($period['error'] !== '') {
    $liveform->add_warning(h($period['error']));
}

$report = erp_profit_report($period['from'], $period['to'], array('orders' => $with_orders));
$totals = $report['totals'];
$base = erp_base_currency();

// ------------------------------------------------------------------ export
if (($_GET['export'] ?? '') === 'xlsx') {
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

    return '<span class="text-nowrap' . (($kurus < 0) ? ' fg-danger' : '') . ($class !== '' ? ' ' . $class : '') . '">' . h(erp_money_out($kurus)) . '</span>';
};
$margin = function ($value) {
    return ($value === null) ? '<span class="fg-secondary">—</span>' : h(erp_profit_margin_text($value));
};

$gross_margin = erp_profit_margin($totals['gross'], $totals['revenue']);
$net_margin = erp_profit_margin($totals['net'], $totals['revenue']);
$costs = $totals['purchase_costs'] + $totals['expenses'];

// The statement.
$output_expense_lines = '';
foreach ((array) $report['expenses']['categories'] as $category) {
    $output_expense_lines .= '
        <tr class="pg-report-detail">
            <th scope="row" class="ps-7 fg-secondary fw-normal">' . h($category['name']) . (($category['code'] !== '') ? ' <span class="font-monospace">' . h($category['code']) . '</span>' : '') . '</th>
            <td class="text-end fg-secondary">' . $money(-$category['cost']) . '</td>
        </tr>';
}

$output_statement = '
    <table class="pg-report-table">
        <tbody>
            <tr><th scope="row" class="fw-normal">' . lang('Sales') . '</th><td class="text-end">' . $money($totals['sales']) . '</td></tr>
            ' . ($report['orders']['included'] ? '<tr><th scope="row" class="fw-normal">' . lang('Completed orders without an invoice') . '<div class="pg-text-meta fg-secondary">' . h(lang(array('string' => '{var:1} order(s), by the order date', 'vars' => (int) $report['orders']['count']))) . '</div></th><td class="text-end">' . $money($totals['order_sales']) . '</td></tr>' : '') . '
            <tr><th scope="row" class="fw-normal">' . lang('Returns') . '</th><td class="text-end">' . $money(-$totals['returns']) . '</td></tr>
            <tr class="fw-semibold border-top"><th scope="row" class="fw-semibold">' . lang('Net sales') . '</th><td class="text-end">' . $money($totals['revenue']) . '</td></tr>
            <tr><th scope="row" class="fw-normal">' . lang('Cost of goods sold')
                . (((int) $totals['cogs_estimated'] !== 0) ? '<div class="pg-text-meta fg-secondary">' . h(lang(array('string' => 'of which {var:1} estimated at today\'s average cost', 'vars' => erp_money_out((int) $totals['cogs_estimated'])))) . '</div>' : '')
                . '</th><td class="text-end">' . $money(-$totals['cogs']) . '</td></tr>
            <tr class="fw-semibold border-top"><th scope="row" class="fw-semibold">' . lang('Gross profit') . ' <span class="fg-secondary fw-normal pg-text-meta">' . $margin($gross_margin) . '</span></th><td class="text-end">' . $money($totals['gross']) . '</td></tr>
            <tr><th scope="row" class="fw-normal">' . lang('Costs from purchase invoices') . '<div class="pg-text-meta fg-secondary">' . lang('Purchase lines with no product: services and costs bought on an invoice.') . '</div></th><td class="text-end">' . $money(-$totals['purchase_costs']) . '</td></tr>
            <tr><th scope="row" class="fw-normal">' . lang('Expenses') . ' <a class="pg-text-meta" href="erp_expenses.php?' . h(http_build_query(array('period' => 'dates', 'from' => $period['from'], 'to' => $period['to']))) . '">' . lang('List') . '</a></th><td class="text-end">' . $money(-$totals['expenses']) . '</td></tr>
            ' . $output_expense_lines . '
            <tr class="fw-bold border-top border-2"><th scope="row">' . lang('Net profit') . ' <span class="fg-secondary fw-normal pg-text-meta">' . $margin($net_margin) . '</span></th><td class="text-end">' . $money($totals['net']) . '</td></tr>
        </tbody>
    </table>';

// Month by month.
$output_months = '';
$month_names = array();

foreach ($report['months'] as $month => $figures) {
    $label = get_month_name_from_number(substr($month, 5, 2)) . ' ' . substr($month, 0, 4);
    $month_names[$month] = $label;

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
        <tr class="pg-report-total" data-pg-sort-fixed>
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
                . ((($product['sku'] !== '') && ($product['sku'] !== $product['name'])) ? '<div class="pg-text-meta fg-secondary font-monospace">' . h($product['sku']) . '</div>' : '')
                . (((int) $product['uncosted'] > 0) ? '<div class="pg-text-meta fg-emphasis-warning">' . lang('no cost known') . '</div>' : '') . '</td>
            <td class="text-end" data-sort="' . h((string) $product['quantity']) . '">' . h(erp_quantity_text($product['quantity'])) . '</td>
            <td class="text-end" data-sort="' . (int) $product['revenue'] . '">' . $money($product['revenue']) . '</td>
            <td class="text-end" data-sort="' . (int) $product['cost'] . '">' . $money($product['cost'])
                . (((int) $product['estimated'] > 0) ? ' <i class="bi bi-info-circle fg-secondary" title="' . h(lang('Partly estimated at today\'s average cost')) . '"></i>' : '') . '</td>
            <td class="text-end fw-semibold" data-sort="' . (int) $product['profit'] . '">' . $money($product['profit']) . '</td>
            <td class="text-end" data-sort="' . h((string) ($product['margin'] ?? '')) . '">' . $margin($product['margin']) . '</td>
        </tr>';
}
if ($output_products === '') {
    $output_products = '<tr data-pg-sort-fixed><td colspan="6" class="text-center fg-secondary py-7">' . lang('Nothing was sold in this period.') . '</td></tr>';
}

$output_accounts = '';
foreach (array_slice($report['accounts'], 0, 500) as $account) {
    $output_accounts .= '
        <tr>
            <td>' . (($account['account_id'] > 0) ? '<a class="pg-link-body-emphasis" href="edit_erp_account.php?id=' . (int) $account['account_id'] . '">' . h($account['title']) . '</a>' : h($account['title'])) . '</td>
            <td class="text-end" data-sort="' . (int) $account['documents'] . '">' . (int) $account['documents'] . '</td>
            <td class="text-end" data-sort="' . (int) $account['revenue'] . '">' . $money($account['revenue']) . '</td>
            <td class="text-end" data-sort="' . (int) $account['cost'] . '">' . $money($account['cost']) . '</td>
            <td class="text-end fw-semibold" data-sort="' . (int) $account['profit'] . '">' . $money($account['profit']) . '</td>
            <td class="text-end" data-sort="' . h((string) ($account['margin'] ?? '')) . '">' . $margin($account['margin']) . '</td>
        </tr>';
}
if ($output_accounts === '') {
    $output_accounts = '<tr data-pg-sort-fixed><td colspan="6" class="text-center fg-secondary py-7">' . lang('Nothing was sold in this period.') . '</td></tr>';
}

// What the reader should know about the figures.
$notes = array();
if ((int) $report['estimated_lines'] > 0) {
    $notes[] = h(lang(array(
        'string' => '{var:1} line(s) sold before the ERP knew their cost are costed at the product\'s average cost today.',
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
$output_notes = empty($notes) ? '' : '<div class="alert theme-info small my-5"><ul class="mb-0 ps-5"><li>' . implode('</li><li>', $notes) . '</li></ul></div>';

// Orders the invoices do not cover: said plainly, one click to count them.
if (!$report['orders']['included'] && ((int) $report['orders']['count'] > 0)) {
    $output_notes = '<div class="alert theme-warning my-5 d-flex flex-wrap align-items-center gap-3">
            <span><i class="bi bi-cart-check me-1" aria-hidden="true"></i>' . h(lang(array(
                'string' => '{var:1} completed order(s) of this period have no invoice (about {var:2} without tax), so they are not in these figures.',
                'vars' => array((int) $report['orders']['count'], erp_money_out((int) $report['orders']['net'])),
            ))) . '</span>
            <a class="btn btn-sm btn-solid theme-warning ms-auto" href="erp_profit.php?' . h(http_build_query(array('range' => 'dates', 'from' => $period['from'], 'to' => $period['to'], 'orders' => '1'))) . '">' . lang('Count them') . '</a>
        </div>' . $output_notes;
}

// The period picker.
$range = $period['range'];
$period_options = erp_profit_period_options($period);
$output_month_options = $period_options['month'];
$output_quarter_options = $period_options['quarter'];
$output_year_options = $period_options['year'];

$range_options = '';
foreach (array('month' => lang('Month'), 'quarter' => lang('Quarter'), 'year' => lang('Year'), 'dates' => lang('From - to')) as $key => $label) {
    $range_options .= '<option value="' . $key . '"' . (($range === $key) ? ' selected' : '') . '>' . h($label) . '</option>';
}

$export_url = 'erp_profit.php?' . h(http_build_query(array('range' => 'dates', 'from' => $period['from'], 'to' => $period['to'], 'orders' => $with_orders ? '1' : '0', 'export' => 'xlsx')));
// The chart over the tables (includes/fn/report_view.php), drawn by
// assets/js/pg_report_view.js from the same months the By month table prints.
$report_view = array(
    'figures' => array(),
    'chart' => pg_report_view_profit_chart($report['months'], $month_names, function ($kurus) {
        return erp_money_out((int) $kurus);
    }),
    'strings' => pg_report_view_strings(),
);

// The summary figures as the report screens' cards; a figure below zero
// keeps its red, a net loss marks its note.
$figure_class = function ($kurus) {
    return ((int) $kurus < 0) ? 'fg-danger' : '';
};
$output_figures = pg_report_figures_html(array(
    pg_report_view_figure(lang('Net sales'), erp_money_out((int) $totals['revenue']), 'bi-bar-chart', 'teal', lang(array('string' => '{var:1} returned', 'vars' => erp_money_out((int) $totals['returns'])))) + array('class' => $figure_class($totals['revenue'])),
    pg_report_view_figure(lang('Gross profit'), erp_money_out((int) $totals['gross']), 'bi-graph-up', 'blue', lang(array('string' => 'Margin {var:1}', 'vars' => erp_profit_margin_text($gross_margin)))) + array('class' => $figure_class($totals['gross'])),
    pg_report_view_figure(lang('Costs and expenses'), erp_money_out((int) $costs), 'bi-receipt-cutoff', 'rose', lang(array('string' => '{var:1} expense(s)', 'vars' => (int) ($report['expenses']['total']['count'] ?? 0)))) + array('class' => $figure_class($costs)),
    pg_report_view_figure(lang('Net profit'), erp_money_out((int) $totals['net']), 'bi-cash-stack', 'violet', lang(array('string' => 'Margin {var:1}', 'vars' => erp_profit_margin_text($net_margin))), ($totals['net'] < 0) ? 'bad' : '') + array('class' => $figure_class($totals['net'])),
));

echo
pg_page_shell([
    'title' => lang('Profit and loss'),
    'extra classes' => 'erp erp_profit pg-measure-report',
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

            <form method="get" action="erp_profit.php" class="pg-report-tools" id="erp_profit_period">
                <span class="pg-report-field">
                    <label class="pg-report-label" for="range">' . lang('Period') . '</label>
                    <select class="form-control" id="range" name="range">' . $range_options . '</select>
                </span>
                <span class="pg-report-field" data-range="month"' . (($range === 'month') ? '' : ' hidden') . '>
                    <label class="visually-hidden" for="month">' . lang('Month') . '</label>
                    <select class="form-control" id="month" name="month">' . $output_month_options . '</select>
                </span>
                <span class="pg-report-field" data-range="quarter"' . (($range === 'quarter') ? '' : ' hidden') . '>
                    <label class="visually-hidden" for="quarter">' . lang('Quarter') . '</label>
                    <select class="form-control" id="quarter" name="quarter">' . $output_quarter_options . '</select>
                </span>
                <span class="pg-report-field" data-range="year"' . (($range === 'year') ? '' : ' hidden') . '>
                    <label class="visually-hidden" for="year">' . lang('Year') . '</label>
                    <select class="form-control" id="year" name="year">' . $output_year_options . '</select>
                </span>
                <span class="pg-report-field" data-range="dates"' . (($range === 'dates') ? '' : ' hidden') . '>
                    <label class="pg-report-label" for="from">' . lang('Start') . '</label>
                    <input type="date" data-pg-date class="form-control" id="from" name="from" min="' . ERP_PROFIT_FIRST_YEAR . '-01-01" max="' . ERP_PROFIT_LAST_YEAR . '-12-31" value="' . h($period['from']) . '" />
                </span>
                <span class="pg-report-field" data-range="dates"' . (($range === 'dates') ? '' : ' hidden') . '>
                    <label class="pg-report-label" for="to">' . lang('End') . '</label>
                    <input type="date" data-pg-date class="form-control" id="to" name="to" min="' . ERP_PROFIT_FIRST_YEAR . '-01-01" max="' . ERP_PROFIT_LAST_YEAR . '-12-31" value="' . h($period['to']) . '" />
                </span>
                <span class="pg-report-field">
                    <input type="hidden" name="orders" value="0" />
                    <input class="pg-switch" type="checkbox" id="orders" name="orders" value="1"' . ($with_orders ? ' checked' : '') . ' />
                    <label class="pg-report-label" for="orders">' . lang('Count orders without an invoice') . '</label>
                </span>
                <button type="submit" class="pg-button pg-button-quiet"><i class="bi bi-arrow-repeat" aria-hidden="true"></i>' . lang('Show') . '</button>
                <span class="pg-report-range"><i class="bi bi-calendar3" aria-hidden="true"></i>' . h(prepare_form_data_for_output($period['from'], 'date', false) . ' – ' . prepare_form_data_for_output($period['to'], 'date', false)) . '</span>
                <a class="pg-button pg-button-quiet ms-auto" href="' . $export_url . '"><i class="bi bi-file-earmark-spreadsheet" aria-hidden="true"></i>' . lang('Excel') . '</a>
            </form>

            <section class="pg-report-view" id="pg_report_view" aria-label="' . h(lang('Summary')) . '">
                <div class="pg-report-view-figures">' . $output_figures . '</div>
                <div class="pg-report-view-chart"></div>
                <script type="application/json" id="pg_report_view_data">' . (string) json_encode($report_view, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) . '</script>
            </section>
            ' . pg_js_bundle_tags('assets/js/pg_report_view.min.js') . '

            ' . $output_notes . '

            <div class="row g-5 mb-7">
                <div class="col-12 2xl:col-5">
                    <section class="pg-card pg-report-frame h-100 mb-0">
                        <div class="pg-report-frame-head"><h3 class="pg-report-frame-title">' . lang('Profit and loss statement') . '</h3></div>
                        <div class="pg-report-frame-body">' . $output_statement . '</div>
                    </section>
                </div>
                <div class="col-12 2xl:col-7">
                    <section class="pg-card pg-report-frame h-100 mb-0">
                        <div class="pg-report-frame-head"><h3 class="pg-report-frame-title">' . lang('By month') . '</h3></div>
                            <div class="pg-report-frame-body">
                                <table class="pg-report-table" data-pg-sort id="erp_profit_months">
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
                    </section>
                </div>
            </div>

            <div class="row g-5">
                <div class="col-12 2xl:col-7">
                    <section class="pg-card pg-report-frame h-100 mb-0">
                        <div class="pg-report-frame-head"><h3 class="pg-report-frame-title">' . lang('By product') . '</h3></div>
                        <div class="pg-report-frame-body table-responsive" style="max-height:36rem">
                            <table class="pg-report-table" data-pg-sort id="erp_profit_products">
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
                    </section>
                </div>
                <div class="col-12 2xl:col-5">
                    <section class="pg-card pg-report-frame h-100 mb-0">
                        <div class="pg-report-frame-head"><h3 class="pg-report-frame-title">' . lang('By account') . '</h3></div>
                        <div class="pg-report-frame-body table-responsive" style="max-height:36rem;scroll-padding-top:2.5rem">
                            <table class="pg-report-table" data-pg-sort id="erp_profit_accounts">
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
                    </section>
                </div>
            </div>

            <p class="pg-text-meta fg-secondary my-5">' . lang('A management view, not the books: sales and returns by the date they were issued, the cost of the goods as the stock moves recorded it, costs and expenses by their date. Cancelled documents and expenses count for nothing.') . '</p>
        </div>
    </div>
</main>
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

})();
</script>
' . get_date_picker_format() . pg_fields_script() .
output_footer();

$liveform->remove_form();
