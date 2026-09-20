<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * ERP - the cash flow report screen.
 *
 * A period, a bucket size and optionally one till; the money in and out per
 * bucket with the running position, the same period till by till, and the
 * split by payment method. The address carries the whole state so a view can
 * be bookmarked. CSV of the period table.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');
$user = validate_user();
if (!validate_erp_access($user, 'cash')) {
    exit();
}

require_once(PG_FUNCTIONS_DIR . '/includes/erp/bootstrap.php');
include_once('liveform.class.php');
$liveform = new liveform('erp_cashflow');

$options = erp_cashflow_options(array(
    'from' => $_GET['from'] ?? '',
    'to' => $_GET['to'] ?? '',
    'group' => $_GET['group'] ?? '',
    'till' => $_GET['till'] ?? 0,
));

$report = erp_cashflow_report($options);

$query_base = 'from=' . $options['from'] . '&to=' . $options['to'] . '&till=' . $options['till'];
$query = $query_base . '&group=' . $options['group'];

// The file: the period table, written before any page output.
if (!empty($_GET['csv'])) {
    $csv = erp_cashflow_csv($report, $options);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="cashflow_' . $options['from'] . '_' . $options['to'] . '.csv"');
    header('Cache-Control: no-store');

    erp_export_write_csv($csv['columns'], $csv['rows'], 'php://output');
    exit();
}

$tills = erp_cashflow_tills();
$by_till = erp_cashflow_by_till($options);
$by_method = erp_cashflow_by_method($options);
$groups = erp_cashflow_groups();

$kind_labels = array(
    'cash' => lang('Cash'),
    'bank' => lang('Bank'),
    'pos' => lang('Card terminal'),
    'credit_card' => lang('Credit card'),
);

$money = function ($kurus, $muted_zero = true) {
    $kurus = (int) $kurus;
    $class = ($kurus === 0 && $muted_zero) ? ' text-body-secondary' : '';

    return '<span class="' . trim($class) . '">' . h(erp_money_out($kurus)) . '</span>';
};

$signed = function ($kurus) {
    $kurus = (int) $kurus;
    $class = ($kurus > 0) ? 'text-success' : (($kurus < 0) ? 'text-danger' : 'text-body-secondary');

    return '<span class="' . $class . '">' . h((($kurus > 0) ? '+' : '') . erp_money_out($kurus)) . '</span>';
};

// ----------------------------------------------------------------- toolbar
$group_toggle = function ($value, $label) use ($options, $query_base) {
    $active = ($options['group'] === $value);

    return '<a class="btn btn-sm btn-ghost' . ($active ? ' active' : '') . '" href="erp_cashflow.php?' . h($query_base . '&group=' . $value) . '"' . ($active ? ' aria-current="page"' : '') . '>' . h($label) . '</a>';
};

$output_group_toggles = '';
foreach ($groups as $value => $label) {
    $output_group_toggles .= $group_toggle($value, $label);
}

// Quick ranges keep the till and let the group pick itself again.
$this_month = array(date('Y-m-01'), date('Y-m-d'));
$last_month = array(date('Y-m-01', strtotime('first day of last month')), date('Y-m-t', strtotime('first day of last month')));
$this_year = array(date('Y-01-01'), date('Y-m-d'));
$last_90 = array(date('Y-m-d', strtotime('-89 days')), date('Y-m-d'));

$quick = function ($range, $label) use ($options) {
    $active = ($options['from'] === $range[0]) && ($options['to'] === $range[1]);

    return '<a class="btn btn-sm btn-ghost' . ($active ? ' active' : '') . '" href="erp_cashflow.php?from=' . $range[0] . '&amp;to=' . $range[1] . '&amp;till=' . (int) $options['till'] . '">' . h($label) . '</a>';
};

$output_till_options = '<option value="0"' . (((int) $options['till'] === 0) ? ' selected' : '') . '>' . lang('All tills') . '</option>';
foreach ($tills as $till) {
    $output_till_options .= '<option value="' . (int) $till['id'] . '"' . (((int) $options['till'] === (int) $till['id']) ? ' selected' : '') . '>' . h($till['name']) . (((int) $till['is_active'] === 1) ? '' : ' (' . lang('passive') . ')') . '</option>';
}

$period_text = prepare_form_data_for_output($options['from'], 'date', false) . ' – ' . prepare_form_data_for_output($options['to'], 'date', false);

// ------------------------------------------------------------ period rows
$output_rows = '';

foreach ($report['rows'] as $row) {
    $quiet = ($row['count'] === 0);

    $output_rows .= '
                            <tr' . ($quiet ? ' class="text-body-secondary"' : '') . '>
                                <td class="align-middle text-nowrap">' . h($row['label']) . '</td>
                                <td class="align-middle text-end">' . $money($row['in']) . '</td>
                                <td class="align-middle text-end">' . $money($row['out']) . '</td>
                                <td class="align-middle text-end">' . $signed($row['net']) . '</td>
                                <td class="align-middle text-end small">' . ($quiet ? '—' : number_format($row['count'])) . '</td>
                                <td class="align-middle text-end fw-bold' . (($row['balance'] < 0) ? ' text-danger' : '') . '">' . h(erp_money_out($row['balance'])) . '</td>
                            </tr>';
}

// -------------------------------------------------------------- till rows
$output_till_rows = '';

foreach ($by_till as $row) {
    $name = h($row['name']) . (($row['is_active']) ? '' : ' <span class="badge text-bg-secondary">' . lang('passive') . '</span>');
    $note = (!$row['in_base']) ? '<div class="small text-body-secondary">' . h(lang(array('string' => 'Kept in {var:1}; movements at their booked base value, the opening figure left out.', 'vars' => $row['currency']))) . '</div>' : '';

    $output_till_rows .= '
                            <tr>
                                <td class="align-middle"><a href="edit_erp_till.php?id=' . (int) $row['id'] . '" class="link-body-emphasis">' . $name . '</a>' . $note . '</td>
                                <td class="align-middle">' . h($kind_labels[$row['kind']] ?? $row['kind']) . '</td>
                                <td class="align-middle text-end">' . h(erp_money_out($row['opening'])) . '</td>
                                <td class="align-middle text-end">' . $money($row['in']) . '</td>
                                <td class="align-middle text-end">' . $money($row['out']) . '</td>
                                <td class="align-middle text-end">' . $signed($row['net']) . '</td>
                                <td class="align-middle text-end small">' . (($row['count'] === 0) ? '—' : number_format($row['count'])) . '</td>
                                <td class="align-middle text-end fw-bold' . (($row['closing'] < 0) ? ' text-danger' : '') . '">' . h(erp_money_out($row['closing'])) . '</td>
                            </tr>';
}

if ($output_till_rows === '') {
    $output_till_rows = '
                            <tr><td colspan="8" class="text-center text-body-secondary py-4">' . lang('There are no tills yet.') . '</td></tr>';
}

// ------------------------------------------------------------ method rows
$output_method_rows = '';

foreach ($by_method as $row) {
    $output_method_rows .= '
                            <tr>
                                <td class="align-middle">' . h($row['label']) . '</td>
                                <td class="align-middle text-end">' . $money($row['in']) . '</td>
                                <td class="align-middle text-end">' . $money($row['out']) . '</td>
                                <td class="align-middle text-end">' . $signed($row['in'] - $row['out']) . '</td>
                                <td class="align-middle text-end small">' . number_format($row['count']) . '</td>
                            </tr>';
}

if ($output_method_rows === '') {
    $output_method_rows = '
                            <tr><td colspan="5" class="text-center text-body-secondary py-4">' . lang('No movements in this period.') . '</td></tr>';
}

// -------------------------------------------------------------- footnotes
$notes = array();

if ((int) $options['till'] === 0) {
    $notes[] = lang('Transfers between your own tills are left out of the figures above; they appear in the table by till, where they are real movements.');
}
if ($report['foreign_tills'] > 0) {
    $notes[] = lang('Tills kept in another currency are counted at the base value each movement was booked at; their opening figures are not added in.');
}
if (!$options['group_chosen']) {
    $notes[] = lang(array('string' => 'The bucket size was picked from the length of the period ({var:1} days).', 'vars' => (int) $options['days']));
}

$output_notes = '';
foreach ($notes as $note) {
    $output_notes .= '<div class="form-text">' . h($note) . '</div>';
}

echo
pg_page_shell(array(
        'title' => lang('Cash flow'),
        'extra_classes' => 'erp erp_cash',
        'icon' => 'store',
        'heading' => lang('Cash flow'),
        'heading_description' => lang('What came into the tills and what went out over a period, in the base currency, and where the position stood.'),
        'cancel' => false,
    )) . '
<main id="content" class="container-fluid">
    <div class="row">
        <div class="col-12">
            ' . $liveform->output_errors() . '
            ' . $liveform->get_warnings() . '
            ' . $liveform->output_notices() . '

            <nav id="button_bar" class="pg-toolbar navigation" aria-label="' . lang('Button Bar') . '">
                <div class="btn-group btn-group-sm" role="group" aria-label="' . lang('Bucket') . '">
                    ' . $output_group_toggles . '
                </div>
                <div class="btn-group btn-group-sm" role="group" aria-label="' . lang('Period') . '">
                    ' . $quick($this_month, lang('This month')) . '
                    ' . $quick($last_month, lang('Last month')) . '
                    ' . $quick($last_90, lang('Last 90 days')) . '
                    ' . $quick($this_year, lang('This year')) . '
                </div>
                <form method="get" action="erp_cashflow.php" class="d-flex align-items-center gap-2 mb-0">
                    <input type="hidden" name="group" value="' . h($options['group_chosen'] ? $options['group'] : '') . '" />
                    <label for="from" class="small text-body-secondary text-nowrap mb-0">' . lang('Start') . '</label>
                    <input type="date" id="from" name="from" class="form-control form-control-sm w-auto" value="' . h($options['from']) . '" max="' . h(date('Y-m-d')) . '" />
                    <label for="to" class="small text-body-secondary text-nowrap mb-0">' . lang('End') . '</label>
                    <input type="date" id="to" name="to" class="form-control form-control-sm w-auto" value="' . h($options['to']) . '" max="' . h(date('Y-m-d')) . '" />
                    <select id="till" name="till" class="form-select form-select-sm w-auto" aria-label="' . lang('Till') . '">' . $output_till_options . '</select>
                    <button type="submit" class="btn btn-sm btn-ghost" title="' . lang('Apply') . '"><i class="bi bi-arrow-right-circle"></i></button>
                </form>
                <div class="pg-toolbar-grow"></div>
                <a class="btn btn-sm btn-ghost" href="erp_cashflow.php?' . h($query) . '&amp;csv=1"><i class="bi bi-filetype-csv me-1"></i>' . lang('Download CSV') . '</a>
                <a class="btn btn-sm btn-outline-secondary" href="erp_cash.php" data-loading-content="' . lang(array('string' => 'Loading')) . '"><i class="bi bi-cash-stack me-1"></i>' . lang('Cash and Bank') . '</a>
            </nav>

            <div class="card my-4">
                <div class="card-body">
                    <div class="row g-3 text-center text-md-start">
                        <div class="col-6 col-md">
                            <div class="small text-uppercase text-body-secondary">' . lang('Opening') . '</div>
                            <div class="h5 mb-0">' . h(erp_money_out($report['opening'])) . '</div>
                            <div class="small text-body-secondary">' . h(prepare_form_data_for_output($options['from'], 'date', false)) . '</div>
                        </div>
                        <div class="col-6 col-md">
                            <div class="small text-uppercase text-body-secondary">' . lang('Money in') . '</div>
                            <div class="h5 mb-0 text-success">' . h(erp_money_out($report['in'])) . '</div>
                        </div>
                        <div class="col-6 col-md">
                            <div class="small text-uppercase text-body-secondary">' . lang('Money out') . '</div>
                            <div class="h5 mb-0 text-danger">' . h(erp_money_out($report['out'])) . '</div>
                        </div>
                        <div class="col-6 col-md">
                            <div class="small text-uppercase text-body-secondary">' . lang('Net change') . '</div>
                            <div class="h5 mb-0">' . $signed($report['net']) . '</div>
                            <div class="small text-body-secondary">' . h(lang(array('string' => '{var:1} movement(s)', 'vars' => (int) $report['count']))) . '</div>
                        </div>
                        <div class="col-6 col-md">
                            <div class="small text-uppercase text-body-secondary">' . lang('Closing') . '</div>
                            <div class="h5 mb-0' . (($report['closing'] < 0) ? ' text-danger' : '') . '">' . h(erp_money_out($report['closing'])) . '</div>
                            <div class="small text-body-secondary">' . h(prepare_form_data_for_output($options['to'], 'date', false)) . '</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card my-4">
                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold d-flex align-items-center flex-wrap gap-2">
                    <span>' . h($groups[$options['group']]) . '</span>
                    <span class="small text-body-secondary fw-normal text-lowercase ms-auto">' . h($period_text) . '</span>
                </div>
                <div class="card-body p-0 position-relative">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>' . lang('Period') . '</th>
                                <th class="text-end">' . lang('Money in') . '</th>
                                <th class="text-end">' . lang('Money out') . '</th>
                                <th class="text-end">' . lang('Net change') . '</th>
                                <th class="text-end">' . lang('Movements') . '</th>
                                <th class="text-end">' . lang('Balance') . '</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr class="fw-bold">
                                <td>' . lang('Opening Balance') . '</td>
                                <td colspan="4"></td>
                                <td class="text-end">' . h(erp_money_out($report['opening'])) . '</td>
                            </tr>' . $output_rows . '
                        </tbody>
                        <tfoot>
                            <tr class="fw-bold">
                                <td>' . lang('Total') . '</td>
                                <td class="text-end">' . h(erp_money_out($report['in'])) . '</td>
                                <td class="text-end">' . h(erp_money_out($report['out'])) . '</td>
                                <td class="text-end">' . $signed($report['net']) . '</td>
                                <td class="text-end small">' . number_format($report['count']) . '</td>
                                <td class="text-end">' . h(erp_money_out($report['closing'])) . '</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                ' . (($output_notes !== '') ? '<div class="px-3 pb-3">' . $output_notes . '</div>' : '') . '
            </div>

            <div class="row">
                <div class="col-12 col-xl-8">
                    <div class="card my-4">
                        <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">' . lang('By till') . '</div>
                        <div class="card-body p-0 position-relative">
                            <table class="table table-hover align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>' . lang('Till') . '</th>
                                        <th>' . lang('Type') . '</th>
                                        <th class="text-end">' . lang('Opening') . '</th>
                                        <th class="text-end">' . lang('Money in') . '</th>
                                        <th class="text-end">' . lang('Money out') . '</th>
                                        <th class="text-end">' . lang('Net change') . '</th>
                                        <th class="text-end">' . lang('Movements') . '</th>
                                        <th class="text-end">' . lang('Closing') . '</th>
                                    </tr>
                                </thead>
                                <tbody>' . $output_till_rows . '
                                </tbody>
                            </table>
                        </div>
                        ' . (((int) $options['till'] === 0) ? '<div class="px-3 pb-3"><div class="form-text">' . lang('Transfers are counted here: what left one till arrived in another.') . '</div></div>' : '') . '
                    </div>
                </div>
                <div class="col-12 col-xl-4">
                    <div class="card my-4">
                        <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">' . lang('By payment method') . '</div>
                        <div class="card-body p-0 position-relative">
                            <table class="table table-hover align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>' . lang('Method') . '</th>
                                        <th class="text-end">' . lang('In') . '</th>
                                        <th class="text-end">' . lang('Out') . '</th>
                                        <th class="text-end">' . lang('Net change') . '</th>
                                        <th class="text-end">#</th>
                                    </tr>
                                </thead>
                                <tbody>' . $output_method_rows . '
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>
' .
output_footer();

$liveform->remove_form();
