<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * ERP - the e-document service: what the screens call when an invoice is to
 * go to the tax authority through the active provider, and what they read
 * back. The driver layer (registry.php) speaks to the provider; this file
 * owns the invoice row - edoc_provider, edoc_external_id, edoc_status,
 * gib_number, gib_uuid, edoc_error, edoc_sent_at - and the rules of when a
 * document may go.
 *
 * A document, once carried by a provider, stays with that provider: every
 * later question (status, PDF, XML) goes to erp_invoices.edoc_provider, not
 * to whichever provider is active today.
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
 * The words the screens print for erp_invoices.edoc_status.
 *
 * @return array  status => [label, bootstrap tone]
 */
function erp_edoc_status_labels()
{
    return array(
        'none' => array(lang('Not sent'), 'secondary'),
        'queued' => array(lang('Queued'), 'info'),
        'sending' => array(lang('Sending'), 'info'),
        'sent' => array(lang('Sent, awaiting GİB'), 'primary'),
        'accepted' => array(lang('Accepted by GİB'), 'success'),
        'rejected' => array(lang('Rejected'), 'danger'),
        'error' => array(lang('Error'), 'danger'),
    );
}

/**
 * May this invoice be sent now, and if not, why not - in the operator's words.
 *
 * @param array $invoice  An erp_invoices row
 * @return array ['ok' => bool, 'reason' => string, 'provider' => string]
 */
function erp_edoc_invoice_can_send($invoice)
{
    $provider = erp_edoc_active();

    if ($provider === '') {
        return array('ok' => false, 'provider' => '', 'reason' => lang('No e-document provider is selected on the E-Invoice card of the commerce settings.'));
    }

    if (!erp_edoc_supports('send_invoice', $provider)) {
        return array('ok' => false, 'provider' => $provider, 'reason' => lang(array('string' => 'The {var:1} driver does not send invoices yet.', 'vars' => erp_edoc_info($provider)['label'])));
    }

    if ((string) $invoice['direction'] !== 'sales') {
        return array('ok' => false, 'provider' => $provider, 'reason' => lang('Only sales documents are sent; a purchase invoice is the supplier\'s to issue.'));
    }

    if (in_array((string) $invoice['status'], array('draft', 'cancelled'), true)) {
        return array('ok' => false, 'provider' => $provider, 'reason' => lang('A draft or a cancelled document is not sent.'));
    }

    if (in_array((string) $invoice['edoc_status'], array('queued', 'sending', 'sent', 'accepted'), true)) {
        return array('ok' => false, 'provider' => $provider, 'reason' => lang('The document has already gone; ask after its status instead of sending it twice.'));
    }

    return array('ok' => true, 'provider' => $provider, 'reason' => '');
}

/**
 * Sends an invoice through the active provider and writes the outcome on
 * the row. The operator sees the provider's own message on failure.
 *
 * @param int $invoice_id
 * @param int $user_id
 * @return array ['success' => bool, 'message' => string, 'error' => string]
 */
function erp_edoc_invoice_send($invoice_id, $user_id = 0)
{
    $invoice_id = (int) $invoice_id;
    $invoice = db_item("SELECT * FROM erp_invoices WHERE id = '" . $invoice_id . "' LIMIT 1");

    if (!is_array($invoice)) {
        return array('success' => false, 'message' => '', 'error' => lang('The invoice could not be found.'));
    }

    $gate = erp_edoc_invoice_can_send($invoice);

    if (!$gate['ok']) {
        return array('success' => false, 'message' => '', 'error' => $gate['reason']);
    }

    $provider = $gate['provider'];
    $lines = (array) db_items("SELECT * FROM erp_invoice_items WHERE invoice_id = '" . $invoice_id . "' ORDER BY line_no ASC, id ASC");

    // Marked before the call: a second click while the first is in flight
    // finds the row "sending" and stops at the gate.
    erp_query("UPDATE erp_invoices SET edoc_status = 'sending', edoc_error = '', updated_at = '" . time() . "' WHERE id = '" . $invoice_id . "'");

    $result = erp_edoc_call('send_invoice', array($invoice, $lines, array('party' => erp_edoc_invoice_party($invoice))), $provider);

    if (empty($result['success'])) {
        erp_query("UPDATE erp_invoices
            SET edoc_status = 'error', edoc_error = '" . escape(mb_substr((string) $result['error'], 0, 2000)) . "', updated_at = '" . time() . "'
            WHERE id = '" . $invoice_id . "'");

        return array('success' => false, 'message' => '', 'error' => (string) $result['error']);
    }

    erp_query("UPDATE erp_invoices
        SET edoc_provider = '" . escape($provider) . "',
            edoc_external_id = '" . escape(mb_substr((string) ($result['external_id'] ?? ''), 0, 64)) . "',
            edoc_status = 'sent',
            edoc_error = '',
            edoc_sent_at = '" . time() . "',
            updated_at = '" . time() . "'
        WHERE id = '" . $invoice_id . "'");

    if (function_exists('log_activity')) {
        log_activity(lang(array('string' => 'erp invoice ({var:1}) was sent to {var:2} as an e-document', 'vars' => array((string) $invoice['full_number'], erp_edoc_info($provider)['label']))),
            (string) ($_SESSION['sessionusername'] ?? ''));
    }

    if (function_exists('erp_event_invoice')) {
        erp_event_invoice($invoice_id, 'erp.invoice.edoc_changed');
    }

    return array(
        'success' => true,
        'message' => lang(array('string' => '{var:1} took the invoice (id {var:2}). Ask after its status in a minute for the GİB number.', 'vars' => array(erp_edoc_info($provider)['label'], (string) ($result['external_id'] ?? '')))),
        'error' => '',
    );
}

/**
 * Asks the carrying provider where a sent invoice stands and writes what
 * it hears: GİB number, ETTN, status.
 *
 * @param int $invoice_id
 * @return array ['success' => bool, 'message' => string, 'error' => string, 'status' => string]
 */
function erp_edoc_invoice_poll($invoice_id)
{
    $invoice_id = (int) $invoice_id;
    $invoice = db_item("SELECT * FROM erp_invoices WHERE id = '" . $invoice_id . "' LIMIT 1");

    if (!is_array($invoice)) {
        return array('success' => false, 'message' => '', 'error' => lang('The invoice could not be found.'), 'status' => '');
    }

    $provider = (string) $invoice['edoc_provider'];

    if (($provider === '') || ((string) $invoice['edoc_external_id'] === '')) {
        return array('success' => false, 'message' => '', 'error' => lang('The document has not been sent to a provider.'), 'status' => (string) $invoice['edoc_status']);
    }

    if (!erp_edoc_supports('poll', $provider)) {
        return array('success' => false, 'message' => '', 'error' => lang(array('string' => 'The {var:1} driver cannot ask after a document yet.', 'vars' => erp_edoc_info($provider)['label'])), 'status' => (string) $invoice['edoc_status']);
    }

    $result = erp_edoc_call('poll', array($invoice, (string) $invoice['edoc_external_id']), $provider);

    if (empty($result['success'])) {
        return array('success' => false, 'message' => '', 'error' => (string) $result['error'], 'status' => (string) $invoice['edoc_status']);
    }

    $status = (string) ($result['status'] ?? 'sent');
    $known = array_keys(erp_edoc_status_labels());
    $status = in_array($status, $known, true) ? $status : 'sent';
    $before = (string) $invoice['edoc_status'];

    $set = array(
        "edoc_status = '" . escape($status) . "'",
        "edoc_error = '" . escape(mb_substr((string) ($result['message'] ?? ''), 0, 2000)) . "'",
        "updated_at = '" . time() . "'",
    );

    if (trim((string) ($result['gib_number'] ?? '')) !== '') {
        $set[] = "gib_number = '" . escape(mb_substr(trim((string) $result['gib_number']), 0, 20)) . "'";
    }

    if (trim((string) ($result['gib_uuid'] ?? '')) !== '') {
        $set[] = "gib_uuid = '" . escape(mb_substr(trim((string) $result['gib_uuid']), 0, 36)) . "'";
    }

    erp_query("UPDATE erp_invoices SET " . implode(', ', $set) . " WHERE id = '" . $invoice_id . "'");

    if (($status !== $before) && function_exists('erp_event_invoice')) {
        erp_event_invoice($invoice_id, 'erp.invoice.edoc_changed');
    }

    $labels = erp_edoc_status_labels();
    $message = $labels[$status][0];

    if (trim((string) ($result['gib_number'] ?? '')) !== '') {
        $message .= ' · ' . trim((string) $result['gib_number']);
    }

    if (trim((string) ($result['message'] ?? '')) !== '') {
        $message .= ' — ' . trim((string) $result['message']);
    }

    return array('success' => true, 'message' => $message, 'error' => '', 'status' => $status);
}

/**
 * The provider's copy of a sent document.
 *
 * @param int    $invoice_id
 * @param string $format  'pdf' | 'xml'
 * @return array ['success' => bool, 'content' => string, 'filename' => string, 'mime' => string, 'error' => string]
 */
function erp_edoc_invoice_document($invoice_id, $format = 'pdf')
{
    $invoice = db_item("SELECT * FROM erp_invoices WHERE id = '" . (int) $invoice_id . "' LIMIT 1");
    $none = array('success' => false, 'content' => '', 'filename' => '', 'mime' => '', 'error' => '');

    if (!is_array($invoice)) {
        return array_merge($none, array('error' => lang('The invoice could not be found.')));
    }

    $provider = (string) $invoice['edoc_provider'];

    if ($provider === '') {
        return array_merge($none, array('error' => lang('The document has not been sent to a provider.')));
    }

    if (!erp_edoc_supports('fetch_document', $provider)) {
        return array_merge($none, array('error' => lang(array('string' => 'The {var:1} driver does not hand documents back yet.', 'vars' => erp_edoc_info($provider)['label']))));
    }

    return array_merge($none, erp_edoc_call('fetch_document', array($invoice, $format), $provider));
}
