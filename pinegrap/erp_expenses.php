<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * ERP - expenses: the receipts of a period, what they add up to by category,
 * and what is still to pay. The expenses themselves are kept in
 * includes/erp/expenses.php.
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
$liveform = new liveform('erp_expenses');

$readonly = defined('USER_ERP_READONLY') && USER_ERP_READONLY;
$ready = erp_expenses_ready();

// ------------------------------------------------------------------ filters
// A month by default; a year, or two dates, when asked for.
$period_choice = (string) ($_GET['period'] ?? date('Y-m'));
$filters = array(
    'category_id' => (int) ($_GET['category_id'] ?? 0),
    'status' => (string) ($_GET['status'] ?? 'standing'),
    'query' => trim(mb_substr((string) ($_GET['q'] ?? ''), 0, 100)),
);

if (preg_match('/^year-(\d{4})$/', $period_choice, $parts)) {
    $period = erp_profit_period(array('range' => 'year', 'year' => $parts[1]));
} elseif ($period_choice === 'dates') {
    $period = erp_profit_period(array('range' => 'dates', 'from' => (string) ($_GET['from'] ?? ''), 'to' => (string) ($_GET['to'] ?? '')));

    if ($period['error'] !== '') {
        $liveform->add_warning(h($period['error']));
    }
} else {
    $period = erp_profit_period(array('range' => 'month', 'month' => $period_choice));
    $period_choice = substr($period['from'], 0, 7);
}

$filters['from'] = $period['from'];
$filters['to'] = $period['to'];

$categories = $ready ? erp_expense_categories() : array();
$statuses = erp_expense_statuses();
$base = erp_base_currency();

// ------------------------------------------------------------------ export
if ($ready && ((string) ($_GET['export'] ?? '') === 'xlsx')) {
    $rows = array();

    foreach (erp_expenses_list($filters + array('limit' => 5000)) as $expense) {
        $rows[] = array(
            (string) $expense['expense_date'],
            (string) $expense['category_name'],
            (string) $expense['category_code'],
            (string) $expense['supplier'],
            (string) $expense['supplier_tax_number'],
            (string) $expense['document_no'],
            (string) $expense['description'],
            (int) $expense['net_amount'],
            (float) $expense['tax_rate'],
            (int) $expense['tax_amount'],
            (int) $expense['total_amount'],
            strtoupper((string) $expense['currency']),
            (int) $expense['total_base'],
            ((int) $expense['tax_deductible'] === 1) ? lang('Yes') : lang('No'),
            $statuses[(string) $expense['status']] ?? (string) $expense['status'],
            (string) $expense['till_name'],
            ((string) $expense['paid_date'] > '0000-00-00') ? (string) $expense['paid_date'] : '',
        );
    }

    $path = tempnam(sys_get_temp_dir(), 'pgx');
    $written = ($path !== false) && erp_accountant_write_workbook(array(array(
        'title' => lang('Expenses'),
        'columns' => array(
            array('label' => lang('Date'), 'type' => 'date', 'width' => 11),
            array('label' => lang('Category'), 'type' => 'text', 'width' => 26),
            array('label' => lang('Code'), 'type' => 'text', 'width' => 10),
            array('label' => lang('Supplier'), 'type' => 'text', 'width' => 28),
            array('label' => erp_tax_id_label(), 'type' => 'text', 'width' => 14),
            array('label' => lang('Receipt number'), 'type' => 'text', 'width' => 14),
            array('label' => lang('Description'), 'type' => 'text', 'width' => 30),
            array('label' => lang('Net'), 'type' => 'money', 'width' => 13),
            array('label' => erp_tax_label('percent'), 'type' => 'number', 'width' => 8),
            array('label' => erp_tax_label('tax'), 'type' => 'money', 'width' => 12),
            array('label' => lang('Total'), 'type' => 'money', 'width' => 13),
            array('label' => lang('Currency'), 'type' => 'text', 'width' => 8),
            array('label' => lang(array('string' => 'Total ({var:1})', 'vars' => $base)), 'type' => 'money', 'width' => 14),
            array('label' => lang('Tax taken back'), 'type' => 'text', 'width' => 10),
            array('label' => lang('Status'), 'type' => 'text', 'width' => 10),
            array('label' => lang('Paid from'), 'type' => 'text', 'width' => 18),
            array('label' => lang('Paid on'), 'type' => 'date', 'width' => 11),
        ),
        'rows' => $rows,
    )), $path);

    if (!$written) {
        if ($path !== false) {
            @unlink($path);
        }
        $liveform->mark_error('_error', lang('The workbook could not be written.'));
        go(PATH . SOFTWARE_DIRECTORY . '/erp_expenses.php');
    }

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . erp_accountant_file_part(lang('Expenses')) . '_' . $period['from'] . '_' . $period['to'] . '.xlsx"');
    header('Content-Length: ' . filesize($path));
    header('Cache-Control: private, no-store');
    readfile($path);
    @unlink($path);
    exit();
}

// ------------------------------------------------------------------ display
if (!$ready) {
    $liveform->mark_error('', lang('Expenses come with the software update; run the update to use them.'));
}

// Repeating expenses that have come due are written on a visit too, so a
// store without a crontab still gets its rent; one indexed read otherwise.
if ($ready && !$readonly && erp_expense_recurring_ready()) {
    $run = erp_expense_recurring_run();

    if ($run['written'] > 0) {
        $liveform->add_notice(h(lang(array('string' => '{var:1} repeating expense(s) were written.', 'vars' => (int) $run['written']))));
    }
}

$expenses = $ready ? erp_expenses_list($filters) : array();
$totals = $ready ? erp_expense_totals($period['from'], $period['to']) : array('categories' => array(), 'total' => array('count' => 0, 'net' => 0, 'tax' => 0, 'deductible_tax' => 0, 'total' => 0, 'cost' => 0, 'unpaid' => 0));

// Everything still to pay, whatever its date: that is what the operator has
// to find the money for.
$open = $ready ? db_item("SELECT COUNT(*) AS count, COALESCE(SUM(total_base), 0) AS total,
        COALESCE(SUM(IF(due_date > '0000-00-00' AND due_date < CURDATE(), total_base, 0)), 0) AS overdue
    FROM erp_expenses WHERE status = 'unpaid'") : null;

$money = function ($kurus) {
    return h(erp_money_out((int) $kurus));
};

$today = date('Y-m-d');
$output_rows = '';

foreach ($expenses as $expense) {
    $id = (int) $expense['id'];
    $status = (string) $expense['status'];
    $foreign = (strtoupper((string) $expense['currency']) !== $base);
    $overdue = ($status === 'unpaid') && ((string) $expense['due_date'] > '0000-00-00') && ((string) $expense['due_date'] < $today);

    if ($status === 'paid') {
        $output_status = '<span class="badge text-bg-success">' . h($statuses['paid']) . '</span>'
            . '<div class="small text-body-secondary">' . h((string) $expense['till_name']) . '</div>';
    } elseif ($status === 'cancelled') {
        $output_status = '<span class="badge text-bg-secondary">' . h($statuses['cancelled']) . '</span>';
    } else {
        $output_status = '<span class="badge ' . ($overdue ? 'text-bg-danger' : 'text-bg-warning') . '">' . h($statuses['unpaid']) . '</span>'
            . (((string) $expense['due_date'] > '0000-00-00')
                ? '<div class="small ' . ($overdue ? 'text-danger' : 'text-body-secondary') . '">' . h(lang(array('string' => 'due {var:1}', 'vars' => prepare_form_data_for_output((string) $expense['due_date'], 'date', false)))) . '</div>'
                : '');
    }

    $struck = ($status === 'cancelled') ? ' text-decoration-line-through text-body-secondary' : '';

    $output_rows .= '
        <tr' . (($status === 'cancelled') ? ' class="opacity-75"' : '') . '>
            <td class="align-middle text-nowrap" data-sort="' . h(str_replace('-', '', (string) $expense['expense_date']) . sprintf('%07d', $id % 10000000)) . '">
                <a class="link-body-emphasis" href="edit_erp_expense.php?id=' . $id . '">' . h(prepare_form_data_for_output((string) $expense['expense_date'], 'date', false)) . '</a>
            </td>
            <td class="align-middle">' . h((string) $expense['category_name']) . '</td>
            <td class="align-middle' . $struck . '">
                <a class="link-body-emphasis text-decoration-none" href="edit_erp_expense.php?id=' . $id . '">' . h(((string) $expense['supplier'] !== '') ? (string) $expense['supplier'] : ('#' . $id)) . '</a>'
                . (((string) $expense['document_no'] !== '') ? '<div class="small text-body-secondary font-monospace">' . h((string) $expense['document_no']) . '</div>' : '') . '
            </td>
            <td class="align-middle small' . $struck . '" style="max-width:18rem">' . h((string) $expense['description']) . '</td>
            <td class="align-middle text-end text-nowrap" data-sort="' . (int) $expense['tax_base'] . '">' . $money($expense['tax_base'])
                . (((int) $expense['tax_deductible'] !== 1) && ((int) $expense['tax_base'] !== 0) ? '<div class="small text-body-secondary">' . lang('not taken back') . '</div>' : '') . '</td>
            <td class="align-middle text-end text-nowrap fw-semibold' . $struck . '" data-sort="' . (int) $expense['total_base'] . '">' . $money($expense['total_base'])
                . ($foreign ? '<div class="small text-body-secondary fw-normal">' . h(erp_money_out_currency((int) $expense['total_amount'], (string) $expense['currency'])) . '</div>' : '') . '</td>
            <td class="align-middle" data-sort="' . h($status . ':' . (string) $expense['due_date']) . '">' . $output_status . '</td>
        </tr>';
}

if ($output_rows === '') {
    $output_rows = '<tr data-pg-sort-fixed><td colspan="7" class="text-center text-body-secondary py-4">' . lang('No expense in this period.') . '</td></tr>';
}

// By category: a bar per category, as a share of the period.
$output_categories = '';
$grand = max(1, (int) $totals['total']['total']);

foreach ($totals['categories'] as $category) {
    $share = round($category['total'] * 100 / $grand);
    $output_categories .= '
        <li class="list-group-item px-3 py-2">
            <div class="d-flex justify-content-between gap-2">
                <a class="link-body-emphasis text-decoration-none text-truncate" href="erp_expenses.php?' . h(http_build_query(array('period' => $period_choice, 'from' => $period['from'], 'to' => $period['to'], 'category_id' => (int) $category['category_id']))) . '">' . h($category['name']) . '</a>
                <span class="text-nowrap fw-semibold">' . $money($category['total']) . '</span>
            </div>
            <div class="progress mt-1" style="height:.35rem" role="progressbar" aria-label="' . h($category['name']) . '" aria-valuenow="' . (int) $share . '" aria-valuemin="0" aria-valuemax="100">
                <div class="progress-bar" style="width:' . (int) $share . '%"></div>
            </div>
        </li>';
}

if ($output_categories === '') {
    $output_categories = '<li class="list-group-item text-body-secondary small">' . lang('No expense in this period.') . '</li>';
}

// The repeating expenses, the soonest first.
$output_recurring = '';

if ($ready && erp_expense_recurring_ready()) {
    $periods = erp_expense_recurring_periods();

    foreach (erp_expense_recurrences(true) as $recurrence) {
        $label = ((string) $recurrence['supplier'] !== '') ? (string) $recurrence['supplier'] : (string) $recurrence['description'];

        $output_recurring .= '
            <li class="list-group-item px-3 py-2">
                <div class="d-flex justify-content-between gap-2">
                    <a class="link-body-emphasis text-decoration-none text-truncate" href="edit_erp_expense.php?id=' . (int) $recurrence['source_expense_id'] . '">' . h($label) . '</a>
                    <span class="text-nowrap fw-semibold">' . h(erp_money_out_currency((int) $recurrence['amount'], (string) $recurrence['currency'])) . '</span>
                </div>
                <div class="small text-body-secondary d-flex justify-content-between gap-2">
                    <span class="text-truncate">' . h(($periods[(int) $recurrence['every_months']] ?? '') . ' · ' . (string) $recurrence['category_name']) . '</span>
                    <span class="text-nowrap">' . h(prepare_form_data_for_output((string) $recurrence['next_date'], 'date', false)) . '</span>
                </div>'
                . (((string) $recurrence['last_error'] !== '') ? '<div class="small text-warning-emphasis text-truncate" title="' . h((string) $recurrence['last_error']) . '"><i class="bi bi-exclamation-triangle me-1" aria-hidden="true"></i>' . h((string) $recurrence['last_error']) . '</div>' : '') . '
            </li>';
    }
}

// The period picker: the last eighteen months, this year and last year, or
// two dates.
$output_periods = '';
for ($i = 0; $i < 18; $i++) {
    $month = date('Y-m', strtotime(date('Y-m-01') . ' -' . $i . ' month'));
    $output_periods .= '<option value="' . h($month) . '"' . (($period_choice === $month) ? ' selected' : '') . '>'
        . h(get_month_name_from_number(substr($month, 5, 2)) . ' ' . substr($month, 0, 4)) . '</option>';
}
foreach (array((int) date('Y'), (int) date('Y') - 1) as $year) {
    $output_periods .= '<option value="year-' . $year . '"' . (($period_choice === 'year-' . $year) ? ' selected' : '') . '>' . h(lang(array('string' => 'Year {var:1}', 'vars' => $year))) . '</option>';
}
$output_periods .= '<option value="dates"' . (($period_choice === 'dates') ? ' selected' : '') . '>' . lang('From - to') . '</option>';

$output_category_options = '<option value="0">' . lang('All categories') . '</option>';
foreach ($categories as $category) {
    $output_category_options .= '<option value="' . (int) $category['id'] . '"' . (($filters['category_id'] === (int) $category['id']) ? ' selected' : '') . '>'
        . h($category['name'] . (((int) $category['is_active'] !== 1) ? ' (' . lang('off') . ')' : '')) . '</option>';
}

$output_status_options = '<option value="standing"' . (($filters['status'] === 'standing') ? ' selected' : '') . '>' . lang('All but cancelled') . '</option>';
foreach ($statuses as $key => $label) {
    $output_status_options .= '<option value="' . h($key) . '"' . (($filters['status'] === $key) ? ' selected' : '') . '>' . h($label) . '</option>';
}
$output_status_options .= '<option value="all"' . (($filters['status'] === 'all') ? ' selected' : '') . '>' . lang('All') . '</option>';

$export_url = 'erp_expenses.php?' . h(http_build_query(array(
    'period' => $period_choice, 'from' => $period['from'], 'to' => $period['to'],
    'category_id' => $filters['category_id'], 'status' => $filters['status'], 'q' => $filters['query'], 'export' => 'xlsx',
)));

echo
pg_page_shell([
        'title' => lang('Expenses'),
        'extra classes' => 'erp erp_expenses',
        'icon' => 'erp',
        'heading' => lang('Expenses'),
        'heading_description' => lang('Receipts for rent, fuel, subscriptions and everything else the business spends that is not a purchase invoice.'),
        'cancel' => false,
    ]) . '
<main id="content" class="container-fluid">
    <div class="row">
        <div class="col-12">
            ' . $liveform->output_errors() . '
            ' . $liveform->get_warnings() . '
            ' . $liveform->output_notices() . '

            <nav id="button_bar" class="pg-toolbar navigation" aria-label="' . lang('Button Bar') . '">
                ' . ((!$readonly && $ready) ? '<a class="btn btn-sm btn-primary rounded-pill px-3" href="add_erp_expense.php" data-loading-content="' . lang(array('string' => 'Loading')) . '"><i class="bi bi-plus-circle me-1" aria-hidden="true"></i>' . lang('New expense') . '</a>' : '') . '
                ' . ((!$readonly && $ready) ? '<a class="btn btn-sm btn-outline-secondary" href="erp_expense_categories.php" data-loading-content="' . lang(array('string' => 'Loading')) . '"><i class="bi bi-tags me-1" aria-hidden="true"></i>' . lang('Categories') . '</a>' : '') . '
                <a class="btn btn-sm btn-outline-secondary" href="erp_profit.php?' . h(http_build_query(array('range' => 'dates', 'from' => $period['from'], 'to' => $period['to']))) . '" data-loading-content="' . lang(array('string' => 'Loading')) . '"><i class="bi bi-graph-up me-1" aria-hidden="true"></i>' . lang('Profit and loss') . '</a>
                ' . ($ready ? '<a class="btn btn-sm btn-outline-secondary" href="' . $export_url . '"><i class="bi bi-file-earmark-spreadsheet me-1" aria-hidden="true"></i>' . lang('Excel') . '</a>' : '') . '
            </nav>

            <form method="get" action="erp_expenses.php" class="card my-4">
                <div class="card-body row g-2 align-items-end">
                    <div class="col-12 col-md-3">
                        <label class="form-label small text-body-secondary mb-1" for="period">' . lang('Period') . '</label>
                        <select class="form-select form-select-sm" id="period" name="period">' . $output_periods . '</select>
                    </div>
                    <div class="col-6 col-md-2 erp-expense-dates"' . (($period_choice === 'dates') ? '' : ' hidden') . '>
                        <label class="form-label small text-body-secondary mb-1" for="from">' . lang('From') . '</label>
                        <input type="date" class="form-control form-control-sm" id="from" name="from" value="' . h($period['from']) . '" />
                    </div>
                    <div class="col-6 col-md-2 erp-expense-dates"' . (($period_choice === 'dates') ? '' : ' hidden') . '>
                        <label class="form-label small text-body-secondary mb-1" for="to">' . lang('To') . '</label>
                        <input type="date" class="form-control form-control-sm" id="to" name="to" value="' . h($period['to']) . '" />
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label small text-body-secondary mb-1" for="category_id">' . lang('Category') . '</label>
                        <select class="form-select form-select-sm" id="category_id" name="category_id">' . $output_category_options . '</select>
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label small text-body-secondary mb-1" for="status">' . lang('Status') . '</label>
                        <select class="form-select form-select-sm" id="status" name="status">' . $output_status_options . '</select>
                    </div>
                    <div class="col-12 col-md">
                        <label class="form-label small text-body-secondary mb-1" for="q">' . lang('Search') . '</label>
                        <div class="input-group input-group-sm">
                            <input type="search" class="form-control" id="q" name="q" value="' . h($filters['query']) . '" placeholder="' . h(lang('Supplier, receipt number, words')) . '" />
                            <button type="submit" class="btn btn-outline-primary"><i class="bi bi-funnel" aria-hidden="true"></i><span class="visually-hidden">' . lang('Filter') . '</span></button>
                        </div>
                    </div>
                </div>
            </form>

            <div class="row g-3">
                <div class="col-6 col-lg-3">
                    <div class="card h-100"><div class="card-body">
                        <div class="small text-uppercase text-body-secondary">' . lang('Spent in the period') . '</div>
                        <div class="h4 mb-0">' . $money($totals['total']['total']) . '</div>
                        <div class="small text-body-secondary">' . h(lang(array('string' => '{var:1} expense(s)', 'vars' => (int) $totals['total']['count']))) . '</div>
                    </div></div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="card h-100"><div class="card-body">
                        <div class="small text-uppercase text-body-secondary">' . h(lang(array('string' => '{var:1} to take back', 'vars' => erp_tax_label('tax')))) . '</div>
                        <div class="h4 mb-0">' . $money($totals['total']['deductible_tax']) . '</div>
                        <div class="small text-body-secondary">' . lang('Goes to the accountant with the purchases.') . '</div>
                    </div></div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="card h-100"><div class="card-body">
                        <div class="small text-uppercase text-body-secondary">' . lang('Cost to the business') . '</div>
                        <div class="h4 mb-0">' . $money($totals['total']['cost']) . '</div>
                        <div class="small text-body-secondary">' . lang('Net, plus the tax that is not taken back.') . '</div>
                    </div></div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="card h-100' . ((is_array($open) && ((int) $open['overdue'] > 0)) ? ' border-danger' : '') . '"><div class="card-body">
                        <div class="small text-uppercase text-body-secondary">' . lang('Still to pay') . '</div>
                        <div class="h4 mb-0' . ((is_array($open) && ((int) $open['overdue'] > 0)) ? ' text-danger' : '') . '">' . $money(is_array($open) ? $open['total'] : 0) . '</div>
                        <div class="small text-body-secondary">'
                            . h(lang(array('string' => '{var:1} expense(s), any date', 'vars' => is_array($open) ? (int) $open['count'] : 0)))
                            . ((is_array($open) && ((int) $open['overdue'] > 0)) ? ' · <span class="text-danger">' . h(lang(array('string' => '{var:1} past due', 'vars' => erp_money_out((int) $open['overdue'])))) . '</span>' : '') . '
                        </div>
                    </div></div>
                </div>
            </div>

            <div class="row g-3 my-1">
                <div class="col-12 col-xl-9">
                    <div class="card h-100">
                        <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">'
                            . h(lang(array('string' => 'Expenses {var:1}', 'vars' => erp_accountant_period_label($period['from'], $period['to'])))) . '</div>
                        <div class="card-body p-0 table-responsive">
                            <table class="table table-hover align-middle mb-0" data-pg-sort id="erp_expenses_list">
                                <thead>
                                    <tr>
                                        <th>' . lang('Date') . '</th>
                                        <th>' . lang('Category') . '</th>
                                        <th>' . lang('Supplier') . '</th>
                                        <th>' . lang('Description') . '</th>
                                        <th class="text-end">' . h(erp_tax_label('tax')) . '</th>
                                        <th class="text-end">' . lang('Total') . '</th>
                                        <th>' . lang('Status') . '</th>
                                    </tr>
                                </thead>
                                <tbody>' . $output_rows . '</tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-xl-3">
                    <div class="card">
                        <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">' . lang('By category') . '</div>
                        <ul class="list-group list-group-flush">' . $output_categories . '</ul>
                    </div>
                    ' . (($output_recurring !== '') ? '<div class="card mt-3">
                        <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold"><i class="bi bi-arrow-repeat me-1" aria-hidden="true"></i>' . lang('Repeating expenses') . '</div>
                        <ul class="list-group list-group-flush">' . $output_recurring . '</ul>
                    </div>' : '') . '
                </div>
            </div>
        </div>
    </div>
</main>
<script>
(function () {
    var period = document.getElementById("period");
    if (!period) { return; }
    period.addEventListener("change", function () {
        var dates = (period.value === "dates");
        document.querySelectorAll(".erp-expense-dates").forEach(function (el) { el.hidden = !dates; });
        if (!dates) { period.form.submit(); }
    });
})();
</script>
' .
output_footer();

$liveform->remove_form();
