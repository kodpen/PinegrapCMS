<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * ERP - one receipt or payment: what it closed, what is left of it, and the
 * way to take it back.
 *
 * A receipt is two movements, one on the till and one on the account, and
 * the allocations that say which invoices it paid. This screen shows all of
 * that in one place and offers the three corrections a wrong entry needs:
 * remove an allocation, allocate what is still unallocated, and cancel the
 * receipt outright. Cancelling reverses, it never deletes; the reversal is
 * shown here afterwards, and a cancelled receipt takes no further action.
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
$liveform = new liveform('erp_receipt');

$list_url = OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/erp_cash.php';

$cash_id = (int) ($_REQUEST['id'] ?? 0);
$receipt = ($cash_id > 0) ? erp_receipt($cash_id) : null;

if ($receipt === null) {
    output_error(lang('The receipt could not be found.') . ' <a href="' . h($list_url) . '">' . lang('Cash and Bank') . '</a>');
    exit();
}

$self_url = PATH . SOFTWARE_DIRECTORY . '/erp_receipt.php?id=' . $cash_id;
$ledger_id = (int) $receipt['ledger_id'];
$is_collection = ((string) $receipt['doc_type'] === 'collection');
$is_cancelled = ((int) $receipt['reversal_id'] > 0);

if ($_POST) {

    validate_token_field();

    $action = (string) ($_POST['erp_action'] ?? '');

    if ($action === 'cancel') {
        $result = erp_receipt_cancel($cash_id, (string) ($_POST['reason'] ?? ''), (int) $user['id']);

        if ($result['success']) {
            log_activity(lang(array('string' => 'erp receipt (#{var:1}) was cancelled', 'vars' => $cash_id)), $_SESSION['sessionusername']);
            $liveform->add_notice(lang(array('string' => 'The receipt has been cancelled and {var:1} invoice(s) reopened.', 'vars' => count($result['reopened']))));
        } else {
            $liveform->mark_error('_error', $result['error']);
        }

        go($self_url);
    }

    if ($action === 'unsettle') {
        $result = erp_settlement_remove((int) ($_POST['settlement_id'] ?? 0), (int) $user['id']);

        if ($result['success']) {
            log_activity(lang(array('string' => 'erp allocation was removed from receipt (#{var:1})', 'vars' => $cash_id)), $_SESSION['sessionusername']);
            $liveform->add_notice(lang('The allocation has been removed.'));
        } else {
            $liveform->mark_error('_error', $result['error']);
        }

        go($self_url);
    }

    if ($action === 'allocate') {
        $amounts = isset($_POST['allocate']) && is_array($_POST['allocate']) ? $_POST['allocate'] : array();
        $done = 0;
        $errors = array();

        // One invoice at a time, each in its own transaction, so a refused
        // line does not undo the accepted ones and the message names the line.
        // The figure typed is what to allocate on top of what this receipt
        // already holds on that invoice: the pair is unique, so the total is
        // what gets written.
        foreach ($amounts as $invoice_id => $text) {
            $amount = erp_kurus((string) $text);

            if ($amount <= 0) {
                continue;
            }

            $held = (int) db_value("SELECT COALESCE(SUM(amount), 0) FROM erp_settlements
                WHERE account_txn_id = '" . $ledger_id . "' AND invoice_id = '" . (int) $invoice_id . "'");

            $result = erp_settlement_allocate(array(
                'invoice_id' => (int) $invoice_id,
                'account_txn_id' => $ledger_id,
                'amount' => $amount + $held,
                'created_by' => (int) $user['id'],
            ));

            if ($result['success']) {
                $done++;
            } else {
                $number = (string) db_value("SELECT full_number FROM erp_invoices WHERE id = '" . (int) $invoice_id . "' LIMIT 1");
                $errors[] = (($number !== '') ? ($number . ': ') : '') . $result['error'];
            }
        }

        if ($done > 0) {
            log_activity(lang(array('string' => 'erp receipt (#{var:1}) was allocated to {var:2} invoice(s)', 'vars' => array($cash_id, $done))), $_SESSION['sessionusername']);
            $liveform->add_notice(lang(array('string' => '{var:1} allocation(s) saved.', 'vars' => $done)));
        } elseif (empty($errors)) {
            $liveform->mark_error('_error', lang('Nothing was allocated.'));
        }

        foreach ($errors as $error) {
            $liveform->mark_error('_error', $error);
        }

        go($self_url);
    }

    go($self_url);
}

// ------------------------------------------------------------------ render

$currency = strtoupper(trim((string) $receipt['currency']));
$is_foreign = ($currency !== erp_base_currency());

$money = function ($kurus, $show_sign = true) use ($currency) {
    return h(erp_money_out_currency((int) $kurus, $currency, $show_sign));
};

$settlements = erp_txn_settlements($ledger_id);
$unallocated = erp_txn_unallocated($ledger_id);
$reversal = $is_cancelled ? db_item("SELECT * FROM erp_cash_transactions WHERE id = '" . (int) $receipt['reversal_id'] . "' LIMIT 1") : null;

$method_labels = array(
    'cash' => lang('Cash'),
    'transfer' => lang('Bank transfer'),
    'card' => lang('Card'),
    'other' => lang('Other'),
);

$title = $is_collection ? lang('Receipt') : lang('Payment');

// The receipt itself.
$output_header = '
            <div class="card my-4">
                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <span>' . h($title) . ' #' . $cash_id . '</span>
                    ' . ($is_cancelled
                        ? '<span class="badge text-bg-secondary"><i class="bi bi-x-circle me-1"></i>' . lang('Cancelled') . '</span>'
                        : '<span class="badge ' . ($is_collection ? 'text-bg-success' : 'text-bg-danger') . '">' . ($is_collection ? lang('Money in') : lang('Money out')) . '</span>') . '
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-12 col-sm-6 col-lg-3 my-2">
                            <div class="form-label text-body-secondary">' . lang('Date') . '</div>
                            <div>' . h(prepare_form_data_for_output($receipt['doc_date'], 'date')) . '</div>
                        </div>
                        <div class="col-12 col-sm-6 col-lg-3 my-2">
                            <div class="form-label text-body-secondary">' . lang('Amount') . '</div>
                            <div class="h5 mb-0 ' . ($is_cancelled ? 'text-decoration-line-through text-body-secondary' : '') . '">' . $money((int) $receipt['amount']) . '</div>
                            ' . ($is_foreign ? '<div class="text-body-secondary small">' . h(erp_money_out((int) $receipt['amount_base'])) . ' &middot; ' . h(erp_fx_rate_out($receipt['exchange_rate'])) . '</div>' : '') . '
                        </div>
                        <div class="col-12 col-sm-6 col-lg-3 my-2">
                            <div class="form-label text-body-secondary">' . lang('Account') . '</div>
                            <div><a href="edit_erp_account.php?id=' . (int) $receipt['account_id'] . '" class="link-body-emphasis">' . h($receipt['account_title']) . '</a></div>
                        </div>
                        <div class="col-12 col-sm-6 col-lg-3 my-2">
                            <div class="form-label text-body-secondary">' . lang('Till or Bank Account') . '</div>
                            <div><a href="edit_erp_till.php?id=' . (int) $receipt['cash_account_id'] . '" class="link-body-emphasis">' . h($receipt['cash_account_name']) . '</a></div>
                        </div>
                        <div class="col-12 col-sm-6 col-lg-3 my-2">
                            <div class="form-label text-body-secondary">' . lang('Payment Method') . '</div>
                            <div>' . h(isset($method_labels[$receipt['payment_method']]) ? $method_labels[$receipt['payment_method']] : (string) $receipt['payment_method']) . '</div>
                        </div>
                        <div class="col-12 col-lg-9 my-2">
                            <div class="form-label text-body-secondary">' . lang('Description') . '</div>
                            <div>' . h($receipt['description']) . '</div>
                        </div>
                    </div>
                    ' . (is_array($reversal) ? '
                    <div class="alert alert-secondary mt-3 mb-0"><i class="bi bi-arrow-counterclockwise me-2"></i>' . h(prepare_form_data_for_output($reversal['doc_date'], 'date')) . ' &middot; ' . h($reversal['description']) . '</div>' : '') . '
                </div>
            </div>';

// The allocations, and what is left over.
$output_allocation_rows = '';

foreach ($settlements as $settlement) {
    $invoice_id = (int) $settlement['invoice_id'];

    $output_remove = '';
    if (!$is_cancelled && ((string) $settlement['invoice_status'] !== 'cancelled')) {
        $output_remove = '<form method="post" action="erp_receipt.php" class="d-inline">
                                        ' . get_token_field() . '
                                        <input type="hidden" name="id" value="' . $cash_id . '" />
                                        <input type="hidden" name="erp_action" value="unsettle" />
                                        <input type="hidden" name="settlement_id" value="' . (int) $settlement['id'] . '" />
                                        <button type="submit" class="btn btn-sm btn-outline-warning" title="' . lang('Remove allocation') . '" onclick="return confirm(\'' . h(lang('Remove this allocation? The money stays on the account; the invoice will ask for it again.')) . '\');"><i class="bi bi-x-circle me-1"></i>' . lang('Remove') . '</button>
                                    </form>';
    }

    $output_allocation_rows .= '
                                <tr>
                                    <td class="text-nowrap">' . h(prepare_form_data_for_output($settlement['doc_date'], 'date')) . '</td>
                                    <td><a href="edit_erp_invoice.php?id=' . $invoice_id . '" class="link-body-emphasis">' . h($settlement['full_number']) . '</a></td>
                                    <td>' . h((string) $settlement['invoice_status']) . '</td>
                                    <td class="text-end">' . $money((int) $settlement['amount'])
                                        . ($is_foreign ? ' <span class="text-body-secondary small">' . h(erp_money_out((int) $settlement['amount_base'])) . '</span>' : '') . '</td>
                                    <td class="text-end text-nowrap">' . $output_remove . '</td>
                                </tr>';
}

if ($output_allocation_rows === '') {
    $output_allocation_rows = '<tr><td colspan="5" class="text-center text-body-secondary py-3">' . lang('This receipt has not been allocated to any invoice.') . '</td></tr>';
}

$output_allocations = '
            <div class="card my-4">
                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <span>' . lang('Allocations') . '</span>
                    <span class="small fw-normal text-body-secondary">' . lang('Unallocated') . ': <span class="fw-bold ' . (($unallocated > 0 && !$is_cancelled) ? 'text-warning' : '') . '" id="unallocated_amount">' . $money($is_cancelled ? 0 : $unallocated) . '</span></span>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle mb-0" id="allocations_table">
                            <thead>
                                <tr>
                                    <th>' . lang('Date') . '</th>
                                    <th>' . lang('Invoice') . '</th>
                                    <th>' . lang('Status') . '</th>
                                    <th class="text-end">' . lang('Amount') . '</th>
                                    <th class="text-end">' . lang(array('string' => 'Action')) . '</th>
                                </tr>
                            </thead>
                            <tbody>' . $output_allocation_rows . '
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>';

// Allocating what is left: the account's open invoices in this currency, the
// oldest first, with a proposal already typed in.
$output_allocate = '';

if (!$is_cancelled && ($unallocated > 0)) {
    $suggestion = erp_settlement_suggest($ledger_id);
    $open_invoices = erp_open_invoices((int) $receipt['account_id'], 200, $is_collection ? 'sales' : 'purchase');

    $output_open_rows = '';
    foreach ($open_invoices as $invoice) {
        if (erp_fx_enabled() && (strtoupper(trim((string) $invoice['currency'])) !== $currency)) {
            continue;
        }

        $invoice_id = (int) $invoice['id'];
        $proposed = isset($suggestion[$invoice_id]) ? (int) $suggestion[$invoice_id] : 0;

        $output_open_rows .= '
                                <tr>
                                    <td><a href="edit_erp_invoice.php?id=' . $invoice_id . '" class="link-body-emphasis">' . h($invoice['full_number']) . '</a></td>
                                    <td class="text-nowrap">' . h(prepare_form_data_for_output($invoice['issue_date'], 'date')) . '</td>
                                    <td class="text-end">' . $money((int) $invoice['open_amount']) . '</td>
                                    <td class="text-end" style="width:12rem">
                                        <input type="text" name="allocate[' . $invoice_id . ']" id="allocate_' . $invoice_id . '" class="form-control form-control-sm text-end" inputmode="decimal" autocomplete="off" value="' . (($proposed > 0) ? h(number_format($proposed / 100, 2, '.', '')) : '') . '" />
                                    </td>
                                </tr>';
    }

    if ($output_open_rows !== '') {
        $output_allocate = '
            <form name="allocate_form" action="erp_receipt.php" method="post">
                ' . get_token_field() . '
                <input type="hidden" name="id" value="' . $cash_id . '" />
                <input type="hidden" name="erp_action" value="allocate" />
                <div class="card my-4">
                    <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                        ' . lang('Allocate to open invoices') . '
                    </div>
                    <div class="card-body">
                        <div class="form-text mb-2">' . lang('The amounts are suggested oldest first; change them before saving. Nothing is written until you save.') . '</div>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0" id="open_invoices_table">
                                <thead>
                                    <tr>
                                        <th>' . lang('Invoice') . '</th>
                                        <th>' . lang('Date') . '</th>
                                        <th class="text-end">' . lang('Outstanding') . '</th>
                                        <th class="text-end">' . lang('Allocate') . '</th>
                                    </tr>
                                </thead>
                                <tbody>' . $output_open_rows . '
                                </tbody>
                            </table>
                        </div>
                        <div class="text-end mt-3">
                            <button type="submit" name="submit_allocate" value="Allocate" class="btn btn-primary" data-loading-content="' . lang(array('string' => 'Saving')) . '"><span class="bi bi-link-45deg me-2"></span><span class="btn-text">' . lang('Save the allocations') . '</span></button>
                        </div>
                    </div>
                </div>
            </form>';
    } else {
        $output_allocate = '
            <div class="card my-4">
                <div class="card-body text-body-secondary"><i class="bi bi-info-circle me-2"></i>' . lang('There is no open invoice in this currency on the account to allocate the rest to.') . '</div>
            </div>';
    }
}

// Cancelling: a modal that asks why, so the reversal carries the reason.
$output_cancel = '';

if (!$is_cancelled) {
    $output_cancel = '
            <nav class="buttons navigation text-center position-sticky mb-4" style="bottom:.5rem;" aria-label="data edit buttons">
                <div class="container">
                    <div class="btn-group flex-wrap justify-content-center">
                        <button type="button" class="btn my-1 btn-outline-warning" data-bs-toggle="modal" data-bs-target="#cancel_receipt_modal"><span class="bi bi-arrow-counterclockwise me-2"></span>' . ($is_collection ? lang('Cancel the Receipt') : lang('Cancel the Payment')) . '</button>
                    </div>
                </div>
            </nav>

            <div class="modal fade" id="cancel_receipt_modal" tabindex="-1" aria-labelledby="cancel_receipt_modal_label" aria-hidden="true">
                <div class="modal-dialog">
                    <form method="post" action="erp_receipt.php">
                        ' . get_token_field() . '
                        <input type="hidden" name="id" value="' . $cash_id . '" />
                        <input type="hidden" name="erp_action" value="cancel" />
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="cancel_receipt_modal_label">' . ($is_collection ? lang('Cancel the Receipt') : lang('Cancel the Payment')) . '</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="' . lang('Close') . '"></button>
                            </div>
                            <div class="modal-body">
                                <p>' . lang('The till and the account get an opposite movement of the same amount, dated today; nothing is deleted. Every invoice this money closed will ask for it again.') . '</p>
                                <label for="reason" class="form-label">' . lang('Reason for the cancellation') . '</label>
                                <textarea name="reason" id="reason" class="form-control" rows="3" maxlength="200" required></textarea>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">' . lang('Close') . '</button>
                                <button type="submit" name="submit_cancel" value="Cancel" class="btn btn-outline-warning" data-loading-content="' . lang(array('string' => 'Please Wait')) . '"><span class="bi bi-arrow-counterclockwise me-2"></span><span class="btn-text">' . lang('Confirm the cancellation') . '</span></button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>';
}

echo
pg_page_shell([
    'title' => $title,
    'extra_classes' => 'erp erp_cash',
    'icon' => 'store',
    'heading' => $title . ' #' . $cash_id,
    'heading_description' => lang('The movement, the invoices it closed, and the corrections it allows.'),
    'cancel' => array('enable' => 'true', 'url' => 'edit_erp_till.php?id=' . (int) $receipt['cash_account_id']),
    'breadcrumb' => array(
        array('label' => lang('Cash and Bank'), 'url' => $list_url),
        array('label' => $receipt['cash_account_name'], 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/edit_erp_till.php?id=' . (int) $receipt['cash_account_id']),
        array('label' => $title . ' #' . $cash_id),
    ),
]) . '
<main id="content" class="container-fluid">
    <div class="row">
        <div class="col-12">
            ' . $liveform->output_errors() . '
            ' . $liveform->get_warnings() . '
            ' . $liveform->output_notices() . '
            ' . $output_header . '
            ' . $output_allocations . '
            ' . $output_allocate . '
            ' . $output_cancel . '
        </div>
    </div>
</main>' .
output_footer();

$liveform->remove_form();
