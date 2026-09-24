<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * ERP - repeating invoices: every invoice set to be written again, when the
 * next one is due, how it is written and what stood in its way. Started and
 * stopped on the invoice itself; the work lives in
 * includes/erp/invoice_recurring.php.
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
$liveform = new liveform('erp_invoice_recurrences');

$ready = erp_invoice_recurring_ready();
$show_stopped = !empty($_GET['all']);

if (!$ready) {
    $liveform->mark_error('', lang('Repeating invoices come with the software update; run the update to use them.'));
}

$periods = erp_expense_recurring_periods();
$modes = erp_invoice_recurring_modes();
$output_rows = '';

foreach (erp_invoice_recurrences(!$show_stopped) as $row) {
    $active = ((string) $row['status'] === 'active');

    $output_rows .= '
        <tr' . ($active ? '' : ' class="opacity-75"') . '>
            <td style="max-width:260px" class="text-truncate">' . h((string) $row['account_title']) . '</td>
            <td class="text-nowrap"><a class="link-body-emphasis" href="edit_erp_invoice.php?id=' . (int) $row['source_invoice_id'] . '#erp-recurring">' . h((string) $row['source_number']) . '</a></td>
            <td>' . h($periods[(int) $row['every_months']] ?? '') . '</td>
            <td>' . h($modes[(string) $row['mode']] ?? '') . '</td>
            <td class="text-nowrap" data-sort="' . h(str_replace('-', '', (string) $row['next_date'])) . '">' . ($active ? h(prepare_form_data_for_output((string) $row['next_date'], 'date')) : '<span class="badge text-bg-secondary">' . lang('Stopped') . '</span>')
                . (((string) $row['end_date'] !== '0000-00-00') ? '<div class="small text-body-secondary">' . h(lang(array('string' => 'Until {var:1}.', 'vars' => prepare_form_data_for_output((string) $row['end_date'], 'date', false)))) . '</div>' : '') . '</td>
            <td class="text-end text-nowrap" data-sort="' . (int) $row['grand_total'] . '">' . h(erp_money_out_currency((int) $row['grand_total'], (string) $row['currency'])) . '</td>
            <td class="text-end">' . (int) $row['occurrences'] . '</td>
            <td>' . (((int) $row['last_invoice_id'] > 0)
                ? '<a class="link-body-emphasis" href="' . (((string) $row['last_status'] === 'draft') ? 'edit_erp_invoice_draft.php' : 'edit_erp_invoice.php') . '?id=' . (int) $row['last_invoice_id'] . '">' . h(((string) $row['last_number'] !== '') ? (string) $row['last_number'] : lang('Draft')) . '</a>'
                : '—')
                . (((string) $row['last_error'] !== '') ? '<div class="small text-danger">' . h((string) $row['last_error']) . '</div>' : '') . '</td>
        </tr>';
}

if ($output_rows === '') {
    $output_rows = '<tr data-pg-sort-fixed><td colspan="8" class="text-center text-body-secondary py-4">' . lang('No invoice repeats yet. Open an issued invoice typed in the ERP and use "Repeat this invoice".') . '</td></tr>';
}

echo
pg_page_shell([
    'title' => lang('Repeating invoices'),
    'extra classes' => 'erp erp_invoices',
    'icon' => 'erp',
    'heading' => lang('Repeating invoices'),
    'heading_description' => lang('Invoices written again every month, every three months or every year: maintenance contracts, rents, subscriptions.'),
    'cancel' => array('enable' => 'true', 'url' => 'erp_invoices.php'),
    'breadcrumb' => array(
        array('label' => lang('Invoices'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/erp_invoices.php'),
        array('label' => lang('Repeating invoices')),
    ),
]) . '
<main id="content" class="container-fluid">
    <div class="row">
        <div class="col-12">
            ' . $liveform->output_errors() . '
            ' . $liveform->get_warnings() . '
            ' . $liveform->output_notices() . '

            <nav id="button_bar" class="pg-toolbar navigation" aria-label="' . lang('Button Bar') . '">
                <div class="btn-group" role="group">
                    <a class="btn btn-sm ' . (!$show_stopped ? 'btn-secondary' : 'btn-outline-secondary') . '" href="erp_invoice_recurrences.php">' . lang('Running') . '</a>
                    <a class="btn btn-sm ' . ($show_stopped ? 'btn-secondary' : 'btn-outline-secondary') . '" href="erp_invoice_recurrences.php?all=1">' . lang('All') . '</a>
                </div>
            </nav>

            <div class="card my-4">
                <div class="card-body p-0 table-responsive">
                    <table class="table table-hover align-middle mb-0" data-pg-sort>
                        <thead>
                            <tr>
                                <th>' . lang('Account') . '</th>
                                <th>' . lang('From invoice') . '</th>
                                <th>' . lang('Repeats') . '</th>
                                <th>' . lang('Each invoice is') . '</th>
                                <th>' . lang('Next invoice on') . '</th>
                                <th class="text-end">' . lang('Total') . '</th>
                                <th class="text-end">' . lang('Written') . '</th>
                                <th>' . lang('Last one') . '</th>
                            </tr>
                        </thead>
                        <tbody>' . $output_rows . '</tbody>
                    </table>
                </div>
                <div class="form-text px-3 pb-3">' . lang('The total is the source invoice\'s; each new one is worked out again on its day. A day in a locked period is skipped; anything else that stands in the way is retried the next day.') . '</div>
            </div>
        </div>
    </div>
</main>' .
output_footer();

$liveform->remove_form();
