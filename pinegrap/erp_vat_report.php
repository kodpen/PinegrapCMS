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

$input = array(
    'range' => (string) ($_GET['range'] ?? 'month'),
    'month' => (string) ($_GET['month'] ?? date('Y-m')),
    'quarter' => (string) ($_GET['quarter'] ?? (date('Y') . '-Q' . (int) ceil(date('n') / 3))),
    'year' => (string) ($_GET['year'] ?? date('Y')),
    'from' => (string) ($_GET['from'] ?? ''),
    'to' => (string) ($_GET['to'] ?? ''),
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
if ((string) ($_GET['export'] ?? '') === 'xlsx') {
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

    return '<span class="text-nowrap' . (($kurus < 0) ? ' text-danger' : '') . ($class !== '' ? ' ' . $class : '') . '">' . h(erp_money_out($kurus)) . '</span>';
};
$has_withholding = !empty($report['withholding']) || ($totals['withheld_sales'] !== 0) || ($totals['withheld_purchases'] !== 0);
// The VAT withheld on sales is taken off the calculated VAT only when the
// ERP settings say so; otherwise it stays beside it.
$net_withholding = $report['net_withholding'] && ($totals['withheld_sales'] !== 0);

// The statement: calculated, deductible, difference.
$statement_line = function ($label, $kurus, $class = '') use ($money) {
    return '<tr' . (($class !== '') ? ' class="' . $class . '"' : '') . '><td>' . $label . '</td><td class="text-end">' . $money($kurus) . '</td></tr>';
};

$output_statement = '
    <table class="table table-sm align-middle mb-0">
        <tbody>
            ' . $statement_line(h($labels['sales']) . ' <span class="small text-body-secondary">' . h(lang(array('string' => '{var:1} document(s)', 'vars' => (int) $kinds['sales']['count']))) . '</span>', $kinds['sales']['vat']) . '
            ' . $statement_line(h($labels['sales_return']) . ' <span class="small text-body-secondary">' . h(lang(array('string' => '{var:1} document(s)', 'vars' => (int) $kinds['sales_return']['count']))) . '</span>', -$kinds['sales_return']['vat']) . '
            ' . $statement_line(h(erp_tax_label('calculated')), $totals['calculated'], 'fw-semibold border-top') . '
            ' . ($net_withholding ? $statement_line(h(lang('VAT withheld on sales, declared by the buyer')), -$totals['withheld_sales']) : '') . '
            ' . $statement_line(h($labels['purchase']) . ' <span class="small text-body-secondary">' . h(lang(array('string' => '{var:1} document(s)', 'vars' => (int) $kinds['purchase']['count']))) . '</span>', $kinds['purchase']['vat']) . '
            ' . $statement_line(h($labels['purchase_return']) . ' <span class="small text-body-secondary">' . h(lang(array('string' => '{var:1} document(s)', 'vars' => (int) $kinds['purchase_return']['count']))) . '</span>', -$kinds['purchase_return']['vat']) . '
            ' . $statement_line(h($labels['expense']) . ' <span class="small text-body-secondary">' . h(lang('only the tax that is taken back')) . '</span>', $totals['deductible_expenses']) . '
            ' . $statement_line(h(erp_tax_label('deductible')), $totals['deductible'], 'fw-semibold border-top') . '
            ' . $statement_line(h(erp_vat_difference_label($report['net_withholding'])), $totals['difference'], 'fw-bold border-top border-2') . '
        </tbody>
    </table>';

// Figures kept apart from the difference.
$aside = array();
if ($has_withholding) {
    if (!$net_withholding) {
        $aside[] = $statement_line(h(lang('VAT withheld on sales')) . '<div class="small text-body-secondary">' . h(lang('Left for the buyer to declare and pay.')) . '</div>', $totals['withheld_sales']);
    }
    $aside[] = $statement_line(h(lang('VAT withheld on purchases')) . '<div class="small text-body-secondary">' . h(lang('Declared and paid by the store on the seller\'s behalf.')) . '</div>', $totals['withheld_purchases']);
}
if ($report['has_tax2']) {
    $aside[] = $statement_line(h(lang(array('string' => '{var:1} on sales (less returns)', 'vars' => $tax2_label))), $totals['tax2_calculated']);
    $aside[] = $statement_line(h(lang(array('string' => '{var:1} on purchases (less returns)', 'vars' => $tax2_label))), $totals['tax2_deductible']);
}
if ($totals['expense_not_deductible'] !== 0) {
    $aside[] = $statement_line(h(lang('Tax on expenses that is not deductible (a cost)')), $totals['expense_not_deductible']);
}
$output_aside = empty($aside) ? '' : '
    <table class="table table-sm align-middle small mt-3 mb-0">
        <tbody>' . implode('', $aside) . '</tbody>
    </table>';

// By rate: the order means something (kind, tax, rate), so it does not sort.
$output_rates = '';
$previous_kind = '';
foreach ($report['rates'] as $item) {
    $output_rates .= '
        <tr' . ((($previous_kind !== '') && ($previous_kind !== $item['key'])) ? ' class="border-top"' : '') . '>
            <td>' . (($previous_kind !== $item['key']) ? h($labels[$item['key']]) : '') . '</td>
            <td>' . h(($item['tax'] === 'tax2') ? $tax2_label : $tax_label) . '</td>
            <td class="text-end">' . h(erp_percent_text($item['rate'])) . '</td>
            <td class="text-end">' . (int) $item['documents'] . '</td>
            <td class="text-end">' . $money($item['net']) . '</td>
            <td class="text-end fw-semibold">' . $money($item['tax_amount']) . '</td>
        </tr>';
    $previous_kind = $item['key'];
}
if ($output_rates === '') {
    $output_rates = '<tr><td colspan="6" class="text-center text-body-secondary py-4">' . lang('No invoices, returns or expenses in this period.') . '</td></tr>';
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
        <div class="card my-3">
            <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">' . lang('VAT withholding by code') . '</div>
            <div class="card-body p-0 table-responsive">
                <table class="table table-sm table-hover align-middle mb-0">
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
        </div>';
}

// Month by month, when the period has more than one.
$output_months = '';
if (count($report['months']) > 1) {
    $rows = '';
    foreach ($report['months'] as $month => $figures) {
        $rows .= '
            <tr>
                <td class="text-nowrap" data-sort="' . h(str_replace('-', '', $month)) . '"><a class="link-body-emphasis" href="erp_vat_report.php?range=month&amp;month=' . h($month) . '">' . h(get_month_name_from_number(substr($month, 5, 2)) . ' ' . substr($month, 0, 4)) . '</a></td>
                <td class="text-end" data-sort="' . (int) $figures['calculated'] . '">' . $money($figures['calculated']) . '</td>
                ' . ($net_withholding ? '<td class="text-end" data-sort="' . (int) -$figures['withheld_sales'] . '">' . $money(-$figures['withheld_sales']) . '</td>' : '') . '
                <td class="text-end" data-sort="' . (int) $figures['deductible_purchases'] . '">' . $money($figures['deductible_purchases']) . '</td>
                <td class="text-end" data-sort="' . (int) $figures['deductible_expenses'] . '">' . $money($figures['deductible_expenses']) . '</td>
                <td class="text-end fw-semibold" data-sort="' . (int) $figures['difference'] . '">' . $money($figures['difference']) . '</td>
            </tr>';
    }

    $rows .= '
            <tr class="fw-bold border-top" data-pg-sort-fixed>
                <td>' . lang('Total') . '</td>
                <td class="text-end">' . $money($totals['calculated']) . '</td>
                ' . ($net_withholding ? '<td class="text-end">' . $money(-$totals['withheld_sales']) . '</td>' : '') . '
                <td class="text-end">' . $money($totals['deductible_purchases']) . '</td>
                <td class="text-end">' . $money($totals['deductible_expenses']) . '</td>
                <td class="text-end">' . $money($totals['difference']) . '</td>
            </tr>';

    $output_months = '
        <div class="card my-3">
            <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">' . lang('By month') . '</div>
            <div class="card-body p-0 table-responsive">
                <table class="table table-sm table-hover align-middle mb-0" data-pg-sort id="erp_vat_months">
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
        </div>';
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
        $marks .= ' <span class="badge text-bg-secondary">' . lang('Cancelled') . '</span>';
    } elseif (($item['type'] === 'expense') && !$item['deductible']) {
        $marks .= ' <span class="badge text-bg-light border" title="' . h(lang('Tax on expenses that is not deductible (a cost)')) . '">' . lang('Not deductible') . '</span>';
    }
    if ($item['currency'] !== $base) {
        $marks .= ' <span class="badge text-bg-light border font-monospace">' . h($item['currency']) . '</span>';
    }

    $output_documents .= '
        <tr data-kind="' . h($item['kind']) . '"' . ($item['cancelled'] ? ' class="text-body-secondary"' : '') . '>
            <td class="text-nowrap" data-sort="' . h(str_replace('-', '', $item['date'])) . '">' . h(prepare_form_data_for_output($item['date'], 'date', false)) . '</td>
            <td class="text-nowrap"><a class="link-body-emphasis" href="' . h($url) . '">' . h($number) . '</a>' . $marks . '</td>
            <td>' . h($labels[$item['kind']]) . '</td>
            <td>' . h($item['account']) . (($item['tax_number'] !== '') ? '<div class="small text-body-secondary font-monospace">' . h($item['tax_number']) . '</div>' : '') . '</td>
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
    $output_documents = '<tr data-pg-sort-fixed><td colspan="' . $columns . '" class="text-center text-body-secondary py-4">' . lang('No invoices, returns or expenses in this period.') . '</td></tr>';
}

$kind_options = '<option value="">' . lang('All') . '</option>';
foreach ($labels as $key => $label) {
    if (isset($kinds_listed[$key])) {
        $kind_options .= '<option value="' . h($key) . '">' . h($label) . '</option>';
    }
}

$more_documents = (count($report['documents']) > $shown_limit)
    ? '<p class="small text-body-secondary m-3">' . h(lang(array('string' => 'The first {var:1} of {var:2} are shown; the workbook has them all.', 'vars' => array($shown_limit, count($report['documents']))))) . '</p>'
    : '';

// What the reader should know about the figures.
$output_notes = '';
if ((int) $report['orders']['count'] > 0) {
    $output_notes .= '<div class="alert alert-warning my-3"><i class="bi bi-cart-check me-1" aria-hidden="true"></i>' . h(lang(array(
        'string' => '{var:1} completed order(s) of this period have no invoice (about {var:2} of tax), so their tax is not in these figures.',
        'vars' => array((int) $report['orders']['count'], erp_money_out((int) $report['orders']['tax'])),
    ))) . '</div>';
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

$export_url = 'erp_vat_report.php?' . h(http_build_query(array('range' => 'dates', 'from' => $period['from'], 'to' => $period['to'], 'export' => 'xlsx')));
$difference_hint = ($totals['difference'] > 0)
    ? lang('Calculated exceeds deductible')
    : (($totals['difference'] < 0) ? lang('Deductible exceeds calculated') : lang('Calculated and deductible are equal'));

echo
pg_page_shell([
    'title' => $title,
    'extra classes' => 'erp erp_vat_report',
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

            <form method="get" action="erp_vat_report.php" class="card my-4" id="erp_vat_period">
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
                        <label class="form-label small text-body-secondary mb-1" for="from">' . lang('Start date') . '</label>
                        <input type="date" class="form-control form-control-sm" id="from" name="from" value="' . h($period['from']) . '" />
                    </div>
                    <div class="col-6 col-md-2" data-range="dates"' . (($range === 'dates') ? '' : ' hidden') . '>
                        <label class="form-label small text-body-secondary mb-1" for="to">' . lang('End date') . '</label>
                        <input type="date" class="form-control form-control-sm" id="to" name="to" value="' . h($period['to']) . '" />
                    </div>
                    <div class="col-12 col-md-auto">
                        <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-arrow-repeat me-1" aria-hidden="true"></i>' . lang('Show') . '</button>
                        <a class="btn btn-sm btn-outline-secondary" href="' . $export_url . '"><i class="bi bi-file-earmark-spreadsheet me-1" aria-hidden="true"></i>' . lang('Excel') . '</a>
                    </div>
                    <div class="col-12 small text-body-secondary">' . h(prepare_form_data_for_output($period['from'], 'date', false) . ' – ' . prepare_form_data_for_output($period['to'], 'date', false)) . '</div>
                </div>
            </form>

            <div class="row g-3">
                <div class="col-6 col-lg-' . ($has_withholding ? '3' : '4') . '"><div class="card h-100"><div class="card-body">
                    <div class="small text-uppercase text-body-secondary">' . h(erp_tax_label('calculated')) . '</div>
                    <div class="h4 mb-0">' . $money($totals['calculated']) . '</div>
                    <div class="small text-body-secondary">' . lang('Sales less sales returns') . '</div>
                </div></div></div>
                <div class="col-6 col-lg-' . ($has_withholding ? '3' : '4') . '"><div class="card h-100"><div class="card-body">
                    <div class="small text-uppercase text-body-secondary">' . h(erp_tax_label('deductible')) . '</div>
                    <div class="h4 mb-0">' . $money($totals['deductible']) . '</div>
                    <div class="small text-body-secondary">' . h(lang(array('string' => 'Purchases {var:1} · expenses {var:2}', 'vars' => array(erp_money_out($totals['deductible_purchases']), erp_money_out($totals['deductible_expenses']))))) . '</div>
                </div></div></div>
                <div class="col-6 col-lg-' . ($has_withholding ? '3' : '4') . '"><div class="card h-100 border-primary"><div class="card-body">
                    <div class="small text-uppercase text-body-secondary">' . lang('Difference') . '</div>
                    <div class="h4 mb-0">' . $money($totals['difference']) . '</div>
                    <div class="small text-body-secondary">' . h($difference_hint) . '</div>
                </div></div></div>
                ' . ($has_withholding ? '<div class="col-6 col-lg-3"><div class="card h-100"><div class="card-body">
                    <div class="small text-uppercase text-body-secondary">' . lang('Withholding') . '</div>
                    <div class="h4 mb-0">' . $money($totals['withheld_sales']) . '</div>
                    <div class="small text-body-secondary">' . h(lang(array('string' => 'On sales; on purchases {var:1}', 'vars' => erp_money_out($totals['withheld_purchases'])))) . '</div>
                </div></div></div>' : '') . '
            </div>

            ' . $output_notes . '

            <div class="row g-3 my-1">
                <div class="col-12 col-xl-5">
                    <div class="card h-100">
                        <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">' . lang('Summary') . '</div>
                        <div class="card-body">' . $output_statement . $output_aside . '</div>
                    </div>
                </div>
                <div class="col-12 col-xl-7">
                    <div class="card h-100">
                        <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">' . lang('By rate') . '</div>
                        <div class="card-body p-0 table-responsive">
                            <table class="table table-sm table-hover align-middle mb-0" id="erp_vat_rates">
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
                    </div>
                </div>
            </div>

            ' . $output_withholding . '
            ' . $output_months . '

            <div class="card my-3">
                <div class="card-header bg-reset border-0 d-flex flex-wrap align-items-center gap-2">
                    <span class="text-uppercase h5 text-primary fw-bold mb-0 me-auto">' . lang('Documents of the period') . '</span>
                    <label class="small text-body-secondary" for="erp_vat_kind">' . lang('Document type') . '</label>
                    <select class="form-select form-select-sm w-auto" id="erp_vat_kind">' . $kind_options . '</select>
                </div>
                <div class="card-body p-0 table-responsive" style="max-height:40rem">
                    <table class="table table-sm table-hover align-middle mb-0" data-pg-sort id="erp_vat_documents">
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
            </div>

            <p class="small text-body-secondary my-3">' . lang('A summary of the invoices, returns and expenses the ERP keeps, not a tax return: check it with your accountant before you file. Documents count by the date they were issued, expenses by their date; cancelled documents and drafts count for nothing, and the tax on an expense that is not taken back is a cost, left out of the deductible tax.') . '</p>
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
' .
output_footer();

$liveform->remove_form();
