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

    // The draft the provider is holding, handed to the tax authority. A
    // separate action from the send: it never creates anything, so a failed
    // send can be finished here without risking a second draft.
    if (($_POST['erp_action'] ?? '') === 'edoc_submit') {

        $result = erp_edoc_invoice_submit($invoice_id, (int) $user['id']);

        if ($result['success']) {
            $liveform->add_notice($result['message']);
        } else {
            $liveform->mark_error('_error', $result['error']);
        }

        go(OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/edit_erp_invoice.php?id=' . $invoice_id);
    }

    // The buyer completed where the refusal was read. Writes the account
    // card and this document's copy together and sends nothing: the
    // operator presses send themselves once they can see it will work.
    // Moved to today before it goes: GİB takes documents of one type only in
    // date order, and an unsent invoice is not official yet.
    if (($_POST['erp_action'] ?? '') === 'edoc_redate') {

        $result = erp_edoc_invoice_redate($invoice_id, (int) $user['id']);

        if ($result['success']) {
            $liveform->add_notice(h($result['message']));
        } else {
            $liveform->mark_error('_error', h($result['error']));
        }

        go(OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/edit_erp_invoice.php?id=' . $invoice_id . '#edoc_date');
    }

    // The same move, and the send straight after it: once the provider has
    // refused the invoice for its date, sending it unchanged is certain to
    // be refused again, so the screen offers the two as one step.
    if (($_POST['erp_action'] ?? '') === 'edoc_redate_send') {

        $moved = erp_edoc_invoice_redate($invoice_id, (int) $user['id']);

        if (!$moved['success']) {
            $liveform->mark_error('_error', h($moved['error']));
            go(OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/edit_erp_invoice.php?id=' . $invoice_id . '#edoc_date');
        }

        $liveform->add_notice(h($moved['message']));
        $result = erp_edoc_invoice_send($invoice_id, (int) $user['id']);

        if ($result['success']) {
            $liveform->add_notice($result['message']);
        } else {
            $liveform->mark_error('_error', $result['error']);
        }

        go(OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/edit_erp_invoice.php?id=' . $invoice_id);
    }

    if (($_POST['erp_action'] ?? '') === 'edoc_fix') {

        $result = erp_edoc_invoice_party_fix($invoice_id, array(
            'title' => (string) ($_POST['fix_title'] ?? ''),
            'tax_number' => (string) ($_POST['fix_tax_number'] ?? ''),
            'address' => (string) ($_POST['fix_address'] ?? ''),
            'postcode' => (string) ($_POST['fix_postcode'] ?? ''),
            'city' => (string) ($_POST['fix_city'] ?? ''),
            'district' => (string) ($_POST['fix_district'] ?? ''),
            'carrier_title' => (string) ($_POST['fix_carrier_title'] ?? ''),
            'carrier_vkn' => (string) ($_POST['fix_carrier_vkn'] ?? ''),
        ), (int) $user['id']);

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

        // A document the provider has is taken back there first, the way the
        // driver says (erp_edoc_invoice_cancellable()); a refusal leaves both
        // sides as they were.
        $provider_cancel = erp_edoc_invoice_provider_cancel($invoice_id, '', (int) $user['id']);

        if (!$provider_cancel['success']) {
            $liveform->mark_error('_error', $provider_cancel['error']);
            go(OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/edit_erp_invoice.php?id=' . $invoice_id);
        }

        if ((string) $provider_cancel['message'] !== '') {
            $liveform->add_notice($provider_cancel['message']);
        }

        $result = erp_invoice_cancel($invoice_id, (int) $user['id']);

        if ($result['success']) {
            log_activity(lang(array('string' => 'erp document ({var:1}) was cancelled', 'vars' => $invoice['full_number'])), $_SESSION['sessionusername']);
            $liveform->add_notice(lang('The document has been cancelled.'));
        } else {
            $liveform->mark_error('_error', $result['error']);
        }

        go(OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/edit_erp_invoice.php?id=' . $invoice_id);
    }

    // Repeating the invoice: started here with how often, from when, until
    // when and how; stopped here too (includes/erp/invoice_recurring.php).
    if (($_POST['erp_action'] ?? '') === 'recur_start') {

        $started = erp_invoice_recurrence_create($invoice_id, array(
            'every_months' => (int) ($_POST['recur_every'] ?? 1),
            'first_date' => (string) ($_POST['recur_first'] ?? ''),
            'end_date' => trim((string) ($_POST['recur_end'] ?? '')),
            'mode' => (string) ($_POST['recur_mode'] ?? 'draft'),
        ), (int) $user['id']);

        if ($started['success']) {
            log_activity(lang(array('string' => 'erp invoice ({var:1}) was set to repeat', 'vars' => $invoice['full_number'])), $_SESSION['sessionusername']);
            $liveform->add_notice(lang('The invoice will repeat.'));
        } else {
            $liveform->mark_error('_error', h($started['error']));
        }

        go(OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/edit_erp_invoice.php?id=' . $invoice_id . '#erp-recurring');
    }

    if (($_POST['erp_action'] ?? '') === 'recur_stop') {

        if (erp_invoice_recurrence_stop((int) ($_POST['recurrence_id'] ?? 0), (int) $user['id'])) {
            log_activity(lang(array('string' => 'erp invoice ({var:1}) was stopped repeating', 'vars' => $invoice['full_number'])), $_SESSION['sessionusername']);
            $liveform->add_notice(lang('The invoice no longer repeats. The invoices it wrote stay.'));
        }

        go(OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/edit_erp_invoice.php?id=' . $invoice_id . '#erp-recurring');
    }

    if (($_POST['erp_action'] ?? '') === 'mail') {

        $sent = erp_mail_invoice_send($invoice_id, (string) ($_POST['mail_to'] ?? ''), (string) ($_POST['mail_subject'] ?? ''),
            (string) ($_POST['mail_message'] ?? ''), (int) $user['id']);

        if ($sent['success']) {
            $liveform->add_notice($sent['official']
                ? lang('The invoice was e-mailed, with the official copy of the e-document.')
                : lang('The invoice was e-mailed.'));
        } else {
            $liveform->mark_error('mail_to', h($sent['error']));
            $_SESSION['software']['erp_mail_draft'][$invoice_id] = array(
                'to' => (string) ($_POST['mail_to'] ?? ''),
                'subject' => (string) ($_POST['mail_subject'] ?? ''),
                'message' => (string) ($_POST['mail_message'] ?? ''),
            );
        }

        go(OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/edit_erp_invoice.php?id=' . $invoice_id . '#erp-mail');
    }
}

$lines = (array) db_items("SELECT i.*, p.image_name
    FROM erp_invoice_items i
    LEFT JOIN products p ON p.id = i.product_id
    WHERE i.invoice_id = '" . $invoice_id . "' ORDER BY i.line_no ASC, i.id ASC");

// A picture per line, when the store shows pictures in tables at all. The
// column disappears entirely when it does not, rather than standing there
// full of placeholders.
$output_line_thumbs = erp_product_thumbs_on();
$output_line_columns = $output_line_thumbs ? 9 : 8;

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

// What the provider allows decides the button's words and whether it is
// there at all: an e-Invoice that has gone is answered with a return.
$provider_cancellable = $can_cancel ? erp_edoc_invoice_cancellable($invoice) : array('ok' => true, 'manual' => false, 'reason' => '', 'url' => '');
$can_cancel = $can_cancel && !empty($provider_cancellable['ok']);
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
    $discount = (int) $line['discount_amount'];

    // How the discount was made up: the store's campaign on the product and
    // the rate typed on top of it, when either is on the line.
    $discount_parts = array();
    if ((float) ($line['offer_discount_rate'] ?? 0) > 0) {
        $discount_parts[] = lang('Campaign') . ' ' . erp_percent_text($line['offer_discount_rate']);
    }
    if ((float) ($line['discount_rate'] ?? 0) > 0) {
        $discount_parts[] = erp_percent_text($line['discount_rate']);
    }
    $output_discount_note = (($discount > 0) && (count($discount_parts) > 0))
        ? '<div class="small text-body-secondary text-nowrap">' . h(implode(' + ', $discount_parts)) . '</div>'
        : '';

    $output_lines .= '
        <tr>
            <td class="align-middle text-body-secondary">' . (int) $line['line_no'] . '</td>
            ' . ($output_line_thumbs
                ? '<td class="align-middle" style="width:3.5rem">' . erp_product_thumb((string) ($line['image_name'] ?? ''), 40) . '</td>'
                : '') . '
            <td class="align-middle">' . h($line['description'])
                . ((trim((string) ($line['withholding_code'] ?? '')) !== '')
                    ? '<div class="small text-body-secondary">' . h(lang(array('string' => 'VAT withholding {var:1}: {var:2}', 'vars' => array(
                        erp_withholding_label((string) $line['withholding_code'], (float) $line['withholding_rate'], false),
                        erp_money_out_currency((int) ($line['withholding_amount'] ?? 0), $currency))))) . '</div>'
                    : '') . '</td>
            <td class="align-middle text-end" data-sort="' . (float) $line['quantity'] . '">' . h(erp_quantity_text($line['quantity'])) . '</td>
            <td class="align-middle text-end" data-sort="' . (int) $line['unit_price'] . '">' . $money((int) $line['unit_price']) . '</td>
            <td class="align-middle text-end" data-sort="' . (int) $discount . '">' . (($discount > 0) ? '&minus;' . $money($discount) : '') . $output_discount_note . '</td>
            <td class="align-middle text-end" data-sort="' . ((int) $line['line_total'] - $discount) . '">' . $money((int) $line['line_total'] - $discount) . '</td>
            <td class="align-middle text-end text-nowrap" data-sort="' . ((float) $line['tax_rate'] + (float) ($line['tax2_rate'] ?? 0)) . '">' . h(erp_percent_text($line['tax_rate']) . (((float) ($line['tax2_rate'] ?? 0) > 0) ? (' + ' . erp_percent_text($line['tax2_rate'])) : '')) . '</td>
            <td class="align-middle text-end" data-sort="' . (int) $line['tax_total'] . '">' . $money((int) $line['tax_total']) . '</td>
        </tr>';
}

if ($output_lines === '') {
    $output_lines = '<tr data-pg-sort-fixed><td colspan="' . $output_line_columns . '" class="text-center text-body-secondary py-4">' . lang('This invoice has no lines.') . '</td></tr>';
}

// What the document did to stock and cost (includes/erp/stock.php). A move
// still waiting to be counted - its request ended before the count changed -
// is counted now, so the card shows the count as it is.
$output_stock = '';

if (function_exists('erp_stock_ready') && erp_stock_ready() && ((string) $invoice['status'] !== 'draft')) {
    erp_stock_apply_pending();

    $stock_moves = erp_stock_document_moves($invoice_id);
    $stock_kinds = erp_stock_kinds();
    $stock_rows = '';

    foreach ($stock_moves as $move) {
        $product_name = (trim((string) $move['product_name']) !== '') ? (string) $move['product_name'] : (string) $move['product_sku'];

        $stock_rows .= '
                        <tr>
                            <td class="align-middle">' . h($product_name) . ((trim((string) $move['product_sku']) !== '') ? '<div class="small text-body-secondary font-monospace">' . h($move['product_sku']) . '</div>' : '') . '</td>
                            <td class="align-middle">' . h($stock_kinds[(string) $move['kind']] ?? (string) $move['kind']) . '</td>
                            <td class="align-middle">' . h(erp_stock_effect_text($move)) . '</td>
                            <td class="align-middle text-end text-nowrap" data-sort="' . (int) $move['unit_cost'] . '">' . h(erp_money_out((int) $move['unit_cost'])) . '</td>
                            <td class="align-middle text-end text-nowrap" data-sort="' . (int) $move['cost_total'] . '">' . h(erp_money_out((int) $move['cost_total'])) . '</td>
                        </tr>';
    }

    $stock_note = '';

    if ($stock_rows === '') {
        if (((string) $invoice['direction'] === 'sales') && ((int) $invoice['order_id'] > 0)) {
            $stock_note = lang('Raised from an order: the order took the goods out of stock, so this document does not move it.');
        } elseif ((int) db_value("SELECT COUNT(*) FROM erp_invoice_items WHERE invoice_id = '" . $invoice_id . "' AND product_id > 0") === 0) {
            $stock_note = lang('No line is linked to a product, so stock and cost are unchanged.');
        } else {
            $stock_note = lang('This document was issued before stock was kept from documents; it moved nothing.');
        }
    }

    $output_stock = '
            <div class="card my-4">
                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <span>' . lang('Stock') . '</span>
                    <a class="btn btn-sm btn-outline-secondary m-1" href="erp_stock.php"><i class="bi bi-boxes me-2" aria-hidden="true"></i>' . lang('Stock and cost') . '</a>
                </div>' . (($stock_rows !== '') ? '
                <div class="card-body p-0 table-responsive">
                    <table class="table table-hover align-middle mb-0" data-pg-sort>
                        <thead>
                            <tr>
                                <th>' . lang('Product') . '</th>
                                <th>' . lang('Movement') . '</th>
                                <th>' . lang('Stock') . '</th>
                                <th class="text-end">' . lang('Unit cost') . '</th>
                                <th class="text-end">' . lang('Cost') . '</th>
                            </tr>
                        </thead>
                        <tbody>' . $stock_rows . '</tbody>
                    </table>
                </div>' : '
                <div class="card-body pt-0 small text-body-secondary">' . h($stock_note) . '</div>') . '
            </div>';
}

// The money behind the paid figure. Shown even when it is one line, because
// "paid" without saying which receipt paid it is the thing nobody can reconcile.
$output_settlements = '';

if (!empty($settlements)) {
    $output_settlements = '
                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle mb-0 mt-3" data-pg-sort>
                            <thead>
                                <tr>
                                    <th>' . lang('Date') . '</th>
                                    <th>' . lang('Description') . '</th>
                                    <th>' . lang('Till or Bank Account') . '</th>
                                    <th class="text-end">' . lang('Amount') . '</th>
                                    <th class="text-end" data-pg-sort="none">' . lang(array('string' => 'Action')) . '</th>
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
                                    <td class="text-nowrap" data-sort="' . h(str_replace('-', '', (string) $settlement['doc_date'])) . '">' . h(prepare_form_data_for_output($settlement['doc_date'], 'date')) . '</td>
                                    <td>' . h($settlement['description']) . '</td>
                                    <td>' . h($settlement['cash_account_name']) . '</td>
                                    <td class="text-end" data-sort="' . (int) $settlement['amount'] . '">' . $money((int) $settlement['amount'])
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
                        <table class="table table-sm table-hover align-middle mb-0 mt-3" data-pg-sort>
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
                                    <td class="text-nowrap" data-sort="' . h(str_replace('-', '', (string) $return['issue_date'])) . '">' . h(prepare_form_data_for_output($return['issue_date'], 'date')) . '</td>
                                    <td><a href="edit_erp_invoice.php?id=' . (int) $return['id'] . '">' . h($return['full_number']) . '</a></td>
                                    <td class="text-end" data-sort="' . (int) $return['grand_total'] . '">' . $money((int) $return['grand_total']) . '</td>
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
                        <div class="col-6 col-sm-4 col-lg-12 my-2">
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
                        <div class="col-12 col-sm-6 col-lg-12 my-1">
                            <div class="form-label text-body-secondary">' . lang('Payment Method') . '</div>
                            <div>' . $output_payment_method . '</div>
                        </div>
                        <div class="col-12 col-sm-6 col-lg-12 my-1">
                            <div class="form-label text-body-secondary">' . lang('Payment Date') . '</div>
                            <div>' . $date_out($invoice['payment_date'] ?? '') . '</div>
                        </div>
                        <div class="col-12 col-sm-6 col-lg-12 my-1">
                            <div class="form-label text-body-secondary">' . lang('Shipment Date') . '</div>
                            <div>' . $date_out($invoice['shipment_date'] ?? '') . '</div>
                        </div>
                        <div class="col-12 col-sm-6 col-lg-12 my-1">
                            <div class="form-label text-body-secondary">' . lang('Carrier') . '</div>
                            <div>' . $output_carrier . '</div>
                        </div>
                        <div class="col-12 col-sm-6 col-lg-12 my-1">
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
                                <button type="submit" name="submit_snooze" value="Snooze" class="btn btn-outline-secondary w-100" data-loading-content="' . lang(array('string' => 'Saving')) . '"><i class="bi bi-bell-slash me-2" aria-hidden="true"></i><span class="btn-text">' . lang('Put off') . '</span></button>
                            </div>
                        </form>'
            . (($reminder['phase'] === 'snoozed')
                ? '
                        <form name="unsnooze_form" action="edit_erp_invoice.php" method="post" class="mt-2">
                            ' . get_token_field() . '
                            <input type="hidden" name="id" value="' . $invoice_id . '" />
                            <input type="hidden" name="erp_action" value="unsnooze" />
                            <button type="submit" name="submit_unsnooze" value="Resume" class="btn btn-link link-body-emphasis text-decoration-none p-0" data-loading-content="' . lang(array('string' => 'Saving')) . '"><i class="bi bi-bell me-2" aria-hidden="true"></i><span class="btn-text">' . lang('Let the reminders resume') . '</span></button>
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

// A purchase invoice is the supplier's document: nothing is sent, so the
// sending card is for sales only. One taken in from an incoming e-invoice
// gets the card below instead.
if (((string) $invoice['direction'] === 'sales')
    && (($edoc_active !== '') || ($edoc_provider !== '') || ((string) ($invoice['edoc_status'] ?? 'none') !== 'none'))) {
    $edoc_labels = erp_edoc_status_labels();
    $edoc_status = (string) ($invoice['edoc_status'] ?? 'none');
    $edoc_label = $edoc_labels[$edoc_status] ?? array($edoc_status, 'secondary');
    $edoc_gate = erp_edoc_invoice_can_send($invoice);
    $edoc_carrier = ($edoc_provider !== '') ? $edoc_provider : $edoc_active;
    $edoc_carrier_info = ($edoc_carrier !== '') ? erp_edoc_info($edoc_carrier) : null;
    $edoc_carrier_label = is_array($edoc_carrier_info) ? (string) $edoc_carrier_info['label'] : $edoc_carrier;
    $edoc_environment = ($edoc_carrier !== '') ? erp_edoc_environment($edoc_carrier) : array('is_test' => false, 'label' => '');
    $edoc_test_badge = !empty($edoc_environment['is_test'])
        ? ' <span class="badge text-bg-warning">' . h((string) $edoc_environment['label']) . '</span>'
        : '';
    $empty = '<span class="text-body-secondary">&mdash;</span>';

    $output_edoc_buttons = '';

    // The date, when the provider's ordering rule will refuse it or already
    // has. When the provider itself refused this date, sending it unchanged
    // is refused again: the send is then offered only together with moving
    // the date, on the date box below.
    $edoc_date_latest = erp_edoc_invoice_date_behind($invoice);
    $edoc_refusal = erp_edoc_invoice_date_refusal($invoice);
    $edoc_date_refused = $edoc_refusal['refused'];
    $edoc_date_blocked = $edoc_date_refused && ($edoc_refusal['latest'] !== '') && ($edoc_date_latest !== '');

    // Sending a document the provider is certain to refuse spends a call and
    // leaves a refusal to read; while boxes are missing, the button points
    // at them instead.
    $edoc_send_gaps = array_merge((array) erp_edoc_invoice_party_missing($invoice)['fields'], (array) erp_edoc_invoice_document_missing($invoice)['fields']);

    if ($edoc_gate['ok'] && !empty($edoc_send_gaps) && erp_edoc_invoice_party_editable($invoice)) {
        $output_edoc_buttons .= '<a class="btn btn-sm btn-outline-warning m-1" href="#edoc_fix"><i class="bi bi-pencil-square me-2" aria-hidden="true"></i>' . lang('Complete the missing fields') . '</a>';
    } elseif ($edoc_gate['ok'] && $edoc_date_blocked) {
        $output_edoc_buttons .= '<a class="btn btn-sm btn-outline-warning m-1" href="#edoc_date"><i class="bi bi-calendar-x me-2" aria-hidden="true"></i>' . lang('Fix the date') . '</a>';
    } elseif ($edoc_gate['ok']) {
        $output_edoc_buttons .= '<form method="post" action="edit_erp_invoice.php" class="d-inline">
                        ' . get_token_field() . '
                        <input type="hidden" name="id" value="' . $invoice_id . '" />
                        <input type="hidden" name="erp_action" value="edoc_send" />
                        <button type="submit" class="btn btn-sm btn-primary m-1" data-loading-content="' . lang(array('string' => 'Sending')) . '"><i class="bi bi-send me-2" aria-hidden="true"></i><span class="btn-text">' . h(lang(array('string' => 'Send to {var:1}', 'vars' => $edoc_carrier_label))) . '</span></button>
                    </form>';
    }

    // Only while the document is still a draft there: once it has gone, the
    // button goes with it, so nothing is handed over twice.
    if (($edoc_status === 'created') && ($edoc_provider !== '') && ((string) $invoice['edoc_external_id'] !== '') && erp_edoc_supports('submit_invoice', $edoc_provider)) {
        $output_edoc_buttons .= '<form method="post" action="edit_erp_invoice.php" class="d-inline">
                        ' . get_token_field() . '
                        <input type="hidden" name="id" value="' . $invoice_id . '" />
                        <input type="hidden" name="erp_action" value="edoc_submit" />
                        <button type="submit" class="btn btn-sm btn-primary m-1" data-loading-content="' . lang(array('string' => 'Sending')) . '"><i class="bi bi-bank me-2" aria-hidden="true"></i><span class="btn-text">' . lang('Hand to GİB') . '</span></button>
                    </form>';
    }

    if (($edoc_provider !== '') && ((string) $invoice['edoc_external_id'] !== '') && erp_edoc_supports('poll', $edoc_provider)) {
        $output_edoc_buttons .= '<form method="post" action="edit_erp_invoice.php" class="d-inline">
                        ' . get_token_field() . '
                        <input type="hidden" name="id" value="' . $invoice_id . '" />
                        <input type="hidden" name="erp_action" value="edoc_poll" />
                        <button type="submit" class="btn btn-sm btn-outline-secondary m-1" data-loading-content="' . lang(array('string' => 'Asking')) . '"><i class="bi bi-arrow-repeat me-2" aria-hidden="true"></i><span class="btn-text">' . lang('Ask After the Status') . '</span></button>
                    </form>';
    }

    if (($edoc_provider !== '') && erp_edoc_supports('fetch_document', $edoc_provider)) {
        if ((string) $invoice['gib_uuid'] !== '') {
            $output_edoc_buttons .= '<a class="btn btn-sm btn-outline-secondary m-1" href="get_erp_edoc_document.php?id=' . $invoice_id . '&amp;format=pdf" target="_blank" rel="noopener"><i class="bi bi-file-earmark-text me-2"></i>' . h(lang(array('string' => 'The document at {var:1}', 'vars' => $edoc_carrier_label))) . '</a>';
        }

        if ((string) $invoice['edoc_external_id'] !== '') {
            $output_edoc_buttons .= '<a class="btn btn-sm btn-outline-secondary m-1" href="get_erp_edoc_document.php?id=' . $invoice_id . '&amp;format=xml"><i class="bi bi-filetype-xml me-2"></i>' . lang('UBL') . '</a>';
        }
    }

    // The refusal is read on this card, so the repair belongs on it: the
    // fields the provider is missing, as boxes. Offered only while nothing
    // has gone - after that the copy is evidence and is not edited.
    // Two kinds of gap, one form: the buyer's fields, which also go on the
    // account card, and the document's own - the carrier an internet sale
    // has to name, which belongs to this shipment alone.
    // The date, when the provider's ordering rule will refuse it or already
    // has: said with the dates side by side, and the one repair that exists
    // while the invoice has not gone - moving it to today.
    $output_edoc_date = '';

    if (($edoc_date_latest !== '') || ($edoc_date_refused && erp_edoc_invoice_party_editable($invoice))) {
        $edoc_redate = erp_edoc_invoice_redate_allowed($invoice);
        $edoc_date_text = $edoc_date_blocked
            ? lang(array('string' => '{var:1} refused the invoice for its date: the latest document of the same type there is dated {var:2}, and this one {var:3}. GİB takes them only in date order.', 'vars' => array(
                $edoc_carrier_label,
                prepare_form_data_for_output($edoc_refusal['latest'], 'date', false) . (($edoc_refusal['latest_time'] !== '') ? ' ' . $edoc_refusal['latest_time'] : ''),
                prepare_form_data_for_output((string) $invoice['issue_date'], 'date', false))))
            : (($edoc_date_latest !== '')
                ? lang(array('string' => 'The invoice is dated {var:1}, but {var:2} already has documents dated {var:3}. GİB takes documents of one type only in date order, so it will refuse this one.', 'vars' => array(prepare_form_data_for_output((string) $invoice['issue_date'], 'date', false), $edoc_carrier_label, prepare_form_data_for_output($edoc_date_latest, 'date', false))))
                : lang(array('string' => '{var:1} refused the invoice for its date: a document of the same type dated later has already been issued. GİB takes them only in date order.', 'vars' => $edoc_carrier_label)));
        $output_edoc_date = '
                    <div class="border-top mt-3 pt-3" id="edoc_date">
                        <div class="alert alert-warning d-flex align-items-start gap-2 mb-2" role="alert">
                            <i class="bi bi-calendar-x"></i>
                            <div>' . h($edoc_date_text) . '
                                <div class="small">' . h($edoc_redate['ok']
                                    ? lang('The invoice has not gone to the provider, so it is not official yet: move it to today and send it again. Its due date moves by the same number of days.')
                                    : $edoc_redate['reason']) . '</div>
                            </div>
                        </div>
                        ' . ($edoc_redate['ok'] ? '<div class="d-flex flex-wrap gap-2">
                        ' . ($edoc_gate['ok'] && empty($edoc_send_gaps) ? '<form method="post" action="edit_erp_invoice.php">
                            ' . get_token_field() . '
                            <input type="hidden" name="id" value="' . $invoice_id . '" />
                            <input type="hidden" name="erp_action" value="edoc_redate_send" />
                            <button type="submit" class="btn btn-sm btn-primary" data-confirm-content="' . h(lang(array('string' => 'Date the invoice {var:1} instead of {var:2} and send it to {var:3}?', 'vars' => array(prepare_form_data_for_output(date('Y-m-d'), 'date', false), prepare_form_data_for_output((string) $invoice['issue_date'], 'date', false), $edoc_carrier_label)))) . '" data-loading-content="' . lang(array('string' => 'Sending')) . '"><i class="bi bi-send-check me-2" aria-hidden="true"></i><span class="btn-text">' . h(lang(array('string' => 'Move to today ({var:1}) and send', 'vars' => prepare_form_data_for_output(date('Y-m-d'), 'date', false)))) . '</span></button>
                        </form>' : '') . '
                        <form method="post" action="edit_erp_invoice.php">
                            ' . get_token_field() . '
                            <input type="hidden" name="id" value="' . $invoice_id . '" />
                            <input type="hidden" name="erp_action" value="edoc_redate" />
                            <button type="submit" class="btn btn-sm ' . ($edoc_gate['ok'] && empty($edoc_send_gaps) ? 'btn-outline-secondary' : 'btn-primary') . '" data-confirm-content="' . h(lang(array('string' => 'Date the invoice {var:1} instead of {var:2}?', 'vars' => array(prepare_form_data_for_output(date('Y-m-d'), 'date', false), prepare_form_data_for_output((string) $invoice['issue_date'], 'date', false))))) . '" data-loading-content="' . lang(array('string' => 'Saving')) . '"><i class="bi bi-calendar-check me-2" aria-hidden="true"></i><span class="btn-text">' . h(lang(array('string' => 'Move to today ({var:1})', 'vars' => prepare_form_data_for_output(date('Y-m-d'), 'date', false)))) . '</span></button>
                        </form>
                        </div>' : '') . '
                    </div>';
    }

    $output_edoc_fix = '';
    $edoc_missing = erp_edoc_invoice_party_missing($invoice);
    $edoc_doc_missing = erp_edoc_invoice_document_missing($invoice);

    if (!empty($edoc_doc_missing['fields'])) {
        $edoc_missing['label'] = ($edoc_missing['label'] !== '') ? $edoc_missing['label'] : $edoc_doc_missing['label'];
        $edoc_missing['fields'] = array_merge((array) $edoc_missing['fields'], $edoc_doc_missing['fields']);
    }

    if (!empty($edoc_missing['fields']) && erp_edoc_invoice_party_editable($invoice)) {
        // Offered filled in wherever something is known: the document's own
        // copy first, then the account card. The common case is a card that
        // was completed after the invoice was raised - then the boxes come
        // up already right and saving is one press, which copies the card
        // onto the document.
        $edoc_fix_party = erp_edoc_invoice_party($invoice);
        $edoc_fix_card = ((int) $invoice['account_id'] > 0)
            ? (array) db_item("SELECT title, tax_number, address, postcode, city, district FROM erp_accounts WHERE id = '" . (int) $invoice['account_id'] . "' LIMIT 1")
            : array();
        // Last, the order's billing address: an account opened from a
        // contact carries the contact's business address, which a web
        // customer often never filled in, while the order has the address
        // the customer typed at checkout. Offered, not saved, until the
        // operator presses the button.
        $edoc_fix_order = ((int) ($invoice['order_id'] ?? 0) > 0)
            ? (array) db_item("SELECT billing_address_1 AS address, billing_zip_code AS postcode, billing_state AS city, billing_city AS district
                FROM orders WHERE id = '" . (int) $invoice['order_id'] . "' LIMIT 1")
            : array();
        // The document's own fields answer for themselves.
        $edoc_fix_party['carrier_title'] = (string) ($invoice['carrier_title'] ?? '');
        $edoc_fix_party['carrier_vkn'] = (string) ($invoice['carrier_vkn'] ?? '');
        $edoc_fix_names = erp_edoc_fix_fields();

        // One box: its short name, the value known so far, and - under it -
        // the provider's reason when the reason says more than the name.
        $edoc_fix_box = function ($name, $reason, $width = 'col-12 col-sm-6 col-xl-4') use ($edoc_fix_names, $edoc_fix_party, $edoc_fix_card, $edoc_fix_order) {
            $label = isset($edoc_fix_names[$name]) ? $edoc_fix_names[$name]['label'] : $reason;
            $value = '';
            foreach (array($edoc_fix_party, $edoc_fix_card, $edoc_fix_order) as $source) {
                if (trim((string) ($source[$name] ?? '')) !== '') {
                    $value = (string) $source[$name];
                    break;
                }
            }

            return '
                                <div class="' . $width . ' my-1">
                                    <label class="form-label" for="fix_' . h($name) . '">' . h($label) . '</label>
                                    <input type="text" class="form-control form-control-sm' . (($reason !== '') ? ' is-invalid' : '') . '" id="fix_' . h($name) . '" name="fix_' . h($name) . '" value="' . h($value) . '" autocomplete="off" />
                                    ' . ((($reason !== '') && ($reason !== $label)) ? '<div class="invalid-feedback d-block">' . h($reason) . '</div>' : '') . '
                                </div>';
        };

        $edoc_fix_buyer = '';
        $edoc_fix_carrier_missing = array();

        foreach ($edoc_missing['fields'] as $edoc_fix_name => $edoc_fix_label) {
            if ((isset($edoc_fix_names[$edoc_fix_name]) ? $edoc_fix_names[$edoc_fix_name]['group'] : 'buyer') === 'carrier') {
                $edoc_fix_carrier_missing[$edoc_fix_name] = $edoc_fix_label;
            } else {
                $edoc_fix_buyer .= $edoc_fix_box($edoc_fix_name, $edoc_fix_label);
            }
        }

        $output_edoc_fix_fields = '';

        if ($edoc_fix_buyer !== '') {
            $output_edoc_fix_fields .= '
                                <div class="col-12 mt-1"><div class="fw-semibold">' . lang('Buyer') . '</div><div class="small text-body-secondary">' . lang('Saved here, it goes on the account card as well, so the next invoice has it too.') . '</div></div>' . $edoc_fix_buyer;
        }

        // The carrier comes as a pair, whichever half is missing: whether a
        // name has to be a person's depends on the number, so asking for
        // one without showing the other is how an operator gets stuck with
        // a company name and a TCKN. Known carriers fill both at once.
        if (!empty($edoc_fix_carrier_missing)) {
            $edoc_fix_carriers = erp_edoc_known_carriers();
            $edoc_fix_pick = '';

            if (!empty($edoc_fix_carriers)) {
                $edoc_fix_options = '<option value="">' . h(lang('Pick a carrier you used before')) . '</option>';
                foreach ($edoc_fix_carriers as $edoc_fix_carrier) {
                    $edoc_fix_options .= '<option value="' . h($edoc_fix_carrier['vkn']) . '" data-title="' . h($edoc_fix_carrier['title']) . '">' . h($edoc_fix_carrier['title'] . ' - ' . $edoc_fix_carrier['vkn']) . '</option>';
                }
                $edoc_fix_pick = '
                                <div class="col-12 col-xl-4 my-1">
                                    <label class="form-label" for="fix_carrier_pick">' . lang('Known carriers') . '</label>
                                    <select class="form-select form-select-sm" id="fix_carrier_pick" onchange="if (this.value) { document.getElementById(\'fix_carrier_title\').value = this.options[this.selectedIndex].getAttribute(\'data-title\'); document.getElementById(\'fix_carrier_vkn\').value = this.value; }">' . $edoc_fix_options . '</select>
                                </div>';
            }

            // The shipping method is where a carrier is written once for
            // every order that ships with it.
            $edoc_fix_method = ((int) $invoice['order_id'] > 0)
                ? db_item("SELECT shipping_methods.id, shipping_methods.name, shipping_methods.carrier_vkn
                    FROM ship_tos
                    INNER JOIN shipping_methods ON shipping_methods.id = ship_tos.shipping_method_id
                    WHERE ship_tos.order_id = '" . (int) $invoice['order_id'] . "' ORDER BY ship_tos.id ASC LIMIT 1")
                : null;
            $edoc_fix_method_note = (is_array($edoc_fix_method) && (trim((string) $edoc_fix_method['carrier_vkn']) === ''))
                ? ' <a href="edit_shipping_method.php?id=' . (int) $edoc_fix_method['id'] . '" class="link-body-emphasis">' . h(lang(array('string' => 'Write the carrier on the "{var:1}" shipping method once and the next orders bring it along.', 'vars' => (string) $edoc_fix_method['name']))) . '</a>'
                : '';

            $output_edoc_fix_fields .= '
                                <div class="col-12 mt-3"><div class="fw-semibold">' . lang('Carrier') . '</div><div class="small text-body-secondary">' . lang('A cargo company: its title and its 10-digit VKN. A courier or a person: first name, surname and the 11-digit TCKN. Saved on this invoice only.') . $edoc_fix_method_note . '</div></div>'
                . $edoc_fix_pick
                . $edoc_fix_box('carrier_title', (string) ($edoc_fix_carrier_missing['carrier_title'] ?? ''))
                . $edoc_fix_box('carrier_vkn', (string) ($edoc_fix_carrier_missing['carrier_vkn'] ?? ''));
        }

        $output_edoc_fix = '
                    <div class="border-top mt-3 pt-3" id="edoc_fix">
                        <div class="alert alert-warning d-flex align-items-start gap-2 mb-3" role="alert">
                            <i class="bi bi-exclamation-triangle-fill"></i>
                            <div>' . h(lang(array('string' => '{var:1} will not take this document until these are filled in.', 'vars' => $edoc_missing['label']))) . '</div>
                        </div>
                        <form method="post" action="edit_erp_invoice.php">
                            ' . get_token_field() . '
                            <input type="hidden" name="id" value="' . $invoice_id . '" />
                            <input type="hidden" name="erp_action" value="edoc_fix" />
                            <div class="row">' . $output_edoc_fix_fields . '
                                <div class="col-12 mt-2">
                                    <button type="submit" class="btn btn-sm btn-primary" data-loading-content="' . lang(array('string' => 'Saving')) . '"><i class="bi bi-check-circle me-2" aria-hidden="true"></i><span class="btn-text">' . lang('Complete and save') . '</span></button>
                                </div>
                            </div>
                        </form>
                    </div>';
    }

    $output_edoc_log = '';

    foreach (erp_edoc_log_for('invoice', $invoice_id, 8) as $log_row) {
        $output_edoc_log .= '<tr>
                                <td class="text-nowrap text-body-secondary small" data-sort="' . (int) $log_row['created_at'] . '">' . h(date('d.m.Y H:i:s', (int) $log_row['created_at'])) . '</td>
                                <td class="small"><code>' . h($log_row['method'] . ' ' . $log_row['path']) . '</code></td>
                                <td class="text-end small">' . (((int) $log_row['http_code'] >= 200 && (int) $log_row['http_code'] < 300) ? '<span class="text-success">' : '<span class="text-danger">') . (int) $log_row['http_code'] . '</span></td>
                                <td class="small text-break">' . h(mb_substr((string) $log_row['response_excerpt'], 0, 200)) . '</td>
                            </tr>';
    }

    $output_edoc = '
            <div class="card my-4">
                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <span>' . lang('e-Document') . $edoc_test_badge . '</span>
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
                    ' . $output_edoc_date . '
                    ' . $output_edoc_fix . '
                    ' . (($output_edoc_log !== '')
                        ? '<div class="table-responsive border-top mt-2 pt-2">
                        <table class="table table-sm mb-0" data-pg-sort>
                            <thead><tr><th>' . lang('Time') . '</th><th>' . lang('Call') . '</th><th class="text-end">' . lang('HTTP') . '</th><th>' . lang('Answer') . '</th></tr></thead>
                            <tbody>' . $output_edoc_log . '</tbody>
                        </table>
                    </div>'
                        : '') . '
                </div>
            </div>';
} elseif (((string) $invoice['direction'] === 'purchase') && ((string) $invoice['gib_uuid'] !== '')) {
    // The e-invoice the supplier sent, when the invoice came from one (or was
    // linked to one): its GİB number and ETTN, and the way back to it.
    $inbox_id = erp_edoc_inbox_installed()
        ? (int) db_value("SELECT id FROM erp_edoc_inbox WHERE invoice_id = '" . $invoice_id . "' OR gib_uuid = '" . escape((string) $invoice['gib_uuid']) . "' ORDER BY (invoice_id = '" . $invoice_id . "') DESC, id ASC LIMIT 1")
        : 0;
    $empty = '<span class="text-body-secondary">&mdash;</span>';

    $output_edoc = '
            <div class="card my-4">
                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <span>' . lang('Incoming e-invoice') . '</span>
                    ' . (($inbox_id > 0)
                        ? '<span>
                        <a class="btn btn-sm btn-outline-secondary m-1" href="erp_inbox_document.php?id=' . $inbox_id . '"><i class="bi bi-inbox me-2" aria-hidden="true"></i>' . lang('The e-invoice') . '</a>
                        <a class="btn btn-sm btn-outline-secondary m-1" href="get_erp_inbox_file.php?id=' . $inbox_id . '&amp;format=pdf" target="_blank" rel="noopener"><i class="bi bi-file-earmark-pdf me-2" aria-hidden="true"></i>' . lang('PDF') . '</a>
                    </span>'
                        : '') . '
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-12 col-sm-6 col-lg-3 my-2">
                            <div class="form-label text-body-secondary">' . lang('GİB Number') . '</div>
                            <div>' . (((string) $invoice['gib_number'] !== '') ? h($invoice['gib_number']) : $empty) . '</div>
                        </div>
                        <div class="col-12 col-sm-6 col-lg-9 my-2">
                            <div class="form-label text-body-secondary">' . lang('ETTN') . '</div>
                            <div class="text-break small">' . h($invoice['gib_uuid']) . '</div>
                        </div>
                    </div>
                </div>
            </div>';
}

// Where it was talked about in the workspace, and the tasks about it.
// E-mail: the form to send the invoice to the customer, and every e-mail it
// already went out in.
$output_mail = '';
$output_mail_button = '';
$mail_invoice = function_exists('erp_mail_invoice') ? erp_mail_invoice($invoice_id) : null;

if (($mail_invoice !== null) && ((string) $invoice['direction'] === 'sales') && erp_mail_ready()) {
    $mail_refusal = erp_mail_invoice_refusal($mail_invoice);
    $mail_readonly = defined('USER_ERP_READONLY') && USER_ERP_READONLY;
    $mail_defaults = erp_mail_invoice_defaults($mail_invoice);
    $mail_draft = $_SESSION['software']['erp_mail_draft'][$invoice_id] ?? null;
    unset($_SESSION['software']['erp_mail_draft'][$invoice_id]);

    if (is_array($mail_draft)) {
        $mail_defaults = array_merge($mail_defaults, $mail_draft);
    }

    $mail_statuses = erp_mail_statuses();
    $mail_rows = '';

    foreach (erp_mail_history('invoice', $invoice_id) as $mail) {
        $status = $mail_statuses[(string) $mail['status']] ?? array((string) $mail['status'], 'secondary');
        $mail_rows .= '
            <tr>
                <td class="text-nowrap" data-sort="' . (int) $mail['created_at'] . '">' . h(prepare_form_data_for_output(date('Y-m-d H:i:s', (int) $mail['created_at']), 'date and time')) . '</td>
                <td class="text-break">' . h((string) $mail['to_address']) . '
                    ' . (((string) $mail['attachments'] !== '') ? '<div class="small text-body-secondary"><i class="bi bi-paperclip" aria-hidden="true"></i> ' . h((string) $mail['attachments']) . '</div>' : '') . '
                    ' . (((string) $mail['error'] !== '') ? '<div class="small text-danger">' . h((string) $mail['error']) . '</div>' : '') . '</td>
                <td>' . (((string) $mail['mode'] === 'auto') ? lang('On its own') : h(((string) $mail['created_by_name'] !== '') ? (string) $mail['created_by_name'] : '—')) . '</td>
                <td><span class="badge text-bg-' . h($status[1]) . '">' . h($status[0]) . '</span></td>
            </tr>';
    }

    $mail_official = erp_mail_invoice_official($mail_invoice);
    $mail_note = $mail_official
        ? lang('The official copy of the e-document (from the provider, with its GİB number) is attached.')
        : ((((string) $mail_invoice['edoc_provider'] !== '') && ((string) $mail_invoice['edoc_status'] !== 'accepted'))
            ? lang('GİB has not accepted the e-document yet, so the store\'s PDF is attached. Once it is accepted, the official copy goes instead.')
            : lang('The invoice PDF is attached, the copy kept when it was issued.'));

    $output_mail_form = '';

    if (($mail_refusal === '') && !$mail_readonly) {
        $output_mail_form = '
            <form name="mail_form" action="edit_erp_invoice.php" method="post" class="row g-2">
                ' . get_token_field() . '
                <input type="hidden" name="id" value="' . $invoice_id . '" />
                <input type="hidden" name="erp_action" value="mail" />
                <div class="col-12 col-md-6">
                    <label class="form-label" for="mail_to">' . lang('Recipient e-mail') . '</label>
                    <input type="text" class="form-control" id="mail_to" name="mail_to" value="' . h((string) $mail_defaults['to']) . '" maxlength="500" autocomplete="off" required />
                    <div class="form-text">' . (((string) $mail_defaults['to'] === '')
                        ? lang('The account has no e-mail address. Type one, or add it to the account.')
                        : lang('More than one address: separate them with commas.')) . '</div>
                </div>
                <div class="col-12 col-md-6">
                    <label class="form-label" for="mail_subject">' . lang('Subject') . '</label>
                    <input type="text" class="form-control" id="mail_subject" name="mail_subject" value="' . h((string) $mail_defaults['subject']) . '" maxlength="255" />
                </div>
                <div class="col-12">
                    <label class="form-label" for="mail_message">' . lang('Message') . '</label>
                    <textarea class="form-control" id="mail_message" name="mail_message" rows="3" maxlength="5000">' . h((string) $mail_defaults['message']) . '</textarea>
                    <div class="form-text"><i class="bi bi-paperclip" aria-hidden="true"></i> ' . h($mail_note) . '</div>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-sm btn-primary" data-loading-content="' . lang(array('string' => 'Please Wait')) . '"><i class="bi bi-envelope me-2" aria-hidden="true"></i>' . lang('Send the e-mail') . '</button>
                </div>
            </form>';
        $output_mail_button = '<a class="btn btn-sm btn-outline-secondary" href="#erp-mail"><i class="bi bi-envelope me-1" aria-hidden="true"></i>' . lang('E-mail') . '</a>';
    } elseif ($mail_refusal !== '') {
        $output_mail_form = '<p class="text-body-secondary mb-0">' . h($mail_refusal) . '</p>';
    }

    $output_mail = '
            <div class="card my-4" id="erp-mail">
                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                    ' . lang('E-mail to the customer') . '
                </div>
                <div class="card-body">
                    ' . $output_mail_form . '
                    ' . (($mail_rows !== '') ? '
                    <div class="table-responsive mt-3">
                        <table class="table table-sm align-middle mb-0" data-pg-sort>
                            <thead><tr><th>' . lang('Date') . '</th><th>' . lang('Recipient') . '</th><th>' . lang('Sent by') . '</th><th>' . lang('Status') . '</th></tr></thead>
                            <tbody>' . $mail_rows . '</tbody>
                        </table>
                    </div>' : '') . '
                </div>
            </div>';
}

// Repeating: what is running from this invoice, or the form to start it.
$output_recurring = '';

if (function_exists('erp_invoice_recurring_ready') && erp_invoice_recurring_ready() && ((string) $invoice['direction'] === 'sales') && ((string) $invoice['doc_type'] === 'invoice')) {
    $recurrence = erp_invoice_recurrence_of($invoice_id);
    $recur_refusal = erp_invoice_recurring_refusal($invoice);
    $recur_readonly = defined('USER_ERP_READONLY') && USER_ERP_READONLY;
    $recur_body = '';

    if ($recurrence !== null) {
        $recur_body = '<p class="mb-2"><i class="bi bi-arrow-repeat me-1" aria-hidden="true"></i>' . h(erp_invoice_recurrence_text($recurrence))
            . (((string) $recurrence['end_date'] !== '0000-00-00') ? ' ' . h(lang(array('string' => 'Until {var:1}.', 'vars' => prepare_form_data_for_output((string) $recurrence['end_date'], 'date', false)))) : '') . '</p>'
            . (((int) $recurrence['occurrences'] > 0) ? '<p class="small text-body-secondary mb-2">' . h(lang(array('string' => '{var:1} invoice(s) written so far.', 'vars' => (int) $recurrence['occurrences']))) . '</p>' : '')
            . (((string) $recurrence['last_error'] !== '') ? '<div class="alert alert-warning small py-2">' . h((string) $recurrence['last_error']) . '</div>' : '')
            . (!$recur_readonly ? '<form method="post" action="edit_erp_invoice.php">' . get_token_field() . '<input type="hidden" name="id" value="' . $invoice_id . '" /><input type="hidden" name="erp_action" value="recur_stop" /><input type="hidden" name="recurrence_id" value="' . (int) $recurrence['id'] . '" />'
                . '<button type="submit" class="btn btn-sm btn-outline-secondary" data-confirm-content="' . h(lang('Stop repeating this invoice? The invoices it already wrote stay.')) . '"><i class="bi bi-stop-circle me-1" aria-hidden="true"></i>' . lang('Stop repeating') . '</button></form>' : '');
    } elseif ($recur_refusal !== '') {
        $recur_body = '<p class="text-body-secondary mb-0">' . h($recur_refusal) . '</p>';
    } elseif (!$recur_readonly) {
        $period_options = '';
        foreach (erp_expense_recurring_periods() as $months => $label) {
            $period_options .= '<option value="' . (int) $months . '">' . h($label) . '</option>';
        }
        $mode_options = '';
        foreach (erp_invoice_recurring_modes() as $mode => $label) {
            $mode_options .= '<option value="' . h($mode) . '">' . h($label) . '</option>';
        }
        $recur_body = '
                    <form method="post" action="edit_erp_invoice.php" class="row g-2 align-items-end">
                        ' . get_token_field() . '
                        <input type="hidden" name="id" value="' . $invoice_id . '" />
                        <input type="hidden" name="erp_action" value="recur_start" />
                        <div class="col-6 col-md-3">
                            <label class="form-label" for="recur_every">' . lang('Repeats') . '</label>
                            <select class="form-select form-select-sm" id="recur_every" name="recur_every">' . $period_options . '</select>
                        </div>
                        <div class="col-6 col-md-3">
                            <label class="form-label" for="recur_first">' . lang('Next invoice on') . '</label>
                            <input type="date" class="form-control form-control-sm" id="recur_first" name="recur_first" value="' . h(erp_expense_recurring_step((string) $invoice['issue_date'], 1, (int) substr((string) $invoice['issue_date'], 8, 2))) . '" required />
                        </div>
                        <div class="col-6 col-md-3">
                            <label class="form-label" for="recur_end">' . lang('Until (optional)') . '</label>
                            <input type="date" class="form-control form-control-sm" id="recur_end" name="recur_end" />
                        </div>
                        <div class="col-6 col-md-3">
                            <label class="form-label" for="recur_mode">' . lang('Each invoice is') . '</label>
                            <select class="form-select form-select-sm" id="recur_mode" name="recur_mode">' . $mode_options . '</select>
                        </div>
                        <div class="col-12">
                            <div class="form-text mb-2">' . lang('The same account, lines and prices, dated on the day. Issued straight away, each takes the next number, and the e-document and e-mail settings apply to it.') . '</div>
                            <button type="submit" class="btn btn-sm btn-outline-primary"><i class="bi bi-arrow-repeat me-1" aria-hidden="true"></i>' . lang('Repeat this invoice') . '</button>
                        </div>
                    </form>';
    }

    if ($recur_body !== '') {
        $output_recurring = '
            <div class="card my-4" id="erp-recurring">
                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">' . lang('Repeating invoice') . '</div>
                <div class="card-body">' . $recur_body . '</div>
            </div>';
    }
}

$output_workspace_button = '';

if (defined('WORKSPACE_ENABLED') && WORKSPACE_ENABLED) {
    require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php');
    $output_workspace_button = ws_record_button($user, 'invoice', (int) $invoice_id, (string) $invoice['full_number']);
}

echo
pg_page_shell([
        'title' => $is_return ? lang('Return') : lang('Invoice'),
        'extra classes' => 'erp erp_invoices',
        'icon' => 'erp',
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

            <nav id="button_bar" class="pg-toolbar navigation" aria-label="' . lang('Button Bar') . '">
                        <a class="btn btn-sm btn-outline-secondary" href="get_erp_invoice_pdf.php?id=' . $invoice_id . '" target="_blank" rel="noopener"><i class="bi bi-file-earmark-pdf me-1"></i>' . lang('PDF') . '</a>
                        <a class="btn btn-sm btn-outline-secondary" href="get_erp_invoice_pdf.php?id=' . $invoice_id . '&amp;download=1"><i class="bi bi-download me-1"></i>' . lang('Download') . '</a>
                        ' . $output_mail_button . '
                        ' . $output_waybill_button . '
                        ' . $output_workspace_button . '
                        ' . ((function_exists('erp_audit_ready') && erp_audit_ready() && ((((int) $user['role'] < 3) || !empty($user['manage_erp_settings']) || (defined('USER_ERP_READONLY') && USER_ERP_READONLY)))) ? '<a class="btn btn-sm btn-outline-secondary" href="erp_audit.php?type=invoice&amp;id=' . $invoice_id . '"><i class="bi bi-shield-check me-1" aria-hidden="true"></i>' . lang('Audit trail') . '</a>' : '') . '
                    </nav>
        </div>

        <!--
            The form column. The summary sits beside it rather than above
            it, because a wide screen spent on one column of full-width
            cards reads as spread out - the decision is in CLAUDE.md and
            the pattern is the one the product editor uses.
        -->
        <div class="col-12 col-lg-8 col-xxl-9 order-1">

            ' . $output_reminders . '

            <div class="card my-4">
                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                    ' . lang('Lines') . '
                </div>
                <div class="card-body p-0 position-relative table-responsive">
                    <table class="table table-hover align-middle mb-0" data-pg-sort>
                        <thead>
                            <tr>
                                <th style="width:3rem">#</th>
                                ' . ($output_line_thumbs ? '<th style="width:3.5rem" data-pg-sort="none">' . lang('Image') . '</th>' : '') . '
                                <th>' . lang('Description') . '</th>
                                <th class="text-end">' . lang('Quantity') . '</th>
                                <th class="text-end">' . lang('Unit price') . '</th>
                                <th class="text-end">' . lang('Discount') . '</th>
                                <th class="text-end">' . lang('Taxable Amount') . '</th>
                                <th class="text-end">' . lang('Rate') . '</th>
                                <th class="text-end">' . h(erp_line_tax_label('tax', ((int) ($invoice['tax2_total'] ?? 0) !== 0))) . '</th>
                            </tr>
                        </thead>
                        <tbody>' . $output_lines . '</tbody>
                    </table>
                </div>
            </div>
            ' . $output_stock . '
            ' . $output_edoc . '
            ' . $output_mail . '
            ' . $output_recurring . '

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
                                <button type="submit" name="submit_notes" value="Save" class="btn btn-outline-secondary" data-loading-content="' . lang(array('string' => 'Saving')) . '"><i class="bi bi-save me-2" aria-hidden="true"></i><span class="btn-text">' . lang('Save the Note') . '</span></button>
                            </div>
                        </div>
                    </div>
                </div>
            </form>

            <div class="card my-4">
                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <span>' . lang('Settlement') . '</span>
                    ' . (($open_amount > 0 && (string) $invoice['status'] !== 'cancelled')
                        ? '<a class="btn btn-sm btn-primary" href="add_erp_receipt.php?direction=' . (((string) $invoice['direction'] === 'sales') ? 'collection' : 'payment') . '&amp;invoice_id=' . $invoice_id . '"><i class="bi bi-cash-coin me-2" aria-hidden="true"></i>' . lang('Record a Receipt') . '</a>'
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
                                ? '<a class="btn my-1 btn-outline-warning" href="add_erp_return.php?invoice_id=' . $invoice_id . '"><i class="bi bi-arrow-return-left me-2" aria-hidden="true"></i>' . lang('Return an Invoice') . '</a>'
                                : '') . '
                            ' . ($can_cancel
                                ? '<button type="submit" name="submit_cancel" value="Cancel" class="btn my-1 btn-outline-danger" data-loading-content="' . lang(array('string' => 'Please Wait')) . '"' . (((string) $provider_cancellable['reason'] !== '') ? ' title="' . h((string) $provider_cancellable['reason']) . '"' : '') . '><i class="bi bi-x-circle me-2" aria-hidden="true"></i><span class="btn-text">' . (!empty($provider_cancellable['manual']) ? lang('Cancelled there: cancel it here too') : lang('Cancel the Document')) . '</span></button>'
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
                                <span>' . h(erp_tax_label('tax')) . '</span><b>' . $money((int) $invoice['tax_total'] - (int) ($invoice['tax2_total'] ?? 0)) . '</b>
                            </div>' . (((int) ($invoice['tax2_total'] ?? 0) !== 0) ? '
                            <div class="d-flex justify-content-between py-1">
                                <span>' . h((erp_tax2_name() !== '') ? erp_tax2_name() : lang('Second tax')) . '</span><b>' . $money((int) $invoice['tax2_total']) . '</b>
                            </div>' : '') . '
                            ' . (((int) $invoice['withholding_total'] !== 0)
                                ? '<div class="d-flex justify-content-between py-1">
                                <span>' . lang('Total including taxes') . '</span><b>' . $money((int) $invoice['grand_total'] + (int) $invoice['withholding_total']) . '</b>
                            </div>
                            <div class="d-flex justify-content-between py-1">
                                <span>' . lang('VAT withholding') . '</span><b>&minus;' . $money((int) $invoice['withholding_total']) . '</b>
                            </div>'
                                : '') . '
                            <div class="d-flex justify-content-between py-2 border-top h5 mb-0">
                                <span>' . (((int) $invoice['withholding_total'] !== 0) ? lang('Amount payable') : lang('Total')) . '</span><b>' . $money((int) $invoice['grand_total']) . '</b>
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

        <!--
            What is read, not worked on. Sticky under the fixed header, and
            after the form column in source order so a narrow screen puts it
            below the document rather than in front of it.
        -->
        <div class="col-12 col-lg-4 col-xxl-3 order-2">
            <div class="position-sticky" style="top:3.3rem;">
                <div class="card my-4">
                    <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                        ' . ($is_return ? lang('Return') : lang('Invoice')) . '
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-6 col-sm-4 col-lg-12 my-2">
                                <div class="form-label text-body-secondary">' . lang('Account') . '</div>
                                <div>' . $output_account . '</div>
                            </div>
                            <div class="col-6 col-sm-4 col-lg-12 my-2">
                                <div class="form-label text-body-secondary">' . h(erp_tax_id_label($has_snapshot ? (string) $invoice['account_country_code'] : (string) ($invoice['live_country_code'] ?? ''))) . '</div>
                                <div>' . h($account_tax_number ?: '—') . ' <span class="text-body-secondary">' . h($account_tax_office) . '</span></div>
                            </div>
                            <div class="col-6 col-sm-4 col-lg-12 my-2">
                                <div class="form-label text-body-secondary">' . lang('Date') . '</div>
                                <div>' . h(prepare_form_data_for_output($invoice['issue_date'], 'date')) . '</div>
                            </div>
                            <div class="col-6 col-sm-4 col-lg-12 my-2">
                                <div class="form-label text-body-secondary">' . lang('Due Date') . '</div>
                                <div>' . h(prepare_form_data_for_output(((string) $invoice['due_date'] !== '0000-00-00') ? $invoice['due_date'] : $invoice['issue_date'], 'date')) . '</div>
                            </div>
                            <div class="col-6 col-sm-4 col-lg-12 my-2">
                                <div class="form-label text-body-secondary">' . lang('Status') . '</div>
                                <div>' . h($status_labels[$invoice['status']] ?? $invoice['status']) . '</div>
                            </div>
                            <div class="col-6 col-sm-4 col-lg-12 my-2">
                                <div class="form-label text-body-secondary">' . lang('Order') . '</div>
                                <div>' . $output_order . '</div>
                            </div>
                            ' . ($is_return
                                ? '<div class="col-6 col-sm-4 col-lg-12 my-2">
                                <div class="form-label text-body-secondary">' . lang('Against Invoice') . '</div>
                                <div><a href="edit_erp_invoice.php?id=' . (int) $invoice['parent_invoice_id'] . '">' . h((string) db_value("SELECT full_number FROM erp_invoices WHERE id = '" . (int) $invoice['parent_invoice_id'] . "'")) . '</a></div>
                            </div>'
                                : '') . '
                            ' . $output_supplier . '
                        </div>
                        ' . ($is_foreign
                            ? '<div class="row border-top mt-2 pt-2">
                            <div class="col-6 col-sm-4 col-lg-12 my-2">
                                <div class="form-label text-body-secondary">' . lang('Currency') . '</div>
                                <div>' . h($currency) . '</div>
                            </div>
                            <div class="col-6 col-sm-4 col-lg-12 my-2">
                                <div class="form-label text-body-secondary">' . lang('Exchange Rate') . '</div>
                                <div>' . h(erp_fx_rate_text((float) $invoice['exchange_rate'])) . ' <span class="text-body-secondary">' . h(erp_base_currency()) . ((trim((string) $invoice['exchange_rate_source']) !== '') ? ' · ' . h($invoice['exchange_rate_source']) : '') . '</span></div>
                            </div>
                            <div class="col-6 col-sm-4 col-lg-12 my-2">
                                <div class="form-label text-body-secondary">' . lang('Exchange Rate Date') . '</div>
                                <div>' . h(prepare_form_data_for_output($invoice['exchange_rate_date'], 'date')) . '</div>
                            </div>
                            <div class="col-6 col-sm-4 col-lg-12 my-2">
                                <div class="form-label text-body-secondary">' . h(lang(array('string' => 'Total in {var:1}', 'vars' => erp_base_currency()))) . '</div>
                                <div>' . h(erp_money_out((int) $invoice['grand_total_base'])) . '</div>
                            </div>
                        </div>'
                            : '') . '
                        ' . $output_internet_sale . '
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>
' .
output_footer();

$liveform->remove_form();
