<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * ERP - one expense: what it was, its receipt, and what can still be done
 * with it - pay it, attach the receipt, change it or cancel it.
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
$liveform = new liveform('edit_erp_expense');

$list_url = OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/erp_expenses.php';
$readonly = defined('USER_ERP_READONLY') && USER_ERP_READONLY;
$can_cash = defined('USER_MANAGE_ERP_CASH') && USER_MANAGE_ERP_CASH;

$id = (int) ($_REQUEST['id'] ?? 0);
$expense = erp_expense($id);

if ($expense === null) {
    $liveform_list = new liveform('erp_expenses');
    $liveform_list->mark_error('_error', lang('The expense could not be found.'));
    go($list_url);
}

$self = PATH . SOFTWARE_DIRECTORY . '/edit_erp_expense.php?id=' . $id;

// ------------------------------------------------------------------ actions
if ($_POST) {
    validate_token_field();

    $action = (string) ($_POST['erp_action'] ?? '');

    if ($action === 'pay') {
        if (!$can_cash) {
            $liveform->mark_error('_error', lang('Paying from a till needs the right to the tills.'));
            go($self);
        }

        $paid_date = trim((string) ($_POST['paid_date'] ?? ''));

        if (($paid_date !== '') && (validate_date($paid_date) == false)) {
            $liveform->mark_error('paid_date', lang('Please enter a valid date.'));
            go($self);
        }

        $result = erp_expense_pay($id, array(
            'cash_account_id' => (int) ($_POST['cash_account_id'] ?? 0),
            'payment_method' => (string) ($_POST['payment_method'] ?? 'cash'),
            'paid_date' => ($paid_date !== '') ? prepare_form_data_for_input($paid_date, 'date') : date('Y-m-d'),
        ), (int) $user['id']);

        if (!$result['success']) {
            $liveform->mark_error('_error', h($result['error']));
            go($self);
        }

        log_activity(lang('erp expense was paid'), $_SESSION['sessionusername']);
        $liveform->add_notice(lang('The expense was paid.'));
        go($self);
    }

    if ($action === 'cancel') {
        $result = erp_expense_cancel($id, (string) ($_POST['reason'] ?? ''), (int) $user['id']);

        if (!$result['success']) {
            $liveform->mark_error('reason', h($result['error']));
            go($self);
        }

        log_activity(lang('erp expense was cancelled'), $_SESSION['sessionusername']);
        $liveform->add_notice(((string) $expense['status'] === 'paid')
            ? lang('The expense was cancelled and its money put back into the till.')
            : lang('The expense was cancelled.'));
        go($self);
    }

    if (($action === 'repeat') && !$readonly) {
        $repeat_end = trim((string) ($_POST['repeat_end'] ?? ''));

        if (($repeat_end !== '') && (validate_date($repeat_end) == false)) {
            $liveform->mark_error('repeat_end', lang('Please enter a valid date.'));
            go($self);
        }

        $repeat = erp_expense_recurrence_create($id, array(
            'every_months' => (int) ($_POST['repeat_every'] ?? 1),
            'due_days' => (int) ($_POST['repeat_due_days'] ?? 0),
            'pay' => $can_cash && !empty($_POST['repeat_pay']) && ((string) $expense['status'] === 'paid'),
            'cash_account_id' => (int) $expense['cash_account_id'],
            'payment_method' => (string) $expense['payment_method'],
            'end_date' => ($repeat_end !== '') ? prepare_form_data_for_input($repeat_end, 'date') : '',
        ), (int) $user['id']);

        if (!$repeat['success']) {
            $liveform->mark_error('_error', h($repeat['error']));
            go($self);
        }

        log_activity(lang('erp expense repeat was started'), $_SESSION['sessionusername']);
        $liveform->add_notice(lang('The expense repeats from now on.'));
        go($self);
    }

    if (($action === 'stop_repeat') && !$readonly) {
        $recurrence = erp_expense_recurrence_of($expense);

        if (is_array($recurrence) && erp_expense_recurrence_stop((int) $recurrence['id'], (int) $user['id'])) {
            log_activity(lang('erp expense repeat was stopped'), $_SESSION['sessionusername']);
            $liveform->add_notice(lang('The expense no longer repeats. The expenses it wrote stay.'));
        }

        go($self);
    }

    if ((($action === 'attach') || ($action === 'replace')) && !$readonly) {
        $replace = ($action === 'replace');
        $result = erp_expense_attach($id, $_FILES['receipt_file'] ?? null, (int) $user['id'], $replace);

        if (!$result['success']) {
            $liveform->mark_error('receipt_file', h($result['error']));
            go($self);
        }

        if ($result['replaced']) {
            log_activity(lang('erp expense receipt was replaced'), $_SESSION['sessionusername']);
            $liveform->add_notice(lang('The new file stands for the receipt now. The earlier one stays with the expense.'));
        } else {
            $liveform->add_notice(lang('The receipt was kept with the expense.'));
        }

        go($self);
    }

    go($self);
}

// ------------------------------------------------------------------ display
$status = (string) $expense['status'];
$statuses = erp_expense_statuses();
$currency = strtoupper((string) $expense['currency']);
$base = erp_base_currency();
$foreign = ($currency !== $base);
$locked = erp_locked((string) $expense['expense_date'])
    || (($status === 'paid') && ((string) $expense['paid_date'] > '0000-00-00') && erp_locked((string) $expense['paid_date']));
$open = ($status !== 'cancelled');
$can_change = !$readonly && $open && !$locked;
// An expense of a closed period can still be paid in an open one: the lock
// is on the payment's date, which erp_expense_pay() checks.
$can_pay = !$readonly && $can_cash && ($status === 'unpaid');
$kept = erp_archive_file('expense', $id);

$money = function ($kurus) use ($currency) {
    return h(erp_money_out_currency((int) $kurus, $currency));
};
$date = function ($value) {
    return ((string) $value > '0000-00-00') ? h(prepare_form_data_for_output((string) $value, 'date', false)) : '<span class="text-body-secondary">—</span>';
};

$method_words = array(
    'cash' => lang('Cash'),
    'transfer' => lang('Bank transfer'),
    'card' => lang('Credit card'),
    'cheque' => lang('Cheque'),
    'other' => lang('Other'),
);

if ($status === 'paid') {
    $output_badge = '<span class="badge text-bg-success">' . h($statuses['paid']) . '</span>';
} elseif ($status === 'cancelled') {
    $output_badge = '<span class="badge text-bg-secondary">' . h($statuses['cancelled']) . '</span>';
} else {
    $overdue = ((string) $expense['due_date'] > '0000-00-00') && ((string) $expense['due_date'] < date('Y-m-d'));
    $output_badge = '<span class="badge ' . ($overdue ? 'text-bg-danger' : 'text-bg-warning') . '">' . h($statuses['unpaid']) . '</span>';
}

$output_details = '
    <dl class="row mb-0">
        <dt class="col-sm-4 text-body-secondary fw-normal">' . lang('Date') . '</dt><dd class="col-sm-8">' . $date($expense['expense_date']) . '</dd>
        <dt class="col-sm-4 text-body-secondary fw-normal">' . lang('Category') . '</dt><dd class="col-sm-8">' . h((string) $expense['category_name'])
            . (((string) $expense['category_code'] !== '') ? ' <span class="text-body-secondary font-monospace small">' . h((string) $expense['category_code']) . '</span>' : '') . '</dd>
        <dt class="col-sm-4 text-body-secondary fw-normal">' . lang('Supplier') . '</dt><dd class="col-sm-8">' . (((string) $expense['supplier'] !== '') ? h((string) $expense['supplier']) : '<span class="text-body-secondary">—</span>')
            . (((string) $expense['supplier_tax_number'] !== '') ? '<div class="small text-body-secondary">' . h(erp_tax_id_label()) . ': <span class="font-monospace">' . h((string) $expense['supplier_tax_number']) . '</span></div>' : '') . '</dd>
        <dt class="col-sm-4 text-body-secondary fw-normal">' . lang('Receipt number') . '</dt><dd class="col-sm-8 font-monospace">' . (((string) $expense['document_no'] !== '') ? h((string) $expense['document_no']) : '<span class="text-body-secondary font-sans-serif">—</span>') . '</dd>
        <dt class="col-sm-4 text-body-secondary fw-normal">' . lang('Description') . '</dt><dd class="col-sm-8">' . (((string) $expense['description'] !== '') ? h((string) $expense['description']) : '<span class="text-body-secondary">—</span>') . '</dd>
    </dl>';

$output_figures = '
    <table class="table table-sm mb-0">
        <tbody>
            <tr><td class="text-body-secondary">' . lang('Net') . '</td><td class="text-end">' . $money($expense['net_amount']) . '</td></tr>
            <tr><td class="text-body-secondary">' . h(erp_tax_label('tax')) . ' ' . h(erp_percent_text((float) $expense['tax_rate'])) . '</td><td class="text-end">' . $money($expense['tax_amount'])
                . '<div class="small text-body-secondary">' . (((int) $expense['tax_deductible'] === 1) ? lang('taken back') : lang('not taken back: counts as cost')) . '</div></td></tr>
            <tr class="fw-bold"><td>' . lang('Total') . '</td><td class="text-end">' . $money($expense['total_amount']) . '</td></tr>
            ' . ($foreign ? '<tr><td class="text-body-secondary">' . h(lang(array('string' => 'In {var:1}', 'vars' => $base))) . '</td><td class="text-end">' . h(erp_money_out((int) $expense['total_base']))
                . '<div class="small text-body-secondary">' . h(lang(array('string' => 'at {var:1}', 'vars' => erp_fx_rate_text((float) $expense['exchange_rate'])))) . '</div></td></tr>' : '') . '
        </tbody>
    </table>';

// The payment: how it went, or the form to make it.
if ($status === 'paid') {
    $output_payment = '
        <dl class="row mb-0">
            <dt class="col-sm-4 text-body-secondary fw-normal">' . lang('Paid on') . '</dt><dd class="col-sm-8">' . $date($expense['paid_date']) . '</dd>
            <dt class="col-sm-4 text-body-secondary fw-normal">' . lang('Paid from') . '</dt><dd class="col-sm-8">'
                . ($can_cash ? '<a href="edit_erp_till.php?id=' . (int) $expense['cash_account_id'] . '">' . h((string) $expense['till_name']) . '</a>' : h((string) $expense['till_name'])) . '</dd>
            <dt class="col-sm-4 text-body-secondary fw-normal">' . lang('Payment Method') . '</dt><dd class="col-sm-8">' . h($method_words[(string) $expense['payment_method']] ?? (string) $expense['payment_method']) . '</dd>
        </dl>';
} elseif ($can_pay) {
    // Only the tills kept in the expense's currency can pay it.
    $till_options = '<option value="">' . lang('Choose a till or bank account') . '</option>';
    $till_count = 0;
    foreach ((array) db_items("SELECT id, name, currency FROM erp_cash_accounts WHERE is_active = 1 ORDER BY sort_order ASC, name ASC") as $till) {
        $till_currency = strtoupper(trim((string) $till['currency']));

        if (erp_fx_enabled() && ($till_currency !== '') && ($till_currency !== $currency)) {
            continue;
        }

        $till_count++;
        $till_options .= '<option value="' . (int) $till['id'] . '">' . h($till['name'] . (erp_fx_enabled() ? (' (' . $till_currency . ')') : '')) . '</option>';
    }

    $method_options = '';
    foreach ($method_words as $key => $label) {
        $method_options .= '<option value="' . h($key) . '">' . h($label) . '</option>';
    }

    $output_payment = '
        <p class="mb-2">' . (((string) $expense['due_date'] > '0000-00-00') ? h(lang(array('string' => 'To pay by {var:1}.', 'vars' => prepare_form_data_for_output((string) $expense['due_date'], 'date', false)))) : lang('Not paid yet.')) . '</p>
        ' . (($till_count === 0) ? '<div class="alert alert-warning small py-2">' . h(lang(array('string' => 'No active till or bank account is kept in {var:1}, so this expense cannot be paid from one yet.', 'vars' => $currency))) . '</div>' : '') . '
        <form method="post" action="edit_erp_expense.php" class="row g-2 align-items-end">
            ' . get_token_field() . '
            <input type="hidden" name="id" value="' . $id . '" />
            <input type="hidden" name="erp_action" value="pay" />
            <div class="col-12 col-md-5">
                <label class="form-label small mb-1" for="cash_account_id">' . lang('Paid from') . '</label>
                <select class="form-select form-select-sm" id="cash_account_id" name="cash_account_id" required>' . $till_options . '</select>
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label small mb-1" for="payment_method">' . lang('Payment Method') . '</label>
                <select class="form-select form-select-sm" id="payment_method" name="payment_method">' . $method_options . '</select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small mb-1" for="paid_date">' . lang('Paid on') . '</label>
                <input type="text" class="form-control form-control-sm" id="paid_date" name="paid_date" value="' . h(prepare_form_data_for_output(date('Y-m-d'), 'date')) . '" maxlength="10" autocomplete="off" />
            </div>
            <div class="col-12 col-md-2">
                <button type="submit" class="btn btn-sm btn-success w-100" data-loading-content="' . lang(array('string' => 'Saving')) . '"><i class="bi bi-cash-coin me-1" aria-hidden="true"></i>' . lang('Pay') . '</button>
            </div>
        </form>';
} else {
    $output_payment = '<p class="mb-0 text-body-secondary">' . (($status === 'cancelled') ? lang('Cancelled; nothing to pay.')
        : ((((string) $expense['due_date'] > '0000-00-00') ? h(lang(array('string' => 'To pay by {var:1}.', 'vars' => prepare_form_data_for_output((string) $expense['due_date'], 'date', false)))) . ' ' : '')
            . (!$can_cash ? lang('Paying from a till needs the right to the tills.') : ''))) . '</p>';
}

// The receipt: shown when kept, the way to keep it otherwise.
if ($kept !== null) {
    $file_url = OUTPUT_PATH . (string) $kept['name'];
    $is_pdf = (strtolower((string) $kept['type']) === 'pdf');
    $output_receipt = $is_pdf
        ? '<a class="btn btn-sm btn-outline-primary" href="' . h($file_url) . '" target="_blank" rel="noopener"><i class="bi bi-file-earmark-pdf me-1" aria-hidden="true"></i>' . lang('Open the PDF') . '</a>'
        : '<a href="' . h($file_url) . '" target="_blank" rel="noopener"><img src="' . h($file_url) . '" alt="' . h(lang('The receipt')) . '" class="img-fluid rounded border" style="max-height:28rem" /></a>';

    // The files it replaced stay with the expense; listed, not shown.
    $earlier = array();
    foreach (erp_expense_receipts($id) as $file) {
        if ((int) $file['id'] !== (int) $kept['id']) {
            $earlier[] = $file;
        }
    }

    $output_receipt .= '<div class="small text-body-secondary mt-2">' . h(lang(array(
        'string' => 'Kept on {var:1}',
        'vars' => prepare_form_data_for_output(date('Y-m-d H:i:s', (int) $kept['timestamp']), 'date and time'),
    ))) . '</div>';

    if (!empty($earlier)) {
        $earlier_rows = '';
        foreach ($earlier as $file) {
            $when = h(prepare_form_data_for_output(date('Y-m-d H:i:s', (int) $file['timestamp']), 'date and time'));
            $who = ((string) ($file['username'] ?? '') !== '') ? ' · ' . h((string) $file['username']) : '';
            $earlier_rows .= '<li>' . ($file['on_disk']
                ? '<a href="' . h(OUTPUT_PATH . (string) $file['name']) . '" target="_blank" rel="noopener">' . $when . '</a>'
                : $when . ' <span class="text-body-secondary">(' . lang('not on the disk') . ')</span>')
                . ' <span class="text-body-secondary">' . h(strtoupper((string) $file['type'])) . $who . '</span></li>';
        }

        $output_receipt .= '
            <details class="mt-2 small">
                <summary class="text-body-secondary">' . h(lang(array('string' => 'Earlier files ({var:1})', 'vars' => count($earlier)))) . '</summary>
                <ul class="mb-0 mt-1 ps-3">' . $earlier_rows . '</ul>
            </details>';
    }

    if (!$readonly) {
        $replace_refusal = erp_expense_receipt_refusal($expense);

        if ($replace_refusal === '') {
            $output_receipt .= '
                <details class="mt-3"' . ($liveform->check_field_error('receipt_file') ? ' open' : '') . '>
                    <summary class="small">' . lang('Replace the file') . '</summary>
                    <form method="post" action="edit_erp_expense.php" enctype="multipart/form-data" class="mt-2">
                        ' . get_token_field() . '
                        <input type="hidden" name="id" value="' . $id . '" />
                        <input type="hidden" name="erp_action" value="replace" />
                        <div class="input-group input-group-sm">
                            <input class="form-control" type="file" id="receipt_file" name="receipt_file" accept="image/jpeg,image/png,image/webp,application/pdf" required />
                            <button type="submit" class="btn btn-outline-primary" data-confirm-content="' . h(lang('Take the new file in place of this one? The earlier file stays with the expense.')) . '" data-loading-content="' . lang(array('string' => 'Saving')) . '"><i class="bi bi-arrow-left-right me-1" aria-hidden="true"></i>' . lang('Replace') . '</button>
                        </div>
                        <div class="form-text">' . lang('For a blurred picture or the wrong page. The earlier file is not deleted; it stays listed with the expense.') . '</div>
                    </form>
                </details>';
        } elseif ($open) {
            $output_receipt .= '<div class="small text-body-secondary mt-2"><i class="bi bi-lock me-1" aria-hidden="true"></i>' . h($replace_refusal) . '</div>';
        }
    }
} elseif (!$readonly && $open) {
    $output_receipt = '
        <form method="post" action="edit_erp_expense.php" enctype="multipart/form-data">
            ' . get_token_field() . '
            <input type="hidden" name="id" value="' . $id . '" />
            <input type="hidden" name="erp_action" value="attach" />
            <label for="receipt_file" class="form-label">' . lang('A picture or a PDF of the receipt') . '</label>
            <div class="input-group input-group-sm">
                <input class="form-control" type="file" id="receipt_file" name="receipt_file" accept="image/jpeg,image/png,image/webp,application/pdf" required />
                <button type="submit" class="btn btn-outline-primary" data-loading-content="' . lang(array('string' => 'Saving')) . '"><i class="bi bi-upload me-1" aria-hidden="true"></i>' . lang('Keep') . '</button>
            </div>
            <div class="form-text">' . h(lang(array('string' => 'At most {var:1} MB. On a phone the camera can take it.', 'vars' => (int) (ERP_EXPENSE_FILE_MAX_BYTES / 1048576)))) . '</div>
        </form>';
} else {
    $output_receipt = '<p class="text-body-secondary mb-0">' . lang('No receipt was kept with this expense.') . '</p>';
}

// Repeating: what this expense repeats as, or the way to make it repeat.
$output_repeat = '';

if (erp_expense_recurring_ready()) {
    $recurrence = erp_expense_recurrence_of($expense);
    $periods = erp_expense_recurring_periods();

    if (is_array($recurrence)) {
        $active = ((string) $recurrence['status'] === 'active');
        $can_restart = !$active && !$readonly && $open;
        $is_source = ((int) $recurrence['source_expense_id'] === $id);

        $output_repeat = '
            <p class="mb-2">' . h($periods[(int) $recurrence['every_months']] ?? '') . ' · '
                . ($active
                    ? h(lang(array('string' => 'next on {var:1}', 'vars' => prepare_form_data_for_output((string) $recurrence['next_date'], 'date', false))))
                    : '<span class="badge text-bg-secondary">' . lang('Stopped') . '</span>')
                . ' · ' . h(lang(array('string' => 'written {var:1} time(s)', 'vars' => (int) $recurrence['occurrences']))) . '</p>
            <p class="small text-body-secondary mb-2">' . (((string) $recurrence['pay_mode'] === 'paid')
                ? h(lang(array('string' => 'Written as paid from {var:1}.', 'vars' => (string) $recurrence['till_name'])))
                : ((((int) $recurrence['due_days'] > 0) ? h(lang(array('string' => 'Written to pay, due {var:1} days later.', 'vars' => (int) $recurrence['due_days']))) : lang('Written to pay.'))))
                . ((((string) $recurrence['end_date'] !== '0000-00-00')) ? ' ' . h(lang(array('string' => 'Until {var:1}.', 'vars' => prepare_form_data_for_output((string) $recurrence['end_date'], 'date', false)))) : '') . '</p>
            ' . (((string) $recurrence['last_error'] !== '') ? '<div class="alert alert-warning small py-2">' . h((string) $recurrence['last_error']) . '</div>' : '') . '
            ' . (!$is_source ? '<p class="small mb-2"><a href="edit_erp_expense.php?id=' . (int) $recurrence['source_expense_id'] . '">' . lang('The expense it started from') . '</a></p>' : '') . '
            ' . (($active && !$readonly) ? '<form method="post" action="edit_erp_expense.php">
                ' . get_token_field() . '
                <input type="hidden" name="id" value="' . $id . '" />
                <input type="hidden" name="erp_action" value="stop_repeat" />
                <button type="submit" class="btn btn-sm btn-outline-secondary" data-confirm-content="' . h(lang('Stop repeating this expense?')) . '"><i class="bi bi-stop-circle me-1" aria-hidden="true"></i>' . lang('Stop repeating') . '</button>
            </form>' : '');
    }

    if (!is_array($recurrence) || !empty($can_restart)) {
        $period_options = '';
        foreach ($periods as $months => $label) {
            $period_options .= '<option value="' . (int) $months . '">' . h($label) . '</option>';
        }

        $output_repeat .= (($output_repeat !== '') ? '<hr />' : '') . '
            <form method="post" action="edit_erp_expense.php" class="row g-2 align-items-end">
                ' . get_token_field() . '
                <input type="hidden" name="id" value="' . $id . '" />
                <input type="hidden" name="erp_action" value="repeat" />
                <div class="col-12 col-md-4">
                    <label class="form-label small mb-1" for="repeat_every">' . lang('How often') . '</label>
                    <select class="form-select form-select-sm" id="repeat_every" name="repeat_every">' . $period_options . '</select>
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label small mb-1" for="repeat_due_days">' . lang('Due after (days)') . '</label>
                    <input type="number" class="form-control form-control-sm" id="repeat_due_days" name="repeat_due_days" min="0" max="365" placeholder="0" />
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label small mb-1" for="repeat_end">' . lang('Until (optional)') . '</label>
                    <input type="text" class="form-control form-control-sm" id="repeat_end" name="repeat_end" maxlength="10" autocomplete="off" />
                </div>
                <div class="col-12 col-md-2">
                    <button type="submit" class="btn btn-sm btn-outline-primary w-100"><i class="bi bi-arrow-repeat me-1" aria-hidden="true"></i>' . lang('Make it repeat') . '</button>
                </div>
                ' . (($can_cash && ($status === 'paid')) ? '<div class="col-12">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="repeat_pay" name="repeat_pay" value="1" />
                        <label class="form-check-label small" for="repeat_pay">' . h(lang(array('string' => 'Write the next ones as paid from {var:1}', 'vars' => (string) $expense['till_name']))) . '</label>
                    </div>
                </div>' : '') . '
                <div class="col-12 form-text mt-0">' . lang('The next one is written on the same day of the month, with this amount and tax.') . '</div>
            </form>';
    }
}

$output_cancel = '';

if ($can_change) {
    $output_cancel = '
        <div class="card my-4 border-danger-subtle">
            <div class="card-header bg-reset border-0 text-uppercase h6 text-danger fw-bold">' . lang('Cancel the expense') . '</div>
            <div class="card-body">
                <p class="small text-body-secondary">' . (($status === 'paid')
                    ? lang('The expense stays on the list, struck through; its money goes back into the till with a movement dated today.')
                    : lang('The expense stays on the list, struck through, and counts for nothing.')) . '</p>
                <form method="post" action="edit_erp_expense.php" class="d-flex flex-wrap gap-2">
                    ' . get_token_field() . '
                    <input type="hidden" name="id" value="' . $id . '" />
                    <input type="hidden" name="erp_action" value="cancel" />
                    <input type="text" class="form-control form-control-sm flex-grow-1" style="min-width:14rem" name="reason" maxlength="255" placeholder="' . h(lang('Why')) . '" aria-label="' . h(lang('Reason')) . '" required />
                    <button type="submit" class="btn btn-sm btn-outline-danger" data-confirm-content="' . h(lang('Cancel this expense?')) . '"><i class="bi bi-x-circle me-1" aria-hidden="true"></i>' . lang('Cancel the expense') . '</button>
                </form>
            </div>
        </div>';
}

$output_cancelled = '';

if ($status === 'cancelled') {
    $output_cancelled = '
        <div class="alert alert-secondary my-4">' . h(lang(array(
            'string' => 'Cancelled on {var:1}: {var:2}',
            'vars' => array(prepare_form_data_for_output(date('Y-m-d', (int) $expense['cancelled_at']), 'date', false), (string) $expense['cancel_reason']),
        ))) . '</div>';
}

$heading = ((string) $expense['supplier'] !== '') ? (string) $expense['supplier'] : lang(array('string' => 'Expense #{var:1}', 'vars' => $id));

echo
pg_page_shell([
    'title' => lang(array('string' => 'Expense #{var:1}', 'vars' => $id)),
    'extra classes' => 'erp erp_expenses',
    'icon' => 'erp',
    'heading' => h($heading),
    'heading_description' => h((string) $expense['category_name']) . ' · ' . h(prepare_form_data_for_output((string) $expense['expense_date'], 'date', false)),
    'cancel' => array('enable' => 'true', 'url' => 'erp_expenses.php'),
    'breadcrumb' => array(
        array('label' => lang('Expenses'), 'url' => $list_url),
        array('label' => lang(array('string' => 'Expense #{var:1}', 'vars' => $id))),
    ),
]) . '
<main id="content" class="container-fluid">
    <div class="row">
        <div class="col-12">
            ' . $liveform->output_errors() . '
            ' . $liveform->get_warnings() . '
            ' . $liveform->output_notices() . '

            <nav id="button_bar" class="pg-toolbar navigation" aria-label="' . lang('Button Bar') . '">
                ' . ($can_change ? '<a class="btn btn-sm btn-primary rounded-pill px-3" href="add_erp_expense.php?id=' . $id . '" data-loading-content="' . lang(array('string' => 'Loading')) . '"><i class="bi bi-pencil me-1" aria-hidden="true"></i>' . lang('Change') . '</a>' : '') . '
                ' . (!$readonly ? '<a class="btn btn-sm btn-outline-secondary" href="add_erp_expense.php" data-loading-content="' . lang(array('string' => 'Loading')) . '"><i class="bi bi-plus-circle me-1" aria-hidden="true"></i>' . lang('New expense') . '</a>' : '') . '
                <span class="ms-2">' . $output_badge . '</span>
            </nav>

            ' . (($locked && $open) ? '<div class="alert alert-warning my-3"><i class="bi bi-lock me-1" aria-hidden="true"></i>' . h(($status === 'unpaid')
                ? lang(array('string' => 'The books are locked up to {var:1}: this expense can no longer be changed or cancelled. It can still be paid, with a date after the lock.', 'vars' => prepare_form_data_for_output(erp_lock_date(), 'date', false)))
                : (erp_lock_refusal((string) $expense['expense_date']) ?: erp_lock_refusal((string) $expense['paid_date']))) . '</div>' : '') . '
            ' . $output_cancelled . '

            <div class="row g-4 my-1">
                <div class="col-12 col-xl-7">
                    <div class="card h-100">
                        <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">' . lang('The expense') . '</div>
                        <div class="card-body">' . $output_details . '</div>
                    </div>
                </div>
                <div class="col-12 col-xl-5">
                    <div class="card h-100">
                        <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">' . lang('Amount and tax') . '</div>
                        <div class="card-body">' . $output_figures . '</div>
                    </div>
                </div>
                <div class="col-12 col-xl-7">
                    <div class="card h-100">
                        <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">' . lang('Payment') . '</div>
                        <div class="card-body">' . $output_payment . '</div>
                    </div>
                </div>
                <div class="col-12 col-xl-5">
                    <div class="card h-100">
                        <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">' . lang('The receipt') . '</div>
                        <div class="card-body">' . $output_receipt . '</div>
                    </div>
                </div>
            </div>

            ' . (($output_repeat !== '') ? '<div class="card my-4">
                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold"><i class="bi bi-arrow-repeat me-1" aria-hidden="true"></i>' . lang('Repeat') . '</div>
                <div class="card-body">' . $output_repeat . '</div>
            </div>' : '') . '

            ' . $output_cancel . '

            <p class="small text-body-secondary">' . h(lang(array(
                'string' => 'Recorded by {var:1} on {var:2}.',
                'vars' => array(((string) $expense['created_by_name'] !== '') ? (string) $expense['created_by_name'] : '-', prepare_form_data_for_output(date('Y-m-d H:i:s', (int) $expense['created_at']), 'date and time')),
            ))) . '</p>
        </div>
    </div>
</main>
' . get_date_picker_format() . '
<script>
if (document.getElementById("paid_date")) { $("#paid_date").datepicker(datetimepicker_options); }
if (document.getElementById("repeat_end")) { $("#repeat_end").datepicker(datetimepicker_options); }
</script>
' .
output_footer();

$liveform->remove_form();
