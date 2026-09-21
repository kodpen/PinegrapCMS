<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * ERP - read an invoice.
 *
 * An issued invoice is not edited. It is a document that has already been
 * handed to somebody, so a mistake on it is corrected by a credit note against
 * it rather than by quietly changing what it says. The lines are shown as they
 * were written, with the figure each one was checked against. The one field
 * that may change afterwards is the note at the foot of the document: the
 * ledger does not depend on it.
 *
 * A draft is not a document yet; it is sent on to the draft editor.
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
$liveform = new liveform('edit_erp_invoice');

$list_url = OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/erp_invoices.php';

$invoice_id = (int) ($_REQUEST['id'] ?? 0);

$invoice = ($invoice_id > 0)
    ? db_item("SELECT i.*, a.title AS live_account_title, a.tax_number AS live_tax_number, a.tax_office AS live_tax_office, o.order_number
        FROM erp_invoices i
        LEFT JOIN erp_accounts a ON i.account_id = a.id
        LEFT JOIN orders o ON i.order_id = o.id
        WHERE i.id = '" . $invoice_id . "' LIMIT 1")
    : null;

if (!is_array($invoice)) {
    output_error(lang('The invoice could not be found.') . ' <a href="' . h($list_url) . '">' . lang('Invoices') . '</a>');
    exit();
}

// A draft has no number and no movement; it is edited, not read.
if ((string) $invoice['status'] === 'draft') {
    go(OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/edit_erp_invoice_draft.php?id=' . $invoice_id);
}

if ($_POST) {

    validate_token_field();

    if (($_POST['erp_action'] ?? '') === 'notes') {

        $notes = mb_substr(trim((string) ($_POST['notes'] ?? '')), 0, 5000);

        if (db("UPDATE erp_invoices SET notes = '" . escape($notes) . "', updated_at = '" . time() . "' WHERE id = '" . $invoice_id . "'") !== false) {
            $liveform->add_notice(lang('The note has been saved.'));
        } else {
            $liveform->mark_error('_error', lang('The note could not be saved.'));
        }

        go(OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/edit_erp_invoice.php?id=' . $invoice_id);
    }

    // Putting the overdue reminders off, or letting them resume. The date
    // itself is checked in the module; the screen only carries the answer.
    if (($_POST['erp_action'] ?? '') === 'snooze') {

        // The field is typed in the site's date format; the module wants Y-m-d.
        $snooze_until = trim((string) ($_POST['snooze_until'] ?? ''));
        $result = (($snooze_until !== '') && validate_date($snooze_until))
            ? erp_overdue_snooze($invoice_id, prepare_form_data_for_input($snooze_until, 'date'))
            : array('success' => false, 'error' => lang('Please enter a valid date.'), 'until' => 0);

        if ($result['success']) {
            log_activity(lang(array('string' => 'erp overdue reminders for ({var:1}) were put off until {var:2}', 'vars' => array($invoice['full_number'], date('Y-m-d', $result['until'])))), $_SESSION['sessionusername']);
            $liveform->add_notice(lang(array('string' => 'The reminders for this invoice are put off until {var:1}.', 'vars' => prepare_form_data_for_output(date('Y-m-d', $result['until']), 'date'))));
        } else {
            $liveform->mark_error('snooze_until', $result['error']);
        }

        go(OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/edit_erp_invoice.php?id=' . $invoice_id);
    }

    if (($_POST['erp_action'] ?? '') === 'unsnooze') {

        if (erp_overdue_unsnooze($invoice_id)) {
            log_activity(lang(array('string' => 'erp overdue reminders for ({var:1}) were resumed', 'vars' => $invoice['full_number'])), $_SESSION['sessionusername']);
            $liveform->add_notice(lang('The reminders for this invoice will resume.'));
        } else {
            $liveform->mark_error('_error', lang('The change could not be saved.'));
        }

        go(OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/edit_erp_invoice.php?id=' . $invoice_id);
    }

    // Taking an allocation off moves money about on paper, so it takes the
    // cash right, like recording the receipt did.
    if (($_POST['erp_action'] ?? '') === 'unsettle') {

        if (!defined('USER_MANAGE_ERP_CASH') || !USER_MANAGE_ERP_CASH) {
            $liveform->mark_error('_error', lang('Access denied'));
            go(OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/edit_erp_invoice.php?id=' . $invoice_id);
        }

        $result = erp_settlement_remove((int) ($_POST['settlement_id'] ?? 0), (int) $user['id']);

        if ($result['success'] && ((int) $result['invoice_id'] === $invoice_id)) {
            log_activity(lang(array('string' => 'erp allocation was removed from invoice ({var:1})', 'vars' => $invoice['full_number'])), $_SESSION['sessionusername']);
            $liveform->add_notice(lang('The allocation has been removed.'));
        } else {
            $liveform->mark_error('_error', ($result['error'] !== '') ? $result['error'] : lang('The allocation could not be found.'));
        }

        go(OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/edit_erp_invoice.php?id=' . $invoice_id);
    }

    // The e-document: off to the provider, or a question about where it
    // stands. Both answer in the provider's own words on the notice line.
    if (($_POST['erp_action'] ?? '') === 'edoc_send') {

        $result = erp_edoc_invoice_send($invoice_id, (int) $user['id']);

        if ($result['success']) {
            $liveform->add_notice($result['message']);
        } else {
            $liveform->mark_error('_error', $result['error']);
        }

        go(OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/edit_erp_invoice.php?id=' . $invoice_id);
    }

    if (($_POST['erp_action'] ?? '') === 'edoc_poll') {

        $result = erp_edoc_invoice_poll($invoice_id);

        if ($result['success']) {
            $liveform->add_notice($result['message']);
        } else {
            $liveform->mark_error('_error', $result['error']);
        }

        go(OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/edit_erp_invoice.php?id=' . $invoice_id);
    }

    if (($_POST['erp_action'] ?? '') === 'cancel') {

        $result = erp_invoice_cancel($invoice_id, (int) $user['id']);

        if ($result['success']) {
            log_activity(lang(array('string' => 'erp document ({var:1}) was cancelled', 'vars' => $invoice['full_number'])), $_SESSION['sessionusername']);
            $liveform->add_notice(lang('The document has been cancelled.'));
        } else {
            $liveform->mark_error('_error', $result['error']);
        }

        go(OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/edit_erp_invoice.php?id=' . $invoice_id);
    }
}

$lines = (array) db_items("SELECT * FROM erp_invoice_items
    WHERE invoice_id = '" . $invoice_id . "' ORDER BY line_no ASC, id ASC");

$settlements = erp_invoice_settlements($invoice_id);
$returns = erp_invoice_returns($invoice_id);
$returned_total = erp_invoice_returned_total($invoice_id);
$open_amount = erp_invoice_open_amount($invoice);

$is_return = ((string) $invoice['doc_type'] === 'return');
$is_cancelled = ((string) $invoice['status'] === 'cancelled');

// Every figure on the document is in its own currency. A document in another
// currency also states its rate and its base-currency total, once.
$currency = strtoupper(trim((string) $invoice['currency']));
$is_foreign = ($currency !== erp_base_currency());

$money = function ($kurus, $show_sign = true) use ($currency) {
    return h(erp_money_out_currency((int) $kurus, $currency, $show_sign));
};

// Cancelling is for a document nothing has been hung on yet. Once money has
// been allocated to it or goods have come back against it, the way out is a
// return, not a quiet withdrawal. The gift card allocation the invoice posted
// for itself is not somebody's money and is reversed with the document.
$can_cancel = (!$is_cancelled && (erp_invoice_receipt_count($invoice_id) === 0) && empty($returns));
$can_return = (!$is_cancelled && !$is_return && ($invoice['doc_type'] === 'invoice'));

$status_labels = array(
    'draft' => lang('Draft'),
    'issued' => lang('Issued'),
    'partially_paid' => lang('Partly paid'),
    'paid' => lang('Paid'),
    'cancelled' => lang('Cancelled'),
);

// The lines are shown in the order they were written on the document, so this
// is deliberately not a DataTable: a line number is part of what the document
// says, and sorting it away would show something the customer never received.
$output_lines = '';

foreach ($lines as $line) {
    $rate = rtrim(rtrim((string) $line['tax_rate'], '0'), '.');
    $discount = (int) $line['discount_amount'];

    // How the discount was made up: the store's campaign on the product and
    // the rate typed on top of it, when either is on the line.
    $trim_rate = function ($value) {
        return rtrim(rtrim(number_format((float) $value, 3, '.', ''), '0'), '.');
    };
    $discount_parts = array();
    if ((float) ($line['offer_discount_rate'] ?? 0) > 0) {
        $discount_parts[] = lang('Campaign') . ' %' . $trim_rate($line['offer_discount_rate']);
    }
    if ((float) ($line['discount_rate'] ?? 0) > 0) {
        $discount_parts[] = '%' . $trim_rate($line['discount_rate']);
    }
    $output_discount_note = (($discount > 0) && (count($discount_parts) > 0))
        ? '<div class="small text-body-secondary text-nowrap">' . h(implode(' + ', $discount_parts)) . '</div>'
        : '';

    $output_lines .= '
        <tr>
            <td class="align-middle text-body-secondary">' . (int) $line['line_no'] . '</td>
            <td class="align-middle">' . h($line['description']) . '</td>
            <td class="align-middle text-end">' . h(rtrim(rtrim(number_format((float) $line['quantity'], 4, '.', ''), '0'), '.')) . '</td>
            <td class="align-middle text-end">' . $money((int) $line['unit_price']) . '</td>
            <td class="align-middle text-end">' . (($discount > 0) ? '&minus;' . $money($discount) : '') . $output_discount_note . '</td>
            <td class="align-middle text-end">' . $money((int) $line['line_total'] - $discount) . '</td>
            <td class="align-middle text-end text-nowrap">%' . h($rate) . '</td>
            <td class="align-middle text-end">' . $money((int) $line['tax_total']) . '</td>
        </tr>';
}

if ($output_lines === '') {
    $output_lines = '<tr><td colspan="8" class="text-center text-body-secondary py-4">' . lang('This invoice has no lines.') . '</td></tr>';
}

// The money behind the paid figure. Shown even when it is one line, because
// "paid" without saying which receipt paid it is the thing nobody can reconcile.
$output_settlements = '';

if (!empty($settlements)) {
    $output_settlements = '
                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle mb-0 mt-3">
                            <thead>
                                <tr>
                                    <th>' . lang('Date') . '</th>
                                    <th>' . lang('Description') . '</th>
                                    <th>' . lang('Till or Bank Account') . '</th>
                                    <th class="text-end">' . lang('Amount') . '</th>
                                    <th class="text-end">' . lang(array('string' => 'Action')) . '</th>
                                </tr>
                            </thead>
                            <tbody>';

    // The receipt behind an allocation opens on its own screen; the allocation
    // itself can be taken off by whoever may move cash. The gift card's is not
    // anybody's decision and goes only with the invoice.
    $can_unsettle = (!$is_cancelled && defined('USER_MANAGE_ERP_CASH') && USER_MANAGE_ERP_CASH);

    foreach ($settlements as $settlement) {
        $is_receipt = in_array((string) $settlement['source_type'], array('collection', 'payment'), true);

        $output_actions = '';
        if ($is_receipt && ((int) $settlement['cash_id'] > 0)) {
            $output_actions .= '<a class="btn btn-sm btn-outline-secondary" href="erp_receipt.php?id=' . (int) $settlement['cash_id'] . '" title="' . lang('Receipt') . '"><i class="bi bi-receipt"></i></a> ';
        }
        if ($can_unsettle && ((string) $settlement['source_type'] !== 'gift_card')) {
            $output_actions .= '<form method="post" action="edit_erp_invoice.php" class="d-inline">
                                        ' . get_token_field() . '
                                        <input type="hidden" name="id" value="' . $invoice_id . '" />
                                        <input type="hidden" name="erp_action" value="unsettle" />
                                        <input type="hidden" name="settlement_id" value="' . (int) $settlement['id'] . '" />
                                        <button type="submit" class="btn btn-sm btn-outline-warning" title="' . lang('Remove allocation') . '" onclick="return confirm(\'' . h(lang('Remove this allocation? The money stays on the account; the invoice will ask for it again.')) . '\');"><i class="bi bi-x-circle"></i></button>
                                    </form>';
        }

        $output_settlements .= '
                                <tr>
                                    <td class="text-nowrap">' . h(prepare_form_data_for_output($settlement['doc_date'], 'date')) . '</td>
                                    <td>' . h($settlement['description']) . '</td>
                                    <td>' . h($settlement['cash_account_name']) . '</td>
                                    <td class="text-end">' . $money((int) $settlement['amount'])
                                        . ($is_foreign ? ' <span class="text-body-secondary small">' . h(erp_money_out((int) $settlement['amount_base'])) . '</span>' : '') . '</td>
                                    <td class="text-end text-nowrap">' . $output_actions . '</td>
                                </tr>';
    }

    $output_settlements .= '
                            </tbody>
                        </table>
                    </div>';
}

// The returns, listed on the document they were raised against, because that is
// where somebody looking at a total that no longer matches will look first.
$output_returns = '';

if (!empty($returns)) {
    $output_returns = '
                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle mb-0 mt-3">
                            <thead>
                                <tr>
                                    <th>' . lang('Date') . '</th>
                                    <th>' . lang('Return') . '</th>
                                    <th class="text-end">' . lang('Amount') . '</th>
                                </tr>
                            </thead>
                            <tbody>';

    foreach ($returns as $return) {
        $output_returns .= '
                                <tr>
                                    <td class="text-nowrap">' . h(prepare_form_data_for_output($return['issue_date'], 'date')) . '</td>
                                    <td><a href="edit_erp_invoice.php?id=' . (int) $return['id'] . '">' . h($return['full_number']) . '</a></td>
                                    <td class="text-end">' . $money((int) $return['grand_total']) . '</td>
                                </tr>';
    }

    $output_returns .= '
                            </tbody>
                        </table>
                    </div>';
}

$output_order = ((int) $invoice['order_id'] > 0)
    ? '<a href="view_order.php?id=' . (int) $invoice['order_id'] . '">' . h($invoice['order_number'] ?: ('#' . (int) $invoice['order_id'])) . '</a>'
    : '<span class="text-body-secondary">&mdash;</span>';

// The counterparty as it read when the document was issued; the live card
// only for documents written before the copy existed.
$has_snapshot = (trim((string) ($invoice['account_title'] ?? '')) !== '');
$account_title = $has_snapshot ? (string) $invoice['account_title'] : (string) $invoice['live_account_title'];
$account_tax_number = $has_snapshot ? (string) $invoice['account_tax_number'] : (string) $invoice['live_tax_number'];
$account_tax_office = $has_snapshot ? (string) $invoice['account_tax_office'] : (string) $invoice['live_tax_office'];

$output_account = ((int) $invoice['account_id'] > 0)
    ? '<a href="edit_erp_account.php?id=' . (int) $invoice['account_id'] . '">' . h($account_title) . '</a>'
    : h($account_title);

if (!$liveform->field_in_session('notes')) {
    $liveform->assign_field_value('notes', (string) ($invoice['notes'] ?? ''));
}

$output_supplier = '';
if (((string) $invoice['direction'] === 'purchase') && (trim((string) $invoice['supplier_invoice_no']) !== '')) {
    $supplier_date = (string) $invoice['supplier_invoice_date'];
    $output_supplier = '
                        <div class="col-12 col-sm-6 col-lg-3 my-2">
                            <div class="form-label text-body-secondary">' . lang('Supplier Invoice Number') . '</div>
                            <div>' . h($invoice['supplier_invoice_no']) . (($supplier_date !== '' && $supplier_date !== '0000-00-00') ? ' <span class="text-body-secondary">' . h(prepare_form_data_for_output($supplier_date, 'date')) . '</span>' : '') . '</div>
                        </div>';
}

// The internet sale block: an e-archive invoice for a sale made over the
// internet has to state how and when it was paid, who carried the goods and
// when they left, and the address it was sold at. Only shown for such a sale.
$output_internet_sale = '';
if ((int) ($invoice['is_internet_sale'] ?? 0) === 1) {
    $empty = '<span class="text-body-secondary">&mdash;</span>';

    $date_out = function ($date) use ($empty) {
        $date = (string) $date;
        return ($date !== '' && $date !== '0000-00-00') ? h(prepare_form_data_for_output($date, 'date')) : $empty;
    };

    $payment_code = (string) ($invoice['payment_method'] ?? '');
    $payment_labels = erp_payment_method_labels();
    $output_payment_method = ($payment_code !== '') ? h($payment_labels[$payment_code] ?? $payment_code) : $empty;

    $carrier_title = (string) ($invoice['carrier_title'] ?? '');
    $carrier_vkn = (string) ($invoice['carrier_vkn'] ?? '');
    $output_carrier = ($carrier_title !== '' || $carrier_vkn !== '')
        ? h($carrier_title) . (($carrier_vkn !== '') ? ' <span class="text-body-secondary">' . h($carrier_vkn) . '</span>' : '')
        : $empty;

    $web_address = (string) ($invoice['web_address'] ?? '');
    $output_web_address = ($web_address !== '') ? h($web_address) : $empty;

    $output_internet_sale = '
                    <div class="row border-top mt-2 pt-2">
                        <div class="col-12 col-sm-6 col-lg-3 my-2">
                            <div class="form-label text-body-secondary">' . lang('Payment Method') . '</div>
                            <div>' . $output_payment_method . '</div>
                        </div>
                        <div class="col-12 col-sm-6 col-lg-2 my-2">
                            <div class="form-label text-body-secondary">' . lang('Payment Date') . '</div>
                            <div>' . $date_out($invoice['payment_date'] ?? '') . '</div>
                        </div>
                        <div class="col-12 col-sm-6 col-lg-2 my-2">
                            <div class="form-label text-body-secondary">' . lang('Shipment Date') . '</div>
                            <div>' . $date_out($invoice['shipment_date'] ?? '') . '</div>
                        </div>
                        <div class="col-12 col-sm-6 col-lg-3 my-2">
                            <div class="form-label text-body-secondary">' . lang('Carrier') . '</div>
                            <div>' . $output_carrier . '</div>
                        </div>
                        <div class="col-12 col-sm-6 col-lg-2 my-2">
                            <div class="form-label text-body-secondary">' . lang('Web Address') . '</div>
                            <div>' . $output_web_address . '</div>
                        </div>
                    </div>';
}

// The overdue reminders, for a claim that can be late. The card appears once
// the store has switched reminders on, or once this document has been in a
// digest; a store that never set a threshold is not told about it on every
// invoice.
$output_reminders = '';
$reminder = erp_overdue_invoice_state($invoice);

if (($reminder['phase'] !== 'off') || ($reminder['notified_at'] > 0) || ($reminder['snoozed_until'] > 0)) {

    $date_stamp = function ($stamp) {
        return h(prepare_form_data_for_output(date('Y-m-d', (int) $stamp), 'date'));
    };

    $reminder_lines = array();

    switch ($reminder['phase']) {
        case 'not_due':
            $reminder_lines[] = lang('Not yet due.');
            break;
        case 'below':
            $reminder_lines[] = lang(array('string' => '{var:1} day(s) overdue; the reminder threshold for this account is {var:2} days. It will be announced once it passes.', 'vars' => array($reminder['days'], $reminder['threshold'])));
            break;
        case 'pending':
            $reminder_lines[] = lang(array('string' => '{var:1} day(s) overdue, past the {var:2}-day threshold. It will be announced in the next digest.', 'vars' => array($reminder['days'], $reminder['threshold'])));
            break;
        case 'pending_second':
            $reminder_lines[] = lang(array('string' => 'Announced on {var:1}. A month past the threshold and still open: the next digest lists it a second and last time.', 'vars' => $date_stamp($reminder['notified_at'])));
            break;
        case 'snoozed':
            $reminder_lines[] = lang(array('string' => 'Put off: left out of the digests until {var:1}.', 'vars' => $date_stamp($reminder['snoozed_until'])));
            if ($reminder['notified_at'] > 0) {
                $reminder_lines[] = lang(array('string' => 'Announced on {var:1}.', 'vars' => $date_stamp($reminder['notified_at'])));
            }
            break;
        case 'announced':
            $reminder_lines[] = lang(array('string' => 'Announced on {var:1}.', 'vars' => $date_stamp($reminder['notified_at'])));
            if ($reminder['second_notified_at'] > 0) {
                $reminder_lines[] = lang(array('string' => 'Announced a second and last time on {var:1}. From now on it only counts in the total.', 'vars' => $date_stamp($reminder['second_notified_at'])));
            }
            break;
        default:
            // Nothing ahead: reminders are off, or the document is no longer
            // a claim that can be late (paid, cancelled). What was said about
            // it is still worth reading.
            if (!erp_overdue_notify_enabled()) {
                $reminder_lines[] = lang('Reminders are switched off on the ERP settings card.');
            }
            if ($reminder['notified_at'] > 0) {
                $reminder_lines[] = lang(array('string' => 'Announced on {var:1}.', 'vars' => $date_stamp($reminder['notified_at'])));
            }
            if ($reminder['second_notified_at'] > 0) {
                $reminder_lines[] = lang(array('string' => 'Announced a second and last time on {var:1}. From now on it only counts in the total.', 'vars' => $date_stamp($reminder['second_notified_at'])));
            }
    }

    if ($reminder['customer_notified_at'] > 0) {
        $reminder_lines[] = lang(array('string' => 'The customer was e-mailed on {var:1}.', 'vars' => $date_stamp($reminder['customer_notified_at'])));
    } elseif (defined('ERP_OVERDUE_NOTIFY_CUSTOMER') && ERP_OVERDUE_NOTIFY_CUSTOMER && ($reminder['phase'] !== 'off')) {
        if (!$reminder['customer_wanted']) {
            $reminder_lines[] = lang('This account does not receive reminder e-mails.');
        } elseif ($reminder['customer_address'] === '') {
            $reminder_lines[] = lang('The account has no e-mail address, so the customer cannot be written to.');
        }
    }

    $output_reminder_lines = '';
    foreach ($reminder_lines as $line) {
        $output_reminder_lines .= '<div>' . h($line) . '</div>';
    }

    // A snooze is offered while the reminders have anything left to say: up
    // to and including the second announcement.
    $can_snooze = ($reminder['phase'] !== 'off') && (($reminder['second_notified_at'] === 0) || ($reminder['phase'] === 'snoozed'));

    $output_snooze = '';

    if ($can_snooze) {
        $default_until = ($reminder['snoozed_until'] > time()) ? date('Y-m-d', $reminder['snoozed_until']) : date('Y-m-d', strtotime('+14 days'));

        if (!$liveform->field_in_session('snooze_until')) {
            $liveform->assign_field_value('snooze_until', prepare_form_data_for_output($default_until, 'date'));
        }

        $output_snooze = '
                        <form name="snooze_form" action="edit_erp_invoice.php" method="post" class="row g-2 align-items-end">
                            ' . get_token_field() . '
                            <input type="hidden" name="id" value="' . $invoice_id . '" />
                            <input type="hidden" name="erp_action" value="snooze" />
                            <div class="col-12 col-sm-7">
                                <label for="snooze_until" class="form-label">' . lang('Put the reminders off until') . '</label>
                                ' . $liveform->output_field(array(
                                    'type' => 'text', 'id' => 'snooze_until', 'name' => 'snooze_until',
                                    'class' => 'form-control', 'size' => '10', 'maxlength' => '10',
                                    'autocomplete' => 'off')) . '
                                ' . get_date_picker_format() . '
                                <script>$("#snooze_until").datepicker(datetimepicker_options);</script>
                            </div>
                            <div class="col-12 col-sm-5">
                                <button type="submit" name="submit_snooze" value="Snooze" class="btn btn-outline-secondary w-100" data-loading-content="' . lang(array('string' => 'Saving')) . '"><span class="bi bi-bell-slash me-2"></span><span class="btn-text">' . lang('Put off') . '</span></button>
                            </div>
                        </form>'
            . (($reminder['phase'] === 'snoozed')
                ? '
                        <form name="unsnooze_form" action="edit_erp_invoice.php" method="post" class="mt-2">
                            ' . get_token_field() . '
                            <input type="hidden" name="id" value="' . $invoice_id . '" />
                            <input type="hidden" name="erp_action" value="unsnooze" />
                            <button type="submit" name="submit_unsnooze" value="Resume" class="btn btn-link link-body-emphasis text-decoration-none p-0" data-loading-content="' . lang(array('string' => 'Saving')) . '"><span class="bi bi-bell me-2"></span><span class="btn-text">' . lang('Let the reminders resume') . '</span></button>
                        </form>'
                : '');
    }

    $output_reminders = '
            <div class="card my-4">
                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                    ' . lang('Overdue reminders') . '
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-12 col-lg-7 my-2">' . $output_reminder_lines . '
                            <div class="form-text">' . lang('The digest goes to the panel bell, by e-mail and to subscribed devices as set on the ERP settings card. A document is announced when it passes the threshold and once more a month later; putting it off keeps it out of the digests until the date you choose.') . '</div>
                        </div>
                        <div class="col-12 col-lg-5 my-2">' . $output_snooze . '
                        </div>
                    </div>
                </div>
            </div>';
}

// The delivery note for what this invoice sold: the one written, or the way
// to write it. The usual order is invoice first, note second, so the button
// sits here; the note opens filled in from the invoice (its order's goods and
// address, or its own lines) and ties itself to the invoice.
$output_waybill_button = '';
if (((string) $invoice['direction'] === 'sales') && !$is_return && !$is_cancelled && ((string) $invoice['status'] !== 'draft') && function_exists('erp_waybills_for_invoice')) {
    $invoice_waybills = erp_waybills_for_invoice($invoice_id);
    if (!$invoice_waybills && ((int) $invoice['order_id'] > 0)) {
        $invoice_waybills = erp_waybills_for_order((int) $invoice['order_id']);
    }

    if ($invoice_waybills) {
        $output_waybill_button = '<a class="btn btn-sm btn-outline-secondary m-1" href="edit_erp_waybill.php?id=' . (int) $invoice_waybills[0]['id'] . '"><i class="bi bi-truck me-2"></i>' . h($invoice_waybills[0]['full_number']) . '</a>';
    } else {
        $output_waybill_button = '<a class="btn btn-sm btn-outline-primary m-1" href="add_erp_waybill.php?invoice_id=' . $invoice_id . '" data-loading-content="' . lang(array('string' => 'Loading')) . '"><i class="bi bi-truck me-2"></i>' . lang('Issue the Delivery Note') . '</a>';
    }
}

// The e-document card: shown once a provider is chosen or the document has
// been through one. It says where the document stands, offers the send and
// the status question, and hands back the provider's PDF / UBL once there
// is one. The last few provider calls are listed so an error can be read
// without opening the database.
$output_edoc = '';
$edoc_active = erp_edoc_installed() ? erp_edoc_active() : '';
$edoc_provider = (string) ($invoice['edoc_provider'] ?? '');

if (($edoc_active !== '') || ($edoc_provider !== '') || ((string) ($invoice['edoc_status'] ?? 'none') !== 'none')) {
    $edoc_labels = erp_edoc_status_labels();
    $edoc_status = (string) ($invoice['edoc_status'] ?? 'none');
    $edoc_label = $edoc_labels[$edoc_status] ?? array($edoc_status, 'secondary');
    $edoc_gate = erp_edoc_invoice_can_send($invoice);
    $edoc_carrier = ($edoc_provider !== '') ? $edoc_provider : $edoc_active;
    $edoc_carrier_info = ($edoc_carrier !== '') ? erp_edoc_info($edoc_carrier) : null;
    $edoc_carrier_label = is_array($edoc_carrier_info) ? (string) $edoc_carrier_info['label'] : $edoc_carrier;
    $empty = '<span class="text-body-secondary">&mdash;</span>';

    $output_edoc_buttons = '';

    if ($edoc_gate['ok']) {
        $output_edoc_buttons .= '<form method="post" action="edit_erp_invoice.php" class="d-inline">
                        ' . get_token_field() . '
                        <input type="hidden" name="id" value="' . $invoice_id . '" />
                        <input type="hidden" name="erp_action" value="edoc_send" />
                        <button type="submit" class="btn btn-sm btn-primary m-1" data-loading-content="' . lang(array('string' => 'Sending')) . '"><span class="bi bi-send me-2"></span><span class="btn-text">' . h(lang(array('string' => 'Send to {var:1}', 'vars' => $edoc_carrier_label))) . '</span></button>
                    </form>';
    }

    if (($edoc_provider !== '') && ((string) $invoice['edoc_external_id'] !== '') && erp_edoc_supports('poll', $edoc_provider)) {
        $output_edoc_buttons .= '<form method="post" action="edit_erp_invoice.php" class="d-inline">
                        ' . get_token_field() . '
                        <input type="hidden" name="id" value="' . $invoice_id . '" />
                        <input type="hidden" name="erp_action" value="edoc_poll" />
                        <button type="submit" class="btn btn-sm btn-outline-secondary m-1" data-loading-content="' . lang(array('string' => 'Asking')) . '"><span class="bi bi-arrow-repeat me-2"></span><span class="btn-text">' . lang('Ask After the Status') . '</span></button>
                    </form>';
    }

    if (($edoc_provider !== '') && erp_edoc_supports('fetch_document', $edoc_provider)) {
        if ((string) $invoice['gib_uuid'] !== '') {
            $output_edoc_buttons .= '<a class="btn btn-sm btn-outline-secondary m-1" href="get_erp_edoc_document.php?id=' . $invoice_id . '&amp;format=pdf" target="_blank" rel="noopener"><i class="bi bi-file-earmark-pdf me-2"></i>' . h(lang(array('string' => '{var:1} PDF', 'vars' => $edoc_carrier_label))) . '</a>';
        }

        if ((string) $invoice['edoc_external_id'] !== '') {
            $output_edoc_buttons .= '<a class="btn btn-sm btn-outline-secondary m-1" href="get_erp_edoc_document.php?id=' . $invoice_id . '&amp;format=xml"><i class="bi bi-filetype-xml me-2"></i>' . lang('UBL') . '</a>';
        }
    }

    $output_edoc_log = '';

    foreach (erp_edoc_log_for('invoice', $invoice_id, 8) as $log_row) {
        $output_edoc_log .= '<tr>
                                <td class="text-nowrap text-body-secondary small">' . h(date('d.m.Y H:i:s', (int) $log_row['created_at'])) . '</td>
                                <td class="small"><code>' . h($log_row['method'] . ' ' . $log_row['path']) . '</code></td>
                                <td class="text-end small">' . (((int) $log_row['http_code'] >= 200 && (int) $log_row['http_code'] < 300) ? '<span class="text-success">' : '<span class="text-danger">') . (int) $log_row['http_code'] . '</span></td>
                                <td class="small text-break">' . h(mb_substr((string) $log_row['response_excerpt'], 0, 200)) . '</td>
                            </tr>';
    }

    $output_edoc = '
            <div class="card my-4">
                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <span>' . lang('e-Document') . '</span>
                    <span>' . $output_edoc_buttons . '</span>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-12 col-sm-6 col-lg-3 my-2">
                            <div class="form-label text-body-secondary">' . lang('Status') . '</div>
                            <div><span class="badge text-bg-' . h($edoc_label[1]) . '">' . h($edoc_label[0]) . '</span></div>
                        </div>
                        <div class="col-12 col-sm-6 col-lg-3 my-2">
                            <div class="form-label text-body-secondary">' . lang('Provider') . '</div>
                            <div>' . (($edoc_provider !== '') ? h($edoc_carrier_label) : (($edoc_active !== '') ? '<span class="text-body-secondary">' . h(lang(array('string' => '{var:1} (not sent yet)', 'vars' => $edoc_carrier_label))) . '</span>' : $empty)) . '</div>
                        </div>
                        <div class="col-12 col-sm-6 col-lg-3 my-2">
                            <div class="form-label text-body-secondary">' . lang('GİB Number') . '</div>
                            <div>' . (((string) $invoice['gib_number'] !== '') ? h($invoice['gib_number']) : $empty) . '</div>
                        </div>
                        <div class="col-12 col-sm-6 col-lg-3 my-2">
                            <div class="form-label text-body-secondary">' . lang('ETTN') . '</div>
                            <div class="text-break small">' . (((string) $invoice['gib_uuid'] !== '') ? h($invoice['gib_uuid']) : $empty) . '</div>
                        </div>
                        <div class="col-12 col-sm-6 col-lg-3 my-2">
                            <div class="form-label text-body-secondary">' . lang('Provider Id') . '</div>
                            <div>' . (((string) $invoice['edoc_external_id'] !== '') ? h($invoice['edoc_external_id']) : $empty) . '</div>
                        </div>
                        <div class="col-12 col-sm-6 col-lg-3 my-2">
                            <div class="form-label text-body-secondary">' . lang('Sent At') . '</div>
                            <div>' . (((int) $invoice['edoc_sent_at'] > 0) ? h(date('d.m.Y H:i', (int) $invoice['edoc_sent_at'])) : $empty) . '</div>
                        </div>
                        ' . ((trim((string) $invoice['edoc_error']) !== '')
                            ? '<div class="col-12 my-2">
                            <div class="form-label text-body-secondary">' . lang('Last Message') . '</div>
                            <div class="' . (in_array($edoc_status, array('error', 'rejected'), true) ? 'text-danger' : '') . '">' . h($invoice['edoc_error']) . '</div>
                        </div>'
                            : '') . '
                        ' . ((!$edoc_gate['ok'] && ($edoc_status === 'none'))
                            ? '<div class="col-12 my-2"><div class="form-text">' . h($edoc_gate['reason']) . '</div></div>'
                            : '') . '
                    </div>
                    ' . (($output_edoc_log !== '')
                        ? '<div class="table-responsive border-top mt-2 pt-2">
                        <table class="table table-sm mb-0">
                            <thead><tr><th>' . lang('Time') . '</th><th>' . lang('Call') . '</th><th class="text-end">' . lang('HTTP') . '</th><th>' . lang('Answer') . '</th></tr></thead>
                            <tbody>' . $output_edoc_log . '</tbody>
                        </table>
                    </div>'
                        : '') . '
                </div>
            </div>';
}

echo
pg_page_shell([
        'title' => $is_return ? lang('Return') : lang('Invoice'),
        'extra_classes' => 'erp erp_invoices',
        'icon' => 'store',
        'heading' => h($invoice['full_number']),
        'heading_description' => lang('The document as it was issued, and the lines it was built from.'),
        'cancel' => array('enable' => 'true', 'url' => 'erp_invoices.php'),
        'breadcrumb' => array(
            array('label' => lang('Invoices'), 'url' => $list_url),
            array('label' => $invoice['full_number']),
        ),
    ]) . '
<main id="content" class="container-fluid">
    <div class="row">
        <div class="col-12">
            ' . $liveform->output_errors() . '
            ' . $liveform->get_warnings() . '
            ' . $liveform->output_notices() . '

            <div class="row mb-2 flex-wrap">
                <div class="col-12 text-center text-md-start">
                    <nav id="button_bar" class="navigation" aria-label="Button Bar">
                        <a class="btn btn-sm btn-outline-secondary m-1" href="get_erp_invoice_pdf.php?id=' . $invoice_id . '" target="_blank" rel="noopener"><i class="bi bi-file-earmark-pdf me-2"></i>' . lang('PDF') . '</a>
                        <a class="btn btn-sm btn-outline-secondary m-1" href="get_erp_invoice_pdf.php?id=' . $invoice_id . '&amp;download=1"><i class="bi bi-download me-2"></i>' . lang('Download') . '</a>
                        ' . $output_waybill_button . '
                    </nav>
                </div>
            </div>

            <div class="card my-4">
                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                    ' . ($is_return ? lang('Return') : lang('Invoice')) . '
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-12 col-sm-6 col-lg-3 my-2">
                            <div class="form-label text-body-secondary">' . lang('Account') . '</div>
                            <div>' . $output_account . '</div>
                        </div>
                        <div class="col-12 col-sm-6 col-lg-3 my-2">
                            <div class="form-label text-body-secondary">' . lang('VKN / TCKN') . '</div>
                            <div>' . h($account_tax_number ?: '—') . ' <span class="text-body-secondary">' . h($account_tax_office) . '</span></div>
                        </div>
                        <div class="col-12 col-sm-4 col-lg-2 my-2">
                            <div class="form-label text-body-secondary">' . lang('Date') . '</div>
                            <div>' . h(prepare_form_data_for_output($invoice['issue_date'], 'date')) . '</div>
                        </div>
                        <div class="col-12 col-sm-4 col-lg-2 my-2">
                            <div class="form-label text-body-secondary">' . lang('Due Date') . '</div>
                            <div>' . h(prepare_form_data_for_output(((string) $invoice['due_date'] !== '0000-00-00') ? $invoice['due_date'] : $invoice['issue_date'], 'date')) . '</div>
                        </div>
                        <div class="col-12 col-sm-4 col-lg-2 my-2">
                            <div class="form-label text-body-secondary">' . lang('Status') . '</div>
                            <div>' . h($status_labels[$invoice['status']] ?? $invoice['status']) . '</div>
                        </div>
                        <div class="col-12 col-sm-4 col-lg-2 my-2">
                            <div class="form-label text-body-secondary">' . lang('Order') . '</div>
                            <div>' . $output_order . '</div>
                        </div>
                        ' . ($is_return
                            ? '<div class="col-12 col-sm-4 col-lg-2 my-2">
                            <div class="form-label text-body-secondary">' . lang('Against Invoice') . '</div>
                            <div><a href="edit_erp_invoice.php?id=' . (int) $invoice['parent_invoice_id'] . '">' . h((string) db_value("SELECT full_number FROM erp_invoices WHERE id = '" . (int) $invoice['parent_invoice_id'] . "'")) . '</a></div>
                        </div>'
                            : '') . '
                        ' . $output_supplier . '
                    </div>
                    ' . ($is_foreign
                        ? '<div class="row border-top mt-2 pt-2">
                        <div class="col-12 col-sm-4 col-lg-2 my-2">
                            <div class="form-label text-body-secondary">' . lang('Currency') . '</div>
                            <div>' . h($currency) . '</div>
                        </div>
                        <div class="col-12 col-sm-4 col-lg-3 my-2">
                            <div class="form-label text-body-secondary">' . lang('Exchange Rate') . '</div>
                            <div>' . h(erp_fx_rate_out((float) $invoice['exchange_rate'])) . ' <span class="text-body-secondary">' . h(erp_base_currency()) . ((trim((string) $invoice['exchange_rate_source']) !== '') ? ' · ' . h($invoice['exchange_rate_source']) : '') . '</span></div>
                        </div>
                        <div class="col-12 col-sm-4 col-lg-2 my-2">
                            <div class="form-label text-body-secondary">' . lang('Exchange Rate Date') . '</div>
                            <div>' . h(prepare_form_data_for_output($invoice['exchange_rate_date'], 'date')) . '</div>
                        </div>
                        <div class="col-12 col-sm-6 col-lg-3 my-2">
                            <div class="form-label text-body-secondary">' . h(lang(array('string' => 'Total in {var:1}', 'vars' => erp_base_currency()))) . '</div>
                            <div>' . h(erp_money_out((int) $invoice['grand_total_base'])) . '</div>
                        </div>
                    </div>'
                        : '') . '
                    ' . $output_internet_sale . '
                </div>
            </div>
            ' . $output_reminders . '

            <div class="card my-4">
                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                    ' . lang('Lines') . '
                </div>
                <div class="card-body p-0 position-relative">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th style="width:3rem">#</th>
                                <th>' . lang('Description') . '</th>
                                <th class="text-end">' . lang('Quantity') . '</th>
                                <th class="text-end">' . lang('Unit price') . '</th>
                                <th class="text-end">' . lang('Discount') . '</th>
                                <th class="text-end">' . lang('Taxable Amount') . '</th>
                                <th class="text-end">' . lang('Rate') . '</th>
                                <th class="text-end">' . lang('VAT') . '</th>
                            </tr>
                        </thead>
                        <tbody>' . $output_lines . '</tbody>
                    </table>
                </div>
            </div>
            ' . $output_edoc . '

            <form name="notes_form" action="edit_erp_invoice.php" method="post">
                ' . get_token_field() . '
                <input type="hidden" name="id" value="' . $invoice_id . '" />
                <input type="hidden" name="erp_action" value="notes" />
                <div class="card my-4">
                    <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                        ' . lang('Notes') . '
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-12 col-lg-9 my-2">
                                ' . $liveform->output_field(array(
                                    'type' => 'textarea', 'id' => 'notes', 'name' => 'notes',
                                    'class' => 'form-control', 'rows' => '2', 'maxlength' => '5000',
                                    'aria-label' => lang('Notes'))) . '
                                <div class="form-text">' . lang('Printed at the foot of the document. The only part of an issued invoice that may still be changed; the figures and the lines cannot.') . '</div>
                            </div>
                            <div class="col-12 col-lg-3 my-2 d-flex align-items-start">
                                <button type="submit" name="submit_notes" value="Save" class="btn btn-outline-secondary" data-loading-content="' . lang(array('string' => 'Saving')) . '"><span class="bi bi-save me-2"></span><span class="btn-text">' . lang('Save the Note') . '</span></button>
                            </div>
                        </div>
                    </div>
                </div>
            </form>

            <div class="card my-4">
                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <span>' . lang('Settlement') . '</span>
                    ' . (($open_amount > 0 && (string) $invoice['status'] !== 'cancelled')
                        ? '<a class="btn btn-sm btn-primary" href="add_erp_receipt.php?direction=' . (((string) $invoice['direction'] === 'sales') ? 'collection' : 'payment') . '&amp;invoice_id=' . $invoice_id . '"><span class="bi bi-cash-coin me-2"></span>' . lang('Record a Receipt') . '</a>'
                        : '') . '
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-12 col-sm-3 my-2">
                            <div class="form-label text-body-secondary">' . lang('Invoice Total') . '</div>
                            <div class="h5 mb-0">' . $money((int) $invoice['grand_total']) . '</div>
                            ' . ($is_foreign ? '<div class="text-body-secondary small">' . h(erp_money_out((int) $invoice['grand_total_base'])) . '</div>' : '') . '
                        </div>
                        <div class="col-12 col-sm-3 my-2">
                            <div class="form-label text-body-secondary">' . lang('Paid') . '</div>
                            <div class="h5 mb-0 text-success">' . $money((int) $invoice['paid_total']) . '</div>
                        </div>
                        <div class="col-12 col-sm-3 my-2">
                            <div class="form-label text-body-secondary">' . lang('Returned') . '</div>
                            <div class="h5 mb-0 ' . (($returned_total > 0) ? 'text-warning' : 'text-body-secondary') . '">' . $money($returned_total) . '</div>
                        </div>
                        <div class="col-12 col-sm-3 my-2">
                            <div class="form-label text-body-secondary">' . lang('Outstanding') . '</div>
                            <div class="h5 mb-0 ' . (($open_amount > 0) ? 'text-danger' : 'text-body-secondary') . '">' . $money($open_amount) . '</div>
                        </div>
                    </div>
                    ' . $output_settlements . '
                    ' . $output_returns . '
                </div>
            </div>

            ' . (($can_return || $can_cancel)
                ? '<form name="form" action="edit_erp_invoice.php" method="post">
                ' . get_token_field() . '
                <input type="hidden" name="id" value="' . $invoice_id . '" />
                <input type="hidden" name="erp_action" value="cancel" />
                <nav class="buttons navigation text-center position-sticky mb-4" style="bottom:.5rem;" aria-label="data edit buttons">
                    <div class="container">
                        <div class="btn-group flex-wrap justify-content-center">
                            ' . ($can_return
                                ? '<a class="btn my-1 btn-outline-warning" href="add_erp_return.php?invoice_id=' . $invoice_id . '"><span class="bi bi-arrow-return-left me-2"></span>' . lang('Return an Invoice') . '</a>'
                                : '') . '
                            ' . ($can_cancel
                                ? '<button type="submit" name="submit_cancel" value="Cancel" class="btn my-1 btn-outline-danger" data-loading-content="' . lang(array('string' => 'Please Wait')) . '"><span class="bi bi-x-circle me-2"></span><span class="btn-text">' . lang('Cancel the Document') . '</span></button>'
                                : '') . '
                        </div>
                    </div>
                </nav>
            </form>'
                : '') . '

            <div class="row">
                <div class="col-12 col-lg-5 ms-auto">
                    <div class="card my-4">
                        <div class="card-body">
                            <div class="d-flex justify-content-between py-1">
                                <span>' . lang('Subtotal') . '</span><b>' . $money((int) $invoice['subtotal']) . '</b>
                            </div>
                            <div class="d-flex justify-content-between py-1">
                                <span>' . lang('Discount') . '</span><b>&minus;' . $money((int) $invoice['discount_total']) . '</b>
                            </div>
                            <div class="d-flex justify-content-between py-1">
                                <span>' . lang('VAT') . '</span><b>' . $money((int) $invoice['tax_total']) . '</b>
                            </div>
                            <div class="d-flex justify-content-between py-2 border-top h5 mb-0">
                                <span>' . lang('Total') . '</span><b>' . $money((int) $invoice['grand_total']) . '</b>
                            </div>
                            ' . ($is_foreign
                                ? '<div class="d-flex justify-content-between py-1 text-body-secondary">
                                <span>' . h(lang(array('string' => 'Total in {var:1}', 'vars' => erp_base_currency()))) . '</span><b>' . h(erp_money_out((int) $invoice['grand_total_base'])) . '</b>
                            </div>'
                                : '') . '
                            ' . (((int) $invoice['gift_card_total'] > 0)
                                ? '<div class="d-flex justify-content-between py-1 text-body-secondary">
                                <span>' . lang('Settled by gift card') . '</span><b>' . $money((int) $invoice['gift_card_total']) . '</b>
                            </div>'
                                : '') . '
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>
' .
output_footer();

$liveform->remove_form();
