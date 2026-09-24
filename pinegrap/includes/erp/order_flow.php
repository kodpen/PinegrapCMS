<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * ERP - an order's documents, in the order they are made.
 *
 * An order becomes four documents: the invoice, its e-document (e-Fatura or
 * e-Arşiv, decided by the buyer), the delivery note and its e-document
 * (e-İrsaliye). The order screen shows all four with where each stands, and
 * offers the next one as a single "Next" button, so an operator can walk an
 * order from invoice to delivery note without leaving it.
 *
 * Nothing here decides anything a document screen does not already decide:
 * every step calls the same function its own screen's button calls, with the
 * same gates. A step that cannot run says why - the buyer's missing fields
 * before the provider is asked, not after it refused.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_ERP_ENTRY')) {
    exit;
}

/**
 * Which e-document an invoice becomes, in the words the operator uses.
 *
 * GİB decides it by the buyer: a registered e-Fatura taxpayer gets an
 * e-Fatura, everybody else an e-Arşiv invoice. The registration is what the
 * account card learned from the taxpayer query; a card that was never asked
 * says so rather than guessing.
 *
 * @param array      $invoice  An erp_invoices row
 * @param array|null $account  Its erp_accounts row
 * @return array ['kind' => 'einvoice'|'earchive'|'unknown', 'label' => string, 'note' => string]
 */
function erp_order_flow_edoc_kind($invoice, $account)
{
    // The decision itself is the e-document layer's, so the order card and
    // the date check read the same answer.
    $kind = erp_edoc_invoice_expected_kind((array) $invoice, $account);
    $note = ((int) ($invoice['is_internet_sale'] ?? 0) === 1) ? lang('internet sale') : '';

    if ($kind === 'einvoice') {
        return array('kind' => 'einvoice', 'label' => lang('e-Invoice'), 'note' => '');
    }

    if ($kind === 'earchive') {
        return array('kind' => 'earchive', 'label' => lang('e-Archive'), 'note' => $note);
    }

    return array(
        'kind' => 'unknown',
        'label' => lang('e-Invoice or e-Archive'),
        'note' => lang('Whether this VKN is registered for e-Invoice has not been asked yet; the provider decides when the document is sent.'),
    );
}

/**
 * Where an order's four documents stand, and what can be done next.
 *
 * @param int $order_id
 * @return array|null  null for an order that is not there; otherwise
 *                     ['order', 'account', 'invoice', 'waybill', 'provider',
 *                      'steps' => key => [label, state, title, facts, action, reason, links],
 *                      'next' => step key or '']
 *                     state: done | ready | waiting | blocked | unavailable | later
 */
function erp_order_flow_state($order_id)
{
    $order_id = (int) $order_id;
    $order = db_item("SELECT id, order_number, status, contact_id, erp_invoice_id, erp_account_id,
            payment_method, transaction_id, refunded_amount, refund_status
        FROM orders WHERE id = '" . $order_id . "' LIMIT 1");

    if (!is_array($order)) {
        return null;
    }

    $invoice_row = erp_order_flow_invoice_row($order);

    // A cancelled invoice leaves the order to be invoiced again. The row is
    // kept aside: a cancelled order's card still says its invoice was
    // cancelled, and what came in for it still has to go back.
    $invoice = $invoice_row;
    if (is_array($invoice) && ((string) $invoice['status'] === 'cancelled')) {
        $invoice = null;
    }

    $account_id = is_array($invoice) ? (int) $invoice['account_id'] : (int) $order['erp_account_id'];
    $account = ($account_id > 0) ? db_item("SELECT * FROM erp_accounts WHERE id = '" . $account_id . "' LIMIT 1") : null;

    $waybill = db_item("SELECT * FROM erp_waybills WHERE order_id = '" . $order_id . "' AND status <> 'cancelled' ORDER BY id ASC LIMIT 1");
    $waybill = is_array($waybill) ? $waybill : null;

    $provider = erp_edoc_installed() ? erp_edoc_active() : '';
    $provider_label = ($provider !== '') ? (string) erp_edoc_info($provider)['label'] : '';

    // A finished sale is 'complete' or 'exported' (see
    // erp_order_billable_statuses()); an incomplete order is a checkout that
    // never finished and a cancelled one is not a sale any more.
    $complete = in_array((string) $order['status'], erp_order_billable_statuses(), true);
    $cancelled = ((string) $order['status'] === 'cancelled');
    $billable = ((int) $order['contact_id'] > 0) || ((int) $order['erp_account_id'] > 0);

    $steps = array();

    // ------------------------------------------------------------ invoice
    $step = array('label' => lang('Invoice'), 'state' => 'ready', 'title' => '', 'facts' => array(), 'action' => '', 'reason' => '', 'links' => array());

    if (is_array($invoice)) {
        $step['state'] = 'done';
        $step['title'] = ((string) $invoice['status'] === 'draft') ? lang('Draft') : (string) $invoice['full_number'];
        $step['facts'][] = erp_money_out_currency((int) $invoice['grand_total'], strtoupper(trim((string) $invoice['currency'])));
        $step['links'][] = array('label' => lang('View'), 'url' => (((string) $invoice['status'] === 'draft') ? 'edit_erp_invoice_draft.php?id=' : 'edit_erp_invoice.php?id=') . (int) $invoice['id']);
        if ((string) $invoice['status'] !== 'draft') {
            $step['links'][] = array('label' => lang('PDF'), 'url' => 'get_erp_invoice_pdf.php?id=' . (int) $invoice['id'], 'blank' => true);

            // Without a provider the store's own PDF is the document to hand
            // over; with one, the print that counts is the e-document's.
            if ($provider === '') {
                $step['print'] = array('label' => lang('Print the Invoice'), 'url' => 'get_erp_invoice_pdf.php?id=' . (int) $invoice['id']);
            }
        }
    } elseif (!$complete) {
        $step['state'] = 'later';
        $step['disabled_action'] = 'invoice';
        $step['reason'] = $cancelled
            ? lang('A cancelled order is not invoiced.')
            : lang('The order has not been completed yet (the checkout did not finish); it can be invoiced once it is.');
    } elseif (!$billable) {
        $step['state'] = 'blocked';
        $step['disabled_action'] = 'invoice';
        $step['reason'] = lang('No walk-in sales account is named on the ERP settings card, so this sale cannot be invoiced unless a customer is picked.');
    } else {
        $step['action'] = 'invoice';
        $step['reason'] = lang('The invoice takes its figures from the order, with a number from the sales series.');

        // The buyer's gaps, said before the number is spent: issuing checks
        // the same list when a provider is active and refuses.
        if (($provider !== '') && is_array($account)) {
            $gaps = erp_edoc_account_missing($account);
            if (!empty($gaps['fields'])) {
                $step['state'] = 'blocked';
                $step['action'] = '';
                $step['disabled_action'] = 'invoice';
                $step['reason'] = lang(array('string' => '{var:1} will not take the invoice until these are on the account card: {var:2}.', 'vars' => array($gaps['label'], erp_edoc_fix_summary($gaps['fields']))));
                $step['links'][] = array('label' => lang('Complete the account card'), 'url' => 'edit_erp_account.php?id=' . (int) $account['id']);
            }
        }
    }
    $steps['invoice'] = $step;

    // ------------------------------------------------------- e-document
    $kind = is_array($invoice) ? erp_order_flow_edoc_kind($invoice, $account) : array('kind' => 'unknown', 'label' => lang('e-Invoice or e-Archive'), 'note' => '');
    $step = array('label' => $kind['label'] . (($kind['note'] !== '' && $kind['kind'] === 'earchive') ? ' (' . $kind['note'] . ')' : ''), 'state' => 'later', 'title' => '', 'facts' => array(), 'action' => '', 'reason' => '', 'links' => array());

    if ($provider === '' && (!is_array($invoice) || ((string) ($invoice['edoc_status'] ?? 'none') === 'none'))) {
        $step['state'] = 'unavailable';
        $step['reason'] = lang('No e-document provider is selected on the E-Invoice card of the commerce settings; the invoice stays a PDF.');
    } elseif (!is_array($invoice) || ((string) $invoice['status'] === 'draft')) {
        $step['reason'] = lang('Made official once the invoice is issued.');
    } else {
        $labels = erp_edoc_status_labels();
        $status = (string) ($invoice['edoc_status'] ?? 'none');
        $step['title'] = ((string) ($invoice['gib_number'] ?? '') !== '') ? (string) $invoice['gib_number'] : '';
        $step['facts'][] = array('badge' => $labels[$status] ?? array($status, 'secondary'));
        if ($kind['kind'] === 'unknown') {
            $step['reason'] = $kind['note'];
        }
        $step['links'][] = array('label' => lang('View'), 'url' => 'edit_erp_invoice.php?id=' . (int) $invoice['id']);

        // The official copy is the provider's: it carries the GİB number and
        // the QR code, which the store's own PDF does not. Offered once the
        // document has gone to GİB, never for a draft still at the provider.
        if (in_array($status, array('sent', 'accepted'), true) && erp_edoc_supports('fetch_document', (string) $invoice['edoc_provider'])) {
            $step['print'] = array(
                'label' => lang(array('string' => 'Print the {var:1}', 'vars' => $kind['label'])),
                'url' => 'get_erp_edoc_document.php?id=' . (int) $invoice['id'] . '&format=pdf',
            );
        }

        if ($status === 'accepted') {
            $step['state'] = 'done';
        } elseif ($status === 'created') {
            $step['state'] = 'ready';
            $step['action'] = 'edoc_submit';
            $step['reason'] = lang(array('string' => 'The document is waiting as a draft at {var:1}; hand it to GİB to make it official.', 'vars' => $provider_label));
        } elseif (in_array($status, array('queued', 'sending', 'sent'), true)) {
            $step['state'] = 'waiting';
            $step['action'] = 'edoc_poll';
            $step['reason'] = lang('Sent; GİB has not answered yet. Ask again in a little while.');
        } else {
            $gate = erp_edoc_invoice_can_send($invoice);
            $party = erp_edoc_invoice_party_missing($invoice);
            $document = erp_edoc_invoice_document_missing($invoice);
            $fields = array_merge((array) $party['fields'], (array) $document['fields']);

            if (!empty($fields)) {
                // The summary names the boxes; why each one is wanted is said
                // on the invoice, next to the box.
                $step['state'] = 'blocked';
                $step['reason'] = lang(array('string' => '{var:1} will not take the document until these are filled in: {var:2}.', 'vars' => array($provider_label, erp_edoc_fix_summary($fields))));
                $step['links'][0]['label'] = lang('Complete the missing fields');
                $step['links'][0]['url'] .= '#edoc_fix';
            } elseif (!$gate['ok']) {
                $step['state'] = 'blocked';
                $step['reason'] = $gate['reason'];
            } elseif (($date_latest = erp_edoc_invoice_date_behind($invoice)) !== '') {
                // GİB's date order: fixed on the invoice, where the one
                // repair (moving it to today) is offered.
                $step['state'] = 'blocked';
                $step['reason'] = lang(array('string' => 'The invoice is dated {var:1}, but {var:2} already has documents dated {var:3}. GİB takes documents of one type only in date order, so it will refuse this one.', 'vars' => array(prepare_form_data_for_output((string) $invoice['issue_date'], 'date', false), $provider_label, prepare_form_data_for_output($date_latest, 'date', false))));
                $step['links'][0]['label'] = lang('Fix the date');
                $step['links'][0]['url'] .= '#edoc_date';
            } else {
                $step['state'] = 'ready';
                $step['action'] = 'edoc_send';
                $step['action_label'] = ($kind['kind'] === 'unknown')
                    ? lang('Issue the e-Document')
                    : lang(array('string' => 'Issue the {var:1}', 'vars' => $kind['label']));
                $step['reason'] = erp_edoc_autosend()
                    ? lang(array('string' => 'Sent to {var:1} and handed to GİB in one step.', 'vars' => $provider_label))
                    : lang(array('string' => 'Sent to {var:1} as a draft; handing it to GİB is the next step.', 'vars' => $provider_label));
            }

            // The provider's last word, unless the gaps above already say it:
            // a refusal for a missing field is the same sentence twice.
            if ((($status === 'error') && empty($fields)) || ($status === 'rejected')) {
                $message = trim((string) ($invoice['edoc_error'] ?? ''));
                if ($message !== '') {
                    $step['facts'][] = array('text' => lang(array('string' => 'Last answer: {var:1}', 'vars' => $message)));
                }
            }
        }
    }
    $steps['edoc'] = $step;

    // --------------------------------------------------- delivery note
    $step = array('label' => lang('Delivery Note'), 'state' => 'later', 'title' => '', 'facts' => array(), 'action' => '', 'reason' => '', 'links' => array());

    if (is_array($waybill)) {
        $step['state'] = 'done';
        $step['title'] = (string) $waybill['full_number'];
        if (trim((string) $waybill['carrier_title']) !== '') {
            $step['facts'][] = (string) $waybill['carrier_title'];
        }
        $step['links'][] = array('label' => lang('View'), 'url' => 'edit_erp_waybill.php?id=' . (int) $waybill['id']);
        $step['print'] = array('label' => lang('Print the Delivery Note'), 'url' => 'get_erp_waybill_pdf.php?id=' . (int) $waybill['id']);
    } elseif (!$complete) {
        $step['disabled_action'] = 'waybill';
        $step['reason'] = $cancelled
            ? lang('A cancelled order gets no delivery note.')
            : lang('The order has not been completed yet; the delivery note is written once it is.');
    } elseif (!$billable) {
        $step['state'] = 'blocked';
        $step['disabled_action'] = 'waybill';
        $step['reason'] = lang('This order is not linked to a contact, so there is no account to bill.');
    } else {
        $step['state'] = 'ready';
        $step['action'] = 'waybill';
        $step['reason'] = lang('The note takes the order\'s items, its first recipient and the carrier recorded on the order.');
        $step['links'][] = array('label' => lang('Write it on the form'), 'url' => is_array($invoice) ? ('add_erp_waybill.php?invoice_id=' . (int) $invoice['id']) : ('add_erp_waybill.php?order_id=' . $order_id));
    }
    $steps['waybill'] = $step;

    // ------------------------------------------------ e-delivery note
    $step = array('label' => lang('e-Delivery Note'), 'state' => 'later', 'title' => '', 'facts' => array(), 'action' => '', 'reason' => '', 'links' => array());

    if (is_array($waybill) && ((string) ($waybill['edoc_external_id'] ?? '') !== '')) {
        $step['state'] = 'done';
        $step['title'] = (string) $waybill['edoc_external_id'];
    } elseif (($provider === '') || !erp_edoc_supports('send_waybill', $provider)) {
        $step['state'] = 'unavailable';
        $step['reason'] = ($provider === '')
            ? lang('No e-document provider is selected; the delivery note stays a paper document.')
            : lang(array('string' => '{var:1} does not send e-Delivery Notes through its API; the delivery note stays a paper document. Only a store registered for e-İrsaliye is required to send one.', 'vars' => $provider_label));
    } elseif (!is_array($waybill)) {
        $step['reason'] = lang('Sent once the delivery note is written.');
    } else {
        $step['state'] = 'ready';
        $step['action'] = 'ewaybill';
        $step['reason'] = lang(array('string' => 'The delivery note is sent to {var:1} as an e-Delivery Note.', 'vars' => $provider_label));
    }
    $steps['ewaybill'] = $step;

    // ---------------------------------------------- cancellation, refunds
    // Cancelling an order or refunding a card touches none of the ERP by
    // itself: the invoice stays issued and the customer stays in debt. When
    // either has happened, the card adds the two steps that put the books
    // right - the document, then the money - each a click, prefilled.
    $cancelled = ((string) $order['status'] === 'cancelled');
    $refunded = (int) ($order['refunded_amount'] ?? 0);

    if (($cancelled || ($refunded > 0)) && is_array($invoice_row) && ((string) $invoice_row['status'] !== 'draft')) {
        foreach (erp_order_flow_reverse_steps($order, $invoice_row, $account, $provider_label) as $key => $reverse_step) {
            $steps[$key] = $reverse_step;
        }

        // A cancelled order is not made official, and nothing more is
        // written for it: the steps that would are set aside, so "next"
        // lands on putting the books right. A document already with GİB
        // keeps its step - its answer is still worth asking for.
        if ($cancelled) {
            foreach (array('edoc', 'waybill', 'ewaybill') as $key) {
                if (!in_array($steps[$key]['state'], array('done', 'waiting'), true)) {
                    $steps[$key]['state'] = 'unavailable';
                    $steps[$key]['action'] = '';
                    $steps[$key]['disabled_action'] = '';
                    $steps[$key]['reason'] = lang('The order was cancelled; this document is not made for it.');
                    // Nothing is left to write or complete on it: only the
                    // links that open what exists stay, as plain "View".
                    $kept = array();
                    foreach ($steps[$key]['links'] as $link) {
                        $url = (string) $link['url'];
                        if (strpos($url, '#edoc_') !== false) {
                            $link = array('label' => lang('View'), 'url' => strtok($url, '#'));
                            $url = $link['url'];
                        }
                        if ((strpos($url, 'edit_') === 0) || (strpos($url, 'get_') === 0)) {
                            $kept[] = $link;
                        }
                    }
                    $steps[$key]['links'] = $kept;
                }
            }
            if ($steps['invoice']['state'] !== 'done') {
                $steps['invoice']['state'] = 'unavailable';
                $steps['invoice']['disabled_action'] = '';
            }
        }
    }

    // Without an e-document provider there is no e-Invoice and no
    // e-Delivery Note to make: those steps are not drawn, unless this
    // order's documents went through a provider before.
    if ($provider === '') {
        if (!is_array($invoice) || ((string) ($invoice['edoc_status'] ?? 'none') === 'none')) {
            unset($steps['edoc']);
        }

        if (!is_array($waybill) || ((string) ($waybill['edoc_external_id'] ?? '') === '')) {
            unset($steps['ewaybill']);
        }
    }

    // The next step is the first one that is not finished, as long as it
    // can run; a blocked step stops the chain where it is. A document that
    // is waiting for GİB's answer does not hold the next one back - the
    // answer can take hours, and in a test environment it may never come -
    // so the delivery note is offered and the question stays on its own
    // button. Nothing runs by itself: every step is a click.
    $next = '';
    $waiting = '';
    foreach ($steps as $key => $candidate) {
        if (in_array($candidate['state'], array('done', 'unavailable'), true)) {
            continue;
        }
        if ($candidate['state'] === 'waiting') {
            $waiting = ($waiting === '') ? $key : $waiting;
            continue;
        }
        if (($candidate['state'] === 'ready') && (($candidate['action'] !== '') || !empty($candidate['goto']))) {
            $next = $key;
        }
        break;
    }
    if ($next === '') {
        $next = $waiting;
    }

    return array(
        'order' => $order,
        'account' => $account,
        'invoice' => $invoice,
        'waybill' => $waybill,
        'provider' => $provider,
        'steps' => $steps,
        'next' => $next,
    );
}

/**
 * The order's sales invoice: the one it points at, or - once that has been
 * cancelled, which unlinks it so the order can be invoiced again - the last
 * one written for it.
 *
 * @param array $order  id, erp_invoice_id
 * @return array|null
 */
function erp_order_flow_invoice_row($order)
{
    if ((int) $order['erp_invoice_id'] > 0) {
        return db_item("SELECT * FROM erp_invoices WHERE id = '" . (int) $order['erp_invoice_id'] . "' LIMIT 1");
    }

    return db_item("SELECT * FROM erp_invoices
        WHERE order_id = '" . (int) $order['id'] . "' AND direction = 'sales' AND doc_type = 'invoice'
        ORDER BY id DESC LIMIT 1");
}

/**
 * The two steps a cancelled or refunded order adds: the document that takes
 * the sale back, and the money that goes back to the customer.
 *
 * The document: an invoice the provider does not have yet is not official,
 * so it is cancelled; one that has gone is taken back with a return invoice,
 * written on the return form with the quantities prefilled. The money: read
 * from the customer's account, not guessed - once the sale is taken back,
 * a credit on the account is what the store owes, and paying it out is a
 * payment from the till or bank the money leaves.
 *
 * @param array      $order
 * @param array      $invoice        The invoice row, cancelled or not
 * @param array|null $account
 * @param string     $provider_label
 * @return array  key => step
 */
function erp_order_flow_reverse_steps($order, $invoice, $account, $provider_label)
{
    $invoice_id = (int) $invoice['id'];
    $order_id = (int) $order['id'];
    $cancelled_order = ((string) $order['status'] === 'cancelled');
    $currency = strtoupper(trim((string) $invoice['currency']));
    $returned = erp_invoice_returned_total($invoice_id);
    $grand = (int) $invoice['grand_total'];
    $refunded = (int) ($order['refunded_amount'] ?? 0);
    $steps = array();

    // ------------------------------------------------------ the document
    $step = array('label' => lang('Return or cancellation'), 'state' => 'ready', 'title' => '', 'facts' => array(), 'action' => '', 'reason' => '', 'links' => array());

    foreach (erp_invoice_returns($invoice_id) as $return) {
        $step['links'][] = array('label' => (string) $return['full_number'], 'url' => 'edit_erp_invoice.php?id=' . (int) $return['id']);
    }

    $return_url = 'add_erp_return.php?invoice_id=' . $invoice_id . '&back_order=' . $order_id;

    // Whether the provider can take the document back is the driver's to
    // say (erp_edoc_invoice_cancellable()): a draft is deleted, an e-Archive
    // invoice may be cancelled through the API or on the provider's own
    // screen, an e-Invoice is answered with a return. The card only follows
    // the answer, so a provider that learns to cancel changes nothing here.
    $has_edoc = (trim((string) ($invoice['edoc_external_id'] ?? '')) !== '')
        || !in_array((string) ($invoice['edoc_status'] ?? 'none'), array('none', 'error', 'rejected'), true);
    $cancellable = $has_edoc ? erp_edoc_invoice_cancellable($invoice) : array('ok' => true, 'manual' => false, 'reason' => '', 'url' => '');

    if ((string) $invoice['status'] === 'cancelled') {
        $step['state'] = 'done';
        $step['title'] = lang('The invoice was cancelled');
        $step['facts'][] = (string) $invoice['full_number'];
        $step['links'][] = array('label' => lang('View'), 'url' => 'edit_erp_invoice.php?id=' . $invoice_id);
    } elseif ($returned >= $grand) {
        $step['state'] = 'done';
        $step['title'] = lang('Returned in full');
    } elseif ($cancelled_order && !empty($cancellable['ok']) && empty(erp_invoice_returns($invoice_id))) {
        $step['action'] = 'invoice_cancel';
        if (!$has_edoc && ($provider_label === '')) {
            // Without an e-document provider the invoice is paper: whether
            // it is official depends on whether the customer has it.
            $step['reason'] = lang('If the printed invoice has not reached the customer, cancel it; if it has, issue a return invoice instead.');
            $step['links'][] = array('label' => lang('Issue the return invoice'), 'url' => $return_url);
        } elseif (!$has_edoc) {
            $step['reason'] = lang('The invoice has not been made official, so it is cancelled; its number stays in the register as cancelled.');
        } else {
            $step['reason'] = trim(lang('The invoice is cancelled here once the provider has let it go; its number stays in the register as cancelled.') . ' ' . (string) $cancellable['reason']);
            if (!empty($cancellable['manual'])) {
                // The operator acts on the provider's screen; the click
                // asks the provider before anything changes here.
                $step['action_label'] = lang('Cancelled there: cancel it here too');
                if ((string) ($cancellable['url'] ?? '') !== '') {
                    $step['links'][] = array('label' => lang('Open it at the provider'), 'url' => (string) $cancellable['url'], 'blank' => true);
                }
                $step['links'][] = array('label' => lang('Issue the return invoice'), 'url' => $return_url);
            }
        }
        if (erp_invoice_receipt_count($invoice_id) > 0) {
            $step['reason'] .= ' ' . lang('The money allocated to it stays on the account; the next step records it going back.');
        }
    } elseif ($cancelled_order) {
        $step['goto'] = array('label' => lang('Issue the return invoice'), 'url' => $return_url, 'icon' => 'bi-arrow-counterclockwise');
        $step['reason'] = ($returned > 0)
            ? lang(array('string' => 'Part of the invoice has been returned ({var:1} of {var:2}); the return form comes up with what is left.', 'vars' => array(erp_money_out_currency($returned, $currency), erp_money_out_currency($grand, $currency))))
            : trim((($has_edoc && ((string) $cancellable['reason'] !== '')) ? (string) $cancellable['reason'] . ' ' : '') . lang('The form comes up with every line.'));
    } elseif ($refunded > $returned) {
        $step['goto'] = array('label' => lang('Issue the return invoice'), 'url' => $return_url, 'icon' => 'bi-arrow-counterclockwise');
        $step['reason'] = lang(array('string' => '{var:1} was refunded to the card, and return invoices cover {var:2}. Choose the lines that came back.', 'vars' => array(erp_money_out($refunded), erp_money_out($returned))));
    } else {
        $step['state'] = 'done';
        $step['title'] = lang('The refunds are covered by return invoices');
    }
    $steps['reverse'] = $step;

    // --------------------------------------------------------- the money
    // What this sale still owes the customer: the money that came in for it,
    // less what the sale is still worth, less what has gone back already.
    // Worked out for the order, not read off the account - a walk-in
    // account is shared by every counter sale.
    $step = array('label' => lang('Refund of the money'), 'state' => 'later', 'title' => '', 'facts' => array(), 'action' => '', 'reason' => '', 'links' => array());
    $money = erp_order_flow_money($order, $invoice);

    foreach ($money['refunds'] as $refund) {
        $step['facts'][] = lang(array('string' => '{var:1} paid back on {var:2}', 'vars' => array(erp_money_out($refund['amount_base']), prepare_form_data_for_output($refund['doc_date'], 'date'))));
    }

    if ($steps['reverse']['state'] !== 'done') {
        $step['reason'] = lang('Recorded once the sale has been taken back.');
    } elseif ($money['owed'] > 0) {
        $card = ((string) ($order['transaction_id'] ?? '') !== '') || (stripos((string) ($order['payment_method'] ?? ''), 'card') !== false);
        $step['state'] = 'ready';
        $step['goto'] = array(
            'label' => lang('Record the refund'),
            'icon' => 'bi-box-arrow-up',
            'url' => 'add_erp_receipt.php?direction=payment&account_id=' . (int) $invoice['account_id'] . '&amount=' . $money['owed']
                . '&method=' . ($card ? 'card' : 'cash') . '&back_order=' . $order_id
                . '&description=' . rawurlencode(lang(array('string' => 'Refund for order {var:1}', 'vars' => (string) $order['order_number'])))
        );
        $step['reason'] = lang(array('string' => '{var:1} came in for this order and the store owes it back. Once the money has gone, record which till or bank it left.', 'vars' => erp_money_out($money['owed'])));
        if ($money['guessed']) {
            $step['reason'] .= ' ' . lang('The figure is the account\'s credit; if part of it belongs to another sale, change it on the form.');
        }
        if ((string) ($order['refund_status'] ?? '') === 'refunded') {
            $step['reason'] .= ' ' . lang('The card payment has already been refunded through the payment provider.');
        }
    } elseif (($money['in'] === 0) && empty($money['refunds'])) {
        $step['state'] = 'unavailable';
        $step['reason'] = lang('No receipt was recorded for this order, so there is no money to record going back. A card payment that reached the bank without a receipt in the ERP is not in the tills either.');
    } else {
        $step['state'] = 'done';
        $step['title'] = lang('Nothing is owed to the customer');
    }
    $steps['money'] = $step;

    return $steps;
}

/**
 * The money of one order, for the refund step.
 *
 * in:  receipts allocated to the invoice, plus receipts recorded from the
 *      order's card (order_id) that were not allocated to it;
 * worth: what the sale is still worth - nothing once the invoice is
 *      cancelled, otherwise the invoice less its returns;
 * refunds: payments recorded from the order's card.
 *
 * An invoice cancelled from the card frees its allocations first, so the
 * money that paid it is no longer tied to it. Then the account's credit is
 * the only measure left, and the figure is marked as a guess for the form.
 *
 * @param array $order
 * @param array $invoice
 * @return array ['in' => int, 'worth' => int, 'refunds' => array, 'owed' => int, 'guessed' => bool]  Base kurus
 */
function erp_order_flow_money($order, $invoice)
{
    $invoice_id = (int) $invoice['id'];
    $order_id = (int) $order['id'];
    $linked = erp_overdue_column_exists('erp_cash_transactions', 'order_id');
    $not_reversed = "NOT EXISTS (SELECT 1 FROM erp_cash_transactions r WHERE r.doc_type = 'cancel' AND r.doc_id = c.id)";

    $in = (int) db_value("SELECT COALESCE(SUM(s.amount_base), 0) FROM erp_settlements s
        LEFT JOIN erp_account_transactions t ON s.account_txn_id = t.id
        WHERE s.invoice_id = '" . $invoice_id . "' AND (t.id IS NULL OR t.doc_type <> 'gift_card')");

    $refunds = array();

    if ($linked) {
        $in += (int) db_value("SELECT COALESCE(SUM(c.amount_base), 0) FROM erp_cash_transactions c
            WHERE c.order_id = '" . $order_id . "' AND c.direction = 'in' AND c.doc_type = 'collection' AND " . $not_reversed . "
              AND NOT EXISTS (SELECT 1 FROM erp_settlements s
                  INNER JOIN erp_account_transactions t ON s.account_txn_id = t.id
                  WHERE s.invoice_id = '" . $invoice_id . "' AND t.doc_type = 'collection' AND t.doc_id = c.id)");

        $refunds = (array) db_items("SELECT c.id, c.doc_date, c.amount_base FROM erp_cash_transactions c
            WHERE c.order_id = '" . $order_id . "' AND c.direction = 'out' AND c.doc_type = 'payment' AND " . $not_reversed . "
            ORDER BY c.doc_date ASC, c.id ASC");
    }

    $paid_back = 0;
    foreach ($refunds as $refund) {
        $paid_back += (int) $refund['amount_base'];
    }

    $cancelled = ((string) $invoice['status'] === 'cancelled');
    $returned = (int) db_value("SELECT COALESCE(SUM(grand_total_base), 0) FROM erp_invoices
        WHERE parent_invoice_id = '" . $invoice_id . "' AND doc_type = 'return' AND status <> 'cancelled'");
    $worth = $cancelled ? 0 : max(0, (int) $invoice['grand_total_base'] - $returned);

    $owed = max(0, $in - $worth - $paid_back);
    $guessed = false;

    // A cancelled invoice has no allocations left to count. If nothing tied
    // to the order says what came in, the account's credit stands in for it
    // until a refund has been recorded from here.
    if ($cancelled && ($in === 0) && empty($refunds)) {
        $balance = (int) db_value("SELECT COALESCE(SUM(CASE WHEN direction = 'debit' THEN amount_base ELSE -amount_base END), 0)
            FROM erp_account_transactions WHERE account_id = '" . (int) $invoice['account_id'] . "'");
        if ($balance < 0) {
            $owed = -$balance;
            $in = $owed;
            $guessed = true;
        }
    }

    return array('in' => $in, 'worth' => $worth, 'refunds' => $refunds, 'owed' => $owed, 'guessed' => $guessed);
}

/**
 * The words of the button that runs a step.
 *
 * @return array  action => [label, icon]
 */
function erp_order_flow_actions()
{
    return array(
        'invoice' => array(lang('Issue the Invoice'), 'bi-receipt'),
        'edoc_send' => array(lang('Make it official'), 'bi-send'),
        'edoc_submit' => array(lang('Hand to GİB'), 'bi-bank'),
        'edoc_poll' => array(lang('Ask status'), 'bi-arrow-repeat'),
        'waybill' => array(lang('Issue the Delivery Note'), 'bi-truck'),
        'ewaybill' => array(lang('Issue the e-Delivery Note'), 'bi-send'),
        'invoice_cancel' => array(lang('Cancel the invoice'), 'bi-x-circle'),
    );
}

/**
 * Runs one step for an order. Every step is the function its own document
 * screen calls; this only picks it and reports back in one shape.
 *
 * @param int    $order_id
 * @param string $action   A key of erp_order_flow_actions()
 * @param int    $user_id
 * @return array ['success' => bool, 'messages' => string[], 'error' => string]
 */
function erp_order_flow_run($order_id, $action, $user_id = 0)
{
    $state = erp_order_flow_state($order_id);

    if ($state === null) {
        return array('success' => false, 'messages' => array(), 'error' => lang('Order not found.'));
    }

    // Only what the screen offered: a stale page cannot run a step that has
    // since been done or has become impossible.
    $offered = false;
    foreach ($state['steps'] as $step) {
        if ($step['action'] === $action) {
            $offered = true;
        }
    }

    if (!$offered) {
        return array('success' => false, 'messages' => array(), 'error' => lang('This step is not available for the order any more; the screen has been refreshed.'));
    }

    $invoice_id = is_array($state['invoice']) ? (int) $state['invoice']['id'] : 0;

    switch ($action) {
        case 'invoice':
            $result = erp_invoice_from_order((int) $order_id, array('created_by' => (int) $user_id));
            if (!$result['success']) {
                return array('success' => false, 'messages' => array(), 'error' => $result['error']);
            }
            log_activity(lang(array('string' => 'erp invoice ({var:1}) was created', 'vars' => $result['full_number'])));
            $messages = array(lang(array('string' => 'Invoice {var:1} created.', 'vars' => $result['full_number'])));

            // A delivery note written before the invoice is tied to it now,
            // and what the note knows (ship date, carrier) goes onto the
            // invoice where it is still empty - the same write-back a note
            // written after the invoice does.
            if (is_array($state['waybill']) && ((int) $state['waybill']['invoice_id'] === 0)) {
                erp_query("UPDATE erp_waybills SET invoice_id = '" . (int) $result['invoice_id'] . "', updated_at = '" . time() . "'
                    WHERE id = '" . (int) $state['waybill']['id'] . "' AND invoice_id = 0");
                $written_back = erp_waybill_write_back((int) $state['waybill']['id']);
                if (isset($written_back['invoice_shipment_date']) || isset($written_back['invoice_carrier'])) {
                    $messages[] = lang('The shipment date and the carrier were written onto the invoice, which had none.');
                }
            }

            return array('success' => true, 'error' => '', 'messages' => $messages);

        case 'edoc_send':
            $result = erp_edoc_invoice_send($invoice_id, (int) $user_id);
            break;

        case 'edoc_submit':
            $result = erp_edoc_invoice_submit($invoice_id, (int) $user_id);
            break;

        case 'edoc_poll':
            $result = erp_edoc_invoice_poll($invoice_id);
            break;

        case 'waybill':
            $read = ($invoice_id > 0) ? erp_waybill_data_from_invoice($invoice_id, (int) $user_id) : erp_waybill_data_from_order((int) $order_id, (int) $user_id);
            if (!$read['success']) {
                return array('success' => false, 'messages' => array(), 'error' => $read['error']);
            }
            $data = $read['data'];
            $data['created_by'] = (int) $user_id;
            $result = erp_waybill_create($data);
            if (!$result['success']) {
                return array('success' => false, 'messages' => array(), 'error' => $result['error']);
            }
            log_activity(lang(array('string' => 'erp delivery note ({var:1}) was created', 'vars' => $result['full_number'])));
            $messages = array(lang(array('string' => 'Delivery note {var:1} created.', 'vars' => $result['full_number'])));
            $written_back = (array) ($result['written_back'] ?? array());
            if (isset($written_back['invoice_shipment_date']) || isset($written_back['invoice_carrier'])) {
                $messages[] = lang('The shipment date and the carrier were written onto the invoice, which had none.');
            }
            return array('success' => true, 'error' => '', 'messages' => $messages);

        case 'ewaybill':
            $result = erp_edoc_waybill_send((int) $state['waybill']['id'], (int) $user_id);
            break;

        case 'invoice_cancel':
            // The allocations go first: the money they paired stays on the
            // account as a credit, which the refund step then pays out. A
            // receipt spent wholly on this invoice is tied to the order
            // instead, so the refund step still knows it came in for it.
            $cancel_id = $invoice_id;

            // The provider first: a draft there is deleted, a document there
            // must have been let go. If it will not, nothing changes here.
            $provider_cancel = erp_edoc_invoice_provider_cancel($cancel_id, lang('The order was cancelled.'), (int) $user_id);
            if (!$provider_cancel['success']) {
                return array('success' => false, 'messages' => array(), 'error' => (string) $provider_cancel['error']);
            }
            $settlements = (array) db_items("SELECT s.id, s.amount_base, t.doc_type AS source_type, t.doc_id AS cash_id FROM erp_settlements s
                LEFT JOIN erp_account_transactions t ON s.account_txn_id = t.id
                WHERE s.invoice_id = '" . $cancel_id . "' AND (t.id IS NULL OR t.doc_type <> 'gift_card')");
            if (erp_overdue_column_exists('erp_cash_transactions', 'order_id')) {
                foreach ($settlements as $settlement) {
                    if ((string) $settlement['source_type'] !== 'collection') {
                        continue;
                    }
                    $cash = db_item("SELECT c.id, c.amount_base, c.order_id,
                            (SELECT COALESCE(SUM(s2.amount_base), 0) FROM erp_settlements s2
                                INNER JOIN erp_account_transactions t2 ON s2.account_txn_id = t2.id
                                WHERE t2.doc_type = 'collection' AND t2.doc_id = c.id) AS allocated
                        FROM erp_cash_transactions c WHERE c.id = '" . (int) $settlement['cash_id'] . "' LIMIT 1");
                    if (is_array($cash) && ((int) $cash['order_id'] === 0)
                        && ((int) $cash['allocated'] === (int) $settlement['amount_base'])
                        && ((int) $cash['amount_base'] === (int) $settlement['amount_base'])) {
                        erp_query("UPDATE erp_cash_transactions SET order_id = '" . (int) $state['order']['id'] . "' WHERE id = '" . (int) $cash['id'] . "'");
                    }
                }
            }
            foreach ($settlements as $settlement) {
                $freed = erp_settlement_remove((int) $settlement['id'], (int) $user_id);
                if (!$freed['success']) {
                    return array('success' => false, 'messages' => array(), 'error' => (string) $freed['error']);
                }
            }
            $result = erp_invoice_cancel($cancel_id, (int) $user_id);
            if (!$result['success']) {
                return array('success' => false, 'messages' => array(), 'error' => (string) $result['error']);
            }
            log_activity(lang(array('string' => 'erp invoice for cancelled order ({var:1}) was cancelled', 'vars' => (string) $state['order']['order_number'])));
            return array('success' => true, 'error' => '', 'messages' => array_values(array_filter(array((string) $provider_cancel['message'], lang('The invoice has been cancelled.')))));

        default:
            return array('success' => false, 'messages' => array(), 'error' => lang('This step is not available for the order any more; the screen has been refreshed.'));
    }

    if (!$result['success']) {
        return array('success' => false, 'messages' => array(), 'error' => (string) $result['error']);
    }

    return array('success' => true, 'error' => '', 'messages' => array_filter(array((string) ($result['message'] ?? ''))));
}

/**
 * The order screen's documents card.
 *
 * @param int        $order_id
 * @param array|null $state  From erp_order_flow_state(), when the caller has it
 * @return string  '' for an order that is not there
 */
function erp_order_flow_card($order_id, $state = null)
{
    if ($state === null) {
        $state = erp_order_flow_state($order_id);
    }

    if ($state === null) {
        return '';
    }

    $actions = erp_order_flow_actions();
    $tones = array(
        'done' => array('bi-check-circle-fill', 'text-success'),
        'ready' => array('bi-circle', 'text-primary'),
        'waiting' => array('bi-hourglass-split', 'text-primary'),
        'blocked' => array('bi-exclamation-triangle-fill', 'text-warning'),
        'unavailable' => array('bi-dash-circle', 'text-body-secondary'),
        'later' => array('bi-circle', 'text-body-secondary'),
    );

    $form_id = 'erp_order_flow_form';
    $button = function ($action, $primary, $label = '') use ($actions, $form_id) {
        $words = $actions[$action];
        if ($label !== '') {
            $words[0] = $label;
        }
        return '<button type="submit" form="' . $form_id . '" name="erp_step" value="' . h($action) . '" class="btn btn-sm ' . ($primary ? 'btn-primary rounded-pill px-3' : 'btn-outline-secondary') . '" data-loading-content="' . lang(array('string' => 'Loading')) . '"><i class="bi ' . $words[1] . ' me-1" aria-hidden="true"></i>' . h($words[0]) . '</button>';
    };

    $output_steps = '';
    $number = 0;

    foreach ($state['steps'] as $key => $step) {
        $number++;
        $tone = $tones[$step['state']] ?? $tones['later'];
        $is_next = ($key === $state['next']);

        $facts = '';
        foreach ($step['facts'] as $fact) {
            if (is_array($fact) && isset($fact['badge'])) {
                $facts .= ' <span class="badge text-bg-' . h($fact['badge'][1]) . '">' . h($fact['badge'][0]) . '</span>';
            } elseif (is_array($fact) && isset($fact['text'])) {
                $facts .= '<div class="small text-body-secondary text-break">' . h($fact['text']) . '</div>';
            } else {
                $facts .= ' <span class="text-body-secondary">' . h((string) $fact) . '</span>';
            }
        }

        // The document's own buttons: open it, its PDF, the form or card that
        // completes it. Buttons rather than links so they read as things to
        // do, the way the step's action does.
        $links = '';
        foreach ($step['links'] as $link) {
            $links .= '<a href="' . h($link['url']) . '" class="btn btn-sm btn-outline-secondary"' . (!empty($link['blank']) ? ' target="_blank" rel="noopener"' : '') . '>' . h($link['label']) . '</a>';
        }

        // A step that cannot run yet still shows its button, greyed, with the
        // reason on it - so the operator sees where the invoice and the
        // delivery note are made and why not now.
        $disabled = '';
        if (!empty($step['disabled_action']) && ($step['action'] === '') && isset($actions[$step['disabled_action']])) {
            $words = $actions[$step['disabled_action']];
            $disabled = '<div class="text-nowrap"><span class="d-inline-block" tabindex="0" title="' . h($step['reason']) . '"><button type="button" class="btn btn-sm btn-outline-secondary" disabled><i class="bi ' . $words[1] . ' me-1" aria-hidden="true"></i>' . h($words[0]) . '</button></span></div>';
        }

        $output_steps .= '
                <li class="list-group-item d-flex gap-3 align-items-start' . ($is_next ? ' bg-body-tertiary' : '') . '">
                    <i class="bi ' . $tone[0] . ' ' . $tone[1] . ' fs-5 mt-1" aria-hidden="true"></i>
                    <div class="flex-grow-1 min-w-0">
                        <div class="d-flex flex-wrap align-items-baseline gap-2">
                            <span class="fw-semibold">' . $number . '. ' . h($step['label']) . '</span>
                            ' . (($step['title'] !== '') ? '<span class="text-nowrap">' . h($step['title']) . '</span>' : '') . $facts . '
                        </div>
                        ' . (($step['reason'] !== '') ? '<div class="small ' . (($step['state'] === 'blocked') ? 'text-warning-emphasis' : 'text-body-secondary') . '">' . h($step['reason']) . '</div>' : '') . '
                        ' . (($links !== '') ? '<div class="d-flex flex-wrap gap-2 mt-2">' . $links . '</div>' : '') . '
                    </div>
                    ' . (!empty($step['print']) ? '<div class="text-nowrap"><a class="btn btn-sm btn-outline-primary" href="' . h($step['print']['url']) . '" target="_blank" rel="noopener"><i class="bi bi-printer me-1" aria-hidden="true"></i>' . h($step['print']['label']) . '</a></div>' : '') . '
                    ' . ((($step['action'] !== '') && !$is_next) ? '<div class="text-nowrap">' . $button($step['action'], false, (string) ($step['action_label'] ?? '')) . '</div>' : '') . '
                    ' . (($is_next && ($step['action'] !== '')) ? '<div class="text-nowrap">' . $button($step['action'], true, (string) ($step['action_label'] ?? '')) . '</div>' : '') . '
                    ' . (!empty($step['goto']) ? '<div class="text-nowrap"><a href="' . h($step['goto']['url']) . '" class="btn btn-sm ' . ($is_next ? 'btn-primary rounded-pill px-3' : 'btn-outline-secondary') . '"><i class="bi ' . h($step['goto']['icon']) . ' me-1" aria-hidden="true"></i>' . h($step['goto']['label']) . '</a></div>' : '') . '
                    ' . $disabled . '
                </li>';
    }

    $all_done = true;
    foreach ($state['steps'] as $step) {
        if (!in_array($step['state'], array('done', 'unavailable'), true)) {
            $all_done = false;
        }
    }

    return '
<div class="card mb-4 d-print-none" id="erp_order_documents">
    <div class="card-header bg-reset border-0 d-flex justify-content-between align-items-center">
        <span class="text-uppercase h5 text-primary fw-bold mb-0">' . lang('Invoice and delivery documents') . '</span>
        ' . ($all_done ? '<span class="small text-success"><i class="bi bi-check2-all me-1" aria-hidden="true"></i>' . lang('Every document is done.') : '') . '
    </div>
    <ul class="list-group list-group-flush">' . $output_steps . '
    </ul>
</div>
<form id="' . $form_id . '" method="post" action="erp_order_flow.php" class="d-none">
    ' . get_token_field() . '
    <input type="hidden" name="order_id" value="' . (int) $order_id . '" />
</form>';
}
