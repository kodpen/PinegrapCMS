<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * ERP - bank statements: take in the file a bank lets its customers
 * download (CSV), and the statements taken in so far with the lines still
 * to deal with. The work lives in includes/erp/bank_import.php.
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
$liveform = new liveform('erp_bank_statements');

$ready = erp_bank_import_ready();
$readonly = defined('USER_ERP_READONLY') && USER_ERP_READONLY;
$self = PATH . SOFTWARE_DIRECTORY . '/erp_bank_statements.php';

if ($_POST) {
    validate_token_field();

    $file = $_FILES['statement'] ?? null;

    if (!is_array($file) || ((int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) || !is_uploaded_file((string) $file['tmp_name'])) {
        $liveform->mark_error('statement', h(lang('Choose the file the bank gave you.')));
        go($self);
    }

    if ((int) $file['size'] > 5 * 1024 * 1024) {
        $liveform->mark_error('statement', h(lang('The file is larger than 5 MB.')));
        go($self);
    }

    $uploaded = erp_bank_statement_upload((int) ($_POST['cash_account_id'] ?? 0), (string) $file['tmp_name'], (string) $file['name'], (int) $user['id']);

    if (!$uploaded['success']) {
        $liveform->mark_error('_error', h($uploaded['error']));
        go($self);
    }

    log_activity(lang(array('string' => 'erp bank statement (#{var:1}) was taken in', 'vars' => $uploaded['id'])), $_SESSION['sessionusername']);
    go(PATH . SOFTWARE_DIRECTORY . '/erp_bank_statement.php?id=' . (int) $uploaded['id']);
}

if (!$ready) {
    $liveform->mark_error('', lang('Bank statements come with the software update; run the update to use them.'));
}

$tills = '';
foreach ((array) db_items("SELECT id, name, currency, kind FROM erp_cash_accounts WHERE is_active = 1 ORDER BY (kind = 'bank') DESC, sort_order ASC, id ASC") as $till) {
    $tills .= '<option value="' . (int) $till['id'] . '">' . h($till['name'] . ' (' . $till['currency'] . ')') . '</option>';
}

$output_rows = '';
foreach (erp_bank_statements_list() as $row) {
    $output_rows .= '
        <tr>
            <td class="text-nowrap" data-sort="' . (int) $row['created_at'] . '"><a class="link-body-emphasis fw-semibold" href="erp_bank_statement.php?id=' . (int) $row['id'] . '">' . h(prepare_form_data_for_output(date('Y-m-d H:i:s', (int) $row['created_at']), 'date and time')) . '</a></td>
            <td>' . h((string) $row['till_name']) . '</td>
            <td class="small text-break">' . h((string) $row['file_name']) . '</td>
            <td class="text-end">' . (int) $row['line_count'] . '</td>
            <td class="text-end">' . (((string) $row['status'] === 'mapping') ? '<span class="badge text-bg-warning">' . lang('Columns to choose') . '</span>' : (((int) $row['open_lines'] > 0) ? '<span class="badge text-bg-warning">' . (int) $row['open_lines'] . '</span>' : '<span class="badge text-bg-success">' . lang('Done') . '</span>')) . '</td>
        </tr>';
}

if ($output_rows === '') {
    $output_rows = '<tr data-pg-sort-fixed><td colspan="5" class="text-center text-body-secondary py-4">' . lang('No statement taken in yet.') . '</td></tr>';
}

echo
pg_page_shell([
    'title' => lang('Bank statements'),
    'extra classes' => 'erp erp_cash',
    'icon' => 'erp',
    'heading' => lang('Bank statements'),
    'heading_description' => lang('Take in the statement your bank lets you download: lines the books already have are matched, the rest are recorded here as collections, payments or expenses, or set aside.'),
    'cancel' => array('enable' => 'true', 'url' => 'erp_cash.php'),
    'breadcrumb' => array(
        array('label' => lang('Cash and Bank'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/erp_cash.php'),
        array('label' => lang('Bank statements')),
    ),
]) . '
<main id="content" class="container-fluid">
    <div class="row">
        <div class="col-12 col-xxl-9">
            ' . $liveform->output_errors() . '
            ' . $liveform->get_warnings() . '
            ' . $liveform->output_notices() . '

            ' . (($ready && !$readonly) ? '<form method="post" action="erp_bank_statements.php" enctype="multipart/form-data" class="card my-4">
                ' . get_token_field() . '
                <div class="card-body row g-2 align-items-end">
                    <div class="col-12 col-md-4">
                        <label class="form-label" for="cash_account_id">' . lang('Bank account') . '</label>
                        <select class="form-select" id="cash_account_id" name="cash_account_id">' . $tills . '</select>
                    </div>
                    <div class="col-12 col-md-5">
                        <label class="form-label" for="statement">' . lang('Statement file (CSV)') . '</label>
                        <input type="file" class="form-control" id="statement" name="statement" accept=".csv,.txt,text/csv" required />
                    </div>
                    <div class="col-12 col-md-3">
                        <button type="submit" class="btn btn-primary w-100"><i class="bi bi-upload me-2" aria-hidden="true"></i>' . lang('Take in') . '</button>
                    </div>
                    <div class="col-12 form-text">' . lang('Save the statement from internet banking as CSV (in Excel: Save As, CSV). You choose the columns on the next screen; they are remembered for this bank account. A line already taken in is not added twice.') . '</div>
                </div>
            </form>' : '') . '

            <div class="card my-4">
                <div class="card-body p-0 table-responsive">
                    <table class="table table-hover align-middle mb-0" data-pg-sort>
                        <thead>
                            <tr>
                                <th>' . lang('Taken in on') . '</th>
                                <th>' . lang('Bank account') . '</th>
                                <th>' . lang('File') . '</th>
                                <th class="text-end">' . lang('Lines') . '</th>
                                <th class="text-end">' . lang('To do') . '</th>
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
