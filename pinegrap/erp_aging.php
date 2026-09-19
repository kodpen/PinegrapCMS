<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * ERP - aging report.
 *
 * What each account owed (receivables) or was owed (payables) on a date, laid
 * out by how far past due it was: not yet due, 1-30, 31-60, 61-90 and over 90
 * days. Every figure is in the base currency at the booked rates; the
 * arithmetic lives in includes/erp/aging.php, shared with the invoice list and
 * the dashboard. Add csv=1 to the same address to get the table as a file.
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
$liveform = new liveform('erp_aging');

// The address carries the whole state, so a view can be bookmarked or linked
// from the dashboard. Anything that is not one of the known values falls back.
$direction = (($_GET['direction'] ?? 'sales') === 'purchase') ? 'purchase' : 'sales';
$as_of = erp_aging_as_of($_GET['as_of'] ?? '');
$is_today = ($as_of === date('Y-m-d'));

$rows = erp_aging_invoices($direction, $as_of);
$folded = erp_aging_by_account($rows);
$buckets = erp_aging_buckets();

$query = 'direction=' . $direction . '&as_of=' . $as_of;

// The file: the same table, written before any page output.
if (!empty($_GET['csv'])) {
    $csv = erp_aging_csv($folded);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="aging_' . $direction . '_' . $as_of . '.csv"');
    header('Cache-Control: no-store');

    erp_export_write_csv($csv['columns'], $csv['rows'], 'php://output');
    exit();
}

$direction_titles = array(
    'sales' => lang('Receivables'),
    'purchase' => lang('Payables'),
);

$output_rows = '';

foreach ($folded['accounts'] as $account) {
    $account_id = (int) $account['id'];
    $account_query = $query . '&account_id=' . $account_id;

    $output_cells = '';
    foreach (array_keys($buckets) as $key) {
        $amount = (int) $account['buckets'][$key];
        $output_cells .= '<td class="align-middle text-end' . (($amount > 0) ? '' : ' text-body-secondary') . '" data-order="' . $amount . '">'
            . (($amount > 0)
                ? '<a href="erp_invoices.php?' . h($account_query . '&bucket=' . $key) . '" class="link-body-emphasis">' . h(erp_money_out($amount)) . '</a>'
                : h(erp_money_out(0)))
            . '</td>';
    }

    $output_rows .=
        '<tr>
            <td class="align-middle text-start">
                <button type="button" class="m-1 btn-data-control btn btn-outline-primary border-2" data-loading-content=" " title="' . lang('View') . '" onclick="window.location.href=\'edit_erp_account.php?id=' . $account_id . '\'"><i class="bi bi-eye"></i></button>
            </td>
            <td style="max-width:280px" class="text-nowrap text-truncate align-middle chart_label">' . h($account['title'])
                . '<div class="small text-body-secondary">' . h(lang(array('string' => '{var:1} document(s)', 'vars' => (int) $account['count']))) . '</div></td>
            ' . $output_cells . '
            <td class="align-middle text-end fw-bold" data-order="' . (int) $account['total'] . '"><a href="erp_invoices.php?' . h($account_query . '&filter=open') . '" class="link-body-emphasis">' . h(erp_money_out((int) $account['total'])) . '</a></td>
        </tr>';
}

$output_total_cells = '';
foreach (array_keys($buckets) as $key) {
    $output_total_cells .= '<th class="text-end">' . h(erp_money_out((int) $folded['totals']['buckets'][$key])) . '</th>';
}

$output_headings = '';
foreach ($buckets as $label) {
    $output_headings .= '<th class="text-end">' . h($label) . '</th>';
}

// The other direction keeps the date; the date form keeps the direction.
$toggle = function ($value, $label, $icon) use ($direction, $as_of) {
    return '<a class="btn btn-sm btn-ghost' . (($direction === $value) ? ' active' : '') . '" href="erp_aging.php?direction=' . $value . '&amp;as_of=' . h($as_of) . '"' . (($direction === $value) ? ' aria-current="page"' : '') . '><i class="bi ' . $icon . ' me-1"></i>' . h($label) . '</a>';
};

$output_fx_note = erp_fx_enabled()
    ? '<div class="form-text px-3 pb-3">' . lang('Foreign-currency documents are counted at the rate they were booked at.') . '</div>'
    : '';

echo
pg_page_shell(array(
        'title' => lang('Aging report'),
        'extra_classes' => 'erp erp_aging',
        'icon' => 'store',
        'heading' => lang('Aging report'),
        'heading_description' => lang('Open invoices by account and by how long they have been past due, in the base currency.'),
        'cancel' => false,
    )) . '
<main id="content" class="container-fluid">
    <div class="row">
        <div class="col-12">
            ' . $liveform->output_errors() . '
            ' . $liveform->get_warnings() . '
            ' . $liveform->output_notices() . '

            <nav id="button_bar" class="pg-toolbar navigation" aria-label="' . lang('Button Bar') . '">
                <div class="btn-group btn-group-sm" role="group" aria-label="' . lang('Direction') . '">
                    ' . $toggle('sales', $direction_titles['sales'], 'bi-arrow-down-left-circle') . '
                    ' . $toggle('purchase', $direction_titles['purchase'], 'bi-arrow-up-right-circle') . '
                </div>
                <form method="get" action="erp_aging.php" class="d-flex align-items-center gap-2 mb-0">
                    <input type="hidden" name="direction" value="' . h($direction) . '" />
                    <label for="as_of" class="small text-body-secondary text-nowrap mb-0">' . lang('As of') . '</label>
                    <input type="date" id="as_of" name="as_of" class="form-control form-control-sm" value="' . h($as_of) . '" max="' . h(date('Y-m-d')) . '" />
                    <button type="submit" class="btn btn-sm btn-ghost" title="' . lang('Apply') . '"><i class="bi bi-arrow-right-circle"></i></button>
                </form>
                <div class="pg-toolbar-grow small text-body-secondary text-truncate">
                    ' . h(lang(array('string' => '{var:1} as of {var:2}: {var:3} document(s) open on {var:4} account(s)', 'vars' => array($direction_titles[$direction], prepare_form_data_for_output($as_of, 'date', false), (int) $folded['totals']['count'], count($folded['accounts']))))) . '
                </div>
                <a class="btn btn-sm btn-ghost" href="erp_aging.php?' . h($query) . '&amp;csv=1"><i class="bi bi-filetype-csv me-1"></i>' . lang('Download CSV') . '</a>
                <a class="btn btn-sm btn-outline-secondary" href="erp_invoices.php?' . h($query . '&filter=' . ($is_today ? 'overdue' : 'open')) . '" data-loading-content="' . lang(array('string' => 'Loading')) . '"><i class="bi bi-receipt me-1"></i>' . lang('Invoices') . '</a>
            </nav>

            <div class="card my-4">
                <div class="card-body p-0 position-relative">
                    <table class="chart table-hover table" style="width:100%;display:none;">
                        <thead>
                            <tr>
                                <th class="noVis">' . lang(array('string' => 'Action')) . '</th>
                                <th>' . lang('Account') . '</th>
                                ' . $output_headings . '
                                <th class="text-end">' . lang('Total') . '</th>
                            </tr>
                        </thead>
                        <tbody>' . $output_rows . '</tbody>
                        <tfoot>
                            <tr>
                                <th></th>
                                <th>' . lang('Total') . '</th>
                                ' . $output_total_cells . '
                                <th class="text-end">' . h(erp_money_out((int) $folded['totals']['total'])) . '</th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                ' . $output_fx_note . '
            </div>
        </div>
    </div>
</main>
' .
output_footer();

$liveform->remove_form();
