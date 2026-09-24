<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * ERP - one cheque or promissory note: what it is, the movements it made, and
 * its next step - to the bank, collected, passed on, bounced, or paid. The
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
$liveform = new liveform('erp_cheque');

$cheque_id = (int) ($_REQUEST['id'] ?? 0);
$cheque = erp_cheque($cheque_id);

if ($cheque === null) {
    output_error(lang('The cheque or note could not be found.') . ' <a href="erp_cheques.php">' . lang('Cheques and notes') . '</a>');
    exit();
}

$self = PATH . SOFTWARE_DIRECTORY . '/erp_cheque.php?id=' . $cheque_id;
$readonly = defined('USER_ERP_READONLY') && USER_ERP_READONLY;

if ($_POST) {
    validate_token_field();

    $step = (string) ($_POST['step'] ?? '');
    $typed = trim((string) ($_POST['date'] ?? ''));
    $date = preg_match('/^\d{4}-\d{2}-\d{2}$/', $typed) ? $typed : ((($typed !== '') && validate_date($typed)) ? prepare_form_data_for_input($typed, 'date') : date('Y-m-d'));

    $done = erp_cheque_step($cheque_id, $step, array(
        'date' => $date,
        'bank_till_id' => (int) ($_POST['bank_till_id'] ?? 0),
        'account_id' => (int) ($_POST['account_id'] ?? 0),
        'reason' => (string) ($_POST['reason'] ?? ''),
    ), (int) $user['id']);

    if (!$done['success']) {
        $liveform->mark_error('_error', h($done['error']));
        go($self);
    }

    $step_labels = array('deposit' => lang('At the bank'), 'collect' => lang('Collected'), 'endorse' => lang('Passed on'), 'bounce' => lang('Bounced'), 'pay' => lang('Paid'));
    log_activity(lang(array('string' => 'erp cheque or note (#{var:1}) was marked {var:2}', 'vars' => array($cheque_id, $step_labels[$step] ?? $step))), $_SESSION['sessionusername']);
    $liveform->add_notice(h(lang('Done. The movement is in the till and on the account.')));
    go($self);
}

$statuses = erp_cheque_statuses();
$kinds = erp_cheque_kinds();
$status = (string) $cheque['status'];
$state = $statuses[$status] ?? array($status, 'secondary');
$received = ((string) $cheque['direction'] === 'received');
$name = trim(($kinds[(string) $cheque['kind']] ?? '') . ' ' . (string) $cheque['serial_no']);

$tills = '';
foreach ((array) db_items("SELECT id, name, currency, kind FROM erp_cash_accounts WHERE is_active = 1 AND id <> '" . (int) $cheque['portfolio_till_id'] . "' ORDER BY (kind = 'bank') DESC, sort_order ASC, id ASC") as $till) {
    $tills .= '<option value="' . (int) $till['id'] . '"' . (((int) $till['id'] === (int) $cheque['bank_till_id']) ? ' selected' : '') . '>' . h($till['name'] . ' (' . $till['currency'] . ')') . '</option>';
}

$accounts = '';
foreach (erp_accounts(array('status' => 'active')) as $account) {
    if ((int) $account['id'] !== (int) $cheque['account_id']) {
        $accounts .= '<option value="' . (int) $account['id'] . '">' . h($account['title']) . '</option>';
    }
}

$step_form = function ($step, $title, $button, $class, $fields, $note) use ($cheque_id) {
    return '
        <form method="post" action="erp_cheque.php" class="card my-3">
            ' . get_token_field() . '
            <input type="hidden" name="id" value="' . $cheque_id . '" />
            <input type="hidden" name="step" value="' . $step . '" />
            <div class="card-body row g-2 align-items-end">
                <div class="col-12 fw-semibold">' . $title . '</div>
                ' . $fields . '
                <div class="col-12 col-md-3"><button type="submit" class="btn btn-sm ' . $class . ' w-100" data-loading-content="' . lang(array('string' => 'Please Wait')) . '">' . $button . '</button></div>
                ' . (($note !== '') ? '<div class="col-12 form-text">' . $note . '</div>' : '') . '
            </div>
        </form>';
};

$date_field = '<div class="col-6 col-md-3"><label class="form-label small">' . lang('Date') . '</label><input type="date" class="form-control form-control-sm" name="date" value="' . date('Y-m-d') . '" /></div>';
$bank_field = '<div class="col-6 col-md-4"><label class="form-label small">' . lang('Bank account') . '</label><select class="form-select form-select-sm" name="bank_till_id">' . $tills . '</select></div>';

$output_steps = '';
if (!$readonly) {
    if ($received && ($status === 'portfolio')) {
        $output_steps .= $step_form('deposit', lang('Handed to the bank for collection'), lang('At the bank'), 'btn-outline-info', $bank_field, lang('Only its place changes; the money moves when the bank collects it.'));
    }
    if ($received && in_array($status, array('portfolio', 'deposited'), true)) {
        $output_steps .= $step_form('collect', lang('Collected'), lang('Collected'), 'btn-success', $date_field . $bank_field, lang('The amount moves from the portfolio to the bank account.'));
        $output_steps .= $step_form('bounce', lang('Bounced'), lang('Bounced'), 'btn-outline-danger', $date_field . '<div class="col-12 col-md-6"><label class="form-label small">' . lang('Reason') . '</label><input type="text" class="form-control form-control-sm" name="reason" maxlength="150" /></div>', lang('The amount leaves the portfolio and goes back on the customer\'s account: they owe it again.'));
    }
    if ($received && ($status === 'portfolio')) {
        $output_steps .= $step_form('endorse', lang('Passed on to a supplier'), lang('Pass on'), 'btn-outline-secondary', $date_field . '<div class="col-12 col-md-6"><label class="form-label small">' . lang('Account') . '</label><select class="form-select form-select-sm" name="account_id"><option value="">' . lang('Choose an account') . '</option>' . $accounts . '</select></div>', lang('A payment to that account out of the portfolio: what the store owes it comes down.'));
    }
    if (!$received && ($status === 'given')) {
        $output_steps .= $step_form('pay', lang('Paid by the bank'), lang('Paid'), 'btn-success', $date_field . $bank_field, lang('The amount moves from the bank account to the till the given ones are kept in.'));
    }
}

$movements = '';
foreach (array('open_cash_id', 'close_cash_id') as $column) {
    if ((int) $cheque[$column] > 0) {
        $move = db_item("SELECT t.id, t.doc_date, t.doc_type, t.direction, t.amount, t.currency, t.description, c.name AS till_name
            FROM erp_cash_transactions t LEFT JOIN erp_cash_accounts c ON c.id = t.cash_account_id
            WHERE t.id = '" . (int) $cheque[$column] . "' LIMIT 1");
        if (is_array($move)) {
            $link = in_array((string) $move['doc_type'], array('collection', 'payment'), true) ? 'erp_receipt.php?id=' . (int) $move['id'] : 'erp_cash.php';
            $movements .= '<tr><td class="text-nowrap">' . h(prepare_form_data_for_output((string) $move['doc_date'], 'date')) . '</td><td><a class="link-body-emphasis" href="' . h($link) . '">' . h((string) $move['description']) . '</a></td><td>' . h((string) $move['till_name']) . '</td>'
                . '<td class="text-end text-nowrap ' . (((string) $move['direction'] === 'in') ? 'text-success' : 'text-danger') . '">' . (((string) $move['direction'] === 'in') ? '+' : '−') . h(erp_money_out_currency((int) $move['amount'], (string) $move['currency'])) . '</td></tr>';
        }
    }
}

$fact = function ($label, $value) {
    return ((string) $value !== '') ? '<div class="col-6 col-md-3"><div class="small text-body-secondary">' . $label . '</div><div>' . h((string) $value) . '</div></div>' : '';
};

echo
pg_page_shell([
    'title' => ($name !== '') ? $name : lang('Cheques and notes'),
    'extra classes' => 'erp erp_cash',
    'icon' => 'erp',
    'heading' => h($name) . ' <span class="badge text-bg-' . h($state[1]) . ' ms-1 align-middle fs-6">' . h($state[0]) . '</span>',
    'heading_description' => (string) $cheque['account_title'] . ' · ' . erp_money_out_currency((int) $cheque['amount'], (string) $cheque['currency']) . ' · ' . lang(array('string' => 'due {var:1}', 'vars' => prepare_form_data_for_output((string) $cheque['due_date'], 'date', false))),
    'cancel' => array('enable' => 'true', 'url' => 'erp_cheques.php'),
    'breadcrumb' => array(
        array('label' => lang('Cheques and notes'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/erp_cheques.php'),
        array('label' => ($name !== '') ? $name : ('#' . $cheque_id)),
    ),
]) . '
<main id="content" class="container-fluid">
    <div class="row">
        <div class="col-12 col-xxl-9">
            ' . $liveform->output_errors() . '
            ' . $liveform->get_warnings() . '
            ' . $liveform->output_notices() . '

            <div class="card my-4">
                <div class="card-body row g-3">
                    ' . $fact(lang('Direction'), $received ? lang('Taken from a customer') : lang('Given to a supplier')) . '
                    ' . $fact(lang('Account'), (string) $cheque['account_title']) . '
                    ' . $fact(lang('Amount'), erp_money_out_currency((int) $cheque['amount'], (string) $cheque['currency'])) . '
                    ' . $fact(lang('Due Date'), prepare_form_data_for_output((string) $cheque['due_date'], 'date', false)) . '
                    ' . $fact(lang('Date'), prepare_form_data_for_output((string) $cheque['doc_date'], 'date', false)) . '
                    ' . $fact(lang('Bank'), trim((string) $cheque['bank_name'] . ' ' . (string) $cheque['branch'])) . '
                    ' . $fact(lang('Drawn by'), (string) $cheque['drawer']) . '
                    ' . $fact(lang('Kept in'), (string) $cheque['portfolio_name']) . '
                    ' . $fact(lang('Bank account'), (string) $cheque['bank_till_name']) . '
                    ' . $fact(lang('Passed on to'), (string) $cheque['endorsed_title']) . '
                    ' . $fact(lang('Notes'), (string) $cheque['notes']) . '
                </div>
            </div>

            ' . (($movements !== '') ? '<div class="card my-4">
                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">' . lang('Movements') . '</div>
                <div class="card-body p-0 table-responsive"><table class="table table-sm align-middle mb-0"><tbody>' . $movements . '</tbody></table></div>
            </div>' : '') . '

            ' . $output_steps . '
        </div>
    </div>
</main>' .
output_footer();

$liveform->remove_form();
