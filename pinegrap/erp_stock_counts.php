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

foreach (erp_stock_count_list() as $row) {
    $status = $statuses[(string) $row['status']] ?? array((string) $row['status'], 'secondary');
    $output_rows .= '
        <tr>
            <td><a class="link-body-emphasis fw-semibold" href="erp_stock_count.php?id=' . (int) $row['id'] . '">' . h((string) $row['title']) . '</a></td>
            <td class="text-nowrap" data-sort="' . (int) $row['created_at'] . '">' . h(prepare_form_data_for_output(date('Y-m-d H:i:s', (int) $row['created_at']), 'date and time')) . '</td>
            <td>' . h((string) $row['created_by_name']) . '</td>
            <td class="text-end">' . (int) $row['line_count'] . '</td>
            <td><span class="badge text-bg-' . h($status[1]) . '">' . h($status[0]) . '</span></td>
        </tr>';
}

if ($output_rows === '') {
    $output_rows = '<tr data-pg-sort-fixed><td colspan="5" class="text-center text-body-secondary py-4">' . lang('No count yet. Start one and scan the shelf.') . '</td></tr>';
}

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
        <div class="col-12 col-xxl-9">
            ' . $liveform->output_errors() . '
            ' . $liveform->get_warnings() . '
            ' . $liveform->output_notices() . '

            ' . (($ready && !$readonly) ? '<form method="post" action="erp_stock_counts.php" class="card my-4">
                ' . get_token_field() . '
                <div class="card-body row g-2 align-items-end">
                    <div class="col-12 col-md-8">
                        <label class="form-label" for="title">' . lang('New count') . '</label>
                        <input type="text" class="form-control" id="title" name="title" maxlength="100" placeholder="' . h(lang(array('string' => 'Count of {var:1}', 'vars' => prepare_form_data_for_output(date('Y-m-d'), 'date', false)))) . '" />
                    </div>
                    <div class="col-12 col-md-4">
                        <button type="submit" class="btn btn-primary w-100"><i class="bi bi-upc-scan me-2" aria-hidden="true"></i>' . lang('Start counting') . '</button>
                    </div>
                </div>
            </form>' : '') . '

            <div class="card my-4">
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
    </div>
</main>' .
output_footer();

$liveform->remove_form();
