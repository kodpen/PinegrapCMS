<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * ERP - cheques and promissory notes: the ones taken from customers and the
 * ones given to suppliers, soonest due first, and a form for a new one. The
 * work lives in includes/erp/cheques.php.
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
$liveform = new liveform('erp_cheques');

$ready = erp_cheques_ready();
$readonly = defined('USER_ERP_READONLY') && USER_ERP_READONLY;
$self = PATH . SOFTWARE_DIRECTORY . '/erp_cheques.php';

if ($_POST) {
    validate_token_field();
    $liveform->add_fields_to_session();

    $date_in = function ($field) use ($liveform) {
        $typed = trim((string) $liveform->get_field_value($field));
        return (($typed !== '') && validate_date($typed)) ? prepare_form_data_for_input($typed, 'date') : '';
    };

    $created = erp_cheque_create(array(
        'direction' => (string) $liveform->get_field_value('direction'),
        'kind' => (string) $liveform->get_field_value('kind'),
        'account_id' => (int) $liveform->get_field_value('account_id'),
        'amount' => erp_kurus($liveform->get_field_value('amount')),
        'doc_date' => $date_in('doc_date'),
        'due_date' => $date_in('due_date'),
        'serial_no' => (string) $liveform->get_field_value('serial_no'),
        'bank_name' => (string) $liveform->get_field_value('bank_name'),
        'branch' => (string) $liveform->get_field_value('branch'),
        'drawer' => (string) $liveform->get_field_value('drawer'),
        'till_id' => (int) $liveform->get_field_value('till_id'),
        'notes' => (string) $liveform->get_field_value('notes'),
    ), (int) $user['id']);

    if (!$created['success']) {
        $liveform->mark_error($created['field'], h($created['error']));
        go($self . '#erp_cheque_new');
    }

    log_activity(lang(array('string' => 'erp cheque or note (#{var:1}) was recorded', 'vars' => $created['id'])), $_SESSION['sessionusername']);
    $liveform->remove_form();
    $liveform_cheque = new liveform('erp_cheque');
    $liveform_cheque->add_notice(h(lang('Recorded, with its movement in the till and on the account.')));
    go(PATH . SOFTWARE_DIRECTORY . '/erp_cheque.php?id=' . (int) $created['id']);
}

if (!$ready) {
    $liveform->mark_error('', lang('Cheques and notes come with the software update; run the update to use them.'));
}

$direction = in_array((string) ($_GET['direction'] ?? ''), array('received', 'given'), true) ? (string) $_GET['direction'] : '';
$status = (string) ($_GET['status'] ?? 'open');
$search = mb_substr(trim((string) ($_GET['search'] ?? '')), 0, 100);
$statuses = erp_cheque_statuses();
$kinds = erp_cheque_kinds();
$totals = erp_cheque_totals();
$today = date('Y-m-d');

$output_rows = '';
foreach (erp_cheque_rows(array('direction' => $direction, 'status' => $status, 'search' => $search)) as $row) {
    $state = $statuses[(string) $row['status']] ?? array((string) $row['status'], 'secondary');
    $open = in_array((string) $row['status'], array('portfolio', 'deposited', 'given'), true);
    $late = $open && ((string) $row['due_date'] < $today);

    $output_rows .= '
        <tr>
            <td class="text-nowrap" data-sort="' . h(str_replace('-', '', (string) $row['due_date'])) . '"><a class="link-body-emphasis fw-semibold" href="erp_cheque.php?id=' . (int) $row['id'] . '">' . h(prepare_form_data_for_output((string) $row['due_date'], 'date')) . '</a>'
                . ($late ? ' <span class="badge text-bg-danger">' . lang('Overdue') . '</span>' : '') . '</td>
            <td>' . h($kinds[(string) $row['kind']] ?? '') . '<div class="small text-body-secondary">' . ((string) $row['direction'] === 'received' ? lang('Received') : lang('Given')) . '</div></td>
            <td style="max-width:240px" class="text-truncate">' . h((string) $row['account_title']) . (((string) $row['drawer'] !== '') ? '<div class="small text-body-secondary text-truncate">' . h((string) $row['drawer']) . '</div>' : '') . '</td>
            <td class="small">' . h(trim((string) $row['bank_name'] . ' ' . (string) $row['serial_no'])) . '</td>
            <td class="text-end text-nowrap" data-sort="' . (int) $row['amount_base'] . '">' . h(erp_money_out_currency((int) $row['amount'], (string) $row['currency'])) . '</td>
            <td><span class="badge text-bg-' . h($state[1]) . '">' . h($state[0]) . '</span></td>
        </tr>';
}

if ($output_rows === '') {
    $output_rows = '<tr data-pg-sort-fixed><td colspan="6" class="text-center text-body-secondary py-4">' . lang('No cheque or note here.') . '</td></tr>';
}

$link = function ($params, $label, $active) {
    return '<a class="btn btn-sm ' . ($active ? 'btn-secondary' : 'btn-outline-secondary') . '" href="erp_cheques.php' . (!empty($params) ? '?' . h(http_build_query($params)) : '') . '">' . $label . '</a>';
};

$accounts = array(lang('Choose an account') => '');
foreach (erp_accounts(array('status' => 'active')) as $account) {
    $accounts[h($account['title'])] = (string) (int) $account['id'];
}
$tills = array(lang('Choose a till or bank account') => '');
foreach ((array) db_items("SELECT id, name, currency FROM erp_cash_accounts WHERE is_active = 1 ORDER BY sort_order ASC, id ASC") as $till) {
    $tills[h($till['name'] . ' (' . $till['currency'] . ')')] = (string) (int) $till['id'];
}

if (!$liveform->field_in_session('doc_date')) {
    $liveform->assign_field_value('doc_date', prepare_form_data_for_output($today, 'date'));
    $liveform->assign_field_value('direction', 'received');
    $liveform->assign_field_value('kind', 'cheque');
}

$output_new = (!$ready || $readonly) ? '' : '
            <form method="post" action="erp_cheques.php" class="card my-4" id="erp_cheque_new" autocomplete="off">
                ' . get_token_field() . '
                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">' . lang('New cheque or note') . '</div>
                <div class="card-body row g-2">
                    <div class="col-6 col-md-3">
                        <label class="form-label" for="direction">' . lang('Direction') . '</label>
                        ' . $liveform->output_field(array('type' => 'select', 'id' => 'direction', 'name' => 'direction', 'class' => 'form-select', 'options' => array(lang('Taken from a customer') => 'received', lang('Given to a supplier') => 'given'))) . '
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="form-label" for="kind">' . lang('Type') . '</label>
                        ' . $liveform->output_field(array('type' => 'select', 'id' => 'kind', 'name' => 'kind', 'class' => 'form-select', 'options' => array($kinds['cheque'] => 'cheque', $kinds['note'] => 'note'))) . '
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="account_id">' . lang('Account') . '</label>
                        ' . $liveform->output_field(array('type' => 'select', 'id' => 'account_id', 'name' => 'account_id', 'class' => 'form-select', 'options' => $accounts)) . '
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="form-label" for="amount">' . lang('Amount') . '</label>
                        ' . $liveform->output_field(array('type' => 'text', 'id' => 'amount', 'name' => 'amount', 'class' => 'form-control text-end', 'inputmode' => 'decimal', 'maxlength' => '20')) . '
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="form-label" for="doc_date">' . lang('Date') . '</label>
                        ' . $liveform->output_field(array('type' => 'text', 'id' => 'doc_date', 'name' => 'doc_date', 'class' => 'form-control', 'maxlength' => '10')) . '
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="form-label" for="due_date">' . lang('Due Date') . '</label>
                        ' . $liveform->output_field(array('type' => 'text', 'id' => 'due_date', 'name' => 'due_date', 'class' => 'form-control', 'maxlength' => '10')) . '
                        ' . get_date_picker_format() . '
                        <script>$("#doc_date, #due_date").datepicker(datetimepicker_options);</script>
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="form-label" for="serial_no">' . lang('Serial number') . '</label>
                        ' . $liveform->output_field(array('type' => 'text', 'id' => 'serial_no', 'name' => 'serial_no', 'class' => 'form-control', 'maxlength' => '40')) . '
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="form-label" for="bank_name">' . lang('Bank') . '</label>
                        ' . $liveform->output_field(array('type' => 'text', 'id' => 'bank_name', 'name' => 'bank_name', 'class' => 'form-control', 'maxlength' => '100')) . '
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="form-label" for="branch">' . lang('Branch') . '</label>
                        ' . $liveform->output_field(array('type' => 'text', 'id' => 'branch', 'name' => 'branch', 'class' => 'form-control', 'maxlength' => '100')) . '
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="drawer">' . lang('Drawn by') . '</label>
                        ' . $liveform->output_field(array('type' => 'text', 'id' => 'drawer', 'name' => 'drawer', 'class' => 'form-control', 'maxlength' => '150')) . '
                        <div class="form-text">' . lang('Whose cheque or note it is, when it is not the account\'s own.') . '</div>
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="till_id">' . lang('Kept in') . '</label>
                        ' . $liveform->output_field(array('type' => 'select', 'id' => 'till_id', 'name' => 'till_id', 'class' => 'form-select', 'options' => $tills)) . '
                        <div class="form-text">' . lang('A till the store keeps for cheques and notes: the portfolio for the ones taken, one for the ones given. Add it under Cash and Bank first.') . '</div>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="notes">' . lang('Notes') . '</label>
                        ' . $liveform->output_field(array('type' => 'text', 'id' => 'notes', 'name' => 'notes', 'class' => 'form-control', 'maxlength' => '255')) . '
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary" data-loading-content="' . lang(array('string' => 'Saving')) . '"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>' . lang(array('string' => 'Save')) . '</button>
                        <span class="form-text ms-2">' . lang('Taken from a customer, it is a collection: their debt comes down now. Given to a supplier, it is a payment.') . '</span>
                    </div>
                </div>
            </form>';

echo
pg_page_shell([
    'title' => lang('Cheques and notes'),
    'extra classes' => 'erp erp_cash',
    'icon' => 'erp',
    'heading' => lang('Cheques and notes'),
    'heading_description' => lang('Cheques and promissory notes taken from customers and given to suppliers, soonest due first, and what became of each.'),
    'cancel' => false,
]) . '
<main id="content" class="container-fluid">
    <div class="row">
        <div class="col-12">
            ' . $liveform->output_errors() . '
            ' . $liveform->get_warnings() . '
            ' . $liveform->output_notices() . '

            <div class="row g-3 my-1">
                <div class="col-12 col-md-4"><div class="card h-100"><div class="card-body"><div class="small text-body-secondary text-uppercase">' . lang('In the portfolio or at the bank') . '</div><div class="h4 mb-0">' . h(erp_money_out($totals['received'][1])) . '</div><div class="small text-body-secondary">' . h(lang(array('string' => '{var:1} cheque(s) or note(s)', 'vars' => $totals['received'][0]))) . '</div></div></div></div>
                <div class="col-12 col-md-4"><div class="card h-100"><div class="card-body"><div class="small text-body-secondary text-uppercase">' . lang('Given and not yet paid') . '</div><div class="h4 mb-0">' . h(erp_money_out($totals['given'][1])) . '</div><div class="small text-body-secondary">' . h(lang(array('string' => '{var:1} cheque(s) or note(s)', 'vars' => $totals['given'][0]))) . '</div></div></div></div>
                <div class="col-12 col-md-4"><div class="card h-100"><div class="card-body"><div class="small text-body-secondary text-uppercase">' . lang('Due within a week') . '</div><div class="h4 mb-0' . (($totals['due_week'] > 0) ? ' text-danger' : '') . '">' . (int) $totals['due_week'] . '</div></div></div></div>
            </div>

            <nav id="button_bar" class="pg-toolbar navigation d-flex flex-wrap gap-2" aria-label="' . lang('Button Bar') . '">
                <div class="btn-group" role="group">
                    ' . $link(array('status' => 'open') + (($direction !== '') ? array('direction' => $direction) : array()), lang('Open'), ($status === 'open')) . '
                    ' . $link(array('status' => 'all') + (($direction !== '') ? array('direction' => $direction) : array()), lang('All'), ($status === 'all')) . '
                </div>
                <div class="btn-group" role="group">
                    ' . $link(array('status' => $status), lang('Both ways'), ($direction === '')) . '
                    ' . $link(array('status' => $status, 'direction' => 'received'), lang('Received'), ($direction === 'received')) . '
                    ' . $link(array('status' => $status, 'direction' => 'given'), lang('Given'), ($direction === 'given')) . '
                </div>
                <form method="get" action="erp_cheques.php" class="d-flex gap-1 ms-auto" role="search">
                    <input type="hidden" name="status" value="' . h($status) . '" />
                    ' . (($direction !== '') ? '<input type="hidden" name="direction" value="' . h($direction) . '" />' : '') . '
                    <input type="search" class="form-control form-control-sm" name="search" value="' . h($search) . '" placeholder="' . h(lang('Serial, bank, drawer or account')) . '" aria-label="' . h(lang('Search')) . '" />
                    <button type="submit" class="btn btn-sm btn-outline-secondary"><i class="bi bi-search" aria-hidden="true"></i></button>
                </form>
            </nav>

            <div class="card my-4">
                <div class="card-body p-0 table-responsive">
                    <table class="table table-hover align-middle mb-0" data-pg-sort>
                        <thead>
                            <tr>
                                <th>' . lang('Due Date') . '</th>
                                <th>' . lang('Type') . '</th>
                                <th>' . lang('Account') . '</th>
                                <th>' . lang('Bank and serial') . '</th>
                                <th class="text-end">' . lang('Amount') . '</th>
                                <th>' . lang('Status') . '</th>
                            </tr>
                        </thead>
                        <tbody>' . $output_rows . '</tbody>
                    </table>
                </div>
            </div>
            ' . $output_new . '
        </div>
    </div>
</main>' .
output_footer();

$liveform->remove_form();
