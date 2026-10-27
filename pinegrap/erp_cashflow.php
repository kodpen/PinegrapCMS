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
 * ?view=forecast is the other side: what the books say is still to come
 * over the next 30, 60 or 90 days (includes/erp/cash_forecast.php).
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

// Two views: what happened (the report below) and what is ahead (the
// forecast, includes/erp/cash_forecast.php), side by side in the toolbar.
$output_view_pills = function ($active) {
    return '<ul class="nav nav-pills nav-sm mb-0 me-3">
                    <li class="nav-item"><a class="nav-link py-1 px-3' . (($active === 'flow') ? ' active' : '') . '" href="erp_cashflow.php">' . lang('What happened') . '</a></li>
                    <li class="nav-item"><a class="nav-link py-1 px-3' . (($active === 'forecast') ? ' active' : '') . '" href="erp_cashflow.php?view=forecast">' . lang('What is ahead') . '</a></li>
                </ul>';
};

if (($_GET['view'] ?? '') === 'forecast') {
    $forecast = erp_cash_forecast($_GET['horizon'] ?? 90);
    $kinds = erp_cash_forecast_kinds();
    $bucket_keys = array_keys($forecast['buckets']);

    $signed = function ($kurus) {
        $kurus = (int) $kurus;
        $class = ($kurus > 0) ? 'fg-success' : (($kurus < 0) ? 'fg-danger' : 'fg-secondary');

        return '<span class="' . $class . '">' . h((($kurus > 0) ? '+' : '') . erp_money_out($kurus)) . '</span>';
    };

    $plain = function ($kurus) {
        return ((int) $kurus === 0) ? '<span class="fg-secondary">—</span>' : h(erp_money_out((int) $kurus));
    };

    // Column headings: past due, then each thirty days with its dates.
    $range_labels = array(30 => lang('1-30 days'), 60 => lang('31-60 days'), 90 => lang('61-90 days'));
    $output_heads = '';
    foreach ($forecast['buckets'] as $key => $bucket) {
        $caption = ($key === 'overdue') ? lang('Past due') : $range_labels[(int) substr($key, 1)];
        $dates = ($key === 'overdue') ? '' : '<div class="small fw-normal fg-secondary text-nowrap">'
            . h(prepare_form_data_for_output($bucket['start'], 'date', false) . ' – ' . prepare_form_data_for_output($bucket['end'], 'date', false)) . '</div>';
        $output_heads .= '<th class="text-end">' . h($caption) . $dates . '</th>';
    }

    $output_kind_rows = '';
    foreach ($kinds as $kind => $info) {
        $cells = '';
        foreach ($bucket_keys as $key) {
            $cells .= '<td class="text-end">' . $plain($forecast['kinds'][$kind][$key]) . '</td>';
        }
        $output_kind_rows .= '
                            <tr>
                                <td><i class="bi ' . (($info['direction'] === 'in') ? 'bi-arrow-down-left fg-success' : 'bi-arrow-up-right fg-danger') . ' me-3" aria-hidden="true"></i>' . h($info['label']) . '</td>
                                ' . $cells . '
                                <td class="text-end fw-semibold">' . $plain($forecast['kinds'][$kind]['total']) . '</td>
                            </tr>';
    }

    $total_row = function ($label, $values, $format, $class = '') use ($bucket_keys) {
        $cells = '';
        foreach ($bucket_keys as $key) {
            $cells .= '<td class="text-end">' . $format($values[$key]) . '</td>';
        }

        return '
                            <tr class="' . $class . '">
                                <td>' . h($label) . '</td>
                                ' . $cells . '
                                <td class="text-end">' . $format($values['total']) . '</td>
                            </tr>';
    };

    $balance_out = function ($kurus) {
        return '<span class="' . (((int) $kurus < 0) ? 'fg-danger' : '') . '">' . h(erp_money_out((int) $kurus)) . '</span>';
    };

    $output_item_rows = '';
    foreach (array_slice($forecast['items'], 0, ERP_CASH_FORECAST_MAX_ITEMS) as $item) {
        $in = ($item['direction'] === 'in');
        $title = ((string) $item['title'] !== '') ? h((string) $item['title']) : '—';
        $output_item_rows .= '
                            <tr>
                                <td class="text-nowrap' . (($item['bucket'] === 'overdue') ? ' fg-danger' : '') . '">' . h(prepare_form_data_for_output((string) $item['date'], 'date', false)) . '</td>
                                <td>' . h($kinds[$item['kind']]['label']) . '</td>
                                <td>' . (((string) $item['url'] !== '') ? '<a class="pg-link-body-emphasis" href="' . h((string) $item['url']) . '">' . $title . '</a>' : $title) . '</td>
                                <td>' . h((string) $item['party']) . '</td>
                                <td class="text-end fg-success">' . ($in ? h(erp_money_out((int) $item['amount'])) : '') . '</td>
                                <td class="text-end fg-danger">' . ($in ? '' : h(erp_money_out((int) $item['amount']))) . '</td>
                            </tr>';
    }

    if ($output_item_rows === '') {
        $output_item_rows = '
                            <tr><td colspan="6" class="text-center fg-secondary py-7">' . lang('Nothing is due in this period.') . '</td></tr>';
    }

    $notes = array(lang('Amounts past their date are counted as if they settled today. Draft invoices and cancelled documents are not counted.'));
    if ($forecast['cheque_tills'] > 0) {
        $notes[] = lang('The tills the cheques and notes are kept in are left out of the cash today; the cheques and notes are counted on their due dates instead.');
    }
    if ($forecast['foreign_tills'] > 0) {
        $notes[] = lang('Tills kept in another currency are not added into the cash today.');
    }
    if ($forecast['unpriced'] > 0) {
        $notes[] = lang(array('string' => '{var:1} repeating document(s) in another currency are left out: no exchange rate is recorded for today.', 'vars' => (int) $forecast['unpriced']));
    }
    if (count($forecast['items']) > ERP_CASH_FORECAST_MAX_ITEMS) {
        $notes[] = lang(array('string' => 'The list shows the first {var:1} of {var:2} movements; the table counts them all.', 'vars' => array(ERP_CASH_FORECAST_MAX_ITEMS, count($forecast['items']))));
    }

    $output_notes = '';
    foreach ($notes as $note) {
        $output_notes .= '<div class="form-text">' . h($note) . '</div>';
    }

    $last_day = end($forecast['buckets'])['end'];

    echo
    pg_page_shell(array(
            'title' => lang('Cash forecast'),
            'extra classes' => 'erp erp_cash',
            'icon' => 'erp',
            'heading' => lang('Cash forecast'),
            'heading_description' => lang('What the books say will come in and go out over the coming days: open invoices, unpaid expenses, repeating documents and cheques on their due dates, and where the tills would stand.'),
            'cancel' => false,
        )) . '
<main id="content" class="container-fluid">
    <div class="row">
        <div class="col-12">
            ' . $liveform->output_errors() . '
            ' . $liveform->get_warnings() . '
            ' . $liveform->output_notices() . '

            <nav id="button_bar" class="pg-toolbar navigation" aria-label="' . lang('Button Bar') . '">
                ' . $output_view_pills('forecast') . '
                ' . pg_filter_select('horizon', erp_cash_forecast_horizons(), (string) $forecast['horizon'],
                    array('label' => lang('Period'), 'icon' => 'calendar3', 'action' => 'erp_cashflow.php', 'keep' => array('view'))) . '
                <div class="pg-toolbar-grow"></div>
                <a class="btn btn-sm btn-outline theme-secondary" href="erp_cash.php"><i class="bi bi-cash-stack me-1"></i>' . lang('Cash and Bank') . '</a>
            </nav>

            <div class="card my-7">
                <div class="card-body">
                    <div class="row g-5 text-center md:text-start">
                        <div class="col-6 md:col">
                            <div class="small text-uppercase fg-secondary">' . lang('Cash today') . '</div>
                            <div class="h5 mb-0">' . h(erp_money_out($forecast['cash'])) . '</div>
                            <div class="small fg-secondary">' . h(prepare_form_data_for_output($forecast['today'], 'date', false)) . '</div>
                        </div>
                        <div class="col-6 md:col">
                            <div class="small text-uppercase fg-secondary">' . lang('Expected in') . '</div>
                            <div class="h5 mb-0 fg-success">' . h(erp_money_out($forecast['in']['total'])) . '</div>
                        </div>
                        <div class="col-6 md:col">
                            <div class="small text-uppercase fg-secondary">' . lang('Expected out') . '</div>
                            <div class="h5 mb-0 fg-danger">' . h(erp_money_out($forecast['out']['total'])) . '</div>
                        </div>
                        <div class="col-6 md:col">
                            <div class="small text-uppercase fg-secondary">' . lang('Expected balance') . '</div>
                            <div class="h5 mb-0' . (($forecast['position']['total'] < 0) ? ' fg-danger' : '') . '">' . h(erp_money_out($forecast['position']['total'])) . '</div>
                            <div class="small fg-secondary">' . h(prepare_form_data_for_output($last_day, 'date', false)) . '</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card my-7">
                <div class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">' . h(erp_cash_forecast_horizons()[$forecast['horizon']]) . '</div>
                <div class="card-body p-0 position-relative table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th></th>
                                ' . $output_heads . '
                                <th class="text-end">' . lang('Total') . '</th>
                            </tr>
                        </thead>
                        <tbody>' . $output_kind_rows . '
                        </tbody>
                        <tfoot>'
                            . $total_row(lang('Money in'), $forecast['in'], $plain)
                            . $total_row(lang('Money out'), $forecast['out'], $plain)
                            . $total_row(lang('Net change'), $forecast['net'], $signed)
                            . $total_row(lang('Expected balance'), $forecast['position'], $balance_out, 'fw-bold') . '
                        </tfoot>
                    </table>
                </div>
                <div class="px-5 pb-5">' . $output_notes . '</div>
            </div>

            <div class="card my-7">
                <div class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">' . lang('Expected movements') . '</div>
                <div class="card-body p-0 position-relative table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>' . lang('Due date') . '</th>
                                <th>' . lang('Type') . '</th>
                                <th>' . lang('Document') . '</th>
                                <th>' . lang('Account') . '</th>
                                <th class="text-end">' . lang('In') . '</th>
                                <th class="text-end">' . lang('Out') . '</th>
                            </tr>
                        </thead>
                        <tbody>' . $output_item_rows . '
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</main>
' .
    output_footer();

    $liveform->remove_form();
    exit();
}

// The quick ranges of the toolbar's period select. A range picked there
// arrives as ?range=<key> and stands for both ends of the period; it is
// written into from/to before they are read, so everything below (and the
// values the bucket select carries along) sees an ordinary from/to.
$quick_ranges = array(
    'this_month' => array(lang('This month'), date('Y-m-01'), date('Y-m-d')),
    'last_month' => array(lang('Last month'), date('Y-m-01', strtotime('first day of last month')), date('Y-m-t', strtotime('first day of last month'))),
    'last_90' => array(lang('Last 90 days'), date('Y-m-d', strtotime('-89 days')), date('Y-m-d')),
    'this_year' => array(lang('This year'), date('Y-01-01'), date('Y-m-d')),
);
$quick_key = is_string($_GET['range'] ?? null) ? $_GET['range'] : '';
if (isset($quick_ranges[$quick_key])) {
    $_GET['from'] = $quick_ranges[$quick_key][1];
    $_GET['to'] = $quick_ranges[$quick_key][2];
}

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
    $class = ($kurus === 0 && $muted_zero) ? ' fg-secondary' : '';

    return '<span class="' . trim($class) . '">' . h(erp_money_out($kurus)) . '</span>';
};

$signed = function ($kurus) {
    $kurus = (int) $kurus;
    $class = ($kurus > 0) ? 'fg-success' : (($kurus < 0) ? 'fg-danger' : 'fg-secondary');

    return '<span class="' . $class . '">' . h((($kurus > 0) ? '+' : '') . erp_money_out($kurus)) . '</span>';
};

// ----------------------------------------------------------------- toolbar

$output_till_options = '<option value="0"' . (((int) $options['till'] === 0) ? ' selected' : '') . '>' . lang('All tills') . '</option>';
foreach ($tills as $till) {
    $output_till_options .= '<option value="' . (int) $till['id'] . '"' . (((int) $options['till'] === (int) $till['id']) ? ' selected' : '') . '>' . h($till['name']) . (((int) $till['is_active'] === 1) ? '' : ' (' . lang('passive') . ')') . '</option>';
}

$period_text = prepare_form_data_for_output($options['from'], 'date', false) . ' – ' . prepare_form_data_for_output($options['to'], 'date', false);

// Bucket and period as two selects that keep each other and the till. A
// period that is not one of the quick ranges is listed by its dates.
$output_group_filter = pg_filter_select('group', $groups, (string) $options['group'],
    array('label' => lang('Bucket'), 'icon' => 'bar-chart-steps', 'action' => 'erp_cashflow.php', 'keep' => array('from', 'to', 'till')));

$period_options = array();
$period_current = '';
foreach ($quick_ranges as $key => $range) {
    $period_options[$key] = $range[0];
    if (($options['from'] === $range[1]) && ($options['to'] === $range[2])) {
        $period_current = $key;
    }
}
if ($period_current === '') {
    $period_options = array('' => $period_text) + $period_options;
}
$output_period_filter = pg_filter_select('range', $period_options, $period_current,
    array('label' => lang('Period'), 'icon' => 'calendar3', 'action' => 'erp_cashflow.php', 'keep' => array('from', 'to', 'till', 'group')));

// ------------------------------------------------------------ period rows
$output_rows = '';

foreach ($report['rows'] as $row) {
    $quiet = ($row['count'] === 0);

    $output_rows .= '
                            <tr' . ($quiet ? ' class="fg-secondary"' : '') . '>
                                <td class="align-middle text-nowrap" data-sort="' . h(str_replace('-', '', (string) $row['start'])) . '">' . h($row['label']) . '</td>
                                <td class="align-middle text-end" data-sort="' . (int) $row['in'] . '">' . $money($row['in']) . '</td>
                                <td class="align-middle text-end" data-sort="' . (int) $row['out'] . '">' . $money($row['out']) . '</td>
                                <td class="align-middle text-end" data-sort="' . (int) $row['net'] . '">' . $signed($row['net']) . '</td>
                                <td class="align-middle text-end small" data-sort="' . (int) $row['count'] . '">' . ($quiet ? '—' : number_format($row['count'])) . '</td>
                                <td class="align-middle text-end fw-bold' . (($row['balance'] < 0) ? ' fg-danger' : '') . '" data-sort="' . (int) $row['balance'] . '">' . h(erp_money_out($row['balance'])) . '</td>
                            </tr>';
}

// -------------------------------------------------------------- till rows
$output_till_rows = '';

foreach ($by_till as $row) {
    $name = h($row['name']) . (($row['is_active']) ? '' : ' <span class="badge theme-secondary">' . lang('passive') . '</span>');
    $note = (!$row['in_base']) ? '<div class="small fg-secondary">' . h(lang(array('string' => 'Kept in {var:1}; movements at their booked base value, the opening figure left out.', 'vars' => $row['currency']))) . '</div>' : '';

    $output_till_rows .= '
                            <tr>
                                <td class="align-middle"><a href="edit_erp_till.php?id=' . (int) $row['id'] . '" class="pg-link-body-emphasis">' . $name . '</a>' . $note . '</td>
                                <td class="align-middle">' . h($kind_labels[$row['kind']] ?? $row['kind']) . '</td>
                                <td class="align-middle text-end" data-sort="' . (int) $row['opening'] . '">' . h(erp_money_out($row['opening'])) . '</td>
                                <td class="align-middle text-end" data-sort="' . (int) $row['in'] . '">' . $money($row['in']) . '</td>
                                <td class="align-middle text-end" data-sort="' . (int) $row['out'] . '">' . $money($row['out']) . '</td>
                                <td class="align-middle text-end" data-sort="' . (int) $row['net'] . '">' . $signed($row['net']) . '</td>
                                <td class="align-middle text-end small" data-sort="' . (int) $row['count'] . '">' . (($row['count'] === 0) ? '—' : number_format($row['count'])) . '</td>
                                <td class="align-middle text-end fw-bold' . (($row['closing'] < 0) ? ' fg-danger' : '') . '" data-sort="' . (int) $row['closing'] . '">' . h(erp_money_out($row['closing'])) . '</td>
                            </tr>';
}

if ($output_till_rows === '') {
    $output_till_rows = '
                            <tr data-pg-sort-fixed><td colspan="8" class="text-center fg-secondary py-7">' . lang('There are no tills yet.') . '</td></tr>';
}

// ------------------------------------------------------------ method rows
$output_method_rows = '';

foreach ($by_method as $row) {
    $output_method_rows .= '
                            <tr>
                                <td class="align-middle">' . h($row['label']) . '</td>
                                <td class="align-middle text-end" data-sort="' . (int) $row['in'] . '">' . $money($row['in']) . '</td>
                                <td class="align-middle text-end" data-sort="' . (int) $row['out'] . '">' . $money($row['out']) . '</td>
                                <td class="align-middle text-end" data-sort="' . (int) ($row['in'] - $row['out']) . '">' . $signed($row['in'] - $row['out']) . '</td>
                                <td class="align-middle text-end small" data-sort="' . (int) $row['count'] . '">' . number_format($row['count']) . '</td>
                            </tr>';
}

if ($output_method_rows === '') {
    $output_method_rows = '
                            <tr data-pg-sort-fixed><td colspan="5" class="text-center fg-secondary py-7">' . lang('No movements in this period.') . '</td></tr>';
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
        'extra classes' => 'erp erp_cash',
        'icon' => 'erp',
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
                ' . $output_view_pills('flow') . '
                ' . $output_group_filter . '
                ' . $output_period_filter . '
                <form method="get" action="erp_cashflow.php" class="d-flex align-items-center gap-3 mb-0">
                    <input type="hidden" name="group" value="' . h($options['group_chosen'] ? $options['group'] : '') . '" />
                    <label for="from" class="small fg-secondary text-nowrap mb-0">' . lang('Start') . '</label>
                    <input type="date" id="from" name="from" class="form-control form-control-sm w-auto" value="' . h($options['from']) . '" max="' . h(date('Y-m-d')) . '" />
                    <label for="to" class="small fg-secondary text-nowrap mb-0">' . lang('End') . '</label>
                    <input type="date" id="to" name="to" class="form-control form-control-sm w-auto" value="' . h($options['to']) . '" max="' . h(date('Y-m-d')) . '" />
                    <select id="till" name="till" class="form-control form-control-sm w-auto" aria-label="' . lang('Till') . '">' . $output_till_options . '</select>
                    <button type="submit" class="btn btn-sm btn-ghost" title="' . lang('Apply') . '"><i class="bi bi-arrow-right-circle"></i></button>
                </form>
                <div class="pg-toolbar-grow"></div>
                <a class="btn btn-sm btn-ghost" href="erp_cashflow.php?' . h($query) . '&amp;csv=1"><i class="bi bi-filetype-csv me-1"></i>' . lang('Download CSV') . '</a>
                <a class="btn btn-sm btn-outline theme-secondary" href="erp_cash.php"><i class="bi bi-cash-stack me-1"></i>' . lang('Cash and Bank') . '</a>
            </nav>

            <div class="card my-7">
                <div class="card-body">
                    <div class="row g-5 text-center md:text-start">
                        <div class="col-6 md:col">
                            <div class="small text-uppercase fg-secondary">' . lang('Opening') . '</div>
                            <div class="h5 mb-0">' . h(erp_money_out($report['opening'])) . '</div>
                            <div class="small fg-secondary">' . h(prepare_form_data_for_output($options['from'], 'date', false)) . '</div>
                        </div>
                        <div class="col-6 md:col">
                            <div class="small text-uppercase fg-secondary">' . lang('Money in') . '</div>
                            <div class="h5 mb-0 fg-success">' . h(erp_money_out($report['in'])) . '</div>
                        </div>
                        <div class="col-6 md:col">
                            <div class="small text-uppercase fg-secondary">' . lang('Money out') . '</div>
                            <div class="h5 mb-0 fg-danger">' . h(erp_money_out($report['out'])) . '</div>
                        </div>
                        <div class="col-6 md:col">
                            <div class="small text-uppercase fg-secondary">' . lang('Net change') . '</div>
                            <div class="h5 mb-0">' . $signed($report['net']) . '</div>
                            <div class="small fg-secondary">' . h(lang(array('string' => '{var:1} movement(s)', 'vars' => (int) $report['count']))) . '</div>
                        </div>
                        <div class="col-6 md:col">
                            <div class="small text-uppercase fg-secondary">' . lang('Closing') . '</div>
                            <div class="h5 mb-0' . (($report['closing'] < 0) ? ' fg-danger' : '') . '">' . h(erp_money_out($report['closing'])) . '</div>
                            <div class="small fg-secondary">' . h(prepare_form_data_for_output($options['to'], 'date', false)) . '</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card my-7">
                <div class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold d-flex align-items-center flex-wrap gap-3">
                    <span>' . h($groups[$options['group']]) . '</span>
                    <span class="small fg-secondary fw-normal text-lowercase ms-auto">' . h($period_text) . '</span>
                </div>
                <div class="card-body p-0 position-relative table-responsive">
                    <table class="table table-hover align-middle mb-0" data-pg-sort>
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
                            <tr class="fw-bold" data-pg-sort-fixed="top">
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
                ' . (($output_notes !== '') ? '<div class="px-5 pb-5">' . $output_notes . '</div>' : '') . '
            </div>

            <div class="row">
                <div class="col-12 xl:col-8">
                    <div class="card my-7">
                        <div class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">' . lang('By till') . '</div>
                        <div class="card-body p-0 position-relative table-responsive">
                            <table class="table table-hover align-middle mb-0" data-pg-sort>
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
                        ' . (((int) $options['till'] === 0) ? '<div class="px-5 pb-5"><div class="form-text">' . lang('Transfers are counted here: what left one till arrived in another.') . '</div></div>' : '') . '
                    </div>
                </div>
                <div class="col-12 xl:col-4">
                    <div class="card my-7">
                        <div class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">' . lang('By payment method') . '</div>
                        <div class="card-body p-0 position-relative table-responsive">
                            <table class="table table-hover align-middle mb-0" data-pg-sort>
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
