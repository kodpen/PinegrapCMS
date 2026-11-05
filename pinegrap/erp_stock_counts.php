<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * ERP - stock counts: the counts made so far and a new one. The work lives in
 * includes/erp/stock_counts.php.
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
$liveform = new liveform('erp_stock_counts');

$ready = erp_stock_counts_ready();
$readonly = defined('USER_ERP_READONLY') && USER_ERP_READONLY;

if ($_POST) {
    validate_token_field();

    // The title is one value. One posted as an array counts as not given:
    // (string) named the count "Array" with a notice.
    pg_request_single_values(array('title'));

    $count_id = erp_stock_count_create((string) ($_POST['title'] ?? ''), (int) $user['id']);

    if ($count_id <= 0) {
        $liveform->mark_error('_error', lang('Stock counts come with the software update; run the update to use them.'));
        go(PATH . SOFTWARE_DIRECTORY . '/erp_stock_counts.php');
    }

    log_activity(lang(array('string' => 'erp stock count (#{var:1}) was started', 'vars' => $count_id)), $_SESSION['sessionusername']);
    go(PATH . SOFTWARE_DIRECTORY . '/erp_stock_count.php?id=' . $count_id);
}

if (!$ready) {
    $liveform->mark_error('', lang('Stock counts come with the software update; run the update to use them.'));
}

$statuses = erp_stock_count_statuses();
$output_rows = '';

// Read in the status column: how many counts there are, by status.
$summary_total = 0;
$summary_by_status = array_fill_keys(array_keys($statuses), 0);

foreach (erp_stock_count_list() as $row) {
    $status = $statuses[(string) $row['status']] ?? array((string) $row['status'], 'secondary');
    $summary_total++;
    if (isset($summary_by_status[(string) $row['status']])) {
        $summary_by_status[(string) $row['status']]++;
    }
    $output_rows .= '
        <tr>
            <td class="text-wrap text-break" style="max-width:280px"><a class="pg-link-body-emphasis fw-semibold" href="erp_stock_count.php?id=' . (int) $row['id'] . '">' . h((string) $row['title']) . '</a></td>
            <td class="text-nowrap" data-sort="' . (int) $row['created_at'] . '">' . h(prepare_form_data_for_output(date('Y-m-d H:i:s', (int) $row['created_at']), 'date and time')) . '</td>
            <td>' . h((string) $row['created_by_name']) . '</td>
            <td class="text-end">' . (int) $row['line_count'] . '</td>
            <td><span class="badge theme-' . h($status[1]) . '">' . h($status[0]) . '</span></td>
        </tr>';
}

if ($output_rows === '') {
    $output_rows = '<tr data-pg-sort-fixed><td colspan="5" class="text-center fg-secondary py-7">' . lang('No count yet. Start one and scan the shelf.') . '</td></tr>';
}

// Each status with its badge and how many counts carry it.
$summary_rows = '';
foreach ($statuses as $key => $status) {
    $summary_rows .= '
                                <dt class="col-8 fw-normal mb-2"><span class="badge theme-' . h($status[1]) . '">' . h($status[0]) . '</span></dt>
                                <dd class="col-4 text-end mb-2">' . pg_format_number($summary_by_status[$key], 0) . '</dd>';
}

// Without the update or with read-only ERP access there is nothing to start:
// no form and no save bar, the list and the Summary stay.
$can_start = $ready && !$readonly;

echo
pg_page_shell([
    'title' => lang('Stock counts'),
    'extra classes' => 'erp erp_stock',
    'icon' => 'erp',
    'heading' => lang('Stock counts'),
    'heading_description' => lang('Count the shelf by scanning barcodes, correct the counts, then apply them: the store\'s stock becomes what was counted, and what it was before stays on the count.'),
    'cancel' => array('enable' => 'true', 'url' => 'erp_stock.php'),
    'breadcrumb' => array(
        array('label' => lang('Stock and cost'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/erp_stock.php'),
        array('label' => lang('Stock counts')),
    ),
]) . '
<main id="content" class="container-fluid">
    <div class="row">
        <div class="col-12">
            ' . $liveform->output_errors() . '
            ' . $liveform->get_warnings() . '
            ' . $liveform->output_notices() . '

            ' . ($can_start ? '<form method="post" action="erp_stock_counts.php">
                ' . get_token_field() : '') . '
                <div class="row">
                <div class="col-12 lg:col-8 2xl:col-9 order-1">
                ' . ($can_start ? '<div class="card my-7">
                    <div class="card-body">
                        <label class="form-label" for="title">' . lang('New count') . '</label>
                        <input type="text" class="form-control" id="title" name="title" maxlength="100" placeholder="' . h(lang(array('string' => 'Count of {var:1}', 'vars' => prepare_form_data_for_output(date('Y-m-d'), 'date', false)))) . '" />
                    </div>
                </div>' : '') . '
                <div class="card my-7">
                    <div class="card-body p-0 table-responsive">
                        <table class="table table-hover align-middle mb-0" data-pg-sort>
                            <thead>
                                <tr>
                                    <th>' . lang('Stock count') . '</th>
                                    <th>' . lang('Started') . '</th>
                                    <th>' . lang('Started by') . '</th>
                                    <th class="text-end">' . lang('Products') . '</th>
                                    <th>' . lang('Status') . '</th>
                                </tr>
                            </thead>
                            <tbody>' . $output_rows . '</tbody>
                        </table>
                    </div>
                </div>
                </div>
                <div class="col-12 lg:col-4 2xl:col-3 order-2">
                    <div class="card my-7 position-sticky" style="top:3.3rem;">
                        <div class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">' . h(lang('Summary')) . '</div>
                        <div class="card-body">
                            <dl class="row small mb-0">
                                <dt class="col-8 fw-normal fg-secondary">' . h(lang('Stock counts')) . '</dt>
                                <dd class="col-4 text-end mb-2">' . pg_format_number($summary_total, 0) . '</dd>' . $summary_rows . '
                            </dl>
                        </div>
                    </div>
                </div>
                ' . ($can_start ? '<div class="col-12 order-3">
                <nav class="buttons navigation text-center position-sticky mb-7" style="bottom:.5rem;" aria-label="' . h(lang('data edit buttons')) . '">
                    <div class="container">
                        <button type="submit" class="btn my-1 btn-solid theme-primary"><i class="bi bi-upc-scan me-3" aria-hidden="true"></i><span class="pg-btn-label">' . h(lang('Start counting')) . '</span></button>
                    </div>
                </nav>
                </div>' : '') . '
                </div>
            ' . ($can_start ? '</form>' : '') . '
        </div>
    </div>
</main>' .
output_footer();

$liveform->remove_form();
