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
        $output_cells .= '<td class="text-end' . (($amount > 0) ? '' : ' fg-secondary') . '" data-sort="' . $amount . '">'
            . (($amount > 0)
                ? '<a href="erp_invoices.php?' . h($account_query . '&bucket=' . $key) . '" class="pg-link-body-emphasis">' . h(erp_money_out($amount)) . '</a>'
                : h(erp_money_out(0)))
            . '</td>';
    }

    $output_rows .=
        '<tr>
            <th scope="row" class="text-wrap" data-sort="' . h($account['title']) . '"><a href="edit_erp_account.php?id=' . $account_id . '" class="pg-link-body-emphasis">' . h($account['title']) . '</a>'
                . '<div class="pg-text-meta fg-secondary">' . h(lang(array('string' => '{var:1} document(s)', 'vars' => (int) $account['count']))) . '</div></th>
            ' . $output_cells . '
            <td class="text-end fw-bold" data-sort="' . (int) $account['total'] . '"><a href="erp_invoices.php?' . h($account_query . '&filter=open') . '" class="pg-link-body-emphasis">' . h(erp_money_out((int) $account['total'])) . '</a></td>
        </tr>';
}

$output_total_cells = '';
foreach (array_keys($buckets) as $key) {
    $output_total_cells .= '<td class="text-end">' . h(erp_money_out((int) $folded['totals']['buckets'][$key])) . '</td>';
}

$output_headings = '';
foreach ($buckets as $label) {
    $output_headings .= '<th class="text-end">' . h($label) . '</th>';
}

// The other direction keeps the date (its select sends a form of its own
// through the form attribute); the date form keeps the direction.
$output_direction_options = '';
foreach ($direction_titles as $key => $label) {
    $output_direction_options .= '<option value="' . h($key) . '"' . (($key === $direction) ? ' selected' : '') . '>' . h($label) . '</option>';
}

$output_fx_note = erp_fx_enabled()
    ? '<p class="pg-report-frame-note px-7 py-5 mb-0">' . lang('Foreign-currency documents are counted at the rate they were booked at.') . '</p>'
    : '';

echo
pg_page_shell(array(
        'title' => lang('Aging report'),
        'extra classes' => 'erp erp_aging pg-measure-report',
        'icon' => 'erp',
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

            <form method="get" action="erp_aging.php" class="pg-report-tools disable_shortcut" id="erp_aging_as_of">
                <input type="hidden" name="direction" value="' . h($direction) . '" />
                <span class="pg-report-field">
                    <label class="pg-report-label" for="direction">' . lang('Direction') . '</label>
                    <select class="form-control" id="direction" name="direction" form="erp_aging_direction" onchange="this.form.submit()">' . $output_direction_options . '</select>
                </span>
                <span class="pg-report-field">
                    <label class="pg-report-label" for="as_of">' . lang('As of') . '</label>
                    <input type="date" data-pg-date class="form-control" id="as_of" name="as_of" value="' . h($as_of) . '" max="' . h(date('Y-m-d')) . '" />
                </span>
                <button type="submit" class="pg-button pg-button-quiet"><i class="bi bi-arrow-repeat" aria-hidden="true"></i>' . lang('Show') . '</button>
                <span class="pg-report-range text-wrap">' . h(lang(array('string' => '{var:1} as of {var:2}: {var:3} document(s) open on {var:4} account(s)', 'vars' => array($direction_titles[$direction], prepare_form_data_for_output($as_of, 'date', false), (int) $folded['totals']['count'], count($folded['accounts']))))) . '</span>
                <a class="pg-button pg-button-quiet ms-auto" href="erp_invoices.php?' . h($query . '&filter=' . ($is_today ? 'overdue' : 'open')) . '"><i class="bi bi-receipt" aria-hidden="true"></i>' . lang('Invoices') . '</a>
                <a class="pg-button pg-button-quiet" href="erp_aging.php?' . h($query) . '&amp;csv=1"><i class="bi bi-filetype-csv" aria-hidden="true"></i>' . lang('Download CSV') . '</a>
            </form>
            <form method="get" action="erp_aging.php" class="disable_shortcut" id="erp_aging_direction">' . ((isset($_GET['as_of']) && is_scalar($_GET['as_of']) && ((string) $_GET['as_of'] !== '')) ? '<input type="hidden" name="as_of" value="' . h((string) $_GET['as_of']) . '" />' : '') . '</form>

            <section class="pg-card pg-report-frame">
                <div class="pg-report-frame-head">
                    <h3 class="pg-report-frame-title">' . h($direction_titles[$direction]) . '</h3>
                    <span class="pg-report-frame-note">' . h(prepare_form_data_for_output($as_of, 'date', false)) . '</span>
                </div>
                <div class="pg-report-frame-body">
                    <table class="pg-report-table" data-pg-sort>
                        <thead>
                            <tr>
                                <th>' . lang('Account') . '</th>
                                ' . $output_headings . '
                                <th class="text-end">' . lang('Total') . '</th>
                            </tr>
                        </thead>
                        <tbody>' . $output_rows . '</tbody>
                        <tfoot>
                            <tr class="pg-report-total">
                                <th scope="row">' . lang('Total') . '</th>
                                ' . $output_total_cells . '
                                <td class="text-end">' . h(erp_money_out((int) $folded['totals']['total'])) . '</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                ' . $output_fx_note . '
            </section>
        </div>
    </div>
</main>
' . get_date_picker_format() . pg_fields_script() .
output_footer();

$liveform->remove_form();
