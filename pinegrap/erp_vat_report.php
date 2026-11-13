<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * ERP - the tax report: the tax the sales of a period carried, the tax of its
 * purchases and expenses that is taken back, and the difference; by kind of
 * document and rate, with the withholding, the months and the documents
 * behind the figures. Worked out in includes/erp/vat_report.php, the same
 * way the accountant's pack writes its VAT sheet.
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
$liveform = new liveform('erp_vat_report');

// The period comes from the address; a value sent as an array (?month[]=x)
// counts as not given: it wrote "Array to string conversion" into the log.
$input = array(
    'range' => is_string($_GET['range'] ?? null) ? $_GET['range'] : 'month',
    'month' => is_string($_GET['month'] ?? null) ? $_GET['month'] : date('Y-m'),
    'quarter' => is_string($_GET['quarter'] ?? null) ? $_GET['quarter'] : (date('Y') . '-Q' . (int) ceil(date('n') / 3)),
    'year' => is_string($_GET['year'] ?? null) ? $_GET['year'] : date('Y'),
    'from' => is_string($_GET['from'] ?? null) ? $_GET['from'] : '',
    'to' => is_string($_GET['to'] ?? null) ? $_GET['to'] : '',
);

$period = erp_profit_period($input);

if ($period['error'] !== '') {
    $liveform->add_warning(h($period['error']));
}

$report = erp_vat_report($period['from'], $period['to']);
$totals = $report['totals'];
$kinds = $report['kinds'];
$base = erp_base_currency();
$title = erp_tax_label('report');
$tax_label = erp_tax_label('tax');
$tax2_label = erp_vat_tax2_label();
$labels = erp_vat_kind_labels();

// ------------------------------------------------------------------ export
if (($_GET['export'] ?? '') === 'xlsx') {
    $path = tempnam(sys_get_temp_dir(), 'pgv');

    if (($path === false) || !erp_vat_workbook($report, $path)) {
        if ($path !== false) {
            @unlink($path);
        }
        $liveform->mark_error('_error', lang('The workbook could not be written.'));
        go(PATH . SOFTWARE_DIRECTORY . '/erp_vat_report.php');
    }

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . erp_accountant_file_part($title) . '_' . $period['from'] . '_' . $period['to'] . '.xlsx"');
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
$has_withholding = !empty($report['withholding']) || ($totals['withheld_sales'] !== 0) || ($totals['withheld_purchases'] !== 0);
// The VAT withheld on sales is taken off the calculated VAT only when the
// ERP settings say so; otherwise it stays beside it.
$net_withholding = $report['net_withholding'] && ($totals['withheld_sales'] !== 0);

// The statement: calculated, deductible, difference.
// The label is the row header and keeps the row's weight: each row names
// its own, as the report table draws a row header at normal weight.
$statement_line = function ($label, $kurus, $class = '') use ($money) {
    $weight = (strpos($class, 'fw-bold') !== false) ? ' class="fw-bold"' : ((strpos($class, 'fw-semibold') !== false) ? ' class="fw-semibold"' : ' class="fw-normal"');

    return '<tr' . (($class !== '') ? ' class="' . $class . '"' : '') . '><th scope="row"' . $weight . '>' . $label . '</th><td class="text-end">' . $money($kurus) . '</td></tr>';
};

$output_statement = '
    <table class="pg-report-table">
        <tbody>
            ' . $statement_line(h($labels['sales']) . ' <span class="small fg-secondary">' . h(lang(array('string' => '{var:1} document(s)', 'vars' => (int) $kinds['sales']['count']))) . '</span>', $kinds['sales']['vat']) . '
            ' . $statement_line(h($labels['sales_return']) . ' <span class="small fg-secondary">' . h(lang(array('string' => '{var:1} document(s)', 'vars' => (int) $kinds['sales_return']['count']))) . '</span>', -$kinds['sales_return']['vat']) . '
            ' . $statement_line(h(erp_tax_label('calculated')), $totals['calculated'], 'fw-semibold border-top') . '
            ' . ($net_withholding ? $statement_line(h(lang('VAT withheld on sales, declared by the buyer')), -$totals['withheld_sales']) : '') . '
            ' . $statement_line(h($labels['purchase']) . ' <span class="small fg-secondary">' . h(lang(array('string' => '{var:1} document(s)', 'vars' => (int) $kinds['purchase']['count']))) . '</span>', $kinds['purchase']['vat']) . '
            ' . $statement_line(h($labels['purchase_return']) . ' <span class="small fg-secondary">' . h(lang(array('string' => '{var:1} document(s)', 'vars' => (int) $kinds['purchase_return']['count']))) . '</span>', -$kinds['purchase_return']['vat']) . '
            ' . $statement_line(h($labels['expense']) . ' <span class="small fg-secondary">' . h(lang('only the tax that is taken back')) . '</span>', $totals['deductible_expenses']) . '
            ' . $statement_line(h(erp_tax_label('deductible')), $totals['deductible'], 'fw-semibold border-top') . '
            ' . $statement_line(h(erp_vat_difference_label($report['net_withholding'])), $totals['difference'], 'fw-bold border-top border-2') . '
        </tbody>
    </table>';

// Figures kept apart from the difference.
$aside = array();
if ($has_withholding) {
    if (!$net_withholding) {
        $aside[] = $statement_line(h(lang('VAT withheld on sales')) . '<div class="small fg-secondary">' . h(lang('Left for the buyer to declare and pay.')) . '</div>', $totals['withheld_sales']);
    }
    $aside[] = $statement_line(h(lang('VAT withheld on purchases')) . '<div class="small fg-secondary">' . h(lang('Declared and paid by the store on the seller\'s behalf.')) . '</div>', $totals['withheld_purchases']);
}
if ($report['has_tax2']) {
    $aside[] = $statement_line(h(lang(array('string' => '{var:1} on sales (less returns)', 'vars' => $tax2_label))), $totals['tax2_calculated']);
    $aside[] = $statement_line(h(lang(array('string' => '{var:1} on purchases (less returns)', 'vars' => $tax2_label))), $totals['tax2_deductible']);
}
if ($totals['expense_not_deductible'] !== 0) {
    $aside[] = $statement_line(h(lang('Tax on expenses that is not deductible (a cost)')), $totals['expense_not_deductible']);
}
$output_aside = empty($aside) ? '' : '
    <table class="pg-report-table small mt-5">
        <tbody>' . implode('', $aside) . '</tbody>
    </table>';

// By rate: the order means something (kind, tax, rate), so it does not sort.
// A group shows its document type once; the later rows keep it for screen
// readers only, so no row reads with an empty Document type cell.
$output_rates = '';
$previous_kind = '';
foreach ($report['rates'] as $item) {
    $output_rates .= '
        <tr' . ((($previous_kind !== '') && ($previous_kind !== $item['key'])) ? ' class="border-top"' : '') . '>
            <td>' . (($previous_kind !== $item['key']) ? h($labels[$item['key']]) : ('<span class="visually-hidden">' . h($labels[$item['key']]) . '</span>')) . '</td>
            <td>' . h(($item['tax'] === 'tax2') ? $tax2_label : $tax_label) . '</td>
            <td class="text-end">' . h(erp_percent_text($item['rate'])) . '</td>
            <td class="text-end">' . (int) $item['documents'] . '</td>
            <td class="text-end">' . $money($item['net']) . '</td>
            <td class="text-end fw-semibold">' . $money($item['tax_amount']) . '</td>
        </tr>';
    $previous_kind = $item['key'];
}
if ($output_rates === '') {
    $output_rates = '<tr><td colspan="6" class="text-center fg-secondary py-7">' . lang('No invoices, returns or expenses in this period.') . '</td></tr>';
}

// Withholding by code.
$output_withholding = '';
if ($has_withholding) {
    $rows = '';
    foreach ($report['withholding'] as $item) {
        $rows .= '
            <tr>
                <td>' . h($labels[$item['key']]) . '</td>
                <td class="font-monospace">' . h($item['code']) . '</td>
                <td class="text-end">' . h(erp_percent_text($item['rate'])) . '</td>
                <td class="text-end">' . (int) $item['documents'] . '</td>
                <td class="text-end">' . $money($item['net']) . '</td>
                <td class="text-end">' . $money($item['tax_amount']) . '</td>
                <td class="text-end fw-semibold">' . $money($item['amount']) . '</td>
            </tr>';
    }

    $output_withholding = '
        <section class="pg-card pg-report-frame">
            <div class="pg-report-frame-head"><h3 class="pg-report-frame-title">' . lang('VAT withholding by code') . '</h3></div>
            <div class="pg-report-frame-body">
                <table class="pg-report-table">
                    <thead>
                        <tr>
                            <th>' . lang('Document type') . '</th>
                            <th>' . lang('Code') . '</th>
                            <th class="text-end">' . lang('Rate') . '</th>
                            <th class="text-end">' . lang('Number of documents') . '</th>
                            <th class="text-end">' . lang('Taxable Amount') . '</th>
                            <th class="text-end">' . h($tax_label) . '</th>
                            <th class="text-end">' . lang('Withheld') . '</th>
                        </tr>
                    </thead>
                    <tbody>' . $rows . '</tbody>
                </table>
            </div>
        </section>';
}

// Month by month, when the period has more than one.
$output_months = '';
if (count($report['months']) > 1) {
    $rows = '';
    foreach ($report['months'] as $month => $figures) {
        $rows .= '
            <tr>
                <td class="text-nowrap" data-sort="' . h(str_replace('-', '', $month)) . '"><a class="pg-link-body-emphasis" href="erp_vat_report.php?range=month&amp;month=' . h($month) . '">' . h(get_month_name_from_number(substr($month, 5, 2)) . ' ' . substr($month, 0, 4)) . '</a></td>
                <td class="text-end" data-sort="' . (int) $figures['calculated'] . '">' . $money($figures['calculated']) . '</td>
                ' . ($net_withholding ? '<td class="text-end" data-sort="' . (int) -$figures['withheld_sales'] . '">' . $money(-$figures['withheld_sales']) . '</td>' : '') . '
                <td class="text-end" data-sort="' . (int) $figures['deductible_purchases'] . '">' . $money($figures['deductible_purchases']) . '</td>
                <td class="text-end" data-sort="' . (int) $figures['deductible_expenses'] . '">' . $money($figures['deductible_expenses']) . '</td>
                <td class="text-end fw-semibold" data-sort="' . (int) $figures['difference'] . '">' . $money($figures['difference']) . '</td>
            </tr>';
    }

    $rows .= '
            <tr class="pg-report-total" data-pg-sort-fixed>
                <td>' . lang('Total') . '</td>
                <td class="text-end">' . $money($totals['calculated']) . '</td>
                ' . ($net_withholding ? '<td class="text-end">' . $money(-$totals['withheld_sales']) . '</td>' : '') . '
                <td class="text-end">' . $money($totals['deductible_purchases']) . '</td>
                <td class="text-end">' . $money($totals['deductible_expenses']) . '</td>
                <td class="text-end">' . $money($totals['difference']) . '</td>
            </tr>';

    $output_months = '
        <section class="pg-card pg-report-frame">
            <div class="pg-report-frame-head"><h3 class="pg-report-frame-title">' . lang('By month') . '</h3></div>
            <div class="pg-report-frame-body">
                <table class="pg-report-table" data-pg-sort id="erp_vat_months">
                    <thead>
                        <tr>
                            <th>' . lang('Month') . '</th>
                            <th class="text-end">' . h(erp_tax_label('calculated')) . '</th>
                            ' . ($net_withholding ? '<th class="text-end">' . lang('Withheld on sales') . '</th>' : '') . '
                            <th class="text-end">' . lang('Deductible, purchases') . '</th>
                            <th class="text-end">' . lang('Deductible, expenses') . '</th>
                            <th class="text-end">' . lang('Difference') . '</th>
                        </tr>
                    </thead>
                    <tbody>' . $rows . '</tbody>
                </table>
            </div>
        </section>';
}

// The documents behind the figures.
$shown_limit = 1000;
$output_documents = '';
$kinds_listed = array();
foreach (array_slice($report['documents'], 0, $shown_limit) as $item) {
    $kinds_listed[$item['kind']] = true;
    $rates = array();
    foreach ($item['rates'] as $rate) {
        $rates[] = erp_percent_text($rate);
    }

    $url = ($item['type'] === 'expense') ? 'edit_erp_expense.php?id=' . (int) $item['id'] : 'edit_erp_invoice.php?id=' . (int) $item['id'];
    $number = ($item['number'] !== '') ? $item['number'] : '#' . (int) $item['id'];
    $marks = '';
    if ($item['cancelled']) {
        $marks .= ' <span class="badge theme-secondary">' . lang('Cancelled') . '</span>';
    } elseif (($item['type'] === 'expense') && !$item['deductible']) {
        $marks .= ' <span class="badge badge-subtle theme-secondary border" title="' . h(lang('Tax on expenses that is not deductible (a cost)')) . '">' . lang('Not deductible') . '</span>';
    }
    if ($item['currency'] !== $base) {
        $marks .= ' <span class="badge badge-subtle theme-secondary border font-monospace">' . h($item['currency']) . '</span>';
    }

    $output_documents .= '
        <tr data-kind="' . h($item['kind']) . '"' . ($item['cancelled'] ? ' class="fg-secondary"' : '') . '>
            <td class="text-nowrap" data-sort="' . h(str_replace('-', '', $item['date'])) . '">' . h(prepare_form_data_for_output($item['date'], 'date', false)) . '</td>
            <td class="text-nowrap"><a class="pg-link-body-emphasis" href="' . h($url) . '">' . h($number) . '</a>' . $marks . '</td>
            <td>' . h($labels[$item['kind']]) . '</td>
            <td class="text-wrap text-break" style="min-width:10rem;max-width:280px">' . h($item['account']) . (($item['tax_number'] !== '') ? '<div class="small fg-secondary font-monospace">' . h($item['tax_number']) . '</div>' : '') . '</td>
            <td class="text-end text-nowrap">' . h(implode(', ', $rates)) . '</td>
            <td class="text-end" data-sort="' . (int) $item['net'] . '">' . $money($item['net']) . '</td>
            <td class="text-end fw-semibold" data-sort="' . (int) $item['vat'] . '">' . $money($item['vat']) . '</td>
            ' . ($report['has_tax2'] ? '<td class="text-end" data-sort="' . (int) $item['tax2'] . '">' . $money($item['tax2']) . '</td>' : '') . '
            ' . ($has_withholding ? '<td class="text-end" data-sort="' . (int) $item['withholding'] . '">' . $money($item['withholding']) . '</td>' : '') . '
            <td class="text-end" data-sort="' . (int) $item['total'] . '">' . $money($item['total']) . '</td>
        </tr>';
}
$columns = 8 + ($report['has_tax2'] ? 1 : 0) + ($has_withholding ? 1 : 0);
if ($output_documents === '') {
    $output_documents = '<tr data-pg-sort-fixed><td colspan="' . $columns . '" class="text-center fg-secondary py-7">' . lang('No invoices, returns or expenses in this period.') . '</td></tr>';
}

$kind_options = '<option value="">' . lang('All') . '</option>';
foreach ($labels as $key => $label) {
    if (isset($kinds_listed[$key])) {
        $kind_options .= '<option value="' . h($key) . '">' . h($label) . '</option>';
    }
}

$more_documents = (count($report['documents']) > $shown_limit)
    ? '<p class="small fg-secondary m-5">' . h(lang(array('string' => 'The first {var:1} of {var:2} are shown; the workbook has them all.', 'vars' => array($shown_limit, count($report['documents']))))) . '</p>'
    : '';

// What the reader should know about the figures.
$output_notes = '';
if ((int) $report['orders']['count'] > 0) {
    $output_notes .= '<div class="alert theme-warning my-5"><i class="bi bi-cart-check me-1" aria-hidden="true"></i>' . h(lang(array(
        'string' => '{var:1} completed order(s) of this period have no invoice (about {var:2} of tax), so their tax is not in these figures.',
        'vars' => array((int) $report['orders']['count'], erp_money_out((int) $report['orders']['tax'])),
    ))) . '</div>';
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

$export_url = 'erp_vat_report.php?' . h(http_build_query(array('range' => 'dates', 'from' => $period['from'], 'to' => $period['to'], 'export' => 'xlsx')));

// The figures and the chart over the tables (includes/fn/report_view.php),
// read from the same totals and months the tables print.
$report_view_money = function ($kurus) {
    return erp_money_out((int) $kurus);
};
$report_view_month_names = array();

foreach (array_keys($report['months']) as $month) {
    $report_view_month_names[$month] = get_month_name_from_number(substr($month, 5, 2)) . ' ' . substr($month, 0, 4);
}

$report_view_names = array(
    'calculated' => erp_tax_label('calculated'),
    'deductible' => erp_tax_label('deductible'),
    'kinds' => $labels,
    'months' => $report_view_month_names,
);
$report_view = array(
    'figures' => pg_report_view_vat_figures($totals, $has_withholding, $report_view_names, $report_view_money),
    'chart' => pg_report_view_vat_chart($report, $report_view_names, $report_view_money),
    'strings' => pg_report_view_strings(),
);

echo
pg_page_shell([
    'title' => $title,
    'extra classes' => 'erp erp_vat_report pg-measure-report',
    'icon' => 'erp',
    'heading' => h($title),
    'heading_description' => h(lang(array('string' => 'The tax on the invoices, returns and expenses of a period, by rate, in {var:1}.', 'vars' => $base))),
    'cancel' => false,
]) . '
<main id="content" class="container-fluid">
    <div class="row">
        <div class="col-12">
            ' . $liveform->output_errors() . '
            ' . $liveform->get_warnings() . '
            ' . $liveform->output_notices() . '

            <form method="get" action="erp_vat_report.php" class="pg-report-tools" id="erp_vat_period">
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
                    <label class="pg-report-label" for="from">' . lang('Start date') . '</label>
                    <input type="date" data-pg-date class="form-control" id="from" name="from" min="' . ERP_PROFIT_FIRST_YEAR . '-01-01" max="' . ERP_PROFIT_LAST_YEAR . '-12-31" value="' . h($period['from']) . '" />
                </span>
                <span class="pg-report-field" data-range="dates"' . (($range === 'dates') ? '' : ' hidden') . '>
                    <label class="pg-report-label" for="to">' . lang('End date') . '</label>
                    <input type="date" data-pg-date class="form-control" id="to" name="to" min="' . ERP_PROFIT_FIRST_YEAR . '-01-01" max="' . ERP_PROFIT_LAST_YEAR . '-12-31" value="' . h($period['to']) . '" />
                </span>
                <button type="submit" class="pg-button pg-button-quiet"><i class="bi bi-arrow-repeat" aria-hidden="true"></i>' . lang('Show') . '</button>
                <span class="pg-report-range"><i class="bi bi-calendar3" aria-hidden="true"></i>' . h(prepare_form_data_for_output($period['from'], 'date', false) . ' – ' . prepare_form_data_for_output($period['to'], 'date', false)) . '</span>
                <a class="pg-button pg-button-quiet ms-auto" href="' . $export_url . '"><i class="bi bi-file-earmark-spreadsheet" aria-hidden="true"></i>' . lang('Excel') . '</a>
            </form>

            <section class="pg-report-view" id="pg_report_view" aria-label="' . h(lang('Summary')) . '">
                <div class="pg-report-view-figures"></div>
                <div class="pg-report-view-chart"></div>
                <script type="application/json" id="pg_report_view_data">' . (string) json_encode($report_view, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) . '</script>
            </section>
            ' . pg_js_bundle_tags('assets/js/pg_report_view.min.js') . '

            ' . $output_notes . '

            <div class="row g-5 mb-7">
                <div class="col-12 xl:col-5">
                    <section class="pg-card pg-report-frame h-100 mb-0">
                        <div class="pg-report-frame-head"><h3 class="pg-report-frame-title">' . lang('Summary') . '</h3></div>
                        <div class="pg-report-frame-body">' . $output_statement . $output_aside . '</div>
                    </section>
                </div>
                <div class="col-12 xl:col-7">
                    <section class="pg-card pg-report-frame h-100 mb-0">
                        <div class="pg-report-frame-head"><h3 class="pg-report-frame-title">' . lang('By rate') . '</h3></div>
                        <div class="pg-report-frame-body">
                            <table class="pg-report-table" id="erp_vat_rates">
                                <thead>
                                    <tr>
                                        <th>' . lang('Document type') . '</th>
                                        <th>' . lang('Tax') . '</th>
                                        <th class="text-end">' . lang('Rate') . '</th>
                                        <th class="text-end">' . lang('Number of documents') . '</th>
                                        <th class="text-end">' . lang('Taxable Amount') . '</th>
                                        <th class="text-end">' . lang('Tax amount') . '</th>
                                    </tr>
                                </thead>
                                <tbody>' . $output_rates . '</tbody>
                            </table>
                        </div>
                    </section>
                </div>
            </div>

            ' . $output_withholding . '
            ' . $output_months . '

            <section class="pg-card pg-report-frame">
                <div class="pg-report-frame-head">
                    <h3 class="pg-report-frame-title">' . lang('Documents of the period') . '</h3>
                    <label class="pg-report-label ms-auto" for="erp_vat_kind">' . lang('Document type') . '</label>
                    <select class="form-control" id="erp_vat_kind">' . $kind_options . '</select>
                </div>
                <div class="pg-report-frame-body table-responsive" style="max-height:40rem;scroll-padding-top:2.5rem">
                    <table class="pg-report-table" data-pg-sort id="erp_vat_documents">
                        <thead class="sticky-top">
                            <tr>
                                <th>' . lang('Date') . '</th>
                                <th>' . lang('Document Number') . '</th>
                                <th>' . lang('Document type') . '</th>
                                <th>' . lang('Account') . '</th>
                                <th class="text-end">' . lang('Rate') . '</th>
                                <th class="text-end">' . lang('Taxable Amount') . '</th>
                                <th class="text-end">' . h($tax_label) . '</th>
                                ' . ($report['has_tax2'] ? '<th class="text-end">' . h($tax2_label) . '</th>' : '') . '
                                ' . ($has_withholding ? '<th class="text-end">' . lang('Withholding') . '</th>' : '') . '
                                <th class="text-end">' . lang('Total') . '</th>
                            </tr>
                        </thead>
                        <tbody>' . $output_documents . '</tbody>
                    </table>
                </div>
                ' . $more_documents . '
            </section>

            <p class="small fg-secondary my-5">' . lang('A summary of the invoices, returns and expenses the ERP keeps, not a tax return: check it with your accountant before you file. Documents count by the date they were issued, expenses by their date; cancelled documents and drafts count for nothing, and the tax on an expense that is not taken back is a cost, left out of the deductible tax.') . '</p>
        </div>
    </div>
</main>
<script>
(function () {
    var form = document.getElementById("erp_vat_period");
    var range = document.getElementById("range");
    if (form && range) {
        range.addEventListener("change", function () {
            form.querySelectorAll("[data-range]").forEach(function (el) { el.hidden = (el.getAttribute("data-range") !== range.value); });
            if (range.value !== "dates") { form.submit(); }
        });
        ["month", "quarter", "year"].forEach(function (name) {
            var select = document.getElementById(name);
            if (select) { select.addEventListener("change", function () { form.submit(); }); }
        });
    }

    var kind = document.getElementById("erp_vat_kind");
    var table = document.getElementById("erp_vat_documents");
    if (kind && table) {
        kind.addEventListener("change", function () {
            table.querySelectorAll("tbody tr[data-kind]").forEach(function (row) {
                row.hidden = (kind.value !== "") && (row.getAttribute("data-kind") !== kind.value);
            });
        });
    }
})();
</script>
' . get_date_picker_format() . pg_fields_script() .
output_footer();

$liveform->remove_form();
